<?php

declare(strict_types=1);
// $baseUri, $pdo ve $isLoggedIn normalde init.php'den gelir; doğrudan include/lint durumları için güvenli varsayılanlar.
$envConfig = $envConfig ?? [];
$baseUri = $baseUri ?? "";
$pdo = $pdo ?? null;
$isLoggedIn = !empty($_SESSION['_auth_user_id']) || (bool) ($isLoggedIn ?? false);

$appName = $envConfig["APP_NAME"] ?? "İçerik Topic";
$pageTitle = $pageTitle ?? "Ana Sayfa";
$metaDescription =
    $metaDescription ??
    "Topluluk dosyaları, güncellemeler, araçlar ve oyun eklentileri.";
$publicCategories = isset($publicCategories) && is_array($publicCategories)
    ? $publicCategories
    : (function_exists("getPublicCategories")
        ? getPublicCategories($pdo)
        : []);
$publicCategoriesTree = isset($publicCategoriesTree) && is_array($publicCategoriesTree)
    ? $publicCategoriesTree
    : (function_exists("getPublicCategoriesTree")
        ? getPublicCategoriesTree($pdo)
        : []);

// Görünüm ayarları
$_lay = isset($_lay) && is_array($_lay)
    ? $_lay
    : (isset($settings) && is_array($settings)
        ? $settings
        : (function_exists("getAdminSettings") && $pdo
            ? getAdminSettings($pdo)
            : []));
$siteNameSetting = trim((string) ($_lay["site_name"] ?? ""));
if ($siteNameSetting !== "") {
    $appName = $siteNameSetting;
}
$_hBrandSetting = trim((string) ($_lay["header_brand_text"] ?? ""));
$_hBrand = ($_hBrandSetting !== "" && $_hBrandSetting !== "İçerik Topic") ? $_hBrandSetting : $appName;
$_hIcon = $_lay["header_brand_icon"] ?? "M";
$_hSearch = ($_lay["header_show_search"] ?? "1") === "1";
$_hSearchPh = $_lay["header_search_placeholder"] ?? "İçerik ara...";
$_hAuth = ($_lay["header_show_auth_buttons"] ?? "1") === "1";
$_hProfile = ($_lay["header_show_profile_btn"] ?? "1") === "1";
$_hAdmin = ($_lay["header_show_admin_btn"] ?? "1") === "1";
$_hSticky = ($_lay["header_sticky"] ?? "1") === "1";
$_hBg = $_lay["header_bg_color"] ?? "";
$_hTxt = $_lay["header_text_color"] ?? "";
$_hAccent = $_lay["header_accent_color"] ?? "";
$_hBorder = $_lay["header_border_color"] ?? "";
$_hTopbar = ($_lay["header_topbar_enabled"] ?? "0") === "1";
$_hTopbarTxt = $_lay["header_topbar_text"] ?? "";
$_hTopbarBg = $_lay["header_topbar_bg"] ?? (defined('BRAND_TOPBAR_BG_DEFAULT') ? BRAND_TOPBAR_BG_DEFAULT : "#0f172a");
$_hCustomCss = trim($_lay["header_custom_css"] ?? "");
$_publicCustomCss = trim($_lay["custom_css"] ?? "");
$_faviconUrl = trim($_lay["favicon_url"] ?? "");
$_logoUrl = trim($_lay["logo_url"] ?? "");
$_defaultAccentColor = defined('BRAND_DEFAULT_ACCENT') ? BRAND_DEFAULT_ACCENT : "#8b1538";
$_legacyAccentColor = defined('BRAND_LEGACY_ACCENT') ? BRAND_LEGACY_ACCENT : "#f2a51a";
$_normalizeDefaultAccent = static function (?string $color) use ($_defaultAccentColor, $_legacyAccentColor): string {
    $color = trim((string) $color);
    if ($color === "" || strtolower($color) === $_legacyAccentColor) {
        return $_defaultAccentColor;
    }
    return $color;
};
$_hAccent = $_normalizeDefaultAccent($_hAccent);
$_hBorder = $_normalizeDefaultAccent($_hBorder);
$_accentColor = $_normalizeDefaultAccent($_lay["accent_color"] ?? "");
$_secondaryColor = trim($_lay["secondary_color"] ?? "");
$_fontFamily = $_lay["font_family"] ?? "roboto";
$_themeMode = $_lay["dark_mode"] ?? "auto";
if (!in_array($_themeMode, ["auto", "light", "dark"], true)) {
    $_themeMode = "auto";
}
$_themeManager = $GLOBALS["themeManager"] ?? null;
$_activePublicTheme = $_themeManager instanceof ThemeManager
    ? $_themeManager->activeThemeId()
    : (string) ($_lay["theme_active_id"] ?? "default");
