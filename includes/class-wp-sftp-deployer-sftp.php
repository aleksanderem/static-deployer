<?php
  /**
   * SFTP functionality
   */
  class WP_SFTP_Deployer_SFTP {
    private $connection;
    private $sftp;
    private $logger;
    private $using_phpseclib = false;

    public function __construct() {
      $this->logger = new WP_SFTP_Deployer_Logger();

      // Check if SSH2 extension is loaded
      $this->using_phpseclib = !extension_loaded('ssh2');
      if (!extension_loaded('ssh2')) {
        $this->logger->log('PHP SSH2 extension is not installed; switching to phpseclib', 'error');
        // throw new Exception('PHP SSH2 extension is not installed. Please contact your hosting provider.');
      }
    }
    private function connect_with_phpseclib($config) {
        $this->logger->log('Using phpseclib for SFTP connection');

        try {
            // Użyj biblioteki phpseclib
            $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port'], $config['timeout']);

            if (!$sftp->login($config['username'], $config['password'])) {
                throw new Exception("SFTP authentication failed for user: {$config['username']}");
            }

            $this->sftp = $sftp;
            $this->connection = true;

            return true;
        } catch (Exception $e) {
            $this->logger->log('SFTP connection error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    /**
     * Connect to the SFTP server
     */
    private function connect_with_ssh2($config) {
        $this->logger->log('Connecting to SFTP server: ' . $config['host'] . ':' . $config['port']);
        // Connect to server
        $this->connection = @ssh2_connect($config['host'], $config['port']);
        if (!$this->connection) {
          $this->logger->log('Could not connect to SFTP server', 'error');
          throw new Exception("Could not connect to SFTP server. Please check host and port settings.");
        }

        // Authenticate
        if (!@ssh2_auth_password($this->connection, $config['username'], $config['password'])) {
          $this->logger->log('SFTP authentication failed for user: ' . $config['username'], 'error');
          throw new Exception("SFTP authentication failed. Please check username and password.");
        }

        // Initialize SFTP subsystem
        $this->sftp = @ssh2_sftp($this->connection);
        if (!$this->sftp) {
          $this->logger->log('Could not initialize SFTP subsystem', 'error');
          throw new Exception("Could not initialize SFTP subsystem.");
        }

        $this->logger->log('Successfully connected to SFTP server');
        return true;
    }
    public function connect($config) {
       if ($this->using_phpseclib) {
            return $this->connect_with_phpseclib($config);
        } else {
            return $this->connect_with_ssh2($config);
        }
    }

    /**
     * Check if remote directory exists and has files
     */
    public function check_remote_dir($remote_path) {
      $sftp_path = 'ssh2.sftp://' . intval($this->sftp) . $remote_path;

      // Check if directory exists
      if (!file_exists($sftp_path)) {
        // Create directory if it doesn't exist
        if (!$this->exec_command('mkdir -p ' . escapeshellarg($remote_path))) {
          $this->logger->log('Failed to create remote directory: ' . $remote_path, 'error');
          throw new Exception("Failed to create remote directory: $remote_path");
        }
        $this->logger->log('Created remote directory: ' . $remote_path);
        return false;
      }

      // Check if directory has files
      $dir_handle = opendir($sftp_path);
      if (!$dir_handle) {
        $this->logger->log('Could not read remote directory: ' . $remote_path, 'error');
        throw new Exception("Could not read remote directory: $remote_path");
      }

      $has_files = false;
      while (($file = readdir($dir_handle)) !== false) {
        if ($file != '.' && $file != '..') {
          $has_files = true;
          break;
        }
      }
      closedir($dir_handle);

      if ($has_files) {
        $this->logger->log('Remote directory contains files: ' . $remote_path);
      } else {
        $this->logger->log('Remote directory is empty: ' . $remote_path);
      }

      return $has_files;
    }

    /**
     * Upload a file to the SFTP server
     */
    public function upload_file($local_file, $remote_path) {
      $remote_file = rtrim($remote_path, '/') . '/' . basename($local_file);
      $sftp_remote_file = 'ssh2.sftp://' . intval($this->sftp) . $remote_file;

      $this->logger->log('Uploading file to: ' . $remote_file);

      $content = file_get_contents($local_file);
      if ($content === false) {
        $this->logger->log('Could not read local file: ' . $local_file, 'error');
        throw new Exception("Could not read local file: $local_file");
      }

      $stream = @fopen($sftp_remote_file, 'w');
      if (!$stream) {
        $this->logger->log('Could not open remote file for writing: ' . $remote_file, 'error');
        throw new Exception("Could not open remote file for writing: $remote_file");
      }

      if (@fwrite($stream, $content) === false) {
        fclose($stream);
        $this->logger->log('Could not write to remote file: ' . $remote_file, 'error');
        throw new Exception("Could not write to remote file: $remote_file");
      }

      fclose($stream);
      $this->logger->log('File uploaded successfully: ' . $remote_file);

      return $remote_file;
    }

    /**
     * Extract the ZIP file on the remote server
     */
    public function extract_zip($remote_zip, $remote_path) {
      $this->logger->log('Extracting remote ZIP file: ' . $remote_zip);

      $command = 'cd ' . escapeshellarg($remote_path) . ' && unzip -o ' . escapeshellarg(basename($remote_zip));
      $result = $this->exec_command($command);

      if (!$result) {
        $this->logger->log('Failed to extract ZIP file: ' . $remote_zip, 'error');
        throw new Exception("Failed to extract ZIP file on remote server. Make sure unzip is installed.");
      }

      $this->logger->log('ZIP file extracted successfully');
      return true;
    }

    /**
     * Delete a file on the remote server
     */
    /**
     * Delete a file on the remote server
     */
    public function delete_file($remote_file) {
      $this->logger->log('Deleting remote file: ' . $remote_file);

      $sftp_remote_file = 'ssh2.sftp://' . intval($this->sftp) . $remote_file;
      if (!@unlink($sftp_remote_file)) {
        $this->logger->log('Failed to delete remote file: ' . $remote_file, 'error');
        throw new Exception("Failed to delete remote file: $remote_file");
      }

      $this->logger->log('Remote file deleted successfully');
      return true;
    }

    /**
     * Execute a command on the remote server
     */
    private function exec_command($command) {
      $this->logger->log('Executing command: ' . $command);

      $stream = @ssh2_exec($this->connection, $command);
      if (!$stream) {
        $this->logger->log('Failed to execute command: ' . $command, 'error');
        return false;
      }

      stream_set_blocking($stream, true);
      $output = stream_get_contents($stream);
      fclose($stream);

      $this->logger->log('Command output: ' . $output);
      return true;
    }
    /**
     * Test SFTP connection
     */
    public function test_connection($config) {
        $this->logger->log('Testing SFTP connection to: ' . $config['host'] . ':' . $config['port']);

        // Connect to server
        $connection = @ssh2_connect($config['host'], $config['port']);
        if (!$connection) {
            $this->logger->log('Could not connect to SFTP server', 'error');
            throw new Exception("Could not connect to SFTP server. Please check host and port settings.");
        }

        // Authenticate
        if (!@ssh2_auth_password($connection, $config['username'], $config['password'])) {
            $this->logger->log('SFTP authentication failed for user: ' . $config['username'], 'error');
            throw new Exception("SFTP authentication failed. Please check username and password.");
        }

        // Initialize SFTP subsystem to verify the connection fully
        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            $this->logger->log('Could not initialize SFTP subsystem', 'error');
            throw new Exception("Could not initialize SFTP subsystem.");
        }

        $this->logger->log('SFTP connection test successful');
        return true;
    }
  }

