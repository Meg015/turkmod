<?php

declare(strict_types=1);

/**
 * Sidebar menü tanımları ve render mantığı.
 * `$baseUri`, `$adminCan` ve sidebar sayımları header.php'den gelir.
 */

// ---------------------------------------------------------------------------
// 1. Yol eşleme tablosu — her sayfanın hangi menü anahtarına ait olduğunu belirler
// ---------------------------------------------------------------------------
$menuRouteMap = [
    'admin/index.php'                   => 'dashboard',
    'admin/queue.php'                   => 'queue',

    // İçerik Yönetimi
    'admin/topics.php'                  => 'topics',
    'admin/edit.php'                    => 'topics',
    'admin/create.php'                  => 'create',
    'admin/categories.php'              => 'categories',
    'admin/comments-manager.php'        => 'comments',
    'admin/complaints-reports.php'      => 'reports',
    'admin/reports.php'                 => 'reports',
    'admin/user-reports.php'            => 'reports',
    'admin/contacts.php'                => 'contacts',

    // Araçlar
    'admin/scraper.php'                 => 'scraper',
    'admin/events.php'                  => 'events',
    'admin/events-pending.php'          => 'events',
    'admin/events-raffles.php'          => 'events',
    'admin/events-rewards.php'          => 'events',
    'admin/events-settings.php'         => 'events',
    'admin/events-stats.php'            => 'events',
    'admin/events-tasks.php'            => 'events',
    'admin/events-wheel.php'            => 'events',
    'admin/events-audit-log.php'        => 'events',
    'admin/legacy-redirects.php'        => 'legacy-redirects',

    // Günlükler (alt sekmeler sayfa içinde render edilir, sidebar tek link)
    'admin/logs.php'                    => 'logs',
    'admin/application-logs.php'        => 'logs',
    'admin/action-log.php'              => 'logs',
    'admin/rate-limits.php'             => 'logs',

    // Sistem
    'admin/settings.php'                => 'settings',
    'admin/appearance.php'              => 'appearance',
    'admin/themes.php'                  => 'themes',
    'admin/users.php'                   => 'users',
    'admin/user-activity.php'           => 'users',
    'admin/user-edit.php'               => 'users',
    'admin/notifications.php'           => 'notifications',
    'admin/leaderboard.php'             => 'leaderboard',
    'admin/media-manager.php'           => 'media',
    'admin/system-health.php'           => 'system-health',
    'admin/database-sync/index.php'     => 'database-sync',
];

// Geçerli aktif sayfayı route map'ten bul
// $baseUri prefix'ini kaldır (örn. /yenidosyalar/admin/logs.php → admin/logs.php)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePrefix = rtrim(str_replace('\\', '/', $baseUri ?? ''), '/');
$relativePath = $basePrefix !== '' && str_starts_with($scriptName, $basePrefix . '/')
    ? ltrim(substr($scriptName, strlen($basePrefix) + 1), '/')
    : ltrim($scriptName, '/');
$activeKey = $menuRouteMap[$relativePath] ?? '';

/**
 * Bir menü öğesinin aktif olup olmadığını kontrol eder.
 */
function sidebarIsActive(string $key): bool {
    global $activeKey;
    return $activeKey === $key;
}

/**
 * Aktifse 'active' class'ını döndürür.
 */
function sidebarActiveClass(string $key): string {
    return sidebarIsActive($key) ? ' active' : '';
}

/**
 * Badge HTML'i render eder.
 */
