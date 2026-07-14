<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class DatabaseConnection
{
    private static ?PDO $pdo = null;
    private static array $envCache = [];
    private static ?string $lastError = null;
    private static bool $logQueries = false;

    /**
     * Parse and return .env configuration.
     */
    public static function getEnvConfig(): array
    {
        if (!empty(self::$envCache)) {
            return self::$envCache;
        }

        $envPath = dirname(__DIR__, 3) . '/.env';
        if (!file_exists($envPath)) {
            return self::$envCache;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            self::$envCache[$key] = $value;
        }

        return self::$envCache;
    }

    public static function connection(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $env = self::getEnvConfig();

        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $database = $env['DB_DATABASE'] ?? 'turkmod';
        $username = $env['DB_USERNAME'] ?? 'root';
        $password = $env['DB_PASSWORD'] ?? '';
        $persistent = in_array(strtolower((string) ($env['DB_PERSISTENT'] ?? 'false')), ['1', 'true', 'yes', 'on'], true);
        $compressed = in_array(strtolower((string) ($env['DB_COMPRESS'] ?? 'false')), ['1', 'true', 'yes', 'on'], true);

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => $persistent,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                PDO::ATTR_TIMEOUT => 5,
            ];

            if ($compressed) {
                $options[PDO::MYSQL_ATTR_COMPRESS] = true;
            }

            self::$pdo = new PDO($dsn, $username, $password, $options);

            self::$lastError = null;
        } catch (PDOException $e) {
            self::$pdo = null;
            self::$lastError = $e->getMessage();
            error_log('Database connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function enableQueryLogging(bool $enable = true): void
    {
        self::$logQueries = $enable;
    }

    public static function isQueryLoggingEnabled(): bool
    {
        return self::$logQueries;
    }
}
