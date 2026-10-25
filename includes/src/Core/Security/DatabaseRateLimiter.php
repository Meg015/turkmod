<?php

declare(strict_types=1);

namespace App\Core\Security;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class DatabaseRateLimiter implements RateLimiter
{
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

        $this->assertSchemaReady();
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

        if ($state['expires_at_epoch'] === null || $state['expires_at_epoch'] <= $state['database_now_epoch']) {
            // Do not delete here: another request may have renewed this key after the read.
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
        $tableName = $this->table;

        $sql = "INSERT INTO `{$tableName}` (scope, rate_key, attempt_count, first_attempt_at, last_attempt_at, expires_at, created_at, updated_at)
            VALUES (:scope, :rate_key, 1, NOW(), NOW(), TIMESTAMPADD(SECOND, :window_seconds, NOW()), NOW(), NOW())
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
                'window_seconds' => $windowSeconds,
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
        if ($state === null || $state['expires_at_epoch'] === null) {
            return 0;
        }
        return max(0, $state['expires_at_epoch'] - $state['database_now_epoch']);
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
     * @return array{attempt_count:int,expires_at_epoch:?int,database_now_epoch:int}|null
     */
    private function readState(string $key): ?array
    {
        $tableName = $this->table;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT attempt_count, UNIX_TIMESTAMP(expires_at) AS expires_at_epoch,
                    UNIX_TIMESTAMP() AS database_now_epoch
                 FROM `{$tableName}` WHERE scope = :scope AND rate_key = :rate_key LIMIT 1",
            );
            $stmt->execute([
                'scope' => $this->scope,
                'rate_key' => $this->normalizeKey($key),
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new RuntimeException('Rate limiter state could not be read.', 0, $exception);
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
            'expires_at_epoch' => isset($row['expires_at_epoch']) ? (int) $row['expires_at_epoch'] : null,
            'database_now_epoch' => (int) $row['database_now_epoch'],
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

    private function assertSchemaReady(): void
    {
        try {
            // Check the actual table contract without a separate metadata permission/query.
            $stmt = $this->pdo->prepare("SELECT scope, rate_key, attempt_count, first_attempt_at,
                last_attempt_at, expires_at, created_at, updated_at FROM `{$this->table}` WHERE 1 = 0");
            $stmt->execute();
            $stmt->closeCursor();
        } catch (PDOException $exception) {
            throw new RuntimeException('Rate limiter schema readiness could not be verified.', 0, $exception);
        }
    }
}
