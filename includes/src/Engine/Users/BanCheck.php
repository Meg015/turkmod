<?php

declare(strict_types=1);

namespace App\Engine\Users;

use PDO;
use Throwable;

final class BanCheck
{
    public static function accessRestriction(PDO $pdo, int $userId): ?array
    {
        if (function_exists('usersPruneExpiredRestrictions')) {
            usersPruneExpiredRestrictions($pdo);
        }

        $nowSql = self::nowSql($pdo);

        $stmt = $pdo->prepare("SELECT restriction_type, reason, expires_at, created_at FROM user_restrictions WHERE user_id = :user_id AND restriction_type = 'all' AND (expires_at IS NULL OR expires_at > {$nowSql}) LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $restriction = $stmt->fetch();
        if ($restriction) {
            $reason = trim((string) ($restriction['reason'] ?? ''));
            $expiresAt = $restriction['expires_at'] ?? null;
            $restrictionType = (string) ($restriction['restriction_type'] ?? 'all');
            return [
                'type' => $restrictionType,
                'restriction_type' => $restrictionType,
                'restriction_label' => self::restrictionTypeLabel($restrictionType),
                'status_label' => 'Kisitli',
                'title' => 'Hesabiniz kisitlandi',
                'message' => 'Hesabiniz tum islemlerden kisitlanmistir.' . ($reason !== '' ? ' Sebep: ' . $reason : ''),
                'reason' => $reason,
                'started_at' => $restriction['created_at'] ?? null,
                'ends_at' => $expiresAt,
                'date' => $expiresAt,
                'is_permanent' => empty($expiresAt),
            ];
        }

        try {
            $stmt = $pdo->prepare("SELECT status, is_banned, banned_at, ban_reason FROM users WHERE id = :user_id LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            if ($user && ((int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned')) {
                $reason = trim((string) ($user['ban_reason'] ?? ''));
                return [
                    'type' => 'banned',
                    'restriction_type' => 'banned',
                    'restriction_label' => 'Ban',
                    'status_label' => 'Banli',
                    'title' => 'Hesabiniz banlandi',
                    'message' => $reason !== '' ? 'Sebep: ' . $reason : 'Hesabiniz banli durumda.',
                    'reason' => $reason,
                    'started_at' => $user['banned_at'] ?? null,
                    'ends_at' => null,
                    'date' => $user['banned_at'] ?? null,
                    'is_permanent' => true,
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
        $path = self::normalizeRequestPath($path);
        if ($path === '') {
            return false;
        }

        if (preg_match('~/assets/~', $path) === 1) {
            return true;
        }

        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $banAppealsPath = '/' . ltrim((string) \routePublicStaticPath('ban_appeals'), '/');
        $logoutPath = '/' . ltrim((string) \routePublicStaticPath('logout'), '/');
        $allowedPaths = [
            $banAppealsPath,
            $logoutPath,
            ($baseUri !== '' ? $baseUri : '') . $banAppealsPath,
            ($baseUri !== '' ? $baseUri : '') . $logoutPath,
        ];
        $allowedPaths = array_values(array_unique(array_filter(array_map(
            [self::class, 'normalizeRequestPath'],
            $allowedPaths
        ))));

        return in_array($path, $allowedPaths, true);
    }

    private static function normalizeRequestPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        }

        $path = str_replace('\\', '/', rawurldecode($path));
        $path = '/' . trim($path, '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    private static function nowSql(PDO $pdo): string
    {
        return stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false
            ? "datetime('now')"
            : 'NOW()';
    }

    private static function restrictionTypeLabel(string $type): string
    {
        if (function_exists('usersGetRestrictionTypeLabel')) {
            return (string) usersGetRestrictionTypeLabel($type);
        }

        return match ($type) {
            'all' => 'Tum Islemler',
            'comment' => 'Yorum Yapma',
            'topic' => 'Konu Olusturma',
            'upload' => 'Dosya Yukleme',
            'download' => 'Indirme',
            'message' => 'Mesaj Gonderme',
            'profile' => 'Profil Duzenleme',
            'events' => 'Etkinlik Kullanimi',
            default => ucfirst($type),
        };
    }
}
