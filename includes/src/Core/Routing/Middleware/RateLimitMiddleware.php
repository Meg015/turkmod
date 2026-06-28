<?php

declare(strict_types=1);

namespace App\Core\Routing\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware;
use PDO;

final class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private int $maxAttempts = 60,
        private int $decayMinutes = 1,
        private string $keyPrefix = 'route',
    ) {
    }

    public function process(Request $request, Handler $next): Response
    {
        if (!function_exists('checkRateLimit')) {
            return $next->handle($request);
        }

        $key = $this->buildKey($request);
        if (!checkRateLimit($key, $this->maxAttempts, $this->decayMinutes)) {
            $remaining = function_exists('getRateLimitRemainingSeconds')
                ? (int) getRateLimitRemainingSeconds($key, $this->decayMinutes)
                : 0;
            $remaining = max(0, $remaining);

            global $pdo;
            if (($pdo ?? null) instanceof PDO && function_exists('logRateLimitExceeded')) {
                logRateLimitExceeded($pdo, $request->getPath(), 'router');
            }

            $headers = ['Retry-After' => (string) $remaining];
            if ($this->expectsJson($request)) {
                $headers['Content-Type'] = 'application/json; charset=utf-8';
                return new Response(
                    '{"success":false,"error":"rate_limit_exceeded","message":"Too many requests."}',
                    429,
                    $headers,
                );
            }

            $headers['Content-Type'] = 'text/plain; charset=utf-8';
            return new Response('Too many requests.', 429, $headers);
        }

        if (function_exists('incrementRateLimit')) {
            incrementRateLimit($key, $this->decayMinutes);
        }

        return $next->handle($request);
    }

    private function buildKey(Request $request): string
    {
        $ip = (string) $request->serverParam('REMOTE_ADDR', 'guest');
        $path = ltrim($request->getPath(), '/');
        if ($path === '') {
            $path = 'home';
        }

        return implode(':', [
            $this->keyPrefix,
            strtoupper($request->getMethod()),
            str_replace('/', '_', $path),
            $ip,
        ]);
    }

    private function expectsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'json')) {
            return true;
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if (str_contains($contentType, 'json')) {
            return true;
        }

        $path = ltrim($request->getPath(), '/');
        return str_starts_with($path, 'api/');
    }
}
