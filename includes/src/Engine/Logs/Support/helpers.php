<?php
/**
 * Logs Module — Aktivite logları iş mantığı
 */

declare(strict_types=1);

function logsGetList(
    PDO $pdo,
    string $search = '',
    string $filterAction = '',
    int $page = 1,
    int $perPage = 10,
    string $filterSubject = '',
    string $dateFrom = '',
    string $dateTo = ''
): array
{
    $page = max(1, $page);
    $perPage = max(1, min(10, $perPage));

    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(a.action LIKE :search_action OR u.username LIKE :search_user OR a.subject_type LIKE :search_subject)";
        $searchTerm = '%' . $search . '%';
        $params['search_action'] = $searchTerm;
        $params['search_user'] = $searchTerm;
        $params['search_subject'] = $searchTerm;
    }
    if ($filterAction !== '') {
        $where[] = "a.action = :action";
        $params['action'] = $filterAction;
    }
    if ($filterSubject !== '') {
        $where[] = "a.subject_type = :subject_type";
        $params['subject_type'] = $filterSubject;
    }
    if ($dateFrom !== '') {
        $where[] = "a.created_at >= :date_from";
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = "a.created_at < :date_to";
        $params['date_to'] = date('Y-m-d H:i:s', strtotime($dateTo . ' +1 day'));
    }

    $whereStr = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON a.actor_id = u.id WHERE {$whereStr}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch
    $stmt = $pdo->prepare("SELECT a.*, u.username AS actor_name,
                                  t.title AS topic_title,
                                  su.username AS subject_user_name,
                                  c.name AS subject_category_name
                           FROM activity_logs a
                           LEFT JOIN users u ON a.actor_id = u.id
                           LEFT JOIN topics t ON a.subject_type = 'topic' AND a.subject_id = t.id
                           LEFT JOIN users su ON a.subject_type = 'user' AND a.subject_id = su.id
                           LEFT JOIN categories c ON a.subject_type = 'category' AND a.subject_id = c.id
                           WHERE {$whereStr}
                           ORDER BY a.created_at DESC
                           LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
}

function logsGetActionTypes(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function logsGetSubjectTypes(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT DISTINCT subject_type FROM activity_logs WHERE subject_type IS NOT NULL AND subject_type <> '' ORDER BY subject_type");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function logsGetStats(PDO $pdo): array
{
    $stats = [
        'total' => (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
        'today' => (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'week' => (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    ];
    if (function_exists('adminQualityObservabilitySummary')) {
        $stats += adminQualityObservabilitySummary($pdo);
    } else {
        $stats += ['error_events' => 0, 'critical_admin_actions' => 0];
    }
    return $stats;
}

function logsRuntimeLogFiles(string $logDir): array
{
    if (!is_dir($logDir)) {
        return [];
    }

    $files = [];
    $base = rtrim($logDir, DIRECTORY_SEPARATOR);
    foreach (['critical-*.log', 'error-*.log', 'app-*.log', 'api_*.log'] as $pattern) {
        foreach (glob($base . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
            $files[$file] = $file;
        }
    }

    return array_values($files);
}

function logsGetRuntimeLogSummary(string $logDir): array
{
    $summary = [
        'latest_at' => null,
        'latest_message' => '',
        'error_count_24h' => 0,
        'latest_file' => '',
    ];

    if (!is_dir($logDir)) {
        return $summary;
    }

    $cutoff = time() - 86400;
    $files = logsRuntimeLogFiles($logDir);
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }

        $fileMtime = filemtime($file) ?: null;
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('~^(?:stack trace:|#\d+\s|[-]{3,})~i', $line) === 1) {
                continue;
            }

            $lowerLine = strtolower($line);
            $isCritical = str_contains($lowerLine, 'critical')
                || str_contains($lowerLine, 'fatal')
                || str_contains($lowerLine, 'exception')
                || str_contains($lowerLine, 'uncaught')
                || str_contains($lowerLine, 'undefined function');
            $isError = $isCritical
                || str_contains($lowerLine, 'error')
                || str_contains($lowerLine, 'sqlstate')
                || str_contains($lowerLine, 'activity logging failed');
            if (!$isError) {
                continue;
            }

            $timestamp = null;
            $message = $line;
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $timestamp = strtotime((string) ($decoded['ts'] ?? '')) ?: null;
                $message = trim((string) ($decoded['msg'] ?? $line));
            } elseif (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $matches) === 1) {
                $timestamp = strtotime($matches[1]) ?: null;
            }

            $timestamp ??= $fileMtime;
            if ($timestamp === null) {
                continue;
            }

            if ($timestamp >= $cutoff) {
                $summary['error_count_24h']++;
            }
            if ($summary['latest_at'] === null || $timestamp > (int) $summary['latest_at']) {
                $summary['latest_at'] = $timestamp;
                $summary['latest_message'] = mb_substr($message !== '' ? $message : $line, 0, 220, 'UTF-8');
                $summary['latest_file'] = basename($file);
            }
        }

        fclose($handle);
    }

    return $summary;
}

