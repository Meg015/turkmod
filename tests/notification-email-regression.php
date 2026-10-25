<?php
declare(strict_types=1);

// Run: php tests/notification-email-regression.php
// Real MySQL queries use connection-local TEMPORARY tables; SMTP stays on loopback.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';

final class NotificationFixtureBroadcast
{
    public static function publish(array|int $userIds, array $payload): bool { return true; }
}
class_alias(NotificationFixtureBroadcast::class, App\Core\Realtime\WebSocketBroadcaster::class);
function getAdminSettings(?PDO $pdo): array
{
    return $pdo ? $pdo->query('SELECT setting_key, setting_value FROM admin_settings')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
}
function appPublicBaseUrl(...$args): string { return 'https://turkmod.test/turkmod'; }
function topicUrl(string $slug, int $id): string { return '/turkmod/konu/' . $slug . '-' . $id; }
function topicUrlForRow(array $row): string { return topicUrl($row['slug'], (int) $row['id']); }
function routeCanonicalPath(string $route): string { return '/turkmod/konu'; }
function appLogException(Throwable $exception, array $context = []): void {}
function appLog(?PDO $pdo, string $level, string $channel, string $message, array $context = []): void
{
    $GLOBALS['notificationFixtureLogs'][] = compact('level', 'channel', 'message', 'context');
}
require_once dirname(__DIR__) . '/includes/notifications.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Email/Support/helpers.php';

use App\Engine\Email\AccountEmailService;
use App\Engine\Email\AdminEmailService;
use App\Modules\Notifications\Listeners\NotificationEventSubscriber;
use App\Modules\Notifications\Services\NotificationEmailQueueService;
use App\Modules\Notifications\Services\NotificationTemplateService;
use App\Modules\TopicWorkflow\Events\TopicWorkflowEvent;

$checks = 0;
function emailAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    $GLOBALS['checks']++;
}
function emailReject(callable $action, string $message): void
{
    try { $action(); } catch (RuntimeException $e) { $GLOBALS['checks']++; return; }
    throw new RuntimeException($message);
}
function emailSetting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$key, $value]);
}

$pdo = App\Core\DatabaseConnection::connection();
if (!$pdo) { throw new RuntimeException('MySQL connection required for isolated regression tests.'); }
$templates = new NotificationTemplateService();
$savedRows = $pdo->query('SELECT * FROM notification_templates')->fetchAll(PDO::FETCH_ASSOC);
$savedTemplateSettings = $pdo->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'account_email_%' OR setting_key LIKE 'admin_email_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$storedTemplates = [];
foreach ($savedRows as $row) {
    $template = $templates->normalizeRow($row);
    $storedTemplates[$row['template_key']] = $template;
    emailAssert($templates->validate($template) === [], 'Stored template invalid: ' . $row['template_key']);
    emailAssert($templates->emailCopyErrors($template) === [], 'Stored email incomplete: ' . $row['template_key']);
}

// Tables disappear when this connection closes; existing rows are never changed.
foreach (['users', 'topics', 'user_settings', 'admin_settings', 'notifications', 'notification_templates', 'notification_email_queue', 'notification_dispatch_suppression_logs', 'email_logs'] as $table) {
    $definition = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
    $definition = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition);
    $definition = implode("\n", array_filter(explode("\n", $definition), static fn(string $line): bool => !preg_match('/^\s*(?:CONSTRAINT|FULLTEXT)/', $line)));
    $definition = preg_replace('/,\n\)/', "\n)", $definition);
    $definition = preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition);
    $pdo->exec($definition);
}
$pdo->exec("INSERT INTO users (id, username, email, password) VALUES (1, 'TestYazar', 'author@turkmod.test', 'fixture-only'), (2, 'TestAdmin', 'admin@turkmod.test', 'fixture-only')");
$pdo->exec("INSERT INTO topics (id, category_id, author_id, title, slug, status) VALUES (1, 1, 1, 'Türkçe Örnek Mod', 'ornek-mod', 'published')");
$templates->ensureSchema($pdo);
$defaults = $templates->defaults();
$editorTemplates = array_replace($defaults, $storedTemplates);
emailAssert(isset($defaults['topic_approved'], $defaults['topic_published']), 'Topic templates missing');

