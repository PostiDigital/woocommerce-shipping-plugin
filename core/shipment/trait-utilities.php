<?php
namespace Woo_Posti_Core;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Shipment_Utilities_Trait {
  public static function get_status_text( $status_code ) {
    $statuses = array(
      13 => __('Item is collected from sender - picked up', 'woo-pakettikauppa'),
      20 => __('Exception', 'woo-pakettikauppa'),
      22 => __('Item has been handed over to the recipient', 'woo-pakettikauppa'),
      31 => __('Item is in transport', 'woo-pakettikauppa'),
      38 => __('C.O.D payment is paid to the sender', 'woo-pakettikauppa'),
      45 => __('Informed consignee of arrival', 'woo-pakettikauppa'),
      48 => __('Item is loaded onto a means of transport', 'woo-pakettikauppa'),
      56 => __('Item not delivered – delivery attempt made', 'woo-pakettikauppa'),
      68 => __('Pre-information is received from sender', 'woo-pakettikauppa'),
      71 => __('Item is ready for delivery transportation', 'woo-pakettikauppa'),
      77 => __('Item is returning to the sender', 'woo-pakettikauppa'),
      91 => __('Item is arrived to a post office', 'woo-pakettikauppa'),
      99 => __('Outbound', 'woo-pakettikauppa'),
    );
    $status = $statuses[ intval($status_code) ] ?? __('Unknown status', 'woo-pakettikauppa');
    if ( ! isset($statuses[ intval($status_code) ]) && ! empty($status_code) ) {
      $status .= ': ' . $status_code;
    }
    return $status;
  }

  public static function tracking_url( $base_url, $tracking_code ) {
    return ( empty($base_url) || empty($tracking_code) ) ? '' : $base_url . $tracking_code;
  }

  public static function get_default_service() {
    return '2103';
  }

  public static function validate_order_shipping_receiver( $order ) {
    return ! empty($order->get_formatted_shipping_full_name())
      && ( ! empty($order->get_shipping_address_1()) || ! empty($order->get_shipping_address_2()) )
      && ! empty($order->get_shipping_postcode())
      && ! empty($order->get_shipping_city());
  }

  public static function check_selected_product( $prod_id, $selected_products ) {
    if ( empty($selected_products) ) {
      return true;
    }
    foreach ( $selected_products as $product ) {
      if ( $prod_id == $product['prod'] ) {
        return true;
      }
    }
    return false;
  }

  public static function get_selected_product( $prod_id, $selected_products ) {
    foreach ( $selected_products as $product ) {
      if ( $prod_id == $product['prod'] ) {
        return $product;
      }
    }
    return false;
  }

  public static function order_weight( $order, $selected_products = array() ) {
    $weight = 0;
    $wcpf = new \WC_Product_Factory();
    foreach ( $order->get_items() as $item ) {
      if ( empty($item['product_id']) ) {
        continue;
      }
      $variation_id = $item['variation_id'];
      $item_match_id = ! empty($variation_id) ? $variation_id : $item['product_id'];
      if ( ! self::check_selected_product($item_match_id, $selected_products) ) {
        continue;
      }
      $product = $wcpf->get_product(! empty($variation_id) ? $variation_id : $item['product_id']);
      $selected_product = self::get_selected_product($item_match_id, $selected_products);
      if ( empty($product) || $product->is_virtual() || ! is_numeric($product->get_weight()) ) {
        continue;
      }
      $quantity = ($selected_product !== false) ? $selected_product['qty'] : $item->get_quantity();
      $weight += wc_get_weight($product->get_weight() * $quantity, 'kg');
    }
    return $weight;
  }

  public static function order_volume( $order, $selected_products = array() ) {
    $volume = 0;
    $wcpf = new \WC_Product_Factory();
    foreach ( $order->get_items() as $item ) {
      if ( empty($item['product_id']) ) {
        continue;
      }
      $variation_id = $item['variation_id'];
      $item_match_id = ! empty($variation_id) ? $variation_id : $item['product_id'];
      if ( ! self::check_selected_product($item_match_id, $selected_products) ) {
        continue;
      }
      $product = $wcpf->get_product(! empty($variation_id) ? $variation_id : $item['product_id']);
      $selected_product = self::get_selected_product($item_match_id, $selected_products);
      if ( empty($product) || $product->is_virtual() || ! is_numeric($product->get_width()) || ! is_numeric($product->get_height()) || ! is_numeric($product->get_length()) ) {
        continue;
      }
      $units = array( 'mm' => 0.001, 'cm' => 0.01, 'dm' => 0.1 );
      $dim_multiplier = $units[ strtolower(get_option('woocommerce_dimension_unit')) ] ?? 1;
      $quantity = ($selected_product !== false) ? $selected_product['qty'] : $item['qty'];
      $volume += pow($dim_multiplier, 3) * $product->get_width() * $product->get_height() * $product->get_length() * $quantity;
    }
    return $volume;
  }

  public static function calculate_reference( $id ) {
    $weights = array( 7, 3, 1 );
    $sum = 0;
    $base = str_split(strval($id));
    foreach ( array_reverse($base) as $index => $digit ) {
      $sum += $digit * $weights[ $index % 3 ];
    }
    return implode('', $base) . ((10 - $sum % 10) % 10);
  }

  public static function get_express_freight_services() {
    return array('2142', '2143', '2144', '2145');
  }

  public static function get_express_freight_pallet_types() {
    return array(
      'CC' => _x('Colli', 'Pallet type', 'woo-pakettikauppa'),
      'HP' => _x('Half pallet', 'Pallet type', 'woo-pakettikauppa'),
      'EUR' => _x('EUR-pallet', 'Pallet type', 'woo-pakettikauppa'),
      'FIN' => _x('FIN-pallet', 'Pallet type', 'woo-pakettikauppa'),
      'CW' => _x('Rollcage', 'Pallet type', 'woo-pakettikauppa'),
      'FPL' => _x('Furniture pallet', 'Pallet type', 'woo-pakettikauppa'),
      'KK' => _x('Loose colli', 'Pallet type', 'woo-pakettikauppa'),
    );
  }
}
