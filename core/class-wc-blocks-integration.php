<?php
namespace Woo_Pakettikauppa_Core;

use \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Wc_Blocks_Integration') ) {
  /**
   * Wc_Blocks_Integration Class
   *
   * @class Wc_Blocks_Integration
   * @version 1.0.0
   * @since 3.9.4
   * @package woo-pakettikauppa
   * @author Seravo
   */
  class Wc_Blocks_Integration implements IntegrationInterface {
    /**
     * @var string
     */
    private $version = '0.0.1';

    /**
     * @var Core
     */
    private $core = null;

    /**
     * @var Shipment
     */
    private $shipment = null;

    /**
     * @var string
     */
    private $prefix = '';

    /**
     * Constructor
     */
    public function __construct( Core $plugin ) {
      $this->core = $plugin;
      $this->shipment = $this->core->shipment;
      $this->prefix = str_replace('_', '-', $this->core->prefix) . '-';
    }
    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
      return strtolower($this->core->vendor_name) . '-blocks';
    }

    /**
     * Initial integration
     */
    public function initialize() {
      require_once 'class-wc-blocks-extend-store-endpoint.php';
      $this->register_editor_scripts();
      $this->register_frontend_scripts();
      $this->register_additional_actions();
    }

    /**
     * Array of script handles to enqueue in the frontend context
     * 
     * @return array
     */
    public function get_script_handles() {
      return array(
        $this->prefix . 'pickup-point-selection-front-checkout',
        $this->prefix . 'pickup-point-selection-front-cart',
      );
    }

    /**
     * Array of script handles to enqueue in the editor context
     * 
     * @return array
     */
    public function get_editor_script_handles() {
      return array(
        $this->prefix . 'pickup-point-selection-edit-checkout',
        $this->prefix . 'pickup-point-selection-edit-cart'
      );
    }

    /**
     * Array of key, value pairs of data made available to the block on the frontend
     * 
     * @return array
     */
    public function get_script_data() {
      $settings = $this->shipment->get_settings();
      return array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce($this->core->prefix . '_blocks_nonce'),
        'methods' => $this->get_pakettikauppa_methods(),
        'allow_custom_address' => (isset($settings['show_pickup_point_override_query']) && $settings['show_pickup_point_override_query'] === 'yes'),
        'list_type' => (isset($settings['pickup_point_list_type'])) ? $settings['pickup_point_list_type'] : 'menu',
        'txt' => array(
          'block_options' => __('Block options', 'woo-pakettikauppa'),
          'pickup_block_title' => __('Pickup point', 'woo-pakettikauppa'),
          'pickup_select_field_default' => __('Select a pickup point', 'woo-pakettikauppa'),
          'pickup_select_field_optional' => __('No pickup point: Send to the street address', 'woo-pakettikauppa'),
          'pickup_select_other' => __('Other', 'woo-pakettikauppa'),
          'pickup_error' => __('Please choose a pickup point', 'woo-pakettikauppa'),
          'pickup_not_found' => __('No pickup points were found. Check the address.', 'woo-pakettikauppa'),
          'cart_pickup_info' => __('You can choose the pickup point on the Checkout page', 'woo-pakettikauppa'),
          'checkout_pickup_info' => __('Choose one of pickup points close to the address you entered', 'woo-pakettikauppa'),
          'custom_pickup_title' => __('Custom pickup address', 'woo-pakettikauppa'),
          'custom_pickup_description' => __('If none of your preferred pickup points are listed, fill in a custom address above and select another pickup point', 'woo-pakettikauppa'),
          'custom_pickup_help' => __('After entering, please wait for a while for the results to be received', 'woo-pakettikauppa'),
          'custom_pickup_error_too_short' => __('The value is too short', 'woo-pakettikauppa'),
          'custom_pickup_error_bad_char' => __('Invalid character entered', 'woo-pakettikauppa'),
          'custom_pickup_address' => __('The selection of pickup points has been changed based on the address %s', 'woo-pakettikauppa')
        ),
      );
    }

    public function register_additional_actions() {
      add_action('wp_ajax_pakettikauppa_blocks_get_pickup_points', array($this, 'get_pickup_points_callback'));
      add_action('wp_ajax_nopriv_pakettikauppa_blocks_get_pickup_points', array($this, 'get_pickup_points_callback'));
      add_action('wp_ajax_pakettikauppa_blocks_get_custom_pickup_points', array($this, 'get_pickup_points_by_free_input_callback'));
      add_action('wp_ajax_nopriv_pakettikauppa_blocks_get_custom_pickup_points', array($this, 'get_pickup_points_by_free_input_callback'));
    }

    public function get_pickup_points_callback() {
      if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        return wp_send_json_error('Request method must be POST');
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
      $street_address = sanitize_text_field($request_body->destination->address_1) . ', ' . sanitize_text_field($request_body->destination->city);

      try {
        $pickup_points = $this->shipment->get_pickup_points(
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
        $pickup_points = $this->shipment->get_pickup_points_by_free_input(
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

    private function get_pakettikauppa_methods() {
      $methods = array();
      $found_methods = array();
      $settings = $this->shipment->get_settings();
      $shipping_methods = json_decode(isset($settings['pickup_points']) ? $settings['pickup_points'] : '[]', true);

      foreach ( $shipping_methods as $method_instance_id => $method_params ) {
        if ( empty($method_params['service']) || $method_params['service'] === '__NULL__' ) {
          continue;
        }
        if ( in_array($method_instance_id, $found_methods) ) {
          continue;
        }
        $have_pickup_points = false;
        foreach ( $method_params as $service_id => $service_params ) {
          if ( $method_params['service'] === '__PICKUPPOINTS__' ) {
            if ( isset($service_params['active']) && $service_params['active'] === 'yes' ) {
              $have_pickup_points = true;
            }
          } else if ( $this->shipment->service_has_pickup_points($method_params['service']) ) {
            if ( $method_params[$method_params['service']]['pickuppoints'] === 'yes' ) {
              $have_pickup_points = true;
            }
          }
        }
        $methods[] = array(
          'instance_id' => $method_instance_id,
          'service' => $method_params['service'],
          'have_pickups' => $have_pickup_points,
          'pickup_required' => ($have_pickup_points && ! $this->shipment->is_optional_pickup_point_service($method_params['service'])),
        );
        $found_methods[] = $method_instance_id;
      }

      return $methods;
    }

    /*private function get_all_methods_instance_ids() {
      $all_methods = array();
      $settings = $this->shipment->get_settings();
      $shipping_methods = json_decode(isset($settings['pickup_points']) ? $settings['pickup_points'] : '[]', true);

      foreach ( $shipping_methods as $method_instance_id => $method_params ) {
        $add_this = false;
        if ( ! empty($method_params['service']) && $method_params['service'] !== '__NULL__' ) {
          $add_this = true;
        }
        if ( $add_this && ! in_array($method_instance_id, $all_methods) ) {
          $all_methods[] = $method_instance_id;
        }
      }
      return $all_methods;
    }*/

    /*private function get_pickup_methods_instance_ids() {
      $methods_with_pickup = array();
      $settings = $this->shipment->get_settings();
      $shipping_methods = json_decode(isset($settings['pickup_points']) ? $settings['pickup_points'] : '[]', true);
      
      foreach ( $shipping_methods as $method_instance_id => $method_params ) {
        if ( ! empty($method_params['service']) ) {
          //if ( $method_params['service'] === '__PICKUPPOINTS__' )
          foreach ( $method_params as $service_id => $service_params ) {
            $have_pickup_points = false;
            if ( $method_params['service'] === '__PICKUPPOINTS__' ) {
              if ( isset($service_params['active']) && $service_params['active'] === 'yes' ) {
                $have_pickup_points = true;
              }
            } else if ( $this->shipment->service_has_pickup_points($method_params['service']) ) {
              if ( $method_params[$method_params['service']]['pickuppoints'] === 'yes' ) {
                $have_pickup_points = true;
              }
            }
            if ( $have_pickup_points && ! in_array($method_instance_id, $methods_with_pickup)) {
              $methods_with_pickup[] = $method_instance_id;
            }
          }
        }
      }

      return $methods_with_pickup;
    }*/

    /**
     * Get URL of the scripts folder
     * 
     * @return string
     */
    private function get_scripts_url() {
      return $this->core->dir_url . 'assets/blocks/';
    }

    /**
     * Get path to the scripts folder
     * 
     * @return string
     */
    private function get_scripts_dir() {
      return $this->core->dir . 'assets/blocks/';
    }

    /**
     * List of frontend scripts
     */
    private function register_frontend_scripts() {
      $scripts = array(
        'pickup-point-selection-front-checkout' => array(
          'js' => 'pickup-point-selection/checkout/front.js',
          'asset' => 'pickup-point-selection/checkout/front.asset.php',
          'css' => 'pickup-point-selection/checkout/front.css'
        ),
        'pickup-point-selection-front-cart' => array(
          'js' => 'pickup-point-selection/cart/front.js',
          'asset' => 'pickup-point-selection/cart/front.asset.php',
        ),
      );

      $this->register_scripts($scripts);
    }

    /**
     * List of admin area page edit scripts
     */
    private function register_editor_scripts() {
      $scripts = array(
        'pickup-point-selection-edit-checkout' => array(
          'js' => 'pickup-point-selection/checkout/index.js',
          'asset' => 'pickup-point-selection/checkout/index.asset.php',
        ),
        'pickup-point-selection-edit-cart' => array(
          'js' => 'pickup-point-selection/cart/index.js',
          'asset' => 'pickup-point-selection/cart/index.asset.php',
        ),
      );

      $this->register_scripts($scripts);
    }

    /**
     * Register received scripts
     * 
     * @param array $scripts_list
     */
    private function register_scripts( $scripts_list ) {
      foreach ( $scripts_list as $script_id => $script_files ) {
        if ( isset($script_files['js']) && isset($script_files['asset']) ) {
          $script_url = $this->get_scripts_url() . $script_files['js'];
          $script_asset_path = $this->get_scripts_dir() . $script_files['asset'];

          $script_asset = file_exists($script_asset_path) ? require $script_asset_path : array(
            'dependencies' => array(),
            'version' => $this->get_file_version($script_asset_path),
          );

          wp_register_script(
            $this->prefix . $script_id,
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
          );
        }

        if ( isset($script_files['translations']) ) {
          wp_set_script_translations(
            $this->prefix . $script_id,
            $script_files['translations'],
            $this->core->dir . '/core/languages'
          );
        }

        if ( isset($script_files['css']) ) {
          $style_url = $this->get_scripts_url() . $script_files['css'];
          $style_path = $this->get_scripts_dir() . $script_files['css'];

          wp_enqueue_style(
            $this->prefix . $script_id,
            $style_url,
            [],
            $this->get_file_version($style_path)
          );
        }
      }
    }

    /**
     * Extends the cart schema to include the shipping-workshop value.
     */
    private function extend_store_api()
    {
      Wc_Blocks_Extend_Store_Endpoint::init();
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode
     *
     * @param string $file - Local path to the file
     * @return string - The cache buster value to use for the given file
     */
    private function get_file_version( $file )
    {
      if ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file) ) {
        return filemtime($file);
      }
      
      return $this->version;
    }
  }
}
