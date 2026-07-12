<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationSchemaService
{
    /** @var array<string,bool>|null */
    private ?array $eventColumns = null;
    private bool $eventSchemaEnsured = false;
    private bool $templateSchemaEnsured = false;
    private bool $emailQueueSchemaEnsured = false;
    private bool $dismissalSchemaEnsured = false;

    /** @return array<string,bool> */
    public function eventTableColumns(PDO $pdo, bool $refresh = false): array
    {
        if ($this->eventColumns !== null && !$refresh) {
            return $this->eventColumns;
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM notifications');
            $this->eventColumns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $this->eventColumns[(string) $row['Field']] = true;
            }
        } catch (Throwable $e) {
            error_log('Notification event column lookup failed: ' . $e->getMessage());
            $this->eventColumns = [];
        }

        return $this->eventColumns;
    }

    public function eventIndexExists(PDO $pdo, string $indexName): bool
    {
        try {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = \'notifications\'
                  AND INDEX_NAME = ?
            ');
            $stmt->execute([$indexName]);

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            error_log('Notification event index lookup failed: ' . $e->getMessage());
            return true;
        }
    }

    public function tableExists(PDO $pdo, string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ');
            $stmt->execute([$tableName]);

            return ((int) $stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            error_log('Notification table lookup failed: ' . $e->getMessage());

            return false;
        }
    }

    public function ensureEventSchema(PDO $pdo): void
    {
        if ($this->eventSchemaEnsured) {
            return;
        }
        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }
        $this->eventSchemaEnsured = true;

        $columns = $this->eventTableColumns($pdo, true);
        $columnSql = [
            'event_key' => 'ALTER TABLE notifications ADD COLUMN event_key VARCHAR(100) NULL AFTER created_at',
            'entity_type' => 'ALTER TABLE notifications ADD COLUMN entity_type VARCHAR(50) NULL AFTER event_key',
            'entity_id' => 'ALTER TABLE notifications ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER entity_type',
            'actor_user_id' => 'ALTER TABLE notifications ADD COLUMN actor_user_id BIGINT UNSIGNED NULL AFTER entity_id',
            'dedupe_key' => 'ALTER TABLE notifications ADD COLUMN dedupe_key VARCHAR(190) NULL AFTER actor_user_id',
            'delivery_channels' => 'ALTER TABLE notifications ADD COLUMN delivery_channels JSON NULL AFTER dedupe_key',
        ];

        foreach ($columnSql as $column => $sql) {
            if (!isset($columns[$column])) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    error_log('Notification event schema column update failed: ' . $e->getMessage());
                }
            }
        }

        $indexSql = [
            'notifications_event_key_index' => 'ALTER TABLE notifications ADD INDEX notifications_event_key_index (event_key, created_at)',
            'notifications_entity_index' => 'ALTER TABLE notifications ADD INDEX notifications_entity_index (entity_type, entity_id)',
            'notifications_actor_index' => 'ALTER TABLE notifications ADD INDEX notifications_actor_index (actor_user_id)',
            'notifications_dedupe_index' => 'ALTER TABLE notifications ADD INDEX notifications_dedupe_index (dedupe_key)',
        ];

        foreach ($indexSql as $indexName => $sql) {
            if (!$this->eventIndexExists($pdo, $indexName)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    error_log('Notification event schema index update failed: ' . $e->getMessage());
                }
            }
        }

        $this->eventTableColumns($pdo, true);
    }

    public function ensureTemplateSchema(PDO $pdo, NotificationTemplateService $templates): void
    {
        if ($this->templateSchemaEnsured) {
            return;
        }
        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }
        $this->templateSchemaEnsured = true;

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS notification_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key VARCHAR(100) NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                type VARCHAR(50) NOT NULL DEFAULT \'info\',
                title_template VARCHAR(255) NOT NULL,
                message_template TEXT NOT NULL,
                link_template VARCHAR(1024) NULL,
                in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
                email_enabled TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                variables_json JSON NULL,
                sample_payload JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_templates_key_unique (template_key),
                INDEX notification_templates_active_index (is_active, template_key),
                INDEX notification_templates_type_index (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $templates->seedDefaultTemplates($pdo);
    }

    public function ensureEmailQueueSchema(PDO $pdo): void
    {
        if ($this->emailQueueSchemaEnsured) {
            return;
        }
        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }
        $this->emailQueueSchemaEnsured = true;

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS notification_email_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255) NULL,
                template_key VARCHAR(100) NULL,
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                link VARCHAR(1024) NULL,
                status VARCHAR(30) NOT NULL DEFAULT \'queued\',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
                error_message TEXT NULL,
                provider_message_id VARCHAR(255) NULL,
                metadata_json JSON NULL,
                available_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                locked_at TIMESTAMP NULL,
                sent_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_email_queue_notification_user_unique (notification_id, user_id),
                INDEX notification_email_queue_status_available_index (status, available_at),
                INDEX notification_email_queue_user_index (user_id, created_at),
                INDEX notification_email_queue_template_index (template_key, created_at),
                CONSTRAINT notification_email_queue_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                CONSTRAINT notification_email_queue_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function ensureNotificationDismissalSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        if ($this->dismissalSchemaEnsured) {
            return;
        }
        if ($respectRuntimeGate && function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }
        $this->dismissalSchemaEnsured = true;

        if (!$this->tableExists($pdo, 'notifications')) {
            return;
        }

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS notification_dismissals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY notification_dismissals_unique (notification_id, user_id),
                INDEX notification_dismissals_user_index (user_id, created_at),
                CONSTRAINT notification_dismissals_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                CONSTRAINT notification_dismissals_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
}
