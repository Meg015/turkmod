-- ============================================================================
-- Additional Performance Indexes Migration (2026-06-26)
-- ============================================================================
-- 1. Index on topics (author_id, deleted_at, created_at) to speed up user details topic listing.
-- 2. Index on reactions (user_id, type) to speed up helpful reactions count.
-- 3. Index on users (status, public_profile, is_banned, deleted_at) to speed up sitemaps query.
-- ============================================================================

-- Speed up topics list by author in user details
ALTER TABLE `topics` ADD INDEX `idx_topics_author_deleted_created` (`author_id`, `deleted_at`, `created_at`);

-- Speed up user reactions count
ALTER TABLE `reactions` ADD INDEX `idx_reactions_user_type` (`user_id`, `type`);

-- Speed up users query in sitemaps index
ALTER TABLE `users` ADD INDEX `idx_users_sitemap_lookup` (`status`, `public_profile`, `is_banned`, `deleted_at`);
