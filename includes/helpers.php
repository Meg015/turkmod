<?php

declare(strict_types=1);

/**
 * Global helper functions.
 */

if (! function_exists('runtimeSchemaUpdatesAllowed')) {
    /**
     * Runtime DDL stays enabled locally and for explicit CLI migrations only.
     */
    function runtimeSchemaUpdatesAllowed(): bool
    {
        if (defined('ALLOW_RUNTIME_SCHEMA_UPDATES') && ALLOW_RUNTIME_SCHEMA_UPDATES === true) {
            return true;
        }

        if (! class_exists(\App\Core\Database::class)) {
            return true;
        }

        $env = \App\Core\Database::getEnvConfig();
        $appEnv = strtolower((string) ($env['APP_ENV'] ?? 'local'));
        $rawAllow = strtolower((string) ($env['APP_ALLOW_RUNTIME_SCHEMA_UPDATES'] ?? ($appEnv === 'production' ? 'false' : 'true')));

        return in_array($rawAllow, ['1', 'true', 'yes', 'on'], true);
    }
}

if (! function_exists('e')) {
    /**
     * Shorthand for htmlspecialchars with UTF-8 encoding.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('passwordPolicyConfig')) {
    function passwordPolicyConfig(?array $settings = null): array
    {
        if ($settings === null) {
            $settings = [];
            if (function_exists('getAdminSettings')) {
                global $pdo;
                if (isset($pdo) && $pdo instanceof PDO) {
                    try {
                        $settings = getAdminSettings($pdo);
                    } catch (Throwable $e) {
                        $settings = [];
                    }
                }
            }
        }

        return [
            'min_length' => max(6, (int) ($settings['password_min_length'] ?? 10)),
            'require_uppercase' => (string) ($settings['password_require_uppercase'] ?? '0') === '1',
            'require_numbers' => (string) ($settings['password_require_numbers'] ?? '1') === '1',
            'require_special' => (string) ($settings['password_require_special'] ?? '0') === '1',
        ];
    }
}

if (! function_exists('passwordPolicyHint')) {
    function passwordPolicyHint(?array $settings = null): string
    {
        $policy = passwordPolicyConfig($settings);
        $parts = ['En az ' . $policy['min_length'] . ' karakter'];
        if ($policy['require_uppercase']) {
            $parts[] = 'bir büyük harf';
        }
        if ($policy['require_numbers']) {
            $parts[] = 'bir rakam';
        }
        if ($policy['require_special']) {
            $parts[] = 'bir özel karakter';
        }

        return implode(', ', $parts) . ' içermeli';
    }
}

if (! function_exists('validatePasswordPolicy')) {
    function validatePasswordPolicy(string $password, ?array $settings = null, string $label = 'Şifre'): string
    {
        $policy = passwordPolicyConfig($settings);

        if (mb_strlen($password, 'UTF-8') < $policy['min_length']) {
            return "{$label} en az {$policy['min_length']} karakter olmalıdır.";
        }
        if ($policy['require_uppercase'] && !preg_match('/[A-ZÇĞİÖŞÜ]/u', $password)) {
            return "{$label} en az bir büyük harf içermelidir.";
        }
        if ($policy['require_numbers'] && !preg_match('/\d/', $password)) {
            return "{$label} en az bir rakam içermelidir.";
        }
        if ($policy['require_special'] && !preg_match('/[^A-Za-z0-9ÇĞİÖŞÜçğıöşü]/u', $password)) {
            return "{$label} en az bir özel karakter içermelidir.";
        }

        return '';
    }
}

if (! function_exists('recordCronRun')) {
    function recordCronRun(?PDO $pdo, string $jobKey, string $status, array $context = []): void
    {
        $jobKey = strtolower(trim($jobKey));
        $jobKey = preg_replace('/[^a-z0-9_.:-]+/', '_', $jobKey) ?: '';
        if ($jobKey === '') {
            return;
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['success', 'warning', 'error', 'skipped'], true)) {
            $status = 'success';
        }

        $level = match ($status) {
            'error' => 'error',
            'warning' => 'warning',
            default => 'info',
        };

        $context = array_merge([
            'job_key' => $jobKey,
            'status' => $status,
            'sapi' => PHP_SAPI,
        ], $context);

        if (function_exists('appLog')) {
            appLog($pdo, $level, 'cron', 'cron_run:' . $jobKey, $context);
        }
    }
}

if (! function_exists('base_uri')) {
    /**
     * Get the base URI for asset and link generation.
     */
    function base_uri(): string
    {
        static $cached = null;
        if (is_string($cached)) {
            return $cached;
        }

        $projectRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            $fallback = trim(basename($projectRoot), '/');
            if ($fallback !== '' && $fallback !== '.' && $fallback !== '/') {
                return $cached = '/' . $fallback;
            }
        }

        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot !== false) {
            $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
            if ($documentRoot !== '' && str_starts_with($projectRoot . '/', $documentRoot . '/')) {
                $relative = trim(substr($projectRoot, strlen($documentRoot)), '/');
                return $cached = $relative !== '' ? '/' . $relative : '';
            }
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName !== '' && str_starts_with($scriptName, '/')) {
            $scriptDir = rtrim(dirname($scriptName), '/');
            if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
                return $cached = $scriptDir;
            }
        }

        $fallback = trim(basename($projectRoot), '/');
        if ($fallback !== '' && $fallback !== '.' && $fallback !== '/') {
            return $cached = '/' . $fallback;
        }

        return $cached = '';
    }
}

