<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$userId = eventsApiRequireAuth();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo, 'tasks');

if (!eventsTasksTablesReady($pdo)) {
    sendError('events_tasks_schema_missing', 'Gorev tablolari hazir degil. database/schema.sql kurulumu tamamlanmali.', 503);
}

$taskId = (int)($payload['task_id'] ?? 0);
$periodKey = trim((string)($payload['period_key'] ?? '')) ?: null;
if ($taskId <= 0) {
    sendValidationError('Geçerli bir görev seçilmedi.', ['task_id' => 'required']);
}

$result = eventsClaimTaskReward($pdo, $userId, $taskId, $periodKey);
if (!($result['success'] ?? false)) {
    sendError((string)($result['error'] ?? 'task_claim_failed'), (string)($result['message'] ?? 'Görev ödülü alınamadı.'), 422);
}

sendSuccess((string)($result['message'] ?? 'Görev ödülü alındı.'), ['claim' => $result]);
