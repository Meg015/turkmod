<?php

declare(strict_types=1);

$leaderboardProjectRoot = dirname(__DIR__, 5);
$pdo = requireDatabaseConnection($GLOBALS['pdo'] ?? null);
$GLOBALS['pdo'] = $pdo;
$baseUri = (string) ($GLOBALS['baseUri'] ?? '');
$isLoggedIn = (bool) ($GLOBALS['isLoggedIn'] ?? false);

require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Support/helpers.php';
require_once $leaderboardProjectRoot . '/includes/src/Modules/Leaderboard/Support/cache-manager.php';

$pageTitle = 'Lider Tablosu';

$leaderboardSettings = leaderboardGetSettings($pdo);

$leaderboard_disabled = (($leaderboardSettings['leaderboard_enabled'] ?? '1') !== '1');
$leaderboard_disabled_message = trim((string) ($leaderboardSettings['leaderboard_disabled_message'] ?? ''));
if ($leaderboard_disabled_message === '') {
    $leaderboard_disabled_message = 'Liderlik tablosu şu anda kapalı. Lütfen daha sonra tekrar kontrol edin.';
}
$leaderboard_disabled_contact_url = routePublicStaticUrl('contact');
$leaderboard_disabled_categories_url = categoryListUrl();

$leaderboardThemeShellSettings = isset($_lay) && is_array($_lay)
    ? $_lay
    : (isset($settings) && is_array($settings)
        ? $settings
        : (function_exists('getAdminSettings') && isset($pdo)
            ? getAdminSettings($pdo)
            : []));
$leaderboardThemeShellSettings['site_name'] = trim((string) ($leaderboardThemeShellSettings['site_name'] ?? '')) !== ''
    ? (string) $leaderboardThemeShellSettings['site_name']
    : 'TurkMod';
$leaderboardThemeShellSettings['header_brand_text'] = trim((string) ($leaderboardThemeShellSettings['header_brand_text'] ?? '')) !== ''
    ? (string) $leaderboardThemeShellSettings['header_brand_text']
    : 'TurkMod';
$leaderboardThemeShellSettings['footer_brand_text'] = trim((string) ($leaderboardThemeShellSettings['footer_brand_text'] ?? '')) !== ''
    ? (string) $leaderboardThemeShellSettings['footer_brand_text']
    : 'TurkMod';
$leaderboardThemeShellSettings['menu_items'] = "Anasayfa|/index.php|bi-house\nKategoriler|{category_list}|bi-grid";
$leaderboardThemeShellSettings['footer_nav_links'] = "Ana sayfa|{base_url}/index.php\nKategoriler|{base_url}/kategoriler";
$leaderboardThemeShellSettings['footer_copyright'] = '&copy; {current_year}. <a href="{base_url}/index.php" class="site-footer-brand-link">{site_name}</a> - Tüm hakları saklıdır.';
$__leaderboardThemeShellSettings = $leaderboardThemeShellSettings;
$GLOBALS['_lay'] = $__leaderboardThemeShellSettings;
$_lay = $__leaderboardThemeShellSettings;

/**
 * Keep the structured theme template in sync with the variables prepared by
 * this page while preserving any values supplied by the route/bootstrap.
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $scope
 * @return array<string, mixed>
 */
$leaderboardThemePageVars = static function (array $existing, array $scope): array {
    foreach ($scope as $key => $value) {
        if (str_starts_with((string) $key, 'leaderboard_')) {
            $existing[(string) $key] = $value;
        }
    }

    return $existing;
};

