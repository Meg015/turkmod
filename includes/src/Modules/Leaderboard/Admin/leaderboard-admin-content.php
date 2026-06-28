<?php

declare(strict_types=1);
$leaderboardProjectRoot = dirname(__DIR__, 5);

adminRequirePermission('leaderboard.view', 'Liderlik tablosunu görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('leaderboard.manage', 'Liderlik tablosu ayarlarını yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}

$pdo = requireDatabaseConnection($GLOBALS['pdo'] ?? null);
$GLOBALS['pdo'] = $pdo;
$baseUri = (string) ($GLOBALS['baseUri'] ?? '');

require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Legacy/helpers.php';
require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Legacy/cache-manager.php';

if (!\App\Engine\Users\PermissionChecker::can('leaderboard.admin', $pdo)) {
    adminRequirePermission('leaderboard.manage', 'Liderlik tablosu ayarlarını yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}

$pageTitle = 'Liderlik Tablosu Yönetimi';
$leaderboardSettingKeys = [
    'leaderboard_enabled',
    'leaderboard_disabled_message',
    'leaderboard_cache_ttl_daily',
    'leaderboard_cache_ttl_weekly',
    'leaderboard_cache_ttl_monthly',
    'leaderboard_exclude_admins',
    'leaderboard_show_sidebar',
    'leaderboard_sidebar_limit',
];
$adminDefinitions = adminSettingDefinitions();
$moduleMetadata = (new \App\Core\Modules\ModuleLoader())->load($leaderboardProjectRoot . '/includes/src/Modules/Leaderboard');
$moduleConfig = isset($moduleMetadata['config']) && is_array($moduleMetadata['config']) ? $moduleMetadata['config'] : [];
if ($moduleConfig !== []) {
    $adminDefinitions = array_replace($adminDefinitions, $moduleConfig);
}
$leaderboardSettingDefs = array_intersect_key($adminDefinitions, array_flip($leaderboardSettingKeys));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['_leaderboard_settings'] ?? '') === '1') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: leaderboard.php#leaderboard-settings');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:key, :value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $legacyStmt = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
            VALUES (:key, :value, :type, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type), updated_at = NOW()");

        foreach ($leaderboardSettingDefs as $key => $definition) {
            $type = (string)$definition['type'];
            if ($type === 'bool') {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value = trim((string)($_POST[$key] ?? $definition['default']));
            }

            if ($type === 'number') {
                $value = (string)max(0, (int)$value);
            }

            $stmt->execute(['key' => $key, 'value' => $value]);
            try {
                $legacyStmt->execute(['key' => $key, 'value' => $value, 'type' => $type]);
            } catch (Throwable $e) {
                appLogException($e, ['source' => 'admin/leaderboard.php', 'setting_key' => (string) $key]);
            }
        }

        invalidateAdminSettingsCache();
        logActivity($pdo, 'leaderboard_settings_updated', 'settings', null, ['keys' => array_keys($leaderboardSettingDefs)]);
        flash('success', 'Liderlik tablosu ayarları kaydedildi.');
        header('Location: leaderboard.php#leaderboard-settings');
        exit;
    } catch (Throwable $e) {
        flash('error', 'Ayarlar kaydedilemedi: ' . $e->getMessage());
    }
}

$adminSettings = getAdminSettings($pdo);

// Get cache status for all category/period combinations
$categories = array_map(
    static fn(array $category): string => $category['name'],
    leaderboardGetCategories()
);

$periods = [
    'daily' => 'Günlük',
    'weekly' => 'Haftalık',
    'monthly' => 'Aylık',
    'quarterly' => 'Çeyreklik',
    'yearly' => 'Yıllık'
];

// Get cache status for all combinations
$cacheStatus = [];
foreach ($categories as $catKey => $catLabel) {
    foreach ($periods as $periodKey => $periodLabel) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT user_id) as user_count,
                MAX(calculated_at) as last_updated,
                SUM(LENGTH(metadata)) as approx_size
            FROM leaderboard_cache
            WHERE category = ? AND period = ?
            AND period_start = (
                SELECT MAX(period_start)
                FROM leaderboard_cache
                WHERE category = ? AND period = ?
            )
        ");
        $stmt->execute([$catKey, $periodKey, $catKey, $periodKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $cacheStatus[] = [
            'category' => $catKey,
            'category_label' => $catLabel,
            'period' => $periodKey,
            'period_label' => $periodLabel,
            'user_count' => (int)($row['user_count'] ?? 0),
            'last_updated' => $row['last_updated'] ?? null,
            'approx_size' => (int)($row['approx_size'] ?? 0),
            'is_stale' => leaderboardIsCacheStale($pdo, $catKey, $periodKey)
        ];
    }
}

