<?php

declare(strict_types=1);

require_once __DIR__ . '/SeoPublicPages.php';

/**
 * SEO Helper Functions
 * Meta Tags, Open Graph, Twitter Cards, Structured Data
 */

if (!function_exists('getSeoMeta')) {
    /**
     * Generate comprehensive SEO meta tags
     * Includes Open Graph, Twitter Cards, and Canonical URL
     *
     * @param string $title Page title
     * @param string $description Meta description (will be truncated to 160 chars for Turkish)
     * @param string|null $url Canonical URL
     * @param string|null $image OG image URL
     * @param bool $includeCanonical Whether to emit the canonical link tag
     * @param string|null $ogType OG type (website, article, etc.)
     * @return string HTML meta tags
     */
    function getSeoMeta(
        string $title,
        string $description,
        ?string $url = null,
        ?string $image = null,
        bool $includeCanonical = true,
        ?string $ogType = null
    ): string {
        global $baseUri, $envConfig, $pdo;

        // Get admin settings for SEO
        $settings = function_exists('seoSettings')
            ? seoSettings()
            : (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);

        $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        $titleIsFinal = !empty($GLOBALS['_seo_public_page_title_is_final']);
        $skipPublicPresets = !empty($GLOBALS['_seo_skip_public_page_presets']);

        if (
            !$skipPublicPresets
            && function_exists('seoPublicPageResolveKey')
            && function_exists('seoPublicPageMeta')
        ) {
            $resolvedPageKey = seoPublicPageResolveKey((string) ($_SERVER['REQUEST_URI'] ?? '/'), $settings, null);
            if ($resolvedPageKey !== '') {
                $resolvedMeta = seoPublicPageMeta(
                    $resolvedPageKey,
                    [
                        'title' => $title,
                        'description' => $description,
                        'image' => (string) ($image ?? ''),
                    ],
                    [],
                    $settings
                );
                $title = (string) ($resolvedMeta['title'] ?? $title);
                $description = (string) ($resolvedMeta['description'] ?? $description);
                $image = (string) ($resolvedMeta['image'] ?? $image);
                $titleIsFinal = !empty($resolvedMeta['title_is_final']);
            }
        }

        // Use default_meta_title if title is empty
        if (empty(trim($title))) {
            $title = $settings['default_meta_title'] ?? $siteName;
        }

        // Apply meta_title_suffix if set
        $titleSuffix = (string) ($settings['meta_title_suffix'] ?? '');
        if (!$titleIsFinal && $titleSuffix !== '' && !str_contains($title, $titleSuffix)) {
            $title = $title . ' ' . $titleSuffix;
        }

        // Use default_meta_description if description is empty
        if (empty(trim($description))) {
            $description = $settings['default_meta_description'] ?? '';
        }

        // Use the canonical helper when available so legacy includes match the
        // newer SEO stack exactly.
        if (function_exists('seoCanonicalUrl')) {
            $url = seoCanonicalUrl($url ?: null, $settings);
        } else {
            // Fallback for direct legacy includes.
            $canonicalBase = $settings['canonical_base_url'] ?? '';
            if (empty($canonicalBase)) {
                $url = $url ?: getCurrentUrl();
            } else {
                $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
                $url = rtrim($canonicalBase, '/') . $requestUri;

                if (($settings['canonical_trailing_slash'] ?? '0') === '1') {
                    if (!str_ends_with($url, '/') && !str_contains(basename($url), '.')) {
                        $url .= '/';
                    }
                } else {
                    $url = rtrim($url, '/');
                }
            }
        }

        // Use og_image from settings if no image provided.
        // Fallback to active theme preview so the tag never points to a missing asset.
        if (empty($image)) {
            $image = trim((string) ($settings['og_image'] ?? ''));
            if (($image === '' || $image === '/assets/og-default.jpg' || $image === 'assets/og-default.jpg')
                && isset($GLOBALS['themeManager']) && $GLOBALS['themeManager'] instanceof ThemeManager) {
                $image = rtrim((string) $GLOBALS['themeManager']->themeUrl($GLOBALS['themeManager']->activeThemeId()), '/') . '/images/preview.png';
            }
        }

        // Ensure absolute URLs
        if (!str_starts_with($image, 'http')) {
            $image = function_exists('seoCanonicalUrl')
                ? seoCanonicalUrl($image, $settings)
                : rtrim(getBaseUrl(), '/') . '/' . ltrim($image, '/');
        }

        // Use meta_description_max_length from settings
        $maxLength = (int)($settings['meta_description_max_length'] ?? 160);
        if (mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        // Use twitter_card from settings
        $twitterCard = $settings['twitter_card'] ?? 'summary_large_image';

        // Use og_type from the caller when provided, otherwise fall back to settings/defaults.
        $ogType = $ogType ?? ($settings['og_type'] ?? ($image !== '' ? 'article' : 'website'));

        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars((string) $siteName, ENT_QUOTES, 'UTF-8');
        $ogType = htmlspecialchars((string) $ogType, ENT_QUOTES, 'UTF-8');
        $twitterCard = htmlspecialchars((string) $twitterCard, ENT_QUOTES, 'UTF-8');

        $meta = <<<HTML
    <!-- SEO Meta Tags -->
    <meta name="description" content="{$description}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="{$ogType}">
    <meta property="og:site_name" content="{$siteName}">
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:image" content="{$image}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{$url}">
    <meta property="og:locale" content="tr_TR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="{$twitterCard}">
    <meta name="twitter:title" content="{$title}">
    <meta name="twitter:description" content="{$description}">
    <meta name="twitter:image" content="{$image}">
HTML;

        // Add Twitter handle if set
        if (!empty($settings['twitter_handle'])) {
            $twitterHandle = htmlspecialchars($settings['twitter_handle'], ENT_QUOTES, 'UTF-8');
            $meta .= "\n    <meta name=\"twitter:site\" content=\"@{$twitterHandle}\">";
        }

        if ($includeCanonical) {
            $meta .= "\n    \n    <!-- Canonical URL -->\n    <link rel=\"canonical\" href=\"{$url}\">";
        }

        return $meta;
    }
}

if (!function_exists('getStructuredData')) {
    /**
     * Generate Schema.org structured data (JSON-LD)
     */
    function getStructuredData(string $type, array $data): string {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => $type,
        ];
        
        $structuredData = array_merge($structuredData, $data);
        
        $json = json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }
}

