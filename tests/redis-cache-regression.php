<?php
declare(strict_types=1);

// The test double deliberately runs without the native extension: php -n tests/redis-cache-regression.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (extension_loaded('redis')) { throw new RuntimeException('Run this isolated regression with php -n.'); }

final class Redis
{
    public array $values = [];
    public array $sets = [];
    public array $ttls = [];
    public array $scanBatches = [];
    public ?string $fail = null;
    public static bool $connectionAllowed = true;
    public static bool $authAllowed = true;
    public function connect(string $host, int $port, float $timeout): bool { return self::$connectionAllowed; }
    public function auth(string $password): bool { return self::$authAllowed; }
    public function get(string $key): string|false {
        if ($this->fail === 'get') { throw new RuntimeException('Redis unavailable'); }
        return $this->values[$key] ?? false;
    }
    public function set(string $key, string $value): bool {
        if ($this->fail === 'set') { return false; }
        $this->values[$key] = $value;
        return true;
    }
    public function setex(string $key, int $ttl, string $value): bool {
        $this->ttls[$key] = $ttl;
        return $this->set($key, $value);
    }
    public function sadd(string $key, string $member): int|false {
        if ($this->fail === 'sadd') { return false; }
        $added = !isset($this->sets[$key][$member]);
        $this->sets[$key][$member] = true;
        return (int) $added;
    }
    public function smembers(string $key): array|false {
        return $this->fail === 'smembers' ? false : array_map('strval', array_keys($this->sets[$key] ?? []));
    }
    public function srem(string $key, string $member): int {
        $removed = isset($this->sets[$key][$member]);
        unset($this->sets[$key][$member]);
        return (int) $removed;
    }
    public function expire(string $key, int $ttl): bool { $this->ttls[$key] = $ttl; return true; }
    public function del(string ...$keys): int {
        foreach ($keys as $key) { unset($this->values[$key], $this->sets[$key], $this->ttls[$key]); }
        return count($keys);
    }
    public function unlink(array $keys): int|false { return $this->fail === 'unlink' ? false : $this->del(...$keys); }
    public function scan(?int &$cursor, string $pattern, int $count): array|false {
        if ($this->scanBatches !== []) {
            [$cursor, $keys] = array_shift($this->scanBatches);
            return $keys;
        }
        $cursor = 0;
        return array_values(array_filter(array_unique([...array_keys($this->values), ...array_keys($this->sets)]), static fn ($key) => fnmatch($pattern, $key)));
    }
}

require_once dirname(__DIR__) . '/includes/autoloader.php';
use App\Core\Cache\RedisCache;
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
};
$throws = static function (callable $action, string $label) use (&$checks): void {
    try { $action(); } catch (Throwable) { $checks++; return; }
    throw new RuntimeException($label);
};
$redis = new Redis();
$cache = new RedisCache(prefix: 'regression', redis: $redis);
$key = static fn (string $name): string => 'regression:entry:' . hash('sha256', $name);
$assert($cache->get('absent', 'missing') === 'missing', 'Cache miss lost default');
foreach ([null, false, 0, '', ['title' => 'Türkçe içerik']] as $value) {
    $cache->set('value', $value);
    $assert($cache->get('value', 'missing') === $value, 'Cached scalar/null did not round trip');
}
$cache->set('topic', ['id' => 1], 60, ['topics', 'category', '0']);
$assert($redis->ttls[$key('topic')] === 60 && $redis->ttls[$key('topic') . ':tags'] === 60, 'TTL not applied to entry and tag metadata');
$assert(in_array($key('topic'), $redis->smembers('regression:tag:0'), true), 'Numeric-string tag was discarded');
$cache->set('topic:tags', 'separate value');
$assert($cache->get('topic:tags') === 'separate value' && $cache->get('topic') === ['id' => 1], 'User key collides with internal tag metadata');
$cache->set('topic', 'new value', 0, ['new-tag']);
$assert(!in_array($key('topic'), $redis->smembers('regression:tag:topics'), true), 'Overwrite kept obsolete tag membership');
$cache->set('other-topic', 'other value', 0, ['new-tag', 'another-tag']);
$redis->sadd('tag:new-tag', 'foreign legacy data');
$cache->invalidateTag('new-tag');
$assert($cache->get('topic') === null && $cache->get('other-topic') === null, 'Tag invalidation left tagged entries');
$assert($redis->smembers('regression:tag:another-tag') === [], 'Tag invalidation left other tag memberships');
$assert($redis->smembers('tag:new-tag') === ['foreign legacy data'], 'Tag invalidation touched another namespace');
$cache->set('delete', 1, 0, ['delete-tag']);
$cache->delete('delete');
$assert($redis->smembers('regression:tag:delete-tag') === [], 'Delete left tag membership');
$cache->set('clear', 1, 0, ['clear-tag']);
$redis->set('other:entry:key', 'foreign');
$ownedKeys = array_values(array_filter([...array_keys($redis->values), ...array_keys($redis->sets)], static fn ($key) => str_starts_with($key, 'regression:')));
$redis->scanBatches = [[17, false], [0, $ownedKeys]];
$cache->clear();
$assert($cache->get('clear') === null && $cache->get('topic:tags') === null, 'SCAN stopped on an empty intermediate batch or used tuple results');
$assert($redis->get('other:entry:key') === 'foreign' && $redis->smembers('tag:new-tag') === ['foreign legacy data'], 'Clear removed foreign cache');
$redis->values[$key('corrupt')] = '{bad json';
$throws(fn () => $cache->get('corrupt'), 'Corrupt data was hidden as a cache miss');
foreach (['set', 'sadd', 'smembers', 'get', 'unlink'] as $failure) {
    $redis->fail = $failure;
    $throws(match ($failure) {
        'get' => fn () => $cache->get('key'),
        'unlink' => fn () => $cache->clear(),
        default => fn () => $cache->set('key', 1, 0, ['tag']),
    }, 'Redis failure was hidden: ' . $failure);
}
$redis->fail = null;
$throws(fn () => new RedisCache(prefix: 'prefix*', redis: $redis), 'Unsafe glob prefix accepted');
Redis::$connectionAllowed = false;
$throws(fn () => new RedisCache(), 'Failed connection was accepted');
Redis::$connectionAllowed = true;
Redis::$authAllowed = false;
$throws(fn () => new RedisCache(password: 'test'), 'Failed authentication was accepted');
echo "PASS: {$checks} Redis cache regression checks.\n";
