<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0010_normalize_brand_accent_settings';
    }

    public function up(PDO $pdo): void
    {
        $keys = [
            'accent_color',
            'header_accent_color',
            'header_border_color',
            'menu_cta_color',
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            "UPDATE admin_settings
             SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
             WHERE setting_key IN ({$placeholders})
               AND LOWER(TRIM(setting_value)) = ?",
        );
        $stmt->execute(array_merge(['#8b1538'], $keys, ['#f2a51a']));
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Canonical brand accent values are not reverted automatically.');
    }
};
