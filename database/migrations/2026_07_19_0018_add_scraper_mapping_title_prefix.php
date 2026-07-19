<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_19_0018_add_scraper_mapping_title_prefix';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'bot_category_mappings') || $this->columnExists($pdo, 'bot_category_mappings', 'title_prefix')) {
            return;
        }

        if ($this->isSqlite($pdo)) {
            $pdo->exec("ALTER TABLE bot_category_mappings ADD COLUMN title_prefix TEXT NOT NULL DEFAULT ''");
            return;
        }

        $pdo->exec("ALTER TABLE `bot_category_mappings` ADD COLUMN `title_prefix` VARCHAR(100) NOT NULL DEFAULT '' AFTER `remote_category_url`");
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'bot_category_mappings') || !$this->columnExists($pdo, 'bot_category_mappings', 'title_prefix')) {
            return;
        }

        if ($this->isSqlite($pdo)) {
            throw new RuntimeException('SQLite does not support dropping bot_category_mappings.title_prefix automatically.');
        }

        $pdo->exec('ALTER TABLE `bot_category_mappings` DROP COLUMN `title_prefix`');
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

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $table = $this->identifier($table);
        $column = $this->identifier($column);
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
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
