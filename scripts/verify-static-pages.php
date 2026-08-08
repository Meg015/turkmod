<?php

declare(strict_types=1);

use App\Modules\StaticPages\Database\migrations\Support\StaticPageSchemaInstaller;
use App\Modules\StaticPages\Services\StaticPageService;
use App\Modules\StaticPages\Services\StaticPageValidationException;

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

function verifyStaticPages(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectStaticPageValidation(callable $callback, string $field): void
{
    try {
        $callback();
    } catch (StaticPageValidationException $exception) {
        verifyStaticPages(isset($exception->errors()[$field]), 'Expected validation error for ' . $field);

        return;
    }

    throw new RuntimeException('Expected validation failure for ' . $field);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$installer = new StaticPageSchemaInstaller();
$installer->ensureSchema($pdo);
$installer->ensureSchema($pdo);

verifyStaticPages((int) $pdo->query('SELECT COUNT(*) FROM static_pages')->fetchColumn() === 5, 'System page seeding must be idempotent.');

$service = new StaticPageService($pdo);
verifyStaticPages($service->schemaReady(), 'Static page schema should be ready.');

$draft = $service->save([
    'title' => 'Test Sayfasi',
    'slug' => 'test-sayfasi',
    'body_html' => '',
    'status' => 'draft',
    'sitemap_include' => '1',
], null, 0)['page'];
$pageId = (int) ($draft['id'] ?? 0);
verifyStaticPages($pageId > 0, 'An empty draft should be created.');

expectStaticPageValidation(static fn () => $service->save([
    'title' => 'Bos Yayin',
    'slug' => 'bos-yayin',
    'body_html' => '<p>&nbsp;</p>',
    'status' => 'published',
], null, 0), 'body_html');

expectStaticPageValidation(static fn () => $service->save([
    'title' => 'Yasak Yol',
    'slug' => 'admin',
    'body_html' => '<p>Icerik</p>',
    'status' => 'published',
], null, 0), 'slug');

$published = $service->save([
    'title' => 'Test Sayfasi',
    'slug' => 'test-sayfasi',
    'body_html' => '<script>alert(1)</script><h2>Baslik</h2><p><a href="javascript:alert(2)">Metin</a></p>',
    'status' => 'published',
    'sitemap_include' => '1',
    'show_in_footer' => '1',
    'footer_label' => 'Test',
    'footer_order' => '60',
], $pageId, 0)['page'];
$body = (string) ($published['body_html'] ?? '');
verifyStaticPages(!str_contains(strtolower($body), '<script'), 'Script elements must be removed.');
verifyStaticPages(!str_contains(strtolower($body), 'javascript:'), 'Unsafe URLs must be removed.');
verifyStaticPages(($service->resolveRoute('test-sayfasi')['type'] ?? '') === 'page', 'Published page route should resolve.');
verifyStaticPages(count($service->footerPages()) === 1, 'Footer-eligible page should be listed.');
verifyStaticPages(count($service->sitemapPages()) === 1, 'Sitemap-eligible page should be listed.');

expectStaticPageValidation(static fn () => $service->save([
    'title' => 'Kopya',
    'slug' => 'test-sayfasi',
    'body_html' => '<p>Icerik</p>',
    'status' => 'draft',
], null, 0), 'slug');

$renamed = $service->save([
    'title' => 'Test Sayfasi',
    'slug' => 'yenilenen-test-sayfasi',
    'body_html' => '<h2>Baslik</h2><p>Temiz icerik</p>',
    'status' => 'published',
    'sitemap_include' => '1',
    'show_in_footer' => '1',
    'footer_label' => 'Test',
    'footer_order' => '60',
], $pageId, 0);
verifyStaticPages($renamed['redirect_created'] === true, 'Renaming a published page should create a redirect.');
verifyStaticPages(($service->resolveRoute('test-sayfasi')['type'] ?? '') === 'redirect', 'Old slug should resolve as redirect.');
verifyStaticPages(($service->resolveRoute('yenilenen-test-sayfasi')['type'] ?? '') === 'page', 'New slug should resolve as page.');

expectStaticPageValidation(static fn () => $service->save([
    'title' => 'Test Sayfasi',
    'slug' => 'test-sayfasi',
    'body_html' => '<p>Icerik</p>',
    'status' => 'published',
], $pageId, 0), 'slug');

verifyStaticPages($service->archive($pageId, 0), 'Published page should be archived.');
verifyStaticPages($service->resolveRoute('test-sayfasi') === null, 'Archived redirect must not resolve.');
verifyStaticPages($service->resolveRoute('yenilenen-test-sayfasi') === null, 'Archived page must not resolve.');
verifyStaticPages($service->footerPages() === [], 'Archived page must leave footer inventory.');
verifyStaticPages($service->sitemapPages() === [], 'Archived page must leave sitemap inventory.');
verifyStaticPages($service->restoreToDraft($pageId, 0), 'Archived page should restore to draft.');
verifyStaticPages($service->resolveRoute('yenilenen-test-sayfasi') === null, 'Restored draft must remain private.');

fwrite(STDOUT, "Static pages verification passed.\n");
