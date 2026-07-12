<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../admin/helpers.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    sendMethodNotAllowed(['GET', 'POST']);
}

$pdo = requireDatabaseConnection($pdo ?? null);
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

$topicId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $topicId = (int) ($payload['topic_id'] ?? ($_POST['topic_id'] ?? 0));
} else {
    $topicId = (int) ($_GET['topic_id'] ?? 0);
}

if ($topicId <= 0) {
    sendValidationError('Gecersiz konu id.', ['topic_id' => 'Topic id zorunludur.']);
}

try {
    $topicStmt = $pdo->prepare("SELECT id FROM topics WHERE id = :id AND status = 'published' AND deleted_at IS NULL LIMIT 1");
    $topicStmt->execute(['id' => $topicId]);
    $topicExists = (bool) $topicStmt->fetchColumn();
    if (!$topicExists) {
        sendNotFound('Konu bulunamadi.');
    }

    $currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
    $state = function_exists('topicDownloadAccessState')
        ? topicDownloadAccessState($pdo, $settings, $topicId, $currentUserId)
        : [
            'mode' => 'public',
            'comment_requirement' => 'submitted',
            'locked' => false,
            'reason' => 'none',
            'message' => '',
            'has_comment' => false,
            'requires_login' => false,
        ];

    sendSuccess('Erisim durumu guncellendi.', [
        'topic_id' => $topicId,
        'user_id' => $currentUserId,
        'logged_in' => $currentUserId > 0,
        'access' => $state,
        'stage' => (string) ($state['stage'] ?? 'open'),
        'locked' => !empty($state['locked']),
        'reason' => (string) ($state['reason'] ?? 'none'),
        'message' => (string) ($state['message'] ?? ''),
        'mode' => (string) ($state['mode'] ?? 'public'),
        'comment_requirement' => (string) ($state['comment_requirement'] ?? 'submitted'),
    ]);
} catch (Throwable $e) {
    sendServerError('Indirme erisim durumu alinamadi.', $e);
}
