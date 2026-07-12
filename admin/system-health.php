<?php

declare(strict_types=1);

$pageTitle = 'Sistem Sağlığı';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/UserActivity/Legacy/helpers.php';

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

function healthRuntimeLogLabel(string $level): string
{
    return match (strtolower(trim($level))) {
        'critical' => 'Kritik',
        'error' => 'Hata',
        default => 'Kayıt',
    };
}

function healthApplicationLogLevelLabel(string $level): string
{
    $normalized = strtolower(trim($level));
    if (function_exists('appLogsLevelLabel')) {
        return appLogsLevelLabel($normalized);
    }

    return match ($normalized) {
        'emergency' => 'Acil',
        'alert' => 'Alarm',
        'critical' => 'Kritik',
        'error' => 'Hata',
        'warning', 'warn' => 'Uyarı',
        'notice' => 'Bildirim',
        'info' => 'Bilgi',
        'debug' => 'Hata Ayıklama',
        default => $normalized !== '' ? strtoupper($normalized) : 'Bilinmiyor',
    };
}

function healthApplicationLogChannelLabel(string $channel): string
{
    $normalized = strtolower(trim($channel));
    if (function_exists('appLogsChannelLabel')) {
        return appLogsChannelLabel($normalized);
    }

    if ($normalized === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $normalized));
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
            'latest' => 'storage/logs bulunamadi',
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
    $latestTs = 0;

    foreach ($files as $file) {
        foreach (healthTailLines($file, 250) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('~^(?:stack trace:|#\d+\s|[-]{3,})~i', $trimmed) === 1) {
                continue;
            }

            $timestamp = null;
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmed, $matches) === 1) {
                $timestamp = strtotime($matches[1]) ?: null;
            }
            $timestamp ??= filemtime($file) ?: null;
            if ($timestamp === null) {
                continue;
            }

            $isCritical = preg_match('~critical|fatal|exception|uncaught|undefined function~i', $trimmed) === 1;
            $isError = preg_match('~error|warning|sqlstate|activity logging failed~i', $trimmed) === 1;
            if (!$isCritical && !$isError) {
                continue;
            }

            if ($isCritical) {
                $summary['critical']++;
            } else {
                $summary['errors']++;
            }

            if ($timestamp > $latestTs) {
                $latestTs = $timestamp;
                $summary['latest'] = basename($file) . ': ' . mb_substr($trimmed, 0, 160);
            }
        }
    }

    return $summary;
}

function healthTailLines(string $file, int $lineLimit = 250, int $byteLimit = 262144): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $size = filesize($file);
    if ($size === false || $size <= 0) {
        return [];
    }

    $handle = fopen($file, 'rb');
    if (!$handle) {
        return [];
    }

    $readSize = min($byteLimit, (int) $size);
    if ($readSize < (int) $size) {
        fseek($handle, -$readSize, SEEK_END);
    }

    $chunk = (string) fread($handle, $readSize);
    fclose($handle);

    $lines = preg_split('/\R/u', $chunk) ?: [];
    $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

    return array_slice($lines, -$lineLimit);
}

function healthRuntimeLogLevel(string $line): ?string
{
    if (preg_match('~critical|fatal|exception|uncaught|undefined function~i', $line) === 1) {
        return 'critical';
    }
    if (preg_match('~error|warning|sqlstate|activity logging failed~i', $line) === 1) {
        return 'error';
    }

    return null;
}

function healthRuntimeLogEntries(string $root, int $limit = 40): array
{
    $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        return [];
    }

    $files = [];
    foreach (['critical-*.log', 'error-*.log', 'app-*.log', 'api_*.log'] as $pattern) {
        foreach (glob($logDir . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
            $files[$file] = $file;
        }
    }
    if ($files === []) {
        return [];
    }

    usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $entries = [];

    foreach (array_slice($files, 0, 10) as $file) {
        $fileMtime = filemtime($file) ?: null;
        foreach (healthTailLines($file, 360) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('~^(?:stack trace:|#\d+\s|[-]{3,})~i', $trimmed) === 1) {
                continue;
            }

            $level = healthRuntimeLogLevel($trimmed);
            if ($level === null) {
                continue;
            }

            $timestamp = null;
            $message = $trimmed;
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $timestamp = strtotime((string) ($decoded['ts'] ?? $decoded['time'] ?? '')) ?: null;
                $message = trim((string) ($decoded['msg'] ?? $decoded['message'] ?? $trimmed));
            } elseif (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmed, $matches) === 1) {
                $timestamp = strtotime($matches[1]) ?: null;
            } elseif (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $trimmed, $matches) === 1) {
                $timestamp = strtotime($matches[1]) ?: null;
            }

            $timestamp ??= $fileMtime;
            if ($timestamp === null) {
                continue;
            }

            $entries[] = [
                'timestamp' => (int) $timestamp,
                'level' => $level,
                'file' => basename($file),
                'message' => mb_substr($message !== '' ? $message : $trimmed, 0, 240, 'UTF-8'),
            ];
        }
    }

    usort($entries, static fn (array $a, array $b): int => ((int) $b['timestamp']) <=> ((int) $a['timestamp']));
    if (count($entries) > $limit) {
        $entries = array_slice($entries, 0, $limit);
    }

    return $entries;
}

function healthApplicationErrorLevels(): array
{
    return ['emergency', 'alert', 'critical', 'error', 'warning'];
}

function healthApplicationErrorTone(string $level): string
{
    $normalized = strtolower(trim($level));
    if (in_array($normalized, ['emergency', 'alert', 'critical', 'error'], true)) {
        return 'bad';
    }

    return 'warn';
}