function logsFormatAction(string $action): string
{
    if ($action === 'topic_viewed') {
        return 'Konu görüntülendi';
    }
    $map = [
        'user_login' => 'Giriş yapıldı',
        'user_registered' => 'Hesap oluşturuldu',
        'password_reset_requested' => 'Şifre sıfırlama istendi',
        'password_changed' => 'Şifre değiştirildi',
        'profile_updated' => 'Profil güncellendi',
        'avatar_updated' => 'Profil fotoğrafı güncellendi',
        'topic_created' => 'Konu oluşturuldu',
        'topic_updated' => 'Konu güncellendi',
        'topic_deleted' => 'Konu çöp kutusuna taşındı',
        'topic_deleted_permanently' => 'Konu kalıcı olarak silindi',
        'topic_restored' => 'Konu geri yüklendi',
        'comment_created' => 'Yorum yapıldı',
        'settings_updated' => 'Ayarlar güncellendi',
        'category_created' => 'Kategori oluşturuldu',
        'category_updated' => 'Kategori güncellendi',
        'category_deleted' => 'Kategori silindi',
        'media_uploaded' => 'Medya yüklendi',
        'media_deleted' => 'Medya silindi',
        'rate_limit_records_deleted' => 'Rate limit kayıtları temizlendi',
        'cron_logs_cleared' => 'Cron logları temizlendi',
        'system_notifications_deleted' => 'Sistem bildirimleri silindi',
        'system_notifications_cleared' => 'Sistem bildirimleri temizlendi',
        'application_logs_cleared' => 'Uygulama logları temizlendi',
        'email_logs_cleared' => 'E-posta logları temizlendi',
        'activity_logs_cleared' => 'Aktivite logları temizlendi',
        'admin_action_log_cleared' => 'Yönetici işlem kayıtları temizlendi',
        'leaderboard_recalculated' => 'Liderlik hesaplandı',
        'leaderboard_cache_cleared' => 'Liderlik önbelleği temizlendi',
        'admin_action_reverted' => 'Admin işlemi geri alındı',
        'bot_import_published' => 'Bot içeriği yayımlandı',
    ];
    return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
}

function logsFormatSubject(?string $subjectType, $subjectId = null, ?string $subjectTitle = null): string
{
    if (!$subjectType) {
        return 'Genel sistem olayı';
    }

    $map = [
        'topic' => 'Konu',
        'comment' => 'Yorum',
        'user' => 'Kullanıcı',
        'category' => 'Kategori',
        'settings' => 'Ayar',
    ];

    $label = $map[$subjectType] ?? ucwords(str_replace('_', ' ', (string) $subjectType));

    if ($subjectTitle) {
        return $label . ': ' . $subjectTitle;
    }

    return $subjectId ? $label . ' #' . (int) $subjectId : $label;
}

function logsFormatProperties(?string $propertiesJson): string
{
    if (!$propertiesJson) {
        return 'Ek detay yok';
    }

    $properties = json_decode($propertiesJson, true);
    if (!is_array($properties) || empty($properties)) {
        return 'Ek detay yok';
    }

    unset($properties['subject_title']);

    $labelMap = [
        'topic_slug' => 'Konu bağlantısı',
        'category_slug' => 'Kategori bağlantısı',
        'subject_slug' => 'Konu bağlantısı',
    ];
    $parts = [];
    foreach ($properties as $key => $value) {
        if (is_array($value) || is_object($value) || $value === '' || $value === null) {
            continue;
        }
        $parts[] = ($labelMap[$key] ?? $key) . ': ' . $value;
    }

    return $parts ? implode(' • ', $parts) : 'Ek detay yok';
}

function logsClearAll(PDO $pdo): int
{
    $count = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    $pdo->exec("TRUNCATE TABLE activity_logs");
    return $count;
}

function logsActionBadgeClass(string $action): string
{
    if ($action === 'topic_viewed') return 'admin-badge-info';
    if (str_contains($action, 'delete')) return 'admin-badge-danger';
    if (str_contains($action, 'login') || str_contains($action, 'register')) return 'admin-badge-primary';
    if (str_contains($action, 'create')) return 'admin-badge-success';
    if (str_contains($action, 'update') || str_contains($action, 'restore')) return 'admin-badge-warning';
    return 'admin-badge-secondary';
}

