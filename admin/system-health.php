<?php

declare(strict_types=1);

$pageTitle = 'Sistem Sağlığı';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/UserActivity/Support/helpers.php';

$settings = function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];



adminRequirePermission('system.view', 'Sistem sağlığını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$root = dirname(__DIR__);

function healthDatabaseReadableBytes(int|float $bytes): string
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

function healthDatabaseOptimizeThresholds(): array
{
    return [
        'min_overhead_bytes' => 5 * 1024 * 1024,
        'min_percent_overhead_bytes' => 1024 * 1024,
        'min_overhead_percent' => 10.0,
        'large_table_bytes' => 256 * 1024 * 1024,
    ];
}

function healthDatabaseSupportsOptimize(?PDO $pdo): bool
{
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    } catch (Throwable) {
        return false;
    }
}

function healthDatabaseQuoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function healthDatabaseTableStats(?PDO $pdo): array
{
    if (!healthDatabaseSupportsOptimize($pdo)) {
        return [];
    }

    try {
        $stmt = $pdo->query("
            SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY DATA_FREE DESC, TABLE_NAME ASC
        ");
    } catch (Throwable) {
        return [];
    }

    $thresholds = healthDatabaseOptimizeThresholds();
    $rows = [];
    foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) {
        $sizeBytes = (int) ($row['DATA_LENGTH'] ?? 0) + (int) ($row['INDEX_LENGTH'] ?? 0);
        $overheadBytes = (int) ($row['DATA_FREE'] ?? 0);
        $overheadPercent = $sizeBytes > 0 ? ($overheadBytes / $sizeBytes) * 100 : 0.0;
        $recommended = $overheadBytes >= $thresholds['min_overhead_bytes']
            || ($overheadBytes >= $thresholds['min_percent_overhead_bytes'] && $overheadPercent >= $thresholds['min_overhead_percent']);

        $rows[] = [
            'name' => (string) ($row['TABLE_NAME'] ?? ''),
            'engine' => (string) ($row['ENGINE'] ?? '-'),
            'rows' => (int) ($row['TABLE_ROWS'] ?? 0),
            'size_bytes' => $sizeBytes,
            'overhead_bytes' => $overheadBytes,
            'overhead_percent' => $overheadPercent,
            'recommended' => $recommended,
            'large' => $sizeBytes >= $thresholds['large_table_bytes'],
        ];
    }

    return $rows;
}

function healthDatabaseStatsSummary(array $tableStats): array
{
    $summary = ['tables' => count($tableStats), 'size_bytes' => 0, 'overhead_bytes' => 0, 'large_tables' => 0];
    foreach ($tableStats as $row) {
        $summary['size_bytes'] += (int) ($row['size_bytes'] ?? 0);
        $summary['overhead_bytes'] += (int) ($row['overhead_bytes'] ?? 0);
        if (!empty($row['large'])) {
            $summary['large_tables']++;
        }
    }

    return $summary;
}

function healthDatabaseOptimizationCandidates(array $tableStats): array
{
    $candidates = array_values(array_filter($tableStats, static function (array $row): bool {
        return !empty($row['recommended']) && (int) ($row['overhead_bytes'] ?? 0) > 0 && (string) ($row['name'] ?? '') !== '';
    }));

    usort($candidates, static fn (array $a, array $b): int => ((int) ($b['overhead_bytes'] ?? 0)) <=> ((int) ($a['overhead_bytes'] ?? 0)));

    return $candidates;
}

function healthDatabaseStatsByName(array $tableStats): array
{
    $map = [];
    foreach ($tableStats as $row) {
        $name = (string) ($row['name'] ?? '');
        if ($name !== '') {
            $map[$name] = $row;
        }
    }

    return $map;
}

function healthDatabaseSelectedTables(mixed $rawTables, array $allowedTables): array
{
    $allowed = array_fill_keys($allowedTables, true);
    $selected = [];
    foreach ((array) $rawTables as $table) {
        $table = trim((string) $table);
        if ($table !== '' && isset($allowed[$table])) {
            $selected[$table] = $table;
        }
    }

    return array_values($selected);
}

