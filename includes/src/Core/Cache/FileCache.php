<?php

declare(strict_types=1);

namespace App\Core\Cache;

use InvalidArgumentException;
use RuntimeException;

final class FileCache implements TaggableCache
{
    public function __construct(
        private string $directory,
        private string $prefix = 'cache',
    ) {
        $this->directory = rtrim($this->directory, DIRECTORY_SEPARATOR);
        if ($this->directory === '') {
            throw new InvalidArgumentException('Cache directory cannot be empty.');
        }

        if (!is_dir($this->directory) && !mkdir($concurrentDirectory = $this->directory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Cache directory could not be created: ' . $this->directory);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->readEntry($key);
        if ($entry === null) {
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0, array $tags = []): bool
    {
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $entry = [
            'value' => $value,
            'expires_at' => $expiresAt,
            'tags' => array_values(array_unique(array_filter(array_map('strval', $tags), static fn (string $tag): bool => trim($tag) !== ''))),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return false;
        }

        $result = file_put_contents($this->entryPath($key), $encoded, LOCK_EX);

        return $result !== false;
    }

    public function delete(string $key): bool
    {
        return $this->deletePath($this->entryPath($key));
    }

    public function clear(): bool
    {
        $success = true;
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . $this->prefix . '-*.cache') ?: [] as $path) {
            if (!$this->deletePath($path)) {
                $success = false;
            }
        }

        return $success;
    }

    public function invalidateTag(string $tag): bool
    {
        $success = true;
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . $this->prefix . '-*.cache') ?: [] as $path) {
            $entry = $this->readEntryFromPath($path);
            if ($entry === null) {
                continue;
            }

            if (in_array($tag, $entry['tags'], true) && !$this->deletePath($path)) {
                $success = false;
            }
        }

        return $success;
    }

    public function invalidateTags(array $tags): bool
    {
        $success = true;
        foreach ($tags as $tag) {
            if (!is_string($tag) || trim($tag) === '') {
                continue;
            }

            if (!$this->invalidateTag($tag)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @return array{value:mixed,expires_at:int,tags:array<int,string>}|null
     */
    private function readEntry(string $key): ?array
    {
        return $this->readEntryFromPath($this->entryPath($key));
    }

    /**
     * @return array{value:mixed,expires_at:int,tags:array<int,string>}|null
     */
    private function readEntryFromPath(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $entry = json_decode($raw, true);
        if (!is_array($entry) || !array_key_exists('value', $entry)) {
            $legacy = unserialize($raw, ['allowed_classes' => false]);
            if ($legacy === false && $raw !== serialize(false)) {
                return null;
            }
            if (!is_array($legacy) || !array_key_exists('value', $legacy)) {
                return null;
            }
            $entry = $legacy;
        }

        $expiresAt = (int) ($entry['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            $this->deletePath($path);

            return null;
        }

        $tags = $entry['tags'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        }

        return [
            'value' => $entry['value'],
            'expires_at' => $expiresAt,
            'tags' => array_values(array_filter(array_map('strval', $tags), static fn (string $tag): bool => trim($tag) !== '')),
        ];
    }

    private function entryPath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->prefix . '-' . hash('sha256', $key) . '.cache';
    }

    private function deletePath(string $path): bool
    {
        if (!is_file($path)) {
            return true;
        }

        if (@unlink($path)) {
            return true;
        }

        clearstatcache(true, $path);

        return !is_file($path);
    }
}
