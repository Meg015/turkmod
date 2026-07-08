<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use PDO;
use Throwable;

final class ContactMailService
{
    /**
     * @return list<array{id:int,username:string,name:string,email:string}>
     */
    public function adminRecipients(PDO $pdo): array
    {
        try {
            if (function_exists('usersEnsureGroupSchema')) {
                usersEnsureGroupSchema($pdo);
            }

            $stmt = $pdo->query("
                SELECT DISTINCT u.id, u.username, u.email
                FROM users u
                INNER JOIN user_group_members ugm ON ugm.user_id = u.id
                INNER JOIN user_groups g ON g.id = ugm.group_id AND g.is_active = 1
                LEFT JOIN user_group_permissions p ON p.group_id = g.id AND p.permission_value = 1 AND p.permission_key IN ('*', 'admin.access')
                WHERE u.email IS NOT NULL
                  AND u.email <> ''
                  AND u.status = 'active'
                  AND (u.is_banned = 0 OR u.is_banned IS NULL)
                  AND (g.slug = 'admin' OR p.permission_key IS NOT NULL)
                ORDER BY u.username ASC, u.id ASC
            ");

            $recipients = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $email = trim((string) ($row['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $recipients[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'username' => trim((string) ($row['username'] ?? '')),
                    'name' => trim((string) ($row['username'] ?? '')),
                    'email' => $email,
                ];
            }

            return $recipients;
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     * @return array{success:bool,sent_count:int,total_count:int,error:string}
     */
    public function sendAdminNotification(PDO $pdo, array $message, array $category): array
    {
        $recipients = $this->adminRecipients($pdo);
        if ($recipients === []) {
            return [
                'success' => false,
                'sent_count' => 0,
                'total_count' => 0,
                'error' => 'Yonetici e-posta alicisi bulunamadi.',
            ];
        }

        $subject = $this->adminSubject($category, $message);
        $body = $this->adminBody($category, $message);
        $sent = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            $ok = appSendMail(
                (string) $recipient['email'],
                $subject,
                $body,
                [
                    'reply_to' => (string) ($message['sender_email'] ?? ''),
                ],
            );

            if ($ok) {
                $sent++;
            } else {
                $errors[] = (string) $recipient['email'];
            }
        }

        $total = count($recipients);
        $error = '';
        if ($sent <= 0) {
            $error = 'Yonetici e-postasi gonderilemedi.';
        } elseif ($sent < $total) {
            $failedPreview = implode(', ', array_slice($errors, 0, 3));
            $error = sprintf(
                'Kismi bildirim: %d/%d adrese gonderildi.%s',
                $sent,
                $total,
                $failedPreview !== '' ? ' Basarisiz: ' . $failedPreview : ''
            );
        }

        return [
            'success' => $sent > 0,
            'sent_count' => $sent,
            'total_count' => $total,
            'error' => $error,
        ];
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     * @return array{success:bool,error:string}
     */
    public function sendReply(PDO $pdo, array $message, array $category, string $replyBody, string $adminName = 'Yonetim'): array
    {
        $senderEmail = trim((string) ($message['sender_email'] ?? ''));
        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Gonderici e-posta adresi gecersiz.',
            ];
        }

        $subject = $this->replySubject($message, $category);
        $body = $this->replyBody($message, $category, $replyBody, $adminName);
        $ok = appSendMail($senderEmail, $subject, $body);

        return [
            'success' => $ok,
            'error' => $ok ? '' : 'Yanit e-postasi gonderilemedi.',
        ];
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     */
    private function adminSubject(array $category, array $message): string
    {
        $categoryName = trim((string) ($category['name'] ?? $message['category_name_snapshot'] ?? 'Iletisim'));
        $subject = trim((string) ($message['subject'] ?? ''));

        return 'Yeni Iletisim Mesaji: ' . $categoryName . ($subject !== '' ? ' - ' . $subject : '');
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     */
    private function replySubject(array $message, array $category): string
    {
        $subject = trim((string) ($message['subject'] ?? ''));

        return $subject !== '' ? 'Re: ' . $subject : 'Re: Iletisim Mesaji';
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     */
    private function adminBody(array $category, array $message): string
    {
        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $adminLink = $baseUri !== '' ? $baseUri . '/admin/contacts.php?tab=messages&message_id=' . (int) ($message['id'] ?? 0) : '';
        $categoryName = htmlspecialchars((string) ($category['name'] ?? $message['category_name_snapshot'] ?? 'Iletisim'), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars((string) ($message['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string) ($message['sender_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($message['sender_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $bodyText = nl2br(htmlspecialchars((string) ($message['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $adminLinkHtml = $adminLink !== '' ? '<p><a href="' . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . '">Mesaji admin panelinde ac</a></p>' : '';

        return <<<HTML
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Iletisim Mesaji</title>
</head>
<body style="font-family:Roboto,sans-serif;background:#f8fafc;margin:0;padding:24px;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 16px;">Yeni Iletisim Mesaji</h2>
        <p><strong>Kategori:</strong> {$categoryName}</p>
        <p><strong>Konu:</strong> {$subject}</p>
        <p><strong>Gonderen:</strong> {$name} ({$email})</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">
        <div style="white-space:normal;line-height:1.6;">{$bodyText}</div>
        {$adminLinkHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * @param array<string,mixed> $message
     * @param array<string,mixed> $category
     */
    private function replyBody(array $message, array $category, string $replyBody, string $adminName): string
    {
        $siteName = (string) (($GLOBALS['envConfig']['APP_NAME'] ?? '') ?: ($GLOBALS['_lay']['header_brand_text'] ?? 'Site'));
        $categoryName = htmlspecialchars((string) ($category['name'] ?? $message['category_name_snapshot'] ?? 'Iletisim'), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars((string) ($message['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $replyHtml = nl2br(htmlspecialchars($replyBody, ENT_QUOTES, 'UTF-8'));
        $senderName = htmlspecialchars((string) ($message['sender_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $senderEmail = htmlspecialchars((string) ($message['sender_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $adminName = htmlspecialchars($adminName !== '' ? $adminName : 'Yonetim', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Iletisim Yaniti</title>
</head>
<body style="font-family:Roboto,sans-serif;background:#f8fafc;margin:0;padding:24px;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 16px;">Iletisim Yaniti</h2>
        <p>Merhaba <strong>{$senderName}</strong>,</p>
        <p><strong>Kategori:</strong> {$categoryName}</p>
        <p><strong>Konu:</strong> {$subject}</p>
        <div style="margin:20px 0;padding:16px;border-left:4px solid #8b1538;background:#f8fafc;line-height:1.7;">
            {$replyHtml}
        </div>
        <p style="color:#64748b;">Bu yanit {$siteName} tarafindan gonderildi.</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;">
        <p style="font-size:0.92rem;color:#475569;"><strong>Orijinal gonderici:</strong> {$senderName} ({$senderEmail})</p>
        <p style="font-size:0.92rem;color:#475569;"><strong>Yaniti hazirlayan:</strong> {$adminName}</p>
    </div>
</body>
</html>
HTML;
    }
}
