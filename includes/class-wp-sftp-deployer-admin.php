<?php
/**
 * Admin functionality
 */
class WP_SFTP_Deployer_Admin {
  
  /**
   * Initialize admin hooks
   */
  public function init() {
	// Add admin menu
	add_action('admin_menu', [$this, 'add_admin_menu']);
	
	// Register admin scripts and styles
	add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
	
	// Handle form submissions
	add_action('admin_init', [$this, 'handle_form_submissions']);
	
	// Add AJAX handlers
	add_action('wp_ajax_wp_sftp_deployer_test_connection', [$this, 'test_sftp_connection']);
	add_action('wp_ajax_wp_sftp_deployer_start', [$this, 'start_deployment']);
	add_action('wp_ajax_wp_sftp_deployer_check_status', [$this, 'check_deployment_status']);
	add_action('wp_ajax_wp_sftp_deployer_confirm_overwrite', [$this, 'confirm_overwrite']);
	
	// Add background process handler
	add_action('wp_sftp_deployer_process', [$this, 'process_deployment']);
	add_action('wp_ajax_wp_sftp_deployer_get_logs', array($this, 'ajax_get_logs'));
	
  }
  
  /**
   * Add admin menu items
   */
  public function add_admin_menu() {
	add_menu_page(
		__('SFTP Deployer', 'wp-sftp-deployer'),
		__('SFTP Deployer', 'wp-sftp-deployer'),
		'manage_options',
		'wp-sftp-deployer',
		[$this, 'render_deployment_page'],
		'dashicons-migrate',
		100
	);
	
	add_submenu_page(
		'wp-sftp-deployer',
		__('Deploy', 'wp-sftp-deployer'),
		__('Deploy', 'wp-sftp-deployer'),
		'manage_options',
		'wp-sftp-deployer',
		[$this, 'render_deployment_page']
	);
	
	add_submenu_page(
		'wp-sftp-deployer',
		__('Settings', 'wp-sftp-deployer'),
		__('Settings', 'wp-sftp-deployer'),
		'manage_options',
		'wp-sftp-deployer-settings',
		[$this, 'render_settings_page']
	);
	
	add_submenu_page(
		'wp-sftp-deployer',
		__('Logs', 'wp-sftp-deployer'),
		__('Logs', 'wp-sftp-deployer'),
		'manage_options',
		'wp-sftp-deployer-logs',
		[$this, 'render_logs_page']
	);
  }
  /**
   * Register admin scripts and styles
   */
  public function register_admin_assets($hook) {
	$admin_pages = [
		'toplevel_page_wp-sftp-deployer',
		'sftp-deployer_page_wp-sftp-deployer-settings',
		'sftp-deployer_page_wp-sftp-deployer-logs'
	];
	
	if (!in_array($hook, $admin_pages)) {
	  return;
	}
	
	wp_enqueue_style(
		'wp-sftp-deployer-admin',
		WP_SFTP_DEPLOYER_PLUGIN_URL . 'assets/css/admin.css',
		[],
		WP_SFTP_DEPLOYER_VERSION
	);
	
	wp_enqueue_script(
		'wp-sftp-deployer-admin',
		WP_SFTP_DEPLOYER_PLUGIN_URL . 'assets/js/admin.js',
		['jquery'],
		WP_SFTP_DEPLOYER_VERSION,
		true
	);
	
	wp_localize_script('wp-sftp-deployer-admin', 'wpSftpDeployer', [
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('wp_sftp_deployer_nonce'),
		'i18n' => [
			'confirmOverwrite' => __('Files exist on the remote server. Do you want to continue and overwrite them?', 'wp-sftp-deployer'),
			'deploymentStarted' => __('Deployment started...', 'wp-sftp-deployer'),
			'deploymentComplete' => __('Deployment completed successfully!', 'wp-sftp-deployer'),
			'deploymentFailed' => __('Deployment failed. Please check logs.', 'wp-sftp-deployer')
		]
	]);
  }
  
