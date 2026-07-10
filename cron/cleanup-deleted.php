<?php

declare(strict_types=1);

/**
 * Soft Delete Cleanup Script
 * Silindikten sonra 30 gün geçmiş kayıtları kalıcı olarak veritabanından siler.
 * Bu scripti sunucuda cron job olarak günde 1 kez çalıştırabilirsiniz.
 * Örn: 0 3 * * * php /var/www/vhosts/turkmod.com/httpdocs/cron/cleanup-deleted.php
 */

require_once __DIR__ . '/../includes/init.php';

// Güvenlik kontrolü - Sadece CLI üzerinden çalışmasına izin ver
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.");
}

$cronRunStatus = 'error';
$cronRunContext = ['reason' => 'cron_not_completed'];
$cronRunLogged = false;
$cronPdo = $pdo ?? null;

$pdo = \App\Core\Database::connection();
if ($pdo instanceof PDO) {
    $cronPdo = $pdo;
}

register_shutdown_function(static function () use (&$cronRunLogged, &$cronRunStatus, &$cronRunContext, &$cronPdo): void {
    if ($cronRunLogged || !($cronPdo instanceof PDO) || !function_exists('recordCronRun')) {
        return;
    }

    $cronRunLogged = true;
    recordCronRun($cronPdo, 'cleanup_deleted', $cronRunStatus, $cronRunContext);
});

if (!$pdo) {
    $cronRunStatus = 'error';
    $cronRunContext = ['reason' => 'database_connection_unavailable'];
    die("Database connection failed.\n");
}

echo "Soft delete cleanup started at " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo->beginTransaction();

    // 1. Silineli 30 günü geçmiş yorumları (varsa) temizle
    // (Aşağıdaki yorum tablosu varsa aktif edilebilir)
    // $stmt = $pdo->prepare("DELETE FROM comments WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    // $stmt->execute();
    // $deletedComments = $stmt->rowCount();
    // echo "Deleted comments: {$deletedComments}\n";

    // 2. Silineli 30 günü geçmiş bildirimleri temizle (isteğe bağlı optimizasyon)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read = 1");
    $stmt->execute();
    $deletedReadNotifications = $stmt->rowCount();
    echo "Deleted old read notifications: {$deletedReadNotifications}\n";

    // 3. Silineli 30 günü geçmiş konuları (Topics) temizle
    $stmt = $pdo->prepare("DELETE FROM topics WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $deletedTopics = $stmt->rowCount();
    echo "Deleted topics: {$deletedTopics}\n";

    $pdo->commit();

    if ($deletedTopics > 0 && function_exists('seoInvalidateSitemapCaches')) {
        seoInvalidateSitemapCaches();
        echo "Invalidated sitemap caches.\n";
    }

    echo "Cleanup finished successfully.\n";

    $cronRunStatus = 'success';
    $cronRunContext = [
        'deleted_topics' => $deletedTopics,
        'deleted_notifications' => $deletedReadNotifications
    ];

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $cronRunStatus = 'error';
    $cronRunContext = [
        'error' => $e->getMessage(),
    ];
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    if (function_exists('appLogException')) {
        appLogException($e, ['source' => 'cron_cleanup_deleted']);
    }
}
