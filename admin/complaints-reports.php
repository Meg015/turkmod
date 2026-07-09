<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
if (!function_exists('topicReportReasonLabels') || !function_exists('userReportReasonLabels')) {
    try {
        $reportHelpersFile = __DIR__ . '/../includes/src/Modules/Reports/Legacy/helpers.php';
        if (is_file($reportHelpersFile)) {
            require_once $reportHelpersFile;
        }
    } catch (Throwable $e) {
        // Local fallbacks below keep the page usable if the report helpers fail to load.
    }
}

if (!function_exists('topicReportReasonLabels')) {
    function topicReportReasonLabels(): array
    {
        return [
            'broken_link' => 'Bozuk / Kırık Link',
            'outdated' => 'Eski Sürüm',
            'malware' => 'Virüslü Dosya',
            'spam' => 'Spam / Reklam',
            'inappropriate' => 'Uygunsuz İçerik',
            'wrong_category' => 'Yanlış Kategori',
            'other' => 'Diğer',
        ];
    }
}

if (!function_exists('userReportReasonLabels')) {
    function userReportReasonLabels(): array
    {
        return [
            'harassment' => 'Taciz / hakaret',
            'spam' => 'Spam',
            'impersonation' => 'Taklit hesap',
            'unsafe' => 'Güvensiz davranış',
            'inappropriate' => 'Uygunsuz profil',
            'other' => 'Diğer',
        ];
    }
}

