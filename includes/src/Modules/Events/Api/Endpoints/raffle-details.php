<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'raffle');

$raffleId = (int)($_GET['raffle_id'] ?? $_GET['id'] ?? 0);
if ($raffleId <= 0) {
    sendValidationError('Geçerli bir çekiliş seçilmedi.', ['raffle_id' => 'required']);
}

$stmt = $pdo->prepare("SELECT r.*,
        (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS entry_count,
        (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id AND e.user_id = ?) AS user_entries
    FROM events_raffles r
    WHERE r.id = ? AND r.is_active = 1
    LIMIT 1");
$stmt->execute([$userId, $raffleId]);
$raffle = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$raffle) {
    sendNotFound('Çekiliş bulunamadı.');
}

sendSuccess('Çekiliş detayı.', ['raffle' => $raffle]);
