<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_12_0001_create_email_logs_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                status VARCHAR(20) NOT NULL DEFAULT 'sent',
                source VARCHAR(50) NOT NULL DEFAULT 'system',
                source_key VARCHAR(100) NULL,
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255) NULL,
                subject VARCHAR(255) NOT NULL,
                driver VARCHAR(20) NULL,
                transport VARCHAR(20) NULL,
                notification_id BIGINT UNSIGNED NULL,
                queue_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                attempt_no TINYINT UNSIGNED NULL,
                max_attempts TINYINT UNSIGNED NULL,
                provider_message_id VARCHAR(255) NULL,
                provider_response LONGTEXT NULL,
                smtp_code INT NULL,
                smtp_response TEXT NULL,
                error_message LONGTEXT NULL,
                exception_class VARCHAR(255) NULL,
                exception_file VARCHAR(500) NULL,
                exception_line INT NULL,
                context_json JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX email_logs_status_created_index (status, created_at),
                INDEX email_logs_source_created_index (source, source_key, created_at),
                INDEX email_logs_recipient_created_index (recipient_email, created_at),
                INDEX email_logs_driver_created_index (driver, created_at),
                INDEX email_logs_queue_created_index (queue_id, created_at),
                INDEX email_logs_notification_created_index (notification_id, created_at),
                INDEX email_logs_user_created_index (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `email_logs`');
    }
};
