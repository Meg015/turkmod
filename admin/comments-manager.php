<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Media/Support/helpers.php';
adminRequirePermission('comments.view', 'Yorumlari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Yorum Yönetimi';
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
$currentUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
$canManageCommentUsers = $currentUserId > 0 && userHasPermission($pdo, $currentUserId, 'users.edit');
$canViewCommentUserDetails = $currentUserId > 0 && ($canManageCommentUsers || userHasPermission($pdo, $currentUserId, 'admin.access'));

// Filters
$status = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'deleted'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}
$search = trim($_GET['search'] ?? '');
$topicId = (int)($_GET['topic_id'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$userState = (string)($_GET['user_state'] ?? 'all');
$allowedUserStates = ['all', 'banned', 'restricted'];
if (!in_array($userState, $allowedUserStates, true)) {
    $userState = 'all';
}
$dateRange = (string)($_GET['date_range'] ?? 'all');
$allowedDateRanges = ['all', 'today', 'week', 'month'];
if (!in_array($dateRange, $allowedDateRanges, true)) {
    $dateRange = 'all';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();

$commentsManagerBuildWhereClause = static function (string $status, string $search, int $topicId, int $userId, string $userState, string $dateRange, bool $hasRestrictionTable): array {
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
        $where[] = '(c.body LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR t.title LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
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

    if ($userState === 'banned') {
        $where[] = 'COALESCE(u.is_banned, 0) = 1';
    } elseif ($userState === 'restricted') {
        $where[] = $hasRestrictionTable
            ? "EXISTS (SELECT 1 FROM user_restrictions ur WHERE ur.user_id = c.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()))"
            : '0=1';
    }

    if ($dateRange === 'today') {
        $where[] = 'c.created_at >= CURDATE()';
    } elseif ($dateRange === 'week') {
        $where[] = 'c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    } elseif ($dateRange === 'month') {
        $where[] = 'c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
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

$commentsManagerHasRestrictionTable = function_exists('usersTableExists')
    ? usersTableExists($pdo, 'user_restrictions')
    : $commentsManagerTableExists($pdo, 'user_restrictions');

$commentsManagerFilterQuery = static function (array $overrides = []) use ($status, $search, $topicId, $userId, $userState, $dateRange): array {
    $query = [
        'status' => $status,
        'search' => $search,
        'topic_id' => $topicId,
        'user_id' => $userId,
        'user_state' => $userState,
        'date_range' => $dateRange,
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return array_filter($query, static function ($value, string $key): bool {
        if ($value === null || $value === '') {
            return false;
        }
        if (in_array($key, ['topic_id', 'user_id', 'page'], true)) {
            return (int)$value > 0;
        }
        if (in_array($key, ['status', 'user_state', 'date_range'], true)) {
            return $value !== 'all';
        }
        return true;
    }, ARRAY_FILTER_USE_BOTH);
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

$commentsManagerParseMentions = static function (PDO $pdo, string $body): array {
    preg_match_all('/@([\w\-]+)/', $body, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $usernames = array_values(array_unique(array_filter(array_map('strval', $matches[1]))));
    if ($usernames === []) {
        return [];
    }

    try {
        $lookupColumn = (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username'))
            ? 'username'
            : 'name';
        $selectName = $lookupColumn === 'username' ? 'username' : 'name';
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $sql = "SELECT id, {$selectName} AS mention_name FROM users WHERE {$lookupColumn} IN ({$placeholders})";
        if (!function_exists('usersColumnExists') || usersColumnExists($pdo, 'users', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 10';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($usernames);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'comments-manager.parse-mentions']);
        }
    }

    return [];
};

$commentsManagerDispatchApprovalNotifications = static function (PDO $pdo, array $commentRow, bool $accessOpened) use ($commentsManagerTableExists, $commentsManagerParseMentions): void {
    $commentId = (int) ($commentRow['id'] ?? 0);
    $topicId = (int) ($commentRow['topic_id'] ?? 0);
    $commentAuthorId = (int) ($commentRow['user_id'] ?? 0);
    if ($commentId <= 0 || $topicId <= 0 || !empty($commentRow['deleted_at']) || !function_exists('notificationDispatch')) {
        return;
    }

    try {
        $topicStmt = $pdo->prepare("SELECT id, author_id, title, slug FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $topicStmt->execute([$topicId]);
        $topicRow = $topicStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($topicRow === []) {
            return;
        }

        $topicTitle = trim((string) ($topicRow['title'] ?? 'Konu')) ?: 'Konu';
        $topicLink = topicUrl((string) ($topicRow['slug'] ?? ''), (int) ($topicRow['id'] ?? $topicId));
        $commentLink = $topicLink . '#comment-' . $commentId;
        $adminActorId = (int) ($_SESSION['_auth_user_id'] ?? 0) ?: null;
        $adminActorName = (string) ($_SESSION['_auth_user_name'] ?? 'Yönetim');

        $commentAuthorName = 'Bir kullanıcı';
        if ($commentAuthorId > 0) {
            try {
                $authorStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $authorStmt->execute([$commentAuthorId]);
                $authorName = trim((string) $authorStmt->fetchColumn());
                if ($authorName !== '') {
                    $commentAuthorName = $authorName;
                }
            } catch (Throwable $e) {
                error_log('Comment approval author lookup failed: ' . $e->getMessage());
            }

            $approvalPayload = [
                'actor_name' => $adminActorName,
                'topic_title' => $topicTitle,
                'link' => $commentLink,
                'dedupe_key' => 'comment_approved:' . $commentAuthorId . ':' . $commentId,
            ];
            if ($accessOpened) {
                $approvalPayload['title'] = 'İndirme erişiminiz açıldı';
                $approvalPayload['message'] = '"' . $topicTitle . '" konusundaki yorumunuz onaylandı. İndirme bağlantıları artık kullanıma hazır.';
            }
            notificationDispatch($pdo, 'comment_approved', $commentAuthorId, $adminActorId, 'comment', $commentId, $approvalPayload);
        }

        $basePayload = [
            'actor_name' => $commentAuthorName,
            'topic_title' => $topicTitle,
            'link' => $commentLink,
        ];
        $notifiedRecipients = [];
        $notifyRecipient = static function (int $recipientId, string $eventKey) use (
            $pdo,
            $commentId,
            $commentAuthorId,
            $basePayload,
            &$notifiedRecipients
        ): bool {
            if ($recipientId <= 0 || isset($notifiedRecipients[$recipientId])) {
                return false;
            }

            $sent = notificationDispatch(
                $pdo,
                $eventKey,
                $recipientId,
                $commentAuthorId > 0 ? $commentAuthorId : null,
                'comment',
                $commentId,
                $basePayload
            );
            if ($sent) {
                $notifiedRecipients[$recipientId] = true;
            }

            return $sent;
        };

        $notifyRecipient((int) ($topicRow['author_id'] ?? 0), 'comment_on_topic');

        $parentId = (int) ($commentRow['parent_id'] ?? 0);
        if ($parentId > 0) {
            try {
                $parentStmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ? LIMIT 1');
                $parentStmt->execute([$parentId]);
                $notifyRecipient((int) $parentStmt->fetchColumn(), 'comment_reply');
            } catch (Throwable $e) {
                error_log('Comment approval parent notification lookup failed: ' . $e->getMessage());
            }
        }

        $mentionedUsers = $commentAuthorId > 0
            ? $commentsManagerParseMentions($pdo, (string) ($commentRow['body'] ?? ''))
            : [];
        $mentionRecipientIds = array_map('intval', array_keys($mentionedUsers));
        $mentionsTableReady = $commentAuthorId > 0 && $commentsManagerTableExists($pdo, 'comment_mentions');
        if ($mentionsTableReady && $mentionedUsers !== []) {
            try {
                $mentionStmt = $pdo->prepare('INSERT IGNORE INTO comment_mentions (comment_id, mentioned_user_id, mentioner_user_id, created_at) VALUES (?, ?, ?, NOW())');
                foreach ($mentionedUsers as $mentionedUserId => $mentionedName) {
                    $mentionStmt->execute([$commentId, (int) $mentionedUserId, $commentAuthorId]);
                }
                if (function_exists('usersColumnExists') && usersColumnExists($pdo, 'comments', 'mention_count')) {
                    $pdo->prepare('UPDATE comments SET mention_count = ? WHERE id = ?')->execute([count($mentionedUsers), $commentId]);
                }
                $mentionRowsStmt = $pdo->prepare('SELECT mentioned_user_id FROM comment_mentions WHERE comment_id = ? AND is_notified = 0');
                $mentionRowsStmt->execute([$commentId]);
                $mentionRecipientIds = array_map('intval', $mentionRowsStmt->fetchAll(PDO::FETCH_COLUMN) ?: $mentionRecipientIds);
            } catch (Throwable $e) {
                if (function_exists('appLogException')) {
                    appLogException($e, ['source' => 'comments-manager.comment-approval-mentions', 'comment_id' => $commentId]);
                }
            }
        }

        foreach (array_unique($mentionRecipientIds) as $mentionedUserId) {
            if ($notifyRecipient((int) $mentionedUserId, 'comment_mention') && $mentionsTableReady) {
                try {
                    $pdo->prepare('UPDATE comment_mentions SET is_notified = 1 WHERE comment_id = ? AND mentioned_user_id = ?')
                        ->execute([$commentId, (int) $mentionedUserId]);
                } catch (Throwable $e) {
                    error_log('Comment approval mention notified flag failed: ' . $e->getMessage());
                }
            }
        }

        if ($commentsManagerTableExists($pdo, 'topic_favorites')) {
            $favoriteStmt = $pdo->prepare('SELECT user_id FROM topic_favorites WHERE topic_id = ?');
            $favoriteStmt->execute([$topicId]);
            foreach ($favoriteStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $favoriteUserId) {
                $notifyRecipient((int) $favoriteUserId, 'favorite_topic_comment');
            }
        }
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'comments-manager.comment-approval-notifications', 'comment_id' => $commentId]);
        } else {
            error_log('Comment approval notifications failed: ' . $e->getMessage());
        }
    }
};

$commentsManagerBulkActions = [
    'bulk_approve' => 'approve',
    'bulk_reject' => 'reject',
    'bulk_delete' => 'delete',
    'bulk_restore' => 'restore',
];

$commentsManagerApplyCommentAction = static function (PDO $pdo, array $settings, array $commentRow, string $action) use ($commentsManagerDispatchApprovalNotifications): bool {
    $commentId = (int)($commentRow['id'] ?? 0);
    if ($commentId <= 0) {
        return false;
    }

    switch ($action) {
        case 'approve':
            if ((string)($commentRow['status'] ?? '') === 'approved') {
                return false;
            }

            $approvedAt = date('Y-m-d H:i:s');
            $pdo->prepare("UPDATE comments SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
            $accessOpened = false;
            if (empty($commentRow['deleted_at']) && function_exists('topicDownloadApproveAccessGrant')) {
                $accessOpened = topicDownloadApproveAccessGrant($pdo, $settings, $commentRow, $approvedAt);
            }
            commentApplyTopicCountDelta($pdo, $commentRow, 'approved', !empty($commentRow['deleted_at']));
            if (function_exists('eventsRecordActivity') && (int)$commentRow['user_id'] > 0 && empty($commentRow['deleted_at'])) {
                eventsRecordActivity($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, [
                    'topic_id' => (int)$commentRow['topic_id'],
                    'text_length' => mb_strlen((string)$commentRow['body']),
                ]);
            }
            $commentsManagerDispatchApprovalNotifications($pdo, $commentRow, $accessOpened);
            if (function_exists('invalidatePublicContentCache')) {
                invalidatePublicContentCache();
            }
            return true;

        case 'reject':
            if ((string)($commentRow['status'] ?? '') === 'rejected') {
                return false;
            }

            $pdo->prepare("UPDATE comments SET status = 'rejected' WHERE id = ?")->execute([$commentId]);
            if (function_exists('topicDownloadRevokeAccessGrant')) {
                topicDownloadRevokeAccessGrant($pdo, $commentId, 'comment_rejected');
            }
            commentApplyTopicCountDelta($pdo, $commentRow, 'rejected', !empty($commentRow['deleted_at']));
            if (function_exists('eventsReverseActivityPoints') && (int)$commentRow['user_id'] > 0 && empty($commentRow['deleted_at']) && (string)$commentRow['status'] === 'approved') {
                eventsReverseActivityPoints($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, 'comment_rejected');
            }
            if (function_exists('invalidatePublicContentCache')) {
                invalidatePublicContentCache();
            }
            return true;

        case 'delete':
            if (!empty($commentRow['deleted_at'])) {
                return false;
            }

            $pdo->prepare("UPDATE comments SET deleted_at = NOW() WHERE id = ?")->execute([$commentId]);
            if ((string)($settings['download_access_relock_on_comment_delete'] ?? '1') === '1' && function_exists('topicDownloadRevokeAccessGrant')) {
                topicDownloadRevokeAccessGrant($pdo, $commentId, 'comment_deleted');
            }
            commentApplyTopicCountDelta($pdo, $commentRow, (string)($commentRow['status'] ?? ''), true);
            if (function_exists('eventsReverseActivityPoints') && (int)$commentRow['user_id'] > 0 && (string)$commentRow['status'] === 'approved') {
                eventsReverseActivityPoints($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, 'comment_deleted');
            }
            if (function_exists('invalidatePublicContentCache')) {
                invalidatePublicContentCache();
            }
            return true;

        case 'restore':
            if (empty($commentRow['deleted_at'])) {
                return false;
            }

            $pdo->prepare("UPDATE comments SET deleted_at = NULL WHERE id = ?")->execute([$commentId]);
            if (function_exists('topicDownloadRestoreAccessGrant')) {
                topicDownloadRestoreAccessGrant($pdo, $settings, array_merge($commentRow, ['deleted_at' => null]));
            }
            commentApplyTopicCountDelta($pdo, $commentRow, (string)($commentRow['status'] ?? ''), false);
            if (function_exists('eventsRecordActivity') && (int)$commentRow['user_id'] > 0 && (string)$commentRow['status'] === 'approved') {
                eventsRecordActivity($pdo, (int)$commentRow['user_id'], 'comment_created', 'comment', $commentId, [
                    'topic_id' => (int)$commentRow['topic_id'],
                    'text_length' => mb_strlen((string)$commentRow['body']),
                ]);
            }
            if (function_exists('invalidatePublicContentCache')) {
                invalidatePublicContentCache();
            }
            return true;
    }

    return false;
};

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: comments-manager.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $userModerationActions = ['ban', 'unban', 'add_restriction'];
    $commentBulkAction = $commentsManagerBulkActions[$action] ?? null;
    if (in_array($action, $userModerationActions, true)) {
        if (!$canManageCommentUsers) {
            adminDenyAction('Kullanici moderasyonu icin gerekli izin hesabiniza tanimlanmamis.', 'comments-manager.php');
        }
    } else {
        $requiredPermission = in_array($action, ['delete', 'purge_deleted', 'bulk_delete'], true) ? 'comments.delete' : 'comments.edit';
        if (!adminCurrentUserCan($requiredPermission)) {
            adminDenyAction('Yorum islemi yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'comments-manager.php');
        }
    }

    if ($action === 'purge_deleted') {
        try {
            if ($status !== 'deleted') {
                flash('error', 'Kalıcı silme için önce silinenler görünümüne geçin.');
            } else {
                [$purgeWhereClause, $purgeParams] = $commentsManagerBuildWhereClause('deleted', $search, $topicId, $userId, $userState, $dateRange, $commentsManagerHasRestrictionTable);
                $seedStmt = $pdo->prepare("SELECT c.id, c.topic_id, c.parent_id, c.status, c.deleted_at
                                           FROM comments c
                                           LEFT JOIN users u ON c.user_id = u.id
                                           LEFT JOIN topics t ON t.id = c.topic_id
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
    } elseif ($commentBulkAction !== null) {
        $selectedCommentIds = $commentsManagerNormalizeIds((array)($_POST['comment_ids'] ?? []));
        try {
            if ($selectedCommentIds === []) {
                flash('error', 'Toplu işlem için en az bir yorum seçmelisiniz.');
            } else {
                $commentRowsById = [];
                foreach (array_chunk($selectedCommentIds, 500) as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $bulkStmt = $pdo->prepare("SELECT id, topic_id, parent_id, user_id, body, status, deleted_at, created_at, updated_at FROM comments WHERE id IN ({$placeholders})");
                    $bulkStmt->execute($chunk);
                    foreach ($bulkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                        $commentRowsById[(int)$row['id']] = $row;
                    }
                }

                $processed = 0;
                $skipped = 0;
                $failed = 0;
                foreach ($selectedCommentIds as $selectedCommentId) {
                    $commentRow = $commentRowsById[$selectedCommentId] ?? null;
                    if (!$commentRow) {
                        $skipped++;
                        continue;
                    }

                    try {
                        if ($commentsManagerApplyCommentAction($pdo, $settings, $commentRow, $commentBulkAction)) {
                            $processed++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $bulkActionError) {
                        $failed++;
                        if (function_exists('appLogException')) {
                            appLogException($bulkActionError, [
                                'source' => 'comments-manager.bulk-comment-action',
                                'action' => $commentBulkAction,
                                'comment_id' => $selectedCommentId,
                            ]);
                        } else {
                            error_log('Bulk comment action failed: ' . $bulkActionError->getMessage());
                        }
                    }
                }

                if ($processed > 0) {
                    $message = $processed . ' yorum için toplu işlem tamamlandı.';
                    if ($skipped > 0) {
                        $message .= ' ' . $skipped . ' yorum atlandı.';
                    }
                    if ($failed > 0) {
                        $message .= ' ' . $failed . ' yorum işlenemedi.';
                    }
                    flash('success', $message);
                } elseif ($failed > 0) {
                    flash('error', 'Seçili yorumlar işlenemedi.');
                } else {
                    flash('error', 'Seçili yorumlarda değişiklik yapılmadı.');
                }
            }
        } catch (Throwable $e) {
            flash('error', 'Toplu işlem başarısız: ' . safeErrorMessage($e));
        }
    } elseif (in_array($action, $userModerationActions, true)) {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        try {
            if ($targetUserId <= 0) {
                flash('error', 'Gecersiz kullanici.');
            } elseif ($targetUserId === $currentUserId) {
                flash('error', 'Kendi hesabiniz icin bu islemi yapamazsiniz.');
            } else {
                switch ($action) {
                    case 'ban':
                        $reason = trim((string)($_POST['ban_reason'] ?? ($_POST['reason'] ?? '')));
                        if ($reason === '') {
                            flash('error', 'Yasaklama icin gerekce zorunludur.');
                            break;
                        }
                        usersBan($pdo, $targetUserId, $reason);
                        adminAuditLogger()->logAction($pdo, 'ban', 'user', $targetUserId, $reason, ['is_banned' => 0], ['is_banned' => 1], true);
                        flash('success', 'Kullanici banlandi.');
                        break;

                    case 'unban':
                        $reason = trim((string)($_POST['reason'] ?? ''));
                        $oldBanReasonStmt = $pdo->prepare("SELECT ban_reason FROM users WHERE id = ?");
                        $oldBanReasonStmt->execute([$targetUserId]);
                        $oldBanReason = (string)($oldBanReasonStmt->fetchColumn() ?: '');
                        usersUnban($pdo, $targetUserId);
                        adminAuditLogger()->logAction($pdo, 'unban', 'user', $targetUserId, $reason, ['is_banned' => 1, 'ban_reason' => $oldBanReason], ['is_banned' => 0], true);
                        if (function_exists('usersDispatchAccountNotification')) {
                            usersDispatchAccountNotification($pdo, 'user_unbanned', $targetUserId, $currentUserId, 'Hesabinizdaki ban kaldirildi.' . ($reason !== '' ? ' Gerekce: ' . $reason : ''), 'success');
                        }
                        flash('success', 'Kullanici bani kaldirildi.');
                        break;

                    case 'add_restriction':
                        $restrictReason = trim((string)($_POST['restrict_reason'] ?? ''));
                        if ($restrictReason === '') {
                            flash('error', 'Kisitlama icin gerekce zorunludur.');
                            break;
                        }
                        $restrictTypes = $_POST['restrict_types'] ?? [];
                        if (empty($restrictTypes)) {
                            $singleType = (string)($_POST['restrict_type'] ?? '');
                            $restrictTypes = $singleType !== '' ? [$singleType] : ['all'];
                        }
                        $restrictDays = (int)($_POST['restrict_days'] ?? 0);
                        $pdo->beginTransaction();
                        try {
                            foreach ($restrictTypes as $restrictType) {
                                $restrictType = (string)$restrictType;
                                usersAddRestriction($pdo, $targetUserId, $restrictType, $restrictReason, $restrictDays, $currentUserId);
                                adminAuditLogger()->logAction($pdo, 'restrict', 'user', $targetUserId, $restrictReason, [], ['type' => $restrictType, 'days' => $restrictDays], false);
                                if (function_exists('usersDispatchAccountNotification')) {
                                    usersDispatchAccountNotification($pdo, 'user_restricted', $targetUserId, $currentUserId, usersGetRestrictionTypeLabel($restrictType) . ' kisitlamasi eklendi. Sebep: ' . $restrictReason, 'warning');
                                }
                            }
                            $pdo->commit();
                            flash('success', count($restrictTypes) . ' adet kisitlama basariyla eklendi.');
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $e;
                        }
                        break;
                }
            }
        } catch (Throwable $e) {
            flash('error', 'Kullanici islemi basarisiz: ' . safeErrorMessage($e));
        }
    } elseif ($commentId > 0) {
        try {
            $commentStmt = $pdo->prepare("SELECT id, topic_id, parent_id, user_id, body, status, deleted_at, created_at, updated_at FROM comments WHERE id = ? LIMIT 1");
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
                    if ($commentRow && (string) ($commentRow['status'] ?? '') !== 'approved') {
                        $commentsManagerDispatchApprovalNotifications($pdo, $commentRow, $accessOpened);
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
    header('Location: comments-manager.php?' . http_build_query($commentsManagerFilterQuery(['page' => $redirectPage])));
    exit;
}

// Build query
[$whereClause, $params] = $commentsManagerBuildWhereClause($status, $search, $topicId, $userId, $userState, $dateRange, $commentsManagerHasRestrictionTable);

// Get total count
$countSql = "SELECT COUNT(*) FROM comments c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN topics t ON t.id = c.topic_id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Get comments
$offset = ($page - 1) * $perPage;
$authorActiveRestrictionCountSql = $commentsManagerHasRestrictionTable
    ? "(SELECT COUNT(*) FROM user_restrictions ur WHERE ur.user_id = c.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()))"
    : "0";
$sql = "SELECT c.*,
        u.username AS author_name, u.avatar as author_avatar, u.is_banned AS author_is_banned,
        {$authorActiveRestrictionCountSql} AS author_active_restriction_count,
        t.id AS topic_row_id, t.title AS topic_title, t.slug AS topic_slug,
        (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id) as reaction_count
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN topics t ON t.id = c.topic_id
        WHERE $whereClause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$comments = $stmt->fetchAll();

$parentCommentMap = [];
$parentIds = $commentsManagerNormalizeIds(array_column($comments, 'parent_id'));
if ($parentIds !== []) {
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
    $parentStmt = $pdo->prepare("SELECT pc.id, pc.body, pc.user_id, pc.deleted_at, pc.status, pu.username AS author_name
        FROM comments pc
        LEFT JOIN users pu ON pu.id = pc.user_id
        WHERE pc.id IN ({$placeholders})");
    $parentStmt->execute($parentIds);
    foreach ($parentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $parentCommentMap[(int)$row['id']] = $row;
    }
}

$commentsById = [];
$childrenByParent = [];
foreach ($comments as $comment) {
    $commentIdForMap = (int)($comment['id'] ?? 0);
    if ($commentIdForMap <= 0) {
        continue;
    }
    $commentsById[$commentIdForMap] = $comment;
}
foreach ($comments as $comment) {
    $commentIdForMap = (int)($comment['id'] ?? 0);
    $parentIdForMap = (int)($comment['parent_id'] ?? 0);
    if ($commentIdForMap > 0 && $parentIdForMap > 0 && isset($commentsById[$parentIdForMap])) {
        $childrenByParent[$parentIdForMap][] = $commentIdForMap;
    }
}

$commentRenderRows = [];
$visitedCommentIds = [];
$walkCommentTree = static function (int $commentId, int $depth) use (&$walkCommentTree, &$commentRenderRows, &$visitedCommentIds, $commentsById, $childrenByParent): void {
    if ($commentId <= 0 || isset($visitedCommentIds[$commentId]) || !isset($commentsById[$commentId])) {
        return;
    }
    $visitedCommentIds[$commentId] = true;
    $row = $commentsById[$commentId];
    $row['_depth'] = min($depth, 3);
    $commentRenderRows[] = $row;
    foreach ($childrenByParent[$commentId] ?? [] as $childId) {
        $walkCommentTree((int)$childId, $depth + 1);
    }
};
foreach ($comments as $comment) {
    $commentIdForMap = (int)($comment['id'] ?? 0);
    $parentIdForMap = (int)($comment['parent_id'] ?? 0);
    if ($commentIdForMap > 0 && ($parentIdForMap <= 0 || !isset($commentsById[$parentIdForMap]))) {
        $walkCommentTree($commentIdForMap, 0);
    }
}
foreach ($comments as $comment) {
    $commentIdForMap = (int)($comment['id'] ?? 0);
    if ($commentIdForMap > 0 && !isset($visitedCommentIds[$commentIdForMap])) {
        $walkCommentTree($commentIdForMap, 0);
    }
}

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

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

require_once __DIR__ . '/header.php';
?>
<div class="comments-manager">
    <?= adminRenderFlashAlerts($successMsg, $errorMsg) ?>

    <div class="comments-manager-shell">
        <aside class="comments-manager-sidebar">
            <section class="comments-manager-top ui-card">
                <div class="comments-manager-top__copy">
                    <span class="comments-manager-kicker"><i class="bi bi-chat-left-text"></i> Yorumlar</span>
                    <h2>Yorum Yönetimi</h2>
                    <p>Konu ve yanıt bağlamıyla yorumları inceleyin.</p>
                </div>
                <div class="comments-manager-top__actions">
                    <a href="?<?= htmlspecialchars(http_build_query($commentsManagerFilterQuery(['status' => 'pending', 'page' => null]))) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-hourglass-split"></i> Bekleyenler</a>
                    <a href="?<?= htmlspecialchars(http_build_query($commentsManagerFilterQuery(['status' => 'deleted', 'page' => null]))) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Silinenler</a>
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
                            $statusQuery = $commentsManagerFilterQuery(['status' => $statusKey, 'page' => null]);
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
                <form method="get" action="comments-manager.php" class="comments-manager-search-form admin-filter-form">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="topic_id" value="<?= (int) $topicId ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                    <div class="comments-manager-search-row">
                        <input type="text" name="search" class="ui-comment-manager-filter-input ui-input comments-manager-search" placeholder="Yorum, kullanıcı, konu ara..." value="<?= htmlspecialchars($search) ?>">
                        <select name="user_state" class="ui-admin-form-select comments-manager-filter-select" aria-label="Kullanıcı durumu">
                            <option value="all"<?= $userState === 'all' ? ' selected' : '' ?>>Tüm kullanıcılar</option>
                            <option value="banned"<?= $userState === 'banned' ? ' selected' : '' ?>>Banlı kullanıcılar</option>
                            <option value="restricted"<?= $userState === 'restricted' ? ' selected' : '' ?>>Kısıtlı kullanıcılar</option>
                        </select>
                        <select name="date_range" class="ui-admin-form-select comments-manager-filter-select" aria-label="Tarih aralığı">
                            <option value="all"<?= $dateRange === 'all' ? ' selected' : '' ?>>Tüm tarihler</option>
                            <option value="today"<?= $dateRange === 'today' ? ' selected' : '' ?>>Bugün</option>
                            <option value="week"<?= $dateRange === 'week' ? ' selected' : '' ?>>Son 7 gün</option>
                            <option value="month"<?= $dateRange === 'month' ? ' selected' : '' ?>>Son 30 gün</option>
                        </select>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Ara</button>
                        <a href="comments-manager.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-circle"></i> Temizle</a>
                    </div>
                </form>
                <?php if ($status === 'deleted' && !empty($comments)): ?>
                    <form method="post" action="comments-manager.php?<?= htmlspecialchars(http_build_query($commentsManagerFilterQuery(['page' => $page]))) ?>" class="comments-manager-toolbar__actions"<?= adminConfirmAttrs(['message' => 'Bu görünümdeki silinen yorumları, yanıtları ve ilişkili kayıtları kalıcı olarak silmek istediğinize emin misiniz?', 'title' => 'Tümünü kalıcı sil', 'ok' => 'Kalıcı sil', 'tone' => 'danger']) ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="purge_deleted">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm">
                            <i class="bi bi-trash3"></i> Tümünü Sil
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($comments)): ?>
                <?= adminRenderEmptyState([
                    'icon' => 'bi-inbox',
                    'tone' => 'info',
                    'title' => 'Yorum Bulunamadı',
                    'description' => 'Seçili filtrelere uygun yorum bulunmuyor.',
                    'class' => 'ui-comment-manager-empty comments-manager-empty',
                ]) ?>
            <?php else: ?>
                <form method="post" action="comments-manager.php?<?= htmlspecialchars(http_build_query($commentsManagerFilterQuery(['page' => $page]))) ?>" id="commentsBulkForm" class="comments-manager-bulk-form" data-comments-bulk-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="" data-comments-bulk-action>
                    <div class="comments-manager-bulk-bar" data-comments-bulk-bar>
                        <div class="comments-manager-bulk-main">
                            <label class="comments-manager-bulk-select">
                                <input type="checkbox" data-comment-bulk-select-all>
                                <span>Tümünü seç</span>
                            </label>
                            <span class="comments-manager-bulk-count" data-comments-bulk-count>0 yorum seçildi</span>
                        </div>
                        <div class="comments-manager-bulk-actions">
                            <?php if ($status === 'deleted'): ?>
                                <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm comments-manager-bulk-submit" data-comments-bulk-submit data-comments-bulk-action-name="bulk_restore" disabled>
                                    <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                                </button>
                            <?php else: ?>
                                <button type="submit" class="ui-admin-btn ui-admin-btn-success ui-admin-btn-sm comments-manager-bulk-submit" data-comments-bulk-submit data-comments-bulk-action-name="bulk_approve" disabled>
                                    <i class="bi bi-check-lg"></i> Onayla
                                </button>
                                <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm comments-manager-bulk-submit" data-comments-bulk-submit data-comments-bulk-action-name="bulk_reject" disabled>
                                    <i class="bi bi-x-lg"></i> Reddet
                                </button>
                                <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm comments-manager-bulk-submit" data-comments-bulk-submit data-comments-bulk-action-name="bulk_delete" disabled>
                                    <i class="bi bi-trash"></i> Sil
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="comments-manager-list-head">
                    <span>Seç</span>
                    <span>Konuşma</span>
                    <span>Kullanıcı</span>
                    <span>Durum</span>
                    <span>Tarih</span>
                    <span>İşlem</span>
                </div>

                <div class="ui-comment-manager-comments-list comments-manager-list">
                    <?php foreach ($commentRenderRows as $comment): ?>
                        <?php
                            $commentIdValue = (int)($comment['id'] ?? 0);
                            $commentDepth = (int)($comment['_depth'] ?? 0);
                            $commentUserId = (int)($comment['user_id'] ?? 0);
                            $commentAuthor = (string)($comment['author_name'] ?? 'Anonim');
                            $commentParentId = (int)($comment['parent_id'] ?? 0);
                            $parentContext = $commentParentId > 0 ? ($parentCommentMap[$commentParentId] ?? null) : null;
                            $topicRowId = (int)($comment['topic_row_id'] ?? ($comment['topic_id'] ?? 0));
                            $topicTitle = trim((string)($comment['topic_title'] ?? ''));
                            $topicSlug = trim((string)($comment['topic_slug'] ?? ''));
                            $commentUrl = ($topicRowId > 0 && $topicSlug !== '') ? topicUrl($topicSlug, $topicRowId) . '#comment-' . $commentIdValue : '';
                            $canModerateAuthor = $canManageCommentUsers && $commentUserId > 0 && $commentUserId !== $currentUserId;
                            $isAuthorBanned = (int)($comment['author_is_banned'] ?? 0) === 1;
                            $authorRestrictionCount = (int)($comment['author_active_restriction_count'] ?? 0);
                            $authorStateClass = $isAuthorBanned ? 'is-danger' : ($authorRestrictionCount > 0 ? 'is-warning' : 'is-success');
                            $authorStateIcon = $isAuthorBanned ? 'bi-slash-circle' : ($authorRestrictionCount > 0 ? 'bi-shield-exclamation' : 'bi-check-circle');
                            $authorStateLabel = $isAuthorBanned ? 'Banlı' : ($authorRestrictionCount > 0 ? 'Kısıtlı' : 'Temiz');
                            $statusValue = (string)($comment['status'] ?? '');
                        ?>
                        <div class="ui-comment-manager-comment-card ui-card comments-manager-card<?= $commentDepth > 0 ? ' is-nested' : '' ?>" style="--comment-depth: <?= $commentDepth ?>">
                            <div class="ui-comment-manager-comment-header ui-panel__head comments-manager-card__head">
                                <label class="comments-manager-select-cell" title="Yorumu seç">
                                    <input type="checkbox" name="comment_ids[]" value="<?= $commentIdValue ?>" form="commentsBulkForm" class="comments-manager-select-checkbox" data-comment-bulk-checkbox aria-label="Yorum #<?= $commentIdValue ?> seç">
                                </label>
                                <div class="ui-comment-manager-comment-avatar default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml($commentAuthor, (string) ($comment['author_avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <div class="ui-comment-manager-comment-meta comments-manager-card__meta">
                                    <?php if ($commentUserId > 0 && $canViewCommentUserDetails): ?>
                                        <details class="comments-manager-user-insight-menu" data-comment-user-insight-menu data-user-id="<?= $commentUserId ?>" data-user-name="<?= htmlspecialchars($commentAuthor, ENT_QUOTES, 'UTF-8') ?>" data-can-moderate="<?= $canModerateAuthor ? '1' : '0' ?>" data-is-banned="<?= $isAuthorBanned ? '1' : '0' ?>">
                                            <summary class="comments-manager-user-chip <?= htmlspecialchars($authorStateClass) ?>" data-comment-user-insight-toggle>
                                                <span class="comments-manager-user-chip__name"><?= htmlspecialchars($commentAuthor) ?></span>
                                                <span class="comments-manager-user-chip__status"><i class="bi <?= htmlspecialchars($authorStateIcon) ?>"></i> <?= htmlspecialchars($authorStateLabel) ?></span>
                                                <i class="bi bi-chevron-down comments-manager-user-chip__chevron" aria-hidden="true"></i>
                                            </summary>
                                            <div class="comments-manager-user-insight-popover" data-comment-user-insight-popover>
                                                <div class="comments-manager-user-insight-content" data-comment-user-insight-content>
                                                    <span class="ui-admin-muted-sm">Kullanıcı bilgisi yükleniyor...</span>
                                                </div>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <div class="ui-comment-manager-comment-author"><?= htmlspecialchars($commentAuthor) ?></div>
                                    <?php endif; ?>
                                    <div class="ui-comment-manager-comment-info">
                                        <span class="ui-comment-manager-comment-info-item">
                                            <i class="bi bi-calendar"></i>
                                            <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                        </span>
                                        <span class="ui-comment-manager-comment-info-item">
                                            <i class="bi bi-heart"></i>
                                            <?= number_format((int) $comment['reaction_count']) ?> reaksiyon
                                        </span>
                                        <span class="ui-comment-manager-comment-status <?= htmlspecialchars($statusValue) ?>">
                                            <?php if ($statusValue === 'pending'): ?>
                                                <i class="bi bi-clock"></i> Bekliyor
                                            <?php elseif ($statusValue === 'approved'): ?>
                                                <i class="bi bi-check-circle"></i> Onaylı
                                            <?php else: ?>
                                                <i class="bi bi-x-circle"></i> Reddedildi
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="comments-manager-topic-line">
                                        <i class="bi bi-folder2-open"></i>
                                        <?php if ($commentUrl !== ''): ?>
                                            <a href="<?= htmlspecialchars($commentUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($topicTitle !== '' ? $topicTitle : ('Konu #' . $topicRowId)) ?></a>
                                        <?php elseif ($topicRowId > 0): ?>
                                            <span><?= htmlspecialchars($topicTitle !== '' ? $topicTitle : ('Konu #' . $topicRowId)) ?></span>
                                        <?php else: ?>
                                            <span>Konu bulunamadı</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($commentParentId > 0): ?>
                                <div class="comments-manager-parent-context">
                                    <i class="bi bi-reply"></i>
                                    <?php if ($parentContext): ?>
                                        <span>
                                            <strong><?= htmlspecialchars((string)($parentContext['author_name'] ?? 'Anonim')) ?></strong>
                                            yorumuna yanıt:
                                            <?= htmlspecialchars(mb_strimwidth(trim((string)($parentContext['body'] ?? '')), 0, 150, '...')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span>Yanıtlanan yorum silinmiş veya bu filtrede görünmüyor.</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div id="admin-comment-body-<?= $commentIdValue ?>" class="ui-comment-manager-comment-body ui-panel__body comments-manager-card__body" data-ui-comment-manager-body>
                                <?= nl2br(htmlspecialchars($comment['body'])) ?>
                            </div>

                            <div class="ui-comment-manager-comment-footer ui-panel__foot comments-manager-card__foot">
                                <div class="comments-manager-card__notes">
                                    <span class="comments-manager-note"><i class="bi bi-chat-text"></i> Yorum #<?= $commentIdValue ?></span>
                                    <?php if ($commentParentId > 0): ?>
                                        <span class="comments-manager-note"><i class="bi bi-reply"></i> Yanıt #<?= $commentParentId ?></span>
                                    <?php endif; ?>
                                    <span class="comments-manager-note"><i class="bi bi-person"></i> <?= htmlspecialchars($commentAuthor) ?></span>
                                    <?php if ($topicTitle !== '' || $topicRowId > 0): ?>
                                        <span class="comments-manager-note"><i class="bi bi-folder"></i> <?= htmlspecialchars($topicTitle !== '' ? mb_strimwidth($topicTitle, 0, 48, '...') : ('Konu #' . $topicRowId)) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="ui-comment-manager-comment-actions comments-manager-card__actions">
                                    <details class="user-row-actions-menu comments-manager-actions-menu" data-comment-actions-menu>
                                        <summary class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline comments-manager-actions-toggle" data-comment-actions-toggle title="İşlemler">
                                            <i class="bi bi-three-dots"></i>
                                        </summary>
                                        <div class="user-row-actions-popover comments-manager-actions-popover" data-comment-actions-popover>
                                            <?php if ($comment['deleted_at']): ?>
                                                <form method="post" class="ui-admin-inline-form comments-manager-menu-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="comment_id" value="<?= $commentIdValue ?>">
                                                    <button type="submit" class="user-row-action comments-manager-menu-item is-success">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="user-row-action comments-manager-menu-item" data-comment-edit="<?= $commentIdValue ?>" data-comment-body="<?= htmlspecialchars((string) $comment['body'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-pencil"></i> Düzenle
                                                </button>
                                                <?php if ($statusValue === 'pending'): ?>
                                                    <form method="post" class="ui-admin-inline-form comments-manager-menu-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="comment_id" value="<?= $commentIdValue ?>">
                                                        <button type="submit" class="user-row-action comments-manager-menu-item is-success">
                                                            <i class="bi bi-check-lg"></i> Onayla
                                                        </button>
                                                    </form>
                                                    <form method="post" class="ui-admin-inline-form comments-manager-menu-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="comment_id" value="<?= $commentIdValue ?>">
                                                        <button type="submit" class="user-row-action comments-manager-menu-item is-warning">
                                                            <i class="bi bi-x-lg"></i> Reddet
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" class="ui-admin-inline-form comments-manager-menu-form"<?= adminConfirmAttrs(['message' => 'Bu yorumu silmek istediğinize emin misiniz?', 'title' => 'Yorum silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="comment_id" value="<?= $commentIdValue ?>">
                                                    <button type="submit" class="user-row-action comments-manager-menu-item is-danger">
                                                        <i class="bi bi-trash"></i> Sil
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canModerateAuthor): ?>
                                                <div class="comments-manager-menu-separator" aria-hidden="true"></div>
                                                <?php if ($isAuthorBanned): ?>
                                                    <button type="button" class="user-row-action comments-manager-menu-item is-success" data-comment-user-unban="<?= $commentUserId ?>" data-user-name="<?= htmlspecialchars($commentAuthor, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-check-circle"></i> Ban Kaldır
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="user-row-action comments-manager-menu-item is-danger" data-comment-user-ban="<?= $commentUserId ?>" data-user-name="<?= htmlspecialchars($commentAuthor, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-slash-circle"></i> Banla
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="user-row-action comments-manager-menu-item is-warning" data-comment-user-restrict="<?= $commentUserId ?>" data-user-name="<?= htmlspecialchars($commentAuthor, ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-shield-exclamation"></i> Kısıtla
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total > $perPage): ?>
                    <?php
                    $totalPages = (int) ceil($total / $perPage);
                    $queryParams = $commentsManagerFilterQuery(['page' => null]);

                    echo adminRenderPagination($totalPages, $page, static function (int $targetPage) use ($queryParams): string {
                        return '?' . http_build_query(array_merge($queryParams, ['page' => $targetPage]));
                    }, [
                        'wrapper_class' => 'comments-manager-pagination',
                        'inner_class' => 'comments-manager-pagination-inner',
                        'aria_label' => 'Yorum sayfalama',
                    ]);
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<div id="commentBanModal" class="media-modal-overlay" role="dialog" aria-modal="true" aria-label="Kullanıcı banla" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-slash-circle"></i> Kullanıcıyı Banla</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-comment-ban-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form method="post" id="commentBanForm" data-comment-ban-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="user_id" id="commentBanUserId">
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Kullanıcı</label>
                    <input type="text" id="commentBanUserName" class="ui-admin-form-control" readonly>
                </div>
                <div class="ui-admin-moderation-context" data-comment-ban-context>
                    <div class="ui-admin-moderation-context__current" data-comment-ban-current>
                        <span class="ui-admin-muted-sm">Ban bilgisi yükleniyor...</span>
                    </div>
                    <div>
                        <div class="ui-admin-moderation-context__title">Son 5 Moderasyon Geçmişi</div>
                        <div class="ui-admin-moderation-context__list" data-comment-ban-history>
                            <span class="ui-admin-muted-sm">Geçmiş yükleniyor...</span>
                        </div>
                    </div>
                </div>
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Sebep</label>
                    <textarea name="ban_reason" id="commentBanReason" class="ui-admin-form-control" rows="3" required placeholder="Ban gerekçesi..."></textarea>
                </div>
                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-comment-ban-close>İptal</button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-slash-circle"></i> Banla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="commentUnbanModal" class="media-modal-overlay" role="dialog" aria-modal="true" aria-label="Kullanıcı banını kaldır" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-check-circle"></i> Kullanıcının Banını Kaldır</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-comment-unban-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form method="post" id="commentUnbanForm" data-comment-unban-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="user_id" id="commentUnbanUserId">
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Kullanıcı</label>
                    <input type="text" id="commentUnbanUserName" class="ui-admin-form-control" readonly>
                </div>
                <div class="ui-admin-moderation-context" data-comment-unban-context>
                    <div class="ui-admin-moderation-context__current" data-comment-unban-current>
                        <span class="ui-admin-muted-sm">Ban bilgisi yükleniyor...</span>
                    </div>
                    <div>
                        <div class="ui-admin-moderation-context__title">Son 5 Moderasyon Geçmişi</div>
                        <div class="ui-admin-moderation-context__list" data-comment-unban-history>
                            <span class="ui-admin-muted-sm">Geçmiş yükleniyor...</span>
                        </div>
                    </div>
                </div>
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">İşlem Notu <small class="ui-admin-muted-xs">(Opsiyonel)</small></label>
                    <textarea name="reason" id="commentUnbanReason" class="ui-admin-form-control" rows="2" placeholder="Ban kaldırma notu..."></textarea>
                </div>
                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-comment-unban-close>İptal</button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-success"><i class="bi bi-check-circle"></i> Ban Kaldır</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="commentRestrictionModal" class="media-modal-overlay" role="dialog" aria-modal="true" aria-label="Kısıtlama ekle" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-shield-exclamation"></i> Kısıtlama Ekle</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-comment-restriction-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form method="post" id="commentRestrictionForm" data-comment-restriction-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_restriction">
                <input type="hidden" name="user_id" id="commentRestrictUserId">
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Kullanıcı</label>
                    <input type="text" id="commentRestrictUserName" class="ui-admin-form-control" readonly>
                </div>
                <div class="ui-admin-moderation-context" data-comment-restriction-context>
                    <div>
                        <div class="ui-admin-moderation-context__title">Aktif Kısıtlamalar</div>
                        <div class="ui-admin-moderation-context__list" data-comment-restriction-current>
                            <span class="ui-admin-muted-sm">Kısıtlamalar yükleniyor...</span>
                        </div>
                    </div>
                    <div>
                        <div class="ui-admin-moderation-context__title">Son 5 Moderasyon Geçmişi</div>
                        <div class="ui-admin-moderation-context__list" data-comment-restriction-history>
                            <span class="ui-admin-muted-sm">Geçmiş yükleniyor...</span>
                        </div>
                    </div>
                </div>
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Kısıtlama Türleri</label>
                    <select name="restrict_types[]" id="commentRestrictTypes" class="ui-admin-form-select ui-admin-select-auto" multiple size="7" required>
                        <option value="profile">Profil Düzenleme</option>
                        <option value="events">Etkinlik Kullanımı</option>
                        <option value="all">Tüm İşlemler</option>
                        <option value="comment">Yorum Yapma</option>
                        <option value="topic">Konu Oluşturma</option>
                        <option value="upload">Dosya Yükleme</option>
                        <option value="download">İndirme</option>
                    </select>
                </div>
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Süre (gün)</label>
                    <input type="number" name="restrict_days" class="ui-admin-form-control" min="0" placeholder="0 = Süresiz">
                </div>
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Sebep</label>
                    <textarea name="restrict_reason" class="ui-admin-form-control" rows="3" required placeholder="Kısıtlama sebebi..."></textarea>
                </div>
                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-comment-restriction-close>İptal</button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-warning"><i class="bi bi-shield-exclamation"></i> Kısıtla</button>
                </div>
            </form>
        </div>
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
