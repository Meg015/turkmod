<?php

declare(strict_types=1);

namespace App\Engine\Users;

use PDO;

final class BanCheck
{
    public static function accessRestriction(PDO $pdo, int $userId): ?array
    {
        if (function_exists('usersPruneExpiredRestrictions')) {
            usersPruneExpiredRestrictions($pdo);
        }

        $nowSql = self::nowSql($pdo);

        try {
            $stmt = $pdo->prepare("SELECT restriction_type, reason, expires_at FROM user_restrictions WHERE user_id = :user_id AND restriction_type = 'all' AND (expires_at IS NULL OR expires_at > {$nowSql}) LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $restriction = $stmt->fetch();
            if ($restriction) {
                return [
                    'type' => $restriction['restriction_type'],
                    'title' => 'Hesabiniz kisitlandi',
                    'message' => 'Hesabiniz tum islemlerden kisitlanmistir. ' . ($restriction['reason'] ? 'Sebep: ' . $restriction['reason'] : ''),
                    'date' => $restriction['expires_at'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            // Older installs/tests may not have the granular restrictions table.
        }

        try {
            $stmt = $pdo->prepare("SELECT status, is_banned, banned_at, ban_reason FROM users WHERE id = :user_id LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            if ($user && ((int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned')) {
                return [
                    'type' => 'banned',
                    'title' => 'Hesabiniz banlandi',
                    'message' => trim((string) ($user['ban_reason'] ?? '')) !== '' ? 'Sebep: ' . (string) $user['ban_reason'] : 'Hesabiniz banli durumda.',
                    'date' => $user['banned_at'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    public static function hasRestriction(PDO $pdo, int $userId, string $restrictionType): bool
    {
        if (function_exists('usersPruneExpiredRestrictions')) {
            usersPruneExpiredRestrictions($pdo);
        }

        $nowSql = self::nowSql($pdo);

        $stmt = $pdo->prepare("SELECT 1 FROM user_restrictions WHERE user_id = :user_id AND restriction_type = :type AND (expires_at IS NULL OR expires_at > {$nowSql}) LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'type' => $restrictionType]);
        return (bool) $stmt->fetch();
    }

    public static function restrictedPathAllowed(string $path): bool
    {
        if (preg_match('~/assets/~', $path) === 1) {
            return true;
        }

        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $banAppealsPath = \function_exists('routePublicStaticPath')
            ? '/' . ltrim((string) \routePublicStaticPath('ban_appeals'), '/')
            : '/ban-appeals.php';
        $logoutPath = \function_exists('routePublicStaticPath')
            ? '/' . ltrim((string) \routePublicStaticPath('logout'), '/')
            : '/logout.php';
        $allowedPaths = array_values(array_unique(array_filter([
            $banAppealsPath,
            $logoutPath,
            '/ban-appeals.php',
            '/logout.php',
            '/ban-itiraz',
            '/cikis',
            ($baseUri !== '' ? $baseUri : '') . $banAppealsPath,
            ($baseUri !== '' ? $baseUri : '') . $logoutPath,
            ($baseUri !== '' ? $baseUri : '') . '/ban-appeals.php',
            ($baseUri !== '' ? $baseUri : '') . '/logout.php',
        ])));

        return in_array($path, $allowedPaths, true);
    }

    private static function nowSql(PDO $pdo): string
    {
        return stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false
            ? "datetime('now')"
            : 'NOW()';
    }
}
