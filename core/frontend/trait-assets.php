<?php

namespace Woo_Posti_Core;

if ( ! defined('ABSPATH') ) {
  exit;
}

trait Frontend_Assets_Trait {
  /**
   * Add an error with a specified error message.
   *
   * @param string $message A message containing details about the error.
   */
  public function add_error( $message ) {
    if ( ! empty($message) ) {
      array_push($this->errors, $message);
    }
  }

  /**
   * Display error in woocommerce
   */
  public function display_error( $error = null ) {
    if ( ! $error ) {
      $error = __('An error occurred. Please try again later.', 'woo-pakettikauppa');
    }

    wc_add_notice($error, 'error');
  }

  /**
   * Enqueue frontend-specific styles and scripts.
   */
  public function enqueue_scripts() {

    if ( ! is_checkout() ) {
      return;
    }

    wp_enqueue_style($this->core->prefix . '_css', $this->core->dir_url . 'assets/css/frontend.css', array(), $this->core->version);
    wp_enqueue_script($this->core->prefix . '_js', $this->core->dir_url . 'assets/js/frontend.js', array( 'jquery' ), $this->core->version, true);
    wp_localize_script(
      $this->core->prefix . '_js',
      'pakettikauppaData',
      array(
        'privatePickupPointConfirm' => __('The pickup point you\'ve chosen is not available for public access. Are you sure that you can retrieve the package?', 'woo-pakettikauppa'),
      )
    );
  }
}
