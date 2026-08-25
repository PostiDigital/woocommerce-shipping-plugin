<?php

namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

require_once __DIR__ . '/frontend/trait-checkout.php';
require_once __DIR__ . '/frontend/trait-orders.php';
require_once __DIR__ . '/frontend/trait-assets.php';

if ( ! class_exists(__NAMESPACE__ . '\Frontend') ) {
  /**
   * Frontend Class
   *
   * @class Frontend
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Frontend {
    use Frontend_Checkout_Trait;
    use Frontend_Orders_Trait;
    use Frontend_Assets_Trait;

    /**
     * @var \Woo_Posti_Shipping
     */
    public $core = null;

    /**
     * @var Shipment
     */
    private $shipment = null;
    private $errors = array();

    public function __construct( \Woo_Posti_Shipping $plugin ) {
      // $this->id = 'woo-pakettikauppa'; // not used for anything
      $this->core = $plugin;
    }

    public function load() {
      add_action('wp_enqueue_scripts', array( $this, 'enqueue_scripts' ));
      add_action('woocommerce_review_order_after_shipping', array( $this, 'pickup_point_field_html' ));
      add_action('woocommerce_order_details_after_order_table', array( $this, 'display_order_data' ));
      add_action('woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_pickup_point_field' ));
      add_action('woocommerce_checkout_process', array( $this, 'validate_checkout' ));
      add_action('woocommerce_order_status_changed', array( $this, 'restore_order_params_after_status_change' ), 9999);

      add_action('wp_ajax_pakettikauppa_save_pickup_point_info_to_session', array( $this, 'save_pickup_point_info_to_session' ), 10);
      add_action('wp_ajax_nopriv_pakettikauppa_save_pickup_point_info_to_session', array( $this, 'save_pickup_point_info_to_session' ), 10);

      add_action('wp_ajax_pakettikauppa_use_custom_address_for_pickup_point', array( $this, 'use_custom_address_for_pickup_point' ), 10);
      add_action('wp_ajax_nopriv_pakettikauppa_use_custom_address_for_pickup_point', array( $this, 'use_custom_address_for_pickup_point' ), 10);

      add_filter('woocommerce_checkout_fields', array( $this, 'add_checkout_fields' ));

      $this->shipment = $this->core->shipment;

      $settings = $this->shipment->get_settings();
      if ( ! empty($settings['create_shipments_automatically']) && $settings['create_shipments_automatically'] !== 'no' ) {
        add_action('woocommerce_order_status_' . $settings['create_shipments_automatically'], array( $this, 'create_shipment_for_order_automatically' ));
      }
    }
  }
}
