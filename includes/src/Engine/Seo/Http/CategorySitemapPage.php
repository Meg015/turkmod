<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Cache\TaggableCache;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Engine\Seo\Support\SitemapInventory;
use Closure;
use PDO;

final class CategorySitemapPage implements Handler
{
    /**
     * @param array<string,mixed>|null $settings
     */
    public function __construct(
        private ?array $settings = null,
        private ?string $canonicalBase = null,
        private ?PDO $pdo = null,
        private ?Closure $settingsResolver = null,
        private ?Closure $categoryTreeResolver = null,
        private ?Closure $nowResolver = null,
        private ?TaggableCache $cache = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');
        $page = $this->resolvePage($request);
        $inventory = new SitemapInventory($this->resolvePdo(), $this->categoryTreeResolver);
        if (!$inventory->pageIsValid('category', $page, $settings)) {
            return seoSitemapNotFoundResponse();
        }

        $cacheDuration = seoSitemapCacheTtl($settings);
        $cacheKey = seoSitemapCacheKey('category-sitemap:v3', [
            'base' => $canonicalBase,
            'page' => $page,
            'settings' => $settings,
        ]);
        $cached = seoSitemapCacheGet($this->cache, $cacheKey);
        if ($cached !== null) {
            return seoSitemapResponse($request, $cached['body'], $cached['last_modified_timestamp'], $cacheDuration);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $latestLastmod = null;

        foreach ($inventory->categoryPage($settings, $page) as $entry) {
            $node = $entry['node'];
            $slug = trim((string) ($node['slug'] ?? ''));
            $lastmod = trim((string) ($node['updated_at'] ?? $node['created_at'] ?? ''));
            $timestamp = $lastmod !== '' ? strtotime($lastmod) : false;
            if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                $latestLastmod = $timestamp;
            }

            $body .= $this->renderUrlEntry(
                $this->canonicalUrl((string) categoryUrl($slug, $entry['parent_slug']), $settings, $canonicalBase),
                $timestamp !== false ? date('Y-m-d\TH:i:sP', $timestamp) : null,
                (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                (string) ($settings['sitemap_priority_categories'] ?? '0.7'),
            );
        }
        $body .= '</urlset>' . "\n";

        $preparedBody = seoPrepareSitemapXml($body);
        $lastModifiedTimestamp = $latestLastmod ?? (strtotime($this->now()) ?: time());
        seoSitemapCacheSet(
            $this->cache,
            $cacheKey,
            $preparedBody,
            $lastModifiedTimestamp,
            $cacheDuration,
            ['sitemap:category'],
        );

        return seoSitemapResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
    }

    /** @return array<string,mixed> */
    private function resolveSettings(): array
    {
        if ($this->settingsResolver instanceof Closure) {
            $settings = ($this->settingsResolver)();

            return is_array($settings) ? $settings : [];
        }

        $pdo = $this->resolvePdo();
        if (function_exists('getAdminSettings') && $pdo instanceof PDO) {
            return getAdminSettings($pdo);
        }

        return [];
    }

    /** @param array<string,mixed> $settings */
    private function resolveCanonicalBase(array $settings): string
    {
        return function_exists('seoCanonicalBase') ? (string) seoCanonicalBase($settings) : '';
    }

    private function resolvePdo(): ?PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $pdo = $GLOBALS['pdo'] ?? null;

        return $pdo instanceof PDO ? $pdo : null;
    }

    private function resolvePage(Request $request): int
    {
        $path = $request->getPath();
        if (preg_match('/category-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/category-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? (int) $matches[1] : 1;
    }

    /** @param array<string,mixed> $settings */
    private function canonicalUrl(string $path, array $settings, string $canonicalBase): string
    {
        if (function_exists('seoCanonicalUrl')) {
            return (string) seoCanonicalUrl($path, $settings);
        }

        return rtrim($canonicalBase, '/') . '/' . ltrim($path, '/');
    }

    private function renderUrlEntry(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $body = '    <url>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        if ($lastmod !== null) {
            $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
        }
        $body .= '        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>' . "\n";
        $body .= '        <priority>' . htmlspecialchars($priority, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>' . "\n";
        $body .= '    </url>' . "\n";

        return $body;
    }

    private function now(): string
    {
        if ($this->nowResolver instanceof Closure) {
            return (string) ($this->nowResolver)();
        }

        return date('Y-m-d\TH:i:sP');
    }
}
