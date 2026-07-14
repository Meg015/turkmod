<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0007_normalize_legacy_topic_statuses';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec("UPDATE topics SET status = 'draft' WHERE status = 'pending'");
        $pdo->exec("UPDATE topics SET status = 'published', published_at = COALESCE(published_at, created_at, NOW()) WHERE status = 'archived'");
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Eski konu durumları kalıcı olarak normalize edildi; otomatik geri dönüş desteklenmiyor.');
    }
};
