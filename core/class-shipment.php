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
    private $use_posti_auth = false;
    private $client_authorized = false;
    private $access_token = null;

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
      $secret_key     = isset($settings['secret_key']) ? $settings['secret_key'] : '';
      $mode           = $this->core->api_mode;

      if ( empty($this->config[$mode]) ) {
          $this->config[$mode] = array();
      }

      $configs = $this->core->api_config;

      $configs[$mode] = array_merge(
        array(
          'api_key'   => $account_number,
          'secret'    => $secret_key,
          'use_posti_auth' => false,
        ),
        $this->core->api_config[$mode]
      );

      $this->client = new \Pakettikauppa\Client($configs, $mode);
      $this->client->setComment($this->core->api_comment);
      $this->client->setSenderSystemName('Woocommerce');

      $this->client_authorized = false;
      $this->access_token      = null;
      $this->use_posti_auth    = ! empty($configs[$mode]['use_posti_auth']);

      if ( is_admin() ) {
        add_action('admin_notices', array( $this, 'show_connection_error_notice' ));
        add_action('admin_post_' . $this->core->prefix . '_retry_token', array( $this, 'retry_token_request' ));
      }

      add_action($this->core->prefix . '_refresh_token', array( $this, 'refresh_token_in_background' ));
      if ( ! is_admin() ) {
        // Request the token in the background while the visitor is browsing, before it is needed at checkout
        add_action('template_redirect', array( $this, 'schedule_token_refresh' ));
      }

      // The token is requested lazily on the first API call (see get_client()), except on the
      // shipping settings page, where token errors are shown as an admin notice
      if ( is_admin() && isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'shipping' ) {
        $this->get_client();
      }
    }

    /**
     * Get the API client, requesting the access token first if it was not done yet in this request.
     *
     * @return Client
     */
    public function get_client() {
      if ( ! $this->client_authorized ) {
        // Mark as authorized before trying, so a failed attempt is not repeated within the same request
        $this->client_authorized = true;
        $this->authorize_client();
      }

      return $this->client;
    }

    /**
     * Get the Posti OAuth access token, requesting it if needed.
     *
     * @return string|null
     */
    public function get_access_token() {
      $this->get_client();

      return $this->access_token;
    }

    /**
     * Schedule the access token request via Action Scheduler if there is no valid token.
     * If the action did not run yet when the token is needed, it is requested directly as usual.
     */
    public function schedule_token_refresh() {
      if ( ! $this->use_posti_auth || ! function_exists('as_enqueue_async_action') || ! function_exists('as_has_scheduled_action') ) {
        return;
      }

      $failed_transient_name = $this->get_failed_token_transient_name();
      if ( $failed_transient_name === null ) {
        return;
      }

      // Nothing to do if the token is valid, it is being requested right now or requests are blocked after a failure
      if ( ! $this->is_token_expired(get_transient($this->core->prefix . '_access_token'))
        || get_transient($this->core->prefix . '_access_token_lock') !== false
        || get_transient($failed_transient_name) !== false ) {
        return;
      }

      $hook = $this->core->prefix . '_refresh_token';
      if ( as_has_scheduled_action($hook, array(), $this->core->prefix) ) {
        return;
      }

      as_enqueue_async_action($hook, array(), $this->core->prefix);
    }

    /**
     * Action Scheduler callback for the access token request.
     */
    public function refresh_token_in_background() {
      $this->get_client();
    }

    /**
     * @param mixed $token Token saved in transient
     *
     * @return bool True if there is no token or it is expired
     */
    private function is_token_expired( $token ) {
      return empty($token) || (isset($token->timestamp) && ($token->timestamp + $token->expires_in - 100) < time());
    }

    /**
     * Request the Posti OAuth access token (or take it from cache) and pass it to the API client.
     */
    private function authorize_client() {
      $settings = $this->get_settings();

      $account_number = isset($settings['account_number']) ? $settings['account_number'] : '';
      $secret_key     = isset($settings['secret_key']) ? $settings['secret_key'] : '';

      // If we don't have an account number or secret key, we can't authorize the API client
      if ($account_number === '' || $secret_key === '') {
        return;
      }

      if ( $this->use_posti_auth ) {
        $transient_name = $this->core->prefix . '_access_token';
        $lock_name      = $this->core->prefix . '_access_token_lock';

        $failed_transient_name  = $this->get_failed_token_transient_name();
        $failed_ttl             = 300; // seconds - how long to skip retrying known-bad credentials
        $no_response_step       = 300; // seconds - block time added for every failure in a row when the server did not respond at all
        $no_response_max_ttl    = 1800; // seconds - max block time when the server did not respond at all
        $no_response_count_name = $failed_transient_name . '_count';
        $no_response_count_ttl  = DAY_IN_SECONDS; // seconds - how long to remember failures in a row

        $lock_ttl = 30; // seconds
        $loop_wait = 200000; // 200ms in microseconds
        $max_wait = 5; // seconds
        $max_loops = (int) (($max_wait * 1000000) / $loop_wait); // calculate how many loops fit into max wait

        // Bail out early if we already know these credentials do not work, instead of hitting the API again
        $failed_token = get_transient($failed_transient_name);
        if ( $failed_token !== false ) {
          $this->showTokenError($failed_token);
          return;
        }

        $token = get_transient($transient_name);

        // check if we hame timestamp saved and check if token is not expired
        if ( $this->is_token_expired($token) ) {
          // check the lock for this request
          if ( get_transient($lock_name) === false ) {
            // lock execution from other requests
            set_transient($lock_name, 1, $lock_ttl);

            $token = $this->client->getToken();

            if ( empty($token) || ! isset($token->expires_in) || isset($token->error) ) {
              // remove lock if failed to get token
              delete_transient($lock_name);

              // remember that these credentials failed so we don't keep retrying them right away.
              // No response at all (DNS, timeout, blocked connection) is blocked longer with every failure in a row.
              $no_response = empty($this->client->http_response_code);
              if ( $no_response ) {
                $no_response_count = (int) get_transient($no_response_count_name) + 1;
                set_transient($no_response_count_name, $no_response_count, $no_response_count_ttl);
                $ttl = min($no_response_step * $no_response_count, $no_response_max_ttl);
              } else {
                $no_response_count = 0;
                delete_transient($no_response_count_name);
                $ttl = $failed_ttl;
              }

              $failed_token = ! empty($token) ? $token : (object) array(
                'message' => __('Failed to connect with server', 'woo-pakettikauppa'),
              );
              $failed_token->no_response       = $no_response;
              $failed_token->no_response_count = $no_response_count;
              $failed_token->blocked_until     = time() + $ttl;
              set_transient($failed_transient_name, $failed_token, $ttl);

              $this->showTokenError($failed_token);
              return;
            }

            // credentials worked - clear any previous failure marker for this hash
            delete_transient($failed_transient_name);
            delete_transient($no_response_count_name);

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

        $this->access_token = $token->access_token;
        $this->client->setAccessToken($this->access_token);
      }
    }

    /**
     * Get the name of the transient which remembers a failed token request for the current credentials.
     *
     * @return string|null Null if credentials are not set
     */
    private function get_failed_token_transient_name() {
      $settings = $this->get_settings();

      $account_number = isset($settings['account_number']) ? $settings['account_number'] : '';
      $secret_key     = isset($settings['secret_key']) ? $settings['secret_key'] : '';

      if ( $account_number === '' || $secret_key === '' ) {
        return null;
      }

      // Hash the credentials so we can remember if this exact api_key/secret pair already failed,
      // without ever storing the raw secret in the transient name.
      return $this->core->prefix . '_access_token_failed_' . md5($account_number . ':' . $secret_key);
    }

    /**
     * Check if the failed token request should be shown on all admin pages, i.e. the server did not respond
     * at least twice in a row.
     *
     * @param object $failed_token
     *
     * @return bool
     */
    private function is_connection_down( $failed_token ) {
      return ! empty($failed_token->no_response) && isset($failed_token->no_response_count) && $failed_token->no_response_count >= 2;
    }

    /**
     * Show a notice on all admin pages while token requests are blocked because the server did not respond.
     */
    public function show_connection_error_notice() {
      if ( ! current_user_can('manage_woocommerce') ) {
        return;
      }

      $failed_transient_name = $this->get_failed_token_transient_name();
      if ( $failed_transient_name === null ) {
        return;
      }

      $failed_token = get_transient($failed_transient_name);
      if ( ! is_object($failed_token) || ! $this->is_connection_down($failed_token) ) {
        return;
      }

      $retry_url = wp_nonce_url(admin_url('admin-post.php?action=' . $this->core->prefix . '_retry_token'), $this->core->prefix . '_retry_token');
      $blocked_until = isset($failed_token->blocked_until) ? wp_date(get_option('time_format'), $failed_token->blocked_until) : '';

      echo '<div class="notice notice-error"><p><b>'
        . esc_html($this->core->vendor_fullname)
        . ' error:</b> '
        /* translators: %s: Time until which connection attempts are blocked */
        . esc_html(sprintf(__('No response from the API server, connection to the API server is down. Further connection attempts are blocked until %s.', 'woo-pakettikauppa'), $blocked_until))
        . '</p><p><a class="button" href="' . esc_url($retry_url) . '">'
        . esc_html__('Retry now', 'woo-pakettikauppa')
        . '</a></p></div>';
    }

    /**
     * Remove the failed token request block and try to get the token again.
     */
    public function retry_token_request() {
      if ( ! current_user_can('manage_woocommerce') ) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'woo-pakettikauppa'));
      }

      check_admin_referer($this->core->prefix . '_retry_token');

      $failed_transient_name = $this->get_failed_token_transient_name();
      if ( $failed_transient_name !== null ) {
        delete_transient($failed_transient_name);
      }

      // If it fails again, the block is set again and the notice is shown after redirect
      $this->get_client();

      wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
      exit;
    }

    private function showTokenError($token)
    {
      // Repeated no response errors are shown on all admin pages by show_connection_error_notice()
      if ( is_object($token) && $this->is_connection_down($token) ) {
        return;
      }

      add_action('admin_notices', function () use ($token) {
        if ( isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'shipping' ) {
            $message = (isset($token->message)) ? $token->message : __('Unknown error', 'woo-pakettikauppa');
            echo '<div class="notice notice-error"><p><b>'
              . esc_html($this->core->vendor_fullname)
              . ' error:</b> '
              . esc_html($message)
              . '</p></div>';
        }
      });
    }

  }
}
