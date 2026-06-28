<?php
/**
 * Users Module — İş mantığı fonksiyonları
 */

declare(strict_types=1);

use App\Modules\BanAppeals\Services\BanAppealNotificationService;
use App\Modules\BanAppeals\Services\BanAppealSchemaService;
use App\Modules\BanAppeals\Services\BanAppealService;

function usersDbDriver(PDO $pdo): string
{
    try {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {
        return '';
    }
}

function usersIsSqlite(PDO $pdo): bool
{
    return usersDbDriver($pdo) === 'sqlite';
}

function usersTableExists(PDO $pdo, string $table): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $stmt = $pdo->prepare("PRAGMA table_info(" . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . ")");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersIndexExists(PDO $pdo, string $table, string $index): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $stmt = $pdo->prepare("PRAGMA index_list({$safeTable})");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersDropLegacyGroupTypeColumn(PDO $pdo): void
{
    if (!usersTableExists($pdo, 'user_groups') || !usersColumnExists($pdo, 'user_groups', 'type')) {
        return;
    }

    try {
        if (usersIsSqlite($pdo)) {
            $pdo->exec("ALTER TABLE user_groups DROP COLUMN type");
            return;
        }

        if (usersIndexExists($pdo, 'user_groups', 'user_groups_type_index')) {
            $pdo->exec("ALTER TABLE user_groups DROP INDEX user_groups_type_index");
        }

        $pdo->exec("ALTER TABLE user_groups DROP COLUMN type");
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersDropLegacyGroupTypeColumn']);
        }
    }
}

function usersGroupsAvailable(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (!empty($GLOBALS['_users_group_schema_ready_' . $key])) {
        return usersTableExists($pdo, 'user_groups')
            && usersTableExists($pdo, 'user_group_members')
            && usersTableExists($pdo, 'user_group_permissions');
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $cache[$key] = usersTableExists($pdo, 'user_groups')
        && usersTableExists($pdo, 'user_group_members')
        && usersTableExists($pdo, 'user_group_permissions');

    return $cache[$key];
}

function usersResetGroupAvailabilityCache(PDO $pdo): void
{
    $GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)] = true;
}

function usersGroupSlug(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('slugify')) {
        $slug = (string) slugify($value);
    } else {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
        $slug = trim($slug, '-');
    }

    return substr($slug, 0, 100);
}

function usersDefaultGroupPermissions(string $slug): array
{
    $catalog = array_keys(usersPermissionCatalog());

    if ($slug === 'admin') {
        return ['*'];
    }

    if ($slug === 'editor') {
        return array_values(array_unique(array_merge([
            'admin.access',
            'dashboard.view',
            'queue.view',
            'users.view',
            'topics.view',
            'topics.create',
            'topics.edit',
            'categories.view',
            'categories.create',
            'categories.edit',
            'comments.view',
            'comments.edit',
            'comments.delete',
            'media.view',
            'media.manage',
            'reports.view',
            'reports.manage',
            'scraper.view',
            'logs.view',
        ], array_filter($catalog, static fn(string $key): bool => str_starts_with($key, 'topics.') || str_starts_with($key, 'comments.')))));
    }

    return [
        'topics.view',
        'topics.create',
        'comments.view',
        'comments.create',
    ];
}

