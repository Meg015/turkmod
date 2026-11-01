<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/SecurityHelpers.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Topics/Support/helpers.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Topics/Support/core_helpers.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Categories/Support/helpers.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Seo/Support/pagination.php';
require_once dirname(__DIR__) . '/includes/SeoPublicPages.php';
require_once dirname(__DIR__) . '/includes/SeoHelpers.php';
require_once dirname(__DIR__) . '/includes/TemplateRenderer.php';
require_once dirname(__DIR__) . '/includes/ThemeManager.php';

function auditAssert(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
    $GLOBALS['auditChecks']++;
}
function auditThrows(callable $action, string $label): void {
    try { $action(); } catch (Throwable) { $GLOBALS['auditChecks']++; return; }
    throw new RuntimeException($label);
}
function appLogException(Throwable $exception, array $context = []): void {}
function seoSettings(?array $settings = null): array { return $settings ?? []; }
function seoCanonicalBase(?array $settings = null): string { return 'https://turkmod.test'; }

// Load the actual standalone function without running application bootstrap.
$source = file_get_contents(dirname(__DIR__) . '/includes/init.php');
$start = strpos($source, 'function routeApplyTrailingSlash(');
$end = strpos($source, "\nfunction routeCanonicalPath(", $start);
eval(substr($source, $start, $end - $start));
$start = strpos($source, 'function seoCanonicalUrl(');
$end = strpos($source, "\nfunction seoRobotsMeta(", $start);
eval(substr($source, $start, $end - $start));
$start = strpos($source, 'function publicProfileDisplayName(');
$end = strpos($source, "\nfunction publicProfileUrl(", $start);
eval(substr($source, $start, $end - $start));
$baseUri = '/turkmod';
$GLOBALS['auditChecks'] = 0;

$_GET = ['page' => '7'];
auditAssert(seoCanonicalUrl('/turkmod/konu/ornek.1') === 'https://turkmod.test/konu/ornek.1', 'Unrelated request pagination leaked into explicit canonical');
auditAssert(seoCanonicalUrl('/turkmod/kategori/ets2?page=2') === 'https://turkmod.test/kategori/ets2?page=2', 'Explicit pagination was lost or duplicated');
auditAssert(seoCanonicalUrl('https://turkmod.test/kategori/ets2?page=3#fragment') === 'https://turkmod.test/kategori/ets2?page=3', 'Absolute canonical query/fragment handling failed');
auditAssert(seoCanonicalUrl('/turkmod/konu/ornek.1?utm_source=test&confirm=1') === 'https://turkmod.test/konu/ornek.1', 'Tracking/action query leaked into canonical');
auditAssert(seoCanonicalUrl('/turkmod/kategori/ets2?page=1') === 'https://turkmod.test/kategori/ets2', 'First page canonical has redundant page=1');
auditAssert(seoCanonicalUrl('/turkmod/kategori/ets2?page[]=2') === 'https://turkmod.test/kategori/ets2', 'Array pagination was accepted');
$_SERVER['REQUEST_URI'] = '/turkmod/?page=2&utm_source=test';
$_GET = ['page' => '2'];
auditAssert(seoCanonicalUrl() === 'https://turkmod.test/?page=2', 'Current-request pagination canonical is missing');
$tags = seoGetPaginationTags(2, 4, '/turkmod/kategori/ets2', []);
auditAssert(substr_count($tags, 'page=2') === 1 && !str_contains($tags, '?page=2&amp;page='), 'Pagination canonical is duplicated');
auditAssert(str_contains($tags, 'rel="prev" href="https://turkmod.test/kategori/ets2"'), 'Previous page does not point to first canonical');
auditAssert(seoCanonicalUrl('/turkmod/konu/example-1', ['route_trailing_slash' => 'add']) === 'https://turkmod.test/konu/example-1/', 'Canonical conflicts with slash-adding redirect');
auditAssert(seoCanonicalUrl('/turkmod/konu/example-1/', ['route_trailing_slash' => 'remove', 'canonical_trailing_slash' => '1']) === 'https://turkmod.test/konu/example-1', 'Canonical conflicts with slash-removing redirect');
auditAssert(routeApplyTrailingSlash('/turkmod/', ['route_trailing_slash' => 'remove']) === '/turkmod/', 'Subdirectory root redirects back and forth with Apache');
auditAssert(routeApplyTrailingSlash('/sitemap.xml', ['route_trailing_slash' => 'add']) === '/sitemap.xml', 'Slash policy changes file endpoints');
auditAssert(routeApplyTrailingSlash('/konu/mod-1.62-42', ['route_trailing_slash' => 'add']) === '/konu/mod-1.62-42/', 'Dotted topic slug disagrees with redirect policy');
auditAssert(seoCanonicalUrl('/turkmod/turkmod', ['route_trailing_slash' => 'remove']) === 'https://turkmod.test/turkmod', 'Page slug matching the app folder is mistaken for the root');

