<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();

try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $stmt = $pdo->prepare("
        SELECT
            ws.id,
            ws.created_at,
            wr.name AS reward_name,
            wr.type AS reward_type,
            wr.value AS reward_value,
            ur.status AS reward_status
        FROM events_wheel_spins ws
        JOIN events_wheel_rewards wr ON wr.id = ws.reward_id
        LEFT JOIN events_user_rewards ur ON ur.id = ws.user_reward_id
        WHERE ws.user_id = ?
        ORDER BY ws.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $spins = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalCount = (int)$countStmt->fetchColumn();

    sendSuccess('Çark geçmişi yüklendi.', [
        'spins' => $spins,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'hasMore' => ($offset + $limit) < $totalCount,
    ]);
} catch (Throwable $e) {
    eventsErrorLog($pdo, 'Wheel history failed.', ['error' => $e->getMessage()], 'ERROR');
    sendServerError('Çark geçmişi yüklenemedi.', $e);
}
