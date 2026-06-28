<?php

declare(strict_types=1);

/**
 * Cleanup script for the legacy `settings` table.
 *
 * The `settings` table has accumulated a high auto_increment value (3.2K+)
 * from repeated INSERT...ON DUPLICATE KEY UPDATE operations. This script:
 *
 * 1. Removes any orphaned entries that don't match known setting definitions
 * 2. Resets the auto_increment to a clean value
 * 3. Optimizes the table to reclaim space
 *
 * Run via: php cron/cleanup-settings-table.php
 * Or via HTTP: /cron/cleanup-settings-table.php?secret=YOUR_CRON_SECRET
 *
 * This is safe to run in production — it only removes entries that are not
 * in the current adminSettingDefinitions() list.
 */

// CLI or HTTP entry point
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

if (!$isCli) {
    require_once __DIR__ . '/../includes/init.php';

    $envConfig = \App\Core\Database::getEnvConfig();
    $cronSecret = $envConfig['CRON_SECRET'] ?? ($_ENV['CRON_SECRET'] ?? '');
    $requestSecret = $_GET['secret'] ?? '';

    if ($cronSecret === '' || $requestSecret !== $cronSecret) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid cron secret']);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
} else {
    // CLI mode — load minimal bootstrap
    $composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
    }
    require_once dirname(__DIR__) . '/includes/Database.php';
    require_once dirname(__DIR__) . '/includes/helpers.php';
}

$pdo = \App\Core\Database::connection();
if (!$pdo instanceof PDO) {
    $result = ['status' => 'error', 'message' => 'Database connection failed'];
    if ($isCli) {
        fwrite(STDERR, "Database connection failed\n");
        exit(1);
    }
    echo json_encode($result);
    exit;
}

$results = [
    'status' => 'success',
    'steps' => [],
];

// Step 1: Count current settings entries
try {
    $countBefore = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    $results['steps'][] = [
        'step' => 'count_before',
        'count' => $countBefore,
    ];
} catch (Throwable $e) {
    $results['steps'][] = ['step' => 'count_before', 'error' => $e->getMessage()];
    $countBefore = 0;
}

// Step 2: Load known setting definitions
$knownKeys = [];
if (function_exists('adminSettingDefinitions')) {
    if (!$isCli) {
        require_once dirname(__DIR__) . '/admin/helpers.php';
    }
    $definitions = adminSettingDefinitions();
    $knownKeys = array_keys($definitions);
}

// Step 3: Remove orphaned entries (keys not in definitions)
$orphanedCount = 0;
if (!empty($knownKeys)) {
    try {
        $placeholders = implode(',', array_fill(0, count($knownKeys), '?'));
        $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` NOT IN ({$placeholders})");
        $stmt->execute($knownKeys);
        $orphanedCount = $stmt->rowCount();
        $results['steps'][] = [
            'step' => 'remove_orphaned',
            'removed' => $orphanedCount,
        ];
    } catch (Throwable $e) {
        $results['steps'][] = ['step' => 'remove_orphaned', 'error' => $e->getMessage()];
    }
} else {
    $results['steps'][] = ['step' => 'remove_orphaned', 'skipped' => 'No definitions loaded'];
}

// Step 4: Count after cleanup
try {
    $countAfter = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    $results['steps'][] = [
        'step' => 'count_after',
        'count' => $countAfter,
    ];
} catch (Throwable $e) {
    $results['steps'][] = ['step' => 'count_after', 'error' => $e->getMessage()];
    $countAfter = $countBefore;
}

// Step 5: Reset auto_increment to count + 1 (safe value)
if ($countAfter > 0) {
    try {
        $nextId = $countAfter + 1;
        $pdo->exec("ALTER TABLE settings AUTO_INCREMENT = {$nextId}");
        $results['steps'][] = [
            'step' => 'reset_auto_increment',
            'new_value' => $nextId,
        ];
    } catch (Throwable $e) {
        $results['steps'][] = ['step' => 'reset_auto_increment', 'error' => $e->getMessage()];
    }
}

// Step 6: Optimize table (reclaim space from deleted rows)
try {
    $pdo->exec("OPTIMIZE TABLE settings");
    $results['steps'][] = ['step' => 'optimize_table', 'status' => 'done'];
} catch (Throwable $e) {
    $results['steps'][] = ['step' => 'optimize_table', 'error' => $e->getMessage()];
}

// Step 7: Log the cleanup
if (function_exists('recordCronRun')) {
    recordCronRun($pdo, 'cleanup-settings-table', 'success', [
        'orphaned_removed' => $orphanedCount,
        'count_before' => $countBefore,
        'count_after' => $countAfter,
    ]);
}

// Output results
$results['summary'] = [
    'orphaned_removed' => $orphanedCount,
    'count_before' => $countBefore,
    'count_after' => $countAfter,
];

if ($isCli) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
