<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

/**
 * Shipping label storage/retrieval (including legacy single-label compatibility)
 * and fetching label PDFs / shipment status from the API.
 */
trait Shipment_Labels_Trait {

  /**
   * Function for maintain compatibility with previously used a single shipping label.
   * Required as long as there are old orders that store only one shipping label.
   *
   * @param int $post_id The post/order id
   *
   * @return array Previously used a single shipping label data converted in to new structure
   */
  public function get_old_structure_label( $post_id ) {
    $order = wc_get_order($post_id);
    $tracking_code = $order->get_meta('_' . $this->core->prefix . '_tracking_code', true);

    if ( empty($tracking_code) ) {
      return false;
    }

    $label_code = $order->get_meta('_' . $this->core->prefix . '_label_code', true);
    $tracking_url = $order->get_meta('_' . $this->core->prefix . '_tracking_url', true);
    $service_id = $order->get_meta('_' . $this->core->prefix . '_custom_service_id', true);
    $pickup_id = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point_id', true);
    $pickup_name = $order->get_meta('_' . str_replace('wc_', '', $this->core->prefix) . '_pickup_point', true);
    $ship_status = $order->get_meta('_' . $this->core->prefix . '_shipment_status', true);

    $order = new \WC_Order($post_id);
    $additional_services = $this->get_additional_services_from_order($order);
    $save_additional_services = array();
    $all_additional_services = $this->get_additional_services();
    foreach ( $additional_services as $service ) {
      foreach ( $service as $serv_key => $serv_value ) {
        $serv_name = $serv_key;
        if ( isset($all_additional_services[$service_id]) ) {
          foreach ( $all_additional_services[$service_id] as $serv_obj ) {
            if ( $serv_obj->service_code == $serv_key ) {
              $serv_name = $serv_obj->name;
              break;
            }
          }
        }
        if ( empty($serv_value) ) {
          $save_additional_services[$serv_key] = array(
            'name' => $serv_name,
            'values' => array(),
          );
        } else {
          $save_additional_services[$serv_key] = array(
            'name' => $serv_name,
            'values' => $serv_value,
          );
        }
      }
    }

    return array(
      'service_id' => $service_id,
      'tracking_code' => $tracking_code,
      'tracking_url' => $tracking_url,
      'label_code' => $label_code,
      'pickup_id' => $pickup_id,
      'pickup_name' => $pickup_name,
      'shipment_status' => $ship_status,
      'products' => array(),
      'additional_services' => $save_additional_services,
    );
  }

  /**
   * Delete previously used single shipping label data.
   *
   * @param int $post_id The post/order id
   */
  public function delete_old_structure_label( $post_id ) {
    $order = wc_get_order($post_id);
    $old_label = $order->get_meta('_' . $this->core->prefix . '_tracking_code', true);
    if ( ! empty($old_label) ) {
      $order->delete_meta_data('_' . $this->core->prefix . '_tracking_code');
      $order->delete_meta_data('_' . $this->core->prefix . '_tracking_url');
      $order->delete_meta_data('_' . $this->core->prefix . '_label_code');
      $order->delete_meta_data('_' . $this->core->prefix . '_creating_shipment');
      $order->delete_meta_data('_' . $this->core->prefix . '_custom_service_id');
      $order->save();
    }
  }

  /**
   * Get post meta of all shipping labels.
   * Include previously used single shipping label.
   *
   * @param int $post_id The post/order id
   *
   * @return array All Order shipping labels
   */
  public function get_labels( $post_id ) {
    $old_label = $this->get_old_structure_label($post_id);
    $order = wc_get_order($post_id);
    $labels = $order->get_meta('_' . $this->core->prefix . '_labels', true);

    if ( empty($labels) ) {
      $labels = array();
    }
    if ( $old_label ) {
      array_unshift($labels, $old_label);
    }

    return $labels;
  }

