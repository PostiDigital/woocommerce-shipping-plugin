<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * AJAX endpoints: metabox save/creation/deletion, price estimation, credentials check,
 * and shipping label output.
 */
trait Admin_Ajax_Trait {

  public function ajax_meta_box() {
    check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security');

    $error_count = count($this->get_errors());

    if ( ! is_numeric($_POST['post_id']) ) {
      $this->add_admin_notice(__('Received Post ID is not a number', 'woo-pakettikauppa'), 'error');
      wp_die('', '', 501);
    }
    $this->save_ajax_metabox((int) $_POST['post_id']);

    if ( count($this->get_errors()) !== $error_count ) {
      foreach ( $this->get_errors() as $error ) {
        $this->add_admin_notice($error, 'error');
      }
      wp_die('', '', 501);
    }

    $this->meta_box(wc_get_order((int) $_POST['post_id']));
    wp_die();
  }

  public function ajax_meta_box_bulk() {
    check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security');

    $error_count = count($this->get_errors());

    if ( ! is_numeric($_POST['post_id']) ) {
      $this->add_admin_notice(__('Received Post ID is not a number', 'woo-pakettikauppa'), 'error');
      wp_die('', '', 501);
    }
    $this->save_ajax_metabox((int) $_POST['post_id']);

    if ( count($this->get_errors()) !== $error_count ) {
      foreach ( $this->get_errors() as $error ) {
        $this->add_admin_notice($error, 'error');
      }
      wp_die('', '', 501);
    }
    $this->get_current_shipment(wc_get_order((int) $_POST['post_id']));
    wp_die();
  }

  public function update_estimated_shipping_price() {
    $method_code = sanitize_text_field($_POST['method']);
    $order_id = wc_clean($_POST['order_id']);

    if ( empty($order_id) ) {
      wp_die();
    }

    if ( empty($method_code) ) {
      wp_die();
    }

    $order = new \WC_Order((int) $order_id);

    if ( ! Shipment::validate_order_shipping_receiver($order) ) {
      wp_die();
    }

    $selected_point = '';
    if ( ! empty($_POST['point']) ) {
      preg_match('~\(#(.*?)\)~', sanitize_text_field($_POST['point']), $selected_point_id);
      if ( ! empty(intval($selected_point_id[1])) ) {
        $selected_point = intval($selected_point_id[1]);
      }
    }

    $selected_products = (isset($_POST['selected']) && is_array($_POST['selected'])) ? $_POST['selected'] : array();

    $additional_services = null;
    $selected_additional_services = (isset($_POST['services']) && is_array($_POST['services'])) ? $_POST['services'] : array();

    if ( ! empty($selected_point) ) {
      $selected_additional_services[] = array(
        'key' => '2106',
        'param' => $selected_point,
      );
    }

    if ( ! empty($selected_additional_services) ) {
      $additional_services = array();
      foreach ( $selected_additional_services as $service ) {
        $service_values = '';
        if ( $service['key'] == '3143' ) {
          $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
          if ( ! empty($dangerous_goods['weight']) ) {
            $service_values = array(
              'lqweight' => $dangerous_goods['weight'],
              'lqcount' => $dangerous_goods['count'],
            );
          }
        }
        if ( $service['key'] == 'wc_pakettikauppa_mps_count' && intval($service['param']) > 1 ) {
          $service['key'] = '3102';
          $service_values = array(
            'count' => (string) intval($service['param']),
          );
        }
        if ( $service['key'] == '2106' ) {
          $service_values = array(
            'pickup_point_id' => $selected_point,
          );
        }
        $additional_services[] = array( $service['key'] => $service_values );
      }
    }

    $estimated_price = $this->shipment->get_estimated_shipping_price($order, $method_code, $additional_services, $selected_products);

    echo ($estimated_price) ? wc_price($estimated_price / 100) : str_replace('0', '-', wc_price(0));
    wp_die();
  }

  public function ajax_check_credentials() {
    if ( ! wp_verify_nonce(sanitize_key($_POST['_wpnonce']), $this->core->prefix . '_nonce') ) {
      echo json_encode(array('msg' => 'Unauthorized request'));
      wp_die();
    }
    $account_number = sanitize_text_field($_POST['api_account']);
    $secret_key = trim($_POST['api_secret']);
    $api_check = $this->shipment->check_api_credentials($account_number, $secret_key);
    echo json_encode($api_check);
    wp_die();
  }

