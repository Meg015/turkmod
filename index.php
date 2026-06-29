<?php

declare(strict_types=1);
// Cache header'ı init.php'den önce ayarla
$GLOBALS['_cache_control_set'] = true;

require_once __DIR__ . "/includes/init.php";

$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
$usesThemeRenderer = function_exists('usesPublicThemeRenderer')
    && usesPublicThemeRenderer()
    && isset($themeManager)
    && $themeManager instanceof ThemeManager;

$pageTitle = "Ana Sayfa";
$pageCssFiles = ["assets/css/home-categories.css"];
if ($usesThemeRenderer) {
    // Turkmod theme bundle already contains home category styles.
    $pageCssFiles = [];
}
$homeUploadUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('upload_topic')
    : rtrim((string) ($baseUri ?? ''), '/') . '/upload-topic.php';
$homeRegisterUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('register')
    : rtrim((string) ($baseUri ?? ''), '/') . '/kayit';

// Arama, filtreleme, sıralama ve sayfalama
$search = function_exists("sanitizeSearchQuery")
    ? sanitizeSearchQuery($_GET["q"] ?? "")
    : trim($_GET["q"] ?? "");
$requestedSort = (string) ($_GET["sort"] ?? "newest");
$sort = in_array(
    $requestedSort,
    ["newest", "popular", "downloads", "rating", "comments"],
    true,
)
    ? $requestedSort
    : "newest";
$categoryFilter = function_exists("validateSlug")
    ? validateSlug($_GET["category"] ?? "") ?? ""
    : "";
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = (int)($settings["items_per_page"] ?? TOPICS_PER_PAGE) ?: TOPICS_PER_PAGE;
$isDefaultHomeRequest = ($page === 1 && $search === "" && $sort === "newest" && $categoryFilter === "");
$cacheEnabled = ($settings['cache_enabled'] ?? '1') === '1';
$isAnonymousVisitor = empty($_SESSION['_auth_user_id']);

if ($cacheEnabled && $isAnonymousVisitor && $isDefaultHomeRequest) {
    $ttl = max(60, (int) ($settings['cache_ttl'] ?? 300));
    $staleWhileRevalidate = max($ttl, min(86400, $ttl * 12));
    header("Cache-Control: public, max-age={$ttl}, stale-while-revalidate={$staleWhileRevalidate}");
    header('Vary: Accept-Encoding, Cookie');
} else {
    header("Cache-Control: private, no-cache, max-age=0, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

if ($search !== "") {
    $searchRateLimit = (int)($settings['search_rate_limit'] ?? 10) ?: 10;
    $searchRateWindow = max(1, (int)($settings['search_rate_window'] ?? 1));
    $rateLimitKey = 'search_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rateLimitKey, $searchRateLimit, $searchRateWindow)) {
        logRateLimitExceeded($pdo, '/index.php', 'search_attempts');
        $retryAfter = function_exists('getRateLimitRemainingSeconds')
            ? getRateLimitRemainingSeconds($rateLimitKey, $searchRateWindow)
            : 60;
        http_response_code(429);
        header('Retry-After: ' . max(1, $retryAfter));
        $pageTitle = 'Cok Fazla Istek';
        include __DIR__ . '/includes/public-header.php';
        echo '<main class="container py-5 ui-container">' . renderEmptyState('Çok fazla arama yaptınız', 'Lütfen biraz bekleyip tekrar deneyin.', 'bi-hourglass-split') . '</main>';
        include __DIR__ . '/includes/public-footer.php';
        exit;
    }
    incrementRateLimit($rateLimitKey, $searchRateWindow);
}

$cache = null;
if (class_exists(\App\Core\Bootstrap\Boot::class)) {
    try {
        $cache = \App\Core\Bootstrap\Boot::container()->get(\App\Core\Cache\Cache::class);
    } catch (Throwable $e) {
        // Fallback to null
    }
}

$result = null;
$isDefaultHome = $isDefaultHomeRequest;
if ($isDefaultHome && $cache) {
    $result = $cache->get('homepage_default_topics');
}
if (!is_array($result)) {
    $result = getTopics($pdo, $page, $perPage, $search, $sort, $categoryFilter);
    if ($isDefaultHome && $cache && is_array($result)) {
        $cache->set('homepage_default_topics', $result, 300);
    }
}
$items = $result["items"];
$total = $result["total"];

// Popüler modlar (indirme sayısına göre) - N+1 query önlendi, görseller tek sorguda
$sidebarItems = null;
if ($cache) {
    $sidebarItems = $cache->get('homepage_sidebar_items');
}
if (!is_array($sidebarItems)) {
    $sidebarItems = [];
    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                "SELECT t.id, t.title, t.slug, t.download_count, pm.path as image_path
                 FROM topics t
                 LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                 WHERE t.status = 'published' AND t.deleted_at IS NULL
                 ORDER BY t.download_count DESC
                 LIMIT ?"
            );
            $stmt->execute([SIDEBAR_POPULAR_LIMIT]);
            $sidebarItems = $stmt->fetchAll() ?: [];
            if ($cache && !empty($sidebarItems)) {
                $cache->set('homepage_sidebar_items', $sidebarItems, 300);
            }
        } catch (Throwable $e) {
            appLogException($e, ["source" => "index.php sidebarItems"]);
        }
    }
}

