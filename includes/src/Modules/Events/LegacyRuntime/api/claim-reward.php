<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$userId = eventsApiRequireAuth();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo, 'rewards');

$rewardId = (int)($payload['reward_id'] ?? 0);
if ($rewardId <= 0) {
    sendValidationError('Geçerli bir ödül seçilmedi.', ['reward_id' => 'required']);
}

try {
    $rewardsUrl = function_exists('eventsPublicUrl')
        ? eventsPublicUrl('rewards')
        : (function_exists('routePublicStaticUrl')
            ? routePublicStaticUrl('events', 'rewards')
            : rtrim((string) ($baseUri ?? ''), '/') . '/events/rewards');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM events_user_rewards WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$rewardId, $userId]);
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) {
        $pdo->rollBack();
        sendNotFound('Ödül bulunamadı.');
    }
    if (eventsRewardExpired($reward)) {
        eventsMarkRewardExpired($pdo, $rewardId, $userId);
        $pdo->commit();
        sendError('reward_expired', 'Bu odulun suresi dolmus.', 422);
    }
    if ((string)$reward['status'] !== 'pending') {
        $pdo->rollBack();
        sendError('reward_not_pending', 'Bu ödül teslim alınabilir durumda değil.', 422);
    }
    if ((string)$reward['reward_type'] === 'points') {
        $pdo->rollBack();
        sendError('points_reward_admin_required', 'Puan ödülleri puan sistemi üzerinden uygulanmalıdır.', 422);
    }

    $update = $pdo->prepare("UPDATE events_user_rewards SET status = 'claimed', claimed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $update->execute([$rewardId]);

    $notification = $pdo->prepare("INSERT INTO events_notifications (user_id, type, title, message, related_type, related_id, action_url, priority, is_read, created_at)
        VALUES (?, 'reward_claimed', 'Ödül teslim edildi', ?, 'reward', ?, ?, 'low', 0, NOW())");
    $notification->execute([$userId, 'Teslim edilen ödül: ' . (string)$reward['reward_name'], $rewardId, $rewardsUrl]);

    eventsAuditLog($pdo, 'reward_claim', 'user_reward', $rewardId, [], $userId);
    $pdo->commit();

    $reward['status'] = 'claimed';
    $reward['claimed_at'] = date('c');
    sendSuccess('Ödülünüz teslim edildi.', ['reward' => $reward]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    eventsErrorLog($pdo, 'Claim reward failed.', ['error' => $e->getMessage(), 'reward_id' => $rewardId], 'ERROR');
    sendServerError('Ödül teslim alınırken hata oluştu.', $e);
}
