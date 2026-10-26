<?php
/**
 * apPolls domain contract.
 *
 * @package apPolls
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APPolls_Contract')) {
    final class APPolls_Contract
    {
        /**
         * @return array<string,mixed>
         */
        public static function component(): array
        {
            return [
                'key' => 'appolls',
                'name' => 'apPolls',
                'layer' => 'standalone_plugin',
                'status' => 'commercial_1_0',
                'version' => APPOLLS_VERSION,
                'responsibility' => 'WordPress polls, profile qualification capture, voting guardrails, results, analytics, exports, embeds, AI-assisted question generation, and optional audience routing.',
                'stands_alone' => true,
                'depends_on' => [],
                'optional_integrations' => ['apCRM', 'apSubscriptions', 'apForms', 'apSurveys', 'apAI', 'apMailer', 'apSMS', 'apCampaignStudio', 'apPortal'],
                'theme_boundary' => self::theme_boundary_contract(),
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function domain(): array
        {
            return [
                'owns' => [
                    'polls',
                    'poll_questions',
                    'poll_choices',
                    'poll_embeds',
                    'poll_sessions',
                    'poll_responses',
                    'poll_response_answers',
                    'profile_links',
                    'sync_routes',
                    'analytics_snapshots',
                    'export_jobs',
                    'ai_jobs',
                    'ai_generation_schedules',
                    'ai_draft_batches',
                    'ai_draft_items',
                    'moderation_events',
                ],
                'does_not_own' => [
                    'CRM contact truth',
                    'subscriber truth',
                    'form submission truth',
                    'survey research truth',
                    'AI provider credentials',
                    'email or SMS transport truth',
                    'portal authentication',
                ],
                'competitive_baseline' => [
                    'unlimited_polls',
                    'single_choice',
                    'multiple_choice',
                    'yes_no',
                    'rating',
                    'likert',
                    'open_text',
                    'other_option',
                    'scheduling',
                    'result_visibility_rules',
                    'period_end_auto_results_transition',
                    'results_display_modes',
                    'bar_pie_donut_list_table_results_ready',
                    'percentage_only_results',
                    'optional_response_count_reveal',
                    'anonymous_or_identified_responses',
                    'cookie_ip_user_email_vote_guardrails',
                    'shortcode_widget_block_ready_embeds',
                    'real_time_results_ready',
                    'charts_ready_analytics',
                    'export_jobs',
                    'lead_and_profile_capture',
                    'crm_subscription_profile_routes',
                    'AI_question_generation',
                    'AI_scheduled_draft_generation',
                    'AI_human_approval_workflow',
                    'AI_auto_publish_policy',
                    'AI_result_summaries',
                ],
                'question_types' => self::question_types(),
                'integration_contract' => self::integration_contract(),
                'configuration_contract' => self::configuration_contract(),
                'hard_rules' => self::hard_rules(),
                'table_plan' => self::table_plan(),
            ];
        }

        /**
         * @return array<int,string>
         */
        public static function question_types(): array
        {
            return ['single_choice', 'multiple_choice', 'yes_no', 'rating', 'likert', 'open_text'];
        }

        /**
         * @return array<string,string>
         */
        public static function integration_contract(): array
        {
            return [
                'apCRM' => 'Can receive qualification answers as mapped profile evidence; apPolls never owns contact truth.',
                'apSubscriptions' => 'Can receive subscriber qualification and preference evidence.',
                'apForms' => 'Can embed poll questions inside forms or hand off lead capture.',
                'apSurveys' => 'Owns longer research instruments; apPolls owns lightweight rotating polls.',
                'apAI' => 'Can generate question drafts, option sets, and response summaries when configured.',
                'apMailer' => 'Can send poll invitations or result notices.',
                'apSMS' => 'Can send poll invitations or alerts later.',
                'apPortal' => 'Can expose authenticated poll experiences later.',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function configuration_contract(): array
        {
            return [
                'resolution_order' => ['shared_pubmotor_or_apcrm_settings', 'appolls_local_settings', 'not_configured'],
                'shared_setting_candidates' => ['ai_provider', 'default_profile_route', 'default_export_policy', 'email_invite_route', 'sms_invite_route'],
                'privacy_defaults' => [
                    'store_ip_hash' => true,
                    'store_raw_ip' => false,
                    'anonymous_allowed' => true,
                    'email_capture_optional' => true,
                ],
            ];
        }

        /**
         * @return array<int,string>
         */
        public static function hard_rules(): array
        {
            return [
                'apPolls must work on plain WordPress without apCRM.',
                'Poll definitions must not hardcode ArtistPro, MMR, SBO, or any publisher-specific questions.',
                'Profile routing creates evidence or mapped handoffs only; CRM/subscription systems own final profile truth.',
                'Anonymous responses can be linked later only through explicit visitor/profile link records.',
                'Vote blocking must not silently discard evidence; duplicate or blocked attempts should be classified.',
                'AI is optional; users may write every question manually.',
                'AI provider credentials are owned by apAI or a shared settings/secret layer, not apPolls.',
                'Scheduled AI generation creates draft polls by default; automatic publishing requires explicit auto-publish policy.',
                'AI schedules must support do-not-repeat and audience constraints so the system does not ask stale or sensitive questions.',
                'At the end of a poll period, display may automatically transition from voting to results only according to the poll transition policy.',
                'Result display should default to percentages without revealing response counts unless explicitly configured.',
                'Theme changes must not affect voting, response storage, profile routing, or analytics truth.',
            ];
        }

        /**
         * @return array<string,string>
         */
        public static function table_plan(): array
        {
            return [
                'polls' => 'appolls_polls',
                'questions' => 'appolls_questions',
                'choices' => 'appolls_choices',
                'embeds' => 'appolls_embeds',
                'sessions' => 'appolls_sessions',
                'responses' => 'appolls_responses',
                'response_answers' => 'appolls_response_answers',
                'profile_links' => 'appolls_profile_links',
                'sync_routes' => 'appolls_sync_routes',
                'analytics_snapshots' => 'appolls_analytics_snapshots',
                'export_jobs' => 'appolls_export_jobs',
                'ai_jobs' => 'appolls_ai_jobs',
                'ai_generation_schedules' => 'appolls_ai_generation_schedules',
                'ai_draft_batches' => 'appolls_ai_draft_batches',
                'ai_draft_items' => 'appolls_ai_draft_items',
                'moderation_events' => 'appolls_moderation_events',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function theme_boundary_contract(): array
        {
            return [
                'themes_are_presentation_only' => true,
                'plugin_owned_surfaces' => ['data', 'schema', 'routes', 'permissions', 'workflow_state', 'integrations', 'business_rules'],
                'theme_owned_surfaces' => ['visual_rendering', 'CSS', 'template_slot_markup'],
                'hard_rules' => [
                    'No voting, eligibility, duplication, sync, profile mapping, analytics, or AI-routing behavior may live in a theme.',
                    'Themes may style poll output but must not decide or mutate poll state.',
                ],
            ];
        }
    }
}