  /**
   * Re-render the shipping methods mapping for a given sender country.
   * Triggered when the sender country select changes in the settings page,
   * so available carrier services update without saving and reloading.
   */
  public function ajax_get_pickup_points_mapping() {
    if ( ! wp_verify_nonce(sanitize_key($_POST['_wpnonce'] ?? ''), $this->core->prefix . '_nonce') ) {
      wp_send_json_error(array( 'msg' => 'Unauthorized request' ));
    }

    if ( ! current_user_can('manage_woocommerce') ) {
      wp_send_json_error(array( 'msg' => 'Forbidden' ));
    }

    $sender_country = isset($_POST['sender_country']) ? sanitize_text_field(wp_unslash($_POST['sender_country'])) : '';

    $method = $this->core->shipping_method_instance;
    if ( ! $method ) {
      if ( ! class_exists('\Woo_Posti_Core\Shipping_Method') ) {
        require_once __DIR__ . '/class-shipping-method.php';
      }
      $method = new \Woo_Posti_Core\Shipping_Method();
    }

    $html = $method->render_pickup_points_mapping($sender_country);

    wp_send_json_success(array( 'html' => $html ));
  }

  public function ajax_get_pickup_points() {
    if ( ! wp_verify_nonce(sanitize_key($_POST['_wpnonce']), $this->core->prefix . '_custom_shipment_nonce') ) {
      return '';
      wp_die();
    }
    if ( ! isset($_POST['id']) ) {
      return '';
      wp_die();
    }

    $id = sanitize_text_field($_POST['id']);
    $this->get_pickup_points_html($id);
    wp_die();
  }

