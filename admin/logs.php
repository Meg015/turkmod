<?php

declare(strict_types=1);

/**
 * Loglar Controller
 * Is mantigi: includes/src/Engine/Logs/Legacy/helpers.php
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Legacy/helpers.php';
adminRequirePermission('logs.view', 'Loglari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$view = strtolower(trim((string) ($_GET['view'] ?? 'activity')));
if (!in_array($view, ['activity', 'cron'], true)) {
    $view = 'activity';
}

$pageTitle = $view === 'cron' ? 'Cron Loglari' : 'Aktivite Loglari';

// Aktivite log temizleme islemleri
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'clear_old' || $postAction === 'clear_all' || $postAction === 'clear_cron_all') {
        $redirectTarget = $view === 'cron' ? 'logs.php?view=cron' : 'logs.php';
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Guvenlik hatasi.');
        } elseif (!adminCurrentUserCan('logs.manage')) {
            adminDenyAction('Log temizlemek icin gerekli izin hesabiniza tanimlanmamis.', $redirectTarget);
        } else {
            try {
                if ($postAction === 'clear_cron_all') {
                    $deleted = appLogsClearFiltered($pdo, '', '', 'cron');
                    logActivity($pdo, 'cron_logs_cleared', 'logs', null, ['scope' => 'all', 'deleted' => $deleted, 'channel' => 'cron']);
                    if (function_exists('adminAuditLogger')) {
                        adminAuditLogger()->logAction(
                            $pdo,
                            'cron_logs_cleared',
                            'logs',
                            0,
                            'Cron loglari tamamen temizlendi',
                            [],
                            ['scope' => 'all', 'deleted' => $deleted, 'channel' => 'cron'],
                            false
                        );
                    }
                    flash('success', 'Cron loglari temizlendi. Silinen kayit: ' . $deleted . '.');
                } elseif ($postAction === 'clear_all') {
                    $deleted = logsClearAll($pdo);
                    if (function_exists('adminAuditLogger')) {
                        adminAuditLogger()->logAction(
                            $pdo,
                            'activity_logs_cleared',
                            'settings',
                            0,
                            'Aktivite loglari tamamen temizlendi',
                            [],
                            ['scope' => 'all', 'deleted' => $deleted],
                            false
                        );
                    }
                    flash('success', 'Tebrikler, ' . $deleted . ' adet kayit tamamen temizlendi.');
                } else {
                    $days = max(7, (int) ($_POST['days'] ?? 90));
                    $deleted = logsClearOld($pdo, $days);
                    logActivity($pdo, 'activity_logs_cleared', 'logs', null, ['action' => $postAction, 'deleted' => $deleted, 'days' => $days]);
                    if (function_exists('adminAuditLogger')) {
                        adminAuditLogger()->logAction(
                            $pdo,
                            'activity_logs_cleared',
                            'settings',
                            0,
                            'Aktivite loglari kismi temizlendi',
                            [],
                            ['scope' => 'old', 'deleted' => $deleted, 'days' => $days],
                            false
                        );
                    }
                    flash('success', $deleted . ' eski log kaydi silindi.');
                }
            } catch (Throwable $e) {
                flash('error', 'Temizleme hatasi: ' . safeErrorMessage($e));
            }
        }

        header('Location: ' . $redirectTarget);
        exit;
    }
}

// Aktivite loglari filtreleri
$search = trim((string) ($_GET['q'] ?? ''));
$filterAction = (string) ($_GET['action'] ?? '');
$filterSubject = trim((string) ($_GET['subject'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) === 1 ? (string) $_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) === 1 ? (string) $_GET['date_to'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$logs = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 50];
$stats = ['total' => 0, 'today' => 0, 'week' => 0, 'error_events' => 0, 'critical_admin_actions' => 0];
$actionTypes = [];
$subjectTypes = [];

// Cron loglari filtreleri
$cronSearch = trim((string) ($_GET['cron_q'] ?? ''));
$cronStatus = strtolower(trim((string) ($_GET['cron_status'] ?? 'all')));
$cronJob = trim((string) ($_GET['cron_job'] ?? ''));
$cronPage = max(1, (int) ($_GET['cron_page'] ?? 1));
$cronPerPage = 50;
$cronAllowedStatuses = ['all', 'success', 'warning', 'error', 'skipped'];
if (!in_array($cronStatus, $cronAllowedStatuses, true)) {
    $cronStatus = 'all';
}

$cronLogs = ['items' => [], 'total' => 0, 'page' => $cronPage, 'perPage' => $cronPerPage];
$cronStats = ['total' => 0, 'success' => 0, 'warning' => 0, 'error' => 0, 'job_count' => 0];
$cronJobs = [];

if ($pdo) {
    try {
        $logs = logsGetList($pdo, $search, $filterAction, $page, 50, $filterSubject, $dateFrom, $dateTo);
        $stats = logsGetStats($pdo);
        $actionTypes = logsGetActionTypes($pdo);
        $subjectTypes = logsGetSubjectTypes($pdo);
    } catch (Throwable $e) {
        flash('error', 'Aktivite loglari yuklenemedi: ' . safeErrorMessage($e));
    }

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

        $cronTotalPages = (int) ceil($cronLogs['total'] / max(1, $cronPerPage));
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

        foreach ($rows as $row) {
            $context = json_decode((string) ($row['context_json'] ?? ''), true);
            if (!is_array($context)) {
                $context = [];
            }

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

            $row['context_data'] = $context;
            $row['job_key'] = $jobKey;
            $row['status'] = $status;
            $cronLogs['items'][] = $row;
        }
    } catch (Throwable $e) {
        flash('error', 'Cron loglari yuklenemedi: ' . safeErrorMessage($e));
    }
}

$cronStatusLabel = static function (string $status): string {
    return match ($status) {
        'success' => 'Basarili',
        'warning' => 'Uyari',
        'error' => 'Hata',
        'skipped' => 'Atlandi',
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
require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs($view === 'cron' ? 'cron' : 'activity'); ?>

<div class="logs-page">
<?php if ($view === 'cron'): ?>
    <div class="admin-stat-grid logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-card-list"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam Cron Log</span><span class="stat-value"><?= number_format((int) $cronStats['total']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-content"><span class="stat-label">Basarili</span><span class="stat-value"><?= number_format((int) $cronStats['success']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-warning logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-content"><span class="stat-label">Uyari</span><span class="stat-value"><?= number_format((int) $cronStats['warning']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-danger logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-bug"></i></div>
            <div class="stat-content"><span class="stat-label">Hata</span><span class="stat-value"><?= number_format((int) $cronStats['error']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-cpu"></i></div>
            <div class="stat-content"><span class="stat-label">Farkli Job</span><span class="stat-value"><?= number_format((int) $cronStats['job_count']) ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-header logs-toolbar-head ui-panel__head">
            <form method="get" action="logs.php" class="logs-filter-form">
                <input type="hidden" name="view" value="cron">
                <input type="text" name="cron_q" class="ui-admin-form-control" placeholder="Mesaj, job key veya IP ara..." value="<?= htmlspecialchars($cronSearch) ?>">
                <select name="cron_status" class="ui-admin-form-select">
                    <option value="all" <?= $cronStatus === 'all' ? 'selected' : '' ?>>Tum Durumlar</option>
                    <option value="success" <?= $cronStatus === 'success' ? 'selected' : '' ?>>Basarili</option>
                    <option value="warning" <?= $cronStatus === 'warning' ? 'selected' : '' ?>>Uyari</option>
                    <option value="error" <?= $cronStatus === 'error' ? 'selected' : '' ?>>Hata</option>
                    <option value="skipped" <?= $cronStatus === 'skipped' ? 'selected' : '' ?>>Atlandi</option>
                </select>
                <select name="cron_job" class="ui-admin-form-select">
                    <option value="">Tum Joblar</option>
                    <?php foreach ($cronJobs as $cronJobOption): ?>
                    <option value="<?= htmlspecialchars((string) $cronJobOption) ?>" <?= $cronJob === (string) $cronJobOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $cronJobOption) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($cronSearch !== '' || $cronStatus !== 'all' || $cronJob !== ''): ?>
                    <a href="logs.php?view=cron" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
                <?php endif; ?>
            </form>
            <div class="logs-clear-form d-flex flex-wrap gap-2 align-items-center">
                <?php if (adminCurrentUserCan('logs.manage')): ?>
                <form method="post" action="logs.php?view=cron" data-admin-confirm="Tum cron loglari kalici olarak silinecek. Emin misiniz?" data-admin-confirm-title="Cron loglari silinsin mi?" data-admin-confirm-ok="Tumunu Sil" data-admin-confirm-tone="danger">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_cron_all">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Tumunu Sil</button>
                </form>
                <?php endif; ?>
                <a href="settings.php#cron" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-gear"></i> Cron Ayarlari</a>
            </div>
        </div>
    </div>

    <div class="admin-card logs-list-card ui-panel">
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($cronLogs['items'])): ?>
                <div class="ui-admin-empty ui-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-card-list"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Cron log kaydi bulunamadi</h3>
                    <p class="ui-admin-empty-desc ui-empty">Filtreye uyan cron kaydi yok. Cron calistiginda kayitlar burada listelenir.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper ui-table-wrap ui-surface">
                    <table class="admin-table">
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
                                $cronSummary = $cronContextSummary($cronContext);
                                $cronIp = trim((string) ($cronLog['ip_address'] ?? ''));
                            ?>
                            <tr>
                                <td class="ui-admin-table-cell-id"><?= (int) ($cronLog['id'] ?? 0) ?></td>
                                <td class="ui-admin-table-cell-date"><?= date('d.m.Y H:i:s', strtotime((string) ($cronLog['created_at'] ?? 'now'))) ?></td>
                                <td><span class="badge <?= htmlspecialchars($cronLevelClass) ?>"><?= htmlspecialchars($cronStatusLabel($cronStatusValue)) ?></span></td>
                                <td class="ui-admin-table-cell-strong"><code><?= htmlspecialchars((string) ($cronLog['job_key'] ?? '-')) ?></code></td>
                                <td class="ui-admin-table-cell-secondary"><?= htmlspecialchars((string) ($cronLog['message'] ?? '-')) ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                    <div class="ui-admin-log-desc-scroll">
                                        <?= nl2br(htmlspecialchars($cronSummary)) ?>
                                        <?php if ($cronIp !== ''): ?>
                                            <br><small>ip=<?= htmlspecialchars($cronIp) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($cronContext['sapi']) && is_scalar($cronContext['sapi'])): ?>
                                            <br><small>sapi=<?= htmlspecialchars((string) $cronContext['sapi']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $cronTotalPages = (int) ceil(($cronLogs['total'] ?? 0) / max(1, (int) ($cronLogs['perPage'] ?? 1)));
                if ($cronTotalPages > 1):
                    $cronQueryBase = array_filter([
                        'view' => 'cron',
                        'cron_q' => $cronSearch,
                        'cron_status' => $cronStatus !== 'all' ? $cronStatus : '',
                        'cron_job' => $cronJob,
                    ], static fn ($value): bool => $value !== '');
                ?>
                <div class="pagination-wrapper">
                    <div class="pagination">
                        <?php if ($cronPage > 1): ?>
                            <?php $prevParams = $cronQueryBase + ['cron_page' => $cronPage - 1]; ?>
                            <a href="logs.php?<?= htmlspecialchars(http_build_query($prevParams), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Onceki"><i class="bi bi-chevron-left"></i></a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $cronPage - 2); $i <= min($cronTotalPages, $cronPage + 2); $i++): ?>
                            <?php $pageParams = $cronQueryBase + ['cron_page' => $i]; ?>
                            <a href="logs.php?<?= htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8') ?>" class="page-link <?= $i === $cronPage ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($cronPage < $cronTotalPages): ?>
                            <?php $nextParams = $cronQueryBase + ['cron_page' => $cronPage + 1]; ?>
                            <a href="logs.php?<?= htmlspecialchars(http_build_query($nextParams), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Sonraki"><i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="admin-stat-grid logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam Log</span><span class="stat-value"><?= number_format($stats['total']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-content"><span class="stat-label">Bugun Olusan</span><span class="stat-value"><?= number_format($stats['today']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-warning logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-content"><span class="stat-label">Son 7 Gun</span><span class="stat-value"><?= number_format($stats['week']) ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-list-check"></i></div>
            <div class="stat-content"><span class="stat-label">Islem Turu</span><span class="stat-value"><?= number_format((int) count($actionTypes)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-tags"></i></div>
            <div class="stat-content"><span class="stat-label">Kayit Turu</span><span class="stat-value"><?= number_format((int) count($subjectTypes)) ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-header logs-toolbar-head ui-panel__head">
            <form method="get" action="logs.php" class="logs-filter-form">
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanici, islem veya tur ara..." value="<?= htmlspecialchars($search) ?>">
                <select name="action" class="ui-admin-form-select">
                    <option value="">Tum Islemler</option>
                    <?php foreach ($actionTypes as $at): ?>
                    <option value="<?= htmlspecialchars($at) ?>" <?= $filterAction === $at ? 'selected' : '' ?>><?= htmlspecialchars(logsFormatAction($at)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="subject" class="ui-admin-form-select">
                    <option value="">Tum Kayit Turleri</option>
                    <?php foreach ($subjectTypes as $subjectType): ?>
                    <option value="<?= htmlspecialchars((string) $subjectType) ?>" <?= $filterSubject === (string) $subjectType ? 'selected' : '' ?>><?= htmlspecialchars(logsFormatSubject((string) $subjectType)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="Baslangic tarihi">
                <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo) ?>" aria-label="Bitis tarihi">
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($search !== '' || $filterAction !== '' || $filterSubject !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="logs.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
                <?php endif; ?>
            </form>
            <form method="post" action="logs.php" class="logs-clear-form" data-admin-confirm="Tum aktivite loglari kalici olarak silinecek. Emin misiniz?" data-admin-confirm-title="Tum loglar temizlensin mi?" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="danger">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Tum Loglari Temizle</button>
            </form>
        </div>
    </div>

    <div class="admin-card logs-list-card ui-panel">
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($logs['items'])): ?>
                <div class="ui-admin-empty ui-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-text"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Henuz log kaydi yok</h3>
                    <p class="ui-admin-empty-desc ui-empty">Aktivite olustugunda veya filtreleri degistirdiginizde kayitlar burada gorunecek.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper ui-table-wrap ui-surface">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="ui-admin-table-head-narrow">#</th>
                                <th>Tarih</th>
                                <th>Kullanici</th>
                                <th>Ne Oldu?</th>
                                <th>Ilgili Kayit</th>
                                <th>Aciklama</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs['items'] as $log): ?>
                            <tr>
                                <td class="ui-admin-table-cell-id"><?= (int) $log['id'] ?></td>
                                <td class="ui-admin-table-cell-date"><?= date('d.m.Y H:i', strtotime((string) $log['created_at'])) ?></td>
                                <td class="ui-admin-table-cell-strong"><?= htmlspecialchars((string) ($log['actor_name'] ?? 'Sistem')) ?></td>
                                <td>
                                    <span class="admin-badge <?= logsActionBadgeClass((string) $log['action']) ?>">
                                        <?= htmlspecialchars(logsFormatAction((string) $log['action'])) ?>
                                    </span>
                                </td>
                                <?php
                                    $logProps = !empty($log['properties']) ? json_decode((string) $log['properties'], true) : null;
                                    $logSubjectTitle = is_array($logProps) ? ($logProps['subject_title'] ?? null) : null;
                                    if (!$logSubjectTitle) {
                                        if (($log['subject_type'] ?? '') === 'topic' && !empty($log['topic_title'])) {
                                            $logSubjectTitle = $log['topic_title'];
                                        } elseif (($log['subject_type'] ?? '') === 'user') {
                                            if (!empty($log['subject_user_name'])) {
                                                $logSubjectTitle = $log['subject_user_name'];
                                            } elseif (($log['subject_id'] ?? null) == ($log['actor_id'] ?? null) && !empty($log['actor_name'])) {
                                                $logSubjectTitle = $log['actor_name'];
                                            }
                                        } elseif (($log['subject_type'] ?? '') === 'category' && !empty($log['subject_category_name'])) {
                                            $logSubjectTitle = $log['subject_category_name'];
                                        } elseif (($log['subject_type'] ?? '') === 'comment' && is_array($logProps) && isset($logProps['topic_id'])) {
                                            $logSubjectTitle = 'Konu #' . $logProps['topic_id'] . ' altindaki yorum';
                                        }
                                    }
                                    $formattedSubject = logsFormatSubject($log['subject_type'] ?? null, $log['subject_id'] ?? null, is_string($logSubjectTitle) ? $logSubjectTitle : null);
                                ?>
                                <td class="ui-admin-table-cell-secondary ui-admin-muted-sm"><?= htmlspecialchars((string) $formattedSubject) ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                    <div class="ui-admin-log-desc-scroll">
                                        <?= nl2br(htmlspecialchars(logsFormatProperties($log['properties'] ?? null))) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $totalPages = (int) ceil((int) ($logs['total'] ?? 0) / max(1, (int) ($logs['perPage'] ?? 1)));
                if ($totalPages > 1):
                ?>
                <div class="ui-admin-pagination-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $qs = http_build_query(array_filter([
                            'q' => $search,
                            'action' => $filterAction,
                            'subject' => $filterSubject,
                            'date_from' => $dateFrom,
                            'date_to' => $dateTo,
                            'page' => $i,
                        ]));
                        ?>
                        <a href="logs.php?<?= $qs ?>" class="ui-admin-btn ui-admin-btn-sm <?= $i === $page ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
