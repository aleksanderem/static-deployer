<div class="wrap wp-sftp-deployer">
    <h1><?php _e('SFTP Deployer Settings', 'wp-sftp-deployer'); ?></h1>

    <?php settings_errors('wp_sftp_deployer_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('wp_sftp_deployer_settings', 'wp_sftp_deployer_settings_nonce'); ?>

        <div class="card">
            <h2><?php _e('SFTP Connection Settings', 'wp-sftp-deployer'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sftp_host"><?php _e('Host', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="text" id="sftp_host" name="sftp_host" value="<?php echo esc_attr($sftp['host']); ?>" class="regular-text" required />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sftp_port"><?php _e('Port', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="number" id="sftp_port" name="sftp_port" value="<?php echo esc_attr($sftp['port']); ?>" class="small-text" required />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sftp_username"><?php _e('Username', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="text" id="sftp_username" name="sftp_username" value="<?php echo esc_attr($sftp['username']); ?>" class="regular-text" required />
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sftp_password"><?php _e('Password', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="password" id="sftp_password" name="sftp_password" value="" class="regular-text" placeholder="<?php echo !empty($sftp['password']) ? '••••••••' : ''; ?>" />
                        <p class="description"><?php _e('Leave empty to keep the current password.', 'wp-sftp-deployer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sftp_remote_path"><?php _e('Remote Path', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="text" id="sftp_remote_path" name="sftp_remote_path" value="<?php echo esc_attr($sftp['remote_path']); ?>" class="regular-text" required />
                        <p class="description"><?php _e('Absolute path on the remote server where files will be deployed.', 'wp-sftp-deployer'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sftp_timeout"><?php _e('Connection Timeout', 'wp-sftp-deployer'); ?></label></th>
                    <td>
                        <input type="number" id="sftp_timeout" name="sftp_timeout" value="<?php echo esc_attr($sftp['timeout']); ?>" class="small-text" required />
                        <p class="description"><?php _e('Connection timeout in seconds.', 'wp-sftp-deployer'); ?></p>
                    </td>
                </tr>
            </table>

        <div class="sftp-test-connection">
            <button type="button" id="test-sftp-connection" class="button"><?php _e('Test SFTP Connection', 'wp-sftp-deployer'); ?></button>
            <span id="test-connection-result"></span>
        </div>
        </div>

        <div class="card">
            <h2><?php _e('Local Settings', 'wp-sftp-deployer'); ?></h2>

            <table class="form-table">
               <tr>
                   <th scope="row"><label for="local_source_path"><?php _e('Source Directory', 'wp-sftp-deployer'); ?></label></th>
                   <td>
                       <input type="text" id="local_source_path" name="local_source_path" value="<?php echo esc_attr($local['source_path']); ?>" class="regular-text" required />
                       <p class="description">
                           <?php _e('Absolute path to the local directory that will be packaged and deployed.', 'wp-sftp-deployer'); ?>
                           <br>
                           <em><?php _e('WordPress root path: ', 'wp-sftp-deployer'); ?><?php echo esc_html(ABSPATH); ?></em>
                       </p>
                   </td>
               </tr>
           </table>
       </div>

       <div class="card">
           <h2><?php _e('Logging Settings', 'wp-sftp-deployer'); ?></h2>

           <table class="form-table">
               <tr>
                   <th scope="row"><?php _e('Enable Logging', 'wp-sftp-deployer'); ?></th>
                   <td>
                       <label for="logging_enabled">
                           <input type="checkbox" id="logging_enabled" name="logging_enabled" value="1" <?php checked($logging['enabled']); ?> />
                           <?php _e('Enable logging of deployment operations', 'wp-sftp-deployer'); ?>
                       </label>
                   </td>
               </tr>

               <tr>
                   <th scope="row"><label for="logging_level"><?php _e('Log Level', 'wp-sftp-deployer'); ?></label></th>
                   <td>
                       <select id="logging_level" name="logging_level">
                           <option value="debug" <?php selected($logging['log_level'], 'debug'); ?>><?php _e('Debug', 'wp-sftp-deployer'); ?></option>
                           <option value="info" <?php selected($logging['log_level'], 'info'); ?>><?php _e('Info', 'wp-sftp-deployer'); ?></option>
                           <option value="warning" <?php selected($logging['log_level'], 'warning'); ?>><?php _e('Warning', 'wp-sftp-deployer'); ?></option>
                           <option value="error" <?php selected($logging['log_level'], 'error'); ?>><?php _e('Error', 'wp-sftp-deployer'); ?></option>
                       </select>
                   </td>
               </tr>

               <tr>
                   <th scope="row"><label for="logging_custom_path"><?php _e('Custom Log Path', 'wp-sftp-deployer'); ?></label></th>
                   <td>
                       <input type="text" id="logging_custom_path" name="logging_custom_path" value="<?php echo esc_attr($logging['custom_path']); ?>" class="regular-text" />
                       <p class="description"><?php _e('Optional: Provide an additional directory path where logs will be stored (must be writable).', 'wp-sftp-deployer'); ?></p>
                   </td>
               </tr>
           </table>
       </div>

       <p class="submit">
           <input type="submit" name="wp_sftp_deployer_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'wp-sftp-deployer'); ?>" />
       </p>
   </form>
</div>

