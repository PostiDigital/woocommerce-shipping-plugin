== Developer notes ==

= Hooks =

* pakettikauppa_prepare_create_shipment

arguments: $order, $service_id, $additional_services

* pakettikauppa_post_create_shipment

arguments: $order

= Actions =

* pakettikauppa_create_shipments

Call for example:

    $pdf = '';
    $order_ids = array (15, 16, 17);
    $args = array( $order_ids, &$pdf );
    do_action_ref_array('pakettikauppa_create_shipments', $args);"

* pakettikauppa_fetch_shipping_labels

Call for example:

    $tracking_code='';
    $args = array( $order_id, &$tracking_code );
    do_action_ref_array('pakettikauppa_fetch_tracking_code', $args);

* pakettikauppa_fetch_tracking_code

Call for example:

    $args = array( $order_id, $order_id2, ... );
    do_action('pakettikauppa_create_shipments', $args);

