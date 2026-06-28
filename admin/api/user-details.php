<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/init.php";

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int)($_SESSION["_auth_user_id"] ?? 0);
$currentUserIsAdmin = $currentUserId > 0 && userHasPermission($pdo, $currentUserId, 'admin.access');
$canManageUsers = $currentUserId > 0 && userHasPermission($pdo, $currentUserId, "users.edit");

if ($currentUserId <= 0 || (!$currentUserIsAdmin && !$canManageUsers)) {
    sendForbidden('Bu islemi yapma yetkiniz yok.');
}

session_write_close();

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    sendValidationError('Gecersiz kullanici ID.');
}

$userId = (int)$_GET['id'];
$userInfo = usersGetGroupInfo($pdo, $userId);
if ($userInfo && function_exists('usersDecorateUserWithPrimaryGroup')) {
    $userInfo = usersDecorateUserWithPrimaryGroup($pdo, $userInfo);
}

if (!$userInfo) {
    sendNotFound('Kullanici bulunamadi.');
}

$groupHistory = usersGetGroupHistory($pdo, $userId, 5);

$stats = [
    'total_topics' => 0,
    'total_comments' => 0,
    'total_downloads' => 0,
    'helpful_count' => 0,
];

try {
    $topicStmt = $pdo->prepare("SELECT COUNT(*), SUM(download_count) FROM topics WHERE author_id = ? AND status = 'published' AND deleted_at IS NULL");
    $topicStmt->execute([$userId]);
    $topicRow = $topicStmt->fetch(PDO::FETCH_NUM);
    $stats['total_topics'] = (int)($topicRow[0] ?? 0);
    $stats['total_downloads'] = (int)($topicRow[1] ?? 0);

    $commentStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ? AND deleted_at IS NULL");
    $commentStmt->execute([$userId]);
    $stats['total_comments'] = (int)$commentStmt->fetchColumn();

    $helpfulStmt = $pdo->prepare("SELECT COUNT(*) FROM reactions WHERE user_id = ? AND type = 'helpful'");
    $helpfulStmt->execute([$userId]);
    $stats['helpful_count'] = (int)$helpfulStmt->fetchColumn();
} catch (Throwable $e) {
    appLogException($e, ["source" => "user-details-api", "user_id" => $userId]);
}

// ── 360° ek veriler ──
$recentTopics = [];
$recentComments = [];
$reportsAbout = 0;
$restrictions = [];
$loginIps = [];
$auditHistory = [];
$banInfo = ['is_banned' => 0, 'banned_at' => null, 'ban_reason' => null, 'last_login_ip' => null];

try {
    // Son konular
    $rt = $pdo->prepare("SELECT id, title, slug, status, created_at FROM topics WHERE author_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $rt->execute([$userId]);
    foreach ($rt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recentTopics[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'url' => topicUrlForRow($row),
            'status' => (string)$row['status'],
            'created_at' => formatAppDateTime($row['created_at']),
        ];
    }

    // Son yorumlar
    $rc = $pdo->prepare("SELECT id, topic_id, body, created_at FROM comments WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $rc->execute([$userId]);
    foreach ($rc->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recentComments[] = [
            'id' => (int)$row['id'],
            'topic_id' => (int)$row['topic_id'],
            'excerpt' => mb_substr(trim((string)$row['body']), 0, 120),
            'created_at' => formatAppDateTime($row['created_at']),
        ];
    }

    // Hakkında açılan şikayet sayısı
    try {
        $ra = $pdo->prepare("SELECT COUNT(*) FROM user_reports WHERE reported_user_id = ?");
        $ra->execute([$userId]);
        $reportsAbout = (int)$ra->fetchColumn();
    } catch (Throwable $e) { /* tablo yoksa 0 */ }

    // Aktif kısıtlamalar (mevcut helper)
    if (function_exists('usersGetRestrictions')) {
        $restrictions = usersGetRestrictions($pdo, $userId);
    }

    // Ban / durum / son giriş IP
    $banInfo = [
        'is_banned' => (int)($userInfo['is_banned'] ?? 0),
        'banned_at' => ($userInfo['banned_at'] ?? null) ? formatAppDateTime($userInfo['banned_at']) : null,
        'ban_reason' => $userInfo['ban_reason'] ?? null,
        'last_login_ip' => $userInfo['last_login_ip'] ?? null,
    ];

    // Son farklı IP'ler (security_events üzerinden — user_id + ip_address içerir)
    try {
        $ips = $pdo->prepare("SELECT DISTINCT ip_address FROM security_events WHERE user_id = ? AND ip_address IS NOT NULL AND ip_address <> '' ORDER BY id DESC LIMIT 5");
        $ips->execute([$userId]);
        $loginIps = array_values(array_filter($ips->fetchAll(PDO::FETCH_COLUMN) ?: []));
    } catch (Throwable $e) { /* tablo/kolon yoksa atla */ }
    // last_login_ip'yi de listeye dahil et (yoksa)
    if (!empty($banInfo['last_login_ip']) && !in_array($banInfo['last_login_ip'], $loginIps, true)) {
        array_unshift($loginIps, $banInfo['last_login_ip']);
    }

    // Bu kullanıcıya uygulanan admin eylemleri (audit)
    if (function_exists('adminGetActionLog')) {
        $auditRows = adminAuditLogger()->getActionLog($pdo, ['target_type' => 'user', 'target_id' => $userId], 10, 0);
        foreach ($auditRows as $a) {
            $auditHistory[] = [
                'action' => adminAuditLogger()->actionLabel((string) $a['action_type']),
                'actor' => (string)($a['actor_name'] ?? ('#' . $a['actor_id'])),
                'reason' => (string)($a['reason'] ?? ''),
                'reverted' => !empty($a['reverted_at']),
                'created_at' => formatAppDateTime($a['created_at']),
            ];
        }
    }
} catch (Throwable $e) {
    appLogException($e, ["source" => "user-details-api-360", "user_id" => $userId]);
}

sendSuccess('Kullanici detaylari basariyla getirildi.', [
    'data' => [
        'id' => $userInfo['id'],
        'name' => $userInfo['name'],
        'email' => $userInfo['email'],
        'avatar' => $userInfo['avatar'],
        'group_id' => $userInfo['group_id'] ?? null,
        'group_name' => $userInfo['group_name'] ?? '',
        'group_slug' => $userInfo['group_slug'] ?? '',
        'status' => $userInfo['status'],
        'created_at' => formatAppDateTime($userInfo['created_at']),
        'last_login_at' => $userInfo['last_login_at'] ? formatAppDateTime($userInfo['last_login_at']) : 'Hic giris yapmadi',
        'bio' => $userInfo['bio'],
        'website' => $userInfo['website'],
        'location' => $userInfo['location'],
        'social_github' => $userInfo['social_github'],
        'social_twitter' => $userInfo['social_twitter'],
        'social_discord' => $userInfo['social_discord'],
        'stats' => $stats,
        'group_history' => $groupHistory,
        // 360° ek veriler
        'is_banned' => $banInfo['is_banned'],
        'banned_at' => $banInfo['banned_at'],
        'ban_reason' => $banInfo['ban_reason'],
        'last_login_ip' => $banInfo['last_login_ip'],
        'reports_about' => $reportsAbout,
        'recent_topics' => $recentTopics,
        'recent_comments' => $recentComments,
        'restrictions' => $restrictions,
        'login_ips' => $loginIps,
        'audit_history' => $auditHistory,
    ],
]);
