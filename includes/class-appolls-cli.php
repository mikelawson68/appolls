<?php
/**
 * apPolls WP-CLI commands.
 *
 * @package apPolls
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APPolls_CLI')) {
    final class APPolls_CLI
    {
        public function install(): void
        {
            APPolls_Schema::install();
            WP_CLI::success('apPolls schema installed.');
        }

        public function contract(): void
        {
            WP_CLI::print_value([
                'component' => APPolls_Contract::component(),
                'domain' => APPolls_Contract::domain(),
            ], ['format' => 'json']);
        }

        public function counts(): void
        {
            WP_CLI::print_value(APPolls_Lifecycle::counts(), ['format' => 'json']);
        }

        public function smoke_lifecycle(): void
        {
            APPolls_Schema::install();
            $run_key = strtolower(wp_generate_password(8, false, false));
            $poll = APPolls_Lifecycle::create_poll([
                'poll_key' => 'reader_qualification_' . $run_key,
                'name' => 'Reader Qualification Poll',
                'description' => 'Reusable publisher profile poll without hardcoded publication assumptions.',
                'status' => 'active',
                'audience_key' => 'publisher_readers',
                'result_visibility' => 'after_vote',
                'closed_display_mode' => 'results',
                'results_style' => 'bar',
                'results_metric' => 'percent_only',
                'reveal_response_count' => false,
                'transition_policy' => 'auto_on_close',
                'vote_policy' => ['cookie' => true, 'ip_hash' => true, 'email' => true, 'user' => true],
            ]);
            $poll_id = (int) ($poll['poll_id'] ?? 0);

            $role = APPolls_Lifecycle::add_question($poll_id, [
                'question_key' => 'primary_role',
                'question_text' => 'What best describes your role?',
                'question_type' => 'single_choice',
                'required' => true,
                'allow_other' => true,
                'profile_field_key' => 'primary_role',
                'sort_order' => 10,
            ]);
            $role_id = (int) ($role['question_id'] ?? 0);
            $choices = [
                APPolls_Lifecycle::add_choice($role_id, ['choice_key' => 'owner', 'label' => 'Owner or executive', 'sort_order' => 10]),
                APPolls_Lifecycle::add_choice($role_id, ['choice_key' => 'buyer', 'label' => 'Buyer or purchasing influencer', 'sort_order' => 20]),
                APPolls_Lifecycle::add_choice($role_id, ['choice_key' => 'educator', 'label' => 'Educator or program leader', 'sort_order' => 30]),
            ];

            $purchase = APPolls_Lifecycle::add_question($poll_id, [
                'question_key' => 'purchase_influence',
                'question_text' => 'Do you influence buying decisions?',
                'question_type' => 'yes_no',
                'profile_field_key' => 'purchase_influence',
                'sort_order' => 20,
            ]);
            $purchase_id = (int) ($purchase['question_id'] ?? 0);
            $yes = APPolls_Lifecycle::add_choice($purchase_id, ['choice_key' => 'yes', 'label' => 'Yes', 'sort_order' => 10]);
            $no = APPolls_Lifecycle::add_choice($purchase_id, ['choice_key' => 'no', 'label' => 'No', 'sort_order' => 20]);

            $embed = APPolls_Lifecycle::create_embed($poll_id, [
                'embed_key' => 'sidebar_' . $run_key,
                'embed_type' => 'shortcode',
                'placement' => 'sidebar',
                'settings' => ['shortcode' => '[appolls poll="reader_qualification_' . $run_key . '"]'],
            ]);
            $session = APPolls_Lifecycle::get_or_create_session([
                'visitor_id' => 'visitor-' . $run_key,
                'email' => 'reader@example.test',
                'ip' => '192.0.2.10',
                'user_agent' => 'apPolls smoke',
            ]);
            $response = APPolls_Lifecycle::record_response($poll_id, (int) ($session['session_id'] ?? 0), [
                ['question_id' => $role_id, 'choice_id' => (int) ($choices[0]['choice_id'] ?? 0), 'answer_value' => 'owner', 'profile_field_key' => 'primary_role'],
                ['question_id' => $purchase_id, 'choice_id' => (int) ($yes['choice_id'] ?? 0), 'answer_value' => 'yes', 'profile_field_key' => 'purchase_influence'],
            ], [
                'respondent_email' => 'reader@example.test',
                'respondent_ref' => 'contact:demo',
                'source_url' => 'https://example.test/poll',
            ]);
            $duplicate = APPolls_Lifecycle::record_response($poll_id, (int) ($session['session_id'] ?? 0), [
                ['question_id' => $purchase_id, 'choice_id' => (int) ($no['choice_id'] ?? 0), 'answer_value' => 'no', 'profile_field_key' => 'purchase_influence'],
            ], [
                'respondent_email' => 'reader@example.test',
                'respondent_ref' => 'contact:demo',
                'source_url' => 'https://example.test/poll',
            ]);
            $route = APPolls_Lifecycle::create_sync_route('crm_profile_' . $run_key, 'CRM Profile Qualification Route', 'apcrm', 'contact_profile', [
                'primary_role' => 'qualification.primary_role',
                'purchase_influence' => 'qualification.purchase_influence',
            ]);
            $link = APPolls_Lifecycle::link_profile((int) ($session['session_id'] ?? 0), 'visitor-' . $run_key, 'reader@example.test', 'apcrm', 'contact:demo');
            $snapshot = APPolls_Lifecycle::create_analytics_snapshot($poll_id, 'snapshot_' . $run_key);
            $export = APPolls_Lifecycle::queue_export($poll_id, 'csv');
            $ai = APPolls_Lifecycle::queue_ai_job($poll_id, 'question_generation', [
                'provider_ref' => 'apAI:default',
                'audience' => 'publisher readers',
                'goal' => 'qualification profile enrichment',
            ], [
                'suggested_questions' => ['What products or services do you budget for this year?'],
            ]);
            $schedule = APPolls_Lifecycle::create_ai_generation_schedule([
                'schedule_key' => 'weekly_reader_poll_' . $run_key,
                'name' => 'Weekly Reader Poll Drafts',
                'audience_key' => 'publisher_readers',
                'cadence' => 'weekly',
                'provider_ref' => 'apAI:default',
                'approval_policy' => 'human_required',
                'auto_publish' => false,
                'constraints' => [
                    'avoid_questions_asked_within_days' => 90,
                    'max_questions' => 3,
                    'sensitive_categories_allowed' => false,
                    'draft_only_by_default' => true,
                ],
            ]);
            $draft_batch = APPolls_Lifecycle::generate_ai_draft_batch((int) ($schedule['ai_generation_schedule_id'] ?? 0), [
                [
                    'item_key' => 'budget_priority',
                    'proposed_question' => 'Which purchase category matters most to your organization this quarter?',
                    'proposed_type' => 'single_choice',
                    'proposed_choices' => ['Equipment', 'Software', 'Training', 'Events'],
                    'profile_field_key' => 'budget_priority',
                ],
                [
                    'item_key' => 'renewal_timing',
                    'proposed_question' => 'When do you usually plan annual purchases?',
                    'proposed_type' => 'single_choice',
                    'proposed_choices' => ['Q1', 'Q2', 'Q3', 'Q4', 'Varies'],
                    'profile_field_key' => 'purchase_timing',
                ],
            ], [
                'provider_ref' => 'apAI:default',
                'source' => 'scheduled_generation_smoke',
                'audience' => 'publisher_readers',
            ]);
            $approved = APPolls_Lifecycle::approve_ai_draft_item((int) (($draft_batch['draft_item_ids'][0] ?? 0)), 1);
            $published = APPolls_Lifecycle::publish_ai_draft_batch((int) ($draft_batch['ai_draft_batch_id'] ?? 0), 'ai_generated_' . $run_key, 'AI Generated Reader Poll');

            WP_CLI::print_value([
                'run_key' => $run_key,
                'poll' => $poll,
                'questions' => [$role, $purchase],
                'choices' => array_merge($choices, [$yes, $no]),
                'embed' => $embed,
                'session' => $session,
                'response' => $response,
                'duplicate_response' => $duplicate,
                'sync_route' => $route,
                'profile_link' => $link,
                'analytics_snapshot' => $snapshot,
                'export_job' => $export,
                'ai_job' => $ai,
                'ai_generation_schedule' => $schedule,
                'ai_draft_batch' => $draft_batch,
                'approved_draft_item' => $approved,
                'published_draft_poll' => $published,
                'counts' => APPolls_Lifecycle::counts(),
                'standalone' => true,
                'crm_required' => false,
            ], ['format' => 'json']);
        }
    }
}
