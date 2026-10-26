<?php
/**
 * Plugin Name: apPolls
 * Plugin URI: https://artistprowp.com/appolls/
 * Description: Create unlimited accessible WordPress polls with live results, scheduling, vote controls, analytics, exports, and optional audience routing.
 * Version: 1.0.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: artistproWP
 * Author URI: https://artistprowp.com/
 * Text Domain: appolls
 * Network: true
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('APPOLLS_VERSION', '1.0.2');
define('APPOLLS_PLUGIN_FILE', __FILE__);
define('APPOLLS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-contract.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-schema.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-lifecycle.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-plugin.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-admin.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-cli.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-artistprowp-license-client.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-license.php';

register_activation_hook(__FILE__, ['APPolls_Schema', 'install']);

add_action('plugins_loaded', static function (): void {
    APPolls_License::init();
    APPolls_Plugin::instance()->boot();
    APPolls_Admin::instance()->boot();
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('appolls', 'APPolls_CLI');
}
