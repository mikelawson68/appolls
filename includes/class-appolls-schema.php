<?php
/**
 * apPolls schema installer.
 *
 * @package apPolls
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APPolls_Schema')) {
    final class APPolls_Schema
    {
        /**
         * @return array<string,string>
         */
        public static function tables(): array
        {
            global $wpdb;
            $prefix = $wpdb->prefix;

            return [
                'polls' => $prefix . 'appolls_polls',
                'questions' => $prefix . 'appolls_questions',
                'choices' => $prefix . 'appolls_choices',
                'embeds' => $prefix . 'appolls_embeds',
                'sessions' => $prefix . 'appolls_sessions',
                'responses' => $prefix . 'appolls_responses',
                'response_answers' => $prefix . 'appolls_response_answers',
                'profile_links' => $prefix . 'appolls_profile_links',
                'sync_routes' => $prefix . 'appolls_sync_routes',
                'analytics_snapshots' => $prefix . 'appolls_analytics_snapshots',
                'export_jobs' => $prefix . 'appolls_export_jobs',
                'ai_jobs' => $prefix . 'appolls_ai_jobs',
                'ai_generation_schedules' => $prefix . 'appolls_ai_generation_schedules',
                'ai_draft_batches' => $prefix . 'appolls_ai_draft_batches',
                'ai_draft_items' => $prefix . 'appolls_ai_draft_items',
                'moderation_events' => $prefix . 'appolls_moderation_events',
            ];
        }

        public static function install(): void
        {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            $t = self::tables();
            $sql = [];

            $sql[] = "CREATE TABLE {$t['polls']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                description text NULL,
                status varchar(32) NOT NULL DEFAULT 'draft',
                audience_key varchar(100) NOT NULL DEFAULT '',
                start_at datetime NULL,
                end_at datetime NULL,
                result_visibility varchar(32) NOT NULL DEFAULT 'after_vote',
                closed_display_mode varchar(32) NOT NULL DEFAULT 'results',
                results_style varchar(32) NOT NULL DEFAULT 'bar',
                results_metric varchar(32) NOT NULL DEFAULT 'percent_only',
                reveal_response_count tinyint unsigned NOT NULL DEFAULT 0,
                transition_policy varchar(32) NOT NULL DEFAULT 'auto_on_close',
                vote_policy_json longtext NULL,
                settings_json longtext NULL,
                created_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY poll_key (poll_key),
                KEY status (status),
                KEY audience_key (audience_key),
                KEY schedule (start_at, end_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['questions']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_id bigint unsigned NOT NULL,
                question_key varchar(100) NOT NULL,
                question_text text NOT NULL,
                helper_text text NULL,
                question_type varchar(32) NOT NULL DEFAULT 'single_choice',
                required tinyint unsigned NOT NULL DEFAULT 0,
                allow_other tinyint unsigned NOT NULL DEFAULT 0,
                profile_field_key varchar(100) NOT NULL DEFAULT '',
                sort_order int unsigned NOT NULL DEFAULT 0,
                settings_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY poll_question_key (poll_id, question_key),
                KEY poll_id (poll_id),
                KEY profile_field_key (profile_field_key),
                KEY sort_order (sort_order)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['choices']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                question_id bigint unsigned NOT NULL,
                choice_key varchar(100) NOT NULL,
                label varchar(255) NOT NULL,
                value varchar(190) NOT NULL,
                sort_order int unsigned NOT NULL DEFAULT 0,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY question_choice_key (question_id, choice_key),
                KEY question_id (question_id),
                KEY sort_order (sort_order)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['embeds']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_id bigint unsigned NOT NULL,
                embed_key varchar(100) NOT NULL,
                embed_type varchar(32) NOT NULL DEFAULT 'shortcode',
                placement varchar(100) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'active',
                settings_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY embed_key (embed_key),
                KEY poll_id (poll_id),
                KEY embed_type (embed_type),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['sessions']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                session_key varchar(190) NOT NULL,
                visitor_id varchar(190) NOT NULL DEFAULT '',
                user_id bigint unsigned NOT NULL DEFAULT 0,
                email_hash varchar(64) NOT NULL DEFAULT '',
                ip_hash varchar(64) NOT NULL DEFAULT '',
                user_agent_hash varchar(64) NOT NULL DEFAULT '',
                first_seen_at datetime NOT NULL,
                last_seen_at datetime NOT NULL,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_key (session_key),
                KEY visitor_id (visitor_id),
                KEY user_id (user_id),
                KEY email_hash (email_hash)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['responses']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_id bigint unsigned NOT NULL,
                session_id bigint unsigned NOT NULL DEFAULT 0,
                response_key varchar(190) NOT NULL,
                respondent_email varchar(190) NOT NULL DEFAULT '',
                respondent_ref varchar(190) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'complete',
                duplicate_status varchar(32) NOT NULL DEFAULT 'unique',
                source_url text NULL,
                submitted_at datetime NOT NULL,
                synced_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY response_key (response_key),
                KEY poll_id (poll_id),
                KEY session_id (session_id),
                KEY respondent_email (respondent_email),
                KEY status (status),
                KEY synced_at (synced_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['response_answers']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                response_id bigint unsigned NOT NULL,
                poll_id bigint unsigned NOT NULL,
                question_id bigint unsigned NOT NULL,
                choice_id bigint unsigned NOT NULL DEFAULT 0,
                answer_value text NULL,
                other_text text NULL,
                profile_field_key varchar(100) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY response_id (response_id),
                KEY poll_id (poll_id),
                KEY question_id (question_id),
                KEY choice_id (choice_id),
                KEY profile_field_key (profile_field_key)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['profile_links']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                session_id bigint unsigned NOT NULL DEFAULT 0,
                visitor_id varchar(190) NOT NULL DEFAULT '',
                email varchar(190) NOT NULL DEFAULT '',
                target_system varchar(64) NOT NULL DEFAULT '',
                target_ref varchar(190) NOT NULL DEFAULT '',
                linked_at datetime NOT NULL,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY session_id (session_id),
                KEY visitor_id (visitor_id),
                KEY email (email),
                KEY target_system (target_system)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['sync_routes']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                route_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                target_system varchar(64) NOT NULL,
                target_module varchar(64) NOT NULL DEFAULT '',
                field_map_json longtext NULL,
                status varchar(32) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY route_key (route_key),
                KEY target_system (target_system),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['analytics_snapshots']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_id bigint unsigned NOT NULL,
                snapshot_key varchar(100) NOT NULL,
                response_count int unsigned NOT NULL DEFAULT 0,
                results_json longtext NULL,
                segment_json longtext NULL,
                generated_at datetime NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY snapshot_key (snapshot_key),
                KEY poll_id (poll_id),
                KEY generated_at (generated_at)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['export_jobs']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                export_key varchar(100) NOT NULL,
                poll_id bigint unsigned NOT NULL DEFAULT 0,
                export_format varchar(32) NOT NULL DEFAULT 'csv',
                status varchar(32) NOT NULL DEFAULT 'queued',
                file_ref varchar(255) NOT NULL DEFAULT '',
                requested_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                filters_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY export_key (export_key),
                KEY poll_id (poll_id),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['ai_jobs']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                ai_job_key varchar(100) NOT NULL,
                poll_id bigint unsigned NOT NULL DEFAULT 0,
                job_type varchar(64) NOT NULL,
                provider_ref varchar(190) NOT NULL DEFAULT '',
                prompt_context_json longtext NULL,
                result_json longtext NULL,
                status varchar(32) NOT NULL DEFAULT 'queued',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY ai_job_key (ai_job_key),
                KEY poll_id (poll_id),
                KEY job_type (job_type),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['ai_generation_schedules']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                schedule_key varchar(100) NOT NULL,
                name varchar(190) NOT NULL,
                audience_key varchar(100) NOT NULL DEFAULT '',
                cadence varchar(32) NOT NULL DEFAULT 'weekly',
                next_run_at datetime NULL,
                last_run_at datetime NULL,
                provider_ref varchar(190) NOT NULL DEFAULT 'apAI:default',
                approval_policy varchar(32) NOT NULL DEFAULT 'human_required',
                auto_publish tinyint unsigned NOT NULL DEFAULT 0,
                retire_previous_policy varchar(32) NOT NULL DEFAULT 'manual',
                constraints_json longtext NULL,
                status varchar(32) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY schedule_key (schedule_key),
                KEY audience_key (audience_key),
                KEY cadence (cadence),
                KEY next_run_at (next_run_at),
                KEY status (status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['ai_draft_batches']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                schedule_id bigint unsigned NOT NULL DEFAULT 0,
                batch_key varchar(100) NOT NULL,
                ai_job_id bigint unsigned NOT NULL DEFAULT 0,
                status varchar(32) NOT NULL DEFAULT 'draft',
                generated_at datetime NOT NULL,
                approved_at datetime NULL,
                published_poll_id bigint unsigned NOT NULL DEFAULT 0,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_key (batch_key),
                KEY schedule_id (schedule_id),
                KEY ai_job_id (ai_job_id),
                KEY status (status),
                KEY published_poll_id (published_poll_id)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['ai_draft_items']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                draft_batch_id bigint unsigned NOT NULL,
                item_key varchar(100) NOT NULL,
                proposed_question text NOT NULL,
                proposed_type varchar(32) NOT NULL DEFAULT 'single_choice',
                proposed_choices_json longtext NULL,
                profile_field_key varchar(100) NOT NULL DEFAULT '',
                approval_status varchar(32) NOT NULL DEFAULT 'pending',
                approved_by_user_id bigint unsigned NOT NULL DEFAULT 0,
                approved_at datetime NULL,
                rejection_reason text NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_item_key (draft_batch_id, item_key),
                KEY draft_batch_id (draft_batch_id),
                KEY approval_status (approval_status)
            ) $charset;";

            $sql[] = "CREATE TABLE {$t['moderation_events']} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                poll_id bigint unsigned NOT NULL DEFAULT 0,
                response_id bigint unsigned NOT NULL DEFAULT 0,
                event_type varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'recorded',
                reason text NULL,
                metadata_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY poll_id (poll_id),
                KEY response_id (response_id),
                KEY event_type (event_type),
                KEY status (status)
            ) $charset;";

            foreach ($sql as $statement) {
                dbDelta($statement);
            }

            update_option('appolls_schema_version', APPOLLS_VERSION, false);
        }
    }
}
