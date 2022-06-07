<?php

function get_plugin_class() {
  $dir = basename(get_plugin_directory(true));
  $class = '';

  $parts = explode('-', $dir);

  foreach ( $parts as $k => $part ) {
    $parts[$k] = ucfirst($part);
  }

  return join('_', $parts);
}

function get_plugin_directory( $replace = false ) {
  $dir = dirname(__DIR__);

  if ( ! $replace ) {
    return $dir;
  }

  // Hack to get tests working without deactivating the plugin for old users
  return str_replace('woo-pakettikauppa', 'wc-pakettikauppa', dirname(__DIR__));
}


/**
 * Dynamically find plugin main file name from plugin root dir.
 *
 * @return string|null Plugin file name or null if none found.
 */
function get_plugin_main_filename() {
  $plugin_dir = get_plugin_directory(true);
  $plugin_dir = rtrim($plugin_dir, '/\\');
  // phpcs:ignore PHPCS_SecurityAudit.BadFunctions.FilesystemFunctions.WarnFilesystem
  $files = scandir($plugin_dir);

  if ( ! $files ) {
    return null;
  }

  // phpcs:ignore PHPCS_SecurityAudit.BadFunctions.CallbackFunctions.WarnCallbackFunctions
  $files = array_filter(
    // phpcs:ignore PHPCS_SecurityAudit.BadFunctions.FilesystemFunctions.WarnFilesystem
    $files,
    function ( $filename ) {
      return '.php' === substr($filename, - 4);
    }
  );

  foreach ( $files as $file ) {
    // phpcs:ignore PHPCS_SecurityAudit.BadFunctions.FilesystemFunctions.WarnFilesystem, WordPress.WP.AlternativeFunctions.file_system_read_fopen
    $fh = fopen("$plugin_dir/$file", 'rb');

    if ( $fh === false ) {
      return null;
    }

    // Find plugin name declaration from first 20 lines.
    for ( $i = 0; $i < 20; $i ++ ) {
      // phpcs:ignore PHPCS_SecurityAudit.BadFunctions.FilesystemFunctions.WarnFilesystem
      $line = fgets($fh);

      if ( false === $line ) {
        break;
      }

      if ( 1 === preg_match('/^\s?\*?\s?Plugin Name:\s.+\n?$/', $line) ) {
        return $file;
      }
    }
  }

  return null;
}

function get_plugin_config() {
  $file = get_plugin_directory() . '/' . get_plugin_main_filename();
  return array(
    'root' => $file,
    'version' => get_file_data($file, array( 'Version' ), 'plugin')[0],
    'shipping_method_name' => 'pakettikauppa_shipping_method',
    'vendor_name' => 'Pakettikauppa',
    'vendor_url' => 'https://www.pakettikauppa.fi/',
    'vendor_logo' => 'assets/img/pakettikauppa-logo.png',
    'setup_background' => 'assets/img/pakettikauppa-background.jpg',
  );
}

function get_instance() {
  static $plugin = null;

  if ( ! $plugin ) {
    // phpcs:disable
    require get_plugin_directory() . '/' . get_plugin_main_filename();
    // phpcs:enable

    $plugin = $instance;
  }

  return $plugin;
}
