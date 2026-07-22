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
if (!in_array($view, ['activity', 'cron', 'system_notifications'], true)) {
    $view = 'activity';
}

$pageTitle = match ($view) {
    'cron' => 'Cron Logları',
    'system_notifications' => 'Sistem Bildirimleri',
    default => 'Yönetici İşlem Günlüğü',
};
$canManageLogs = function_exists('adminCurrentUserCan') && adminCurrentUserCan('logs.manage');
$csrfToken = function_exists('csrf_token') ? csrf_token() : '';

function adminSystemNotificationsReady(PDO $pdo): bool
{
    return function_exists('adminTableExists') && adminTableExists($pdo, 'notifications');
}

function adminSystemNotificationDeleteRelated(PDO $pdo, array $notificationIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $notificationIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return;
    }

    $relatedTables = [
        'notification_email_queue' => 'notification_id',
        'notification_reads' => 'notification_id',
        'notification_dismissals' => 'notification_id',
    ];

    foreach ($relatedTables as $table => $column) {
        if (!function_exists('adminTableExists') || !adminTableExists($pdo, $table)) {
            continue;
        }

        foreach (array_chunk($ids, 300) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
            foreach ($chunk as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
        }
    }
}

function adminSystemNotificationsClearAll(PDO $pdo): int
{
    if (!adminSystemNotificationsReady($pdo)) {
        return 0;
    }

    $systemNotificationWhere = function_exists('adminSystemNotificationWhereSql')
        ? adminSystemNotificationWhereSql($pdo, '')
        : "type = 'system'";
    $ids = array_map('intval', $pdo->query("SELECT id FROM notifications WHERE {$systemNotificationWhere}")->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($ids === []) {
        return 0;
    }

    adminSystemNotificationDeleteRelated($pdo, $ids);

    $deleted = 0;
    foreach (array_chunk($ids, 300) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE {$systemNotificationWhere} AND id IN ({$placeholders})");
        foreach ($chunk as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $deleted += max(0, (int) $stmt->rowCount());
    }

    return $deleted;
}

function adminSystemNotificationIdsFromRequest(): array
{
    $rawIds = $_POST['notification_ids'] ?? [];
    if (!is_array($rawIds)) {
        $rawIds = [$rawIds];
    }

    if (isset($_POST['notification_id'])) {
        $rawIds[] = $_POST['notification_id'];
    }

    return array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn (int $id): bool => $id > 0)));
}

