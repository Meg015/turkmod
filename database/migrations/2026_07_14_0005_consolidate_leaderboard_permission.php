<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0005_consolidate_leaderboard_permission';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT INTO user_group_permissions (group_id, permission_key, permission_value, created_at, updated_at)
             SELECT DISTINCT group_id, 'leaderboard.admin', 1, NOW(), NOW()
             FROM user_group_permissions
             WHERE permission_key IN ('leaderboard.view', 'leaderboard.manage')
               AND permission_value = 1
             ON DUPLICATE KEY UPDATE permission_value = 1, updated_at = NOW()"
        );

        $pdo->exec(
            "INSERT INTO user_group_permission_overrides (user_id, permission_key, permission_value, reason, updated_by, created_at, updated_at)
             SELECT user_id, 'leaderboard.admin', MAX(permission_value), 'Leaderboard permission consolidation', MAX(updated_by), NOW(), NOW()
             FROM user_group_permission_overrides
             WHERE permission_key IN ('leaderboard.view', 'leaderboard.manage')
             GROUP BY user_id
             ON DUPLICATE KEY UPDATE permission_value = VALUES(permission_value), reason = VALUES(reason), updated_by = VALUES(updated_by), updated_at = NOW()"
        );

        $pdo->exec("DELETE FROM user_group_permissions WHERE permission_key IN ('leaderboard.view', 'leaderboard.manage')");
        $pdo->exec("DELETE FROM user_group_permission_overrides WHERE permission_key IN ('leaderboard.view', 'leaderboard.manage')");
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Eski leaderboard izinleri bilinçli olarak birleştirildi; otomatik geri dönüş desteklenmiyor.');
    }
};
