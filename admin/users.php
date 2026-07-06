<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

if (
    !isset($_SESSION['_auth_user_id'])
    || (
        !userHasPermission($pdo, (int) $_SESSION['_auth_user_id'], 'users.view')
        && !userHasPermission($pdo, (int) $_SESSION['_auth_user_id'], 'groups.view')
    )
) {
    adminRenderForbiddenPage('Kullanıcı yönetimini görüntülemek için gerekli izin hesabınıza tanımlanmamış.');
}

$pageTitle = 'Kullanıcı Yönetimi';
$currentUserId = (int)$_SESSION['_auth_user_id'];
$csrfToken = csrf_token();
$tabs = ['users', 'groups', 'banned', 'restricted', 'appeals', 'activity'];
$tabPartials = [
    'users' => 'admin/users-tabs/users.php',
    'groups' => 'admin/users-tabs/groups.php',
    'banned' => 'admin/users-tabs/banned.php',
    'restricted' => 'admin/users-tabs/restricted.php',
    'appeals' => 'admin/users-tabs/appeals.php',
    'activity' => 'admin/users-tabs/activity.php',
];
$activeTab = (string)($_GET['tab'] ?? 'users');
if (!in_array($activeTab, $tabs, true)) {
    $activeTab = 'users';
}

$canViewUsers = userHasPermission($pdo, $currentUserId, 'users.view');
$canViewGroups = userHasPermission($pdo, $currentUserId, 'groups.view');

if ($activeTab === 'users' && !$canViewUsers) {
    if ($canViewGroups) {
        $activeTab = 'groups';
    } else {
        adminRenderForbiddenPage('Kullanıcı yönetimini görüntülemek için gerekli izin hesabınıza tanımlanmamış.');
    }
}
if ($activeTab === 'groups' && !$canViewGroups) {
    adminRenderForbiddenPage('Grup yönetimini görüntülemek için gerekli izin hesabınıza tanımlanmamış.');
}
if (in_array($activeTab, ['banned', 'restricted', 'appeals', 'activity'], true) && !$canViewUsers) {
    adminRenderForbiddenPage('Bu sekmeyi görüntülemek için gerekli izin hesabınıza tanımlanmamış.');
}

$groups = function_exists('usersGetGroups') ? usersGetGroups($pdo, false) : [];
$allGroups = $groups;
$validGroupIds = array_map('intval', array_column($groups, 'id'));
$permissionCatalog = function_exists('usersPermissionCatalog') ? usersPermissionCatalog() : [];
$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$selectedGroup = $selectedGroupId > 0 && function_exists('usersGetGroupById') ? usersGetGroupById($pdo, $selectedGroupId) : null;
$selectedGroupPermissions = $selectedGroupId > 0 && function_exists('usersGetGroupPermissionMap') ? usersGetGroupPermissionMap($pdo, $selectedGroupId) : [];

