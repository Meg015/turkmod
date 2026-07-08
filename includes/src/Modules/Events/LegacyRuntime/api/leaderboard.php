<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'events');

$sortBy = (string)($_GET['sort_by'] ?? 'total_spins');
if (!in_array($sortBy, ['total_spins', 'total_wins', 'total_points'], true)) {
    $sortBy = 'total_spins';
}

$orderExpr = match ($sortBy) {
    'total_wins' => 'total_wins DESC',
    'total_points' => 'total_points DESC',
    default => 'total_spins DESC',
};

$stmt = $pdo->query("SELECT u.id AS user_id, u.username AS username,
        COUNT(DISTINCT s.id) AS total_spins,
        COUNT(DISTINCT ur.id) AS total_wins,
        SUM(CASE WHEN ur.reward_type = 'points' THEN CAST(ur.reward_value AS UNSIGNED) ELSE 0 END) AS total_points
    FROM users u
    LEFT JOIN events_wheel_spins s ON s.user_id = u.id
    LEFT JOIN events_user_rewards ur ON ur.user_id = u.id
    WHERE u.deleted_at IS NULL
    GROUP BY u.id, u.username
    ORDER BY {$orderExpr}, u.id ASC
    LIMIT 50");

sendSuccess('Etkinlik leaderboard.', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
