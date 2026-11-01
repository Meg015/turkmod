<?php

declare(strict_types=1);

if (!function_exists('seoGetPaginationTags')) {
    function seoGetPaginationTags(int $currentPage, int $totalPages, string $baseUrl, array $settings): string
    {
        $strategy = $settings['pagination_strategy'] ?? 'full';
        $baseUrl = seoGetPaginationCanonical(1, seoCanonicalUrl($baseUrl, $settings));

        $tags = [];

        // Canonical URL
        $canonicalUrl = seoGetPaginationCanonical($currentPage, $baseUrl);
        $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '">';

        // Strategy-specific tags
        if ($strategy === 'full') {
            // Prev link
            if ($currentPage > 1) {
                $prevPage = $currentPage - 1;
                $prevUrl = $prevPage === 1
                    ? $baseUrl
                    : $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $prevPage;
                $tags[] = '<link rel="prev" href="' . htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') . '">';
            }

            // Next link
            if ($currentPage < $totalPages) {
                $nextPage = $currentPage + 1;
                $nextUrl = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $nextPage;
                $tags[] = '<link rel="next" href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        return implode("\n    ", $tags);
    }
}

if (!function_exists('seoGetPaginationCanonical')) {
    function seoGetPaginationCanonical(int $currentPage, string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        $query = [];
        parse_str(is_array($parts) ? (string) ($parts['query'] ?? '') : '', $query);
        unset($query['page']);
        $baseUrl = explode('?', explode('#', $baseUrl, 2)[0], 2)[0];
        if ($query !== []) {
            $baseUrl .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        if ($currentPage <= 1) {
            return $baseUrl;
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $currentPage;
    }
}

if (!function_exists('seoShouldIndexPage')) {
    function seoShouldIndexPage(int $currentPage, array $settings): bool
    {
        $strategy = $settings['pagination_strategy'] ?? 'full';
        $maxPagesIndex = (int) ($settings['pagination_max_pages_index'] ?? 50);

        if ($currentPage > $maxPagesIndex) {
            return false;
        }

        if ($strategy === 'noindex' && $currentPage > 1) {
            return false;
        }

        return true;
    }
}
