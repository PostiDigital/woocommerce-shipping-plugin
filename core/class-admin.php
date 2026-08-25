<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

require_once __DIR__ . '/admin/trait-notices.php';
require_once __DIR__ . '/admin/trait-orders-list.php';
require_once __DIR__ . '/admin/trait-order-meta.php';
require_once __DIR__ . '/admin/trait-settings.php';
require_once __DIR__ . '/admin/trait-metabox.php';
require_once __DIR__ . '/admin/trait-ajax.php';

if ( ! class_exists(__NAMESPACE__ . '\Admin') ) {
  /**
   * Admin Class
   *
   * Behaviour is split across several traits in core/admin/ to keep this file manageable:
   * notices, orders list, order meta, settings/plugin links, meta box rendering and AJAX handlers.
   *
   * @class Admin
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Admin {
    use Admin_Notices_Trait;
    use Admin_Orders_List_Trait;
    use Admin_Order_Meta_Trait;
    use Admin_Settings_Trait;
    use Admin_Metabox_Trait;
    use Admin_Ajax_Trait;

    /**
     * @var Shipment
     */
    private $shipment = null;

    /**
     * @var \Woo_Posti_Shipping
     */
    public $core = null;
    private $errors = array();

    public function __construct( \Woo_Posti_Shipping $plugin ) {
      // $this->id = self::$module_config['admin']; // Doesn't do anything
      $this->core = $plugin;
    }

    public function load() {
      add_action('current_screen', array( $this, 'maybe_show_notices' ));
      add_filter('plugin_action_links_' . $this->core->basename, array( $this, 'add_settings_link' ));
      add_filter('plugin_row_meta', array( $this, 'plugin_row_meta_wrapper' ), 10, 2);
      add_filter('bulk_actions-edit-shop_order', array( $this, 'register_multi_create_orders' ));
      add_filter('bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_multi_create_orders' )); //HPOS
      add_filter('manage_edit-shop_order_columns', array( $this, 'add_column_to_orders_table' ), 20);
      add_filter('manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column_to_orders_table' ), 20); //HPOS
      add_action('woocommerce_admin_order_actions_end', array( $this, 'register_quick_create_order' ), 10, 2); //to add print option at the end of each orders in orders page
      add_action('admin_notices', array( $this, 'show_admin_notices' ));
      add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ));
      add_action('add_meta_boxes', array( $this, 'register_meta_boxes' ));
      add_action('admin_post_show_pakettikauppa', array( $this, 'show' ), 10);
      add_action('admin_post_' . $this->core->params_prefix . 'quick_create_label', array( $this, 'quick_create_label' ), 10, 3);
      add_action('woocommerce_email_order_meta', array( $this, 'attach_tracking_to_email' ), 10, 4);
      add_action('woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_pickup_point_in_admin_order_meta' ), 10, 1);
      add_action('save_post', array( $this, 'save_admin_order_meta' ));
      add_action('woocommerce_update_order', array( $this, 'save_admin_order_meta_hpos' ));
      add_action('handle_bulk_actions-edit-shop_order', array( $this, 'bulk_create_label' ), 10, 3); // admin_action_{action name}
      add_action('handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_create_label' ), 10, 3); //HPOS
      add_action('manage_shop_order_posts_custom_column', array( $this, 'add_column_data_to_orders_table' ), 10, 2);
      add_action('manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_column_data_to_orders_table_hpos' ), 10, 2); //HPOS
      add_action($this->core->params_prefix . 'create_shipments', array( $this, 'hook_create_shipments' ), 10, 2);
      add_action($this->core->params_prefix . 'fetch_shipping_labels', array( $this, 'hook_fetch_shipping_labels' ), 10, 2);
      add_action($this->core->params_prefix . 'fetch_tracking_code', array( $this, 'hook_fetch_tracking_code' ), 10, 2);
      add_action('wp_ajax_pakettikauppa_meta_box', array( $this, 'ajax_meta_box' ));
      add_action('woocommerce_order_status_changed', array( $this, 'restore_order_params_after_status_change' ), 9999);
      add_action('wp_ajax_get_pickup_point_by_custom_address', array( $this, 'get_pickup_point_by_custom_address' ));
      add_action('wp_ajax_update_estimated_shipping_price', array( $this, 'update_estimated_shipping_price' ));
      add_action('wp_ajax_check_api', array( $this, 'ajax_check_credentials' ));
      add_action('wp_ajax_pakettikauppa_meta_box_bulk', array( $this, 'ajax_meta_box_bulk' ));
      add_action('admin_menu', array( $this, 'add_submenu' ));
      add_action('wp_ajax_pakettikauppa_get_pickup_points', array( $this, 'ajax_get_pickup_points' ));
      add_action('wp_ajax_pakettikauppa_get_mapping', array( $this, 'ajax_get_pickup_points_mapping' ));

      $this->shipment = $this->core->shipment;

      $settings = $this->shipment->get_settings();
      if ( ! empty($settings['create_shipments_automatically']) && $settings['create_shipments_automatically'] !== 'no' ) {
        add_action('woocommerce_order_status_' . $settings['create_shipments_automatically'], array( $this, 'create_shipment_for_order_automatically' ));
      }
    }

    /**
     * Add an error with a specified error message.
     *
     * @param string $message A message containing details about the error.
     */
    public function add_error( $message ) {
      $this->shipment->add_error($message);
    }

    /**
     * Return all errors that have been added via add_error().
     *
     * @return array Errors
     */
    public function get_errors() {
      return $this->shipment->get_errors();
    }

    /**
     * Clear all existing errors that have been added via add_error().
     */
    public function clear_errors() {
      $this->shipment->clear_errors();
    }
  }
}
