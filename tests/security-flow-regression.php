<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware\CsrfGuard;
use App\Core\Routing\Middleware\RateLimitMiddleware;

$checks = 0;
$failures = [];
function flowCheck(bool $condition, string $label): void {
    $GLOBALS['checks']++;
    if (!$condition) { $GLOBALS['failures'][] = $label; }
}
function flowRejects(callable $action): bool {
    try { $action(); } catch (Throwable) { return true; }
    return false;
}
$next = new class implements Handler {
    public int $calls = 0;
    public function handle(Request $request): Response { $this->calls++; return new Response('reached', 204); }
};

// Missing security functions must stop execution even when warnings are handled.
set_error_handler(static fn (): bool => true);
flowCheck(flowRejects(fn () => (new CsrfGuard())->process(new Request('POST', '/api/test'), $next)), 'Missing CSRF verifier allowed a mutation');
flowCheck($next->calls === 0, 'Missing CSRF verifier reached handler');
$next->calls = 0;
flowCheck(flowRejects(fn () => (new RateLimitMiddleware())->process(new Request('GET', '/api/test'), $next)), 'Missing rate limiter allowed a request');
flowCheck($next->calls === 0, 'Missing rate limiter reached handler');
restore_error_handler();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once dirname(__DIR__) . '/includes/helpers.php';
session_start();
$_SESSION['_csrf_token'] = str_repeat('a', 64);
$token = csrf_token();
$guard = new CsrfGuard();
foreach ([
    new Request('POST', '/turkmod/api/test', headers: ['X-CSRF-Token' => $token]),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/json'], body: json_encode(['_token' => $token])),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/problem+json'], body: json_encode(['_token' => $token])),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/x-www-form-urlencoded'], body: http_build_query(['_token' => $token])),
    new Request('POST', '/turkmod/yukle', headers: ['Content-Type' => 'multipart/form-data; boundary=test'], parsedBody: ['_token' => $token]),
] as $request) {
    $next->calls = 0;
    flowCheck($guard->process($request, $next)->getStatusCode() === 204 && $next->calls === 1, 'Valid body/header CSRF token rejected');
}
foreach ([
    new Request('POST', '/turkmod/api/test'),
    new Request('POST', '/turkmod/api/test', query: ['_token' => $token]),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/json'], body: '{broken'),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/json'], body: '{"_token":["bad"]}'),
    new Request('POST', '/turkmod/api/test', headers: ['Content-Type' => 'application/x-www-form-urlencoded'], body: '_token[]=bad'),
    new Request('POST', '/turkmod/api/test', parsedBody: ['_token' => ['bad']]),
] as $request) {
    $next->calls = 0;
    $response = $guard->process($request, $next);
    flowCheck($response->getStatusCode() === 419 && $next->calls === 0, 'Invalid CSRF token reached handler');
    flowCheck(str_contains((string) $response->getHeader('Content-Type'), 'application/json'), 'Subdirectory API CSRF error is not JSON');
    flowCheck($response->getHeader('Cache-Control') === 'no-store', 'CSRF error can be cached');
}
$_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/turkmod/yukle', 'CONTENT_TYPE' => 'multipart/form-data; boundary=test'];
$_POST = ['_token' => $token];
flowCheck(Request::fromGlobals()->getParsedBody() === $_POST, 'Global multipart form fields lost');
flowCheck($guard->process(Request::fromGlobals(), $next)->getStatusCode() === 204, 'Global multipart form rejected');
$_POST = ['_token' => ['bad']];
flowCheck(CsrfProtection::getFromRequest() === null && !CsrfProtection::validateRequest(), 'Array CSRF token throws or is accepted');
foreach ([null, '', [], 123, false, new stdClass()] as $invalid) {
    flowCheck(!verify_csrf_token($invalid), 'Invalid token type is accepted or throws');
}
if (!function_exists('checkRateLimit')) {
    function checkRateLimit(string $key, int $max, int $window): bool { return $GLOBALS['rateAllowed']; }
    function incrementRateLimit(string $key, int $window): void { $GLOBALS['rateHits'] = ($GLOBALS['rateHits'] ?? 0) + 1; }
    function getRateLimitRemainingSeconds(string $key, int $window): int { return 17; }
}
$GLOBALS['rateAllowed'] = true;
$next->calls = 0;
flowCheck((new RateLimitMiddleware())->process(new Request('GET', '/turkmod/api/test'), $next)->getStatusCode() === 204 && $next->calls === 1, 'Allowed request blocked');
flowCheck(($GLOBALS['rateHits'] ?? 0) === 1, 'Allowed request was not counted');
$GLOBALS['rateAllowed'] = false;
$next->calls = 0;
$blocked = (new RateLimitMiddleware())->process(new Request('GET', '/turkmod/api/test'), $next);
flowCheck($blocked->getStatusCode() === 429 && $next->calls === 0, 'Rate-limited request reached handler');
flowCheck($blocked->getHeader('Retry-After') === '17' && str_contains((string) $blocked->getHeader('Content-Type'), 'application/json'), 'API rate-limit response lost JSON/retry delay');

