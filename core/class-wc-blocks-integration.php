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
   * @version  1.0.0
   * @since 3.9.4
   * @package  woo-pakettikauppa
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
     * Constructor
     */
    public function __construct( Core $plugin ) {
      $this->core = $plugin;
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

    }

    /**
     * Array of script handles to enqueue in the frontend context
     * 
     * @return array
     */
    public function get_script_handles() {
      return array();
    }

    /**
     * Array of script handles to enqueue in the editor context
     * 
     * @return array
     */
    public function get_editor_script_handles() {
      return array();
    }

    /**
     * Array of key, value pairs of data made available to the block on the frontend
     * 
     * @return array
     */
    public function get_script_data() {
      return array();
    }
  }
}
