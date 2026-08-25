<?php
/**
 * Plugin Name: Posti Shipping
 * Version: 3.10.13
 * Plugin URI: https://github.com/PostiDigital/woocommerce-shipping-plugin
 * Description: Posti shipping service for WooCommerce.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: woo-posti_shipping
 * Domain Path: /core/languages/
 * License: GPL v3 or later
 *
 * Requires at least: 5.0
 * Tested up to: 7.0
 * WC requires at least: 4.7
 * WC tested up to: 10.7.0
 * Requires PHP: 7.1
 *
 * Copyright: © 2017-2019 Seravo Oy, 2020-2026 Posti Oy
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

require_once 'core/class-text.php';
require_once 'core/class-shipment.php';
require_once 'core/class-wc-hpos.php';
require_once 'core/class-wc-blocks.php';
require_once 'core/class-frontend.php';
require_once 'core/class-admin.php';
require_once 'core/class-check-tool.php';
require_once 'core/class-manifest.php';
require_once 'core/class-product.php';
require_once 'core/class-shortcode.php';
require_once 'core/class-setup-wizard.php';
// class-shipping-method.php extends WC_Shipping_Method, so it must be required lazily,
// only after WooCommerce has loaded, not eagerly here.

class Woo_Posti_Shipping {
  public $version = null;

  public $root_file;
  public $basename;
  public $dir;
  public $dir_url;
  public $templates_dir;
  public $templates;
  public $prefix;
  public $params_prefix;

  public $shippingmethod; // Name of the shipping method. Not to be confused with $shipping_method_instance!
  public $vendor_name;
  public $vendor_fullname;
  public $vendor_url;
  public $vendor_logo;
  public $tracking_base_url;
  public $setup_background;
  public $setup_page;

  public $admin;
  public $frontend;
  public $text;
  public $shipping_method_instance; // Added as an afterthought to fix a bug, merge with $shippingmethod in the future.
  public $setup_wizard;
  public $product;
  public $shortcode;
  public $check_tool;
  public $manifest;

  public $api_config; // Used by Pakettikauppa\Client
  public $api_comment; // Used by ^
  public $api_mode;

  public $order_pickup;
  public $order_pickup_url;

  public static $instance; // The class is a singleton.

  /**
   * Takes an options array with the following key-values.
   *
   * [
   *   'root' => __FILE__, // Reference to the file the plugin was loaded from. Why? Not all of the code is refactored.
   *   'textdomain' => 'woo-pakettikauppa', // Self explanatory
   *   'shipping_method_name' => 'pakettikauppa_shipping_method', // ID to use for the WooCommerce shipping method. Must be unique.
   *
   *   // Branding options
   *   'vendor_name' => 'Pakettikauppa',
   *   'vendor_url' => 'https://www.pakettikauppa.fi/',
   *   'vendor_logo' => 'assets/img/pakettikauppa-logo.png',
   *   'setup_background' => 'assets/img/pakettikauppa-background.jpg',
   *   'setup_page' => 'wcpk-setup',
   * ]
   */
  public function __construct( $config = array() ) {
    $this->version = $config['version'];
    $this->root_file = $config['root'];
    $this->basename = plugin_basename($config['root']);
    $this->dir = plugin_dir_path($config['root']);
    $this->dir_url = plugin_dir_url($config['root']);
    $this->templates_dir = $config['templates_dir'] ?? plugin_dir_path($config['root']) . 'templates/';
    $this->templates = (object) ($config['templates'] ?? array(
      'checkout_pickup' => 'pakettikauppa/checkout-pickup.php',
      'account_order' => 'pakettikauppa/myaccount-order.php',
      'tracking_email' => (object) array(
        'html' => 'pakettikauppa/tracking-email-html.php',
        'txt' => 'pakettikauppa/tracking-email-txt.php',
      ),
    ));

    $this->prefix = 'wc_pakettikauppa';
    $this->params_prefix = 'pakettikauppa' . '_';
    $this->shippingmethod = 'posti_shipping_method';
    
    $this->vendor_name = 'Posti';
    $this->vendor_fullname = 'Posti Shipping';
    $this->vendor_url = 'https://www.posti.fi';
    $this->vendor_logo = $this->dir_url . 'assets/img/posti-logo.png';

    $this->tracking_base_url = 'https://www.posti.fi/fi/seuranta#/lahetys/';

    $this->setup_background = $this->dir_url . 'assets/img/posti-background.jpg';
    $this->setup_page = 'wcpk-setup';

    $this->text = new Woo_Posti_Core\Text($this);

    $this->api_config = apply_filters('posti_api_configs', array(
      'production' => array(
        'base_uri' => 'https://nextshipping.posti.fi',
        'use_posti_auth' => true,
        'posti_auth_url' => 'https://oauth2.posti.com',
      ),
    ));
    $this->api_mode = 'production';
    $this->api_comment = 'From WooCommerce';

    $this->order_pickup = true;
    $this->order_pickup_url = 'https://connect.posti.fi/transportation/v1/orders';

    self::$instance = $this;

    $wc_hpos = new Woo_Posti_Core\Wc_Hpos($this);
    $wc_hpos->load();

    $wc_blocks = new Woo_Posti_Core\Wc_Blocks($this);
    $wc_blocks->load();

    add_action(
      'plugins_loaded', //'wp_loaded',
      function() {
        $this->load_textdomain();
        $this->load_pre_init();
      }
    );
    add_action(
      'init', //'wp_loaded',
      function() {
        $this->load();
      }
    );
    $this->load_shipping_method();
    $this->add_shipping_method();
  }

  /**
   * Get class instance. Only used by Shipping_Method class, which can't be injected with
   * $this. After legacy shipping method is removed, rethink about the existence of this, as it's a terrible hack.
   *
   * See https://github.com/Seravo/woo-pakettikauppa/issues/96.
   */
  public static function get_instance() {
    return self::$instance;
  }

  /**
   * Sanity check. Is WooCommerce active?
   */
  public function woocommerce_exists() {
    if ( function_exists('WC') ) {
      return true;
    }

    return false;
  }

  public function load_pre_init() {
    if ( is_admin() ) {
      $this->manifest = new Woo_Posti_Core\Manifest($this);
    }
  }

  public function load() {
    if ( ! $this->woocommerce_exists() ) {
      add_action(
        'admin_notices',
        function() {
          echo '<div class="notice notice-error">';
          echo '<p>' . $this->text->no_woo_error() . '</p>';
          echo '</div>';
        }
      );

      return;
    }

    /**
     * If the shipping method is added too early, errors will ensue.
     * If the shipping method is added too late, errors will ensue.
     */
    add_action(
      'wp_loaded', //woocommerce_shipping_init',
      function() {
        // Instance is only used for hacking classes together.
        // It's not used by WooCommerce. WooCommerce creates it's own instances, otherwise the legacy
        // shipping method breaks. If this class doesn't contain the shipping method class instance
        // things like setup wizard break.
        if ( ! $this->shipping_method_instance ) {
          require_once 'core/class-shipping-method.php';
          $this->shipping_method_instance = new Woo_Posti_Core\Shipping_Method();
        }
        $this->add_shipping_method();
      }
    );

    if ( is_admin() ) {
      $this->admin = new Woo_Posti_Core\Admin($this);
      $this->admin->load();

      $this->setup_wizard = $this->maybe_load_setup_wizard();

      $this->check_tool = new Woo_Posti_Core\Check_Tool($this);
    }

    // Always load classes
    $this->frontend = new Woo_Posti_Core\Frontend($this);
    $this->frontend->load();

    $this->product = new Woo_Posti_Core\Product($this);
    $this->product->load();

    $this->shortcode = new Woo_Posti_Core\Shortcode($this);
    $this->shortcode->load();

    return $this;
  }

  public function load_textdomain() {
    load_plugin_textdomain(
      'woo-pakettikauppa',
      false,
      dirname($this->basename) . '/core/languages/'
    );
    load_plugin_textdomain(
      'woo-posti_shipping',
      false,
      dirname($this->basename) . '/core/languages/'
    );
  }

  public function load_shipping_method() {
    add_action(
      'woocommerce_shipping_init',
      function() {
        if ( ! $this->shipping_method_instance ) {
          require_once 'core/class-shipping-method.php';
          $this->shipping_method_instance = new Woo_Posti_Core\Shipping_Method();
        }
      }
    );
  }

  public function add_shipping_method() {
    add_filter(
      'woocommerce_shipping_methods',
      function( $methods ) {
        // Ideally we'd control the class init ourselves, but the legacy shipping method doesn't work
        // if WC doesn't control it.
        // $methods[$this->shippingmethod] = $this->shipping_method_instance;

        // WooCommerce instantiates this class name itself, so it must exist by now
        require_once 'core/class-shipping-method.php';
        $methods[$this->shippingmethod] = 'Woo_Posti_Core\Shipping_Method';

        return $methods;
      }
    );
  }

  protected function maybe_load_setup_wizard() {
    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);

    if ( $page === $this->setup_page ) {
      return new Woo_Posti_Core\Setup_Wizard($this);
    }

    return false;
  }
}

$instance = new Woo_Posti_Shipping(
  [
    'root' => __FILE__,
    'version' => get_file_data(__FILE__, array( 'Version' ), 'plugin')[0],
  ]
);

