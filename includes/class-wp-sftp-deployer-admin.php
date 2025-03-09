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
      add_action('wp_ajax_wp_sftp_deployer_test_connection', [$this, 'test_sftp_connection']);
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
                  ? $_POST['sftp_password'] // Don't sanitize password to allow special chars
                  : $this->get_existing_password(),
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
          ]
      ];

      file_put_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
      chmod(WP_SFTP_DEPLOYER_SETTINGS_FILE, 0600);

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
      if (file_exists(WP_SFTP_DEPLOYER_SETTINGS_FILE)) {
        $settings = json_decode(file_get_contents(WP_SFTP_DEPLOYER_SETTINGS_FILE), true);
        return $settings;
      }
      return [];
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
          'source_path' => '',
      ]);

      $local = isset($settings['local']) ? $settings['local'] : [];
      $local = wp_parse_args($local, [
          // Automatycznie ustaw ścieżkę do katalogu WordPress
          'source_path' => empty($local['source_path']) ? ABSPATH : $local['source_path'],
      ]);
      $logging = wp_parse_args($logging, [
          'enabled' => true,
          'custom_path' => '',
          'log_level' => 'info'
      ]);

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

         // Pobierz dane z formularza
         $host = sanitize_text_field($_POST['host']);
         $port = intval($_POST['port']);
         $username = sanitize_text_field($_POST['username']);
         $password = $_POST['password']; // Nie sanityzuj hasła aby zachować znaki specjalne

         if (empty($host) || empty($username) || empty($password)) {
             wp_send_json_error(['message' => __('Host, username and password are required.', 'wp-sftp-deployer')]);
             return;
         }

         $logger = new WP_SFTP_Deployer_Logger();

         try {
             // Inicjalizacja klasy SFTP
             $sftp = new WP_SFTP_Deployer_SFTP();

             // Próba połączenia
             $result = $sftp->test_connection([
                 'host' => $host,
                 'port' => $port,
                 'username' => $username,
                 'password' => $password
             ]);

             // Jeśli doszliśmy tutaj, połączenie się powiodło
             $logger->log("SFTP connection test successful: $username@$host:$port");
             wp_send_json_success(['message' => __('Connection successful!', 'wp-sftp-deployer')]);

         } catch (Exception $e) {
             $logger->log('SFTP connection test failed: ' . $e->getMessage(), 'error');
             wp_send_json_error(['message' => __('Connection failed: ', 'wp-sftp-deployer') . $e->getMessage()]);
         }
     }
  }
