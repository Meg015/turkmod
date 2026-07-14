<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class HealthCheckPage implements Handler
{
    public function __construct(
        private ?string $rootPath = null,
        private ?Closure $checksResolver = null,
        private ?Closure $clock = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return new JsonResponse([
                'status' => 'error',
                'checks' => [],
            ], 405, [
                'Allow' => 'GET, HEAD',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Robots-Tag' => 'noindex',
            ]);
        }

        $checks = $this->resolveChecks();
        $status = in_array('fail', $checks, true) ? 'degraded' : 'ok';
        $expiresAt = $this->now()->modify('+5 seconds');

        return new JsonResponse([
            'status' => $status,
            'checks' => $checks,
        ], 200, [
            'Cache-Control' => 'public, max-age=5',
            'Pragma' => 'public',
            'Expires' => $expiresAt->format('D, d M Y H:i:s') . ' GMT',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * @return array{db:string,cache:string,queue:string}
     */
    private function resolveChecks(): array
    {
        if ($this->checksResolver instanceof Closure) {
            $checks = ($this->checksResolver)();

            return $this->normalizeChecks(is_array($checks) ? $checks : []);
        }

        return [
            'db' => $this->databaseStatus(),
            'cache' => $this->cacheStatus(),
            'queue' => 'ok',
        ];
    }

    /**
     * @param array<string,mixed> $checks
     * @return array{db:string,cache:string,queue:string}
     */
    private function normalizeChecks(array $checks): array
    {
        return [
            'db' => $this->normalizeStatus($checks['db'] ?? 'fail'),
            'cache' => $this->normalizeStatus($checks['cache'] ?? 'fail'),
            'queue' => $this->normalizeStatus($checks['queue'] ?? 'fail'),
        ];
    }

    private function normalizeStatus(mixed $status): string
    {
        return $status === 'ok' ? 'ok' : 'fail';
    }

    private function databaseStatus(): string
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return 'fail';
        }

        try {
            $statement = $pdo->query('SELECT 1');
            if ($statement === false) {
                return 'fail';
            }

            return $statement->fetchColumn() !== false ? 'ok' : 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function cacheStatus(): string
    {
        $cachePath = $this->resolveRootPath() . '/storage/cache';

        return is_dir($cachePath) && is_writable($cachePath) ? 'ok' : 'fail';
    }

    private function now(): DateTimeImmutable
    {
        if ($this->clock instanceof Closure) {
            $now = ($this->clock)();
            if ($now instanceof DateTimeImmutable) {
                return $now->setTimezone(new DateTimeZone('UTC'));
            }
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function resolveRootPath(): string
    {
        $rootPath = $this->rootPath ?? dirname(__DIR__, 5);

        return rtrim($rootPath, '/\\');
    }
}