function healthDatabaseOptimizeTables(PDO $pdo, array $tableNames, array $statsByName): array
{
    $result = [
        'optimized' => 0,
        'failed' => 0,
        'before_overhead_bytes' => 0,
        'after_overhead_bytes' => 0,
        'saved_bytes' => 0,
        'items' => [],
    ];

    foreach ($tableNames as $tableName) {
        $before = (int) ($statsByName[$tableName]['overhead_bytes'] ?? 0);
        $result['before_overhead_bytes'] += $before;
        $messages = [];
        $ok = true;

        try {
            $stmt = $pdo->query('OPTIMIZE TABLE ' . healthDatabaseQuoteIdentifier($tableName));
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $messageRow) {
                $type = strtolower((string) ($messageRow['Msg_type'] ?? 'status'));
                $text = (string) ($messageRow['Msg_text'] ?? '');
                $messages[] = trim(($type !== '' ? $type . ': ' : '') . $text);
                if ($type === 'error') {
                    $ok = false;
                }
            }
        } catch (Throwable $e) {
            $ok = false;
            $messages[] = safeErrorMessage($e);
        }

        $result[$ok ? 'optimized' : 'failed']++;
        $result['items'][] = [
            'table' => $tableName,
            'ok' => $ok,
            'before_overhead_bytes' => $before,
            'messages' => array_slice(array_values(array_filter($messages)), 0, 4),
        ];
    }

    $afterStats = healthDatabaseStatsByName(healthDatabaseTableStats($pdo));
    foreach ($result['items'] as &$item) {
        $after = (int) ($afterStats[(string) $item['table']]['overhead_bytes'] ?? 0);
        $item['after_overhead_bytes'] = $after;
        $item['saved_bytes'] = max(0, (int) $item['before_overhead_bytes'] - $after);
        $result['after_overhead_bytes'] += $after;
        $result['saved_bytes'] += (int) $item['saved_bytes'];
    }
    unset($item);

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'optimize_db') {
    adminRequirePermission('system.manage', 'Veritabanı optimizasyonu için gerekli izin hesabınıza tanımlanmamış.');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        try {
            if (!healthDatabaseSupportsOptimize($pdo)) {
                throw new RuntimeException('Bu optimizasyon aracı yalnızca MySQL/MariaDB bağlantılarında desteklenir.');
            }

            $tableStats = healthDatabaseTableStats($pdo);
            $candidates = healthDatabaseOptimizationCandidates($tableStats);
            $candidateNames = array_map(static fn (array $row): string => (string) $row['name'], $candidates);
            $explicitSelection = isset($_POST['table_selection_present']);
            $selectedTables = healthDatabaseSelectedTables($_POST['tables'] ?? [], $candidateNames);
            if (!$explicitSelection && $selectedTables === []) {
                $selectedTables = $candidateNames;
            }

            if ($candidateNames === []) {
                flash('info', 'Optimizasyon eşiğini aşan tablo bulunamadı. İşlem çalıştırılmadı.');
            } elseif ($selectedTables === []) {
                flash('error', 'Optimize etmek için en az bir önerilen tablo seçmelisiniz.');
            } else {
                $optimizeResult = healthDatabaseOptimizeTables($pdo, $selectedTables, healthDatabaseStatsByName($tableStats));
                $message = $optimizeResult['optimized'] . ' tablo optimize edildi';
                if ((int) $optimizeResult['failed'] > 0) {
                    $message .= ', ' . $optimizeResult['failed'] . ' tabloda hata oluştu';
                }
                $message .= '. Tahmini kazanım: ' . healthDatabaseReadableBytes((int) $optimizeResult['saved_bytes']) . '.';

                if (function_exists('appLog')) {
                    appLog($pdo, (int) $optimizeResult['failed'] > 0 ? 'warning' : 'info', 'system', 'database_optimize', [
                        'tables' => $selectedTables,
                        'optimized' => (int) $optimizeResult['optimized'],
                        'failed' => (int) $optimizeResult['failed'],
                        'before_overhead_bytes' => (int) $optimizeResult['before_overhead_bytes'],
                        'after_overhead_bytes' => (int) $optimizeResult['after_overhead_bytes'],
                        'saved_bytes' => (int) $optimizeResult['saved_bytes'],
                        'items' => $optimizeResult['items'],
                    ]);
                }
                if (function_exists('adminAuditLogger')) {
                    adminAuditLogger()->logAction($pdo, 'database_optimized', 'database', 0, 'Veritabanı tabloları optimize edildi', [], [
                        'tables' => $selectedTables,
                        'optimized' => (int) $optimizeResult['optimized'],
                        'failed' => (int) $optimizeResult['failed'],
                        'saved_bytes' => (int) $optimizeResult['saved_bytes'],
                    ], false);
                }

                flash((int) $optimizeResult['failed'] > 0 ? 'error' : 'success', $message);
            }
        } catch (Throwable $e) {
            flash('error', 'Optimizasyon sırasında bir hata oluştu: ' . safeErrorMessage($e));
        }
    }
    header("Location: system-health.php?tab=database");
    exit;
}

