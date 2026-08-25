<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use Pakettikauppa\Shipment as PK_Shipment;
use Pakettikauppa\Shipment\ContentLine;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Parcel;

/**
 * Shipment creation: building the API payload from an order and sending it,
 * price estimation and API credentials check.
 */
trait Shipment_Creation_Trait {

  public function get_estimated_shipping_price( \WC_Order $order, $service_id = null, $additional_services = null, $selected_products = array() ) {
    $estimated_price = 0;

    if ( ! self::validate_order_shipping_receiver($order) ) {
      return $estimated_price;
    }

    if ( $additional_services === null ) {
      $additional_services = $this->get_additional_services_from_order($order);

      $pickup_point_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');

      if ( ! empty($pickup_point_id) ) {
        $additional_services[] = array(
          '2106' => array(
            'pickup_point_id' => $pickup_point_id,
          ),
        );
      }

      if ( ! empty($selected_products) ) {
        $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
      } else {
        $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, 'kg');
      }
      $count_services = count($additional_services);
      for ( $i = 0; $i < $count_services; $i++ ) {
        if ( isset($additional_services[$i]['3143']) && $additional_services[$i]['3143']['lqweight'] != $dangerous_goods['weight'] ) {
          $additional_services[$i]['3143']['lqweight'] = $dangerous_goods['weight'];
          $additional_services[$i]['3143']['lqcount'] = $dangerous_goods['count'];
        }
        if ( empty($additional_services[$i]['3143']['lqweight']) ) {
          unset($additional_services[$i]['3143']);
        }
      }
    }

    $shipment = $this->create_shipment_from_order($order, $service_id, $additional_services, $selected_products, array( 'return_shipment' => true ));
    $estimated = $this->client->estimateShippingCost($shipment);

    if ( isset($estimated->total_price) ) {
      $estimated_price = $estimated->total_price;
    }

