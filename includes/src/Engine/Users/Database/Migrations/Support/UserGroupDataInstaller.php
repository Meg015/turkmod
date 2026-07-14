<?php

declare(strict_types=1);

namespace App\Engine\Users\Database\Migrations\Support;

use PDO;
use RuntimeException;

final class UserGroupDataInstaller
{
    public function install(PDO $pdo): void
    {
        foreach (['users', 'user_groups', 'user_group_permissions', 'user_group_members'] as $table) {
            if (!$this->tableExists($pdo, $table)) {
                throw new RuntimeException("Missing {$table}; synchronize the base database schema first.");
            }
        }

        $groups = [
            ['Admin', 'admin', 'Tam yetkili sistem grubu.', '#dc2626', 1, 0, 1],
            ['Editor', 'editor', 'Icerik yonetimi grubu.', '#2563eb', 2, 0, 1],
            ['Uye', 'member', 'Varsayilan uye grubu.', '#64748b', 3, 1, 0],
        ];

        foreach ($groups as [$name, $slug, $description, $color, $order, $isDefault, $isStaff]) {
            $this->upsertGroup($pdo, compact('name', 'slug', 'description', 'color', 'order', 'isDefault', 'isStaff'));
        }

        $adminId = $this->groupId($pdo, 'admin');
        if ($adminId > 0) {
            $this->insertPermissions($pdo, $adminId, ['*']);
        }

        $editorId = $this->groupId($pdo, 'editor');
        if ($editorId > 0 && $this->permissionCount($pdo, $editorId) === 0) {
            $this->insertPermissions($pdo, $editorId, [
                'admin.access', 'dashboard.view', 'queue.view', 'users.view',
                'topics.view', 'topics.create', 'topics.edit', 'topics.delete',
                'categories.view', 'categories.create', 'categories.edit',
                'comments.view', 'comments.create', 'comments.edit', 'comments.delete',
                'media.view', 'media.manage', 'reports.view', 'reports.manage',
                'scraper.view', 'logs.view',
            ]);
        }

        $memberId = $this->groupId($pdo, 'member');
        if ($memberId > 0 && $this->permissionCount($pdo, $memberId) === 0) {
            $this->insertPermissions($pdo, $memberId, [
                'topics.view', 'topics.create', 'comments.view', 'comments.create',
            ]);
        }

        $defaultGroupId = $this->defaultGroupId($pdo);
        if ($defaultGroupId > 0 && $this->missingMembershipCount($pdo) > 0) {
            $this->backfillMemberships($pdo, $defaultGroupId);
        }
    }

    private function upsertGroup(PDO $pdo, array $group): void
    {
        $params = [
            'name' => $group['name'],
            'slug' => $group['slug'],
            'description' => $group['description'],
            'color' => $group['color'],
            'priority' => $group['order'],
            'display_order' => $group['order'],
            'is_default' => $group['isDefault'],
            'is_staff' => $group['isStaff'],
        ];

        if ($this->isSqlite($pdo)) {
            $existingId = $this->groupId($pdo, (string) $group['slug']);
            if ($existingId > 0) {
                $stmt = $pdo->prepare("UPDATE user_groups SET
                    is_staff = CASE WHEN :is_staff = 1 THEN 1 ELSE is_staff END,
                    priority = CASE WHEN priority = 0 OR priority IN (100, 500, 1000) THEN :priority ELSE priority END,
                    display_order = CASE WHEN display_order IN (10, 20, 30) THEN :display_order ELSE display_order END,
                    color = COALESCE(color, :color)
                    WHERE id = :id");
                $stmt->execute([
                    'is_staff' => $params['is_staff'],
                    'priority' => $params['priority'],
                    'display_order' => $params['display_order'],
                    'color' => $params['color'],
                    'id' => $existingId,
                ]);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO user_groups
                (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
                VALUES (:name, :slug, :description, :color, :priority, :display_order, 1, :is_default, :is_staff, datetime('now'), datetime('now'))");
            $stmt->execute($params);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO user_groups
            (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
            VALUES (:name, :slug, :description, :color, :priority, :display_order, 1, :is_default, :is_staff, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                is_staff = CASE WHEN VALUES(is_staff) = 1 THEN 1 ELSE user_groups.is_staff END,
                priority = CASE WHEN user_groups.priority = 0 OR user_groups.priority IN (100, 500, 1000) THEN VALUES(priority) ELSE user_groups.priority END,
                display_order = CASE WHEN user_groups.display_order IN (10, 20, 30) THEN VALUES(display_order) ELSE user_groups.display_order END,
                color = COALESCE(user_groups.color, VALUES(color)),
                updated_at = user_groups.updated_at");
        $stmt->execute($params);
    }

    private function insertPermissions(PDO $pdo, int $groupId, array $permissions): void
    {
        $now = $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()';
        $verb = $this->isSqlite($pdo) ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt = $pdo->prepare("{$verb} INTO user_group_permissions
            (group_id, permission_key, permission_value, created_at, updated_at)
            VALUES (?, ?, 1, {$now}, {$now})");
        foreach (array_values(array_unique($permissions)) as $permission) {
            $stmt->execute([$groupId, $permission]);
        }
    }

    private function backfillMemberships(PDO $pdo, int $groupId): void
    {
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_group_members
                (user_id, group_id, is_primary, reason, created_at, updated_at)
                SELECT u.id, ?, 1, 'default_group_import', datetime('now'), datetime('now')
                FROM users u LEFT JOIN user_group_members m ON m.user_id = u.id
                WHERE m.user_id IS NULL");
        } else {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_group_members
                (user_id, group_id, is_primary, reason, created_at, updated_at)
                SELECT u.id, ?, 1, 'default_group_import', NOW(), NOW()
                FROM users u LEFT JOIN user_group_members m ON m.user_id = u.id
                WHERE m.user_id IS NULL");
        }
        $stmt->execute([$groupId]);
    }

    private function missingMembershipCount(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM users u LEFT JOIN user_group_members m ON m.user_id = u.id WHERE m.user_id IS NULL')->fetchColumn();
    }

    private function permissionCount(PDO $pdo, int $groupId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_group_permissions WHERE group_id = ?');
        $stmt->execute([$groupId]);
        return (int) $stmt->fetchColumn();
    }

    private function groupId(PDO $pdo, string $slug): int
    {
        $stmt = $pdo->prepare('SELECT id FROM user_groups WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function defaultGroupId(PDO $pdo): int
    {
        $id = (int) ($pdo->query('SELECT id FROM user_groups WHERE is_default = 1 AND is_active = 1 ORDER BY display_order, id LIMIT 1')->fetchColumn() ?: 0);
        return $id > 0 ? $id : $this->groupId($pdo, 'member');
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ($this->isSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        }
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isSqlite(PDO $pdo): bool
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }
}
