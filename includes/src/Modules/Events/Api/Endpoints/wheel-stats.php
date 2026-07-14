<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'wheel');

$config = eventsGetConfig($pdo);
$stmt = $pdo->prepare("SELECT
    COUNT(*) AS total_spins,
    SUM(created_at >= CURDATE()) AS today_spins,
    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS hourly_spins
    FROM events_wheel_spins
    WHERE user_id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

sendSuccess('Çark istatistikleri.', [
    'stats' => [
        'total_spins' => (int)($row['total_spins'] ?? 0),
        'today_spins' => (int)($row['today_spins'] ?? 0),
        'hourly_spins' => (int)($row['hourly_spins'] ?? 0),
        'daily_limit' => (int)$config['wheel_daily_limit'],
        'hourly_limit' => (int)$config['wheel_hourly_limit'],
    ],
]);
