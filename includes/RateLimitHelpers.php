<?php

declare(strict_types=1);

/**
 * Canonical rate-limit helper functions.
 *
 * Runtime calls go through App\Core\Security\RateLimiter by default. The
 * shared request_rate_limits table is also used during early bootstrap or in
 * degraded environments where the Core container cannot be resolved.
 */

function coreRateLimitWindowCacheKey(): string
{
    return '_rate_limit_windows';
}

function coreRateLimitRememberWindow(string $key, int $decayMinutes): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION[coreRateLimitWindowCacheKey()][$key] = max(1, $decayMinutes);
}

function coreRateLimitResolveWindow(string $key, int $fallbackMinutes = 15): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return max(1, $fallbackMinutes);
    }

    return max(1, (int) ($_SESSION[coreRateLimitWindowCacheKey()][$key] ?? $fallbackMinutes));
}

function coreRateLimitWindowSeconds(int $decayMinutes): int
{
    return max(1, $decayMinutes) * 60;
}

function coreRateLimitScope(): string
{
    return 'default';
}

function coreRateLimitLog(Throwable $exception, array $context = []): void
{
    if (function_exists('appLogException')) {
        appLogException($exception, $context);

        return;
    }

    if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
        try {
            Logger::getInstance()->error('Rate limit operation failed', [
                'error' => $exception->getMessage(),
            ] + $context);

            return;
        } catch (Throwable $loggerException) {
            error_log('Rate limit logger failed: ' . $loggerException->getMessage());
        }
    }

    error_log('Rate limit operation failed: ' . $exception->getMessage());
}

function coreRateLimiter(): ?\App\Core\Security\RateLimiter
{
    try {
        if (!class_exists(\App\Core\Bootstrap\Boot::class)) {
            return null;
        }

        $limiter = \App\Core\Bootstrap\Boot::container()->get(\App\Core\Security\RateLimiter::class);

        return $limiter instanceof \App\Core\Security\RateLimiter ? $limiter : null;
    } catch (Throwable $exception) {
        coreRateLimitLog($exception, ['fn' => 'coreRateLimiter']);

        return null;
    }
}

/**
 * @return array{attempt_count:int,first_attempt_at:?string,expires_at:?string}
 */
function legacyRateLimitFetch(?PDO $pdo, string $key, string $scope = 'default'): array
{
    if (!$pdo) {
        return ['attempt_count' => 0, 'first_attempt_at' => null, 'expires_at' => null];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT attempt_count, first_attempt_at, expires_at FROM request_rate_limits WHERE scope = :scope AND rate_key = :rate_key LIMIT 1',
        );
        $stmt->execute(['scope' => $scope, 'rate_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? [
                'attempt_count' => (int) ($row['attempt_count'] ?? 0),
                'first_attempt_at' => isset($row['first_attempt_at']) ? (string) $row['first_attempt_at'] : null,
                'expires_at' => isset($row['expires_at']) ? (string) $row['expires_at'] : null,
            ]
            : ['attempt_count' => 0, 'first_attempt_at' => null, 'expires_at' => null];
    } catch (Throwable $exception) {
        coreRateLimitLog($exception, ['fn' => 'legacyRateLimitFetch', 'key' => $key]);

        return ['attempt_count' => 0, 'first_attempt_at' => null, 'expires_at' => null];
    }
}

function legacyRateLimitExpiry(int $decayMinutes): string
{
    return date('Y-m-d H:i:s', time() + coreRateLimitWindowSeconds($decayMinutes));
}

if (!function_exists('checkRateLimit')) {
    function checkRateLimit(string $key, int $limit, int $window): bool
    {
        global $pdo;

        $limit = max(1, $limit);
        $window = max(1, $window);
        coreRateLimitRememberWindow($key, $window);

        $limiter = coreRateLimiter();
        if ($limiter instanceof \App\Core\Security\RateLimiter) {
            try {
                return !$limiter->tooManyAttempts($key, $limit, coreRateLimitWindowSeconds($window));
            } catch (Throwable $exception) {
                coreRateLimitLog($exception, ['fn' => 'checkRateLimit', 'key' => $key]);
            }
        }

        $pdo = ($pdo ?? null) instanceof PDO
            ? $pdo
            : (class_exists(\App\Core\Database::class) ? \App\Core\Database::connection() : null);
        $data = legacyRateLimitFetch($pdo, $key, coreRateLimitScope());
        $expiresAt = !empty($data['expires_at']) ? strtotime((string) $data['expires_at']) : null;
        if ($expiresAt !== null && $expiresAt <= time()) {
            resetRateLimit($key);

            return true;
        }

        return (int) ($data['attempt_count'] ?? 0) < $limit;
    }
}

