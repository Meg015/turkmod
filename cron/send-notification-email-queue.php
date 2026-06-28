<?php

declare(strict_types=1);

use App\Core\Queue\SyncQueue;
use App\Modules\Notifications\Jobs\NotificationEmailQueueWorkerJob;

/**
 * Notification email queue worker.
 *
 * Usage:
 *   php cron/send-notification-email-queue.php [--limit=25] [--dry-run]
 *
 * Recommended cron:
 *   * * * * * php /path/to/cron/send-notification-email-queue.php --limit=25
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../admin/helpers.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo, 'cron_secret_key', '') : '';
    $providedSecret = $_GET['secret'] ?? '';
    
    if (empty($secretKey) || !is_string($providedSecret) || !hash_equals((string) $secretKey, $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$options = getopt('', ['limit::', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Notification Email Queue Worker\n";
    echo "Usage: php cron/send-notification-email-queue.php [--limit=25] [--dry-run]\n";
    echo "Cron:  * * * * * php /path/to/cron/send-notification-email-queue.php --limit=25\n";
    exit(0);
}

if (!$pdo) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
if (($settings['notif_email_channel_ready'] ?? '0') !== '1') {
    recordCronRun($pdo, 'notification_email_queue', 'skipped', ['reason' => 'email_queue_disabled']);
    echo "Notification email queue is disabled.\n";
    exit(0);
}

if (!$isCli) {
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
    $dryRun = isset($_GET['dry-run']);
} else {
    $limit = max(1, min(100, (int) ($options['limit'] ?? 25)));
    $dryRun = isset($options['dry-run']);
}
$startedAt = date('Y-m-d H:i:s');

$workerJob = new NotificationEmailQueueWorkerJob($pdo, notificationEmailQueueService(), $limit, $dryRun);
(new SyncQueue())->push($workerJob);
$result = $workerJob->result();
$stats = notificationEmailQueueStats($pdo);

echo "Notification email queue worker\n";
echo "Started: {$startedAt}\n";
echo "Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE') . "\n";
echo "Limit: {$limit}\n";
echo "Selected: {$result['selected']}\n";
echo "Sent: {$result['sent']}\n";
echo "Requeued: {$result['requeued']}\n";
echo "Failed: {$result['failed']}\n";
echo "Dry run: {$result['dry_run']}\n";
echo "Queue totals: queued={$stats['queued']} processing={$stats['processing']} sent={$stats['sent']} failed={$stats['failed']}\n";

if (!empty($result['errors'])) {
    recordCronRun($pdo, 'notification_email_queue', 'error', [
        'limit' => $limit,
        'dry_run' => $dryRun,
        'result' => $result,
        'stats' => $stats,
    ]);
    foreach ($result['errors'] as $error) {
        echo "Error: {$error}\n";
    }
    exit(1);
}

recordCronRun($pdo, 'notification_email_queue', ((int) $result['failed'] > 0 ? 'warning' : 'success'), [
    'limit' => $limit,
    'dry_run' => $dryRun,
    'result' => $result,
    'stats' => $stats,
]);

exit(0);
