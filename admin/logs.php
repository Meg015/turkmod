<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/AdminAudit/Support/helpers.php';

adminRequirePermission('logs.view', 'Günlükleri görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if (function_exists('ensureAdminActionLogTable')) {
    ensureAdminActionLogTable($pdo);
}

$view = strtolower(trim((string) ($_GET['view'] ?? 'activity')));
if (!in_array($view, ['activity', 'cron'], true)) {
    $view = 'activity';
}

$pageTitle = $view === 'cron' ? 'Cron Logları' : 'Yönetici İşlem Günlüğü';
$canManageLogs = function_exists('adminCurrentUserCan') && adminCurrentUserCan('logs.manage');
$csrfToken = function_exists('csrf_token') ? csrf_token() : '';

$activityDefaultActionTypes = [
    'group_change',
    'status_change',
    'ban',
    'unban',
    'restrict',
    'delete',
    'group_save',
    'group_deactivate',
    'settings_updated',
    'admin_user_updated',
    'topic_settings_updated',
    'topic_created',
    'topic_updated',
    'topic_deleted',
    'topic_deleted_permanently',
    'topic_restored',
    'topic_approved',
    'topic_user_edited',
    'topic_bulk_publish',
    'topic_bulk_unpublish',
    'topic_bulk_draft',
    'topic_bulk_delete',
    'topic_bulk_purge',
    'topic_bulk_restore',
    'topic_revision_restored',
    'topic_moderated',
    'topic_health_scan_completed',
    'topic_health_cleared',
    'download_link_checked',
    'category_created',
    'category_updated',
    'category_deleted',
    'media_uploaded',
    'media_deleted',
    'leaderboard_recalculated',
    'leaderboard_cache_cleared',
    'leaderboard_settings_updated',
    'cron_manual_triggered',
    'cron_logs_cleared',
    'bot_import_published',
    'activity_logs_cleared',
    'application_logs_cleared',
    'email_logs_cleared',
    'admin_action_log_cleared',
    'rate_limit_records_deleted',
    'notification_records_deleted',
    'events_audit_logs_cleared',
];

$activityDefaultTargetTypes = [
    'user',
    'topic',
    'category',
    'settings',
    'media',
    'leaderboard',
    'user_group',
    'system',
    'logs',
];

$activityActionTypes = $activityDefaultActionTypes;
$activityTargetTypes = $activityDefaultTargetTypes;

