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
            mkdir($directory, 0775, true);
        }

        file_put_contents($cacheFile, $output, LOCK_EX);
    }
}
