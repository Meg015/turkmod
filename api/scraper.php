<?php

declare(strict_types=1);

use App\Engine\Scraper\Support\ScraperEngine;

/**
 * Scraper Bot AJAX API Endpoint
 * Handles all async operations for the content scraper module.
 */

require_once __DIR__ . '/../admin/init.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Support/ScraperEngine.php';


function scraperApiRespond(array $payload, ?int $statusCode = null): void
{
    $statusCode ??= http_response_code();
    if ($statusCode < 100) {
        $statusCode = 200;
    }

    $success = (bool) ($payload['success'] ?? ($statusCode >= 200 && $statusCode < 400));
    $message = (string) ($payload['message'] ?? ($payload['error'] ?? ($success ? 'OK' : 'İşlem tamamlanamadı.')));
    $errorCode = $success ? null : (string) ($payload['code'] ?? 'scraper_error');

    sendJsonResponse($statusCode, $success, $message, $payload, $errorCode);
}

function scraperCacheDirectory(string $bucket): string
{
    $safeBucket = preg_replace('/[^a-z0-9_-]/i', '', $bucket) ?: 'scraper';
    return dirname(__DIR__) . '/storage/cache/' . $safeBucket;
}

function scraperReadJsonCache(string $bucket, string $key, int $ttlSeconds): ?array
{
    $ttlSeconds = max(1, $ttlSeconds);
    $file = scraperCacheDirectory($bucket) . '/' . hash('crc32b', $key) . '.json';
    if (!is_file($file) || (time() - filemtime($file)) > $ttlSeconds) {
        return null;
    }

    $payload = json_decode((string) file_get_contents($file), true);
    return is_array($payload) ? $payload : null;
}

function scraperWriteJsonCache(string $bucket, string $key, array $payload): void
{
    $directory = scraperCacheDirectory($bucket);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    if (!is_dir($directory) || !is_writable($directory)) {
        return;
    }

    file_put_contents(
        $directory . '/' . hash('sha256', $key) . '.json',
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        LOCK_EX
    );
}

function scraperPreviewBotSettings(array $botSettings): array
{
    $timeout = (int) ($botSettings['bot_request_timeout'] ?? 30);
    $timeout = max(5, min(15, $timeout > 0 ? $timeout : 15));

    return array_merge($botSettings, [
        'bot_request_delay' => '0',
        'bot_request_timeout' => (string) $timeout,
        'bot_retry_count' => '0',
        'bot_retry_delay' => '0',
        'bot_download_images' => '0',
        'bot_use_hotlink_images' => '1',
        'bot_require_cover_image' => '0',
        'bot_min_title_length' => '0',
        'bot_min_content_length' => '0',
    ]);
}

function scraperDiscoverBotSettings(array $botSettings): array
{
    $timeout = (int) ($botSettings['bot_request_timeout'] ?? 30);
    $timeout = max(4, min(8, $timeout > 0 ? $timeout : 8));

    return array_merge($botSettings, [
        'bot_request_delay' => '0',
        'bot_request_timeout' => (string) $timeout,
        'bot_retry_count' => '0',
        'bot_retry_delay' => '0',
        'bot_download_images' => '0',
        'bot_use_hotlink_images' => '1',
        'bot_translate_enabled' => '0',
        'bot_translate_title' => '0',
        'bot_translate_content' => '0',
        'bot_translate_download_names' => '0',
        'bot_deepl_api_key' => '',
    ]);
}

function scraperPreviewSiteConfig(array $siteConfig): array
{
    $siteConfig['settings'] = is_array($siteConfig['settings'] ?? null) ? $siteConfig['settings'] : [];
    return $siteConfig;
}

