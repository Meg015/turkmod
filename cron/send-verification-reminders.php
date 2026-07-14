<?php

declare(strict_types=1);

/**
 * Verification reminder cron job.
 *
 * Usage:
 *   php cron/send-verification-reminders.php [--limit=50] [--dry-run]
 *
 * Recommended cron:
 *   0 * * * * php /path/to/cron/send-verification-reminders.php --limit=50
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/src/Engine/Users/Support/users-helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Email/Support/helpers.php';
require_once __DIR__ . '/../admin/helpers.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo, 'cron_secret_key', '') : '';
    $providedSecret = $_GET['secret'] ?? '';
    if ($secretKey === '' || !hash_equals((string) $secretKey, (string) $providedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing cron secret key.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$options = getopt('', ['limit::', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Verification Reminder Cron\n";
    echo "Usage: php cron/send-verification-reminders.php [--limit=50] [--dry-run]\n";
    echo "Cron:  0 * * * * php /path/to/cron/send-verification-reminders.php --limit=50\n";
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
    recordCronRun($cronPdo, 'verification_reminders', $cronRunStatus, $cronRunContext);
});

if (!$pdo instanceof PDO) {
    $cronRunStatus = 'error';
    $cronRunContext = ['reason' => 'database_connection_unavailable'];
    if (!$isCli) {
        http_response_code(500);
    }
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$settings = function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];
$verificationEnabled = (($settings['account_email_verification_enabled'] ?? '0') === '1')
    && (($settings['account_email_verification_required'] ?? '0') === '1')
    && (($settings['account_email_verification_reminder_enabled'] ?? '1') === '1');

if (!$verificationEnabled) {
    $cronRunStatus = 'skipped';
    $cronRunContext = ['reason' => 'verification_reminders_disabled'];
    echo "Verification reminders are disabled.\n";
    exit(0);
}

if (($settings['account_email_system_enabled'] ?? '1') !== '1') {
    $cronRunStatus = 'skipped';
    $cronRunContext = ['reason' => 'email_system_disabled'];
    echo "Email system is disabled.\n";
    exit(0);
}

$limit = $isCli
    ? max(1, min(500, (int) ($options['limit'] ?? $settings['account_email_verification_reminder_batch_size'] ?? 50)))
    : max(1, min(500, (int) ($_GET['limit'] ?? $settings['account_email_verification_reminder_batch_size'] ?? 50)));
$dryRun = isset($options['dry-run']) || isset($_GET['dry-run']);
$afterMinutes = max(60, min(10080, (int) ($settings['account_email_verification_reminder_after_minutes'] ?? 1440)));
$candidates = function_exists('usersVerificationReminderCandidates')
    ? usersVerificationReminderCandidates($pdo, $settings, $limit)
    : [];

echo "Verification reminder cron\n";
echo "Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE') . "\n";
echo "Limit: {$limit}\n";
echo "Reminder threshold: {$afterMinutes} minutes\n";
echo "Eligible users: " . count($candidates) . "\n";

if ($candidates === []) {
    $cronRunStatus = 'skipped';
    $cronRunContext = [
        'reason' => 'no_candidates',
        'limit' => $limit,
        'after_minutes' => $afterMinutes,
        'processed' => 0,
        'failed' => 0,
    ];
    echo "No pending verifications found.\n";
    exit(0);
}

$processed = 0;
$failed = 0;
$skipped = 0;
$mailer = accountEmailService($pdo);

foreach ($candidates as $candidate) {
    $userId = (int) ($candidate['id'] ?? 0);
    $email = trim((string) ($candidate['email'] ?? ''));
    $username = trim((string) ($candidate['username'] ?? ''));
    if ($userId <= 0 || $email === '') {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "Would send reminder to #{$userId} {$email}\n";
        $skipped++;
        continue;
    }

    try {
        $sent = $mailer->issueVerification($userId, $email, $username);
        if ($sent) {
            $processed++;
            if (function_exists('logActivity')) {
                logActivity($pdo, 'email_verification_reminder_sent', 'user', $userId, [
                    'email' => $email,
                    'source' => 'cron/send-verification-reminders.php',
                ]);
            }
            echo "Sent reminder to #{$userId} {$email}\n";
        } else {
            $failed++;
            echo "Failed reminder to #{$userId} {$email}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'cron/send-verification-reminders.php', 'user_id' => $userId]);
        }
        echo "Error reminder to #{$userId} {$email}: " . $e->getMessage() . "\n";
    }
}

$cronRunStatus = $failed > 0 ? 'warning' : 'success';
$cronRunContext = [
    'limit' => $limit,
    'dry_run' => $dryRun,
    'after_minutes' => $afterMinutes,
    'eligible' => count($candidates),
    'processed' => $processed,
    'failed' => $failed,
    'skipped' => $skipped,
];

if (!$isCli && $failed > 0) {
    http_response_code(500);
}

exit($failed > 0 ? 1 : 0);
