<?php

declare(strict_types=1);

function legacyRedirectDefaultSettings(): array
{
    return [
        'enabled' => '1',
        'minimum_score' => '75',
        'low_score_mode' => 'redirect',
    ];
}

function legacyRedirectParsePath(string $path): ?array
{
    $rawPath = (string) parse_url($path, PHP_URL_PATH);
    $rawPath = '/' . trim($rawPath, '/');
    $baseUri = '/' . trim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    if ($baseUri !== '/' && str_starts_with($rawPath . '/', $baseUri . '/')) {
        $rawPath = '/' . ltrim(substr($rawPath, strlen($baseUri)), '/');
    }

    $patterns = [
        'topic' => '#^/konu/([a-zA-Z0-9._-]+)\.(\d+)/?$#',
        'category' => '#^/forums/([a-zA-Z0-9._-]+)\.(\d+)/?$#',
    ];

    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $rawPath, $matches) !== 1) {
            continue;
        }

        $slug = trim((string) $matches[1], '.-/');
        if ($slug === '') {
            return null;
        }

        return [
            'type' => $type,
            'slug' => $slug,
            'legacy_id' => (int) $matches[2],
            'path' => $rawPath . (str_ends_with($rawPath, '/') ? '' : '/'),
        ];
    }

    return null;
}

function legacyRedirectNormalizeRequestPath(string $path): string
{
    $rawPath = (string) parse_url($path, PHP_URL_PATH);
    $rawPath = '/' . trim($rawPath, '/');
    $baseUri = '/' . trim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    if ($baseUri !== '/' && str_starts_with($rawPath . '/', $baseUri . '/')) {
        $rawPath = '/' . ltrim(substr($rawPath, strlen($baseUri)), '/');
    }

    return $rawPath;
}

function legacyRedirectParseRoutedPath(string $path, ?array $routes = null): ?array
{
    $rawPath = legacyRedirectNormalizeRequestPath($path);
    $routes = $routes ?: (function_exists('routePrefixSettings') ? routePrefixSettings($GLOBALS['pdo'] ?? null) : ['topic' => 'konu', 'category' => 'kategori']);

    foreach (['topic', 'category'] as $type) {
        $prefix = trim((string) ($routes[$type] ?? ''), '/');
        if ($prefix === '') {
            continue;
        }

        $pattern = '#^/' . preg_quote($prefix, '#') . '/([a-zA-Z0-9._-]+)/?$#';
        if (preg_match($pattern, $rawPath, $matches) !== 1) {
            continue;
        }

        $slug = trim((string) $matches[1]);
        if ($slug === '') {
            return null;
        }

        return [
            'type' => $type,
            'slug' => $slug,
            'legacy_id' => null,
            'path' => $rawPath . (str_ends_with($rawPath, '/') ? '' : '/'),
        ];
    }

    return null;
}

function legacyRedirectNormalizeText(string $text): string
{
    if (function_exists('slugify')) {
        $text = str_replace(['.', '_'], ' ', $text);
        $text = str_replace('-', ' ', slugify($text));
    } else {
        $text = mb_strtolower(str_replace(['.', '_', '-'], ' ', $text), 'UTF-8');
    }

    $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return trim($text);
}

function legacyRedirectScoreCandidate(string $needle, array $candidate): int
{
    $source = legacyRedirectNormalizeText($needle);
    $slug = legacyRedirectNormalizeText((string) ($candidate['slug'] ?? ''));
    $title = legacyRedirectNormalizeText((string) ($candidate['title'] ?? $candidate['name'] ?? ''));
    $haystack = trim($slug . ' ' . $title);

    if ($source === '' || $haystack === '') {
        return 0;
    }

    similar_text($source, $slug, $slugPercent);
    similar_text($source, $title, $titlePercent);

    $sourceTokens = array_values(array_unique(array_filter(explode(' ', $source))));
    $haystackTokens = array_values(array_unique(array_filter(explode(' ', $haystack))));
    $overlap = count(array_intersect($sourceTokens, $haystackTokens));
    $tokenPercent = count($sourceTokens) > 0 ? ($overlap / count($sourceTokens)) * 100 : 0;

    return (int) round(max($slugPercent, $titlePercent, $tokenPercent));
}

