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
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }

            $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            if ($count > 0) {
                throw new RuntimeException($table . ' tablosunda ' . $count . ' kayıt bulundu; veri kaybını önlemek için temizlik durduruldu.');
            }
        }

        $this->validateUsernameBackup($pdo);
        $this->validateTopicRatings($pdo);

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

    private function validateUsernameBackup(PDO $pdo): void
    {
        $table = 'users_username_backup_20260710_184907';
        if (!$this->tableExists($pdo, $table)) {
            return;
        }

        $columns = $this->columns($pdo, $table);
        foreach (['id', 'old_username'] as $requiredColumn) {
            if (!isset($columns[$requiredColumn])) {
                throw new RuntimeException($table . ' doğrulanamadı; ' . $requiredColumn . ' kolonu eksik.');
            }
        }

        $emailColumn = isset($columns['user_email']) ? 'user_email' : (isset($columns['email']) ? 'email' : '');
        if ($emailColumn === '') {
            throw new RuntimeException($table . ' doğrulanamadı; e-posta kolonu eksik.');
        }

        $missingUsers = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM `users_username_backup_20260710_184907` backup
             LEFT JOIN users matched_user ON matched_user.id = backup.id
             WHERE matched_user.id IS NULL'
        )->fetchColumn();
        if ($missingUsers > 0) {
            throw new RuntimeException('Kullanıcı adı yedeğinde güncel users tablosunda bulunmayan ' . $missingUsers . ' kayıt var; yedek silinmedi.');
        }

        $emailMismatches = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM `users_username_backup_20260710_184907` backup
             INNER JOIN users matched_user ON matched_user.id = backup.id
             WHERE NOT (matched_user.email <=> backup.`' . $emailColumn . '`)'
        )->fetchColumn();
        if ($emailMismatches > 0) {
            throw new RuntimeException('Kullanıcı adı yedeğinde ' . $emailMismatches . ' e-posta eşleşmezliği var; yedek silinmedi.');
        }
    }

    private function validateTopicRatings(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'topics')) {
            return;
        }

        $columns = $this->columns($pdo, 'topics');
        if (!isset($columns['rating_average']) && !isset($columns['rating_count'])) {
            return;
        }

        $conditions = [];
        if (isset($columns['rating_average'])) {
            $conditions[] = 'COALESCE(rating_average, 0) <> 0';
        }
        if (isset($columns['rating_count'])) {
            $conditions[] = 'COALESCE(rating_count, 0) <> 0';
        }

        $nonZeroCount = (int) $pdo->query('SELECT COUNT(*) FROM topics WHERE ' . implode(' OR ', $conditions))->fetchColumn();
        if ($nonZeroCount > 0) {
            throw new RuntimeException('topics tablosunda puan verisi bulunan ' . $nonZeroCount . ' kayıt var; puan kolonları silinmedi.');
        }
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
