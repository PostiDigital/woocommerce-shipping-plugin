<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * Order meta saving, automatic shipment creation and tracking email attachment.
 */
trait Admin_Order_Meta_Trait {

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
   * Save custom order meta in order edit page
   */
  public function save_admin_order_meta( $post_id ) {
    global $post_type;
    if ( 'shop_order' != $post_type ) {
      return $post_id;
    }
    $this->save_admin_order_meta_to_order($post_id);
  }

  /**
   * Save custom order meta in order edit page when use HPOS
   */
  public function save_admin_order_meta_hpos( $post_id ) {
    if ( Wc_Hpos::get_admin_order_page_screen_id() != Wc_Hpos::get_current_screen_id() ) {
      return $post_id;
    }
    
    remove_action('woocommerce_update_order', array( $this, 'save_admin_order_meta_hpos' )); //Avoid infinity loop when use $order->save() in this function

    $this->save_admin_order_meta_to_order($post_id);

    add_action('woocommerce_update_order', array( $this, 'save_admin_order_meta_hpos' )); //Restore hook

    return $post_id;
  }

  /**
   * Custom meta data save function of Order
   */
  private function save_admin_order_meta_to_order( $order_id ) {
    $order = wc_get_order($order_id);
    if ( ! $order ) {
      return;
    }
    if ( isset($_POST[$this->core->params_prefix . 'shipping_phone']) ) {
      $order->set_shipping_phone(wc_clean($_POST[$this->core->params_prefix . 'shipping_phone']));
    }
    if ( isset($_POST[$this->core->params_prefix . 'shipping_email']) ) {
      $order->update_meta_data('_shipping_email', wc_clean($_POST[$this->core->params_prefix . 'shipping_email']));
    }
    $order->save();
  }

  /**
   * Attach tracking URL to email.
   *
   * @param $order
   * @param bool $sent_to_admin
   * @param bool $plain_text
   * @param null $email
   */
  public function attach_tracking_to_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
    $settings = $this->shipment->get_settings();
    $add_to_email = $settings['add_tracking_to_email'];
    $add_pickup_point_to_email = $settings['add_pickup_point_to_email'];

    if ( ! ($add_to_email === 'yes' && isset($email->id) && $email->id === 'customer_completed_order') ) {
      return;
    }

    $labels = $this->shipment->get_labels($order->get_ID());

    $tracking_codes = array();
    foreach ( $labels as $label ) {
      if ( ! empty($label['tracking_code']) ) {
        array_push(
          $tracking_codes,
          array(
            'code' => $label['tracking_code'],
            'url' => $label['tracking_url'],
            'point' => $label['pickup_name'],
          )
        );
      }
    }

    if ( empty($tracking_codes) ) {
      return;
    }

    $template = $plain_text ? $this->core->templates->tracking_email->txt : $this->core->templates->tracking_email->html;
    wc_get_template(
      $template,
      array(
        'tracking_codes' => $tracking_codes,
        'add_pickup_point_to_email' => $add_pickup_point_to_email,
      ),
      '',
      $this->core->templates_dir
    );
  }
}
