<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Core\Database\SchemaInspector;
use PDO;
use Throwable;

final class NotificationSchemaService
{
    private const EMAIL_TEMPLATE_COLUMNS = [
        'email_subject_template',
        'email_body_template',
        'email_link_template',
        'email_preview_template',
    ];

    /** @var array<string,bool>|null */
    private ?array $eventColumns = null;
    /** @var array<string,bool>|null */
    private ?array $templateColumns = null;
    private SchemaInspector $inspector;
    private bool $eventSchemaEnsured = false;
    private bool $templateSchemaEnsured = false;
    private bool $emailQueueSchemaEnsured = false;
    private bool $dismissalSchemaEnsured = false;
    private bool $suppressionLogSchemaEnsured = false;

    public function __construct(?SchemaInspector $inspector = null)
    {
        $this->inspector = $inspector ?? new SchemaInspector();
    }

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

    /** @return array<string,bool> */
    public function templateTableColumns(PDO $pdo, bool $refresh = false): array
    {
        if ($this->templateColumns !== null && !$refresh) {
            return $this->templateColumns;
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM notification_templates');
            $this->templateColumns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $this->templateColumns[(string) $row['Field']] = true;
            }
        } catch (Throwable $e) {
            error_log('Notification template column lookup failed: ' . $e->getMessage());
            $this->templateColumns = [];
        }

        return $this->templateColumns;
    }

    public function tableExists(PDO $pdo, string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        return $this->inspector->tableExists($pdo, $tableName);
    }

    /** @return list<string> */
    public function missingEmailTemplateColumns(PDO $pdo, bool $refresh = false): array
    {
        if (!$this->tableExists($pdo, 'notification_templates')) {
            return self::EMAIL_TEMPLATE_COLUMNS;
        }

        $columns = $this->templateTableColumns($pdo, $refresh);

        return array_values(array_filter(
            self::EMAIL_TEMPLATE_COLUMNS,
            static fn (string $column): bool => !isset($columns[$column])
        ));
    }

    public function hasEmailTemplateColumns(PDO $pdo, bool $refresh = false): bool
    {
        return $this->missingEmailTemplateColumns($pdo, $refresh) === [];
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

    public function ensureSuppressionLogSchema(PDO $pdo): void
    {
        if ($this->suppressionLogSchemaEnsured) {
            return;
        }
        if (!$this->tableExists($pdo, 'notification_dispatch_suppression_logs')) {
            throw new \RuntimeException('Missing notification_dispatch_suppression_logs; run Database Synchronization.');
        }
        $this->suppressionLogSchemaEnsured = true;
    }
}
