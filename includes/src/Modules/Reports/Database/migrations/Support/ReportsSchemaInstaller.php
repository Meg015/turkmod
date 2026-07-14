<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\migrations\Support;

use PDO;

final class ReportsSchemaInstaller
{
    /** @var array<string,bool> */
    private array $initialized = [];

    public function isSqlite(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function ensureUserReports(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'user_reports', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reported_user_id INTEGER NOT NULL,
                    reporter_user_id INTEGER,
                    reason TEXT NOT NULL,
                    details TEXT,
                    status TEXT NOT NULL DEFAULT 'open',
                    admin_note TEXT,
                    created_at TEXT,
                    updated_at TEXT
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS user_reports_status_created_index ON user_reports (status, created_at)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS user_reports_reported_user_index ON user_reports (reported_user_id)');
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_reports (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    reported_user_id BIGINT UNSIGNED NOT NULL,
                    reporter_user_id BIGINT UNSIGNED NULL,
                    reason VARCHAR(255) NOT NULL,
                    details TEXT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'open',
                    admin_note TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX user_reports_status_created_index (status, created_at),
                    INDEX user_reports_reported_user_index (reported_user_id),
                    CONSTRAINT user_reports_reported_user_foreign FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT user_reports_reporter_user_foreign FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        });

        $this->ensureUserReportEvents($pdo, $respectRuntimeGate);
    }

    public function ensureUserReportEvents(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'user_report_events', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_report_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    report_id INTEGER NOT NULL,
                    actor_id INTEGER,
                    event_type TEXT NOT NULL,
                    old_status TEXT,
                    new_status TEXT,
                    note TEXT,
                    created_at TEXT
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS user_report_events_report_created_index ON user_report_events (report_id, created_at)');
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_report_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    report_id BIGINT UNSIGNED NOT NULL,
                    actor_id BIGINT UNSIGNED NULL,
                    event_type VARCHAR(255) NOT NULL,
                    old_status VARCHAR(255) NULL,
                    new_status VARCHAR(255) NULL,
                    note TEXT NULL,
                    created_at TIMESTAMP NULL,
                    INDEX user_report_events_report_created_index (report_id, created_at),
                    CONSTRAINT user_report_events_report_id_foreign FOREIGN KEY (report_id) REFERENCES user_reports(id) ON DELETE CASCADE,
                    CONSTRAINT user_report_events_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        });
    }

    public function ensureTopicReports(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'topic_reports', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS topic_reports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    topic_id INTEGER NOT NULL,
                    reporter_user_id INTEGER,
                    reporter_name TEXT,
                    reporter_email TEXT,
                    reporter_type TEXT,
                    reason TEXT NOT NULL,
                    details TEXT,
                    status TEXT NOT NULL DEFAULT 'open',
                    admin_note TEXT,
                    created_at TEXT,
                    updated_at TEXT
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_reports_status_created_index ON topic_reports (status, created_at)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_reports_topic_index ON topic_reports (topic_id)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_reports_reporter_email_index ON topic_reports (reporter_email)');
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS topic_reports (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    topic_id BIGINT UNSIGNED NOT NULL,
                    reporter_user_id BIGINT UNSIGNED NULL,
                    reporter_name VARCHAR(255) NULL,
                    reporter_email VARCHAR(255) NULL,
                    reporter_type VARCHAR(32) NULL,
                    reason VARCHAR(255) NOT NULL,
                    details TEXT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'open',
                    admin_note TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX topic_reports_status_created_index (status, created_at),
                    INDEX topic_reports_topic_index (topic_id),
                    INDEX topic_reports_reporter_email_index (reporter_email),
                    CONSTRAINT topic_reports_topic_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
                    CONSTRAINT topic_reports_reporter_user_foreign FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }

            $this->addTopicReportColumnsIfMissing($pdo);
        });

        $this->ensureTopicReportEvents($pdo, $respectRuntimeGate);
    }

    public function ensureTopicReportEvents(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'topic_report_events', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS topic_report_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    report_id INTEGER NOT NULL,
                    actor_id INTEGER,
                    event_type TEXT NOT NULL,
                    old_status TEXT,
                    new_status TEXT,
                    note TEXT,
                    created_at TEXT
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_report_events_report_created_index ON topic_report_events (report_id, created_at)');
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS topic_report_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    report_id BIGINT UNSIGNED NOT NULL,
                    actor_id BIGINT UNSIGNED NULL,
                    event_type VARCHAR(255) NOT NULL,
                    old_status VARCHAR(255) NULL,
                    new_status VARCHAR(255) NULL,
                    note TEXT NULL,
                    created_at TIMESTAMP NULL,
                    INDEX topic_report_events_report_created_index (report_id, created_at),
                    CONSTRAINT topic_report_events_report_id_foreign FOREIGN KEY (report_id) REFERENCES topic_reports(id) ON DELETE CASCADE,
                    CONSTRAINT topic_report_events_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        });
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

    private function addTopicReportColumnsIfMissing(PDO $pdo): void
    {
        foreach ([
            'reporter_name' => $this->isSqlite($pdo) ? 'TEXT' : 'VARCHAR(255) NULL',
            'reporter_email' => $this->isSqlite($pdo) ? 'TEXT' : 'VARCHAR(255) NULL',
            'reporter_type' => $this->isSqlite($pdo) ? 'TEXT' : 'VARCHAR(32) NULL',
        ] as $column => $definition) {
            if ($this->columnExists($pdo, 'topic_reports', $column)) {
                continue;
            }

            $pdo->exec('ALTER TABLE `topic_reports` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
        }

        if ($this->isSqlite($pdo)) {
            return;
        }

        try {
            $indexes = $pdo->query('SHOW INDEX FROM `topic_reports`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($indexes as $index) {
                if ((string) ($index['Key_name'] ?? '') === 'topic_reports_reporter_email_index') {
                    return;
                }
            }
            $pdo->exec('CREATE INDEX topic_reports_reporter_email_index ON topic_reports (reporter_email)');
        } catch (\Throwable) {
            // Index creation is best-effort.
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->query('PRAGMA table_info(`' . str_replace('`', '``', $table) . '`)');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*)
                               FROM INFORMATION_SCHEMA.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = :table_name
                                 AND COLUMN_NAME = :column_name');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