$_themeAssetIsolated = $_themeManager instanceof ThemeManager
    ? $_themeManager->isAssetIsolated($_activePublicTheme)
    : false;
if (!preg_match('/^[a-z0-9_-]+$/', $_activePublicTheme)) {
    $_activePublicTheme = "default";
}
if (!in_array($_fontFamily, ["roboto"], true)) {
    $_fontFamily = "roboto";
}
$_siteLanguage = $_lay["site_language"] ?? "tr";

$_publicSettingAsset = static function (string $url) use ($baseUri): string {
    if ($url === "") {
        return "";
    }
    if (preg_match('~^(https?:)?//|^data:|^/~i', $url) === 1) {
        return $url;
    }
    return rtrim($baseUri, "/") . "/" . ltrim($url, "/");
};
$_faviconHref = $_publicSettingAsset($_faviconUrl);
$_logoSrc = $_publicSettingAsset($_logoUrl);
if ($_themeAssetIsolated && $_themeManager instanceof ThemeManager) {
    $_faviconHref = $_themeManager->assetUrl($_activePublicTheme, "images/favicon.ico");
}

// Menü ayarları
$_menuItems = trim($_lay["menu_items"] ?? "");

$_uploadTopicUrl = routePublicStaticUrl("upload_topic");
$_notificationsUrl = routePublicStaticUrl("notifications");
$_messagesUrl = routePublicStaticUrl("messages");
$_logoutUrl = routePublicStaticUrl("logout");
$_loginBaseHref = routePublicStaticUrl("login");
$_registerHref = routePublicStaticUrl("register");
$_forgotPasswordHref = routePublicStaticUrl("forgot_password");

$_menuShowCats = ($_lay["menu_show_categories"] ?? "0") === "1";
$_menuCatLimit = (int) ($_lay["menu_category_limit"] ?? 8);
$_menuCta = ($_lay["menu_cta_enabled"] ?? "0") === "1";
$_menuCtaTxt = $_lay["menu_cta_text"] ?? "İçerik Yükle";
$_menuCtaUrl = $_lay["menu_cta_url"] ?? "";
$_menuCtaClr = $_normalizeDefaultAccent($_lay["menu_cta_color"] ?? "");
$_menuCtaIco = $_lay["menu_cta_icon"] ?? "bi-cloud-arrow-up";

$_fontStacks = [
    "roboto" => "'Roboto',sans-serif",
];

$_currentScript = basename((string) ($_SERVER["SCRIPT_NAME"] ?? ""));
$_currentRequestUri = (string) ($_SERVER["REQUEST_URI"] ?? ($baseUri . "/index.php"));
$_isAuthPage = function_exists("routeIsAuthPage")
    ? routeIsAuthPage($_currentScript)
    : in_array($_currentScript, ["login.php", "register.php", "forgot-password.php", "reset-password.php", "giris", "kayit", "sifremi-unuttum", "sifre-sifirla"], true);
$_loginHref = $_loginBaseHref . (!$_isAuthPage ? "?redirect=" . rawurlencode($_currentRequestUri) : "");
$_pageCssMap = [];
$_extraPageCssFiles = isset($pageCssFiles) && is_array($pageCssFiles)
    ? $pageCssFiles
    : [];
$_pageCssFiles = array_values(
    array_unique(array_merge($_pageCssMap[$_currentScript] ?? [], $_extraPageCssFiles)),
);
$_themeBodyClass = "public-theme-" . $_activePublicTheme;
$bodyClass = trim((string) ($bodyClass ?? "") . " " . $_themeBodyClass);
if (!$_hSticky) {
    $bodyClass = trim($bodyClass . " public-header-static");
}
$_publicCssBundle = __DIR__ . "/../assets/dist/public.min.css";
$_publicJsBundle = __DIR__ . "/../assets/dist/public.min.js";

if ($_isAuthPage && function_exists('sendNoStoreHeaders')) {
    sendNoStoreHeaders();
}

