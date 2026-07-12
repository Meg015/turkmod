<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_12_120000_create_topic_download_access_grants';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS topic_download_access_grants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                comment_id BIGINT UNSIGNED NOT NULL,
                grant_mode VARCHAR(16) NOT NULL DEFAULT 'permanent',
                granted_at TIMESTAMP NOT NULL,
                expires_at TIMESTAMP NULL DEFAULT NULL,
                revoked_at TIMESTAMP NULL DEFAULT NULL,
                revoke_reason VARCHAR(50) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY topic_download_grants_comment_unique (comment_id),
                KEY topic_download_grants_topic_user_granted (topic_id, user_id, granted_at),
                KEY topic_download_grants_expires (expires_at),
                CONSTRAINT topic_download_grants_topic_foreign FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
                CONSTRAINT topic_download_grants_user_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT topic_download_grants_comment_foreign FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `topic_download_access_grants`');
    }
};
