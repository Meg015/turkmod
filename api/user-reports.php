<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$pdo = requireDatabaseConnection($pdo ?? null);

$clientKey = 'api_user_reports_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$userReportsRateLimit = max(1, (int)($settings['api_user_reports_rate_limit'] ?? 10));
$userReportsRateWindow = max(1, (int)($settings['api_user_reports_rate_window'] ?? 1));
$userReportSubmitRateLimit = max(1, (int)($settings['api_user_report_submit_rate_limit'] ?? 5));
$userReportSubmitRateWindow = max(1, (int)($settings['api_user_report_submit_rate_window'] ?? 10));
if (!checkRateLimit($clientKey, $userReportsRateLimit, $userReportsRateWindow)) {
    sendRateLimitError(max(60, $userReportsRateWindow * 60));
}
incrementRateLimit($clientKey, $userReportsRateWindow);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
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

$reportedUserId = (int) ($payload['reported_user_id'] ?? $_POST['reported_user_id'] ?? 0);
$reason = (string) ($payload['reason'] ?? $_POST['reason'] ?? '');
$details = (string) ($payload['details'] ?? $_POST['details'] ?? '');

$userRateKey = 'api_user_report_' . (int) $_SESSION['_auth_user_id'];
if (!checkRateLimit($userRateKey, $userReportSubmitRateLimit, $userReportSubmitRateWindow)) {
    sendRateLimitError(max(60, $userReportSubmitRateWindow * 60));
}
incrementRateLimit($userRateKey, $userReportSubmitRateWindow);

try {
    $result = submitUserReport($pdo, $reportedUserId, (int) $_SESSION['_auth_user_id'], $reason, $details);
    $success = (bool) ($result['success'] ?? false);
    $message = (string) ($result['message'] ?? ($success ? 'Şikayet alındı.' : 'Şikayet gönderilemedi.'));
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
    appLogException($e, ['source' => 'api/user-reports.php', 'reported_user_id' => $reportedUserId]);
    sendServerError('Sunucu hatası.', $e);
}