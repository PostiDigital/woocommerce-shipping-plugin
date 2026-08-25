<?php

namespace Woo_Posti_Core;

/**
 * Shipment module.
 */

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use Pakettikauppa\Shipment as PK_Shipment;
use Pakettikauppa\Shipment\ContentLine;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\AdditionalService;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Client;

require_once __DIR__ . '/shipment/trait-creation.php';
require_once __DIR__ . '/shipment/trait-labels.php';
require_once __DIR__ . '/shipment/trait-pickup-points.php';
require_once __DIR__ . '/shipment/trait-services.php';
require_once __DIR__ . '/shipment/trait-settings.php';
require_once __DIR__ . '/shipment/trait-utilities.php';

if ( ! class_exists(__NAMESPACE__ . '\Shipment') ) {
  class Shipment {
    use Shipment_Creation_Trait;
    use Shipment_Labels_Trait;
    use Shipment_Pickup_Points_Trait;
    use Shipment_Services_Trait;
    use Shipment_Settings_Trait;
    use Shipment_Utilities_Trait;

    public $core = null;
    public $client = null;
    protected $settings = null;
    public $id;
    public $config;
    private $errors = array();

    public function __construct( \Woo_Posti_Shipping $plugin ) {
      $this->core = $plugin;
      $this->id = $this->core->prefix . '_shipment';
    }

    public function add_error( $message ) {
      if ( ! empty($message) ) {
        array_push($this->errors, $message);
      }
    }

    public function get_errors() {
      return $this->errors;
    }

    public function clear_errors() {
      unset($this->errors);
      $this->errors = array();
    }

    public function load() {
      $settings = $this->get_settings();
      $account_number = isset($settings['account_number']) ? $settings['account_number'] : '';
      $secret_key = isset($settings['secret_key']) ? $settings['secret_key'] : '';
      $mode = $this->core->api_mode;
      if ( empty($this->config[$mode]) ) {
        $this->config[$mode] = array();
      }
      $configs = $this->core->api_config;
      $configs[$mode] = array_merge(
        array(
          'api_key' => $account_number,
          'secret' => $secret_key,
          'use_posti_auth' => false,
        ),
        $this->core->api_config[$mode]
      );
      $this->client = new \Pakettikauppa\Client($configs, $mode);
      $this->client->setComment($this->core->api_comment);
      $this->client->setSenderSystemName('Woocommerce');

      if ( $configs[$mode]['use_posti_auth'] ) {
        $transient_name = $this->core->prefix . '_access_token';
        $lock_name      = $this->core->prefix . '_access_token_lock';

        $lock_ttl = 30; // seconds
        $loop_wait = 200000; // 200ms in microseconds
        $max_wait = 10; // seconds
        $max_loops = (int) (($max_wait * 1000000) / $loop_wait); // calculate how many loops fit into max wait

        $token = get_transient($transient_name);

        // check if we hame timestamp saved and check if token is not expired
        if ( empty($token) || (isset($token->timestamp) && ($token->timestamp + $token->expires_in - 100) < time()) ) {
          // check the lock for this request
          if ( get_transient($lock_name) === false ) {
            // lock execution from other requests
            set_transient($lock_name, 1, $lock_ttl);

            $token = $this->client->getToken();

            if ( empty($token) || ! isset($token->expires_in) || isset($token->error) ) {
              // remove lock if failed to get token
              delete_transient($lock_name);
              $this->showTokenError($token);
              return;
            }

            // add timestamp to token for validating expiration
            $token->timestamp = time();

            // let's remove 100 seconds from expires_in time so in case of a network lag, requests will still be valid on server side
            set_transient($transient_name, $token, $token->expires_in - 100);

            // unlock
            delete_transient($lock_name);
          } else {
            // wait until lock released or token appears
            for ( $i = 0; $i < $max_loops; $i++ ) {
              usleep($loop_wait);

              $token = get_transient($transient_name);
              if ( ! empty($token) ) {
                break;
              }

              if ( get_transient($lock_name) === false ) {
                // lock gone but token still missing
                $this->showTokenError((object)[
                  'message' => sprintf(__('Failed to obtain access token (%s)', 'woo-pakettikauppa'), __('lock expired', 'woo-pakettikauppa'))
                ]);
                return;
              }
            }

            // timeout safeguard
            if ( empty($token) ) {
              $this->showTokenError((object)[
                'message' => sprintf(__('Failed to obtain access token (%s)', 'woo-pakettikauppa'), __('timeout', 'woo-pakettikauppa'))
              ]);
              return;
            }
          }
        }

        $this->client->setAccessToken($token->access_token);
      }
    }

    private function showTokenError($token)
    {
      add_action('admin_notices', function () use ($token) {
        if ( isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'shipping' ) {
            $message = (isset($token->message)) ? $token->message : __('Unknown error', 'woo-pakettikauppa');
            echo '<div class="notice notice-error"><p><b>TEST'
              . esc_html($this->core->vendor_fullname)
              . ' error:</b> '
              . esc_html($message)
              . '</p></div>';
        }
      });
    }

  }
}
