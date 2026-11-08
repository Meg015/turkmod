<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';

// All writes use a disposable database; realtime delivery is captured in memory.
final class WorkflowFixtureBroadcast
{
    public static array $events = [];
    public static function publish(array|int $users, array $payload): bool
    {
        self::$events[] = $payload;
        return true;
    }
}
class_alias(WorkflowFixtureBroadcast::class, App\Core\Realtime\WebSocketBroadcaster::class);
function getAdminSettings(?PDO $pdo): array { return $GLOBALS['workflowSettings']; }
function appLogException(Throwable $e, array $context = []): void { $GLOBALS['workflowErrors'][] = $e->getMessage(); }

use App\Modules\Messages\Services\MessageService;
use App\Modules\Notifications\Services\NotificationCenterService;
use App\Modules\TopicWorkflow\Services\TopicModerationNotificationService;

$GLOBALS['workflowSettings'] = ['notif_email_channel_ready' => '1'];
$GLOBALS['workflowErrors'] = [];
$GLOBALS['baseUri'] = '/turkmod';
$checks = 0;
function workflowCheck(bool $condition, string $message): void
{
    $GLOBALS['checks']++;
    if (!$condition) throw new RuntimeException($message);
}
$dsn = getenv('WORKFLOW_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=turkmod;charset=utf8mb4';
$user = getenv('WORKFLOW_TEST_USER') ?: 'root';
$password = getenv('WORKFLOW_TEST_PASSWORD') ?: '';
$source = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'turkmod_workflow_test_' . bin2hex(random_bytes(8));
if (!preg_match('/^turkmod_workflow_test_[a-f0-9]{16}$/D', $database)) throw new RuntimeException('Invalid fixture database');
$source->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $db = new PDO(preg_replace('/dbname=[^;]+/', 'dbname=' . $database, $dsn), $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($source->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row) {
        $table = str_replace('`', '``', $row[0]);
        $definition = $source->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        $db->exec($definition);
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $db->exec("INSERT INTO users (id,username,email,password) VALUES (1,'FixtureAdmin','admin@example.test','!'),(2,'FixtureAuthor','author@example.test','!'),(3,'FixturePeer','peer@example.test','!')");
    $db->exec("INSERT INTO categories (id,name,slug) VALUES (1,'Fixture','fixture')");
    $db->exec("INSERT INTO topics (id,category_id,author_id,title,slug,status) VALUES (10,1,2,'Türkçe Mod','fixture-mod','revision')");
    $topic = ['id' => 10, 'author_id' => 2, 'title' => 'Türkçe Mod'];
    $note = 'Açıklamayı ve indirme bağlantısını düzeltin.';
    $link = '/turkmod/konu-duzenle?id=10';
    $moderation = new TopicModerationNotificationService();
    $count = static fn (string $table): int => (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    $inAppCount = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM notifications WHERE delivery_channels LIKE '%\"in_app\"%'")->fetchColumn();
    for ($mask = 0; $mask < 8; $mask++) {
        $channels = [];
        foreach (['in_app', 'email', 'message'] as $bit => $channel) if ($mask & (1 << $bit)) $channels[] = $channel;
        $before = [$inAppCount(), $count('notification_email_queue'), $count('message_messages'), count(WorkflowFixtureBroadcast::$events)];
        $delivery = $moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, $channels);
        workflowCheck($delivery['failed'] === [] && $delivery['sent'] === $channels, 'Channel matrix failed at mask ' . $mask . ': ' . json_encode($delivery));
        workflowCheck($inAppCount() - $before[0] === (int) in_array('in_app', $channels, true), 'Unselected bell channel used');
        workflowCheck($count('notification_email_queue') - $before[1] === (int) in_array('email', $channels, true), 'Unselected email channel used');
        workflowCheck($count('message_messages') - $before[2] === (int) in_array('message', $channels, true), 'Unselected message channel used');
        workflowCheck(count(WorkflowFixtureBroadcast::$events) - $before[3] === (int) in_array('in_app', $channels, true) + (int) in_array('message', $channels, true), 'Email-only delivery emitted a bell event');
    }
    workflowCheck((int) $db->query("SELECT COUNT(*) FROM notifications WHERE event_key = 'direct_message_received'")->fetchColumn() === 0, 'Revision private message fanned out to other channels');
    foreach ($db->query('SELECT body,link FROM notification_email_queue') as $email) {
        workflowCheck(str_contains($email['body'], $note) && $email['link'] === $link, 'Email lost note or edit link');
    }
    foreach ($db->query('SELECT body FROM message_messages') as $message) {
        workflowCheck(str_contains($message['body'], $note) && str_contains($message['body'], $link), 'Private message lost note or link');
    }
    $GLOBALS['workflowSettings']['notif_email_channel_ready'] = '0';
    $delivery = $moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, ['in_app', 'email']);
    workflowCheck($delivery === ['sent' => ['in_app'], 'failed' => ['email']], 'Disabled email channel was silently reported as sent');
    $GLOBALS['workflowSettings']['notif_email_channel_ready'] = '1';
    $db->exec("INSERT INTO user_settings (user_id,setting_key,setting_value) VALUES (2,'notif_group_email','0')");
    workflowCheck($moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, ['email'])['failed'] === ['email'], 'Disabled recipient preference ignored');
    $db->exec('DELETE FROM user_settings');
    $db->exec("UPDATE users SET email='invalid' WHERE id=2");
    workflowCheck($moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, ['email'])['failed'] === ['email'], 'Email queue failure reported as delivery');
    $db->exec("UPDATE users SET email='author@example.test' WHERE id=2");
    $before = $inAppCount();
    $moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, ['in_app']);
    $moderation->sendRevision($db, $topic, 1, 'Yönetim', $note, $link, ['in_app']);
    workflowCheck($inAppCount() - $before === 2, 'Later revision was suppressed by the first revision dedupe key');
    workflowCheck($moderation->sendRevision($db, $topic, 2, 'Yazar', $note, $link, ['message'])['failed'] === ['message'], 'Self-message was reported as delivered');

    $messages = new MessageService();
    $thread = $messages->getOrCreateThreadByUser($db, 1, 3);
    $insert = $db->prepare('INSERT INTO message_messages (thread_id,sender_user_id,body) VALUES (?,?,?)');
    $ids = [];
    for ($i = 1; $i <= 350; $i++) {
        $insert->execute([$thread, 1, 'Fixture message ' . $i]);
        $ids[] = (int) $db->lastInsertId();
    }
    $last = end($ids);
    $db->prepare('UPDATE message_threads SET last_message_id=?,last_message_at=NOW() WHERE id=?')->execute([$last, $thread]);
    $notice = $db->prepare("INSERT INTO notifications (user_id,title,message,type,event_key,entity_type,entity_id,dedupe_key,delivery_channels) VALUES (3,'Message','Fixture','info','direct_message_received','message_thread',?,?, '[\"in_app\"]')");
    $notice->execute([$thread, 'direct_message_received:' . $last]);
    $readNotice = (int) $db->lastInsertId();
    $notice->execute([$thread, 'direct_message_received:' . ($last + 1)]);
    $futureNotice = (int) $db->lastInsertId();
    $notice->execute([999, 'other-thread']);
    $otherNotice = (int) $db->lastInsertId();
    $payload = $messages->openThread($db, 3, $thread);
    workflowCheck(count($payload['messages']) === 300 && $payload['messages'][0]['id'] === $ids[50], 'Long thread does not show latest 300 in chronological order');
    workflowCheck(end($payload['messages'])['id'] === $last, 'Newest message missing');
    $history = $messages->getHistory($db, 3, $thread, $payload['messages'][0]['id']);
    workflowCheck(count($history) === 50 && $history[0]['id'] === $ids[0] && end($history)['id'] === $ids[49], 'History lost earlier messages');
    $reads = $db->query('SELECT notification_id FROM notification_reads WHERE user_id=3')->fetchAll(PDO::FETCH_COLUMN);
    workflowCheck(in_array($readNotice, array_map('intval', $reads), true), 'Open thread bell was not marked read');
    workflowCheck(!in_array($futureNotice, array_map('intval', $reads), true), 'Concurrent future message marked read before retrieval');
    workflowCheck(!in_array($otherNotice, array_map('intval', $reads), true), 'Other conversation bell was marked read');
    $dropdown = (new NotificationCenterService())->dropdownPayload($db, 3, $thread);
    workflowCheck($dropdown['unread_count'] === 1 && array_column($dropdown['latest'], 'id') === [$otherNotice], 'Open conversation still appears in the bell list');
    workflowCheck((new NotificationCenterService())->dropdownPayload($db, 3, 999)['unread_count'] === 2, 'Nonparticipant conversation can filter bell notifications');
    workflowCheck($messages->openThread($db, 2, $thread) === null, 'Nonparticipant can read the thread');
    $insert->execute([$thread, 1, 'Newest after initial load']);
    $newId = (int) $db->lastInsertId();
    $db->prepare('UPDATE message_threads SET last_message_id=?,last_message_at=NOW() WHERE id=?')->execute([$newId, $thread]);
    $payload = $messages->openThread($db, 3, $thread);
    workflowCheck(end($payload['messages'])['id'] === $newId, 'Refresh after 300 messages remains stale');
    workflowCheck((new NotificationCenterService())->dropdownPayload($db, 3)['unread_count'] === 1, 'Bell count did not preserve only the unrelated unread notification');
    $edited = $messages->editMessage($db, $ids[0], 1, "  <b>Türkçe düzenleme</b>\r\nİkinci satır  ");
    workflowCheck(!empty($edited['success']) && $edited['updated_message']['body'] === "Türkçe düzenleme\nİkinci satır", 'Edited message is not normalized or returned to the client');
    workflowCheck(empty($messages->editMessage($db, $ids[0], 1, str_repeat('ş', 4001))['success']), 'Overlength edited message was accepted');
    workflowCheck(empty($messages->editMessage($db, $ids[0], 1, '<b></b>')['success']), 'Markup-only empty edited message was accepted');
    workflowCheck(empty($messages->editMessage($db, $ids[0], 3, 'Not mine')['success']), 'Other participant can edit the sender message');
    $deleted = $messages->deleteMessage($db, $ids[1], 1);
    workflowCheck(!empty($deleted['success']) && $deleted['updated_message']['is_deleted'] === true && $deleted['updated_message']['id'] === $ids[1], 'Deleted message patch is missing');
    workflowCheck(empty($messages->editMessage($db, $ids[1], 1, 'Revive')['success']), 'Deleted message can be edited');
    $olderMessages = $messages->getHistory($db, 3, $thread, $ids[50]);
    workflowCheck($olderMessages[0]['body'] === "Türkçe düzenleme\nİkinci satır" && !empty($olderMessages[1]['is_deleted']), 'History does not preserve older edit and deletion');
    workflowCheck($GLOBALS['workflowErrors'] === [], 'Unexpected service exceptions: ' . json_encode($GLOBALS['workflowErrors']));
    echo 'PASS: ' . $checks . " workflow regression checks.\n";
} finally {
    $source->exec('DROP DATABASE `' . $database . '`');
}