if (! function_exists('asset_version')) {
    /**
     * Return a version string for asset cache busting.
     * Uses file modification time for better caching.
     */
    function asset_version(string $relativePath): string
    {
        $method = defined('ASSET_VERSION_METHOD') ? ASSET_VERSION_METHOD : 'filemtime';
        
        if ($method === 'manual') {
            return defined('ASSET_VERSION_MANUAL') ? ASSET_VERSION_MANUAL : '1.0.0';
        }
        
        if ($method === 'git_hash') {
            static $gitHash = null;
            if ($gitHash === null) {
                $gitFile = __DIR__ . '/../../.git/HEAD';
                if (file_exists($gitFile)) {
                    $gitHash = substr(file_get_contents($gitFile) ?: '', 0, 7);
                } else {
                    $gitHash = 'dev';
                }
            }
            return $gitHash;
        }
        
        // Default: filemtime
        $fullPath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
        if (file_exists($fullPath)) {
            return (string) filemtime($fullPath);
        }
        
        return '1';
    }
}

if (! function_exists('asset_url')) {
    /**
     * Build a local asset URL that forces the browser to fetch the latest file.
     */
    function asset_url(string $relativePath, ?string $baseUri = null): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $baseUri = $baseUri ?? ($GLOBALS['baseUri'] ?? base_uri());
        $themeManager = $GLOBALS['themeManager'] ?? null;
        $requestPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $isBackOffice = str_contains($requestPath, '/admin/') || str_contains($requestPath, '/install/');

        if (!$isBackOffice && is_object($themeManager) && method_exists($themeManager, 'publicAssetUrl')) {
            $themeUrl = $themeManager->publicAssetUrl($relativePath);
            if (is_string($themeUrl) && $themeUrl !== '') {
                return $themeUrl;
            }

            if (
                method_exists($themeManager, 'isAssetIsolated')
                && $themeManager->isAssetIsolated()
                && str_starts_with($relativePath, 'assets/')
            ) {
                throw new RuntimeException('Isolated theme asset not found: ' . $relativePath);
            }
        }

        $url = rtrim($baseUri, '/') . '/' . $relativePath . '?v=' . rawurlencode(asset_version($relativePath));

        // CDN prefix for public pages (Item #10)
        if (!$isBackOffice) {
            static $cdnBaseUrl = null;
            if ($cdnBaseUrl === null) {
                $cdnBaseUrl = rtrim((string) (\App\Core\Database::getEnvConfig()['CDN_BASE_URL'] ?? ''), '/');
            }
            if ($cdnBaseUrl !== '' && !str_starts_with($relativePath, 'http')) {
                return $cdnBaseUrl . '/' . $relativePath . '?v=' . rawurlencode(asset_version($relativePath));
            }
        }

        return $url;
    }
}

