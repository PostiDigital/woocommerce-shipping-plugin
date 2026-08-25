<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * Plugin listing links, meta box registration and admin script/style enqueueing.
 */
trait Admin_Settings_Trait {

  public function add_submenu() {
    $submenu = add_submenu_page('', 'Create custom shipments', 'Custom shipments', 'manage_woocommerce', 'bulk-create-custom-shipment', array( $this, 'create_custom_shipment_table' ));
    add_action('load-' . $submenu, array( $this, 'load_admin_custom_shipment_js' ));
  }

  public function load_admin_custom_shipment_js() {
    add_action('admin_enqueue_scripts', array( $this, 'enqueue_admin_js' ));
  }

  public function enqueue_admin_js() {
    wp_enqueue_script($this->core->prefix . '_admin_custom_shipment_js', $this->core->dir_url . 'assets/js/admin_custom_shipment.js', array( 'jquery' ), $this->core->version, true);
    wp_localize_script(
      $this->core->prefix . '_admin_custom_shipment_js',
      'postiCustomShipmentData',
      array(
        'nonce' => wp_create_nonce($this->core->prefix . '_custom_shipment_nonce')
      )
    );
  }

  public function create_custom_shipment_table() {
    echo '<div class="wrap">';
    echo $this->get_custom_shipment_table();
    echo '</div>';
  }

  public function plugin_row_meta_wrapper( $links, $file ) {
    return $this->core->admin->plugin_row_meta($links, $file);
  }

  /**
   * Show row meta on the plugin screen.
   *
   * @param  mixed $links Plugin Row Meta
   * @param  mixed $file Plugin Base file
   *
   * @return  array
   */
  public function plugin_row_meta( $links, $file ) {
    if ( $file === $this->core->basename ) {
      $row_meta = array(
        'service' => sprintf(
          '<a href="%1$s" aria-label="%2$s">%3$s</a>',
          esc_url('https://www.posti.fi'),
          esc_attr__('Visit Website', 'woo-pakettikauppa'),
          /* translators: %s: Vendor name */
          sprintf(esc_html__('Show site %s', 'woo-pakettikauppa'), $this->core->vendor_name)
        ),
      );

      return array_merge($links, $row_meta);
    }

    return (array) $links;
  }

  /**
   * Register meta boxes for WooCommerce order metapage.
   */
  public function register_meta_boxes() {
    foreach ( wc_get_order_types('order-meta-boxes') as $type ) {
      $screen = Wc_Hpos::get_admin_order_page_screen_id();
      if ( ! $screen ) {
        $screen = $type;
      }

      add_meta_box(
        'woo-pakettikauppa', // Using a variable WILL BREAK JS
        // $this->core->prefix,
        esc_attr($this->core->vendor_name),
        array(
          $this,
          'meta_box',
        ),
        $screen,
        'side',
        'core'
      );
    }
  }

  /**
   * Enqueue admin-specific styles and scripts.
   */
  public function admin_enqueue_scripts() {
    wp_enqueue_style($this->core->prefix . '_admin', $this->core->dir_url . 'assets/css/admin.css', array(), $this->core->version);
    wp_enqueue_script($this->core->prefix . '_admin_js', $this->core->dir_url . 'assets/js/admin.js', array( 'jquery' ), $this->core->version, true);
    wp_localize_script($this->core->prefix . '_admin_js', 'pakettikauppa_params', array(
      'express_freight_services' => Shipment::get_express_freight_services(),
      'mapping_nonce'            => wp_create_nonce($this->core->prefix . '_nonce'),
      'sender_country_field'     => 'woocommerce_' . $this->core->shippingmethod . '_sender_country',
      'mapping_loading_text'     => esc_html__('Loading shipping methods…', 'woo-pakettikauppa'),
    ));
  }

  /**
   * Add settings link to the Pakettikauppa metabox on the plugins page when used with
   * the WordPress hook plugin_action_links_woo-pakettikauppa.
   *
   * @param array $links Already existing links on the plugin metabox
   *
   * @return array The plugin settings page link appended to the already existing links
   */
  public function add_settings_link( $links ) {
    $url  = admin_url('admin.php?page=wc-settings&tab=shipping&section=' . $this->core->shippingmethod);
    $link = sprintf('<a href="%1$s">%2$s</a>', $url, esc_attr__('Settings'));

    return array_merge(array( $link ), $links);
  }
}