if (
    $_themeManager instanceof ThemeManager
    && $_themeManager->usesPublicRenderer()
    && class_exists(PublicThemeRenderer::class)
    && PublicThemeRenderer::start([
        "theme_manager" => $_themeManager,
        "base_uri" => $baseUri,
        "pdo" => $pdo,
        "settings" => $_lay,
        "env_config" => $envConfig,
        "app_name" => $appName,
        "page_title" => $pageTitle,
        "meta_description" => $metaDescription,
        "is_logged_in" => $isLoggedIn,
        "site_language" => $_siteLanguage,
        "theme_mode" => $_themeMode,
        "body_class" => $bodyClass,
        "current_script" => $_currentScript,
        "current_request_uri" => $_currentRequestUri,
        "public_categories" => $publicCategories,
        "public_categories_tree" => $publicCategoriesTree,
        "sidebar_items" => $sidebarItems ?? [],
        "recent_comments" => $recentComments ?? [],
        "page_vars" => isset($publicThemePageVars) && is_array($publicThemePageVars)
            ? $publicThemePageVars
            : get_defined_vars(),
    ])
) {
    return;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($_siteLanguage) ?>" data-theme-mode="<?= htmlspecialchars($_themeMode) ?>" data-public-theme="<?= htmlspecialchars($_activePublicTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script<?= function_exists('appCspNonceAttr') ? appCspNonceAttr() : '' ?>><?php
// Inline theme-mode-init — sıfır HTTP isteği, anında çalışır
try {
    $themeInitCode = @file_get_contents(__DIR__ . '/../assets/js/theme-mode-init.js');
    if ($themeInitCode !== false) {
        // Remove wrapping IIFE — content is already a self-executing function
        echo $themeInitCode;
    } else {
        // Fallback minimal inline version
        echo '(function(){var d=document.documentElement,m=d.getAttribute("data-theme-mode")||"auto",s=window.localStorage?localStorage.getItem("theme-mode")||localStorage.getItem("theme"):"",t=s||m;t=t==="light"||t==="dark"?t:"auto";var h=t==="auto"?window.matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light":t;d.setAttribute("data-theme",h);d.setAttribute("data-theme-mode",t);d.setAttribute("data-bs-theme",h);})();';
    }
} catch (Throwable $_) {
    echo '(function(){var d=document.documentElement,m=d.getAttribute("data-theme-mode")||"auto",t=m==="dark"?"dark":"light";if(m==="auto"&&window.matchMedia){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}d.setAttribute("data-theme",t);d.setAttribute("data-bs-theme",t);d.setAttribute("data-theme-mode",m==="light"||m==="dark"||m==="auto"?m:"auto");})();';
}
?></script>
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($appName) ?></title>
    <!-- SEO Meta Tags -->
<?php if (isset($seoMetaTags)): ?>
    <?= $seoMetaTags ?>
<?php else: ?>
    <?= getSeoMeta($pageTitle . " - " . $appName, $metaDescription) ?>
<?php endif; ?>

<!-- Pagination SEO -->
<?php if (isset($seoPaginationTags) && $seoPaginationTags !== ''): ?>
    <?= $seoPaginationTags ?>
<?php endif; ?>
    <?php
$robotsMeta = seoRobotsMeta($_lay, null, isset($pageKey) ? (string) $pageKey : null);
$indexDraftTopics = function_exists('seoIndexToggleValue')
    ? seoIndexToggleValue($_lay, 'index_draft_topics', '0', 'noindex_draft_topics')
    : (((string) ($_lay['noindex_draft_topics'] ?? '1')) === '1' ? '0' : '1');
$indexEmptyCategories = function_exists('seoIndexToggleValue')
    ? seoIndexToggleValue($_lay, 'index_empty_categories', '0', 'noindex_empty_categories')
    : (((string) ($_lay['noindex_empty_categories'] ?? '1')) === '1' ? '0' : '1');

if (isset($topic) && ($topic['status'] ?? 'published') !== 'published' && $indexDraftTopics !== '1') {
    $robotsMeta = "noindex, nofollow";
}
if (isset($categoryId) && $categoryId > 0 && isset($items) && empty($items) && $indexEmptyCategories !== '1') {
    $robotsMeta = "noindex, nofollow";
}
?>
    <meta name="robots" content="<?= htmlspecialchars($robotsMeta) ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <meta name="app-base-uri" content="<?= htmlspecialchars($baseUri) ?>">
    <meta name="color-scheme" content="light dark">
    <?php $__robotoLocalHref = asset_url("assets/css/roboto-local.css", $baseUri); ?>
    <link rel="preload" as="style" href="<?= htmlspecialchars($__robotoLocalHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($__robotoLocalHref, ENT_QUOTES, 'UTF-8') ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= htmlspecialchars($__robotoLocalHref, ENT_QUOTES, 'UTF-8') ?>"></noscript>
    <link rel="icon" href="<?= htmlspecialchars($_faviconHref !== "" ? $_faviconHref : asset_url("assets/favicon.svg", $baseUri)) ?>">
    <?php if (is_file($_publicCssBundle)): ?>
    <!-- Critical CSS (render-blocking) -->
    <link rel="stylesheet" href="<?= asset_url("assets/dist/public.min.css", $baseUri) ?>">
    <!-- Theme CSS -->
    <?php if ($_activePublicTheme === 'default'): ?>
    <?php $_themeMinCss = __DIR__ . "/../assets/dist/theme.min.css"; if (is_file($_themeMinCss)): ?>
    <link rel="stylesheet" href="<?= asset_url("assets/dist/theme.min.css", $baseUri) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <?php $_fontFiles = glob(__DIR__ . "/../assets/dist/bootstrap-icons-*.woff2"); if (!empty($_fontFiles)): $_fontRelPath = 'assets/dist/' . basename($_fontFiles[0]); ?>
    <link rel="preload" href="<?= asset_url($_fontRelPath, $baseUri) ?>" as="font" type="font/woff2" crossorigin>
    <?php endif; ?>
    <?php else: ?>
    <link rel="stylesheet" href="<?= asset_url("assets/css/general.css", $baseUri) ?>">
    <?php $_bootstrapIconsCss = asset_url("assets/bootstrap-icons.css", $baseUri); ?>
    <link rel="stylesheet" href="<?= $_bootstrapIconsCss ?>">
    <?php if ($_activePublicTheme === 'default'): ?>
    <?php $_themeCss = asset_url("assets/css/theme.css", $baseUri); ?>
    <link rel="stylesheet" href="<?= $_themeCss ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset_url("assets/css/public-dialog.css", $baseUri) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset_url("assets/css/ui-foundation.css", $baseUri) ?>">
    <?php foreach ($_pageCssFiles as $_pageCssFile): ?>
    <link rel="stylesheet" href="<?= asset_url($_pageCssFile, $baseUri) ?>">
    <?php endforeach; ?>
    <?php if ($_themeManager instanceof ThemeManager): ?>
    <?= $_themeManager->renderAssetTags("css") . "\n" ?>
    <?php endif; ?>
    <?php if (is_file($_publicJsBundle)): ?>
    <script src="<?= asset_url("assets/dist/public.min.js", $baseUri) ?>" defer></script>
    <?php else: ?>
    <script src="<?= asset_url("assets/js/app.js", $baseUri) ?>" defer></script>
    <script src="<?= asset_url("assets/js/ui.js", $baseUri) ?>" defer></script>
    <script src="<?= asset_url("assets/js/ui-foundation.js", $baseUri) ?>" defer></script>
    <?php endif; ?>
    <script src="<?= asset_url("assets/js/public-toast-bridge.js", $baseUri) ?>" defer></script>
    <?php if ($_themeManager instanceof ThemeManager): ?>
    <?= $_themeManager->renderAssetTags("js") . "\n" ?>
    <?php endif; ?>
    <?php if (($_lay["structured_data"] ?? "1") === "1" && ($_lay["schema_site_search"] ?? "1") === "1"): ?>
    <script type="application/ld+json"><?= getWebsiteStructuredDataJson($_lay) ?></script>
    <?php endif; ?>
    <?php if (!$_themeAssetIsolated && trim((string) ($_lay["custom_head_code"] ?? "")) !== ""): ?>
    <?= sanitizeSeoHeadCode((string) $_lay["custom_head_code"]) . "\n" ?>
    <?php endif; ?>
    <?php if (!$_themeAssetIsolated && !empty($_lay["google_analytics_id"])): ?>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_lay["google_analytics_id"]) ?>"></script>
    <script src="<?= asset_url('assets/js/google-analytics-init.js', $baseUri) ?>" data-ga-id="<?= htmlspecialchars((string) $_lay["google_analytics_id"], ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>
    <?php if (!empty($_lay["google_site_verification"])): ?>
    <meta name="google-site-verification" content="<?= htmlspecialchars($_lay["google_site_verification"]) ?>">
    <?php endif; ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars((string) $bodyClass) . '"' : '' ?>>
<?php
?>
<!-- Structured Data -->
<?php if (isset($seoStructuredData) && $seoStructuredData !== ''): ?>
    <?= $seoStructuredData ?>
<?php endif; ?>
    <div class="topic-shell d-flex flex-column ui-section">
        <?php if ($_hTopbar && trim($_hTopbarTxt) !== ""): ?>
        <div class="topbar">
            <div class="topbar-inner">
                <?= htmlspecialchars($_hTopbarTxt) ?>
            </div>
        </div>
        <?php endif; ?>
        <header class="header"<?= $_hSticky
            ? ""
            : ' data-static-header="true"' ?>>
            <div class="container ui-container">
                <a class="brand" href="<?= $baseUri ?>/index.php">
                    <?php if ($_logoSrc !== ""): ?>
                    <img src="<?= htmlspecialchars($_logoSrc) ?>" alt="" class="brand-logo" width="148" height="40" loading="eager" fetchpriority="high" decoding="async">
                    <?php else: ?>
                    <span class="topic-brand-mark" aria-hidden="true"><?php if (str_starts_with($_hIcon, "bi-")): ?><i class="bi <?= htmlspecialchars($_hIcon) ?>"></i><?php else: ?><?= htmlspecialchars($_hIcon) ?><?php endif; ?></span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($_hBrand) ?></span>
                </a>

                <?php $_menuLines =
                    $_menuItems !== ""
                        ? array_filter(
                            array_map("trim", explode("\n", $_menuItems)),
                        )
                        : [];
                $_resolveMenuUrl = static function (string $url) use ($baseUri): string {
                    $url = trim($url);
                    if ($url === '') {
                        return '#';
                    }
                    $categoryListMarkers = ['{category_list}', '/kategori', 'kategori', '/kategoriler', 'kategoriler', '/category', 'category', '/categories', 'categories'];
                    if (in_array(trim($url), $categoryListMarkers, true)) {
                        return categoryListUrl();
                    }
                    if (preg_match('/^(?:https?:)?\/\//i', $url) || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
                        return $url;
                    }
                    if (str_starts_with($url, '/')) {
                        $base = '/' . trim((string) $baseUri, '/');
                        $path = '/' . trim($url, '/');
                        if ($base !== '/' && $path !== $base && !str_starts_with($path, $base . '/')) {
                            return rtrim((string) $baseUri, '/') . $path;
                        }
                        return $url;
                    }

                    return rtrim((string) $baseUri, '/') . '/' . ltrim($url, '/');
                }; ?>
                <nav class="nav" id="publicNav" aria-label="Ana gezinme">
                    <?php if (!empty($_menuLines)): ?>
                        <?php foreach ($_menuLines as $_mLine):

                            $_mParts = array_map("trim", explode("|", $_mLine));
                            $_mLabel = $_mParts[0] ?? "";
                            $_mUrl = $_mParts[1] ?? "#";
                            $_mIco = $_mParts[2] ?? "";
                            if ($_mLabel === "") {
                                continue;
                            }
                            $_mHref = $_resolveMenuUrl($_mUrl);
                            ?>
                            <a href="<?= htmlspecialchars($_mHref) ?>"><?php
if ($_mIco !== ""): ?><i class="bi <?= htmlspecialchars(
    $_mIco,
) ?>" aria-hidden="true"></i> <?php endif;
echo htmlspecialchars($_mLabel);
?></a>
                        <?php
                        endforeach; ?>
                    <?php else: ?>
                        <a href="<?= $baseUri ?>/index.php">Anasayfa</a>
                        <a href="<?= categoryListUrl() ?>">Kategoriler</a>
                        <a href="<?= htmlspecialchars($_uploadTopicUrl) ?>">Mod Yükle</a>
                        <a href="<?= htmlspecialchars(routePublicStaticUrl("events")) ?>">Etkinlikler</a>
                        <a href="<?= htmlspecialchars(routePublicStaticUrl("contact")) ?>"><i class="bi bi-envelope-paper" aria-hidden="true"></i> Iletisim</a>
                    <?php endif; ?>
                    <?php if ($_menuShowCats): ?>
                        <?php foreach (
                            array_slice($publicCategories, 0, $_menuCatLimit)
                            as $_mCat
                        ): ?>
                            <a href="<?= categoryUrl(
                                (string) ($_mCat["slug"] ?? ""),
                            ) ?>"><?= htmlspecialchars(
    (string) $_mCat["name"],
) ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </nav>

                <div class="header-right">
                    <?php
                    $_uploadCtaHref =
                        $_menuCta &&
                        $_menuCtaTxt !== "" &&
                        trim($_menuCtaUrl) !== ""
                            ? $_menuCtaUrl
                            : $_uploadTopicUrl;
                    $_uploadCtaText =
                        $_menuCta && $_menuCtaTxt !== ""
                            ? $_menuCtaTxt
                            : "Yükle";
                    $_uploadCtaIcon =
                        $_menuCta && $_menuCtaIco !== ""
                            ? $_menuCtaIco
                            : "bi-cloud-upload";
                    $_uploadCtaStyle =
                        $_menuCta && $_menuCtaClr !== ""
                            ? (
                                function_exists('uiStyleAttribute') && function_exists('uiCssColorValue')
                                    ? uiStyleAttribute(['--header-upload-bg' => uiCssColorValue((string) $_menuCtaClr)])
                                    : ''
                            )
                            : "";
                    ?>
                    <a class="btn-upload" href="<?= htmlspecialchars(
                        $_uploadCtaHref,
                    ) ?>"<?= $_uploadCtaStyle ?>>
                        <i class="bi <?= htmlspecialchars(
                            $_uploadCtaIcon,
                        ) ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($_uploadCtaText) ?></span>
                    </a>

                    <?php if ($_hSearch): ?>
                    <form class="search topic-nav-search" action="<?= $baseUri ?>/index.php" method="get" role="search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" name="q" value="<?= htmlspecialchars(
                            $_GET["q"] ?? "",
                        ) ?>" placeholder="<?= htmlspecialchars(
    $_hSearchPh,
) ?>" aria-label="<?= htmlspecialchars(
    $_hSearchPh,
) ?>" autocomplete="off" data-search-autocomplete>
                    </form>
                    <?php endif; ?>

                    <button class="theme-toggle" title="Tema Değiştir" type="button"><i class="bi bi-moon-stars-fill" id="theme-icon" aria-hidden="true"></i></button>

                    <?php if ($isLoggedIn): ?>
                        <div
                            class="notif-dropdown"
                            id="messagesDropdown"
                            data-messages-dropdown
                            data-messages-api="<?= htmlspecialchars($baseUri . '/api/messages.php', ENT_QUOTES, 'UTF-8') ?>"
                            data-messages-fallback-url="<?= htmlspecialchars($_messagesUrl, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <button class="notif-toggle" type="button" aria-expanded="false" aria-label="Mesajlari ac" data-messages-toggle>
                                <i class="bi bi-chat-left-text-fill"></i>
                                <span class="notif-badge" id="msgBadge">0</span>
                            </button>
                            <div class="notif-menu">
                                <div class="notif-menu-header">
                                    <span>Mesajlar</span>
                                    <a href="#" data-messages-mark-all>Tumunu Okundu Isaretle</a>
                                </div>
                                <div class="notif-menu-list" id="msgList">
                                    <div class="notif-menu-state is-loading">Yukleniyor...</div>
                                </div>
                                <div class="notif-menu-footer">
                                    <a href="<?= htmlspecialchars($_messagesUrl, ENT_QUOTES, 'UTF-8') ?>">Tum Mesajlari Gor</a>
                                </div>
                            </div>
                        </div>

                        <div
                            class="notif-dropdown"
                            id="notifDropdown"
                            data-notif-dropdown
                            data-notif-api="<?= htmlspecialchars($baseUri . '/api/notifications.php', ENT_QUOTES, 'UTF-8') ?>"
                            data-notif-read-api="<?= htmlspecialchars($baseUri . '/api/notifications-read.php', ENT_QUOTES, 'UTF-8') ?>"
                            data-notif-fallback-url="<?= htmlspecialchars($_notificationsUrl, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <button class="notif-toggle" type="button" aria-expanded="false" aria-label="Bildirimleri aç" data-notif-toggle>
                                <i class="bi bi-bell-fill"></i>
                                <span class="notif-badge" id="notifBadge">0</span>
                            </button>
                            <div class="notif-menu">
                                <div class="notif-menu-header">
                                    <span>Bildirimler</span>
                                    <a href="#" data-notif-mark-all>Tümünü Okundu İşaretle</a>
                                </div>
                                <div class="notif-menu-list" id="notifList">
                                    <div class="notif-menu-state is-loading">Yükleniyor...</div>
                                </div>
                                <div class="notif-menu-footer">
                                    <a href="<?= htmlspecialchars($_notificationsUrl, ENT_QUOTES, 'UTF-8') ?>">Tüm Bildirimleri Gör</a>
                                </div>
                            </div>
                        </div>
                        <script src="<?= asset_url('assets/js/public-notifications-menu.js', $baseUri) ?>" defer></script>
                        <script src="<?= asset_url('assets/js/public-messages-menu.js', $baseUri) ?>" defer></script>

                        <?php
                        $_profileName = htmlspecialchars(
                            $_SESSION["_auth_user_name"] ?? "Hesap",
                        );
                        $_currentUserId = (int) ($_SESSION["_auth_user_id"] ?? 0);
                        $_isAdmin = function_exists("userIsAdmin")
                            ? userIsAdmin($pdo ?? null, $_currentUserId)
                            : (
                                function_exists("userHasPermission")
                                && userHasPermission($pdo ?? null, $_currentUserId, "admin.access")
                            );
