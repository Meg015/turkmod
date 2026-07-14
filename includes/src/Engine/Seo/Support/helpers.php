<?php

declare(strict_types=1);

/**
 * SEO Module Loader
 *
 * Loads the canonical SEO helper modules.
 */

// Load all SEO modules
require_once dirname(__DIR__, 4) . '/SeoPublicPages.php';
require_once __DIR__ . '/meta-tags.php';
require_once __DIR__ . '/structured-data.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/image-optimization.php';
if (is_file(__DIR__ . '/sitemap-routing.php')) {
    require_once __DIR__ . '/sitemap-routing.php';
}