function healthApplicationErrorContext(?string $contextJson): string
{
    $raw = trim((string) $contextJson);
    if ($raw === '') {
        return '';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || $decoded === []) {
        return mb_substr($raw, 0, 220, 'UTF-8');
    }

    $parts = [];
    $labelMap = [
        'file' => 'Dosya',
        'line' => 'Satır',
        'url' => 'URL',
        'route' => 'Rota',
        'method' => 'Metot',
        'request_id' => 'İstek ID',
        'code' => 'Kod',
    ];
    foreach (['file', 'line', 'url', 'route', 'method', 'request_id', 'code'] as $key) {
        if (array_key_exists($key, $decoded) && is_scalar($decoded[$key])) {
            $parts[] = ($labelMap[$key] ?? $key) . ': ' . (string) $decoded[$key];
        }
        if (count($parts) >= 3) {
            break;
        }
    }

    if ($parts === []) {
        foreach ($decoded as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = (($labelMap[(string) $key] ?? ucwords(str_replace('_', ' ', (string) $key))) . ': ' . (string) $value);
            }
            if (count($parts) >= 3) {
                break;
            }
        }
    }

    return mb_substr(implode(' | ', $parts), 0, 220, 'UTF-8');
}

function healthApplicationErrorChannels(?PDO $pdo): array
{
    if (!$pdo || !healthTableExists($pdo, 'application_logs')) {
        return [];
    }

    try {
        $stmt = $pdo->query("
            SELECT DISTINCT channel
            FROM application_logs
            WHERE level IN ('emergency','alert','critical','error','warning')
              AND channel IS NOT NULL
              AND channel <> ''
            ORDER BY channel ASC
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function healthApplicationErrorFeed(
    ?PDO $pdo,
    string $search = '',
    string $level = '',
    string $channel = '',
    int $page = 1,
    int $perPage = 20
): array {
    $safePage = max(1, $page);
    $safePerPage = max(10, min(100, $perPage));
    $result = ['total' => 0, 'page' => $safePage, 'perPage' => $safePerPage, 'items' => []];
    if (!$pdo || !healthTableExists($pdo, 'application_logs')) {
        return $result;
    }

    $allowedLevels = healthApplicationErrorLevels();
    $normalizedLevel = strtolower(trim($level));
    if ($normalizedLevel !== '' && !in_array($normalizedLevel, $allowedLevels, true)) {
        $normalizedLevel = '';
    }

    $where = ["level IN ('emergency','alert','critical','error','warning')"];
    $params = [];

    if ($search !== '') {
        $where[] = "(message LIKE :search OR channel LIKE :search OR ip_address LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    if ($normalizedLevel !== '') {
        $where[] = 'level = :level';
        $params['level'] = $normalizedLevel;
    }
    if ($channel !== '') {
        $where[] = 'channel = :channel';
        $params['channel'] = $channel;
    }

    $whereSql = implode(' AND ', $where);
    $offset = ($safePage - 1) * $safePerPage;

    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM application_logs WHERE {$whereSql}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $result['total'] = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, level, channel, message, context_json, ip_address, created_at
            FROM application_logs
            WHERE {$whereSql}
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
            OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $safePerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result['items'] = array_map(static function (array $row): array {
            $row['context_excerpt'] = healthApplicationErrorContext((string) ($row['context_json'] ?? ''));
            return $row;
        }, $items);
    } catch (Throwable $e) {
        return $result;
    }

    return $result;
}

function healthTableExists(?PDO $pdo, string $table): bool
{
    if (!$pdo || $table === '') {
        return false;
    }

    static $cache = [];

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $cacheKey = spl_object_id($pdo) . ':' . $driver;

        if (!isset($cache[$cacheKey])) {
            $cache[$cacheKey] = [];

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
                foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) ?: [] as $name) {
                    $cache[$cacheKey][strtolower((string) $name)] = true;
                }
            } else {
                $stmt = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
                foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) ?: [] as $name) {
                    $cache[$cacheKey][strtolower((string) $name)] = true;
                }
            }
        }

        $normalized = strtolower($table);
        if (isset($cache[$cacheKey][$normalized])) {
            return true;
        }

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            $exists = (bool) $stmt->fetchColumn();
            if ($exists) {
                $cache[$cacheKey][$normalized] = true;
            }
            return $exists;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        if ($exists) {
            $cache[$cacheKey][$normalized] = true;
        }
        return $exists;
    } catch (Throwable $e) {
        return false;
    }
}

function healthColumnExists(?PDO $pdo, string $table, string $column): bool
{
    if (!$pdo || $table === '' || $column === '') {
        return false;
    }

    static $cache = [];

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $cacheKey = spl_object_id($pdo) . ':' . $driver . ':' . strtolower($table);

        if (!isset($cache[$cacheKey])) {
            $cache[$cacheKey] = [];

            if ($driver === 'sqlite') {
                $safeTable = str_replace('"', '""', $table);
                $query = $pdo->query('PRAGMA table_info("' . $safeTable . '")');
                foreach (($query ? $query->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) {
                    $cache[$cacheKey][strtolower((string) ($row['name'] ?? ''))] = true;
                }
            } else {
                $stmt = $pdo->prepare(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
                );
                $stmt->execute([$table]);
                foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $name) {
                    $cache[$cacheKey][strtolower((string) $name)] = true;
                }
            }
        }

        $normalized = strtolower($column);
        if (isset($cache[$cacheKey][$normalized])) {
            return true;
        }

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
    $free = disk_free_space($path);
    $total = disk_total_space($path);
    if ($free === false || $total === false || $total <= 0) {
        return 'disk bilgisi okunamadı';
    }

    $usedPercent = (int) round((1 - ($free / $total)) * 100);
    return healthReadableBytes((float) $free) . ' boş / ' . healthReadableBytes((float) $total) . ' toplam, kullanım %' . $usedPercent;
}