// Get activity log (recent calculations)
$stmt = $pdo->prepare("
    SELECT
        action,
        subject_type,
        subject_id,
        properties,
        created_at,
        actor_id
    FROM activity_logs
    WHERE action LIKE 'leaderboard_%'
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute();
$activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(DISTINCT u.id) as total_users
    FROM users u
    WHERE NOT EXISTS (
        SELECT 1
        FROM user_group_members m
        INNER JOIN user_group_permissions p ON p.group_id = m.group_id
        WHERE m.user_id = u.id
          AND p.permission_key IN ('*', 'admin.access')
          AND p.permission_value = 1
    )");
$totalUsers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_cached FROM leaderboard_cache");
$totalCached = (int)$stmt->fetchColumn();

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once $leaderboardProjectRoot . '/admin/header.php';
?>
<div class="leaderboard-page">
<div class="ui-admin-page-hero">
    <div class="ui-admin-page-hero-text">
        <h2 class="ui-admin-mb-xs"><i class="bi bi-trophy ui-admin-icon-gap"></i>Liderlik Tablosu Kontrol Paneli</h2>
        <p class="ui-admin-m-0">Topluluk Performansı: Cache sağlığını izleyin, periyotları yenileyin ve sıralama hesaplamalarını tek ekrandan yönetin.</p>
    </div>

</div>

<div class="leaderboard-admin-tabs" role="tablist" aria-label="Liderlik tablosu yonetimi">
    <button type="button" class="leaderboard-admin-tab is-active" data-tab-target="leaderboard-status" role="tab" aria-selected="true">
        <i class="bi bi-speedometer2"></i> Durum
    </button>
    <button type="button" class="leaderboard-admin-tab" data-tab-target="leaderboard-settings" role="tab" aria-selected="false">
        <i class="bi bi-sliders"></i> Ayarlar
    </button>
</div>

<section id="leaderboard-status" class="leaderboard-admin-tab-panel is-active ui-panel" role="tabpanel">
<div class="admin-card leaderboard-admin-panel ui-panel">
    <div class="card-header ui-panel__head">
        <i class="bi bi-activity me-2"></i>Sistem Ozeti
    </div>
    <div class="card-body ui-panel__body">
        <?php if ($successMsg): ?>
            <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <div class="admin-stat-grid leaderboard-admin-stats ui-grid">
            <div class="admin-stat-card stat-info leaderboard-admin-stat ui-card">
                <div class="stat-icon"><i class="bi bi-database"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Toplam Cache Kayıtları</span>
                    <span class="stat-value"><?= number_format($totalCached) ?></span>
                </div>
            </div>
            <div class="admin-stat-card stat-success leaderboard-admin-stat ui-card">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Toplam Kullanıcı</span>
                    <span class="stat-value"><?= number_format($totalUsers) ?></span>
                </div>
            </div>
            <div class="admin-stat-card stat-warning leaderboard-admin-stat ui-card">
                <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Kategori Sayısı</span>
                    <span class="stat-value"><?= count($categories) ?></span>
                </div>
            </div>
            <div class="admin-stat-card stat-info leaderboard-admin-stat ui-card">
                <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Periyot Sayısı</span>
                    <span class="stat-value"><?= count($periods) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card leaderboard-admin-panel ui-panel">
    <div class="card-header ui-panel__head ui-admin-header-split">
        <div>
            <i class="bi bi-table me-2"></i>Cache Durumu
        </div>
        <div class="ui-admin-action-row ui-admin-action-row-compact">
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" id="recalculateAllBtn">
                <i class="bi bi-arrow-repeat"></i> Tümünü Yeniden Hesapla
            </button>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" id="clearAllCacheBtn">
                <i class="bi bi-trash"></i> Tüm Cache'i Temizle
            </button>
        </div>
    </div>
    <div class="card-body ui-panel__body">
        <div
            class="leaderboard-admin-table-wrap"
            data-leaderboard-admin-config="<?= htmlspecialchars(json_encode([
                'apiBase' => rtrim($baseUri, '/') . '/admin/api',
                'categories' => array_keys($categories),
                'periods' => array_keys($periods),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        >
            <table class="ui-admin-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Periyot</th>
                        <th>Son Güncelleme</th>
                        <th>Kullanıcı Sayısı</th>
                        <th>Boyut (yaklaşık)</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cacheStatus as $status): ?>
                        <tr data-category="<?= htmlspecialchars($status['category']) ?>" data-period="<?= htmlspecialchars($status['period']) ?>">
                            <td><strong><?= htmlspecialchars($status['category_label']) ?></strong></td>
                            <td><?= htmlspecialchars($status['period_label']) ?></td>
                            <td>
                                <?php if ($status['last_updated']): ?>
                                    <span title="<?= htmlspecialchars($status['last_updated']) ?>">
                                        <?= date('d.m.Y H:i', strtotime($status['last_updated'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="ui-admin-table-cell-secondary">Hiç hesaplanmadı</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($status['user_count']) ?></td>
                            <td><?= number_format($status['approx_size'] / 1024, 2) ?> KB</td>
                            <td>
                                <?php if ($status['is_stale']): ?>
                                    <span class="ui-admin-badge ui-admin-badge-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Eski
                                    </span>
                                <?php else: ?>
                                    <span class="ui-admin-badge ui-admin-badge-success">
                                        <i class="bi bi-check-circle"></i> Güncel
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="leaderboard-admin-actions">
                                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline recalculate-btn"
                                            data-category="<?= htmlspecialchars($status['category']) ?>"
                                            data-period="<?= htmlspecialchars($status['period']) ?>">
                                        <i class="bi bi-arrow-repeat"></i> Hesapla
                                    </button>
                                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger clear-cache-btn"
                                            data-category="<?= htmlspecialchars($status['category']) ?>"
                                            data-period="<?= htmlspecialchars($status['period']) ?>">
                                        <i class="bi bi-trash"></i> Temizle
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="admin-card leaderboard-admin-panel ui-panel">
    <div class="card-header ui-panel__head">
        <i class="bi bi-clock-history me-2"></i>Son Aktiviteler
    </div>
    <div class="card-body ui-panel__body">
        <?php if (empty($activityLog)): ?>
            <p class="ui-admin-muted-centered">Henüz aktivite kaydı yok.</p>
        <?php else: ?>
            <div class="leaderboard-admin-table-wrap">
                <table class="ui-admin-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>İşlem</th>
                            <th>Detaylar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLog as $log): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <?php
                                    $actionLabels = [
                                        'leaderboard_recalculated' => 'Yeniden Hesaplandı',
                                        'leaderboard_cache_cleared' => 'Cache Temizlendi',
                                        'leaderboard_triggered' => 'Tetiklendi',
                                        'leaderboard_settings_updated' => 'Ayarlar Güncellendi'
                                    ];
                                    echo htmlspecialchars($actionLabels[$log['action']] ?? $log['action']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $properties = json_decode($log['properties'] ?? '{}', true);
                                    if (!empty($properties)) {
                                        echo '<code class="ui-admin-code-sm">' . htmlspecialchars(json_encode($properties, JSON_UNESCAPED_UNICODE)) . '</code>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</section>

<section id="leaderboard-settings" class="leaderboard-admin-tab-panel ui-panel" role="tabpanel" hidden>
    <form method="post" action="leaderboard.php#leaderboard-settings" class="admin-card leaderboard-admin-panel leaderboard-settings-card ui-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="_leaderboard_settings" value="1">
        <div class="card-header ui-panel__head">
            <i class="bi bi-sliders me-2"></i>Liderlik Ayarlari
        </div>
        <div class="card-body ui-panel__body">
            <div class="leaderboard-settings-intro">
                <div>
                    <h3>Gorunum ve cache davranisi</h3>
                    <p>Bu ayarlar sayac tabanli liderlik tablosunun gorunurlugunu ve cache surelerini yonetir.</p>
                </div>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary">
                    <i class="bi bi-check2-circle"></i> Ayarlari Kaydet
                </button>
            </div>

            <div class="leaderboard-settings-grid ui-grid">
                <?php foreach ($leaderboardSettingDefs as $key => $definition): ?>
                    <?php
                    $type = (string)$definition['type'];
                    $label = (string)$definition['label'];
                    $tooltip = (string)($definition['tooltip'] ?? '');
                    $value = (string)($adminSettings[$key] ?? $definition['default']);
                    ?>
                    <div class="leaderboard-setting-item<?= $type === 'textarea' ? ' leaderboard-setting-item--wide' : '' ?>">
                        <?php if ($type === 'bool'): ?>
                            <label class="ui-admin-switch leaderboard-setting-switch">
                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label"><?= htmlspecialchars($label) ?></span>
                            </label>
                        <?php elseif ($type === 'textarea'): ?>
                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                            <textarea
                                id="<?= htmlspecialchars($key) ?>"
                                name="<?= htmlspecialchars($key) ?>"
                                class="ui-admin-form-control leaderboard-disabled-message"
                                rows="4"
                            ><?= htmlspecialchars($value) ?></textarea>
                        <?php else: ?>
                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                            <input
                                id="<?= htmlspecialchars($key) ?>"
                                name="<?= htmlspecialchars($key) ?>"
                                type="number"
                                min="0"
                                class="ui-admin-form-control"
                                value="<?= htmlspecialchars($value) ?>"
                            >
                        <?php endif; ?>

                        <?php if ($tooltip !== ''): ?>
                            <p><?= htmlspecialchars($tooltip) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</section>

</div>

<script src="<?= asset_url('admin/assets/leaderboard-page.js', $baseUri) ?>" defer></script>

<?php require_once $leaderboardProjectRoot . '/admin/footer.php'; ?>