function sidebarBadge(?int $count, int $max = 99): string {
    if ($count === null || $count <= 0) return '';
    $label = $count > $max ? $max . '+' : (string) $count;
    return ' <span class="admin-menu-badge">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

// ---------------------------------------------------------------------------
// 2. Menü grupları
// ---------------------------------------------------------------------------
?>
<nav class="admin-menu" aria-label="Admin menüsü">

    <!-- Ana Menü -->
    <section class="admin-menu-group">
        <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
            <span>Ana Menü</span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="admin-menu-group-body ui-panel__body">
            <?php if ($adminCan('dashboard.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('dashboard') ?>" href="<?= $baseUri ?>/admin/index.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <?php endif; ?>
            <?php if ($adminCan('queue.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('queue') ?>" href="<?= $baseUri ?>/admin/queue.php"><i class="bi bi-inbox-fill"></i><span>Bekleyen İşler</span><?= sidebarBadge($sidebarQueueCount) ?></a>
            <?php endif; ?>
        </div>
    </section>

    <!-- İçerik Yönetimi -->
    <section class="admin-menu-group">
        <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
            <span>İçerik Yönetimi</span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="admin-menu-group-body ui-panel__body">
            <?php if ($adminCan('topics.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('topics') ?>" href="<?= $baseUri ?>/admin/topics.php"><i class="bi bi-files"></i><span>Konular</span></a>
            <?php endif; ?>
            <?php if ($adminCan('topics.create')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('create') ?>" href="<?= $baseUri ?>/admin/create.php"><i class="bi bi-plus-circle"></i><span>Yeni Konu</span></a>
            <?php endif; ?>
            <?php if ($adminCan('categories.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('categories') ?>" href="<?= $baseUri ?>/admin/categories.php"><i class="bi bi-diagram-3"></i><span>Kategoriler</span></a>
            <?php endif; ?>
            <?php if ($adminCan('comments.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('comments') ?>" href="<?= $baseUri ?>/admin/comments-manager.php"><i class="bi bi-chat-left-text"></i><span>Yorumlar</span></a>
            <?php endif; ?>
            <?php if ($adminCan('reports.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('reports') ?>" href="<?= $baseUri ?>/admin/complaints-reports.php"><i class="bi bi-shield-exclamation"></i><span>Şikayetler & Raporlar</span><?= sidebarBadge($sidebarReportsCount) ?></a>
            <?php endif; ?>
            <?php if ($adminCan('contact.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('contacts') ?>" href="<?= $baseUri ?>/admin/contacts.php"><i class="bi bi-envelope-paper"></i><span>İletişim</span><?= sidebarBadge($sidebarContactCount) ?></a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Araçlar -->
    <section class="admin-menu-group">
        <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
            <span>Araçlar</span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="admin-menu-group-body ui-panel__body">
            <?php if ($adminCan('scraper.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('scraper') ?>" href="<?= $baseUri ?>/admin/scraper.php"><i class="bi bi-robot"></i><span>İçerik Botu</span></a>
            <?php endif; ?>
            <?php if ($adminCan('events.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('events') ?>" href="<?= $baseUri ?>/admin/events.php"><i class="bi bi-stars"></i><span>Etkinlikler</span></a>
            <?php endif; ?>
            <?php if ($adminCan('legacy_redirects.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('legacy-redirects') ?>" href="<?= $baseUri ?>/admin/legacy-redirects.php"><i class="bi bi-signpost-split"></i><span>SEO Yönlendirmeleri</span></a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Günlükler (tek link, alt sekmeler sayfa içinde) -->
    <?php if ($adminCan('logs.view') || $adminCan('rate_limits.view')): ?>
        <div class="admin-menu-group admin-menu-group-standalone">
            <div class="admin-menu-group-body ui-panel__body">
                <a class="admin-menu-item<?= sidebarActiveClass('logs') ?>" href="<?= $baseUri ?>/admin/logs.php"><i class="bi bi-journal-text"></i><span>Günlükler</span></a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sistem -->
    <section class="admin-menu-group">
        <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
            <span>Sistem</span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="admin-menu-group-body ui-panel__body">
            <?php if ($adminCan('settings.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('settings') ?>" href="<?= $baseUri ?>/admin/settings.php"><i class="bi bi-sliders"></i><span>Genel Ayarlar</span></a>
            <?php endif; ?>
            <?php if ($adminCan('appearance.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('appearance') ?>" href="<?= $baseUri ?>/admin/appearance.php"><i class="bi bi-palette2"></i><span>Görünüm</span></a>
            <?php endif; ?>
            <?php if ($adminCan('themes.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('themes') ?>" href="<?= $baseUri ?>/admin/themes.php"><i class="bi bi-brush"></i><span>Temalar</span></a>
            <?php endif; ?>
            <?php if ($adminCan(['users.view', 'groups.view'])): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('users') ?>" href="<?= $baseUri ?>/admin/users.php"><i class="bi bi-people"></i><span>Kullanıcılar</span></a>
            <?php endif; ?>
            <?php if ($adminCan('notifications.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('notifications') ?>" href="<?= $baseUri ?>/admin/notifications.php"><i class="bi bi-bell"></i><span>Bildirim Merkezi</span></a>
            <?php endif; ?>
            <?php if ($adminCan('leaderboard.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('leaderboard') ?>" href="<?= $baseUri ?>/admin/leaderboard.php"><i class="bi bi-trophy"></i><span>Liderlik Tablosu</span></a>
            <?php endif; ?>
            <?php if ($adminCan('media.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('media') ?>" href="<?= $baseUri ?>/admin/media-manager.php"><i class="bi bi-folder2-open"></i><span>Dosya Yöneticisi</span></a>
            <?php endif; ?>
            <?php if ($adminCan('system.view')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('system-health') ?>" href="<?= $baseUri ?>/admin/system-health.php"><i class="bi bi-clipboard2-pulse"></i><span>Sistem Sağlığı</span></a>
            <?php endif; ?>
            <?php if ($adminCan('system.manage')): ?>
                <a class="admin-menu-item<?= sidebarActiveClass('database-sync') ?>" href="<?= $baseUri ?>/admin/database-sync/index.php"><i class="bi bi-database-check"></i><span>Veritabanı Senkronizasyonu</span></a>
            <?php endif; ?>
        </div>
    </section>

</nav>
