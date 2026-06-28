<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/init.php";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
    sendCsrfError();
}

if (!isset($_POST['action'], $_POST['user_id'], $_POST['new_group_id'])) {
    sendValidationError('Gerekli alanlar eksik.');
}

$currentUserId = (int)($_SESSION["_auth_user_id"] ?? 0);
if ($currentUserId <= 0 || !userHasPermission($pdo, $currentUserId, "users.edit")) {
    sendForbidden('Bu islemi yapma yetkiniz yok.');
}

$userId = (int)$_POST['user_id'];
$newGroupId = (int)$_POST['new_group_id'];
$reason = sanitizeInput($_POST['reason'] ?? '');

if (trim($reason) === '') {
    sendValidationError('Grup degisimi icin gerekce zorunludur.');
}

$userStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    sendNotFound('Kullanici bulunamadi.');
}

$group = function_exists('usersGetGroupById') ? usersGetGroupById($pdo, $newGroupId) : null;
if (!$group) {
    sendValidationError('Gecersiz grup secimi.');
}

$oldGroupIds = function_exists('usersUserGroupIds') ? usersUserGroupIds($pdo, $userId) : [];
$validGroupIds = array_map('intval', array_column(function_exists('usersGetGroups') ? usersGetGroups($pdo, false) : [], 'id'));
$error = function_exists('usersChangeGroup')
    ? usersChangeGroup($pdo, $userId, $newGroupId, $currentUserId, $validGroupIds)
    : 'Grup sistemi hazir degil.';

if ($error !== '') {
    sendError('group_update_failed', $error, 500);
}

adminAuditLogger()->logAction($pdo, 'group_change', 'user', $userId, $reason, ['group_ids' => $oldGroupIds], ['group_id' => $newGroupId], true);

logActivity($pdo, "user_group_changed", "user", $userId, [
    "old_group_ids" => $oldGroupIds,
    "new_group_id" => $newGroupId,
    "new_group" => $group['name'],
    "changed_by" => $currentUserId,
    "reason" => $reason,
]);

sendSuccess('Kullanici grubu basariyla guncellendi.', [
    'data' => [
        'user_id' => $userId,
        'user_name' => $user['name'],
        'old_group_ids' => $oldGroupIds,
        'new_group_id' => $newGroupId,
        'new_group' => $group['name'],
        'changed_by' => $currentUserId,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s'),
    ],
]);
