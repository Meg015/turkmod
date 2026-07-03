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
    int $perPage = 50,
    string $filterSubject = '',
    string $dateFrom = '',
    string $dateTo = ''
): array
{
    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(a.action LIKE :search_action OR u.name LIKE :search_user OR a.subject_type LIKE :search_subject)";
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
    $stmt = $pdo->prepare("SELECT a.*, u.name AS actor_name,
                                  t.title AS topic_title,
                                  su.name AS subject_user_name,
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
    $files = glob(rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.log') ?: [];
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

            $lowerLine = strtolower($line);
            if (!str_contains($lowerLine, 'error') && !str_contains($lowerLine, 'critical')) {
                continue;
            }

            $timestamp = null;
            $message = $line;
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $timestamp = strtotime((string) ($decoded['ts'] ?? '')) ?: null;
                $message = (string) ($decoded['msg'] ?? $line);
            } elseif (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $matches) === 1) {
                $timestamp = strtotime($matches[1]) ?: null;
            }

            $timestamp ??= $fileMtime;
            if ($timestamp !== null && $timestamp >= $cutoff) {
                $summary['error_count_24h']++;
            }

            if ($timestamp !== null && ($summary['latest_at'] === null || $timestamp > (int) $summary['latest_at'])) {
                $summary['latest_at'] = $timestamp;
                $summary['latest_message'] = mb_substr($message, 0, 220, 'UTF-8');
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
        'activity_logs_cleared' => 'Aktivite logları temizlendi',
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
