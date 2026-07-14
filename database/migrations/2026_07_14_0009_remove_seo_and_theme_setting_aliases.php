<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0009_remove_seo_and_theme_setting_aliases';
    }

    public function up(PDO $pdo): void
    {
        $this->copyIfMissing($pdo, 'active_public_theme', 'theme_active_id', false);
        $this->copyIfMissing($pdo, 'robots_noindex_search', 'index_search_results', true);
        $this->copyIfMissing($pdo, 'noindex_empty_categories', 'index_empty_categories', true);
        $this->copyIfMissing($pdo, 'noindex_draft_topics', 'index_draft_topics', true);

        $keys = [
            'active_public_theme',
            'robots_noindex_search',
            'noindex_empty_categories',
            'noindex_draft_topics',
            'meta_description_length',
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("DELETE FROM admin_settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute($keys);
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Removed setting aliases are not restored automatically.');
    }

    private function copyIfMissing(PDO $pdo, string $source, string $target, bool $invertBoolean): void
    {
        $sql = 'INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
                SELECT ?, ' . ($invertBoolean
                    ? "CASE WHEN setting_value = '1' THEN '0' ELSE '1' END"
                    : 'setting_value') . ', NOW(), NOW()
                FROM admin_settings source
                WHERE source.setting_key = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM admin_settings target WHERE target.setting_key = ?
                  )';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target, $source, $target]);
    }
};
