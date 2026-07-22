<?php

declare(strict_types=1);

namespace App\Engine\Email;

use PDO;

final class AdminEmailService
{
    public const TEMPLATE_KEYS = [
        'registration_admin_notice',
        'registration_suspicious_alert',
    ];

    /** @return array<string,array<string,mixed>> */
    public static function catalog(): array
    {
        return [
            'registration_admin_notice' => [
                'label' => 'Yeni Üye Kaydı',
                'description' => 'Yeni kullanıcı kaydı oluştuğunda yönetici hesaplarına gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} - Yeni kullanıcı kaydı',
                'body' => "{{username}} yeni hesap oluşturdu.\n\nKullanıcı adı: {{username}}\nE-posta: {{email}}\nKullanıcı ID: {{user_id}}\nDurum: {{user_status}}",
                'action_label' => 'Kullanıcıyı İncele',
            ],
            'registration_suspicious_alert' => [
                'label' => 'Şüpheli Kayıt Yoğunluğu',
                'description' => 'Kısa sürede olağan dışı kayıt yoğunluğu algılandığında yönetici hesaplarına gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} - Şüpheli kayıt yoğunluğu',
                'body' => "Son {{window_minutes}} dakikada {{total_count}} kayıt görüldü. En yoğun IP {{top_ip}} üzerinden {{top_ip_count}} kayıt geldi.\n\nZaman aralığı: {{window_minutes}} dakika\nEşik: {{threshold}} kayıt\nIP özeti: {{top_ip_summary}}",
                'action_label' => 'Kayıtları İncele',
            ],
        ];
    }

    /** @return array<string,string> */
    public static function allowedVariables(): array
    {
        return [
            'site_name' => 'Site adı',
            'username' => 'Yeni kullanıcının adı',
            'email' => 'Yeni kullanıcının e-posta adresi',
            'user_id' => 'Yeni kullanıcı ID değeri',
            'user_status' => 'Kullanıcının kayıt sonrası durumu',
            'approval_status' => 'Yönetici onayı bilgisi',
            'admin_link' => 'Yönetici panelindeki ilgili kayıt bağlantısı',
            'window_minutes' => 'Şüpheli kayıt izleme aralığı',
            'threshold' => 'Şüpheli kayıt eşiği',
            'total_count' => 'Aralıkta görülen toplam kayıt sayısı',
            'top_ip' => 'En yoğun kayıt gelen IP adresi',
            'top_ip_count' => 'En yoğun IP kayıt sayısı',
            'top_ip_summary' => 'İlk IP özetleri',
            'date_time' => 'Gönderim zamanı',
        ];
    }

    /** @return array<string,list<string>> */
    public static function requiredVariables(): array
    {
        return [
            'registration_admin_notice' => ['site_name', 'username', 'email', 'user_id', 'user_status'],
            'registration_suspicious_alert' => ['site_name', 'window_minutes', 'threshold', 'total_count', 'top_ip', 'top_ip_count'],
        ];
    }

    public static function settingKey(string $templateKey, string $field): string
    {
        return 'admin_email_' . $templateKey . '_' . $field;
    }

    public static function bodyForEditor(string $templateKey, string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return $body;
        }
        if (!preg_match('/<[a-z][a-z0-9:-]*(?:\s[^>]*)?>/i', $body)) {
            return $body;
        }

