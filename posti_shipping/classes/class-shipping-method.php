<?php

namespace Woo_Posti_Shipping;

class Shipping_Method extends \Woo_Pakettikauppa_Core\Shipping_Method {
    public function get_core() {
      return \Woo_Posti_Shipping::get_instance();
    }

    protected function get_form_field_mode() {
        return array(
            'title'   => $this->get_core()->text->mode(),
            'type'    => 'hidden',
            'default' => 'production',
            'options' => array(
                'test'       => $this->get_core()->text->testing_environment(),
                'production' => $this->get_core()->text->production_environment(),
            ),
        );
    }
}
