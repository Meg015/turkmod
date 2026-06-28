<?php

declare(strict_types=1);

namespace App\Core\Routing;

final class RouteGroup
{
    /**
     * @param array<string,array<string,mixed>> $routes
     * @param array<int,Middleware|string> $middleware
     */
    public function __construct(
        private string $name,
        private string $prefix = '',
        private array $middleware = [],
        private array $routes = [],
    ) {
        $this->prefix = self::normalizePrefix($this->prefix);
        $this->middleware = self::normalizeMiddleware($this->middleware);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return array<int,Middleware|string>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function addMiddleware(Middleware|string $middleware): void
    {
        $candidate = self::normalizeMiddleware([$middleware]);
        if ($candidate === []) {
            return;
        }

        $this->middleware[] = $candidate[0];
    }

    /**
     * @param array<string,mixed> $definition
     */
    public function add(string $path, array $definition): void
    {
        $normalizedPath = $this->normalizePath($path);
        $definition['path'] = $normalizedPath;
        $definition['group'] = $this->name;
        $definition['group_prefix'] = $this->prefix;
        $definition['group_middleware'] = $this->middleware;
        $this->routes[$normalizedPath] = $definition;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function isEmpty(): bool
    {
        return $this->routes === [];
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, "/\\ \t\n\r\0\x0B");
        if ($this->prefix === '') {
            return $path;
        }

        if ($path === '') {
            return $this->prefix;
        }

        if ($path === $this->prefix || str_starts_with($path, $this->prefix . '/')) {
            return $path;
        }

        return $this->prefix . '/' . $path;
    }

    /**
     * @param array<int,Middleware|string> $middleware
     * @return array<int,Middleware|string>
     */
    private static function normalizeMiddleware(array $middleware): array
    {
        $normalized = [];

        foreach ($middleware as $candidate) {
            if ($candidate instanceof Middleware) {
                $normalized[] = $candidate;
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values($normalized);
    }

    private static function normalizePrefix(string $prefix): string
    {
        return trim($prefix, "/\\ \t\n\r\0\x0B");
    }
}
