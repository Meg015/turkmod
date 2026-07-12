<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_12_130000_add_email_verification_lifecycle';
    }

    private function hasColumn(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['users', $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function up(PDO $pdo): void
    {
        if (!$this->hasColumn($pdo, 'email_verification_expires_at')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_expires_at TIMESTAMP NULL DEFAULT NULL AFTER email_verification_token');
        }
        if (!$this->hasColumn($pdo, 'email_verification_sent_at')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_sent_at TIMESTAMP NULL DEFAULT NULL AFTER email_verification_expires_at');
        }
    }

    public function down(PDO $pdo): void
    {
        if ($this->hasColumn($pdo, 'email_verification_sent_at')) {
            $pdo->exec('ALTER TABLE users DROP COLUMN email_verification_sent_at');
        }
        if ($this->hasColumn($pdo, 'email_verification_expires_at')) {
            $pdo->exec('ALTER TABLE users DROP COLUMN email_verification_expires_at');
        }
    }
};
