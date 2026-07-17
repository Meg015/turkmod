<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$pdo = requireDatabaseConnection($pdo ?? null);

$clientKey = 'api_topics_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$apiTopicsRateLimit = max(1, (int)($settings['api_topics_rate_limit'] ?? 60));
$apiTopicsRateWindow = max(1, (int)($settings['api_topics_rate_window'] ?? 1));
if (!checkRateLimit($clientKey, $apiTopicsRateLimit, $apiTopicsRateWindow)) {
    sendRateLimitError(max(60, $apiTopicsRateWindow * 60));
}
incrementRateLimit($clientKey, $apiTopicsRateWindow);

$search = function_exists('sanitizeSearchQuery') ? sanitizeSearchQuery($_GET['q'] ?? '') : trim((string)($_GET['q'] ?? ''));
$sortInput = (string) ($_GET['sort'] ?? 'newest');
$sort = in_array($sortInput, ['newest', 'popular', 'downloads', 'comments'], true) ? $sortInput : 'newest';
$category = function_exists('validateSlug') ? (validateSlug($_GET['category'] ?? '') ?? '') : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(60, max(1, (int)($_GET['per_page'] ?? TOPICS_PER_PAGE)));

$result = getTopics($pdo, $page, $perPage, $search, $sort, $category);

ob_start();
foreach ($result['items'] as $item) {
    include __DIR__ . '/../includes/partials/topic-card.php';
}
$html = trim((string)ob_get_clean());

if ($html === '') {
    $html = renderEmptyState(
        $search !== '' ? 'Sonuç bulunamadı' : 'Henüz içerik yok',
        $search !== '' ? 'Farklı anahtar kelimelerle tekrar deneyebilirsiniz.' : 'Bu filtreye uygun yayınlanmış içerik bulunmuyor.',
        $search !== '' ? 'bi-search' : 'bi-inbox'
    );
}

sendSuccess('OK', [
    'html' => $html,
    'total' => (int)$result['total'],
    'page' => (int)$result['page'],
    'per_page' => (int)$result['perPage'],
]);
