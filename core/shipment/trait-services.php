<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * Fetching, caching and resolving carrier shipping methods and their additional services.
 */
trait Shipment_Services_Trait {

  /**
   * Resolve language for services list based on current admin locale.
   *
   * @return string Two-letter language code supported by API.
   */
  public function get_services_language() {
    $locale = get_locale();

    // In admin-ajax context determine_locale() can resolve to site locale,
    // so prefer current user's admin locale for settings UI language.
    if ( function_exists('get_user_locale') ) {
      $locale = get_user_locale();
    } elseif ( function_exists('determine_locale') ) {
      $locale = determine_locale();
    }

    $language = strtolower(substr(strval($locale), 0, 2));
    $supported_languages = array( 'fi', 'en' );

    if ( ! in_array($language, $supported_languages, true) ) {
      $language = 'en';
    }

    return $language;
  }

  /**
   * Get all available shipping services.
   *
   * @param array $params Parameters to pass to the API request
   *
   * @return array Available shipping services
   */
  public function services($params = array()) {
    if ( empty($params['language']) ) {
      $params['language'] = $this->get_services_language();
    }

    $services = array();

    $all_shipping_methods = $this->get_shipping_methods($params);

    // List all available methods as shipping options on checkout page
    if ( $all_shipping_methods === null ) {
      // returning null seems to invalidate services cache
      return null;
    }

    foreach ( $all_shipping_methods as $shipping_method ) {
      $services[ strval($shipping_method->shipping_method_code) ] = sprintf('%1$s: %2$s', $shipping_method->service_provider, $shipping_method->name);
    }

    ksort($services);

    return $services;
  }

  public function get_additional_services_from_order( \WC_Order $order ) {
    $additional_services = array();

    $settings = $this->get_settings();

    $shipping_methods = $order->get_shipping_methods();

    $chosen_shipping_method = array_pop($shipping_methods);

    $add_cod_to_additional_services = 'cod' === $order->get_payment_method();
    $add_dangerous_good_to_additional_services = false;

    $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, 'kg');

    if ( ! empty($chosen_shipping_method) ) {
      $method_id = $chosen_shipping_method->get_method_id();

      if ( $method_id === 'local_pickup' ) {
        return $additional_services;
      }

      $instance_id = $chosen_shipping_method->get_instance_id();

      $pickup_points = json_decode($settings['pickup_points'], true);

      if ( ! empty($pickup_points[ $instance_id ]['service']) ) {
        $service_id = $pickup_points[ $instance_id ]['service'];

        $services = array();

        if ( ! empty($pickup_points[ $instance_id ][ $service_id ]) && isset($pickup_points[ $instance_id ][ $service_id ]['additional_services']) ) {
          $services = $pickup_points[ $instance_id ][ $service_id ]['additional_services'];
        }

        if ( ! empty($services) ) {
          $check_separately = array( '3101', '3143' );
          foreach ( $services as $service_code => $service ) {
            if ( $service !== 'yes' ) {
              continue;
            }
            if ( $service_code == 'return_label' ) {
              continue;
            }
            if ( ! in_array($service_code, $check_separately) ) {
              $additional_services[] = array( $service_code => null );
            }
            if ( $service_code == '3101' ) {
              $add_cod_to_additional_services = true;
            }
            if ( $dangerous_goods['count'] > 0 && $service_code == '3143' ) {
              $add_dangerous_good_to_additional_services = true;
            }
          }
        }
      }
    }

    if ( $add_cod_to_additional_services ) {
      $additional_services[] = array(
        '3101' => array(
          'amount' => $order->get_total(),
          'account' => $settings['cod_iban'],
          'codbic' => $settings['cod_bic'],
          'reference' => $this->calculate_reference($order->get_id()),
        ),
      );
    }

    if ( $add_dangerous_good_to_additional_services ) {
      $additional_services[] = array(
        '3143' => array(
          'lqweight' => $dangerous_goods['weight'],
          'lqcount' => $dangerous_goods['count'],
        ),
      );
    }

    return $additional_services;
  }


  public function get_additional_services( $params = array() ) {
    if ( empty($params['language']) ) {
      $params['language'] = $this->get_services_language();
    }

    $all_shipping_methods = $this->get_shipping_methods($params);

    if ( $all_shipping_methods === null ) {
      return null;
    }

    $additional_services = array();
    foreach ( $all_shipping_methods as $shipping_method ) {
      $additional_services[ strval($shipping_method->shipping_method_code) ] = $shipping_method->additional_services;
    }

    return $additional_services;
  }

  /**
   * Fetch shipping methods from the Pakettikauppa and returns it as objects
   *
   * @param array $params Parameters to pass to the API request
   *
   * @return mixed
   */
  private function get_shipping_methods($params = array()) {
    $transient_name = $this->get_shipping_methods_transient_name($params);
    $transient_time = 86400; // 24 hours

    $all_shipping_methods = get_transient($transient_name);

    if ( empty($all_shipping_methods) ) {
      try {
        $all_shipping_methods = $this->client->listShippingMethods($params);
      } catch ( \Exception $ex ) {
        $all_shipping_methods = null;
      }

      if ( ! empty($all_shipping_methods) ) {
        set_transient($transient_name, $all_shipping_methods, $transient_time);
        $this->register_shipping_methods_transient($transient_name);
      }
    }

    if ( empty($all_shipping_methods) ) {
      return null;
    }

    return $all_shipping_methods;
  }

  /**
   * Build the transient name for cached shipping methods, varying by sender
   * country and language so different combinations are cached separately.
   *
   * @param array $params Parameters passed to the API request
   *
   * @return string
   */
  private function get_shipping_methods_transient_name($params = array()) {
    $base = $this->core->prefix . '_shipping_methods';

    $key_parts = array();
    if ( ! empty($params['sender_country']) ) {
      $key_parts[] = 'c' . sanitize_key($params['sender_country']);
    }
    if ( ! empty($params['language']) ) {
      $key_parts[] = 'l' . sanitize_key($params['language']);
    }

    if ( empty($key_parts) ) {
      return $base;
    }

    return $base . '_' . implode('_', $key_parts);
  }

  /**
   * Keep track of every shipping methods transient that gets created so all
   * cached variants (per country/language) can be purged at once later.
   *
   * @param string $transient_name
   *
   * @return void
   */
  private function register_shipping_methods_transient( $transient_name ) {
    $index_key = $this->core->prefix . '_shipping_methods_index';

    $index = get_option($index_key, array());
    if ( ! is_array($index) ) {
      $index = array();
    }

    if ( ! in_array($transient_name, $index, true) ) {
      $index[] = $transient_name;
      update_option($index_key, $index, false);
    }
  }

  /**
   * Delete every cached shipping methods variant (all countries/languages).
   *
   * @return void
   */
  public function delete_shipping_methods_cache() {
    $index_key = $this->core->prefix . '_shipping_methods_index';

    $index = get_option($index_key, array());
    if ( is_array($index) ) {
      foreach ( $index as $transient_name ) {
        delete_transient($transient_name);
      }
    }

    // Also delete the base transient in case of legacy cache without an index entry.
    delete_transient($this->core->prefix . '_shipping_methods');
    delete_option($index_key);
  }
}