if (!function_exists('incrementRateLimit')) {
    function incrementRateLimit(string $key, ?int $window = null): void
    {
        global $pdo;

        $window = $window !== null ? max(1, $window) : coreRateLimitResolveWindow($key, 15);
        coreRateLimitRememberWindow($key, $window);

        $limiter = coreRateLimiter();
        $mirrorRateLimitTable = true;
        if ($limiter instanceof \App\Core\Security\RateLimiter) {
            try {
                $limiter->hit($key, coreRateLimitWindowSeconds($window));
                // Database backend already persists into request_rate_limits.
                // For file backend we still mirror to DB so admin/rate-limits stays observable.
                if ($limiter instanceof \App\Core\Security\DatabaseRateLimiter) {
                    $mirrorRateLimitTable = false;
                }
            } catch (Throwable $exception) {
                coreRateLimitLog($exception, ['fn' => 'incrementRateLimit', 'key' => $key]);
            }
        }

        if (!$mirrorRateLimitTable) {
            return;
        }

        $pdo = ($pdo ?? null) instanceof PDO
            ? $pdo
            : (class_exists(\App\Core\Database::class) ? \App\Core\Database::connection() : null);
        if (!$pdo) {
            return;
        }

        try {
            $scope = coreRateLimitScope();
            $now = date('Y-m-d H:i:s');
            $existing = legacyRateLimitFetch($pdo, $key, $scope);
            $attemptCount = (int) ($existing['attempt_count'] ?? 0);
            $firstAttemptAt = !empty($existing['first_attempt_at']) ? (string) $existing['first_attempt_at'] : $now;
            $expiresAt = !empty($existing['expires_at']) ? strtotime((string) $existing['expires_at']) : null;

            if ($expiresAt !== null && $expiresAt <= time()) {
                $attemptCount = 0;
                $firstAttemptAt = $now;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO request_rate_limits (scope, rate_key, attempt_count, first_attempt_at, last_attempt_at, expires_at, created_at, updated_at)
                VALUES (:scope, :rate_key, :attempt_count, :first_attempt_at, :last_attempt_at, :expires_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE attempt_count = VALUES(attempt_count), first_attempt_at = VALUES(first_attempt_at), last_attempt_at = VALUES(last_attempt_at), expires_at = VALUES(expires_at), updated_at = NOW()',
            );
            $stmt->execute([
                'scope' => $scope,
                'rate_key' => $key,
                'attempt_count' => $attemptCount + 1,
                'first_attempt_at' => $firstAttemptAt,
                'last_attempt_at' => $now,
                'expires_at' => legacyRateLimitExpiry($window),
            ]);
        } catch (Throwable $exception) {
            coreRateLimitLog($exception, ['fn' => 'incrementRateLimit', 'key' => $key]);
        }
    }
}

if (!function_exists('getRateLimitRemainingSeconds')) {
    function getRateLimitRemainingSeconds(string $key, int $window): int
    {
        global $pdo;

        $window = max(1, $window);
        coreRateLimitRememberWindow($key, $window);

        $limiter = coreRateLimiter();
        if ($limiter instanceof \App\Core\Security\RateLimiter) {
            try {
                return $limiter->availableIn($key, coreRateLimitWindowSeconds($window));
            } catch (Throwable $exception) {
                coreRateLimitLog($exception, ['fn' => 'getRateLimitRemainingSeconds', 'key' => $key]);
            }
        }

        $pdo = ($pdo ?? null) instanceof PDO
            ? $pdo
            : (class_exists(\App\Core\Database::class) ? \App\Core\Database::connection() : null);
        $data = legacyRateLimitFetch($pdo, $key, coreRateLimitScope());
        if (empty($data['expires_at'])) {
            return 0;
        }

        $expiresAt = strtotime((string) $data['expires_at']);
        if ($expiresAt === false) {
            return 0;
        }

        return max(0, $expiresAt - time());
    }
}

if (!function_exists('resetRateLimit')) {
    function resetRateLimit(string $key): void
    {
        global $pdo;

        $limiter = coreRateLimiter();
        if ($limiter instanceof \App\Core\Security\RateLimiter) {
            try {
                $limiter->clear($key);
            } catch (Throwable $exception) {
                coreRateLimitLog($exception, ['fn' => 'resetRateLimit', 'key' => $key]);
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[coreRateLimitWindowCacheKey()][$key]);
        }

        $pdo = ($pdo ?? null) instanceof PDO
            ? $pdo
            : (class_exists(\App\Core\Database::class) ? \App\Core\Database::connection() : null);
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM request_rate_limits WHERE scope = :scope AND rate_key = :rate_key');
            $stmt->execute(['scope' => coreRateLimitScope(), 'rate_key' => $key]);
        } catch (Throwable $exception) {
            coreRateLimitLog($exception, ['fn' => 'resetRateLimit', 'key' => $key]);
        }
    }
}

if (!function_exists('isRateLimited')) {
    function isRateLimited(string $key, int $limit, int $window): void
    {
        if (checkRateLimit($key, $limit, $window)) {
            return;
        }

        $remaining = getRateLimitRemainingSeconds($key, $window);
        http_response_code(429);
        header('Retry-After: ' . $remaining);

        if (class_exists('Logger') && method_exists('Logger', 'getInstance')) {
            try {
                Logger::getInstance()->security('Rate limit exceeded', [
                    'key' => $key,
                    'limit' => $limit,
                    'window' => $window,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            } catch (Throwable $exception) {
                coreRateLimitLog($exception, ['fn' => 'isRateLimited', 'key' => $key]);
            }
        }

        throw new Exception('Rate limit exceeded. Please try again later.', 429);
    }
}
