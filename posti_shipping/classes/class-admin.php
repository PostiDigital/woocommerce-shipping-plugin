<?php

namespace Woo_Posti_Shipping;

class Admin extends \Woo_Pakettikauppa_Core\Admin
{
    public function __construct(\Woo_Pakettikauppa_Core\Core $plugin)
    {
        parent::__construct($plugin);
    }

    public function plugin_row_meta($links, $file)
    {
        if ($file === $this->core->basename) {
            $row_meta = array(
                'service' => sprintf(
                    '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                    esc_url('https://www.posti.fi'),
                    esc_attr__('Visit Posti', 'woo_posti_shipping'),
                    esc_html__('Show site Posti', 'woo_posti_shipping')
                ),
            );

            return array_merge($links, $row_meta);
        }

        return (array)$links;
    }
}

