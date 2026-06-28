<?php

declare(strict_types=1);

namespace App\Core\Security;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class DatabaseRateLimiter implements RateLimiter
{
    /**
     * @var array<string,bool>
     */
    private static array $schemaEnsured = [];

    public function __construct(
        private PDO $pdo,
        private string $scope = 'default',
        private string $table = 'request_rate_limits',
    ) {
        $this->scope = trim($this->scope);
        if ($this->scope === '') {
            throw new InvalidArgumentException('Rate limiter scope cannot be empty.');
        }

        $this->table = trim($this->table);
        if ($this->table === '' || preg_match('/^[A-Za-z0-9_]+$/', $this->table) !== 1) {
            throw new InvalidArgumentException('Rate limiter table name is invalid.');
        }

        $this->ensureSchema();
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

        $state = $this->readState($key);
        if ($state === null) {
            return false;
        }

        $expiresAt = strtotime((string) $state['expires_at']);
        if ($expiresAt !== false && $expiresAt <= time()) {
            $this->clear($key);

            return false;
        }

        return (int) ($state['attempt_count'] ?? 0) >= $limit;
    }

    public function hit(string $key, int $windowSeconds): void
    {
        if ($windowSeconds < 1) {
            return;
        }

        $normalizedKey = $this->normalizeKey($key);
        $expiresAt = date('Y-m-d H:i:s', time() + $windowSeconds);
        $tableName = $this->table;

        $sql = "INSERT INTO `{$tableName}` (scope, rate_key, attempt_count, first_attempt_at, last_attempt_at, expires_at, created_at, updated_at)
            VALUES (:scope, :rate_key, 1, NOW(), NOW(), :expires_at, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                attempt_count = CASE
                    WHEN expires_at IS NULL OR expires_at <= NOW() THEN 1
                    ELSE attempt_count + 1
                END,
                first_attempt_at = CASE
                    WHEN expires_at IS NULL OR expires_at <= NOW() THEN NOW()
                    ELSE first_attempt_at
                END,
                last_attempt_at = NOW(),
                expires_at = VALUES(expires_at),
                updated_at = NOW()";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'scope' => $this->scope,
                'rate_key' => $normalizedKey,
                'expires_at' => $expiresAt,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Rate limiter hit could not be persisted.', 0, $exception);
        }
    }

    public function availableIn(string $key, int $windowSeconds): int
    {
        if ($windowSeconds < 1) {
            return 0;
        }

        $state = $this->readState($key);
        if ($state === null || empty($state['expires_at'])) {
            return 0;
        }

        $expiresAt = strtotime((string) $state['expires_at']);
        if ($expiresAt === false) {
            return 0;
        }

        if ($expiresAt <= time()) {
            $this->clear($key);

            return 0;
        }

        return max(0, $expiresAt - time());
    }

    public function clear(string $key): void
    {
        $tableName = $this->table;

        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM `{$tableName}` WHERE scope = :scope AND rate_key = :rate_key",
            );
            $stmt->execute([
                'scope' => $this->scope,
                'rate_key' => $this->normalizeKey($key),
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Rate limiter state could not be cleared.', 0, $exception);
        }
    }

    /**
     * @return array{attempt_count:int,expires_at:?string}|null
     */
    private function readState(string $key): ?array
    {
        $tableName = $this->table;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT attempt_count, expires_at FROM `{$tableName}` WHERE scope = :scope AND rate_key = :rate_key LIMIT 1",
            );
            $stmt->execute([
                'scope' => $this->scope,
                'rate_key' => $this->normalizeKey($key),
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
            'expires_at' => isset($row['expires_at']) ? (string) $row['expires_at'] : null,
        ];
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'empty-key';
        }

        if (strlen($key) <= 191) {
            return $key;
        }

        return 'sha256:' . hash('sha256', $key);
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
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `scope` varchar(100) NOT NULL,
            `rate_key` varchar(191) NOT NULL,
            `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
            `first_attempt_at` timestamp NULL DEFAULT NULL,
            `last_attempt_at` timestamp NULL DEFAULT NULL,
            `expires_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `scope_key_unique` (`scope`, `rate_key`),
            KEY `expires_index` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
            self::$schemaEnsured[$this->table] = true;
        } catch (PDOException $exception) {
            throw new RuntimeException('Rate limiter schema could not be created.', 0, $exception);
        }
    }
}
