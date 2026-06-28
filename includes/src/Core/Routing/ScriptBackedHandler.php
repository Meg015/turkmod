<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;
use Throwable;

abstract class ScriptBackedHandler implements Handler
{
    public function __construct(private ?string $rootPath = null)
    {
    }

    final public function handle(Request $request): Response
    {
        $projectRoot = $this->resolveRootPath();
        $bufferLevel = ob_get_level();

        try {
            ob_start();
            $pdo = $GLOBALS['pdo'] ?? null;
            $baseUri = $GLOBALS['baseUri'] ?? '';
            $isLoggedIn = $GLOBALS['isLoggedIn'] ?? false;
            $envConfig = $GLOBALS['envConfig'] ?? [];
            $themeManager = $GLOBALS['themeManager'] ?? null;
            $_lay = $GLOBALS['_lay'] ?? [];
            require $projectRoot . '/' . ltrim($this->contentPath(), '/\\');
            $body = ob_get_clean();

            return new Response(is_string($body) ? $body : '', http_response_code() ?: 200);
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => static::class]);
            } else {
                error_log($exception->getMessage());
            }

            throw $exception;
        }
    }

    abstract protected function contentPath(): string;

    private function resolveRootPath(): string
    {
        $rootPath = $this->rootPath ?? dirname(__DIR__, 4);

        return rtrim($rootPath, '/\\');
    }
}
