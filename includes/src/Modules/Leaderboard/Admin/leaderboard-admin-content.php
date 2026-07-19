<?php

declare(strict_types=1);
$leaderboardProjectRoot = dirname(__DIR__, 5);

adminRequirePermission('leaderboard.admin', 'Liderlik tablosunu yönetmek için gerekli izin hesabınıza tanımlanmamış.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('leaderboard.admin', 'Liderlik tablosu ayarlarını yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}

$pdo = requireDatabaseConnection($GLOBALS['pdo'] ?? null);
$GLOBALS['pdo'] = $pdo;
$baseUri = (string) ($GLOBALS['baseUri'] ?? '');
$leaderboardAdminUrl = rtrim($baseUri, '/') . '/admin/leaderboard';
$canManageLeaderboard = function_exists('adminCurrentUserCan') && adminCurrentUserCan('leaderboard.admin');
$leaderboardActivityUrl = static function (int $page) use ($leaderboardAdminUrl): string {
    $page = max(1, $page);
    return $leaderboardAdminUrl . ($page > 1 ? '?activity_page=' . $page : '') . '#leaderboard-status';
};

require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Support/helpers.php';
require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Support/cache-manager.php';

adminRequirePermission('leaderboard.admin', 'Liderlik tablosu ayarlarını yönetmek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Liderlik Tablosu Yönetimi';
$leaderboardSettingKeys = [
    'leaderboard_enabled',
    'leaderboard_disabled_message',
    'leaderboard_cache_ttl_daily',
    'leaderboard_cache_ttl_weekly',
    'leaderboard_cache_ttl_monthly',
    'leaderboard_cache_ttl_quarterly',
    'leaderboard_cache_ttl_yearly',
    'leaderboard_cache_ttl_all_time',
    'leaderboard_exclude_admins',
    'leaderboard_show_sidebar',
    'leaderboard_sidebar_limit',
    'leaderboard_show_profile',
    'leaderboard_profile_limit',
    'leaderboard_exclude_banned',
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
        header('Location: ' . $leaderboardAdminUrl . '#leaderboard-settings');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:key, :value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
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
        }

        invalidateAdminSettingsCache();
        logActivity($pdo, 'leaderboard_settings_updated', 'settings', null, ['keys' => array_keys($leaderboardSettingDefs)]);
        if (function_exists('adminAuditLogger')) {
            adminAuditLogger()->logAction($pdo, 'leaderboard_settings_updated', 'settings', 0, 'Liderlik tablosu ayarları güncellendi', [], ['keys' => array_keys($leaderboardSettingDefs)], false);
        }
        flash('success', 'Liderlik tablosu ayarları kaydedildi.');
        header('Location: ' . $leaderboardAdminUrl . '#leaderboard-settings');
        exit;
    } catch (Throwable $e) {
        flash('error', 'Ayarlar kaydedilemedi: ' . $e->getMessage());
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['_leaderboard_activity_action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: ' . $leaderboardActivityUrl(max(1, (int) ($_POST['activity_page'] ?? 1))));
        exit;
    }

    $activityLogId = max(0, (int) ($_POST['activity_log_id'] ?? 0));
    $activityPage = max(1, (int) ($_POST['activity_page'] ?? 1));
    if ($activityLogId <= 0) {
        flash('error', 'Silinecek kayıt seçilmedi.');
        header('Location: ' . $leaderboardActivityUrl($activityPage));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, action, subject_type, subject_id, properties, created_at
            FROM activity_logs
            WHERE id = :id AND action LIKE 'leaderboard_%'
            LIMIT 1
        ");
        $stmt->execute(['id' => $activityLogId]);
        $activityRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$activityRow) {
            flash('error', 'Silinecek kayıt bulunamadı.');
            header('Location: ' . $leaderboardActivityUrl($activityPage));
            exit;
        }

        $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = :id AND action LIKE 'leaderboard_%' LIMIT 1");
        $deleteStmt->execute(['id' => $activityLogId]);
        if ($deleteStmt->rowCount() < 1) {
            flash('error', 'Kayıt silinemedi.');
            header('Location: ' . $leaderboardActivityUrl($activityPage));
            exit;
        }

        if (function_exists('adminAuditLogger')) {
            try {
                adminAuditLogger()->logAction(
                    $pdo,
                    'delete',
                    'leaderboard',
                    $activityLogId,
                    'Son aktivite kaydı silindi',
                    [
                        'id' => (int) ($activityRow['id'] ?? 0),
                        'action' => (string) ($activityRow['action'] ?? ''),
                        'subject_type' => (string) ($activityRow['subject_type'] ?? ''),
                        'subject_id' => (int) ($activityRow['subject_id'] ?? 0),
                        'properties' => (string) ($activityRow['properties'] ?? ''),
                        'created_at' => (string) ($activityRow['created_at'] ?? ''),
                    ],
                    [],
                    false
                );
            } catch (Throwable $auditException) {
                if (function_exists('appLogException')) {
                    appLogException($auditException, [
                        'source' => 'admin/leaderboard',
                        'action' => 'leaderboard_activity_delete',
                        'activity_id' => $activityLogId,
                    ]);
                }
            }
        }

        flash('success', 'Aktivite kaydı silindi.');
        header('Location: ' . $leaderboardActivityUrl($activityPage));
        exit;
    } catch (Throwable $e) {
        flash('error', 'Aktivite kaydı silinemedi: ' . $e->getMessage());
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['_leaderboard_activity_action'] ?? '') === 'bulk_delete_all') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: ' . $leaderboardActivityUrl(max(1, (int) ($_POST['activity_page'] ?? 1))));
        exit;
    }

    $activityPage = max(1, (int) ($_POST['activity_page'] ?? 1));

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action LIKE 'leaderboard_%'");
        $stmt->execute();
        $deletedCount = (int) $stmt->fetchColumn();

        if ($deletedCount < 1) {
            flash('error', 'Silinecek kayıt bulunamadı.');
            header('Location: ' . $leaderboardActivityUrl($activityPage));
            exit;
        }

        $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE action LIKE 'leaderboard_%'");
        $deleteStmt->execute();
        $deletedCount = (int) $deleteStmt->rowCount();
        if ($deletedCount < 1) {
            flash('error', 'Kayıtlar silinemedi.');
            header('Location: ' . $leaderboardActivityUrl($activityPage));
            exit;
        }

        if (function_exists('adminAuditLogger')) {
            try {
                adminAuditLogger()->logAction(
                    $pdo,
                    'bulk_delete',
                    'leaderboard',
                    0,
                    'Son aktivite kayıtları tamamen silindi',
                    [
                        'count' => $deletedCount,
                        'page' => $activityPage,
                    ],
                    [],
                    false
                );
            } catch (Throwable $auditException) {
                if (function_exists('appLogException')) {
                    appLogException($auditException, [
                        'source' => 'admin/leaderboard',
                        'action' => 'leaderboard_activity_clear_all',
                        'count' => $deletedCount,
                    ]);
                }
            }
        }

        flash('success', $deletedCount . ' aktivite kaydı silindi.');
        header('Location: ' . $leaderboardActivityUrl(1));
        exit;
    } catch (Throwable $e) {
        flash('error', 'Aktivite kayıtları silinemedi: ' . $e->getMessage());
    }
}

