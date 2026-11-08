<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use Throwable;

final class NotFoundPage implements Handler
{
    public function __construct(
        private ?string $rootPath = null,
        private ?string $baseUri = null,
        private ?Closure $bodyRenderer = null,
        private bool $topicMissing = false,
    ) {
    }

    public function handle(Request $request): Response
    {
        return new Response($this->renderBody($request), 404, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
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

        return $this->renderBodyTemplate();
    }

    private function renderBodyTemplate(): string
    {
        $rootPath = $this->resolveRootPath();
        $bufferLevel = ob_get_level();

        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            $baseUri = $baseUri ?? $this->resolveBaseUri();
            $isLoggedIn = $GLOBALS['isLoggedIn'] ?? false;
            $envConfig = $GLOBALS['envConfig'] ?? [];
            $_lay = $GLOBALS['_lay'] ?? null;
            $notFoundTitle = $this->topicMissing ? 'Konu bulunamadı' : 'Sayfa bulunamadı';
            $notFoundDescription = $this->topicMissing
                ? 'Aradığınız konu kaldırılmış, yayından alınmış veya hiç paylaşılmamış olabilir. Keşfedilecek daha çok mod var.'
                : 'Bu bağlantı artık bir yere çıkmıyor. Sayfa taşınmış veya kaldırılmış olabilir. Yeni bir rota çizelim.';
            $pageTitle = '404 - ' . $notFoundTitle;
            $pageKey = 'not_found';
            $robotsMetaOverride = 'noindex, nofollow';
            $pageCssFiles = ['assets/css/not-found.css'];

            ob_start();
            require $rootPath . '/includes/public-header.php';
            require $rootPath . '/includes/partials/not-found.php';
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