if (! function_exists('csrf_token')) {
    /**
     * Generate or retrieve the current CSRF token.
     */
    function csrf_token(): string
    {
        if (class_exists('CsrfProtection') && method_exists('CsrfProtection', 'token')) {
            return CsrfProtection::token();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (! function_exists('csrf_field')) {
    /**
     * Generate an HTML hidden input with the CSRF token.
     */
    function csrf_field(): string
    {
        if (class_exists('CsrfProtection') && method_exists('CsrfProtection', 'field')) {
            return CsrfProtection::field('_token');
        }

        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (! function_exists('verify_csrf_token')) {
    /**
     * Verify the submitted CSRF token matches the session token.
     */
    function verify_csrf_token(?string $token): bool
    {
        if (class_exists('CsrfProtection') && method_exists('CsrfProtection', 'verify')) {
            return CsrfProtection::verify($token);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $expected = $_SESSION['_csrf_token'] ?? '';

        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }
}

if (! function_exists('flash')) {
    /**
     * Set a flash message (available only for the next request).
     */
    function flash(string $key, mixed $value): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['_flash'][$key] = $value;
        $_SESSION['_flash_' . $key] = $value;
    }
}

if (! function_exists('get_flash')) {
    /**
     * Get and clear a flash message.
     */
    function get_flash(string $key, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $value = $_SESSION['_flash'][$key]
            ?? $_SESSION['_flash_' . $key]
            ?? $default;
        unset($_SESSION['_flash'][$key], $_SESSION['_flash_' . $key]);

        return $value;
    }
}

if (! function_exists('slugify')) {
    /**
     * Generate a URL-safe slug from a string.
     *
     * Settings are cached in a static variable to avoid repeated DB queries.
     * The first call may hit the DB (via getAdminSettings), but subsequent calls
     * within the same request use the cached values.
     */
    function slugify(string $text): string
    {
        static $cachedSettings = null;

        $map = [
            "\u{00E7}" => 'c', "\u{00C7}" => 'C',
            "\u{011F}" => 'g', "\u{011E}" => 'G',
            "\u{0131}" => 'i', "\u{0130}" => 'I',
            "\u{00F6}" => 'o', "\u{00D6}" => 'O',
            "\u{015F}" => 's', "\u{015E}" => 'S',
            "\u{00FC}" => 'u', "\u{00DC}" => 'U',
        ];

        // Cache settings on first call — getAdminSettings already has its own
        // APCu/file cache layer, so this only hits DB on cold cache.
        if ($cachedSettings === null) {
            $cachedSettings = [
                'separator' => '-',
                'caseMode' => 'lowercase',
                'maxLength' => 200,
            ];
            if (function_exists('getAdminSettings')) {
                global $pdo;
                if (isset($pdo) && $pdo) {
                    try {
                        $settings = getAdminSettings($pdo);
                        $cachedSettings['separator'] = ($settings['route_slug_format'] ?? 'dash') === 'underscore' ? '_' : '-';
                        $cachedSettings['caseMode'] = $settings['route_case_sensitive'] ?? 'lowercase';
                        $cachedSettings['maxLength'] = max(1, (int)($settings['route_url_max_length'] ?? 200));
                    } catch (\Throwable $e) {}
                }
            }
        }

        $separator = $cachedSettings['separator'];
        $caseMode = $cachedSettings['caseMode'];
        $maxLength = $cachedSettings['maxLength'];

        $text = strtr($text, $map);

        if ($caseMode === 'lowercase') {
            $text = mb_strtolower($text, 'UTF-8');
        }

        // Sadece harf, rakam, bosluk, tire ve altcizgiye izin ver.
        $text = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $text) ?? '';

        // Bosluk, tire ve altcizgileri ayarlanan ayırıcıya cevir
        $text = preg_replace('/[\s_-]+/', $separator, $text) ?? '';

        $text = trim($text, $separator);

        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
            $text = trim($text, $separator);
        }

        return $text;
    }
}

if (! function_exists('uploadCleanExtension')) {
    function uploadCleanExtension(string $extension, string $fallback = 'bin'): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');
        return $extension !== '' ? $extension : $fallback;
    }
}

if (! function_exists('uploadTitleFilenameBase')) {
    function uploadTitleFilenameBase(string $title, string $fallback = 'dosya'): string
    {
        $base = function_exists('slugify') ? slugify($title) : '';
        if ($base === '') {
            $base = function_exists('slugify') ? slugify($fallback) : '';
        }

        return $base !== '' ? $base : 'dosya';
    }
}

if (! function_exists('uploadTitleFilename')) {
    function uploadTitleFilename(string $title, string $extension, ?int $sequence = null, string $fallback = 'dosya'): string
    {
        $base = uploadTitleFilenameBase($title, $fallback);
        if ($sequence !== null && $sequence > 0) {
            $base .= '-' . $sequence;
        }

        return $base . '.' . uploadCleanExtension($extension);
    }
}

if (! function_exists('uploadProfileAvatarFilename')) {
    function uploadProfileAvatarFilename(int $userId, string $name, string $extension): string
    {
        $nameSlug = uploadTitleFilenameBase($name, 'profil');
        return 'user-' . max(0, $userId) . '-' . $nameSlug . '-avatar.' . uploadCleanExtension($extension);
    }
}

if (! function_exists('uploadAvailableFilename')) {
    function uploadAvailableFilename(string $directory, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 2;

        while (file_exists(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $base . '-' . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }

        return $candidate;
    }
}
if (! function_exists('topicDescriptionWithoutRepeatedTitle')) {
    function topicDescriptionHeadingText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\x{00a0}/u', ' ', $value) ?? $value;
        $value = preg_replace('/[\s\p{Zs}]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return mb_strtolower($value, 'UTF-8');
    }

    function topicDescriptionWithoutRepeatedTitle(string $html, string $title): string
    {
        $html = trim($html);
        $titleKey = topicDescriptionHeadingText($title);
        if ($html === '' || $titleKey === '') {
            return $html;
        }

        if (preg_match('/^\s*' . preg_quote($title, '/') . '\s*(?:\R+|<br\s*\/?>|<\/p>|$)/iu', $html) === 1) {
            $html = preg_replace('/^\s*' . preg_quote($title, '/') . '\s*(?:\R+|<br\s*\/?>|<\/p>|$)/iu', '', $html, 1) ?? $html;
        }

        if (!class_exists('DOMDocument')) {
            return trim($html);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="topic-desc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) {
            return trim($html);
        }

        $removeLeadingSeparators = static function (DOMElement $root): void {
            while ($root->firstChild) {
                $node = $root->firstChild;
                if ($node instanceof DOMText && trim($node->nodeValue ?? '') === '') {
                    $root->removeChild($node);
                    continue;
                }
                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['br', 'hr'], true)) {
                    $root->removeChild($node);
                    continue;
                }
                break;
            }
        };

        $removeLeadingSeparators($root);
        $first = $root->firstChild;
        if ($first instanceof DOMText) {
            $text = (string) $first->nodeValue;
            $parts = preg_split('/\R+/', $text, 2);
            if (is_array($parts) && topicDescriptionHeadingText((string) ($parts[0] ?? '')) === $titleKey) {
                if (isset($parts[1]) && trim((string) $parts[1]) !== '') {
                    $first->nodeValue = ltrim((string) $parts[1]);
                } else {
                    $root->removeChild($first);
                }
                $removeLeadingSeparators($root);
            }
        } elseif ($first instanceof DOMElement) {
            $tag = strtolower($first->tagName);
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span', 'strong', 'b'], true)
                && topicDescriptionHeadingText($first->textContent ?? '') === $titleKey) {
                $root->removeChild($first);
                $removeLeadingSeparators($root);
            }
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }
}

if (! function_exists('sanitizeTopicHtml')) {
    /**
     * Sanitize topic rich text while preserving the small HTML subset used by the editor.
     */
    function sanitizeTopicHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $allowedTags = [
            'p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'ul', 'li', 'ol',
            'a', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
            'code', 'pre', 'hr', 'iframe', 'div', 'span', 'table', 'thead',
            'tbody', 'tfoot', 'tr', 'td', 'th',
        ];
        $allowedAttrs = [
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
            'iframe' => ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'],
            'p' => ['style', 'class'], 'div' => ['style', 'class'], 'span' => ['style', 'class'],
            'h1' => ['style', 'class'], 'h2' => ['style', 'class'], 'h3' => ['style', 'class'], 'h4' => ['style', 'class'], 'h5' => ['style', 'class'], 'h6' => ['style', 'class'],
            'td' => ['style', 'class'], 'th' => ['style', 'class'], 'tr' => ['style', 'class'],
            'ul' => ['style', 'class'], 'ol' => ['style', 'class'], 'li' => ['style', 'class'],
            'blockquote' => ['style', 'class'], 'pre' => ['style', 'class'],
        ];

        // Sadece guvenli CSS propertylerine izin ver
        $allowedCssProperties = ['text-align', 'text-decoration', 'font-weight', 'font-style', 'color', 'background-color', 'margin', 'margin-left', 'margin-right', 'padding', 'padding-left', 'padding-right', 'text-indent', 'line-height', 'display', 'list-style-type'];

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $wrapped = '<div>' . $html . '</div>';
        $doc->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $isSafeUrl = static function (string $url, bool $allowDataImage = false): bool {
            $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '') {
                return false;
            }
            if (str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
                return true;
            }
            if (parse_url($url, PHP_URL_SCHEME) === null && !str_contains($url, ':')) {
                return true;
            }
            if ($allowDataImage && preg_match('/^data:image\/(?:png|jpe?g|gif|webp);base64,[a-z0-9+\/=\s]+$/i', $url) === 1) {
                return true;
            }
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https', 'mailto'], true);
        };

        $isTrustedIframe = static function (string $url): bool {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            return in_array($host, ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com', 'player.vimeo.com'], true);
        };

        $sanitizeCss = static function (string $style) use ($allowedCssProperties): string {
            $clean = [];
            $declarations = array_filter(array_map('trim', explode(';', $style)));
            foreach ($declarations as $decl) {
                $parts = explode(':', $decl, 2);
                if (count($parts) !== 2) continue;
                $prop = strtolower(trim($parts[0]));
                $val = trim($parts[1]);
                if (in_array($prop, $allowedCssProperties, true) && !preg_match('/expression|url|javascript|import/i', $val)) {
                    $clean[] = $prop . ':' . $val;
                }
            }
            return implode(';', $clean);
        };

        $sanitizeClass = static function (string $class): string {
            $clean = [];
            foreach (preg_split('/\s+/', trim($class)) ?: [] as $candidate) {
                $candidate = strtolower($candidate);
                if (preg_match('/^(?:content-align|ql-align)-(?:left|center|right|justify)$/', $candidate)) {
                    $clean[] = $candidate;
                }
            }
            return implode(' ', array_unique($clean));
        };

        $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $doc, $allowedTags, $allowedAttrs, $isSafeUrl, $isTrustedIframe, $sanitizeCss, $sanitizeClass): void {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    $text = $doc->createTextNode($node->textContent);
                    $node->parentNode?->replaceChild($text, $node);
                    return;
                }

                $allowed = $allowedAttrs[$tag] ?? [];
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $value = trim($attr->value);
                    if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                        $node->removeAttribute($attr->name);
                        continue;
                    }
                    if ($name === 'class') {
                        $cleaned = $sanitizeClass($value);
                        if ($cleaned === '') {
                            $node->removeAttribute($attr->name);
                        } else {
                            $node->setAttribute($attr->name, $cleaned);
                        }
                        continue;
                    }
                    if ($name === 'style') {
                        $cleaned = $sanitizeCss($value);
                        if ($cleaned === '') {
                            $node->removeAttribute($attr->name);
                        } else {
                            $node->setAttribute($attr->name, $cleaned);
                        }
                        continue;
                    }
                    if ($tag === 'a' && $name === 'href' && !$isSafeUrl($value)) {
                        $node->removeAttribute($attr->name);
                    }
                    if ($tag === 'img' && $name === 'src' && !$isSafeUrl($value, true)) {
                        $node->removeAttribute($attr->name);
                    }
                    if ($tag === 'iframe' && $name === 'src' && (!$isSafeUrl($value) || !$isTrustedIframe($value))) {
                        $node->parentNode?->removeChild($node);
                        return;
                    }
                }

                if ($tag === 'a' && $node->hasAttribute('href')) {
                    $node->setAttribute('rel', 'nofollow noopener noreferrer');
                    if (!$node->hasAttribute('target')) {
                        $node->setAttribute('target', '_blank');
                    }
                }
                if ($tag === 'img' && $node->hasAttribute('src') && !$node->hasAttribute('loading')) {
                    $node->setAttribute('loading', 'lazy');
                }
                if ($tag === 'iframe') {
                    if (!$node->hasAttribute('src') || !$isTrustedIframe((string) $node->getAttribute('src'))) {
                        $node->parentNode?->removeChild($node);
                        return;
                    }
                    $node->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                    $node->setAttribute('loading', 'lazy');
                }
            }

            foreach (iterator_to_array($node->childNodes) as $child) {
                $sanitizeNode($child);
            }
        };

        $root = $doc->documentElement;
        if ($root) {
            $sanitizeNode($root);
        }

        $output = '';
        $container = $doc->getElementsByTagName('div')->item(0);
        if ($container) {
            foreach ($container->childNodes as $child) {
                $output .= $doc->saveHTML($child);
            }
        }

        return trim($output);
    }
}

