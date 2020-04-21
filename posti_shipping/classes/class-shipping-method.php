<?php

namespace Woo_Posti_Shipping;

class Shipping_Method extends \Woo_Pakettikauppa_Core\Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
    }

    public function get_core()
    {
        return \Woo_Posti_Shipping::get_instance();
    }

    protected function get_form_field_mode()
    {
        return array(
            'type'    => 'hidden',
            'default' => 'production',
        );
    }

    public function generate_hidden_html( $key, $args )
    {
        $field_key = $this->get_field_key($key);

        return '<input type="hidden" name="' . esc_html($field_key) . '" value="' . esc_attr($args['default']) . '" />';
    }
}
