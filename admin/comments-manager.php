<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Media/Support/helpers.php';
adminRequirePermission('comments.view', 'Yorumlari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Yorum Yönetimi';
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

// Filters
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$topicId = (int)($_GET['topic_id'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$commentsManagerBuildWhereClause = static function (string $status, string $search, int $topicId, int $userId): array {
    $where = ['1=1'];
    $params = [];

    if ($status !== 'all') {
        if ($status === 'deleted') {
            $where[] = 'c.deleted_at IS NOT NULL';
        } else {
            $where[] = 'c.status = ?';
            $where[] = 'c.deleted_at IS NULL';
            $params[] = $status;
        }
    } else {
        $where[] = 'c.deleted_at IS NULL';
    }

    if ($search !== '') {
        $where[] = '(c.body LIKE ? OR u.username LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($topicId > 0) {
        $where[] = 'c.topic_id = ?';
        $params[] = $topicId;
    }

    if ($userId > 0) {
        $where[] = 'c.user_id = ?';
        $params[] = $userId;
    }

    return [implode(' AND ', $where), $params];
};

$commentsManagerNormalizeIds = static function (array $ids): array {
    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

    return $ids;
};

$commentsManagerTableExists = static function (PDO $pdo, string $table): bool {
    static $cache = [];
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
    if ($table === '') {
        return false;
    }

    if (!array_key_exists($table, $cache)) {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
    }

    return $cache[$table];
};

$commentsManagerDeleteByIds = static function (PDO $pdo, string $sqlTemplate, array $ids): int {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return 0;
    }

    $affected = 0;
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(str_replace('{ids}', $placeholders, $sqlTemplate));
        $stmt->execute($chunk);
        $affected += $stmt->rowCount();
    }

    return $affected;
};

$commentsManagerCollectTreeRows = static function (PDO $pdo, array $seedRows): array {
    $rowsById = [];
    $pendingIds = [];

    foreach ($seedRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || isset($rowsById[$id])) {
            continue;
        }

        $rowsById[$id] = [
            'id' => $id,
            'topic_id' => (int) ($row['topic_id'] ?? 0),
            'parent_id' => (int) ($row['parent_id'] ?? 0) ?: null,
            'status' => (string) ($row['status'] ?? ''),
            'deleted_at' => $row['deleted_at'] ?? null,
        ];
        $pendingIds[] = $id;
    }

    while ($pendingIds !== []) {
        $batch = array_splice($pendingIds, 0, 500);
        $placeholders = implode(',', array_fill(0, count($batch), '?'));

        $stmt = $pdo->prepare("SELECT id, topic_id, parent_id, status, deleted_at FROM comments WHERE parent_id IN ({$placeholders})");
        $stmt->execute($batch);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($rowsById[$id])) {
                continue;
            }

            $rowsById[$id] = [
                'id' => $id,
                'topic_id' => (int) ($row['topic_id'] ?? 0),
                'parent_id' => (int) ($row['parent_id'] ?? 0) ?: null,
                'status' => (string) ($row['status'] ?? ''),
                'deleted_at' => $row['deleted_at'] ?? null,
            ];
            $pendingIds[] = $id;
        }
    }

    return array_values($rowsById);
};

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: comments-manager.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $requiredPermission = in_array($action, ['delete', 'purge_deleted'], true) ? 'comments.delete' : 'comments.edit';
    if (!adminCurrentUserCan($requiredPermission)) {
        adminDenyAction('Yorum islemi yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'comments-manager.php');
    }

    if ($action === 'purge_deleted') {
        try {
            if ($status !== 'deleted') {
                flash('error', 'Kalıcı silme için önce silinenler görünümüne geçin.');
            } else {
                [$purgeWhereClause, $purgeParams] = $commentsManagerBuildWhereClause('deleted', $search, $topicId, $userId);
                $seedStmt = $pdo->prepare("SELECT c.id, c.topic_id, c.parent_id, c.status, c.deleted_at
                                           FROM comments c
                                           LEFT JOIN users u ON c.user_id = u.id
                                           WHERE {$purgeWhereClause}");
                $seedStmt->execute($purgeParams);
                $seedRows = $seedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $commentRows = $commentsManagerCollectTreeRows($pdo, $seedRows);
                $commentIds = $commentsManagerNormalizeIds(array_column($commentRows, 'id'));

                if ($commentIds === []) {
                    flash('error', 'Kalıcı silinecek yorum bulunamadı.');
                } else {
                    $topicDeltas = [];
                    foreach ($commentRows as $row) {
                        if (function_exists('commentTopicCountsAsVisible') && commentTopicCountsAsVisible($row)) {
                            $topicIdForRow = (int) ($row['topic_id'] ?? 0);
                            if ($topicIdForRow > 0) {
                                $topicDeltas[$topicIdForRow] = ($topicDeltas[$topicIdForRow] ?? 0) + 1;
                            }
                        }
                    }

                    $commentMediaPaths = [];
                    if ($commentsManagerTableExists($pdo, 'comment_media')) {
                        foreach (array_chunk($commentIds, 500) as $chunk) {
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $mediaStmt = $pdo->prepare("SELECT DISTINCT file_path FROM comment_media WHERE comment_id IN ({$placeholders})");
                            $mediaStmt->execute($chunk);
                            foreach ($mediaStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $filePath) {
                                $filePath = trim((string) $filePath);
                                if ($filePath !== '') {
                                    $commentMediaPaths[$filePath] = $filePath;
                                }
                            }
                        }
                    }

                    try {
                        $pdo->beginTransaction();

                        $commentsManagerDeleteByIds($pdo, "DELETE FROM notifications WHERE entity_type = 'comment' AND entity_id IN ({ids})", $commentIds);
                        $commentsManagerDeleteByIds($pdo, "DELETE FROM events_notifications WHERE related_type = 'comment' AND related_id IN ({ids})", $commentIds);
                        $commentsManagerDeleteByIds($pdo, "DELETE FROM activity_logs WHERE subject_type = 'comment' AND subject_id IN ({ids})", $commentIds);
                        $commentsManagerDeleteByIds($pdo, "DELETE FROM user_activity_events WHERE subject_type = 'comment' AND subject_id IN ({ids})", $commentIds);
                        $commentsManagerDeleteByIds($pdo, "DELETE FROM events_point_ledger WHERE subject_type = 'comment' AND subject_id IN ({ids})", $commentIds);
                        $commentsManagerDeleteByIds($pdo, "DELETE FROM admin_action_log WHERE target_type = 'comment' AND target_id IN ({ids})", $commentIds);
                        if ($commentsManagerTableExists($pdo, 'comment_reports')) {
                            $commentsManagerDeleteByIds($pdo, "DELETE FROM comment_reports WHERE comment_id IN ({ids})", $commentIds);
                        }

                        foreach ($topicDeltas as $topicIdForRow => $visibleCount) {
                            if ($visibleCount <= 0) {
                                continue;
                            }

                            $topicUpdateStmt = $pdo->prepare('UPDATE topics SET comment_count = GREATEST(comment_count - ?, 0) WHERE id = ?');
                            $topicUpdateStmt->execute([(int) $visibleCount, (int) $topicIdForRow]);
                        }

                        $commentsManagerDeleteByIds($pdo, 'DELETE FROM comments WHERE id IN ({ids})', $commentIds);

                        $pdo->commit();

                        $fileCleanupFailures = 0;
                        if ($commentMediaPaths !== [] && function_exists('mediaDeleteFile') && function_exists('mediaNormalizeRelativePath')) {
                            $uploadRoot = mediaNormalizeRelativePath((string) ($settings['upload_path'] ?? 'uploads'));
                            if ($uploadRoot === '' || str_contains($uploadRoot, ':')) {
                                $uploadRoot = 'uploads';
                            }

                            $projectRoot = dirname(__DIR__);
                            $uploadBaseCandidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadRoot);
                            $uploadBase = realpath($uploadBaseCandidate) ?: $uploadBaseCandidate;

                            if (is_dir($uploadBase)) {
                                foreach ($commentMediaPaths as $filePath) {
                                    if (!mediaDeleteFile($pdo, $uploadBase, $filePath)) {
                                        $fileCleanupFailures++;
                                    }
                                }
                            }
                        }

                        $message = count($commentIds) . ' yorum ve ilişkili kayıt kalici olarak silindi.';
                        if ($fileCleanupFailures > 0) {
                            $message .= ' ' . $fileCleanupFailures . ' ek dosya silinemedi.';
                        }
                        flash('success', $message);
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        flash('error', 'Kalici silme islemi basarisiz: ' . safeErrorMessage($e));
                    }
                }
            }
        } catch (Throwable $e) {
            flash('error', 'Kalıcı silme hazırlığı başarısız: ' . safeErrorMessage($e));
        }
    } elseif ($commentId > 0) {
        try {
            $commentStmt = $pdo->prepare("SELECT id, topic_id, user_id, body, status, deleted_at, created_at, updated_at FROM comments WHERE id = ? LIMIT 1");
            $commentStmt->execute([$commentId]);
            $commentRow = $commentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            switch ($action) {
                case 'approve':
                    $approvedAt = date('Y-m-d H:i:s');
                    $pdo->prepare("UPDATE comments SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
                    $accessOpened = false;
                    if ($commentRow && (string) $commentRow['status'] !== 'approved' && empty($commentRow['deleted_at']) && function_exists('topicDownloadApproveAccessGrant')) {
                        $accessOpened = topicDownloadApproveAccessGrant($pdo, $settings, $commentRow, $approvedAt);
                    }
                    if ($commentRow) {
                        commentApplyTopicCountDelta($pdo, $commentRow, 'approved', !empty($commentRow['deleted_at']));
                    }
                    if ($commentRow && function_exists('eventsRecordActivity') && (int)$commentRow['user_id'] > 0 && (string)$commentRow['status'] !== 'approved' && empty($commentRow['deleted_at'])) {
                        eventsRecordActivity($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, [
                            'topic_id' => (int)$commentRow['topic_id'],
                            'text_length' => mb_strlen((string)$commentRow['body']),
                        ]);
                    }
                    if (function_exists('invalidatePublicContentCache')) {
                        invalidatePublicContentCache();
                    }
                    if ($commentRow && (int) ($commentRow['user_id'] ?? 0) > 0 && (string) ($commentRow['status'] ?? '') !== 'approved' && empty($commentRow['deleted_at']) && function_exists('notificationDispatch')) {
                        try {
                            $topicStmt = $pdo->prepare("SELECT id, title, slug FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                            $topicStmt->execute([(int) ($commentRow['topic_id'] ?? 0)]);
                            $topicRow = $topicStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                            $topicTitle = trim((string) ($topicRow['title'] ?? 'Konu')) ?: 'Konu';
                            $topicLink = topicUrl((string) ($topicRow['slug'] ?? ''), (int) ($topicRow['id'] ?? $commentRow['topic_id'])) . '#comment-' . $commentId;
                            $payload = [
                                'actor_name' => (string) ($_SESSION['_auth_user_name'] ?? 'Yönetim'),
                                'topic_title' => $topicTitle,
                                'link' => $topicLink,
                                'dedupe_key' => 'comment_approved:' . (int) $commentRow['user_id'] . ':' . $commentId,
                            ];
                            if ($accessOpened) {
                                $payload['title'] = 'İndirme erişiminiz açıldı';
                                $payload['message'] = '“' . $topicTitle . '” konusundaki yorumunuz onaylandı. İndirme bağlantıları artık kullanıma hazır.';
                            }
                            notificationDispatch(
                                $pdo,
                                'comment_approved',
                                (int) $commentRow['user_id'],
                                (int) ($_SESSION['_auth_user_id'] ?? 0) ?: null,
                                'comment',
                                $commentId,
                                $payload
                            );
                        } catch (Throwable $e) {
                            if (function_exists('appLogException')) {
                                appLogException($e, ['source' => 'comments-manager.comment-approved-notification', 'comment_id' => $commentId]);
                            } else {
                                error_log('Comment approval notification failed: ' . $e->getMessage());
                            }
                        }
                    }
                    flash('success', 'Yorum onaylandı.');
                    break;

                case 'reject':
                    $pdo->prepare("UPDATE comments SET status = 'rejected' WHERE id = ?")->execute([$commentId]);
                    if (function_exists('topicDownloadRevokeAccessGrant')) {
                        topicDownloadRevokeAccessGrant($pdo, $commentId, 'comment_rejected');
                    }
                    if ($commentRow) {
                        commentApplyTopicCountDelta($pdo, $commentRow, 'rejected', !empty($commentRow['deleted_at']));
                    }
                    if ($commentRow && function_exists('eventsReverseActivityPoints') && (int)$commentRow['user_id'] > 0 && empty($commentRow['deleted_at']) && (string)$commentRow['status'] === 'approved') {
                        eventsReverseActivityPoints($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, 'comment_rejected');
                    }
                    if (function_exists('invalidatePublicContentCache')) {
                        invalidatePublicContentCache();
                    }
                    flash('success', 'Yorum reddedildi.');
                    break;

                case 'delete':
                    $pdo->prepare("UPDATE comments SET deleted_at = NOW() WHERE id = ?")->execute([$commentId]);
                    if ((string) ($settings['download_access_relock_on_comment_delete'] ?? '1') === '1' && function_exists('topicDownloadRevokeAccessGrant')) {
                        topicDownloadRevokeAccessGrant($pdo, $commentId, 'comment_deleted');
                    }
                    if ($commentRow) {
                        commentApplyTopicCountDelta($pdo, $commentRow, (string)($commentRow['status'] ?? ''), true);
                    }
                    if ($commentRow && function_exists('eventsReverseActivityPoints') && (int)$commentRow['user_id'] > 0 && empty($commentRow['deleted_at']) && (string)$commentRow['status'] === 'approved') {
                        eventsReverseActivityPoints($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, 'comment_deleted');
                    }
                    if (function_exists('invalidatePublicContentCache')) {
                        invalidatePublicContentCache();
                    }
                    flash('success', 'Yorum silindi.');
                    break;

                case 'restore':
                    $pdo->prepare("UPDATE comments SET deleted_at = NULL WHERE id = ?")->execute([$commentId]);
                    if ($commentRow && function_exists('topicDownloadRestoreAccessGrant')) {
                        topicDownloadRestoreAccessGrant($pdo, $settings, array_merge($commentRow, ['deleted_at' => null]));
                    }
                    if ($commentRow) {
                        commentApplyTopicCountDelta($pdo, $commentRow, (string)($commentRow['status'] ?? ''), false);
                        if (function_exists('eventsRecordActivity') && (int)$commentRow['user_id'] > 0 && (string)$commentRow['status'] === 'approved' && !empty($commentRow['deleted_at'])) {
                            eventsRecordActivity($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, [
                                'topic_id' => (int)$commentRow['topic_id'],
                                'text_length' => mb_strlen((string)$commentRow['body']),
                            ]);
                        }
                    }
                    if (function_exists('invalidatePublicContentCache')) {
                        invalidatePublicContentCache();
                    }
                    flash('success', 'Yorum geri yüklendi.');
                    break;

                case 'edit':
                    $newBody = trim($_POST['body'] ?? '');
                    $editReason = trim($_POST['edit_reason'] ?? '');
                    if ($newBody !== '' && $commentRow) {
                        $editResult = commentUpdateWithHistory(
                            $pdo,
                            $commentRow,
                            $newBody,
                            (int) ($_SESSION['_auth_user_id'] ?? 0),
                            $editReason,
                            (string) ($settings['comment_edit_history'] ?? '1') === '1'
                        );
                        if (!empty($editResult['changed']) && function_exists('notificationDispatchCommentEdited')) {
                            try {
                                notificationDispatchCommentEdited(
                                    $pdo,
                                    $commentRow,
                                    (int) ($_SESSION['_auth_user_id'] ?? 0),
                                    (string) ($_SESSION['_auth_user_name'] ?? 'Yönetim'),
                                    $editResult
                                );
                            } catch (Throwable $notificationError) {
                                if (function_exists('appLogException')) {
                                    appLogException($notificationError, [
                                        'source' => 'comments-manager.comment-edit-notification',
                                        'comment_id' => $commentId,
                                    ]);
                                } else {
                                    error_log('Comment edit notification failed: ' . $notificationError->getMessage());
                                }
                            }
                        }
                        if (function_exists('invalidatePublicContentCache')) {
                            invalidatePublicContentCache();
                        }
                        flash('success', !empty($editResult['changed']) ? 'Yorum güncellendi.' : 'Yorum içeriğinde değişiklik yok.');
                    } else {
                        flash('error', 'Yorum içeriği boş olamaz.');
                    }
                    break;
            }
        } catch (Throwable $e) {
            flash('error', 'İşlem başarısız: ' . safeErrorMessage($e));
        }
    }

    $redirectPage = $action === 'purge_deleted' ? 1 : $page;
    header('Location: comments-manager.php?' . http_build_query(['status' => $status, 'search' => $search, 'topic_id' => $topicId, 'user_id' => $userId, 'page' => $redirectPage]));
    exit;
}

// Build query
[$whereClause, $params] = $commentsManagerBuildWhereClause($status, $search, $topicId, $userId);

// Get total count
$countSql = "SELECT COUNT(*) FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Get comments
$offset = ($page - 1) * $perPage;
$sql = "SELECT c.*,
        u.username AS author_name, u.avatar as author_avatar,
        (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id) as reaction_count
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE $whereClause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$comments = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'approved' AND deleted_at IS NULL")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'rejected' AND deleted_at IS NULL")->fetchColumn(),
    'deleted' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE deleted_at IS NOT NULL")->fetchColumn(),
];

