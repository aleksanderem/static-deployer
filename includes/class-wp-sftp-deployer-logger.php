<?php
  /**
   * Logging functionality
   */
  class WP_SFTP_Deployer_Logger {
    private $settings;
    
    public function __construct() {
      $this->settings = $this->get_settings();
    }
    
    /**
     * Log a message
     */
    public function log($message, $level = 'info') {
      if (!isset($this->settings['logging']['enabled']) || !$this->settings['logging']['enabled']) {
        return;
      }
      
      // Only log messages with level higher than or equal to the configured level
      $log_levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
      $configured_level = isset($this->settings['logging']['log_level']) ? $this->settings['logging']['log_level'] : 'info';
      
      if ($log_levels[$level] < $log_levels[$configured_level]) {
        return;
      }
      
      $timestamp = date('Y-m-d H:i:s');
      $log_message = "[$timestamp][$level] $message\n";
      
      // Log to internal log file
      $internal_log_file = WP_SFTP_DEPLOYER_LOG_DIR . 'deployer.log';
      file_put_contents($internal_log_file, $log_message, FILE_APPEND);
      
      // Log to custom path if configured
      if (!empty($this->settings['logging']['custom_path'])) {
        $custom_log_file = rtrim($this->settings['logging']['custom_path'], '/') . '/deployer.log';
        $custom_log_dir = dirname($custom_log_file);
        
        if (file_exists($custom_log_dir) && is_writable($custom_log_dir)) {
          file_put_contents($custom_log_file, $log_message, FILE_APPEND);
        }
      }
    }
    
    /**
     * Get log entries
     */
    public function get_logs($count = 100) {
      $log_file = WP_SFTP_DEPLOYER_LOG_DIR . 'deployer.log';
      
      if (!file_exists($log_file)) {
        return [];
      }
      
      $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $logs = array_reverse($logs);
      
      return array_slice($logs, 0, $count);
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
	 * Clear log file contents
	 *
	 * @return boolean True if logs were successfully cleared
	 */
	public function clear_logs() {
	  $log_file = WP_SFTP_DEPLOYER_LOG_DIR . 'deployer.log';
	  $success = true;
	  
	  // Clear the main log file
	  if (file_exists($log_file)) {
		$success = file_put_contents($log_file, '') !== false;
	  }
	  
	  // Clear custom log file if configured
	  if (!empty($this->settings['logging']['custom_path'])) {
		$custom_log_file = rtrim($this->settings['logging']['custom_path'], '/') . '/deployer.log';
		
		if (file_exists($custom_log_file) && is_writable($custom_log_file)) {
		  file_put_contents($custom_log_file, '');
		}
	  }
	  
	  // Add entry about log clearing
	  if ($success) {
		$this->log('Log file has been cleared', 'info');
	  }
	  
	  return $success;
	}
  }
