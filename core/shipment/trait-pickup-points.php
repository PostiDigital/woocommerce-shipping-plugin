<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * Pickup point search/lookup and per-service pickup point capability checks.
 */
trait Shipment_Pickup_Points_Trait {

  public function get_pickup_point_methods() {
    $methods = array(
    );

    return $methods;
  }

  /**
   * ...
   *
   * @param WC_Order $order The order that is currently being viewed in wp-admin
   * @param bool $return_default_shipping_method
   *
   * @return int|null
   */
  public function get_service_id_from_order( \WC_Order $order, $return_default_shipping_method = true ) {
    if ( $order === null ) {
      return null;
    }

    $service_id = $order->get_meta('_' . $this->core->prefix . '_service_id', true);

    if ( empty($service_id) ) {
      $shipping_methods = $order->get_shipping_methods();

      $shipping_method = array_pop($shipping_methods);

      if ( ! empty($shipping_method) ) {
        $service_id = $shipping_method->get_meta('service_code');
      }
    }

    if ( empty($service_id) ) { //Dedicated for multi labels, but not sure if that’s useful
      $labels = $this->get_labels($order->get_id());
      foreach ( $labels as $label ) {
        if ( ! empty($label['service_id']) ) {
          $service_id = $label['service_id'];
          break;
        }
      }
    }

    if ( empty($service_id) ) {
      $service_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_provider_id', true);
    }

    if ( empty($service_id) ) {
      $shipping_methods = $order->get_shipping_methods();
      $chosen_shipping_method = array_pop($shipping_methods);

      if ( ! empty($chosen_shipping_method) ) {
        $method_id = $chosen_shipping_method->get_method_id();

        if ( $method_id === 'local_pickup' ) {
          return null;
        }

        $instance_id = $chosen_shipping_method->get_instance_id();
        $settings = $this->get_settings();
        $pickup_points = json_decode($settings['pickup_points'], true);

        if ( ! empty($pickup_points[ $instance_id ]['service']) ) {
          $service_id = $pickup_points[ $instance_id ]['service'];
        }

        if ( ! isset($pickup_points[ $instance_id ]) ) {
          return null;
        }
      }
    }

    if ( $service_id === '__NULL__' ) {
      return null;
    }

    if ( empty($service_id) && $return_default_shipping_method ) {
      $service_id = self::get_default_service();
    }

    if ( $service_id === '__PICKUPPOINTS__' ) {
        // This might be a bug or a version update problem
        $pickup_point = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', true);

        $provider = explode(':', $pickup_point, 2);

        $service_id = null;
        if ( ! empty($provider) ) {
            $methods = array_flip($this->get_pickup_point_methods());
            if ( isset($provider[0]) && isset($methods[$provider[0]]) ) {
              $service_id = $methods[$provider[0]];
            }
        }
    }

    return $service_id;
  }

  /**
   * Return pickup points near a location specified by the parameters.
   *
   * @param int $postcode The postcode of the pickup point
   * @param string $street_address The street address of the pickup point
   * @param string $country The country in which the pickup point is located
   * @param string $service_provider A service that should be provided by the pickup point
   *
   * @return array The pickup points based on the parameters, or empty array if none were found
   * @throws Exception
   */
  public function get_pickup_points( $postcode, $street_address = null, $country = null, $service_provider = null, $type = null ) {
    $pickup_point_limit = 5; // Default limit value for pickup point search
    $pickup_points_type = null; // Default pickup points type. null = all.

    if ( isset($this->settings['pickup_points_search_limit']) && ! empty($this->settings['pickup_points_search_limit']) ) {
      $pickup_point_limit = intval($this->settings['pickup_points_search_limit']);
    }
    if ( ! $type && isset($this->settings['pickup_points_type']) && ! empty($this->settings['pickup_points_type']) && ! in_array('all', $this->settings['pickup_points_type']) ) {
      $pickup_points_type = implode(',', $this->settings['pickup_points_type']);
    } else {
      $pickup_points_type = $type;
    }

    $pickup_point_data = $this->client->searchPickupPoints(trim($postcode), trim($street_address), trim($country), $service_provider, $pickup_point_limit, $pickup_points_type);

    if ( $pickup_point_data === 'Bad request' ) {
      throw new \Exception(__('An error occurred while searching for pickup points.', 'woo-pakettikauppa'));
    }

    // This makes zero sense unless you read this issue:
    // https://github.com/Pakettikauppa/api-library/issues/11
    if ( empty($pickup_point_data) ) {
      throw new \Exception(__('No pickup points were found. Check the address.', 'woo-pakettikauppa'));
    }

    return $pickup_point_data;
  }

