<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Scraper/Legacy/SafeUrlGuard.php';

function adminQualityColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function adminQualityTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function adminQualityEnsureSchema(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return;
    }

    $topicColumns = [
        'moderation_flags' => 'JSON NULL',
        'last_checked_at' => 'TIMESTAMP NULL',
        'health_status' => "VARCHAR(32) NOT NULL DEFAULT 'unchecked'",
        'health_summary' => 'JSON NULL',
    ];
    foreach ($topicColumns as $column => $definition) {
        if (!adminQualityColumnExists($pdo, 'topics', $column)) {
            try {
                $pdo->exec("ALTER TABLE topics ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }
    }

    $linkColumns = [
        'last_checked_at' => 'TIMESTAMP NULL',
        'last_status_code' => 'INT NULL',
        'health_status' => "VARCHAR(32) NOT NULL DEFAULT 'unchecked'",
        'last_health_message' => 'VARCHAR(255) NULL',
        'last_final_url' => 'VARCHAR(2048) NULL',
    ];
    foreach ($linkColumns as $column => $definition) {
        if (!adminQualityColumnExists($pdo, 'topic_download_links', $column)) {
            try {
                $pdo->exec("ALTER TABLE topic_download_links ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }
    }

    $mediaColumns = [
        'last_checked_at' => 'TIMESTAMP NULL',
        'last_status_code' => 'INT NULL',
        'health_status' => "VARCHAR(32) NOT NULL DEFAULT 'unchecked'",
        'last_health_message' => 'VARCHAR(255) NULL',
    ];
    foreach ($mediaColumns as $column => $definition) {
        if (!adminQualityColumnExists($pdo, 'media_files', $column)) {
            try {
                $pdo->exec("ALTER TABLE media_files ADD COLUMN {$column} {$definition}");
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }
    }
}

function adminQualitySeoSummary(?PDO $pdo): array
{
    $summary = [
        'missing_meta_description' => 0,
        'missing_primary_media' => 0,
        'duplicate_titles' => 0,
        'canonical_missing' => 0,
        'total_issues' => 0,
    ];
    if (!$pdo) {
        return $summary;
    }

    try {
        $summary['missing_meta_description'] = (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') AND (meta_description IS NULL OR meta_description = '')")->fetchColumn();
        $summary['missing_primary_media'] = (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') AND primary_media_file_id IS NULL")->fetchColumn();
        $summary['duplicate_titles'] = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT title FROM topics WHERE deleted_at IS NULL GROUP BY title HAVING COUNT(*) > 1) dup")->fetchColumn();
        $canonical = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'canonical_base_url' LIMIT 1");
        $canonical->execute();
        $summary['canonical_missing'] = trim((string)$canonical->fetchColumn()) === '' ? 1 : 0;
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    $summary['total_issues'] = array_sum($summary);
    return $summary;
}

function adminQualityModerationSummary(?PDO $pdo): array
{
    if (!$pdo) {
        return ['pending' => 0, 'rejected' => 0, 'revision' => 0];
    }

    try {
        return [
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status = 'draft'")->fetchColumn(),
            'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status = 'rejected'")->fetchColumn(),
            'revision' => (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status = 'revision'")->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return ['pending' => 0, 'rejected' => 0, 'revision' => 0];
    }
}

function adminQualityDownloadSummary(?PDO $pdo): array
{
    if (!$pdo) {
        return ['unchecked' => 0, 'ok' => 0, 'broken' => 0, 'warning' => 0];
    }

    try {
        adminQualityEnsureSchema($pdo);
        return [
            'unchecked' => (int)$pdo->query("SELECT COUNT(*) FROM topic_download_links WHERE health_status = 'unchecked' OR health_status IS NULL")->fetchColumn(),
            'ok' => (int)$pdo->query("SELECT COUNT(*) FROM topic_download_links WHERE health_status = 'ok'")->fetchColumn(),
            'broken' => (int)$pdo->query("SELECT COUNT(*) FROM topic_download_links WHERE health_status = 'broken'")->fetchColumn(),
            'warning' => (int)$pdo->query("SELECT COUNT(*) FROM topic_download_links WHERE health_status = 'warning'")->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return ['unchecked' => 0, 'ok' => 0, 'broken' => 0, 'warning' => 0];
    }
}

function adminQualityObservabilitySummary(?PDO $pdo): array
{
    if (!$pdo) {
        return ['error_events' => 0, 'critical_admin_actions' => 0, 'today_activity' => 0];
    }

    try {
        return [
            'error_events' => (int)$pdo->query("SELECT COUNT(*) FROM application_logs WHERE level IN ('error','critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'critical_admin_actions' => (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('settings_updated','category_created','category_updated','category_deleted','media_uploaded','media_deleted','topic_moderated','download_link_checked') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'today_activity' => (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return ['error_events' => 0, 'critical_admin_actions' => 0, 'today_activity' => 0];
    }
}

function adminQualitySetTopicModeration(?PDO $pdo, int $topicId, string $decision, string $note = ''): bool
{
    if (!$pdo || $topicId <= 0) {
        return false;
    }

    $statusMap = [
        'approve' => 'published',
        'reject' => 'rejected',
        'revision' => 'revision',
    ];
    if (!isset($statusMap[$decision])) {
        return false;
    }
    adminQualityEnsureSchema($pdo);

    $flags = [
        'decision' => $decision,
        'note' => $note,
        'moderated_at' => date('c'),
        'moderator_id' => $_SESSION['_auth_user_id'] ?? null,
    ];

    $stmt = $pdo->prepare("UPDATE topics
        SET status = :status,
            moderation_flags = :flags,
            published_at = CASE WHEN :status_publish = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END,
            updated_at = NOW()
        WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([
        'status' => $statusMap[$decision],
        'status_publish' => $statusMap[$decision],
        'flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
        'id' => $topicId,
    ]);

    $updated = $stmt->rowCount() > 0;
    if ($updated && function_exists('seoInvalidateSitemapCaches')) {
        seoInvalidateSitemapCaches();
    }

    logActivity($pdo, 'topic_moderated', 'topic', $topicId, ['decision' => $decision, 'note' => $note]);
    return $updated;
}

function adminQualitySafeHttpUrl(string $url): bool
{
    return ScraperUrlGuard::inspect($url) !== null;
}

function adminQualityProbeHttpUrl(string $url, int $maxRedirects = 3): ?int
{
    if (!function_exists('curl_init')) {
        return null;
    }

    for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
        $inspection = ScraperUrlGuard::inspect($url);
        if ($inspection === null) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
            CURLOPT_RESOLVE => [$inspection['curl_resolve']],
        ]);
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($statusCode < 300 || $statusCode >= 400 || $redirectUrl === '') {
            return $statusCode;
        }

        $url = $redirectUrl;
    }

    return null;
}

function adminQualityResolveRedirectUrl(string $baseUrl, string $location): string
{
    $location = trim($location);
    if ($location === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $location) === 1) {
        return $location;
    }

    $base = parse_url($baseUrl);
    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return '';
    }

    if (str_starts_with($location, '//')) {
        return (string)$base['scheme'] . ':' . $location;
    }

    $origin = (string)$base['scheme'] . '://' . (string)$base['host'];
    if (!empty($base['port'])) {
        $origin .= ':' . (int)$base['port'];
    }

    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }

    $path = (string)($base['path'] ?? '/');
    $dir = preg_replace('~/[^/]*$~', '/', $path) ?: '/';
    return $origin . $dir . $location;
}

function adminQualityProbeUrlDetailed(string $url, string $method = 'HEAD', int $maxRedirects = 3, int $bodyBytes = 65536): array
{
    $result = [
        'safe' => false,
        'status_code' => null,
        'final_url' => $url,
        'content_type' => '',
        'content_length' => null,
        'content_disposition' => '',
        'body_sample' => '',
        'error' => '',
        'redirects' => 0,
    ];

    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_missing';
        return $result;
    }

    $method = strtoupper($method) === 'GET' ? 'GET' : 'HEAD';
    $currentUrl = trim($url);

    for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
        $inspection = ScraperUrlGuard::inspect($currentUrl);
        if ($inspection === null) {
            $result['error'] = 'unsafe_url';
            return $result;
        }

        $result['safe'] = true;
        $headers = [];
        $body = '';
        $ch = curl_init($currentUrl);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_RESOLVE => [$inspection['curl_resolve']],
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
                $pos = strpos($header, ':');
                if ($pos !== false) {
                    $name = strtolower(trim(substr($header, 0, $pos)));
                    $value = trim(substr($header, $pos + 1));
                    $headers[$name] = $value;
                }
                return strlen($header);
            },
        ]);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_RANGE, '0-' . max(0, $bodyBytes - 1));
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use (&$body, $bodyBytes): int {
                $remaining = max(0, $bodyBytes - strlen($body));
                if ($remaining === 0) {
                    return 0;
                }

                if (strlen($chunk) > $remaining) {
                    $body .= substr($chunk, 0, $remaining);
                    return 0;
                }

                $body .= $chunk;
                return strlen($chunk);
            });
        }
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $error = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($method === 'GET' && strlen($body) >= $bodyBytes) {
            $error = '';
        }

        $result['status_code'] = $statusCode > 0 ? $statusCode : null;
        $result['final_url'] = $currentUrl;
        $result['content_type'] = $contentType !== '' ? $contentType : (string)($headers['content-type'] ?? '');
        $result['content_length'] = is_numeric($contentLength) && (float)$contentLength >= 0 ? (int)$contentLength : null;
        $result['content_disposition'] = (string)($headers['content-disposition'] ?? '');
        $result['body_sample'] = $body;
        $result['error'] = $error;
        $result['redirects'] = $redirects;

        $location = (string)($headers['location'] ?? '');
        if ($statusCode >= 300 && $statusCode < 400 && $location !== '') {
            $nextUrl = adminQualityResolveRedirectUrl($currentUrl, $location);
            if ($nextUrl === '') {
                return $result;
            }
            $currentUrl = $nextUrl;
            $result['final_url'] = $currentUrl;
            continue;
        }

        return $result;
    }

    $result['error'] = 'redirect_limit';
    return $result;
}

