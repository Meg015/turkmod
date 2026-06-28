<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Modules\Notifications\Services\NotificationCenterService;

$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

if ($userId <= 0) {
    sendUnauthorized('Oturum açmanız gerekiyor.');
}

session_write_close();

$pdo = requireDatabaseConnection($pdo ?? null);

try {
    $payload = (new NotificationCenterService())->dropdownPayload($pdo, $userId);
    sendSuccess('OK', (array) $payload);
} catch (Throwable $e) {
    appLogException($e, ['source' => '/api/notifications.php']);
    sendServerError('Bir hata oluştu.', $e);
}