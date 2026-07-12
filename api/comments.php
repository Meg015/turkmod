<?php

declare(strict_types=1);
/**
 * AJAX Comment API
 * GET  ?topic_id=X           → list comments
 * POST {topic_id, body}      → add comment
 * POST {action=delete, id}   → delete comment (admin/owner)
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';
// admin/helpers.php saf fonksiyon tanımları içeriyor (requireAdmin gibi yan etkisiz);
// burada getAdminSettings(), adminSettingDefinitions() vb. fonksiyonları kullanabilmek için include ediyoruz.
require_once __DIR__ . '/../admin/helpers.php';
if (is_file(__DIR__ . '/../includes/src/Modules/Events/init.php')) {
    require_once __DIR__ . '/../includes/src/Modules/Events/init.php';
}

header('Content-Type: application/json; charset=utf-8');

$pdo = requireDatabaseConnection($pdo ?? null);

// Settings
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$allowComments       = ($settings['allow_comments'] ?? '1') === '1';
$approvalRequired    = ($settings['comment_approval_required'] ?? '0') === '1';
$maxLength           = (int)($settings['max_comment_length'] ?? 2000);
$minLength           = (int)($settings['comment_min_length'] ?? 1);
$guestComments       = ($settings['comment_allow_guest'] ?? '0') === '1';
$editWindow          = (int)($settings['comment_edit_window'] ?? 0);
$nestedComments      = ($settings['comment_nested'] ?? '0') === '1';
$maxNestDepth        = (int)($settings['comment_max_depth'] ?? 3);
$commentsPerPage     = (int)($settings['comment_per_page'] ?? 50);
$commentOrder        = $settings['comment_sort_order'] ?? 'asc';
$rateMinutes         = (int)($settings['comment_rate_minutes'] ?? 5);
$rateMax             = (int)($settings['comment_rate_max'] ?? 5);
$rateAdminBypass     = ($settings['comment_rate_admin_bypass'] ?? '1') === '1';
$mentionRateMax      = max(1, (int)($settings['comment_mention_rate_max'] ?? 30));
$mentionRateWindow   = max(1, (int)($settings['comment_mention_rate_window'] ?? 1));
$commentEditRateMax  = max(1, (int)($settings['comment_edit_rate_max'] ?? 20));
$commentEditRateWindow = max(1, (int)($settings['comment_edit_rate_window'] ?? 1));
$commentReactionRateMax = max(1, (int)($settings['comment_reaction_rate_max'] ?? 60));
$commentReactionRateWindow = max(1, (int)($settings['comment_reaction_rate_window'] ?? 1));
$commentReportRateMax = max(1, (int)($settings['comment_report_rate_max'] ?? 5));
$commentReportRateWindow = max(1, (int)($settings['comment_report_rate_window'] ?? 10));
$bannedWords         = array_filter(array_map('trim', explode("\n", $settings['banned_words'] ?? '')));

// Enhanced comment features
$reactionsEnabled    = ($settings['comment_reactions_enabled'] ?? '1') === '1';
$reactionTypes       = ['like', 'dislike'];
$markdownEnabled     = ($settings['comment_markdown_enabled'] ?? '1') === '1';
$mentionsEnabled     = ($settings['comment_mentions_enabled'] ?? '1') === '1';
$editHistoryEnabled  = ($settings['comment_edit_history'] ?? '1') === '1';
$mediaEnabled        = ($settings['comment_media_enabled'] ?? '0') === '1';
$spamDetection       = ($settings['comment_spam_detection'] ?? '1') === '1';

$wordFilterStr       = trim($settings['comment_word_filter'] ?? '');
$wordFilter          = $wordFilterStr !== '' ? array_filter(array_map('trim', explode(',', $wordFilterStr))) : [];
$autoBanAction       = $settings['comment_auto_ban_words'] ?? 'pending';
$autoHideCount       = (int)($settings['comment_auto_hide_reports'] ?? 5);
$currentUserIsAdmin = isset($_SESSION['_auth_user_id']) && function_exists('userHasPermission') && userHasPermission($pdo, (int) $_SESSION['_auth_user_id'], 'admin.access');
$isLoggedIn = isset($_SESSION['_auth_user_id']) && !empty($_SESSION['_auth_user_id']);

// Release session lock early as this API only reads from session
session_write_close();

/**
 * Cached schema column check — runs SHOW COLUMNS at most once per request.
 * Replaces repeated per-call SHOW COLUMNS queries (performance item #7).
 */
function _commentsSchemaHas(PDO $pdo, string $column): bool
{
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM comments");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[(string) ($row['Field'] ?? '')] = true;
            }
        } catch (Throwable) {
            // Schema is stable — assume current schema.sql columns exist
            $columns = ['is_edited' => true, 'edited_at' => true, 'reaction_count' => true, 'is_markdown' => true, 'mention_count' => true];
        }
    }
    return isset($columns[$column]);
}

function jsonResponse(int $code, array $data): void
{
    $success = (bool) ($data['success'] ?? ($code >= 200 && $code < 300));
    $message = (string) ($data['message'] ?? ($data['error'] ?? ($success ? 'OK' : '')));
    $errorCode = array_key_exists('code', $data) && is_string($data['code']) && $data['code'] !== ''
        ? (string) $data['code']
        : null;
    $extra = $data;
    unset($extra['success']);
    // `message` ve `error` frontend tarafinda eski sekliyle kullanildigindan
    // extra icinde tutuyoruz; sendJsonResponse'in array_merge'i override etmez
    // cunku biz zaten ayri alanlari set ediyoruz.
    sendJsonResponse($code, $success, $message !== '' ? $message : null, $extra, $errorCode);
}

function filterBannedWords(string $text, array $words): bool
{
    if (empty($words)) return false;
    $lower = mb_strtolower($text);
    foreach ($words as $w) {
        if ($w !== '' && str_contains($lower, mb_strtolower($w))) return true;
    }
    return false;
}

