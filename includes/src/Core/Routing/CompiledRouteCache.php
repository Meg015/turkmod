<?php

declare(strict_types=1);

namespace App\Core\Routing;

final class CompiledRouteCache
{
    public function __construct(private string $cacheFile)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadIfFresh(string $signature): ?array
    {
        $payload = $this->readPayload();
        if ($payload === []) {
            return null;
        }

        if (!hash_equals((string) ($payload['signature'] ?? ''), $signature)) {
            return null;
        }

        $routes = $payload['routes'] ?? null;
        return is_array($routes) ? $routes : null;
    }

    /**
     * @param array<string,mixed> $routes
     */
    public function store(array $routes, string $signature): void
    {
        $directory = dirname($this->cacheFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $payload = [
            'signature' => $signature,
            'generated_at' => gmdate('c'),
            'routes' => $routes,
        ];

        $compiled = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
        $tempFile = $this->cacheFile . '.tmp';
        if (file_put_contents($tempFile, $compiled, LOCK_EX) === false) {
            return;
        }

        if (is_file($tempFile)) {
            rename($tempFile, $this->cacheFile);
        }
    }

    public function clear(): void
    {
        if (is_file($this->cacheFile)) {
            if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readPayload(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }

        $payload = require $this->cacheFile;
        if (!is_array($payload)) {
            return [];
        }

        return $payload;
    }
}
