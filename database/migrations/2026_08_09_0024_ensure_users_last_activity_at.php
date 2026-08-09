<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_08_09_0024_ensure_users_last_activity_at';
    }

    private function hasColumn(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ');
        $stmt->execute([
            'table_name' => 'users',
            'column_name' => 'last_activity_at',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasIndex(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ');
        $stmt->execute([
            'table_name' => 'users',
            'index_name' => 'idx_last_activity',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function up(PDO $pdo): void
    {
        if (!$this->hasColumn($pdo)) {
            $pdo->exec("
                ALTER TABLE users
                ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Son aktivite zamani'
            ");
        }

        if (!$this->hasIndex($pdo)) {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_last_activity (last_activity_at)');
        }
    }

    public function down(PDO $pdo): void
    {
        // The column is part of the baseline schema and may predate this
        // compatibility migration, so rollback intentionally keeps it intact.
    }
};
