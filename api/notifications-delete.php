<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Notifications\Services\NotificationCenterService;

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

$rawIds = $_POST['ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = preg_split('/[,\s]+/', (string) $rawIds, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

$notificationIds = [];
foreach ($rawIds as $value) {
    $id = (is_string($value) || is_int($value))
        ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($id === false) {
        sendValidationError('Geçerli bir bildirim seçilmedi.', ['ids' => 'invalid']);
    }
    $notificationIds[] = $id;
}
$notificationIds = array_values(array_unique($notificationIds));

if ($notificationIds === []) {
    sendValidationError('Geçerli bir bildirim seçilmedi.', ['ids' => 'required']);
}

session_write_close();

$pdo = requireDatabaseConnection($pdo ?? null);

try {
    notificationEnsureDismissalSchema($pdo, false);

    $service = new NotificationCenterService();
    $deletedCount = $service->dismissNotifications($pdo, $userId, $notificationIds);
    $dropdownState = $service->dropdownPayload($pdo, $userId);

    sendSuccess('Seçilen bildirimler silindi.', [
        'ok' => true,
        'deleted_count' => $deletedCount,
        'show_badge' => !empty($dropdownState['show_badge']),
        'unread_count' => (int) ($dropdownState['unread_count'] ?? 0),
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/notifications-delete.php']);
    sendServerError('Bir hata oluştu.', $e);
}
