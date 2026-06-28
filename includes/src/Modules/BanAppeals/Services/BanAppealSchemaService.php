<?php

declare(strict_types=1);

namespace App\Modules\BanAppeals\Services;

use PDO;
use Throwable;

final class BanAppealSchemaService
{
    /** @var array<string,bool> */
    private array $initialized = [];

    public function isSqlite(PDO $pdo): bool
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }

    public function nowSql(PDO $pdo): string
    {
        if (function_exists('userActivityNowSql')) {
            return userActivityNowSql($pdo);
        }

        return $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()';
    }

    public function ensureSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureAppeals($pdo, $respectRuntimeGate);
        $this->ensureMessages($pdo, $respectRuntimeGate);
    }

    public function ensureAppeals(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'ban_appeals', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS ban_appeals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    message TEXT NOT NULL,
                    admin_note TEXT,
                    status TEXT NOT NULL DEFAULT 'open',
                    reviewed_by INTEGER,
                    reviewed_at TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS ban_appeals_user_status_index ON ban_appeals (user_id, status)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS ban_appeals_status_created_index ON ban_appeals (status, created_at)');
                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS ban_appeals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                admin_note TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'open',
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ban_appeals_user_status_index (user_id, status),
                KEY ban_appeals_status_created_index (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        });
    }

    public function ensureMessages(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureAppeals($pdo, $respectRuntimeGate);
        $this->once($pdo, 'ban_appeal_messages', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS ban_appeal_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    appeal_id INTEGER NOT NULL,
                    sender_user_id INTEGER NULL,
                    sender_type TEXT NOT NULL DEFAULT 'user',
                    message TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS ban_appeal_messages_appeal_created ON ban_appeal_messages (appeal_id, created_at)');
                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS ban_appeal_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appeal_id BIGINT UNSIGNED NOT NULL,
                sender_user_id BIGINT UNSIGNED NULL,
                sender_type VARCHAR(20) NOT NULL DEFAULT 'user',
                message TEXT NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ban_appeal_messages_appeal_created (appeal_id, created_at),
                KEY ban_appeal_messages_sender (sender_user_id),
                CONSTRAINT ban_appeal_messages_appeal_foreign FOREIGN KEY (appeal_id) REFERENCES ban_appeals(id) ON DELETE CASCADE,
                CONSTRAINT ban_appeal_messages_sender_foreign FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        });

        if (!$this->columnExists($pdo, 'ban_appeal_messages', 'sender_type')) {
            try {
                $definition = $this->isSqlite($pdo)
                    ? "TEXT NOT NULL DEFAULT 'user'"
                    : "VARCHAR(20) NOT NULL DEFAULT 'user'";
                $pdo->exec("ALTER TABLE ban_appeal_messages ADD COLUMN sender_type {$definition}");
            } catch (Throwable $e) {
                error_log('Ban appeal sender_type migration failed: ' . $e->getMessage());
            }
        }
    }

    public function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $stmt = $pdo->prepare("PRAGMA table_info({$safeTable})");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string) ($row['name'] ?? '') === $column) {
                        return true;
                    }
                }

                return false;
            }

            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ');
            $stmt->execute([$table, $column]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('Ban appeal column lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    private function once(PDO $pdo, string $name, bool $respectRuntimeGate, callable $callback): void
    {
        if ($respectRuntimeGate && function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }

        $key = spl_object_id($pdo) . ':' . $name;
        if (!empty($this->initialized[$key])) {
            return;
        }

        $callback();
        $this->initialized[$key] = true;
    }
}
