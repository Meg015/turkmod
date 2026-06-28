<?php

declare(strict_types=1);

if (php_sapi_name() === 'cli') {
    return;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    return;
}

// Yalnızca başarılı veritabanı bağlantısı varsa çalışır
if (!isset($pdo) || !$pdo) {
    return;
}

$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed = parse_url($requestUri);
if ($parsed === false) {
    return;
}

$path = $parsed['path'] ?? '/';
$query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

$originalHostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
$originalHost = function_exists('appSanitizeHostHeader')
    ? appSanitizeHostHeader($originalHostHeader)
    : trim($originalHostHeader);
if ($originalHost === '') {
    $originalHost = 'localhost';
}
function routeCanonicalHost(array $envConfig, string $fallbackHost): string
{
    if (function_exists('appTrustedHostFromRequest')) {
        return appTrustedHostFromRequest(true, $envConfig, $fallbackHost);
    }

    $fallbackHost = trim($fallbackHost);
    if ($fallbackHost === '') {
        return 'localhost';
    }

    return $fallbackHost;
}

$host = routeCanonicalHost($envConfig ?? [], $originalHost);

$trustedProxy = function_exists('isTrustedProxyAddress') && isTrustedProxyAddress((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($trustedProxy && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $isHttps ? 'https' : 'http';
$originalScheme = $scheme;

$needsRedirect = false;

// 1. HTTPS Zorlaması
if (($settings['route_https_redirect'] ?? '0') === '1' && !$isHttps) {
    $scheme = 'https';
    $needsRedirect = true;
}

// 2. WWW Yönlendirmesi
$wwwMode = $settings['route_www_redirect'] ?? 'none';
if ($wwwMode === 'www' && !str_starts_with(strtolower($host), 'www.')) {
    // Sadece localhost/127.0.0.1 değilse ekle
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        $host = 'www.' . $host;
        $needsRedirect = true;
    }
} elseif ($wwwMode === 'non-www' && str_starts_with(strtolower($host), 'www.')) {
    $host = substr($host, 4);
    $needsRedirect = true;
}

// Sonraki filtreler admin paneli, api ve varlıklar (css/js/images) için çalışmaz
$isAdmin = str_contains($path, '/admin/') || preg_match('#/admin$#', $path);
$isApi = str_contains($path, '/api/');
$isAsset = preg_match('/\.(png|jpg|jpeg|gif|css|js|ico|svg|woff|woff2|ttf|eot|webp|avif)$/i', $path);

if (!$isAdmin && !$isApi && !$isAsset) {

    // 3. index.php gizleme
    if (($settings['route_hide_index_php'] ?? '0') === '1') {
        if (preg_match('#/index\.php$#i', $path)) {
            $path = preg_replace('#/index\.php$#i', '/', $path);
            if ($path === '//') $path = '/';
            $needsRedirect = true;
        }
    }

    // Dosya uzantısı içermeyen sayfalar için Case ve Slash kuralları
    if (!preg_match('/\.[a-z0-9]+$/i', $path)) {
        
        // 4. Küçük Harf Zorlaması (Case Sensitive)
        $caseMode = $settings['route_case_sensitive'] ?? 'lowercase';
        if ($caseMode === 'lowercase') {
            $lowerPath = strtolower($path);
            if ($path !== $lowerPath) {
                $path = $lowerPath;
                $needsRedirect = true;
            }
        }

        // 5. Trailing Slash Yönlendirmesi
        $slashMode = $settings['route_trailing_slash'] ?? 'none';
        if ($path !== '/') {
            if ($slashMode === 'add' && !str_ends_with($path, '/')) {
                $path .= '/';
                $needsRedirect = true;
            } elseif ($slashMode === 'remove' && str_ends_with($path, '/')) {
                // Kök dizinden (örn: /mod2/) slash kaldırmak Apache'de sonsuz döngü yaratır, o yüzden kök değilse kaldır.
                $appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
                if ($path !== $appRoot . '/') {
                    $path = rtrim($path, '/');
                    $needsRedirect = true;
                }
            }
        }
    }
}

if ($needsRedirect) {
    $port = $_SERVER['SERVER_PORT'] ?? 80;
    // Port varsa ve standart dışıysa ekleyelim
    $portSuffix = '';
    if (($scheme === 'http' && $port != 80) || ($scheme === 'https' && $port != 443)) {
        // HTTP_HOST zaten port içeriyor olabilir
        if (!str_contains($host, ':')) {
            $portSuffix = ':' . $port;
        }
    }

    $redirectUrl = $scheme . '://' . $host . $portSuffix . $path . $query;

    // Self-redirect koruması: hedef URL mevcut istekle aynıysa yönlendirme yapma.
    // Bu, proxy ardında $isHttps yanlış algılandığında oluşabilecek sonsuz 301
    // döngüsünü engeller (krş. category.php self-redirect guard).
    $currentUrl = $originalScheme . '://' . $originalHost . $requestUri;
    if ($redirectUrl === $currentUrl) {
        return;
    }

    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirectUrl);
    exit;
}
