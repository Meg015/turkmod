<?php

declare(strict_types=1);

namespace App\Core\Container;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

final class Container
{
    /**
     * @var array<string,array{concrete:callable|string, singleton:bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string,object>
     */
    private array $instances = [];

    public function bind(string $abstract, callable|string|object $concrete, bool $singleton = false): void
    {
        if (is_object($concrete) && !$concrete instanceof Closure) {
            $this->instance($abstract, $concrete);

            return;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, callable|string|object $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        unset($this->bindings[$abstract]);
    }

    public function override(string $abstract, object $instance): void
    {
        $this->instance($abstract, $instance);
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract)
            || interface_exists($abstract);
    }

    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolveBinding($abstract);
        }

        return $this->autowire($abstract);
    }

    public function make(string $abstract): object
    {
        return $this->get($abstract);
    }

    public function autowire(string $class): object
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) {
            throw new RuntimeException('Unable to reflect class: ' . $class, 0, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException('Class is not instantiable: ' . $class);
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveBinding(string $abstract): object
    {
        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        if (is_object($concrete) && !$concrete instanceof Closure) {
            $this->instances[$abstract] = $concrete;

            return $concrete;
        }

        if (is_string($concrete)) {
            if ($concrete === $abstract && class_exists($concrete)) {
                $resolved = $this->autowire($concrete);
            } else {
                $resolved = $this->get($concrete);
            }
        } else {
            $resolved = $concrete($this);
            if (!is_object($resolved)) {
                throw new RuntimeException('Container binding for ' . $abstract . ' must return an object.');
            }
        }

        if ($binding['singleton']) {
            $this->instances[$abstract] = $resolved;
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            try {
                return $this->get($type->getName());
            } catch (Throwable $exception) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                if ($parameter->allowsNull()) {
                    return null;
                }

                throw $exception;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        $declaringClass = $parameter->getDeclaringClass();
        $owner = $declaringClass?->getName() ?? 'unknown';
        throw new RuntimeException(
            'Unable to resolve parameter $' . $parameter->getName() . ' for ' . $owner,
        );
    }
}
