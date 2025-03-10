(function($) {
    'use strict';
    function refreshLogs() {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_sftp_deployer_get_logs'
            },
            success: function(response) {
                if (response.success) {
                    var logContainer = jQuery('#wp-sftp-deployer-logs');
                    logContainer.html('');

                    // Add each log entry to the container
                    jQuery.each(response.data.logs, function(index, log) {
                        logContainer.append('<div class="log-entry">' + log + '</div>');
                    });
                }
            }
        });

        // Refresh logs every 2 seconds
        setTimeout(refreshLogs, 2000);
    }
    // Deployment functionality
    var WPSFTPDeployer = {
        deploymentId: null,
        statusCheckInterval: null,

        init: function() {
            $('#start-deployment').on('click', this.startDeployment);
            $('#confirm-overwrite').on('click', this.confirmOverwrite);
            $('#cancel-overwrite').on('click', this.cancelOverwrite);
        },

        startDeployment: function() {
            $('#start-deployment').prop('disabled', true);
            $('#deployment-progress-container').show();
            $('#deployment-progress-bar').css('width', '0%');
            $('#deployment-status').text(wpSftpDeployer.i18n.deploymentStarted);
            $('#deployment-log').empty();
            $('#confirmation-dialog').hide();

            $.ajax({
                url: wpSftpDeployer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_sftp_deployer_start',
                    security: wpSftpDeployer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSFTPDeployer.deploymentId = response.data.deployment_id;
                        WPSFTPDeployer.startStatusCheck();
                    } else {
                        WPSFTPDeployer.handleError(response.data.message);
                    }
                },
                error: function() {
                    WPSFTPDeployer.handleError('Failed to start deployment. Please try again.');
                }
            });
        },

        startStatusCheck: function() {
            // Clear any existing interval
            if (WPSFTPDeployer.statusCheckInterval) {
                clearInterval(WPSFTPDeployer.statusCheckInterval);
            }

            // Start checking status every 2 seconds
            WPSFTPDeployer.statusCheckInterval = setInterval(function() {
                WPSFTPDeployer.checkStatus();
            }, 2000);
        },

        checkStatus: function() {
            $.ajax({
                url: wpSftpDeployer.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'wp_sftp_deployer_check_status',
                    deployment_id: WPSFTPDeployer.deploymentId,
                    security: wpSftpDeployer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPSFTPDeployer.updateStatus(response.data);
                    } else {
                        WPSFTPDeployer.handleError(response.data.message);
                    }
                },
                error: function() {
                    WPSFTPDeployer.handleError('Failed to check deployment status.');
                }
            });
        },

        updateStatus: function(data) {
            // Update progress bar
            $('#deployment-progress-bar').css('width', data.progress + '%');

            // Update status message
            if (data.message) {
                $('#deployment-status').text(data.message);
            }

            // Update log
            $('#deployment-log').empty();
            if (data.log && data.log.length > 0) {
                $.each(data.log, function(index, logEntry) {
                    $('#deployment-log').append(logEntry + "\n");
                });

                // Scroll to bottom of log
                var logContainer = document.getElementById('deployment-log');
                logContainer.scrollTop = logContainer.scrollHeight;
            }

            // Handle specific statuses
            if (data.status === 'needs_confirmation') {
                // Show confirmation dialog
                $('#confirmation-dialog').show();
                clearInterval(WPSFTPDeployer.statusCheckInterval);
            }
            else if (data.status === 'complete') {
                // Deployment completed
                clearInterval(WPSFTPDeployer.statusCheckInterval);
                $('#start-deployment').prop('disabled', false);
                $('#deployment-status').html('<strong>' + wpSftpDeployer.i18n.deploymentComplete + '</strong>');
            }
            else if (data.status === 'error') {
                // Deployment failed
                clearInterval(WPSFTPDeployer.statusCheckInterval);
                $('#start-deployment').prop('disabled', false);
                $('#deployment-status').html('<strong class="error">' + wpSftpDeployer.i18n.deploymentFailed + '</strong>');
            }
        },

        confirmOverwrite: function() {
            $('#confirmation-dialog').hide();

            $.ajax({
                url: wpSftpDeployer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_sftp_deployer_confirm_overwrite',
                    deployment_id: WPSFTPDeployer.deploymentId,
                    security: wpSftpDeployer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Resume status checking
                        WPSFTPDeployer.startStatusCheck();
                    } else {
                        WPSFTPDeployer.handleError(response.data.message);
                    }
                },
                error: function() {
                    WPSFTPDeployer.handleError('Failed to confirm overwrite. Please try again.');
                }
            });
        },

        cancelOverwrite: function() {
            $('#confirmation-dialog').hide();
            $('#start-deployment').prop('disabled', false);
            $('#deployment-status').text('Deployment cancelled by user.');
        },

        handleError: function(message) {
            clearInterval(WPSFTPDeployer.statusCheckInterval);
            $('#start-deployment').prop('disabled', false);
            $('#deployment-status').html('<strong class="error">Error: ' + message + '</strong>');
        }
    };

    // SFTP Connection Tester functionality
    var SFTPConnectionTester = {
        init: function() {
            $('#test-sftp-connection').on('click', this.testConnection);
        },

        testConnection: function() {
            var $button = $(this);
            var $result = $('#test-connection-result');

            // Get current form values
            var host = $('#sftp_host').val();
            var port = $('#sftp_port').val();
            var username = $('#sftp_username').val();
            var password = $('#sftp_password').val();

            // If password field is empty, use existing password
            var useExistingPassword = password === '';

            if (!host || !username || (!password && !useExistingPassword)) {
                $result.html('<span class="error">Please fill in all required fields.</span>');
                return;
            }

            // Disable button during test
            $button.prop('disabled', true).text('Testing connection...');
            $result.html('<span class="loading">Testing connection...</span>');

            // Send AJAX request
            $.ajax({
                url: wpSftpDeployer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_sftp_deployer_test_connection',
                    security: wpSftpDeployer.nonce,
                    host: host,
                    port: port,
                    username: username,
                    password: password,
                    use_existing: useExistingPassword
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="success">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span class="error">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="error">Server error occurred while testing connection.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test SFTP Connection');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#start-deployment').length) {
            WPSFTPDeployer.init();
            refreshLogs();
        }
        if ($('#test-sftp-connection').length) {
            SFTPConnectionTester.init();
        }

    });

})(jQuery);
