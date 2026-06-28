<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
session_write_close();

$pdo = requireDatabaseConnection($pdo ?? null);

$query = function_exists('sanitizeSearchQuery') ? sanitizeSearchQuery($_GET['q'] ?? '') : trim((string)($_GET['q'] ?? ''));
$clientKey = 'api_search_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$apiSearchRateLimit = max(1, (int)($settings['search_rate_limit'] ?? 30));
$apiSearchRateWindow = max(1, (int)($settings['search_rate_window'] ?? 1));

if (!checkRateLimit($clientKey, $apiSearchRateLimit, $apiSearchRateWindow)) {
    sendRateLimitError(max(60, $apiSearchRateWindow * 60));
}
incrementRateLimit($clientKey, $apiSearchRateWindow);

if (mb_strlen($query) < 2) {
    sendSuccess('OK', ['results' => [], 'total' => 0]);
}

$results = [];

try {
    $buildFullTextQuery = static function (string $value): string {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        $terms = [];

        foreach ($tokens as $token) {
            $token = preg_replace('/[+\-><()~*"@]+/u', '', $token) ?? '';
            $token = trim($token);
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            $terms[] = '+' . $token . '*';
        }

        return implode(' ', array_slice(array_unique($terms), 0, 6));
    };

    $rows = [];
    $fullTextQuery = $buildFullTextQuery($query);

    if ($fullTextQuery !== '') {
        try {
            $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.topic_descriptions, t.download_count,
                                          pm.path AS primary_media_path,
                                          cat.name AS category, cat.slug AS category_slug,
                                          MATCH(t.title, t.topic_descriptions) AGAINST(:search_score IN BOOLEAN MODE) AS relevance
                                   FROM topics t
                                   LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                                   LEFT JOIN categories cat ON cat.id = t.category_id
                                   WHERE t.status = 'published'
                                     AND t.deleted_at IS NULL
                                     AND MATCH(t.title, t.topic_descriptions) AGAINST(:search_where IN BOOLEAN MODE)
                                   ORDER BY relevance DESC,
                                            t.download_count DESC,
                                            COALESCE(t.published_at, t.created_at) DESC
                                   LIMIT 8");
            $stmt->execute([
                'search_score' => $fullTextQuery,
                'search_where' => $fullTextQuery,
            ]);
            $rows = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            appLogException($e, ['source' => 'api/search.php fulltext']);
            $rows = [];
        }
    }

    if ($rows === []) {
        $like = '%' . $query . '%';
        $starts = $query . '%';
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.topic_descriptions, t.download_count,
                                      pm.path AS primary_media_path,
                                      cat.name AS category, cat.slug AS category_slug
                               FROM topics t
                               LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                               LEFT JOIN categories cat ON cat.id = t.category_id
                               WHERE t.status = 'published'
                                 AND t.deleted_at IS NULL
                                 AND (
                                    t.title LIKE :q_title
                                    OR t.slug LIKE :q_slug
                                    OR COALESCE(cat.name, '') LIKE :q_category
                                 )
                               ORDER BY
                                 CASE WHEN t.title LIKE :starts THEN 0 ELSE 1 END,
                                 t.download_count DESC,
                                 COALESCE(t.published_at, t.created_at) DESC
                               LIMIT 8");
        $stmt->execute([
            'q_title' => $like,
            'q_slug' => $like,
            'q_category' => $like,
            'starts' => $starts,
        ]);
        $rows = $stmt->fetchAll() ?: [];
    }

    foreach ($rows as $row) {
        $image = getTopicPrimaryMediaPath($row) ?: 'assets/portal-pack.svg';
        if (!str_starts_with((string)$image, 'http')) {
            $image = rtrim($baseUri, '/') . '/' . ltrim((string)$image, '/');
        }
        $results[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'category' => (string)($row['category'] ?? 'Genel'),
            'url' => topicUrlForRow($row),
            'image' => $image,
            'downloads' => (int)($row['download_count'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/search.php']);
    sendServerError('Arama sırasında hata oluştu.', $e);
}

sendSuccess('OK', ['results' => $results, 'total' => count($results)]);