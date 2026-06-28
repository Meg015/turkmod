<?php
declare(strict_types=1);

/**
 * Minimal file-based logger.
 *
 * Default log dir: <project>/storage/logs/
 * Set APP_LOG_DIR in .env to override.
 * Set APP_LOG_LEVEL in .env (debug|info|warning|error). Default: warning.
 *
 * Note: A separate DB-backed `appLog(?PDO, ...)` exists in includes/init.php
 * for activity logging to the `application_logs` table; the helpers here
 * intentionally use the `appFileLog` prefix to avoid that name collision.
 *
 * Usage:
 *   appFileLog('error', 'Something failed', ['context' => 'data']);
 *   appLogException($throwable, ['file' => __FILE__]);
 */

if (!defined('APP_LOG_LEVELS')) {
    define('APP_LOG_LEVELS', ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3]);
}

function appLogDir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $env = $GLOBALS['envConfig']['APP_LOG_DIR'] ?? '';
    $env = trim((string) $env);
    if ($env !== '') {
        $dir = rtrim($env, '/\\');
    } else {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Web erişimini engelle
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function appLogLevel(): int
{
    static $level = null;
    if ($level !== null) {
        return $level;
    }
    $raw = strtolower((string) ($GLOBALS['envConfig']['APP_LOG_LEVEL'] ?? 'warning'));
    $level = APP_LOG_LEVELS[$raw] ?? APP_LOG_LEVELS['warning'];
    return $level;
}

function appFileLog(string $level, string $message, array $context = []): void
{
    $levelKey = strtolower($level);
    $levelNum = APP_LOG_LEVELS[$levelKey] ?? APP_LOG_LEVELS['info'];
    if ($levelNum < appLogLevel()) {
        return;
    }

    $entry = [
        'ts' => date('c'),
        'level' => $levelKey,
        'msg' => $message,
        'ctx' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $path = appLogDir() . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function appLogException(Throwable $e, array $context = []): void
{
    $context['exception'] = [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode(),
    ];
    appFileLog('error', $e->getMessage(), $context);
}
