<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container\Container;
use InvalidArgumentException;
use RuntimeException;

final class Dispatcher implements EventDispatcher
{
    /**
     * @var array<string,list<callable|Listener|string>>
     */
    private array $listeners = [];

    public function __construct(private ?Container $container = null)
    {
    }

    public function listen(string $eventName, callable|Listener|string $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
    }

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    public function clear(?string $eventName = null): void
    {
        if ($eventName === null) {
            $this->listeners = [];

            return;
        }

        unset($this->listeners[$eventName]);
    }

    public function dispatch(Event $event): Event
    {
        foreach ($this->listeners[$event->name()] ?? [] as $listener) {
            $this->invokeListener($listener, $event);
        }

        return $event;
    }

    private function invokeListener(callable|Listener|string $listener, Event $event): void
    {
        if (is_string($listener)) {
            $listener = $this->resolveListener($listener);
        }

        if ($listener instanceof Listener) {
            $listener->handle($event);

            return;
        }

        if (is_callable($listener)) {
            $listener($event);

            return;
        }

        throw new InvalidArgumentException('Unsupported listener type.');
    }

    private function resolveListener(string $listener): callable|Listener
    {
        if ($this->container instanceof Container && class_exists($listener)) {
            $resolved = $this->container->get($listener);
        } elseif (class_exists($listener)) {
            $resolved = new $listener();
        } else {
            throw new RuntimeException('Listener class not found: ' . $listener);
        }

        if ($resolved instanceof Listener || is_callable($resolved)) {
            return $resolved;
        }

        throw new RuntimeException('Listener must be invokable or implement Listener: ' . $listener);
    }
}
