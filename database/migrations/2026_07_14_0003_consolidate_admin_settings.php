<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const SCRAPER_KEYS = [
        'bot_allowed_image_extensions',
        'bot_append_source_link',
        'bot_auto_publish',
        'bot_bulk_concurrency',
        'bot_bulk_continue_on_error',
        'bot_bulk_default_selected',
        'bot_bulk_max_topics_per_page',
        'bot_clean_html',
        'bot_content_align',
        'bot_custom_headers',
        'bot_deepl_api_key',
        'bot_default_author_id',
        'bot_default_max_images',
        'bot_default_status',
        'bot_detect_author_enabled',
        'bot_detect_author_labels',
        'bot_detect_version_enabled',
        'bot_detect_version_pattern',
        'bot_discover_cover_lookup_limit',
        'bot_download_images',
        'bot_duplicate_strategy',
        'bot_extract_download_links',
        'bot_follow_redirects',
        'bot_image_filename_mode',
        'bot_image_save_path',
        'bot_log_level',
        'bot_min_content_length',
        'bot_min_title_length',
        'bot_proxy_url',
        'bot_publish_date_mode',
        'bot_request_delay',
        'bot_request_timeout',
        'bot_require_cover_image',
        'bot_retry_count',
        'bot_retry_delay',
        'bot_skip_duplicate_urls',
        'bot_source_lang',
        'bot_ssl_verify',
        'bot_strip_iframes',
        'bot_strip_scripts',
        'bot_target_lang',
        'bot_translate_content',
        'bot_translate_download_names',
        'bot_translate_enabled',
        'bot_translate_title',
        'bot_use_hotlink_images',
        'bot_user_agent',
    ];

    private const OBSOLETE_ROUTE_KEYS = [
        'route_old_url_redirect',
        'route_alias_redirects',
        'route_topic_aliases',
        'route_category_aliases',
        'route_profile_aliases',
        'route_redirect_to_canonical',
    ];

    public function name(): string
    {
        return '2026_07_14_0003_consolidate_admin_settings';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'settings')) {
            return;
        }
        if (!$this->tableExists($pdo, 'admin_settings')) {
            throw new RuntimeException('admin_settings tablosu bulunamadığı için eski ayarlar güvenle taşınamadı.');
        }

        $this->removeObsoleteRouteSettings($pdo);

        $legacyOnly = $pdo->query(
            'SELECT legacy.`key`, legacy.value
             FROM settings legacy
             LEFT JOIN admin_settings canonical ON canonical.setting_key = legacy.`key`
             WHERE canonical.setting_key IS NULL
             ORDER BY legacy.`key`'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $allowed = array_fill_keys(self::SCRAPER_KEYS, true);
        $insert = $pdo->prepare(
            'INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
             VALUES (:setting_key, :setting_value, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        foreach ($legacyOnly as $row) {
            if (!isset($allowed[(string) ($row['key'] ?? '')])) {
                continue;
            }
            $insert->execute([
                'setting_key' => (string) $row['key'],
                'setting_value' => $row['value'],
            ]);
        }

        $pdo->exec(
            'DELETE legacy
             FROM settings legacy
             INNER JOIN admin_settings canonical ON canonical.setting_key = legacy.`key`'
        );

        $pdo->exec('DROP TABLE `settings`');

        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Eski settings tablosu bilinçli olarak kaldırıldı; otomatik geri dönüş desteklenmiyor.');
    }

    private function removeObsoleteRouteSettings(PDO $pdo): void
    {
        $placeholders = implode(',', array_fill(0, count(self::OBSOLETE_ROUTE_KEYS), '?'));
        $stmt = $pdo->prepare(
            "DELETE FROM settings
             WHERE `key` IN ({$placeholders})
                OR `key` LIKE 'legacy_redirect_%'"
        );
        $stmt->execute(self::OBSOLETE_ROUTE_KEYS);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
