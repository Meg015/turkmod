<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use RuntimeException;

final class SqlFileMigration implements Migration
{
    public function __construct(
        private string $filePath,
        private ?string $migrationName = null,
    ) {
        $this->filePath = rtrim($this->filePath);
        if ($this->filePath === '' || !is_file($this->filePath)) {
            throw new RuntimeException('SQL migration file not found: ' . $this->filePath);
        }
    }

    public function name(): string
    {
        if (is_string($this->migrationName) && $this->migrationName !== '') {
            return $this->migrationName;
        }

        return str_replace('\\', '/', $this->filePath);
    }

    public function up(PDO $pdo): void
    {
        $sql = file_get_contents($this->filePath);
        if (!is_string($sql)) {
            throw new RuntimeException('Unable to read SQL migration: ' . $this->filePath);
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            if ($this->shouldSkipStatement($pdo, $statement)) {
                continue;
            }

            $result = $pdo->exec($statement);
            if ($result === false) {
                $errorInfo = $pdo->errorInfo();
                $detail = isset($errorInfo[2]) && is_string($errorInfo[2]) && $errorInfo[2] !== ''
                    ? $errorInfo[2]
                    : 'unknown database error';

                throw new RuntimeException('SQL migration failed for ' . $this->name() . ': ' . $detail);
            }
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Rollback is not supported for SQL file migrations: ' . $this->name());
    }

    /**
     * @return array<int,string>
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;

        $lines = [];
        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lines[] = $line;
        }

        $sql = implode("\n", $lines);
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];

            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                if ($inSingleQuote && $index + 1 < $length && $sql[$index + 1] === "'") {
                    $buffer .= "''";
                    $index++;
                    continue;
                }

                $inSingleQuote = !$inSingleQuote;
                $buffer .= $char;
                continue;
            }

            if ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $inDoubleQuote = !$inDoubleQuote;
                $buffer .= $char;
                continue;
            }

            if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
                $buffer .= $char;
                continue;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function shouldSkipStatement(PDO $pdo, string $statement): bool
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement)) ?? trim($statement);
        if ($normalized === '') {
            return true;
        }

        if (
            preg_match('~^ALTER\s+TABLE\s+`?(?<table>[A-Za-z0-9_]+)`?\s+DROP\s+INDEX\s+`?(?<index>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && !$this->indexExists($pdo, (string) $matches['table'], (string) $matches['index'])
        ) {
            return true;
        }

        if (
            preg_match('~^ALTER\s+TABLE\s+`?(?<table>[A-Za-z0-9_]+)`?\s+ADD\s+(?:UNIQUE\s+|FULLTEXT\s+)?INDEX\s+`?(?<index>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && $this->indexExists($pdo, (string) $matches['table'], (string) $matches['index'])
        ) {
            return true;
        }

        if (
            preg_match('~^ALTER\s+TABLE\s+`?(?<table>[A-Za-z0-9_]+)`?\s+ADD\s+COLUMN\s+`?(?<column>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && $this->columnExists($pdo, (string) $matches['table'], (string) $matches['column'])
        ) {
            return true;
        }

        if (
            preg_match('~^ALTER\s+TABLE\s+`?(?<table>[A-Za-z0-9_]+)`?\s+DROP\s+COLUMN\s+`?(?<column>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && !$this->columnExists($pdo, (string) $matches['table'], (string) $matches['column'])
        ) {
            return true;
        }

        if (
            preg_match('~^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(?<table>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && $this->tableExists($pdo, (string) $matches['table'])
        ) {
            return true;
        }

        if (
            preg_match('~^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(?<table>[A-Za-z0-9_]+)`?~i', $normalized, $matches) === 1
            && !$this->tableExists($pdo, (string) $matches['table'])
        ) {
            return true;
        }

        return false;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $table = $this->normalizeIdentifier($table);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name',
        );
        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $table = $this->normalizeIdentifier($table);
        $index = $this->normalizeIdentifier($index);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name',
        );
        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $table = $this->normalizeIdentifier($table);
        $column = $this->normalizeIdentifier($column);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name',
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier, " \t\n\r\0\x0B`");
        if ($identifier === '' || preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Invalid SQL identifier in migration: ' . $identifier);
        }

        return $identifier;
    }
}
