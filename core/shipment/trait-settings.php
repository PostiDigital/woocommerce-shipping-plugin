<?php
namespace Woo_Posti_Core;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Shipment_Settings_Trait {
  public function get_settings() {
    if ( ! $this->settings ) {
      $this->settings = get_option('woocommerce_' . $this->core->shippingmethod . '_settings', array());
      if ( empty($this->settings) ) {
        $this->settings = array(
          'account_number' => '',
          'secret_key' => '',
          'pickup_points' => '',
          'sender_name' => get_bloginfo('name'),
          'sender_address' => get_option('woocommerce_store_address'),
          'sender_city' => get_option('woocommerce_store_city'),
          'sender_phone' => '',
          'sender_postal_code' => get_option('woocommerce_store_postcode'),
          'show_pickup_point_override_query' => '',
          'label_additional_info' => '',
          'download_type_of_labels' => '',
        );
      }
    }
    return $this->settings;
  }

  public function save_settings() {
    return update_option('woocommerce_' . $this->core->shippingmethod . '_settings', $this->get_settings(), 'yes');
  }

  public function update_setting( $name, $value ) {
    $this->settings[$name] = $value;
  }

  public function can_create_shipment_automatically( \WC_Order $order ) {
    $settings = $this->get_settings();
    if ( ! $this->is_allowed_create_shipment($order) ) {
      return false;
    }
    return ! empty($settings['create_shipments_automatically']) && $order->get_status() === $settings['create_shipments_automatically'];
  }

  public function allow_create_shipment( \WC_Order $order, $allow ) {
    $meta_key = '_' . $this->core->prefix . '_disable_shipment_create';
    if ( $allow ) {
      if ( $order->meta_exists($meta_key) ) {
        $order->delete_meta_data($meta_key);
        $order->save();
      }
    } else {
      $order->update_meta_data($meta_key, true);
      $order->save();
    }
  }

  public function is_allowed_create_shipment( \WC_Order $order ) {
    return ! $order->meta_exists('_' . $this->core->prefix . '_disable_shipment_create');
  }
}
