<?php

declare(strict_types=1);

/**
 * Leaderboard Cache Update Cron Script
 *
 * This script automatically updates leaderboard cache for specified periods and categories.
 * It should be run via cron at regular intervals to keep leaderboard data fresh.
 *
 * Usage:
 *   php update-leaderboard-cache.php --period=daily [--category=downloads] [--dry-run] [--force]
 *
 * Parameters:
 *   --period      Required. Comma-separated list: daily, weekly, monthly, quarterly, yearly, all
 *   --category    Optional. Specific category or 'all' (default: all)
 *   --dry-run     Optional. Show what would be done without executing
 *   --force       Optional. Force recalculation even if cache is fresh
 *   --help        Show this help message
 *
 * Examples:
 *   php update-leaderboard-cache.php --period=daily
 *   php update-leaderboard-cache.php --period=weekly,monthly --category=downloads
 *   php update-leaderboard-cache.php --period=all --force
 *   php update-leaderboard-cache.php --period=daily --dry-run
 *
 * Recommended Cron Schedule:
 *   Every 15 minutes: php /path/to/cron/update-leaderboard-cache.php --period=daily
 *   Every hour: php /path/to/cron/update-leaderboard-cache.php --period=weekly
 *   Every 6 hours: php /path/to/cron/update-leaderboard-cache.php --period=monthly
 *   Daily at 3 AM: php /path/to/cron/update-leaderboard-cache.php --period=quarterly,yearly
 */

// Ensure script is run from command line
$isCli = php_sapi_name() === 'cli';

// Initialize
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Support/cache-manager.php';
require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Support/calculator.php';
require_once __DIR__ . '/../admin/helpers.php';

if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo, 'cron_secret_key', '') : '';
    $providedSecret = $_GET['secret'] ?? '';
    
    if ($secretKey === '' || !hash_equals((string) $secretKey, (string) $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }
    header('Content-Type: text/plain; charset=utf-8');
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
    recordCronRun($cronPdo, 'leaderboard_cache', $cronRunStatus, $cronRunContext);
});

if (!$isCli) {
    if (!isset($_GET['period'])) {
        $cronRunStatus = 'error';
        $cronRunContext = [
            'reason' => 'missing_period',
            'periods_input' => null,
        ];
        if (!$isCli) {
            http_response_code(422);
        }
        echo "Error: period parameter is required.\n\n";
        exit(1);
    }
    $periodsInput = $_GET['period'];
    $categoryInput = $_GET['category'] ?? 'all';
    $dryRun = isset($_GET['dry-run']);
    $force = isset($_GET['force']);
} else {
    // Parse command line arguments
    $options = getopt('', ['period:', 'category::', 'dry-run', 'force', 'help']);

    // Show help
    if (isset($options['help'])) {
        showHelp();
        exit(0);
    }

    // Validate required parameters
    if (!isset($options['period'])) {
        $cronRunStatus = 'error';
        $cronRunContext = [
            'reason' => 'missing_period',
            'periods_input' => null,
        ];
        echo "Error: --period parameter is required.\n\n";
        showHelp();
        exit(1);
    }

    // Parse parameters
    $periodsInput = $options['period'];
    $categoryInput = $options['category'] ?? 'all';
    $dryRun = isset($options['dry-run']);
    $force = isset($options['force']);
}

// Valid options
$validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
$validCategories = ['downloads', 'active', 'helpful', 'rising_star', 'quality'];

// Parse periods
$periods = [];
if ($periodsInput === 'all') {
    $periods = $validPeriods;
} else {
    $periods = array_map('trim', explode(',', $periodsInput));
    foreach ($periods as $period) {
        if (!in_array($period, $validPeriods, true)) {
            $cronRunStatus = 'error';
            $cronRunContext = [
                'reason' => 'invalid_period',
                'invalid_period' => $period,
                'periods_input' => $periodsInput,
            ];
            if (!$isCli) {
                http_response_code(422);
            }
            echo "Error: Invalid period '{$period}'. Valid periods: " . implode(', ', $validPeriods) . ", all\n";
            exit(1);
        }
    }
}

// Parse categories
$categories = [];
if ($categoryInput === 'all') {
    $categories = $validCategories;
} else {
    $categories = array_map('trim', explode(',', $categoryInput));
    foreach ($categories as $category) {
        if (!in_array($category, $validCategories, true)) {
            $cronRunStatus = 'error';
            $cronRunContext = [
                'reason' => 'invalid_category',
                'invalid_category' => $category,
                'category_input' => $categoryInput,
            ];
            if (!$isCli) {
                http_response_code(422);
            }
            echo "Error: Invalid category '{$category}'. Valid categories: " . implode(', ', $validCategories) . ", all\n";
            exit(1);
        }
    }
}

