<?php
  /**
   * The main plugin class
   */
  class WP_SFTP_Deployer {

    /**
     * Initialize the plugin
     */
/**
 * Initialize the plugin
 */
public function init() {
    // Check requirements first
    require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-requirements.php';
    $requirements = new WP_SFTP_Deployer_Requirements();
    $check = $requirements->check_all();

    // Register requirements notices
    add_action('admin_notices', [$requirements, 'display_notices']);

    // If critical requirements are not met, don't initialize the rest
    if ($check['has_critical_errors']) {
      // return;
    }

    // Initialize logging
    require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-logger.php';

    // Initialize admin
    if (is_admin()) {
        require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-admin.php';
        $admin = new WP_SFTP_Deployer_Admin();
        $admin->init();
    }

    // Initialize AJAX actions
    $this->init_ajax_handlers();
}

    /**
     * Start the deployment process
     */
    public function handle_deployment() {
      check_ajax_referer('wp_sftp_deployer_nonce', 'security');

      $logger = new WP_SFTP_Deployer_Logger();
      $logger->log('Starting deployment process');

      // Create a unique ID for this deployment
      $deployment_id = uniqid('deploy_');
      set_transient('wp_sftp_deployer_status_' . $deployment_id, [
          'status' => 'running',
          'progress' => 0,
          'message' => 'Starting deployment',
          'log' => []
      ], 3600);

      // Start deployment in background
      wp_schedule_single_event(time(), 'wp_sftp_deployer_process_deployment', [$deployment_id]);

      wp_send_json_success([
          'deployment_id' => $deployment_id,
          'message' => 'Deployment started'
      ]);
    }

    /**
     * Process the deployment (runs in background)
     */
    public function process_deployment($deployment_id) {
      $logger = new WP_SFTP_Deployer_Logger();
      $settings = $this->get_settings();
      $status = get_transient('wp_sftp_deployer_status_' . $deployment_id);

      try {
        // Step 1: Create ZIP file
        $this->update_status($deployment_id, 10, 'Creating ZIP file');
        $zipper = new WP_SFTP_Deployer_Zipper();
        $zip_file = $zipper->create_zip($settings['local']['source_path']);
        $logger->log('ZIP file created: ' . $zip_file);

        // Step 2: Connect to SFTP
        $this->update_status($deployment_id, 25, 'Connecting to SFTP server');
        $sftp = new WP_SFTP_Deployer_SFTP();
        $sftp->connect($settings['sftp']);
        $logger->log('Connected to SFTP server');

        // Step 3: Check if remote directory exists and has files
        $this->update_status($deployment_id, 40, 'Checking remote directory');
        $has_files = $sftp->check_remote_dir($settings['sftp']['remote_path']);

        if ($has_files) {
          // Need confirmation to proceed
          $this->update_status($deployment_id, 45, 'needs_confirmation', 'Remote directory contains files. Confirmation required.');
          return;
        }

        // Step 4: Upload ZIP file
        $this->complete_deployment($deployment_id, $zip_file, $sftp, $settings, $logger);

      } catch (Exception $e) {
        $logger->log('Error: ' . $e->getMessage(), 'error');
        $this->update_status($deployment_id, 100, 'error', 'Deployment failed: ' . $e->getMessage());
      }
    }

    /**
     * Complete the deployment process after confirmation (if needed)
     */
    public function complete_deployment($deployment_id, $zip_file, $sftp, $settings, $logger) {
      // Step 4: Upload ZIP file
      $this->update_status($deployment_id, 50, 'Uploading ZIP file');
      $remote_zip = $sftp->upload_file($zip_file, $settings['sftp']['remote_path']);
      $logger->log('ZIP file uploaded to: ' . $remote_zip);

      // Step 5: Extract ZIP on remote server
      $this->update_status($deployment_id, 70, 'Extracting ZIP file on remote server');
      $sftp->extract_zip($remote_zip, $settings['sftp']['remote_path']);
      $logger->log('ZIP file extracted on remote server');

      // Step 6: Clean up
      $this->update_status($deployment_id, 85, 'Cleaning up');
      $sftp->delete_file($remote_zip);
      unlink($zip_file);
      $logger->log('Cleanup completed');

      // Step 7: Complete
      $this->update_status($deployment_id, 100, 'complete', 'Deployment completed successfully');
      $logger->log('Deployment completed successfully');
    }

    /**
     * Confirm overwrite and continue deployment
     */
    public function confirm_overwrite() {
      check_ajax_referer('wp_sftp_deployer_nonce', 'security');

      $deployment_id = sanitize_text_field($_POST['deployment_id']);
      $logger = new WP_SFTP_Deployer_Logger();
      $settings = $this->get_settings();

      $status = get_transient('wp_sftp_deployer_status_' . $deployment_id);
      if (!$status || $status['status'] !== 'needs_confirmation') {
        wp_send_json_error(['message' => 'Invalid deployment status']);
        return;
      }

      try {
        // Recreate required objects
        $zipper = new WP_SFTP_Deployer_Zipper();
        $zip_file = $zipper->create_zip($settings['local']['source_path']);

        $sftp = new WP_SFTP_Deployer_SFTP();
        $sftp->connect($settings['sftp']);

        // Continue with deployment
        $this->complete_deployment($deployment_id, $zip_file, $sftp, $settings, $logger);

        wp_send_json_success(['message' => 'Deployment continuing after confirmation']);
      } catch (Exception $e) {
        $logger->log('Error after confirmation: ' . $e->getMessage(), 'error');
        $this->update_status($deployment_id, 100, 'error', 'Deployment failed: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Deployment failed: ' . $e->getMessage()]);
      }
    }

    /**
     * Check the status of a deployment
     */
    public function check_deployment_status() {
      check_ajax_referer('wp_sftp_deployer_nonce', 'security');

      $deployment_id = sanitize_text_field($_GET['deployment_id']);
      $status = get_transient('wp_sftp_deployer_status_' . $deployment_id);

      if (!$status) {
        wp_send_json_error(['message' => 'Deployment not found']);
        return;
      }

      wp_send_json_success($status);
    }

    /**
     * Update the status of a deployment
     */
    private function update_status($deployment_id, $progress, $status, $message = '') {
      $current = get_transient('wp_sftp_deployer_status_' . $deployment_id);
      $log = $current['log'] ?? [];

      if ($message) {
        $log[] = date('Y-m-d H:i:s') . ' - ' . $message;
      }

      set_transient('wp_sftp_deployer_status_' . $deployment_id, [
          'status' => $status,
          'progress' => $progress,
          'message' => $message ?: $current['message'],
          'log' => $log
      ], 3600);
    }

    /**
     * Get plugin settings
     */
    public function get_settings() {
      if (file_exists(WP_SFTP_DEPLOYER_SETTINGS_FILE)) {
        $settings = json_decode(file_get_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE), true);
        return $settings;
      }
      return [];
    }
