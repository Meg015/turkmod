<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_19_0002_create_dispatch_suppression_logs';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notification_dispatch_suppression_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_key` VARCHAR(100) NOT NULL,
                `reason_key` VARCHAR(80) NOT NULL,
                `reason_label` VARCHAR(160) NOT NULL,
                `recipient_user_id` BIGINT UNSIGNED NULL,
                `actor_user_id` BIGINT UNSIGNED NULL,
                `entity_type` VARCHAR(50) NULL,
                `entity_id` BIGINT UNSIGNED NULL,
                `dedupe_key` VARCHAR(190) NULL,
                `template_key` VARCHAR(100) NULL,
                `type` VARCHAR(50) NULL,
                `context_json` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `notification_suppression_reason_created_index` (`reason_key`, `created_at`),
                INDEX `notification_suppression_event_created_index` (`event_key`, `created_at`),
                INDEX `notification_suppression_recipient_created_index` (`recipient_user_id`, `created_at`),
                INDEX `notification_suppression_entity_index` (`entity_type`, `entity_id`),
                INDEX `notification_suppression_dedupe_index` (`dedupe_key`),
                CONSTRAINT `notification_suppression_recipient_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `notification_suppression_actor_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `notification_dispatch_suppression_logs`');
    }
};
