<?php

declare(strict_types=1);
// $baseUri normalde init.php'den gelir; erken include durumunda burada hazırlanır.
if (!isset($baseUri)) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseUri = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
    if ($baseUri === '/' || $baseUri === '.') {
        $baseUri = '';
    }
}
$pageTitle = $pageTitle ?? 'Admin Paneli';

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
    <?php
    // Sayfa bazında conditional asset yüklemeleri için route map kontrolü
    $isMediaManagerPage = str_contains($_SERVER['SCRIPT_NAME'], 'admin/media-manager.php');
    ?>
    <link rel="stylesheet" href="<?= asset_url('assets/css/design-tokens.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/ui-foundation.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('admin/assets/admin.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('admin/assets/admin-foundation.css', $baseUri) ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/bootstrap-icons.css', $baseUri) ?>">
    <?php if ($isMediaManagerPage): ?>
    <link rel="stylesheet" href="<?= asset_url('admin/assets/media-manager.css', $baseUri) ?>">
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
            <?php require __DIR__ . '/sidebar.php'; ?>
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
