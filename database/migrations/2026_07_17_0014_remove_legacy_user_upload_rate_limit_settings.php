<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const LEGACY_KEYS = [
        'user_upload_hourly_limit',
        'user_upload_daily_limit',
    ];

    public function name(): string
    {
        return '2026_07_17_0014_remove_legacy_user_upload_rate_limit_settings';
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
        throw new RuntimeException('Removed legacy user upload rate limit settings are not restored automatically.');
    }
};
