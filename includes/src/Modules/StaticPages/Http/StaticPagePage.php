<?php

declare(strict_types=1);

namespace App\Modules\StaticPages\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Throwable;

final class StaticPagePage implements Handler
{
    public function __construct(private ?string $rootPath = null)
    {
    }

    public function handle(Request $request): Response
    {
        $route = $GLOBALS['_static_page_route'] ?? null;
        $page = is_array($route) && is_array($route['page'] ?? null) ? $route['page'] : null;
        if (!is_array($page)) {
            return new Response('', 404, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        if (($route['type'] ?? '') === 'redirect') {
            $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
            $location = $baseUri . '/' . rawurlencode((string) ($page['slug'] ?? ''));

            return new Response('', 301, ['Location' => $location, 'Cache-Control' => 'public, max-age=3600']);
        }

        return new Response($this->render($page, !empty($route['preview'])), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => !empty($route['preview']) ? 'private, no-store' : 'public, max-age=300',
        ]);
    }

    /** @param array<string,mixed> $page */
    private function render(array $page, bool $preview): string
    {
        $rootPath = $this->rootPath ?? dirname(__DIR__, 5);
        $bufferLevel = ob_get_level();
        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            $baseUri = (string) ($GLOBALS['baseUri'] ?? '');
            $isLoggedIn = (bool) ($GLOBALS['isLoggedIn'] ?? false);
            $envConfig = is_array($GLOBALS['envConfig'] ?? null) ? $GLOBALS['envConfig'] : [];
            $_lay = is_array($GLOBALS['_lay'] ?? null)
                ? $GLOBALS['_lay']
                : (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);
            $siteName = trim((string) ($_lay['site_name'] ?? ($envConfig['APP_NAME'] ?? 'Icerik Topic')));
            $pageTitle = trim((string) ($page['title'] ?? ''));
            $seoTitle = trim((string) ($page['seo_title'] ?? ''));
            $seoTitle = $seoTitle !== '' ? $seoTitle : $pageTitle;
            $plainBody = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($page['body_html'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $metaDescription = trim((string) ($page['meta_description'] ?? ''));
            if ($metaDescription === '') {
                $metaDescription = mb_substr($plainBody, 0, 180, 'UTF-8');
            }
            $path = rtrim($baseUri, '/') . '/' . rawurlencode((string) ($page['slug'] ?? ''));
            $ogImage = trim((string) ($page['og_image'] ?? ''));
            $pageKey = 'static_page';
            $seoPageTitle = $seoTitle;
            $seoPageTitleIsFinal = false;
            $bodyClass = 'static-page-body';
            $pageCssFiles = ['assets/css/static-page.css'];
            $robots = [];
            $robots[] = $preview || !empty($page['robots_noindex']) ? 'noindex' : 'index';
            $robots[] = $preview || !empty($page['robots_nofollow']) ? 'nofollow' : 'follow';
            $robotsMetaOverride = implode(', ', $robots);
            $metaTitle = $seoTitle . ($siteName !== '' ? ' - ' . $siteName : '');
            $GLOBALS['_seo_skip_public_page_presets'] = true;
            $seoMetaTags = function_exists('getSeoMeta')
                ? getSeoMeta($metaTitle, $metaDescription, $path, $ogImage, true, 'website')
                : '';
            $seoStructuredData = function_exists('getStructuredData')
                ? getStructuredData('WebPage', [
                    'name' => $pageTitle,
                    'description' => $metaDescription,
                    'url' => function_exists('seoCanonicalUrl') ? seoCanonicalUrl($path, $_lay) : $path,
                    'datePublished' => (string) ($page['published_at'] ?? ''),
                    'dateModified' => (string) ($page['updated_at'] ?? ''),
                ])
                : '';
            $safeBody = function_exists('sanitizeTopicHtml')
                ? sanitizeTopicHtml((string) ($page['body_html'] ?? ''))
                : nl2br(htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8'));
            $GLOBALS['_public_page_key_override'] = 'static_page';

            ob_start();
            require $rootPath . '/includes/public-header.php';
            ?>
            <article class="static-page-view topic-section topic-descriptions ui-section" data-static-page-id="<?= (int) ($page['id'] ?? 0) ?>">
                <?php if ($preview): ?>
                    <div class="static-page-preview-banner" role="status"><i class="bi bi-eye"></i><span>Yönetici önizlemesi: Bu sayfa henüz ziyaretçilere açık olmayabilir.</span></div>
                <?php endif; ?>
                <header class="static-page-header">
                    <span class="static-page-kicker"><i class="bi bi-file-earmark-text"></i> Bilgilendirme</span>
                    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if (!empty($page['updated_at'])): ?><p>Son güncelleme: <?= htmlspecialchars((string) $page['updated_at'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </header>
                <div class="topic-content topic-detail-content static-page-content ui-section">
                    <?= $safeBody ?>
                </div>
            </article>
            <?php
            require $rootPath . '/includes/public-footer.php';
            $html = (string) ob_get_clean();
            unset($GLOBALS['_public_page_key_override'], $GLOBALS['_seo_skip_public_page_presets']);

            return $html;
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            unset($GLOBALS['_public_page_key_override'], $GLOBALS['_seo_skip_public_page_presets']);
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => self::class, 'page_id' => (int) ($page['id'] ?? 0)]);
            }
            throw $exception;
        }
    }
}
