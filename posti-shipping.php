<?php
/**
 * Plugin Name: Posti Shipping
 * Version: 3.10.3
 * Plugin URI: https://github.com/PostiDigital/woocommerce-shipping-plugin
 * Description: Posti shipping service for WooCommerce.
 * Author: Posti
 * Author URI: https://www.posti.fi/
 * Text Domain: woo-posti_shipping
 * Domain Path: /core/languages/
 * License: GPL v3 or later
 *
 * Requires at least: 5.0
 * Tested up to: 6.5.5
 * WC requires at least: 3.4
 * WC tested up to: 9.0.2
 * Requires PHP: 7.1
 *
 * Copyright: © 2017-2019 Seravo Oy, 2020-2024 Posti Oy
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
    'order_pickup' => true, //enable or disable order pickup feature
    'order_pickup_callback_url' => 'https://connect.posti.fi/transportation/v1/orders', // PROD
    // 'pakettikauppa_api_comment' => 'From WooCommerce', // Overrides default
  ]
);