// Kategoriler (public-header.php'den önce lazım)
$publicCategories = [];
$publicCategoriesTree = [];
$publicCategoriesBundle = $cache ? $cache->get('homepage_public_categories_bundle') : null;
if (
    is_array($publicCategoriesBundle)
    && isset($publicCategoriesBundle['categories'], $publicCategoriesBundle['tree'])
    && is_array($publicCategoriesBundle['categories'])
    && is_array($publicCategoriesBundle['tree'])
) {
    $publicCategories = $publicCategoriesBundle['categories'];
    $publicCategoriesTree = $publicCategoriesBundle['tree'];
} else {
    $publicCategories = getPublicCategories($pdo);
    $publicCategoriesTree = function_exists("getPublicCategoriesTree")
        ? getPublicCategoriesTree($pdo)
        : [];
    if ($cache) {
        $cache->set('homepage_public_categories_bundle', [
            'categories' => $publicCategories,
            'tree' => $publicCategoriesTree,
        ], 600);
    }
}

// Kategori sayılarını ve site istatistiklerini tek sorguda çek (N+1 query önleme + sorgu birleştirme)
$categoryCounts = [];
$siteStats = [
    "mods" => $total,
    "kategoriler" => count($publicCategories),
    "downloads" => 0,
    "comments" => 0,
];

$statsCache = null;
if ($cache) {
    $statsCache = $cache->get('homepage_site_stats_category_counts');
}