  /**
   * Save metabox values and fetch the shipping label for the order.
   */
  public function save_ajax_metabox( $post_id ) {
    /**
     * Because this function is called every time something is saved in WooCommerce, then let's check this first
     * so it won't slow down saving other stuff too much.
     */
    if ( ! isset($_POST['wc_pakettikauppa']) ) {
      return;
    }

    if ( ! check_ajax_referer(str_replace('wc_', '', $this->core->prefix) . '-meta-box', 'security') ) {
      return;
    }

    if ( ! current_user_can('edit_post', $post_id) ) {
      return;
    }

    if ( wp_is_post_autosave($post_id) ) {
      return;
    }

    if ( wp_is_post_revision($post_id) ) {
      return;
    }

    $order = new \WC_Order($post_id);

    $old_request_id = $order->get_meta('_' . $this->core->params_prefix . 'request_id');
    if ( ! empty($_REQUEST['request_id']) && $old_request_id == $_REQUEST['request_id'] ) {
      return;
    } else {
      $order->update_meta_data('_' . $this->core->params_prefix . 'request_id', $_REQUEST['request_id']);
    }

    if ( isset($_REQUEST['add_to_manifest']) ) {
      (new Manifest($this->core))->add_manifest_orders(null, str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest', array( $order->get_id() ));
    }

    $order->save();

    $command = sanitize_key(key($_POST['wc_pakettikauppa']));

    $service_id = null;

    switch ( $command ) {
      case 'create':
        if ( ! empty($_REQUEST['wc_pakettikauppa_service_id']) ) {
          $service_id = sanitize_key($_REQUEST['wc_pakettikauppa_service_id']);
        }

        $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');
        $selected_products = (! empty($_REQUEST['for_products'])) ? $_REQUEST['for_products'] : array();
        $additional_order_params = (! empty($_REQUEST['additional_params'])) ? $_REQUEST['additional_params'] : array();
        $pallet_type = (! empty($_REQUEST['pallet_type'])) ? $_REQUEST['pallet_type'] : '';

        if ( empty($_REQUEST['custom_method']) ) {
          $additional_services = null;

          if ( empty($pickup_point_id) && ! empty($_REQUEST['wc_pakettikauppa_pickup_point_id']) ) {
            $pickup_point_id = strtoupper(sanitize_key($_REQUEST['wc_pakettikauppa_pickup_point_id']));

            $order->update_meta_data('_' . $this->core->params_prefix . 'pickup_point_id', $pickup_point_id);
            $order->save();
          }
        } else {
          $additional_services = array();

          $settings = $this->shipment->get_settings();
          $additional_services_with_params = array(
            '3101' => array(
              'amount' => $order->get_total(),
              'account' => $settings['cod_iban'],
              'codbic' => $settings['cod_bic'],
              'reference' => $this->shipment->calculate_reference($order->get_id()),
            ),
          );

          $dangerous_goods = $this->core->product->calc_selected_dangerous_goods($selected_products, 'kg');
          if ( ! empty($dangerous_goods['weight']) ) {
            $additional_services_with_params['3143'] = array(
              'lqweight' => $dangerous_goods['weight'],
              'lqcount' => $dangerous_goods['count'],
            );
          }

          if ( ! empty($_REQUEST['additional_services']) ) {
            foreach ( $_REQUEST['additional_services'] as $_additional_service_code ) {
              $additional_service_params = null;
              if ( isset($additional_services_with_params[$_additional_service_code]) ) {
                $additional_service_params = $additional_services_with_params[$_additional_service_code];
              }
              $additional_services[] = array( (string) $_additional_service_code => $additional_service_params );
            }
          }

          if ( ! empty($_REQUEST['wc_pakettikauppa_mps_count']) ) {
            $additional_services[] = array( '3102' => array( 'count' => (string) intval($_REQUEST['wc_pakettikauppa_mps_count']) ) );
          }

          if ( ! empty($_REQUEST['custom_pickup']) ) {
            $pickup_point_id = strtoupper(sanitize_key($_REQUEST['custom_pickup']));

            $additional_services[] = array(
              '2106' => array(
                'pickup_point_id' => $pickup_point_id,
              ),
            );
          }
        }
        // additional text for custom shipment
        $extra_params = array();
        if ( isset($_REQUEST['additional_text']) ) {
          $extra_params['additional_text'] = sanitize_textarea_field($_REQUEST['additional_text']);
        }
        if ( isset($_REQUEST['package_type']) && in_array($service_id, Shipment::get_express_freight_services()) ) {
          $extra_params['package_type'] = strtoupper(sanitize_key($_REQUEST['package_type']));
        }
        $extra_params['ignore_product_weight'] = (isset($additional_order_params['ignore_weight']) && filter_var($additional_order_params['ignore_weight'], FILTER_VALIDATE_BOOLEAN));

        $tracking_code = $this->shipment->create_shipment($order, $service_id, $additional_services, $selected_products, $extra_params);

        return $tracking_code;
        break;
      case 'get_status':
        $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);
        $this->get_status($order, $tracking_code);
        break;
      case 'delete_shipping_label':
        $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);

        $this->delete_shipping_label($order, $tracking_code);
        break;
      case 'create_return_label':
        $this->create_return_label($order);
        break;
      case 'delete_return_label':
        $tracking_code = sanitize_text_field($_POST['wc_pakettikauppa'][$command]);
        $this->delete_return_label($order, $tracking_code);
        break;
      case 'add_to_manifest':
        (new Manifest($this->core))->add_manifest_orders(null, str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest', array( $order->get_id() ));
        break;
    }
  }

