<?php
declare(strict_types=1);

namespace App\Core\Cache;

use InvalidArgumentException;
use Redis;
use RuntimeException;

class RedisCache implements TaggableCache
{
    private Redis $redis;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $password = '',
        private string $prefix = 'cache',
        float $timeout = 2.5,
        ?Redis $redis = null,
    ) {
        if ($prefix === '' || preg_match('/^[a-zA-Z0-9_.:-]+$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Redis cache prefix is invalid.');
        }
        if ($redis !== null) {
            $this->redis = $redis;
            return;
        }
        $this->redis = new Redis();
        $connected = $this->redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new RuntimeException('Redis cache connection could not be established.');
        }
        if ($password !== '' && !$this->redis->auth($password)) {
            throw new RuntimeException('Redis cache authentication failed.');
        }
    }

    private function entryKey(string $key): string
    {
        return $this->prefix . ':entry:' . hash('sha256', $key);
    }

    private function tagKey(string $tag): string
    {
        return $this->prefix . ':tag:' . $tag;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->entryKey($key));
        return $value === false ? $default : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0, array $tags = []): bool
    {
        $entryKey = $this->entryKey($key);
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->deleteEntry($entryKey);
        $stored = $ttlSeconds > 0
            ? $this->redis->setex($entryKey, $ttlSeconds, $encoded)
            : $this->redis->set($entryKey, $encoded);
        if (!$stored) {
            throw new RuntimeException('Redis cache entry could not be stored.');
        }
        $tagListKey = $entryKey . ':tags';
        $tags = array_unique(array_filter(array_map(static fn ($tag): string => trim((string) $tag), $tags), static fn (string $tag): bool => $tag !== ''));
        foreach ($tags as $tag) {
            $this->assertCommand($this->redis->sadd($tagListKey, $tag));
            $this->assertCommand($this->redis->sadd($this->tagKey($tag), $entryKey));
        }
        if ($tags !== [] && $ttlSeconds > 0) {
            $this->assertCommand($this->redis->expire($tagListKey, $ttlSeconds));
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $this->deleteEntry($this->entryKey($key));
        return true;
    }

    private function deleteEntry(string $entryKey): void
    {
        $tagListKey = $entryKey . ':tags';
        foreach ($this->members($tagListKey) as $tag) {
            $this->assertCommand($this->redis->srem($this->tagKey($tag), $entryKey));
        }
        $this->assertCommand($this->redis->del($entryKey, $tagListKey));
    }

    public function clear(): bool
    {
        // phpredis updates the cursor by reference and returns a flat key list.
        $cursor = null;
        do {
            $keys = $this->redis->scan($cursor, $this->prefix . ':*', 100);
            if (is_array($keys) && $keys !== []) {
                $this->assertCommand($this->redis->unlink($keys));
            }
        } while ((string) $cursor !== '0');
        return true;
    }

    public function invalidateTag(string $tag): bool
    {
        $tagKey = $this->tagKey($tag);
        foreach ($this->members($tagKey) as $entryKey) {
            $this->deleteEntry($entryKey);
        }
        $this->assertCommand($this->redis->del($tagKey));
        return true;
    }

    public function invalidateTags(array $tags): bool
    {
        foreach ($tags as $tag) {
            $this->invalidateTag((string) $tag);
        }
        return true;
    }

    private function members(string $key): array
    {
        $members = $this->redis->smembers($key);
        $this->assertCommand($members);
        return $members;
    }

    private function assertCommand(mixed $result): void
    {
        if ($result === false) {
            throw new RuntimeException('Redis cache command failed.');
        }
    }
}
