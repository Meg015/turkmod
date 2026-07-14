<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const EMPTY_TABLES = [
        'report_events',
        'reports',
        'ratings',
        'reactions',
        'blocked_ips',
        'failed_login_attempts',
        'suspicious_activities',
        'pages',
        'permissions',
    ];

    public function name(): string
    {
        return '2026_07_14_0004_drop_obsolete_tables_and_ratings';
    }

    public function up(PDO $pdo): void
    {
        foreach (self::EMPTY_TABLES as $table) {
            if ($this->tableExists($pdo, $table)) {
                $pdo->exec('DROP TABLE `' . $table . '`');
            }
        }

        if ($this->tableExists($pdo, 'users_username_backup_20260710_184907')) {
            $pdo->exec('DROP TABLE `users_username_backup_20260710_184907`');
        }

        $this->dropColumnIfExists($pdo, 'topics', 'rating_average');
        $this->dropColumnIfExists($pdo, 'topics', 'rating_count');
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Kaldırılan eski tablolar ve puan alanları için otomatik geri dönüş desteklenmiyor.');
    }

    private function dropColumnIfExists(PDO $pdo, string $table, string $column): void
    {
        if ($this->tableExists($pdo, $table) && isset($this->columns($pdo, $table)[$column])) {
            $pdo->exec('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<string,bool> */
    private function columns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);

        return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
    }
};