$_profileAvatarUrl = function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri) : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
                        $_profileHasAvatar = false;
                        if ($isLoggedIn && $pdo instanceof PDO && !empty($_SESSION["_auth_user_id"])) {
                            // Oturum avatar onbellegi: her sayfa yukunde DB sorgusu yapmamak icin
                            // avatar bilgisini $_SESSION icinde kisa TTL ile tut. Avatar guncellemesi
                            // yapan kod yollarinda invalidateUserAvatarCache() cagirilarak tazelenebilir.
                            $_authUid = (int) $_SESSION["_auth_user_id"];
                            $_avatarCache = $_SESSION["_auth_avatar_cache"] ?? null;
                            $_avatarCacheTtl = 60;
                            if (
                                !is_array($_avatarCache)
                                || ($_avatarCache["uid"] ?? 0) !== $_authUid
                                || (time() - (int) ($_avatarCache["ts"] ?? 0)) > $_avatarCacheTtl
                            ) {
                                try {
                                    $_avatarStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
                                    $_avatarStmt->execute([$_authUid]);
                                    $_profileAvatarRaw = trim((string) ($_avatarStmt->fetchColumn() ?: ""));
                                    $_SESSION["_auth_avatar_cache"] = [
                                        "uid" => $_authUid,
                                        "raw" => $_profileAvatarRaw,
                                        "ts" => time(),
                                    ];
                                } catch (Throwable $e) {
                                    $_SESSION["_auth_avatar_cache"] = null;
                                    $_profileAvatarRaw = "";
                                }
                            } else {
                                $_profileAvatarRaw = (string) ($_avatarCache["raw"] ?? "");
                            }

                            if ($_profileAvatarRaw !== "") {
                                if (function_exists('resolveAvatarUrl')) {
                                    $_resolvedProfileAvatar = resolveAvatarUrl($_profileAvatarRaw, $baseUri, false);
                                    if ($_resolvedProfileAvatar !== '') {
                                        $_profileAvatarUrl = $_resolvedProfileAvatar;
                                        $_profileHasAvatar = true;
                                    }
                                } elseif (preg_match('~^(https?:)?//|^/~i', $_profileAvatarRaw) === 1) {
                                    $_profileAvatarUrl = $_profileAvatarRaw;
                                    $_profileHasAvatar = true;
                                } else {
                                    $_profileAvatarUrl = rtrim($baseUri, "/") . "/" . ltrim($_profileAvatarRaw, "/");
                                    $_profileHasAvatar = true;
                                }
                            }
                        }
                        ?>
                        <div class="topic-profile-dd">
                            <button class="profile-avatar topic-profile-toggle"
                                    type="button"
                                    id="profileDropdownBtn"
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    aria-label="Profil menüsünü aç"
                                    aria-controls="profileDropdownMenu">
                                    <?php if ($_profileHasAvatar): ?>
                                        <img class="topic-profile-avatar-img" src="<?= htmlspecialchars($_profileAvatarUrl) ?>" alt="" loading="eager" decoding="async" width="40" height="40" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars(function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri) : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg') ?>">
                                    <?php else: ?>
                                    <?= function_exists('defaultAvatarHtml') ? defaultAvatarHtml((string) ($_SESSION["_auth_user_name"] ?? "U"), ['class' => 'topic-profile-default-avatar', 'base_uri' => $baseUri, 'alt' => '', 'width' => 40, 'height' => 40]) : '<span class="topic-profile-default-avatar default-avatar" aria-hidden="true"></span>' ?>
                                    <?php endif; ?>
                                </button>
                                <ul class="topic-profile-menu"
                                    id="profileDropdownMenu"
                                    role="menu"
                                    aria-labelledby="profileDropdownBtn">
                                <li class="topic-profile-menu-header ui-panel__head">
                                    <span class="topic-profile-menu-avatar">
                                        <?php if ($_profileHasAvatar): ?>
                                            <img class="topic-profile-avatar-img" src="<?= htmlspecialchars($_profileAvatarUrl) ?>" alt="<?= $_profileName ?> profil fotoğrafı" loading="eager" decoding="async" width="40" height="40" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars(function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri) : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg') ?>">
                                        <?php else: ?>
                                            <?= function_exists('defaultAvatarHtml') ? defaultAvatarHtml((string) ($_SESSION["_auth_user_name"] ?? "U"), ['class' => 'topic-profile-default-avatar', 'base_uri' => $baseUri, 'width' => 40, 'height' => 40]) : '<span class="topic-profile-default-avatar default-avatar"></span>' ?>
                                        <?php endif; ?>
                                    </span>
                                    <div class="topic-profile-menu-info">
                                        <strong><?= $_profileName ?></strong>
                                        <small class="topic-profile-role"><?= $_isAdmin
                                            ? "Yönetici"
                                            : "Üye" ?></small>
                                    </div>
                                </li>
                                <?php if ($_hProfile): ?>
                                <li><a class="tpm-item" href="<?= $baseUri ?>/profile.php"><i class="bi bi-person" aria-hidden="true"></i>Profilim</a></li>
                                <?php endif; ?>
                                <li><a class="tpm-item" href="<?= $baseUri ?>/profile.php#topics"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>İçeriklerim</a></li>
                                <li><a class="tpm-item" href="<?= $baseUri ?>/profile.php?tab=favorites"><i class="bi bi-heart" aria-hidden="true"></i>Favorilerim</a></li>
                                <li><a class="tpm-item" href="<?= $baseUri ?>/profile.php?tab=activity"><i class="bi bi-clock-history" aria-hidden="true"></i>Aktivitem</a></li>
                                <li><a class="tpm-item" href="<?= htmlspecialchars($_messagesUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-chat-left-text" aria-hidden="true"></i>Mesajlar</a></li>
                                <li><a class="tpm-item" href="<?= htmlspecialchars($_notificationsUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-bell" aria-hidden="true"></i>Bildirimlerim</a></li>
                                <li><a class="tpm-item" href="<?= htmlspecialchars($_uploadTopicUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>İçerik Yükle</a></li>
                                <?php if ($_hAdmin && $_isAdmin): ?>
                                <li class="tpm-divider"></li>
                                <li><a class="tpm-item tpm-admin" href="<?= $baseUri ?>/admin/index.php"><i class="bi bi-speedometer2" aria-hidden="true"></i>Admin Paneli</a></li>
                                <li><a class="tpm-item tpm-admin" href="<?= $baseUri ?>/admin/topics.php"><i class="bi bi-collection" aria-hidden="true"></i>Konu Yönetimi</a></li>
                                <?php endif; ?>
                                <li class="tpm-divider"></li>
                                <li>
                                    <form action="<?= htmlspecialchars($_logoutUrl, ENT_QUOTES, 'UTF-8') ?>" method="post" class="topic-profile-form">
                                        <?= csrf_field() ?>
                                        <button class="tpm-item tpm-logout" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Çıkış Yap</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php elseif ($_hAuth): ?>
                        <div class="auth-popover">
                            <button class="btn-secondary auth-popover-trigger"
                                    type="button"
                                    aria-expanded="false"
                                    aria-haspopup="dialog"
                                    aria-controls="authPopoverPanel"
                                    data-auth-popover-trigger>
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                <span>Giriş / Kayıt Ol</span>
                            </button>
                            <div class="auth-popover-panel ui-panel" id="authPopoverPanel" role="dialog" aria-label="Giriş ve kayıt seçenekleri" hidden>
                                <div class="auth-popover-head ui-panel__head">
                                    <span class="auth-popover-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                                    <div>
                                        <strong>Hesabına devam et</strong>
                                        <small>İçerik yükle, favorilerini sakla ve profilini yönet.</small>
                                    </div>
                                </div>
                                <div class="auth-popover-actions">
                                    <a class="auth-popover-primary" href="<?= htmlspecialchars($_loginHref) ?>">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                        Giriş Yap
                                    </a>
                                    <a class="auth-popover-secondary" href="<?= htmlspecialchars($_registerHref) ?>">
                                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                                        Kayıt Ol
                                    </a>
                                </div>
                                <a class="auth-popover-forgot" href="<?= htmlspecialchars($_forgotPasswordHref) ?>">Åifremi unuttum</a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </header>
        <main id="main-content" class="flex-grow-1" tabindex="-1">


