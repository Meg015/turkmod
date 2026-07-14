<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0006_drop_topic_like_count';
    }

    public function up(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topics' AND COLUMN_NAME = 'like_count'"
        );
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            return;
        }

        $pdo->exec('ALTER TABLE topics DROP COLUMN like_count');
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Eski like_count kolonu bilinçli olarak kaldırıldı; otomatik geri dönüş desteklenmiyor.');
    }
};