adminRequirePermission('reports.view', 'Şikayetleri görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Şikayetler & Raporlar';

function adminComplaintsStatusLabels(): array
{
    return [
        'open' => ['Açık', 'danger', 'bi-exclamation-circle-fill'],
        'reviewing' => ['İnceleniyor', 'warning', 'bi-hourglass-split'],
        'resolved' => ['Çözüldü', 'success', 'bi-check2-circle'],
        'rejected' => ['Reddedildi', 'muted', 'bi-x-circle'],
    ];
}

function adminComplaintsUrl(string $tab, array $params = []): string
{
    $tab = $tab === 'users' ? 'users' : 'topics';
    $allowed = ['status', 'q', 'reason', 'date_from', 'date_to'];
    $query = ['tab' => $tab];
    foreach ($allowed as $key) {
        $value = $params[$key] ?? null;
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = (string) $value;
    }

    return 'complaints-reports.php?' . http_build_query($query);
}

function adminComplaintsRedirectAfterPost(string $fallbackTab, string $returnQuery = ''): void
{
    $tab = $fallbackTab === 'users' ? 'users' : 'topics';
    $params = [];
    if ($returnQuery !== '') {
        parse_str(ltrim($returnQuery, '?'), $params);
        if (($params['tab'] ?? '') === 'users' || ($params['tab'] ?? '') === 'topics') {
            $tab = (string) $params['tab'];
        }
    }

    header('Location: ' . adminComplaintsUrl($tab, is_array($params) ? $params : []));
    exit;
}

function adminComplaintsReportHelperCall(string $function, array $arguments = [], mixed $fallback = null): mixed
{
    if (!function_exists($function)) {
        return $fallback;
    }

    try {
        return $function(...$arguments);
    } catch (Throwable $e) {
        return $fallback;
    }
}

function adminComplaintsReasonLabels(string $scope): array
{
    $scope = $scope === 'users' ? 'users' : 'topics';
    $fallback = $scope === 'users'
        ? [
            'harassment' => 'Taciz / hakaret',
            'spam' => 'Spam',
            'impersonation' => 'Taklit hesap',
            'unsafe' => 'Güvensiz davranış',
            'inappropriate' => 'Uygunsuz profil',
            'other' => 'Diğer',
        ]
        : [
            'broken_link' => 'Bozuk / Kırık Link',
            'outdated' => 'Eski Sürüm',
            'malware' => 'Virüslü Dosya',
            'spam' => 'Spam / Reklam',
            'inappropriate' => 'Uygunsuz İçerik',
            'wrong_category' => 'Yanlış Kategori',
            'other' => 'Diğer',
        ];

    $labels = adminComplaintsReportHelperCall($scope === 'users' ? 'userReportReasonLabels' : 'topicReportReasonLabels', [], $fallback);
    return is_array($labels) && $labels !== [] ? $labels : $fallback;
}

function adminComplaintsEnsureReportTable(?PDO $pdo, string $scope): void
{
    adminComplaintsReportHelperCall($scope === 'users' ? 'ensureUserReportsTable' : 'ensureTopicReportsTable', [$pdo], null);
}

function adminComplaintsFetchReports(?PDO $pdo, string $scope, string $status, int $limit, array $filters): array
{
    $reports = adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReports' : 'getTopicReports', [$pdo, $status, $limit, $filters], []);
    return is_array($reports) ? $reports : [];
}

function adminComplaintsFetchReportEvents(?PDO $pdo, string $scope, array $reportIds): array
{
    $events = adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReportEventsForReports' : 'getTopicReportEventsForReports', [$pdo, $reportIds], []);
    return is_array($events) ? $events : [];
}

function adminComplaintsUpdateReportStatus(?PDO $pdo, string $scope, int $reportId, string $status, string $adminNote, ?int $actorId = null): bool
{
    $updated = adminComplaintsReportHelperCall(
        $scope === 'users' ? 'updateUserReportStatus' : 'updateTopicReportStatus',
        [$pdo, $reportId, $status, $adminNote, $actorId],
        false
    );

    return (bool) $updated;
}

function adminComplaintsStatusCounts(?PDO $pdo, string $scope): array
{
    $counts = ['open' => 0, 'reviewing' => 0, 'resolved' => 0, 'rejected' => 0];
    if (!$pdo) {
        return $counts;
    }

    $allowedScopes = ['users' => 'user_reports', 'topics' => 'topic_reports'];
    $table = $allowedScopes[$scope] ?? null;
    if ($table === null) {
        return $counts;
    }

    try {
        adminComplaintsEnsureReportTable($pdo, $scope === 'users' ? 'users' : 'topics');

        $stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status");
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        return $counts;
    }

    return $counts;
}

function adminComplaintsDate(?string $value): string
{
    $timestamp = strtotime((string) ($value ?: 'now'));
    return date('d.m.Y H:i', $timestamp ?: time());
}

function adminComplaintsStatusMeta(array $statusLabels, string $status): array
{
    return $statusLabels[$status] ?? $statusLabels['open'];
}

function adminComplaintsStatusLabel(array $statusLabels, string $status): string
{
    return (string) (adminComplaintsStatusMeta($statusLabels, $status)[0] ?? $status);
}

$statusLabels = adminComplaintsStatusLabels();
$allowedStatuses = array_keys($statusLabels);
$allowedTabs = ['topics', 'users'];
$activeTab = (string) ($_GET['tab'] ?? 'topics');
$activeTab = in_array($activeTab, $allowedTabs, true) ? $activeTab : 'topics';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('reports.manage', 'Şikayetleri yönetmek için gerekli izin hesabınıza tanımlanmamış.');
    $postScope = (string) ($_POST['report_scope'] ?? $activeTab);
    $postScope = $postScope === 'users' ? 'users' : 'topics';
    $returnQuery = (string) ($_POST['_return'] ?? '');

    try {
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Güvenlik doğrulaması başarısız.');
            adminComplaintsRedirectAfterPost($postScope, $returnQuery);
        }

        $action = (string) ($_POST['action'] ?? 'update');
        $actorId = (int) ($_SESSION['_auth_user_id'] ?? 0);

        if ($postScope === 'users') {
            if ($action === 'bulk_update') {
                $ids = array_values(array_filter(array_map('intval', (array) ($_POST['bulk_report_ids'] ?? [])), static fn (int $id): bool => $id > 0));
                $bulkStatus = (string) ($_POST['bulk_status'] ?? '');
                $bulkNote = (string) ($_POST['bulk_admin_note'] ?? '');
                $updatedCount = 0;
                if ($ids !== [] && in_array($bulkStatus, $allowedStatuses, true)) {
                    foreach ($ids as $id) {
                        if (adminComplaintsUpdateReportStatus($pdo, 'users', $id, $bulkStatus, $bulkNote, $actorId)) {
                            $updatedCount++;
                        }
                    }
                }
                $updatedCount > 0
                    ? flash('success', "{$updatedCount} kullanıcı şikayeti güncellendi.")
                    : flash('error', 'Toplu işlem uygulanamadı.');
            } else {
                $updated = adminComplaintsUpdateReportStatus(
                    $pdo,
                    'users',
                    (int) ($_POST['report_id'] ?? 0),
                    (string) ($_POST['status'] ?? ''),
                    (string) ($_POST['admin_note'] ?? ''),
                    $actorId
                );
                $updated
                    ? flash('success', 'Kullanıcı şikayeti güncellendi.')
                    : flash('error', 'Şikayet güncellenemedi.');
            }
        } else {
            if ($action === 'bulk_update') {
                $ids = array_values(array_filter(array_map('intval', (array) ($_POST['bulk_report_ids'] ?? [])), static fn (int $id): bool => $id > 0));
                $bulkStatus = (string) ($_POST['bulk_status'] ?? '');
                $bulkNote = (string) ($_POST['bulk_admin_note'] ?? '');
                $updatedCount = 0;
                if ($ids !== [] && in_array($bulkStatus, $allowedStatuses, true)) {
                    foreach ($ids as $id) {
                        if (adminComplaintsUpdateReportStatus($pdo, 'topics', $id, $bulkStatus, $bulkNote, $actorId)) {
                            $updatedCount++;
                        }
                    }
                }
                $updatedCount > 0
                    ? flash('success', "{$updatedCount} konu raporu güncellendi.")
                    : flash('error', 'Toplu işlem uygulanamadı.');
            } else {
                $updated = adminComplaintsUpdateReportStatus(
                    $pdo,
                    'topics',
                    (int) ($_POST['report_id'] ?? 0),
                    (string) ($_POST['status'] ?? ''),
                    (string) ($_POST['admin_note'] ?? ''),
                    $actorId
                );
                $updated
                    ? flash('success', 'Konu raporu güncellendi.')
                    : flash('error', 'Rapor güncellenemedi.');
            }
        }
    } catch (Throwable $e) {
        flash('error', 'İşlem sırasında bir hata oluştu: ' . safeErrorMessage($e));
    }

    adminComplaintsRedirectAfterPost($postScope, $returnQuery);
}

