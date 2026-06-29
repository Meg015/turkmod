<?php

declare(strict_types=1);
// $baseUri init.php'den geliyor, fallback
if (!isset($baseUri)) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseUri = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
    if ($baseUri === '/' || $baseUri === '.') {
        $baseUri = '';
    }
}
$pageTitle = $pageTitle ?? 'Admin Paneli';
$currentPath = $_SERVER['SCRIPT_NAME'];
$isDashboard = str_contains($currentPath, 'admin/index.php');
$isQueue = str_contains($currentPath, 'admin/queue.php');
$isTopics = str_contains($currentPath, 'admin/topics.php') || str_contains($currentPath, 'admin/edit.php');
$isCreate = str_contains($currentPath, 'admin/create.php');
$isCategories = str_contains($currentPath, 'admin/categories.php');
$isSettings = str_contains($currentPath, 'admin/settings.php');
$isMediaManager = str_contains($currentPath, 'admin/media-manager.php');
$isUsers = str_contains($currentPath, 'admin/users.php');
$isUserActivity = str_contains($currentPath, 'admin/user-activity.php');
$isSystemHealth = str_contains($currentPath, 'admin/system-health.php');
$isDatabaseSync = str_contains($currentPath, 'admin/database-sync');
$isLogs = str_contains($currentPath, 'admin/logs.php');
$isActionLog = str_contains($currentPath, 'admin/action-log.php');
$isRateLimits = str_contains($currentPath, 'admin/rate-limits.php');
$isAppearance = str_contains($currentPath, 'admin/appearance.php');
$isThemes = str_contains($currentPath, 'admin/themes.php');
$isScraper = str_contains($currentPath, 'admin/scraper.php');
$isLegacyRedirects = str_contains($currentPath, 'admin/legacy-redirects.php');
$isReports = str_contains($currentPath, 'admin/complaints-reports.php') || str_contains($currentPath, 'admin/reports.php') || str_contains($currentPath, 'admin/user-reports.php');
$isCommentsManager = str_contains($currentPath, 'admin/comments-manager.php');
$isContacts = str_contains($currentPath, 'admin/contacts.php');
$isLeaderboard = str_contains($currentPath, 'admin/leaderboard.php');
$isEvents = str_contains($currentPath, 'admin/events');
$isNotifications = str_contains($currentPath, 'admin/notifications.php');

$userName = $_SESSION['_auth_user_name'] ?? 'Admin';
$adminCan = static function (array|string $permissions): bool {
    return function_exists('adminCurrentUserCan') ? adminCurrentUserCan($permissions) : true;
};
$adminHomeHref = $baseUri . '/admin/index.php';
if (!$adminCan('dashboard.view')) {
    foreach ([
        ['queue.view', '/admin/queue.php'],
        ['topics.view', '/admin/topics.php'],
        ['categories.view', '/admin/categories.php'],
        ['comments.view', '/admin/comments-manager.php'],
        ['reports.view', '/admin/complaints-reports.php'],
        ['scraper.view', '/admin/scraper.php'],
        ['events.view', '/admin/events.php'],
        ['settings.view', '/admin/settings.php'],
        ['users.view', '/admin/users.php'],
        ['groups.view', '/admin/users.php?tab=groups'],
        ['logs.view', '/admin/logs.php'],
    ] as [$permission, $path]) {
        if ($adminCan($permission)) {
            $adminHomeHref = $baseUri . $path;
            break;
        }
    }
}