  /**
   * Get data of one shipping label.
   *
   * @param int $post_id The post/order id
   * @param string $tracking_code Shipping label tracking code if want get specific label.
   *
   * @return array|bool Shipping label data or false if not exist
   */
  public function get_single_label( $post_id, $tracking_code = '' ) {
    $labels = $this->get_labels($post_id);
    foreach ( $labels as $label ) {
      if ( empty($tracking_code) || $label['tracking_code'] == $tracking_code ) {
        return $label;
      }
    }
    return false;
  }

  /**
   * Save shipping label data to labels array in database.
   * If the order uses a single shipping label used previously, this function will convert it to the new structure.
   *
   * @param int $post_id The post/order id
   * @param array $save_values Values for want to save. A 'tracking_code' is required for saving to occur.
   */
  public function save_label( $post_id, $save_values = array() ) {
    if ( version_compare(get_bloginfo('version'), '5.3.0', '>=') ) {
      $current_time = strtotime(wp_date('Y-m-d H:i:s'));
    } else {
      $current_time = current_time('timestamp');
    }

    $label_values = array_replace(
      array(
        'service_id' => '',
        'tracking_code' => '',
        'tracking_url' => '',
        'label_code' => '',
        'pickup_id' => '',
        'pickup_name' => '',
        'shipment_status' => '',
        'products' => array(),
        'additional_services' => array(),
        'timestamp' => $current_time,
      ),
      $save_values
    );

    if ( ! empty($label_values['tracking_code']) ) {
      $order = wc_get_order($post_id);
      $all_labels = $this->get_labels($post_id);
      $insert = true;
      foreach ( $all_labels as $key => $label ) {
        if ( $label['tracking_code'] == $label_values['tracking_code'] ) {
          foreach ( $label_values as $name => $value ) {
            if ( array_key_exists($name, $save_values) ) {
              if ( $name == 'pickup_id' && empty($label_values['pickup_name']) ) {
                $pickup_name = $this->get_pickup_name($value, $label['service_id']);
                $all_labels[$key]['pickup_name'] = $pickup_name;
              }
              $all_labels[$key][$name] = $value;
            }
          }
          $insert = false;
        }
      }
      if ( $insert ) {
        array_push($all_labels, $label_values);
      }
      $order->update_meta_data('_' . $this->core->prefix . '_labels', $all_labels);
      $order->save();
      $this->delete_old_structure_label($post_id);
    }
  }

  /**
   * Get pickup point name.
   *
   * @param int $pickup_id Pickup point ID
   * @param int $service_id Service ID
   *
   * @return string Pickup point name
   */
  public function get_pickup_name( $pickup_id, $service_id ) {
    $pickup_info = json_decode($this->client->getPickupPointInfo($pickup_id, $service_id), true);
    if ( isset($pickup_info['name']) ) {
      return $pickup_info['name'] . ' (#' . $pickup_id . ')';
    } else {
      return '(#' . $pickup_id . ')';
    }
  }

  /**
   * Get the status of a shipment
   *
   * @param int $post_id The post id of the order to update status of
   *
   * @return int The status code of the shipment
   */
  public function get_shipment_status( $tracking_code ) {
    if ( ! empty($tracking_code) ) {
      $data = $this->client->getShipmentStatus($tracking_code);
      if ( ! empty($data) && isset($data[0]) ) {
        return $data[0]->{'status_code'};
      }
    }
    return '';
  }

  /**
   * @param array $tracking_codes
   *
   * @return SimpleXMLElement
   * @throws Exception
   */
  public function fetch_shipping_labels( $tracking_codes, $labels_size = null ) {
    return $this->client->fetchShippingLabels($tracking_codes, $labels_size);
  }

  /**
   * @param string $tracking_code
   *
   * @return SimpleXMLElement
   * @throws Exception
   */
  public function fetch_shipping_label( $tracking_code, $labels_size = null ) {
    return $this->fetch_shipping_labels(array( $tracking_code ), $labels_size);
  }
}
