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
if ($rewardId <= 0) {
    sendValidationError('Geçerli bir ödül seçilmedi.', ['reward_id' => 'required']);
}

try {
    $result = eventsApplyPointsReward($pdo, $rewardId, eventsGetConfig($pdo, true), $adminId);
    if (!$result['success']) {
        sendError((string)$result['error'], (string)$result['message'], 422);
    }

    sendSuccess('Puan ödülü uygulandı.', $result);
} catch (Throwable $e) {
    eventsErrorLog($pdo, 'Apply points failed.', ['error' => $e->getMessage(), 'reward_id' => $rewardId], 'ERROR');
    sendServerError('Puan uygulanırken hata oluştu.', $e);
}
