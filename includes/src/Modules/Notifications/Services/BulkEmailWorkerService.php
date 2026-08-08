<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class BulkEmailWorkerService
{
    public function __construct(
        private ?BulkEmailCampaignService $campaigns = null,
        private ?BulkEmailContentService $content = null,
    ) {
        $this->content ??= new BulkEmailContentService();
        $this->campaigns ??= new BulkEmailCampaignService($this->content);
    }

    /** @return array{locked:bool,prepared:int,selected:int,sent:int,failed:int,requeued:int,campaign_id:int,errors:list<string>} */
    public function process(PDO $pdo, int $limit = 100, int $maxAttempts = 3, bool $dryRun = false, ?callable $sender = null): array
    {
        $this->campaigns->requireSchema($pdo);
        $limit = max(1, min(200, $limit));
        $maxAttempts = max(1, min(10, $maxAttempts));
        $result = [
            'locked' => false,
            'prepared' => 0,
            'selected' => 0,
            'sent' => 0,
            'failed' => 0,
            'requeued' => 0,
            'campaign_id' => 0,
            'errors' => [],
        ];

        $lockStmt = $pdo->query("SELECT GET_LOCK('bulk_email_campaign_worker', 0)");
        if (!$lockStmt || (int) $lockStmt->fetchColumn() !== 1) {
            $result['locked'] = true;
            return $result;
        }

        try {
            $this->recoverStaleLocks($pdo, $maxAttempts);

            $preparingId = $this->campaigns->nextPreparingCampaignId($pdo);
            if ($preparingId > 0) {
                $prepared = $this->campaigns->prepareRecipientChunk($pdo, $preparingId, 1000);
                $result['prepared'] = (int) $prepared['inserted'];
                $result['campaign_id'] = $preparingId;
                if (!$prepared['complete']) {
                    return $result;
                }
            }

            $campaign = $this->selectCampaign($pdo);
            if (!$campaign) {
                return $result;
            }
            $campaignId = (int) $campaign['id'];
            $result['campaign_id'] = $campaignId;
            $token = bin2hex(random_bytes(24));
            $rows = $this->claimBatch($pdo, $campaignId, $limit, $token);
            $result['selected'] = count($rows);
            if ($dryRun) {
                $this->releaseClaim($pdo, $token);
                return $result;
            }

            foreach ($rows as $row) {
                $recipientId = (int) $row['id'];
                $currentStatus = $this->campaignStatus($pdo, $campaignId);
                if ($currentStatus !== 'sending') {
                    $targetStatus = $currentStatus === 'cancelled' ? 'cancelled' : 'pending';
                    $stmt = $pdo->prepare('UPDATE bulk_email_recipients SET status = ?, lock_token = NULL, locked_at = NULL, available_at = NOW(), updated_at = NOW() WHERE id = ? AND lock_token = ?');
                    $stmt->execute([$targetStatus, $recipientId, $token]);
                    $result['requeued']++;
                    continue;
                }

                $ok = false;
                $error = '';
                try {
                    $payload = $this->content->recipientPayload($row);
                    if ($sender) {
                        $message = $this->content->render((string) $row['subject_template'], (string) $row['body_html_template'], $payload);
                        $ok = (bool) $sender($row, $message);
                    } else {
                        $ok = $this->content->send(
                            (string) $row['recipient_email'],
                            (string) $row['subject_template'],
                            (string) $row['body_html_template'],
                            $payload,
                            [
                                'source' => 'bulk_email',
                                'source_key' => 'campaign:' . $campaignId,
                                'queue_id' => $recipientId,
                                'user_id' => (int) ($row['user_id'] ?? 0),
                                'recipient_name' => (string) $row['recipient_username'],
                            ]
                        );
                    }
                    if (!$ok && function_exists('appLastMailResult')) {
                        $mailResult = appLastMailResult();
                        $error = trim((string) ($mailResult['error'] ?? $mailResult['smtp_response'] ?? ''));
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    if (function_exists('appLogException')) {
                        appLogException($e, ['source' => 'bulk_email_worker', 'campaign_id' => $campaignId, 'recipient_id' => $recipientId]);
                    }
                }

                $attempt = (int) $row['attempt_count'] + 1;
                if ($ok) {
                    $stmt = $pdo->prepare("UPDATE bulk_email_recipients
                        SET status = 'sent', attempt_count = ?, sent_at = NOW(), last_error = NULL,
                            lock_token = NULL, locked_at = NULL, updated_at = NOW()
                        WHERE id = ? AND status = 'processing' AND lock_token = ?");
                    $stmt->execute([$attempt, $recipientId, $token]);
                    $result['sent']++;
                    continue;
                }

                $error = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $error) ?? '');
                $error = mb_substr($error !== '' ? $error : 'E-posta servisi false sonucu dondurdu.', 0, 1000, 'UTF-8');
                $terminal = $attempt >= $maxAttempts;
                $availableAt = date('Y-m-d H:i:s', time() + $this->retryDelaySeconds($attempt));
                $stmt = $pdo->prepare("UPDATE bulk_email_recipients
                    SET status = ?, attempt_count = ?, available_at = ?, last_error = ?,
                        lock_token = NULL, locked_at = NULL, updated_at = NOW()
                    WHERE id = ? AND status = 'processing' AND lock_token = ?");
                $stmt->execute([$terminal ? 'failed' : 'pending', $attempt, $availableAt, $error, $recipientId, $token]);
                if ($terminal) {
                    $result['failed']++;
                    $result['errors'][] = '#' . $recipientId . ': ' . $error;
                } else {
                    $result['requeued']++;
                }
            }

            $this->campaigns->reconcile($pdo, $campaignId);
            return $result;
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK('bulk_email_campaign_worker')");
            } catch (Throwable) {
            }
        }
    }

    private function recoverStaleLocks(PDO $pdo, int $maxAttempts): void
    {
        $stmt = $pdo->prepare("UPDATE bulk_email_recipients r
            INNER JOIN bulk_email_campaigns c ON c.id = r.campaign_id
            SET r.status = CASE
                    WHEN c.status = 'cancelled' THEN 'cancelled'
                    WHEN r.attempt_count + 1 >= ? THEN 'failed'
                    ELSE 'pending'
                END,
                r.attempt_count = r.attempt_count + 1,
                r.last_error = CASE
                    WHEN c.status = 'cancelled' THEN r.last_error
                    ELSE 'Worker zaman asimi: sonuc kesinlestirilemedi.'
                END,
                r.available_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                r.lock_token = NULL,
                r.locked_at = NULL,
                r.updated_at = NOW()
            WHERE r.status = 'processing'
              AND r.locked_at IS NOT NULL
              AND r.locked_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
        $stmt->execute([$maxAttempts]);
    }

    private function selectCampaign(PDO $pdo): ?array
    {
        $stmt = $pdo->query("SELECT * FROM bulk_email_campaigns WHERE status = 'sending' ORDER BY id ASC LIMIT 1");
        $campaign = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($campaign) {
            return $campaign;
        }

        $stmt = $pdo->query("SELECT * FROM bulk_email_campaigns WHERE status = 'queued' ORDER BY id ASC LIMIT 1");
        $campaign = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$campaign) {
            return null;
        }
        $update = $pdo->prepare("UPDATE bulk_email_campaigns
            SET status = 'sending', started_at = COALESCE(started_at, NOW()), updated_at = NOW()
            WHERE id = ? AND status = 'queued'");
        $update->execute([(int) $campaign['id']]);
        if ($update->rowCount() !== 1) {
            return null;
        }
        $campaign['status'] = 'sending';
        return $campaign;
    }

    /** @return list<array<string,mixed>> */
    private function claimBatch(PDO $pdo, int $campaignId, int $limit, string $token): array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM bulk_email_recipients
                WHERE campaign_id = ? AND status = 'pending' AND (available_at IS NULL OR available_at <= NOW())
                ORDER BY id ASC LIMIT ? FOR UPDATE");
            $stmt->bindValue(1, $campaignId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                $pdo->commit();
                $this->campaigns->reconcile($pdo, $campaignId);
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update = $pdo->prepare("UPDATE bulk_email_recipients
                SET status = 'processing', lock_token = ?, locked_at = NOW(), updated_at = NOW()
                WHERE id IN ({$placeholders}) AND status = 'pending'");
            $update->execute(array_merge([$token], $ids));
            $pdo->commit();

            $rows = $pdo->prepare("SELECT r.*, c.subject_template, c.body_html_template
                FROM bulk_email_recipients r
                INNER JOIN bulk_email_campaigns c ON c.id = r.campaign_id
                WHERE r.lock_token = ? AND r.status = 'processing'
                ORDER BY r.id ASC");
            $rows->execute([$token]);
            return $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function releaseClaim(PDO $pdo, string $token): void
    {
        $stmt = $pdo->prepare("UPDATE bulk_email_recipients
            SET status = 'pending', lock_token = NULL, locked_at = NULL, updated_at = NOW()
            WHERE lock_token = ? AND status = 'processing'");
        $stmt->execute([$token]);
    }

    private function campaignStatus(PDO $pdo, int $campaignId): string
    {
        $stmt = $pdo->prepare('SELECT status FROM bulk_email_campaigns WHERE id = ?');
        $stmt->execute([$campaignId]);
        return (string) $stmt->fetchColumn();
    }

    private function retryDelaySeconds(int $attempt): int
    {
        return match ($attempt) {
            1 => 60,
            2 => 300,
            default => 900,
        };
    }
}
