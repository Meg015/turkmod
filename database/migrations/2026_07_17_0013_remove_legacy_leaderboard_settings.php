<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const LEGACY_KEYS = [
        'leaderboard_min_topics',
        'leaderboard_min_downloads',
        'leaderboard_min_views',
        'leaderboard_reset_frequency',
    ];

    public function name(): string
    {
        return '2026_07_17_0013_remove_legacy_leaderboard_settings';
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
        throw new RuntimeException('Removed legacy leaderboard settings are not restored automatically.');
    }
};
