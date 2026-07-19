<?php

declare(strict_types=1);

/**
 * Cleanup expired request rate-limit rows.
 *
 * Usage:
 *   php cron/cleanup-expired-rate-limits.php
 *   php cron/cleanup-expired-rate-limits.php --help
 *
 * Suggested schedule (every 15 minutes):
 *   cron expression with minute step 15 + php /path/to/cron/cleanup-expired-rate-limits.php
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../admin/helpers.php';

$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo ?? null, 'cron_secret_key', '') : '';
    $providedSecret = $_GET['secret'] ?? '';

    if ($secretKey === '' || !hash_equals((string) $secretKey, (string) $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }

    header('Content-Type: text/plain; charset=utf-8');
}

$options = getopt('', ['help']);
if ($isCli && isset($options['help'])) {
    echo "Expired Rate Limit Cleanup Cron\n";
    echo "Usage: php cron/cleanup-expired-rate-limits.php\n";
    echo "Suggested schedule: */15 * * * * php /path/to/cron/cleanup-expired-rate-limits.php\n";
    exit(0);
}

$cronRunStatus = 'error';
$cronRunContext = ['reason' => 'cron_not_completed'];
$cronRunLogged = false;
$cronPdo = $pdo ?? null;
register_shutdown_function(static function () use (&$cronRunLogged, &$cronRunStatus, &$cronRunContext, &$cronPdo): void {
    if ($cronRunLogged || !($cronPdo instanceof PDO) || !function_exists('recordCronRun')) {
        return;
    }

    $cronRunLogged = true;
    recordCronRun($cronPdo, 'rate_limits_cleanup', $cronRunStatus, $cronRunContext);
});

if (!($pdo ?? null) instanceof PDO) {
    $cronRunStatus = 'error';
    $cronRunContext = ['reason' => 'database_connection_unavailable'];
    $message = "Database connection failed.\n";
    if ($isCli) {
        fwrite(STDERR, $message);
    } else {
        http_response_code(500);
        echo $message;
    }
    exit(1);
}

try {
    $stmt = $pdo->prepare('DELETE FROM request_rate_limits WHERE expires_at IS NOT NULL AND expires_at <= NOW()');
    $stmt->execute();
    $deletedRows = (int) $stmt->rowCount();

    $cronRunStatus = 'success';
    $cronRunContext = [
        'deleted_rows' => $deletedRows,
    ];

    if ($deletedRows > 0 && function_exists('appLog')) {
        appLog($pdo, 'info', 'cron', 'rate_limit_cleanup', [
            'action' => 'cron_cleanup',
            'status' => 'success',
            'deleted' => $deletedRows,
            'job_key' => 'rate_limits_cleanup',
        ]);
    }

    echo 'Expired rate limit rows deleted: ' . $deletedRows . "\n";
    exit(0);
} catch (Throwable $e) {
    $cronRunStatus = 'error';
    $cronRunContext = [
        'error' => $e->getMessage(),
    ];

    if (function_exists('appLogException')) {
        appLogException($e, ['source' => 'cron/cleanup-expired-rate-limits.php']);
    }

    $message = 'Cleanup failed: ' . $e->getMessage() . "\n";
    if ($isCli) {
        fwrite(STDERR, $message);
    } else {
        http_response_code(500);
        echo $message;
    }
    exit(1);
}
