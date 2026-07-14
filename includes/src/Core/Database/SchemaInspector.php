<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use RuntimeException;
use Throwable;

final class SchemaInspector
{
    public function isSqlite(PDO $pdo): bool
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
                $stmt->execute([$table]);
                return (bool) $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                foreach ($pdo->query("PRAGMA table_info({$safeTable})")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string) ($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function indexExists(PDO $pdo, string $table, string $index): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                foreach ($pdo->query("PRAGMA index_list({$safeTable})")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string) ($row['name'] ?? '') === $index) {
                        return true;
                    }
                }
                return false;
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $stmt->execute([$table, $index]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function requireTables(PDO $pdo, array $tables): void
    {
        $missing = array_values(array_filter($tables, fn (string $table): bool => !$this->tableExists($pdo, $table)));
        if ($missing !== []) {
            throw new RuntimeException('Missing database tables: ' . implode(', ', $missing) . '; run Admin Panel > Database Synchronization.');
        }
    }

    public function requireColumns(PDO $pdo, string $table, array $columns): void
    {
        $missing = array_values(array_filter($columns, fn (string $column): bool => !$this->columnExists($pdo, $table, $column)));
        if ($missing !== []) {
            throw new RuntimeException('Missing ' . $table . ' columns: ' . implode(', ', $missing) . '; run Admin Panel > Database Synchronization.');
        }
    }
}
