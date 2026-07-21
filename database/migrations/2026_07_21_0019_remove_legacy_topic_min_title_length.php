<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const LEGACY_KEYS = [
        'topic_min_title_length',
    ];

    public function name(): string
    {
        return '2026_07_21_0019_remove_legacy_topic_min_title_length';
    }

    public function up(PDO $pdo): void
    {
        $placeholders = implode(',', array_fill(0, count(self::LEGACY_KEYS), '?'));
        $stmt = $pdo->prepare("DELETE FROM admin_settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute(self::LEGACY_KEYS);

        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Removed legacy topic title length setting is not restored automatically.');
    }
};
