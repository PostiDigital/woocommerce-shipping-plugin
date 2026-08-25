<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * Orders list column, bulk/quick actions and multi-shipment creation.
 */
trait Admin_Orders_List_Trait {

  public function add_column_to_orders_table( $columns ) {
    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {
      $new_columns[ $column_name ] = $column_info;
      if ( $column_name == 'shipping_address' ) {
        $new_columns[str_replace('wc_', '', $this->core->prefix) . '_tracking_link'] = __('Shipment Tracking', 'woo-pakettikauppa');
      }
    }

    return $new_columns;
  }

  public function add_column_data_to_orders_table( $column, $post_id ) {
    $order = wc_get_order($post_id);

    $this->add_column_data_to_orders_table_hpos($column, $order);
  }

  public function add_column_data_to_orders_table_hpos( $column, $order ) {
    if ( ! is_a($order, 'WC_Order') ) {
      return;
    }

    if ( $column == str_replace('wc_', '', $this->core->prefix) . '_tracking_link' ) {
      $labels = $this->shipment->get_labels($order->get_id());
      if ( empty($labels) ) {
        echo '-';
        return;
      }
      $labels_links = array();
      foreach ( $labels as $label ) {
        if ( ! empty($label['tracking_code']) ) {
          $labels_links[$label['tracking_code']] = $label['tracking_url'];
        }
      }
      $labels_links_html = '';
      foreach ( $labels_links as $label_code => $label_url ) {
        if ( ! empty($labels_links_html) ) {
          $labels_links_html .= ', ';
        }
        if ( empty($label_url) ) {
          $labels_links_html .= $label_code;
          continue;
        }
        $labels_links_html .= sprintf('<a href="%1$s" target="_blank">%2$s</a>', $label_url, $label_code);
      }
      echo $labels_links_html;
    }
  }

  /**
   * action -hook to fetch tracking codes of the order as array.
   *
   * Call for example:
   *
   * $tracking_code=array();
   * $args = array( $order_id, &$tracking_code );
   * do_action_ref_array($this->core->params_prefix . 'fetch_tracking_code', $args);"
   *
   * @param $order_id
   * @param $tracking_code
   */
  public function hook_fetch_tracking_codes( $order_id, &$tracking_codes ) {
    $order = new \WC_Order($order_id);
    $tracking_codes = $this->shipment->get_labels($order->getid());
  }

  /**
   * action -hook to create shipments to orders.
   *
   * Call for example:
   *
   * $args = array( $order_id, $order_id2, ... );
   * do_action($this->core->params_prefix . 'create_shipments', $args);"
   *
   * @param $order_ids
   */
  public function hook_create_shipments( $order_ids ) {
    $this->create_shipments($order_ids);
  }

  /**
   * action -hook to create shipments to orders.
   *
   * Call for example:
   * $pdf = '';
   * $order_ids = array (15, 16, 17);
   * $args = array( $order_ids, &$pdf );
   * do_action_ref_array($this->core->params_prefix . 'create_shipments', $args);"
   *
   * @param $order_ids
   * @param $pdf
   */
  public function hook_fetch_shipping_labels( $order_ids, &$pdf ) {
    $tracking_codes = $this->create_shipments($order_ids);

    $contents = $this->fetch_shipping_labels($tracking_codes);
    if ( ! $contents ) {
      return;
    }

    $pdf = base64_decode($contents->{'response.file'});
  }

  /**
   * @param $bulk_actions
   *
   * @return mixed
   */
  public function register_multi_create_orders( $bulk_actions ) {
    global $wp_version;

    if ( version_compare($wp_version, '5.6.0', '>=') ) {
      $bulk_actions[$this->core->vendor_name] = array(
        $this->core->params_prefix . 'create_multiple_shipping_labels' => __('Create and fetch shipping labels', 'woo-pakettikauppa'),
        $this->core->params_prefix . 'create_custom_shipments' => __('Create custom shipments', 'woo-pakettikauppa'),
      );
    } else {
      $bulk_actions[$this->core->params_prefix . 'create_multiple_shipping_labels'] = $this->core->vendor_name . ': ' . __('Create and fetch shipping labels', 'woo-pakettikauppa');
      $bulk_actions[$this->core->params_prefix . 'create_custom_shipments'] = $this->core->vendor_name . ': ' . __('Create custom shipments', 'woo-pakettikauppa');
    }

    return $bulk_actions;
  }

  /**
   * @param $actions
   * @param WC_Order $order
   *
   * @return array
   */
  public function register_quick_create_order( $order ) {
    $shipping_methods = $order->get_shipping_methods();

    if ( ! empty($shipping_methods) ) {
      $shipping_method = array_pop($shipping_methods);

      if ( ! empty($shipping_method) ) {
        $method_id = $shipping_method->get_method_id();

        if ( $method_id === 'local_pickup' ) {
          return;
        }
      }
    }

    $document_url = wp_nonce_url(admin_url('admin-post.php?post[]=' . $order->get_id() . '&action=' . $this->core->params_prefix . 'quick_create_label'), 'bulk-posts');

    $class = $this->core->params_prefix . 'create_shipping_label';

    $actions = array(
      'name'   => __('Create shipping label', 'woo-pakettikauppa'),
      'action' => $this->core->params_prefix . 'create_shipping_label',
      'url'    => $document_url,
    );

    printf('<a class="button wc-action-button wc-action-button-%s %s" href="%s" title="%s" target="_blank">%s</a>', $class, $class, $actions['url'], $actions['name'], $actions['name']);
  }

