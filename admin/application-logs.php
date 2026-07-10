<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Legacy/helpers.php';

adminRequirePermission('logs.view', 'Uygulama loglarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Uygulama Logları';

if (!function_exists('applicationLogsLevelBadgeClass')) {
    function applicationLogsLevelBadgeClass(string $level): string
    {
        $normalized = strtolower(trim($level));
        if (in_array($normalized, ['emergency', 'alert', 'critical', 'error'], true)) {
            return 'admin-badge-danger';
        }
        if (in_array($normalized, ['warning', 'warn'], true)) {
            return 'admin-badge-warning';
        }
        if ($normalized === 'notice') {
            return 'admin-badge-info';
        }
        if ($normalized === 'info') {
            return 'admin-badge-success';
        }
        return 'admin-badge-secondary';
    }
}

$normalizeDate = static function ($value): string {
    $date = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if (in_array($postAction, ['clear_old', 'clear_filtered', 'clear_all'], true)) {
        $postSearch = trim((string) ($_POST['q'] ?? ''));
        $postLevel = trim((string) ($_POST['level'] ?? ''));
        $postChannel = trim((string) ($_POST['channel'] ?? ''));
        $postDateFrom = $normalizeDate($_POST['date_from'] ?? '');
        $postDateTo = $normalizeDate($_POST['date_to'] ?? '');

        $redirectParams = array_filter([
            'q' => $postSearch,
            'level' => $postLevel,
            'channel' => $postChannel,
            'date_from' => $postDateFrom,
            'date_to' => $postDateTo,
        ], static fn ($value): bool => $value !== '' && $value !== null);
        $redirectUrl = 'application-logs.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : '');

        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Guvenlik hatasi.');
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (!adminCurrentUserCan('logs.manage')) {
            adminDenyAction('Uygulama loglarini temizlemek icin gerekli izin hesabiniza tanimlanmamis.', $redirectUrl);
        }

        try {
            if ($postAction === 'clear_all') {
                $deleted = appLogsClearAll($pdo);
                logActivity($pdo, 'application_logs_cleared', 'logs', null, [
                    'scope' => 'all',
                    'deleted' => $deleted,
                ]);
                if (function_exists('adminAuditLogger')) {
                    adminAuditLogger()->logAction(
                        $pdo,
                        'application_logs_cleared',
                        'settings',
                        0,
                        'Uygulama loglari tamamen temizlendi',
                        [],
                        ['scope' => 'all', 'deleted' => $deleted],
                        false
                    );
                }
                flash('success', $deleted . ' uygulama logu temizlendi.');
            } elseif ($postAction === 'clear_old') {
                $days = max(7, min(3650, (int) ($_POST['days'] ?? 90)));
                $deleted = appLogsClearOld($pdo, $days);
                logActivity($pdo, 'application_logs_cleared', 'logs', null, [
                    'scope' => 'old',
                    'deleted' => $deleted,
                    'days' => $days,
                ]);
                if (function_exists('adminAuditLogger')) {
                    adminAuditLogger()->logAction(
                        $pdo,
                        'application_logs_cleared',
                        'settings',
                        0,
                        'Uygulama loglari kismi temizlendi',
                        [],
                        ['scope' => 'old', 'deleted' => $deleted, 'days' => $days],
                        false
                    );
                }
                flash('success', $deleted . ' kayit silindi (' . $days . ' gun onceki ve daha eski).');
            } else {
                $hasFilter = $postSearch !== '' || $postLevel !== '' || $postChannel !== '' || $postDateFrom !== '' || $postDateTo !== '';
                if (!$hasFilter) {
                    flash('error', 'Filtre secmeden filtreye gore temizleme yapamazsiniz.');
                } else {
                    $deleted = appLogsClearFiltered($pdo, $postSearch, $postLevel, $postChannel, $postDateFrom, $postDateTo);
                    logActivity($pdo, 'application_logs_cleared', 'logs', null, [
                        'scope' => 'filtered',
                        'deleted' => $deleted,
                        'filters' => [
                            'q' => $postSearch,
                            'level' => $postLevel,
                            'channel' => $postChannel,
                            'date_from' => $postDateFrom,
                            'date_to' => $postDateTo,
                        ],
                    ]);
                    if (function_exists('adminAuditLogger')) {
                        adminAuditLogger()->logAction(
                            $pdo,
                            'application_logs_cleared',
                            'settings',
                            0,
                            'Uygulama loglari filtreye gore temizlendi',
                            [],
                            [
                                'scope' => 'filtered',
                                'deleted' => $deleted,
                                'filters' => [
                                    'q' => $postSearch,
                                    'level' => $postLevel,
                                    'channel' => $postChannel,
                                    'date_from' => $postDateFrom,
                                    'date_to' => $postDateTo,
                                ],
                            ],
                            false
                        );
                    }
                    flash('success', $deleted . ' filtreli uygulama logu silindi.');
                }
            }
        } catch (Throwable $e) {
            flash('error', 'Temizleme hatasi: ' . safeErrorMessage($e));
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$filterLevel = trim((string) ($_GET['level'] ?? ''));
$filterChannel = trim((string) ($_GET['channel'] ?? ''));
$dateFrom = $normalizeDate($_GET['date_from'] ?? '');
$dateTo = $normalizeDate($_GET['date_to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$logs = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 50];
$stats = ['total' => 0, 'total_24h' => 0, 'total_7d' => 0, 'channels' => 0, 'errors_24h' => 0, 'errors_7d' => 0];
$levels = [];
$channels = [];

if ($pdo) {
    try {
        $logs = appLogsGetList($pdo, $search, $filterLevel, $filterChannel, $page, 50, $dateFrom, $dateTo);
        $stats = appLogsGetStats($pdo);
        if (!isset($stats['total_24h'])) {
            $stats['total_24h'] = (int) $pdo->query("SELECT COUNT(*) FROM application_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
        }
        if (!isset($stats['total_7d'])) {
            $stats['total_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM application_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        }
        $levels = appLogsGetLevels($pdo);
        $channels = appLogsGetChannels($pdo);
    } catch (Throwable $e) {
        flash('error', 'Uygulama loglari yuklenemedi: ' . safeErrorMessage($e));
    }
}

if ($filterLevel !== '' && !in_array($filterLevel, $levels, true)) {
    $levels[] = $filterLevel;
    sort($levels);
}
if ($filterChannel !== '' && !in_array($filterChannel, $channels, true)) {
    $channels[] = $filterChannel;
    sort($channels);
}

$hasFilters = $search !== '' || $filterLevel !== '' || $filterChannel !== '' || $dateFrom !== '' || $dateTo !== '';
$canManageLogs = adminCurrentUserCan('logs.manage');

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

$renderFilterHiddenFields = static function () use ($search, $filterLevel, $filterChannel, $dateFrom, $dateTo): void {
    echo '<input type="hidden" name="q" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="level" value="' . htmlspecialchars($filterLevel, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="channel" value="' . htmlspecialchars($filterChannel, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="date_from" value="' . htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="date_to" value="' . htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') . '">';
};

require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs('application'); ?>

<div class="logs-page application-logs-page">
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-journal-code"></i> Sistem kayıtları</span>
            <h2>Uygulama Logları</h2>
            <p>Sistem, hata ve bakım kayıtlarını tek listede izleyin.</p>
        </div>
    </section>

    <div class="ui-admin-alert ui-admin-alert-info ui-alert">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Hata merkezi guncellendi:</strong>
            Sistem kaynakli hatalari tek noktadan takip etmek icin
            <a href="<?= htmlspecialchars(rtrim((string) $baseUri, '/') . '/admin/system-health.php?tab=logs', ENT_QUOTES, 'UTF-8') ?>">Sistem Sagligi > Loglar & Hatalar</a>
            sekmesini kullanin.
        </div>
    </div>
    <div class="admin-stat-grid logs-summary application-logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-journal-code"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam Kayit</span><span class="stat-value"><?= number_format((int) ($stats['total'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-clock"></i></div>
            <div class="stat-content"><span class="stat-label">Kayit (24s)</span><span class="stat-value"><?= number_format((int) ($stats['total_24h'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-content"><span class="stat-label">Kayit (7g)</span><span class="stat-value"><?= number_format((int) ($stats['total_7d'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            <div class="stat-content"><span class="stat-label">Aktif Kanal</span><span class="stat-value"><?= number_format((int) ($stats['channels'] ?? 0)) ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card application-logs-toolbar logs-toolbar-shell">
            <form method="get" action="application-logs.php" class="ui-admin-filter-row application-logs-filter-form">
                <div class="ui-admin-filter-grow application-logs-search">
                    <label class="ui-admin-form-label">Ara</label>
                    <input type="text" name="q" class="ui-admin-form-control" placeholder="Mesaj, kanal, seviye veya IP ara..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Seviye</label>
                    <select name="level" class="ui-admin-form-select">
                        <option value="">Tum Seviyeler</option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= htmlspecialchars((string) $level, ENT_QUOTES, 'UTF-8') ?>" <?= $filterLevel === (string) $level ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper((string) $level), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Kanal</label>
                    <select name="channel" class="ui-admin-form-select">
                        <option value="">Tum Kanallar</option>
                        <?php foreach ($channels as $channel): ?>
                            <option value="<?= htmlspecialchars((string) $channel, ENT_QUOTES, 'UTF-8') ?>" <?= $filterChannel === (string) $channel ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $channel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Başlangıç</label>
                    <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" aria-label="Başlangıç tarihi">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Bitiş</label>
                    <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" aria-label="Bitiş tarihi">
                </div>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($hasFilters): ?>
                    <a href="application-logs.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-circle"></i> Temizle</a>
                <?php endif; ?>
            </form>

            <?php if ($canManageLogs): ?>
                <div class="application-logs-maintenance">
                    <div class="application-logs-maintenance-label"><i class="bi bi-tools"></i> Bakım işlemleri</div>
                    <div class="application-logs-actions logs-toolbar-actions">
                        <form method="post" action="application-logs.php" class="logs-clear-form" data-admin-confirm="Yalnizca aktif filtreye uyan uygulama loglari silinsin mi?" data-admin-confirm-title="Filtreli loglari temizle" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="warning">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear_filtered">
                            <?php $renderFilterHiddenFields(); ?>
                            <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-xs" <?= $hasFilters ? '' : 'disabled' ?>><i class="bi bi-funnel"></i> Filtreyi Temizle</button>
                        </form>
                        <form method="post" action="application-logs.php" class="logs-clear-form" data-admin-confirm="Belirttiginiz gunden daha eski uygulama loglari silinsin mi?" data-admin-confirm-title="Eski loglari temizle" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="warning">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear_old">
                            <?php $renderFilterHiddenFields(); ?>
                            <div class="application-logs-old-row">
                                <input type="number" name="days" min="7" max="3650" step="1" value="90" class="ui-admin-form-control application-logs-days-input" aria-label="Gun sayisi">
                                <button type="submit" class="ui-admin-btn ui-admin-btn-warning ui-admin-btn-xs"><i class="bi bi-calendar-minus"></i> Eskiyi Temizle</button>
                            </div>
                        </form>
                        <form method="post" action="application-logs.php" class="logs-clear-form" data-admin-confirm="Tum uygulama loglari kalici olarak silinecek. Emin misiniz?" data-admin-confirm-title="Tum uygulama loglarini temizle" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear_all">
                            <?php $renderFilterHiddenFields(); ?>
                            <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs"><i class="bi bi-trash"></i> Tümünü Sil</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card logs-list-card ui-panel">
        <div class="card-header logs-list-head ui-admin-card-header-actions ui-panel__head ui-card">
            <div>
                <h3><i class="bi bi-journal-code"></i> Uygulama Logları</h3>
                <span><?= number_format((int) ($logs['total'] ?? 0), 0, ',', '.') ?> kayıt</span>
            </div>
        </div>
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($logs['items'])): ?>
                <div class="ui-admin-empty ui-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-code"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Kayit bulunamadi</h3>
                    <p class="ui-admin-empty-desc ui-empty"><?= $hasFilters ? 'Secili filtreyle eslesen uygulama logu yok.' : 'Henuz uygulama logu olusmamis.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrapper ui-table-wrap ui-surface">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Seviye</th>
                            <th>Kanal</th>
                            <th>Mesaj</th>
                            <th>IP</th>
                            <th>Detay</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs['items'] as $log): ?>
                            <?php
                            $level = (string) ($log['level'] ?? '');
                            $createdAtRaw = (string) ($log['created_at'] ?? '');
                            $createdAtTs = strtotime($createdAtRaw);
                            $createdLabel = $createdAtTs !== false ? date('d.m.Y H:i', $createdAtTs) : $createdAtRaw;
                            $contextText = appLogsFormatContext($log['context_json'] ?? null);
                            ?>
                            <tr>
                                <td class="ui-admin-table-cell-date"><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="admin-badge <?= htmlspecialchars(applicationLogsLevelBadgeClass($level), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(strtoupper($level !== '' ? $level : 'unknown'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="ui-admin-table-cell-secondary ui-admin-muted-sm"><?= htmlspecialchars((string) ($log['channel'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                    <div class="ui-admin-log-desc-scroll"><?= nl2br(htmlspecialchars((string) ($log['message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                </td>
                                <td class="ui-admin-table-cell-secondary ui-admin-muted-sm"><?= htmlspecialchars((string) (($log['ip_address'] ?? '') !== '' ? $log['ip_address'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                    <div class="ui-admin-log-desc-scroll"><?= nl2br(htmlspecialchars($contextText, ENT_QUOTES, 'UTF-8')) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $perPage = max(1, (int) ($logs['perPage'] ?? 50));
                $totalRows = max(0, (int) ($logs['total'] ?? 0));
                $totalPages = (int) ceil($totalRows / $perPage);
                if ($totalPages > 1):
                    $pageParams = array_filter([
                        'q' => $search,
                        'level' => $filterLevel,
                        'channel' => $filterChannel,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    $pageBase = 'application-logs.php?' . ($pageParams ? http_build_query($pageParams) . '&' : '') . 'page=';
                    ?>
                    <div class="pagination-wrapper logs-pagination-wrapper">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= htmlspecialchars($pageBase . ($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Önceki" aria-label="Önceki sayfa"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="<?= htmlspecialchars($pageBase . $i, ENT_QUOTES, 'UTF-8') ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= htmlspecialchars($pageBase . ($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="page-link" title="Sonraki" aria-label="Sonraki sayfa"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
