<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$adminId = eventsApiRequireAdmin();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo);

$rewardId = (int)($payload['reward_id'] ?? 0);
$note = mb_substr(trim((string)($payload['note'] ?? '')), 0, 500);
if ($rewardId <= 0) {
    sendValidationError('Geçerli bir ödül seçilmedi.', ['reward_id' => 'required']);
}

try {
    $result = eventsApplyCustomReward($pdo, $rewardId, $adminId, $note, $baseUri ?? '');
    if (!$result['success']) {
        sendError((string)$result['error'], (string)$result['message'], 422);
    }

    sendSuccess('Özel ödül uygulandı.', $result);
} catch (Throwable $e) {
    eventsErrorLog($pdo, 'Apply custom reward failed.', ['error' => $e->getMessage(), 'reward_id' => $rewardId], 'ERROR');
    sendServerError('Özel ödül uygulanırken hata oluştu.', $e);
}
