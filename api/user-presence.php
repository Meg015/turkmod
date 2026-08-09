<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use App\Core\Bootstrap\Boot;
use App\Core\Cache\Cache;
use App\Engine\UserActivity\UserPresenceLookup;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    sendMethodNotAllowed(['GET']);
}

$rawIds = trim((string) ($_GET['ids'] ?? ''));
if ($rawIds === '') {
    sendValidationError('En az bir kullanıcı kimliği gerekli.');
}

$rawParts = explode(',', $rawIds);
if (count($rawParts) > UserPresenceLookup::MAX_IDS_PER_BATCH) {
    sendValidationError('Tek istekte en fazla 100 kullanıcı sorgulanabilir.');
}

$userIds = [];
foreach ($rawParts as $rawPart) {
    $rawPart = trim($rawPart);
    if ($rawPart === '' || !ctype_digit($rawPart) || (int) $rawPart <= 0) {
        sendValidationError('Kullanıcı kimlikleri pozitif sayı olmalıdır.');
    }
    $userIds[(int) $rawPart] = (int) $rawPart;
}
$userIds = array_values($userIds);
sort($userIds, SORT_NUMERIC);

$viewerId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$rateIdentity = $viewerId > 0
    ? 'user:' . $viewerId
    : 'ip:' . hash('sha256', function_exists('getRealIp') ? getRealIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$rateLimitKey = 'public_presence:' . $rateIdentity;
$rateLimitMax = 180;
$rateLimitWindowMinutes = 1;

try {
    if (!checkRateLimit($rateLimitKey, $rateLimitMax, $rateLimitWindowMinutes)) {
        sendRateLimitError(getRateLimitRemainingSeconds($rateLimitKey, $rateLimitWindowMinutes));
    }
    incrementRateLimit($rateLimitKey, $rateLimitWindowMinutes);
} catch (Throwable $error) {
    if (function_exists('appLogException')) {
        appLogException($error, ['source' => 'Public presence rate limiter']);
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = requireDatabaseConnection($pdo ?? null);
$cacheKey = 'public_presence:v1:' . hash('sha256', implode(',', $userIds));
$cache = null;
$cached = null;
try {
    $candidate = Boot::container()->get(Cache::class);
    if ($candidate instanceof Cache) {
        $cache = $candidate;
        $cached = $cache->get($cacheKey);
    }
} catch (Throwable $error) {
    if (function_exists('appLogException')) {
        appLogException($error, ['source' => 'Public presence cache read']);
    }
}

if (is_array($cached) && isset($cached['users'], $cached['observed_at'])) {
    sendSuccess('OK', [
        'source' => 'http',
        'observed_at' => (int) $cached['observed_at'],
        'users' => (array) $cached['users'],
        'cached' => true,
    ]);
}

try {
    $users = userPresenceLookupUsers($pdo, $userIds);
    $observedAt = (int) floor(microtime(true) * 1000);
    $payload = [
        'observed_at' => $observedAt,
        'users' => $users,
    ];

    if ($cache instanceof Cache) {
        try {
            $tags = array_map(static fn (int $id): string => 'user-presence:' . $id, $userIds);
            $cache->set($cacheKey, $payload, 10, $tags);
        } catch (Throwable $error) {
            if (function_exists('appLogException')) {
                appLogException($error, ['source' => 'Public presence cache write']);
            }
        }
    }

    sendSuccess('OK', [
        'source' => 'http',
        'observed_at' => $observedAt,
        'users' => $users,
        'cached' => false,
    ]);
} catch (InvalidArgumentException $error) {
    sendValidationError($error->getMessage());
} catch (Throwable $error) {
    sendServerError('Presence bilgisi alınamadı.', $error);
}