if (! function_exists('safeExternalUrl')) {
    /**
     * Sanitise a user-supplied URL for safe use in an href. Blocks dangerous
     * schemes (javascript:, data:, vbscript: ...) that survive htmlspecialchars
     * and could execute script when clicked. Returns '#' for anything unsafe.
     */
    function safeExternalUrl(string $url, string $fallback = '#'): string
    {
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }

        // Allow root-relative and protocol-relative links as-is.
        if ($url[0] === '/' || str_starts_with($url, '//')) {
            return $url;
        }

        // If it has a scheme, only http/https/mailto are permitted.
        if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $url, $m) === 1) {
            $scheme = strtolower($m[1]);
            if (! in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return $fallback;
            }
            return $url;
        }

        // No scheme (e.g. "example.com/path") -> assume https.
        return 'https://' . ltrim($url, '/');
    }
}

if (! function_exists('avatarHue')) {
    /**
     * Deterministic hue (0-359) derived from a seed (user name, email or id).
     * The same seed always yields the same colour, so every user keeps a stable,
     * personal default-avatar colour across the whole site.
     */
    function avatarHue(string $seed): int
    {
        $seed = trim($seed);
        if ($seed === '') {
            return 222; // neutral brand-ish blue for empty/unknown
        }

        // crc32 is fast, stable and well-distributed for short strings.
        return (int) (crc32(mb_strtolower($seed, 'UTF-8')) % 360);
    }
}