// Sidebar pending work badge — lightweight count from cache-friendly sources
$sidebarQueueCount = 0;
$sidebarReportsCount = 0;
$sidebarContactCount = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $counts = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM topic_reports WHERE status IN ('open','reviewing')) AS topic_reports,
                (SELECT COUNT(*) FROM user_reports WHERE status IN ('open','reviewing')) AS user_reports,
                (SELECT COUNT(*) FROM topics WHERE status = 'draft' AND deleted_at IS NULL) AS pending_topics
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $topicReports = (int) ($counts['topic_reports'] ?? 0);
        $userReports  = (int) ($counts['user_reports'] ?? 0);
        $pendingTopics = (int) ($counts['pending_topics'] ?? 0);
        $sidebarReportsCount = $topicReports + $userReports;
        $sidebarQueueCount   = $topicReports + $userReports + $pendingTopics;
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        if (function_exists('usersGetBanAppealStats')) {
            $a = usersGetBanAppealStats($pdo);
            $sidebarQueueCount += (int) (($a['open'] ?? 0) + ($a['reviewing'] ?? 0));
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        if (function_exists('contactOpenMessageCount')) {
            $sidebarContactCount = (int) contactOpenMessageCount($pdo);
        } else {
            $sidebarContactCount = 0;
        }
    } catch (Throwable $e) {
        $sidebarContactCount = 0;
        error_log('[silent-catch] ' . $e->getMessage());
    }
}
$adminThemeSettings = [];
$adminThemeMode = 'auto';
if (function_exists('getAdminSettings') && isset($pdo) && $pdo instanceof PDO) {
    $adminThemeSettings = getAdminSettings($pdo);
    $adminThemeMode = $adminThemeSettings['dark_mode'] ?? 'auto';
}
if (!in_array($adminThemeMode, ['auto', 'light', 'dark'], true)) {
    $adminThemeMode = 'auto';
}
$adminSiteName = trim((string) ($adminThemeSettings['site_name'] ?? ''));
if ($adminSiteName === '') {
    $adminSiteName = 'İçerik Topic';
}
$adminBrandSetting = trim((string) ($adminThemeSettings['header_brand_text'] ?? ''));
$adminBrandText = ($adminBrandSetting !== '' && $adminBrandSetting !== 'İçerik Topic') ? $adminBrandSetting : $adminSiteName;
$adminBrandIcon = $adminThemeSettings['header_brand_icon'] ?? 'M';
$adminAccentColor = trim((string) ($adminThemeSettings['accent_color'] ?? ''));
$adminAccentColor = function_exists('uiCssColorValue') ? uiCssColorValue($adminAccentColor) : $adminAccentColor;
$adminStyleBridge = $adminAccentColor !== ''
    ? "--brand-accent:{$adminAccentColor};--ui-admin-accent:{$adminAccentColor};--ui-admin-primary:{$adminAccentColor};--primary:{$adminAccentColor}"
    : '';