function healthPhpFileCount(string $root): int
{
    $codePaths = [
        'admin',
        'api',
        'cron',
        'includes',
        'themes',
        'index.php',
        'messages.php',
        'route.php',
    ];
    $skipDirs = ['.git', 'node_modules', 'storage', 'tmp', 'uploads', 'vendor'];
    $count = 0;

    foreach ($codePaths as $relativePath) {
        $path = $root . DIRECTORY_SEPARATOR . $relativePath;
        if (is_file($path)) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                $count++;
            }
            continue;
        }
        if (!is_dir($path)) {
            continue;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current) use ($skipDirs): bool {
                if (!$current->isDir()) {
                    return true;
                }

                return !in_array(strtolower($current->getFilename()), $skipDirs, true);
            }
        );
        $iterator = new RecursiveIteratorIterator($filter);
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && strtolower($file->getExtension()) === 'php') {
                $count++;
            }
        }
    }

    return $count;
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
    'logs' => ['Loglar & Hatalar', 'bi-journal-code', 'Çalışma zamanı ve uygulama hata özeti'],
    'queues' => ['Kuyruklar & Cron', 'bi-diagram-3', 'E-posta, bot ve zamanlanmış işler'],
    'content' => ['İçerik Sağlığı', 'bi-clipboard2-pulse', 'Raporlar, linkler ve içerik kuyrukları'],
];

$activeTab = (string) ($_GET['tab'] ?? 'overview');
$activeTab = array_key_exists($activeTab, $sections) ? $activeTab : 'overview';
$logsSubtabs = [
    'summary' => ['Loglar & Hatalar', 'bi-journal-text'],
    'center' => ['Hata Merkezi', 'bi-exclamation-octagon'],
];
$logsView = (string) ($_GET['logs_view'] ?? 'summary');
if (!array_key_exists($logsView, $logsSubtabs)) {
    $logsView = 'summary';
}

$envConfig = $envConfig ?? [];
$appEnv = strtolower((string) ($envConfig['APP_ENV'] ?? 'local'));
$isProduction = $appEnv === 'production';
$appDebug = (($envConfig['APP_DEBUG'] ?? 'false') === 'true');
$forceHttps = (($envConfig['APP_FORCE_HTTPS'] ?? 'false') === 'true');
$appUrl = (string) ($envConfig['APP_URL'] ?? '');
$isLocalUrl = preg_match('~localhost|127\.0\.0\.1|\.test(?:/|$)~i', $appUrl) === 1;
$runtimeSchemaAllowed = function_exists('runtimeSchemaUpdatesAllowed') && runtimeSchemaUpdatesAllowed();
$trustedProxies = trim((string) ($envConfig['TRUSTED_PROXIES'] ?? ''));
$loadLogSection = !$isProduction || $activeTab === 'logs';
$loadQueueSection = !$isProduction || $activeTab === 'queues';
$loadContentSection = !$isProduction || $activeTab === 'content';
$loadDatabaseSection = !$isProduction || $activeTab === 'database';
$maintenanceMode = function_exists('adminSettingValue') && $pdo instanceof PDO
    ? adminSettingValue($pdo, 'maintenance_mode', '0')
    : '0';
$maintenanceMessage = function_exists('adminSettingValue') && $pdo instanceof PDO
    ? adminSettingValue($pdo, 'maintenance_message', 'Site bakım modundadır, lütfen daha sonra tekrar deneyin.')
    : '';
$healthAdminSettings = [];
if ($loadQueueSection && function_exists('getAdminSettings') && $pdo instanceof PDO) {
    try {
        $healthAdminSettings = getAdminSettings($pdo);
    } catch (Throwable $e) {
        $healthAdminSettings = [];
    }
}
$notificationEmailEnabled = (($healthAdminSettings['notif_email_channel_ready'] ?? '0') === '1');
$eventsReady = false;
$eventsConfig = [];
$eventsSystemEnabled = false;
$eventsEmailQueueEnabled = false;
$eventsRaffleAutoResolve = false;
if ($loadQueueSection) {
    $eventsReady = function_exists('eventsTablesReady') && $pdo instanceof PDO && eventsTablesReady($pdo);
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
}

$runtimeLogSummary = $loadLogSection
    ? healthRuntimeLogSummary($root)
    : ['files' => 0, 'critical' => 0, 'errors' => 0, 'latest' => 'canlı hızlı görünümde log özeti atlandı'];
$logSearch = trim((string) ($_GET['log_q'] ?? ''));
$logLevel = strtolower(trim((string) ($_GET['log_level'] ?? '')));
$logChannel = trim((string) ($_GET['log_channel'] ?? ''));
$runtimePage = max(1, (int) ($_GET['runtime_page'] ?? 1));
$appPage = max(1, (int) ($_GET['app_page'] ?? 1));
$runtimePerPage = 15;
$appPerPage = 20;
$logAllowedLevels = healthApplicationErrorLevels();
if ($logLevel !== '' && !in_array($logLevel, $logAllowedLevels, true)) {
    $logLevel = '';
}
$runtimeLogEntries = $loadLogSection ? healthRuntimeLogEntries($root, 45) : [];
$runtimeTotalRows = count($runtimeLogEntries);
$runtimeTotalPages = max(1, (int) ceil($runtimeTotalRows / max(1, $runtimePerPage)));
$runtimePage = min($runtimePage, $runtimeTotalPages);
$runtimeOffset = ($runtimePage - 1) * $runtimePerPage;
$runtimePageItems = array_slice($runtimeLogEntries, $runtimeOffset, $runtimePerPage);
$applicationErrorChannels = $loadLogSection ? healthApplicationErrorChannels($pdo) : [];
if ($logChannel !== '' && !in_array($logChannel, $applicationErrorChannels, true)) {
    $applicationErrorChannels[] = $logChannel;
    sort($applicationErrorChannels);
}
$applicationErrorFeed = $loadLogSection
    ? healthApplicationErrorFeed($pdo, $logSearch, $logLevel, $logChannel, $appPage, $appPerPage)
    : ['total' => 0, 'page' => 1, 'perPage' => $appPerPage, 'items' => []];
