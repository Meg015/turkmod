<?php

declare(strict_types=1);

use App\Engine\Themes\ThemeHeaderViewData;
use App\Engine\Themes\ThemeMetadata;

final class PublicThemeRenderer
{
    private const STATE_KEY = '_public_theme_renderer';

    /**
     * @param array<string, mixed> $context
     */
    public static function start(array $context): bool
    {
        $themeManager = $context['theme_manager'] ?? null;
        if (!$themeManager instanceof ThemeManager || !$themeManager->usesPublicRenderer()) {
            return false;
        }

        if (self::isActive()) {
            return true;
        }

        $settings = self::arrayValue($context, 'settings');
        $pageVars = self::arrayValue($context, 'page_vars');
        $baseUri = (string) ($context['base_uri'] ?? '');
        $siteName = (string) ($context['app_name'] ?? ($settings['site_name'] ?? ($settings['header_brand_text'] ?? 'TurkMod')));
        $pageTitle = (string) ($context['page_title'] ?? 'Ana Sayfa');
        $siteDescription = (string) ($settings['footer_description'] ?? $settings['footer_text'] ?? 'Topluluk dosyalari, guncellemeler ve modlar.');
        $currentScript = (string) ($context['current_script'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
        $currentRequestUri = (string) ($context['current_request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? ($baseUri . '/index.php')));
        $isLoggedIn = !empty($_SESSION['_auth_user_id']) || (bool) ($context['is_logged_in'] ?? false);
        $userName = (string) ($_SESSION['_auth_user_name'] ?? 'Uye');
        $publicCategories = self::arrayValue($context, 'public_categories');
        $publicCategoriesTree = self::arrayValue($context, 'public_categories_tree');
        $pdo = $context['pdo'] ?? null;
        $currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        $userIsAdmin = function_exists('userHasPermission')
            && userHasPermission($pdo instanceof PDO ? $pdo : null, $currentUserId, 'admin.access');

        $activeCategorySlug = self::activeCategorySlug($pageVars);
        $categoryNodes = $publicCategoriesTree !== [] ? $publicCategoriesTree : array_slice($publicCategories, 0, 10);
        $categoryListUrl = self::categoryListHref($baseUri);
        $categoryMenuItems = self::categoryMenuItems($categoryNodes, $activeCategorySlug, 'sidebar', $baseUri, $pdo);
        $headerCategoryMenuItems = self::categoryMenuItems($categoryNodes, $activeCategorySlug, 'topbar', $baseUri, $pdo);

        $isEventsRequest = preg_match('~(?:^|/)events(?:/|$)~', '/' . trim((string) parse_url($currentRequestUri, PHP_URL_PATH), '/')) === 1;
        $pageKey = self::pageKey($currentScript, $isEventsRequest, $currentRequestUri);
        $redirectPath = (string) parse_url((string) ($_GET['redirect'] ?? ''), PHP_URL_PATH);
        $isEventsLoginRedirect = in_array($currentScript, ['login.php', 'giris'], true)
            && preg_match('~(?:^|/)events(?:/|$)~', '/' . trim($redirectPath, '/')) === 1;
        $focusPageKeys = array_values(array_unique(array_merge(
            ['download', 'events', 'profile', 'public_profile', 'upload_topic', 'edit_topic', 'notifications', 'messages', 'leaderboard', 'contact'],
            ThemeMetadata::authFocusPageKeys($themeManager),
        )));
        $isFocusLayout = in_array($pageKey, $focusPageKeys, true) || $isEventsLoginRedirect;
        $layoutMode = $isFocusLayout ? 'focus' : 'standard';

        $headerAvatarFallback = function_exists('defaultAvatarUrl')
            ? defaultAvatarUrl($baseUri)
            : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        $headerAvatar = $isLoggedIn
            ? (function_exists('resolveAvatarUrl')
                ? resolveAvatarUrl(self::currentUserAvatar($baseUri), $baseUri, true)
                : self::absoluteMediaUrl(self::currentUserAvatar($baseUri), $baseUri))
            : '';

        $headerVars = [
            'site_language' => (string) ($context['site_language'] ?? 'tr'),
            'site_name' => $siteName,
            'site_description' => $siteDescription,
            'base_url' => rtrim($baseUri, '/'),
            'logo_url' => self::publicSettingAsset(trim((string) ($settings['logo_url'] ?? '')), $baseUri),
            'logo_alt' => $siteName . ' logo',
            'page_title' => $pageTitle,
            'search_query' => (string) ($_GET['q'] ?? ''),
            'menu_items' => function_exists('sidebarBuilderNavigationItems')
                ? sidebarBuilderNavigationItems($settings, $baseUri)
                : [],
            'logged_in' => $isLoggedIn,
            'user_name' => $userName,
            'user_initial' => self::initial($userName),
            'user_avatar_url' => $headerAvatar,
            'user_avatar_fallback' => $headerAvatarFallback,
            'user_is_admin' => $isLoggedIn && $userIsAdmin,
            'category_list_url' => $categoryListUrl,
            'category_menu_items' => $categoryMenuItems,
            'has_category_menu' => $categoryMenuItems !== [],
            'header_category_menu_items' => $headerCategoryMenuItems,
            'has_header_category_menu' => $headerCategoryMenuItems !== [],
        ];
        $headerVars = array_merge(
            $headerVars,
            ThemeHeaderViewData::notificationMenu($baseUri, $isLoggedIn),
            ThemeHeaderViewData::messageMenu($baseUri, $isLoggedIn),
        );

        $unreadCount = 0;
        if ($isLoggedIn && $pdo instanceof \PDO && $currentUserId > 0) {
            if (class_exists(\App\Modules\Notifications\Services\NotificationCenterService::class)) {
                $payload = (new \App\Modules\Notifications\Services\NotificationCenterService())->dropdownPayload($pdo, $currentUserId);
                if (isset($payload['show_badge']) && $payload['show_badge']) {
                    $unreadCount = (int) ($payload['unread_count'] ?? 0);
                }
            }
        }

        $headerVars['notifications_has_unread'] = $unreadCount > 0;
        $headerVars['notifications_unread_count_text'] = $unreadCount > 99 ? '99+' : (string) $unreadCount;

        $footerData = self::buildFooterData($context);
        $sidebarConfig = function_exists('sidebarBuilderConfigFromSettings') ? sidebarBuilderConfigFromSettings($settings) : [];
        $sidebarSourceVars = array_merge($headerVars, $footerData, [
            'page_key' => $pageKey,
            'logged_in' => $isLoggedIn,
            'user_name' => $userName,
            'current_topic' => isset($pageVars['topic']) && is_array($pageVars['topic']) ? $pageVars['topic'] : [],
            'current_category_id' => (int) ($pageVars['category_id'] ?? $pageVars['categoryId'] ?? ($pageVars['category']['id'] ?? 0)),
            'current_category_slug' => (string) ($pageVars['category_slug'] ?? ($pageVars['category']['slug'] ?? '')),
        ]);
        $leftSidebarVars = function_exists('sidebarBuilderAreaTemplateVars')
            ? sidebarBuilderAreaTemplateVars($pdo instanceof PDO ? $pdo : null, $settings, $sidebarConfig, 'left', $pageKey, $sidebarSourceVars)
            : ['has_sidebar_widgets' => true, 'sidebar_global_enabled' => true];
        $rightSidebarVars = function_exists('sidebarBuilderAreaTemplateVars')
            ? sidebarBuilderAreaTemplateVars($pdo instanceof PDO ? $pdo : null, $settings, $sidebarConfig, 'right', $pageKey, $sidebarSourceVars)
            : ['has_sidebar_widgets' => true, 'sidebar_global_enabled' => true];

        $desktopLayout = (string) (($sidebarConfig['global']['desktop_layout'] ?? 'both'));
        $sidebarGlobalEnabled = !empty($leftSidebarVars['sidebar_global_enabled']) || !empty($rightSidebarVars['sidebar_global_enabled']);
        $showLeftSidebar = !$isFocusLayout && $sidebarGlobalEnabled && $desktopLayout !== 'right' && !empty($leftSidebarVars['has_sidebar_widgets']);
        $showRightSidebar = !$isFocusLayout && $sidebarGlobalEnabled && $desktopLayout !== 'left' && !empty($rightSidebarVars['has_sidebar_widgets']);
        if (in_array($pageKey, ['leaderboard', 'upload_topic', 'edit_topic', 'messages'], true)) {
            $showLeftSidebar = false;
            $showRightSidebar = false;
        }

        $GLOBALS['_public_theme_layout_mode'] = $layoutMode;
        $GLOBALS['_public_theme_show_left_sidebar'] = $showLeftSidebar;
        $GLOBALS['_public_theme_show_right_sidebar'] = $showRightSidebar;
        $GLOBALS['_public_theme_sidebar_right_vars'] = $rightSidebarVars;
        $GLOBALS['_public_theme_footer_data'] = $footerData;

        $header = $themeManager->render('header', $headerVars);
        $sidebarLeft = $showLeftSidebar
            ? $themeManager->render('partials.sidebar_left', array_merge($headerVars, $leftSidebarVars), ['sidebar_widget.html'])
            : '';

        $bodyClass = trim((string) ($context['body_class'] ?? '') . ' public-theme-layout public-theme-layout-active');
        $bodyClass = trim($bodyClass . ' public-page-' . str_replace('_', '-', $pageKey));
        if ($pageKey === 'topic') {
            $bodyClass = trim($bodyClass . ' topic-detail-page');
        }
        if ($showLeftSidebar) {
            $bodyClass = trim($bodyClass . ' has-left-sidebar');
        }
        if ($showRightSidebar) {
            $bodyClass = trim($bodyClass . ' has-right-sidebar');
        }
        $head = self::buildHead(array_replace($context, ['page_key' => $pageKey]));
        $scripts = '';
        if (function_exists('asset_url')) {
            try {
                $themeJs = trim($themeManager->renderAssetTags('js'));
                $activeThemeId = $themeManager->activeThemeId();
                // Public header/sidebar behavior must stay identical across all Turkmod pages.
                // Use the full theme runtime everywhere so Bootstrap dropdowns, theme toggle,
                // notifications, profile menu, and sidebar controls share one initializer path.
                $preferLeanRuntime = false;
                $themeOwnsSharedRuntime = $themeJs !== '' && $activeThemeId === 'turkmod' && !$preferLeanRuntime;
                if ($themeOwnsSharedRuntime) {
                    // Turkmod bundle already includes shared public runtime (analytics/ui bootstrap).
                    // Avoid loading root public bundle again to prevent duplicate initializers.
                    $scripts = $themeJs;
                } else {
                    $publicJsPath = __DIR__ . '/../assets/dist/public.min.js';
                    if (is_file($publicJsPath)) {
                        $scripts = '<script src="' . htmlspecialchars(asset_url('assets/dist/public.min.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>';
                    } else {
                        $scripts = '<script src="' . htmlspecialchars(asset_url('assets/js/app.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n" .
                                   '<script src="' . htmlspecialchars(asset_url('assets/js/ui.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n" .
                                   '<script src="' . htmlspecialchars(asset_url('assets/js/ui-foundation.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>';
                    }
                    if ($themeJs !== '' && !$preferLeanRuntime) {
                        $scripts = trim($scripts . "\n" . $themeJs);
                    }
                }
                if ($preferLeanRuntime && $activeThemeId === 'turkmod') {
                    // Lean listing runtime still needs sidebar category/atlas toggles.
                    // Load a tiny theme script instead of the full turkmod bundle.
                    $leanRuntimeRelative = 'js/lean-runtime.js';
                    $leanRuntimePath = __DIR__ . '/../themes/' . $activeThemeId . '/' . $leanRuntimeRelative;
                    if (preg_match('/^[a-z0-9_-]+$/', $activeThemeId) === 1 && is_file($leanRuntimePath)) {
                        $scripts = trim($scripts . "\n" . '<script src="' . htmlspecialchars($themeManager->assetUrl($activeThemeId, $leanRuntimeRelative), ENT_QUOTES, 'UTF-8') . '" defer></script>');
                    }
                }
                $toastBridgeScript = '<script src="' . htmlspecialchars(asset_url('assets/js/public-toast-bridge.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>';
                if (!str_contains($scripts, 'assets/js/public-toast-bridge.js')) {
                    $scripts = trim($scripts . "\n" . $toastBridgeScript);
                }
                if ($pageKey === 'topic') {
                    $scripts = trim($scripts . "\n" . '<script src="' . htmlspecialchars(asset_url('assets/js/topic-view-track.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>');
                    $scripts = trim($scripts . "\n" . '<script src="' . htmlspecialchars(asset_url('assets/js/topic-downloads.js', $baseUri), ENT_QUOTES, 'UTF-8') . '" defer></script>');
                }
            } catch (Throwable $error) {
                if (function_exists('appLogException')) {
                    appLogException($error, ['source' => 'PublicThemeRenderer ui foundation js']);
                }
            }
        }
        $breadcrumbsHtml = self::buildBreadcrumbHtml($themeManager, $context, $pageKey, $pageTitle, $baseUri, $isEventsRequest);

        $layoutVars = [
            'site_language' => (string) ($context['site_language'] ?? 'tr'),
            'site_name' => $siteName,
            'site_description' => $siteDescription,
            'base_url' => rtrim($baseUri, '/'),
            'page_title' => $pageTitle,
            'page_description' => (string) ($context['meta_description'] ?? $siteDescription),
            'page_key' => $pageKey,
            'theme_mode' => (string) ($context['theme_mode'] ?? 'auto'),
            'body_class' => $bodyClass,
            'layout_mode' => $layoutMode,
            'head' => $head,
            'header' => $header,
            'sidebar_left' => $sidebarLeft,
            'sidebar_right' => '',
            'footer' => '',
            'scripts' => $scripts,
            'content' => '',
            'breadcrumbs_html' => $breadcrumbsHtml,
            'category' => self::categoryVars($pageVars),
        ];

        $GLOBALS[self::STATE_KEY] = [
            'theme_manager' => $themeManager,
            'context' => $context,
            'vars' => $layoutVars,
            'footer_data' => $footerData,
            'sidebar_right_vars' => $rightSidebarVars,
            'raw_keys' => [
                'head',
                'header',
                'content',
                'sidebar_left',
                'sidebar_right',
                'footer',
                'scripts',
                'breadcrumbs_html',
            ],
        ];

        ob_start();
        return true;
    }

    public static function isActive(): bool
    {
        return isset($GLOBALS[self::STATE_KEY]) && is_array($GLOBALS[self::STATE_KEY]);
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $context
     */
    public static function renderTopicCardList(array $items, array $context = []): string
    {
        $cards = self::renderTopicCards($items, $context);
        if ($cards === '') {
            return '';
        }

        return '<div class="topic-grid topic-grid--list ui-grid" data-contract=\'class="topic-grid ui-grid"\' data-topic-list-container>' . $cards . '</div>';
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $context
     */
    public static function renderTopicCards(array $items, array $context = []): string
    {
        $baseUri = (string) ($context['base_uri'] ?? ($GLOBALS['baseUri'] ?? ''));
        $pdo = $context['pdo'] ?? ($GLOBALS['pdo'] ?? null);
        $settings = self::arrayValue($context, 'settings');
        $themeManager = $context['theme_manager'] ?? ($GLOBALS['themeManager'] ?? null);
        $html = '';

        foreach ($items as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = $row;
            $isLcpCandidate = $index === 0;
            if ($themeManager instanceof ThemeManager && $themeManager->usesPublicRenderer()) {
                try {
                    $html .= $themeManager->render('partials.topic_card', [
                        'topic' => self::topicCardVars($item, $baseUri, $pdo, $settings, $isLcpCandidate),
                    ]);
                    continue;
                } catch (Throwable $error) {
                    if (function_exists('appLogException')) {
                        appLogException($error, ['source' => 'PublicThemeRenderer topic card TPL']);
                    }
                }
            }

            ob_start();
            try {
                include __DIR__ . '/partials/topic-card.php';
                $html .= (string) ob_get_clean();
            } catch (Throwable $error) {
                ob_end_clean();
                if (function_exists('appLogException')) {
                    appLogException($error, ['source' => 'PublicThemeRenderer topic card']);
                }
            }
        }

        return $html;
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public static function topicCardListVars(array $items, array $context = []): array
    {
        $baseUri = (string) ($context['base_uri'] ?? ($GLOBALS['baseUri'] ?? ''));
        $pdo = $context['pdo'] ?? ($GLOBALS['pdo'] ?? null);
        $settings = self::arrayValue($context, 'settings');
        $topics = [];

        foreach ($items as $index => $row) {
            if (is_array($row)) {
                $topics[] = self::topicCardVars($row, $baseUri, $pdo, $settings, $index === 0);
            }
        }

        return $topics;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, has_items: bool}
     */
    public static function paginationVars(int $total, int $page, int $perPage, string $baseUrl): array
    {
        $totalPages = (int) ceil($total / max(1, $perPage));
        if ($totalPages <= 1) {
            return ['items' => [], 'has_items' => false];
        }

        $page = max(1, min($page, $totalPages));
        $maxVisible = defined('PAGINATION_MAX_VISIBLE_PAGES') ? (int) PAGINATION_MAX_VISIBLE_PAGES : 5;
        $maxVisible = max(3, min($maxVisible, $totalPages));
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $urlForPage = static fn (int $targetPage): string => $baseUrl . $separator . 'page=' . $targetPage;
        $items = [];

        if ($page > 1) {
            $items[] = ['url' => $urlForPage($page - 1), 'label' => '«', 'class' => '', 'active' => false, 'is_gap' => false, 'aria_label' => 'Onceki sayfa'];
        }

        $start = max(1, $page - (int) floor($maxVisible / 2));
        $end = min($totalPages, $start + $maxVisible - 1);
        $start = max(1, $end - $maxVisible + 1);

        if ($start > 1) {
            $items[] = ['url' => $urlForPage(1), 'label' => '1', 'class' => '', 'active' => false, 'is_gap' => false, 'aria_label' => ''];
            if ($start > 2) {
                $items[] = ['url' => '', 'label' => '...', 'class' => 'pagination-gap', 'active' => false, 'is_gap' => true, 'aria_label' => ''];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $items[] = ['url' => $urlForPage($i), 'label' => (string) $i, 'class' => $i === $page ? 'active' : '', 'active' => $i === $page, 'is_gap' => false, 'aria_label' => ''];
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $items[] = ['url' => '', 'label' => '...', 'class' => 'pagination-gap', 'active' => false, 'is_gap' => true, 'aria_label' => ''];
            }
            $items[] = ['url' => $urlForPage($totalPages), 'label' => (string) $totalPages, 'class' => '', 'active' => false, 'is_gap' => false, 'aria_label' => ''];
        }

        if ($page < $totalPages) {
            $items[] = ['url' => $urlForPage($page + 1), 'label' => '»', 'class' => '', 'active' => false, 'is_gap' => false, 'aria_label' => 'Sonraki sayfa'];
        }

        return ['items' => $items, 'has_items' => $items !== []];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function paginationItems(int $total, int $page, int $perPage, string $baseUrl, string $pageParam = 'page', int $maxVisible = 5): array
    {
        $totalPages = (int) ceil($total / max(1, $perPage));
        if ($totalPages <= 1) {
            return [];
        }

        $page = max(1, min($page, $totalPages));
        $maxVisible = max(3, min($maxVisible, $totalPages));
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $pageParam = preg_replace('/[^a-zA-Z0-9_-]/', '', $pageParam) ?: 'page';
        $urlForPage = static fn (int $targetPage): string => $baseUrl . $separator . rawurlencode($pageParam) . '=' . $targetPage;
        $items = [];
        $addPage = static function (int $targetPage, string $label = '', string $ariaLabel = '') use (&$items, $urlForPage, $page): void {
            $isActive = $targetPage === $page;
            $items[] = [
                'url' => $urlForPage($targetPage),
                'label' => $label !== '' ? $label : (string) $targetPage,
                'class' => $isActive ? 'active' : '',
                'active' => $isActive,
                'is_gap' => false,
                'aria_label' => $ariaLabel,
            ];
        };

        if ($page > 1) {
            $addPage($page - 1, '«', 'Onceki sayfa');
        }

        $start = max(1, $page - (int) floor($maxVisible / 2));
        $end = min($totalPages, $start + $maxVisible - 1);
        $start = max(1, $end - $maxVisible + 1);

        if ($start > 1) {
            $addPage(1);
            if ($start > 2) {
                $items[] = [
                    'url' => '',
                    'label' => '...',
                    'class' => 'disabled',
                    'active' => false,
                    'is_gap' => true,
                    'aria_label' => '',
                ];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $addPage($i);
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $items[] = [
                    'url' => '',
                    'label' => '...',
                    'class' => 'disabled',
                    'active' => false,
                    'is_gap' => true,
                    'aria_label' => '',
                ];
            }
            $addPage($totalPages);
        }

        if ($page < $totalPages) {
            $addPage($page + 1, '»', 'Sonraki sayfa');
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function topicCardVars(array $item, string $baseUri, mixed $pdo, array $settings, bool $isLcpCandidate = false): array
    {
        $category = (string) ($item['category'] ?? 'Genel');
        $categorySlug = (string) ($item['category_slug'] ?? strtolower($category));
        $categoryParentSlug = (string) ($item['parent_slug'] ?? ($item['category_parent_slug'] ?? ($item['parent_category_slug'] ?? '')));
        $summary = mb_substr(strip_tags((string) ($item['topic_descriptions'] ?? $item['description'] ?? '')), 0, 150);
        $views = (int) ($item['view_count'] ?? $item['views'] ?? 0);
        $author = trim((string) ($item['author'] ?? $item['user_name'] ?? 'Admin'));
        $title = (string) ($item['title'] ?? 'Icerik');
        $published = function_exists('formatAppDate')
            ? formatAppDate((string) ($item['published_at'] ?? ($item['created_at'] ?? 'now')), $pdo)
            : date('d M Y', strtotime((string) ($item['published_at'] ?? ($item['created_at'] ?? 'now'))));
        $href = function_exists('topicUrlForRow') ? topicUrlForRow($item) : '#';
        $authorUrl = !empty($item['author_id']) && function_exists('publicProfileUrl')
            ? publicProfileUrl([
                'id' => (int) $item['author_id'],
                'name' => $author !== '' ? $author : 'Admin',
            ])
            : '';

        $imageMap = [
            'Design' => 'portal-ui.svg',
            'Development' => 'portal-code.svg',
            'Operations' => 'portal-server.svg',
        ];
        $cover = $imageMap[$category] ?? 'portal-pack.svg';
        $image = function_exists('getTopicPrimaryMediaPath') ? (string) (getTopicPrimaryMediaPath($item) ?? '') : '';
        $image = self::absolutePublicHref($image, $baseUri);
        if ($image === '') {
            $image = function_exists('asset_url')
                ? asset_url('assets/' . $cover, $baseUri)
                : rtrim($baseUri, '/') . '/assets/' . $cover;
        }
        $imageSrcset = '';
        $imageSizes = '';
        if ($isLcpCandidate) {
            $thumbnailHref = self::listingThumbnailHref($image, $baseUri);
            if ($thumbnailHref !== '') {
                $imageSrcset = $thumbnailHref . ' 400w, ' . $image . ' 1200w';
                $imageSizes = '(max-width: 640px) 94vw, (max-width: 1024px) 70vw, 640px';
                $image = $thumbnailHref;
            }
        }

        $imageAlt = function_exists('seoGenerateImageAlt')
            ? seoGenerateImageAlt('topic-card', $title, $settings)
            : $title . ' kapak gorseli';
        $imageTitle = function_exists('seoGenerateImageTitle')
            ? seoGenerateImageTitle('topic-card', $title, $settings)
            : $imageAlt;

        return [
            'url' => $href,
            'image' => $image,
            'image_srcset' => $imageSrcset,
            'image_sizes' => $imageSizes,
            'image_alt' => $imageAlt,
            'image_title' => $imageTitle,
            'image_loading' => $isLcpCandidate ? 'eager' : 'lazy',
            'image_decoding' => $isLcpCandidate ? 'sync' : 'async',
            'image_fetchpriority' => $isLcpCandidate ? 'high' : '',
            'title' => $title,
            'category' => $category,
            'category_url' => $categorySlug !== '' && function_exists('categoryUrl') ? categoryUrl($categorySlug, $categoryParentSlug) : '#',
            'date' => $published,
            'excerpt' => $summary,
            'views' => number_format($views, 0, ',', '.'),
            'likes' => number_format((int) ($item['likes'] ?? $item['rating_count'] ?? 0), 0, ',', '.'),
            'comments_count' => number_format((int) ($item['comment_count'] ?? $item['comments_count'] ?? 0), 0, ',', '.'),
            'author' => $author !== '' ? $author : 'Admin',
            'author_url' => $authorUrl,
        ];
    }

    public static function renderEmptyState(string $title, string $description, string $icon = 'bi-info-circle'): string
    {
        if (function_exists('renderEmptyState')) {
            return renderEmptyState($title, $description, $icon);
        }

        return '<div class="card card-body text-center ui-panel ui-panel__body"><i class="bi ' .
            htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') .
            '"></i><h2 class="h5 mt-2">' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '</h2><p class="mb-0 text-secondary">' .
            htmlspecialchars($description, ENT_QUOTES, 'UTF-8') .
            '</p></div>';
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function finish(array $context = []): bool
    {
        if (!self::isActive()) {
            return false;
        }

        $state = $GLOBALS[self::STATE_KEY];
        $themeManager = $state['theme_manager'] ?? null;
        if (!$themeManager instanceof ThemeManager) {
            unset($GLOBALS[self::STATE_KEY]);
            return false;
        }

        $capturedContent = ob_get_clean();
        if (!is_string($capturedContent)) {
            $capturedContent = '';
        }

        $stateContext = self::arrayValue($state, 'context');
        $context = array_replace($stateContext, $context);
        $footerData = self::arrayValue($state, 'footer_data');
        if ($footerData === []) {
            $footerData = self::buildFooterData($context);
        }
        $vars = self::arrayValue($state, 'vars');
        $sidebarRightVars = self::arrayValue($state, 'sidebar_right_vars');
        $baseUri = rtrim((string) ($vars['base_url'] ?? ($context['base_uri'] ?? '')), '/');
        $sidebarRuntimeVars = [
            'user_avatar_fallback' => function_exists('defaultAvatarUrl')
                ? defaultAvatarUrl($baseUri)
                : $baseUri . '/assets/images/noavatar-neon-helmet.svg',
        ];
        $pageKey = (string) ($vars['page_key'] ?? '');
        $dropInlineScripts = in_array($pageKey, ['profile', 'public_profile', 'upload_topic', 'edit_topic'], true);
        $fragments = self::extractAssetFragments($capturedContent, $themeManager->isAssetIsolated(), $dropInlineScripts);
        $content = trim($capturedContent);
        if ($pageKey === 'topic') {
            $content = self::renderTopicTemplate($themeManager, $content, $context);
        } elseif (!in_array($pageKey, ['home', 'category'], true)) {
            $content = self::renderCapturedPageTemplate($themeManager, $pageKey, $content, $context, $vars);
        }

        $vars['content'] = $content;
        $vars['head'] = trim((string) ($vars['head'] ?? '') . "\n" . $fragments['head']);
        $vars['scripts'] = trim((string) ($vars['scripts'] ?? '') . "\n" . $fragments['scripts']);
        $vars['sidebar_right'] = !empty($GLOBALS['_public_theme_show_right_sidebar'])
            ? $themeManager->render('partials.sidebar_right', array_merge($footerData, $sidebarRightVars, $sidebarRuntimeVars), ['sidebar_widget.html'])
            : '';
        $vars['footer'] = trim($themeManager->render('footer', $footerData) . "\n" . self::renderToastContainer($context) . "\n" . self::renderPopupAnnouncement($context));

        try {
            echo $themeManager->renderLayout($vars, self::arrayValue($state, 'raw_keys'));
        } catch (Throwable $error) {
            if (function_exists('appLogException')) {
                appLogException($error, ['source' => 'PublicThemeRenderer layout']);
            }

            echo self::renderStandaloneThemeErrorPage($error, 'layout', $context);
        }
        unset($GLOBALS[self::STATE_KEY]);
        return true;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $layoutVars
     */
    private static function renderCapturedPageTemplate(
        ThemeManager $themeManager,
        string $pageKey,
        string $capturedContent,
        array $context,
        array $layoutVars
    ): string {
        $templateKey = $pageKey === 'events' ? self::eventTemplateKey($context) : $pageKey;
        $pageVars = self::arrayValue($context, 'page_vars');
        $pageTitle = (string) ($layoutVars['page_title'] ?? $context['page_title'] ?? 'TurkMod');
        $profileVars = self::profileTemplateVars($pageVars, (string) ($layoutVars['base_url'] ?? ''), $pageKey);
        $usesStructuredUploadForm = in_array($pageKey, ['upload_topic', 'edit_topic'], true);
        $baseVars = array_replace($pageVars, [
            'site_name' => (string) ($layoutVars['site_name'] ?? 'TurkMod'),
            'site_description' => (string) ($layoutVars['site_description'] ?? ''),
            'base_url' => (string) ($layoutVars['base_url'] ?? ''),
            'page_title' => $pageTitle,
            'page_description' => (string) ($layoutVars['page_description'] ?? ''),
            'search_query' => (string) ($_GET['q'] ?? ''),
            'result_count' => (string) (is_countable($pageVars['items'] ?? null) ? count($pageVars['items']) : ''),
            'content' => empty($profileVars['use_captured_content']) && in_array($pageKey, ['profile', 'public_profile'], true) ? '' : $capturedContent,
            'profile' => $profileVars,
            'upload' => $usesStructuredUploadForm ? self::uploadFormVars($pageVars, (string) ($layoutVars['base_url'] ?? ''), $pageKey) : [],
            'csrf_field' => function_exists('csrf_field') ? csrf_field() : '',
        ]);

        if ($pageKey === 'events') {
            return $capturedContent;
        }

        $slot = match ($pageKey) {
            default => 'content',
        };
        if (!$usesStructuredUploadForm && (!in_array($pageKey, ['profile', 'public_profile'], true) || !empty($profileVars['use_captured_content']))) {
            $baseVars[$slot] = $capturedContent;
        }

        try {
            return $themeManager->render($templateKey, $baseVars, [
                'content',
                'csrf_field',
                'profile.pagination_html',
                'profile.topics_pagination_html',
                'profile.comments_pagination_html',
                'profile.favorites_pagination_html',
                'profile.reports_pagination_html',
                'profile.activity_pagination_html',
                'profile_pagination_html',
            ]);
        } catch (Throwable $error) {
            if (function_exists('appLogException')) {
                appLogException($error, ['source' => 'PublicThemeRenderer page template', 'page_key' => $pageKey]);
            }

            return self::renderThemeErrorPanel($error, $pageKey, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderTopicTemplate(ThemeManager $themeManager, string $capturedContent, array $context): string
    {
        $pageVars = self::arrayValue($context, 'page_vars');
        $topic = isset($pageVars['topic']) && is_array($pageVars['topic']) ? $pageVars['topic'] : [];
        if ($topic === []) {
            return self::renderThemeErrorPanel(new RuntimeException('Topic data is missing for theme render.'), 'topic', $context);
        }

        try {
            $baseUri = (string) ($context['base_uri'] ?? '');
            $settings = self::arrayValue($context, 'settings');
            $pdo = $context['pdo'] ?? ($GLOBALS['pdo'] ?? null);
            $topicVars = self::topicVars($topic, $pageVars, $baseUri, $settings);
            $topicVars = array_replace(
                $topicVars,
                self::topicDescriptionVars($topic, $pageVars),
                self::topicMediaVars($topic, $baseUri, $pdo, $settings),
                self::topicDetailsVars($topic, $pageVars, $baseUri, $settings),
                self::topicReportVars($topic, $baseUri, !empty($context['is_logged_in'])),
                self::topicDownloadVars($topic, $baseUri, $settings, $pdo),
                self::topicTagVars($topic, $baseUri, $settings),
                self::topicRelatedVars($topic, $baseUri, $pdo, $settings),
                self::topicCommentsVars($topic, $baseUri, $settings, !empty($context['is_logged_in']))
            );

            $rendered = $themeManager->render('topic', [
                'page_title' => (string) ($pageVars['pageTitle'] ?? $topic['title'] ?? ''),
                'page_description' => (string) ($pageVars['metaDescription'] ?? ''),
                'topic' => $topicVars,
                'topics' => $topicVars['related_topics'] ?? [],
                'content' => '',
                'description_html' => '',
                'media_html' => '',
                'details_html' => '',
                'report_html' => '',
                'comments_html' => '',
            ], ['content', 'topic.description_html']);

            return trim($rendered) !== ''
                ? trim($rendered)
                : self::renderThemeErrorPanel(new RuntimeException('Theme topic template rendered empty.'), 'topic', $context);
        } catch (Throwable $error) {
            if (function_exists('appLogException')) {
                appLogException($error, ['source' => 'PublicThemeRenderer topic template']);
            }

            return self::renderThemeErrorPanel($error, 'topic', $context);
        }
    }

    private static function renderRawPartial(ThemeManager $themeManager, string $templateKey, string $slot, string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $rendered = trim($themeManager->render($templateKey, [
            $slot => $html,
        ], [$slot]));

        return $rendered !== '' ? $rendered : trim($html);
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $pageVars
     * @return array<string, mixed>
     */
    private static function topicDescriptionVars(array $topic, array $pageVars): array
    {
        $description = (string) ($pageVars['cleanTopicDescription'] ?? '');
        if ($description === '') {
            $description = (string) ($topic['topic_descriptions'] ?? $topic['content'] ?? '');
            if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
                $description = topicDescriptionWithoutRepeatedTitle($description, (string) ($topic['title'] ?? ''));
            }
        }

        $body = function_exists('sanitizeTopicHtml')
            ? sanitizeTopicHtml($description)
            : nl2br(htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8'));

        return [
            'description_html' => $body,
            'has_description' => trim(strip_tags($body)) !== '',
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @return array<string, mixed>
     */
    private static function topicMediaVars(array $topic, string $baseUri, mixed $pdo, array $settings): array
    {
        if (($settings['topic_detail_show_media'] ?? '1') !== '1') {
            return [
                'has_media' => false,
                'has_media_slides' => false,
                'has_media_thumbs' => false,
                'media_slides' => [],
                'media_slides_json' => '[]',
                'media_slide_count' => 0,
                'has_media_links' => false,
                'media_links' => [],
                'show_media_placeholder' => false,
            ];
        }

        $mediaLinks = function_exists('getTopicMediaGallery')
            ? getTopicMediaGallery($pdo instanceof PDO ? $pdo : null, (int) ($topic['id'] ?? 0))
            : [];
        if (!is_array($mediaLinks)) {
            $mediaLinks = [];
        }

        $slides = [];
        $links = [];
        foreach ($mediaLinks as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $match) === 1) {
                $slides[] = ['type' => 'youtube', 'id' => $match[1], 'thumb' => 'https://img.youtube.com/vi/' . $match[1] . '/mqdefault.jpg'];
            } elseif (preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/i', $url, $match) === 1) {
                $slides[] = ['type' => 'vimeo', 'id' => $match[1], 'thumb' => ''];
            } elseif (preg_match('/\.(mp4|webm|ogg)$/i', $url) === 1) {
                $slides[] = ['type' => 'video', 'url' => self::absoluteMediaUrl($url, $baseUri), 'thumb' => ''];
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url) === 1) {
                $src = self::absoluteMediaUrl($url, $baseUri);
                $slides[] = ['type' => 'image', 'url' => $src, 'thumb' => $src];
            } else {
                $links[] = ['url' => $url, 'label' => basename($url)];
            }
        }

        $slideRows = [];
        foreach ($slides as $index => $slide) {
            $thumb = (string) ($slide['thumb'] ?? '');
            $slideRows[] = [
                'index' => $index,
                'number' => $index + 1,
                'type' => (string) ($slide['type'] ?? ''),
                'thumb' => $thumb,
                'has_thumb' => $thumb !== '',
                'active_class' => $index === 0 ? 'active' : '',
                'current_attr' => $index === 0 ? 'aria-current="true"' : '',
            ];
        }

        return [
            'has_media' => $slides !== [] || $links !== [] || true,
            'has_media_slides' => $slides !== [],
            'has_media_thumbs' => count($slides) > 1,
            'media_slides' => $slideRows,
            'media_slides_json' => json_encode($slides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '[]',
            'media_slide_count' => count($slides),
            'has_media_links' => $links !== [],
            'media_links' => $links,
            'show_media_placeholder' => $slides === [] && $links === [],
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function topicDetailsVars(array $topic, array $pageVars, string $baseUri, array $settings): array
    {
        if (($settings['topic_detail_show_info_panel'] ?? '1') !== '1') {
            return [
                'has_details' => false,
                'info_rows' => [],
            ];
        }

        $rows = [];
        $category = (string) ($topic['category'] ?? 'Genel');
        $categorySlug = (string) ($topic['category_slug'] ?? '');
        $published = function_exists('formatAppDate')
            ? formatAppDate((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now'), $GLOBALS['pdo'] ?? null)
            : self::formatDate((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now'));

        if (($settings['show_author_info'] ?? '1') === '1') {
            $authorUrl = '';
            if (!empty($topic['author_id']) && function_exists('publicProfileUrl')) {
                $authorUrl = publicProfileUrl(['id' => (int) $topic['author_id'], 'name' => (string) ($topic['author'] ?? 'Anonim')]);
            }
            $rows[] = ['icon' => 'bi-person-badge', 'label' => 'Konu Sahibi', 'value' => (string) ($topic['author'] ?? 'Anonim'), 'url' => $authorUrl];
        }

        $rows[] = ['icon' => 'bi-calendar3', 'label' => 'Yayın Tarihi', 'value' => $published, 'url' => ''];
        if (!empty($topic['author_topic']) || !empty($topic['topic_version'])) {
            $rows[] = ['icon' => 'bi-tools', 'label' => 'Mod Yapımcısı', 'value' => (string) ($topic['author_topic'] ?? '-'), 'url' => ''];
            $rows[] = ['icon' => 'bi-controller', 'label' => 'Oyun Sürümü', 'value' => (string) ($topic['topic_version'] ?? '-'), 'url' => ''];
        }

        $categoryUrl = $categorySlug !== '' && function_exists('categoryUrl') ? categoryUrl($categorySlug) : '#';
        $rows[] = ['icon' => 'bi-folder2-open', 'label' => 'Kategori', 'value' => $category, 'url' => $categoryUrl];
        $views = (int) ($topic['view_count'] ?? 0);
        if (($settings['show_view_count'] ?? '1') === '1' && $views > 0) {
            $rows[] = ['icon' => 'bi-eye', 'label' => 'Görüntülenme', 'value' => number_format($views, 0, ',', '.'), 'url' => ''];
        }

        return [
            'has_details' => $rows !== [],
            'info_rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @return array<string, mixed>
     */
    private static function topicReportVars(array $topic, string $baseUri, bool $isLoggedIn): array
    {
        $reasons = [];
        $reasonLabels = function_exists('topicReportReasonLabels') ? topicReportReasonLabels() : ['other' => 'Diger'];
        foreach ($reasonLabels as $value => $label) {
            $reasons[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return [
            'report_logged_in' => $isLoggedIn,
            'report_endpoint' => rtrim($baseUri, '/') . '/api/reports.php',
            'report_reasons' => $reasons,
            'csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function topicDownloadVars(array $topic, string $baseUri, array $settings, mixed $pdo): array
    {
        // Keep download-gate behavior synced with live admin settings.
        $runtimeSettings = function_exists('getAdminSettings')
            ? getAdminSettings($pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null))
            : [];
        if (is_array($runtimeSettings) && $runtimeSettings !== []) {
            foreach ([
                'topic_detail_show_download_panel',
                'download_countdown_seconds',
                'download_ready_text',
                'download_wait_text',
                'download_done_text',
                'download_show_counts',
                'download_access_mode',
                'download_access_comment_requirement',
                'download_access_login_message',
                'download_access_comment_message',
                'download_access_locked_button_text',
                'download_access_comment_cta_label',
                'download_access_open_auth_popup',
                'download_access_focus_comment_form',
                'download_access_unlock_after_auth',
                'download_access_unlock_after_comment',
                'download_access_auth_modal_title',
                'download_access_auth_login_label',
                'download_access_auth_register_label',
                'download_access_auth_success_message',
            ] as $downloadKey) {
                if (array_key_exists($downloadKey, $runtimeSettings)) {
                    $settings[$downloadKey] = $runtimeSettings[$downloadKey];
                }
            }
        }

        $readyText = trim((string) ($settings['download_ready_text'] ?? 'Indirmek icin tiklayiniz')) ?: 'Indirmek icin tiklayiniz';
        $waitText = trim((string) ($settings['download_wait_text'] ?? 'Indirme linkiniz kontrol ediliyor, lutfen bekleyiniz')) ?: 'Indirme linkiniz kontrol ediliyor, lutfen bekleyiniz';
        $doneText = trim((string) ($settings['download_done_text'] ?? 'Indirme linkiniz hazir, indirmek icin tiklayin')) ?: 'Indirme linkiniz hazir, indirmek icin tiklayin';
        $topicId = (int) ($topic['id'] ?? 0);
        $currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        $downloadStatusApi = rtrim($baseUri, '/') . '/api/download-access.php';
        $downloadAuthApi = rtrim($baseUri, '/') . '/api/auth-popup.php';
        $downloadLoginUrl = function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : ($baseUri . '/giris');
        $downloadRegisterUrl = function_exists('routePublicStaticUrl') ? routePublicStaticUrl('register') : ($baseUri . '/kayit');
        $downloadLockButtonText = trim((string) ($settings['download_access_locked_button_text'] ?? 'Kilidi Ac')) ?: 'Kilidi Ac';
        $downloadCommentCtaLabel = trim((string) ($settings['download_access_comment_cta_label'] ?? 'Yorumlara Git')) ?: 'Yorumlara Git';
        $downloadAuthModalTitle = trim((string) ($settings['download_access_auth_modal_title'] ?? 'Indirme linklerini acmak icin giris yapin')) ?: 'Indirme linklerini acmak icin giris yapin';
        $downloadAuthLoginLabel = trim((string) ($settings['download_access_auth_login_label'] ?? 'Giris Yap')) ?: 'Giris Yap';
        $downloadAuthRegisterLabel = trim((string) ($settings['download_access_auth_register_label'] ?? 'Kayit Ol')) ?: 'Kayit Ol';
        $downloadAuthSuccessMessage = trim((string) ($settings['download_access_auth_success_message'] ?? 'Oturum basariyla acildi. Kilitli indirme kartlari guncelleniyor.')) ?: 'Oturum basariyla acildi. Kilitli indirme kartlari guncelleniyor.';
        $downloadOpenAuthPopup = (string) ($settings['download_access_open_auth_popup'] ?? '1') === '1';
        $downloadFocusCommentForm = (string) ($settings['download_access_focus_comment_form'] ?? '1') === '1';
        $downloadUnlockAfterAuth = (string) ($settings['download_access_unlock_after_auth'] ?? '1') === '1';
        $downloadUnlockAfterComment = (string) ($settings['download_access_unlock_after_comment'] ?? '1') === '1';

        $downloadAccessState = function_exists('topicDownloadAccessState')
            ? topicDownloadAccessState($pdo instanceof PDO ? $pdo : null, $settings, $topicId, $currentUserId)
            : ['locked' => false, 'reason' => 'none', 'message' => '', 'mode' => 'public'];
        $downloadLocked = !empty($downloadAccessState['locked']);
        $downloadLockReason = trim((string) ($downloadAccessState['reason'] ?? 'none')) ?: 'none';
        $downloadLockMessage = trim((string) ($downloadAccessState['message'] ?? ''));
        if ($downloadLockMessage === '') {
            $downloadLockMessage = $downloadLockReason === 'comment_required'
                ? 'Indirme linklerini gormek icin once yorum yapmaniz gerekir.'
                : 'Bu icerigi gormek icin kayit olmaniz veya giris yapmaniz gerekir.';
        }
        $downloadCommentTarget = function_exists('topicUrl')
            ? topicUrl((string) ($topic['slug'] ?? ''), $topicId) . '#comments-heading'
            : (rtrim($baseUri, '/') . '/topic.php?id=' . $topicId . '#comments-heading');

        if (($settings['topic_detail_show_download_panel'] ?? '1') !== '1') {
            return [
                'has_downloads' => false,
                'download_links' => [],
                'download_countdown' => max(0, (int) ($settings['download_countdown_seconds'] ?? 5)),
                'download_ready_text' => $readyText,
                'download_wait_text' => $waitText,
                'download_done_text' => $doneText,
                'download_locked' => false,
                'download_lock_reason' => 'none',
                'download_lock_message' => '',
            ];
        }

        $links = function_exists('getTopicDownloadLinks')
            ? getTopicDownloadLinks($pdo instanceof PDO ? $pdo : null, (int) ($topic['id'] ?? 0), (string) ($topic['download_links'] ?? ''))
            : [];
        if (!is_array($links)) {
            $links = [];
        }

        $showCounts = (string) ($settings['download_show_counts'] ?? '1') === '1';
        $rows = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $id = (int) ($link['id'] ?? 0);
            $href = $id > 0
                ? (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('download') : (rtrim($baseUri, '/') . '/download.php')) . '?id=' . $id
                : (function_exists('safeExternalUrl') ? safeExternalUrl($url, '') : $url);
            if ($href === '') {
                continue;
            }
            $cardButtonText = $readyText;
            if ($downloadLocked) {
                $cardButtonText = $downloadLockReason === 'comment_required'
                    ? $downloadCommentCtaLabel
                    : $downloadLockButtonText;
            }
            $rows[] = [
                'href' => $downloadLocked ? '#' : $href,
                'download_href' => $href,
                'name' => trim((string) ($link['name'] ?? '')) ?: 'Indirme Linki',
                'host' => (string) (parse_url($url, PHP_URL_HOST) ?: $url),
                'show_count' => $showCounts,
                'count' => number_format((int) ($link['download_count'] ?? 0), 0, ',', '.'),
                'locked' => $downloadLocked,
                'lock_reason' => $downloadLockReason,
                'lock_message' => $downloadLockMessage,
                'button_text' => $cardButtonText,
            ];
        }

        return [
            'has_downloads' => $rows !== [],
            'download_links' => $rows,
            'download_countdown' => max(0, (int) ($settings['download_countdown_seconds'] ?? 5)),
            'download_ready_text' => $readyText,
            'download_wait_text' => $waitText,
            'download_done_text' => $doneText,
            'download_topic_id' => $topicId,
            'download_locked' => $downloadLocked,
            'download_lock_reason' => $downloadLockReason,
            'download_lock_message' => $downloadLockMessage,
            'download_lock_button_text' => $downloadLockButtonText,
            'download_comment_cta_label' => $downloadCommentCtaLabel,
            'download_open_auth_popup' => $downloadOpenAuthPopup ? '1' : '0',
            'download_focus_comment_form' => $downloadFocusCommentForm ? '1' : '0',
            'download_unlock_after_auth' => $downloadUnlockAfterAuth ? '1' : '0',
            'download_unlock_after_comment' => $downloadUnlockAfterComment ? '1' : '0',
            'download_auth_modal_title' => $downloadAuthModalTitle,
            'download_auth_login_label' => $downloadAuthLoginLabel,
            'download_auth_register_label' => $downloadAuthRegisterLabel,
            'download_auth_success_message' => $downloadAuthSuccessMessage,
            'download_login_url' => $downloadLoginUrl,
            'download_register_url' => $downloadRegisterUrl,
            'download_status_api' => $downloadStatusApi,
            'download_auth_api' => $downloadAuthApi,
            'download_comment_target' => $downloadCommentTarget,
            'download_current_request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'download_csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
        ];
    }
    /**
     * @param array<string, mixed> $topic
     * @return array<string, mixed>
     */
    private static function topicTagVars(array $topic, string $baseUri, array $settings): array
    {
        if (($settings['topic_detail_show_tags'] ?? '1') !== '1') {
            return [
                'has_tags' => false,
                'tags' => [],
            ];
        }

        $source = $topic['tags'] ?? $topic['topic_tags'] ?? [];
        if (is_string($source)) {
            $source = preg_split('/[,;#]+/', $source) ?: [];
        }
        if (!is_array($source)) {
            $source = [];
        }

        $seen = [];
        $tags = [];
        foreach ($source as $tag) {
            $label = trim(strip_tags((string) $tag));
            if ($label === '') {
                continue;
            }
            $key = mb_strtolower($label, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = [
                'label' => $label,
                'url' => rtrim($baseUri, '/') . '/index.php?q=' . rawurlencode($label),
            ];
            if (count($tags) >= 12) {
                break;
            }
        }

        return [
            'has_tags' => $tags !== [],
            'tags' => $tags,
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function topicRelatedVars(array $topic, string $baseUri, mixed $pdo, array $settings): array
    {
        if (($settings['show_related_topics'] ?? '1') !== '1') {
            return [
                'has_related_topics' => false,
                'related_topics' => [],
            ];
        }

        $topicId = (int) ($topic['id'] ?? 0);
        $categoryId = (int) ($topic['category_id'] ?? 0);
        $limit = max(1, min(12, (int) ($settings['topic_detail_related_limit'] ?? 4)));
        $related = [];

        if ($pdo instanceof PDO && $topicId > 0 && $categoryId > 0) {
            try {
                $statement = $pdo->prepare(
                    "SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, parent.slug AS parent_slug, u.name AS author, u.id AS author_id
                     FROM topics t
                     LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                     LEFT JOIN categories cat ON cat.id = t.category_id
                     LEFT JOIN categories parent ON parent.id = cat.parent_id
                     LEFT JOIN users u ON u.id = t.author_id
                     WHERE t.id != :topic_id
                       AND t.category_id = :category_id
                       AND t.status = 'published'
                       AND t.deleted_at IS NULL
                     ORDER BY (t.download_count * 3 + t.view_count + t.comment_count * 8) DESC, COALESCE(t.published_at, t.created_at) DESC, t.id DESC
                     LIMIT {$limit}"
                );
                $statement->execute([
                    'topic_id' => $topicId,
                    'category_id' => $categoryId,
                ]);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $related[] = self::topicCardVars($row, $baseUri, $pdo, $settings);
                    }
                }
            } catch (Throwable $error) {
                if (function_exists('appLogException')) {
                    appLogException($error, ['source' => 'PublicThemeRenderer related topics', 'topic_id' => $topicId]);
                }
            }
        }

        return [
            'has_related_topics' => $related !== [],
            'related_topics' => $related,
        ];
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function topicCommentsVars(array $topic, string $baseUri, array $settings, bool $isLoggedIn): array
    {
        if (($settings['topic_detail_comments_enabled'] ?? '1') !== '1') {
            return [
                'comments_enabled' => false,
                'comments_api' => rtrim($baseUri, '/') . '/api/comments.php',
                'comments_logged_in' => '0',
                'comments_logged_in_bool' => false,
                'current_user_name' => '',
                'current_user_avatar' => '',
                'avatar_fallback' => function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri) : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg',
                'current_user_initial' => 'U',
                'comment_report_enabled' => '0',
                'author' => (string) ($topic['author'] ?? ''),
                'comment_poll' => 0,
                'comment_max_length' => defined('COMMENT_MAX_LENGTH') ? (int) COMMENT_MAX_LENGTH : 2000,
                'csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
            ];
        }

        $userName = (string) ($_SESSION['_auth_user_name'] ?? '');
        $avatar = self::currentUserAvatar($baseUri);
        $avatar = function_exists('resolveAvatarUrl')
            ? resolveAvatarUrl($avatar, $baseUri, $isLoggedIn)
            : ($avatar !== '' ? self::absoluteMediaUrl($avatar, $baseUri) : '');
        $avatarFallback = function_exists('defaultAvatarUrl')
            ? defaultAvatarUrl($baseUri)
            : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        $maxLength = defined('COMMENT_MAX_LENGTH') ? (int) COMMENT_MAX_LENGTH : 2000;

        return [
            'comments_enabled' => true,
            'comments_api' => rtrim($baseUri, '/') . '/api/comments.php',
            'comments_logged_in' => $isLoggedIn ? '1' : '0',
            'comments_logged_in_bool' => $isLoggedIn,
            'current_user_name' => $userName,
            'current_user_avatar' => $avatar,
            'avatar_fallback' => $avatarFallback,
            'current_user_initial' => self::initial($userName !== '' ? $userName : 'U'),
            'comment_report_enabled' => (($settings['comment_report_enabled'] ?? '1') === '1') ? '1' : '0',
            'author' => (string) ($topic['author'] ?? ''),
            'comment_poll' => (int) ($settings['comment_realtime_poll'] ?? 15),
            'comment_max_length' => $maxLength,
            'csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderThemeErrorPanel(Throwable $error, string $templateKey, array $context): string
    {
        $debug = !empty($GLOBALS['appDebug']);
        $title = $debug ? 'Tema render hatası' : 'Sayfa görünümü hazırlanamadı';
        $message = $debug
            ? $templateKey . ': ' . $error->getMessage()
            : 'Aktif tema bu sayfayi guvenli bicimde olusturamadi. Hata kayda alindi.';

        return '<section class="ui-theme-theme-error" role="alert">' .
            '<div class="ui-theme-theme-error__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>' .
            '<div><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' .
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
            '</p></div></section>';
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderStandaloneThemeErrorPage(Throwable $error, string $templateKey, array $context): string
    {
        $debug = !empty($GLOBALS['appDebug']);
        $title = $debug ? 'Tema layout hatası' : 'Site geçici olarak hazır değil';
        $message = $debug
            ? $templateKey . ': ' . $error->getMessage()
            : "Aktif tema layout'u guvenli bicimde olusturulamadi. Hata kayda alindi.";
        $baseUri = rtrim((string) ($context['base_uri'] ?? ($GLOBALS['baseUri'] ?? '')), '/');
        $themeErrorCssPath = dirname(__DIR__) . '/assets/css/theme-error.css';
        $themeErrorCssHref = $baseUri . '/assets/css/theme-error.css?v=' . rawurlencode((string) (is_file($themeErrorCssPath) ? filemtime($themeErrorCssPath) : time()));

        return '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '</title><link rel="stylesheet" href="' .
            htmlspecialchars($themeErrorCssHref, ENT_QUOTES, 'UTF-8') .
            '"></head><body><main class="theme-error"><section class="theme-error__card"><h1>' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '</h1><p>' .
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
            '</p></section></main></body></html>';
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function eventTemplateKey(array $context): string
    {
        $requestUri = (string) ($context['current_request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
        $path = '/' . trim((string) parse_url($requestUri, PHP_URL_PATH), '/');
        $baseUri = trim((string) ($context['base_uri'] ?? ($GLOBALS['baseUri'] ?? '')), '/');
        $segments = array_values(array_filter(explode('/', trim(rawurldecode($path), '/')), static fn (string $part): bool => $part !== ''));
        if ($baseUri !== '') {
            $baseSegments = array_values(array_filter(explode('/', $baseUri), static fn (string $part): bool => $part !== ''));
            if ($baseSegments !== [] && array_slice($segments, 0, count($baseSegments)) === $baseSegments) {
                $segments = array_slice($segments, count($baseSegments));
            }
        }

        $eventPage = (string) ($segments[1] ?? 'index');
        return match ($eventPage) {
            'wheel' => 'events.wheel',
            'raffle' => 'events.raffle',
            'rewards' => 'events.rewards',
            'tasks' => 'events.tasks',
            default => 'events.index',
        };
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $pageVars
     */
    private static function topicDescriptionSection(array $topic, array $pageVars): string
    {
        $description = (string) ($pageVars['cleanTopicDescription'] ?? '');
        if ($description === '') {
            $description = (string) ($topic['topic_descriptions'] ?? $topic['content'] ?? '');
            if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
                $description = topicDescriptionWithoutRepeatedTitle($description, (string) ($topic['title'] ?? ''));
            }
        }

        $body = function_exists('sanitizeTopicHtml')
            ? sanitizeTopicHtml($description)
            : nl2br(htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8'));

        return '<section class="topic-section topic-descriptions ui-section" aria-labelledby="desc-heading">' .
            '<h2 id="desc-heading">Açıklama</h2><div class="topic-content topic-detail-content ui-section">' .
            $body .
            '</div></section>';
    }

    /**
     * @param array<string, mixed> $topic
     */
    private static function topicMediaSection(array $topic, string $baseUri, mixed $pdo): string
    {
        $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo instanceof PDO ? $pdo : null) : [];
        if (($settings['topic_detail_show_media'] ?? '1') !== '1') {
            return '';
        }

        $mediaLinks = function_exists('getTopicMediaGallery')
            ? getTopicMediaGallery($pdo instanceof PDO ? $pdo : null, (int) ($topic['id'] ?? 0))
            : [];
        if (!is_array($mediaLinks)) {
            $mediaLinks = [];
        }

        $slides = [];
        $others = [];
        foreach ($mediaLinks as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $match) === 1) {
                $slides[] = ['type' => 'youtube', 'id' => $match[1], 'thumb' => 'https://img.youtube.com/vi/' . $match[1] . '/mqdefault.jpg'];
            } elseif (preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/i', $url, $match) === 1) {
                $slides[] = ['type' => 'vimeo', 'id' => $match[1], 'thumb' => ''];
            } elseif (preg_match('/\.(mp4|webm|ogg)$/i', $url) === 1) {
                $slides[] = ['type' => 'video', 'url' => self::absoluteMediaUrl($url, $baseUri), 'thumb' => ''];
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url) === 1) {
                $src = self::absoluteMediaUrl($url, $baseUri);
                $slides[] = ['type' => 'image', 'url' => $src, 'thumb' => $src];
            } else {
                $others[] = $url;
            }
        }

        $html = '<section class="topic-section topic-images-videos ui-section" aria-labelledby="media-heading"><h2 id="media-heading">Resim ve Videolar</h2>';
        if ($slides !== []) {
            $html .= '<div class="topic-carousel"><div class="topic-carousel-main">' .
                '<button id="ui-comment-prev" class="topic-carousel-nav topic-carousel-nav-prev" type="button" aria-label="Önceki medya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>' .
                '<button id="ui-comment-next" class="topic-carousel-nav topic-carousel-nav-next" type="button" aria-label="Sonraki medya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' .
                '<div id="ui-comment-content" class="topic-carousel-content ui-section"></div>' .
                '<div class="topic-carousel-counter" id="tcCounter" aria-live="polite">1 / ' . count($slides) . '</div></div>';
            if (count($slides) > 1) {
                $html .= '<div class="topic-carousel-thumbs" aria-label="Galeri önizlemeleri">';
                foreach ($slides as $index => $slide) {
                    $active = $index === 0 ? ' active' : '';
                    $current = $index === 0 ? ' aria-current="true"' : '';
                    $html .= '<button type="button" class="ui-comment-thumb' . $active . '" data-idx="' . $index . '" aria-label="Galeri görseli ' . ($index + 1) . '"' . $current . '>';
                    if (($slide['type'] ?? '') === 'image' || ($slide['type'] ?? '') === 'youtube') {
                        $html .= '<img src="' . htmlspecialchars((string) ($slide['thumb'] ?? ''), ENT_QUOTES, 'UTF-8') . '" alt="" title="Galeri görseli ' . ($index + 1) . '" loading="lazy" decoding="async" width="90" height="60">';
                    } else {
                        $html .= '<i class="bi bi-play-circle-fill" aria-hidden="true"></i>';
                    }
                    $html .= '</button>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        if ($others !== []) {
            $html .= '<div class="topic-other-links mt-3">';
            foreach ($others as $url) {
                $html .= '<div class="topic-media-item topic-media-item-inline"><a href="' .
                    htmlspecialchars($url, ENT_QUOTES, 'UTF-8') .
                    '" target="_blank" rel="noopener" class="topic-media-link-inline"><i class="bi bi-link-45deg" aria-hidden="true"></i> ' .
                    htmlspecialchars(basename($url), ENT_QUOTES, 'UTF-8') .
                    '</a></div>';
            }
            $html .= '</div>';
        }

        if ($slides === [] && $others === []) {
            $html .= '<div class="topic-media-grid ui-grid"><div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div><div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div><div class="topic-media-placeholder"><i class="bi bi-play-circle" aria-hidden="true"></i></div></div>';
        }

        return $html . '</section>';
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $settings
     */
    private static function topicDetailsSection(array $topic, array $pageVars, string $baseUri, array $settings): string
    {
        if (($settings['topic_detail_show_info_panel'] ?? '1') !== '1') {
            return '';
        }

        $category = (string) ($topic['category'] ?? 'Genel');
        $categorySlug = (string) ($topic['category_slug'] ?? '');
        $published = function_exists('formatAppDate')
            ? formatAppDate((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now'), $GLOBALS['pdo'] ?? null)
            : date('d.m.Y', strtotime((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now')) ?: time());
        $showAuthor = ($settings['show_author_info'] ?? '1') === '1';
        $showViews = ($settings['show_view_count'] ?? '1') === '1';
        $views = (int) ($topic['view_count'] ?? 0);
        $html = '<section class="topic-section topic-details ui-section" aria-labelledby="content-info-heading"><h2 id="content-info-heading">İçerik Bilgileri</h2><div class="topic-info-grid ui-grid">';

        if ($showAuthor) {
            $author = htmlspecialchars((string) ($topic['author'] ?? 'Anonim'), ENT_QUOTES, 'UTF-8');
            $authorHtml = $author;
            if (!empty($topic['author_id']) && function_exists('publicProfileUrl')) {
                $authorHtml = '<a href="' . htmlspecialchars(publicProfileUrl(['id' => (int) $topic['author_id'], 'name' => (string) ($topic['author'] ?? 'Anonim')]), ENT_QUOTES, 'UTF-8') . '">' . $author . '</a>';
            }
            $html .= self::topicInfoRow('bi-person-badge', 'Konu Sahibi', $authorHtml, true);
        }

        $html .= self::topicInfoRow('bi-calendar3', 'Yayın Tarihi', htmlspecialchars($published, ENT_QUOTES, 'UTF-8'), true);
        if (!empty($topic['author_topic']) || !empty($topic['topic_version'])) {
            $html .= self::topicInfoRow('bi-tools', 'Mod Yapımcısı', htmlspecialchars((string) ($topic['author_topic'] ?? '-'), ENT_QUOTES, 'UTF-8'), true);
            $html .= self::topicInfoRow('bi-controller', 'Oyun Sürümü', htmlspecialchars((string) ($topic['topic_version'] ?? '-'), ENT_QUOTES, 'UTF-8'), true);
        }

        $categoryUrl = $categorySlug !== '' && function_exists('categoryUrl') ? categoryUrl($categorySlug) : '#';
        $html .= self::topicInfoRow('bi-folder2-open', 'Kategori', '<a href="' . htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</a>', true);
        if ($showViews && $views > 0) {
            $html .= self::topicInfoRow('bi-eye', 'Görüntülenme', number_format($views, 0, ',', '.'), true);
        }

        return $html . '</div></section>';
    }

    private static function topicInfoRow(string $icon, string $label, string $value, bool $rawValue = false): string
    {
        $valueHtml = $rawValue ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Uzun değerler ellipsis ile kısaldığında tam metni tooltip olarak göster.
        $plainValue = trim(html_entity_decode(strip_tags($valueHtml), ENT_QUOTES, 'UTF-8'));
        $titleAttr = $plainValue !== ''
            ? ' title="' . htmlspecialchars($plainValue, ENT_QUOTES, 'UTF-8') . '" data-info-value tabindex="0"'
            : '';

        return '<div class="topic-info-row"><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i><span>' .
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
            '</span><strong' . $titleAttr . '>' .
            $valueHtml .
            '</strong></div>';
    }

    /**
     * @param array<string, mixed> $topic
     */
    private static function topicReportSection(array $topic, string $baseUri, bool $isLoggedIn): string
    {
        $html = '<div class="topic-report-modal" id="topicReportModal" role="dialog" aria-modal="true" aria-labelledby="report-heading" hidden aria-hidden="true"><div class="topic-report-backdrop" data-report-modal-close data-ui-modal-close></div><div class="topic-report-dialog ui-panel"><div class="topic-report-header ui-panel__head"><h2 id="report-heading"><i class="bi bi-flag" aria-hidden="true"></i> İçeriği Raporla</h2><button type="button" class="topic-report-close" data-report-modal-close data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>';
        if ($isLoggedIn) {
            $html .= '<form class="topic-report-form" action="' . htmlspecialchars(rtrim($baseUri, '/') . '/api/reports.php', ENT_QUOTES, 'UTF-8') . '" method="post">';
            $html .= function_exists('csrf_field') ? csrf_field() : '';
            $html .= '<input type="hidden" name="action" value="create"><input type="hidden" name="topic_id" value="' . (int) ($topic['id'] ?? 0) . '"><div class="topic-report-grid ui-grid"><label><span>Neden</span><select name="reason" required>';
            $reasons = function_exists('topicReportReasonLabels') ? topicReportReasonLabels() : ['other' => 'Diğer'];
            foreach ($reasons as $value => $label) {
                $html .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select></label><label><span>Detay</span><textarea name="details" rows="3" maxlength="1000" placeholder="Ek bilgi varsa yazın"></textarea></label></div><button type="submit" class="topic-report-submit"><i class="bi bi-send" aria-hidden="true"></i> Rapor Gönder</button><div class="topic-report-feedback" aria-live="polite"></div></form>';
        } else {
            $html .= '<div class="topic-report-login"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><span>Rapor göndermek için giriş yapmalısınız.</span><a href="' . htmlspecialchars((function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : (rtrim($baseUri, '/') . '/giris')), ENT_QUOTES, 'UTF-8') . '">Giriş yap</a></div>';
        }

        return $html . '</div></div>';
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $settings
     */
    private static function topicDownloadSection(array $topic, string $baseUri, array $settings, mixed $pdo): string
    {
        if (($settings['topic_detail_show_download_panel'] ?? '1') !== '1') {
            return '';
        }

        $links = function_exists('getTopicDownloadLinks')
            ? getTopicDownloadLinks($pdo instanceof PDO ? $pdo : null, (int) ($topic['id'] ?? 0), (string) ($topic['download_links'] ?? ''))
            : [];
        if (!is_array($links) || $links === []) {
            return '';
        }

        $countdown = max(0, (int) ($settings['download_countdown_seconds'] ?? 5));
        $readyText = trim((string) ($settings['download_ready_text'] ?? 'İndirmek için tıklayınız')) ?: 'İndirmek için tıklayınız';
        $waitText = trim((string) ($settings['download_wait_text'] ?? 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz')) ?: 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz';
        $doneText = trim((string) ($settings['download_done_text'] ?? 'İndirme linkiniz hazır, indirmek için tıklayın')) ?: 'İndirme linkiniz hazır, indirmek için tıklayın';
        $showCounts = (string) ($settings['download_show_counts'] ?? '1') === '1';
        $html = '<section class="topic-section topic-downloads topic-download-links ui-section" aria-labelledby="dl-heading"><h2 id="dl-heading">İndirme Bağlantıları</h2><div class="topic-dl-trust" role="note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>İndirme bağlantısı açılmadan önce kısa bir güvenlik beklemesi uygulanır.</span></div><div class="topic-dl-section ui-section" data-countdown-seconds="' . $countdown . '" data-wait-text="' . htmlspecialchars($waitText, ENT_QUOTES, 'UTF-8') . '" data-done-text="' . htmlspecialchars($doneText, ENT_QUOTES, 'UTF-8') . '"><div class="download-grid topic-dl-grid ui-grid">';
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $id = (int) ($link['id'] ?? 0);
            $name = trim((string) ($link['name'] ?? '')) ?: 'İndirme Linki';
            $href = $id > 0
                ? (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('download') : (rtrim($baseUri, '/') . '/download.php')) . '?id=' . $id
                : (function_exists('safeExternalUrl') ? safeExternalUrl($url, '') : $url);
            if ($href === '') {
                continue;
            }
            $host = parse_url($url, PHP_URL_HOST) ?: $url;
            $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener" class="download-card topic-dl-card ui-card"><div class="download-icon topic-dl-icon"><i class="bi bi-cloud-arrow-down" aria-hidden="true"></i></div><div class="download-info topic-dl-info"><strong>' .
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
                '</strong><small>' . htmlspecialchars((string) $host, ENT_QUOTES, 'UTF-8') . '</small>';
            if ($showCounts) {
                $html .= '<span class="download-count topic-dl-count"><i class="bi bi-download" aria-hidden="true"></i> ' . number_format((int) ($link['download_count'] ?? 0), 0, ',', '.') . ' indirme</span>';
            }
            $html .= '</div><span class="download-btn topic-dl-button"><span class="topic-dl-spinner"></span><span class="topic-dl-action">' . htmlspecialchars($readyText, ENT_QUOTES, 'UTF-8') . '</span></span></a>';
        }

        return $html . '</div></div></section>';
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $settings
     */
    private static function topicCommentsSection(array $topic, string $baseUri, array $settings, bool $isLoggedIn): string
    {
        if (($settings['topic_detail_comments_enabled'] ?? '1') !== '1') {
            return '';
        }

        $userName = (string) ($_SESSION['_auth_user_name'] ?? '');
        $avatar = self::currentUserAvatar($baseUri);
        $avatar = function_exists('resolveAvatarUrl')
            ? resolveAvatarUrl($avatar, $baseUri, $isLoggedIn)
            : ($avatar !== '' ? self::absoluteMediaUrl($avatar, $baseUri) : '');
        $avatarFallback = function_exists('defaultAvatarUrl')
            ? defaultAvatarUrl($baseUri)
            : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        $maxLength = defined('COMMENT_MAX_LENGTH') ? (int) COMMENT_MAX_LENGTH : 2000;
        $html = '<section class="topic-section topic-comments ui-section" aria-labelledby="comments-heading" data-topic-id="' . (int) ($topic['id'] ?? 0) . '" data-api="' . htmlspecialchars(rtrim($baseUri, '/') . '/api/comments.php', ENT_QUOTES, 'UTF-8') . '" data-csrf="' . htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8') . '" data-logged-in="' . ($isLoggedIn ? '1' : '0') . '" data-user-name="' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '" data-user-avatar="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" data-avatar-fallback="' . htmlspecialchars($avatarFallback, ENT_QUOTES, 'UTF-8') . '" data-report-enabled="' . (($settings['comment_report_enabled'] ?? '1') === '1' ? '1' : '0') . '" data-topic-author="' . htmlspecialchars((string) ($topic['author'] ?? ''), ENT_QUOTES, 'UTF-8') . '" data-poll="' . (int) ($settings['comment_realtime_poll'] ?? 15) . '">';
        $html .= '<div class="ui-comment-header ui-comment-header--compact ui-panel__head"><h2 id="comments-heading" class="ui-comment-header__title">Yorumlar <span class="ui-comment-count" id="tcCount">(0)</span></h2><div class="ui-comment-sort ui-comment-header__sort"><span class="ui-comment-sort-label">Sırala:</span><select class="ui-comment-sort-select" id="tcSort"><option value="asc">En Eski</option><option value="desc">En Yeni</option><option value="popular">Popüler</option><option value="liked">Beğenilenler</option><option value="disliked">Beğenilmeyenler</option></select></div></div>';
        if ($isLoggedIn) {
            $avatarHtml = function_exists('avatarImageHtml')
                ? avatarImageHtml($userName !== '' ? $userName : 'U', $avatar, ['base_uri' => $baseUri, 'width' => 48, 'height' => 48])
                : '<img src="' . htmlspecialchars($avatar !== '' ? $avatar : $avatarFallback, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($userName !== '' ? $userName : 'U', ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($userName !== '' ? $userName : 'U', ENT_QUOTES, 'UTF-8') . '" width="48" height="48" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="' . htmlspecialchars($avatarFallback, ENT_QUOTES, 'UTF-8') . '">';
            $form = '<div class="ui-comment-form-wrap" id="tcFormWrap"><div class="ui-comment-form-avatar">' . $avatarHtml . '</div><div class="ui-comment-form-body ui-panel__body"><textarea id="tcInput" class="ui-comment-textarea" placeholder="Düşüncelerini paylaş..." maxlength="' . $maxLength . '" rows="1"></textarea><div class="ui-comment-form-actions is-hidden" id="tcActions"><span class="ui-comment-char-count"><span id="tcCharCount">0</span>/' . $maxLength . '</span><div class="ui-comment-form-btns"><button type="button" class="ui-comment-btn-cancel" id="tcCancel">İptal</button><button type="button" class="ui-comment-btn-submit" id="tcSubmit" disabled>Gönder</button></div></div></div></div>';
        } else {
            $form = '<div class="ui-comment-login-prompt">Yorum yapmak için <a href="' . htmlspecialchars((function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : (rtrim($baseUri, '/') . '/giris')), ENT_QUOTES, 'UTF-8') . '">giriş yapın</a>.</div>';
        }
        $html .= $form . '<div class="ui-comment-list" id="tcList"><div class="ui-comment-loading" id="tcLoading"><div class="ui-comment-skeleton"><div class="ui-comment-skeleton-avatar"></div><div class="ui-comment-skeleton-body"><div class="ui-comment-skeleton-line ui-comment-skeleton-line--short"></div><div class="ui-comment-skeleton-line ui-comment-skeleton-line--full"></div><div class="ui-comment-skeleton-line ui-comment-skeleton-line--medium"></div></div></div></div></div><div class="ui-comment-load-more-wrap is-hidden" id="tcLoadMoreWrap"><button type="button" class="ui-comment-load-more-btn" id="tcLoadMore">Daha fazla yorum yükle</button></div><div class="ui-comment-pagination-info is-hidden" id="tcPaginationInfo"></div></section>';

        return $html;
    }

    private static function currentUserAvatar(string $baseUri): string
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO || empty($_SESSION['_auth_user_id'])) {
            return '';
        }

        $userId = (int) $_SESSION['_auth_user_id'];
        $avatarCache = $_SESSION['_auth_avatar_cache'] ?? null;
        $cacheTtl = 60;
        if (
            is_array($avatarCache)
            && (int) ($avatarCache['uid'] ?? 0) === $userId
            && (time() - (int) ($avatarCache['ts'] ?? 0)) <= $cacheTtl
        ) {
            return trim((string) ($avatarCache['raw'] ?? ''));
        }

        try {
            $statement = $pdo->prepare('SELECT avatar FROM users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            $avatar = trim((string) ($statement->fetchColumn() ?: ''));
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_auth_avatar_cache'] = [
                    'uid' => $userId,
                    'raw' => $avatar,
                    'ts' => time(),
                ];
            }
            return $avatar;
        } catch (Throwable) {
            return '';
        }
    }

    private static function absoluteMediaUrl(string $url, string $baseUri): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('~^(https?:)?//|^data:|^/~i', $url) === 1) {
            return $url;
        }

        return rtrim($baseUri, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function buildBreadcrumbHtml(ThemeManager $themeManager, array $context, string $pageKey, string $pageTitle, string $baseUri, bool $isEventsRequest): string
    {
        $items = self::breadcrumbItems($context, $pageKey, $pageTitle, $baseUri, $isEventsRequest);
        if ($items === []) {
            return '';
        }

        $lastIndex = count($items) - 1;
        $innerHtml = '';
        $breadcrumbItems = [];
        foreach ($items as $index => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if ($innerHtml !== '') {
                $innerHtml .= '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
            }

            $url = trim((string) ($item['url'] ?? ''));
            $icon = $index === 0 ? '<i class="bi bi-house-door" aria-hidden="true"></i> ' : '';
            if ($url !== '') {
                $current = $index === $lastIndex ? ' aria-current="page"' : '';
                $innerHtml .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $current . '>' . $icon . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                $current = $index === $lastIndex ? ' aria-current="page"' : '';
                $innerHtml .= '<span' . $current . '>' . $icon . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $breadcrumbItems[] = [
                'label' => $label,
                'url' => $url,
                'is_last' => $index === $lastIndex,
            ];
        }

        if ($innerHtml === '') {
            return '';
        }

        return $themeManager->render('partials.breadcrumb', [
            'breadcrumbs_html' => $innerHtml,
            'breadcrumb_items' => $breadcrumbItems,
        ], ['breadcrumbs_html']);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array{label: string, url?: string}>
     */
    private static function breadcrumbItems(array $context, string $pageKey, string $pageTitle, string $baseUri, bool $isEventsRequest): array
    {
        $pageVars = self::arrayValue($context, 'page_vars');
        $homeUrl = rtrim($baseUri, '/') . '/index.php';
        $items = [
            ['label' => 'Ana Sayfa', 'url' => $homeUrl],
        ];

        if ($isEventsRequest || $pageKey === 'events') {
            $items[] = ['label' => 'Etkinlikler', 'url' => (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('events') : (rtrim($baseUri, '/') . '/events'))];
            if ($pageTitle !== '' && $pageTitle !== 'Etkinlikler') {
                $items[] = ['label' => $pageTitle];
            }
            return $items;
        }

        if ($pageKey === 'home') {
            $search = trim((string) ($_GET['q'] ?? ''));
            if ($search !== '') {
                $items[] = ['label' => 'Arama', 'url' => $homeUrl . '?q=' . rawurlencode($search)];
                $items[] = ['label' => $search, 'url' => $homeUrl . '?q=' . rawurlencode($search)];
                return $items;
            }

            return [['label' => 'Ana Sayfa', 'url' => $homeUrl]];
        }

        if ($pageKey === 'category') {
            $categoryListUrl = function_exists('categoryListUrl') ? categoryListUrl() : (rtrim($baseUri, '/') . '/kategoriler');
            $items[] = ['label' => 'Kategoriler', 'url' => $categoryListUrl];
            if (!empty($pageVars['isSpecificCategory'])) {
                $parentName = trim((string) ($pageVars['categoryParentName'] ?? ''));
                $parentSlug = trim((string) ($pageVars['categoryActualParentSlug'] ?? ''));
                if ($parentName !== '' && $parentSlug !== '' && function_exists('categoryUrl')) {
                    $items[] = ['label' => $parentName, 'url' => categoryUrl($parentSlug)];
                }
                $categorySlug = trim((string) ($pageVars['categorySlug'] ?? ''));
                $categoryUrl = $categorySlug !== '' && function_exists('categoryUrl')
                    ? categoryUrl($categorySlug, $parentSlug)
                    : '';
                $items[] = ['label' => (string) ($pageVars['categoryName'] ?? $pageTitle), 'url' => $categoryUrl];
            }
            return $items;
        }

        if ($pageKey === 'topic' && isset($pageVars['topic']) && is_array($pageVars['topic'])) {
            $topic = $pageVars['topic'];
            $categoryListUrl = function_exists('categoryListUrl') ? categoryListUrl() : (rtrim($baseUri, '/') . '/kategoriler');
            $items[] = ['label' => 'Kategoriler', 'url' => $categoryListUrl];
            $category = trim((string) ($topic['category'] ?? ''));
            $categorySlug = trim((string) ($topic['category_slug'] ?? ''));
            $categoryParentSlug = trim((string) ($topic['parent_slug'] ?? ($topic['category_parent_slug'] ?? ($topic['parent_category_slug'] ?? ''))));
            $categoryParentName = trim((string) ($topic['parent_name'] ?? ($topic['category_parent_name'] ?? '')));
            if ($categorySlug !== '' && ($categoryParentSlug === '' || $categoryParentName === '')) {
                $parent = self::categoryParentForSlug($context['pdo'] ?? null, $categorySlug);
                $categoryParentSlug = $categoryParentSlug !== '' ? $categoryParentSlug : (string) ($parent['slug'] ?? '');
                $categoryParentName = $categoryParentName !== '' ? $categoryParentName : (string) ($parent['name'] ?? '');
            }
            if ($categoryParentSlug !== '' && $categoryParentName !== '' && function_exists('categoryUrl')) {
                $items[] = ['label' => $categoryParentName, 'url' => categoryUrl($categoryParentSlug)];
            }
            if ($category !== '' && $categorySlug !== '' && function_exists('categoryUrl')) {
                $items[] = ['label' => $category, 'url' => categoryUrl($categorySlug, $categoryParentSlug)];
            }
            $topicUrl = function_exists('topicUrlForRow') ? topicUrlForRow($topic) : '';
            $items[] = ['label' => (string) ($topic['title'] ?? $pageTitle), 'url' => $topicUrl];
            return $items;
        }

        if ($pageKey === 'download') {
            $items[] = ['label' => 'Indirme'];
            return $items;
        }

        if ($pageKey === 'profile') {
            $items[] = ['label' => 'Profilim'];
            return $items;
        }

        if ($pageKey === 'public_profile') {
            $items[] = ['label' => 'Profil'];
            $profileName = trim((string) ($pageVars['profileUser']['name'] ?? $pageTitle));
            if ($profileName !== '') {
                $items[] = ['label' => $profileName];
            }
            return $items;
        }

        $label = $pageTitle !== '' ? $pageTitle : self::labelForPageKey($pageKey);
        if ($label !== '') {
            $items[] = ['label' => $label];
        }

        return $items;
    }

    /**
     * @return array{name?: string, slug?: string}
     */
    private static function categoryParentForSlug(mixed $pdo, string $categorySlug): array
    {
        static $cache = [];

        $categorySlug = trim($categorySlug);
        if ($categorySlug === '' || !$pdo instanceof PDO) {
            return [];
        }
        if (array_key_exists($categorySlug, $cache)) {
            return $cache[$categorySlug];
        }

        $cache[$categorySlug] = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT parent.name, parent.slug
                 FROM categories cat
                 LEFT JOIN categories parent ON parent.id = cat.parent_id
                 WHERE cat.slug = :slug
                   AND cat.deleted_at IS NULL
                   AND parent.deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['slug' => $categorySlug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['slug'])) {
                $cache[$categorySlug] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'slug' => (string) ($row['slug'] ?? ''),
                ];
            }
        } catch (Throwable $error) {
            if (function_exists('appLogException')) {
                appLogException($error, ['source' => 'PublicThemeRenderer category breadcrumb']);
            }
        }

        return $cache[$categorySlug];
    }

    private static function labelForPageKey(string $pageKey): string
    {
        return match ($pageKey) {
            'login' => 'Giris',
            'register' => 'Kayit',
            'forgot_password' => 'Sifremi Unuttum',
            'reset_password' => 'Yeni Sifre',
            'upload_topic' => 'Icerik Yukle',
            'edit_topic' => 'Icerik Duzenle',
            'notifications' => 'Bildirimler',
            'messages' => 'Mesajlar',
            'leaderboard' => 'Liderlik',
            'contact' => 'Iletisim',
            'ban_appeals' => 'Ban Itirazlari',
            'not_found' => 'Sayfa Bulunamadi',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $pageVars
     * @return array<string, mixed>
     */
    private static function topicVars(array $topic, array $pageVars, string $baseUri, array $settings): array
    {
        $title = (string) ($topic['title'] ?? $pageVars['pageTitle'] ?? 'Konu');
        $category = (string) ($topic['category'] ?? 'Genel');
        $categorySlug = (string) ($topic['category_slug'] ?? '');
        $categoryParentSlug = trim((string) ($topic['parent_slug'] ?? ($topic['category_parent_slug'] ?? ($topic['parent_category_slug'] ?? ''))));
        if ($categorySlug !== '' && $categoryParentSlug === '') {
            $parent = self::categoryParentForSlug($GLOBALS['pdo'] ?? null, $categorySlug);
            $categoryParentSlug = trim((string) ($parent['slug'] ?? ''));
        }
        $published = (string) ($pageVars['published'] ?? ($topic['published_at'] ?? ($topic['created_at'] ?? 'now')));
        $timestamp = strtotime($published) ?: time();
        $image = (string) ($pageVars['topicPrimaryImage'] ?? '');

        if ($image === '' && function_exists('getTopicPrimaryMediaPath')) {
            $image = (string) (getTopicPrimaryMediaPath($topic) ?? '');
        }
        if ($image !== '' && !preg_match('~^(https?:)?//|^data:|^/~i', $image)) {
            $image = rtrim($baseUri, '/') . '/' . ltrim($image, '/');
        }
        if ($image === '') {
            $cover = (string) ($pageVars['cover'] ?? 'topic-pack.svg');
            $image = function_exists('asset_url') ? asset_url('assets/' . $cover, $baseUri) : rtrim($baseUri, '/') . '/assets/' . ltrim($cover, '/');
        }

        $comments = isset($pageVars['comments']) && is_array($pageVars['comments']) ? $pageVars['comments'] : [];
        $schemaData = isset($pageVars['schemaData']) && is_array($pageVars['schemaData']) ? $pageVars['schemaData'] : [];
        $commentsCount = (int) ($topic['comment_count'] ?? $topic['comments_count'] ?? $schemaData['commentCount'] ?? count($comments));
        $views = (int) ($topic['view_count'] ?? 0);
        $downloads = (int) ($topic['download_count'] ?? $topic['downloads_count'] ?? 0);
        $favorites = (int) ($pageVars['favoritesCount'] ?? $topic['favorites_count'] ?? $topic['likes'] ?? $topic['rating_count'] ?? 0);
        $topicUrl = function_exists('topicUrlForRow') ? topicUrlForRow($topic) : '#';
        if ($topicUrl !== '#' && !preg_match('~^(https?:)?//|^/~i', $topicUrl)) {
            $topicUrl = rtrim($baseUri, '/') . '/' . ltrim($topicUrl, '/');
        }

        return [
            'id' => (int) ($topic['id'] ?? 0),
            'url' => $topicUrl,
            'image' => $image,
            'image_alt' => $title,
            'image_title' => function_exists('seoGenerateImageTitle')
                ? seoGenerateImageTitle('topic-hero', $title, $settings)
                : $title . ' kapak gorseli',
            'title' => $title,
            'category' => $category,
            'category_url' => $categorySlug !== '' && function_exists('categoryUrl') ? categoryUrl($categorySlug, $categoryParentSlug) : '#',
            'date' => function_exists('formatAppDate') ? formatAppDate((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now'), $GLOBALS['pdo'] ?? null) : date('d.m.Y', $timestamp),
            'date_short' => date('d.m', $timestamp),
            'day_name' => date('D', $timestamp),
            'show_toolbar' => ($settings['topic_detail_show_toolbar'] ?? '1') === '1',
            'show_view_count' => ($settings['show_view_count'] ?? '1') === '1',
            'show_download_count' => ($settings['show_download_count'] ?? '1') === '1',
            'views' => number_format($views, 0, ',', '.'),
            'downloads' => number_format($downloads, 0, ',', '.'),
            'likes' => number_format($favorites, 0, ',', '.'),
            'favorites_count' => number_format($favorites, 0, ',', '.'),
            'is_favorited' => !empty($pageVars['isFavorited']),
            'is_logged_in' => !empty($pageVars['isLoggedIn']),
            'can_edit' => !empty($pageVars['canEditTopic']),
            'edit_url' => (string) ($pageVars['topicEditUrl'] ?? '#'),
            'comments_count' => number_format($commentsCount, 0, ',', '.'),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function discoverListingLcpImageHref(array $pageVars, string $baseUri, string $pageKey): string
    {
        $listingPageKeys = ['home', 'search', 'category', 'leaderboard'];
        if (!in_array($pageKey, $listingPageKeys, true)) {
            return '';
        }

        $items = [];
        if (isset($pageVars['items']) && is_array($pageVars['items'])) {
            $items = $pageVars['items'];
        } elseif (isset($pageVars['topics']) && is_array($pageVars['topics'])) {
            $items = $pageVars['topics'];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $imageHref = trim((string) ($item['image'] ?? ''));
            if ($imageHref === '' && function_exists('getTopicPrimaryMediaPath')) {
                $imageHref = trim((string) (getTopicPrimaryMediaPath($item) ?? ''));
            }
            if ($imageHref === '') {
                $imageHref = trim((string) ($item['image_path'] ?? $item['primary_media_path'] ?? $item['cover_image'] ?? ''));
            }
            if ($imageHref === '') {
                continue;
            }
            if (preg_match('~^data:~i', $imageHref) === 1) {
                continue;
            }
            $imageHref = self::absolutePublicHref($imageHref, $baseUri);
            $thumbnailHref = self::listingThumbnailHref($imageHref, $baseUri);
            if ($thumbnailHref !== '') {
                return $thumbnailHref;
            }

            return $imageHref;
        }

        return '';
    }

    private static function absolutePublicHref(string $href, string $baseUri): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (preg_match('~^(https?:)?//|^data:|^/~i', $href) === 1) {
            return $href;
        }

        return rtrim($baseUri, '/') . '/' . ltrim($href, '/');
    }

    private static function listingThumbnailHref(string $imageHref, string $baseUri): string
    {
        $imageHref = trim($imageHref);
        if ($imageHref === '' || preg_match('~^data:~i', $imageHref) === 1) {
            return '';
        }

        static $cache = [];
        $cacheKey = $baseUri . '|' . $imageHref;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $path = $imageHref;
        if (preg_match('~^(https?:)?//~i', $path) === 1) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = str_replace('\\', '/', $path);
        if ($path === '') {
            $cache[$cacheKey] = '';
            return '';
        }

        $trimmedBase = trim($baseUri, '/');
        if ($trimmedBase !== '') {
            $prefix = '/' . $trimmedBase . '/';
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'uploads/')) {
            $cache[$cacheKey] = '';
            return '';
        }

        $dirname = trim((string) pathinfo($path, PATHINFO_DIRNAME), '/.');
        $filename = (string) pathinfo($path, PATHINFO_FILENAME);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($dirname === '' || $filename === '') {
            $cache[$cacheKey] = '';
            return '';
        }

        $candidates = [$dirname . '/thumbnails/' . $filename . '.webp'];
        if ($extension !== '' && $extension !== 'webp') {
            $candidates[] = $dirname . '/thumbnails/' . $filename . '.' . $extension;
        }
        $candidates[] = $dirname . '/thumbs/' . $filename . '.webp';
        if ($extension !== '') {
            $candidates[] = $dirname . '/thumbs/' . $filename . '.' . $extension;
        }

        foreach ($candidates as $candidate) {
            $candidate = (string) preg_replace('~/+~', '/', $candidate);
            $fullPath = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($candidate, '/'));
            if (is_file($fullPath)) {
                $cache[$cacheKey] = rtrim($baseUri, '/') . '/' . ltrim($candidate, '/');
                return $cache[$cacheKey];
            }
        }

        $cache[$cacheKey] = '';
        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function buildHead(array $context): string
    {
        $themeManager = $context['theme_manager'] ?? null;
        $settings = self::arrayValue($context, 'settings');
        $pageVars = self::arrayValue($context, 'page_vars');
        $baseUri = (string) ($context['base_uri'] ?? '');
        $siteName = (string) ($context['app_name'] ?? ($settings['site_name'] ?? ($settings['header_brand_text'] ?? 'TurkMod')));
        $pageTitle = (string) ($context['page_title'] ?? 'Ana Sayfa');
        $metaDescription = (string) ($context['meta_description'] ?? 'Topluluk dosyalari, guncellemeler ve modlar.');
        $themeMode = (string) ($context['theme_mode'] ?? 'auto');
        $faviconUrl = trim((string) ($settings['favicon_url'] ?? ''));
        $seoPageKey = (string) ($context['page_key'] ?? '');
        if (function_exists('seoPublicPageResolveKey')) {
            $seoPageKey = seoPublicPageResolveKey((string) ($context['current_request_uri'] ?? ''), $settings, $seoPageKey !== '' ? $seoPageKey : null);
        }
        $seoPageTitle = trim((string) ($pageVars['seoPageTitle'] ?? $pageVars['seo_page_title'] ?? ''));
        $seoPageTitleIsFinal = !empty($pageVars['seoPageTitleIsFinal'] ?? $pageVars['seo_page_title_is_final'] ?? $pageVars['pageTitleIsFinal'] ?? false);
        if ($seoPageTitle === '') {
            $seoPageTitle = $pageTitle;
        }
        if (
            !$seoPageTitleIsFinal
            && $seoPageKey !== ''
            && function_exists('seoPublicPageMeta')
        ) {
            $resolvedPageMeta = seoPublicPageMeta(
                $seoPageKey,
                [
                    'title' => $seoPageTitle,
                    'description' => $metaDescription,
                ],
                [],
                $settings
            );
            if (!empty($resolvedPageMeta['title_is_final'])) {
                $seoPageTitle = (string) ($resolvedPageMeta['title'] ?? $seoPageTitle);
                $seoPageTitleIsFinal = true;
            }
        }
        $resolvedTitle = $seoPageTitleIsFinal
            ? $seoPageTitle
            : ($seoPageTitle !== '' ? $seoPageTitle . ' - ' . $siteName : $siteName);

        $head = [];
        $head[] = '<meta charset="UTF-8">';
        $head[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $head[] = self::themeModeScript($baseUri);
        $head[] = '<title>' . htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') . '</title>';

        if (isset($pageVars['seoMetaTags']) && (string) $pageVars['seoMetaTags'] !== '') {
            $head[] = (string) $pageVars['seoMetaTags'];
        } elseif (function_exists('getSeoMeta')) {
            $head[] = getSeoMeta($pageTitle . ' - ' . $siteName, $metaDescription);
        }

        if (isset($pageVars['seoPaginationTags']) && (string) $pageVars['seoPaginationTags'] !== '') {
            $head[] = (string) $pageVars['seoPaginationTags'];
        }

        $robotsMeta = function_exists('seoRobotsMeta') ? seoRobotsMeta($settings, null, $seoPageKey) : 'index, follow';
        $indexDraftTopics = function_exists('seoIndexToggleValue')
            ? seoIndexToggleValue($settings, 'index_draft_topics', '0', 'noindex_draft_topics')
            : (((string) ($settings['noindex_draft_topics'] ?? '1')) === '1' ? '0' : '1');
        $indexEmptyCategories = function_exists('seoIndexToggleValue')
            ? seoIndexToggleValue($settings, 'index_empty_categories', '0', 'noindex_empty_categories')
            : (((string) ($settings['noindex_empty_categories'] ?? '1')) === '1' ? '0' : '1');

        if (isset($pageVars['topic']) && is_array($pageVars['topic']) && ($pageVars['topic']['status'] ?? 'published') !== 'published' && $indexDraftTopics !== '1') {
            $robotsMeta = 'noindex, nofollow';
        }
        if (isset($pageVars['categoryId'], $pageVars['items']) && (int) $pageVars['categoryId'] > 0 && empty($pageVars['items']) && $indexEmptyCategories !== '1') {
            $robotsMeta = 'noindex, nofollow';
        }
        $head[] = '<meta name="robots" content="' . htmlspecialchars($robotsMeta, ENT_QUOTES, 'UTF-8') . '">';

        $pageKey = (string) ($context['page_key'] ?? '');
        $isLeanListingPage = in_array($pageKey, ['home', 'search', 'category', 'leaderboard'], true);
        $sessionCookieName = session_name();
        $hasSessionCookie = $sessionCookieName !== '' && isset($_COOKIE[$sessionCookieName]);
        $shouldEmitCsrfMeta = !$isLeanListingPage
            || session_status() === PHP_SESSION_ACTIVE
            || $hasSessionCookie;
        if (function_exists('csrf_token') && $shouldEmitCsrfMeta) {
            $head[] = '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
        }
        $head[] = '<meta name="app-base-uri" content="' . htmlspecialchars($baseUri, ENT_QUOTES, 'UTF-8') . '">';
        $head[] = '<meta name="color-scheme" content="light dark">';

        $lcpImageHref = self::discoverListingLcpImageHref($pageVars, $baseUri, (string) ($context['page_key'] ?? ''));
        if ($lcpImageHref !== '') {
            $head[] = '<link rel="preload" as="image" href="' . htmlspecialchars($lcpImageHref, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high">';
        }

        $activeThemeId = $themeManager instanceof ThemeManager
            ? $themeManager->activeThemeId()
            : '';
        $faviconHref = self::publicSettingAsset($faviconUrl, $baseUri);
        if ($faviconHref === '' && function_exists('asset_url')) {
            // Prefer lightweight SVG favicon for performance.
            $faviconHref = asset_url('assets/favicon.svg', $baseUri);
        }
        if ($faviconHref === '' && $themeManager instanceof ThemeManager && $themeManager->isAssetIsolated()) {
            $faviconHref = $themeManager->assetUrl($activeThemeId, 'images/favicon.ico');
        }
        if ($faviconHref !== '') {
            $head[] = '<link rel="icon" href="' . htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8') . '">';
        }
        $robotoLocalHref = function_exists('asset_url')
            ? asset_url('assets/css/roboto-local.css', $baseUri)
            : rtrim($baseUri, '/') . '/assets/css/roboto-local.css';
        $robotoLocalEsc = htmlspecialchars($robotoLocalHref, ENT_QUOTES, 'UTF-8');
        $head[] = '<link rel="preload" as="style" href="' . $robotoLocalEsc . '">';
        $head[] = '<link rel="stylesheet" href="' . $robotoLocalEsc . '">';

        // Keep Turkmod on the same CSS bundle across public pages. The lean listing bundle is
        // intentionally skipped here because it can omit topbar/sidebar rules used by shared shell widgets.
        $preferLeanThemeCss = false;
        $skipRootPublicCssForLeanListing = $preferLeanThemeCss && $activeThemeId === 'turkmod';

        if ($themeManager instanceof ThemeManager) {
            if (function_exists('asset_url')) {
                try {
                    // Keep the non-min listing bundle for visual parity.
                    // The previous minified variant dropped some style rules on parse in production browsers.
                    $leanThemeCssRelative = 'css/bundle-listing.css';
                    $leanThemeCssPath = __DIR__ . '/../themes/' . $activeThemeId . '/' . $leanThemeCssRelative;
                    $leanThemeCssAvailable = $preferLeanThemeCss
                        && preg_match('/^[a-z0-9_-]+$/', $activeThemeId) === 1
                        && is_file($leanThemeCssPath);
                    // Critical CSS (render-blocking) — design tokens, shell layout, ui-foundation
                    $publicCssPath = __DIR__ . '/../assets/dist/public.min.css';
                    if ($skipRootPublicCssForLeanListing) {
                        // Lean turkmod listing pages already include shared shell/foundation CSS in the theme bundle.
                        // Skipping root public.min.css avoids duplicate render-blocking payload.
                        // bundle-listing.css already contains @font-face + icon map.
                        // Keep standalone icon CSS only when lean bundle is unavailable.
                        if (!$leanThemeCssAvailable) {
                            $leanIconsRelative = 'css/bootstrap-icons-lean.min.css';
                            $leanIconsPath = __DIR__ . '/../themes/' . $activeThemeId . '/' . $leanIconsRelative;
                            if (is_file($leanIconsPath)) {
                                $head[] = '<link rel="stylesheet" href="' . htmlspecialchars($themeManager->assetUrl($activeThemeId, $leanIconsRelative), ENT_QUOTES, 'UTF-8') . '" data-theme-asset="' . htmlspecialchars($activeThemeId, ENT_QUOTES, 'UTF-8') . '" data-theme-icons-lean="1">';
                            } else {
                                $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/bootstrap-icons.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                            }
                        }
                    } elseif (is_file($publicCssPath)) {
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/dist/public.min.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    } else {
                        // Fallback: load individual files
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/general.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    }
                    // The lean turkmod listing bundle already includes ui-foundation.
                    // Skip duplicate render-blocking payload on those pages.
                    if (!$leanThemeCssAvailable) {
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/ui-foundation.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    }

                    // Theme CSS — load normally so CSP cannot leave it stuck in print media.
                    // Prefer dist bundle ONLY if active theme is default. Always load active theme's own CSS asset tags.
                    $themeMinCssPath = __DIR__ . '/../assets/dist/theme.min.css';
                    if ($activeThemeId === 'default' && is_file($themeMinCssPath)) {
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/dist/theme.min.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    }
                    if ($leanThemeCssAvailable) {
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars($themeManager->assetUrl($activeThemeId, $leanThemeCssRelative), ENT_QUOTES, 'UTF-8') . '" data-theme-asset="' . htmlspecialchars($activeThemeId, ENT_QUOTES, 'UTF-8') . '" data-theme-asset-lean="1">';
                        if ($pageKey === 'home' && $activeThemeId === 'turkmod') {
                            $leanHomeToolbarCssRelative = 'css/lean-home-toolbar.css';
                            $leanHomeToolbarCssPath = __DIR__ . '/../themes/' . $activeThemeId . '/' . $leanHomeToolbarCssRelative;
                            if (is_file($leanHomeToolbarCssPath)) {
                                $head[] = '<link rel="stylesheet" href="' . htmlspecialchars($themeManager->assetUrl($activeThemeId, $leanHomeToolbarCssRelative), ENT_QUOTES, 'UTF-8') . '" data-theme-asset="' . htmlspecialchars($activeThemeId, ENT_QUOTES, 'UTF-8') . '" data-theme-home-toolbar-lean="1">';
                            }
                        }
                    } else {
                        $head[] = $themeManager->renderAssetTags('css');
                    }

                    if ($activeThemeId !== 'turkmod') {
                        // Font preload
                        $fontFiles = glob(__DIR__ . '/../assets/dist/bootstrap-icons-*.woff2');
                        if (!empty($fontFiles)) {
                            $fontRelPath = 'assets/dist/' . basename($fontFiles[0]);
                            $head[] = '<link rel="preload" href="' . htmlspecialchars(asset_url($fontRelPath, $baseUri), ENT_QUOTES, 'UTF-8') . '" as="font" type="font/woff2" crossorigin>';
                        } else {
                            // Fallback: preload original font path
                            $head[] = '<link rel="preload" href="' . htmlspecialchars(asset_url('assets/bootstrap-icons/fonts/bootstrap-icons.woff2', $baseUri), ENT_QUOTES, 'UTF-8') . '" as="font" type="font/woff2" crossorigin>';
                        }
                    }

                    // Page-specific CSS files
                    $pageCssFiles = isset($pageVars['pageCssFiles']) && is_array($pageVars['pageCssFiles'])
                        ? $pageVars['pageCssFiles']
                        : [];
                    foreach ($pageCssFiles as $pageCssFile) {
                        if (!is_string($pageCssFile)) {
                            continue;
                        }
                        $pageCssFile = trim($pageCssFile);
                        if ($pageCssFile === '') {
                            continue;
                        }
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url($pageCssFile, $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    }
                    if ($pageKey === 'leaderboard') {
                        $head[] = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/leaderboard-page.css', $baseUri), ENT_QUOTES, 'UTF-8') . '">';
                    }
                } catch (Throwable $error) {
                    if (function_exists('appLogException')) {
                        appLogException($error, ['source' => 'PublicThemeRenderer css loading']);
                    }
                }
            }
        }

        if (isset($pageVars['seoStructuredData']) && (string) $pageVars['seoStructuredData'] !== '') {
            $head[] = (string) $pageVars['seoStructuredData'];
        }

        if (!empty($settings['google_site_verification'])) {
            $head[] = '<meta name="google-site-verification" content="' . htmlspecialchars((string) $settings['google_site_verification'], ENT_QUOTES, 'UTF-8') . '">';
        }

        return trim(implode("\n", array_filter($head, static fn (string $line): bool => trim($line) !== '')));
    }

    private static function themeModeScript(string $baseUri): string
    {
        // Inline theme-mode-init — zero HTTP requests, executes immediately
        $initPath = __DIR__ . '/../assets/js/theme-mode-init.js';
        $code = @file_get_contents($initPath);
        if ($code !== false && $code !== '') {
            $nonce = function_exists('appCspNonceAttr') ? appCspNonceAttr() : '';
            return '<script' . $nonce . '>' . $code . '</script>';
        }
        // Fallback: external script
        $src = function_exists('asset_url')
            ? asset_url('assets/js/theme-mode-init.js', $baseUri)
            : rtrim($baseUri, '/') . '/assets/js/theme-mode-init.js';

        return '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /**
     * @param array<string, mixed> $content
     * @return array{name: string, description: string}
     */
    private static function categoryVars(array $content): array
    {
        return [
            'name' => (string) ($content['categoryName'] ?? ''),
            'description' => (string) ($content['categoryDescription'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $pageVars
     */
    private static function activeCategorySlug(array $pageVars): string
    {
        if (!empty($pageVars['isSpecificCategory']) && isset($pageVars['categorySlug'])) {
            return trim((string) $pageVars['categorySlug']);
        }

        if (isset($pageVars['topic']) && is_array($pageVars['topic'])) {
            return trim((string) ($pageVars['topic']['category_slug'] ?? ''));
        }

        return '';
    }

    /**
     * @param array<int, mixed> $nodes
     */
    private static function categoryListHref(string $baseUri): string
    {
        return function_exists('categoryListUrl')
            ? categoryListUrl()
            : (rtrim($baseUri, '/') . '/kategoriler');
    }

    /**
     * @param array<int, mixed> $nodes
     * @return array<int, array<string, mixed>>
     */
    private static function categoryMenuItems(array $nodes, string $activeSlug, string $variant, string $baseUri, mixed $pdo): array
    {
        $state = ['index' => 0];
        $items = [];

        foreach (array_slice($nodes, 0, 12) as $node) {
            if (is_array($node)) {
                $items[] = self::categoryMenuItemVars($node, 0, $variant, $activeSlug, $baseUri, $pdo, $state);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    private static function categoryMenuItemVars(array $node, int $depth, string $variant, string $activeSlug, string $baseUri, mixed $pdo, array &$state): array
    {
        $name = (string) ($node['name'] ?? 'Kategori');
        $slug = (string) ($node['slug'] ?? '');
        $children = array_values(array_filter($node['children'] ?? [], 'is_array'));
        $count = self::categoryTotal($node);
        $isActive = $activeSlug !== '' && $slug === $activeSlug;
        $containsActive = self::categoryContainsSlug($node, $activeSlug);
        $url = function_exists('categoryUrlForRow')
            ? categoryUrlForRow($pdo instanceof PDO ? $pdo : null, $node)
            : (function_exists('categoryUrl') ? categoryUrl($slug) : ($baseUri . '/category.php?slug=' . rawurlencode($slug)));
        $itemId = 'cat-' . $variant . '-' . (++$state['index']);
        $hasChildren = $children !== [];
        $isSidebarRootToggle = $variant === 'sidebar' && $depth === 0 && $hasChildren;
        $isSidebarRootLink = $variant === 'sidebar' && $depth === 0 && !$hasChildren;
        $expanded = $variant === 'sidebar' && $hasChildren && $containsActive;
        $itemClass = trim(implode(' ', array_filter([
            'nav-item',
            'cat-menu__item',
            $depth > 0 ? 'cat-menu__item--child' : 'cat-menu__item--parent',
            $hasChildren ? 'cat-menu__item--has-children' : '',
            'cat-menu__item--' . $variant,
            $isActive ? 'is-active' : '',
            !$isActive && $containsActive ? 'has-active-child' : '',
        ])));

        $childItems = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $childItems[] = self::categoryMenuItemVars($child, $depth + 1, $variant, $activeSlug, $baseUri, $pdo, $state);
            }
        }

        return [
            'name' => $name,
            'url' => $url,
            'count' => (string) number_format($count, 0, ',', '.'),
            'item_class' => $itemClass,
            'link_class' => $depth > 0 ? 'cat-menu__link--child' : 'cat-menu__link--parent',
            'icon_class' => $depth > 0 ? 'bi-arrow-return-right' : 'bi-folder2',
            'panel_id' => $itemId . '-children',
            'toggle_label' => $name . ' alt kategorileri',
            'expanded' => $expanded ? 'true' : 'false',
            'is_collapsed' => !$expanded,
            'is_active' => $isActive,
            'has_children' => $hasChildren,
            'is_sidebar_root_toggle' => $isSidebarRootToggle,
            'is_sidebar_root_link' => $isSidebarRootLink,
            'children' => $childItems,
        ];
    }

    private static function renderHeaderCategoryMenu(array $nodes, string $activeSlug, string $baseUri, mixed $pdo): string
    {
        $html = '<li><a class="dropdown-item cat-menu__all-link" href="' .
            htmlspecialchars(function_exists('categoryListUrl') ? categoryListUrl() : (rtrim($baseUri, '/') . '/kategoriler'), ENT_QUOTES, 'UTF-8') .
            '"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span>Tüm Kategoriler</span></a></li><li class="cat-dropdown-tree"><ul class="nav nav-link-secondary flex-column fw-bold gap-1 cat-menu cat-menu--nested cat-menu--topbar" data-cat-menu>';
        $html .= self::renderCategoryNodes($nodes, $activeSlug, 'topbar', $baseUri, $pdo);
        return $html . '</ul></li>';
    }

    /**
     * @param array<int, mixed> $nodes
     */
    private static function renderCategoryMenu(array $nodes, string $activeSlug, string $variant, string $baseUri, mixed $pdo): string
    {
        $html = '<ul class="nav nav-link-secondary flex-column fw-bold gap-2 cat-menu cat-menu--nested cat-menu--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" data-cat-menu>';
        $html .= self::renderCategoryNodes($nodes, $activeSlug, $variant, $baseUri, $pdo);
        return $html . '</ul>';
    }

    /**
     * @param array<int, mixed> $nodes
     */
    private static function renderCategoryNodes(array $nodes, string $activeSlug, string $variant, string $baseUri, mixed $pdo): string
    {
        $state = ['index' => 0];
        $html = '';
        foreach (array_slice($nodes, 0, 12) as $node) {
            if (is_array($node)) {
                $html .= self::renderCategoryNode($node, 0, $variant, $activeSlug, $baseUri, $pdo, $state);
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, int> $state
     */
    private static function renderCategoryNode(array $node, int $depth, string $variant, string $activeSlug, string $baseUri, mixed $pdo, array &$state): string
    {
        $name = htmlspecialchars((string) ($node['name'] ?? 'Kategori'), ENT_QUOTES, 'UTF-8');
        $slug = (string) ($node['slug'] ?? '');
        $children = array_values(array_filter($node['children'] ?? [], 'is_array'));
        $count = self::categoryTotal($node);
        $isActive = $activeSlug !== '' && $slug === $activeSlug;
        $containsActive = self::categoryContainsSlug($node, $activeSlug);
        $url = function_exists('categoryUrlForRow')
            ? categoryUrlForRow($pdo instanceof PDO ? $pdo : null, $node)
            : (function_exists('categoryUrl') ? categoryUrl($slug) : ($baseUri . '/category.php?slug=' . rawurlencode($slug)));
        $itemId = 'cat-' . $variant . '-' . (++$state['index']);
        $panelId = $itemId . '-children';
        $depthClass = $depth > 0 ? ' cat-menu__item--child' : ' cat-menu__item--parent';
        $hasChildrenClass = $children !== [] ? ' cat-menu__item--has-children' : '';
        $activeClass = $isActive ? ' is-active' : '';
        $activePathClass = !$isActive && $containsActive ? ' has-active-child' : '';
        $linkClass = $depth > 0 ? 'cat-menu__link--child' : 'cat-menu__link--parent';
        $icon = $depth > 0 ? 'bi-arrow-return-right' : 'bi-folder2';
        $expanded = $variant === 'sidebar' && $children !== [] && $containsActive ? 'true' : 'false';
        $hidden = $expanded === 'true' ? '' : ' hidden';
        $current = $isActive ? ' aria-current="page"' : '';
        $countHtml = '<small class="cat-menu__count">' . number_format($count, 0, ',', '.') . '</small>';
        $nameHtml = '<span class="cat-menu__name"><i class="bi ' . $icon . '" aria-hidden="true"></i><span class="cat-menu__label">' . $name . '</span></span>';
        $isSidebarRootToggle = $variant === 'sidebar' && $depth === 0 && $children !== [];
        $isSidebarRootLink = $variant === 'sidebar' && $depth === 0 && $children === [];

        $html = '<li class="nav-item cat-menu__item' . $depthClass . $hasChildrenClass . ' cat-menu__item--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . $activeClass . $activePathClass . '" data-cat-item>';
        if ($isSidebarRootToggle) {
            $html .= '<button class="cat-menu__row cat-menu__trigger" type="button" data-cat-toggle aria-expanded="' . $expanded . '" aria-controls="' . $panelId . '" aria-label="' . $name . ' alt kategorileri">' . $nameHtml . $countHtml . '<i class="bi bi-arrow-right cat-menu__arrow" aria-hidden="true"></i></button>';
        } elseif ($isSidebarRootLink) {
            $html .= '<a class="nav-link cat-menu__row cat-menu__link cat-menu__link--parent cat-menu__root-link" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $current . '>' . $nameHtml . $countHtml . '<span class="cat-menu__arrow-spacer" aria-hidden="true"></span></a>';
        } else {
            $html .= '<div class="cat-menu__row"><a class="nav-link cat-menu__link ' . $linkClass . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $current . '>' . $nameHtml . $countHtml . '</a>';
            if ($children !== []) {
                $html .= '<button class="cat-menu__toggle" type="button" data-cat-toggle aria-expanded="' . $expanded . '" aria-controls="' . $panelId . '" aria-label="' . $name . ' alt kategorileri"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>';
            }
            $html .= '</div>';
        }

        if ($children !== []) {
            $html .= '<ul id="' . $panelId . '" class="nav nav-link-secondary flex-column cat-menu__children" data-cat-panel' . $hidden . '>';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= self::renderCategoryNode($child, $depth + 1, $variant, $activeSlug, $baseUri, $pdo, $state);
                }
            }
            $html .= '</ul>';
        }

        return $html . '</li>';
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function categoryTotal(array $node): int
    {
        $total = (int) ($node['topic_count'] ?? $node['news_count'] ?? $node['count'] ?? 0);
        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $total += self::categoryTotal($child);
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function categoryContainsSlug(array $node, string $slug): bool
    {
        if ($slug === '') {
            return false;
        }
        if ((string) ($node['slug'] ?? '') === $slug) {
            return true;
        }
        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child) && self::categoryContainsSlug($child, $slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function buildFooterData(array $context): array
    {
        $baseUri = (string) ($context['base_uri'] ?? '');
        $settings = self::arrayValue($context, 'settings');
        $envConfig = self::arrayValue($context, 'env_config');
        $pdo = $context['pdo'] ?? null;
        $pageVars = self::arrayValue($context, 'page_vars');
        $publicCategories = self::arrayValue($context, 'public_categories');
        if ($publicCategories === [] && $pageVars !== []) {
            $publicCategories = self::arrayValue($pageVars, 'publicCategories');
        }
        $publicCategoriesTree = self::arrayValue($context, 'public_categories_tree');
        if ($publicCategoriesTree === [] && $pageVars !== []) {
            $publicCategoriesTree = self::arrayValue($pageVars, 'publicCategoriesTree');
        }
        $siteName = (string) ($settings['site_name'] ?? ($settings['header_brand_text'] ?? ($envConfig['APP_NAME'] ?? 'TurkMod')));
        $siteDescription = (string) ($settings['footer_text'] ?? $settings['footer_description'] ?? 'Topluluk dosyalari, guncellemeler ve modlar.');

        $popularTopics = [];
        $recentComments = [];
        $tagCloudItems = [];
        $sidebarSource = isset($context['sidebar_items']) && is_array($context['sidebar_items']) ? $context['sidebar_items'] : [];
        if ($sidebarSource === [] && $pageVars !== []) {
            $sidebarSource = self::arrayValue($pageVars, 'sidebarItems');
        }

        if ($sidebarSource === [] && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->query(
                    "SELECT t.id, t.title, t.slug, t.download_count, pm.path AS image_path
                     FROM topics t
                     LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                     WHERE t.status = 'published' AND t.deleted_at IS NULL
                     ORDER BY t.view_count DESC, t.download_count DESC
                     LIMIT 5"
                );
                $sidebarSource = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            } catch (Throwable $error) {
                if (function_exists('appLogException')) {
                    appLogException($error, ['source' => 'PublicThemeRenderer popular']);
                }
            }
        }

        foreach ($sidebarSource as $row) {
            if (!is_array($row)) {
                continue;
            }
            $image = (string) ($row['image_path'] ?? $row['primary_media_path'] ?? '');
            if ($image !== '' && !preg_match('~^(https?:)?//|^data:|^/~i', $image)) {
                $image = rtrim($baseUri, '/') . '/' . ltrim($image, '/');
            }
            $popularTopics[] = [
                'title' => (string) ($row['title'] ?? 'Konu'),
                'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#',
                'image' => $image,
                'meta' => isset($row['download_count']) ? number_format((int) $row['download_count'], 0, ',', '.') . ' indirme' : '',
            ];
        }

        $recentCommentRows = isset($context['recent_comments']) && is_array($context['recent_comments'])
            ? $context['recent_comments']
            : [];
        if ($recentCommentRows === [] && $pageVars !== []) {
            $recentCommentRows = self::arrayValue($pageVars, 'recentComments');
        }
        if ($recentCommentRows !== []) {
            $avatarFallbackUrl = function_exists('defaultAvatarUrl')
                ? defaultAvatarUrl($baseUri)
                : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
            foreach ($recentCommentRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $author = (string) ($row['author'] ?? $row['username'] ?? 'Uye');
                $commentAvatar = '';
                if (function_exists('resolveAvatarUrl')) {
                    $commentAvatar = resolveAvatarUrl((string) ($row['user_avatar'] ?? $row['avatar'] ?? ''), $baseUri, true);
                } else {
                    $commentAvatar = $avatarFallbackUrl;
                }
                $recentComments[] = [
                    'author' => $author,
                    'avatar' => $commentAvatar,
                    'excerpt' => mb_substr(trim(strip_tags((string) ($row['body'] ?? $row['content'] ?? ''))), 0, 74),
                    'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#',
                    'date' => function_exists('formatAppDate') ? formatAppDate((string) ($row['created_at'] ?? 'now'), $pdo) : '',
                ];
            }
        } elseif ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query(
                    "SELECT c.body, c.created_at, t.id AS topic_id, t.title, t.slug, u.name AS author, u.avatar AS user_avatar
                     FROM comments c
                     INNER JOIN topics t ON t.id = c.topic_id
                     LEFT JOIN users u ON u.id = c.user_id
                     WHERE c.status = 'approved' AND c.deleted_at IS NULL
                       AND t.status = 'published' AND t.deleted_at IS NULL
                     ORDER BY c.created_at DESC
                     LIMIT 3"
                );
                $avatarFallbackUrl = function_exists('defaultAvatarUrl')
                    ? defaultAvatarUrl($baseUri)
                    : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
                foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                    $author = (string) ($row['author'] ?? 'Uye');
                    $commentAvatar = '';
                    if (function_exists('resolveAvatarUrl')) {
                        $commentAvatar = resolveAvatarUrl((string) ($row['user_avatar'] ?? ''), $baseUri, true);
                    } else {
                        $commentAvatar = $avatarFallbackUrl;
                    }
                    $recentComments[] = [
                        'author' => $author,
                        'avatar' => $commentAvatar,
                        'excerpt' => mb_substr(trim(strip_tags((string) ($row['body'] ?? ''))), 0, 74),
                        'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#',
                        'date' => function_exists('formatAppDate') ? formatAppDate((string) ($row['created_at'] ?? 'now'), $pdo) : '',
                    ];
                }
            } catch (Throwable $error) {
                if (function_exists('appLogException')) {
                    appLogException($error, ['source' => 'PublicThemeRenderer comments']);
                }
            }
        }

        foreach (array_slice($publicCategories, 0, 12) as $category) {
            if (!is_array($category)) {
                continue;
            }
            $name = (string) ($category['name'] ?? '');
            $slug = (string) ($category['slug'] ?? '');
            if ($name === '' || $slug === '') {
                continue;
            }
            $url = function_exists('categoryUrl') ? categoryUrl($slug) : '#';
            $tagCloudItems[] = [
                'label' => $name,
                'url' => $url,
            ];
        }

        $footerCopyright = (string) ($settings['footer_copyright'] ?? '&copy; {current_year}. <a href="{base_url}/index.php" class="site-footer-brand-link">{site_name}</a> - Tüm hakları saklıdır.');
        $footerCopyright = str_replace(['{current_year}', '{base_url}', '{site_name}'], [date('Y'), rtrim($baseUri, '/'), htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8')], $footerCopyright);

        $footerNavLinksRaw = (string) ($settings['footer_nav_links'] ?? "Ana sayfa|{base_url}/index.php\nKategoriler|{base_url}/kategoriler\nEtkinlikler|{base_url}/events\nMod Yükle|{base_url}/upload-topic.php");
        $footerNavItems = [];
        foreach (explode("\n", str_replace("\r", "", $footerNavLinksRaw)) as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $label = trim($parts[0]);
                $url = trim($parts[1]);
                $url = str_replace('{base_url}', rtrim($baseUri, '/'), $url);
                $footerNavItems[] = ['label' => $label, 'url' => $url];
            }
        }

        return [
            'site_name' => $siteName,
            'site_description' => $siteDescription,
            'base_url' => rtrim($baseUri, '/'),
            'current_year' => date('Y'),
            'recent_comments' => $recentComments,
            'popular_topics' => $popularTopics,
            'tag_cloud_items' => $tagCloudItems,
            'footer_copyright' => $footerCopyright,
            'footer_nav_items' => $footerNavItems,
        ];
    }

    /**
     * @return array{head: string, scripts: string}
     */
    private static function extractAssetFragments(string &$content, bool $dropRootAssets, bool $dropInlineScripts = false): array
    {
        $head = [];
        $scripts = [];

        $content = (string) preg_replace_callback('~<link\b[^>]*rel=["\']stylesheet["\'][^>]*>\s*~i', static function (array $matches) use (&$head, $dropRootAssets): string {
            $tag = (string) $matches[0];
            $isThemeAsset = str_contains($tag, '/themes/');
            $isModuleAsset = str_contains($tag, '/events/assets/');
            if ($dropRootAssets && str_contains($tag, '/assets/') && !$isThemeAsset && !$isModuleAsset) {
                return '';
            }
            $head[] = trim($tag);
            return '';
        }, $content);

        $content = (string) preg_replace_callback('~<script\b(?=[^>]*\bsrc=)[^>]*>\s*</script>\s*~i', static function (array $matches) use (&$scripts, $dropRootAssets): string {
            $tag = (string) $matches[0];
            $isThemeAsset = str_contains($tag, '/themes/');
            $isModuleAsset = str_contains($tag, '/events/assets/');
            if ($dropRootAssets && str_contains($tag, '/assets/') && !$isThemeAsset && !$isModuleAsset) {
                return '';
            }
            $scripts[] = trim($tag);
            return '';
        }, $content);

        $content = (string) preg_replace_callback('~<script\b(?![^>]*\bsrc=)([^>]*)>.*?</script>\s*~is', static function (array $matches) use (&$scripts, $dropInlineScripts): string {
            $tag = (string) $matches[0];
            $attributes = strtolower((string) ($matches[1] ?? ''));
            if (str_contains($attributes, 'application/ld+json')) {
                return '';
            }
            if ($dropInlineScripts) {
                return '';
            }

            $scripts[] = trim($tag);
            return '';
        }, $content);

        return [
            'head' => implode("\n", array_unique($head)),
            'scripts' => implode("\n", array_unique($scripts)),
        ];
    }

    private static function publicSettingAsset(string $url, string $baseUri): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('~^(https?:)?//|^data:|^/~i', $url) === 1) {
            return $url;
        }

        return rtrim($baseUri, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderToastContainer(array $context): string
    {
        $settings = self::arrayValue($context, 'settings');
        $enabled = ($settings['toast_enabled'] ?? '1') === '1';
        $duration = (int) ($settings['toast_duration'] ?? 5000);
        $position = (string) ($settings['toast_position'] ?? 'bottom-right');
        $theme = (string) ($settings['toast_theme'] ?? 'default');
        $animation = (string) ($settings['toast_animation'] ?? 'slide');
        $progress = ($settings['toast_progress_bar'] ?? '1') === '1' ? 'true' : 'false';
        $close = ($settings['toast_close_button'] ?? '1') === '1' ? 'true' : 'false';
        $max = (int) ($settings['toast_max_visible'] ?? 5);
        $stack = (string) ($settings['toast_stack_direction'] ?? 'down');
        $click = ($settings['toast_click_to_close'] ?? '1') === '1' ? 'true' : 'false';
        $pause = ($settings['toast_pause_on_hover'] ?? '1') === '1' ? 'true' : 'false';
        $successDuration = (int) ($settings['toast_duration_success'] ?? 0);
        $errorDuration = (int) ($settings['toast_duration_error'] ?? 0);
        $warningDuration = (int) ($settings['toast_duration_warning'] ?? 0);

        $pageVars = self::arrayValue($context, 'page_vars');
        $success = (string) ($pageVars['successMsg'] ?? ($_SESSION['_flash_success'] ?? ''));
        $error = (string) ($pageVars['errorMsg'] ?? ($_SESSION['_flash_error'] ?? ''));
        $info = (string) ($pageVars['infoMsg'] ?? ($_SESSION['_flash_info'] ?? ''));
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error'], $_SESSION['_flash_info']);

        if (!$enabled) {
            return '<div class="topic-toast-container is-hidden ui-panel__foot" id="toastContainer"></div>';
        }

        return '<div class="topic-toast-container toast-pos-' . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . ' ui-panel__foot" id="toastContainer" aria-live="polite" aria-atomic="true"'
            . ' data-toast-duration="' . $duration . '"'
            . ' data-toast-theme="' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-toast-animation="' . htmlspecialchars($animation, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-toast-progress="' . $progress . '"'
            . ' data-toast-close="' . $close . '"'
            . ' data-toast-max="' . $max . '"'
            . ' data-toast-stack="' . htmlspecialchars($stack, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-toast-click-close="' . $click . '"'
            . ' data-toast-pause-hover="' . $pause . '"'
            . ' data-toast-dur-success="' . $successDuration . '"'
            . ' data-toast-dur-error="' . $errorDuration . '"'
            . ' data-toast-dur-warning="' . $warningDuration . '"'
            . ' data-toast-success="' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-toast-error="' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-toast-info="' . htmlspecialchars($info, ENT_QUOTES, 'UTF-8') . '"></div>';
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderPopupAnnouncement(array $context): string
    {
        if (!function_exists('renderPopupAnnouncementHtml')) {
            return '';
        }
        $settings = self::arrayValue($context, 'settings');
        $pdo = $GLOBALS['pdo'] ?? null;
        return renderPopupAnnouncementHtml($pdo, $settings);
    }

    private static function pageKey(string $currentScript, bool $isEventsRequest, string $currentRequestUri = ''): string
    {
        if ($isEventsRequest) {
            return 'events';
        }

        if (function_exists('routeAuthPageKey')) {
            $authPageKey = routeAuthPageKey($currentScript);
            if ($authPageKey !== '') {
                return $authPageKey;
            }
        }

        $requestPath = '/' . trim((string) parse_url($currentRequestUri, PHP_URL_PATH), '/');
        $routeType = self::pageKeyFromRequestPath($requestPath);
        if ($routeType !== '') {
            return $routeType;
        }

        return match ($currentScript) {
            'category.php' => 'category',
            'topic.php' => 'topic',
            'download.php' => 'download',
            'profile.php' => 'profile',
            'public-profile.php' => 'public_profile',
            'contact.php' => 'contact',
            'iletisim' => 'contact',
            'login.php' => 'login',
            'giris' => 'login',
            'register.php' => 'register',
            'kayit' => 'register',
            'forgot-password.php' => 'forgot_password',
            'sifremi-unuttum' => 'forgot_password',
            'reset-password.php' => 'reset_password',
            'sifre-sifirla' => 'reset_password',
            'upload-topic.php' => 'upload_topic',
            'edit-topic.php' => 'edit_topic',
            'notifications.php' => 'notifications',
            'messages.php' => 'messages',
            'leaderboard.php' => 'leaderboard',
            'ban-appeals.php' => 'ban_appeals',
            default => 'home',
        };
    }

    private static function pageKeyFromRequestPath(string $requestPath): string
    {
        $requestPath = '/' . trim(rawurldecode($requestPath), '/');
        if ($requestPath === '/') {
            return 'home';
        }

        $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn (string $part): bool => $part !== ''));
        $baseUri = trim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        if ($baseUri !== '') {
            $baseSegments = array_values(array_filter(explode('/', $baseUri), static fn (string $part): bool => $part !== ''));
            if ($baseSegments !== [] && array_slice($segments, 0, count($baseSegments)) === $baseSegments) {
                $segments = array_slice($segments, count($baseSegments));
            }
        }

        if (function_exists('routeAuthPageKey')) {
            $authPageKey = routeAuthPageKey($requestPath);
            if ($authPageKey !== '') {
                return $authPageKey;
            }
        }

        $prefix = strtolower((string) ($segments[0] ?? ''));
        if ($prefix === '') {
            return 'home';
        }

        if (self::matchesPublicStaticAlias('messages', $prefix)) {
            return 'messages';
        }

        $routes = function_exists('routePrefixSettings') ? routePrefixSettings($GLOBALS['pdo'] ?? null) : [];
        if (function_exists('routePrefixMatches')) {
            if (routePrefixMatches('topic', $prefix, $routes)) {
                return 'topic';
            }
            if (routePrefixMatches('category', $prefix, $routes) || routePrefixMatches('category_list', $prefix, $routes)) {
                return 'category';
            }
            if (routePrefixMatches('profile', $prefix, $routes)) {
                return isset($segments[1]) && (string) $segments[1] !== '' ? 'public_profile' : 'profile';
            }
        }

        return match ($prefix) {
            'konu', 'topic' => 'topic',
            'kategori', 'kategoriler', 'category', 'categories' => 'category',
            'profil', 'profile' => isset($segments[1]) && (string) $segments[1] !== '' ? 'public_profile' : 'profile',
            'profile.php' => 'profile',
            'public-profile.php' => 'public_profile',
            'iletisim', 'contact' => 'contact',
            'download.php', 'indir' => 'download',
            'konu-yukle', 'upload-topic', 'upload-topic.php', 'mod-yukle' => 'upload_topic',
            'konu-duzenle', 'edit-topic', 'edit-topic.php', 'mod-duzenle' => 'edit_topic',
            'bildirimler', 'notifications', 'notifications.php' => 'notifications',
            'messages.php' => 'messages',
            'liderlik', 'leaderboard', 'leaderboard.php' => 'leaderboard',
            'ban-itiraz', 'ban-appeals', 'ban-appeals.php' => 'ban_appeals',
            'giris' => 'login',
            'kayit' => 'register',
            'sifremi-unuttum' => 'forgot_password',
            'sifre-sifirla' => 'reset_password',
            default => '',
        };
    }

    private static function matchesPublicStaticAlias(string $routeKey, string $prefix): bool
    {
        if ($prefix === '' || !function_exists('routePublicStaticPathAliases')) {
            return false;
        }

        $aliases = routePublicStaticPathAliases($routeKey);
        foreach ($aliases as $alias) {
            $cleanAlias = strtolower(trim((string) $alias, '/'));
            if ($cleanAlias !== '' && $cleanAlias === $prefix) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $pageVars
     * @return array<string, mixed>
     */
    private static function uploadFormVars(array $pageVars, string $baseUri, string $pageKey): array
    {
        $isEdit = $pageKey === 'edit_topic' || (string) ($pageVars['upload_mode'] ?? '') === 'edit';
        $formData = self::arrayValue($pageVars, 'upload_form_data');
        $categories = [];
        foreach (self::normalizeList(self::arrayValue($pageVars, 'upload_categories')) as $category) {
            $categories[] = [
                'id' => (string) ($category['id'] ?? ''),
                'label' => (string) ($category['label'] ?? ''),
                'selected' => (string) ($category['selected'] ?? '') !== '',
            ];
        }

        $downloadLinks = [];
        foreach (self::normalizeList(self::arrayValue($pageVars, 'edit_download_links')) as $link) {
            $downloadLinks[] = [
                'name' => (string) ($link['name'] ?? ''),
                'url' => (string) ($link['url'] ?? ''),
            ];
        }
        if ($downloadLinks === []) {
            $downloadLinks[] = ['name' => '', 'url' => ''];
        }

        $coverMedia = [];
        $galleryMedia = [];
        foreach (self::normalizeList(self::arrayValue($pageVars, 'edit_existing_media')) as $media) {
            $item = [
                'id' => (string) ($media['id'] ?? ''),
                'url' => (string) ($media['url'] ?? ''),
                'label' => (string) ($media['label'] ?? 'Gorsel'),
            ];
            if (stripos((string) ($media['label'] ?? ''), 'kapak') !== false && $coverMedia === []) {
                $coverMedia[] = $item;
            } else {
                $galleryMedia[] = $item;
            }
        }

        $hourlyLimit = (int) ($pageVars['hourlyLimit'] ?? 0);
        $dailyLimit = (int) ($pageVars['dailyLimit'] ?? 0);
        $remainingHourly = $pageVars['remainingHourlyUploads'] ?? null;
        $remainingDaily = $pageVars['remainingDailyUploads'] ?? null;
        $limitRows = [];
        if ($hourlyLimit > 0) {
            $limitRows[] = [
                'label' => 'Saatlik',
                'text' => $remainingHourly === null ? 'kontrol edilecek' : ((string) (int) $remainingHourly . ' / ' . $hourlyLimit . ' kaldi'),
            ];
        }
        if ($dailyLimit > 0) {
            $limitRows[] = [
                'label' => 'Gunluk',
                'text' => $remainingDaily === null ? 'kontrol edilecek' : ((string) (int) $remainingDaily . ' / ' . $dailyLimit . ' kaldi'),
            ];
        }

        $allowedVideoHosts = '';
        if (isset($pageVars['allowedVideoHosts']) && is_array($pageVars['allowedVideoHosts'])) {
            $allowedVideoHosts = implode(', ', array_map('strval', $pageVars['allowedVideoHosts']));
        } elseif (isset($pageVars['allowedVideoHosts'])) {
            $allowedVideoHosts = (string) $pageVars['allowedVideoHosts'];
        }

        $wizardEnabled = $isEdit ? true : self::truthyValue($pageVars['wizardEnabled'] ?? true);
        $allowStepSkip = $isEdit ? true : self::truthyValue($pageVars['allowStepSkip'] ?? false);
        $coverRequired = !$isEdit && (string) ($pageVars['upload_cover_required'] ?? '') !== '';
        $galleryRequired = !$isEdit && (string) ($pageVars['upload_gallery_required'] ?? '') !== '';
        $requireAuthor = (string) ($pageVars['upload_author_required'] ?? '') !== '';
        $requireVersion = (string) ($pageVars['upload_version_required'] ?? '') !== '';
        $requireDownload = (string) ($pageVars['upload_download_required'] ?? '') !== '';
        $videoAllowed = self::truthyValue($pageVars['upload_video_allowed'] ?? false);
        $blockDuplicateTitles = self::truthyValue($pageVars['blockDuplicateTitles'] ?? false);

        return [
            'mode' => $isEdit ? 'edit' : 'create',
            'is_edit' => $isEdit,
            'is_create' => !$isEdit,
            'form_action' => (string) ($pageVars['upload_form_action'] ?? ($isEdit ? (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('edit_topic') : ($baseUri . '/edit-topic.php')) : (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('upload_topic') : ($baseUri . '/upload-topic.php')))),
            'cancel_url' => $isEdit ? ($baseUri . '/profile.php?tab=topics') : ($baseUri . '/index.php'),
            'profile_topics_url' => $baseUri . '/profile.php?tab=topics',
            'csrf_token' => (string) ($pageVars['upload_csrf_token'] ?? (function_exists('csrf_token') ? csrf_token() : '')),
            'submit_token' => (string) ($pageVars['upload_submit_token'] ?? ''),
            'has_submit_token' => (string) ($pageVars['upload_submit_token'] ?? '') !== '',
            'categories' => $categories,
            'title_value' => (string) ($pageVars['edit_title_value'] ?? ''),
            'content_value' => (string) ($pageVars['edit_content_value'] ?? ''),
            'author_value' => (string) ($pageVars['edit_author_value'] ?? ''),
            'version_value' => (string) ($pageVars['edit_version_value'] ?? ''),
            'video_value' => (string) ($pageVars['edit_video_url'] ?? ''),
            'status_label' => (string) ($pageVars['edit_status_label'] ?? ''),
            'moderation_note' => (string) ($pageVars['edit_moderation_note'] ?? ''),
            'has_moderation_note' => self::truthyValue($pageVars['edit_has_moderation_note'] ?? false),
            'cover_media' => $coverMedia,
            'has_cover_media' => $coverMedia !== [],
            'gallery_media' => $galleryMedia,
            'has_gallery_media' => $galleryMedia !== [],
            'download_links' => $downloadLinks,
            'min_title_length' => (string) ($pageVars['upload_min_title_length'] ?? '0'),
            'max_title_length' => (string) ($pageVars['upload_max_title_length'] ?? '150'),
            'min_content_length' => (string) ($pageVars['upload_min_content_length'] ?? '0'),
            'accept_image_attr' => (string) ($pageVars['upload_accept_image_attr'] ?? 'image/*'),
            'allowed_image_ext_text' => (string) ($pageVars['upload_allowed_image_ext_text'] ?? ''),
            'image_dimension_rule_text' => (string) ($pageVars['upload_image_dimension_rule_text'] ?? ''),
            'attachment_accept' => (string) ($pageVars['upload_attachment_accept'] ?? '.zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp'),
            'default_content_align' => (string) ($pageVars['upload_default_content_align'] ?? 'center'),
            'notice' => (string) ($pageVars['upload_notice'] ?? ''),
            'cover_required' => $coverRequired,
            'gallery_required' => $galleryRequired,
            'author_required' => $requireAuthor,
            'version_required' => $requireVersion,
            'download_required' => $requireDownload,
            'video_allowed' => $videoAllowed,
            'allowed_video_hosts_text' => $allowedVideoHosts !== '' ? $allowedVideoHosts : 'Tum saglayicilar',
            'author_rule_text' => $requireAuthor ? 'Zorunlu' : 'Istege bagli',
            'version_rule_text' => $requireVersion ? 'Zorunlu' : 'Istege bagli',
            'download_rule_text' => $requireDownload ? 'En az 1 gecerli link zorunlu' : 'Link istege bagli',
            'cover_help_text' => $coverRequired ? 'Bu alan zorunludur.' : 'Bu alan istege baglidir.',
            'gallery_help_text' => $galleryRequired ? 'En az 1 mod resmi gereklidir.' : 'Mod resimleri istege baglidir.',
            'wizard_enabled' => $wizardEnabled,
            'wizard_class' => $wizardEnabled ? '' : 'is-hidden',
            'hide_inactive_panels' => $wizardEnabled,
            'allow_step_skip' => $allowStepSkip,
            'lock_after_submit' => (string) ($formData['lock_after_submit'] ?? (!$isEdit && self::truthyValue($pageVars['lockAfterSubmit'] ?? false) ? '1' : '0')),
            'max_images' => (string) ($formData['max_images'] ?? ($pageVars['maxImages'] ?? '10')),
            'cover_max_size_mb' => (string) ($formData['cover_max_size_mb'] ?? ($pageVars['coverMaxSizeMb'] ?? '10')),
            'gallery_max_size_mb' => (string) ($formData['gallery_max_size_mb'] ?? ($pageVars['galleryMaxSizeMb'] ?? '10')),
            'attachment_max_size_mb' => (string) ($formData['attachment_max_size_mb'] ?? ($pageVars['attachmentMaxSizeMb'] ?? '50')),
            'allowed_image_ext' => (string) ($formData['allowed_image_ext'] ?? ''),
            'image_min_width' => (string) ($formData['image_min_width'] ?? '0'),
            'image_min_height' => (string) ($formData['image_min_height'] ?? '0'),
            'image_max_width' => (string) ($formData['image_max_width'] ?? '0'),
            'image_max_height' => (string) ($formData['image_max_height'] ?? '0'),
            'require_author_data' => $requireAuthor ? '1' : '0',
            'require_version_data' => $requireVersion ? '1' : '0',
            'require_download_link_data' => $requireDownload ? '1' : '0',
            'require_cover_data' => $coverRequired ? '1' : '0',
            'require_gallery_data' => $galleryRequired ? '1' : '0',
            'allow_video_url_data' => $videoAllowed ? '1' : '0',
            'allowed_video_hosts_data' => isset($pageVars['allowedVideoHosts']) && is_array($pageVars['allowedVideoHosts'])
                ? implode(',', array_map('strval', $pageVars['allowedVideoHosts']))
                : (string) ($pageVars['allowedVideoHosts'] ?? ''),
            'hourly_limit' => (string) $hourlyLimit,
            'daily_limit' => (string) $dailyLimit,
            'block_duplicate_titles_data' => $blockDuplicateTitles ? '1' : '0',
            'has_limits' => $limitRows !== [],
            'limit_rows' => $limitRows,
            'block_duplicate_titles' => $blockDuplicateTitles,
            'show_profile_followup' => !$isEdit && self::truthyValue($pageVars['showProfileFollowup'] ?? false),
            'show_profile_button' => !$isEdit && self::truthyValue($pageVars['showProfileButton'] ?? false),
        ];
    }

    private static function truthyValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return !in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'no', 'off', 'null'], true);
    }

    /**
     * @param array<string, mixed> $pageVars
     * @return array<string, mixed>
     */
    private static function profileTemplateVars(array $pageVars, string $baseUri, string $pageKey = ''): array
    {
        $user = isset($pageVars['user']) && is_array($pageVars['user']) ? $pageVars['user'] : [];
        $isProfilePage = in_array($pageKey, ['profile', 'public_profile'], true);
        if ($isProfilePage && $user === []) {
            return [
                'name' => (string) ($pageVars['pageTitle'] ?? 'Kullanici'),
                'bio' => '',
                'avatar' => '',
                'has_avatar' => false,
                'avatar_fallback' => function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri) : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg',
                'cover' => '',
                'is_public' => false,
                'is_private' => false,
                'use_captured_content' => true,
                'group' => '',
                'group_badge_html' => '',
                'status_badge_html' => '',
                'initials' => 'U',
                'location' => '',
                'has_location' => false,
                'member_since' => '',
                'page_summary' => '',
            ];
        }

        $isPublic = !empty($pageVars['profile_is_public']);
        $isPrivate = !empty($pageVars['profile_is_private']);
        $profileContext = isset($pageVars['profileContext']) && is_array($pageVars['profileContext']) ? $pageVars['profileContext'] : [];
        if ($profileContext !== []) {
            $profileContext['is_public'] = $isPublic;
            $profileContext['is_private'] = $isPrivate;
            $profileContext['use_captured_content'] = !$isProfilePage;
            $profileContext['page_summary'] = (string) ($pageVars['profile_page_summary'] ?? ($profileContext['page_summary'] ?? ''));
            $profileContext['csrf_token'] = function_exists('csrf_token') ? csrf_token() : '';

            if ($isPublic) {
                return array_replace($profileContext, self::publicProfileVars($pageVars, $baseUri, $user, (string) ($profileContext['group_slug'] ?? ($pageVars['group_slug'] ?? $user['group_slug'] ?? 'member')), (string) ($profileContext['group_name'] ?? ($pageVars['profile_group_name'] ?? $pageVars['profile_private_group'] ?? $user['group_name'] ?? 'Kullanıcı Grubu'))));
            }

            if ($isPrivate) {
                return array_replace($profileContext, self::privateProfileVars($pageVars, $baseUri, $user, (string) ($profileContext['group_slug'] ?? ($pageVars['group_slug'] ?? $user['group_slug'] ?? 'member')), (string) ($profileContext['group_name'] ?? ($pageVars['profile_group_name'] ?? $pageVars['profile_private_group'] ?? $user['group_name'] ?? 'Kullanıcı Grubu'))));
            }

            return $profileContext;
        }

        $name = (string) ($pageVars['profile_name'] ?? $pageVars['profile_private_name'] ?? $user['name'] ?? $_SESSION['_auth_user_name'] ?? 'Kullanici');
        $avatar = (string) ($pageVars['profile_avatar_url'] ?? $pageVars['profile_private_avatar'] ?? $pageVars['avatarUrl'] ?? '');
        $avatarFallback = function_exists('defaultAvatarUrl')
            ? defaultAvatarUrl($baseUri)
            : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        if ($avatar !== '' && function_exists('resolveAvatarUrl')) {
            $avatar = resolveAvatarUrl($avatar, $baseUri, true);
        }
        $groupName = (string) ($pageVars['profile_group_name'] ?? $pageVars['profile_private_group'] ?? $user['group_name'] ?? 'Kullanıcı Grubu');
        $groupSlug = (string) ($pageVars['groupSlug'] ?? $pageVars['group_slug'] ?? $user['group_slug'] ?? 'member');
        $createdAt = (string) ($user['created_at'] ?? date('Y-m-d'));
        $updatedAt = (string) ($user['updated_at'] ?? $createdAt);
        $location = (string) ($pageVars['profile_location'] ?? $pageVars['profile_private_location'] ?? $user['location'] ?? '');

        $profile = [
            'id' => (int) ($user['id'] ?? $pageVars['profileUserId'] ?? 0),
            'name' => $name,
            'email' => (string) ($pageVars['profile_private_email'] ?? $user['email'] ?? ''),
            'bio' => (string) ($pageVars['profile_bio'] ?? $pageVars['profile_private_bio'] ?? $user['bio'] ?? ''),
            'avatar' => $avatar,
            'has_avatar' => $avatar !== '' && $avatar !== $avatarFallback,
            'avatar_fallback' => $avatarFallback,
            'cover' => (string) ($user['cover'] ?? $user['cover_image'] ?? ''),
            'is_public' => $isPublic,
            'is_private' => $isPrivate,
            'use_captured_content' => !$isProfilePage,
            'group' => $groupName,
            'group_slug' => self::safeClassToken($groupSlug, 'member'),
            'status_label' => self::profileStatusLabel($user),
            'status_badge_class' => self::profileStatusBadgeClass($user),
            'initials' => (string) ($pageVars['profile_initials'] ?? $pageVars['profile_private_initials'] ?? self::initial($name)),
            'location' => $location,
            'has_location' => trim($location) !== '',
            'member_since' => (string) ($pageVars['profile_member_since'] ?? (function_exists('profileMemberSince') ? profileMemberSince($createdAt) : self::formatDate($createdAt))),
            'tenure' => function_exists('profileTenureLabel') ? profileTenureLabel($createdAt) : '',
            'created_date' => self::formatDate($createdAt),
            'updated_date' => self::formatDate($updatedAt),
            'created_at' => self::formatDateTime($createdAt),
            'updated_at' => self::formatDateTime($updatedAt),
            'page_summary' => (string) ($pageVars['profile_page_summary'] ?? ''),
            'csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
            'website' => function_exists('safeExternalUrl') ? safeExternalUrl((string) ($pageVars['profile_private_website'] ?? $user['website'] ?? ''), '') : (string) ($pageVars['profile_private_website'] ?? $user['website'] ?? ''),
            'social_github' => (string) ($pageVars['profile_private_social_github'] ?? $user['social_github'] ?? ''),
            'social_twitter' => (string) ($pageVars['profile_private_social_twitter'] ?? $user['social_twitter'] ?? ''),
            'social_discord' => (string) ($pageVars['profile_private_social_discord'] ?? $user['social_discord'] ?? ''),
        ];

        if ($isPublic) {
            return array_replace($profile, self::publicProfileVars($pageVars, $baseUri, $user, $groupSlug, $groupName));
        }

        if ($isPrivate) {
            return array_replace($profile, self::privateProfileVars($pageVars, $baseUri, $user, $groupSlug, $groupName));
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private static function publicProfileVars(array $pageVars, string $baseUri, array $user, string $groupSlug, string $groupName): array
    {
        $profileContext = isset($pageVars['profileContext']) && is_array($pageVars['profileContext']) ? $pageVars['profileContext'] : [];
        $socialLinks = self::normalizeLinkList(self::arrayValue($pageVars, 'profile_social_links'));
        if ($socialLinks === [] && $profileContext !== []) {
            $socialLinks = self::normalizeLinkList(self::arrayValue($profileContext, 'social_links'));
        }
        $stats = self::normalizeList(self::arrayValue($pageVars, 'profile_stats'));
        if ($stats === [] && $profileContext !== []) {
            $stats = self::normalizeList(self::arrayValue($profileContext, 'stats'));
        }
        $canReport = !empty($pageVars['profile_can_report']) || !empty($profileContext['can_report']);
        $canMessage = !empty($pageVars['profile_can_message']) || !empty($profileContext['can_message']);
        $messageUrl = (string) ($pageVars['profile_message_url'] ?? ($profileContext['message_url'] ?? ''));
        if ($messageUrl === '' && $canMessage && function_exists('routePublicStaticUrl')) {
            $messageUrl = routePublicStaticUrl('messages') . '?with=' . (int) ($user['id'] ?? 0);
        }
        $sidebarStats = [];
        $sidebarStatClasses = ['stat-info', '', 'stat-success', 'stat-warning'];
        foreach ($stats as $index => $stat) {
            $sidebarStats[] = [
                'class' => (string) ($stat['class'] ?? $sidebarStatClasses[$index] ?? ''),
                'icon' => (string) ($stat['icon'] ?? 'bi-bar-chart'),
                'value' => (string) ($stat['value'] ?? '0'),
                'label' => (string) ($stat['label'] ?? ''),
            ];
        }
        $topics = self::normalizeList(self::arrayValue($pageVars, 'profile_topics'));
        foreach ($topics as $index => $topic) {
            if (!is_array($topic)) {
                continue;
            }

            if (trim((string) ($topic['image_alt'] ?? '')) === '') {
                $topic['image_alt'] = (string) ($topic['title'] ?? 'Konu') . ' kapak gorseli';
            }
            if (trim((string) ($topic['image_title'] ?? '')) === '') {
                $topic['image_title'] = (string) ($topic['title'] ?? 'Konu') . ' kapak gorseli';
            }

            $topics[$index] = $topic;
        }
        $collections = self::normalizeList(self::arrayValue($pageVars, 'profile_public_collections'));
        $reportReasons = self::normalizeList(self::arrayValue($pageVars, 'profile_report_reasons'));

        return [
            'stats' => $stats,
            'sidebar_stats' => $sidebarStats,
            'social_links' => $socialLinks,
            'has_social_links' => $socialLinks !== [],
            'has_sidebar_actions' => $socialLinks !== [] || $canReport || $canMessage,
            'has_actions' => $socialLinks !== [] || $canReport || $canMessage,
            'can_report' => $canReport,
            'can_message' => $canMessage,
            'message_url' => $messageUrl,
            'show_account_info' => false,
            'report_endpoint' => (string) ($pageVars['profile_report_endpoint'] ?? rtrim($baseUri, '/') . '/api/user-reports.php'),
            'report_reasons' => $reportReasons,
            'show_topics' => !empty($pageVars['profile_show_topics']),
            'topics_hidden' => !empty($pageVars['profile_topics_hidden']),
            'topics_empty' => !empty($pageVars['profile_topics_empty']),
            'topic_count_label' => (string) ($pageVars['profile_topic_count_label'] ?? ''),
            'page_summary' => (string) ($pageVars['profile_page_summary'] ?? ''),
            'topics' => $topics,
            'pagination_groups' => self::profilePublicPaginationGroups($pageVars, $user),
            'pagination_html' => (string) ($pageVars['profile_pagination_html'] ?? ''),
            'public_collections' => $collections,
            'has_public_collections' => !empty($pageVars['profile_has_public_collections']) || $collections !== [],
        ];
    }

    /**
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private static function privateProfileVars(array $pageVars, string $baseUri, array $user, string $groupSlug, string $groupName): array
    {
        $activeTab = (string) ($pageVars['activeTab'] ?? 'overview');
        $totals = self::arrayValue($pageVars, 'profileTotals');
        $stats = self::arrayValue($pageVars, 'stats');
        $userTopics = self::normalizeList(self::arrayValue($pageVars, 'userTopics'));
        $pendingTopics = self::normalizeList(self::arrayValue($pageVars, 'pendingTopics'));
        $userComments = self::normalizeList(self::arrayValue($pageVars, 'userComments'));
        $userFavorites = self::normalizeList(self::arrayValue($pageVars, 'userFavorites'));
        $userActivity = self::normalizeList(self::arrayValue($pageVars, 'userActivity'));
        $userReports = self::normalizeList(self::arrayValue($pageVars, 'userReports'));
        $userCollections = self::normalizeList(self::arrayValue($pageVars, 'userCollections'));
        $collectionItems = self::arrayValue($pageVars, 'collectionItems');
        $tabs = self::normalizeList(self::arrayValue($pageVars, 'profile_private_tabs'));
        $socialLinks = self::privateSocialLinks($pageVars, $user);

        $topics = [];
        foreach ($userTopics as $row) {
            $topics[] = self::profileTopicItem($row, $baseUri, true);
        }

        $pending = [];
        foreach ($pendingTopics as $row) {
            $pending[] = self::profilePendingTopicItem($row, $baseUri);
        }

        $comments = [];
        foreach ($userComments as $row) {
            $comments[] = self::profileCommentItem($row);
        }

        $favorites = [];
        foreach ($userFavorites as $row) {
            $favorites[] = self::profileFavoriteItem($row, $userCollections, $collectionItems);
        }

        $reports = [];
        foreach ($userReports as $row) {
            $reports[] = self::profileReportItem($row);
        }

        $activity = [];
        foreach ($userActivity as $row) {
            $activity[] = self::profileActivityItem($row);
        }

        $securityEvents = [];
        foreach ($userActivity as $row) {
            $action = (string) ($row['action'] ?? '');
            if (str_contains($action, 'login') || str_contains($action, 'password')) {
                $securityEvents[] = self::profileSecurityEventItem($row);
            }
            if (count($securityEvents) >= 5) {
                break;
            }
        }

        $topicStatusOptions = [];
        $topicStatusFilter = (string) ($pageVars['topicStatusFilter'] ?? 'all');
        foreach (self::arrayValue($pageVars, 'topicStatusOptions') as $key => $option) {
            if (!is_array($option)) {
                continue;
            }
            $topicStatusOptions[] = [
                'url' => rtrim($baseUri, '/') . '/profile.php?tab=topics&topic_status=' . rawurlencode((string) $key),
                'class' => 'profile-topic-status-filter-link' . ($topicStatusFilter === (string) $key ? ' active' : ''),
                'icon' => (string) ($option[1] ?? 'bi-circle'),
                'label' => (string) ($option[0] ?? $key),
            ];
        }

        $activityFilterOptions = [];
        $activityFilter = (string) ($pageVars['activityFilter'] ?? 'all');
        $activityCounts = self::arrayValue($pageVars, 'activityFilterCounts');
        foreach (self::arrayValue($pageVars, 'activityFilterOptions') as $key => $option) {
            if (!is_array($option)) {
                continue;
            }
            $url = rtrim($baseUri, '/') . '/profile.php?tab=activity';
            if ((string) $key !== 'all') {
                $url .= '&activity_type=' . rawurlencode((string) $key);
            }
            $activityFilterOptions[] = [
                'url' => $url,
                'class' => 'profile-topic-status-filter-link profile-activity-filter-link' . ($activityFilter === (string) $key ? ' active' : ''),
                'icon' => (string) ($option['icon'] ?? 'bi-circle'),
                'label' => (string) ($option['label'] ?? $key),
                'count' => number_format((int) ($activityCounts[$key] ?? 0), 0, ',', '.'),
            ];
        }

        $collections = [];
        foreach ($userCollections as $collection) {
            $visibility = (string) ($collection['visibility'] ?? 'private');
            $collections[] = [
                'id' => (int) ($collection['id'] ?? 0),
                'name' => (string) ($collection['name'] ?? 'Koleksiyon'),
                'count' => number_format((int) ($collection['item_count'] ?? 0), 0, ',', '.') . ' icerik',
                'description' => (string) ($collection['description'] ?? ''),
                'visibility_label' => $visibility === 'public' ? 'Public profilde gorunur' : 'Sadece size gorunur',
                'toggle_visibility' => $visibility === 'public' ? 'private' : 'public',
                'toggle_title' => $visibility === 'public' ? 'Gizle' : 'Public yap',
                'toggle_icon' => $visibility === 'public' ? 'bi-eye-slash' : 'bi-eye',
            ];
        }

        $privacyOptions = self::profilePrivacyOptions($pageVars, $user);
        $totalTopics = (int) ($totals['topics'] ?? 0);
        $totalComments = (int) ($totals['comments'] ?? 0);
        $totalFavorites = (int) ($totals['favorites'] ?? 0);
        $totalReports = (int) ($totals['reports'] ?? 0);
        $totalActivity = (int) ($totals['activity'] ?? 0);

        return [
            'active_tab' => $activeTab,
            'success' => !empty($pageVars['profile_private_suppress_success_alert']) ? '' : (string) ($pageVars['profile_private_success'] ?? ''),
            'error' => (string) ($pageVars['profile_private_error'] ?? ''),
            'pw_success' => (string) ($pageVars['profile_private_pw_success'] ?? ''),
            'pw_error' => (string) ($pageVars['profile_private_pw_error'] ?? ''),
            'followup_title' => (($_GET['edited'] ?? '') === '1') ? 'Degisiklikler onaya gonderildi' : 'Modunuz onaya gonderildi',
            'followup_message' => (($_GET['submitted'] ?? '') === '1' || ($_GET['edited'] ?? '') === '1') ? 'Onay bekleyen icerikler bu sekmede gorunur. Moderator notu gelirse ayni karttan duzenleyip tekrar gonderebilirsiniz.' : '',
            'tabs' => $tabs,
            'quick_links' => [
                ['url' => rtrim($baseUri, '/') . '/profile.php?tab=topics', 'icon' => 'bi-file-earmark-text', 'value' => number_format($totalTopics, 0, ',', '.'), 'label' => 'Konularim'],
                ['url' => rtrim($baseUri, '/') . '/profile.php?tab=favorites', 'icon' => 'bi-heart', 'value' => number_format($totalFavorites, 0, ',', '.'), 'label' => 'Favorilerim'],
                ['url' => rtrim($baseUri, '/') . '/profile.php?tab=comments', 'icon' => 'bi-chat-dots', 'value' => number_format($totalComments, 0, ',', '.'), 'label' => 'Yorumlarim'],
                ['url' => (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('notifications') : (rtrim($baseUri, '/') . '/notifications.php')), 'icon' => 'bi-bell', 'value' => 'Merkez', 'label' => 'Bildirimler'],
                ['url' => rtrim($baseUri, '/') . '/profile.php?tab=settings', 'icon' => 'bi-gear', 'value' => 'Profil', 'label' => 'Ayarlar'],
            ],
            'tab_overview' => $activeTab === 'overview',
            'tab_topics' => $activeTab === 'topics',
            'tab_comments' => $activeTab === 'comments',
            'tab_favorites' => $activeTab === 'favorites',
            'tab_reports' => $activeTab === 'reports',
            'tab_activity' => $activeTab === 'activity',
            'tab_settings' => $activeTab === 'settings',
            'tab_security' => $activeTab === 'security',
            'total_topics' => number_format($totalTopics, 0, ',', '.'),
            'total_comments' => number_format($totalComments, 0, ',', '.'),
            'total_favorites' => number_format($totalFavorites, 0, ',', '.'),
            'total_reports' => number_format($totalReports, 0, ',', '.'),
            'total_activity' => number_format($totalActivity, 0, ',', '.'),
            'sidebar_stats' => [
                ['class' => 'stat-info', 'icon' => 'bi-file-earmark-text', 'value' => number_format((int) ($stats['topics'] ?? $totalTopics), 0, ',', '.'), 'label' => 'Konu'],
                ['class' => '', 'icon' => 'bi-chat-dots', 'value' => number_format((int) ($stats['comments'] ?? $totalComments), 0, ',', '.'), 'label' => 'Yorum'],
                ['class' => 'stat-success', 'icon' => 'bi-eye', 'value' => number_format((int) ($stats['views'] ?? 0), 0, ',', '.'), 'label' => 'Görüntülenme'],
                ['class' => 'stat-warning', 'icon' => 'bi-download', 'value' => number_format((int) ($stats['downloads'] ?? 0), 0, ',', '.'), 'label' => 'Indirme'],
            ],
            'social_links' => $socialLinks,
            'has_social_links' => $socialLinks !== [],
            'has_sidebar_actions' => $socialLinks !== [],
            'can_report' => false,
            'show_account_info' => true,
            'restrictions' => self::profileRestrictionItems(self::normalizeList(self::arrayValue($pageVars, 'userRestrictions'))),
            'has_restrictions' => !empty($pageVars['userRestrictions']),
            'topics' => $topics,
            'topics_preview' => array_slice($topics, 0, 5),
            'has_topics' => $topics !== [],
            'more_topics_url' => count($topics) > 5 ? rtrim($baseUri, '/') . '/profile.php?tab=topics' : '',
            'pending_topics' => $pending,
            'has_pending_topics' => $pending !== [],
            'topic_status_options' => $topicStatusOptions,
            'comments' => $comments,
            'comments_preview' => array_slice($comments, 0, 5),
            'has_comments' => $comments !== [],
            'favorites' => $favorites,
            'has_favorites' => $favorites !== [],
            'collections' => $collections,
            'has_collections' => $collections !== [],
            'reports' => $reports,
            'has_reports' => $reports !== [],
            'activity' => $activity,
            'activity_preview' => array_slice($activity, 0, 8),
            'has_activity' => $activity !== [],
            'activity_filter_options' => $activityFilterOptions,
            'activity_empty_text' => $activityFilter === 'all' ? 'Henuz aktivite yok.' : 'Bu filtrede aktivite yok.',
            'activity_empty_url' => $activityFilter === 'all' ? rtrim($baseUri, '/') . '/profile.php?tab=settings' : rtrim($baseUri, '/') . '/profile.php?tab=activity',
            'activity_empty_action' => $activityFilter === 'all' ? 'Profil ayarlarina git' : 'Tum aktiviteler',
            'topics_pagination_groups' => self::profilePrivatePaginationGroups($pageVars, $baseUri, 'topics'),
            'comments_pagination_groups' => self::profilePrivatePaginationGroups($pageVars, $baseUri, 'comments'),
            'favorites_pagination_groups' => self::profilePrivatePaginationGroups($pageVars, $baseUri, 'favorites'),
            'reports_pagination_groups' => self::profilePrivatePaginationGroups($pageVars, $baseUri, 'reports'),
            'activity_pagination_groups' => self::profilePrivatePaginationGroups($pageVars, $baseUri, 'activity'),
            'topics_pagination_html' => self::callProfilePagination($pageVars, 'topics'),
            'comments_pagination_html' => self::callProfilePagination($pageVars, 'comments'),
            'favorites_pagination_html' => self::callProfilePagination($pageVars, 'favorites'),
            'reports_pagination_html' => self::callProfilePagination($pageVars, 'reports'),
            'activity_pagination_html' => self::callProfilePagination($pageVars, 'activity'),
            'privacy_options' => $privacyOptions,
            'password_min_length' => (string) ($pageVars['profile_password_min_length'] ?? '8'),
            'password_policy_hint' => (string) ($pageVars['profile_password_policy_hint'] ?? ''),
            'password_require_uppercase' => !empty($pageVars['profile_password_require_uppercase']),
            'password_require_numbers' => !empty($pageVars['profile_password_require_numbers']),
            'password_require_special' => !empty($pageVars['profile_password_require_special']),
            'security_checks' => [
                ['icon' => 'bi-check-circle-fill', 'class' => 'profile-success-icon', 'label' => 'Guvenli oturum cerezleri'],
                ['icon' => 'bi-check-circle-fill', 'class' => 'profile-success-icon', 'label' => 'Sifre bcrypt ile hashlenmis'],
                ['icon' => !empty($user['email_verified_at']) ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill', 'class' => !empty($user['email_verified_at']) ? 'profile-success-icon' : 'profile-warning-icon', 'label' => !empty($user['email_verified_at']) ? 'E-posta dogrulanmis' : 'E-posta dogrulanmamis'],
            ],
            'session_info' => [
                ['label' => 'Bu cihaz', 'value' => !empty($_SESSION['_auth_login_time']) ? date('d.m.Y H:i', (int) $_SESSION['_auth_login_time']) : 'Bilinmiyor'],
                ['label' => 'Son etkinlik', 'value' => !empty($_SESSION['_auth_last_activity']) ? date('d.m.Y H:i', (int) $_SESSION['_auth_last_activity']) : 'Simdi'],
                ['label' => 'Oturum modu', 'value' => !empty($_SESSION['_auth_remember_session']) ? 'Bu cihazda hatirlaniyor' : 'Standart sureli oturum'],
            ],
            'security_events' => $securityEvents,
            'has_security_events' => $securityEvents !== [],
        ];
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private static function arrayValue(array $array, string $key): array
    {
        return isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeList(array $items): array
    {
        $list = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeLinkList(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $links[] = [
                'icon' => (string) ($item['icon'] ?? 'bi-link-45deg'),
                'url' => (string) ($item['url'] ?? '#'),
                'title' => (string) ($item['title'] ?? 'Baglanti'),
            ];
        }

        return $links;
    }

    private static function safeClassToken(string $value, string $fallback = 'item'): string
    {
        $token = preg_replace('/[^a-z0-9_-]/i', '', strtolower($value));
        return $token !== '' ? $token : $fallback;
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function profileStatusLabel(array $user): string
    {
        return function_exists('profileResolveStatusLabel')
            ? profileResolveStatusLabel($user)
            : (empty($user['is_banned'])
                ? (($user['status'] ?? 'active') === 'active' ? 'Aktif' : 'Devre Disi')
                : 'Banli');
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function profileStatusBadgeClass(array $user): string
    {
        return function_exists('profileResolveStatusBadgeClass')
            ? profileResolveStatusBadgeClass($user)
            : (empty($user['is_banned'])
                ? (($user['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-secondary')
                : 'badge-danger');
    }

    private static function formatDate(string $value): string
    {
        $time = strtotime($value) ?: time();
        return date('d.m.Y', $time);
    }

    private static function formatDateTime(string $value): string
    {
        $time = strtotime($value) ?: time();
        return date('d.m.Y H:i', $time);
    }

    /**
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $user
     * @return array<int, array<string, string>>
     */
    private static function privateSocialLinks(array $pageVars, array $user): array
    {
        $linkUser = [
            'website' => (string) ($pageVars['profile_private_website'] ?? $user['website'] ?? ''),
            'social_github' => (string) ($pageVars['profile_private_social_github'] ?? $user['social_github'] ?? ''),
            'social_twitter' => (string) ($pageVars['profile_private_social_twitter'] ?? $user['social_twitter'] ?? ''),
            'social_discord' => (string) ($pageVars['profile_private_social_discord'] ?? $user['social_discord'] ?? ''),
        ];

        if (function_exists('profileBuildSocialLinks')) {
            return profileBuildSocialLinks($linkUser);
        }

        $links = [];
        $website = trim((string) $linkUser['website']);
        $github = trim((string) $linkUser['social_github']);
        $twitter = trim((string) $linkUser['social_twitter']);
        $discord = trim((string) $linkUser['social_discord']);

        if ($website !== '') {
            $links[] = ['icon' => 'bi-globe2', 'url' => function_exists('safeExternalUrl') ? safeExternalUrl($website) : $website, 'title' => 'Web Sitesi'];
        }
        if ($github !== '') {
            $links[] = ['icon' => 'bi-github', 'url' => 'https://github.com/' . rawurlencode($github), 'title' => 'GitHub'];
        }
        if ($twitter !== '') {
            $links[] = ['icon' => 'bi-twitter-x', 'url' => 'https://x.com/' . rawurlencode($twitter), 'title' => 'X / Twitter'];
        }
        if ($discord !== '') {
            $links[] = ['icon' => 'bi-discord', 'url' => '#', 'title' => 'Discord: ' . $discord];
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profileTopicItem(array $row, string $baseUri, bool $includeEdit): array
    {
        $created = (string) ($row['published_at'] ?? $row['created_at'] ?? 'now');
        $item = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Konu'),
            'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#',
            'category' => (string) ($row['category'] ?? $row['category_name'] ?? 'Genel'),
            'views' => number_format((int) ($row['view_count'] ?? 0), 0, ',', '.'),
            'downloads' => number_format((int) ($row['download_count'] ?? 0), 0, ',', '.'),
            'comments' => number_format((int) ($row['comment_count'] ?? 0), 0, ',', '.'),
            'date' => self::formatDate($created),
        ];

        if ($includeEdit) {
            $item['edit_url'] = (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('edit_topic') : (rtrim($baseUri, '/') . '/edit-topic.php')) . '?id=' . (int) ($row['id'] ?? 0);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profilePendingTopicItem(array $row, string $baseUri): array
    {
        $status = (string) ($row['status'] ?? 'draft');
        if ($status === 'pending') {
            $status = 'draft';
        }
        $labels = [
            'draft' => ['Taslak', 'bi-pencil-square'],
            'revision' => ['Revizyon Istendi', 'bi-arrow-repeat'],
            'rejected' => ['Reddedildi', 'bi-x-circle'],
        ];
        $flags = [];
        if (!empty($row['moderation_flags'])) {
            $decoded = json_decode((string) $row['moderation_flags'], true);
            $flags = is_array($decoded) ? $decoded : [];
        }
        $meta = $labels[$status] ?? [ucfirst($status), 'bi-info-circle'];

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Konu'),
            'edit_url' => (function_exists('routePublicStaticUrl') ? routePublicStaticUrl('edit_topic') : (rtrim($baseUri, '/') . '/edit-topic.php')) . '?id=' . (int) ($row['id'] ?? 0),
            'category' => (string) ($row['category'] ?? 'Genel'),
            'updated' => self::formatDateTime((string) ($row['updated_at'] ?? $row['created_at'] ?? 'now')),
            'moderation_note' => trim((string) ($flags['note'] ?? '')),
            'needs_revision' => in_array($status, ['revision', 'rejected'], true),
            'status_label' => $meta[0],
            'status_icon' => $meta[1],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profileCommentItem(array $row): array
    {
        $date = (string) ($row['created_at'] ?? 'now');
        $url = '#';
        if (function_exists('topicUrl')) {
            $url = topicUrl((string) ($row['topic_slug'] ?? ''), (int) ($row['topic_id'] ?? 0)) . '#comment-' . (int) ($row['id'] ?? 0);
        } elseif (isset($row['topic_id'])) {
            $url = function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#';
        }

        return [
            'body' => mb_substr(trim(strip_tags((string) ($row['body'] ?? $row['content'] ?? ''))), 0, 180),
            'topic' => (string) ($row['topic_title'] ?? $row['title'] ?? 'Konu'),
            'date' => self::formatDateTime($date),
            'date_short' => self::formatDate($date),
            'url' => $url,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $collections
     * @param array<string|int, mixed> $collectionItems
     * @return array<string, mixed>
     */
    private static function profileFavoriteItem(array $row, array $collections, array $collectionItems): array
    {
        $category = (string) ($row['category'] ?? $row['category_name'] ?? 'Genel');
        $categorySlug = (string) ($row['category_slug'] ?? (function_exists('slugify') ? slugify($category) : ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Konu'),
            'url' => function_exists('topicUrlForRow') ? topicUrlForRow($row) : '#',
            'category' => $category,
            'category_url' => $categorySlug !== '' && function_exists('categoryUrl') ? categoryUrl($categorySlug) : '#',
            'author' => (string) ($row['author'] ?? '-'),
            'views' => number_format((int) ($row['view_count'] ?? 0), 0, ',', '.'),
            'date' => self::formatDate((string) ($row['favorited_at'] ?? 'now')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profileReportItem(array $row): array
    {
        $status = (string) ($row['status'] ?? 'open');
        $statusLabels = [
            'open' => ['Acik', 'warning'],
            'reviewing' => ['Inceleniyor', 'info'],
            'resolved' => ['Cozuldu', 'success'],
            'rejected' => ['Reddedildi', 'danger'],
        ];
        $reasonLabels = function_exists('topicReportReasonLabels') ? topicReportReasonLabels() : [];
        $statusMeta = $statusLabels[$status] ?? ['Acik', 'warning'];

        return [
            'title' => (string) ($row['topic_title'] ?? $row['title'] ?? 'Silinmis konu'),
            'url' => function_exists('topicUrl') ? topicUrl((string) ($row['topic_slug'] ?? ''), (int) ($row['topic_id'] ?? 0)) : '#',
            'category' => (string) ($row['category'] ?? 'Genel'),
            'date' => self::formatDateTime((string) ($row['created_at'] ?? 'now')),
            'reason' => (string) ($reasonLabels[$row['reason'] ?? ''] ?? $row['reason'] ?? ''),
            'details' => (string) ($row['details'] ?? ''),
            'admin_note' => (string) ($row['admin_note'] ?? ''),
            'status_label' => $statusMeta[0],
            'status_class' => 'profile-report-status profile-report-status-' . $statusMeta[1],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profileActivityItem(array $row): array
    {
        $action = (string) ($row['action'] ?? '');
        $date = (string) ($row['created_at'] ?? 'now');
        return [
            'title' => function_exists('profileActivitySentence') ? profileActivitySentence($row) : $action,
            'badge' => function_exists('profileActivityTitle') ? profileActivityTitle($row) : $action,
            'detail' => function_exists('profileActivityDisplayDetail') ? profileActivityDisplayDetail($row) : '',
            'url' => function_exists('profileActivityTargetUrl') ? profileActivityTargetUrl($row) : '',
            'tone' => self::profileActivityTone($action),
            'date' => self::formatDate($date),
            'time' => date('H:i', strtotime($date) ?: time()),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function profileSecurityEventItem(array $row): array
    {
        $action = (string) ($row['action'] ?? '');
        return [
            'title' => function_exists('profileActivitySentence') ? profileActivitySentence($row) : $action,
            'detail' => function_exists('profileActivityIsLogin') && profileActivityIsLogin($row) && function_exists('profileActivityLoginDetail') ? profileActivityLoginDetail($row) : '',
            'url' => function_exists('profileActivityTargetUrl') ? profileActivityTargetUrl($row) : '',
            'tone' => str_contains($action, 'login') ? 'is-success' : 'is-warning',
            'date' => date('d.m H:i', strtotime((string) ($row['created_at'] ?? 'now')) ?: time()),
        ];
    }

    private static function profileActivityTone(string $action): string
    {
        $tones = [
            'login' => 'is-success',
            'create' => 'is-primary',
            'update' => 'is-warning',
            'delete' => 'is-danger',
            'comment' => 'is-info',
            'register' => 'is-primary',
            'password' => 'is-warning',
            'avatar' => 'is-danger',
            'profile' => 'is-info',
        ];
        foreach ($tones as $needle => $class) {
            if (str_contains($action, $needle)) {
                return $class;
            }
        }

        return 'is-muted';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private static function profileRestrictionItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $type = (string) ($row['restriction_type'] ?? '');
            $items[] = [
                'label' => function_exists('profileRestrictionLabel') ? profileRestrictionLabel($type) : $type,
                'expires_label' => !empty($row['expires_at']) ? self::formatDateTime((string) $row['expires_at']) . ' tarihine kadar' : 'Suresiz',
                'reason' => (string) ($row['reason'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $user
     * @return array<int, array<string, string>>
     */
    private static function profilePrivacyOptions(array $pageVars, array $user): array
    {
        $source = self::normalizeList(self::arrayValue($pageVars, 'profile_privacy_options'));
        if ($source === []) {
            $source = [
                ['field' => 'public_profile', 'id' => 'pp_profile', 'icon' => 'bi-person-badge', 'title' => 'Profil sayfam yayinda olsun', 'description' => 'Kapaliyken public profiliniz ziyaretcilere gosterilmez.', 'default' => 1],
                ['field' => 'public_show_topics', 'id' => 'pp_topics', 'icon' => 'bi-collection', 'title' => 'Konularim gorunsun', 'description' => 'Public profilde yayinlanmis icerikleriniz listelenir.', 'default' => 1],
                ['field' => 'public_show_comments', 'id' => 'pp_comments', 'icon' => 'bi-chat-square-text', 'title' => 'Yorum sayim gorunsun', 'description' => 'Profil ozetinde yorum aktiviteniz paylasilir.', 'default' => 0],
                ['field' => 'public_show_socials', 'id' => 'pp_socials', 'icon' => 'bi-link-45deg', 'title' => 'Sosyal baglantilarim gorunsun', 'description' => 'Web sitesi ve sosyal hesaplariniz public profilde gorunur.', 'default' => 1],
            ];
        }

        $items = [];
        foreach ($source as $option) {
            $field = (string) ($option['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $items[] = [
                'field' => $field,
                'id' => (string) ($option['id'] ?? $field),
                'icon' => (string) ($option['icon'] ?? 'bi-eye'),
                'title' => (string) ($option['title'] ?? $field),
                'description' => (string) ($option['description'] ?? ''),
                'checked' => ((string) ($option['checked'] ?? '') === 'checked' || (int) ($user[$field] ?? $option['default'] ?? 0) === 1) ? 'checked' : '',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $pageVars
     */
    private static function callProfilePagination(array $pageVars, string $tab): string
    {
        $callback = $pageVars['renderProfilePagination'] ?? null;
        if ($callback instanceof Closure || is_callable($callback)) {
            try {
                return (string) $callback($tab);
            } catch (Throwable) {
                return '';
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $pageVars
     * @param array<string, mixed> $user
     * @return array<int, array{items: array<int, array<string, mixed>>}>
     */
    private static function profilePublicPaginationGroups(array $pageVars, array $user): array
    {
        $items = [];
        if (!empty($pageVars['profile_show_topics'])) {
            $total = (int) ($pageVars['publishedTopicCount'] ?? 0);
            $page = (int) ($pageVars['profileCurrentPage'] ?? 1);
            $perPage = (int) ($pageVars['topicsPerPage'] ?? 12);
            $baseUrl = function_exists('publicProfileUrl') ? publicProfileUrl($user) : '';
            if ($baseUrl !== '') {
                $items = self::paginationItems($total, $page, $perPage, $baseUrl, 'page');
            }
        }

        return $items !== [] ? [['items' => $items]] : [];
    }

    /**
     * @param array<string, mixed> $pageVars
     * @return array<int, array{items: array<int, array<string, mixed>>}>
     */
    private static function profilePrivatePaginationGroups(array $pageVars, string $baseUri, string $tab): array
    {
        $totals = self::arrayValue($pageVars, 'profileTotals');
        $pages = self::arrayValue($pageVars, 'profilePages');
        $params = self::arrayValue($pageVars, 'profilePageParams');
        $total = (int) ($totals[$tab] ?? 0);
        $page = (int) ($pages[$tab] ?? 1);
        $perPage = (int) ($pageVars['profilePerPage'] ?? 10);
        $pageParam = (string) ($params[$tab] ?? ($tab . '_page'));
        if ($total <= 0 || $perPage <= 0) {
            return [];
        }

        $query = ['tab' => $tab];
        if ($tab === 'topics') {
            $topicStatus = (string) ($pageVars['safeTopicStatusFilter'] ?? $pageVars['topicStatusFilter'] ?? 'all');
            if ($topicStatus !== 'all') {
                $query['topic_status'] = $topicStatus;
            }
        }
        if ($tab === 'activity') {
            $activityFilter = (string) ($pageVars['safeActivityFilter'] ?? $pageVars['activityFilter'] ?? 'all');
            if ($activityFilter !== 'all') {
                $query['activity_type'] = $activityFilter;
            }
        }

        $baseUrl = rtrim($baseUri, '/') . '/profile.php?' . http_build_query($query);
        $items = self::paginationItems($total, $page, $perPage, $baseUrl, $pageParam, 7);

        return $items !== [] ? [['items' => $items]] : [];
    }

    private static function initial(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'U';
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
    }
}


