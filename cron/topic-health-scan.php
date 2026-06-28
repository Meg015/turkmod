<?php

declare(strict_types=1);

/**
 * Topic Health Scan Cron Job.
 *
 * Runs health check for a batch of topics.
 * Setup a cron job to run this periodically.
 *
 * Example:
 *   * * * * * php /path/to/project/cron/topic-health-scan.php --limit=50
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/src/Engine/AdminQuality/Legacy/helpers.php';
require_once __DIR__ . '/../admin/helpers.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secretKey = adminSettingValue($pdo, 'cron_secret_key', '');
    $providedSecret = $_GET['secret'] ?? '';
    
    if ($secretKey === '' || !hash_equals((string) $secretKey, (string) $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }
}

// Ensure HTTPS is forced to avoid bootstrap errors
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';


$options = getopt('', [
    'limit::',
    'offset::',
    'help',
]);

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    // For web requests, we can also accept GET parameters
    $limit = max(1, (int) ($_GET['limit'] ?? adminSettingValue($pdo, 'cron_batch_size', '50')));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
} else {
    if (isset($options['help'])) {
        echo "Topic Health Scan Cron\n";
        echo "Usage: php cron/topic-health-scan.php [--limit=50] [--offset=0]\n";
        exit(0);
    }
    $limit = max(1, (int) ($options['limit'] ?? adminSettingValue($pdo, 'cron_batch_size', '50')));
    $offset = max(0, (int) ($options['offset'] ?? 0));
}

try {
    adminQualityEnsureSchema($pdo);

    $total = adminQualityCountScannableTopics($pdo);
    if ($total === 0) {
        echo "No topics require a health scan at this moment.\n";
        exit(0);
    }

    echo "Found {$total} total scannable topics.\n";
    echo "Processing batch of {$limit} topics (offset: {$offset})...\n";

    $topicIds = adminQualityGetScannableTopicIds($pdo, $offset, $limit);
    $processed = 0;
    
    foreach ($topicIds as $topicId) {
        $result = adminQualityCheckTopicHealth($pdo, (int)$topicId);
        echo "Checked Topic #{$topicId}: Status - " . ($result['status'] ?? 'unknown') . "\n";
        $processed++;
    }

    echo "Completed batch processing for {$processed} topics.\n";

    if ($offset + $processed >= $total && function_exists('logActivity')) {
        logActivity($pdo, 'topic_health_scan_completed_cron', 'system', null, [
            'total' => $total,
            'processed_batch' => $processed
        ]);
        echo "All scannable topics are up to date.\n";
    }
    
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Topic health scan failed: " . $e->getMessage() . "\n");
    if (function_exists('appLogException')) {
        appLogException($e, ['source' => 'cron/topic-health-scan.php']);
    }
    exit(1);
}