  /**
   * Handle form submissions
   */
  public function handle_form_submissions() {
	if (!isset($_POST['wp_sftp_deployer_save_settings'])) {
	  return;
	}
	
	check_admin_referer('wp_sftp_deployer_settings', 'wp_sftp_deployer_settings_nonce');
	
	if (!current_user_can('manage_options')) {
	  wp_die(__('You do not have sufficient permissions to access this page.', 'wp-sftp-deployer'));
	}
	
	$settings = [
		'sftp' => [
			'host' => sanitize_text_field($_POST['sftp_host']),
			'port' => intval($_POST['sftp_port']),
			'username' => sanitize_text_field($_POST['sftp_username']),
			'password' => isset($_POST['sftp_password']) && !empty($_POST['sftp_password'])
				? $_POST['sftp_password']
				: (isset($_POST['use_existing_password']) ? $this->get_existing_password() : ''),
			'remote_path' => sanitize_text_field($_POST['sftp_remote_path']),
			'timeout' => intval($_POST['sftp_timeout'])
		],
		'local' => [
			'source_path' => sanitize_text_field($_POST['local_source_path']),
		],
		'logging' => [
			'enabled' => isset($_POST['logging_enabled']),
			'custom_path' => sanitize_text_field($_POST['logging_custom_path']),
			'log_level' => sanitize_text_field($_POST['logging_level'])
		],
		'test_mode' => isset($_POST['test_mode'])
	];
	
	$deployer = new WP_SFTP_Deployer();
	$deployer->update_settings($settings);
	
	add_settings_error(
		'wp_sftp_deployer_settings',
		'settings_updated',
		__('Settings saved successfully.', 'wp-sftp-deployer'),
		'updated'
	);
  }
  
  /**
   * Get the existing password from settings
   */
  private function get_existing_password() {
	$settings = $this->get_settings();
	return isset($settings['sftp']['password']) ? $settings['sftp']['password'] : '';
  }
  
  /**
   * Get plugin settings
   */
  private function get_settings() {
	$deployer = new WP_SFTP_Deployer();
	return $deployer->get_settings();
  }
  
  /**
   * Render the deployment page
   */
  public function render_deployment_page() {
	$settings = $this->get_settings();
	
	// Check if settings are configured
	$is_configured = !empty($settings['sftp']['host']) &&
		!empty($settings['sftp']['username']) &&
		!empty($settings['sftp']['password']) &&
		!empty($settings['sftp']['remote_path']) &&
		!empty($settings['local']['source_path']);
	
	include WP_SFTP_DEPLOYER_PLUGIN_DIR . 'templates/deployment-page.php';
  }
  
  /**
   * Render the settings page
   */
  public function render_settings_page() {
	$settings = $this->get_settings();
	
	// Set defaults if needed
	$sftp = isset($settings['sftp']) ? $settings['sftp'] : [];
	$sftp = wp_parse_args($sftp, [
		'host' => '',
		'port' => 22,
		'username' => '',
		'password' => '',
		'remote_path' => '',
		'timeout' => 30
	]);
	
	$local = isset($settings['local']) ? $settings['local'] : [];
	$local = wp_parse_args($local, [
		'source_path' => empty($local['source_path']) ? ABSPATH : $local['source_path'],
	]);
	
	$logging = isset($settings['logging']) ? $settings['logging'] : [];
	$logging = wp_parse_args($logging, [
		'enabled' => true,
		'custom_path' => '',
		'log_level' => 'info'
	]);
	
	$test_mode = isset($settings['test_mode']) ? $settings['test_mode'] : false;
	
	include WP_SFTP_DEPLOYER_PLUGIN_DIR . 'templates/settings-page.php';
  }
  
  /**
   * Render the logs page
   */
  public function render_logs_page() {
	$logger = new WP_SFTP_Deployer_Logger();
	$logs = $logger->get_logs();
	
	include WP_SFTP_DEPLOYER_PLUGIN_DIR . 'templates/logs-page.php';
  }
  
