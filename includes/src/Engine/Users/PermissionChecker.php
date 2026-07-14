<?php

declare(strict_types=1);

namespace App\Engine\Users;

use PDO;

final class PermissionChecker
{
    public static function can(string $permission, ?PDO $pdo = null, ?int $userId = null): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $pdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
        $userId = $userId ?? (int) ($_SESSION['_auth_user_id'] ?? 0);
        if (!$pdo instanceof PDO || $userId <= 0 || !function_exists('userHasPermission')) {
            return false;
        }

        return userHasPermission($pdo, $userId, $permission);
    }
}