$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$respond = static function (bool $ok, string $message, array $data = []) use ($isAjax, $activeTab): void {
    if ($isAjax) {
        sendJsonResponse($ok ? 200 : 422, $ok, $message, ['ok' => $ok] + $data, $ok ? null : 'user_action_failed');
    }

    flash($ok ? 'success' : 'error', $message);
    $params = ['tab' => $activeTab];
    if (isset($data['group_id']) && (int)$data['group_id'] > 0) {
        $params['group_id'] = (int)$data['group_id'];
    }
    header('Location: users.php?' . http_build_query($params));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
        $respond(false, 'Güvenlik doğrulaması başarısız.');
    }
    if (!userHasPermission($pdo, $currentUserId, 'users.edit') && !userHasPermission($pdo, $currentUserId, 'groups.edit')) {
        $respond(false, 'Bu işlemi yapma yetkiniz yok.');
    }

    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);
    $canEditUsers = userHasPermission($pdo, $currentUserId, 'users.edit');
    $canEditGroups = userHasPermission($pdo, $currentUserId, 'groups.edit');
    $userManagedActions = [
        'save_user',
        'change_group',
        'toggle_status',
        'clear_activity_logs',
        'ban',
        'unban',
        'add_restriction',
        'remove_restriction',
        'remove_all_restrictions',
        'add_admin_note',
        'appeal_update',
        'bulk_action',
        'bulk_appeal_update',
    ];
    $groupManagedActions = ['save_group', 'delete_group'];
    if (in_array($action, $userManagedActions, true) && !$canEditUsers) {
        $respond(false, 'Bu işlemi yapma yetkiniz yok.');
    }
    if (in_array($action, $groupManagedActions, true) && !$canEditGroups && !$canEditUsers) {
        $respond(false, 'Bu işlemi yapma yetkiniz yok.');
    }

    try {
        $error = '';
        switch ($action) {
            case 'save_group':
                if (!$canEditGroups && !$canEditUsers) {
                    $respond(false, 'Grup duzenleme yetkiniz yok.');
                }
                $result = usersSaveGroup($pdo, $_POST, $currentUserId);
                $targetGroupId = (int)($result['group_id'] ?? ($_POST['group_id'] ?? 0));
                if (!empty($result['ok']) && function_exists('adminLogAction')) {
                    adminAuditLogger()->logAction($pdo, 'group_save', 'user_group', $targetGroupId, 'Kullanici grubu kaydedildi', [], [
                        'group_id' => $targetGroupId,
                        'name' => (string)($_POST['name'] ?? ''),
                    ], false);
                }
                $respond(!empty($result['ok']), (string)($result['message'] ?? 'Grup kaydedilemedi.'), ['group_id' => $targetGroupId]);
                break;

            case 'delete_group':
                if (!userHasPermission($pdo, $currentUserId, 'groups.delete') && !$canEditUsers) {
                    $respond(false, 'Grup silme yetkiniz yok.');
                }
                $groupId = (int)($_POST['group_id'] ?? 0);
                $error = usersDeleteGroup($pdo, $groupId, $currentUserId);
                if ($error === '' && function_exists('adminLogAction')) {
                    adminAuditLogger()->logAction($pdo, 'group_deactivate', 'user_group', $groupId, 'Kullanici grubu pasife alindi', [], ['is_active' => 0], false);
                }
                $respond($error === '', $error === '' ? 'Grup pasife alindi.' : $error, ['group_id' => $groupId]);
                break;

            case 'save_user':
                $error = usersUpdateProfile($pdo, $userId, $_POST, $currentUserId, $validGroupIds);
                if ($error === '' && function_exists('userActivityLog')) {
                    userActivityLog($pdo, $userId, 'admin_user_updated', 'admin', 'user', $userId, 'Admin kullanici bilgilerini guncelledi', [
                        'fields' => array_values(array_intersect(array_keys($_POST), ['name', 'email', 'group_id', 'status', 'bio', 'website', 'location', 'social_github', 'social_twitter', 'social_discord'])),
                    ], $currentUserId);
                }
                $respond($error === '', $error === '' ? 'Kullanıcı bilgileri güncellendi.' : $error);
                break;

            case 'change_group':
                $reason = trim((string)($_POST['reason'] ?? ''));
                if ($reason === '') {
                    $respond(false, 'Grup degisimi icin gerekce zorunludur.');
                }
                $oldGroupIds = function_exists('usersUserGroupIds') ? usersUserGroupIds($pdo, $userId) : [];
                $newGroupId = (int)($_POST['new_group_id'] ?? 0);
                $error = usersChangeGroup($pdo, $userId, $newGroupId, $currentUserId, $validGroupIds);
                if ($error === '') {
                    adminAuditLogger()->logAction($pdo, 'group_change', 'user', $userId, $reason,
                        ['group_ids' => $oldGroupIds], ['group_id' => $newGroupId], true);
                    userActivityLog($pdo, $userId, 'user_group_changed', 'admin', 'user', $userId, 'Kullanici grubu guncellendi', [
                        'old_group_ids' => $oldGroupIds,
                        'new_group_id' => $newGroupId,
                        'reason' => $reason,
                    ], $currentUserId);
                    usersDispatchAccountNotification($pdo, 'user_group_changed', $userId, $currentUserId, 'Hesap grubunuz yonetim tarafindan guncellendi. Gerekce: ' . $reason, 'info');
                }
                $respond($error === '', $error === '' ? 'Kullanici grubu guncellendi.' : $error);
                break;

            case 'toggle_status':
                $reason = trim((string)($_POST['reason'] ?? ''));
                if ($reason === '') {
                    $respond(false, 'Durum değişimi için gerekçe zorunludur.');
                }
                $oldStatusStmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
                $oldStatusStmt->execute([$userId]);
                $oldStatus = (string)($oldStatusStmt->fetchColumn() ?: '');
                $newStatus = ((string)($_POST['new_status'] ?? '') === 'active') ? 'active' : 'inactive';
                ($newStatus === 'active') ? usersActivate($pdo, $userId) : usersDeactivate($pdo, $userId);
                adminAuditLogger()->logAction($pdo, 'status_change', 'user', $userId, $reason,
                    ['status' => $oldStatus], ['status' => $newStatus], true);
                userActivityLog($pdo, $userId, 'user_status_changed', 'admin', 'user', $userId, 'Kullanici durumu guncellendi', [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ], $currentUserId);
                $statusLabels = ['active' => 'Aktif', 'inactive' => 'Pasif'];
                $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;
                usersDispatchAccountNotification($pdo, 'user_status_changed', $userId, $currentUserId, 'Hesap durumunuz "' . $newStatusLabel . '" olarak guncellendi. Gerekce: ' . $reason, 'info');
                $respond(true, 'Kullanıcı durumu güncellendi.');
                break;

            case 'clear_activity_logs':
                $scope = (string)($_POST['scope'] ?? '');
                $targetUserId = (int)($_POST['target_user_id'] ?? 0);
                $where = [];
                $params = [];
                
                if ($scope === 'user' && $targetUserId > 0) {
                    $where[] = "user_id = ?";
                    $params[] = $targetUserId;
                } elseif ($scope === 'older_than_30_days') {
                    $where[] = "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                } elseif ($scope === 'all') {
                    // Tüm kayıtları temizle
                } else {
                    $respond(false, 'Geçersiz temizleme kapsamı.');
                    break;
                }
                
                if ($scope === 'all') {
                    $deletedCount = $pdo->query("SELECT COUNT(*) FROM user_activity_events")->fetchColumn();
                    $pdo->exec("TRUNCATE TABLE user_activity_events");
                } else {
                    $sql = "DELETE FROM user_activity_events" . (!empty($where) ? " WHERE " . implode(' AND ', $where) : "");
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $deletedCount = $stmt->rowCount();
                }
                
                if (function_exists('adminLogAction') && $scope !== 'all') {
                    $scopeMap = [
                        'all' => 'Tümü',
                        'older_than_30_days' => '30 Günden Eskiler',
                        'user' => 'Belirli Kullanıcı'
                    ];
                    $scopeName = $scopeMap[$scope] ?? $scope;
                    adminAuditLogger()->logAction($pdo, 'activity_logs_cleared', 'system', 0, "Kapsam: $scopeName, Silinen: $deletedCount", [], [], true);
                }
                $respond(true, "Tebrikler, $deletedCount adet kayıt hiçbir kalıntı bırakılmadan temizlendi ve sayaçlar sıfırlandı!");
                break;

            case 'ban':
                if ($userId === $currentUserId) {
                    $respond(false, 'Kendi hesabınızı banlayamazsınız.');
                }
                $reason = trim((string)($_POST['ban_reason'] ?? ($_POST['reason'] ?? '')));
                if ($reason === '') {
                    $respond(false, 'Yasaklama için gerekçe zorunludur.');
                }
                usersBan($pdo, $userId, $reason);
                adminAuditLogger()->logAction($pdo, 'ban', 'user', $userId, $reason,
                    ['is_banned' => 0], ['is_banned' => 1], true);
                userActivityLog($pdo, $userId, 'user_banned', 'moderation', 'user', $userId, 'Kullanici banlandi', [
                    'reason' => $reason,
                    'message' => trim((string)($_POST['ban_message'] ?? '')),
                ], $currentUserId);
                usersDispatchAccountNotification(
                    $pdo,
                    'user_banned',
                    $userId,
                    $currentUserId,
                    'Hesabiniz banlandi. Sebep: ' . $reason,
                    'error',
                    function_exists('routePublicStaticPath')
                        ? '/' . ltrim((string) routePublicStaticPath('ban_appeals'), '/')
                        : '/ban-appeals.php'
                );
                $respond(true, 'Kullanıcı banlandı.');
                break;

            case 'unban':
                $reason = trim((string)($_POST['reason'] ?? ''));
                $oldBanReasonStmt = $pdo->prepare("SELECT ban_reason FROM users WHERE id = ?");
                $oldBanReasonStmt->execute([$userId]);
                $oldBanReason = (string)($oldBanReasonStmt->fetchColumn() ?: '');
                usersUnban($pdo, $userId);
                adminAuditLogger()->logAction($pdo, 'unban', 'user', $userId, $reason,
                    ['is_banned' => 1, 'ban_reason' => $oldBanReason], ['is_banned' => 0], true);
                userActivityLog($pdo, $userId, 'user_unbanned', 'moderation', 'user', $userId, 'Kullanici bani kaldirildi', [
                    'reason' => $reason,
                    'old_ban_reason' => $oldBanReason,
                ], $currentUserId);
                usersDispatchAccountNotification($pdo, 'user_unbanned', $userId, $currentUserId, 'Hesabinizdaki ban kaldirildi.' . ($reason !== '' ? ' Gerekce: ' . $reason : ''), 'success');
                $respond(true, 'Kullanıcı banı kaldırıldı.');
                break;

            case 'add_restriction':
                $restrictReason = trim((string)($_POST['restrict_reason'] ?? ''));
                if ($restrictReason === '') {
                    $respond(false, 'Kısıtlama için gerekçe zorunludur.');
                }
                
                $restrictTypes = $_POST['restrict_types'] ?? [];
                if (empty($restrictTypes)) {
                    $singleType = (string)($_POST['restrict_type'] ?? '');
                    if ($singleType !== '') {
                        $restrictTypes = [$singleType];
                    } else {
                        $restrictTypes = ['all'];
                    }
                }
                
                $restrictDays = (int)($_POST['restrict_days'] ?? 0);
                $restrictMessage = trim((string)($_POST['restrict_message'] ?? ''));
                
                $pdo->beginTransaction();
                try {
                    foreach ($restrictTypes as $restrictType) {
                        $restrictType = (string)$restrictType;
                        usersAddRestriction($pdo, $userId, $restrictType, $restrictReason, $restrictDays, $currentUserId);
                        adminAuditLogger()->logAction($pdo, 'restrict', 'user', $userId, $restrictReason,
                            [], ['type' => $restrictType, 'days' => $restrictDays], false);
                        userActivityLog($pdo, $userId, 'user_restricted', 'moderation', 'restriction', $userId, 'Kisitlama eklendi', [
                            'restriction_type' => $restrictType,
                            'days' => $restrictDays,
                            'reason' => $restrictReason,
                            'message' => $restrictMessage,
                        ], $currentUserId);
                        usersDispatchAccountNotification($pdo, 'user_restricted', $userId, $currentUserId, usersGetRestrictionTypeLabel($restrictType) . ' kisitlamasi eklendi. Sebep: ' . $restrictReason, 'warning');
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                
                $respond(true, count($restrictTypes) . ' adet kısıtlama başarıyla eklendi.');
                break;

            case 'remove_restriction':
                $restrictionId = (int)($_POST['restriction_id'] ?? 0);
                $restrictionStmt = $pdo->prepare("SELECT user_id, restriction_type, reason FROM user_restrictions WHERE id = ? LIMIT 1");
                $restrictionStmt->execute([$restrictionId]);
                $restrictionBefore = $restrictionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                usersRemoveRestriction($pdo, $restrictionId);
                if ($restrictionBefore) {
                    $targetUserId = (int) $restrictionBefore['user_id'];
                    userActivityLog($pdo, $targetUserId, 'user_restriction_removed', 'moderation', 'restriction', $restrictionId, 'Kisitlama kaldirildi', [
                        'restriction_type' => (string) $restrictionBefore['restriction_type'],
                        'reason' => (string) ($restrictionBefore['reason'] ?? ''),
                    ], $currentUserId);
                    usersDispatchAccountNotification($pdo, 'user_restriction_removed', $targetUserId, $currentUserId, usersGetRestrictionTypeLabel((string) $restrictionBefore['restriction_type']) . ' kisitlamasi kaldirildi.', 'success');
                }
                $respond(true, 'Kısıtlama kaldırıldı.');
                break;

            case 'remove_all_restrictions':
                userActivityLog($pdo, $userId, 'user_restrictions_cleared', 'moderation', 'user', $userId, 'Tum kisitlamalar kaldirildi', [], $currentUserId);
                usersRemoveAllRestrictions($pdo, $userId);
                usersDispatchAccountNotification($pdo, 'user_restriction_removed', $userId, $currentUserId, 'Hesabinizdaki tum kisitlamalar kaldirildi.', 'success');
                $respond(true, 'Tüm kısıtlamalar kaldırıldı.');
                break;

            case 'add_admin_note':
                $error = usersAddAdminNote(
                    $pdo,
                    $userId,
                    $currentUserId,
                    (string)($_POST['admin_note'] ?? ''),
                    (string)($_POST['note_tone'] ?? 'info'),
                    (string)($_POST['note_tags'] ?? '')
                );
                $respond($error === '', $error === '' ? 'Admin notu eklendi.' : $error);
                break;

            case 'appeal_update':
                $error = usersUpdateBanAppeal(
                    $pdo,
                    (int)($_POST['appeal_id'] ?? 0),
                    (string)($_POST['appeal_status'] ?? ''),
                    (string)($_POST['admin_note'] ?? ''),
                    $currentUserId
                );
                $respond($error === '', $error === '' ? 'Ban itirazı güncellendi.' : $error);
                break;

            case 'bulk_action':
                $error = usersApplyBulkAction($pdo, (string)($_POST['bulk_action'] ?? ''), (array)($_POST['user_ids'] ?? []), $currentUserId, $_POST, $validGroupIds);
                $respond($error === '', $error === '' ? 'Toplu işlem uygulandı.' : $error);
                break;

            case 'bulk_appeal_update':
                $appealIds = array_values(array_filter(array_map('intval', (array)($_POST['bulk_appeal_ids'] ?? [])), static fn (int $id): bool => $id > 0));
                $appealStatus = (string)($_POST['bulk_appeal_status'] ?? '');
                $appealNote = (string)($_POST['bulk_admin_note'] ?? '');
                if (!in_array($appealStatus, ['reviewing', 'accepted', 'rejected'], true)) {
                    $respond(false, 'Geçersiz itiraz durumu.');
                }
                $appealCount = 0;
                foreach ($appealIds as $aid) {
                    if (usersUpdateBanAppeal($pdo, $aid, $appealStatus, $appealNote, $currentUserId) === '') {
                        $appealCount++;
                    }
                }
                $respond($appealCount > 0, $appealCount > 0 ? "{$appealCount} itiraz güncellendi." : 'Toplu işlem uygulanamadı.');
                break;

            default:
                $respond(false, 'Geçersiz işlem.');
        }
    } catch (Throwable $e) {
        appLogException($e, ['source' => 'admin/users.php', 'action' => $action]);
        $respond(false, safeErrorMessage($e, 'İşlem tamamlanamadı.'));
    }
}

$search = sanitizeSearchQuery($_GET['q'] ?? ($_GET['search'] ?? ''));
$filterGroup = trim((string)($_GET['group'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? ''));
$usersPage = max(1, (int)($_GET['page'] ?? 1));
$usersPerPage = 25;
$usersOffset = ($usersPage - 1) * $usersPerPage;
$usersTotal = 0;
$usersTotalPages = 1;
$users = [];
$editUser = null;
$viewRestrictionsUserId = (int)($_GET['view_restrictions'] ?? 0);
$userRestrictions = [];
$bannedUsers = [];
$restrictedUsers = [];
$restrictedUserRestrictionsMap = [];
$appealFilter = '';
$appealStats = ['open' => 0, 'reviewing' => 0, 'accepted' => 0, 'rejected' => 0];
$banAppeals = [];

$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editUser = usersGetById($pdo, $editId);
}

if ($viewRestrictionsUserId > 0) {
    $userRestrictions = usersGetRestrictions($pdo, $viewRestrictionsUserId);
}

if ($activeTab === 'users') {
    $usersTotal = usersCountList($pdo, $search, $filterGroup, $filterStatus);
    $usersTotalPages = max(1, (int)ceil($usersTotal / $usersPerPage));
    $users = usersGetList($pdo, $search, $filterGroup, $filterStatus, $usersPerPage, $usersOffset);
} elseif ($activeTab === 'banned') {
    $bannedUsers = usersGetBannedList($pdo, $search);
} elseif ($activeTab === 'restricted') {
    $restrictedUsers = usersGetRestrictedList($pdo, $search);
    if (!empty($restrictedUsers)) {
        $restrictedUserRestrictionsMap = usersGetRestrictionsForUsers($pdo, array_column($restrictedUsers, 'id'));
    }
} elseif ($activeTab === 'appeals') {
    $appealFilter = trim((string)($_GET['appeal_status'] ?? ''));
    $appealStats = usersGetBanAppealStats($pdo);
    $banAppeals = usersGetBanAppealsForAdmin($pdo, $appealFilter);
}

$stats = usersGetStats($pdo);

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

require_once __DIR__ . '/header.php';
?>
<div class="users-page">
    <?php if ($successMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($successMsg) ?><button type="button" class="ui-admin-alert-close">&times;</button></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="ui-admin-alert ui-admin-alert-error ui-alert ui-alert--error"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($errorMsg) ?><button type="button" class="ui-admin-alert-close">&times;</button></div>
    <?php endif; ?>

    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <h2><i class="bi bi-people"></i> <?= htmlspecialchars($pageTitle) ?></h2>
            <p>Kullanıcıları, grupları, banları, kısıtlamaları ve itirazları tek çalışma alanında yönetin.</p>
        </div>
    </section>

    <div class="users-summary">
        <div class="users-stat"><span>Toplam</span><strong><?= number_format((int)$stats['total']) ?></strong></div>
        <div class="users-stat"><span>Aktif</span><strong><?= number_format((int)$stats['active']) ?></strong></div>
        <div class="users-stat"><span>Banlı</span><strong><?= number_format((int)$stats['banned']) ?></strong></div>
        <div class="users-stat"><span>Kısıtlı</span><strong><?= number_format((int)$stats['restricted']) ?></strong></div>
        <div class="users-stat"><span>Admin</span><strong><?= number_format((int)$stats['admins']) ?></strong></div>
    </div>

    <nav class="users-tabs" aria-label="Kullanıcı yönetimi sekmeleri">
        <?php
        $tabLinks = [
            'users' => ['Tüm Kullanıcılar', 'bi-people'],
            'groups' => ['Gruplar', 'bi-diagram-3'],
            'banned' => ['Banlılar', 'bi-slash-circle'],
            'restricted' => ['Kısıtlılar', 'bi-shield-exclamation'],
            'appeals' => ['Ban İtirazları', 'bi-envelope-exclamation'],
            'activity' => ['Kullanıcı İzleme', 'bi-activity'],
        ];
        foreach ($tabLinks as $tabKey => [$label, $icon]):
        ?>
            <a href="users.php?tab=<?= htmlspecialchars($tabKey) ?>" class="ui-admin-btn <?= $activeTab === $tabKey ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>" <?= $activeTab === $tabKey ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php
    if ($activeTab === 'groups') {
        require dirname(__DIR__) . '/' . $tabPartials['groups'];
    } elseif ($activeTab === 'banned') {
        require dirname(__DIR__) . '/' . $tabPartials['banned'];
    } elseif ($activeTab === 'restricted') {
        require dirname(__DIR__) . '/' . $tabPartials['restricted'];
    } elseif ($activeTab === 'appeals') {
        require dirname(__DIR__) . '/' . $tabPartials['appeals'];
    } elseif ($activeTab === 'activity') {
        require dirname(__DIR__) . '/' . $tabPartials['activity'];
    } else {
        require dirname(__DIR__) . '/' . $tabPartials['users'];
    }
    ?>

    <?php $editGroups = $groups ?? []; ?>
    <div class="media-modal-overlay user-edit-modal<?= $editUser ? ' ui-admin-modal-open is-open' : '' ?>" id="userEditModal" role="dialog" aria-modal="true" aria-label="Kullanıcı düzenle" <?= $editUser ? 'aria-hidden="false"' : 'hidden aria-hidden="true"' ?>>
        <div class="media-modal ui-panel">
            <div class="media-modal-header ui-panel__head">
                <div>
                    <h3 class="ui-admin-modal-title"><i class="bi bi-pencil-square"></i> Kullanıcıyı Düzenle</h3>
                    <p class="user-edit-help" id="userEditEmailPreview"><?= htmlspecialchars((string) ($editUser['email'] ?? '')) ?></p>
                </div>
                <a href="users.php?tab=<?= htmlspecialchars($activeTab) ?>" class="user-edit-close" data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg"></i></a>
            </div>
            <form method="post" action="users.php?tab=<?= htmlspecialchars($activeTab) ?>">
                <div class="media-modal-body ui-panel__body">
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="user_id" id="editUserId" value="<?= (int) ($editUser['id'] ?? 0) ?>">

                    <p class="user-edit-section-title">Hesap Bilgileri</p>
                    <div class="user-edit-grid ui-admin-mb-md ui-grid">
                        <div>
                            <label class="ui-admin-form-label">Ad Soyad</label>
                            <input type="text" name="name" id="editUserName" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['name'] ?? '')) ?>" required>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">E-posta</label>
                            <input type="email" name="email" id="editUserEmail" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['email'] ?? '')) ?>" required>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Grup</label>
                            <select name="group_id" id="editUserGroup" class="ui-admin-form-select" required>
                                <?php foreach ($editGroups as $group): ?>
                                    <option value="<?= (int) $group['id'] ?>" <?= (int) ($editUser['group_id'] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $group['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Durum</label>
                            <select name="status" id="editUserStatus" class="ui-admin-form-select">
                                <option value="active" <?= ($editUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                                <option value="inactive" <?= ($editUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                            </select>
                        </div>
                    </div>

                    <p class="user-edit-section-title">Profil Detayları</p>
                    <div class="user-edit-grid ui-admin-mb-md ui-grid">
                        <div>
                            <label class="ui-admin-form-label">Konum</label>
                            <input type="text" name="location" id="editUserLocation" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['location'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Web sitesi</label>
                            <input type="url" name="website" id="editUserWebsite" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['website'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">GitHub</label>
                            <input type="text" name="social_github" id="editUserGithub" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['social_github'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Twitter</label>
                            <input type="text" name="social_twitter" id="editUserTwitter" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['social_twitter'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Discord</label>
                            <input type="text" name="social_discord" id="editUserDiscord" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($editUser['social_discord'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Yeni şifre</label>
                            <div class="user-password-field">
                                <input type="password" name="password" id="editUserPassword" class="ui-admin-form-control" autocomplete="new-password" minlength="6">
                                <button type="button" class="user-password-toggle" id="editPasswordToggle" data-edit-password-toggle aria-label="Şifreyi göster" aria-pressed="false">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <p class="user-edit-help">Boş bırakırsanız şifre değişmez. En az 6 karakter girin.</p>
                        </div>
                    </div>

                    <div>
                        <label class="ui-admin-form-label">Biyografi</label>
                        <textarea name="bio" id="editUserBio" class="ui-admin-form-control" rows="4"><?= htmlspecialchars((string) ($editUser['bio'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="media-modal-footer user-edit-footer ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-user-edit-close>
                        <i class="bi bi-x-circle"></i> İptal
                    </button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary user-edit-save">
                        <i class="bi bi-check2-circle"></i> Değişiklikleri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ban Modal -->
    <div class="media-modal-overlay" id="banModal" role="dialog" aria-modal="true" aria-label="Kullanıcı banla" hidden aria-hidden="true">
        <div class="media-modal ui-admin-modal-sm ui-panel">
            <div class="media-modal-header ui-panel__head">
                <h3 class="ui-admin-modal-title"><i class="bi bi-slash-circle"></i> Kullanıcıyı Banla</h3>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-ban-close>&times;</button>
            </div>
            <div class="media-modal-body ui-panel__body">
                <form id="banForm" data-ban-form>
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="ban">
                    <input type="hidden" name="user_id" id="banUserId">
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kullanıcı</label>
                        <input type="text" id="banUserName" class="ui-admin-form-control" readonly>
                    </div>
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Sebep</label>
                        <textarea name="ban_reason" id="banReason" class="ui-admin-form-control" rows="3" required placeholder="Ban gerekçesi..."></textarea>
                    </div>
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kullanıcıya Görünecek Mesaj</label>
                        <textarea name="ban_message" class="ui-admin-form-control" rows="2" placeholder="Boş kalırsa sebep metni gösterilir."></textarea>
                    </div>
                    <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-ban-close>İptal</button>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-slash-circle"></i> Banla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Note Modal -->
    <div class="media-modal-overlay" id="adminNoteModal" role="dialog" aria-modal="true" aria-label="Admin notu" hidden aria-hidden="true">
        <div class="media-modal ui-admin-modal-sm ui-panel">
            <div class="media-modal-header ui-panel__head">
                <h3 class="ui-admin-modal-title"><i class="bi bi-journal-plus"></i> Admin Notu</h3>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-admin-note-close>&times;</button>
            </div>
            <div class="media-modal-body ui-panel__body">
                <form id="adminNoteForm" method="post" action="users.php?tab=<?= htmlspecialchars($activeTab) ?>">
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_admin_note">
                    <input type="hidden" name="user_id" id="adminNoteUserId">
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kullanıcı</label>
                        <input type="text" id="adminNoteUserName" class="ui-admin-form-control" readonly>
                    </div>
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Önem</label>
                        <select name="note_tone" class="ui-admin-form-select">
                            <option value="info">Bilgi</option>
                            <option value="warning">Uyarı</option>
                            <option value="danger">Kritik</option>
                            <option value="success">Olumlu</option>
                        </select>
                    </div>
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Etiketler</label>
                        <input type="text" name="note_tags" class="ui-admin-form-control" placeholder="örn: güvenlik, şikayet, takip">
                    </div>
                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Not</label>
                        <textarea name="admin_note" class="ui-admin-form-control" rows="4" required placeholder="Sadece adminler görür..."></textarea>
                    </div>
                    <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-admin-note-close>İptal</button>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Restriction Modal -->
    <div class="media-modal-overlay" id="restrictionModal" role="dialog" aria-modal="true" aria-label="Kısıtlama ekle" hidden aria-hidden="true">
        <div class="media-modal ui-admin-modal-sm ui-panel">
            <div class="media-modal-header ui-panel__head">
                <h3 class="ui-admin-modal-title"><i class="bi bi-shield-exclamation"></i> Kısıtlama Ekle</h3>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-restriction-close>&times;</button>
            </div>
            <div class="media-modal-body ui-panel__body">
                <form id="restrictionForm" data-restriction-form>
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="user_id" id="restrictUserId">

                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kullanıcı</label>
                        <input type="text" id="restrictUserName" class="ui-admin-form-control" readonly>
                    </div>

                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kısıtlama Türleri <small class="ui-admin-muted-xs">(Birden fazla seçebilirsiniz)</small></label>
                        <select name="restrict_types[]" id="restrictTypes" class="ui-admin-form-select ui-admin-select-auto" multiple size="7" required>
                            <option value="profile">Profil Düzenleme</option>
                            <option value="events">Etkinlik Kullanımı</option>
                            <option value="all">🚫 Tüm İşlemler (En ciddi kısıtlama)</option>
                            <option value="comment">💬 Yorum Yapma</option>
                            <option value="topic">📝 Konu Oluşturma</option>
                            <option value="upload">📤 Dosya Yükleme</option>
                            <option value="download">📥 İndirme</option>
                        </select>
                        <small class="ui-admin-help-block">
                            <i class="bi bi-info-circle"></i> Ctrl/Cmd tuşu ile birden fazla seçim yapabilirsiniz
                        </small>
                    </div>

                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Süre (Gün)</label>
                        <input type="number" name="restrict_days" class="ui-admin-form-control" min="0" placeholder="0 = Süresiz">
                        <small class="ui-admin-muted-xs">0 veya boş bırakırsanız süresiz olur</small>
                    </div>

                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Sebep</label>
                        <textarea name="restrict_reason" class="ui-admin-form-control" rows="3" placeholder="Kısıtlama sebebi..."></textarea>
                    </div>

                    <div class="ui-admin-mb-md">
                        <label class="ui-admin-form-label">Kullanıcıya Görünecek Mesaj</label>
                        <textarea name="restrict_message" class="ui-admin-form-control" rows="2" placeholder="Boş kalırsa sebep metni gösterilir."></textarea>
                    </div>

                    <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-restriction-close>İptal</button>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-warning">
                            <i class="bi bi-shield-exclamation"></i> Kısıtla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Restrictions Modal -->
    <?php if ($viewRestrictionsUserId > 0 && !empty($userRestrictions)):
        $restrictedUser = usersGetById($pdo, $viewRestrictionsUserId);
    ?>
    <div class="media-modal-overlay ui-admin-modal-open is-open" id="viewRestrictionsModal" role="dialog" aria-modal="true" aria-label="Kullanıcı kısıtlamaları" aria-hidden="false">
        <div class="media-modal ui-admin-modal-md ui-panel">
            <div class="media-modal-header ui-panel__head">
                <h3 class="ui-admin-modal-title">
                    <i class="bi bi-shield-exclamation"></i>
                    <?= htmlspecialchars((string) ($restrictedUser['name'] ?? 'Kullanıcı')) ?> - Kısıtlamalar
                </h3>
                <a href="users.php?tab=<?= htmlspecialchars($activeTab) ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost">&times;</a>
            </div>
            <div class="media-modal-body ui-panel__body">
                <div class="ui-admin-section-head-inline ui-panel__head">
                    <p class="ui-admin-m-0 ui-admin-muted-sm">
                        Toplam <?= count($userRestrictions) ?> kısıtlama
                    </p>
                    <form method="post" class="ui-admin-inline-form" data-admin-confirm="Tüm kısıtlamaları kaldırmak istediğinizden emin misiniz?" data-admin-confirm-title="Tüm kısıtlamalar kaldırılsın mı?" data-admin-confirm-ok="Kaldır" data-admin-confirm-tone="danger">
                        <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="remove_all_restrictions">
                        <input type="hidden" name="user_id" value="<?= $viewRestrictionsUserId ?>">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger">
                            <i class="bi bi-trash"></i> Tümünü Kaldır
                        </button>
                    </form>
                </div>

                <?php foreach ($userRestrictions as $restriction): ?>
                    <div class="restriction-card">
                        <div class="restriction-header">
                            <div>
                                <span class="restriction-type restriction-type-<?= htmlspecialchars($restriction['restriction_type']) ?>">
                                    <i class="bi bi-shield-exclamation"></i>
                                    <?= htmlspecialchars(usersGetRestrictionTypeLabel($restriction['restriction_type'])) ?>
                                </span>
                            </div>
                            <form method="post" class="ui-admin-inline-form" data-admin-confirm="Bu kısıtlamayı kaldırmak istediğinizden emin misiniz?" data-admin-confirm-title="Kısıtlama kaldırılsın mı?" data-admin-confirm-ok="Kaldır" data-admin-confirm-tone="danger">
                                <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="remove_restriction">
                                <input type="hidden" name="restriction_id" value="<?= (int) $restriction['id'] ?>">
                                <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger">
                                    <i class="bi bi-x-circle"></i> Kaldır
                                </button>
                            </form>
                        </div>
                        <div class="restriction-info">
                            <div class="restriction-info-row">
                                <i class="bi bi-calendar"></i>
                                <span>Başlangıç: <?= date('d.m.Y H:i', strtotime($restriction['created_at'])) ?></span>
                            </div>
                            <?php if ($restriction['expires_at']): ?>
                                <div class="restriction-info-row">
                                    <i class="bi bi-clock"></i>
                                    <span>Bitiş: <?= date('d.m.Y H:i', strtotime($restriction['expires_at'])) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="restriction-info-row">
                                    <i class="bi bi-infinity"></i>
                                    <span>Süresiz</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($restriction['admin_name']): ?>
                                <div class="restriction-info-row">
                                    <i class="bi bi-person-badge"></i>
                                    <span>Ekleyen: <?= htmlspecialchars($restriction['admin_name']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($restriction['reason']): ?>
                            <div class="restriction-reason">
                                <strong class="ui-admin-label-muted">Sebep:</strong>
                                <?= nl2br(htmlspecialchars($restriction['reason'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= asset_url('admin/assets/users-tab.js', $baseUri) ?>" defer></script>

    <div id="userDetailModal" class="ui-admin-detail-overlay" role="dialog" aria-modal="true" aria-label="Kullanıcı detayı" hidden aria-hidden="true" data-user-detail-backdrop>
        <div class="ui-admin-detail-modal">
            <div class="ui-admin-detail-modal-head ui-panel__head">
                <h3><i class="bi bi-person-vcard"></i> Kullanıcı Detayı</h3>
                <button type="button" class="ui-admin-detail-close" data-ui-modal-close data-user-detail-close><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="userDetailBody" class="ui-admin-detail-modal-body ui-panel__body"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

