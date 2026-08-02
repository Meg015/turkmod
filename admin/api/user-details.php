<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/init.php";
require_once __DIR__ . "/../helpers.php";
require_once __DIR__ . "/../../includes/src/Engine/Users/Support/profile-helpers.php";
require_once __DIR__ . "/../../includes/src/Engine/Users/Support/users-helpers.php";
if (file_exists(__DIR__ . "/../../includes/src/Engine/AdminAudit/Support/helpers.php")) {
    require_once __DIR__ . "/../../includes/src/Engine/AdminAudit/Support/helpers.php";
}

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int)($_SESSION["_auth_user_id"] ?? 0);
if ($currentUserId <= 0 || !userHasPermission($pdo, $currentUserId, 'comments.view')) {
    sendForbidden('Bu islemi yapma yetkiniz yok.');
}

$currentUserIsAdmin = userHasPermission($pdo, $currentUserId, 'admin.access');
$canManageUsers = userHasPermission($pdo, $currentUserId, "users.edit");
$canViewSensitiveUserDetails = $currentUserIsAdmin || $canManageUsers;
$canBanUsers = $canManageUsers;
$canRestrictUsers = $canManageUsers;
$canAddAdminNotes = $canManageUsers;

session_write_close();

$userId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    sendValidationError('Gecersiz kullanici ID.');
}

// ── Kullanıcı temel bilgileri (fallback korumalı) ──
$userInfo = null;
if (function_exists('usersGetGroupInfo')) {
    try {
        $userInfo = usersGetGroupInfo($pdo, $userId);
        if ($userInfo && function_exists('usersDecorateUserWithPrimaryGroup')) {
            $userInfo = usersDecorateUserWithPrimaryGroup($pdo, $userInfo);
        }
    } catch (Throwable $e) {}
}

if (!$userInfo && function_exists('usersGetById')) {
    try {
        $userInfo = usersGetById($pdo, $userId);
    } catch (Throwable $e) {}
}

if (!$userInfo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}
}

if (!$userInfo) {
    sendNotFound('Kullanici bulunamadi.');
}

$groupHistory = [];
if (function_exists('usersGetGroupHistory')) {
    try {
        $groupHistory = usersGetGroupHistory($pdo, $userId, 5);
    } catch (Throwable $e) {}
}

$stats = [
    'total_topics' => 0,
    'total_comments' => 0,
    'total_downloads' => 0,
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
} catch (Throwable $e) {
    appLogException($e, ["source" => "user-details-api-stats", "user_id" => $userId]);
}

// ── 360° ek veriler ──
$recentTopics = [];
$recentComments = [];
$reports = [];
$reportsAbout = 0;
$restrictions = [];
$loginIps = [];
$auditHistory = [];
$banHistory = [];
$recentActivity = [];
$adminNotes = [];
$restrictionHistory = [];
$moderationHistory = [];
$moderationHistoryRows = [];
$lastActivityAt = null;
$banInfo = [
    'is_banned' => (int)($userInfo['is_banned'] ?? 0),
    'banned_at' => (!empty($userInfo['banned_at'])) ? formatAppDateTime((string)$userInfo['banned_at']) : null,
    'ban_reason' => (string)($userInfo['ban_reason'] ?? ''),
    'last_login_ip' => (string)($userInfo['last_login_ip'] ?? ''),
];

$formatDetailDate = static function ($value): string {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? formatAppDateTime($value) : '';
};

$moderationActionMeta = static function (string $actionType): array {
    return match ($actionType) {
        'ban' => ['label' => 'Banlandı', 'category' => 'ban', 'tone' => 'danger'],
        'unban' => ['label' => 'Ban Kaldırıldı', 'category' => 'ban', 'tone' => 'success'],
        'restrict' => ['label' => 'Kısıtlama Eklendi', 'category' => 'restriction', 'tone' => 'warning'],
        'unrestrict' => ['label' => 'Kısıtlama Kaldırıldı', 'category' => 'restriction', 'tone' => 'success'],
        'unrestrict_all' => ['label' => 'Tüm Kısıtlamalar Kaldırıldı', 'category' => 'restriction', 'tone' => 'success'],
        'status_change' => ['label' => 'Durum Değiştirildi', 'category' => 'account', 'tone' => 'info'],
        'group_change' => ['label' => 'Grup Değiştirildi', 'category' => 'account', 'tone' => 'info'],
        'user_admin_note_added' => ['label' => 'Admin Notu Eklendi', 'category' => 'note', 'tone' => 'info'],
        default => ['label' => $actionType !== '' ? $actionType : 'Moderasyon İşlemi', 'category' => 'moderation', 'tone' => 'muted'],
    };
};