if (! function_exists('avatarInitials')) {
    /**
     * Up to two uppercase initials from a display name (e.g. "Ahmet Yilmaz" -> "AY").
     * Falls back to a single letter, then to "?" for empty input.
     */
    function avatarInitials(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '?';
        }

        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
            if (mb_strlen($initials, 'UTF-8') >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
}

if (! function_exists('defaultAvatarUrl')) {
    function defaultAvatarUrl(?string $baseUri = null): string
    {
        $base = $baseUri ?? (string) ($GLOBALS['baseUri'] ?? '');
        return rtrim($base, '/') . '/assets/images/noavatar-neon-helmet.svg';
    }
}

if (! function_exists('resolvePublicMediaUrl')) {
    function resolvePublicMediaUrl(?string $path, ?string $baseUri = null): string
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            return '';
        }

        if (preg_match('~^(https?:)?//~i', $path) === 1 || preg_match('~^data:~i', $path) === 1) {
            return $path;
        }

        $base = $baseUri ?? (string) ($GLOBALS['baseUri'] ?? '');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (! function_exists('authUserAvatarRaw')) {
    function authUserAvatarRaw(?PDO $pdo = null, ?int $userId = null, int $ttl = 60): string
    {
        $userId = $userId ?? (int) ($_SESSION['_auth_user_id'] ?? 0);
        if ($userId <= 0) {
            return '';
        }

        static $requestCache = [];
        $cacheKey = $userId . ':' . max(1, $ttl);
        if (array_key_exists($cacheKey, $requestCache)) {
            return $requestCache[$cacheKey];
        }

        $avatarCache = $_SESSION['_auth_avatar_cache'] ?? null;
        if (
            is_array($avatarCache)
            && (int) ($avatarCache['uid'] ?? 0) === $userId
            && (time() - (int) ($avatarCache['ts'] ?? 0)) <= $ttl
        ) {
            return $requestCache[$cacheKey] = trim((string) ($avatarCache['raw'] ?? ''));
        }

        if (!$pdo instanceof PDO) {
            return $requestCache[$cacheKey] = '';
        }

        try {
            $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $avatar = trim((string) ($stmt->fetchColumn() ?: ''));
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_auth_avatar_cache'] = [
                    'uid' => $userId,
                    'raw' => $avatar,
                    'ts' => time(),
                ];
            }

            return $requestCache[$cacheKey] = $avatar;
        } catch (Throwable) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_auth_avatar_cache'] = null;
            }

            return $requestCache[$cacheKey] = '';
        }
    }
}

if (! function_exists('authUserAvatarUrl')) {
    function authUserAvatarUrl(?PDO $pdo = null, ?string $baseUri = null, bool $fallback = true, int $ttl = 60): string
    {
        $avatar = authUserAvatarRaw($pdo, null, $ttl);

        if (function_exists('resolveAvatarUrl')) {
            return resolveAvatarUrl($avatar, $baseUri, $fallback);
        }

        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            return $fallback ? defaultAvatarUrl($baseUri) : '';
        }

        if (preg_match('~^(https?:)?//~i', $avatar) === 1 || preg_match('~^data:~i', $avatar) === 1) {
            return $avatar;
        }

        $base = $baseUri ?? (string) ($GLOBALS['baseUri'] ?? '');
        return rtrim($base, '/') . '/' . ltrim($avatar, '/');
    }
}

if (! function_exists('resolveAvatarUrl')) {
    function resolveAvatarUrl(?string $avatar, ?string $baseUri = null, bool $fallback = true): string
    {
        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            return $fallback ? defaultAvatarUrl($baseUri) : '';
        }

        if (preg_match('~^(https?:)?//~i', $avatar) === 1) {
            return $avatar;
        }

        if (preg_match('~^(data|javascript|vbscript):~i', $avatar) === 1) {
            return $fallback ? defaultAvatarUrl($baseUri) : '';
        }

        $base = $baseUri ?? (string) ($GLOBALS['baseUri'] ?? '');
        $cleanBase = trim($base, '/');
        $relative = ltrim($avatar, '/');
        if ($cleanBase !== '' && str_starts_with($relative, $cleanBase . '/')) {
            $relative = substr($relative, strlen($cleanBase) + 1);
        }

        $localPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($localPath)) {
            return $fallback ? defaultAvatarUrl($baseUri) : '';
        }

        return rtrim($base, '/') . '/' . $relative;
    }
}

