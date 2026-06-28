<?php

declare(strict_types=1);

/**
 * Rate-limit module compatibility loader.
 *
 * The canonical procedural helpers live in includes/RateLimitHelpers.php and
 * delegate to App\Core\Security\RateLimiter. This file stays as the historical
 * module include path while callers are migrated.
 */

$rateLimitHelpers = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'RateLimitHelpers.php';
if (is_file($rateLimitHelpers)) {
    require_once $rateLimitHelpers;
}