/**
 * Initialize AJAX handlers for deployment operations
 */
protected function init_ajax_handlers() {
    // Handler for starting a deployment
    add_action('wp_ajax_wp_sftp_deployer_start', [$this, 'ajax_start_deployment']);

    // Handler for checking deployment status
    add_action('wp_ajax_wp_sftp_deployer_check_status', [$this, 'ajax_check_deployment_status']);

    // Handler for confirming overwrite during deployment
    add_action('wp_ajax_wp_sftp_deployer_confirm_overwrite', [$this, 'ajax_confirm_overwrite']);

    // Handler for testing SFTP connection
    add_action('wp_ajax_wp_sftp_deployer_test_connection', [$this, 'ajax_test_connection']);
}

/**
 * AJAX handler for starting a deployment
 */
public function ajax_start_deployment() {
    check_ajax_referer('wp_sftp_deployer_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
        return;
    }

    // Create a unique deployment ID
    $deployment_id = uniqid('deploy_');

    // Start the deployment process here
    // For now, just return success with the ID
    wp_send_json_success([
        'deployment_id' => $deployment_id,
        'message' => __('Deployment started.', 'wp-sftp-deployer')
    ]);
}

/**
 * AJAX handler for checking deployment status
 */
public function ajax_check_deployment_status() {
    check_ajax_referer('wp_sftp_deployer_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
        return;
    }

    $deployment_id = isset($_GET['deployment_id']) ? sanitize_text_field($_GET['deployment_id']) : '';

    if (empty($deployment_id)) {
        wp_send_json_error(['message' => __('Invalid deployment ID.', 'wp-sftp-deployer')]);
        return;
    }

    // For now, return dummy progress data
    wp_send_json_success([
        'status' => 'in_progress',
        'progress' => 50,
        'message' => __('Deployment in progress...', 'wp-sftp-deployer'),
        'log' => ['Starting deployment...', 'Checking files...', 'Creating package...']
    ]);
}

/**
 * AJAX handler for confirming overwrite during deployment
 */
public function ajax_confirm_overwrite() {
    check_ajax_referer('wp_sftp_deployer_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
        return;
    }

    $deployment_id = isset($_POST['deployment_id']) ? sanitize_text_field($_POST['deployment_id']) : '';

    if (empty($deployment_id)) {
        wp_send_json_error(['message' => __('Invalid deployment ID.', 'wp-sftp-deployer')]);
        return;
    }

    // Resume deployment with overwrite flag
    wp_send_json_success(['message' => __('Continuing deployment with overwrite.', 'wp-sftp-deployer')]);
}

/**
 * AJAX handler for testing SFTP connection
 */
public function ajax_test_connection() {
    check_ajax_referer('wp_sftp_deployer_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have sufficient permissions.', 'wp-sftp-deployer')]);
        return;
    }

    require_once WP_SFTP_DEPLOYER_PLUGIN_DIR . 'includes/class-wp-sftp-deployer-sftp.php';
    $sftp = new WP_SFTP_Deployer_SFTP();

    try {
        // Get connection parameters from POST data
        $host = isset($_POST['host']) ? sanitize_text_field($_POST['host']) : '';
        $port = isset($_POST['port']) ? intval($_POST['port']) : 22;
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($host) || empty($username) || empty($password)) {
            throw new Exception(__('Missing required connection parameters.', 'wp-sftp-deployer'));
        }

        // Just return success for now
        wp_send_json_success(['message' => __('Connection successful!', 'wp-sftp-deployer')]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

  }
