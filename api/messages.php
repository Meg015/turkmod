<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Messages\Services\MessageService;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

if ($userId <= 0) {
    sendUnauthorized('Oturum acmaniz gerekiyor.');
}

$pdo = requireDatabaseConnection($pdo ?? null);
$service = new MessageService();
$baseUri = rtrim((string) ($baseUri ?? ''), '/');
$schemaReady = $service->isSchemaReady($pdo, true);
$schemaUnavailableMessage = $service->unavailableMessage();

$getAction = trim((string) ($_GET['action'] ?? 'dropdown'));
$postAction = trim((string) ($_POST['action'] ?? ''));

try {
    if ($method === 'GET') {
        $action = $getAction !== '' ? $getAction : 'dropdown';

        switch ($action) {
            case 'dropdown':
                $limit = max(1, min(20, (int) ($_GET['limit'] ?? 6)));
                if (!$schemaReady) {
                    sendSuccess($schemaUnavailableMessage, [
                        'ok' => false,
                        'unread_count' => 0,
                        'latest' => [],
                    ]);
                }
                sendSuccess('OK', $service->dropdownPayload($pdo, $userId, $limit, $baseUri));

            case 'list':
                $limit = max(1, min(100, (int) ($_GET['limit'] ?? 80)));
                if (!$schemaReady) {
                    sendSuccess($schemaUnavailableMessage, [
                        'ok' => false,
                        'threads' => [],
                        'unread_count' => 0,
                    ]);
                }
                sendSuccess('OK', [
                    'ok' => true,
                    'threads' => $service->listThreads($pdo, $userId, $limit, $baseUri),
                    'unread_count' => $service->unreadCount($pdo, $userId),
                ]);

            case 'thread':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $threadId = max(0, (int) ($_GET['thread_id'] ?? $_GET['thread'] ?? 0));
                if ($threadId <= 0) {
                    sendValidationError('Gecerli bir sohbet secilmedi.');
                }

                $payload = $service->openThread($pdo, $userId, $threadId, $baseUri);
                if (!is_array($payload)) {
                    sendNotFound('Sohbet bulunamadi.');
                }

                sendSuccess('OK', [
                    'ok' => true,
                    'thread' => is_array($payload['thread'] ?? null) ? $payload['thread'] : null,
                    'messages' => is_array($payload['messages'] ?? null) ? $payload['messages'] : [],
                    'marked_unread_count' => (int) ($payload['marked_unread_count'] ?? 0),
                    'unread_count' => $service->unreadCount($pdo, $userId),
                ]);

            case 'history':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $threadId = max(0, (int) ($_GET['thread_id'] ?? 0));
                $beforeId = max(0, (int) ($_GET['before_id'] ?? 0));

                if ($threadId <= 0 || $beforeId <= 0) {
                    sendValidationError('Gecerli bir sohbet ve referans mesaji belirtilmedi.');
                }

                $messages = $service->getHistory($pdo, $userId, $threadId, $beforeId);
                sendSuccess('OK', [
                    'ok' => true,
                    'messages' => $messages,
                ]);

            case 'search':
                $query = trim((string) ($_GET['q'] ?? ''));
                $limit = max(1, min(20, (int) ($_GET['limit'] ?? 8)));
                sendSuccess('OK', [
                    'ok' => true,
                    'users' => $service->searchUsers($pdo, $userId, $query, $limit, $baseUri),
                ]);

            default:
                sendValidationError('Gecersiz islem.');
        }
    }

    if ($method === 'POST') {
        if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
            sendCsrfError();
        }

        $action = $postAction;
        if ($action === '') {
            sendValidationError('Gecersiz islem.');
        }

        switch ($action) {
            case 'send':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }

                $clientKey = 'api_messages_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
                $settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
                $messagesRateLimit = max(1, (int)($settings['api_messages_rate_limit'] ?? 60));
                $messagesRateWindow = max(1, (int)($settings['api_messages_rate_window'] ?? 1));
                
                if (!checkRateLimit($clientKey, $messagesRateLimit, $messagesRateWindow)) {
                    sendRateLimitError(max(60, $messagesRateWindow * 60));
                }
                incrementRateLimit($clientKey, $messagesRateWindow);

                $threadId = max(0, (int) ($_POST['thread_id'] ?? 0));
                $body = (string) ($_POST['body'] ?? '');

                if ($threadId > 0) {
                    $result = $service->sendMessageToThread($pdo, $userId, $threadId, $body, $baseUri);
                } else {
                    $targetUserId = max(0, (int) ($_POST['target_user_id'] ?? 0));
                    $result = $service->sendMessage($pdo, $userId, $targetUserId, $body, $baseUri);
                }

                if (empty($result['success'])) {
                    sendValidationError((string) ($result['message'] ?? 'Mesaj gonderilemedi.'));
                }

                sendSuccess((string) ($result['message'] ?? 'Mesaj gonderildi.'), [
                    'ok' => true,
                    'thread_id' => (int) ($result['thread_id'] ?? 0),
                    'message_id' => (int) ($result['message_id'] ?? 0),
                    'unread_count' => $service->unreadCount($pdo, $userId),
                ]);

            case 'mark_all_read':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $updatedThreads = $service->markAllRead($pdo, $userId);
                sendSuccess('Mesajlar okundu olarak isaretlendi.', [
                    'ok' => true,
                    'updated_threads' => $updatedThreads,
                    'unread_count' => $service->unreadCount($pdo, $userId),
                ]);

            case 'typing':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $threadId = max(0, (int) ($_POST['thread_id'] ?? 0));
                if ($threadId > 0) {
                    $service->updateTypingStatus($pdo, $threadId, $userId);
                }
                sendSuccess('OK', ['ok' => true]);

            case 'stop_typing':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $threadId = max(0, (int) ($_POST['thread_id'] ?? 0));
                if ($threadId > 0) {
                    $service->clearTypingStatus($pdo, $threadId, $userId);
                }
                sendSuccess('OK', ['ok' => true]);

            case 'delete':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
                $result = $service->deleteMessage($pdo, $messageId, $userId);
                if (empty($result['success'])) {
                    sendValidationError((string) ($result['message'] ?? 'Mesaj silinemedi.'));
                }
                sendSuccess((string) ($result['message'] ?? 'Mesaj silindi.'), ['ok' => true]);

            case 'edit':
                if (!$schemaReady) {
                    sendValidationError($schemaUnavailableMessage);
                }
                $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
                $body = (string) ($_POST['body'] ?? '');
                $result = $service->editMessage($pdo, $messageId, $userId, $body);
                if (empty($result['success'])) {
                    sendValidationError((string) ($result['message'] ?? 'Mesaj düzenlenemedi.'));
                }
                sendSuccess((string) ($result['message'] ?? 'Mesaj düzenlendi.'), ['ok' => true]);

            default:
                sendValidationError('Gecersiz islem.');
        }
    }

    sendMethodNotAllowed(['GET', 'POST']);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/messages.php']);
    sendServerError('Bir hata olustu.', $e);
}
