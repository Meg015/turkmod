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

if (!function_exists('uiEscape')) {
    function uiEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('uiClass')) {
    function uiClass(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $value) ?? '';
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $unique = [];
        foreach ($parts as $part) {
            if ($part === '' || isset($unique[$part])) {
                continue;
            }
            $unique[$part] = true;
        }

        return implode(' ', array_keys($unique));
    }
}

if (!function_exists('uiAttrName')) {
    function uiAttrName(string $name): string
    {
        $name = trim($name);

        return $name !== '' && preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:\-\.]*$/', $name) === 1 ? $name : '';
    }
}

if (!function_exists('uiAttrs')) {
    /**
     * @param array<string,string|int|bool|null> $attrs
     */
    function uiAttrs(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $attrName = uiAttrName((string) $name);
            if ($attrName === '') {
                continue;
            }
            $html .= ' ' . uiEscape($attrName);
            if ($value !== true) {
                $html .= '="' . uiEscape($value) . '"';
            }
        }

        return $html;
    }
}

if (!function_exists('uiNormalizeTone')) {
    function uiNormalizeTone(string $tone): string
    {
        $tone = strtolower(trim($tone));
        $map = [
            'ok' => 'success',
            'active' => 'success',
            'enabled' => 'success',
            'published' => 'success',
            'approved' => 'success',
            'sent' => 'success',
            'read' => 'success',
            'success' => 'success',
            'warn' => 'warning',
            'pending' => 'warning',
            'queued' => 'warning',
            'revision' => 'warning',
            'warning' => 'warning',
            'error' => 'danger',
            'failed' => 'danger',
            'fail' => 'danger',
            'rejected' => 'danger',
            'danger' => 'danger',
            'notice' => 'info',
            'processing' => 'info',
            'info' => 'info',
            'primary' => 'primary',
            'accent' => 'accent',
            'inactive' => 'muted',
            'disabled' => 'muted',
            'expired' => 'muted',
            'skipped' => 'muted',
            'none' => 'muted',
            'neutral' => 'muted',
            'secondary' => 'muted',
            'muted' => 'muted',
        ];

        return $map[$tone] ?? 'muted';
    }
}

if (!function_exists('uiAlertClass')) {
    function uiAlertClass(string $tone = 'info', string $class = ''): string
    {
        $tone = uiNormalizeTone($tone);
        $alertTone = $tone === 'danger' ? 'error' : $tone;

        return trim(uiClass('ui-admin-alert ui-admin-alert-' . $tone . ' ui-alert ui-alert--' . $alertTone . ' ' . $class));
    }
}

if (!function_exists('uiRenderAlert')) {
    /**
     * @param array{icon?:string,title?:string,html?:string,class?:string,attrs?:array<string,string|int|bool|null>,role?:string,closable?:bool,close_label?:string,close_class?:string} $options
     */
    function uiRenderAlert(string $message, string $tone = 'info', array $options = []): string
    {
        $message = trim($message);
        $contentHtml = trim((string) ($options['html'] ?? ''));
        $title = trim((string) ($options['title'] ?? ''));
        if ($message === '' && $contentHtml === '' && $title === '') {
            return '';
        }

        $tone = uiNormalizeTone($tone);
        $role = (string) ($options['role'] ?? (in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status'));
        $class = uiAlertClass($tone, (string) ($options['class'] ?? ''));
        $attrs = (array) ($options['attrs'] ?? []);
        if ($role !== '') {
            $attrs['role'] = $role;
        }

        $defaultIcons = [
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info' => 'bi-info-circle-fill',
            'primary' => 'bi-info-circle-fill',
            'accent' => 'bi-info-circle-fill',
            'muted' => 'bi-info-circle',
        ];
        $icon = uiClass((string) ($options['icon'] ?? ($defaultIcons[$tone] ?? '')));

        $html = '<div class="' . uiEscape($class) . '"' . uiAttrs($attrs) . '>';
        if ($icon !== '') {
            $html .= '<i class="bi ' . uiEscape($icon) . '"></i> ';
        }
        if ($contentHtml !== '') {
            $html .= $contentHtml;
        } elseif ($title !== '') {
            $html .= '<div><strong>' . uiEscape($title) . '</strong><br><span>' . uiEscape($message) . '</span></div>';
        } else {
            $html .= uiEscape($message);
        }
        if (!empty($options['closable'])) {
            $closeLabel = (string) ($options['close_label'] ?? 'Kapat');
            $closeClass = trim(uiClass('ui-admin-alert-close ' . (string) ($options['close_class'] ?? '')));
            $html .= '<button type="button" class="' . uiEscape($closeClass) . '" aria-label="' . uiEscape($closeLabel) . '"><i class="bi bi-x-lg"></i></button>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('uiRenderFlashAlerts')) {
    function uiRenderFlashAlerts(?string $successMessage = null, ?string $errorMessage = null, array $options = []): string
    {
        $html = '';
        if ((string) $successMessage !== '') {
            $html .= uiRenderAlert((string) $successMessage, 'success', (array) ($options['success'] ?? []));
        }
        if ((string) $errorMessage !== '') {
            $html .= uiRenderAlert((string) $errorMessage, 'danger', (array) ($options['error'] ?? []));
        }

        return $html;
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