  /**
   * Test SFTP connection via AJAX
   */
  public function test_sftp_connection() {
	check_ajax_referer('wp_sftp_deployer_nonce', 'security');
	
	if (!current_user_can('manage_options')) {
	  wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
	  return;
	}
	
	// Get form data
	$host = sanitize_text_field($_POST['host']);
	$port = intval($_POST['port']);
	$username = sanitize_text_field($_POST['username']);
	$use_existing = isset($_POST['use_existing']) && $_POST['use_existing'] === 'true';
	
	// If password is empty, use existing
	if (empty($_POST['password'])) {
	  $use_existing = true;
	}
	
	if ($use_existing) {
	  $settings = $this->get_settings();
	  $password = $settings['sftp']['password'] ?? '';
	} else {
	  $password = $_POST['password']; // Don't sanitize password
	}
	
	if (empty($host) || empty($username) || empty($password)) {
	  wp_send_json_error(['message' => __('Host, username and password are required.', 'wp-sftp-deployer')]);
	  return;
	}
	
	$logger = new WP_SFTP_Deployer_Logger();
	
	try {
	  // Initialize SFTP class
	  $sftp = new WP_SFTP_Deployer_SFTP();
	  
	  // Test connection
	  $result = $sftp->test_connection([
		  'host' => $host,
		  'port' => $port,
		  'username' => $username,
		  'password' => $password,
		  'timeout' => 30
	  ]);
	  
	  // Connection successful
	  $logger->log("SFTP connection test successful: $username@$host:$port");
	  wp_send_json_success(['message' => __('Connection successful!', 'wp-sftp-deployer')]);
	  
	} catch (Exception $e) {
	  $logger->log('SFTP connection test failed: ' . $e->getMessage(), 'error');
	  wp_send_json_error(['message' => __('Connection failed: ', 'wp-sftp-deployer') . $e->getMessage()]);
	}
  }
  
  /**
   * Start deployment process via AJAX
   */
  /**
   * Start deployment process via AJAX
   */
  public function start_deployment() {
	check_ajax_referer('wp_sftp_deployer_nonce', 'security');
	
	if (!current_user_can('manage_options')) {
	  wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
	  return;
	}
	
	// Generate a unique deployment ID
	$deployment_id = uniqid('deploy_');
	
	// Initialize status
	set_transient('wp_sftp_deployer_status_' . $deployment_id, [
		'status' => 'started',
		'progress' => 0,
		'message' => __('Deployment initiated', 'wp-sftp-deployer'),
		'log' => [__('Starting deployment process', 'wp-sftp-deployer')]
	], 3600);
	
	// Get settings
	$settings = $this->get_settings();
	
	// Store deployment ID in session for status checking
	$_SESSION['deploy_start_' . $deployment_id] = time();
	
	// Add the deployment ID to settings so we can track progress
	$settings['deployment_id'] = $deployment_id;
	
	// Zamiast planować zadanie, wykonaj je bezpośrednio (tylko do debugowania)
	// wp_schedule_single_event(time(), 'wp_sftp_deployer_process', [$settings]);
	$this->process_deployment($settings);
	
	wp_send_json_success(['deployment_id' => $deployment_id]);
  }

  
  /**
   * Process deployment in background
   */
  public function process_deployment($settings) {
	$deployment_id = $settings['deployment_id'];
	$logger = new WP_SFTP_Deployer_Logger();
	
	$logger->log("Starting process_deployment with ID: $deployment_id");
	
	try {
	  // Update status
	  $logger->log("Updating deployment status to 10%");
	  $this->update_deployment_status($deployment_id, [
		  'progress' => 10,
		  'message' => __('Starting deployment...', 'wp-sftp-deployer'),
		  'log' => [__('Processing deployment request', 'wp-sftp-deployer')]
	  ]);
	  
	  // Initialize the deployer
	  $logger->log("Initializing deployer");
	  $deployer = new WP_SFTP_Deployer();
	  
	  // Call the deploy method with settings
	  $logger->log("Calling deploy method");
	  $result = $deployer->deploy($settings);
	  $logger->log("Deploy result: " . print_r($result, true));
	  
	  if ($result && isset($result['success']) && $result['success']) {
		// Update status to complete
		$logger->log("Deployment successful, updating status to complete");
		$this->update_deployment_status($deployment_id, [
			'status' => 'complete',
			'progress' => 100,
			'message' => $result['message'],
			'log' => [__('Deployment process finished', 'wp-sftp-deployer')]
		]);
	  } else {
		// Update status to error
		$logger->log("Deployment failed, updating status to error");
		$this->update_deployment_status($deployment_id, [
			'status' => 'error',
			'progress' => 0,
			'message' => isset($result['message']) ? $result['message'] : __('Deployment failed', 'wp-sftp-deployer'),
			'log' => [__('Error during deployment process', 'wp-sftp-deployer')]
		]);
	  }
	} catch (Exception $e) {
	  $logger->log('Deployment error: ' . $e->getMessage(), 'error');
	  
	  // Update status to error
	  $this->update_deployment_status($deployment_id, [
		  'status' => 'error',
		  'progress' => 0,
		  'message' => $e->getMessage(),
		  'log' => [__('Error: ', 'wp-sftp-deployer') . $e->getMessage()]
	  ]);
	}
	
	$logger->log("process_deployment finished");
  }
  /**
   * Update deployment status
   */
  private function update_deployment_status($deployment_id, $new_data) {
	$current_status = get_transient('wp_sftp_deployer_status_' . $deployment_id) ?: [
		'status' => 'started',
		'progress' => 0,
		'message' => '',
		'log' => []
	];
	
	// Merge log entries
	if (isset($new_data['log']) && is_array($new_data['log'])) {
	  if (!isset($current_status['log'])) {
		$current_status['log'] = [];
	  }
	  $current_status['log'] = array_merge($current_status['log'], $new_data['log']);
	}
	
	// Update other fields
	foreach ($new_data as $key => $value) {
	  if ($key !== 'log') {
		$current_status[$key] = $value;
	  }
	}
	
	// Save updated status
	set_transient('wp_sftp_deployer_status_' . $deployment_id, $current_status, 3600);
  }
  