$appTotalRows = max(0, (int) ($applicationErrorFeed['total'] ?? 0));
$appCurrentPerPage = max(1, (int) ($applicationErrorFeed['perPage'] ?? $appPerPage));
$appTotalPages = max(1, (int) ceil($appTotalRows / $appCurrentPerPage));
if ($loadLogSection && $appPage > $appTotalPages) {
    $appPage = $appTotalPages;
    $applicationErrorFeed = healthApplicationErrorFeed($pdo, $logSearch, $logLevel, $logChannel, $appPage, $appCurrentPerPage);
}
$logHasFilters = $logSearch !== '' || $logLevel !== '' || $logChannel !== '';
$logsSubtabBaseParams = ['tab' => 'logs'];
$logsSummaryUrl = 'system-health.php?' . http_build_query($logsSubtabBaseParams + ['logs_view' => 'summary']);
$logsCenterBaseParams = array_filter([
    'tab' => 'logs',
    'logs_view' => 'center',
    'log_q' => $logSearch,
    'log_level' => $logLevel,
    'log_channel' => $logChannel,
], static fn ($value): bool => $value !== '' && $value !== null);
$logsCenterUrl = 'system-health.php?' . http_build_query($logsCenterBaseParams);
$runtimePageBase = 'system-health.php?' . http_build_query($logsCenterBaseParams + ['app_page' => $appPage]) . '&runtime_page=';
$appPageBase = 'system-health.php?' . http_build_query($logsCenterBaseParams + ['runtime_page' => $runtimePage]) . '&app_page=';
$coreTables = ['users', 'user_groups', 'user_group_members', 'user_group_permissions', 'categories', 'topics', 'media_files', 'admin_settings', 'activity_logs', 'user_activity_events', 'admin_action_log', 'application_logs', 'request_rate_limits'];
$missingCoreTables = [];
foreach ($coreTables as $table) {
    if (!healthTableExists($pdo, $table)) {
        $missingCoreTables[] = $table;
    }
}

$topicReportsOpen = $loadContentSection && healthTableExists($pdo, 'topic_reports')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM topic_reports WHERE status IN ('open','reviewing')")
    : 0;
$userReportsOpen = $loadContentSection && healthTableExists($pdo, 'user_reports')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM user_reports WHERE status IN ('open','reviewing')")
    : 0;
$pendingTopics = $loadContentSection && healthTableExists($pdo, 'topics')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM topics WHERE status = 'draft' AND deleted_at IS NULL")
    : 0;
$orphanMedia = $loadContentSection && healthTableExists($pdo, 'media_files') && healthTableExists($pdo, 'topics')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM media_files mf LEFT JOIN topics t ON t.id = mf.topic_id WHERE mf.topic_id IS NOT NULL AND t.id IS NULL")
    : 0;

$appErrors24h = $loadLogSection && healthTableExists($pdo, 'application_logs')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM application_logs WHERE level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
    : 0;
$appErrors7d = $loadLogSection && healthTableExists($pdo, 'application_logs')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM application_logs WHERE level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
    : 0;
$latestAppLog = $loadLogSection && healthTableExists($pdo, 'application_logs')
    ? healthTextScalar($pdo, "SELECT CONCAT(level, ' / ', channel, ' / ', LEFT(message, 140)) FROM application_logs ORDER BY id DESC LIMIT 1", [], 'kayıt yok')
    : 'log detayı için Loglar sekmesini açın';
$activityToday = $loadLogSection && healthTableExists($pdo, 'user_activity_events')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM user_activity_events e WHERE e.created_at >= CURDATE() AND e.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND e.event_group NOT IN ('admin', 'moderation') AND e.event_type NOT IN ('admin_user_updated', 'group_save', 'group_deactivate', 'user_group_changed', 'user_status_changed', 'user_banned', 'user_unbanned', 'user_restricted', 'user_restriction_removed', 'user_restrictions_cleared', 'user_admin_note_added', 'settings_updated', 'topic_settings_updated', 'topic_moderated', 'topic_revision_restored', 'topic_health_scan_completed', 'topic_health_cleared', 'download_link_checked', 'category_created', 'category_updated', 'category_deleted', 'media_uploaded', 'media_deleted', 'leaderboard_recalculated', 'leaderboard_cache_cleared', 'leaderboard_settings_updated', 'application_logs_cleared', 'activity_logs_cleared', 'rate_limit_records_deleted', 'cron_manual_triggered', 'bot_import_published') AND e.event_type NOT LIKE 'topic_bulk_%' AND NOT EXISTS (SELECT 1 FROM user_group_members ugm INNER JOIN user_groups ug ON ug.id = ugm.group_id LEFT JOIN user_group_permissions ugp ON ugp.group_id = ug.id AND ugp.permission_value = 1 AND ugp.permission_key IN ('*', 'admin.access') WHERE ugm.user_id = e.actor_user_id AND ug.is_active = 1 AND (ug.slug = 'admin' OR ugp.permission_key IS NOT NULL))")
    : 0;

