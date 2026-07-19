<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Modules/Reports/Support/helpers.php';

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
    $tab = in_array($tab, ['topics', 'users', 'reasons'], true) ? $tab : 'topics';
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
    $tab = in_array($fallbackTab, ['topics', 'users', 'reasons'], true) ? $fallbackTab : 'topics';
    $params = [];
    if ($returnQuery !== '') {
        parse_str(ltrim($returnQuery, '?'), $params);
        if (in_array(($params['tab'] ?? ''), ['topics', 'users', 'reasons'], true)) {
            $tab = (string) $params['tab'];
        }
    }

    header('Location: ' . adminComplaintsUrl($tab, is_array($params) ? $params : []));
    exit;
}

function adminComplaintsReportHelperCall(string $function, array $arguments = []): mixed
{
    if (!is_callable($function)) {
        throw new LogicException('Report service adapter is unavailable: ' . $function);
    }
    return $function(...$arguments);
}

function adminComplaintsReasonLabels(string $scope): array
{
    return $scope === 'users' ? userReportReasonLabels() : topicReportReasonLabels();
}

/** @return array<string,string> */
function adminComplaintsNormalizeTopicReasons(array $keys, array $labels): array
{
    $reasons = [];
    $total = max(count($keys), count($labels));
    for ($index = 0; $index < $total && count($reasons) < 20; $index++) {
        $label = trim((string) ($labels[$index] ?? ''));
        $key = strtolower(trim((string) ($keys[$index] ?? '')));
        if ($label === '') {
            continue;
        }
        if ($key === '') {
            $key = function_exists('slugify') ? slugify($label) : $label;
            $key = str_replace('-', '_', strtolower((string) $key));
        }
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');
        if (preg_match('/^[a-z0-9_]{2,40}$/', $key) !== 1 || isset($reasons[$key])) {
            throw new RuntimeException('Her rapor nedeni benzersiz, 2-40 karakterlik bir anahtara sahip olmalıdır.');
        }
        $reasons[$key] = mb_substr($label, 0, 80, 'UTF-8');
    }

    if ($reasons === []) {
        throw new RuntimeException('En az bir rapor nedeni tanımlayın.');
    }

    return $reasons;
}

function adminComplaintsEnsureReportTable(?PDO $pdo, string $scope): void
{
    adminComplaintsReportHelperCall($scope === 'users' ? 'ensureUserReportsTable' : 'ensureTopicReportsTable', [$pdo]);
}

function adminComplaintsFetchReports(?PDO $pdo, string $scope, string $status, int $limit, array $filters): array
{
    $reports = adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReports' : 'getTopicReports', [$pdo, $status, $limit, $filters]);
    return is_array($reports) ? $reports : [];
}

function adminComplaintsFetchReportEvents(?PDO $pdo, string $scope, array $reportIds): array
{
    $events = adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReportEventsForReports' : 'getTopicReportEventsForReports', [$pdo, $reportIds]);
    return is_array($events) ? $events : [];
}

function adminComplaintsUpdateReportStatus(?PDO $pdo, string $scope, int $reportId, string $status, string $adminNote, ?int $actorId = null): bool
{
    $updated = adminComplaintsReportHelperCall(
        $scope === 'users' ? 'updateUserReportStatus' : 'updateTopicReportStatus',
        [$pdo, $reportId, $status, $adminNote, $actorId]
    );

    return (bool) $updated;
}

