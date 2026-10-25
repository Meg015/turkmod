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
                $topicNoticeStmt = $pdo->prepare("SELECT id, author_id, title, slug, status FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1");
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

                if ($eventKey !== '' && !($decision === 'approve' && in_array((string) ($topicForNotification['status'] ?? ''), ['published', 'approved'], true))) {
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
                $publicationStmt = $pdo->prepare("SELECT id, author_id, title, slug, status FROM topics WHERE id IN ({$placeholders}) AND deleted_at IS NULL AND status NOT IN ('published', 'approved')");
                $publicationStmt->execute($selectedIds);
                $publicationTopics = $publicationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $params = array_merge(['published'], $selectedIds);
                $stmt = $pdo->prepare("UPDATE topics SET status = ?, published_at = COALESCE(published_at, NOW()), updated_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
                $stmt->execute($params);
                foreach ($publicationTopics as $publicationTopic) {
                    notificationDispatchTopicPublished($pdo, array_replace($publicationTopic, ['status' => 'published']), (string) $publicationTopic['status'], $currentUserId ?: null);
                }
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
$categoryFilter = (int) (filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$viewFilter = ($_GET['view'] ?? 'active') === 'deleted' ? 'deleted' : 'active';
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
$sortFilter = trim((string) ($_GET['sort'] ?? 'newest'));
$allowedSorts = [
    'newest' => 'COALESCE(t.published_at, t.created_at) DESC, t.id DESC',
    'oldest' => 'COALESCE(t.published_at, t.created_at) ASC, t.id ASC',
    'updated' => 'COALESCE(t.updated_at, t.created_at) DESC, t.id DESC',
];
if (!isset($allowedSorts[$sortFilter])) {
    $sortFilter = 'newest';
}
$legacyTab = trim((string) ($_GET['tab'] ?? ''));
if ($legacyTab === 'reports') {
    header('Location: complaints-reports.php?tab=topics');
    exit;
}
if ($legacyTab !== '') {
    $topicListQuery = array_intersect_key($_GET, array_fill_keys(['q', 'category', 'status', 'sort', 'view', 'page'], true));
    header('Location: topics.php' . ($topicListQuery ? '?' . http_build_query($topicListQuery) : ''));
    exit;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();
$topics = [];
$topicCategories = [];
$stats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'revision' => 0,
    'deleted' => 0,
];
$totalPages = 1;
$totalFiltered = 0;
if ($pdo) {
    try {
        $topicCategories = $pdo->query('SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

        if ($categoryFilter > 0) {
            $where[] = 't.category_id = :category_id';
            $params['category_id'] = $categoryFilter;
        }

        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            if ($statusFilter === 'published') {
                $where[] = "t.status IN ('published', 'approved')";
            } else {
                $where[] = 't.status = :status';
                $params['status'] = $statusFilter;
            }
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
                    cat.name AS category,
                    u.username AS author_name,
                    pm.path AS cover_image
             FROM topics t
             LEFT JOIN categories cat ON t.category_id = cat.id
             LEFT JOIN users u ON t.author_id = u.id
             LEFT JOIN media_files pm ON t.primary_media_file_id = pm.id
             {$whereSql}
             ORDER BY {$allowedSorts[$sortFilter]}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $topics = $stmt->fetchAll() ?: [];

        $topicCounts = $pdo->query("SELECT
            COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) AS total,
            COUNT(CASE WHEN deleted_at IS NULL AND status IN ('published', 'approved') THEN 1 END) AS published,
            COUNT(CASE WHEN deleted_at IS NULL AND status = 'draft' THEN 1 END) AS draft,
            COUNT(CASE WHEN deleted_at IS NULL AND status = 'revision' THEN 1 END) AS revision,
            COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) AS deleted
            FROM topics")->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats = array_replace($stats, array_map('intval', $topicCounts));
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
<header class="topics-heading admin-page-heading">
    <div class="admin-page-heading__copy"><h2>Konular <span class="topics-heading-count"><?= number_format((int) $stats['total'], 0, ',', '.') ?></span></h2><p>İçeriği düzenle, yayın durumunu yönet.</p></div>
    <div class="admin-page-heading__actions"><a href="<?= htmlspecialchars($baseUri . '/admin/create.php', ENT_QUOTES, 'UTF-8') ?>" class="topics-button topics-button-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>Yeni konu</a></div>
</header>

<?php require __DIR__ . '/partials/topics-list.php'; ?>

<div class="moderation-note-modal ui-admin-modal-overlay" id="moderationNoteModal" hidden aria-hidden="true">
    <div class="moderation-note-backdrop" data-moderation-note-close></div>
    <div class="moderation-note-dialog ui-admin-modal-shell ui-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="moderationNoteTitle">
        <div class="moderation-note-header ui-modal__head ui-panel__head">
            <h2 class="moderation-note-title" id="moderationNoteTitle">Son moderasyon notu</h2>
            <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" data-moderation-note-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="moderation-note-body ui-modal__body ui-panel__body" id="moderationNoteBody"></div>
    </div>
</div>

<div class="moderation-note-modal ui-admin-modal-overlay" id="moderationActionNoteModal" hidden aria-hidden="true">
    <div class="moderation-note-backdrop" data-moderation-action-note-close></div>
    <div class="moderation-note-dialog moderation-action-note-dialog ui-admin-modal-shell ui-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="moderationActionNoteTitle">
        <div class="moderation-note-header ui-modal__head ui-panel__head">
            <div class="moderation-action-note-head">
                <span class="moderation-action-note-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
                <div class="moderation-action-note-title-wrap">
                    <h2 class="moderation-note-title" id="moderationActionNoteTitle">Moderasyon notu</h2>
                    <div class="moderation-action-note-subtitle">Kullanıcıya gösterilecek kısa açıklama</div>
                </div>
            </div>
            <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" data-moderation-action-note-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="moderation-note-body moderation-action-note-body ui-modal__body ui-panel__body">
            <label class="moderation-action-note-label" for="moderationActionNoteText">
                Not metni
                <span>Zorunlu</span>
            </label>
            <textarea class="moderation-action-note-text" id="moderationActionNoteText" rows="5" placeholder="Örn: İçeriği yayınlamadan önce eksik bilgileri tamamlayın."></textarea>
            <div class="moderation-action-note-error" id="moderationActionNoteError" aria-live="polite" hidden></div>
        </div>
        <div class="moderation-note-footer ui-modal__foot ui-panel__foot">
            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-moderation-action-note-close>İptal</button>
            <button type="button" class="ui-admin-btn ui-admin-btn-primary" id="moderationActionNoteSubmit"><i class="bi bi-send"></i> Gönder</button>
        </div>
    </div>
</div>

</div>

<script src="<?= asset_url('admin/assets/topics-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
