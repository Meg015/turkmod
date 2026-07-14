-- ============================================================================
-- Consolidated Performance Indexes Migration (2026-06-29)
-- ============================================================================
-- This migration consolidates index cleanup + additional performance indexes.
-- It replaces:
--   - 2026_06_24_cleanup_duplicate_indexes.sql
--   - 2026_06_25_performance_index_cleanup.sql
--   - 2026_06_26_additional_performance_indexes.sql
--
-- Notes:
-- - Statements are intentionally simple ALTER INDEX operations.
-- - The runtime SQL migration engine safely skips add/drop operations when the
--   target index already exists (for ADD) or does not exist (for DROP).
-- ============================================================================

-- COMMENTS: remove redundant/duplicate indexes
ALTER TABLE `comments` DROP INDEX `idx_comments_topic_id`;
ALTER TABLE `comments` DROP INDEX `idx_comments_user_id`;
ALTER TABLE `comments` DROP INDEX `idx_comments_status`;
ALTER TABLE `comments` DROP INDEX `idx_comments_topic_status`;
ALTER TABLE `comments` DROP INDEX `comments_topic_parent`;
ALTER TABLE `comments` DROP INDEX `idx_comments_parent_id`;
ALTER TABLE `comments` DROP INDEX `comments_user_id_index`;
ALTER TABLE `comments` DROP INDEX `comments_status_index`;

-- TOPICS: remove redundant/overlapping indexes
ALTER TABLE `topics` DROP INDEX `idx_topics_status`;
ALTER TABLE `topics` DROP INDEX `idx_topics_category_id`;
ALTER TABLE `topics` DROP INDEX `idx_topics_status_deleted`;
ALTER TABLE `topics` DROP INDEX `idx_topics_published_at`;
ALTER TABLE `topics` DROP INDEX `topics_slug_status`;
ALTER TABLE `topics` DROP INDEX `idx_author_status_deleted`;
ALTER TABLE `topics` DROP INDEX `topics_author_id_index`;
ALTER TABLE `topics` DROP INDEX `topics_category_status_index`;

-- ADMIN_SETTINGS / USERS: remove redundant indexes
ALTER TABLE `admin_settings` DROP INDEX `idx_setting_key`;
ALTER TABLE `users` DROP INDEX `idx_users_email`;

-- ADDITIONAL PERFORMANCE INDEXES
ALTER TABLE `topics` ADD INDEX `idx_topics_author_deleted_created` (`author_id`, `deleted_at`, `created_at`);
ALTER TABLE `users` ADD INDEX `idx_users_sitemap_lookup` (`status`, `public_profile`, `is_banned`, `deleted_at`);
