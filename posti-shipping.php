<?php
/**
 * Plugin Name: Posti Shipping
 * Version: 3.4.0
 * Plugin URI: https://github.com/PostiDigital/woocommerce-shipping-plugin
 * Description: Posti shipping service for WooCommerce.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: woo-posti_shipping
 * Domain Path: /languages/
 * License: GPL v3 or later
 *
 * WC requires at least: 3.4
 * WC tested up to: 6.0.0
 *
 * Copyright: © 2017-2019 Seravo Oy, 2020-2022 Posti Oy
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * Autoloader loads nothing but Pakettikauppa libraries. The classname of the generated autoloader is not unique,
 * posti_shipping forks use the same autoloader which results in a fatal error if the main plugin and a posti_shipping plugin
 * co-exist.
 */
if ( ! class_exists('\Pakettikauppa\Client') ) {
  require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'core/class-core.php';

class Woo_Posti_Shipping extends Woo_Pakettikauppa_Core\Core {
  public $prefix = 'woo_posti_shipping';

  public function __construct( $config = [] ) {
    parent::__construct($config);

    add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ));
    add_action('woocommerce_before_checkout_form', array( $this, 'enqueue_frontend_scripts' ));

    add_action(
      'init',
      function() {
      if ( apply_filters('woo_posti_shipping_enable_setup_wizard', true) && current_user_can('manage_woocommerce') ) {
          add_action('admin_enqueue_scripts', array( $this, 'enqueue_setup_scripts' ));
      }
      }
    );
  }

  public function can_load() {
    if ( class_exists('Wc_Pakettikauppa') ) {
      add_action(
        'admin_notices',
        function() {
          echo '<div class="notice notice-error">';
          echo '<p>' . $this->text->activated_core_plugin_error() . '</p>';
          echo '</div>';
        }
      );

      return false;
    }

    return true;
  }

  public function enqueue_setup_scripts() {
    wp_enqueue_style('woo_posti_shipping_admin_setup', $this->dir_url . 'posti_shipping/assets/admin-setup.css', array(), $this->version);
    wp_enqueue_style('wp-admin');
    wp_enqueue_style('buttons');
  }

  public function enqueue_admin_scripts() {
    // wp_enqueue_style('woo_posti_shipping_admin', $this->dir_url . 'posti_shipping/assets/admin.css', array(), $this->version);
    // wp_enqueue_script('woo_posti_shipping_admin_js', $this->dir_url . 'assets/js/admin.js', array( 'jquery' ), $this->version, true);
  }

  public function enqueue_frontend_scripts() {
    // wp_enqueue_style('woo_posti_shipping', $this->dir_url . '/posti_shipping/assets/frontend.css', array(), $this->version);
    // wp_enqueue_script('woo_posti_shipping_js', $this->dir_url . '/assets/js/frontend.js', array( 'jquery' ), $this->version, true);
  }

  protected function load_shipment_class() {
    require_once 'core/class-shipment.php';
    require_once 'posti_shipping/classes/class-shipment.php';

    $shipment = new \Woo_Posti_Shipping\Shipment($this);
    $shipment->load();

    return $shipment;
  }

  protected function load_admin_class() {
    require_once 'core/class-admin.php';
    require_once 'posti_shipping/classes/class-admin.php';

    $admin = new \Woo_Posti_Shipping\Admin($this);
    $admin->load();

    return $admin;
  }

  public function add_shipping_method() {
    add_filter(
      'woocommerce_shipping_methods',
      function( $methods ) {
        // Ideally we'd control the class init ourselves, but the legacy shipping method doesn't work
        // if WC doesn't control it.
        // $methods[$this->shippingmethod] = $this->shipping_method_instance;

        $methods[$this->shippingmethod] = '\Woo_Posti_Shipping\Shipping_Method';
        return $methods;
      }
    );
  }


  public function load_textdomain() {
    parent::load_textdomain();

    load_plugin_textdomain(
      'woo_posti_shipping',
      false,
      dirname($this->basename) . '/posti_shipping/languages/'
    );
  }

   protected function load_shipping_method_class() {
    require_once 'core/class-shipping-method.php';
      require_once 'posti_shipping/classes/class-shipping-method.php';

      $method = new \Woo_Posti_Shipping\Shipping_Method();
      // We can't inject the core to the shipping method class if WooCommerce controls
      // the init of it. This class was turned into a singleton to go around that.
      // $method->injectCore($this)->load();
      // $method->load();

      return $method;
    }

  protected function load_text_class() {
    require_once 'core/class-text.php';
    require_once 'posti_shipping/classes/class-text.php';

    return new \Woo_Posti_Shipping\Text($this);
  }
}

$instance = new Woo_Posti_Shipping(
  [
    'root' => __FILE__,
    'version' => get_file_data(__FILE__, array( 'Version' ), 'plugin')[0],
    'shipping_method_name' => 'posti_shipping_method',
    'vendor_name' => 'Posti',
    'vendor_fullname' => 'Posti Shipping',
    'vendor_url' => 'https://www.posti.fi/',
    'vendor_logo' => 'assets/img/posti-logo.png',
    'setup_background' => 'assets/img/posti-background.jpg',
    'setup_page' => 'wcpk-setup',
    'pakettikauppa_api_config' => [
      'production' => [
        'base_uri' => 'https://nextshipping.posti.fi',
        'use_posti_auth' => true,
        'posti_auth_url' => 'https://oauth.posti.com',
      ],
      'test' => [
        'base_uri' => 'https://nextshipping.posti.fi',
        'use_posti_auth' => true,
        'posti_auth_url' => 'https://oauth.posti.com',
      ], 
    ], // Overrides defaults and UI settings
    'tracking_base_url' => 'https://www.posti.fi/fi/seuranta#/lahetys/',
    // 'pakettikauppa_api_comment' => 'From WooCommerce', // Overrides default
  ]
);

