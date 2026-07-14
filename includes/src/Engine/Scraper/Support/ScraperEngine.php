<?php

declare(strict_types=1);

namespace App\Engine\Scraper\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

require_once __DIR__ . '/ScraperEncodingNormalizer.php';
require_once __DIR__ . '/ScraperUrlGuard.php';
require_once dirname(__DIR__, 4) . '/helpers.php';

/**
 * Content Scraper Engine ï¿½ Fetch, Parse, Download, Translate
 */

class ScraperEngine
{
    private string $userAgent;
    private int $timeout;
    private int $delay;
    private int $retryCount;
    private int $retryDelay;
    private bool $followRedirects;
    private bool $sslVerify;
    private string $proxyUrl;
    private string $customHeaders;
    private ?string $deeplApiKey;
    private string $imageSavePath;
    private string $imageFilenameMode;
    private array $allowedImageExtensions;
    private bool $cleanHtmlEnabled;
    private bool $stripScripts;
    private bool $stripIframes;
    private int $maxImageBytes;
    private array $translationErrors = [];

    /**
     * İstek bazlı önbellek — aynı URL aynı request'te iki kez çekilmesin.
     */
    private static array $pageCache = [];

    /**
     * parseTopicPage önbelleği — aynı HTML tekrar parse edilmesin.
     */
    private static array $parseCache = [];

    /**
     * İstek önbelleğini temizler (her yeni request başında).
     */
    public static function clearCache(): void
    {
        self::$pageCache = [];
        self::$parseCache = [];
    }

    public function __construct(array $botSettings = [])
    {
        $this->userAgent = $botSettings['bot_user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $this->timeout = (int)($botSettings['bot_request_timeout'] ?? 30);
        $this->delay = (int)($botSettings['bot_request_delay'] ?? 1000);
        $this->retryCount = max(0, (int)($botSettings['bot_retry_count'] ?? 1));
        $this->retryDelay = max(0, (int)($botSettings['bot_retry_delay'] ?? 750));
        $this->followRedirects = ($botSettings['bot_follow_redirects'] ?? '1') === '1';
        $this->sslVerify = ($botSettings['bot_ssl_verify'] ?? '1') === '1';
        $proxyUrl = trim((string)($botSettings['bot_proxy_url'] ?? ''));
        $this->proxyUrl = $this->isValidProxyUrl($proxyUrl) ? $proxyUrl : '';
        $this->customHeaders = trim((string)($botSettings['bot_custom_headers'] ?? ''));
        $this->deeplApiKey = $botSettings['bot_deepl_api_key'] ?? '';
        $rawImageSavePath = trim((string)($botSettings['bot_image_save_path'] ?? 'uploads/konu/'));
        if ($rawImageSavePath === '' || $rawImageSavePath === 'uploads' || $rawImageSavePath === 'uploads/') {
            $rawImageSavePath = 'uploads/konu';
        }
        $rawImageSavePath = str_replace('\\', '/', $rawImageSavePath);
        $rawImageSavePath = preg_replace('#/+#', '/', $rawImageSavePath) ?: '';
        if (
            !str_starts_with($rawImageSavePath, 'uploads/') ||
            str_contains($rawImageSavePath, '../') ||
            str_contains($rawImageSavePath, '/..')
        ) {
            $rawImageSavePath = 'uploads/konu';
        }
        $this->imageSavePath = rtrim($rawImageSavePath, '/') . '/';
        $imageFilenameMode = $botSettings['bot_image_filename_mode'] ?? 'slug';
        $this->imageFilenameMode = in_array($imageFilenameMode, ['slug', 'hash', 'original'], true)
            ? $imageFilenameMode
            : 'slug';
        $extensions = array_filter(array_map('trim', explode(',', strtolower((string)($botSettings['bot_allowed_image_extensions'] ?? 'jpg,jpeg,png,webp,gif')))));
        $this->allowedImageExtensions = $extensions ?: ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $this->cleanHtmlEnabled = ($botSettings['bot_clean_html'] ?? '1') === '1';
        $this->stripScripts = ($botSettings['bot_strip_scripts'] ?? '1') === '1';
        $this->stripIframes = ($botSettings['bot_strip_iframes'] ?? '1') === '1';
        $this->maxImageBytes = max(1, (int)($botSettings['bot_max_image_bytes'] ?? 8 * 1024 * 1024));
    }

    private function isSafeFetchUrl(string $url): bool
    {
        return ScraperUrlGuard::isSafe($url);
    }

    private function isSteamCommunityUrl(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        return $host === 'steamcommunity.com' || $host === 'www.steamcommunity.com';
    }

    private function steamCommunityHeaders(string $url): array
    {
        if (!$this->isSteamCommunityUrl($url)) {
            return [];
        }

        return [
            'Cookie: birthtime=568022401; lastagecheckage=1-January-1988; mature_content=1',
            'Referer: https://steamcommunity.com/',
        ];
    }

    /**
     * Fetch a page via cURL and return HTML content.
     */
    public function fetchPage(string $url, array $customHeaders = []): ?string
    {
        $html = $this->fetchUrl($url, $customHeaders, false);
        return is_string($html) ? $this->normalizeHtmlEncoding($html) : null;
    }

    private function fetchBinary(string $url, array $customHeaders = []): ?string
    {
        return $this->fetchUrl($url, $customHeaders, true);
    }

    public function fetchPreviewImage(string $url): ?array
    {
        $data = $this->fetchBinary($url);
        if (!is_string($data) || $data === '' || strlen($data) > $this->maxImageBytes) {
            return null;
        }

        $info = getimagesizefromstring($data);
        if (!is_array($info) || empty($info['mime']) || !str_starts_with((string)$info['mime'], 'image/')) {
            return null;
        }

        $mime = strtolower((string)$info['mime']);
        $mimeExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];

        $allowed = false;
        foreach ($mimeExtensions[$mime] ?? [] as $extension) {
            if (in_array($extension, $this->allowedImageExtensions, true)) {
                $allowed = true;
                break;
            }
        }

        return $allowed ? ['data' => $data, 'mime' => $mime] : null;
    }

