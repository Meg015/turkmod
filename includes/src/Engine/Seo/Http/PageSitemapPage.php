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

final class PageSitemapPage implements Handler
{
    /**
     * @param array<string,mixed>|null $settings
     */
    public function __construct(
        private ?array $settings = null,
        private ?string $canonicalBase = null,
        private ?PDO $pdo = null,
        private ?Closure $settingsResolver = null,
        private ?Closure $nowResolver = null,
        private ?TaggableCache $cache = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');
        $page = $this->resolvePage($request);
        $inventory = new SitemapInventory($this->resolvePdo());
        if (!$inventory->pageIsValid('page', $page, $settings)) {
            return seoSitemapNotFoundResponse();
        }

        $cacheDuration = seoSitemapCacheTtl($settings);
        $cacheKey = seoSitemapCacheKey('page-sitemap:v1', [
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
        $maxUrlsPerSitemap = SitemapInventory::maxUrls($settings);

        foreach ($inventory->pagePage($settings, $page) as $entry) {
            $lastmod = trim((string) ($entry['lastmod'] ?? ''));
            $timestamp = $lastmod !== '' ? strtotime($lastmod) : false;
            if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                $latestLastmod = $timestamp;
            }

            $body .= $this->renderUrlEntry(
                (string) ($entry['loc'] ?? ''),
                $timestamp !== false ? date('Y-m-d\TH:i:sP', $timestamp) : null,
                (string) ($entry['changefreq'] ?? ($settings['sitemap_changefreq'] ?? 'weekly')),
                (string) ($entry['priority'] ?? '0.5'),
            );
        }

        $body .= '</urlset>' . "\n";
        $preparedBody = seoPrepareSitemapXml($body);
        $lastModifiedTimestamp = $latestLastmod ?? (strtotime($this->now()) ?: time());
        seoSitemapCacheSet($this->cache, $cacheKey, $preparedBody, $lastModifiedTimestamp, $cacheDuration, ['sitemap:page']);

        return seoSitemapResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
    }

    /**
     * @return array<string,mixed>
     */
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

    /**
     * @param array<string,mixed> $settings
     */
    private function resolveCanonicalBase(array $settings): string
    {
        if (function_exists('seoCanonicalBase')) {
            return (string) seoCanonicalBase($settings);
        }

        return '';
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
        if (preg_match('/page-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/page-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? (int) $matches[1] : 1;
    }

    private function renderUrlEntry(string $url, ?string $lastmod, string $changefreq, string $priority): string
    {
        $body = '    <url>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        if ($lastmod !== null && $lastmod !== '') {
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
