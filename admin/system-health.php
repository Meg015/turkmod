<?php

declare(strict_types=1);

$pageTitle = 'Sistem Sağlığı';
require_once __DIR__ . '/init.php';

adminRequirePermission('system.view', 'Sistem sağlığını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'optimize_db') {
    adminRequirePermission('system.manage', 'Veritabanı optimizasyonu için gerekli izin hesabınıza tanımlanmamış.');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        try {
            $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($tables) {
                $tableList = array_map(fn($t) => '`' . $t . '`', $tables);
                $pdo->exec("OPTIMIZE TABLE " . implode(', ', $tableList));
                flash('success', 'Veritabanındaki ' . count($tables) . ' tablo başarıyla optimize edildi.');
            } else {
                flash('error', 'Optimize edilecek tablo bulunamadı.');
            }
        } catch (Throwable $e) {
            flash('error', 'Optimizasyon sırasında bir hata oluştu: ' . safeErrorMessage($e));
        }
    }
    header("Location: system-health.php?tab=database");
    exit;
}

$root = dirname(__DIR__);

function healthBoolLabel(bool $ok, string $level = 'required'): string
{
    if ($ok) {
        return 'OK';
    }

    return $level === 'warning' ? 'Uyarı' : 'Kontrol';
}

function healthRow(
    string $section,
    string $label,
    bool $ok,
    string $detail,
    string $level = 'required',
    string $actionUrl = '',
    string $actionLabel = 'Aç'
): array {
    return [
        'section' => $section,
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
        'level' => $level,
        'action_url' => $actionUrl,
        'action_label' => $actionLabel,
    ];
}

function healthPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function healthRuntimeLogSummary(string $root): array
{
    $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        return [
            'files' => 0,
            'critical' => 0,
            'errors' => 0,
            'latest' => 'storage/logs bulunamadı',
        ];
    }

    $files = [];
    foreach (['critical-*.log', 'error-*.log', 'app-*.log', 'api_*.log'] as $pattern) {
        foreach (glob($logDir . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
            $files[$file] = $file;
        }
    }

    usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $files = array_slice($files, 0, 8);

    $summary = [
        'files' => count($files),
        'critical' => 0,
        'errors' => 0,
        'latest' => 'son kayıt yok',
    ];

    foreach ($files as $file) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach (array_slice($lines, -250) as $line) {
            if (preg_match('~critical|fatal|exception|uncaught|undefined function|stack trace~i', $line) === 1) {
                $summary['critical']++;
                $summary['latest'] = basename($file) . ': ' . mb_substr(trim($line), 0, 160);
                continue;
            }

            if (preg_match('~error|warning|sqlstate|activity logging failed~i', $line) === 1) {
                $summary['errors']++;
                $summary['latest'] = basename($file) . ': ' . mb_substr(trim($line), 0, 160);
            }
        }
    }

    return $summary;
}