if (! function_exists('avatarImageHtml')) {
    /**
     * @param array<string, mixed> $options
     */
    function avatarImageHtml(string $name, ?string $avatar, array $options = []): string
    {
        $baseUri = isset($options['base_uri']) ? (string) $options['base_uri'] : null;
        $src = resolveAvatarUrl($avatar, $baseUri, true);
        $fallback = defaultAvatarUrl($baseUri);
        $extraClass = trim((string) ($options['class'] ?? ''));
        $classAttr = trim('ui-avatar-img ' . $extraClass);
        $alt = (string) ($options['alt'] ?? $name);
        $loading = trim((string) ($options['loading'] ?? 'lazy'));
        if (!in_array($loading, ['lazy', 'eager', 'auto'], true)) {
            $loading = 'lazy';
        }
        $decoding = trim((string) ($options['decoding'] ?? 'async'));
        if (!in_array($decoding, ['async', 'sync', 'auto'], true)) {
            $decoding = 'async';
        }
        $fetchpriority = trim((string) ($options['fetchpriority'] ?? 'low'));
        if (!in_array($fetchpriority, ['high', 'low', 'auto'], true)) {
            $fetchpriority = 'low';
        }
        $width = isset($options['width']) ? max(0, (int) $options['width']) : 0;
        $height = isset($options['height']) ? max(0, (int) $options['height']) : 0;

        return '<img class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '"'
            . ' src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
            . ' loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"'
            . ' decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '"'
            . ' fetchpriority="' . htmlspecialchars($fetchpriority, ENT_QUOTES, 'UTF-8') . '"'
            . ($width > 0 ? ' width="' . $width . '"' : '')
            . ($height > 0 ? ' height="' . $height . '"' : '')
            . ' data-ui-avatar-img'
            . ' data-ui-avatar-fallback="' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (! function_exists('defaultAvatarHtml')) {
    /**
     * Render the single central no-avatar image used across the site.
     *
     * @param array<string, mixed> $options extra: class, base_uri, alt.
     */
    function defaultAvatarHtml(string $name, array $options = []): string
    {
        $extraClass = trim((string) ($options['class'] ?? ''));
        $classAttr = 'default-avatar' . ($extraClass !== '' ? ' ' . $extraClass : '');
        $tag = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($options['tag'] ?? 'span'))) ?: 'span';
        $baseUri = isset($options['base_uri']) ? (string) $options['base_uri'] : null;
        $alt = (string) ($options['alt'] ?? $name);
        $loading = trim((string) ($options['loading'] ?? 'lazy'));
        if (!in_array($loading, ['lazy', 'eager', 'auto'], true)) {
            $loading = 'lazy';
        }
        $decoding = trim((string) ($options['decoding'] ?? 'async'));
        if (!in_array($decoding, ['async', 'sync', 'auto'], true)) {
            $decoding = 'async';
        }
        $fetchpriority = trim((string) ($options['fetchpriority'] ?? 'low'));
        if (!in_array($fetchpriority, ['high', 'low', 'auto'], true)) {
            $fetchpriority = 'low';
        }
        $width = isset($options['width']) ? max(0, (int) $options['width']) : 0;
        $height = isset($options['height']) ? max(0, (int) $options['height']) : 0;
        $fallbackUrl = defaultAvatarUrl($baseUri);

        return '<' . $tag . ' class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-ui-avatar-default>'
            . '<img src="' . htmlspecialchars($fallbackUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" data-ui-avatar-img'
            . ' decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '"'
            . ' fetchpriority="' . htmlspecialchars($fetchpriority, ENT_QUOTES, 'UTF-8') . '"'
            . ' loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"'
            . ($width > 0 ? ' width="' . $width . '"' : '')
            . ($height > 0 ? ' height="' . $height . '"' : '')
            . ' data-ui-avatar-fallback="' . htmlspecialchars($fallbackUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '</' . $tag . '>';
    }
}

if (! function_exists('invalidateUserAvatarCache')) {
    /**
     * Oturum icinde onbelleklenen avatar bilgisini temizle. Avatar yukleme veya
     * kaldirma islemi yapan kod yollari, degisikligin header'da hemen görünmesi icin
     * bu fonksiyonu cagirmalidir.
     */
    function invalidateUserAvatarCache(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["_auth_avatar_cache"])) {
            unset($_SESSION["_auth_avatar_cache"]);
        }
    }
}

