<?php
namespace Woo_Posti_Core;

use WC_Countries;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Shipping_Method_Settings_Trait {
  public function validate_pickuppoints_field( $key, $value ) {
    // Merge with previously saved mapping so that shipping methods whose
    // fields were not submitted (e.g. their service is unavailable for the
    // currently selected sender country) keep their stored configuration
    // instead of being silently dropped.
    $old = $this->get_option($key);
    if ( is_string($old) && $old !== '' ) {
      $old = json_decode($old, true);
    }
    if ( is_array($old) && is_array($value) ) {
      foreach ( $old as $method_id => $method_config ) {
        if ( ! isset($value[ $method_id ]) ) {
          $value[ $method_id ] = $method_config;
        }
      }
    }
    return wp_json_encode($value);
  }

  public function generate_notices_html( $key, $value ) {
    $settings = $this->get_core()->shipment->get_settings();
    $shipping_method = $this->get_core()->shippingmethod;
    $field_pref = 'woocommerce_' . $shipping_method . '_';
    $configs = $this->get_core()->api_config;
    if ( isset($_POST[$field_pref . 'account_number']) ) {
      $settings['account_number'] = sanitize_text_field($_POST[$field_pref . 'account_number']);
      $settings['secret_key'] = trim($_POST[$field_pref . 'secret_key']);
    }

    wp_localize_script( // Passing values to JS instead of directly inserting into JS code
      $this->get_core()->prefix . '_admin_js',
      'postiNoticesData',
      array(
        'apiAccount' => $settings['account_number'],
        'apiSecret'  => $settings['secret_key'],
        'nonce' => wp_create_nonce($this->get_core()->prefix . '_nonce')
      )
    );

    ob_start();
    ?>
    <script>
    jQuery(function( $ ) {
      $( document ).ready(function() {
        hide_mode_react();

        $.ajax({
          type: "POST",
          url: ajaxurl,
          data: {
            action: 'check_api',
            api_account: postiNoticesData.apiAccount,
            api_secret: postiNoticesData.apiSecret,
            _wpnonce: postiNoticesData.nonce
          },
          dataType: 'json'
        }).done(function( status ) {
          hide_mode_react(status.api_good);
          if (status.api_good) {
            show_api_notice("", false);
          } else {
            var msg = status.msg;
            if (status.error) {
              msg += ".<br/><b><?php _e('Error', 'woo-pakettikauppa'); ?>:</b> " + status.error;
            }
            if (status.code) {
              msg += " <i>(<?php _e('Code', 'woo-pakettikauppa'); ?> " + status.code + ")</i>";
            }
            show_api_notice(msg, true);
          }
        });
      });

      function hide_mode_react( show = true ) {
        if (show) {
          $(".mode_react").closest("tr").removeClass("row-disabled");
          $("h3.mode_react").removeClass("row-disabled");
        }
        else {
          $(".mode_react").closest("tr").addClass("row-disabled");
          $("h3.mode_react").addClass("row-disabled");
        }
      }

      function show_api_notice(text, show = true) {
        if (show) {
          $("#pakettikauppa_notices").show();
          $("#pakettikauppa_notice_api span").html(text+".");
          $("#pakettikauppa_notice_api").show();
        } else {
          $("#pakettikauppa_notices").hide();
          $("#pakettikauppa_notice_api").hide();
          $("#pakettikauppa_notice_api p").text('');
        }
      }
    });
    </script>
    <tr id="pakettikauppa_notices" style="display:none;"><td colspan="2">
      <div id="pakettikauppa_notice_api" class="pakettikauppa-notice notice-error">
        <p><b><?php echo strtoupper(__('API error!', 'woo-pakettikauppa')); ?></b> <span></span></p>
      </div>
    </td></tr>
    <?php
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
  }

  public function generate_pickuppoints_html( $key, $value ) {
    $sender_country = $this->get_option('sender_country');

    ob_start();
    ?>
      <script>
        function pkSetInputs(parent, disabled) {
            var inputs = parent.querySelectorAll('input');
            for(var j=0; j<inputs.length; ++j) {
                if (disabled){
                    inputs[j].setAttribute('disabled', disabled);
                } else {
                    inputs[j].removeAttribute('disabled');
                }
            }
        }

        function pkChangeOptions(elem, methodId) {
            var strUser = elem.options[elem.selectedIndex].value;
            var elements = document.getElementsByClassName('pk-services-' + methodId);
            var servicesElement = document.getElementById('services-' + methodId + '-' + strUser);
            var pickuppointsElement = document.getElementById('pickuppoints-' + methodId);
            var servicePickuppointsElement = document.getElementById('service-' + methodId + '-' + strUser + '-pickuppoints');
            var card = elem.closest('.pk-method-card');
            if (card) {
                if (strUser === '__NULL__') {
                    card.classList.add('pk-method-card--inactive');
                } else {
                    card.classList.remove('pk-method-card--inactive');
                }
                var selectedOption = elem.options[elem.selectedIndex];
                var isUnavailable = !!(selectedOption && selectedOption.classList.contains('pk-option-unavailable'));
                if (!isUnavailable) {
                    card.classList.remove('pk-method-card--unavailable');
                    var warning = card.querySelector('.pk-method-card__warning');
                    if (warning) {
                        warning.style.display = 'none';
                    }
                }
                var wcEnabled = card.getAttribute('data-wc-enabled') === '1';
                var hasService = (strUser !== '__NULL__');
                var dotState = 'active';
                if (!hasService) {
                    dotState = 'inactive';
                }
                if (isUnavailable || (hasService && !wcEnabled)) {
                    dotState = 'error';
                }
                var dot = card.querySelector('.pk-method-card__dot');
                if (dot) {
                    dot.className = 'pk-method-card__dot pk-dot--' + dotState;
                }
            }
            for(var i=0; i<elements.length; ++i) {
                elements[i].style.display = "none";
                pkSetInputs(elements[i], true);
            }
            if (strUser == '__PICKUPPOINTS__') {
              if (pickuppointsElement) {
                  pickuppointsElement.style.display = "block";
                  pkSetInputs(pickuppointsElement, false);
              }
              if (servicesElement) {
                  servicesElement.style.display = "none";
                  pkSetInputs(servicesElement, true);
              }
            } else {
              if (pickuppointsElement) {
                  pickuppointsElement.style.display = "none";
                  pkSetInputs(pickuppointsElement, true);
              }
              if (servicesElement) {
                  servicesElement.style.display = "block";
                  pkSetInputs(servicesElement, false);
              }
              if (elem.options[elem.selectedIndex].getAttribute('data-haspp') == 'true') {
                  servicePickuppointsElement.style.display = "block";
                  pkSetInputs(servicePickuppointsElement, false);
              }
            }
        }

        function pkInitMappingCards(container) {
            var scope = container || document;
            var selects = scope.querySelectorAll('.pk-method-card select.pk-service-select');
            for (var i = 0; i < selects.length; ++i) {
                pkChangeOptions(selects[i], selects[i].getAttribute('data-method'));
            }
        }
      </script>
      <tr>
        <th colspan="2" class="titledesc mode_react" scope="row"><?php echo esc_html($value['title']); ?></th>
      </tr>
      <tr>
        <td colspan="2" class="mode_react">
          <div id="pk-mapping-container" class="pk-mapping-container">
            <?php echo $this->render_pickup_points_mapping($sender_country); ?>
          </div>
        </td>
      </tr>
      <script>pkInitMappingCards(document.getElementById("pk-mapping-container"));</script>
    <?php
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
  }

  public function generate_enchancedtextarea_html( $key, $value ) {
    $field_key = $this->get_field_key($key);
    $field_value = $this->get_option($key);
    ob_start();
    ?>
    <tr valign="top" class="pakettikauppa-setting">
      <th scope="row" class="titledesc"><label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label></th>
      <td class="forminp"><fieldset>
        <legend class="screen-reader-text"><span><?php echo esc_html($value['title']); ?></span></legend>
        <textarea rows="3" cols="20" class="input-text wide-input " type="textarea" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" style="" placeholder=""><?php echo esc_html($field_value); ?></textarea>
        <?php if ( ! empty($value['available_params']) && is_array($value['available_params']) ) : ?>
          <?php foreach ( $value['available_params'] as $param_key => $param_desc ) : ?>
            <p class="description enchtext noselect"><code class="enchtext-code" data-param="<?php echo esc_html($param_key); ?>" onclick="click_enchancedtextarea_code('<?php echo $field_key; ?>', '<?php echo esc_html($param_key); ?>');">{<?php echo esc_html($param_key); ?>}</code> - <?php echo esc_html($param_desc); ?></p>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ( ! empty($value['description']) ) : ?><p class="description"><?php echo $value['description']; ?></p><?php endif; ?>
      </fieldset></td>
    </tr>
    <?php
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
  }

  public function generate_button_html( $key, $value ) {
    $field_key = $this->get_field_key($key);
    ob_start();
    ?>
    <tr valign="top" class="pakettikauppa-setting">
      <th scope="row" class="titledesc"><label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label></th>
      <td class="forminp"><fieldset><a class="button button-primary" href="<?php echo $value['url']; ?>"><?php echo $value['text']; ?></a></fieldset></td>
    </tr>
    <?php
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
  }

  public function generate_hidden_html( $key, $args ) {
    $field_key = $this->get_field_key($key);
    return '<input type="hidden" name="' . esc_html($field_key) . '" value="' . esc_attr($args['default']) . '" />';
  }

  private function my_global_form_fields() {
    if ( ! class_exists(__NAMESPACE__ . '\Shipment') ) {
      require_once 'class-shipment.php';
    }
    $wc_countries = new WC_Countries();
    $fields = array(
      'notices' => array('type' => 'notices'),
      array('title' => '', 'type' => 'title', 'class' => 'hidden'),
      'account_number' => array('title' => __('API key', 'woo-pakettikauppa'), 'desc' => sprintf(__('API key provided by %1$s', 'woo-pakettikauppa'), \esc_html($this->get_core()->vendor_name)), 'type' => 'text', 'default' => '', 'desc_tip' => true),
      'secret_key' => array('title' => __('API secret', 'woo-pakettikauppa'), 'desc' => __('API secret provided by Posti', 'woo-pakettikauppa'), 'type' => 'password', 'default' => '', 'desc_tip' => true),
      array('title' => __('Store owner information', 'woo-pakettikauppa'), 'type' => 'title'),
      'sender_name' => array('title' => __('Sender name', 'woo-pakettikauppa'), 'type' => 'text', 'default' => get_bloginfo('name')),
      'sender_address' => array('title' => __('Sender address', 'woo-pakettikauppa'), 'type' => 'text', 'default' => WC()->countries->get_base_address()),
      'sender_postal_code' => array('title' => __('Sender postal code', 'woo-pakettikauppa'), 'type' => 'text', 'default' => WC()->countries->get_base_postcode()),
      'sender_city' => array('title' => __('Sender city', 'woo-pakettikauppa'), 'type' => 'text', 'default' => WC()->countries->get_base_city()),
      'sender_country' => array('title' => __('Sender country', 'woo-pakettikauppa'), 'type' => 'select', 'default' => WC()->countries->get_base_country(), 'options' => $wc_countries->get_countries()),
      'sender_phone' => array('title' => __('Sender phone', 'woo-pakettikauppa'), 'type' => 'text'),
      'sender_email' => array('title' => __('Sender email', 'woo-pakettikauppa'), 'type' => 'email'),
      'info_code' => array('title' => __('Info-code for shipments', 'woo-pakettikauppa'), 'type' => 'text', 'default' => '', 'description' => __('Info-code length must be 15 characters or less', 'woo-pakettikauppa'), 'custom_attributes' => array('maxlength' => 15)),
      'order_pickup' => array('title' => __('Order pickup', 'woo-pakettikauppa'), 'type' => 'title'),
      'order_pickup_customer_id' => array('title' => __('Customer ID', 'woo-pakettikauppa'), 'desc' => '', 'type' => 'text', 'default' => '', 'desc_tip' => true),
      'order_pickup_invoice_id' => array('title' => __('Invoice ID', 'woo-pakettikauppa'), 'desc' => '', 'type' => 'text', 'default' => '', 'desc_tip' => true),
      'pickup_points' => array('title' => __('Shipping methods mapping', 'woo-pakettikauppa'), 'type' => 'pickuppoints'),
      array('title' => __('Shipping settings', 'woo-pakettikauppa'), 'type' => 'title', 'description' => (function() { $url = 'https://docs.woocommerce.com/document/setting-up-shipping-zones/'; return sprintf(__('You can activate new shipping method to checkout in %1$s. For more information, see %2$s', 'woo-pakettikauppa'), '<b>' . __('WooCommerce > Settings > Shipping > Shipping zones', 'woo-pakettikauppa') . '</b>', '<a target="_blank" href="' . $url . '">' . $url . '</a>'); })()),
      'add_tracking_to_email' => array('title' => __('Add tracking link to the order completed email', 'woo-pakettikauppa'), 'type' => 'checkbox', 'default' => 'yes', 'class' => 'mode_react'),
      'add_pickup_point_to_email' => array('title' => __('Add selected pickup point information to the order completed email', 'woo-pakettikauppa'), 'type' => 'checkbox', 'default' => 'yes', 'class' => 'mode_react'),
      'ignore_product_weight' => array('title' => __('Ignore product weight information', 'woo-pakettikauppa'), 'type' => 'checkbox', 'default' => 'no', 'class' => 'mode_react'),
      'exclude_prods_without_hs' => array('title' => __('Exclude products without HS tariff code from the CN23 customs document', 'woo-pakettikauppa'), 'type' => 'checkbox', 'default' => 'no', 'class' => 'mode_react'),
      'express_freight_pallet_type' => array('title' => __('Express-freight default pallet type', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'CC', 'options' => Shipment::get_express_freight_pallet_types(), 'class' => 'mode_react'),
      'change_order_status_to' => array('title' => __('When creating shipping label change order status to', 'woo-pakettikauppa'), 'type' => 'select', 'default' => '', 'options' => array('' => __('No order status change', 'woo-pakettikauppa'), 'completed' => __('Completed', 'woocommerce'), 'processing' => __('Processing', 'woocommerce')), 'class' => 'mode_react'),
      'translate_products_in_labels' => array('title' => __('Translate products names in labels', 'woo-pakettikauppa'), 'type' => 'checkbox', 'default' => 'no', 'description' => __("Use the client's language for product names displayed on labels", 'woo-pakettikauppa'), 'desc_tip' => true, 'class' => 'mode_react'),
      'create_shipments_automatically' => array('title' => __('Create shipping labels automatically', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'no', 'options' => array('no' => __('No automatic creation of shipping labels', 'woo-pakettikauppa'), 'completed' => sprintf(__('When order status is "%s"', 'woo-pakettikauppa'), __('Completed', 'woocommerce')), 'processing' => sprintf(__('When order status is "%s"', 'woo-pakettikauppa'), __('Processing', 'woocommerce'))), 'class' => 'mode_react'),
      'labels_size' => array('title' => __('Shipping label size', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'menu', 'options' => array('A5' => 'A5', '107x225' => '107x225'), 'class' => 'mode_react'),
      'download_type_of_labels' => array('title' => __('Print labels', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'menu', 'options' => array('browser' => __('Browser', 'woo-pakettikauppa'), 'download' => __('Download', 'woo-pakettikauppa')), 'class' => 'mode_react'),
      'post_label_to_url' => array('title' => __('Post shipping label to URL', 'woo-pakettikauppa'), 'type' => 'text', 'default' => '', 'description' => __('Plugin can upload shipping label to an URL when creating shipping label. Define URL if you want to upload PDF.', 'woo-pakettikauppa'), 'desc_tip' => true, 'class' => 'mode_react'),
      array('title' => __('Checkout options', 'woo-pakettikauppa'), 'type' => 'title'),
      'field_phone_required' => array('title' => __('Make shipping phone number mandatory'), 'type' => 'select', 'default' => 'no', 'options' => array('no' => __('No'), 'yes' => __('Yes'))),
      'pickup_points_type' => array('title' => __('Pickup points type', 'woo-pakettikauppa'), 'type' => 'multiselect', 'options' => array('all' => __('All', 'woo-pakettikauppa'), 'PRIVATE_LOCKER' => __('Private lockers', 'woo-pakettikauppa'), 'OUTDOOR_LOCKER' => __('Outdoor lockers', 'woo-pakettikauppa'), 'PARCEL_LOCKER' => __('Parcel lockers', 'woo-pakettikauppa'), 'PICKUP_POINT,AGENCY' => __('Pickup points', 'woo-pakettikauppa')), 'default' => 'all', 'description' => __('Choose which type of pickup points will be displayed in the list of pickup points', 'woo-pakettikauppa'), 'desc_tip' => true),
      'pickup_points_search_limit' => array('title' => __('Pickup point search limit', 'woo-pakettikauppa'), 'type' => 'number', 'default' => 5, 'description' => __('Limit the amount of nearest pickup points shown.', 'woo-pakettikauppa'), 'desc_tip' => true, 'class' => 'mode_react'),
      'pickup_point_list_type' => array('title' => __('Show pickup points as', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'menu', 'options' => array('menu' => __('Menu', 'woo-pakettikauppa'), 'list' => __('List', 'woo-pakettikauppa')), 'class' => 'mode_react'),
      'show_pickup_point_override_query' => array('title' => __('Show pickup point override in checkout', 'woo-pakettikauppa'), 'type' => 'select', 'default' => 'yes', 'options' => array('no' => __('No'), 'yes' => __('Yes')), 'description' => __('Allow user to use custom address for pickup point search.', 'woo-pakettikauppa'), 'desc_tip' => true),
      'cod_title' => array('title' => __('Cash on Delivery (COD) Settings', 'woo-pakettikauppa'), 'type' => 'title'),
      'cod_iban' => array('title' => __('Bank account number for Cash on Delivery (IBAN)', 'woo-pakettikauppa'), 'type' => 'text', 'default' => ''),
      'cod_bic' => array('title' => __('BIC code for Cash on Delivery', 'woo-pakettikauppa'), 'type' => 'text', 'default' => ''),
      array('title' => __('Advanced settings', 'woo-pakettikauppa'), 'type' => 'title'),
      'label_additional_info' => array('title' => __('Add additional text on labels', 'woo-pakettikauppa'), 'type' => 'enchancedtextarea', 'description' => '', 'available_params' => array('ORDER_NUMBER' => __('Order number', 'woo-pakettikauppa'), 'ORDER_NOTE' => __('The note is specified in the order', 'woo-pakettikauppa'), 'PRODUCTS_NAMES' => __('Names of the goods in the shipment', 'woo-pakettikauppa'), 'PRODUCTS_NAME_WITH_QUANTITY' => __('Names and quantities of the goods in the shipment', 'woo-pakettikauppa'), 'PRODUCTS_SKU' => __('SKU codes of the goods in the shipment', 'woo-pakettikauppa'), 'PRODUCTS_SKU_WITH_QUANTITY' => __('SKU codes and quantities of the goods in the shipment', 'woo-pakettikauppa'))),
    );
    if ( ! $this->get_core()->order_pickup ) {
      unset($fields['order_pickup'], $fields['order_pickup_customer_id'], $fields['order_pickup_invoice_id']);
    }
    if ( get_option($this->get_core()->prefix . '_wizard_done') == 1 ) {
      $fields['setup_wizard'] = array('title' => __('Setup wizard', 'woo-pakettikauppa'), 'type' => 'button', 'url' => esc_url(admin_url('admin.php?page=' . $this->get_core()->setup_page)), 'text' => __('Restart setup wzard', 'woo-pakettikauppa'));
    }
    return $fields;
  }

  public function process_admin_options() {
    $this->get_core()->shipment->delete_shipping_methods_cache();
    update_option($this->get_core()->prefix . '_wizard_done', 1);
    delete_transient($this->get_core()->prefix . '_access_token');
    return parent::process_admin_options();
  }
}