function usersEnsureGroupSchema(PDO $pdo): void
{
    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    static $done = [];
    $key = spl_object_id($pdo);
    if (!empty($done[$key])) {
        return;
    }

    if (!usersTableExists($pdo, 'users')) {
        return;
    }

    if (usersIsSqlite($pdo)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT NULL,
            color TEXT NULL,
            priority INTEGER NOT NULL DEFAULT 0,
            parent_group_id INTEGER NULL,
            display_order INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            is_default INTEGER NOT NULL DEFAULT 0,
            is_staff INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");
        foreach (['color TEXT NULL', 'priority INTEGER NOT NULL DEFAULT 0', 'parent_group_id INTEGER NULL'] as $definition) {
            [$column] = explode(' ', $definition, 2);
            if (!usersColumnExists($pdo, 'user_groups', $column)) {
                $pdo->exec("ALTER TABLE user_groups ADD COLUMN {$definition}");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_permissions (
            group_id INTEGER NOT NULL,
            permission_key TEXT NOT NULL,
            permission_value INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            PRIMARY KEY (group_id, permission_key)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_members (
            user_id INTEGER NOT NULL,
            group_id INTEGER NOT NULL,
            is_primary INTEGER NOT NULL DEFAULT 0,
            assigned_by INTEGER NULL,
            reason TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            PRIMARY KEY (user_id, group_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            old_group_ids TEXT NULL,
            new_group_ids TEXT NULL,
            changed_by INTEGER NULL,
            reason TEXT NULL,
            created_at TEXT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_permission_overrides (
            user_id INTEGER NOT NULL,
            permission_key TEXT NOT NULL,
            permission_value INTEGER NOT NULL DEFAULT 1,
            reason TEXT NULL,
            updated_by INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            PRIMARY KEY (user_id, permission_key)
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT NULL,
            color VARCHAR(32) NULL,
            priority INT NOT NULL DEFAULT 0,
            parent_group_id BIGINT UNSIGNED NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_staff TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_groups_slug_unique (slug),
            KEY user_groups_active_order_index (is_active, display_order),
            KEY user_groups_default_index (is_default),
            KEY user_groups_priority_index (priority),
            KEY user_groups_parent_index (parent_group_id),
            CONSTRAINT user_groups_parent_foreign FOREIGN KEY (parent_group_id) REFERENCES user_groups(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'color' => "ALTER TABLE user_groups ADD COLUMN color VARCHAR(32) NULL AFTER description",
            'priority' => "ALTER TABLE user_groups ADD COLUMN priority INT NOT NULL DEFAULT 0 AFTER color",
            'parent_group_id' => "ALTER TABLE user_groups ADD COLUMN parent_group_id BIGINT UNSIGNED NULL AFTER priority",
        ] as $column => $sql) {
            if (!usersColumnExists($pdo, 'user_groups', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_permissions (
            group_id BIGINT UNSIGNED NOT NULL,
            permission_key VARCHAR(191) NOT NULL,
            permission_value TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, permission_key),
            KEY user_group_permissions_key_index (permission_key, permission_value),
            CONSTRAINT user_group_permissions_group_foreign FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_members (
            user_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            assigned_by BIGINT UNSIGNED NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, group_id),
            KEY user_group_members_group_index (group_id),
            KEY user_group_members_primary_index (user_id, is_primary),
            KEY user_group_members_assigned_by_index (assigned_by),
            CONSTRAINT user_group_members_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT user_group_members_group_foreign FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
            CONSTRAINT user_group_members_assigned_by_foreign FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            old_group_ids TEXT NULL,
            new_group_ids TEXT NULL,
            changed_by BIGINT UNSIGNED NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_group_logs_user_created_index (user_id, created_at),
            KEY user_group_logs_changed_by_index (changed_by),
            CONSTRAINT user_group_logs_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT user_group_logs_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_group_permission_overrides (
            user_id BIGINT UNSIGNED NOT NULL,
            permission_key VARCHAR(191) NOT NULL,
            permission_value TINYINT(1) NOT NULL DEFAULT 1,
            reason VARCHAR(255) NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, permission_key),
            KEY user_group_permission_overrides_key_index (permission_key, permission_value),
            KEY user_group_permission_overrides_updated_by_index (updated_by),
            CONSTRAINT user_group_permission_overrides_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT user_group_permission_overrides_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    usersDropLegacyGroupTypeColumn($pdo);

    usersSeedDefaultGroups($pdo);
    usersBackfillGroupMemberships($pdo);

    $done[$key] = true;
    usersResetGroupAvailabilityCache($pdo);
}

function usersSeedDefaultGroups(PDO $pdo): void
{
    $groups = [
        ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Tam yetkili sistem grubu.', 'is_active' => 1, 'is_default' => 0, 'display_order' => 1],
        ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Icerik yonetimi grubu.', 'is_active' => 1, 'is_default' => 0, 'display_order' => 2],
        ['name' => 'Uye', 'slug' => 'member', 'description' => 'Varsayilan uye grubu.', 'is_active' => 1, 'is_default' => 1, 'display_order' => 3],
    ];

    foreach ($groups as $group) {
        $slug = usersGroupSlug((string) ($group['slug'] ?? $group['name'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $name = (string) ($group['name'] ?? ucfirst($slug));
        $isAdmin = $slug === 'admin';
        $isStaff = $isAdmin || $slug === 'editor';
        $displayOrder = (int) ($group['display_order'] ?? 0);

        $params = [
            'name' => $name,
            'slug' => $slug,
            'description' => $group['description'] ?? null,
            'color' => $isAdmin ? '#dc2626' : ($isStaff ? '#2563eb' : '#64748b'),
            'priority' => $displayOrder,
            'display_order' => $displayOrder,
            'is_active' => (int) ($group['is_active'] ?? 1) === 1 ? 1 : 0,
            'is_default' => (int) ($group['is_default'] ?? 0) === 1 || $slug === 'member' ? 1 : 0,
            'is_staff' => $isStaff ? 1 : 0,
        ];

        if (usersIsSqlite($pdo)) {
            $existingId = usersGroupIdBySlug($pdo, $slug);
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
                    'color' => $params['color'],
                    'id' => $existingId,
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO user_groups (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
                    VALUES (:name, :slug, :description, :color, :priority, :display_order, :is_active, :is_default, :is_staff, datetime('now'), datetime('now'))");
                $stmt->execute($params);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_groups (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
                VALUES (:name, :slug, :description, :color, :priority, :display_order, :is_active, :is_default, :is_staff, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    is_staff = CASE WHEN VALUES(is_staff) = 1 THEN 1 ELSE user_groups.is_staff END,
                    priority = CASE WHEN user_groups.priority = 0 OR user_groups.priority IN (100, 500, 1000) THEN VALUES(priority) ELSE user_groups.priority END,
                    display_order = CASE WHEN user_groups.display_order IN (10, 20, 30) THEN VALUES(display_order) ELSE user_groups.display_order END,
                    color = COALESCE(user_groups.color, VALUES(color)),
                    updated_at = user_groups.updated_at");
            $stmt->execute($params);
        }

        $groupId = usersGroupIdBySlug($pdo, $slug);
        if ($groupId <= 0) {
            continue;
        }

        $permissionCount = (int) $pdo->query("SELECT COUNT(*) FROM user_group_permissions WHERE group_id = " . $groupId)->fetchColumn();
        if ($permissionCount > 0 && !$isAdmin) {
            continue;
        }

        $permissions = usersDefaultGroupPermissions($slug);
        if ($isAdmin && !in_array('*', $permissions, true)) {
            $permissions[] = '*';
        }

        usersReplaceGroupPermissions($pdo, $groupId, $permissions);
    }
}

function usersBackfillGroupMemberships(PDO $pdo): void
{
    if (!usersTableExists($pdo, 'user_groups') || !usersTableExists($pdo, 'user_group_members')) {
        return;
    }

    $defaultGroupId = usersDefaultGroupId($pdo);
    if ($defaultGroupId > 0) {
        try {
            if (usersIsSqlite($pdo)) {
                $pdo->exec("INSERT OR IGNORE INTO user_group_members (user_id, group_id, is_primary, reason, created_at, updated_at)
                    SELECT u.id, {$defaultGroupId}, 1, 'default_group_import', datetime('now'), datetime('now')
                    FROM users u
                    WHERE NOT EXISTS (SELECT 1 FROM user_group_members m WHERE m.user_id = u.id)");
            } else {
                $pdo->exec("INSERT IGNORE INTO user_group_members (user_id, group_id, is_primary, reason, created_at, updated_at)
                    SELECT u.id, {$defaultGroupId}, 1, 'default_group_import', NOW(), NOW()
                    FROM users u
                    WHERE NOT EXISTS (SELECT 1 FROM user_group_members m WHERE m.user_id = u.id)");
            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }
}

function usersGroupIdBySlug(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare("SELECT id FROM user_groups WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function usersDefaultGroupId(PDO $pdo): int
{
    try {
        $id = (int) ($pdo->query("SELECT id FROM user_groups WHERE is_default = 1 AND is_active = 1 ORDER BY display_order, id LIMIT 1")->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        return (int) ($pdo->query("SELECT id FROM user_groups WHERE slug = 'member' AND is_active = 1 LIMIT 1")->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function usersReplaceGroupPermissions(PDO $pdo, int $groupId, array $permissions): void
{
    $groupId = max(0, $groupId);
    if ($groupId <= 0) {
        return;
    }

    $permissions = usersNormalizePermissionKeys($permissions);
    $group = usersGetGroupById($pdo, $groupId);
    if ($group && ((string) ($group['slug'] ?? '') === 'admin')) {
        if (!in_array('*', $permissions, true)) {
            $permissions[] = '*';
        }
    }

    $pdo->prepare("DELETE FROM user_group_permissions WHERE group_id = ?")->execute([$groupId]);
    if (empty($permissions)) {
        return;
    }

    $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
    $stmt = $pdo->prepare("INSERT INTO user_group_permissions (group_id, permission_key, permission_value, created_at, updated_at)
        VALUES (:group_id, :permission_key, 1, {$nowSql}, {$nowSql})");
    foreach ($permissions as $permission) {
        $stmt->execute([
            'group_id' => $groupId,
            'permission_key' => substr($permission, 0, 191),
        ]);
    }
}

function usersGetGroups(PDO $pdo, bool $activeOnly = false): array
{
    try {
        usersEnsureGroupSchema($pdo);
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $stmt = $pdo->query("SELECT g.*,
                (SELECT COUNT(*) FROM user_group_members m WHERE m.group_id = g.id) AS member_count,
                (SELECT COUNT(*) FROM user_group_permissions p WHERE p.group_id = g.id AND p.permission_value = 1) AS permission_count
            FROM user_groups g
            {$where}
            ORDER BY g.display_order ASC, g.name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersGetGroupById(PDO $pdo, int $groupId): ?array
{
    if ($groupId <= 0) {
        return null;
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT * FROM user_groups WHERE id = ? LIMIT 1");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        return $group ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function usersGetGroupPermissionMap(PDO $pdo, int $groupId): array
{
    if ($groupId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT permission_key FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 ORDER BY permission_key");
        $stmt->execute([$groupId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $permission) {
            $map[(string) $permission] = true;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function usersModulePermissionCatalog(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $modulesRoot = dirname(__DIR__, 4) . '/src/Modules';
    if (!is_dir($modulesRoot)) {
        $cached = ['labels' => [], 'descriptions' => []];
        return $cached;
    }

    $labels = [];
    $descriptions = [];

    foreach (glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        try {
            $metadata = require $moduleFile;
        } catch (Throwable $e) {
            continue;
        }

        if (!is_array($metadata) || !isset($metadata['permissions']) || !is_array($metadata['permissions'])) {
            continue;
        }

        foreach ($metadata['permissions'] as $permission) {
            $key = (string) ($permission['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $labels[$key] = (string) ($permission['label'] ?? $key);
            $descriptions[$key] = (string) ($permission['description'] ?? $permission['label'] ?? $key);
        }
    }

    $cached = ['labels' => $labels, 'descriptions' => $descriptions];
    return $cached;
}

function usersPermissionCatalog(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $core = [
        '*' => 'Tum Yetkiler',
        'admin.access' => 'Admin Paneline Erisim',
        'dashboard.view' => 'Dashboard Goruntule',
        'queue.view' => 'Bekleyen Isleri Goruntule',
        'groups.view' => 'Gruplari Goruntule',
        'groups.create' => 'Grup Olustur',
        'groups.edit' => 'Grup Duzenle',
        'groups.delete' => 'Grup Sil',
        'users.view' => 'Kullanicilari Goruntule',
        'users.create' => 'Kullanici Olustur',
        'users.edit' => 'Kullanici Duzenle',
        'users.delete' => 'Kullanici Sil',
        'topics.view' => 'Konulari Goruntule',
        'topics.create' => 'Konu Olustur',
        'topics.edit' => 'Konu Duzenle',
        'topics.delete' => 'Konu Sil',
        'categories.view' => 'Kategorileri Goruntule',
        'categories.create' => 'Kategori Olustur',
        'categories.edit' => 'Kategori Duzenle',
        'categories.delete' => 'Kategori Sil',
        'comments.view' => 'Yorumlari Goruntule',
        'comments.create' => 'Yorum Olustur',
        'comments.edit' => 'Yorum Duzenle',
        'comments.delete' => 'Yorum Sil',
        'settings.view' => 'Ayarlari Goruntule',
        'settings.edit' => 'Ayarlari Duzenle',
        'logs.view' => 'Kayitlari Goruntule',
        'logs.manage' => 'Kayitlari Yonet',
        'media.view' => 'Medyayi Goruntule',
        'media.manage' => 'Medyayi Yonet',
        'scraper.view' => 'Icerik Botunu Goruntule',
        'scraper.manage' => 'Icerik Botunu Yonet',
        'leaderboard.view' => 'Liderlik Tablosunu Goruntule',
        'leaderboard.manage' => 'Liderlik Tablosunu Yonet',
        'system.view' => 'Sistem Sagligini Goruntule',
        'system.manage' => 'Sistem Bakimini Yonet',
        'notifications.view' => 'Bildirimleri Goruntule',
        'notifications.manage' => 'Bildirimleri Yonet',
        'appearance.view' => 'Gorunumu Goruntule',
        'appearance.edit' => 'Gorunumu Duzenle',
        'themes.view' => 'Temalari Goruntule',
        'themes.edit' => 'Temalari Duzenle',
        'legacy_redirects.view' => 'SEO Yonlendirmelerini Goruntule',
        'legacy_redirects.manage' => 'SEO Yonlendirmelerini Yonet',
        'rate_limits.view' => 'Rate Limit Kayitlarini Goruntule',
        'rate_limits.manage' => 'Rate Limit Kayitlarini Yonet',
        'events.view' => 'Etkinlikleri Goruntule',
        'events.manage' => 'Etkinlikleri Yonet',
    ];

    $cached = $core + usersModulePermissionCatalog()['labels'];
    return $cached;
}

function usersPermissionDescriptions(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $core = [
        '*' => 'Sistemdeki tum admin ve yonetim yetkilerini verir. Sadece tam guvenilen gruplarda kullanin.',
        'admin.access' => 'Admin paneline giris yapabilir ve yetkili oldugu admin ekranlarini gorebilir.',
        'dashboard.view' => 'Admin dashboard ozetlerini ve hizli durum kartlarini gorebilir.',
        'queue.view' => 'Bekleyen isler kuyrugunu, bekleyen rapor ve icerik ozetlerini gorebilir.',
        'groups.view' => 'Kullanici gruplarini ve grup yetki listesini goruntuleyebilir.',
        'groups.create' => 'Yeni kullanici grubu olusturabilir.',
        'groups.edit' => 'Mevcut gruplarin ad, sira, durum ve yetkilerini duzenleyebilir.',
        'groups.delete' => 'Gruplari pasife alabilir veya silebilir.',
        'users.view' => 'Kullanici listesini ve kullanici detaylarini goruntuleyebilir.',
        'users.create' => 'Admin panelinden yeni kullanici olusturabilir.',
        'users.edit' => 'Kullanici bilgilerini, durumunu ve grup atamalarini duzenleyebilir.',
        'users.delete' => 'Kullanici hesaplarini silebilir veya silme islemi baslatabilir.',
        'topics.view' => 'Admin panelinde konulari listeleyip detaylarini gorebilir.',
        'topics.create' => 'Yeni konu veya icerik olusturabilir.',
        'topics.edit' => 'Mevcut konu iceriklerini ve yayin durumlarini duzenleyebilir.',
        'topics.delete' => 'Konulari silebilir veya kaldirma islemi yapabilir.',
        'categories.view' => 'Kategori listesini ve kategori ayarlarini goruntuleyebilir.',
        'categories.create' => 'Yeni kategori olusturabilir.',
        'categories.edit' => 'Kategori ad, siralama ve gorunum ayarlarini duzenleyebilir.',
        'categories.delete' => 'Kategorileri silebilir veya pasife alabilir.',
        'comments.view' => 'Yorumlari ve yorum moderasyon ekranlarini goruntuleyebilir.',
        'comments.create' => 'Yorum olusturabilir veya yorum ekleme islemlerini kullanabilir.',
        'comments.edit' => 'Yorum iceriklerini ve moderasyon durumlarini duzenleyebilir.',
        'comments.delete' => 'Yorumlari silebilir veya kaldirabilir.',
        'settings.view' => 'Genel ayarlar ve sistem yapilandirmasini goruntuleyebilir.',
        'settings.edit' => 'Site ayarlarini, gorunum ve sistem yapilandirmasini degistirebilir.',
        'logs.view' => 'Aktivite, islem ve sistem kayitlarini goruntuleyebilir.',
        'logs.manage' => 'Aktivite ve islem kayitlarini temizleyebilir veya geri alma islemlerini kullanabilir.',
        'media.view' => 'Medya kutuphanesini ve yuklenen dosyalari goruntuleyebilir.',
        'media.manage' => 'Medya dosyalarini yukleyebilir, duzenleyebilir veya silebilir.',
        'scraper.view' => 'Icerik botu panelini, site eslemelerini ve bot kayitlarini gorebilir.',
        'scraper.manage' => 'Icerik botu site/esleme ayarlarini, cekme ve yayinlama islemlerini yonetebilir.',
        'leaderboard.view' => 'Liderlik tablosu cache ve hesaplama durumunu goruntuleyebilir.',
        'leaderboard.manage' => 'Liderlik tablosunu yeniden hesaplayabilir, cache temizleyebilir ve ayarlarini kaydedebilir.',
        'system.view' => 'Sistem sagligi ve ortam kontrollerini goruntuleyebilir.',
        'system.manage' => 'Sistem bakim islemlerini calistirabilir.',
        'notifications.view' => 'Bildirim gecmisi, sablonlar ve gonderim loglarini goruntuleyebilir.',
        'notifications.manage' => 'Bildirim olusturabilir, sablonlari ve bildirim ayarlarini yonetebilir.',
        'appearance.view' => 'Gorunum, header, footer, sidebar ve menu ayarlarini gorebilir.',
        'appearance.edit' => 'Gorunum, header, footer, sidebar ve menu ayarlarini kaydedebilir.',
        'themes.view' => 'Tema merkezini, tema dosyalarini ve tema ayarlarini goruntuleyebilir.',
        'themes.edit' => 'Tema aktiflestirme, dosya kaydetme, cogaltma, ZIP yukleme ve tema ayarlari islemlerini yapabilir.',
        'legacy_redirects.view' => 'SEO yonlendirme kurallarini, hitleri ve saglik kontrollerini gorebilir.',
        'legacy_redirects.manage' => 'SEO yonlendirme ayarlarini, kurallarini, test ve ice aktarma islemlerini yonetebilir.',
        'rate_limits.view' => 'Rate limit kayitlarini ve durum ozetlerini goruntuleyebilir.',
        'rate_limits.manage' => 'Rate limit kayitlarini silebilir veya temizleyebilir.',
        'events.view' => 'Etkinlik modulu admin ekranlarini goruntuleyebilir.',
        'events.manage' => 'Etkinlik ayarlari, oduller, cekilisler ve admin aksiyonlarini yonetebilir.',
    ];

    $cached = $core + usersModulePermissionCatalog()['descriptions'];
    return $cached;
}

function usersPermissionAliases(string $permission): array
{
    $permission = trim($permission);
    if ($permission === '') {
        return [];
    }

    $aliases = [$permission];
    if (str_ends_with($permission, '.view')) {
        $prefix = substr($permission, 0, -5);
        foreach (['manage', 'edit', 'create', 'delete'] as $suffix) {
            $aliases[] = $prefix . '.' . $suffix;
        }
    }
    if (str_ends_with($permission, '.edit')) {
        $aliases[] = substr($permission, 0, -5) . '.manage';
    }
    if (str_ends_with($permission, '.delete')) {
        $aliases[] = substr($permission, 0, -7) . '.manage';
    }

    return array_values(array_unique($aliases));
}

function usersNormalizePermissionKeys(array $permissions): array
{
    $known = array_fill_keys(array_keys(usersPermissionCatalog()), true);
    $normalized = [];
    foreach ($permissions as $permission) {
        $permission = substr(trim((string) $permission), 0, 191);
        if ($permission === '' || !isset($known[$permission])) {
            continue;
        }
        $normalized[$permission] = true;
    }

    if (isset($normalized['*'])) {
        return ['*'];
    }

    return array_keys($normalized);
}

function usersGroupGrantsAdmin(PDO $pdo, int $groupId): bool
{
    $group = usersGetGroupById($pdo, $groupId);
    if (!$group) {
        return $groupId === 1;
    }
    if ((string) ($group['slug'] ?? '') === 'admin') {
        return true;
    }
    $permissions = usersGetGroupPermissionMap($pdo, $groupId);
    return isset($permissions['*']) || isset($permissions['admin.access']);
}

function usersUserGroupIds(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT m.group_id FROM user_group_members m INNER JOIN user_groups g ON g.id = m.group_id WHERE m.user_id = ? AND g.is_active = 1 ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC");
        $stmt->execute([$userId]);
        return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    } catch (Throwable $e) {
        return [];
    }
}

function usersPrimaryGroupMap(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
    if ($userIds === []) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) {
        return [];
    }

    if (!usersGroupsAvailable($pdo) && empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("SELECT
                m.user_id,
                g.id,
                g.name,
                g.slug
            FROM user_group_members m
            INNER JOIN user_groups g ON g.id = m.group_id
            WHERE m.user_id IN ({$placeholders}) AND g.is_active = 1
            ORDER BY m.user_id ASC, m.is_primary DESC, g.display_order ASC, g.name ASC");
        $stmt->execute($userIds);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid <= 0 || isset($map[$uid])) {
                continue;
            }
            $map[$uid] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function usersDecorateUsersWithPrimaryGroup(PDO $pdo, array $users): array
{
    if ($users === []) {
        return $users;
    }

    $map = usersPrimaryGroupMap($pdo, array_column($users, 'id'));
    if ($map === []) {
        return $users;
    }

    foreach ($users as $index => $user) {
        $uid = (int) ($user['id'] ?? 0);
        $group = $map[$uid] ?? null;
        if (!$group) {
            continue;
        }
        $users[$index]['group_id'] = (int) $group['id'];
        $users[$index]['group_name'] = (string) $group['name'];
        $users[$index]['group_slug'] = (string) $group['slug'];
    }

    return $users;
}

function usersPrimaryGroupForUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $map = usersPrimaryGroupMap($pdo, [$userId]);
    return $map[$userId] ?? null;
}

function usersDecorateUserWithPrimaryGroup(PDO $pdo, array $user): array
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return $user;
    }

    $group = usersPrimaryGroupForUser($pdo, $userId);
    if (!$group) {
        return $user;
    }
    $user['group_id'] = (int) $group['id'];
    $user['group_name'] = (string) $group['name'];
    $user['group_slug'] = (string) $group['slug'];

    return $user;
}

function usersSaveGroup(PDO $pdo, array $data, int $actorId = 0): array
{
    usersEnsureGroupSchema($pdo);

    $groupId = max(0, (int) ($data['group_id'] ?? $data['id'] ?? 0));
    $name = trim((string) ($data['name'] ?? ''));
    $slug = usersGroupSlug((string) ($data['slug'] ?? $name));
    $description = trim((string) ($data['description'] ?? ''));
    $priority = max(1, (int) ($data['priority'] ?? $data['display_order'] ?? 1));
    $displayOrder = $priority;
    $color = function_exists('uiCssColorValue') ? uiCssColorValue((string)($data['color'] ?? '')) : trim((string)($data['color'] ?? ''));
    if ($color === '') {
        $color = null;
    }
    $isActive = isset($data['is_active']) ? 1 : 0;
    $isDefault = isset($data['is_default']) ? 1 : 0;
    $isStaff = isset($data['is_staff']) ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Grup adi zorunludur.'];
    }
    if ($slug === '') {
        return ['ok' => false, 'message' => 'Grup slug degeri olusturulamadi.'];
    }

    $existingGroup = $groupId > 0 ? usersGetGroupById($pdo, $groupId) : null;
    $existingSlug = (string) ($existingGroup['slug'] ?? '');
    $existingIsDefault = (int) ($existingGroup['is_default'] ?? 0) === 1;

    if ($existingSlug === 'admin') {
        if ($slug !== 'admin') {
            return ['ok' => false, 'message' => 'Admin grubunun slug degeri degistirilemez.'];
        }
        $isActive = 1;
        $isStaff = 1;
    }

    if ($existingIsDefault && $isActive !== 1) {
        return ['ok' => false, 'message' => 'Varsayilan grup pasife alinamaz.'];
    }
    if ($isDefault === 1 && $isActive !== 1) {
        return ['ok' => false, 'message' => 'Varsayilan grup aktif olmak zorundadir.'];
    }
    if ($existingIsDefault && $isDefault !== 1) {
        $otherDefaultStmt = $pdo->prepare("SELECT id FROM user_groups WHERE is_default = 1 AND is_active = 1 AND id <> ? LIMIT 1");
        $otherDefaultStmt->execute([$groupId]);
        if (!$otherDefaultStmt->fetchColumn()) {
            return ['ok' => false, 'message' => 'En az bir aktif varsayilan grup kalmalidir.'];
        }
    }

    $duplicateStmt = $pdo->prepare("SELECT id FROM user_groups WHERE slug = ? AND id <> ? LIMIT 1");
    $duplicateStmt->execute([$slug, $groupId]);
    if ($duplicateStmt->fetchColumn()) {
        return ['ok' => false, 'message' => 'Bu slug ile baska bir grup var.'];
    }

    $permissions = (array) ($data['permissions'] ?? []);
    $permissions = usersNormalizePermissionKeys($permissions);
    if ($slug === 'admin' && !in_array('*', $permissions, true)) {
        $permissions[] = '*';
    }

    $ownTransaction = !$pdo->inTransaction();

    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';

        if ($isDefault === 1) {
            $pdo->exec("UPDATE user_groups SET is_default = 0");
        }

        if ($groupId > 0) {
            $stmt = $pdo->prepare("UPDATE user_groups SET
                    name = :name,
                    slug = :slug,
                    description = :description,
                    color = :color,
                    priority = :priority,
                    display_order = :display_order,
                    is_active = :is_active,
                    is_default = :is_default,
                    is_staff = :is_staff,
                    updated_at = {$nowSql}
                WHERE id = :id");
            $stmt->execute([
                'id' => $groupId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'color' => $color,
                'priority' => $priority,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_staff' => $isStaff,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_groups (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
                VALUES (:name, :slug, :description, :color, :priority, :display_order, :is_active, :is_default, :is_staff, {$nowSql}, {$nowSql})");
            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'color' => $color,
                'priority' => $priority,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_staff' => $isStaff,
            ]);
            $groupId = (int) $pdo->lastInsertId();
        }

        usersReplaceGroupPermissions($pdo, $groupId, $permissions);

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return ['ok' => true, 'message' => 'Grup kaydedildi.', 'group_id' => $groupId];
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSaveGroup', 'group_id' => $groupId, 'actor_id' => $actorId]);
        }
        return ['ok' => false, 'message' => 'Grup kaydedilemedi.'];
    }
}

function usersDeleteGroup(PDO $pdo, int $groupId, int $actorId = 0): string
{
    usersEnsureGroupSchema($pdo);

    if ($groupId <= 0) {
        return 'Gecersiz grup.';
    }

    $group = usersGetGroupById($pdo, $groupId);
    if (!$group) {
        return 'Grup bulunamadi.';
    }
    if ((string) ($group['slug'] ?? '') === 'admin') {
        return 'Admin grubu pasife alinamaz.';
    }
    if ((int) ($group['is_default'] ?? 0) === 1) {
        return 'Varsayilan grup pasife alinamaz. Once baska bir grubu varsayilan yapin.';
    }

    $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
    $fallbackGroupId = usersDefaultGroupId($pdo);
    if ($fallbackGroupId <= 0 || $fallbackGroupId === $groupId) {
        return 'Aktif varsayilan grup bulunamadigi icin grup pasife alinamaz.';
    }

    $pdo->prepare("UPDATE user_groups SET is_active = 0, is_default = 0, updated_at = {$nowSql} WHERE id = ?")->execute([$groupId]);
    if ($fallbackGroupId > 0 && $fallbackGroupId !== $groupId) {
        $userIdsStmt = $pdo->prepare("SELECT user_id FROM user_group_members WHERE group_id = ?");
        $userIdsStmt->execute([$groupId]);
        foreach ($userIdsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $userId) {
            $current = array_values(array_filter(usersUserGroupIds($pdo, (int) $userId), static fn(int $id): bool => $id !== $groupId));
            if (empty($current)) {
                $current[] = $fallbackGroupId;
            }
            usersSyncUserGroups($pdo, (int) $userId, $current, $actorId, 'group_deactivated');
        }
    }

    return '';
}

function usersSyncUserGroups(PDO $pdo, int $userId, array $groupIds, int $changedBy = 0, string $reason = ''): string
{
    usersEnsureGroupSchema($pdo);

    if ($userId <= 0) {
        return 'Gecersiz kullanici.';
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => $id > 0)));
    if (empty($groupIds)) {
        $defaultGroupId = usersDefaultGroupId($pdo);
        if ($defaultGroupId > 0) {
            $groupIds[] = $defaultGroupId;
        }
    }

    if (empty($groupIds)) {
        return 'En az bir aktif grup secilmelidir.';
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    if (usersIsSqlite($pdo)) {
        $activeStmt = $pdo->prepare("SELECT id FROM user_groups WHERE id IN ({$placeholders}) AND is_active = 1");
        $activeStmt->execute($groupIds);
        $activeSet = array_fill_keys(array_map('intval', $activeStmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
        $activeGroupIds = array_values(array_filter($groupIds, static fn(int $id): bool => isset($activeSet[$id])));
    } else {
        $activeStmt = $pdo->prepare("SELECT id FROM user_groups WHERE id IN ({$placeholders}) AND is_active = 1 ORDER BY FIELD(id, {$placeholders})");
        $activeStmt->execute(array_merge($groupIds, $groupIds));
        $activeGroupIds = array_values(array_map('intval', $activeStmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }
    if (empty($activeGroupIds)) {
        return 'Aktif grup bulunamadi.';
    }

    if ($userId === $changedBy) {
        $keepsAdmin = false;
        foreach ($activeGroupIds as $groupId) {
            if (usersGroupGrantsAdmin($pdo, $groupId)) {
                $keepsAdmin = true;
                break;
            }
        }
        if (!$keepsAdmin) {
            return 'Kendi admin grubunuzu kaldiramazsiniz.';
        }
    }

    $oldGroupIds = usersUserGroupIds($pdo, $userId);

    $ownTransaction = !$pdo->inTransaction();

    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        $pdo->prepare("DELETE FROM user_group_members WHERE user_id = ?")->execute([$userId]);

        $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
        $insertStmt = $pdo->prepare("INSERT INTO user_group_members (user_id, group_id, is_primary, assigned_by, reason, created_at, updated_at)
            VALUES (:user_id, :group_id, :is_primary, :assigned_by, :reason, {$nowSql}, {$nowSql})");
        foreach ($activeGroupIds as $index => $groupId) {
            $insertStmt->execute([
                'user_id' => $userId,
                'group_id' => $groupId,
                'is_primary' => $index === 0 ? 1 : 0,
                'assigned_by' => $changedBy > 0 ? $changedBy : null,
                'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
            ]);
        }

        $logStmt = $pdo->prepare("INSERT INTO user_group_logs (user_id, old_group_ids, new_group_ids, changed_by, reason, created_at)
            VALUES (:user_id, :old_group_ids, :new_group_ids, :changed_by, :reason, {$nowSql})");
        $logStmt->execute([
            'user_id' => $userId,
            'old_group_ids' => implode(',', $oldGroupIds),
            'new_group_ids' => implode(',', $activeGroupIds),
            'changed_by' => $changedBy > 0 ? $changedBy : null,
            'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
        ]);

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return '';
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSyncUserGroups', 'user_id' => $userId]);
        }
        return 'Kullanici gruplari guncellenemedi.';
    }
}

function usersUserHasGroupPermission(PDO $pdo, int $userId, string $permission): ?bool
{
    if ($userId <= 0 || $permission === '') {
        return false;
    }

    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) {
        return null;
    }

    if (!usersGroupsAvailable($pdo) && empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return null;
    }

    try {
        $aliases = usersPermissionAliases($permission);

        // 1. Bireysel yetki ezme (override) kontrolleri (Grup yetkilerinden önce değerlendirilir)
        $overrideStmt = $pdo->prepare("SELECT permission_key, permission_value 
            FROM user_group_permission_overrides 
            WHERE user_id = ? AND (permission_key = '*' OR permission_key IN (" . implode(',', array_fill(0, count($aliases), '?')) . "))");
        $overrideStmt->execute(array_merge([$userId], $aliases));
        $overrides = $overrideStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($overrides)) {
            $hasDeny = false;
            $hasGrant = false;
            foreach ($overrides as $override) {
                if ((int)$override['permission_value'] === 0) {
                    $hasDeny = true;
                } else {
                    $hasGrant = true;
                }
            }
            if ($hasDeny) {
                return false;
            }
            if ($hasGrant) {
                return true;
            }
        }

        // 2. Grup yetkisi kontrolleri
        $groupStmt = $pdo->prepare("SELECT g.id, g.slug
            FROM user_group_members m
            INNER JOIN user_groups g ON g.id = m.group_id
            WHERE m.user_id = ? AND g.is_active = 1
            ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC");
        $groupStmt->execute([$userId]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if ((string) ($group['slug'] ?? '') === 'admin') {
                return true;
            }

            $wildcardStmt = $pdo->prepare("SELECT 1 FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 AND permission_key = '*' LIMIT 1");
            $wildcardStmt->execute([(int) $group['id']]);
            if ($wildcardStmt->fetchColumn()) {
                return true;
            }

            $permissionStmt = $pdo->prepare("SELECT 1 FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 AND permission_key IN (" . implode(',', array_fill(0, count($aliases), '?')) . ") LIMIT 1");
            $permissionStmt->execute(array_merge([(int) $group['id']], $aliases));
            if ($permissionStmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersUserHasGroupPermission', 'user_id' => $userId, 'permission' => $permission]);
        }
        return null;
    }
}

function usersGetUserPermissionOverrides(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT permission_key, permission_value, reason FROM user_group_permission_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersSaveUserPermissionOverrides(PDO $pdo, int $userId, array $overrides, int $updatedBy = 0, string $reason = ''): void
{
    if ($userId <= 0) {
        return;
    }

    $ownTransaction = !$pdo->inTransaction();
    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        $pdo->prepare("DELETE FROM user_group_permission_overrides WHERE user_id = ?")->execute([$userId]);

        if (!empty($overrides)) {
            $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
            $stmt = $pdo->prepare("INSERT INTO user_group_permission_overrides (user_id, permission_key, permission_value, reason, updated_by, created_at, updated_at) 
                VALUES (:user_id, :permission_key, :permission_value, :reason, :updated_by, {$nowSql}, {$nowSql})");
            foreach ($overrides as $key => $value) {
                $stmt->execute([
                    'user_id' => $userId,
                    'permission_key' => substr((string)$key, 0, 191),
                    'permission_value' => (int)$value === 1 ? 1 : 0,
                    'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
                    'updated_by' => $updatedBy > 0 ? $updatedBy : null,
                ]);
            }
        }

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSaveUserPermissionOverrides', 'user_id' => $userId]);
        }
    }
}

function userHasPermission(?PDO $pdo, int $userId, string $permission): bool
{
    if (!$pdo || $userId <= 0 || $permission === '') {
        return false;
    }

    $result = usersUserHasGroupPermission($pdo, $userId, $permission);
    return $result === true;
}

function userIsAdmin(?PDO $pdo, int $userId): bool
{
    return $pdo instanceof PDO
        && $userId > 0
        && userHasPermission($pdo, $userId, 'admin.access');
}

function usersGetGroupInfo(PDO $pdo, int $userId): ?array
{
    $user = usersGetById($pdo, $userId);
    return $user ?: null;
}

function usersGetGroupHistory(PDO $pdo, int $userId, int $limit = 50): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT l.*, actor.name AS changed_by_name
            FROM user_group_logs l
            LEFT JOIN users actor ON actor.id = l.changed_by
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersChangeGroup(PDO $pdo, int $userId, int $newGroupId, int $currentUserId, array $validGroupIds): string
{
    if (!in_array($newGroupId, $validGroupIds, false)) {
        return 'Gecersiz grup.';
    }
    if ($userId === $currentUserId && !usersGroupGrantsAdmin($pdo, $newGroupId)) {
        return 'Kendi admin grubunuzu kaldiramazsiniz.';
    }

    return usersSyncUserGroups($pdo, $userId, [$newGroupId], $currentUserId, 'admin_primary_group_change');
}

function usersBan(PDO $pdo, int $userId, string $reason = ''): void
{
    \App\Engine\Users\BanService::ban($pdo, $userId, $reason);
}

function usersUnban(PDO $pdo, int $userId): void
{
    \App\Engine\Users\BanService::unban($pdo, $userId);
}

function usersActivate(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id")
        ->execute(['id' => $userId]);
}

function usersDeactivate(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = :id")
        ->execute(['id' => $userId]);
}

function usersDelete(PDO $pdo, int $userId): void
{
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $userId]);
}

function usersBuildListFilters(string $search = '', string $filterGroup = '', string $filterStatus = '', ?PDO $pdo = null): array
{
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $searchIpLike = '%' . $search . '%';
        $where[] = '(u.name LIKE :search_name OR u.email LIKE :search_email OR u.location LIKE :search_location OR u.last_login_ip LIKE :search_ip OR EXISTS (SELECT 1 FROM security_events se WHERE se.user_id = u.id AND se.ip_address LIKE :search_security_ip)' . (ctype_digit($search) ? ' OR u.id = :search_id' : '') . ')';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
        $params['search_location'] = $searchTerm;
        $params['search_ip'] = $searchIpLike;
        $params['search_security_ip'] = $searchIpLike;
        if (ctype_digit($search)) {
            $params['search_id'] = (int) $search;
        }
    }
    if ($filterGroup !== '') {
        if ($pdo && (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)]))) {
            $where[] = 'EXISTS (SELECT 1 FROM user_group_members ugmf WHERE ugmf.user_id = u.id AND ugmf.group_id = :group_id)';
        } else {
            $where[] = '0=1';
        }
        $params['group_id'] = (int) $filterGroup;
    }
    if ($filterStatus === 'banned') {
        $where[] = 'u.is_banned = 1';
    } elseif ($filterStatus === 'active') {
        $where[] = "(u.status = 'active' AND (u.is_banned = 0 OR u.is_banned IS NULL))";
    } elseif ($filterStatus === 'inactive') {
        $where[] = "u.status = 'inactive'";
    } elseif ($filterStatus === 'restricted') {
        $where[] = "EXISTS (SELECT 1 FROM user_restrictions WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW()))";
    }

    return [implode(' AND ', $where), $params];
}

function usersCountList(PDO $pdo, string $search = '', string $filterGroup = '', string $filterStatus = ''): int
{
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    [$whereStr, $params] = usersBuildListFilters($search, $filterGroup, $filterStatus, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereStr}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function usersGetList(PDO $pdo, string $search = '', string $filterGroup = '', string $filterStatus = '', int $limit = 50, int $offset = 0): array
{
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    [$whereStr, $params] = usersBuildListFilters($search, $filterGroup, $filterStatus, $pdo);
    $stmt = $pdo->prepare("SELECT u.*,\n                           (SELECT GROUP_CONCAT(DISTINCT restriction_type SEPARATOR ',')\n                            FROM user_restrictions\n                            WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW())) AS restrictions\n                           FROM users u\n                           WHERE {$whereStr}\n                           ORDER BY u.id ASC\n                           LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetStats(PDO $pdo): array
{
    usersPruneExpiredRestrictions($pdo);
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    $adminCountSql = "SELECT COUNT(DISTINCT m.user_id)\n            FROM user_group_members m\n            INNER JOIN user_groups g ON g.id = m.group_id\n            LEFT JOIN user_group_permissions p ON p.group_id = g.id AND p.permission_value = 1 AND p.permission_key IN ('*', 'admin.access')\n            WHERE g.is_active = 1 AND (g.slug = 'admin' OR p.permission_key IS NOT NULL)";
    return [
        'total' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND (is_banned = 0 OR is_banned IS NULL)")->fetchColumn(),
        'banned' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
        'admins' => (int) $pdo->query($adminCountSql)->fetchColumn(),
        'restricted' => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_restrictions WHERE expires_at IS NULL OR expires_at > NOW()")->fetchColumn(),
    ];
}

function usersGetById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT u.*\n                           FROM users u\n                           WHERE u.id = :id\n                           LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return usersDecorateUserWithPrimaryGroup($pdo, $user);
    }
    return $user;
}

function usersUpdateProfile(PDO $pdo, int $userId, array $data, int $currentUserId, array $validGroupIds): string
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $groupId = (int) ($data['group_id'] ?? 0);
    $status = trim((string) ($data['status'] ?? 'active'));
    $bio = trim((string) ($data['bio'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $github = trim((string) ($data['social_github'] ?? ''));
    $twitter = trim((string) ($data['social_twitter'] ?? ''));
    $discord = trim((string) ($data['social_discord'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($name === '' || $email === '') {
        return 'Ad ve e-posta zorunludur.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Geçerli bir e-posta adresi girin.';
    }
    if (!in_array($groupId, $validGroupIds, false)) {
        return 'Gecersiz grup.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        return 'Geçersiz durum.';
    }
    if ($userId === $currentUserId && !usersGroupGrantsAdmin($pdo, $groupId)) {
        return 'Kendi admin grubunuzu kaldiramazsiniz.';
    }
    if ($password !== '') {
        $policyError = validatePasswordPolicy($password, null, 'Şifre');
        if ($policyError !== '') {
            return $policyError;
        }
    }

    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $existsStmt->execute(['email' => $email, 'id' => $userId]);
    if ($existsStmt->fetch()) {
        return 'Bu e-posta adresi başka bir kullanıcıda kayıtlı.';
    }
$sql = "UPDATE users
            SET name = :name,
                email = :email,
                status = :status,
                bio = :bio,
                website = :website,
                location = :location,
                social_github = :github,
                social_twitter = :twitter,
                social_discord = :discord,
                updated_at = NOW()";
    $params = [
        'name' => $name,
        'email' => $email,
        'status' => $status,
        'bio' => $bio !== '' ? $bio : null,
        'website' => function_exists('profileNormalizeExternalUrl') ? (profileNormalizeExternalUrl($website) ?: null) : ($website !== '' ? $website : null),
        'location' => $location !== '' ? $location : null,
        'github' => function_exists('profileNormalizeSocialHandle') ? (profileNormalizeSocialHandle($github) ?: null) : ($github !== '' ? $github : null),
        'twitter' => function_exists('profileNormalizeSocialHandle') ? (profileNormalizeSocialHandle($twitter) ?: null) : ($twitter !== '' ? $twitter : null),
        'discord' => $discord !== '' ? $discord : null,
        'id' => $userId,
    ];

    if ($password !== '') {
        $sql .= ', password = :password';
        $params['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $groupError = usersSyncUserGroups($pdo, $userId, [$groupId], $currentUserId, 'admin_profile_group_update');
        if ($groupError !== '') {
            return $groupError;
        }
    }

    return '';
}

function usersApplyBulkAction(PDO $pdo, string $action, array $userIds, int $currentUserId, array $payload, array $validGroupIds): string
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
    if (empty($userIds)) {
        return 'Toplu işlem için en az bir kullanıcı seçin.';
    }

    if (in_array($currentUserId, $userIds, true) && in_array($action, ['ban', 'deactivate', 'delete'], true)) {
        return 'Kendi hesabınıza bu toplu işlemi uygulayamazsınız.';
    }

    switch ($action) {
        case 'activate':
            $stmt = $pdo->prepare('UPDATE users SET status = \"active\", updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                $stmt->execute([$id]);
            }
            return '';

        case 'deactivate':
            $stmt = $pdo->prepare('UPDATE users SET status = \"inactive\", updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$id]);
            }
            return '';

        case 'ban':
            $reason = trim((string) ($payload['ban_reason'] ?? ''));
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 1, banned_at = NOW(), ban_reason = ?, updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$reason, $id]);
            }
            return '';

        case 'unban':
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, banned_at = NULL, ban_reason = NULL, updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                $stmt->execute([$id]);
            }
            return '';

        case 'change_group':
            $groupId = (int) ($payload['group_id'] ?? 0);
            foreach ($userIds as $id) {
                $err = usersChangeGroup($pdo, $id, $groupId, $currentUserId, $validGroupIds);
                if ($err !== '') {
                    return $err;
                }
            }
            return '';

        case 'delete':
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$id]);
            }
            return '';
    }

    return 'Geçersiz toplu işlem.';
}

function usersGetRestrictionTypes(): array
{
    return [
        'all' => 'Tum Islemler',
        'comment' => 'Yorum',
        'topic' => 'Konu',
        'upload' => 'Yükleme',
        'message' => 'Mesaj',
        'download' => 'Indirme',
        'profile' => 'Profil',
        'events' => 'Etkinlik',
    ];
}

function usersAddRestriction(PDO $pdo, int $userId, string $restrictionType, ?string $reason = null, ?int $expiresInDays = null, ?int $adminId = null): void
{
    if (!array_key_exists($restrictionType, usersGetRestrictionTypes())) {
        $restrictionType = 'all';
    }

    $expiresAt = $expiresInDays && $expiresInDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days")) : null;
    $pdo->prepare("INSERT INTO user_restrictions (user_id, restriction_type, reason, expires_at, admin_id, created_at)
                   VALUES (:user_id, :type, :reason, :expires_at, :admin_id, NOW())")
        ->execute([
            'user_id' => $userId,
            'type' => $restrictionType,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'admin_id' => $adminId
        ]);
}

function usersRemoveRestriction(PDO $pdo, int $restrictionId): void
{
    $pdo->prepare("DELETE FROM user_restrictions WHERE id = :id")
        ->execute(['id' => $restrictionId]);
}

function usersRemoveAllRestrictions(PDO $pdo, int $userId): void
{
    $pdo->prepare("DELETE FROM user_restrictions WHERE user_id = :user_id")
        ->execute(['user_id' => $userId]);
}

function usersPruneExpiredRestrictions(PDO $pdo, int $limit = 100): int
{
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    try {
        $nowSql = function_exists('userActivityNowSql') ? userActivityNowSql($pdo) : 'NOW()';
        $stmt = $pdo->prepare("SELECT id, user_id, restriction_type, reason, expires_at FROM user_restrictions WHERE expires_at IS NOT NULL AND expires_at <= {$nowSql} ORDER BY expires_at ASC LIMIT ?");
        $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            if (function_exists('userActivityLog')) {
                userActivityLog($pdo, (int) $row['user_id'], 'user_restriction_expired', 'moderation', 'restriction', (int) $row['id'], 'Kisitlama suresi doldu', [
                    'restriction_type' => (string) $row['restriction_type'],
                    'reason' => (string) ($row['reason'] ?? ''),
                    'expires_at' => (string) ($row['expires_at'] ?? ''),
                ], null);
            }
            if (function_exists('usersDispatchAccountNotification')) {
                usersDispatchAccountNotification(
                    $pdo,
                    'user_restriction_removed',
                    (int) $row['user_id'],
                    null,
                    'Hesabinizdaki ' . usersGetRestrictionTypeLabel((string) $row['restriction_type']) . ' kisitlamasi suresi doldugu icin kaldirildi.',
                    'success'
                );
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delete = $pdo->prepare("DELETE FROM user_restrictions WHERE id IN ({$placeholders})");
        $delete->execute($ids);
        return count($ids);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersPruneExpiredRestrictions']);
        }
        return 0;
    }
}

function usersGetRestrictionsForUsers(PDO $pdo, array $userIds, bool $onlyActive = true): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
    if ($userIds === []) {
        return [];
    }

    if ($onlyActive) {
        usersPruneExpiredRestrictions($pdo);
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT r.*, u.name AS admin_name
            FROM user_restrictions r
            LEFT JOIN users u ON u.id = r.admin_id
            WHERE r.user_id IN ({$placeholders})";
    if ($onlyActive) {
        $sql .= " AND (r.expires_at IS NULL OR r.expires_at > NOW())";
    }
    $sql .= " ORDER BY r.user_id ASC, r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        if (!isset($map[$uid])) {
            $map[$uid] = [];
        }
        $map[$uid][] = $row;
    }

    return $map;
}

function usersGetRestrictions(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $map = usersGetRestrictionsForUsers($pdo, [$userId], true);
    return $map[$userId] ?? [];
}

function usersGetBannedList(PDO $pdo, string $search = ''): array
{
    $where = ['u.is_banned = 1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(u.name LIKE :search_name OR u.email LIKE :search_email)';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT u.* FROM users u
                           WHERE {$whereStr}
                           ORDER BY u.banned_at DESC
                           LIMIT 200");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetRestrictedList(PDO $pdo, string $search = ''): array
{
    usersPruneExpiredRestrictions($pdo);
    $where = ['EXISTS (SELECT 1 FROM user_restrictions WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW()))'];
    $params = [];

    if ($search !== '') {
        $where[] = '(u.name LIKE :search_name OR u.email LIKE :search_email)';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT u.* FROM users u
                           WHERE {$whereStr}
                           ORDER BY u.id ASC
                           LIMIT 200");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetRestrictionTypeLabel(string $type): string
{
    return match($type) {
        'all' => 'Tüm İşlemler',
        'comment' => 'Yorum Yapma',
        'topic' => 'Konu Oluşturma',
        'upload' => 'Dosya Yükleme',
        'download' => 'İndirme',
        'message' => 'Mesaj Gönderme',
        'profile' => 'Profil Duzenleme',
        'events' => 'Etkinlik Kullanimi',
        default => ucfirst($type)
    };
}

function usersHasRestriction(PDO $pdo, int $userId, string $restrictionType): bool
{
    return \App\Engine\Users\BanCheck::hasRestriction($pdo, $userId, $restrictionType);
}

function usersGetAccessRestriction(PDO $pdo, int $userId): ?array
{
    return \App\Engine\Users\BanCheck::accessRestriction($pdo, $userId);
}

function usersEnsureAdminNotesTable(PDO $pdo): void
{
    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    static $done = [];
    $key = spl_object_id($pdo);
    if (!empty($done[$key])) {
        return;
    }

    if (function_exists('userActivityIsSqlite') && userActivityIsSqlite($pdo)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_admin_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            admin_id INTEGER NULL,
            note TEXT NOT NULL,
            tone TEXT NOT NULL DEFAULT 'info',
            tags TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS user_admin_notes_user_created ON user_admin_notes (user_id, created_at)");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_admin_notes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            admin_id BIGINT UNSIGNED NULL,
            note TEXT NOT NULL,
            tone VARCHAR(20) NOT NULL DEFAULT 'info',
            tags VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_admin_notes_user_created (user_id, created_at),
            KEY user_admin_notes_admin_index (admin_id),
            CONSTRAINT user_admin_notes_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT user_admin_notes_admin_foreign FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $done[$key] = true;
}

function usersAddAdminNote(PDO $pdo, int $userId, int $adminId, string $note, string $tone = 'info', string $tags = ''): string
{
    $note = trim($note);
    if ($userId <= 0) {
        return 'Gecersiz kullanici.';
    }
    if (mb_strlen($note) < 3) {
        return 'Not en az 3 karakter olmalidir.';
    }
    if (mb_strlen($note) > 2000) {
        return 'Not en fazla 2000 karakter olabilir.';
    }
    if (!in_array($tone, ['info', 'warning', 'danger', 'success'], true)) {
        $tone = 'info';
    }

    usersEnsureAdminNotesTable($pdo);
    $nowSql = function_exists('userActivityNowSql') ? userActivityNowSql($pdo) : 'NOW()';
    $pdo->prepare("INSERT INTO user_admin_notes (user_id, admin_id, note, tone, tags, created_at, updated_at)
        VALUES (:user_id, :admin_id, :note, :tone, :tags, {$nowSql}, {$nowSql})")
        ->execute([
            'user_id' => $userId,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'note' => $note,
            'tone' => $tone,
            'tags' => trim($tags) !== '' ? mb_substr(trim($tags), 0, 255) : null,
        ]);

    if (function_exists('userActivityLog')) {
        userActivityLog($pdo, $userId, 'user_admin_note_added', 'note', 'user', $userId, 'Admin notu eklendi', [
            'tone' => $tone,
            'tags' => $tags,
        ], $adminId);
    }

    return '';
}

function usersGetAdminNotes(PDO $pdo, int $userId, int $limit = 20): array
{
    try {
        usersEnsureAdminNotesTable($pdo);
        $stmt = $pdo->prepare("SELECT n.*, a.name AS admin_name, a.email AS admin_email
            FROM user_admin_notes n
            LEFT JOIN users a ON a.id = n.admin_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersDispatchAccountNotification(
    PDO $pdo,
    string $eventKey,
    int $userId,
    ?int $actorId,
    string $message,
    string $type = 'info',
    string $link = '/notifications.php',
    ?int $entityId = null
): void {
    if (!function_exists('notificationDispatch') || $userId <= 0) {
        return;
    }

    if ($link === '/notifications.php' || $link === '/bildirimler') {
        $link = function_exists('routePublicStaticPath')
            ? '/' . ltrim((string) routePublicStaticPath('notifications'), '/')
            : '/notifications.php';
    }

    try {
        notificationDispatch($pdo, $eventKey, $userId, $actorId, 'user', $entityId ?? $userId, [
            'recipient_name' => 'Kullanici',
            'actor_name' => 'Yonetim',
            'moderation_note' => $message,
            'type' => $type,
            'link' => $link,
            'dedupe_key' => $eventKey . ':' . $userId . ':' . ($entityId ?? $userId) . ':' . substr(hash('sha1', $message . microtime(true)), 0, 10),
        ]);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersDispatchAccountNotification', 'event_key' => $eventKey]);
        }
    }
}

function usersBanAppealService(): BanAppealService
{
    static $service = null;

    return $service ??= new BanAppealService(
        new BanAppealSchemaService(),
        new BanAppealNotificationService()
    );
}

function usersEnsureBanAppealSchema(PDO $pdo): void
{
    usersBanAppealService()->ensureSchema($pdo);
}

function usersEnsureBanAppealMessagesTable(PDO $pdo): void
{
    usersBanAppealService()->ensureMessagesTable($pdo);
}

function usersSubmitBanAppeal(PDO $pdo, int $userId, string $message): string
{
    return usersBanAppealService()->submitForUser($pdo, $userId, $message);
}

function usersAddBanAppealMessage(PDO $pdo, int $appealId, ?int $senderUserId, string $senderType, string $message): string
{
    return usersBanAppealService()->addMessage($pdo, $appealId, $senderUserId, $senderType, $message);
}

function usersGetBanAppealMessages(PDO $pdo, int $appealId): array
{
    return usersBanAppealService()->messages($pdo, $appealId);
}

function usersBanAppealStatusLabel(string $status): string
{
    return usersBanAppealService()->statusLabel($status);
}

function usersCreateBanAppeal(PDO $pdo, int $userId, string $message): string
{
    return usersBanAppealService()->create($pdo, $userId, $message);
}

function usersGetBanAppealsForUser(PDO $pdo, int $userId): array
{
    return usersBanAppealService()->forUser($pdo, $userId);
}

function usersGetActiveBanAppealId(PDO $pdo, int $userId): ?int
{
    return usersBanAppealService()->activeId($pdo, $userId);
}

function usersGetBanAppealStats(PDO $pdo): array
{
    return usersBanAppealService()->stats($pdo);
}

function usersGetBanAppealsForAdmin(PDO $pdo, string $statusFilter = ''): array
{
    return usersBanAppealService()->forAdmin($pdo, $statusFilter);
}

function usersUpdateBanAppeal(PDO $pdo, int $appealId, string $status, string $adminNote, int $adminId): string
{
    return usersBanAppealService()->update($pdo, $appealId, $status, $adminNote, $adminId);
}

function usersRestrictedPathAllowed(string $path): bool
{
    return \App\Engine\Users\BanCheck::restrictedPathAllowed($path);
}
