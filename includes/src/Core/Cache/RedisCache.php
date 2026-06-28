<?php

declare(strict_types=1);

namespace App\Core\Cache;

use Redis;
use RedisException;

class RedisCache implements TaggableCache
{
    private Redis $redis;
    private string $prefix;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $password = '',
        string $prefix = 'cache',
        float $timeout = 2.5,
        ?Redis $redis = null,
    ) {
        $this->prefix = $prefix;

        if ($redis !== null) {
            $this->redis = $redis;
        } else {
            $this->redis = new Redis();
            if ($password !== '') {
                $this->redis->connect($host, $port, $timeout, null, 0, $timeout, ['auth' => $password]);
            } else {
                $this->redis->connect($host, $port, $timeout);
            }
        }
    }

    private function entryKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    private function tagKey(string $tag): string
    {
        return 'tag:' . $tag;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->redis->get($this->entryKey($key));
            if ($value === false) {
                return $default;
            }
            $decoded = json_decode($value, true);

            return $decoded ?? $default;
        } catch (RedisException) {
            return $default;
        }
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0, array $tags = []): bool
    {
        try {
            $entryKey = $this->entryKey($key);
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return false;
            }

            if ($ttlSeconds > 0) {
                $this->redis->setex($entryKey, $ttlSeconds, $encoded);
            } else {
                $this->redis->set($entryKey, $encoded);
            }

            if (!empty($tags)) {
                $tagListKey = $entryKey . ':tags';
                foreach ($tags as $tag) {
                    $this->redis->sadd($tagListKey, $tag);
                    $this->redis->sadd($this->tagKey($tag), $entryKey);
                }
                if ($ttlSeconds > 0) {
                    $this->redis->expire($tagListKey, $ttlSeconds);
                }
            }

            return true;
        } catch (RedisException) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $entryKey = $this->entryKey($key);

            $tagListKey = $entryKey . ':tags';
            $tags = $this->redis->smembers($tagListKey);
            foreach ($tags as $tag) {
                $this->redis->srem($this->tagKey($tag), $entryKey);
            }

            $this->redis->del($entryKey, $tagListKey);

            return true;
        } catch (RedisException) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            $cursor = 0;
            $allKeys = [];

            do {
                $result = $this->redis->scan($cursor, $this->prefix . ':*', 100);
                if ($result === false) {
                    break;
                }
                $cursor = $result[0];
                $allKeys = array_merge($allKeys, $result[1]);
            } while ($cursor > 0);

            $cursor = 0;
            do {
                $result = $this->redis->scan($cursor, 'tag:*', 100);
                if ($result === false) {
                    break;
                }
                $cursor = $result[0];
                $allKeys = array_merge($allKeys, $result[1]);
            } while ($cursor > 0);

            if (!empty($allKeys)) {
                $this->redis->unlink($allKeys);
            }

            return true;
        } catch (RedisException) {
            return false;
        }
    }

    public function invalidateTag(string $tag): bool
    {
        try {
            $tagKey = $this->tagKey($tag);
            $members = $this->redis->smembers($tagKey);

            if (!empty($members)) {
                $keysToDelete = $members;
                foreach ($members as $member) {
                    $keysToDelete[] = $member . ':tags';
                }
                $this->redis->unlink($keysToDelete);
            }

            $this->redis->del($tagKey);

            return true;
        } catch (RedisException) {
            return false;
        }
    }

    public function invalidateTags(array $tags): bool
    {
        $success = true;
        foreach ($tags as $tag) {
            if (!$this->invalidateTag($tag)) {
                $success = false;
            }
        }

        return $success;
    }
}
