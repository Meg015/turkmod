<?php

declare(strict_types=1);

/**
 * Proje bootstrap / front controller yardimcilari.
 *
 * Bu dosya kademeli olarak olusturulmus genis bir bootstrap dosyasidir ve icinde
 * birkac mantiksal katman vardir:
 *
 *   1) Autoload + modul helper yukleme
 *   2) .env ayarlarinin parse edilmesi
 *   3) Guvenlik header'lari (CSP, COEP/CORP, HSTS vb.)
 *   4) Olaganande host / HTTPS dogrulama (route-filters.php'de)
 *   5) Oturum ve auth gate (maintenance / ban restrictions)
 *   6) Rota catalog (kullanisli URL -> handler/eslestirme tablosu)
 *   7) renderPagination / renderEmptyState gibi ortak UI yardimcilari
 *
 * Standartlik acisindan idealinde her katman kendi dosyasina tasinmalidir
 * (bootstrap.php, security.php, routes.php vb.) fakat bu dosya halihazirda
 * cok sayida cagri yeri tarafindan `require_once init.php` ile kullanildigi icin
 * bu refaktorun test kapsamli olarak yapilmasi onerilir. Asagidaki `require_once`
 * sirasi, fonksiyon tanimlarindaki `if (!function_exists(...))` muhafazalariyla
 * birlikte urun ortaminda guvenle calismaktadir.
 */

// Load custom PSR-4 autoloader (replaces vendor/autoload.php).
// vendor/ contains only dev dependencies (phpunit), no production packages.
require_once __DIR__ . "/autoloader.php";

// Asset versioning — single timestamp per request instead of per-file filemtime().
define('ASSET_VERSION_METHOD', 'manual');
define('ASSET_VERSION_MANUAL', (string) time());

// Base helpers
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/UiStyleHelpers.php";
require_once __DIR__ . "/SecurityHelpers.php";
require_once __DIR__ . "/InstallGuard.php";

installGuardRedirectIfNeeded();

// Güvenlik ve logging sistemleri (Sadece fonksiyonlar)
require_once __DIR__ . "/RateLimitHelpers.php";
require_once __DIR__ . "/ActivityLoggingHelpers.php";

require_once __DIR__ . "/ApiResponse.php";
require_once __DIR__ . "/Database.php";

// Load .env configuration before bootstrapping handlers that inspect runtime env.
$envConfig = \App\Core\Database::getEnvConfig();
foreach ($envConfig as $envKey => $envValue) {
    if (!is_string($envKey)) {
        continue;
    }

    if (!array_key_exists($envKey, $_ENV)) {
        $_ENV[$envKey] = (string) $envValue;
    }
}

// `init.php` applies headers explicitly. Disable SecurityHeaders autostart here.
if (!defined('SECURITY_HEADERS_MANUAL')) {
    define('SECURITY_HEADERS_MANUAL', true);
}

require_once __DIR__ . "/CsrfProtection.php";
require_once __DIR__ . "/Logger.php";
require_once __DIR__ . "/SecurityLogger.php";
require_once __DIR__ . "/SecurityHeaders.php";
require_once __DIR__ . "/ErrorHandler.php";
require_once __DIR__ . "/ThemeManager.php";
require_once __DIR__ . "/TemplateRenderer.php";
require_once __DIR__ . "/PublicThemeRenderer.php";
require_once __DIR__ . "/SeoPublicPages.php";
require_once __DIR__ . "/ThemeConverter.php";
require_once __DIR__ . "/SessionSecurity.php";
require_once __DIR__ . "/UploadSecurity.php";

require_once __DIR__ . "/src/Engine/UserActivity/Legacy/helpers.php";
require_once __DIR__ . "/notifications.php";
require_once __DIR__ . "/src/Modules/Reports/Legacy/helpers.php";

// Modüller (sadece init.php'de tanımlı olmayan fonksiyonları yükle)
require_once __DIR__ . "/src/Engine/Categories/Legacy/helpers.php";
require_once __DIR__ . "/src/Engine/Topics/Legacy/helpers.php";
require_once __DIR__ . "/src/Engine/Topics/Legacy/core_helpers.php";
require_once __DIR__ . "/src/Engine/Comments/Legacy/helpers.php";
require_once __DIR__ . "/src/Engine/Email/Legacy/helpers.php";
require_once __DIR__ . "/src/Engine/Sidebar/Legacy/helpers.php";
require_once __DIR__ . "/src/Engine/Seo/Legacy/legacy-redirect-helpers.php";
require_once __DIR__ . "/src/Engine/Users/Legacy/users-helpers.php";

// Admin helpers (getAdminSettings, adminSettingDefinitions, vb.)
if (file_exists(__DIR__ . "/../admin/helpers.php")) {
    require_once __DIR__ . "/../admin/helpers.php";
}

// Application constants & logger
require_once __DIR__ . "/lib/constants.php";
require_once __DIR__ . "/lib/logger.php";

/**
 * Render the unified empty-state partial.
 *
 * @param string $title       Bold one-liner (e.g. "Bu kategoride içerik yok").
 * @param string $description Optional smaller subtext.
 * @param string $icon        Optional Bootstrap Icons class (defaults to bi-inbox).
 * @param string $html        Optional pre-escaped HTML body (overrides title/description).
 * @param array<int,array<string,string>> $actions Optional action links/buttons.
 */
function renderEmptyState(
    string $title,
    string $description = "",
    string $icon = "bi-inbox",
    string $html = "",
    array $actions = [],
): string {
    $emptyState = [
        "title" => $title,
        "description" => $description,
        "icon" => $icon,
        "html" => $html,
        "actions" => $actions,
    ];
    ob_start();
    include __DIR__ . "/partials/empty-state.php";
    return (string) ob_get_clean();
}

if (!function_exists('appConfiguredUrl')) {
    function appConfiguredUrl(?array $envConfig = null): string
    {
        $envConfig = $envConfig ?? [];
        $candidate = trim((string) ($envConfig['APP_URL'] ?? ''));
        if ($candidate === '') {
            return '';
        }

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        return $scheme . '://' . $host . $port;
    }
}

if (!function_exists('appEnvBool')) {
    function appEnvBool(array $envConfig, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $envConfig)) {
            return $default;
        }

        return in_array(
            strtolower(trim((string) $envConfig[$key])),
            ['1', 'true', 'yes', 'on'],
            true,
        );
    }
}

if (!function_exists('appSanitizeHostHeader')) {
    function appSanitizeHostHeader(string $host): string
    {
        $host = strtolower(trim(str_replace(["\r", "\n"], '', $host)));
        if ($host === '') {
            return '';
        }

        if (preg_match('/^(?:\[[a-f0-9:]+\]|[a-z0-9.-]+)(?::\d{1,5})?$/i', $host) !== 1) {
            return '';
        }

        return $host;
    }
}

if (!function_exists('appIsLocalHost')) {
    function appIsLocalHost(string $host): bool
    {
        return preg_match('/^(?:localhost|127\.0\.0\.1|\[?::1\]?)(?::\d{1,5})?$/i', $host) === 1;
    }
}

if (!function_exists('appParseTrustedHosts')) {
    function appParseTrustedHosts(?array $envConfig = null): array
    {
        $envConfig = $envConfig ?? [];
        $trustedHosts = array_filter(array_map(
            static function (string $raw): string {
                $raw = trim($raw);
                if ($raw === '') {
                    return '';
                }

                if (str_contains($raw, '://')) {
                    $host = (string) parse_url($raw, PHP_URL_HOST);
                    $port = parse_url($raw, PHP_URL_PORT);
                    if ($host === '') {
                        return '';
                    }

                    return strtolower($host) . ($port ? ':' . (int) $port : '');
                }

                return appSanitizeHostHeader($raw);
            },
            explode(',', (string) ($envConfig['APP_TRUSTED_HOSTS'] ?? '')),
        ));

        return array_values(array_unique($trustedHosts));
    }
}

if (!function_exists('appTrustedHostFromRequest')) {
    function appTrustedHostFromRequest(
        bool $allowRequestHostFallback = false,
        ?array $envConfig = null,
        ?string $hostHeader = null,
    ): string {
        $envConfig = $envConfig ?? [];
        $configuredUrl = appConfiguredUrl($envConfig);
        if ($configuredUrl !== '') {
            $host = (string) parse_url($configuredUrl, PHP_URL_HOST);
            $port = parse_url($configuredUrl, PHP_URL_PORT);
            if ($host !== '') {
                return strtolower($host) . ($port ? ':' . (int) $port : '');
            }
        }

        $candidate = appSanitizeHostHeader((string) ($hostHeader ?? ($_SERVER['HTTP_HOST'] ?? '')));
        if ($candidate === '') {
            return 'localhost';
        }

        $isLocalHost = appIsLocalHost($candidate);
        $trustRequestHostFallback = appEnvBool($envConfig, 'APP_TRUST_REQUEST_HOST_FALLBACK', false);
        if ($isLocalHost || ($allowRequestHostFallback && $trustRequestHostFallback)) {
            return $candidate;
        }

        $trustedHosts = appParseTrustedHosts($envConfig);

        $candidateHostOnly = strtolower((string) parse_url('http://' . $candidate, PHP_URL_HOST));
        $trustedHostOnly = array_values(array_unique(array_map(
            static fn(string $trusted): string => strtolower((string) parse_url('http://' . $trusted, PHP_URL_HOST)),
            $trustedHosts,
        )));

        if (in_array($candidate, $trustedHosts, true) || ($candidateHostOnly !== '' && in_array($candidateHostOnly, $trustedHostOnly, true))) {
            return $candidate;
        }

        return 'localhost';
    }
}

if (!function_exists('appRequestScheme')) {
    function appRequestScheme(?array $envConfig = null): string
    {
        $envConfig = $envConfig ?? [];
        $forceHttps = appEnvBool($envConfig, 'APP_FORCE_HTTPS', false);
        $trustedProxyHttps = function_exists("isTrustedProxyAddress")
            && isTrustedProxyAddress((string) ($_SERVER["REMOTE_ADDR"] ?? ""))
            && strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? '')) === "https";

        if ($forceHttps || $trustedProxyHttps || (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")) {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('appPublicBaseUrl')) {
    function appPublicBaseUrl(
        bool $allowRequestHostFallback = false,
        ?string $baseUri = null,
        ?array $envConfig = null,
    ): string {
        $envConfig = $envConfig ?? [];
        $configuredUrl = appConfiguredUrl($envConfig);
        if ($baseUri === null || $baseUri === '') {
            $baseUri = function_exists('base_uri') ? base_uri() : (string) ($GLOBALS['baseUri'] ?? '');
        }
        $path = rtrim((string) $baseUri, '/');
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl . $path, '/');
        }

        $scheme = appRequestScheme($envConfig);
        $host = appTrustedHostFromRequest($allowRequestHostFallback, $envConfig);

        return rtrim($scheme . '://' . $host . $path, '/');
    }
}

// Error reporting based on APP_DEBUG
$appDebug = appEnvBool($envConfig, "APP_DEBUG", false);
if ($appDebug) {
    ini_set("display_errors", "1");
    error_reporting(E_ALL);
} else {
    ini_set("display_errors", "0");
    error_reporting(0);
}