        $text = preg_replace('~<br\s*/?>~i', "\n", $body) ?? $body;
        $text = preg_replace('~</th>\s*<td\b[^>]*>~i', ': ', $text) ?? $text;
        $text = preg_replace('~</td>\s*</tr>~i', "\n", $text) ?? $text;
        $text = preg_replace('~</p>~i', "\n\n", $text) ?? $text;
        $text = preg_replace('~</(?:div|table|tbody|thead)>~i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $text) ?: [$text];
        $lines = array_map(static fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line), $lines);
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : (string) (self::catalog()[$templateKey]['body'] ?? '');
    }

    /** @return array<string,string> */
    public static function samplePayload(array $settings = []): array
    {
        $base = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        $adminLink = $base !== '' ? $base . '/admin/user-edit.php?id=1001' : '/admin/user-edit.php?id=1001';

        return [
            'site_name' => (string) ($settings['site_name'] ?? 'Türk Mod'),
            'username' => 'Test Kullanıcısı',
            'email' => 'test@example.com',
            'user_id' => '1001',
            'user_status' => 'Aktif',
            'approval_status' => 'Onay gerekmiyor',
            'admin_link' => $adminLink,
            'window_minutes' => '15',
            'threshold' => '3',
            'total_count' => '7',
            'top_ip' => '203.0.113.42',
            'top_ip_count' => '4',
            'top_ip_summary' => '203.0.113.42 (4), 198.51.100.12 (2), 192.0.2.8 (1)',
            'date_time' => date('d.m.Y H:i'),
        ];
    }

    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function settings(): array
    {
        return function_exists('getAdminSettings') ? (array) getAdminSettings($this->pdo) : [];
    }

    public function template(string $key, ?array $settings = null): array
    {
        $default = self::catalog()[$key] ?? [];
        if ($default === []) {
            return [];
        }

        $settings ??= $this->settings();
        foreach (['enabled', 'subject', 'body', 'action_label'] as $field) {
            $value = (string) ($settings[self::settingKey($key, $field)] ?? $default[$field]);
            $default[$field] = $field === 'body' ? self::bodyForEditor($key, $value) : $value;
        }

        return $default;
    }

    private function enabledValue(mixed $value, string $default = '1'): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) ($value ?? $default)));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function effectiveSettings(array $overrides = []): array
    {
        $settings = $this->settings();
        $overrideSettings = isset($overrides['settings']) && is_array($overrides['settings'])
            ? $overrides['settings']
            : [];

        return $overrideSettings !== [] ? array_replace($settings, $overrideSettings) : $settings;
    }

    public function render(string $value, array $variables, bool $escape = false): string
    {
        $safe = [];
        foreach (array_keys(self::allowedVariables()) as $name) {
            $raw = (string) ($variables[$name] ?? '');
            $safe['{{' . $name . '}}'] = $escape ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') : $raw;
        }

        return strtr($value, $safe);
    }

    /** @return array<string,string> */
    public function registrationVariables(
        int $newUserId,
        string $username,
        string $email,
        bool $requiresApproval,
        string $adminLink,
        ?array $settings = null
    ): array {
        $settings ??= $this->settings();
        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = 'Sistem';
        }
        $status = $requiresApproval ? 'Yönetici onayı bekliyor' : 'Aktif';

        return array_merge(self::samplePayload($settings), [
            'site_name' => $siteName,
            'username' => trim($username) !== '' ? trim($username) : 'Bir kullanıcı',
            'email' => $email,
            'user_id' => (string) $newUserId,
            'user_status' => $status,
            'approval_status' => $requiresApproval ? 'Yönetici onayı gerekli' : 'Onay gerekmiyor',
            'admin_link' => $adminLink,
            'date_time' => date('d.m.Y H:i'),
        ]);
    }

    /** @return array<string,string> */
    public function suspiciousRegistrationVariables(
        array $summary,
        int $windowMinutes,
        int $threshold,
        string $adminLink,
        ?array $settings = null
    ): array {
        $settings ??= $this->settings();
        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = 'Sistem';
        }

        $topIp = trim((string) ($summary['top_ip'] ?? ''));
        if ($topIp === '') {
            $topIp = 'bilinmiyor';
        }

        $topLines = [];
        foreach (array_slice((array) ($summary['rows'] ?? []), 0, 3) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowIp = trim((string) ($row['ip_address'] ?? ''));
            if ($rowIp === '') {
                continue;
            }
            $topLines[] = $rowIp . ' (' . (int) ($row['total'] ?? 0) . ')';
        }

        return array_merge(self::samplePayload($settings), [
            'site_name' => $siteName,
            'admin_link' => $adminLink,
            'window_minutes' => (string) $windowMinutes,
            'threshold' => (string) $threshold,
            'total_count' => (string) (int) ($summary['total'] ?? 0),
            'top_ip' => $topIp,
            'top_ip_count' => (string) (int) ($summary['top_ip_count'] ?? 0),
            'top_ip_summary' => $topLines !== [] ? implode(', ', $topLines) : 'Özet yok',
            'date_time' => date('d.m.Y H:i'),
        ]);
    }

    private function htmlBody(string $subject, string $body, string $actionLabel, string $actionUrl, array $variables): string
    {
        if (function_exists('appMailIsStandardLayout') && appMailIsStandardLayout($body)) {
            return $body;
        }

        if (function_exists('appMailIsHtmlDocument') && appMailIsHtmlDocument($body)) {
            $contentHtml = function_exists('appMailPlainTextHtml')
                ? appMailPlainTextHtml(trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
                : nl2br(htmlspecialchars(strip_tags($body), ENT_QUOTES, 'UTF-8'));
        } elseif (function_exists('appMailLooksLikeHtml') && appMailLooksLikeHtml($body)) {
            $contentHtml = $body;
        } else {
            $contentHtml = function_exists('appMailPlainTextHtml')
                ? appMailPlainTextHtml($body)
                : nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        }

        if (!function_exists('appRenderMailLayout')) {
            return $contentHtml;
        }

        return appRenderMailLayout([
            'site_name' => (string) ($variables['site_name'] ?? 'Sistem'),
            'eyebrow' => 'Yönetim bildirimi',
            'title' => trim(strip_tags($subject)) !== '' ? trim(strip_tags($subject)) : 'Yönetim bildirimi',
            'content_html' => $contentHtml,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'footer_note' => 'Bu e-posta yönetim bildirim sistemi tarafından otomatik gönderilmiştir.',
        ]);
    }

    public function sendTest(string $templateKey, string $to, array $variables = [], array $overrides = []): bool
    {
        $settings = $this->effectiveSettings($overrides);
        $template = $this->template($templateKey, $settings);
        if ($template === []) {
            $this->logNotificationDelivery('notification_delivery_skipped', [
                'source' => 'admin_email_test',
                'status' => 'skipped',
                'reason' => 'admin_email_template_missing',
                'template_key' => $templateKey,
                'recipient_type' => 'admin',
                'recipient_email' => $to,
                'delivery_channels' => ['email'],
            ], 'notice');
            return false;
        }

        foreach (['enabled', 'subject', 'body', 'action_label'] as $field) {
            if (array_key_exists($field, $overrides)) {
                $template[$field] = (string) $overrides[$field];
            }
        }

        if (!$this->enabledValue($template['enabled'] ?? null, '1') && empty($overrides['force'])) {
            $this->logNotificationDelivery('notification_delivery_skipped', [
                'source' => 'admin_email_test',
                'status' => 'skipped',
                'reason' => 'admin_email_template_disabled',
                'template_key' => $templateKey,
                'recipient_type' => 'admin',
                'recipient_email' => $to,
                'delivery_channels' => ['email'],
            ], 'notice');
            return false;
        }

        $variables = array_merge(self::samplePayload($settings), $variables);
        $subject = $this->render((string) $template['subject'], $variables);
        $body = $this->render((string) $template['body'], $variables);
        $actionLabel = $this->render((string) ($template['action_label'] ?? ''), $variables);
        $html = $this->htmlBody($subject, $body, $actionLabel, (string) ($variables['admin_link'] ?? ''), $variables);

        $sent = function_exists('appSendMail') && appSendMail($to, strip_tags($subject), $html, [
            'settings' => $settings,
            'email_log' => [
                'source' => 'admin_notification',
                'source_key' => $templateKey,
                'recipient_name' => $to,
            ],
        ]);

        $this->logNotificationDelivery($sent ? 'notification_email_sent' : 'notification_delivery_failed', [
            'source' => 'admin_email_test',
            'status' => $sent ? 'sent' : 'failed',
            'reason' => $sent ? '' : (function_exists('appSendMail') ? 'send_returned_false' : 'mail_helper_missing'),
            'error' => $sent ? '' : $this->mailFailureMessage(),
            'template_key' => $templateKey,
            'recipient_type' => 'admin',
            'recipient_email' => $to,
            'title' => strip_tags($subject),
            'message' => $html,
            'link' => (string) ($variables['admin_link'] ?? ''),
            'delivery_channels' => ['email'],
        ], $sent ? 'info' : 'error');

        return $sent;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logNotificationDelivery(string $message, array $context, string $level = 'info'): void
    {
        if ($this->pdo && \function_exists('notificationDeliveryLog')) {
            \notificationDeliveryLog($this->pdo, $message, $context, $level);
        }
    }

    private function mailFailureMessage(): string
    {
        if (!function_exists('appLastMailResult')) {
            return '';
        }

        $mailResult = appLastMailResult();
        foreach (['error', 'smtp_response', 'response'] as $key) {
            $message = trim((string) ($mailResult[$key] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return '';
    }
}
