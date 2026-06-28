<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use InvalidArgumentException;
use RuntimeException;

final class MiddlewareChain implements Handler
{
    /**
     * @param array<int,Middleware|string> $middleware
     */
    public function __construct(
        private array $middleware,
        private Handler $handler,
        private ?Container $container = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $next = $this->handler;

        for ($index = count($this->middleware) - 1; $index >= 0; $index--) {
            $resolved = $this->resolveMiddleware($this->middleware[$index]);
            $next = new class($resolved, $next) implements Handler {
                public function __construct(
                    private Middleware $middleware,
                    private Handler $next,
                ) {
                }

                public function handle(Request $request): Response
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $next->handle($request);
    }

    private function resolveMiddleware(Middleware|string $middleware): Middleware
    {
        if ($middleware instanceof Middleware) {
            return $middleware;
        }

        $middleware = trim($middleware);
        if ($middleware === '') {
            throw new InvalidArgumentException('Middleware class name cannot be empty.');
        }

        if (!class_exists($middleware) || !is_a($middleware, Middleware::class, true)) {
            throw new InvalidArgumentException('Middleware must implement ' . Middleware::class . ': ' . $middleware);
        }

        $resolved = $this->container instanceof Container
            ? $this->container->get($middleware)
            : new $middleware();

        if (!$resolved instanceof Middleware) {
            throw new RuntimeException('Resolved middleware must implement ' . Middleware::class . ': ' . $middleware);
        }

        return $resolved;
    }
}
