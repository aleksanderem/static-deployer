<?php
/**
 * Plugin Name: WP SFTP Deployer
 * Description: Deploy files to remote server via SFTP with zip packaging and extraction
 * Version: 1.0.0
 * Author: Alex Miesak
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
define('WP_SFTP_DEPLOYER_SETTINGS_DIR', WP_SFTP_DEPLOYER_PLUGIN_DIR . 'settings/');
define('WP_SFTP_DEPLOYER_SETTINGS_FILE', WP_SFTP_DEPLOYER_SETTINGS_DIR . 'deployer-settings.json');
define('WP_SFTP_DEPLOYER_LOG_DIR', WP_SFTP_DEPLOYER_PLUGIN_DIR . 'logs/');
define('WP_SFTP_DEPLOYER_TEMP_DIR', WP_SFTP_DEPLOYER_PLUGIN_DIR . 'temp/');

// Load phpseclib if SSH2 extension is not available
if (!extension_loaded('ssh2')) {
  // Check if Composer autoloader exists
  if (file_exists(WP_SFTP_DEPLOYER_PLUGIN_DIR . 'vendor/autoload.php')) {
	require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'vendor/autoload.php';
  } else {
	// Ręczne ładowanie phpseclib (alternatywnie można dodać autoloader)
	require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'vendor/phpseclib/bootstrap.php';
  }
}

// Include required files
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-requirements.php';
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer.php';
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-admin.php';
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-sftp.php';
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-zipper.php';
require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-logger.php';

// Initialize the plugin
function wp_sftp_deployer_init() {
  // Check requirements first
  $requirements = new WP_SFTP_Deployer_Requirements();
  $check = $requirements->check_all();
  
  // Register requirements notices
  add_action('admin_notices', [$requirements, 'display_notices']);
  
  // Initialize the main plugin class
  $plugin = new WP_SFTP_Deployer();
  $plugin->init();
  
  // Initialize the admin class
  $admin = new WP_SFTP_Deployer_Admin();
  $admin->init();
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
  if (!file_exists(WP_SFTP_DEPLOYER_SETTINGS_DIR)) {
	wp_mkdir_p(WP_SFTP_DEPLOYER_SETTINGS_DIR);
  }
  
  // Create temp directory
  if (!file_exists(WP_SFTP_DEPLOYER_TEMP_DIR)) {
	wp_mkdir_p(WP_SFTP_DEPLOYER_TEMP_DIR);
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
			'source_path' => ABSPATH, // Domyślnie ścieżka WordPress
		],
		'logging' => [
			'enabled' => true,
			'custom_path' => '',
			'log_level' => 'info'
		],
		'test_mode' => false // Dodatkowa opcja trybu testowego
	];
	
	file_put_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE, json_encode($default_settings, JSON_PRETTY_PRINT));
  }
  
  // Set proper permissions for security
  chmod(WP_SFTP_DEPLOYER_SETTINGS_FILE, 0600);
  
  // Add protection to sensitive directories
  $htaccess_content = "Order deny,allow\nDeny from all";
  file_put_contents(WP_SFTP_DEPLOYER_SETTINGS_DIR . '.htaccess', $htaccess_content);
  file_put_contents(WP_SFTP_DEPLOYER_LOG_DIR . '.htaccess', $htaccess_content);
  file_put_contents(WP_SFTP_DEPLOYER_TEMP_DIR . '.htaccess', $htaccess_content);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_sftp_deployer_deactivate');
function wp_sftp_deployer_deactivate() {
  // Clean up temporary files
  $temp_files = glob(WP_SFTP_DEPLOYER_TEMP_DIR . '*.zip');
  if (is_array($temp_files)) {
	foreach ($temp_files as $file) {
	  @unlink($file);
	}
  }
}
