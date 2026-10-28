<?php
namespace Woo_Posti_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit();
}

/**
 * Admin notices: setup wizard prompts, transient-based admin/error/success notices.
 */
trait Admin_Notices_Trait {

  public function add_admin_notice( $msg, $type ) {
    $user_id = get_current_user_id();
    $key = 'pakettikauppa_notices_' . $user_id;

    $notices = get_transient($key);
    if ( ! is_array($notices) ) {
      $notices = [];
    }

    $notices[] = [
      'msg' => $msg,
      'type' => $type
    ];

    set_transient($key, $notices, 5 * MINUTE_IN_SECONDS);
  }

  public function show_admin_notices() {
    $user_id = get_current_user_id();
    $key = 'pakettikauppa_notices_' . $user_id;

    $notices = get_transient($key);

    if ( is_array($notices) ) {
      foreach ( $notices as $notice ) {
        if ( $notice['type'] === 'error' ) {
          $this->add_error_notice($notice['msg'], false);
        }
        if ( $notice['type'] === 'success' ) {
          $this->add_success_notice($notice['msg'], false);
        }
      }
    }

    if ($notices !== false) {
      delete_transient($key);
    }
  }

  public function maybe_show_notices( $current_screen ) {
    // Don't show the setup notice in every screen because that would be excessive.
    $show_notice_in_screens = array( 'plugins', 'dashboard' );

    // Always show the setup notice in plugin settings page
    $tab = isset($_GET['tab']) ? filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS) : false;
    $section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS) : false;
    $is_in_wc_settings = $current_screen->id === 'woocommerce_page_wc-settings' && $tab === 'shipping' && $section === $this->core->params_prefix . 'shipping_method';

    if ( in_array($current_screen->id, $show_notice_in_screens, true) ) {
      // Determine if this is a new install by checking if the plugin settings
      // have been saved even once. There's a longstanding bug that causes the plugin to save it's options pretty much immediately after activating,
      // as the show_pakettikauppa_shipping_method option is set to `no` by default. There are more than one saved setting if the user has ACTUALLY saved the settings...
      $settings = $this->shipment->get_settings();

      if ( empty($settings) || count($settings) < 2 ) {
        add_action('admin_notices', array( $this, 'new_install_notice_content' ));
      }
    } elseif ( $is_in_wc_settings ) {
      if ( get_option($this->core->prefix . '_wizard_done') !== '1' ) {
        add_action('admin_notices', array( $this, 'settings_page_setup_notice' ));
      }
    }
  }

  public function new_install_notice_content() {
    ?>
    <div class="notice notice-info pakettikauppa-notice pakettikauppa-notice--setup">
      <div class="pakettikauppa-notice__logo">
        <img src="<?php echo $this->core->dir_url; ?>assets/img/pakettikauppa-logo-black.png" alt="<?php echo $this->core->vendor_name; ?>">
      </div>

      <div class="pakettikauppa-notice__content">
        <p>
          <?php echo esc_html__('Thank you for installing Posti Shipping! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'); ?>

          <br />
          <br />

          <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $this->core->setup_page)); ?>">
            <?php echo __('Start the setup wizard', 'woo-pakettikauppa'); ?>
          </a>
        </p>
      </div>
    </div>
    <?php
  }

  public function settings_page_setup_notice() {
    ?>
    <div class="notice notice-info pakettikauppa-notice pakettikauppa-notice--setup">
      <div class="pakettikauppa-notice__logo">
        <img src="<?php echo $this->core->dir_url; ?>assets/img/pakettikauppa-logo-black.png" alt="<?php echo $this->core->vendor_name; ?>">
      </div>

      <div class="pakettikauppa-notice__content">
        <p>
          <?php echo esc_html__('Thank you for installing Posti Shipping! To get started smoothly, please open our setup wizard.', 'woo-pakettikauppa'); ?>

          <br />
          <br />

          <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $this->core->setup_page)); ?>">
            <?php echo __('Start the setup wizard', 'woo-pakettikauppa'); ?>
          </a>
        </p>
      </div>
    </div>
    <?php
  }

  /**
   * Add an admin error notice to wp-admin.
   */
  public function add_error_notice( $message, $show_prefix_text = true ) {
    if ( ! empty($message) ) {
      $class = 'notice notice-error';
      if ( $show_prefix_text ) {
        /* translators: %s: Error message */
        $print_error = wp_sprintf(__('An error occurred: %s', 'woo-pakettikauppa'), $message);
      } else {
        $print_error = $message;
      }
      printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
    }
  }

  /**
   * Add an admin success notice to wp-admin.
   */
  public function add_success_notice( $message, $show_prefix_text = true ) {
    if ( ! empty($message) ) {
      $class = 'notice notice-success';
      if ( $show_prefix_text ) {
        /* translators: %s: Error message */
        $print_error = wp_sprintf(__('Succeed: %s', 'woo-pakettikauppa'), $message);
      } else {
        $print_error = $message;
      }
      printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
    }
  }
}
