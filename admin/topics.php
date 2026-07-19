<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Konu Yönetimi';

$currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
adminRequirePermission('topics.view', 'Konulari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$allowedStatuses = ['draft', 'published', 'approved', 'rejected', 'revision'];
$statusLabels = [
    'draft' => ['Taslak', 'warning'],
    'published' => ['Yayında', 'success'],
    'approved' => ['Onaylı', 'success'],
    'rejected' => ['Reddedildi', 'danger'],
    'revision' => ['Revizyon', 'warning'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: topics.php');
        exit;
    }

    $postAction = trim((string) ($_POST['action'] ?? ''));
    if ($postAction === 'save_topic_settings') {
        if (!adminCurrentUserCan('topics.edit')) {
            adminDenyAction('Konu ayarlarini kaydetmek icin gerekli izin hesabiniza tanimlanmamis.', 'topics.php?tab=settings');
        }
        try {
            $_POST['_sections'] = 'topic_management';
            saveAdminSettings($pdo, $_POST);
            logActivity($pdo, 'topic_settings_updated', 'settings', null, ['section' => 'topic_management']);
            adminAuditLogger()->logAction($pdo, 'topic_settings_updated', 'settings', 0, 'Konu yönetimi ayarları güncellendi', [], ['section' => 'topic_management'], false);
            flash('success', 'Konu yönetimi ayarları kaydedildi.');
        } catch (Throwable $e) {
            flash('error', 'Konu yönetimi ayarları kaydedilemedi: ' . safeErrorMessage($e));
        }
        header('Location: topics.php?tab=settings');
        exit;
    }

    if ($postAction === 'moderation_action') {
        if (!adminCurrentUserCan('topics.edit')) {
            adminDenyAction('Konu moderasyonu icin gerekli izin hesabiniza tanimlanmamis.', 'topics.php');
        }
        $topicId = (int)($_POST['topic_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        $note = trim((string)($_POST['moderation_note'] ?? ''));
        $topicForNotification = null;
        if ($topicId > 0) {
            try {
                $topicNoticeStmt = $pdo->prepare("SELECT id, author_id, title, slug FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                $topicNoticeStmt->execute([$topicId]);
                $topicForNotification = $topicNoticeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                $topicForNotification = null;
            }
        }

        $ok = adminQualitySetTopicModeration($pdo, $topicId, $decision, $note);
        if ($ok && $topicForNotification && function_exists('notificationDispatch')) {
            try {
                $eventKey = [
                    'approve' => 'topic_approved',
                    'reject' => 'topic_rejected',
                    'revision' => 'topic_revision_requested',
                ][$decision] ?? '';

                if ($eventKey !== '') {
                    $topicSlug = trim((string) ($topicForNotification['slug'] ?? ''));
                    $topicLink = $decision === 'approve'
                        ? topicUrl($topicSlug, (int) ($topicForNotification['id'] ?? $topicId))
                        : routePublicStaticUrl('edit_topic') . '?id=' . $topicId;
                    $noteLine = $note !== '' ? ' Not: ' . $note : '';

                    notificationDispatch($pdo, $eventKey, (int) $topicForNotification['author_id'], $currentUserId ?: null, 'topic', $topicId, [
                        'actor_name' => (string) ($_SESSION['_auth_user_name'] ?? 'Yönetim'),
                        'topic_title' => (string) ($topicForNotification['title'] ?? 'Konu'),
                        'moderation_note_line' => $noteLine,
                        'link' => $topicLink,
                    ]);
                }
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }
        flash($ok ? 'success' : 'error', $ok ? 'Moderasyon kararı kaydedildi.' : 'Moderasyon kararı kaydedilemedi.');
        header('Location: topics.php');
        exit;
    }

    if ($postAction === 'topic_report_action') {
        header('Location: complaints-reports.php?tab=topics');
        exit;
    }

    if ($postAction === 'check_download_link') {
        if (!adminCurrentUserCan('topics.edit')) {
            adminDenyAction('Konu baglanti kontrolu icin gerekli izin hesabiniza tanimlanmamis.', 'topics.php');
        }
        $result = adminQualityCheckDownloadLink($pdo, (int)($_POST['link_id'] ?? 0));
        flash($result['success'] ? 'success' : 'error', (string)$result['message']);
        header('Location: topics.php');
        exit;
    }

    if ($postAction === 'clear_topic_health') {
        if (!adminCurrentUserCan('topics.edit')) {
            adminDenyAction('Konu sağlığını temizlemek için gerekli izin hesabınıza tanımlanmamış.', 'topics.php?tab=health');
        }
        $result = adminQualityClearTopicHealth($pdo);
        flash($result['success'] ? 'success' : 'error', (string) $result['message']);
        header('Location: topics.php?tab=health&health_cleared=1&health_clear_ts=' . rawurlencode((string) time()));
        exit;
    }

    $action = trim((string) ($_POST['bulk_action'] ?? ''));
    $selectedIds = array_values(array_filter(array_map('intval', (array) ($_POST['topic_ids'] ?? [])), static fn (int $id): bool => $id > 0));

    if ($action === '' || empty($selectedIds)) {
        flash('error', 'Lütfen işlem yapacağınız en az bir konu seçin.');
        header('Location: topics.php');
        exit;
    }
    if (in_array($action, ['delete', 'purge'], true) && !adminCurrentUserCan('topics.delete')) {
        adminDenyAction('Konu silmek icin gerekli izin hesabiniza tanimlanmamis.', 'topics.php');
    }
    if (!in_array($action, ['delete', 'purge'], true) && !adminCurrentUserCan('topics.edit')) {
        adminDenyAction('Konu islemi yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'topics.php');
    }

    try {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

        switch ($action) {
            case 'publish':
                $params = array_merge(['published'], $selectedIds);
                $stmt = $pdo->prepare("UPDATE topics SET status = ?, published_at = COALESCE(published_at, NOW()), updated_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
                $stmt->execute($params);
                flash('success', count($selectedIds) . ' konu yayına alındı.');
                break;

            case 'draft':
                $params = array_merge(['draft'], $selectedIds);
                $stmt = $pdo->prepare("UPDATE topics SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
                $stmt->execute($params);
                flash('success', count($selectedIds) . ' konu taslağa alındı.');
                break;

            case 'restore':
                $params = array_merge(['published'], $selectedIds);
                $stmt = $pdo->prepare("UPDATE topics SET status = ?, deleted_at = NULL, published_at = COALESCE(published_at, NOW()), updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute($params);
                flash('success', count($selectedIds) . ' konu geri yüklendi.');
                break;

            case 'delete':
                $stmt = $pdo->prepare("UPDATE topics SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
                $stmt->execute($selectedIds);
                flash('success', count($selectedIds) . ' konu çöp kutusuna taşındı.');
                break;

            case 'purge':
                $deletedCount = 0;
                foreach ($selectedIds as $selectedId) {
                    $result = permanentlyDeleteTopic($pdo, (int) $selectedId, (string) $baseUri);
                    if (!$result['success']) {
                        throw new RuntimeException((string) $result['message']);
                    }
                    $deletedCount++;
                }
                flash('success', $deletedCount . ' konu kalıcı olarak silindi.');
                break;

            default:
                flash('error', 'Geçersiz toplu işlem seçildi.');
                break;
        }
        if ($action !== '') {
            seoInvalidateSitemapCaches();
            logActivity($pdo, 'topic_bulk_' . $action, 'topic', null, ['count' => count($selectedIds)]);
            adminAuditLogger()->logAction($pdo, 'topic_bulk_' . $action, 'topic', 0, 'Toplu konu işlemi', [], ['action' => $action, 'count' => count($selectedIds)], false);
        }
    } catch (Throwable $e) {
        flash('error', 'Toplu işlem başarısız: ' . safeErrorMessage($e));
    }

    header('Location: topics.php');
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$viewFilter = trim((string) ($_GET['view'] ?? 'active'));
$activeTab = trim((string) ($_GET['tab'] ?? 'list'));
if ($activeTab === 'reports') {
    header('Location: complaints-reports.php?tab=topics');
    exit;
}
$activeTab = in_array($activeTab, ['list', 'health', 'settings'], true) ? $activeTab : 'list';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();
$healthSearch = trim((string)($_GET['health_q'] ?? ''));
$healthStatusFilter = trim((string)($_GET['health_status'] ?? ''));
$healthIssueFilter = trim((string)($_GET['health_issue'] ?? ''));
$healthPage = max(1, (int)($_GET['health_page'] ?? 1));
$healthPerPage = adminPaginationPerPage();
$healthFilters = [
    'q' => $healthSearch,
    'health_status' => $healthStatusFilter,
    'health_issue' => $healthIssueFilter,
];
if (function_exists('adminQualityNormalizeTopicHealthFilters')) {
    $healthFilters = adminQualityNormalizeTopicHealthFilters($healthFilters);
    $healthSearch = $healthFilters['q'];
    $healthStatusFilter = $healthFilters['health_status'];
    $healthIssueFilter = $healthFilters['health_issue'];
}

$topics = [];
$stats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'deleted' => 0,
];
$totalPages = 1;
$totalFiltered = 0;
$topicHealthSummary = [
    'total' => 0,
    'checked' => 0,
    'ok' => 0,
    'warning' => 0,
    'broken' => 0,
    'unchecked' => 0,
    'download_link_issues' => 0,
    'missing_download_links' => 0,
    'missing_primary_media' => 0,
    'broken_download_links' => 0,
    'broken_media' => 0,
    'image_issues' => 0,
];
$topicHealthRows = [];
$topicHealthTotalFiltered = 0;
$topicHealthTotalPages = 1;
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$definitions = function_exists('adminSettingDefinitions') ? adminSettingDefinitions() : [];
$topicHealthBatchSize = max(1, min(10, (int) ($settings['topic_health_scan_batch_size'] ?? 3)));

if ($pdo) {
    try {
        adminQualityEnsureSchema($pdo);

        $where = [];
        $params = [];

        if ($viewFilter === 'deleted') {
            $where[] = 't.deleted_at IS NOT NULL';
        } else {
            $where[] = 't.deleted_at IS NULL';
        }

        if ($search !== '') {
            $where[] = '(t.title LIKE :search_title OR t.slug LIKE :search_slug OR cat.name LIKE :search_category)';
            $searchTerm = '%' . $search . '%';
            $params['search_title'] = $searchTerm;
            $params['search_slug'] = $searchTerm;
            $params['search_category'] = $searchTerm;
        }

        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $statusFilter;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM topics t LEFT JOIN categories cat ON t.category_id = cat.id {$whereSql}");
        $countStmt->execute($params);
        $totalFiltered = (int) $countStmt->fetchColumn();
        
        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT t.id, t.title, t.slug, t.status, t.created_at, t.updated_at, t.published_at, t.deleted_at, t.moderation_flags,
                    (SELECT MIN(tdl.id) FROM topic_download_links tdl WHERE tdl.topic_id = t.id) AS first_download_link_id,
                    cat.name AS category,
                    u.username AS author_name,
                    pm.path AS cover_image
             FROM topics t
             LEFT JOIN categories cat ON t.category_id = cat.id
             LEFT JOIN users u ON t.author_id = u.id
             LEFT JOIN media_files pm ON t.primary_media_file_id = pm.id
             {$whereSql}
             ORDER BY COALESCE(t.published_at, t.created_at) DESC, t.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $topics = $stmt->fetchAll() ?: [];

        $stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL")->fetchColumn();
        $stats['published'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published', 'approved')")->fetchColumn();
        $stats['draft'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status = 'draft'")->fetchColumn();
        $stats['deleted'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NOT NULL")->fetchColumn();
        $topicHealthSummary = adminQualityTopicHealthSummary($pdo);
        $topicHealthTotalFiltered = adminQualityCountTopicHealthRows($pdo, $healthFilters);
        $topicHealthTotalPages = max(1, (int)ceil($topicHealthTotalFiltered / $healthPerPage));
        if ($healthPage > $topicHealthTotalPages) {
            $healthPage = $topicHealthTotalPages;
        }
        $topicHealthRows = adminQualityGetTopicHealthRows($pdo, $healthPerPage, ($healthPage - 1) * $healthPerPage, $healthFilters);
    } catch (Throwable $e) {
        flash('error', 'Konular yüklenemedi: ' . safeErrorMessage($e));
    }
}

if (empty($topics)) {
    $topics = [];
}

require_once __DIR__ . '/header.php';
?>
<div class="topics-page">
<?= adminRenderPageHero('bi-card-heading', 'Konu yönetimi', 'Konu Yönetimi', 'İçeriklerinizi premium arayüzle inceleyin, onaylayın veya düzenleyin.', [
    ['href' => $baseUri . '/admin/create.php', 'label' => 'Yeni Konu', 'icon' => 'bi-plus-lg', 'class' => 'btn-create-topic ui-admin-btn-primary'],
], ['tag' => 'div']) ?>

<?= adminRenderTabBar([
    'list' => [
        'href' => 'topics.php',
        'icon' => 'bi-list-ul',
        'label' => 'Konu Listesi',
    ],
    'health' => [
        'href' => 'topics.php?tab=health',
        'icon' => 'bi-heart-pulse',
        'label' => 'Konu Sağlığı',
        'badge' => (int) ($topicHealthSummary['broken'] ?? 0) > 0 ? number_format((int) $topicHealthSummary['broken'], 0, ',', '.') : '',
        'badge_tone' => 'danger',
        'badge_class' => 'ui-admin-badge-xs',
    ],
    'settings' => [
        'href' => 'topics.php?tab=settings',
        'icon' => 'bi-sliders',
        'label' => 'Ayarlar',
    ],
], $activeTab, [
    'class' => 'admin-tabs topics-admin-tabs',
    'link_class' => 'admin-tab',
    'active_class' => 'active',
    'aria_label' => 'Konu yönetimi sekmeleri',
]) ?>

<?php if ($activeTab === 'list'): ?>
<?= adminRenderStatCards([
    ['href' => 'topics.php?view=active', 'tone' => 'info', 'icon' => 'bi-collection', 'label' => 'Toplam Konu', 'value' => number_format((int) $stats['total'], 0, ',', '.'), 'class' => 'topics-stat-card' . ($viewFilter !== 'deleted' && $statusFilter === '' ? ' is-active' : '')],
    ['href' => 'topics.php?view=active&status=published', 'tone' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Yayında', 'value' => number_format((int) $stats['published'], 0, ',', '.'), 'class' => 'topics-stat-card' . ($viewFilter !== 'deleted' && $statusFilter === 'published' ? ' is-active' : '')],
    ['href' => 'topics.php?view=active&status=draft', 'tone' => 'warning', 'icon' => 'bi-pencil-square', 'label' => 'Taslak', 'value' => number_format((int) $stats['draft'], 0, ',', '.'), 'class' => 'topics-stat-card' . ($viewFilter !== 'deleted' && $statusFilter === 'draft' ? ' is-active' : '')],
    ['href' => 'topics.php?view=deleted', 'tone' => 'danger', 'icon' => 'bi-trash', 'label' => 'Çöp Kutusu', 'value' => number_format((int) $stats['deleted'], 0, ',', '.'), 'class' => 'topics-stat-card' . ($viewFilter === 'deleted' ? ' is-active' : '')],
], ['class' => 'topics-stat-grid', 'aria_label' => 'Konu özeti']) ?>

<form id="bulkTopicsForm" method="post" action="topics.php">
    <?= csrf_field() ?>
</form>

<?php $hasTopicFilters = $search !== '' || $statusFilter !== '' || $viewFilter === 'deleted'; ?>
<div class="topics-ops-deck">
    <div class="topics-filter-bar topics-filter-bar-modern">
        <div class="topics-ops-head">
            <div>
                <span class="topics-ops-kicker"><i class="bi bi-funnel"></i> Akilli filtre</span>
                <strong><?= number_format($totalFiltered, 0, ',', '.') ?> konu listeleniyor</strong>
            </div>
            <?php if ($hasTopicFilters): ?>
                <a href="topics.php" class="topics-filter-reset"><i class="bi bi-x-circle"></i> Filtreleri temizle</a>
            <?php endif; ?>
        </div>
        <form method="get" action="topics.php" class="topics-filter-form admin-filter-form">
            <label class="topics-filter-field topics-filter-search">
                <span>Arama</span>
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Baslik, slug veya kategori ara...">
            </label>
            <label class="topics-filter-field">
                <span>Durum</span>
                <select name="status">
                    <option value="">Tum durumlar</option>
                    <?php foreach ($allowedStatuses as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$statusOption][0] ?? ucfirst($statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="topics-filter-field">
                <span>Gorunum</span>
                <select name="view">
                    <option value="active" <?= $viewFilter !== 'deleted' ? 'selected' : '' ?>>Aktif konular</option>
                    <option value="deleted" <?= $viewFilter === 'deleted' ? 'selected' : '' ?>>Cop kutusu</option>
                </select>
            </label>
            <button type="submit" class="topics-filter-submit"><i class="bi bi-sliders2"></i> Uygula</button>
        </form>
    </div>

    <div class="bulk-bar topics-bulk-bar admin-bulk-action-bar ui-panel" data-topic-bulk-bar>
        <div class="topics-bulk-status">
            <div class="topics-bulk-count-badge" aria-hidden="true">
                <strong id="selectedTopicCount">0</strong>
            </div>
            <div class="topics-bulk-copy">
                <span class="topics-bulk-kicker"><i class="bi bi-command"></i> Toplu islem</span>
                <strong id="topicsBulkTitle">Konu secilmedi</strong>
                <span id="topicsBulkHint">Listeden konu secince islemler aktiflesir.</span>
            </div>
        </div>
        <div class="topics-bulk-meta">
            <label class="topics-select-all">
                <input type="checkbox" id="selectAllTopics" class="chk-styled">
                <span>Tum gorunenleri sec</span>
            </label>
            <button type="button" class="topics-bulk-clear" id="clearTopicSelection" disabled>
                <i class="bi bi-x-circle"></i>
                Secimi temizle
            </button>
        </div>
        <div class="bulk-bar-right topics-bulk-actions">
            <select name="bulk_action" form="bulkTopicsForm" id="bulkTopicAction" class="ui-admin-form-select">
                <option value="">Toplu islem sec...</option>
                <?php if ($viewFilter === 'deleted'): ?>
                    <option value="restore">Geri yukle</option>
                    <option value="purge">Kalici sil</option>
                <?php else: ?>
                    <option value="publish">Yayina al</option>
                    <option value="draft">Taslak yap</option>
                    <option value="delete">Cope tasi</option>
                <?php endif; ?>
            </select>
            <button type="submit" form="bulkTopicsForm" class="ui-admin-btn ui-admin-btn-primary topics-bulk-apply" id="bulkTopicApply" disabled><i class="bi bi-check2-circle"></i> Uygula</button>
        </div>
    </div>
</div>

<?= adminRenderTableOpen([
    ['label' => '', 'class' => 'ui-admin-table-head-check'],
    'Konu',
    'Kategori',
    'Yazar',
    'Durum',
    'Tarih',
    ['label' => 'İşlemler', 'class' => 'ui-admin-table-head-actions'],
], [
    'class' => 'topics-table',
    'wrap_class' => 'topics-table-container',
    'wrap_attrs' => ['data-ui-table' => 'topics'],
    'label' => 'Konu listesi',
    'colgroup_html' => '<colgroup><col class="topics-col-check"><col class="topics-col-topic"><col class="topics-col-category"><col class="topics-col-author"><col class="topics-col-status"><col class="topics-col-date"><col class="topics-col-actions"></colgroup>',
]) ?>
            <?php if (empty($topics)): ?>
                <?= adminRenderTableEmptyRow(7, [
                        'icon' => 'bi-search',
                        'tone' => 'info',
                        'title' => 'Konu bulunamadı',
                        'description' => 'Seçili filtrelerle eşleşen konu yok. Filtreleri temizleyip tekrar deneyebilirsiniz.',
                        'actions' => [
                            ['href' => 'topics.php', 'label' => 'Filtreleri Temizle', 'icon' => 'bi-x-circle'],
                            ['href' => $baseUri . '/admin/create.php', 'label' => 'Yeni Konu', 'icon' => 'bi-plus-circle', 'class' => 'ui-admin-btn-primary'],
                        ],
                    ]) ?>
            <?php else: ?>
                <?php foreach ($topics as $t): ?>
                    <?php
                    $rawStatusKey = (string) ($t['status'] ?? 'draft');
                    $statusKey = match ($rawStatusKey) {
                        'pending' => 'draft',
                        'archived' => 'published',
                        default => $rawStatusKey,
                    };
                    $statusMeta = function_exists('adminStatusMeta')
                        ? adminStatusMeta($statusKey, 'topic')
                        : [
                            'label' => $statusLabels[$statusKey][0] ?? ucfirst($statusKey),
                            'tone' => $statusLabels[$statusKey][1] ?? 'secondary',
                            'icon' => '',
                        ];
                    $isDeleted = !empty($t['deleted_at']);
                    $displayDate = $t['published_at'] ?: ($t['updated_at'] ?: ($t['created_at'] ?? null));
                    $coverPath = adminSafeImageUrl((string) ($t['cover_image'] ?? ''), $baseUri);
                    $authorName = $t['author_name'] ?? 'Sistem';
                    $authorInitial = mb_substr($authorName, 0, 1, 'UTF-8');
                    $moderationNote = '';
                    if (!empty($t['moderation_flags'])) {
                        $moderationFlags = json_decode((string)$t['moderation_flags'], true);
                        if (is_array($moderationFlags)) {
                            $moderationNote = trim((string)($moderationFlags['note'] ?? ''));
                        }
                    }
                    ?>
                    <tr class="ui-admin-topic-click-row<?= $isDeleted ? ' ui-admin-row-dimmed' : '' ?>" data-topic-edit-url="<?= $baseUri ?>/admin/edit.php?id=<?= (int) $t['id'] ?>" tabindex="0" title="Düzenleme sayfasına git">
                        <td>
                            <input type="checkbox" name="topic_ids[]" form="bulkTopicsForm" value="<?= (int) $t['id'] ?>" class="topic-row-checkbox chk-styled">
                        </td>
                        <td>
                            <div class="topic-meta-wrap">
                                <?php if($coverPath): ?>
                                    <img src="<?= htmlspecialchars($coverPath) ?>" alt="" class="topic-thumbnail" width="48" height="48">
                                <?php else: ?>
                                    <?= adminRenderImagePlaceholder('topic-thumbnail topic-thumbnail-empty') ?>
                                <?php endif; ?>
                                <div class="topic-title-info">
                                    <strong><?= htmlspecialchars((string) ($t['title'] ?? 'Başlıksız konu')) ?></strong>
                                    <span><?= htmlspecialchars((string) $t['slug']) ?></span>
                                    <?php if ($moderationNote !== ''): ?>
                                        <button type="button" class="moderation-note-preview" data-moderation-note-open data-moderation-note="<?= htmlspecialchars($moderationNote, ENT_QUOTES, 'UTF-8') ?>" data-moderation-topic="<?= htmlspecialchars((string) ($t['title'] ?? 'Başlıksız konu'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Son moderasyon notunu görüntüle">
                                            <i class="bi bi-chat-left-text"></i>
                                            <span>Son moderasyon notu</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string) ($t['category'] ?? 'Genel')) ?></td>
                        <td>
                            <div class="author-wrap">
                                <div class="author-avatar default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml($authorName, (string) ($t['author_avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <span class="author-name"><?= htmlspecialchars($authorName) ?></span>
                            </div>
                        </td>
                        <td>
                            <?= adminRenderBadge((string) ($statusMeta['label'] ?? ucfirst($statusKey)), [
                                'tone' => (string) ($statusMeta['tone'] ?? 'muted'),
                                'icon' => (string) ($statusMeta['icon'] ?? ''),
                                'class' => 'minimal-badge badge-' . $statusKey,
                            ]) ?>
                        </td>
                        <td class="ui-admin-table-cell-muted">
                            <?= $displayDate ? htmlspecialchars(date('d.m.Y', strtotime((string) $displayDate))) : '-' ?>
                        </td>
                        <td class="ui-admin-table-cell-actions">
                            <div class="action-btns">
                                <a href="<?= $baseUri ?>/admin/edit.php?id=<?= (int) $t['id'] ?>" class="btn-icon-minimal edit" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                
                                <?php if (!$isDeleted): ?>
                                    <form action="topics.php" method="post" class="ui-admin-inline-form-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="moderation_action">
                                        <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="btn-icon-minimal success" title="Onayla"><i class="bi bi-check2"></i></button>
                                    </form>
                                    <form action="topics.php" method="post" class="ui-admin-inline-form-block" data-moderation-note-form data-moderation-note-title="Reddetme notu" data-moderation-note-required="1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="moderation_action">
                                        <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="hidden" name="moderation_note" value="">
                                        <button type="submit" class="btn-icon-minimal danger" title="Reddet"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                    <form action="topics.php" method="post" class="ui-admin-inline-form-block" data-moderation-note-form data-moderation-note-title="Revizyon notu" data-moderation-note-required="1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="moderation_action">
                                        <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="decision" value="revision">
                                        <input type="hidden" name="moderation_note" value="">
                                        <button type="submit" class="btn-icon-minimal warning" title="Revizyon İste"><i class="bi bi-arrow-repeat"></i></button>
                                    </form>
                                    
                                    <form action="<?= $baseUri ?>/admin/delete.php" method="post" class="ui-admin-inline-form-block"<?= adminConfirmAttrs(['message' => 'Bu konuyu çöpe taşımak istediğinize emin misiniz?', 'title' => 'Konu çöpe taşınsın mı?', 'ok' => 'Çöpe Taşı', 'tone' => 'danger']) ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn-icon-minimal danger" title="Çöpe Taşı"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form action="<?= $baseUri ?>/admin/delete.php" method="post" class="ui-admin-inline-form-block"<?= adminConfirmAttrs(['message' => 'Bu konu ve bağlı tüm dosyalar kalıcı olarak silinecek. Onaylıyor musunuz?', 'title' => 'Kalıcı silme', 'ok' => 'Kalıcı Sil', 'tone' => 'danger']) ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="permanent" value="1">
                                        <button type="submit" class="btn-icon-minimal danger" title="Kalıcı Sil"><i class="bi bi-fire"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
<?= adminRenderTableClose() ?>

<?php if ($totalPages > 1): ?>
    <?php
    $qs = $_GET;
    unset($qs['page']);
    $baseUrl = 'topics.php?' . (count($qs) > 0 ? http_build_query($qs) . '&' : '') . 'page=';
    echo adminRenderPagination($totalPages, $page, static fn (int $targetPage): string => $baseUrl . $targetPage, [
        'aria_label' => 'Konu sayfalama',
    ]);
    ?>
<?php endif; ?>

<?php endif; ?>


<?php if ($activeTab === 'settings'): ?>
<?= adminRenderPanelOpen([
    'class' => 'admin-card-spaced topic-settings-panel',
    'icon' => 'bi-sliders',
    'title' => 'Konu Yönetimi Ayarları',
    'subtitle' => 'Konu oluşturma, kullanıcı düzenleme onayı ve manuel sağlık taraması davranışlarını buradan yönetin.',
]) ?>
        <form method="post" action="topics.php?tab=settings" class="settings-admin-form topic-settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_topic_settings">
            <input type="hidden" name="_sections" value="topic_management">

            <div class="admin-section-note ui-section">
                Bu ayarlar kullanıcıların konu gönderme ve düzenleme akışına doğrudan uygulanır. Konu içi public görünüm ayarları Görünüm sayfasındaki <strong>Konu İçi Ayarları</strong> sekmesine taşındı.
            </div>

            <?= adminRenderSettingsGrid($definitions, $settings, 'topic_management') ?>

            <div class="settings-savebar settings-savebar-inline">
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i>Ayarları Kaydet</button>
                <a href="topics.php" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-list-ul"></i>Konu Listesine Dön</a>
            </div>
        </form>
<?= adminRenderPanelClose() ?>

<?php endif; ?>
<?php if ($activeTab === 'health'): ?>
<?php
$healthStatusMeta = [
    'ok' => ['Sağlam', 'success', 'bi-check-circle-fill'],
    'warning' => ['Uyarı', 'warning', 'bi-exclamation-triangle-fill'],
    'broken' => ['Sorunlu', 'danger', 'bi-x-octagon-fill'],
    'unchecked' => ['Kontrol edilmedi', 'secondary', 'bi-question-circle-fill'],
];
$healthStatusFilterOptions = [
    'ok' => 'Sağlam',
    'needs_check' => 'Kontrol edilmeli',
];
$healthIssueOptions = [
    '' => 'Tum sorunlar',
    'never_checked' => 'Kontrol edilmemis',
    'download_issues' => 'İndirme Linki Sorunlu',
    'image_issues' => 'Resimlerde Sorun Var',
];
$hasHealthFilters = $healthSearch !== '' || $healthStatusFilter !== '' || $healthIssueFilter !== '';
$healthPercent = (int)($topicHealthSummary['total'] ?? 0) > 0
    ? (int)round(((int)($topicHealthSummary['checked'] ?? 0) / max(1, (int)$topicHealthSummary['total'])) * 100)
    : 0;
?>
<section class="topic-health-shell ui-section" data-topic-health-shell>
    <div class="topic-health-overview">
        <div class="topic-health-card topic-health-card-primary ui-card">
            <span class="topic-health-card-icon ui-card"><i class="bi bi-activity"></i></span>
            <div>
                <span>Toplam yayınlı konu</span>
                <strong data-health-summary="total"><?= number_format((int)$topicHealthSummary['total'], 0, ',', '.') ?></strong>
            </div>
        </div>
        <div class="topic-health-card ui-card">
            <span class="topic-health-card-icon tone-success ui-card"><i class="bi bi-check-circle"></i></span>
            <div>
                <span>Sağlam</span>
                <strong data-health-summary="ok"><?= number_format((int)$topicHealthSummary['ok'], 0, ',', '.') ?></strong>
            </div>
        </div>
        <div class="topic-health-card ui-card">
            <span class="topic-health-card-icon tone-warning ui-card"><i class="bi bi-exclamation-triangle"></i></span>
            <div>
                <span>Uyarılı</span>
                <strong data-health-summary="warning"><?= number_format((int)$topicHealthSummary['warning'], 0, ',', '.') ?></strong>
            </div>
        </div>
        <div class="topic-health-card ui-card">
            <span class="topic-health-card-icon tone-danger ui-card"><i class="bi bi-x-octagon"></i></span>
            <div>
                <span>Sorunlu</span>
                <strong data-health-summary="broken"><?= number_format((int)$topicHealthSummary['broken'], 0, ',', '.') ?></strong>
            </div>
        </div>
    </div>

    <div class="topic-health-scan-panel ui-panel">
        <div class="topic-health-scan-copy">
            <span class="topics-ops-kicker"><i class="bi bi-radar"></i> Manuel sağlık taraması</span>
            <h3>Konu linklerini ve görsellerini şimdi kontrol et</h3>
            <p>İndirme linkleri HTTP durum kodu, yönlendirme, bilinen dosya-yok sinyalleri, captcha/koruma sayfaları ve doğrudan indirme ipuçlarıyla kontrol edilir. Görsellerde yerel dosya varlığı ve uzak görsel erişimi taranır.</p>
        </div>
        <div class="topic-health-scan-actions"
             data-health-api="<?= htmlspecialchars($baseUri . '/admin/api/topic-health-scan.php', ENT_QUOTES, 'UTF-8') ?>"
             data-health-token="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
             data-health-batch-size="<?= (int) $topicHealthBatchSize ?>"
             data-health-total="<?= (int)$topicHealthSummary['total'] ?>">
            <button type="button" class="ui-admin-btn ui-admin-btn-primary topic-health-start" id="topicHealthScanStart">
                <i class="bi bi-play-circle"></i> Kontrolü Başlat
            </button>
            <form method="post" action="topics.php?tab=health" class="topic-health-clear-form"<?= adminConfirmAttrs(['message' => 'Tüm konu sağlığı verileri sıfırlanacak ve sağlık taraması geçmişi silinecek. Bu işlem geri alınamaz. Devam edilsin mi?', 'title' => 'Konu sağlığı temizlensin mi?', 'ok' => 'Temizle', 'tone' => 'danger']) ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_topic_health">
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline topic-health-clear-btn">
                    <i class="bi bi-trash3"></i> Temizle
                </button>
            </form>
            <div class="topic-health-progress" aria-live="polite">
                <div class="topic-health-progress-meta">
                    <span id="topicHealthProgressText">Hazır, kontrol bekliyor.</span>
                    <strong id="topicHealthProgressPercent"><?= $healthPercent ?>%</strong>
                </div>
                <div class="topic-health-progress-track">
                    <span id="topicHealthProgressBar" data-ui-style-number="--topic-health-progress:<?= $healthPercent ?>%"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="topic-health-kpi-grid ui-grid">
        <div class="topic-health-kpi">
            <span>Kontrol edilmiş</span>
            <strong data-health-summary="checked"><?= number_format((int)$topicHealthSummary['checked'], 0, ',', '.') ?></strong>
        </div>
        <div class="topic-health-kpi">
            <span>İndirme Linki Sorunlu</span>
            <strong data-health-summary="download_link_issues"><?= number_format((int)$topicHealthSummary['download_link_issues'], 0, ',', '.') ?></strong>
        </div>
        <div class="topic-health-kpi">
            <span>Resimlerde Sorun Var</span>
            <strong data-health-summary="image_issues"><?= number_format((int)($topicHealthSummary['image_issues'] ?? 0), 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="topic-health-filter-panel ui-panel">
        <div class="topic-health-filter-head ui-panel__head">
            <div>
                <span class="topics-ops-kicker"><i class="bi bi-funnel"></i> Saglik filtresi</span>
                <strong><?= number_format((int)$topicHealthTotalFiltered, 0, ',', '.') ?> konu listeleniyor</strong>
            </div>
            <?php if ($hasHealthFilters): ?>
                <a href="topics.php?tab=health" class="topics-filter-reset"><i class="bi bi-x-circle"></i> Filtreleri temizle</a>
            <?php endif; ?>
        </div>
        <form method="get" action="topics.php" class="topic-health-filter-form admin-filter-form">
            <input type="hidden" name="tab" value="health">
            <label class="topics-filter-field topic-health-search">
                <span>Arama</span>
                <i class="bi bi-search"></i>
                <input type="search" name="health_q" value="<?= htmlspecialchars($healthSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Konu, slug veya kategori ara...">
            </label>
            <label class="topics-filter-field">
                <span>Saglik durumu</span>
                <select name="health_status">
                    <option value="" hidden <?= $healthStatusFilter === '' ? 'selected' : '' ?>>Durum sec</option>
                    <?php foreach ($healthStatusFilterOptions as $statusValue => $statusLabel): ?>
                        <option value="<?= htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') ?>" <?= $healthStatusFilter === $statusValue ? 'selected' : '' ?>><?= htmlspecialchars((string)$statusLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="topics-filter-field">
                <span>Sorun tipi</span>
                <select name="health_issue">
                    <?php foreach ($healthIssueOptions as $issueValue => $issueLabel): ?>
                        <option value="<?= htmlspecialchars($issueValue, ENT_QUOTES, 'UTF-8') ?>" <?= $healthIssueFilter === $issueValue ? 'selected' : '' ?>><?= htmlspecialchars($issueLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="topics-filter-submit"><i class="bi bi-sliders2"></i> Filtrele</button>
        </form>
    </div>

    <?= adminRenderTableOpen([
        'Konu',
        'Kategori',
        'Sağlık',
        'İndirme',
        'Görsel',
        'Harici Link',
        'Son Kontrol',
        ['label' => '', 'class' => 'ui-admin-table-head-actions'],
    ], [
        'class' => 'topic-health-table',
        'wrap_class' => 'topics-table-container topic-health-table-wrap',
        'tbody_attrs' => ['id' => 'topicHealthRows'],
        'label' => 'Konu sağlık kayıtları',
    ]) ?>
                <?php if (empty($topicHealthRows)): ?>
                    <?= adminRenderTableEmptyRow(8, [
                                'icon' => 'bi-heart-pulse',
                                'tone' => 'info',
                                'title' => 'Sağlık verisi yok',
                                'description' => 'Kontrolü başlattığınızda yayınlı konular burada listelenir.',
                            ]) ?>
                <?php else: ?>
                    <?php foreach ($topicHealthRows as $row): ?>
                        <?php
                        $rowStatus = (string)($row['health_status'] ?? 'unchecked');
                        $rowStatus = isset($healthStatusMeta[$rowStatus]) ? $rowStatus : 'unchecked';
                        $meta = $healthStatusMeta[$rowStatus];
                        $summary = adminQualityDecodeTopicHealthSummary($row['health_summary'] ?? null);
                        $isChecked = !empty($row['last_checked_at']);
                        $downloads = $summary['downloads'] ?? [];
                        $media = $summary['media'] ?? [];
                        $external = $summary['external_links'] ?? [];
                        $downloadLinkCount = (int)($row['download_link_count'] ?? ($downloads['total'] ?? 0));
                        $downloadIssueCount = (int)($row['download_issue_count'] ?? 0);
                        $imageCount = (int)($row['image_count'] ?? ($media['total'] ?? 0));
                        $imageIssueCount = (int)($row['image_issue_count'] ?? 0);
                        $hasDownloadIssue = $isChecked && ($downloadLinkCount === 0 || $downloadIssueCount > 0 || (int)($downloads['missing'] ?? 0) > 0 || (int)($downloads['broken'] ?? 0) > 0);
                        $hasImageIssue = $isChecked && (empty($row['primary_media_file_id']) || $imageCount === 0 || $imageIssueCount > 0 || (int)($media['missing_primary'] ?? 0) > 0 || (int)($media['broken'] ?? 0) > 0 || (int)($media['warning'] ?? 0) > 0);
                        $hasExternalIssue = $isChecked && ((int)($external['broken'] ?? 0) > 0 || (int)($external['warning'] ?? 0) > 0);
                        $hasManualWarning = $isChecked && !$hasDownloadIssue && !$hasImageIssue && !$hasExternalIssue && $rowStatus === 'warning';
                        $healthReasonLabels = [];
                        if ($hasDownloadIssue) {
                            $healthReasonLabels[] = 'İndirme Linki Sorunlu';
                        }
                        if ($hasImageIssue) {
                            $healthReasonLabels[] = 'Resimlerde Sorun Var';
                        }
                        if ($hasExternalIssue) {
                            $healthReasonLabels[] = 'Harici Link Sorunlu';
                        }
                        if ($hasManualWarning) {
                            $healthReasonLabels[] = 'Manuel Kontrol Gerekli';
                        }
                        $healthBadgeLabel = !empty($healthReasonLabels) ? implode(' + ', $healthReasonLabels) : (string)$meta[0];
                        $healthBadgeTitle = !empty($healthReasonLabels) ? implode(', ', $healthReasonLabels) : $healthBadgeLabel;
                        $badgeStatus = !empty($healthReasonLabels) ? ($rowStatus === 'broken' ? 'broken' : 'warning') : $rowStatus;
                        $badgeMeta = $healthStatusMeta[$badgeStatus] ?? $meta;
                        $lastChecked = !empty($row['last_checked_at']) ? date('d.m.Y H:i', strtotime((string)$row['last_checked_at'])) : '-';
                        $editUrl = $baseUri . '/admin/edit.php?id=' . (int)$row['id'];
                        ?>
                        <tr data-health-topic-row="<?= (int)$row['id'] ?>" data-health-edit-url="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" tabindex="0" title="Duzenleme sayfasina git">
                            <td>
                                <div class="topic-title-info">
                                    <strong><?= htmlspecialchars((string)($row['title'] ?? 'Başlıksız konu')) ?></strong>
                                    <span><?= htmlspecialchars((string)($row['slug'] ?? '')) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars((string)($row['category'] ?? 'Genel')) ?></td>
                            <td>
                                <span class="topic-health-badge is-<?= htmlspecialchars($badgeStatus) ?>" title="<?= htmlspecialchars($healthBadgeTitle, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi <?= htmlspecialchars($badgeMeta[2]) ?>"></i>
                                    <?= htmlspecialchars($healthBadgeLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= $isChecked ? ($hasDownloadIssue ? 'İndirme Linki Sorunlu' : 'Sorun Yok') : 'Kontrol edilmedi' ?></td>
                            <td><?= $isChecked ? ($hasImageIssue ? 'Resimlerde Sorun Var' : 'Sorun Yok') : 'Kontrol edilmedi' ?></td>
                            <td><?= (int)($external['total'] ?? 0) ?> link</td>
                            <td class="ui-admin-table-cell-muted"><?= htmlspecialchars($lastChecked) ?></td>
                            <td>
                                <a href="<?= $baseUri ?>/admin/edit.php?id=<?= (int)$row['id'] ?>" class="btn-icon-minimal edit" title="Düzenle"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
    <?= adminRenderTableClose() ?>
    <?php if ($topicHealthTotalPages > 1): ?>
        <?php
        $healthQs = $_GET;
        $healthQs['tab'] = 'health';
        unset($healthQs['health_page'], $healthQs['page']);
        $healthBaseUrl = 'topics.php?' . (count($healthQs) > 0 ? http_build_query($healthQs) . '&' : '') . 'health_page=';
        echo adminRenderPagination($topicHealthTotalPages, $healthPage, static fn (int $targetPage): string => $healthBaseUrl . $targetPage, [
            'wrapper_class' => 'topic-health-pagination',
            'aria_label' => 'Konu sağlık sayfalama',
        ]);
        ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="moderation-note-modal ui-admin-modal-overlay" id="moderationActionNoteModal" hidden aria-hidden="true">
    <div class="moderation-note-backdrop" data-moderation-action-note-close></div>
    <div class="moderation-note-dialog moderation-action-note-dialog ui-admin-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="moderationActionNoteTitle">
        <div class="moderation-note-header">
            <div class="moderation-action-note-head">
                <span class="moderation-action-note-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
                <div class="moderation-action-note-title-wrap">
                    <h2 class="moderation-note-title" id="moderationActionNoteTitle">Moderasyon notu</h2>
                    <div class="moderation-action-note-subtitle">Kullanıcıya gösterilecek kısa açıklama</div>
                </div>
            </div>
            <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" data-moderation-action-note-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="moderation-note-body moderation-action-note-body">
            <label class="moderation-action-note-label" for="moderationActionNoteText">
                Not metni
                <span>Zorunlu</span>
            </label>
            <textarea class="moderation-action-note-text" id="moderationActionNoteText" rows="5" placeholder="Örn: İçeriği yayınlamadan önce eksik bilgileri tamamlayın."></textarea>
            <div class="moderation-action-note-error" id="moderationActionNoteError" aria-live="polite" hidden></div>
        </div>
        <div class="moderation-note-footer">
            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-moderation-action-note-close>İptal</button>
            <button type="button" class="ui-admin-btn ui-admin-btn-primary" id="moderationActionNoteSubmit"><i class="bi bi-send"></i> Gönder</button>
        </div>
    </div>
</div>

</div>

<script src="<?= asset_url('admin/assets/topics-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
