<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Engine/AdminQuality/Legacy/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$currentUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
$hasAdminAccess = $currentUserId > 0 && (
    (function_exists('userHasPermission') && userHasPermission($pdo, $currentUserId, 'admin.access'))
    || (function_exists('userHasPermission') && userHasPermission($pdo, $currentUserId, 'topics.edit'))
);
if (!$hasAdminAccess) {
    sendForbidden('Admin access required.');
}

if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
    sendCsrfError();
}

$offset = max(0, (int)($_POST['offset'] ?? 0));
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
$defaultBatchSize = max(1, min(10, (int) ($settings['topic_health_scan_batch_size'] ?? 3)));
$batchSize = max(1, min(10, (int)($_POST['batch_size'] ?? $defaultBatchSize)));

try {
    adminQualityEnsureSchema($pdo);

    $total = adminQualityCountScannableTopics($pdo);
    $topicIds = adminQualityGetScannableTopicIds($pdo, $offset, $batchSize);
    $results = [];

    foreach ($topicIds as $topicId) {
        $results[] = adminQualityCheckTopicHealth($pdo, (int)$topicId);
    }

    $processed = $offset + count($topicIds);
    $done = $processed >= $total || empty($topicIds);

    if ($done && function_exists('logActivity')) {
        logActivity($pdo, 'topic_health_scan_completed', 'topic', null, [
            'total' => $total,
            'actor_id' => $currentUserId,
        ]);
    }
    if ($done && function_exists('adminAuditLogger')) {
        adminAuditLogger()->logAction($pdo, 'topic_health_scan_completed', 'topic', 0, 'Konu sağlığı taraması tamamlandı', [], [
            'total' => $total,
            'offset' => $offset,
            'processed' => min($processed, $total),
            'batch_size' => $batchSize,
        ], false);
    }

    sendSuccess('Topic health scan batch completed.', [
        'total' => $total,
        'offset' => $offset,
        'processed' => min($processed, $total),
        'next_offset' => min($processed, $total),
        'done' => $done,
        'results' => $results,
        'summary' => adminQualityTopicHealthSummary($pdo),
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => 'admin/api/topic-health-scan.php']);
    sendServerError('Topic health scan failed.', $e);
}

