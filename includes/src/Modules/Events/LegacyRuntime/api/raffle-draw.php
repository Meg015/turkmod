<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$adminId = eventsApiRequireAdmin();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo);

$raffleId = (int)($payload['raffle_id'] ?? 0);
$notes = mb_substr(trim((string)($payload['notes'] ?? '')), 0, 2000);
if ($raffleId <= 0) {
    sendValidationError('Geçerli bir çekiliş seçilmedi.', ['raffle_id' => 'required']);
}

try {
    $config = eventsGetConfig($pdo, true);
    $pdo->beginTransaction();

    $result = eventsDrawRaffle($pdo, $raffleId, $config, $adminId, $notes);
    $pdo->commit();

    sendSuccess('Çekiliş çekildi.', [
        'draw_id' => $result['draw_id'],
        'winners' => $result['winners'],
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    eventsErrorLog($pdo, 'Raffle draw failed.', ['error' => $e->getMessage(), 'raffle_id' => $raffleId], 'ERROR');
    
    // Check if it's a RuntimeException which we use for validation errors
    if ($e instanceof RuntimeException) {
        sendError('draw_validation_failed', $e->getMessage(), 400);
    } else {
        sendServerError('Çekiliş çekimi sırasında hata oluştu: ' . $e->getMessage(), $e);
    }
}
