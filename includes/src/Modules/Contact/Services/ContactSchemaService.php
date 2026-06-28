<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use PDO;
use Throwable;

final class ContactSchemaService
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

    public function ensureSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->ensureCategoriesTable($pdo, $respectRuntimeGate);
        $this->ensureMessagesTable($pdo, $respectRuntimeGate);
        $this->seedDefaultCategories($pdo, $respectRuntimeGate);
    }

    public function ensureCategoriesTable(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'contact_categories', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS contact_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    icon TEXT NOT NULL DEFAULT 'bi-envelope',
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_categories_active_sort_index ON contact_categories (is_active, sort_order, id)');

                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS contact_categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                icon VARCHAR(80) NOT NULL DEFAULT 'bi-envelope',
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY contact_categories_slug_unique (slug),
                KEY contact_categories_active_sort_index (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        });
    }

    public function ensureMessagesTable(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'contact_messages', $respectRuntimeGate, function () use ($pdo): void {
            if ($this->isSqlite($pdo)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category_id INTEGER NULL,
                    category_name_snapshot TEXT NOT NULL,
                    category_icon_snapshot TEXT NOT NULL DEFAULT 'bi-envelope',
                    user_id INTEGER NULL,
                    is_member INTEGER NOT NULL DEFAULT 0,
                    sender_name TEXT NOT NULL,
                    sender_email TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    message TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'new',
                    seen_at TEXT NULL,
                    admin_reply_body TEXT NULL,
                    admin_reply_sent_at TEXT NULL,
                    admin_reply_admin_id INTEGER NULL,
                    admin_reply_email_status TEXT NOT NULL DEFAULT 'pending',
                    admin_reply_email_error TEXT NULL,
                    submitted_ip TEXT NULL,
                    submitted_user_agent TEXT NULL,
                    created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES contact_categories(id) ON DELETE SET NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (admin_reply_admin_id) REFERENCES users(id) ON DELETE SET NULL
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_status_created_index ON contact_messages (status, created_at DESC, id DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_category_index ON contact_messages (category_id, created_at DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_user_index ON contact_messages (user_id, created_at DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_reply_admin_index ON contact_messages (admin_reply_admin_id, created_at DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_email_status_index ON contact_messages (admin_reply_email_status, created_at DESC)');

                return;
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id BIGINT UNSIGNED NULL,
                category_name_snapshot VARCHAR(160) NOT NULL,
                category_icon_snapshot VARCHAR(80) NOT NULL DEFAULT 'bi-envelope',
                user_id BIGINT UNSIGNED NULL,
                is_member TINYINT(1) NOT NULL DEFAULT 0,
                sender_name VARCHAR(160) NOT NULL,
                sender_email VARCHAR(255) NOT NULL,
                subject VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                seen_at TIMESTAMP NULL,
                admin_reply_body TEXT NULL,
                admin_reply_sent_at TIMESTAMP NULL,
                admin_reply_admin_id BIGINT UNSIGNED NULL,
                admin_reply_email_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                admin_reply_email_error TEXT NULL,
                submitted_ip VARCHAR(64) NULL,
                submitted_user_agent TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY contact_messages_status_created_index (status, created_at, id),
                KEY contact_messages_category_index (category_id, created_at),
                KEY contact_messages_user_index (user_id, created_at),
                KEY contact_messages_reply_admin_index (admin_reply_admin_id, created_at),
                KEY contact_messages_email_status_index (admin_reply_email_status, created_at),
                CONSTRAINT contact_messages_category_id_foreign FOREIGN KEY (category_id) REFERENCES contact_categories(id) ON DELETE SET NULL,
                CONSTRAINT contact_messages_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT contact_messages_reply_admin_id_foreign FOREIGN KEY (admin_reply_admin_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        });

        $this->ensureReplyAuditColumns($pdo, $respectRuntimeGate);
    }

    private function ensureReplyAuditColumns(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'contact_messages_reply_audit', $respectRuntimeGate, function () use ($pdo): void {
            try {
                if (!$this->columnExists($pdo, 'contact_messages', 'admin_reply_admin_id')) {
                    if ($this->isSqlite($pdo)) {
                        $pdo->exec('ALTER TABLE contact_messages ADD COLUMN admin_reply_admin_id INTEGER NULL');
                    } else {
                        $pdo->exec('ALTER TABLE contact_messages ADD COLUMN admin_reply_admin_id BIGINT UNSIGNED NULL AFTER admin_reply_sent_at');
                    }
                }

                if (!$this->indexExists($pdo, 'contact_messages', 'contact_messages_reply_admin_index')) {
                    if ($this->isSqlite($pdo)) {
                        $pdo->exec('CREATE INDEX IF NOT EXISTS contact_messages_reply_admin_index ON contact_messages (admin_reply_admin_id, created_at DESC)');
                    } else {
                        $pdo->exec('ALTER TABLE contact_messages ADD INDEX contact_messages_reply_admin_index (admin_reply_admin_id, created_at)');
                    }
                }
            } catch (Throwable $exception) {
                error_log('Contact reply audit columns ensure failed: ' . $exception->getMessage());
            }
        });
    }

    /**
     * @return array<int,array{name:string,slug:string,icon:string,sort_order:int,is_active:int}>
     */
    public function defaultCategories(): array
    {
        return [
            ['name' => 'Destek', 'slug' => 'destek', 'icon' => 'bi-headset', 'sort_order' => 10, 'is_active' => 1],
            ['name' => 'Reklam', 'slug' => 'reklam', 'icon' => 'bi-megaphone', 'sort_order' => 20, 'is_active' => 1],
            ['name' => 'Oneri', 'slug' => 'oneri', 'icon' => 'bi-lightbulb', 'sort_order' => 30, 'is_active' => 1],
            ['name' => 'Sikayet', 'slug' => 'sikayet', 'icon' => 'bi-exclamation-triangle', 'sort_order' => 40, 'is_active' => 1],
            ['name' => 'DMCA & Telif', 'slug' => 'dmca-telif', 'icon' => 'bi-shield-lock', 'sort_order' => 50, 'is_active' => 1],
        ];
    }

    private function seedDefaultCategories(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $this->once($pdo, 'contact_categories_seed', $respectRuntimeGate, function () use ($pdo): void {
            try {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM contact_categories')->fetchColumn();
                if ($count > 0) {
                    return;
                }

                $nowSql = $this->nowSql($pdo);
                $insertSql = $this->isSqlite($pdo)
                    ? 'INSERT INTO contact_categories (name, slug, icon, sort_order, is_active, created_at, updated_at) VALUES (:name, :slug, :icon, :sort_order, :is_active, ' . $nowSql . ', ' . $nowSql . ')'
                    : 'INSERT INTO contact_categories (name, slug, icon, sort_order, is_active, created_at, updated_at) VALUES (:name, :slug, :icon, :sort_order, :is_active, ' . $nowSql . ', ' . $nowSql . ')';

                $stmt = $pdo->prepare($insertSql);
                foreach ($this->defaultCategories() as $category) {
                    $stmt->execute([
                        'name' => $category['name'],
                        'slug' => $category['slug'],
                        'icon' => $category['icon'],
                        'sort_order' => (int) $category['sort_order'],
                        'is_active' => (int) $category['is_active'],
                    ]);
                }
            } catch (Throwable $exception) {
                error_log('Contact category seed failed: ' . $exception->getMessage());
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

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
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

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
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

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $stmt->execute([$table, $index]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
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
