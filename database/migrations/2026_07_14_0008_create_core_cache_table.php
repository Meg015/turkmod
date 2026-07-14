<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0008_create_core_cache_table';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS core_cache (
                cache_key VARCHAR(191) NOT NULL PRIMARY KEY,
                cache_value LONGTEXT NOT NULL,
                tags LONGTEXT NULL,
                expires_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                KEY cache_expires_index (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS core_cache');
    }
};