function adminQualityNormalizeTextSignal(string $value): string
{
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function adminQualityStringContainsAny(string $haystack, array $needles): ?string
{
    foreach ($needles as $needle) {
        $needle = (string)$needle;
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return $needle;
        }
    }
    return null;
}

function adminQualityClassifyHttpProbe(array $probe, string $mode = 'link'): array
{
    $statusCode = $probe['status_code'] ?? null;
    $finalUrl = (string)($probe['final_url'] ?? '');
    $contentType = strtolower((string)($probe['content_type'] ?? ''));
    $contentDisposition = strtolower((string)($probe['content_disposition'] ?? ''));
    $body = adminQualityNormalizeTextSignal((string)($probe['body_sample'] ?? ''));
    $host = strtolower((string)(parse_url($finalUrl, PHP_URL_HOST) ?: ''));

    if (empty($probe['safe'])) {
        return ['status' => 'broken', 'message' => 'Gecersiz veya guvensiz URL.', 'reason' => 'unsafe_url'];
    }

    if (!empty($probe['error']) && !$statusCode) {
        return ['status' => 'warning', 'message' => 'Baglanti kurulamadı: ' . (string)$probe['error'], 'reason' => 'connection_error'];
    }

    if (!$statusCode) {
        return ['status' => 'warning', 'message' => 'HTTP durum kodu alinamadi.', 'reason' => 'no_status'];
    }

    if (in_array((int)$statusCode, [404, 410], true)) {
        return ['status' => 'broken', 'message' => 'Hedef 404/410 donuyor.', 'reason' => 'not_found'];
    }

    if ($statusCode >= 400 && $statusCode < 500) {
        $status = in_array((int)$statusCode, [401, 403, 429], true) ? 'warning' : 'broken';
        return ['status' => $status, 'message' => 'Hedef HTTP ' . $statusCode . ' donuyor.', 'reason' => 'client_error'];
    }

    if ($statusCode >= 500) {
        return ['status' => 'warning', 'message' => 'Hedef sunucu HTTP ' . $statusCode . ' donuyor.', 'reason' => 'server_error'];
    }

    $badSignals = [
        'file not found',
        'file was deleted',
        'file has been deleted',
        'file has been removed',
        'no such file',
        'not available anymore',
        'download not available',
        'this file is no longer available',
        'the file you are trying to download is no longer available',
        'item has been removed',
        'marked as hidden',
        'you do not have permission to view this item',
        'dosya bulunamadı',
        'dosya bulunamadi',
        'dosya silindi',
        'dosya kaldırıldı',
        'dosya kaldirildi',
    ];
    $badSignal = adminQualityStringContainsAny($body, $badSignals);
    if ($badSignal !== null) {
        return ['status' => 'broken', 'message' => 'Dosya/sayfa yok sinyali bulundu.', 'reason' => 'negative_signal'];
    }

    $manualSignals = [
        'captcha',
        'cloudflare',
        'checking your browser',
        'enable javascript',
        'access denied',
        'attention required',
        'security check',
        'rate limit',
        'güvenlik doğrulaması',
        'guvenlik dogrulamasi',
    ];
    $manualSignal = adminQualityStringContainsAny($body, $manualSignals);
    if ($manualSignal !== null) {
        return ['status' => 'warning', 'message' => 'Koruma/captcha sinyali var; manuel kontrol gerekli.', 'reason' => 'manual_gate'];
    }

    if ($mode === 'media') {
        if (str_starts_with($contentType, 'image/')) {
            return ['status' => 'ok', 'message' => 'Gorsel erisilebilir.', 'reason' => 'image_content_type'];
        }
        if ($statusCode >= 200 && $statusCode < 400) {
            return ['status' => 'warning', 'message' => 'URL erisilebilir ama yanit gorsel gibi degil.', 'reason' => 'non_image_response'];
        }
    }

    if ($mode === 'external') {
        return ['status' => ($statusCode >= 200 && $statusCode < 400) ? 'ok' : 'warning', 'message' => 'Harici baglanti erisilebilir.', 'reason' => 'http_ok'];
    }

    $directDownloadTypes = [
        'application/octet-stream',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar',
        'application/vnd.rar',
        'application/x-7z-compressed',
        'application/x-compressed',
    ];
    $downloadExtensions = ['.zip', '.rar', '.7z', '.scs', '.exe', '.iso'];
    $path = strtolower((string)(parse_url($finalUrl, PHP_URL_PATH) ?: ''));
    $hasDownloadExtension = false;
    foreach ($downloadExtensions as $extension) {
        if (str_ends_with($path, $extension)) {
            $hasDownloadExtension = true;
            break;
        }
    }

    if (
        $contentDisposition !== ''
        || $hasDownloadExtension
        || adminQualityStringContainsAny($contentType, $directDownloadTypes) !== null
    ) {
        return ['status' => 'ok', 'message' => 'Dogudan indirme yaniti alindi.', 'reason' => 'direct_download'];
    }

    $knownPositive = [
        'download file',
        'download now',
        'free download',
        'file size',
        'subscribe to download',
        'indir',
    ];
    if (
        str_contains($host, 'sharemods.com')
        || str_contains($host, 'modsfire.com')
        || str_contains($host, 'steamcommunity.com')
        || str_contains($host, 'modsbase.com')
    ) {
        if (adminQualityStringContainsAny($body, $knownPositive) !== null || $body === '') {
            return ['status' => 'ok', 'message' => 'Bilinen host erisilebilir; olumsuz dosya sinyali yok.', 'reason' => 'known_host_ok'];
        }
    }

    if ($statusCode >= 200 && $statusCode < 400) {
        return ['status' => 'warning', 'message' => 'Sayfa erisilebilir; indirilebilirlik manuel dogrulanmali.', 'reason' => 'reachable_manual_download'];
    }

    return ['status' => 'warning', 'message' => 'Kontrol sonucu belirsiz.', 'reason' => 'unknown'];
}