if ($leaderboard_disabled) {
    $pageTitle = 'Lider Tablosu Kapali';
    $publicHeaderVars = $leaderboardThemePageVars(
        isset($publicHeaderVars) && is_array($publicHeaderVars) ? $publicHeaderVars : [],
        get_defined_vars()
    );
    require_once $leaderboardProjectRoot . '/includes/public-header.php';
    ?>

    <div class="container public-container public-breadcrumb breadcrumb-container ui-container">
        <nav class="breadcrumb">
            <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
            <i class="bi bi-chevron-right"></i>
            <span>Lider Tablosu</span>
        </nav>
    </div>

    <div class="leaderboard-container ui-container ui-section">
        <div class="ui-panel">
            <div class="ui-panel__body">
                <section class="leaderboard-empty-state leaderboard-empty-state--disabled ui-empty" role="status" aria-labelledby="leaderboardDisabledTitle">
                    <div class="leaderboard-empty-state__media" aria-hidden="true">
                        <span class="leaderboard-empty-state__halo"></span>
                        <span class="leaderboard-empty-state__icon"><i class="bi bi-pause-circle"></i></span>
                    </div>
                    <div class="leaderboard-empty-state__content">
                        <span class="leaderboard-empty-state__eyebrow">Liderlik sistemi</span>
                        <h1 id="leaderboardDisabledTitle"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= htmlspecialchars($leaderboard_disabled_message, ENT_QUOTES, 'UTF-8') ?></p>
                        <ul class="leaderboard-empty-state__tips">
                            <li><i class="bi bi-check2-circle"></i> Sistem yeniden acildiginda siralamalar otomatik hesaplanir.</li>
                            <li><i class="bi bi-check2-circle"></i> Kategorileri gezerek aktif icerikleri takip edebilirsiniz.</li>
                        </ul>
                        <div class="leaderboard-empty-state__actions">
                            <a class="leaderboard-empty-state__btn is-primary" href="<?= htmlspecialchars($leaderboard_disabled_categories_url, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-grid"></i>
                                <span>Kategorilere Git</span>
                            </a>
                            <a class="leaderboard-empty-state__btn is-secondary" href="<?= htmlspecialchars($leaderboard_disabled_contact_url, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-envelope-paper"></i>
                                <span>Yonetimle Iletisim</span>
                            </a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <?php
    require_once $leaderboardProjectRoot . '/includes/public-footer.php';
    return;
}

// Get parameters
$category = $_GET['category'] ?? 'daily_login';
$period = $_GET['period'] ?? 'daily';
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$search = trim((string) ($_GET['search'] ?? ''));
$perPage = 50;
$offset = 0;

// Validate category
$validCategories = leaderboardGetValidCategories();
if (!in_array($category, $validCategories, true)) {
    $category = 'daily_login';
}

// Validate period
$validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all_time'];
if (!in_array($period, $validPeriods, true)) {
    $period = 'daily';
}

$categories = leaderboardGetCategories();

// Period names
$periods = [
    'daily' => 'Günlük',
    'weekly' => 'Haftalık',
    'monthly' => 'Aylık',
    'quarterly' => 'Çeyreklik',
    'yearly' => 'Yıllık',
    'all_time' => 'Tüm Zamanlar'
];
$leaderboardBaseUrl = routePublicStaticUrl('leaderboard');
$leaderboard_base_url = $leaderboardBaseUrl;

try {
    $fetchLimit = $perPage;
    $fetchOffset = ($requestedPage - 1) * $perPage;
    $leaderboardData = leaderboardGetData($pdo, $category, $period, $fetchLimit, $fetchOffset, $search !== '' ? $search : null);
    $allUsers = $leaderboardData['data'] ?? [];
    $total = (int) ($leaderboardData['total'] ?? 0);
    $isCached = $leaderboardData['is_cached'] ?? false;
} catch (Throwable $e) {
    appLogException($e, ['source' => 'leaderboard']);
    $allUsers = [];
    $total = 0;
    $isCached = false;
}

$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($requestedPage, $totalPages);
$offset = ($page - 1) * $perPage;
$shouldRefetch = $page !== $requestedPage;

if ($shouldRefetch) {
    try {
        $leaderboardData = leaderboardGetData($pdo, $category, $period, $perPage, $offset, $search !== '' ? $search : null);
        $allUsers = $leaderboardData['data'] ?? [];
        $total = (int) ($leaderboardData['total'] ?? 0);
        $isCached = $leaderboardData['is_cached'] ?? false;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($requestedPage, $totalPages);
        $offset = ($page - 1) * $perPage;
    } catch (Throwable $e) {
        appLogException($e, ['source' => 'leaderboard']);
        $allUsers = [];
        $total = 0;
        $isCached = false;
    }
}

$users = $allUsers;
$medals = ['🥇', '🥈', '🥉'];