if ($pdo) {
    try {
        $dbActionTypes = $pdo->query("SELECT DISTINCT action_type FROM admin_action_log ORDER BY action_type ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $activityActionTypes = array_values(array_unique(array_merge($activityActionTypes, $dbActionTypes)));
        sort($activityActionTypes, SORT_NATURAL | SORT_FLAG_CASE);
    } catch (Throwable $e) {
        $activityActionTypes = $activityDefaultActionTypes;
    }

    try {
        $dbTargetTypes = $pdo->query("SELECT DISTINCT target_type FROM admin_action_log ORDER BY target_type ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $activityTargetTypes = array_values(array_unique(array_merge($activityTargetTypes, $dbTargetTypes)));
        sort($activityTargetTypes, SORT_NATURAL | SORT_FLAG_CASE);
    } catch (Throwable $e) {
        $activityTargetTypes = $activityDefaultTargetTypes;
    }
}

$filterAction = trim((string) ($_GET['action_type'] ?? ''));
$filterTargetType = trim((string) ($_GET['target_type'] ?? ''));
$filterTarget = max(0, (int) ($_GET['target_id'] ?? 0));
$filterActor = max(0, (int) ($_GET['actor_id'] ?? 0));
$filterState = trim((string) ($_GET['state'] ?? ''));
if (!in_array($filterState, ['', 'active', 'reverted', 'reversible'], true)) {
    $filterState = '';
}
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) === 1 ? (string) $_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) === 1 ? (string) $_GET['date_to'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();

$activityFilters = array_filter([
    'action_type' => $filterAction,
    'target_type' => $filterTargetType,
    'target_id' => $filterTarget > 0 ? $filterTarget : null,
    'actor_id' => $filterActor > 0 ? $filterActor : null,
    'state' => $filterState,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
], static fn ($value): bool => $value !== '' && $value !== null);

$activityLogs = ['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
$activityTotalPages = 1;
$activityCriticalCount = 0;
$cronSearch = trim((string) ($_GET['cron_q'] ?? ''));
$cronStatus = strtolower(trim((string) ($_GET['cron_status'] ?? 'all')));
if (!in_array($cronStatus, ['all', 'success', 'warning', 'error', 'skipped'], true)) {
    $cronStatus = 'all';
}
$cronJob = trim((string) ($_GET['cron_job'] ?? ''));
$cronPage = max(1, (int) ($_GET['cron_page'] ?? 1));
$cronPerPage = adminPaginationPerPage();
$cronLogs = ['items' => [], 'total' => 0, 'page' => $cronPage, 'perPage' => $cronPerPage];
$cronStats = ['total' => 0, 'success' => 0, 'warning' => 0, 'error' => 0, 'job_count' => 0];
$cronJobs = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $redirectParams = array_filter($_GET, static fn ($value): bool => $value !== '' && $value !== null);

    $respond = static function (bool $ok, string $message) use ($isAjax, $redirectParams, $view): void {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        flash($ok ? 'success' : 'error', $message);
        $location = 'logs.php';
        if ($redirectParams !== []) {
            $location .= '?' . http_build_query($redirectParams);
        } elseif ($view === 'cron') {
            $location .= '?view=cron';
        }
        header('Location: ' . $location);
        exit;
    };

    if ($view === 'activity' && $postAction === 'revert') {
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            $respond(false, 'Güvenlik doğrulaması başarısız.');
        }
        if (!$canManageLogs) {
            $respond(false, 'Bu işlemi yapmak için logs.manage izni gereklidir.');
        }

        $err = adminAuditLogger()->revertAction(
            $pdo,
            (int) ($_POST['log_id'] ?? 0),
            (int) ($_SESSION['_auth_user_id'] ?? 0)
        );
        $respond($err === '', $err === '' ? 'İşlem geri alındı.' : $err);
    }

    if ($view === 'activity' && $postAction === 'clear_action_logs') {
        $scope = trim((string) ($_POST['scope'] ?? ''));
        $redirectUrl = 'logs.php';
        if ($redirectParams !== []) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        } elseif ($view === 'cron') {
            $redirectUrl .= '?view=cron';
        }

        adminRunLogCleanup($pdo, [
            'action_type' => 'admin_action_log_cleared',
            'scope' => $scope,
            'allowed_scopes' => ['older_than_30_days', 'all'],
            'permission' => 'logs.manage',
            'permission_message' => 'Bu işlemi yapmak için logs.manage izni gereklidir.',
            'redirect_url' => $redirectUrl,
            'source' => 'admin_logs',
            'delete' => static fn (PDO $pdo, string $scope): int => adminClearActionLog($pdo, $scope),
            'app_log' => true,
            'app_log_message' => 'admin_action_log_cleared',
            'success_message' => static fn (int $deleted): string => $deleted . ' adet yönetici işlem kaydı temizlendi.',
        ]);
    }

    if ($view === 'cron' && $postAction === 'clear_cron_all') {
        $redirectUrl = 'logs.php';
        if ($redirectParams !== []) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        } elseif ($view === 'cron') {
            $redirectUrl .= '?view=cron';
        }

        adminRunLogCleanup($pdo, [
            'action_type' => 'cron_logs_cleared',
            'scope' => 'all',
            'allowed_scopes' => ['all'],
            'permission' => 'logs.manage',
            'permission_message' => 'Bu işlemi yapmak için logs.manage izni gereklidir.',
            'redirect_url' => $redirectUrl,
            'source' => 'cron_logs',
            'delete' => static fn (PDO $pdo): int => function_exists('appLogsClearFiltered') ? appLogsClearFiltered($pdo, '', '', 'cron') : 0,
            'context' => [
                'channel' => 'cron',
            ],
            'success_message' => static fn (int $deleted): string => 'Cron logları temizlendi. Silinen kayıt: ' . $deleted . '.',
        ]);
    }
}

$activityLogList = [];
$activityTotal = 0;
$activityCriticalActions = [
    'ban',
    'delete',
    'restrict',
    'group_change',
    'status_change',
    'settings_updated',
    'topic_settings_updated',
    'topic_deleted',
    'topic_deleted_permanently',
    'topic_bulk_delete',
    'topic_bulk_purge',
    'category_deleted',
    'media_deleted',
    'leaderboard_settings_updated',
    'admin_action_log_cleared',
    'application_logs_cleared',
    'email_logs_cleared',
    'rate_limit_records_deleted',
    'notification_records_deleted',
    'events_audit_logs_cleared',
    'cron_logs_cleared',
];

if ($pdo && $view === 'activity') {
    try {
        $activityLogList = adminGetActionLog($pdo, $activityFilters, $perPage, ($page - 1) * $perPage);
        $activityTotal = adminCountActionLog($pdo, $activityFilters);
        $activityTotalPages = max(1, (int) ceil(max(0, $activityTotal) / max(1, $perPage)));
        if ($page > $activityTotalPages) {
            $page = $activityTotalPages;
            $activityLogList = adminGetActionLog($pdo, $activityFilters, $perPage, ($page - 1) * $perPage);
        }
        $activityCriticalCount = count(array_filter($activityLogList, static fn (array $log): bool => in_array((string) ($log['action_type'] ?? ''), $activityCriticalActions, true)));
    } catch (Throwable $e) {
        flash('error', 'Yönetici işlem günlükleri yüklenemedi: ' . safeErrorMessage($e));
    }
}

