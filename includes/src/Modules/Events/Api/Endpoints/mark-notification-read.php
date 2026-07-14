<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$userId = eventsApiRequireAuth();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo, 'events');

$notificationId = (int)($payload['notification_id'] ?? 0);
if ($notificationId <= 0) {
    sendValidationError('Geçerli bir bildirim seçilmedi.', ['notification_id' => 'required']);
}

$stmt = $pdo->prepare("UPDATE events_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
$stmt->execute([$notificationId, $userId]);

sendSuccess('Bildirim okundu olarak işaretlendi.', ['updated' => $stmt->rowCount()]);
