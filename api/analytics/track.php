<?php

declare(strict_types=1);

$GLOBALS['_skip_session_bootstrap'] = true;
$GLOBALS['_cache_control_set'] = true;

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Engine/Analytics/Legacy/helpers.php';

// Analytics endpoint responses must not set session cookies or no-store headers,
// otherwise browser back/forward cache eligibility is reduced.
header_remove('Set-Cookie');
header('Cache-Control: private, no-cache, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

// CSRF-style guard: analytics accepts only same-origin requests.
// sendBeacon can omit Origin/Referer in some browsers, so we also allow
// missing headers when Fetch Metadata indicates non-cross-site traffic.
$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$requestReferer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$expectedHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$expectedHost = preg_replace('/:\d+$/', '', $expectedHost) ?? '';
$originValid = false;
$sameOriginHeader = static function (string $value) use ($expectedHost): bool {
    if ($value === '' || $expectedHost === '') {
        return false;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    return $host === $expectedHost;
};
if ($sameOriginHeader($requestOrigin) || $sameOriginHeader($requestReferer)) {
    $originValid = true;
}
if (!$originValid && $requestOrigin === '' && $requestReferer === '') {
    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    $originValid = in_array($fetchSite, ['', 'same-origin', 'same-site', 'none'], true);
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
    'user_id' => null,
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
