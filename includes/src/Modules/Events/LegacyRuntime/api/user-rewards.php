<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'rewards');
eventsExpirePendingRewards($pdo);

$pagination = eventsNormalizePagination($_GET);
$status = trim((string)($_GET['status'] ?? ''));
$where = 'WHERE user_id = :user_id';
$params = ['user_id' => $userId];
if ($status !== '') {
    $where .= ' AND status = :status';
    $params['status'] = $status;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM events_user_rewards {$where}");
$countStmt->execute($params);
$count = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM events_user_rewards {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

sendSuccess('Ödüller listelendi.', [
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'pagination' => [
        'current_page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $count,
        'last_page' => max(1, (int)ceil($count / $pagination['per_page'])),
    ],
]);
