<?php

declare(strict_types=1);

namespace App\Core\Security;

interface RateLimiter
{
    public function check(string $key, int $limit, int $windowSeconds): bool;

    public function tooManyAttempts(string $key, int $limit, int $windowSeconds): bool;

    public function hit(string $key, int $windowSeconds): void;

    public function availableIn(string $key, int $windowSeconds): int;

    public function clear(string $key): void;
}
