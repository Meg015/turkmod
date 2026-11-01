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

final class ImageSitemapPage implements Handler
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
        if (!$inventory->pageIsValid('image', $page, $settings)) {
            return seoSitemapNotFoundResponse();
        }

        $cacheDuration = seoSitemapCacheTtl($settings);
        $cacheKey = seoSitemapCacheKey('image-sitemap:v4', [
            'base' => $canonicalBase,
            'page' => $page,
            'settings' => $settings,
        ]);
        $cached = seoSitemapCacheGet($this->cache, $cacheKey);
        if ($cached !== null) {
            return seoSitemapResponse($request, $cached['body'], $cached['last_modified_timestamp'], $cacheDuration);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $body .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        $latestLastmod = null;

        if ($inventory->typeEnabled('image', $settings)) {
            foreach ($inventory->imageTopicPage($settings, $page) as $topic) {
                $lastmod = trim((string) ($topic['updated_at'] ?? $topic['published_at'] ?? ''));
                $timestamp = $lastmod !== '' ? strtotime($lastmod) : false;
                if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                    $latestLastmod = $timestamp;
                }

                $images = [];
                foreach (($topic['image_paths'] ?? []) as $path) {
                    $path = trim((string) $path);
                    if ($path === '') {
                        continue;
                    }
                    $images[] = filter_var($path, FILTER_VALIDATE_URL)
                        ? $path
                        : $this->canonicalUrl($path, $settings, $canonicalBase);
                }

                if ($images !== []) {
                    $body .= $this->renderUrlEntry(
                        $this->topicUrl($topic, $settings, $canonicalBase),
                        $timestamp !== false ? date('Y-m-d\TH:i:sP', $timestamp) : null,
                        (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                        (string) ($settings['sitemap_priority_topics'] ?? '0.6'),
                        $images,
                        trim((string) ($topic['title'] ?? '')),
                    );
                }
            }
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
            ['sitemap:image'],
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
        if (preg_match('/image-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/image-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? (int) $matches[1] : 1;
    }

    /** @param array<string,mixed> $topic @param array<string,mixed> $settings */
    private function topicUrl(array $topic, array $settings, string $canonicalBase): string
    {
        return $this->canonicalUrl(topicUrlForRow($topic), $settings, $canonicalBase);
    }

    /** @param array<string,mixed> $settings */
    private function canonicalUrl(string $path, array $settings, string $canonicalBase): string
    {
        if (function_exists('seoCanonicalUrl')) {
            return (string) seoCanonicalUrl($path, $settings);
        }

        return rtrim($canonicalBase, '/') . '/' . ltrim($path, '/');
    }

    /** @param list<string> $images */
    private function renderUrlEntry(
        string $loc,
        ?string $lastmod,
        string $changefreq,
        string $priority,
        array $images,
        string $title = '',
    ): string {
        $body = '    <url>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        if ($lastmod !== null) {
            $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
        }
        $body .= '        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>' . "\n";
        $body .= '        <priority>' . htmlspecialchars($priority, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>' . "\n";
        foreach ($images as $image) {
            $body .= '        <image:image>' . "\n";
            $body .= '            <image:loc>' . htmlspecialchars($image, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</image:loc>' . "\n";
            if ($title !== '') {
                $body .= '            <image:title>' . htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</image:title>' . "\n";
            }
            $body .= '        </image:image>' . "\n";
        }
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