function logsClearOld(PDO $pdo, int $daysToKeep = 90): int
{
    $cutoff = (new DateTimeImmutable())->modify('-' . max(1, $daysToKeep) . ' days')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at IS NOT NULL AND created_at < :cutoff");
    $stmt->execute(['cutoff' => $cutoff]);
    return $stmt->rowCount();
}

/**
 * SQL fragment that identifies application log rows owned by the Cron Logs view.
 */
function appLogsCronWhereClause(string $prefix = ''): string
{
    $channelCol = $prefix . 'channel';
    $messageCol = $prefix . 'message';
    $contextCol = $prefix . 'context_json';
    $channelSql = "LOWER(COALESCE({$channelCol}, ''))";
    $messageSql = "LOWER(COALESCE({$messageCol}, ''))";
    $contextSql = "LOWER(COALESCE(CAST({$contextCol} AS CHAR), ''))";

    return "({$channelSql} = 'cron'"
        . " OR {$messageSql} LIKE 'cron_run:%'"
        . " OR {$contextSql} LIKE '%\"action\":\"cron_cleanup\"%'"
        . " OR {$contextSql} LIKE '%\"source\":\"cron/%'"
        . " OR {$contextSql} LIKE '%\"source\":\"cron_%')";
}

/**
 * @return array{where:string,params:array<string,string>}
 */