  /**
   * When the action button is activated in the order's "Actions" column on the orders list page
   * 
   * @throws Exception
   */
  public function quick_create_label() {
    if ( ! isset($_REQUEST['post']) ) {
      return;
    }

    if ( ! is_array($_REQUEST['post']) ) {
      return;
    }

    if ( ! isset($_REQUEST['action']) ) {
      return;
    }

    if ( ! wp_verify_nonce(sanitize_key($_REQUEST['_wpnonce']), 'bulk-posts') ) {
      return;
    }

    $action = null;
    
    if ( isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ) {
      $action = sanitize_key($_REQUEST['action']);
    } elseif ( isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1' ) {
      $action = sanitize_key($_REQUEST['action2']);
    }

    if ( $action === null ) {
      return;
    }

    if ( $action !== $this->core->params_prefix . 'quick_create_label' ) {
      return;
    }

    return $this->create_multiple_shipments('', $_REQUEST['post']);
  }

  /**
   * When the orders list bulk action is activated
   * 
   * @param string $redirect_to - Redirect URL
   * @param string $action - Bulk action name
   * @param array $ids - IDs of the selected Orders
   * 
   * @throws Exception
   */
  public function bulk_create_label( $redirect_to, $action, $ids ) {
    if ( empty($action) ) {
      if ( isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ) {
        $action = sanitize_key($_REQUEST['action']);
      } elseif ( isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1' ) {
        $action = sanitize_key($_REQUEST['action2']);
      }
    }

    if ( $action === null ) {
      return $redirect_to;
    }

    if ( $action === $this->core->params_prefix . 'create_custom_shipments' ) {
      return add_query_arg('id', $ids, menu_page_url('bulk-create-custom-shipment'));
    }

    if ( $action === $this->core->params_prefix . 'create_multiple_shipping_labels' ) {
      return $this->create_multiple_shipments($redirect_to, $ids);
    }

    return $redirect_to;
  }

  /**
   * This function exits on success, returns on error
   *
   * @param string $redirect_to - Redirect URL
   * @param array $ids - IDs of the selected Orders
   * 
   * @throws Exception
   */
  public function create_multiple_shipments( $redirect_to, $ids ) {
    if ( ! is_array($ids) ) {
      return $redirect_to;
    }

    $order_ids = array();

    // instead of array_map we use foreach because array_map is not allowed by sniff rules
    foreach ( $ids as $order_id ) {
        $order_ids[] = sanitize_text_field($order_id);
    }

    if ( empty($order_ids) ) {
      $this->add_admin_notice(__('Please select at least one Order', 'woo-pakettikauppa'), 'error');
      return $redirect_to;
    }

    $tracking_codes = $this->create_shipments($order_ids);

    $contents = $this->fetch_shipping_labels($tracking_codes);
    if ( ! $contents ) {
      return $redirect_to;
    }

    if ( $contents->{'response.file'}->__toString() === '' ) {
      $this->add_admin_notice(__('Cannot find shipments with given shipment numbers.', 'woo-pakettikauppa'), 'error');

      return $redirect_to;
    }

    $this->output_shipping_label($contents, 'multiple-shipping-labels');
  }

  private function fetch_shipping_labels( $tracking_codes ) {
    $shipping_labels = false;
    $settings = $this->shipment->get_settings();
    $labels_size = (isset($settings['labels_size'])) ? $settings['labels_size'] : null;

    try {
      $shipping_labels = $this->shipment->fetch_shipping_labels($tracking_codes, $labels_size);
    } catch ( \Exception $e ) {
      $this->add_admin_notice($e->getMessage(), 'error');
    }

    return $shipping_labels;
  }

  private function create_shipments( $order_ids ) {
    $tracking_codes = array();

    foreach ( $order_ids as $order_id ) {
      $order = new \WC_Order($order_id);

      $labels = $this->shipment->get_labels($order_id);
      if ( ! empty($labels) ) {
        $last_label = end($labels);
        $tracking_code = $last_label['tracking_code'];
      } else {
        $tracking_code = $this->shipment->create_shipment($order);
      }

      if ( $tracking_code !== null ) {
        $tracking_codes[] = $tracking_code;
      }
    }

    return $tracking_codes;
  }

  public function wc_pakettikauppa_updated() {
    $shipping_method_found = false;
    $shipping_zones = \WC_Shipping_Zones::get_zones();

    foreach ( $shipping_zones as $shipping_zone ) {
      foreach ( $shipping_zone['shipping_methods'] as $shipping_object ) {
        if ( get_class($shipping_object) === __NAMESPACE__ . '\Shipping_Method' ) {
          $shipping_method_found = true;
        }
      }
    }

    $settings = $this->shipment->get_settings();

    if ( ! empty($settings['pickup_points']) ) {
      $pickup_points = json_decode($settings['pickup_points'], true);

      if ( ! empty($pickup_points) ) {
        foreach ( $pickup_points as $shipping_method ) {
          foreach ( $shipping_method as $provider ) {
            if ( isset($provider['active']) && $provider['active'] === 'yes' ) {
              $shipping_method_found = true;
            }
          }
        }
      }
    }

    if ( ! $shipping_method_found ) {
      echo '<div class="updated warning">';
      /* translators: %s: Vendor full name */
      echo '<p>' . sprintf(__('%s has been installed/updated but no shipping methods are currently active!', 'woo-pakettikauppa'), $this->core->vendor_fullname) . '</p>';
      echo '</div>';
    }
  }
}
