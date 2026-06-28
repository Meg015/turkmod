<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
    if ($pdo instanceof PDO && function_exists('logCsrfFailure')) {
        logCsrfFailure($pdo, '/api/track-view.php');
    }
    sendCsrfError();
}

$actorId = (int) ($_SESSION['_auth_user_id'] ?? 0);
session_write_close();

$topicId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($topicId <= 0) {
    sendError('invalid_topic_id', 'Geçersiz konu kimliği.', 400);
}

$pdo = requireDatabaseConnection($pdo);

try {
    $realIp = getRealIp();
    $viewRateKey = "topic_view_" . $topicId . "_" . $realIp;

    if (checkRateLimit($viewRateKey, 1, RATE_LIMIT_VIEW_WINDOW)) {
        $pdo->prepare(
            "UPDATE topics SET view_count = view_count + 1 WHERE id = ? AND deleted_at IS NULL"
        )->execute([$topicId]);

        if ($actorId > 0 && function_exists('logActivity')) {
            $activityProperties = [];
            try {
                $topic = function_exists('getTopic') ? getTopic($pdo, $topicId) : null;
                if (is_array($topic)) {
                    $topicTitle = trim((string) ($topic['title'] ?? ''));
                    $topicSlug = trim((string) ($topic['slug'] ?? ''));
                    $categoryId = (int) ($topic['category_id'] ?? 0);
                    $categorySlug = trim((string) ($topic['category_slug'] ?? ''));

                    if ($topicTitle !== '') {
                        $activityProperties['subject_title'] = $topicTitle;
                    }
                    if ($topicSlug !== '') {
                        $activityProperties['topic_slug'] = $topicSlug;
                    }
                    if ($categoryId > 0) {
                        $activityProperties['category_id'] = $categoryId;
                    }
                    if ($categorySlug !== '') {
                        $activityProperties['category_slug'] = $categorySlug;
                    }
                }
            } catch (Throwable $metaError) {
                appLogException($metaError, [
                    'source' => 'track-view.php topicMetaLookup',
                    'topic_id' => $topicId,
                ]);
            }

            logActivity($pdo, 'topic_viewed', 'topic', $topicId, $activityProperties);
        }

        incrementRateLimit($viewRateKey);
        sendSuccess('Görüntüleme kaydedildi.', ['topic_id' => $topicId]);
    } else {
        sendRateLimitError(120);
    }
} catch (Throwable $e) {
    appLogException($e, [
        "source" => "track-view.php viewIncrement",
        "topic_id" => $topicId,
    ]);
    sendServerError('Görüntüleme kaydedilemedi.', $e);
}
