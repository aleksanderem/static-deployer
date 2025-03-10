<div class="wrap wp-sftp-deployer">
  <h1><?php _e('SFTP Deployer', 'wp-sftp-deployer'); ?></h1>
  
  <?php if (!$is_configured): ?>
    <div class="notice notice-warning">
      <p><?php _e('Please configure the plugin settings before deployment.', 'wp-sftp-deployer'); ?> <a href="<?php echo admin_url('admin.php?page=wp-sftp-deployer-settings'); ?>"><?php _e('Go to settings', 'wp-sftp-deployer'); ?></a></p>
    </div>
  <?php else: ?>
    <div class="card">
      <h2><?php _e('Deployment Information', 'wp-sftp-deployer'); ?></h2>
      <p><strong><?php _e('Source Directory:', 'wp-sftp-deployer'); ?></strong> <?php echo esc_html($settings['local']['source_path']); ?></p>
      <p><strong><?php _e('Destination Server:', 'wp-sftp-deployer'); ?></strong> <?php echo esc_html($settings['sftp']['host']); ?>:<?php echo esc_html($settings['sftp']['port']); ?></p>
      <p><strong><?php _e('Remote Path:', 'wp-sftp-deployer'); ?></strong> <?php echo esc_html($settings['sftp']['remote_path']); ?></p>
    </div>
    
    <div class="card deployment-card">
      <h2><?php _e('Start Deployment', 'wp-sftp-deployer'); ?></h2>
      <p><?php _e('This will package your files into a ZIP archive and deploy them to the remote server.', 'wp-sftp-deployer'); ?></p>
      
      <button id="start-deployment" class="button button-primary"><?php _e('Start Deployment', 'wp-sftp-deployer'); ?></button>
      
      <div id="deployment-progress-container" style="display: none; margin-top: 20px;">
        <div class="progress-bar-container">
          <div id="deployment-progress-bar" class="progress-bar"></div>
        </div>
        <p id="deployment-status"><?php _e('Initializing deployment...', 'wp-sftp-deployer'); ?></p>
        
        <div id="confirmation-dialog" style="display: none; margin-top: 15px;" class="notice notice-warning">
          <p><?php _e('Files exist on the remote server. Do you want to continue and overwrite them?', 'wp-sftp-deployer'); ?></p>
          <button id="confirm-overwrite" class="button button-primary"><?php _e('Yes, Continue', 'wp-sftp-deployer'); ?></button>
          <button id="cancel-overwrite" class="button"><?php _e('No, Cancel', 'wp-sftp-deployer'); ?></button>
        </div>
        
        <div id="deployment-details" style="margin-top: 20px;">
          <h3><?php _e('Deployment Log', 'wp-sftp-deployer'); ?></h3>
          <div id="deployment-log" class="deployment-log"></div><div id="wp-sftp-deployer-logs"></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
