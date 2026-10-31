<?php
/**
 * Plugin Name: apPolls
 * Plugin URI: https://artistprowp.com/appolls/
 * Description: Create unlimited accessible WordPress polls with live results, scheduling, vote controls, analytics, exports, and optional audience routing.
 * Version: 1.0.4
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: artistproWP
 * Author URI: https://artistprowp.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: appolls
 * Network: true
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// The directory edition shares these classes and tables. Never load both.
$appolls_directory_active = in_array('artistpro-polls/artistpro-polls.php', (array) get_option('active_plugins', []), true);
if (is_multisite()) {
    $appolls_directory_active = $appolls_directory_active || isset(get_site_option('active_sitewide_plugins', [])['artistpro-polls/artistpro-polls.php']);
}
if ($appolls_directory_active) {
    register_activation_hook(__FILE__, static function (): void {
        wp_die(esc_html__('Deactivate artistpro Polls before activating apPolls. Your poll data will be retained.', 'appolls'));
    });
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-warning"><p>' . esc_html__('apPolls is paused because artistpro Polls is active.', 'appolls') . '</p></div>';
    });
    return;
}

define('APPOLLS_VERSION', '1.0.4');
define('APPOLLS_PLUGIN_FILE', __FILE__);
define('APPOLLS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-contract.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-schema.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-lifecycle.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-plugin.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-admin.php';
require_once APPOLLS_PLUGIN_DIR . 'includes/class-appolls-cli.php';

register_activation_hook(__FILE__, ['APPolls_Schema', 'install']);

add_action('plugins_loaded', static function (): void {
    APPolls_Plugin::instance()->boot();
    APPolls_Admin::instance()->boot();
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('appolls', 'APPolls_CLI');
}
