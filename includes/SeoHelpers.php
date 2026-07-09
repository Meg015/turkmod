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

if (!function_exists('seoSitemapCacheTtl')) {
    function seoSitemapCacheTtl(?array $settings = null, int $default = 3600): int
    {
        $settings = is_array($settings) ? $settings : [];

        return max(0, (int) ($settings['sitemap_cache_duration'] ?? $default));
    }
}

if (!function_exists('seoSitemapCacheKey')) {
    function seoSitemapCacheKey(string $type, array $signature = []): string
    {
        $payload = json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            $payload = serialize($signature);
        }

        $normalizedType = strtolower(trim($type));
        $normalizedType = preg_replace('/[^a-z0-9:_-]+/i', '-', $normalizedType) ?? 'sitemap';

        return 'seo-sitemap:v1:' . $normalizedType . ':' . hash('sha256', $payload);
    }
}

if (!function_exists('seoSitemapCacheValue')) {
    function seoSitemapCacheValue(string $body, int $lastModifiedTimestamp): array
    {
        return [
            'body' => $body,
            'last_modified_timestamp' => max(0, $lastModifiedTimestamp),
        ];
    }
}

if (!function_exists('seoSitemapCacheValueFromMixed')) {
    /**
     * @return array{body:string,last_modified_timestamp:int}|null
     */
    function seoSitemapCacheValueFromMixed(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $body = $value['body'] ?? null;
        $lastModifiedTimestamp = $value['last_modified_timestamp'] ?? null;
        if (!is_string($body) || !is_numeric($lastModifiedTimestamp)) {
            return null;
        }

        return [
            'body' => $body,
            'last_modified_timestamp' => max(0, (int) $lastModifiedTimestamp),
        ];
    }
}

if (!function_exists('seoSitemapCacheGet')) {
    /**
     * @return array{body:string,last_modified_timestamp:int}|null
     */
    function seoSitemapCacheGet(?\App\Core\Cache\TaggableCache $cache, string $key): ?array
    {
        if (!$cache instanceof \App\Core\Cache\TaggableCache) {
            return null;
        }

        return seoSitemapCacheValueFromMixed($cache->get($key));
    }
}

if (!function_exists('seoSitemapCacheSet')) {
    function seoSitemapCacheSet(
        ?\App\Core\Cache\TaggableCache $cache,
        string $key,
        string $body,
        int $lastModifiedTimestamp,
        int $ttlSeconds,
        array $tags = [],
    ): void {
        if (!$cache instanceof \App\Core\Cache\TaggableCache || $ttlSeconds <= 0) {
            return;
        }

        $cache->set(
            $key,
            seoSitemapCacheValue($body, $lastModifiedTimestamp),
            $ttlSeconds,
            array_values(array_unique(array_merge(['sitemap'], array_map('strval', $tags)))),
        );
    }
}

if (!function_exists('seoInvalidateSitemapCaches')) {
    function seoInvalidateSitemapCaches(?\App\Core\Cache\TaggableCache $cache = null): void
    {
        if (!$cache instanceof \App\Core\Cache\TaggableCache && class_exists(\App\Core\Bootstrap\Boot::class)) {
            try {
                $container = \App\Core\Bootstrap\Boot::container(dirname(__DIR__));
                $resolved = $container->get(\App\Core\Cache\TaggableCache::class);
                if ($resolved instanceof \App\Core\Cache\TaggableCache) {
                    $cache = $resolved;
                }
            } catch (\Throwable $exception) {
                return;
            }
        }

        if ($cache instanceof \App\Core\Cache\TaggableCache) {
            $cache->invalidateTag('sitemap');
        }
    }
}

if (!function_exists('seoNormalizeSitemapEtag')) {
    function seoNormalizeSitemapEtag(string $etag): string
    {
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            $etag = trim(substr($etag, 2));
        }

        return trim($etag, "\"' \t\n\r\0\x0B");
    }
}

if (!function_exists('seoSitemapRequestNotModified')) {
    function seoSitemapRequestNotModified(\App\Core\Http\Request $request, string $etag, int $lastModifiedTimestamp): bool
    {
        $ifNoneMatch = trim((string) $request->header('If-None-Match', ''));
        if ($ifNoneMatch !== '') {
            if ($ifNoneMatch === '*') {
                return true;
            }

            $normalizedEtag = seoNormalizeSitemapEtag($etag);
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (seoNormalizeSitemapEtag($candidate) === $normalizedEtag) {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = trim((string) $request->header('If-Modified-Since', ''));
        if ($ifModifiedSince !== '') {
            $modifiedSince = strtotime($ifModifiedSince);
            if ($modifiedSince !== false && $modifiedSince >= $lastModifiedTimestamp) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('seoPrepareSitemapXml')) {
    function seoPrepareSitemapXml(string $xml): string
    {
        if (!str_contains($xml, 'xml-stylesheet')) {
            $declaration = '<?xml version="1.0" encoding="UTF-8"?>';
            $stylesheet = '<?xml-stylesheet type="text/css" href="sitemap.css"?>';
            if (str_starts_with($xml, $declaration)) {
                $xml = $declaration . "\n" . $stylesheet . "\n" . substr($xml, strlen($declaration) + 1);
            } else {
                $xml = $stylesheet . "\n" . $xml;
            }
        }

        return function_exists('formatSitemapXml') ? formatSitemapXml($xml) : $xml;
    }
}

if (!function_exists('seoSitemapResponse')) {
    function seoSitemapResponse(
        \App\Core\Http\Request $request,
        string $preparedBody,
        int $lastModifiedTimestamp,
        int $cacheDuration = 600,
    ): \App\Core\Http\Response {
        $cacheDuration = max(0, $cacheDuration);
        if ($lastModifiedTimestamp <= 0) {
            $lastModifiedTimestamp = time();
        }
        $etag = '"' . hash('sha256', $preparedBody) . '"';
        $cacheControl = $cacheDuration > 0
            ? 'public, max-age=' . $cacheDuration . ', stale-while-revalidate=86400'
            : 'no-cache, must-revalidate';
        $expiresAt = time() + $cacheDuration;
        $headers = [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => $cacheControl,
            'Expires' => gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModifiedTimestamp) . ' GMT',
        ];

        if (seoSitemapRequestNotModified($request, $etag, $lastModifiedTimestamp)) {
            return new \App\Core\Http\Response('', 304, [
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => $cacheControl,
                'Expires' => $headers['Expires'],
                'ETag' => $etag,
                'Last-Modified' => $headers['Last-Modified'],
            ]);
        }

        return new \App\Core\Http\Response($preparedBody, 200, $headers);
    }
}
