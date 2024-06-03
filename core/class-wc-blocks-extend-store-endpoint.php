<?php
namespace Woo_Pakettikauppa_Core;

use \Automattic\WooCommerce\Blocks\Package;
use \Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use \Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;

if ( ! defined('ABSPATH') ) {
  exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Wc_Blocks_Extend_Store_Endpoint') ) {
  /**
  * Wc_Blocks_Extend_Store_Endpoint Class
  *
  * @class Wc_Blocks_Extend_Store_Endpoint
  * @version 1.0.0
  * @since 3.9.4
  * @package woo-pakettikauppa
  * @author Seravo
  */
  class Wc_Blocks_Extend_Store_Endpoint {
    /**
     * Stores Rest Extending instance.
     *
     * @var ExtendRestApi
     */
    private static $extend;

    /**
     * Plugin Identifier, unique to each plugin.
     *
     * @var string
     */
    const IDENTIFIER = 'wc-pakettikauppa';

    /**
     * Bootstraps the class and hooks required data.
     *
     */
    public static function init()
    {
      self::$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class );
      self::extend_store();
    }

    public static function extend_store()
    {

    }

    public static function extend_checkout_schema()
    {

    }
  }
}