if (is_array($statsCache) && isset($statsCache['categoryCounts'], $statsCache['siteStats'])) {
    $categoryCounts = $statsCache['categoryCounts'];
    $cachedStats = $statsCache['siteStats'];
    $siteStats['downloads'] = $cachedStats['downloads'] ?? 0;
    $siteStats['comments'] = $cachedStats['comments'] ?? 0;
    $siteStats['mods'] = $total;
} elseif (!$usesThemeRenderer) {
    if ($pdo) {
        try {
            // Tek sorguda hem kategori sayıları hem de toplam istatistikler
            $stmt = $pdo->query(
                "SELECT
                    category_id,
                    COUNT(*) as category_count,
                    COALESCE(SUM(download_count), 0) as category_downloads
                 FROM topics
                 WHERE status = 'published' AND deleted_at IS NULL
                 GROUP BY category_id WITH ROLLUP"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            $totalDownloads = 0;
            foreach ($rows as $row) {
                if ($row['category_id'] === null) {
                    // ROLLUP satırı - toplam değerler
                    $totalDownloads = (int) $row['category_downloads'];
                } else {
                    $categoryCounts[(int) $row['category_id']] = (int) $row['category_count'];
                }
            }
            $siteStats["downloads"] = $totalDownloads;
            
            // Yorum sayısı için ayrı sorgu (comments tablosu farklı)
            $commentStmt = $pdo->query("SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL AND status = 'approved'");
            $siteStats["comments"] = (int) ($commentStmt ? $commentStmt->fetchColumn() : 0);

            if ($cache) {
                $cache->set('homepage_site_stats_category_counts', [
                    'categoryCounts' => $categoryCounts,
                    'siteStats' => [
                        'downloads' => $siteStats['downloads'],
                        'comments' => $siteStats['comments'],
                    ],
                ], 300);
            }
        } catch (Throwable $e) {
            appLogException($e, ["source" => "index.php categoryCounts+siteStats"]);
        }
    }
}

// Son yorumlar
$recentComments = null;
if ($cache) {
    $recentComments = $cache->get('homepage_recent_comments');
}
if (!is_array($recentComments)) {
    $recentComments = [];
    if ($pdo) {
        try {
            $stmt = $pdo->query(
                "SELECT c.body AS content, c.created_at, u.name AS username, u.avatar AS user_avatar, c.topic_id, t.slug AS topic_slug
                 FROM comments c
                 LEFT JOIN users u ON u.id = c.user_id
                 INNER JOIN topics t ON t.id = c.topic_id
                 WHERE c.status = 'approved' AND c.deleted_at IS NULL
                   AND t.status = 'published' AND t.deleted_at IS NULL
                 ORDER BY c.created_at DESC
                 LIMIT 5"
            );
            $recentComments = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            if ($cache && is_array($recentComments)) {
                $cache->set('homepage_recent_comments', $recentComments, 300);
            }
        } catch (Throwable $e) {
            appLogException($e, ["source" => "index.php recentComments"]);
        }
    }
}

if ($search !== "") {
    $pageTitle = "Arama: " . $search;
}

$sortInsightMessages = [
    "newest" => "Yeni içerikler yayın tarihine göre en güncelden eskiye sıralanır.",
    "popular" => "Popüler sıralama görüntülenme ve indirme ilgisine göre listelenir.",
    "downloads" => "Trend içerikler en çok indirilen modlara göre öne çıkar.",
    "rating" => "En iyi içerikler puan ortalaması ve puan sayısına göre sıralanır.",
    "comments" => "En çok konuşulan içerikler yorum sayısına göre sıralanır.",
];
$sortInsight = $sortInsightMessages[$sort] ?? $sortInsightMessages["newest"];

require_once __DIR__ . "/includes/public-header.php";

if ($usesThemeRenderer) {
    $tmSortUrl = static function (string $value) use ($baseUri, $search, $categoryFilter): string {
        $params = [];
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($value !== 'newest') {
            $params['sort'] = $value;
        }
        if ($categoryFilter !== '') {
            $params['category'] = $categoryFilter;
        }

        return rtrim($baseUri, '/') . '/index.php' . ($params !== [] ? '?' . http_build_query($params) : '');
    };

    $sortLabels = [
        'newest' => 'Yeni',
        'popular' => 'Populer',
        'downloads' => 'Trend',
        'rating' => 'En Iyi',
        'comments' => 'Yorumlar',
    ];
    $sortOptions = [];
    foreach ($sortLabels as $sortKey => $sortLabel) {
        $sortOptions[] = [
            'label' => $sortLabel,
            'url' => $tmSortUrl($sortKey),
            'active_class' => $sort === $sortKey ? ' active' : '',
        ];
    }
    $searchNotice = $search !== ''
        ? $search . ' icin ' . number_format((int) $total, 0, ',', '.') . ' sonuc bulundu.'
        : '';

    if (empty($items)) {
        $emptyState = $search !== ''
            ? ['title' => 'Sonuc Bulunamadi', 'description' => 'Farkli anahtar kelimelerle tekrar deneyebilirsiniz.', 'icon' => 'bi-search']
            : ['title' => 'Henuz Icerik Yok', 'description' => 'Henuz onaylanmis icerik yayinlanmadi.', 'icon' => 'bi-inbox'];
        $itemsHtml = '';
        $topicCards = [];
    } else {
        $emptyState = [];
        $topicCards = PublicThemeRenderer::topicCardListVars($items, [
            'base_uri' => $baseUri,
            'pdo' => $pdo,
            'settings' => $settings,
        ]);
        $itemsHtml = '';
    }

    $paginationHtml = '';
    $paginationData = ['items' => [], 'has_items' => false];
    if ($total > $perPage) {
        $paginationParams = [];
        if ($search !== '') {
            $paginationParams['q'] = $search;
        }
        if ($sort !== 'newest') {
            $paginationParams['sort'] = $sort;
        }
        if ($categoryFilter !== '') {
            $paginationParams['category'] = $categoryFilter;
        }
        $paginationBase = rtrim($baseUri, '/') . '/index.php' . ($paginationParams !== [] ? '?' . http_build_query($paginationParams) : '');
        $paginationData = PublicThemeRenderer::paginationVars((int) $total, (int) $page, (int) $perPage, $paginationBase);
        $paginationHtml = '';
    }

    $templateKey = $search !== '' ? 'search' : 'home';
    echo $themeManager->render($templateKey, [
        'page_title' => $pageTitle,
        'search_query' => $search,
        'result_count' => number_format((int) $total, 0, ',', '.'),
        'sort_insight' => $sortInsight,
        'sort_options' => $sortOptions,
        'search_notice' => $searchNotice,
        'topics' => $topicCards,
        'empty_state' => $emptyState,
        'pagination_items' => $paginationData['items'],
    ]);

    require_once __DIR__ . "/includes/public-footer.php";
    exit;
}
?>

<!-- Breadcrumb -->
<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container public-container public-breadcrumb breadcrumb-container ui-container">
    <nav class="breadcrumb">
        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <span>Modlar</span>
    </nav>
</div>
<?php endif; ?>

<!-- Main Layout -->
<div class="container public-container main-layout-container ui-container">
    <div class="public-layout layout ui-section">
        <h1 class="visually-hidden"><?= htmlspecialchars($pageTitle) ?></h1>
        <!-- Left Sidebar -->
        <aside class="sidebar sidebar-left">
            <!-- Kategoriler -->
            <div class="widget category-widget category-atlas-widget">
                <button class="widget-header widget-toggle category-atlas-header active ui-panel__head" data-ui-action="toggleWidget">
                    <span class="category-atlas-heading">
                        <strong>Kategoriler</strong>
                    </span>
                    <span class="category-atlas-total"><?= number_format((int) $total, 0, ',', '.') ?> konu</span>
                </button>
                <div class="widget-body category-atlas-body ui-panel__body">
                    <div class="category-tree category-atlas-list">
                        <a href="<?= rtrim($baseUri, '/') ?>/kategoriler" class="category-link category-link-all<?= $categoryFilter === ""
                            ? " active"
                            : "" ?>">
                            <span class="category-link-content ui-section">
                                <span class="category-icon-wrap"><i class="bi bi-grid category-icon ui-grid" aria-hidden="true"></i></span>
                                <span class="category-copy">
                            <span class="category-name">Tüm Kategoriler</span>
                                </span>
                            </span>
                            <span class="category-count"><?= number_format((int) $total, 0, ',', '.') ?></span>
                        </a>
                        <?php if (!empty($publicCategoriesTree)): ?>
                            <?php foreach ($publicCategoriesTree as $cat): ?>
                                <?php
                                $catName = htmlspecialchars($cat["name"] ?? "");
                                $catSlug = $cat["slug"] ?? "";
                                $catIcon = $cat["icon"] ?? "bi-folder2-open";
                                $hasChildren = !empty($cat["children"]);
                                $isActiveCategory = $categoryFilter === $catSlug;
                                $hasActiveChild = false;
                                if ($hasChildren) {
                                    foreach ($cat["children"] as $child) {
                                        if ($categoryFilter === ($child["slug"] ?? "")) {
                                            $hasActiveChild = true;
                                            break;
                                        }
                                    }
                                }
                                $isOpenCategory = $isActiveCategory || $hasActiveChild;
                                $childGroupId = "home-subcategories-" . (int) ($cat["id"] ?? 0);

                                // Kategori sayısını cache'den al (N+1 query önlendi)
                                $catCount = (int) ($categoryCounts[$cat["id"]] ?? 0);
                                // Alt kategorilerin sayılarını da ekle
                                if ($hasChildren) {
                                    foreach ($cat["children"] as $child) {
                                        $catCount += (int) ($categoryCounts[$child["id"]] ?? 0);
                                    }
                                }
                                ?>

                                <?php if ($hasChildren): ?>
                                    <div class="category-item<?= $isActiveCategory
                                        ? " active"
                                        : "" ?><?= $hasActiveChild
                                        ? " has-active-child"
                                        : "" ?><?= $isOpenCategory
                                        ? " open"
                                        : "" ?>">
                                        <button class="category-toggle" type="button" aria-expanded="<?= $isOpenCategory ? "true" : "false" ?>" aria-controls="<?= htmlspecialchars($childGroupId) ?>" aria-label="<?= $catName ?> alt kategorilerini ac veya kapat">
                                            <span class="category-link-content ui-section">
                                                <span class="category-chevron"><i class="bi bi-chevron-right chevron-icon"></i></span>
                                                <span class="category-icon-wrap"><i class="bi <?= htmlspecialchars(
                                                    $catIcon,
                                                ) ?> category-icon"></i></span>
                                                <span class="category-copy">
                                                    <span class="category-name"><?= $catName ?></span>
                                                </span>
                                            </span>
                                            <span class="category-count"><?= number_format($catCount, 0, ',', '.') ?></span>
                                        </button>
                                         <div id="<?= htmlspecialchars($childGroupId) ?>" class="subcategories">
                                            <?php foreach (
                                                $cat["children"]
                                                as $child
                                            ): ?>
                                                <?php
                                                $childName = htmlspecialchars(
                                                    $child["name"] ?? "",
                                                );
                                                $childSlug =
                                                    $child["slug"] ?? "";
                                                // Alt kategori sayısını cache'den al
                                                $childCount = (int) ($categoryCounts[$child["id"]] ?? 0);
                                                ?>
                                                <a class="subcategory-link<?= $categoryFilter === $childSlug
                                                    ? " active"
                                                    : "" ?>" href="<?= categoryUrl(
                                                    $childSlug,
                                                    $catSlug,
                                                ) ?>">
                                                    <span class="subcategory-link-content ui-section">
                                                        <span class="subcategory-name"><?= $childName ?></span>
                                                    </span>
                                                    <span class="subcategory-count"><?= number_format($childCount, 0, ',', '.') ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="category-item<?= $isActiveCategory
                                        ? " active"
                                        : "" ?>">
                                        <a href="<?= categoryUrl(
                                            $catSlug,
                                        ) ?>" class="category-link<?= $isActiveCategory
                                            ? " active"
                                            : "" ?>">
                                            <span class="category-link-content ui-section">
                                                <span class="category-icon-wrap"><i class="bi <?= htmlspecialchars(
                                                    $catIcon,
                                                ) ?> category-icon"></i></span>
                                                <span class="category-copy">
                                                    <span class="category-name"><?= $catName ?></span>
                                                </span>
                                            </span>
                                            <span class="category-count"><?= number_format($catCount, 0, ',', '.') ?></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php elseif (!empty($publicCategories)): ?>
                            <?php foreach (
                                array_slice($publicCategories, 0, 10)
                                as $cat
                            ): ?>
                                <?php
                                $catName = htmlspecialchars($cat["name"] ?? "");
                                $catSlug = $cat["slug"] ?? "";
                                // Kategori sayısını cache'den al
                                $catCount = (int) ($categoryCounts[$cat["id"]] ?? 0);
                                ?>
                                <div class="category-item<?= $categoryFilter === $catSlug
                                    ? " active"
                                    : "" ?>">
                                    <a href="<?= categoryUrl(
                                        $catSlug,
                                    ) ?>" class="category-link<?= $categoryFilter === $catSlug
                                        ? " active"
                                        : "" ?>">
                                        <span class="category-link-content ui-section">
                                            <span class="category-icon-wrap"><i class="bi bi-folder2-open category-icon"></i></span>
                                            <span class="category-copy">
                                                <span class="category-name"><?= $catName ?></span>
                                            </span>
                                        </span>
                                        <span class="category-count"><?= number_format($catCount, 0, ',', '.') ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sıralama -->
            <div class="widget">
                <div class="widget-header">
                    <h3><i class="bi bi-sort-down"></i> Sıralama</h3>
                </div>
                <div class="widget-body">
                    <select class="game-select" data-filter="sort" aria-label="Sıralama seçimi">
                        <option value="newest"<?= $sort === "newest"
                            ? " selected"
                            : "" ?>>En Yeni</option>
                        <option value="popular"<?= $sort === "popular"
                            ? " selected"
                            : "" ?>>En Popüler</option>
                        <option value="downloads"<?= $sort === "downloads"
                            ? " selected"
                            : "" ?>>En Çok İndirilen</option>
                        <option value="rating"<?= $sort === "rating"
                            ? " selected"
                            : "" ?>>En Yüksek Puan</option>
                        <option value="comments"<?= $sort === "comments"
                            ? " selected"
                            : "" ?>>En Çok Yorumlanan</option>
                    </select>
                    <p class="sort-helper-text"><?= htmlspecialchars($sortInsight) ?></p>
                </div>
            </div>

        </aside>

        <!-- Main Content -->
        <main class="content ui-section">
            <div class="filter-bar">
                <div class="filter-tabs">
                    <button class="<?= $sort === "newest"
                        ? "active"
                        : "" ?>" data-filter="sort" data-value="newest" type="button">Yeni</button>
                    <button class="<?= $sort === "popular"
                        ? "active"
                        : "" ?>" data-filter="sort" data-value="popular" type="button">Popüler</button>
                    <button class="<?= $sort === "downloads"
                        ? "active"
                        : "" ?>" data-filter="sort" data-value="downloads" type="button">Trend</button>
                    <button class="<?= $sort === "rating"
                        ? "active"
                        : "" ?>" data-filter="sort" data-value="rating" type="button">En İyi</button>
                </div>
                <select class="sort-select" data-filter="sort" aria-label="İçerik sıralama">
                    <option value="newest"<?= $sort === "newest"
                        ? " selected"
                        : "" ?>>En Yeni</option>
                    <option value="popular"<?= $sort === "popular"
                        ? " selected"
                        : "" ?>>En Popüler</option>
                    <option value="downloads"<?= $sort === "downloads"
                        ? " selected"
                        : "" ?>>En Çok İndirilen</option>
                    <option value="rating"<?= $sort === "rating"
                        ? " selected"
                        : "" ?>>En Yüksek Puan</option>
                    <option value="comments"<?= $sort === "comments"
                        ? " selected"
                        : "" ?>>En Çok Yorumlanan</option>
                </select>
            </div>
            <?php if ($search !== ""): ?>
                <div class="search-result-info">
                    <strong>"<?= htmlspecialchars(
                        $search,
                    ) ?>"</strong> için <?= $total ?> sonuç bulundu.
                </div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <?php if ($search !== ""): ?>
                    <div class="empty-state-container">
                        <i class="bi bi-search empty-state-icon"></i>
                        <h3 class="empty-state-title">Sonuç Bulunamadı</h3>
                        <p class="empty-state-text"><strong>"<?= htmlspecialchars(
                            $search,
                        ) ?>"</strong> aramasıyla eşleşen içerik bulunamadı.</p>
                        <p class="empty-state-text">Farklı anahtar kelimelerle tekrar deneyebilirsiniz.</p>
                        <div class="empty-state-actions">
                            <a href="<?= $baseUri ?>/index.php" class="empty-state-action"><i class="bi bi-arrow-counterclockwise"></i> Aramayı Sıfırla</a>
                            <a href="<?= categoryListUrl() ?>" class="empty-state-action is-secondary"><i class="bi bi-grid ui-grid"></i> Kategorilere Bak</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state-container">
                        <i class="bi bi-inbox empty-state-icon"></i>
                        <h3 class="empty-state-title">Henüz İçerik Yok</h3>
                        <p class="empty-state-text">Henüz onaylanmış içerik yayınlanmadı.</p>
                        <div class="empty-state-actions">
                            <a href="<?= htmlspecialchars($isLoggedIn ? $homeUploadUrl : $homeRegisterUrl, ENT_QUOTES, 'UTF-8') ?>" class="empty-state-action"><i class="bi bi-cloud-upload"></i> <?= $isLoggedIn ? 'İlk İçeriği Yükle' : 'Hesap Oluştur' ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php // Topic card author links use publicProfileUrl(). ?>
                <!-- Mod Grid -->
                <div class="topic-grid topic-grid--list ui-grid" data-contract='class="topic-grid ui-grid"' data-topic-list-container>
                    <?php foreach ($items as $item): ?>
                        <?php include __DIR__ .
                            "/includes/partials/topic-card.php"; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total > $perPage): ?>
                    <?php
                    $paginationParams = [];
                    if ($search !== "") {
                        $paginationParams["q"] = $search;
                    }
                    if ($sort !== "newest") {
                        $paginationParams["sort"] = $sort;
                    }
                    if ($categoryFilter !== "") {
                        $paginationParams["category"] = $categoryFilter;
                    }
                    $paginationBase = $baseUri . "/index.php" . ($paginationParams !== [] ? "?" . http_build_query($paginationParams) : "");
                    echo renderPagination($total, $page, $perPage, $paginationBase);
                    ?>
