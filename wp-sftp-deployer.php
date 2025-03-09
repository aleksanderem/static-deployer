<?php
  /**
   * Plugin Name: WP SFTP Deployer
   * Description: Deploy files to remote server via SFTP with zip packaging and extraction
   * Version: 1.0.0
   * Author: Cody
   * Text Domain: wp-sftp-deployer
   */

  // Exit if accessed directly
  if (!defined('ABSPATH')) {
    exit;
  }

  // Define plugin constants
  define('WP_SFTP_DEPLOYER_VERSION', '1.0.0');
  define('WP_SFTP_DEPLOYER_PLUGIN_DIR', plugin_dir_path(__FILE__));
  define('WP_SFTP_DEPLOYER_PLUGIN_URL', plugin_dir_url(__FILE__));
  define('WP_SFTP_DEPLOYER_SETTINGS_FILE', WP_SFTP_DEPLOYER_PLUGIN_DIR . 'settings/deployer-settings.json');
  define('WP_SFTP_DEPLOYER_LOG_DIR', WP_SFTP_DEPLOYER_PLUGIN_DIR . 'logs/');

  // Include required files
  require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer.php';
  require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-admin.php';
  require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-sftp.php';
  require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-zipper.php';
  require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-logger.php';

  // Initialize the plugin
  function wp_sftp_deployer_init() {
    $plugin = new WP_SFTP_Deployer();
    $plugin->init();
  }
  add_action('plugins_loaded', 'wp_sftp_deployer_init');

  // Activation hook
  register_activation_hook(__FILE__, 'wp_sftp_deployer_activate');
  function wp_sftp_deployer_activate() {
    // Create logs directory
    if (!file_exists(WP_SFTP_DEPLOYER_LOG_DIR)) {
      wp_mkdir_p(WP_SFTP_DEPLOYER_LOG_DIR);
    }

    // Create settings directory and file if not exists
    $settings_dir = dirname(WP_SFTP_DEPLOYER_SETTINGS_FILE);
    if (!file_exists($settings_dir)) {
      wp_mkdir_p($settings_dir);
    }

    if (!file_exists(WP_SFTP_DEPLOYER_SETTINGS_FILE)) {
      $default_settings = [
          'sftp' => [
              'host' => '',
              'port' => 22,
              'username' => '',
              'password' => '',
              'remote_path' => '',
              'timeout' => 30
          ],
          'local' => [
              'source_path' => '',
          ],
          'logging' => [
              'enabled' => true,
              'custom_path' => '',
              'log_level' => 'info'
          ]
      ];
      file_put_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE, json_encode($default_settings, JSON_PRETTY_PRINT));
    }

    // Set proper permissions for security
    chmod(WP_SFTP_DEPLOYER_SETTINGS_FILE, 0600);
  }
