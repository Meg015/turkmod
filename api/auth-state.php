<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

if (function_exists('sendNoStoreHeaders')) {
    sendNoStoreHeaders();
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    sendMethodNotAllowed(['GET', 'HEAD']);
}

$loginUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('login')
    : '/giris';
$logoutUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('logout')
    : '/cikis';
$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

if ($userId <= 0) {
    sendSuccess('OK', [
        'authenticated' => false,
        'logged_in' => false,
        'user' => null,
        'is_admin' => false,
        'login_url' => $loginUrl,
        'logout_url' => $logoutUrl,
    ]);
}

$pdo = requireDatabaseConnection($pdo ?? null);

try {
    if (!function_exists('refreshAuthenticatedSession') || !refreshAuthenticatedSession($pdo)) {
        if (function_exists('logoutUser')) {
            logoutUser($pdo, false);
        } else {
            $_SESSION = [];
        }

        sendSuccess('OK', [
            'authenticated' => false,
            'logged_in' => false,
            'user' => null,
            'is_admin' => false,
            'login_url' => $loginUrl,
            'logout_url' => $logoutUrl,
            'reason' => 'session_invalid',
        ]);
    }

    $userId = (int) ($_SESSION['_auth_user_id'] ?? 0);
    $userName = trim((string) ($_SESSION['_auth_user_name'] ?? ''));
    $userEmail = (string) ($_SESSION['_auth_user_email'] ?? '');
    $roleId = (int) ($_SESSION['_auth_role_id'] ?? 0);
    $roleSlug = (string) ($_SESSION['_auth_role_slug'] ?? '');
    $isAdmin = $userId > 0
        && function_exists('userHasPermission')
        && userHasPermission($pdo, $userId, 'admin.access');

    sendSuccess('OK', [
        'authenticated' => $userId > 0,
        'logged_in' => $userId > 0,
        'user' => $userId > 0 ? [
            'id' => $userId,
            'username' => $userName,
            'name' => $userName,
            'email' => $userEmail,
            'role_id' => $roleId,
            'role_slug' => $roleSlug,
        ] : null,
        'is_admin' => $isAdmin,
        'login_url' => $loginUrl,
        'logout_url' => $logoutUrl,
    ]);
} catch (Throwable $exception) {
    sendServerError('Oturum durumu kontrol edilemedi.', $exception);
}
