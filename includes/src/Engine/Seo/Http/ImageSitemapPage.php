<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use Throwable;

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
        private ?Closure $topicsResolver = null,
        private ?Closure $primaryMediaResolver = null,
        private ?Closure $galleryResolver = null,
        private ?Closure $mediaFilesResolver = null,
        private ?Closure $nowResolver = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');

        if ((string) ($settings['image_sitemap_enabled'] ?? '1') !== '1') {
            return $this->xmlResponse(
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n",
                null,
            );
        }

        $maxUrlsPerSitemap = max(1, min(50000, (int) ($settings['sitemap_max_urls'] ?? 1000)));
        $maxImages = (int) ($settings['image_sitemap_max_images'] ?? 20);
        $latestLastmod = null;

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $body .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($this->resolveTopics($settings, $this->resolvePage($request), $maxUrlsPerSitemap) as $topic) {
            $images = $this->collectImages($topic, $settings, $canonicalBase, $maxImages);
            if ($images === []) {
                continue;
            }

            $lastmod = (string) ($topic['updated_at'] ?? $topic['published_at'] ?? $this->now());
            $timestamp = strtotime($lastmod);
            if ($timestamp !== false && ($latestLastmod === null || $timestamp > $latestLastmod)) {
                $latestLastmod = $timestamp;
            }

            $body .= $this->renderUrlEntry(
                $this->topicUrl($topic, $settings, $canonicalBase),
                date('Y-m-d\TH:i:sP', $timestamp !== false ? $timestamp : time()),
                (string) ($settings['sitemap_changefreq'] ?? 'weekly'),
                (string) ($settings['sitemap_priority_topics'] ?? '0.6'),
                $images,
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
        if (preg_match('/image-sitemap-(\d+)\.xml/', $path, $matches) !== 1) {
            $uri = (string) $request->serverParam('REQUEST_URI', '');
            preg_match('/image-sitemap-(\d+)\.xml/', $uri, $matches);
        }

        return isset($matches[1]) ? max(1, (int) $matches[1]) : 1;
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
                'SELECT t.id, t.slug, t.title, pm.path AS primary_media_path, t.primary_media_file_id, t.updated_at, t.published_at
                 FROM topics t
                 LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                 WHERE t.status IN (' . $statusPlaceholders . ') AND t.deleted_at IS NULL AND t.slug IS NOT NULL
                 ORDER BY t.published_at DESC, t.id DESC
                 LIMIT ? OFFSET ?',
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
                appLogException($exception, ['source' => self::class, 'scope' => 'topics']);
            } else {
                error_log($exception->getMessage());
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $topic
     * @param array<string,mixed> $settings
     * @return list<array{url:string,caption:string}>
     */
    private function collectImages(array $topic, array $settings, string $canonicalBase, int $maxImages): array
    {
        $images = [];
        $seen = [];
        $title = (string) ($topic['title'] ?? '');
        $appendImage = static function (string $url, string $caption) use (&$images, &$seen, $maxImages): void {
            $url = trim($url);
            if ($url === '' || isset($seen[$url]) || count($images) >= $maxImages) {
                return;
            }

            $seen[$url] = true;
            $images[] = [
                'url' => $url,
                'caption' => $caption,
            ];
        };

        if ((string) ($settings['image_sitemap_hero'] ?? '1') === '1') {
            $hero = $this->primaryMediaPath($topic);
            if ($hero !== '') {
                $appendImage(
                    filter_var($hero, FILTER_VALIDATE_URL) ? $hero : $this->canonicalUrl($hero, $settings, $canonicalBase),
                    $title . ' - kapak görseli',
                );
            }
        }

        if ((string) ($settings['image_sitemap_inline'] ?? '1') === '1') {
            foreach ($this->mediaGallery((int) ($topic['id'] ?? 0)) as $line) {
                if ($line !== '' && preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $line) === 1) {
                    $appendImage(
                        filter_var($line, FILTER_VALIDATE_URL) ? $line : $this->canonicalUrl($line, $settings, $canonicalBase),
                        $title . ' - ' . basename($line),
                    );
                }
            }
        }

        if ((string) ($settings['image_sitemap_media'] ?? '1') === '1') {
            foreach ($this->mediaFiles((int) ($topic['id'] ?? 0), max(1, $maxImages - count($images))) as $mediaFile) {
                $filePath = trim((string) ($mediaFile['path'] ?? ''));
                if ($filePath === '') {
                    continue;
                }

                $appendImage(
                    $this->canonicalUrl($filePath, $settings, $canonicalBase),
                    $title . ' - ' . (string) ($mediaFile['original_name'] ?? basename($filePath)),
                );
            }
        }

        return $images;
    }

    /**
     * @param array<string,mixed> $topic
     */
    private function primaryMediaPath(array $topic): string
    {
        if ($this->primaryMediaResolver instanceof Closure) {
            return trim((string) ($this->primaryMediaResolver)($topic));
        }

        if (function_exists('getTopicPrimaryMediaPath')) {
            return trim((string) (getTopicPrimaryMediaPath($topic) ?? ''));
        }

        return trim((string) ($topic['primary_media_path'] ?? ''));
    }

    /**
     * @return list<string>
     */
    private function mediaGallery(int $topicId): array
    {
        if ($this->galleryResolver instanceof Closure) {
            $gallery = ($this->galleryResolver)($this->resolvePdo(), $topicId);

            return is_array($gallery) ? array_values(array_map('strval', $gallery)) : [];
        }

        $pdo = $this->resolvePdo();
        if (function_exists('getTopicMediaGallery') && $pdo instanceof PDO) {
            $gallery = getTopicMediaGallery($pdo, $topicId);

            return is_array($gallery) ? array_values(array_map('strval', $gallery)) : [];
        }

        return [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function mediaFiles(int $topicId, int $limit): array
    {
        if ($this->mediaFilesResolver instanceof Closure) {
            $mediaFiles = ($this->mediaFilesResolver)($this->resolvePdo(), $topicId, $limit);

            return is_array($mediaFiles) ? array_values(array_filter($mediaFiles, 'is_array')) : [];
        }

        return $this->loadMediaFiles($topicId, $limit);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadMediaFiles(int $topicId, int $limit): array
    {
        $pdo = $this->resolvePdo();
        if (!$pdo instanceof PDO) {
            return [];
        }

        try {
            $statement = $pdo->prepare(
                "SELECT path, original_name
                 FROM media_files
                 WHERE topic_id = :topic_id AND (type = 'image' OR mime_type LIKE 'image/%')
                 ORDER BY is_primary DESC, display_order ASC, id ASC
                 LIMIT :limit",
            );
            $statement->bindValue(':topic_id', $topicId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $statement->execute();
            $mediaFiles = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($mediaFiles) ? array_values(array_filter($mediaFiles, 'is_array')) : [];
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => self::class, 'scope' => 'media_files', 'topic_id' => $topicId]);
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

    /**
     * @param list<array{url:string,caption:string}> $images
     */
    private function renderUrlEntry(string $loc, string $lastmod, string $changefreq, string $priority, array $images): string
    {
        $body = '    <url>' . "\n";
        $body .= '        <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
        $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
        $body .= '        <changefreq>' . htmlspecialchars($changefreq, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>' . "\n";
        $body .= '        <priority>' . htmlspecialchars($priority, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>' . "\n";
        foreach ($images as $image) {
            $body .= '        <image:image>' . "\n";
            $body .= '            <image:loc>' . htmlspecialchars($image['url'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</image:loc>' . "\n";
            $body .= '            <image:caption>' . htmlspecialchars($image['caption'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</image:caption>' . "\n";
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

    private function xmlResponse(string $body, ?int $lastModifiedTimestamp): Response
    {
        if (!str_contains($body, 'xml-stylesheet')) {
            $declaration = '<?xml version="1.0" encoding="UTF-8"?>';
            $stylesheet = '<?xml-stylesheet type="text/css" href="sitemap.css"?>';
            if (str_starts_with($body, $declaration)) {
                $body = $declaration . "\n" . $stylesheet . "\n" . substr($body, strlen($declaration) + 1);
            } else {
                $body = $stylesheet . "\n" . $body;
            }
        }
        $body = function_exists('formatSitemapXml') ? formatSitemapXml($body) : $body;
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
