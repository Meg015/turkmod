<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use Throwable;

final class TopicSitemapPage implements Handler
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
        private ?Closure $topicsResolver = null,
        private ?Closure $nowResolver = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');
        $page = $this->resolvePage($request);
        $maxUrlsPerSitemap = max(1, min(50000, (int) ($settings['sitemap_max_urls'] ?? 1000)));
        $latestLastmod = null;

        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        if ($page === 1) {
            $body .= $this->renderStaticEntries($settings, $canonicalBase);
        }

        foreach ($this->resolveTopics($settings, $page, $maxUrlsPerSitemap) as $topic) {
            $lastmod = (string) ($topic['updated_at'] ?? $topic['published_at'] ?? $this->now());
            $timestamp = strtotime($lastmod);
            if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                $latestLastmod = $timestamp;
            }

            $body .= $this->renderUrlEntry(
                $this->topicUrl($topic, $settings, $canonicalBase),
                date('Y-m-d', $timestamp !== false ? $timestamp : time()),
                (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                (string) ($settings['sitemap_priority_topics'] ?? '0.6'),
            );
        }

        $body .= '</urlset>' . "\n";

        return $this->xmlResponse($body, $latestLastmod);
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
        if (preg_match('/topic-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/topic-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? max(1, (int) $matches[1]) : 1;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function renderStaticEntries(array $settings, string $canonicalBase): string
    {
        $body = $this->renderUrlEntry(
            $this->canonicalUrl('/', $settings, $canonicalBase),
            null,
            'daily',
            (string) ($settings['sitemap_priority_home'] ?? '1.0'),
        );

        if ((string) ($settings['sitemap_include_categories'] ?? '1') !== '1') {
            return $body;
        }

        $changefreq = (string) ($settings['sitemap_changefreq'] ?? 'weekly');
        $body .= $this->renderUrlEntry(
            $this->canonicalUrl($this->categoryListPath(), $settings, $canonicalBase),
            null,
            $changefreq,
            '0.8',
        );

        foreach ($this->resolveCategoryTree() as $node) {
            $body .= $this->renderCategoryNode($node, '', $settings, $canonicalBase);
        }

        return $body;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolveCategoryTree(): array
    {
        if ($this->categoryTreeResolver instanceof Closure) {
            $tree = ($this->categoryTreeResolver)($this->resolvePdo());

            return is_array($tree) ? array_values(array_filter($tree, 'is_array')) : [];
        }

        $pdo = $this->resolvePdo();
        if (function_exists('getPublicCategoriesTree') && $pdo instanceof PDO) {
            $tree = getPublicCategoriesTree($pdo);

            return is_array($tree) ? array_values(array_filter($tree, 'is_array')) : [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $settings
     */
    private function renderCategoryNode(array $node, string $parentSlug, array $settings, string $canonicalBase): string
    {
        $slug = (string) ($node['slug'] ?? '');
        if ($slug === '') {
            return '';
        }

        $body = $this->renderUrlEntry(
            $this->canonicalUrl($this->categoryPath($slug, $parentSlug), $settings, $canonicalBase),
            null,
            (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
            (string) ($settings['sitemap_priority_categories'] ?? '0.7'),
        );

        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $body .= $this->renderCategoryNode($child, $slug, $settings, $canonicalBase);
            }
        }

        return $body;
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array<string,mixed>>
     */
    private function resolveTopics(array $settings, int $page, int $maxUrlsPerSitemap): array
    {
        if ($this->topicsResolver instanceof Closure) {
            $topics = ($this->topicsResolver)($settings, $this->resolvePdo(), $page, $maxUrlsPerSitemap);

            return is_array($topics) ? array_values(array_filter($topics, 'is_array')) : [];
        }

        return $this->loadTopics($settings, $page, $maxUrlsPerSitemap);
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array<string,mixed>>
     */
    private function loadTopics(array $settings, int $page, int $maxUrlsPerSitemap): array
    {
        $pdo = $this->resolvePdo();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $statuses = (string) ($settings['sitemap_exclude_drafts'] ?? '1') === '1'
            ? ['published']
            : ['published', 'draft'];
        $statusPlaceholders = implode(', ', array_fill(0, count($statuses), '?'));
        $offset = ($page - 1) * $maxUrlsPerSitemap;

        try {
            $statement = $pdo->prepare(
                'SELECT id, slug, updated_at, published_at FROM topics WHERE status IN ('
                . $statusPlaceholders
                . ') AND deleted_at IS NULL AND slug IS NOT NULL ORDER BY published_at DESC LIMIT ? OFFSET ?',
            );
            $parameter = 1;
            foreach ($statuses as $status) {
                $statement->bindValue($parameter, $status);
                $parameter++;
            }
            $statement->bindValue($parameter, $maxUrlsPerSitemap, PDO::PARAM_INT);
            $statement->bindValue($parameter + 1, $offset, PDO::PARAM_INT);
            $statement->execute();
            $topics = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($topics) ? array_values(array_filter($topics, 'is_array')) : [];
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
     * @param array<string,mixed> $topic
     * @param array<string,mixed> $settings
     */
    private function topicUrl(array $topic, array $settings, string $canonicalBase): string
    {
        if (function_exists('topicUrlForRow')) {
            return $this->canonicalUrl(topicUrlForRow($topic), $settings, $canonicalBase);
        }

        $slug = trim((string) ($topic['slug'] ?? ''), '/');
        $id = (string) ($topic['id'] ?? '');
        $path = '/konu/' . $slug . ($id !== '' ? '-' . $id : '');

        return $this->canonicalUrl($path, $settings, $canonicalBase);
    }

    private function categoryListPath(): string
    {
        return function_exists('categoryListUrl') ? (string) categoryListUrl() : '/kategori';
    }

    private function categoryPath(string $slug, string $parentSlug): string
    {
        if (function_exists('categoryUrl')) {
            return (string) categoryUrl($slug, $parentSlug);
        }

        return '/kategori/' . ($parentSlug !== '' ? trim($parentSlug, '/') . '/' : '') . trim($slug, '/');
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

    private function xmlResponse(string $body, ?int $lastModifiedTimestamp): Response
    {
        $expiresAt = ($lastModifiedTimestamp ?? time()) + 600;

        return new Response($body, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=86400',
            'Expires' => gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'ETag' => '"' . hash('sha256', $body) . '"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModifiedTimestamp ?? time()) . ' GMT',
        ]);
    }
}
