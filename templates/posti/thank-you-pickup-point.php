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

<div class="woocommerce-order-details__pickup-point posti-thank-you-pickup-point">
  <h2 class="wp-block-heading"><?php esc_html_e('Pickup point', 'woo-pakettikauppa'); ?></h2>
  <p><?php echo $pickup_point; ?></p>
</div>
