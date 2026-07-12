<?php
declare(strict_types=1);

/**
 * Admin panel ic yardimci fonksiyonlari.
 *
 * Tek dosyada toplanan cesitli domain yardimcilari: admin ayarlari (getAdminSettings,
 * adminSettingDefinitions), izin/audit (adminRequirePermission, adminShouldAudit),
 * rapor/siralama/duzenleme yardimcilari vb.
 *
 * Standartlar acisindan bu dosya alan bazli alt dosyalara bolunmeli:
 *   admin/helpers/admin-settings.php
 *   admin/helpers/permissions.php
 *   admin/helpers/audit.php
 *   admin/helpers/... vb.
 * Bu bolunme sirasiyla gerceklestirilmelidir (init.php`deki require_once sirasina
 * dikkat). Bu dokumantasyon, sonraki bakimci icin yol isaretidir.
 */

function adminSafeIdentifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
        throw new InvalidArgumentException("Invalid identifier: {$identifier}");
    }
    return "`{$identifier}`";
}

function adminRenderForbiddenPage(string $message = 'Bu sayfaya erişim yetkiniz yok.'): void
{
    global $pdo, $baseUri;

    http_response_code(403);

    $pageTitle = 'Erişim Engellendi';
    $forbiddenMessage = $message;

    require __DIR__ . '/header.php';
    ?>
    <section class="ui-empty ui-admin-forbidden-empty" aria-labelledby="admin-forbidden-title">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        <h2 id="admin-forbidden-title">Bu alan için yetkiniz yok</h2>
        <p><?= htmlspecialchars($forbiddenMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.adminForbidden) {
            window.adminForbidden(<?= json_encode($forbiddenMessage, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        }
    });
    </script>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

function adminCurrentUserId(): int
{
    return (int) ($_SESSION['_auth_user_id'] ?? 0);
}

function adminPermissionList(array|string $permissions): array
{
    $items = is_array($permissions) ? $permissions : [$permissions];
    return array_values(array_filter(array_map(static function ($permission): string {
        return trim((string) $permission);
    }, $items), static fn(string $permission): bool => $permission !== ''));
}

function adminUserCanAny(?PDO $pdo, int $userId, array|string $permissions): bool
{
    if (!$pdo || $userId <= 0) {
        return false;
    }

    foreach (adminPermissionList($permissions) as $permission) {
        if (function_exists('userHasPermission') && userHasPermission($pdo, $userId, $permission)) {
            return true;
        }
    }

    return false;
}

function adminCurrentUserCan(array|string $permissions): bool
{
    global $pdo;

    return adminUserCanAny($pdo instanceof PDO ? $pdo : null, adminCurrentUserId(), $permissions);
}

function adminRequirePermission(array|string $permissions, string $message = 'Bu sayfaya erisim yetkiniz yok.'): void
{
    if (!adminCurrentUserCan($permissions)) {
        adminRenderForbiddenPage($message);
    }
}

function adminIsAjaxRequest(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function adminDenyAction(string $message = 'Bu islemi yapma yetkiniz yok.', string $redirect = ''): void
{
    if (adminIsAjaxRequest()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'success' => false,
            'message' => $message,
            'error' => $message,
            'code' => 'forbidden',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    flash('error', $message);
    if ($redirect !== '') {
        $redirect = trim($redirect);
        if ($redirect !== '' && (preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1 || preg_match('~^[a-z][a-z0-9+.-]*:~i', $redirect) === 1 || str_starts_with($redirect, '//') || str_contains($redirect, '\\'))) {
            $redirect = '';
        }
    }
    if ($redirect !== '') {
        header('Location: ' . $redirect);
        exit;
    }

    adminRenderForbiddenPage($message);
}

function adminRenderLogsSubtabs(string $active): void
{
    global $baseUri;

    $items = [
        'activity' => [
            'label' => 'Yönetici İşlem Günlüğü',
            'href' => '/admin/logs.php',
            'icon' => 'bi-journal-text',
            'permission' => 'logs.view',
        ],
        'action' => [
            'label' => 'Kullanıcı İşlem Günlüğü',
            'href' => '/admin/action-log.php',
            'icon' => 'bi-clock-history',
            'permission' => 'logs.view',
        ],
        'application' => [
            'label' => 'Uygulama Logları',
            'href' => '/admin/application-logs.php',
            'icon' => 'bi-journal-code',
            'permission' => 'logs.view',
        ],
        'email' => [
            'label' => 'E-posta Logları',
            'href' => '/admin/email-logs.php',
            'icon' => 'bi-envelope-paper',
            'permission' => 'logs.view',
        ],
        'cron' => [
            'label' => 'Cron Logları',
            'href' => '/admin/logs.php?view=cron',
            'icon' => 'bi-card-list',
            'permission' => 'logs.view',
        ],
        'rate_limits' => [
            'label' => 'Rate Limit İzleme',
            'href' => '/admin/rate-limits.php',
            'icon' => 'bi-speedometer2',
            'permission' => 'rate_limits.view',
        ],
    ];

    $visibleItems = array_filter($items, static function (array $item): bool {
        return function_exists('adminCurrentUserCan') && adminCurrentUserCan((string) $item['permission']);
    });
    if ($visibleItems === []) {
        return;
    }

    echo '<nav class="site-subtabs logs-subtabs" aria-label="Günlükler alt sekmeleri">';
    foreach ($visibleItems as $key => $item) {
        $classes = 'site-subtab-link logs-subtab-link' . ($active === $key ? ' active' : '');
        echo '<a class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars(rtrim((string) $baseUri, '/') . (string) $item['href'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="bi ' . htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8') . '"></i>';
        echo '<span>' . htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</a>';
    }
    echo '</nav>';
}

function adminSettingDefinitions(): array
{
    // Performans (#20): Tanımlar saf sabit bir dizi olduğundan, her çağrıda ~1000
    // satırlık dizinin yeniden inşa edilmesini önlemek için istek içi memoizasyon.
    // adminSettingValue() gibi sık çağrılan yollar artık tekrar tekrar inşa etmez.
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $definitions = [
        // -- Genel ----------------------------------------------
        'items_per_page'         => ['label' => 'Sayfa Başına İçerik',   'type' => 'number', 'default' => '20',                                         'section' => 'general'],
        'site_language'          => ['label' => 'Site Dili',             'type' => 'select', 'default' => 'tr', 'section' => 'general', 'options' => ['tr' => 'Türkçe']],
        'timezone'               => ['label' => 'Saat Dilimi',           'type' => 'select', 'default' => 'Europe/Istanbul', 'section' => 'general', 'options' => ['Europe/Istanbul' => 'Europe/Istanbul (UTC+3)', 'Europe/London' => 'Europe/London (UTC+0)', 'America/New_York' => 'America/New_York (UTC-5)', 'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)', 'UTC' => 'UTC']],
        'date_format'            => ['label' => 'Tarih Formatı',         'type' => 'select', 'default' => 'd.m.Y', 'section' => 'general', 'options' => ['d.m.Y' => '31.12.2025', 'Y-m-d' => '2025-12-31', 'd/m/Y' => '31/12/2025', 'M d, Y' => 'Dec 31, 2025']],
        'terms_url'              => ['label' => 'Kullanım Koşulları URL', 'type' => 'string', 'default' => '',                                           'section' => 'general'],
        'privacy_url'            => ['label' => 'Gizlilik Politikası URL','type' => 'string', 'default' => '',                                           'section' => 'general'],
        'footer_text'            => ['label' => 'Footer Metni',          'type' => 'text',   'default' => '',                                            'section' => 'general'],
        'site_name'              => ['label' => 'Site Adı',              'type' => 'string', 'default' => 'İçerik Topic',                              'section' => 'general'],
        'site_description'       => ['label' => 'Site Açıklaması',       'type' => 'text',   'default' => 'Topluluk içerikleri ve paylaşım platformu.',     'section' => 'general'],

        // -- SEO ------------------------------------------------
        'canonical_base_url'       => ['label' => 'Canonical Ana URL',            'type' => 'string', 'default' => '',                               'section' => 'seo', 'tooltip' => 'Canonical URL\'ler için kullanılacak temel URL (boş bırakılırsa otomatik tespit edilir)'],
        'canonical_trailing_slash' => ['label' => 'Canonical Sonda Slash Kullan', 'type' => 'bool',   'default' => '0',                                                  'section' => 'seo', 'tooltip' => 'Canonical URL\'lerin sonuna "/" ekler (örn: /sayfa/ yerine /sayfa)'],
        'og_image'                 => ['label' => 'Open Graph Görsel URL',        'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Sosyal medyada paylaşımlarda kullanılacak varsayılan Open Graph görseli (1200x630px)'],
        'og_type'                  => ['label' => 'Open Graph Türü',              'type' => 'select', 'default' => 'website', 'section' => 'seo', 'options' => ['website' => 'Website', 'article' => 'Article', 'blog' => 'Blog'], 'tooltip' => 'Open Graph içerik türü (website: genel site, article: makale, blog: blog yazısı)'],
        'twitter_card'             => ['label' => 'Twitter Card Türü',            'type' => 'select', 'default' => 'summary_large_image', 'section' => 'seo', 'options' => ['summary' => 'Summary', 'summary_large_image' => 'Summary Large Image'], 'tooltip' => 'Twitter\'da paylaşımlarda kullanılacak kart türü (summary_large_image: büyük görsel önerilir)'],
        'twitter_handle'           => ['label' => 'Twitter Kullanıcı Adı',        'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Sitenizin Twitter kullanıcı adı (@ olmadan, örn: icerik_topic)'],
        'google_analytics_id'      => ['label' => 'Google Analytics ID',          'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Google Analytics izleme kodu (örn: G-XXXXXXXXXX veya UA-XXXXXXXXX-X)'],
        'google_site_verification' => ['label' => 'Google Site Doğrulama Kodu',   'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Google Search Console site doğrulama meta tag içeriği'],
        'custom_head_code'         => ['label' => 'Özel Head Kodu (JS/CSS)',      'type' => 'text',   'default' => '',                                                   'section' => 'seo', 'tooltip' => '<head> bölümüne eklenecek özel HTML/JS/CSS kodu (tracking scriptleri, meta taglar vb.)'],
        'sitemap_enabled'          => ['label' => 'Sitemap Aktif',                'type' => 'bool',   'default' => '1',                                                  'section' => 'seo', 'tooltip' => 'XML sitemap oluşturma ve sunma özelliğini aktif eder'],
        'sitemap_max_urls'         => ['label' => 'Sitemap Maks. URL Sayısı',   'type' => 'number', 'default' => '1000',                                                'section' => 'seo', 'tooltip' => 'Tek bir sitemap dosyasına dahil edilecek maksimum URL sayısı (Google limiti: 50.000). Bu sayı aşılırsa otomatik olarak topic-sitemap-2.xml, topic-sitemap-3.xml gibi yeni sitemaplar oluşturulur.'],
        'sitemap_changefreq'       => ['label' => 'Sitemap Değişim Sıklığı',    'type' => 'select', 'default' => 'weekly', 'section' => 'seo', 'options' => ['always' => 'Her zaman', 'hourly' => 'Saatlik', 'daily' => 'Günlük', 'weekly' => 'Haftalık', 'monthly' => 'Aylık', 'yearly' => 'Yıllık', 'never' => 'Asla'], 'tooltip' => 'Arama motorlarına içeriğin ne sıklıkla değiştiğini bildirir (tavsiye niteliğinde)'],
        'sitemap_priority_home'    => ['label' => 'Sitemap Ana Sayfa Önceliği', 'type' => 'select', 'default' => '1.0', 'section' => 'seo', 'options' => ['1.0' => '1.0', '0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.5' => '0.5'], 'tooltip' => 'Anasayfanın sitedeki göreli önceliği (0.0-1.0 arası, 1.0 en yüksek)'],
        'sitemap_priority_topics'  => ['label' => 'Sitemap Konu Önceliği',      'type' => 'select', 'default' => '0.6', 'section' => 'seo', 'options' => ['0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.6' => '0.6', '0.5' => '0.5', '0.4' => '0.4'], 'tooltip' => 'Konu sayfalarının sitedeki göreli önceliği (0.0-1.0 arası)'],
        'sitemap_priority_categories' => ['label' => 'Sitemap Kategori Önceliği', 'type' => 'select', 'default' => '0.7', 'section' => 'seo', 'options' => ['0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.6' => '0.6', '0.5' => '0.5'], 'tooltip' => 'Kategori sayfalarının sitedeki göreli önceliği (0.0-1.0 arası)'],
        'sitemap_include_categories' => ['label' => 'Sitemap Kategorileri Dahil Et', 'type' => 'bool', 'default' => '1',                                                'section' => 'seo', 'tooltip' => 'Kategori sayfalarını XML sitemap\'e dahil eder'],
        'image_sitemap_enabled'    => ['label' => 'Image Sitemap Aktif',        'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Görseller için ayrı XML sitemap oluşturur (Google Images için önemli)'],
        'image_sitemap_max_images' => ['label' => 'Konu Başına Maks. Görsel',   'type' => 'number', 'default' => '20',                                                   'section' => 'seo', 'tooltip' => 'Bir konu sayfasından image sitemap\'e dahil edilecek maksimum görsel sayısı'],
        'image_sitemap_hero'       => ['label' => 'Üst Resmi Dahil Et',         'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Konu kapak görsellerini (hero image) image sitemap\'e dahil eder'],
        'image_sitemap_media'      => ['label' => 'Medya Dosyalarını Dahil Et', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Medya galerisi görsellerini image sitemap\'e dahil eder'],
        'image_sitemap_inline'     => ['label' => 'İçerik Görsellerini Dahil Et', 'type' => 'bool', 'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'İçerik metni içindeki görselleri image sitemap\'e dahil eder'],
        'robots_enabled'           => ['label' => 'Robots.txt Aktif',           'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Dinamik robots.txt dosyası oluşturma ve sunma özelliğini aktif eder'],
        'robots_disallow_admin'    => ['label' => 'Robots: /admin/ Engelle',    'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Admin panelini arama motorlarından gizler (güvenlik için önerilir)'],
        'robots_disallow_includes' => ['label' => 'Robots: /includes/ Engelle', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'PHP include dosyalarını arama motorlarından gizler (önerilir)'],
        'robots_disallow_uploads'  => ['label' => 'Robots: /uploads/ Engelle',  'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Yükleme klasörünü arama motorlarından gizler; image sitemap içinden de dışlanır'],
        'robots_disallow_database' => ['label' => 'Robots: /database/ Engelle', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Veritabanı klasörünü arama motorlarından gizler (güvenlik için önerilir)'],
        'robots_crawl_delay'       => ['label' => 'Robots Crawl Delay (sn)',    'type' => 'number', 'default' => '0',                                                    'section' => 'seo', 'tooltip' => 'Botların istekler arasında beklemesi gereken süre (0=sınırsız, sunucu yükünü azaltır)'],
        'robots_custom_rules'      => ['label' => 'Robots Özel Kurallar',       'type' => 'text',   'default' => '',                                                     'section' => 'seo', 'tooltip' => 'Robots.txt\'e eklenecek özel kurallar (her satır bir kural)'],
        'robots_noindex_search'    => ['label' => 'Arama Sonuçlarını Noindexle (Eski Uyumluluk)',  'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Eski sürümlerle uyumluluk içindir. Yeni ayar: "Arama Sonuçları İndekslensin"'],
        'robots_noindex_profiles'  => ['label' => 'Profil Sayfalarına Noindex', 'type' => 'bool',   'default' => '0',                                                    'section' => 'seo', 'tooltip' => 'Tüm profil sayfalarına noindex ekler (genellikle kapalı tutulur)'],
        'structured_data'          => ['label' => 'Yapısal Veri (JSON-LD)',       'type' => 'bool',   'default' => '1',                                                  'section' => 'seo', 'tooltip' => 'Schema.org yapısal veri (JSON-LD) ekleme özelliğini aktif eder (rich snippets için önemli)'],
        'schema_site_search'       => ['label' => 'Site Search Schema',         'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Google\'da site içi arama kutusu gösterilmesini sağlar (SearchAction schema)'],
        'schema_breadcrumbs'       => ['label' => 'Breadcrumb Schema',          'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Breadcrumb yapısal verisi ekler (Google arama sonuçlarında breadcrumb gösterir)'],

        // Meta Tags Settings
        'seo_public_page_presets_json' => [
            'label' => 'Public Sayfa SEO Presetleri JSON',
            'type' => 'text',
            'default' => '',
            'section' => 'seo',
            'tooltip' => 'Public sayfa presetleri yapılandırılmış arayüzden otomatik oluşturulur.'
        ],
        'default_og_image' => [
            'label' => 'Varsayılan OG Image',
            'type' => 'string',
            'default' => '/assets/og-default.jpg',
            'section' => 'seo',
            'tooltip' => 'Sayfalarda özel görsel yoksa kullanılacak varsayılan Open Graph görseli (1200x630px önerilir)'
        ],
        // Structured Data Settings
        'structured_data_category' => [
            'label' => 'Kategori Structured Data',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kategori sayfalarında BreadcrumbList ve CollectionPage schema.org yapısal verisi ekler'
        ],
        'structured_data_profile' => [
            'label' => 'Profil Structured Data',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Profil sayfalarında Person schema.org yapısal verisi ve etkileşim istatistikleri ekler'
        ],
        'schema_organization_name' => [
            'label' => 'Organization Name',
            'type' => 'string',
            'default' => 'İçerik Topic',
            'section' => 'seo',
            'tooltip' => 'Schema.org Organization yapısal verisinde kullanılacak organizasyon adı'
        ],
        'schema_organization_logo' => [
            'label' => 'Organization Logo URL',
            'type' => 'string',
            'default' => '',
            'section' => 'seo',
            'tooltip' => 'Schema.org Organization yapısal verisinde kullanılacak logo URL (tam URL gerekli)'
        ],

        // Pagination SEO Settings
        'pagination_strategy' => [
            'label' => 'Pagination Stratejisi',
            'type' => 'select',
            'options' => [
                'full' => 'Tam (Canonical + Prev/Next)',
                'canonical-only' => 'Sadece Canonical',
                'noindex' => 'Sayfa 2+ Noindex'
            ],
            'default' => 'full',
            'section' => 'seo',
            'tooltip' => 'Tam: Tüm sayfalar canonical + rel prev/next (Bing için önerilir). Canonical-only: Sadece canonical tag. Noindex: 2. sayfadan sonrası noindex'
        ],
        'pagination_max_pages_index' => [
            'label' => 'Maksimum İndexlenecek Sayfa',
            'type' => 'number',
            'default' => '50',
            'section' => 'seo',
            'tooltip' => 'Bu sayıdan sonraki sayfalara otomatik noindex eklenir (crawl bütçesini korur)'
        ],

        // Image SEO Settings
        'image_alt_auto_generate' => [
            'label' => 'Otomatik Alt Text',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Görseller için otomatik olarak SEO dostu alt text oluşturur'
        ],
        'image_alt_template' => [
            'label' => 'Alt Text Şablonu',
            'type' => 'text',
            'default' => '{{title}} - {{category}} modu',
            'section' => 'seo',
            'tooltip' => 'Konu kartları için alt text şablonu. Değişkenler: {{title}}, {{category}}, {{username}}, {{context}}'
        ],
        'image_alt_fallback' => [
            'label' => 'Alt Text Fallback',
            'type' => 'string',
            'default' => 'İçerik görseli',
            'section' => 'seo',
            'tooltip' => 'Şablon uygulanamadığında kullanılacak varsayılan alt text'
        ],
        'image_alt_min_length' => [
            'label' => 'Minimum Alt Text Uzunluğu',
            'type' => 'number',
            'default' => '10',
            'section' => 'seo',
            'tooltip' => 'Alt text minimum karakter sayısı. Daha kısa olanlar fallback ile değiştirilir.'
        ],
        'image_title_auto_generate' => [
            'label' => 'Otomatik Title Text',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Görseller için otomatik olarak SEO dostu title text oluşturur'
        ],
        'image_title_template' => [
            'label' => 'Title Text Şablonu',
            'type' => 'text',
            'default' => '{{title}} - {{category}} modu',
            'section' => 'seo',
            'tooltip' => 'Görseller için title text şablonu. Değişkenler: {{title}}, {{category}}, {{username}}, {{context}}'
        ],
        'image_title_fallback' => [
            'label' => 'Title Text Fallback',
            'type' => 'string',
            'default' => 'İçerik görseli',
            'section' => 'seo',
            'tooltip' => 'Şablon uygulanamadığında kullanılacak varsayılan title text'
        ],
        'image_title_min_length' => [
            'label' => 'Minimum Title Text Uzunluğu',
            'type' => 'number',
            'default' => '10',
            'section' => 'seo',
            'tooltip' => 'Title text minimum karakter sayısı. Daha kısa olanlar fallback ile değiştirilir.'
        ],

        // Sitemap Settings
        'sitemap_route_enabled' => [
            'label' => 'Sitemap Routing Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Sitemap dosyalarını route.php üzerinden dinamik olarak sunar (/sitemap.xml, /topic-sitemap.xml, /image-sitemap.xml)'
        ],
        'sitemap_cache_duration' => [
            'label' => 'Sitemap Cache Süresi (saniye)',
            'type' => 'number',
            'default' => '3600',
            'section' => 'seo',
            'tooltip' => 'Sitemap dosyalarının önbellekte tutulma süresi (3600 = 1 saat). Performans için önerilir.'
        ],

        // Index/Noindex Settings
        'index_homepage' => [
            'label' => 'Anasayfa Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Anasayfanın arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_categories' => [
            'label' => 'Kategori Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kategori sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_profiles' => [
            'label' => 'Profil Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kullanıcı profil sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_topics' => [
            'label' => 'Konu Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Konu detay sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_search_results' => [
            'label' => 'Arama Sonuclari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Arama sonuç sayfalarının indekslenmesi (genellikle kapalı tutulur, duplicate content önler)'
        ],
        'index_archive_pages' => [
            'label' => 'Arsiv Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Genel arşiv sayfalarının indekslenmesi (genellikle kapalı, duplicate content riski)'
        ],
        'index_empty_categories' => [
            'label' => 'Bos Kategori Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalı olduğunda içeriği olmayan kategori sayfalarına noindex uygulanır'
        ],
        'index_draft_topics' => [
            'label' => 'Taslak Konular Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalı olduğunda taslak konulara noindex uygulanır'
        ],
        'sitemap_exclude_drafts' => [
            'label' => 'Taslakları Sitemap\'ten Çıkar',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Taslak durumundaki konuları sitemap.xml içeriklerinden hariç tutar'
        ],
        'index_paginated_pages' => [
            'label' => 'Sayfalama Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalıysa 2. ve sonraki sayfalara otomatik olarak noindex eklenir. (Crawl bütçesi için önerilir)'
        ],
        'noindex_empty_categories' => [
            'label' => 'Boş Kategorileri İndeksleme',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'İçeriği olmayan kategori sayfalarına otomatik olarak noindex etiketi ekler'
        ],
        'noindex_draft_topics' => [
            'label' => 'Taslak Konuları İndeksleme',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Taslak konuların detay sayfalarına noindex etiketi ekler'
        ],

        // -- Görünüm --------------------------------------------
        'accent_color'           => ['label' => 'Vurgu Rengi',            'type' => 'color',  'default' => '#8b1538',  'section' => 'appearance'],
        'secondary_color'        => ['label' => 'İkincil Renk',           'type' => 'color',  'default' => '#3b82f6',  'section' => 'appearance'],
        'logo_url'               => ['label' => 'Logo URL',               'type' => 'string', 'default' => '',         'section' => 'appearance'],
        'favicon_url'            => ['label' => 'Favicon URL',            'type' => 'string', 'default' => '',         'section' => 'appearance'],
        'custom_css'             => ['label' => 'Özel CSS Kodları',       'type' => 'text',   'default' => '',         'section' => 'appearance'],
        'dark_mode'              => ['label' => 'Karanlık Mod Desteği',   'type' => 'select', 'default' => 'auto',     'section' => 'appearance', 'options' => ['auto' => 'Otomatik (Sistem)', 'light' => 'Her Zaman Açık', 'dark' => 'Her Zaman Karanlik']],
        'font_family'            => ['label' => 'Yazı Tipi',              'type' => 'select', 'default' => 'roboto',  'section' => 'appearance', 'options' => ['roboto' => 'Roboto']],
        'allow_user_theme_selection' => ['label' => 'Kullanıcı Tema Seçimi', 'type' => 'bool', 'default' => '0',       'section' => 'appearance'],
        'theme_preview_enabled'  => ['label' => 'Tema Önizleme',          'type' => 'bool',   'default' => '1',        'section' => 'appearance'],
        'theme_debug_mode'       => ['label' => 'Tema Debug Modu',        'type' => 'bool',   'default' => '0',        'section' => 'appearance'],
        // Internal/canonical active public theme key. Not rendered in settings UI.
        'theme_active_id'        => ['label' => 'Active Theme (Internal)', 'type' => 'string', 'default' => 'default',  'section' => '__internal'],

        // -- Header Ayarlari -----------------------------------
        'header_sticky'          => ['label' => 'Yapışkan Header',            'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_search'     => ['label' => 'Arama Çubuğu Göster',        'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_search_placeholder' => ['label' => 'Arama Placeholder',       'type' => 'string', 'default' => 'İçerik ara...', 'section' => 'lay_header'],
        'header_show_auth_buttons' => ['label' => 'Giriş/Kayıt Butonları',   'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_profile_btn'  => ['label' => 'Profil Butonu Göster',    'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_admin_btn'  => ['label' => 'Admin Panel Butonu',         'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_brand_text'      => ['label' => 'Marka Metni',               'type' => 'string', 'default' => 'İçerik Topic', 'section' => 'lay_header'],
        'header_brand_icon'      => ['label' => 'Marka İkonu (harf)',         'type' => 'string', 'default' => 'M',       'section' => 'lay_header'],
        'header_bg_color'        => ['label' => 'Arka Plan Rengi',           'type' => 'color',  'default' => '#1a2332',  'section' => 'lay_header'],
        'header_text_color'      => ['label' => 'Yazı Rengi',                'type' => 'color',  'default' => '#ffffff',  'section' => 'lay_header'],
        'header_accent_color'    => ['label' => 'Vurgu Rengi',               'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_header'],
        'header_border_color'    => ['label' => 'Alt Çizgi Rengi',           'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_header'],
        'header_custom_css'      => ['label' => 'Header Özel CSS',           'type' => 'text',   'default' => '',         'section' => 'lay_header'],
        'header_topbar_enabled'  => ['label' => 'Üst Bilgi Çubuğu',          'type' => 'bool',   'default' => '0',        'section' => 'lay_header'],
        'header_topbar_text'     => ['label' => 'Üst Çubuk Metni',           'type' => 'string', 'default' => '',         'section' => 'lay_header'],
        'header_topbar_bg'       => ['label' => 'Üst Çubuk Arka Plan',       'type' => 'color',  'default' => '#0f172a',  'section' => 'lay_header'],

        // -- Footer Ayarlari -----------------------------------
        'footer_nav_links'       => ['label' => 'Footer Linkleri (satır başına: Başlık|URL)', 'type' => 'text', 'default' => "Ana sayfa|{base_url}/index.php\nKategoriler|{base_url}/kategoriler\nEtkinlikler|{base_url}/events\nMod Yükle|{base_url}/konu-yukle", 'section' => 'lay_footer'],
        'footer_copyright'  => ['label' => 'Telif Hakkı Metni', 'type' => 'string', 'default' => '&copy; {current_year}. <a href="{base_url}/index.php" class="site-footer-brand-link">{site_name}</a> - Tüm hakları saklıdır.', 'section' => 'lay_footer'],

        // -- Sidebar Ayarlari ----------------------------------
        // Genel Sidebar Ayarları
        'sidebar_enabled'        => ['label' => 'Sidebar Aktif',              'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_position'       => ['label' => 'Sidebar Konumu',            'type' => 'select', 'default' => 'right',    'section' => 'lay_sidebar', 'options' => ['right' => 'Sağ', 'left' => 'Sol']],
        'sidebar_width'          => ['label' => 'Sidebar Genişligi (px)',    'type' => 'number', 'default' => '330',      'section' => 'lay_sidebar'],
        'sidebar_builder_config' => ['label' => 'Sidebar Builder JSON',      'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Anasayfa Sidebar
        'sidebar_home_sticky'    => ['label' => 'Anasayfa: Kayan Sidebar',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_template'  => ['label' => 'Anasayfa Sidebar Şablonu',  'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'full' => 'Tam', 'custom' => 'Ozel']],
        'sidebar_home_popular'   => ['label' => 'Anasayfa: Popüler İçerikler', 'type' => 'bool', 'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_categories'=> ['label' => 'Anasayfa: Kategori Bulutu', 'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_stats'     => ['label' => 'Anasayfa: Site İstatistikleri', 'type' => 'bool', 'default' => '1',      'section' => 'lay_sidebar'],
        'sidebar_home_custom'    => ['label' => 'Anasayfa: Özel Widget',     'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Konu Detay Sidebar
        'sidebar_topic_sticky'   => ['label' => 'Konu Detay: Kayan Sidebar',  'type' => 'bool', 'default' => '1',       'section' => 'lay_sidebar'],
        'sidebar_topic_template' => ['label' => 'Konu Sidebar Şablonu',      'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'related' => 'Benzer İçerikler', 'custom' => 'Ozel']],
        'sidebar_topic_popular'  => ['label' => 'Konu: Popüler İçerikler',   'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_related'  => ['label' => 'Konu: Benzer İçerikler',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_categories'=> ['label' => 'Konu: Kategori Bulutu',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_author'   => ['label' => 'Konu: Yazar Bilgisi',       'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_custom'   => ['label' => 'Konu: Özel Widget',         'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Kategori Sidebar
        'sidebar_category_sticky'=> ['label' => 'Kategori: Kayan Sidebar',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_category_template' => ['label' => 'Kategori Sidebar Şablonu', 'type' => 'select', 'default' => 'default', 'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'navigation' => 'Navigasyon', 'custom' => 'Ozel']],
        'sidebar_category_list'  => ['label' => 'Kategori: Kategori Listesi', 'type' => 'bool',  'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_category_popular'=> ['label' => 'Kategori: Popüler İçerikler', 'type' => 'bool', 'default' => '1',       'section' => 'lay_sidebar'],
        'sidebar_category_stats' => ['label' => 'Kategori: Kategori İstatistikleri', 'type' => 'bool', 'default' => '1',   'section' => 'lay_sidebar'],
        'sidebar_category_custom'=> ['label' => 'Kategori: Özel Widget',     'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Arama Sidebar
        'sidebar_search_sticky'  => ['label' => 'Arama: Kayan Sidebar',      'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_template'=> ['label' => 'Arama Sidebar Şablonu',     'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'filters' => 'Filtreler', 'custom' => 'Ozel']],
        'sidebar_search_filters' => ['label' => 'Arama: Filtre Paneli',      'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_categories'=> ['label' => 'Arama: Kategori Listesi', 'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_popular' => ['label' => 'Arama: Popüler Aramalar',   'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_custom'  => ['label' => 'Arama: Özel Widget',        'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Genel Widget Ayarları
        'sidebar_popular_count'  => ['label' => 'Popüler İçerik Sayısı',     'type' => 'number', 'default' => '6',        'section' => 'lay_sidebar'],
        'sidebar_popular_sort'   => ['label' => 'Popüler Sıralama',          'type' => 'select', 'default' => 'downloads','section' => 'lay_sidebar', 'options' => ['downloads' => 'Indirme', 'views' => 'Görüntülenme', 'date' => 'Tarih']],
        'sidebar_widget_position'=> ['label' => 'Özel Widget Konumu',        'type' => 'select', 'default' => 'bottom',   'section' => 'lay_sidebar', 'options' => ['top' => 'En Üst', 'after-popular' => 'Popülerden Sonra', 'after-categories' => 'Kategorilerden Sonra', 'bottom' => 'En Alt']],

        // -- Menu Ayarlari ------------------------------------
        'menu_items'             => ['label' => 'Üst Menü Öğeleri (satır başına: Başlık|URL|Ikon)', 'type' => 'text', 'default' => "Anasayfa|/index.php|bi-house\nKategoriler|{category_list}|bi-grid\nMod Yükle|{upload_topic}|bi-cloud-arrow-up\nEtkinlikler|{events}|bi-calendar-event\nLiderlik|{leaderboard}|bi-trophy\nİletişim|{contact}|bi-envelope-paper", 'section' => 'lay_menu'],
        'menu_show_categories'   => ['label' => 'Kategorileri Menüye Ekle',  'type' => 'bool',   'default' => '0',        'section' => 'lay_menu'],
        'menu_category_limit'    => ['label' => 'Menüde Maks. Kategori',     'type' => 'number', 'default' => '8',        'section' => 'lay_menu'],
        'menu_cta_enabled'       => ['label' => 'CTA Butonu Aktif',          'type' => 'bool',   'default' => '0',        'section' => 'lay_menu'],
        'menu_cta_text'          => ['label' => 'CTA Buton Metni',           'type' => 'string', 'default' => 'İçerik Yükle', 'section' => 'lay_menu'],
        'menu_cta_url'           => ['label' => 'CTA Buton URL',             'type' => 'string', 'default' => '',         'section' => 'lay_menu'],
        'menu_cta_color'         => ['label' => 'CTA Buton Rengi',           'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_menu'],
        'menu_cta_icon'          => ['label' => 'CTA Buton İkonu',           'type' => 'string', 'default' => 'bi-cloud-arrow-up', 'section' => 'lay_menu'],



        // -- Moderasyon -----------------------------------------
        'banned_words'              => ['label' => 'Yasakli Kelimeler (satır başına bir)', 'type' => 'text', 'default' => '', 'section' => 'moderation'],

        // -- Yorum Sistemi -------------------------------------
        // Genel açma/kapama + onay anahtarları kolay erişim için bu sekmede.
        'allow_comments'            => ['label' => 'Yorumlara Izin Ver',           'type' => 'bool',   'default' => '1', 'section' => 'comments'],
        'comment_approval_required' => ['label' => 'Yorum Onayi Gerekli',         'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'max_comment_length'        => ['label' => 'Maks. Yorum Uzunlugu',        'type' => 'number', 'default' => '2000', 'section' => 'comments'],
        'comment_nested'            => ['label' => 'Yanit (Nested) Yorumlar',     'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'comment_max_depth'         => ['label' => 'Maks. Yanit Derinligi',       'type' => 'number', 'default' => '3', 'section' => 'comments'],
        'comment_allow_guest'       => ['label' => 'Misafir Yorum Izni',          'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'comment_edit_window'       => ['label' => 'Duzenleme Suresi (dk, 0=kapali)', 'type' => 'number', 'default' => '0', 'section' => 'comments'],
        'comment_min_length'        => ['label' => 'Min. Yorum Uzunlugu',         'type' => 'number', 'default' => '1', 'section' => 'comments'],
        'comment_per_page'          => ['label' => 'Sayfa Basina Yorum',          'type' => 'number', 'default' => '50', 'section' => 'comments'],
        'comment_sort_order'        => ['label' => 'Yorum Siralama',              'type' => 'select', 'default' => 'asc', 'section' => 'comments', 'options' => ['asc' => 'Eskiden yeniye', 'desc' => 'Yeniden eskiye']],
        'comment_rate_minutes'      => ['label' => 'Yorum Gonderim Penceresi (dakika)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Yorum sayaci bu sure sonunda sifirlanir. Ornek: 5 dakika.'],
        'comment_rate_max'          => ['label' => 'Yorum Gonderim Limiti (pencere basina)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Bir kullanici pencere suresi icinde en fazla kac yorum gonderebilir.'],
        'comment_rate_admin_bypass' => ['label' => 'Adminler Yorum Limitinden Muaf', 'type' => 'bool', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Aciksa admin hesaplari yorum gonderim limitine takilmaz.'],
        'comment_realtime_poll'     => ['label' => 'Canli Yorum Yoklama (sn, 0=kapali)', 'type' => 'number', 'default' => '15', 'section' => 'comments'],

        // -- Gelişmiş Yorum Özellikleri ----------------------
        'comment_reactions_enabled'  => ['label' => 'Reaksiyon Sistemi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumlara emoji reaksiyonları (👍 ❤️ 😂) eklemesine izin verir.'],
        'comment_reactions_types'    => [
            'label' => 'Aktif Reaksiyonlar',
            'type' => 'multicheck',
            'options' => [
                'like' => 'Beğen (👍)',
                'love' => 'Muhteşem (❤️)',
                'laugh' => 'Komik (😂)',
                'wow' => 'İnanılmaz (😮)',
                'sad' => 'Üzgün (😢)',
                'angry' => 'Kızgın (😡)'
            ],
            'default' => 'like,love,laugh,wow,sad,angry',
            'section' => 'comments',
            'tooltip' => 'Aktif etmek istediğiniz reaksiyonları seçin.'
        ],
        'comment_markdown_enabled'   => ['label' => 'Markdown Desteği', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Yorumlarda **kalın**, *italik*, `kod` gibi markdown formatlamaya izin verir.'],
        'comment_mentions_enabled'   => ['label' => 'Mention (@kullanıcı) Sistemi', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => '@kullaniciadi şeklinde kullanıcı etiketlemeye izin verir. Etiketlenen kullanıcı bildirim alır.'],
        'comment_edit_history'       => ['label' => 'Düzenleme Geçmişi Kaydet', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Yorumlarda yapılan düzenlemelerin geçmişini saklar ve görüntülemeye izin verir.'],
        'comment_media_enabled'      => ['label' => 'Görsel Yükleme İzni', 'type' => 'bool', 'default' => '0', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumlara görsel (screenshot) eklemesine izin verir.'],
        'comment_spam_detection'     => ['label' => 'Otomatik Spam Tespiti', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Tekrarlayan yorumları ve spam içeriği otomatik tespit eder.'],
        'comment_report_enabled'     => ['label' => 'Şikayet Sistemi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumları şikayet etmesine izin verir.'],
        'comment_auto_hide_reports'  => ['label' => 'Oto-Gizleme Şikayet Sayısı', 'type' => 'number', 'default' => '5', 'section' => 'comments', 'tooltip' => 'Bir yorum bu sayıda şikayet alırsa otomatik gizlenir (0 = kapalı).'],
        'comment_word_filter'        => ['label' => 'Kelime Filtresi', 'type' => 'text', 'default' => '', 'section' => 'comments', 'tooltip' => 'Virgülle ayırın. Bu kelimeleri içeren yorumlar engellenir veya sansürlenir.'],
        'comment_auto_ban_words'     => ['label' => 'Yasaklı Kelime Eylemi', 'type' => 'select', 'default' => 'pending', 'section' => 'comments', 'options' => ['pending' => 'Onaya Düşür', 'reject' => 'Reddet (Kaydetme)', 'censor' => 'Sansürle (***)'], 'tooltip' => 'Yasaklı kelime bulunduğunda ne yapılacağını seçin.'],

        // -- Dosya Yoneticisi -----------------------------------
        // Download Manager
        'download_countdown_seconds' => ['label' => 'Geri Sayım Suresi (sn)', 'type' => 'number', 'default' => '5', 'section' => 'downloads'],
        'download_ready_text'        => ['label' => 'Tiklama Oncesi Metin', 'type' => 'string', 'default' => 'İndirmek için tıklayınız', 'section' => 'downloads'],
        'download_wait_text'         => ['label' => 'Geri Sayım Metni', 'type' => 'string', 'default' => 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz', 'section' => 'downloads'],
        'download_show_counts'       => ['label' => 'Link Bazli İndirme Sayacıni Goster', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_done_text'         => ['label' => 'Hazır Metni', 'type' => 'string', 'default' => 'İndirme linkiniz hazır, indirmek için tıklayın', 'section' => 'downloads'],
        'download_redirect_page_enabled' => ['label' => 'Yönlendirme Sayfası Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'downloads', 'tooltip' => 'Kapalıysa geçerli indirme bağlantıları sayaç güncellemesinden sonra doğrudan hedef adrese yönlendirilir.'],
        'download_redirect_auto_enabled' => ['label' => 'Otomatik Yönlendirme Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_redirect_show_target_url' => ['label' => 'Hedef URL Görünsün', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_redirect_kicker' => ['label' => 'Üst Etiket Metni', 'type' => 'string', 'default' => 'Güvenli geçiş kontrolü', 'section' => 'downloads'],
        'download_redirect_title' => ['label' => 'Sayfa Başlığı', 'type' => 'string', 'default' => 'Dış indirme bağlantısı', 'section' => 'downloads'],
        'download_redirect_intro' => ['label' => 'Açıklama Metni', 'type' => 'text', 'default' => 'Dosya site dışında barındırılıyor. Devam etmeden önce hedef alan adını ve bağlantı bilgisini kontrol edin.', 'section' => 'downloads'],
        'download_redirect_host_label' => ['label' => 'Hedef Alan Adı Etiketi', 'type' => 'string', 'default' => 'Hedef alan adı', 'section' => 'downloads'],
        'download_redirect_link_label' => ['label' => 'Bağlantı Etiketi', 'type' => 'string', 'default' => 'Bağlantı', 'section' => 'downloads'],
        'download_redirect_topic_label' => ['label' => 'İçerik Etiketi', 'type' => 'string', 'default' => 'İçerik', 'section' => 'downloads'],
        'download_redirect_protocol_label' => ['label' => 'Protokol Etiketi', 'type' => 'string', 'default' => 'Protokol', 'section' => 'downloads'],
        'download_redirect_safety_domain_text' => ['label' => 'Güvenlik Maddesi 1', 'type' => 'string', 'default' => 'Hedef domain açıkça gösteriliyor', 'section' => 'downloads'],
        'download_redirect_safety_count_text' => ['label' => 'Güvenlik Maddesi 2', 'type' => 'string', 'default' => 'İndirme sayacı yalnızca devam edince güncellenir', 'section' => 'downloads'],
        'download_redirect_safety_external_text' => ['label' => 'Güvenlik Maddesi 3', 'type' => 'string', 'default' => 'Dış sitenin güvenliği size ait kontroldedir', 'section' => 'downloads'],
        'download_redirect_note' => ['label' => 'Alt Not', 'type' => 'text', 'default' => 'Devam ettiğinizde indirme sayacı güncellenecek ve yeni hedefe yönlendirileceksiniz.', 'section' => 'downloads'],
        'download_redirect_timer_template' => ['label' => 'Sayaç Metni Şablonu', 'type' => 'string', 'default' => '{{seconds}} saniye içinde otomatik yönlendirileceksiniz.', 'section' => 'downloads', 'tooltip' => '{{seconds}} değişkeni kalan saniye ile değiştirilir.'],
        'download_redirect_primary_label' => ['label' => 'Birincil Buton Metni', 'type' => 'string', 'default' => 'Hedefe Git', 'section' => 'downloads'],
        'download_redirect_primary_countdown_label' => ['label' => 'Geri Sayım Buton Metni', 'type' => 'string', 'default' => 'Hedefe Git ({{seconds}})', 'section' => 'downloads'],
        'download_redirect_redirecting_label' => ['label' => 'Yönleniyor Buton Metni', 'type' => 'string', 'default' => 'Yönlendiriliyor...', 'section' => 'downloads'],
        'download_redirect_secondary_label' => ['label' => 'İkincil Buton Metni', 'type' => 'string', 'default' => 'Konuya Dön', 'section' => 'downloads'],
        'download_redirect_default_link_name' => ['label' => 'Varsayılan Link Adı', 'type' => 'string', 'default' => 'Harici kaynak', 'section' => 'downloads'],
        'download_redirect_default_topic_title' => ['label' => 'Varsayılan İçerik Adı', 'type' => 'string', 'default' => 'Konu', 'section' => 'downloads'],
        'download_redirect_missing_message' => ['label' => 'Bulunamadı Hata Metni', 'type' => 'string', 'default' => 'İndirme bağlantısı bulunamadı veya kaldırılmış.', 'section' => 'downloads'],
        'download_redirect_invalid_message' => ['label' => 'Geçersiz Link Hata Metni', 'type' => 'string', 'default' => 'Geçersiz indirme bağlantısı.', 'section' => 'downloads'],
        'download_redirect_error_message' => ['label' => 'Genel Hata Metni', 'type' => 'string', 'default' => 'İndirme işlemi sırasında bir hata oluştu.', 'section' => 'downloads'],

        'download_access_mode' => [
            'label' => 'İndirme Erişim Kilidi',
            'type' => 'select',
            'default' => 'public',
            'section' => 'downloads',
            'options' => [
                'public' => 'Herkese Açık',
                'members' => 'Sadece Giriş Yapan Üyeler',
                'members_comment' => 'Üyelik + Yorum Şartlı',
            ],
            'tooltip' => 'Konu detayındaki indirme kartlarının kimlere açık olacağını belirler.',
        ],
        'download_access_comment_requirement' => [
            'label' => 'Yorum Doğrulaması',
            'type' => 'select',
            'default' => 'submitted',
            'section' => 'downloads',
            'options' => [
                'submitted' => 'Yorum Gönderimi Yeterli',
                'approved' => 'Yorum Onayı Gerekli',
            ],
            'tooltip' => 'Üyelik + Yorum Şartlı modunda yorumun ne zaman geçerli sayılacağını belirler.',
        ],
        'download_access_login_message' => [
            'label' => 'Giriş Kilidi Mesajı',
            'type' => 'string',
            'default' => 'Önce giriş yapın, sonra bir yorum gönderin; kilit otomatik açılır.',
            'section' => 'downloads',
        ],
        'download_access_comment_message' => [
            'label' => 'Yorum Kilidi Mesajı',
            'type' => 'string',
            'default' => 'Önce bir yorum gönderin; kilit otomatik açılır.',
            'section' => 'downloads',
        ],
        'download_access_locked_button_text' => [
            'label' => 'Kilitli Kart Buton Metni',
            'type' => 'string',
            'default' => 'Kilidi Aç',
            'section' => 'downloads',
        ],
        'download_access_comment_cta_label' => [
            'label' => 'Yorum Çağrı Metni',
            'type' => 'string',
            'default' => 'Yorumlara Git',
            'section' => 'downloads',
        ],
        'download_access_open_auth_popup' => [
            'label' => 'Kilitte Giriş/Kayıt Penceresini Aç',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_focus_comment_form' => [
            'label' => 'Yorum Şartında Forma Odaklan',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_unlock_after_auth' => [
            'label' => 'Giriş Sonrası Sayfayı Yenile',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_unlock_after_comment' => [
            'label' => 'Yorum Sonrası Sayfayı Yenile',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_auth_modal_title' => [
            'label' => 'Giriş/Kayıt Penceresi Başlığı',
            'type' => 'string',
            'default' => 'İndirme linklerini açmak için giriş yapın',
            'section' => 'downloads',
        ],
        'download_access_auth_login_label' => [
            'label' => 'Giriş Sekmesi Metni',
            'type' => 'string',
            'default' => 'Giriş Yap',
            'section' => 'downloads',
        ],
        'download_access_auth_register_label' => [
            'label' => 'Kayıt Sekmesi Metni',
            'type' => 'string',
            'default' => 'Kayıt Ol',
            'section' => 'downloads',
        ],
        'download_access_auth_success_message' => [
            'label' => 'Giriş Başarı Mesajı',
            'type' => 'string',
            'default' => 'Oturum başarıyla açıldı. Kilitli indirme kartları güncelleniyor.',
            'section' => 'downloads',
        ],

        'max_upload_size'        => ['label' => 'Maks. Dosya Boyutu (MB)',    'type' => 'number', 'default' => '50',  'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi uzerinden yuklenebilecek tekil dosya boyutu. 0 sinirsiz anlamina gelir.'],
        'upload_path'            => ['label' => 'Yukleme Dizini',             'type' => 'string', 'default' => 'uploads/',  'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi kok dizini. Guvenlik icin proje icindeki goreli dizin kullanin.'],
        'allowed_file_ext'       => ['label' => 'Izinli Dosya Uzantilari',    'type' => 'string', 'default' => 'jpg,jpeg,png,gif,webp,pdf,zip,rar,7z,txt,md,json,csv,docx,xlsx,pptx,mp4,webm,mp3,wav', 'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi icin kabul edilecek guvenli uzantilari virgul ile ayirin. Script ve calistirilabilir uzantilar kabul edilmez.'],
        'media_default_folder'   => ['label' => 'Varsayilan Yukleme Klasoru', 'type' => 'select', 'default' => 'genel', 'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'options' => ['genel' => 'Genel', 'konu' => 'Konu', 'profil' => 'Profil'], 'tooltip' => 'Dosya Yoneticisi acilisinda secili dizin yokken yukleme formunun hedef klasoru.'],

        // -- Mod Yukle (Kullanici) ------------------------------
        'user_upload_enabled'    => ['label' => 'Kullanicilar Mod Yukleyebilir', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Genel', 'tooltip' => 'Kullanici tarafindaki Mod Yukle sayfasini aktif eder veya tamamen kapatir.'],
        'user_upload_require_approval' => ['label' => 'Modlar Onay Gerektirir', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Akış', 'tooltip' => 'Acikken kullanici gonderileri yayinlanmadan once moderator/admin onayina duser.'],
        'user_upload_default_status' => ['label' => 'Onaysiz Gonderim Varsayilan Durumu', 'type' => 'select', 'default' => 'published', 'section' => 'content_moderation', 'group' => 'Akış', 'options' => ['published' => 'Yayinda', 'draft' => 'Taslak'], 'tooltip' => 'Onay zorunlu degilse yeni kullanici modlarinin hangi durumla kaydedilecegini belirler.'],
        'user_upload_require_cover' => ['label' => 'Üst Resim Zorunlu', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod gonderirken kapak/ust gorsel yuklemeyi zorunlu hale getirir.'],
        'user_upload_require_gallery' => ['label' => 'En Az 1 Galeri Resmi Zorunlu', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Galeri adiminda en az bir mod gorseli yuklenmeden gonderime izin vermez.'],
        'user_upload_require_author' => ['label' => 'Yapimci Alani Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod yuklerken yapimci bilgisini doldurmak zorunda kalir.'],
        'user_upload_require_version' => ['label' => 'Oyun Surumu Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod yuklerken uyumlu oyun surumunu doldurmak zorunda kalir.'],
        'user_upload_require_download_link' => ['label' => 'Indirme Linki Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'En az bir indirme kaynagi girilmeden gonderime izin verilmez.'],
        'user_upload_min_title_length' => ['label' => 'Minimum Baslik Uzunlugu', 'type' => 'number', 'default' => '3', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod basliginin kabul edilmesi icin gereken en az karakter sayisi.'],
        'user_upload_max_title_length' => ['label' => 'Maksimum Baslik Uzunlugu', 'type' => 'number', 'default' => '150', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod basliginda izin verilen en fazla karakter sayisi.'],
        'user_upload_min_content_length' => ['label' => 'Minimum Aciklama Uzunlugu', 'type' => 'number', 'default' => '10', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod aciklamasinin kabul edilmesi icin gereken en az metin uzunlugu.'],
        'user_upload_max_images' => ['label' => 'Maks. Resim Sayisi', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Bir mod gonderisinde yuklenebilecek maksimum galeri gorseli sayisi.'],
        'user_upload_cover_max_size_mb' => ['label' => 'Kapak Maks. Boyut (MB)', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak gorseli icin izin verilen maksimum dosya boyutu.'],
        'user_upload_gallery_max_size_mb' => ['label' => 'Galeri Gorsel Maks. Boyut (MB)', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Her bir galeri gorseli icin izin verilen maksimum dosya boyutu.'],
        'user_upload_allowed_image_ext' => ['label' => 'Izinli Gorsel Uzantilari', 'type' => 'string', 'default' => 'jpg,jpeg,png,webp', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri yuklemelerinde kabul edilecek gorsel uzantilarini virgulle ayirin.'],
        'user_upload_image_min_width' => ['label' => 'Gorsel Minimum Genislik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin minimum genislik. 0 sinir yok demektir.'],
        'user_upload_image_min_height' => ['label' => 'Gorsel Minimum Yukseklik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin minimum yukseklik. 0 sinir yok demektir.'],
        'user_upload_image_max_width' => ['label' => 'Gorsel Maksimum Genislik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin maksimum genislik. 0 sinir yok demektir.'],
        'user_upload_image_max_height' => ['label' => 'Gorsel Maksimum Yukseklik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin maksimum yukseklik. 0 sinir yok demektir.'],
        'user_upload_allow_video_url' => ['label' => 'Video URL Alanina Izin Ver', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Video ve Linkler', 'tooltip' => 'Kullanici formunda tanitim videosu URL alani gorunsun ve kaydedilebilsin.'],
        'user_upload_allowed_video_hosts' => ['label' => 'Izinli Video Saglayicilari', 'type' => 'string', 'default' => 'youtube.com,youtu.be,vimeo.com', 'section' => 'user_uploads', 'group' => 'Video ve Linkler', 'tooltip' => 'Virgul ile ayirin. Bos birakilirsa tum video URLleri kabul edilir.'],
        'user_upload_max_size_mb' => ['label' => 'Maks. Dosya Boyutu (MB)', 'type' => 'number', 'default' => '50', 'section' => 'user_uploads', 'group' => 'Limitler', 'tooltip' => 'Opsiyonel mod dosyasi/ek dosya yuklemesi icin izin verilen maksimum boyut.'],
        'user_upload_hourly_limit' => ['label' => 'Saatlik Mod Gonderim Limiti', 'type' => 'number', 'default' => '0', 'section' => 'rate_limit', 'group' => 'Gonderimler', 'tooltip' => 'Bir kullanici 1 saat icinde en fazla kac mod gonderebilir. 0 = sinirsiz.'],
        'user_upload_daily_limit' => ['label' => 'Gunluk Mod Gonderim Limiti', 'type' => 'number', 'default' => '0', 'section' => 'rate_limit', 'group' => 'Gonderimler', 'tooltip' => 'Bir kullanici 24 saat icinde en fazla kac mod gonderebilir. 0 = sinirsiz.'],
        'user_upload_block_duplicate_titles' => ['label' => 'Ayni Baslikla Tekrar Gonderimi Engelle', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Ayni kullanicinin ayni baslikla tekrar mod gondermesini engeller.'],
        'user_upload_default_content_align' => ['label' => 'Varsayilan Aciklama Hizasi', 'type' => 'select', 'default' => 'center', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'options' => ['left' => 'Sol', 'center' => 'Orta', 'right' => 'Sag'], 'tooltip' => 'Kullanici aciklama metni icin varsayilan hizalamayi belirler.'],
        'user_upload_submission_notice' => ['label' => 'Gonderim Sonrasi Bilgilendirme Metni', 'type' => 'text', 'default' => 'Onay durumunu Profil > Konularim menusunden takip edebilirsiniz.', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Basarili gonderimden sonra kullaniciya gosterilecek takip/bilgilendirme metni.'],
        'user_upload_lock_after_submit' => ['label' => 'Basarili Gonderimden Sonra Butonu Kilitle', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Basarili gonderimden sonra tekrar tiklamayi ve cift gonderimi onlemek icin butonu kilitler.'],
        'user_upload_wizard_enabled' => ['label' => 'Wizard Arayuzu Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Mod yukleme formunu adim adim wizard arayuzuyle gosterir.'],
        'user_upload_allow_step_skip' => ['label' => 'Wizard Adim Atlamaya Izin Ver', 'type' => 'bool', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Acikken kullanici zorunlu kontrolleri tamamlamadan wizard adimlari arasinda gezebilir.'],
        'user_upload_show_profile_followup' => ['label' => 'Son Adimda Profil Takip Kutusu Goster', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Onay adiminda kullaniciya gonderisini profilinden takip edebilecegini hatirlatan kutuyu gosterir.'],
        'user_upload_show_profile_button' => ['label' => 'Konularima Git Butonu Goster', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Profil takip kutusunda Konularima Git baglantisini gosterir veya gizler.'],

        // -- Icerik Moderasyonu -------------------------------
        'content_moderation_enabled' => ['label' => 'İçerik Moderasyonu Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Akış', 'tooltip' => 'Kapalıysa otomatik yasak kelime taraması ve otomatik flag aksiyonları devre dışı kalır. Onay ve zorunlu alan ayarları ayrıca uygulanmaya devam eder.'],
        'content_moderation_blocked_words' => ['label' => 'Yasak Kelime Listesi', 'type' => 'text', 'default' => '', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi', 'tooltip' => 'Virgül veya satır ile ayırın. Başlık ve açıklama metninde aranır.'],
        'content_moderation_blocked_words_action' => ['label' => 'Yasak Kelime Aksiyonu', 'type' => 'select', 'default' => 'draft', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi', 'options' => ['draft' => 'Taslak + Moderasyon Bayrağı', 'reject' => 'Gönderimi Reddet', 'flag' => 'Yayına Al + Moderasyon Bayrağı'], 'tooltip' => 'Yasak kelime bulunduğunda konuya uygulanacak davranışı belirler.'],
        'content_moderation_blocked_words_message' => ['label' => 'Reddetme Mesajı', 'type' => 'string', 'default' => 'İçerikte izin verilmeyen kelime bulundu. Lütfen metni düzenleyin.', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi'],
        'content_moderation_flag_note' => ['label' => 'Moderasyon Bayrağı Notu', 'type' => 'string', 'default' => 'Otomatik içerik moderasyonu incelemesi gerekiyor.', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi'],

        // -- Rota Filtreleri -------------------------------
        'route_topic_prefix'            => ['label' => 'Konu URL On Eki',                     'type' => 'string', 'default' => 'konu',      'section' => 'route_filters'],
        'route_category_prefix'         => ['label' => 'Kategori URL On Eki',                 'type' => 'string', 'default' => 'kategori',  'section' => 'route_filters'],
        'route_category_list_prefix'    => ['label' => 'Genel Kategori Listesi URL On Eki',   'type' => 'string', 'default' => 'kategori',  'section' => 'route_filters', 'tooltip' => 'Tum kategorilerin listelendigi ana kategori sayfasinin URL on eki. Ornek: /kategori veya /kategoriler'],
        'route_profile_prefix'          => ['label' => 'Profil URL On Eki',                   'type' => 'string', 'default' => 'profil',    'section' => 'route_filters'],
        'route_login_path'              => ['label' => 'Giris Sayfasi URL Yolu',              'type' => 'string', 'default' => 'giris',            'section' => 'route_filters', 'tooltip' => 'Ornek: /giris'],
        'route_register_path'           => ['label' => 'Kayit Sayfasi URL Yolu',              'type' => 'string', 'default' => 'kayit',            'section' => 'route_filters', 'tooltip' => 'Ornek: /kayit'],
        'route_logout_path'             => ['label' => 'Cikis Islem URL Yolu',                'type' => 'string', 'default' => 'cikis',            'section' => 'route_filters', 'tooltip' => 'Ornek: /cikis'],
        'route_forgot_password_path'    => ['label' => 'Sifremi Unuttum URL Yolu',            'type' => 'string', 'default' => 'sifremi-unuttum',  'section' => 'route_filters', 'tooltip' => 'Ornek: /sifremi-unuttum'],
        'route_reset_password_path'     => ['label' => 'Sifre Sifirlama URL Yolu',            'type' => 'string', 'default' => 'sifre-sifirla',    'section' => 'route_filters', 'tooltip' => 'Ornek: /sifre-sifirla'],
        'route_notifications_path'      => ['label' => 'Bildirimler URL Yolu',                'type' => 'string', 'default' => 'bildirimler',      'section' => 'route_filters', 'tooltip' => 'Ornek: /bildirimler'],
        'route_messages_path'           => ['label' => 'Mesajlar URL Yolu',                   'type' => 'string', 'default' => 'mesajlar',         'section' => 'route_filters', 'tooltip' => 'Ornek: /mesajlar'],
        'route_leaderboard_path'        => ['label' => 'Liderlik URL Yolu',                   'type' => 'string', 'default' => 'liderlik',         'section' => 'route_filters', 'tooltip' => 'Ornek: /liderlik'],
        'route_ban_appeals_path'        => ['label' => 'Ban Itiraz URL Yolu',                 'type' => 'string', 'default' => 'ban-itiraz',       'section' => 'route_filters', 'tooltip' => 'Ornek: /ban-itiraz'],
        'route_contact_path'            => ['label' => 'Iletisim URL Yolu',                   'type' => 'string', 'default' => 'iletisim',         'section' => 'route_filters', 'tooltip' => 'Ornek: /iletisim'],
        'route_upload_topic_path'       => ['label' => 'Konu Yukle URL Yolu',                 'type' => 'string', 'default' => 'konu-yukle',       'section' => 'route_filters', 'tooltip' => 'Ornek: /konu-yukle'],
        'route_edit_topic_path'         => ['label' => 'Konu Duzenle URL Yolu',               'type' => 'string', 'default' => 'konu-duzenle',     'section' => 'route_filters', 'tooltip' => 'Ornek: /konu-duzenle'],
        'route_download_path'           => ['label' => 'Indirme URL Yolu',                    'type' => 'string', 'default' => 'indir',            'section' => 'route_filters', 'tooltip' => 'Ornek: /indir'],
        'route_events_path'             => ['label' => 'Etkinlik Merkezi URL Yolu',           'type' => 'string', 'default' => 'events',           'section' => 'route_filters', 'tooltip' => 'Events alt sayfalari bu taban yola baglanir. Ornek: /events, /events/wheel'],
        'route_www_redirect'            => ['label' => 'WWW Yonlendirmesi',                   'type' => 'select', 'default' => 'none',      'section' => 'route_filters', 'options' => ['none' => 'Islem Yok', 'www' => 'WWW Zorla (www.site.com)', 'non-www' => 'WWW Kaldir (site.com)']],
        'route_https_redirect'          => ['label' => 'HTTPS Zorla (SSL)',                   'type' => 'bool',   'default' => '0',         'section' => 'route_filters'],
        'route_hide_index_php'          => ['label' => 'Ana Sayfa URL\'sinde index.php Gizle',  'type' => 'bool',   'default' => '0',         'section' => 'route_filters', 'tooltip' => 'Etkinleştirildiğinde, ana sayfa https://site.example/ şeklinde görünür, https://site.example/index.php yerine'],
        'route_trailing_slash'          => ['label' => 'Sondaki Slash (/) Yonlendirmesi',     'type' => 'select', 'default' => 'none',      'section' => 'route_filters', 'options' => ['none' => 'Islem Yok', 'add' => 'Sona Slash Ekle (/kategori/)', 'remove' => 'Sondaki Slash\'i Kaldir (/kategori)']],
        'route_topic_id_suffix'         => ['label' => 'Konu URL Sonuna ID Ekle',              'type' => 'bool',   'default' => '1',         'section' => 'route_filters', 'tooltip' => 'Etkinse konu URLleri /konu/slug-id formatinda uretilir.'],
        'route_slug_format'             => ['label' => 'URL Slug Formati (Bosluklar Icin)',   'type' => 'select', 'default' => 'dash',      'section' => 'route_filters', 'options' => ['dash' => 'Tire (-) ornek-baslik', 'underscore' => 'Alt Cizgi (_) ornek_baslik']],
        'route_case_sensitive'          => ['label' => 'Harf Buyuklugu (Case Sensitivity)',   'type' => 'select', 'default' => 'lowercase', 'section' => 'route_filters', 'options' => ['sensitive' => 'Duyarli (Orijinal Birak)', 'lowercase' => 'Tumunu Kucult (SEO Onerisi)']],
        'route_url_max_length'          => ['label' => 'Maksimum URL Uzunlugu',               'type' => 'number', 'default' => '200',       'section' => 'route_filters', 'tooltip' => 'Slug olusturulurken bu degerden sonrasi kesilir.'],
        'route_pagination_format'       => ['label' => 'Sayfalama Formati',                   'type' => 'select', 'default' => 'query',     'section' => 'route_filters', 'options' => ['query' => 'Query String (?page=2)', 'path' => 'Path Bazi (/sayfa/2)']],
        'route_sort_format'             => ['label' => 'Siralama Formati',                    'type' => 'select', 'default' => 'query',     'section' => 'route_filters', 'options' => ['query' => 'Query String (?sort=newest)', 'path' => 'Path Bazi (/siralama/yeni)']],


        // -- Konu Yonetimi --------------------------------

        // -- Bildirim Sistemi ------------------------------
        'notif_center_enabled' => ['label' => 'Bildirim Merkezi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Kapatılırsa kullanıcı bildirim API çıktısı sessize alınır ve yeni bildirim gönderimi durdurulur.'],
        'notif_allow_global_broadcasts' => ['label' => 'Genel Yayınlara İzin Ver', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Kapatılırsa hedef kullanıcı seçilmeden tüm kullanıcılara bildirim gönderilemez.'],
        'notif_allow_direct_messages' => ['label' => 'Kullanıcıya Özel Bildirimlere İzin Ver', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Kapatılırsa belirli kullanıcı ID hedefli bildirim gönderilemez.'],
        'notif_respect_user_preferences' => ['label' => 'Kullanıcı Tercihlerini Uygula', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Kullanıcıların kapattığı bildirim tipleri kullanıcı sayfası ve üst menü bildirim API çıktısında gizlenir.'],
        'notif_show_header_badge' => ['label' => 'Üst Menü Rozetini Göster', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Kapatılırsa okunmamış sayı rozeti gizlenir, ancak bildirim menüsü görüntülenebilir.'],
        'notif_auto_mark_link_click' => ['label' => 'Linke Tıklayınca Okundu Yap', 'type' => 'bool', 'default' => '1', 'section' => 'notifications', 'tooltip' => 'Bildirim linki açıldığında bildirim otomatik okundu olarak işaretlenir.'],
        'notif_enable_read_more' => ['label' => 'Uzun Mesajlarda Genişletme Butonu', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_empty_state_tips' => ['label' => 'Boş Durum Yardım Metni Göster', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_dropdown_limit' => ['label' => 'Üst Menü Bildirim Limiti', 'type' => 'number', 'default' => '5', 'section' => 'notifications'],
        'notif_user_page_per_page' => ['label' => 'Kullanıcı Sayfası Sayfa Başına', 'type' => 'number', 'default' => '10', 'section' => 'notifications'],
        'notif_history_per_page' => ['label' => 'Admin Geçmiş Sayfa Başına', 'type' => 'number', 'default' => '20', 'section' => 'notifications'],
        'notif_user_message_lines' => ['label' => 'Kullanıcı Mesaj Satır Limiti', 'type' => 'number', 'default' => '3', 'section' => 'notifications'],
        'notif_history_message_preview' => ['label' => 'Admin Geçmiş Mesaj Önizleme', 'type' => 'number', 'default' => '140', 'section' => 'notifications'],
        'notif_max_title_length' => ['label' => 'Maksimum Başlık Uzunluğu', 'type' => 'number', 'default' => '120', 'section' => 'notifications'],
        'notif_max_message_length' => ['label' => 'Maksimum Mesaj Uzunluğu', 'type' => 'number', 'default' => '800', 'section' => 'notifications'],
        'notif_default_type' => ['label' => 'Varsayılan Bildirim Tipi', 'type' => 'select', 'default' => 'info', 'section' => 'notifications', 'options' => ['info' => 'Bilgi', 'success' => 'Başarılı', 'warning' => 'Uyarı', 'error' => 'Hata', 'system' => 'Sistem']],
        'notif_default_link' => ['label' => 'Varsayılan Bildirim Linki', 'type' => 'string', 'default' => '', 'section' => 'notifications'],
        'notif_require_https_links' => ['label' => 'Harici Linklerde HTTPS Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'notifications'],
        'notif_system_sender' => ['label' => 'Sistem Gönderen Adı', 'type' => 'string', 'default' => 'Sistem', 'section' => 'notifications'],
        'notif_retention_days' => ['label' => 'Saklama Süresi Gün', 'type' => 'number', 'default' => '30', 'section' => 'notifications'],
        'notif_welcome_enabled' => ['label' => 'Yeni Üye Hoş Geldin Bildirimi', 'type' => 'bool', 'default' => '0', 'section' => 'notifications'],
        'notif_welcome_msg' => ['label' => 'Hoş Geldin Mesajı', 'type' => 'text', 'default' => 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.', 'section' => 'notifications'],

        // -- Bildirim Olay Ayarları ------------------------
        'notif_events_enabled' => ['label' => 'Otomatik Olay Bildirimleri', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_event_comments_enabled' => ['label' => 'Yorum Olayları', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_event_mentions_enabled' => ['label' => 'Bahsetme Olayları', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_event_topic_moderation_enabled' => ['label' => 'Konu Moderasyon Olayları', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_event_favorites_enabled' => ['label' => 'Favori Konu Olayları', 'type' => 'bool', 'default' => '0', 'section' => 'notifications'],
        'notif_event_skip_actor' => ['label' => 'Kendi İşleminden Bildirim Alma', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_event_dedupe_enabled' => ['label' => 'Tekrar Bildirimlerini Engelle', 'type' => 'bool', 'default' => '1', 'section' => 'notifications'],
        'notif_email_channel_ready' => ['label' => 'E-posta Kuyruğu Aktif', 'type' => 'bool', 'default' => '0', 'section' => 'notifications', 'tooltip' => 'E-posta açık şablonlardan kuyruk kaydı oluşturur; cron worker bu kayıtları SMTP/mail ayarlarıyla gönderir.'],
        'notif_email_queue_max_attempts' => ['label' => 'E-posta Deneme Hakkı', 'type' => 'number', 'default' => '3', 'section' => 'notifications'],

        // -- Toast Bildirim Ayarları -----------------------
        'toast_enabled' => [
            'label'   => 'Toast Bildirim Sistemi',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Sayfa üzerinde anlık bildirim (toast) gösterimini açar veya kapatır'
        ],
        'toast_position' => [
            'label'   => 'Bildirim Konumu',
            'type'    => 'select',
            'options' => [
                'top-right'    => 'Sağ Üst',
                'top-left'     => 'Sol Üst',
                'top-center'   => 'Orta Üst',
                'bottom-right' => 'Sağ Alt',
                'bottom-left'  => 'Sol Alt',
                'bottom-center'=> 'Orta Alt',
            ],
            'default' => 'bottom-right',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin ekranın hangi köşesinde görüneceğini belirler'
        ],
        'toast_duration' => [
            'label'   => 'Varsayılan Gösterim Süresi (ms)',
            'type'    => 'number',
            'default' => '5000',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin ekranda kalma süresi (milisaniye). 1000 = 1 saniye'
        ],
        'toast_duration_success' => [
            'label'   => 'Başarı Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '4000',
            'section' => 'toast_notifications',
            'tooltip' => 'Başarılı işlem bildirimlerinin ekranda kalma süresi. 0 = varsayılanı kullan'
        ],
        'toast_duration_error' => [
            'label'   => 'Hata Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '8000',
            'section' => 'toast_notifications',
            'tooltip' => 'Hata bildirimlerinin ekranda kalma süresi. Hatalar daha uzun gösterilmelidir. 0 = varsayılanı kullan'
        ],
        'toast_duration_warning' => [
            'label'   => 'Uyarı Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '6000',
            'section' => 'toast_notifications',
            'tooltip' => 'Uyarı bildirimlerinin ekranda kalma süresi. 0 = varsayılanı kullan'
        ],
        'toast_theme' => [
            'label'   => 'Bildirim Teması',
            'type'    => 'select',
            'options' => [
                'default'  => 'Klasik (Koyu Arkaplan)',
                'colored'  => 'Renkli Dolgulu',
                'glass'    => 'Cam Efekti (Glassmorphism)',
                'minimal'  => 'Minimalist',
            ],
            'default' => 'default',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin görsel temasını belirler'
        ],
        'toast_animation' => [
            'label'   => 'Giriş Animasyonu',
            'type'    => 'select',
            'options' => [
                'slide'  => 'Kayma (Slide)',
                'fade'   => 'Solma (Fade)',
                'bounce' => 'Zıplama (Bounce)',
                'scale'  => 'Büyüme (Scale)',
            ],
            'default' => 'slide',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin ekrana giriş animasyonunu belirler'
        ],
        'toast_progress_bar' => [
            'label'   => 'İlerleme Çubuğu Göster',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin altında kalan süreyi gösteren bir ilerleme çubuğu gösterir'
        ],
        'toast_close_button' => [
            'label'   => 'Kapatma Butonu (×)',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin sağ üst köşesine bir kapatma (×) butonu ekler'
        ],
        'toast_max_visible' => [
            'label'   => 'Maks. Görünür Bildirim Sayısı',
            'type'    => 'number',
            'default' => '5',
            'section' => 'toast_notifications',
            'tooltip' => 'Ekranda aynı anda görünebilecek maksimum bildirim sayısı. Sınır aşılırsa en eski bildirim kapanır'
        ],
        'toast_stack_direction' => [
            'label'   => 'Yığılma Yönü',
            'type'    => 'select',
            'options' => [
                'down' => 'Aşağı Doğru (Yeni altta)',
                'up'   => 'Yukarı Doğru (Yeni üstte)',
            ],
            'default' => 'down',
            'section' => 'toast_notifications',
            'tooltip' => 'Yeni bildirimlerin mevcut bildirimlerin altına mı üstüne mi ekleneceğini belirler'
        ],
        'toast_click_to_close' => [
            'label'   => 'Tıklayarak Kapat',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin herhangi bir yerine tıklayarak kapatma imkanı sunar'
        ],
        'toast_pause_on_hover' => [
            'label'   => 'Üzerine Gelince Duraklat',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Fare bildirimin üzerine geldiğinde zamanlayıcı durur, ayrıldığında devam eder'
        ],

        // -- Dosya Yoneticisi / Resim Ayarlari -------------------
        'allowed_image_ext'      => ['label' => 'Izinli Gorsel Uzantilari',   'type' => 'string', 'default' => 'png,jpg,jpeg,webp,gif', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'auto_resize_images'     => ['label' => 'Otomatik Boyutlandir',       'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_resize_width'     => ['label' => 'Boyutlandirma Genişlik (px)','type' => 'number', 'default' => '1920', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_resize_height'    => ['label' => 'Boyutlandirma Yukseklik (px)','type' => 'number','default' => '1080', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_enabled'           => ['label' => 'WebP Donusumu Aktif',        'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_quality'           => ['label' => 'WebP Kalite (1-100)',        'type' => 'number', 'default' => '82',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_keep_original'     => ['label' => 'Orijinal Dosyayi Sakla',     'type' => 'bool',   'default' => '0',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'jpeg_quality'           => ['label' => 'JPEG Kalite (1-100)',        'type' => 'number', 'default' => '85',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'png_compression'        => ['label' => 'PNG Sıkıştırma (0-9)',      'type' => 'number', 'default' => '6',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_strip_metadata'   => ['label' => 'EXIF/Meta Veri Temizle',     'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_sharpen'          => ['label' => 'Boyutlandirmada Netlestir',  'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_enabled'      => ['label' => 'Otomatik Thumbnail Olustur', 'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_width'        => ['label' => 'Thumbnail Genişlik (px)',    'type' => 'number', 'default' => '400',  'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_height'       => ['label' => 'Thumbnail Yukseklik (px)',   'type' => 'number', 'default' => '300',  'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_crop'         => ['label' => 'Thumbnail Kirp (Cover)',     'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_enabled'      => ['label' => 'Filigran Ekle',             'type' => 'bool',   'default' => '0',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_text'         => ['label' => 'Filigran Metni',            'type' => 'string', 'default' => '',     'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_position'     => ['label' => 'Filigran Konumu',           'type' => 'select', 'default' => 'bottom-right', 'section' => 'file_manager', 'group' => 'Resim Ayarlari', 'options' => ['top-left' => 'Sol Ust', 'top-right' => 'Sağ Ust', 'bottom-left' => 'Sol Alt', 'bottom-right' => 'Sağ Alt', 'center' => 'Orta']],
        'watermark_opacity'      => ['label' => 'Filigran Saydamlik (%)',    'type' => 'number', 'default' => '30',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_font_size'    => ['label' => 'Filigran Yazi Boyutu (px)', 'type' => 'number', 'default' => '16',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],

        // -- E-posta --------------------------------------------
        'mail_driver'            => ['label' => 'E-posta Surucusu',       'type' => 'select', 'default' => 'smtp', 'section' => 'email', 'options' => ['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'mail' => 'PHP mail()']],
        'smtp_host'              => ['label' => 'SMTP Sunucu',            'type' => 'string', 'default' => 'localhost', 'section' => 'email'],
        'smtp_port'              => ['label' => 'SMTP Port',              'type' => 'number', 'default' => '587',       'section' => 'email'],
        'smtp_username'          => ['label' => 'SMTP Kullanici Adi',     'type' => 'string', 'default' => '',          'section' => 'email'],
        'smtp_password'          => ['label' => 'SMTP Sifre',             'type' => 'string', 'default' => '',          'section' => 'email'],
        'smtp_encryption'        => ['label' => 'SMTP Sifreleme',         'type' => 'select', 'default' => 'tls', 'section' => 'email', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Yok']],
        'mail_from_name'         => ['label' => 'Gonderici Adi',          'type' => 'string', 'default' => 'İçerik Topic', 'section' => 'email'],
        'mail_from_address'      => ['label' => 'Gonderici E-posta',      'type' => 'string', 'default' => 'noreply@topic.test', 'section' => 'email'],

        // -- Sistem ---------------------------------------------
        'maintenance_mode'       => ['label' => 'Bakim Modu',                'type' => 'bool',   'default' => '0',        'section' => 'general'],
        'maintenance_message'    => ['label' => 'Bakim Mesaji',              'type' => 'text',   'default' => 'Site bakim modundadir, lutfen daha sonra tekrar deneyin.', 'section' => 'general'],

        // -- Rate Limit -----------------------------------------
        'register_rate_limit'    => ['label' => 'Kayit Deneme Limiti (pencere basina)', 'type' => 'number', 'default' => '3', 'section' => 'rate_limit', 'tooltip' => 'Ayni IP adresi, kayit penceresi icinde en fazla bu kadar kayit istegi gonderebilir.'],
        'register_rate_window'   => ['label' => 'Kayit Penceresi (dakika)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Kayit deneme sayacinin kac dakikada sifirlanacagini belirler.'],
        'login_rate_limit'       => ['label' => 'Basarisiz Giris Limiti (pencere basina)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Ayni IP adresi, giris penceresi icinde en fazla bu kadar basarisiz giris deneyebilir.'],
        'login_rate_window'      => ['label' => 'Giris Penceresi (dakika)', 'type' => 'number', 'default' => '15', 'section' => 'rate_limit', 'tooltip' => 'Basarisiz giris sayacinin kac dakikada sifirlanacagini belirler.'],
        'password_reset_rate_limit' => ['label' => 'Sifre Sifirlama Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '3', 'section' => 'rate_limit', 'tooltip' => 'Ayni IP adresi pencere suresi icinde en fazla bu kadar sifre sifirlama istegi gonderebilir.'],
        'password_reset_rate_window' => ['label' => 'Sifre Sifirlama Penceresi (dakika)', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Sifre sifirlama istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'search_rate_limit'      => ['label' => 'Site Arama Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Ayni IP adresi pencere suresi icinde en fazla bu kadar arama istegi gonderebilir.'],
        'search_rate_window'     => ['label' => 'Site Arama Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Arama istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_topics_rate_limit'  => ['label' => 'Konu API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Konu listeleme API uclari icin pencere suresi icindeki maksimum istek sayisi.'],
        'api_topics_rate_window' => ['label' => 'Konu API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Konu API istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_messages_rate_limit' => ['label' => 'Mesaj API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Mesaj API uclari icin pencere suresi icindeki maksimum istek sayisi.'],
        'api_messages_rate_window' => ['label' => 'Mesaj API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Mesaj API istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_leaderboard_rate_limit' => ['label' => 'Leaderboard API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Leaderboard ve kullanici siralama API uclari icin maksimum istek sayisi.'],
        'api_leaderboard_rate_window' => ['label' => 'Leaderboard API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Leaderboard API istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_analytics_rate_limit' => ['label' => 'Analitik API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '120', 'section' => 'rate_limit', 'tooltip' => 'Analitik/track API uclari icin pencere suresi icindeki maksimum istek sayisi.'],
        'api_analytics_rate_window' => ['label' => 'Analitik API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Analitik API istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_favorite_rate_limit' => ['label' => 'Favori API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Favori ekleme ve cikarma API istekleri icin maksimum sayi.'],
        'api_favorite_rate_window' => ['label' => 'Favori API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Favori API istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_reports_rate_limit' => ['label' => 'Konu Sikayet API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Konu sikayet listeleme/okuma API istekleri icin maksimum sayi.'],
        'api_reports_rate_window' => ['label' => 'Konu Sikayet API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Konu sikayet API sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_report_submit_rate_limit' => ['label' => 'Konu Sikayet Gonderim Limiti (pencere basina)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Bir oturum penceresi icinde en fazla kac konu sikayeti gonderebilir.'],
        'api_report_submit_rate_window' => ['label' => 'Konu Sikayet Gonderim Penceresi (dakika)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Konu sikayet gonderim sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_user_reports_rate_limit' => ['label' => 'Kullanici Sikayet API Istek Limiti (pencere basina)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Kullanici sikayet listeleme/okuma API istekleri icin maksimum sayi.'],
        'api_user_reports_rate_window' => ['label' => 'Kullanici Sikayet API Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Kullanici sikayet API sayacinin kac dakikada sifirlanacagini belirler.'],
        'api_user_report_submit_rate_limit' => ['label' => 'Kullanici Sikayet Gonderim Limiti (pencere basina)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Bir oturum penceresi icinde en fazla kac kullanici sikayeti gonderebilir.'],
        'api_user_report_submit_rate_window' => ['label' => 'Kullanici Sikayet Gonderim Penceresi (dakika)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Kullanici sikayet gonderim sayacinin kac dakikada sifirlanacagini belirler.'],
        'download_count_rate_limit' => ['label' => 'Indirme Sayaci Yazma Limiti (pencere basina)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Ayni IP adresi ayni indirme kaydi icin pencere suresi icinde en fazla kac kez sayaca yazabilir.'],
        'download_count_rate_window' => ['label' => 'Indirme Sayaci Penceresi (dakika)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Indirme sayaci ayni IP icin bu sure sonunda tekrar artabilir.'],
        'comment_mention_rate_max' => ['label' => 'Mention Arama Limiti (pencere basina)', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Yorum mention arama istekleri icin maksimum sayi.'],
        'comment_mention_rate_window' => ['label' => 'Mention Arama Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Mention arama istek sayacinin kac dakikada sifirlanacagini belirler.'],
        'comment_edit_rate_max'  => ['label' => 'Yorum Duzenleme/Silme Limiti (pencere basina)', 'type' => 'number', 'default' => '20', 'section' => 'rate_limit', 'tooltip' => 'Yorum duzenleme ve silme istekleri icin maksimum sayi.'],
        'comment_edit_rate_window' => ['label' => 'Yorum Duzenleme/Silme Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Yorum duzenleme/silme sayacinin kac dakikada sifirlanacagini belirler.'],
        'comment_reaction_rate_max' => ['label' => 'Yorum Reaksiyon Limiti (pencere basina)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Yorum reaksiyon (like vb.) istekleri icin maksimum sayi.'],
        'comment_reaction_rate_window' => ['label' => 'Yorum Reaksiyon Penceresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Yorum reaksiyon sayacinin kac dakikada sifirlanacagini belirler.'],
        'comment_report_rate_max' => ['label' => 'Yorum Sikayet Gonderim Limiti (pencere basina)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Yorum sikayet gonderim istekleri icin maksimum sayi.'],
        'comment_report_rate_window' => ['label' => 'Yorum Sikayet Gonderim Penceresi (dakika)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Yorum sikayet sayacinin kac dakikada sifirlanacagini belirler.'],

        // -- Sosyal Medya ---------------------------------------
        'social_facebook'        => ['label' => 'Facebook URL',   'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_twitter'         => ['label' => 'Twitter/X URL',  'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_instagram'       => ['label' => 'Instagram URL',  'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_youtube'         => ['label' => 'YouTube URL',    'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_github'          => ['label' => 'GitHub URL',     'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_discord'         => ['label' => 'Discord URL',    'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_telegram'        => ['label' => 'Telegram URL',   'type' => 'string', 'default' => '', 'section' => 'social_features'],

        // -- Liderlik Tablosu -----------------------------------
        'leaderboard_enabled'           => ['label' => 'Sistem Aktif',                    'type' => 'bool',   'default' => '1',     'section' => 'leaderboard'],
        'leaderboard_disabled_message'  => ['label' => 'Sistem Kapalı Mesajı',            'type' => 'textarea', 'default' => 'Liderlik tablosu şu anda kapalı. Lütfen daha sonra tekrar kontrol edin.', 'section' => 'leaderboard', 'tooltip' => 'Sistem kapalıyken liderlik sayfasında kullanıcılara gösterilecek mesaj.'],
        'leaderboard_cache_ttl_daily'   => ['label' => 'Günlük Cache TTL (saniye)',       'type' => 'number', 'default' => '900',   'section' => 'leaderboard', 'tooltip' => 'Günlük liderlik tablosu cache süresi (varsayılan: 15 dakika)'],
        'leaderboard_cache_ttl_weekly'  => ['label' => 'Haftalık Cache TTL (saniye)',     'type' => 'number', 'default' => '3600',  'section' => 'leaderboard', 'tooltip' => 'Haftalık liderlik tablosu cache süresi (varsayılan: 1 saat)'],
        'leaderboard_cache_ttl_monthly' => ['label' => 'Aylık Cache TTL (saniye)',        'type' => 'number', 'default' => '21600', 'section' => 'leaderboard', 'tooltip' => 'Aylık liderlik tablosu cache süresi (varsayılan: 6 saat)'],
        'leaderboard_cache_ttl_quarterly' => ['label' => 'Quarterly Cache TTL (seconds)', 'type' => 'number', 'default' => '43200', 'section' => 'leaderboard', 'tooltip' => 'Cache lifetime for quarterly leaderboard snapshots'],
        'leaderboard_cache_ttl_yearly' => ['label' => 'Yearly Cache TTL (seconds)', 'type' => 'number', 'default' => '86400', 'section' => 'leaderboard', 'tooltip' => 'Cache lifetime for yearly leaderboard snapshots'],
        'leaderboard_cache_ttl_all_time' => ['label' => 'All Time Cache TTL (seconds)', 'type' => 'number', 'default' => '86400', 'section' => 'leaderboard', 'tooltip' => 'Cache lifetime for all-time leaderboard snapshots'],
        'leaderboard_min_topics'        => ['label' => 'Minimum Mod Sayısı',              'type' => 'number', 'default' => '1',     'section' => 'leaderboard', 'tooltip' => 'Liderlik tablosunda görünmek için gereken minimum mod sayısı'],
        'leaderboard_exclude_admins'    => ['label' => 'Adminleri Hariç Tut',             'type' => 'bool',   'default' => '1',     'section' => 'leaderboard', 'tooltip' => 'Admin kullanıcıları liderlik tablosundan hariç tut'],
        'leaderboard_show_sidebar'      => ['label' => 'Ana Sayfada Göster',              'type' => 'bool',   'default' => '1',     'section' => 'leaderboard', 'tooltip' => 'Ana sayfa sidebar\'ında liderlik tablosu widget\'ını göster'],
        'leaderboard_sidebar_limit'     => ['label' => 'Sidebar Limit',                   'type' => 'number', 'default' => '5',     'section' => 'leaderboard', 'tooltip' => 'Ana sayfa sidebar\'ında gösterilecek kullanıcı sayısı'],
        'leaderboard_show_profile'      => ['label' => 'Profil Sayfasında Göster',        'type' => 'bool',   'default' => '1',     'section' => 'leaderboard', 'tooltip' => 'Profil sayfasında liderlik tablosu widget\'ını göster'],
        'leaderboard_profile_limit'     => ['label' => 'Profil Sayfası Limit',            'type' => 'number', 'default' => '10',    'section' => 'leaderboard', 'tooltip' => 'Profil sayfasında gösterilecek kullanıcı sayısı'],
        'leaderboard_min_downloads'     => ['label' => 'Minimum İndirme Sayısı',          'type' => 'number', 'default' => '0',     'section' => 'leaderboard', 'tooltip' => 'Liderlik tablosunda görünmek için gereken minimum indirme sayısı'],
        'leaderboard_min_views'         => ['label' => 'Minimum Görüntülenme Sayısı',     'type' => 'number', 'default' => '0',     'section' => 'leaderboard', 'tooltip' => 'Liderlik tablosunda görünmek için gereken minimum görüntülenme sayısı'],
        'leaderboard_exclude_banned'    => ['label' => 'Yasaklı Kullanıcıları Hariç Tut', 'type' => 'bool',   'default' => '1',     'section' => 'leaderboard', 'tooltip' => 'Yasaklı kullanıcıları liderlik tablosundan hariç tut'],
        'leaderboard_reset_frequency'   => ['label' => 'Sıfırlama Sıklığı',               'type' => 'select', 'default' => 'never',  'section' => 'leaderboard', 'options' => ['never' => 'Asla', 'daily' => 'Günlük', 'weekly' => 'Haftalık', 'monthly' => 'Aylık'], 'tooltip' => 'Liderlik tablosu puanlarının sıfırlanma sıklığı'],

        // -- Kullanıcı Sistemi -----------------------------------
        'allow_registration'            => ['label' => 'Kayıt Olma İzni',                'type' => 'bool',   'default' => '1',     'section' => 'user_system'],
        'login_identifier_mode'         => [
            'label' => 'Giris Kimligi',
            'type' => 'select',
            'default' => 'email',
            'section' => 'user_system',
            'options' => [
                'email' => 'Sadece E-posta',
                'username' => 'Sadece Kullanici Adi',
                'both' => 'E-posta veya Kullanici Adi',
            ],
            'tooltip' => 'Kullanici girisinde hangi kimlik bilgisinin kullanilacagini belirler.',
        ],
        'password_min_length'           => ['label' => 'Minimum Şifre Uzunluğu',          'type' => 'number', 'default' => '8',     'section' => 'user_system', 'tooltip' => 'Kullanıcı şifrelerinin minimum karakter sayısı'],
        'password_require_uppercase'    => ['label' => 'Büyük Harf Gerekli',              'type' => 'bool',   'default' => '1',     'section' => 'user_system', 'tooltip' => 'Şifrelerde en az bir büyük harf zorunlu'],
        'password_require_numbers'      => ['label' => 'Sayı Gerekli',                    'type' => 'bool',   'default' => '1',     'section' => 'user_system', 'tooltip' => 'Şifrelerde en az bir sayı zorunlu'],
        'password_require_special'      => ['label' => 'Özel Karakter Gerekli',           'type' => 'bool',   'default' => '0',     'section' => 'user_system', 'tooltip' => 'Şifrelerde özel karakter zorunlu'],
        'password_expiry_days' => [
            'label' => 'Şifre Geçerlilik Süresi (Gün)',
            'type' => 'number',
            'default' => '90',
            'section' => 'user_system',
            'tooltip' => 'Kullanıcı şifrelerinin kaç gün sonra süresinin dolacağı. (0: Süresiz)'
        ],
        'session_timeout_minutes' => [
            'label' => 'Oturum Zaman Aşımı (Dakika)',
            'type' => 'number',
            'default' => '120',
            'section' => 'user_system',
            'tooltip' => 'Hareketsiz kalan oturumların kaç dakika sonra sonlanacağı.'
        ],
        'remember_session_timeout_minutes' => [
            'label' => 'Oturumu Acik Tut Suresi (Dakika)',
            'type' => 'number',
            'default' => '43200',
            'section' => 'user_system',
            'tooltip' => 'Oturumumu acik tut secildiginde kalici girisin kac dakika gecerli olacagi. 43200 = 30 gun.'
        ],

        // -- Konu Yönetimi -----------------------------------------
        'topic_min_title_length' => [
            'label' => 'Minimum Başlık Uzunluğu',
            'type' => 'number',
            'default' => '5',
            'section' => 'content_moderation',
            'group' => 'Kalite',
            'tooltip' => 'Yeni konu eklerken gereken minimum başlık karakter sayısı'
        ],
        'topic_require_excerpt' => [
            'label' => 'Kısa Açıklama Zorunlu',
            'type' => 'bool',
            'default' => '1',
            'section' => 'content_moderation',
            'group' => 'Kalite',
            'tooltip' => 'Yeni konu eklerken kısa açıklama alanının doldurulmasını zorunlu kılar'
        ],
        'topic_user_edit_requires_approval' => [
            'label' => 'Kullanıcı Düzenlemesi Onaya Düşsün',
            'type' => 'bool',
            'default' => '1',
            'section' => 'content_moderation',
            'group' => 'Akış',
            'tooltip' => 'Kullanıcı kendi konusunu düzenlediğinde değişikliğin yeniden moderasyon onayına düşmesini sağlar'
        ],
        'topic_health_scan_batch_size' => [
            'label' => 'Sağlık Taraması Parti Boyutu',
            'type' => 'number',
            'default' => '3',
            'section' => 'topic_management',
            'tooltip' => 'Konular sayfasındaki manuel sağlık taramasında tek istekte kontrol edilecek konu sayısını belirler'
        ],

        // -- Konu İçi Görünüm ------------------------------------
        'topic_detail_show_toolbar' => [
            'label' => 'Konu Üst Araç Çubuğu',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu başlığının altında tarih, sayaçlar ve hızlı aksiyonların yer aldığı araç çubuğunu gösterir'
        ],
        'topic_detail_show_info_panel' => [
            'label' => 'İçerik Bilgileri Paneli',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu sahibi, yayın tarihi, kategori ve benzeri bilgilerin yer aldığı paneli gösterir'
        ],
        'show_author_info' => [
            'label' => 'Yazar Bilgisi Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu içi bilgi panelinde içerik sahibini ve profil bağlantısını gösterir'
        ],
        'show_view_count' => [
            'label' => 'Görüntülenme Sayacı',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayında görüntülenme bilgisinin gösterilip gösterilmeyeceğini belirler'
        ],
        'show_download_count' => [
            'label' => 'İndirme Sayacı',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayındaki üst sayaçlarda toplam indirme bilgisini gösterir'
        ],
        'topic_detail_show_media' => [
            'label' => 'Medya Galerisini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu içindeki resim, video ve medya galeri bölümünü gösterir'
        ],
        'topic_detail_show_download_panel' => [
            'label' => 'İndirme Panelini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayında indirme bağlantıları panelinin görünürlüğünü yönetir'
        ],
        'show_related_topics' => [
            'label' => 'Benzer Konuları Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detay sayfasında aynı kategoriden benzer içerikleri gösterir'
        ],
        'topic_detail_related_limit' => [
            'label' => 'Benzer Konu Limiti',
            'type' => 'number',
            'default' => '4',
            'section' => 'topic_detail',
            'tooltip' => 'Benzer konular bölümünde gösterilecek maksimum içerik sayısını belirler'
        ],
        'topic_detail_show_tags' => [
            'label' => 'Etiketleri Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayındaki etiket listesinin görünürlüğünü yönetir'
        ],
        'topic_detail_comments_enabled' => [
            'label' => 'Konu Yorumlarını Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detay sayfasındaki yorum bölümünü tamamen gösterir veya gizler'
        ],

                // -- Performans -----------------------------------------
        'cache_enabled' => [
            'label' => 'Sistem Önbelleği Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'performance',
            'tooltip' => 'Veritabanı sorgularını ve yoğun sayfaları önbelleğe alır'
        ],
        'cache_ttl' => [
            'label' => 'Önbellek Süresi (Saniye)',
            'type' => 'number',
            'default' => '3600',
            'section' => 'performance',
            'tooltip' => 'Önbelleğin ne kadar süreyle geçerli olacağı'
        ],

        // -- Sosyal Özellikler ----------------------------------

                // -- İçerik Moderasyonu --------------------------------
        'auto_hide_reported' => [
            'label' => 'Raporlanan Konuları Gizle',
            'type' => 'bool',
            'default' => '1',
            'section' => 'moderation',
            'tooltip' => 'Rapor sayısı eşiği aştığında konuyu otomatik olarak gizler'
        ],
        'report_threshold' => [
            'label' => 'Gizleme Eşiği (Rapor Sayısı)',
            'type' => 'number',
            'default' => '5',
            'section' => 'moderation',
            'tooltip' => 'Bir konunun otomatik gizlenmesi için gereken rapor sayısı'
        ],

        // -- Analitik & İstatistikler ---------------------------

        // -- Cron Yönetimi ---------------------------------------
        'cron_enabled' => [
            'label' => 'Cron Görevleri Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'cron',
            'tooltip' => 'Tüm arka plan cron görevlerinin çalışmasına izin verir'
        ],
        'cron_php_binary' => [
            'label' => 'PHP CLI Yolu',
            'type' => 'string',
            'default' => '',
            'section' => 'cron',
            'tooltip' => 'Boş bırakılırsa otomatik olarak bulunan ilk uygun PHP CLI yolu kullanılır. Örn: /usr/bin/php8.2'
        ],
        'cron_secret_key' => [
            'label' => 'Cron Güvenlik Anahtarı (Secret)',
            'type' => 'string',
            'default' => 'turkmod_cron_123',
            'section' => 'cron',
            'tooltip' => 'Cron URL\'lerini dışarıdan tetiklemek için gereken güvenlik şifresi (?secret=...)'
        ],
        'cron_health_scan_interval' => [
            'label' => 'Konu Sağlık Taraması Sıklığı (Saat)',
            'type' => 'number',
            'default' => '24',
            'section' => 'cron',
            'tooltip' => 'Düzenli sağlık taramasının (link/resim kontrolü) saat cinsinden sıklığı'
        ],
        'cron_batch_size' => [
            'label' => 'Görev Başına İşlem Limiti (Batch Size)',
            'type' => 'number',
            'default' => '50',
            'section' => 'cron',
            'tooltip' => 'Cron her çalıştığında tek seferde maksimum kaç içeriğin işleneceği'
        ],
        'popup_announcement_enabled' => [
            'label' => 'Popup Duyuru Aktif',
            'type' => 'bool',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Girişte ziyaretçilere gösterilecek popup duyurusunu açar veya kapatır.'
        ],
        'popup_announcement_title' => [
            'label' => 'Duyuru Başlığı',
            'type' => 'string',
            'default' => 'Önemli Duyuru',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin üst kısmında görünecek başlık.'
        ],
        'popup_announcement_content' => [
            'label' => 'Duyuru İçeriği',
            'type' => 'richtext',
            'default' => '<p>Sitemize hoş geldiniz! Güncel paylaşımlarımıza göz atmayı unutmayın.</p>',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup içerisinde gösterilecek duyuru metni.'
        ],
        'popup_announcement_type' => [
            'label' => 'Duyuru Türü / Teması',
            'type' => 'select',
            'options' => [
                'info' => 'Bilgi (Mavi)',
                'success' => 'Başarı / Hediye (Yeşil)',
                'warning' => 'Uyarı (Sarı / Turuncu)',
                'danger' => 'Önemli / Kritik (Kırmızı)'
            ],
            'default' => 'info',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin renk şemasını ve ikon stilini belirler.'
        ],
        'popup_announcement_strict' => [
            'label' => 'Katı Kapatma Modu',
            'type' => 'bool',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Eğer aktif edilirse, kullanıcı popup dışına tıklayarak pencereyi kapatamaz. Sadece kapatma butonuna tıklaması gerekir.'
        ],
        'popup_announcement_timer' => [
            'label' => 'Otomatik Kapanma Süresi (Saniye)',
            'type' => 'number',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin kaç saniye sonra otomatik kapanacağını belirler. 0 girilirse otomatik kapanma devre dışı kalır.'
        ],
        'popup_announcement_button_text' => [
            'label' => 'Kapat Buton Metni',
            'type' => 'string',
            'default' => 'Kapat',
            'section' => 'popup_announcement',
            'tooltip' => 'Duyuruyu kapatmak için kullanılacak butonun metni.'
        ],
        'popup_announcement_action_text' => [
            'label' => 'Aksiyon Buton Metni (İsteğe Bağlı)',
            'type' => 'string',
            'default' => '',
            'section' => 'popup_announcement',
            'tooltip' => 'Yönlendirme için ek buton metni (boş bırakılırsa gösterilmez).'
        ],
        'popup_announcement_action_url' => [
            'label' => 'Aksiyon Buton Linki (İsteğe Bağlı)',
            'type' => 'string',
            'default' => '',
            'section' => 'popup_announcement',
            'tooltip' => 'Aksiyon butonuna tıklandığında gidilecek URL adresi (örn: https://site.com/sayfa).'
        ],
        'popup_announcement_target' => [
            'label' => 'Duyuru Hedef Kitlesi',
            'type' => 'select',
            'options' => [
                'all' => 'Hem Üyelere Hem Ziyaretçilere gösterilsin',
                'guests' => 'Sadece Ziyaretçilere Gösterilsin',
                'members' => 'Sadece Üyelere Gösterilsin'
            ],
            'default' => 'all',
            'section' => 'popup_announcement',
            'tooltip' => 'Duyurunun hangi kullanıcılara gösterileceğini belirler.'
        ],
        'popup_announcement_cookie_days' => [
            'label' => 'Duyuru Gösterim Sıklığı (Gün)',
            'type' => 'number',
            'default' => '1',
            'section' => 'popup_announcement',
            'tooltip' => 'Ziyaretçi duyuruyu kapattıktan sonra kaç gün boyunca tekrar görmesin? (0 girilirse her sayfa yenilemede tekrar gösterilir).'
        ],
    ];

    return $cache = adminNormalizeSettingDefinitions($definitions);
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @return array<string, array<string, mixed>>
 */
function adminNormalizeSettingDefinitions(array $definitions): array
{
    foreach ($definitions as $key => $definition) {
        if (trim((string) ($definition['tooltip'] ?? '')) === '') {
            $definitions[$key]['tooltip'] = adminDefaultSettingTooltip((string) $key, $definition);
        }
    }

    return $definitions;
}

/**
 * @param array<string, mixed> $definition
 */
function adminDefaultSettingTooltip(string $key, array $definition): string
{
    $label = trim((string) ($definition['label'] ?? $key));
    $type = (string) ($definition['type'] ?? 'string');
    $section = (string) ($definition['section'] ?? 'general');
    $sectionNames = [
        'general' => 'genel site davranışını',
        'seo' => 'SEO ve arama motoru görünürlüğünü',
        'appearance' => 'public görünüm ayarlarını',
        'lay_header' => 'header görünümünü',
        'lay_footer' => 'footer görünümünü',
        'lay_sidebar' => 'sidebar düzenini',
        'lay_menu' => 'navigasyon menüsünü',
        'moderation' => 'moderasyon kurallarını',
        'comments' => 'yorum sistemini',
        'downloads' => 'indirme deneyimini',
        'file_manager' => 'dosya yöneticisi davranışını',
        'user_uploads' => 'kullanıcı mod yükleme akışını',
        'route_filters' => 'public URL rota davranışını',
        'topic_management' => 'konu yönetimi akışını',
        'topic_detail' => 'public konu içi görünümünü',
        'notifications' => 'bildirim merkezi davranışını',
        'toast_notifications' => 'toast bildirim görünümünü',
        'email' => 'e-posta gönderim ayarlarını',
        'user_system' => 'kullanıcı sistemi erişim ve şifre politikalarını',
        'rate_limit' => 'rate limit güvenlik kurallarını',
        'leaderboard' => 'liderlik tablosu davranışını',
        'user_system' => 'kullanıcı sistemi erişim ve şifre politikalarını',
        'performance' => 'performans ayarlarını',
        'social_features' => 'sosyal özellikleri',
        'content_moderation' => 'içerik moderasyonunu',
        'cron' => 'zamanlanmış görevleri',
        'popup_announcement' => 'giriş popup duyurusu sistemini',
    ];
    $sectionText = $sectionNames[$section] ?? 'ilgili sistem davranışını';

    if ($type === 'bool') {
        return "{$label} seçeneğini açar veya kapatır; {$sectionText} doğrudan etkiler.";
    }

    if ($type === 'number') {
        return "{$label} için kullanılacak sayısal limiti veya süre değerini belirler.";
    }

    if ($type === 'select') {
        return "{$label} için kullanılacak seçenek değerini belirler; değişiklik {$sectionText} etkiler.";
    }

    if ($type === 'text') {
        return "{$label} alanında kullanılacak çok satırlı metin veya yapılandırma içeriğini belirler.";
    }

    if ($type === 'color') {
        return "{$label} için kullanılacak tema rengini belirler; geçerli renk değeri girilmelidir.";
    }

    if ($type === 'multicheck') {
        return "{$label} için aktif olacak seçenekleri belirler.";
    }

    return "{$label} değerini belirler; değişiklik {$sectionText} etkiler.";
}

/**
 * Render a single admin setting control with the shared admin UI contract.
 *
 * @param array<string, mixed> $definition
 * @param array<string, string> $settings
 */
function adminRenderSettingField(string $key, array $definition, array $settings, string $gridItemClass = ''): string
{
    $type = (string) ($definition['type'] ?? 'string');
    $label = (string) ($definition['label'] ?? $key);
    $tooltip = trim((string) ($definition['tooltip'] ?? ''));
    $value = (string) ($settings[$key] ?? ($definition['default'] ?? ''));
    $fieldId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?: $key;
    $classes = trim($gridItemClass . ' ' . (($type === 'text' || $type === 'richtext') ? 'admin-field-wide' : ''));

    $helpIcon = $tooltip !== ''
        ? ' <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '"></i>'
        : '';

    ob_start();
    ?>
    <div class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($type === 'bool'): ?>
            <label class="ui-admin-switch">
                <input type="checkbox" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                <span class="ui-admin-switch-label">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?>
                </span>
            </label>
        <?php elseif ($type === 'color'): ?>
            <?php
                $colorDefault = (string) ($definition['default'] ?? '#000000');
                $resolvedColor = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $colorDefault;
            ?>
            <label class="ui-admin-form-label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?></label>
            <div class="admin-color-field" data-color-field>
                <input id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" type="color" class="admin-color-input" value="<?= htmlspecialchars($resolvedColor, ENT_QUOTES, 'UTF-8') ?>" data-color-input>
                <div class="admin-color-meta">
                    <strong data-color-value><?= htmlspecialchars(strtoupper($resolvedColor), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>Mevcut renk<?= $value === '' ? ' (varsayılan)' : '' ?></span>
                </div>
                <span class="admin-color-default">Varsayılan: <?= htmlspecialchars(strtoupper($colorDefault), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php else: ?>
            <label class="ui-admin-form-label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?></label>
            <?php if ($type === 'text' || $type === 'richtext'): ?>
                <textarea id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" rows="3" class="ui-admin-form-control<?= ($type === 'richtext' || !empty($definition['rich'])) ? ' rich-editor' : '' ?>"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php elseif ($type === 'select'): ?>
                <select id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-form-select">
                    <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'multicheck'): ?>
                <?php $currentValues = array_map('trim', explode(',', $value !== '' ? $value : (string) ($definition['default'] ?? ''))); ?>
                <div class="admin-multicheck-group">
                    <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string) $optionValue, $currentValues, true) ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label"><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <input id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @param array<string, string> $settings
 * @param array<int, string>|null $keys
 */
function adminRenderSettingsGrid(array $definitions, array $settings, string $section, ?array $keys = null, string $gridClass = 'admin-settings-grid ui-grid'): string
{
    $allowedKeys = $keys !== null ? array_fill_keys($keys, true) : null;
    $html = '<div class="' . htmlspecialchars($gridClass, ENT_QUOTES, 'UTF-8') . '">';

    foreach ($definitions as $key => $definition) {
        if ((string) ($definition['section'] ?? '') !== $section) {
            continue;
        }
        if ($allowedKeys !== null && !isset($allowedKeys[$key])) {
            continue;
        }
        $html .= adminRenderSettingField((string) $key, $definition, $settings);
    }

    return $html . '</div>';
}

function adminRuntimeSchemaUpdatesAllowed(): bool
{
    return !function_exists('runtimeSchemaUpdatesAllowed') || runtimeSchemaUpdatesAllowed();
}

function adminContentModerationBlockedWords(array $settings): array
{
    $raw = (string) ($settings['content_moderation_blocked_words'] ?? '');
    if (trim($raw) === '') {
        return [];
    }

    $words = [];
    foreach (preg_split('/[\r\n,]+/u', $raw) ?: [] as $word) {
        $word = trim((string) $word);
        if ($word === '') {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
        $words[$key] = $word;
    }

    return array_values($words);
}

function adminContentModerationTextMatches(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }

    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    return stripos($haystack, $needle) !== false;
}

function adminContentModerationMatches(array $settings, string $title, string $content): array
{
    if ((string) ($settings['content_moderation_enabled'] ?? '1') !== '1') {
        return [];
    }

    $words = adminContentModerationBlockedWords($settings);
    if ($words === []) {
        return [];
    }

    $haystack = html_entity_decode(strip_tags($title . ' ' . $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $matched = [];
    foreach ($words as $word) {
        if (adminContentModerationTextMatches($haystack, (string) $word)) {
            $matched[] = (string) $word;
        }
    }

    return array_values(array_unique($matched));
}

function adminContentModerationDecision(array $settings, string $title, string $content): array
{
    $matches = adminContentModerationMatches($settings, $title, $content);
    if ($matches === []) {
        return [
            'matched' => false,
            'action' => 'none',
            'message' => '',
            'flags' => null,
        ];
    }

    $action = (string) ($settings['content_moderation_blocked_words_action'] ?? 'draft');
    if (!in_array($action, ['draft', 'reject', 'flag'], true)) {
        $action = 'draft';
    }

    $message = trim((string) ($settings['content_moderation_blocked_words_message'] ?? ''));
    if ($message === '') {
        $message = 'İçerikte izin verilmeyen kelime bulundu. Lütfen metni düzenleyin.';
    }

    $note = trim((string) ($settings['content_moderation_flag_note'] ?? ''));
    if ($note === '') {
        $note = 'Otomatik içerik moderasyonu incelemesi gerekiyor.';
    }

    return [
        'matched' => true,
        'action' => $action,
        'message' => $message,
        'flags' => [
            'source' => 'content_moderation',
            'type' => 'blocked_words',
            'action' => $action,
            'words' => $matches,
            'note' => $note,
            'created_at' => date(DATE_ATOM),
        ],
    ];
}

function ensureAdminSchema(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    if (!adminRuntimeSchemaUpdatesAllowed()) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) NOT NULL UNIQUE,
        setting_value LONGTEXT NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $legacyCategories = adminLegacyIdentifier('a2F0ZWdvcmlsZXI=');
    $legacyTopics = adminLegacyIdentifier('a29udWxhcg==');
    $legacySubcategories = adminLegacyIdentifier('YWx0X2thdGVnb3JpbGVy');
    $legacyTopicVersions = adminLegacyIdentifier('a29udV92ZXJzaW9ucw==');
    $legacyCategoryId = adminLegacyIdentifier('a2F0ZWdvcmlfaWQ=');
    $legacySubcategoryId = adminLegacyIdentifier('YWx0X2thdGVnb3JpX2lk');
    $legacyTopicId = adminLegacyIdentifier('a29udV9pZA==');
    $legacyDefaultCategoryId = adminLegacyIdentifier('ZGVmYXVsdF9rYXRlZ29yaV9pZA==');
    $legacyTrustedTopic = adminLegacyIdentifier('dHJ1c3RlZF9tb2Q=');

    adminRenameTableIfNeeded($pdo, $legacyCategories, 'categories');
    adminRenameTableIfNeeded($pdo, $legacyTopics, 'topics');
    adminDropTableIfExists($pdo, $legacyTopicVersions);
    adminDropTableIfExists($pdo, 'topic_versions');

    adminRenameColumnIfNeeded($pdo, 'topics', $legacyCategoryId, 'category_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'topics', $legacyTrustedTopic, 'trusted_topic', 'TINYINT(1) NOT NULL DEFAULT 0');
    adminDropColumnIfExists($pdo, 'topics', 'trusted_mod');
    adminDropColumnIfExists($pdo, 'topics', $legacySubcategoryId);
    adminDropColumnIfExists($pdo, 'topics', 'subcategory_id');
    adminDropTableIfExists($pdo, $legacySubcategories);
    adminRenameColumnIfNeeded($pdo, 'media_files', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NULL');
    adminRenameColumnIfNeeded($pdo, 'downloads', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'comments', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'ratings', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'reactions', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'reports', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NOT NULL');
    adminRenameColumnIfNeeded($pdo, 'bot_imports', $legacyTopicId, 'topic_id', 'BIGINT UNSIGNED NULL');

    $columns = [
        'parent_id' => 'BIGINT UNSIGNED NULL',
        'display_order' => 'INT NOT NULL DEFAULT 0',
        'seo_title' => 'VARCHAR(255) NULL',
        'seo_description' => 'TEXT NULL',
        'deleted_at' => 'TIMESTAMP NULL',
    ];

    foreach ($columns as $name => $definition) {
        if (!adminColumnExists($pdo, 'categories', $name)) {
            $safeName = adminSafeIdentifier($name);
            $pdo->exec("ALTER TABLE categories ADD COLUMN {$safeName} {$definition}");
        }
    }

    // Rename legacy portal_ columns to topic_ for live DB compatibility.
    $renameMap = [
    ];
    foreach ($renameMap as $old => $meta) {
        if (adminColumnExists($pdo, 'topics', $old) && !adminColumnExists($pdo, 'topics', $meta['new'])) {
            $pdo->exec("ALTER TABLE topics CHANGE COLUMN `{$old}` `{$meta['new']}` {$meta['type']}");
        }
    }

    // Recreate FULLTEXT index if it still references portal_descriptions
    try {
        $idxStmt = $pdo->query("SHOW INDEX FROM topics WHERE Key_name = 'topics_search_fulltext'");
        $idxCols = [];
        while ($r = $idxStmt->fetch()) { $idxCols[] = $r['Column_name']; }
        if (in_array('portal_descriptions', $idxCols, true)) {
            $pdo->exec("ALTER TABLE topics DROP INDEX topics_search_fulltext");
            $pdo->exec("ALTER TABLE topics ADD FULLTEXT INDEX topics_search_fulltext (title, topic_descriptions)");
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    // Ensure topics table has topic columns
    $topicColumns = [
        'author_topic' => 'VARCHAR(255) NULL AFTER slug',
        'topic_version' => 'VARCHAR(255) NULL AFTER author_topic',
        'topic_descriptions' => 'LONGTEXT NULL AFTER topic_version',
        'topic_download_links' => 'LONGTEXT NULL AFTER topic_descriptions',
        'primary_media_file_id' => 'BIGINT UNSIGNED NULL AFTER meta_description',
        'trusted_topic' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'last_checked_at' => 'TIMESTAMP NULL',
        'health_status' => "VARCHAR(32) NOT NULL DEFAULT 'unchecked'",
        'health_summary' => 'JSON NULL',
        'moderation_flags' => 'JSON NULL',
    ];

    foreach ($topicColumns as $name => $definition) {
        if (!adminColumnExists($pdo, 'topics', $name)) {
            $safeName = adminSafeIdentifier($name);
            $pdo->exec("ALTER TABLE topics ADD COLUMN {$safeName} {$definition}");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_download_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        topic_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        url VARCHAR(2048) NOT NULL,
        download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        display_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        last_checked_at TIMESTAMP NULL,
        last_status_code INT NULL,
        health_status VARCHAR(32) NOT NULL DEFAULT 'unchecked',
        last_health_message VARCHAR(255) NULL,
        last_final_url VARCHAR(2048) NULL,
        INDEX topic_download_links_topic_order_index (topic_id, display_order),
        CONSTRAINT topic_download_links_topic_id_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    topicRevisionEnsureSchema($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS request_rate_limits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(100) NOT NULL,
        rate_key VARCHAR(191) NOT NULL,
        attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
        first_attempt_at TIMESTAMP NULL,
        last_attempt_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY request_rate_limits_scope_key_unique (scope, rate_key),
        INDEX request_rate_limits_expires_index (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS application_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        level VARCHAR(20) NOT NULL,
        channel VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        context_json JSON NULL,
        ip_address VARCHAR(255) NULL,
        created_at TIMESTAMP NULL,
        INDEX application_logs_level_created_index (level, created_at),
        INDEX application_logs_channel_created_index (channel, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (function_exists('emailLogsEnsureSchema')) {
        emailLogsEnsureSchema($pdo);
    }

    // Drop legacy columns if they still exist
    adminDropColumnIfExists($pdo, 'topics', 'summary');
    adminDropColumnIfExists($pdo, 'topics', 'body');
    adminDropColumnIfExists($pdo, 'topics', 'topic_first_image');
    adminDropColumnIfExists($pdo, 'topics', 'topic_images_videos');
    adminDropColumnIfExists($pdo, 'topics', 'portal_detail_hero');
    adminDropColumnIfExists($pdo, 'topics', 'portal_images_videos');
    adminDropColumnIfExists($pdo, 'topics', 'topic_detail_hero');

    // Ensure comments table has parent_id for nested comments
    if (!adminColumnExists($pdo, 'comments', 'parent_id')) {
        $pdo->exec("ALTER TABLE comments ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER user_id");
        try { $pdo->exec("ALTER TABLE comments ADD CONSTRAINT comments_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE"); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    if (adminColumnExists($pdo, 'comments', 'user_id')) {
        try {
            $pdo->exec("ALTER TABLE comments MODIFY COLUMN user_id BIGINT UNSIGNED NULL");
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    try {
        $pdo->exec("ALTER TABLE comments DROP FOREIGN KEY comments_user_id_foreign");
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        $pdo->exec("ALTER TABLE comments ADD CONSTRAINT comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    $topicIndexStatements = [
        "ALTER TABLE topics ADD INDEX topics_status_deleted_downloads_index (status, deleted_at, download_count)",
        "ALTER TABLE topics ADD INDEX topics_status_deleted_published_index (status, deleted_at, published_at)",
    ];
    foreach ($topicIndexStatements as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    $commentIndexStatements = [
        "ALTER TABLE comments ADD INDEX comments_topic_status_created_index (topic_id, status, created_at)",
    ];
    foreach ($commentIndexStatements as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    $mediaIndexStatements = [
        "ALTER TABLE media_files ADD COLUMN display_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER size",
        "ALTER TABLE media_files ADD INDEX media_files_topic_primary_index (topic_id, is_primary, display_order)",
        "ALTER TABLE media_files ADD COLUMN last_checked_at TIMESTAMP NULL",
        "ALTER TABLE media_files ADD COLUMN last_status_code INT NULL",
        "ALTER TABLE media_files ADD COLUMN health_status VARCHAR(32) NOT NULL DEFAULT 'unchecked'",
        "ALTER TABLE media_files ADD COLUMN last_health_message VARCHAR(255) NULL",
    ];
    foreach ($mediaIndexStatements as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    $logIndexStatements = [
        "ALTER TABLE activity_logs ADD INDEX activity_logs_action_created_index (action, created_at)",
    ];
    foreach ($logIndexStatements as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    // Ensure users table has required account/profile/security columns
    $userColumns = [
        'username' => "VARCHAR(30) NULL",
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
        'is_banned' => "TINYINT(1) NOT NULL DEFAULT 0",
        'banned_at' => "TIMESTAMP NULL",
        'ban_reason' => "VARCHAR(500) NULL",
        'avatar' => "VARCHAR(500) NULL",
        'bio' => "TEXT NULL",
        'website' => "VARCHAR(500) NULL",
        'location' => "VARCHAR(255) NULL",
        'social_github' => "VARCHAR(255) NULL",
        'social_twitter' => "VARCHAR(255) NULL",
        'social_discord' => "VARCHAR(255) NULL",
        'remember_token' => "VARCHAR(100) NULL",
        'password_reset_token' => "VARCHAR(255) NULL",
        'password_reset_expires_at' => "TIMESTAMP NULL",
        'password_changed_at' => "TIMESTAMP NULL",
        'last_login_at' => "TIMESTAMP NULL",
        'last_login_ip' => "VARCHAR(45) NULL",
        'deleted_at' => "TIMESTAMP NULL",
    ];
    foreach ($userColumns as $name => $definition) {
        if (!adminColumnExists($pdo, 'users', $name)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$name} {$definition}");
        }
    }

    if (function_exists('usersEnsureUsernameSchema')) {
        usersEnsureUsernameSchema($pdo);
    }

    if (function_exists('usersEnsureBanAppealSchema')) {
        usersEnsureBanAppealSchema($pdo);
    }

    if (function_exists('usersEnsureGroupSchema')) {
        try {
            usersEnsureGroupSchema($pdo);
            $adminGroupId = function_exists('usersGroupIdBySlug') ? usersGroupIdBySlug($pdo, 'admin') : 0;
            if ($adminGroupId > 0) {
                $adminCount = (int)$pdo->query("SELECT COUNT(DISTINCT m.user_id)
                    FROM user_group_members m
                    INNER JOIN user_group_permissions p ON p.group_id = m.group_id
                    WHERE p.permission_key IN ('*', 'admin.access') AND p.permission_value = 1")->fetchColumn();
                if ($adminCount === 0) {
                    $firstUserId = (int)$pdo->query("SELECT MIN(id) FROM users")->fetchColumn();
                    if ($firstUserId > 0 && function_exists('usersSyncUserGroups')) {
                        usersSyncUserGroups($pdo, $firstUserId, [$adminGroupId], 0, 'ensure_first_admin_group');
                    }
                }
            }
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'ensureAdminSchema.user_groups']);
            }
        }
    }
}

function topicRevisionEnsureSchema(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_revisions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        topic_id BIGINT UNSIGNED NOT NULL,
        actor_user_id BIGINT UNSIGNED NULL,
        revision_number INT UNSIGNED NOT NULL DEFAULT 1,
        reason VARCHAR(80) NOT NULL DEFAULT 'admin_update',
        category_id BIGINT UNSIGNED NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        author_topic VARCHAR(255) NULL,
        topic_version VARCHAR(255) NULL,
        topic_descriptions LONGTEXT NULL,
        topic_download_links LONGTEXT NULL,
        status VARCHAR(255) NOT NULL,
        meta_title VARCHAR(255) NULL,
        meta_description TEXT NULL,
        primary_media_file_id BIGINT UNSIGNED NULL,
        links_json JSON NULL,
        media_json JSON NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP NULL,
        INDEX topic_revisions_topic_created_index (topic_id, created_at),
        INDEX topic_revisions_actor_index (actor_user_id),
        CONSTRAINT topic_revisions_topic_id_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
        CONSTRAINT topic_revisions_actor_user_id_foreign FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function topicRevisionNextNumber(PDO $pdo, int $topicId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(revision_number), 0) + 1 FROM topic_revisions WHERE topic_id = ?");
    $stmt->execute([$topicId]);
    return max(1, (int)$stmt->fetchColumn());
}

function topicRevisionCapture(PDO $pdo, int $topicId, ?int $actorUserId, string $reason = 'admin_update'): ?int
{
    $stmt = $pdo->prepare("SELECT id, category_id, title, slug, author_topic, topic_version, topic_descriptions, topic_download_links, status, meta_title, meta_description, primary_media_file_id
                           FROM topics
                           WHERE id = ? AND deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$topic) {
        return null;
    }

    $linksStmt = $pdo->prepare("SELECT id, name, url, download_count, display_order, created_at, updated_at
                                FROM topic_download_links
                                WHERE topic_id = ?
                                ORDER BY display_order, id");
    $linksStmt->execute([$topicId]);
    $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC);

    $mediaStmt = $pdo->prepare("SELECT id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at
                                FROM media_files
                                WHERE topic_id = ?
                                ORDER BY is_primary DESC, display_order, id");
    $mediaStmt->execute([$topicId]);
    $media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare("INSERT INTO topic_revisions
        (topic_id, actor_user_id, revision_number, reason, category_id, title, slug, author_topic, topic_version, topic_descriptions, topic_download_links, status, meta_title, meta_description, primary_media_file_id, links_json, media_json, ip_address, user_agent, created_at)
        VALUES
        (:topic_id, :actor_user_id, :revision_number, :reason, :category_id, :title, :slug, :author_topic, :topic_version, :topic_descriptions, :topic_download_links, :status, :meta_title, :meta_description, :primary_media_file_id, :links_json, :media_json, :ip_address, :user_agent, NOW())");
    $insert->execute([
        'topic_id' => $topicId,
        'actor_user_id' => $actorUserId ?: null,
        'revision_number' => topicRevisionNextNumber($pdo, $topicId),
        'reason' => $reason,
        'category_id' => $topic['category_id'] ?? null,
        'title' => (string)$topic['title'],
        'slug' => (string)$topic['slug'],
        'author_topic' => $topic['author_topic'] ?? null,
        'topic_version' => $topic['topic_version'] ?? null,
        'topic_descriptions' => $topic['topic_descriptions'] ?? null,
        'topic_download_links' => $topic['topic_download_links'] ?? null,
        'status' => (string)$topic['status'],
        'meta_title' => $topic['meta_title'] ?? null,
        'meta_description' => $topic['meta_description'] ?? null,
        'primary_media_file_id' => $topic['primary_media_file_id'] ?? null,
        'links_json' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'media_json' => json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
    ]);

    $revisionId = (int)$pdo->lastInsertId();
    topicRevisionPrune($pdo, $topicId);
    return $revisionId;
}

function topicRevisionPrune(PDO $pdo, int $topicId, int $limit = 50): void
{
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("SELECT id FROM topic_revisions WHERE topic_id = ? ORDER BY revision_number DESC, id DESC LIMIT 18446744073709551615 OFFSET " . (int) $limit);
    $stmt->execute([$topicId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $delete = $pdo->prepare("DELETE FROM topic_revisions WHERE id IN ({$placeholders})");
    $delete->execute($ids);
}

function topicRevisionList(PDO $pdo, int $topicId): array
{
    $stmt = $pdo->prepare("SELECT tr.*, u.username AS actor_name, u.email AS actor_email
                           FROM topic_revisions tr
                           LEFT JOIN users u ON u.id = tr.actor_user_id
                           WHERE tr.topic_id = ?
                           ORDER BY tr.revision_number DESC, tr.id DESC");
    $stmt->execute([$topicId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function topicRevisionFind(PDO $pdo, int $revisionId): ?array
{
    $stmt = $pdo->prepare("SELECT tr.*, t.title AS current_title, u.username AS actor_name, u.email AS actor_email
                           FROM topic_revisions tr
                           LEFT JOIN topics t ON t.id = tr.topic_id
                           LEFT JOIN users u ON u.id = tr.actor_user_id
                           WHERE tr.id = ?
                           LIMIT 1");
    $stmt->execute([$revisionId]);
    $revision = $stmt->fetch(PDO::FETCH_ASSOC);
    return $revision ?: null;
}

function topicRevisionRestore(PDO $pdo, int $revisionId, ?int $actorUserId): int
{
    $revision = topicRevisionFind($pdo, $revisionId);
    if (!$revision) {
        throw new RuntimeException('Revizyon bulunamadi.');
    }

    $topicId = (int)$revision['topic_id'];
    topicRevisionCapture($pdo, $topicId, $actorUserId, 'before_restore');

    $primaryMediaId = null;
    if (!empty($revision['primary_media_file_id'])) {
        $mediaCheck = $pdo->prepare("SELECT id FROM media_files WHERE id = ? AND topic_id = ? LIMIT 1");
        $mediaCheck->execute([(int)$revision['primary_media_file_id'], $topicId]);
        $primaryMediaId = $mediaCheck->fetchColumn() ? (int)$revision['primary_media_file_id'] : null;
    }

    $stmt = $pdo->prepare("UPDATE topics
        SET category_id = :category_id,
            title = :title,
            slug = :slug,
            author_topic = :author_topic,
            topic_version = :topic_version,
            topic_descriptions = :topic_descriptions,
            topic_download_links = :topic_download_links,
            status = :status,
            meta_title = :meta_title,
            meta_description = :meta_description,
            primary_media_file_id = :primary_media_file_id,
            updated_at = NOW(),
            published_at = CASE WHEN :status_published = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END
        WHERE id = :topic_id");
    $stmt->execute([
        'category_id' => $revision['category_id'],
        'title' => $revision['title'],
        'slug' => $revision['slug'],
        'author_topic' => $revision['author_topic'],
        'topic_version' => $revision['topic_version'],
        'topic_descriptions' => $revision['topic_descriptions'],
        'topic_download_links' => $revision['topic_download_links'],
        'status' => $revision['status'],
        'status_published' => $revision['status'],
        'meta_title' => $revision['meta_title'],
        'meta_description' => $revision['meta_description'],
        'primary_media_file_id' => $primaryMediaId,
        'topic_id' => $topicId,
    ]);

    $links = json_decode((string)($revision['links_json'] ?? '[]'), true);
    if (is_array($links)) {
        $lines = [];
        foreach ($links as $link) {
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $name = trim((string)($link['name'] ?? 'Link'));
            $lines[] = ($name !== '' ? $name : 'Link') . '|' . $url;
        }
        syncTopicDownloadLinks($pdo, $topicId, implode("\n", $lines));
    }

    seoInvalidateSitemapCaches();

    return $topicId;
}

function adminLegacyIdentifier(string $encoded): string
{
    return base64_decode($encoded, true) ?: '';
}

/**
 * Validate identifier (table/column name) to prevent SQL injection.
 */
function adminValidateIdentifier(string $name): string
{
    // Only allow alphanumeric and underscore characters
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
        throw new \InvalidArgumentException("Invalid identifier: {$name}");
    }
    return '`' . $name . '`';
}

function adminTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?");
    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function adminColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}

function adminRenameTableIfNeeded(PDO $pdo, string $from, string $to): void
{
    if (adminTableExists($pdo, $from) && !adminTableExists($pdo, $to)) {
        $fromSafe = adminValidateIdentifier($from);
        $toSafe = adminValidateIdentifier($to);
        $pdo->exec("RENAME TABLE {$fromSafe} TO {$toSafe}");
    }
}

function adminDropTableIfExists(PDO $pdo, string $table): void
{
    if (adminTableExists($pdo, $table)) {
        $tableSafe = adminValidateIdentifier($table);
        try {
            $pdo->exec("DROP TABLE {$tableSafe}");
        } catch (Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec("DROP TABLE IF EXISTS {$tableSafe}");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        }
    }
}

function adminRenameColumnIfNeeded(PDO $pdo, string $table, string $from, string $to, string $definition): void
{
    if (!adminTableExists($pdo, $table) || !adminColumnExists($pdo, $table, $from) || adminColumnExists($pdo, $table, $to)) {
        return;
    }

    $tableSafe = adminValidateIdentifier($table);
    $fromSafe = adminValidateIdentifier($from);
    $toSafe = adminValidateIdentifier($to);

    // Validate definition against allowed patterns
    $allowedDefinitions = [
        'BIGINT UNSIGNED NOT NULL',
        'BIGINT UNSIGNED NULL',
        'INT NOT NULL DEFAULT 0',
        'VARCHAR(255) NULL',
        'TEXT NULL',
    ];
    if (!in_array($definition, $allowedDefinitions, true)) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE {$tableSafe} RENAME COLUMN {$fromSafe} TO {$toSafe}");
    } catch (Throwable $e) {
        $pdo->exec("ALTER TABLE {$tableSafe} CHANGE {$fromSafe} {$toSafe} {$definition}");
    }
}

function adminDropColumnIfExists(PDO $pdo, string $table, string $column): void
{
    if (!adminTableExists($pdo, $table) || !adminColumnExists($pdo, $table, $column)) {
        return;
    }

    $tableSafe = adminValidateIdentifier($table);
    $columnSafe = adminValidateIdentifier($column);

    adminDropForeignKeysForColumn($pdo, $table, $column);
    $pdo->exec("ALTER TABLE {$tableSafe} DROP COLUMN {$columnSafe}");
}

function adminDropForeignKeysForColumn(PDO $pdo, string $table, string $column): void
{
    $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
          AND REFERENCED_TABLE_NAME IS NOT NULL");
    $stmt->execute([$table, $column]);

    $tableSafe = adminValidateIdentifier($table);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $constraint) {
        $constraintSafe = adminValidateIdentifier($constraint);
        $pdo->exec("ALTER TABLE {$tableSafe} DROP FOREIGN KEY {$constraintSafe}");
    }
}

if (!function_exists('adminCronPhpBinaryNormalizeValue')) {
    function adminCronPhpBinaryNormalizeValue(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $autoValues = [
            'auto',
            'automatic',
            'default',
            'php',
            'otomatik',
            'varsayilan',
            'varsayılan',
            'system',
            'sistem',
        ];
        if (in_array($normalized, $autoValues, true)) {
            return '';
        }

        return $value;
    }
}

if (!function_exists('adminCronPhpBinaryIsAutoValue')) {
    function adminCronPhpBinaryIsAutoValue(string $value): bool
    {
        return adminCronPhpBinaryNormalizeValue($value) === '';
    }
}

if (!function_exists('adminCronPhpBinaryCandidates')) {
    function adminCronPhpBinaryCandidates(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $candidates = [];
        $seen = [];
        $addCandidate = static function ($path) use (&$candidates, &$seen): void {
            $path = trim(str_replace(["\r", "\n"], '', (string) $path));
            if ($path === '') {
                return;
            }

            $basename = strtolower(basename($path));
            if (!preg_match('/^php(?:[0-9.]+)?(?:-cli)?(?:\.exe)?$/i', $basename)) {
                return;
            }

            if (
                stripos($basename, 'cgi') !== false
                || stripos($basename, 'fpm') !== false
                || stripos($basename, 'dbg') !== false
                || stripos($basename, 'config') !== false
                || stripos($basename, 'ize') !== false
            ) {
                return;
            }

            // PATH entries can point at binaries outside open_basedir; probe quietly.
            $resolved = @realpath($path);
            if (is_string($resolved) && $resolved !== '') {
                $path = $resolved;
            }

            $normalized = strtolower(str_replace('\\', '/', $path));
            if (isset($seen[$normalized])) {
                return;
            }

            if (!@is_file($path)) {
                return;
            }

            if (function_exists('is_executable') && !@is_executable($path)) {
                return;
            }

            $seen[$normalized] = true;
            $candidates[] = $path;
        };

        $patterns = [];
        if (defined('PHP_BINARY')) {
            $addCandidate(PHP_BINARY);
        }
        if (defined('PHP_BINDIR')) {
            $bindir = rtrim(str_replace('\\', '/', (string) PHP_BINDIR), '/');
            if ($bindir !== '') {
                $patterns[] = $bindir . '/php*';
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $patterns = array_merge([
                'C:/xampp/php/php*.exe',
                'C:/php/php*.exe',
                'C:/Program Files/PHP/php*.exe',
                'C:/Program Files (x86)/PHP/php*.exe',
            ], $patterns);
        } else {
            $patterns = array_merge([
                '/usr/bin/php*',
                '/usr/local/bin/php*',
                '/opt/cpanel/ea-php*/root/usr/bin/php*',
                '/opt/plesk/php/*/bin/php*',
            ], $patterns);
        }

        foreach ($patterns as $pattern) {
            foreach (glob($pattern, GLOB_NOSORT) ?: [] as $candidate) {
                $addCandidate($candidate);
            }
        }

        $cache = array_values($candidates);
        return $cache;
    }
}

function adminNormalizeSettingValue(string $key, string $value, array $definition): string
{
    if ($key === 'site_language') {
        return 'tr';
    }

    if ($key === 'seo_public_page_presets_json' && function_exists('seoPublicPagePresetsJson')) {
        return seoPublicPagePresetsJson($value);
    }

    if ($key === 'sidebar_builder_config' && trim($value) !== '' && function_exists('sidebarBuilderSanitizeConfigJson')) {
        return sidebarBuilderSanitizeConfigJson($value);
    }

    if ($key === 'cron_php_binary') {
        return adminCronPhpBinaryNormalizeValue($value);
    }

    if (($definition['type'] ?? '') === 'select' && isset($definition['options']) && is_array($definition['options'])) {
        if (array_key_exists($value, $definition['options'])) {
            return $value;
        }

        return (string)($definition['default'] ?? array_key_first($definition['options']) ?? '');
    }

    return $value;
}

function adminApplySettingAliases(array $settings, array $definitions): array
{
    $pairs = [
        'og_image' => 'default_og_image',
    ];

    foreach ($pairs as $canonical => $alias) {
        if (!array_key_exists($canonical, $settings) || !array_key_exists($alias, $settings)) {
            continue;
        }

        $canonicalValue = (string)$settings[$canonical];
        $aliasValue = (string)$settings[$alias];
        $canonicalDefault = (string)($definitions[$canonical]['default'] ?? '');
        $aliasDefault = (string)($definitions[$alias]['default'] ?? '');
        $value = $canonicalValue;

        if (($canonicalValue === '' || $canonicalValue === $canonicalDefault) && $aliasValue !== '' && $aliasValue !== $aliasDefault) {
            $value = $aliasValue;
        }
        if ($canonical === 'og_image' && $canonicalValue === '' && $aliasValue !== '') {
            $value = $aliasValue;
        }

        $settings[$canonical] = $value;
        $settings[$alias] = $value;
    }

    $inversePairs = [
        'index_search_results' => 'robots_noindex_search',
        'index_empty_categories' => 'noindex_empty_categories',
        'index_draft_topics' => 'noindex_draft_topics',
    ];

    foreach ($inversePairs as $canonical => $legacyNoindex) {
        if (!array_key_exists($canonical, $settings) || !array_key_exists($legacyNoindex, $settings)) {
            continue;
        }

        $canonicalValue = (string) $settings[$canonical];
        $legacyValue = (string) $settings[$legacyNoindex];
        $canonicalDefault = (string) ($definitions[$canonical]['default'] ?? '0');
        $legacyDefault = (string) ($definitions[$legacyNoindex]['default'] ?? '1');

        $value = in_array($canonicalValue, ['0', '1'], true) ? $canonicalValue : $canonicalDefault;
        $canonicalIsDefault = $canonicalValue === '' || $canonicalValue === $canonicalDefault;
        $legacyIsBool = in_array($legacyValue, ['0', '1'], true);
        $legacyHasSignal = $legacyValue !== '' && $legacyValue !== $legacyDefault;

        if ($canonicalIsDefault && $legacyIsBool && $legacyHasSignal) {
            $value = $legacyValue === '1' ? '0' : '1';
        }

        $settings[$canonical] = $value;
        $settings[$legacyNoindex] = $value === '1' ? '0' : '1';
    }

    if (array_key_exists('site_language', $settings)) {
        $settings['site_language'] = 'tr';
    }

    return $settings;
}

if (!function_exists('adminLegacySeoSettingDefinitions')) {
    function adminLegacySeoSettingDefinitions(): array
    {
        return [
            'default_meta_title' => ['type' => 'string', 'default' => '', 'section' => 'seo'],
            'default_meta_description' => ['type' => 'text', 'default' => '', 'section' => 'seo'],
            'meta_title_suffix' => ['type' => 'string', 'default' => '', 'section' => 'seo'],
            'meta_description_max_length' => ['type' => 'number', 'default' => '160', 'section' => 'seo'],
            'meta_description_length' => ['type' => 'number', 'default' => '160', 'section' => 'seo'],
            'allow_indexing' => ['type' => 'bool', 'default' => '1', 'section' => 'seo'],
        ];
    }
}

function adminNormalizeLegacyTopicStatuses(?PDO $pdo): void
{
    static $ran = false;
    if ($ran || !$pdo) {
        return;
    }
    $ran = true;

    $changed = false;
    try {
        $affected = $pdo->exec("UPDATE topics SET status = 'draft' WHERE status = 'pending'");
        if ($affected !== false && $affected > 0) {
            $changed = true;
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    try {
        $affected = $pdo->exec("UPDATE topics SET status = 'published', published_at = COALESCE(published_at, created_at, NOW()) WHERE status = 'archived'");
        if ($affected !== false && $affected > 0) {
            $changed = true;
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    if ($changed && function_exists('invalidatePublicContentCache')) {
        invalidatePublicContentCache();
    }

    if ($changed) {
        seoInvalidateSitemapCaches();
    }
}

function adminProcessLegacySeoSetting(string $key, string $value, array $legacySeoDefinitions, array &$settings): bool
{
    if (array_key_exists($key, $legacySeoDefinitions)) {
        $settings[$key] = adminNormalizeSettingValue(
            $key,
            $value,
            $legacySeoDefinitions[$key]
        );
        return true;
    }
    return false;
}

function adminProcessActiveThemeSetting(string $key, string $value, array $definitions, array &$settings): bool
{
    if ($key === 'active_public_theme' && array_key_exists('theme_active_id', $settings)) {
        $settings['theme_active_id'] = adminNormalizeSettingValue(
            'theme_active_id',
            $value,
            $definitions['theme_active_id']
        );
        return true;
    }
    return false;
}

function getAdminSettings(?PDO $pdo): array
{
    // Static cache: aynı request içinde tekrar sorgu atmayı önler
    static $cache = null;

    // Invalidation flag varsa static cache'i sıfırla
    if (!empty($GLOBALS['_admin_settings_cache_invalid'])) {
        $cache = null;
        unset($GLOBALS['_admin_settings_cache_invalid']);
    }

    if ($cache !== null) {
        return $cache;
    }
    unset($GLOBALS['_admin_settings_cache_invalid']);

    // File-based cache: DB sorgusunu atlamak için dosya cache kullan
    $cacheFile = dirname(__DIR__) . '/storage/cache/admin_settings_compiled.php';
    $cacheTtl = 300; // 5 dakika
    if (!$pdo) {
        // PDO yoksa sadece defaults dön
        $definitions = adminSettingDefinitions();
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $settings[$key] = adminNormalizeSettingValue((string)$key, (string)$definition['default'], $definition);
        }
        return adminApplySettingAliases($settings, $definitions);
    }

    // APCu cache (en hızlı katman)
    $apcuKey = 'admin_settings_v1';
    if (function_exists('apcu_fetch') && empty($GLOBALS['_admin_settings_cache_invalid'])) {
        $apcuData = apcu_fetch($apcuKey, $apcuSuccess);
        if ($apcuSuccess && is_array($apcuData)) {
            $cache = $apcuData;
            return $cache;
        }
    }

    // Dosya cache kontrolü
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $fileCached = require $cacheFile;
        if (is_array($fileCached) && !empty($fileCached)) {
            // APCu'ya da yaz
            if (function_exists('apcu_store')) {
                apcu_store($apcuKey, $fileCached, $cacheTtl);
            }
            $cache = $fileCached;
            return $cache;
        }
    }

    $definitions = adminSettingDefinitions();
    $settings = [];

    foreach ($definitions as $key => $definition) {
        $settings[$key] = adminNormalizeSettingValue((string)$key, (string)$definition['default'], $definition);
    }

    try {
        $legacyStmt = $pdo->query("SELECT `key`, value FROM settings");
        $legacySeoDefinitions = adminLegacySeoSettingDefinitions();
        while ($row = $legacyStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = (string)$row['key'];
            if (adminProcessActiveThemeSetting($key, (string)($row['value'] ?? ''), $definitions, $settings)) {
                continue;
            }
            if (adminProcessLegacySeoSetting($key, (string)($row['value'] ?? ''), $legacySeoDefinitions, $settings)) {
                continue;
            }
            if (array_key_exists($key, $settings)) {
                $settings[$key] = adminNormalizeSettingValue($key, (string)($row['value'] ?? ''), $definitions[$key]);
            }
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings");
        $legacySeoDefinitions = $legacySeoDefinitions ?? adminLegacySeoSettingDefinitions();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = (string)$row['setting_key'];
            if (adminProcessActiveThemeSetting($key, (string)($row['setting_value'] ?? ''), $definitions, $settings)) {
                continue;
            }
            if (adminProcessLegacySeoSetting($key, (string)($row['setting_value'] ?? ''), $legacySeoDefinitions, $settings)) {
                continue;
            }
            if (array_key_exists($key, $settings)) {
                $settings[$key] = adminNormalizeSettingValue($key, (string)($row['setting_value'] ?? ''), $definitions[$key]);
            }
        }
    } catch (Throwable $e) {
        return adminApplySettingAliases($settings, $definitions);
    }

    $cache = adminApplySettingAliases($settings, $definitions);

    // Dosya cache'ine yaz
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    file_put_contents($cacheFile, "<?php\nreturn " . var_export($cache, true) . ";\n", LOCK_EX);

    // APCu'ya da yaz
    if (function_exists('apcu_store')) {
        apcu_store($apcuKey, $cache, $cacheTtl);
    }

    return $cache;
}

function adminSettingValue(?PDO $pdo, string $key, ?string $fallback = null): string
{
    $definitions = adminSettingDefinitions();
    $default = $fallback ?? (string)($definitions[$key]['default'] ?? '');

    if (!$pdo) {
        return $default;
    }

    $settings = getAdminSettings($pdo);
    return array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
}

/**
 * Invalidate all layers of the settings cache (in-memory, APCu, file).
 * Call after saveAdminSettings or any programmatic settings change.
 */
function invalidateAdminSettingsCache(): void
{
    // Static variable reset: in-memory cache'i geçersiz kıl
    $GLOBALS['_admin_settings_cache_invalid'] = true;

    // APCu cache'i temizle
    if (function_exists('apcu_delete')) {
        apcu_delete('admin_settings_v1');
    }

    // Dosya cache'i temizle
    $cacheFile = dirname(__DIR__) . '/storage/cache/admin_settings_compiled.php';
    if (is_file($cacheFile)) {
        @unlink($cacheFile);
    }
}

function saveAdminSettings(?PDO $pdo, array $input): void
{
    if (!$pdo) {
        return;
    }

    $definitions = adminSettingDefinitions();
    $input['site_language'] = 'tr';
    if (isset($input['seo_public_pages']) && is_array($input['seo_public_pages']) && function_exists('seoPublicPagePresetsJson')) {
        $input['seo_public_page_presets_json'] = seoPublicPagePresetsJson($input['seo_public_pages']);
    } elseif (array_key_exists('seo_public_page_presets_json', $input) && function_exists('seoPublicPagePresetsJson')) {
        $input['seo_public_page_presets_json'] = seoPublicPagePresetsJson($input['seo_public_page_presets_json']);
    }
    if (array_key_exists('og_image', $input)) {
        $input['default_og_image'] = $input['og_image'];
    }

    $normalizeBoolInput = static function ($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
    };

    $inverseSeoPairs = [
        'index_search_results' => 'robots_noindex_search',
        'index_empty_categories' => 'noindex_empty_categories',
        'index_draft_topics' => 'noindex_draft_topics',
    ];
    foreach ($inverseSeoPairs as $canonical => $legacyNoindex) {
        if (array_key_exists($canonical, $input)) {
            $canonicalValue = $normalizeBoolInput($input[$canonical]);
        } elseif (array_key_exists($legacyNoindex, $input)) {
            $legacyValue = $normalizeBoolInput($input[$legacyNoindex]);
            $canonicalValue = $legacyValue === '1' ? '0' : '1';
        } else {
            $canonicalValue = '0';
        }

        $input[$canonical] = $canonicalValue;
        $input[$legacyNoindex] = $canonicalValue === '1' ? '0' : '1';
    }

    $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
        VALUES (:key, :value, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $legacyStmt = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
        VALUES (:key, :value, :type, NOW(), NOW())
        ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type), updated_at = NOW()");

    // Hangi section'lar bu formda var? _sections alanından veya _active_tab'dan belirle
    $allowedSections = [];
    if (!empty($input['_sections'])) {
        $allowedSections = array_map('trim', explode(',', (string)$input['_sections']));
    }

    $routePrefixKeys = [
        'topic' => 'route_topic_prefix',
        'category' => 'route_category_prefix',
        'category_list' => 'route_category_list_prefix',
        'profile' => 'route_profile_prefix',
    ];
    $routePrefixValues = [];
    foreach ($routePrefixKeys as $type => $prefixKey) {
        $fallback = (string)($definitions[$prefixKey]['default'] ?? $type);
        $rawPrefix = trim((string)($input[$prefixKey] ?? $fallback));
        $sanitized = function_exists('routePrefixSanitize') ? routePrefixSanitize($rawPrefix) : slugify($rawPrefix);
        $routePrefixValues[$prefixKey] = $sanitized !== '' ? $sanitized : $fallback;
    }

    $coreRoutePrefixValues = [
        'route_topic_prefix' => $routePrefixValues['route_topic_prefix'] ?? '',
        'route_category_prefix' => $routePrefixValues['route_category_prefix'] ?? '',
        'route_profile_prefix' => $routePrefixValues['route_profile_prefix'] ?? '',
    ];
    if (count(array_unique($coreRoutePrefixValues)) !== count($coreRoutePrefixValues)) {
        foreach (['route_topic_prefix', 'route_category_prefix', 'route_profile_prefix'] as $prefixKey) {
            $routePrefixValues[$prefixKey] = (string)($definitions[$prefixKey]['default'] ?? '');
        }
    }

    if (in_array((string)($routePrefixValues['route_category_list_prefix'] ?? ''), [
        (string)($routePrefixValues['route_topic_prefix'] ?? ''),
        (string)($routePrefixValues['route_profile_prefix'] ?? ''),
    ], true)) {
        $routePrefixValues['route_category_list_prefix'] = (string)($definitions['route_category_list_prefix']['default'] ?? 'kategori');
    }

    $publicRoutePathKeys = function_exists('routePublicStaticPathSettingKeys')
        ? routePublicStaticPathSettingKeys()
        : [
            'login' => 'route_login_path',
            'register' => 'route_register_path',
            'logout' => 'route_logout_path',
            'forgot_password' => 'route_forgot_password_path',
            'reset_password' => 'route_reset_password_path',
            'notifications' => 'route_notifications_path',
            'messages' => 'route_messages_path',
            'leaderboard' => 'route_leaderboard_path',
            'ban_appeals' => 'route_ban_appeals_path',
            'contact' => 'route_contact_path',
            'upload_topic' => 'route_upload_topic_path',
            'edit_topic' => 'route_edit_topic_path',
            'download' => 'route_download_path',
            'events' => 'route_events_path',
        ];
    $publicRoutePathValues = [];
    foreach ($publicRoutePathKeys as $routeKey => $settingKey) {
        if (!isset($definitions[$settingKey])) {
            continue;
        }

        $fallback = (string)($definitions[$settingKey]['default'] ?? $routeKey);
        $rawPath = trim((string)($input[$settingKey] ?? $fallback));
        $sanitized = function_exists('routePublicStaticPathSanitize')
            ? routePublicStaticPathSanitize($rawPath)
            : slugify($rawPath);
        $publicRoutePathValues[$settingKey] = $sanitized !== '' ? $sanitized : $fallback;
    }

    $publicRouteBlocked = [];
    $reservedRouteSegments = function_exists('routePublicStaticReservedSegments')
        ? routePublicStaticReservedSegments()
        : ['admin', 'api', 'assets', 'database', 'docs', 'includes', 'tests', 'uploads', 'cron', 'install', 'route', 'index', 'health'];
    foreach ($reservedRouteSegments as $segment) {
        $segment = trim((string)$segment);
        if ($segment !== '') {
            $publicRouteBlocked[$segment] = true;
        }
    }
    foreach (['route_topic_prefix', 'route_category_prefix', 'route_category_list_prefix', 'route_profile_prefix'] as $prefixKey) {
        $segment = trim((string)($routePrefixValues[$prefixKey] ?? ''));
        if ($segment !== '') {
            $publicRouteBlocked[$segment] = true;
        }
    }

    foreach ($publicRoutePathKeys as $routeKey => $settingKey) {
        if (!isset($definitions[$settingKey])) {
            continue;
        }

        $fallback = (string)($definitions[$settingKey]['default'] ?? $routeKey);
        $candidate = trim((string)($publicRoutePathValues[$settingKey] ?? $fallback));
        if ($candidate === '' || isset($publicRouteBlocked[$candidate])) {
            $candidate = trim((string)$fallback);
        }
        if ($candidate === '' || isset($publicRouteBlocked[$candidate])) {
            $base = $candidate !== '' ? $candidate : slugify((string)$routeKey);
            if ($base === '') {
                $base = 'route';
            }
            $candidate = $base;
            $suffix = 2;
            while (isset($publicRouteBlocked[$candidate])) {
                $candidate = $base . '-' . $suffix;
                $suffix++;
            }
        }

        $publicRoutePathValues[$settingKey] = $candidate;
        $publicRouteBlocked[$candidate] = true;
    }

    foreach ($definitions as $key => $definition) {
        $section = (string)($definition['section'] ?? '');

        // Eğer izin verilen section'lar belirtilmişse, sadece o section'daki ayarları kaydet
        if (!empty($allowedSections) && !in_array($section, $allowedSections, true)) {
            continue;
        }

        $type = (string)$definition['type'];
        if ($type === 'bool') {
            if (array_key_exists($key, $input)) {
                $rawBoolValue = $input[$key];
                if (is_array($rawBoolValue)) {
                    $rawBoolValue = reset($rawBoolValue);
                }
                $normalizedBool = strtolower(trim((string) $rawBoolValue));
                $value = in_array($normalizedBool, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
            } else {
                $value = '0';
            }
        } elseif ($type === 'multicheck') {
            $value = isset($input[$key]) && is_array($input[$key]) ? implode(',', array_map('trim', $input[$key])) : '';
        } else {
            $value = trim((string)($input[$key] ?? $definition['default']));
        }

        if ($type === 'number') {
            $value = (string)max(0, (int)$value);
        }

        if (in_array($key, ['route_topic_prefix', 'route_category_prefix', 'route_category_list_prefix', 'route_profile_prefix'], true)) {
            $value = $routePrefixValues[$key] ?? (string)$definition['default'];
        }

        if (array_key_exists($key, $publicRoutePathValues)) {
            $value = (string)($publicRoutePathValues[$key] ?? $definition['default']);
        }

        if ($key === 'sidebar_builder_config' && function_exists('sidebarBuilderSanitizeConfigJson')) {
            $value = sidebarBuilderSanitizeConfigJson($value);
        }

        $value = adminNormalizeSettingValue($key, $value, $definition);

        $stmt->execute(['key' => $key, 'value' => $value]);
        try {
            $legacyStmt->execute(['key' => $key, 'value' => $value, 'type' => $type]);
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    // Ayar cache'ini invalidate et
    invalidateAdminSettingsCache();
}

function getAdminCategories(?PDO $pdo, bool $activeOnly = false): array
{
    if (!$pdo) {
        return [
            ['id' => 1, 'parent_id' => null, 'name' => 'Genel', 'slug' => 'genel', 'description' => '', 'status' => 'active', 'display_order' => 0, 'seo_title' => '', 'seo_description' => '', 'topic_count' => 0],
        ];
    }

    ensureAdminSchema($pdo);

    try {
        $where = $activeOnly ? "WHERE cat.status = 'active' AND cat.deleted_at IS NULL" : "WHERE cat.deleted_at IS NULL";
        $stmt = $pdo->query("SELECT cat.*, COUNT(t.id) AS topic_count
            FROM categories cat
            LEFT JOIN topics t ON t.category_id = cat.id AND t.deleted_at IS NULL
            {$where}
            GROUP BY cat.id
            ORDER BY COALESCE(cat.parent_id, 0), cat.display_order, cat.name");
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function buildAdminCategoryTree(array $categories, ?int $parentId = null, int $depth = 0): array
{
    $branch = [];

    foreach ($categories as $category) {
        $categoryParent = $category['parent_id'] === null ? null : (int)$category['parent_id'];
        if ($categoryParent !== $parentId) {
            continue;
        }

        $category['depth'] = $depth;
        $branch[] = $category;
        array_push($branch, ...buildAdminCategoryTree($categories, (int)$category['id'], $depth + 1));
    }

    return $branch;
}

function getAdminCategoryOptions(?PDO $pdo): array
{
    $categories = getAdminCategories($pdo, true);
    if (empty($categories) && $pdo) {
        ensureDefaultCategory($pdo);
        $categories = getAdminCategories($pdo, true);
    }

    return buildAdminCategoryTree($categories);
}

function categoryHasTopics(?PDO $pdo, int $id): bool
{
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}




