<?php

namespace Woo_Posti_Shipping;

class Shipment extends \Woo_Pakettikauppa_Core\Shipment {
  public function __construct( \Woo_Pakettikauppa_Core\Core $plugin ) {
    parent::__construct($plugin);
  }

  public function get_pickup_point_methods() {
    $methods = array(
    );

    return $methods;
  }

  public static function tracking_url( $tracking_code ) {
    if ( empty($tracking_code) ) {
      return '';
    }
    $tracking_url = 'https://www.posti.fi/fi/seuranta#/lahetys/' . $tracking_code;

    return $tracking_url;
  }
}
