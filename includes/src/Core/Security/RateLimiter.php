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

final class FileRateLimiter implements RateLimiter
{
    public function __construct(
        private string $directory,
        private string $prefix = 'rate-limit',
    ) {
        $this->directory = rtrim($this->directory, DIRECTORY_SEPARATOR);
        if ($this->directory === '') {
            throw new \InvalidArgumentException('Rate limiter directory cannot be empty.');
        }

        if (!is_dir($this->directory) && !mkdir($concurrentDirectory = $this->directory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Rate limiter directory could not be created: ' . $this->directory);
        }
    }

    public function check(string $key, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            return false;
        }

        if ($this->tooManyAttempts($key, $limit, $windowSeconds)) {
            return false;
        }

        $this->hit($key, $windowSeconds);

        return true;
    }

    public function tooManyAttempts(string $key, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            return true;
        }

        $hits = $this->loadHits($key, $windowSeconds);

        return count($hits) >= $limit;
    }

    public function hit(string $key, int $windowSeconds): void
    {
        if ($windowSeconds < 1) {
            return;
        }

        $hits = $this->loadHits($key, $windowSeconds);
        $hits[] = time();
        $this->saveHits($key, $hits);
    }

    public function availableIn(string $key, int $windowSeconds): int
    {
        if ($windowSeconds < 1) {
            return 0;
        }

        $hits = $this->loadHits($key, $windowSeconds);
        if ($hits === []) {
            return 0;
        }

        $oldest = min($hits);

        return max(0, $windowSeconds - (time() - (int) $oldest));
    }

    public function clear(string $key): void
    {
        $path = $this->entryPath($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @return array<int,int>
     */
    private function loadHits(string $key, int $windowSeconds): array
    {
        $path = $this->entryPath($key);
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['hits']) || !is_array($data['hits'])) {
            $legacy = unserialize($raw, ['allowed_classes' => false]);
            if ($legacy === false && $raw !== serialize(false)) {
                return [];
            }
            if (!is_array($legacy) || !isset($legacy['hits']) || !is_array($legacy['hits'])) {
                return [];
            }
            $data = $legacy;
        }

        $cutoff = time() - $windowSeconds;

        return array_values(array_filter(
            array_map('intval', $data['hits']),
            static fn (int $timestamp): bool => $timestamp > $cutoff,
        ));
    }

    /**
     * @param array<int,int> $hits
     */
    private function saveHits(string $key, array $hits): void
    {
        $payload = [
            'hits' => array_values($hits),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('Rate limiter payload could not be encoded.');
        }

        $result = file_put_contents($this->entryPath($key), $encoded, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Rate limiter hit could not be stored.');
        }
    }

    private function entryPath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->prefix . '-' . hash('sha256', $key) . '.cache';
    }
}
