<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'events');

$overview = eventsUserOverview($pdo, $userId);
sendSuccess('Kullanıcı etkinlik istatistikleri.', [
    'stats' => [
        'today_spins' => (int)$overview['today_spins'],
        'pending_rewards' => (int)$overview['pending_rewards'],
        'active_raffles' => count($overview['active_raffles']),
        'unread_notifications' => count(array_filter($overview['notifications'], static fn(array $row): bool => (int)$row['is_read'] === 0)),
    ],
]);
