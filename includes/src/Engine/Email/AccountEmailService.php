<?php

declare(strict_types=1);

namespace App\Engine\Email;

use PDO;
use Throwable;

final class AccountEmailService
{
    public const TEMPLATE_KEYS = [
        'welcome',
        'verification_request',
        'verification_completed',
        'password_reset_request',
        'password_reset_completed',
        'password_changed',
        'email_changed',
    ];

    public static function catalog(): array
    {
        $paragraph = static fn (string $text): string => '<p style="margin:0 0 14px;color:#344054;font-size:15px;line-height:1.65;">' . $text . '</p>';
        $wrap = static function (string $title, string $content, string $actionLabel = '', string $actionUrl = ''): string {
            if (function_exists('appRenderMailLayout')) {
                return appRenderMailLayout([
                    'site_name' => '{{site_name}}',
                    'eyebrow' => 'Hesap bildirimi',
                    'title' => $title,
                    'content_html' => $content,
                    'action_label' => $actionLabel,
                    'action_url' => $actionUrl,
                    'footer_note' => 'Bu e-posta hesabınızla ilgili otomatik bir bilgilendirmedir.',
                    'editable' => true,
                ]);
            }

            $button = $actionLabel !== '' && $actionUrl !== ''
                ? '<p style="margin:24px 0;text-align:center"><a href="' . $actionUrl . '" style="display:inline-block;background:#8b1538;color:#fff;padding:12px 22px;border-radius:7px;text-decoration:none;font-weight:700">' . $actionLabel . '</a></p>'
                : '';

            return '<!doctype html><html lang="tr"><head><meta charset="UTF-8"></head><body style="margin:0;background:#f3f5f8;font-family:Segoe UI,Roboto,sans-serif;color:#172033"><div style="max-width:640px;margin:0 auto;padding:34px 16px"><div data-account-email-editable="1" style="background:#fff;border:1px solid #e4e8ef;border-radius:14px;padding:30px;box-shadow:0 18px 38px rgba(16,24,40,.08)"><div style="margin:0 0 10px;color:#8b1538;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase">Hesap bildirimi</div><h1 style="font-size:23px;line-height:1.28;margin:0 0 18px;color:#101828">' . $title . '</h1>' . $content . $button . '<p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #eaecf0;color:#98a2b3;font-size:12px">Bu e-posta otomatik olarak gönderilmiştir.</p></div></div></body></html>';
        };

        return [
            'welcome' => [
                'label' => 'Yeni Üyeye Hoş Geldin',
                'description' => 'Normal ve popup kayıt tamamlandığında gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} ailesine hoş geldiniz',
                'body' => $wrap('Hoş geldiniz, {{username}}!', $paragraph('Hesabınız başarıyla oluşturuldu. Topluluğa katılmaya hazırsınız.'), 'Giriş Yap', '{{login_url}}'),
            ],
            'verification_request' => [
                'label' => 'E-posta Doğrulama Bağlantısı',
                'description' => 'Doğrulama sistemi açıkken kayıt ve yeniden gönderim işlemlerinde kullanılır.',
                'enabled' => '1',
                'subject' => '{{site_name}} e-posta adresinizi doğrulayın',
                'body' => $wrap('E-posta adresinizi doğrulayın', $paragraph('Merhaba {{username}}, hesabınızdaki e-posta adresini doğrulamak için aşağıdaki bağlantıyı kullanın.') . $paragraph('Bağlantı {{expires_minutes}} dakika geçerlidir.'), 'E-postamı Doğrula', '{{action_url}}'),
            ],
            'verification_completed' => [
                'label' => 'E-posta Doğrulandı',
                'description' => 'Doğrulama işlemi başarıyla tamamlandığında gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} e-posta adresiniz doğrulandı',
                'body' => $wrap('E-posta adresiniz doğrulandı', $paragraph('Merhaba {{username}}, hesabınızın e-posta doğrulaması başarıyla tamamlandı.'), 'Profilime Git', '{{profile_url}}'),
            ],
            'password_reset_request' => [
                'label' => 'Şifre Sıfırlama Bağlantısı',
                'description' => 'Şifremi unuttum formundan güvenli sıfırlama bağlantısı gönderir.',
                'enabled' => '1',
                'subject' => '{{site_name}} şifre sıfırlama talebi',
                'body' => $wrap('Şifrenizi sıfırlayın', $paragraph('Merhaba {{username}}, hesabınız için şifre sıfırlama talebi aldık.') . $paragraph('Bağlantı {{expires_minutes}} dakika geçerlidir. Bu talep size ait değilse e-postayı yok sayabilirsiniz.'), 'Şifremi Sıfırla', '{{action_url}}'),
            ],
            'password_reset_completed' => [
                'label' => 'Şifre Sıfırlama Tamamlandı',
                'description' => 'Sıfırlama bağlantısıyla yeni şifre belirlendiğinde gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} şifreniz değiştirildi',
                'body' => $wrap('Şifreniz başarıyla değiştirildi', $paragraph('Merhaba {{username}}, şifre sıfırlama işleminiz {{date_time}} tarihinde tamamlandı.') . $paragraph('Bu işlem size ait değilse hemen yeniden şifre sıfırlama talebi oluşturun.'), 'Hesabıma Git', '{{login_url}}'),
            ],
            'password_changed' => [
                'label' => 'Şifre Değiştirildi Güvenlik Bildirimi',
                'description' => 'Profil veya yönetici işlemiyle şifre değiştiğinde gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} hesap güvenliği bildirimi',
                'body' => $wrap('Hesap şifreniz değiştirildi', $paragraph('Merhaba {{username}}, hesabınızın şifresi {{date_time}} tarihinde değiştirildi.') . $paragraph('İşlem kaynağı: {{actor_context}}') . $paragraph('Bu işlem size ait değilse şifrenizi hemen sıfırlayın.'), 'Şifremi Sıfırla', '{{action_url}}'),
            ],
            'email_changed' => [
                'label' => 'E-posta Adresi Değiştirildi',
                'description' => 'Yönetici hesap e-posta adresini değiştirdiğinde eski ve yeni adrese gönderilir.',
                'enabled' => '1',
                'subject' => '{{site_name}} e-posta adresiniz değiştirildi',
                'body' => $wrap('E-posta adresiniz değiştirildi', $paragraph('Merhaba {{username}}, hesabınızdaki e-posta adresi değiştirildi.') . (function_exists('appMailDetailTableHtml') ? appMailDetailTableHtml(['Eski adres' => '{{old_email}}', 'Yeni adres' => '{{new_email}}']) : $paragraph('Eski adres: {{old_email}}<br>Yeni adres: {{new_email}}')) . $paragraph('Bu değişiklik size ait değilse site yönetimiyle iletişime geçin.')),
            ],
        ];
    }

