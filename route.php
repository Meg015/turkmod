<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Routing\AssetRouteAdapter;
use App\Modules\Events\Api\EventsApiHandler;

// Static file passthrough for PHP built-in dev server.
// Without this, the router sets Content-type: text/html for all requests,
// which breaks CSS, JS, and font delivery.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($uri !== null && $uri !== '/') {
    $decoded = rawurldecode($uri);
    // Prevent path traversal: reject any path containing '..'
    if (str_contains($decoded, '..')) {
        routerNotFound();
    }
    $filePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $decoded);
    $realPath = realpath($filePath);
    if ($realPath !== false && str_starts_with($realPath, __DIR__ . DIRECTORY_SEPARATOR) && is_file($realPath)) {
        return false;
    }
}

$statelessSeoRoutePath = strtolower(rawurldecode((string) ($uri ?? '')));
if (
    $statelessSeoRoutePath !== ''
    && preg_match('~(?:^|/)(?:robots\.txt|sitemap\.xml|(?:topic|profile|image)-sitemap(?:-\d+)?\.xml|favicon\.ico|xmlrpc\.php)$~i', $statelessSeoRoutePath) === 1
) {
    $GLOBALS['_skip_session_bootstrap'] = true;
}

require_once __DIR__ . '/includes/init.php';

function routerNotFound(): void
{
    http_response_code(404);
    require __DIR__ . '/includes/public-404.php';
    exit;
}

function routerApplyScriptContext(string $relativePath): void
{
    $normalized = str_replace('\\', '/', ltrim($relativePath, '/\\'));
    if (str_contains($normalized, '/')) {
        return;
    }

    $base = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $scriptName = ($base !== '' ? $base : '') . '/' . $normalized;
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['PHP_SELF'] = $scriptName;
}

function routerRequireFile(string $relativePath): void
{
    $root = realpath(__DIR__);
    $target = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));

    if (
        $root === false ||
        $target === false ||
        !str_starts_with($target, $root . DIRECTORY_SEPARATOR) ||
        !is_file($target)
    ) {
        routerNotFound();
    }

    routerApplyScriptContext($relativePath);
    $pdo = $GLOBALS['pdo'] ?? null;
    $baseUri = $GLOBALS['baseUri'] ?? '';
    $envConfig = $GLOBALS['envConfig'] ?? [];
    $appDebug = $GLOBALS['appDebug'] ?? false;
    require $target;
    exit;
}

function routerDispatchTarget(string $target, string $scriptContext = '', array $middleware = []): void
{
    if ($scriptContext !== '') {
        routerApplyScriptContext($scriptContext);
    }

    $dispatcher = routeCompatibilityDispatcher();
    $request = Request::fromGlobals();
    $dispatcher->emit($dispatcher->dispatch($request, $target, $middleware));
    exit;
}

function routeSegmentsFromRequest(string $baseUri): array
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = trim(rawurldecode($path), '/');
    $base = trim($baseUri, '/');

    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = ltrim(substr($path, strlen($base)), '/');
    }

    if ($path === '') {
        return [];
    }

    return array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
}

function routerStaticRoutes(): array
{
    return routePublicStaticFileRoutes();
}

function routerHandleSitemapRoute(string $cleanRoute, array $settings): void
{
    if (($settings['sitemap_route_enabled'] ?? '1') !== '1') {
        return;
    }

    $sitemapRoutes = [
        'sitemap.xml' => \App\Engine\Seo\Http\SitemapIndexPage::class,
        'topic-sitemap.xml' => \App\Engine\Seo\Http\TopicSitemapPage::class,
        'profile-sitemap.xml' => \App\Engine\Seo\Http\ProfileSitemapPage::class,
        'image-sitemap.xml' => \App\Engine\Seo\Http\ImageSitemapPage::class,
    ];

    if (isset($sitemapRoutes[$cleanRoute])) {
        routerDispatchTarget($sitemapRoutes[$cleanRoute], $cleanRoute);
        exit;
    }

    $patternRoutes = [
        '/^topic-sitemap-(\d+)\.xml$/' => \App\Engine\Seo\Http\TopicSitemapPage::class,
        '/^profile-sitemap-(\d+)\.xml$/' => \App\Engine\Seo\Http\ProfileSitemapPage::class,
        '/^image-sitemap-(\d+)\.xml$/' => \App\Engine\Seo\Http\ImageSitemapPage::class,
    ];

    foreach ($patternRoutes as $pattern => $target) {
        if (preg_match($pattern, $cleanRoute) === 1) {
            routerDispatchTarget($target, $cleanRoute);
            exit;
        }
    }
}

