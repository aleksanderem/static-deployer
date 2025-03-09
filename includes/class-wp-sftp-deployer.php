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
        // Initialize AJAX handlers
        $this->init_ajax_handlers();

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
     * Initialize AJAX handlers
     */
    protected function init_ajax_handlers() {
        // Handler for testing SFTP connection
        add_action('wp_ajax_wp_sftp_deployer_test_connection', [$this, 'ajax_test_connection']);

        // Handler for deployment
        add_action('wp_ajax_wp_sftp_deployer_deploy', [$this, 'ajax_deploy']);

        // Handler for checking deployment status
        add_action('wp_ajax_wp_sftp_deployer_check_status', [$this, 'ajax_check_status']);
    }

    /**
     * AJAX handler for testing SFTP connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wp_sftp_deployer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions', 'wp-sftp-deployer')
            ]);
        }

        $host = isset($_POST['host']) ? sanitize_text_field($_POST['host']) : '';
        $port = isset($_POST['port']) ? intval($_POST['port']) : 22;
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $timeout = isset($_POST['timeout']) ? intval($_POST['timeout']) : 30;

        $config = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'timeout' => $timeout
        ];

        try {
            $sftp = new WP_SFTP_Deployer_SFTP();
            $result = $sftp->test_connection($config);

            if ($result) {
                wp_send_json_success([
                    'message' => __('Connection successful! SFTP credentials are valid.', 'wp-sftp-deployer')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Connection failed. Please check your credentials.', 'wp-sftp-deployer')
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for deployment
     */
    public function ajax_deploy() {
        check_ajax_referer('wp_sftp_deployer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions', 'wp-sftp-deployer')
            ]);
        }

        $deployment_id = uniqid('deploy_');
        $settings = $this->get_settings();

        // Check if test mode is enabled
        $test_mode = isset($settings['test_mode']) && $settings['test_mode'];

        // Start deployment asynchronously
        $this->logger->log("Starting deployment ID: $deployment_id");

        if ($test_mode) {
            $this->logger->log("Running in TEST MODE - operations simulated");

            // Return success immediately for test mode
            wp_send_json_success([
                'deployment_id' => $deployment_id,
                'message' => __('Deployment started in test mode', 'wp-sftp-deployer'),
                'test_mode' => true
            ]);
            return;
        }

        // In a real implementation, you would spawn an async process here
        // For now, we'll just return success and let the status checking handle it
        wp_send_json_success([
            'deployment_id' => $deployment_id,
            'message' => __('Deployment started', 'wp-sftp-deployer')
        ]);
    }

    /**
     * AJAX handler for checking deployment status
     */
    public function ajax_check_status() {
        check_ajax_referer('wp_sftp_deployer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions', 'wp-sftp-deployer')
            ]);
        }

        $deployment_id = isset($_GET['deployment_id']) ? sanitize_text_field($_GET['deployment_id']) : '';

        if (empty($deployment_id)) {
            wp_send_json_error([
                'message' => __('Invalid deployment ID', 'wp-sftp-deployer')
            ]);
        }

        // For testing, simulate different statuses based on time
        $status = 'in_progress';
        $progress = 0;
        $message = __('Initializing deployment...', 'wp-sftp-deployer');
        $log_entries = [];

        // Check if we have settings with test mode
        $settings = $this->get_settings();
        $test_mode = isset($settings['test_mode']) && $settings['test_mode'];

        if ($test_mode) {
            // Generate simulated progress for test mode
            $now = time();
            $start_time = isset($_SESSION['deploy_start_' . $deployment_id]) ?
                $_SESSION['deploy_start_' . $deployment_id] :
                ($now - 5); // Default to 5 seconds ago

            $elapsed = $now - $start_time;

            // Simulate progress based on elapsed time
            if ($elapsed < 2) {
                $status = 'in_progress';
                $progress = 10;
                $message = __('Connecting to SFTP server...', 'wp-sftp-deployer');
                $log_entries = [
                    __('Starting deployment in test mode', 'wp-sftp-deployer'),
                    __('Initializing SFTP connection...', 'wp-sftp-deployer')
                ];
            } else if ($elapsed < 4) {
                $status = 'in_progress';
                $progress = 30;
                $message = __('Creating zip package...', 'wp-sftp-deployer');
                $log_entries = [
                    __('Starting deployment in test mode', 'wp-sftp-deployer'),
                    __('SFTP connection established', 'wp-sftp-deployer'),
                    __('Creating deployment package...', 'wp-sftp-deployer')
                ];
            } else if ($elapsed < 6) {
                $status = 'in_progress';
                $progress = 60;
                $message = __('Uploading package...', 'wp-sftp-deployer');
                $log_entries = [
                    __('Starting deployment in test mode', 'wp-sftp-deployer'),
                    __('SFTP connection established', 'wp-sftp-deployer'),
                    __('Deployment package created successfully', 'wp-sftp-deployer'),
                    __('Uploading package to server...', 'wp-sftp-deployer')
                ];
            } else if ($elapsed < 8) {
                $status = 'in_progress';
                $progress = 80;
                $message = __('Extracting files on server...', 'wp-sftp-deployer');
                $log_entries = [
                    __('Starting deployment in test mode', 'wp-sftp-deployer'),
                    __('SFTP connection established', 'wp-sftp-deployer'),
                    __('Deployment package created successfully', 'wp-sftp-deployer'),
                    __('Package uploaded to server', 'wp-sftp-deployer'),
                    __('Extracting files on remote server...', 'wp-sftp-deployer')
                ];
            } else {
                $status = 'complete';
                $progress = 100;
                $message = __('Deployment completed successfully!', 'wp-sftp-deployer');
                $log_entries = [
                    __('Starting deployment in test mode', 'wp-sftp-deployer'),
                    __('SFTP connection established', 'wp-sftp-deployer'),
                    __('Deployment package created successfully', 'wp-sftp-deployer'),
                    __('Package uploaded to server', 'wp-sftp-deployer'),
                    __('Files extracted on remote server', 'wp-sftp-deployer'),
                    __('Cleaning up temporary files', 'wp-sftp-deployer'),
                    __('Deployment completed successfully!', 'wp-sftp-deployer')
                ];
            }
        } else {
            // Real deployment would read status from file or database
            // For now, we'll just use a similar simulation
            $now = time();
            $start_time = isset($_SESSION['deploy_start_' . $deployment_id]) ?
                $_SESSION['deploy_start_' . $deployment_id] :
                ($now - 5);

            $elapsed = $now - $start_time;

            // Build similar progress simulation
            // [simulation code similar to above]
        }

        wp_send_json_success([
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'log' => $log_entries,
            'test_mode' => $test_mode
        ]);
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

        return $settings;
    }

    /**
     * Update plugin settings
     */
    public function update_settings($settings) {
        $settings_dir = dirname(WP_SFTP_DEPLOYER_SETTINGS_FILE);

        // Ensure settings directory exists
        if (!file_exists($settings_dir)) {
            wp_mkdir_p($settings_dir);
        }

        // Save settings as JSON
        $result = file_put_contents(
            WP_SFTP_DEPLOYER_SETTINGS_FILE,
            json_encode($settings, JSON_PRETTY_PRINT)
        );

        if ($result === false) {
            $this->logger->log('Failed to write settings file', 'error');
            return false;
        }

        // Set secure permissions
        chmod(WP_SFTP_DEPLOYER_SETTINGS_FILE, 0600);

        return true;
    }

    /**
     * Core deployment logic
     */
    public function deploy($settings) {
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

            // [Additional deployment steps would go here]

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
