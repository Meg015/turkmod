<?php

declare(strict_types=1);

namespace App\Engine\Seo\Support;

use Closure;
use PDO;
use Throwable;

final class SitemapInventory
{
    /** @var array<string,list<array{node:array<string,mixed>,parent_slug:string}>> */
    private array $categoryEntriesCache = [];

    /** @var array<string,list<array<string,mixed>>> */
    private array $imageTopicsCache = [];

    /** @var array<string,list<array{loc:string,priority:string,changefreq:string,lastmod?:string}>> */
    private array $pageEntriesCache = [];

    public function __construct(
        private ?PDO $pdo = null,
        private ?Closure $categoryTreeResolver = null,
    ) {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function maxUrls(array $settings): int
    {
        return max(1, min(50000, (int) ($settings['sitemap_max_urls'] ?? 1000)));
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function maxImages(array $settings): int
    {
        return max(1, min(1000, (int) ($settings['image_sitemap_max_images'] ?? 20)));
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function sitemapEnabled(array $settings): bool
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
     * @param array<string,mixed> $settings
     */
    public function typeEnabled(string $type, array $settings): bool
    {
        if (!$this->sitemapEnabled($settings)) {
            return false;
        }

        return match ($type) {
            'page' => !function_exists('seoPublicPageShouldAppearInSitemap')
                || seoPublicPageShouldAppearInSitemap('home', $settings)
                || count($this->pageEntries($settings)) > 0,
            'category' => (string) ($settings['sitemap_include_categories'] ?? '1') === '1'
                && (!function_exists('seoPublicPageShouldAppearInSitemap')
                    || seoPublicPageShouldAppearInSitemap('category', $settings)),
            'topic' => $this->topicStatuses($settings) !== [],
            'profile' => !function_exists('seoPublicPageShouldAppearInSitemap')
                || seoPublicPageShouldAppearInSitemap('public_profile', $settings),
            'image' => (string) ($settings['image_sitemap_enabled'] ?? '1') === '1'
                && $this->imageSourceEnabled($settings)
                && $this->topicStatuses($settings) !== [],
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function total(string $type, array $settings): int
    {
        if (!$this->typeEnabled($type, $settings)) {
            return 0;
        }

        return match ($type) {
            'page' => count($this->pageEntries($settings)),
            'category' => count($this->categoryEntries($settings)),
            'topic' => $this->countTopics($settings),
            'profile' => $this->countProfiles(),
            'image' => count($this->imageTopics($settings)),
            default => 0,
        };
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function pageCount(string $type, array $settings): int
    {
        $total = $this->total($type, $settings);

        return $total > 0 ? (int) ceil($total / self::maxUrls($settings)) : 0;
    }

    /**
     * Disabled sitemap types keep only their canonical first page as an empty XML document.
     *
     * @param array<string,mixed> $settings
     */
    public function pageIsValid(string $type, int $page, array $settings): bool
    {
        if ($page < 1) {
            return false;
        }

        if (!$this->typeEnabled($type, $settings)) {
            return $page === 1;
        }

        return $page <= $this->pageCount($type, $settings);
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    public function indexUrls(array $settings, string $canonicalBase): array
    {
        if (!$this->sitemapEnabled($settings)) {
            return [];
        }

        $canonicalBase = rtrim($canonicalBase, '/');
        $urls = [];
        foreach (['page', 'category', 'topic', 'image', 'profile'] as $type) {
            if (!$this->typeEnabled($type, $settings)) {
                continue;
            }
            $pages = max(1, $this->pageCount($type, $settings));
            for ($page = 1; $page <= $pages; $page++) {
                $name = $type . '-sitemap' . ($page === 1 ? '' : '-' . $page) . '.xml';
                $urls[] = $canonicalBase . '/' . $name;
            }
        }

        return $urls;
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array{loc:string,priority:string,changefreq:string,lastmod?:string}>
     */
    public function pagePage(array $settings, int $page): array
    {
        if (!$this->typeEnabled('page', $settings)) {
            return [];
        }

        $limit = self::maxUrls($settings);

        return array_slice($this->pageEntries($settings), ($page - 1) * $limit, $limit);
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array{loc:string,priority:string,changefreq:string,lastmod?:string}>
     */
    public function pageEntries(array $settings): array
    {
        $cacheKey = $this->settingsKey($settings);
        if (isset($this->pageEntriesCache[$cacheKey])) {
            return $this->pageEntriesCache[$cacheKey];
        }

        if (!function_exists('seoPublicPageCatalog') || !function_exists('seoPublicPageShouldAppearInSitemap')) {
            return $this->pageEntriesCache[$cacheKey] = [];
        }

        $catalog = seoPublicPageCatalog($settings);
        $entries = [];
        $skipKeys = ['topic', 'category', 'profile', 'public_profile', 'search'];

        foreach ($catalog as $pageKey => $item) {
            if (in_array($pageKey, $skipKeys, true)) {
                continue;
            }
            if (!seoPublicPageShouldAppearInSitemap((string) $pageKey, $settings)) {
                continue;
            }

            if ($pageKey === 'home') {
                $loc = function_exists('seoCanonicalUrl') ? seoCanonicalUrl('/', $settings) : '/';
            } else {
                $path = (string) ($item['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $loc = function_exists('seoCanonicalUrl') ? seoCanonicalUrl($path, $settings) : $path;
            }

            $priority = function_exists('seoPublicPageSitemapPriority')
                ? seoPublicPageSitemapPriority((string) $pageKey, $settings)
                : '0.5';

            $entries[] = [
                'loc' => $loc,
                'priority' => (string) $priority,
                'changefreq' => (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
            ];
        }

        if ($this->pdo instanceof PDO && class_exists(\App\Modules\StaticPages\Services\StaticPageService::class)) {
            try {
                $service = new \App\Modules\StaticPages\Services\StaticPageService($this->pdo);
                $knownLocations = array_fill_keys(array_map(static fn (array $entry): string => (string) ($entry['loc'] ?? ''), $entries), true);
                foreach ($service->sitemapPages() as $page) {
                    $path = '/' . rawurlencode((string) ($page['slug'] ?? ''));
                    $loc = function_exists('seoCanonicalUrl') ? seoCanonicalUrl($path, $settings) : $path;
                    if ($loc === '' || isset($knownLocations[$loc])) {
                        continue;
                    }
                    $entries[] = [
                        'loc' => $loc,
                        'priority' => '0.5',
                        'changefreq' => (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                        'lastmod' => (string) ($page['updated_at'] ?? $page['published_at'] ?? ''),
                    ];
                    $knownLocations[$loc] = true;
                }
            } catch (Throwable $exception) {
                if (function_exists('appLogException')) {
                    appLogException($exception, ['source' => self::class . ' static pages']);
                }
            }
        }

        return $this->pageEntriesCache[$cacheKey] = $entries;
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array{node:array<string,mixed>,parent_slug:string}>
     */
    public function categoryPage(array $settings, int $page): array
    {
        if (!$this->typeEnabled('category', $settings)) {
            return [];
        }

        $limit = self::maxUrls($settings);

        return array_slice($this->categoryEntries($settings), ($page - 1) * $limit, $limit);
    }

    /**
     * Image topics contain an `image_paths` list produced by the same rules used for inventory counts.
     *
     * @param array<string,mixed> $settings
     * @return list<array<string,mixed>>
     */
    public function imageTopicPage(array $settings, int $page): array
    {
        if (!$this->typeEnabled('image', $settings)) {
            return [];
        }

        $limit = self::maxUrls($settings);

        return array_slice($this->imageTopics($settings), ($page - 1) * $limit, $limit);
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array{node:array<string,mixed>,parent_slug:string}>
     */
    private function categoryEntries(array $settings): array
    {
        $cacheKey = $this->settingsKey($settings);
        if (isset($this->categoryEntriesCache[$cacheKey])) {
            return $this->categoryEntriesCache[$cacheKey];
        }

        $entries = [];
        foreach ($this->resolveCategoryTree() as $node) {
            $this->flattenCategoryNode($node, '', $settings, $entries);
        }

        return $this->categoryEntriesCache[$cacheKey] = $entries;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $settings
     * @param list<array{node:array<string,mixed>,parent_slug:string}> $entries
     */
    private function flattenCategoryNode(array $node, string $parentSlug, array $settings, array &$entries): void
    {
        $slug = trim((string) ($node['slug'] ?? ''));
        if ($slug === '') {
            return;
        }

        if (!function_exists('seoCategoryShouldAppearInSitemap') || seoCategoryShouldAppearInSitemap($node, $settings)) {
            $entries[] = ['node' => $node, 'parent_slug' => $parentSlug];
        }

        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $this->flattenCategoryNode($child, $slug, $settings, $entries);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private function resolveCategoryTree(): array
    {
        if ($this->categoryTreeResolver instanceof Closure) {
            $tree = ($this->categoryTreeResolver)($this->pdo);

            return is_array($tree) ? array_values(array_filter($tree, 'is_array')) : [];
        }

        if (function_exists('getPublicCategoriesTree') && $this->pdo instanceof PDO) {
            $tree = getPublicCategoriesTree($this->pdo);

            return is_array($tree) ? array_values(array_filter($tree, 'is_array')) : [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function countTopics(array $settings): int
    {
        if (!$this->pdo instanceof PDO) {
            return 0;
        }

        $statuses = $this->topicStatuses($settings);
        if ($statuses === []) {
            return 0;
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM topics WHERE status IN (' . $placeholders
                . ") AND deleted_at IS NULL AND slug IS NOT NULL AND TRIM(slug) <> ''",
            );
            $statement->execute($statuses);

            return max(0, (int) $statement->fetchColumn());
        } catch (Throwable $exception) {
            $this->log($exception, 'topic_count');

            return 0;
        }
    }

    private function countProfiles(): int
    {
        if (!$this->pdo instanceof PDO) {
            return 0;
        }

        try {
            $statement = $this->pdo->query(
                "SELECT COUNT(*) FROM users
                 WHERE status = 'active'
                   AND public_profile = 1
                   AND deleted_at IS NULL
                   AND (is_banned = 0 OR is_banned IS NULL)
                   AND username IS NOT NULL
                   AND TRIM(username) <> ''",
            );

            return max(0, (int) $statement->fetchColumn());
        } catch (Throwable $exception) {
            $this->log($exception, 'profile_count');

            return 0;
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<array<string,mixed>>
     */
    private function imageTopics(array $settings): array
    {
        $cacheKey = $this->settingsKey($settings);
        if (isset($this->imageTopicsCache[$cacheKey])) {
            return $this->imageTopicsCache[$cacheKey];
        }
        if (!$this->pdo instanceof PDO) {
            return $this->imageTopicsCache[$cacheKey] = [];
        }

        $statuses = $this->topicStatuses($settings);
        if ($statuses === []) {
            return $this->imageTopicsCache[$cacheKey] = [];
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $topicStatement = $this->pdo->prepare(
                'SELECT t.id, t.slug, t.title, t.status, t.updated_at, t.published_at,
                        pm.path AS primary_media_path, pm.type AS primary_media_type,
                        pm.mime_type AS primary_media_mime_type
                 FROM topics t
                 LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                 WHERE t.status IN (' . $placeholders . ")
                   AND t.deleted_at IS NULL
                   AND t.slug IS NOT NULL
                   AND TRIM(t.slug) <> ''
                 ORDER BY t.published_at DESC, t.id DESC",
            );
            $topicStatement->execute($statuses);
            $topics = $topicStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $mediaStatement = $this->pdo->prepare(
                'SELECT mf.topic_id, mf.path, mf.original_name, mf.type, mf.mime_type,
                        mf.is_primary, mf.display_order, mf.id
                 FROM media_files mf
                 INNER JOIN topics t ON t.id = mf.topic_id
                 WHERE t.status IN (' . $placeholders . ")
                   AND t.deleted_at IS NULL
                   AND t.slug IS NOT NULL
                   AND TRIM(t.slug) <> ''
                 ORDER BY mf.topic_id ASC, mf.is_primary DESC, mf.display_order ASC, mf.id ASC",
            );
            $mediaStatement->execute($statuses);
            $mediaRows = $mediaStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->log($exception, 'image_topics');

            return $this->imageTopicsCache[$cacheKey] = [];
        }

        $groupedMedia = [];
        foreach ($mediaRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $topicId = (int) ($row['topic_id'] ?? 0);
            if ($topicId > 0) {
                $groupedMedia[$topicId][] = $row;
            }
        }

        $eligible = [];
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $topicId = (int) ($topic['id'] ?? 0);
            $paths = $this->collectImagePaths($topic, $groupedMedia[$topicId] ?? [], $settings);
            if ($paths === []) {
                continue;
            }
            $topic['image_paths'] = $paths;
            $eligible[] = $topic;
        }

        return $this->imageTopicsCache[$cacheKey] = $eligible;
    }

    /**
     * @param array<string,mixed> $topic
     * @param list<array<string,mixed>> $mediaRows
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    private function collectImagePaths(array $topic, array $mediaRows, array $settings): array
    {
        $maxImages = self::maxImages($settings);
        $paths = [];
        $seen = [];
        $append = function (array $row) use (&$paths, &$seen, $settings, $maxImages): void {
            $path = trim((string) ($row['path'] ?? ''));
            if (
                $path === ''
                || isset($seen[$path])
                || count($paths) >= $maxImages
                || !$this->isImageRow($row)
                || !$this->imagePathIsCrawlable($path, $settings)
            ) {
                return;
            }

            $seen[$path] = true;
            $paths[] = $path;
        };

        if ((string) ($settings['image_sitemap_hero'] ?? '1') === '1') {
            $append([
                'path' => $topic['primary_media_path'] ?? '',
                'type' => $topic['primary_media_type'] ?? '',
                'mime_type' => $topic['primary_media_mime_type'] ?? '',
            ]);
        }

        if (
            (string) ($settings['image_sitemap_inline'] ?? '1') === '1'
            || (string) ($settings['image_sitemap_media'] ?? '1') === '1'
        ) {
            foreach ($mediaRows as $row) {
                $append($row);
            }
        }

        return $paths;
    }

    /** @param array<string,mixed> $row */
    private function isImageRow(array $row): bool
    {
        $path = trim((string) ($row['path'] ?? ''));
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        $mimeType = strtolower(trim((string) ($row['mime_type'] ?? '')));

        return $type === 'image'
            || str_starts_with($mimeType, 'image/')
            || ($path !== '' && preg_match('/\.(?:avif|gif|jpe?g|png|svg|webp)(?:[?#].*)?$/i', $path) === 1);
    }

    /** @param array<string,mixed> $settings */
    private function imagePathIsCrawlable(string $path, array $settings): bool
    {
        return trim($path) !== '';
    }

    /** @param array<string,mixed> $settings */
    private function imageSourceEnabled(array $settings): bool
    {
        return (string) ($settings['image_sitemap_hero'] ?? '1') === '1'
            || (string) ($settings['image_sitemap_inline'] ?? '1') === '1'
            || (string) ($settings['image_sitemap_media'] ?? '1') === '1';
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    private function topicStatuses(array $settings): array
    {
        if (function_exists('seoSitemapTopicStatuses')) {
            $statuses = seoSitemapTopicStatuses($settings);

            return is_array($statuses) ? array_values(array_map('strval', $statuses)) : [];
        }

        return (string) ($settings['sitemap_exclude_drafts'] ?? '1') === '1'
            ? ['published']
            : ['published', 'draft'];
    }

    /** @param array<string,mixed> $settings */
    private function settingsKey(array $settings): string
    {
        $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', is_string($encoded) ? $encoded : serialize($settings));
    }

    private function log(Throwable $exception, string $scope): void
    {
        if (function_exists('appLogException')) {
            appLogException($exception, ['source' => self::class, 'scope' => $scope]);
        } else {
            error_log($exception->getMessage());
        }
    }
}
