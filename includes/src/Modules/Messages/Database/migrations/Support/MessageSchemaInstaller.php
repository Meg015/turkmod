<?php

declare(strict_types=1);

namespace App\Modules\Messages\Database\migrations\Support;

use PDO;
use Throwable;

final class MessageSchemaInstaller
{
    /** @var array<string,bool> */
    private array $initialized = [];

    public function isSqlite(PDO $pdo): bool
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }

    public function nowSql(PDO $pdo): string
    {
        return $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()';
    }

    public function insertIgnorePrefix(PDO $pdo): string
    {
        return $this->isSqlite($pdo) ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    }

    public function ensureSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureThreadsTable($pdo, $respectRuntimeGate);
        $this->ensureParticipantsTable($pdo, $respectRuntimeGate);
        $this->ensureMessagesTable($pdo, $respectRuntimeGate);
    }

    public function ensureThreadsTable(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'message_threads', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS message_threads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    thread_key TEXT NOT NULL UNIQUE,
                    last_message_id INTEGER NULL,
                    last_message_at TEXT NULL,
                    created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_threads_last_message_at_index ON message_threads (last_message_at, id)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_threads_last_message_id_index ON message_threads (last_message_id)');

                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS message_threads (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_key VARCHAR(120) NOT NULL,
                last_message_id BIGINT UNSIGNED NULL,
                last_message_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY message_threads_thread_key_unique (thread_key),
                KEY message_threads_last_message_at_index (last_message_at, id),
                KEY message_threads_last_message_id_index (last_message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        });
    }

    public function ensureParticipantsTable(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureThreadsTable($pdo, $respectRuntimeGate);
        $this->once($pdo, 'message_thread_participants', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS message_thread_participants (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    thread_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    last_read_message_id INTEGER NULL,
                    last_read_at TEXT NULL,
                    typing_at TEXT NULL,
                    created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(thread_id, user_id),
                    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_participants_user_read_index ON message_thread_participants (user_id, last_read_at)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_participants_thread_read_index ON message_thread_participants (thread_id, last_read_message_id)');

                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS message_thread_participants (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                last_read_message_id BIGINT UNSIGNED NULL,
                last_read_at TIMESTAMP NULL,
                typing_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY message_participants_thread_user_unique (thread_id, user_id),
                KEY message_participants_user_read_index (user_id, last_read_at),
                KEY message_participants_thread_read_index (thread_id, last_read_message_id),
                CONSTRAINT message_participants_thread_foreign FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
                CONSTRAINT message_participants_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if (!$this->columnExists($pdo, 'message_thread_participants', 'typing_at')) {
                if ($this->isSqlite($pdo)) {
                    $pdo->exec("ALTER TABLE message_thread_participants ADD COLUMN typing_at TEXT NULL");
                } else {
                    $pdo->exec("ALTER TABLE message_thread_participants ADD COLUMN typing_at TIMESTAMP NULL AFTER last_read_at");
                }
            }
        });
    }

    public function ensureMessagesTable(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureThreadsTable($pdo, $respectRuntimeGate);
        $this->once($pdo, 'message_messages', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS message_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    thread_id INTEGER NOT NULL,
                    sender_user_id INTEGER NOT NULL,
                    body TEXT NOT NULL,
                    is_deleted INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_messages_thread_id_index ON message_messages (thread_id, id)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_messages_thread_created_index ON message_messages (thread_id, created_at)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS message_messages_sender_created_index ON message_messages (sender_user_id, created_at)');

                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS message_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_id BIGINT UNSIGNED NOT NULL,
                sender_user_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY message_messages_thread_id_index (thread_id, id),
                KEY message_messages_thread_created_index (thread_id, created_at),
                KEY message_messages_sender_created_index (sender_user_id, created_at),
                CONSTRAINT message_messages_thread_foreign FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
                CONSTRAINT message_messages_sender_foreign FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if (!$this->columnExists($pdo, 'message_messages', 'is_deleted')) {
                $pdo->exec("ALTER TABLE message_messages ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER body");
            }
        });
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
                $stmt->execute([$table]);

                return (bool) $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ');
            $stmt->execute([$table]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
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
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function indexExists(PDO $pdo, string $table, string $index): bool
    {
        try {
            if ($this->isSqlite($pdo)) {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $stmt = $pdo->prepare("PRAGMA index_list({$safeTable})");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if ((string) ($row['name'] ?? '') === $index) {
                        return true;
                    }
                }

                return false;
            }

            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ');
            $stmt->execute([$table, $index]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function once(PDO $pdo, string $name, bool $respectRuntimeGate, callable $callback): void
    {
        if ($respectRuntimeGate && function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            if (!$this->tableExists($pdo, $name)) {
                throw new \RuntimeException("Missing required table {$name}; run Database Synchronization.");
            }
            $this->initialized[spl_object_id($pdo) . ':' . $name] = true;
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
