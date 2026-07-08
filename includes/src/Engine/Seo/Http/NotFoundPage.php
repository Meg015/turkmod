<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use Throwable;

final class NotFoundPage implements Handler
{
    public function __construct(
        private ?string $rootPath = null,
        private ?string $baseUri = null,
        private ?Closure $legacyMapResolver = null,
        private ?Closure $legacyRedirectResolver = null,
        private ?Closure $bodyRenderer = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $redirectUrl = $this->resolveRedirectUrl($request);
        if ($redirectUrl !== null) {
            return new Response('', 301, [
                'Location' => $redirectUrl,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        return new Response($this->renderBody($request), 404, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    private function resolveRedirectUrl(Request $request): ?string
    {
        $mappedRedirect = $this->resolveMappedRedirectUrl($request);
        if ($mappedRedirect !== null) {
            return $mappedRedirect;
        }

        return $this->resolveLegacyContentRedirectUrl($request);
    }

    private function resolveMappedRedirectUrl(Request $request): ?string
    {
        $target = null;
        if ($this->legacyMapResolver instanceof Closure) {
            $target = ($this->legacyMapResolver)($request);
        } elseif (function_exists('routeLegacyRedirectTarget')) {
            $target = routeLegacyRedirectTarget($request->getUri());
        }

        if ($target === null) {
            return null;
        }

        $normalizedTarget = trim((string) $target, '/');
        if ($normalizedTarget === $this->currentRoutePath($request)) {
            return null;
        }

        $baseUri = rtrim($this->resolveBaseUri(), '/');
        $redirect = $baseUri . '/';
        if ($normalizedTarget !== '') {
            $redirect .= $normalizedTarget;
        }

        return $redirect;
    }

    private function resolveLegacyContentRedirectUrl(Request $request): ?string
    {
        $result = null;
        if ($this->legacyRedirectResolver instanceof Closure) {
            $result = ($this->legacyRedirectResolver)($request);
        } elseif (function_exists('legacyRedirectResolve')) {
            $pdo = $GLOBALS['pdo'] ?? null;
            $result = legacyRedirectResolve($pdo instanceof PDO ? $pdo : null, $request->getUri());
        }

        if (is_string($result) && trim($result) !== '') {
            return $result;
        }

        if (
            is_array($result)
            && !empty($result['redirect'])
            && trim((string) ($result['target_url'] ?? '')) !== ''
        ) {
            return (string) $result['target_url'];
        }

        return null;
    }

    private function currentRoutePath(Request $request): string
    {
        $path = (string) parse_url($request->getUri(), PHP_URL_PATH);
        $path = trim(str_replace('\\', '/', $path), '/');
        $baseUri = trim($this->resolveBaseUri(), '/');

        if ($baseUri !== '' && ($path === $baseUri || str_starts_with($path, $baseUri . '/'))) {
            $path = ltrim(substr($path, strlen($baseUri)), '/');
        }

        return trim($path, '/');
    }

    private function resolveBaseUri(): string
    {
        if ($this->baseUri !== null) {
            return $this->baseUri;
        }

        return (string) ($GLOBALS['baseUri'] ?? '');
    }

    private function renderBody(Request $request): string
    {
        if ($this->bodyRenderer instanceof Closure) {
            return (string) ($this->bodyRenderer)($request);
        }

        return $this->renderLegacyBody();
    }

    private function renderLegacyBody(): string
    {
        $rootPath = $this->resolveRootPath();
        $bufferLevel = ob_get_level();

        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            $baseUri = $baseUri ?? $this->resolveBaseUri();
            $isLoggedIn = $GLOBALS['isLoggedIn'] ?? false;
            $envConfig = $GLOBALS['envConfig'] ?? [];
            $_lay = $GLOBALS['_lay'] ?? null;
            $pageTitle = '404 - Sayfa Bulunamadı';
            $pageKey = 'not_found';
            $pageCssFiles = ['assets/css/not-found.css'];

            ob_start();
            require $rootPath . '/includes/public-header.php';
            ?>

<main class="not-found-shell">
    <div class="not-found-container ui-container">
        <div class="not-found-card">
            <div class="not-found-visual">
                <div class="not-found-glitch" data-text="404">404</div>
                <div class="not-found-decor-circle-1"></div>
                <div class="not-found-decor-circle-2"></div>
            </div>
            
            <div class="not-found-content">
                <h1 class="not-found-title">Sayfa Bulunamadı</h1>
                <p class="not-found-desc">
                    Görünüşe göre uzayın derinliklerinde kaybolduk. Aradığın sayfa taşınmış, silinmiş veya hiç var olmamış olabilir.
                </p>
                
                <div class="not-found-actions">
                    <a href="<?= htmlspecialchars($baseUri) ?>/index.php" class="not-found-btn not-found-btn--primary">
                        <i class="bi bi-rocket-takeoff" aria-hidden="true"></i>
                        <span>Ana Sayfaya Dön</span>
                    </a>
                    <a href="<?= function_exists('categoryListUrl') ? htmlspecialchars(categoryListUrl()) : htmlspecialchars($baseUri . '/kategoriler') ?>" class="not-found-btn not-found-btn--secondary">
                        <i class="bi bi-grid" aria-hidden="true"></i>
                        <span>İçerikleri Keşfet</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

            <?php
            require $rootPath . '/includes/public-footer.php';

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => self::class]);
            } else {
                error_log($exception->getMessage());
            }

            throw $exception;
        }
    }

    private function resolveRootPath(): string
    {
        $rootPath = $this->rootPath ?? dirname(__DIR__, 5);

        return rtrim($rootPath, '/\\');
    }
}
