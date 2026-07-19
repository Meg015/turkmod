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
    appLogException($e, ["source" => "user-details-api", "user_id" => $userId]);
}

// ── 360° ek veriler ──
$recentTopics = [];
$recentComments = [];
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
$banInfo = ['is_banned' => 0, 'banned_at' => null, 'ban_reason' => null, 'last_login_ip' => null];
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
        } catch (Throwable $e) {
            $banHistory = [];
        }
    }

    if (function_exists('userActivityList')) {
        $activityGroups = function_exists('userActivityGroupLabels') ? userActivityGroupLabels() : [];
        foreach (userActivityList($pdo, ['user_id' => $userId], 6, 0) as $row) {
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
                'ip_address' => (string)($row['ip_address'] ?? ''),
                'device' => implode(' / ', $deviceParts),
                'actor' => (string)($row['actor_name'] ?? ''),
                'created_at' => $formatDetailDate($createdAt),
            ];
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

    if (function_exists('usersGetAdminNotes')) {
        foreach (usersGetAdminNotes($pdo, $userId, 5) as $note) {
            $adminNotes[] = [
                'note' => (string)($note['note'] ?? ''),
                'tone' => (string)($note['tone'] ?? 'info'),
                'tags' => (string)($note['tags'] ?? ''),
                'admin' => (string)($note['admin_name'] ?? ($note['admin_email'] ?? '')),
                'created_at' => $formatDetailDate($note['created_at'] ?? ''),
            ];
        }
    }

    if (usersTableExists($pdo, 'user_restrictions')) {
        try {
            $restrictionHistoryRows = [];
            $rh = $pdo->prepare("SELECT r.*, a.username AS admin_name
                FROM user_restrictions r
                LEFT JOIN users a ON a.id = r.admin_id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 10");
            $rh->execute([$userId]);
            foreach ($rh->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $expiresAt = (string)($row['expires_at'] ?? '');
                $restrictionHistoryRows[] = [
                    'action' => 'Kısıtlama Eklendi',
                    'action_type' => 'restrict',
                    'category' => 'restriction',
                    'tone' => 'warning',
                    'type' => function_exists('usersGetRestrictionTypeLabel') ? usersGetRestrictionTypeLabel((string)$row['restriction_type']) : (string)$row['restriction_type'],
                    'reason' => (string)($row['reason'] ?? ''),
                    'admin' => (string)($row['admin_name'] ?? ''),
                    'created_at' => $formatDetailDate($row['created_at'] ?? ''),
                    '_created_raw' => (string)($row['created_at'] ?? ''),
                    'expires_at' => $expiresAt !== '' ? $formatDetailDate($expiresAt) : 'Süresiz',
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
                        LIMIT 10");
                    $rl->execute([$userId]);
                    foreach ($rl->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                        $actionType = (string)($row['action_type'] ?? '');
                        $oldValue = json_decode((string)($row['old_value'] ?? ''), true);
                        $oldValue = is_array($oldValue) ? $oldValue : [];
                        $typeLabel = 'Kısıtlama';
                        $reason = (string)($row['reason'] ?? '');
                        $expiresLabel = '';
                        if ($actionType === 'unrestrict_all') {
                            $items = $oldValue['restrictions'] ?? [];
                            $items = is_array($items) ? $items : [];
                            $labels = [];
                            foreach ($items as $item) {
                                if (!is_array($item)) {
                                    continue;
                                }
                                $rawType = (string)($item['restriction_type'] ?? ($item['type'] ?? ''));
                                $labels[] = function_exists('usersGetRestrictionTypeLabel') ? usersGetRestrictionTypeLabel($rawType) : ($rawType ?: 'Kısıtlama');
                            }
                            $labels = array_values(array_unique(array_filter($labels)));
                            if (!empty($labels)) {
                                $typeLabel = implode(', ', array_slice($labels, 0, 3));
                                if (count($labels) > 3) {
                                    $typeLabel .= ' +' . (count($labels) - 3);
                                }
                            } else {
                                $typeLabel = 'Tüm Kısıtlamalar';
                            }
                        } else {
                            $rawType = (string)($oldValue['restriction_type'] ?? ($oldValue['type'] ?? ''));
                            $typeLabel = function_exists('usersGetRestrictionTypeLabel') ? usersGetRestrictionTypeLabel($rawType) : ($rawType ?: 'Kısıtlama');
                            if ($typeLabel === '') {
                                $typeLabel = 'Kısıtlama';
                            }
                            $reason = (string)($oldValue['reason'] ?? '') ?: $reason;
                            $expiresAt = (string)($oldValue['expires_at'] ?? '');
                            $expiresLabel = $expiresAt !== '' ? $formatDetailDate($expiresAt) : 'Süresiz';
                        }
                        $restrictionHistoryRows[] = [
                            'action' => $actionType === 'unrestrict_all' ? 'Tüm Kısıtlamalar Kaldırıldı' : 'Kısıtlama Kaldırıldı',
                            'action_type' => $actionType,
                            'category' => 'restriction',
                            'tone' => 'success',
                            'type' => $typeLabel,
                            'reason' => $reason,
                            'admin' => (string)($row['actor_name'] ?? ''),
                            'created_at' => $formatDetailDate($row['created_at'] ?? ''),
                            '_created_raw' => (string)($row['created_at'] ?? ''),
                            'expires_at' => $expiresLabel,
                            'active' => false,
                        ];
                    }
                } catch (Throwable $e) {
                    // Action log is optional for legacy installs; active restriction rows still render.
                }
            }
            usort($restrictionHistoryRows, static function (array $a, array $b): int {
                return strtotime((string)($b['_created_raw'] ?? '')) <=> strtotime((string)($a['_created_raw'] ?? ''));
            });
            foreach (array_slice($restrictionHistoryRows, 0, 5) as $row) {
                $pushModerationHistory($row);
                unset($row['_created_raw']);
                $restrictionHistory[] = $row;
            }
        } catch (Throwable $e) {
            $restrictionHistory = [];
        }
    }
} catch (Throwable $e) {
    appLogException($e, ["source" => "user-details-api-360", "user_id" => $userId]);
}

usort($moderationHistoryRows, static function (array $a, array $b): int {
    return (strtotime((string)($b['_created_raw'] ?? '')) ?: 0) <=> (strtotime((string)($a['_created_raw'] ?? '')) ?: 0);
});
foreach (array_slice($moderationHistoryRows, 0, 5) as $row) {
    unset($row['_created_raw']);
    $moderationHistory[] = $row;
}

sendSuccess('Kullanici detaylari basariyla getirildi.', [
    'data' => [
        'id' => $userInfo['id'],
        'username' => (string) ($userInfo['username'] ?? ''),
        'name' => (string) ($userInfo['username'] ?? ''),
        'email' => $userInfo['email'],
        'avatar' => $userInfo['avatar'],
        'group_id' => $userInfo['group_id'] ?? null,
        'group_name' => $userInfo['group_name'] ?? '',
        'group_slug' => $userInfo['group_slug'] ?? '',
        'status' => $userInfo['status'],
        'created_at' => formatAppDateTime($userInfo['created_at']),
        'last_login_at' => $userInfo['last_login_at'] ? formatAppDateTime($userInfo['last_login_at']) : 'Hiç giriş yapmadı',
        'last_activity_at' => $lastActivityAt ? $formatDetailDate($lastActivityAt) : '',
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
        'recent_activity' => $recentActivity,
        'admin_notes' => $adminNotes,
        'moderation_history' => $moderationHistory,
        'ban_history' => $banHistory,
        'restriction_history' => $restrictionHistory,
        'restrictions' => $restrictions,
        'login_ips' => $loginIps,
        'audit_history' => $auditHistory,
        'can_manage_users' => $canManageUsers,
        'can_moderate' => $canManageUsers && $userId !== $currentUserId,
    ],
]);
