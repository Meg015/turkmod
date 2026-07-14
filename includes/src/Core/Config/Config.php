<?php

declare(strict_types=1);

namespace App\Core\Config;

final class Config
{
    /**
     * @var array<string,mixed>
     */
    private array $items;

    /**
     * @param array<string,mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Load configuration from .env merged over defaults.
     *
     * @param array<string,mixed> $defaults
     */
    public static function load(array $defaults = []): self
    {
        $env = class_exists(\App\Core\DatabaseConnection::class) ? \App\Core\DatabaseConnection::getEnvConfig() : [];

        return new self(array_replace_recursive($defaults, $env));
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current =& $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                $current = [];
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current =& $current[$segment];
        }

        $current = $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    public function merge(array $values): void
    {
        $this->items = array_replace_recursive($this->items, $values);
    }

    /**
     * @param array<string,mixed> $values
     */
    public function with(array $values): self
    {
        $clone = clone $this;
        $clone->merge($values);

        return $clone;
    }
}