$adminSettings = getAdminSettings($pdo);

// Get cache status for all category/period combinations

$categories = leaderboardGetCategories();

$periods = [
    'daily' => 'Günlük',
    'weekly' => 'Haftalık',
    'monthly' => 'Aylık',
    'quarterly' => 'Çeyreklik',
    'yearly' => 'Yıllık',
    'all_time' => 'Tüm Zamanlar'
];

// Get cache status for all combinations
$cacheStatus = [];
foreach ($categories as $catKey => $catData) {
    $catLabel = (string) ($catData['name'] ?? $catKey);
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
        $userCount = (int) ($row['user_count'] ?? 0);
        $lastUpdated = $row['last_updated'] ?? null;
        $isEmpty = $lastUpdated === null && $userCount === 0;
        $isStale = !$isEmpty && leaderboardIsCacheStale($pdo, $catKey, $periodKey);

        $cacheStatus[] = [
            'category' => $catKey,
            'category_label' => $catLabel,
            'period' => $periodKey,
            'period_label' => $periodLabel,
            'user_count' => $userCount,
            'last_updated' => $lastUpdated,
            'approx_size' => (int)($row['approx_size'] ?? 0),
            'is_empty' => $isEmpty,
            'is_stale' => $isStale,
            'status_label' => $isEmpty ? 'Veri yok' : ($isStale ? 'Güncel değil' : 'Güncel'),
            'status_title' => $isEmpty
                ? 'Bu kategori ve dönem için henüz veri oluşmadı.'
                : ($isStale ? 'Önbellek süresi doldu veya yeniden hesaplama bekliyor.' : 'Önbellek güncel.')
        ];
    }
}