$leaderboard_description = (string) ($categories[$category]['desc'] ?? '');
$leaderboard_is_cached = (bool) $isCached;
$leaderboard_search = $search;
$leaderboard_category = $category;
$leaderboard_period = $period;
$leaderboard_category_tabs = [];
foreach ($categories as $catKey => $catInfo) {
    $leaderboard_category_tabs[] = [
        'url' => $leaderboardBaseUrl . '?' . http_build_query(array_filter([
            'category' => $catKey,
            'period' => $period,
            'search' => $search !== '' ? $search : null,
        ], static fn ($value): bool => $value !== null && $value !== '')),
        'class' => $category === $catKey ? 'leaderboard-tab active' : 'leaderboard-tab',
        'icon' => (string) ($catInfo['icon'] ?? 'bi-trophy'),
        'name' => (string) ($catInfo['name'] ?? $catKey),
    ];
}

$leaderboard_period_options = [];
foreach ($periods as $periodKey => $periodName) {
    $leaderboard_period_options[] = [
        'url' => $leaderboardBaseUrl . '?' . http_build_query(array_filter([
            'category' => $category,
            'period' => $periodKey,
            'search' => $search !== '' ? $search : null,
        ], static fn ($value): bool => $value !== null && $value !== '')),
        'class' => $period === $periodKey ? 'period-btn active' : 'period-btn',
        'name' => (string) $periodName,
    ];
}

$fallbackAvatarUrl = function_exists('defaultAvatarUrl')
    ? defaultAvatarUrl($baseUri)
    : $baseUri . '/assets/images/noavatar-neon-helmet.svg';
$leaderboard_rows = [];
foreach ($users as $index => $user) {
    if (!is_array($user)) {
        continue;
    }

    $row = function_exists('leaderboardDecorateRow')
        ? leaderboardDecorateRow($user, $baseUri)
        : $user;
    $rank = (int) ($row['rank'] ?? ($offset + $index + 1));
    $userId = (int) ($row['user_id'] ?? 0);
    $avatarUrl = (string) ($row['avatar_url'] ?? $fallbackAvatarUrl);
    $hasAvatar = $avatarUrl !== $fallbackAvatarUrl;
    $rowUsername = (string) ($row['username'] ?? 'Anonim');
    $profileDisplayName = publicProfileDisplayName($row);
    if ($profileDisplayName === '') {
        $profileDisplayName = 'kullanici';
    }

    $rankChange = (int) ($row['rank_change'] ?? $row['change'] ?? 0);
    $metadata = isset($row['metadata']) && is_array($row['metadata']) ? $row['metadata'] : [];
    $metricKey = $categories[$category]['metadata_key'] ?? null;
    $metricLabel = (string) ($categories[$category]['metadata_label'] ?? 'Sayı');
    $metricValue = $metricKey && isset($metadata[$metricKey]) ? number_format((int) $metadata[$metricKey]) : '';
    $isCurrentUser = $isLoggedIn && $userId === (int) ($_SESSION['_auth_user_id'] ?? 0);

    $leaderboard_rows[] = [
        'row_class' => $isCurrentUser ? 'current-user' : '',
        'rank_label' => (string) $rank,
        'rank_class' => $rank <= 3 ? 'medal' : 'rank-number',
        'avatar_url' => $avatarUrl,
        'has_avatar' => $hasAvatar,
        'profile_url' => (string) ($row['profile_url'] ?? publicProfileUrl([
            'id' => $userId,
            'username' => $profileDisplayName,
        ])),
        'username' => $rowUsername,
        'is_current_user' => $isCurrentUser,
        'score' => number_format((int) ($row['count'] ?? $row['score'] ?? 0)),
        'change_class' => $rankChange > 0 ? 'rank-change up' : ($rankChange < 0 ? 'rank-change down' : 'rank-change neutral'),
        'change_icon' => $rankChange > 0 ? 'bi-arrow-up' : ($rankChange < 0 ? 'bi-arrow-down' : 'bi-dash'),
        'change_label' => $rankChange !== 0 ? (string) abs($rankChange) : '',
        'has_metadata' => $metricValue !== '',
        'metadata_icon' => (string) ($categories[$category]['icon'] ?? 'bi-trophy'),
        'metadata_label' => $metricLabel,
        'metadata_value' => $metricValue,
    ];
}

$users = $leaderboard_rows;
$leaderboard_has_rows = $leaderboard_rows !== [];
$leaderboard_empty_message = $search !== ''
    ? 'Aramanızla eşleşen kullanıcı bulunamadı.'
    : 'Bu kategori için henüz sıralama verisi bulunmuyor.';
