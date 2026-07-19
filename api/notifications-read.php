<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Notifications\Services\NotificationCenterService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$submittedToken = (string) ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
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

try {
    $marked = (new NotificationCenterService())->markRead($pdo, $userId, is_scalar($notificationId) ? (string) $notificationId : 'all');
    if (!$marked) {
        sendError('notification_read_failed', 'Bildirim okundu olarak işaretlenemedi.', 409);
    }
    sendSuccess('Okundu olarak işaretlendi.', ['ok' => true]);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/notifications-read.php']);
    sendServerError('Bir hata oluştu.', $e);
}
