<?php

namespace Woo_Posti_Shipping;

class Shipping_Method extends \Woo_Pakettikauppa_Core\Shipping_Method {
public function get_core() {
      return \Woo_Posti_Shipping::get_instance();
    }
}
