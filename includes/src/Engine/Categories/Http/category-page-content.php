<?php



declare(strict_types=1);



$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
$cacheEnabled = ($settings['cache_enabled'] ?? '1') === '1';
if ($cacheEnabled && empty($_SESSION['_auth_user_id'])) {
    $ttl = (int)($settings['cache_ttl'] ?? 3600);
    header("Cache-Control: public, max-age={$ttl}, must-revalidate");
    header('Vary: Accept-Encoding, Cookie');
} else {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}


require_once $projectRoot . '/includes/src/Engine/Seo/Legacy/helpers.php';



// Slug tabanlÄ± URL desteÄŸi (eski name parametresi de desteklenir)

$categorySlug = trim($_GET['slug'] ?? '');

$categoryParentSlug = trim($_GET['parent'] ?? '');

$categoryName = trim($_GET['name'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));

$settingsGlobal = function_exists("getAdminSettings") && isset($pdo) ? getAdminSettings($pdo) : [];

$perPage = (int)($settingsGlobal['items_per_page'] ?? CATEGORY_TOPICS_PER_PAGE) ?: CATEGORY_TOPICS_PER_PAGE;



$items = [];

$total = 0;

$categoryExists = false;
$categoryId = 0;
$categoryParentName = '';
$categoryActualParentSlug = '';
$categorySeoTitle = '';
$categorySeoDescription = '';
$categoryDescription = '';

// Slug varsa slug ile, yoksa name ile ara

