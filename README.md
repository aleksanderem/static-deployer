# WordPress SFTP Deployer

A powerful WordPress plugin for deploying static websites via SFTP with secure credential handling and live deployment tracking.

## Description

WordPress SFTP Deployer provides an efficient way to package and deploy static website files from your WordPress installation to any remote server via SFTP. Perfect for deploying static sites, staging environments, or backing up critical files.

## Integration with Simply Static

This plugin is designed to work seamlessly with Simply Static's local directory export option:

1. Configure Simply Static to export your WordPress site to a local directory
2. In SFTP Deployer, set that same local directory as your "Source Path"
3. After generating your static site with Simply Static, use SFTP Deployer to upload and extract it to your remote server

This workflow creates a powerful static site deployment pipeline that maintains all the SEO benefits of static sites while providing a user-friendly WordPress editing experience.

## Features

- **Secure Password Storage**: All SFTP credentials are encrypted using WordPress salts and OpenSSL
- **Multiple Connection Methods**: Uses phpseclib 3.0 with fallback to native SSH2 extension
- **Simple Deployment**: Package, upload, and extract files in one click
- **Live Deployment Logs**: Watch the deployment process in real-time
- **Test Mode**: Verify your configuration without making actual changes
- **Custom Log Paths**: Configure where logs are stored for better organization

## Installation

1. Upload the `static-deployer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tools → SFTP Deployer to configure your deployment settings

## Configuration

### SFTP Settings

- **Host**: Your SFTP server hostname
- **Port**: SFTP port (typically 22)
- **Username**: SFTP account username
- **Password**: SFTP account password (securely encrypted)
- **Remote Path**: Directory on the remote server where files will be deployed

### Local Settings

- **Source Path**: Local directory containing files to deploy (typically your Simply Static export directory)
- **Test Mode**: Enable to simulate deployment without actual file transfer

### Logging

- **Enable Logging**: Toggle detailed logging on/off
- **Log Level**: Choose between debug, info, warning, or error logging
- **Custom Log Path**: Specify an alternative location for log files

## Usage

1. Configure your SFTP connection settings
2. Set your local source path to match your Simply Static export directory
3. Run Simply Static to generate your site files
4. Click "Deploy Now" to start the deployment process
5. Monitor the live deployment logs for progress and status

## Security

The plugin prioritizes security by:
- Encrypting SFTP passwords using AES-256-CBC encryption
- Using WordPress security keys as encryption keys
- Setting restrictive file permissions on settings files (0600)
- Offering .env file support as an alternative credential storage method

## Technical Details

- Requires PHP 7.0 or higher
- Requires WordPress 5.0 or higher
- Uses phpseclib 3.0 for SFTP operations when PHP SSH2 extension is unavailable
- Follows WordPress coding standards and security best practices

## Support

For bug reports or feature requests, please submit an issue on our GitHub repository.

## Author

Alex Miesak @ Kolabo Group
