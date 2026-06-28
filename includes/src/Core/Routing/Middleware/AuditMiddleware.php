<?php

declare(strict_types=1);

namespace App\Core\Routing\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware;
use PDO;
use Throwable;

final class AuditMiddleware implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $next->handle($request);
            $this->writeLog($request, $response->getStatusCode(), $startedAt);

            return $response;
        } catch (Throwable $exception) {
            $this->writeLog($request, 500, $startedAt, $exception);
            throw $exception;
        }
    }

    private function writeLog(Request $request, int $statusCode, float $startedAt, ?Throwable $exception = null): void
    {
        if (!function_exists('appLog')) {
            return;
        }

        global $pdo;
        if (!($pdo ?? null) instanceof PDO) {
            return;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $context = [
            'method' => strtoupper($request->getMethod()),
            'path' => $request->getPath(),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'route_group' => (string) $request->getAttribute('route_group', ''),
        ];

        if ($exception instanceof Throwable) {
            $context['exception'] = $exception->getMessage();
            appLog($pdo, 'error', 'routing', 'route_dispatch_failed', $context);

            return;
        }

        appLog($pdo, 'info', 'routing', 'route_dispatch', $context);
    }
}
