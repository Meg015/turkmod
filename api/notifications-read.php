<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Notifications\Services\NotificationCenterService;
use App\Modules\Messages\Services\MessageService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$submittedToken = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($submittedToken)) {
    sendCsrfError();
}

$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

if ($userId <= 0) {
    sendUnauthorized('Oturum açmanız gerekiyor.');
}

session_write_close();

$pdo = requireDatabaseConnection($pdo ?? null);
$notificationId = $_POST['id'] ?? 'all';
if ((!is_string($notificationId) && !is_int($notificationId))
    || ($notificationId !== 'all' && filter_var($notificationId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
    sendValidationError('Geçerli bir bildirim seçilmedi.', ['id' => 'invalid']);
}

try {
    $service = new NotificationCenterService();
    $marked = $service->markRead($pdo, $userId, $notificationId);
    if (!$marked) {
        sendError('notification_read_failed', 'Bildirim okundu olarak işaretlenemedi.', 409);
    }

    $dropdownState = $service->dropdownPayload($pdo, $userId);
    $messageService = new MessageService();
    sendSuccess('Okundu olarak işaretlendi.', [
        'ok' => true,
        'show_badge' => !empty($dropdownState['show_badge']),
        'unread_count' => (int) ($dropdownState['unread_count'] ?? 0),
        'messages_unread_count' => $messageService->unreadCount($pdo, $userId),
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/notifications-read.php']);
    sendServerError('Bir hata oluştu.', $e);
}