function _commentsUsersHasUsername(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
        $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function _commentsUserNameExpr(PDO $pdo, string $alias = 'u'): string
{
    return _commentsUsersHasUsername($pdo)
        ? "COALESCE(NULLIF({$alias}.username, ''), CONCAT('user-', {$alias}.id))"
        : "CONCAT('user-', {$alias}.id)";
}

// ─── GET: List comments ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') === 'mention_search') {
        if (!$mentionsEnabled || !$isLoggedIn) {
            jsonResponse(200, ['users' => []]);
        }

        $mentionRateKey = 'comment_mention_search_' . (int)$_SESSION['_auth_user_id'];
        if (!checkRateLimit($mentionRateKey, $mentionRateMax, $mentionRateWindow)) {
            jsonResponse(429, ['error' => 'Cok fazla arama yaptiniz. Lutfen biraz bekleyin.']);
        }
        incrementRateLimit($mentionRateKey, $mentionRateWindow);

        $query = trim((string)($_GET['q'] ?? ''));
        $query = preg_replace('/[^\p{L}\p{N}_\-. ]/u', '', $query) ?? '';
        $query = mb_substr(trim($query), 0, 40);
        if (mb_strlen($query) < 1) {
            jsonResponse(200, ['users' => []]);
        }

        try {
            $mentionNameExpr = _commentsUserNameExpr($pdo, 'users');
            $stmt = $pdo->prepare(
                "SELECT id, {$mentionNameExpr} AS username FROM users
                 WHERE deleted_at IS NULL
                   AND {$mentionNameExpr} LIKE :query
                 ORDER BY CASE WHEN {$mentionNameExpr} LIKE :prefix THEN 0 ELSE 1 END, {$mentionNameExpr} ASC
                 LIMIT 8"
            );
            $stmt->execute([
                'query' => '%' . $query . '%',
                'prefix' => $query . '%',
            ]);
            $users = array_map(static function (array $row): array {
                $username = (string)($row['username'] ?? '');
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'username' => $username,
                    'name' => $username,
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

            jsonResponse(200, ['users' => $users]);
        } catch (Throwable $e) {
            jsonResponse(200, ['users' => []]);
        }
    }

    if (($_GET['action'] ?? '') === 'edit_history') {
        if (!$editHistoryEnabled) jsonResponse(403, ['error' => 'Duzenleme gecmisi kapali.']);
        if (!$isLoggedIn) jsonResponse(401, ['error' => 'Giris yapmalisiniz.']);

        $commentId = (int)($_GET['comment_id'] ?? 0);
        if ($commentId <= 0) jsonResponse(400, ['error' => 'Gecersiz yorum ID.']);

        try {
            $ownerStmt = $pdo->prepare("SELECT c.user_id FROM comments c WHERE c.id = ? AND c.deleted_at IS NULL LIMIT 1");
            $ownerStmt->execute([$commentId]);
            $commentRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$commentRow) jsonResponse(404, ['error' => 'Yorum bulunamadi.']);

            $commentOwnerId = (int)($commentRow['user_id'] ?? 0);
            $currentUserIdForHistory = (int)($_SESSION['_auth_user_id'] ?? 0);
            $hasCommentEditPerm = function_exists('userHasPermission') && userHasPermission($pdo, $currentUserIdForHistory, 'comments.edit');
            if (!$currentUserIsAdmin && !$hasCommentEditPerm && ($commentOwnerId <= 0 || $commentOwnerId !== $currentUserIdForHistory)) {
                jsonResponse(403, ['error' => 'Bu gecmisi gorme yetkiniz yok.']);
            }

            $editorNameExpr = _commentsUserNameExpr($pdo, 'u');
            $stmt = $pdo->prepare("SELECT h.id, h.old_body, h.new_body, h.edit_reason, h.created_at, {$editorNameExpr} as editor_name
                                   FROM comment_edit_history h
                                   LEFT JOIN users u ON h.user_id = u.id
                                   WHERE h.comment_id = ?
                                   ORDER BY h.created_at DESC
                                   LIMIT 20");
            $stmt->execute([$commentId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(200, [
                'success' => true,
                'history' => array_map(function($h) {
                    return [
                        'id' => (int)$h['id'],
                        'old_body' => $h['old_body'],
                        'new_body' => $h['new_body'],
                        'edit_reason' => $h['edit_reason'],
                        'editor_name' => $h['editor_name'] ?? 'Anonim',
                        'created_at' => $h['created_at'],
                        'time_ago' => timeAgo($h['created_at'])
                    ];
                }, $history)
            ]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Gecmis yuklenemedi.']);
        }
    }

    $topicId = (int)($_GET['topic_id'] ?? 0);
    if ($topicId <= 0) jsonResponse(400, ['error' => 'Geçersiz konu ID.']);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $commentsPerPage;

    // Sort: allow client-side override via ?sort= parameter
    $sortParam = strtolower(trim((string)($_GET['sort'] ?? '')));
    $validSorts = ['asc', 'desc', 'newest', 'oldest', 'popular'];
    if (in_array($sortParam, $validSorts, true)) {
        switch ($sortParam) {
            case 'newest':
            case 'desc':
                $orderDir = 'DESC';
                break;
            case 'popular':
            case 'liked':
            case 'disliked':
                $orderDir = 'DESC'; // Will be overridden below
                break;
            default:
                $orderDir = 'ASC';
        }
    } else {
        // Validate and sanitize order direction from admin settings
        $orderDir = (strtoupper($commentOrder) === 'DESC') ? 'DESC' : 'ASC';
    }

    // Sort by popularity (reaction count) if requested
    $orderByPopular = in_array($sortParam, ['popular', 'liked', 'disliked']);

    try {
        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE topic_id = :tid AND status = 'approved' AND deleted_at IS NULL");
        $countStmt->execute(['tid' => $topicId]);
        $total = (int)$countStmt->fetchColumn();

        // Root count (for pagination)
        $countRootStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE topic_id = :tid AND status = 'approved' AND deleted_at IS NULL AND parent_id IS NULL");
        $countRootStmt->execute(['tid' => $topicId]);
        $totalRoot = (int)$countRootStmt->fetchColumn();

        // Fetch all comments for this topic (flat)
        // Use cached schema check instead of per-request SHOW COLUMNS
        $hasNewColumns = _commentsSchemaHas($pdo, 'is_edited');
        $authorNameExpr = _commentsUserNameExpr($pdo, 'u');

        $selectCols = $hasNewColumns
            ? "c.id, c.user_id, c.body, c.parent_id, c.created_at, c.updated_at, c.is_edited, c.edited_at, c.reaction_count, {$authorNameExpr} AS author, u.avatar, COALESCE(ug.name, '') AS group_name"
            : "c.id, c.user_id, c.body, c.parent_id, c.created_at, c.updated_at, {$authorNameExpr} AS author, u.avatar, COALESCE(ug.name, '') AS group_name";

        // Add sorting by likes-dislikes if popular, liked, or disliked
        if ($orderByPopular) {
            $orderClause = "(COALESCE(cr_agg.likes_cnt, 0) - COALESCE(cr_agg.dislikes_cnt, 0)) DESC, c.created_at ASC";
            if ($sortParam === 'liked') {
                $orderClause = "COALESCE(cr_agg.likes_cnt, 0) DESC, c.created_at ASC";
            } elseif ($sortParam === 'disliked') {
                $orderClause = "COALESCE(cr_agg.dislikes_cnt, 0) DESC, c.created_at ASC";
            }

            $sqlRoot = "SELECT $selectCols, COALESCE(cr_agg.likes_cnt, 0) AS likes_cnt, COALESCE(cr_agg.dislikes_cnt, 0) AS dislikes_cnt
                   FROM comments c 
                   LEFT JOIN users u ON c.user_id = u.id
                   LEFT JOIN user_group_members ugm ON ugm.user_id = u.id AND ugm.is_primary = 1
                   LEFT JOIN user_groups ug ON ug.id = ugm.group_id
                   LEFT JOIN (
                       SELECT comment_id, 
                              SUM(IF(reaction_type = 'like', 1, 0)) AS likes_cnt, 
                              SUM(IF(reaction_type = 'dislike', 1, 0)) AS dislikes_cnt
                       FROM comment_reactions
                       GROUP BY comment_id
                   ) cr_agg ON cr_agg.comment_id = c.id
                   WHERE c.topic_id = :tid AND c.status = 'approved' AND c.deleted_at IS NULL AND c.parent_id IS NULL
                   ORDER BY $orderClause 
                   LIMIT :lim OFFSET :off";
        } else {
            $sqlRoot = "SELECT $selectCols
                   FROM comments c LEFT JOIN users u ON c.user_id = u.id
                   LEFT JOIN user_group_members ugm ON ugm.user_id = u.id AND ugm.is_primary = 1
                   LEFT JOIN user_groups ug ON ug.id = ugm.group_id
                   WHERE c.topic_id = :tid AND c.status = 'approved' AND c.deleted_at IS NULL AND c.parent_id IS NULL
                   ORDER BY c.created_at " . $orderDir . " LIMIT :lim OFFSET :off";
        }

        $stmt = $pdo->prepare($sqlRoot);
        $stmt->bindValue(':tid', $topicId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $commentsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rootComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch ALL REPLIES for the entire topic
        $sqlReplies = $hasNewColumns
            ? "SELECT c.id, c.user_id, c.body, c.parent_id, c.created_at, c.updated_at, c.is_edited, c.edited_at, c.reaction_count, {$authorNameExpr} AS author, u.avatar, COALESCE(ug.name, '') AS group_name
               FROM comments c LEFT JOIN users u ON c.user_id = u.id
               LEFT JOIN user_group_members ugm ON ugm.user_id = u.id AND ugm.is_primary = 1
               LEFT JOIN user_groups ug ON ug.id = ugm.group_id
               WHERE c.topic_id = :tid AND c.status = 'approved' AND c.deleted_at IS NULL AND c.parent_id IS NOT NULL
               ORDER BY c.created_at ASC"
            : "SELECT c.id, c.user_id, c.body, c.parent_id, c.created_at, c.updated_at, {$authorNameExpr} AS author, u.avatar, COALESCE(ug.name, '') AS group_name
               FROM comments c LEFT JOIN users u ON c.user_id = u.id
               LEFT JOIN user_group_members ugm ON ugm.user_id = u.id AND ugm.is_primary = 1
               LEFT JOIN user_groups ug ON ug.id = ugm.group_id
               WHERE c.topic_id = :tid AND c.status = 'approved' AND c.deleted_at IS NULL AND c.parent_id IS NOT NULL
               ORDER BY c.created_at ASC";

        $stmtReplies = $pdo->prepare($sqlReplies);
        $stmtReplies->bindValue(':tid', $topicId, PDO::PARAM_INT);
        $stmtReplies->execute();
        $replies = $stmtReplies->fetchAll(PDO::FETCH_ASSOC);

        $allComments = array_merge($rootComments, $replies);

        // Batch fetch reactions for all comments to avoid N+1 query
        $commentIds = array_column($allComments, 'id');
        $reactionsMap = [];
        $userReactionsMap = [];

        if ($reactionsEnabled && !empty($commentIds)) {
            try {
                // Fetch all reactions for these comments
                $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
                $reactionsStmt = $pdo->prepare("SELECT comment_id, reaction_type, COUNT(*) as count FROM comment_reactions WHERE comment_id IN ($placeholders) GROUP BY comment_id, reaction_type");
                $reactionsStmt->execute($commentIds);
                while ($row = $reactionsStmt->fetch()) {
                    $cid = (int)$row['comment_id'];
                    if (!isset($reactionsMap[$cid])) {
                        $reactionsMap[$cid] = [];
                    }
                    $reactionsMap[$cid][$row['reaction_type']] = (int)$row['count'];
                }

                // Fetch user's reactions if logged in
                if (isset($_SESSION['_auth_user_id'])) {
                    $userReactionsStmt = $pdo->prepare("SELECT comment_id, reaction_type FROM comment_reactions WHERE comment_id IN ($placeholders) AND user_id = ?");
                    $userReactionsStmt->execute(array_merge($commentIds, [(int)$_SESSION['_auth_user_id']]));
                    while ($row = $userReactionsStmt->fetch()) {
                        $cid = (int)$row['comment_id'];
                        if (!isset($userReactionsMap[$cid])) {
                            $userReactionsMap[$cid] = [];
                        }
                        $userReactionsMap[$cid][] = $row['reaction_type'];
                    }
                }
            } catch (Throwable $e) {
                // Silent fail for reactions
            }
        }

        // Build lookup and child map (group by direct parent_id)
        $byId = [];
        $childMap = []; // parent_id => [child comments]
        foreach ($allComments as $c) {
            $cid = (int)$c['id'];
            $pid = $c['parent_id'] ? (int)$c['parent_id'] : 0;
            $byId[$cid] = $c;
            $childMap[$pid][] = $c;
        }

        // Recursive tree builder
        $buildTree = function(int $parentId) use (&$buildTree, &$childMap, &$byId, $editWindow, &$reactionsMap, &$userReactionsMap): array {
            $children = $childMap[$parentId] ?? [];
            $result = [];
            foreach ($children as $c) {
                $cid = (int)$c['id'];
                $pid = $c['parent_id'] ? (int)$c['parent_id'] : null;
                if ($pid && isset($byId[$pid])) {
                    $parent = $byId[$pid];
                    $c['parent_author'] = $parent['author'] ?? 'Anonim';
                    $c['parent_body_preview'] = mb_substr($parent['body'], 0, 80);
                }
                $c['replies'] = $buildTree($cid);
                $result[] = formatComment($c, $editWindow, $reactionsMap, $userReactionsMap);
            }
            return $result;
        };

        // Root comments = parent_id is NULL (key 0)
        $formatted = $buildTree(0);

        jsonResponse(200, [
            'comments' => $formatted,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $commentsPerPage,
            'pages'    => max(1, (int)ceil($totalRoot / $commentsPerPage)),
        ]);
    } catch (Throwable $e) {
        file_put_contents(__DIR__ . '/../storage/logs/api_comments_error.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        jsonResponse(500, ['error' => 'Yorumlar yüklenemedi.']);
    }
}

function formatComment(array $c, int $editWindow, array $reactionsMap = [], array $userReactionsMap = []): array
{
    $userId = isset($_SESSION['_auth_user_id']) ? (int)$_SESSION['_auth_user_id'] : 0;
    $isOwner = $userId > 0 && (int)$c['user_id'] === $userId;

    $canEdit = false;
    if ($userId > 0) {
        if ($isOwner) {
            $createdTs = strtotime($c['created_at']);
            $canEdit = $editWindow <= 0 || (time() - $createdTs) < ($editWindow * 60);
        } else {
            $canEdit = function_exists('userHasPermission') && userHasPermission($GLOBALS['pdo'] ?? null, $userId, 'comments.edit');
        }
    }

    $canDelete = $userId > 0 && ($isOwner || (function_exists('userHasPermission') && userHasPermission($GLOBALS['pdo'] ?? null, $userId, 'comments.delete')));

    $result = [
        'id'                  => (int)$c['id'],
        'author'              => $c['author'] ?? 'Anonim',
        'avatar'              => $c['avatar'] ?? null,
        'profile_url'         => (int) ($c['user_id'] ?? 0) > 0
            ? publicProfileUrl([
                'id' => (int) ($c['user_id'] ?? 0),
                'name' => (string) ($c['author'] ?? 'Anonim'),
            ])
            : '',
        'group_name'          => $c['group_name'] ?? '',
        'body'                => $c['body'],
        'parent_id'           => $c['parent_id'] ?? null,
        'parent_author'       => $c['parent_author'] ?? null,
        'parent_body_preview' => $c['parent_body_preview'] ?? null,
        'created_at'          => $c['created_at'],
        'time_ago'            => timeAgo($c['created_at']),
        'is_edited'           => (bool)($c['is_edited'] ?? false),
        'edited_at'           => $c['edited_at'] ?? null,
        'can_edit'            => $canEdit,
        'can_delete'          => $canDelete,
        'replies'             => $c['replies'] ?? [],
    ];

    $cid = (int)$c['id'];
    $result['reactions'] = $reactionsMap[$cid] ?? [];
    $result['reaction_count'] = (int)($c['reaction_count'] ?? 0);
    $result['user_reactions'] = $userReactionsMap[$cid] ?? [];

    return $result;
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'az önce';
    if ($diff < 3600) return floor($diff / 60) . ' dk önce';
    if ($diff < 86400) return floor($diff / 3600) . ' saat önce';
    if ($diff < 604800) return floor($diff / 86400) . ' gün önce';
    return date('d M Y', strtotime($datetime));
}

/**
 * Parse mentions from comment body (@username)
 * Returns array of mentioned user IDs
 */
function parseMentions(string $body, PDO $pdo): array
{
    // Match @username pattern (alphanumeric, underscore, dash)
    preg_match_all('/@([\w\-]+)/', $body, $matches);
    if (empty($matches[1])) return [];

    $usernames = array_unique($matches[1]);
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));

    try {
        $lookupColumn = (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username'))
            ? 'username'
            : 'name';
        $selectName = $lookupColumn === 'username'
            ? "username"
            : 'name';
        $stmt = $pdo->prepare("SELECT id, {$selectName} AS mention_name FROM users WHERE {$lookupColumn} IN ($placeholders) AND deleted_at IS NULL LIMIT 10");
        $stmt->execute($usernames);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => mention_name]
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Simple markdown parser for comments - XSS-safe
 * Supports: **bold**, *italic*, `code`, [link](url)
 */
function parseMarkdown(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

    $text = preg_replace_callback(
        '/\[(.+?)\]\((.+?)\)/',
        function ($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = filter_var($matches[2], FILTER_VALIDATE_URL);
            if (!$url || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
                return $text;
            }
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $text
            );
        },
        $text
    );

    $text = nl2br($text);

    return $text;
}

/**
 * Detect spam patterns in comment
 */
function detectSpam(string $body, PDO $pdo, int $userId): bool
{
    // Check for repeated comments (same user, same text in last 5 minutes)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments
                               WHERE user_id = ? AND body = ?
                               AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                               AND deleted_at IS NULL");
        $stmt->execute([$userId, $body]);
        if ((int)$stmt->fetchColumn() > 0) return true;
    } catch (Throwable $e) {
        // Ignore
    }

    // Check for excessive links (more than 3)
    if (substr_count($body, 'http://') + substr_count($body, 'https://') > 3) return true;

    // Check for excessive caps (more than 70%)
    $upperCount = preg_match_all('/[A-Z]/', $body);
    $totalLetters = preg_match_all('/[a-zA-Z]/', $body);
    if ($totalLetters > 10 && ($upperCount / $totalLetters) > 0.7) return true;

    return false;
}

// ─── POST: Add / Delete / Edit comment ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) {
        jsonResponse(503, ['error' => 'Veritabanı bağlantısı şu anda kullanılamıyor.']);
    }

    $rawInput = file_get_contents('php://input');
    if (!is_string($rawInput) || strlen($rawInput) > 10485760) {
        jsonResponse(413, ['error' => 'Request body çok büyük.']);
    }

    $input = json_decode($rawInput, true) ?? $_POST;
    if (!is_array($input)) {
        jsonResponse(400, ['error' => 'Geçersiz JSON.']);
    }

    $action = $input['action'] ?? 'add';

    if (!verify_csrf_token($input['_token'] ?? '')) {
        jsonResponse(403, ['error' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.']);
    }

    // ── Delete ──
    if ($action === 'delete') {
        if (!$isLoggedIn) jsonResponse(401, ['error' => 'Giriş yapmalısınız.']);
        $deleteRateKey = 'comment_delete_' . (int)$_SESSION['_auth_user_id'];
        if (!checkRateLimit($deleteRateKey, $commentEditRateMax, $commentEditRateWindow)) {
            jsonResponse(429, ['error' => 'Cok fazla silme yaptiniz. Lutfen biraz bekleyin.']);
        }
        incrementRateLimit($deleteRateKey, $commentEditRateWindow);

        $commentId = (int)($input['id'] ?? 0);
        if ($commentId <= 0) jsonResponse(400, ['error' => 'Geçersiz yorum ID.']);

        try {
            $check = $pdo->prepare("SELECT id, topic_id, user_id, status, deleted_at FROM comments WHERE id = ? AND deleted_at IS NULL");
            $check->execute([$commentId]);
            $row = $check->fetch();
            if (!$row) jsonResponse(404, ['error' => 'Yorum bulunamadı.']);

            $canDelete = ((int)$row['user_id'] === (int)$_SESSION['_auth_user_id'])
                || (function_exists('userHasPermission') && userHasPermission($pdo, (int)$_SESSION['_auth_user_id'], 'comments.delete'));
            if (!$canDelete) jsonResponse(403, ['error' => 'Yetkiniz yok.']);

            $pdo->prepare("UPDATE comments SET deleted_at = NOW() WHERE id = ?")->execute([$commentId]);
            if ((string) ($settings['download_access_relock_on_comment_delete'] ?? '1') === '1' && function_exists('topicDownloadRevokeAccessGrant')) {
                topicDownloadRevokeAccessGrant($pdo, $commentId, 'comment_deleted');
            }
            commentApplyTopicCountDelta($pdo, $row, (string)($row['status'] ?? ''), true);
            if (function_exists('eventsReverseActivityPoints') && (int)$row['user_id'] > 0 && (string)$row['status'] === 'approved') {
                eventsReverseActivityPoints($pdo, (int)$row['user_id'], 'comment_created', 'comment', $commentId, 'comment_deleted');
            }
            if (function_exists('invalidatePublicContentCache')) {
                invalidatePublicContentCache();
            }

            jsonResponse(200, ['success' => true, 'message' => 'Yorum silindi.', '_token' => csrf_token()]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Yorum silinemedi.']);
        }
    }

    // ── Edit ──
    if ($action === 'edit') {
        if (!$isLoggedIn) jsonResponse(401, ['error' => 'Giriş yapmalısınız.']);
        $commentId = (int)($input['id'] ?? 0);
        $body = trim($input['body'] ?? '');
        if ($commentId <= 0 || $body === '') jsonResponse(400, ['error' => 'Geçersiz veri.']);

        try {
            $check = $pdo->prepare("SELECT id, topic_id, user_id, body, created_at FROM comments WHERE id = ? AND deleted_at IS NULL");
            $check->execute([$commentId]);
            $row = $check->fetch();
            if (!$row) jsonResponse(404, ['error' => 'Yorum bulunamadı.']);

            $isOwner = (int)$row['user_id'] === (int)$_SESSION['_auth_user_id'];
            $canEdit = $isOwner || (function_exists('userHasPermission') && userHasPermission($pdo, (int)$_SESSION['_auth_user_id'], 'comments.edit'));
            if (!$canEdit) jsonResponse(403, ['error' => 'Yetkiniz yok.']);

            if ($isOwner && $editWindow > 0) {
                $elapsed = time() - strtotime($row['created_at']);
                if ($elapsed > $editWindow * 60) jsonResponse(403, ['error' => 'Düzenleme süresi dolmuş.']);
            }

            $editResult = commentUpdateWithHistory(
                $pdo,
                $row,
                $body,
                (int) $_SESSION['_auth_user_id'],
                null,
                $editHistoryEnabled
            );
            if (!empty($editResult['changed']) && function_exists('notificationDispatchCommentEdited')) {
                try {
                    notificationDispatchCommentEdited(
                        $pdo,
                        $row,
                        (int) $_SESSION['_auth_user_id'],
                        (string) ($_SESSION['_auth_user_name'] ?? 'Yetkili'),
                        $editResult
                    );
                } catch (Throwable $notificationError) {
                    if (function_exists('appLogException')) {
                        appLogException($notificationError, [
                            'source' => 'api.comments.comment-edit-notification',
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

            jsonResponse(200, [
                'success' => true,
                'body' => (string) $editResult['body'],
                'is_edited' => !empty($editResult['changed']),
                '_token' => csrf_token(),
            ]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Düzenleme başarısız.']);
        }
    }

    // ── Add ── (only if action is not set or is 'add')
    if ($action !== 'delete' && $action !== 'edit' && $action !== 'react' && $action !== 'report') {
        if (!$allowComments) jsonResponse(403, ['error' => 'Yorumlar kapatılmış.']);
        if (!$isLoggedIn && !$guestComments) jsonResponse(401, ['error' => 'Yorum yapmak için giriş yapmalısınız.']);

        // Check comment restriction
        if ($isLoggedIn && function_exists('usersHasRestriction')) {
            if (usersHasRestriction($pdo, (int)$_SESSION['_auth_user_id'], 'comment')) {
                jsonResponse(403, ['error' => 'Yorum yapma yetkiniz kısıtlanmış.']);
            }
        }

        $topicId = (int)($input['topic_id'] ?? 0);
        $body = trim($input['body'] ?? '');
        $parentId = ((int)($input['parent_id'] ?? 0)) ?: null;

        if ($topicId <= 0) jsonResponse(400, ['error' => 'Geçersiz konu.']);
        if (mb_strlen($body) < $minLength) jsonResponse(400, ['error' => "Yorum en az {$minLength} karakter olmalı."]);
        if (mb_strlen($body) > $maxLength) jsonResponse(400, ['error' => "Yorum en fazla {$maxLength} karakter olabilir."]);

    // Banned words check (Old array)
    if (filterBannedWords($body, $bannedWords)) {
        jsonResponse(400, ['error' => 'Yorumunuz yasaklı kelimeler içeriyor.']);
    }

    $status = $approvalRequired ? 'pending' : 'approved';

    // New Word Filter check
    if (!empty($wordFilter) && filterBannedWords($body, $wordFilter)) {
        if ($autoBanAction === 'reject') {
            jsonResponse(400, ['error' => 'Yorumunuz izin verilmeyen kelimeler içerdiği için reddedildi.']);
        } elseif ($autoBanAction === 'censor') {
            foreach ($wordFilter as $word) {
                if (mb_stripos($body, $word) !== false) {
                    $body = preg_replace('/' . preg_quote($word, '/') . '/iu', str_repeat('*', mb_strlen($word)), $body);
                }
            }
        } elseif ($autoBanAction === 'pending') {
            $status = 'pending';
        }
    }

    // Spam detection
    if ($spamDetection && $isLoggedIn) {
        if (detectSpam($body, $pdo, (int)$_SESSION['_auth_user_id'])) {
            jsonResponse(429, ['error' => 'Spam tespit edildi. Lütfen farklı bir yorum yazın.']);
        }
    }

    // Rate limit (0 = devre dışı; admin'ler comment_rate_admin_bypass açıkken muaf)
    $skipRate = $rateAdminBypass && $currentUserIsAdmin;
    if (!$skipRate && $rateMax > 0 && $rateMinutes > 0) {
        $rateSubject = $isLoggedIn
            ? 'user_' . (int) ($_SESSION['_auth_user_id'] ?? 0)
            : 'guest_' . preg_replace('/[^a-zA-Z0-9_.:-]/', '', getRealIp());
        $rateKey = 'comment_' . $rateSubject;
        if (!checkRateLimit($rateKey, $rateMax, $rateMinutes)) {
            $remaining = getRateLimitRemainingSeconds($rateKey, $rateMinutes);
            jsonResponse(429, ['error' => "Çok hızlı yorum yapıyorsunuz. {$remaining} saniye bekleyin."]);
        }
    }

    if ($parentId && !$nestedComments) {
        jsonResponse(400, ['error' => 'Yanıt yorumları devre dışı.']);
    }

    // Nested depth check
    if ($parentId) {
        $parentStmt = $pdo->prepare("SELECT id, topic_id, parent_id, user_id FROM comments WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $parentStmt->execute([$parentId]);
        $parentRow = $parentStmt->fetch();
        if (!$parentRow) {
            jsonResponse(404, ['error' => 'Yanıt verilecek yorum bulunamadı.']);
        }
        if ((int)($parentRow['topic_id'] ?? 0) !== $topicId) {
            jsonResponse(400, ['error' => 'Yanıt yorumu farklı bir konuya ait.']);
        }

        if ($maxNestDepth > 0) {
            $depth = 1;
            $pid = $parentRow['parent_id'] ? (int)$parentRow['parent_id'] : null;
            while ($pid && $depth < $maxNestDepth) {
                $pStmt = $pdo->prepare("SELECT parent_id FROM comments WHERE id = ?");
                $pStmt->execute([$pid]);
                $pRow = $pStmt->fetch();
                $pid = $pRow ? (($pRow['parent_id'] ?? null) !== null ? (int)$pRow['parent_id'] : null) : null;
                $depth++;
            }
            if ($depth >= $maxNestDepth) jsonResponse(400, ['error' => 'Maksimum yanıt derinliğine ulaşıldı.']);
        }
    }



    try {
        $userId = $isLoggedIn ? (int)$_SESSION['_auth_user_id'] : null;

        // Parse mentions if enabled
        $mentionedUsers = [];
        if ($mentionsEnabled && $userId) {
            $mentionedUsers = parseMentions($body, $pdo);
        }

        // Use cached schema check instead of per-request SHOW COLUMNS
        $hasNewColumns = _commentsSchemaHas($pdo, 'is_markdown');

        // Insert comment (with or without new columns)
        if ($hasNewColumns) {
            $stmt = $pdo->prepare("INSERT INTO comments (topic_id, user_id, parent_id, body, status, is_markdown, mention_count, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$topicId, $userId, $parentId, $body, $status, $markdownEnabled ? 1 : 0, count($mentionedUsers)]);
        } else {
            // Fallback to old schema
            $stmt = $pdo->prepare("INSERT INTO comments (topic_id, user_id, parent_id, body, status, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$topicId, $userId, $parentId, $body, $status]);
        }
        $newId = (int)$pdo->lastInsertId();

        if ($userId && function_exists('topicDownloadCreateAccessGrant')) {
            $createdAt = date('Y-m-d H:i:s');
            topicDownloadCreateAccessGrant($pdo, $settings, [
                'id' => $newId,
                'topic_id' => $topicId,
                'user_id' => $userId,
                'status' => $status,
                'deleted_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ], $createdAt);
        }

        // Save mentions (only if table exists)
        if (!empty($mentionedUsers)) {
            try {
                $mentionStmt = $pdo->prepare("INSERT INTO comment_mentions (comment_id, mentioned_user_id, mentioner_user_id, created_at) VALUES (?, ?, ?, NOW())");
                foreach ($mentionedUsers as $mentionedUserId => $mentionedName) {
                    try {
                        $mentionStmt->execute([$newId, $mentionedUserId, $userId]);
                    } catch (Throwable $e) {
                        // Ignore duplicate mentions or missing table
                    }
                }
            } catch (Throwable $e) {
                // Table doesn't exist yet, skip mentions
            }
        }

        if ($status === 'approved') {
            $pdo->prepare("UPDATE topics SET comment_count = comment_count + 1 WHERE id = ?")->execute([$topicId]);
            if ($userId && function_exists('eventsRecordActivity')) {
                eventsRecordActivity($pdo, $userId, 'comment_created', 'comment', $newId, [
                    'topic_id' => $topicId,
                    'text_length' => mb_strlen($body),
                ]);
            }

            if (function_exists('notificationDispatch')) {
                try {
                    $topicStmt = $pdo->prepare("SELECT id, author_id, title, slug FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1");
                    $topicStmt->execute([$topicId]);
                    $topicRow = $topicStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $topicTitle = trim((string) ($topicRow['title'] ?? 'Konu'));
                    $topicSlug = trim((string) ($topicRow['slug'] ?? ''));
                    $topicPath = topicUrl($topicSlug, (int) ($topicRow['id'] ?? $topicId));
                    $commentLink = $topicPath . '#comment-' . $newId;
                    $actorName = $userId ? (string) ($_SESSION['_auth_user_name'] ?? 'Bir kullanıcı') : 'Bir ziyaretçi';
                    $basePayload = [
                        'actor_name' => $actorName,
                        'topic_title' => $topicTitle !== '' ? $topicTitle : 'Konu',
                        'link' => $commentLink,
                    ];
                    $notifiedRecipients = [];

                    $notifyRecipient = static function (int $recipientId, string $eventKey) use (
                        $pdo,
                        $newId,
                        $userId,
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
                            $userId ?: null,
                            'comment',
                            $newId,
                            $basePayload
                        );

                        if ($sent) {
                            $notifiedRecipients[$recipientId] = true;
                        }

                        return $sent;
                    };

                    $topicAuthorId = (int) ($topicRow['author_id'] ?? 0);
                    $notifyRecipient($topicAuthorId, 'comment_on_topic');

                    if ($parentId && !empty($parentRow['user_id'])) {
                        $notifyRecipient((int) $parentRow['user_id'], 'comment_reply');
                    }

                    $mentionRecipientIds = array_keys($mentionedUsers);
                    try {
                        $mentionRowsStmt = $pdo->prepare("SELECT mentioned_user_id FROM comment_mentions WHERE comment_id = ? AND is_notified = 0");
                        $mentionRowsStmt->execute([$newId]);
                        $mentionRecipientIds = array_map('intval', $mentionRowsStmt->fetchAll(PDO::FETCH_COLUMN) ?: $mentionRecipientIds);
                    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

                    foreach (array_unique(array_map('intval', $mentionRecipientIds)) as $mentionedUserId) {
                        if ($notifyRecipient($mentionedUserId, 'comment_mention')) {
                            try {
                                $pdo->prepare("UPDATE comment_mentions SET is_notified = 1 WHERE comment_id = ? AND mentioned_user_id = ?")
                                    ->execute([$newId, $mentionedUserId]);
                            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
                        }
                    }

                    try {
                        $favoriteStmt = $pdo->prepare("SELECT user_id FROM topic_favorites WHERE topic_id = ?");
                        $favoriteStmt->execute([$topicId]);
                        foreach ($favoriteStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $favoriteUserId) {
                            $notifyRecipient((int) $favoriteUserId, 'favorite_topic_comment');
                        }
                    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
            }
        }

        // Update leaderboard stats for user
        if ($userId && file_exists(__DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php')) {
            require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php';
            leaderboardTriggerComment($pdo, $userId);
        }

        // Rate limit sayacını yorum başarıyla eklendikten SONRA artır
        if (!$skipRate && $rateMax > 0 && $rateMinutes > 0) {
            incrementRateLimit($rateKey, $rateMinutes);
        }

        logActivity($pdo, 'comment_created', 'comment', $newId, ['topic_id' => $topicId]);

        // Fetch the newly created comment
        $newAuthorExpr = _commentsUserNameExpr($pdo, 'u');
        $newStmt = $pdo->prepare("SELECT c.id, c.user_id, c.body, c.parent_id, c.created_at, c.updated_at, {$newAuthorExpr} AS author, u.avatar
                                  FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ?");
        $newStmt->execute([$newId]);
        $newComment = $newStmt->fetch(PDO::FETCH_ASSOC);

        $message = $status === 'pending' ? 'Yorumunuz onay bekliyor.' : 'Yorumunuz eklendi.';
        jsonResponse(201, [
            'success' => true,
            'message' => $message,
            'pending' => $status === 'pending',
            'comment' => $newComment ? formatComment($newComment, $editWindow) : null,
            '_token'  => csrf_token(),
        ]);
    } catch (Throwable $e) {
        jsonResponse(500, ['error' => 'Yorum eklenemedi.']);
    }
    } // End of Add comment block
}

// ─── POST: React to comment ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($input['action'] ?? '') === 'react') {
    if (!$reactionsEnabled) jsonResponse(403, ['error' => 'Reaksiyon sistemi kapalı.']);
    if (!$isLoggedIn) jsonResponse(401, ['error' => 'Reaksiyon eklemek için giriş yapmalısınız.']);

    $commentId = (int)($input['comment_id'] ?? 0);
    $reactionType = trim($input['reaction_type'] ?? '');

    if ($commentId <= 0) jsonResponse(400, ['error' => 'Geçersiz yorum ID.']);
    if (!in_array($reactionType, $reactionTypes, true)) jsonResponse(400, ['error' => 'Geçersiz reaksiyon tipi.']);

    $userId = (int)$_SESSION['_auth_user_id'];
    $reactionRateKey = 'comment_reaction_' . $userId;
    if (!checkRateLimit($reactionRateKey, $commentReactionRateMax, $commentReactionRateWindow)) {
        jsonResponse(429, ['error' => 'Cok fazla reaksiyon islemi yaptiniz. Lutfen biraz bekleyin.']);
    }
    incrementRateLimit($reactionRateKey, $commentReactionRateWindow);

    try {
        // Check if comment exists and get comment owner
        $checkStmt = $pdo->prepare("SELECT id, topic_id, user_id FROM comments WHERE id = ? AND deleted_at IS NULL");
        $checkStmt->execute([$commentId]);
        $comment = $checkStmt->fetch();
        if (!$comment) jsonResponse(404, ['error' => 'Yorum bulunamadı.']);

        $commentOwnerId = (int)($comment['user_id'] ?? 0);

        // Check if user already reacted with this type
        $existingStmt = $pdo->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND reaction_type = ?");
        $existingStmt->execute([$commentId, $userId, $reactionType]);
        $existing = $existingStmt->fetch();

        if ($existing) {
            // Remove reaction (toggle off)
            $pdo->prepare("DELETE FROM comment_reactions WHERE id = ?")->execute([$existing['id']]);
            $pdo->prepare("UPDATE comments SET reaction_count = GREATEST(reaction_count - 1, 0) WHERE id = ?")->execute([$commentId]);
            $action = 'removed';

            // Update leaderboard stats for comment owner (decrement helpful count)
            if ($reactionType === 'like' && $commentOwnerId > 0 && file_exists(__DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php')) {
                require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php';
                leaderboardTriggerHelpfulRemoved($pdo, $commentOwnerId);
            }
        } else {
            // Add reaction
            $pdo->prepare("INSERT INTO comment_reactions (comment_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, NOW())")
                ->execute([$commentId, $userId, $reactionType]);
            $pdo->prepare("UPDATE comments SET reaction_count = reaction_count + 1 WHERE id = ?")->execute([$commentId]);
            $action = 'added';
            if (function_exists('eventsRecordActivity')) {
                eventsRecordActivity($pdo, $userId, 'comment_reaction_added', 'comment', $commentId, [
                    'topic_id' => (int)($comment['topic_id'] ?? 0),
                    'reaction_type' => $reactionType,
                    'subject_user_id' => $commentOwnerId,
                ]);
            }

            // Update leaderboard stats for comment owner (increment helpful count)
            if ($reactionType === 'like' && $commentOwnerId > 0 && file_exists(__DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php')) {
                require_once __DIR__ . '/../includes/src/Modules/Leaderboard/Legacy/triggers.php';
                leaderboardTriggerHelpful($pdo, $commentOwnerId);
            }
        }

        // Get updated reaction counts
        $reactionsStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM comment_reactions WHERE comment_id = ? GROUP BY reaction_type");
        $reactionsStmt->execute([$commentId]);
        $reactions = [];
        while ($row = $reactionsStmt->fetch()) {
            $reactions[$row['reaction_type']] = (int)$row['count'];
        }

        // Get user's reactions
        $userReactionsStmt = $pdo->prepare("SELECT reaction_type FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
        $userReactionsStmt->execute([$commentId, $userId]);
        $userReactions = $userReactionsStmt->fetchAll(PDO::FETCH_COLUMN);

        jsonResponse(200, [
            'success' => true,
            'action' => $action,
            'reactions' => $reactions,
            'user_reactions' => $userReactions,
            '_token' => csrf_token()
        ]);
    } catch (Throwable $e) {
        jsonResponse(500, ['error' => 'Reaksiyon eklenemedi.']);
    }
}

// ─── POST: Report comment ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($input['action'] ?? '') === 'report') {
    if (!$isLoggedIn) jsonResponse(401, ['error' => 'Şikayet etmek için giriş yapmalısınız.']);

    $commentId = (int)($input['comment_id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    $details = trim((string)($input['details'] ?? ''));

    if ($commentId <= 0) jsonResponse(400, ['error' => 'Geçersiz yorum ID.']);
    if ($reason === '') jsonResponse(400, ['error' => 'Lütfen bir şikayet sebebi seçin.']);

    $validReasons = ['spam', 'abusive', 'inappropriate', 'misinformation', 'other'];
    if (!in_array($reason, $validReasons, true)) jsonResponse(400, ['error' => 'Geçersiz şikayet sebebi.']);

    // Rate limit reports
    $reportRateKey = 'comment_report_' . (int)$_SESSION['_auth_user_id'];
    if (!checkRateLimit($reportRateKey, $commentReportRateMax, $commentReportRateWindow)) {
        jsonResponse(429, ['error' => 'Çok fazla şikayet işlemi yaptınız. Lütfen bekleyin.']);
    }
    incrementRateLimit($reportRateKey, $commentReportRateWindow);

    try {
        // Verify comment exists and get topic_id
        $checkStmt = $pdo->prepare("SELECT id, topic_id, user_id, body FROM comments WHERE id = ? AND deleted_at IS NULL");
        $checkStmt->execute([$commentId]);
        $comment = $checkStmt->fetch();
        if (!$comment) jsonResponse(404, ['error' => 'Yorum bulunamadı.']);

        $topicId = (int)$comment['topic_id'];
        $commentOwnerId = (int)($comment['user_id'] ?? 0);
        $reporterId = (int)$_SESSION['_auth_user_id'];

        // Don't allow reporting own comments
        if ($commentOwnerId === $reporterId) {
            jsonResponse(400, ['error' => 'Kendi yorumunuzu şikayet edemezsiniz.']);
        }

        // Check for duplicate report in comment_reports
        try {
            $dupStmt = $pdo->prepare("SELECT id FROM comment_reports WHERE comment_id = ? AND user_id = ? AND status = 'open'");
            $dupStmt->execute([$commentId, $reporterId]);
            if ($dupStmt->fetch()) {
                jsonResponse(400, ['error' => 'Bu yorumu zaten şikayet ettiniz.']);
            }
        } catch (Throwable $e) {
            // Table might not exist yet, continue
        }

        $reasonMap = [
            'spam' => 'Spam / Reklam',
            'abusive' => 'Küfürlü / Hakaret',
            'inappropriate' => 'Uygunsuz İçerik',
            'misinformation' => 'Yanıltıcı Bilgi',
            'other' => 'Diğer',
        ];

        // Store report in comment_reports table
        $mappedReason = $reasonMap[$reason] ?? $reason;
        if ($details !== '') {
            $mappedReason .= ' - ' . $details;
        }

        try {
            $insertReport = $pdo->prepare("INSERT INTO comment_reports (comment_id, user_id, reason, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
            $insertReport->execute([
                $commentId,
                $reporterId,
                $mappedReason
            ]);

            if ($autoHideCount > 0) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comment_reports WHERE comment_id = ?");
                $countStmt->execute([$commentId]);
                $totalReports = (int)$countStmt->fetchColumn();

                if ($totalReports >= $autoHideCount) {
                    $hideStmt = $pdo->prepare("UPDATE comments SET status = 'pending' WHERE id = ?");
                    $hideStmt->execute([$commentId]);
                }
            }

        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Şikayet kaydedilemedi.']);
        }

        // Log activity
        logActivity($pdo, 'comment_reported', 'comment', $commentId, [
            'topic_id' => $topicId,
            'reason' => $reason,
            'reporter_id' => $reporterId,
        ]);

        jsonResponse(200, [
            'success' => true,
            'message' => 'Şikayetiniz alındı. En kısa sürede incelenecek.',
            '_token' => csrf_token(),
        ]);
    } catch (Throwable $e) {
        jsonResponse(500, ['error' => 'Şikayet işlenemedi.']);
    }
}

// ─── GET: Edit history ─────────────────────────────────────────
jsonResponse(405, ['error' => 'Geçersiz istek metodu.']);


