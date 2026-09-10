<?php
namespace Woo_Pakettikauppa_Core;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Wc_Blocks') ) {
  /**
   * Wc_Blocks Class
   *
   * @class Wc_Blocks
   * @version  1.0.0
   * @since 3.9.4
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Wc_Blocks {
    /**
     * Core instance
     *
     * @var Core
     */
    public $core = null;

    private $ajax_actions_registered = false;

    public function __construct( Core $plugin ) {
      $this->core = $plugin;
    }

    public function load() {
      $this->register_ajax_actions();

      add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
      add_action('woocommerce_blocks_loaded', array($this, 'init'));
    }

    public function declare_compatibility() {
      if ( class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil') ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->core->root_file, true );
      }
    }

    public function init() {
      require_once 'class-wc-blocks-integration.php';

      add_action('woocommerce_blocks_checkout_block_registration', function( $integration_registry ) {
        $integration_registry->register( new Wc_Blocks_Integration($this) );
      });
      add_action('woocommerce_blocks_cart_block_registration', function( $integration_registry ) {
        $integration_registry->register( new Wc_Blocks_Integration($this) );
      });

      if ( function_exists('woocommerce_store_api_register_endpoint_data') ) {
        woocommerce_store_api_register_endpoint_data(array(
          'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
          'namespace' => str_replace('_', '-', $this->core->prefix),
          'data_callback' => array($this, 'pk_data_callback'),
          'schema_callback' => array($this, 'pk_schema_callback'),
          'schema_type' => ARRAY_A,
        ));
      }
      add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'update_block_order_meta'), 10, 2);

      add_filter(
        '__experimental_woocommerce_blocks_add_data_attributes_to_namespace',
        function ( $allowed_namespaces ) {
          $allowed_namespaces[] = 'pakettikauppa';
          return $allowed_namespaces;
        },
        10,
        1
      );
    }

    public function update_block_order_meta($order, $request) {
      $data = $request['extensions']['wc-pakettikauppa'] ?? array();

      $selected_pickup_point = wc_clean($data['pakettikauppa_pickup_point'] ?? '');

      if ( ! empty($selected_pickup_point) ) {
        $order->update_meta_data('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', sanitize_text_field($selected_pickup_point));

        preg_match('/\(#[A-Z0-9]+\)/', $selected_pickup_point, $matches);
        $pakettikauppa_pickup_point_id = (! empty($matches)) ? substr($matches[0], 2, -1) : '';
        $order->update_meta_data('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', $pakettikauppa_pickup_point_id);

        preg_match('/\(\%[0-9]+\)/', $selected_pickup_point, $matches);
        $pakettikauppa_pickup_point_provider_id = (! empty($matches)) ? substr($matches[0], 2, -1) : '';
        $order->update_meta_data('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_provider_id', $pakettikauppa_pickup_point_provider_id);

        $order->save();
      }
    }

    public function register_block_categories( $categories ) {
      return array_merge($categories, array(
        array( 'slug'  => str_replace('_', '-', $this->core->prefix), 'title' => $this->core->vendor_fullname ),
      ));
    }

    public function pk_data_callback() {
      return array(
        'pakettikauppa_pickup_point' => ''
      );
    }

    public function pk_schema_callback() {
      return array(
        'pakettikauppa_pickup_point'  => array(
          'description' => __('Selected pickup point', 'woo-pakettikauppa'),
          'type'        => array('string', 'null'),
          'readonly'    => true,
        ),
      );
    }

    public function register_ajax_actions() {
      if ( $this->ajax_actions_registered ) {
        return;
      }
      $this->ajax_actions_registered = true;

      add_action('wp_ajax_pakettikauppa_blocks_get_pickup_points', array($this, 'get_pickup_points_callback'));
      add_action('wp_ajax_nopriv_pakettikauppa_blocks_get_pickup_points', array($this, 'get_pickup_points_callback'));
      add_action('wp_ajax_pakettikauppa_blocks_get_custom_pickup_points', array($this, 'get_pickup_points_by_free_input_callback'));
      add_action('wp_ajax_nopriv_pakettikauppa_blocks_get_custom_pickup_points', array($this, 'get_pickup_points_by_free_input_callback'));
    }

    public function get_pickup_points_callback() {
      if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        return wp_send_json_error('Request method must be POST');
      }

      if ( ! $this->core->shipment ) {
        return wp_send_json_error('Plugin is not ready');
      }

      $request_body = json_decode(file_get_contents('php://input'));
      if ( ! is_object($request_body)
        || ! property_exists($request_body, '_wpnonce')
        || ! wp_verify_nonce($request_body->_wpnonce, $this->core->prefix . '_blocks_nonce')
      ) {
        return wp_send_json_error('Unauthorized request');
      }

      $postcode = sanitize_text_field($request_body->destination->postcode);
      if ( empty($postcode) ) {
        return wp_send_json_error('A postcode is required to get the pickup points');
      }
      $street_address = sanitize_text_field($request_body->destination->address) . ', ' . sanitize_text_field($request_body->destination->city);

      try {
        $pickup_points = $this->core->shipment->get_pickup_points(
          $postcode,
          $street_address,
          sanitize_text_field($request_body->destination->country),
          sanitize_text_field($request_body->service)
        );
      } catch (\Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
      }

      if ( empty($pickup_points) ) {
        wp_send_json_error('Received pickup points list is empty');
      }
      wp_send_json_success($pickup_points);
    }

    public function get_pickup_points_by_free_input_callback() {
      if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        return wp_send_json_error('Request method must be POST');
      }
      
      if ( ! $this->core->shipment ) {
        return wp_send_json_error('Plugin is not ready');
      }

      $request_body = json_decode(file_get_contents('php://input'));
      if ( ! is_object($request_body)
        || ! property_exists($request_body, '_wpnonce')
        || ! wp_verify_nonce($request_body->_wpnonce, $this->core->prefix . '_blocks_nonce')
      ) {
        return wp_send_json_error('Unauthorized request');
      }

      $address = sanitize_text_field($request_body->address);
      if ( empty($address) ) {
        return wp_send_json_error('A postcode is required to get the pickup points');
      }

      try {
        $pickup_points = $this->core->shipment->get_pickup_points_by_free_input(
          $address,
          sanitize_text_field($request_body->service)
        );
      } catch (\Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
      }

      if ( empty($pickup_points) ) {
        wp_send_json_error('Received pickup points list is empty');
      }
      wp_send_json_success($pickup_points);
    }
  }
}