function legacyRedirectPickBestCandidate(string $legacySlug, array $candidates): ?array
{
    $best = null;
    $bestScore = -1;

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $score = legacyRedirectScoreCandidate($legacySlug, $candidate);
        if ($score > $bestScore) {
            $best = $candidate;
            $best['score'] = $score;
            $bestScore = $score;
        }
    }

    return $best;
}

function legacyRedirectShouldRedirectScore(int $score, array $settings): bool
{
    if (($settings['enabled'] ?? '1') !== '1') {
        return false;
    }

    $minimumScore = max(0, min(100, (int) ($settings['minimum_score'] ?? 75)));
    if ($score >= $minimumScore) {
        return true;
    }

    return ($settings['low_score_mode'] ?? 'redirect') === 'redirect';
}

function ensureLegacyRedirectSchema(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS legacy_redirect_rules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_path VARCHAR(1024) NOT NULL,
        source_type ENUM('topic','category') NOT NULL,
        legacy_id BIGINT UNSIGNED NULL,
        legacy_slug VARCHAR(500) NOT NULL,
        target_type ENUM('topic','category','url') NULL,
        target_id BIGINT UNSIGNED NULL,
        target_url VARCHAR(1024) NULL,
        match_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('active','pending','ignored') NOT NULL DEFAULT 'active',
        is_manual TINYINT(1) NOT NULL DEFAULT 0,
        hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_hit_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY legacy_redirect_rules_source_unique (source_path(255)),
        INDEX legacy_redirect_rules_status_index (status),
        INDEX legacy_redirect_rules_type_score_index (source_type, match_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS legacy_redirect_hits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        rule_id BIGINT UNSIGNED NULL,
        source_path VARCHAR(1024) NOT NULL,
        source_type ENUM('topic','category') NULL,
        target_url VARCHAR(1024) NULL,
        match_score TINYINT UNSIGNED NULL,
        result ENUM('redirected','pending','ignored','not_found','disabled') NOT NULL,
        referrer VARCHAR(2048) NULL,
        ip_address VARCHAR(255) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP NULL,
        INDEX legacy_redirect_hits_source_index (source_path(255)),
        INDEX legacy_redirect_hits_result_created_index (result, created_at),
        CONSTRAINT legacy_redirect_hits_rule_id_foreign FOREIGN KEY (rule_id) REFERENCES legacy_redirect_rules(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    legacyRedirectSeedDefaultSettings($pdo);
}

function legacyRedirectSeedDefaultSettings(PDO $pdo): void
{
    $defaults = legacyRedirectDefaultSettings();
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
        VALUES (:key, :value, :type, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `key` = `key`");

    foreach ($defaults as $name => $value) {
        $stmt->execute([
            'key' => 'legacy_redirect_' . $name,
            'value' => $value,
            'type' => $name === 'minimum_score' ? 'number' : ($name === 'enabled' ? 'bool' : 'string'),
        ]);
    }
}

function legacyRedirectGetSettings(?PDO $pdo): array
{
    $settings = legacyRedirectDefaultSettings();
    if (!$pdo) {
        return $settings;
    }

    try {
        $stmt = $pdo->query("SELECT `key`, value FROM settings WHERE `key` LIKE 'legacy_redirect_%'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = preg_replace('/^legacy_redirect_/', '', (string) $row['key']);
            if (array_key_exists($key, $settings)) {
                $settings[$key] = (string) ($row['value'] ?? '');
            }
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return $settings;
}

function legacyRedirectSaveSettings(?PDO $pdo, array $input): void
{
    if (!$pdo) {
        return;
    }

    $settings = [
        'enabled' => isset($input['enabled']) ? '1' : '0',
        'minimum_score' => (string) max(0, min(100, (int) ($input['minimum_score'] ?? 75))),
        'low_score_mode' => in_array(($input['low_score_mode'] ?? 'redirect'), ['redirect', 'review'], true)
            ? (string) $input['low_score_mode']
            : 'redirect',
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
        VALUES (:key, :value, :type, NOW(), NOW())
        ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type), updated_at = NOW()");

    foreach ($settings as $key => $value) {
        $stmt->execute([
            'key' => 'legacy_redirect_' . $key,
            'value' => $value,
            'type' => $key === 'minimum_score' ? 'number' : ($key === 'enabled' ? 'bool' : 'string'),
        ]);
    }
}

function legacyRedirectFindStoredRule(?PDO $pdo, string $sourcePath): ?array
{
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM legacy_redirect_rules WHERE source_path = ? LIMIT 1");
    $stmt->execute([$sourcePath]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    return $rule ?: null;
}

function legacyRedirectFetchCandidates(?PDO $pdo, string $type, string $legacySlug): array
{
    if (!$pdo) {
        return [];
    }

    $terms = array_values(array_filter(explode(' ', legacyRedirectNormalizeText($legacySlug))));
    $likeTerms = array_slice($terms, 0, 6);

    if ($type === 'category') {
        $sql = "SELECT id, slug, name AS title FROM categories WHERE deleted_at IS NULL AND status = 'active'";
        $params = [];
        if (!empty($likeTerms)) {
            $likes = [];
            foreach ($likeTerms as $index => $term) {
                $slugKey = 't' . $index . '_slug';
                $nameKey = 't' . $index . '_name';
                $likes[] = "(slug LIKE :{$slugKey} OR name LIKE :{$nameKey})";
                $params[$slugKey] = '%' . $term . '%';
                $params[$nameKey] = '%' . $term . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $likes) . ')';
        }
        $sql .= ' ORDER BY display_order ASC, name ASC LIMIT 100';
    } else {
        $sql = "SELECT id, slug, title FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved')";
        $params = [];
        if (!empty($likeTerms)) {
            $likes = [];
            foreach ($likeTerms as $index => $term) {
                $slugKey = 't' . $index . '_slug';
                $titleKey = 't' . $index . '_title';
                $likes[] = "(slug LIKE :{$slugKey} OR title LIKE :{$titleKey})";
                $params[$slugKey] = '%' . $term . '%';
                $params[$titleKey] = '%' . $term . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $likes) . ')';
        }
        $sql .= ' ORDER BY published_at DESC, id DESC LIMIT 150';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!empty($rows)) {
        return $rows;
    }

    $fallbackSql = $type === 'category'
        ? "SELECT id, slug, name AS title FROM categories WHERE deleted_at IS NULL AND status = 'active' ORDER BY display_order ASC, name ASC LIMIT 100"
        : "SELECT id, slug, title FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') ORDER BY published_at DESC, id DESC LIMIT 150";

    return $pdo->query($fallbackSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function legacyRedirectTargetUrl(string $type, array $candidate): string
{
    $slug = (string) ($candidate['slug'] ?? '');

    return $type === 'category' ? categoryUrl($slug) : topicUrlForRow($candidate);
}

function legacyRedirectCreateOrUpdateRule(?PDO $pdo, array $parsed, ?array $candidate, array $settings): ?array
{
    if (!$pdo) {
        return null;
    }

    $score = (int) ($candidate['score'] ?? 0);
    $shouldRedirect = $candidate && legacyRedirectShouldRedirectScore($score, $settings);
    $status = $candidate ? ($shouldRedirect ? 'active' : 'pending') : 'pending';
    $targetUrl = $candidate ? legacyRedirectTargetUrl((string) $parsed['type'], $candidate) : null;
    $targetId = $candidate ? (int) ($candidate['id'] ?? 0) : null;

    $stmt = $pdo->prepare("INSERT INTO legacy_redirect_rules
        (source_path, source_type, legacy_id, legacy_slug, target_type, target_id, target_url, match_score, status, is_manual, created_at, updated_at)
        VALUES (:source_path, :source_type, :legacy_id, :legacy_slug, :target_type, :target_id, :target_url, :match_score, :status, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE target_type = VALUES(target_type), target_id = VALUES(target_id), target_url = VALUES(target_url),
            match_score = VALUES(match_score), status = IF(is_manual = 1, status, VALUES(status)), updated_at = NOW()");

    $stmt->execute([
        'source_path' => $parsed['path'],
        'source_type' => $parsed['type'],
        'legacy_id' => $parsed['legacy_id'],
        'legacy_slug' => $parsed['slug'],
        'target_type' => $candidate ? $parsed['type'] : null,
        'target_id' => $targetId ?: null,
        'target_url' => $targetUrl,
        'match_score' => $score,
        'status' => $status,
    ]);

    return legacyRedirectFindStoredRule($pdo, (string) $parsed['path']);
}

function legacyRedirectResolveParsed(?PDO $pdo, array $parsed): array
{
    ensureLegacyRedirectSchema($pdo);
    $settings = legacyRedirectGetSettings($pdo);
    if (($settings['enabled'] ?? '1') !== '1') {
        legacyRedirectLogHit($pdo, null, (string) $parsed['path'], (string) $parsed['type'], null, null, 'disabled');
        return ['result' => 'disabled', 'redirect' => false, 'target_url' => null, 'rule' => null, 'parsed' => $parsed];
    }

    $rule = legacyRedirectFindStoredRule($pdo, (string) $parsed['path']);
    if (!$rule) {
        $candidate = legacyRedirectPickBestCandidate((string) $parsed['slug'], legacyRedirectFetchCandidates($pdo, (string) $parsed['type'], (string) $parsed['slug']));
        $rule = legacyRedirectCreateOrUpdateRule($pdo, $parsed, $candidate, $settings);
    }

    if (!$rule) {
        legacyRedirectLogHit($pdo, null, (string) $parsed['path'], (string) $parsed['type'], null, null, 'not_found');
        return ['result' => 'not_found', 'redirect' => false, 'target_url' => null, 'rule' => null, 'parsed' => $parsed];
    }

    $status = (string) ($rule['status'] ?? 'pending');
    $targetUrl = trim((string) ($rule['target_url'] ?? ''));
    $score = (int) ($rule['match_score'] ?? 0);

    if ($status === 'active' && $targetUrl !== '' && legacyRedirectIsSafeTargetUrl($targetUrl)) {
        legacyRedirectLogHit($pdo, $rule, (string) $parsed['path'], (string) $parsed['type'], $targetUrl, $score, 'redirected');
        return ['result' => 'redirected', 'redirect' => true, 'target_url' => $targetUrl, 'rule' => $rule, 'parsed' => $parsed];
    }

    $result = $status === 'ignored' ? 'ignored' : ($targetUrl !== '' && !legacyRedirectIsSafeTargetUrl($targetUrl) ? 'unsafe_target' : 'pending');
    legacyRedirectLogHit($pdo, $rule, (string) $parsed['path'], (string) $parsed['type'], $targetUrl ?: null, $score, $result);

    return ['result' => $result, 'redirect' => false, 'target_url' => null, 'rule' => $rule, 'parsed' => $parsed];
}

function legacyRedirectResolveMissingRoutedPath(?PDO $pdo, string $path): array
{
    $parsed = legacyRedirectParseRoutedPath($path);
    if (!$parsed) {
        return ['result' => 'not_found', 'redirect' => false, 'target_url' => null, 'rule' => null, 'parsed' => null];
    }

    return legacyRedirectResolveParsed($pdo, $parsed);
}

function legacyRedirectLogHit(?PDO $pdo, ?array $rule, string $sourcePath, ?string $sourceType, ?string $targetUrl, ?int $score, string $result): void
{
    if (!$pdo) {
        return;
    }

    try {
        if ($rule && !empty($rule['id'])) {
            $pdo->prepare("UPDATE legacy_redirect_rules SET hit_count = hit_count + 1, last_hit_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([(int) $rule['id']]);
        }

        $stmt = $pdo->prepare("INSERT INTO legacy_redirect_hits
            (rule_id, source_path, source_type, target_url, match_score, result, referrer, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $rule['id'] ?? null,
            $sourcePath,
            $sourceType,
            $targetUrl,
            $score,
            $result,
            $_SERVER['HTTP_REFERER'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

function legacyRedirectResolve(?PDO $pdo, string $path): array
{
    $parsed = legacyRedirectParsePath($path);
    if (!$parsed) {
        return ['result' => 'not_found', 'redirect' => false, 'target_url' => null, 'rule' => null, 'parsed' => null];
    }

    return legacyRedirectResolveParsed($pdo, $parsed);
}

function legacyRedirectStats(?PDO $pdo): array
{
    $stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'ignored' => 0, 'hits' => 0];
    if (!$pdo) {
        return $stats;
    }

    try {
        foreach (['total' => '', 'active' => "WHERE status = 'active'", 'pending' => "WHERE status = 'pending'", 'ignored' => "WHERE status = 'ignored'"] as $key => $where) {
            $stats[$key] = (int) $pdo->query("SELECT COUNT(*) FROM legacy_redirect_rules {$where}")->fetchColumn();
        }
        $stats['hits'] = (int) $pdo->query("SELECT COUNT(*) FROM legacy_redirect_hits")->fetchColumn();
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return $stats;
}

function legacyRedirectRecentHits(?PDO $pdo, int $limit = 25, string $result = '', string $type = '', string $search = '', string $sort = 'date', string $dir = 'desc'): array
{
    if (!$pdo) {
        return [];
    }

    try {
        $limit = max(1, min(100, $limit));
        $where = [];
        $params = [];
        if (in_array($result, ['redirected', 'pending', 'ignored', 'not_found', 'disabled'], true)) {
            $where[] = 'h.result = :result';
            $params['result'] = $result;
        }
        if (in_array($type, ['topic', 'category'], true)) {
            $where[] = 'h.source_type = :type';
            $params['type'] = $type;
        }
        if ($search !== '') {
            $where[] = '(h.source_path LIKE :search_path OR h.target_url LIKE :search_target)';
            $searchTerm = '%' . $search . '%';
            $params['search_path'] = $searchTerm;
            $params['search_target'] = $searchTerm;
        }

        $sortColumns = [
            'source' => 'h.source_path',
            'result' => 'h.result',
            'type' => 'h.source_type',
            'score' => 'h.match_score',
            'target' => 'h.target_url',
            'date' => 'h.created_at',
        ];
        $sort = array_key_exists($sort, $sortColumns) ? $sort : 'date';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $column = $sortColumns[$sort];
        $nullLast = in_array($sort, ['score', 'target', 'date'], true) ? "{$column} IS NULL ASC, " : '';
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $pdo->prepare("SELECT h.*, r.status AS rule_status
            FROM legacy_redirect_hits h
            LEFT JOIN legacy_redirect_rules r ON r.id = h.rule_id
            {$whereSql}
            ORDER BY {$nullLast}{$column} {$dir}, h.id DESC
            LIMIT :limit");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function legacyRedirectCreatePrefixImportRules(?PDO $pdo, string $type, string $fromPrefix, int $limit = 500): int
{
    if (!$pdo || !in_array($type, ['topic', 'category', 'profile'], true)) {
        return 0;
    }

    $fromPrefix = routePrefixSanitize($fromPrefix);
    if ($fromPrefix === '') {
        return 0;
    }

    $created = 0;
    $limit = max(1, min(2000, $limit));

    if ($type === 'category') {
        $rows = $pdo->query("SELECT id, slug, name AS title FROM categories WHERE deleted_at IS NULL AND status = 'active' ORDER BY id DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($type === 'profile') {
        $rows = $pdo->query("SELECT id, name AS title FROM users WHERE status = 'active' AND (is_banned = 0 OR is_banned IS NULL) ORDER BY id DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $rows = $pdo->query("SELECT id, slug, title FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') ORDER BY id DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $stmt = $pdo->prepare("INSERT INTO legacy_redirect_rules
        (source_path, source_type, legacy_id, legacy_slug, target_type, target_id, target_url, match_score, status, is_manual, created_at, updated_at)
        VALUES (:source_path, :source_type, :legacy_id, :legacy_slug, :target_type, :target_id, :target_url, 100, 'active', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE target_url = VALUES(target_url), status = 'active', is_manual = 1, updated_at = NOW()");

    foreach ($rows as $row) {
        $slug = $type === 'profile'
            ? publicProfileSlug((int)$row['id'], (string)($row['title'] ?? 'uye'))
            : (string)($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $targetUrl = match ($type) {
            'category' => categoryUrl($slug),
            'profile' => publicProfileUrl(['id' => (int)$row['id'], 'name' => (string)($row['title'] ?? 'uye')]),
            default => topicUrl($slug, (int)$row['id']),
        };

        $stmt->execute([
            'source_path' => '/' . $fromPrefix . '/' . $slug . '/',
            'source_type' => $type === 'profile' ? 'topic' : $type,
            'legacy_id' => (int)$row['id'],
            'legacy_slug' => $slug,
            'target_type' => 'url',
            'target_id' => (int)$row['id'],
            'target_url' => $targetUrl,
        ]);
        $created++;
    }

    return $created;
}

function legacyRedirectRuleFilterClause(string $status = '', string $type = '', string $search = ''): array
{
    $where = [];
    $params = [];
    if (in_array($status, ['active', 'pending', 'ignored'], true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }
    if (in_array($type, ['topic', 'category'], true)) {
        $where[] = 'source_type = :type';
        $params['type'] = $type;
    }
    if ($search !== '') {
        $where[] = '(source_path LIKE :search_path OR legacy_slug LIKE :search_slug OR target_url LIKE :search_target)';
        $searchTerm = '%' . $search . '%';
        $params['search_path'] = $searchTerm;
        $params['search_slug'] = $searchTerm;
        $params['search_target'] = $searchTerm;
    }

    return [$where ? ('WHERE ' . implode(' AND ', $where)) : '', $params];
}

function legacyRedirectCountRules(?PDO $pdo, string $status = '', string $type = '', string $search = ''): int
{
    if (!$pdo) {
        return 0;
    }

    try {
        [$whereSql, $params] = legacyRedirectRuleFilterClause($status, $type, $search);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM legacy_redirect_rules {$whereSql}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function legacyRedirectListRules(?PDO $pdo, string $status = '', string $type = '', string $search = '', string $sort = 'last_hit', string $dir = 'desc', int $limit = 250, int $offset = 0): array
{
    if (!$pdo) {
        return [];
    }

    [$whereSql, $params] = legacyRedirectRuleFilterClause($status, $type, $search);
    $sortColumns = [
        'source' => 'source_path',
        'type' => 'source_type',
        'score' => 'match_score',
        'status' => 'status',
        'hits' => 'hit_count',
        'target' => 'target_url',
        'last_hit' => 'last_hit_at',
    ];
    $sort = array_key_exists($sort, $sortColumns) ? $sort : 'last_hit';
    $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    $column = $sortColumns[$sort];
    $nullLast = in_array($sort, ['target', 'last_hit'], true) ? "{$column} IS NULL ASC, " : '';
    $limit = max(1, min(250, $limit));
    $offset = max(0, $offset);

    $stmt = $pdo->prepare("SELECT * FROM legacy_redirect_rules {$whereSql} ORDER BY {$nullLast}{$column} {$dir}, id DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function legacyRedirectUpdateRule(?PDO $pdo, int $id, string $status, string $targetUrl): void
{
    if (!$pdo || $id <= 0 || !in_array($status, ['active', 'pending', 'ignored'], true)) {
        return;
    }

    $targetUrl = trim($targetUrl);
    if ($targetUrl !== '' && !legacyRedirectIsSafeTargetUrl($targetUrl)) {
        throw new InvalidArgumentException('Yönlendirme hedefi site içi bir yol olmalıdır.');
    }

    $stmt = $pdo->prepare("UPDATE legacy_redirect_rules SET status = ?, target_url = ?, target_type = 'url', target_id = NULL, is_manual = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $targetUrl !== '' ? $targetUrl : null, $id]);
}

function legacyRedirectIsSafeTargetUrl(string $targetUrl): bool
{
    $targetUrl = trim($targetUrl);
    if (
        $targetUrl === '' ||
        str_starts_with($targetUrl, '//') ||
        str_contains($targetUrl, '\\') ||
        preg_match('/%5c|%2f/i', $targetUrl) === 1
    ) {
        return false;
    }

    $parts = parse_url($targetUrl);
    if ($parts === false) {
        return false;
    }

    if (!empty($parts['scheme']) || !empty($parts['host'])) {
        return false;
    }

    $path = (string)($parts['path'] ?? '');
    return str_starts_with($path, '/') && !str_contains($path, "\0");
}