// Get activity log (recent calculations)
$activityPage = max(1, (int) ($_GET['activity_page'] ?? 1));
$activityPerPage = function_exists('adminPaginationPerPage') ? adminPaginationPerPage() : 10;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action LIKE 'leaderboard_%'");
$stmt->execute();
$activityTotal = (int) $stmt->fetchColumn();
$activityTotalPages = max(1, (int) ceil($activityTotal / $activityPerPage));
$activityPage = min($activityPage, $activityTotalPages);
$activityOffset = ($activityPage - 1) * $activityPerPage;

$stmt = $pdo->prepare("
    SELECT
        id,
        action,
        subject_type,
        subject_id,
        properties,
        created_at,
        actor_id
    FROM activity_logs
    WHERE action LIKE 'leaderboard_%'
    ORDER BY created_at DESC, id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue('limit', $activityPerPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $activityOffset, PDO::PARAM_INT);
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
<?= adminRenderPageHero(
    'bi-trophy',
    'Liderlik tablosu',
    'Liderlik Tablosu Kontrol Paneli',
    'Topluluk Performansı: Önbellek sağlığını izleyin, periyotları yenileyin ve sıralama hesaplamalarını tek ekrandan yönetin.'
) ?>

<?= adminRenderButtonTabs([
    'leaderboard-status' => [
        'target' => 'leaderboard-status',
        'icon' => 'bi-speedometer2',
        'label' => 'Durum',
    ],
    'leaderboard-settings' => [
        'target' => 'leaderboard-settings',
        'icon' => 'bi-sliders',
        'label' => 'Ayarlar',
    ],
], 'leaderboard-status', [
    'class' => 'leaderboard-admin-tabs',
    'button_class' => 'leaderboard-admin-tab',
    'aria_label' => 'Liderlik tablosu yönetimi',
]) ?>

<section id="leaderboard-status" class="leaderboard-admin-tab-panel is-active ui-panel" role="tabpanel">
<?= adminRenderPanelOpen([
    'tag' => 'div',
    'class' => 'leaderboard-admin-panel',
    'title' => 'Sistem Özeti',
    'icon' => 'bi-activity',
]) ?>
    <?= adminRenderFlashAlerts($successMsg, $errorMsg) ?>
    <?= adminRenderStatCards([
        [
            'tone' => 'info',
            'icon' => 'bi-database',
            'label' => 'Toplam Önbellek Kayıtları',
            'value' => number_format($totalCached),
            'class' => 'leaderboard-admin-stat',
        ],
        [
            'tone' => 'success',
            'icon' => 'bi-people-fill',
            'label' => 'Toplam Kullanıcı',
            'value' => number_format($totalUsers),
            'class' => 'leaderboard-admin-stat',
        ],
        [
            'tone' => 'warning',
            'icon' => 'bi-folder2-open',
            'label' => 'Kategori Sayısı',
            'value' => count($categories),
            'class' => 'leaderboard-admin-stat',
        ],
        [
            'tone' => 'info',
            'icon' => 'bi-calendar-range',
            'label' => 'Periyot Sayısı',
            'value' => count($periods),
            'class' => 'leaderboard-admin-stat',
        ],
    ], [
        'class' => 'leaderboard-admin-stats',
        'aria_label' => 'Liderlik sistemi özeti',
    ]) ?>
<?= adminRenderPanelClose('div') ?>

<?= adminRenderPanelOpen([
    'tag' => 'div',
    'class' => 'leaderboard-admin-panel',
    'title' => 'Önbellek Durumu',
    'icon' => 'bi-table',
    'header_class' => 'ui-admin-header-split',
    'actions_html' => adminRenderActionButtons([
        [
            'label' => 'Tümünü Yeniden Hesapla',
            'icon' => 'bi-arrow-repeat',
            'class' => 'ui-admin-btn-sm ui-admin-btn-outline',
            'attrs' => ['id' => 'recalculateAllBtn'],
        ],
        [
            'label' => 'Tüm Önbelleği Temizle',
            'icon' => 'bi-trash',
            'class' => 'ui-admin-btn-sm ui-admin-btn-danger',
            'attrs' => ['id' => 'clearAllCacheBtn'],
        ],
    ], ['class' => 'ui-admin-action-row ui-admin-action-row-compact']),
]) ?>
    <?= adminRenderTableOpen([
        'Kategori',
        'Periyot',
        'Son Güncelleme',
        'Kullanıcı Sayısı',
        'Boyut (yaklaşık)',
        'Durum',
        'İşlemler',
    ], [
        'wrap_class' => 'leaderboard-admin-table-wrap',
        'wrap_attrs' => [
            'data-leaderboard-admin-config' => json_encode([
                'apiBase' => rtrim($baseUri, '/') . '/admin/api',
                'categories' => array_keys($categories),
                'periods' => array_keys($periods),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}',
        ],
        'label' => 'Liderlik önbellek durumu',
    ]) ?>
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
                                <?php
                                if (!empty($status['is_empty'])) {
                                    echo adminRenderBadge('Veri yok', [
                                        'tone' => 'muted',
                                        'icon' => 'bi-dash-circle',
                                        'title' => (string) ($status['status_title'] ?? ''),
                                    ]);
                                } elseif ($status['is_stale']) {
                                    echo adminRenderBadge('Güncel değil', [
                                        'tone' => 'warning',
                                        'icon' => 'bi-exclamation-triangle',
                                        'title' => (string) ($status['status_title'] ?? ''),
                                    ]);
                                } else {
                                    echo adminRenderBadge('Güncel', [
                                        'tone' => 'success',
                                        'icon' => 'bi-check-circle',
                                        'title' => (string) ($status['status_title'] ?? ''),
                                    ]);
                                }
                                ?>
                            </td>
                            <td>
                                <?= adminRenderActionButtons([
                                    [
                                        'label' => 'Hesapla',
                                        'icon' => 'bi-arrow-repeat',
                                        'class' => 'ui-admin-btn-sm ui-admin-btn-outline recalculate-btn',
                                        'attrs' => [
                                            'data-category' => $status['category'],
                                            'data-period' => $status['period'],
                                        ],
                                    ],
                                    [
                                        'label' => 'Temizle',
                                        'icon' => 'bi-trash',
                                        'class' => 'ui-admin-btn-sm ui-admin-btn-danger clear-cache-btn',
                                        'attrs' => [
                                            'data-category' => $status['category'],
                                            'data-period' => $status['period'],
                                        ],
                                    ],
                                ], ['class' => 'leaderboard-admin-actions']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
    <?= adminRenderTableClose() ?>
<?= adminRenderPanelClose('div') ?>

<?= adminRenderPanelOpen([
    'tag' => 'div',
    'class' => 'leaderboard-admin-panel',
    'title' => 'Son Aktiviteler',
    'icon' => 'bi-clock-history',
    'header_class' => 'ui-admin-header-split',
    'actions_html' => '<span class="ui-admin-count-pill">' . number_format($activityTotal) . '</span>',
]) ?>
        <?php if (empty($activityLog)): ?>
            <?= adminRenderEmptyState([
                'icon' => 'bi-clock-history',
                'tone' => 'muted',
                'title' => 'Henüz aktivite kaydı yok',
                'description' => 'Liderlik tablosu işlemleri başladığında son aktiviteler burada listelenir.',
            ]) ?>
        <?php else: ?>
            <?php if ($canManageLeaderboard): ?>
                <form
                    id="leaderboardActivityClearAllForm"
                    method="post"
                    action="<?= htmlspecialchars($leaderboardAdminUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="leaderboard-activity-bulk-bar"
                    data-admin-confirm="Son aktivite kayıtlarının tamamı kalıcı olarak silinecek. Devam edilsin mi?"
                    data-admin-confirm-title="Tüm aktiviteler silinsin mi?"
                    data-admin-confirm-ok="Sil"
                    data-admin-confirm-tone="danger"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="_leaderboard_activity_action" value="bulk_delete_all">
                    <input type="hidden" name="activity_page" value="<?= $activityPage ?>">

                    <div class="leaderboard-activity-bulk-copy">
                        <strong>Toplu silme</strong>
                        <span>Son aktivite kayıtlarının tamamını siler.</span>
                    </div>

                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm">
                        <i class="bi bi-trash"></i> Tümünü Sil
                    </button>
                </form>
            <?php endif; ?>
            <?php
            $activityHeaders = ['Tarih', 'İşlem', 'Detaylar'];
            if ($canManageLeaderboard) {
                $activityHeaders[] = 'İşlemler';
            }
            ?>
            <?= adminRenderTableOpen($activityHeaders, [
                'wrap_class' => 'leaderboard-admin-table-wrap',
                'label' => 'Liderlik son aktiviteler',
            ]) ?>
                        <?php foreach ($activityLog as $log): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <?php
                                    $actionLabels = [
                                        'leaderboard_recalculated' => 'Yeniden Hesaplandı',
                                        'leaderboard_cache_cleared' => 'Önbellek Temizlendi',
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
                                <?php if ($canManageLeaderboard): ?>
                                    <td>
                                        <form method="post" action="<?= htmlspecialchars($leaderboardAdminUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form" data-admin-confirm="Bu aktivite kaydı kalıcı olarak silinecek. Devam edilsin mi?" data-admin-confirm-title="Aktivite kaydı silinsin mi?" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_leaderboard_activity_action" value="delete">
                                            <input type="hidden" name="activity_log_id" value="<?= (int) $log['id'] ?>">
                                            <input type="hidden" name="activity_page" value="<?= $activityPage ?>">
                                            <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm">
                                                <i class="bi bi-trash"></i> Sil
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
            <?php if ($activityTotalPages > 1): ?>
                <?= adminRenderPagination($activityTotalPages, $activityPage, $leaderboardActivityUrl, [
                    'wrapper_class' => 'ui-admin-pagination-center leaderboard-activity-pagination',
                    'inner_class' => 'leaderboard-activity-pagination-list',
                    'aria_label' => 'Liderlik aktivite sayfalama',
                ]) ?>
            <?php endif; ?>
        <?php endif; ?>
<?= adminRenderPanelClose('div') ?>
</section>

<section id="leaderboard-settings" class="leaderboard-admin-tab-panel ui-panel" role="tabpanel" hidden>
    <?= adminRenderPanelOpen([
        'tag' => 'form',
        'class' => 'leaderboard-admin-panel leaderboard-settings-card',
        'attrs' => [
            'method' => 'post',
            'action' => $leaderboardAdminUrl . '#leaderboard-settings',
        ],
        'title' => 'Liderlik Ayarları',
        'icon' => 'bi-sliders',
    ]) ?>
        <?= csrf_field() ?>
        <input type="hidden" name="_leaderboard_settings" value="1">
            <div class="leaderboard-settings-intro">
                <div>
                    <h3>Görünüm ve önbellek davranışı</h3>
                    <p>Bu ayarlar sayaç tabanlı liderlik tablosunun görünürlüğünü ve önbellek sürelerini yönetir.</p>
                </div>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary">
                    <i class="bi bi-check2-circle"></i> Ayarları Kaydet
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
    <?= adminRenderPanelClose('form') ?>
</section>

</div>

<script src="<?= asset_url('admin/assets/leaderboard-page.js', $baseUri) ?>" defer></script>

<?php require_once $leaderboardProjectRoot . '/admin/footer.php'; ?>



