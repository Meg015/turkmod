<?php

declare(strict_types=1);

use App\Engine\Scraper\Support\ScraperEngine;

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Support/ScraperEngine.php';

function scraperImageCacheDirectory(): string
{
    return dirname(__DIR__) . '/storage/cache/scraper-images';
}

function scraperImageCacheKey(string $url): string
{
    return hash('sha256', $url);
}

function scraperImageReadCache(string $url, int $ttlSeconds = 604800): ?array
{
    $directory = scraperImageCacheDirectory();
    $key = scraperImageCacheKey($url);
    $dataFile = $directory . '/' . $key . '.bin';
    $metaFile = $directory . '/' . $key . '.json';

    if (!is_file($dataFile) || !is_file($metaFile) || (time() - filemtime($dataFile)) > max(1, $ttlSeconds)) {
        return null;
    }

    $meta = json_decode((string) file_get_contents($metaFile), true);
    $mime = is_array($meta) ? strtolower((string)($meta['mime'] ?? '')) : '';
    if ($mime === '' || !str_starts_with($mime, 'image/')) {
        return null;
    }

    $data = file_get_contents($dataFile);
    return is_string($data) && $data !== '' ? ['data' => $data, 'mime' => $mime] : null;
}

function scraperImageWriteCache(string $url, array $image): void
{
    $data = $image['data'] ?? null;
    $mime = strtolower((string)($image['mime'] ?? ''));
    if (!is_string($data) || $data === '' || $mime === '' || !str_starts_with($mime, 'image/')) {
        return;
    }

    $directory = scraperImageCacheDirectory();
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    if (!is_dir($directory) || !is_writable($directory)) {
        return;
    }

    $key = scraperImageCacheKey($url);
    file_put_contents($directory . '/' . $key . '.bin', $data, LOCK_EX);
    file_put_contents(
        $directory . '/' . $key . '.json',
        json_encode(['mime' => $mime, 'cached_at' => time()], JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function scraperImageSend(array $image, string $cacheState): void
{
    header_remove('Cross-Origin-Embedder-Policy');
    header('Content-Type: ' . $image['mime']);
    header('Cache-Control: public, max-age=604800, immutable');
    header('X-Content-Type-Options: nosniff');
    header('X-Scraper-Image-Cache: ' . $cacheState);
    header('Content-Length: ' . strlen((string)$image['data']));
    echo $image['data'];
}

function scraperImageCanPreview(?PDO $pdo): bool
{
    if (PHP_SAPI === 'cli' && defined('ALLOW_CLI_ADMIN') && ALLOW_CLI_ADMIN === true) {
        return true;
    }

    $userId = (int)($_SESSION['_auth_user_id'] ?? 0);
    if (!$pdo instanceof PDO || $userId <= 0 || !function_exists('userHasPermission')) {
        return false;
    }

    return userHasPermission($pdo, $userId, 'admin.access') || userHasPermission($pdo, $userId, 'scraper.view');
}

$pdo = requireDatabaseConnection($pdo ?? null);
if (!scraperImageCanPreview($pdo)) {
    http_response_code(403);
    exit;
}

$url = trim((string)($_GET['url'] ?? ''));
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit;
}

$host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
if ($host === '') {
    http_response_code(400);
    exit;
}

$allowedHosts = [];
try {
    $sites = $pdo->query("SELECT base_url, settings FROM bot_sites WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sites as $site) {
        $siteHost = strtolower((string)(parse_url((string)($site['base_url'] ?? ''), PHP_URL_HOST) ?: ''));
        if ($siteHost !== '') {
            $allowedHosts[$siteHost] = true;
        }
        $settings = json_decode((string)($site['settings'] ?? '{}'), true) ?: [];
        foreach (explode(',', (string)($settings['allowed_image_domains'] ?? '')) as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain !== '') {
                $allowedHosts[$domain] = true;
            }
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}

$isAllowed = false;
foreach (array_keys($allowedHosts) as $allowedHost) {
    if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    exit;
}

$cachedImage = scraperImageReadCache($url);
if ($cachedImage) {
    scraperImageSend($cachedImage, 'hit');
    exit;
}

$engine = new ScraperEngine(array_merge(getScraperBotSettings($pdo), [
    'bot_request_delay' => '0',
    'bot_request_timeout' => '6',
    'bot_retry_count' => '0',
    'bot_retry_delay' => '0',
]));
$image = $engine->fetchPreviewImage($url);
if (!$image) {
    http_response_code(404);
    exit;
}

scraperImageWriteCache($url, $image);
scraperImageSend($image, 'miss');
