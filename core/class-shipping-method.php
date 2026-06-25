<?php
namespace Woo_Pakettikauppa_Core;

// Prevent direct access to the script
use WC_Countries;

if ( ! defined('ABSPATH') ) {
  exit;
}

if ( ! class_exists(__NAMESPACE__ . '\Shipping_Method') ) {
  /**
   * Shipping_Method Class
   *
   * @class Shipping_Method
   * @version  1.0.0
   * @since 1.0.0
   * @package  woo-pakettikauppa
   * @author Seravo
   */
  class Shipping_Method extends \WC_Shipping_Method {
    /**
     * Required to access Pakettikauppa client
     * @var Shipment $shipment
     */
    private $shipment = null;

    public $is_loaded = false;

    /**
     * Constructor for Pakettikauppa shipping class
     *
     * @access public
     * @return void
     */
    public function __construct( $instance_id = 0 ) {
      parent::__construct($instance_id);

      $this->load();
    }

    public function get_core() {
      return \Woo_Posti_Shipping::get_instance();
    }

    public function load() {
      if ( $this->is_loaded ) {
        return;
      }

      $this->id = $this->get_core()->shippingmethod; // ID for your shipping method. Should be unique.
      $this->method_title = $this->get_core()->text->shipping_method_name();
      $this->method_description = $this->get_core()->text->shipping_method_desc(); // Description shown in admin

      $this->supports = array(
        'settings',
      );

      $this->init();

      // Save settings in admin if you have any defined
      add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));

      $this->is_loaded = true;
    }

    /**
     * Initialize Pakettikauppa shipping
     */
    public function init() {
      $this->form_fields          = $this->my_global_form_fields();
      $this->title                = $this->get_option('title');
    }

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

      $values = wp_json_encode($value);
      return $values;
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
                // Once the user picks any option, the previously selected
                // unavailable service is no longer in effect.
                var selectedOption = elem.options[elem.selectedIndex];
                var isUnavailable = !!(selectedOption && selectedOption.classList.contains('pk-option-unavailable'));
                if (!isUnavailable) {
                    card.classList.remove('pk-method-card--unavailable');
                    var warning = card.querySelector('.pk-method-card__warning');
                    if (warning) {
                        warning.style.display = 'none';
                    }
                }

                // Recompute the status dot colour.
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

    /**
     * Resolve the list of carrier services for a given sender country.
     *
     * @param string $sender_country ISO country code of the sender
     *
     * @return array Associative array of service_id => service_name
     */
    public function get_services_for_country( $sender_country ) {
      $shipping_methods_params = $this->get_shipping_methods_params_for_country($sender_country);

      $all_shipping_methods = $this->get_core()->shipment->services($shipping_methods_params);
      if ( empty($all_shipping_methods) ) {
        $all_shipping_methods = array();
      }

      return $all_shipping_methods;
    }

    /**
     * Build the API request params (language + sender country) for a given
     * sender country. FI/AX use Finnish, everything else English.
     *
     * @param string $sender_country ISO country code of the sender
     *
     * @return array
     */
    public function get_shipping_methods_params_for_country( $sender_country ) {
      $services_lang = (in_array($sender_country, array( 'FI', 'AX' ), true) ? 'fi' : 'en');
      $shipping_methods_params = array(
        'language' => $services_lang,
      );
      if ( in_array($sender_country, array( 'FI', 'AX', 'EE', 'LV', 'LT' ), true) ) {
        $shipping_methods_params['sender_country'] = $sender_country;
      }

      return $shipping_methods_params;
    }

    /**
     * Render the shipping methods mapping as visual cards. Shared between the
     * initial settings render and the AJAX reload triggered by sender country change.
     *
     * @param string|null $sender_country Sender country to resolve services for. Defaults to saved value.
     * @param array|null  $values         Saved pickup point mapping. Defaults to saved option value.
     *
     * @return string
     */
    public function render_pickup_points_mapping( $sender_country = null, $values = null ) {
      $field_key = $this->get_field_key('pickup_points');

      if ( $sender_country === null ) {
        $sender_country = $this->get_option('sender_country');
      }

      if ( $values === null ) {
        $values = $this->get_option('pickup_points');
        if ( is_string($values) && $values !== '' ) {
          $values = json_decode($values, true);
        }
      }
      if ( empty($values) || ! is_array($values) ) {
        $values = array();
      }

      $all_shipping_methods = $this->get_services_for_country($sender_country);
      $methods = $this->get_core()->shipment->get_pickup_point_methods();

      $shipping_methods_params = $this->get_shipping_methods_params_for_country($sender_country);
      $all_additional_services = $this->get_core()->shipment->get_additional_services($shipping_methods_params);
      if ( empty($all_additional_services) ) {
        $all_additional_services = array();
      }

      ob_start();
      ?>
      <div class="pk-mapping">
        <?php foreach ( \WC_Shipping_Zones::get_zones('admin') as $zone_raw ) : ?>
          <?php $zone = new \WC_Shipping_Zone($zone_raw['zone_id']); ?>
          <?php
          $zone_methods = array();
          foreach ( $zone->get_shipping_methods() as $method_id => $shipping_method ) {
            if ( $shipping_method->id !== $this->get_core()->shippingmethod && $shipping_method->id !== 'local_pickup' ) {
              $zone_methods[ $method_id ] = $shipping_method;
            }
          }
          ?>
          <?php if ( empty($zone_methods) ) : ?>
            <?php continue; ?>
          <?php endif; ?>
          <section class="pk-zone">
            <header class="pk-zone__header">
              <span class="pk-zone__name"><?php echo esc_html($zone->get_zone_name()); ?></span>
              <span class="pk-zone__regions"><?php echo esc_html($zone->get_formatted_location()); ?></span>
            </header>
            <div class="pk-zone__methods">
              <?php foreach ( $zone_methods as $method_id => $shipping_method ) : ?>
                <?php
                $selected_service = null;
                if ( ! empty($values[ $method_id ]['service']) ) {
                  $selected_service = $values[ $method_id ]['service'];
                }
                if ( empty($selected_service) && ! empty($methods) && isset($values[ $method_id ]) ) {
                  $selected_service = '__PICKUPPOINTS__';
                }
                $service_available = ($selected_service === null
                  || $selected_service === '__NULL__'
                  || $selected_service === '__PICKUPPOINTS__'
                  || isset($all_shipping_methods[ strval($selected_service) ]));
                $has_service_assigned = ($selected_service !== null && $selected_service !== '__NULL__');
                $wc_method_enabled = $shipping_method->is_enabled();
                $is_inactive = ! $has_service_assigned;

                // Determine the status dot colour:
                // - red:   misconfiguration (service assigned but method disabled in WC, or service unavailable)
                // - grey:  no service assigned
                // - green: active and correctly configured
                if ( ($has_service_assigned && ! $wc_method_enabled) || ! $service_available ) {
                  $dot_state = 'error';
                } elseif ( ! $has_service_assigned ) {
                  $dot_state = 'inactive';
                } else {
                  $dot_state = 'active';
                }

                $card_classes = 'pk-method-card';
                if ( $is_inactive ) {
                  $card_classes .= ' pk-method-card--inactive';
                }
                if ( ! $service_available ) {
                  $card_classes .= ' pk-method-card--unavailable';
                }
                if ( ! $wc_method_enabled ) {
                  $card_classes .= ' pk-method-card--wc-disabled';
                }
                ?>
                <div class="<?php echo esc_attr($card_classes); ?>" data-method="<?php echo esc_attr($method_id); ?>" data-wc-enabled="<?php echo $wc_method_enabled ? '1' : '0'; ?>">
                  <div class="pk-method-card__head">
                    <span class="pk-method-card__title">
                      <span class="pk-method-card__dot pk-dot--<?php echo esc_attr($dot_state); ?>"></span>
                      <span class="pk-method-card__name<?php echo ! $wc_method_enabled ? ' pk-strike' : ''; ?>"<?php echo ! $wc_method_enabled ? ' title="' . esc_attr($this->get_core()->text->shipping_method_inactive_in_zones()) . '"' : ''; ?>><?php echo esc_html($shipping_method->title); ?></span>
                    </span>
                    <select id="<?php echo esc_attr($method_id); ?>-select" class="pk-service-select" data-method="<?php echo esc_attr($method_id); ?>" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo esc_attr($method_id); ?>');">
                      <option value="__NULL__"><?php echo $this->get_core()->text->no_shipping(); ?></option>
                      <?php if ( ! empty($methods) ) : ?>
                        <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>><?php esc_html_e('Pickup points', 'woo-pakettikauppa'); ?></option>
                      <?php endif; ?>
                      <?php if ( ! $service_available ) : ?>
                        <option value="<?php echo esc_attr($selected_service); ?>" selected class="pk-option-unavailable" data-haspp="false">
                          <?php echo esc_html($this->get_core()->text->selected_unavailable_service_code($selected_service)); ?>
                        </option>
                      <?php endif; ?>
                      <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                        <?php $has_pp = ($this->get_core()->shipment->service_has_pickup_points($service_id)) ? true : false; ?>
                        <option value="<?php echo esc_attr($service_id); ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?> data-haspp="<?php echo ($has_pp) ? 'true' : 'false'; ?>">
                          <?php echo esc_html($service_name); ?>
                          <?php if ( $has_pp ) : ?>
                            (<?php echo $this->get_core()->text->includes_pickup_points(); ?>)
                          <?php endif; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ( ! $service_available ) : ?>
                      <p class="pk-method-card__warning"><?php echo esc_html($this->get_core()->text->service_not_available_for_country()); ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="pk-method-card__body">
                    <div style='display: none;' id="pickuppoints-<?php echo esc_attr($method_id); ?>">
                      <?php foreach ( $methods as $method_code => $method_name ) : ?>
                        <input type="hidden"
                                name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                                value="no">
                        <p>
                          <label>
                            <input type="checkbox"
                                  name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>"
                                  value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['active']) && $values[ $method_id ][ $method_code ]['active'] === 'yes') ? 'checked' : ''; ?>>
                            <?php echo esc_html($method_name); ?>
                          </label>
                        </p>
                      <?php endforeach; ?>
                    </div>

                    <?php foreach ( $all_additional_services as $method_code => $additional_services ) : ?>
                      <div class="pk-services-<?php echo esc_attr($method_id); ?> pk-service-options" style='display: none;' id="services-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($method_code); ?>">
                        <?php foreach ( $additional_services as $additional_service ) : ?>
                          <?php if ( empty($additional_service->specifiers) || in_array($additional_service->service_code, array( '3102' ), true) ) : ?>
                            <input type="hidden"
                                    name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                    value="no">
                            <p>
                              <label>
                                <input type="checkbox"
                                      name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>"
                                      value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ]) && $values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ] === 'yes') ? 'checked' : ''; ?>>
                                <?php echo esc_html($additional_service->name); ?>
                              </label>
                            </p>
                          <?php endif; ?>
                        <?php endforeach; ?>
                        <input type="hidden"
                          name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>"
                          value="no">
                        <p>
                          <label>
                            <input type="checkbox"
                                  name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>"
                                  value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services']['return_label']) && $values[ $method_id ][ $method_code ]['additional_services']['return_label'] === 'yes') ? 'checked' : ''; ?>>
                            <?php echo esc_html__('Include return label (if available)', 'woo-pakettikauppa'); ?>
                          </label>
                        </p>
                      </div>
                    <?php endforeach; ?>
                    <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                      <?php if ( $this->get_core()->shipment->service_has_pickup_points($service_id) ) : ?>
                        <div id="service-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($service_id); ?>-pickuppoints" class="pk-services-<?php echo esc_attr($method_id); ?> pk-service-options" style="display: none;">
                          <input type="hidden"
                            name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="no">
                          <p>
                            <label>
                              <input type="checkbox"
                                name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>"
                                value="yes" <?php echo ((! empty($values[ $method_id ][ $service_id ]['pickuppoints']) && $values[ $method_id ][ $service_id ]['pickuppoints'] === 'yes') || empty($values[ $method_id ][ $service_id ]['pickuppoints'])) ? 'checked' : ''; ?>>
                              <?php echo esc_html__('Pickup points', 'woo-pakettikauppa'); ?>
                            </label>
                          </p>
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
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
        <th scope="row" class="titledesc">
          <label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label>
        </th>
        <td class="forminp">
          <fieldset>
            <legend class="screen-reader-text"><span><?php echo esc_html($value['title']); ?></span></legend>
            <textarea rows="3" cols="20" class="input-text wide-input " type="textarea" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" style="" placeholder=""><?php echo esc_html($field_value); ?></textarea>
            <?php if ( ! empty($value['available_params']) && is_array($value['available_params']) ) : ?>
                <?php foreach ( $value['available_params'] as $param_key => $param_desc ) : ?>
                  <p class="description enchtext noselect">
                    <code class="enchtext-code" data-param="<?php echo esc_html($param_key); ?>" onclick="click_enchancedtextarea_code('<?php echo $field_key; ?>', '<?php echo esc_html($param_key); ?>');">{<?php echo esc_html($param_key); ?>}</code> - <?php echo esc_html($param_desc); ?>
                  </p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ( ! empty($value['description']) ) : ?>
              <p class="description"><?php echo $value['description']; ?></p>
            <?php endif; ?>
          </fieldset>
        </td>
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
        <th scope="row" class="titledesc">
          <label for="<?php echo $field_key; ?>"><?php echo esc_html($value['title']); ?></label>
        </th>
        <td class="forminp">
          <fieldset>
            <a class="button button-primary" href="<?php echo $value['url']; ?>">
              <?php echo $value['text']; ?>
            </a>
          </fieldset>
        </td>
      </tr>
      <?php
      $html = ob_get_contents();
      ob_end_clean();
      return $html;
    }

    public function generate_hidden_html( $key, $args )
    {
      $field_key = $this->get_field_key($key);

      return '<input type="hidden" name="' . esc_html($field_key) . '" value="' . esc_attr($args['default']) . '" />';
    }

    private function my_global_form_fields() {
      if ( ! class_exists(__NAMESPACE__ . '\Shipment') ) {
        require_once 'class-shipment.php';
      }

      $wc_countries = new WC_Countries();

      $fields = array(
        'notices'    => array(
          'type'     => 'notices',
        ),

        array(
          'title' => '',
          'type'  => 'title',
          'class' => 'hidden',
        ),

        'account_number'             => array(
          'title'    => $this->get_core()->text->api_key_title(),
          'desc'     => $this->get_core()->text->api_key_desc($this->get_core()->vendor_name),
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'secret_key'                 => array(
          'title'    => $this->get_core()->text->api_secret_title(),
          'desc'     => $this->get_core()->text->api_secret_desc(),
          'type'     => 'password',
          'default'  => '',
          'desc_tip' => true,
        ),

        array(
          'title' => $this->get_core()->text->store_owner_information(),
          'type'  => 'title',
        ),

        'sender_name'                => array(
          'title'   => $this->get_core()->text->sender_name(),
          'type'    => 'text',
          'default' => get_bloginfo('name'),
        ),

        'sender_address'             => array(
          'title'   => $this->get_core()->text->sender_address(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_address(),
        ),

        'sender_postal_code'         => array(
          'title'   => $this->get_core()->text->sender_postal_code(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_postcode(),
        ),

        'sender_city'                => array(
          'title'   => $this->get_core()->text->sender_city(),
          'type'    => 'text',
          'default' => WC()->countries->get_base_city(),
        ),

        'sender_country'                => array(
          'title'   => $this->get_core()->text->sender_country(),
          'type'    => 'select',
          'default' => WC()->countries->get_base_country(),
          'options'   => $wc_countries->get_countries(),
        ),

        'sender_phone'                => array(
          'title'   => $this->get_core()->text->sender_phone(),
          'type'    => 'text',
        ),

        'sender_email'                => array(
          'title'   => $this->get_core()->text->sender_email(),
          'type'    => 'email',
        ),

        'info_code'                  => array(
          'title'   => $this->get_core()->text->info_code(),
          'type'    => 'text',
          'default' => '',
          'description' => $this->get_core()->text->info_code_desc(),
          'custom_attributes' => array(
            'maxlength' => 15,
          ),          
        ),

        'order_pickup'              => array(
          'title' => $this->get_core()->text->order_pickup_title(),
          'type'  => 'title',
        ),

        'order_pickup_customer_id'                 => array(
          'title'    => $this->get_core()->text->customer_id_title(),
          'desc'     => '',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'order_pickup_invoice_id'                 => array(
          'title'    => $this->get_core()->text->invoice_id_title(),
          'desc'     => '',
          'type'     => 'text',
          'default'  => '',
          'desc_tip' => true,
        ),

        'pickup_points'              => array(
          'title' => $this->get_core()->text->pickup_points_title(),
          'type'  => 'pickuppoints',
        ),

        array(
          'title'       => $this->get_core()->text->shipping_settings_title(),
          'type'        => 'title',
          /* translators: %s: url to documentation */
          'description' => $this->get_core()->text->shipping_settings_desc(),
        ),

        'add_tracking_to_email'      => array(
          'title'   => $this->get_core()->text->add_tracking_link_to_email(),
          'type'    => 'checkbox',
          'default' => 'yes',
          'class'   => 'mode_react',
        ),

        'add_pickup_point_to_email'      => array(
          'title'   => $this->get_core()->text->add_pickup_point_to_email(),
          'type'    => 'checkbox',
          'default' => 'yes',
          'class'   => 'mode_react',
        ),

        'ignore_product_weight'      => array(
          'title'   => $this->get_core()->text->ignore_product_weight(),
          'type'    => 'checkbox',
          'default' => 'no',
          'class'   => 'mode_react',
        ),

        'exclude_prods_without_hs'    => array(
          'title'   => $this->get_core()->text->exclude_prods_without_hs(),
          'type'    => 'checkbox',
          'default' => 'no',
          'class'   => 'mode_react',
        ),

        'express_freight_pallet_type'      => array(
          'title'   => $this->get_core()->text->express_freight_default_pallet_type(),
          'type'    => 'select',
          'default' => 'CC',
          'options' => Shipment::get_express_freight_pallet_types(),
          'class'   => 'mode_react',
        ),

        'change_order_status_to'      => array(
          'title'   => $this->get_core()->text->change_order_status_to(),
          'type'    => 'select',
          'default' => '',
          'options' => array(
            '' => $this->get_core()->text->no_order_status_change(),
            'completed'  => __('Completed', 'woocommerce'),
            'processing' => __('Processing', 'woocommerce'),
          ),
          'class'   => 'mode_react',
        ),

        'translate_products_in_labels'      => array(
          'title'   => $this->get_core()->text->translate_products_in_labels_title(),
          'type'    => 'checkbox',
          'default' => 'no',
          'description' => $this->get_core()->text->translate_products_in_labels_desc(),
          'desc_tip'    => true,
          'class'   => 'mode_react',
        ),

        'create_shipments_automatically'     => array(
          'title'   => $this->get_core()->text->create_shipments_automatically(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => $this->get_core()->text->no_automatic_creation_of_labels(),
            /* translators: %s: order status */
            'completed'  => $this->get_core()->text->when_order_status_is(__('Completed', 'woocommerce')),
            /* translators: %s: order status */
            'processing' => $this->get_core()->text->when_order_status_is(__('Processing', 'woocommerce')),
          ),
          'class'   => 'mode_react',
        ),

        'labels_size'     => array(
          'title'   => $this->get_core()->text->labels_size_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'A5'  => 'A5',
            '107x225'  => '107x225',
          ),
          'class'   => 'mode_react',
        ),

        'download_type_of_labels'     => array(
          'title'   => $this->get_core()->text->download_type_of_labels_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'browser'  => $this->get_core()->text->download_type_of_labels_option_browser(),
            'download'  => $this->get_core()->text->download_type_of_labels_option_download(),
          ),
          'class'   => 'mode_react',
        ),

        'post_label_to_url' => array(
          'title'   => $this->get_core()->text->post_shipping_label_to_url_title(),
          'type'    => 'text',
          'default' => '',
          'description' => $this->get_core()->text->post_shipping_label_to_url_desc(),
          'desc_tip'    => true,
          'class'   => 'mode_react',
        ),

        array(
          'title' => $this->get_core()->text->checkout_settings(),
          'type'  => 'title',
        ),

        'field_phone_required' => array(
          'title'   => $this->get_core()->text->field_phone_required(),
          'type'    => 'select',
          'default' => 'no',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
        ),

        'pickup_points_type' => array(
          'title' => $this->get_core()->text->pickup_points_type_title(),
          'type' => 'multiselect',
          'options' => array(
            'all' => $this->get_core()->text->pickup_points_type_all(),
            'PRIVATE_LOCKER' => $this->get_core()->text->pickup_points_type_private_locker(),
            'OUTDOOR_LOCKER' => $this->get_core()->text->pickup_points_type_outdoor_locker(),
            'PARCEL_LOCKER' => $this->get_core()->text->pickup_points_type_parcel_locker(),
            'PICKUP_POINT,AGENCY' => $this->get_core()->text->pickup_points_type_pickup_point(),
          ),
          'default' => 'all',
          'description' => $this->get_core()->text->pickup_points_type_desc(),
          'desc_tip'    => true,
        ),

        'pickup_points_search_limit' => array(
          'title'       => $this->get_core()->text->pickup_points_search_limit_title(),
          'type'        => 'number',
          'default'     => 5,
          'description' => $this->get_core()->text->pickup_points_search_limit_desc(),
          'desc_tip'    => true,
          'class'   => 'mode_react',
        ),

        'pickup_point_list_type'     => array(
          'title'   => $this->get_core()->text->pickup_point_list_type_title(),
          'type'    => 'select',
          'default' => 'menu',
          'options' => array(
            'menu'  => $this->get_core()->text->pickup_point_list_type_option_menu(),
            'list'  => $this->get_core()->text->pickup_point_list_type_option_list(),
          ),
          'class'   => 'mode_react',
        ),
        'show_pickup_point_override_query' => array(
          'title'   => $this->get_core()->text->show_pickup_point_override_query(),
          'type'    => 'select',
          'default' => 'yes',
          'options' => array(
            'no'  => __('No'),
            'yes'  => __('Yes'),
          ),
          'description' => $this->get_core()->text->pickup_points_override_query_desc(),
          'desc_tip'    => true,
        ),

        'cod_title' => array(
          'title' => $this->get_core()->text->cod_settings(),
          'type'  => 'title',
        ),
        'cod_iban'                   => array(
          'title'   => $this->get_core()->text->cod_iban(),
          'type'    => 'text',
          'default' => '',
        ),
        'cod_bic'                    => array(
          'title'   => $this->get_core()->text->cod_bic(),
          'type'    => 'text',
          'default' => '',
        ),
        array(
          'title' => $this->get_core()->text->advanced_settings(),
          'type'  => 'title',
        ),
        'label_additional_info' => array(
          'title'   => $this->get_core()->text->additional_info_param_title(),
          'type'    => 'enchancedtextarea',
          'description' => '',
          'available_params' => array(
            'ORDER_NUMBER' => $this->get_core()->text->additional_info_param_order_number(),
            'ORDER_NOTE' => $this->get_core()->text->additional_info_param_order_note(),
            'PRODUCTS_NAMES' => $this->get_core()->text->additional_info_param_products_names(),
            'PRODUCTS_NAME_WITH_QUANTITY' => $this->get_core()->text->additional_info_param_products_names_with_qty(),
            'PRODUCTS_SKU' => $this->get_core()->text->additional_info_param_products_sku(),
            'PRODUCTS_SKU_WITH_QUANTITY' => $this->get_core()->text->additional_info_param_products_sku_with_qty(),
          ),
        ),
      );
      //unset order pickup settings if feature is disabled
      if ( ! $this->get_core()->order_pickup ) {
          unset($fields['order_pickup']);
          unset($fields['order_pickup_customer_id']);
          unset($fields['order_pickup_invoice_id']);
      }
      if ( get_option($this->get_core()->prefix . '_wizard_done') == 1 ) {
        $fields['setup_wizard'] = array(
          'title'   => $this->get_core()->text->setup_wizard(),
          'type'    => 'button',
          'url'     => esc_url(admin_url('admin.php?page=' . $this->get_core()->setup_page)),
          'text'    => $this->get_core()->text->restart_setup_wizard(),
        );
      }
      return $fields;
    }

    public function process_admin_options() {
      $this->get_core()->shipment->delete_shipping_methods_cache();
      update_option($this->get_core()->prefix . '_wizard_done', 1);
      //delete token on update, in case settings changed
      delete_transient($this->get_core()->prefix . '_access_token');
      return parent::process_admin_options();
    }
  }
}