function appLogsBuildWhere(
    string $search = '',
    string $level = '',
    string $channel = '',
    string $dateFrom = '',
    string $dateTo = '',
    string $prefix = 'a.',
    array $excludedChannels = []
): array {
    $where = ['1=1'];
    $params = [];
    $excludedChannels = array_values(array_unique(array_filter(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        $excludedChannels
    ), static fn (string $value): bool => $value !== '')));

    $messageCol = $prefix . 'message';
    $channelCol = $prefix . 'channel';
    $levelCol = $prefix . 'level';
    $ipCol = $prefix . 'ip_address';
    $contextCol = $prefix . 'context_json';
    $createdCol = $prefix . 'created_at';

    if ($search !== '') {
        $where[] = "({$messageCol} LIKE :search OR {$channelCol} LIKE :search OR {$levelCol} LIKE :search OR {$ipCol} LIKE :search OR CAST({$contextCol} AS CHAR) LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    if ($level !== '') {
        $where[] = "{$levelCol} = :level";
        $params['level'] = $level;
    }
    if ($channel !== '') {
        $where[] = "{$channelCol} = :channel";
        $params['channel'] = $channel;
    }
    if ($excludedChannels !== []) {
        $placeholders = [];
        foreach ($excludedChannels as $index => $excludedChannel) {
            $paramKey = 'excluded_channel_' . $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $excludedChannel;
        }
        $where[] = "({$channelCol} IS NULL OR LOWER({$channelCol}) NOT IN (" . implode(',', $placeholders) . '))';
        if (in_array('cron', $excludedChannels, true)) {
            $where[] = 'NOT ' . appLogsCronWhereClause($prefix);
        }
    }
    if ($dateFrom !== '') {
        $where[] = "{$createdCol} >= :date_from";
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = "{$createdCol} < :date_to";
        $params['date_to'] = date('Y-m-d H:i:s', strtotime($dateTo . ' +1 day'));
    }

    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function appLogsGetList(
    PDO $pdo,
    string $search = '',
    string $level = '',
    string $channel = '',
    int $page = 1,
    int $perPage = 10,
    string $dateFrom = '',
    string $dateTo = '',
    array $excludedChannels = []
): array {
    $page = max(1, $page);
    $perPage = max(1, min(10, $perPage));

    $filter = appLogsBuildWhere($search, $level, $channel, $dateFrom, $dateTo, 'a.', $excludedChannels);
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM application_logs a WHERE {$filter['where']}");
    $countStmt->execute($filter['params']);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT a.* FROM application_logs a WHERE {$filter['where']} ORDER BY a.created_at DESC, a.id DESC LIMIT :limit OFFSET :offset");
    foreach ($filter['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
    ];
}

function appLogsGetStats(PDO $pdo, array $excludedChannels = []): array
{
    $filter = appLogsBuildWhere('', '', '', '', '', '', $excludedChannels);
    $where = $filter['where'];
    $params = $filter['params'];
    $scalar = static function (string $sql) use ($pdo, $params): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };

    return [
        'total' => $scalar("SELECT COUNT(*) FROM application_logs WHERE {$where}"),
        'total_24h' => $scalar("SELECT COUNT(*) FROM application_logs WHERE {$where} AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
        'total_7d' => $scalar("SELECT COUNT(*) FROM application_logs WHERE {$where} AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'errors_24h' => $scalar("SELECT COUNT(*) FROM application_logs WHERE {$where} AND level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
        'errors_7d' => $scalar("SELECT COUNT(*) FROM application_logs WHERE {$where} AND level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'channels' => $scalar("SELECT COUNT(DISTINCT channel) FROM application_logs WHERE {$where}"),
    ];
}

function appLogsGetLevels(PDO $pdo, array $excludedChannels = []): array
{
    try {
        $filter = appLogsBuildWhere('', '', '', '', '', '', $excludedChannels);
        $stmt = $pdo->prepare("SELECT DISTINCT level FROM application_logs WHERE {$filter['where']} AND level IS NOT NULL AND level <> '' ORDER BY level");
        $stmt->execute($filter['params']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function appLogsGetChannels(PDO $pdo, array $excludedChannels = []): array
{
    try {
        $filter = appLogsBuildWhere('', '', '', '', '', '', $excludedChannels);
        $stmt = $pdo->prepare("SELECT DISTINCT channel FROM application_logs WHERE {$filter['where']} AND channel IS NOT NULL AND channel <> '' ORDER BY channel");
        $stmt->execute($filter['params']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function appLogsClearAll(PDO $pdo, array $excludedChannels = []): int
{
    $filter = appLogsBuildWhere('', '', '', '', '', '', $excludedChannels);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM application_logs WHERE {$filter['where']}");
    $countStmt->execute($filter['params']);
    $count = (int) $countStmt->fetchColumn();
    $deleteStmt = $pdo->prepare("DELETE FROM application_logs WHERE {$filter['where']}");
    $deleteStmt->execute($filter['params']);
    return $count;
}

function appLogsClearOld(PDO $pdo, int $daysToKeep = 90, array $excludedChannels = []): int
{
    $cutoff = (new DateTimeImmutable())->modify('-' . max(1, $daysToKeep) . ' days')->format('Y-m-d H:i:s');
    $filter = appLogsBuildWhere('', '', '', '', '', '', $excludedChannels);
    $stmt = $pdo->prepare("DELETE FROM application_logs WHERE {$filter['where']} AND created_at IS NOT NULL AND created_at < :cutoff");
    $stmt->execute(array_merge($filter['params'], ['cutoff' => $cutoff]));
    return $stmt->rowCount();
}

function appLogsClearFiltered(
    PDO $pdo,
    string $search = '',
    string $level = '',
    string $channel = '',
    string $dateFrom = '',
    string $dateTo = '',
    array $excludedChannels = []
): int {
    $filter = appLogsBuildWhere($search, $level, $channel, $dateFrom, $dateTo, '', $excludedChannels);
    $sql = "DELETE FROM application_logs WHERE {$filter['where']}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter['params']);
    return $stmt->rowCount();
}

function appLogsClearCron(PDO $pdo): int
{
    $where = appLogsCronWhereClause('');
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM application_logs WHERE {$where}");
    $countStmt->execute();
    $count = (int) $countStmt->fetchColumn();

    $deleteStmt = $pdo->prepare("DELETE FROM application_logs WHERE {$where}");
    $deleteStmt->execute();

    return $count;
}

function appLogsFormatContext(?string $contextJson): string
{
    $raw = trim((string) $contextJson);
    if ($raw === '') {
        return 'Ek detay yok';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || $decoded === []) {
        return strlen($raw) > 300 ? substr($raw, 0, 297) . '...' : $raw;
    }

    $parts = [];
    foreach ($decoded as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if (is_scalar($value)) {
            $parts[] = (string) $key . ': ' . (string) $value;
            continue;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && $encoded !== '') {
            $parts[] = (string) $key . ': ' . $encoded;
        }
    }

    return $parts !== [] ? implode(' | ', $parts) : 'Ek detay yok';
}

if (!function_exists('appLogsDecodeContext')) {
    function appLogsDecodeContext(?string $contextJson): array
    {
        $raw = trim((string) $contextJson);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('appLogsPrettyLabel')) {
    function appLogsPrettyLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return ucwords(strtolower($value));
    }
}

if (!function_exists('appLogsLevelLabel')) {
    function appLogsLevelLabel(string $level): string
    {
        $level = strtolower(trim($level));

        return match ($level) {
            'emergency' => 'Acil',
            'alert' => 'Alarm',
            'critical' => 'Kritik',
            'error' => 'Hata',
            'warning', 'warn' => 'Uyarı',
            'notice' => 'Bildirim',
            'info' => 'Bilgi',
            'debug' => 'Hata Ayıklama',
            default => appLogsPrettyLabel($level),
        };
    }
}

if (!function_exists('appLogsChannelLabel')) {
    function appLogsChannelLabel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        static $map = [
            'activity' => 'Aktivite',
            'auth' => 'Giriş',
            'cron' => 'Cron',
            'email' => 'E-posta',
            'maintenance' => 'Bakım',
            'routing' => 'Rota',
            'security' => 'Güvenlik',
            'settings' => 'Ayarlar',
            'system' => 'Sistem',
            'template' => 'Şablon',
            'theme' => 'Tema',
            'notification' => 'Bildirim',
            'notifications' => 'Bildirimler',
        ];

        return $channel !== '' ? ($map[$channel] ?? appLogsPrettyLabel($channel)) : '';
    }
}

if (!function_exists('appLogsContextLabel')) {
    function appLogsContextLabel(string $key): string
    {
        static $map = [
            'actor_id' => 'İşlemi yapan',
            'actor_user_id' => 'İşlemi yapan',
            'user_id' => 'Kullanıcı',
            'target_user_id' => 'Hedef kullanıcı',
            'subject_type' => 'Hedef tür',
            'subject_id' => 'Hedef',
            'method' => 'Yöntem',
            'path' => 'Yol',
            'status_code' => 'HTTP',
            'duration_ms' => 'Süre',
            'route_group' => 'Rota grubu',
            'job_key' => 'Job',
            'status' => 'Durum',
            'scope' => 'Kapsam',
            'deleted' => 'Silinen',
            'deleted_rows' => 'Silinen satır',
            'expired_count' => 'Süresi dolan',
            'processed' => 'İşlenen',
            'sent' => 'Gönderilen',
            'failed' => 'Başarısız',
            'total_operations' => 'Toplam işlem',
            'template_key' => 'Şablon',
            'theme_id' => 'Tema',
            'asset' => 'Varlık',
            'sapi' => 'Çalıştırma',
            'reason' => 'Sebep',
            'channel' => 'Kanal',
            'count' => 'Adet',
            'days' => 'Gün',
            'exception' => 'Hata',
            'cycle' => 'Döngü',
            'title' => 'Başlık',
            'name' => 'Ad',
            'display_name' => 'Ad',
            'username' => 'Kullanıcı adı',
            'subject_title' => 'Başlık',
        ];

        return $map[$key] ?? appLogsPrettyLabel($key);
    }
}

if (!function_exists('appLogsSubjectTypeLabel')) {
    function appLogsSubjectTypeLabel(string $subjectType): string
    {
        static $map = [
            'topic' => 'Konu',
            'comment' => 'Yorum',
            'user' => 'Kullanıcı',
            'category' => 'Kategori',
            'settings' => 'Ayar',
            'media' => 'Medya',
            'leaderboard' => 'Liderlik',
            'rate_limit' => 'Rate limit',
            'logs' => 'Günlük',
        ];

        $subjectType = trim($subjectType);
        if ($subjectType === '') {
            return '';
        }

        return $map[$subjectType] ?? appLogsPrettyLabel($subjectType);
    }
}

if (!function_exists('appLogsValueText')) {
    function appLogsValueText($value, bool $short = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            $text = $value ? 'Evet' : 'Hayır';
        } elseif (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text = is_string($encoded) ? $encoded : '';
        } else {
            $text = trim((string) $value);
        }

        if ($text === '') {
            return '';
        }

        if ($short && function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 120) {
            $text = function_exists('mb_substr')
                ? mb_substr($text, 0, 117, 'UTF-8') . '...'
                : substr($text, 0, 117) . '...';
        }

        return $text;
    }
}

if (!function_exists('appLogsContextUserIds')) {
    function appLogsContextUserIds(array $context): array
    {
        $ids = [];

        foreach (['actor_id', 'actor_user_id', 'user_id', 'target_user_id', 'subject_user_id'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];
            if (is_numeric($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        $subjectType = strtolower(trim((string) ($context['subject_type'] ?? '')));
        if ($subjectType === 'user' && is_numeric($context['subject_id'] ?? null) && (int) $context['subject_id'] > 0) {
            $ids[(int) $context['subject_id']] = (int) $context['subject_id'];
        }

        return array_values($ids);
    }
}

if (!function_exists('appLogsResolveUserLabels')) {
    function appLogsResolveUserLabels(PDO $pdo, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id IN ({$placeholders})");
            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();

            $labels = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $label = trim((string) ($row['username'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($row['email'] ?? ''));
                }
                if ($label === '') {
                    $label = '#' . (int) ($row['id'] ?? 0);
                }
                $labels[(int) ($row['id'] ?? 0)] = $label;
            }

            return $labels;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('appLogsUserLabel')) {
    function appLogsUserLabel(int $userId, array $userLabels = []): string
    {
        if ($userId <= 0) {
            return '';
        }

        $label = trim((string) ($userLabels[$userId] ?? ''));
        if ($label !== '') {
            return $label . ' (#' . $userId . ')';
        }

        return '#' . $userId;
    }
}

if (!function_exists('appLogsContextParts')) {
    function appLogsContextParts(array $context, array $preferredKeys = [], int $limit = 3): array
    {
        $parts = [];
        $seen = [];

        foreach ($preferredKeys as $key) {
            $key = (string) $key;
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $valueText = appLogsValueText($context[$key], true);
            if ($valueText === '') {
                continue;
            }

            $parts[] = appLogsContextLabel($key) . ': ' . $valueText;
            $seen[$key] = true;
            if (count($parts) >= $limit) {
                return $parts;
            }
        }

        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (isset($seen[$key])) {
                continue;
            }

            $valueText = appLogsValueText($value, true);
            if ($valueText === '') {
                continue;
            }

            $parts[] = appLogsContextLabel($key) . ': ' . $valueText;
            if (count($parts) >= $limit) {
                break;
            }
        }

        return $parts;
    }
}

if (!function_exists('appLogsHumanizeMessage')) {
    function appLogsHumanizeMessage(string $message, string $channel = '', array $context = []): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Kayıt';
        }

        static $map = [
            'user_login' => 'Kullanıcı girişi',
            'user_logout' => 'Kullanıcı çıkışı',
            'user_registered' => 'Hesap oluşturuldu',
            'warning' => 'Uyarı',
            'warn' => 'Uyarı',
            'notice' => 'Bildirim',
            'info' => 'Bilgi',
            'debug' => 'Hata ayıklama',
            'password_reset_requested' => 'Şifre sıfırlama istendi',
            'password_changed' => 'Şifre değiştirildi',
            'profile_updated' => 'Profil güncellendi',
            'avatar_updated' => 'Profil fotoğrafı güncellendi',
            'topic_created' => 'Konu oluşturuldu',
            'topic_updated' => 'Konu güncellendi',
            'topic_deleted' => 'Konu silindi',
            'topic_deleted_permanently' => 'Konu kalıcı olarak silindi',
            'topic_restored' => 'Konu geri yüklendi',
            'topic_viewed' => 'Konu görüntülendi',
            'comment_created' => 'Yorum yapıldı',
            'settings_updated' => 'Ayarlar güncellendi',
            'category_created' => 'Kategori oluşturuldu',
            'category_updated' => 'Kategori güncellendi',
            'category_deleted' => 'Kategori silindi',
            'media_uploaded' => 'Medya yüklendi',
            'media_deleted' => 'Medya silindi',
            'rate_limit_cleanup' => 'Rate limit temizliği',
            'cron_logs_cleared' => 'Cron logları temizlendi',
            'system_notifications_deleted' => 'Sistem bildirimleri silindi',
            'system_notifications_cleared' => 'Sistem bildirimleri temizlendi',
            'application_logs_cleared' => 'Uygulama logları temizlendi',
            'email_logs_cleared' => 'E-posta logları temizlendi',
            'activity_logs_cleared' => 'Aktivite logları temizlendi',
            'leaderboard_recalculated' => 'Liderlik tablosu yeniden hesaplandı',
            'leaderboard_cache_cleared' => 'Liderlik önbelleği temizlendi',
            'admin_action_reverted' => 'Admin işlemi geri alındı',
            'user_activity_events_cleared' => 'Kullanıcı hareket kayıtları temizlendi',
            'admin_action_log_cleared' => 'Yönetici işlem kayıtları temizlendi',
            'bot_import_published' => 'Bot içeriği yayımlandı',
            'route_dispatch' => 'Rota işlendi',
            'route_dispatch_failed' => 'Rota yönlendirme hatası',
            'activity_log_failed' => 'Aktivite kaydı yazılamadı',
        ];

        if (isset($map[$message])) {
            return $map[$message];
        }

        if (str_starts_with($message, 'cron_run:')) {
            return 'Cron çalıştı';
        }
        if (str_starts_with($message, 'TPL Missing Variable:')) {
            return 'Şablon değişkeni eksik';
        }
        if (str_starts_with($message, 'TPL include cycle:')) {
            return 'Şablon döngüsü tespit edildi';
        }
        if (str_starts_with($message, 'Compiled template error:')) {
            return 'Şablon derleme hatası';
        }
        if (str_starts_with($message, 'Theme asset not found:')) {
            return 'Tema varlığı bulunamadı';
        }

        return appLogsPrettyLabel($message);
    }
}

if (!function_exists('appLogsContextSummary')) {
    function appLogsContextSummary(array $context, string $channel = '', string $message = '', string $level = '', array $userLabels = []): string
    {
        $channel = strtolower(trim($channel));
        $message = trim($message);
        $level = strtolower(trim($level));

        if ($channel === 'routing') {
            $parts = [];
            $method = strtoupper(trim((string) ($context['method'] ?? '')));
            $path = trim((string) ($context['path'] ?? ''));
            if ($method !== '' || $path !== '') {
                $parts[] = trim($method . ' ' . $path);
            }
            $statusCode = appLogsValueText($context['status_code'] ?? null, true);
            if ($statusCode !== '') {
                $parts[] = 'HTTP ' . $statusCode;
            }
            $duration = appLogsValueText($context['duration_ms'] ?? null, true);
            if ($duration !== '') {
                $parts[] = $duration . ' ms';
            }
            $routeGroup = trim((string) ($context['route_group'] ?? ''));
            if ($routeGroup !== '') {
                $parts[] = 'Grup: ' . $routeGroup;
            }
            $exception = trim((string) ($context['exception'] ?? ''));
            if ($exception !== '') {
                $parts[] = 'Hata: ' . appLogsValueText($exception, true);
            }

            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        if ($channel === 'cron' || str_starts_with($message, 'cron_run:')) {
            $parts = [];
            $jobKey = trim((string) ($context['job_key'] ?? ''));
            if ($jobKey === '' && str_starts_with($message, 'cron_run:')) {
                $jobKey = trim(substr($message, 9));
            }
            if ($jobKey !== '') {
                $parts[] = 'Job: ' . $jobKey;
            }

            $status = strtolower(trim((string) ($context['status'] ?? '')));
            if ($status === '') {
                $status = $level;
            }
            if ($status !== '') {
                $statusLabel = [
                    'success' => 'Başarılı',
                    'warning' => 'Uyarı',
                    'error' => 'Hata',
                    'skipped' => 'Atlandı',
                ][$status] ?? appLogsPrettyLabel($status);
                $parts[] = 'Durum: ' . $statusLabel;
            }

            foreach (['processed', 'sent', 'failed', 'deleted', 'deleted_rows', 'expired_count', 'total_operations'] as $key) {
                $valueText = appLogsValueText($context[$key] ?? null, true);
                if ($valueText === '') {
                    continue;
                }
                $parts[] = appLogsContextLabel($key) . ': ' . $valueText;
                if (count($parts) >= 4) {
                    break;
                }
            }

            $sapi = trim((string) ($context['sapi'] ?? ''));
            if ($sapi !== '' && count($parts) < 4) {
                $parts[] = 'Çalıştırma: ' . $sapi;
            }

            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        if ($channel === 'activity') {
            $parts = [];
            $actorId = is_numeric($context['actor_id'] ?? null) ? (int) $context['actor_id'] : 0;
            $subjectType = strtolower(trim((string) ($context['subject_type'] ?? '')));
            $subjectId = is_numeric($context['subject_id'] ?? null) ? (int) $context['subject_id'] : 0;
            $properties = is_array($context['properties'] ?? null) ? $context['properties'] : [];

            if ($actorId > 0) {
                $actorLabel = appLogsUserLabel($actorId, $userLabels);
                if ($subjectType === 'user' && $subjectId > 0 && $subjectId === $actorId) {
                    $parts[] = 'Kullanıcı: ' . $actorLabel;
                } else {
                    $parts[] = 'İşlemi yapan: ' . $actorLabel;
                }
            }

            if ($subjectType !== '') {
                $subjectLabel = appLogsSubjectTypeLabel($subjectType);
                $subjectName = '';
                foreach (['subject_title', 'title', 'name', 'display_name', 'username'] as $key) {
                    $subjectName = appLogsValueText($properties[$key] ?? ($context[$key] ?? null), true);
                    if ($subjectName !== '') {
                        break;
                    }
                }

                if ($subjectType === 'user' && $subjectId > 0) {
                    $subjectName = appLogsUserLabel($subjectId, $userLabels);
                    if (!($actorId > 0 && $actorId === $subjectId)) {
                        $parts[] = 'Hedef kullanıcı: ' . $subjectName;
                    }
                } elseif ($subjectName !== '') {
                    $parts[] = $subjectLabel . ': ' . $subjectName;
                } elseif ($subjectId > 0) {
                    $parts[] = $subjectLabel . ' #' . $subjectId;
                }
            }

            if ($properties !== []) {
                $propertyParts = appLogsContextParts($properties, ['scope', 'deleted', 'count', 'reason', 'status', 'days'], 2);
                $parts = array_merge($parts, $propertyParts);
            }

            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        if ($channel === 'maintenance') {
            $contextForSummary = $context;
            $targetUserId = is_numeric($context['target_user_id'] ?? null) ? (int) $context['target_user_id'] : 0;
            if ($targetUserId > 0) {
                $contextForSummary['target_user_id'] = appLogsUserLabel($targetUserId, $userLabels);
            }

            $parts = appLogsContextParts($contextForSummary, ['target_user_id', 'scope', 'deleted', 'days', 'reason'], 4);
            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        if ($channel === 'template') {
            $parts = [];
            $templateKey = trim((string) ($context['template_key'] ?? ''));
            if ($templateKey !== '') {
                $parts[] = 'Şablon: ' . $templateKey;
            }
            $variable = '';
            foreach (['key', 'variable', 'missing_key', 'name'] as $key) {
                $variable = appLogsValueText($context[$key] ?? null, true);
                if ($variable !== '') {
                    break;
                }
            }
            if ($message !== '' && str_starts_with($message, 'TPL Missing Variable:')) {
                $variable = trim(substr($message, strlen('TPL Missing Variable:')));
            }
            if ($variable !== '') {
                $parts[] = 'Değişken: ' . $variable;
            }
            $cycle = trim((string) ($context['cycle'] ?? ''));
            if ($cycle !== '') {
                $parts[] = 'Döngü: ' . $cycle;
            }
            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        if ($channel === 'theme') {
            $parts = appLogsContextParts($context, ['theme_id', 'asset', 'path', 'reason'], 3);
            return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
        }

        $parts = appLogsContextParts($context, ['actor_id', 'subject_type', 'subject_id', 'job_key', 'status', 'scope', 'reason'], 3);
        return $parts !== [] ? implode(' · ', $parts) : 'Ek detay yok';
    }
}

if (!function_exists('appLogsFormatContextTechnical')) {
    function appLogsFormatContextTechnical(?string $contextJson, string $channel = '', string $message = ''): string
    {
        $lines = [];
        $channel = trim($channel);
        $message = trim($message);

        if ($channel !== '') {
            $lines[] = 'Kanal: ' . $channel;
        }
        if ($message !== '') {
            $lines[] = 'Mesaj: ' . $message;
        }

        $raw = trim((string) $contextJson);
        if ($raw === '') {
            return implode("\n", $lines);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            $lines[] = 'Ham context: ' . $raw;
            return implode("\n", $lines);
        }

        foreach ($decoded as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $lines[] = (string) $key . ': ' . (string) $value;
                continue;
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded) && $encoded !== '') {
                $lines[] = (string) $key . ': ' . $encoded;
            }
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('appLogsDecorateItems')) {
    function appLogsDecorateItems(PDO $pdo, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $contexts = [];
        $userIds = [];

        foreach ($items as $index => $item) {
            $context = appLogsDecodeContext($item['context_json'] ?? null);
            $contexts[$index] = $context;
            foreach (appLogsContextUserIds($context) as $userId) {
                $userIds[$userId] = $userId;
            }
        }

        $userLabels = $userIds !== [] ? appLogsResolveUserLabels($pdo, array_values($userIds)) : [];

        foreach ($items as $index => &$item) {
            $context = $contexts[$index] ?? [];
            $channel = (string) ($item['channel'] ?? '');
            $message = (string) ($item['message'] ?? '');
            $level = (string) ($item['level'] ?? '');

            $item['context_data'] = $context;
            $item['level_label'] = appLogsLevelLabel($level);
            $item['channel_label'] = appLogsChannelLabel($channel);
            $item['human_message'] = appLogsHumanizeMessage($message, $channel, $context);
            $item['context_summary'] = appLogsContextSummary($context, $channel, $message, $level, $userLabels);
            $item['context_technical'] = appLogsFormatContextTechnical($item['context_json'] ?? null, $channel, $message);
        }
        unset($item);

        return $items;
    }
}