$topicStatusCounts = adminComplaintsStatusCounts($pdo, 'topics');
$userStatusCounts = adminComplaintsStatusCounts($pdo, 'users');
$topicActiveCount = $topicStatusCounts['open'] + $topicStatusCounts['reviewing'];
$userActiveCount = $userStatusCounts['open'] + $userStatusCounts['reviewing'];
$totalActiveCount = $topicActiveCount + $userActiveCount;

$currentStatus = (string) ($_GET['status'] ?? '');
$currentStatus = in_array($currentStatus, $allowedStatuses, true) ? $currentStatus : '';
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'reason' => trim((string) ($_GET['reason'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$returnQuery = http_build_query(array_filter([
    'tab' => $activeTab,
    'status' => $currentStatus,
    'q' => $filters['q'],
    'reason' => $filters['reason'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
], static fn ($value): bool => $value !== '' && $value !== null));

if ($activeTab === 'users') {
    $reasonLabels = adminComplaintsReasonLabels('users');
    $reports = adminComplaintsFetchReports($pdo, 'users', $currentStatus, 150, $filters);
    $reportEvents = adminComplaintsFetchReportEvents($pdo, 'users', array_column($reports, 'id'));
    $scopeTitle = 'Kullanıcı Şikayetleri';
    $scopeDesc = 'Üyelerle ilgili güvenlik, davranış ve topluluk bildirimlerini değerlendirin.';
    $scopeIcon = 'bi-person-exclamation';
    $scopeCounts = $userStatusCounts;
    $scopeTotal = array_sum($userStatusCounts);
} else {
    $reasonLabels = adminComplaintsReasonLabels('topics');
    $reports = adminComplaintsFetchReports($pdo, 'topics', $currentStatus, 150, $filters);
    $reportEvents = adminComplaintsFetchReportEvents($pdo, 'topics', array_column($reports, 'id'));
    $scopeTitle = 'Konu Raporları';
    $scopeDesc = 'Bozuk link, eski sürüm, güvenlik ve içerik bildirimlerini inceleyin.';
    $scopeIcon = 'bi-flag';
    $scopeCounts = $topicStatusCounts;
    $scopeTotal = array_sum($topicStatusCounts);
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$currentStatusLabel = $currentStatus !== ''
    ? adminComplaintsStatusLabel($statusLabels, $currentStatus)
    : 'Tüm kayıtlar';

require_once __DIR__ . '/header.php';
?>
<div class="complaints-page">
    <?php if ($successMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="complaints-hero-kicker"><i class="bi bi-shield-exclamation"></i> Moderasyon merkezi</span>
            <h2>Şikayetler & Raporlar</h2>
            <p>Siteyle ilgili konu raporlarını ve kullanıcı şikayetlerini tek yönetim kuyruğunda takip edin.</p>
        </div>
        <div class="ui-admin-page-hero-actions">
            <span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-inbox"></i> <?= number_format($totalActiveCount, 0, ',', '.') ?> aktif iş</span>
        </div>
    </section>

    <nav class="complaints-tabs" aria-label="Rapor sekmeleri">
        <a class="complaints-tab <?= $activeTab === 'topics' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(adminComplaintsUrl('topics')) ?>" <?= $activeTab === 'topics' ? 'aria-current="page"' : '' ?>>
            <span class="complaints-tab-main">
                <span class="complaints-tab-icon"><i class="bi bi-flag"></i></span>
                <span>
                    <span class="complaints-tab-title">Konu Raporları</span>
                    <span class="complaints-tab-desc">İçerik ve bağlantı bildirimleri</span>
                </span>
            </span>
            <span class="ui-admin-badge <?= $topicActiveCount > 0 ? 'ui-admin-badge-danger' : 'ui-admin-badge-muted' ?>"><?= number_format($topicActiveCount, 0, ',', '.') ?></span>
        </a>
        <a class="complaints-tab <?= $activeTab === 'users' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(adminComplaintsUrl('users')) ?>" <?= $activeTab === 'users' ? 'aria-current="page"' : '' ?>>
            <span class="complaints-tab-main">
                <span class="complaints-tab-icon"><i class="bi bi-person-exclamation"></i></span>
                <span>
                    <span class="complaints-tab-title">Kullanıcı Şikayetleri</span>
                    <span class="complaints-tab-desc">Üye davranışı ve güvenlik bildirimleri</span>
                </span>
            </span>
            <span class="ui-admin-badge <?= $userActiveCount > 0 ? 'ui-admin-badge-danger' : 'ui-admin-badge-muted' ?>"><?= number_format($userActiveCount, 0, ',', '.') ?></span>
        </a>
    </nav>

    <section class="admin-stat-grid complaints-summary-row ui-grid" aria-label="<?= htmlspecialchars($scopeTitle) ?> özeti">
        <a href="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="admin-stat-card stat-info complaints-summary-card<?= $currentStatus === '' ? ' is-active' : '' ?> ui-card">
            <div class="stat-icon"><i class="bi <?= htmlspecialchars($scopeIcon) ?>"></i></div>
            <div class="stat-content">
                <span class="stat-label">Tüm kayıtlar</span>
                <span class="stat-value"><?= number_format($scopeTotal, 0, ',', '.') ?></span>
            </div>
        </a>
        <?php foreach ($statusLabels as $key => $meta): ?>
            <a href="<?= htmlspecialchars(adminComplaintsUrl($activeTab, ['status' => $key])) ?>" class="admin-stat-card stat-<?= htmlspecialchars($meta[1] === 'muted' ? 'info' : $meta[1]) ?> complaints-summary-card<?= $currentStatus === $key ? ' is-active' : '' ?> ui-card">
                <div class="stat-icon"><i class="bi <?= htmlspecialchars($meta[2]) ?>"></i></div>
                <div class="stat-content">
                    <span class="stat-label"><?= htmlspecialchars($meta[0]) ?></span>
                    <span class="stat-value"><?= number_format((int) ($scopeCounts[$key] ?? 0), 0, ',', '.') ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="admin-card complaints-board ui-panel">
        <div class="complaints-toolbar">
            <div class="complaints-toolbar-title">
                <div>
                    <strong><?= htmlspecialchars($scopeTitle) ?> · <?= htmlspecialchars($currentStatusLabel) ?></strong>
                    <span><?= number_format(count($reports), 0, ',', '.') ?> kayıt listeleniyor</span>
                </div>
                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars($scopeIcon) ?>"></i><?= htmlspecialchars($scopeDesc) ?></span>
            </div>

            <form method="get" class="complaints-filter">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <div class="complaints-filter-primary">
                    <div>
                        <label class="ui-admin-form-label" for="report-search">Ara</label>
                        <input id="report-search" name="q" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="<?= $activeTab === 'users' ? 'Kullanıcı, e-posta veya detay' : 'Konu, kullanıcı veya detay' ?>">
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-status-filter">Durum</label>
                        <select id="report-status-filter" name="status" class="ui-admin-form-select">
                            <option value="">Tüm kayıtlar</option>
                            <?php foreach ($statusLabels as $key => $meta): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-reason-filter">Sebep</label>
                        <select id="report-reason-filter" name="reason" class="ui-admin-form-select">
                            <option value="">Tüm sebepler</option>
                            <?php foreach ($reasonLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $filters['reason'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="complaints-filter-actions">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-sm complaints-filter-submit"><i class="bi bi-search"></i> Filtrele</button>
                        <a href="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Temizle"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <details class="complaints-filter-advanced" <?= ($filters['date_from'] !== '' || $filters['date_to'] !== '') ? 'open' : '' ?>>
                    <summary><i class="bi bi-calendar-range"></i> Tarih aralığı</summary>
                    <div class="complaints-filter-extra">
                        <div>
                            <label class="ui-admin-form-label" for="report-date-from">Başlangıç</label>
                            <input id="report-date-from" name="date_from" type="date" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label" for="report-date-to">Bitiş</label>
                            <input id="report-date-to" name="date_to" type="date" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                    </div>
                </details>
            </form>
        </div>

        <?php if (empty($reports)): ?>
            <div class="complaints-empty">
                <div><i class="bi <?= htmlspecialchars($scopeIcon) ?>"></i><strong>Bu filtrede kayıt yok.</strong><p>Yeni bildirimler geldiğinde burada listelenecek.</p></div>
            </div>
        <?php else: ?>
            <form id="bulkReportsForm" method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="complaints-bulk-bar" data-admin-confirm="Seçili kayıtlar güncellensin mi?" data-admin-confirm-title="Raporlar güncellensin mi?" data-admin-confirm-ok="Güncelle" data-admin-confirm-tone="warning">
                <?= csrf_field() ?>
                <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                <input type="hidden" name="action" value="bulk_update">
                <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                <div class="complaints-bulk-meta">
                    <label class="ui-admin-check-inline">
                        <input type="checkbox" id="selectAllReports">
                        <span>Tümünü seç</span>
                    </label>
                    <span><i class="bi bi-check2-square"></i> <span id="selectedReportCount">0</span> seçili</span>
                </div>
                <div class="complaints-bulk-controls">
                    <select name="bulk_status" class="ui-admin-form-select" required>
                        <option value="">Toplu durum seç</option>
                        <?php foreach ($statusLabels as $key => $meta): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="bulk_admin_note" class="ui-admin-form-control" placeholder="Toplu admin notu">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-lightning-charge"></i> Uygula</button>
                </div>
            </form>

            <div class="complaints-list">
                <?php foreach ($reports as $report): ?>
                    <?php
                    $reportId = (int) $report['id'];
                    $statusKey = (string) ($report['status'] ?? 'open');
                    $statusMeta = adminComplaintsStatusMeta($statusLabels, $statusKey);
                    $events = $reportEvents[$reportId] ?? [];
                    $modalId = $activeTab . '-report-detail-' . $reportId;
                    $details = (string) ($report['details'] ?? '');
                    $adminNote = (string) ($report['admin_note'] ?? '');
                    ?>
                    <article class="complaints-item">
                        <div class="complaints-select">
                            <input type="checkbox" name="bulk_report_ids[]" value="<?= $reportId ?>" class="complaints-row-checkbox" form="bulkReportsForm">
                        </div>
                        <div class="complaints-main">
                            <div class="complaints-title-row">
                                <div>
                                    <?php if ($activeTab === 'users'): ?>
                                        <span class="complaints-title"><?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?></span>
                                        <span class="complaints-subtitle"><?= htmlspecialchars((string) ($report['reported_user_email'] ?? '')) ?></span>
                                        <?php if ((int) ($report['reported_user_id'] ?? 0) > 0): ?>
                                            <a class="complaints-link" href="users.php?tab=users&amp;edit=<?= (int) $report['reported_user_id'] ?>"><i class="bi bi-person-lines-fill"></i> Kullanıcıyı aç</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="complaints-title"><?= htmlspecialchars((string) ($report['topic_title'] ?? 'Silinmiş konu')) ?></span>
                                        <?php if (!empty($report['topic_slug'])): ?>
                                            <a class="complaints-link" href="<?= topicUrl((string) $report['topic_slug'], (int) ($report['topic_id'] ?? 0)) ?>" target="_blank" rel="noopener">Konuyu aç <i class="bi bi-box-arrow-up-right"></i></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($statusMeta[1]) ?>"><i class="bi <?= htmlspecialchars($statusMeta[2]) ?>"></i><?= htmlspecialchars($statusMeta[0]) ?></span>
                            </div>

                            <div class="complaints-meta-grid">
                                <span class="complaints-meta-pill"><i class="bi <?= $activeTab === 'users' ? 'bi-person-exclamation' : 'bi-flag' ?>"></i><strong><?= htmlspecialchars($reasonLabels[$report['reason']] ?? (string) $report['reason']) ?></strong></span>
                                <?php if ($activeTab === 'users'): ?>
                                    <span class="complaints-meta-pill"><i class="bi bi-person"></i>Şikayet eden: <strong><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?></strong></span>
                                <?php else: ?>
                                    <span class="complaints-meta-pill"><i class="bi bi-person"></i><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?></span>
                                <?php endif; ?>
                                <span class="complaints-meta-pill"><i class="bi bi-clock"></i><?= htmlspecialchars(adminComplaintsDate((string) ($report['created_at'] ?? 'now'))) ?></span>
                                <?php if ($events !== []): ?>
                                    <span class="complaints-meta-pill"><i class="bi bi-clock-history"></i><?= count($events) ?> geçmiş</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($details !== ''): ?>
                                <p class="complaints-detail is-preview"><?= nl2br(htmlspecialchars($details)) ?></p>
                            <?php endif; ?>

                            <div class="complaints-row-actions">
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-complaints-modal-open="<?= htmlspecialchars($modalId) ?>"><i class="bi bi-card-text"></i> Detay</button>
                            </div>
                        </div>

                        <aside class="complaints-actions" aria-label="Rapor işlemleri">
                            <div class="complaints-actions-title">
                                <span>İşlem paneli</span>
                                <span class="ui-admin-badge ui-admin-badge-muted">#<?= $reportId ?></span>
                            </div>
                            <div class="complaints-quick-actions" aria-label="Hızlı işlem">
                                <?php foreach (['reviewing', 'resolved', 'rejected'] as $quickStatus): ?>
                                    <?php if ($statusKey !== $quickStatus): ?>
                                    <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                                        <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                                        <input type="hidden" name="action" value="quick_update">
                                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($quickStatus) ?>">
                                        <input type="hidden" name="admin_note" value="<?= htmlspecialchars($adminNote) ?>">
                                        <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="<?= htmlspecialchars(adminComplaintsStatusLabel($statusLabels, $quickStatus)) ?>"><i class="bi <?= htmlspecialchars($statusLabels[$quickStatus][2]) ?>"></i></button>
                                    </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="complaints-admin-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                                <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                <select name="status" class="ui-admin-form-select" aria-label="Rapor durumu">
                                    <?php foreach ($statusLabels as $key => $meta): ?>
                                        <option value="<?= htmlspecialchars($key) ?>" <?= $statusKey === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta[0]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <textarea name="admin_note" class="ui-admin-form-control" placeholder="Admin notu"><?= htmlspecialchars($adminNote) ?></textarea>
                                <button type="submit" class="ui-admin-btn ui-admin-btn-sm complaints-save-btn"><i class="bi bi-save"></i> Kaydet</button>
                            </form>
                        </aside>
                    </article>

                    <div class="complaints-modal ui-admin-modal-overlay" id="<?= htmlspecialchars($modalId) ?>" hidden aria-hidden="true">
                        <div class="complaints-modal-backdrop" data-complaints-modal-close></div>
                        <div class="complaints-modal-dialog ui-admin-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                            <div class="complaints-modal-head">
                                <div>
                                    <h3 id="<?= htmlspecialchars($modalId) ?>-title">
                                        <?php if ($activeTab === 'users'): ?>
                                            <?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string) ($report['topic_title'] ?? 'Silinmiş konu')) ?>
                                        <?php endif; ?>
                                    </h3>
                                    <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($statusMeta[1]) ?>"><i class="bi <?= htmlspecialchars($statusMeta[2]) ?>"></i><?= htmlspecialchars($statusMeta[0]) ?></span>
                                </div>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-complaints-modal-close><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="complaints-modal-body">
                                <div class="complaints-meta-grid">
                                    <span class="complaints-meta-pill"><i class="bi <?= $activeTab === 'users' ? 'bi-person-exclamation' : 'bi-flag' ?>"></i><strong><?= htmlspecialchars($reasonLabels[$report['reason']] ?? (string) $report['reason']) ?></strong></span>
                                    <span class="complaints-meta-pill"><i class="bi bi-person"></i><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?></span>
                                    <span class="complaints-meta-pill"><i class="bi bi-clock"></i><?= htmlspecialchars(adminComplaintsDate((string) ($report['created_at'] ?? 'now'))) ?></span>
                                </div>
                                <?php if ($activeTab === 'users'): ?>
                                    <div>
                                        <strong>Şikayet edilen kullanıcı</strong>
                                        <p class="complaints-detail"><?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?><br><?= htmlspecialchars((string) ($report['reported_user_email'] ?? '')) ?></p>
                                    </div>
                                    <div>
                                        <strong>Şikayet eden</strong>
                                        <p class="complaints-detail"><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?><br><?= htmlspecialchars((string) ($report['reporter_email'] ?? '')) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong>Rapor detayı</strong>
                                    <p class="complaints-detail"><?= nl2br(htmlspecialchars($details !== '' ? $details : 'Detay girilmemiş.')) ?></p>
                                </div>
                                <div>
                                    <strong>Admin notu</strong>
                                    <p class="complaints-detail"><?= nl2br(htmlspecialchars($adminNote !== '' ? $adminNote : 'Not yok.')) ?></p>
                                </div>
                                <div>
                                    <strong>Geçmiş</strong>
                                    <div class="complaints-history">
                                        <?php if ($events === []): ?>
                                            <div class="complaints-history-item"><span>Henüz geçmiş kaydı yok.</span></div>
                                        <?php else: ?>
                                            <?php foreach ($events as $event): ?>
                                                <div class="complaints-history-item">
                                                    <strong><?= htmlspecialchars((string) ($event['actor_name'] ?? 'Sistem')) ?> · <?= htmlspecialchars((string) $event['event_type']) ?></strong>
                                                    <span>
                                                        <?= htmlspecialchars(adminComplaintsDate((string) ($event['created_at'] ?? 'now'))) ?>
                                                        <?php if (!empty($event['new_status'])): ?>
                                                            · <?= htmlspecialchars(adminComplaintsStatusLabel($statusLabels, (string) ($event['old_status'] ?? 'open'))) ?> → <?= htmlspecialchars(adminComplaintsStatusLabel($statusLabels, (string) $event['new_status'])) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if (!empty($event['note'])): ?><p><?= nl2br(htmlspecialchars((string) $event['note'])) ?></p><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="<?= asset_url('admin/assets/complaints-reports-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
