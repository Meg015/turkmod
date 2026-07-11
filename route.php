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
    $GLOBALS['_cache_control_set'] = true;
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

    $paths = function_exists('routePublicStaticPathSettings')
        ? routePublicStaticPathSettings($GLOBALS['pdo'] ?? null)
        : ['events' => 'events'];
    $canonicalPrefix = trim((string) ($paths['events'] ?? 'events'), '/');
    if ($canonicalPrefix === '') {
        $canonicalPrefix = 'events';
    }
    if ($requestPrefix !== $canonicalPrefix) {
        return;
    }

    $normalizedSegments = $segments;
    $normalizedSegments[0] = $canonicalPrefix;

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

    $middleware = is_array($route['group_middleware'] ?? null) ? $route['group_middleware'] : [];
    routerDispatchTarget($target, $cleanRoute === '' ? 'index.php' : $cleanRoute, $middleware);
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
routerHandleGotoPostRoute($segments);
routerHandleDynamicContentRoute($segments, $pdo);

routerNotFound();