$unsafe = '<p onclick=alert(1)>Safe <strong>bold</strong><img src=x onerror=alert(1)><a href="jav&#x61;script:alert(1)">link</a></p>';
$safe = sanitizeHtml($unsafe);
auditAssert(!preg_match('/on(?:click|error)\s*=/i', $safe) && !str_contains($safe, 'javascript:'), 'HTML sanitizer retains executable attributes or URLs');
auditAssert(str_contains($safe, '<strong>bold</strong>'), 'HTML sanitizer removed supported formatting');
$schema = getStructuredData('DiscussionForumPosting', ['headline' => '</script><script>alert(1)</script>']);
auditAssert(substr_count($schema, '</script>') === 1 && !str_contains($schema, '<script>alert'), 'JSON-LD permits an HTML script escape');
auditAssert(json_decode(substr($schema, strpos($schema, '>') + 1, strrpos($schema, '</script>') - strpos($schema, '>') - 1), true)['headline'] === '</script><script>alert(1)</script>', 'Safe JSON-LD changed the title');

foreach ([[], ['page' => '1'], ['page' => '2', 'q' => 'ets2'], ['ids' => '1,2,3']] as $query) {
    auditAssert(App\Core\Http\QueryInput::isValid($query), 'Valid scalar query rejected');
}
foreach ([['q' => ['x']], ['page' => '0'], ['page' => '-1'], ['page' => '1.5'], ['page' => '1e5'], ['page' => '2147483648'], ['page' => []]] as $query) {
    auditAssert(!App\Core\Http\QueryInput::isValid($query), 'Malformed query accepted');
}
$backendNormalizer = new ReflectionMethod(App\Core\Bootstrap\Boot::class, 'normalizeBackend');
auditAssert($backendNormalizer->invoke(null, 'redis') === 'redis', 'Configured Redis backend rejected');
auditThrows(fn () => $backendNormalizer->invoke(null, 'unknown'), 'Invalid backend silently selected another store');
auditThrows(fn () => (new App\Core\AppSettings())->all(), 'Missing database silently used default settings');
$theme = new ThemeManager(dirname(__DIR__), '/turkmod');
$theme->setActiveTheme('turkmod');
auditAssert($theme->activeThemeId() === 'turkmod', 'Installed theme cannot be activated');
auditThrows(fn () => $theme->setActiveTheme('missing-audit-theme'), 'Missing theme silently selected a different installed theme');
auditAssert($theme->activeThemeId() === 'turkmod', 'Rejected activation changed the selected theme');
auditThrows(fn () => $theme->usesPublicRenderer('missing-audit-theme'), 'Missing manifest silently selected the legacy renderer');
auditThrows(fn () => $theme->publicAssetUrl('assets/a.js', 'missing-audit-theme'), 'Missing manifest silently used shared assets');
auditThrows(fn () => seoSitemapCacheKey('test', ['invalid' => "\xB1\x31"]), 'Invalid sitemap cache signature silently changed encodings');
auditThrows(fn () => formatSitemapXml('<urlset><url></urlset>'), 'Invalid sitemap XML was returned as a successful document');

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE topics (id INTEGER, slug TEXT, title TEXT, status TEXT, deleted_at TEXT, primary_media_file_id INTEGER, category_id INTEGER, author_id INTEGER)');
$pdo->exec('CREATE TABLE media_files (id INTEGER, path TEXT)');
$pdo->exec('CREATE TABLE categories (id INTEGER, name TEXT, slug TEXT, parent_id INTEGER)');
$pdo->exec('CREATE TABLE users (id INTEGER, username TEXT)');
$pdo->exec("INSERT INTO topics VALUES (1,'published','Published','published',NULL,NULL,NULL,NULL), (2,'draft','Private draft','draft',NULL,NULL,NULL,NULL), (3,'deleted','Deleted','published','2026-01-01',NULL,NULL,NULL)");
auditAssert(getTopic($pdo, 1)['title'] === 'Published', 'Published topic cannot be read');
auditAssert(getTopic($pdo, 2) === null, 'Unpublished topic is publicly accessible by ID');
auditAssert(getTopic($pdo, 3) === null, 'Deleted topic is publicly accessible');
auditThrows(fn() => getTopic(null, 1), 'Missing database was presented as a missing topic');
auditThrows(fn() => getTopics(null), 'Missing database was presented as an empty listing');
auditThrows(fn() => getPublicCategories(null), 'Missing database was replaced with sample categories');
auditThrows(fn() => getTopicComments(null, 1), 'Missing database was presented as an empty comment list');
$pdo->exec('ALTER TABLE users ADD COLUMN status TEXT');
$pdo->exec('ALTER TABLE users ADD COLUMN public_profile INTEGER');
$pdo->exec('ALTER TABLE users ADD COLUMN is_banned INTEGER');
$pdo->exec('ALTER TABLE users ADD COLUMN deleted_at TEXT');
$pdo->exec("INSERT INTO users VALUES (1, 'Public', 'active', 1, 0, NULL), (2, 'Private', 'active', 0, 0, NULL), (3, 'Banned', 'active', 1, 1, NULL), (4, 'Deleted', 'active', 1, 0, '2026-01-01'), (5, 'Inactive', 'inactive', 1, 0, NULL)");
auditAssert(publicProfileResolveUserBySlug($pdo, 'public-1')['id'] === 1, 'Visible public profile cannot be resolved');
foreach (['private-2', 'banned-3', 'deleted-4', 'inactive-5', 'wrong-1'] as $slug) {
    auditAssert(publicProfileResolveUserBySlug($pdo, $slug) === null, 'Private, removed, or incorrect public profile is accessible');
}
$pdo->exec('DROP TABLE users');
auditThrows(fn () => publicProfileResolveUserBySlug($pdo, 'public-1'), 'Profile database failure becomes a missing user');
$pdo->exec('DROP TABLE topics');
auditThrows(fn() => getTopic($pdo, 1), 'Database query failure was presented as a missing topic');

