<?php
/**
 * Requirements checker
 */
class WP_SFTP_Deployer_Requirements {
    private $requirements = [
        'extensions' => [
            'ssh2' => [
                'name' => 'SSH2',
                'critical' => true,
                'message' => 'The PHP SSH2 extension is required for SFTP connections. Please install it or contact your hosting provider.'
            ],
            'zip' => [
                'name' => 'ZIP',
                'critical' => true,
                'message' => 'The PHP ZIP extension is required for creating deployment packages. Please install it or contact your hosting provider.'
            ],
            'json' => [
                'name' => 'JSON',
                'critical' => true,
                'message' => 'The PHP JSON extension is required for plugin settings. Please install it or contact your hosting provider.'
            ]
        ],
        'php_version' => [
            'required' => '7.0',
            'critical' => true,
            'message' => 'PHP version 7.0 or higher is required.'
        ],
        'permissions' => [
            'settings_dir' => [
                'path' => WP_SFTP_DEPLOYER_PLUGIN_DIR . 'settings',
                'critical' => true,
                'message' => 'The settings directory must be writable.'
            ],
            'logs_dir' => [
                'path' => WP_SFTP_DEPLOYER_PLUGIN_DIR . 'logs',
                'critical' => true,
                'message' => 'The logs directory must be writable.'
            ],
            'temp_dir' => [
                'path' => WP_SFTP_DEPLOYER_PLUGIN_DIR . 'temp',
                'critical' => false,
                'message' => 'The temp directory must be writable for optimal performance.'
            ]
        ]
    ];

    private $errors = [];
    private $warnings = [];

    /**
     * Check all requirements
     */
    public function check_all() {
        $this->check_php_version();
        $this->check_extensions();
        $this->check_permissions();

        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'has_critical_errors' => !empty($this->errors)
        ];
    }

    /**
     * Check PHP version
     */
    private function check_php_version() {
        $req = $this->requirements['php_version'];

        if (version_compare(PHP_VERSION, $req['required'], '<')) {
            if ($req['critical']) {
                $this->errors[] = $req['message'] . ' (Current version: ' . PHP_VERSION . ')';
            } else {
                $this->warnings[] = $req['message'] . ' (Current version: ' . PHP_VERSION . ')';
            }
        }
    }

    /**
     * Check required extensions
     */
    private function check_extensions() {
        foreach ($this->requirements['extensions'] as $extension => $req) {
            if (!extension_loaded($extension)) {
                if ($req['critical']) {
                    $this->errors[] = $req['message'];
                } else {
                    $this->warnings[] = $req['message'];
                }
            }
        }
    }

    /**
     * Check directory permissions
     */
    private function check_permissions() {
        foreach ($this->requirements['permissions'] as $type => $req) {
            $path = $req['path'];

            // Check if directory exists, if not - try to create it
            if (!file_exists($path)) {
                @wp_mkdir_p($path);
            }

            // Now check if it's writable
            if (!file_exists($path) || !is_writable($path)) {
                if ($req['critical']) {
                    $this->errors[] = $req['message'] . ' (Path: ' . $path . ')';
                } else {
                    $this->warnings[] = $req['message'] . ' (Path: ' . $path . ')';
                }
            }
        }
    }

    /**
     * Display admin notices for requirements issues
     */
    public function display_notices() {
        if (!empty($this->errors)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>SFTP Deployer - Critical Requirements Not Met:</strong></p>';
            echo '<ul style="margin-left: 20px; list-style-type: disc;">';
            foreach ($this->errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if (!empty($this->warnings)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>SFTP Deployer - Recommendations:</strong></p>';
            echo '<ul style="margin-left: 20px; list-style-type: disc;">';
            foreach ($this->warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
}