/** @return array{reports:int,events:int,notifications:int,activities:int,user_activities:int} */
function adminComplaintsDeleteAllTopicReports(?PDO $pdo, ?int $actorId = null): array
{
    $result = adminComplaintsReportHelperCall('deleteAllTopicReports', [$pdo, $actorId]);
    if (!is_array($result)) {
        throw new RuntimeException('Konu raporları silinemedi.');
    }

    return [
        'reports' => (int) ($result['reports'] ?? 0),
        'events' => (int) ($result['events'] ?? 0),
        'notifications' => (int) ($result['notifications'] ?? 0),
        'activities' => (int) ($result['activities'] ?? 0),
        'user_activities' => (int) ($result['user_activities'] ?? 0),
    ];
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
$allowedTabs = ['topics', 'users', 'reasons'];
$activeTab = (string) ($_GET['tab'] ?? 'topics');
$activeTab = in_array($activeTab, $allowedTabs, true) ? $activeTab : 'topics';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('reports.manage', 'Şikayetleri yönetmek için gerekli izin hesabınıza tanımlanmamış.');
    $postScope = (string) ($_POST['report_scope'] ?? $activeTab);
    $postScope = in_array($postScope, ['topics', 'users', 'reasons'], true) ? $postScope : 'topics';
    $returnQuery = (string) ($_POST['_return'] ?? '');

    try {
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Güvenlik doğrulaması başarısız.');
            adminComplaintsRedirectAfterPost($postScope, $returnQuery);
        }

        $action = (string) ($_POST['action'] ?? 'update');
        $actorId = (int) ($_SESSION['_auth_user_id'] ?? 0);

        if ($action === 'save_topic_reasons') {
            $topicReasons = adminComplaintsNormalizeTopicReasons(
                array_values((array) ($_POST['reason_keys'] ?? [])),
                array_values((array) ($_POST['reason_labels'] ?? []))
            );
            saveAdminSettings($pdo, [
                '_sections' => 'reports',
                'topic_report_reasons_json' => json_encode($topicReasons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            flash('success', 'Konu rapor nedenleri güncellendi.');
            adminComplaintsRedirectAfterPost('reasons');
        }

        if ($action === 'delete_all_topic_reports') {
            $deleted = adminComplaintsDeleteAllTopicReports($pdo, $actorId);
            flash(
                'success',
                $deleted['reports'] > 0
                    ? sprintf(
                        '%d konu raporu; %d geçmiş, %d bildirim ve %d aktivite kaydıyla birlikte kalıcı olarak silindi.',
                        $deleted['reports'],
                        $deleted['events'],
                        $deleted['notifications'],
                        $deleted['activities'] + $deleted['user_activities']
                    )
                    : 'Silinecek konu raporu bulunamadı.'
            );
            adminComplaintsRedirectAfterPost('topics');
        }

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
    $scopeIcon = 'bi-person-exclamation';
    $scopeCounts = $userStatusCounts;
    $scopeTotal = array_sum($userStatusCounts);
} elseif ($activeTab === 'topics') {
    $reasonLabels = adminComplaintsReasonLabels('topics');
    $reports = adminComplaintsFetchReports($pdo, 'topics', $currentStatus, 150, $filters);
    $reportEvents = adminComplaintsFetchReportEvents($pdo, 'topics', array_column($reports, 'id'));
    $scopeTitle = 'Konu Raporları';
    $scopeIcon = 'bi-flag';
    $scopeCounts = $topicStatusCounts;
    $scopeTotal = array_sum($topicStatusCounts);
} else {
    $reasonLabels = adminComplaintsReasonLabels('topics');
    $reports = [];
    $reportEvents = [];
    $scopeTitle = 'Rapor Nedenleri';
    $scopeIcon = 'bi-list-check';
    $scopeCounts = $topicStatusCounts;
    $scopeTotal = count($reasonLabels);
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$currentStatusLabel = $currentStatus !== ''
    ? adminComplaintsStatusLabel($statusLabels, $currentStatus)
    : 'Tüm kayıtlar';

require_once __DIR__ . '/header.php';
?>
<div class="complaints-page">
    <?= adminRenderFlashAlerts($successMsg, $errorMsg) ?>

    <?= adminRenderPageHero('bi-shield-exclamation', 'Moderasyon merkezi', 'Şikayetler & Raporlar', 'Siteyle ilgili konu raporlarını ve kullanıcı şikayetlerini tek yönetim kuyruğunda takip edin.', [], [
        'actions_html' => '<span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-inbox"></i> ' . number_format($totalActiveCount, 0, ',', '.') . ' aktif iş</span>',
    ]) ?>

    <?= adminRenderTabBar([
        'topics' => [
            'href' => adminComplaintsUrl('topics'),
            'icon' => 'bi-flag',
            'label' => 'Konu Raporları',
            'description' => 'İçerik ve bağlantı bildirimleri',
            'badge' => number_format($topicActiveCount, 0, ',', '.'),
            'badge_tone' => $topicActiveCount > 0 ? 'danger' : 'muted',
        ],
        'users' => [
            'href' => adminComplaintsUrl('users'),
            'icon' => 'bi-person-exclamation',
            'label' => 'Kullanıcı Şikayetleri',
            'description' => 'Üye davranışı ve güvenlik bildirimleri',
            'badge' => number_format($userActiveCount, 0, ',', '.'),
            'badge_tone' => $userActiveCount > 0 ? 'danger' : 'muted',
        ],
        'reasons' => [
            'href' => adminComplaintsUrl('reasons'),
            'icon' => 'bi-list-check',
            'label' => 'Rapor Nedenleri',
            'description' => 'Public rapor seçeneklerini düzenle',
            'badge' => number_format(count(adminComplaintsReasonLabels('topics')), 0, ',', '.'),
            'badge_tone' => 'muted',
        ],
    ], $activeTab, [
        'class' => 'complaints-tabs',
        'link_class' => 'complaints-tab',
        'active_class' => 'is-active',
        'aria_label' => 'Rapor sekmeleri',
        'leading_class' => 'complaints-tab-main',
        'icon_wrap_class' => 'complaints-tab-icon',
        'title_class' => 'complaints-tab-title',
        'description_class' => 'complaints-tab-desc',
        'badge_class' => 'complaints-tab-badge',
    ]) ?>

    <?php if ($activeTab === 'reasons'): ?>
    <?= adminRenderPanelOpen([
        'class' => 'complaints-reasons-panel',
        'header_class' => 'complaints-reasons-head',
        'body_class' => 'ui-admin-card-body-flush',
        'header_html' => '
            <div>
                <span class="complaints-hero-kicker"><i class="bi bi-sliders"></i> Public raporlama ayarı</span>
                <h3>Konu rapor nedenleri</h3>
                <p>Buradaki sıralama ve başlıklar public konu raporlama penceresine doğrudan yansır. Mevcut anahtarlar geçmiş raporlarla uyum için sabit tutulur.</p>
            </div>
            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-report-reason-add><i class="bi bi-plus-lg"></i> Yeni neden</button>',
    ]) ?>
        <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl('reasons')) ?>" class="complaints-reasons-form" data-report-reasons-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_topic_reasons">
            <input type="hidden" name="report_scope" value="reasons">
            <div class="complaints-reasons-list" data-report-reasons-list>
                <?php foreach ($reasonLabels as $reasonKey => $reasonLabel): ?>
                <div class="complaints-reason-row" data-report-reason-row>
                    <span class="complaints-reason-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                    <div class="complaints-reason-key">
                        <label class="ui-admin-form-label">Sistem anahtarı</label>
                        <input class="ui-admin-form-control" name="reason_keys[]" value="<?= htmlspecialchars((string) $reasonKey) ?>" readonly aria-readonly="true">
                    </div>
                    <div class="complaints-reason-label">
                        <label class="ui-admin-form-label">Public başlık</label>
                        <input class="ui-admin-form-control" name="reason_labels[]" value="<?= htmlspecialchars((string) $reasonLabel) ?>" maxlength="80" required>
                    </div>
                    <div class="complaints-reason-actions">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-report-reason-up title="Yukarı taşı"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-report-reason-down title="Aşağı taşı"><i class="bi bi-arrow-down"></i></button>
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm complaints-reason-remove" data-report-reason-remove title="Nedeni kaldır"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="complaints-reasons-foot">
                <span><i class="bi bi-info-circle"></i> En az bir, en fazla 20 neden tanımlanabilir.</span>
                <button type="submit" class="ui-admin-btn complaints-save-btn"><i class="bi bi-save"></i> Rapor nedenlerini kaydet</button>
            </div>
        </form>
        <template data-report-reason-template>
            <div class="complaints-reason-row is-new" data-report-reason-row>
                <span class="complaints-reason-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                <div class="complaints-reason-key">
                    <label class="ui-admin-form-label">Sistem anahtarı</label>
                    <input class="ui-admin-form-control" name="reason_keys[]" placeholder="ornek_neden" maxlength="40" pattern="[a-z0-9_]{2,40}" required>
                </div>
                <div class="complaints-reason-label">
                    <label class="ui-admin-form-label">Public başlık</label>
                    <input class="ui-admin-form-control" name="reason_labels[]" placeholder="Örn. Yanıltıcı bilgi" maxlength="80" required>
                </div>
                <div class="complaints-reason-actions">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-report-reason-up title="Yukarı taşı"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-report-reason-down title="Aşağı taşı"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm complaints-reason-remove" data-report-reason-remove title="Nedeni kaldır"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
        </template>
    <?= adminRenderPanelClose() ?>
</div>
<script src="<?= asset_url('admin/assets/complaints-reports-page.js', $baseUri) ?>" defer></script>
<?php require_once __DIR__ . '/footer.php'; exit; ?>
    <?php endif; ?>

    <?php
    $complaintsSummaryCards = [[
        'href' => adminComplaintsUrl($activeTab),
        'tone' => 'info',
        'icon' => $scopeIcon,
        'label' => 'Tüm kayıtlar',
        'value' => number_format($scopeTotal, 0, ',', '.'),
        'class' => 'complaints-summary-card' . ($currentStatus === '' ? ' is-active' : ''),
    ]];
    foreach ($statusLabels as $key => $meta) {
        $complaintsSummaryCards[] = [
            'href' => adminComplaintsUrl($activeTab, ['status' => $key]),
            'tone' => $meta[1] === 'muted' ? 'info' : (string) $meta[1],
            'icon' => (string) $meta[2],
            'label' => (string) $meta[0],
            'value' => number_format((int) ($scopeCounts[$key] ?? 0), 0, ',', '.'),
            'class' => 'complaints-summary-card' . ($currentStatus === $key ? ' is-active' : ''),
        ];
    }
    echo adminRenderStatCards($complaintsSummaryCards, [
        'class' => 'complaints-summary-row',
        'aria_label' => $scopeTitle . ' özeti',
    ]);
    ?>

    <?= adminRenderPanelOpen(['class' => 'complaints-board', 'body_class' => 'ui-admin-card-body-flush']) ?>
        <div class="complaints-toolbar">
            <div class="complaints-toolbar-title">
                <div>
                    <strong><?= htmlspecialchars($scopeTitle) ?> · <?= htmlspecialchars($currentStatusLabel) ?></strong>
                    <span><?= number_format(count($reports), 0, ',', '.') ?> kayıt listeleniyor</span>
                </div>
                <?php if ($activeTab === 'topics'): ?>
                <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl('topics')) ?>" class="ui-admin-inline-form complaints-delete-all-form"<?= adminConfirmAttrs(['message' => 'Tüm konu raporları; geçmişleri, rapora bağlı bildirimleri ve aktivite kayıtlarıyla birlikte kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?', 'title' => 'Tüm konu raporları silinsin mi?', 'ok' => 'Tümünü Kalıcı Sil', 'tone' => 'danger']) ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_all_topic_reports">
                    <input type="hidden" name="report_scope" value="topics">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm" <?= $scopeTotal <= 0 ? 'disabled' : '' ?>><i class="bi bi-trash3"></i> Tümünü Sil</button>
                </form>
                <?php endif; ?>
            </div>

            <form method="get" class="complaints-filter admin-filter-form">
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
            <?= adminRenderEmptyState([
                'icon' => $scopeIcon,
                'tone' => 'info',
                'title' => 'Bu filtrede kayıt yok.',
                'description' => 'Yeni bildirimler geldiğinde burada listelenecek.',
                'class' => 'complaints-empty',
            ]) ?>
        <?php else: ?>
            <form id="bulkReportsForm" method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="complaints-bulk-bar admin-bulk-action-bar"<?= adminConfirmAttrs(['message' => 'Seçili kayıtlar güncellensin mi?', 'title' => 'Raporlar güncellensin mi?', 'ok' => 'Güncelle', 'tone' => 'warning']) ?>>
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
    <?= adminRenderPanelClose() ?>
</div>

<script src="<?= asset_url('admin/assets/complaints-reports-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
