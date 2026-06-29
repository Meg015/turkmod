<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $tableName = 'core_migrations',
    ) {
    }

    public function apply(Migration $migration): void
    {
        if ($this->isApplied($migration)) {
            return;
        }

        $this->ensureTable();

        try {
            $this->pdo->beginTransaction();
            $migration->up($this->pdo);
            $this->recordApplied($migration);
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            error_log('Migration apply failed for ' . $migration->name() . ': ' . $exception->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function rollback(Migration $migration): void
    {
        if (!$this->isApplied($migration)) {
            return;
        }

        $this->ensureTable();

        try {
            $this->pdo->beginTransaction();
            $migration->down($this->pdo);
            $this->removeApplied($migration);
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            error_log('Migration rollback failed for ' . $migration->name() . ': ' . $exception->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function isApplied(Migration $migration): bool
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE migration_name = :migration_name',
        );
        $stmt->execute(['migration_name' => $migration->name()]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<int,string>
     */
    public function allApplied(): array
    {
        $this->ensureTable();

        $stmt = $this->pdo->query(
            'SELECT migration_name FROM ' . $this->tableName . ' ORDER BY applied_at ASC, migration_name ASC',
        );

        return $stmt ? array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }

    private function ensureTable(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `migration_name` VARCHAR(191) NOT NULL PRIMARY KEY,
                `applied_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $this->tableName,
        );

        if ($this->pdo->exec($sql) === false) {
            throw new RuntimeException('Unable to ensure migration tracking table exists.');
        }
    }

    private function recordApplied(Migration $migration): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $this->tableName . ' (migration_name, applied_at) VALUES (:migration_name, :applied_at)',
        );
        $stmt->execute([
            'migration_name' => $migration->name(),
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function removeApplied(Migration $migration): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . $this->tableName . ' WHERE migration_name = :migration_name',
        );
        $stmt->execute(['migration_name' => $migration->name()]);
    }
}