function healthSettingEnabled(array $settings, string $key, string $default = '1'): bool
{
    $value = $settings[$key] ?? $default;
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

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
    ?int $perPage = null
): array {
    $safePage = max(1, $page);
    $safePerPage = min(adminPaginationPerPage(), max(1, $perPage ?? adminPaginationPerPage()));
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
$notificationEmailEnabled = healthSettingEnabled($healthAdminSettings, 'notif_email_channel_ready', '0');
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
$runtimePerPage = adminPaginationPerPage();
$appPerPage = adminPaginationPerPage();
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
$runtimePageBase = 'system-health.php?' . http_build_query(array_merge($logsCenterBaseParams, ['app_page' => $appPage])) . '&runtime_page=';
$appPageBase = 'system-health.php?' . http_build_query(array_merge($logsCenterBaseParams, ['runtime_page' => $runtimePage])) . '&app_page=';
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
    ? healthScalar($pdo, "SELECT COUNT(*) FROM user_activity_events e WHERE e.created_at >= CURDATE() AND e.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND e.event_group NOT IN ('admin', 'moderation') AND e.event_type NOT IN ('admin_user_updated', 'group_save', 'group_deactivate', 'user_group_changed', 'user_status_changed', 'user_banned', 'user_unbanned', 'user_restricted', 'user_restriction_removed', 'user_restrictions_cleared', 'user_admin_note_added', 'settings_updated', 'topic_settings_updated', 'topic_moderated', 'topic_revision_restored', 'topic_health_scan_completed', 'topic_health_cleared', 'download_link_checked', 'category_created', 'category_updated', 'category_deleted', 'media_uploaded', 'media_deleted', 'leaderboard_recalculated', 'leaderboard_cache_cleared', 'leaderboard_settings_updated', 'admin_action_log_cleared', 'application_logs_cleared', 'email_logs_cleared', 'activity_logs_cleared', 'rate_limit_records_deleted', 'cron_logs_cleared', 'cron_manual_triggered', 'bot_import_published') AND e.event_type NOT LIKE 'topic_bulk_%' AND NOT EXISTS (SELECT 1 FROM user_group_members ugm INNER JOIN user_groups ug ON ug.id = ugm.group_id LEFT JOIN user_group_permissions ugp ON ugp.group_id = ug.id AND ugp.permission_value = 1 AND ugp.permission_key IN ('*', 'admin.access') WHERE ugm.user_id = e.actor_user_id AND ug.is_active = 1 AND (ug.slug = 'admin' OR ugp.permission_key IS NOT NULL))")
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
$verificationReminderEnabled = healthSettingEnabled($settings, 'account_email_verification_enabled', '0')
    && healthSettingEnabled($settings, 'account_email_verification_required', '0')
    && healthSettingEnabled($settings, 'account_email_verification_reminder_enabled', '1');
$verificationReminderAfterMinutes = max(60, min(10080, (int) ($settings['account_email_verification_reminder_after_minutes'] ?? 1440)));
$verificationReminderEligibleUsers = $loadDatabaseSection && $verificationReminderEnabled && healthTableExists($pdo, 'users')
    ? healthScalar(
        $pdo,
        "SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL AND email_verification_sent_at IS NOT NULL AND email_verification_sent_at <= DATE_SUB(NOW(), INTERVAL {$verificationReminderAfterMinutes} MINUTE)"
    )
    : 0;
$cronScriptPaths = [
    'Bildirim e-posta' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'send-notification-email-queue.php',
    'Liderlik cache' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'update-leaderboard-cache.php',
    'Doğrulama hatırlatma' => $root . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'send-verification-reminders.php',
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
        'verification_reminders' => healthCronLastRun($pdo, 'verification_reminders'),
        'rate_limits_cleanup' => healthCronLastRun($pdo, 'rate_limits_cleanup'),
        'events_master' => healthCronLastRun($pdo, 'events_master'),
    ]
    : [
        'notification_email_queue' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
        'leaderboard_cache' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
        'verification_reminders' => ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []],
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

$dbOptimizationThresholds = healthDatabaseOptimizeThresholds();
$dbTableStats = [];
$dbOptimizationCandidates = [];
$dbSummary = ['tables' => 0, 'size_bytes' => 0, 'overhead_bytes' => 0, 'large_tables' => 0];
$dbLargeOptimizationCandidates = 0;
$dbDefaultSelectedOptimizationCandidates = 0;
if ($loadDatabaseSection && healthDatabaseSupportsOptimize($pdo)) {
    $dbTableStats = healthDatabaseTableStats($pdo);
    $dbOptimizationCandidates = healthDatabaseOptimizationCandidates($dbTableStats);
    $dbSummary = healthDatabaseStatsSummary($dbTableStats);
    $dbLargeOptimizationCandidates = count(array_filter($dbOptimizationCandidates, static fn (array $row): bool => !empty($row['large'])));
    $dbDefaultSelectedOptimizationCandidates = count($dbOptimizationCandidates) - $dbLargeOptimizationCandidates;
}
$dbSizeMb = round(((int) ($dbSummary['size_bytes'] ?? 0)) / 1024 / 1024, 2);
$dbOverheadMb = round(((int) ($dbSummary['overhead_bytes'] ?? 0)) / 1024 / 1024, 2);
$dbOptimizationDetail = !$loadDatabaseSection
    ? 'canlı hızlı görünümde tablo analizi atlandı'
    : (!healthDatabaseSupportsOptimize($pdo)
        ? 'MySQL/MariaDB dışı sürücü; optimize analizi desteklenmiyor'
        : (count($dbOptimizationCandidates) === 0
        ? 'optimizasyon eşiğini aşan tablo yok'
        : count($dbOptimizationCandidates) . ' önerilen tablo, toplam overhead ' . healthDatabaseReadableBytes((int) ($dbSummary['overhead_bytes'] ?? 0))));

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
    healthRow('database', 'Şema dosyası (database/schema.sql)', is_file($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'), 'kurulum ve referans şema için gerekli'),
    healthRow('database', 'Veritabanı optimizasyon önerisi', !$loadDatabaseSection || count($dbOptimizationCandidates) === 0, $dbOptimizationDetail, $loadDatabaseSection ? 'warning' : 'info'),
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

    healthRow('queues', 'E-posta kuyruğu', $emailFailed === 0, $emailQueued . ' bekleyen/işlenen, ' . $emailFailed . ' başarısız', 'warning', $baseUri . '/admin/notifications.php?tab=email', 'E-posta Bildirimleri'),
    healthRow('queues', 'Cron betik dosyaları', count($missingCronScripts) === 0, count($missingCronScripts) === 0 ? count($cronScriptPaths) . ' betik mevcut' : 'Eksik: ' . implode(', ', $missingCronScripts), 'warning'),
    healthRow('queues', 'Sıkışan e-posta işlemleri', $emailStuckProcessing === 0, $emailStuckProcessing . ' işlem 15 dakikadan uzun süredir işleniyor', 'warning', $baseUri . '/admin/notifications.php?tab=email', 'E-posta Bildirimleri'),
    healthRow('queues', 'Son e-posta hatası', $emailFailed === 0, $latestFailedEmail, 'warning', $baseUri . '/admin/system-health.php?tab=logs&logs_view=center', 'Hata Merkezi'),
    healthRow('queues', 'Bildirim e-posta cronu', healthCronIsFresh($cronRuns['notification_email_queue'], 30, $notificationEmailEnabled), $notificationEmailEnabled ? healthCronDetail($cronRuns['notification_email_queue'], 'cron kaydı yok; worker çalışmıyor olabilir') : 'e-posta kuyruğu kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/notifications.php?tab=email', 'E-posta Bildirimleri'),
    healthRow('queues', 'Doğrulama hatırlatma cronu', healthCronIsFresh($cronRuns['verification_reminders'], 180, $verificationReminderEnabled), $verificationReminderEnabled ? healthCronDetail($cronRuns['verification_reminders'], 'son 3 saat içinde cron kaydı yok; doğrulama hatırlatmaları gecikiyor olabilir') . ' • bekleyen hesap: ' . $verificationReminderEligibleUsers : 'e-posta doğrulama hatırlatma kapalı; cron zorunlu değil', 'warning', $baseUri . '/admin/settings.php#user_system', 'Kullanıcı Sistemi'),
    healthRow('queues', 'Liderlik cronu', healthCronIsFresh($cronRuns['leaderboard_cache'], 1440, true), healthCronDetail($cronRuns['leaderboard_cache'], 'son 24 saat için cron kaydı yok'), 'warning', $baseUri . '/admin/leaderboard', 'Liderlik'),
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

require_once __DIR__ . '/header.php';
?>
<div class="health-page">
    <?= adminRenderPageHero('bi-clipboard2-pulse', 'Operasyon merkezi', 'Sistem Sağlığı', 'Ortam, güvenlik, veritabanı, loglar, kuyruklar ve içerik sinyallerini tek ekranda izleyin.', [], [
        'actions_html' => '<span class="ui-admin-badge ' . ($requiredIssues > 0 ? 'ui-admin-badge-danger' : ($warningIssues > 0 ? 'ui-admin-badge-warning' : 'ui-admin-badge-success')) . '"><i class="bi ' . ($requiredIssues > 0 ? 'bi-exclamation-triangle-fill' : 'bi-check2-circle') . '"></i> Skor ' . (int) $healthScore . '/100</span>',
    ]) ?>

    <?= adminRenderStatCards([
        ['tone' => $requiredIssues > 0 ? 'danger' : 'success', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Zorunlu Sorun', 'value' => number_format($requiredIssues, 0, ',', '.'), 'class' => 'health-stat'],
        ['tone' => $warningIssues > 0 ? 'warning' : 'success', 'icon' => 'bi-cone-striped', 'label' => 'Uyarı', 'value' => number_format($warningIssues, 0, ',', '.'), 'class' => 'health-stat'],
        ['tone' => $operationsCount > 0 ? 'warning' : 'success', 'icon' => 'bi-inboxes-fill', 'label' => 'Operasyon Yükü', 'value' => number_format($operationsCount, 0, ',', '.'), 'class' => 'health-stat'],
        ['tone' => $maintenanceMode === '1' ? 'warning' : 'success', 'icon' => $maintenanceMode === '1' ? 'bi-tools' : 'bi-check-circle-fill', 'label' => 'Bakım Modu', 'value' => $maintenanceMode === '1' ? 'Açık' : 'Kapalı', 'class' => 'health-stat'],
    ], ['class' => 'health-summary', 'aria_label' => 'Sistem sağlığı özeti']) ?>

    <?php if ($activeTab === 'database'): ?>
    <?= adminRenderPanelShellOpen(['class' => 'ui-admin-db-status-panel health-db-optimize-panel']) ?>
        <form method="post" action="system-health.php?tab=database" class="health-db-optimize-form"<?= adminConfirmAttrs(['message' => 'Seçili tablolar sırayla optimize edilecek. Büyük tablolarda kısa süreli kilitlenme yaşanabilir. Devam edilsin mi?', 'title' => 'Veritabanı optimize edilsin mi?', 'ok' => 'Seçili Tabloları Optimize Et', 'tone' => 'warning']) ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="optimize_db">
            <input type="hidden" name="table_selection_present" value="1">
            <div class="health-db-optimize-head">
                <div>
                    <span class="health-kicker"><i class="bi bi-database-check"></i> Veritabanı Durumu</span>
                    <h3 class="ui-admin-db-status-title">Güvenli Veritabanı Optimizasyonu</h3>
                    <p class="ui-admin-db-status-copy">
                        Toplam Boyut: <strong><?= number_format($dbSizeMb, 2, ',', '.') ?> MB</strong>
                        <span class="ui-admin-db-status-note <?= count($dbOptimizationCandidates) > 0 ? 'is-danger' : 'is-success' ?>">
                            <i class="bi <?= count($dbOptimizationCandidates) > 0 ? 'bi-exclamation-circle' : 'bi-check-circle' ?>"></i>
                            <?= htmlspecialchars(healthDatabaseReadableBytes((int) ($dbSummary['overhead_bytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> overhead
                        </span>
                    </p>
                    <p class="health-db-optimize-copy">
                        Yalnızca overhead eşiğini aşan tablolar önerilir; işlem tablo tablo çalışır ve sonuç uygulama loguna yazılır.
                    </p>
                </div>
                <button type="submit" class="btn-primary <?= count($dbOptimizationCandidates) === 0 ? 'ui-admin-disabled-soft' : '' ?>" <?= count($dbOptimizationCandidates) === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-magic"></i> Seçili Tabloları Optimize Et
                </button>
            </div>

            <?php if (!healthDatabaseSupportsOptimize($pdo)): ?>
                <div class="ui-admin-alert ui-admin-alert-info health-db-optimize-alert">
                    Bu bağlantı MySQL/MariaDB değil; tablo optimizasyonu bu sürücüde çalıştırılmadı.
                </div>
            <?php elseif (count($dbOptimizationCandidates) === 0): ?>
                <div class="ui-admin-alert ui-admin-alert-success health-db-optimize-alert">
                    Optimizasyon eşiğini aşan tablo yok. Toplam <?= number_format((int) ($dbSummary['tables'] ?? 0), 0, ',', '.') ?> tablo sağlıklı görünüyor.
                </div>
            <?php else: ?>
                <?php if ($dbLargeOptimizationCandidates > 0): ?>
                    <div class="ui-admin-alert ui-admin-alert-warning health-db-optimize-alert">
                        <?= (int) $dbLargeOptimizationCandidates ?> büyük tablo öneri listesinde. Büyük tablolar kısa süreli kilitlenme riski nedeniyle varsayılan seçilmedi.
                    </div>
                <?php endif; ?>

                <?= adminRenderTableOpen([
                    ['html' => '<span class="ui-admin-sr-only">Seç</span>'],
                    'Tablo',
                    'Motor',
                    'Satır',
                    'Boyut',
                    'Overhead',
                    'Risk',
                ], [
                    'class' => 'health-db-optimize-table',
                    'wrap_class' => 'health-db-optimize-table-wrap',
                    'label' => 'Optimizasyon önerilen veritabanı tabloları',
                ]) ?>
                    <?php foreach ($dbOptimizationCandidates as $dbTable): ?>
                        <?php
                        $isLargeTable = !empty($dbTable['large']);
                        $tableName = (string) ($dbTable['name'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="scraper-check" name="tables[]" value="<?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?>" <?= !$isLargeTable ? 'checked' : '' ?>>
                            </td>
                            <td><strong><?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string) ($dbTable['engine'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((int) ($dbTable['rows'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(healthDatabaseReadableBytes((int) ($dbTable['size_bytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="health-db-overhead-value"><?= htmlspecialchars(healthDatabaseReadableBytes((int) ($dbTable['overhead_bytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="health-db-overhead-percent">%<?= number_format((float) ($dbTable['overhead_percent'] ?? 0), 1, ',', '.') ?></span>
                            </td>
                            <td>
                                <span class="ui-admin-badge <?= $isLargeTable ? 'ui-admin-badge-warning' : 'ui-admin-badge-info' ?>">
                                    <i class="bi <?= $isLargeTable ? 'bi-exclamation-triangle' : 'bi-check2-circle' ?>"></i>
                                    <?= $isLargeTable ? 'Büyük tablo' : 'Standart' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?= adminRenderTableClose() ?>
                <p class="health-db-optimize-footnote">
                    Varsayılan seçili tablo: <?= (int) $dbDefaultSelectedOptimizationCandidates ?>.
                    Eşik: <?= htmlspecialchars(healthDatabaseReadableBytes((int) $dbOptimizationThresholds['min_overhead_bytes']), ENT_QUOTES, 'UTF-8') ?> overhead veya
                    <?= htmlspecialchars(healthDatabaseReadableBytes((int) $dbOptimizationThresholds['min_percent_overhead_bytes']), ENT_QUOTES, 'UTF-8') ?> üzeri ve %<?= number_format((float) $dbOptimizationThresholds['min_overhead_percent'], 1, ',', '.') ?>+ overhead.
                </p>
            <?php endif; ?>
        </form>
    <?= adminRenderPanelShellClose() ?>
    <?php endif; ?>



    <?php
    $healthTabItems = [];
    foreach ($sections as $key => $meta) {
        $healthTabItems[$key] = [
            'href' => 'system-health.php?tab=' . urlencode((string) $key),
            'icon' => (string) $meta[1],
            'label' => (string) $meta[0],
            'description' => (string) $meta[2],
        ];
    }
    echo adminRenderTabBar($healthTabItems, $activeTab, [
        'class' => 'health-tabs',
        'link_class' => 'health-tab',
        'active_class' => 'is-active',
        'aria_label' => 'Sistem sağlığı sekmeleri',
        'copy_class' => 'health-tab-copy',
        'title_class' => 'health-tab-title',
        'description_class' => 'health-tab-desc',
    ]);
    ?>

    <?php if ($activeTab === 'logs'): ?>
    <?= adminRenderTabBar([
        'summary' => [
            'href' => $logsSummaryUrl,
            'icon' => 'bi-journal-text',
            'label' => 'Loglar & Hatalar',
        ],
        'center' => [
            'href' => $logsCenterUrl,
            'icon' => 'bi-exclamation-octagon',
            'label' => 'Hata Merkezi',
        ],
    ], $logsView, [
        'class' => 'health-log-subtabs',
        'link_class' => 'logs-subtab-link',
        'active_class' => 'active',
        'aria_label' => 'Loglar ve hatalar alt sekmeleri',
    ]) ?>
    <?php endif; ?>

    <?php if ($activeTab !== 'logs' || $logsView === 'summary'): ?>
    <?= adminRenderPanelShellOpen(['class' => 'health-panel']) ?>
        <div class="health-panel-head">
            <div>
                <h2><?= htmlspecialchars($sections[$activeTab][0]) ?></h2>
                <p><?= htmlspecialchars($activeTab === 'overview' && $problemChecks === [] ? 'Öncelikli sorun yok; temel kontroller sağlıklı görünüyor.' : $sections[$activeTab][2]) ?></p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-clock"></i><?= htmlspecialchars(date('d.m.Y H:i')) ?></span>
        </div>

        <?php if ($rowsForTab === []): ?>
            <?= adminRenderEmptyState([
                'icon' => 'bi-check2-circle',
                'tone' => 'success',
                'title' => 'Bu sekmede kontrol bulunamadı.',
                'description' => 'Yeni kontroller eklendikçe burada listelenecek.',
                'class' => 'health-empty',
            ]) ?>
        <?php else: ?>
            <?= adminRenderTableOpen([
                'Kontrol',
                'Durum',
                'Detay',
                ['label' => ''],
            ], [
                'class' => 'health-table',
                'wrap_class' => 'health-table-wrap',
                'label' => 'Sistem sağlığı kontrolleri',
            ]) ?>
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
            <?= adminRenderTableClose() ?>
        <?php endif; ?>
    <?= adminRenderPanelShellClose() ?>
    <?php endif; ?>

    <?php if ($activeTab === 'logs' && $logsView === 'center'): ?>
    <?= adminRenderPanelShellOpen(['class' => 'health-log-center']) ?>
        <div class="health-panel-head">
            <div>
                <h2>Hata Merkezi</h2>
                <p>Çalışma zamanı ve uygulama hatalarını tek ekranda izleyin, filtreleyin ve aksiyon alın.</p>
            </div>
            <div class="ui-admin-action-row health-log-actions">
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars((string) $baseUri . '/admin/application-logs.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-journal-code"></i> Tüm Uygulama Logları
                </a>
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars((string) $baseUri . '/admin/email-logs.php', ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-envelope-paper"></i> E-posta Logları
                </a>
            </div>
        </div>

        <div class="health-log-center-body">
            <?= adminRenderStatCards([
                ['tone' => 'danger', 'icon' => 'bi-exclamation-octagon', 'label' => 'Çalışma zamanı kritik', 'value' => number_format((int) ($runtimeLogSummary['critical'] ?? 0)), 'class' => 'health-log-stat'],
                ['tone' => 'warning', 'icon' => 'bi-exclamation-triangle', 'label' => 'Çalışma zamanı hata', 'value' => number_format((int) ($runtimeLogSummary['errors'] ?? 0)), 'class' => 'health-log-stat'],
                ['tone' => 'danger', 'icon' => 'bi-calendar2-day', 'label' => 'Uygulama hataları (24s)', 'value' => number_format((int) $appErrors24h), 'class' => 'health-log-stat'],
                ['tone' => 'warning', 'icon' => 'bi-calendar2-week', 'label' => 'Uygulama hataları (7g)', 'value' => number_format((int) $appErrors7d), 'class' => 'health-log-stat'],
            ], ['class' => 'health-log-summary', 'aria_label' => 'Hata merkezi özeti']) ?>

            <?= adminRenderFilterToolbarOpen('logs-toolbar-head ui-panel__head logs-toolbar-shell', 'health-log-filter-panel logs-toolbar-card') ?>
                    <form method="get" action="system-health.php" class="logs-filter-form health-log-filter-form admin-log-filter-form admin-filter-form">
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
            <?= adminRenderFilterToolbarClose() ?>

            <div class="health-log-grid">
                <?= adminRenderLogListPanelOpen([
                    'tag' => 'article',
                    'class' => 'health-log-block',
                    'header_class' => 'health-log-block-head',
                    'icon' => 'bi-activity',
                    'title' => 'Çalışma Zamanı Hata Akışı',
                    'actions_html' => '<span class="ui-admin-badge ui-admin-badge-muted">' . number_format(count($runtimeLogEntries), 0, ',', '.') . ' kayıt</span>',
                ]) ?>
                    <?= adminRenderLogTableOpen([
                        'wrapper_class' => 'health-log-table-wrap ui-table-wrap ui-surface admin-log-table-wrap',
                        'table_class' => 'health-table health-log-table admin-log-table',
                        'table_attrs' => ['aria-label' => 'Çalışma zamanı hata akışı'],
                    ]) ?>
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
                                    <?= adminRenderTableEmptyRow(4, [
                                        'icon' => 'bi-check2-circle',
                                        'tone' => 'success',
                                        'title' => 'Hata sinyali bulunamadı.',
                                        'description' => 'Çalışma zamanı log dosyalarında eşleşen hata kaydı yok.',
                                    ]) ?>
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
                    <?= adminRenderLogTableClose() ?>
                    <?php if ($runtimeTotalPages > 1): ?>
                        <?= adminRenderPagination($runtimeTotalPages, $runtimePage, static fn (int $targetPage): string => $runtimePageBase . $targetPage, [
                            'wrapper_class' => 'health-log-pagination',
                            'aria_label' => 'PHP çalışma zamanı log sayfalama',
                        ]) ?>
                    <?php endif; ?>
                <?= adminRenderLogListPanelClose('article') ?>

                <?php
                    $applicationLogCountText = number_format((int) ($applicationErrorFeed['total'] ?? 0), 0, ',', '.') . ' toplam';
                    if ((int) ($applicationErrorFeed['total'] ?? 0) > count($applicationErrorFeed['items'] ?? [])) {
                        $applicationLogCountText .= ' / ' . number_format(count($applicationErrorFeed['items'] ?? []), 0, ',', '.') . ' gösterim';
                    }
                ?>
                <?= adminRenderLogListPanelOpen([
                    'tag' => 'article',
                    'class' => 'health-log-block',
                    'header_class' => 'health-log-block-head',
                    'icon' => 'bi-journal-code',
                    'title' => 'Uygulama Hata Kayıtları',
                    'actions_html' => '<span class="ui-admin-badge ui-admin-badge-muted">' . $applicationLogCountText . '</span>',
                ]) ?>
                    <?= adminRenderLogTableOpen([
                        'wrapper_class' => 'health-log-table-wrap ui-table-wrap ui-surface admin-log-table-wrap',
                        'table_class' => 'health-table health-log-table admin-log-table',
                        'table_attrs' => ['aria-label' => 'Uygulama hata kayıtları'],
                    ]) ?>
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
                                    <?= adminRenderTableEmptyRow(5, [
                                        'icon' => 'bi-journal-x',
                                        'tone' => 'info',
                                        'title' => 'Uygulama hata kaydı yok.',
                                        'description' => 'Seçili filtreyle eşleşen uygulama hata kaydı bulunamadı.',
                                    ]) ?>
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
                    <?= adminRenderLogTableClose() ?>
                    <?php if ($appTotalPages > 1): ?>
                        <?= adminRenderPagination($appTotalPages, $appPage, static fn (int $targetPage): string => $appPageBase . $targetPage, [
                            'wrapper_class' => 'health-log-pagination',
                            'aria_label' => 'Uygulama log sayfalama',
                        ]) ?>
                    <?php endif; ?>
                <?= adminRenderLogListPanelClose('article') ?>
            </div>
        </div>
    <?= adminRenderPanelShellClose() ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