?>
<!doctype html>
<html lang="tr" data-theme-mode="<?= htmlspecialchars($adminThemeMode) ?>"<?= $adminStyleBridge !== '' ? ' data-ui-style-color="' . htmlspecialchars($adminStyleBridge, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-base-uri" content="<?= htmlspecialchars($baseUri, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($adminSiteName) ?> Admin</title>
    <link rel="icon" href="<?= $baseUri ?>/assets/favicon.svg" type="image/svg+xml">
    <script src="<?= asset_url('admin/assets/admin-ui.js', $baseUri) ?>"></script>
    <script src="<?= asset_url('assets/js/ui-foundation.js', $baseUri) ?>" defer></script>
    <link rel="stylesheet" href="<?= asset_url('assets/css/roboto-local.css', $baseUri) ?>">
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet" integrity="sha384-cPa8kzsYWhqpAfWOLWYIw3V0BhPi/m3lrd8tBTPxr2NrYCHRVZ7xy1cEoRGOM/03" crossorigin="anonymous">
    <?php if (!$isEvents): ?>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet" integrity="sha384-ww0rVASXMoKHk188bKXg4fIZUrd5s80drZJWVP6tgTtu4AskG/wVqDnOEmhjJhvp" crossorigin="anonymous">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset_url('assets/css/design-tokens.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/ui-foundation.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('admin/assets/admin.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('admin/assets/admin-foundation.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/bootstrap-icons.css', $baseUri) ?>">
    <?php if ($isMediaManager): ?>
    <link rel="stylesheet" href="<?= asset_url('admin/assets/media-manager.css', $baseUri) ?>">
    <?php endif; ?>
    <?php if (!$isEvents): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js" integrity="sha384-/Wx1NuqlgALfa1Do1U6Mer7quEDHOo8REf/0izoIrV8Y3Z/gtEHQc01STCEMM1LZ" crossorigin="anonymous"></script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
</head>
<body>
    <div id="admin-overlay" class="admin-overlay"></div>
    <div class="admin-shell ui-section">
        <aside id="admin-sidebar" class="admin-sidebar">
            <a class="admin-brand" href="<?= htmlspecialchars($adminHomeHref, ENT_QUOTES, 'UTF-8') ?>">
                <span class="admin-brand-mark"><?php if (str_starts_with($adminBrandIcon, 'bi-')): ?><i class="bi <?= htmlspecialchars($adminBrandIcon) ?>"></i><?php else: ?><?= htmlspecialchars($adminBrandIcon) ?><?php endif; ?></span>
                <span><?= htmlspecialchars($adminBrandText) ?></span>
            </a>
            <nav class="admin-menu" aria-label="Admin menüsü">
                <section class="admin-menu-group">
                    <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
                        <span>Ana Menü</span><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-menu-group-body ui-panel__body">
                        <?php if ($adminCan('dashboard.view')): ?><a class="admin-menu-item <?= $isDashboard ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/index.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a><?php endif; ?>
                        <?php if ($adminCan('queue.view')): ?><a class="admin-menu-item <?= $isQueue ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/queue.php"><i class="bi bi-inbox-fill"></i><span>Bekleyen İşler</span><?php if ($sidebarQueueCount > 0): ?><span class="admin-menu-badge"><?= $sidebarQueueCount > 99 ? '99+' : $sidebarQueueCount ?></span><?php endif; ?></a><?php endif; ?>
                    </div>
                </section>
                <section class="admin-menu-group">
                    <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
                        <span>İçerik Yönetimi</span><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-menu-group-body ui-panel__body">
                        <?php if ($adminCan('topics.view')): ?><a class="admin-menu-item <?= $isTopics ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/topics.php"><i class="bi bi-files"></i><span>Konular</span></a><?php endif; ?>
                        <?php if ($adminCan('topics.create')): ?><a class="admin-menu-item <?= $isCreate ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/create.php"><i class="bi bi-plus-circle"></i><span>Yeni Konu</span></a><?php endif; ?>
                        <?php if ($adminCan('categories.view')): ?><a class="admin-menu-item <?= $isCategories ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/categories.php"><i class="bi bi-diagram-3"></i><span>Kategoriler</span></a><?php endif; ?>
                        <?php if ($adminCan('comments.view')): ?><a class="admin-menu-item <?= $isCommentsManager ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/comments-manager.php"><i class="bi bi-chat-left-text"></i><span>Yorumlar</span></a><?php endif; ?>
                        <?php if ($adminCan('reports.view')): ?><a class="admin-menu-item <?= $isReports ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/complaints-reports.php"><i class="bi bi-shield-exclamation"></i><span>Şikayetler &amp; Raporlar</span><?php if ($sidebarReportsCount > 0): ?><span class="admin-menu-badge"><?= $sidebarReportsCount > 99 ? '99+' : $sidebarReportsCount ?></span><?php endif; ?></a><?php endif; ?>
                        <?php if ($adminCan('contact.view')): ?><a class="admin-menu-item <?= $isContacts ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/contacts.php"><i class="bi bi-envelope-paper"></i><span>İletişim</span><?php if (!empty($sidebarContactCount)): ?><span class="admin-menu-badge"><?= $sidebarContactCount > 99 ? '99+' : $sidebarContactCount ?></span><?php endif; ?></a><?php endif; ?>
                    </div>
                </section>
                <section class="admin-menu-group">
                    <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
                        <span>Araçlar</span><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-menu-group-body ui-panel__body">
                        <?php if ($adminCan('scraper.view')): ?><a class="admin-menu-item <?= $isScraper ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/scraper.php"><i class="bi bi-robot"></i><span>İçerik Botu</span></a><?php endif; ?>
                        <?php if ($adminCan('events.view')): ?><a class="admin-menu-item <?= $isEvents ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/events.php"><i class="bi bi-stars"></i><span>Etkinlikler</span></a><?php endif; ?>
                        <?php if ($adminCan('legacy_redirects.view')): ?><a class="admin-menu-item <?= $isLegacyRedirects ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/legacy-redirects.php"><i class="bi bi-signpost-split"></i><span>SEO Yönlendirmeleri</span></a><?php endif; ?>
                    </div>
                </section>
                <section class="admin-menu-group">
                    <button class="admin-menu-group-toggle" type="button" aria-expanded="true">
                        <span>Sistem</span><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="admin-menu-group-body ui-panel__body">
                        <?php if ($adminCan('settings.view')): ?><a class="admin-menu-item <?= $isSettings ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/settings.php"><i class="bi bi-sliders"></i><span>Genel Ayarlar</span></a><?php endif; ?>
                        <?php if ($adminCan('appearance.view')): ?><a class="admin-menu-item <?= $isAppearance ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/appearance.php"><i class="bi bi-palette2"></i><span>Görünüm</span></a><?php endif; ?>
                        <?php if ($adminCan('themes.view')): ?><a class="admin-menu-item <?= $isThemes ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/themes.php"><i class="bi bi-brush"></i><span>Temalar</span></a><?php endif; ?>
                        <?php if ($adminCan(['users.view', 'groups.view'])): ?><a class="admin-menu-item <?= $isUsers ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/users.php"><i class="bi bi-people"></i><span>Kullanıcılar</span></a><?php endif; ?>
                        <?php if ($adminCan('notifications.view')): ?><a class="admin-menu-item <?= $isNotifications ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/notifications.php"><i class="bi bi-bell"></i><span>Bildirim Merkezi</span></a><?php endif; ?>
                        <?php if ($adminCan('leaderboard.view')): ?><a class="admin-menu-item <?= $isLeaderboard ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/leaderboard.php"><i class="bi bi-trophy"></i><span>Liderlik Tablosu</span></a><?php endif; ?>
                        <?php if ($adminCan('media.view')): ?><a class="admin-menu-item <?= $isMediaManager ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/media-manager.php"><i class="bi bi-folder2-open"></i><span>Dosya Yöneticisi</span></a><?php endif; ?>
                        <?php if ($adminCan('system.view')): ?><a class="admin-menu-item <?= $isSystemHealth ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/system-health.php"><i class="bi bi-clipboard2-pulse"></i><span>Sistem Sağlığı</span></a><?php endif; ?>
                        <?php if ($adminCan('logs.view')): ?><a class="admin-menu-item <?= $isLogs ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/logs.php"><i class="bi bi-journal-text"></i><span>Aktivite Logları</span></a><?php endif; ?>
                        <?php if ($adminCan('logs.view')): ?><a class="admin-menu-item <?= $isActionLog ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/action-log.php"><i class="bi bi-clock-history"></i><span>İşlem Günlüğü</span></a><?php endif; ?>
                        <?php if ($adminCan('rate_limits.view')): ?><a class="admin-menu-item <?= $isRateLimits ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/rate-limits.php"><i class="bi bi-speedometer"></i><span>Rate Limit İzleme</span></a><?php endif; ?>
                        <?php if ($adminCan('system.manage')): ?><a class="admin-menu-item <?= $isDatabaseSync ? 'active' : '' ?>" href="<?= $baseUri ?>/admin/database-sync/index.php"><i class="bi bi-database-check"></i><span>Veritabanı Senkronizasyonu</span></a><?php endif; ?>
                    </div>
                </section>
            </nav>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-title-row">
                    <button id="admin-hamburger-btn" class="admin-hamburger" type="button" aria-label="Menüyü aç"><i class="bi bi-list"></i></button>
                    <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                <div class="admin-topbar-actions">
                    <button class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-sm admin-theme-toggle" id="theme-toggle" title="Tema Değiştir">
                        <i class="bi bi-sun-fill" id="theme-icon"></i>
                    </button>
                    <div class="admin-topbar-profile-dropdown" id="admin-profile-dropdown">
                        <button class="admin-profile-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Kullanıcı Menüsü">
                            <span class="admin-avatar-mini">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'))) ?>
                            </span>
                            <span class="admin-profile-name"><?= htmlspecialchars($userName) ?></span>
                            <i class="bi bi-chevron-down admin-profile-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="admin-profile-menu" role="menu">
                            <div class="admin-profile-menu-header">
                                <div class="admin-profile-header-avatar">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'))) ?>
                                </div>
                                <div class="admin-profile-header-info">
                                    <span class="admin-menu-user-name"><?= htmlspecialchars($userName) ?></span>
                                    <span class="admin-menu-user-role">Yönetici</span>
                                </div>
                            </div>
                            <div class="admin-profile-menu-divider"></div>
                            <a class="admin-profile-menu-item" href="<?= $baseUri ?>/admin/user-edit.php?id=<?= (int)($_SESSION['_auth_user_id'] ?? 0) ?>" role="menuitem">
                                <i class="bi bi-person-gear"></i>
                                <span>Profilimi Düzenle</span>
                            </a>
                            <a class="admin-profile-menu-item" href="<?= $baseUri ?>/index.php" target="_blank" role="menuitem">
                                <i class="bi bi-box-arrow-up-right"></i>
                                <span>Siteyi Gör</span>
                            </a>
                            <div class="admin-profile-menu-divider"></div>
                            <form method="post" action="<?= $baseUri ?>/logout.php" class="admin-profile-logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="admin-profile-menu-item logout-action" role="menuitem">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Çıkış Yap</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>
            <main class="admin-content ui-section">
