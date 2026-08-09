<?php

declare(strict_types=1);

use App\Modules\Messages\Database\migrations\Support\MessageSchemaInstaller;
use App\Modules\Messages\Services\MessageService;

require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/src/Modules/Messages/Database/migrations/Support/MessageSchemaInstaller.php';

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "pdo_sqlite extension is required.\n");
    exit(1);
}

function messageClearAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    avatar TEXT NULL,
    status TEXT NOT NULL DEFAULT "active",
    is_banned INTEGER NOT NULL DEFAULT 0,
    deleted_at TEXT NULL,
    last_activity_at TEXT NULL
)');
$pdo->exec("INSERT INTO users (id, username) VALUES (1, 'Kullanici A'), (2, 'Kullanici B'), (3, 'Kullanici C')");

$installer = new MessageSchemaInstaller();
$installer->ensureSchema($pdo, false);
messageClearAssert(
    $installer->columnExists($pdo, 'message_thread_participants', 'cleared_through_message_id'),
    'Participant clear boundary column was not installed.'
);

$service = new MessageService();
$insertMessage = static function (PDO $pdo, int $threadId, int $senderUserId, string $body): int {
    $stmt = $pdo->prepare('INSERT INTO message_messages (thread_id, sender_user_id, body, is_deleted, created_at, updated_at)
        VALUES (:thread_id, :sender_user_id, :body, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    $stmt->execute([
        'thread_id' => $threadId,
        'sender_user_id' => $senderUserId,
        'body' => $body,
    ]);
    $messageId = (int) $pdo->lastInsertId();
    $update = $pdo->prepare('UPDATE message_threads
        SET last_message_id = :message_id, last_message_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = :thread_id');
    $update->execute(['message_id' => $messageId, 'thread_id' => $threadId]);

    return $messageId;
};

$threadId = $service->getOrCreateThreadByUser($pdo, 1, 2);
messageClearAssert($threadId > 0, 'Test thread could not be created.');
$oldIncomingId = $insertMessage($pdo, $threadId, 2, 'Eski gelen mesaj');
$oldOutgoingId = $insertMessage($pdo, $threadId, 1, 'Eski giden mesaj');

messageClearAssert(count($service->listThreads($pdo, 1)) === 1, 'User A should see the thread before clearing.');
messageClearAssert(count($service->listThreads($pdo, 2)) === 1, 'User B should see the thread before clearing.');
messageClearAssert($service->unreadCount($pdo, 1) === 1, 'User A unread count should include the old incoming message.');

$unauthorized = $service->clearThreadForUser($pdo, $threadId, 3);
messageClearAssert($unauthorized['success'] === false, 'A non-participant must not clear another thread.');

$cleared = $service->clearThreadForUser($pdo, $threadId, 1);
messageClearAssert($cleared['success'] === true, 'User A could not clear the thread.');
messageClearAssert((int) $cleared['cleared_through_message_id'] > $oldIncomingId, 'Clear boundary did not reach the latest old message.');
messageClearAssert($service->listThreads($pdo, 1) === [], 'Cleared thread remained in User A list.');
messageClearAssert($service->openThread($pdo, 1, $threadId) === null, 'Old thread URL restored User A history.');
messageClearAssert($service->getHistory($pdo, 1, $threadId, 999999) === [], 'History API exposed cleared messages to User A.');
messageClearAssert($service->unreadCount($pdo, 1) === 0, 'Cleared messages remained unread for User A.');
messageClearAssert($service->editMessage($pdo, $oldOutgoingId, 1, 'Geri getir') ['success'] === false, 'Edit API reached a cleared message.');
messageClearAssert($service->deleteMessage($pdo, $oldOutgoingId, 1)['success'] === false, 'Delete API reached a cleared message.');

$userBThread = $service->openThread($pdo, 2, $threadId);
messageClearAssert(is_array($userBThread), 'User B lost the shared thread.');
messageClearAssert(count($userBThread['messages'] ?? []) === 2, 'User B lost pre-clear message history.');

$newMessageId = $insertMessage($pdo, $threadId, 2, 'Temizlik sonrasi yeni mesaj');
messageClearAssert(count($service->listThreads($pdo, 1)) === 1, 'A new message did not restore the thread for User A.');
$restored = $service->openThread($pdo, 1, $threadId);
messageClearAssert(is_array($restored), 'Restored thread could not be opened by User A.');
messageClearAssert(count($restored['messages'] ?? []) === 1, 'Pre-clear messages returned after a new message.');
messageClearAssert((int) (($restored['messages'][0]['id'] ?? 0)) === $newMessageId, 'The restored thread did not start with the new message.');

$clearedAgain = $service->clearThreadForUser($pdo, $threadId, 1);
messageClearAssert($clearedAgain['success'] === true, 'Repeated clear should be safe.');
messageClearAssert((int) $clearedAgain['cleared_through_message_id'] === $newMessageId, 'Repeated clear did not advance the boundary.');

$emptyThreadId = $service->getOrCreateThreadByUser($pdo, 1, 3);
messageClearAssert($emptyThreadId > 0, 'Empty test thread could not be created.');
$emptyClear = $service->clearThreadForUser($pdo, $emptyThreadId, 1);
messageClearAssert($emptyClear['success'] === true, 'Clearing an empty thread should be safe.');
messageClearAssert($service->threadForUser($pdo, 1, $emptyThreadId) === null, 'An empty cleared thread should remain hidden.');

$failureThreadId = $service->getOrCreateThreadByUser($pdo, 2, 3);
$insertMessage($pdo, $failureThreadId, 2, 'Rollback testi');
$pdo->exec('CREATE TRIGGER fail_message_thread_clear
    BEFORE UPDATE OF cleared_through_message_id ON message_thread_participants
    WHEN NEW.thread_id = ' . $failureThreadId . ' AND NEW.user_id = 3
    BEGIN
        SELECT RAISE(ABORT, "forced clear failure");
    END');
$failedClear = $service->clearThreadForUser($pdo, $failureThreadId, 3);
messageClearAssert($failedClear['success'] === false, 'Forced clear failure should be reported.');
$boundary = $pdo->query('SELECT cleared_through_message_id FROM message_thread_participants WHERE thread_id = ' . $failureThreadId . ' AND user_id = 3')->fetchColumn();
messageClearAssert((int) $boundary === 0, 'A failed clear left a partial visibility boundary.');
messageClearAssert(!$pdo->inTransaction(), 'A failed clear left the transaction open.');
$pdo->exec('DROP TRIGGER fail_message_thread_clear');

$projectRoot = dirname(__DIR__);
$messageApiSource = (string) file_get_contents($projectRoot . '/api/messages.php');
$messagePageSource = (string) file_get_contents($projectRoot . '/includes/src/Modules/Messages/Http/messages-page-content.php');
$messageScriptSource = (string) file_get_contents($projectRoot . '/assets/js/messages-page.js');
$messageStyleSource = (string) file_get_contents($projectRoot . '/assets/css/messages-page.css');
messageClearAssert(str_contains($messageApiSource, "case 'delete_thread':"), 'Thread clear API action is missing.');
messageClearAssert(str_contains($messagePageSource, 'data-messages-thread-delete'), 'Thread clear button is missing.');
messageClearAssert(str_contains($messageScriptSource, 'thread_cleared'), 'Realtime thread clear handling is missing.');
messageClearAssert(str_contains($messageScriptSource, 'window.TMUI.confirm'), 'Irreversible clear confirmation is missing.');
messageClearAssert(str_contains($messageStyleSource, '.messages-thread-delete:focus-visible'), 'Keyboard-visible clear styling is missing.');

fwrite(STDOUT, "Private message thread clear verification passed.\n");
