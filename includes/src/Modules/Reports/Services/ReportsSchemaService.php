<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use PDO;

final class ReportsSchemaService
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
                    reason TEXT NOT NULL,
                    details TEXT,
                    status TEXT NOT NULL DEFAULT 'open',
                    admin_note TEXT,
                    created_at TEXT,
                    updated_at TEXT
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_reports_status_created_index ON topic_reports (status, created_at)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS topic_reports_topic_index ON topic_reports (topic_id)');
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS topic_reports (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    topic_id BIGINT UNSIGNED NOT NULL,
                    reporter_user_id BIGINT UNSIGNED NULL,
                    reason VARCHAR(255) NOT NULL,
                    details TEXT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'open',
                    admin_note TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX topic_reports_status_created_index (status, created_at),
                    INDEX topic_reports_topic_index (topic_id),
                    CONSTRAINT topic_reports_topic_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
                    CONSTRAINT topic_reports_reporter_user_foreign FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
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