$pushModerationHistory = static function (array $row) use (&$moderationHistoryRows, $formatDetailDate, $moderationActionMeta): void {
    $actionType = (string)($row['action_type'] ?? '');
    $createdRaw = (string)($row['_created_raw'] ?? ($row['created_at_raw'] ?? ''));
    $meta = $moderationActionMeta($actionType);
    $entry = [
        'action' => (string)($row['action'] ?? $meta['label']),
        'action_type' => $actionType,
        'category' => (string)($row['category'] ?? $meta['category']),
        'tone' => (string)($row['tone'] ?? $meta['tone']),
        'type' => (string)($row['type'] ?? ''),
        'reason' => (string)($row['reason'] ?? ''),
        'admin' => (string)($row['admin'] ?? ($row['actor_name'] ?? ($row['actor'] ?? ''))),
        'created_at' => (string)($row['created_at'] ?? ($createdRaw !== '' ? $formatDetailDate($createdRaw) : '')),
        '_created_raw' => $createdRaw,
    ];
    if (array_key_exists('expires_at', $row)) {
        $entry['expires_at'] = (string)$row['expires_at'];
    }
    if (array_key_exists('active', $row)) {
        $entry['active'] = (bool)$row['active'];
    }
    $moderationHistoryRows[] = $entry;
};

// Son konular
try {
    $rt = $pdo->prepare("SELECT id, title, slug, status, created_at FROM topics WHERE author_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20");
    $rt->execute([$userId]);
    foreach ($rt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $recentTopics[] = [
            'id' => (int)$row['id'],
            'title' => (string)($row['title'] ?? ''),
            'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '',
            'status' => (string)($row['status'] ?? ''),
            'created_at' => formatAppDateTime((string)$row['created_at']),
        ];
    }
} catch (Throwable $e) {}

