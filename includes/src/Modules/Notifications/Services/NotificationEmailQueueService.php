<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationEmailQueueService
{
    public function __construct(private ?NotificationSchemaService $schema = null)
    {
        $this->schema ??= new NotificationSchemaService();
    }

    /** @return array{total:int,queued:int,processing:int,sent:int,failed:int} */
    public function stats(PDO $pdo): array
    {
        $this->schema->ensureEmailQueueSchema($pdo);

        $stats = [
            'total' => 0,
            'queued' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        try {
            $rows = $pdo->query('SELECT status, COUNT(*) AS total_count FROM notification_email_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $count = (int) ($row['total_count'] ?? 0);
                $stats['total'] += $count;
                if (array_key_exists($status, $stats)) {
                    $stats[$status] = $count;
                }
            }
        } catch (Throwable $e) {
            error_log('Notification email queue stats failed: ' . $e->getMessage());
        }

        return $stats;
    }

    /** @return array{id:int,username:string,name:string,email:string,status:string}|null */
    public function recipient(PDO $pdo, int $userId): ?array
    {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email, status FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return null;
            }

            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return [
                'id' => (int) $user['id'],
                'username' => (string) ($user['username'] ?? ''),
                'name' => (string) ($user['username'] ?? ''),
                'email' => $email,
                'status' => (string) ($user['status'] ?? ''),
            ];
        } catch (Throwable $e) {
            error_log('Notification email recipient lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    public function queue(
        PDO $pdo,
        int $notificationId,
        int $recipientId,
        string $templateKey,
        string $subject,
        string $body,
        ?string $link,
        array $metadata,
        int $maxAttempts = 3
    ): bool {
        if ($notificationId <= 0 || $recipientId <= 0 || trim($subject) === '' || trim($body) === '') {
            return false;
        }

        $recipient = $this->recipient($pdo, $recipientId);
        if (!$recipient) {
            return false;
        }

        $this->schema->ensureEmailQueueSchema($pdo);
        $maxAttempts = max(1, min(10, $maxAttempts));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO notification_email_queue
                    (notification_id, user_id, recipient_email, recipient_name, template_key, subject, body, link, status, attempts, max_attempts, metadata_json, available_at, created_at, updated_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, 'queued', 0, ?, ?, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    recipient_email = VALUES(recipient_email),
                    recipient_name = VALUES(recipient_name),
                    template_key = VALUES(template_key),
                    subject = VALUES(subject),
                    body = VALUES(body),
                    link = VALUES(link),
                    status = IF(status = 'sent', status, 'queued'),
                    max_attempts = VALUES(max_attempts),
                    metadata_json = VALUES(metadata_json),
                    available_at = IF(status = 'sent', available_at, NOW()),
                    updated_at = NOW()
            ");

            return $stmt->execute([
                $notificationId,
                $recipientId,
                $recipient['email'],
                $recipient['name'],
                $templateKey !== '' ? $templateKey : null,
                mb_substr($subject, 0, 255),
                $body,
                $link,
                $maxAttempts,
                json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            error_log('Notification email queue insert failed: ' . $e->getMessage());

            return false;
        }
    }

    public function absoluteLink(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $link)) {
            return $link;
        }

        $baseUrl = function_exists('appPublicBaseUrl')
            ? rtrim(appPublicBaseUrl(true, null, $GLOBALS['envConfig'] ?? []), '/')
            : '';
        if ($baseUrl === '') {
            return $link;
        }

        $baseParts = parse_url($baseUrl);
        if (!is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return $baseUrl . '/' . ltrim($link, '/');
        }

        $origin = $baseParts['scheme'] . '://' . $baseParts['host'];
        if (!empty($baseParts['port'])) {
            $origin .= ':' . $baseParts['port'];
        }

        $basePath = rtrim((string) ($baseParts['path'] ?? ''), '/');
        $linkParts = parse_url($link);
        $linkPath = is_array($linkParts) ? (string) ($linkParts['path'] ?? '') : '';
        $linkSuffix = '';
        if (is_array($linkParts)) {
            if (isset($linkParts['query']) && $linkParts['query'] !== '') {
                $linkSuffix .= '?' . $linkParts['query'];
            }
            if (isset($linkParts['fragment']) && $linkParts['fragment'] !== '') {
                $linkSuffix .= '#' . $linkParts['fragment'];
            }
        }

        if ($basePath !== '' && $basePath !== '/' && $linkPath !== '' && str_starts_with($linkPath, $basePath . '/')) {
            return rtrim($origin, '/') . $linkPath . $linkSuffix;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($link, '/');
    }

    public function buildHtml(array $row): string
    {
        $subject = htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $body = nl2br(htmlspecialchars((string) ($row['body'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $link = $this->absoluteLink(isset($row['link']) ? (string) $row['link'] : null);
        $linkHtml = '';
        if ($link !== null) {
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $linkHtml = '<p style="text-align:center;margin:24px 0;"><a href="' . $safeLink . '" style="display:inline-block;background:#ef3f6b;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700;">Bildirimi Ac</a></p>';
        }

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;background:#f6f8fb;font-family:Roboto,sans-serif;color:#1f2937;">'
            . '<div style="max-width:560px;margin:0 auto;padding:32px 18px;">'
            . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;box-shadow:0 12px 28px rgba(15,23,42,.08);">'
            . '<h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#111827;">' . $subject . '</h1>'
            . '<div style="font-size:15px;line-height:1.7;color:#374151;">' . $body . '</div>'
            . $linkHtml
            . '<p style="margin:24px 0 0;color:#9ca3af;font-size:12px;">Bu e-posta Bildirim Merkezi tarafindan otomatik hazirlanmistir.</p>'
            . '</div></div></body></html>';
    }

    /**
     * @param callable(array<string,mixed>):bool|null $sender
     * @return array{selected:int,sent:int,failed:int,requeued:int,dry_run:int,errors:list<string>}
     */
    public function process(PDO $pdo, int $limit = 25, bool $dryRun = false, ?callable $sender = null): array
    {
        $this->schema->ensureEmailQueueSchema($pdo);

        $limit = max(1, min(100, $limit));
        $result = [
            'selected' => 0,
            'sent' => 0,
            'failed' => 0,
            'requeued' => 0,
            'dry_run' => 0,
            'errors' => [],
        ];

        try {
            $pdo->exec("UPDATE notification_email_queue
                SET status = 'queued', locked_at = NULL, updated_at = NOW()
                WHERE status = 'processing'
                  AND locked_at IS NOT NULL
                  AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        } catch (Throwable $e) {
            error_log('Notification email stale lock reset failed: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM notification_email_queue
                WHERE status = 'queued'
                  AND attempts < max_attempts
                  AND (available_at IS NULL OR available_at <= NOW())
                ORDER BY id ASC
                LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();

            return $result;
        }

        $result['selected'] = count($rows);
        $sender = $sender ?: function (array $row): bool {
            if (!function_exists('appSendMail')) {
                return false;
            }

            return appSendMail(
                (string) $row['recipient_email'],
                (string) $row['subject'],
                $this->buildHtml($row),
                [
                    'email_log' => [
                        'source' => 'notifications',
                        'source_key' => trim((string) ($row['template_key'] ?? 'notification_queue')) !== ''
                            ? trim((string) ($row['template_key'] ?? 'notification_queue'))
                            : 'notification_queue',
                        'recipient_name' => (string) ($row['recipient_name'] ?? ''),
                        'notification_id' => (int) ($row['notification_id'] ?? 0),
                        'queue_id' => (int) ($row['id'] ?? 0),
                        'user_id' => (int) ($row['user_id'] ?? 0),
                        'attempt_no' => ((int) ($row['attempts'] ?? 0)) + 1,
                        'max_attempts' => (int) ($row['max_attempts'] ?? 3),
                    ],
                ]
            );
        };

        foreach ($rows as $row) {
            $queueId = (int) ($row['id'] ?? 0);
            if ($queueId <= 0) {
                continue;
            }

            if ($dryRun) {
                $result['dry_run']++;
                continue;
            }

            $this->processRow($pdo, $row, $queueId, $sender, $result);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param callable(array<string,mixed>):bool $sender
     * @param array{selected:int,sent:int,failed:int,requeued:int,dry_run:int,errors:list<string>} $result
     */
    private function processRow(PDO $pdo, array $row, int $queueId, callable $sender, array &$result): void
    {
        try {
            $lock = $pdo->prepare("UPDATE notification_email_queue
                SET status = 'processing', locked_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'queued'");
            $lock->execute([$queueId]);
            if ($lock->rowCount() !== 1) {
                return;
            }

            if (function_exists('appSetLastMailResult')) {
                appSetLastMailResult([]);
            }

            $ok = (bool) $sender($row);
            $nextAttempts = (int) ($row['attempts'] ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? 3));

            if ($ok) {
                $update = $pdo->prepare("UPDATE notification_email_queue
                    SET status = 'sent', attempts = ?, locked_at = NULL, sent_at = NOW(), error_message = NULL, updated_at = NOW()
                    WHERE id = ?");
                $update->execute([$nextAttempts, $queueId]);
                $result['sent']++;

                return;
            }

            $this->markAttemptResult($pdo, $queueId, $nextAttempts, $maxAttempts, $this->buildMailFailureMessage(), $result);
        } catch (Throwable $e) {
            $nextAttempts = (int) ($row['attempts'] ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? 3));
            $this->markAttemptResult($pdo, $queueId, $nextAttempts, $maxAttempts, $e->getMessage(), $result);
            $result['errors'][] = $e->getMessage();
        }
    }

    /**
     * @param array{selected:int,sent:int,failed:int,requeued:int,dry_run:int,errors:list<string>} $result
     */
    private function markAttemptResult(PDO $pdo, int $queueId, int $nextAttempts, int $maxAttempts, string $error, array &$result): void
    {
        $status = $nextAttempts >= $maxAttempts ? 'failed' : 'queued';
        $delayMinutes = $status === 'queued' ? min(60, 5 * $nextAttempts) : 0;
        $availableAt = $delayMinutes > 0 ? date('Y-m-d H:i:s', time() + ($delayMinutes * 60)) : date('Y-m-d H:i:s');

        try {
            $update = $pdo->prepare("UPDATE notification_email_queue
                SET status = ?, attempts = ?, locked_at = NULL, error_message = ?, available_at = ?, updated_at = NOW()
                WHERE id = ?");
            $update->execute([$status, $nextAttempts, mb_substr($error, 0, 1000), $availableAt, $queueId]);
        } catch (Throwable $inner) {
            error_log('Notification email queue status update failed: ' . $inner->getMessage());
        }

        if ($status === 'failed') {
            $result['failed']++;
        } else {
            $result['requeued']++;
        }
    }

    private function buildMailFailureMessage(): string
    {
        if (!function_exists('appLastMailResult')) {
            return 'Mail driver returned false.';
        }

        $mailResult = appLastMailResult();
        foreach (['error', 'smtp_response', 'response'] as $key) {
            $message = trim((string) ($mailResult[$key] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        $driver = trim((string) ($mailResult['driver'] ?? ''));
        $transport = trim((string) ($mailResult['transport'] ?? ''));
        if ($driver !== '' || $transport !== '') {
            return sprintf(
                'Mail driver returned false (%s/%s).',
                $driver !== '' ? $driver : 'unknown',
                $transport !== '' ? $transport : 'unknown'
            );
        }

        return 'Mail driver returned false.';
    }
}