if (! function_exists('renderPopupAnnouncementHtml')) {
    /**
     * Render the popup announcement overlay, modal card, CSS, and JS script.
     * Checks database settings and visibility rules (like guests only and dismissed state cookies).
     */
    function renderPopupAnnouncementHtml(?PDO $pdo, ?array $settings = null): string
    {
        if ($settings === null) {
            $settings = [];
            if (function_exists('getAdminSettings') && $pdo instanceof PDO) {
                try {
                    $settings = getAdminSettings($pdo);
                } catch (Throwable $e) {
                    $settings = [];
                }
            }
        }

        $enabled = ($settings['popup_announcement_enabled'] ?? '0') === '1';
        if (!$enabled) {
            return '';
        }

        $target = $settings['popup_announcement_target'] ?? 'all';
        $isGuest = empty($_SESSION['_auth_user_id']);
        if ($target === 'guests' && !$isGuest) {
            return '';
        }
        if ($target === 'members' && $isGuest) {
            return '';
        }

        $title = trim((string)($settings['popup_announcement_title'] ?? 'Önemli Duyuru'));
        $content = trim((string)($settings['popup_announcement_content'] ?? ''));
        $buttonText = trim((string)($settings['popup_announcement_button_text'] ?? 'Kapat'));
        $actionText = trim((string)($settings['popup_announcement_action_text'] ?? ''));
        $actionUrl = trim((string)($settings['popup_announcement_action_url'] ?? ''));
        $cookieDays = (int)($settings['popup_announcement_cookie_days'] ?? 1);

        $type = trim((string)($settings['popup_announcement_type'] ?? 'info'));
        $strict = ($settings['popup_announcement_strict'] ?? '0') === '1';
        $timer = (int)($settings['popup_announcement_timer'] ?? 0);

        if ($content === '') {
            return '';
        }

        $typeConfigs = [
            'info' => [
                'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
                'icon' => 'bi-info-circle-fill',
                'shadow' => 'rgba(59, 130, 246, 0.3)',
                'action_bg' => '#3b82f6',
                'action_hover' => '#2563eb',
            ],
            'success' => [
                'gradient' => 'linear-gradient(135deg, #10b981 0%, #047857 100%)',
                'icon' => 'bi-gift-fill',
                'shadow' => 'rgba(16, 185, 129, 0.3)',
                'action_bg' => '#10b981',
                'action_hover' => '#059669',
            ],
            'warning' => [
                'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                'icon' => 'bi-exclamation-triangle-fill',
                'shadow' => 'rgba(245, 158, 11, 0.3)',
                'action_bg' => '#f59e0b',
                'action_hover' => '#d97706',
            ],
            'danger' => [
                'gradient' => 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)',
                'icon' => 'bi-exclamation-octagon-fill',
                'shadow' => 'rgba(239, 68, 68, 0.3)',
                'action_bg' => '#ef4444',
                'action_hover' => '#dc2626',
            ],
        ];

        $cfg = $typeConfigs[$type] ?? $typeConfigs['info'];

        // Generate a content hash so that any change in Title or Content will automatically reset the cookie dismiss state.
        $contentHash = substr(md5($title . $content), 0, 8);
        $nonceAttr = function_exists('appCspNonceAttr') ? appCspNonceAttr() : '';

        ob_start();
        ?>
        <!-- Popup Announcement -->
        <style<?= $nonceAttr ?>>
            .popup-announcement-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(15, 23, 42, 0.65);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.4s cubic-bezier(0.25, 1, 0.5, 1), visibility 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            }

            .popup-announcement-overlay.is-active {
                opacity: 1;
                visibility: visible;
            }

            .popup-announcement-card {
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                width: 90%;
                max-width: 480px;
                padding: 36px 32px 32px 32px;
                position: relative;
                border: 1px solid rgba(226, 232, 240, 0.8);
                transform: scale(0.9) translateY(20px);
                opacity: 0;
                transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s cubic-bezier(0.25, 1, 0.5, 1);
                overflow: hidden;
            }

            .popup-announcement-overlay.is-active .popup-announcement-card {
                transform: scale(1) translateY(0);
                opacity: 1;
            }

            .popup-announcement-overlay.is-closing .popup-announcement-card {
                transform: scale(0.95) translateY(10px);
                opacity: 0;
                transition: transform 0.3s ease, opacity 0.3s ease;
            }

            .popup-announcement-close-btn {
                position: absolute;
                top: 16px;
                right: 16px;
                background: none;
                border: none;
                font-size: 28px;
                line-height: 1;
                color: #94a3b8;
                cursor: pointer;
                transition: color 0.2s ease, transform 0.2s ease;
                padding: 4px 8px;
                border-radius: 50%;
                z-index: 10;
            }

            .popup-announcement-close-btn:hover {
                color: #475569;
                transform: rotate(90deg);
            }

            .popup-announcement-progress-container {
                position: absolute;
                top: 1px;
                left: 1px;
                right: 1px;
                height: 4px;
                background-color: #f1f5f9;
                border-top-left-radius: 15px;
                border-top-right-radius: 15px;
                overflow: hidden;
            }

            .popup-announcement-progress-bar {
                height: 100%;
                width: 100%;
                background: <?= $cfg['gradient'] ?>;
                transform-origin: left;
                transform: scaleX(1);
            }

            .popup-announcement-overlay.is-active .popup-announcement-progress-bar {
                transform: scaleX(0);
                transition: transform <?= $timer ?>s linear;
            }

            .popup-announcement-icon-wrapper {
                width: 64px;
                height: 64px;
                background: <?= $cfg['gradient'] ?>;
                color: #ffffff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px auto;
                font-size: 28px;
                box-shadow: 0 10px 15px -3px <?= $cfg['shadow'] ?>;
                animation: popup-pulse 2s infinite;
            }

            @keyframes popup-pulse {
                0% {
                    box-shadow: 0 0 0 0 <?= str_replace('0.3', '0.4', $cfg['shadow']) ?>;
                }
                70% {
                    box-shadow: 0 0 0 10px <?= str_replace('0.3', '0', $cfg['shadow']) ?>;
                }
                100% {
                    box-shadow: 0 0 0 0 <?= str_replace('0.3', '0', $cfg['shadow']) ?>;
                }
            }

            .popup-announcement-card-title {
                font-family: inherit;
                font-weight: 700;
                font-size: 22px;
                color: #0f172a;
                text-align: center;
                margin: 0 0 16px 0;
                line-height: 1.3;
            }

            .popup-announcement-card-body {
                font-family: inherit;
                font-size: 15px;
                line-height: 1.6;
                color: #475569;
                margin-bottom: 24px;
                text-align: center;
                max-height: 250px;
                overflow-y: auto;
                padding-right: 4px;
            }

            /* Rich Text Editor Elements styling */
            .popup-announcement-card-body p {
                margin-bottom: 12px;
            }
            .popup-announcement-card-body p:last-child {
                margin-bottom: 0;
            }
            .popup-announcement-card-body strong {
                font-weight: 600;
                color: #0f172a;
            }
            .popup-announcement-card-body a {
                color: <?= $cfg['action_bg'] ?>;
                text-decoration: underline;
            }
            .popup-announcement-card-body a:hover {
                color: <?= $cfg['action_hover'] ?>;
            }
            .popup-announcement-card-body ul, .popup-announcement-card-body ol {
                text-align: left;
                padding-left: 20px;
                margin-bottom: 16px;
            }
            .popup-announcement-card-body li {
                margin-bottom: 6px;
            }
            .popup-announcement-card-body blockquote {
                border-left: 4px solid #cbd5e1;
                padding-left: 12px;
                margin: 12px 0;
                color: #64748b;
                font-style: italic;
            }

            .popup-announcement-card-body::-webkit-scrollbar {
                width: 6px;
            }
            .popup-announcement-card-body::-webkit-scrollbar-track {
                background: transparent;
            }
            .popup-announcement-card-body::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 3px;
            }

            .popup-announcement-card-footer {
                display: flex;
                gap: 12px;
                justify-content: center;
            }

            .popup-announcement-btn {
                font-family: inherit;
                font-weight: 600;
                font-size: 14px;
                padding: 10px 24px;
                border-radius: 8px;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .popup-announcement-btn-close {
                background-color: #f1f5f9;
                color: #475569;
            }

            .popup-announcement-btn-close:hover {
                background-color: #e2e8f0;
                color: #0f172a;
            }

            .popup-announcement-btn-action {
                background-color: <?= $cfg['action_bg'] ?>;
                color: #ffffff;
                box-shadow: 0 4px 6px -1px <?= $cfg['shadow'] ?>;
            }

            .popup-announcement-btn-action:hover {
                background-color: <?= $cfg['action_hover'] ?>;
                box-shadow: 0 10px 15px -3px <?= $cfg['shadow'] ?>;
                transform: translateY(-1px);
            }

            body.popup-announcement-open {
                overflow: hidden !important;
            }

            html[data-theme="dark"] .popup-announcement-card {
                background-color: #1e293b;
                border-color: #334155;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            }

            html[data-theme="dark"] .popup-announcement-card-title {
                color: #f8fafc;
            }

            html[data-theme="dark"] .popup-announcement-card-body {
                color: #94a3b8;
            }

            html[data-theme="dark"] .popup-announcement-card-body strong {
                color: #f8fafc;
            }

            html[data-theme="dark"] .popup-announcement-card-body::-webkit-scrollbar-thumb {
                background: #475569;
            }

            html[data-theme="dark"] .popup-announcement-progress-container {
                background-color: #334155;
            }

            html[data-theme="dark"] .popup-announcement-btn-close {
                background-color: #334155;
                color: #cbd5e1;
            }

            html[data-theme="dark"] .popup-announcement-btn-close:hover {
                background-color: #475569;
                color: #f8fafc;
            }
        </style>

        <div id="popupAnnouncementModal" class="popup-announcement-overlay" data-cookie-days="<?= $cookieDays ?>" data-popup-hash="<?= $contentHash ?>" data-popup-strict="<?= $strict ? '1' : '0' ?>" data-popup-timer="<?= $timer ?>" role="dialog" aria-modal="true" aria-labelledby="popupAnnouncementTitle">
            <div class="popup-announcement-card">
                <?php if ($timer > 0): ?>
                    <div class="popup-announcement-progress-container">
                        <div class="popup-announcement-progress-bar" id="popupProgressBar"></div>
                    </div>
                <?php endif; ?>
                <?php if (!$strict): ?>
                    <button class="popup-announcement-close-btn" aria-label="Kapat" type="button">&times;</button>
                <?php endif; ?>
                <div class="popup-announcement-icon-wrapper">
                    <i class="bi <?= $cfg['icon'] ?>"></i>
                </div>
                <h3 id="popupAnnouncementTitle" class="popup-announcement-card-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="popup-announcement-card-body">
                    <?= $content ?>
                </div>
                <div class="popup-announcement-card-footer">
                    <button type="button" class="popup-announcement-btn popup-announcement-btn-close" data-popup-dismiss><?= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php if ($actionText !== '' && $actionUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="popup-announcement-btn popup-announcement-btn-action"><?= htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script<?= $nonceAttr ?>>
            (function() {
                const modal = document.getElementById('popupAnnouncementModal');
                if (!modal) return;
                
                const hash = modal.getAttribute('data-popup-hash') || 'default';
                const cookieName = 'popup_dismissed_' + hash;
                const cookieDays = parseInt(modal.getAttribute('data-cookie-days') || '1', 10);
                const isStrict = modal.getAttribute('data-popup-strict') === '1';
                const timerSeconds = parseInt(modal.getAttribute('data-popup-timer') || '0', 10);

                function getCookie(name) {
                    const value = "; " + document.cookie;
                    const parts = value.split("; " + name + "=");
                    if (parts.length === 2) return parts.pop().split(";").shift();
                    return null;
                }

                function setCookie(name, value, days) {
                    let expires = "";
                    if (days > 0) {
                        const date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = "; expires=" + date.toUTCString();
                    }
                    document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
                }

                if (cookieDays > 0 && getCookie(cookieName)) {
                    return;
                }

                setTimeout(function() {
                    modal.classList.add('is-active');
                    document.body.classList.add('popup-announcement-open');
                }, 600);

                if (timerSeconds > 0) {
                    setTimeout(function() {
                        if (modal.classList.contains('is-active')) {
                            dismissPopup();
                        }
                    }, 600 + (timerSeconds * 1000));
                }

                function dismissPopup() {
                    modal.classList.remove('is-active');
                    modal.classList.add('is-closing');
                    document.body.classList.remove('popup-announcement-open');
                    if (cookieDays > 0) {
                        setCookie(cookieName, '1', cookieDays);
                    }
                    setTimeout(function() {
                        modal.style.display = 'none';
                    }, 400);
                }

                const closeBtn = modal.querySelector('.popup-announcement-close-btn');
                const dismissBtns = modal.querySelectorAll('[data-popup-dismiss]');

                if (closeBtn) {
                    closeBtn.addEventListener('click', dismissPopup);
                }
                dismissBtns.forEach(function(btn) {
                    btn.addEventListener('click', dismissPopup);
                });

                if (!isStrict) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            dismissPopup();
                        }
                    });
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                            dismissPopup();
                        }
                    });
                }
            })();
        </script>
        <?php
        return trim((string)ob_get_clean());
    }
}
