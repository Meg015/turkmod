<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_27_0022_widen_author_topic_columns';
    }

    public function up(PDO $pdo): void
    {
        $tables = ['topics', 'bot_imports', 'topic_revisions'];

        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table) || !$this->columnExists($pdo, $table, 'author_topic')) {
                continue;
            }

            if ($this->isSqlite($pdo)) {
                continue;
            }

            try {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `author_topic` TEXT DEFAULT NULL");
            } catch (Throwable $e) {
                error_log('[Migration] Column widen author_topic failed on table ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    public function down(PDO $pdo): void
    {
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if (strcasecmp((string)($row['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        return (bool)$stmt->fetchColumn();
    }

    private function isSqlite(PDO $pdo): bool
    {
        return strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }
};
