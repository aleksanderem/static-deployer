<div class="wrap wp-sftp-deployer">
    <h1><?php _e('SFTP Deployer Logs', 'wp-sftp-deployer'); ?></h1>

    <?php if (empty($logs)): ?>
        <div class="notice notice-info">
            <p><?php _e('No logs available yet.', 'wp-sftp-deployer'); ?></p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2><?php _e('Recent Logs', 'wp-sftp-deployer'); ?></h2>

            <div class="deployer-logs">
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <?php
                        // Highlight log entries based on level
                        if (strpos($log, '[error]') !== false) {
                            echo '<div class="log-error">' . esc_html($log) . '</div>';
                        } elseif (strpos($log, '[warning]') !== false) {
                            echo '<div class="log-warning">' . esc_html($log) . '</div>';
                        } else {
                            echo '<div class="log-info">' . esc_html($log) . '</div>';
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
