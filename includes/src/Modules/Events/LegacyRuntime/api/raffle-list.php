<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'raffle');

$pagination = eventsNormalizePagination($_GET);
$periodSql = "WHERE r.is_active = 1";
$count = (int)$pdo->query("SELECT COUNT(*) FROM events_raffles r {$periodSql}")->fetchColumn();
$stmt = $pdo->prepare("SELECT r.*,
        (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS entry_count,
        (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id AND e.user_id = :user_id) AS user_entries
    FROM events_raffles r
    {$periodSql}
    ORDER BY FIELD(r.status, 'active','closed','drawn','draft','cancelled'), r.end_date ASC
    LIMIT :limit OFFSET :offset");
$stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

sendSuccess('Çekilişler listelendi.', [
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'pagination' => [
        'current_page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $count,
        'last_page' => max(1, (int)ceil($count / $pagination['per_page'])),
    ],
]);
