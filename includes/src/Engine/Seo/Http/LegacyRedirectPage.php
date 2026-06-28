<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;

final class LegacyRedirectPage implements Handler
{
    public function __construct(
        private ?string $rootPath = null,
        private ?Closure $legacyRedirectResolver = null,
        private ?Handler $notFoundHandler = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $result = $this->resolveLegacyRedirect($request);
        if (
            !empty($result['redirect'])
            && trim((string) ($result['target_url'] ?? '')) !== ''
        ) {
            return new Response('', 301, [
                'Location' => (string) $result['target_url'],
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        return $this->fallbackNotFound($request);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveLegacyRedirect(Request $request): array
    {
        $requestPath = $this->legacyRequestPath($request);
        if ($this->legacyRedirectResolver instanceof Closure) {
            $result = ($this->legacyRedirectResolver)($requestPath, $request);

            return is_array($result) ? $result : ['redirect' => false, 'target_url' => null];
        }

        if (!function_exists('legacyRedirectResolve')) {
            return ['redirect' => false, 'target_url' => null];
        }

        $pdo = $GLOBALS['pdo'] ?? null;
        $result = legacyRedirectResolve($pdo instanceof PDO ? $pdo : null, $requestPath);

        return is_array($result) ? $result : ['redirect' => false, 'target_url' => null];
    }

    private function legacyRequestPath(Request $request): string
    {
        $requestPath = $request->getUri();
        $query = $request->getQuery();

        if ($requestPath === '' && !empty($query['legacy_slug']) && !empty($query['legacy_id'])) {
            $prefix = ($query['type'] ?? '') === 'category' ? '/forums/' : '/konu/';
            $requestPath = $prefix . (string) $query['legacy_slug'] . '.' . (string) $query['legacy_id'] . '/';
        }

        return $requestPath;
    }

    private function fallbackNotFound(Request $request): Response
    {
        $handler = $this->notFoundHandler;
        if (!$handler instanceof Handler) {
            $handler = new NotFoundPage(
                $this->rootPath,
                null,
                null,
                static fn (Request $request): array => ['redirect' => false, 'target_url' => null],
            );
        }

        return $handler->handle($request);
    }
}