// Session security settings
$skipSessionBootstrap = !empty($GLOBALS['_skip_session_bootstrap']);
if (!$skipSessionBootstrap) {
    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $sessionCookieName = session_name();
    $hasSessionCookie = $sessionCookieName !== '' && isset($_COOKIE[$sessionCookieName]);
    $requestPathForSession = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPathForSession = str_replace('\\', '/', $requestPathForSession);
    $baseUriForSession = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    if ($baseUriForSession === '' && !empty($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
        if ($scriptDir !== '' && $scriptDir !== '.') {
            $baseUriForSession = $scriptDir;
        }
    }
    if ($baseUriForSession !== '' && ($requestPathForSession === $baseUriForSession || str_starts_with($requestPathForSession, $baseUriForSession . '/'))) {
        $requestPathForSession = substr($requestPathForSession, strlen($baseUriForSession));
        if ($requestPathForSession === '') {
            $requestPathForSession = '/';
        }
    }

    // Keep truly anonymous first-hit listing requests stateless to reduce TTFB and avoid unnecessary session cookies.
    $isStatelessHomeRequest = in_array($requestMethod, ['GET', 'HEAD'], true)
        && !$hasSessionCookie
        && ($requestPathForSession === '/' || $requestPathForSession === '/index.php');
    if ($isStatelessHomeRequest) {
        $skipSessionBootstrap = true;
        $GLOBALS['_stateless_home_request'] = true;
        // Defensive hardening: if any downstream helper accidentally starts a session,
        // prevent emitting a session cookie on anonymous default-home requests.
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.use_trans_sid', '0');
    }
}

if (!$skipSessionBootstrap && session_status() === PHP_SESSION_NONE) {
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Lax");
    ini_set("session.use_strict_mode", "1");
    $isHttps =
        (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        appEnvBool($envConfig, "APP_FORCE_HTTPS", false);
    if ($isHttps) {
        ini_set("session.cookie_secure", "1");
    }
    session_cache_limiter(''); // Prevent PHP from sending default session cache headers
    session_start();

    // Session fixation koruması için login işlemlerinde
    // session_regenerate_id(true) çağrılmalı (auth modülünde yapılacak)
}

// Force HTTPS if configured
$forceHttps = appEnvBool($envConfig, "APP_FORCE_HTTPS", false);
$trustedProxyHttps = function_exists("isTrustedProxyAddress")
    && isTrustedProxyAddress((string)($_SERVER["REMOTE_ADDR"] ?? ""))
    && strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https";
if (
    $forceHttps &&
    empty($_SERVER["HTTPS"]) &&
    !$trustedProxyHttps
) {
    $redirectHost = appTrustedHostFromRequest(true, $envConfig);
    $requestPath = (string) ($_SERVER["REQUEST_URI"] ?? "/");
    $requestPath = str_replace(["\r", "\n"], '', $requestPath);
    if ($requestPath === '' || $requestPath[0] !== '/') {
        $requestPath = '/' . ltrim($requestPath, '/');
    }

    header(
        "Location: https://" . $redirectHost . $requestPath,
        true,
        301,
    );
    exit();
}

// Security headers are applied after policy helpers are built.
// Cache-Control: Sayfa bazında ayarlanacak (index.php, topic.php vb.)
// Varsayılan: no-cache (admin ve dinamik sayfalar için)

if (!function_exists('appCspNonce')) {
    function appCspNonce(): string
    {
        $existing = (string) ($GLOBALS['_csp_nonce'] ?? '');
        if ($existing !== '') {
            return $existing;
        }

        if (class_exists(\App\Core\Security\Nonce::class)) {
            $existing = \App\Core\Security\Nonce::generate(18);
        } else {
            $existing = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }

        $GLOBALS['_csp_nonce'] = $existing;

        return $existing;
    }
}

if (!function_exists('appCspNonceAttr')) {
    function appCspNonceAttr(): string
    {
        return ' nonce="' . htmlspecialchars(appCspNonce(), ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('buildContentSecurityPolicy')) {
    function buildContentSecurityPolicy(bool $appDebug, ?array $envConfig = null): string
    {
        $envConfig = $envConfig ?? [];
        // Performance/Security (#26): In production, strict nonce mode is default.
        // unsafe-inline is only allowed when explicitly enabled for development.
        $appEnv = strtolower(trim((string) ($envConfig['APP_ENV'] ?? 'production')));
        $isProduction = in_array($appEnv, ['production', 'prod'], true);
        $strictNonceMode = appEnvBool($envConfig, 'APP_CSP_STRICT_NONCE', $isProduction);
        $allowUnsafeInline = appEnvBool($envConfig, 'APP_CSP_ALLOW_UNSAFE_INLINE', !$strictNonceMode);
        $allowUnsafeInlineStyles = appEnvBool($envConfig, 'APP_CSP_ALLOW_UNSAFE_INLINE_STYLES', !$strictNonceMode);
        $nonceValue = appCspNonce();

        $scriptSources = ["'self'", "'nonce-" . $nonceValue . "'", 'https://cdn.quilljs.com', 'https://cdn.jsdelivr.net'];
        if ($allowUnsafeInline) {
            $scriptSources[] = "'unsafe-inline'";
        }
        if ($appDebug) {
            $scriptSources[] = "'unsafe-eval'";
        }

        $styleSources = ["'self'", "'nonce-" . $nonceValue . "'", 'https://cdn.quilljs.com', 'https://cdn.jsdelivr.net'];
        if ($allowUnsafeInlineStyles) {
            $styleSources[] = "'unsafe-inline'";
        }

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'object-src' => ["'none'"],
            'script-src' => $scriptSources,
            'style-src' => $styleSources,
            'style-src-attr' => ["'unsafe-inline'"],
            'font-src' => ["'self'", 'data:'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'", 'ws:', 'wss:', 'https://cdn.jsdelivr.net', 'https://cdn.quilljs.com', 'https:'],
            'frame-src' => ["'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'],
            'media-src' => ["'self'", 'https:', 'data:'],
        ];

        $parts = [];
        foreach ($directives as $directive => $sources) {
            $cleanedSources = array_values(array_unique(array_filter(array_map('strval', $sources), static fn (string $source): bool => trim($source) !== '')));
            if ($cleanedSources === []) {
                continue;
            }

            $parts[] = $directive . ' ' . implode(' ', $cleanedSources);
        }

        return implode('; ', $parts) . ';';
    }
}

if (!function_exists('buildSecurityHeaders')) {
    function buildSecurityHeaders(bool $appDebug, bool $forceHttps, ?array $envConfig = null): array
    {
        $envConfig = $envConfig ?? [];
        $headers = [
            "Permissions-Policy: geolocation=(), microphone=(), camera=()",
            "Cross-Origin-Embedder-Policy: require-corp",
            "Cross-Origin-Opener-Policy: same-origin",
            "Cross-Origin-Resource-Policy: same-origin",
            "X-Content-Type-Options: nosniff",
            "X-Frame-Options: SAMEORIGIN",
            "X-XSS-Protection: 1; mode=block",
            "Referrer-Policy: strict-origin-when-cross-origin",
            "Content-Security-Policy: " . buildContentSecurityPolicy($appDebug, $envConfig),
        ];

        if ($forceHttps) {
            $headers[] = "Strict-Transport-Security: max-age=31536000; includeSubDomains";
        }

        return $headers;
    }
}

// Security headers: use middleware for route-based headers, plus global bootstrap headers.
// Middleware handles route-based headers via SecurityHeadersMiddleware in routeGroupCatalog().
// Here we emit bootstrap-time headers (those needed before route dispatch).
$securityMiddleware = \App\Core\Security\SecurityHeadersMiddleware::fromEnvConfig($envConfig);
$bootstrapHeaders = $securityMiddleware->buildHeaders();

// Report-To header for CSP violation reporting
$reportTo = $securityMiddleware->getReportToConfig();
if ($reportTo !== null) {
    header('Report-To: ' . json_encode($reportTo, JSON_UNESCAPED_SLASHES));
}

foreach ($bootstrapHeaders as $name => $value) {
    header($name . ': ' . $value);
}

// Cache-Control is still page-specific in index/topic handlers.
if (!isset($GLOBALS['_cache_control_set'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Base URI is auto-detected from the install folder so folder renames do not
// require manual env edits.
$baseUri = function_exists('base_uri') ? base_uri() : '';

function renderSystemErrorPage(string $title, string $description, string $baseUri, string $statusLabel = 'Sistem hatası'): string
{
    $themeErrorCssPath = dirname(__DIR__) . '/assets/css/theme-error.css';
    $themeErrorCssHref = rtrim($baseUri, '/') . '/assets/css/theme-error.css?v=' . rawurlencode((string) (is_file($themeErrorCssPath) ? filemtime($themeErrorCssPath) : time()));

    return '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
        htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
        '</title><link rel="stylesheet" href="' .
        htmlspecialchars($themeErrorCssHref, ENT_QUOTES, 'UTF-8') .
        '"></head><body><main class="theme-error" role="main"><section class="theme-error__card ui-empty" role="alert" aria-live="assertive"><span class="theme-error__eyebrow">' .
        htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') .
        '</span><h1>' .
        htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
        '</h1><p>' .
        htmlspecialchars($description, ENT_QUOTES, 'UTF-8') .
        '</p></section></main></body></html>';
}

if (!function_exists('appValidateProductionConfig')) {
    function appValidateProductionConfig(array $envConfig): array
    {
        $issues = [];
        $appEnv = strtolower(trim((string) ($envConfig['APP_ENV'] ?? 'production')));
        if (!in_array($appEnv, ['production', 'prod'], true)) {
            return $issues;
        }

        $configuredUrl = appConfiguredUrl($envConfig);
        if ($configuredUrl === '') {
            $issues[] = 'APP_URL must be a valid absolute http(s) URL in production.';

            return $issues;
        }

        $configuredHost = strtolower((string) parse_url($configuredUrl, PHP_URL_HOST));
        $configuredPort = parse_url($configuredUrl, PHP_URL_PORT);
        $configuredHostWithPort = $configuredHost . ($configuredPort ? ':' . (int) $configuredPort : '');
        if ($configuredHostWithPort === '') {
            $issues[] = 'APP_URL host could not be resolved.';

            return $issues;
        }

        // Allow localhost-style production configs in local/staging clones.
        if (appIsLocalHost($configuredHostWithPort)) {
            return $issues;
        }

        $rawTrustedHosts = array_values(array_filter(
            array_map('trim', explode(',', (string) ($envConfig['APP_TRUSTED_HOSTS'] ?? ''))),
            static fn(string $value): bool => $value !== '',
        ));
        $trustedHosts = appParseTrustedHosts($envConfig);

        if ($rawTrustedHosts === []) {
            $issues[] = 'APP_TRUSTED_HOSTS must include at least APP_URL host in production.';

            return $issues;
        }

        if (count($trustedHosts) < count($rawTrustedHosts)) {
            $issues[] = 'APP_TRUSTED_HOSTS includes malformed entries; check host list formatting.';
        }

        $trustedHostOnly = array_values(array_unique(array_map(
            static fn(string $trusted): string => strtolower((string) parse_url('http://' . $trusted, PHP_URL_HOST)),
            $trustedHosts,
        )));

        if (!in_array($configuredHostWithPort, $trustedHosts, true) && !in_array($configuredHost, $trustedHostOnly, true)) {
            $issues[] = 'APP_TRUSTED_HOSTS must contain the APP_URL host.';
        }

        return $issues;
    }
}

$productionConfigIssues = appValidateProductionConfig($envConfig);
if ($productionConfigIssues !== []) {
    foreach ($productionConfigIssues as $productionConfigIssue) {
        error_log('Production config validation issue: ' . $productionConfigIssue);
    }

    $warnOnly = appEnvBool($envConfig, 'APP_PROD_CONFIG_WARN_ONLY', false);
    if (!$warnOnly) {
        http_response_code(503);
        header('Retry-After: 300');
        echo renderSystemErrorPage(
            'Yapilandirma hatasi',
            'Uretim ortami ayarlari eksik veya gecersiz. APP_URL ve APP_TRUSTED_HOSTS degerlerini duzeltip tekrar deneyin.',
            $baseUri,
            'Uretim ayari',
        );
        exit;
    }
}

// Database connection
$pdo = \App\Core\Database::connection();
if (!$pdo instanceof PDO) {
    http_response_code(503);
    header('Retry-After: 60');
    $requestPath = str_replace('\\', '/', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    $isApiRequest = preg_match('~/(api|admin/api)(/|$)~', $requestPath) === 1
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    if ($isApiRequest && function_exists('sendDatabaseUnavailable')) {
        sendDatabaseUnavailable(null);
    }

    echo renderSystemErrorPage(
        'Veritabanı bağlantısı kurulamadı',
        'Site veritabanına şu anda ulaşılamıyor. Lütfen kısa süre sonra tekrar deneyin.',
        $baseUri,
        'Bağlantı hatası'
    );
    exit;
}

// Global settings via DB
$adminSettingsGlobal = function_exists("getAdminSettings") && $pdo ? getAdminSettings($pdo) : [];
$timezone = $adminSettingsGlobal['timezone'] ?? 'Europe/Istanbul';
if ($timezone) {
    date_default_timezone_set($timezone);
}

// Global SEO Redirect Engine
require_once __DIR__ . "/route-filters.php";

/**
 * Cached settings fetcher for format helpers (avoids repeated DB hits and global state).
 * @param PDO|null $pdo
 * @return array<string, mixed>
 */
function _formatAppSettings(?PDO $pdo = null): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (isset($GLOBALS['adminSettingsGlobal']) && is_array($GLOBALS['adminSettingsGlobal']) && $GLOBALS['adminSettingsGlobal'] !== []) {
        return $cache = $GLOBALS['adminSettingsGlobal'];
    }
    if (function_exists("getAdminSettings") && $pdo) {
        return $cache = getAdminSettings($pdo);
    }
    return $cache = [];
}

function formatAppDate($dateString, $pdo = null) {
    $settings = _formatAppSettings($pdo);
    $format = $settings['date_format'] ?? 'd.m.Y';
    $time = is_numeric($dateString) ? (int)$dateString : strtotime((string)$dateString);
    return date($format, $time);
}

function formatAppDateTime($dateString, $pdo = null) {
    $settings = _formatAppSettings($pdo);
    $dateFormat = $settings['date_format'] ?? 'd.m.Y';
    $timeFormat = $settings['time_format'] ?? 'H:i';
    $time = is_numeric($dateString) ? (int)$dateString : strtotime((string)$dateString);
    return date($dateFormat . ' ' . $timeFormat, $time);
}

// Ensure security events table exists
if ($pdo) {
    ensureSecurityEventsTable($pdo);
}

// Public theme engine. Existing PHP pages stay compatible while themes can
// provide assets, safe TPL files, and future page-level overrides.
$themeManager = new ThemeManager(dirname(__DIR__), $baseUri, $appDebug);
$themeDebugEnabled = ($adminSettingsGlobal['theme_debug_mode'] ?? '0') === '1';
if ($themeDebugEnabled && !$appDebug) {
    $themeManager = new ThemeManager(dirname(__DIR__), $baseUri, true);
}
$activePublicTheme = (string) (
    $adminSettingsGlobal['theme_active_id']
    ?? $themeManager->defaultThemeId()
);
$currentSessionUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$currentSessionCanAdmin = $currentSessionUserId > 0
    && function_exists('userHasPermission')
    && userHasPermission($pdo, $currentSessionUserId, 'admin.access');
if (
    ($adminSettingsGlobal['theme_preview_enabled'] ?? '1') === '1'
    && isset($_GET['theme_preview'])
    && $currentSessionCanAdmin
) {
    $previewTheme = $themeManager->sanitizeThemeId((string) $_GET['theme_preview']);
    if ($previewTheme !== '' && $themeManager->themeExists($previewTheme)) {
        $_SESSION['_theme_preview_id'] = $previewTheme;
        $activePublicTheme = $previewTheme;
    }
} elseif (
    ($adminSettingsGlobal['theme_preview_enabled'] ?? '1') === '1'
    && $currentSessionCanAdmin
    && !empty($_SESSION['_theme_preview_id'])
) {
    $previewTheme = $themeManager->sanitizeThemeId((string) $_SESSION['_theme_preview_id']);
    if ($previewTheme !== '' && $themeManager->themeExists($previewTheme)) {
        $activePublicTheme = $previewTheme;
    }
}
$themeActivationError = null;
try {
    $themeManager->setActiveTheme($activePublicTheme);
    $activeThemeId = $themeManager->activeThemeId();
    $activeThemeSettings = json_decode((string) ($adminSettingsGlobal['theme_' . $activeThemeId . '_settings'] ?? '{}'), true);
    if (is_array($activeThemeSettings)) {
        $themeManager->setThemeSettings($activeThemeSettings);
    }
} catch (Throwable $themeActivationError) {
    if (function_exists('appLogException')) {
                appLogException($themeActivationError, [
            'source' => 'Theme activation',
            'theme_active_id' => $activePublicTheme,
        ]);
    }

    $GLOBALS['_theme_activation_error'] = $themeActivationError;
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isAdminRequest = str_contains($scriptName, '/admin/');

    if ($isAdminRequest) {
        try {
            $defaultId = $themeManager->defaultThemeId();
            $themeManager->setActiveTheme($defaultId);
            $defaultId = $themeManager->activeThemeId();
            $defaultSettings = json_decode((string) ($adminSettingsGlobal['theme_' . $defaultId . '_settings'] ?? '{}'), true);
            if (is_array($defaultSettings)) {
                $themeManager->setThemeSettings($defaultSettings);
            }
        } catch (Throwable $fallbackError) {
            if (function_exists('appLogException')) {
                appLogException($fallbackError, [
                    'source' => 'Theme activation admin recovery',
                    'theme_active_id' => $themeManager->activeThemeId(),
                ]);
            }
        }
    } else {
        http_response_code(503);
        $themeErrorTitle = $appDebug ? 'Tema etkinleştirilemedi' : 'Tema yüklenemedi';
        $themeErrorMessage = $appDebug
            ? $themeActivationError->getMessage()
            : 'Aktif tema klasörü veya zorunlu tema dosyaları bulunamadı. Public görünüm güvenli şekilde durduruldu.';
        echo renderSystemErrorPage($themeErrorTitle, $themeErrorMessage, $baseUri, 'Tema hatası');
        exit;
    }
}
$GLOBALS['themeManager'] = $themeManager;

// Seed demo data only for controlled first-run scenarios
require_once __DIR__ . "/src/Engine/Seeder/Legacy/helpers.php";
if ($pdo && ($envConfig["APP_AUTO_SEED"] ?? "false") === "true") {
    seederRun($pdo);
}

// Auth helpers are needed before the global auth/session gate.
require_once __DIR__ . "/src/Engine/Auth/Legacy/helpers.php";

if ($pdo && empty($_SESSION["_auth_user_id"]) && function_exists('authAttemptRememberLogin')) {
    authAttemptRememberLogin($pdo, $adminSettingsGlobal);
}

// Auth check helper
$isLoggedIn = !empty($_SESSION["_auth_user_id"]);
if ($isLoggedIn) {
    $isRememberSession = !empty($_SESSION['_auth_remember_session']);
    if ($isRememberSession) {
        // "Remember me" sessions should remain active until explicit logout/security invalidation.
        $_SESSION['_auth_last_activity'] = time();
    } else {
        $sessionTimeout = (int)($adminSettingsGlobal['session_timeout_minutes'] ?? 120);
        $lastActivity = $_SESSION['_auth_last_activity'] ?? time();
        if ($sessionTimeout > 0 && (time() - $lastActivity) > ($sessionTimeout * 60)) {
            logoutUser($pdo);
            $isLoggedIn = false;
        } else {
            $_SESSION['_auth_last_activity'] = time();
        }
    }
}

// Performance (#14): Throttle session refresh to every 5 minutes instead of every request.
// This saves 1 DB query per page load for authenticated users.
$sessionRefreshInterval = 300; // 5 minutes
$lastSessionRefresh = (int) ($_SESSION['_auth_last_session_refresh'] ?? 0);
if ($pdo && $isLoggedIn && function_exists('refreshAuthenticatedSession') && (time() - $lastSessionRefresh) >= $sessionRefreshInterval) {
    if (!refreshAuthenticatedSession($pdo)) {
        logoutUser($pdo);
        $isLoggedIn = false;
    } else {
        $_SESSION['_auth_last_session_refresh'] = time();
    }
}

if ($pdo && $isLoggedIn && !usersRestrictedPathAllowed($_SERVER["SCRIPT_NAME"] ?? "")) {
    try {
        $restriction = usersGetAccessRestriction($pdo, (int) $_SESSION["_auth_user_id"]);
        if ($restriction) {
            http_response_code(403);
            $fallbackCssVersion = is_file(__DIR__ . "/../assets/css/system-fallback.css")
                ? (string) filemtime(__DIR__ . "/../assets/css/system-fallback.css")
                : "1";
            $fallbackCssHref = rtrim($baseUri, "/") . "/assets/css/system-fallback.css?v=" . $fallbackCssVersion;
            $appealUrl = rtrim($baseUri, "/") . "/ban-appeals.php";
            $logoutUrl = rtrim($baseUri, "/") . "/logout.php";
            $reason = htmlspecialchars((string) $restriction["message"], ENT_QUOTES, "UTF-8");
            $title = htmlspecialchars((string) $restriction["title"], ENT_QUOTES, "UTF-8");
            $date = !empty($restriction["date"]) ? date("d.m.Y H:i", strtotime((string) $restriction["date"])) : "";
            echo '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title>';
            echo '<link rel="stylesheet" href="' . htmlspecialchars($fallbackCssHref, ENT_QUOTES, "UTF-8") . '"></head>';
            echo '<body class="system-fallback-page"><main class="ban-lock" role="dialog" aria-modal="true"><div class="ban-lock-head"><span>Hesap erişimi sınırlandı</span><h1>' . $title . '</h1></div><div class="ban-lock-body"><div class="ban-lock-row"><span class="ban-lock-label">Açıklama</span><p>' . $reason . '</p></div>';
            if ($date !== "") {
                echo '<div class="ban-lock-row"><span class="ban-lock-label">İşlem tarihi</span><p>' . htmlspecialchars($date, ENT_QUOTES, "UTF-8") . '</p></div>';
            }
            echo '<div class="ban-lock-actions"><a class="ban-primary" href="' . htmlspecialchars($appealUrl, ENT_QUOTES, "UTF-8") . '">Ban itirazlarım</a><form method="post" action="' . htmlspecialchars($logoutUrl, ENT_QUOTES, "UTF-8") . '">' . csrf_field() . '<button class="ban-secondary" type="submit">Çıkış yap</button></form></div></div></main></body></html>';
            exit;
        }
    } catch (Throwable $e) {
        appLogException($e, ["source" => "restricted_user_gate"]);
    }
}

// Bakım modu kontrolü (admin sayfaları ve login hariç)
if ($pdo) {
    $isAdminPage = str_contains($_SERVER["SCRIPT_NAME"] ?? "", "/admin/");
    $isLoginPage = function_exists("routeIsAuthPage")
        ? routeIsAuthPage((string) ($_SERVER["SCRIPT_NAME"] ?? ""))
        : in_array(
            basename((string) ($_SERVER["SCRIPT_NAME"] ?? "")),
            ["login.php", "register.php", "forgot-password.php", "reset-password.php", "giris", "kayit", "sifremi-unuttum", "sifre-sifirla"],
            true
        );
    $isAdminUser = (int) ($_SESSION['_auth_user_id'] ?? 0) > 0
        && function_exists('userHasPermission')
        && userHasPermission($pdo, (int) $_SESSION['_auth_user_id'], 'admin.access');
    if (!$isAdminPage && !$isLoginPage && !$isAdminUser) {
        try {
            $mVal = function_exists("adminSettingValue")
                ? adminSettingValue($pdo, "maintenance_mode", "0")
                : "0";
            if ($mVal === "1") {
                $mMsg =
                    "Site bakım modundadır, lütfen daha sonra tekrar deneyin.";
                try {
                    $mMsgVal = function_exists("adminSettingValue")
                        ? adminSettingValue($pdo, "maintenance_message", $mMsg)
                        : $mMsg;
                    if ($mMsgVal && $mMsgVal !== "") {
                        $mMsg = $mMsgVal;
                    }
                } catch (Throwable $e) {
                    appLogException($e, [
                        "source" => "maintenance_message_fetch",
                    ]);
                }
                http_response_code(503);
                header("Retry-After: 3600");
                $fallbackCssVersion = is_file(__DIR__ . "/../assets/css/system-fallback.css")
                    ? (string) filemtime(__DIR__ . "/../assets/css/system-fallback.css")
                    : "1";
                $fallbackCssHref = rtrim($baseUri, "/") . "/assets/css/system-fallback.css?v=" . $fallbackCssVersion;
                echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bakım Modu</title>';
                echo '<link rel="stylesheet" href="' . htmlspecialchars($fallbackCssHref, ENT_QUOTES, "UTF-8") . '"></head>';
                echo '<body class="maintenance-page"><div class="maintenance-box"><div class="maintenance-icon">🔧</div><h1>Bakım Modu</h1><p>' .
                    htmlspecialchars($mMsg) .
                    "</p></div></body></html>";
                exit();
            }
        } catch (Throwable $e) {
            appLogException($e, ["source" => "maintenance_mode_check"]);
        }
    }
}

// Admin eylem audit log (grup/ban/durum geri-alınabilir kayıt)
require_once __DIR__ . "/src/Engine/AdminAudit/Legacy/helpers.php";

// Rate limiting
if (!function_exists('renderPagination')) {
/**
 * Render compact pagination HTML with a sliding window around the active page.
 */
function renderPagination(
    int $total,
    int $page,
    int $perPage,
    string $baseUrl,
): string {
    $totalPages = (int) ceil($total / $perPage);
    if ($totalPages <= 1) {
        return "";
    }

    $page = max(1, min($page, $totalPages));
    $maxVisible = defined('PAGINATION_MAX_VISIBLE_PAGES') ? (int) PAGINATION_MAX_VISIBLE_PAGES : 5;
    $maxVisible = max(3, min($maxVisible, $totalPages));
    $separator = str_contains($baseUrl, "?") ? "&" : "?";
    $urlForPage = static function (int $targetPage) use ($baseUrl, $separator): string {
        return htmlspecialchars($baseUrl . $separator . "page=" . $targetPage, ENT_QUOTES, 'UTF-8');
    };

    $html = '<nav class="topic-pagination" aria-label="Sayfa gezinme"><ul>';

    if ($page > 1) {
        $html .= '<li><a href="' . $urlForPage($page - 1) . '" aria-label="Onceki sayfa">&laquo;</a></li>';
    }

    $start = max(1, $page - (int) floor($maxVisible / 2));
    $end = min($totalPages, $start + $maxVisible - 1);
    $start = max(1, $end - $maxVisible + 1);

    if ($start > 1) {
        $html .= '<li><a href="' . $urlForPage(1) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="pagination-gap"><span class="pagination-ellipsis" aria-hidden="true">&hellip;</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' class="active" aria-current="page"' : "";
        $html .= '<li' . $active . '><a href="' . $urlForPage($i) . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="pagination-gap"><span class="pagination-ellipsis" aria-hidden="true">&hellip;</span></li>';
        }
        $html .= '<li><a href="' . $urlForPage($totalPages) . '">' . $totalPages . '</a></li>';
    }

    if ($page < $totalPages) {
        $html .= '<li><a href="' . $urlForPage($page + 1) . '" aria-label="Sonraki sayfa">&raquo;</a></li>';
    }

    $html .= "</ul></nav>";
    return $html;
}
}

require_once __DIR__ . "/src/Engine/Auth/Legacy/rate-limit-helpers.php";

/**
 * Generate clean URL for topic or category.
 */
function routePrefixDefaults(): array
{
    return [
        "topic" => "konu",
        "category" => "kategori",
        "category_list" => "kategori",
        "profile" => "profil",
    ];
}

function routePublicStaticPathDefaults(): array
{
    return [
        "login" => "giris",
        "register" => "kayit",
        "logout" => "cikis",
        "forgot_password" => "sifremi-unuttum",
        "reset_password" => "sifre-sifirla",
        "notifications" => "bildirimler",
        "messages" => "mesajlar",
        "leaderboard" => "liderlik",
        "ban_appeals" => "ban-itiraz",
        "contact" => "iletisim",
        "upload_topic" => "konu-yukle",
        "edit_topic" => "konu-duzenle",
        "download" => "indir",
        "events" => "events",
    ];
}

function routePublicStaticPathSettingKeys(): array
{
    return [
        "login" => "route_login_path",
        "register" => "route_register_path",
        "logout" => "route_logout_path",
        "forgot_password" => "route_forgot_password_path",
        "reset_password" => "route_reset_password_path",
        "notifications" => "route_notifications_path",
        "messages" => "route_messages_path",
        "leaderboard" => "route_leaderboard_path",
        "ban_appeals" => "route_ban_appeals_path",
        "contact" => "route_contact_path",
        "upload_topic" => "route_upload_topic_path",
        "edit_topic" => "route_edit_topic_path",
        "download" => "route_download_path",
        "events" => "route_events_path",
    ];
}

function routePublicStaticLegacyAliases(): array
{
    return [
        "messages" => ["messages"],
        "leaderboard" => ["leaderboard"],
        "contact" => ["contact"],
        "upload_topic" => ["upload-topic"],
        "edit_topic" => ["edit-topic"],
        "download" => ["download"],
    ];
}

function routePublicStaticReservedSegments(): array
{
    return [
        "admin",
        "api",
        "assets",
        "database",
        "docs",
        "includes",
        "tests",
        "uploads",
        "cron",
        "install",
        "route",
        "index",
        "sitemap",
        "topic-sitemap",
        "profile-sitemap",
        "image-sitemap",
        "robots",
        "health",
    ];
}

function routePublicStaticPathSanitize(string $value): string
{
    $value = trim($value);
    $value = trim($value, "/\\ \t\n\r\0\x0B");

    if (
        $value === "" ||
        str_contains($value, "/") ||
        str_contains($value, ".")
    ) {
        return "";
    }

    $value = slugify($value);
    if ($value === "") {
        return "";
    }

    return in_array($value, routePublicStaticReservedSegments(), true)
        ? ""
        : $value;
}

function routePublicStaticPathSettings(
    ?PDO $pdo = null,
    ?array $settings = null,
): array {
    static $cache = null;
    if ($settings === null && is_array($cache)) {
        return $cache;
    }

    $defaults = routePublicStaticPathDefaults();
    $settingKeys = routePublicStaticPathSettingKeys();
    $resolved = $defaults;
    $source = is_array($settings) ? $settings : [];

    if (!is_array($settings)) {
        $pdo = $pdo ?: $GLOBALS["pdo"] ?? null;
        if (function_exists("getAdminSettings")) {
            $candidate = getAdminSettings($pdo);
            $source = is_array($candidate) ? $candidate : [];
        }
    }

    foreach ($settingKeys as $routeKey => $settingKey) {
        $candidate = routePublicStaticPathSanitize(
            (string) ($source[$settingKey] ?? ""),
        );
        if ($candidate !== "") {
            $resolved[$routeKey] = $candidate;
        }
    }

    $blocked = [];
    foreach (routePublicStaticReservedSegments() as $reserved) {
        $reserved = trim((string) $reserved);
        if ($reserved !== "") {
            $blocked[$reserved] = true;
        }
    }

    if (is_array($settings)) {
        $prefixDefaults = routePrefixDefaults();
        $prefixMap = [
            "topic" => "route_topic_prefix",
            "category" => "route_category_prefix",
            "category_list" => "route_category_list_prefix",
            "profile" => "route_profile_prefix",
        ];
        foreach ($prefixMap as $prefixKey => $settingKey) {
            $fallback = (string) ($prefixDefaults[$prefixKey] ?? $prefixKey);
            $candidate = routePrefixSanitize(
                (string) ($settings[$settingKey] ?? ""),
            );
            $segment = $candidate !== "" ? $candidate : $fallback;
            if ($segment !== "") {
                $blocked[$segment] = true;
            }
        }
    } else {
        foreach (routePrefixSettings($pdo) as $segment) {
            $segment = trim((string) $segment);
            if ($segment !== "") {
                $blocked[$segment] = true;
            }
        }
    }

    foreach ($defaults as $routeKey => $defaultPath) {
        $candidate = routePublicStaticPathSanitize(
            (string) ($resolved[$routeKey] ?? ""),
        );
        $fallback = routePublicStaticPathSanitize($defaultPath);
        if ($candidate === "" || isset($blocked[$candidate])) {
            $candidate = $fallback;
        }
        if ($candidate === "" || isset($blocked[$candidate])) {
            $base = $fallback !== "" ? $fallback : $routeKey;
            $candidate = $base;
            $suffix = 2;
            while (isset($blocked[$candidate])) {
                $candidate = $base . "-" . $suffix;
                $suffix++;
            }
        }

        $resolved[$routeKey] = $candidate;
        $blocked[$candidate] = true;
    }

    if ($settings === null) {
        $cache = $resolved;
    }

    return $resolved;
}

function routePublicStaticPathAliases(
    string $routeKey,
    ?array $settings = null,
    ?array $paths = null,
): array {
    $paths = is_array($paths)
        ? $paths
        : routePublicStaticPathSettings($GLOBALS["pdo"] ?? null, $settings);
    $defaults = routePublicStaticPathDefaults();
    $legacy = routePublicStaticLegacyAliases();

    $canonical = routePublicStaticPathSanitize(
        (string) ($paths[$routeKey] ?? ""),
    );
    if ($canonical === "") {
        return [];
    }

    $aliases = [$canonical];
    $default = routePublicStaticPathSanitize(
        (string) ($defaults[$routeKey] ?? ""),
    );
    if ($default !== "") {
        $aliases[] = $default;
    }

    foreach (($legacy[$routeKey] ?? []) as $alias) {
        $clean = routePublicStaticPathSanitize((string) $alias);
        if ($clean !== "") {
            $aliases[] = $clean;
        }
    }

    return array_values(array_unique(array_filter($aliases)));
}

function routePublicStaticPath(
    string $routeKey,
    ?array $settings = null,
): string {
    $paths = routePublicStaticPathSettings($GLOBALS["pdo"] ?? null, $settings);
    $defaults = routePublicStaticPathDefaults();

    $path = trim((string) ($paths[$routeKey] ?? ($defaults[$routeKey] ?? "")), "/");
    if ($path !== "") {
        return $path;
    }

    return trim((string) ($defaults[$routeKey] ?? ""), "/");
}

function routePublicStaticUrl(
    string $routeKey,
    string $suffix = "",
    ?array $settings = null,
): string {
    global $baseUri;

    $base = rtrim((string) ($baseUri ?? ""), "/");
    $path = routePublicStaticPath($routeKey, $settings);
    $url = $base . "/" . ltrim($path, "/");

    $suffix = trim($suffix, "/");
    if ($suffix !== "") {
        $url .= "/" . $suffix;
    }

    return $url;
}

// ────────────────────────────────────────────────────────────────────────────
// Rota catalog (route catalog)
// Tum public/auth rota eslestirmeleri ve handler metotlari bu bolumde
// tanimlanir. Cagri yerleri (route.php, init.php icinde require-once guvencesi)
// bu fonksiyonlari kullanir. Bu bolume yeni rota eklerken `routeBuildGroupedRouteCatalog`
// ve `routeRegisteredRouteCatalog` ciktilarini kontrol edin.
// ────────────────────────────────────────────────────────────────────────────

function routePublicRouteCatalog(): array
{
    $paths = routePublicStaticPathSettings($GLOBALS["pdo"] ?? null);
    $routes = [
        "" => [
            "label" => "Ana Sayfa",
            "target" => "index.php",
            "kind" => "Sayfa",
            "dispatch" => "file",
            "canonical_path" => "",
        ],
    ];

    $staticRouteDefs = [
        "login" => [
            "label" => "Giriş",
            "target" => \App\Engine\Auth\Http\LoginPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "register" => [
            "label" => "Kayıt",
            "target" => \App\Engine\Auth\Http\RegisterPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "logout" => [
            "label" => "Çıkış",
            "target" => \App\Engine\Auth\Http\LogoutAction::class,
            "kind" => "İşlem",
            "dispatch" => "handler",
        ],
        "forgot_password" => [
            "label" => "Şifremi Unuttum",
            "target" => \App\Engine\Auth\Http\ForgotPasswordPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "reset_password" => [
            "label" => "Şifre Sıfırla",
            "target" => \App\Engine\Auth\Http\ResetPasswordPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "notifications" => [
            "label" => "Bildirimler",
            "target" => \App\Modules\Notifications\Http\NotificationsPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "messages" => [
            "label" => "Mesajlar",
            "target" => \App\Modules\Messages\Http\MessagesPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "leaderboard" => [
            "label" => "Liderlik",
            "target" => \App\Modules\Leaderboard\Http\LeaderboardPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "ban_appeals" => [
            "label" => "Ban İtiraz",
            "target" => \App\Modules\BanAppeals\Http\BanAppealsPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "contact" => [
            "label" => "İletişim",
            "target" => \App\Modules\Contact\Http\ContactPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "upload_topic" => [
            "label" => "Konu Yükle",
            "target" => \App\Modules\TopicWorkflow\Http\CreateTopicPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "edit_topic" => [
            "label" => "Konu Düzenle",
            "target" => \App\Modules\TopicWorkflow\Http\EditTopicPage::class,
            "kind" => "Sayfa",
            "dispatch" => "handler",
        ],
        "download" => [
            "label" => "İndir",
            "target" => \App\Modules\TopicWorkflow\Http\DownloadAction::class,
            "kind" => "İşlem",
            "dispatch" => "handler",
        ],
    ];

    foreach ($staticRouteDefs as $routeKey => $meta) {
        $canonicalPath = trim((string) ($paths[$routeKey] ?? ""), "/");
        if ($canonicalPath === "") {
            continue;
        }

        $canonicalMeta = array_merge($meta, [
            "route_key" => $routeKey,
            "canonical_path" => $canonicalPath,
        ]);
        $routes[$canonicalPath] = $canonicalMeta;

        foreach (routePublicStaticPathAliases($routeKey, null, $paths) as $aliasPath) {
            $aliasPath = trim((string) $aliasPath, "/");
            if ($aliasPath === "" || $aliasPath === $canonicalPath || isset($routes[$aliasPath])) {
                continue;
            }

            $aliasMeta = $canonicalMeta;
            $aliasMeta["is_alias"] = true;
            $routes[$aliasPath] = $aliasMeta;
        }
    }

    $eventsBasePath = trim((string) ($paths["events"] ?? "events"), "/");
    $eventsAliases = routePublicStaticPathAliases("events", null, $paths);
    $eventPages = [
        "" => ["label" => "Etkinlikler", "target" => "includes/src/Modules/Events/Pages/index.php", "kind" => "Modül", "dispatch" => "events"],
        "wheel" => ["label" => "Çark", "target" => "includes/src/Modules/Events/Pages/wheel.php", "kind" => "Modül", "dispatch" => "events"],
        "raffle" => ["label" => "Çekiliş", "target" => "includes/src/Modules/Events/Pages/raffle.php", "kind" => "Modül", "dispatch" => "events"],
        "rewards" => ["label" => "Ödüller", "target" => "includes/src/Modules/Events/Pages/rewards.php", "kind" => "Modül", "dispatch" => "events"],
        "tasks" => ["label" => "Görevler", "target" => "includes/src/Modules/Events/Pages/tasks.php", "kind" => "Modül", "dispatch" => "events"],
    ];

    foreach ($eventPages as $suffix => $meta) {
        $canonicalPath = $eventsBasePath;
        if ($suffix !== "") {
            $canonicalPath .= "/" . $suffix;
        }
        $canonicalPath = trim($canonicalPath, "/");
        if ($canonicalPath === "") {
            continue;
        }

        $canonicalMeta = array_merge($meta, [
            "route_key" => "events",
            "canonical_path" => $canonicalPath,
        ]);
        $routes[$canonicalPath] = $canonicalMeta;

        foreach ($eventsAliases as $aliasBasePath) {
            $aliasBasePath = trim((string) $aliasBasePath, "/");
            if ($aliasBasePath === "") {
                continue;
            }

            $aliasPath = $aliasBasePath;
            if ($suffix !== "") {
                $aliasPath .= "/" . $suffix;
            }

            if ($aliasPath === $canonicalPath || isset($routes[$aliasPath])) {
                continue;
            }

            $aliasMeta = $canonicalMeta;
            $aliasMeta["is_alias"] = true;
            $routes[$aliasPath] = $aliasMeta;
        }
    }

    $routes["sitemap.xml"] = ["label" => "Site Haritası", "target" => \App\Engine\Seo\Http\SitemapIndexPage::class, "kind" => "Sistem", "dispatch" => "handler"];
    $routes["topic-sitemap.xml"] = ["label" => "Konu Site Haritası", "target" => \App\Engine\Seo\Http\TopicSitemapPage::class, "kind" => "Sistem", "dispatch" => "handler"];
    $routes["profile-sitemap.xml"] = ["label" => "Profil Site Haritası", "target" => \App\Engine\Seo\Http\ProfileSitemapPage::class, "kind" => "Sistem", "dispatch" => "handler"];
    $routes["image-sitemap.xml"] = ["label" => "Görsel Site Haritası", "target" => \App\Engine\Seo\Http\ImageSitemapPage::class, "kind" => "Sistem", "dispatch" => "handler"];
    $routes["robots.txt"] = ["label" => "Robots", "target" => \App\Engine\Seo\Http\RobotsPage::class, "kind" => "Sistem", "dispatch" => "handler"];
    $routes["health"] = ["label" => "Sağlık", "target" => \App\Engine\Seo\Http\HealthCheckPage::class, "kind" => "Sistem", "dispatch" => "handler"];

    return $routes;
}

function routeGroupCatalog(): array
{
    return [
        "public" => [
            "prefix" => "",
            "middleware" => [
                \App\Core\Security\SecurityHeadersMiddleware::class,
                \App\Core\Routing\Middleware\CsrfGuard::class,
                \App\Core\Routing\Middleware\RateLimitMiddleware::class,
                \App\Core\Routing\Middleware\AuditMiddleware::class,
            ],
        ],
        "auth" => [
            "prefix" => "",
            "middleware" => [
                \App\Core\Security\SecurityHeadersMiddleware::class,
                \App\Core\Routing\Middleware\CsrfGuard::class,
                \App\Core\Routing\Middleware\RateLimitMiddleware::class,
                \App\Core\Routing\Middleware\AuditMiddleware::class,
            ],
        ],
        "admin" => [
            "prefix" => "admin",
            "middleware" => [
                \App\Core\Security\SecurityHeadersMiddleware::class,
                \App\Core\Routing\Middleware\AdminGuard::class,
                \App\Core\Routing\Middleware\CsrfGuard::class,
                \App\Core\Routing\Middleware\AuditMiddleware::class,
            ],
        ],
        "api" => [
            "prefix" => "api",
            "middleware" => [
                \App\Core\Security\SecurityHeadersMiddleware::class,
                \App\Core\Routing\Middleware\CsrfGuard::class,
                \App\Core\Routing\Middleware\RateLimitMiddleware::class,
                \App\Core\Routing\Middleware\AuditMiddleware::class,
            ],
        ],
    ];
}

function routeAuthRoutePaths(): array
{
    $paths = routePublicStaticPathSettings($GLOBALS["pdo"] ?? null);

    return array_values(array_unique(array_filter([
        (string) ($paths["login"] ?? ""),
        (string) ($paths["register"] ?? ""),
        (string) ($paths["forgot_password"] ?? ""),
        (string) ($paths["reset_password"] ?? ""),
    ])));
}

function routeAuthPageKeyMap(): array
{
    $paths = routePublicStaticPathSettings($GLOBALS["pdo"] ?? null);
    $map = [
        "login.php" => "login",
        "register.php" => "register",
        "forgot-password.php" => "forgot_password",
        "reset-password.php" => "reset_password",
    ];

    $authRouteKeys = [
        "login" => "login",
        "register" => "register",
        "forgot_password" => "forgot_password",
        "reset_password" => "reset_password",
    ];

    foreach ($authRouteKeys as $routeKey => $pageKey) {
        foreach (routePublicStaticPathAliases($routeKey, null, $paths) as $path) {
            $cleanPath = trim((string) $path, "/");
            if ($cleanPath !== "") {
                $map[$cleanPath] = $pageKey;
            }
        }
    }

    return $map;
}

function routeAuthPageKey(string $path): string
{
    $path = trim(str_replace("\\", "/", (string) parse_url(trim($path), PHP_URL_PATH)), "/\\ \t\n\r\0\x0B");
    if ($path === "") {
        return "";
    }

    $baseUri = trim((string) ($GLOBALS["baseUri"] ?? ""), "/");
    if ($baseUri !== "") {
        $baseSegments = array_values(array_filter(explode("/", $baseUri), static fn (string $part): bool => $part !== ""));
        $pathSegments = array_values(array_filter(explode("/", $path), static fn (string $part): bool => $part !== ""));
        if ($baseSegments !== [] && array_slice($pathSegments, 0, count($baseSegments)) === $baseSegments) {
            $pathSegments = array_slice($pathSegments, count($baseSegments));
            $path = implode("/", $pathSegments);
        }
    }

    $path = strtolower($path);

    $map = routeAuthPageKeyMap();

    return (string) ($map[$path] ?? "");
}

function routeIsAuthPage(string $path): bool
{
    return routeAuthPageKey($path) !== "";
}

function routePublicRouteGroupName(string $path): string
{
    $path = trim($path, "/\\ \t\n\r\0\x0B");

    return routeIsAuthPage($path) ? "auth" : "public";
}

function routeGroupNormalizePath(string $path, string $prefix): string
{
    $path = trim($path, "/\\ \t\n\r\0\x0B");
    $prefix = trim($prefix, "/\\ \t\n\r\0\x0B");

    if ($prefix === "") {
        return $path;
    }

    if ($path === "") {
        return $prefix;
    }

    if ($path === $prefix || str_starts_with($path, $prefix . "/")) {
        return $path;
    }

    return $prefix . "/" . $path;
}

function routePublicGroupedCatalog(?string $onlyGroup = null): array
{
    $groups = routeGroupCatalog();
    $onlyGroup = $onlyGroup !== null ? trim($onlyGroup) : null;
    $routes = [];

    foreach (routePublicRouteCatalog() as $path => $route) {
        if (!is_array($route)) {
            continue;
        }

        $groupName = routePublicRouteGroupName((string) $path);
        if ($onlyGroup !== null && $groupName !== $onlyGroup) {
            continue;
        }

        $group = $groups[$groupName] ?? $groups["public"];
        $prefix = (string) ($group["prefix"] ?? "");
        $middleware = is_array($group["middleware"] ?? null) ? $group["middleware"] : [];
        $normalizedPath = routeGroupNormalizePath((string) $path, $prefix);
        $route["path"] = $normalizedPath;
        $route["group"] = $groupName;
        $route["group_prefix"] = $prefix;
        $route["group_middleware"] = $middleware;
        $routes[$normalizedPath] = $route;
    }

    return $routes;
}

function routePublicStaticFileRoutes(): array
{
    $routes = [];
    foreach (routePublicRouteCatalog() as $path => $route) {
        if (($route["dispatch"] ?? "file") === "file" && !empty($route["target"])) {
            $routes[$path] = (string) $route["target"];
        }
    }

    return $routes;
}

function routeModuleRouteCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $modulesRoot = __DIR__ . '/src/Modules';
    if (!is_dir($modulesRoot)) {
        $catalog = [];
        return $catalog;
    }

    $registry = new \App\Core\Routing\RouteRegistry(routeGroupCatalog());
    $registry->discover($modulesRoot);

    $catalog = $registry->all();
    return $catalog;
}

function routeBuildGroupedRouteCatalog(): array
{
    $groupCatalog = routeGroupCatalog();
    $routes = [];

    foreach ($groupCatalog as $groupName => $_meta) {
        $routes[$groupName] = [];
    }

    foreach (array_keys($groupCatalog) as $groupName) {
        foreach (routePublicGroupedCatalog((string) $groupName) as $path => $definition) {
            $routes[$groupName][(string) $path] = $definition;
        }
    }

    foreach (routeModuleRouteCatalog() as $moduleId => $moduleGroups) {
        if (!is_array($moduleGroups)) {
            continue;
        }

        foreach ($moduleGroups as $groupName => $groupRoutes) {
            if (!is_array($groupRoutes)) {
                continue;
            }

            if (!isset($routes[$groupName])) {
                $routes[$groupName] = [];
            }

            foreach ($groupRoutes as $path => $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                if (!isset($definition["module"])) {
                    $definition["module"] = (string) $moduleId;
                }
                $routes[$groupName][(string) $path] = $definition;
            }
        }
    }

    return $routes;
}

function routeModuleRouteSources(): array
{
    $modulesRoot = __DIR__ . "/src/Modules";
    if (!is_dir($modulesRoot)) {
        return [];
    }

    $loader = new \App\Core\Modules\ModuleLoader();
    $sources = [];
    foreach ($loader->discover($modulesRoot) as $metadata) {
        $moduleFile = (string) ($metadata["module_file"] ?? "");
        if ($moduleFile !== "" && is_file($moduleFile)) {
            $sources[$moduleFile] = (int) (@filemtime($moduleFile) ?: 0);
        }

        $routesFile = (string) ($metadata["routes"] ?? "");
        if ($routesFile !== "" && is_file($routesFile)) {
            $sources[$routesFile] = (int) (@filemtime($routesFile) ?: 0);
        }
    }

    ksort($sources);
    return $sources;
}

function routeCompiledRouteCacheFile(): string
{
    return dirname(__DIR__) . "/storage/cache/routes/compiled-routes.php";
}

function routeCompiledRouteSignature(): string
{
    $state = [
        "init_mtime" => (int) (@filemtime(__FILE__) ?: 0),
        "groups" => routeGroupCatalog(),
        "sources" => routeModuleRouteSources(),
        "public_static_paths" => routePublicStaticPathSettings($GLOBALS["pdo"] ?? null),
    ];

    $json = json_encode($state, JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        return hash("sha256", $json);
    }

    return hash("sha256", serialize($state));
}

function routeShouldUseCompiledRouteCache(): bool
{
    $envOverride = getenv("APP_ROUTE_CACHE");
    if ($envOverride !== false) {
        return in_array(strtolower(trim((string) $envOverride)), ["1", "true", "yes", "on"], true);
    }

    if (!class_exists(\App\Core\Database::class) || !method_exists(\App\Core\Database::class, "getEnvConfig")) {
        return false;
    }

    $env = \App\Core\Database::getEnvConfig();
    $appEnv = strtolower((string) ($env["APP_ENV"] ?? "local"));
    $rawValue = strtolower((string) ($env["APP_ROUTE_CACHE"] ?? ($appEnv === "production" ? "true" : "false")));

    return in_array($rawValue, ["1", "true", "yes", "on"], true);
}

function routeGroupedRouteCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    if (!routeShouldUseCompiledRouteCache()) {
        $catalog = routeBuildGroupedRouteCatalog();
        return $catalog;
    }

    $cache = new \App\Core\Routing\CompiledRouteCache(routeCompiledRouteCacheFile());
    $signature = routeCompiledRouteSignature();
    $cached = $cache->loadIfFresh($signature);
    if (is_array($cached)) {
        $catalog = $cached;
        return $catalog;
    }

    $catalog = routeBuildGroupedRouteCatalog();
    $cache->store($catalog, $signature);

    return $catalog;
}

function routeLegacyRedirectMap(): array
{
    return (new \App\Core\Routing\LegacyRedirectMap())->all();
}

function routeLegacyRedirectTarget(string $legacyPath): ?string
{
    return (new \App\Core\Routing\LegacyRedirectMap())->targetFor($legacyPath);
}

function routeRegisteredRouteCatalog(): array
{
    return [
        "groups" => routeGroupCatalog(),
        "grouped" => routeGroupedRouteCatalog(),
        "legacy_redirects" => routeLegacyRedirectMap(),
        'public' => routePublicRouteCatalog(),
        'modules' => routeModuleRouteCatalog(),
    ];
}

function routeCompatibilityDispatcher(?\App\Core\Container\Container $container = null): \App\Core\Routing\Dispatcher
{
    if ($container instanceof \App\Core\Container\Container) {
        return new \App\Core\Routing\Dispatcher($container);
    }

    return \App\Core\Bootstrap\Boot::container(dirname(__DIR__))->get(\App\Core\Routing\Dispatcher::class);
}

function routePrefixReservedSegments(): array
{
    return [
        "admin",
        "api",
        "assets",
        "database",
        "docs",
        "includes",
        "tests",
        "uploads",
        "login.php",
        "logout.php",
        "register.php",
        "forgot-password.php",
        "reset-password.php",
        "index.php",
        "topic.php",
        "category.php",
        "download.php",
        "profile.php",
        "sitemap.xml",
        "topic-sitemap.xml",
        "profile-sitemap.xml",
        "image-sitemap.xml",
        "robots.txt",
        "health",
        "health.php",
        "events",
        "giris",
        "kayit",
        "cikis",
        "sifremi-unuttum",
        "sifre-sifirla",
        "bildirimler",
        "liderlik",
        "leaderboard",
        "ban-itiraz",
        "iletisim",
        "contact",
        "konu-yukle",
        "konu-duzenle",
        "indir",
        "forums",
        "legacy-redirect.php",
        "route.php",
    ];
}

function routePrefixSanitize(string $value): string
{
    $value = trim($value);
    $value = trim($value, "/\\ \t\n\r\0\x0B");
    $value = slugify($value);

    if (
        $value === "" ||
        str_contains($value, "/") ||
        str_contains($value, ".")
    ) {
        return "";
    }

    return in_array($value, routePrefixReservedSegments(), true) ? "" : $value;
}

function routePrefixSettings(?PDO $pdo = null): array
{
    $defaults = routePrefixDefaults();
    $settings = $defaults;

    if (
        !empty($GLOBALS["routePrefixOverride"]) &&
        is_array($GLOBALS["routePrefixOverride"])
    ) {
        foreach ($defaults as $key => $default) {
            $candidate = routePrefixSanitize(
                (string) ($GLOBALS["routePrefixOverride"][$key] ?? $default),
            );
            $settings[$key] = $candidate !== "" ? $candidate : $default;
        }
        return $settings;
    }

    $pdo = $pdo ?: $GLOBALS["pdo"] ?? null;
    if (!$pdo instanceof PDO) {
        return $settings;
    }

    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $adminSettings = function_exists("getAdminSettings") ? getAdminSettings($pdo) : [];
    foreach (
        [
            "topic" => "route_topic_prefix",
            "category" => "route_category_prefix",
            "category_list" => "route_category_list_prefix",
            "profile" => "route_profile_prefix",
        ]
        as $routeKey => $settingKey
    ) {
        $candidate = routePrefixSanitize((string) ($adminSettings[$settingKey] ?? ""));
        if ($candidate !== "") {
            $settings[$routeKey] = $candidate;
        }
    }

    $coreRouteSettings = [
        "topic" => $settings["topic"] ?? $defaults["topic"],
        "category" => $settings["category"] ?? $defaults["category"],
        "profile" => $settings["profile"] ?? $defaults["profile"],
    ];
    if (count(array_unique($coreRouteSettings)) !== count($coreRouteSettings)) {
        foreach ($coreRouteSettings as $routeKey => $_routeValue) {
            $settings[$routeKey] = $defaults[$routeKey];
        }
    }

    if (in_array((string) ($settings["category_list"] ?? ""), [
        (string) ($settings["topic"] ?? ""),
        (string) ($settings["profile"] ?? ""),
    ], true)) {
        $settings["category_list"] = $defaults["category_list"];
    }

    $cache = $settings;
    return $settings;
}

function routePrefixValue(string $type, ?PDO $pdo = null): string
{
    $settings = routePrefixSettings($pdo);
    $defaults = routePrefixDefaults();

    return $settings[$type] ?? ($defaults[$type] ?? $type);
}

function routePrefixAliases(
    string $type,
    ?array $settings = null,
    ?array $routes = null,
): array {
    $routes = $routes ?: routePrefixSettings($GLOBALS["pdo"] ?? null);
    $settingsProvided = is_array($settings);
    $settings = $settings ?: [];
    $prefixes = [(string) ($routes[$type] ?? "")];
    $settingKey = "route_" . $type . "_aliases";
    $raw = (string) ($settings[$settingKey] ?? "");

    if (
        $raw === "" &&
        !$settingsProvided &&
        isset($GLOBALS["pdo"]) &&
        $GLOBALS["pdo"] instanceof PDO
    ) {
        try {
            $raw = function_exists("adminSettingValue")
                ? adminSettingValue($GLOBALS["pdo"], $settingKey, "")
                : "";
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }

    foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $alias) {
        $clean = routePrefixSanitize($alias);
        if ($clean !== "") {
            $prefixes[] = $clean;
        }
    }

    return array_values(array_unique(array_filter($prefixes)));
}

function routePrefixMatches(
    string $type,
    string $prefix,
    ?array $routes = null,
    ?array $settings = null,
): bool {
    $prefix = routePrefixSanitize($prefix);
    if ($prefix === "") {
        return false;
    }

    return in_array(
        $prefix,
        routePrefixAliases($type, $settings, $routes),
        true,
    );
}

function routeCanonicalPath(string $type, string $slug = ""): string
{
    global $baseUri;

    $path = ($baseUri ?: "") . "/" . routePrefixValue($type);
    $slug = trim($slug);
    if ($slug !== "") {
        $path .= "/" . rawurlencode($slug);
    }

    return $path;
}

function routeTopicIdSuffixEnabled(?array $settings = null): bool
{
    $settings = routeFriendlySettings($settings);

    return (string) ($settings["route_topic_id_suffix"] ?? "1") === "1";
}

function topicRouteParts(string $value, ?array $settings = null): array
{
    $value = trim($value);
    $parts = [
        "slug" => $value,
        "id" => 0,
        "has_id_suffix" => false,
    ];

    if (
        $value !== "" &&
        routeTopicIdSuffixEnabled($settings) &&
        preg_match('/^(.+)-([1-9][0-9]*)$/', $value, $matches) === 1
    ) {
        $parts["slug"] = trim((string) $matches[1]);
        $parts["id"] = (int) $matches[2];
        $parts["has_id_suffix"] = true;
    }

    return $parts;
}

function topicCanonicalSlug(string $slug, ?int $id = null, ?array $settings = null): string
{
    $slug = trim($slug);
    if ($slug === "") {
        return "";
    }

    $routeSettings = routeFriendlySettings($settings);
    $id = (int) ($id ?? 0);
    $cleanSlug = $slug;
    $parts = topicRouteParts($slug, $routeSettings);
    if (
        $id > 0 &&
        !empty($parts["has_id_suffix"]) &&
        (int) ($parts["id"] ?? 0) === $id
    ) {
        $cleanSlug = (string) ($parts["slug"] ?? $slug);
    }

    if ((string) ($routeSettings["route_case_sensitive"] ?? "lowercase") === "lowercase") {
        $cleanSlug = mb_strtolower($cleanSlug, "UTF-8");
    }

    if (!routeTopicIdSuffixEnabled($routeSettings) || $id <= 0) {
        return $cleanSlug;
    }

    return rtrim($cleanSlug, "-") . "-" . $id;
}

function routeFriendlySettings(?array $settings = null): array
{
    if (is_array($settings)) {
        return $settings;
    }

    if (
        !empty($GLOBALS["routeFriendlySettingOverride"]) &&
        is_array($GLOBALS["routeFriendlySettingOverride"])
    ) {
        return $GLOBALS["routeFriendlySettingOverride"];
    }

    if (function_exists("getAdminSettings")) {
        $adminSettings = getAdminSettings($GLOBALS["pdo"] ?? null);
        return is_array($adminSettings) ? $adminSettings : [];
    }

    return [];
}

function routeFriendlyRedirectsEnabled(?array $settings = null): bool
{
    $settings = routeFriendlySettings($settings);

    return (string) ($settings["route_redirect_to_canonical"] ?? "1") === "1";
}

function routeAliasRedirectsEnabled(?array $settings = null): bool
{
    $settings = routeFriendlySettings($settings);

    return (string) ($settings["route_alias_redirects"] ?? "1") === "1";
}

function routeRequestPrefixFromPath(string $path): string
{
    global $baseUri;

    $normalizedPath = "/" . trim(rawurldecode($path), "/");
    $normalizedBase = "/" . trim((string) ($baseUri ?? ""), "/");

    if ($normalizedBase !== "/" && $normalizedPath === $normalizedBase) {
        $normalizedPath = "/";
    } elseif (
        $normalizedBase !== "/" &&
        str_starts_with($normalizedPath, $normalizedBase . "/")
    ) {
        $normalizedPath = substr($normalizedPath, strlen($normalizedBase));
    }

    $segments = explode("/", trim($normalizedPath, "/"));

    return routePrefixSanitize((string) ($segments[0] ?? ""));
}

function routeRequestUsesAliasPrefix(
    string $type,
    string $requestPath,
    ?array $settings = null,
): bool {
    $requestPrefix = routeRequestPrefixFromPath($requestPath);
    if ($requestPrefix === "") {
        return false;
    }

    $routes = routePrefixSettings($GLOBALS["pdo"] ?? null);
    $canonicalPrefix = routePrefixSanitize((string) ($routes[$type] ?? ""));
    if ($requestPrefix === $canonicalPrefix) {
        return false;
    }

    return in_array(
        $requestPrefix,
        routePrefixAliases($type, routeFriendlySettings($settings), $routes),
        true,
    );
}

function routeRequestNeedsCanonicalRedirect(
    string $type,
    string $slug = "",
): bool {
    $method = strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET"));
    if (!in_array($method, ["GET", "HEAD"], true)) {
        return false;
    }

    $requestPath = (string) parse_url(
        (string) ($_SERVER["REQUEST_URI"] ?? ""),
        PHP_URL_PATH,
    );
    if ($requestPath === "") {
        return false;
    }

    $canonical = routeCanonicalPath($type, $slug);
    $normalize = static function (string $path): string {
        return "/" . trim(rawurldecode($path), "/");
    };

    if ($normalize($requestPath) === $normalize($canonical)) {
        return false;
    }

    $routeSettings = routeFriendlySettings();
    if (routeRequestUsesAliasPrefix($type, $requestPath, $routeSettings)) {
        return routeAliasRedirectsEnabled($routeSettings);
    }

    return routeFriendlyRedirectsEnabled($routeSettings);
}

function topicUrl(string $slug, ?int $id = null): string
{
    return routeCanonicalPath("topic", topicCanonicalSlug($slug, $id));
}

function topicUrlForRow(array $topic): string
{
    $slug = (string) ($topic["slug"] ?? $topic["topic_slug"] ?? "");
    $id = (int) ($topic["id"] ?? $topic["topic_id"] ?? 0);
    return topicUrl($slug, $id);
}

function topicUrlBySlug(?PDO $pdo, string $slug): string
{
    $slug = trim($slug);
    if ($slug === "") {
        return routeCanonicalPath("topic");
    }

    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT id, slug FROM topics WHERE slug = :slug AND deleted_at IS NULL LIMIT 1");
            $stmt->execute(["slug" => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row["slug"])) {
                return topicUrlForRow($row);
            }
        } catch (Throwable $e) {
            appLogException($e, [
                "fn" => "topicUrlBySlug",
                "slug" => $slug,
            ]);
        }
    }

    return topicUrl($slug);
}

function categoryUrl(string $slug, string $parentSlug = ""): string
{
    global $baseUri;

    $slug = trim($slug);
    $parentSlug = trim($parentSlug);
    if ($slug === "") {
        return categoryListUrl();
    }
    if ($parentSlug === "" || $parentSlug === $slug) {
        return routeCanonicalPath("category", $slug);
    }

    return ($baseUri ?: "") .
        "/" .
        routePrefixValue("category") .
        "/" .
        rawurlencode($parentSlug) .
        "/" .
        rawurlencode($slug);
}

function categoryListUrl(): string
{
    return routeCanonicalPath("category_list");
}

/**
 * Bir kategori satırı için en uygun URL'i üret.
 *
 * Tercih sırası:
 *   1) Row'da hazır 'parent_slug' varsa onu kullan (N+1'i önler).
 *   2) Yoksa parent_id'den DB'de parent slug'ı tek seferlik çekip cache'le.
 *   3) Hiçbiri yoksa düz (flat) URL döndür.
 */
function categoryUrlForRow(?PDO $pdo, array $row): string
{
    static $parentSlugCache = [];

    $slug = (string) ($row["slug"] ?? "");
    if ($slug === "") {
        return categoryListUrl();
    }

    // 1) Pre-joined parent_slug
    if (!empty($row["parent_slug"]) && is_string($row["parent_slug"])) {
        return categoryUrl($slug, $row["parent_slug"]);
    }

    $parentId =
        isset($row["parent_id"]) && $row["parent_id"] !== null
            ? (int) $row["parent_id"]
            : 0;
    if ($parentId <= 0) {
        return categoryUrl($slug);
    }

    // 2) Cached lookup
    if (!array_key_exists($parentId, $parentSlugCache)) {
        $parentSlugCache[$parentId] = "";
        if ($pdo) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT slug FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1",
                );
                $stmt->execute(["id" => $parentId]);
                $parentSlugCache[$parentId] =
                    (string) ($stmt->fetchColumn() ?: "");
            } catch (Throwable $e) {
                appLogException($e, [
                    "fn" => "categoryUrlForRow",
                    "parent_id" => $parentId,
                ]);
                $parentSlugCache[$parentId] = "";
            }
        }
    }

    return categoryUrl($slug, $parentSlugCache[$parentId]);
}

function publicProfileSlug(int $userId, string $name): string
{
    $nameSlug = slugify($name);
    return $userId . ($nameSlug !== "" ? "-" . $nameSlug : "");
}

function publicProfileIdFromSlug(string $slug): int
{
    if (preg_match('/^(\d+)(?:-|$)/', trim($slug), $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}

function publicProfileUrl(array $user): string
{
    $id =
        (int) ($user["id"] ?? ($user["author_id"] ?? ($user["user_id"] ?? 0)));
    $name = (string) ($user["name"] ?? ($user["author"] ?? "uye"));

    if ($id <= 0) {
        return "#";
    }

    return routeCanonicalPath("profile", publicProfileSlug($id, $name));
}

/**
 * Generate unique slug with collision check.
 */
function generateUniqueSlug(
    ?PDO $pdo,
    string $title,
    string $table = "topics",
    ?int $excludeId = null,
): string {
    $slug = slugify($title);

    if (!$pdo || $slug === "") {
        return $slug;
    }

    // Validate table name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        return $slug;
    }

    $originalSlug = $slug;
    $counter = 1;

    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = :slug";
        $params = ["slug" => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params["id"] = $excludeId;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
        } catch (Throwable $e) {
            return $slug;
        }

        $slug = $originalSlug . "-" . $counter;
        $counter++;

        if ($counter > 100) {
            return $originalSlug . "-" . uniqid();
        }
    }
}

/**
 * Add a comment to a topic.
 */
function addComment(?PDO $pdo, int $topicId, int $userId, string $body): bool
{
    if (!$pdo || $body === "") {
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO comments (topic_id, user_id, body, status, created_at, updated_at)
                               VALUES (:topic_id, :user_id, :body, 'approved', NOW(), NOW())");
        $stmt->execute([
            "topic_id" => $topicId,
            "user_id" => $userId,
            "body" => $body,
        ]);

        // Update comment count
        $pdo->prepare(
            "UPDATE topics SET comment_count = comment_count + 1 WHERE id = :id",
        )->execute(["id" => $topicId]);

        logActivity(
            $pdo,
            "comment_created",
            "comment",
            (int) $pdo->lastInsertId(),
            ["topic_id" => $topicId],
        );

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Restore a soft-deleted topic.
 */
function restoreTopic(?PDO $pdo, int $id): bool
{
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE topics SET deleted_at = NULL, updated_at = NOW() WHERE id = :id AND deleted_at IS NOT NULL",
        );
        $stmt->execute(["id" => $id]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, "topic_restored", "topic", $id);
            return true;
        }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get deleted topics for trash view.
 */
function getDeletedTopics(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT t.*, cat.name AS category, u.name AS author
                             FROM topics t
                             LEFT JOIN categories cat ON t.category_id = cat.id
                             LEFT JOIN users u ON t.author_id = u.id
                             WHERE t.deleted_at IS NOT NULL
                             ORDER BY t.deleted_at DESC LIMIT 50");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Safe error message - hide DB details in production.
 */
function safeErrorMessage(
    Throwable $e,
    string $fallback = "Bir hata oluştu.",
): string {
    global $appDebug;
    if ($appDebug) {
        return $e->getMessage();
    }
    return $fallback;
}

/**
 * Generate breadcrumb data.
 */
function getBreadcrumbs(array $items): string
{
    global $baseUri;
    if (empty($items)) {
        return "";
    }

    $html =
        '<nav class="topic-breadcrumb" aria-label="Sayfa yolu"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    $position = 1;
    $lastIndex = count($items) - 1;

    foreach ($items as $i => $item) {
        $isLast = $i === $lastIndex;
        $html .=
            '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"';
        if ($isLast) {
            $html .= ' class="active" aria-current="page"';
        }
        $html .= ">";

        $label = htmlspecialchars($item["label"]);
        $icon = $i === 0 ? '<i class="bi bi-house-door" aria-hidden="true"></i> ' : "";

        if (!$isLast && isset($item["url"])) {
            $html .=
                '<a itemprop="item" href="' .
                htmlspecialchars($item["url"]) .
                '">' .
                $icon .
                '<span itemprop="name">' .
                $label .
                "</span></a>";
        } else {
            $html .= $icon . '<span itemprop="name">' . $label . "</span>";
        }

        $html .= '<meta itemprop="position" content="' . $position . '">';
        $html .= "</li>";
        $position++;
    }

    $html .= "</ol></nav>";
    return $html;
}

/**
 * Public on yuzde iki render yolundan hangisinin aktif oldugunu belirler.
 *
 * 1) TPL tema motoru (ThemeManager -> PublicThemeRenderer -> themes/<theme>/*.tpl)
 *    aktif admin ayari (`theme_active_id`) ile secilir; bu yolda sayfa
 *    icerigi render() ciktilari ile TPL placeholder'lariyla harmanlanir.
 * 2) TPL aktif degilse klasik raw PHP yoluna (includes/public-header.php +
 *    includes/public-footer.php + sayfa PHP/HTML icinde `usesPublicThemeRenderer()`
 *    false durumunda inline HTML) donulur.
 *
 * Standartlik acisindan TPL yolu kanonik kabul edilir; eski raw PHP yolu yalnizca
 * aktif tema render yolu devre disi kaldiginda bir geri donus (fallback) olarak
 * tutulmaktadir. Yeni sayfalar once TPL temasina eklenmelidir.
 */
function usesPublicThemeRenderer(): bool
{
    $themeManager = $GLOBALS['themeManager'] ?? null;

    return is_object($themeManager)
        && method_exists($themeManager, 'usesPublicRenderer')
        && $themeManager->usesPublicRenderer();
}

function renderPublicBreadcrumb(array $items, string $extraClass = ""): string
{
    global $baseUri;
    if (empty($items) || usesPublicThemeRenderer()) {
        return "";
    }

    $classes = trim("container public-container public-breadcrumb breadcrumb-container " . $extraClass);
    $html = '<div class="' . htmlspecialchars($classes, ENT_QUOTES, "UTF-8") . '">';
    $html .= '<nav class="breadcrumb" aria-label="Sayfa yolu">';
    $lastIndex = count($items) - 1;

    foreach ($items as $index => $item) {
        $label = htmlspecialchars((string) ($item["label"] ?? ""), ENT_QUOTES, "UTF-8");
        $url = (string) ($item["url"] ?? "");
        $isLast = $index === $lastIndex;

        if ($index > 0) {
            $html .= '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
        }

        if (!$isLast && $url !== "") {
            $icon = $index === 0 ? '<i class="bi bi-house-door" aria-hidden="true"></i> ' : "";
            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, "UTF-8") . '">' . $icon . $label . '</a>';
        } else {
            $html .= '<span aria-current="page">' . $label . '</span>';
        }
    }

    $html .= '</nav></div>';
    return $html;
}

/**
 * Generate SEO meta tags (canonical, OG, Twitter Card).
 *
 * @param string $title       Page title (already includes app name).
 * @param string $description Meta description (max ~160 chars).
 * @param string $url         Override canonical path (e.g. '/kategori/foo'); empty = REQUEST_URI without query.
 * @param string $image       Absolute or relative URL for og:image.
 */
if (!function_exists('getSeoMeta')) {
function getSeoMeta(
    string $title,
    string $description,
    string $url = "",
    string $image = "",
    bool $includeCanonical = true,
    ?string $ogType = null,
): string {
    global $envConfig, $pdo;

    $settings = seoSettings();
    $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));

    $titleIsFinal = !empty($GLOBALS['_seo_public_page_title_is_final']);
    $skipPublicPresets = !empty($GLOBALS['_seo_skip_public_page_presets']);
    if (
        !$skipPublicPresets
        && function_exists('seoPublicPageResolveKey')
        && function_exists('seoPublicPageMeta')
    ) {
        $resolvedPageKey = seoPublicPageResolveKey((string) ($_SERVER['REQUEST_URI'] ?? '/'), $settings, null);
        if ($resolvedPageKey !== '') {
            $resolvedMeta = seoPublicPageMeta(
                $resolvedPageKey,
                [
                    'title' => $title,
                    'description' => $description,
                    'image' => $image,
                ],
                [],
                $settings
            );
            $title = (string) ($resolvedMeta['title'] ?? $title);
            $description = (string) ($resolvedMeta['description'] ?? $description);
            $image = (string) ($resolvedMeta['image'] ?? $image);
            $titleIsFinal = !empty($resolvedMeta['title_is_final']);
        }
    }

    // Use default_meta_title if title is empty
    if (empty(trim($title))) {
        $title = $settings['default_meta_title'] ?? $siteName;
    }

    // Apply meta_title_suffix if set
    $titleSuffix = (string) ($settings['meta_title_suffix'] ?? '');
    if (!$titleIsFinal && $titleSuffix !== '' && !str_contains($title, $titleSuffix)) {
        $title = $title . ' ' . $titleSuffix;
    }

    // Use default_meta_description if description is empty
    if (empty(trim($description))) {
        $description = $settings['default_meta_description'] ?? '';
    }

    // Use meta_description_max_length from settings
    $maxLength = (int)($settings['meta_description_max_length'] ?? 160);
    if (mb_strlen($description, 'UTF-8') > $maxLength) {
        $description = mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
    }

    $fullUrl = seoCanonicalUrl($url !== "" ? $url : null, $settings);

    // Use og_image from settings if no image provided.
    // If still empty, fall back to active theme preview so social crawlers
    // always receive a valid og:image value.
    if ($image === "") {
        $image = trim((string) ($settings['og_image'] ?? ''));
    }
    if ($image === '' || $image === '/assets/og-default.jpg' || $image === 'assets/og-default.jpg') {
        $themeManager = $GLOBALS['themeManager'] ?? null;
        if ($themeManager instanceof ThemeManager) {
            $image = rtrim((string) $themeManager->themeUrl($themeManager->activeThemeId()), '/') . '/images/preview.png';
        }
    }

    // og:image normalize: relative ise canonicalBase'e ekle
    if ($image !== "" && stripos($image, "http") !== 0) {
        $image = seoCanonicalUrl($image, $settings);
    }

    // Use og_type from the caller when provided, otherwise fall back to settings/defaults
    $ogType = $ogType ?? ($settings['og_type'] ?? ($image !== "" ? "article" : "website"));

    // Use twitter_card from settings
    $twitterCard = $settings['twitter_card'] ?? ($image !== "" ? "summary_large_image" : "summary");

    $esc = static fn(string $v): string => htmlspecialchars(
        $v,
        ENT_QUOTES | ENT_HTML5,
        "UTF-8",
    );

    $html = '<meta name="description" content="' . $esc($description) . '">' . "\n";
    if ($includeCanonical) {
        $html .= '<link rel="canonical" href="' . $esc($fullUrl) . '">' . "\n";
    }
    $html .= '<meta property="og:title" content="' . $esc($title) . '">' . "\n";
    $html .=
        '<meta property="og:description" content="' .
        $esc($description) .
        '">' .
        "\n";
    $html .= '<meta property="og:url" content="' . $esc($fullUrl) . '">' . "\n";
    $html .=
        '<meta property="og:type" content="' .
        $esc($ogType) .
        '">' .
        "\n";
    $html .= '<meta property="og:locale" content="tr_TR">' . "\n";
    $html .=
        '<meta property="og:site_name" content="' .
        $esc($siteName) .
        '">' .
        "\n";

    if ($image !== "") {
        $html .=
            '<meta property="og:image" content="' . $esc($image) . '">' . "\n";
    }

    $html .= '<meta name="twitter:card" content="' . $esc($twitterCard) . '">' . "\n";
    $html .=
        '<meta name="twitter:title" content="' . $esc($title) . '">' . "\n";
    $html .=
        '<meta name="twitter:description" content="' .
        $esc($description) .
        '">' .
        "\n";

    if ($image !== "") {
        $html .=
            '<meta name="twitter:image" content="' . $esc($image) . '">' . "\n";
    }

    // Add Twitter handle if set
    if (!empty($settings['twitter_handle'])) {
        $twitterHandle = $esc($settings['twitter_handle']);
        $html .= '<meta name="twitter:site" content="@' . $twitterHandle . '">' . "\n";
    }

    return $html;
}
}

function seoSettings(?array $settings = null): array
{
    if (is_array($settings)) {
        return $settings;
    }

    if (!empty($GLOBALS["_seo_settings_override"]) && is_array($GLOBALS["_seo_settings_override"])) {
        return $GLOBALS["_seo_settings_override"];
    }

    if (function_exists("getAdminSettings")) {
        $adminSettings = getAdminSettings($GLOBALS["pdo"] ?? null);
        if (is_array($adminSettings)) {
            return $adminSettings;
        }
    }

    return [];
}

function seoIndexToggleValue(array $settings, string $indexKey, string $default = '1', ?string $legacyNoindexKey = null): string
{
    $normalize = static function ($value): ?string {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return '1';
        }
        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return '0';
        }
        return null;
    };

    $canonical = $normalize($settings[$indexKey] ?? null);
    if ($canonical !== null) {
        return $canonical;
    }

    if ($legacyNoindexKey !== null) {
        $legacy = $normalize($settings[$legacyNoindexKey] ?? null);
        if ($legacy !== null) {
            return $legacy === '1' ? '0' : '1';
        }
    }

    return $default === '0' ? '0' : '1';
}

function seoCanonicalBase(?array $settings = null): string
{
    global $envConfig;

    $settings = seoSettings($settings);
    $candidate = trim((string) ($settings["canonical_base_url"] ?? ""));
    if ($candidate === "" || stripos($candidate, "localhost") !== false) {
        $candidate = appPublicBaseUrl(true, (string) ($GLOBALS["baseUri"] ?? ""), $envConfig);
    }
    if ($candidate === "") {
        $candidate = appPublicBaseUrl(false, (string) ($GLOBALS["baseUri"] ?? ""), $envConfig);
    }

    return rtrim($candidate, "/");
}

function seoCanonicalUrl(?string $path = null, ?array $settings = null): string
{
    global $baseUri;

    $settings = seoSettings($settings);
    $base = seoCanonicalBase($settings);
    $basePath = (string) parse_url($base, PHP_URL_PATH);
    $basePath = "/" . trim($basePath, "/");
    $basePath = $basePath === "/" ? "" : $basePath;

    if ($path === null || $path === "") {
        $path = (string) parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        $path = (string) parse_url($path, PHP_URL_PATH);
    }

    $path = "/" . ltrim(rawurldecode((string) $path), "/");
    $appBase = "/" . trim((string) ($baseUri ?? ""), "/");
    if ($appBase !== "/" && ($path === $appBase || str_starts_with($path, $appBase . "/"))) {
        $path = substr($path, strlen($appBase));
        $path = $path === "" ? "/" : $path;
    } elseif ($basePath !== "" && ($path === $basePath || str_starts_with($path, $basePath . "/"))) {
        $path = substr($path, strlen($basePath));
        $path = $path === "" ? "/" : $path;
    }

    if ($path === "/index.php") {
        $path = "/";
    }

    $path = "/" . trim($path, "/");
    $trailingSlash = (string) ($settings["canonical_trailing_slash"] ?? "0") === "1";
    if ($path === "/") {
        return $base . "/";
    }

    $hasExtension = pathinfo($path, PATHINFO_EXTENSION) !== "";
    if ($trailingSlash && !$hasExtension && !str_ends_with($path, "/")) {
        $path .= "/";
    } elseif (!$trailingSlash) {
        $path = rtrim($path, "/");
    }

    $url = $base . $path;
    if (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 1) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'page=' . (int)$_GET['page'];
    }
    
    return $url;
}

function seoRobotsMeta(?array $settings = null, ?string $requestUri = null, ?string $pageKey = null): string
{
    $settings = seoSettings($settings);

    // Global indexing check
    if (seoIndexToggleValue($settings, 'allow_indexing', '1') !== '1') {
        return "noindex, nofollow";
    }

    $requestUri = $requestUri ?? (string) ($_SERVER["REQUEST_URI"] ?? "");
    $path = (string) parse_url($requestUri, PHP_URL_PATH);
    $basename = basename($path);
    $pageKey = trim((string) $pageKey);

    if ($pageKey === '' && function_exists('seoPublicPageResolveKey')) {
        $pageKey = seoPublicPageResolveKey($requestUri, $settings, null);
    }

    // Public page preset + hard security rules
    $shouldNoindex = function_exists('seoPublicPageIsNoindex')
        ? seoPublicPageIsNoindex($pageKey, $settings)
        : false;
    if (in_array($pageKey, ['login', 'register', 'forgot_password', 'reset_password'], true)) {
        $shouldNoindex = true;
    }

    // Public page catalog now owns homepage, search, topic, category and profile rules.
    // Legacy index_* toggles remain in storage for compatibility, but the page preset
    // layer is the source of truth for these public routes.

    // Archive pages check
    if (preg_match('~/(arsiv|archive)(/|$)~', $path) === 1) {
        if ((string) ($settings["index_archive_pages"] ?? "1") !== "1") {
            $shouldNoindex = true;
        }
    }

    // Pagination check
    $page = 1;
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $page = (int)$_GET['page'];
    } elseif (preg_match('#/page/(\d+)#', $path, $matches)) {
        $page = (int)$matches[1];
    }
    if ($page > 1) {
        if ((string) ($settings["index_paginated_pages"] ?? "0") === "0") {
            $shouldNoindex = true;
        }
        $maxPagesIndex = (int) ($settings['pagination_max_pages_index'] ?? 50);
        if ($page > $maxPagesIndex) {
            $shouldNoindex = true;
        }
    }

    $indexDirective = $shouldNoindex ? "noindex" : "index";
    $followDirective = ($pageKey === "download" || $basename === "download.php") ? "nofollow" : "follow";
    return $indexDirective . ", " . $followDirective . ", max-image-preview:large, max-snippet:-1, max-video-preview:-1";
}

function buildRobotsTxt(?array $settings = null, ?string $canonicalBase = null): string
{
    $settings = seoSettings($settings);

    // Check if robots.txt is enabled
    if ((string) ($settings['robots_enabled'] ?? '1') !== '1') {
        return "# robots.txt disabled via admin settings\nUser-agent: *\nDisallow:";
    }

    if (seoIndexToggleValue($settings, 'allow_indexing', '1') !== '1') {
        return "# robots.txt - indexing disabled via admin settings\nUser-agent: *\nDisallow: /\n";
    }

    $canonicalBase = rtrim($canonicalBase ?: seoCanonicalBase($settings), "/");
    $siteName = trim((string) ($settings['site_name'] ?? 'Mod Portal'));
    if ($siteName === '') {
        $siteName = 'Mod Portal';
    }
    $siteName = preg_replace('/\s+/u', ' ', $siteName) ?? $siteName;
    $lines = [
        "# robots.txt - " . $siteName,
        "User-agent: *",
        "Allow: /",
        "",
    ];

    $rules = [
        "robots_disallow_admin" => "/admin/",
        "robots_disallow_includes" => "/includes/",
        "robots_disallow_database" => "/database/",
        "robots_disallow_uploads" => "/uploads/",
    ];
    foreach ($rules as $key => $path) {
        if ((string) ($settings[$key] ?? "0") === "1") {
            $lines[] = "Disallow: " . $path;
        }
    }

    $crawlDelay = max(0, (int) ($settings["robots_crawl_delay"] ?? 0));
    if ($crawlDelay > 0) {
        $lines[] = "Crawl-delay: " . $crawlDelay;
    }

    $customRules = trim((string) ($settings["robots_custom_rules"] ?? ""));
    if ($customRules !== "") {
        $lines[] = "";
        foreach (preg_split('/\R/', $customRules) ?: [] as $rule) {
            $rule = trim($rule);
            if ($rule !== "") {
                $lines[] = $rule;
            }
        }
    }

    $lines[] = "";
    $lines[] = "Sitemap: " . $canonicalBase . "/sitemap.xml";

    return implode("\n", $lines) . "\n";
}

function getTopicStructuredDataJson(array $topic, ?array $settings = null): string
{
    $description = mb_substr(trim(strip_tags((string) ($topic["topic_descriptions"] ?? ($topic["description"] ?? "")))), 0, 300, "UTF-8");
    $slug = (string) ($topic["slug"] ?? "");
    $image = trim((string) ($topic["primary_media_path"] ?? ($topic["cover_image"] ?? "")));
    $downloadUrl = trim((string) ($topic["topic_download_url"] ?? ""));

    $data = [
        "@context" => "https://schema.org",
        "@type" => "SoftwareApplication",
        "name" => (string) ($topic["title"] ?? ""),
        "description" => $description,
        "applicationCategory" => "GameApplication",
        "operatingSystem" => "Windows",
        "url" => seoCanonicalUrl($slug !== "" ? topicUrl($slug, (int) ($topic["id"] ?? 0)) : null, $settings),
        "datePublished" => date("c", strtotime((string) ($topic["published_at"] ?? ($topic["created_at"] ?? "now")))),
        "dateModified" => date("c", strtotime((string) ($topic["updated_at"] ?? ($topic["published_at"] ?? ($topic["created_at"] ?? "now"))))),
        "isAccessibleForFree" => true,
        "interactionStatistic" => [
            "@type" => "InteractionCounter",
            "interactionType" => "https://schema.org/DownloadAction",
            "userInteractionCount" => (int) ($topic["download_count"] ?? 0),
        ],
    ];

    if ($image !== "") {
        $data["image"] = filter_var($image, FILTER_VALIDATE_URL) ? $image : seoCanonicalUrl($image, $settings);
    }
    if ($downloadUrl !== "") {
        $data["downloadUrl"] = $downloadUrl;
    }
    if (!empty($topic["author"])) {
        $data["author"] = [
            "@type" => "Person",
            "name" => (string) $topic["author"],
        ];
    }
    if (($topic["rating_average"] ?? 0) > 0) {
        $data["aggregateRating"] = [
            "@type" => "AggregateRating",
            "ratingValue" => (float) $topic["rating_average"],
            "ratingCount" => max(1, (int) ($topic["rating_count"] ?? 0)),
            "bestRating" => 5,
            "worstRating" => 1,
        ];
    }

    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function getWebsiteStructuredDataJson(?array $settings = null): string
{
    global $envConfig;

    $settings = seoSettings($settings);
    $baseUrl = seoCanonicalUrl("/", $settings);
    $siteName = (string) ($settings["site_name"] ?? ($envConfig["APP_NAME"] ?? "Mod Portal"));
    $description = (string) ($settings["default_meta_description"] ?? "Güncel modlar, eklentiler ve topluluk içerikleri.");

    $data = [
        "@context" => "https://schema.org",
        "@type" => "WebSite",
        "name" => $siteName,
        "url" => $baseUrl,
        "description" => $description,
        "potentialAction" => [
            "@type" => "SearchAction",
            "target" => [
                "@type" => "EntryPoint",
                "urlTemplate" => rtrim($baseUrl, "/") . "/?q={search_term_string}",
            ],
            "query-input" => "required name=search_term_string",
        ],
    ];

    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sanitizeSeoHeadCode(string $html): string
{
    $html = trim($html);
    if ($html === "") {
        return "";
    }

    $allowed = "<meta><link>";
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\s(on\w+)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', "", $clean) ?? "";
    $clean = preg_replace('/javascript\s*:/i', "", $clean) ?? "";

    return trim($clean);
}

/**
 * Handle file upload for topics.
 */
function mediaPublicPath(string $relativePath): string
{
    return ltrim(str_replace("\\", "/", $relativePath), "/");
}

function mediaPublicUrl(string $relativePath, string $baseUri = ""): string
{
    $relativePath = mediaPublicPath($relativePath);
    $baseUri = rtrim($baseUri, "/");
    return ($baseUri !== "" ? $baseUri : "") . "/" . $relativePath;
}

function mediaFileTypeFromMime(
    string $mimeType,
    string $fallback = "attachment",
): string {
    return str_starts_with($mimeType, "image/") ? "image" : $fallback;
}

function handleFileUpload(
    ?PDO $pdo,
    int $topicId,
    array $file,
    string $type = "attachment",
    int $displayOrder = 0,
    bool $isPrimary = false,
    string $filenameTitle = "",
    ?int $filenameSequence = null,
): ?array {
    if (!$pdo || ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    // Extension † izin verilen MIME tipleri eşleştirme. Cross-check ile
    // birinin renamed/spoofed olması durumunda red etmek için kullanılır.
    $extensionMimeMap = [
        "jpg" => ["image/jpeg"],
        "jpeg" => ["image/jpeg"],
        "png" => ["image/png"],
        "gif" => ["image/gif"],
        "webp" => ["image/webp"],
        "zip" => [
            "application/zip",
            "application/x-zip-compressed",
            "application/octet-stream",
        ],
        "rar" => [
            "application/x-rar-compressed",
            "application/vnd.rar",
            "application/octet-stream",
        ],
        "7z" => ["application/x-7z-compressed", "application/octet-stream"],
        "pdf" => ["application/pdf"],
        "txt" => ["text/plain"],
    ];
    $maxSize = 50 * 1024 * 1024;

    $tmpName = (string) ($file["tmp_name"] ?? "");
    $originalName = (string) ($file["name"] ?? "");
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($tmpName === "" || $originalName === "" || $extension === "") {
        return ["error" => "Dosya bilgisi eksik."];
    }

    if (!isset($extensionMimeMap[$extension])) {
        return ["error" => "Desteklenmeyen dosya uzantısı: ." . $extension];
    }

    // Güvenlik: çift uzantı (foo.php.jpg) saldırılarını engelle.
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    if (
        preg_match(
            '/\.(php\d?|phtml|phar|exe|sh|bat|cmd|com|cgi|js|jsp|asp|aspx)(\.|$)/i',
            $baseName,
        )
    ) {
        return ["error" => "Güvenlik nedeniyle bu dosya kabul edilmiyor."];
    }

    if (!is_uploaded_file($tmpName)) {
        return ["error" => "Geçersiz yükleme isteği."];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo
        ? (string) finfo_file($finfo, $tmpName)
        : "application/octet-stream";
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === "image/svg+xml" || stripos($mimeType, "svg") !== false) {
        return [
            "error" => "SVG yüklemeleri güvenlik nedeniyle desteklenmiyor.",
        ];
    }

    // Cross-validation: dosya içeriğinin MIME türü uzantıyla uyuşmak zorunda.
    $allowedForExt = $extensionMimeMap[$extension];
    if (!in_array($mimeType, $allowedForExt, true)) {
        appFileLog("warning", "Upload MIME/extension mismatch", [
            "extension" => $extension,
            "detected_mime" => $mimeType,
            "original_name" => $originalName,
        ]);
        return ["error" => "Dosya içeriği uzantı ile uyuşmuyor."];
    }

    if ((int) ($file["size"] ?? 0) > $maxSize) {
        return ["error" => "Dosya boyutu çok büyük (max 50MB)."];
    }

    $datePath = date("Y/m");
    $baseRelativeDir =
        $type === "image"
            ? "uploads/konu/" . $datePath
            : "uploads/" . $datePath;
    $uploadDir = __DIR__ . "/../" . $baseRelativeDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = uploadTitleFilename($filenameTitle, $extension, $filenameSequence);
    $safeName = uploadAvailableFilename($uploadDir, $safeName);
    $relativePath = $baseRelativeDir . "/" . $safeName;
    $fullPath = $uploadDir . "/" . $safeName;

    if (!move_uploaded_file($tmpName, $fullPath)) {
        return ["error" => "Dosya yüklenemedi."];
    }

    $logicalType = mediaFileTypeFromMime($mimeType, $type);

    // Item 5 — Image Optimization / WebP: Process images through the existing media pipeline
    $actualSize = (int) ($file["size"] ?? 0);
    if (
        $logicalType === "image"
        && function_exists('mediaProcessImage')
        && function_exists('getAdminSettings')
    ) {
        try {
            $imageSettings = getAdminSettings($pdo);
            $result = mediaProcessImage($fullPath, $imageSettings);
            if ($result['converted'] ?? false) {
                $safeName = $result['name'];
                $relativePath = dirname($relativePath) . '/' . $safeName;
                $fullPath = $result['path'];
                $mimeType = 'image/webp';
            }
            $actualSize = $result['final_size'] ?? $actualSize;
        } catch (Throwable $e) {
            appFileLog('warning', 'Image processing failed during upload', [
                'error' => $e->getMessage(),
                'path'  => $relativePath,
            ]);
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO media_files (topic_id, user_id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at)
                               VALUES (:topic_id, :user_id, :type, 'local', :path, :original_name, :mime_type, :size, :display_order, :is_primary, NOW(), NOW())");
        $stmt->execute([
            "topic_id" => $topicId,
            "user_id" => $_SESSION["_auth_user_id"] ?? null,
            "type" => $logicalType,
            "path" => $relativePath,
            "original_name" => $safeName,
            "mime_type" => $mimeType,
            "size" => $actualSize,
            "display_order" => max(0, $displayOrder),
            "is_primary" => $isPrimary ? 1 : 0,
        ]);

        return [
            "id" => (int) $pdo->lastInsertId(),
            "path" => $relativePath,
            "url" => mediaPublicUrl($relativePath, $GLOBALS["baseUri"] ?? ""),
            "original_name" => $safeName,
            "mime_type" => $mimeType,
            "size" => $actualSize,
            "type" => $logicalType,
            "display_order" => max(0, $displayOrder),
            "is_primary" => $isPrimary,
        ];
    } catch (Throwable $e) {
        @unlink($fullPath);
        return ["error" => safeErrorMessage($e, "Dosya kaydedilemedi.")];
    }
}

function topicResolveLocalUploadPath(
    string $path,
    string $baseUri = "",
): ?string {
    $path = trim($path);
    if ($path === "") {
        return null;
    }

    if (filter_var($path, FILTER_VALIDATE_URL)) {
        $urlPath = (string) parse_url($path, PHP_URL_PATH);
        $path = $urlPath !== "" ? $urlPath : $path;
    }

    $normalized = str_replace("\\", "/", $path);
    $baseUri = trim(str_replace("\\", "/", $baseUri), "/");

    if ($baseUri !== "") {
        $prefix = "/" . $baseUri . "/";
        if (str_starts_with($normalized, $prefix)) {
            $normalized = substr($normalized, strlen($prefix));
        }
    }

    $normalized = ltrim($normalized, "/");
    $uploadsPos = strpos($normalized, "uploads/");
    if ($uploadsPos === false) {
        return null;
    }

    $normalized = substr($normalized, $uploadsPos);
    if ($normalized === "" || !str_starts_with($normalized, "uploads/")) {
        return null;
    }

    return $normalized;
}

function topicDeletePhysicalFile(string $relativePath): void
{
    $relativePath = ltrim(str_replace("\\", "/", $relativePath), "/");
    if ($relativePath === "" || !str_starts_with($relativePath, "uploads/")) {
        return;
    }

    $projectRoot = realpath(__DIR__ . "/..") ?: dirname(__DIR__);
    $fullPath =
        $projectRoot .
        DIRECTORY_SEPARATOR .
        str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
    $fullRealDir = realpath(dirname($fullPath));
    if ($fullRealDir === false) {
        return;
    }

    $expectedPrefix = realpath($projectRoot . DIRECTORY_SEPARATOR . "uploads");
    if (
        $expectedPrefix === false ||
        ($fullRealDir !== $expectedPrefix && !str_starts_with($fullRealDir, $expectedPrefix . DIRECTORY_SEPARATOR))
    ) {
        return;
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    $thumbPath =
        dirname($fullPath) .
        DIRECTORY_SEPARATOR .
        "thumbs" .
        DIRECTORY_SEPARATOR .
        basename($fullPath);
    if (is_file($thumbPath)) {
        @unlink($thumbPath);
    }
}

function permanentlyDeleteTopic(
    ?PDO $pdo,
    int $topicId,
    string $baseUri = "",
): array {
    if (!$pdo || $topicId <= 0) {
        return ["success" => false, "message" => "Geçersiz konu seçildi."];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, deleted_at, primary_media_file_id FROM topics WHERE id = ? LIMIT 1",
        );
        $stmt->execute([$topicId]);
        $topic = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$topic) {
            return ["success" => false, "message" => "Konu bulunamadı."];
        }

        if (empty($topic["deleted_at"])) {
            return [
                "success" => false,
                "message" =>
                    "Kalıcı silme yalnızca çöp kutusundaki konular için kullanılabilir.",
            ];
        }

        $paths = [];

        $mediaStmt = $pdo->prepare(
            "SELECT path FROM media_files WHERE topic_id = ?",
        );
        $mediaStmt->execute([$topicId]);
        foreach (
            $mediaStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            as $mediaFilePath
        ) {
            $resolved = topicResolveLocalUploadPath(
                (string) $mediaFilePath,
                $baseUri,
            );
            if ($resolved !== null) {
                $paths[] = $resolved;
            }
        }

        $paths = array_values(array_unique(array_filter($paths)));

        $linkedImportsStmt = $pdo->prepare(
            "SELECT id, bot_site_id, status FROM bot_imports WHERE topic_id = ?",
        );
        $linkedImportsStmt->execute([$topicId]);
        $linkedImports = $linkedImportsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pdo->beginTransaction();

        if (!empty($linkedImports)) {
            $resetImportStmt = $pdo->prepare(
                "UPDATE bot_imports SET topic_id = NULL, status = 'preview', updated_at = NOW() WHERE id = ?",
            );
            $decrementSiteStmt = $pdo->prepare(
                "UPDATE bot_sites SET total_imports = GREATEST(total_imports - 1, 0) WHERE id = ?",
            );

            foreach ($linkedImports as $linkedImport) {
                $wasImported =
                    (string) ($linkedImport["status"] ?? "") === "imported";
                $resetImportStmt->execute([(int) $linkedImport["id"]]);
                if ($wasImported && !empty($linkedImport["bot_site_id"])) {
                    $decrementSiteStmt->execute([
                        (int) $linkedImport["bot_site_id"],
                    ]);
                }
            }
        }

        $deleteStmt = $pdo->prepare(
            "DELETE FROM topics WHERE id = ? AND deleted_at IS NOT NULL",
        );
        $deleteStmt->execute([$topicId]);

        if ($deleteStmt->rowCount() < 1) {
            $pdo->rollBack();
            return [
                "success" => false,
                "message" => "Konu kalıcı olarak silinemedi.",
            ];
        }

        $pdo->commit();

        foreach ($paths as $path) {
            topicDeletePhysicalFile($path);
        }

        logActivity($pdo, "topic_deleted_permanently", "topic", $topicId);
        return ["success" => true, "message" => "Konu kalıcı olarak silindi."];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            "success" => false,
            "message" => safeErrorMessage(
                $e,
                "Kalıcı silme sırasında bir hata oluştu.",
            ),
        ];
    }
}

/**
 * Input güvenliğini sağla
 */
function sanitizeInput(?string $input): string
{
    if ($input === null) {
        return "";
    }

    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
