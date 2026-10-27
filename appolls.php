<?php
/**
 * Plugin Name: apPolls
 * Plugin URI: https://artistprowp.com/appolls/
 * Update URI: https://artistprowp.com/appolls/
 * Description: Create unlimited accessible WordPress polls with live results, scheduling, vote controls, analytics, exports, and optional audience routing.
 * Version: 1.0.3
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: artistproWP
 * Author URI: https://artistprowp.com/
 * Text Domain: appolls
 * Network: true
  * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('APPOLLS_VERSION', '1.0.3');
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


// Keep a cached WordPress.org update from replacing the retail edition.
// This guard is deliberately local to this plugin, independent of shared clients.
function appolls_retail_update_guard($transient) {
    if (!is_object($transient) || !isset($transient->response) || !is_array($transient->response)) { return $transient; }
    $plugin = plugin_basename(__FILE__);
    $entry = $transient->response[$plugin] ?? null;
    if (!is_object($entry)) { return $transient; }
    $host = strtolower((string) wp_parse_url((string) ($entry->package ?? ''), PHP_URL_HOST));
    if ($host === 'wordpress.org' || str_ends_with($host, '.wordpress.org')) { unset($transient->response[$plugin]); }
    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'appolls_retail_update_guard', PHP_INT_MAX);
add_filter('site_transient_update_plugins', 'appolls_retail_update_guard', PHP_INT_MAX);

register_activation_hook(__FILE__, ['APPolls_Schema', 'install']);

add_action('plugins_loaded', static function (): void {
    APPolls_License::init();
    APPolls_Plugin::instance()->boot();
    APPolls_Admin::instance()->boot();
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('appolls', 'APPolls_CLI');
}