require_once dirname(__DIR__) . '/includes/src/Engine/Comments/Support/helpers.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE topics (id INTEGER, status TEXT, deleted_at TEXT)');
$pdo->exec('CREATE TABLE comments (id INTEGER, topic_id INTEGER, user_id INTEGER, status TEXT, deleted_at TEXT)');
$pdo->exec("INSERT INTO topics VALUES (1,'published',NULL), (2,'draft',NULL), (3,'published','2026-01-01')");
$pdo->exec("INSERT INTO comments VALUES (1,1,5,'approved',NULL), (2,2,5,'approved',NULL), (3,3,5,'approved',NULL), (4,1,5,'pending',NULL), (5,1,5,'approved','2026-01-01')");
flowCheck(commentPublicTopicExists($pdo, 1), 'Published comment thread unavailable');
foreach ([0, 2, 3, 99] as $id) {
    flowCheck(!commentPublicTopicExists($pdo, $id), 'Nonpublic topic has a comment thread');
}
flowCheck(commentFindPublicComment($pdo, 1, true)['id'] === 1, 'Approved comment cannot be accessed');
flowCheck(commentFindPublicComment($pdo, 4)['id'] === 4, 'Pending own comment cannot be looked up for edit authorization');
foreach ([0, 2, 3, 4, 5, 99] as $id) {
    flowCheck(commentFindPublicComment($pdo, $id, true) === null, 'Nonpublic comment can be reacted/replied to');
}
$brokenPdo = new PDO('sqlite::memory:');
flowCheck(flowRejects(fn () => commentPublicTopicExists($brokenPdo, 1)), 'Missing topic schema is hidden');
flowCheck(flowRejects(fn () => commentFindPublicComment($brokenPdo, 1)), 'Missing comment schema is hidden');

use App\Engine\Comments\CommentInput;
foreach ([
    ['application/json', '{"topic_id":1,"body":"Merhaba","_token":"test"}', []],
    ['application/x-www-form-urlencoded', '', ['topic_id' => '1', 'body' => 'Merhaba', '_token' => 'test']],
    ['multipart/form-data; boundary=test', '', ['topic_id' => '1', 'body' => 'Merhaba', '_token' => 'test']],
] as [$type, $body, $form]) {
    $parsed = CommentInput::parse($type, $body, $form);
    flowCheck($parsed['topic_id'] === 1 && $parsed['body'] === 'Merhaba' && $parsed['action'] === 'add', 'Valid comment input rejected/changed');
}
foreach (['{broken', '[]', 'null', '"text"', '{"body":[]}', '{"topic_id":[1]}', '{"topic_id":"1junk"}', '{"body":12}', '{"_token":{}}', '{"action":"typo"}', '{"comment_id":-1}', '{"id":true}', '{"topic_id":1.0}', '{"action":null}'] as $json) {
    flowCheck(flowRejects(fn () => CommentInput::parse('application/json', $json, ['body' => 'must not be used'])), 'Malformed JSON comment became a form submission: ' . $json);
}
flowCheck(flowRejects(fn () => CommentInput::parse('application/x-www-form-urlencoded', '', ['body' => "\xB1\x31"])), 'Invalid UTF-8 accepted');
flowCheck(flowRejects(fn () => CommentInput::assertBodyLength('ab', ['comment_min_length' => 3])), 'Short edit body bypassed limit');
flowCheck(flowRejects(fn () => CommentInput::assertBodyLength('abcd', ['max_comment_length' => 3])), 'Long edit body bypassed limit');
CommentInput::assertBodyLength('şğ', ['comment_min_length' => 2, 'max_comment_length' => 2]);
flowCheck(true, 'Unicode body length changed');

require_once dirname(__DIR__) . '/includes/src/Engine/Auth/Support/helpers.php';
foreach (['/giris/', '/kayit/', '/sifremi-unuttum/', '/sifre-sifirla/', '/cikis/', '/turkmod/giris/?redirect=%2F', '/%67iris/', '/GIRIS/'] as $target) {
    flowCheck(loginSafeRedirect($target, '/home') === '/home', 'Auth redirect can loop: ' . $target);
}
flowCheck(loginSafeRedirect('/konu/example-1/?page=2', '/home') === '/konu/example-1/?page=2', 'Valid content redirect changed');

