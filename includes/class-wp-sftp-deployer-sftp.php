<?php
/**
 * SFTP connection and operations handler
 */
class WP_SFTP_Deployer_SFTP {
    private $connection = null;
    private $sftp = null;
    private $logger;
    private $using_phpseclib = false;
    private $connected = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new WP_SFTP_Deployer_Logger();
        // Sprawdź czy mamy dostępne rozszerzenie SSH2
        $this->using_phpseclib = !extension_loaded('ssh2');
    }

    /**
     * Connect to the SFTP server
     */
    public function connect($config) {
        if ($this->connected) {
            return true;
        }

        $this->logger->log('Connecting to SFTP server: ' . $config['host'] . ':' . $config['port']);

        try {
            if ($this->using_phpseclib) {
                $result = $this->connect_with_phpseclib($config);
            } else {
                $result = $this->connect_with_ssh2($config);
            }

            $this->connected = true;
            $this->logger->log('Successfully connected to SFTP server');
            return $result;
        } catch (Exception $e) {
            $this->logger->log('SFTP connection error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Connect using PHP's ssh2 extension
     */
    private function connect_with_ssh2($config) {
        $this->logger->log('Using native SSH2 extension');

        // Connect to server
        $connection = @ssh2_connect($config['host'], $config['port']);
        if (!$connection) {
            throw new Exception("Could not connect to SFTP server. Please check host and port settings.");
        }

        // Authenticate
        if (!@ssh2_auth_password($connection, $config['username'], $config['password'])) {
            throw new Exception("SFTP authentication failed. Please check username and password.");
        }

        // Initialize SFTP subsystem
        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            throw new Exception("Could not initialize SFTP subsystem.");
        }

        $this->connection = $connection;
        $this->sftp = $sftp;

        return true;
    }

    /**
     * Connect using phpseclib (pure PHP implementation)
     */
    private function connect_with_phpseclib($config) {
        $this->logger->log('Using phpseclib for SFTP connection');

        try {
            // Użyj biblioteki phpseclib
            $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port'], $config['timeout']);

            if (!$sftp->login($config['username'], $config['password'])) {
                throw new Exception("SFTP authentication failed for user: {$config['username']} and password: {$config['password']}");
            }

            $this->sftp = $sftp;
            return true;
        } catch (Exception $e) {
            $this->logger->log('SFTP connection error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Upload a file to the server
     */
    public function upload_file($local_file, $remote_file) {
        $this->logger->log("Uploading file: $local_file to $remote_file");

        try {
            if ($this->using_phpseclib) {
                // Phpseclib implementation
                $result = $this->sftp->put($remote_file, $local_file, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE);
            } else {
                // Native SSH2 implementation
                $sftp_remote_file = 'ssh2.sftp://' . intval($this->sftp) . $remote_file;
                $result = file_put_contents($sftp_remote_file, file_get_contents($local_file));
            }

            if (!$result) {
                throw new Exception("Failed to upload file: $local_file");
            }

            $this->logger->log("File uploaded successfully");
            return true;
        } catch (Exception $e) {
            $this->logger->log('Upload error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Download a file from the server
     */
    public function download_file($remote_file, $local_file) {
        $this->logger->log("Downloading file: $remote_file to $local_file");

        try {
            if ($this->using_phpseclib) {
                // Phpseclib implementation
                $result = $this->sftp->get($remote_file, $local_file);
            } else {
                // Native SSH2 implementation
                $sftp_remote_file = 'ssh2.sftp://' . intval($this->sftp) . $remote_file;
                $result = file_put_contents($local_file, file_get_contents($sftp_remote_file));
            }

            if (!$result) {
                throw new Exception("Failed to download file: $remote_file");
            }

            $this->logger->log("File downloaded successfully");
            return true;
        } catch (Exception $e) {
            $this->logger->log('Download error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Create a directory on the server
     */
    public function mkdir($remote_dir) {
        $this->logger->log("Creating directory: $remote_dir");

        try {
            if ($this->using_phpseclib) {
                // Phpseclib implementation
                $result = $this->sftp->mkdir($remote_dir, -1, true); // Rekursywne tworzenie
            } else {
                // Native SSH2 implementation
                $sftp_remote_dir = 'ssh2.sftp://' . intval($this->sftp) . $remote_dir;
                $result = @mkdir($sftp_remote_dir, 0755, true);
            }

            if (!$result) {
                throw new Exception("Failed to create directory: $remote_dir");
            }

            $this->logger->log("Directory created successfully");
            return true;
        } catch (Exception $e) {
            $this->logger->log('Directory creation error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Check if a file exists on the server
     */
    public function file_exists($remote_file) {
        try {
            if ($this->using_phpseclib) {
                // Phpseclib implementation
                return $this->sftp->file_exists($remote_file);
            } else {
                // Native SSH2 implementation
                $sftp_remote_file = 'ssh2.sftp://' . intval($this->sftp) . $remote_file;
                return file_exists($sftp_remote_file);
            }
        } catch (Exception $e) {
            $this->logger->log('File check error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * List directory contents
     */
    public function list_directory($remote_dir) {
        $this->logger->log("Listing directory: $remote_dir");

        try {
            if ($this->using_phpseclib) {
                // Phpseclib implementation
                $result = $this->sftp->nlist($remote_dir);
            } else {
                // Native SSH2 implementation
                $sftp_remote_dir = 'ssh2.sftp://' . intval($this->sftp) . $remote_dir;
                $handle = opendir($sftp_remote_dir);
                $result = [];

                if ($handle) {
                    while (($file = readdir($handle)) !== false) {
                        if ($file != '.' && $file != '..') {
                            $result[] = $file;
                        }
                    }
                    closedir($handle);
                }
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->log('Directory listing error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Test the SFTP connection
     */
    public function test_connection($config) {
        try {
            if (!extension_loaded('ssh2') && !class_exists('\phpseclib3\Net\SFTP')) {
                throw new Exception("No SFTP implementation available. Install PHP SSH2 extension or include phpseclib.");
            }

            // Try connecting
            $this->connect($config);

            // Disconnect after test
            $this->disconnect();

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Disconnect from the server
     */
    public function disconnect() {
        if ($this->using_phpseclib) {
            // Phpseclib doesn't need explicit disconnect
            $this->sftp = null;
        } else if ($this->connection) {
            // For native SSH2, we can't really force disconnect, but we can clear our references
            $this->sftp = null;
            $this->connection = null;
        }

        $this->connected = false;
        $this->logger->log('SFTP disconnected');
    }
  /**
   * Execute a command on the remote server
   */
  public function execute_command($command, $sftp_config) {
	$this->logger->log('Executing remote command: ' . $command);
	
	try {
	  if ($this->using_phpseclib) {
		// For phpseclib 3.0, we need an SSH connection
		// We'll reuse username/password from the SFTP connection
		if (!isset($this->ssh)) {
		  // Create SSH connection if not already established
		  $this->ssh = new \phpseclib3\Net\SSH2(
			  $sftp_config['host'],
			  $sftp_config['port']
		  );
		  
		  // Get credentials from SFTP connection or attempt to login
		  if (!$this->ssh->login($sftp_config['username'], $sftp_config['password'])) {
			throw new Exception("SSH authentication failed for command execution");
		  }
		}
		
		$result = $this->ssh->exec($command);
		$this->logger->log('Command result: ' . $result);
		return true;
	  } else {
		// Native SSH2 implementation
		$stream = @ssh2_exec($this->connection, $command);
		if (!$stream) {
		  throw new Exception("Failed to execute command: $command");
		}
		
		stream_set_blocking($stream, true);
		$result = stream_get_contents($stream);
		fclose($stream);
		
		$this->logger->log('Command result: ' . $result);
		return true;
	  }
	} catch (Exception $e) {
	  $this->logger->log('Command execution error: ' . $e->getMessage(), 'error');
	  throw $e;
	}
  }
  
  /**
   * Extract a zip file on the remote server
   */
  public function extract_remote_zip($remote_zip_path, $extract_to, $sftp_config) {
	$this->logger->log('Extracting remote zip: ' . $remote_zip_path . ' to ' . $extract_to);
	
	// Create destination directory if it doesn't exist
	$this->execute_command("mkdir -p " . escapeshellarg($extract_to), $sftp_config);
	
	// Extract zip file using unzip command
	$command = "unzip -o " . escapeshellarg($remote_zip_path) . " -d " . escapeshellarg($extract_to);
	$this->execute_command($command, $sftp_config);
	
	$this->logger->log('Zip extraction completed successfully');
	return true;
  }
  
}
