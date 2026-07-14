<?php

declare(strict_types=1);

/**
 * Canonical database-backed rate-limit helper functions.
 */

function coreRateLimitWindowCacheKey(): string
{
    return '_rate_limit_windows';
}

function coreRateLimitRememberWindow(string $key, int $decayMinutes): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[coreRateLimitWindowCacheKey()][$key] = max(1, $decayMinutes);
    }
}

function coreRateLimitResolveWindow(string $key, int $defaultMinutes = 15): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return max(1, $defaultMinutes);
    }

    return max(1, (int) ($_SESSION[coreRateLimitWindowCacheKey()][$key] ?? $defaultMinutes));
}

function coreRateLimitWindowSeconds(int $decayMinutes): int
{
    return max(1, $decayMinutes) * 60;
}

function coreRateLimitLog(Throwable $exception, array $context = []): void
{
    if (function_exists('appLogException')) {
        appLogException($exception, $context);
        return;
    }

    error_log('Rate limit operation failed: ' . $exception->getMessage());
}

function coreRateLimiter(): \App\Core\Security\RateLimiter
{
    $limiter = \App\Core\Bootstrap\Boot::container()->get(\App\Core\Security\RateLimiter::class);
    if (!$limiter instanceof \App\Core\Security\RateLimiter) {
        throw new RuntimeException('Database rate limiter is not available.');
    }

    return $limiter;
}

if (!function_exists('checkRateLimit')) {
    function checkRateLimit(string $key, int $limit, int $window): bool
    {
        $limit = max(1, $limit);
        $window = max(1, $window);
        coreRateLimitRememberWindow($key, $window);

        return !coreRateLimiter()->tooManyAttempts($key, $limit, coreRateLimitWindowSeconds($window));
    }
}

if (!function_exists('incrementRateLimit')) {
    function incrementRateLimit(string $key, ?int $window = null): void
    {
        $window = $window !== null ? max(1, $window) : coreRateLimitResolveWindow($key);
        coreRateLimitRememberWindow($key, $window);
        coreRateLimiter()->hit($key, coreRateLimitWindowSeconds($window));
    }
}

if (!function_exists('getRateLimitRemainingSeconds')) {
    function getRateLimitRemainingSeconds(string $key, int $window): int
    {
        $window = max(1, $window);
        coreRateLimitRememberWindow($key, $window);

        return coreRateLimiter()->availableIn($key, coreRateLimitWindowSeconds($window));
    }
}

if (!function_exists('resetRateLimit')) {
    function resetRateLimit(string $key): void
    {
        coreRateLimiter()->clear($key);
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[coreRateLimitWindowCacheKey()][$key]);
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
        throw new Exception('Rate limit exceeded. Please try again later.', 429);
    }
}