$pdo->exec('ALTER TABLE topics ADD COLUMN comment_count INTEGER DEFAULT 0');
$pdo->exec('CREATE TABLE comment_reports (comment_id INTEGER, status TEXT)');
$pdo->exec("UPDATE topics SET comment_count = 1 WHERE id = 1");
$pdo->exec("INSERT INTO comment_reports VALUES (1,'resolved'), (1,'resolved'), (1,'resolved')");
if (!function_exists('topicDownloadRevokeAccessGrant')) {
    function topicDownloadRevokeAccessGrant(?PDO $pdo, int $commentId, string $reason): bool { $GLOBALS['revoked'][] = $commentId; return true; }
    function eventsReverseActivityPoints(PDO $pdo, int $userId, string $event, string $entity, int $id, string $reason): array { $GLOBALS['reversed'][] = $id; return []; }
    function invalidatePublicContentCache(): void { $GLOBALS['invalidated'] = ($GLOBALS['invalidated'] ?? 0) + 1; }
}
$pdo->beginTransaction();
$comment = commentFindPublicComment($pdo, 1, true);
flowCheck(!commentApplyReportThreshold($pdo, $comment, 2), 'Resolved reports hide an approved comment');
$pdo->exec("INSERT INTO comment_reports VALUES (1,'open')");
flowCheck(!commentApplyReportThreshold($pdo, $comment, 2), 'Comment hidden before report threshold');
$pdo->exec("INSERT INTO comment_reports VALUES (1,'open')");
flowCheck(commentApplyReportThreshold($pdo, $comment, 2), 'Report threshold did not hide comment');
flowCheck($pdo->query('SELECT comment_count FROM topics WHERE id=1')->fetchColumn() === 0, 'Hidden comment still counted');
flowCheck($pdo->query('SELECT status FROM comments WHERE id=1')->fetchColumn() === 'pending', 'Reported comment remains public');
flowCheck(!commentApplyReportThreshold($pdo, $comment, 2), 'Repeated reports decrement the count twice');
flowCheck(($GLOBALS['revoked'] ?? []) === [1] && ($GLOBALS['reversed'] ?? []) === [1], 'Hidden comment retains grant/points');
$pdo->rollBack();
flowCheck($pdo->query('SELECT status FROM comments WHERE id=1')->fetchColumn() === 'approved', 'Report transaction cannot be rolled back');
flowCheck($pdo->query('SELECT comment_count FROM topics WHERE id=1')->fetchColumn() === 1, 'Report rollback lost comment count');

// Temporary InnoDB tables shadow names only for this connection; real content stays untouched.
$mysql = App\Core\DatabaseConnection::connection();
if (!$mysql instanceof PDO) { throw new RuntimeException('MySQL regression database unavailable.'); }
try {
    $mysql->exec('CREATE TEMPORARY TABLE topics (id INT PRIMARY KEY, status VARCHAR(16), deleted_at DATETIME NULL, comment_count INT) ENGINE=InnoDB');
    $mysql->exec('CREATE TEMPORARY TABLE comments (id INT PRIMARY KEY, topic_id INT, user_id INT, status VARCHAR(16), deleted_at DATETIME NULL) ENGINE=InnoDB');
    $mysql->exec('CREATE TEMPORARY TABLE comment_reports (comment_id INT, status VARCHAR(16)) ENGINE=InnoDB');
    $mysql->exec("INSERT INTO topics VALUES (1,'published',NULL,1)");
    $mysql->exec("INSERT INTO comments VALUES (1,1,5,'approved',NULL)");
    $mysql->beginTransaction();
    $lock = $mysql->prepare("SELECT id FROM comments WHERE id = ? AND status = 'approved' AND deleted_at IS NULL FOR UPDATE");
    $lock->execute([1]);
    flowCheck((int) $lock->fetchColumn() === 1, 'InnoDB report row lock failed');
    $mysql->exec("INSERT INTO comment_reports VALUES (1,'open'), (1,'open')");
    flowCheck(commentApplyReportThreshold($mysql, commentFindPublicComment($mysql, 1, true), 2), 'InnoDB report moderation failed');
    flowCheck((int) $mysql->query('SELECT comment_count FROM topics WHERE id=1')->fetchColumn() === 0, 'InnoDB moderation count incorrect');
    $mysql->rollBack();
    flowCheck($mysql->query('SELECT status FROM comments WHERE id=1')->fetchColumn() === 'approved', 'InnoDB report rollback failed');
    flowCheck((int) $mysql->query('SELECT COUNT(*) FROM comment_reports')->fetchColumn() === 0, 'InnoDB report insert did not roll back');
    flowCheck((int) $mysql->query('SELECT comment_count FROM topics WHERE id=1')->fetchColumn() === 1, 'InnoDB counter rollback failed');
} finally {
    if ($mysql->inTransaction()) { $mysql->rollBack(); }
    foreach (['comment_reports', 'comments', 'topics'] as $table) {
        $mysql->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table);
    }
}
session_destroy();

if ($failures !== []) {
    foreach ($failures as $failure) { fwrite(STDERR, "FAIL: {$failure}\n"); }
    exit(1);
}
echo "PASS: {$checks} security flow regression checks.\n";
