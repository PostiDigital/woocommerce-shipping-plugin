<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * Order edit page meta box: templates and rendering, including bulk custom shipment table.
 */
trait Admin_Metabox_Trait {

  /**
   * Show the selected pickup point in admin order meta. Use together with the hook
   * woocommerce_admin_order_data_after_shipping_address.
   *
   * @param WC_Order $order The order that is currently being viewed in wp-admin
   */
  public function show_pickup_point_in_admin_order_meta( $order ) {
    $service_id = $order->get_meta('_' . $this->core->prefix . '_custom_service_id', true);
    $default_service_id = $this->shipment->get_service_id_from_order($order, false);
    if ( empty($service_id) && empty($default_service_id) ) {
      return;
    }

    $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, 'kg');

    $services_data = array(
      'lqweight' => array(
        'title' => esc_attr__('Total weight of dangerous goods', 'woo-pakettikauppa'),
        'value' => (! empty($dangerous_goods['weight'])) ? $dangerous_goods['weight'] : 0,
        'unit' => 'kg',
        'show_always' => false,
      ),
    );
    ?>
    <div style="clear: both;"></div>
    <h4>
      <?php
      /* translators: %s: Vendor name */
      printf(esc_attr__('%s Shipping', 'woo-pakettikauppa'), $this->core->vendor_name);
      ?>
    </h4>
    <div class="address pakettikauppa">
      <p class="form-field pakettikauppa-field">
        <strong><?php esc_attr_e('Requested pickup point', 'woo-pakettikauppa'); ?></strong>
        <?php
        if ( $order->get_meta('_' . $this->core->params_prefix . 'pickup_point') ) {
          echo esc_attr($order->get_meta('_' . $this->core->params_prefix . 'pickup_point'));
        } else {
          echo esc_attr__('None');
        }
        ?>
        <br>
        <?php foreach ( $services_data as $service_key => $service_params ) : ?>
          <?php if ( ! empty($service_params['value']) || $service_params['show_always'] === true ) : ?>
            <strong><?php echo $service_params['title']; ?></strong>
            <?php
            if ( empty($service_params['unit']) ) {
              $service_params['unit'] = '';
            }
            $nr_dec = '.'; //Number decimals char
            $nr_tsd = ' '; //Number thousands char
            switch ( $service_params['unit'] ) {
              case 'kg':
                $value_text = number_format($service_params['value'], 3, $nr_dec, $nr_tsd) . ' kg';
                break;
              case 'g':
                $value_text = number_format($service_params['value'], false, $nr_dec, $nr_tsd) . ' g';
                break;
              default:
                $value_text = $service_params['value'];
            }
            echo $value_text;
            ?>
            <br>
          <?php endif; ?>
        <?php endforeach; ?>
        <br>
        <?php echo __('Phone', 'woocommerce') . ': ' . $order->get_shipping_phone(); ?>
        <br>
        <?php echo __('Email', 'woocommerce') . ': ' . $order->get_meta('_shipping_email', true); ?>
      </p>
    </div>
    <div class="edit_address pakettikauppa">
      <p class="form-field pakettikauppa-field">
        <strong><?php esc_attr_e('Requested pickup point', 'woo-pakettikauppa'); ?></strong>
        <?php
        if ( $order->get_meta('_' . $this->core->params_prefix . 'pickup_point') ) {
          echo esc_attr($order->get_meta('_' . $this->core->params_prefix . 'pickup_point'));
        } else {
          echo esc_attr__('None');
        }
        ?>
      </p>
      <?php $field_key = $this->core->params_prefix . 'shipping_email'; ?>
      <p class="form-field <?php echo $field_key; ?>">
        <label for="<?php echo $field_key; ?>"><?php esc_attr_e('Email', 'woo-pakettikauppa'); ?></label>
        <input type="email" class="short" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" value="<?php echo esc_attr($order->get_meta('_shipping_email')); ?>">
      </p>
    </div>
    <div class="clear"></div>
    <?php
  }

  /**
   * Template for metabox section title which is little bigger then other titles.
   *
   * @param string $title Section title
   */
  private function tpl_section_title( $title ) {
    ?>
    <div class="pakettikauppa-section-title">
      <h3><?php echo $title; ?></h3>
    </div>
    <?php
  }

  /**
   * Template for metabox section end.
   */
  private function tpl_section_end() {
    ?>
    <hr>
    <?php
  }

  /**
   * Template for shipping label in Order metabox.
   *
   * @param array $label Label information
   * @param int $post_id Order id
   * @param array $all_additional_services Additional services available for all methods, keyed by
   *                                        service_id, used to re-resolve names in the current admin
   *                                        user's language instead of the names stored at label creation time.
   */
  private function tpl_shipping_label( $label, $post_id, $all_additional_services = array() ) {
    ?>
    <?php if ( ! empty($label['tracking_code']) ) : ?>
      <?php $order = wc_get_order($post_id); ?>
      <div class="pakettikauppa-shiplabel pakettikauppa-design-foldedcorner">
        <div class="corner">
          <div class="corner-triangle"></div>
        </div>
        <p>
          <span class="label_time"><?php echo (isset($label['timestamp'])) ? esc_attr(gmdate('Y-m-d H:i:s', $label['timestamp'])) : ''; ?></span>
          <strong><?php echo esc_attr($label['tracking_code']); ?></strong><br />
          <span><?php echo esc_attr($this->shipment->service_title($label['service_id'])); ?></span><br />
          <br />
          <?php $manifest_id = $order->get_meta($this->core->prefix . '_manifest', true); ?>
          <?php if ( $manifest_id ) : ?>
            <strong><?php echo __('Pickup order', 'woo-pakettikauppa'); ?>:</strong> <span>#<?php echo $manifest_id; ?></span><br />
            <?php
              $manifest = get_post($manifest_id);
              $pickup_order_status = ($manifest) ? get_post_status_object(get_post_status($manifest))->label : __('Unknown status', 'woo-pakettikauppa');
            ?>
            <strong><?php echo __('Pickup order status', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $pickup_order_status; ?></span><br />
          <?php endif; ?>
          <strong><?php echo __('Status', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo esc_attr(Shipment::get_status_text($label['shipment_status'])); ?></span><br />
          <?php if ( ! empty($label['label_code']) ) : ?>
            <strong><?php echo __('Label code', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['label_code']; ?></span><br />
          <?php endif; ?>
          <?php if ( ! empty($label['pickup_id']) ) : ?>
            <strong><?php echo __('Pickup point', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['pickup_name']; ?></span><br />
          <?php endif; ?>
          <?php if ( ! empty($label['additional_services']) ) : ?>
            <?php
            $services = '';
            $exclude = array( '2106', '3102' );
            $current_service_names = array();
            if ( ! empty($all_additional_services[ $label['service_id'] ]) ) {
              foreach ( $all_additional_services[ $label['service_id'] ] as $serv_obj ) {
                $current_service_names[ (string) $serv_obj->service_code ] = $serv_obj->name;
              }
            }
            foreach ( $label['additional_services'] as $serv_key => $serv_content ) {
              if ( ! in_array($serv_key, $exclude) && isset($serv_content['name']) ) {
                if ( ! empty($services) ) {
                  $services .= ', ';
                }
                // Re-resolve the name in the admin's current language when possible,
                // falling back to the name stored when the label was created.
                $services .= isset($current_service_names[ (string) $serv_key ]) ? $current_service_names[ (string) $serv_key ] : $serv_content['name'];
              }
            }
            ?>
            <?php if ( ! empty($services) ) : ?>
              <strong><?php echo __('Services', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $services; ?></span><br />
            <?php endif; ?>
          <?php endif; ?>
          <?php if ( ! empty($label['products']) ) : ?>
            <?php
            $products = '';
            foreach ( $label['products'] as $prod ) {
              $product = wc_get_product($prod['prod']);
              $product_name = ($product) ? $product->get_title() : __('Unknown product', 'woo-pakettikauppa') . ' (ID: ' . $prod['prod'] . ')';
              $products .= '<br/>' . $prod['qty'] . ' x ' . $product_name;
            }
            ?>
            <strong><?php echo __('Products', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $products; ?></span><br />
          <?php endif; ?>
          <br />
          <a href="<?php echo esc_url($label['document_url']); ?>" target="_blank" class="download"><?php esc_attr_e('Print', 'woo-pakettikauppa'); ?></a> -
          <?php if ( ! empty($label['tracking_url']) ) : ?>
            <a href="<?php echo esc_url($label['tracking_url']); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a> -
          <?php endif; ?>
          <a href="javascript:void(0)" class="status" name="wc_pakettikauppa[get_status]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Refresh', 'woo-pakettikauppa'); ?></a> -
          <?php if ( $this->core->order_pickup ) : ?>
          <a href="javascript:void(0)" class="manifest" name="wc_pakettikauppa[add_to_manifest]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Add to pickup order', 'woo-pakettikauppa'); ?></a> -
          <?php endif; ?>
          <a href="javascript:void(0)" class="delete" name="wc_pakettikauppa[delete_shipping_label]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Delete', 'woo-pakettikauppa'); ?></a>
        </p>
      </div>
    <?php endif; ?>
    <?php
  }

  /**
   * Template for return label in Order metabox.
   *
   * @param array $label Label information
   */
  private function tpl_return_label( $label ) {
    ?>
    <div class="pakettikauppa-returnlabel pakettikauppa-design-foldedcorner">
      <div class="corner">
        <div class="corner-triangle"></div>
      </div>
      <p>
        <span class="label_time"><?php echo (isset($label['timestamp'])) ? esc_attr(gmdate('Y-m-d H:i:s', $label['timestamp'])) : ''; ?></span>
        <strong><?php echo esc_attr($label['tracking_code']); ?></strong><br />
        <span><?php echo esc_attr($this->shipment->service_title($label['service_id'])); ?></span><br />
        <br />
        <strong><?php echo __('Label code', 'woo-pakettikauppa'); ?>:</strong> <span><?php echo $label['label_code']; ?></span><br />
        <br />
        <a href="<?php echo esc_url($label['document_url']); ?>" target="_blank" class="download"><?php esc_attr_e('Print', 'woo-pakettikauppa'); ?></a> -
        <a href="<?php echo esc_url($label['tracking_url']); ?>" target="_blank" class="tracking"><?php esc_attr_e('Track', 'woo-pakettikauppa'); ?></a> -
        <a href="javascript:void(0)" class="delete" name="wc_pakettikauppa[delete_return_label]" data-value="<?php echo esc_attr($label['tracking_code']); ?>" onclick="pakettikauppa_meta_box_submit(this);"><?php echo __('Delete', 'woo-pakettikauppa'); ?></a>
      </p>
    </div>
    <?php
  }

  /**
   * Template for metabox return label buttons, which is using to control all return labels.
   */
  private function tpl_return_label_global_buttons() {
    ?>
    <div class="pakettikauppa-global-labels-buttons">
      <button type="button" value="create_return_label" name="wc_pakettikauppa[create_return_label]" onclick="pakettikauppa_meta_box_submit(this);" class="button pakettikauppa_meta_box"><?php echo __('Create Return Label', 'woo-pakettikauppa'); ?></button>
    </div>
    <?php
  }

  /**
   * Template for Order metabox products selection field.
   *
   * @param WC_Order $order The order that is currently being viewed in wp-admin
   */
  private function tpl_products_selector( $order ) {
    ?>
    <div class="pakettikauppa-metabox-products">
      <?php
      $items = $order->get_items();
      $order_items = array();
      foreach ( $items as $item ) {
        $item_data = $item->get_data();
        $item_prod_id = ! empty($item_data['variation_id']) ? $item_data['variation_id'] : $item_data['product_id'];
        array_push(
          $order_items,
          array(
            'id' => $item_prod_id,
            'name' => $item_data['name'],
            'max' => $item_data['quantity'],
            'lqweight' => $this->core->product->get_product_dg_weight($item_data['product_id'], 'kg'),
          )
        );
      }
      ?>
      <h4><?php echo __('Create for products', 'woo-pakettikauppa'); ?></h4>
      <div class="prod_select_dropdown">
        <textarea id="prod_select_droptxt" class="list" readonly>-</textarea>
        <div id="prod_select_content" class="content">
          <?php foreach ( $order_items as $item ) : ?>
            <div class="list list_item">
              <label for="prod_<?php echo $item['id']; ?>" class="list item_label">
                <input type="checkbox" id="prod_<?php echo $item['id']; ?>" class="list item_cb" value="<?php echo $item['id']; ?>" data-name="<?php echo $item['name']; ?>" data-lqweight="<?php echo $item['lqweight']; ?>" />
                <span><?php echo $item['name']; ?> </span>
                <input type="hidden" class="list quantity" min="1" max="<?php echo $item['max']; ?>" value="<?php echo $item['max']; ?>" />
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <script type="text/javascript">
        if (typeof init_prod_select !== "undefined") {
          init_prod_select();
        } else {
          document.addEventListener('DOMContentLoaded', function() {
            init_prod_select();
          }, false);
        }
      </script>
    </div>
    <?php
  }

  private function tpl_additional_params( $order )
  {
    $settings = $this->shipment->get_settings();
    ?>
    <div class="pakettikauppa-metabox-additional-params">
      <div>
        <?php $checked = (isset($settings['ignore_product_weight']) && $settings['ignore_product_weight'] == 'yes') ? 'checked' : ''; ?>
        <input type="checkbox" id="pk_additional_param_ignore_weight" class="pakettikauppa_metabox_array_values" name="wc_pakettikauppa_additional_params" value="ignore_weight" <?php echo $checked; ?>/>
        <label for="pk_additional_order_param_ignore_weight"><?php _e('Ignore product weight information', 'woo-pakettikauppa'); ?></label>
      </div>
    </div>
    <?php
  }

  /**
   * Meta box for managing shipments.
   *
   * @param $post
   */
  public function meta_box( $post ) {
    $order = Wc_Hpos::get_order_from_object($post);

    if ( $order === null ) {
      return;
    }

    if ( ! Shipment::validate_order_shipping_receiver($order) ) {
      esc_attr_e('Please add shipping info to the order to manage shipments.', 'woo-pakettikauppa');

      return;
    }

    $labels = $this->shipment->get_labels($order->get_id());
    $service_id = null;

    foreach ( $labels as $key => $label ) {
      if ( empty($label['tracking_url']) ) {
        $labels[$key]['tracking_url'] = Shipment::tracking_url($this->core->tracking_base_url, $label['tracking_code']);
      }
      if ( empty($label['service_id']) ) {
        $labels[$key]['service_id'] = $this->shipment->get_service_id_from_order($order, false);
      }
      if ( empty($service_id) ) {
        $service_id = $labels[$key]['service_id'];
      }
      $labels[$key]['document_url'] = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $label['tracking_code']);
    }

    $default_service_id = $this->shipment->get_service_id_from_order($order, false);
    if ( empty($service_id) ) {
      $service_id = $default_service_id;
    }

    $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');

    $default_additional_services = array();
    foreach ( $this->shipment->get_additional_services_from_order($order) as $_additional_service ) {
      $default_additional_services[] = key($_additional_service);
    }

    $return_shipments = $order->get_meta('_' . $this->core->prefix . '_return_shipment');

    $all_shipment_services = $this->shipment->services();

    $all_additional_services = $this->shipment->get_additional_services();

    if ( empty($all_additional_services) ) {
      $all_additional_services = array();
    }
    $all_shipment_additional_services = array();
    if ( ! empty($all_additional_services) && ! empty($service_id) ) {
      $all_shipment_additional_services = $all_additional_services[$service_id];
    }

    if ( ! empty($all_shipment_additional_services) ) {
      foreach ( $all_shipment_additional_services as $additional_service ) {
        $additional_service_names[(string) $additional_service->service_code] = $additional_service->name;
      }
    }

    $order_postcode = $order->get_shipping_postcode();
    $order_address  = $order->get_shipping_address_1();
    $order_city = $order->get_shipping_city();
    $order_country  = $order->get_shipping_country();

    $weight_unit = 'kg';
    $dangerous_goods = $this->core->product->calc_order_dangerous_goods($order, $weight_unit);

    $is_cod = $order->get_payment_method() === 'cod';
    $show_section = 'main';
    if ( empty($service_id) ) {
      $show_section = 'custom';
    }
    ?>
    <div>
      <?php if ( $show_section === 'custom' ) : ?>
        <div class="pakettikauppa-notice notice-error">
          <p>
            <?php _e('No shipping method configured! Configure shipping method from settings.', 'woo-pakettikauppa'); ?>
          </p>
        </div>
      <?php endif; ?>
      <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-meta-box'); ?>" id="pakettikauppa_metabox_nonce" />
      <input type="hidden" name="pakettikauppa_order_id" value="<?php echo esc_html($order->get_id()); ?>" id="pakettikauppa_metabox_order_id" />
      <?php
      if ( empty($service_id) ) {
        $this->tpl_section_title(__('Send order', 'woo-pakettikauppa'));
      }
      if ( ! empty($labels) ) {
        $this->tpl_section_title(__('Shipping labels', 'woo-pakettikauppa'));
        foreach ( $labels as $label ) {
          $this->tpl_shipping_label($label, $order->get_id(), $all_additional_services);
        }
      }
      if ( (! empty($labels) || ! empty($return_shipments)) && ! empty($service_id) ) {
        $this->tpl_section_title(__('Return labels', 'woo-pakettikauppa'));
        if ( ! empty($return_shipments) ) {
          foreach ( $return_shipments as $return_label ) {
            $this->tpl_return_label($return_label);
          }
        }
        if ( ! empty($labels) && ! empty($service_id) ) {
          $this->tpl_return_label_global_buttons();
        }
        $this->tpl_section_end();
      }
      ?>
        <div class="pakettikauppa-services">
          <?php $show_main = ($show_section == 'main') ? '' : 'display:none;'; ?>
          <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_shipping_method" style="<?php echo $show_main; ?>">
            <h4><?php echo esc_html($this->shipment->service_title($default_service_id)); ?></h4>
            <?php if ( ! empty($default_additional_services) ) : ?>
              <h4><?php echo esc_attr__('Additional services', 'woo-pakettikauppa'); ?>:</h4>
              <ol style="list-style: circle;">
                <?php foreach ( $default_additional_services as $i => $additional_service ) : ?>
                  <?php if ( ! in_array($additional_service, array( '3102' ), true) ) : ?>
                    <li>
                      <?php if ( isset($additional_service_names[ $additional_service ]) ) : ?>
                        <?php echo $additional_service_names[ $additional_service ]; ?>
                      <?php else : ?>
                        <?php echo $additional_service; ?>
                      <?php endif; ?>
                      <?php if ( $additional_service == '3143' ) : ?>
                        <span class="service_info">(<span class="changeable_lqweight"><?php echo $dangerous_goods['weight']; ?></span> <?php echo $weight_unit; ?>)</span>
                      <?php endif; ?>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if ( in_array('3102', $default_additional_services, true) ) : ?>
                  <li>
                    <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                    <input type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 3em;" min="1" step="1" max="15">
                  </li>
                <?php endif; ?>
              </ol>
            <?php endif; ?>

            <?php if ( $pickup_point_id ) : ?>
              <?php
              $pickpoint_requested = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point');
              ?>
              <div class="pakettikauppa-pickup-point-requested">
                <h4>
                  <?php echo esc_html__('Requested pickup point', 'woo-pakettikauppa'); ?>
                </h4>
                <p id="pickup-point-requested-txt">
                  <?php echo esc_html($pickpoint_requested); ?>
                </p>
              </div>
            <?php endif; ?>
          </fieldset>

          <?php $show_custom = ($show_section == 'custom') ? '' : 'display:none;'; ?>
          <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_custom_shipping_method" style="<?php echo $show_custom; ?>">
            <?php if ( ! empty($all_shipment_services) ) : ?>
            <select name="wc_pakettikauppa_service_id" id="pakettikauppa-service" class="pakettikauppa_metabox_values" onchange="pakettikauppa_change_shipping_method(this);">
              <option value="__NULL__"><?php esc_html_e('No shipping', 'woo-pakettikauppa'); ?></option>
              <?php foreach ( $all_shipment_services as $_service_code => $_service_title ) : ?>
                <option
                  <?php if ( strval($_service_code) === $service_id ) : ?>
                        selected="selected"
                  <?php endif; ?>
                        value="<?php echo esc_attr($_service_code); ?>">
                  <?php echo esc_html($_service_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php else : ?>
              <span class="pakettikauppa-msg-error"><?php echo __('Service not working. Please check the settings.', 'woo-pakettikauppa'); ?></span>
            <?php endif; ?>

            <?php foreach ( $all_additional_services as $method_code => $_additional_services ) : ?>
              <ol style="list-style: circle; display: none;" class="pk-admin-additional-services" id="pk-admin-additional-services-<?php echo $method_code; ?>">
                <?php $show_3102 = false; ?>
                <?php foreach ( $_additional_services as $additional_service ) : ?>
                  <?php if ( empty($additional_service->specifiers) || $additional_service->service_code === '3101' ) : ?>
                    <?php $elem_id = 'pk_custom_service_' . $method_code . '_' . $additional_service->service_code; ?>
                    <?php $info_text = ''; ?>
                    <?php if ( $additional_service->service_code === '3143' ) : ?>
                      <?php $info_text = '<span class="changeable_lqweight">' . $dangerous_goods['weight'] . '</span> kg'; ?>
                    <?php endif; ?>
                    <li class="service-<?php echo $additional_service->service_code; ?>">
                      <input
                              type="checkbox"
                              id="<?php echo $elem_id; ?>"
                              class="pakettikauppa_metabox_array_values"
                              name="wc_pakettikauppa_additional_services"
                              value="<?php echo $additional_service->service_code; ?>"
                              <?php echo ($additional_service->service_code === '3101' && $is_cod || in_array($additional_service->service_code, $default_additional_services) ? 'checked': ''); ?>
                              />
                      <label for="<?php echo $elem_id; ?>"><?php echo $additional_service->name; ?></label>
                      <?php if ( ! empty($info_text) ) : ?>
                        <span class="service_info">(<?php echo $info_text; ?>)</span>
                      <?php endif; ?>
                    </li>
                  <?php elseif ( $additional_service->service_code === '3102' ) : ?>
                    <?php $show_3102 = true; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if ( $show_3102 ) : ?>
                  <li class="service-3102">
                    <?php echo esc_html__('Parcel count', 'woo-pakettikauppa'); ?>:
                    <input class="pakettikauppa_metabox_values" type="number" name="wc_pakettikauppa_mps_count" value="1" style="width: 4em;" min="1" step="1" max="15">
                  </li>
                <?php endif; ?>
              </ol>
              <?php if ( $this->shipment->service_has_pickup_points($method_code) ) : ?>
                <?php
                $address_override_field_name = $this->core->params_prefix . 'merchant_override_custom_pickup_point_address';
                $custom_address = $order->get_meta($address_override_field_name, true);
                $custom_address = empty($custom_address) ? "$order_address, $order_postcode $order_city, $order_country" : $custom_address;
                if ( $this->shipment->is_optional_pickup_point_service($method_code) ) {
                  $custom_address = '';
                }
                $pickup_points = $this->get_pickup_points_for_method($method_code, $order_postcode, "$order_address, $order_city", $order_country, $custom_address);
                $select_first_option = '- ' . __('Select', 'woo-pakettikauppa') . ' -';
                $settings = $this->shipment->get_settings();
                $pickup_points_type = array();
                if ( isset($settings['pickup_points_type']) && ! empty($settings['pickup_points_type']) && ! in_array('all', $settings['pickup_points_type']) ) {
                  $pickup_points_type = $settings['pickup_points_type'];
                }
                ?>
                <div id="pickup-changer-<?php echo $method_code; ?>" class="pakettikauppa-pickup-changer" style="display: none;">
                  <script>
                    var btn_values_<?php echo $method_code; ?> = {
                      container_id : "pickup-changer-<?php echo $method_code; ?>"
                    };
                  </script>
                  <div class="pakettikauppa-pickup-search">
                    <h4><?php echo __('Search pickup points', 'woo-pakettikauppa'); ?></h4>
                    <input class="pakettikauppa-pickup-method" type="hidden" value="<?php echo $method_code; ?>">
                    <textarea class="pakettikauppa-pickup-search-field" rows="2" onchange="pakettikauppa_change_element_value('.pakettikauppa-pickup-search-field',this.value);"><?php echo $custom_address; ?></textarea>
                    <?php if ( $pickup_points_type ) { ?>
                      <ol style="list-style:circle;">
                        <li>
                          <input
                                  type="radio"
                                  id="search_filter_all_<?php echo $method_code; ?>"
                                  class="pakettikauppa_metabox_array_values"
                                  name="wc_pakettikauppa_search_filter"
                                  value="all"
                                  />
                          <label for="search_filter_all_<?php echo $method_code; ?>"><?php echo __('Without filters', 'woo-pakettikauppa'); ?></label>
                        </li>
                        <li>
                          <input
                                  type="radio"
                                  id="search_filter_filters_<?php echo $method_code; ?>"
                                  class="pakettikauppa_metabox_array_values"
                                  name="wc_pakettikauppa_search_filter"
                                  value="<?php echo implode(',', $pickup_points_type); ?>"
                                  />
                          <label for="search_filter_filters_<?php echo $method_code; ?>"><?php echo __('With filters', 'woo-pakettikauppa'); ?></label>
                        </li>
                      </ol>
                     <?php } ?>
                    <button type="button" value="search" class="button button-small btn-search" onclick="pakettikauppa_pickup_points_by_custom_address(btn_values_<?php echo $method_code; ?>);"><?php echo __('Search', 'woo-pakettikauppa'); ?></button>
                    <span class="pakettikauppa-msg-error error-pickup-search" style="display:none;"><?php echo __('No pickup points were found', 'woo-pakettikauppa'); ?></span>
                    <span class="pakettikauppa-msg-notice notice-pickup-search" style="display:none;"><?php echo __('Pickup point selection cleared', 'woo-pakettikauppa'); ?></span>
                  </div>
                  <div class="pakettikauppa-pickup-select-block">
                    <h4><?php echo __('Select pickup point', 'woo-pakettikauppa'); ?></h4>
                    <select class="pakettikauppa_metabox_values pakettikauppa-pickup-select" onchange="pakettikauppa_change_selected_pickup_point(this);">
                      <?php if ( is_array($pickup_points) ) : ?>
                        <?php foreach ( $pickup_points as $point ) : ?>
                          <?php
                          $point_name    = $point->provider . ': ' . $point->name;
                          $point_id      = ' (#' . $point->pickup_point_id . ')';
                          $point_address = ' (' . $point->street_address . ')';
                          ?>
                          <option value="<?php echo $point_name . $point_id; ?>" data-id="<?php echo $point->pickup_point_id; ?>"><?php echo $point_name . $point_address; ?></option>
                        <?php endforeach; ?>
                      <?php else : ?>
                        <option>---</option>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php $settings = $this->shipment->get_settings(); ?>
            <div class="pakettikauppa_express_freight_pallet_type_block">
              <h4><?php echo __('Pallet type', 'woo-pakettikauppa'); ?></h4>
              <select name="wc_pakettikauppa_express_freight_pallet_type" id="pakettikauppa_express_freight_pallet_type" class="pakettikauppa_metabox_values">
                <?php $default_pallet_type = (isset($settings['express_freight_pallet_type'])) ? $settings['express_freight_pallet_type'] : 'CC'; ?>
                <?php foreach ( Shipment::get_express_freight_pallet_types() as $pallet_key => $pallet_title ) : ?>
                  <?php $selected = ($default_pallet_type == $pallet_key) ? 'selected' : ''; ?>
                  <option value="<?php echo esc_attr($pallet_key); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($pallet_title); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <h4><?php echo __('Add additional text on labels', 'woo-pakettikauppa'); ?></h4>
              <textarea class="pakettikauppa-additional-info" rows="2"><?php echo $settings['label_additional_info'] ?? ''; ?></textarea>
            </div>
          </fieldset>
            <fieldset id = "default_shipment_additional_services">
                <ol style="list-style: circle;">
                </ol>
            </fieldset>
        </div>
        <div class="pakettikauppa-general">
          <?php $this->tpl_products_selector($order); ?>
          <?php $this->tpl_additional_params($order); ?>
          <?php if ( $this->core->shippingmethod == 'pakettikauppa_shipping_method' ) : ?>
            <div class="pakettikauppa-estimated-price">
              <span class="title">
                <?php echo esc_html__('Estimated shipping price', 'woo-pakettikauppa'); ?>:
              </span>
              <span id="estimated-shipping-price" class="value" data-service="<?php echo esc_html($service_id); ?>">
                <?php $estimated_price = $this->core->shipment->get_estimated_shipping_price($order, $service_id); ?>
                <?php echo ($estimated_price) ? wc_price($estimated_price / 100) : str_replace('0', '-', wc_price(0)); ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
        <p class="pakettikauppa-metabox-footer">
          <?php if ( $this->core->order_pickup ) : ?>
          <label for="wc_pakettikauppa_add_to_manifest" id="custom_add_to_manifest">
            <input type="checkbox" id="wc_pakettikauppa_add_to_manifest" class="pakettikauppa_metabox_array_values" name="wc_pakettikauppa_add_to_manifest" value="1">
            <?php echo esc_html__('Add to pickup order', 'woo-pakettikauppa'); ?>
          </label>
          <?php endif; ?>
          <?php if ( ! empty($service_id) ) : ?>
            <?php $button_text = __('Custom shipping...', 'woo-pakettikauppa'); ?>
            <button type="button" value="change" id="pakettikauppa_metabtn_change" class="button pakettikauppa_meta_box" onclick="pakettikauppa_change_method(this);" data-txt1="<?php echo $button_text; ?>" data-txt2="<?php echo __('Original shipping...', 'woo-pakettikauppa'); ?>">
              <?php echo $button_text; ?>
            </button>
          <?php endif; ?>
          <input type="hidden" id="pakettikauppa_microtime" name="pakettikauppa_microtime" value="<?php echo round(microtime(true) * 1000); ?>"/>
          <button type="button" value="create" id="pakettikauppa_metabtn_create" name="wc_pakettikauppa[create]" class="button pakettikauppa_meta_box button-primary" onclick="pakettikauppa_meta_box_submit(this);">
            <?php echo __('Create', 'woo-pakettikauppa'); ?>
          </button>
        </p>
    </div>
    <?php
  }

  public function get_pickup_point_by_custom_address() {
    $method_code = sanitize_text_field($_POST['method']);
    $custom_address = sanitize_text_field($_POST['address']);
    $type = (isset($_POST['type'])) ? sanitize_text_field($_POST['type']) : null;
    try {
      // Admin-only search, unlike checkout, so it always searches by
      // custom address regardless of show_pickup_point_override_query.
      $pickup_points = $this->shipment->get_pickup_points_by_free_input($custom_address, $method_code, $type);
    } catch ( \Exception $e ) {
      $pickup_points = 'error-zip';
    }
    if ( $pickup_points == 'error-zip' ) {
      echo $pickup_points;
    } else {
      echo json_encode($pickup_points);
    }
    wp_die();
  }

  private function get_pickup_points_for_method( $method_code, $postcode, $address = null, $country = null, $custom_address = null, $type = null ) {
    $pickup_points = array();
    try {
      $settings = $this->shipment->get_settings();
      if ( $custom_address && $settings['show_pickup_point_override_query'] === 'yes' ) {
        $pickup_points = $this->shipment->get_pickup_points_by_free_input($custom_address, $method_code, $type);
      } elseif ( ! empty($postcode) ) {
        $pickup_points = $this->shipment->get_pickup_points($postcode, $address, $country, $method_code, $type);
      }
    } catch ( \Exception $e ) {
      $pickup_points = 'error-zip';
    }
    return $pickup_points;
  }

  public function get_custom_shipment_table() {
    if ( ! isset($_REQUEST['id']) ) {
      return false;
    }

    $ids = $_REQUEST['id'];

    ?>
    <div class="loader-wrapper"><span class="loader"></span></div>
    <div id="pakettikauppa-shipments-table">
      <div>
        <h3><?php echo esc_attr__('Pakettikaupa create custom shipments', 'woo-pakettikauppa'); ?></h3>
        <div>
          <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
              <tr>
                <th><?php echo esc_attr__('Order', 'woo-pakettikauppa'); ?></th>
                <th><?php echo esc_attr__('Current shipping method', 'woo-pakettikauppa'); ?></th>
                <th><?php echo esc_attr__('New shipping method', 'woo-pakettikauppa'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
                foreach ( $ids as $id ) {
                  $order = wc_get_order($id);
                  if ( $order ) {
              ?>
                    <tr class="inside" id="woo-pakettikauppa_<?php echo $id; ?>">
                      <td>#<?php echo $id; ?> <?php echo $order->get_formatted_shipping_full_name(); ?></td>
                      <?php $this->meta_box_custom_shipments($order); ?>
                    </tr>
                    <?php
                      }
                    }
                    ?>
            </tbody>
          </table>
        </div>
      </div>

      <button type="button" value="create" id="pakettikauppa_metabtn_create_bulk" name="wc_pakettikauppa[create]" class="button pakettikauppa_meta_box button-primary" onclick="pakettikauppa_meta_box_bulk_submit(this);">
        <?php echo esc_attr__('Create', 'woo-pakettikauppa'); ?>
      </button>
    </div>
    <?php
  }

  /**
   * Meta box for managing shipments.
   *
   * @param $post
   */
  public function meta_box_custom_shipments( $order ) {
    if ( $order === null ) {
      return;
    }

    if ( ! Shipment::validate_order_shipping_receiver($order) ) {
      esc_attr_e('Please add shipping info to the order to manage shipments.', 'woo-pakettikauppa');
      return;
    }

    $service_id = '';

    $default_service_id = $this->shipment->get_service_id_from_order($order, false);
    if ( empty($service_id) ) {
      $service_id = $default_service_id;
    }

    $all_shipment_services = $this->shipment->services();
    ?>
    <td class="current">
    <?php $this->get_current_shipment($order); ?>
    </td>
    <td>
      <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_custom_shipping_method">
        <?php if ( ! empty($all_shipment_services) ) : ?>
        <select name="wc_pakettikauppa_service_id" id="pakettikauppa-service" class="pakettikauppa_metabox_values" onchange="pakettikauppa_change_shipping_method(this);">
          <option value="__NULL__"><?php esc_html_e('No shipping', 'woo-pakettikauppa'); ?></option>
          <?php foreach ( $all_shipment_services as $_service_code => $_service_title ) : ?>
            <option
              <?php if ( strval($_service_code) === $service_id ) : ?>
                    selected="selected"
              <?php endif; ?>
                    value="<?php echo esc_attr($_service_code); ?>">
              <?php echo esc_html($_service_title); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php else : ?>
          <span class="pakettikauppa-msg-error"><?php echo __('Service not working. Please check the settings.', 'woo-pakettikauppa'); ?></span>
        <?php endif; ?>

      </fieldset>

      <input type="hidden" id="pakettikauppa_microtime" name="pakettikauppa_microtime" value="<?php echo round(microtime(true) * 1000); ?>"/>
      <input type="hidden" name="pakettikauppa_order_id[]" value="<?php echo $order->get_id(); ?>"/>

    </td>
    <?php
  }

  public function get_current_shipment( $order ) {
    if ( $order === null ) {
      return;
    }
    $default_service_id = $this->shipment->get_service_id_from_order($order, false);
    $pickup_point_id = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point_id');
    ?>
    <input type="hidden" name="pakettikauppa_nonce" value="<?php echo wp_create_nonce(str_replace('wc_', '', $this->core->prefix) . '-meta-box'); ?>" id="pakettikauppa_metabox_nonce" />
    <fieldset class="pakettikauppa-metabox-fieldset" id="wc_pakettikauppa_shipping_method">
      <p><?php echo esc_html($this->shipment->service_title($default_service_id)); ?></p>

      <?php if ( $pickup_point_id ) : ?>
        <?php
        $labels = $this->shipment->get_labels($order->get_id());
        if ( ! empty($labels) ) {
          $last_label = end($labels);
          $pickpoint_requested = $last_label['pickup_name'];
        } else {
          $pickpoint_requested = $order->get_meta('_' . $this->core->params_prefix . 'pickup_point');
        }
        ?>
        <div class="pakettikauppa-pickup-point-requested">
          <p>
            <b><?php echo esc_html__('Pickup point', 'woo-pakettikauppa'); ?></b></br>
            <?php echo esc_html($pickpoint_requested); ?>
          </p>
        </div>
      <?php endif; ?>
    </fieldset>
    <?php
  }

  public function get_pickup_points_html( $id ) {
    $order = wc_get_order($id);

    if ( $order === null ) {
      return;
    }

    $all_additional_services = $this->shipment->get_additional_services();

    if ( empty($all_additional_services) ) {
      $all_additional_services = array();
    }
    $all_shipment_additional_services = array();
    if ( ! empty($all_additional_services) && ! empty($service_id) ) {
      $all_shipment_additional_services = $all_additional_services[$service_id];
    }

    if ( ! empty($all_shipment_additional_services) ) {
      foreach ( $all_shipment_additional_services as $additional_service ) {
        $additional_service_names[(string) $additional_service->service_code] = $additional_service->name;
      }
    }

    $order_postcode = $order->get_shipping_postcode();
    $order_address  = $order->get_shipping_address_1();
    $order_city = $order->get_shipping_city();
    $order_country  = $order->get_shipping_country();
    $address_override_field_name = $this->core->params_prefix . 'merchant_override_custom_pickup_point_address';
    $custom_address = $order->get_meta($address_override_field_name, true);
    $custom_address = empty($custom_address) ? "$order_address, $order_postcode $order_city, $order_country" : $custom_address;

    $service_id = '';

    $default_service_id = $this->shipment->get_service_id_from_order($order, false);
    if ( empty($service_id) ) {
      $service_id = $default_service_id;
    }

    foreach ( $all_additional_services as $method_code => $_additional_services ) {
      if ( $this->shipment->service_has_pickup_points($method_code) ) {
        $pickup_points = $this->get_pickup_points_for_method($method_code, $order_postcode, "$order_address, $order_city", $order_country, $custom_address);
        ?>
        <div id="pickup-changer-<?php echo $method_code; ?>" class="pakettikauppa-pickup-changer" <?php echo $service_id != $method_code ? 'style="display: none;"' : ''; ?>>
          <script>
            var btn_values_<?php echo $method_code; ?> = {
              container_id : "pickup-changer-<?php echo $method_code; ?>"
            };
          </script>
          <div class="pakettikauppa-pickup-select-block">
            <p style="margin-bottom: 5px;"><?php echo __('Select pickup point', 'woo-pakettikauppa'); ?></p>
            <select class="pakettikauppa_metabox_values pakettikauppa-pickup-select" onchange="pakettikauppa_change_selected_pickup_point(this);">
              <?php if ( is_array($pickup_points) ) : ?>
                <?php foreach ( $pickup_points as $point ) : ?>
                  <?php
                  $point_name    = $point->provider . ': ' . $point->name;
                  $point_id      = ' (#' . $point->pickup_point_id . ')';
                  $point_address = ' (' . $point->street_address . ')';
                  ?>
                  <option value="<?php echo $point_name . $point_id; ?>" data-id="<?php echo $point->pickup_point_id; ?>"><?php echo $point_name . $point_address; ?></option>
                <?php endforeach; ?>
              <?php else : ?>
                <option>---</option>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <?php
      }
    }
  }
}
