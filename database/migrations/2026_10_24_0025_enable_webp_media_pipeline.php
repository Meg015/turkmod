<?php
declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_10_24_0025_enable_webp_media_pipeline';
    }

    public function up(PDO $pdo): void
    {
        $settings = [
            'webp_enabled' => '1', 'webp_quality' => '82', 'webp_keep_original' => '0',
            'auto_resize_images' => '1', 'image_resize_width' => '1920', 'image_resize_height' => '1080',
            'image_strip_metadata' => '1', 'image_sharpen' => '1',
            'thumbnail_enabled' => '1', 'thumbnail_width' => '400', 'thumbnail_height' => '300', 'thumbnail_crop' => '0',
            'jpeg_quality' => '85', 'png_compression' => '6', 'allowed_image_ext' => 'png,jpg,jpeg,webp,gif',
            'watermark_enabled' => '0', 'watermark_text' => '', 'watermark_position' => 'bottom-right',
            'watermark_opacity' => '30', 'watermark_font_size' => '16',
            'bot_download_images' => '1', 'bot_use_hotlink_images' => '0',
            'bot_image_save_path' => 'uploads/konu/', 'bot_allowed_image_extensions' => 'jpg,jpeg,png,webp,gif',
        ];
        $statement = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:setting_key, :setting_value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        foreach ($settings as $key => $value) {
            $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
        }
        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        } elseif (class_exists(\App\Core\AppSettings::class)) {
            \App\Core\AppSettings::instance($pdo)->invalidate();
        }
    }

    public function down(PDO $pdo): void
    {
        // Retain administrator preferences and existing media during rollback.
    }
};