    public static function allowedVariables(): array
    {
        return ['site_name', 'username', 'recipient_email', 'action_url', 'login_url', 'profile_url', 'expires_minutes', 'old_email', 'new_email', 'actor_context', 'ip_address', 'date_time', 'support_email'];
    }

    /** @return array<string,list<string>> */
    public static function requiredVariables(): array
    {
        return [
            'welcome' => ['site_name', 'username', 'login_url'],
            'verification_request' => ['site_name', 'username', 'action_url', 'expires_minutes'],
            'verification_completed' => ['site_name', 'username', 'profile_url'],
            'password_reset_request' => ['site_name', 'username', 'action_url', 'expires_minutes'],
            'password_reset_completed' => ['site_name', 'username', 'login_url', 'date_time'],
            'password_changed' => ['site_name', 'username', 'date_time', 'actor_context', 'action_url'],
            'email_changed' => ['site_name', 'username', 'old_email', 'new_email'],
        ];
    }

    public static function settingKey(string $templateKey, string $field): string
    {
        return 'account_email_' . $templateKey . '_' . $field;
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
        foreach (['enabled', 'subject', 'body'] as $field) {
            $default[$field] = (string) ($settings[self::settingKey($key, $field)] ?? $default[$field]);
        }
        return $default;
    }

    public function render(string $value, array $variables): string
    {
        $safe = [];
        foreach (self::allowedVariables() as $name) {
            $raw = (string) ($variables[$name] ?? '');
            $safe['{{' . $name . '}}'] = in_array($name, ['action_url', 'login_url', 'profile_url'], true)
                ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8')
                : htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        }
        return strtr($value, $safe);
    }

    /**
     * @param array<string,string> $variables
     * @return array{label:string,url:string}
     */
    private function actionForTemplate(string $templateKey, array $variables): array
    {
        return match ($templateKey) {
            'welcome' => ['label' => 'Giriş Yap', 'url' => (string) ($variables['login_url'] ?? '')],
            'verification_request' => ['label' => 'E-postamı Doğrula', 'url' => (string) ($variables['action_url'] ?? '')],
            'verification_completed' => ['label' => 'Profilime Git', 'url' => (string) ($variables['profile_url'] ?? '')],
            'password_reset_request', 'password_changed' => ['label' => 'Şifremi Sıfırla', 'url' => (string) ($variables['action_url'] ?? '')],
            'password_reset_completed' => ['label' => 'Hesabıma Git', 'url' => (string) ($variables['login_url'] ?? '')],
            default => ['label' => '', 'url' => ''],
        };
    }