$leaderboard_avatar_fallback = $fallbackAvatarUrl;
$leaderboard_search_clear_url = $leaderboardBaseUrl . '?' . http_build_query([
    'category' => $category,
    'period' => $period,
]);
$leaderboard_empty_title = $search !== ''
    ? 'Arama sonucu bulunamadı'
    : 'Sıralama henüz oluşmadı';
$leaderboard_empty_description = $search !== ''
    ? 'Filtreyi değiştirerek tekrar deneyebilir veya aramayı temizleyerek tüm kullanıcıları görebilirsiniz.'
    : 'Bu kategori ve dönem için henüz puanlanan üye yok. İlk hareket geldiğinde tablo otomatik oluşur.';
$leaderboard_empty_primary_url = $search !== ''
    ? $leaderboard_search_clear_url
    : routePublicStaticUrl('upload_topic');
$leaderboard_empty_primary_label = $search !== ''
    ? 'Aramayı Temizle'
    : 'İlk İçeriği Yükle';
$leaderboard_empty_secondary_url = function_exists('categoryListUrl')
    ? categoryListUrl()
    : (rtrim((string) $baseUri, '/') . '/kategoriler');
$leaderboard_empty_secondary_label = 'Kategorileri Keşfet';
$leaderboard_empty_period_label = (string) ($periods[$period] ?? 'Seçili dönem');
$leaderboard_empty_category_label = (string) ($categories[$category]['name'] ?? 'Genel');
$leaderboard_total_pages = (int) $totalPages;
$leaderboard_current_page = (int) $page;
$leaderboard_has_pagination = $leaderboard_total_pages > 1;
$leaderboard_prev_url = $leaderboardBaseUrl . '?' . http_build_query(array_filter([
    'category' => $category,
    'period' => $period,
    'page' => max(1, $page - 1),
    'search' => $search !== '' ? $search : null,
], static fn ($value): bool => $value !== null && $value !== ''));
$leaderboard_next_url = $leaderboardBaseUrl . '?' . http_build_query(array_filter([
    'category' => $category,
    'period' => $period,
    'page' => min($leaderboard_total_pages, $page + 1),
    'search' => $search !== '' ? $search : null,
], static fn ($value): bool => $value !== null && $value !== ''));
$leaderboard_prev_disabled = $page <= 1;
$leaderboard_next_disabled = $page >= $leaderboard_total_pages;
$leaderboard_pagination_pages = [];
if ($leaderboard_has_pagination) {
    $startPage = max(1, $page - 2);
    $endPage = min($leaderboard_total_pages, $page + 2);
    for ($i = $startPage; $i <= $endPage; $i++) {
        $leaderboard_pagination_pages[] = [
            'url' => $leaderboardBaseUrl . '?' . http_build_query(array_filter([
                'category' => $category,
                'period' => $period,
                'page' => $i,
                'search' => $search !== '' ? $search : null,
            ], static fn ($value): bool => $value !== null && $value !== '')),
            'label' => (string) $i,
            'class' => $i === $page ? 'active' : '',
        ];
    }
}

$publicHeaderVars = $leaderboardThemePageVars(
    isset($publicHeaderVars) && is_array($publicHeaderVars) ? $publicHeaderVars : [],
    get_defined_vars()
);
require_once $leaderboardProjectRoot . '/includes/public-header.php';
?>

<!-- Breadcrumb -->
<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container public-container public-breadcrumb breadcrumb-container ui-container">
    <nav class="breadcrumb">
        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <span>Lider Tablosu</span>
    </nav>
</div>
<?php endif; ?>

<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/leaderboard-page.css', $baseUri) ?>">
<?php endif; ?>

