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

final class SitemapIndexPage implements Handler
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
        $cacheDuration = seoSitemapCacheTtl($settings);
        $cacheKey = seoSitemapCacheKey('sitemap-index:v7', [
            'base' => $canonicalBase,
            'settings' => $settings,
        ]);
        $cached = seoSitemapCacheGet($this->cache, $cacheKey);
        if ($cached !== null) {
            return seoSitemapResponse($request, $cached['body'], $cached['last_modified_timestamp'], $cacheDuration);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $nowFormatted = $this->now();

        if ($this->sitemapIsEnabled($settings)) {
            $inventory = new SitemapInventory($this->resolvePdo());
            foreach ($inventory->indexUrls($settings, $canonicalBase) as $url) {
                $body .= $this->renderSitemapEntry($url, $nowFormatted);
            }
        }
        $body .= '</sitemapindex>' . "\n";

        $preparedBody = seoPrepareSitemapXml($body);
        $lastModifiedTimestamp = strtotime($nowFormatted) ?: time();
        seoSitemapCacheSet($this->cache, $cacheKey, $preparedBody, $lastModifiedTimestamp, $cacheDuration, ['sitemap:index']);

        return seoSitemapResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function sitemapIsEnabled(array $settings): bool
    {
        if ((string) ($settings['sitemap_enabled'] ?? '1') !== '1') {
            return false;
        }

        if (function_exists('seoIndexToggleValue')) {
            return seoIndexToggleValue($settings, 'allow_indexing', '1') === '1';
        }

        return (string) ($settings['allow_indexing'] ?? '1') === '1';
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

    private function renderSitemapEntry(string $url, ?string $lastmod = null): string
    {
        $body = '    <sitemap>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        if ($lastmod !== null && $lastmod !== '') {
            $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
        }
        $body .= '    </sitemap>' . "\n";

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