function healthTableExists(?PDO $pdo, string $table): bool
{
    if (!$pdo || $table === '') {
        return false;
    }

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function healthColumnExists(?PDO $pdo, string $table, string $column): bool
{
    if (!$pdo || $table === '' || $column === '') {
        return false;
    }

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $safeTable = str_replace('"', '""', $table);
            $query = $pdo->query('PRAGMA table_info("' . $safeTable . '")');
            $rows = $query ? ($query->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function healthScalar(?PDO $pdo, string $sql, array $params = [], int $fallback = 0): int
{
    if (!$pdo) {
        return $fallback;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return $fallback;
    }
}

function healthTextScalar(?PDO $pdo, string $sql, array $params = [], string $fallback = ''): string
{
    if (!$pdo) {
        return $fallback;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return is_scalar($value) ? (string) $value : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function healthReadableBytes(int|float $bytes): string
{
    $bytes = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;
    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }

    return number_format($bytes, $index === 0 ? 0 : 1, ',', '.') . ' ' . $units[$index];
}

function healthDiskDetail(string $path): string
{
    $free = @disk_free_space($path);
    $total = @disk_total_space($path);
    if ($free === false || $total === false || $total <= 0) {
        return 'disk bilgisi okunamadı';
    }

    $usedPercent = (int) round((1 - ($free / $total)) * 100);
    return healthReadableBytes((float) $free) . ' boş / ' . healthReadableBytes((float) $total) . ' toplam, kullanım %' . $usedPercent;
}

function healthAgeLabel(?string $datetime): string
{
    $timestamp = $datetime ? strtotime($datetime) : false;
    if ($timestamp === false) {
        return 'zaman okunamadı';
    }

    $seconds = max(0, time() - $timestamp);
    if ($seconds < 120) {
        return 'az önce';
    }
    if ($seconds < 3600) {
        return (int) floor($seconds / 60) . ' dk önce';
    }
    if ($seconds < 86400) {
        return (int) floor($seconds / 3600) . ' saat önce';
    }

    return (int) floor($seconds / 86400) . ' gün önce';
}

function healthCronLastRun(?PDO $pdo, string $jobKey): array
{
    if (!$pdo || !healthTableExists($pdo, 'application_logs')) {
        return ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
    }

    try {
        $stmt = $pdo->prepare("SELECT level, context_json, created_at FROM application_logs WHERE channel = 'cron' AND message = ? ORDER BY created_at DESC, id DESC LIMIT 1");
        $stmt->execute(['cron_run:' . $jobKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
        }

        $context = json_decode((string) ($row['context_json'] ?? ''), true);
        if (!is_array($context)) {
            $context = [];
        }

        return [
            'found' => true,
            'status' => (string) ($context['status'] ?? $row['level'] ?? 'success'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'context' => $context,
        ];
    } catch (Throwable $e) {
        return ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
    }
}

function healthCronIsFresh(array $run, int $maxAgeMinutes, bool $required = true): bool
{
    if (!$required) {
        return true;
    }
    if (empty($run['found']) || empty($run['created_at'])) {
        return false;
    }
    if (in_array((string) ($run['status'] ?? ''), ['error', 'warning', 'skipped'], true)) {
        return false;
    }

    $timestamp = strtotime((string) $run['created_at']);
    return $timestamp !== false && (time() - $timestamp) <= ($maxAgeMinutes * 60);
}

function healthCronDetail(array $run, string $missingText): string
{
    if (empty($run['found'])) {
        return $missingText;
    }

    $statusLabels = [
        'success' => 'başarılı',
        'warning' => 'uyarı',
        'error' => 'hata',
        'skipped' => 'atlandı',
    ];
    $createdAt = (string) ($run['created_at'] ?? '');
    $context = is_array($run['context'] ?? null) ? $run['context'] : [];
    $status = (string) ($run['status'] ?? 'success');
    $parts = [$statusLabels[$status] ?? $status, date('d.m.Y H:i', strtotime($createdAt) ?: time()), healthAgeLabel($createdAt)];

    foreach (['reason', 'selected', 'sent', 'failed', 'processed', 'expired_count', 'deleted_rows', 'total_operations', 'total_affected_users'] as $key) {
        if (array_key_exists($key, $context) && is_scalar($context[$key])) {
            $parts[] = $key . '=' . (string) $context[$key];
        }
    }
    if (isset($context['result']) && is_array($context['result'])) {
        $result = $context['result'];
        foreach (['selected', 'sent', 'requeued', 'failed'] as $key) {
            if (array_key_exists($key, $result)) {
                $parts[] = $key . '=' . (string) $result[$key];
            }
        }
    }
    if (isset($context['stats']) && is_array($context['stats'])) {
        $stats = $context['stats'];
        $parts[] = 'kuyruk=' . (int) ($stats['queued'] ?? 0) . '/' . (int) ($stats['processing'] ?? 0) . ', hatalı=' . (int) ($stats['failed'] ?? 0);
    }

    return implode(' · ', array_filter($parts, static fn ($part): bool => $part !== ''));
}

function healthStatusTone(array $row): string
{
    if ((bool) $row['ok']) {
        return 'ok';
    }

    return $row['level'] === 'warning' ? 'warn' : 'bad';
}

$sections = [
    'overview' => ['Genel Bakış', 'bi-speedometer2', 'Öncelikli aksiyonlar ve genel skor'],
    'security' => ['Ortam & Güvenlik', 'bi-shield-lock', '.env, HTTPS ve dosya erişim kontrolleri'],
    'database' => ['Veritabanı & Schema', 'bi-database-check', 'Tablo, bağlantı ve schema sinyalleri'],
    'logs' => ['Loglar & Hatalar', 'bi-journal-code', 'Runtime ve uygulama hata özeti'],
    'queues' => ['Kuyruklar & Cron', 'bi-diagram-3', 'E-posta, bot ve zamanlanmış işler'],
    'content' => ['İçerik Sağlığı', 'bi-clipboard2-pulse', 'Raporlar, linkler ve içerik kuyrukları'],
];

$activeTab = (string) ($_GET['tab'] ?? 'overview');
$activeTab = array_key_exists($activeTab, $sections) ? $activeTab : 'overview';

$envConfig = $envConfig ?? [];
$appEnv = strtolower((string) ($envConfig['APP_ENV'] ?? 'local'));
$isProduction = $appEnv === 'production';
$appDebug = (($envConfig['APP_DEBUG'] ?? 'false') === 'true');
$forceHttps = (($envConfig['APP_FORCE_HTTPS'] ?? 'false') === 'true');
$appUrl = (string) ($envConfig['APP_URL'] ?? '');
$isLocalUrl = preg_match('~localhost|127\.0\.0\.1|\.test(?:/|$)~i', $appUrl) === 1;
$runtimeSchemaAllowed = function_exists('runtimeSchemaUpdatesAllowed') && runtimeSchemaUpdatesAllowed();
$trustedProxies = trim((string) ($envConfig['TRUSTED_PROXIES'] ?? ''));
$maintenanceMode = function_exists('adminSettingValue') && $pdo instanceof PDO
    ? adminSettingValue($pdo, 'maintenance_mode', '0')
    : '0';
$maintenanceMessage = function_exists('adminSettingValue') && $pdo instanceof PDO
    ? adminSettingValue($pdo, 'maintenance_message', 'Site bakım modundadır, lütfen daha sonra tekrar deneyin.')
    : '';
$healthAdminSettings = [];
if (function_exists('getAdminSettings') && $pdo instanceof PDO) {
    try {
        $healthAdminSettings = getAdminSettings($pdo);
    } catch (Throwable $e) {
        $healthAdminSettings = [];
    }
}
$notificationEmailEnabled = (($healthAdminSettings['notif_email_channel_ready'] ?? '0') === '1');
$eventsReady = function_exists('eventsTablesReady') && $pdo instanceof PDO && eventsTablesReady($pdo);
$eventsConfig = [];
if ($eventsReady && function_exists('eventsGetConfig')) {
    try {
        $eventsConfig = eventsGetConfig($pdo, true);
    } catch (Throwable $e) {
        $eventsConfig = [];
    }
}
$eventsSystemEnabled = $eventsReady && function_exists('eventsConfigBool') && eventsConfigBool($eventsConfig, 'events_system_enabled');
$eventsEmailQueueEnabled = $eventsSystemEnabled && function_exists('eventsConfigBool') && eventsConfigBool($eventsConfig, 'email_notifications_enabled') && eventsConfigBool($eventsConfig, 'email_queue_enabled');
$eventsRaffleAutoResolve = $eventsSystemEnabled && function_exists('eventsConfigBool') && eventsConfigBool($eventsConfig, 'events_raffles_enabled') && eventsConfigBool($eventsConfig, 'raffle_auto_resolve');

$runtimeLogSummary = healthRuntimeLogSummary($root);
$coreTables = ['users', 'user_groups', 'user_group_members', 'user_group_permissions', 'categories', 'topics', 'media_files', 'admin_settings', 'activity_logs', 'application_logs', 'request_rate_limits'];
$missingCoreTables = [];
foreach ($coreTables as $table) {
    if (!healthTableExists($pdo, $table)) {
        $missingCoreTables[] = $table;
    }
}

$topicReportsOpen = healthTableExists($pdo, 'topic_reports')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM topic_reports WHERE status IN ('open','reviewing')")
    : 0;
$userReportsOpen = healthTableExists($pdo, 'user_reports')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM user_reports WHERE status IN ('open','reviewing')")
    : 0;
$pendingTopics = healthTableExists($pdo, 'topics')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM topics WHERE status = 'draft' AND deleted_at IS NULL")
    : 0;
$orphanMedia = healthTableExists($pdo, 'media_files') && healthTableExists($pdo, 'topics')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM media_files mf LEFT JOIN topics t ON t.id = mf.topic_id WHERE mf.topic_id IS NOT NULL AND t.id IS NULL")
    : 0;

$appErrors24h = healthTableExists($pdo, 'application_logs')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM application_logs WHERE level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
    : 0;
$appErrors7d = healthTableExists($pdo, 'application_logs')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM application_logs WHERE level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
    : 0;
$latestAppLog = healthTableExists($pdo, 'application_logs')
    ? healthTextScalar($pdo, "SELECT CONCAT(level, ' / ', channel, ' / ', LEFT(message, 140)) FROM application_logs ORDER BY created_at DESC, id DESC LIMIT 1", [], 'kayıt yok')
    : 'application_logs tablosu yok';
$activityToday = healthTableExists($pdo, 'activity_logs')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")
    : 0;

$emailQueued = healthTableExists($pdo, 'notification_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status IN ('queued','processing')")
    : 0;
$emailFailed = healthTableExists($pdo, 'notification_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status = 'failed'")
    : 0;
$emailStuckProcessing = healthTableExists($pdo, 'notification_email_queue') && healthColumnExists($pdo, 'notification_email_queue', 'locked_at')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status = 'processing' AND locked_at IS NOT NULL AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")
    : 0;
$latestFailedEmail = healthTableExists($pdo, 'notification_email_queue')
    ? healthTextScalar($pdo, "SELECT CONCAT('#', id, ' / ', LEFT(COALESCE(error_message, 'hata detayı yok'), 140)) FROM notification_email_queue WHERE status = 'failed' ORDER BY updated_at DESC, id DESC LIMIT 1", [], 'başarısız kayıt yok')
    : 'notification_email_queue tablosu yok';
$eventsEmailPending = healthTableExists($pdo, 'events_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_email_queue WHERE status = 'pending'")
    : 0;
$eventsEmailFailed = healthTableExists($pdo, 'events_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_email_queue WHERE status = 'failed'")
    : 0;

$expiredRewards = healthTableExists($pdo, 'events_user_rewards')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_user_rewards WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()")
    : 0;
$expiredRateLimits = healthTableExists($pdo, 'request_rate_limits')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM request_rate_limits WHERE expires_at IS NOT NULL AND expires_at < NOW()")
    : 0;
$requestRateLimitRows = healthTableExists($pdo, 'request_rate_limits')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM request_rate_limits")
    : 0;
$legacyRateLimitRows = healthTableExists($pdo, 'rate_limits')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM rate_limits")
    : 0;
$rateLimitHelperReady = function_exists('checkRateLimit')
    && function_exists('incrementRateLimit')
    && function_exists('resetRateLimit')
    && function_exists('getRateLimitRemainingSeconds');
$cronScriptPaths = [
    'Bildirim e-posta' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'send-notification-email-queue.php',
    'Liderlik cache' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'update-leaderboard-cache.php',
    'Etkinlik Ana Cron' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'events-master.php',
];
$missingCronScripts = [];
foreach ($cronScriptPaths as $label => $path) {
    if (!is_file($path)) {
        $missingCronScripts[] = $label;
    }
}
$cronRuns = [
    'notification_email_queue' => healthCronLastRun($pdo, 'notification_email_queue'),
    'leaderboard_cache' => healthCronLastRun($pdo, 'leaderboard_cache'),
    'events_master' => healthCronLastRun($pdo, 'events_master'),
];

$phpFiles = [];
try {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && strtolower($file->getExtension()) === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
} catch (Throwable $e) {
    $phpFiles = [];
}

$mdFiles = glob($root . DIRECTORY_SEPARATOR . '*.md') ?: [];
$backupPaths = [
    $root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'backup.php',
    $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'backup',
    $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups',
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups',
];
$backupResidues = array_values(array_filter($backupPaths, static fn (string $path): bool => file_exists($path)));

$checks = [
    healthRow('security', 'PHP sürümü', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION),
    healthRow('security', 'PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'aktif' : 'eksik'),
    healthRow('security', 'mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'aktif' : 'eksik'),
    healthRow('security', 'GD', extension_loaded('gd'), extension_loaded('gd') ? 'aktif' : 'görsel işlemleri için önerilir', 'warning'),
    healthRow('security', '.env dosyası', is_file($root . DIRECTORY_SEPARATOR . '.env'), is_file($root . DIRECTORY_SEPARATOR . '.env') ? 'mevcut' : 'eksik'),
    healthRow('security', 'APP_DEBUG', !$appDebug, $appDebug ? 'false olmalı' : 'false'),
    healthRow('security', 'APP_FORCE_HTTPS', !$isProduction || $forceHttps, $forceHttps ? 'aktif' : ($isProduction ? 'canlıda aktif olmalı' : 'local ortamda kapalı olabilir'), 'warning'),
    healthRow('security', 'APP_URL', !$isProduction || ($appUrl !== '' && !$isLocalUrl), $appUrl !== '' ? $appUrl : 'boş', 'warning'),
    healthRow('security', 'TRUSTED_PROXIES', !$isProduction || $trustedProxies !== '', $trustedProxies !== '' ? $trustedProxies : 'reverse proxy varsa tanımlanmalı', 'warning'),
    healthRow('security', 'Root .htaccess', is_file($root . DIRECTORY_SEPARATOR . '.htaccess'), 'gizli/sistem dosyaları için erişim bariyeri'),
    healthRow('security', 'uploads .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess'), 'upload script çalıştırma bariyeri'),
    healthRow('security', 'storage .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.htaccess'), 'storage erişim bariyeri'),
    healthRow('security', 'database .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '.htaccess'), 'database klasörü erişim bariyeri'),
    healthRow('security', 'install klasörü', !is_dir($root . DIRECTORY_SEPARATOR . 'install'), is_dir($root . DIRECTORY_SEPARATOR . 'install') ? 'kurulumdan sonra silinmeli' : 'silinmiş', 'warning'),
    healthRow('security', 'Yedekleme kalıntısı', count($backupResidues) === 0, count($backupResidues) === 0 ? 'yok' : implode(', ', array_map('healthPath', $backupResidues)), 'warning'),
    healthRow('security', 'Markdown dosyaları', count($mdFiles) === 0 || !$isProduction, count($mdFiles) === 0 ? 'yok' : count($mdFiles) . ' adet bulundu', 'warning'),

    healthRow('database', 'Veritabanı bağlantısı', $pdo instanceof PDO, $pdo instanceof PDO ? 'bağlı' : 'bağlantı yok'),
    healthRow('database', 'DB sürücüsü', $pdo instanceof PDO, $pdo instanceof PDO ? (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'yok'),
    healthRow('database', 'Core tablolar', count($missingCoreTables) === 0, count($missingCoreTables) === 0 ? count($coreTables) . ' tablo mevcut' : 'Eksik: ' . implode(', ', $missingCoreTables)),
    healthRow('database', 'Runtime schema güncellemesi', !$isProduction || !$runtimeSchemaAllowed, $runtimeSchemaAllowed ? 'aktif' : 'kapalı', 'warning'),
    healthRow('database', 'database/schema.sql', is_file($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'), 'kurulum ve referans schema için gerekli'),
    healthRow('database', 'PHP dosyaları', count($phpFiles) > 0, count($phpFiles) . ' adet PHP dosyası'),
    healthRow('database', 'storage/cache yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'), healthPath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'), 'warning'),
    healthRow('database', 'uploads yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'uploads'), healthPath($root . DIRECTORY_SEPARATOR . 'uploads')),
    healthRow('database', 'storage/logs yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'), healthPath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs')),
    healthRow('database', 'Disk kapasitesi', true, healthDiskDetail($root), 'info'),
    healthRow('database', 'Rate limit helper uyumu', $rateLimitHelperReady && healthTableExists($pdo, 'request_rate_limits'), ($rateLimitHelperReady ? 'helper hazır' : 'helper eksik') . ', request_rate_limits=' . $requestRateLimitRows . ', legacy rate_limits=' . $legacyRateLimitRows, 'warning', $baseUri . '/admin/rate-limits.php', 'Rate Limit'),
    healthRow('database', 'Legacy rate_limits kullanımı', $legacyRateLimitRows === 0, $legacyRateLimitRows === 0 ? 'aktif eski kayıt yok' : $legacyRateLimitRows . ' eski tablo kaydı var; runtime request_rate_limits ile izlenmeli', 'warning', $baseUri . '/admin/rate-limits.php', 'İzle'),

    healthRow('logs', 'Runtime kritik loglar', (int) $runtimeLogSummary['critical'] === 0, (int) $runtimeLogSummary['critical'] === 0 ? 'son kayıtlarda kritik hata yok' : $runtimeLogSummary['critical'] . ' kritik sinyal; son: ' . $runtimeLogSummary['latest']),
    healthRow('logs', 'Runtime hata logları', (int) $runtimeLogSummary['errors'] === 0, (int) $runtimeLogSummary['errors'] === 0 ? $runtimeLogSummary['files'] . ' log dosyası tarandı' : $runtimeLogSummary['errors'] . ' hata/uyarı sinyali; son: ' . $runtimeLogSummary['latest'], 'warning', $baseUri . '/admin/logs.php', 'Loglar'),
    healthRow('logs', 'Uygulama hataları 24s', $appErrors24h === 0, $appErrors24h . ' hata/kritik kayıt', 'warning', $baseUri . '/admin/logs.php', 'Loglar'),
    healthRow('logs', 'Uygulama hataları 7g', $appErrors7d === 0, $appErrors7d . ' hata/kritik kayıt', 'warning', $baseUri . '/admin/logs.php', 'Loglar'),
    healthRow('logs', 'Son uygulama logu', true, $latestAppLog, 'info', $baseUri . '/admin/logs.php', 'Loglar'),
    healthRow('logs', 'Bugünkü aktivite', true, $activityToday . ' işlem kaydı', 'info', $baseUri . '/admin/action-log.php', 'İşlem Günlüğü'),

    healthRow('queues', 'E-posta kuyruğu', $emailFailed === 0, $emailQueued . ' bekleyen/işlenen, ' . $emailFailed . ' başarısız', 'warning', $baseUri . '/admin/notifications.php?tab=logs', 'Bildirimler'),
    healthRow('queues', 'Cron script dosyaları', count($missingCronScripts) === 0, count($missingCronScripts) === 0 ? count($cronScriptPaths) . ' script mevcut' : 'Eksik: ' . implode(', ', $missingCronScripts), 'warning'),
    healthRow('queues', 'E-posta takılan işlemler', $emailStuckProcessing === 0, $emailStuckProcessing . ' işlem 15 dakikadan uzun süredir processing', 'warning', $baseUri . '/admin/notifications.php?tab=logs&email=processing', 'Kuyruk'),
    healthRow('queues', 'Son e-posta hatası', $emailFailed === 0, $latestFailedEmail, 'warning', $baseUri . '/admin/notifications.php?tab=logs&email=failed', 'Hatalılar'),
    healthRow('queues', 'Bildirim e-posta cron', healthCronIsFresh($cronRuns['notification_email_queue'], 30, $notificationEmailEnabled), $notificationEmailEnabled ? healthCronDetail($cronRuns['notification_email_queue'], 'cron kaydı yok; worker çalışmıyor olabilir') : 'e-posta kuyruğu kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/notifications.php', 'Bildirimler'),
    healthRow('queues', 'Liderlik cron', healthCronIsFresh($cronRuns['leaderboard_cache'], 1440, true), healthCronDetail($cronRuns['leaderboard_cache'], 'son 24 saat için cron kaydı yok'), 'warning', $baseUri . '/admin/leaderboard.php', 'Liderlik'),
    healthRow('queues', 'Etkinlik e-posta kuyruğu', $eventsEmailFailed === 0, $eventsEmailPending . ' bekleyen, ' . $eventsEmailFailed . ' hatalı', 'warning', $baseUri . '/admin/events.php?tab=settings', 'Etkinlikler'),
    healthRow('queues', 'Etkinlik Ana Cron', healthCronIsFresh($cronRuns['events_master'], 30, $eventsSystemEnabled), $eventsSystemEnabled ? healthCronDetail($cronRuns['events_master'], 'son 30 dakika içinde cron kaydı yok; master cron çalışmıyor olabilir') : 'events sistemi kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/events.php', 'Etkinlikler'),

    healthRow('queues', 'Süresi geçmiş ödüller', $expiredRewards === 0, $expiredRewards . ' süresi geçmiş bekleyen ödül', 'warning', $baseUri . '/admin/events-rewards.php', 'Ödüller'),
    healthRow('queues', 'Süresi dolmuş rate limit', $expiredRateLimits < 500, $expiredRateLimits . ' temizlenebilir kayıt', 'warning', $baseUri . '/admin/rate-limits.php?status=expired', 'Temizle'),
    healthRow('queues', 'Bakım modu', in_array($maintenanceMode, ['0', '1'], true), $maintenanceMode === '1' ? 'aktif: ' . $maintenanceMessage : 'kapalı', 'warning', $baseUri . '/admin/settings.php#general', 'Ayarlar'),

    healthRow('content', 'Konu raporları', $topicReportsOpen === 0, $topicReportsOpen . ' açık/incelenen rapor', 'warning', $baseUri . '/admin/complaints-reports.php?tab=topics&status=open', 'Raporlar'),
    healthRow('content', 'Kullanıcı şikayetleri', $userReportsOpen === 0, $userReportsOpen . ' açık/incelenen şikayet', 'warning', $baseUri . '/admin/complaints-reports.php?tab=users&status=open', 'Şikayetler'),
    healthRow('content', 'Taslak konular', $pendingTopics === 0, $pendingTopics . ' taslak konu', 'warning', $baseUri . '/admin/topics.php?status=draft', 'Konular'),
    healthRow('content', 'Orphan medya kayıtları', $orphanMedia === 0, $orphanMedia . ' konuya bağlı olmayan medya kaydı', 'warning', $baseUri . '/admin/media-manager.php', 'Medya'),
];

$problemChecks = array_values(array_filter($checks, static fn (array $check): bool => !$check['ok']));
$requiredIssues = count(array_filter($checks, static fn (array $check): bool => !$check['ok'] && $check['level'] === 'required'));
$warningIssues = count(array_filter($checks, static fn (array $check): bool => !$check['ok'] && $check['level'] === 'warning'));
$operationsCount = $topicReportsOpen + $userReportsOpen + $pendingTopics + $emailFailed + $emailStuckProcessing;
$healthScore = max(0, min(100, 100 - ($requiredIssues * 20) - ($warningIssues * 5)));

$overviewChecks = array_filter($checks, static fn(array $check): bool => 
    !str_contains(strtolower($check['label']), 'cron') && 
    !str_contains(strtolower($check['label']), 'konu') &&
    $check['section'] !== 'content'
);
$overviewProblemChecks = array_values(array_filter($overviewChecks, static fn (array $check): bool => !$check['ok']));

$rowsForTab = $activeTab === 'overview'
    ? ($overviewProblemChecks !== [] ? $overviewProblemChecks : array_slice(array_values($overviewChecks), 0, 8))
    : array_values(array_filter($checks, static fn (array $check): bool => $check['section'] === $activeTab));
$priorityActions = array_slice($overviewProblemChecks, 0, 4);

$dbSizeMb = 0;
$dbOverheadMb = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT SUM(data_length + index_length) AS size, SUM(data_free) AS overhead FROM information_schema.tables WHERE table_schema = DATABASE()");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbSizeMb = round(($row['size'] ?? 0) / 1024 / 1024, 2);
        $dbOverheadMb = round(($row['overhead'] ?? 0) / 1024 / 1024, 2);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

require_once __DIR__ . '/header.php';
?>
<div class="health-page">
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="health-kicker"><i class="bi bi-clipboard2-pulse"></i> Operasyon merkezi</span>
            <h2>Sistem Sağlığı</h2>
            <p>Ortam, güvenlik, veritabanı, loglar, kuyruklar ve içerik sinyallerini tek ekranda izleyin.</p>
        </div>
        <div class="ui-admin-page-hero-actions">
            <span class="ui-admin-badge <?= $requiredIssues > 0 ? 'ui-admin-badge-danger' : ($warningIssues > 0 ? 'ui-admin-badge-warning' : 'ui-admin-badge-success') ?>">
                <i class="bi <?= $requiredIssues > 0 ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle' ?>"></i>
                Skor <?= $healthScore ?>/100
            </span>
        </div>
    </section>

    <div class="admin-stat-grid health-summary ui-grid">
        <div class="admin-stat-card <?= $requiredIssues > 0 ? 'stat-danger' : 'stat-success' ?> health-stat ui-card">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-content"><span class="stat-label">Zorunlu Sorun</span><span class="stat-value"><?= number_format($requiredIssues, 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card <?= $warningIssues > 0 ? 'stat-warning' : 'stat-success' ?> health-stat ui-card">
            <div class="stat-icon"><i class="bi bi-cone-striped"></i></div>
            <div class="stat-content"><span class="stat-label">Uyarı</span><span class="stat-value"><?= number_format($warningIssues, 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card <?= $operationsCount > 0 ? 'stat-warning' : 'stat-success' ?> health-stat ui-card">
            <div class="stat-icon"><i class="bi bi-inboxes-fill"></i></div>
            <div class="stat-content"><span class="stat-label">Operasyon Yükü</span><span class="stat-value"><?= number_format($operationsCount, 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card <?= $maintenanceMode === '1' ? 'stat-warning' : 'stat-success' ?> health-stat ui-card">
            <div class="stat-icon"><i class="bi <?= $maintenanceMode === '1' ? 'bi-tools' : 'bi-check-circle-fill' ?>"></i></div>
            <div class="stat-content"><span class="stat-label">Bakım Modu</span><span class="stat-value"><?= $maintenanceMode === '1' ? 'Açık' : 'Kapalı' ?></span></div>
        </div>
    </div>

    <?php if ($activeTab === 'database'): ?>
    <section class="admin-card ui-panel ui-admin-db-status-panel">
        <div>
            <span class="health-kicker"><i class="bi bi-database-check"></i> Veritabanı Durumu</span>
            <h3 class="ui-admin-db-status-title">Veritabanı Optimizasyonu</h3>
            <p class="ui-admin-db-status-copy">
                Toplam Boyut: <strong><?= number_format($dbSizeMb, 2, ',', '.') ?> MB</strong> 
                <?php if ($dbOverheadMb > 10): ?>
                    <span class="ui-admin-db-status-note is-danger"><i class="bi bi-exclamation-circle"></i> <?= number_format($dbOverheadMb, 2, ',', '.') ?> MB birikmiş alan (optimizasyon önerilir).</span>
                <?php else: ?>
                    <span class="ui-admin-db-status-note is-success"><i class="bi bi-check-circle"></i> Tamamen optimize durumda. (<?= number_format($dbOverheadMb, 2, ',', '.') ?> MB standart disk rezervi)</span>
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="system-health.php?tab=database" class="ui-admin-m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="optimize_db">
            <button type="submit" class="btn-primary <?= $dbOverheadMb <= 10 ? 'ui-admin-disabled-soft' : '' ?>" data-ui-confirm="Veritabanını optimize etmek istiyor musunuz? Bu işlem tablo sayısına göre biraz zaman alabilir." <?= $dbOverheadMb <= 10 ? 'disabled' : '' ?>>
                <i class="bi bi-magic"></i> Şimdi Optimize Et
            </button>
        </form>
    </section>
    <?php endif; ?>



    <nav class="health-tabs" aria-label="Sistem sağlığı sekmeleri">
        <?php foreach ($sections as $key => $meta): ?>
            <a class="health-tab <?= $activeTab === $key ? 'is-active' : '' ?>" href="system-health.php?tab=<?= htmlspecialchars($key) ?>" <?= $activeTab === $key ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= htmlspecialchars($meta[1]) ?>"></i>
                <span>
                    <strong><?= htmlspecialchars($meta[0]) ?></strong>
                    <span><?= htmlspecialchars($meta[2]) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="admin-card health-panel ui-panel">
        <div class="health-panel-head">
            <div>
                <h2><?= htmlspecialchars($sections[$activeTab][0]) ?></h2>
                <p><?= htmlspecialchars($activeTab === 'overview' && $problemChecks === [] ? 'Öncelikli sorun yok; temel kontroller sağlıklı görünüyor.' : $sections[$activeTab][2]) ?></p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-clock"></i><?= htmlspecialchars(date('d.m.Y H:i')) ?></span>
        </div>

        <?php if ($rowsForTab === []): ?>
            <div class="health-empty ui-admin-empty ui-empty">
                <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-check2-circle"></i></div>
                <h3 class="ui-admin-empty-title ui-empty">Bu sekmede kontrol bulunamadı.</h3>
                <p class="ui-admin-empty-desc ui-empty">Yeni kontroller eklendikçe burada listelenecek.</p>
            </div>
        <?php else: ?>
            <div class="health-table-wrap">
                <table class="health-table">
                    <thead>
                        <tr>
                            <th>Kontrol</th>
                            <th>Durum</th>
                            <th>Detay</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsForTab as $row): ?>
                            <?php $tone = healthStatusTone($row); ?>
                            <tr>
                                <td>
                                    <span class="health-check-name"><span class="health-dot <?= htmlspecialchars($tone) ?>"></span><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><span class="health-badge <?= htmlspecialchars($tone) ?>"><?= htmlspecialchars(healthBoolLabel((bool) $row['ok'], (string) $row['level']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="health-detail"><?= htmlspecialchars((string) $row['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="health-action">
                                    <?php if ((string) $row['action_url'] !== ''): ?>
                                        <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars((string) $row['action_url'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-right"></i><?= htmlspecialchars((string) $row['action_label'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
