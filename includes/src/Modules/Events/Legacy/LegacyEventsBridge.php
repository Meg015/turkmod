<?php

declare(strict_types=1);

namespace App\Modules\Events\Legacy;

use RuntimeException;

final class LegacyEventsBridge
{
    public static function moduleRoot(): string
    {
        return dirname(__DIR__) . '/LegacyRuntime';
    }

    public static function requireInit(): void
    {
        self::requireFromModule('init.php');
    }

    public static function requireHelpers(): void
    {
        self::requireFromModule('helpers.php');
    }

    public static function requireTasks(): void
    {
        self::requireFromModule('tasks.php');
    }

    public static function requireSchema(): void
    {
        self::requireFromModule('schema.php');
    }

    public static function requirePage(string $page): void
    {
        $name = self::sanitizeName($page);
        self::requireFromModule('pages/' . $name . '.php');
    }

    public static function requireApi(string $apiName): void
    {
        $name = self::sanitizeName($apiName);
        self::requireFromModule('api/' . $name . '.php');
    }

    public static function requireAdmin(string $adminPage): void
    {
        $name = self::sanitizeName($adminPage);
        self::requireFromModule('admin/' . $name . '.php');
    }

    private static function sanitizeName(string $value): string
    {
        $name = trim(strtolower($value));
        if ($name === '' || preg_match('/^[a-z0-9_-]+$/', $name) !== 1) {
            throw new RuntimeException('Invalid legacy events alias segment: ' . $value);
        }

        return $name;
    }

    private static function requireFromModule(string $relativePath): void
    {
        $root = self::moduleRoot();
        $target = realpath($root . '/' . ltrim($relativePath, '/\\'));
        $moduleRoot = realpath($root);

        if ($target === false || $moduleRoot === false || !str_starts_with($target, $moduleRoot . DIRECTORY_SEPARATOR) || !is_file($target)) {
            throw new RuntimeException('Legacy events alias target not found: ' . $relativePath);
        }

        require_once $target;
    }
}