  /**
   * @param WC_Order $order
   *
   * @throws Exception
   */
  private function create_return_label( \WC_Order $order ) {
    try {
      $shipping_label = $this->shipment->get_single_label($order->get_id());
      if ( ! $shipping_label ) {
        $this->add_error_notice(esc_attr__('It is not allowed to create a return label when shipping labels not exists', 'woo-pakettikauppa'));
        return;
      }

      if ( isset($shipping_label['service_id']) && ! empty($shipping_label['service_id']) ) {
        $service_id = $shipping_label['service_id'];
      } else {
        $service_id = $this->shipment->get_service_id_from_order($order, false);
      }

      $return_services_map = array(
        //'Product code' => 'Return product code'
        '2101' => '2102',
        '2102' => '2102',
        '2103' => '2108',
        '2104' => '2108',
        '2124' => '2102',
        '2142' => '2144',
        '2143' => '2144',
        '2144' => '2144',
        '2145' => '2144',
        '2331' => '2338',
        '2351' => '2358',
        '2352' => '2358',
        '2354' => '2359',
        '2461' => '2108',
        '2711' => '2718',
      );
      if ( ! isset($return_services_map[$service_id]) ) {
        $order->add_order_note(__('Unable to create return label for this shipment type.', 'woo-pakettikauppa'));
        return;
      }
      $return_service_id = $return_services_map[$service_id];

      $additional_services = array();
      if ( $return_service_id === '2108' ) {
        $additional_services[] = array('9902' => array());
      }

      $extra_params = array();
      if ( in_array($service_id, array('2101', '2102', '2124', '2142', '2143', '2144', '2145')) ) {
        $extra_params['switch_sender_receiver'] = true;
      }

      $shipment = $this->shipment->create_shipment_from_order($order, $return_service_id, $additional_services, array(), $extra_params);

      if ( $shipment !== null ) {
        $tracking_code = null;

        if ( isset($shipment->{'response.trackingcode'}) ) {
          $tracking_code = $shipment->{'response.trackingcode'}->__toString();
          $document_url  = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);
          $tracking_url  = (string) $shipment->{'response.trackingcode'}['tracking_url'];
          $label_code    = (string) $shipment->{'response.trackingcode'}['labelcode'];

          if ( version_compare(get_bloginfo('version'), '5.3.0', '>=') ) {
            $current_time = strtotime(wp_date('Y-m-d H:i:s'));
          } else {
            $current_time = current_time('timestamp');
          }

          $label_data = array(
            'service_id' => $return_service_id,
            'tracking_code' => $tracking_code,
            'document_url' => $document_url,
            'tracking_url' => $tracking_url,
            'label_code' => $label_code,
            'timestamp' => $current_time,
          );

          $return_labels = $order->get_meta('_' . $this->core->prefix . '_return_shipment');
          if ( empty($return_labels) ) {
            $order->add_meta_data('_' . $this->core->prefix . '_return_shipment', array($label_data));
          } else {
            if ( ! is_array($return_labels) ) {
              $return_labels = array($return_labels);
            }
            $return_labels[] = $label_data;
            $order->update_meta_data('_' . $this->core->prefix . '_return_shipment', $return_labels);
          }
          $order->save();

          $order->add_order_note(
              '<b>' . $this->core->vendor_name . ':</b> ' . __('Created return label', 'woo-pakettikauppa') . '.<br/>' . $tracking_code
          );
        }
      }
    } catch ( \Exception $e ) {
      $this->add_error($e->getMessage());
      add_action(
        'admin_notices',
        function() use ( $e ) {
          /* translators: %s: Error message */
          $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
        }
      );

      $order->add_order_note(
        sprintf(
          /* translators: %1$s: Vendor name, %2$s: Error message */
          esc_attr__('Creating %1$s return label failed! Errors: %2$s', 'woo-pakettikauppa'),
          $this->core->vendor_name,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * @param WC_Order $order
   */
  private function delete_shipping_label( \WC_Order $order, $tracking_code ) {
    try {
      $old_label = $order->get_meta('_' . $this->core->prefix . '_tracking_code', true);
      $labels = $order->get_meta('_' . $this->core->prefix . '_labels', true);
      if ( $tracking_code == $old_label ) {
        $this->shipment->delete_old_structure_label($order->get_id());
      }
      foreach ( $labels as $key => $label ) {
        if ( $label['tracking_code'] == $tracking_code ) {
          unset($labels[$key]);
        }
      }
      $order->update_meta_data('_' . $this->core->prefix . '_labels', $labels);
      $order->save();
      /* translators: %1$s: Vendor name, %2$s: tracking code */
      $order->add_order_note(sprintf(esc_attr__('Deleted %1$s shipping label %2$s.', 'woo-pakettikauppa'), $this->core->vendor_name, $tracking_code));
    } catch ( \Exception $e ) {
      $this->add_error($e->getMessage());
      add_action(
        'admin_notices',
        function() use ( $e ) {
          /* translators: %s: Error message */
          $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
        }
      );

      $order->add_order_note(
        sprintf(
          /* translators: %1$s: Vendor name, %2$s: Error message */
          esc_attr__('Deleting %1$s shipment failed! Errors: %2$s', 'woo-pakettikauppa'),
          $this->core->vendor_name,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * @param WC_Order $order
   */
  private function delete_return_label( \WC_Order $order, $tracking_code ) {
    try {
      $return_shipments = $order->get_meta('_' . $this->core->prefix . '_return_shipment');

      foreach ( $return_shipments as $key => $return_shipment ) {
        if ( $return_shipment['tracking_code'] === $tracking_code ) {
          unset($return_shipments[$key]);
          if ( empty($return_shipments) ) {
            $order->delete_meta_data('_' . $this->core->prefix . '_return_shipment');
          } else {
            $order->update_meta_data('_' . $this->core->prefix . '_return_shipment', $return_shipments);
          }
          $order->save();
          /* translators: %%s: tracking code */
          $order->add_order_note(sprintf(esc_attr__('Deleted %1$s return label %2$s.', 'woo-pakettikauppa'), $this->core->vendor_name, $tracking_code));
          return;
        }
      }
    } catch ( \Exception $e ) {
      $this->add_error($e->getMessage());
      add_action(
        'admin_notices',
        function() use ( $e ) {
          /* translators: %s: Error message */
          $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
        }
      );

      $order->add_order_note(
        sprintf(
          /* translators: %1$s: Vendor name, %2$s: Error message */
          esc_attr__('Deleting %1$s return label failed! Errors: %2$s', 'woo-pakettikauppa'),
          $this->core->vendor_name,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * @param WC_Order $order
   */
  private function get_status( \WC_Order $order, $tracking_code ) {
    try {
      $status_code = $this->shipment->get_shipment_status($tracking_code);
      $this->shipment->save_label(
        $order->get_id(),
        array(
          'tracking_code' => $tracking_code,
          'shipment_status' => $status_code,
        )
      );
    } catch ( \Exception $e ) {
      $this->add_error($e->getMessage());
      add_action(
        'admin_notices',
        function() use ( $e ) {
          /* translators: %s: Error message */
          $this->add_error_notice(wp_sprintf(esc_attr__('An error occurred: %s', 'woo-pakettikauppa'), $e->getMessage()));
        }
      );
    }
  }

  /**
   * Output shipment label as PDF in browser.
   */
  public function show() {
    // Find shipment ID either from GET parameters or from the order
    // data.
    if ( empty( $_REQUEST['tracking_code'] ) ) { // @codingStandardsIgnoreLine
      esc_attr_e('Shipment tracking code is not defined.', 'woo-pakettikauppa');

      return;
    }

    $tracking_code = sanitize_text_field($_REQUEST['tracking_code']); // @codingStandardsIgnoreLine
    $settings = $this->shipment->get_settings();
    $labels_size = (isset($settings['labels_size'])) ? $settings['labels_size'] : null;

    try {
      $contents = $this->shipment->fetch_shipping_label($tracking_code, $labels_size);
    } catch ( \Exception $e ) {
      esc_attr_e('Failed to get shipment label.', 'woo-pakettikauppa');
      echo '</br>' . esc_attr__('Error', 'woo-pakettikauppa') . ': ' . $e->getMessage();

      return;
    }

    if ( $contents->{'response.file'}->__toString() === '' ) {
      esc_attr_e('Cannot find shipment with given shipment number.', 'woo-pakettikauppa');

      return;
    }

    $this->output_shipping_label($contents, $tracking_code);
  }

  /**
   * Fetches PDF from the XML and outputs it. Ends execution.
   *
   * @param $contents
   * @param $filename
   */
  private function output_shipping_label( $contents, $filename ) {
    $settings = $this->shipment->get_settings();

    if ( $settings['download_type_of_labels'] === 'download' ) {
      header('Content-Type: application/octet-stream');
      $content_disposition = 'attachment';
    } else {
      header('Content-Type: application/pdf');
      $content_disposition = 'inline';
    }

    $pdf = base64_decode( $contents->{'response.file'} ); // @codingStandardsIgnoreLine

    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header("Content-Disposition: $content_disposition;filename=\"{$filename}.pdf\"");
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;

    exit();
  }
}
