<?php
  /**
   * Zip functionality
   */
  class WP_SFTP_Deployer_Zipper {
    private $logger;
    
    public function __construct() {
      $this->logger = new WP_SFTP_Deployer_Logger();
    }
	public function create_package($source_path, $output_file) {
	  $this->logger->log('Creating deployment package from: ' . $source_path);
	  
	  // Call the existing zip creation method
	  $zip_file = $this->create_zip($source_path);
	  
	  // If the output path is different from what create_zip generated,
	  // rename the file to match the expected output
	  if ($zip_file !== $output_file && file_exists($zip_file)) {
		rename($zip_file, $output_file);
		return $output_file;
	  }
	  
	  return $zip_file;
	}
    /**
     * Create a ZIP file from a directory
     */
    public function create_zip($source_path) {
      $this->logger->log('Creating ZIP from directory: ' . $source_path);
      
      // Validate source path
      if (!file_exists($source_path)) {
        $this->logger->log('Source directory does not exist: ' . $source_path, 'error');
        throw new Exception("Source directory does not exist: $source_path");
      }
      
      // Create a temporary file for the ZIP
      $zip_file = WP_SFTP_DEPLOYER_PLUGIN_DIR . 'temp/deploy_' . date('YmdHis') . '.zip';
      $zip_dir = dirname($zip_file);
      
      // Ensure temp directory exists
      if (!file_exists($zip_dir)) {
        wp_mkdir_p($zip_dir);
      }
      
      // Create ZIP archive
      $zip = new ZipArchive();
      if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $this->logger->log('Failed to create ZIP file', 'error');
        throw new Exception("Failed to create ZIP file");
      }
      
      // Add files to ZIP
      $source_path = rtrim($source_path, '/');
      $files = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($source_path),
          RecursiveIteratorIterator::LEAVES_ONLY
      );
      
      $file_count = 0;
      foreach ($files as $file) {
        // Skip directories (they are added automatically)
        if ($file->isDir()) {
          continue;
        }
        
        // Get real and relative path for current file
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen($source_path) + 1);
        
        // Add current file to archive
        $zip->addFile($file_path, $relative_path);
        $file_count++;
      }
      
      // Close the ZIP file
      $zip->close();
      
      $this->logger->log("ZIP file created with $file_count files: " . $zip_file);
      return $zip_file;
    }
  }
