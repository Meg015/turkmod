<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_19_0017_add_admin_moderation_indexes';
    }

    public function up(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'comments')) {
            $this->addIndex(
                $pdo,
                'comments',
                'comments_status_deleted_created_index',
                '`status`(191), `deleted_at`, `created_at`',
                'status, deleted_at, created_at'
            );
        }

        if ($this->tableExists($pdo, 'admin_action_log')) {
            $this->addIndex(
                $pdo,
                'admin_action_log',
                'admin_action_log_target_created_index',
                '`target_type`, `target_id`, `created_at`',
                'target_type, target_id, created_at'
            );
        }

        if ($this->tableExists($pdo, 'user_restrictions')) {
            $this->addIndex(
                $pdo,
                'user_restrictions',
                'user_restrictions_user_expires_index',
                '`user_id`, `expires_at`',
                'user_id, expires_at'
            );
        }
    }

    public function down(PDO $pdo): void
    {
        foreach ([
            ['comments', 'comments_status_deleted_created_index'],
            ['admin_action_log', 'admin_action_log_target_created_index'],
            ['user_restrictions', 'user_restrictions_user_expires_index'],
        ] as [$table, $index]) {
            if ($this->tableExists($pdo, $table)) {
                $this->dropIndex($pdo, $table, $index);
            }
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $table = $this->identifier($table);
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return (bool)$stmt->fetchColumn();
    }

    private function addIndex(PDO $pdo, string $table, string $index, string $mysqlColumns, string $sqliteColumns): void
    {
        $table = $this->identifier($table);
        $index = $this->identifier($index);
        if ($this->indexExists($pdo, $table, $index)) {
            return;
        }

        if ($this->isSqlite($pdo)) {
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$index} ON {$table} ({$sqliteColumns})");
            return;
        }

        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$mysqlColumns})");
    }

    private function dropIndex(PDO $pdo, string $table, string $index): void
    {
        $table = $this->identifier($table);
        $index = $this->identifier($index);
        if (!$this->indexExists($pdo, $table, $index)) {
            return;
        }

        if ($this->isSqlite($pdo)) {
            $pdo->exec("DROP INDEX IF EXISTS {$index}");
            return;
        }

        $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $table = $this->identifier($table);
        $index = $this->identifier($index);
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->query("PRAGMA index_list({$table})");
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
        $stmt->execute([$index]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function isSqlite(PDO $pdo): bool
    {
        return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function identifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }

        return $identifier;
    }
};
