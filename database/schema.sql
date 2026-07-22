CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_subject_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_action_created_index` (`action`,`created_at`),
  KEY `activity_logs_actor_id_foreign` (`actor_id`),
  KEY `activity_logs_actor_action` (`actor_id`,`action`,`created_at`),
  KEY `activity_logs_created_at` (`created_at`),
  CONSTRAINT `activity_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_action_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action_type` varchar(40) NOT NULL,
  `target_type` varchar(40) NOT NULL DEFAULT 'user',
  `target_id` bigint(20) unsigned NOT NULL,
  `reason` text DEFAULT NULL,
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `is_reversible` tinyint(1) NOT NULL DEFAULT 0,
  `reverted_at` timestamp NULL DEFAULT NULL,
  `reverted_by` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_action_log_target_index` (`target_type`,`target_id`),
  KEY `admin_action_log_created_index` (`created_at`),
  KEY `admin_action_log_actor_index` (`actor_id`),
  KEY `admin_action_log_target_created_index` (`target_type`,`target_id`,`created_at`),
  KEY `admin_action_log_reverted_by_foreign` (`reverted_by`),
  CONSTRAINT `admin_action_log_actor_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_action_log_reverted_by_foreign` FOREIGN KEY (`reverted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_settings` (
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `application_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` varchar(20) NOT NULL,
  `channel` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_json`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_logs_level_created_index` (`level`,`created_at`),
  KEY `application_logs_channel_created_index` (`channel`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=58611 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `source` varchar(50) NOT NULL DEFAULT 'system',
  `source_key` varchar(100) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `driver` varchar(20) DEFAULT NULL,
  `transport` varchar(20) DEFAULT NULL,
  `notification_id` bigint(20) unsigned DEFAULT NULL,
  `queue_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `attempt_no` tinyint(3) unsigned DEFAULT NULL,
  `max_attempts` tinyint(3) unsigned DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `provider_response` longtext DEFAULT NULL,
  `smtp_code` int(11) DEFAULT NULL,
  `smtp_response` text DEFAULT NULL,
  `error_message` longtext DEFAULT NULL,
  `exception_class` varchar(255) DEFAULT NULL,
  `exception_file` varchar(500) DEFAULT NULL,
  `exception_line` int(11) DEFAULT NULL,
  `context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email_logs_status_created_index` (`status`,`created_at`),
  KEY `email_logs_source_created_index` (`source`,`source_key`,`created_at`),
  KEY `email_logs_recipient_created_index` (`recipient_email`,`created_at`),
  KEY `email_logs_driver_created_index` (`driver`,`created_at`),
  KEY `email_logs_queue_created_index` (`queue_id`,`created_at`),
  KEY `email_logs_notification_created_index` (`notification_id`,`created_at`),
  KEY `email_logs_user_created_index` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ban_appeal_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appeal_id` bigint(20) unsigned NOT NULL,
  `sender_user_id` bigint(20) unsigned DEFAULT NULL,
  `sender_type` varchar(20) NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ban_appeal_messages_appeal_created` (`appeal_id`,`created_at`),
  KEY `ban_appeal_messages_sender` (`sender_user_id`),
  CONSTRAINT `ban_appeal_messages_appeal_foreign` FOREIGN KEY (`appeal_id`) REFERENCES `ban_appeals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ban_appeal_messages_sender_foreign` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ban_appeals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `message` text NOT NULL,
  `admin_note` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ban_appeals_user_status_index` (`user_id`,`status`),
  KEY `ban_appeals_status_created_index` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bot_category_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bot_site_id` bigint(20) unsigned NOT NULL,
  `remote_category_name` varchar(255) NOT NULL,
  `remote_category_url` varchar(2048) NOT NULL,
  `title_prefix` varchar(100) NOT NULL DEFAULT '',
  `local_category_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bcm_site` (`bot_site_id`),
  KEY `fk_bcm_local_category` (`local_category_id`),
  CONSTRAINT `fk_bcm_local_category` FOREIGN KEY (`local_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bcm_site` FOREIGN KEY (`bot_site_id`) REFERENCES `bot_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bot_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bot_job_id` bigint(20) unsigned DEFAULT NULL,
  `bot_site_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `source_url` varchar(2048) NOT NULL,
  `source_title` varchar(500) DEFAULT NULL,
  `translated_title` varchar(500) DEFAULT NULL,
  `author_topic` varchar(255) DEFAULT NULL,
  `topic_version` varchar(255) DEFAULT NULL,
  `source_content` longtext DEFAULT NULL,
  `translated_content` longtext DEFAULT NULL,
  `source_images` text DEFAULT NULL,
  `downloaded_images` text DEFAULT NULL,
  `source_download_links` text DEFAULT NULL,
  `status` enum('pending','preview','imported','failed','skipped') DEFAULT 'pending',
  `images_count` int(10) unsigned DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_imports_site_url_unique` (`bot_site_id`,`source_url`(255)),
  KEY `idx_bi_site` (`bot_site_id`),
  KEY `idx_bi_job` (`bot_job_id`),
  KEY `idx_bi_status` (`status`),
  KEY `fk_bi_topic` (`topic_id`),
  KEY `idx_bi_source_url` (`source_url`(255)),
  KEY `idx_bi_created_at` (`created_at`),
  KEY `idx_bi_site_status` (`bot_site_id`,`status`),
  KEY `bot_imports_status_created` (`status`,`created_at`),
  KEY `bot_imports_topic_id` (`topic_id`),
  CONSTRAINT `fk_bi_job` FOREIGN KEY (`bot_job_id`) REFERENCES `bot_jobs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bi_site` FOREIGN KEY (`bot_site_id`) REFERENCES `bot_sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bi_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bot_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bot_site_id` bigint(20) unsigned NOT NULL,
  `bot_category_mapping_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','running','completed','failed','cancelled') DEFAULT 'pending',
  `total_urls` int(10) unsigned DEFAULT 0,
  `processed_urls` int(10) unsigned DEFAULT 0,
  `failed_urls` int(10) unsigned DEFAULT 0,
  `imported_urls` int(10) unsigned DEFAULT 0,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `error_log` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bj_site` (`bot_site_id`),
  KEY `fk_bj_mapping` (`bot_category_mapping_id`),
  KEY `idx_bj_status` (`status`),
  KEY `idx_bj_created_at` (`created_at`),
  CONSTRAINT `fk_bj_mapping` FOREIGN KEY (`bot_category_mapping_id`) REFERENCES `bot_category_mappings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bj_site` FOREIGN KEY (`bot_site_id`) REFERENCES `bot_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bot_sites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `base_url` varchar(2048) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `selectors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selectors`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `total_imports` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `categories_status_index` (`status`),
  KEY `categories_parent_order_index` (`parent_id`,`display_order`),
  KEY `idx_parent_status` (`parent_id`,`status`,`deleted_at`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comment_edit_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `old_body` longtext NOT NULL,
  `new_body` longtext NOT NULL,
  `edit_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `comment_edit_history_comment_index` (`comment_id`,`created_at`),
  KEY `comment_edit_history_user_index` (`user_id`),
  CONSTRAINT `comment_edit_history_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_edit_history_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comment_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `comment_media_comment_index` (`comment_id`),
  KEY `comment_media_user_index` (`user_id`),
  CONSTRAINT `comment_media_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_media_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comment_mentions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL,
  `mentioned_user_id` bigint(20) unsigned NOT NULL,
  `mentioner_user_id` bigint(20) unsigned NOT NULL,
  `is_notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_mentions_unique` (`comment_id`,`mentioned_user_id`),
  KEY `comment_mentions_mentioned_index` (`mentioned_user_id`),
  KEY `comment_mentions_comment_index` (`comment_id`),
  KEY `comment_mentions_mentioner_foreign` (`mentioner_user_id`),
  CONSTRAINT `comment_mentions_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_mentions_mentioned_foreign` FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_mentions_mentioner_foreign` FOREIGN KEY (`mentioner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comment_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `reaction_type` varchar(20) NOT NULL DEFAULT 'like',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_reactions_unique` (`comment_id`,`user_id`,`reaction_type`),
  KEY `comment_reactions_comment_index` (`comment_id`),
  KEY `comment_reactions_user_index` (`user_id`),
  KEY `comment_reactions_type_index` (`reaction_type`),
  CONSTRAINT `comment_reactions_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_reactions_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` longtext NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'approved',
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `reaction_count` int(10) unsigned NOT NULL DEFAULT 0,
  `mention_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_markdown` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_topic_status_created_index` (`topic_id`,`status`,`created_at`),
  KEY `comments_is_edited_index` (`is_edited`),
  KEY `comments_reaction_count_index` (`reaction_count`),
  KEY `comments_user_status` (`user_id`,`status`,`created_at`),
  KEY `idx_comments_topic_status_parent_deleted_created` (`topic_id`,`status`(191),`parent_id`,`deleted_at`,`created_at`),
  KEY `idx_comments_user_deleted_created` (`user_id`,`deleted_at`,`created_at`),
  KEY `idx_comments_status_deleted` (`status`(191),`deleted_at`),
  KEY `comments_status_deleted_created_index` (`status`(191),`deleted_at`,`created_at`),
  CONSTRAINT `comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_download_access_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `comment_id` bigint(20) unsigned NOT NULL,
  `grant_mode` varchar(16) NOT NULL DEFAULT 'permanent',
  `granted_at` timestamp NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoke_reason` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_download_grants_comment_unique` (`comment_id`),
  KEY `topic_download_grants_topic_user_granted` (`topic_id`,`user_id`,`granted_at`),
  KEY `topic_download_grants_expires` (`expires_at`),
  CONSTRAINT `topic_download_grants_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_download_grants_topic_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_download_grants_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `core_cache` (
  `cache_key` varchar(191) NOT NULL,
  `cache_value` longtext NOT NULL,
  `tags` longtext DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cache_key`),
  KEY `cache_expires_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `downloads_topic_created_index` (`topic_id`,`created_at`),
  KEY `downloads_user_id_index` (`user_id`),
  KEY `downloads_user_created` (`user_id`,`created_at`),
  KEY `downloads_ip_created` (`ip_address`,`created_at`),
  KEY `downloads_topic_user` (`topic_id`,`user_id`),
  CONSTRAINT `downloads_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `downloads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_activity_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `activity_type` varchar(80) NOT NULL,
  `label` varchar(191) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `daily_limit` int(11) NOT NULL DEFAULT 0,
  `weekly_limit` int(11) NOT NULL DEFAULT 0,
  `monthly_limit` int(11) NOT NULL DEFAULT 0,
  `cooldown_minutes` int(11) NOT NULL DEFAULT 0,
  `repeat_policy` varchar(40) NOT NULL DEFAULT 'once_per_subject',
  `min_length` int(11) NOT NULL DEFAULT 0,
  `min_account_age_days` int(11) NOT NULL DEFAULT 0,
  `required_group_id` bigint(20) unsigned DEFAULT NULL,
  `allow_self_subject` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approved_subject` tinyint(1) NOT NULL DEFAULT 0,
  `reversal_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_type` (`activity_type`),
  KEY `idx_events_activity_rules_active` (`is_active`,`activity_type`),
  KEY `idx_events_activity_rules_dates` (`starts_at`,`ends_at`),
  KEY `events_activity_rules_group_index` (`required_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `subject_type` varchar(80) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_audit_log_action_created` (`action`,`created_at`),
  KEY `idx_events_audit_log_user_created` (`user_id`,`created_at`),
  CONSTRAINT `events_audit_log_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(191) NOT NULL,
  `config_value` longtext DEFAULT NULL,
  `value_type` enum('string','int','bool','json') NOT NULL DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_email_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `email_to` varchar(255) NOT NULL,
  `email_subject` varchar(255) NOT NULL,
  `email_body` longtext NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_data`)),
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_email_queue_status_created` (`status`,`created_at`),
  KEY `events_email_queue_user_foreign` (`user_id`),
  CONSTRAINT `events_email_queue_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_error_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` enum('ERROR','WARNING','INFO','DEBUG') NOT NULL,
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_error_log_level_created` (`level`,`created_at`),
  KEY `events_error_log_user_foreign` (`user_id`),
  CONSTRAINT `events_error_log_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('wheel_win','raffle_win','reward_expiring','raffle_result','reward_claimed','task_completed','task_reward') NOT NULL,
  `title` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_events_notifications_user_created` (`user_id`,`created_at`),
  CONSTRAINT `events_notifications_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_point_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `source_type` enum('activity','task','admin','reversal') NOT NULL DEFAULT 'activity',
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `activity_type` varchar(80) DEFAULT NULL,
  `points_delta` int(11) NOT NULL DEFAULT 0,
  `subject_type` varchar(80) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `dedupe_key` varchar(191) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_point_ledger_dedupe_unique` (`dedupe_key`),
  KEY `idx_events_point_ledger_user_created` (`user_id`,`created_at`),
  KEY `idx_events_point_ledger_activity_created` (`activity_type`,`created_at`),
  CONSTRAINT `events_point_ledger_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_prize_pool_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('points','custom','coupon','discount') NOT NULL,
  `value` varchar(191) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `remaining_quantity` int(11) NOT NULL DEFAULT 0,
  `weight` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `description` text DEFAULT NULL,
  `expires_in_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_raffle_draws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raffle_id` bigint(20) unsigned NOT NULL,
  `drawn_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_raffle_draws_raffle` (`raffle_id`),
  KEY `events_raffle_draws_user_foreign` (`drawn_by`),
  CONSTRAINT `events_raffle_draws_raffle_foreign` FOREIGN KEY (`raffle_id`) REFERENCES `events_raffles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_draws_user_foreign` FOREIGN KEY (`drawn_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_raffle_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raffle_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `entry_type` enum('manual','admin') NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_raffle_entries_raffle_user` (`raffle_id`,`user_id`),
  KEY `idx_events_raffle_entries_user_created` (`user_id`,`created_at`),
  CONSTRAINT `events_raffle_entries_raffle_foreign` FOREIGN KEY (`raffle_id`) REFERENCES `events_raffles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_entries_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_raffle_items` (
  `raffle_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`raffle_id`,`item_id`),
  KEY `events_raffle_items_item_fk` (`item_id`),
  CONSTRAINT `events_raffle_items_item_fk` FOREIGN KEY (`item_id`) REFERENCES `events_prize_pool_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_items_raffle_fk` FOREIGN KEY (`raffle_id`) REFERENCES `events_raffles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_raffle_winners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raffle_id` bigint(20) unsigned NOT NULL,
  `draw_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `pool_item_id` bigint(20) unsigned DEFAULT NULL,
  `user_reward_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_raffle_winners_raffle` (`raffle_id`),
  KEY `idx_events_raffle_winners_user` (`user_id`),
  KEY `events_raffle_winners_draw_foreign` (`draw_id`),
  KEY `events_raffle_winners_pool_item_foreign` (`pool_item_id`),
  KEY `events_raffle_winners_user_reward_foreign` (`user_reward_id`),
  CONSTRAINT `events_raffle_winners_draw_foreign` FOREIGN KEY (`draw_id`) REFERENCES `events_raffle_draws` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_winners_pool_item_foreign` FOREIGN KEY (`pool_item_id`) REFERENCES `events_prize_pool_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_raffle_winners_raffle_foreign` FOREIGN KEY (`raffle_id`) REFERENCES `events_raffles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_winners_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_raffle_winners_user_reward_foreign` FOREIGN KEY (`user_reward_id`) REFERENCES `events_user_rewards` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_raffles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `draw_date` datetime DEFAULT NULL,
  `max_entries_per_user` int(11) NOT NULL DEFAULT 1,
  `winner_count` int(11) NOT NULL DEFAULT 1,
  `status` enum('draft','active','closed','drawn','cancelled') NOT NULL DEFAULT 'draft',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_raffles_status_dates` (`status`,`start_date`,`end_date`),
  KEY `idx_events_raffles_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_task_claims` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `period_key` varchar(32) NOT NULL,
  `reward_type` enum('points','wheel_spin','raffle_entry','coupon','custom') NOT NULL,
  `reward_value` varchar(191) DEFAULT NULL,
  `reward_quantity` int(11) NOT NULL DEFAULT 1,
  `user_reward_id` bigint(20) unsigned DEFAULT NULL,
  `claimed_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_task_claims_unique` (`user_id`,`task_id`,`period_key`),
  KEY `idx_events_task_claims_user_created` (`user_id`,`created_at`),
  KEY `events_task_claims_task_foreign` (`task_id`),
  KEY `events_task_claims_user_reward_foreign` (`user_reward_id`),
  CONSTRAINT `events_task_claims_task_foreign` FOREIGN KEY (`task_id`) REFERENCES `events_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_task_claims_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_task_claims_user_reward_foreign` FOREIGN KEY (`user_reward_id`) REFERENCES `events_user_rewards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_task_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `activity_type` varchar(80) NOT NULL,
  `target_count` int(11) NOT NULL DEFAULT 1,
  `metadata_filter` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_filter`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_task_requirements_task` (`task_id`),
  KEY `idx_events_task_requirements_activity` (`activity_type`),
  CONSTRAINT `events_task_requirements_task_foreign` FOREIGN KEY (`task_id`) REFERENCES `events_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(191) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `task_type` enum('daily','weekly','monthly','achievement') NOT NULL DEFAULT 'daily',
  `reward_type` enum('points','wheel_spin','raffle_entry','coupon','custom') NOT NULL DEFAULT 'points',
  `reward_value` varchar(191) DEFAULT NULL,
  `reward_quantity` int(11) NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `min_user_points` int(11) DEFAULT NULL,
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_events_tasks_active_type_order` (`is_active`,`task_type`,`display_order`),
  KEY `idx_events_tasks_dates` (`starts_at`,`ends_at`),
  KEY `events_tasks_group_index` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_user_bonus_spins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `source_type` enum('task','admin') NOT NULL DEFAULT 'task',
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `remaining_quantity` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_bonus_spins_user_remaining` (`user_id`,`remaining_quantity`,`expires_at`),
  CONSTRAINT `events_bonus_spins_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_user_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `email_notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notification_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_types`)),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `events_user_preferences_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_user_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `source_type` enum('wheel','raffle','admin','task') NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `reward_name` varchar(191) NOT NULL,
  `reward_type` enum('points','custom','coupon','discount') NOT NULL,
  `reward_value` varchar(191) NOT NULL,
  `status` enum('pending','claimed','expired','cancelled') NOT NULL DEFAULT 'pending',
  `claimed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_user_rewards_user_status` (`user_id`,`status`),
  KEY `idx_events_user_rewards_expires_status` (`expires_at`,`status`),
  CONSTRAINT `events_user_rewards_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_user_streaks` (
  `user_id` bigint(20) unsigned NOT NULL,
  `current_streak` int(11) NOT NULL DEFAULT 0,
  `longest_streak` int(11) NOT NULL DEFAULT 0,
  `last_login_date` date DEFAULT NULL,
  `last_reward_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `events_user_streaks_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_user_task_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `requirement_id` bigint(20) unsigned NOT NULL,
  `period_key` varchar(32) NOT NULL,
  `progress_count` int(11) NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_task_progress_unique` (`user_id`,`task_id`,`requirement_id`,`period_key`),
  KEY `idx_events_task_progress_user_period` (`user_id`,`period_key`),
  KEY `idx_events_task_progress_task_period` (`task_id`,`period_key`),
  KEY `events_task_progress_requirement_foreign` (`requirement_id`),
  CONSTRAINT `events_task_progress_requirement_foreign` FOREIGN KEY (`requirement_id`) REFERENCES `events_task_requirements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_task_progress_task_foreign` FOREIGN KEY (`task_id`) REFERENCES `events_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_task_progress_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_wheel_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('points','custom','coupon','discount') NOT NULL,
  `value` varchar(191) NOT NULL,
  `probability` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `image_url` varchar(500) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `min_user_points` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `remaining_quantity` int(11) DEFAULT NULL,
  `expires_in_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_wheel_rewards_active_order` (`is_active`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events_wheel_spins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `reward_id` bigint(20) unsigned NOT NULL,
  `user_reward_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_wheel_spins_user_created` (`user_id`,`created_at`),
  KEY `idx_events_wheel_spins_reward` (`reward_id`),
  KEY `events_wheel_spins_user_reward_foreign` (`user_reward_id`),
  CONSTRAINT `events_wheel_spins_reward_foreign` FOREIGN KEY (`reward_id`) REFERENCES `events_wheel_rewards` (`id`),
  CONSTRAINT `events_wheel_spins_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_wheel_spins_user_reward_foreign` FOREIGN KEY (`user_reward_id`) REFERENCES `events_user_rewards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leaderboard_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `category` varchar(50) NOT NULL COMMENT 'downloads, active, helpful, rising_star, quality',
  `period` varchar(20) NOT NULL COMMENT 'daily, weekly, monthly, quarterly, yearly',
  `rank` int(10) unsigned NOT NULL,
  `score` decimal(15,2) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'DetaylÄ± metrikler' CHECK (json_valid(`metadata`)),
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_category_period` (`user_id`,`category`,`period`,`period_start`),
  KEY `idx_category_period_rank` (`category`,`period`,`rank`),
  KEY `idx_user_category` (`user_id`,`category`),
  KEY `idx_calculated_at` (`calculated_at`),
  CONSTRAINT `leaderboard_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leaderboard_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `category` varchar(50) NOT NULL,
  `period` varchar(20) NOT NULL,
  `rank` int(10) unsigned NOT NULL,
  `score` decimal(15,2) NOT NULL,
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_snapshot` (`user_id`,`snapshot_date`),
  KEY `idx_category_snapshot` (`category`,`snapshot_date`),
  CONSTRAINT `leaderboard_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL,
  `path` varchar(1024) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `last_status_code` int(11) DEFAULT NULL,
  `health_status` varchar(32) NOT NULL DEFAULT 'unchecked',
  `last_health_message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_files_topic_type_index` (`topic_id`,`type`),
  KEY `media_files_topic_primary_index` (`topic_id`,`is_primary`,`display_order`),
  KEY `media_files_user_id_foreign` (`user_id`),
  KEY `media_files_user_type` (`user_id`,`type`,`created_at`),
  KEY `idx_media_files_topic_id_is_primary` (`topic_id`,`type`(191),`mime_type`(191),`is_primary`,`display_order`),
  KEY `idx_media_files_path` (`path`(191)),
  KEY `idx_media_files_health_status` (`health_status`),
  CONSTRAINT `media_files_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `media_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7527 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_email_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `template_key` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `link` varchar(1024) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `available_at` timestamp NULL DEFAULT current_timestamp(),
  `locked_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_email_queue_notification_user_unique` (`notification_id`,`user_id`),
  KEY `notification_email_queue_status_available_index` (`status`,`available_at`),
  KEY `notification_email_queue_user_index` (`user_id`,`created_at`),
  KEY `notification_email_queue_template_index` (`template_key`,`created_at`),
  CONSTRAINT `notification_email_queue_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_email_queue_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_dispatch_suppression_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_key` varchar(100) NOT NULL,
  `reason_key` varchar(80) NOT NULL,
  `reason_label` varchar(160) NOT NULL,
  `recipient_user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `dedupe_key` varchar(190) DEFAULT NULL,
  `template_key` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `context_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `notification_suppression_reason_created_index` (`reason_key`,`created_at`),
  KEY `notification_suppression_event_created_index` (`event_key`,`created_at`),
  KEY `notification_suppression_recipient_created_index` (`recipient_user_id`,`created_at`),
  KEY `notification_suppression_entity_index` (`entity_type`,`entity_id`),
  KEY `notification_suppression_dedupe_index` (`dedupe_key`),
  KEY `notification_suppression_actor_foreign` (`actor_user_id`),
  CONSTRAINT `notification_suppression_actor_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notification_suppression_recipient_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_reads_unique` (`notification_id`,`user_id`),
  KEY `notification_reads_user_index` (`user_id`),
  CONSTRAINT `notification_reads_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title_template` varchar(255) NOT NULL,
  `message_template` text NOT NULL,
  `link_template` varchar(1024) DEFAULT NULL,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `email_subject_template` varchar(255) DEFAULT NULL,
  `email_body_template` text DEFAULT NULL,
  `email_link_template` varchar(1024) DEFAULT NULL,
  `email_preview_template` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables_json`)),
  `sample_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sample_payload`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_key_unique` (`template_key`),
  KEY `notification_templates_active_index` (`is_active`,`template_key`),
  KEY `notification_templates_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `link` varchar(1024) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `event_key` varchar(100) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `dedupe_key` varchar(190) DEFAULT NULL,
  `delivery_channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_channels`)),
  PRIMARY KEY (`id`),
  KEY `notifications_user_created_index` (`user_id`,`created_at`),
  KEY `notifications_event_key_index` (`event_key`,`created_at`),
  KEY `notifications_entity_index` (`entity_type`,`entity_id`),
  KEY `notifications_actor_index` (`actor_user_id`),
  KEY `notifications_dedupe_index` (`dedupe_key`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `request_rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(100) NOT NULL,
  `rate_key` varchar(191) NOT NULL,
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `first_attempt_at` timestamp NULL DEFAULT NULL,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_rate_limits_scope_key_unique` (`scope`,`rate_key`),
  KEY `request_rate_limits_expires_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6663 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `security_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `request_uri` varchar(255) DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_collection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `collection_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_collection_items_unique` (`collection_id`,`topic_id`),
  KEY `topic_collection_items_topic_index` (`topic_id`),
  CONSTRAINT `topic_collection_items_collection_foreign` FOREIGN KEY (`collection_id`) REFERENCES `topic_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_collection_items_topic_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `visibility` varchar(20) NOT NULL DEFAULT 'private',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_collections_user_name_unique` (`user_id`,`name`),
  KEY `topic_collections_user_index` (`user_id`),
  CONSTRAINT `topic_collections_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_download_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `download_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `last_status_code` int(11) DEFAULT NULL,
  `health_status` varchar(32) NOT NULL DEFAULT 'unchecked',
  `last_health_message` varchar(255) DEFAULT NULL,
  `last_final_url` varchar(2048) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_download_links_topic_order_index` (`topic_id`,`display_order`),
  KEY `idx_download_links_topic_id` (`topic_id`),
  CONSTRAINT `topic_download_links_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4699 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_favorites_unique` (`topic_id`,`user_id`),
  KEY `topic_favorites_user_index` (`user_id`),
  CONSTRAINT `topic_favorites_topic_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_favorites_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_report_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `old_status` varchar(255) DEFAULT NULL,
  `new_status` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_report_events_report_created_index` (`report_id`,`created_at`),
  KEY `topic_report_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `topic_report_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_report_events_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `topic_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `reporter_user_id` bigint(20) unsigned DEFAULT NULL,
  `reporter_name` varchar(255) DEFAULT NULL,
  `reporter_email` varchar(255) DEFAULT NULL,
  `reporter_type` varchar(32) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_reports_status_created_index` (`status`,`created_at`),
  KEY `topic_reports_topic_index` (`topic_id`),
  KEY `topic_reports_reporter_email_index` (`reporter_email`),
  KEY `topic_reports_reporter_user_foreign` (`reporter_user_id`),
  CONSTRAINT `topic_reports_reporter_user_foreign` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_reports_topic_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `revision_number` int(10) unsigned NOT NULL DEFAULT 1,
  `reason` varchar(80) NOT NULL DEFAULT 'admin_update',
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `author_topic` varchar(255) DEFAULT NULL,
  `topic_version` varchar(255) DEFAULT NULL,
  `topic_descriptions` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `primary_media_file_id` bigint(20) unsigned DEFAULT NULL,
  `links_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`links_json`)),
  `media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`media_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_revisions_topic_created_index` (`topic_id`,`created_at`),
  KEY `topic_revisions_actor_index` (`actor_user_id`),
  CONSTRAINT `topic_revisions_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_revisions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `author_topic` varchar(255) DEFAULT NULL,
  `topic_version` varchar(255) DEFAULT NULL,
  `topic_descriptions` longtext DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `updated_content_at` timestamp NULL DEFAULT NULL,
  `download_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `view_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `comment_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `primary_media_file_id` bigint(20) unsigned DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `moderation_flags` longtext DEFAULT NULL CHECK (json_valid(`moderation_flags`)),
  `editor_approved` tinyint(1) NOT NULL DEFAULT 0,
  `trusted_topic` tinyint(1) NOT NULL DEFAULT 0,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `health_status` varchar(32) NOT NULL DEFAULT 'unchecked',
  `health_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`health_summary`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `topics_status_published_index` (`status`,`published_at`),
  KEY `topics_status_deleted_downloads_index` (`status`,`deleted_at`,`download_count`),
  KEY `topics_status_deleted_published_index` (`status`,`deleted_at`,`published_at`),
  KEY `topics_featured_published` (`is_featured`,`status`,`published_at`),
  KEY `topics_author_status` (`author_id`,`status`,`created_at`),
  KEY `topics_category_published` (`category_id`,`status`,`published_at`),
  KEY `idx_cat_status_deleted` (`category_id`,`status`,`deleted_at`),
  KEY `idx_author_status_deleted` (`author_id`,`status`,`deleted_at`),
  KEY `idx_sort_metrics` (`view_count`,`download_count`,`published_at`),
  FULLTEXT KEY `topics_search_fulltext` (`title`,`topic_descriptions`),
  CONSTRAINT `topics_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `topics_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3960 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `event_group` varchar(40) NOT NULL DEFAULT 'activity',
  `subject_type` varchar(80) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(40) DEFAULT NULL,
  `browser` varchar(80) DEFAULT NULL,
  `platform` varchar(80) DEFAULT NULL,
  `request_method` varchar(12) DEFAULT NULL,
  `request_path` varchar(255) DEFAULT NULL,
  `session_id_hash` varchar(64) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_activity_events_user_created` (`user_id`,`created_at`),
  KEY `user_activity_events_actor_created` (`actor_user_id`,`created_at`),
  KEY `user_activity_events_type_created` (`event_type`,`created_at`),
  KEY `user_activity_events_group_created` (`event_group`,`created_at`),
  KEY `user_activity_events_subject` (`subject_type`,`subject_id`),
  KEY `user_activity_events_ip_created` (`ip_address`,`created_at`),
  KEY `user_activity_events_created` (`created_at`),
  CONSTRAINT `user_activity_events_actor_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_activity_events_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_admin_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `note` text NOT NULL,
  `tone` varchar(20) NOT NULL DEFAULT 'info',
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_admin_notes_user_created` (`user_id`,`created_at`),
  KEY `user_admin_notes_admin_index` (`admin_id`),
  CONSTRAINT `user_admin_notes_admin_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_admin_notes_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_group_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `old_group_ids` text DEFAULT NULL,
  `new_group_ids` text DEFAULT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_group_logs_user_created_index` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_group_members` (
  `user_id` bigint(20) unsigned NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `user_group_members_group_index` (`group_id`),
  KEY `user_group_members_primary_index` (`user_id`,`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_group_permission_overrides` (
  `user_id` bigint(20) unsigned NOT NULL,
  `permission_key` varchar(191) NOT NULL,
  `permission_value` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`permission_key`),
  KEY `user_group_permission_overrides_key_index` (`permission_key`,`permission_value`),
  KEY `user_group_permission_overrides_updated_by_index` (`updated_by`),
  CONSTRAINT `user_group_permission_overrides_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_group_permission_overrides_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_group_permissions` (
  `group_id` bigint(20) unsigned NOT NULL,
  `permission_key` varchar(191) NOT NULL,
  `permission_value` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`group_id`,`permission_key`),
  KEY `user_group_permissions_key_index` (`permission_key`,`permission_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `parent_group_id` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_groups_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=49151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_report_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `old_status` varchar(255) DEFAULT NULL,
  `new_status` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_report_events_report_created_index` (`report_id`,`created_at`),
  KEY `user_report_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `user_report_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_report_events_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `user_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reported_user_id` bigint(20) unsigned NOT NULL,
  `reporter_user_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_reports_status_created_index` (`status`,`created_at`),
  KEY `user_reports_reported_user_index` (`reported_user_id`),
  KEY `user_reports_reporter_user_foreign` (`reporter_user_id`),
  CONSTRAINT `user_reports_reported_user_foreign` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_reports_reporter_user_foreign` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_restrictions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `restriction_type` varchar(64) NOT NULL,
  `reason` text DEFAULT NULL,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_restrictions_user_type_index` (`user_id`,`restriction_type`),
  KEY `user_restrictions_expires_index` (`expires_at`),
  KEY `user_restrictions_admin_index` (`admin_id`),
  KEY `user_restrictions_user_expires_index` (`user_id`,`expires_at`),
  CONSTRAINT `user_restrictions_admin_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_restrictions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_settings` (
  `user_id` bigint(20) unsigned NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`user_id`,`setting_key`),
  CONSTRAINT `user_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires_at` timestamp NULL DEFAULT NULL,
  `email_verification_sent_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `banned_at` timestamp NULL DEFAULT NULL,
  `ban_reason` varchar(500) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `social_github` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_discord` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires_at` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `public_profile` tinyint(1) NOT NULL DEFAULT 1,
  `public_show_topics` tinyint(1) NOT NULL DEFAULT 1,
  `public_show_comments` tinyint(1) NOT NULL DEFAULT 0,
  `public_show_socials` tinyint(1) NOT NULL DEFAULT 1,
  `total_downloads` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Tum modlarin toplam indirme',
  `total_topics` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Yuklenen mod sayisi',
  `total_comments` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Yapilan yorum sayisi',
  `helpful_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Alinan helpful reaksiyon',
  `last_activity_at` timestamp NULL DEFAULT NULL COMMENT 'Son aktivite zamani',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_status_index` (`status`),
  KEY `users_password_reset_expires_index` (`password_reset_expires_at`),
  KEY `users_deleted_at_index` (`deleted_at`),
  KEY `idx_total_downloads` (`total_downloads`),
  KEY `idx_total_topics` (`total_topics`),
  KEY `idx_last_activity` (`last_activity_at`),
  KEY `users_email_status` (`email`,`status`),
  KEY `users_created_at` (`created_at`),
  KEY `idx_users_banned_at` (`is_banned`,`banned_at`),
  KEY `idx_users_status_banned` (`status`,`is_banned`)
) ENGINE=InnoDB AUTO_INCREMENT=5101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


