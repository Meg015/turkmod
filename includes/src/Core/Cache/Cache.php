<?php

declare(strict_types=1);

namespace App\Core\Cache;

interface Cache
{
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param array<int,string> $tags
     */
    public function set(string $key, mixed $value, int $ttlSeconds = 0, array $tags = []): bool;

    public function delete(string $key): bool;

    public function clear(): bool;
}
