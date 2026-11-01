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
        if ($value === null || ($value === '' && $key !== 'status')) {
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
            $key = slugify($label);
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
    return adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReports' : 'getTopicReports', [$pdo, $status, $limit, $filters]);
}

function adminComplaintsFetchReportEvents(?PDO $pdo, string $scope, array $reportIds): array
{
    return adminComplaintsReportHelperCall($scope === 'users' ? 'getUserReportEventsForReports' : 'getTopicReportEventsForReports', [$pdo, $reportIds]);
}

function adminComplaintsUpdateReportStatus(?PDO $pdo, string $scope, int $reportId, string $status, string $adminNote, ?int $actorId = null): bool
{
    $updated = adminComplaintsReportHelperCall(
        $scope === 'users' ? 'updateUserReportStatus' : 'updateTopicReportStatus',
        [$pdo, $reportId, $status, $adminNote, $actorId]
    );

    return (bool) $updated;
}

function adminComplaintsDeleteReport(?PDO $pdo, string $scope, int $reportId, ?int $actorId = null): bool
{
    $deleted = adminComplaintsReportHelperCall(
        $scope === 'users' ? 'deleteUserReport' : 'deleteTopicReport',
        [$pdo, $reportId, $actorId]
    );

    return (bool) $deleted;
}

