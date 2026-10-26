<?php
/**
 * apPolls lifecycle operations.
 *
 * @package apPolls
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APPolls_Lifecycle')) {
    final class APPolls_Lifecycle
    {
        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_poll(array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $poll_key = self::code($data['poll_key'] ?? '');
            $name = self::text($data['name'] ?? '');
            if ($poll_key === '' || $name === '') {
                return self::error('poll_required', 'Poll key and name are required.');
            }

            $wpdb->insert($t['polls'], [
                'poll_key' => $poll_key,
                'name' => $name,
                'description' => self::textarea($data['description'] ?? ''),
                'status' => self::status($data['status'] ?? 'draft', ['draft', 'active', 'inactive', 'archived'], 'draft'),
                'audience_key' => self::code($data['audience_key'] ?? ''),
                'start_at' => self::date_or_null($data['start_at'] ?? null),
                'end_at' => self::date_or_null($data['end_at'] ?? null),
                'result_visibility' => self::status($data['result_visibility'] ?? 'after_vote', ['never', 'after_vote', 'always', 'closed_only'], 'after_vote'),
                'closed_display_mode' => self::status($data['closed_display_mode'] ?? 'results', ['results', 'thank_you', 'hide', 'archive_link'], 'results'),
                'results_style' => self::status($data['results_style'] ?? 'bar', ['bar', 'pie', 'donut', 'list', 'table'], 'bar'),
                'results_metric' => self::status($data['results_metric'] ?? 'percent_only', ['percent_only', 'count_and_percent', 'count_only'], 'percent_only'),
                'reveal_response_count' => !empty($data['reveal_response_count']) ? 1 : 0,
                'transition_policy' => self::status($data['transition_policy'] ?? 'auto_on_close', ['auto_on_close', 'manual', 'never'], 'auto_on_close'),
                'vote_policy_json' => self::json($data['vote_policy'] ?? ['cookie' => true, 'ip_hash' => true, 'user' => true, 'email' => true]),
                'settings_json' => self::json($data['settings'] ?? []),
                'created_by_user_id' => absint($data['created_by_user_id'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

            return self::insert_result('poll_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function add_question(int $poll_id, array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $question_key = self::code($data['question_key'] ?? '');
            $question_text = self::textarea($data['question_text'] ?? '');
            $question_type = self::status($data['question_type'] ?? 'single_choice', APPolls_Contract::question_types(), 'single_choice');
            if ($poll_id <= 0 || $question_key === '' || $question_text === '') {
                return self::error('question_required', 'Poll, question key, and question text are required.');
            }

            $wpdb->insert($t['questions'], [
                'poll_id' => $poll_id,
                'question_key' => $question_key,
                'question_text' => $question_text,
                'helper_text' => self::textarea($data['helper_text'] ?? ''),
                'question_type' => $question_type,
                'required' => !empty($data['required']) ? 1 : 0,
                'allow_other' => !empty($data['allow_other']) ? 1 : 0,
                'profile_field_key' => self::code($data['profile_field_key'] ?? ''),
                'sort_order' => absint($data['sort_order'] ?? 0),
                'settings_json' => self::json($data['settings'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s']);

            return self::insert_result('question_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function add_choice(int $question_id, array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $choice_key = self::code($data['choice_key'] ?? '');
            $label = self::text($data['label'] ?? '');
            if ($question_id <= 0 || $choice_key === '' || $label === '') {
                return self::error('choice_required', 'Question, choice key, and label are required.');
            }

            $wpdb->insert($t['choices'], [
                'question_id' => $question_id,
                'choice_key' => $choice_key,
                'label' => $label,
                'value' => self::text($data['value'] ?? $choice_key),
                'sort_order' => absint($data['sort_order'] ?? 0),
                'metadata_json' => self::json($data['metadata'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);

            return self::insert_result('choice_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_embed(int $poll_id, array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $embed_key = self::code($data['embed_key'] ?? '');
            if ($poll_id <= 0 || $embed_key === '') {
                return self::error('embed_required', 'Poll and embed key are required.');
            }

            $wpdb->insert($t['embeds'], [
                'poll_id' => $poll_id,
                'embed_key' => $embed_key,
                'embed_type' => self::status($data['embed_type'] ?? 'shortcode', ['shortcode', 'widget', 'block', 'api'], 'shortcode'),
                'placement' => self::code($data['placement'] ?? ''),
                'status' => 'active',
                'settings_json' => self::json($data['settings'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            return self::insert_result('embed_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function get_or_create_session(array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $visitor_id = self::text($data['visitor_id'] ?? '');
            $email = sanitize_email((string) ($data['email'] ?? ''));
            $session_key = self::code($data['session_key'] ?? md5($visitor_id . '|' . $email . '|' . (int) ($data['user_id'] ?? 0)));
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['sessions']} WHERE session_key = %s", $session_key));
            if ($existing > 0) {
                $wpdb->update($t['sessions'], ['last_seen_at' => $now, 'updated_at' => $now], ['id' => $existing]);
                return ['success' => true, 'session_id' => $existing, 'existing' => true];
            }

            $wpdb->insert($t['sessions'], [
                'session_key' => $session_key,
                'visitor_id' => $visitor_id,
                'user_id' => absint($data['user_id'] ?? 0),
                'email_hash' => $email ? hash('sha256', strtolower($email)) : '',
                'ip_hash' => !empty($data['ip']) ? hash('sha256', (string) $data['ip']) : '',
                'user_agent_hash' => !empty($data['user_agent']) ? hash('sha256', (string) $data['user_agent']) : '',
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'metadata_json' => self::json($data['metadata'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            return self::insert_result('session_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<int,array<string,mixed>> $answers
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function record_response(int $poll_id, int $session_id, array $answers, array $data = []): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            if ($poll_id <= 0 || !$answers) {
                return self::error('response_required', 'Poll and answers are required.');
            }

            $email = sanitize_email((string) ($data['respondent_email'] ?? ''));
            $response_key = self::code($data['response_key'] ?? 'resp_' . wp_generate_uuid4());
            $duplicate_status = self::duplicate_status($poll_id, $session_id, $email);

            $wpdb->insert($t['responses'], [
                'poll_id' => $poll_id,
                'session_id' => max(0, $session_id),
                'response_key' => $response_key,
                'respondent_email' => $email,
                'respondent_ref' => self::text($data['respondent_ref'] ?? ''),
                'status' => self::status($data['status'] ?? 'complete', ['complete', 'partial', 'blocked', 'quarantined'], 'complete'),
                'duplicate_status' => $duplicate_status,
                'source_url' => esc_url_raw((string) ($data['source_url'] ?? '')),
                'submitted_at' => $now,
                'synced_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            $response_id = (int) $wpdb->insert_id;
            if ($response_id <= 0) {
                return self::error('response_insert_failed', 'Could not create response.');
            }

            foreach ($answers as $answer) {
                $wpdb->insert($t['response_answers'], [
                    'response_id' => $response_id,
                    'poll_id' => $poll_id,
                    'question_id' => absint($answer['question_id'] ?? 0),
                    'choice_id' => absint($answer['choice_id'] ?? 0),
                    'answer_value' => self::textarea($answer['answer_value'] ?? ''),
                    'other_text' => self::textarea($answer['other_text'] ?? ''),
                    'profile_field_key' => self::code($answer['profile_field_key'] ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']);
            }

            return ['success' => true, 'response_id' => $response_id, 'duplicate_status' => $duplicate_status];
        }

        /**
         * @return array<string,mixed>
         */
        public static function create_sync_route(string $route_key, string $name, string $target_system, string $target_module, array $field_map): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $wpdb->insert($t['sync_routes'], [
                'route_key' => self::code($route_key),
                'name' => self::text($name),
                'target_system' => self::code($target_system),
                'target_module' => self::code($target_module),
                'field_map_json' => self::json($field_map),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            return self::insert_result('sync_route_id', (int) $wpdb->insert_id);
        }

        /**
         * @return array<string,mixed>
         */
        public static function link_profile(int $session_id, string $visitor_id, string $email, string $target_system, string $target_ref): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $email = sanitize_email($email);
            if (!is_email($email)) {
                return self::error('email_required', 'Valid email is required.');
            }
            $wpdb->insert($t['profile_links'], [
                'session_id' => max(0, $session_id),
                'visitor_id' => self::text($visitor_id),
                'email' => $email,
                'target_system' => self::code($target_system),
                'target_ref' => self::text($target_ref),
                'linked_at' => $now,
                'metadata_json' => self::json([]),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            return self::insert_result('profile_link_id', (int) $wpdb->insert_id);
        }

        /**
         * @return array<string,mixed>
         */
        public static function create_analytics_snapshot(int $poll_id, string $snapshot_key): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $response_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['responses']} WHERE poll_id = %d", $poll_id));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT question_id, choice_id, answer_value, COUNT(*) AS count FROM {$t['response_answers']} WHERE poll_id = %d GROUP BY question_id, choice_id, answer_value ORDER BY question_id ASC, count DESC",
                $poll_id
            ), ARRAY_A);
            $totals = [];
            foreach ($rows as $row) {
                $question_id = (string) ($row['question_id'] ?? '0');
                $totals[$question_id] = ($totals[$question_id] ?? 0) + (int) ($row['count'] ?? 0);
            }
            foreach ($rows as &$row) {
                $question_id = (string) ($row['question_id'] ?? '0');
                $total = max(1, (int) ($totals[$question_id] ?? 1));
                $row['percent'] = round(((int) ($row['count'] ?? 0) / $total) * 100, 2);
            }
            $wpdb->insert($t['analytics_snapshots'], [
                'poll_id' => $poll_id,
                'snapshot_key' => self::code($snapshot_key),
                'response_count' => $response_count,
                'results_json' => self::json($rows),
                'segment_json' => self::json([]),
                'generated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);
            return self::insert_result('analytics_snapshot_id', (int) $wpdb->insert_id) + ['response_count' => $response_count];
        }

        /**
         * @return array<string,mixed>
         */
        public static function queue_export(int $poll_id, string $format, int $user_id = 0): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $wpdb->insert($t['export_jobs'], [
                'export_key' => 'export_' . strtolower(wp_generate_password(8, false, false)),
                'poll_id' => max(0, $poll_id),
                'export_format' => self::status($format, ['csv', 'json', 'xlsx'], 'csv'),
                'status' => 'queued',
                'file_ref' => '',
                'requested_by_user_id' => max(0, $user_id),
                'filters_json' => self::json([]),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
            return self::insert_result('export_job_id', (int) $wpdb->insert_id);
        }

        /**
         * @return array<string,mixed>
         */
        public static function queue_ai_job(int $poll_id, string $job_type, array $context, array $result = []): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $wpdb->insert($t['ai_jobs'], [
                'ai_job_key' => 'ai_' . strtolower(wp_generate_password(8, false, false)),
                'poll_id' => max(0, $poll_id),
                'job_type' => self::status($job_type, ['question_generation', 'option_generation', 'result_summary', 'bias_check'], 'question_generation'),
                'provider_ref' => self::text($context['provider_ref'] ?? 'apAI:default'),
                'prompt_context_json' => self::json($context),
                'result_json' => self::json($result),
                'status' => $result ? 'complete' : 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            return self::insert_result('ai_job_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public static function create_ai_generation_schedule(array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            $schedule_key = self::code($data['schedule_key'] ?? '');
            $name = self::text($data['name'] ?? '');
            if ($schedule_key === '' || $name === '') {
                return self::error('schedule_required', 'Schedule key and name are required.');
            }

            $wpdb->insert($t['ai_generation_schedules'], [
                'schedule_key' => $schedule_key,
                'name' => $name,
                'audience_key' => self::code($data['audience_key'] ?? ''),
                'cadence' => self::status($data['cadence'] ?? 'weekly', ['daily', 'weekly', 'monthly', 'manual'], 'weekly'),
                'next_run_at' => self::date_or_null($data['next_run_at'] ?? '+1 week'),
                'last_run_at' => null,
                'provider_ref' => self::text($data['provider_ref'] ?? 'apAI:default'),
                'approval_policy' => self::status($data['approval_policy'] ?? 'human_required', ['human_required', 'auto_publish_allowed', 'manual_only'], 'human_required'),
                'auto_publish' => !empty($data['auto_publish']) ? 1 : 0,
                'retire_previous_policy' => self::status($data['retire_previous_policy'] ?? 'manual', ['manual', 'retire_same_audience', 'retire_same_embed'], 'manual'),
                'constraints_json' => self::json($data['constraints'] ?? []),
                'status' => self::status($data['status'] ?? 'active', ['active', 'paused', 'disabled'], 'active'),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);

            return self::insert_result('ai_generation_schedule_id', (int) $wpdb->insert_id);
        }

        /**
         * @param array<int,array<string,mixed>> $draft_items
         * @return array<string,mixed>
         */
        public static function generate_ai_draft_batch(int $schedule_id, array $draft_items, array $context = []): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            if ($schedule_id <= 0 || !$draft_items) {
                return self::error('draft_batch_required', 'Schedule and draft items are required.');
            }

            $ai_job = self::queue_ai_job(0, 'question_generation', $context, ['draft_count' => count($draft_items)]);
            $batch_key = 'batch_' . strtolower(wp_generate_password(8, false, false));
            $wpdb->insert($t['ai_draft_batches'], [
                'schedule_id' => $schedule_id,
                'batch_key' => $batch_key,
                'ai_job_id' => (int) ($ai_job['ai_job_id'] ?? 0),
                'status' => 'draft',
                'generated_at' => $now,
                'approved_at' => null,
                'published_poll_id' => 0,
                'metadata_json' => self::json($context),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
            $batch_id = (int) $wpdb->insert_id;
            if ($batch_id <= 0) {
                return self::error('draft_batch_insert_failed', 'Could not create AI draft batch.');
            }

            $item_ids = [];
            foreach ($draft_items as $index => $item) {
                $wpdb->insert($t['ai_draft_items'], [
                    'draft_batch_id' => $batch_id,
                    'item_key' => self::code($item['item_key'] ?? 'item_' . ($index + 1)),
                    'proposed_question' => self::textarea($item['proposed_question'] ?? ''),
                    'proposed_type' => self::status($item['proposed_type'] ?? 'single_choice', APPolls_Contract::question_types(), 'single_choice'),
                    'proposed_choices_json' => self::json($item['proposed_choices'] ?? []),
                    'profile_field_key' => self::code($item['profile_field_key'] ?? ''),
                    'approval_status' => 'pending',
                    'approved_by_user_id' => 0,
                    'approved_at' => null,
                    'rejection_reason' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
                $item_ids[] = (int) $wpdb->insert_id;
            }

            $wpdb->update($t['ai_generation_schedules'], ['last_run_at' => $now, 'updated_at' => $now], ['id' => $schedule_id]);
            return ['success' => true, 'ai_draft_batch_id' => $batch_id, 'ai_job' => $ai_job, 'draft_item_ids' => $item_ids];
        }

        /**
         * @return array<string,mixed>
         */
        public static function approve_ai_draft_item(int $draft_item_id, int $user_id = 0): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $now = self::now();
            if ($draft_item_id <= 0) {
                return self::error('draft_item_required', 'Draft item is required.');
            }
            $wpdb->update($t['ai_draft_items'], [
                'approval_status' => 'approved',
                'approved_by_user_id' => max(0, $user_id),
                'approved_at' => $now,
                'updated_at' => $now,
            ], ['id' => $draft_item_id], ['%s', '%d', '%s', '%s'], ['%d']);

            return ['success' => true, 'ai_draft_item_id' => $draft_item_id, 'approval_status' => 'approved'];
        }

        /**
         * @return array<string,mixed>
         */
        public static function publish_ai_draft_batch(int $draft_batch_id, string $poll_key, string $name): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $batch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['ai_draft_batches']} WHERE id = %d", $draft_batch_id), ARRAY_A);
            if (!$batch) {
                return self::error('draft_batch_not_found', 'Draft batch not found.');
            }
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['ai_draft_items']} WHERE draft_batch_id = %d AND approval_status = 'approved' ORDER BY id ASC", $draft_batch_id), ARRAY_A);
            if (!$items) {
                return self::error('no_approved_items', 'At least one approved draft item is required.');
            }

            $poll = self::create_poll([
                'poll_key' => $poll_key,
                'name' => $name,
                'status' => 'active',
                'audience_key' => 'ai_generated',
                'settings' => ['source' => 'ai_draft_batch', 'draft_batch_id' => $draft_batch_id],
            ]);
            $poll_id = (int) ($poll['poll_id'] ?? 0);
            foreach ($items as $index => $item) {
                $question = self::add_question($poll_id, [
                    'question_key' => 'ai_question_' . ($index + 1),
                    'question_text' => (string) $item['proposed_question'],
                    'question_type' => (string) $item['proposed_type'],
                    'profile_field_key' => (string) $item['profile_field_key'],
                    'sort_order' => ($index + 1) * 10,
                ]);
                $question_id = (int) ($question['question_id'] ?? 0);
                $choices = json_decode((string) $item['proposed_choices_json'], true);
                foreach (is_array($choices) ? $choices : [] as $choice_index => $choice_label) {
                    self::add_choice($question_id, [
                        'choice_key' => 'choice_' . ($choice_index + 1),
                        'label' => (string) $choice_label,
                        'sort_order' => ($choice_index + 1) * 10,
                    ]);
                }
            }
            $now = self::now();
            $wpdb->update($t['ai_draft_batches'], [
                'status' => 'published',
                'approved_at' => $now,
                'published_poll_id' => $poll_id,
                'updated_at' => $now,
            ], ['id' => $draft_batch_id], ['%s', '%s', '%d', '%s'], ['%d']);

            return ['success' => true, 'poll' => $poll, 'published_poll_id' => $poll_id];
        }

        /**
         * @return array<string,mixed>|null
         */
        public static function poll_by_key(string $poll_key): ?array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $poll = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['polls']} WHERE poll_key = %s", self::code($poll_key)), ARRAY_A);
            return is_array($poll) ? $poll : null;
        }

        /** @return array<string,mixed>|null */
        public static function poll_by_id(int $poll_id): ?array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $poll = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['polls']} WHERE id = %d", $poll_id), ARRAY_A);
            return is_array($poll) ? $poll : null;
        }

        /** @return array<int,array<string,mixed>> */
        public static function all_polls(): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $rows = $wpdb->get_results("SELECT p.*, COUNT(DISTINCT r.id) AS response_count FROM {$t['polls']} p LEFT JOIN {$t['responses']} r ON r.poll_id = p.id AND r.status = 'complete' GROUP BY p.id ORDER BY p.updated_at DESC", ARRAY_A);
            return is_array($rows) ? $rows : [];
        }

        /** @param array<string,mixed> $data */
        public static function update_poll(int $poll_id, array $data): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            if (!self::poll_by_id($poll_id)) {
                return self::error('poll_not_found', 'Poll not found.');
            }
            $updated = $wpdb->update($t['polls'], [
                'name' => self::text($data['name'] ?? ''),
                'description' => self::textarea($data['description'] ?? ''),
                'status' => self::status($data['status'] ?? 'draft', ['draft', 'active', 'inactive', 'archived'], 'draft'),
                'start_at' => self::date_or_null($data['start_at'] ?? null),
                'end_at' => self::date_or_null($data['end_at'] ?? null),
                'result_visibility' => self::status($data['result_visibility'] ?? 'after_vote', ['never', 'after_vote', 'always', 'closed_only'], 'after_vote'),
                'results_style' => self::status($data['results_style'] ?? 'bar', ['bar', 'list', 'table'], 'bar'),
                'results_metric' => self::status($data['results_metric'] ?? 'percent_only', ['percent_only', 'count_and_percent', 'count_only'], 'percent_only'),
                'reveal_response_count' => !empty($data['reveal_response_count']) ? 1 : 0,
                'updated_at' => self::now(),
            ], ['id' => $poll_id]);
            return $updated === false ? self::error('poll_update_failed', 'Could not update poll.') : ['success' => true, 'poll_id' => $poll_id];
        }

        public static function delete_question(int $question_id): bool
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $wpdb->delete($t['choices'], ['question_id' => $question_id], ['%d']);
            return $wpdb->delete($t['questions'], ['id' => $question_id], ['%d']) !== false;
        }

        /** @return array<string,mixed> */
        public static function live_results(int $poll_id): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $response_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['responses']} WHERE poll_id = %d AND status = 'complete' AND duplicate_status = 'unique'", $poll_id));
            $rows = $wpdb->get_results($wpdb->prepare("SELECT a.question_id, a.choice_id, c.label, COUNT(*) AS votes FROM {$t['response_answers']} a LEFT JOIN {$t['choices']} c ON c.id = a.choice_id INNER JOIN {$t['responses']} r ON r.id = a.response_id WHERE a.poll_id = %d AND r.status = 'complete' AND r.duplicate_status = 'unique' GROUP BY a.question_id, a.choice_id, c.label ORDER BY a.question_id, votes DESC", $poll_id), ARRAY_A);
            $totals = [];
            foreach ($rows as $row) {
                $totals[(int) $row['question_id']] = ($totals[(int) $row['question_id']] ?? 0) + (int) $row['votes'];
            }
            foreach ($rows as &$row) {
                $total = max(1, $totals[(int) $row['question_id']] ?? 1);
                $row['percent'] = round(((int) $row['votes'] / $total) * 100, 1);
            }
            return ['response_count' => $response_count, 'rows' => $rows];
        }

        /** @return array<int,array<string,mixed>> */
        public static function export_rows(int $poll_id): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $rows = $wpdb->get_results($wpdb->prepare("SELECT r.response_key, r.submitted_at, r.respondent_email, q.question_text, c.label AS choice_label, a.answer_value, a.other_text FROM {$t['responses']} r INNER JOIN {$t['response_answers']} a ON a.response_id = r.id INNER JOIN {$t['questions']} q ON q.id = a.question_id LEFT JOIN {$t['choices']} c ON c.id = a.choice_id WHERE r.poll_id = %d ORDER BY r.submitted_at DESC, a.id ASC", $poll_id), ARRAY_A);
            return is_array($rows) ? $rows : [];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        public static function questions_for_poll(int $poll_id): array
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['questions']} WHERE poll_id = %d ORDER BY sort_order ASC, id ASC", $poll_id), ARRAY_A);
            foreach ($questions as &$question) {
                $question['choices'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['choices']} WHERE question_id = %d ORDER BY sort_order ASC, id ASC", (int) $question['id']), ARRAY_A);
            }
            return is_array($questions) ? $questions : [];
        }

        /**
         * @param array<string,mixed> $poll
         */
        public static function poll_is_closed(array $poll): bool
        {
            if (($poll['status'] ?? '') !== 'active') {
                return true;
            }
            if (!empty($poll['end_at'])) {
                $end = strtotime((string) $poll['end_at']);
                return $end !== false && $end <= time();
            }
            return false;
        }

        /**
         * @return array<string,int>
         */
        public static function counts(): array
        {
            global $wpdb;
            $counts = [];
            foreach (APPolls_Schema::tables() as $key => $table) {
                $counts[$key] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            }
            return $counts;
        }

        private static function duplicate_status(int $poll_id, int $session_id, string $email): string
        {
            global $wpdb;
            $t = APPolls_Schema::tables();
            if ($session_id > 0) {
                $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['responses']} WHERE poll_id = %d AND session_id = %d", $poll_id, $session_id));
                if ($exists > 0) {
                    return 'duplicate_session';
                }
            }
            if ($email !== '') {
                $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['responses']} WHERE poll_id = %d AND respondent_email = %s", $poll_id, $email));
                if ($exists > 0) {
                    return 'duplicate_email';
                }
            }
            return 'unique';
        }

        private static function now(): string
        {
            return current_time('mysql', true);
        }

        private static function code($value): string
        {
            return sanitize_key((string) $value);
        }

        private static function text($value): string
        {
            return sanitize_text_field((string) $value);
        }

        private static function textarea($value): string
        {
            return sanitize_textarea_field((string) $value);
        }

        private static function json($value): string
        {
            return (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        /**
         * @param mixed $value
         * @param array<int,string> $allowed
         */
        private static function status($value, array $allowed, string $default): string
        {
            $value = self::code($value);
            return in_array($value, $allowed, true) ? $value : $default;
        }

        private static function date_or_null($value): ?string
        {
            if (!$value) {
                return null;
            }
            $timestamp = strtotime((string) $value);
            return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
        }

        /**
         * @return array<string,mixed>
         */
        private static function insert_result(string $key, int $id): array
        {
            if ($id <= 0) {
                return self::error('insert_failed', 'Could not insert record.');
            }
            return ['success' => true, $key => $id];
        }

        /**
         * @return array<string,mixed>
         */
        private static function error(string $code, string $message): array
        {
            return ['success' => false, 'code' => $code, 'message' => $message];
        }
    }
}