  public function get_pickup_points_by_free_input( $input, $service_provider = null, $type = null ) {
    $pickup_point_limit = 5; // Default limit value for pickup point search
    $pickup_points_type = null; // Default pickup points type. null = all.

    if ( isset($this->settings['pickup_points_search_limit']) && ! empty($this->settings['pickup_points_search_limit']) ) {
      $pickup_point_limit = intval($this->settings['pickup_points_search_limit']);
    }
    if ( ! $type && isset($this->settings['pickup_points_type']) && ! empty($this->settings['pickup_points_type']) && ! in_array('all', $this->settings['pickup_points_type']) ) {
      $pickup_points_type = implode(',', $this->settings['pickup_points_type']);
    } else {
      $pickup_points_type = $type;
    }

    $pickup_point_data = $this->client->searchPickupPointsByText(trim($input), $service_provider, $pickup_point_limit, $pickup_points_type);

    if ( $pickup_point_data === 'Bad request' ) {
      throw new \Exception(__('An error occurred while searching for pickup points.', 'woo-pakettikauppa'));
    }

    // This makes zero sense unless you read this issue:
    // https://github.com/Pakettikauppa/api-library/issues/11
    if ( empty($pickup_point_data) ) {
      throw new \Exception(__('No pickup points were found. Check the address.', 'woo-pakettikauppa'));
    }

    return $pickup_point_data;
  }

  /**
   * Get the title of a service by providing its code.
   *
   * @param int $service_code The code of a service
   *
   * @return string The service title matching with the provided code, or false if not found
   */
  public function service_title( $service_code ) {

    $services = $this->services();
    if ( isset($services[ $service_code ]) ) {
      return $services[ $service_code ];
    }

    return false;
  }

  /**
   * Get the provider of a service by providing its code.
   *
   * @param int $service_code The code of a service
   *
   * @return string The service provider matching with the provided code, or false if not found
   */
  public function service_provider( $service_code ) {
    $all_shipping_methods = $this->get_shipping_methods();

    if ( $all_shipping_methods === null ) {
      return false;
    }

    foreach ( $all_shipping_methods as $shipping_method ) {
      if ( strval($service_code) === strval($shipping_method->shipping_method_code) ) {
        return $shipping_method->service_provider;
      }
    }

    return false;
  }

  /**
   * Returns information if this shipping service supports pickup points
   *
   * @param $service_id
   *
   * @return bool
   */
  public function service_has_pickup_points( $service_id ) {
    $all_shipping_methods = $this->get_shipping_methods();

    if ( $all_shipping_methods === null ) {
      return false;
    }

    foreach ( $all_shipping_methods as $shipping_method ) {
      if ( strval($shipping_method->shipping_method_code) === strval($service_id) ) {
        return $shipping_method->has_pickup_points;
      }
    }

    return false;
  }

  /**
   * Check if service is one from services, for which by default do not load the selection of pickup point, but allow receiving pickup points when using custom search
   * 
   * @param $service_id
   * 
   * @return bool
   */
  public function is_optional_pickup_point_service( $service_id ) {
    return (in_array($service_id, array('2101', '2102', '2711')));
  }
}
