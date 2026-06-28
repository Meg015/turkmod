-- TurkMod database optimization indexes.
-- Safe to run multiple times on MySQL/MariaDB: each index is created only
-- when it is missing from the current database.

SET @db_name := DATABASE();

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'topics'
      AND INDEX_NAME = 'idx_cat_status_deleted'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `topics` ADD INDEX `idx_cat_status_deleted` (`category_id`, `status`, `deleted_at`)',
    'SELECT ''idx_cat_status_deleted already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'topics'
      AND INDEX_NAME = 'idx_author_status_deleted'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `topics` ADD INDEX `idx_author_status_deleted` (`author_id`, `status`, `deleted_at`)',
    'SELECT ''idx_author_status_deleted already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'topics'
      AND INDEX_NAME = 'idx_sort_metrics'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `topics` ADD INDEX `idx_sort_metrics` (`view_count`, `download_count`, `published_at`)',
    'SELECT ''idx_sort_metrics already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'categories'
      AND INDEX_NAME = 'idx_parent_status'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `categories` ADD INDEX `idx_parent_status` (`parent_id`, `status`, `deleted_at`)',
    'SELECT ''idx_parent_status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index optimizations for comments table
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'comments'
      AND INDEX_NAME = 'idx_comments_topic_status_parent_deleted_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `comments` ADD INDEX `idx_comments_topic_status_parent_deleted_created` (`topic_id`, `status`(191), `parent_id`, `deleted_at`, `created_at`)',
    'SELECT ''idx_comments_topic_status_parent_deleted_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'comments'
      AND INDEX_NAME = 'idx_comments_user_deleted_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `comments` ADD INDEX `idx_comments_user_deleted_created` (`user_id`, `deleted_at`, `created_at`)',
    'SELECT ''idx_comments_user_deleted_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'comments'
      AND INDEX_NAME = 'idx_comments_status_deleted'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `comments` ADD INDEX `idx_comments_status_deleted` (`status`(191), `deleted_at`)',
    'SELECT ''idx_comments_status_deleted already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index optimizations for users table
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_banned_at'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_banned_at` (`is_banned`, `banned_at`)',
    'SELECT ''idx_users_banned_at already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_status_banned'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_status_banned` (`status`(50), `is_banned`)',
    'SELECT ''idx_users_status_banned already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index optimizations for media_files table
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'media_files'
      AND INDEX_NAME = 'idx_media_files_topic_id_is_primary'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `media_files` ADD INDEX `idx_media_files_topic_id_is_primary` (`topic_id`, `type`(191), `mime_type`(191), `is_primary`, `display_order`)',
    'SELECT ''idx_media_files_topic_id_is_primary already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'media_files'
      AND INDEX_NAME = 'idx_media_files_path'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `media_files` ADD INDEX `idx_media_files_path` (`path`(191))',
    'SELECT ''idx_media_files_path already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'media_files'
      AND INDEX_NAME = 'idx_media_files_health_status'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `media_files` ADD INDEX `idx_media_files_health_status` (`health_status`)',
    'SELECT ''idx_media_files_health_status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
