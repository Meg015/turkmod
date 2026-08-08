<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_08_08_0001_create_bulk_email_campaign_tables';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulk_email_campaigns` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `created_by_user_id` BIGINT UNSIGNED NULL,
                `subject_template` VARCHAR(255) NOT NULL,
                `body_html_template` LONGTEXT NOT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
                `snapshot_after_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `snapshot_completed_at` TIMESTAMP NULL,
                `recipient_total` INT UNSIGNED NOT NULL DEFAULT 0,
                `pending_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `processing_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `cancelled_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `started_at` TIMESTAMP NULL,
                `paused_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                `cancelled_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `bulk_email_campaigns_status_created_index` (`status`, `created_at`),
                INDEX `bulk_email_campaigns_creator_index` (`created_by_user_id`, `created_at`),
                CONSTRAINT `bulk_email_campaigns_creator_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulk_email_recipients` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `campaign_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `recipient_email` VARCHAR(255) NOT NULL,
                `recipient_username` VARCHAR(255) NOT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `available_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `lock_token` VARCHAR(64) NULL,
                `locked_at` TIMESTAMP NULL,
                `sent_at` TIMESTAMP NULL,
                `last_error` TEXT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `bulk_email_recipients_campaign_user_unique` (`campaign_id`, `user_id`),
                INDEX `bulk_email_recipients_claim_index` (`campaign_id`, `status`, `available_at`, `id`),
                INDEX `bulk_email_recipients_lock_index` (`status`, `locked_at`),
                INDEX `bulk_email_recipients_email_index` (`recipient_email`, `created_at`),
                CONSTRAINT `bulk_email_recipients_campaign_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `bulk_email_campaigns` (`id`) ON DELETE CASCADE,
                CONSTRAINT `bulk_email_recipients_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `bulk_email_recipients`');
        $pdo->exec('DROP TABLE IF EXISTS `bulk_email_campaigns`');
    }
};