if ($categorySlug !== '') {

    // DB'den kategori bilgilerini (parent dahil) Ã§ek

    if ($pdo) {

        try {
            $stmt = $pdo->prepare("SELECT cat.id, cat.name, cat.parent_id, cat.description, cat.seo_title, cat.seo_description, parent.slug AS parent_slug, parent.name AS parent_name, parent.seo_title AS parent_seo_title, parent.seo_description AS parent_seo_description
                                   FROM categories cat
                                   LEFT JOIN categories parent ON parent.id = cat.parent_id
                                   WHERE cat.slug = :slug AND cat.status = 'active' AND cat.deleted_at IS NULL
                                   LIMIT 1");
            $stmt->execute(['slug' => $categorySlug]);
            $catRow = $stmt->fetch();

            if ($catRow) {

                $categoryExists = true;
                $categoryId = (int) $catRow['id'];
                $categoryName = (string) $catRow['name'];
                $categorySeoTitle = trim((string) ($catRow['seo_title'] ?? ''));
                $categorySeoDescription = trim((string) ($catRow['seo_description'] ?? ''));
                $categoryDescription = trim((string) ($catRow['description'] ?? ''));
                $categoryActualParentSlug = (string) ($catRow['parent_slug'] ?? '');
                $categoryParentName = (string) ($catRow['parent_name'] ?? '');
            }
        } catch (Throwable $e) {
            appLogException($e, ['source' => 'category.php categoryLookup', 'slug' => $categorySlug]);

        }

    }



    $result = getTopicsByCategorySlug($pdo, $categorySlug, $page, $perPage);

    $items = $result['items'];

    $total = $result['total'];



    if ($categoryName === '') {

        $categoryName = ucfirst($categorySlug);

    }

} elseif ($categoryName !== '') {

    // Eski name parametresi desteÄŸi - slug'a yÃ¶nlendir

    if ($pdo) {

        try {

            $stmt = $pdo->prepare("SELECT slug FROM categories WHERE name = :name AND status = 'active' AND deleted_at IS NULL");

            $stmt->execute(['name' => $categoryName]);

            $row = $stmt->fetch();

            

        } catch (Throwable $e) {

            appLogException($e, ['source' => 'category.php nameLookup', 'name' => $categoryName]);

        }

    }

    

    // Fallback yok: name ile eÅŸleÅŸen kategori DB'de bulunamadÄ±.

    $items = [];

    $total = 0;

}






$publicCategories = getPublicCategories($pdo);
$categoryCount = count($publicCategories);
$allCategoryTopicCount = 0;
foreach ($publicCategories as $publicCategory) {
    $allCategoryTopicCount += (int) ($publicCategory['topic_count'] ?? 0);
}
$isSpecificCategory = $categorySlug !== '' || $categoryName !== '';
$paginationBase = $categorySlug !== '' ? categoryUrl($categorySlug, $categoryActualParentSlug) : categoryListUrl();
$canonicalUrl = $paginationBase . ($page > 1 ? '?page=' . $page : '');

if ($categorySlug !== '' || $categoryName !== '') {
    $pageTitle = $categorySeoTitle !== ''
        ? $categorySeoTitle
        : 'Kategori: ' . ($categoryParentName !== '' ? $categoryParentName . ' â€º ' : '') . $categoryName;
} else {
    $pageTitle = 'TÃ¼m Kategoriler';
}
$heroTitle = $isSpecificCategory ? $categoryName : 'TÃ¼m Kategoriler';
$heroDescription = $isSpecificCategory
    ? 'Bu kategorideki tÃ¼m yazÄ±lar, eklentiler ve dosyalar aÅŸaÄŸÄ±da listelenmiÅŸtir.'
    : 'TÃ¼m kategorileri tek ekranda inceleyin ve ilginizi Ã§eken iÃ§erik alanÄ±na hÄ±zlÄ±ca geÃ§in.';

$metaDescription = $isSpecificCategory
    ? ($categorySeoDescription !== ''
        ? $categorySeoDescription
        : ($categoryDescription !== ''
            ? $categoryDescription
            : $categoryName . ' kategorisindeki gÃ¼ncel modlarÄ±, eklentileri ve topluluk paylaÅŸÄ±mlarÄ±nÄ± inceleyin.'))
    : 'TÃ¼m mod kategorilerini, eklentileri ve topluluk iÃ§eriklerini tek sayfada keÅŸfedin.';

// SEO Integration
$settings = getAdminSettings($pdo);

// Meta tags
$seoMetaTags = seoGenerateCategoryMeta([
    'name' => $categoryName,
    'slug' => $categorySlug,
    'parent_name' => $categoryParentName,
    'parent_slug' => $categoryActualParentSlug,
    'topic_count' => $total,
    'seo_title' => $categorySeoTitle,
    'seo_description' => $categorySeoDescription,
    'description' => $categoryDescription,
], $settings, $canonicalUrl, !($page > 1 || $total > $perPage));


// Structured data

$seoStructuredData = '';

if ($categoryExists) {

    $seoStructuredData = seoGetCategoryStructuredData([

        'id' => $categoryId,

        'name' => $categoryName,

        'slug' => $categorySlug,

        'parent_name' => $categoryParentName,

        'parent_slug' => $categoryActualParentSlug

    ], $items, $settings);

}

// Pagination tags
$seoPaginationTags = '';
if ($page > 1 || $total > $perPage) {
    $totalPages = (int) ceil($total / $perPage);

    $seoPaginationTags = seoGetPaginationTags($page, $totalPages, $paginationBase, $settings);

}



require_once $projectRoot . '/includes/public-header.php';

if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer() && isset($themeManager) && $themeManager instanceof ThemeManager) {
    if (empty($items) && ($categorySlug !== '' || $categoryName !== '')) {
        $emptyState = ['title' => 'Bu kategoride henuz icerik bulunmuyor.', 'description' => 'Buraya ilk icerigi sen ekleyebilirsin.', 'icon' => 'bi-box-seam'];
        $itemsHtml = '';
        $paginationHtml = '';
        $paginationData = ['items' => [], 'has_items' => false];
        $topicCards = [];
    } elseif (!empty($items)) {
        $emptyState = [];
        $topicCards = PublicThemeRenderer::topicCardListVars($items, [
            'base_uri' => $baseUri,
            'pdo' => $pdo,
            'settings' => $settings,
        ]);
        $itemsHtml = '';
        $paginationData = PublicThemeRenderer::paginationVars((int) $total, (int) $page, (int) $perPage, $paginationBase);
        $paginationHtml = '';
    } else {
        $emptyState = [];
        $topicCards = [];
        $paginationData = ['items' => [], 'has_items' => false];
        $categoryTree = function_exists('getPublicCategoriesTree') ? getPublicCategoriesTree($pdo) : [];
        if (empty($categoryTree)) {
            $categoryTree = array_values(array_filter($publicCategories, 'is_array'));
        }

        $truncateText = static function (string $value, int $length): string {
            $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
            if ($value === '') {
                return '';
            }

            return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '...' : $value;
        };

        $categoryNodeTotal = static function (array $node) use (&$categoryNodeTotal): int {
            $totalCount = (int) ($node['topic_count'] ?? 0);
            foreach (($node['children'] ?? []) as $childNode) {
                if (is_array($childNode)) {
                    $totalCount += $categoryNodeTotal($childNode);
                }
            }

            return $totalCount;
        };

        $categoryVisualValue = static function (array $node, array $keys): string {
            foreach ($keys as $key) {
                $value = trim((string) ($node[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $categoryStyleAttr = static function (array $node) use ($categoryVisualValue, $baseUri): string {
            $styleParts = [];
            $cover = $categoryVisualValue($node, [
                'cover_image',
                'cover_url',
                'background_image',
                'background_url',
                'image',
                'image_url',
                'thumbnail',
                'thumbnail_url',
            ]);
            if ($cover !== '') {
                if (!preg_match('~^(https?:)?//~i', $cover) && !str_starts_with($cover, '/')) {
                    $cover = rtrim((string) $baseUri, '/') . '/' . ltrim($cover, '/');
                }
                $coverValue = function_exists('uiCssUrlValue') ? uiCssUrlValue($cover, (string) $baseUri) : '';
                if ($coverValue !== '') {
                    $styleParts['--ui-theme-category-bg'] = $coverValue;
                }
            }

            $accent = $categoryVisualValue($node, [
                'accent_color',
                'theme_color',
                'color',
            ]);
            if ($accent !== '' && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $accent)) {
                $accentValue = function_exists('uiCssColorValue') ? uiCssColorValue($accent) : '';
                if ($accentValue !== '') {
                    $styleParts['--ui-theme-category-accent'] = $accentValue;
                }
            }

            return function_exists('uiStyleAttribute') ? uiStyleAttribute($styleParts) : '';
        };

        $categoryFamilies = [];
        foreach ($categoryTree as $parent) {
            if (!is_array($parent)) {
                continue;
            }

            $parentName = trim((string) ($parent['name'] ?? ''));
            $parentSlug = trim((string) ($parent['slug'] ?? ''));
            $parentDescription = $truncateText((string) ($parent['description'] ?? $parent['seo_description'] ?? ''), 126);
            $parentChildren = array_values(array_filter(($parent['children'] ?? []), 'is_array'));
            $parentCount = $categoryNodeTotal($parent);
            $parentUrl = categoryUrlForRow($pdo, $parent);
            $children = [];

            if (!empty($parentChildren)) {
                foreach ($parentChildren as $child) {
                    $childName = trim((string) ($child['name'] ?? ''));
                    $childUrlRow = $child;
                    $childUrlRow['parent_slug'] = $parentSlug;
                    $childUrl = categoryUrlForRow($pdo, $childUrlRow);
                    $childCount = $categoryNodeTotal($child);
                    $children[] = [
                        'name' => $childName !== '' ? $childName : 'Alt kategori',
                        'url' => $childUrl,
                        'total' => number_format($childCount, 0, ',', '.'),
                    ];
                }
            }

            $categoryFamilies[] = [
                'name' => $parentName !== '' ? $parentName : 'Kategori',
                'url' => $parentUrl,
                'description' => $parentDescription !== '' ? $parentDescription : 'Bu kategori altindaki icerikleri ve alt basliklari incele.',
                'child_count' => number_format(count($parentChildren), 0, ',', '.'),
                'total' => number_format($parentCount, 0, ',', '.'),
                'children' => $children,
            ];
        }

        if ($categoryFamilies === []) {
            $emptyState = ['title' => 'Kategori bulunamadi.', 'description' => 'Aktif kategori eklendiginde burada gorunecek.', 'icon' => 'bi-grid-3x3-gap'];
            $itemsHtml = '';
        } else {
            $itemsHtml = '';
        }
        $paginationHtml = '';
    }

    $categoryStats = [
        ['value' => number_format($categoryCount, 0, ',', '.'), 'label' => 'Kategori'],
        ['value' => number_format($allCategoryTopicCount, 0, ',', '.'), 'label' => 'Toplam icerik'],
    ];
    if ($isSpecificCategory) {
        $categoryStats[] = ['value' => number_format($total, 0, ',', '.'), 'label' => 'Bu kategori'];
    }

    echo $themeManager->render('category', [
        'page_title' => $pageTitle,
        'page_description' => $heroDescription,
        'category' => [
            'name' => $heroTitle,
            'description' => $heroDescription,
            'stats' => $categoryStats,
        ],
        'category_families' => $categoryFamilies ?? [],
        'topics' => $topicCards,
        'empty_state' => $emptyState,
        'pagination_items' => $paginationData['items'],
    ]);

    require_once $projectRoot . '/includes/public-footer.php';
    exit;
}
?>

<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container public-container public-breadcrumb breadcrumb-container category-breadcrumb-container ui-container">
    <nav class="breadcrumb" aria-label="Sayfa yolu">
        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <?php if ($isSpecificCategory): ?>

            <a href="<?= categoryListUrl() ?>">Kategoriler</a>

            <i class="bi bi-chevron-right"></i>

            <?php if ($categoryParentName !== ''): ?>

                <a href="<?= categoryUrl($categoryActualParentSlug) ?>"><?= htmlspecialchars($categoryParentName) ?></a>

                <i class="bi bi-chevron-right"></i>

            <?php endif; ?>

            <span><?= htmlspecialchars($categoryName) ?></span>

        <?php else: ?>

            <span>TÃ¼m Kategoriler</span>

        <?php endif; ?>
    </nav>
</div>
<?php endif; ?>

<div class="container public-container public-layout main-layout-container category-page-container category-page-layout topic-share-layout ui-container ui-section">
    <div class="category-header category-hero-panel category-hero-panel--full ui-panel__head ui-panel" data-contract='class="category-header ui-panel__head"'>
        <div class="category-hero-copy">
            <span class="topic-eyebrow">Kategori</span>
            <h1><?= htmlspecialchars($heroTitle) ?></h1>
            <p><?= htmlspecialchars($heroDescription) ?></p>
        </div>
        <div class="category-hero-stats" aria-label="Kategori Ã¶zeti">
            <span><strong><?= number_format($categoryCount) ?></strong><small>Kategori</small></span>
            <span><strong><?= number_format($allCategoryTopicCount) ?></strong><small>Toplam iÃ§erik</small></span>
            <?php if ($isSpecificCategory): ?>
                <span><strong><?= number_format($total) ?></strong><small>Bu kategori</small></span>
            <?php endif; ?>
        </div>
    </div>
    <section class="public-content ui-section" aria-label="Kategori iÃ§erikleri">

        <?php if (empty($items) && ($categorySlug !== '' || $categoryName !== '')): ?>
            <div class="topic-grid topic-grid--list ui-grid" data-contract='class="topic-grid ui-grid"' data-topic-list-container>
                <?= renderEmptyState('Bu kategoride henÃ¼z iÃ§erik bulunmuyor.', 'Buraya ilk iÃ§eriÄŸi sen ekleyebilirsin.', 'bi-box-seam') ?>
            </div>
        <?php elseif (!empty($items)): ?>
            <div class="topic-grid topic-grid--list ui-grid" data-contract='class="topic-grid ui-grid"'>

                <?php foreach ($items as $item): ?>

                    <?php include $projectRoot . '/includes/partials/topic-card.php'; ?>

                <?php endforeach; ?>

            </div>



            <?php

                $paginationBase = $categorySlug !== '' ? categoryUrl($categorySlug, $categoryActualParentSlug) : categoryListUrl();

                $pagination = renderPagination($total, $page, $perPage, $paginationBase);

            ?>

        <?php else: ?>

            <!-- TÃ¼m category listesi -->

            <div class="topic-grid topic-grid--list category-overview topic-all-categories-grid ui-grid" data-contract='class="topic-grid ui-grid"'>

                <?php foreach ($publicCategories as $cat): ?>

                    <a href="<?= categoryUrlForRow($pdo, $cat) ?>" class="feed-card category-overview-card topic-category-card ui-card">

                        <span class="category-card-icon ui-card"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>

                        <span class="category-card-body ui-card ui-panel__body">

                            <strong><?= htmlspecialchars((string)$cat['name']) ?></strong>

                            <small><?= (int)($cat['topic_count'] ?? 0) ?> iÃ§erik</small>

                        </span>

                        <span class="category-card-action ui-card" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <nav class="pagination" aria-label="Kategori sayfalama">

            <?= $pagination ?? '' ?>

        </nav>

    </section>



    <?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
        <?php
        // Dinamik sidebar render - kategori context'i
        echo renderSidebar($pdo, 'category', [
            'category_slug' => $categorySlug,
            'category_id' => $categoryId
        ]);
        ?>
    <?php endif; ?>
</div>


<?php require_once $projectRoot . '/includes/public-footer.php'; ?>





