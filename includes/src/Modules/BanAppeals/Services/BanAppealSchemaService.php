<?php

declare(strict_types=1);

namespace App\Modules\BanAppeals\Services;

use App\Core\Database\SchemaInspector;
use PDO;

final class BanAppealSchemaService
{
    public function __construct(private ?SchemaInspector $inspector = null) { $this->inspector ??= new SchemaInspector(); }
    public function isSqlite(PDO $pdo): bool { return $this->inspector->isSqlite($pdo); }
    public function nowSql(PDO $pdo): string { return $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()'; }
    public function columnExists(PDO $pdo, string $table, string $column): bool { return $this->inspector->columnExists($pdo, $table, $column); }
    public function ensureSchema(PDO $pdo, bool $unused = true): void
    {
        $this->inspector->requireTables($pdo, ['ban_appeals', 'ban_appeal_messages']);
        $this->inspector->requireColumns($pdo, 'ban_appeal_messages', ['sender_type']);
    }
    public function ensureAppeals(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['ban_appeals']); }
    public function ensureMessages(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['ban_appeal_messages']); }
}