function scraperResolveMappingSite(PDO $pdo, int $mappingId): ?array
{
    if ($mappingId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT bot_site_id, remote_category_url FROM bot_category_mappings WHERE id = ? LIMIT 1');
    $stmt->execute([$mappingId]);
    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($mapping) ? $mapping : null;
}

function scraperResolveMappingSiteByUrl(PDO $pdo, string $categoryUrl): ?array
{
    $categoryUrl = trim($categoryUrl);
    if ($categoryUrl === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT bot_site_id, remote_category_url FROM bot_category_mappings WHERE remote_category_url = ? LIMIT 1');
    $stmt->execute([$categoryUrl]);
    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($mapping) ? $mapping : null;
}

function scraperResolveMappingTitlePrefix(PDO $pdo, int $mappingId, int $siteId = 0): string
{
    if ($mappingId <= 0) {
        return '';
    }

    try {
        $sql = 'SELECT title_prefix FROM bot_category_mappings WHERE id = ?';
        $params = [$mappingId];
        if ($siteId > 0) {
            $sql .= ' AND bot_site_id = ?';
            $params[] = $siteId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        throw new RuntimeException('Scraper mapping title prefix lookup failed.', 0, $e);
    }
}

function scraperApplyTitlePrefix(string $title, string $prefix): string
{
    $title = trim($title);
    $prefix = trim($prefix);
    if ($title === '' || $prefix === '') {
        return $title;
    }

    $titleStart = function_exists('mb_substr') ? mb_substr($title, 0, mb_strlen($prefix, 'UTF-8'), 'UTF-8') : substr($title, 0, strlen($prefix));
    $normalizedTitleStart = function_exists('mb_strtolower') ? mb_strtolower($titleStart, 'UTF-8') : strtolower($titleStart);
    $normalizedPrefix = function_exists('mb_strtolower') ? mb_strtolower($prefix, 'UTF-8') : strtolower($prefix);
    if ($normalizedTitleStart === $normalizedPrefix) {
        return $title;
    }

    return trim($prefix . ' ' . $title);
}

function scraperApplyTitlePrefixToResult(array $result, string $prefix): array
{
    $prefix = trim($prefix);
    if ($prefix === '') {
        return $result;
    }

    $result['title'] = scraperApplyTitlePrefix((string)($result['title'] ?? ''), $prefix);
    if (trim((string)($result['translated_title'] ?? '')) !== '') {
        $result['translated_title'] = scraperApplyTitlePrefix((string)$result['translated_title'], $prefix);
    }
    if (isset($result['site_defaults']) && is_array($result['site_defaults'])) {
        $result['site_defaults']['title_prefix'] = $prefix;
    }

    return $result;
}


$pdo = requireDatabaseConnection($pdo ?? null);

if (!$pdo) {
    http_response_code(500);
    scraperApiRespond(['success' => false, 'error' => 'Veritabanı bağlantısı yok.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Rate limiting protection
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$isPrivilegedScraperUser = $userId > 0
    && function_exists('userHasPermission')
    && (userHasPermission($pdo, $userId, 'admin.access') || userHasPermission($pdo, $userId, 'scraper.manage'));
if ($userId > 0 && scraperShouldRateLimitAction((string)$action, $isPrivilegedScraperUser)) {
    $cacheKey = "scraper_rate_{$userId}";
    $count = scraperRateLimitFetch($cacheKey);

    if ($count === false) {
        $count = 0;
    }

    if ($count > 20) { // 20 işlem/saat
        http_response_code(429);
        scraperApiRespond(['success' => false, 'error' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.']);
        exit;
    }

    scraperRateLimitStore($cacheKey, $count + 1, 3600);
}

// Action whitelist validation
$allowedActions = [
    'save_site', 'delete_site', 'get_site',
    'save_mapping', 'get_mapping', 'delete_mapping',
    'discover_urls', 'scrape_single', 'preview_url', 'scrape_batch',
    'publish_import', 'save_and_publish_import', 'delete_import', 'get_import',
    'save_bot_settings', 'get_stats', 'test_connection', 'test_translation',
];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    scraperApiRespond(['success' => false, 'error' => 'Geçersiz action.']);
    exit;
}

// Bireysel/Grup bot yetkisi doğrulaması
$readActions = ['get_site', 'get_mapping', 'get_stats', 'discover_urls', 'preview_url', 'get_import'];
$isWriteAction = !in_array($action, $readActions, true);

if ($isWriteAction) {
    if (!userHasPermission($pdo, $userId, 'scraper.manage')) {
        http_response_code(403);
        scraperApiRespond(['success' => false, 'error' => 'Bu işlem için bot yönetici izni (scraper.manage) gereklidir.']);
        exit;
    }
} else {
    if (!userHasPermission($pdo, $userId, 'scraper.view')) {
        http_response_code(403);
        scraperApiRespond(['success' => false, 'error' => 'Bu işlem için bot görüntüleme izni (scraper.view) gereklidir.']);
        exit;
    }
}

$postOnlyActions = [
    'save_site', 'delete_site',
    'save_mapping', 'delete_mapping',
    'discover_urls', 'scrape_single', 'preview_url', 'scrape_batch',
    'publish_import', 'save_and_publish_import', 'delete_import',
    'save_bot_settings', 'test_connection', 'test_translation',
];

if (in_array($action, $postOnlyActions, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    scraperApiRespond(['success' => false, 'error' => 'Bu işlem sadece POST isteğiyle yapılabilir.']);
    exit;
}

$schemaSkipActions = ['discover_urls', 'test_connection', 'test_translation'];

function scraperImportDataFromResult(int $siteId, string $url, array $result, ?int $jobId = null, string $status = 'preview'): array
{
    return [
        'bot_job_id' => $jobId,
        'bot_site_id' => $siteId,
        'source_url' => $url,
        'source_title' => $result['title'] ?? '',
        'translated_title' => $result['translated_title'] ?: null,
        'author_topic' => $result['author_topic'] ?? null,
        'topic_version' => $result['topic_version'] ?? null,
        'source_content' => $result['content'] ?? '',
        'translated_content' => $result['translated_content'] ?: null,
        'source_images' => implode("\n", $result['images'] ?? []),
        'downloaded_images' => implode("\n", $result['downloaded_images'] ?? []),
        'source_download_links' => $result['download_links'] ?? '',
        'status' => $status,
        'images_count' => $result['images_count'] ?? 0,
        'error_message' => null,
    ];
}

function scraperDraftSourceUrl(string $url): string
{
    $separator = str_contains($url, '#') ? '&' : '#';
    return $url . $separator . 'draft-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
}

function scraperResolvePublishDefaults(PDO $pdo, array $site, ?int $mappingId, array $botSettings): array
{
    $categoryId = 0;
    if ($mappingId) {
        try {
            $stmt = $pdo->prepare("SELECT local_category_id FROM bot_category_mappings WHERE id = ? LIMIT 1");
            $stmt->execute([$mappingId]);
            $categoryId = (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    $siteSettings = json_decode($site['settings'] ?? '{}', true) ?: [];
    if (!$categoryId) {
        $categoryId = (int)($siteSettings['site_default_category_id'] ?? 0);
    }

    $status = (string)($siteSettings['site_default_status'] ?? '');
    if (!in_array($status, ['published', 'draft'], true)) {
        $status = in_array($botSettings['bot_default_status'] ?? '', ['published', 'draft'], true)
            ? (string)$botSettings['bot_default_status']
            : 'draft';
    }

    return ['category_id' => $categoryId, 'status' => $status];
}

// CSRF check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''));
    if (!verify_csrf_token((string) $csrfToken)) {
        http_response_code(403);
        scraperApiRespond(['success' => false, 'error' => 'Geçersiz güvenlik tokeni.']);
        exit;
    }
}

if (!in_array($action, $schemaSkipActions, true)) {
    ensureScraperSchema($pdo);
}

try {
    switch ($action) {

        // --- Site CRUD ---
        case 'save_site':
            $id = (int)($_POST['site_id'] ?? 0) ?: null;
            $siteId = saveScraperSite($pdo, $_POST, $id);
            scraperApiRespond(['success' => true, 'site_id' => $siteId, 'message' => $id ? 'Site güncellendi.' : 'Site eklendi.']);
            break;

        case 'delete_site':
            $id = (int)($_POST['id'] ?? 0);
            deleteScraperSite($pdo, $id);
            scraperApiRespond(['success' => true, 'message' => 'Site silindi.']);
            break;

        case 'get_site':
            $id = (int)($_GET['id'] ?? 0);
            $site = getScraperSite($pdo, $id);
            if ($site) {
                $site['selectors'] = json_decode($site['selectors'] ?? '{}', true) ?: [];
                $site['settings'] = json_decode($site['settings'] ?? '{}', true) ?: [];
            }
            scraperApiRespond(['success' => (bool)$site, 'site' => $site]);
            break;

        // --- Category Mapping CRUD ---
        case 'save_mapping':
            $data = $_POST;
            if (empty($data['remote_category_name']) && !empty($data['remote_category_url'])) {
                $parts = explode('/', trim((string)parse_url($data['remote_category_url'], PHP_URL_PATH), '/'));
                $data['remote_category_name'] = ucfirst(end($parts)) ?: 'Kategori';
            }
            $id = (int)($data['mapping_id'] ?? 0) ?: null;
            $mappingId = saveScraperMapping($pdo, $data, $id);
            scraperApiRespond(['success' => true, 'mapping_id' => $mappingId, 'message' => 'Eşleme kaydedildi.']);
            break;

        case 'get_mapping':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM bot_category_mappings WHERE id = ?");
            $stmt->execute([$id]);
            $mapping = $stmt->fetch();
            scraperApiRespond(['success' => (bool)$mapping, 'mapping' => $mapping]);
            break;

        case 'delete_mapping':
            $id = (int)($_POST['id'] ?? 0);
            deleteScraperMapping($pdo, $id);
            scraperApiRespond(['success' => true, 'message' => 'Eşleme silindi.']);
            break;

        // -- Scrape Operations ---------------------------------
        case 'discover_urls':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $mappingId = (int)($_POST['mapping_id'] ?? 0);
            $categoryUrl = trim($_POST['category_url'] ?? '');

            // Tek seferlik mapping çözümleme - önce siteId/categoryUrl eksikse mapping'den doldur
            if ((!$siteId || !$categoryUrl) && $mappingId > 0) {
                $mapping = scraperResolveMappingSite($pdo, $mappingId);
                if ($mapping) {
                    $siteId = $siteId ?: (int)($mapping['bot_site_id'] ?? 0);
                    $categoryUrl = $categoryUrl ?: trim((string)($mapping['remote_category_url'] ?? ''));
                }
            }
            if (!$siteId || !$categoryUrl) {
                scraperApiRespond(['success' => false, 'error' => 'Site ve kategori URL gerekli.']);
                break;
            }
            // Site bulunamazsa URL üzerinden dene
            $site = getScraperSite($pdo, $siteId);
            if (!$site) {
                $mapping = scraperResolveMappingSiteByUrl($pdo, $categoryUrl);
                if ($mapping) {
                    $siteId = (int)($mapping['bot_site_id'] ?? 0);
                    $site = getScraperSite($pdo, $siteId);
                }
            }
            if (!$site) {
                scraperApiRespond(['success' => false, 'error' => 'Site bulunamadı.']);
                break;
            }
            $selectors = json_decode($site['selectors'] ?? '{}', true) ?: [];
            $botSettings = getScraperBotSettings($pdo);
            $requestedCoverLookupLimit = array_key_exists('cover_lookup_limit', $_POST)
                ? max(0, min(50, (int)($_POST['cover_lookup_limit'] ?? 0)))
                : null;
            $coverLookupLimit = $requestedCoverLookupLimit
                ?? max(0, min(50, (int)($botSettings['bot_discover_cover_lookup_limit'] ?? 2)));
            $maxTopics = max(0, (int)($botSettings['bot_bulk_max_topics_per_page'] ?? 0));
            $discoverCacheKey = implode('|', [
                'v3',
                (string)$siteId,
                $categoryUrl,
                (string)$coverLookupLimit,
                (string)$maxTopics,
                hash('crc32b', json_encode($selectors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
                (string)($site['updated_at'] ?? ''),
            ]);
            $cachedDiscover = scraperReadJsonCache('scraper-discover', $discoverCacheKey, 900);
            if (is_array($cachedDiscover) && isset($cachedDiscover['urls'])) {
                $cachedUrls = markScraperImportedTopics($pdo, $siteId, (array)$cachedDiscover['urls']);
                scraperApiRespond([
                    'success' => true,
                    'urls' => $cachedUrls,
                    'count' => count($cachedUrls),
                    'current_url' => (string)($cachedDiscover['current_url'] ?? $categoryUrl),
                    'next_url' => (string)($cachedDiscover['next_url'] ?? ''),
                    'cover_lookup_limit' => (int)($cachedDiscover['cover_lookup_limit'] ?? $coverLookupLimit),
                    'cached' => true,
                ]);
                break;
            }
            $discoverBotSettings = scraperDiscoverBotSettings($botSettings);
            $engine = new ScraperEngine($discoverBotSettings);
            $html = $engine->fetchPage($categoryUrl);
            if (!$html) {
                scraperApiRespond(['success' => false, 'error' => 'Sayfa indirilemedi.']);
                break;
            }
            // Kombine metot: HTML'i tek seferde parse eder - discoverTopicUrls + discoverPaginationUrl
            $discovered = $engine->discoverTopicUrlsWithPagination($html, $selectors, $categoryUrl);
            $urls = $discovered['items'];
            $nextUrl = $discovered['nextUrl'];
            if ($maxTopics > 0) {
                $urls = array_slice($urls, 0, $maxTopics);
            }
            
            // Resim yoksa detay sayfasından ilk resmi çek (tüm konular için)
            $coverLookups = 0;
            foreach ($urls as &$topic) {
                if ($coverLookups >= $coverLookupLimit) {
                    break;
                }
                if (empty($topic['image'])) {
                    $coverLookups++;
                    $detailHtml = $engine->fetchPage($topic['url']);
                    if ($detailHtml) {
                        $parsed = $engine->parseTopicPage($detailHtml, $selectors, $site['base_url']);
                        if (!empty($parsed['images'])) {
                            $topic['image'] = $parsed['images'][0];
                        }
                    }
                }
            }
            unset($topic);
            scraperWriteJsonCache('scraper-discover', $discoverCacheKey, [
                'urls' => $urls,
                'current_url' => $categoryUrl,
                'next_url' => $nextUrl,
                'cover_lookup_limit' => $coverLookupLimit,
            ]);
            $urls = markScraperImportedTopics($pdo, $siteId, $urls);
            scraperApiRespond([
                'success' => true,
                'urls' => $urls,
                'count' => count($urls),
                'current_url' => $categoryUrl,
                'next_url' => $nextUrl,
                'cover_lookup_limit' => $coverLookupLimit,
                'cached' => false,
            ]);
            break;

        case 'scrape_single':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $mappingId = (int)($_POST['mapping_id'] ?? 0);
            $url = trim($_POST['url'] ?? '');
            if (!$siteId || !$url) {
                scraperApiRespond(['success' => false, 'error' => 'Site ve URL gerekli.']);
                break;
            }
            $site = getScraperSite($pdo, $siteId);
            if (!$site) {
                scraperApiRespond(['success' => false, 'error' => 'Site bulunamadı.']);
                break;
            }
            $siteConfig = [
                'selectors' => json_decode($site['selectors'] ?? '{}', true) ?: [],
                'settings' => json_decode($site['settings'] ?? '{}', true) ?: [],
                'base_url' => $site['base_url'],
                'slug' => $site['slug'],
            ];
            $botSettings = getScraperBotSettings($pdo);
            $engine = new ScraperEngine($botSettings);
            $existingImport = getScraperImportBySource($pdo, $siteId, $url);
            $duplicateImport = getScraperDuplicateImportBySource($pdo, $siteId, $url);
            $duplicate = resolveScraperDuplicateImportAction($duplicateImport, (string)($botSettings['bot_duplicate_strategy'] ?? 'skip'));
            if (!$duplicate['should_scrape']) {
                scraperApiRespond([
                    'success' => true,
                    'import_id' => $duplicate['import_id'],
                    'duplicate' => true,
                    'skipped' => true,
                    'warning' => $duplicate['warning'],
                    'message' => $duplicate['warning'],
                    'data' => ['site_defaults' => $duplicateImport ? getScraperImportSiteDefaults($pdo, $duplicateImport) : []],
                ]);
                break;
            }
            $result = $engine->scrapeUrl($url, $siteConfig, $botSettings);

            if ($result['success']) {
                $result = scraperApplyTitlePrefixToResult($result, scraperResolveMappingTitlePrefix($pdo, $mappingId, $siteId));
                $importUrl = $duplicate['action'] === 'draft' ? scraperDraftSourceUrl($url) : $url;
                $importData = scraperImportDataFromResult($siteId, $importUrl, $result);
                if ($duplicate['action'] === 'update' && !empty($duplicate['import_id'])) {
                    updateScraperImport($pdo, (int)$duplicate['import_id'], $importData);
                    $importId = (int)$duplicate['import_id'];
                } elseif ($duplicate['action'] === 'create' && $existingImport) {
                    updateScraperImport($pdo, (int)$existingImport['id'], $importData);
                    $importId = (int)$existingImport['id'];
                } else {
                    $importId = createScraperImport($pdo, $importData);
                }
                $topicUrl = null;
                if (scraperBoolSetting($botSettings, 'bot_auto_publish', '0')) {
                    $publishDefaults = scraperResolvePublishDefaults($pdo, $site, $mappingId > 0 ? $mappingId : null, $botSettings);
                    if ($publishDefaults['category_id'] > 0) {
                        $publishedSlug = publishScraperImport($pdo, $importId, (int)$publishDefaults['category_id'], (string)$publishDefaults['status']);
                        $topicUrl = $publishedSlug ? topicUrlBySlug($pdo, $publishedSlug) : null;
                    }
                }
                // Frontend'in beklediği alan adlarıyla eşleştir
                $scrapeData = [
                    'title' => $result['title'] ?? '',
                    'source_title' => $result['title'] ?? '',
                    'translated_title' => $result['translated_title'] ?? '',
                    'author_topic' => $result['author_topic'] ?? '',
                    'topic_version' => $result['topic_version'] ?? '',
                    'content' => $result['content'] ?? '',
                    'source_content' => $result['content'] ?? '',
                    'translated_content' => $result['translated_content'] ?? '',
                    'images' => $result['images'] ?? [],
                    'source_images' => $result['images'] ?? [],
                    'downloaded_images' => $result['downloaded_images'] ?? [],
                    'download_links' => $result['download_links'] ?? '',
                    'source_download_links' => $result['download_links'] ?? '',
                    'images_count' => $result['images_count'] ?? 0,
                    'detection_meta' => $result['detection_meta'] ?? ['author_topic' => false, 'topic_version' => false],
                    'translation_errors' => $result['translation_errors'] ?? [],
                    'site_defaults' => $result['site_defaults'] ?? [],
                ];
                scraperApiRespond(['success' => true, 'import_id' => $importId, 'duplicate' => $duplicate['action'] !== 'create', 'warning' => $duplicate['warning'], 'topic_url' => $topicUrl, 'translation_errors' => $scrapeData['translation_errors'], 'data' => $scrapeData]);
            } else {
                scraperApiRespond(['success' => false, 'error' => $result['error']]);
            }
            break;

        case 'preview_url':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $mappingId = (int)($_POST['mapping_id'] ?? 0);
            $url = trim($_POST['url'] ?? '');
            if (!$siteId || !$url) {
                scraperApiRespond(['success' => false, 'error' => 'Site ve URL gerekli.']);
                break;
            }
            $site = getScraperSite($pdo, $siteId);
            if (!$site) {
                scraperApiRespond(['success' => false, 'error' => 'Site bulunamadı.']);
                break;
            }
            
            $siteConfig = [
                'selectors' => json_decode($site['selectors'] ?? '{}', true) ?: [],
                'settings' => json_decode($site['settings'] ?? '{}', true) ?: [],
                'base_url' => $site['base_url'],
                'slug' => $site['slug'],
            ];
            $botSettings = getScraperBotSettings($pdo);
            $previewBotSettings = scraperPreviewBotSettings($botSettings);
            $previewSiteConfig = scraperPreviewSiteConfig($siteConfig);
            $engine = new ScraperEngine($previewBotSettings);
            $result = $engine->scrapeUrl($url, $previewSiteConfig, $previewBotSettings);
            $duplicateImport = getScraperDuplicateImportBySource($pdo, $siteId, $url);
            $duplicate = resolveScraperDuplicateImportAction($duplicateImport, (string)($botSettings['bot_duplicate_strategy'] ?? 'skip'));

            if ($result['success']) {
                $result = scraperApplyTitlePrefixToResult($result, scraperResolveMappingTitlePrefix($pdo, $mappingId, $siteId));
                // Frontend'in beklediği alan adlarıyla eşleştir
                $previewData = [
                    'site_id' => $siteId,
                    'source_url' => $url,
                    'title' => $result['title'] ?? '',
                    'source_title' => $result['title'] ?? '',
                    'translated_title' => $result['translated_title'] ?? '',
                    'author_topic' => $result['author_topic'] ?? '',
                    'topic_version' => $result['topic_version'] ?? '',
                    'content' => $result['content'] ?? '',
                    'source_content' => $result['content'] ?? '',
                    'translated_content' => $result['translated_content'] ?? '',
                    'images' => $result['images'] ?? [],
                    'source_images' => $result['images'] ?? [],
                    'downloaded_images' => $result['downloaded_images'] ?? [],
                    'download_links' => $result['download_links'] ?? '',
                    'source_download_links' => $result['download_links'] ?? '',
                    'images_count' => $result['images_count'] ?? 0,
                    'detection_meta' => $result['detection_meta'] ?? ['author_topic' => false, 'topic_version' => false],
                    'translation_errors' => $result['translation_errors'] ?? [],
                    'site_defaults' => $result['site_defaults'] ?? [],
                ];
                scraperApiRespond(['success' => true, 'duplicate' => $duplicate['action'] !== 'create', 'warning' => $duplicate['warning'], 'translation_errors' => $previewData['translation_errors'], 'data' => $previewData]);
            } else {
                scraperApiRespond(['success' => false, 'error' => $result['error'] ?: 'Onizleme alinamadi.']);
            }
            break;

        case 'scrape_batch':
            $siteId = (int)($_POST['site_id'] ?? 0);
            $mappingId = (int)($_POST['mapping_id'] ?? 0) ?: null;
            $urls = json_decode($_POST['urls'] ?? '[]', true) ?: [];
            if (!$siteId || empty($urls)) {
                scraperApiRespond(['success' => false, 'error' => 'Site ve URL listesi gerekli.']);
                break;
            }
            $site = getScraperSite($pdo, $siteId);
            if (!$site) {
                scraperApiRespond(['success' => false, 'error' => 'Site bulunamadı.']);
                break;
            }
            $botSettings = getScraperBotSettings($pdo);
            $jobId = createScraperJob($pdo, $siteId, $mappingId, $botSettings, count($urls));
            updateScraperJob($pdo, $jobId, ['status' => 'running']);

            // Batch processing timeout protection
            set_time_limit(300); // 5 dakika
            $batchStartTime = time();
            $maxBatchDuration = 240; // 4 dakika

            $siteConfig = [
                'selectors' => json_decode($site['selectors'] ?? '{}', true) ?: [],
                'settings' => json_decode($site['settings'] ?? '{}', true) ?: [],
                'base_url' => $site['base_url'],
                'slug' => $site['slug'],
            ];
            $engine = new ScraperEngine($botSettings);
            $processed = 0; $failed = 0; $imported = 0; $skipped = 0; $warnings = [];
            $continueOnError = scraperBoolSetting($botSettings, 'bot_bulk_continue_on_error', '1');
            $autoPublish = scraperBoolSetting($botSettings, 'bot_auto_publish', '0');
            $publishDefaults = scraperResolvePublishDefaults($pdo, $site, $mappingId, $botSettings);

            // Batch load existing and duplicate imports to avoid N+1 queries
            $existingImports = getScraperImportsBySourceBatch($pdo, $siteId, $urls);
            $duplicateImports = getScraperDuplicateImportsBySourceBatch($pdo, $siteId, $urls);

            foreach ($urls as $url) {
                // Timeout check
                if (time() - $batchStartTime > $maxBatchDuration) {
                    $warnings[] = ['url' => '', 'message' => "Timeout: İşlem kısaltıldı. {$processed}/" . count($urls) . " URL işlendi."];
                    break;
                }

                $processed++;
                $url = (string)$url;
                $existingImport = $existingImports[$url] ?? null;
                $duplicateImport = $duplicateImports[$url] ?? null;
                $duplicate = resolveScraperDuplicateImportAction($duplicateImport, (string)($botSettings['bot_duplicate_strategy'] ?? 'skip'));
                if ($duplicate['warning'] !== '') {
                    $warnings[] = ['url' => $url, 'message' => $duplicate['warning']];
                }
                if (!$duplicate['should_scrape']) {
                    $skipped++;
                    updateScraperJob($pdo, $jobId, [
                        'processed_urls' => $processed,
                        'failed_urls' => $failed,
                        'imported_urls' => $imported,
                        'error_log' => implode("\n", array_map(static fn($w) => $w['url'] . ' - ' . $w['message'], $warnings)),
                    ]);
                    continue;
                }

                $result = $engine->scrapeUrl($url, $siteConfig, $botSettings);
                if ($result['success']) {
                    $result = scraperApplyTitlePrefixToResult($result, scraperResolveMappingTitlePrefix($pdo, (int)($mappingId ?? 0), $siteId));
                    $importUrl = $duplicate['action'] === 'draft' ? scraperDraftSourceUrl($url) : $url;
                    $importData = scraperImportDataFromResult($siteId, $importUrl, $result, $jobId);
                    if ($duplicate['action'] === 'update' && !empty($duplicate['import_id'])) {
                        updateScraperImport($pdo, (int)$duplicate['import_id'], $importData);
                        $importId = (int)$duplicate['import_id'];
                    } elseif ($duplicate['action'] === 'create' && $existingImport) {
                        updateScraperImport($pdo, (int)$existingImport['id'], $importData);
                        $importId = (int)$existingImport['id'];
                    } else {
                        $importId = createScraperImport($pdo, $importData);
                    }
                    if ($autoPublish && $publishDefaults['category_id'] > 0) {
                        publishScraperImport($pdo, $importId, (int)$publishDefaults['category_id'], (string)$publishDefaults['status']);
                    }
                    $imported++;
                } else {
                    $failed++;
                    $warnings[] = ['url' => $url, 'message' => $result['error']];
                    if (!$continueOnError) {
                        break;
                    }
                }
                updateScraperJob($pdo, $jobId, [
                    'processed_urls' => $processed,
                    'failed_urls' => $failed,
                    'imported_urls' => $imported,
                    'error_log' => implode("\n", array_map(static fn($w) => $w['url'] . ' - ' . $w['message'], $warnings)),
                ]);
            }
            updateScraperJob($pdo, $jobId, ['status' => 'completed']);
            scraperApiRespond(['success' => true, 'job_id' => $jobId, 'processed' => $processed, 'failed' => $failed, 'imported' => $imported, 'skipped' => $skipped, 'warnings' => $warnings]);
            break;

        // -- Import Operations ---------------------------------
        case 'publish_import':
            $importId = (int)($_POST['import_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $status = in_array($_POST['publish_status'] ?? '', ['published', 'draft']) ? $_POST['publish_status'] : 'draft';
            if (!$categoryId && $importId) {
                $importForDefaults = getScraperImport($pdo, $importId);
                if ($importForDefaults) {
                    $siteDefaults = getScraperImportSiteDefaults($pdo, $importForDefaults);
                    $categoryId = (int)($siteDefaults['category_id'] ?? 0);
                    if (!empty($siteDefaults['status']) && in_array($siteDefaults['status'], ['published', 'draft'], true)) {
                        $status = $siteDefaults['status'];
                    }
                }
            }
            if (!$importId || !$categoryId) {
                scraperApiRespond(['success' => false, 'error' => 'Import ID ve kategori gerekli.']);
                break;
            }
            
            // Check if there are edits
            if (isset($_POST['title']) || isset($_POST['author_topic']) || isset($_POST['topic_version']) || isset($_POST['content']) || isset($_POST['download_links'])) {
                $updateData = [];
                if (isset($_POST['title'])) {
                    $updateData['translated_title'] = $_POST['title'];
                    $updateData['source_title'] = $_POST['title'];
                }
                if (isset($_POST['author_topic'])) {
                    $updateData['author_topic'] = trim((string)$_POST['author_topic']);
                }
                if (isset($_POST['topic_version'])) {
                    $updateData['topic_version'] = trim((string)$_POST['topic_version']);
                }
                if (isset($_POST['content'])) {
                    $updateData['translated_content'] = $_POST['content'];
                    $updateData['source_content'] = $_POST['content'];
                }
                if (isset($_POST['download_links'])) {
                    $updateData['source_download_links'] = $_POST['download_links'];
                }
                if (!empty($updateData)) {
                    updateScraperImport($pdo, $importId, $updateData);
                }
            }

            $slug = publishScraperImport($pdo, $importId, $categoryId, $status);
            if ($slug) {
                scraperApiRespond(['success' => true, 'message' => 'İçerik yayınlandı.', 'topic_url' => topicUrlBySlug($pdo, $slug)]);
            } else {
                scraperApiRespond(['success' => false, 'error' => 'Yayınlama başarısız.']);
            }
            break;

        case 'save_and_publish_import':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $status = in_array($_POST['publish_status'] ?? '', ['published', 'draft']) ? $_POST['publish_status'] : 'draft';
            $dataStr = $_POST['data'] ?? '';
            if (!$dataStr) {
                scraperApiRespond(['success' => false, 'error' => 'Veri gerekli.']);
                break;
            }
            $data = is_string($dataStr) ? json_decode($dataStr, true) : $dataStr;
            if (!$data || !isset($data['site_id'])) {
                scraperApiRespond(['success' => false, 'error' => 'Geçersiz veri formatı.']);
                break;
            }

            $siteId = (int)$data['site_id'];
            if (!$categoryId) {
                $categoryId = (int)($data['site_defaults']['category_id'] ?? 0);
            }
            if (!$categoryId) {
                $siteForDefaults = getScraperSite($pdo, $siteId);
                $siteSettings = json_decode($siteForDefaults['settings'] ?? '{}', true) ?: [];
                $categoryId = (int)($siteSettings['site_default_category_id'] ?? 0);
                if (!empty($siteSettings['site_default_status']) && in_array($siteSettings['site_default_status'], ['published', 'draft'], true)) {
                    $status = $siteSettings['site_default_status'];
                }
            }
            if (!$categoryId) {
                scraperApiRespond(['success' => false, 'error' => 'Kategori gerekli.']);
                break;
            }

            $lines = static function ($value): string {
                if (is_array($value)) {
                    return implode("\n", array_filter(array_map('trim', $value)));
                }
                return trim((string)($value ?? ''));
            };

            $sourceUrl = trim((string)($data['source_url'] ?? ''));
            $importData = [
                'bot_site_id' => $siteId,
                'source_url' => $sourceUrl,
                'source_title' => $data['title'] ?? '',
                'translated_title' => $data['translated_title'] ?? null,
                'author_topic' => $data['author_topic'] ?? null,
                'topic_version' => $data['topic_version'] ?? null,
                'source_content' => $data['content'] ?? '',
                'translated_content' => $data['translated_content'] ?? null,
                'source_images' => $lines($data['images'] ?? ''),
                'downloaded_images' => $lines($data['downloaded_images'] ?? ''),
                'source_download_links' => $data['download_links'] ?? '',
                'status' => 'preview',
                'images_count' => $data['images_count'] ?? 0,
            ];

            $existingImport = $sourceUrl !== '' ? getScraperImportBySource($pdo, $siteId, $sourceUrl) : null;
            if ($existingImport) {
                updateScraperImport($pdo, (int)$existingImport['id'], $importData);
                $importId = (int)$existingImport['id'];
            } else {
                $importId = createScraperImport($pdo, $importData);
            }

            if ($importId) {
                $slug = publishScraperImport($pdo, $importId, $categoryId, $status);
                if ($slug) {
                    scraperApiRespond(['success' => true, 'message' => 'İçerik yayınlandı.', 'topic_url' => topicUrlBySlug($pdo, $slug)]);
                } else {
                    scraperApiRespond(['success' => false, 'error' => 'Yayınlama başarısız.']);
                }
            } else {
                scraperApiRespond(['success' => false, 'error' => 'İçerik veritabanına kaydedilemedi.']);
            }
            break;

        case 'delete_import':
            $id = (int)($_POST['id'] ?? 0);
            deleteScraperImport($pdo, $id);
            scraperApiRespond(['success' => true, 'message' => 'İçerik silindi.']);
            break;

        case 'get_import':
            $id = (int)($_GET['id'] ?? 0);
            $import = getScraperImport($pdo, $id);
            if ($import) {
                $import['site_defaults'] = getScraperImportSiteDefaults($pdo, $import);
            }
            scraperApiRespond(['success' => (bool)$import, 'import' => $import]);
            break;

        // -- Bot Settings --------------------------------------
        case 'save_bot_settings':
            saveScraperBotSettings($pdo, $_POST);
            scraperApiRespond(['success' => true, 'message' => 'Bot ayarları kaydedildi.']);
            break;

        // -- Stats ---------------------------------------------
        case 'get_stats':
            $stats = getScraperStats($pdo);
            scraperApiRespond(['success' => true, 'stats' => $stats]);
            break;

        // -- Test Connection -----------------------------------
        case 'test_connection':
            $url = trim($_POST['url'] ?? '');
            if (!$url) {
                scraperApiRespond(['success' => false, 'error' => 'URL gerekli.']);
                break;
            }
            $botSettings = getScraperBotSettings($pdo);
            $engine = new ScraperEngine($botSettings);
            $html = $engine->fetchPage($url);
            scraperApiRespond([
                'success' => (bool)$html,
                'length' => $html ? strlen($html) : 0,
                'message' => $html ? 'Bağlantı başarılı (' . strlen($html) . ' byte)' : 'Bağlantı kurulamadı.',
            ]);
            break;

        // -- Test Translation ---------------------------------
        case 'test_translation':
            $text = trim((string)($_POST['text'] ?? ''));
            if ($text === '') {
                scraperApiRespond(['success' => false, 'error' => 'Çevrilecek metin gerekli.']);
                break;
            }

            if (!function_exists('curl_init')) {
                scraperApiRespond(['success' => false, 'error' => 'Sunucuda cURL eklentisi aktif değil.']);
                break;
            }

            if (mb_strlen($text) > 5000) {
                $text = mb_substr($text, 0, 5000);
            }

            $botSettings = getScraperBotSettings($pdo);
            foreach (['bot_deepl_api_key', 'bot_source_lang', 'bot_target_lang', 'bot_ssl_verify'] as $settingKey) {
                if (array_key_exists($settingKey, $_POST)) {
                    $botSettings[$settingKey] = trim((string)$_POST[$settingKey]);
                }
            }

            $apiKey = trim((string)($botSettings['bot_deepl_api_key'] ?? ''));
            if ($apiKey === '') {
                scraperApiRespond(['success' => false, 'error' => 'DeepL API anahtarı gerekli.']);
                break;
            }

            $sourceLang = strtoupper(trim((string)($botSettings['bot_source_lang'] ?? 'EN'))) ?: 'EN';
            $targetLang = strtoupper(trim((string)($botSettings['bot_target_lang'] ?? 'TR'))) ?: 'TR';
            $engine = new ScraperEngine($botSettings);
            $translatedText = $engine->translateText($text, $sourceLang, $targetLang);
            $translationErrors = $engine->getTranslationErrors();

            if ($translatedText === null || $translatedText === '') {
                scraperApiRespond([
                    'success' => false,
                    'error' => $translationErrors[0] ?? 'DeepL çeviri yanıtı alınamadı.',
                    'translation_errors' => $translationErrors,
                ]);
                break;
            }

            scraperApiRespond([
                'success' => true,
                'message' => 'Çeviri başarılı.',
                'translated_text' => $translatedText,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'translation_errors' => $translationErrors,
            ]);
            break;

        default:
            http_response_code(400);
            scraperApiRespond(['success' => false, 'error' => 'Geçersiz işlem: ' . $action]);
    }
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/scraper.php']);
    http_response_code(500);
    scraperApiRespond(['success' => false, 'error' => 'Sunucu hatası.']);
}