// Son yorumlar
try {
    $rc = $pdo->prepare("SELECT c.id, c.topic_id, c.body, c.status, c.created_at, t.title AS topic_title, t.slug AS topic_slug
        FROM comments c
        LEFT JOIN topics t ON t.id = c.topic_id
        WHERE c.user_id = ? AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC
        LIMIT 20");
    $rc->execute([$userId]);
    foreach ($rc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $recentComments[] = [
            'id' => (int)$row['id'],
            'topic_id' => (int)($row['topic_id'] ?? 0),
            'topic_title' => (string)($row['topic_title'] ?? ''),
            'url' => (!empty($row['topic_slug']) && function_exists('topicUrl')) ? topicUrl((string)$row['topic_slug'], (int)$row['topic_id']) . '#comment-' . (int)$row['id'] : '',
            'status' => (string)($row['status'] ?? ''),
            'excerpt' => mb_substr(trim((string)($row['body'] ?? '')), 0, 160),
            'created_at' => formatAppDateTime((string)$row['created_at']),
        ];
    }
} catch (Throwable $e) {}

// Hakkında açılan şikayetler
try {
    $ra = $pdo->prepare("SELECT COUNT(*) FROM user_reports WHERE reported_user_id = ?");
    $ra->execute([$userId]);
    $reportsAbout = (int)$ra->fetchColumn();
    $reportList = $pdo->prepare("SELECT r.id, r.status, r.reason, r.created_at, reporter.username AS reporter_name
        FROM user_reports r
        LEFT JOIN users reporter ON reporter.id = r.reporter_user_id
        WHERE r.reported_user_id = ?
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 20");
    $reportList->execute([$userId]);
    foreach ($reportList->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $reports[] = [
            'id' => (int)$row['id'],
            'status' => (string)($row['status'] ?? ''),
            'reason' => (string)($row['reason'] ?? ''),
            'reporter' => (string)($row['reporter_name'] ?? ''),
            'created_at' => $formatDetailDate($row['created_at'] ?? ''),
        ];
    }
} catch (Throwable $e) {}

// Aktif kısıtlamalar
if (function_exists('usersGetRestrictions')) {
    try {
        $restrictions = usersGetRestrictions($pdo, $userId);
    } catch (Throwable $e) {}
}

// Admin Audit Log
if (function_exists('adminAuditLogger') && function_exists('adminGetActionLog')) {
    try {
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
    } catch (Throwable $e) {}
}

// Ban Geçmişi
if (function_exists('ensureAdminActionLogTable')) {
    try {
        ensureAdminActionLogTable($pdo);
        $bh = $pdo->prepare("SELECT l.action_type, l.reason, l.created_at, actor.username AS actor_name
            FROM admin_action_log l
            LEFT JOIN users actor ON actor.id = l.actor_id
            WHERE l.target_type = 'user'
              AND l.target_id = ?
              AND l.action_type IN ('ban', 'unban')
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT 5");
        $bh->execute([$userId]);
        foreach ($bh->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $actionType = (string)($row['action_type'] ?? '');
            $banHistoryRow = [
                'action' => $actionType === 'unban' ? 'Ban Kaldırıldı' : 'Banlandı',
                'action_type' => $actionType,
                'category' => 'ban',
                'tone' => $actionType === 'unban' ? 'success' : 'danger',
                'reason' => (string)($row['reason'] ?? ''),
                'admin' => (string)($row['actor_name'] ?? ''),
                'created_at' => $formatDetailDate($row['created_at'] ?? ''),
                '_created_raw' => (string)($row['created_at'] ?? ''),
            ];
            $pushModerationHistory($banHistoryRow);
            unset($banHistoryRow['category'], $banHistoryRow['tone'], $banHistoryRow['_created_raw']);
            $banHistory[] = $banHistoryRow;
        }
    } catch (Throwable $e) {}
}

// Kullanıcı Hareketleri
if (function_exists('userActivityList')) {
    try {
        $activityGroups = function_exists('userActivityGroupLabels') ? userActivityGroupLabels() : [];
        foreach (userActivityList($pdo, ['user_id' => $userId], 20, 0) as $row) {
            $eventType = (string)($row['event_type'] ?? '');
            $eventGroup = (string)($row['event_group'] ?? '');
            $createdAt = (string)($row['created_at'] ?? '');
            if ($lastActivityAt === null && $createdAt !== '') {
                $lastActivityAt = $createdAt;
            }
            $deviceParts = array_values(array_filter([
                trim((string)($row['browser'] ?? '')),
                trim((string)($row['platform'] ?? '')),
            ]));

            $recentActivity[] = [
                'event' => function_exists('userActivityEventLabel') ? userActivityEventLabel($eventType) : $eventType,
                'group' => (string)($activityGroups[$eventGroup] ?? $eventGroup),
                'title' => trim((string)($row['title'] ?? '')),
                'ip_address' => $canViewSensitiveUserDetails ? (string)($row['ip_address'] ?? '') : '',
                'device' => implode(' / ', $deviceParts),
                'actor' => (string)($row['actor_name'] ?? ''),
                'created_at' => $formatDetailDate($createdAt),
            ];
        }
    } catch (Throwable $e) {}
}

// Admin Notları
if (function_exists('usersGetAdminNotes')) {
    try {
        foreach (usersGetAdminNotes($pdo, $userId, 20) as $note) {
            $adminNotes[] = [
                'note' => (string)($note['note'] ?? ''),
                'tone' => (string)($note['tone'] ?? 'info'),
                'tags' => (string)($note['tags'] ?? ''),
                'admin' => (string)($note['admin_name'] ?? ($note['admin_email'] ?? '')),
                'created_at' => $formatDetailDate($note['created_at'] ?? ''),
            ];
        }
    } catch (Throwable $e) {}
}

// Kisitlama gecmisi ve kaldirma islemleri
if (function_exists('usersTableExists') && usersTableExists($pdo, 'user_restrictions')) {
    try {
        $restrictionHistoryRows = [];
        $rh = $pdo->prepare("SELECT r.*, a.username AS admin_name
            FROM user_restrictions r
            LEFT JOIN users a ON a.id = r.admin_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT 20");
        $rh->execute([$userId]);
        foreach ($rh->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $expiresAt = (string)($row['expires_at'] ?? '');
            $restrictionHistoryRows[] = [
                'action' => 'Kisitlama Eklendi',
                'action_type' => 'restrict',
                'category' => 'restriction',
                'tone' => 'warning',
                'type' => function_exists('usersGetRestrictionTypeLabel') ? usersGetRestrictionTypeLabel((string)($row['restriction_type'] ?? '')) : (string)($row['restriction_type'] ?? 'Kisitlama'),
                'reason' => (string)($row['reason'] ?? ''),
                'admin' => (string)($row['admin_name'] ?? ''),
                'created_at' => $formatDetailDate($row['created_at'] ?? ''),
                '_created_raw' => (string)($row['created_at'] ?? ''),
                'expires_at' => $expiresAt !== '' ? $formatDetailDate($expiresAt) : 'Suresiz',
                'active' => $expiresAt === '' || strtotime($expiresAt) > time(),
            ];
        }

        if (function_exists('ensureAdminActionLogTable')) {
            try {
                ensureAdminActionLogTable($pdo);
                $rl = $pdo->prepare("SELECT l.action_type, l.reason, l.old_value, l.created_at, actor.username AS actor_name
                    FROM admin_action_log l
                    LEFT JOIN users actor ON actor.id = l.actor_id
                    WHERE l.target_type = 'user'
                      AND l.target_id = ?
                      AND l.action_type IN ('unrestrict', 'unrestrict_all')
                    ORDER BY l.created_at DESC, l.id DESC
                    LIMIT 20");
                $rl->execute([$userId]);
                foreach ($rl->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $actionType = (string)($row['action_type'] ?? '');
                    $oldValue = json_decode((string)($row['old_value'] ?? ''), true);
                    $oldValue = is_array($oldValue) ? $oldValue : [];
                    $rawType = (string)($oldValue['restriction_type'] ?? ($oldValue['type'] ?? ''));
                    $typeLabel = $actionType === 'unrestrict_all'
                        ? 'Tum Kisitlamalar'
                        : (function_exists('usersGetRestrictionTypeLabel') ? usersGetRestrictionTypeLabel($rawType) : ($rawType ?: 'Kisitlama'));
                    $restrictionHistoryRows[] = [
                        'action' => $actionType === 'unrestrict_all' ? 'Tum Kisitlamalar Kaldirildi' : 'Kisitlama Kaldirildi',
                        'action_type' => $actionType,
                        'category' => 'restriction',
                        'tone' => 'success',
                        'type' => $typeLabel,
                        'reason' => (string)($row['reason'] ?? ($oldValue['reason'] ?? '')),
                        'admin' => (string)($row['actor_name'] ?? ''),
                        'created_at' => $formatDetailDate($row['created_at'] ?? ''),
                        '_created_raw' => (string)($row['created_at'] ?? ''),
                        'expires_at' => !empty($oldValue['expires_at']) ? $formatDetailDate($oldValue['expires_at']) : 'Suresiz',
                        'active' => false,
                    ];
                }
            } catch (Throwable $e) {}
        }

        usort($restrictionHistoryRows, static function (array $a, array $b): int {
            return (strtotime((string)($b['_created_raw'] ?? '')) ?: 0) <=> (strtotime((string)($a['_created_raw'] ?? '')) ?: 0);
        });
        foreach (array_slice($restrictionHistoryRows, 0, 10) as $row) {
            $pushModerationHistory($row);
            unset($row['_created_raw']);
            $restrictionHistory[] = $row;
        }
    } catch (Throwable $e) {
        appLogException($e, ['source' => 'user-details-api-restrictions', 'user_id' => $userId]);
    }
}

if ($lastActivityAt === null) {
    foreach (['last_activity_at', 'last_login_at', 'updated_at', 'created_at'] as $column) {
        if (!empty($userInfo[$column])) {
            $lastActivityAt = (string)$userInfo[$column];
            break;
        }
    }
}

usort($moderationHistoryRows, static function (array $a, array $b): int {
    return (strtotime((string)($b['_created_raw'] ?? '')) ?: 0) <=> (strtotime((string)($a['_created_raw'] ?? '')) ?: 0);
});
foreach (array_slice($moderationHistoryRows, 0, 10) as $row) {
    unset($row['_created_raw']);
    $moderationHistory[] = $row;
}

$publicProfileUrl = (function_exists('publicProfileUrl') && is_array($userInfo))
    ? publicProfileUrl($userInfo)
    : '';
$userManagementUrl = 'users.php?' . http_build_query(['search' => (string)($userInfo['username'] ?? '')]);
$activityUrl = 'users.php?' . http_build_query(['tab' => 'activity', 'user_id' => $userId]);

$structuredData = [
    'user' => [
        'id' => (int)($userInfo['id'] ?? $userId),
        'username' => (string)($userInfo['username'] ?? ''),
        'name' => (string)($userInfo['username'] ?? ''),
        'email' => $canViewSensitiveUserDetails ? (string)($userInfo['email'] ?? '') : '',
        'avatar' => (string)($userInfo['avatar'] ?? ''),
        'group_id' => $userInfo['group_id'] ?? null,
        'group_name' => (string)($userInfo['group_name'] ?? 'Kullanıcı'),
        'group_slug' => (string)($userInfo['group_slug'] ?? ''),
        'status' => (string)($userInfo['status'] ?? 'active'),
        'created_at' => !empty($userInfo['created_at']) ? formatAppDateTime((string)$userInfo['created_at']) : '-',
        'last_login_at' => !empty($userInfo['last_login_at']) ? formatAppDateTime((string)$userInfo['last_login_at']) : 'Hiç giriş yapmadı',
        'last_activity_at' => $lastActivityAt ? $formatDetailDate($lastActivityAt) : '',
        'bio' => (string)($userInfo['bio'] ?? ''),
        'website' => (string)($userInfo['website'] ?? ''),
        'location' => (string)($userInfo['location'] ?? ''),
        'is_banned' => (int)($banInfo['is_banned'] ?? 0),
        'banned_at' => $banInfo['banned_at'] ?? null,
        'ban_reason' => $banInfo['ban_reason'] ?? null,
        'last_login_ip' => $canViewSensitiveUserDetails ? ($banInfo['last_login_ip'] ?? null) : null,
    ],
    'stats' => array_merge($stats, [
        'reports_about' => $reportsAbout,
        'active_restrictions' => is_array($restrictions) ? count($restrictions) : 0,
    ]),
    'activity' => $recentActivity,
    'comments' => $recentComments,
    'topics' => $recentTopics,
    'reports' => $reports,
    'notes' => $adminNotes,
    'restrictions' => is_array($restrictions) ? $restrictions : [],
    'moderation_history' => $moderationHistory,
    'permissions' => [
        'view_sensitive' => $canViewSensitiveUserDetails,
        'manage_users' => $canManageUsers,
        'ban' => $canBanUsers && $userId !== $currentUserId,
        'restrict' => $canRestrictUsers && $userId !== $currentUserId,
        'add_note' => $canAddAdminNotes && $userId !== $currentUserId,
    ],
    'links' => [
        'public_profile' => $publicProfileUrl,
        'user_management' => $userManagementUrl,
        'full_activity' => $activityUrl,
    ],
];

sendSuccess('Kullanici detaylari basariyla getirildi.', [
    'data' => array_merge([
        'id' => (int)($userInfo['id'] ?? $userId),
        'username' => (string) ($userInfo['username'] ?? ''),
        'name' => (string) ($userInfo['username'] ?? ''),
        'email' => $canViewSensitiveUserDetails ? (string)($userInfo['email'] ?? '') : '',
        'avatar' => (string)($userInfo['avatar'] ?? ''),
        'group_id' => $userInfo['group_id'] ?? null,
        'group_name' => (string)($userInfo['group_name'] ?? 'Kullanıcı'),
        'group_slug' => (string)($userInfo['group_slug'] ?? ''),
        'status' => (string)($userInfo['status'] ?? 'active'),
        'created_at' => !empty($userInfo['created_at']) ? formatAppDateTime((string)$userInfo['created_at']) : '-',
        'last_login_at' => !empty($userInfo['last_login_at']) ? formatAppDateTime((string)$userInfo['last_login_at']) : 'Hiç giriş yapmadı',
        'last_activity_at' => $lastActivityAt ? $formatDetailDate($lastActivityAt) : '',
        'bio' => (string)($userInfo['bio'] ?? ''),
        'website' => (string)($userInfo['website'] ?? ''),
        'location' => (string)($userInfo['location'] ?? ''),
        'social_github' => (string)($userInfo['social_github'] ?? ''),
        'social_twitter' => (string)($userInfo['social_twitter'] ?? ''),
        'social_discord' => (string)($userInfo['social_discord'] ?? ''),
        'stats' => $stats,
        'group_history' => $groupHistory,
        'is_banned' => (int)($banInfo['is_banned'] ?? 0),
        'banned_at' => $banInfo['banned_at'] ?? null,
        'ban_reason' => $banInfo['ban_reason'] ?? null,
        'last_login_ip' => $canViewSensitiveUserDetails ? ($banInfo['last_login_ip'] ?? null) : null,
        'reports_about' => $reportsAbout,
        'recent_topics' => $recentTopics,
        'recent_comments' => $recentComments,
        'recent_activity' => $recentActivity,
        'admin_notes' => $adminNotes,
        'moderation_history' => $moderationHistory,
        'ban_history' => $banHistory,
        'restriction_history' => $restrictionHistory,
        'restrictions' => is_array($restrictions) ? $restrictions : [],
        'login_ips' => $loginIps,
        'audit_history' => $auditHistory,
        'can_manage_users' => $canManageUsers,
        'can_moderate' => $canManageUsers && $userId !== $currentUserId,
    ], $structuredData),
]);
