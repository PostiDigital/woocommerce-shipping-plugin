<?php

namespace Woo_Posti_Core;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Frontend_Orders_Trait {
  public function create_shipment_for_order_automatically( $order_id ) {
    $order = new \WC_Order($order_id);

    if ( $this->shipment->can_create_shipment_automatically($order) ) {
      $this->shipment->allow_create_shipment($order, false);
      $this->shipment->create_shipment($order);
    }
  }

  public function restore_order_params_after_status_change( $order_id ) {
    $order = new \WC_Order($order_id);

    $this->shipment->allow_create_shipment($order, true);
  }

  /**
   * Display pickup point to customer after order.
   *
   * @param WC_Order $order the order that was placed
   */
  public function display_order_data( $order ) {
    $pickup_point = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point');

    if ( ! empty($pickup_point) ) {
      wc_get_template($this->core->templates->account_order, array( 'pickup_point' => esc_attr($pickup_point) ), '', $this->core->templates_dir);
    }
  }
}