function adminComplaintsSelectionExists(PDO $pdo, string $scope, array $reportIds): bool
{
    $table = match ($scope) {
        'topics' => 'topic_reports',
        'users' => 'user_reports',
        default => throw new InvalidArgumentException('Geçersiz rapor türü.'),
    };
    $ids = \App\Modules\Reports\Services\ReportDeletionService::normalizeIds($reportIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE id IN ({$placeholders}) LIMIT 1");
    $stmt->execute($ids);
    return (bool) $stmt->fetchColumn();
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
        throw new RuntimeException('Rapor sayıları alınamadı: veritabanı bağlantısı yok.');
    }

    $table = match ($scope) {
        'users' => 'user_reports',
        'topics' => 'topic_reports',
        default => throw new InvalidArgumentException('Geçersiz rapor türü.'),
    };
    adminComplaintsEnsureReportTable($pdo, $scope);
    $stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string) $row['status'];
        if (array_key_exists($status, $counts)) {
            $counts[$status] = (int) $row['total'];
        }
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
    $postScope = is_string($_POST['report_scope'] ?? null) ? $_POST['report_scope'] : '';
    $returnQuery = is_string($_POST['_return'] ?? null) ? $_POST['_return'] : '';

    try {
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Güvenlik doğrulaması başarısız.');
            adminComplaintsRedirectAfterPost($postScope, $returnQuery);
        }

        if (!in_array($postScope, ['topics', 'users', 'reasons'], true)) {
            throw new InvalidArgumentException('Geçersiz rapor türü.');
        }
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $actorId = (int) ($_SESSION['_auth_user_id'] ?? 0);

        if ($action === 'save_topic_reasons') {
            if ($postScope !== 'reasons') {
                throw new InvalidArgumentException('Geçersiz rapor ayarı işlemi.');
            }
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
            if ($postScope !== 'topics') {
                throw new InvalidArgumentException('Geçersiz rapor silme işlemi.');
            }
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

        if (!in_array($postScope, ['topics', 'users'], true)) {
            throw new InvalidArgumentException('Geçersiz rapor türü.');
        }
        $reportLabel = $postScope === 'users' ? 'kullanıcı şikayeti' : 'konu raporu';
        if ($action === 'bulk_action') {
            $rawIds = $_POST['bulk_report_ids'] ?? [];
            if (!is_array($rawIds)) {
                throw new InvalidArgumentException('Seçilen rapor kimlikleri geçersiz.');
            }
            $ids = \App\Modules\Reports\Services\ReportDeletionService::normalizeIds($rawIds);
            $operation = $_POST['bulk_operation'] ?? '';
            if ($operation === 'delete') {
                $deletedCount = deleteSelectedReports($pdo, $postScope, $ids, $actorId);
                $deletedCount > 0
                    ? flash('success', "{$deletedCount} {$reportLabel} ve işlem geçmişi silindi.")
                    : flash('error', 'Seçilen raporlar bulunamadı; hiçbir kayıt silinmedi.');
            } elseif (is_string($operation) && in_array($operation, $allowedStatuses, true)) {
                $bulkNote = $_POST['bulk_admin_note'] ?? '';
                if (!is_string($bulkNote)) {
                    throw new InvalidArgumentException('Yönetici notu geçersiz.');
                }
                $updatedCount = updateSelectedReportsStatus($pdo, $postScope, $ids, $operation, $bulkNote, $actorId);
                if ($updatedCount > 0) {
                    flash('success', "{$updatedCount} {$reportLabel} güncellendi.");
                } elseif (adminComplaintsSelectionExists($pdo, $postScope, $ids)) {
                    flash('success', 'Seçilen raporlar zaten güncel; değişiklik yapılmadı.');
                } else {
                    flash('error', 'Seçilen raporlar bulunamadı.');
                }
            } else {
                throw new InvalidArgumentException('Geçerli bir toplu işlem seçin.');
            }
        } elseif ($action === 'delete') {
            $reportId = \App\Modules\Reports\Services\ReportDeletionService::normalizeIds([$_POST['report_id'] ?? null])[0];
            $deleted = adminComplaintsDeleteReport($pdo, $postScope, $reportId, $actorId);
            $deleted
                ? flash('success', $postScope === 'users' ? 'Kullanıcı şikayeti silindi.' : 'Konu raporu silindi.')
                : flash('error', 'Rapor silinemedi.');
        } elseif (in_array($action, ['update', 'quick_update'], true)) {
            $reportId = \App\Modules\Reports\Services\ReportDeletionService::normalizeIds([$_POST['report_id'] ?? null])[0];
            $status = $_POST['status'] ?? '';
            $adminNote = $_POST['admin_note'] ?? '';
            if (!is_string($status) || !in_array($status, $allowedStatuses, true) || !is_string($adminNote)) {
                throw new InvalidArgumentException('Rapor durumu veya yönetici notu geçersiz.');
            }
            $updated = adminComplaintsUpdateReportStatus(
                $pdo,
                $postScope,
                $reportId,
                $status,
                $adminNote,
                $actorId
            );
            if ($updated) {
                flash('success', $postScope === 'users' ? 'Kullanıcı şikayeti güncellendi.' : 'Konu raporu güncellendi.');
            } elseif (adminComplaintsSelectionExists($pdo, $postScope, [$reportId])) {
                flash('success', 'Rapor zaten güncel; değişiklik yapılmadı.');
            } else {
                flash('error', 'Rapor bulunamadı.');
            }
        } else {
            throw new InvalidArgumentException('Geçersiz rapor işlemi.');
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

$currentStatus = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'open';
$currentStatus = $currentStatus === '' || in_array($currentStatus, $allowedStatuses, true) ? $currentStatus : 'open';
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
], static fn ($value, $key): bool => $key === 'status' || ($value !== '' && $value !== null), ARRAY_FILTER_USE_BOTH));

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

    <?= adminRenderPageHero('bi-shield-exclamation', '', 'Şikayetler & Raporlar', 'Konu raporlarını ve kullanıcı şikayetlerini tek yerden yönetin.', [], [
        'class' => 'complaints-heading',
        'actions_html' => '<span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-inbox"></i> ' . number_format($totalActiveCount, 0, ',', '.') . ' aktif iş</span>',
    ]) ?>

    <?= adminRenderTabBar([
        'topics' => [
            'href' => adminComplaintsUrl('topics'),
            'icon' => 'bi-flag',
            'label' => 'Konu Raporları',
            'badge' => number_format($topicActiveCount, 0, ',', '.'),
            'badge_tone' => $topicActiveCount > 0 ? 'danger' : 'muted',
        ],
        'users' => [
            'href' => adminComplaintsUrl('users'),
            'icon' => 'bi-person-exclamation',
            'label' => 'Kullanıcı Şikayetleri',
            'badge' => number_format($userActiveCount, 0, ',', '.'),
            'badge_tone' => $userActiveCount > 0 ? 'danger' : 'muted',
        ],
        'reasons' => [
            'href' => adminComplaintsUrl('reasons'),
            'icon' => 'bi-list-check',
            'label' => 'Rapor Nedenleri',
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
        'href' => adminComplaintsUrl($activeTab, $filters + ['status' => '']),
        'tone' => 'info',
        'icon' => $scopeIcon,
        'label' => 'Tüm kayıtlar',
        'value' => number_format($scopeTotal, 0, ',', '.'),
        'class' => 'complaints-summary-card' . ($currentStatus === '' ? ' is-active' : ''),
        'attrs' => $currentStatus === '' ? ['aria-current' => 'page'] : [],
    ]];
    foreach ($statusLabels as $key => $meta) {
        $complaintsSummaryCards[] = [
            'href' => adminComplaintsUrl($activeTab, $filters + ['status' => $key]),
            'tone' => $meta[1] === 'muted' ? 'info' : (string) $meta[1],
            'icon' => (string) $meta[2],
            'label' => (string) $meta[0],
            'value' => number_format((int) ($scopeCounts[$key] ?? 0), 0, ',', '.'),
            'class' => 'complaints-summary-card' . ($currentStatus === $key ? ' is-active' : ''),
            'attrs' => $currentStatus === $key ? ['aria-current' => 'page'] : [],
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
                <div class="complaints-toolbar-heading">
                    <strong><?= htmlspecialchars($scopeTitle) ?></strong>
                    <span><?= htmlspecialchars($currentStatusLabel) ?> · <?= number_format(count($reports), 0, ',', '.') ?> kayıt</span>
                </div>
                <?php if ($activeTab === 'topics'): ?>
                <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl('topics')) ?>" class="ui-admin-inline-form complaints-delete-all-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_all_topic_reports">
                    <input type="hidden" name="report_scope" value="topics">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm" <?= $scopeTotal <= 0 ? 'disabled' : '' ?>><i class="bi bi-trash3"></i> Tüm raporları sil</button>
                </form>
                <?php endif; ?>
            </div>

            <form method="get" class="complaints-filter admin-filter-form">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <div class="complaints-filter-primary">
                    <div class="complaints-filter-search">
                        <label class="ui-admin-form-label" for="report-search">Ara</label>
                        <input id="report-search" name="q" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="<?= $activeTab === 'users' ? 'Kullanıcı, e-posta veya detay' : 'Konu, kullanıcı veya detay' ?>">
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-status-filter">Durum</label>
                        <select id="report-status-filter" name="status" class="ui-admin-form-select">
                            <option value="" <?= $currentStatus === '' ? 'selected' : '' ?>>Tüm kayıtlar</option>
                            <?php foreach ($statusLabels as $key => $meta): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-reason-filter">Rapor nedeni</label>
                        <select id="report-reason-filter" name="reason" class="ui-admin-form-select">
                            <option value="">Tüm nedenler</option>
                            <?php foreach ($reasonLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $filters['reason'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-date-from">Başlangıç tarihi</label>
                        <input id="report-date-from" name="date_from" type="date" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="report-date-to">Bitiş tarihi</label>
                        <input id="report-date-to" name="date_to" type="date" class="ui-admin-form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                    <div class="complaints-filter-actions">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-funnel"></i> Filtrele</button>
                        <a href="<?= htmlspecialchars(adminComplaintsUrl($activeTab, ['status' => ''])) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Filtreleri temizle" aria-label="Filtreleri temizle"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($reports)): ?>
            <?= adminRenderEmptyState([
                'icon' => $scopeIcon,
                'tone' => 'info',
                'title' => 'Bu filtrede kayıt yok.',
                'description' => 'Yeni bildirimler geldiğinde burada listelenecek.',
            ]) ?>
        <?php else: ?>
            <form id="bulkReportsForm" method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="complaints-bulk-bar admin-bulk-action-bar" data-admin-no-lock="1">
                <?= csrf_field() ?>
                <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                <input type="hidden" name="action" value="bulk_action">
                <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                <div class="complaints-bulk-meta">
                    <label class="ui-admin-check-inline">
                        <input type="checkbox" id="selectAllReports">
                        <span>Tümünü seç</span>
                    </label>
                    <span class="complaints-selection-count" aria-live="polite" aria-atomic="true"><span id="selectedReportCount">0</span> seçili</span>
                </div>
                <div class="complaints-bulk-controls">
                    <select id="bulkReportOperation" name="bulk_operation" class="ui-admin-form-select" aria-label="Toplu işlem" required>
                        <option value="">Toplu işlem seçin</option>
                        <optgroup label="Durumu değiştir">
                            <?php foreach ($statusLabels as $key => $meta): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta[0]) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Silme işlemi">
                            <option value="delete">Seçilenleri sil</option>
                        </optgroup>
                    </select>
                    <input id="bulkReportNote" name="bulk_admin_note" class="ui-admin-form-control" placeholder="Not (boşsa mevcut not korunur)" maxlength="1000" aria-label="Toplu yönetici notu" title="Boş bırakıldığında her raporun mevcut yönetici notu korunur.">
                    <button id="bulkReportSubmit" type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" disabled><i class="bi bi-check2"></i> <span>Uygula</span></button>
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
                    <article class="complaints-item-card ui-card">
                        <div class="complaints-item-header">
                            <div class="complaints-item-header-left">
                                <label class="complaints-select" title="Şikayeti seç">
                                    <input type="checkbox" name="bulk_report_ids[]" value="<?= $reportId ?>" class="complaints-row-checkbox" form="bulkReportsForm" aria-label="Rapor #<?= $reportId ?> seç">
                                </label>
                                <span class="complaints-id-badge"><i class="bi <?= $activeTab === 'users' ? 'bi-person-exclamation' : 'bi-flag-fill' ?>"></i> Rapor #<?= $reportId ?></span>
                                <span class="complaints-reason-tag"><i class="bi bi-shield-exclamation"></i> <?= htmlspecialchars($reasonLabels[$report['reason']] ?? (string) $report['reason']) ?></span>
                            </div>
                            <div class="complaints-item-header-right">
                                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($statusMeta[1]) ?>"><i class="bi <?= htmlspecialchars($statusMeta[2]) ?>"></i> <?= htmlspecialchars($statusMeta[0]) ?></span>
                            </div>
                        </div>

                        <div class="complaints-item-body">
                            <div class="complaints-entity-box">
                                <?php if ($activeTab === 'users'): ?>
                                    <div class="complaints-entity-target">
                                        <span class="complaints-entity-label">ŞİKAYET EDİLEN KULLANICI</span>
                                        <strong class="complaints-entity-title"><i class="bi bi-person-circle"></i> <?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?></strong>
                                        <?php if (!empty($report['reported_user_email'])): ?>
                                            <span class="complaints-entity-sub"><?= htmlspecialchars((string) $report['reported_user_email']) ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) ($report['reported_user_id'] ?? 0) > 0): ?>
                                            <a class="complaints-link-btn" href="users.php?tab=users&amp;edit=<?= (int) $report['reported_user_id'] ?>"><i class="bi bi-person-gear"></i> Profili Aç</a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="complaints-entity-target">
                                        <span class="complaints-entity-label">ŞİKAYET EDİLEN KONU</span>
                                        <strong class="complaints-entity-title"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars((string) ($report['topic_title'] ?? 'Silinmiş konu')) ?></strong>
                                        <?php if (!empty($report['topic_slug'])): ?>
                                            <a class="complaints-link-btn" href="<?= topicUrl((string) $report['topic_slug'], (int) ($report['topic_id'] ?? 0)) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Konuyu Gör</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="complaints-reporter-info">
                                    <span class="complaints-reporter-meta"><i class="bi bi-person-fill"></i> <strong><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?></strong> bildirimi</span>
                                    <span class="complaints-reporter-meta"><i class="bi bi-calendar3"></i> <?= htmlspecialchars(adminComplaintsDate((string) ($report['created_at'] ?? 'now'))) ?></span>
                                    <?php if ($events !== []): ?>
                                        <span class="complaints-reporter-meta"><i class="bi bi-clock-history"></i> <?= count($events) ?> işlem kaydı</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($details !== '' || $adminNote !== ''): ?>
                            <div class="complaints-item-notes">
                            <?php if ($details !== ''): ?>
                                <div class="complaints-reason-detail-box">
                                    <span class="complaints-detail-label"><i class="bi bi-chat-square-quote-fill"></i> ŞİKAYET AÇIKLAMASI</span>
                                    <p><?= nl2br(htmlspecialchars($details)) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($adminNote !== ''): ?>
                                <div class="complaints-admin-note-box">
                                    <span class="complaints-admin-note-label"><i class="bi bi-sticky-fill"></i> YÖNETİCİ NOTU</span>
                                    <p><?= nl2br(htmlspecialchars($adminNote)) ?></p>
                                </div>
                            <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="complaints-item-footer">
                            <div class="complaints-quick-status-bar">
                                <span class="complaints-quick-label">Hızlı Durum:</span>
                                <?php foreach (['open', 'reviewing', 'resolved', 'rejected'] as $quickStatus): ?>
                                    <?php if ($statusKey !== $quickStatus): ?>
                                        <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="ui-admin-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                                            <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                                            <input type="hidden" name="action" value="quick_update">
                                            <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                            <input type="hidden" name="status" value="<?= htmlspecialchars($quickStatus) ?>">
                                            <input type="hidden" name="admin_note" value="<?= htmlspecialchars($adminNote) ?>">
                                            <?php
                                                $qMeta = adminComplaintsStatusMeta($statusLabels, $quickStatus);
                                                $btnToneClass = $quickStatus === 'resolved' ? 'ui-admin-btn-success' : ($quickStatus === 'reviewing' ? 'ui-admin-btn-warning' : ($quickStatus === 'open' ? 'ui-admin-btn-danger-outline' : 'ui-admin-btn-outline'));
                                            ?>
                                            <button type="submit" class="ui-admin-btn ui-admin-btn-xs <?= $btnToneClass ?>" title="<?= htmlspecialchars($qMeta[0]) ?>">
                                                <i class="bi <?= htmlspecialchars($qMeta[2]) ?>"></i> <?= htmlspecialchars($qMeta[0]) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="ui-admin-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                                    <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                    <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger-outline" title="Raporu Sil">
                                        <i class="bi bi-trash3"></i> Sil
                                    </button>
                                </form>
                            </div>

                            <div class="complaints-item-actions">
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-primary" data-complaints-modal-open="<?= htmlspecialchars($modalId) ?>">
                                    <i class="bi bi-pencil-square"></i> İncele & Not Ekle
                                </button>
                            </div>
                        </div>
                    </article>

                    <div class="complaints-modal ui-admin-modal-overlay" id="<?= htmlspecialchars($modalId) ?>" hidden aria-hidden="true">
                        <div class="complaints-modal-backdrop" data-complaints-modal-close></div>
                        <div class="complaints-modal-dialog ui-admin-modal-shell ui-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                            <div class="complaints-modal-head ui-modal__head ui-panel__head">
                                <div>
                                    <h3 id="<?= htmlspecialchars($modalId) ?>-title">
                                        <?php if ($activeTab === 'users'): ?>
                                            <i class="bi bi-person-exclamation"></i> <?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?>
                                        <?php else: ?>
                                            <i class="bi bi-flag-fill"></i> <?= htmlspecialchars((string) ($report['topic_title'] ?? 'Silinmiş konu')) ?>
                                        <?php endif; ?>
                                    </h3>
                                    <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($statusMeta[1]) ?>"><i class="bi <?= htmlspecialchars($statusMeta[2]) ?>"></i> <?= htmlspecialchars($statusMeta[0]) ?></span>
                                </div>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-complaints-modal-close><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="complaints-modal-nav">
                                <button type="button" class="complaints-modal-tab-btn is-active" data-complaints-modal-tab="action">
                                    <i class="bi bi-pencil-square"></i> Moderasyon & Not
                                </button>
                                <button type="button" class="complaints-modal-tab-btn" data-complaints-modal-tab="details">
                                    <i class="bi bi-file-earmark-text"></i> Rapor Detayı
                                </button>
                                <button type="button" class="complaints-modal-tab-btn" data-complaints-modal-tab="history">
                                    <i class="bi bi-clock-history"></i> İşlem Geçmişi (<?= count($events) ?>)
                                </button>
                            </div>
                            <div class="complaints-modal-body ui-modal__body ui-panel__body">
                                <div class="complaints-modal-panel" data-complaints-modal-panel="action">
                                    <form method="post" action="<?= htmlspecialchars(adminComplaintsUrl($activeTab)) ?>" class="complaints-modal-admin-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="report_scope" value="<?= htmlspecialchars($activeTab) ?>">
                                        <input type="hidden" name="_return" value="<?= htmlspecialchars($returnQuery) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="ui-admin-form-label">Rapor Durumu</label>
                                                <select name="status" class="ui-admin-form-select">
                                                    <?php foreach ($statusLabels as $key => $meta): ?>
                                                        <option value="<?= htmlspecialchars($key) ?>" <?= $statusKey === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta[0]) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="ui-admin-form-label">Rapor Nedeni</label>
                                                <input type="text" class="ui-admin-form-control" value="<?= htmlspecialchars($reasonLabels[$report['reason']] ?? (string) $report['reason']) ?>" readonly>
                                            </div>
                                            <div class="col-12">
                                                <label class="ui-admin-form-label">Yönetici Notu</label>
                                                <textarea name="admin_note" class="ui-admin-form-control" rows="4" placeholder="Bu şikayet ile ilgili iç notunuzu buraya yazabilirsiniz..."><?= htmlspecialchars($adminNote) ?></textarea>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Güncelle ve Kaydet</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="complaints-modal-panel" data-complaints-modal-panel="details" hidden>
                                    <div class="complaints-modal-info-grid">
                                        <?php if ($activeTab === 'users'): ?>
                                            <div class="complaints-modal-box">
                                                <strong><i class="bi bi-person-x"></i> Şikayet Edilen Kullanıcı</strong>
                                                <p><?= htmlspecialchars((string) ($report['reported_user_name'] ?? 'Silinmiş kullanıcı')) ?><br><?= htmlspecialchars((string) ($report['reported_user_email'] ?? '')) ?></p>
                                            </div>
                                            <div class="complaints-modal-box">
                                                <strong><i class="bi bi-person-check"></i> Şikayet Eden (Bildiren)</strong>
                                                <p><?= htmlspecialchars((string) ($report['reporter_name'] ?? 'Anonim')) ?><br><?= htmlspecialchars((string) ($report['reporter_email'] ?? '')) ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="complaints-modal-box col-12">
                                                <strong><i class="bi bi-file-earmark-text"></i> Şikayet Edilen Konu</strong>
                                                <p><?= htmlspecialchars((string) ($report['topic_title'] ?? 'Silinmiş konu')) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="complaints-modal-box col-12">
                                            <strong><i class="bi bi-chat-left-quote"></i> Rapor Açıklaması</strong>
                                            <p><?= nl2br(htmlspecialchars($details !== '' ? $details : 'Açıklama girilmemiş.')) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="complaints-modal-panel" data-complaints-modal-panel="history" hidden>
                                    <div class="complaints-history">
                                        <?php if ($events === []): ?>
                                            <?= adminRenderEmptyState(['size' => 'inline', 'icon' => 'bi-clock-history', 'title' => 'Geçmiş kaydı yok', 'description' => '']) ?>
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
                            <div class="complaints-modal-foot ui-modal__foot ui-panel__foot">
                                <span class="ui-admin-muted-sm"><i class="bi bi-info-circle"></i> İşlem değişiklikleri burada kaydedilir.</span>
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