<?php endif; ?>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <aside class="sidebar sidebar-right">
            <div class="live-stats-widget">
            <!-- İstatistikler -->
            <div class="widget">
                <div class="widget-header">
                    <h3><i class="bi bi-graph-up"></i> Canlı İstatistikler</h3>
                </div>
                <div class="widget-body">
                    <div class="stats">
                        <div class="stat-item">
                            <span class="stat-icon stat-icon-mods"><i class="bi bi-files"></i></span>
                            <div>
                                <strong><?= number_format(
                                    $siteStats["mods"],
                                ) ?></strong>
                                <span>Toplam Mod</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon stat-icon-downloads"><i class="bi bi-download"></i></span>
                            <div>
                                <strong><?= number_format(
                                    $siteStats["downloads"],
                                ) ?></strong>
                                <span>İndirme</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon stat-icon-categories"><i class="bi bi-grid-3x3-gap"></i></span>
                            <div>
                                <strong><?= $siteStats[
                                    "kategoriler"
                                ] ?></strong>
                                <span>Kategori</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <!-- Son Yorumlar -->
            <?php if (!empty($recentComments)): ?>
            <div class="widget">
                <div class="widget-header">
                    <h3><i class="bi bi-chat-dots"></i> Son Yorumlar</h3>
                </div>
                <div class="widget-body">
                    <div class="comments-list">
                        <?php foreach ($recentComments as $comment): ?>
                            <?php
                            $commentUser = htmlspecialchars(
                                $comment["username"] ?? "Anonim",
                            );
                            $commentText = htmlspecialchars(
                                mb_substr($comment["content"] ?? "", 0, 80),
                            );
                            $commentTime = $comment["created_at"] ?? "";

                            // Zaman farkı hesapla
                            $timeAgo = "Bilinmiyor";
                            if ($commentTime) {
                                $diff = time() - strtotime($commentTime);
                                if ($diff < 3600) {
                                    $timeAgo =
                                        floor($diff / 60) . " dakika önce";
                                } elseif ($diff < 86400) {
                                    $timeAgo =
                                        floor($diff / 3600) . " saat önce";
                                } else {
                                    $timeAgo =
                                        floor($diff / 86400) . " gün önce";
                                }
                            }

                            $commentAvatarUrl = '';
                            if (function_exists('resolveAvatarUrl')) {
                                $commentAvatarUrl = resolveAvatarUrl($comment['user_avatar'] ?? '', $baseUri, true);
                            } else {
                                $commentAvatarUrl = function_exists('defaultAvatarUrl')
                                    ? defaultAvatarUrl($baseUri)
                                    : $baseUri . '/assets/images/noavatar-neon-helmet.svg';
                            }
                            $defaultAvatarFallback = function_exists('defaultAvatarUrl')
                                ? defaultAvatarUrl($baseUri)
                                : $baseUri . '/assets/images/noavatar-neon-helmet.svg';
                            ?>
                            <div class="comment-item">
                                <div class="comment-avatar">
                                    <img src="<?= htmlspecialchars($commentAvatarUrl) ?>" alt="<?= $commentUser ?>" width="32" height="32" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($defaultAvatarFallback) ?>">
                                </div>
                                <div class="comment-content ui-section">
                                    <strong><?= $commentUser ?></strong>
                                    <p><?= $commentText ?></p>
                                    <small><?= $timeAgo ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- En Popüler -->
            <?php if (!empty($sidebarItems)): ?>
            <div class="widget">
                <div class="widget-header">
                    <h3><i class="bi bi-fire"></i> En Popüler</h3>
                </div>
                <div class="widget-body">
                    <div class="popular-list">
                        <?php foreach (
                            array_slice($sidebarItems, 0, 3)
                            as $popItem
                        ): ?>
                            <?php
                            $popTitle = htmlspecialchars(
                                $popItem["title"] ?? "",
                            );
                            $popSlug = $popItem["slug"] ?? "";
                            $popDownloads = number_format(
                                $popItem["download_count"] ?? 0,
                            );
                            $popUrl = topicUrlForRow($popItem);
                            ?>
                            <a href="<?= htmlspecialchars($popUrl, ENT_QUOTES, 'UTF-8') ?>" class="popular-item">
                                <?php
                                // Görsel zaten ana sorguda çekildi (N+1 query önlendi)
                                $popImage = $popItem["image_path"] ?? null;
                                $popImageUrl = $popImage
                                    ? htmlspecialchars($baseUri . "/" . ltrim($popImage, "/"))
                                    : htmlspecialchars(asset_url("assets/portal-pack.svg", $baseUri));
                                ?>
                                <img src="<?= $popImageUrl ?>" alt="<?= $popTitle ?> görseli" width="50" height="50" loading="lazy">
                                <div>
                                    <strong><?= $popTitle ?></strong>
                                    <span><?= $popDownloads ?> indirme</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <?php if ($isLoggedIn): ?>
            <div class="widget cta-widget">
                <i class="bi bi-cloud-upload"></i>
                <h3>Modunu Paylaş</h3>
                <p>Kendi modunu yükle ve topluluğa katkıda bulun!</p>
                <button class="btn-cta" data-ui-href="<?= htmlspecialchars($homeUploadUrl, ENT_QUOTES, 'UTF-8') ?>">Hemen Yükle</button>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script src="<?= asset_url('assets/js/home-widgets.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . "/includes/public-footer.php"; ?>

