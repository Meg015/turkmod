<?php

declare(strict_types=1);

function scraperRateLimitFetch(string $key): int|false
{
    if (function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL)) {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? (int) $value : false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $entry = $_SESSION['_scraper_rate_limits'][$key] ?? null;
    if (!is_array($entry)) {
        return false;
    }

    $expiresAt = (int) ($entry['expires_at'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < time()) {
        unset($_SESSION['_scraper_rate_limits'][$key]);
        return false;
    }

    return (int) ($entry['value'] ?? 0);
}

function scraperRateLimitStore(string $key, int $value, int $ttlSeconds): void
{
    if (function_exists('apcu_store') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL)) {
        apcu_store($key, $value, $ttlSeconds);
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['_scraper_rate_limits'][$key] = [
        'value' => $value,
        'expires_at' => time() + max(1, $ttlSeconds),
    ];
}

function scraperShouldRateLimitAction(string $action, bool $isPrivileged = false): bool
{
    if ($isPrivileged) {
        return false;
    }

    return in_array($action, [
        'discover_urls',
        'preview_url',
        'scrape_single',
        'scrape_batch',
        'test_connection',
    ], true);
}

function scraperJsArg($value): string
{
    return htmlspecialchars(
        json_encode($value, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Content Scraper Bot Module — Database Schema & CRUD Helpers
 */

// ─── Database Schema ──────────────────────────────────────────────

function ensureScraperSchema(?PDO $pdo): void
{
    if (!$pdo) return;

    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    // Bot Sites
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_sites (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        base_url VARCHAR(2048) NOT NULL,
        description TEXT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        selectors JSON NULL,
        settings JSON NULL,
        total_imports INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bot Category Mappings
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_category_mappings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bot_site_id BIGINT UNSIGNED NOT NULL,
        remote_category_name VARCHAR(255) NOT NULL,
        remote_category_url VARCHAR(2048) NOT NULL,
        local_category_id BIGINT UNSIGNED NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        INDEX idx_bcm_site (bot_site_id),
        CONSTRAINT fk_bcm_site FOREIGN KEY (bot_site_id) REFERENCES bot_sites(id) ON DELETE CASCADE,
        CONSTRAINT fk_bcm_local_category FOREIGN KEY (local_category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bot Jobs
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_jobs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bot_site_id BIGINT UNSIGNED NOT NULL,
        bot_category_mapping_id BIGINT UNSIGNED NULL,
        status ENUM('pending','running','completed','failed','cancelled') DEFAULT 'pending',
        total_urls INT UNSIGNED DEFAULT 0,
        processed_urls INT UNSIGNED DEFAULT 0,
        failed_urls INT UNSIGNED DEFAULT 0,
        imported_urls INT UNSIGNED DEFAULT 0,
        settings JSON NULL,
        error_log TEXT NULL,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        INDEX idx_bj_site (bot_site_id),
        CONSTRAINT fk_bj_site FOREIGN KEY (bot_site_id) REFERENCES bot_sites(id) ON DELETE CASCADE,
        CONSTRAINT fk_bj_mapping FOREIGN KEY (bot_category_mapping_id) REFERENCES bot_category_mappings(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bot Imports
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_imports (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bot_job_id BIGINT UNSIGNED NULL,
        bot_site_id BIGINT UNSIGNED NOT NULL,
        topic_id BIGINT UNSIGNED NULL,
        source_url VARCHAR(2048) NOT NULL,
        source_title VARCHAR(500) NULL,
        translated_title VARCHAR(500) NULL,
        author_topic VARCHAR(255) NULL,
        topic_version VARCHAR(255) NULL,
        source_content LONGTEXT NULL,
        translated_content LONGTEXT NULL,
        source_images TEXT NULL,
        downloaded_images TEXT NULL,
        source_download_links TEXT NULL,
        status ENUM('pending','preview','imported','failed','skipped') DEFAULT 'pending',
        images_count INT UNSIGNED DEFAULT 0,
        error_message TEXT NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY bot_imports_site_url_unique (bot_site_id, source_url(255)),
        INDEX idx_bi_site (bot_site_id),
        INDEX idx_bi_job (bot_job_id),
        INDEX idx_bi_status (status),
        CONSTRAINT fk_bi_site FOREIGN KEY (bot_site_id) REFERENCES bot_sites(id) ON DELETE CASCADE,
        CONSTRAINT fk_bi_job FOREIGN KEY (bot_job_id) REFERENCES bot_jobs(id) ON DELETE SET NULL,
        CONSTRAINT fk_bi_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure columns exist (for upgrades)
    $cols = [
        'bot_sites' => ['total_imports' => 'INT UNSIGNED DEFAULT 0'],
        'bot_imports' => [
            'translated_title' => 'VARCHAR(500) NULL',
            'translated_content' => 'LONGTEXT NULL',
            'author_topic' => 'VARCHAR(255) NULL',
            'topic_version' => 'VARCHAR(255) NULL',
        ],
    ];
    foreach ($cols as $table => $columns) {
        foreach ($columns as $col => $def) {
            if (!adminColumnExists($pdo, $table, $col)) {
                try { $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}"); } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
            }
        }
    }
}

// ─── Bot Settings ─────────────────────────────────────────────────

function getScraperBotSettings(?PDO $pdo): array
{
    static $cache = [];
    $cacheKey = $pdo ? spl_object_id($pdo) : 0;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $defaults = [
        'bot_deepl_api_key'     => '',
        'bot_user_agent'        => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'bot_request_delay'     => '1000',
        'bot_request_timeout'   => '30',
        'bot_retry_count'       => '1',
        'bot_retry_delay'       => '750',
        'bot_discover_cover_lookup_limit'=> '2',
        'bot_follow_redirects'  => '1',
        'bot_ssl_verify'        => '1',
        'bot_proxy_url'         => '',
        'bot_custom_headers'    => '',
        'bot_default_max_images'=> '5',
        'bot_image_save_path'   => 'uploads/konu/',
        'bot_content_align'     => 'center',
        'bot_min_title_length'  => '3',
        'bot_min_content_length'=> '20',
        'bot_clean_html'        => '1',
        'bot_strip_scripts'     => '1',
        'bot_strip_iframes'     => '1',
        'bot_append_source_link'=> '0',
        'bot_skip_duplicate_urls'=> '1',
        'bot_extract_download_links'=> '1',
        'bot_translate_enabled' => '0',
        'bot_translate_title'   => '1',
        'bot_translate_content' => '1',
        'bot_translate_download_names'=> '0',
        'bot_translation_fallback_original'=> '1',
        'bot_source_lang'       => 'EN',
        'bot_target_lang'       => 'TR',
        'bot_auto_publish'      => '0',
        'bot_default_status'    => 'draft',
        'bot_default_author_id' => '1',
        'bot_duplicate_strategy'=> 'skip',
        'bot_publish_date_mode' => 'now',
        'bot_require_cover_image'=> '0',
        'bot_download_images'   => '1',
        'bot_use_hotlink_images'=> '0',
        'bot_image_filename_mode'=> 'slug',
        'bot_allowed_image_extensions'=> 'jpg,jpeg,png,webp,gif',
        'bot_bulk_concurrency'  => '1',
        'bot_bulk_max_topics_per_page'=> '0',
        'bot_bulk_continue_on_error'=> '1',
        'bot_log_level'         => 'normal',
        'bot_bulk_default_selected'=> '1',
        'bot_detect_author_enabled' => '1',
        'bot_detect_author_labels' => 'author,authors,credit,credits',
        'bot_detect_version_enabled' => '1',
        'bot_detect_version_pattern' => '1\\.(?:[3-9]\\d|[1-9]\\d{2,})',
    ];

    if (!$pdo) return $defaults;

    try {
        $keys = array_keys($defaults);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT `key`, value FROM settings WHERE `key` IN ({$placeholders})");
        $stmt->execute($keys);
        foreach ($stmt->fetchAll() as $row) {
            if (array_key_exists($row['key'], $defaults)) {
                $defaults[$row['key']] = (string)($row['value'] ?? '');
            }
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    // Public proxy transport is intentionally disabled until destination
    // pinning can be enforced through the proxy connection itself.
    $defaults['bot_proxy_url'] = '';

    $cache[$cacheKey] = $defaults;
    return $defaults;
}

function saveScraperBotSettings(?PDO $pdo, array $input): void
{
    if (!$pdo) return;
    $keys = [
        'bot_deepl_api_key', 'bot_user_agent', 'bot_request_delay', 'bot_request_timeout',
        'bot_retry_count', 'bot_retry_delay', 'bot_discover_cover_lookup_limit',
        'bot_follow_redirects', 'bot_ssl_verify',
        'bot_proxy_url', 'bot_custom_headers',
        'bot_default_max_images', 'bot_image_save_path', 'bot_translate_enabled',
        'bot_translate_title', 'bot_translate_content', 'bot_translate_download_names',
        'bot_translation_fallback_original',
        'bot_source_lang', 'bot_target_lang', 'bot_auto_publish', 'bot_default_status',
        'bot_content_align', 'bot_min_title_length', 'bot_min_content_length', 'bot_clean_html',
        'bot_strip_scripts', 'bot_strip_iframes', 'bot_append_source_link', 'bot_skip_duplicate_urls',
        'bot_extract_download_links', 'bot_default_author_id', 'bot_duplicate_strategy',
        'bot_publish_date_mode', 'bot_require_cover_image', 'bot_download_images',
        'bot_use_hotlink_images', 'bot_image_filename_mode', 'bot_allowed_image_extensions',
        'bot_bulk_concurrency', 'bot_bulk_max_topics_per_page', 'bot_bulk_continue_on_error',
        'bot_log_level', 'bot_bulk_default_selected',
        'bot_detect_author_enabled', 'bot_detect_author_labels',
        'bot_detect_version_enabled', 'bot_detect_version_pattern',
    ];
    $boolKeys = [
        'bot_translate_enabled', 'bot_auto_publish', 'bot_follow_redirects', 'bot_ssl_verify',
        'bot_clean_html', 'bot_strip_scripts', 'bot_strip_iframes', 'bot_append_source_link',
        'bot_skip_duplicate_urls', 'bot_extract_download_links', 'bot_translate_title',
        'bot_translate_content', 'bot_translate_download_names', 'bot_translation_fallback_original',
        'bot_require_cover_image', 'bot_download_images', 'bot_use_hotlink_images',
        'bot_bulk_continue_on_error', 'bot_bulk_default_selected',
        'bot_detect_author_enabled', 'bot_detect_version_enabled',
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
        VALUES (?, ?, 'string', NOW(), NOW())
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()");

    foreach ($keys as $k) {
        if (in_array($k, $boolKeys)) {
            $val = isset($input[$k]) ? '1' : '0';
        } else {
            $val = $k === 'bot_proxy_url' ? '' : trim((string)($input[$k] ?? ''));
        }
        $stmt->execute([$k, $val]);
    }
}

function markScraperImportedTopics(?PDO $pdo, int $siteId, array $topics): array
{
    if (!$pdo || empty($topics)) return $topics;

    $urls = [];
    foreach ($topics as $topic) {
        $url = is_array($topic) ? (string)($topic['url'] ?? '') : (string)$topic;
        if ($url !== '') $urls[] = $url;
    }
    $urls = array_values(array_unique($urls));
    if (empty($urls)) return $topics;

    try {
        $placeholders = implode(',', array_fill(0, count($urls), '?'));
        // INNER JOIN topics kaldırıldı - sadece bot_imports tablosu yeterli
        $stmt = $pdo->prepare("SELECT i.source_url, i.status, i.topic_id
                               FROM bot_imports i
                               WHERE i.bot_site_id = ? AND i.source_url IN ({$placeholders})");
        $stmt->execute(array_merge([$siteId], $urls));
        $seen = [];
        foreach ($stmt->fetchAll() as $row) {
            $seen[(string)$row['source_url']] = [
                'status' => (string)($row['status'] ?? ''),
                'topic_id' => (int)($row['topic_id'] ?? 0),
            ];
        }

        foreach ($topics as $index => $topic) {
            $url = is_array($topic) ? (string)($topic['url'] ?? '') : (string)$topic;
            if ($url === '' || !isset($seen[$url])) continue;
            if (!is_array($topics[$index])) {
                $topics[$index] = ['url' => $url, 'title' => 'Link ' . ($index + 1), 'image' => ''];
            }
            $topics[$index]['already_imported'] = true;
            $topics[$index]['imported_status'] = $seen[$url]['status'];
            $topics[$index]['imported_topic_id'] = $seen[$url]['topic_id'];
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return $topics;
}

// ─── Sites CRUD ───────────────────────────────────────────────────

function getScraperSites(?PDO $pdo): array
{
    if (!$pdo) return [];
    try {
        return $pdo->query("SELECT s.*,
            (SELECT COUNT(*) FROM bot_category_mappings WHERE bot_site_id = s.id) AS mapping_count,
            (SELECT COUNT(*) FROM bot_imports WHERE bot_site_id = s.id AND status = 'imported' AND topic_id IS NOT NULL) AS import_count
            FROM bot_sites s ORDER BY s.created_at DESC")->fetchAll();
    } catch (Throwable $e) { return []; }
}

function getScraperSite(?PDO $pdo, int $id): ?array
{
    static $cache = [];
    if (!$pdo) return null;
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM bot_sites WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch() ?: null;
        $cache[$id] = $result;
        return $result;
    } catch (Throwable $e) { return null; }
}

function saveScraperSite(?PDO $pdo, array $data, ?int $id = null): int
{
    if (!$pdo) return 0;

    $selectors = json_encode([
        'topic_list'     => trim($data['sel_topic_list'] ?? ''),
        'topic_link'     => trim($data['sel_topic_link'] ?? ''),
        'title'          => trim($data['sel_title'] ?? ''),
        'content'        => trim($data['sel_content'] ?? ''),
        'images'         => trim($data['sel_images'] ?? ''),
        'download_links' => trim($data['sel_download_links'] ?? ''),
        'pagination'     => trim($data['sel_pagination'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);

    $normalizeList = static function ($value): array {
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $decoded;
        }
        return (array)$value;
    };
    $replacementRules = [];
    $findList = $normalizeList($data['site_replace_find'] ?? $data['site_replace_find[]'] ?? []);
    $replaceList = $normalizeList($data['site_replace_replace'] ?? $data['site_replace_replace[]'] ?? []);
    $scopeList = $normalizeList($data['site_replace_scope'] ?? $data['site_replace_scope[]'] ?? []);
    foreach ($findList as $index => $find) {
        $find = trim((string)$find);
        if ($find === '') continue;
        $scope = (string)($scopeList[$index] ?? 'all');
        if (!in_array($scope, ['all', 'title', 'content', 'download_links'], true)) {
            $scope = 'all';
        }
        $replacementRules[] = [
            'find' => $find,
            'replace' => (string)($replaceList[$index] ?? ''),
            'scope' => $scope,
        ];
    }
    $removeTexts = [];
    $removeTextList = $normalizeList($data['remove_text_text'] ?? []);
    $removeScopeList = $normalizeList($data['remove_text_scope'] ?? []);
    foreach ($removeTextList as $index => $text) {
        $text = trim((string)$text);
        if ($text === '') continue;
        $scope = (string)($removeScopeList[$index] ?? 'all');
        if (!in_array($scope, ['all', 'title', 'content', 'download_links'], true)) $scope = 'all';
        $removeTexts[] = ['text' => $text, 'scope' => $scope];
    }
    $autoTags = [];
    $keywordList = $normalizeList($data['auto_tag_keyword'] ?? []);
    $tagList = $normalizeList($data['auto_tag_tag'] ?? []);
    foreach ($keywordList as $index => $keyword) {
        $keyword = trim((string)$keyword);
        $tag = trim((string)($tagList[$index] ?? ''));
        if ($keyword !== '' && $tag !== '') $autoTags[] = ['keyword' => $keyword, 'tag' => $tag];
    }
    $downloadLinkRules = [];
    $downloadFindList = $normalizeList($data['download_link_find'] ?? []);
    $downloadReplaceList = $normalizeList($data['download_link_replace'] ?? []);
    foreach ($downloadFindList as $index => $find) {
        $find = trim((string)$find);
        if ($find !== '') $downloadLinkRules[] = ['find' => $find, 'replace' => (string)($downloadReplaceList[$index] ?? '')];
    }

    $settings = json_encode([
        'max_images'     => (int)($data['max_images'] ?? 5),
        'translate'      => isset($data['translate']) ? true : false,
        'source_lang'    => trim($data['source_lang'] ?? 'EN'),
        'target_lang'    => trim($data['target_lang'] ?? 'TR'),
        'custom_headers' => trim($data['custom_headers'] ?? ''),
        'replacements'   => $replacementRules,
        'remove_texts'   => $removeTexts,
        'title_template' => trim((string)($data['title_template'] ?? '')),
        'content_prepend'=> (string)($data['content_prepend'] ?? ''),
        'content_append' => (string)($data['content_append'] ?? ''),
        'remove_selectors'=> trim((string)($data['remove_selectors'] ?? '')),
        'trim_before_text'=> trim((string)($data['trim_before_text'] ?? '')),
        'trim_after_text'=> trim((string)($data['trim_after_text'] ?? '')),
        'auto_tags'      => $autoTags,
        'site_default_category_id' => (int)($data['site_default_category_id'] ?? 0),
        'site_default_status' => in_array($data['site_default_status'] ?? '', ['draft', 'published'], true) ? $data['site_default_status'] : '',
        'site_default_author_id' => (int)($data['site_default_author_id'] ?? 0),
        'skip_image_contains' => trim((string)($data['skip_image_contains'] ?? '')),
        'allowed_image_domains'=> trim((string)($data['allowed_image_domains'] ?? '')),
        'min_image_width' => (int)($data['min_image_width'] ?? 0),
        'download_link_replacements' => $downloadLinkRules,
        'skip_download_domains' => trim((string)($data['skip_download_domains'] ?? '')),
        'detect_author_enabled' => isset($data['detect_author_enabled']),
        'detect_author_labels' => trim((string)($data['detect_author_labels'] ?? 'author,authors,credit,credits')),
        'detect_version_enabled' => isset($data['detect_version_enabled']),
        'detect_version_pattern' => trim((string)($data['detect_version_pattern'] ?? '1\\.(?:[3-9]\\d|[1-9]\\d{2,})')),
    ], JSON_UNESCAPED_UNICODE);

    $name = trim($data['name'] ?? '');
    $baseUrl = rtrim(trim($data['base_url'] ?? ''), '/');
    $description = trim($data['description'] ?? '');
    $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';

    if ($id && $id > 0) {
        $stmt = $pdo->prepare("UPDATE bot_sites SET name=?, base_url=?, description=?, status=?, selectors=?, settings=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $baseUrl, $description, $status, $selectors, $settings, $id]);
        return $id;
    } else {
        $slug = slugify($name) ?: 'site-' . time();
        $stmt = $pdo->prepare("INSERT INTO bot_sites (name, slug, base_url, description, status, selectors, settings, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$name, $slug, $baseUrl, $description, $status, $selectors, $settings]);
        return (int)$pdo->lastInsertId();
    }
}

function deleteScraperSite(?PDO $pdo, int $id): bool
{
    if (!$pdo) return false;
    try {
        $pdo->prepare("DELETE FROM bot_sites WHERE id = ?")->execute([$id]);
        return true;
    } catch (Throwable $e) { return false; }
}

// ─── Category Mappings CRUD ───────────────────────────────────────

function getScraperMappings(?PDO $pdo, ?int $siteId = null): array
{
    if (!$pdo) return [];
    try {
        $sql = "SELECT m.*, s.name AS site_name, c.name AS local_category_name
                FROM bot_category_mappings m
                LEFT JOIN bot_sites s ON m.bot_site_id = s.id
                LEFT JOIN categories c ON m.local_category_id = c.id";
        if ($siteId) {
            $sql .= " WHERE m.bot_site_id = ?";
            $stmt = $pdo->prepare($sql . " ORDER BY m.remote_category_name");
            $stmt->execute([$siteId]);
        } else {
            $stmt = $pdo->query($sql . " ORDER BY s.name, m.remote_category_name");
        }
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

function saveScraperMapping(?PDO $pdo, array $data, ?int $id = null): int
{
    if (!$pdo) return 0;
    $siteId = (int)($data['bot_site_id'] ?? 0);
    $remoteName = trim($data['remote_category_name'] ?? '');
    $remoteUrl = trim($data['remote_category_url'] ?? '');
    $localCatId = (int)($data['local_category_id'] ?? 0) ?: null;
    $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';

    if ($id && $id > 0) {
        $stmt = $pdo->prepare("UPDATE bot_category_mappings SET remote_category_name=?, remote_category_url=?, local_category_id=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$remoteName, $remoteUrl, $localCatId, $status, $id]);
        return $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO bot_category_mappings (bot_site_id, remote_category_name, remote_category_url, local_category_id, status, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$siteId, $remoteName, $remoteUrl, $localCatId, $status]);
        return (int)$pdo->lastInsertId();
    }
}

function deleteScraperMapping(?PDO $pdo, int $id): bool
{
    if (!$pdo) return false;
    try {
        $pdo->prepare("DELETE FROM bot_category_mappings WHERE id = ?")->execute([$id]);
        return true;
    } catch (Throwable $e) { return false; }
}

// ─── Jobs CRUD ────────────────────────────────────────────────────

function getScraperJobs(?PDO $pdo, ?int $siteId = null, int $limit = 50): array
{
    if (!$pdo) return [];
    try {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT j.id, j.bot_site_id, j.status, j.total_urls, j.processed_urls, j.failed_urls,
                       j.imported_urls, j.created_at, j.started_at, j.completed_at, s.name AS site_name
                FROM bot_jobs j LEFT JOIN bot_sites s ON j.bot_site_id = s.id";
        if ($siteId) {
            $sql .= " WHERE j.bot_site_id = ?";
            $stmt = $pdo->prepare($sql . " ORDER BY j.created_at DESC LIMIT {$limit}");
            $stmt->execute([$siteId]);
        } else {
            $stmt = $pdo->query($sql . " ORDER BY j.created_at DESC LIMIT {$limit}");
        }
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

function createScraperJob(?PDO $pdo, int $siteId, ?int $mappingId, array $settings, int $totalUrls): int
{
    if (!$pdo) return 0;
    $stmt = $pdo->prepare("INSERT INTO bot_jobs (bot_site_id, bot_category_mapping_id, status, total_urls, settings, created_at, updated_at) VALUES (?,?,'pending',?,?,NOW(),NOW())");
    $stmt->execute([$siteId, $mappingId, $totalUrls, json_encode($settings, JSON_UNESCAPED_UNICODE)]);
    return (int)$pdo->lastInsertId();
}

function updateScraperJob(?PDO $pdo, int $id, array $data): bool
{
    if (!$pdo) return false;
    $sets = [];
    $params = [];
    foreach (['status', 'processed_urls', 'failed_urls', 'imported_urls', 'error_log'] as $k) {
        if (array_key_exists($k, $data)) {
            $sets[] = "{$k} = ?";
            $params[] = $data[$k];
        }
    }
    if (($data['status'] ?? '') === 'running' && !isset($data['started_at'])) {
        $sets[] = "started_at = NOW()";
    }
    if (in_array($data['status'] ?? '', ['completed', 'failed', 'cancelled'])) {
        $sets[] = "completed_at = NOW()";
    }
    $sets[] = "updated_at = NOW()";
    $params[] = $id;
    $pdo->prepare("UPDATE bot_jobs SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    return true;
}

// ─── Imports CRUD ─────────────────────────────────────────────────

function getScraperImports(?PDO $pdo, ?int $siteId = null, ?string $status = null, int $limit = 100): array
{
    if (!$pdo) return [];
    try {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT i.id, i.bot_job_id, i.bot_site_id, i.topic_id, i.source_url,
                       i.source_title, i.translated_title, i.source_images, i.downloaded_images,
                       i.status, i.images_count, i.error_message, i.created_at, i.updated_at,
                       s.name AS site_name
                FROM bot_imports i LEFT JOIN bot_sites s ON i.bot_site_id = s.id WHERE 1=1";
        $params = [];
        if ($siteId) { $sql .= " AND i.bot_site_id = ?"; $params[] = $siteId; }
        if ($status) { $sql .= " AND i.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY i.created_at DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

function getScraperImport(?PDO $pdo, int $id): ?array
{
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("SELECT i.*, s.name AS site_name FROM bot_imports i LEFT JOIN bot_sites s ON i.bot_site_id = s.id WHERE i.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

function getScraperImportBySource(?PDO $pdo, int $siteId, string $sourceUrl): ?array
{
    if (!$pdo || $siteId <= 0 || trim($sourceUrl) === '') return null;
    try {
        $stmt = $pdo->prepare("SELECT i.*, s.name AS site_name
                               FROM bot_imports i
                               LEFT JOIN bot_sites s ON i.bot_site_id = s.id
                               WHERE i.bot_site_id = ? AND i.source_url = ?
                               LIMIT 1");
        $stmt->execute([$siteId, $sourceUrl]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

function getScraperDuplicateImportBySource(?PDO $pdo, int $siteId, string $sourceUrl): ?array
{
    if (!$pdo || $siteId <= 0 || trim($sourceUrl) === '') return null;
    try {
        $stmt = $pdo->prepare("SELECT i.*, s.name AS site_name
                               FROM bot_imports i
                               INNER JOIN topics t ON t.id = i.topic_id
                               LEFT JOIN bot_sites s ON i.bot_site_id = s.id
                               WHERE i.bot_site_id = ? AND i.source_url = ?
                               LIMIT 1");
        $stmt->execute([$siteId, $sourceUrl]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

function getScraperImportsBySourceBatch(?PDO $pdo, int $siteId, array $sourceUrls): array
{
    if (!$pdo || $siteId <= 0 || empty($sourceUrls)) return [];
    try {
        $placeholders = implode(',', array_fill(0, count($sourceUrls), '?'));
        $stmt = $pdo->prepare("SELECT i.*, s.name AS site_name
                               FROM bot_imports i
                               LEFT JOIN bot_sites s ON i.bot_site_id = s.id
                               WHERE i.bot_site_id = ? AND i.source_url IN ({$placeholders})");
        $params = array_merge([$siteId], $sourceUrls);
        $stmt->execute($params);
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['source_url']] = $row;
        }
        return $results;
    } catch (Throwable $e) { return []; }
}

function getScraperDuplicateImportsBySourceBatch(?PDO $pdo, int $siteId, array $sourceUrls): array
{
    if (!$pdo || $siteId <= 0 || empty($sourceUrls)) return [];
    try {
        $placeholders = implode(',', array_fill(0, count($sourceUrls), '?'));
        $stmt = $pdo->prepare("SELECT i.*, s.name AS site_name
                               FROM bot_imports i
                               INNER JOIN topics t ON t.id = i.topic_id
                               LEFT JOIN bot_sites s ON i.bot_site_id = s.id
                               WHERE i.bot_site_id = ? AND i.source_url IN ({$placeholders})");
        $params = array_merge([$siteId], $sourceUrls);
        $stmt->execute($params);
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['source_url']] = $row;
        }
        return $results;
    } catch (Throwable $e) { return []; }
}

function resolveScraperDuplicateImportAction(?array $existingImport, string $strategy): array
{
    if (!$existingImport) {
        return [
            'action' => 'create',
            'should_scrape' => true,
            'import_id' => 0,
            'warning' => '',
        ];
    }

    $strategy = in_array($strategy, ['skip', 'update', 'draft'], true) ? $strategy : 'skip';
    $importId = (int)($existingImport['id'] ?? 0);

    if ($strategy === 'update') {
        return [
            'action' => 'update',
            'should_scrape' => true,
            'import_id' => $importId,
            'warning' => 'Bu konu daha önce çekilmiş; mevcut import kaydı güncellenecek.',
        ];
    }

    if ($strategy === 'draft') {
        return [
            'action' => 'draft',
            'should_scrape' => true,
            'import_id' => 0,
            'warning' => 'Bu konu daha önce çekilmiş; yeni taslak import kopyası oluşturulacak.',
        ];
    }

    return [
        'action' => 'skip',
        'should_scrape' => false,
        'import_id' => $importId,
        'warning' => 'Daha önce çekildi; tekrar import edilmedi.',
    ];
}

function getScraperImportSiteDefaults(?PDO $pdo, array $import): array
{
    if (!$pdo) return ['category_id' => 0, 'status' => '', 'author_id' => 0];

    $categoryId = 0;
    if (!empty($import['bot_job_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT m.local_category_id
                                   FROM bot_jobs j
                                   LEFT JOIN bot_category_mappings m ON m.id = j.bot_category_mapping_id
                                   WHERE j.id = ?
                                   LIMIT 1");
            $stmt->execute([(int)$import['bot_job_id']]);
            $categoryId = (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    $status = '';
    $authorId = 0;
    try {
        $site = getScraperSite($pdo, (int)($import['bot_site_id'] ?? 0));
        $siteSettings = json_decode($site['settings'] ?? '{}', true) ?: [];
        if (!$categoryId) {
            $categoryId = (int)($siteSettings['site_default_category_id'] ?? 0);
        }
        if (!empty($siteSettings['site_default_status']) && in_array($siteSettings['site_default_status'], ['published', 'draft'], true)) {
            $status = $siteSettings['site_default_status'];
        }
        $authorId = (int)($siteSettings['site_default_author_id'] ?? 0);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return ['category_id' => $categoryId, 'status' => $status, 'author_id' => $authorId];
}

function createScraperImport(?PDO $pdo, array $data): int
{
    if (!$pdo) return 0;
    $stmt = $pdo->prepare("INSERT INTO bot_imports (bot_job_id, bot_site_id, source_url, source_title, translated_title, author_topic, topic_version, source_content, translated_content, source_images, downloaded_images, source_download_links, status, images_count, error_message, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([
        $data['bot_job_id'] ?? null,
        $data['bot_site_id'] ?? 0,
        $data['source_url'] ?? '',
        $data['source_title'] ?? null,
        $data['translated_title'] ?? null,
        $data['author_topic'] ?? null,
        $data['topic_version'] ?? null,
        $data['source_content'] ?? null,
        $data['translated_content'] ?? null,
        $data['source_images'] ?? null,
        $data['downloaded_images'] ?? null,
        $data['source_download_links'] ?? null,
        $data['status'] ?? 'pending',
        $data['images_count'] ?? 0,
        $data['error_message'] ?? null,
    ]);
    return (int)$pdo->lastInsertId();
}

function updateScraperImport(?PDO $pdo, int $id, array $data): bool
{
    if (!$pdo) return false;
    $sets = [];
    $params = [];
    $allowed = ['bot_job_id', 'source_title', 'translated_title', 'author_topic', 'topic_version', 'source_content', 'translated_content', 'source_images', 'downloaded_images', 'source_download_links', 'status', 'images_count', 'error_message'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $sets[] = "{$key} = ?";
            $params[] = $data[$key];
        }
    }
    if (empty($sets)) return true;
    
    $sets[] = "updated_at = NOW()";
    $params[] = $id;
    
    try {
        $pdo->prepare("UPDATE bot_imports SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        return true;
    } catch (Throwable $e) { return false; }
}

function deleteScraperImport(?PDO $pdo, int $id): bool
{
    if (!$pdo) return false;
    try {
        $pdo->prepare("DELETE FROM bot_imports WHERE id = ?")->execute([$id]);
        return true;
    } catch (Throwable $e) { return false; }
}

function scraperStripAuthorMetadataLines(string $content, array $labels): string
{
    $content = trim((string)$content);
    if ($content === '') {
        return '';
    }

    $normalizedLabels = array_values(array_filter(array_map(static function ($label): string {
        $value = trim((string)$label);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }, $labels), static fn(string $label): bool => $label !== ''));
    $normalizedLabels = array_values(array_unique($normalizedLabels));
    if (empty($normalizedLabels)) {
        return $content;
    }

    $escapedLabels = array_map(static fn(string $label): string => preg_quote($label, '/'), $normalizedLabels);
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

function scraperNormalizePublishContent(string $content): string
{
    $content = trim((string)$content);
    if ($content === '') {
        return '';
    }

    $shouldForceCenter = preg_match('~(?:content-align-center|ql-align-center|scraper-content-centered|text-align\s*:\s*center)~i', $content) === 1;
    $content = preg_replace('/\x{00a0}/u', ' ', $content) ?? $content;

    if (!class_exists('DOMDocument')) {
        $content = preg_replace('~<p[^>]*>\s*(?:&nbsp;|\xC2\xA0|\s|<br\s*/?>)*</p>~iu', '', $content) ?? $content;
        $content = preg_replace('~<div[^>]*>\s*(?:&nbsp;|\xC2\xA0|\s|<br\s*/?>)*</div>~iu', '', $content) ?? $content;
        $content = preg_replace('~(<(?:p|div)[^>]*>)\s*(?:<br\s*/?>\s*)+~iu', '$1', $content) ?? $content;
        $content = preg_replace('~(?:<br\s*/?>\s*)+(</(?:p|div)>)~iu', '$1', $content) ?? $content;
        $content = preg_replace('~(?:<br\s*/?>\s*){2,}~i', '<br>', $content) ?? $content;
        $content = preg_replace('~^\s*(?:<br\s*/?>\s*)+~i', '', $content) ?? $content;
        $content = preg_replace('~(?:<br\s*/?>\s*)+\s*$~i', '', $content) ?? $content;
        if ($shouldForceCenter) {
            $content = preg_replace_callback(
                '~<p([^>]*)>~iu',
                static function (array $matches): string {
                    $attrs = $matches[1] ?? '';
                    if (preg_match('~\bstyle\s*=\s*(["\'])(.*?)\1~iu', $attrs, $styleMatch) === 1) {
                        $style = preg_replace('~(?:^|;)\s*text-align\s*:[^;]*~iu', '', $styleMatch[2] ?? '') ?? ($styleMatch[2] ?? '');
                        $style = trim((string)$style, " ;\t\n\r\0\x0B");
                        $style = $style === '' ? 'text-align:center' : ($style . '; text-align:center');
                        return preg_replace(
                            '~\bstyle\s*=\s*(["\'])(.*?)\1~iu',
                            'style="' . $style . '"',
                            $matches[0],
                            1
                        ) ?? $matches[0];
                    }
                    return '<p' . $attrs . ' style="text-align:center">';
                },
                $content
            ) ?? $content;
        }
        return trim($content);
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $wrapped = '<div id="scraper-normalize-root">' . $content . '</div>';
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    if (!$loaded) {
        return trim($content);
    }

    $root = $doc->getElementById('scraper-normalize-root');
    if (!$root instanceof DOMElement) {
        return trim($content);
    }

    $normalizeText = static function (string $value): string {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xC2\xA0", ' ', $decoded);
        $decoded = preg_replace('/[\s\p{Zs}]+/u', ' ', $decoded) ?? $decoded;
        return trim($decoded);
    };
    $hasRichContent = static function (DOMElement $element): bool {
        foreach (['img', 'iframe', 'video', 'audio', 'table', 'ul', 'ol', 'li', 'pre', 'blockquote', 'code', 'hr'] as $tag) {
            if ($element->getElementsByTagName($tag)->length > 0) {
                return true;
            }
        }
        return false;
    };

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('.//*[self::p or self::div or self::span]', $root);
    if ($nodes instanceof DOMNodeList) {
        for ($index = $nodes->length - 1; $index >= 0; $index--) {
            $node = $nodes->item($index);
            if (!$node instanceof DOMElement || !$node->parentNode) {
                continue;
            }

            $text = $normalizeText($node->textContent ?? '');
            $hasMeaningfulChildren = false;
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) !== 'br') {
                    $hasMeaningfulChildren = true;
                    break;
                }
            }

            if ($text === '' && !$hasMeaningfulChildren && !$hasRichContent($node)) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    $trimBreakEdges = static function (DOMElement $element) use (&$trimBreakEdges): void {
        while ($element->firstChild instanceof DOMText && trim((string)$element->firstChild->textContent) === '') {
            $element->removeChild($element->firstChild);
        }
        while ($element->firstChild instanceof DOMElement && strtolower($element->firstChild->tagName) === 'br') {
            $element->removeChild($element->firstChild);
        }

        while ($element->lastChild instanceof DOMText && trim((string)$element->lastChild->textContent) === '') {
            $element->removeChild($element->lastChild);
        }
        while ($element->lastChild instanceof DOMElement && strtolower($element->lastChild->tagName) === 'br') {
            $element->removeChild($element->lastChild);
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'div'], true)) {
                $trimBreakEdges($child);
            }
        }
    };
    $trimBreakEdges($root);

    $paragraphs = $xpath->query('.//p', $root);
    if ($paragraphs instanceof DOMNodeList) {
        foreach (iterator_to_array($paragraphs) as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }

            $style = trim((string)$paragraph->getAttribute('style'));
            $rules = [];
            if ($style !== '') {
                foreach (array_filter(array_map('trim', explode(';', $style))) as $declaration) {
                    $parts = explode(':', $declaration, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }
                    $property = strtolower(trim($parts[0]));
                    if (str_starts_with($property, 'margin') || ($shouldForceCenter && $property === 'text-align')) {
                        continue;
                    }
                    $rules[] = $property . ':' . trim($parts[1]);
                }
            }
            $rules[] = 'margin:0';
            if ($shouldForceCenter) {
                $rules[] = 'text-align:center';
            }
            $paragraph->setAttribute('style', implode('; ', array_values(array_unique($rules))));
        }
    }

    $normalized = '';
    foreach ($root->childNodes as $child) {
        $normalized .= $doc->saveHTML($child);
    }

    $normalized = preg_replace('~</p>\s*(?:<br\s*/?>\s*)+<p~i', '</p><p', $normalized) ?? $normalized;
    $normalized = preg_replace('~(<(?:p|div)[^>]*>)\s*(?:<br\s*/?>\s*)+~iu', '$1', $normalized) ?? $normalized;
    $normalized = preg_replace('~(?:<br\s*/?>\s*)+(</(?:p|div)>)~iu', '$1', $normalized) ?? $normalized;
    $normalized = preg_replace('~(?:<br\s*/?>\s*){2,}~i', '<br>', $normalized) ?? $normalized;
    $normalized = preg_replace('~^\s*(?:<br\s*/?>\s*)+~i', '', $normalized) ?? $normalized;
    $normalized = preg_replace('~(?:<br\s*/?>\s*)+\s*$~i', '', $normalized) ?? $normalized;

    return trim($normalized);
}

function publishScraperImport(?PDO $pdo, int $importId, int $categoryId, string $publishStatus = 'draft')
{
    if (!$pdo) {
        return false;
    }

    $import = getScraperImport($pdo, $importId);
    if (!$import) {
        return false;
    }

    if (($import['status'] ?? '') === 'imported' && (int)($import['topic_id'] ?? 0) > 0) {
        try {
            $stmt = $pdo->prepare("SELECT slug FROM topics WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$import['topic_id']]);
            $existingSlug = (string)($stmt->fetchColumn() ?: '');
            if ($existingSlug !== '') {
                return $existingSlug;
            }
        } catch (Throwable $e) {
            error_log('[silent-catch] ' . $e->getMessage());
        }
    }

    $title = $import['translated_title'] ?: $import['source_title'] ?: ('İçerik #' . $importId);
    $slug = generateUniqueSlug($pdo, $title, 'topics');
    $downloadLinks = $import['source_download_links'] ?? '';
    $botSettings = getScraperBotSettings($pdo);
    $site = getScraperSite($pdo, (int)$import['bot_site_id']);
    $siteSettings = json_decode($site['settings'] ?? '{}', true) ?: [];
    $authorLabelsRaw = (string)($siteSettings['detect_author_labels'] ?? ($botSettings['bot_detect_author_labels'] ?? 'author,authors,credit,credits'));
    $authorLabels = array_values(array_filter(array_map(static fn($label): string => trim((string)$label), preg_split('/[,\n]+/', $authorLabelsRaw) ?: []), static fn(string $label): bool => $label !== ''));
    $contentRaw = (string)($import['translated_content'] ?: $import['source_content'] ?: '');
    $content = scraperNormalizePublishContent(scraperStripAuthorMetadataLines($contentRaw, $authorLabels));
    $siteSlug = trim((string)($site['slug'] ?? ''));
    if ($siteSlug === '') {
        $siteSlug = 'site-' . (int)$import['bot_site_id'];
    }
    $siteSlug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($siteSlug)) ?: ('site-' . (int)$import['bot_site_id']);
    $downloadImages = ((string)($botSettings['bot_download_images'] ?? '1') === '1');
    $sourceScheme = (string)(parse_url((string)($import['source_url'] ?? ''), PHP_URL_SCHEME) ?: 'https');

    $images = $import['downloaded_images'] ?: $import['source_images'] ?: '';
    $imageLines = array_values(array_unique(array_filter(array_map('trim', explode("\n", $images)))));
    $resolveProxyImageUrl = static function (string $path): string {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('~(?:^|/)(?:api/)?scraper-image\.php(?:\?|$)~i', $trimmed) === 1) {
            $query = (string)(parse_url($trimmed, PHP_URL_QUERY) ?? '');
            if ($query !== '') {
                parse_str($query, $params);
                if (!empty($params['url']) && is_string($params['url'])) {
                    $decoded = trim((string)$params['url']);
                    if ($decoded !== '') {
                        return $decoded;
                    }
                }
            }
        }

        return $trimmed;
    };
    $normalizeRemoteUrl = static function (string $url, string $fallbackScheme): string {
        $value = trim($url);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '//')) {
            return $fallbackScheme . ':' . $value;
        }
        return $value;
    };

    $mediaItems = [];
    $downloadCache = [];
    $engine = null;
    $topicSlugForMedia = $slug !== '' ? $slug : (slugify($title) ?: ('topic-' . $importId));

    foreach ($imageLines as $index => $imagePath) {
        $resolvedImagePath = $resolveProxyImageUrl((string)$imagePath);
        if ($resolvedImagePath === '' || preg_match('~^data:image/~i', $resolvedImagePath) === 1) {
            continue;
        }

        $remoteUrl = $normalizeRemoteUrl($resolvedImagePath, $sourceScheme);
        $isRemote = preg_match('~^https?://~i', $remoteUrl) === 1;

        if ($isRemote && $downloadImages) {
            if (!array_key_exists($remoteUrl, $downloadCache)) {
                if (!$engine instanceof ScraperEngine) {
                    $engine = new ScraperEngine($botSettings);
                }
                $downloadCache[$remoteUrl] = $engine->downloadImage($remoteUrl, $siteSlug, $topicSlugForMedia, $index + 1);
            }

            $localPath = trim((string)($downloadCache[$remoteUrl] ?? ''));
            if ($localPath !== '') {
                $mediaItems[] = [
                    'path' => $localPath,
                    'disk' => 'local',
                    'mime_type' => null,
                ];
                continue;
            }
        }

        if ($isRemote) {
            $mediaItems[] = [
                'path' => $remoteUrl,
                'disk' => 'remote',
                'mime_type' => 'image/remote',
            ];
            continue;
        }

        $mediaItems[] = [
            'path' => $resolvedImagePath,
            'disk' => 'local',
            'mime_type' => null,
        ];
    }

    $storedImageLines = implode("\n", array_values(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['path'] ?? '')),
        $mediaItems
    ))));

    $siteAuthorId = (int)($siteSettings['site_default_author_id'] ?? 0);
    $authorId = $siteAuthorId ?: ((int)($_SESSION['_auth_user_id'] ?? 0) ?: max(1, (int)($botSettings['bot_default_author_id'] ?? 1)));
    $publishDateMode = $botSettings['bot_publish_date_mode'] ?? 'now';
    $publishedAt = null;
    if ($publishStatus === 'published' && $publishDateMode !== 'empty') {
        $publishedAt = date('Y-m-d H:i:s');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO topics (category_id, author_id, title, slug, author_topic, topic_version, topic_descriptions, topic_download_links, status, created_at, published_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)");
        $stmt->execute([
            $categoryId,
            $authorId,
            $title,
            $slug,
            ($import['author_topic'] ?? '') ?: null,
            ($import['topic_version'] ?? '') ?: null,
            $content,
            $downloadLinks !== '' ? $downloadLinks : null,
            $publishStatus,
            $publishedAt,
        ]);
        $topicId = (int)$pdo->lastInsertId();

        $primaryMediaId = null;
        $stmtMedia = $pdo->prepare("INSERT INTO media_files (topic_id, user_id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at)
                                    VALUES (?, NULL, 'image', ?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())");
        foreach ($mediaItems as $index => $mediaItem) {
            $imagePath = trim((string)($mediaItem['path'] ?? ''));
            if ($imagePath === '') {
                continue;
            }
            $disk = ((string)($mediaItem['disk'] ?? 'local') === 'remote') ? 'remote' : 'local';
            $mimeType = $mediaItem['mime_type'] ?? null;
            $originalName = basename(parse_url($imagePath, PHP_URL_PATH) ?: $imagePath);
            if ($originalName === '') {
                $originalName = 'image-' . ($index + 1);
            }
            $stmtMedia->execute([
                $topicId,
                $disk,
                $imagePath,
                $originalName,
                $mimeType,
                $index,
                $index === 0 ? 1 : 0,
            ]);
            if ($index === 0) {
                $primaryMediaId = (int)$pdo->lastInsertId();
            }
        }

        if ($primaryMediaId !== null) {
            $pdo->prepare("UPDATE topics SET primary_media_file_id = ? WHERE id = ?")->execute([$primaryMediaId, $topicId]);
        }

        if ($downloadLinks !== '') {
            syncTopicDownloadLinks($pdo, $topicId, $downloadLinks);
        }

        $pdo->prepare("UPDATE bot_imports SET topic_id=?, status='imported', downloaded_images=?, images_count=?, updated_at=NOW() WHERE id=?")->execute([
            $topicId,
            $storedImageLines !== '' ? $storedImageLines : null,
            count($mediaItems),
            $importId,
        ]);
        $pdo->prepare("UPDATE bot_sites SET total_imports = total_imports + 1 WHERE id = ?")->execute([$import['bot_site_id']]);

        $pdo->commit();
        logActivity($pdo, 'bot_import_published', 'topic', $topicId, ['import_id' => $importId, 'source' => $import['source_url']]);
        return $slug;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Scraper Import Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return false;
    }
}

// ─── Stats ────────────────────────────────────────────────────────

function getScraperStats(?PDO $pdo): array
{
    $stats = ['sites' => 0, 'mappings' => 0, 'jobs' => 0, 'imports' => 0, 'imported' => 0, 'pending' => 0];
    if (!$pdo) return $stats;
    try {
        $row = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM bot_sites) AS sites,
                (SELECT COUNT(*) FROM bot_category_mappings) AS mappings,
                (SELECT COUNT(*) FROM bot_jobs) AS jobs,
                (SELECT COUNT(*) FROM bot_imports) AS imports,
                (SELECT COUNT(*) FROM bot_imports WHERE status = 'imported' AND topic_id IS NOT NULL) AS imported,
                (SELECT COUNT(*) FROM bot_imports WHERE status = 'pending') AS pending
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (array_keys($stats) as $key) {
            $stats[$key] = (int)($row[$key] ?? 0);
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    return $stats;
}
