<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$userId = eventsApiRequireAuth();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo, 'raffle');

$raffleId = (int)($payload['raffle_id'] ?? 0);
if ($raffleId <= 0) {
    sendValidationError('Geçerli bir çekiliş seçilmedi.', ['raffle_id' => 'required']);
}

try {
    $pdo->beginTransaction();

    // Prevent race conditions by locking the user row for the duration of the transaction
    $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE")->execute([$userId]);

    $stmt = $pdo->prepare("SELECT * FROM events_raffles WHERE id = ? AND is_active = 1 FOR UPDATE");
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$raffle || (string)$raffle['status'] !== 'active') {
        $pdo->rollBack();
        sendError('raffle_not_active', 'Bu çekiliş aktif değil.', 422);
    }

    if (!eventsRaffleIsOpen($raffle)) {
        $pdo->rollBack();
        sendError('raffle_outside_dates', 'Bu çekiliş katılıma açık değil.', 422);
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events_raffle_entries WHERE raffle_id = ? AND user_id = ?");
    $countStmt->execute([$raffleId, $userId]);
    $currentEntries = (int)$countStmt->fetchColumn();
    $maxEntries = max(1, (int)$raffle['max_entries_per_user']);
    if ($currentEntries >= $maxEntries) {
        $pdo->rollBack();
        sendError('raffle_entry_limit', 'Bu çekiliş için katılım limitiniz doldu.', 422);
    }

    $insertStmt = $pdo->prepare("INSERT INTO events_raffle_entries (raffle_id, user_id, entry_type, created_at) VALUES (?, ?, 'manual', NOW())");
    $insertStmt->execute([$raffleId, $userId]);

    eventsAuditLog($pdo, 'raffle_entry_manual', 'raffle', $raffleId, ['entry_id' => (int)$pdo->lastInsertId()], $userId);
    $pdo->commit();

    sendSuccess('Çekilişe başarıyla katıldınız.', [
        'entry' => [
            'raffle_id' => $raffleId,
            'entry_type' => 'manual',
            'remaining_entries' => max(0, $maxEntries - $currentEntries - 1),
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    eventsErrorLog($pdo, 'Raffle join failed.', ['error' => $e->getMessage(), 'raffle_id' => $raffleId], 'ERROR');
    sendServerError('Çekilişe katılım sırasında hata oluştu.', $e);
}
