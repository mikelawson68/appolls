<?php
/** apPolls administration. @package apPolls */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class APPolls_Admin
{
    private static ?self $instance = null;
    public static function instance(): self { return self::$instance ?: (self::$instance = new self()); }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'menu'], 5);
        add_action('admin_post_appolls_save_poll', [$this, 'save_poll']);
        add_action('admin_post_appolls_add_question', [$this, 'add_question']);
        add_action('admin_post_appolls_delete_question', [$this, 'delete_question']);
        add_action('admin_post_appolls_export', [$this, 'export']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu(): void
    {
        add_menu_page('apPolls', 'apPolls', 'manage_options', 'appolls', [$this, 'render'], 'dashicons-chart-bar', 27);
        add_submenu_page('appolls', 'All Polls', 'All Polls', 'manage_options', 'appolls', [$this, 'render']);
        add_submenu_page('appolls', 'Add New Poll', 'Add New', 'manage_options', 'appolls-new', [$this, 'render_editor']);
        add_submenu_page('appolls', 'Getting Started', 'Getting Started', 'manage_options', 'appolls-start', [$this, 'render_start']);
    }

    public function assets(string $hook): void
    {
        if (strpos($hook, 'appolls') === false) { return; }
        wp_enqueue_style('appolls-admin', plugin_dir_url(APPOLLS_PLUGIN_FILE) . 'assets/appolls-admin.css', [], APPOLLS_VERSION);
    }

    public function render(): void
    {
        $this->guard();
        $polls = APPolls_Lifecycle::all_polls();
        ?>
        <div class="wrap appolls-admin"><div class="appolls-heading"><div><h1>apPolls</h1><p>Fast, accessible polls with results you own.</p></div><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=appolls-new')); ?>">Create a poll</a></div>
        <?php $this->notice(); ?>
        <?php if (!$polls): ?>
            <div class="appolls-card appolls-empty"><h2>Create your first poll</h2><p>Build a question, add choices, publish it, then paste one shortcode into any page or post.</p><a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=appolls-new')); ?>">Start in under two minutes</a></div>
        <?php else: ?>
            <div class="appolls-card"><table class="widefat striped"><thead><tr><th>Poll</th><th>Status</th><th>Responses</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($polls as $poll): ?><tr><td><strong><?php echo esc_html($poll['name']); ?></strong><br><span class="description"><?php echo esc_html($poll['description']); ?></span></td><td><span class="appolls-status appolls-status-<?php echo esc_attr($poll['status']); ?>"><?php echo esc_html(ucfirst($poll['status'])); ?></span></td><td><?php echo esc_html((string) $poll['response_count']); ?></td><td><code>[appolls poll="<?php echo esc_attr($poll['poll_key']); ?>"]</code></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=appolls-new&poll_id=' . (int) $poll['id'])); ?>">Edit &amp; results</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?></div><?php
    }

    public function render_editor(): void
    {
        $this->guard();
        $poll_id = absint($_GET['poll_id'] ?? 0);
        $poll = $poll_id ? APPolls_Lifecycle::poll_by_id($poll_id) : null;
        $questions = $poll ? APPolls_Lifecycle::questions_for_poll($poll_id) : [];
        $results = $poll ? APPolls_Lifecycle::live_results($poll_id) : [];
        ?>
        <div class="wrap appolls-admin"><h1><?php echo $poll ? 'Edit poll' : 'Create a poll'; ?></h1><?php $this->notice(); ?>
        <form class="appolls-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="appolls_save_poll"><input type="hidden" name="poll_id" value="<?php echo esc_attr((string) $poll_id); ?>"><?php wp_nonce_field('appolls_save_poll'); ?>
            <div class="appolls-grid"><p><label>Poll name<br><input class="regular-text" required name="name" value="<?php echo esc_attr($poll['name'] ?? ''); ?>"></label></p><p><label>Poll key<br><input class="regular-text code" <?php echo $poll ? 'readonly' : 'required'; ?> name="poll_key" value="<?php echo esc_attr($poll['poll_key'] ?? ''); ?>"><span class="description">Lowercase URL-safe identifier.</span></label></p></div>
            <p><label>Description<br><textarea class="large-text" rows="3" name="description"><?php echo esc_textarea($poll['description'] ?? ''); ?></textarea></label></p>
            <div class="appolls-grid"><p><label>Status<br><select name="status"><?php foreach (['draft','active','inactive','archived'] as $status): ?><option value="<?php echo esc_attr($status); ?>" <?php selected($poll['status'] ?? 'draft', $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select></label></p><p><label>Show results<br><select name="result_visibility"><?php foreach (['after_vote'=>'After voting','always'=>'Always','closed_only'=>'After poll closes','never'=>'Never'] as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($poll['result_visibility'] ?? 'after_vote', $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p><p><label>Starts (optional)<br><input type="datetime-local" name="start_at" value="<?php echo esc_attr($this->datetime_value($poll['start_at'] ?? '')); ?>"></label></p><p><label>Ends (optional)<br><input type="datetime-local" name="end_at" value="<?php echo esc_attr($this->datetime_value($poll['end_at'] ?? '')); ?>"></label></p></div>
            <p><label><input type="checkbox" name="reveal_response_count" value="1" <?php checked(!empty($poll['reveal_response_count'])); ?>> Show total response count</label></p>
            <?php submit_button($poll ? 'Save poll' : 'Create poll'); ?>
        </form>
        <?php if ($poll): ?>
            <div class="appolls-card"><h2>Questions</h2>
            <?php foreach ($questions as $question): ?><div class="appolls-question-row"><div><strong><?php echo esc_html($question['question_text']); ?></strong><div><?php echo esc_html(implode(' • ', array_map(static fn($c) => $c['label'], (array) $question['choices']))); ?></div></div><a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=appolls_delete_question&poll_id=' . $poll_id . '&question_id=' . (int) $question['id']), 'appolls_delete_question')); ?>" onclick="return confirm('Delete this question and its choices?')">Delete</a></div><?php endforeach; ?>
            <hr><h3>Add a question</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="appolls_add_question"><input type="hidden" name="poll_id" value="<?php echo esc_attr((string) $poll_id); ?>"><?php wp_nonce_field('appolls_add_question'); ?><p><label>Question<br><input class="large-text" required name="question_text"></label></p><div class="appolls-grid"><p><label>Type<br><select name="question_type"><option value="single_choice">Single choice</option><option value="multiple_choice">Multiple choice</option><option value="yes_no">Yes / No</option><option value="rating">Rating 1–5</option><option value="likert">Agreement scale</option><option value="open_text">Open text</option></select></label></p><p><label><input type="checkbox" name="required" value="1" checked> Required</label></p></div><p><label>Choices, one per line<br><textarea class="large-text" rows="6" name="choices" placeholder="Choice one&#10;Choice two"></textarea></label></p><?php submit_button('Add question', 'secondary'); ?></form></div>
            <div class="appolls-card"><h2>Publish</h2><p>Paste this shortcode into any page, post, or widget:</p><p><code class="appolls-shortcode">[appolls poll="<?php echo esc_attr($poll['poll_key']); ?>"]</code></p></div>
            <div class="appolls-card"><div class="appolls-heading"><div><h2>Results</h2><p><?php echo esc_html((string) ($results['response_count'] ?? 0)); ?> unique responses</p></div><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=appolls_export&poll_id=' . $poll_id), 'appolls_export')); ?>">Export CSV</a></div><?php $this->results_table($results); ?></div>
        <?php endif; ?></div><?php
    }

    public function render_start(): void
    {
        $this->guard(); ?><div class="wrap appolls-admin"><h1>Getting started with apPolls</h1><div class="appolls-card"><ol class="appolls-steps"><li><strong>Create</strong><span>Name your poll and choose when results appear.</span></li><li><strong>Add questions</strong><span>Use single choice, multiple choice, yes/no, rating, agreement scale, or open text.</span></li><li><strong>Activate</strong><span>Set the poll status to Active.</span></li><li><strong>Publish</strong><span>Copy the shortcode into a page or post.</span></li><li><strong>Learn</strong><span>Watch live results and export response data any time.</span></li></ol><p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=appolls-new')); ?>">Create your first poll</a></p></div></div><?php
    }

    public function save_poll(): void
    {
        $this->post_guard('appolls_save_poll');
        $data = wp_unslash($_POST);
        $poll_id = absint($data['poll_id'] ?? 0);
        $result = $poll_id ? APPolls_Lifecycle::update_poll($poll_id, $data) : APPolls_Lifecycle::create_poll($data + ['created_by_user_id' => get_current_user_id()]);
        $poll_id = (int) ($result['poll_id'] ?? $poll_id);
        $this->redirect('appolls-new', $poll_id, !empty($result['success']) ? 'saved' : 'error');
    }

    public function add_question(): void
    {
        $this->post_guard('appolls_add_question');
        $data = wp_unslash($_POST); $poll_id = absint($data['poll_id'] ?? 0); $type = sanitize_key($data['question_type'] ?? 'single_choice');
        $choices = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\R/', (string) ($data['choices'] ?? '')) ?: [])));
        if ($type === 'yes_no') { $choices = ['Yes', 'No']; }
        if ($type === 'rating') { $choices = ['1', '2', '3', '4', '5']; }
        if ($type === 'likert') { $choices = ['Strongly disagree', 'Disagree', 'Neither agree nor disagree', 'Agree', 'Strongly agree']; }
        $question = APPolls_Lifecycle::add_question($poll_id, ['question_key' => 'q_' . strtolower(wp_generate_password(8, false, false)), 'question_text' => $data['question_text'] ?? '', 'question_type' => $type, 'required' => !empty($data['required']), 'sort_order' => time()]);
        foreach ($choices as $index => $label) { APPolls_Lifecycle::add_choice((int) ($question['question_id'] ?? 0), ['choice_key' => 'c_' . ($index + 1) . '_' . sanitize_key($label), 'label' => $label, 'sort_order' => ($index + 1) * 10]); }
        $this->redirect('appolls-new', $poll_id, !empty($question['success']) ? 'question-added' : 'error');
    }

    public function delete_question(): void { $this->post_guard('appolls_delete_question', false); $poll_id = absint($_GET['poll_id'] ?? 0); APPolls_Lifecycle::delete_question(absint($_GET['question_id'] ?? 0)); $this->redirect('appolls-new', $poll_id, 'question-deleted'); }

    public function export(): void
    {
        $this->post_guard('appolls_export', false); $poll_id = absint($_GET['poll_id'] ?? 0); $poll = APPolls_Lifecycle::poll_by_id($poll_id);
        if (!$poll) { wp_die('Poll not found.'); }
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . sanitize_file_name($poll['poll_key']) . '-responses.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, ['response_key','submitted_at','email','question','choice','answer','other']); foreach (APPolls_Lifecycle::export_rows($poll_id) as $row) { fputcsv($out, $row); } fclose($out); exit;
    }

    private function results_table(array $results): void { if (empty($results['rows'])) { echo '<p>No responses yet.</p>'; return; } echo '<table class="widefat striped"><thead><tr><th>Choice</th><th>Votes</th><th>Share</th></tr></thead><tbody>'; foreach ($results['rows'] as $row) { echo '<tr><td>' . esc_html($row['label'] ?: $row['answer_value'] ?? 'Response') . '</td><td>' . esc_html((string) $row['votes']) . '</td><td>' . esc_html((string) $row['percent']) . '%</td></tr>'; } echo '</tbody></table>'; }
    private function guard(): void { if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to manage polls.', 'appolls')); } }
    private function post_guard(string $action, bool $post = true): void { $this->guard(); check_admin_referer($action); }
    private function redirect(string $page, int $poll_id, string $notice): void { wp_safe_redirect(add_query_arg(array_filter(['page'=>$page,'poll_id'=>$poll_id ?: null,'appolls_notice'=>$notice]), admin_url('admin.php'))); exit; }
    private function notice(): void { $notice = sanitize_key($_GET['appolls_notice'] ?? ''); if (!$notice) { return; } $messages = ['saved'=>'Poll saved.','question-added'=>'Question added.','question-deleted'=>'Question deleted.','error'=>'The change could not be saved.']; echo '<div class="notice ' . ($notice === 'error' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($messages[$notice] ?? 'Done.') . '</p></div>'; }
    private function datetime_value(string $value): string { return $value ? gmdate('Y-m-d\TH:i', strtotime($value)) : ''; }
}
