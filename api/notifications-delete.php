<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Notifications\Services\NotificationCenterService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

if (!verify_csrf_token($_POST['_token'] ?? '')) {
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

$notificationIds = array_values(array_filter(array_map(static function ($value): int {
    return (int) $value;
}, $rawIds), static function (int $value): bool {
    return $value > 0;
}));

if ($notificationIds === []) {
    sendValidationError('Geçerli bir bildirim seçilmedi.', ['ids' => 'required']);
}

session_write_close();

$pdo = requireDatabaseConnection($pdo ?? null);

try {
    notificationEnsureDismissalSchema($pdo, false);

    $deletedCount = (new NotificationCenterService())->dismissNotifications($pdo, $userId, $notificationIds);

    sendSuccess('Seçilen bildirimler silindi.', [
        'ok' => true,
        'deleted_count' => $deletedCount,
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/notifications-delete.php']);
    sendServerError('Bir hata oluştu.', $e);
}
