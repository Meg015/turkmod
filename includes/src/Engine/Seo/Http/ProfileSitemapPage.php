<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Cache\TaggableCache;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use Throwable;

final class ProfileSitemapPage implements Handler
{
    /**
     * @param array<string,mixed>|null $settings
     */
    public function __construct(
        private ?array $settings = null,
        private ?string $canonicalBase = null,
        private ?PDO $pdo = null,
        private ?Closure $settingsResolver = null,
        private ?Closure $profilesResolver = null,
        private ?Closure $nowResolver = null,
        private ?TaggableCache $cache = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');
        $maxUrlsPerSitemap = max(1, min(50000, (int) ($settings['sitemap_max_urls'] ?? 1000)));
        $latestLastmod = null;
        $cacheDuration = seoSitemapCacheTtl($settings);
        $cacheKey = seoSitemapCacheKey('profile-sitemap', [
            'base' => $canonicalBase,
            'page' => $this->resolvePage($request),
            'settings' => $settings,
        ]);
        $cached = seoSitemapCacheGet($this->cache, $cacheKey);
        if ($cached !== null) {
            return $this->xmlResponse($request, $cached['body'], $cached['last_modified_timestamp'], $cacheDuration);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        if (function_exists('seoPublicPageShouldAppearInSitemap') && !seoPublicPageShouldAppearInSitemap('public_profile', $settings)) {
            $body .= '</urlset>' . "\n";
            $preparedBody = seoPrepareSitemapXml($body);
            $lastModifiedTimestamp = strtotime($this->now()) ?: time();
            seoSitemapCacheSet($this->cache, $cacheKey, $preparedBody, $lastModifiedTimestamp, $cacheDuration, ['sitemap:profile']);

            return $this->xmlResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
        }

        if ((string) ($settings['sitemap_enabled'] ?? '1') === '1') {
            $body .= "\n";
            foreach ($this->resolveProfiles($this->resolvePage($request), $maxUrlsPerSitemap) as $profile) {
                $lastmod = (string) ($profile['updated_at'] ?? $profile['created_at'] ?? $this->now());
                $timestamp = strtotime($lastmod);
                if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                    $latestLastmod = $timestamp;
                }

                $body .= $this->renderUrlEntry(
                    $this->profileUrl($profile, $settings, $canonicalBase),
                    date('Y-m-d\TH:i:sP', $timestamp !== false ? $timestamp : time()),
                    (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                    '0.5',
                );
            }
        }

        $body .= '</urlset>' . "\n";
        $preparedBody = seoPrepareSitemapXml($body);
        $lastModifiedTimestamp = $latestLastmod ?? (strtotime($this->now()) ?: time());
        seoSitemapCacheSet($this->cache, $cacheKey, $preparedBody, $lastModifiedTimestamp, $cacheDuration, ['sitemap:profile']);

        return $this->xmlResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
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
        if (preg_match('/profile-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/profile-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? max(1, (int) $matches[1]) : 1;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolveProfiles(int $page, int $maxUrlsPerSitemap): array
    {
        if ($this->profilesResolver instanceof Closure) {
            $profiles = ($this->profilesResolver)($this->resolvePdo(), $page, $maxUrlsPerSitemap);

            return is_array($profiles) ? array_values(array_filter($profiles, 'is_array')) : [];
        }

        return $this->loadProfiles($page, $maxUrlsPerSitemap);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadProfiles(int $page, int $maxUrlsPerSitemap): array
    {
        $pdo = $this->resolvePdo();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $offset = ($page - 1) * $maxUrlsPerSitemap;

        try {
            $statement = $pdo->prepare(
                "SELECT id, username, updated_at, created_at
                 FROM users
                 WHERE status = 'active'
                   AND public_profile = 1
                   AND deleted_at IS NULL
                   AND (is_banned = 0 OR is_banned IS NULL)
                   AND username IS NOT NULL
                   AND TRIM(username) <> ''
                 ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
                 LIMIT :limit OFFSET :offset",
            );
            $statement->bindValue(':limit', $maxUrlsPerSitemap, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $profiles = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($profiles) ? array_values(array_filter($profiles, 'is_array')) : [];
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => self::class]);
            } else {
                error_log($exception->getMessage());
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $settings
     */
    private function profileUrl(array $profile, array $settings, string $canonicalBase): string
    {
        if (function_exists('publicProfileUrl')) {
            return $this->canonicalUrl(publicProfileUrl([
                'id' => (int) ($profile['id'] ?? 0),
                'username' => (string) ($profile['username'] ?? ''),
            ]), $settings, $canonicalBase);
        }

        $id = (int) ($profile['id'] ?? 0);
        $username = (string) ($profile['username'] ?? 'uye');
        $slug = $id . '-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($username)), '-');

        return $this->canonicalUrl('/profil/' . $slug, $settings, $canonicalBase);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function canonicalUrl(string $path, array $settings, string $canonicalBase): string
    {
        if (function_exists('seoCanonicalUrl')) {
            return (string) seoCanonicalUrl($path, $settings);
        }

        return rtrim($canonicalBase, '/') . '/' . ltrim($path, '/');
    }

    private function renderUrlEntry(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        $body = '    <url>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
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

    private function xmlResponse(Request $request, string $preparedBody, int $lastModifiedTimestamp, int $cacheDuration): Response
    {
        return seoSitemapResponse($request, $preparedBody, $lastModifiedTimestamp, $cacheDuration);
    }
}
