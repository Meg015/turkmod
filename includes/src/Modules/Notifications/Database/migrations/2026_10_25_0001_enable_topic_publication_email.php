<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Notifications\Services\NotificationTemplateService;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_10_25_0001_enable_topic_publication_email';
    }

    public function up(PDO $pdo): void
    {
        $templates = new NotificationTemplateService();
        $templates->ensureSchema($pdo);
        // Keep administrator-edited copy; enable only the requested topic emails.
        $pdo->exec("UPDATE notification_templates SET email_enabled = 1, updated_at = NOW()
            WHERE template_key IN ('topic_approved', 'topic_published')");
        $pdo->exec("INSERT INTO admin_settings (setting_key, setting_value)
            VALUES ('notif_email_channel_ready', '1')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    }

    public function down(PDO $pdo): void
    {
        // Preserve administrator copy, delivery history and subsequent channel preferences.
    }
};
