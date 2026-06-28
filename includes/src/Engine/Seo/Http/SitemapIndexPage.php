<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use Throwable;

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
        private ?Closure $canonicalBaseResolver = null,
        private ?Closure $statisticsResolver = null,
        private ?Closure $nowResolver = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $canonicalBase = rtrim($this->canonicalBase ?? $this->resolveCanonicalBase($settings), '/');
        $now = $this->now();

        if ((string) ($settings['sitemap_enabled'] ?? '1') !== '1') {
            return $this->xmlResponse(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>',
                $now,
            );
        }

        $statistics = $this->resolveStatistics($settings, $now);

        return $this->xmlResponse(
            $this->renderSitemapIndex($settings, $canonicalBase, $statistics),
            $this->latestModifiedAt($statistics, $now),
        );
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
        if ($this->canonicalBaseResolver instanceof Closure) {
            return (string) ($this->canonicalBaseResolver)($settings);
        }

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

    /**
     * @param array<string,mixed> $settings
     * @return array{topic_lastmod:string,profile_lastmod:string,image_lastmod:string,total_topics:int,total_public_profiles:int,total_topics_with_images:int}
     */
    private function resolveStatistics(array $settings, string $now): array
    {
        if ($this->statisticsResolver instanceof Closure) {
            $statistics = ($this->statisticsResolver)($settings, $this->resolvePdo(), $now);

            return $this->normalizeStatistics(is_array($statistics) ? $statistics : [], $now);
        }

        return $this->loadStatistics($settings, $now);
    }

    /**
     * @param array<string,mixed> $settings
     * @return array{topic_lastmod:string,profile_lastmod:string,image_lastmod:string,total_topics:int,total_public_profiles:int,total_topics_with_images:int}
     */
    private function loadStatistics(array $settings, string $now): array
    {
        $statistics = $this->normalizeStatistics([], $now);
        $pdo = $this->resolvePdo();
        if (!$pdo instanceof PDO) {
            return $statistics;
        }

        $statuses = (string) ($settings['sitemap_exclude_drafts'] ?? '1') === '1'
            ? ['published']
            : ['published', 'draft'];
        $statusPlaceholders = implode(', ', array_fill(0, count($statuses), '?'));
        $imageEnabled = (string) ($settings['image_sitemap_enabled'] ?? '1') === '1';

        try {
            $topicLastmod = $this->fetchOne(
                $pdo,
                'SELECT MAX(COALESCE(updated_at, published_at, created_at)) AS last_mod FROM topics WHERE status IN ('
                    . $statusPlaceholders
                    . ') AND deleted_at IS NULL',
                $statuses,
            );
            if ($topicLastmod !== null && $topicLastmod !== '') {
                $statistics['topic_lastmod'] = date('Y-m-d\TH:i:sP', strtotime($topicLastmod));
                $statistics['image_lastmod'] = $statistics['topic_lastmod'];
            }

            $statistics['total_topics'] = (int) $this->fetchColumn(
                $pdo,
                'SELECT COUNT(*) FROM topics WHERE status IN ('
                    . $statusPlaceholders
                    . ') AND deleted_at IS NULL AND slug IS NOT NULL',
                $statuses,
            );

            if ($imageEnabled) {
                $statistics['total_topics_with_images'] = (int) $this->fetchColumn(
                    $pdo,
                    'SELECT COUNT(*) FROM topics WHERE status IN ('
                        . $statusPlaceholders
                        . ") AND deleted_at IS NULL AND slug IS NOT NULL AND (primary_media_file_id IS NOT NULL OR id IN (SELECT DISTINCT topic_id FROM media_files WHERE type = 'image' OR mime_type LIKE 'image/%'))",
                    $statuses,
                );
            }

            $profileStatement = $pdo->prepare("SELECT MAX(COALESCE(updated_at, created_at)) AS last_mod, COUNT(*) AS total_profiles FROM users WHERE status = 'active' AND public_profile = 1 AND deleted_at IS NULL AND (is_banned = 0 OR is_banned IS NULL) AND name IS NOT NULL AND TRIM(name) <> ''");
            $profileStatement->execute();
            $profileRow = $profileStatement->fetch(PDO::FETCH_ASSOC);
            if (is_array($profileRow)) {
                if (!empty($profileRow['last_mod'])) {
                    $statistics['profile_lastmod'] = date('Y-m-d\TH:i:sP', strtotime((string) $profileRow['last_mod']));
                }
                $statistics['total_public_profiles'] = (int) ($profileRow['total_profiles'] ?? 0);
            }
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => self::class]);
            } else {
                error_log($exception->getMessage());
            }
        }

        return $statistics;
    }

    /**
     * @param list<string> $params
     */
    private function fetchOne(PDO $pdo, string $sql, array $params): ?string
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !array_key_exists('last_mod', $row)) {
            return null;
        }

        return $row['last_mod'] !== null ? (string) $row['last_mod'] : null;
    }

    /**
     * @param list<string> $params
     */
    private function fetchColumn(PDO $pdo, string $sql, array $params): int
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string,mixed> $statistics
     * @return array{topic_lastmod:string,profile_lastmod:string,image_lastmod:string,total_topics:int,total_public_profiles:int,total_topics_with_images:int}
     */
    private function normalizeStatistics(array $statistics, string $now): array
    {
        return [
            'topic_lastmod' => (string) ($statistics['topic_lastmod'] ?? $now),
            'profile_lastmod' => (string) ($statistics['profile_lastmod'] ?? $now),
            'image_lastmod' => (string) ($statistics['image_lastmod'] ?? ($statistics['topic_lastmod'] ?? $now)),
            'total_topics' => max(0, (int) ($statistics['total_topics'] ?? 0)),
            'total_public_profiles' => max(0, (int) ($statistics['total_public_profiles'] ?? 0)),
            'total_topics_with_images' => max(0, (int) ($statistics['total_topics_with_images'] ?? 0)),
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array{topic_lastmod:string,profile_lastmod:string,image_lastmod:string,total_topics:int,total_public_profiles:int,total_topics_with_images:int} $statistics
     */
    private function renderSitemapIndex(array $settings, string $canonicalBase, array $statistics): string
    {
        $maxUrlsPerSitemap = max(1, min(50000, (int) ($settings['sitemap_max_urls'] ?? 1000)));
        $imageEnabled = (string) ($settings['image_sitemap_enabled'] ?? '1') === '1';

        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $body .= $this->renderSitemapEntries($canonicalBase, 'topic-sitemap', $statistics['topic_lastmod'], $statistics['total_topics'], $maxUrlsPerSitemap);
        $body .= $this->renderSitemapEntries($canonicalBase, 'profile-sitemap', $statistics['profile_lastmod'], $statistics['total_public_profiles'], $maxUrlsPerSitemap);
        if ($imageEnabled) {
            $body .= $this->renderSitemapEntries($canonicalBase, 'image-sitemap', $statistics['image_lastmod'], $statistics['total_topics_with_images'], $maxUrlsPerSitemap);
        }
        $body .= '</sitemapindex>' . "\n";

        return $body;
    }

    private function renderSitemapEntries(
        string $canonicalBase,
        string $name,
        string $lastmod,
        int $total,
        int $maxUrlsPerSitemap,
    ): string {
        $pages = max(1, (int) ceil($total / $maxUrlsPerSitemap));
        $body = '';

        for ($page = 1; $page <= $pages; $page++) {
            $url = $page === 1
                ? $canonicalBase . '/' . $name . '.xml'
                : $canonicalBase . '/' . $name . '-' . $page . '.xml';
            $body .= '    <sitemap>' . "\n";
            $body .= '        <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
            $body .= '        <lastmod>' . htmlspecialchars($lastmod, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>' . "\n";
            $body .= '    </sitemap>' . "\n";
        }

        return $body;
    }

    private function now(): string
    {
        if ($this->nowResolver instanceof Closure) {
            return (string) ($this->nowResolver)();
        }

        return date('Y-m-d\TH:i:sP');
    }

    /**
     * @param array{topic_lastmod:string,profile_lastmod:string,image_lastmod:string,total_topics:int,total_public_profiles:int,total_topics_with_images:int} $statistics
     */
    private function latestModifiedAt(array $statistics, string $fallback): string
    {
        $latest = null;
        foreach ([$statistics['topic_lastmod'], $statistics['profile_lastmod'], $statistics['image_lastmod']] as $lastmod) {
            $timestamp = strtotime($lastmod);
            if ($timestamp !== false && ($latest === null || $timestamp > $latest)) {
                $latest = $timestamp;
            }
        }

        return date('Y-m-d\TH:i:sP', $latest ?? (strtotime($fallback) ?: time()));
    }

    private function xmlResponse(string $body, string $lastModified): Response
    {
        $expiresAt = max(time(), strtotime($lastModified) ?: time()) + 600;

        return new Response($body, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=86400',
            'Expires' => gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'ETag' => '"' . hash('sha256', $body) . '"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', strtotime($lastModified) ?: time()) . ' GMT',
        ]);
    }
}