if ($pdo && $view === 'cron') {
    try {
        $jobStmt = $pdo->query("SELECT DISTINCT message FROM application_logs WHERE channel = 'cron' ORDER BY message ASC");
        $jobRows = $jobStmt ? ($jobStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        $jobMap = [];
        foreach ($jobRows as $jobMessageRaw) {
            $jobMessage = trim((string) $jobMessageRaw);
            if ($jobMessage === '') {
                continue;
            }
            $jobKey = str_starts_with($jobMessage, 'cron_run:') ? substr($jobMessage, 9) : $jobMessage;
            $jobKey = trim((string) $jobKey);
            if ($jobKey === '') {
                continue;
            }
            $jobMap[$jobKey] = $jobKey;
        }
        $cronJobs = array_values($jobMap);
        sort($cronJobs);

        $cronStatsStmt = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN level = 'warning' THEN 1 ELSE 0 END) AS warning_count,
                SUM(CASE WHEN context_json LIKE '%\"status\":\"success\"%' THEN 1 ELSE 0 END) AS success_count
            FROM application_logs
            WHERE channel = 'cron'
        ");
        $cronStatsRow = $cronStatsStmt ? ($cronStatsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $cronStats['total'] = (int) ($cronStatsRow['total'] ?? 0);
        $cronStats['error'] = (int) ($cronStatsRow['error_count'] ?? 0);
        $cronStats['warning'] = (int) ($cronStatsRow['warning_count'] ?? 0);
        $cronStats['success'] = (int) ($cronStatsRow['success_count'] ?? 0);
        $cronStats['job_count'] = count($cronJobs);

        $where = ["channel = 'cron'"];
        $params = [];

        if ($cronSearch !== '') {
            $where[] = '(message LIKE :cron_search OR context_json LIKE :cron_search OR ip_address LIKE :cron_search)';
            $params['cron_search'] = '%' . $cronSearch . '%';
        }

        if ($cronJob !== '') {
            $where[] = '(message = :cron_job_message OR context_json LIKE :cron_job_context)';
            $params['cron_job_message'] = 'cron_run:' . $cronJob;
            $params['cron_job_context'] = '%"job_key":"' . $cronJob . '"%';
        }

        if ($cronStatus === 'success') {
            $where[] = '(context_json LIKE \'%"status":"success"%\')';
        } elseif ($cronStatus === 'warning') {
            $where[] = '(level = \'warning\' OR context_json LIKE \'%"status":"warning"%\')';
        } elseif ($cronStatus === 'error') {
            $where[] = '(level = \'error\' OR context_json LIKE \'%"status":"error"%\')';
        } elseif ($cronStatus === 'skipped') {
            $where[] = '(context_json LIKE \'%"status":"skipped"%\')';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM application_logs WHERE {$whereSql}");
        foreach ($params as $paramKey => $paramValue) {
            $countStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $cronLogs['total'] = (int) $countStmt->fetchColumn();

        $cronTotalPages = (int) ceil(max(0, $cronLogs['total']) / max(1, $cronPerPage));
        if ($cronTotalPages > 0 && $cronPage > $cronTotalPages) {
            $cronPage = $cronTotalPages;
        }
        $cronLogs['page'] = $cronPage;

        $offset = ($cronPage - 1) * $cronPerPage;
        $listStmt = $pdo->prepare("
            SELECT id, level, message, context_json, ip_address, created_at
            FROM application_logs
            WHERE {$whereSql}
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $paramKey => $paramValue) {
            $listStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
        }
        $listStmt->bindValue(':limit', $cronPerPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $listStmt->execute();
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== [] && function_exists('appLogsDecorateItems')) {
            $rows = appLogsDecorateItems($pdo, $rows);
        }

        foreach ($rows as $row) {
            $context = is_array($row['context_data'] ?? null) ? $row['context_data'] : [];

            $jobKey = trim((string) ($context['job_key'] ?? ''));
            if ($jobKey === '') {
                $message = (string) ($row['message'] ?? '');
                $jobKey = str_starts_with($message, 'cron_run:') ? substr($message, 9) : $message;
            }

            $status = strtolower(trim((string) ($context['status'] ?? '')));
            if ($status === '') {
                $status = match ((string) ($row['level'] ?? '')) {
                    'error' => 'error',
                    'warning' => 'warning',
                    default => 'success',
                };
            }

            $row['job_key'] = $jobKey;
            $row['status'] = $status;
            $cronLogs['items'][] = $row;
        }
    } catch (Throwable $e) {
        flash('error', 'Cron logları yüklenemedi: ' . safeErrorMessage($e));
    }
}

$adminAuditTargetLabel = static function (string $targetType, int $targetId, ?string $targetName = null): string {
    $map = [
        'user' => 'Kullanıcı',
        'topic' => 'Konu',
        'category' => 'Kategori',
        'settings' => 'Ayarlar',
        'media' => 'Medya',
        'leaderboard' => 'Liderlik',
        'user_group' => 'Kullanıcı Grubu',
        'system' => 'Sistem',
        'logs' => 'Günlükler',
    ];

    $label = $map[$targetType] ?? ($targetType !== '' ? ucwords(str_replace('_', ' ', $targetType)) : 'Hedef');
    if ($targetType === 'user' && $targetName !== null && $targetName !== '') {
        return $label . ': ' . $targetName;
    }
    if ($targetId > 0) {
        return $label . ' #' . $targetId;
    }

    return $label;
};

$adminAuditScalar = static function ($value): string {
    if (is_bool($value)) {
        return $value ? 'Evet' : 'Hayır';
    }
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
    }
    if (is_object($value)) {
        return '[Nesne]';
    }
    if ($value === null || $value === '') {
        return '—';
    }

    return (string) $value;
};

$adminAuditChanges = static function (array $old, array $new) use ($adminAuditScalar): string {
    $keyMap = [
        'status' => 'Durum',
        'group_id' => 'Grup ID',
        'group_ids' => 'Grup IDleri',
        'username' => 'Kullanıcı Adı',
        'email' => 'E-posta',
        'avatar' => 'Avatar',
        'bio' => 'Biyografi',
        'website' => 'Website',
        'location' => 'Konum',
        'social_github' => 'GitHub',
        'social_twitter' => 'Twitter',
        'social_discord' => 'Discord',
        'public_profile' => 'Profil Açık',
        'public_show_topics' => 'Konular',
        'public_show_comments' => 'Yorumlar',
        'public_show_socials' => 'Sosyal Bağlantılar',
        'password_changed' => 'Şifre Değişti',
        'is_banned' => 'Yasaklı',
        'ban_reason' => 'Yasaklanma Gerekçesi',
        'type' => 'Kısıtlama Türü',
        'days' => 'Süre (Gün)',
        'section' => 'Bölüm',
        'scope' => 'Kapsam',
        'deleted' => 'Silinen',
        'count' => 'Adet',
        'keys' => 'Alanlar',
        'channel' => 'Kanal',
        'target_user_id' => 'Kullanıcı ID',
    ];

    $parts = [];
    foreach ($new as $key => $newValue) {
        $oldValue = $old[$key] ?? null;
        $label = $keyMap[$key] ?? ucwords(str_replace('_', ' ', (string) $key));
        $parts[] = '<div class="ui-admin-change-line"><strong class="ui-admin-text-muted">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> <span class="ui-admin-text-danger">' . htmlspecialchars($adminAuditScalar($oldValue), ENT_QUOTES, 'UTF-8') . '</span> <i class="bi bi-arrow-right ui-admin-text-muted"></i> <span class="ui-admin-text-success">' . htmlspecialchars($adminAuditScalar($newValue), ENT_QUOTES, 'UTF-8') . '</span></div>';
    }

    return $parts !== [] ? implode('', $parts) : '<span class="ui-admin-muted">—</span>';
};

$adminAuditCleanupActions = [
    'admin_action_log_cleared',
    'activity_logs_cleared',
    'application_logs_cleared',
    'email_logs_cleared',
    'cron_logs_cleared',
    'rate_limit_records_deleted',
    'notification_records_deleted',
    'events_audit_logs_cleared',
];

$adminAuditCleanupSummary = static function (string $actionType, array $payload) use ($adminAuditScalar, $adminAuditCleanupActions): string {
    if (!in_array($actionType, $adminAuditCleanupActions, true) || $payload === []) {
        return '';
    }

    $sourceLabels = [
        'admin_logs' => 'Yönetici günlüğü',
        'action_log' => 'Kullanıcı işlem günlüğü',
        'users_activity_tab' => 'Kullanıcılar sekmesi',
        'application_logs' => 'Uygulama logları',
        'email_logs' => 'E-posta logları',
        'cron_logs' => 'Cron logları',
        'rate_limits' => 'Rate limit',
    ];
    $scopeLabels = [
        'all' => 'Tümü',
        'older_than_30_days' => '30 günden eski kayıtlar',
        'old' => 'Eski kayıtlar',
        'filtered' => 'Aktif filtre',
        'user' => 'Belirli kullanıcı',
        'login' => 'Giriş kilitleri',
        'expired' => 'Süresi dolmuş kayıtlar',
        'single' => 'Tek kayıt',
        'selected' => 'Seçili kayıtlar',
    ];
    $chips = [];
    $addChip = static function (string $label, $value, string $tone = '') use (&$chips, $adminAuditScalar): void {
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            return;
        }
        $toneClass = $tone !== '' ? ' is-' . preg_replace('/[^a-z0-9_-]/i', '', $tone) : '';
        $chips[] = '<span class="admin-audit-cleanup-chip' . $toneClass . '"><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>' . htmlspecialchars($adminAuditScalar($value), ENT_QUOTES, 'UTF-8') . '</span>';
    };

    $scope = (string) ($payload['scope'] ?? '');
    $addChip('Kapsam', $payload['scope_label'] ?? ($scopeLabels[$scope] ?? $scope), 'scope');
    $addChip('Silinen', $payload['deleted'] ?? null, 'deleted');
    $addChip('Kaynak', $sourceLabels[(string) ($payload['source'] ?? '')] ?? ($payload['source'] ?? ''), 'source');
    $addChip('Kullanıcı', $payload['target_user_id'] ?? null);
    $addChip('Gün', $payload['days'] ?? null);
    $addChip('Kanal', $payload['channel'] ?? null);

    $filterSummary = '';
    $filters = is_array($payload['filters'] ?? null) ? array_filter($payload['filters'], static fn ($value): bool => $value !== '' && $value !== null) : [];
    if ($filters !== []) {
        $filterParts = [];
        foreach ($filters as $key => $value) {
            $filterParts[] = '<span><strong>' . htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key)), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($adminAuditScalar($value), ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $filterSummary = '<div class="admin-audit-cleanup-filters">' . implode('', $filterParts) . '</div>';
    }

    if ($chips === [] && $filterSummary === '') {
        return '';
    }

    return '<div class="admin-audit-cleanup-summary" aria-label="Temizlik özeti">' . implode('', $chips) . $filterSummary . '</div>';
};

$cronStatusLabel = static function (string $status): string {
    return match ($status) {
        'success' => 'Başarılı',
        'warning' => 'Uyarı',
        'error' => 'Hata',
        'skipped' => 'Atlandı',
        default => strtoupper($status),
    };
};

$cronStatusBadgeClass = static function (string $status): string {
    return match ($status) {
        'success' => 'bg-success',
        'warning' => 'bg-warning text-dark',
        'error' => 'bg-danger',
        'skipped' => 'bg-secondary',
        default => 'bg-secondary',
    };
};

$cronContextSummary = static function (array $context): string {
    $parts = [];
    foreach (['reason', 'selected', 'processed', 'sent', 'failed', 'deleted_rows', 'expired_count', 'total_operations'] as $key) {
        if (array_key_exists($key, $context) && is_scalar($context[$key])) {
            $parts[] = $key . '=' . (string) $context[$key];
        }
        if (count($parts) >= 4) {
            break;
        }
    }

    return $parts === [] ? '-' : implode(' | ', $parts);
};

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$activityActionQuery = http_build_query(array_filter([
    'action_type' => $filterAction,
    'target_type' => $filterTargetType,
    'target_id' => $filterTarget > 0 ? $filterTarget : '',
    'actor_id' => $filterActor > 0 ? $filterActor : '',
    'state' => $filterState,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
], static fn ($value): bool => $value !== '' && $value !== null));
$activityPostAction = 'logs.php' . ($activityActionQuery !== '' ? '?' . $activityActionQuery : '');

require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs($view === 'cron' ? 'cron' : 'activity'); ?>

<div class="logs-page">
<?php if ($view === 'activity'): ?>
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-shield-check"></i> Denetim merkezi</span>
            <h2>Yönetici İşlem Günlüğü</h2>
            <p>Grup, durum, kısıtlama, silme ve ayar değişikliklerini izleyin; geri alınabilir işlemleri kontrollü şekilde iptal edin.</p>
        </div>
    </section>

    <?php if ($successMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-error ui-alert ui-alert--error"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="admin-stat-grid logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam kayıt</span><span class="stat-value"><?= number_format((int) $activityTotal, 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-warning logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-content"><span class="stat-label">Geri alınabilir</span><span class="stat-value"><?= number_format((int) count(array_filter($activityLogList, static fn (array $log): bool => (int) ($log['is_reversible'] ?? 0) === 1 && empty($log['reverted_at']))), 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
            <div class="stat-content"><span class="stat-label">Geri alınmış</span><span class="stat-value"><?= number_format((int) count(array_filter($activityLogList, static fn (array $log): bool => !empty($log['reverted_at']))), 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-danger logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
            <div class="stat-content"><span class="stat-label">Kritik eylem</span><span class="stat-value"><?= number_format((int) $activityCriticalCount, 0, ',', '.') ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card logs-toolbar-shell">
            <div class="logs-toolbar-row logs-toolbar-row--activity">
            <form method="get" action="logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form">
                <input type="hidden" name="view" value="activity">
                <select name="action_type" class="ui-admin-form-select">
                    <option value="">Tüm eylemler</option>
                    <?php foreach ($activityActionTypes as $actionType): ?>
                        <option value="<?= htmlspecialchars((string) $actionType, ENT_QUOTES, 'UTF-8') ?>" <?= $filterAction === (string) $actionType ? 'selected' : '' ?>>
                            <?= htmlspecialchars(adminActionLabel((string) $actionType), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="target_type" class="ui-admin-form-select">
                    <option value="">Tüm hedefler</option>
                    <?php foreach ($activityTargetTypes as $targetType): ?>
                        <option value="<?= htmlspecialchars((string) $targetType, ENT_QUOTES, 'UTF-8') ?>" <?= $filterTargetType === (string) $targetType ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ([
                                'user' => 'Kullanıcı',
                                'topic' => 'Konu',
                                'category' => 'Kategori',
                                'settings' => 'Ayarlar',
                                'media' => 'Medya',
                                'leaderboard' => 'Liderlik',
                                'user_group' => 'Kullanıcı Grubu',
                                'system' => 'Sistem',
                                'logs' => 'Günlükler',
                            ][$targetType] ?? ucwords(str_replace('_', ' ', (string) $targetType))), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="target_id" class="ui-admin-form-control" min="1" placeholder="Hedef ID" value="<?= $filterTarget > 0 ? (int) $filterTarget : '' ?>">
                <input type="number" name="actor_id" class="ui-admin-form-control" min="1" placeholder="Admin ID" value="<?= $filterActor > 0 ? (int) $filterActor : '' ?>">
                <select name="state" class="ui-admin-form-select">
                    <option value="">Tüm durumlar</option>
                    <option value="active" <?= $filterState === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="reversible" <?= $filterState === 'reversible' ? 'selected' : '' ?>>Geri alınabilir</option>
                    <option value="reverted" <?= $filterState === 'reverted' ? 'selected' : '' ?>>Geri alınmış</option>
                </select>
                <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                <div class="logs-filter-actions">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                    <?php if ($activityActionQuery !== ''): ?>
                        <a href="logs.php" class="logs-filter-reset"><i class="bi bi-x-circle"></i> Filtreleri Sıfırla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <section class="admin-card user-activity-feed-card logs-list-card ui-panel ui-card">
        <div class="card-header user-activity-card-head ui-admin-card-header-actions ui-panel__head ui-card logs-list-head">
            <div>
                <h3><i class="bi bi-journal-check"></i> Yönetici İşlem Akışı</h3>
                <span><?= number_format((int) $activityTotal, 0, ',', '.') ?> kayıt</span>
            </div>
            <div class="logs-toolbar-actions">
                <?php if ($canManageLogs): ?>
                    <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger-outline" data-clear-logs-open>
                        <i class="bi bi-trash"></i> Günlüğü Temizle
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($activityLogList)): ?>
                <div class="ui-admin-empty ui-admin-empty-pro ui-admin-empty-audit ui-empty admin-log-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-check"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">İşlem kaydı bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty"><?= $activityActionQuery !== '' ? 'Seçili filtrelerle eşleşen denetim kaydı yok.' : 'Henüz kritik veya geri alınabilir admin işlemi kaydedilmedi.' ?></p>
                    <div class="ui-admin-empty-meta" aria-label="Günlük durumu">
                        <span><i class="bi bi-funnel"></i> <?= $activityActionQuery !== '' ? 'Filtre aktif' : 'Filtre yok' ?></span>
                        <span><i class="bi bi-shield-lock"></i> Kritik kayıt yok</span>
                    </div>
                    <?php if ($activityActionQuery !== ''): ?>
                        <div class="ui-admin-empty-actions ui-empty">
                            <a href="logs.php" class="logs-filter-reset"><i class="bi bi-x-circle"></i> Filtreleri Sıfırla</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ui-admin-table-responsive ui-table-wrap ui-surface admin-log-table-wrap">
                    <table class="ui-admin-table ui-admin-table-striped admin-log-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Yönetici</th>
                                <th>Eylem</th>
                                <th>Hedef</th>
                                <th>Ayrıntı</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLogList as $log): ?>
                                <?php
                                $old = json_decode((string) ($log['old_value'] ?? ''), true);
                                if (!is_array($old)) {
                                    $old = [];
                                }
                                $new = json_decode((string) ($log['new_value'] ?? ''), true);
                                if (!is_array($new)) {
                                    $new = [];
                                }
                                $isReverted = !empty($log['reverted_at']);
                                $canRevert = $canManageLogs && (int) ($log['is_reversible'] ?? 0) === 1 && !$isReverted;
                                $actionType = (string) ($log['action_type'] ?? '');
                                $actionLabel = htmlspecialchars(adminActionLabel($actionType), ENT_QUOTES, 'UTF-8');
                                $targetType = (string) ($log['target_type'] ?? '');
                                $targetName = (string) ($log['target_name'] ?? '');
                                $reason = trim((string) ($log['reason'] ?? ''));
                                $hasChangeDetails = $old !== [] || $new !== [];
                                $isCleanupAudit = in_array($actionType, $adminAuditCleanupActions, true);
                                $cleanupSummaryHtml = $isCleanupAudit ? $adminAuditCleanupSummary($actionType, $new) : '';
                                $tone = 'info';
                                if ($isReverted) {
                                    $tone = 'muted';
                                } elseif ($actionType === 'ban' || str_contains($actionType, 'delete')) {
                                    $tone = 'danger';
                                } elseif ($actionType === 'restrict' || $actionType === 'status_change' || str_contains($actionType, 'settings') || str_contains($actionType, 'update')) {
                                    $tone = 'warning';
                                } elseif ($actionType === 'unban' || $actionType === 'group_change') {
                                    $tone = 'success';
                                }
                                $changeHtml = $adminAuditChanges($old, $new);
                                ?>

                                <tr<?= $isReverted ? ' class="ui-admin-row-muted"' : '' ?>>
                                    <td class="ui-admin-muted ui-admin-nowrap"><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <i class="bi bi-person-badge ui-admin-text-muted"></i>
                                        <?= htmlspecialchars((string) ($log['actor_name'] ?? ('#' . (int) ($log['actor_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>"><?= $actionLabel ?></span></td>
                                    <td>
                                        <?php if ($targetType === 'user' && (int) ($log['target_id'] ?? 0) > 0): ?>
                                            <a href="users.php?edit=<?= (int) $log['target_id'] ?>">
                                                <?= htmlspecialchars($adminAuditTargetLabel($targetType, (int) $log['target_id'], $targetName !== '' ? $targetName : null), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($adminAuditTargetLabel($targetType, (int) ($log['target_id'] ?? 0), $targetName !== '' ? $targetName : null), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                        <div class="ui-admin-log-summary"><?= htmlspecialchars($reason !== '' ? $reason : 'Gerekçe yok', ENT_QUOTES, 'UTF-8') ?></div>
                                        <?= $cleanupSummaryHtml ?>
                                        <?php if ($hasChangeDetails): ?>
                                            <details class="ui-admin-log-technical ui-admin-log-technical--activity">
                                                <summary><i class="bi bi-code-slash"></i> <?= $isCleanupAudit ? 'Audit ayrıntıları' : 'Değişim ayrıntıları' ?></summary>
                                                <div class="ui-admin-log-technical-body ui-admin-log-technical-body-html"><?= $changeHtml ?></div>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isReverted): ?>
                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-arrow-counterclockwise"></i> Geri alındı</span>
                                        <?php else: ?>
                                            <span class="ui-admin-badge ui-admin-badge-success">Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canRevert): ?>
                                            <form method="post" action="<?= htmlspecialchars($activityPostAction, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form" data-admin-confirm="Bu işlemi geri almak istediğinize emin misiniz?" data-admin-confirm-tone="warning">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="revert">
                                                <input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
                                                <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-warning">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Geri Al
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="ui-admin-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($activityTotalPages > 1): ?>
                    <?php
                    $activityPageParams = $activityFilters;
                    $pageBase = 'logs.php?' . ($activityPageParams ? http_build_query($activityPageParams) . '&' : '') . 'page=';
                    echo adminRenderPagination($activityTotalPages, $page, static fn (int $targetPage): string => $pageBase . $targetPage, [
                        'wrapper_class' => 'logs-pagination-wrapper',
                        'aria_label' => 'Yönetici işlem günlüğü sayfalama',
                    ]);
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canManageLogs): ?>
        <?php
        $logClearModal = [
            'aria_label' => 'Yönetici günlüğünü temizle',
            'title' => 'Günlüğü Temizle',
            'form_action' => $activityPostAction,
            'hidden_fields' => [
                ['name' => 'action', 'value' => 'clear_action_logs'],
            ],
            'scope_name' => 'scope',
            'options' => [
                [
                    'value' => 'older_than_30_days',
                    'label' => '30 Günden Eski Kayıtları Sil',
                    'confirm_title' => 'Kayıtları Temizle',
                ],
                [
                    'value' => 'all',
                    'label' => 'Tüm Günlüğü Sil (Tehlikeli)',
                    'confirm_title' => 'Günlüğü Temizle',
                ],
            ],
            'warning' => 'Bu işlem denetim kayıtlarını silecektir. Güvenlik incelemeleri için son logların tutulması önerilir. Sadece gerekli olduğunda temizlik yapın. İşlem geri alınamaz.',
        ];
        include __DIR__ . '/partials/log-clear-modal.php';
        unset($logClearModal);
        ?>
    <?php endif; ?>

<?php else: ?>
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-cpu"></i> Zamanlanmış işler</span>
            <h2>Cron Logları</h2>
            <p>Cron çalışmalarını, durumları ve çıktılarını tek listede izleyin.</p>
        </div>
    </section>

    <div class="admin-stat-grid logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-card-list"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam Cron Log</span><span class="stat-value"><?= number_format((int) $cronStats['total'], 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-content"><span class="stat-label">Başarılı</span><span class="stat-value"><?= number_format((int) $cronStats['success'], 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-warning logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-content"><span class="stat-label">Uyarı</span><span class="stat-value"><?= number_format((int) $cronStats['warning'], 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-danger logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-bug"></i></div>
            <div class="stat-content"><span class="stat-label">Hata</span><span class="stat-value"><?= number_format((int) $cronStats['error'], 0, ',', '.') ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-cpu"></i></div>
            <div class="stat-content"><span class="stat-label">Farklı Job</span><span class="stat-value"><?= number_format((int) $cronStats['job_count'], 0, ',', '.') ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card logs-toolbar-shell">
            <form method="get" action="logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form">
                <input type="hidden" name="view" value="cron">
                <input type="text" name="cron_q" class="ui-admin-form-control" placeholder="Mesaj, job key veya IP ara..." value="<?= htmlspecialchars($cronSearch, ENT_QUOTES, 'UTF-8') ?>">
                <select name="cron_status" class="ui-admin-form-select">
                    <option value="all" <?= $cronStatus === 'all' ? 'selected' : '' ?>>Tüm Durumlar</option>
                    <option value="success" <?= $cronStatus === 'success' ? 'selected' : '' ?>>Başarılı</option>
                    <option value="warning" <?= $cronStatus === 'warning' ? 'selected' : '' ?>>Uyarı</option>
                    <option value="error" <?= $cronStatus === 'error' ? 'selected' : '' ?>>Hata</option>
                    <option value="skipped" <?= $cronStatus === 'skipped' ? 'selected' : '' ?>>Atlandı</option>
                </select>
                <select name="cron_job" class="ui-admin-form-select">
                    <option value="">Tüm Joblar</option>
                    <?php foreach ($cronJobs as $cronJobOption): ?>
                        <option value="<?= htmlspecialchars((string) $cronJobOption, ENT_QUOTES, 'UTF-8') ?>" <?= $cronJob === (string) $cronJobOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $cronJobOption, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($cronSearch !== '' || $cronStatus !== 'all' || $cronJob !== ''): ?>
                    <a href="logs.php?view=cron" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
                <?php endif; ?>
            </form>
            <div class="logs-toolbar-actions">
                <?php if ($canManageLogs): ?>
                    <button type="button" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs" data-clear-logs-open>
                        <i class="bi bi-trash"></i> Günlüğü Temizle
                    </button>
                <?php endif; ?>
                <a href="settings.php#cron" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs"><i class="bi bi-gear"></i> Cron Ayarları</a>
            </div>
        </div>
    </div>

    <div class="admin-card logs-list-card ui-panel">
        <div class="card-header user-activity-card-head ui-admin-card-header-actions ui-panel__head ui-card logs-list-head">
            <div>
                <h3><i class="bi bi-card-list"></i> Cron Logları</h3>
                <span><?= number_format((int) $cronLogs['total'], 0, ',', '.') ?> kayıt</span>
            </div>
        </div>
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($cronLogs['items'])): ?>
                <div class="ui-admin-empty ui-empty admin-log-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-card-list"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Cron log kaydı bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty">Filtreye uyan cron kaydı yok. Cron çalıştığında kayıtlar burada listelenir.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper ui-table-wrap ui-surface admin-log-table-wrap">
                    <table class="admin-table admin-log-table">
                        <thead>
                            <tr>
                                <th class="ui-admin-table-head-narrow">#</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>Job</th>
                                <th>Mesaj</th>
                                <th>Detay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cronLogs['items'] as $cronLog): ?>
                                <?php
                                $cronContext = is_array($cronLog['context_data'] ?? null) ? $cronLog['context_data'] : [];
                                $cronStatusValue = strtolower((string) ($cronLog['status'] ?? 'success'));
                                $cronLevelClass = $cronStatusBadgeClass($cronStatusValue);
                                $cronHumanMessage = trim((string) ($cronLog['human_message'] ?? (string) ($cronLog['message'] ?? '')));
                                $cronSummary = trim((string) ($cronLog['context_summary'] ?? ''));
                                $cronTechnical = trim((string) ($cronLog['context_technical'] ?? ''));
                                $cronJobLabel = function_exists('appLogsPrettyLabel')
                                    ? appLogsPrettyLabel((string) ($cronLog['job_key'] ?? '-'))
                                    : (string) ($cronLog['job_key'] ?? '-');
                                ?>
                                <tr>
                                    <td class="ui-admin-table-cell-id"><?= (int) ($cronLog['id'] ?? 0) ?></td>
                                    <td class="ui-admin-table-cell-date"><?= date('d.m.Y H:i:s', strtotime((string) ($cronLog['created_at'] ?? 'now'))) ?></td>
                                    <td><span class="badge <?= htmlspecialchars($cronLevelClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cronStatusLabel($cronStatusValue), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="ui-admin-table-cell-strong"><code><?= htmlspecialchars($cronJobLabel !== '' ? $cronJobLabel : '-', ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-message-cell">
                                        <div class="ui-admin-log-message-title"><?= htmlspecialchars($cronHumanMessage !== '' ? $cronHumanMessage : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                        <div class="ui-admin-log-summary"><?= htmlspecialchars($cronSummary !== '' ? $cronSummary : 'Ek detay yok', ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ($cronTechnical !== ''): ?>
                                            <details class="ui-admin-log-technical">
                                                <summary><i class="bi bi-code-slash"></i> Teknik ayrıntı</summary>
                                                <pre class="ui-admin-log-technical-body"><?= htmlspecialchars($cronTechnical, ENT_QUOTES, 'UTF-8') ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $cronTotalPages = (int) ceil(max(0, (int) $cronLogs['total']) / max(1, (int) $cronLogs['perPage']));
                if ($cronTotalPages > 1):
                    $cronQueryBase = array_filter([
                        'view' => 'cron',
                        'cron_q' => $cronSearch,
                        'cron_status' => $cronStatus !== 'all' ? $cronStatus : '',
                        'cron_job' => $cronJob,
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    echo adminRenderPagination($cronTotalPages, $cronPage, static fn (int $targetPage): string => 'logs.php?' . http_build_query(array_merge($cronQueryBase, ['cron_page' => $targetPage])), [
                        'wrapper_class' => 'logs-pagination-wrapper',
                        'aria_label' => 'Cron günlüğü sayfalama',
                    ]);
                endif;
                ?>
            <?php endif; ?>

            <?php if ($canManageLogs): ?>
                <?php
                $logClearModal = [
                    'aria_label' => 'Cron günlüğünü temizle',
                    'title' => 'Günlüğü Temizle',
                    'form_action' => 'logs.php?view=cron',
                    'hidden_fields' => [
                        ['name' => 'action', 'value' => 'clear_cron_all'],
                    ],
            'scope_name' => 'scope',
            'options' => [
                [
                    'value' => 'all',
                    'label' => 'Tüm cron günlüğünü sil (Tehlikeli)',
                    'confirm_title' => 'Günlüğü Temizle',
                ],
            ],
            'warning' => 'Seçilen cron logları kalıcı olarak silinir. Bu işlem geri alınamaz.',
        ];
        include __DIR__ . '/partials/log-clear-modal.php';
        unset($logClearModal);
        ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

