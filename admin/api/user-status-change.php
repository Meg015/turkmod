<?php

declare(strict_types=1);

require_once __DIR__ . "/../../includes/init.php";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
    sendCsrfError();
}

if (!isset($_POST['action'], $_POST['user_id'], $_POST['new_status'])) {
    sendValidationError('Gerekli alanlar eksik.');
}

$currentUserId = (int)($_SESSION["_auth_user_id"] ?? 0);
if ($currentUserId <= 0 || !userHasPermission($pdo, $currentUserId, "users.edit")) {
    sendForbidden('Bu işlemi yapma yetkiniz yok.');
}

$userId = (int)$_POST['user_id'];
$newStatus = in_array($_POST['new_status'], ['active', 'inactive'], true) ? (string)$_POST['new_status'] : '';
$reason = sanitizeInput($_POST['reason'] ?? '');
if ($newStatus === '') {
    sendValidationError('Geçersiz durum seçimi.');
}
if (trim($reason) === '') {
    sendValidationError('Durum değişimi için gerekçe zorunludur.');
}

$hasUsernameColumn = function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username');
$userIdentitySelect = $hasUsernameColumn
    ? "username AS username"
    : "name AS username";
$userStmt = $pdo->prepare("SELECT id, {$userIdentitySelect}, email, status FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    sendNotFound('Kullanıcı bulunamadı.');
}

$stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$newStatus, $userId]);
if ($stmt->rowCount() <= 0) {
    sendError('status_update_failed', 'Durum güncelleme sırasında bir hata oluştu.', 500);
}

adminAuditLogger()->logAction($pdo, 'status_change', 'user', $userId, $reason,
    ['status' => $user['status']], ['status' => $newStatus], true);

sendSuccess('Kullanıcı durumu başarıyla güncellendi.', [
    'data' => [
        'user_id' => $userId,
        'user_name' => $user['username'],
        'old_status' => $user['status'],
        'new_status' => $newStatus,
        'changed_by' => $currentUserId,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s'),
    ],
]);