/**
 * @return array{bootstrap:string,assets_base:string,api_template:string,page_map:array<string,string>}
 */
function routerEventsRouteConfig(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $defaults = [
        'bootstrap' => 'includes/src/Modules/Events/init.php',
        'assets_base' => 'includes/src/Modules/Events/assets',
        'api_template' => 'includes/src/Modules/Events/Api/Legacy/%s.php',
        'page_map' => [
            'events' => 'includes/src/Modules/Events/Pages/index.php',
            'events/wheel' => 'includes/src/Modules/Events/Pages/wheel.php',
            'events/raffle' => 'includes/src/Modules/Events/Pages/raffle.php',
            'events/rewards' => 'includes/src/Modules/Events/Pages/rewards.php',
            'events/tasks' => 'includes/src/Modules/Events/Pages/tasks.php',
        ],
    ];

    $routesFile = __DIR__ . '/includes/src/Modules/Events/routes.php';
    if (!is_file($routesFile)) {
        $config = $defaults;

        return $config;
    }

    $candidate = require $routesFile;
    if (!is_array($candidate)) {
        $config = $defaults;

        return $config;
    }

    $pageMap = $candidate['page_map'] ?? [];
    if (!is_array($pageMap)) {
        $pageMap = [];
    }

    $normalizedPageMap = [];
    foreach ($pageMap as $path => $target) {
        if (!is_string($path) || !is_string($target)) {
            continue;
        }

        $path = trim($path, "/\\ \t\n\r\0\x0B");
        $target = trim($target, "/\\ \t\n\r\0\x0B");
        if ($path !== '' && $target !== '') {
            $normalizedPageMap[$path] = $target;
        }
    }

    $bootstrap = trim((string) ($candidate['bootstrap'] ?? $defaults['bootstrap']), "/\\ \t\n\r\0\x0B");
    $assetsBase = trim((string) ($candidate['assets_base'] ?? $defaults['assets_base']), "/\\ \t\n\r\0\x0B");
    $apiTemplate = trim((string) ($candidate['api_template'] ?? $defaults['api_template']));

    $config = [
        'bootstrap' => $bootstrap !== '' ? $bootstrap : $defaults['bootstrap'],
        'assets_base' => $assetsBase !== '' ? $assetsBase : $defaults['assets_base'],
        'api_template' => str_contains($apiTemplate, '%s') ? $apiTemplate : $defaults['api_template'],
        'page_map' => $normalizedPageMap !== [] ? $normalizedPageMap : $defaults['page_map'],
    ];

    return $config;
}

function routerServeEventsAsset(string $assetsBase, array $assetSegments): void
{
    (new AssetRouteAdapter())->sendFromSegments($assetsBase, $assetSegments);
    exit;
}