$statusFilters = [
    'all' => ['label' => 'Tümü', 'count' => $stats['total'], 'icon' => 'bi-chat-left-text'],
    'pending' => ['label' => 'Bekleyen', 'count' => $stats['pending'], 'icon' => 'bi-hourglass-split'],
    'approved' => ['label' => 'Onaylı', 'count' => $stats['approved'], 'icon' => 'bi-check-circle-fill'],
    'rejected' => ['label' => 'Reddedilmiş', 'count' => $stats['rejected'], 'icon' => 'bi-x-circle-fill'],
    'deleted' => ['label' => 'Silinmiş', 'count' => $stats['deleted'], 'icon' => 'bi-trash'],
];

$previewComment = $comments[0] ?? null;

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

require_once __DIR__ . '/header.php';
?>
<div class="comments-manager">
    <?php if ($successMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="status"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="comments-manager-shell">
        <aside class="comments-manager-sidebar">
            <section class="comments-manager-top ui-card">
                <div class="comments-manager-top__copy">
                    <span class="comments-manager-kicker"><i class="bi bi-chat-left-text"></i> Moderasyon</span>
                    <h2>Yorum Yönetimi</h2>
                    <p>Yalnızca yorumlar, kullanıcılar, durumlar ve işlem akışı.</p>
                </div>
                <div class="comments-manager-top__actions">
                    <a href="?<?= htmlspecialchars(http_build_query(['status' => 'pending', 'search' => $search, 'topic_id' => $topicId, 'user_id' => $userId])) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-hourglass-split"></i> Bekleyenler</a>
                    <a href="?<?= htmlspecialchars(http_build_query(['status' => 'deleted', 'search' => $search, 'topic_id' => $topicId, 'user_id' => $userId])) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Silinenler</a>
                </div>
            </section>

            <section class="comments-manager-facet ui-card">
                <div class="comments-manager-section-head">
                    <i class="bi bi-funnel"></i>
                    <span>Durumlar</span>
                </div>
                <div class="comments-manager-chip-row">
                    <?php foreach ($statusFilters as $statusKey => $statusMeta): ?>
                        <?php
                            $statusQuery = ['status' => $statusKey];
                            if ($search !== '') {
                                $statusQuery['search'] = $search;
                            }
                            if ($topicId > 0) {
                                $statusQuery['topic_id'] = $topicId;
                            }
                            if ($userId > 0) {
                                $statusQuery['user_id'] = $userId;
                            }
                        ?>
                        <a href="?<?= htmlspecialchars(http_build_query($statusQuery)) ?>" class="comments-manager-chip<?= $status === $statusKey ? ' active' : '' ?>">
                            <?= htmlspecialchars($statusMeta['label']) ?>
                            <span><?= number_format((int) $statusMeta['count']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="comments-manager-summary ui-card">
                <div class="comments-manager-section-head">
                    <i class="bi bi-bar-chart-line"></i>
                    <span>Özet</span>
                </div>
                <div class="comments-manager-summary-grid">
                    <div class="comments-manager-summary-card">
                        <strong><?= number_format($stats['total']) ?></strong>
                        <span>Toplam</span>
                    </div>
                    <div class="comments-manager-summary-card">
                        <strong><?= number_format($stats['pending']) ?></strong>
                        <span>Bekleyen</span>
                    </div>
                    <div class="comments-manager-summary-card">
                        <strong><?= number_format($stats['deleted']) ?></strong>
                        <span>Silinmiş</span>
                    </div>
                    <div class="comments-manager-summary-card">
                        <strong><?= number_format($stats['approved']) ?></strong>
                        <span>Onaylı</span>
                    </div>
                </div>
            </section>
        </aside>

        <main class="comments-manager-board ui-card">
            <div class="comments-manager-toolbar">
                <form method="get" action="comments-manager.php" class="comments-manager-search-form">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="topic_id" value="<?= (int) $topicId ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <div class="comments-manager-search-row">
                        <input type="text" name="search" class="ui-comment-manager-filter-input ui-input comments-manager-search" placeholder="Yorum, kullanıcı ara..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Ara</button>
                        <a href="comments-manager.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-circle"></i> Temizle</a>
                    </div>
                </form>
                <?php if ($status === 'deleted' && !empty($comments)): ?>
                    <form method="post" action="comments-manager.php?<?= htmlspecialchars(http_build_query(['status' => $status, 'search' => $search, 'topic_id' => $topicId, 'user_id' => $userId, 'page' => $page])) ?>" class="comments-manager-toolbar__actions" data-admin-confirm="Bu görünümdeki silinen yorumları, yanıtları ve ilişkili kayıtları kalıcı olarak silmek istediğinize emin misiniz?" data-admin-confirm-title="Tümünü kalıcı sil" data-admin-confirm-ok="Kalıcı sil" data-admin-confirm-tone="danger">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="purge_deleted">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm">
                            <i class="bi bi-trash3"></i> Tümünü Sil
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($comments)): ?>
                <div class="ui-comment-manager-empty ui-empty comments-manager-empty">
                    <div class="ui-comment-manager-empty-icon ui-empty">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <div class="ui-comment-manager-empty-title ui-empty">Yorum Bulunamadı</div>
                    <div class="ui-comment-manager-empty-text ui-empty">Seçili filtrelere uygun yorum bulunmuyor.</div>
                </div>
            <?php else: ?>
                <div class="comments-manager-list-head">
                    <span></span>
                    <span>Yorum</span>
                    <span>Kullanıcı</span>
                    <span>Durum</span>
                    <span>Tarih</span>
                    <span>İşlem</span>
                </div>

                <div class="ui-comment-manager-comments-list comments-manager-list">
                    <?php foreach ($comments as $comment): ?>
                        <div class="ui-comment-manager-comment-card ui-card comments-manager-card">
                            <div class="ui-comment-manager-comment-header ui-panel__head comments-manager-card__head">
                                <div class="ui-comment-manager-comment-avatar default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml((string) ($comment['author_name'] ?? 'Anonim'), (string) ($comment['author_avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <div class="ui-comment-manager-comment-meta comments-manager-card__meta">
                                    <div class="ui-comment-manager-comment-author"><?= htmlspecialchars($comment['author_name'] ?? 'Anonim') ?></div>
                                    <div class="ui-comment-manager-comment-info">
                                        <span class="ui-comment-manager-comment-info-item">
                                            <i class="bi bi-calendar"></i>
                                            <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                        </span>
                                        <span class="ui-comment-manager-comment-info-item">
                                            <i class="bi bi-heart"></i>
                                            <?= number_format((int) $comment['reaction_count']) ?> reaksiyon
                                        </span>
                                        <span class="ui-comment-manager-comment-status <?= htmlspecialchars((string) $comment['status']) ?>">
                                            <?php if ($comment['status'] === 'pending'): ?>
                                                <i class="bi bi-clock"></i> Bekliyor
                                            <?php elseif ($comment['status'] === 'approved'): ?>
                                                <i class="bi bi-check-circle"></i> Onaylı
                                            <?php else: ?>
                                                <i class="bi bi-x-circle"></i> Reddedildi
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div id="admin-comment-body-<?= (int) $comment['id'] ?>" class="ui-comment-manager-comment-body ui-panel__body comments-manager-card__body" data-ui-comment-manager-body>
                                <?= nl2br(htmlspecialchars($comment['body'])) ?>
                            </div>

                            <div class="ui-comment-manager-comment-footer ui-panel__foot comments-manager-card__foot">
                                <div class="comments-manager-card__notes">
                                    <span class="comments-manager-note"><i class="bi bi-chat-text"></i> Yorum #<?= (int) $comment['id'] ?></span>
                                    <span class="comments-manager-note"><i class="bi bi-person"></i> <?= htmlspecialchars((string) ($comment['author_name'] ?? 'Anonim')) ?></span>
                                </div>

                                <div class="ui-comment-manager-comment-actions comments-manager-card__actions">
                                    <?php if ($comment['deleted_at']): ?>
                                        <form method="post" class="ui-admin-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <button type="submit" class="ui-comment-manager-action-btn approve">
                                                <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="ui-comment-manager-action-btn edit" data-comment-edit="<?= $comment['id'] ?>" data-comment-body="<?= htmlspecialchars((string) $comment['body'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </button>
                                        <?php if ($comment['status'] === 'pending'): ?>
                                            <form method="post" class="ui-admin-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <button type="submit" class="ui-comment-manager-action-btn approve">
                                                    <i class="bi bi-check-lg"></i> Onayla
                                                </button>
                                            </form>
                                            <form method="post" class="ui-admin-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <button type="submit" class="ui-comment-manager-action-btn reject">
                                                    <i class="bi bi-x-lg"></i> Reddet
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="ui-admin-inline-form" data-admin-confirm="Bu yorumu silmek istediğinize emin misiniz?" data-admin-confirm-title="Yorum silinsin mi?" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <button type="submit" class="ui-comment-manager-action-btn delete">
                                                <i class="bi bi-trash"></i> Sil
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($previewComment): ?>
                    <div class="comments-manager-preview">
                        <section class="comments-manager-preview-card ui-card">
                            <div class="comments-manager-section-head">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Yorum Önizlemesi</span>
                            </div>
                            <p class="comments-manager-preview-text"><?= htmlspecialchars(mb_strimwidth((string) $previewComment['body'], 0, 360, '...')) ?></p>
                            <div class="comments-manager-preview-tags">
                                <span class="comments-manager-preview-tag">#<?= (int) $previewComment['id'] ?></span>
                                <span class="comments-manager-preview-tag"><?= htmlspecialchars((string) ($previewComment['author_name'] ?? 'Anonim')) ?></span>
                                <span class="comments-manager-preview-tag">
                                    <?php if ($previewComment['status'] === 'pending'): ?>Bekliyor<?php elseif ($previewComment['status'] === 'approved'): ?>Onaylı<?php else: ?>Reddedildi<?php endif; ?>
                                </span>
                                <span class="comments-manager-preview-tag"><?= date('d.m.Y H:i', strtotime((string) $previewComment['created_at'])) ?></span>
                            </div>
                        </section>
                        <section class="comments-manager-preview-card comments-manager-preview-card--actions ui-card">
                            <div class="comments-manager-section-head">
                                <i class="bi bi-lightning-charge"></i>
                                <span>Hızlı İşlem</span>
                            </div>
                            <p class="comments-manager-preview-text">Onayla, gizle, sil, not ekle ve kullanıcıyı kısıtla gibi işlemler burada toplanır.</p>
                            <div class="comments-manager-preview-actions">
                                <span class="comments-manager-preview-tag">Onayla</span>
                                <span class="comments-manager-preview-tag">Gizle</span>
                                <span class="comments-manager-preview-tag">Sil</span>
                                <span class="comments-manager-preview-tag">Banla</span>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>

                <?php if ($total > $perPage): ?>
                    <nav class="ui-comment-manager-pagination ui-pagination comments-manager-pagination" aria-label="Yorum sayfalama">
                        <?php
                        $totalPages = (int) ceil($total / $perPage);
                        $queryParams = ['status' => $status, 'search' => $search];

                        if ($page > 1):
                            $queryParams['page'] = $page - 1;
                        ?>
                            <a href="?<?= http_build_query($queryParams) ?>" class="ui-comment-manager-page-btn ui-pagination__page">
                                <i class="bi bi-chevron-left"></i> Önceki
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++):
                            $queryParams['page'] = $i;
                        ?>
                            <a href="?<?= http_build_query($queryParams) ?>"
                               class="ui-comment-manager-page-btn ui-pagination__page <?= $i === $page ? 'active' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>>
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages):
                            $queryParams['page'] = $page + 1;
                        ?>
                            <a href="?<?= http_build_query($queryParams) ?>" class="ui-comment-manager-page-btn ui-pagination__page">
                                Sonraki <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="ui-comment-manager-edit-modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle" hidden>
    <div class="ui-comment-manager-edit-overlay" data-ui-modal-close></div>
    <div class="ui-comment-manager-edit-content ui-section" tabindex="-1">
        <div class="ui-comment-manager-edit-header ui-panel__head">
            <h3 class="ui-comment-manager-edit-title" id="editModalTitle">
                <i class="bi bi-pencil-square"></i>
                Yorumu Düzenle
            </h3>
            <button type="button" class="ui-comment-manager-edit-close" aria-label="Kapat" data-ui-modal-close>
                &times;
            </button>
        </div>
        <form method="post" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="comment_id" id="editCommentId">
            <div class="ui-comment-manager-edit-body ui-panel__body">
                <div class="ui-comment-manager-edit-form-group">
                    <label class="ui-comment-manager-edit-label" for="editCommentBody">
                        <i class="bi bi-chat-text"></i> Yorum İçeriği
                    </label>
                    <textarea name="body" id="editCommentBody" class="ui-comment-manager-edit-textarea" required></textarea>
                </div>
                <div class="ui-comment-manager-edit-form-group">
                    <label class="ui-comment-manager-edit-label" for="editCommentReason">
                        <i class="bi bi-card-text"></i> Düzenleme Nedeni <span class="text-muted">(isteğe bağlı)</span>
                    </label>
                    <textarea name="edit_reason" id="editCommentReason" class="ui-comment-manager-edit-textarea" maxlength="255" rows="3" placeholder="Kullanıcıya gösterilecek kısa açıklama..."></textarea>
                </div>
            </div>
            <div class="ui-comment-manager-edit-footer ui-panel__foot">
                <button type="button" class="ui-comment-manager-edit-btn ui-comment-manager-edit-btn-cancel" data-ui-modal-close>
                    <i class="bi bi-x-circle"></i> İptal
                </button>
                <button type="submit" class="ui-comment-manager-edit-btn ui-comment-manager-edit-btn-save">
                    <i class="bi bi-check-circle"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset_url('admin/assets/comments-manager-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
