<?php

declare(strict_types=1);

namespace App\Core\Cache;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class DatabaseCache implements TaggableCache
{
    /**
     * @var array<string,bool>
     */
    private static array $schemaEnsured = [];

    public function __construct(
        private PDO $pdo,
        private string $table = 'core_cache',
        private string $prefix = 'cache',
    ) {
        $this->table = trim($this->table);
        if ($this->table === '' || preg_match('/^[A-Za-z0-9_]+$/', $this->table) !== 1) {
            throw new InvalidArgumentException('Database cache table name is invalid.');
        }

        $this->prefix = trim($this->prefix);
        if ($this->prefix === '') {
            throw new InvalidArgumentException('Database cache prefix cannot be empty.');
        }

        $this->ensureSchema();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->fetchEntry($key);
        if ($entry === null) {
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0, array $tags = []): bool
    {
        $expiresAt = $ttlSeconds > 0 ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedValue)) {
            return false;
        }

        $normalizedTags = $this->normalizeTags($tags);
        $encodedTags = json_encode($normalizedTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedTags)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `{$this->table}` (cache_key, cache_value, tags, expires_at, created_at, updated_at)
                VALUES (:cache_key, :cache_value, :tags, :expires_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), tags = VALUES(tags), expires_at = VALUES(expires_at), updated_at = NOW()",
            );

            return $stmt->execute([
                'cache_key' => $this->entryKey($key),
                'cache_value' => $encodedValue,
                'tags' => $encodedTags,
                'expires_at' => $expiresAt,
            ]);
        } catch (PDOException) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE cache_key = :cache_key");

            return $stmt->execute(['cache_key' => $this->entryKey($key)]);
        } catch (PDOException) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM `{$this->table}` WHERE cache_key LIKE :prefix ESCAPE '\\\\'",
            );

            return $stmt->execute(['prefix' => $this->prefixPattern()]);
        } catch (PDOException) {
            return false;
        }
    }

    public function invalidateTag(string $tag): bool
    {
        $tag = trim($tag);
        if ($tag === '') {
            return true;
        }

        $keys = $this->keysForTag($tag);
        if ($keys === []) {
            return true;
        }

        $success = true;
        foreach ($keys as $key) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE cache_key = :cache_key");
                if (!$stmt->execute(['cache_key' => $key])) {
                    $success = false;
                }
            } catch (PDOException) {
                $success = false;
            }
        }

        return $success;
    }

    public function invalidateTags(array $tags): bool
    {
        $success = true;
        foreach ($this->normalizeTags($tags) as $tag) {
            if (!$this->invalidateTag($tag)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @return array{value:mixed,tags:array<int,string>}|null
     */
    private function fetchEntry(string $key): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT cache_value, tags, expires_at FROM `{$this->table}` WHERE cache_key = :cache_key LIMIT 1",
            );
            $stmt->execute(['cache_key' => $this->entryKey($key)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $expiresAtRaw = $row['expires_at'] ?? null;
        if (is_string($expiresAtRaw) && $expiresAtRaw !== '') {
            $expiresAt = strtotime($expiresAtRaw);
            if ($expiresAt !== false && $expiresAt <= time()) {
                $this->delete($key);

                return null;
            }
        }

        $decodedValue = json_decode((string) ($row['cache_value'] ?? ''), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $decodedTags = json_decode((string) ($row['tags'] ?? ''), true);
        $tags = is_array($decodedTags) ? $this->normalizeTags($decodedTags) : [];

        return [
            'value' => $decodedValue,
            'tags' => $tags,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function keysForTag(string $tag): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT cache_key, tags FROM `{$this->table}` WHERE cache_key LIKE :prefix ESCAPE '\\\\'",
            );
            $stmt->execute(['prefix' => $this->prefixPattern()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            $decodedTags = json_decode((string) ($row['tags'] ?? ''), true);
            if (!is_array($decodedTags)) {
                continue;
            }

            if (in_array($tag, $this->normalizeTags($decodedTags), true)) {
                $keys[] = (string) ($row['cache_key'] ?? '');
            }
        }

        return array_values(array_filter(array_unique($keys), static fn (string $cacheKey): bool => $cacheKey !== ''));
    }

    /**
     * @param array<int,string|int|float|bool> $tags
     * @return array<int,string>
     */
    private function normalizeTags(array $tags): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $tag): string => trim((string) $tag), $tags),
            static fn(string $tag): bool => $tag !== '',
        )));
    }

    private function entryKey(string $key): string
    {
        return $this->prefix . ':' . hash('sha256', $key);
    }

    private function prefixPattern(): string
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->prefix);

        return $prefix . ':%';
    }

    private function ensureSchema(): void
    {
        if (isset(self::$schemaEnsured[$this->table])) {
            return;
        }

        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            self::$schemaEnsured[$this->table] = true;
            return;
        }

        $tableName = $this->table;
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `cache_key` varchar(191) NOT NULL,
            `cache_value` longtext NOT NULL,
            `tags` longtext DEFAULT NULL,
            `expires_at` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`cache_key`),
            KEY `cache_expires_index` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
            self::$schemaEnsured[$this->table] = true;
        } catch (PDOException $exception) {
            throw new RuntimeException('Database cache schema could not be created.', 0, $exception);
        }
    }
}