if (!function_exists('getTopicStructuredData')) {
    /**
     * Generate structured data for a topic/mod page
     */
    function getTopicStructuredData(array $topic): string {
        global $baseUri;
        
        $data = [
            'name' => $topic['title'],
            'description' => strip_tags($topic['description'] ?? ''),
            'image' => $topic['cover_image'] ?? '',
            'url' => topicUrl((string) $topic['slug'], (int) ($topic['id'] ?? 0)),
            'datePublished' => $topic['created_at'] ?? date('c'),
            'dateModified' => $topic['updated_at'] ?? date('c'),
        ];
        
        // Add rating if available
        if (isset($topic['rating']) && $topic['rating'] > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $topic['rating'],
                'ratingCount' => $topic['rating_count'] ?? 1,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }
        
        // Add download info
        if (isset($topic['download_count'])) {
            $data['interactionStatistic'] = [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/DownloadAction',
                'userInteractionCount' => $topic['download_count'],
            ];
        }
        
        // Add author
        if (isset($topic['author_name'])) {
            $data['author'] = [
                '@type' => 'Person',
                'name' => $topic['author_name'],
            ];
        }
        
        return getStructuredData('SoftwareApplication', $data);
    }
}

if (!function_exists('getBreadcrumbStructuredData')) {
    /**
     * Generate breadcrumb structured data
     */
    function getBreadcrumbStructuredData(array $breadcrumbs, ?array $settings = null): string {
        global $baseUri, $pdo;

        // Check if breadcrumb schema is enabled
        $settings = $settings ?? (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);
        if ((string) ($settings['schema_breadcrumbs'] ?? '1') !== '1') {
            return '';
        }

        $items = [];
        $position = 1;

        foreach ($breadcrumbs as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
                'item' => $crumb['url'] ?? null,
            ];
        }

        $data = [
            'itemListElement' => $items,
        ];

        return getStructuredData('BreadcrumbList', $data);
    }
}

