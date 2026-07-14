<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_15_0012_normalize_user_group_sequence';
    }

    public function up(PDO $pdo): void
    {
        if (strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
            return;
        }

        $nextId = max(1, (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM user_groups')->fetchColumn());
        $pdo->exec('ALTER TABLE user_groups AUTO_INCREMENT = ' . $nextId);
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('User group sequence normalization is not reverted automatically.');
    }
};