  /**
   * Check deployment status via AJAX
   */
  public function check_deployment_status() {
	check_ajax_referer('wp_sftp_deployer_nonce', 'security');
	
	if (!isset($_GET['deployment_id'])) {
	  wp_send_json_error(['message' => __('Deployment ID is required.', 'wp-sftp-deployer')]);
	  return;
	}
	
	$deployment_id = sanitize_text_field($_GET['deployment_id']);
	$status = get_transient('wp_sftp_deployer_status_' . $deployment_id);
	$now = time();
	$start_time = get_transient('wp_sftp_deployer_start_' . $deployment_id) ?: ($now - 5);
	
	if (!$status) {
	  wp_send_json_error(['message' => __('Deployment not found or expired.', 'wp-sftp-deployer')]);
	  return;
	}
	
	wp_send_json_success($status);
  }
  
  /**
   * Confirm file overwrite via AJAX
   */
  public function confirm_overwrite() {
	check_ajax_referer('wp_sftp_deployer_nonce', 'security');
	
	if (!isset($_POST['deployment_id'])) {
	  wp_send_json_error(['message' => __('Deployment ID is required.', 'wp-sftp-deployer')]);
	  return;
	}
	
	$deployment_id = sanitize_text_field($_POST['deployment_id']);
	$status = get_transient('wp_sftp_deployer_status_' . $deployment_id);
	
	if (!$status) {
	  wp_send_json_error(['message' => __('Deployment not found or expired.', 'wp-sftp-deployer')]);
	  return;
	}
	
	// Update status to continue deployment
	$status['status'] = 'in_progress';
	$status['message'] = __('Continuing deployment after confirmation', 'wp-sftp-deployer');
	$status['log'][] = __('User confirmed file overwrite', 'wp-sftp-deployer');
	
	set_transient('wp_sftp_deployer_status_' . $deployment_id, $status, 3600);
	
	wp_send_json_success();
  }
  /**
   * AJAX handler for fetching logs
   */
  public function ajax_get_logs() {
	$logger = new WP_SFTP_Deployer_Logger();
	$logs = $logger->get_logs(100); // Get latest 100 log entries
	
	wp_send_json_success(array(
		'logs' => $logs
	));
  }
}
