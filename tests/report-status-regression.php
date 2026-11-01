<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/src/Modules/Reports/Support/helpers.php';

use App\Modules\Reports\Database\migrations\Support\ReportsSchemaInstaller;

$checks = 0;
$failures = [];
// Capture the notification boundary in the isolated database; no real notifications are sent.
function routePrivateProfileUrl(array $params = []): string
{
    return '/profil?' . http_build_query($params);
}
function notificationDispatch(PDO $db, string $eventKey, int $recipientId, ?int $actorId, string $entityType, int $entityId, array $payload = []): bool
{
    $stmt = $db->prepare('INSERT INTO report_notifications (report_id, recipient_id, event_key, payload) VALUES (?, ?, ?, ?)');
    return $stmt->execute([$entityId, $recipientId, $eventKey, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}
function statusCheck(bool $condition, string $label): void
{
    $GLOBALS['checks']++;
    if (!$condition) { $GLOBALS['failures'][] = $label; }
}
function statusRejects(callable $action): bool
{
    try { $action(); } catch (Throwable) { return true; }
    return false;
}
function statusFixture(): PDO
{
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT)');
    $db->exec('CREATE TABLE topics (id INTEGER PRIMARY KEY, title TEXT)');
    $db->exec('CREATE TABLE report_notifications (report_id INTEGER, recipient_id INTEGER, event_key TEXT, payload TEXT)');
    $db->exec("INSERT INTO topics VALUES (1, 'Konu')");
    $installer = new ReportsSchemaInstaller();
    $installer->ensureTopicReports($db, false);
    $installer->ensureUserReports($db, false);
    foreach (['topic' => 'topic_id', 'user' => 'reported_user_id'] as $prefix => $target) {
        $db->exec("INSERT INTO {$prefix}_reports (id, {$target}, reason, status, admin_note, updated_at)
            VALUES (1, 1, 'spam', 'open', 'Birinci not', '2020-01-01 00:00:00'),
                   (2, 1, 'spam', 'open', 'İkinci not', '2020-01-01 00:00:00'),
                   (3, 1, 'spam', 'resolved', 'Tamamlandı', '2020-01-01 00:00:00')");
        $db->exec("UPDATE {$prefix}_reports SET reporter_user_id = 5");
    }
    return $db;
}

foreach (['topics' => 'topic', 'users' => 'user'] as $scope => $prefix) {
    $service = $scope === 'topics' ? topicReportService() : userReportService();
    $db = statusFixture();
    statusCheck(!$service->updateStatus($db, 1, 'open', 'Birinci not'), "$scope: unchanged report counted as updated");
    statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_report_events")->fetchColumn() === 0, "$scope: unchanged report created duplicate history");
    statusCheck((int) $db->query('SELECT COUNT(*) FROM report_notifications')->fetchColumn() === 0, "$scope: unchanged report notified user again");
    statusCheck($db->query("SELECT updated_at FROM {$prefix}_reports WHERE id = 1")->fetchColumn() === '2020-01-01 00:00:00', "$scope: no-op changed modification time");

    $db = statusFixture();
    $db->exec("CREATE TRIGGER stop_event BEFORE INSERT ON {$prefix}_report_events BEGIN SELECT RAISE(ABORT, 'test history failure'); END");
    statusCheck(statusRejects(fn () => $service->updateStatus($db, 1, 'resolved', 'Yeni not')), "$scope: history write failure swallowed");
    statusCheck($db->query("SELECT status FROM {$prefix}_reports WHERE id = 1")->fetchColumn() === 'open', "$scope: failed operation changed status");
    statusCheck($db->query("SELECT admin_note FROM {$prefix}_reports WHERE id = 1")->fetchColumn() === 'Birinci not', "$scope: failed operation changed note");
    statusCheck(!$db->inTransaction(), "$scope: failed transaction left open");
}

if (function_exists('updateSelectedReportsStatus')) {
    foreach (['topics' => 'topic', 'users' => 'user'] as $scope => $prefix) {
        $service = $scope === 'topics' ? topicReportService() : userReportService();
        $db = statusFixture();
        $other = $prefix === 'topic' ? 'user' : 'topic';
        statusCheck(updateSelectedReportsStatus($db, $scope, [1, '2', 2, 999], 'reviewing', '') === 2, "$scope: wrong bulk update count");
        statusCheck($db->query("SELECT admin_note FROM {$prefix}_reports ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) === ['Birinci not', 'İkinci not', 'Tamamlandı'], "$scope: optional empty note erased existing notes");
        statusCheck($db->query("SELECT status FROM {$prefix}_reports ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) === ['reviewing', 'reviewing', 'resolved'], "$scope: bulk selection not respected");
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$other}_reports WHERE status = 'open'")->fetchColumn() === 2, "$scope: other scope changed");
        statusCheck(updateSelectedReportsStatus($db, $scope, [1, 2], 'reviewing', '   ') === 0, "$scope: repeated bulk update created changes");
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_report_events")->fetchColumn() === 2, "$scope: repeated update created duplicate history");
        statusCheck((int) $db->query('SELECT COUNT(*) FROM report_notifications')->fetchColumn() === 2, "$scope: repeated bulk update duplicated notifications");
        $payload = json_decode((string) $db->query('SELECT payload FROM report_notifications LIMIT 1')->fetchColumn(), true);
        statusCheck($payload['moderation_note'] === 'Birinci not' && $payload['report_status'] === 'İnceleniyor', "$scope: notification lost preserved note/status");
        statusCheck(updateSelectedReportsStatus($db, $scope, [1, 2], 'resolved', ' Yeni not ') === 2, "$scope: explicit bulk note not updated");
        statusCheck($db->query("SELECT DISTINCT admin_note FROM {$prefix}_reports WHERE id IN (1,2)")->fetchAll(PDO::FETCH_COLUMN) === ['Yeni not'], "$scope: bulk note not normalized");
        statusCheck($service->updateStatus($db, 1, 'resolved', ''), "$scope: single form cannot intentionally clear note");
        statusCheck($db->query("SELECT admin_note FROM {$prefix}_reports WHERE id = 1")->fetchColumn() === null, "$scope: intentional note clearing ignored");
        statusCheck(!$service->updateStatus($db, 999, 'resolved', 'Yeni not'), "$scope: missing report counted");
        statusCheck(!$service->updateStatus($db, 1, 'invalid', ''), "$scope: invalid single status accepted");

        $db = statusFixture();
        $db->exec("CREATE TRIGGER stop_second BEFORE INSERT ON {$prefix}_report_events WHEN NEW.report_id = 2 BEGIN SELECT RAISE(ABORT, 'test batch failure'); END");
        statusCheck(statusRejects(fn () => updateSelectedReportsStatus($db, $scope, [1, 2], 'resolved', 'Yeni not')), "$scope: bulk history error swallowed");
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_reports WHERE status = 'open'")->fetchColumn() === 2, "$scope: failure partially updated reports");
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_report_events")->fetchColumn() === 0, "$scope: failure left partial history");
        statusCheck((int) $db->query('SELECT COUNT(*) FROM report_notifications')->fetchColumn() === 0, "$scope: failed batch notified users");
        statusCheck(!$db->inTransaction(), "$scope: bulk failure left transaction open");
        $db->beginTransaction();
        $db->exec("UPDATE {$prefix}_reports SET reason = 'other' WHERE id = 3");
        statusCheck(statusRejects(fn () => updateSelectedReportsStatus($db, $scope, [1, 2], 'resolved', '')), "$scope: nested update error swallowed");
        statusCheck($db->inTransaction() && $db->query("SELECT reason FROM {$prefix}_reports WHERE id = 3")->fetchColumn() === 'other', "$scope: nested failure lost caller work");
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_reports WHERE status = 'open'")->fetchColumn() === 2, "$scope: nested failure partially updated selection");
        $db->rollBack();
        $db = statusFixture();
        $db->beginTransaction();
        statusCheck(updateSelectedReportsStatus($db, $scope, [1, 2], 'reviewing', '') === 2 && $db->inTransaction(), "$scope: caller transaction committed");
        $db->rollBack();
        statusCheck((int) $db->query("SELECT COUNT(*) FROM {$prefix}_reports WHERE status = 'open'")->fetchColumn() === 2, "$scope: caller rollback lost reports");
        foreach ([['reasons', [1], 'resolved'], [$scope, [], 'resolved'], [$scope, ['1x'], 'resolved'], [$scope, [1], 'invalid']] as [$invalidScope, $invalidIds, $invalidStatus]) {
            statusCheck(statusRejects(fn () => updateSelectedReportsStatus($db, $invalidScope, $invalidIds, $invalidStatus, '')), "$scope: invalid bulk input accepted");
        }
    }
    statusCheck(statusRejects(fn () => updateSelectedReportsStatus(null, 'topics', [1], 'resolved', '')), 'Missing database silently accepted');
} else {
    statusCheck(false, 'Transactional bulk status adapter is missing');
}

foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
echo sprintf("Report status: %d checks, %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
