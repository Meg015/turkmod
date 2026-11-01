<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/src/Modules/Reports/Support/helpers.php';

use App\Modules\Reports\Services\ReportDeletionService;

$checks = 0;
$failures = [];
function reportCheck(bool $condition, string $label): void
{
    $GLOBALS['checks']++;
    if (!$condition) { $GLOBALS['failures'][] = $label; }
}
function reportRejects(callable $action): bool
{
    try { $action(); } catch (Throwable) { return true; }
    return false;
}
function reportFixture(): PDO
{
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA foreign_keys = ON');
    foreach (['topic', 'user'] as $scope) {
        $db->exec("CREATE TABLE {$scope}_reports (id INTEGER PRIMARY KEY, reporter_name TEXT, reporter_email TEXT, reporter_type TEXT)");
        $db->exec("CREATE TABLE {$scope}_report_events (id INTEGER PRIMARY KEY, report_id INTEGER REFERENCES {$scope}_reports(id) ON DELETE CASCADE)");
        $db->exec("INSERT INTO {$scope}_reports (id) VALUES (1), (2), (3)");
        $db->exec("INSERT INTO {$scope}_report_events VALUES (11,1), (12,1), (21,2), (31,3)");
    }
    $db->exec('CREATE TABLE topics (id INTEGER PRIMARY KEY)');
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    $db->exec('INSERT INTO topics VALUES (1), (2), (3)');
    $db->exec('INSERT INTO users VALUES (1), (2), (3)');
    return $db;
}

reportCheck(ReportDeletionService::normalizeIds([2, '1', 2]) === [2, 1], 'IDs must be unique positive integers');
foreach ([[], [0], [-1], ['1 OR 1=1'], ['1.5'], [1.5], [true], [[1]], [null], ['99999999999999999999999999999999']] as $invalid) {
    reportCheck(reportRejects(fn () => ReportDeletionService::normalizeIds($invalid)), 'Invalid report IDs accepted');
}
foreach (['topics' => 'topic', 'users' => 'user'] as $scope => $table) {
    $db = reportFixture();
    $other = $table === 'topic' ? 'user' : 'topic';
    reportCheck(deleteSelectedReports($db, $scope, [1, '3', 1, 999]) === 2, "$scope: wrong deletion count");
    reportCheck($db->query("SELECT id FROM {$table}_reports")->fetchAll(PDO::FETCH_COLUMN) === [2], "$scope: unselected report removed");
    reportCheck($db->query("SELECT id FROM {$table}_report_events")->fetchAll(PDO::FETCH_COLUMN) === [21], "$scope: related history not scoped");
    reportCheck((int) $db->query("SELECT COUNT(*) FROM {$other}_reports")->fetchColumn() === 3, "$scope: other scope modified");
    reportCheck((int) $db->query('SELECT COUNT(*) FROM topics')->fetchColumn() === 3 && (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3, "$scope: source content removed");
    reportCheck(deleteSelectedReports($db, $scope, [999]) === 0, "$scope: missing report counted as deleted");
    $deleteOne = $scope === 'topics' ? 'deleteTopicReport' : 'deleteUserReport';
    reportCheck(!$deleteOne($db, 999), "$scope: missing single delete reports success");
    reportCheck($deleteOne($db, 2), "$scope: single deletion failed");
    reportCheck(!$db->inTransaction(), "$scope: owned transaction left open");

    $db = reportFixture();
    $db->exec("CREATE TRIGGER stop_delete BEFORE DELETE ON {$table}_reports WHEN OLD.id = 3 BEGIN SELECT RAISE(ABORT, 'test failure'); END");
    reportCheck(reportRejects(fn () => deleteSelectedReports($db, $scope, [1, 3])), "$scope: storage error swallowed");
    reportCheck((int) $db->query("SELECT COUNT(*) FROM {$table}_reports")->fetchColumn() === 3, "$scope: failure partially deleted reports");
    reportCheck((int) $db->query("SELECT COUNT(*) FROM {$table}_report_events")->fetchColumn() === 4, "$scope: failure partially deleted history");
    reportCheck(!$db->inTransaction(), "$scope: failed transaction left open");

    $db->beginTransaction();
    $db->exec("INSERT INTO {$table}_reports (id) VALUES (4)");
    reportCheck(reportRejects(fn () => deleteSelectedReports($db, $scope, [1, 3])), "$scope: nested storage error swallowed");
    reportCheck($db->inTransaction() && (int) $db->query("SELECT COUNT(*) FROM {$table}_reports")->fetchColumn() === 4, "$scope: nested failure lost caller work");
    reportCheck((int) $db->query("SELECT COUNT(*) FROM {$table}_report_events")->fetchColumn() === 4, "$scope: nested failure did not restore history");
    $db->rollBack();

    $db = reportFixture();
    $db->beginTransaction();
    reportCheck(deleteSelectedReports($db, $scope, [1]) === 1 && $db->inTransaction(), "$scope: caller transaction committed");
    $db->rollBack();
    reportCheck((int) $db->query("SELECT COUNT(*) FROM {$table}_reports")->fetchColumn() === 3, "$scope: caller rollback lost reports");
}
$db = reportFixture();
reportCheck(reportRejects(fn () => deleteSelectedReports($db, 'reasons', [1])), 'Invalid scope accepted');
reportCheck(reportRejects(fn () => deleteSelectedReports(null, 'topics', [1])), 'Missing database accepted');
reportCheck((int) $db->query('SELECT COUNT(*) FROM topic_reports')->fetchColumn() === 3, 'Rejected operation changed data');

$migration = require dirname(__DIR__) . '/includes/src/Modules/Reports/Database/migrations/2026_11_01_0001_restore_user_report_tables.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$migration->up($db);
reportsSchemaService()->ensureUserReports($db);
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_reports')->fetchColumn() === 0, 'Repair did not create report table');
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_report_events')->fetchColumn() === 0, 'Repair did not create history table');
$db->exec("INSERT INTO user_reports (reported_user_id, reason) VALUES (1, 'spam')");
$db->exec("INSERT INTO user_report_events (report_id, event_type) VALUES (1, 'created')");
$migration->up($db);
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_reports')->fetchColumn() === 1, 'Repeated migration removed existing reports');
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_report_events')->fetchColumn() === 1, 'Repeated migration removed existing history');
reportCheck(reportRejects(fn () => $migration->down($db)), 'Repair rollback can remove existing data');
$db->exec('DROP TABLE user_reports');
$migration->up($db);
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_reports')->fetchColumn() === 0, 'Missing parent table was not restored');
reportCheck((int) $db->query('SELECT COUNT(*) FROM user_report_events')->fetchColumn() === 1, 'Repair changed existing history');

foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
echo sprintf("Report deletion: %d checks, %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
