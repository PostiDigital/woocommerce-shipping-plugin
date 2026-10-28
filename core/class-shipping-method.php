<?php
namespace Woo_Posti_Core;

use WC_Countries;

if ( ! defined('ABSPATH') ) {
  exit;
}

require_once __DIR__ . '/shipping-method/trait-settings.php';
require_once __DIR__ . '/shipping-method/trait-pickup-points.php';

if ( ! class_exists(__NAMESPACE__ . '\Shipping_Method') ) {
  /**
   * Shipping_Method Class
   *
   * @class Shipping_Method
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Shipping_Method extends \WC_Shipping_Method {
    use Shipping_Method_Settings_Trait;
    use Shipping_Method_Pickup_Points_Trait;

    /**
     * Required to access Pakettikauppa client
     * @var Shipment $shipment
     */
    private $shipment = null;

    public $is_loaded = false;

    /**
     * Constructor for Pakettikauppa shipping class
     *
     * @access public
     * @return void
     */
    public function __construct( $instance_id = 0 ) {
      parent::__construct($instance_id);

      $this->load();
    }

    public function get_core() {
      return \Woo_Posti_Shipping::get_instance();
    }

    public function load() {
      if ( $this->is_loaded ) {
        return;
      }

      $this->id = $this->get_core()->shippingmethod;
      $this->method_title = $this->get_core()->vendor_name;
      $this->method_description = __('Edit to select shipping company and shipping prices.', 'woo-pakettikauppa');

      $this->supports = array(
        'settings',
      );

      $this->init();

      add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));

      $this->is_loaded = true;
    }

    /**
     * Initialize Pakettikauppa shipping
     */
    public function init() {
      $this->form_fields = $this->my_global_form_fields();
      $this->title = $this->get_option('title');
    }
  }
}
