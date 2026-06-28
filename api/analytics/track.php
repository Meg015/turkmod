<?php

declare(strict_types=1);

$incomingSessionName = session_name();
$incomingSessionId = isset($_COOKIE[$incomingSessionName])
    ? (string) $_COOKIE[$incomingSessionName]
    : '';

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Engine/Analytics/Legacy/helpers.php';

// Stale analytics beacons must not replace a freshly regenerated login session.
if (
    $incomingSessionId === ''
    || session_id() === ''
    || !hash_equals($incomingSessionId, session_id())
) {
    header_remove('Set-Cookie');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

// CSRF protection: validate Origin/Referer for JSON POST (Same-Origin policy).
// Analytics beacons are fire-and-forget; token-based CSRF is impractical for
// <navigator.sendBeacon()> usage, so we enforce same-origin via Origin/Referer.
$requestOrigin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$requestReferer = (string)($_SERVER['HTTP_REFERER'] ?? '');
$expectedHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$allowedSchemes = ['http://', 'https://'];
$originValid = false;
if ($requestOrigin !== '' && $expectedHost !== '') {
    foreach ($allowedSchemes as $scheme) {
        if (str_starts_with($requestOrigin, $scheme . $expectedHost)) {
            $originValid = true;
            break;
        }
    }
}
if (!$originValid && $requestReferer !== '' && $expectedHost !== '') {
    foreach ($allowedSchemes as $scheme) {
        if (str_starts_with($requestReferer, $scheme . $expectedHost)) {
            $originValid = true;
            break;
        }
    }
}
if (!$originValid && $requestOrigin === '' && $requestReferer === '') {
    // Same-origin requests from some browsers may omit both headers;
    // allow only if a valid session cookie is present (authenticated user).
    $originValid = !empty($_SESSION['_auth_user_id']);
}
if (!$originValid) {
    sendError('origin_check_failed', 'Aynı alan adı doğrulaması başarısız.', 403);
}

$clientKey = 'api_analytics_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$analyticsRateLimit = max(1, (int)($settings['api_analytics_rate_limit'] ?? 120));
$analyticsRateWindow = max(1, (int)($settings['api_analytics_rate_window'] ?? 1));
if (!checkRateLimit($clientKey, $analyticsRateLimit, $analyticsRateWindow)) {
    sendRateLimitError(max(60, $analyticsRateWindow * 60));
}
incrementRateLimit($clientKey, $analyticsRateWindow);

$maxPayloadBytes = analyticsEnvInt($envConfig ?? [], 'ANALYTICS_MAX_PAYLOAD_BYTES', 16384, 1024, 65536);
$rawPayload = (string) file_get_contents('php://input');
if (strlen($rawPayload) > $maxPayloadBytes) {
    sendError('payload_too_large', 'İstek gövdesi çok büyük.', 413);
}

$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    sendError('invalid_payload', 'Geçersiz JSON gövdesi.', 422);
}

$event = preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string)($payload['event'] ?? 'unknown')) ?: 'unknown';
$data = $payload['data'] ?? [];
if (!is_array($data)) {
    $data = [];
}

$record = [
    'event' => mb_substr($event, 0, 80),
    'data' => analyticsSanitizeValue($data),
    'session_id' => mb_substr((string)($payload['session_id'] ?? ''), 0, 120),
    'user_id' => $_SESSION['_auth_user_id'] ?? null,
    'url' => analyticsSanitizeUrl((string)($payload['url'] ?? '')),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    'created_at' => date('c'),
];

try {
    $storageDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    $retentionDays = analyticsEnvInt($envConfig ?? [], 'ANALYTICS_RETENTION_DAYS', 14, 1, 365);
    analyticsPruneOldLogs($storageDir, $retentionDays);
    file_put_contents(
        analyticsLogPath($storageDir),
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/analytics/track.php']);
}

sendSuccess('Olay kaydedildi.');