if (!function_exists('getWebsiteStructuredData')) {
    /**
     * Generate website structured data for homepage
     */
    function getWebsiteStructuredData(): string {
        global $baseUri, $envConfig, $pdo;

        $settings = function_exists('seoSettings')
            ? seoSettings()
            : (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);
        $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        $baseUrl = getBaseUrl();
        
        $data = [
            'name' => $siteName,
            'url' => $baseUrl,
            'description' => 'Oyun modları, eklentiler ve içerikler paylaşım platformu',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        
        return getStructuredData('WebSite', $data);
    }
}

if (!function_exists('getCurrentUrl')) {
    /**
     * Get current full URL
     */
    function getCurrentUrl(): string {
        $requestUri = str_replace(["\r", "\n"], '', (string) ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/' . ltrim($requestUri, '/');
        }

        if (function_exists('appPublicBaseUrl')) {
            $base = rtrim(appPublicBaseUrl(true, '', $GLOBALS['envConfig'] ?? []), '/');
            return $base . $requestUri;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . $requestUri;
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get base URL of the site
     */
    function getBaseUrl(): string {
        if (function_exists('appPublicBaseUrl')) {
            return appPublicBaseUrl(true, '', $GLOBALS['envConfig'] ?? []);
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . $host;
    }
}

if (!function_exists('generateSitemap')) {
    if (!function_exists('formatSitemapXml')) {
        function formatSitemapXml(string $xml): string
        {
            if (!class_exists(DOMDocument::class)) {
                return $xml;
            }

            $document = new DOMDocument('1.0', 'UTF-8');
            $document->preserveWhiteSpace = false;
            $document->formatOutput = true;

            libxml_use_internal_errors(true);
            $loaded = $document->loadXML($xml, LIBXML_NOBLANKS);
            libxml_clear_errors();

            if (!$loaded) {
                return $xml;
            }

            $formatted = $document->saveXML();

            return is_string($formatted) && $formatted !== '' ? $formatted : $xml;
        }
    }

    if (!function_exists('decorateSitemapXml')) {
        function decorateSitemapXml(string $xml, string $stylesheetHref = 'sitemap.css'): string
        {
            $stylesheetHref = trim($stylesheetHref);
            if ($stylesheetHref === '' || str_contains($xml, 'xml-stylesheet')) {
                return $xml;
            }

            $pi = '<?xml-stylesheet type="text/css" href="' . htmlspecialchars($stylesheetHref, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"?>';
            $declaration = '<?xml version="1.0" encoding="UTF-8"?>';

            if (str_starts_with($xml, $declaration)) {
                return $declaration . "\n" . $pi . "\n" . substr($xml, strlen($declaration) + 1);
            }

            return $pi . "\n" . $xml;
        }
    }

    /**
     * Generate XML sitemap
     */
    function generateSitemap(PDO $pdo): string {
        $settings = function_exists('seoSettings') ? seoSettings() : [];
        $baseUrl = getBaseUrl();
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $seen = [];

        $appendUrl = static function (string $loc, ?string $lastmod, string $changefreq, string $priority) use (&$xml, &$seen): void {
            $loc = trim($loc);
            if ($loc === '' || isset($seen[$loc])) {
                return;
            }

            $seen[$loc] = true;
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
            if ($lastmod !== null && $lastmod !== '') {
                $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</changefreq>\n";
            $xml .= '    <priority>' . htmlspecialchars($priority, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</priority>\n";
            $xml .= "  </url>\n";
        };

        if (!function_exists('seoPublicPageShouldAppearInSitemap') || seoPublicPageShouldAppearInSitemap('home', $settings)) {
            $appendUrl(rtrim($baseUrl, '/') . '/', null, 'daily', '1.0');
        }

        $topicStatuses = function_exists('seoSitemapTopicStatuses') ? seoSitemapTopicStatuses($settings) : ['published'];
        if ($topicStatuses !== []) {
            $statusPlaceholders = implode(', ', array_fill(0, count($topicStatuses), '?'));
            $stmt = $pdo->prepare("
                SELECT id, slug, updated_at
                FROM topics
                WHERE status IN ($statusPlaceholders)
                ORDER BY updated_at DESC, id DESC
                LIMIT 5000
            ");
            $stmt->execute($topicStatuses);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (function_exists('seoTopicShouldAppearInSitemap') && !seoTopicShouldAppearInSitemap($row, $settings)) {
                    continue;
                }

                $url = topicUrlForRow($row);
                $lastmod = !empty($row['updated_at']) ? date('Y-m-d\TH:i:sP', strtotime((string) $row['updated_at']) ?: time()) : null;
                $appendUrl($url, $lastmod, 'weekly', '0.8');
            }
        }

        if (function_exists('getPublicCategoriesTree')) {
            $appendCategoryNode = null;
            $appendCategoryNode = static function (array $node) use (&$appendCategoryNode, &$appendUrl, $settings): void {
                if (!empty($node['slug']) && (!function_exists('seoCategoryShouldAppearInSitemap') || seoCategoryShouldAppearInSitemap($node, $settings))) {
                    $appendUrl(categoryUrl((string) $node['slug']), null, 'weekly', '0.7');
                }

                foreach (($node['children'] ?? []) as $child) {
                    if (is_array($child)) {
                        $appendCategoryNode($child);
                    }
                }
            };

            foreach (getPublicCategoriesTree($pdo) as $node) {
                if (is_array($node)) {
                    $appendCategoryNode($node);
                }
            }
        } else {
            $stmt = $pdo->query("
                SELECT cat.slug, COUNT(t.id) AS topic_count
                FROM categories cat
                LEFT JOIN topics t ON t.category_id = cat.id AND t.status = 'published' AND t.deleted_at IS NULL
                WHERE cat.parent_id IS NULL AND cat.status = 'active' AND cat.deleted_at IS NULL
                GROUP BY cat.id, cat.slug, cat.name, cat.display_order
                ORDER BY cat.name ASC, cat.id ASC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (function_exists('seoCategoryShouldAppearInSitemap') && !seoCategoryShouldAppearInSitemap($row, $settings)) {
                    continue;
                }

                $appendUrl(categoryUrl((string) $row['slug']), null, 'weekly', '0.7');
            }
        }

        $xml .= "</urlset>\n";

        $xml = function_exists('decorateSitemapXml') ? decorateSitemapXml($xml) : $xml;

        return formatSitemapXml($xml);
    }
}

if (!function_exists('seoGenerateSitemapOutput')) {
    function seoGenerateSitemapOutput(string $type, ?array $settings = null): string
    {
        $settings = function_exists('seoSettings')
            ? seoSettings($settings)
            : (is_array($settings) ? $settings : []);

        $normalizedType = strtolower(trim($type));
        $requestUri = match ($normalizedType) {
            'sitemap', 'sitemap.xml', 'index' => '/sitemap.xml',
            'topic', 'topic-sitemap', 'topic-sitemap.xml' => '/topic-sitemap.xml',
            'profile', 'profile-sitemap', 'profile-sitemap.xml' => '/profile-sitemap.xml',
            'image', 'image-sitemap', 'image-sitemap.xml' => '/image-sitemap.xml',
            default => '/' . ltrim($normalizedType, '/'),
        };

        $request = new \App\Core\Http\Request(
            'GET',
            $requestUri,
            [],
            [],
            '',
            ['REQUEST_URI' => $requestUri]
        );
        $pdo = $GLOBALS['pdo'] ?? null;
        $handler = match (true) {
            str_starts_with($normalizedType, 'profile') => new \App\Engine\Seo\Http\ProfileSitemapPage($settings, null, $pdo instanceof PDO ? $pdo : null),
            str_starts_with($normalizedType, 'image') => new \App\Engine\Seo\Http\ImageSitemapPage($settings, null, $pdo instanceof PDO ? $pdo : null),
            str_starts_with($normalizedType, 'topic') => new \App\Engine\Seo\Http\TopicSitemapPage($settings, null, $pdo instanceof PDO ? $pdo : null),
            default => new \App\Engine\Seo\Http\SitemapIndexPage($settings, null, $pdo instanceof PDO ? $pdo : null),
        };

        $response = $handler->handle($request);

        return $response instanceof \App\Core\Http\Response
            ? $response->getBody()
            : (string) $response;
    }
}

if (!function_exists('seoWriteSitemapCache')) {
    function seoWriteSitemapCache(string $cacheFile, string $output): void
    {
        $directory = dirname($cacheFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        file_put_contents($cacheFile, $output, LOCK_EX);
    }
}
