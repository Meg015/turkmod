<?php

declare(strict_types=1);

require_once __DIR__ . '/SeoPublicPages.php';

/**
 * SEO Helper Functions
 * Meta Tags, Open Graph, Twitter Cards, Structured Data
 */

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
        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        }
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