$emailQueued = $loadQueueSection && healthTableExists($pdo, 'notification_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status IN ('queued','processing')")
    : 0;
$emailFailed = $loadQueueSection && healthTableExists($pdo, 'notification_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status = 'failed'")
    : 0;
$emailStuckProcessing = $loadQueueSection && healthTableExists($pdo, 'notification_email_queue') && healthColumnExists($pdo, 'notification_email_queue', 'locked_at')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM notification_email_queue WHERE status = 'processing' AND locked_at IS NOT NULL AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")
    : 0;
$latestFailedEmail = $loadQueueSection && healthTableExists($pdo, 'notification_email_queue')
    ? healthTextScalar($pdo, "SELECT CONCAT('#', id, ' / ', LEFT(COALESCE(error_message, 'hata detayı yok'), 140)) FROM notification_email_queue WHERE status = 'failed' ORDER BY updated_at DESC, id DESC LIMIT 1", [], 'başarısız kayıt yok')
    : 'kuyruk detayı için Kuyruklar sekmesini açın';
$eventsEmailPending = $loadQueueSection && healthTableExists($pdo, 'events_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_email_queue WHERE status = 'pending'")
    : 0;
$eventsEmailFailed = $loadQueueSection && healthTableExists($pdo, 'events_email_queue')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_email_queue WHERE status = 'failed'")
    : 0;

$expiredRewards = $loadQueueSection && healthTableExists($pdo, 'events_user_rewards')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM events_user_rewards WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()")
    : 0;
$expiredRateLimits = $loadQueueSection && healthTableExists($pdo, 'request_rate_limits')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM request_rate_limits WHERE expires_at IS NOT NULL AND expires_at < NOW()")
    : 0;
$requestRateLimitRows = $loadDatabaseSection && healthTableExists($pdo, 'request_rate_limits')
    ? healthScalar($pdo, "SELECT COUNT(*) FROM request_rate_limits")
    : 0;
$rateLimitHelperReady = function_exists('checkRateLimit')
    && function_exists('incrementRateLimit')
    && function_exists('resetRateLimit')
    && function_exists('getRateLimitRemainingSeconds');
$cronScriptPaths = [
    'Bildirim e-posta' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'send-notification-email-queue.php',
    'Liderlik cache' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'update-leaderboard-cache.php',
    'Rate limit cleanup' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'cleanup-expired-rate-limits.php',
    'Etkinlik Ana Cron' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'events-master.php',
];
$missingCronScripts = [];
foreach ($cronScriptPaths as $label => $path) {
    if (!is_file($path)) {
        $missingCronScripts[] = $label;
    }
}
$cronRuns = $loadQueueSection
    ? [
        'notification_email_queue' => healthCronLastRun($pdo, 'notification_email_queue'),
        'leaderboard_cache' => healthCronLastRun($pdo, 'leaderboard_cache'),
        'rate_limits_cleanup' => healthCronLastRun($pdo, 'rate_limits_cleanup'),
        'events_master' => healthCronLastRun($pdo, 'events_master'),
    ]
    : [
        'notification_email_queue' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
        'leaderboard_cache' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
        'rate_limits_cleanup' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
        'events_master' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
    ];

$phpFileCount = 0;
if ($loadDatabaseSection) {
    try {
        $phpFileCount = healthPhpFileCount($root);
    } catch (Throwable $e) {
        $phpFileCount = 0;
    }
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
    healthRow('security', 'GD Kütüphanesi', extension_loaded('gd'), extension_loaded('gd') ? 'aktif' : 'görsel işlemleri için önerilir', 'warning'),
    healthRow('security', '.env dosyası', is_file($root . DIRECTORY_SEPARATOR . '.env'), is_file($root . DIRECTORY_SEPARATOR . '.env') ? 'mevcut' : 'eksik'),
    healthRow('security', 'Hata ayıklama modu (APP_DEBUG)', !$appDebug, $appDebug ? 'false olmalı' : 'false'),
    healthRow('security', 'Zorunlu HTTPS (APP_FORCE_HTTPS)', !$isProduction || $forceHttps, $forceHttps ? 'aktif' : ($isProduction ? 'canlıda aktif olmalı' : 'local ortamda kapalı olabilir'), 'warning'),
    healthRow('security', 'Uygulama adresi (APP_URL)', !$isProduction || ($appUrl !== '' && !$isLocalUrl), $appUrl !== '' ? $appUrl : 'boş', 'warning'),
    healthRow('security', 'Güvenilir proxyler (TRUSTED_PROXIES)', !$isProduction || $trustedProxies !== '', $trustedProxies !== '' ? $trustedProxies : 'reverse proxy varsa tanımlanmalı', 'warning'),
    healthRow('security', 'Root .htaccess', is_file($root . DIRECTORY_SEPARATOR . '.htaccess'), 'gizli/sistem dosyaları için erişim bariyeri'),
    healthRow('security', 'uploads .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess'), 'upload script çalıştırma bariyeri'),
    healthRow('security', 'storage .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.htaccess'), 'storage erişim bariyeri'),
    healthRow('security', 'database .htaccess', is_file($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '.htaccess'), 'database klasörü erişim bariyeri'),
    healthRow('security', 'Kurulum klasörü', !is_dir($root . DIRECTORY_SEPARATOR . 'install'), is_dir($root . DIRECTORY_SEPARATOR . 'install') ? 'kurulumdan sonra silinmeli' : 'silinmiş', 'warning'),
    healthRow('security', 'Yedek dosya kalıntıları', count($backupResidues) === 0, count($backupResidues) === 0 ? 'yok' : implode(', ', array_map('healthPath', $backupResidues)), 'warning'),
    healthRow('security', 'Markdown dosyaları', count($mdFiles) === 0 || !$isProduction, count($mdFiles) === 0 ? 'yok' : count($mdFiles) . ' adet bulundu', 'warning'),

    healthRow('database', 'Veritabanı bağlantısı', $pdo instanceof PDO, $pdo instanceof PDO ? 'bağlı' : 'bağlantı yok'),
    healthRow('database', 'Veritabanı sürücüsü', $pdo instanceof PDO, $pdo instanceof PDO ? (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'yok'),
    healthRow('database', 'Çekirdek tablolar', count($missingCoreTables) === 0, count($missingCoreTables) === 0 ? count($coreTables) . ' tablo mevcut' : 'Eksik: ' . implode(', ', $missingCoreTables)),
    healthRow('database', 'Çalışma zamanında şema güncelleme', !$isProduction || !$runtimeSchemaAllowed, $runtimeSchemaAllowed ? 'aktif' : 'kapalı', 'warning'),
    healthRow('database', 'Şema dosyası (database/schema.sql)', is_file($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'), 'kurulum ve referans şema için gerekli'),
    healthRow('database', 'PHP dosyaları', $loadDatabaseSection ? $phpFileCount > 0 : true, $loadDatabaseSection ? $phpFileCount . ' adet PHP dosyası' : 'canlı hızlı görünümde atlandı', $loadDatabaseSection ? 'required' : 'info'),
    healthRow('database', 'storage/cache yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'), healthPath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'), 'warning'),
    healthRow('database', 'uploads yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'uploads'), healthPath($root . DIRECTORY_SEPARATOR . 'uploads')),
    healthRow('database', 'storage/logs yazılabilir', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'), healthPath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs')),
    healthRow('database', 'Disk kapasitesi', true, healthDiskDetail($root), 'info'),
    healthRow('database', 'İstek sınırı uyumu', $rateLimitHelperReady && healthTableExists($pdo, 'request_rate_limits'), $loadDatabaseSection ? (($rateLimitHelperReady ? 'yardımcı hazır' : 'yardımcı eksik') . ', request_rate_limits=' . $requestRateLimitRows) : 'canlı hızlı görünümde sayım atlandı', $loadDatabaseSection ? 'warning' : 'info', $baseUri . '/admin/rate-limits.php', 'İstek Sınırları'),

    healthRow('logs', 'Çalışma zamanı kritik kayıtları', (int) $runtimeLogSummary['critical'] === 0, (int) $runtimeLogSummary['critical'] === 0 ? 'son kayıtlarda kritik hata yok' : $runtimeLogSummary['critical'] . ' kritik sinyal; son: ' . $runtimeLogSummary['latest']),
    healthRow('logs', 'Çalışma zamanı hata kayıtları', (int) $runtimeLogSummary['errors'] === 0, (int) $runtimeLogSummary['errors'] === 0 ? $runtimeLogSummary['files'] . ' log dosyası tarandı' : $runtimeLogSummary['errors'] . ' hata/uyarı sinyali; son: ' . $runtimeLogSummary['latest'], 'warning', $baseUri . '/admin/system-health.php?tab=logs&logs_view=center', 'Hata Merkezi'),
    healthRow('logs', 'Uygulama hataları (24s)', $appErrors24h === 0, $appErrors24h . ' hata/kritik kayıt', 'warning', $baseUri . '/admin/system-health.php?tab=logs&logs_view=center', 'Hata Merkezi'),
    healthRow('logs', 'Uygulama hataları (7g)', $appErrors7d === 0, $appErrors7d . ' hata/kritik kayıt', 'warning', $baseUri . '/admin/system-health.php?tab=logs&logs_view=center', 'Hata Merkezi'),
    healthRow('logs', 'Son uygulama kaydı', true, $latestAppLog, 'info', $baseUri . '/admin/system-health.php?tab=logs&logs_view=center', 'Hata Merkezi'),
    healthRow('logs', 'Bugünkü kullanıcı işlemleri', true, $activityToday . ' işlem kaydı', 'info', $baseUri . '/admin/action-log.php', 'Kullanıcı İşlem Günlüğü'),

    healthRow('queues', 'E-posta kuyruğu', $emailFailed === 0, $emailQueued . ' bekleyen/işlenen, ' . $emailFailed . ' başarısız', 'warning', $baseUri . '/admin/notifications.php?tab=logs', 'Bildirimler'),
    healthRow('queues', 'Cron betik dosyaları', count($missingCronScripts) === 0, count($missingCronScripts) === 0 ? count($cronScriptPaths) . ' betik mevcut' : 'Eksik: ' . implode(', ', $missingCronScripts), 'warning'),
    healthRow('queues', 'Sıkışan e-posta işlemleri', $emailStuckProcessing === 0, $emailStuckProcessing . ' işlem 15 dakikadan uzun süredir işleniyor', 'warning', $baseUri . '/admin/notifications.php?tab=logs&email=processing', 'Kuyruk'),
    healthRow('queues', 'Son e-posta hatası', $emailFailed === 0, $latestFailedEmail, 'warning', $baseUri . '/admin/notifications.php?tab=logs&email=failed', 'Hatalılar'),
    healthRow('queues', 'Bildirim e-posta cronu', healthCronIsFresh($cronRuns['notification_email_queue'], 30, $notificationEmailEnabled), $notificationEmailEnabled ? healthCronDetail($cronRuns['notification_email_queue'], 'cron kaydı yok; worker çalışmıyor olabilir') : 'e-posta kuyruğu kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/notifications.php', 'Bildirimler'),
    healthRow('queues', 'Liderlik cronu', healthCronIsFresh($cronRuns['leaderboard_cache'], 1440, true), healthCronDetail($cronRuns['leaderboard_cache'], 'son 24 saat için cron kaydı yok'), 'warning', $baseUri . '/admin/leaderboard.php', 'Liderlik'),
    healthRow('queues', 'Süre sınırı temizleme cronu', healthCronIsFresh($cronRuns['rate_limits_cleanup'], 180, true), healthCronDetail($cronRuns['rate_limits_cleanup'], 'son 3 saat içinde cron kaydı yok; süresi dolan kayıtlar birikiyor olabilir'), 'warning', $baseUri . '/admin/rate-limits.php?status=expired', 'Temizle'),
    healthRow('queues', 'Etkinlik e-posta kuyruğu', $eventsEmailFailed === 0, $eventsEmailPending . ' bekleyen, ' . $eventsEmailFailed . ' hatalı', 'warning', $baseUri . '/admin/events.php?tab=settings', 'Etkinlikler'),
    healthRow('queues', 'Etkinlik ana cronu', healthCronIsFresh($cronRuns['events_master'], 30, $eventsSystemEnabled), $eventsSystemEnabled ? healthCronDetail($cronRuns['events_master'], 'son 30 dakika içinde cron kaydı yok; master cron çalışmıyor olabilir') : 'events sistemi kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/events.php', 'Etkinlikler'),

    healthRow('queues', 'Süresi geçmiş ödüller', $expiredRewards === 0, $expiredRewards . ' süresi geçmiş bekleyen ödül', 'warning', $baseUri . '/admin/events-rewards.php', 'Ödüller'),
    healthRow('queues', 'Süresi dolmuş istek sınırı kayıtları', $expiredRateLimits < 500, $expiredRateLimits . ' temizlenebilir kayıt', 'warning', $baseUri . '/admin/rate-limits.php?status=expired', 'Temizle'),
    healthRow('queues', 'Bakım modu', in_array($maintenanceMode, ['0', '1'], true), $maintenanceMode === '1' ? 'aktif: ' . $maintenanceMessage : 'kapalı', 'warning', $baseUri . '/admin/settings.php#general', 'Ayarlar'),

    healthRow('content', 'Konu raporları', $topicReportsOpen === 0, $topicReportsOpen . ' açık/incelenen rapor', 'warning', $baseUri . '/admin/complaints-reports.php?tab=topics&status=open', 'Raporlar'),
    healthRow('content', 'Kullanıcı şikayetleri', $userReportsOpen === 0, $userReportsOpen . ' açık/incelenen şikayet', 'warning', $baseUri . '/admin/complaints-reports.php?tab=users&status=open', 'Şikayetler'),
    healthRow('content', 'Taslak konular', $pendingTopics === 0, $pendingTopics . ' taslak konu', 'warning', $baseUri . '/admin/topics.php?status=draft', 'Konular'),
    healthRow('content', 'Bağlantısız medya kayıtları', $orphanMedia === 0, $orphanMedia . ' konuya bağlı olmayan medya kaydı', 'warning', $baseUri . '/admin/media-manager.php', 'Medya'),
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
if ($loadDatabaseSection && $pdo) {
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

    <?php if ($activeTab === 'logs'): ?>
    <nav class="site-subtabs health-log-subtabs" aria-label="Loglar ve hatalar alt sekmeleri">
        <a class="site-subtab-link logs-subtab-link <?= $logsView === 'summary' ? 'active' : '' ?>" href="<?= htmlspecialchars($logsSummaryUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-journal-text"></i>
            <span>Loglar & Hatalar</span>
        </a>
        <a class="site-subtab-link logs-subtab-link <?= $logsView === 'center' ? 'active' : '' ?>" href="<?= htmlspecialchars($logsCenterUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-exclamation-octagon"></i>
            <span>Hata Merkezi</span>
        </a>
    </nav>
    <?php endif; ?>

    <?php if ($activeTab !== 'logs' || $logsView === 'summary'): ?>
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
    <?php endif; ?>

    <?php if ($activeTab === 'logs' && $logsView === 'center'): ?>
    <section class="admin-card health-log-center ui-panel">
        <div class="health-panel-head">
            <div>
                <h2>Hata Merkezi</h2>
                <p>Çalışma zamanı ve uygulama hatalarını tek ekranda izleyin, filtreleyin ve aksiyon alın.</p>
            </div>
            <div class="ui-admin-page-hero-actions">
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars((string) $baseUri . '/admin/application-logs.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-journal-code"></i> Tüm Uygulama Logları
                </a>
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars((string) $baseUri . '/admin/email-logs.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-envelope-paper"></i> E-posta Logları
                </a>
            </div>
        </div>

        <div class="health-log-center-body">
            <div class="admin-stat-grid health-log-summary ui-grid">
                <div class="admin-stat-card stat-danger health-log-stat ui-card">
                    <div class="stat-icon"><i class="bi bi-exclamation-octagon"></i></div>
                    <div class="stat-content"><span class="stat-label">Çalışma zamanı kritik</span><span class="stat-value"><?= number_format((int) ($runtimeLogSummary['critical'] ?? 0)) ?></span></div>
                </div>
                <div class="admin-stat-card stat-warning health-log-stat ui-card">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-content"><span class="stat-label">Çalışma zamanı hata</span><span class="stat-value"><?= number_format((int) ($runtimeLogSummary['errors'] ?? 0)) ?></span></div>
                </div>
                <div class="admin-stat-card stat-danger health-log-stat ui-card">
                    <div class="stat-icon"><i class="bi bi-calendar2-day"></i></div>
                    <div class="stat-content"><span class="stat-label">Uygulama hataları (24s)</span><span class="stat-value"><?= number_format((int) $appErrors24h) ?></span></div>
                </div>
                <div class="admin-stat-card stat-warning health-log-stat ui-card">
                    <div class="stat-icon"><i class="bi bi-calendar2-week"></i></div>
                    <div class="stat-content"><span class="stat-label">Uygulama hataları (7g)</span><span class="stat-value"><?= number_format((int) $appErrors7d) ?></span></div>
                </div>
            </div>

            <div class="admin-card ui-panel health-log-filter-panel">
                <div class="card-header logs-toolbar-head ui-panel__head">
                    <form method="get" action="system-health.php" class="logs-filter-form health-log-filter-form">
                        <input type="hidden" name="tab" value="logs">
                        <input type="hidden" name="logs_view" value="center">
                        <input type="text" name="log_q" class="ui-admin-form-control" placeholder="Mesaj, kanal veya IP ara..." value="<?= htmlspecialchars($logSearch, ENT_QUOTES, 'UTF-8') ?>">
                        <select name="log_level" class="ui-admin-form-select">
                            <option value="">Tüm Seviyeler</option>
                            <?php foreach ($logAllowedLevels as $levelOption): ?>
                                <option value="<?= htmlspecialchars((string) $levelOption, ENT_QUOTES, 'UTF-8') ?>" <?= $logLevel === (string) $levelOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(healthApplicationLogLevelLabel((string) $levelOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="log_channel" class="ui-admin-form-select">
                            <option value="">Tüm Kanallar</option>
                            <?php foreach ($applicationErrorChannels as $channelOption): ?>
                                <option value="<?= htmlspecialchars((string) $channelOption, ENT_QUOTES, 'UTF-8') ?>" <?= $logChannel === (string) $channelOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(healthApplicationLogChannelLabel((string) $channelOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                        <?php if ($logHasFilters): ?>
                            <a href="system-health.php?tab=logs&logs_view=center" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="health-log-grid">
                <article class="admin-card ui-panel health-log-block">
                    <div class="card-header ui-panel__head health-log-block-head">
                        <h3><i class="bi bi-activity"></i> Çalışma Zamanı Hata Akışı</h3>
                        <span class="ui-admin-badge ui-admin-badge-muted"><?= number_format(count($runtimeLogEntries), 0, ',', '.') ?> kayıt</span>
                    </div>
                    <div class="health-log-table-wrap">
                        <table class="health-table health-log-table">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Seviye</th>
                                    <th>Dosya</th>
                                    <th>Mesaj</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($runtimeTotalRows === 0): ?>
                                    <tr>
                                        <td colspan="4" class="health-log-empty">Çalışma zamanı log dosyalarında hata sinyali bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($runtimePageItems as $runtimeRow): ?>
                                        <?php
                                        $runtimeTone = ((string) ($runtimeRow['level'] ?? '') === 'critical') ? 'bad' : 'warn';
                                        $runtimeLevel = healthRuntimeLogLabel((string) ($runtimeRow['level'] ?? 'error'));
                                        $runtimeTs = (int) ($runtimeRow['timestamp'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($runtimeTs > 0 ? date('d.m.Y H:i:s', $runtimeTs) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="health-badge <?= htmlspecialchars($runtimeTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($runtimeLevel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="health-log-file"><?= htmlspecialchars((string) ($runtimeRow['file'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="health-log-message"><?= htmlspecialchars((string) ($runtimeRow['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($runtimeTotalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($runtimePage > 1): ?>
                                    <a href="<?= htmlspecialchars($runtimePageBase . ($runtimePage - 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Önceki"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $runtimePage - 2); $i <= min($runtimeTotalPages, $runtimePage + 2); $i++): ?>
                                    <a href="<?= htmlspecialchars($runtimePageBase . $i, ENT_QUOTES, 'UTF-8') ?>" class="page-link <?= $i === $runtimePage ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($runtimePage < $runtimeTotalPages): ?>
                                    <a href="<?= htmlspecialchars($runtimePageBase . ($runtimePage + 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Sonraki"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="admin-card ui-panel health-log-block">
                    <div class="card-header ui-panel__head health-log-block-head">
                        <h3><i class="bi bi-journal-code"></i> Uygulama Hata Kayıtları</h3>
                        <span class="ui-admin-badge ui-admin-badge-muted">
                            <?= number_format((int) ($applicationErrorFeed['total'] ?? 0), 0, ',', '.') ?> toplam
                            <?php if ((int) ($applicationErrorFeed['total'] ?? 0) > count($applicationErrorFeed['items'] ?? [])): ?>
                                / <?= number_format(count($applicationErrorFeed['items'] ?? []), 0, ',', '.') ?> gösterim
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="health-log-table-wrap">
                        <table class="health-table health-log-table">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Seviye</th>
                                    <th>Kanal</th>
                                    <th>Mesaj</th>
                                    <th>Detay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applicationErrorFeed['items'])): ?>
                                    <tr>
                                        <td colspan="5" class="health-log-empty">Filtreye uyan uygulama hata kaydı yok.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($applicationErrorFeed['items'] as $appRow): ?>
                                        <?php
                                        $appLevel = (string) ($appRow['level'] ?? '');
                                        $appTone = healthApplicationErrorTone($appLevel);
                                        $appDateRaw = (string) ($appRow['created_at'] ?? '');
                                        $appDate = $appDateRaw !== '' ? (strtotime($appDateRaw) ?: null) : null;
                                        $contextExcerpt = trim((string) ($appRow['context_excerpt'] ?? ''));
                                        $ipAddress = trim((string) ($appRow['ip_address'] ?? ''));
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($appDate !== null ? date('d.m.Y H:i:s', $appDate) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="health-badge <?= htmlspecialchars($appTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(healthApplicationLogLevelLabel($appLevel !== '' ? $appLevel : 'log'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><?= htmlspecialchars(healthApplicationLogChannelLabel((string) ($appRow['channel'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="health-log-message"><?= htmlspecialchars((string) ($appRow['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="health-log-detail-cell">
                                                <?php if ($contextExcerpt !== ''): ?>
                                                    <div class="health-log-context"><?= htmlspecialchars($contextExcerpt, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                                <?php if ($ipAddress !== ''): ?>
                                                    <div class="health-log-ip">IP: <?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($appTotalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($appPage > 1): ?>
                                    <a href="<?= htmlspecialchars($appPageBase . ($appPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Önceki"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $appPage - 2); $i <= min($appTotalPages, $appPage + 2); $i++): ?>
                                    <a href="<?= htmlspecialchars($appPageBase . $i, ENT_QUOTES, 'UTF-8') ?>" class="page-link <?= $i === $appPage ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($appPage < $appTotalPages): ?>
                                    <a href="<?= htmlspecialchars($appPageBase . ($appPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Sonraki"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
