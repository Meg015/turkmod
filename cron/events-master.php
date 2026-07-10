<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../admin/helpers.php';
require_once __DIR__ . '/../includes/src/Modules/Events/init.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secretKey = function_exists('adminSettingValue') ? adminSettingValue($pdo ?? null, 'cron_secret_key', '') : '';
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
$cronHadIssues = false;
register_shutdown_function(static function () use (&$cronRunLogged, &$cronRunStatus, &$cronRunContext, &$cronPdo): void {
    if ($cronRunLogged || !($cronPdo instanceof PDO) || !function_exists('recordCronRun')) {
        return;
    }

    $cronRunLogged = true;
    recordCronRun($cronPdo, 'events_master', $cronRunStatus, $cronRunContext);
});

if (!$pdo || !eventsTablesReady($pdo)) {
    if ($pdo instanceof PDO) {
        $cronRunStatus = 'skipped';
        $cronRunContext = ['reason' => 'events_schema_not_ready'];
    }
    if (!$isCli) {
        http_response_code(503);
    }
    exit("Events tables not ready.\n");
}

$config = eventsGetConfig($pdo, true);

echo "=================================\n";
echo " Etkinlik Sistemi Master Cron\n";
echo "=================================\n";

// 1. Cleanup
echo "\n--- 1. Cleanup (Eski Logları Temizleme) ---\n";
(function() use ($pdo, $config, &$cronHadIssues) {
    $retentionDays = max(1, (int)($config['audit_log_retention_days'] ?? 30));
    try {
        $stmt = $pdo->prepare("DELETE FROM events_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);
        $deletedRows = $stmt->rowCount();
        echo "Deleted $deletedRows old audit log rows (older than $retentionDays days).\n";
        recordCronRun($pdo, 'events_cleanup', 'success', ['deleted_rows' => $deletedRows]);
        if ($deletedRows > 0) {
            eventsAuditLog($pdo, 'system_cleanup', 'events_audit_log', null, ['deleted_rows' => $deletedRows]);
        }
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
        recordCronRun($pdo, 'events_cleanup', 'error', ['error' => $e->getMessage()]);
        $cronHadIssues = true;
    }
})();

// 2. Expire Rewards
echo "\n--- 2. Expire Rewards (Süresi Dolan Ödüller) ---\n";
(function() use ($pdo, &$cronHadIssues) {
    try {
        $expired = $pdo->exec("UPDATE events_user_rewards SET status = 'expired', updated_at = NOW() WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()");
        if ($expired > 0) {
            eventsAuditLog($pdo, 'reward_expire', 'cron', null, ['expired_count' => (int)$expired], null);
        }
        recordCronRun($pdo, 'events_expire_rewards', 'success', ['expired_count' => (int) $expired]);
        echo "Expired rewards: " . (int)$expired . "\n";
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
        $cronHadIssues = true;
    }
})();

// 3. Raffle Resolve
echo "\n--- 3. Raffle Resolve (Çekiliş Sonuçlandırma) ---\n";
(function() use ($pdo, $config, &$cronHadIssues) {
    if (!eventsConfigBool($config, 'events_system_enabled') || !eventsConfigBool($config, 'events_raffles_enabled')) {
        echo "System or raffles disabled.\n";
        return;
    }
    if (!eventsConfigBool($config, 'raffle_auto_resolve')) {
        echo "Auto resolve is disabled in config.\n";
        return;
    }
    try {
        $stmt = $pdo->query("SELECT id FROM events_raffles WHERE is_active = 1 AND status = 'active' AND end_date <= NOW()");
        $raffles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($raffles) === 0) {
            echo "No raffles to resolve.\n";
            return;
        }
        $processed = 0; $failed = 0;
        foreach ($raffles as $raffleId) {
            try {
                $pdo->beginTransaction();
                eventsDrawRaffle($pdo, (int)$raffleId, $config, null, 'Otomatik cron çekimi');
                $pdo->commit();
                $processed++;
            } catch (Throwable $e) {
                $pdo->rollBack();
                eventsErrorLog($pdo, 'Auto raffle resolve failed.', ['error' => $e->getMessage(), 'raffle_id' => $raffleId], 'ERROR');
                $failed++;
                $cronHadIssues = true;
            }
        }
        echo "Processed {$processed} raffles. Failed: {$failed}\n";
        recordCronRun($pdo, 'events_raffle_resolve', $failed > 0 ? 'warning' : 'success', [
            'selected' => count($raffles),
            'processed' => $processed,
            'failed' => $failed,
        ]);
        if ($failed > 0) {
            $cronHadIssues = true;
        }
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
        $cronHadIssues = true;
    }
})();

// 4. Send Email Queue
echo "\n--- 4. Send Email Queue (E-posta Kuyruğu) ---\n";
(function() use ($pdo, $config, &$cronHadIssues) {
    if (!eventsConfigBool($config, 'email_notifications_enabled') || !eventsConfigBool($config, 'email_queue_enabled')) {
        echo "Email notifications or queue disabled.\n";
        return;
    }
    try {
        $maxRetry = max(1, (int)$config['email_max_retry_count']);
        $stmt = $pdo->prepare("SELECT * FROM events_email_queue WHERE status = 'pending' AND retry_count < ? ORDER BY id ASC LIMIT 25");
        $stmt->execute([$maxRetry]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) === 0) {
            echo "No emails in queue.\n";
            return;
        }
        $sent = 0; $failed = 0;
        foreach ($rows as $row) {
            try {
                $ok = function_exists('appSendMail') ? appSendMail((string)$row['email_to'], (string)$row['email_subject'], (string)$row['email_body']) : false;
                if ($ok) {
                    $pdo->prepare("UPDATE events_email_queue SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
                    $sent++;
                } else {
                    $retry = (int)$row['retry_count'] + 1;
                    $status = $retry >= $maxRetry ? 'failed' : 'pending';
                    $pdo->prepare("UPDATE events_email_queue SET status = ?, retry_count = ?, error_message = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $retry, 'Mail driver returned false.', (int)$row['id']]);
                    $failed++;
                }
            } catch (Throwable $e) {
                $retry = (int)$row['retry_count'] + 1;
                $status = $retry >= $maxRetry ? 'failed' : 'pending';
                $pdo->prepare("UPDATE events_email_queue SET status = ?, retry_count = ?, error_message = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $retry, mb_substr($e->getMessage(), 0, 1000), (int)$row['id']]);
                $failed++;
                $cronHadIssues = true;
            }
        }
        echo "Emails sent: {$sent}, failed: {$failed}\n";
        if ($failed > 0) {
            $cronHadIssues = true;
        }
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
        $cronHadIssues = true;
    }
})();

$cronRunStatus = $cronHadIssues ? 'warning' : 'success';
$cronRunContext = ['status' => 'completed'];
if (!$isCli && $cronRunStatus !== 'success') {
    http_response_code(500);
}

echo "\n=================================\n";
echo " Master Cron Completed.\n";
echo "=================================\n";