$capture = tempnam(dirname(__DIR__) . '/storage/cache', 'notification-smtp-');
if ($capture === false) { throw new RuntimeException('SMTP capture file could not be created.'); }
$server = proc_open(['node', __DIR__ . '/fixtures/notification-smtp-server.cjs', $capture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
if (!is_resource($server)) { throw new RuntimeException('SMTP fixture did not start.'); }
stream_set_timeout($pipes[1], 10);
$serverInfo = json_decode((string) fgets($pipes[1]), true);
if (empty($serverInfo['port'])) { throw new RuntimeException('SMTP fixture port unavailable.'); }
$settings = [
    'site_name' => 'Türk Mod Test', 'notif_email_channel_ready' => '0',
    'mail_driver' => 'smtp', 'smtp_host' => '127.0.0.1', 'smtp_port' => (string) $serverInfo['port'],
    'smtp_username' => '', 'smtp_password' => '', 'smtp_encryption' => 'none',
    'mail_from_address' => 'sender@turkmod.test', 'mail_from_name' => 'Türk Mod Test',
    'notif_event_favorites_enabled' => '1',
];
foreach ($settings as $key => $value) { emailSetting($pdo, $key, $value); }
foreach ($savedTemplateSettings as $key => $value) { emailSetting($pdo, $key, (string) $value); }
$queue = notificationEmailQueueService();

try {
    foreach ($editorTemplates as $key => $template) {
        emailAssert($templates->validate($defaults[$key] ?? $template) === [], 'Default invalid: ' . $key);
        $preview = $templates->emailPreview($template);
        emailAssert(!str_contains(implode('', $preview), '{{'), 'Unresolved variable: ' . $key);
        emailAssert($preview['subject'] !== '' && $preview['body'] !== '', 'Empty email: ' . $key);
        // Real SMTP DATA with automatic channel off, template off, unsaved editor contents.
        $template['email_enabled'] = 0;
        $template['is_active'] = 0;
        $template['email_subject_template'] .= ' - düzenlenmiş';
        emailAssert($queue->sendTest($pdo, 2, $key, $template, $settings), 'Direct SMTP test failed: ' . $key);
    }
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn() === 0, 'Direct tests incorrectly entered queue');
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0, 'Direct tests created user notifications');
    $invalid = $defaults['topic_published'];
    $invalid['email_body_template'] = '';
    emailReject(fn() => $queue->sendTest($pdo, 2, 'topic_published', $invalid, $settings), 'Empty test body accepted');
    emailReject(fn() => $queue->sendTest($pdo, 99999, 'topic_published', $defaults['topic_published'], $settings), 'Missing recipient accepted');
    $invalid = $defaults['topic_published'];
    $invalid['email_subject_template'] = '{{unknown_variable}}';
    emailReject(fn() => $queue->sendTest($pdo, 2, 'topic_published', $invalid, $settings), 'Unknown test variable accepted');
    foreach (['text', 'html'] as $format) {
        $template = $defaults['topic_published'];
        if ($format === 'html') { $template['email_body_template'] = '<p>{{topic_title}}</p>'; }
        $preview = $templates->emailPreview($template, array_replace($templates->samplePayload(), ['topic_title' => '<script>unsafe</script> & Türkçe']));
        $html = $queue->buildHtml(['subject' => $preview['subject'], 'body' => $preview['body'], 'metadata_json' => json_encode(['body_format' => $templates->emailBodyFormat($template)])]);
        emailAssert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;unsafe&lt;/script&gt;'), 'Topic text became executable email markup: ' . $format);
    }

    // Migration enables only the two requested events, preserving custom copy and other choices.
    $pdo->exec("UPDATE notification_templates SET email_subject_template = 'Özel onay: {{topic_title}}', email_enabled = 0 WHERE template_key = 'topic_approved'");
    $migration = require dirname(__DIR__) . '/includes/src/Modules/Notifications/Database/migrations/2026_10_25_0001_enable_topic_publication_email.php';
    $migration->up($pdo);
    $migration->up($pdo);
    emailAssert(getAdminSettings($pdo)['notif_email_channel_ready'] === '1', 'Queue not enabled');
    emailAssert($templates->get($pdo, 'topic_approved')['email_subject_template'] === 'Özel onay: {{topic_title}}', 'Custom copy overwritten');
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_templates WHERE email_enabled = 1')->fetchColumn() === 2, 'Unrelated event emails enabled');

    $topic = $pdo->query('SELECT * FROM topics WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    emailAssert(notificationDispatchTopicPublished($pdo, $topic, 'draft', 2), 'Admin publication did not dispatch');
    $row = $pdo->query('SELECT * FROM notification_email_queue LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    emailAssert($row['recipient_email'] === 'author@turkmod.test', 'Publication addressed to wrong user');
    emailAssert(str_contains($row['body'], 'TestYazar') && str_contains($row['body'], 'Türk Mod Test'), 'Real recipient/site missing');
    emailAssert(str_contains($queue->buildHtml($row), 'https://turkmod.test/turkmod/konu/ornek-mod-1'), 'Topic link duplicated base path or missing');
    emailAssert(str_contains($queue->buildHtml($row), 'Türkçe Örnek Mod&quot; konunuz yayımlandı.'), 'Queued preheader missing');
    emailAssert(!notificationDispatchTopicPublished($pdo, $topic, 'draft', 2), 'Repeated publication duplicated');
    emailAssert(!notificationDispatchTopicPublished($pdo, $topic, 'published', 2), 'Ordinary edit dispatched');

    $subscriber = new NotificationEventSubscriber();
    $subscriber->handle(new TopicWorkflowEvent('topic.published', ['pdo' => $pdo, 'topic_id' => 1, 'author_id' => 1, 'title' => $topic['title']]));
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn() === 1, 'Subscriber/admin dedupe mismatch');
    $subscriber->handle(new TopicWorkflowEvent('topic.published', ['pdo' => $pdo, 'topic_id' => 2, 'author_id' => 1, 'editor_user_id' => 1, 'title' => 'Kendi yayınım', 'previous_status' => 'draft']));
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn() === 2, 'Self-publication was suppressed');
    $subscriber->handle(new TopicWorkflowEvent('topic.published', ['pdo' => $pdo, 'topic_id' => 3, 'author_id' => 1, 'title' => 'Düzenleme', 'previous_status' => 'published']));
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn() === 2, 'Published edit generated email');
    emailAssert(notificationDispatch($pdo, 'topic_approved', 1, 2, 'topic', 4, ['topic_title' => 'Onaylanan Mod', 'link' => '/turkmod/konu/onay-4']), 'Approval did not dispatch');

    $eventCapture = new class implements App\Core\Events\EventDispatcher {
        public array $events = [];
        public function dispatch(App\Core\Events\Event $event): App\Core\Events\Event { $this->events[] = $event; return $event; }
    };
    $edit = new App\Modules\TopicWorkflow\Services\TopicEditService($eventCapture);
    $emit = new ReflectionMethod($edit, 'emitEvents');
    $emit->invoke($edit, $pdo, 10, 1, 'published', 1, 'test', 'Başlık', ['author_id' => 1, 'status' => 'draft']);
    emailAssert(count($eventCapture->events) === 2 && $eventCapture->events[1]->payload()['author_id'] === 1, 'Public edit omitted author/publication event');
    $eventCapture->events = [];
    $emit->invoke($edit, $pdo, 10, 1, 'published', 1, 'test', 'Başlık', ['author_id' => 1, 'status' => 'published']);
    emailAssert(count($eventCapture->events) === 1 && $eventCapture->events[0]->name() === 'topic.updated', 'Ordinary edit re-emitted publication');

    // Preference opt-out keeps the in-app channel and omits email.
    $before = (int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn();
    $pdo->exec("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (1, 'notif_email_event_topic_published', '0')");
    emailAssert(notificationDispatchTopicPublished($pdo, array_replace($topic, ['id' => 5]), 'draft', 2), 'In-app channel incorrectly suppressed');
    emailAssert((int) $pdo->query('SELECT COUNT(*) FROM notification_email_queue')->fetchColumn() === $before, 'Email preference ignored');
    $pdo->exec("DELETE FROM user_settings WHERE user_id = 1");

    // Every event dispatches through the real queue and SMTP worker.
    $pdo->exec('UPDATE notification_templates SET email_enabled = 1');
    foreach (notificationPreferenceService()->eventDefinitions() as $key => $definition) {
        $pdo->prepare('INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$definition['setting_key'], '1']);
        emailAssert(notificationDispatch($pdo, $key, 1, 2, 'fixture', 100, $templates->samplePayload()), 'Event dispatch failed: ' . $key);
    }
    $queuedBefore = (int) $pdo->query("SELECT COUNT(*) FROM notification_email_queue WHERE status = 'queued'")->fetchColumn();
    $dry = $queue->process($pdo, 100, true);
    emailAssert($dry['dry_run'] === $queuedBefore && $dry['sent'] === 0, 'Dry run sent messages');
    $sent = $queue->process($pdo, 100);
    emailAssert($sent['sent'] === $queuedBefore && $sent['failed'] === 0 && $sent['errors'] === [], 'SMTP queue delivery failed');
    emailAssert($queue->process($pdo, 100)['selected'] === 0, 'Sent messages selected again');

    foreach (AccountEmailService::catalog() as $key => $template) {
        emailAssert((new AccountEmailService($pdo))->send($key, 'account@turkmod.test', [
            'username' => 'HesapTest', 'action_url' => '/turkmod/islem?token=fixture', 'expires_minutes' => '30',
            'old_email' => 'old@turkmod.test', 'new_email' => 'new@turkmod.test', 'actor_context' => 'Yönetici',
        ], ['force' => true, 'enabled' => '1', 'settings' => $settings]), 'Account SMTP failed: ' . $key);
    }
    foreach (AdminEmailService::catalog() as $key => $template) {
        emailAssert((new AdminEmailService($pdo))->sendTest($key, 'admin@turkmod.test', [], ['force' => true, 'settings' => $settings]), 'Admin SMTP failed: ' . $key);
    }

    // Failure must be visible to the test button and to worker retry accounting.
    $pdo->exec("UPDATE users SET email = 'reject@turkmod.test' WHERE id = 2");
    emailAssert(!$queue->sendTest($pdo, 2, 'topic_published', $defaults['topic_published'], $settings), 'SMTP rejection reported success');
    emailAssert(appLastMailResult()['smtp_code'] === 550, 'SMTP rejection diagnostic lost');
    $pdo->exec("UPDATE users SET email = 'admin@turkmod.test' WHERE id = 2");
    $pdo->exec("UPDATE notification_email_queue SET status = 'queued', attempts = 0, max_attempts = 2, recipient_email = 'fail-data@turkmod.test', available_at = NOW() WHERE id = 1");
    $retry = $queue->process($pdo, 100);
    emailAssert($retry['requeued'] === 1 && $retry['sent'] === 0, 'Temporary SMTP failure was not retried');
    $pdo->exec("UPDATE notification_email_queue SET available_at = NOW() WHERE id = 1");
    $failed = $queue->process($pdo, 100);
    emailAssert($failed['failed'] === 1, 'Retry limit was not enforced');
    $failedRow = $pdo->query('SELECT * FROM notification_email_queue WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    emailAssert((int) $failedRow['attempts'] === 2 && $failedRow['locked_at'] === null && str_contains($failedRow['error_message'], '451'), 'Failure state/diagnostic invalid');
    emailAssert($queue->process($pdo, 100)['selected'] === 0, 'Exhausted failure selected again');

    // An overlapping worker must not consume a row another worker rescheduled.
    $staleSnapshot = array_replace($failedRow, ['attempts' => 0, 'max_attempts' => 3]);
    $pdo->exec("UPDATE notification_email_queue SET status = 'queued', attempts = 1, max_attempts = 3, available_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = 1");
    $attemptedSends = 0;
    $sender = static function (array $row) use (&$attemptedSends): bool { $attemptedSends++; return true; };
    $raceResult = ['selected' => 1, 'sent' => 0, 'failed' => 0, 'requeued' => 0, 'dry_run' => 0, 'errors' => []];
    (new ReflectionMethod($queue, 'processRow'))->invokeArgs($queue, [$pdo, $staleSnapshot, 1, $sender, &$raceResult]);
    emailAssert($attemptedSends === 0, 'Overlapping worker bypassed retry delay');
    emailAssert((int) $pdo->query('SELECT attempts FROM notification_email_queue WHERE id = 1')->fetchColumn() === 1, 'Overlapping worker overwrote retry counter');

    $pdo->exec("UPDATE notification_email_queue SET status = 'processing', locked_at = DATE_SUB(NOW(), INTERVAL 20 MINUTE), available_at = NOW() WHERE id = 1");
    $queue->process($pdo, 100, true, $sender);
    emailAssert($pdo->query('SELECT status FROM notification_email_queue WHERE id = 1')->fetchColumn() === 'processing', 'Dry run changed a stale processing row');

    $custom = array_replace($defaults['topic_published'], [
        'title_template' => 'Özel site başlığı', 'message_template' => 'Özel site mesajı',
        'in_app_enabled' => 0, 'email_enabled' => 0, 'email_subject_template' => 'Özel e-posta konusu',
    ]);
    $templates->save($pdo, 'topic_published', $custom);
    emailAssert($templates->reset($pdo, 'topic_published', 'email'), 'Email-only reset failed');
    $resetTemplate = $templates->get($pdo, 'topic_published');
    emailAssert($resetTemplate['title_template'] === $custom['title_template'] && (int) $resetTemplate['in_app_enabled'] === 0, 'Email reset overwrote in-app configuration');
    emailAssert((int) $resetTemplate['email_enabled'] === 0 && $resetTemplate['email_subject_template'] === $defaults['topic_published']['email_subject_template'], 'Email reset changed delivery preference or failed to restore copy');
    $templates->save($pdo, 'topic_published', $custom);
    emailAssert(notificationTemplateReset($pdo, 'topic_published', 'site'), 'Site-only reset failed');
    $resetTemplate = $templates->get($pdo, 'topic_published');
    emailAssert($resetTemplate['email_subject_template'] === $custom['email_subject_template'] && (int) $resetTemplate['email_enabled'] === 0, 'Site reset overwrote email configuration');
    emailAssert($resetTemplate['title_template'] === $defaults['topic_published']['title_template'] && (int) $resetTemplate['in_app_enabled'] === 0, 'Site reset changed preference or failed to restore copy');
    emailAssert(!$templates->reset($pdo, 'topic_published', 'invalid'), 'Unknown reset channel accepted');

    $captures = array_map(static fn(string $line): array => json_decode($line, true), file($capture, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    foreach ($captures as $message) {
        emailAssert(str_contains($message['message'], 'multipart/alternative'), 'Plain/HTML MIME alternative missing');
        emailAssert(str_contains($message['message'], 'Content-Type: text/plain') && str_contains($message['message'], 'Content-Type: text/html'), 'MIME parts missing');
        emailAssert(!str_contains(quoted_printable_decode($message['message']), '{{'), 'Unresolved token reached SMTP');
    }
    emailAssert(count($captures) === count($defaults) + $queuedBefore + count(AccountEmailService::catalog()) + count(AdminEmailService::catalog()), 'SMTP capture count mismatch');
    echo json_encode(['checks' => $checks, 'stored_templates' => count($savedRows), 'event_templates' => count($defaults), 'account_templates' => count(AccountEmailService::catalog()), 'admin_templates' => count(AdminEmailService::catalog()), 'smtp_messages' => count($captures), 'result' => 'PASS'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    proc_terminate($server);
    foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    proc_close($server);
    if (is_file($capture)) { unlink($capture); }
}
