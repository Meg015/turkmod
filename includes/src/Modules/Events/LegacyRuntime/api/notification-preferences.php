<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET', 'POST']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'events');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $payload = eventsApiPayload();
    eventsApiVerifyCsrf($payload);
    $emailEnabled = !empty($payload['email_notifications_enabled']) ? 1 : 0;
    $types = isset($payload['notification_types']) && is_array($payload['notification_types'])
        ? json_encode(array_values($payload['notification_types']), JSON_UNESCAPED_UNICODE)
        : null;

    $stmt = $pdo->prepare("INSERT INTO events_user_preferences (user_id, email_notifications_enabled, notification_types, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE email_notifications_enabled = VALUES(email_notifications_enabled), notification_types = VALUES(notification_types), updated_at = NOW()");
    $stmt->execute([$userId, $emailEnabled, $types]);
}

$stmt = $pdo->prepare("SELECT * FROM events_user_preferences WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$preferences = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'user_id' => $userId,
    'email_notifications_enabled' => 1,
    'notification_types' => null,
];

sendSuccess('Bildirim tercihleri.', ['preferences' => $preferences]);
