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
    public $core = null;

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

    }

    public function register_block_categories( $categories ) {
      return array_merge($categories, array(
          //array( 'slug'  => 'omnivalt', 'title' => __('Omniva Blocks', 'omnivalt') ), //TODO: Padaryti
      ));
    }
  }
}