function routerHandleEventsRoute(array $segments): void
{
    $requestPrefix = trim((string) ($segments[0] ?? ''), '/');
    if ($requestPrefix === '') {
        return;
    }

    $eventsAliases = function_exists('routePublicStaticPathAliases')
        ? routePublicStaticPathAliases('events')
        : ['events'];
    if (!in_array($requestPrefix, $eventsAliases, true)) {
        return;
    }

    $paths = function_exists('routePublicStaticPathSettings')
        ? routePublicStaticPathSettings($GLOBALS['pdo'] ?? null)
        : ['events' => 'events'];
    $canonicalPrefix = trim((string) ($paths['events'] ?? 'events'), '/');
    if ($canonicalPrefix === '') {
        $canonicalPrefix = 'events';
    }

    if ($requestPrefix !== $canonicalPrefix) {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $routeSettings = function_exists('routeFriendlySettings')
            ? routeFriendlySettings()
            : [];
        $shouldRedirect = in_array($method, ['GET', 'HEAD'], true)
            && function_exists('routeAliasRedirectsEnabled')
            && routeAliasRedirectsEnabled($routeSettings);

        if ($shouldRedirect) {
            $redirectSegments = $segments;
            $redirectSegments[0] = $canonicalPrefix;
            $redirectPath = implode('/', array_values(array_filter($redirectSegments, static fn ($segment): bool => $segment !== '')));
            $redirect = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
            $redirect .= '/' . ltrim($redirectPath, '/');

            $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            if ($query !== '') {
                $redirect .= '?' . $query;
            }

            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }

    $normalizedSegments = $segments;
    $normalizedSegments[0] = 'events';

    $config = routerEventsRouteConfig();
    $bootstrapFile = __DIR__ . '/' . ltrim($config['bootstrap'], '/\\');
    if (!is_file($bootstrapFile)) {
        routerNotFound();
    }
    require_once $bootstrapFile;

    if (isset($normalizedSegments[1]) && $normalizedSegments[1] === 'assets') {
        $assetsBase = __DIR__ . '/' . ltrim($config['assets_base'], '/\\');
        routerServeEventsAsset($assetsBase, array_slice($normalizedSegments, 2));
    }

    if (isset($normalizedSegments[1]) && $normalizedSegments[1] === 'api' && isset($normalizedSegments[2])) {
        $handler = EventsApiHandler::fromTemplate(__DIR__, $config['api_template'], (string) $normalizedSegments[2]);
        if ($handler->exists()) {
            $dispatcher = routeCompatibilityDispatcher();
            $request = Request::fromGlobals()->withAttribute('events_api_name', $handler->getApiName());
            $dispatcher->emit($dispatcher->dispatch($request, $handler));
            exit;
        }

        routerNotFound();
    }

    $pageMap = $config['page_map'];
    $pageKey = count($normalizedSegments) === 1 ? 'events' : implode('/', array_slice($normalizedSegments, 0, 2));

    if (isset($pageMap[$pageKey])) {
        routerRequireFile($pageMap[$pageKey]);
    }

    routerNotFound();
}

function routerHandleStaticRoute(string $cleanRoute): void
{
    $staticRoutes = routePublicGroupedCatalog();
    if (!isset($staticRoutes[$cleanRoute])) {
        return;
    }

    $route = $staticRoutes[$cleanRoute];
    $target = (string) ($route['target'] ?? '');
    if ($target === '') {
        return;
    }

    if (!empty($route['is_alias'])) {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $routeSettings = function_exists('routeFriendlySettings')
            ? routeFriendlySettings()
            : [];
        $shouldRedirect = in_array($method, ['GET', 'HEAD'], true)
            && function_exists('routeAliasRedirectsEnabled')
            && routeAliasRedirectsEnabled($routeSettings);

        $canonicalPath = trim((string) ($route['canonical_path'] ?? ''), '/');
        if ($shouldRedirect && $canonicalPath !== '' && $canonicalPath !== trim($cleanRoute, '/')) {
            $redirect = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
            $redirect .= '/' . ltrim($canonicalPath, '/');

            $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            if ($query !== '') {
                $redirect .= '?' . $query;
            }

            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }

    $middleware = is_array($route['group_middleware'] ?? null) ? $route['group_middleware'] : [];
    routerDispatchTarget($target, $cleanRoute === '' ? 'index.php' : $cleanRoute, $middleware);
}

function routerHandleLegacyPhpRoute(string $cleanRoute): void
{
    if (!str_ends_with(strtolower($cleanRoute), '.php') || !function_exists('routeLegacyRedirectTarget')) {
        return;
    }

    $normalizedLegacyPath = strtolower(trim($cleanRoute, '/'));
    if ($normalizedLegacyPath === 'public-profile.php') {
        $profileSlug = trim((string) ($_GET['profile'] ?? $_GET['slug'] ?? ''));
        if ($profileSlug !== '') {
            $redirect = routeCanonicalPath('profile', $profileSlug);
            $remainingQuery = $_GET;
            unset($remainingQuery['profile'], $remainingQuery['slug']);
            if ($remainingQuery !== []) {
                $query = http_build_query($remainingQuery);
                if ($query !== '') {
                    $redirect .= '?' . $query;
                }
            }

            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }

    $target = routeLegacyRedirectTarget($cleanRoute);
    if ($target === null) {
        return;
    }

    $normalizedTarget = trim($target, '/');
    $currentPath = trim($cleanRoute, '/');
    if ($normalizedTarget === $currentPath) {
        return;
    }

    $base = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $redirect = $base . '/';
    if ($normalizedTarget !== '') {
        $redirect .= $normalizedTarget;
    }

    $queryString = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if ($queryString !== '') {
        $redirect .= '?' . $queryString;
    }

    header('Location: ' . $redirect, true, 301);
    exit;
}

function routerHandleLegacyRoute(array $segments): void
{
    if (count($segments) !== 2 || !preg_match('/^(.+)\.([0-9]+)$/', $segments[1], $legacyMatch)) {
        return;
    }

    $legacyType = match ($segments[0]) {
        'konu' => 'topic',
        'forums' => 'category',
        default => '',
    };

    if ($legacyType !== '') {
        $_GET['type'] = $legacyType;
        $_GET['legacy_slug'] = $legacyMatch[1];
        $_GET['legacy_id'] = $legacyMatch[2];
        routerDispatchTarget(\App\Engine\Seo\Http\LegacyRedirectPage::class, 'legacy-redirect.php');
    }
}

function routerHandleLegacyPageSuffixRoute(array $segments): void
{
    global $settings;

    if (
        count($segments) !== 3 ||
        !preg_match('/^(.+)\.([0-9]+)$/', $segments[1], $legacyMatch) ||
        !preg_match('/^page-([1-9][0-9]*)$/', $segments[2], $pageMatch)
    ) {
        return;
    }

    $legacyType = match ($segments[0]) {
        'konu' => 'topic',
        'forums' => 'category',
        default => '',
    };

    if ($legacyType === '') {
        return;
    }

    $pdo = $GLOBALS['pdo'] ?? null;
    $page = max(1, (int) $pageMatch[1]);
    $queryParams = [];
    parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY), $queryParams);

    if ($legacyType === 'topic') {
        $topic = $pdo instanceof PDO ? getTopic($pdo, (int) $legacyMatch[2]) : null;
        if (is_array($topic) && !empty($topic['slug'])) {
            $redirect = function_exists('topicUrlForRow')
                ? topicUrlForRow($topic)
                : topicUrl((string) $topic['slug'], (int) ($topic['id'] ?? 0));

            unset($queryParams['page']);
            if ($queryParams !== []) {
                $redirect .= (str_contains($redirect, '?') ? '&' : '?') . http_build_query($queryParams);
            }

            header('Location: ' . $redirect, true, 301);
            exit;
        }

        if (function_exists('legacyRedirectResolve')) {
            $seoRedirect = legacyRedirectResolve($pdo instanceof PDO ? $pdo : null, '/' . $segments[0] . '/' . $segments[1] . '/');
            if (!empty($seoRedirect['redirect']) && !empty($seoRedirect['target_url'])) {
                $redirect = (string) $seoRedirect['target_url'];

                unset($queryParams['page']);
                if ($queryParams !== []) {
                    $redirect .= (str_contains($redirect, '?') ? '&' : '?') . http_build_query($queryParams);
                }

                header('Location: ' . $redirect, true, 301);
                exit;
            }
        }

        return;
    }

    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT cat.slug, parent.slug AS parent_slug
             FROM categories cat
             LEFT JOIN categories parent ON parent.id = cat.parent_id
             WHERE cat.id = :id AND cat.deleted_at IS NULL
             LIMIT 1',
        );
        $stmt->execute(['id' => (int) $legacyMatch[2]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }

    if (is_array($row) && !empty($row['slug'])) {
        $redirect = categoryUrl((string) $row['slug'], (string) ($row['parent_slug'] ?? ''));
        $perPage = (int) (($settings['items_per_page'] ?? CATEGORY_TOPICS_PER_PAGE) ?: CATEGORY_TOPICS_PER_PAGE);
        $totalResult = function_exists('getTopicsByCategorySlug')
            ? getTopicsByCategorySlug($pdo, (string) $row['slug'], 1, $perPage)
            : ['total' => 0];
        $totalPages = max(1, (int) ceil((int) ($totalResult['total'] ?? 0) / max(1, $perPage)));
        $page = min($page, $totalPages);
        if ($page > 1) {
            $queryParams['page'] = $page;
        } else {
            unset($queryParams['page']);
        }

        if ($queryParams !== []) {
            $redirect .= (str_contains($redirect, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        header('Location: ' . $redirect, true, 301);
        exit;
    }

    if (function_exists('legacyRedirectResolve')) {
        $seoRedirect = legacyRedirectResolve($pdo, '/' . $segments[0] . '/' . $segments[1] . '/');
        if (!empty($seoRedirect['redirect']) && !empty($seoRedirect['target_url'])) {
            $redirect = (string) $seoRedirect['target_url'];
            if ($page > 1) {
                $queryParams['page'] = $page;
            } else {
                unset($queryParams['page']);
            }
            if ($queryParams !== []) {
                $redirect .= (str_contains($redirect, '?') ? '&' : '?') . http_build_query($queryParams);
            }

            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }
}

function routerHandleGotoPostRoute(array $segments): void
{
    if (count($segments) !== 2 || $segments[0] !== 'goto' || $segments[1] !== 'post') {
        return;
    }

    $commentId = (int) ($_GET['id'] ?? 0);
    if ($commentId <= 0) {
        return;
    }

    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT c.id AS comment_id, c.topic_id, t.slug
             FROM comments c
             INNER JOIN topics t ON t.id = c.topic_id
             WHERE c.id = :id
               AND c.deleted_at IS NULL
               AND c.status = "approved"
               AND t.deleted_at IS NULL
               AND t.status = "published"
             LIMIT 1',
        );
        $stmt->execute(['id' => $commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }

    if (!is_array($row) || empty($row['slug'])) {
        return;
    }

    $redirect = topicUrl((string) $row['slug'], (int) $row['topic_id']) . '#comment-' . (int) $row['comment_id'];
    header('Location: ' . $redirect, true, 301);
    exit;
}

function routerHandleLegacyStyleVariationRoute(array $segments): void
{
    if (count($segments) < 2) {
        return;
    }

    if (strtolower((string) $segments[0]) !== 'misc' || strtolower((string) $segments[1]) !== 'style-variation') {
        return;
    }

    $base = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $redirect = $base === '' ? '/' : ($base . '/');

    header('X-Robots-Tag: noindex, nofollow', true);
    header('Location: ' . $redirect, true, 301);
    exit;
}

function routerHandleDynamicContentRoute(array $segments, ?PDO $pdo): void
{
    $hasNestedSlug = isset($segments[2]) && (string) $segments[2] !== '';
    $prefix = routePrefixSanitize((string) ($_GET['prefix'] ?? ($segments[0] ?? '')));
    $parent = trim((string) ($_GET['parent'] ?? ($hasNestedSlug ? ($segments[1] ?? '') : '')));
    $slug = trim((string) ($_GET['slug'] ?? ($hasNestedSlug ? ($segments[2] ?? '') : ($segments[1] ?? ''))));
    $routes = routePrefixSettings($pdo);

    if (routePrefixMatches('topic', $prefix, $routes) && $slug !== '') {
        $_GET['slug'] = $slug;
        routerDispatchTarget(\App\Engine\Topics\Http\TopicPage::class, implode('/', $segments));
    }

    if (routePrefixMatches('category_list', $prefix, $routes) && $parent === '' && $slug === '') {
        unset($_GET['parent'], $_GET['slug']);
        routerDispatchTarget(\App\Engine\Categories\Http\CategoryPage::class, implode('/', $segments));
    }

    if (routePrefixMatches('category', $prefix, $routes)) {
        if ($parent !== '') {
            $_GET['parent'] = $parent;
        } else {
            unset($_GET['parent']);
        }

        if ($slug !== '') {
            $_GET['slug'] = $slug;
        } else {
            unset($_GET['slug']);
        }
        routerDispatchTarget(\App\Engine\Categories\Http\CategoryPage::class, implode('/', $segments));
    }

    if (routePrefixMatches('profile', $prefix, $routes)) {
        if ($slug !== '') {
            $_GET['profile'] = $slug;
            routerDispatchTarget(\App\Engine\Users\Http\PublicProfilePage::class, implode('/', $segments));
        }

        routerDispatchTarget(\App\Engine\Users\Http\ProfilePage::class, implode('/', $segments));
    }
}

$segments = routeSegmentsFromRequest((string) ($baseUri ?? ''));
$cleanRoute = implode('/', $segments);
$settings = getAdminSettings($pdo);

routerHandleSitemapRoute($cleanRoute, $settings);
routerHandleEventsRoute($segments);
routerHandleStaticRoute($cleanRoute);
routerHandleLegacyPhpRoute($cleanRoute);
routerHandleLegacyPageSuffixRoute($segments);
routerHandleGotoPostRoute($segments);
routerHandleLegacyStyleVariationRoute($segments);
routerHandleLegacyRoute($segments);
routerHandleDynamicContentRoute($segments, $pdo);

routerNotFound();
