<?php

declare(strict_types=1);

namespace App\Modules\Events\Api;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use InvalidArgumentException;
use Throwable;

final class EventsApiHandler implements Handler
{
    public function __construct(
        private string $projectRoot,
        private string $apiTemplate,
        private string $apiName,
    ) {
        $this->projectRoot = rtrim($projectRoot, "/\\");
        if ($this->projectRoot === '') {
            throw new InvalidArgumentException('Events API handler requires a project root.');
        }

        if (!str_contains($this->apiTemplate, '%s')) {
            throw new InvalidArgumentException('Events API handler requires an API template containing %s.');
        }

        $this->apiName = self::sanitizeApiName($this->apiName);
    }

    public static function fromTemplate(string $projectRoot, string $apiTemplate, string $rawApiName): self
    {
        return new self($projectRoot, $apiTemplate, $rawApiName);
    }

    public static function sanitizeApiName(string $rawApiName): string
    {
        return (string) preg_replace('/[^a-z0-9-]/', '', strtolower($rawApiName));
    }

    public function getApiName(): string
    {
        return $this->apiName;
    }

    public function resolveApiFile(): ?string
    {
        if ($this->apiName === '') {
            return null;
        }

        $root = realpath($this->projectRoot);
        if ($root === false) {
            return null;
        }

        $relativePath = ltrim(sprintf($this->apiTemplate, $this->apiName), "/\\");
        $target = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
        if ($target === false || !is_file($target)) {
            return null;
        }

        if ($target !== $root && !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $target;
    }

    public function exists(): bool
    {
        return $this->resolveApiFile() !== null;
    }

    public function handle(Request $request): Response
    {
        $apiFile = $this->resolveApiFile();
        if ($apiFile === null) {
            return new Response('', 404);
        }

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            $baseUri = $GLOBALS['baseUri'] ?? '';
            $isLoggedIn = $GLOBALS['isLoggedIn'] ?? false;
            $envConfig = $GLOBALS['envConfig'] ?? [];
            $themeManager = $GLOBALS['themeManager'] ?? null;
            include $apiFile;
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }

        $body = (string) ob_get_clean();
        $statusCode = http_response_code() ?: 200;

        return new Response($body, $statusCode);
    }
}
