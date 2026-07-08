<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('comments.view', 'Yorumlari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Yorum Yönetimi';

// Filters
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$topicId = (int)($_GET['topic_id'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: comments-manager.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $requiredPermission = $action === 'delete' ? 'comments.delete' : 'comments.edit';
    if (!adminCurrentUserCan($requiredPermission)) {
        adminDenyAction('Yorum islemi yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'comments-manager.php');
    }

    if ($commentId > 0) {
        try {
            $commentStmt = $pdo->prepare("SELECT id, topic_id, user_id, body, status, deleted_at FROM comments WHERE id = ? LIMIT 1");
            $commentStmt->execute([$commentId]);
            $commentRow = $commentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            switch ($action) {
                case 'approve':
                    $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")->execute([$commentId]);
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
                    flash('success', 'Yorum onaylandı.');
                    break;

                case 'reject':
                    $pdo->prepare("UPDATE comments SET status = 'rejected' WHERE id = ?")->execute([$commentId]);
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
                    if ($newBody !== '') {
                        // Check if columns exist
                        $hasEditColumns = false;
                        try {
                            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'is_edited'")->fetchAll();
                            $hasEditColumns = !empty($cols);
                        } catch (Throwable $e) {
                            // Ignore
                        }

                        if ($hasEditColumns) {
                            $pdo->prepare("UPDATE comments SET body = ?, is_edited = 1, edited_at = NOW() WHERE id = ?")->execute([$newBody, $commentId]);
                        } else {
                            $pdo->prepare("UPDATE comments SET body = ? WHERE id = ?")->execute([$newBody, $commentId]);
                        }
                        if (function_exists('invalidatePublicContentCache')) {
                            invalidatePublicContentCache();
                        }
                        flash('success', 'Yorum güncellendi.');
                    } else {
                        flash('error', 'Yorum içeriği boş olamaz.');
                    }
                    break;
            }
        } catch (Throwable $e) {
            flash('error', 'İşlem başarısız: ' . safeErrorMessage($e));
        }
    }

    header('Location: comments-manager.php?' . http_build_query(['status' => $status, 'search' => $search, 'page' => $page]));
    exit;
}

// Build query
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
    $where[] = '(c.body LIKE ? OR u.username LIKE ? OR t.title LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
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

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) FROM comments c WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Get comments
$offset = ($page - 1) * $perPage;
$sql = "SELECT c.*,
        u.username AS author_name, u.avatar as author_avatar,
        t.id as topic_id, t.title as topic_title, t.slug as topic_slug,
        (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id) as reaction_count
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN topics t ON c.topic_id = t.id
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

    <section class="ui-admin-page-hero comments-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-chat-left-text"></i> Moderasyon</span>
            <h2><i class="bi bi-chat-square-text"></i> Yorum Yönetimi</h2>
            <p>Bekleyen, onaylanan, reddedilen ve silinen yorumları tek ekrandan inceleyin.</p>
        </div>
        <div class="ui-admin-page-hero-actions">
            <span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-clock-history"></i> <?= number_format($stats['pending']) ?> bekleyen</span>
        </div>
    </section>

    <!-- Statistics -->
    <div class="admin-stat-grid ui-comment-manager-stats ui-grid">
        <a href="?status=all" class="admin-stat-card stat-info ui-comment-manager-stat-card <?= $status === 'all' ? 'active' : '' ?> ui-card" data-status="total">
            <div class="stat-icon"><i class="bi bi-chat-left-text"></i></div>
            <div class="stat-content">
                <span class="stat-label">Toplam Yorum</span>
                <span class="stat-value"><?= number_format($stats['total']) ?></span>
            </div>
        </a>
        <a href="?status=pending" class="admin-stat-card stat-warning ui-comment-manager-stat-card <?= $status === 'pending' ? 'active' : '' ?> ui-card" data-status="pending">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-content">
                <span class="stat-label">Onay Bekleyen</span>
                <span class="stat-value"><?= number_format($stats['pending']) ?></span>
            </div>
        </a>
        <a href="?status=approved" class="admin-stat-card stat-success ui-comment-manager-stat-card <?= $status === 'approved' ? 'active' : '' ?> ui-card" data-status="approved">
            <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-content">
                <span class="stat-label">Onaylanmış</span>
                <span class="stat-value"><?= number_format($stats['approved']) ?></span>
            </div>
        </a>
        <a href="?status=rejected" class="admin-stat-card stat-danger ui-comment-manager-stat-card <?= $status === 'rejected' ? 'active' : '' ?> ui-card" data-status="rejected">
            <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-content">
                <span class="stat-label">Reddedilmiş</span>
                <span class="stat-value"><?= number_format($stats['rejected']) ?></span>
            </div>
        </a>
        <a href="?status=deleted" class="admin-stat-card stat-info ui-comment-manager-stat-card <?= $status === 'deleted' ? 'active' : '' ?> ui-card" data-status="deleted">
            <div class="stat-icon"><i class="bi bi-trash"></i></div>
            <div class="stat-content">
                <span class="stat-label">Silinmiş</span>
                <span class="stat-value"><?= number_format($stats['deleted']) ?></span>
            </div>
        </a>
    </div>

    <!-- Filters -->
    <div class="ui-comment-manager-filters">
        <form method="get" action="comments-manager.php">
            <div class="ui-comment-manager-filters-row">
                <div class="ui-comment-manager-filter-group">
                    <label class="ui-comment-manager-filter-label">
                        <i class="bi bi-search"></i> Ara
                    </label>
                    <input type="text" name="search" class="ui-comment-manager-filter-input ui-input" placeholder="Yorum, kullanıcı veya konu ara..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="ui-comment-manager-filter-group ui-admin-filter-max-sm">
                    <label class="ui-comment-manager-filter-label">
                        <i class="bi bi-funnel"></i> Durum
                    </label>
                    <select name="status" class="ui-comment-manager-filter-input ui-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Onay Bekleyen</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Onaylanmış</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Reddedilmiş</option>
                        <option value="deleted" <?= $status === 'deleted' ? 'selected' : '' ?>>Silinmiş</option>
                    </select>
                </div>
                <button type="submit" class="ui-comment-manager-filter-btn ui-button">
                    <i class="bi bi-search"></i> Filtrele
                </button>
                <a href="comments-manager.php" class="ui-comment-manager-filter-btn ui-comment-manager-filter-btn-secondary ui-button ui-button--secondary">
                    <i class="bi bi-x-circle"></i> Temizle
                </a>
            </div>
        </form>
    </div>

    <!-- Comments List -->
    <?php if (empty($comments)): ?>
        <div class="ui-comment-manager-empty ui-empty">
            <div class="ui-comment-manager-empty-icon ui-empty">
                <i class="bi bi-inbox"></i>
            </div>
            <div class="ui-comment-manager-empty-title ui-empty">Yorum Bulunamadı</div>
            <div class="ui-comment-manager-empty-text ui-empty">Seçili filtrelere uygun yorum bulunmuyor.</div>
        </div>
    <?php else: ?>
        <div class="ui-comment-manager-comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="ui-comment-manager-comment-card ui-card">
                    <div class="ui-comment-manager-comment-header ui-panel__head">
                        <div class="ui-comment-manager-comment-avatar default-avatar">
                            <?= function_exists('avatarImageHtml') ? avatarImageHtml((string) ($comment['author_name'] ?? 'Anonim'), (string) ($comment['author_avatar'] ?? ''), ['alt' => '']) : '' ?>
                        </div>
                        <div class="ui-comment-manager-comment-meta">
                            <div class="ui-comment-manager-comment-author"><?= htmlspecialchars($comment['author_name'] ?? 'Anonim') ?></div>
                            <div class="ui-comment-manager-comment-info">
                                <span class="ui-comment-manager-comment-info-item">
                                    <i class="bi bi-calendar"></i>
                                    <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                </span>
                                <span class="ui-comment-manager-comment-info-item">
                                    <i class="bi bi-heart"></i>
                                    <?= $comment['reaction_count'] ?> reaksiyon
                                </span>
                                <span class="ui-comment-manager-comment-status <?= $comment['status'] ?>">
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

                    <div id="admin-comment-body-<?= (int) $comment['id'] ?>" class="ui-comment-manager-comment-body ui-panel__body" data-ui-comment-manager-body>
                        <?= nl2br(htmlspecialchars($comment['body'])) ?>
                    </div>

                    <div class="ui-comment-manager-comment-footer ui-panel__foot">
                        <a href="<?= htmlspecialchars(topicUrl((string)($comment['topic_slug'] ?? ''), (int)($comment['topic_id'] ?? 0))) ?>#comment-<?= (int)$comment['id'] ?>"
                           class="ui-comment-manager-comment-topic" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <?= htmlspecialchars($comment['topic_title']) ?>
                        </a>

                        <div class="ui-comment-manager-comment-actions">
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

        <!-- Pagination -->
        <?php if ($total > $perPage): ?>
            <nav class="ui-comment-manager-pagination ui-pagination" aria-label="Yorum sayfalama">
                <?php
                $totalPages = (int)ceil($total / $perPage);
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
