<?php

declare(strict_types=1);

require_once __DIR__ . '/../admin/init.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Legacy/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Legacy/engine.php';

$pdo = requireDatabaseConnection($pdo ?? null);

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

$engine = new ScraperEngine(array_merge(getScraperBotSettings($pdo), [
    'bot_request_delay' => '0',
    'bot_request_timeout' => '15',
]));
$image = $engine->fetchPreviewImage($url);
if (!$image) {
    http_response_code(404);
    exit;
}

header_remove('Cross-Origin-Embedder-Policy');
header('Content-Type: ' . $image['mime']);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo $image['data'];

