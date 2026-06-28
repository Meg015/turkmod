<?php

declare(strict_types=1);

if (!function_exists('uiCssColorValue')) {
    function uiCssColorValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return $value;
        }

        if (preg_match('/^var\(--[a-z0-9-]+(?:,\s*#[0-9a-f]{3,8})?\)$/i', $value)) {
            return $value;
        }

        return '';
    }
}

if (!function_exists('uiUrlValue')) {
    function uiUrlValue(string $url, string $baseUri = ''): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        if (preg_match('/^(?:javascript|vbscript|data):/i', $url)) {
            return '';
        }

        if (!preg_match('~^(https?:)?//~i', $url) && !str_starts_with($url, '/')) {
            $url = rtrim($baseUri, '/') . '/' . ltrim($url, '/');
        }

        return str_replace(["\r", "\n"], '', $url);
    }
}

if (!function_exists('uiCssUrlValue')) {
    function uiCssUrlValue(string $url, string $baseUri = ''): string
    {
        $url = function_exists('uiUrlValue') ? uiUrlValue($url, $baseUri) : trim($url);
        if ($url === '') {
            return '';
        }

        $url = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', '', ''], $url);
        return 'url("' . $url . '")';
    }
}

if (!function_exists('uiStyleDeclaration')) {
    /**
     * Builds a safe CSS declaration list for inline custom-property bridges.
     *
     * @param array<string, string> $properties
     */
    function uiStyleDeclaration(array $properties): string
    {
        $parts = [];
        foreach ($properties as $property => $value) {
            $property = trim((string) $property);
            $value = trim((string) $value);
            if ($property === '' || $value === '') {
                continue;
            }
            if (!preg_match('/^--[a-z0-9-]+$/i', $property)) {
                continue;
            }
            if (preg_match('/[<>{};\x00-\x1F\x7F]/', $value)) {
                continue;
            }
            if (preg_match('/(?:expression|javascript|vbscript)\s*\(/i', $value)) {
                continue;
            }
            $parts[] = $property . ': ' . $value;
        }

        return $parts === [] ? '' : implode('; ', $parts) . ';';
    }
}

if (!function_exists('uiStyleAttribute')) {
    /**
     * @param array<string, string> $properties
     */
    function uiStyleAttribute(array $properties): string
    {
        $style = uiStyleDeclaration($properties);
        return $style === '' ? '' : ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('sanitizeInlineCss')) {
    /**
     * Sanitize user-provided CSS blocks before they are emitted by theme settings.
     */
    function sanitizeInlineCss(?string $css): string
    {
        $css = trim((string) $css);
        if ($css === '') {
            return '';
        }

        $css = strip_tags($css);
        $css = preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
        $css = preg_replace('/@(import|charset|namespace)\b[^;]*;?/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', '/* blocked */(', $css) ?? $css;
        $css = preg_replace('/-o-link\s*:/i', '/* blocked */:', $css) ?? $css;
        $css = preg_replace('/-o-link-source\s*:/i', '/* blocked */:', $css) ?? $css;
        $css = preg_replace('/url\s*\(\s*["\']?\s*(javascript|data|vbscript|file)\s*:/i', 'url(/* blocked */', $css) ?? $css;
        $css = preg_replace('/\b(behavior|-ms-behavior)\s*:/i', '/* blocked */:', $css) ?? $css;
        $css = preg_replace('/-moz-binding\s*:/i', '/* blocked */:', $css) ?? $css;
        $css = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $css) ?? $css;

        if (preg_match('/[<>]/', $css)) {
            return '/* Invalid CSS - contains HTML */';
        }

        return $css;
    }
}