$health = new App\Engine\Seo\Http\HealthCheckPage(checksResolver: static fn() => ['db' => 'fail', 'cache' => 'ok', 'queue' => 'ok']);
$response = $health->handle(new App\Core\Http\Request('GET', '/health'));
auditAssert($response->getStatusCode() === 503, 'Degraded health check returns success');
$notFound = new App\Engine\Seo\Http\NotFoundPage(bodyRenderer: static fn() => '<h1>404</h1>');
$response = $notFound->handle(new App\Core\Http\Request('GET', '/missing'));
auditAssert($response->getStatusCode() === 404 && $response->getHeader('X-Robots-Tag') === 'noindex, nofollow', 'Missing pages can be indexed');
auditAssert($response->getHeader('Cache-Control') === 'no-store', 'Missing page may retain stale cached content');
auditAssert(seoPublicPageIsNoindexLocked('error_404'), '404 indexing can be enabled in SEO settings');
foreach (['profile', 'login', 'register', 'forgot_password', 'reset_password', 'notifications', 'messages', 'ban_appeals', 'upload_topic', 'edit_topic', 'download', 'error_404'] as $key) {
    auditAssert(!seoPublicPageShouldAppearInSitemap($key, []), 'Private/action page can appear in public sitemap');
}
auditAssert(!seoPublicPageShouldAppearInSitemap('ban_appeals', [seoPublicPagePresetSettingKey() => '{"ban_appeals":{"noindex":"0","sitemap_include":"1"}}']), 'SEO preset exposes an authenticated page in sitemap');

$inventoryPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$inventoryPdo->exec('CREATE TABLE topics (id INTEGER, slug TEXT, title TEXT, status TEXT, deleted_at TEXT, updated_at TEXT, published_at TEXT, primary_media_file_id INTEGER)');
$inventoryPdo->exec('CREATE TABLE media_files (id INTEGER, topic_id INTEGER, path TEXT, type TEXT, mime_type TEXT, original_name TEXT, is_primary INTEGER, display_order INTEGER)');
$inventoryPdo->exec('CREATE TABLE users (status TEXT, public_profile INTEGER, deleted_at TEXT, is_banned INTEGER, username TEXT)');
$inventory = new App\Engine\Seo\Support\SitemapInventory($inventoryPdo, static fn () => []);
$sitemapSettings = ['sitemap_exclude_drafts' => '0', 'index_draft_topics' => '1'];
$indexUrls = $inventory->indexUrls($sitemapSettings, 'https://turkmod.test');
auditAssert(count(array_filter($indexUrls, static fn ($url) => preg_match('~/(topic|image|category|profile)-sitemap~', $url))) === 0, 'Empty sitemap advertised in index despite returning 404');
auditAssert(!$inventory->pageIsValid('topic', 1, $sitemapSettings), 'Empty enabled sitemap is a valid index target');
auditAssert(seoSitemapTopicStatuses($sitemapSettings) === ['published'], 'Drafts can enter public sitemap');
$inventoryPdo->exec("INSERT INTO topics VALUES (1,'public','Public','published',NULL,'2026-11-01','2026-11-01',1), (2,'draft','Private','draft',NULL,'2026-11-01','2026-11-01',NULL)");
$inventoryPdo->exec("INSERT INTO media_files VALUES (1,1,'uploads/konu/test.webp','image','image/webp','test.webp',1,0)");
$inventory = new App\Engine\Seo\Support\SitemapInventory($inventoryPdo, static fn () => []);
auditAssert($inventory->total('topic', $sitemapSettings) === 1, 'Draft included in inventory count');
auditAssert($inventory->total('image', $sitemapSettings + ['robots_disallow_uploads' => '1']) === 0, 'Robots-blocked uploads included in image sitemap');
auditAssert($inventory->total('image', $sitemapSettings + ['robots_disallow_uploads' => '0']) === 1, 'Crawlable uploads absent from image sitemap');
$noDatabaseInventory = new App\Engine\Seo\Support\SitemapInventory();
auditThrows(fn () => $noDatabaseInventory->total('topic', []), 'Missing database becomes empty sitemap inventory');
$inventoryPdo->exec('DROP TABLE topics');
auditThrows(fn () => $inventory->total('topic', []), 'Failed query becomes empty sitemap inventory');

$scraper = new App\Engine\Scraper\Support\ScraperEngine([], []);
$html = '<h1>Example mod</h1><main><a href="https://modsfire.com/not-selected">Download</a><a class="actual-download" href="https://example.org/file.zip">File</a></main>';
$scraped = $scraper->parseTopicPage($html, ['title' => 'h1', 'content' => 'main', 'download_links' => '.missing'], 'https://example.org/mod');
auditAssert($scraped['download_links'] === [], 'Scraper guessed download links outside configured selector');
$scraped = $scraper->parseTopicPage($html, ['title' => 'h1', 'content' => 'main', 'download_links' => '.actual-download'], 'https://example.org/mod');
auditAssert(count($scraped['download_links']) === 1 && $scraped['download_links'][0]['url'] === 'https://example.org/file.zip', 'Configured download selector no longer works');

$grantPdo = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        if (str_contains($query, 'information_schema.tables')) { return parent::query('SELECT 1'); }
        return parent::query($query);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { throw new PDOException('Grant update denied'); }
};
auditThrows(fn () => topicDownloadRevokeAccessGrant($grantPdo, 1, 'comment_rejected'), 'Failed grant revocation became an ordinary false result');
echo 'PASS: ' . $GLOBALS['auditChecks'] . " project audit regression checks.\n";
