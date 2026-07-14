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
        $required = ['event_key', 'entity_type', 'entity_id', 'actor_user_id', 'dedupe_key', 'delivery_channels'];
        $columns = $this->eventTableColumns($pdo, true);
        $missing = array_values(array_filter($required, static fn (string $column): bool => !isset($columns[$column])));
        if ($missing !== []) {
            throw new \RuntimeException('Missing notifications columns: ' . implode(', ', $missing) . '; run Database Synchronization.');
        }
        $this->eventSchemaEnsured = true;
    }

    public function ensureTemplateSchema(PDO $pdo, NotificationTemplateService $templates): void
    {
        if ($this->templateSchemaEnsured) {
            return;
        }
        if (!$this->tableExists($pdo, 'notification_templates')) {
            throw new \RuntimeException('Missing notification_templates; run Database Synchronization.');
        }
        $this->templateSchemaEnsured = true;
        $templates->seedDefaultTemplates($pdo);
    }

    public function ensureEmailQueueSchema(PDO $pdo): void
    {
        if ($this->emailQueueSchemaEnsured) {
            return;
        }
        if (!$this->tableExists($pdo, 'notification_email_queue')) {
            throw new \RuntimeException('Missing notification_email_queue; run Database Synchronization.');
        }
        $this->emailQueueSchemaEnsured = true;
    }

    public function ensureNotificationDismissalSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        if ($this->dismissalSchemaEnsured) {
            return;
        }
        if (!$this->tableExists($pdo, 'notification_dismissals')) {
            throw new \RuntimeException('Missing notification_dismissals; run Database Synchronization.');
        }
        $this->dismissalSchemaEnsured = true;
    }
}
