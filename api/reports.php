<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$pdo = requireDatabaseConnection($pdo ?? null);

// Rate limiting for reports API
$clientKey = 'api_reports_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$reportsRateLimit = max(1, (int)($settings['api_reports_rate_limit'] ?? 10));
$reportsRateWindow = max(1, (int)($settings['api_reports_rate_window'] ?? 1));
$reportSubmitRateLimit = max(1, (int)($settings['api_report_submit_rate_limit'] ?? 5));
$reportSubmitRateWindow = max(1, (int)($settings['api_report_submit_rate_window'] ?? 10));
if (!checkRateLimit($clientKey, $reportsRateLimit, $reportsRateWindow)) {
    sendRateLimitError(max(60, $reportsRateWindow * 60));
}
incrementRateLimit($clientKey, $reportsRateWindow);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
$payload = json_decode((string) file_get_contents('php://input'), true);
if (is_array($payload) && $csrf === '') {
    $csrf = (string) ($payload['_token'] ?? '');
}

if (!verify_csrf_token((string) $csrf)) {
    sendCsrfError();
}

if (empty($_SESSION['_auth_user_id'])) {
    sendUnauthorized('Bu işlem için giriş yapmalısınız.');
}

session_write_close();

$action = (string) (($payload['action'] ?? $_POST['action'] ?? 'create'));
if ($action !== 'create') {
    sendError('invalid_action', 'Geçersiz eylem.', 422);
}

$topicId = (int) ($payload['topic_id'] ?? $_POST['topic_id'] ?? 0);
$reason = (string) ($payload['reason'] ?? $_POST['reason'] ?? '');
$details = (string) ($payload['details'] ?? $_POST['details'] ?? '');

$clientKey = 'api_report_' . (int) $_SESSION['_auth_user_id'];
if (!checkRateLimit($clientKey, $reportSubmitRateLimit, $reportSubmitRateWindow)) {
    sendRateLimitError(max(60, $reportSubmitRateWindow * 60));
}
incrementRateLimit($clientKey, $reportSubmitRateWindow);

try {
    $result = submitTopicReport($pdo, $topicId, (int) $_SESSION['_auth_user_id'], $reason, $details);
    $success = (bool) ($result['success'] ?? false);
    $message = (string) ($result['message'] ?? ($success ? 'Rapor alındı.' : 'Rapor gönderilemedi.'));
    $data = $result;
    unset($data['success'], $data['message']);
    sendJsonResponse(
        $success ? 200 : 422,
        $success,
        $message,
        is_array($data) ? $data : [],
        $success ? null : 'report_rejected'
    );
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/reports.php', 'topic_id' => $topicId]);
    sendServerError('Sunucu hatası.', $e);
}