    return $estimated_price;
  }

  public function check_api_credentials( $account_number, $secret_key ) {
    $api_good = true;
    $status = array(
      'api_good' => true,
      'msg' => __('API is good', 'woo-pakettikauppa'),
      'code' => '',
      'error' => '',
    );

    if ( empty($account_number) || empty($secret_key) ) {
      $status['api_good'] = false;
      $status['msg'] = __('Bad API key or API secret', 'woo-pakettikauppa');
    } else {
      try {
        $configs = $this->core->api_config;
        $mode = $this->core->api_mode;
        if ( ! empty($configs[$mode]['use_posti_auth']) ) {
          $token = $this->client->getToken();
          if ( empty($token) ) {
            $status['api_good'] = false;
            $status['msg'] = __('Failed to connect with server', 'woo-pakettikauppa');
            $status['error'] = (! empty($this->client->http_error)) ? $this->client->http_error : '';
          } elseif ( isset($token->error) ) {
            $status['api_good'] = false;
            $status['msg'] = $token->error . ': ' . $token->message;
          } else {
            $this->client->setAccessToken($token->access_token);
            $checker = $this->client->listShippingMethods();
            if ( empty($checker) ) {
              $status['api_good'] = false;
              $status['msg'] = __('Failed to check API credentials or them are bad', 'woo-pakettikauppa');
              $status['error'] = (! empty($this->client->http_error)) ? $this->client->http_error : '';
            }
          }
        } else {
          $checker = $this->client->listShippingMethods();
          if ( empty($checker) ) {
            $status['api_good'] = false;
            $status['msg'] = __('Failed to check API credentials or them are bad', 'woo-pakettikauppa');
            $status['error'] = (! empty($this->client->http_error)) ? $this->client->http_error : '';
          }
        }
      } catch ( \Exception $e ) {
        $status['api_good'] = false;
        $status['msg'] = __('An error occurred while checking API credentials', 'woo-pakettikauppa');
      }
      $status['code'] = (isset($this->client->http_response_code)) ? $this->client->http_response_code : '';
    }

    return $status;
  }

  /**
   * @param WC_Order $order
   * @param null $service_id
   * @param array $additional_services
   *
   * @return string|null
   */
  public function create_shipment( \WC_Order $order, $service_id = null, $additional_services = null, $selected_products = array(), $extra_params = array() ) {
    do_action(str_replace('wc_', '', $this->core->prefix) . '_prepare_create_shipment', $order, $service_id, $additional_services);

    if ( $service_id === null ) {
      $service_id = $this->get_service_id_from_order($order);
    }

    if ( empty($service_id) || $service_id === '__NULL__' || $service_id === '__PICKUPPOINTS__' ) {
      $this->add_error('error');
      $order->add_order_note(esc_attr__('The shipping label was not created because the order does not contain valid shipping method.', 'woo-pakettikauppa'));

      return null;
    }

    // Bail out if the receiver has not been properly configured
    if ( ! self::validate_order_shipping_receiver($order) ) {
      $this->add_error('error');
      add_action(
        'admin_notices',
        function() {
          echo '<div class="update-nag notice">' .
              esc_attr__('The shipping label was not created because the order does not contain valid shipping details.', 'woo-pakettikauppa') .
              '</div>';
        }
      );

      return null;
    }

    $pickup_point_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id');

    if ( $additional_services === null ) {
      $additional_services = $this->get_additional_services_from_order($order);

      if ( ! empty($pickup_point_id) ) {
        $additional_services[] = array(
          '2106' => array(
            'pickup_point_id' => $pickup_point_id,
          ),
        );
      }

      if ( ! empty($selected_products) ) {
        $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
      } else {
        $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, 'kg');
      }
      $count_services = count($additional_services);
      for ( $i = 0; $i < $count_services; $i++ ) {
        if ( isset($additional_services[$i]['3143']) && $additional_services[$i]['3143']['lqweight'] != $dangerous_goods['weight'] ) {
          $additional_services[$i]['3143']['lqweight'] = $dangerous_goods['weight'];
          $additional_services[$i]['3143']['lqcount'] = $dangerous_goods['count'];
        }
        if ( empty($additional_services[$i]['3143']['lqweight']) ) {
          unset($additional_services[$i]['3143']);
        }
      }
    }

    try {
      $shipment = $this->create_shipment_from_order($order, $service_id, $additional_services, $selected_products, $extra_params);
      $tracking_code = $shipment->{'response.trackingcode'}->__toString();
    } catch ( \Exception $e ) {
      $this->add_error($e->getMessage());

      /* translators: %s: Error message */
      $order->add_order_note(sprintf(esc_attr__('Failed to create shipment. Errors: %s', 'woo-pakettikauppa'), $e->getMessage()));
      add_action(
        'admin_notices',
        function() use ( $e ) {
          /* translators: %s: Error message */
          $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
        }
      );

      return null;
    }

    if ( $tracking_code === null ) {
      $this->add_error('error');
      $order->add_order_note(esc_attr__('Failed to create shipment.', 'woo-pakettikauppa'));
      add_action(
        'admin_notices',
        function() {
          /* translators: %s: Error message */
          $this->add_error_notice(esc_attr__('Failed to create shipment.', 'woo-pakettikauppa'));
        }
      );

      return null;
    }

    do_action(str_replace('wc_', '', $this->core->prefix) . '_post_create_shipment', $order);

    $document_url = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);
    $tracking_url = (string) $shipment->{'response.trackingcode'}['tracking_url'];

    $label_code = (string) $shipment->{'response.trackingcode'}['labelcode'];

    $save_additional_services = array();
    $all_additional_services = $this->get_additional_services();
    $has_pickuppoint = false;
    foreach ( $additional_services as $service ) {
      if ( isset($service['2106']['pickup_point_id']) ) {
        $pickup_point_id = $service['2106']['pickup_point_id'];
        $has_pickuppoint = true;
      }
      foreach ( $service as $serv_key => $serv_value ) {
        $serv_name = $serv_key;
        if ( isset($all_additional_services[$service_id]) ) {
          foreach ( $all_additional_services[$service_id] as $serv_obj ) {
            if ( $serv_obj->service_code == $serv_key ) {
              $serv_name = $serv_obj->name;
              break;
            }
          }
        }
        if ( empty($serv_value) ) {
          $save_additional_services[$serv_key] = array(
            'name' => $serv_name,
            'values' => array(),
          );
        } else {
          $save_additional_services[$serv_key] = array(
            'name' => $serv_name,
            'values' => $serv_value,
          );
        }
      }
    }

    if ( empty($selected_products) ) {
      foreach ( $order->get_items() as $item_id => $item ) {
        $variation_id = $item->get_variation_id();
        $product_id = ! empty($variation_id) ? $variation_id : $item->get_product_id();
        $item_quantity  = $item->get_quantity();
        array_push(
          $selected_products,
          array(
            'prod' => $product_id,
            'qty' => $item_quantity,
          )
        );
      }
    }

    if ( $has_pickuppoint ) {
      $pickup_point_name = $this->get_pickup_name($pickup_point_id, $service_id);
    } else {
      $pickup_point_name = '';
    }
    $tracking_info = array(
      'service_id' => $service_id,
      'tracking_code' => $tracking_code,
      'tracking_url' => $tracking_url,
      'label_code' => $label_code,
      'pickup_id' => ($has_pickuppoint) ? $pickup_point_id : '',
      'pickup_name' => $pickup_point_name,
      'shipment_status' => '',
      'products' => $selected_products,
      'additional_services' => $save_additional_services,
    );
    $this->save_label($order->get_id(), $tracking_info);

    // Add order note
    $dl_link       = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $document_url, esc_attr__('Print document', 'woo-pakettikauppa'));
    $tracking_link = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $tracking_url, __('Track', 'woo-pakettikauppa'));

    $service_id = $order->get_meta('_' . $this->core->prefix . '_service_id', true);

    $order->add_order_note(
      '<b>' . $this->core->vendor_name . ':</b> ' . __('Created shipment', 'woo-pakettikauppa') . '.<br/>' . $tracking_code . '<br/>' . $dl_link . ' - ' . $tracking_link
    );

    $settings = $this->get_settings();

    if ( ! empty($settings['post_label_to_url']) ) {
      if ( $this->post_label_to_url($settings['post_label_to_url'], $tracking_code) === false ) {
        $this->add_error('error');
        $order->add_order_note(__('Posting label to URL failed!', 'woo-pakettikauppa'));

        return null;
      } else {
        $order->add_order_note(__('Label posted to URL successfully.', 'woo-pakettikauppa'));
      }
    }

    if ( ! empty($settings['change_order_status_to']) ) {
      if ( $order->get_status() !== $settings['change_order_status_to'] ) {
        $this->allow_create_shipment($order, false);
        $order->update_status($settings['change_order_status_to']);
      }
    }

    return $tracking_code;
  }

  /**
   * ...
   *
   * @param string $url
   * @param string $tracking_code
   *
   * @return string|bool
   */
  private function post_label_to_url( $url, $tracking_code ) {
    $contents = $this->fetch_shipping_label($tracking_code);

    $label = base64_decode( $contents->{'response.file'} ); // @codingStandardsIgnoreLine

    $result = wp_remote_post($url, array( 'label' => $label ));

    if ( $result === false ) {
      return false;
    }

    return $result;
  }

  /**
   * Create Pakettikauppa shipment from order
   *
   * @param WC_Order $order
   *
   * @param null $service_id
   * @param array $additional_services
   *
   * @return SimpleXMLElement
   * @throws Exception
   */
  public function create_shipment_from_order( $order, $service_id = null, $additional_services = array(), $selected_products = array(), $extra_params = array() ) {
    $shipment = new PK_Shipment();
    $language = (function_exists('get_user_locale')) ? ((function_exists('determine_locale')) ? determine_locale() : get_user_locale()) : get_locale();

    if ( ! empty($language) ) {
      $language = substr($language, 0, 2);
    }

    $shipment->setShippingMethod($service_id);

    $shipping_phone = $order->get_shipping_phone();
    $shipping_email = $order->get_meta('_shipping_email', true);

    $sender_data = $this->get_sender_data();
    $receiver_data = $this->get_receiver_data($order, $sender_data['country']);
    if ( isset($extra_params['switch_sender_receiver']) ) {
      $tmp_sender_data = $sender_data;
      $sender_data = $receiver_data;
      $receiver_data = $tmp_sender_data;
    }

    $sender = new Sender();
    $sender->setName1($sender_data['name1']);
    $sender->setName2($sender_data['name2']);
    $sender->setAddr1($sender_data['address1']);
    $sender->setAddr2($sender_data['address2']);
    $sender->setPostcode($sender_data['postcode']);
    $sender->setCity($sender_data['city']);
    $sender->setPhone($sender_data['phone']);
    // $sender->setEmail($sender_data['email']);
    $sender->setCountry($sender_data['country']);
    $shipment->setSender($sender);

    $receiver = new Receiver();
    $receiver->setName1($receiver_data['name1']);
    $receiver->setName2($receiver_data['name2']);
    $receiver->setAddr1($receiver_data['address1']);
    $receiver->setAddr2($receiver_data['address2']);
    $receiver->setPostcode($receiver_data['postcode']);
    $receiver->setCity($receiver_data['city']);
    $receiver->setCountry($receiver_data['country']);
    $receiver->setEmail($receiver_data['email']);
    $receiver->setPhone($receiver_data['phone']);
    $shipment->setReceiver($receiver);

    $parcel_total_count = 1;

    foreach ( $additional_services as $_additional_service ) {
      $additional_service = new AdditionalService();
      $additional_service_code = strval(key($_additional_service));
      $additional_service->setServiceCode($additional_service_code);

      foreach ( $_additional_service as $_additional_service_key => $_additional_service_config ) {
        if ( ! empty($_additional_service_config) ) {
          foreach ( $_additional_service_config as $_name => $_value ) {
            $additional_service->addSpecifier($_name, $_value);

            if ( $additional_service_code === '3102' ) {
              $parcel_total_count = $_value;
            }
          }
        }
      }

      $shipment->addAdditionalService($additional_service);
    }

    $total_selected_products = 0;
    foreach ( $selected_products as $prod ) {
      if ( ! isset($prod['qty']) ) {
        continue;
      }
      $total_selected_products += (int) $prod['qty'];
    }

    $order_total_weight = self::order_weight($order, $selected_products);
    $order_total_volume = self::order_volume($order, $selected_products);

    if ( ! empty($extra_params['ignore_product_weight']) && $total_selected_products > 0 ) {
      $order_total_weight = $total_selected_products;
    }

    $package_type = (! empty($extra_params['package_type'])) ? $extra_params['package_type'] : false;
    if ( empty($package_type) ) {    
      if ( in_array($service_id, self::get_express_freight_services()) ) {    
        $package_type = (isset($this->settings['express_freight_pallet_type'])) ? $this->settings['express_freight_pallet_type'] : 'CC';
      } else {
        $package_type = 'PC';
      }
    }

    for ( $i = 0; $i < $parcel_total_count; $i++ ) {
      $parcel = new Parcel();
      $parcel->setWeight(round($order_total_weight / $parcel_total_count, 2));
      $parcel->setVolume(round($order_total_volume / $parcel_total_count, 4));
      $parcel->setPackageType($package_type);

      if ( ! empty($this->settings['info_code']) ) {
        $parcel->setInfocode(
          trim(mb_substr($this->settings['info_code'], 0, 15))
        );
      }

      $shipment->addParcel($parcel);
    }
    
    $items = $order->get_items();

    $wcpf = new \WC_Product_Factory();

    $products_info = array();

    if ( ! empty($items) ) {
      foreach ( $items as $item ) {
        $item_data = $item->get_data();
        if ( empty($item_data) ) {
          continue;
        }

        $product_variation_id = $item['variation_id'];
        $item_match_id = ! empty($product_variation_id) ? $product_variation_id : $item_data['product_id'];

        if ( ! self::check_selected_product($item_match_id, $selected_products) ) {
          continue;
        }

        // Check if product has variation.
        if ( $product_variation_id ) {
          $product = $wcpf->get_product($item_data['variation_id']);
        } else {
          $product = $wcpf->get_product($item_data['product_id']);
        }

        $selected_product = self::get_selected_product($item_match_id, $selected_products);

        if ( empty($product) ) {
          continue;
        }

        if ( $product->is_virtual() ) {
          continue;
        }

        $tariff_code       = $product->get_meta($this->core->params_prefix . 'tariff_codes', true);
        $country_of_origin = $product->get_meta($this->core->params_prefix . 'country_of_origin', true);

        // For variations, fall back to parent product meta if not set on variation
        if ( $product_variation_id && (empty($tariff_code) || empty($country_of_origin)) ) {
          $parent_product = $wcpf->get_product($item_data['product_id']);
          if ( ! empty($parent_product) ) {
            if ( empty($tariff_code) ) {
              $tariff_code = $parent_product->get_meta($this->core->params_prefix . 'tariff_codes', true);
            }
            if ( empty($country_of_origin) ) {
              $country_of_origin = $parent_product->get_meta($this->core->params_prefix . 'country_of_origin', true);
            }
          }
        }
        
        $quantity = ($selected_product !== false) ? $selected_product['qty'] : $item->get_quantity();

        $translated_product = $product;
        if ( isset($this->settings['translate_products_in_labels']) && $this->settings['translate_products_in_labels'] == 'yes' ) {
          $translated_product = Product::get_translated_product($product, $order);
        }

        $products_info[] = array(
          'name' => $product->get_name(),
          'sku' => $product->get_sku(),
          'qty' => $quantity,
        );

        if ( isset($this->settings['exclude_prods_without_hs']) && $this->settings['exclude_prods_without_hs'] == 'yes' ) {
          if ( empty($tariff_code) ) {
            continue;
          }
        }

        $content_line                    = new ContentLine();
        $content_line->currency          = 'EUR';
        $content_line->country_of_origin = $country_of_origin;
        $content_line->tariff_code       = $tariff_code;
        $content_line->description       = $translated_product->get_name();
        $content_line->quantity          = $quantity;

        if ( ! empty($product->get_weight()) ) {
          $content_line->netweight = wc_get_weight($product->get_weight() * $quantity, 'g');
        }
        if ( ! empty($extra_params['ignore_product_weight']) ) {
          $content_line->netweight = wc_get_weight(1 * $quantity, 'g', 'kg');
        }

        $content_line->value = round($item_data['total'] + $item_data['total_tax'], 2);

        $parcel->addContentLine($content_line);
      }
    }

    $info = new Info();
    $info->setReference($order->get_order_number());
    $info->setCurrency(get_woocommerce_currency());
    $additional_text = $this->settings['label_additional_info'];
    if ( isset($extra_params['additional_text']) ) {
        $additional_text = $extra_params['additional_text'];
    }

    if ( ! empty($additional_text) ) {
      $additional_info = array(
        'order_number' => $order->get_order_number(),
        'order_note' => $order->get_customer_note(),
        'products' => $products_info,
      );
      $info->setAdditionalInfoText($this->prepare_additional_info_text($additional_info, $additional_text));
    }

    $shipment->setShipmentInfo($info);

    $settings = $this->get_settings();

    if ( ! empty($settings['pickup_points']) ) {
      $pickup_settings = json_decode($settings['pickup_points'], true);
      foreach ( $pickup_settings as $setting ) {
        if ( $setting['service'] == $service_id ) {
          if ( isset($setting[$service_id]['additional_services']) && ! empty($setting[$service_id]['additional_services']) ) {
            $setting_additional_services = $setting[$service_id]['additional_services'];
            if ( isset($setting_additional_services['return_label']) && $setting_additional_services['return_label'] == 'yes' ) {
              $shipment->includeReturnLabel(true);
            }
          }
        }
      }
    }

    if ( ! empty($extra_params['return_shipment']) ) {
      return $shipment;
    }

    try {
      $this->client->createTrackingCode($shipment, $language);
    } catch ( \Exception $e ) {
      /* translators: %s: Error message */
      throw new \Exception(wp_sprintf(__('Tracking code creation failed: %s', 'woo-pakettikauppa'), $e->getMessage()));
    }

    return $this->client->getResponse();
  }

  private function get_sender_data() {
    return array(
      'name1' => $this->settings['sender_name'],
      'name2' => null,
      'address1' => $this->settings['sender_address'],
      'address2' => null,
      'postcode' => $this->settings['sender_postal_code'],
      'city' => $this->settings['sender_city'],
      'phone' => $this->settings['sender_phone'],
      'email' => $this->settings['sender_email'],
      'country' => (empty($this->settings['sender_country']) ? 'FI' : $this->settings['sender_country'])
    );
  }

  private function get_receiver_data( $order, $default_country ) {
    $company = $order->get_shipping_company();
    $name = $order->get_formatted_shipping_full_name();
    $country = empty($order->get_shipping_country()) ? $order->get_billing_country() : $order->get_shipping_country();
    $country = empty($country) ? $default_country : $country;
    $phone = $order->get_shipping_phone();
    $email = $order->get_meta('_shipping_email', true);

    return array(
      'name1' => (! empty($company)) ? $company : $name,
      'name2' => (! empty($company)) ? $name : null,
      'address1' => $order->get_shipping_address_1(),
      'address2' => $order->get_shipping_address_2(),
      'postcode' => $order->get_shipping_postcode(),
      'city' => $order->get_shipping_city(),
      'phone' => (! empty($phone)) ? $phone : $order->get_billing_phone(),
      'email' => (! empty($email)) ? $email : $order->get_billing_email(),
      'country' => empty($country) ? 'FI' : $country
    );
  }

  private function prepare_additional_info_text( $values = array(), $custom_text = false ) {
    if ( ! is_array($values) ) {
      return 'ERROR';
    }

    $products = $values['products'] ?? array();

    $shortcodes = array(
      'order_number' => '{ORDER_NUMBER}',
      'order_note' => '{ORDER_NOTE}',
      'products_names' => '{PRODUCTS_NAMES}',
      'products_names_with_qty' => '{PRODUCTS_NAME_WITH_QUANTITY}',
      'products_sku' => '{PRODUCTS_SKU}',
      'products_sku_with_qty' => '{PRODUCTS_SKU_WITH_QUANTITY}',
    );

    // Display "-" in the text instead of the shortcode text if there is no value
    foreach ( $shortcodes as $key => $shortcode ) {
      $values[$key] = (isset($values[$key]) && $values[$key] !== '') ? $values[$key] : '-';
    }

    $additional_info = '';

    $label_additional_info = $this->settings['label_additional_info'];
    if ( $custom_text !== false && ! empty($custom_text) ) {
      $label_additional_info = $custom_text;
    }

    if ( ! empty($label_additional_info) ) {
      $additional_info = $label_additional_info;
      
      // Normalize user-entered shortcodes
      foreach ( $shortcodes as $shortcode ) {
        $additional_info = preg_replace(
          '/' . preg_quote($shortcode, '/') . '/i',
          $shortcode,
          $additional_info
        );
      }

      $additional_info = str_replace('\n', "\n", $additional_info);

      $this->replace_string_in_text($additional_info, $shortcodes['order_number'], $values['order_number']);
      $this->replace_string_in_text($additional_info, $shortcodes['order_note'], $values['order_note']);

      $products_shortcodes_values = [
        'names' => [],
        'names_with_qty' => [],
        'sku' => [],
        'sku_with_qty' => [],
      ];

      if ( is_array($products) && ! empty($products) ) {
        foreach ( $products as $prod ) {
          $prod_name = $prod['name'] ?? '-';
          $prod_qty = $prod['qty'] ?? 1;
          $prod_sku = (! empty($prod['sku'])) ? $prod['sku'] : '-';

          $products_shortcodes_values['names'][] = $prod_name;
          $products_shortcodes_values['names_with_qty'][] = $prod_name . ' (' . $prod_qty . ')';
          $products_shortcodes_values['sku'][] = $prod_sku;
          $products_shortcodes_values['sku_with_qty'][] = $prod_sku . ' (' . $prod_qty . ')';
        }
      } else {
        // Display "-" in the text instead of the shortcode text if there is no products
        $products_shortcodes_values['names'][] = '-';
        $products_shortcodes_values['names_with_qty'][] = '-';
        $products_shortcodes_values['sku'][] = '-';
        $products_shortcodes_values['sku_with_qty'][] = '-';

      }
      $this->replace_string_in_text($additional_info, $shortcodes['products_names'], $products_shortcodes_values['names']);
      $this->replace_string_in_text($additional_info, $shortcodes['products_names_with_qty'], $products_shortcodes_values['names_with_qty']);
      $this->replace_string_in_text($additional_info, $shortcodes['products_sku'], $products_shortcodes_values['sku']);
      $this->replace_string_in_text($additional_info, $shortcodes['products_sku_with_qty'], $products_shortcodes_values['sku_with_qty']);
    }

    return $additional_info;
  }

  private function replace_string_in_text( &$text, $string, $replace ) {
    $replace_text = (is_array($replace)) ? implode(', ', $replace) : $replace;

    $text = str_replace($string, $replace_text, $text);
  }
}
