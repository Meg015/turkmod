<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Routing\AssetRouteAdapter;
use App\Modules\Events\Api\EventsApiHandler;

// Static file passthrough for PHP built-in dev server.
// Without this, the router sets Content-type: text/html for all requests,
// which breaks CSS, JS, and font delivery.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($uri !== null && $uri !== false && $uri !== '/') {
    $decoded = rawurldecode($uri);
    // Prevent path traversal: reject any path containing '..'
    if (str_contains($decoded, '..') || str_contains($decoded, '\\') || preg_match('/[\x00-\x1f\x7f]/', $decoded)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Geçersiz istek yolu.');
    }
    $filePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $decoded);
    $realPath = realpath($filePath);
    if (PHP_SAPI === 'cli-server' && preg_match('~(?:^|/)\.|^/(?:vendor|node_modules|docs|tests|scripts|deployment|database|includes|storage)(?:/|$)~i', $decoded)) {
        http_response_code(403);
        exit;
    }
    if (PHP_SAPI === 'cli-server' && $realPath !== false && str_starts_with($realPath, __DIR__ . DIRECTORY_SEPARATOR) && is_file($realPath)) {
        return false;
    }
}

$statelessSeoRoutePath = strtolower(rawurldecode((string) ($uri ?? '')));
if (
    $statelessSeoRoutePath !== ''
    && preg_match('~(?:^|/)(?:robots\.txt|sitemap\.xml|(?:page|category|topic|profile|image)-sitemap(?:-\d+)?\.xml|favicon\.ico|xmlrpc\.php)$~i', $statelessSeoRoutePath) === 1
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
        'page-sitemap.xml' => \App\Engine\Seo\Http\PageSitemapPage::class,
        'category-sitemap.xml' => \App\Engine\Seo\Http\CategorySitemapPage::class,
        'topic-sitemap.xml' => \App\Engine\Seo\Http\TopicSitemapPage::class,
        'profile-sitemap.xml' => \App\Engine\Seo\Http\ProfileSitemapPage::class,
        'image-sitemap.xml' => \App\Engine\Seo\Http\ImageSitemapPage::class,
    ];

    if (isset($sitemapRoutes[$cleanRoute])) {
        routerDispatchTarget($sitemapRoutes[$cleanRoute], $cleanRoute);
        exit;
    }

    $patternRoutes = [
        '/^page-sitemap-(\d+)\.xml$/' => \App\Engine\Seo\Http\PageSitemapPage::class,
        '/^category-sitemap-(\d+)\.xml$/' => \App\Engine\Seo\Http\CategorySitemapPage::class,
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

    $routesFile = __DIR__ . '/includes/src/Modules/Events/routes.php';
    $candidate = require $routesFile;
    if (!is_array($candidate)) {
        throw new RuntimeException('Events route configuration must be an array.');
    }

    $pageMap = $candidate['page_map'] ?? [];
    if (!is_array($pageMap)) {
        throw new RuntimeException('Events page map must be an array.');
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

    $bootstrap = trim((string) ($candidate['bootstrap'] ?? ''), "/\\ \t\n\r\0\x0B");
    $assetsBase = trim((string) ($candidate['assets_base'] ?? ''), "/\\ \t\n\r\0\x0B");
    $apiTemplate = trim((string) ($candidate['api_template'] ?? ''));
    if ($bootstrap === '' || $assetsBase === '' || !str_contains($apiTemplate, '%s') || $normalizedPageMap === []) {
        throw new RuntimeException('Events route configuration is incomplete.');
    }

    $config = [
        'bootstrap' => $bootstrap,
        'assets_base' => $assetsBase,
        'api_template' => $apiTemplate,
        'page_map' => $normalizedPageMap,
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

    if (isset($normalizedSegments[1]) && $normalizedSegments[1] === 'api' && count($normalizedSegments) === 3) {
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
    if (count($normalizedSegments) > 2) {
        routerNotFound();
    }
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

function routerHandleAdminApiModuleRoute(string $cleanRoute): void
{
    $groups = routeGroupedRouteCatalog();
    foreach (['admin', 'api'] as $groupName) {
        $routes = is_array($groups[$groupName] ?? null) ? $groups[$groupName] : [];
        if (!isset($routes[$cleanRoute]) || !is_array($routes[$cleanRoute])) {
            continue;
        }

        $route = $routes[$cleanRoute];
        $target = (string) ($route['target'] ?? '');
        if ($target === '') {
            continue;
        }

        $middleware = is_array($route['group_middleware'] ?? null) ? $route['group_middleware'] : [];
        routerDispatchTarget($target, $cleanRoute, $middleware);
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

function routerHandleDynamicContentRoute(array $segments, ?PDO $pdo): void
{
    if (count($segments) > 3) {
        return;
    }
    $hasNestedSlug = isset($segments[2]) && (string) $segments[2] !== '';
    $prefix = routePrefixSanitize((string) ($segments[0] ?? ''));
    $parent = trim((string) ($hasNestedSlug ? ($segments[1] ?? '') : ''));
    $slug = trim((string) ($hasNestedSlug ? ($segments[2] ?? '') : ($segments[1] ?? '')));
    $routes = routePrefixSettings($pdo);

    if (routePrefixMatches('topic', $prefix, $routes) && count($segments) === 2 && $slug !== '') {
        unset($_GET['id']);
        $_GET['slug'] = $slug;
        routerDispatchTarget(\App\Engine\Topics\Http\TopicPage::class, implode('/', $segments));
    }

    if (routePrefixMatches('category_list', $prefix, $routes) && $parent === '' && $slug === '') {
        unset($_GET['parent'], $_GET['slug']);
        routerDispatchTarget(\App\Engine\Categories\Http\CategoryPage::class, implode('/', $segments));
    }

    if (routePrefixMatches('category', $prefix, $routes)) {
        unset($_GET['name']);
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

    if (routePrefixMatches('profile', $prefix, $routes) && count($segments) <= 2) {
        if ($slug !== '') {
            $_GET['profile'] = $slug;
            routerDispatchTarget(\App\Engine\Users\Http\PublicProfilePage::class, implode('/', $segments));
        }

        routerDispatchTarget(\App\Engine\Users\Http\ProfilePage::class, implode('/', $segments));
    }
}

function routerHandleManagedStaticPageRoute(array $segments, ?PDO $pdo): void
{
    if (count($segments) !== 1 || !$pdo instanceof PDO) {
        return;
    }

    try {
        $service = new \App\Modules\StaticPages\Services\StaticPageService($pdo);
        $route = $service->resolveRoute((string) $segments[0]);
        if (!is_array($route)) {
            return;
        }

        $GLOBALS['_static_page_route'] = $route;
        routerDispatchTarget(\App\Modules\StaticPages\Http\StaticPagePage::class, (string) $segments[0]);
    } catch (Throwable $exception) {
        if (function_exists('appLogException')) {
            appLogException($exception, ['source' => 'routerHandleManagedStaticPageRoute']);
        }
    }
}

$segments = routeSegmentsFromRequest((string) ($baseUri ?? ''));
$cleanRoute = implode('/', $segments);
$settings = getAdminSettings($pdo);

routerHandleSitemapRoute($cleanRoute, $settings);
routerHandleEventsRoute($segments);
routerHandleAdminApiModuleRoute($cleanRoute);
routerHandleStaticRoute($cleanRoute);
routerHandleGotoPostRoute($segments);
routerHandleDynamicContentRoute($segments, $pdo);
routerHandleManagedStaticPageRoute($segments, $pdo);

routerNotFound();
