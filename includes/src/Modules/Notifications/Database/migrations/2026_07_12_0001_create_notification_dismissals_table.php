<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_12_0001_create_notification_dismissals_table';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notifications')) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notification_dismissals` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `notification_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `notification_dismissals_unique` (`notification_id`, `user_id`),
                INDEX `notification_dismissals_user_index` (`user_id`, `created_at`),
                CONSTRAINT `notification_dismissals_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
                CONSTRAINT `notification_dismissals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `notification_dismissals`');
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ');
        $stmt->execute([$tableName]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
};
