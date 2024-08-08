<?php
namespace Woo_Pakettikauppa_Core;

if ( ! defined('ABSPATH') ) {
  exit();
}

use \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! class_exists(__NAMESPACE__ . '\Wc_Hpos') ) {
  /**
   * Wc_Hpos Class
   *
   * @class Wc_Hpos
   * @version  1.0.0
   * @since 3.9.3
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Wc_Hpos {
    /**
     * @var Core
     */
    public $core = null;

    public function __construct( Core $plugin ) {
      $this->core = $plugin;
    }

    public function load() {
      add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
    }

    public function declare_compatibility() {
      if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->core->root_file, true );
      }
    }

    public static function get_admin_order_page_screen_id() {
      if ( class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
        && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
      ) {
        return wc_get_page_screen_id('shop-order');
      }

      return false;
    }

    public static function get_current_screen_id() {
      if ( ! function_exists('get_current_screen') ) {
        return false;
      }

      $screen = get_current_screen();
      return (isset($screen->id)) ? $screen->id : false;
    }

    public static function get_order_from_object( $post_or_order_object ) {
      return ($post_or_order_object instanceof \WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
    }
  }
}
