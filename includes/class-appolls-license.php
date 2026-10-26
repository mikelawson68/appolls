<?php
/**
 * apPolls license client wrapper.
 *
 * @package APPolls
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class APPolls_License
{
    public static function init(): void
    {
        self::client()->hooks();
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
    }

    public static function client(): ArtistProWP_License_Client
    {
        static $client = null;
        if (!$client) {
            $client = new ArtistProWP_License_Client([
                'product_key' => 'appolls',
                'product_name' => 'apPolls',
                'plugin_file' => plugin_basename(APPOLLS_PLUGIN_FILE),
                'version' => APPOLLS_VERSION,
                'option_prefix' => 'appolls',
                'purchase_url' => 'https://artistprowp.com/appolls/',
                'account_url' => 'https://artistprowp.com/my-account/',
                'support_url' => 'https://artistprowp.com/support/',
                'admin_page' => 'appolls-license',
                'admin_screen_contains' => 'appolls-license',
                'nonce_action' => 'appolls_license',
                'ajax_prefix' => 'appolls',
            ]);
        }
        return $client;
    }

    public static function admin_menu(): void
    {
        add_submenu_page('appolls', 'apPolls License', 'License', 'manage_options', 'appolls-license', [__CLASS__, 'render_page']);
    }

    public static function render_page(): void
    {
        self::client()->render_panel();
    }
}
