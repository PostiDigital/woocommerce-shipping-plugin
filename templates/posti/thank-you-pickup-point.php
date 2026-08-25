<?php
/****************************************************************
 * Posti template: Selected pickup point information on the WooCommerce
 * thank you (order received) page.
 *
 * Variables:
 *   (string) $pickup_point - Selected pickup point
 ***************************************************************/

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}
?>

<h2><?php esc_html_e('Pickup point', 'woo-pakettikauppa'); ?></h2>
<p><?php echo esc_html($pickup_point); ?></p>
