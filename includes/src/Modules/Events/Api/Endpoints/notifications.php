<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'events');

$isToastPoll = (bool)($_GET['toast_poll'] ?? false);

if ($isToastPoll) {
    $lastToastTime = (string)($_SESSION['last_toast_time'] ?? date('Y-m-d H:i:s', time() - 60)); // Only toast last 60 seconds if first load
    $_SESSION['last_toast_time'] = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT title, message, type FROM events_notifications WHERE user_id = :user_id AND created_at > :last_time ORDER BY id ASC");
    $stmt->execute([
        'user_id' => $userId,
        'last_time' => $lastToastTime
    ]);
    
    sendSuccess('Toast bildirimleri çekildi.', [
        'toasts' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    ]);
}

$pagination = eventsNormalizePagination($_GET);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM events_notifications WHERE user_id = ?");
$countStmt->execute([$userId]);
$count = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM events_notifications WHERE user_id = :user_id ORDER BY is_read ASC, id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

sendSuccess('Bildirimler listelendi.', [
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'pagination' => [
        'current_page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $count,
        'last_page' => max(1, (int)ceil($count / $pagination['per_page'])),
    ],
]);
