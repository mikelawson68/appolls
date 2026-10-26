<?php
/** apPolls frontend runtime. @package apPolls */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class APPolls_Plugin
{
    private static ?self $instance = null;
    public static function instance(): self { return self::$instance ?: (self::$instance = new self()); }
    public function boot(): void
    {
        add_shortcode('appolls', [$this, 'shortcode']);
        add_action('wp_ajax_appolls_vote', [$this, 'ajax_vote']);
        add_action('wp_ajax_nopriv_appolls_vote', [$this, 'ajax_vote']);
    }

    private function enqueue(): void
    {
        wp_enqueue_style('appolls', plugin_dir_url(APPOLLS_PLUGIN_FILE) . 'assets/appolls.css', [], APPOLLS_VERSION);
        wp_enqueue_script('appolls', plugin_dir_url(APPOLLS_PLUGIN_FILE) . 'assets/appolls.js', [], APPOLLS_VERSION, true);
        wp_localize_script('appolls', 'apPollsConfig', ['ajaxUrl' => admin_url('admin-ajax.php')]);
    }

    /** @param array<string,mixed> $atts */
    public function shortcode($atts): string
    {
        $this->enqueue();
        $atts = shortcode_atts(['poll' => '', 'show_results' => 'auto'], $atts, 'appolls');
        $poll = APPolls_Lifecycle::poll_by_key((string) $atts['poll']);
        if (!$poll || ($poll['status'] ?? '') !== 'active') { return $this->message('This poll is not available.'); }
        if ($this->not_started($poll)) { return $this->message('This poll has not opened yet.'); }
        $closed = APPolls_Lifecycle::poll_is_closed($poll);
        if ($closed) { return $this->render_results($poll, 'This poll is closed.'); }
        $questions = APPolls_Lifecycle::questions_for_poll((int) $poll['id']);
        if (!$questions) { return $this->message('This poll does not have any questions yet.'); }
        ob_start(); ?>
        <section class="appolls-shell" aria-labelledby="appolls-title-<?php echo esc_attr((string) $poll['id']); ?>">
        <form class="appolls-poll" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <header class="appolls-header"><span class="appolls-eyebrow">Poll</span><h2 id="appolls-title-<?php echo esc_attr((string) $poll['id']); ?>"><?php echo esc_html($poll['name']); ?></h2><?php if ($poll['description']): ?><p><?php echo esc_html($poll['description']); ?></p><?php endif; ?></header>
            <input type="hidden" name="action" value="appolls_vote"><input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('appolls_vote')); ?>"><input type="hidden" name="poll_key" value="<?php echo esc_attr($poll['poll_key']); ?>">
            <?php foreach ($questions as $question) { $this->render_question($question); } ?>
            <div class="appolls-actions"><button type="submit" class="appolls-submit">Submit response</button><span class="appolls-privacy">Your response is stored on this website.</span></div><div class="appolls-feedback" role="status" aria-live="polite"></div>
        </form></section><?php return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $question */
    private function render_question(array $question): void
    {
        $id = (int) $question['id']; $type = (string) $question['question_type']; $required = !empty($question['required']);
        ?><fieldset class="appolls-question"><legend><?php echo esc_html($question['question_text']); ?><?php if ($required): ?><span aria-hidden="true"> *</span><?php endif; ?></legend><?php if ($question['helper_text']): ?><p class="appolls-help"><?php echo esc_html($question['helper_text']); ?></p><?php endif;
        if ($type === 'open_text') { ?><textarea name="answers[<?php echo esc_attr((string) $id); ?>]" rows="4" <?php echo $required ? 'required' : ''; ?>></textarea><?php }
        else { foreach ((array) $question['choices'] as $choice) { $input = $type === 'multiple_choice' ? 'checkbox' : 'radio'; ?><label class="appolls-choice"><input type="<?php echo esc_attr($input); ?>" name="answers[<?php echo esc_attr((string) $id); ?>]<?php echo $input === 'checkbox' ? '[]' : ''; ?>" value="<?php echo esc_attr((string) $choice['id']); ?>" <?php echo $required && $input === 'radio' ? 'required' : ''; ?>><span><?php echo esc_html($choice['label']); ?></span></label><?php } }
        ?></fieldset><?php
    }

    /** @param array<string,mixed> $poll */
    private function render_results(array $poll, string $lead = 'Thanks for voting.'): string
    {
        if (($poll['result_visibility'] ?? '') === 'never' || (($poll['closed_display_mode'] ?? '') === 'hide')) { return $this->message($lead); }
        $results = APPolls_Lifecycle::live_results((int) $poll['id']); $questions = APPolls_Lifecycle::questions_for_poll((int) $poll['id']); $by_question = [];
        foreach ($results['rows'] as $row) { $by_question[(int) $row['question_id']][] = $row; }
        $response_count = (int) $results['response_count'];
        ob_start(); ?><section class="appolls-shell appolls-results"><header class="appolls-header"><span class="appolls-eyebrow">Results</span><h2><?php echo esc_html($poll['name']); ?></h2><p><?php echo esc_html($lead); ?><?php if (!empty($poll['reveal_response_count'])): ?> <?php echo esc_html((string) $response_count); ?> <?php echo $response_count === 1 ? 'response' : 'responses'; ?>.<?php endif; ?></p></header>
        <?php foreach ($questions as $question): if (empty($by_question[(int) $question['id']])) { continue; } ?><div class="appolls-result-question"><h3><?php echo esc_html($question['question_text']); ?></h3><?php foreach ($by_question[(int) $question['id']] as $row): ?><div class="appolls-result"><div><span><?php echo esc_html($row['label'] ?: 'Response'); ?></span><strong><?php echo esc_html((string) $row['percent']); ?>%</strong></div><div class="appolls-bar"><i style="width:<?php echo esc_attr((string) min(100, (float) $row['percent'])); ?>%"></i></div></div><?php endforeach; ?></div><?php endforeach; ?></section><?php return (string) ob_get_clean();
    }

    public function ajax_vote(): void
    {
        check_ajax_referer('appolls_vote', 'nonce'); $post = wp_unslash($_POST); $poll = APPolls_Lifecycle::poll_by_key(sanitize_key($post['poll_key'] ?? ''));
        if (!$poll) { wp_send_json_error(['message' => 'Poll not found.'], 404); }
        if (APPolls_Lifecycle::poll_is_closed($poll) || $this->not_started($poll)) { wp_send_json_error(['message' => 'This poll is not accepting responses.'], 409); }
        $visitor = sanitize_text_field(wp_unslash($_COOKIE['appolls_visitor'] ?? '')); if (!$visitor) { $visitor = wp_generate_uuid4(); setcookie('appolls_visitor', $visitor, ['expires'=>time()+YEAR_IN_SECONDS,'path'=>COOKIEPATH ?: '/','domain'=>COOKIE_DOMAIN,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']); }
        $session = APPolls_Lifecycle::get_or_create_session(['visitor_id'=>$visitor,'user_id'=>get_current_user_id(),'email'=>sanitize_email($post['email'] ?? ''),'ip'=>sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),'user_agent'=>sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''))]);
        $questions = APPolls_Lifecycle::questions_for_poll((int) $poll['id']); $submitted = is_array($post['answers'] ?? null) ? $post['answers'] : []; $answers = [];
        $answers = self::validate_answers($questions, $submitted);
        if (is_wp_error($answers)) { wp_send_json_error(['message' => $answers->get_error_message()], 422); }
        if (!$answers) { wp_send_json_error(['message'=>'Choose an answer before submitting.'], 422); }
        $result = APPolls_Lifecycle::record_response((int) $poll['id'], (int) ($session['session_id'] ?? 0), $answers, ['respondent_email'=>sanitize_email($post['email'] ?? ''),'source_url'=>wp_get_referer() ?: home_url('/')]);
        if (empty($result['success'])) { wp_send_json_error(['message'=>$result['message'] ?? 'Could not record response.'], 400); }
        if (($result['duplicate_status'] ?? 'unique') !== 'unique') { wp_send_json_error(['message'=>'A response from this visitor has already been recorded.'], 409); }
        $show = in_array($poll['result_visibility'], ['after_vote','always'], true); wp_send_json_success(['message'=>'Thanks—your response was recorded.','html'=>$show ? $this->render_results($poll) : $this->message('Thanks—your response was recorded.')]);
    }

    /** Validate each required question after normalization, not just raw input presence. */
    public static function validate_answers(array $questions, array $submitted)
    {
        $answers = [];
        foreach ($questions as $question) {
            $qid = (int) $question['id'];
            $value = $submitted[$qid] ?? null;
            $required = !empty($question['required']);
            if ($value === null || $value === '' || $value === []) {
                if ($required) { return new WP_Error('appolls_required', 'Please answer every required question.'); }
                continue;
            }
            if ($question['question_type'] === 'open_text') {
                if (!is_string($value)) { return new WP_Error('appolls_invalid_answer', 'Please enter a valid text answer.'); }
                $text = trim(sanitize_textarea_field($value));
                if ($text === '') {
                    if ($required) { return new WP_Error('appolls_required', 'Please answer every required question.'); }
                    continue;
                }
                $answers[] = ['question_id'=>$qid, 'choice_id'=>0, 'answer_value'=>$text, 'profile_field_key'=>$question['profile_field_key']];
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            if ($question['question_type'] !== 'multiple_choice' && count($values) !== 1) {
                return new WP_Error('appolls_single_choice', 'Choose one answer for each single-choice question.');
            }
            $allowed = array_map(static fn($choice) => (int) $choice['id'], (array) $question['choices']);
            $selected = [];
            foreach ($values as $choice) {
                if ((!is_string($choice) && !is_int($choice)) || !ctype_digit((string) $choice) || !in_array((int) $choice, $allowed, true)) {
                    return new WP_Error('appolls_invalid_choice', 'Choose an available answer.');
                }
                $selected[] = (int) $choice;
            }
            foreach (array_unique($selected) as $choice_id) {
                $answers[] = ['question_id'=>$qid, 'choice_id'=>$choice_id, 'answer_value'=>(string) $choice_id, 'profile_field_key'=>$question['profile_field_key']];
            }
        }
        return $answers;
    }

    private function not_started(array $poll): bool { return !empty($poll['start_at']) && strtotime((string) $poll['start_at']) > time(); }
    private function message(string $message): string { return '<div class="appolls-shell appolls-message" role="status"><p>' . esc_html($message) . '</p></div>'; }
}
