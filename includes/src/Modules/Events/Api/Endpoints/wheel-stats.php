<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'wheel');

$config = eventsGetConfig($pdo);
$rateConfig = eventsWheelRateConfig($config);
$rateWindowMinutes = (int)$rateConfig['window_minutes'];
$stmt = $pdo->prepare("SELECT
    COUNT(*) AS total_spins,
    SUM(created_at >= CURDATE()) AS today_spins,
    SUM(created_at >= DATE_SUB(NOW(), INTERVAL {$rateWindowMinutes} MINUTE)) AS rate_spins
    FROM events_wheel_spins
    WHERE user_id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

sendSuccess('Çark istatistikleri.', [
    'stats' => [
        'total_spins' => (int)($row['total_spins'] ?? 0),
        'today_spins' => (int)($row['today_spins'] ?? 0),
        'rate_spins' => (int)($row['rate_spins'] ?? 0),
        'rate_limit' => (int)$rateConfig['limit'],
        'rate_window_minutes' => $rateWindowMinutes,
        'daily_limit' => (int)$rateConfig['limit'],
        'hourly_limit' => 0,
    ],
]);
