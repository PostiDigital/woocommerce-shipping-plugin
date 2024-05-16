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
     * @var Core
     */
    private $core = null;

    public function __construct( Core $plugin ) {
      $this->core = $plugin;
    }

    public function load() {
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
        $integration_registry->register( new Wc_Blocks_Integration($this->core) );
      });
      add_action('woocommerce_blocks_cart_block_registration', function( $integration_registry ) {
        $integration_registry->register( new Wc_Blocks_Integration($this->core) );
      });
    }

    public function register_block_categories( $categories ) {
      return array_merge($categories, array(
          array( 'slug'  => $this->core->prefix, 'title' => $this->core->vendor_fullname ),
      ));
    }

    public function pk_data_callback() {
      return array();
    }

    public function pk_schema_callback() {
      return array();
    }
  }
}
