<?php

declare(strict_types=1);

namespace App\Modules\Messages\Services;

use App\Core\Database\SchemaInspector;
use PDO;

final class MessageSchemaService
{
    public function __construct(private ?SchemaInspector $inspector = null)
    {
        $this->inspector ??= new SchemaInspector();
    }

    public function isSqlite(PDO $pdo): bool { return $this->inspector->isSqlite($pdo); }
    public function nowSql(PDO $pdo): string { return $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()'; }
    public function insertIgnorePrefix(PDO $pdo): string { return $this->isSqlite($pdo) ? 'INSERT OR IGNORE' : 'INSERT IGNORE'; }
    public function tableExists(PDO $pdo, string $table): bool { return $this->inspector->tableExists($pdo, $table); }
    public function columnExists(PDO $pdo, string $table, string $column): bool { return $this->inspector->columnExists($pdo, $table, $column); }
    public function indexExists(PDO $pdo, string $table, string $index): bool { return $this->inspector->indexExists($pdo, $table, $index); }

    public function ensureSchema(PDO $pdo, bool $unused = true): void
    {
        $this->inspector->requireTables($pdo, ['message_threads', 'message_thread_participants', 'message_messages']);
        $this->inspector->requireColumns($pdo, 'message_thread_participants', ['typing_at']);
        $this->inspector->requireColumns($pdo, 'message_messages', ['is_deleted']);
    }

    public function ensureThreadsTable(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['message_threads']); }
    public function ensureParticipantsTable(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['message_thread_participants']); }
    public function ensureMessagesTable(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['message_messages']); }
}
