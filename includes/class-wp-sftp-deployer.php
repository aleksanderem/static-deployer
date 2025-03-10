<?php
/**
 * Main plugin class
 */
class WP_SFTP_Deployer {
  
  private $logger;
  private $version;
  
  /**
   * Constructor
   */
  public function __construct() {
	$this->version = WP_SFTP_DEPLOYER_VERSION;
	$this->logger = new WP_SFTP_Deployer_Logger();
  }
  
  /**
   * Initialize the plugin
   */
  public function init() {
	// Add custom action links on the plugins page
	add_filter('plugin_action_links_wp-sftp-deployer/wp-sftp-deployer.php', [$this, 'add_action_links']);
  }
  
  /**
   * Add custom action links on the plugins page
   */
  public function add_action_links($links) {
	$custom_links = [
		'<a href="' . admin_url('tools.php?page=wp-sftp-deployer-settings') . '">' . __('Settings', 'wp-sftp-deployer') . '</a>',
	];
	return array_merge($custom_links, $links);
  }
  
  /**
   * Get plugin settings
   */
  public function get_settings() {
	if (!file_exists(WP_SFTP_DEPLOYER_SETTINGS_FILE)) {
	  return [];
	}
	
	$settings_json = file_get_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE);
	if (empty($settings_json)) {
	  return [];
	}
	
	$settings = json_decode($settings_json, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
	  $this->logger->log('Error parsing settings file: ' . json_last_error_msg(), 'error');
	  return [];
	}
	if (isset($settings['sftp']['password'])) {
	  $settings['sftp']['password'] = $this->decrypt_password($settings['sftp']['password']);
	}
	
	return $settings;
  }
  
  /**
   * Update plugin settings
   */
  /**
   * Encrypt sensitive data before saving
   */
  public function update_settings($settings) {
	// Encrypt the SFTP password if present
	if (isset($settings['sftp']['password']) && !empty($settings['sftp']['password'])) {
	  $settings['sftp']['password'] = $this->encrypt_password($settings['sftp']['password']);
	}
	
	// Continue with normal settings saving
	$settings_dir = dirname(WP_SFTP_DEPLOYER_SETTINGS_FILE);
	if (!file_exists($settings_dir)) {
	  wp_mkdir_p($settings_dir);
	}
	
	$result = file_put_contents(
		WP_SFTP_DEPLOYER_SETTINGS_FILE,
		json_encode($settings, JSON_PRETTY_PRINT)
	);
	
	if ($result === false) {
	  $this->logger->log('Failed to write settings file', 'error');
	  return false;
	}
	
	chmod(WP_SFTP_DEPLOYER_SETTINGS_FILE, 0600);
	return true;
  }
  
  /**
   * Encrypt a password using WordPress salts
   */
  private function encrypt_password($password) {
	if (!function_exists('openssl_encrypt')) {
	  // Fallback if OpenSSL not available
	  return base64_encode($password);
	}
	
	// Use WordPress salt keys for encryption
	$key = substr(SECURE_AUTH_KEY, 0, 32);
	$iv = substr(NONCE_KEY, 0, 16);
	
	return openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
  }
  
  /**
   * Decrypt a password
   */
  private function decrypt_password($encrypted) {
	if (!function_exists('openssl_decrypt')) {
	  // Fallback if OpenSSL not available
	  return base64_decode($encrypted);
	}
	
	// Use WordPress salt keys for decryption
	$key = substr(SECURE_AUTH_KEY, 0, 32);
	$iv = substr(NONCE_KEY, 0, 16);
	
	return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
  }
  
  /**
   * Get decrypted SFTP credentials when needed
   */
  public function get_decrypted_settings() {
	$settings = $this->get_settings();
	
	// Decrypt the password if it exists
	if (isset($settings['sftp']['password'])) {
	  $settings['sftp']['password'] = $this->decrypt_password($settings['sftp']['password']);
	}
	
	return $settings;
  }

  /**
   * Core deployment logic
   */
  public function deploy($settings) {
	$this->logger->clear_logs();
	$this->logger->log('Log cleared by deploy()');
	$this->logger->log('Starting deployment process');
	
	// Check if test mode is enabled
	$test_mode = isset($settings['test_mode']) && $settings['test_mode'];
	
	if ($test_mode) {
	  $this->logger->log('TEST MODE ENABLED - simulating deployment');
	  // Just simulate success and return
	  return [
		  'success' => true,
		  'message' => __('Deployment completed successfully (TEST MODE)', 'wp-sftp-deployer')
	  ];
	}
	
	try {
	  // Initialize SFTP connection

	  $sftp_config = $settings['sftp'];
	  $sftp = new WP_SFTP_Deployer_SFTP();
	  $sftp->connect($sftp_config);
	  
	  // Initialize zipper
	  $zipper = new WP_SFTP_Deployer_Zipper();
	  
	  // Create deployment package
	  $source_path = $settings['local']['source_path'];
	  $package_file = WP_SFTP_DEPLOYER_TEMP_DIR . 'deploy_' . time() . '.zip';
	  
	  $this->logger->log('Creating deployment package from: ' . $source_path);
	  $zipper->create_package($source_path, $package_file);
	  
	  // Upload package
	  $remote_path = $sftp_config['remote_path'];
	  $remote_package = $remote_path . '/' . basename($package_file);
	  
	  $this->logger->log('Uploading package to: ' . $remote_package);
	  $sftp->upload_file($package_file, $remote_package);
	  
	  // Execute unzip on remote server if possible
	  // This would require SSH execution capability or another way to extract files
	  // For now, we'll just log this step
	  $this->logger->log('Package uploaded - would need server-side extraction');
	  $extract_path = $remote_path; // Or any specific directory you want
	  $this->logger->log('Extracting uploaded package to: ' . $extract_path);
	  $sftp->extract_remote_zip($remote_package, $extract_path, $sftp_config);
	  // Clean up
	  @unlink($package_file);
	  
	  return [
		  'success' => true,
		  'message' => __('Deployment completed successfully', 'wp-sftp-deployer')
	  ];
	  
	} catch (Exception $e) {
	  $this->logger->log('Deployment error: ' . $e->getMessage(), 'error');
	  
	  return [
		  'success' => false,
		  'message' => $e->getMessage()
	  ];
	}
  }
}