    private function fetchUrl(string $url, array $customHeaders = [], bool $binary = false): ?string
    {
        if (!$this->isSafeFetchUrl($url)) {
            return null;
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        // HTTP sayfaları için önbellek (binary değilse)
        if (!$binary) {
            $cacheKey = 'page:' . $url;
            if (array_key_exists($cacheKey, self::$pageCache)) {
                return self::$pageCache[$cacheKey];
            }
        }

        $headers = $binary
            ? ['Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8', 'Accept-Language: tr,en;q=0.5']
            : ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: tr,en;q=0.5'];
        array_push($headers, ...$this->steamCommunityHeaders($url));
        if ($this->customHeaders !== '') {
            $customHeaders[] = $this->customHeaders;
        }
        if (!empty($customHeaders)) {
            array_push($headers, ...$this->sanitizeCustomHeaders(implode("\n", (array)$customHeaders)));
        }

        $currentUrl = $url;
        $redirects = 0;
        $maxRedirects = 5;
        $redirectTimeout = 10; // seconds
        $redirectStartTime = time();

        while (true) {
            $inspection = ScraperUrlGuard::inspect($currentUrl);
            if ($inspection === null) {
                return null;
            }

            // Redirect timeout protection
            if (time() - $redirectStartTime > $redirectTimeout) {
                return null;
            }

            // Redirect limit protection
            if ($redirects >= $maxRedirects) {
                return null;
            }

            for ($attempt = 0; $attempt <= $this->retryCount; $attempt++) {
            $requestHeaders = $headers;
            array_push($requestHeaders, ...$this->steamCommunityHeaders($currentUrl));
            $connectTimeout = max(1, min(10, $this->timeout));
            $ch = curl_init();
            $curlOptions = [
                CURLOPT_URL            => $currentUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER         => true,
                CURLOPT_MAXREDIRS      => 0,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_USERAGENT      => $this->userAgent,
                CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
                CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => array_values(array_unique($requestHeaders)),
            ];
            if (defined('CURLOPT_PROTOCOLS')) {
                $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS')) {
                $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
            if (defined('CURLOPT_RESOLVE')) {
                $curlOptions[CURLOPT_RESOLVE] = [$inspection['curl_resolve']];
            }
            curl_setopt_array($ch, $curlOptions);
            if ($this->proxyUrl !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxyUrl);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if (is_string($response) && $httpCode >= 300 && $httpCode < 400 && $this->followRedirects) {
                $headerBlock = substr($response, 0, $headerSize);
                if (preg_match('/^Location:\s*(.+)$/mi', $headerBlock, $matches) === 1) {
                    $nextUrl = $this->resolveRedirectUrl($currentUrl, trim($matches[1]));
                    if ($nextUrl === null || !$this->isSafeFetchUrl($nextUrl) || ++$redirects > 5) {
                        return null;
                    }
                    $currentUrl = $nextUrl;
                    continue 2;
                }
            }

            $body = is_string($response) ? substr($response, $headerSize) : null;
            if ($httpCode >= 200 && $httpCode < 300 && is_string($body) && $body !== '') {
                // Önbelleğe kaydet
                if (!$binary) {
                    self::$pageCache['page:' . $url] = $body;
                }
                return $body;
            }
            if ($attempt < $this->retryCount && $this->retryDelay > 0) {
                usleep($this->retryDelay * 1000);
            }
        }

        return null;
        }
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        if ($location === '') {
            return null;
        }
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $port = isset($base['port']) ? ':' . $base['port'] : '';
        if (str_starts_with($location, '//')) {
            return $base['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $port . $location;
        }

        $path = (string)($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $base['scheme'] . '://' . $base['host'] . $port . ($dir !== '' ? $dir : '') . '/' . $location;
    }

    private function normalizeHtmlEncoding(string $html): string
    {
        return ScraperEncodingNormalizer::normalizeHtml($html);
    }

    private function prepareHtmlForDom(string $html, bool $prependMeta = true): string
    {
        $html = $this->normalizeHtmlEncoding($html);
        $html = preg_replace('/<meta[^>]+charset=["\']?[^"\'>\s]+["\']?[^>]*>/i', '', $html) ?? $html;

        return $prependMeta
            ? '<meta charset="UTF-8">' . $html
            : $html;
    }

    /**
     * Parse HTML and extract content using CSS selectors.
     */
    public function parseTopicPage(string $html, array $selectors, string $baseUrl = ''): array
    {
        // Aynı HTML'i tekrar parse etme (önbellek)
        $cacheKey = 'parse:' . md5($html) . ':' . md5(json_encode($selectors));
        if (array_key_exists($cacheKey, self::$parseCache)) {
            return self::$parseCache[$cacheKey];
        }

        $result = [
            'title'          => '',
            'content'        => '',
            'images'         => [],
            'download_links' => [],
        ];

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $html = $this->prepareHtmlForDom($html);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        libxml_clear_errors();

        // Title
        if (!empty($selectors['title'])) {
            $xp = $this->cssToXPath($selectors['title']);
            $nodes = $xpath->query($xp);
            if ($nodes && $nodes->length > 0) {
                $result['title'] = trim($nodes->item(0)->textContent);
            }
        }

        // Content
        if (!empty($selectors['content'])) {
            $xp = $this->cssToXPath($selectors['content']);
            $nodes = $xpath->query($xp);
            if ($nodes && $nodes->length > 0) {
                // Remove .post-meta elements
                $metaNodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " post-meta ")]');
                if ($metaNodes) {
                    foreach ($metaNodes as $metaNode) {
                        if ($metaNode->parentNode) {
                            $metaNode->parentNode->removeChild($metaNode);
                        }
                    }
                }

                $innerHtml = '';
                foreach ($nodes as $node) {
                    $innerHtml .= $this->getInnerHtml($doc, $node);
                }
                $result['content'] = $this->cleanHtmlWithSettings($innerHtml);
            }
        }

        // Images
        if (!empty($selectors['images'])) {
            $xp = $this->cssToXPath($selectors['images']);
            $nodes = $xpath->query($xp);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $src = $node instanceof DOMElement ? $this->extractImageSource($node) : '';
                    if (empty($src) && strtolower($node->nodeName) === 'a') {
                        $src = $node->getAttribute('href');
                    }
                    if ($src && !str_starts_with($src, 'data:image')) {
                        $resolved = $this->resolveUrl($src, $baseUrl);
                        if (!in_array($resolved, $result['images'])) {
                            $result['images'][] = $resolved;
                        }
                    }
                }
            }
        }

        // Download Links
        if (!empty($selectors['download_links'])) {
            $xp = $this->cssToXPath($selectors['download_links']);
            $nodes = $xpath->query($xp);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $href = $node->getAttribute('href');
                    $text = trim($node->textContent) ?: 'Link';
                    if ($href && $href !== '#' && !str_starts_with($href, 'javascript:')) {
                        $result['download_links'][] = [
                            'name' => $text,
                            'url'  => $this->resolveUrl($href, $baseUrl),
                        ];
                    }
                }
            }
        }

        if ($this->isSteamCommunityUrl($baseUrl)) {
            $result = $this->applySteamCommunityFallback($doc, $xpath, $result, $baseUrl);
        }

        // Parse sonucunu önbelleğe kaydet
        self::$parseCache[$cacheKey] = $result;

        return $result;
    }

    private function applySteamCommunityFallback(DOMDocument $doc, DOMXPath $xpath, array $result, string $pageUrl): array
    {
        if (trim((string)$result['title']) === '') {
            $title = $this->firstXPathText($xpath, [
                '//*[contains(concat(" ", normalize-space(@class), " "), " workshopItemTitle ")]',
                '//*[@id="ig_bottom"]//*[contains(concat(" ", normalize-space(@class), " "), " apphub_AppName ")]',
                '//meta[@property="og:title"]/@content',
            ]);
            $result['title'] = preg_replace('/\s+/', ' ', trim($title)) ?: $result['title'];
        }

        if (trim(strip_tags((string)$result['content'])) === '') {
            $contentNode = $this->firstXPathElement($xpath, [
                '//*[@id="highlightContent"]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " workshopItemDescription ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " workshopItemDescriptionForCollection ")]',
            ]);
            if ($contentNode) {
                $result['content'] = $this->cleanHtmlWithSettings($this->getInnerHtml($doc, $contentNode));
            }
        }

        if (empty($result['images'])) {
            $imageQueries = [
                '//*[@id="previewImage"]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " workshopItemPreviewImageMain ")]//img',
                '//*[contains(concat(" ", normalize-space(@class), " "), " highlight_player_item ")]//img',
                '//meta[@property="og:image"]/@content',
                '//img[contains(@src, "steamusercontent") or contains(@src, "steamstatic")]',
            ];
            foreach ($imageQueries as $query) {
                $nodes = $xpath->query($query);
                if (!$nodes) continue;
                foreach ($nodes as $node) {
                    $src = $node instanceof DOMAttr
                        ? $node->value
                        : ($node instanceof DOMElement ? $this->extractImageSource($node) : '');
                    if ($src === '' || str_contains($src, '/public/images/sharedfiles/')) {
                        continue;
                    }
                    $resolved = $this->resolveUrl($src, $pageUrl);
                    if (!in_array($resolved, $result['images'], true)) {
                        $result['images'][] = $resolved;
                    }
                }
                if (!empty($result['images'])) {
                    break;
                }
            }
        }

        if (empty($result['download_links']) && $pageUrl !== '') {
            $result['download_links'][] = [
                'name' => 'Steam Workshop',
                'url' => $pageUrl,
            ];
        }

        return $result;
    }

    private function firstXPathText(DOMXPath $xpath, array $queries): string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }
            $node = $nodes->item(0);
            $text = $node instanceof DOMAttr ? $node->value : ($node ? $node->textContent : '');
            $text = trim((string)$text);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function firstXPathElement(DOMXPath $xpath, array $queries): ?DOMElement
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }
            $node = $nodes->item(0);
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Discover topic URLs from a category listing page with pagination in a single HTML parse.
     * Combines discoverTopicUrls + discoverPaginationUrl to avoid parsing the same HTML twice.
     */
    public function discoverTopicUrlsWithPagination(string $html, array $selectors, string $baseUrl = ''): array
    {
        $items = [];
        $urls = [];
        $nextUrl = '';
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $html = $this->prepareHtmlForDom($html);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        libxml_clear_errors();

        // Discover topic URLs
        $selector = $selectors['topic_list'] ?? $selectors['topic_link'] ?? '';
        if ($selector !== '') {
            $xp = $this->cssToXPath($selector);
            $nodes = $xpath->query($xp);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $href = '';
                    $title = '';
                    $image = '';
                    
                    if (!empty($selectors['topic_list']) && !empty($selectors['topic_link']) && strtolower($node->nodeName) !== 'a') {
                        $linkXpath = $this->cssToXPath($selectors['topic_link']);
                        $linkXpath = preg_replace('#^//#', './/', $linkXpath);
                        $links = $xpath->query($linkXpath, $node);
                        if ($links && $links->length > 0) {
                            $a = $links->item(0);
                            $href = $a->getAttribute('href');
                            $title = trim($a->textContent);
                        }
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    } elseif (strtolower($node->nodeName) === 'a') {
                        $href = $node->getAttribute('href');
                        $title = trim($node->textContent);
                        
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    } else {
                        $links = $node->getElementsByTagName('a');
                        if ($links->length > 0) {
                            $a = $links->item(0);
                            $href = $a->getAttribute('href');
                            $title = trim($a->textContent);
                            if ($title === '') {
                                foreach ($links as $l) {
                                    if (trim($l->textContent) !== '') {
                                        $title = trim($l->textContent);
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    }
                    
                    if ($href && $href !== '#') {
                        $resolved = $this->resolveUrl($href, $baseUrl);
                        if (!in_array($resolved, $urls)) {
                            $urls[] = $resolved;
                            $resolvedImg = $image ? $this->resolveUrl($image, $baseUrl) : '';
                            $items[] = [
                                'url' => $resolved,
                                'title' => $title,
                                'image' => $resolvedImg
                            ];
                        }
                    }
                }
            }
        }

        // Discover pagination URL using same DOM
        $selector = trim((string)($selectors['pagination'] ?? ''));
        $queries = [];
        if ($selector !== '') {
            $queries[] = $this->cssToXPath($selector);
        }
        $queries[] = '//a[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " next ")]';
        $queries[] = '//a[contains(translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "next") or contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "next") or contains(translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "sonraki") or contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "sonraki")]';
        $queries[] = '//a[contains(concat(" ", normalize-space(@class), " "), " next ")]';
        $queries[] = '//*[contains(concat(" ", normalize-space(@class), " "), " next ")]//a';
        $queries[] = '//nav//a';
        $queries[] = '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pagination") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pager") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "page-numbers") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "nav-links") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pagenavi")]//a';
        $queries[] = '//a[contains(normalize-space(.), "Next") or contains(normalize-space(.), "Older") or contains(normalize-space(.), "Sonraki") or contains(normalize-space(.), "ÿ") or contains(normalize-space(.), "þ")]';

        $nextUrl = $this->findPaginationUrl($xpath, $queries, $baseUrl, true);
        if ($nextUrl === '' && $selector !== '') {
            $nextUrl = $this->findPaginationUrl($xpath, [$this->cssToXPath($selector)], $baseUrl, false);
        }

        return ['items' => $items, 'nextUrl' => $nextUrl];
    }

    /**
     * Discover topic URLs from a category listing page.
     */
    public function discoverTopicUrls(string $html, array $selectors, string $baseUrl = ''): array
    {
        $items = [];
        $urls = [];
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $html = $this->prepareHtmlForDom($html);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        libxml_clear_errors();

        $selector = $selectors['topic_list'] ?? $selectors['topic_link'] ?? '';
        if ($selector !== '') {
            $xp = $this->cssToXPath($selector);
            $nodes = $xpath->query($xp);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $href = '';
                    $title = '';
                    $image = '';
                    
                    if (!empty($selectors['topic_list']) && !empty($selectors['topic_link']) && strtolower($node->nodeName) !== 'a') {
                        $linkXpath = $this->cssToXPath($selectors['topic_link']);
                        $linkXpath = preg_replace('#^//#', './/', $linkXpath);
                        $links = $xpath->query($linkXpath, $node);
                        if ($links && $links->length > 0) {
                            $a = $links->item(0);
                            $href = $a->getAttribute('href');
                            $title = trim($a->textContent);
                        }
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    } elseif (strtolower($node->nodeName) === 'a') {
                        $href = $node->getAttribute('href');
                        $title = trim($node->textContent);
                        
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    } else {
                        $links = $node->getElementsByTagName('a');
                        if ($links->length > 0) {
                            $a = $links->item(0);
                            $href = $a->getAttribute('href');
                            $title = trim($a->textContent);
                            if ($title === '') {
                                foreach ($links as $l) {
                                    if (trim($l->textContent) !== '') {
                                        $title = trim($l->textContent);
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $image = $this->extractThumbnailNearNode($node, $xpath);
                    }
                    
                    if ($href && $href !== '#') {
                        $resolved = $this->resolveUrl($href, $baseUrl);
                        if (!in_array($resolved, $urls)) {
                            $urls[] = $resolved;
                            $resolvedImg = $image ? $this->resolveUrl($image, $baseUrl) : '';
                            $items[] = [
                                'url' => $resolved,
                                'title' => $title,
                                'image' => $resolvedImg
                            ];
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Discover the next pagination URL from a category listing page.
     */
    public function discoverPaginationUrl(string $html, array $selectors, string $baseUrl = ''): string
    {
        $selector = trim((string)($selectors['pagination'] ?? ''));

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $html = $this->prepareHtmlForDom($html);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        libxml_clear_errors();

        $queries = [];
        if ($selector !== '') {
            $queries[] = $this->cssToXPath($selector);
        }
        $queries[] = '//a[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " next ")]';
        $queries[] = '//a[contains(translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "next") or contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "next") or contains(translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "sonraki") or contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "sonraki")]';
        $queries[] = '//a[contains(concat(" ", normalize-space(@class), " "), " next ")]';
        $queries[] = '//*[contains(concat(" ", normalize-space(@class), " "), " next ")]//a';
        $queries[] = '//nav//a';
        $queries[] = '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pagination") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pager") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "page-numbers") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "nav-links") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "pagenavi")]//a';
        $queries[] = '//a[contains(normalize-space(.), "Next") or contains(normalize-space(.), "Older") or contains(normalize-space(.), "Sonraki") or contains(normalize-space(.), "ï¿½") or contains(normalize-space(.), "ï¿½")]';

        $nextUrl = $this->findPaginationUrl($xpath, $queries, $baseUrl, true);
        if ($nextUrl !== '') {
            return $nextUrl;
        }

        if ($selector !== '') {
            return $this->findPaginationUrl($xpath, [$this->cssToXPath($selector)], $baseUrl, false);
        }

        return '';
    }

    /**
     * Download an image and save it locally.
     */
    public function downloadImage(string $imageUrl, string $siteSlug, string $topicSlug, ?int $sequence = null): ?string
    {
        $dir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $appBasePath = dirname(__DIR__, 5);

        $datePath = date('Y/m');
        $saveDir = $appBasePath . '/' . rtrim($this->imageSavePath, '/') . '/' . $datePath;

        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
        $ext = preg_replace('/[^a-z0-9]/i', '', strtolower($ext));
        if (!in_array($ext, $this->allowedImageExtensions, true)) {
            $ext = 'jpg';
        }
        $filename = uploadTitleFilename($topicSlug, $ext, $sequence);
        $filename = uploadAvailableFilename($saveDir, $filename);

        // Path traversal protection: ensure filename doesn't contain directory separators
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
            return null;
        }

        $fullPath = $saveDir . '/' . $filename;

        // Verify resolved path is within saveDir to prevent symlink attacks
        $realSaveDir = realpath($saveDir);
        $realTargetDir = realpath(dirname($fullPath));
        if ($realSaveDir === false || $realTargetDir === false) {
            return null;
        }

        $normalizePath = static function (string $path): string {
            return rtrim(str_replace('\\', '/', $path), '/');
        };
        $normalizedSaveDir = $normalizePath($realSaveDir);
        $normalizedTargetDir = $normalizePath($realTargetDir);
        if (
            $normalizedTargetDir !== $normalizedSaveDir &&
            !str_starts_with($normalizedTargetDir . '/', $normalizedSaveDir . '/')
        ) {
            return null;
        }

        $imageData = $this->fetchBinary($imageUrl);
        if (!$imageData || !$this->isValidImagePayload($imageData, $ext)) return null;

        if (file_put_contents($fullPath, $imageData) !== false) {
            // Bilinen boï¿½/placeholder resimleri MD5 hash ile engelle
            if (md5_file($fullPath) === '006a3226b7a233d4a830078f7237868b') {
                unlink($fullPath);
                return null;
            }

            return rtrim($this->imageSavePath, '/') . '/' . $datePath . '/' . $filename;
        }

        return null;
    }

    /**
     * Translate text via DeepL API.
     */
    public function translateText(string $text, string $sourceLang = 'EN', string $targetLang = 'TR'): ?string
    {
        if (empty($this->deeplApiKey) || trim($text) === '') {
            return null;
        }

        $maxRetries = 3;
        $lastError = null;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $isFree = str_ends_with($this->deeplApiKey, ':fx');
            $apiUrl = $isFree
                ? 'https://api-free.deepl.com/v2/translate'
                : 'https://api.deepl.com/v2/translate';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: DeepL-Auth-Key ' . $this->deeplApiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode([
                    'text'        => [$text],
                    'source_lang' => strtoupper($sourceLang),
                    'target_lang' => strtoupper($targetLang),
                ]),
                CURLOPT_SSL_VERIFYPEER => $this->isSslVerifyEnabled(),
                CURLOPT_SSL_VERIFYHOST => $this->isSslVerifyEnabled() ? 2 : 0,
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['translations'][0]['text'] ?? null;
            }

            $lastError = $curlError !== '' ? $curlError : ('HTTP ' . (int)$httpCode);

            // Retry on 429 (rate limit) or 5xx errors, but not on 4xx client errors
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                break;
            }

            if ($attempt < $maxRetries - 1) {
                $backoffSeconds = 2 ** $attempt;
                usleep($backoffSeconds * 1000000);
            }
        }

        $this->translationErrors[] = 'DeepL çeviri hatası: ' . $lastError;
        return null;
    }

    public function getTranslationErrors(): array
    {
        return $this->translationErrors;
    }

    /**
     * Full scrape pipeline for a single URL.
     */
    public function scrapeUrl(string $url, array $siteConfig, array $botSettings = []): array
    {
        $selectors = $siteConfig['selectors'] ?? [];
        $settings = $siteConfig['settings'] ?? [];
        $baseUrl = $siteConfig['base_url'] ?? '';
        $siteSlug = $siteConfig['slug'] ?? 'unknown';
        $maxImages = (int)($settings['max_images'] ?? $botSettings['bot_default_max_images'] ?? 5);
        $doTranslate = ($settings['translate'] ?? false) || ($botSettings['bot_translate_enabled'] ?? '0') === '1';
        $srcLang = $settings['source_lang'] ?? $botSettings['bot_source_lang'] ?? 'EN';
        $tgtLang = $settings['target_lang'] ?? $botSettings['bot_target_lang'] ?? 'TR';
        $contentAlign = (string)($settings['content_align'] ?? $botSettings['bot_content_align'] ?? 'center');
        $downloadImages = ($botSettings['bot_download_images'] ?? '1') === '1';
        $useHotlinkImages = ($botSettings['bot_use_hotlink_images'] ?? '0') === '1';
        $extractDownloadLinks = ($botSettings['bot_extract_download_links'] ?? '1') === '1';
        $appendSourceLink = ($botSettings['bot_append_source_link'] ?? '0') === '1';
        $minTitleLength = max(0, (int)($botSettings['bot_min_title_length'] ?? 0));
        $minContentLength = max(0, (int)($botSettings['bot_min_content_length'] ?? 0));
        $requireCoverImage = ($botSettings['bot_require_cover_image'] ?? '0') === '1';
        $translateTitle = ($botSettings['bot_translate_title'] ?? '1') === '1';
        $translateContent = ($botSettings['bot_translate_content'] ?? '1') === '1';
        $translateDownloadNames = ($botSettings['bot_translate_download_names'] ?? '0') === '1';
        $translationFallbackOriginal = ($botSettings['bot_translation_fallback_original'] ?? '1') === '1';
        $replacementRules = is_array($settings['replacements'] ?? null) ? $settings['replacements'] : [];
        $this->translationErrors = [];

        $result = [
            'success'        => false,
            'title'          => '',
            'translated_title' => '',
            'author_topic'   => '',
            'topic_version'  => '',
            'content'        => '',
            'translated_content' => '',
            'images'         => [],
            'downloaded_images' => [],
            'download_links' => '',
            'images_count'   => 0,
            'detection_meta'  => ['author_topic' => false, 'topic_version' => false],
            'translation_errors' => [],
            'error'          => '',
        ];

        if (!$this->isAllowedSourceUrl($url, $baseUrl)) {
            $urlHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
            $baseHost = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''));
            $result['error'] = 'URL (' . $urlHost . ') bu kaynak site domaini (' . $baseHost . ') ile eşleşmiyor.';
            return $result;
        }

        // Delay between requests
        if ($this->delay > 0) {
            usleep($this->delay * 1000);
        }

        // Fetch page
        $customHeaders = $settings['custom_headers'] ?? '';
        $html = $this->fetchPage($url, $customHeaders ? [$customHeaders] : []);
        if (!$html) {
            $result['error'] = 'Sayfa indirilemedi. URL geçersiz, site erişilemez durumda veya güvenlik duvarı tarafından engelleniyor olabilir: ' . $url;
            return $result;
        }

        // Parse content
        $parsed = $this->parseTopicPage($html, $selectors, $url);
        $authorDetection = $this->detectAuthorFromContent($parsed['content'] ?? '', $settings, $botSettings);
        $versionDetection = $this->detectVersionFromContent($parsed['content'] ?? '', $settings, $botSettings);
        $result['title'] = $this->applySiteTextReplacements($parsed['title'], $replacementRules, 'title');
        $content = $this->applySiteTextReplacements($parsed['content'], $replacementRules, 'content');
        $result['content'] = $this->applyContentAlignment($content, $contentAlign);
        $result['images'] = array_slice($parsed['images'], 0, $maxImages);

        if ($minTitleLength > 0 && mb_strlen(trim($result['title'])) < $minTitleLength) {
            $result['error'] = 'Baï¿½lï¿½k minimum uzunluk sï¿½nï¿½rï¿½nï¿½n altï¿½nda.';
            return $result;
        }
        if ($minContentLength > 0 && mb_strlen(trim(strip_tags($result['content']))) < $minContentLength) {
            $result['error'] = 'ï¿½ï¿½erik minimum uzunluk sï¿½nï¿½rï¿½nï¿½n altï¿½nda.';
            return $result;
        }
        
        if (trim($result['title']) === '' && trim(strip_tags($result['content'])) === '' && empty($result['images']) && empty($parsed['download_links'])) {
            $result['error'] = 'İçerik bulunamadı (Seçiciler eşleşmedi veya sayfa yapısı uyumsuz).';
            return $result;
        }
        if ($appendSourceLink) {
            $result['content'] .= '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="nofollow noopener" target="_blank">Kaynak</a></p>';
        }

        // Download links
        $dlLines = [];
        if ($extractDownloadLinks) {
            foreach ($parsed['download_links'] as $dl) {
                $name = $this->applySiteTextReplacements($dl['name'] ?? 'Link', $replacementRules, 'download_links');
                $dlLines[] = $name . '|' . ($dl['url'] ?? '');
            }
        }
        $result['download_links'] = implode("\n", $dlLines);
        $result['author_topic'] = $authorDetection;
        $result['topic_version'] = $versionDetection;
        $result['detection_meta'] = [
            'author_topic' => $authorDetection !== '',
            'topic_version' => $versionDetection !== '',
        ];
        $result = $this->applySiteCustomization($result, $settings, $botSettings);

        // Download images
        $topicSlug = slugify($result['title']) ?: 'topic-' . time();
        if ($downloadImages) {
            foreach ($result['images'] as $index => $imgUrl) {
                $localPath = $this->downloadImage($imgUrl, $siteSlug, $topicSlug, $index + 1);
                if ($localPath) {
                    $result['downloaded_images'][] = $localPath;
                }
            }
        } elseif ($useHotlinkImages) {
            $result['downloaded_images'] = $result['images'];
        }
        if ($requireCoverImage && empty($result['downloaded_images']) && empty($result['images'])) {
            $result['error'] = 'Kapak gï¿½rseli bulunamadï¿½.';
            return $result;
        }
        $result['images_count'] = count($result['downloaded_images']);

        // Translate
        if ($doTranslate && $this->deeplApiKey) {
            if ($translateTitle && $result['title']) {
                $result['translated_title'] = $this->translateText($result['title'], $srcLang, $tgtLang) ?? ($translationFallbackOriginal ? $result['title'] : '');
            }
            if ($translateContent && $result['content']) {
                // Translate in chunks for long content
                $contentText = strip_tags($result['content']);
                if (mb_strlen($contentText) > 4500) {
                    $chunks = $this->splitTextForTranslation($contentText, 4500);
                    $translated = '';
                    foreach ($chunks as $chunk) {
                        $t = $this->translateText($chunk, $srcLang, $tgtLang);
                        $translated .= ($t ?? $chunk);
                    }
                    $result['translated_content'] = $this->applyContentAlignment($translated, $contentAlign);
                } else {
                    $translatedContent = $this->translateText($contentText, $srcLang, $tgtLang);
                    $result['translated_content'] = $this->applyContentAlignment($translatedContent ?? ($translationFallbackOriginal ? $contentText : ''), $contentAlign);
                }
            }
            if ($translateDownloadNames && $result['download_links']) {
                $result['download_links'] = $this->translateDownloadLinkNames($result['download_links'], $srcLang, $tgtLang, $translationFallbackOriginal);
            }
        }

        $result['translation_errors'] = $this->getTranslationErrors();
        $result['success'] = true;
        return $result;
    }

    // ï¿½ï¿½ï¿½ Utility Methods ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½ï¿½

    /**
     * Simple CSS selector to XPath converter.
     */
    private function cssToXPath(string $css): string
    {
        $css = trim($css);
        if (str_starts_with($css, '//') || str_starts_with($css, '(//')) {
            return $css; // Already XPath
        }

        // Handle comma-separated selectors
        if (str_contains($css, ',')) {
            $parts = array_map('trim', explode(',', $css));
            $xpaths = [];
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $xpaths[] = $this->cssToXPath($part);
                }
            }
            return implode(' | ', $xpaths);
        }

        $parts = preg_split('/\s+/', $css);
        $xpathParts = [];

        foreach ($parts as $part) {
            if ($part === '>') {
                $xpathParts[] = '/';
                continue;
            }

            $xp = '';
            // Handle tag#id.class patterns
            if (preg_match('/^([a-zA-Z0-9_-]*)(?:#([a-zA-Z0-9_-]+))?(?:\.([a-zA-Z0-9_.-]+))?(?:\[([^\]]+)\])?$/', $part, $m)) {
                $tag = $m[1] ?: '*';
                $id = $m[2] ?? '';
                $classes = $m[3] ?? '';
                $attr = $m[4] ?? '';

                $xp = $tag;
                $conditions = [];

                if ($id) $conditions[] = "@id=" . $this->xpathLiteral($id);
                if ($classes) {
                    foreach (explode('.', $classes) as $cls) {
                        if ($cls) $conditions[] = "contains(concat(' ',normalize-space(@class),' ')," . $this->xpathLiteral(' ' . $cls . ' ') . ")";
                    }
                }
                if ($attr) {
                    $conditions[] = $this->cssAttributeToXPathCondition($attr);
                }

                if (!empty($conditions)) {
                    $xp .= '[' . implode(' and ', $conditions) . ']';
                }
            } else {
                $xp = $part;
            }
            $xpathParts[] = $xp;
        }

        $xpath = '//' . implode('//', $xpathParts);
        // Fix double separators
        $xpath = preg_replace('#/{3,}#', '//', $xpath);
        $xpath = str_replace('///', '//', $xpath);

        return $xpath;
    }

    private function cssAttributeToXPathCondition(string $attr): string
    {
        $attr = trim($attr);
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_:-]*)([*^$~|]?=)?\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]]*))?$/', $attr, $matches)) {
            return '@' . preg_replace('/[^a-zA-Z0-9_:-]/', '', $attr);
        }

        $name = $matches[1];
        $operator = $matches[2] ?? '';
        $value = $matches[3] ?? $matches[4] ?? $matches[5] ?? '';
        $value = trim((string)$value);
        $literal = $this->xpathLiteral($value);

        return match ($operator) {
            '=' => "@{$name}={$literal}",
            '*=' => "contains(@{$name}, {$literal})",
            '^=' => "starts-with(@{$name}, {$literal})",
            '$=' => "substring(@{$name}, string-length(@{$name}) - string-length({$literal}) + 1) = {$literal}",
            '~=' => "contains(concat(' ', normalize-space(@{$name}), ' '), " . $this->xpathLiteral(' ' . $value . ' ') . ")",
            '|=' => "@{$name}={$literal} or starts-with(@{$name}, " . $this->xpathLiteral($value . '-') . ")",
            default => "@{$name}",
        };
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        return "concat('" . implode("', \"'\", '", $parts) . "')";
    }

    private function findPaginationUrl(DOMXPath $xpath, array $queries, string $baseUrl, bool $requireNextSignal): string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $link = $this->findLinkElement($node);
                if (!$link) {
                    continue;
                }

                $href = trim($link->getAttribute('href'));
                if (!$this->isUsableHref($href)) {
                    continue;
                }

                if ($requireNextSignal && !$this->isNextPaginationLink($link, $href)) {
                    continue;
                }

                return $this->resolveUrl($href, $baseUrl);
            }
        }

        return '';
    }

    private function findLinkElement(DOMElement $node): ?DOMElement
    {
        if (strtolower($node->nodeName) === 'a') {
            return $node;
        }

        $links = $node->getElementsByTagName('a');
        if ($links->length === 0) {
            return null;
        }

        $link = $links->item(0);
        return $link instanceof DOMElement ? $link : null;
    }

    private function isUsableHref(string $href): bool
    {
        $href = trim($href);
        $lower = strtolower($href);

        return $href !== ''
            && $href !== '#'
            && !str_starts_with($lower, 'javascript:');
    }

    private function isNextPaginationLink(DOMElement $link, string $href): bool
    {
        $rel = strtolower($link->getAttribute('rel'));
        if (preg_match('/(^|\s)next(\s|$)/', $rel)) {
            return true;
        }

        if (!$this->isPaginationContext($link, $href)) {
            return false;
        }

        $class = strtolower($link->getAttribute('class'));
        $title = strtolower($link->getAttribute('title'));
        $aria = strtolower($link->getAttribute('aria-label'));
        $text = strtolower((string)preg_replace('/\s+/', ' ', trim($link->textContent)));

        return str_contains($class, 'next')
            || str_contains($title, 'next')
            || str_contains($aria, 'next')
            || str_contains($title, 'sonraki')
            || str_contains($aria, 'sonraki')
            || preg_match('/(^|[^a-z])(next|older|sonraki)([^a-z]|$)/', $text) === 1
            || str_contains($text, "\xC2\xBB")
            || str_contains($text, "\xE2\x80\xBA")
            || str_contains($text, "\xE2\x86\x92");
    }

    private function isPaginationContext(DOMElement $link, string $href): bool
    {
        $tokens = ['pagination', 'pager', 'page-numbers', 'page-link', 'page-item', 'nav-links', 'pagenavi'];
        $linkClass = strtolower($link->getAttribute('class'));
        foreach ($tokens as $token) {
            if (str_contains($linkClass, $token)) {
                return true;
            }
        }

        $title = strtolower($link->getAttribute('title'));
        $aria = strtolower($link->getAttribute('aria-label'));
        if (str_contains($title, 'page') || str_contains($aria, 'page')) {
            return true;
        }

        if (preg_match('#(^|/)(page|paged)/\d+/?#i', $href) || preg_match('#[?&](page|paged|pagenum)=\d+#i', $href)) {
            return true;
        }

        $node = $link;
        $depth = 0;
        while ($node instanceof DOMElement && $depth < 8) {
            $tag = strtolower($node->nodeName);
            $class = strtolower($node->getAttribute('class'));
            $nodeAria = strtolower($node->getAttribute('aria-label'));

            if (str_contains($nodeAria, 'page') || str_contains($nodeAria, 'pagination')) {
                return true;
            }

            foreach ($tokens as $token) {
                if (str_contains($class, $token)) {
                    return true;
                }
            }

            $node = $node->parentNode;
            $depth++;
        }

        return false;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        $baseUrl = rtrim($baseUrl, '/');
        if (str_starts_with($url, '/')) {
            $parsed = parse_url($baseUrl);
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $url;
        }
        return $baseUrl . '/' . $url;
    }

    private function extractImageSource(DOMElement $img): string
    {
        if (strtolower($img->nodeName) === 'source') {
            $sourceSrcset = $img->getAttribute('srcset')
                ?: $img->getAttribute('data-srcset')
                ?: $img->getAttribute('data-lazy-srcset');
            if ($sourceSrcset !== '') {
                $candidate = $this->firstSrcsetCandidate($sourceSrcset);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $direct = $img->getAttribute('data-src')
            ?: $img->getAttribute('data-lazy-src')
            ?: $img->getAttribute('data-url')
            ?: $img->getAttribute('data-original')
            ?: $img->getAttribute('data-flickity-lazyload')
            ?: $img->getAttribute('data-lazy')
            ?: $img->getAttribute('data-image')
            ?: $img->getAttribute('data-bg')
            ?: $img->getAttribute('data-background')
            ?: $img->getAttribute('data-bg-src')
            ?: $img->getAttribute('src');

        if ($direct !== '') {
            return $direct;
        }

        $srcset = $img->getAttribute('data-srcset')
            ?: $img->getAttribute('data-lazy-srcset')
            ?: $img->getAttribute('srcset');

        if ($srcset !== '') {
            return $this->firstSrcsetCandidate($srcset);
        }

        $styleImage = $this->extractBackgroundImage($img);
        if ($styleImage !== '') {
            return $styleImage;
        }

        return '';
    }

    private function firstSrcsetCandidate(string $srcset): string
    {
        $bestUrl = '';
        $bestWidth = -1;
        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate);
            $url = trim((string)($parts[0] ?? ''));
            if ($url === '') {
                continue;
            }
            $width = 0;
            foreach (array_slice($parts, 1) as $part) {
                if (preg_match('/^(\d+)w$/', (string)$part, $m)) {
                    $width = (int)$m[1];
                    break;
                }
            }
            if ($bestUrl === '' || $width > $bestWidth) {
                $bestUrl = $url;
                $bestWidth = $width;
            }
        }

        return $bestUrl;
    }

    private function extractBackgroundImage(DOMElement $element): string
    {
        $style = $element->getAttribute('style');
        if ($style !== '' && preg_match('/background(?:-image)?\s*:\s*[^;]*url\((["\']?)(.*?)\1\)/i', $style, $matches)) {
            return trim((string)$matches[2]);
        }

        return '';
    }

    private function extractThumbnailNearNode(DOMNode $node, DOMXPath $xpath): string
    {
        $depth = 0;
        while ($node instanceof DOMElement && $depth < 5) {
            $image = $this->extractThumbnailFromNode($node, $xpath);
            if ($image !== '') {
                return $image;
            }
            $node = $node->parentNode;
            $depth++;
        }

        return '';
    }

    private function findFirstHref(DOMElement $node): string
    {
        $links = $node->getElementsByTagName('a');
        if ($links->length === 0) {
            return '';
        }

        return $links->item(0)->getAttribute('href');
    }

    private function getInnerHtml(DOMDocument $doc, DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }
        return $html;
    }

    private function cleanHtml(string $html): string
    {
        return $this->cleanHtmlWithSettings($html);
    }

    private function cleanHtmlWithSettings(string $html): string
    {
        if (!$this->cleanHtmlEnabled) {
            if ($this->stripScripts) {
                $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html) ?? $html;
            }
            if ($this->stripIframes) {
                $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $html) ?? $html;
            }
            return trim($html);
        }

        $html = $this->sanitizeHtmlAttributes($html);
        if ($this->stripScripts) {
            $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        }
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        if ($this->stripIframes) {
            $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $html);
        }
        $html = preg_replace('/\s+/', ' ', $html);
        return trim(strip_tags($html, '<p><br><strong><em><b><i><a><ul><ol><li><h2><h3><h4><h5><h6><blockquote><pre><code><div><span><table><thead><tbody><tfoot><tr><td><th>'));
    }

    /**
     * Public wrapper — fallback'lerin (örn. DOM içerik çekme) cleanHtmlWithSettings'i
     * engine dışından çağırabilmesi için.
     */
    public function cleanHtmlWithSettingsOverride(string $html): string
    {
        return $this->cleanHtmlWithSettings($html);
    }

    private function sanitizeHtmlAttributes(string $html): string
    {
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
        $html = preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $html) ?? $html;
        $html = preg_replace('/\s+href\s*=\s*(["\'])\s*javascript:[^"\']*\1/is', '', $html) ?? $html;
        $html = preg_replace('/\s+src\s*=\s*(["\'])\s*javascript:[^"\']*\1/is', '', $html) ?? $html;
        return $html;
    }

    /**
     * Extract thumbnail image from a node, prioritizing .post-thumbnail class
     *
     * @param DOMNode $node The DOM node to search in
     * @param DOMXPath $xpath XPath object for querying
     * @return string Image URL or empty string
     */
    private function extractThumbnailFromNode($node, DOMXPath $xpath): string
    {
        if (!$node instanceof DOMElement) {
            return '';
        }

        if (in_array(strtolower($node->nodeName), ['img', 'source'], true)) {
            $imgSrc = $this->extractImageSource($node);
            if ($imgSrc !== '') {
                return $imgSrc;
            }
        }

        $nodeBackground = $this->extractBackgroundImage($node);
        if ($nodeBackground !== '') {
            return $nodeBackground;
        }

        // 1. Önce .post-thumbnail class'ını ara (mods.club için)
        $thumbnailQuery = './/*[contains(@class, "post-thumbnail")]//img | .//*[contains(@class, "post-thumbnail")]//source | .//*[contains(@class, "post-thumbnail")]';
        $thumbnailImgs = $xpath->query($thumbnailQuery, $node);
        if ($thumbnailImgs && $thumbnailImgs->length > 0) {
            foreach ($thumbnailImgs as $img) {
                if (!$img instanceof DOMElement) {
                    continue;
                }
                $imgSrc = $this->extractImageSource($img) ?: $this->extractBackgroundImage($img);
                if ($imgSrc) {
                    return $imgSrc;
                }
            }
        }

        // 2. Thumbnail, featured-image gibi yaygın class'ları ara
        $commonThumbnailClasses = ['thumbnail', 'featured-image', 'featured-img', 'post-image', 'entry-image', 'article-image', 'wp-post-image', 'lazyload'];
        foreach ($commonThumbnailClasses as $className) {
            $classQuery = './/*[contains(@class, "' . $className . '")]//img | .//*[contains(@class, "' . $className . '")]//source | .//*[contains(@class, "' . $className . '")]';
            $classImgs = $xpath->query($classQuery, $node);
            if ($classImgs && $classImgs->length > 0) {
                foreach ($classImgs as $img) {
                    if (!$img instanceof DOMElement) {
                        continue;
                    }
                    $imgClass = $img->getAttribute('class');
                    // Avatar class'ı olan resimleri atla
                    if (str_contains($imgClass, 'rounded-circle') ||
                        str_contains($imgClass, 'border-circle') ||
                        str_contains($imgClass, 'avatar')) {
                        continue;
                    }
                    $imgSrc = $this->extractImageSource($img) ?: $this->extractBackgroundImage($img);
                    if ($imgSrc) {
                        return $imgSrc;
                    }
                }
            }
        }

        // 3. Normal img tag'lerini ara (fallback)
        foreach (['img', 'source'] as $tagName) {
            $imgs = $node->getElementsByTagName($tagName);
            if ($imgs->length === 0) {
                continue;
            }
            foreach ($imgs as $img) {
                $imgClass = $img->getAttribute('class');
                // Avatar class'ı olan resimleri atla
                if (str_contains($imgClass, 'rounded-circle') ||
                    str_contains($imgClass, 'border-circle') ||
                    str_contains($imgClass, 'avatar')) {
                    continue;
                }
                $imgSrc = $this->extractImageSource($img);
                if ($imgSrc) {
                    return $imgSrc;
                }
            }
        }

        $backgroundNodes = $xpath->query('.//*[@style]', $node);
        if ($backgroundNodes) {
            foreach ($backgroundNodes as $backgroundNode) {
                if (!$backgroundNode instanceof DOMElement) {
                    continue;
                }
                $imgSrc = $this->extractBackgroundImage($backgroundNode);
                if ($imgSrc !== '') {
                    return $imgSrc;
                }
            }
        }

        return '';
    }

    private function sanitizeCustomHeaders(string $headers): array
    {
        $blocked = ['cookie', 'authorization', 'host', 'content-length', 'user-agent'];
        $clean = [];
        foreach (explode("\n", $headers) as $line) {
            $line = trim(str_replace(["\r", "\0"], '', (string)$line));
            if ($line === '' || !str_contains($line, ':')) continue;
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $lower = strtolower($name);
            if (!preg_match('/^[A-Za-z0-9-]+$/', $name)) continue;
            if ($value === '' || in_array($lower, $blocked, true)) continue;
            $clean[] = $name . ': ' . $value;
        }
        return $clean;
    }

    private function isValidProxyUrl(string $proxyUrl): bool
    {
        return ScraperUrlGuard::isSafeProxyUrl($proxyUrl);
    }

    private function isAllowedSourceUrl(string $url, string $baseUrl): bool
    {
        $urlHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        $baseHost = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        
        // Eğer baseUrl boş ise, tüm URL'lere izin ver
        if ($baseHost === '') return true;
        
        // Eğer urlHost boş ise, izin verme
        if ($urlHost === '') return false;
        
        // Tam eşleşme
        if ($urlHost === $baseHost) return true;
        
        // Alt domain eşleşmesi (örn: www.mods.club -> mods.club)
        if (str_ends_with($urlHost, '.' . $baseHost)) return true;
        
        // Ters alt domain eşleşmesi (örn: mods.club -> www.mods.club)
        if (str_ends_with($baseHost, '.' . $urlHost)) return true;
        
        // www ön eki farkını tolere et
        $urlWithoutWww = preg_replace('/^www\./i', '', $urlHost);
        $baseWithoutWww = preg_replace('/^www\./i', '', $baseHost);
        if ($urlWithoutWww === $baseWithoutWww) return true;
        
        return false;
    }

    private function splitTextForTranslation(string $text, int $maxLength): array
    {
        $maxLength = max(1, $maxLength);
        $chunks = [];
        $length = mb_strlen($text, 'UTF-8');
        for ($offset = 0; $offset < $length; $offset += $maxLength) {
            $chunks[] = mb_substr($text, $offset, $maxLength, 'UTF-8');
        }
        return $chunks ?: [''];
    }

    private function isValidImagePayload(string $data, string $extension): bool
    {
        if ($data === '' || strlen($data) > $this->maxImageBytes) {
            return false;
        }

        $info = getimagesizefromstring($data);
        if (!is_array($info) || empty($info['mime']) || !str_starts_with((string)$info['mime'], 'image/')) {
            return false;
        }

        $mime = strtolower((string)$info['mime']);
        $mimeExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];

        foreach ($mimeExtensions[$mime] ?? [] as $mimeExtension) {
            if (in_array($mimeExtension, $this->allowedImageExtensions, true)) {
                return true;
            }
        }

        return false;
    }

    private function isSslVerifyEnabled(): bool
    {
        return $this->sslVerify;
    }

    private function applySiteTextReplacements(string $value, array $rules, string $scope): string
    {
        if ($value === '' || empty($rules)) {
            return $value;
        }

        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            $find = (string)($rule['find'] ?? '');
            if ($find === '') continue;
            $ruleScope = (string)($rule['scope'] ?? 'all');
            if ($ruleScope !== 'all' && $ruleScope !== $scope) continue;
            $value = str_replace($find, (string)($rule['replace'] ?? ''), $value);
        }

        return $value;
    }

    private function applySiteCustomization(array $result, array $settings, array $botSettings = []): array
    {
        $removeRules = [];
        foreach (($settings['remove_texts'] ?? []) as $rule) {
            if (!is_array($rule) || trim((string)($rule['text'] ?? '')) === '') continue;
            $removeRules[] = [
                'find' => (string)$rule['text'],
                'replace' => '',
                'scope' => (string)($rule['scope'] ?? 'all'),
            ];
        }

        $result['title'] = $this->applySiteTextReplacements((string)$result['title'], $removeRules, 'title');
        $result['content'] = $this->applySiteTextReplacements((string)$result['content'], $removeRules, 'content');
        $result['download_links'] = $this->applySiteTextReplacements((string)$result['download_links'], $removeRules, 'download_links');

        $result['content'] = $this->removeHtmlByCssSelectors((string)$result['content'], (string)($settings['remove_selectors'] ?? ''));
        $result['content'] = $this->trimContentBetweenMarkers((string)$result['content'], (string)($settings['trim_before_text'] ?? ''), (string)($settings['trim_after_text'] ?? ''));

        $template = trim((string)($settings['title_template'] ?? ''));
        if ($template !== '') {
            $result['title'] = trim(str_replace('{title}', (string)$result['title'], $template));
        }

        $prepend = trim((string)($settings['content_prepend'] ?? ''));
        $append = trim((string)($settings['content_append'] ?? ''));
        if ($prepend !== '') $result['content'] = '<p>' . htmlspecialchars($prepend, ENT_QUOTES, 'UTF-8') . '</p>' . $result['content'];
        if ($append !== '') $result['content'] .= '<p>' . htmlspecialchars($append, ENT_QUOTES, 'UTF-8') . '</p>';

        $result['images'] = $this->filterImages((array)($result['images'] ?? []), $settings);
        $result['download_links'] = $this->customizeDownloadLinks((string)($result['download_links'] ?? ''), $settings);
        $result['auto_tags'] = $this->detectAutoTags((string)$result['title'] . ' ' . strip_tags((string)$result['content']), (array)($settings['auto_tags'] ?? []));
        $result['site_defaults'] = [
            'category_id' => (int)($settings['site_default_category_id'] ?? 0),
            'status' => (string)($settings['site_default_status'] ?? ''),
            'author_id' => (int)($settings['site_default_author_id'] ?? 0),
        ];

        if (trim((string)($result['author_topic'] ?? '')) === '') {
            $result['author_topic'] = $this->detectAuthorFromContent((string)$result['content'], $settings, $botSettings);
            if ((string)$result['author_topic'] !== '') {
                $result['detection_meta']['author_topic'] = true;
            }
        }
        if (trim((string)($result['topic_version'] ?? '')) === '') {
            $result['topic_version'] = $this->detectVersionFromContent((string)$result['content'], $settings, []);
            if ((string)$result['topic_version'] !== '') {
                $result['detection_meta']['topic_version'] = true;
            }
        }

        $result['content'] = $this->stripDetectedAuthorLinesFromContent((string)($result['content'] ?? ''), $settings, $botSettings);
 
        return $result;
    }

    private function getAuthorDetectionLabels(array $settings, array $botSettings): array
    {
        $labelsRaw = (string)($settings['detect_author_labels'] ?? ($botSettings['bot_detect_author_labels'] ?? 'author,authors,credit,credits'));
        $labels = array_values(array_filter(array_map(static function ($label): string {
            $normalized = trim((string)$label);
            if ($normalized === '') {
                return '';
            }

            if (function_exists('mb_strtolower')) {
                return mb_strtolower($normalized, 'UTF-8');
            }

            return strtolower($normalized);
        }, preg_split('/[,\n]+/', $labelsRaw) ?: []), static fn(string $label): bool => $label !== ''));

        return array_values(array_unique($labels));
    }

    private function stripDetectedAuthorLinesFromContent(string $content, array $settings, array $botSettings): string
    {
        $content = trim((string)$content);
        if ($content === '') {
            return '';
        }

        $enabled = $this->resolveBooleanSetting($settings['detect_author_enabled'] ?? null, $botSettings['bot_detect_author_enabled'] ?? '1');
        if (!$enabled) {
            return $content;
        }

        $labels = $this->getAuthorDetectionLabels($settings, $botSettings);
        if (empty($labels)) {
            return $content;
        }

        $escapedLabels = array_map(static fn(string $label): string => preg_quote($label, '/'), $labels);
        $linePattern = '/^\s*(?:[-*]+\s*)?(?:' . implode('|', $escapedLabels) . ')\s*[:\-–—]\s*(.{1,180})\s*$/imu';
        $cleaned = preg_replace('/\x{00a0}/u', ' ', $content) ?? $content;

        if (class_exists('DOMDocument')) {
            $doc = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $wrapped = '<div id="scraper-author-strip-root">' . $cleaned . '</div>';
            $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            if ($loaded) {
                $root = $doc->getElementById('scraper-author-strip-root');
                if ($root instanceof DOMElement) {
                    $xpath = new DOMXPath($doc);
                    $nodes = $xpath->query('.//*[self::p or self::div or self::li or self::span or self::strong or self::b]', $root);
                    if ($nodes instanceof DOMNodeList) {
                        for ($index = $nodes->length - 1; $index >= 0; $index--) {
                            $node = $nodes->item($index);
                            if (!$node instanceof DOMElement || !$node->parentNode) {
                                continue;
                            }

                            foreach (['img', 'iframe', 'video', 'audio', 'table', 'ul', 'ol', 'pre', 'blockquote', 'code'] as $tag) {
                                if ($node->getElementsByTagName($tag)->length > 0) {
                                    continue 2;
                                }
                            }

                            $text = html_entity_decode((string)($node->textContent ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $text = preg_replace('/\x{00a0}/u', ' ', $text) ?? $text;
                            $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
                            $text = trim($text);
                            if ($text !== '' && preg_match($linePattern, $text) === 1) {
                                $node->parentNode->removeChild($node);
                            }
                        }
                    }

                    $rebuilt = '';
                    foreach ($root->childNodes as $child) {
                        $rebuilt .= $doc->saveHTML($child);
                    }
                    $cleaned = $rebuilt;
                }
            }
        }

        $tagPattern = '(?:p|div|li|span|strong|b)';
        $labelPattern = '(?:' . implode('|', $escapedLabels) . ')';
        $cleaned = preg_replace(
            '~<(' . $tagPattern . ')(?:\s[^>]*)?>\s*(?:[-*]+\s*)?' . $labelPattern . '\s*[:\-–—]\s*[^<]{1,180}\s*</\1>~iu',
            '',
            $cleaned
        ) ?? $cleaned;
        $cleaned = preg_replace('~(?:<br\s*/?>\s*){2,}~i', '<br>', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function detectAuthorFromContent(string $content, array $settings, array $botSettings): string
    {
        $enabled = $this->resolveBooleanSetting($settings['detect_author_enabled'] ?? null, $botSettings['bot_detect_author_enabled'] ?? '1');
        if (!$enabled) {
            return '';
        }

        $labels = $this->getAuthorDetectionLabels($settings, $botSettings);
        if (empty($labels)) {
            return '';
        }

        $text = $this->normalizeDetectionText($content);
        if ($text === '') {
            return '';
        }

        $escapedLabels = array_map(static fn(string $label): string => preg_quote($label, '/'), $labels);
        $pattern = '/^\s*(?:[-*]+\s*)?(?:' . implode('|', $escapedLabels) . ')\s*[:\-–—]\s*(.{2,160})\s*$/imu';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $candidate = $this->sanitizeDetectedAuthor($match[1] ?? '');
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function detectVersionFromContent(string $content, array $settings, array $botSettings): string
    {
        $enabled = $this->resolveBooleanSetting($settings['detect_version_enabled'] ?? null, $botSettings['bot_detect_version_enabled'] ?? '1');
        if (!$enabled) {
            return '';
        }

        $pattern = trim((string)($settings['detect_version_pattern'] ?? ($botSettings['bot_detect_version_pattern'] ?? '1\\.(?:[3-9]\\d|[1-9]\\d{2,})')));
        if ($pattern === '') {
            $pattern = '1\\.(?:[3-9]\\d|[1-9]\\d{2,})';
        }

        $text = $this->normalizeDetectionText($content);
        if ($text === '') {
            return '';
        }

        $regex = '/' . str_replace('/', '\\/', $pattern) . '/u';
        if (preg_match_all($regex, $text, $matches) === false || empty($matches[0])) {
            return '';
        }

        $versions = [];
        foreach ($matches[0] as $match) {
            $candidate = trim((string)$match, " \t\n\r\0\x0B.,;:!?)(']\"");
            if ($candidate !== '') {
                $versions[] = $candidate;
            }
        }

        $versions = array_values(array_unique($versions));
        return implode(', ', $versions);
    }

    private function normalizeDetectionText(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n?|\n/u", "\n", $text) ?? $text;
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;
        return trim($text);
    }

    private function sanitizeDetectedAuthor(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*(?:\||\/|\\\\|,)?\s*(?:version|required version|game version)\b.*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*(?:\.|;|,|\||\/)+\s*$/u', '', $value) ?? $value;
        if ($value === '' || mb_strlen($value) < 2) {
            return '';
        }
        if (preg_match('/^(?:n\/a|none|unknown)$/iu', $value)) {
            return '';
        }
        return mb_substr($value, 0, 150);
    }

    private function resolveBooleanSetting(mixed $value, string $default): bool
    {
        if ($value === null || $value === '') {
            $value = $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
 
    private function removeHtmlByCssSelectors(string $html, string $selectors): string
    {
        $selectors = trim($selectors);
        if ($html === '' || $selectors === '') return $html;

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $html = '<meta charset="UTF-8"><div id="scraper-root">' . $this->prepareHtmlForDom($html, false) . '</div>';
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        foreach (array_filter(array_map('trim', explode(',', $selectors))) as $selector) {
            $nodes = $xpath->query($this->cssToXPath($selector));
            if (!$nodes) continue;
            foreach (iterator_to_array($nodes) as $node) {
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }
        }
        $root = $doc->getElementById('scraper-root');
        $clean = $root ? $this->getInnerHtml($doc, $root) : $html;
        libxml_clear_errors();
        return trim($clean);
    }

    private function trimContentBetweenMarkers(string $html, string $before, string $after): string
    {
        if ($before !== '') {
            $pos = mb_strpos($html, $before);
            if ($pos !== false) $html = mb_substr($html, $pos + mb_strlen($before));
        }
        if ($after !== '') {
            $pos = mb_strpos($html, $after);
            if ($pos !== false) $html = mb_substr($html, 0, $pos);
        }
        return trim($html);
    }

    private function filterImages(array $images, array $settings): array
    {
        $skipParts = array_filter(array_map('trim', explode(',', (string)($settings['skip_image_contains'] ?? ''))));
        $allowedDomains = array_filter(array_map('trim', explode(',', (string)($settings['allowed_image_domains'] ?? ''))));
        $minWidth = max(0, (int)($settings['min_image_width'] ?? 0));
        return array_values(array_filter($images, static function ($url) use ($skipParts, $allowedDomains, $minWidth) {
            $url = (string)$url;
            foreach ($skipParts as $part) {
                if ($part !== '' && stripos($url, $part) !== false) return false;
            }
            if ($allowedDomains) {
                $host = parse_url($url, PHP_URL_HOST) ?: '';
                $allowed = false;
                foreach ($allowedDomains as $domain) {
                    if ($domain !== '' && (strcasecmp($host, $domain) === 0 || str_ends_with($host, '.' . $domain))) $allowed = true;
                }
                if (!$allowed) return false;
            }
            if ($minWidth > 0 && preg_match('/(?:^|[^\d])(\d{2,5})x(\d{2,5})(?:[^\d]|$)/', $url, $m) && (int)$m[1] < $minWidth) {
                return false;
            }
            return true;
        }));
    }

    private function customizeDownloadLinks(string $links, array $settings): string
    {
        $skipDomains = array_filter(array_map('trim', explode(',', (string)($settings['skip_download_domains'] ?? ''))));
        $rules = is_array($settings['download_link_replacements'] ?? null) ? $settings['download_link_replacements'] : [];
        $lines = [];
        foreach (array_filter(explode("\n", $links)) as $line) {
            [$name, $url] = array_pad(explode('|', $line, 2), 2, '');
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            foreach ($skipDomains as $domain) {
                if ($domain !== '' && (strcasecmp($host, $domain) === 0 || str_ends_with($host, '.' . $domain))) continue 2;
            }
            foreach ($rules as $rule) {
                if (is_array($rule) && ($rule['find'] ?? '') !== '') $name = str_replace((string)$rule['find'], (string)($rule['replace'] ?? ''), $name);
            }
            $name = $this->normalizeDownloadLinkName((string)$name, (string)$url);
            $lines[] = trim($name ?: 'Link') . '|' . trim($url);
        }
        return implode("\n", $lines);
    }

    private function normalizeDownloadLinkName(string $name, string $url): string
    {
        $name = trim($name);
        if (!preg_match('/^(?:İndir|indir|Indir|Download|download)\s+from\s+(.+)$/u', $name, $matches)) {
            return $name ?: 'Link';
        }

        $domain = $this->extractDomainName((string)$matches[1]);
        if ($domain === '') {
            $domain = $this->extractDomainName($url);
        }
        if ($domain === '') {
            return $name ?: 'Link';
        }

        return $domain . ' üzerinden indir';
    }

    private function extractDomainName(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') return '';

        $firstToken = preg_split('/\s+/', $value, 2)[0] ?? '';
        $firstToken = trim($firstToken, " \t\n\r\0\x0B()[]{}<>.,;:!\"'");
        if ($firstToken === '') return '';

        $candidate = preg_match('~^[a-z][a-z0-9+.-]*://~i', $firstToken) ? $firstToken : 'https://' . $firstToken;
        $host = parse_url($candidate, PHP_URL_HOST) ?: $firstToken;
        $host = preg_replace('/^www\./i', '', trim((string)$host));
        $host = strtolower($host);

        return preg_match('/^[a-z0-9.-]+\.[a-z0-9-]{2,}$/i', $host) ? $host : '';
    }

    private function translateDownloadLinkNames(string $links, string $sourceLang, string $targetLang, bool $fallbackOriginal): string
    {
        $translatedLines = [];
        foreach (array_filter(explode("\n", $links)) as $line) {
            [$name, $url] = array_pad(explode('|', $line, 2), 2, '');
            $translatedName = $this->translateText($name, $sourceLang, $targetLang);
            $translatedLines[] = (($translatedName !== null && $translatedName !== '') ? $translatedName : ($fallbackOriginal ? $name : 'Link')) . '|' . $url;
        }
        return implode("\n", $translatedLines);
    }

    private function detectAutoTags(string $text, array $rules): array
    {
        $tags = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            $keyword = trim((string)($rule['keyword'] ?? ''));
            $tag = trim((string)($rule['tag'] ?? ''));
            if ($keyword !== '' && $tag !== '' && stripos($text, $keyword) !== false) $tags[] = $tag;
        }
        return array_values(array_unique($tags));
    }

    private function applyContentAlignment(string $html, string $align): string
    {
        $html = trim($html);
        $align = strtolower(trim($align));
        if ($html === '' || !in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            return $html;
        }

        if ($align === 'left') {
            return $html;
        }

        if (
            preg_match('/text-align\s*:\s*' . preg_quote($align, '/') . '\b/i', $html)
            || preg_match('/\b(?:content-align|ql-align)-' . preg_quote($align, '/') . '\b/i', $html)
        ) {
            return $html;
        }

        return '<div class="content-align-' . $align . '">' . $html . '</div>';
    }
}