    private function normalizeBody(string $templateKey, string $subject, string $body, array $variables): string
    {
        if (!function_exists('appRenderMailLayout') || (function_exists('appMailIsStandardLayout') && appMailIsStandardLayout($body))) {
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

        $action = $this->actionForTemplate($templateKey, $variables);

        return appRenderMailLayout([
            'site_name' => (string) ($variables['site_name'] ?? 'Türk Mod'),
            'eyebrow' => 'Hesap bildirimi',
            'title' => trim(strip_tags($subject)) !== '' ? trim(strip_tags($subject)) : 'Hesap bildirimi',
            'content_html' => $contentHtml,
            'action_label' => $action['label'],
            'action_url' => $action['url'],
            'footer_note' => 'Bu e-posta hesabınızla ilgili otomatik bir bilgilendirmedir.',
        ]);
    }

    private function absoluteUrl(string $candidate, string $publicBase): string
    {
        $candidate = trim($candidate);
        if ($candidate === '' || preg_match('~^https?://~i', $candidate)) {
            return $candidate;
        }
        if (str_starts_with($candidate, '/')) {
            $parts = parse_url($publicBase);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . $candidate;
            }
        }
        return rtrim($publicBase, '/') . '/' . ltrim($candidate, '/');
    }

    public function send(string $templateKey, string $to, array $variables = [], array $overrides = []): bool
    {
        $settings = $this->settings();
        if (($settings['account_email_system_enabled'] ?? '1') !== '1' && empty($overrides['force'])) {
            return false;
        }
        $template = $this->template($templateKey, $settings);
        if ($template === [] || (($overrides['enabled'] ?? $template['enabled']) !== '1')) {
            return false;
        }
        $variables = array_merge($this->baseVariables($to, $settings), $variables);
        $publicBase = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        foreach (['action_url', 'login_url', 'profile_url'] as $urlVariable) {
            $candidate = trim((string) ($variables[$urlVariable] ?? ''));
            $variables[$urlVariable] = $this->absoluteUrl($candidate, $publicBase);
        }
        $subject = $this->render((string) ($overrides['subject'] ?? $template['subject']), $variables);
        $body = $this->render((string) ($overrides['body'] ?? $template['body']), $variables);
        $body = $this->normalizeBody($templateKey, $subject, $body, $variables);
        return function_exists('appSendMail') && appSendMail($to, strip_tags($subject), $body, [
            'settings' => isset($overrides['settings']) && is_array($overrides['settings']) ? $overrides['settings'] : [],
            'email_log' => [
                'source' => 'account',
                'source_key' => $templateKey,
                'recipient_name' => (string) ($variables['username'] ?? ''),
            ],
        ]);
    }

    public function baseVariables(string $to, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        $base = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        $loginUrl = function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : '/giris';
        $loginUrl = $this->absoluteUrl($loginUrl, $base);
        return [
            'site_name' => (string) ($settings['site_name'] ?? 'Türk Mod'),
            'recipient_email' => $to,
            'login_url' => $loginUrl,
            'profile_url' => $base . '/profil',
            'support_email' => (string) ($settings['mail_from_address'] ?? ''),
            'ip_address' => function_exists('getRealIp') ? (string) getRealIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'date_time' => date('d.m.Y H:i'),
        ];
    }

    public function issueVerification(int $userId, string $email, string $username): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $settings = $this->settings();
        if (($settings['account_email_verification_enabled'] ?? '0') !== '1') {
            return false;
        }
        $minutes = max(15, min(10080, (int) ($settings['account_email_verification_ttl_minutes'] ?? 1440)));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $stmt = $this->pdo->prepare('UPDATE users SET email_verification_token = ?, email_verification_expires_at = ?, email_verification_sent_at = NOW(), email_verified_at = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$hash, $expires, $userId]);
        $base = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        $url = $base . '/verify-email.php?token=' . urlencode($token) . '&email=' . urlencode($email);
        return $this->send('verification_request', $email, ['username' => $username, 'action_url' => $url, 'expires_minutes' => (string) $minutes]);
    }

    public function verify(string $email, string $rawToken): ?array
    {
        if (!$this->pdo || $email === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, username, email FROM users WHERE email = ? AND email_verification_token = ? AND email_verification_expires_at >= NOW() LIMIT 1');
        $stmt->execute([$email, hash('sha256', $rawToken)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        $update = $this->pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL, updated_at = NOW() WHERE id = ? AND email_verification_token = ?');
        $update->execute([(int) $user['id'], hash('sha256', $rawToken)]);
        if ($update->rowCount() !== 1) {
            return null;
        }
        try {
            if (function_exists('logActivity')) {
                logActivity($this->pdo, 'email_verified', 'user', (int) $user['id']);
            }
            $this->send('verification_completed', (string) $user['email'], ['username' => (string) $user['username']]);
        } catch (Throwable) {
        }
        return $user;
    }
}
