<?php

declare(strict_types=1);

use App\Modules\Reports\Services\ReportNotificationService;
use App\Modules\Reports\Services\ReportsSchemaService;
use App\Modules\Reports\Services\TopicReportService;
use App\Modules\Reports\Services\UserReportService;

function reportsSchemaService(): ReportsSchemaService
{
    static $service = null;

    return $service ??= new ReportsSchemaService();
}

function reportNotificationService(): ReportNotificationService
{
    static $service = null;

    return $service ??= new ReportNotificationService();
}

function userReportService(): UserReportService
{
    static $service = null;

    return $service ??= new UserReportService(reportsSchemaService(), reportNotificationService());
}

function topicReportService(): TopicReportService
{
    static $service = null;

    return $service ??= new TopicReportService(reportsSchemaService(), reportNotificationService());
}

function reportsIsSqlite(PDO $pdo): bool
{
    return reportsSchemaService()->isSqlite($pdo);
}

function reportsNow(): string
{
    return reportsSchemaService()->now();
}

function userReportReasonLabels(): array
{
    return userReportService()->reasonLabels();
}

function ensureUserReportsTable(?PDO $pdo): void
{
    userReportService()->ensureTables($pdo);
}

function ensureUserReportEventsTable(?PDO $pdo): void
{
    userReportService()->ensureEventsTable($pdo);
}

function createUserReportEvent(
    ?PDO $pdo,
    int $reportId,
    string $eventType,
    ?int $actorId = null,
    ?string $oldStatus = null,
    ?string $newStatus = null,
    string $note = '',
): void {
    userReportService()->createEvent($pdo, $reportId, $eventType, $actorId, $oldStatus, $newStatus, $note);
}

function submitUserReport(?PDO $pdo, int $reportedUserId, int $reporterUserId, string $reason, string $details = ''): array
{
    return userReportService()->submit($pdo, $reportedUserId, $reporterUserId, $reason, $details);
}

function getUserReports(?PDO $pdo, string $status = '', int $limit = 100, array $filters = []): array
{
    return userReportService()->list($pdo, $status, $limit, $filters);
}

function getUserReportEventsForReports(?PDO $pdo, array $reportIds): array
{
    return userReportService()->eventsForReports($pdo, $reportIds);
}

function getOpenUserReportCount(?PDO $pdo): int
{
    return userReportService()->openCount($pdo);
}

function updateUserReportStatus(
    ?PDO $pdo,
    int $reportId,
    string $status,
    string $adminNote = '',
    ?int $actorId = null,
): bool {
    return userReportService()->updateStatus($pdo, $reportId, $status, $adminNote, $actorId);
}

function topicReportReasonLabels(): array
{
    return topicReportService()->reasonLabels();
}

function reportStatusNotificationLabel(string $status): string
{
    return reportNotificationService()->statusLabel($status);
}

function reportStatusNotificationType(string $status): string
{
    return reportNotificationService()->statusType($status);
}

function reportDispatchStatusNotification(
    PDO $pdo,
    string $eventKey,
    int $recipientId,
    ?int $actorId,
    string $entityType,
    int $reportId,
    string $status,
    string $adminNote = '',
    array $payload = []
): void {
    reportNotificationService()->dispatchStatus(
        $pdo,
        $eventKey,
        $recipientId,
        $actorId,
        $entityType,
        $reportId,
        $status,
        $adminNote,
        $payload
    );
}

function ensureTopicReportsTable(?PDO $pdo): void
{
    topicReportService()->ensureTables($pdo);
}

function ensureTopicReportEventsTable(?PDO $pdo): void
{
    topicReportService()->ensureEventsTable($pdo);
}

function createTopicReportEvent(
    ?PDO $pdo,
    int $reportId,
    string $eventType,
    ?int $actorId = null,
    ?string $oldStatus = null,
    ?string $newStatus = null,
    string $note = ''
): void {
    topicReportService()->createEvent($pdo, $reportId, $eventType, $actorId, $oldStatus, $newStatus, $note);
}

function submitTopicReport(?PDO $pdo, int $topicId, int $reporterUserId, string $reason, string $details = ''): array
{
    return topicReportService()->submit($pdo, $topicId, $reporterUserId, $reason, $details);
}

function getTopicReports(?PDO $pdo, string $status = '', int $limit = 100, array $filters = []): array
{
    return topicReportService()->list($pdo, $status, $limit, $filters);
}

function getTopicReportEventsForReports(?PDO $pdo, array $reportIds): array
{
    return topicReportService()->eventsForReports($pdo, $reportIds);
}

function updateTopicReportStatus(
    ?PDO $pdo,
    int $reportId,
    string $status,
    string $adminNote = '',
    ?int $actorId = null
): bool {
    return topicReportService()->updateStatus($pdo, $reportId, $status, $adminNote, $actorId);
}