function adminSystemNotificationsDeleteSelected(PDO $pdo, array $notificationIds): int
{
    if (!adminSystemNotificationsReady($pdo)) {
        return 0;
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $notificationIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }

    $systemNotificationWhere = function_exists('adminSystemNotificationWhereSql')
        ? adminSystemNotificationWhereSql($pdo, 'n')
        : "n.type = 'system'";
    $allowedIds = [];
    foreach (array_chunk($ids, 300) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT n.id FROM notifications n WHERE {$systemNotificationWhere} AND n.id IN ({$placeholders})");
        foreach ($chunk as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $allowedIds[] = (int) $id;
        }
    }

    $allowedIds = array_values(array_unique(array_filter($allowedIds, static fn (int $id): bool => $id > 0)));
    if ($allowedIds === []) {
        return 0;
    }

    adminSystemNotificationDeleteRelated($pdo, $allowedIds);

    $deleted = 0;
    foreach (array_chunk($allowedIds, 300) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ({$placeholders})");
        foreach ($chunk as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $deleted += max(0, (int) $stmt->rowCount());
    }

    return $deleted;
}

function adminSystemNotificationDeliveryChannels($deliveryChannels): array
{
    if ($deliveryChannels === null || $deliveryChannels === '') {
        return [];
    }

    if (is_array($deliveryChannels)) {
        $rawChannels = $deliveryChannels;
    } else {
        $raw = trim((string) $deliveryChannels);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $rawChannels = is_array($decoded) ? $decoded : preg_split('/[,\|]/', $raw);
    }

    $channels = [];
    foreach ((array) $rawChannels as $channel) {
        if (!is_scalar($channel)) {
            continue;
        }

        $key = strtolower(trim((string) $channel));
        if ($key !== '') {
            $channels[$key] = true;
        }
    }

    return array_keys($channels);
}

function adminSystemNotificationHasInAppDelivery(array $notification): bool
{
    if (!array_key_exists('delivery_channels', $notification)) {
        return true;
    }

    $deliveryChannels = $notification['delivery_channels'] ?? null;
    if ($deliveryChannels === null || $deliveryChannels === [] || (!is_array($deliveryChannels) && trim((string) $deliveryChannels) === '')) {
        return true;
    }

    $channels = adminSystemNotificationDeliveryChannels($deliveryChannels);
    if ($channels === []) {
        return true;
    }

    return in_array('in_app', $channels, true);
}

function adminSystemNotificationReadState(array $notification): array
{
    $targetUserId = (int) ($notification['user_id'] ?? 0);
    $readCount = (int) ($notification['read_count'] ?? 0);
    $dismissedCount = (int) ($notification['dismissed_count'] ?? 0);

    if ($dismissedCount > 0) {
        return [
            'label' => $targetUserId > 0 ? 'Hedef gizledi' : 'Gizlenmiş',
            'badge_class' => 'ui-admin-badge-muted',
        ];
    }

    if (!adminSystemNotificationHasInAppDelivery($notification)) {
        return [
            'label' => 'Site içi değil',
            'badge_class' => 'ui-admin-badge-muted',
        ];
    }

    if ($readCount > 0) {
        return [
            'label' => $targetUserId > 0 ? 'Okundu' : number_format($readCount, 0, ',', '.') . ' okuma',
            'badge_class' => 'ui-admin-badge-success',
        ];
    }

    return [
        'label' => $targetUserId > 0 ? 'Hedef okumadı' : 'Henüz okunmadı',
        'badge_class' => 'ui-admin-badge-danger',
    ];
}

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
    'system_notifications_deleted',
    'system_notifications_cleared',
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
$systemNotificationSearch = trim((string) ($_GET['system_q'] ?? ''));
$systemNotificationTarget = strtolower(trim((string) ($_GET['system_target'] ?? 'all')));
if (!in_array($systemNotificationTarget, ['all', 'global', 'direct'], true)) {
    $systemNotificationTarget = 'all';
}
$systemNotificationPage = max(1, (int) ($_GET['system_page'] ?? 1));
$systemNotificationPerPage = adminPaginationPerPage();
$systemNotificationsReady = $pdo instanceof PDO && adminSystemNotificationsReady($pdo);
$systemNotifications = ['items' => [], 'total' => 0, 'page' => $systemNotificationPage, 'perPage' => $systemNotificationPerPage];
$systemNotificationStats = ['total' => 0, 'global' => 0, 'direct' => 0, 'unread' => 0];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $redirectParams = array_filter($_GET, static fn ($value): bool => $value !== '' && $value !== null);

    $respond = static function (bool $ok, string $message) use ($isAjax, $redirectParams, $view): void {
        if ($isAjax) {
            sendJsonResponse($ok ? 200 : 422, $ok, $message, ['ok' => $ok], $ok ? null : 'admin_log_action_failed');
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
            'delete' => static fn (PDO $pdo): int => function_exists('appLogsClearCron') ? appLogsClearCron($pdo) : 0,
            'context' => [
                'channel' => 'cron',
            ],
            'success_message' => static fn (int $deleted): string => 'Cron logları temizlendi. Silinen kayıt: ' . $deleted . '.',
        ]);
    }

    if ($view === 'system_notifications' && $postAction === 'clear_system_notifications') {
        $redirectUrl = 'logs.php';
        if ($redirectParams !== []) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        } elseif ($view === 'system_notifications') {
            $redirectUrl .= '?view=system_notifications';
        }

        adminRunLogCleanup($pdo, [
            'action_type' => 'system_notifications_cleared',
            'scope' => 'all',
            'allowed_scopes' => ['all'],
            'permission' => 'logs.manage',
            'permission_message' => 'Bu işlemi yapmak için logs.manage izni gereklidir.',
            'redirect_url' => $redirectUrl,
            'source' => 'system_notifications',
            'ready' => $pdo instanceof PDO && adminSystemNotificationsReady($pdo),
            'ready_message' => 'Bildirim tablosu hazır olmadığı için sistem bildirimleri temizlenemedi.',
            'delete' => static fn (PDO $pdo): int => adminSystemNotificationsClearAll($pdo),
            'context' => [
                'type' => 'system',
            ],
            'success_message' => static fn (int $deleted): string => 'Sistem bildirimleri temizlendi. Silinen kayıt: ' . $deleted . '.',
        ]);
    }

    if ($view === 'system_notifications' && $postAction === 'delete_system_notifications_selected') {
        $selectedNotificationIds = adminSystemNotificationIdsFromRequest();
        $redirectUrl = 'logs.php';
        if ($redirectParams !== []) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        } else {
            $redirectUrl .= '?view=system_notifications';
        }

        adminRunLogCleanup($pdo, [
            'action_type' => 'system_notifications_deleted',
            'scope' => 'selected',
            'allowed_scopes' => ['selected'],
            'permission' => 'logs.manage',
            'permission_message' => 'Bu işlemi yapmak için logs.manage izni gereklidir.',
            'redirect_url' => $redirectUrl,
            'source' => 'system_notifications',
            'ready' => $pdo instanceof PDO && adminSystemNotificationsReady($pdo),
            'ready_message' => 'Bildirim tablosu hazır olmadığı için seçili sistem bildirimleri silinemedi.',
            'validate' => static fn (string $scope): string => $selectedNotificationIds === [] ? 'Silmek için en az bir sistem bildirimi seçin.' : '',
            'delete' => static fn (PDO $pdo): int => adminSystemNotificationsDeleteSelected($pdo, $selectedNotificationIds),
            'context' => [
                'source' => 'system_notifications',
                'selected_count' => count($selectedNotificationIds),
                'notification_ids' => array_slice($selectedNotificationIds, 0, 50),
            ],
            'require_deleted' => true,
            'failure_message' => 'Seçili sistem bildirimleri içinde silinebilir kayıt bulunamadı.',
            'success_message' => static fn (int $deleted): string => 'Seçili sistem bildirimleri silindi. Silinen kayıt: ' . $deleted . '.',
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
    'system_notifications_deleted',
    'system_notifications_cleared',
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
        $cronWhereBase = function_exists('appLogsCronWhereClause') ? appLogsCronWhereClause('') : "channel = 'cron'";
        $jobStmt = $pdo->query("SELECT message, context_json FROM application_logs WHERE {$cronWhereBase} ORDER BY message ASC");
        $jobRows = $jobStmt ? ($jobStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $jobMap = [];
        foreach ($jobRows as $jobRow) {
            $jobContext = json_decode((string) ($jobRow['context_json'] ?? ''), true);
            $jobKey = is_array($jobContext) ? trim((string) ($jobContext['job_key'] ?? '')) : '';
            if ($jobKey === '') {
                $jobMessage = trim((string) ($jobRow['message'] ?? ''));
                if ($jobMessage === '') {
                    continue;
                }
                $jobKey = str_starts_with($jobMessage, 'cron_run:') ? substr($jobMessage, 9) : $jobMessage;
            }
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
                SUM(CASE WHEN context_json LIKE '%\"status\":\"success\"%' OR (level = 'info' AND (context_json IS NULL OR context_json NOT LIKE '%\"status\":%')) THEN 1 ELSE 0 END) AS success_count
            FROM application_logs
            WHERE {$cronWhereBase}
        ");
        $cronStatsRow = $cronStatsStmt ? ($cronStatsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $cronStats['total'] = (int) ($cronStatsRow['total'] ?? 0);
        $cronStats['error'] = (int) ($cronStatsRow['error_count'] ?? 0);
        $cronStats['warning'] = (int) ($cronStatsRow['warning_count'] ?? 0);
        $cronStats['success'] = (int) ($cronStatsRow['success_count'] ?? 0);
        $cronStats['job_count'] = count($cronJobs);

        $where = [$cronWhereBase];
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

if ($pdo && $view === 'system_notifications') {
    if (!$systemNotificationsReady) {
        flash('error', 'Bildirim tablosu hazır olmadığı için sistem bildirimleri yüklenemedi.');
    } else {
        try {
            $readCountSelect = function_exists('adminTableExists') && adminTableExists($pdo, 'notification_reads')
                ? '(SELECT COUNT(*) FROM notification_reads nr WHERE nr.notification_id = n.id AND (n.user_id IS NULL OR nr.user_id = n.user_id)) AS read_count'
                : '0 AS read_count';
            $dismissedCountSelect = function_exists('adminTableExists') && adminTableExists($pdo, 'notification_dismissals')
                ? '(SELECT COUNT(*) FROM notification_dismissals nd WHERE nd.notification_id = n.id AND (n.user_id IS NULL OR nd.user_id = n.user_id)) AS dismissed_count'
                : '0 AS dismissed_count';
            $deliveryChannelsSelect = function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'delivery_channels')
                ? 'n.delivery_channels'
                : 'NULL AS delivery_channels';
            $usersJoin = function_exists('adminTableExists') && adminTableExists($pdo, 'users')
                ? 'LEFT JOIN users u ON u.id = n.user_id'
                : '';
            $targetSelect = $usersJoin !== '' ? 'u.username AS target_username, u.email AS target_email' : 'NULL AS target_username, NULL AS target_email';
            $systemNotificationWhere = function_exists('adminSystemNotificationWhereSql')
                ? adminSystemNotificationWhereSql($pdo, 'n')
                : "n.type = 'system'";
            $systemNotificationWherePlain = function_exists('adminSystemNotificationWhereSql')
                ? adminSystemNotificationWhereSql($pdo, '')
                : "type = 'system'";

            $statsStmt = $pdo->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS global_count,
                    SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS direct_count
                FROM notifications
                WHERE {$systemNotificationWherePlain}
            ");
            $statsRow = $statsStmt ? ($statsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            $systemNotificationStats['total'] = (int) ($statsRow['total'] ?? 0);
            $systemNotificationStats['global'] = (int) ($statsRow['global_count'] ?? 0);
            $systemNotificationStats['direct'] = (int) ($statsRow['direct_count'] ?? 0);

            $systemNotificationUnreadWhere = function_exists('adminSystemNotificationUnreadWhereSql')
                ? adminSystemNotificationUnreadWhereSql($pdo, 'n')
                : 'NOT EXISTS (
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.notification_id = n.id
                )';
            $unreadStmt = $pdo->query("
                SELECT COUNT(*)
                FROM notifications n
                WHERE {$systemNotificationWhere}
                AND {$systemNotificationUnreadWhere}
            ");
            $systemNotificationStats['unread'] = $unreadStmt ? (int) $unreadStmt->fetchColumn() : 0;

            $where = [$systemNotificationWhere];
            $params = [];

            if ($systemNotificationSearch !== '') {
                $where[] = '(n.title LIKE :system_q OR n.message LIKE :system_q OR n.link LIKE :system_q' . ($usersJoin !== '' ? ' OR u.username LIKE :system_q OR u.email LIKE :system_q' : '') . ')';
                $params['system_q'] = '%' . $systemNotificationSearch . '%';
            }

            if ($systemNotificationTarget === 'global') {
                $where[] = 'n.user_id IS NULL';
            } elseif ($systemNotificationTarget === 'direct') {
                $where[] = 'n.user_id IS NOT NULL';
            }

            $whereSql = implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n {$usersJoin} WHERE {$whereSql}");
            foreach ($params as $paramKey => $paramValue) {
                $countStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $systemNotifications['total'] = (int) $countStmt->fetchColumn();

            $systemTotalPages = (int) ceil(max(0, $systemNotifications['total']) / max(1, $systemNotificationPerPage));
            if ($systemTotalPages > 0 && $systemNotificationPage > $systemTotalPages) {
                $systemNotificationPage = $systemTotalPages;
            }
            $systemNotifications['page'] = $systemNotificationPage;

            $offset = ($systemNotificationPage - 1) * $systemNotificationPerPage;
            $listStmt = $pdo->prepare("
                SELECT
                    n.id,
                    n.user_id,
                    n.title,
                    n.message,
                    n.link,
                    n.event_key,
                    {$deliveryChannelsSelect},
                    n.created_at,
                    {$targetSelect},
                    {$dismissedCountSelect},
                    {$readCountSelect}
                FROM notifications n
                {$usersJoin}
                WHERE {$whereSql}
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $paramKey => $paramValue) {
                $listStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
            }
            $listStmt->bindValue(':limit', $systemNotificationPerPage, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
            $listStmt->execute();
            $systemNotifications['items'] = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            flash('error', 'Sistem bildirimleri yüklenemedi: ' . safeErrorMessage($e));
        }
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
    'system_notifications_deleted',
    'system_notifications_cleared',
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
        'system_notifications' => 'Sistem bildirimleri',
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
    if (function_exists('adminStatusMeta')) {
        return (string) adminStatusMeta($status, 'cron')['label'];
    }

    return match ($status) {
        'success' => 'Başarılı',
        'warning' => 'Uyarı',
        'error' => 'Hata',
        'skipped' => 'Atlandı',
        default => strtoupper($status),
    };
};

$cronStatusBadgeClass = static function (string $status): string {
    if (function_exists('adminStatusMeta') && function_exists('adminToneBadgeClass')) {
        $meta = adminStatusMeta($status, 'cron');
        return adminToneBadgeClass((string) ($meta['tone'] ?? 'muted'));
    }

    return match ($status) {
        'success' => 'ui-admin-badge-success',
        'warning' => 'ui-admin-badge-warning',
        'error' => 'ui-admin-badge-danger',
        'skipped' => 'ui-admin-badge-muted',
        default => 'ui-admin-badge-muted',
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

<?php adminRenderLogsSubtabs($view); ?>

<div class="logs-page">
<?php if ($view === 'activity'): ?>
    <?= adminRenderLogPageHero('bi-shield-check', 'Denetim merkezi', 'Yönetici İşlem Günlüğü', 'Grup, durum, kısıtlama, silme ve ayar değişikliklerini izleyin; geri alınabilir işlemleri kontrollü şekilde iptal edin.') ?>

    <?php if ($successMsg): ?>
        <?= adminRenderAlert($successMsg, 'success', ['icon' => 'bi-check-circle']) ?>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <?= adminRenderAlert($errorMsg, 'danger', ['icon' => 'bi-exclamation-triangle']) ?>
    <?php endif; ?>

    <?= adminRenderLogStatCards([
        ['tone' => 'info', 'icon' => 'bi-journal-text', 'label' => 'Toplam kayıt', 'value' => number_format((int) $activityTotal, 0, ',', '.')],
        ['tone' => 'warning', 'icon' => 'bi-clock-history', 'label' => 'Geri alınabilir', 'value' => number_format((int) count(array_filter($activityLogList, static fn (array $log): bool => (int) ($log['is_reversible'] ?? 0) === 1 && empty($log['reverted_at']))), 0, ',', '.')],
        ['tone' => 'success', 'icon' => 'bi-arrow-counterclockwise', 'label' => 'Geri alınmış', 'value' => number_format((int) count(array_filter($activityLogList, static fn (array $log): bool => !empty($log['reverted_at']))), 0, ',', '.')],
        ['tone' => 'danger', 'icon' => 'bi-shield-exclamation', 'label' => 'Kritik eylem', 'value' => number_format((int) $activityCriticalCount, 0, ',', '.')],
    ], ['aria_label' => 'Yönetici işlem günlüğü özeti']) ?>

    <?= adminRenderLogToolbarOpen() ?>
            <div class="logs-toolbar-row logs-toolbar-row--activity">
            <form method="get" action="logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form admin-filter-form">
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
    <?= adminRenderLogToolbarClose() ?>

    <?= adminRenderLogListPanelOpen([
        'class' => 'user-activity-feed-card ui-card',
        'header_class' => 'user-activity-card-head',
        'icon' => 'bi-journal-check',
        'title' => 'Yönetici İşlem Akışı',
        'count_text' => number_format((int) $activityTotal, 0, ',', '.') . ' kayıt',
        'actions_html' => $canManageLogs ? adminRenderLogClearTrigger(['label' => 'Günlüğü Temizle']) : '',
    ]) ?>
            <?php if (empty($activityLogList)): ?>
                <?= adminRenderLogEmptyState([
                    'icon' => 'bi-journal-check',
                    'tone' => 'info',
                    'title' => 'İşlem kaydı bulunamadı',
                    'description' => $activityActionQuery !== '' ? 'Seçili filtrelerle eşleşen denetim kaydı yok.' : 'Henüz kritik veya geri alınabilir admin işlemi kaydedilmedi.',
                    'pro' => true,
                    'class' => 'ui-admin-empty-audit',
                    'actions' => $activityActionQuery !== '' ? [
                        ['href' => 'logs.php', 'label' => 'Filtreleri Sıfırla', 'icon' => 'bi-x-circle', 'class' => 'ui-admin-btn-sm logs-filter-reset'],
                    ] : [],
                ]) ?>
            <?php else: ?>
                <?= adminRenderLogTableOpen([
                    'wrapper_class' => 'ui-admin-table-responsive',
                    'table_class' => 'ui-admin-table ui-admin-table-striped',
                ]) ?>
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
                                            <form method="post" action="<?= htmlspecialchars($activityPostAction, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu işlemi geri almak istediğinize emin misiniz?', 'tone' => 'warning']) ?>>
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
                <?= adminRenderLogTableClose() ?>

                <?php if ($activityTotalPages > 1): ?>
                    <?php
                    $activityPageParams = $activityFilters;
                    $pageBase = 'logs.php?' . ($activityPageParams ? http_build_query($activityPageParams) . '&' : '') . 'page=';
                    echo adminRenderLogPagination($activityTotalPages, $page, static fn (int $targetPage): string => $pageBase . $targetPage, [
                        'aria_label' => 'Yönetici işlem günlüğü sayfalama',
                    ]);
                    ?>
                <?php endif; ?>
            <?php endif; ?>
    <?= adminRenderLogListPanelClose() ?>

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
        adminRenderLogClearModal($logClearModal);
        unset($logClearModal);
        ?>
    <?php endif; ?>

<?php elseif ($view === 'cron'): ?>
    <?= adminRenderLogPageHero('bi-cpu', 'Zamanlanmış işler', 'Cron Logları', 'Cron çalışmalarını, durumları ve çıktılarını tek listede izleyin.') ?>

    <?= adminRenderLogStatCards([
        ['tone' => 'info', 'icon' => 'bi-card-list', 'label' => 'Toplam Cron Log', 'value' => number_format((int) $cronStats['total'], 0, ',', '.')],
        ['tone' => 'success', 'icon' => 'bi-check2-circle', 'label' => 'Başarılı', 'value' => number_format((int) $cronStats['success'], 0, ',', '.')],
        ['tone' => 'warning', 'icon' => 'bi-exclamation-triangle', 'label' => 'Uyarı', 'value' => number_format((int) $cronStats['warning'], 0, ',', '.')],
        ['tone' => 'danger', 'icon' => 'bi-bug', 'label' => 'Hata', 'value' => number_format((int) $cronStats['error'], 0, ',', '.')],
        ['tone' => 'info', 'icon' => 'bi-cpu', 'label' => 'Farklı Job', 'value' => number_format((int) $cronStats['job_count'], 0, ',', '.')],
    ], ['aria_label' => 'Cron logları özeti']) ?>

    <?= adminRenderLogToolbarOpen() ?>
            <form method="get" action="logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form admin-filter-form">
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
                    <?= adminRenderLogClearTrigger(['label' => 'Günlüğü Temizle']) ?>
                <?php endif; ?>
                <a href="settings.php#cron" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs"><i class="bi bi-gear"></i> Cron Ayarları</a>
            </div>
    <?= adminRenderLogToolbarClose() ?>

    <?= adminRenderLogListPanelOpen([
        'tag' => 'div',
        'header_class' => 'user-activity-card-head',
        'icon' => 'bi-card-list',
        'title' => 'Cron Logları',
        'count_text' => number_format((int) $cronLogs['total'], 0, ',', '.') . ' kayıt',
    ]) ?>
            <?php if (empty($cronLogs['items'])): ?>
                <?= adminRenderLogEmptyState([
                    'icon' => 'bi-card-list',
                    'tone' => 'info',
                    'title' => 'Cron log kaydı bulunamadı',
                    'description' => 'Filtreye uyan cron kaydı yok. Cron çalıştığında kayıtlar burada listelenir.',
                ]) ?>
            <?php else: ?>
                <?= adminRenderLogTableOpen([
                    'wrapper_class' => 'cron-logs-table-wrap',
                    'table_class' => 'cron-logs-table admin-log-card-table',
                    'table_attrs' => ['aria-label' => 'Cron logları'],
                ]) ?>
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
                                    <td class="ui-admin-table-cell-id" data-label="#"><?= (int) ($cronLog['id'] ?? 0) ?></td>
                                    <td class="ui-admin-table-cell-date" data-label="Tarih"><?= date('d.m.Y H:i:s', strtotime((string) ($cronLog['created_at'] ?? 'now'))) ?></td>
                                    <td data-label="Durum"><span class="ui-admin-badge admin-badge <?= htmlspecialchars($cronLevelClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cronStatusLabel($cronStatusValue), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="ui-admin-table-cell-strong" data-label="Job"><code><?= htmlspecialchars($cronJobLabel !== '' ? $cronJobLabel : '-', ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-message-cell" data-label="Mesaj">
                                        <div class="ui-admin-log-message-title"><?= htmlspecialchars($cronHumanMessage !== '' ? $cronHumanMessage : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell" data-label="Detay">
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
                <?= adminRenderLogTableClose() ?>

                <?php
                $cronTotalPages = (int) ceil(max(0, (int) $cronLogs['total']) / max(1, (int) $cronLogs['perPage']));
                if ($cronTotalPages > 1):
                    $cronQueryBase = array_filter([
                        'view' => 'cron',
                        'cron_q' => $cronSearch,
                        'cron_status' => $cronStatus !== 'all' ? $cronStatus : '',
                        'cron_job' => $cronJob,
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    echo adminRenderLogPagination($cronTotalPages, $cronPage, static fn (int $targetPage): string => 'logs.php?' . http_build_query(array_merge($cronQueryBase, ['cron_page' => $targetPage])), [
                        'aria_label' => 'Cron günlüğü sayfalama',
                    ]);
                endif;
                ?>
            <?php endif; ?>
    <?= adminRenderLogListPanelClose('div') ?>

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
        adminRenderLogClearModal($logClearModal);
        unset($logClearModal);
        ?>
    <?php endif; ?>
<?php else: ?>
    <?= adminRenderLogPageHero('bi-cpu', 'Bildirim günlükleri', 'Sistem Bildirimleri', 'Sistem tipi bildirimleri, hedeflerini ve okunma durumlarını admin panelinden izleyin.') ?>

    <?php if ($successMsg): ?>
        <?= adminRenderAlert($successMsg, 'success', ['icon' => 'bi-check-circle']) ?>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <?= adminRenderAlert($errorMsg, 'danger', ['icon' => 'bi-exclamation-triangle']) ?>
    <?php endif; ?>
    <?php
    $systemNotificationActionQuery = array_filter([
        'view' => 'system_notifications',
        'system_q' => $systemNotificationSearch,
        'system_target' => $systemNotificationTarget !== 'all' ? $systemNotificationTarget : '',
        'system_page' => $systemNotificationPage > 1 ? $systemNotificationPage : '',
    ], static fn ($value): bool => $value !== '' && $value !== null);
    $systemNotificationActionUrl = 'logs.php?' . http_build_query($systemNotificationActionQuery);
    ?>

    <?= adminRenderLogStatCards([
        ['tone' => 'info', 'icon' => 'bi-cpu', 'label' => 'Toplam Sistem Bildirimi', 'value' => number_format((int) $systemNotificationStats['total'], 0, ',', '.')],
        ['tone' => 'success', 'icon' => 'bi-broadcast', 'label' => 'Genel Yayın', 'value' => number_format((int) $systemNotificationStats['global'], 0, ',', '.')],
        ['tone' => 'warning', 'icon' => 'bi-person-badge', 'label' => 'Özel Hedef', 'value' => number_format((int) $systemNotificationStats['direct'], 0, ',', '.')],
        ['tone' => 'danger', 'icon' => 'bi-circle-fill', 'label' => 'Site İçi Okunmamış', 'value' => number_format((int) $systemNotificationStats['unread'], 0, ',', '.')],
    ], ['aria_label' => 'Sistem bildirimleri özeti']) ?>

    <?= adminRenderLogToolbarOpen() ?>
            <form method="get" action="logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form admin-filter-form">
                <input type="hidden" name="view" value="system_notifications">
                <input type="text" name="system_q" class="ui-admin-form-control" placeholder="Başlık, mesaj, link veya kullanıcı ara..." value="<?= htmlspecialchars($systemNotificationSearch, ENT_QUOTES, 'UTF-8') ?>">
                <select name="system_target" class="ui-admin-form-select">
                    <option value="all" <?= $systemNotificationTarget === 'all' ? 'selected' : '' ?>>Tüm Hedefler</option>
                    <option value="global" <?= $systemNotificationTarget === 'global' ? 'selected' : '' ?>>Genel Yayın</option>
                    <option value="direct" <?= $systemNotificationTarget === 'direct' ? 'selected' : '' ?>>Özel Hedef</option>
                </select>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($systemNotificationSearch !== '' || $systemNotificationTarget !== 'all'): ?>
                    <a href="logs.php?view=system_notifications" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
                <?php endif; ?>
            </form>
            <div class="logs-toolbar-actions">
                <?php if ($canManageLogs && (int) $systemNotificationStats['total'] > 0): ?>
                    <?= adminRenderLogClearTrigger(['label' => 'Tümünü Sil']) ?>
                <?php endif; ?>
                <a href="notifications.php?tab=new" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs"><i class="bi bi-send-plus"></i> Yeni Bildirim</a>
            </div>
    <?= adminRenderLogToolbarClose() ?>

    <?= adminRenderLogListPanelOpen([
        'tag' => 'div',
        'class' => 'system-notifications-list-card',
        'header_class' => 'user-activity-card-head',
        'body_class' => 'system-notifications-list-body',
        'icon' => 'bi-cpu',
        'title' => 'Sistem Bildirimleri',
        'count_text' => number_format((int) $systemNotifications['total'], 0, ',', '.') . ' kayıt',
    ]) ?>
            <?php if (!$systemNotificationsReady): ?>
                <?= adminRenderLogEmptyState([
                    'icon' => 'bi-database-exclamation',
                    'tone' => 'warning',
                    'title' => 'Bildirim tablosu hazır değil',
                    'description' => 'Sistem bildirimi kayıtları tablo hazır olduğunda burada listelenir.',
                ]) ?>
            <?php elseif (empty($systemNotifications['items'])): ?>
                <?= adminRenderLogEmptyState([
                    'icon' => 'bi-inbox',
                    'tone' => 'info',
                    'title' => 'Sistem bildirimi bulunamadı',
                    'description' => 'Filtreye uyan sistem bildirimi yok.',
                ]) ?>
            <?php else: ?>
                <?php if ($canManageLogs): ?>
                    <form id="systemNotificationsBulkForm" class="system-notifications-bulk-form" method="post" action="<?= htmlspecialchars($systemNotificationActionUrl, ENT_QUOTES, 'UTF-8') ?>" data-system-notifications-form>
                        <?php if (function_exists('csrf_field')): ?>
                            <?= csrf_field() ?>
                        <?php else: ?>
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <input type="hidden" name="action" value="delete_system_notifications_selected">
                        <input type="hidden" name="scope" value="selected">
                        <div class="system-notifications-bulk-bar admin-bulk-action-bar ui-panel" data-system-notifications-bulk-bar>
                            <label class="system-notifications-select-all">
                                <input type="checkbox" data-system-notifications-check-all>
                                <span>Sayfadaki tümünü seç</span>
                            </label>
                            <span class="system-notifications-selected-count" data-system-notifications-selected-count>0 seçili</span>
                            <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs" disabled data-system-notifications-bulk-delete>
                                <i class="bi bi-trash"></i> Seçilileri Sil
                            </button>
                        </div>
                <?php endif; ?>
                <?= adminRenderLogTableOpen([
                    'wrapper_class' => 'system-notifications-table-wrap',
                    'table_class' => 'system-notifications-table admin-log-card-table',
                    'table_attrs' => ['aria-label' => 'Sistem bildirimleri'],
                ]) ?>
                        <colgroup>
                            <col class="system-notifications-col-message">
                            <col class="system-notifications-col-target">
                            <col class="system-notifications-col-meta">
                            <col class="system-notifications-col-actions">
                            <?php if ($canManageLogs): ?>
                                <col class="system-notifications-col-check">
                            <?php endif; ?>
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="system-notifications-message-head">Bildirim</th>
                                <th class="system-notifications-target-head">Hedef</th>
                                <th class="system-notifications-meta-head">Bilgi</th>
                                <th class="system-notifications-actions-head">İşlem</th>
                                <?php if ($canManageLogs): ?>
                                    <th class="ui-admin-table-head-narrow system-notifications-check-col">Seç</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systemNotifications['items'] as $systemNotification): ?>
                                <?php
                                $notificationId = (int) ($systemNotification['id'] ?? 0);
                                $createdAtRaw = (string) ($systemNotification['created_at'] ?? '');
                                $createdAtTs = strtotime($createdAtRaw);
                                $createdLabel = $createdAtTs !== false ? date('d.m.Y H:i', $createdAtTs) : $createdAtRaw;
                                $targetUserId = (int) ($systemNotification['user_id'] ?? 0);
                                $targetName = trim((string) ($systemNotification['target_username'] ?? ''));
                                if ($targetName === '') {
                                    $targetName = trim((string) ($systemNotification['target_email'] ?? ''));
                                }
                                $messagePreview = trim((string) ($systemNotification['message'] ?? ''));
                                if (function_exists('mb_strlen') && mb_strlen($messagePreview, 'UTF-8') > 180) {
                                    $messagePreview = mb_substr($messagePreview, 0, 177, 'UTF-8') . '...';
                                } elseif (strlen($messagePreview) > 180) {
                                    $messagePreview = substr($messagePreview, 0, 177) . '...';
                                }
                                $eventKey = trim((string) ($systemNotification['event_key'] ?? ''));
                                $link = trim((string) ($systemNotification['link'] ?? ''));
                                $targetLabel = $targetUserId > 0 ? ($targetName !== '' ? $targetName : ('Kullanıcı #' . $targetUserId)) : 'Genel Yayın';
                                $readState = adminSystemNotificationReadState($systemNotification);
                                $readLabel = (string) ($readState['label'] ?? 'Okunmamış');
                                $readBadgeClass = (string) ($readState['badge_class'] ?? 'ui-admin-badge-danger');
                                ?>
                                <tr data-system-notification-row>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-message-cell system-notifications-message-cell" data-label="Bildirim">
                                        <div class="ui-admin-log-message-title"><?= htmlspecialchars((string) ($systemNotification['title'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="ui-admin-log-summary"><?= htmlspecialchars($messagePreview !== '' ? $messagePreview : 'Mesaj yok', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="system-notifications-target-cell" data-label="Hedef">
                                        <div class="system-notifications-target-stack">
                                            <?php if ($targetUserId > 0): ?>
                                                <span class="ui-admin-badge ui-admin-badge-warning"><?= htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php else: ?>
                                                <span class="ui-admin-badge ui-admin-badge-info">Genel Yayın</span>
                                            <?php endif; ?>
                                            <span class="ui-admin-badge <?= htmlspecialchars($readBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($readLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </td>
                                    <td class="system-notifications-meta-cell" data-label="Bilgi">
                                        <div class="system-notifications-message-meta">
                                            <span class="ui-admin-badge ui-admin-badge-muted">#<?= $notificationId ?></span>
                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-clock"></i> <?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($eventKey !== ''): ?>
                                                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-tag"></i> <?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($link !== ''): ?>
                                                <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs system-notifications-message-link" target="_blank" rel="noopener" title="Bağlantıyı aç" data-admin-tooltip="Bağlantıyı aç">
                                                    <i class="bi bi-box-arrow-up-right"></i> Aç
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="system-notifications-actions" data-label="İşlem">
                                        <div class="system-notifications-action-stack">
                                            <button
                                                type="button"
                                                class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs"
                                                aria-label="Bildirim detayını aç"
                                                title="Detay"
                                                data-admin-tooltip="Detay"
                                                data-system-notification-detail-open
                                                data-id="<?= $notificationId ?>"
                                                data-title="<?= htmlspecialchars((string) ($systemNotification['title'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-message="<?= htmlspecialchars((string) ($systemNotification['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-created="<?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                data-target="<?= htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                data-target-id="<?= $targetUserId ?>"
                                                data-read-label="<?= htmlspecialchars($readLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                data-event-key="<?= htmlspecialchars($eventKey !== '' ? $eventKey : '—', ENT_QUOTES, 'UTF-8') ?>"
                                                data-link="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                <i class="bi bi-card-text" aria-hidden="true"></i>
                                            </button>
                                            <?php if ($canManageLogs): ?>
                                                <button type="button" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs" aria-label="Sistem bildirimini sil" title="Sil" data-admin-tooltip="Sil" data-system-notification-delete data-notification-id="<?= $notificationId ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php if ($canManageLogs): ?>
                                        <td class="system-notifications-check-col" data-label="Seç">
                                            <input type="checkbox" class="system-notification-row-checkbox" name="notification_ids[]" value="<?= $notificationId ?>" aria-label="Bildirim #<?= $notificationId ?> seç" data-system-notification-checkbox>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                <?= adminRenderLogTableClose() ?>

                <?php
                $systemTotalPages = (int) ceil(max(0, (int) $systemNotifications['total']) / max(1, (int) $systemNotifications['perPage']));
                if ($systemTotalPages > 1):
                    $systemQueryBase = array_filter([
                        'view' => 'system_notifications',
                        'system_q' => $systemNotificationSearch,
                        'system_target' => $systemNotificationTarget !== 'all' ? $systemNotificationTarget : '',
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    echo adminRenderLogPagination($systemTotalPages, $systemNotificationPage, static fn (int $targetPage): string => 'logs.php?' . http_build_query(array_merge($systemQueryBase, ['system_page' => $targetPage])), [
                        'aria_label' => 'Sistem bildirimleri sayfalama',
                    ]);
                endif;
                ?>
                <?php if ($canManageLogs): ?>
                    </form>
                <?php endif; ?>
    <?= adminRenderLogListPanelClose('div') ?>

                <div class="media-modal-overlay system-notification-detail-modal" id="systemNotificationDetailModal" role="dialog" aria-modal="true" aria-labelledby="systemNotificationDetailTitle" hidden aria-hidden="true">
                    <div class="media-modal ui-admin-modal-md ui-panel">
                        <div class="media-modal-header ui-panel__head">
                            <h3 class="ui-admin-modal-title" id="systemNotificationDetailTitle"><i class="bi bi-card-text"></i> Bildirim Detayı</h3>
                            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-system-notification-detail-close aria-label="Kapat" title="Kapat" data-admin-tooltip="Kapat"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="media-modal-body ui-panel__body system-notification-detail-body">
                            <div class="system-notification-detail-head">
                                <div>
                                    <span class="ui-admin-muted" data-system-notification-detail-id>#</span>
                                    <strong data-system-notification-detail-title>Bildirim</strong>
                                </div>
                                <span class="ui-admin-badge ui-admin-badge-muted" data-system-notification-detail-read>Okunma</span>
                            </div>
                            <div class="system-notification-detail-grid">
                                <div><span>Tarih</span><strong data-system-notification-detail-created>—</strong></div>
                                <div><span>Hedef</span><strong data-system-notification-detail-target>—</strong></div>
                                <div><span>Event</span><strong data-system-notification-detail-event>—</strong></div>
                                <div><span>Bağlantı</span><strong data-system-notification-detail-link-label>—</strong></div>
                            </div>
                            <div class="system-notification-detail-message">
                                <span>Mesaj</span>
                                <p data-system-notification-detail-message>—</p>
                            </div>
                        </div>
                        <div class="media-modal-footer ui-panel__foot system-notification-detail-footer">
                            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-system-notification-detail-close>Kapat</button>
                            <a href="#" class="ui-admin-btn ui-admin-btn-outline" target="_blank" rel="noopener" hidden data-system-notification-detail-link><i class="bi bi-box-arrow-up-right"></i> Bağlantıyı Aç</a>
                            <?php if ($canManageLogs): ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-danger-outline" data-system-notification-detail-delete><i class="bi bi-trash"></i> Sil</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canManageLogs && (int) $systemNotificationStats['total'] > 0): ?>
                <?php
                $logClearModal = [
                    'aria_label' => 'Sistem bildirimlerini temizle',
                    'title' => 'Sistem Bildirimlerini Temizle',
                    'form_action' => 'logs.php?view=system_notifications',
                    'hidden_fields' => [
                        ['name' => 'action', 'value' => 'clear_system_notifications'],
                    ],
                    'scope_name' => 'scope',
                    'options' => [
                        [
                            'value' => 'all',
                            'label' => 'Tüm sistem bildirimlerini sil (Tehlikeli)',
                            'confirm_title' => 'Sistem Bildirimlerini Temizle',
                        ],
                    ],
                    'warning' => 'Tüm sistem bildirimleri ve ilişkili okundu/kuyruk/gizleme kayıtları kalıcı olarak silinir. Bu işlem geri alınamaz.',
                ];
                adminRenderLogClearModal($logClearModal);
                unset($logClearModal);
                ?>
            <?php endif; ?>
<?php endif; ?>
</div>

<?php if ($view === 'system_notifications'): ?>
    <script src="<?= asset_url('admin/assets/system-notifications-log.js', $baseUri) ?>" defer></script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

