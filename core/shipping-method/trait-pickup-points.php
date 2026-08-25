<?php
namespace Woo_Posti_Core;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Shipping_Method_Pickup_Points_Trait {
  public function get_services_for_country( $sender_country ) {
    $shipping_methods_params = $this->get_shipping_methods_params_for_country($sender_country);
    $all_shipping_methods = $this->get_core()->shipment->services($shipping_methods_params);
    if ( empty($all_shipping_methods) ) {
      $all_shipping_methods = array();
    }

    return $all_shipping_methods;
  }

  public function get_shipping_methods_params_for_country( $sender_country ) {
    $services_lang = $this->get_core()->shipment->get_services_language();
    $shipping_methods_params = array(
      'language' => $services_lang,
    );
    if ( in_array($sender_country, array( 'FI', 'AX', 'EE', 'LV', 'LT' ), true) ) {
      $shipping_methods_params['sender_country'] = $sender_country;
    }

    return $shipping_methods_params;
  }

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
                    <span class="pk-method-card__name<?php echo ! $wc_method_enabled ? ' pk-strike' : ''; ?>"<?php echo ! $wc_method_enabled ? ' title="' . esc_attr__('This shipping method is inactive in WooCommerce shipping zones', 'woo-pakettikauppa') . '"' : ''; ?>><?php echo esc_html($shipping_method->title); ?></span>
                  </span>
                  <select id="<?php echo esc_attr($method_id); ?>-select" class="pk-service-select" data-method="<?php echo esc_attr($method_id); ?>" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][service]'; ?>" onchange="pkChangeOptions(this, '<?php echo esc_attr($method_id); ?>');">
                    <option value="__NULL__"><?php echo esc_html__('No shipping', 'woo-pakettikauppa'); ?></option>
                    <?php if ( ! empty($methods) ) : ?>
                      <option value="__PICKUPPOINTS__" <?php echo ($selected_service === '__PICKUPPOINTS__' ? 'selected' : ''); ?>><?php esc_html_e('Pickup points', 'woo-pakettikauppa'); ?></option>
                    <?php endif; ?>
                    <?php if ( ! $service_available ) : ?>
                      <option value="<?php echo esc_attr($selected_service); ?>" selected class="pk-option-unavailable" data-haspp="false">
                        <?php
                          /* translators: %s: service code */
                          echo esc_html(sprintf(esc_html__('Selected unavailable service code: %s', 'woo-pakettikauppa'), $selected_service));
                        ?>
                      </option>
                    <?php endif; ?>
                    <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                      <?php $has_pp = ($this->get_core()->shipment->service_has_pickup_points($service_id)) ? true : false; ?>
                      <option value="<?php echo esc_attr($service_id); ?>" <?php echo (strval($selected_service) === strval($service_id) ? 'selected' : ''); ?> data-haspp="<?php echo ($has_pp) ? 'true' : 'false'; ?>">
                        <?php echo esc_html($service_name); ?>
                        <?php if ( $has_pp ) : ?>
                          (<?php echo esc_html__('includes pickup points', 'woo-pakettikauppa'); ?>)
                        <?php endif; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ( ! $service_available ) : ?>
                    <p class="pk-method-card__warning"><?php echo esc_html__('Service not available in sender\'s country', 'woo-pakettikauppa'); ?></p>
                  <?php endif; ?>
                </div>
                <div class="pk-method-card__body">
                  <div style='display: none;' id="pickuppoints-<?php echo esc_attr($method_id); ?>">
                    <?php foreach ( $methods as $method_code => $method_name ) : ?>
                      <input type="hidden" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>" value="no">
                      <p><label><input type="checkbox" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . $method_code . '][active]'; ?>" value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['active']) && $values[ $method_id ][ $method_code ]['active'] === 'yes') ? 'checked' : ''; ?>><?php echo esc_html($method_name); ?></label></p>
                    <?php endforeach; ?>
                  </div>

                  <?php foreach ( $all_additional_services as $method_code => $additional_services ) : ?>
                    <div class="pk-services-<?php echo esc_attr($method_id); ?> pk-service-options" style='display: none;' id="services-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($method_code); ?>">
                      <?php foreach ( $additional_services as $additional_service ) : ?>
                        <?php if ( empty($additional_service->specifiers) || in_array($additional_service->service_code, array( '3102' ), true) ) : ?>
                          <input type="hidden" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>" value="no">
                          <p><label><input type="checkbox" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][' . $additional_service->service_code . ']'; ?>" value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ]) && $values[ $method_id ][ $method_code ]['additional_services'][ $additional_service->service_code ] === 'yes') ? 'checked' : ''; ?>><?php echo esc_html($additional_service->name); ?></label></p>
                        <?php endif; ?>
                      <?php endforeach; ?>
                      <input type="hidden" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>" value="no">
                      <p><label><input type="checkbox" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($method_code) . '][additional_services][return_label]'; ?>" value="yes" <?php echo (! empty($values[ $method_id ][ $method_code ]['additional_services']['return_label']) && $values[ $method_id ][ $method_code ]['additional_services']['return_label'] === 'yes') ? 'checked' : ''; ?>><?php echo esc_html__('Include return label (if available)', 'woo-pakettikauppa'); ?></label></p>
                    </div>
                  <?php endforeach; ?>
                  <?php foreach ( $all_shipping_methods as $service_id => $service_name ) : ?>
                    <?php if ( $this->get_core()->shipment->service_has_pickup_points($service_id) ) : ?>
                      <div id="service-<?php echo esc_attr($method_id); ?>-<?php echo esc_attr($service_id); ?>-pickuppoints" class="pk-services-<?php echo esc_attr($method_id); ?> pk-service-options" style="display: none;">
                        <input type="hidden" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="no">
                        <p><label><input type="checkbox" name="<?php echo esc_html($field_key) . '[' . esc_attr($method_id) . '][' . esc_attr($service_id) . '][pickuppoints]'; ?>" value="yes" <?php echo ((! empty($values[ $method_id ][ $service_id ]['pickuppoints']) && $values[ $method_id ][ $service_id ]['pickuppoints'] === 'yes') || empty($values[ $method_id ][ $service_id ]['pickuppoints'])) ? 'checked' : ''; ?>><?php echo esc_html__('Pickup points', 'woo-pakettikauppa'); ?></label></p>
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
}