function adminQualitySmartCheckUrl(string $url, string $mode = 'link'): array
{
    $head = adminQualityProbeUrlDetailed($url, 'HEAD');
    $needsGet = $mode === 'download'
        || $mode === 'media'
        || empty($head['status_code'])
        || in_array((int)($head['status_code'] ?? 0), [403, 405, 429], true)
        || ((int)($head['status_code'] ?? 0) >= 200 && (int)($head['status_code'] ?? 0) < 400);

    $probe = $needsGet ? adminQualityProbeUrlDetailed($url, 'GET') : $head;
    $classification = adminQualityClassifyHttpProbe($probe, $mode);

    return [
        'status' => (string)$classification['status'],
        'message' => (string)$classification['message'],
        'reason' => (string)$classification['reason'],
        'status_code' => $probe['status_code'] ?? null,
        'final_url' => (string)($probe['final_url'] ?? $url),
        'content_type' => (string)($probe['content_type'] ?? ''),
    ];
}

function adminQualityCheckDownloadLink(?PDO $pdo, int $linkId): array
{
    if (!$pdo || $linkId <= 0) {
        return ['success' => false, 'message' => 'Gecersiz link secildi.'];
    }

    adminQualityEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT id, topic_id, url FROM topic_download_links WHERE id = ? LIMIT 1");
    $stmt->execute([$linkId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$link) {
        return ['success' => false, 'message' => 'Link bulunamadi.'];
    }

    $check = adminQualitySmartCheckUrl((string)$link['url'], 'download');
    $health = (string)$check['status'];
    $statusCode = $check['status_code'] !== null ? (int)$check['status_code'] : null;
    $message = mb_substr((string)$check['message'], 0, 255, 'UTF-8');

    $update = $pdo->prepare("UPDATE topic_download_links
        SET last_checked_at = NOW(),
            last_status_code = :status_code,
            health_status = :health_status,
            last_health_message = :message,
            last_final_url = :final_url
        WHERE id = :id");
    $update->execute([
        'status_code' => $statusCode,
        'health_status' => $health,
        'message' => $message,
        'final_url' => mb_substr((string)$check['final_url'], 0, 2048, 'UTF-8'),
        'id' => $linkId,
    ]);
    $pdo->prepare("UPDATE topics SET last_checked_at = NOW() WHERE id = ?")->execute([(int)$link['topic_id']]);
    if (function_exists('logActivity')) {
        logActivity($pdo, 'download_link_checked', 'topic', (int)$link['topic_id'], ['link_id' => $linkId, 'health_status' => $health, 'status_code' => $statusCode, 'message' => $message]);
    }

    return ['success' => true, 'message' => adminQualityDownloadHealthLabel($health) . ' - ' . $message, 'health_status' => $health, 'status_code' => $statusCode];
}

function adminQualityDownloadHealthLabel(string $status): string
{
    return [
        'ok' => 'Saglam',
        'broken' => 'Sorunlu',
        'warning' => 'Uyari',
        'unchecked' => 'Kontrol edilmedi',
    ][$status] ?? 'Bilinmiyor';
}

function adminQualityShortMessage(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }
    return mb_substr($message, 0, 255, 'UTF-8');
}

function adminQualityStatusRollup(array $statuses): string
{
    if (in_array('broken', $statuses, true)) {
        return 'broken';
    }
    if (in_array('warning', $statuses, true)) {
        return 'warning';
    }
    if (in_array('unchecked', $statuses, true)) {
        return 'unchecked';
    }
    return 'ok';
}

function adminQualityStatusCounts(array $statuses): array
{
    $counts = ['ok' => 0, 'warning' => 0, 'broken' => 0, 'unchecked' => 0];
    foreach ($statuses as $status) {
        $status = isset($counts[$status]) ? $status : 'warning';
        $counts[$status]++;
    }
    return $counts;
}

function adminQualityLocalMediaPath(string $path): string
{
    $path = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . $path;
}

function adminQualityCheckMediaRecord(?PDO $pdo, array $media): array
{
    $mediaId = (int)($media['id'] ?? 0);
    $path = trim((string)($media['path'] ?? ''));
    $type = strtolower((string)($media['type'] ?? ''));
    $mime = strtolower((string)($media['mime_type'] ?? ''));
    $isImage = $type === 'image' || str_starts_with($mime, 'image/');

    if (!$pdo || $mediaId <= 0 || $path === '') {
        return ['status' => 'broken', 'message' => 'Gorsel kaydi gecersiz.', 'status_code' => null];
    }

    if (!$isImage) {
        return ['status' => 'ok', 'message' => 'Gorsel olmayan medya atlandi.', 'status_code' => null];
    }

    if (preg_match('~^https?://~i', $path) === 1) {
        $check = adminQualitySmartCheckUrl($path, 'media');
        $status = (string)$check['status'];
        $statusCode = $check['status_code'] !== null ? (int)$check['status_code'] : null;
        $message = adminQualityShortMessage((string)$check['message']);
    } else {
        $localPath = adminQualityLocalMediaPath($path);
        if (!is_file($localPath)) {
            $status = 'broken';
            $statusCode = null;
            $message = 'Yerel gorsel dosyasi bulunamadi.';
        } elseif (filesize($localPath) <= 0) {
            $status = 'broken';
            $statusCode = null;
            $message = 'Yerel gorsel dosyasi bos.';
        } elseif (getimagesize($localPath) === false) {
            $status = 'warning';
            $statusCode = null;
            $message = 'Dosya var ama gorsel olarak dogrulanamadi.';
        } else {
            $status = 'ok';
            $statusCode = null;
            $message = 'Yerel gorsel dosyasi mevcut.';
        }
    }

    $stmt = $pdo->prepare("UPDATE media_files
        SET last_checked_at = NOW(),
            last_status_code = :status_code,
            health_status = :health_status,
            last_health_message = :message
        WHERE id = :id");
    $stmt->execute([
        'status_code' => $statusCode,
        'health_status' => $status,
        'message' => $message,
        'id' => $mediaId,
    ]);

    return ['status' => $status, 'message' => $message, 'status_code' => $statusCode];
}

function adminQualityExtractTopicExternalLinks(string $html, string $baseUri = ''): array
{
    $links = [];
    if ($html === '') {
        return [];
    }

    if (preg_match_all('~\bhref\s*=\s*([\'"])(.*?)\1~is', $html, $matches)) {
        foreach ($matches[2] as $url) {
            $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~^https?://~i', $url) !== 1) {
                continue;
            }
            $links[] = $url;
        }
    }

    $links = array_values(array_unique($links));
    $siteHost = strtolower((string)(parse_url((string)$baseUri, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '')));

    return array_values(array_filter($links, static function (string $url) use ($siteHost): bool {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        return $host !== '' && ($siteHost === '' || $host !== $siteHost);
    }));
}

function adminQualityCheckTopicHealth(?PDO $pdo, int $topicId, int $externalLimit = 5): array
{
    if (!$pdo || $topicId <= 0) {
        return ['success' => false, 'message' => 'Gecersiz konu secildi.'];
    }

    adminQualityEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT id, title, slug, topic_descriptions, topic_download_links, primary_media_file_id
                           FROM topics
                           WHERE id = ? AND deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$topic) {
        return ['success' => false, 'message' => 'Konu bulunamadi.'];
    }

    if (function_exists('getTopicDownloadLinks')) {
        getTopicDownloadLinks($pdo, $topicId, (string)($topic['topic_download_links'] ?? ''));
    }

    $downloadRowsStmt = $pdo->prepare("SELECT id, name, url FROM topic_download_links WHERE topic_id = ? ORDER BY display_order ASC, id ASC");
    $downloadRowsStmt->execute([$topicId]);
    $downloadRows = $downloadRowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $downloadStatuses = [];
    $downloadDetails = [];
    foreach ($downloadRows as $link) {
        $result = adminQualityCheckDownloadLink($pdo, (int)$link['id']);
        $status = (string)($result['health_status'] ?? 'warning');
        $downloadStatuses[] = $status;
        $downloadDetails[] = [
            'id' => (int)$link['id'],
            'name' => (string)($link['name'] ?? 'Link'),
            'url' => (string)($link['url'] ?? ''),
            'status' => $status,
            'status_code' => $result['status_code'] ?? null,
            'message' => (string)($result['message'] ?? ''),
        ];
    }
    if (empty($downloadRows)) {
        $downloadStatuses[] = 'broken';
    }

    $mediaStmt = $pdo->prepare("SELECT id, path, type, mime_type, is_primary FROM media_files WHERE topic_id = ? AND (type = 'image' OR mime_type LIKE 'image/%') ORDER BY is_primary DESC, display_order ASC, id ASC");
    $mediaStmt->execute([$topicId]);
    $mediaRows = $mediaStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $mediaStatuses = [];
    $mediaDetails = [];
    foreach ($mediaRows as $media) {
        $result = adminQualityCheckMediaRecord($pdo, $media);
        $status = (string)($result['status'] ?? 'warning');
        $mediaStatuses[] = $status;
        $mediaDetails[] = [
            'id' => (int)$media['id'],
            'path' => (string)($media['path'] ?? ''),
            'status' => $status,
            'status_code' => $result['status_code'] ?? null,
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    $missingPrimary = empty($topic['primary_media_file_id']);
    if ($missingPrimary) {
        $mediaStatuses[] = 'warning';
    } elseif (empty($mediaRows)) {
        $mediaStatuses[] = 'warning';
    }

    $externalLinks = adminQualityExtractTopicExternalLinks((string)($topic['topic_descriptions'] ?? ''), (string)($_SERVER['HTTP_HOST'] ?? ''));
    $externalLinks = array_slice($externalLinks, 0, max(0, $externalLimit));
    $externalStatuses = [];
    $externalDetails = [];
    foreach ($externalLinks as $url) {
        $check = adminQualitySmartCheckUrl($url, 'external');
        $status = (string)$check['status'];
        $externalStatuses[] = $status;
        $externalDetails[] = [
            'url' => $url,
            'status' => $status,
            'status_code' => $check['status_code'] ?? null,
            'message' => (string)$check['message'],
        ];
    }

    $allStatuses = array_merge($downloadStatuses, $mediaStatuses, $externalStatuses);
    $topicHealth = adminQualityStatusRollup($allStatuses ?: ['ok']);

    $summary = [
        'downloads' => array_merge(adminQualityStatusCounts($downloadStatuses), [
            'total' => count($downloadRows),
            'missing' => empty($downloadRows) ? 1 : 0,
            'details' => $downloadDetails,
        ]),
        'media' => array_merge(adminQualityStatusCounts($mediaStatuses), [
            'total' => count($mediaRows),
            'missing_primary' => $missingPrimary ? 1 : 0,
            'details' => $mediaDetails,
        ]),
        'external_links' => array_merge(adminQualityStatusCounts($externalStatuses), [
            'total' => count($externalLinks),
            'checked' => count($externalDetails),
            'details' => $externalDetails,
        ]),
    ];

    $update = $pdo->prepare("UPDATE topics
        SET last_checked_at = NOW(),
            health_status = :health_status,
            health_summary = :health_summary
        WHERE id = :id");
    $update->execute([
        'health_status' => $topicHealth,
        'health_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'id' => $topicId,
    ]);

    return [
        'success' => true,
        'topic_id' => $topicId,
        'title' => (string)($topic['title'] ?? ''),
        'health_status' => $topicHealth,
        'summary' => $summary,
        'message' => adminQualityDownloadHealthLabel($topicHealth),
    ];
}

function adminQualityCountScannableTopics(?PDO $pdo): int
{
    if (!$pdo) {
        return 0;
    }
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved')")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function adminQualityGetScannableTopicIds(?PDO $pdo, int $offset = 0, int $limit = 1): array
{
    if (!$pdo) {
        return [];
    }
    $offset = max(0, $offset);
    $limit = max(1, min(10, $limit));
    try {
        $stmt = $pdo->prepare("SELECT id FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') ORDER BY id ASC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function adminQualityDecodeTopicHealthSummary(?string $json): array
{
    $summary = json_decode((string)$json, true);
    return is_array($summary) ? $summary : [];
}

function adminQualityTopicHealthSummary(?PDO $pdo): array
{
    $summary = [
        'total' => 0,
        'checked' => 0,
        'ok' => 0,
        'warning' => 0,
        'broken' => 0,
        'unchecked' => 0,
        'download_link_issues' => 0,
        'missing_download_links' => 0,
        'missing_primary_media' => 0,
        'broken_download_links' => 0,
        'broken_media' => 0,
        'image_issues' => 0,
    ];
    if (!$pdo) {
        return $summary;
    }

    try {
        adminQualityEnsureSchema($pdo);
        $summary['total'] = adminQualityCountScannableTopics($pdo);
        $summary['checked'] = (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') AND last_checked_at IS NOT NULL")->fetchColumn();
        foreach (['ok', 'warning', 'broken', 'unchecked'] as $status) {
            $summary[$status] = (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') AND last_checked_at IS NOT NULL AND health_status = " . $pdo->quote($status))->fetchColumn();
        }
        $summary['download_link_issues'] = (int)$pdo->query("SELECT COUNT(*)
            FROM topics t
            WHERE t.deleted_at IS NULL
              AND t.status IN ('published','approved')
              AND t.last_checked_at IS NOT NULL
              AND (
                NOT EXISTS (SELECT 1 FROM topic_download_links l WHERE l.topic_id = t.id)
                OR EXISTS (SELECT 1 FROM topic_download_links l WHERE l.topic_id = t.id AND l.health_status = 'broken')
              )")->fetchColumn();
        $summary['missing_download_links'] = (int)$pdo->query("SELECT COUNT(*) FROM topics t WHERE t.deleted_at IS NULL AND t.status IN ('published','approved') AND t.last_checked_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM topic_download_links l WHERE l.topic_id = t.id)")->fetchColumn();
        $summary['missing_primary_media'] = (int)$pdo->query("SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND status IN ('published','approved') AND last_checked_at IS NOT NULL AND primary_media_file_id IS NULL")->fetchColumn();
        $summary['broken_download_links'] = (int)$pdo->query("SELECT COUNT(*) FROM topic_download_links l INNER JOIN topics t ON t.id = l.topic_id WHERE l.health_status = 'broken' AND t.deleted_at IS NULL AND t.status IN ('published','approved') AND t.last_checked_at IS NOT NULL")->fetchColumn();
        $summary['broken_media'] = (int)$pdo->query("SELECT COUNT(*) FROM media_files m INNER JOIN topics t ON t.id = m.topic_id WHERE m.health_status = 'broken' AND t.deleted_at IS NULL AND t.status IN ('published','approved') AND t.last_checked_at IS NOT NULL")->fetchColumn();
        $summary['image_issues'] = (int)$pdo->query("SELECT COUNT(*)
            FROM topics t
            WHERE t.deleted_at IS NULL
              AND t.status IN ('published','approved')
              AND t.last_checked_at IS NOT NULL
              AND (
                t.primary_media_file_id IS NULL
                OR NOT EXISTS (SELECT 1 FROM media_files m WHERE m.topic_id = t.id AND (m.type = 'image' OR m.mime_type LIKE 'image/%'))
                OR EXISTS (SELECT 1 FROM media_files m WHERE m.topic_id = t.id AND m.health_status IN ('broken', 'warning'))
              )")->fetchColumn();
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return $summary;
}

function adminQualityClearTopicHealth(?PDO $pdo): array
{
    $result = [
        'success' => false,
        'message' => 'Veritabanı bağlantısı yok.',
        'topics' => 0,
        'topic_download_links' => 0,
        'media_files' => 0,
        'activity_logs' => 0,
        'user_activity_events' => 0,
        'application_logs' => 0,
    ];

    if (!$pdo) {
        return $result;
    }

    try {
        adminQualityEnsureSchema($pdo);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $tableUpdates = [
            'topics' => [
                'last_checked_at' => 'NULL',
                'health_status' => "'unchecked'",
                'health_summary' => 'NULL',
            ],
            'topic_download_links' => [
                'last_checked_at' => 'NULL',
                'last_status_code' => 'NULL',
                'health_status' => "'unchecked'",
                'last_health_message' => 'NULL',
                'last_final_url' => 'NULL',
            ],
            'media_files' => [
                'last_checked_at' => 'NULL',
                'last_status_code' => 'NULL',
                'health_status' => "'unchecked'",
                'last_health_message' => 'NULL',
            ],
        ];

        foreach ($tableUpdates as $table => $columns) {
            $setParts = [];
            foreach ($columns as $column => $expression) {
                if (adminQualityColumnExists($pdo, $table, $column)) {
                    $setParts[] = $column . ' = ' . $expression;
                }
            }

            if ($setParts === []) {
                continue;
            }

            $whereClause = '';
            if (adminQualityColumnExists($pdo, $table, 'id')) {
                // Safe-update mode in MySQL/MariaDB can reject UPDATE without WHERE.
                $whereClause = ' WHERE id >= 0';
            }

            $affected = $pdo->exec('UPDATE ' . $table . ' SET ' . implode(', ', $setParts) . $whereClause);
            if ($affected === false) {
                throw new RuntimeException('Konu sağlığı temizlenemedi: ' . $table . ' tablosu güncellenemedi.');
            }
            $result[$table] = max(0, (int) $affected);
        }

        if (adminQualityTableExists($pdo, 'activity_logs')) {
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE action LIKE 'topic_health_scan_%'");
            if (!$stmt->execute()) {
                throw new RuntimeException('Konu sağlığı geçmişi temizlenemedi: activity_logs tablosu güncellenemedi.');
            }
            $result['activity_logs'] = max(0, (int) $stmt->rowCount());
        }

        if (adminQualityTableExists($pdo, 'user_activity_events')) {
            $stmt = $pdo->prepare("DELETE FROM user_activity_events WHERE event_type LIKE 'topic_health_scan_%'");
            if (!$stmt->execute()) {
                throw new RuntimeException('Konu sağlığı geçmişi temizlenemedi: user_activity_events tablosu güncellenemedi.');
            }
            $result['user_activity_events'] = max(0, (int) $stmt->rowCount());
        }

        if (adminQualityTableExists($pdo, 'application_logs')) {
            $stmt = $pdo->prepare("DELETE FROM application_logs WHERE channel = 'activity' AND message LIKE 'topic_health_scan_%'");
            if (!$stmt->execute()) {
                throw new RuntimeException('Konu sağlığı geçmişi temizlenemedi: application_logs tablosu güncellenemedi.');
            }
            $result['application_logs'] = max(0, (int) $stmt->rowCount());
        }

        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        $result['summary'] = adminQualityTopicHealthSummary($pdo);

        if (function_exists('logActivity')) {
            try {
                logActivity($pdo, 'topic_health_cleared', 'topic', null, [
                    'topics' => $result['topics'],
                    'topic_download_links' => $result['topic_download_links'],
                    'media_files' => $result['media_files'],
                    'activity_logs' => $result['activity_logs'],
                    'user_activity_events' => $result['user_activity_events'],
                    'application_logs' => $result['application_logs'],
                    'summary' => $result['summary'],
                ]);
            } catch (Throwable $logError) {
                error_log('[silent-catch] ' . $logError->getMessage());
            }
        }

        $result['success'] = true;
        $result['message'] = 'Konu sağlığı verileri ve tarama geçmişi temizlendi.';
    } catch (Throwable $e) {
        if (!empty($startedTransaction) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $result['message'] = 'Konu sağlığı temizlenemedi: ' . safeErrorMessage($e);
    }

    return $result;
}

function adminQualityNormalizeTopicHealthFilters(array $filters): array
{
    $allowedStatuses = ['ok', 'needs_check'];
    $allowedIssues = [
        'never_checked',
        'download_issues',
        'image_issues',
    ];
    $legacyIssueAliases = [
        'missing_downloads' => 'download_issues',
        'broken_downloads' => 'download_issues',
        'warning_downloads' => 'download_issues',
        'missing_primary_media' => 'image_issues',
        'broken_media' => 'image_issues',
        'warning_media' => 'image_issues',
    ];

    $query = trim((string)($filters['q'] ?? ''));
    if (function_exists('mb_substr')) {
        $query = mb_substr($query, 0, 120, 'UTF-8');
    } else {
        $query = substr($query, 0, 120);
    }

    $status = trim((string)($filters['health_status'] ?? ''));
    $legacyStatusAliases = [
        'warning' => 'needs_check',
        'broken' => 'needs_check',
        'unchecked' => 'needs_check',
    ];
    if (isset($legacyStatusAliases[$status])) {
        $status = $legacyStatusAliases[$status];
    }

    $issue = trim((string)($filters['health_issue'] ?? ''));
    if (isset($legacyIssueAliases[$issue])) {
        $issue = $legacyIssueAliases[$issue];
    }

    return [
        'q' => $query,
        'health_status' => in_array($status, $allowedStatuses, true) ? $status : '',
        'health_issue' => in_array($issue, $allowedIssues, true) ? $issue : '',
    ];
}

function adminQualityTopicHealthFilterSql(array $filters): array
{
    $filters = adminQualityNormalizeTopicHealthFilters($filters);
    $where = [
        "t.deleted_at IS NULL",
        "t.status IN ('published','approved')",
    ];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = "(t.title LIKE :health_q_title OR t.slug LIKE :health_q_slug OR cat.name LIKE :health_q_category)";
        $searchTerm = '%' . $filters['q'] . '%';
        $params['health_q_title'] = $searchTerm;
        $params['health_q_slug'] = $searchTerm;
        $params['health_q_category'] = $searchTerm;
    }

    if ($filters['health_status'] !== '') {
        if ($filters['health_status'] === 'needs_check') {
            $where[] = "(t.last_checked_at IS NULL OR COALESCE(t.health_status, 'unchecked') <> 'ok')";
        } else {
            $where[] = "COALESCE(t.health_status, 'unchecked') = :health_status";
            $params['health_status'] = $filters['health_status'];
        }
    }

    switch ($filters['health_issue']) {
        case 'never_checked':
            $where[] = 't.last_checked_at IS NULL';
            break;
        case 'download_issues':
            $where[] = "(t.last_checked_at IS NOT NULL AND (NOT EXISTS (SELECT 1 FROM topic_download_links l WHERE l.topic_id = t.id) OR EXISTS (SELECT 1 FROM topic_download_links l WHERE l.topic_id = t.id AND l.health_status = 'broken')))";
            break;
        case 'image_issues':
            $where[] = "(t.last_checked_at IS NOT NULL AND (t.primary_media_file_id IS NULL OR NOT EXISTS (SELECT 1 FROM media_files m WHERE m.topic_id = t.id AND (m.type = 'image' OR m.mime_type LIKE 'image/%')) OR EXISTS (SELECT 1 FROM media_files m WHERE m.topic_id = t.id AND m.health_status IN ('broken', 'warning'))))";
            break;
    }

    return [
        'where' => 'WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function adminQualityCountTopicHealthRows(?PDO $pdo, array $filters = []): int
{
    if (!$pdo) {
        return 0;
    }

    try {
        adminQualityEnsureSchema($pdo);
        $filterSql = adminQualityTopicHealthFilterSql($filters);
        $stmt = $pdo->prepare("SELECT COUNT(*)
                               FROM topics t
                               LEFT JOIN categories cat ON cat.id = t.category_id
                               {$filterSql['where']}");
        $stmt->execute($filterSql['params']);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function adminQualityGetTopicHealthRows(?PDO $pdo, int $limit = 80, int $offset = 0, array $filters = []): array
{
    if (!$pdo) {
        return [];
    }

    $limit = max(10, min(200, $limit));
    $offset = max(0, $offset);
    try {
        adminQualityEnsureSchema($pdo);
        $filterSql = adminQualityTopicHealthFilterSql($filters);
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.status, t.last_checked_at, t.health_status, t.health_summary, t.primary_media_file_id,
                                     cat.name AS category,
                                     (SELECT COUNT(*) FROM topic_download_links l WHERE l.topic_id = t.id) AS download_link_count,
                                     (SELECT COUNT(*) FROM topic_download_links l WHERE l.topic_id = t.id AND l.health_status = 'broken') AS download_issue_count,
                                     (SELECT COUNT(*) FROM media_files m WHERE m.topic_id = t.id AND (m.type = 'image' OR m.mime_type LIKE 'image/%')) AS image_count,
                                     (SELECT COUNT(*) FROM media_files m WHERE m.topic_id = t.id AND m.health_status IN ('broken','warning')) AS image_issue_count
                             FROM topics t
                             LEFT JOIN categories cat ON cat.id = t.category_id
                             {$filterSql['where']}
                             ORDER BY
                               CASE COALESCE(t.health_status, 'unchecked')
                                   WHEN 'broken' THEN 1
                                   WHEN 'warning' THEN 2
                                   WHEN 'unchecked' THEN 3
                                   ELSE 4
                               END,
                               t.last_checked_at IS NULL DESC,
                               t.last_checked_at ASC,
                               t.id DESC
                             LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($filterSql['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
