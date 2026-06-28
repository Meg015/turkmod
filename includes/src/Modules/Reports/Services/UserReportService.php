<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use PDO;

final class UserReportService
{
    public function __construct(
        private ?ReportsSchemaService $schema = null,
        private ?ReportNotificationService $notifications = null,
    ) {
        $this->schema ??= new ReportsSchemaService();
        $this->notifications ??= new ReportNotificationService();
    }

    /** @return array<string,string> */
    public function reasonLabels(): array
    {
        return [
            'harassment' => 'Taciz / hakaret',
            'spam' => 'Spam',
            'impersonation' => 'Taklit hesap',
            'unsafe' => 'Güvensiz davranış',
            'inappropriate' => 'Uygunsuz profil',
            'other' => 'Diğer',
        ];
    }

    public function ensureTables(?PDO $pdo): void
    {
        if ($pdo) {
            $this->schema->ensureUserReports($pdo);
        }
    }

    public function ensureEventsTable(?PDO $pdo): void
    {
        if ($pdo) {
            $this->schema->ensureUserReportEvents($pdo);
        }
    }

    public function createEvent(
        ?PDO $pdo,
        int $reportId,
        string $eventType,
        ?int $actorId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        string $note = '',
    ): void {
        if (!$pdo || $reportId <= 0) {
            return;
        }

        $this->schema->ensureUserReportEvents($pdo);
        $stmt = $pdo->prepare('INSERT INTO user_report_events (report_id, actor_id, event_type, old_status, new_status, note, created_at)
                               VALUES (:report_id, :actor_id, :event_type, :old_status, :new_status, :note, :created_at)');
        $stmt->execute([
            'report_id' => $reportId,
            'actor_id' => $actorId && $actorId > 0 ? $actorId : null,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => trim($note) !== '' ? mb_substr(trim($note), 0, 1000, 'UTF-8') : null,
            'created_at' => $this->schema->now(),
        ]);
    }

    /** @return array{success:bool,message:string} */
    public function submit(?PDO $pdo, int $reportedUserId, int $reporterUserId, string $reason, string $details = ''): array
    {
        if (!$pdo || $reportedUserId <= 0 || $reporterUserId <= 0) {
            return ['success' => false, 'message' => 'Kullanıcı şikayeti göndermek için giriş yapmalısınız.'];
        }
        if ($reportedUserId === $reporterUserId) {
            return ['success' => false, 'message' => 'Kendi profilinizi şikayet edemezsiniz.'];
        }

        $this->schema->ensureUserReports($pdo);
        $labels = $this->reasonLabels();
        if (!isset($labels[$reason])) {
            return ['success' => false, 'message' => 'Geçerli bir şikayet nedeni seçin.'];
        }

        $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute(['id' => $reportedUserId]);
        if (!$userStmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Şikayet edilecek kullanıcı bulunamadı.'];
        }

        $existing = $pdo->prepare("SELECT id FROM user_reports
                                   WHERE reported_user_id = :reported_user_id
                                     AND reporter_user_id = :reporter_user_id
                                     AND status IN ('open', 'reviewing')
                                   LIMIT 1");
        $existing->execute([
            'reported_user_id' => $reportedUserId,
            'reporter_user_id' => $reporterUserId,
        ]);
        if ($existing->fetch()) {
            return ['success' => false, 'message' => 'Bu kullanıcı için açık bir şikayetiniz zaten var.'];
        }

        $details = mb_substr(trim($details), 0, 1000, 'UTF-8');
        $now = $this->schema->now();
        $stmt = $pdo->prepare("INSERT INTO user_reports (reported_user_id, reporter_user_id, reason, details, status, created_at, updated_at)
                               VALUES (:reported_user_id, :reporter_user_id, :reason, :details, 'open', :created_at, :updated_at)");
        $stmt->execute([
            'reported_user_id' => $reportedUserId,
            'reporter_user_id' => $reporterUserId,
            'reason' => $reason,
            'details' => $details !== '' ? $details : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $reportId = (int) $pdo->lastInsertId();
        $this->createEvent($pdo, $reportId, 'created', $reporterUserId, null, 'open', $details);
        if (function_exists('logActivity')) {
            logActivity($pdo, 'user_reported', 'user', $reportedUserId, ['reason' => $labels[$reason]]);
        }

        return ['success' => true, 'message' => 'Kullanıcı şikayetiniz admin ekibine iletildi.'];
    }

    /** @return list<array<string,mixed>> */
    public function list(?PDO $pdo, string $status = '', int $limit = 100, array $filters = []): array
    {
        if (!$pdo) {
            return [];
        }

        $this->schema->ensureUserReports($pdo);
        $allowedStatuses = ['open', 'reviewing', 'resolved', 'rejected'];
        $where = [];
        $params = [];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }
        $reason = (string) ($filters['reason'] ?? '');
        if ($reason !== '' && isset($this->reasonLabels()[$reason])) {
            $where[] = 'r.reason = :reason';
            $params['reason'] = $reason;
        }
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(reported.name LIKE :q_reported_name OR reported.email LIKE :q_reported_email OR reporter.name LIKE :q_reporter_name OR reporter.email LIKE :q_reporter_email OR r.details LIKE :q_details)';
            $queryTerm = '%' . $query . '%';
            $params['q_reported_name'] = $queryTerm;
            $params['q_reported_email'] = $queryTerm;
            $params['q_reporter_name'] = $queryTerm;
            $params['q_reporter_email'] = $queryTerm;
            $params['q_details'] = $queryTerm;
        }
        $this->appendDateFilters($where, $params, $filters);
        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT r.*,
                       reported.name AS reported_user_name,
                       reported.email AS reported_user_email,
                       reporter.name AS reporter_name,
                       reporter.email AS reporter_email
                FROM user_reports r
                LEFT JOIN users reported ON reported.id = r.reported_user_id
                LEFT JOIN users reporter ON reporter.id = r.reporter_user_id
                {$whereSql}
                ORDER BY CASE r.status WHEN 'open' THEN 1 WHEN 'reviewing' THEN 2 WHEN 'resolved' THEN 3 WHEN 'rejected' THEN 4 ELSE 5 END,
                         r.created_at DESC
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,list<array<string,mixed>>> */
    public function eventsForReports(?PDO $pdo, array $reportIds): array
    {
        if (!$pdo) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $reportIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $this->schema->ensureUserReportEvents($pdo);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT e.*, u.name AS actor_name
                               FROM user_report_events e
                               LEFT JOIN users u ON u.id = e.actor_id
                               WHERE e.report_id IN ({$placeholders})
                               ORDER BY e.created_at DESC, e.id DESC");
        $stmt->execute($ids);

        $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $event) {
            $events[(int) $event['report_id']][] = $event;
        }

        return $events;
    }

    public function openCount(?PDO $pdo): int
    {
        if (!$pdo) {
            return 0;
        }

        $this->schema->ensureUserReports($pdo);
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status IN ('open', 'reviewing')");

        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(?PDO $pdo, int $reportId, string $status, string $adminNote = '', ?int $actorId = null): bool
    {
        if (!$pdo || $reportId <= 0) {
            return false;
        }

        $allowedStatuses = ['open', 'reviewing', 'resolved', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        $this->schema->ensureUserReports($pdo);
        $currentStmt = $pdo->prepare('SELECT status, admin_note, reporter_user_id FROM user_reports WHERE id = :id LIMIT 1');
        $currentStmt->execute(['id' => $reportId]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            return false;
        }

        $note = mb_substr(trim($adminNote), 0, 1000, 'UTF-8');
        $stmt = $pdo->prepare('UPDATE user_reports SET status = :status, admin_note = :admin_note, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'admin_note' => $note !== '' ? $note : null,
            'updated_at' => $this->schema->now(),
            'id' => $reportId,
        ]);

        if ($stmt->rowCount() <= 0 && (string) $current['status'] === $status && (string) ($current['admin_note'] ?? '') === $note) {
            return false;
        }

        $this->createEvent($pdo, $reportId, 'status_updated', $actorId, (string) $current['status'], $status, $note);
        $this->notifications->dispatchStatus(
            $pdo,
            'user_report_status_updated',
            (int) ($current['reporter_user_id'] ?? 0),
            $actorId,
            'user_report',
            $reportId,
            $status,
            $note
        );
        if (function_exists('logActivity')) {
            logActivity($pdo, 'user_report_updated', 'user_report', $reportId, ['status' => $status]);
        }

        return true;
    }

    /** @param list<string> $where @param array<string,string> $params */
    private function appendDateFilters(array &$where, array &$params, array $filters): void
    {
        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where[] = 'r.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where[] = 'r.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }
    }
}