<div class="leaderboard-container ui-container ui-section">
    <div class="ui-panel">
        <div class="ui-panel__body">
                    <!-- Header -->
                    <div class="leaderboard-header">
                        <div class="leaderboard-title">
                            <span class="leaderboard-eyebrow">Topluluk Sıralaması</span>
                            <h1><i class="bi bi-trophy"></i> Lider Tablosu</h1>
                            <p><?= htmlspecialchars($categories[$category]['desc']) ?></p>
                        </div>
                        <?php if ($isCached): ?>
                            <?= uiRenderAlert('Önbellekten hızlı yüklendi', 'info', [
                                'icon' => 'bi-lightning-charge',
                                'class' => 'ui-admin-alert-spaced',
                                'role' => 'status',
                            ]) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Category Tabs -->
                    <div class="leaderboard-tabs">
                        <?php foreach ($categories as $catKey => $catInfo): ?>
                            <a href="?category=<?= $catKey ?>&period=<?= $period ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                               class="leaderboard-tab<?= $category === $catKey ? ' active' : '' ?>">
                                <i class="bi <?= $catInfo['icon'] ?>"></i>
                                <span><?= $catInfo['name'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Period Buttons & Search -->
                    <div class="leaderboard-controls">
                        <div class="period-buttons">
                            <?php foreach ($periods as $periodKey => $periodName): ?>
                                <button class="period-btn<?= $period === $periodKey ? ' active' : '' ?>"
                                        data-period="<?= $periodKey ?>"
                                        data-leaderboard-href="?category=<?= $category ?>&period=<?= $periodKey ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $periodName ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="leaderboard-search">
                            <form method="get" action="">
                                <input type="hidden" name="category" value="<?= $category ?>">
                                <input type="hidden" name="period" value="<?= $period ?>">
                                <div class="search-input-group">
                                    <i class="bi bi-search"></i>
                                    <input type="text"
                                           name="search"
                                           placeholder="Kullanıcı ara..."
                                           value="<?= htmlspecialchars($search) ?>"
                                           class="search-input">
                                    <?php if ($search): ?>
                                        <a href="?category=<?= $category ?>&period=<?= $period ?>" class="search-clear">
                                            <i class="bi bi-x"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Leaderboard Table -->
                    <?php if (empty($users)): ?>
                        <div class="leaderboard-empty-state ui-empty" role="status" aria-live="polite" aria-labelledby="leaderboardEmptyTitle">
                            <div class="leaderboard-empty-state__media" aria-hidden="true">
                                <span class="leaderboard-empty-state__halo"></span>
                                <span class="leaderboard-empty-state__icon"><i class="bi bi-graph-down-arrow"></i></span>
                            </div>
                            <div class="leaderboard-empty-state__content">
                                <span class="leaderboard-empty-state__eyebrow">Henüz sıralama yok</span>
                                <h3 id="leaderboardEmptyTitle"><?= htmlspecialchars($leaderboard_empty_title, ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($leaderboard_empty_description, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="leaderboard-empty-state__context">
                                    <span><i class="bi bi-collection"></i> <?= htmlspecialchars($leaderboard_empty_category_label, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><i class="bi bi-calendar3"></i> <?= htmlspecialchars($leaderboard_empty_period_label, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <ul class="leaderboard-empty-state__tips">
                                    <li><i class="bi bi-check2-circle"></i> Farklı dönem seçerek karşılaştırma yapabilirsiniz.</li>
                                    <li><i class="bi bi-check2-circle"></i> Liste oluştuğunda ilk 3 üye burada rozetle gösterilir.</li>
                                    <li><i class="bi bi-check2-circle"></i> Kategori geçişleri ve arama filtreleri korunur.</li>
                                </ul>
                                <div class="leaderboard-empty-state__actions">
                                    <a class="leaderboard-empty-state__btn is-primary" href="<?= htmlspecialchars($leaderboard_empty_primary_url, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-magic"></i>
                                        <span><?= htmlspecialchars($leaderboard_empty_primary_label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                    <a class="leaderboard-empty-state__btn is-secondary" href="<?= htmlspecialchars($leaderboard_empty_secondary_url, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-compass"></i>
                                        <span><?= htmlspecialchars($leaderboard_empty_secondary_label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="leaderboard-table-container ui-table-wrap">
                            <table class="leaderboard-table">
                                <thead>
                                    <tr>
                                        <th class="col-rank">Sıra</th>
                                        <th class="col-user">Kullanıcı</th>
                                        <th class="col-score">Sayı</th>
                                        <th class="col-change">Değişim</th>
                                        <th class="col-metadata">Detaylar</th>
                                    </tr>
                                </thead>
                                <tbody id="leaderboard-tbody">
                                    <?php foreach ($users as $index => $user): ?>
                                        <?php
                                        $rank = $offset + $index + 1;
                                        $username = htmlspecialchars($user['username'] ?? 'Anonim');
                                        $count = number_format((int)($user['count'] ?? $user['score'] ?? 0));
                                        $userId = (int)($user['user_id'] ?? 0);
                                        $rankChange = (int)($user['rank_change'] ?? $user['change'] ?? 0);
                                        $metadata = $user['metadata'] ?? [];
                                        $metricKey = $categories[$category]['metadata_key'] ?? null;
                                        $metricLabel = $categories[$category]['metadata_label'] ?? 'Sayı';

                                        // Highlight current user
                                        $isCurrentUser = $isLoggedIn && $userId === (int)$_SESSION['_auth_user_id'];

                                        $avatarUrl = htmlspecialchars((string)($user['avatar_url'] ?? $fallbackAvatarUrl), ENT_QUOTES, 'UTF-8');
                                        $profileDisplayName = publicProfileDisplayName($user);
                                        if ($profileDisplayName === '') {
                                            $profileDisplayName = 'kullanici';
                                        }
                                        $profileUrl = htmlspecialchars((string)($user['profile_url'] ?? publicProfileUrl([
                                            'id' => $userId,
                                            'username' => $profileDisplayName,
                                        ])), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr class="<?= $isCurrentUser ? 'current-user' : '' ?>">
                                            <td class="col-rank">
                                                <?php if ($rank <= 3): ?>
                                                    <span class="medal"><?= $medals[$rank - 1] ?></span>
                                                <?php else: ?>
                                                    <span class="rank-number"><?= $rank ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-user">
                                                <div class="user-cell">
                                                    <a href="<?= $profileUrl ?>">
                                                        <img src="<?= $avatarUrl ?>" alt="<?= $username ?>" class="user-avatar" width="30" height="30" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($fallbackAvatarUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                    </a>
                                                    <div class="user-info">
                                                        <a href="<?= $profileUrl ?>" class="user-name"><?= $username ?></a>
                                                        <?php if ($isCurrentUser): ?>
                                                            <span class="user-badge">Siz</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="col-score">
                                                <strong><?= $count ?></strong>
                                            </td>
                                            <td class="col-change">
                                                <?php if ($rankChange > 0): ?>
                                                    <span class="rank-change up">
                                                        <i class="bi bi-arrow-up"></i> <?= $rankChange ?>
                                                    </span>
                                                <?php elseif ($rankChange < 0): ?>
                                                    <span class="rank-change down">
                                                        <i class="bi bi-arrow-down"></i> <?= abs($rankChange) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="rank-change neutral">
                                                        <i class="bi bi-dash"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-metadata">
                                                <?php if (!empty($metadata)): ?>
                                                    <div class="metadata-items">
                                                        <?php if ($metricKey && isset($metadata[$metricKey])): ?>
                                                            <span class="metadata-item" title="<?= htmlspecialchars($metricLabel) ?>">
                                                                <i class="bi <?= htmlspecialchars($categories[$category]['icon']) ?>"></i> <?= number_format((int)$metadata[$metricKey]) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <button <?= $page <= 1 ? 'disabled' : '' ?>
                                        data-leaderboard-href="?category=<?= $category ?>&period=<?= $period ?>&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                                        aria-label="Önceki sayfa">
                                    <i class="bi bi-chevron-left"></i>
                                </button>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                ?>

                                <?php if ($startPage > 1): ?>
                                    <button data-leaderboard-href="?category=<?= $category ?>&period=<?= $period ?>&page=1<?= $search ? '&search=' . urlencode($search) : '' ?>">1</button>
                                    <?php if ($startPage > 2): ?>
                                        <span>...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <button class="<?= $i === $page ? 'active' : '' ?>"
                                            data-leaderboard-href="?category=<?= $category ?>&period=<?= $period ?>&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                                            <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span>...</span>
                                    <?php endif; ?>
                                    <button data-leaderboard-href="?category=<?= $category ?>&period=<?= $period ?>&page=<?= $totalPages ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                        <?= $totalPages ?>
                                    </button>
                                <?php endif; ?>

                                <button <?= $page >= $totalPages ? 'disabled' : '' ?>
                                        data-leaderboard-href="?category=<?= $category ?>&period=<?= $period ?>&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                                        aria-label="Sonraki sayfa">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
            </div>
        </div>
</div>

<?php
$leaderboardScriptPath = $leaderboardProjectRoot . '/assets/js/leaderboard.js';
$leaderboardScriptUrl = rtrim($baseUri, '/') . '/assets/js/leaderboard.js?v=' . rawurlencode((string) (is_file($leaderboardScriptPath) ? filemtime($leaderboardScriptPath) : time()));
?>
<script src="<?= htmlspecialchars($leaderboardScriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>

<?php require_once $leaderboardProjectRoot . '/includes/public-footer.php'; ?>


