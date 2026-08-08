<?php

declare(strict_types=1);

use App\Modules\Notifications\Services\BulkEmailWorkerService;

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../admin/helpers.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo, 'cron_secret_key', '') : '';
    $providedSecret = $_GET['secret'] ?? '';
    if ($secretKey === '' || !is_string($providedSecret) || !hash_equals((string) $secretKey, $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$options = getopt('', ['limit::', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Bulk Email Campaign Worker\n";
    echo "Usage: php cron/send-bulk-email-campaigns.php [--limit=100] [--dry-run]\n";
    exit(0);
}
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$settings = function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];
$enabled = in_array(strtolower(trim((string) ($settings['notif_bulk_email_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
if (!$enabled) {
    recordCronRun($pdo, 'bulk_email_campaigns', 'skipped', ['reason' => 'bulk_email_disabled']);
    echo "Bulk email campaign worker is disabled.\n";
    exit(0);
}

$configuredLimit = max(1, min(200, (int) ($settings['notif_bulk_email_batch_size'] ?? 100)));
$configuredAttempts = max(1, min(10, (int) ($settings['notif_bulk_email_max_attempts'] ?? 3)));
$limit = $isCli
    ? max(1, min(200, (int) ($options['limit'] ?? $configuredLimit)))
    : max(1, min(200, (int) ($_GET['limit'] ?? $configuredLimit)));
$dryRun = $isCli ? isset($options['dry-run']) : isset($_GET['dry-run']);

try {
    $result = (new BulkEmailWorkerService())->process($pdo, $limit, $configuredAttempts, $dryRun);
    $status = $result['errors'] !== [] ? 'warning' : 'success';
    recordCronRun($pdo, 'bulk_email_campaigns', $status, ['limit' => $limit, 'dry_run' => $dryRun, 'result' => $result]);
    echo "Bulk email campaign worker\n";
    echo 'Mode: ' . ($dryRun ? 'DRY RUN' : 'LIVE') . "\n";
    echo "Campaign: {$result['campaign_id']}\n";
    echo "Prepared: {$result['prepared']}\n";
    echo "Selected: {$result['selected']}\n";
    echo "Sent: {$result['sent']}\n";
    echo "Requeued: {$result['requeued']}\n";
    echo "Failed: {$result['failed']}\n";
    echo 'Worker locked: ' . ($result['locked'] ? 'yes' : 'no') . "\n";
    foreach ($result['errors'] as $error) {
        echo 'Error: ' . $error . "\n";
    }
    exit($result['errors'] !== [] ? 1 : 0);
} catch (Throwable $e) {
    if (function_exists('appLogException')) {
        appLogException($e, ['source' => 'cron/send-bulk-email-campaigns.php']);
    }
    recordCronRun($pdo, 'bulk_email_campaigns', 'error', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Bulk email worker failed: ' . $e->getMessage() . "\n");
    exit(1);
}
