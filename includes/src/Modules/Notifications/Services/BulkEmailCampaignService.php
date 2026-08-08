<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use RuntimeException;
use Throwable;

final class BulkEmailCampaignService
{
    public function __construct(private ?BulkEmailContentService $content = null)
    {
        $this->content ??= new BulkEmailContentService();
    }

    public function schemaReady(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)');
            $stmt->execute(['bulk_email_campaigns', 'bulk_email_recipients']);
            return (int) $stmt->fetchColumn() === 2;
        } catch (Throwable) {
            return false;
        }
    }

    public function requireSchema(PDO $pdo): void
    {
        if (!$this->schemaReady($pdo)) {
            throw new RuntimeException('Toplu e-posta tablolari hazir degil. Veritabani Senkronizasyonunu calistirin.');
        }
    }

    public function eligibleRecipientCount(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT email FROM users
            WHERE status = 'active'
              AND COALESCE(is_banned, 0) = 0
              AND deleted_at IS NULL
              AND email IS NOT NULL
              AND TRIM(email) <> ''");
        if (!$stmt) {
            return 0;
        }
        $count = 0;
        while (($email = $stmt->fetchColumn()) !== false) {
            if (filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) !== false) {
                $count++;
            }
        }
        return $count;
    }

    /** @return array{pending:int,processing:int,failed:int} */
    public function queueSnapshot(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT
                COALESCE(SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN r.status = 'processing' THEN 1 ELSE 0 END), 0) AS processing_count,
                COALESCE(SUM(CASE WHEN r.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count
            FROM bulk_email_recipients r
            INNER JOIN bulk_email_campaigns c ON c.id = r.campaign_id
            WHERE c.status IN ('preparing', 'queued', 'sending', 'paused')");
        $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        return [
            'pending' => (int) ($row['pending_count'] ?? 0),
            'processing' => (int) ($row['processing_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
        ];
    }

    /** @return array{found:bool,status:string,created_at:?string,context:array<string,mixed>} */
    public function latestCronRun(PDO $pdo): array
    {
        $snapshot = ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
        try {
            $stmt = $pdo->prepare("SELECT level, context_json, created_at
                FROM application_logs
                WHERE channel = 'cron' AND message = ?
                ORDER BY id DESC LIMIT 1");
            $stmt->execute(['cron_run:bulk_email_campaigns']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $snapshot;
            }
            $context = json_decode((string) ($row['context_json'] ?? ''), true);
            $context = is_array($context) ? $context : [];
            $status = strtolower(trim((string) ($context['status'] ?? '')));
            if ($status === '') {
                $status = match ((string) ($row['level'] ?? 'info')) {
                    'error' => 'error',
                    'warning' => 'warning',
                    default => 'success',
                };
            }
            return [
                'found' => true,
                'status' => $status,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'context' => $context,
            ];
        } catch (Throwable) {
            return $snapshot;
        }
    }

    public function saveDraft(PDO $pdo, int $creatorId, string $subject, string $bodyHtml, int $campaignId = 0): int
    {
        $this->requireSchema($pdo);
        $validated = $this->content->validateAndSanitize($subject, $bodyHtml);

        if ($campaignId > 0) {
            $campaign = $this->find($pdo, $campaignId);
            if (!$campaign || (string) $campaign['status'] !== 'draft') {
                throw new RuntimeException('Yalnizca taslak kampanyalar duzenlenebilir.');
            }
            $stmt = $pdo->prepare('UPDATE bulk_email_campaigns SET subject_template = ?, body_html_template = ?, updated_at = NOW() WHERE id = ? AND status = ?');
            $stmt->execute([$validated['subject'], $validated['body_html'], $campaignId, 'draft']);
            return $campaignId;
        }

        $stmt = $pdo->prepare("INSERT INTO bulk_email_campaigns
            (created_by_user_id, subject_template, body_html_template, status, created_at, updated_at)
            VALUES (?, ?, ?, 'draft', NOW(), NOW())");
        $stmt->execute([$creatorId > 0 ? $creatorId : null, $validated['subject'], $validated['body_html']]);
        return (int) $pdo->lastInsertId();
    }

    public function beginPreparation(PDO $pdo, int $campaignId): array
    {
        $this->requireSchema($pdo);
        if ($this->eligibleRecipientCount($pdo) <= 0) {
            throw new RuntimeException('Gonderime uygun aktif uye bulunamadi.');
        }

        $stmt = $pdo->prepare("UPDATE bulk_email_campaigns
            SET status = 'preparing', snapshot_after_user_id = 0, snapshot_completed_at = NULL,
                recipient_total = 0, pending_count = 0, processing_count = 0, sent_count = 0,
                failed_count = 0, cancelled_count = 0, started_at = NULL, paused_at = NULL,
                completed_at = NULL, cancelled_at = NULL, updated_at = NOW()
            WHERE id = ? AND status = 'draft'");
        $stmt->execute([$campaignId]);
        if ($stmt->rowCount() !== 1) {
            $campaign = $this->find($pdo, $campaignId);
            if (!$campaign || !in_array((string) $campaign['status'], ['preparing', 'queued', 'sending'], true)) {
                throw new RuntimeException('Kampanya gonderime hazirlanamadi.');
            }
        }

        return $this->progress($pdo, $campaignId);
    }

    /** @return array{campaign_id:int,selected:int,inserted:int,complete:bool,status:string} */
    public function prepareRecipientChunk(PDO $pdo, int $campaignId, int $limit = 1000): array
    {
        $this->requireSchema($pdo);
        $limit = max(100, min(2000, $limit));

        $pdo->beginTransaction();
        try {
            $campaignStmt = $pdo->prepare('SELECT * FROM bulk_email_campaigns WHERE id = ? FOR UPDATE');
            $campaignStmt->execute([$campaignId]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) {
                throw new RuntimeException('Kampanya bulunamadi.');
            }
            if ((string) $campaign['status'] !== 'preparing') {
                $pdo->commit();
                return [
                    'campaign_id' => $campaignId,
                    'selected' => 0,
                    'inserted' => 0,
                    'complete' => $campaign['snapshot_completed_at'] !== null,
                    'status' => (string) $campaign['status'],
                ];
            }

            $afterId = (int) $campaign['snapshot_after_user_id'];
            $usersStmt = $pdo->prepare("SELECT id, username, email FROM users
                WHERE id > ?
                  AND status = 'active'
                  AND COALESCE(is_banned, 0) = 0
                  AND deleted_at IS NULL
                  AND email IS NOT NULL
                  AND TRIM(email) <> ''
                ORDER BY id ASC
                LIMIT ?");
            $usersStmt->bindValue(1, $afterId, PDO::PARAM_INT);
            $usersStmt->bindValue(2, $limit, PDO::PARAM_INT);
            $usersStmt->execute();
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $inserted = 0;
            $lastUserId = $afterId;
            $insert = $pdo->prepare("INSERT IGNORE INTO bulk_email_recipients
                (campaign_id, user_id, recipient_email, recipient_username, status, attempt_count, available_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'pending', 0, NOW(), NOW(), NOW())");
            foreach ($users as $user) {
                $lastUserId = max($lastUserId, (int) $user['id']);
                $email = trim((string) ($user['email'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }
                $insert->execute([
                    $campaignId,
                    (int) $user['id'],
                    $email,
                    mb_substr(trim((string) ($user['username'] ?? 'Uye')), 0, 255, 'UTF-8'),
                ]);
                $inserted += $insert->rowCount();
            }

            $complete = count($users) < $limit;
            if ($complete) {
                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM bulk_email_recipients WHERE campaign_id = ?');
                $countStmt->execute([$campaignId]);
                $recipientTotal = (int) $countStmt->fetchColumn();
                if ($recipientTotal <= 0) {
                    throw new RuntimeException('Gonderime uygun gecerli e-posta adresi bulunamadi.');
                }
                $update = $pdo->prepare("UPDATE bulk_email_campaigns
                    SET snapshot_after_user_id = ?, snapshot_completed_at = NOW(), status = 'queued',
                        recipient_total = ?, pending_count = ?, updated_at = NOW()
                    WHERE id = ? AND status = 'preparing'");
                $update->execute([$lastUserId, $recipientTotal, $recipientTotal, $campaignId]);
                $status = 'queued';
            } else {
                $update = $pdo->prepare('UPDATE bulk_email_campaigns SET snapshot_after_user_id = ?, updated_at = NOW() WHERE id = ? AND status = ?');
                $update->execute([$lastUserId, $campaignId, 'preparing']);
                $status = 'preparing';
            }
            $pdo->commit();

            return [
                'campaign_id' => $campaignId,
                'selected' => count($users),
                'inserted' => $inserted,
                'complete' => $complete,
                'status' => $status,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function nextPreparingCampaignId(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id FROM bulk_email_campaigns WHERE status = 'preparing' ORDER BY id ASC LIMIT 1");
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    public function pause(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare("UPDATE bulk_email_campaigns SET status = 'paused', paused_at = NOW(), updated_at = NOW()
            WHERE id = ? AND status IN ('preparing','queued','sending')");
        $stmt->execute([$campaignId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Kampanya bu durumda duraklatilamaz.');
        }
        return $this->progress($pdo, $campaignId);
    }

    public function resume(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare("UPDATE bulk_email_campaigns
            SET status = CASE WHEN snapshot_completed_at IS NULL THEN 'preparing' ELSE 'queued' END,
                paused_at = NULL, updated_at = NOW()
            WHERE id = ? AND status = 'paused'");
        $stmt->execute([$campaignId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Kampanya bu durumda devam ettirilemez.');
        }
        return $this->progress($pdo, $campaignId);
    }

    public function cancel(PDO $pdo, int $campaignId): array
    {
        $pdo->beginTransaction();
        try {
            $campaign = $this->lockCampaign($pdo, $campaignId);
            if (in_array((string) $campaign['status'], ['completed', 'cancelled'], true)) {
                throw new RuntimeException('Tamamlanmis veya iptal edilmis kampanya yeniden iptal edilemez.');
            }
            $recipients = $pdo->prepare("UPDATE bulk_email_recipients
                SET status = 'cancelled', lock_token = NULL, locked_at = NULL, updated_at = NOW()
                WHERE campaign_id = ? AND status = 'pending'");
            $recipients->execute([$campaignId]);
            $stmt = $pdo->prepare("UPDATE bulk_email_campaigns SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$campaignId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $this->reconcile($pdo, $campaignId);
        return $this->progress($pdo, $campaignId);
    }

    public function retryFailed(PDO $pdo, int $campaignId): array
    {
        $pdo->beginTransaction();
        try {
            $campaign = $this->lockCampaign($pdo, $campaignId);
            if ((string) $campaign['status'] === 'cancelled') {
                throw new RuntimeException('Iptal edilmis kampanyanin alicilari yeniden denenemez.');
            }
            $stmt = $pdo->prepare("UPDATE bulk_email_recipients
                SET status = 'pending', attempt_count = 0, available_at = NOW(), lock_token = NULL, locked_at = NULL, updated_at = NOW()
                WHERE campaign_id = ? AND status = 'failed'");
            $stmt->execute([$campaignId]);
            if ($stmt->rowCount() <= 0) {
                throw new RuntimeException('Yeniden denenecek basarisiz alici bulunamadi.');
            }
            $update = $pdo->prepare("UPDATE bulk_email_campaigns SET status = 'queued', completed_at = NULL, updated_at = NOW() WHERE id = ?");
            $update->execute([$campaignId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $this->reconcile($pdo, $campaignId);
        return $this->progress($pdo, $campaignId);
    }

    public function reconcile(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare("SELECT
                COUNT(*) AS recipient_total,
                COALESCE(SUM(status = 'pending'), 0) AS pending_count,
                COALESCE(SUM(status = 'processing'), 0) AS processing_count,
                COALESCE(SUM(status = 'sent'), 0) AS sent_count,
                COALESCE(SUM(status = 'failed'), 0) AS failed_count,
                COALESCE(SUM(status = 'cancelled'), 0) AS cancelled_count
            FROM bulk_email_recipients WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $update = $pdo->prepare("UPDATE bulk_email_campaigns SET
                recipient_total = ?, pending_count = ?, processing_count = ?, sent_count = ?, failed_count = ?, cancelled_count = ?,
                status = CASE
                    WHEN status IN ('sending','queued') AND ? = 0 AND ? = 0 THEN 'completed'
                    ELSE status
                END,
                completed_at = CASE
                    WHEN status IN ('sending','queued') AND ? = 0 AND ? = 0 THEN COALESCE(completed_at, NOW())
                    ELSE completed_at
                END,
                updated_at = NOW()
            WHERE id = ?");
        $pending = (int) ($counts['pending_count'] ?? 0);
        $processing = (int) ($counts['processing_count'] ?? 0);
        $update->execute([
            (int) ($counts['recipient_total'] ?? 0), $pending, $processing,
            (int) ($counts['sent_count'] ?? 0), (int) ($counts['failed_count'] ?? 0), (int) ($counts['cancelled_count'] ?? 0),
            $pending, $processing, $pending, $processing, $campaignId,
        ]);
        return $counts;
    }

    public function find(PDO $pdo, int $campaignId): ?array
    {
        $stmt = $pdo->prepare("SELECT c.*, u.username AS creator_username
            FROM bulk_email_campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            WHERE c.id = ? LIMIT 1");
        $stmt->execute([$campaignId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function progress(PDO $pdo, int $campaignId): array
    {
        $campaign = $this->find($pdo, $campaignId);
        if (!$campaign) {
            throw new RuntimeException('Kampanya bulunamadi.');
        }
        $total = (int) $campaign['recipient_total'];
        $processed = (int) $campaign['sent_count'] + (int) $campaign['failed_count'] + (int) $campaign['cancelled_count'];
        $campaign['processed_count'] = $processed;
        $campaign['progress_percent'] = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 0;
        $campaign['recent_failures'] = $this->recentFailures($pdo, $campaignId, 8);
        return $campaign;
    }

    public function activeCampaign(PDO $pdo): ?array
    {
        $stmt = $pdo->query("SELECT id FROM bulk_email_campaigns
            WHERE status IN ('preparing','queued','sending','paused')
            ORDER BY FIELD(status, 'sending','preparing','paused','queued'), id ASC LIMIT 1");
        $id = (int) ($stmt ? $stmt->fetchColumn() : 0);
        return $id > 0 ? $this->progress($pdo, $id) : null;
    }

    /** @return list<array<string,mixed>> */
    public function history(PDO $pdo, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $pdo->prepare("SELECT c.*, u.username AS creator_username
            FROM bulk_email_campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            ORDER BY c.id DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function recentFailures(PDO $pdo, int $campaignId, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT id, recipient_email, recipient_username, attempt_count, last_error, updated_at
            FROM bulk_email_recipients WHERE campaign_id = ? AND status = 'failed'
            ORDER BY updated_at DESC, id DESC LIMIT ?");
        $stmt->bindValue(1, $campaignId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function lockCampaign(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM bulk_email_campaigns WHERE id = ? FOR UPDATE');
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            throw new RuntimeException('Kampanya bulunamadi.');
        }
        return $campaign;
    }
}
