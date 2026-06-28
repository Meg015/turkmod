<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
if (is_file(__DIR__ . '/../../includes/src/Modules/Events/init.php')) {
    require_once __DIR__ . '/../../includes/src/Modules/Events/init.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$pdo = requireDatabaseConnection($pdo ?? null);

$clientKey = 'api_favorite_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$favoriteRateLimit = max(1, (int)($settings['api_favorite_rate_limit'] ?? 30));
$favoriteRateWindow = max(1, (int)($settings['api_favorite_rate_window'] ?? 1));
if (!checkRateLimit($clientKey, $favoriteRateLimit, $favoriteRateWindow)) {
    sendRateLimitError(max(60, $favoriteRateWindow * 60));
}
incrementRateLimit($clientKey, $favoriteRateWindow);

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
if (!verify_csrf_token((string)$csrf)) {
    sendCsrfError();
}

if (empty($_SESSION['_auth_user_id'])) {
    sendUnauthorized('Bu işlem için giriş yapmalısınız.');
}

$userId = (int)$_SESSION['_auth_user_id'];
session_write_close();

$payload = json_decode((string)file_get_contents('php://input'), true);
$topicId = (int)($payload['topic_id'] ?? $_POST['topic_id'] ?? 0);

if ($topicId <= 0) {
    sendError('invalid_topic', 'Geçersiz konu kimliği.', 422);
}

try {
    $exists = $pdo->prepare("SELECT id, author_id FROM topics WHERE id = ? AND status = 'published' AND deleted_at IS NULL LIMIT 1");
    $exists->execute([$topicId]);
    $topic = $exists->fetch(PDO::FETCH_ASSOC);
    if (!$topic) {
        sendNotFound('Konu bulunamadı.');
    }

    $favorited = toggleTopicFavorite($pdo, $topicId, $userId);
    $count = getTopicFavoriteCount($pdo, $topicId);
    if ($favorited && function_exists('eventsRecordActivity')) {
        eventsRecordActivity($pdo, $userId, 'topic_favorite_added', 'topic', $topicId, [
            'subject_user_id' => (int)($topic['author_id'] ?? 0),
        ]);
    }

    sendSuccess('Favori durumu güncellendi.', [
        'topic_id' => $topicId,
        'favorited' => $favorited,
        'count' => $count,
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/favorites/toggle.php', 'topic_id' => $topicId]);
    sendServerError('Favori işlemi başarısız.', $e);
}