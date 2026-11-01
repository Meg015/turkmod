<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ReportStatusService
{
    public function __construct(
        private ReportsSchemaService $schema,
        private ReportNotificationService $notifications,
    ) {}

    /** A null note preserves each report's existing note. An empty string clears it. */
    public function updateSelected(PDO $pdo, string $scope, array $reportIds, string $status, ?string $adminNote, ?int $actorId = null): int
    {
        $prefix = match ($scope) {
            'topics' => 'topic',
            'users' => 'user',
            default => throw new InvalidArgumentException('Geçersiz rapor türü.'),
        };
        $ids = ReportDeletionService::normalizeIds($reportIds);
        if (!in_array($status, ['open', 'reviewing', 'resolved', 'rejected'], true)) {
            throw new InvalidArgumentException('Geçersiz rapor durumu.');
        }
        if ($scope === 'topics') {
            $this->schema->ensureTopicReports($pdo);
        } else {
            $this->schema->ensureUserReports($pdo);
        }
        $newNote = $adminNote === null ? null : mb_substr(trim($adminNote), 0, 1000, 'UTF-8');
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT report_status_update');
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $topicFields = $scope === 'topics' ? ', t.title AS topic_title' : '';
            $topicJoin = $scope === 'topics' ? 'LEFT JOIN topics t ON t.id = r.topic_id' : '';
            $rowLock = $this->schema->isSqlite($pdo) ? '' : ' FOR UPDATE';
            $selected = $pdo->prepare("SELECT r.id, r.status, r.admin_note, r.reporter_user_id {$topicFields}
                FROM {$prefix}_reports r {$topicJoin} WHERE r.id IN ({$placeholders}) ORDER BY r.id{$rowLock}");
            $selected->execute($ids);
            $reports = $selected->fetchAll(PDO::FETCH_ASSOC);
            $update = $pdo->prepare("UPDATE {$prefix}_reports SET status = :status, admin_note = :admin_note, updated_at = :updated_at WHERE id = :id");
            $event = $pdo->prepare("INSERT INTO {$prefix}_report_events (report_id, actor_id, event_type, old_status, new_status, note, created_at)
                VALUES (:report_id, :actor_id, 'status_updated', :old_status, :new_status, :note, :created_at)");
            $changedReports = [];
            foreach ($reports as $report) {
                $note = $newNote ?? (string) ($report['admin_note'] ?? '');
                if ((string) $report['status'] === $status && (string) ($report['admin_note'] ?? '') === $note) {
                    continue;
                }
                $reportId = (int) $report['id'];
                $now = $this->schema->now();
                $update->execute(['status' => $status, 'admin_note' => $note !== '' ? $note : null, 'updated_at' => $now, 'id' => $reportId]);
                $event->execute([
                    'report_id' => $reportId,
                    'actor_id' => $actorId && $actorId > 0 ? $actorId : null,
                    'old_status' => (string) $report['status'],
                    'new_status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'created_at' => $now,
                ]);
                $report['admin_note'] = $note;
                $changedReports[] = $report;
            }
            foreach ($changedReports as $report) {
                $reportId = (int) $report['id'];
                $note = (string) $report['admin_note'];
                $this->notifications->dispatchStatus(
                    $pdo, $prefix . '_report_status_updated', (int) ($report['reporter_user_id'] ?? 0),
                    $actorId, $prefix . '_report', $reportId, $status, $note,
                    $scope === 'topics' ? ['topic_title' => (string) ($report['topic_title'] ?? 'Konu')] : []
                );
                if (function_exists('logActivity')) {
                    logActivity($pdo, $prefix . '_report_updated', $prefix . '_report', $reportId, ['status' => $status]);
                }
            }
            if ($ownsTransaction) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT report_status_update');
            }
            return count($changedReports);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif (!$ownsTransaction && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT report_status_update');
                $pdo->exec('RELEASE SAVEPOINT report_status_update');
            }
            throw $exception;
        }
    }
}
