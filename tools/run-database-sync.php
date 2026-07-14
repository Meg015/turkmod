<?php

declare(strict_types=1);

use App\Core\Database\DatabaseSyncService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
require_once $root . '/includes/autoloader.php';
require_once $root . '/includes/lib/logger.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/ErrorHandler.php';

if (!function_exists('safeErrorMessage')) {
    function safeErrorMessage(Throwable $exception, string $fallback = 'Operation failed.'): string
    {
        return $exception->getMessage() !== '' ? $exception->getMessage() : $fallback;
    }
}

$apply = !in_array('--preview', $argv, true);
$report = (new DatabaseSyncService(projectRoot: $root))->run($apply);
echo json_encode([
    'status' => $report['status'] ?? 'error',
    'apply' => $apply,
    'summary' => $report['summary'] ?? [],
    'errors' => $report['errors'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(($report['status'] ?? '') === 'error' ? 1 : 0);
