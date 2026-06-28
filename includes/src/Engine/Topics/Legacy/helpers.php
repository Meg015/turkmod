<?php

declare(strict_types=1);

/**
 * Topics Module — topic CRUD, media, favorites, download links.
 */

// Load leaderboard triggers if available
if (file_exists(dirname(__DIR__, 3) . '/Modules/Leaderboard/Legacy/triggers.php')) {
    require_once dirname(__DIR__, 3) . '/Modules/Leaderboard/Legacy/triggers.php';
}

function getTopicPrimaryMediaPath(array $row): ?string
{
    $path = trim((string)($row['primary_media_path'] ?? ''));
    return $path !== '' ? $path : null;
}

function getTopicMediaRecords(?PDO $pdo, int $topicId, bool $includeAttachments = false): array
{
    if (!$pdo || $topicId <= 0) {
        return [];
    }

    try {
        $where = $includeAttachments
            ? 'WHERE topic_id = ?'
            : "WHERE topic_id = ? AND (type = 'image' OR type = 'video' OR mime_type LIKE 'image/%')";
        $stmt = $pdo->prepare("SELECT id, topic_id, path, original_name, mime_type, type, disk, is_primary, display_order
                               FROM media_files
                               {$where}
                               ORDER BY is_primary DESC, display_order ASC, id ASC");
        $stmt->execute([$topicId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function getTopicMediaGallery(?PDO $pdo, int $topicId): array
{
    return array_values(array_filter(array_map(static function (array $row): ?string {
        $path = trim((string)($row['path'] ?? ''));
        return $path !== '' ? $path : null;
    }, getTopicMediaRecords($pdo, $topicId))));
}

function createTopicMediaRecord(?PDO $pdo, int $topicId, string $path, string $type = 'video', int $displayOrder = 0, bool $isPrimary = false, string $disk = 'remote'): ?int
{
    if (!$pdo || $topicId <= 0) {
        return null;
    }

    $path = trim($path);
    if ($path === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO media_files (topic_id, user_id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at)
                               VALUES (:topic_id, :user_id, :type, :disk, :path, :original_name, :mime_type, NULL, :display_order, :is_primary, NOW(), NOW())");
        $stmt->execute([
            'topic_id' => $topicId,
            'user_id' => $_SESSION['_auth_user_id'] ?? null,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => basename(parse_url($path, PHP_URL_PATH) ?: $path),
            'mime_type' => $type === 'image' ? 'image/remote' : 'video/remote',
            'display_order' => max(0, $displayOrder),
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'createTopicMediaRecord', 'topic_id' => $topicId]);
        return null;
    }
}

function deleteTopicMediaRecord(?PDO $pdo, int $mediaId): void
{
    if (!$pdo || $mediaId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT path, disk FROM media_files WHERE id = ? LIMIT 1");
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $pdo->prepare("DELETE FROM media_files WHERE id = ?")->execute([$mediaId]);
        if ((string)($row['disk'] ?? 'local') === 'local') {
            topicDeletePhysicalFile((string)($row['path'] ?? ''));
        }
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'deleteTopicMediaRecord', 'media_id' => $mediaId]);
    }
}

function ensureTopicFavoritesTable(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_favorites (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        topic_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY topic_favorites_unique (topic_id, user_id),
        INDEX topic_favorites_user_index (user_id),
        CONSTRAINT topic_favorites_topic_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
        CONSTRAINT topic_favorites_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $initialized = true;
}

function userHasFavoritedTopic(?PDO $pdo, int $topicId, int $userId): bool
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return false;
    }

    ensureTopicFavoritesTable($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM topic_favorites WHERE topic_id = :topic_id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function getTopicFavoriteCount(?PDO $pdo, int $topicId): int
{
    if (!$pdo || $topicId <= 0) {
        return 0;
    }

    ensureTopicFavoritesTable($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM topic_favorites WHERE topic_id = :topic_id');
    $stmt->execute(['topic_id' => $topicId]);
    return (int) $stmt->fetchColumn();
}

function toggleTopicFavorite(?PDO $pdo, int $topicId, int $userId): bool
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return false;
    }

    ensureTopicFavoritesTable($pdo);

    if (userHasFavoritedTopic($pdo, $topicId, $userId)) {
        $stmt = $pdo->prepare('DELETE FROM topic_favorites WHERE topic_id = :topic_id AND user_id = :user_id');
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        if (function_exists('logActivity')) {
            logActivity($pdo, 'topic_favorite_removed', 'topic', $topicId);
        }
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO topic_favorites (topic_id, user_id, created_at) VALUES (:topic_id, :user_id, NOW())');
    $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
    if (function_exists('logActivity')) {
        logActivity($pdo, 'topic_favorite_added', 'topic', $topicId);
    }
    return true;
}

function parseTopicDownloadLinksText(string $raw): array
{
    $links = [];
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    foreach ($lines as $line) {
        $parts = explode('|', $line, 2);
        $name = count($parts) === 2 ? trim($parts[0]) : 'Link';
        $url = count($parts) === 2 ? trim($parts[1]) : trim($parts[0]);

        if ($url === '') {
            continue;
        }

        $links[] = [
            'id' => null,
            'name' => $name !== '' ? $name : 'Link',
            'url' => $url,
            'download_count' => 0,
        ];
    }

    return $links;
}

function getTopicDownloadLinks(?PDO $pdo, int $topicId, string $legacyLinks = ''): array
{
    $fallback = parseTopicDownloadLinksText($legacyLinks);

    if (!$pdo || $topicId <= 0) {
        return $fallback;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, url, download_count
                               FROM topic_download_links
                               WHERE topic_id = ?
                               ORDER BY display_order ASC, id ASC");
        $stmt->execute([$topicId]);
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            return array_map(static function (array $row): array {
                return [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'url' => (string)$row['url'],
                    'download_count' => (int)($row['download_count'] ?? 0),
                ];
            }, $rows);
        }

        if (!empty($fallback)) {
            syncTopicDownloadLinks($pdo, $topicId, $legacyLinks);

            $stmt->execute([$topicId]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return array_map(static function (array $row): array {
                    return [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'url' => (string)$row['url'],
                        'download_count' => (int)($row['download_count'] ?? 0),
                    ];
                }, $rows);
            }
        }
    } catch (Throwable $e) {
        return $fallback;
    }

    return $fallback;
}

function syncTopicDownloadLinks(?PDO $pdo, int $topicId, string $rawLinks): void
{
    if (!$pdo || $topicId <= 0) {
        return;
    }

    $links = parseTopicDownloadLinksText($rawLinks);
    $existingCounts = [];

    $inTransaction = $pdo->inTransaction();
    if (!$inTransaction) {
        $pdo->beginTransaction();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT url, download_count FROM topic_download_links WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        foreach ($stmt->fetchAll() as $row) {
            $existingCounts[(string)$row['url']] = (int)($row['download_count'] ?? 0);
        }

        $pdo->prepare("DELETE FROM topic_download_links WHERE topic_id = ?")->execute([$topicId]);

        if (!empty($links)) {
            $insert = $pdo->prepare("INSERT INTO topic_download_links
                (topic_id, name, url, download_count, display_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

            foreach ($links as $index => $link) {
                $url = (string)$link['url'];
                $insert->execute([
                    $topicId,
                    (string)$link['name'],
                    $url,
                    $existingCounts[$url] ?? 0,
                    $index,
                ]);
            }
        }

        if (!$inTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if (!$inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function incrementTopicDownloadLink(?PDO $pdo, int $linkId): ?array
{
    if (!$pdo || $linkId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, topic_id, name, url, download_count
                               FROM topic_download_links
                               WHERE id = ?
                               LIMIT 1");
        $stmt->execute([$linkId]);
        $link = $stmt->fetch();
        if (!$link) {
            return null;
        }

        $topicId = (int)($link['topic_id'] ?? 0);
        $clientKey = 'download_' . $topicId . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
        $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
        $downloadCountRateLimit = max(1, (int)($settings['download_count_rate_limit'] ?? 1));
        $downloadCountRateWindow = max(1, (int)($settings['download_count_rate_window'] ?? 60));
        $shouldCount = checkRateLimit($clientKey, $downloadCountRateLimit, $downloadCountRateWindow);

        if ($shouldCount) {
            $pdo->prepare("UPDATE topic_download_links SET download_count = download_count + 1, updated_at = NOW() WHERE id = ?")
                ->execute([$linkId]);
            $pdo->prepare("UPDATE topics SET download_count = download_count + 1 WHERE id = ?")
                ->execute([$topicId]);
            incrementRateLimit($clientKey, $downloadCountRateWindow);

            try {
                $pdo->prepare("INSERT INTO downloads (topic_id, user_id, ip_address, user_agent, created_at, updated_at)
                               VALUES (?, ?, ?, ?, NOW(), NOW())")
                    ->execute([
                        $topicId,
                        $_SESSION['_auth_user_id'] ?? null,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ]);
            } catch (Throwable $e) {
                appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId]);
            }

            // Update leaderboard stats for topic owner
            try {
                $topicStmt = $pdo->prepare("SELECT author_id FROM topics WHERE id = ? LIMIT 1");
                $topicStmt->execute([$topicId]);
                $topicOwnerId = (int)($topicStmt->fetchColumn() ?: 0);
                $downloadUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
                if ($downloadUserId > 0) {
                    if (is_file(dirname(__DIR__, 3) . '/Modules/Events/init.php')) {
                        require_once dirname(__DIR__, 3) . '/Modules/Events/init.php';
                    }
                    if (function_exists('eventsRecordActivity')) {
                        eventsRecordActivity($pdo, $downloadUserId, 'topic_downloaded', 'topic', $topicId, [
                            'subject_user_id' => $topicOwnerId,
                            'dedupe_key' => 'topic_downloaded:topic:' . $topicId . ':user:' . $downloadUserId . ':date:' . date('Y-m-d'),
                        ]);
                    }
                }
                if ($topicOwnerId > 0) {
                    if (function_exists('leaderboardTriggerDownload')) {
                        leaderboardTriggerDownload($pdo, $topicOwnerId);
                    }
                }
            } catch (Throwable $e) {
                appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId, 'context' => 'leaderboard_trigger']);
            }

            $link['download_count'] = (int)($link['download_count'] ?? 0) + 1;
        }

        return $link;
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId]);
        return null;
    }
}
