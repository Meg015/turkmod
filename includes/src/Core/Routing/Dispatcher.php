<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use InvalidArgumentException;
use RuntimeException;

final class Dispatcher
{
    public function __construct(private ?Container $container = null)
    {
    }

    /**
     * @param array<int,Middleware|string> $middleware
     */
    public function dispatch(Request $request, string|Handler|FileHandler $target, array $middleware = []): Response
    {
        $handler = $this->resolveHandler($target);
        if ($middleware === []) {
            return $handler->handle($request);
        }

        return (new MiddlewareChain($middleware, $handler, $this->container))->handle($request);
    }

    public function emit(Response $response): void
    {
        $response->send();
    }

    private function resolveHandler(string|Handler|FileHandler $target): Handler
    {
        if ($target instanceof FileHandler) {
            return $target;
        }

        if ($target instanceof Handler) {
            return $target;
        }

        if (class_exists($target) && is_a($target, Handler::class, true)) {
            $handler = $this->container instanceof Container
                ? $this->container->get($target)
                : new $target();

            if (!$handler instanceof Handler) {
                throw new RuntimeException('Route handler must implement Handler: ' . $target);
            }

            return $handler;
        }

        if (is_file($target)) {
            return FileHandler::for($target);
        }

        throw new InvalidArgumentException('Unsupported route target: ' . $target);
    }
}
