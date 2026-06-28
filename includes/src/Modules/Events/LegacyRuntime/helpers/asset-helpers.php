<?php
/**
 * Asset Helper Functions for Events Module
 * Handles loading of minified assets in production
 */

function eventsPublicAssetPath(string $path): string {
    $normalized = '/' . ltrim(str_replace('\\', '/', (string) $path), '/');
    $eventsBasePath = function_exists('routePublicStaticPath')
        ? '/' . ltrim(routePublicStaticPath('events'), '/')
        : '/events';
    $eventsBasePath = rtrim($eventsBasePath, '/');
    if ($eventsBasePath === '') {
        $eventsBasePath = '/events';
    }

    if (strpos($normalized, '/events/') === 0) {
        return $eventsBasePath . substr($normalized, strlen('/events'));
    }

    return $normalized;
}

function eventsResolveAssetFilePath(string $path): string {
    $normalized = eventsPublicAssetPath($path);
    $moduleRoot = dirname(__DIR__, 2);

    $eventsBasePath = function_exists('routePublicStaticPath')
        ? '/' . ltrim(routePublicStaticPath('events'), '/')
        : '/events';
    $eventsBasePath = rtrim($eventsBasePath, '/');
    if ($eventsBasePath === '') {
        $eventsBasePath = '/events';
    }
    $assetPrefix = $eventsBasePath . '/assets/';

    if (strpos($normalized, $assetPrefix) === 0) {
        $relativePath = substr($normalized, strlen($assetPrefix));
        return $moduleRoot . '/assets/' . ltrim($relativePath, '/');
    }

    if (strpos($normalized, '/events/assets/') === 0) {
        $relativePath = substr($normalized, strlen('/events/assets/'));
        return $moduleRoot . '/assets/' . ltrim($relativePath, '/');
    }

    if (strpos($normalized, 'events/assets/') === 0) {
        $relativePath = substr($normalized, strlen('events/assets/'));
        return $moduleRoot . '/assets/' . ltrim($relativePath, '/');
    }

    return dirname(__DIR__, 6) . '/' . ltrim($normalized, '/');
}

function eventsGetAssetUrl($path, $type = 'css') {
    global $baseUri;

    // Check if minified version exists
    $minPath = str_replace('.' . $type, '.min.' . $type, $path);
    $fullMinPath = eventsResolveAssetFilePath($minPath);
    $fullPath = eventsResolveAssetFilePath($path);

    $useSourceAssets = defined('EVENTS_USE_SOURCE_ASSETS') && EVENTS_USE_SOURCE_ASSETS === true;
    $minAssetIsFresh = is_file($fullMinPath)
        && (!is_file($fullPath) || filemtime($fullMinPath) >= filemtime($fullPath));
    $selectedPath = $minAssetIsFresh && !$useSourceAssets ? $minPath : $path;
    $selectedPublicPath = eventsPublicAssetPath($selectedPath);

    $themeManager = $GLOBALS['themeManager'] ?? null;
    if (is_object($themeManager) && method_exists($themeManager, 'publicAssetUrl')) {
        $themeUrl = $themeManager->publicAssetUrl(ltrim((string) $selectedPublicPath, '/'));
        if (is_string($themeUrl) && $themeUrl !== '') {
            return htmlspecialchars($themeUrl);
        }
    }

    $selectedFullPath = $selectedPath === $minPath ? $fullMinPath : $fullPath;
    $version = is_file($selectedFullPath) ? (string)filemtime($selectedFullPath) : '1';
    return htmlspecialchars(rtrim((string) $baseUri, '/') . $selectedPublicPath . '?v=' . $version);
}

function eventsGetImageUrl($imagePath, $optimize = true) {
    global $baseUri;

    // If WebP is supported and available, use it
    if ($optimize && strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'image/webp') !== false) {
        $webpPath = str_replace(['.png', '.jpg', '.jpeg'], '.webp', $imagePath);
        $fullWebpPath = eventsResolveAssetFilePath($webpPath);
        if (file_exists($fullWebpPath)) {
            $imagePath = $webpPath;
        }
    }

    $publicImagePath = eventsPublicAssetPath((string) $imagePath);
    $themeManager = $GLOBALS['themeManager'] ?? null;
    if (is_object($themeManager) && method_exists($themeManager, 'publicAssetUrl')) {
        $themeUrl = $themeManager->publicAssetUrl(ltrim((string) $publicImagePath, '/'));
        if (is_string($themeUrl) && $themeUrl !== '') {
            return htmlspecialchars($themeUrl);
        }
    }

    return htmlspecialchars(rtrim((string) $baseUri, '/') . $publicImagePath);
}

/**
 * Responsive image helper with srcset
 */
function eventsResponsiveImage($imagePath, $alt, $sizes = []) {
    $baseUrl = rtrim((string) ($GLOBALS['baseUri'] ?? '/'), '/');
    $defaultSizes = [
        'small' => 300,
        'medium' => 600,
        'large' => 1200,
    ];
    $sizes = array_merge($defaultSizes, $sizes);

    $publicImagePath = eventsPublicAssetPath((string) $imagePath);
    $srcset = [];
    foreach ($sizes as $name => $width) {
        $resizedPath = str_replace(
            pathinfo($publicImagePath, PATHINFO_EXTENSION),
            $width . 'w.' . pathinfo($publicImagePath, PATHINFO_EXTENSION),
            $publicImagePath
        );
        $srcset[] = htmlspecialchars($baseUrl . $resizedPath) . ' ' . $width . 'w';
    }

    return [
        'src' => htmlspecialchars($baseUrl . $publicImagePath),
        'srcset' => implode(', ', $srcset),
        'alt' => htmlspecialchars($alt),
    ];
}
?>