// Start execution
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "===========================================\n";
echo "Leaderboard Cache Update\n";
echo "===========================================\n";
echo "Started: {$timestamp}\n";
echo "Periods: " . implode(', ', $periods) . "\n";
echo "Categories: " . implode(', ', $categories) . "\n";
echo "Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE') . "\n";
echo "Force: " . ($force ? 'YES' : 'NO') . "\n";
echo "===========================================\n\n";

// Track results
$results = [];
$totalAffectedUsers = 0;
$totalOperations = 0;
$errors = [];

// Process each combination
foreach ($categories as $category) {
    foreach ($periods as $period) {
        $totalOperations++;
        $operationKey = "{$category}_{$period}";

        echo "Processing: {$category} / {$period}... ";

        try {
            if ($dryRun) {
                // Dry run: check if cache is stale
                $isStale = leaderboardIsCacheStale($pdo, $category, $period);
                $status = $isStale ? 'WOULD UPDATE' : 'SKIP (fresh)';
                echo "{$status}\n";

                $results[$operationKey] = [
                    'status' => $status,
                    'affected_users' => 0,
                    'execution_time_ms' => 0
                ];
            } else {
                // Live run: recalculate
                $opStartTime = microtime(true);
                $result = leaderboardRecalculate($pdo, $category, $period, $force);
                $opEndTime = microtime(true);
                $executionTimeMs = (int)(($opEndTime - $opStartTime) * 1000);

                $affectedUsers = $result['affected_users'] ?? 0;
                $totalAffectedUsers += $affectedUsers;

                $status = $affectedUsers > 0 ? 'UPDATED' : 'SKIPPED';
                echo "{$status} ({$affectedUsers} users, {$executionTimeMs}ms)\n";

                $results[$operationKey] = [
                    'status' => $status,
                    'affected_users' => $affectedUsers,
                    'execution_time_ms' => $executionTimeMs
                ];
            }
        } catch (Throwable $e) {
            echo "ERROR\n";
            $errorMsg = "Error in {$category}/{$period}: " . $e->getMessage();
            $errors[] = $errorMsg;

            // Log exception
            appLogException($e, [
                'source' => 'cron/update-leaderboard-cache.php',
                'category' => $category,
                'period' => $period
            ]);

            $results[$operationKey] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
}

// Calculate total execution time
$endTime = microtime(true);
$totalExecutionTime = (int)(($endTime - $startTime) * 1000);
$totalExecutionSeconds = round($totalExecutionTime / 1000, 2);

// Summary
echo "\n===========================================\n";
echo "Summary\n";
echo "===========================================\n";
echo "Total Operations: {$totalOperations}\n";
echo "Total Affected Users: {$totalAffectedUsers}\n";
echo "Total Execution Time: {$totalExecutionSeconds}s ({$totalExecutionTime}ms)\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n===========================================\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n";

$cronRunStatus = count($errors) > 0 ? 'error' : 'success';
$cronRunContext = [
    'periods' => $periods,
    'categories' => $categories,
    'dry_run' => $dryRun,
    'force' => $force,
    'total_operations' => $totalOperations,
    'total_affected_users' => $totalAffectedUsers,
    'execution_time_ms' => $totalExecutionTime,
    'errors' => $errors,
];
if (!$isCli && count($errors) > 0) {
    http_response_code(500);
}

// Exit with appropriate code
exit(count($errors) > 0 ? 1 : 0);

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP
Leaderboard Cache Update Cron Script

Usage:
  php update-leaderboard-cache.php --period=PERIOD [OPTIONS]

Required Parameters:
  --period=PERIOD       Comma-separated list of periods to update
                        Valid values: daily, weekly, monthly, quarterly, yearly, all
                        Example: --period=daily or --period=weekly,monthly

Optional Parameters:
  --category=CATEGORY   Specific category or 'all' (default: all)
                        Valid values: downloads, active, helpful, rising_star, quality, all
                        Example: --category=downloads or --category=downloads,active

  --dry-run            Show what would be done without executing
  --force              Force recalculation even if cache is fresh
  --help               Show this help message

Examples:
  # Update daily leaderboard for all categories
  php update-leaderboard-cache.php --period=daily

  # Update weekly and monthly for downloads category
  php update-leaderboard-cache.php --period=weekly,monthly --category=downloads

  # Force update all periods and categories
  php update-leaderboard-cache.php --period=all --force

  # Dry run to see what would be updated
  php update-leaderboard-cache.php --period=daily --dry-run

Recommended Cron Schedule:
  # Update daily leaderboard every 15 minutes
  */15 * * * * php /path/to/cron/update-leaderboard-cache.php --period=daily

  # Update weekly leaderboard every hour
  0 * * * * php /path/to/cron/update-leaderboard-cache.php --period=weekly

  # Update monthly leaderboard every 6 hours
  0 */6 * * * php /path/to/cron/update-leaderboard-cache.php --period=monthly

  # Update quarterly and yearly leaderboards daily at 3 AM
  0 3 * * * php /path/to/cron/update-leaderboard-cache.php --period=quarterly,yearly

Exit Codes:
  0 - Success
  1 - Error occurred

HELP;
}

