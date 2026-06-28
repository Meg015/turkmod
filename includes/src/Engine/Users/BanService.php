<?php

declare(strict_types=1);

namespace App\Engine\Users;

use PDO;

final class BanService
{
    public static function ban(PDO $pdo, int $userId, string $reason = ''): void
    {
        $nowSql = self::isSqlite($pdo) ? "datetime('now')" : 'NOW()';
        $pdo->prepare("UPDATE users SET status = 'banned', is_banned = 1, banned_at = {$nowSql}, ban_reason = :reason, updated_at = {$nowSql} WHERE id = :id")
            ->execute(['reason' => $reason, 'id' => $userId]);
    }

    public static function unban(PDO $pdo, int $userId): void
    {
        $nowSql = self::isSqlite($pdo) ? "datetime('now')" : 'NOW()';
        $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'banned' THEN 'active' ELSE status END, is_banned = 0, banned_at = NULL, ban_reason = NULL, updated_at = {$nowSql} WHERE id = :id")
            ->execute(['id' => $userId]);
    }

    private static function isSqlite(PDO $pdo): bool
    {
        return stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false;
    }
}
