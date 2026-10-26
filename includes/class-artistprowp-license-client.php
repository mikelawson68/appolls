<?php
/**
 * Shared artistproWP license client.
 *
 * This class is intentionally product-agnostic so the same pattern can be
 * copied into each distributable artistproWP plugin.
 *
 * @package ArtistPro_Security
 */

if (!defined('WPINC')) {
    die;
}

if (!class_exists('ArtistProWP_License_Client')) {
    class ArtistProWP_License_Client {
        private $config;

        public function __construct(array $config) {
            $this->config = array_merge(array(
                'product_key' => '',
                'product_name' => '',
                'plugin_file' => '',
                'version' => '',
                'option_prefix' => '',
                'api_url' => 'https://artistprowp.com/wp-json/artistpro-license/v1/',
                'account_url' => 'https://artistprowp.com/my-account/',
                'purchase_url' => 'https://artistprowp.com/',
                'support_url' => 'https://artistprowp.com/support/',
                'admin_page' => '',
                'admin_screen_contains' => '',
                'nonce_action' => 'artistprowp_license_client',
                'ajax_prefix' => 'artistprowp_license',
                'show_admin_notices' => true,
            ), $config);
        }

        public function hooks(): void {
            add_action('admin_post_' . $this->action('save_license'), array($this, 'handle_save_license'));
            add_action('wp_ajax_' . $this->action('activate_license'), array($this, 'ajax_activate'));
            add_action('wp_ajax_' . $this->action('deactivate_license'), array($this, 'ajax_deactivate'));
            add_action('wp_ajax_' . $this->action('check_updates'), array($this, 'ajax_check_updates'));
            add_filter('pre_set_site_transient_update_plugins', array($this, 'filter_update_transient'));
            add_filter('plugins_api', array($this, 'filter_plugin_info'), 10, 3);
            if (!empty($this->config['show_admin_notices'])) {
                add_action('admin_notices', array($this, 'admin_notice'));
            }
        }

        public function handle_save_license(): void {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Unauthorized', 'artistpro-security'));
            }
            $this->verify_admin_post_nonce();
            $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
            if ($key !== '') {
                $this->save_key($key, 'saved');
            }
            wp_safe_redirect(add_query_arg(array(
                'page' => $this->config['admin_page'],
                'license' => $key === '' ? 'kept' : 'saved',
            ), admin_url('admin.php')));
            exit;
        }

        public function ajax_activate(): void {
            $this->ajax_guard();
            $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
            if ($key === '') {
                $key = $this->get_key();
            }
            if ($key === '') {
                wp_send_json_error(array('message' => 'License key required.'));
            }
            $result = $this->activate($key);
            $this->send_ajax_result($result);
        }

        public function ajax_deactivate(): void {
            $this->ajax_guard();
            $result = $this->deactivate();
            $this->send_ajax_result($result);
        }

        public function ajax_check_updates(): void {
            $this->ajax_guard();
            $result = $this->check_update();
            $this->send_ajax_result($result);
        }

        public function activate(string $key): array {
            $result = $this->post('activate', array(
                'license_key' => $key,
                'product_id' => $this->config['product_key'],
                'product_key' => $this->config['product_key'],
                'site_url' => home_url(),
                'instance_id' => $this->instance_id(),
            ));
            if (!empty($result['success'])) {
                $this->save_key($key, 'active');
                $this->store_server_state($result);
            } else {
                $this->save_status('inactive', $result);
            }
            return $result;
        }

        public function deactivate(): array {
            $key = $this->get_key();
            if ($key === '') {
                $this->save_status('inactive');
                return array('success' => false, 'message' => 'No license key is saved.');
            }
            $result = $this->post('deactivate', array(
                'license_key' => $key,
                'product_id' => $this->config['product_key'],
                'product_key' => $this->config['product_key'],
                'site_url' => home_url(),
                'instance_id' => $this->instance_id(),
            ));
            $this->save_status('inactive', $result);
            return $result;
        }

        public function remote_check(string $key = ''): array {
            $key = $key === '' ? $this->get_key() : $key;
            if ($key === '') {
                return array('success' => false, 'message' => 'License key is not configured.');
            }
            $result = $this->post('check', array(
                'license_key' => $key,
                'product_id' => $this->config['product_key'],
                'product_key' => $this->config['product_key'],
                'site_url' => home_url(),
                'instance_id' => $this->instance_id(),
            ));
            if (!empty($result['success'])) {
                $this->save_status('active', $result);
            }
            return $result;
        }

        public function check_update(): array {
            $key = $this->get_key();
            if ($key === '') {
                return array('success' => false, 'message' => 'License key required.');
            }
            return $this->post('update-check', array(
                'license_key' => $key,
                'product_id' => $this->config['product_key'],
                'product_key' => $this->config['product_key'],
                'version' => $this->config['version'],
                'current_version' => $this->config['version'],
                'site_url' => home_url(),
                'instance_id' => $this->instance_id(),
            ));
        }

        public function filter_update_transient($transient) {
            if (empty($transient->checked) || empty($this->config['plugin_file'])) {
                return $transient;
            }
            $result = $this->check_update();
            $normalized = $this->normalize_update($result);
            if (!$normalized['update_available']) {
                return $transient;
            }
            $transient->response[$this->config['plugin_file']] = (object) array(
                'slug' => dirname($this->config['plugin_file']),
                'plugin' => $this->config['plugin_file'],
                'new_version' => $normalized['new_version'],
                'package' => $normalized['package'],
                'url' => $this->config['purchase_url'],
            );
            return $transient;
        }

        public function filter_plugin_info($false, $action, $args) {
            if ($action !== 'plugin_information') {
                return $false;
            }
            $slug = dirname((string) $this->config['plugin_file']);
            if (empty($args->slug) || $args->slug !== $slug) {
                return $false;
            }
            return (object) array(
                'name' => $this->config['product_name'],
                'slug' => $slug,
                'version' => $this->config['version'],
                'author' => 'artistproWP',
                'homepage' => $this->config['purchase_url'],
                'sections' => array(
                    'description' => $this->config['product_name'] . ' is licensed through artistprowp.com.',
                ),
            );
        }

        public function admin_notice(): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $screen_contains = (string) ($this->config['admin_screen_contains'] ?: $this->config['admin_page']);
            if (!$screen || ($screen_contains !== '' && strpos((string) $screen->id, $screen_contains) === false)) {
                return;
            }
            $state = $this->state();
            if ($state['status'] === 'active') {
                return;
            }
            $page = esc_url(admin_url('admin.php?page=' . $this->config['admin_page']));
            echo '<div class="notice notice-warning"><p><strong>' . esc_html($this->config['product_name']) . ':</strong> ';
            echo 'Enter and activate your license to receive product updates. ';
            echo '<a href="' . $page . '">Manage license</a></p></div>';
        }

        public function render_panel(): void {
            $state = $this->state();
            $has_key = $this->get_key() !== '';
            $status = (string) $state['status'];
            $nonce = wp_create_nonce($this->config['nonce_action']);
            ?>
            <div class="wrap artistpro-security-wrap">
                <h1><?php echo esc_html($this->config['product_name']); ?> License</h1>

                <div class="artistpro-card">
                    <h2>License Activation</h2>
                    <?php if ($status === 'active'): ?>
                        <div class="notice notice-success inline" style="margin:15px 0;">
                            <p><strong>License Active</strong></p>
                            <?php if (!empty($state['expires_at'])): ?>
                                <p>Expires: <?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $state['expires_at']))); ?></p>
                            <?php else: ?>
                                <p>No expiration date reported.</p>
                            <?php endif; ?>
                            <?php if ((int) $state['activation_limit'] > 0): ?>
                                <p>Activations: <?php echo esc_html((string) $state['activations_count']); ?> / <?php echo esc_html((string) $state['activation_limit']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning inline" style="margin:15px 0;">
                            <p><strong>License Not Active</strong></p>
                            <p>Enter your paid license key, then activate it with artistprowp.com.</p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field($this->config['nonce_action']); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr($this->action('save_license')); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="license_key">License Key</label></th>
                                <td>
                                    <input type="password" id="license_key" name="license_key" value="" class="regular-text code" autocomplete="new-password" placeholder="<?php echo esc_attr($has_key ? 'Saved - ending ' . $state['license_last4'] . '; leave blank to keep current key' : 'Enter license key'); ?>">
                                    <p class="description">
                                        Saved keys are not printed back into this page.
                                        <a href="<?php echo esc_url($this->config['purchase_url']); ?>" target="_blank" rel="noopener">Purchase</a>
                                        |
                                        <a href="<?php echo esc_url($this->config['account_url']); ?>" target="_blank" rel="noopener">Manage licenses</a>
                                        |
                                        <a href="<?php echo esc_url($this->config['support_url']); ?>" target="_blank" rel="noopener">Support</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save License Key</button>
                            <?php if ($has_key): ?>
                                <button type="button" class="button button-secondary" id="artistprowp-license-activate"><?php echo $status === 'active' ? 'Revalidate License' : 'Activate License'; ?></button>
                                <?php if ($status === 'active'): ?>
                                    <button type="button" class="button" id="artistprowp-license-deactivate">Deactivate License</button>
                                <?php endif; ?>
                                <button type="button" class="button" id="artistprowp-license-update-check">Check for Updates</button>
                            <?php endif; ?>
                        </p>
                    </form>
                    <div id="artistprowp-license-response" style="margin-top:20px;"></div>
                </div>

                <div class="artistpro-card">
                    <h2>Product Information</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <tr><th style="width:200px;">Product</th><td><strong><?php echo esc_html($this->config['product_name']); ?></strong></td></tr>
                        <tr><th>Product Key</th><td><code><?php echo esc_html($this->config['product_key']); ?></code></td></tr>
                        <tr><th>Current Version</th><td><?php echo esc_html($this->config['version']); ?></td></tr>
                        <tr><th>License Server</th><td><code><?php echo esc_html($this->config['api_url']); ?></code></td></tr>
                    </table>
                </div>
            </div>
            <script>
            jQuery(function($) {
                function message(text, ok) {
                    $('#artistprowp-license-response').html('<div class="notice ' + (ok ? 'notice-success' : 'notice-error') + ' inline"><p>' + $('<div>').text(text).html() + '</p></div>');
                }
                function post(action, extra, button, busy) {
                    var original = button.text();
                    button.prop('disabled', true).text(busy);
                    $.post(ajaxurl, $.extend({
                        action: action,
                        nonce: '<?php echo esc_js($nonce); ?>',
                        license_key: $('#license_key').val()
                    }, extra || {}), function(response) {
                        if (response.success) {
                            message(response.data.message || 'Done.', true);
                            window.setTimeout(function(){ window.location.reload(); }, 600);
                        } else {
                            message((response.data && response.data.message) || 'Request failed.', false);
                        }
                    }).fail(function() {
                        message('Connection failed. Please try again.', false);
                    }).always(function() {
                        button.prop('disabled', false).text(original);
                    });
                }
                $('#artistprowp-license-activate').on('click', function(){ post('<?php echo esc_js($this->action('activate_license')); ?>', {}, $(this), 'Activating...'); });
                $('#artistprowp-license-deactivate').on('click', function(){
                    if (window.confirm('Deactivate this license on this site?')) {
                        post('<?php echo esc_js($this->action('deactivate_license')); ?>', {}, $(this), 'Deactivating...');
                    }
                });
                $('#artistprowp-license-update-check').on('click', function(){ post('<?php echo esc_js($this->action('check_updates')); ?>', {}, $(this), 'Checking...'); });
            });
            </script>
            <?php
        }

        public function state(): array {
            $state = get_option($this->option('state'), array());
            $state = is_array($state) ? $state : array();
            $key = $this->get_key();
            return array_merge(array(
                'license_hash' => $key === '' ? '' : hash('sha256', $key),
                'license_last4' => $key === '' ? '' : substr($key, -4),
                'status' => $key === '' ? 'unlicensed' : 'saved',
                'updated_at' => '',
                'expires_at' => '',
                'activation_limit' => 0,
                'activations_count' => 0,
                'server' => array(),
            ), $state);
        }

        public function save_key(string $key, string $status = 'saved'): array {
            update_option($this->option('key'), $key, false);
            return $this->save_status($status, array(
                'license' => array(
                    'key_hash' => hash('sha256', $key),
                    'last4' => substr($key, -4),
                ),
            ));
        }

        public function get_key(): string {
            return (string) get_option($this->option('key'), '');
        }

        public function save_status(string $status, array $server = array()): array {
            $state = $this->state();
            $state['status'] = sanitize_key($status);
            $state['updated_at'] = current_time('mysql');
            $state['server'] = $server;
            $license = isset($server['license']) && is_array($server['license']) ? $server['license'] : array();
            if (!empty($license['expires_at'])) {
                $state['expires_at'] = sanitize_text_field((string) $license['expires_at']);
            }
            if (isset($license['activation_limit'])) {
                $state['activation_limit'] = (int) $license['activation_limit'];
            }
            if (isset($license['activations_count'])) {
                $state['activations_count'] = (int) $license['activations_count'];
            }
            update_option($this->option('state'), $state, false);
            update_option($this->option('status'), $state['status'], false);
            update_option($this->option('key_hash'), $state['license_hash'], false);
            update_option($this->option('expires'), $state['expires_at'], false);
            update_option($this->option('activation_limit'), $state['activation_limit'], false);
            update_option($this->option('activations'), $state['activations_count'], false);
            return $state;
        }

        private function store_server_state(array $result): void {
            $this->save_status('active', $result);
        }

        private function post(string $endpoint, array $body): array {
            $response = wp_remote_post(trailingslashit($this->config['api_url']) . ltrim($endpoint, '/'), array(
                'body' => $body,
                'timeout' => 15,
            ));
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => 'Connection error: ' . $response->get_error_message());
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                return array('success' => false, 'message' => 'License server returned an invalid response.');
            }
            if (!isset($data['message']) && isset($data['reason'])) {
                $data['message'] = (string) $data['reason'];
            }
            return $data;
        }

        private function normalize_update(array $result): array {
            $available = !empty($result['update_available']) || (isset($result['decision']) && $result['decision'] === 'update_available');
            return array(
                'update_available' => $available,
                'new_version' => (string) ($result['new_version'] ?? $result['available_version'] ?? ''),
                'package' => (string) ($result['package'] ?? $result['download_url'] ?? ''),
            );
        }

        private function send_ajax_result(array $result): void {
            $ok = !empty($result['success']) || (isset($result['decision']) && in_array($result['decision'], array('allowed', 'current', 'update_available'), true));
            $message = (string) ($result['message'] ?? $result['reason'] ?? ($ok ? 'Request completed.' : 'Request failed.'));
            if ($ok) {
                wp_send_json_success(array_merge($result, array('message' => $message)));
            }
            wp_send_json_error(array_merge($result, array('message' => $message)));
        }

        private function ajax_guard(): void {
            $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
            if (
                !wp_verify_nonce($nonce, $this->config['nonce_action'])
                && !wp_verify_nonce($nonce, 'artistpro_security_nonce')
            ) {
                wp_send_json_error(array('message' => 'Security check failed.'));
            }
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permission denied.'));
            }
        }

        private function verify_admin_post_nonce(): void {
            $valid = false;
            foreach (array($this->config['nonce_action'], 'artistpro_security_save_license', 'artistpro_license_settings') as $action) {
                if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), $action)) {
                    $valid = true;
                    break;
                }
                $field = $action . '_nonce';
                if (isset($_REQUEST[$field]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST[$field])), $action)) {
                    $valid = true;
                    break;
                }
                if (isset($_REQUEST['artistpro_license_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['artistpro_license_nonce'])), $action)) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                wp_die(esc_html__('Security check failed.', 'artistpro-security'));
            }
        }

        private function action(string $suffix): string {
            return sanitize_key($this->config['ajax_prefix'] . '_' . $suffix);
        }

        private function option(string $suffix): string {
            return sanitize_key($this->config['option_prefix'] . '_license_' . $suffix);
        }

        private function instance_id(): string {
            $option = $this->option('instance_id');
            $id = (string) get_option($option, '');
            if ($id === '') {
                $id = wp_generate_uuid4();
                update_option($option, $id, false);
            }
            return $id;
        }
    }
}
