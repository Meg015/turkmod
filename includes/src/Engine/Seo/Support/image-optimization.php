<?php

declare(strict_types=1);

if (!function_exists('seoGenerateImageText')) {
    function seoGenerateImageText(string $variant, string $context, string $title, array $settings): string
    {
        $variant = $variant === 'title' ? 'title' : 'alt';
        $autoKey = $variant === 'title' ? 'image_title_auto_generate' : 'image_alt_auto_generate';
        $templateKey = $variant === 'title' ? 'image_title_template' : 'image_alt_template';
        $fallbackKey = $variant === 'title' ? 'image_title_fallback' : 'image_alt_fallback';
        $minLengthKey = $variant === 'title' ? 'image_title_min_length' : 'image_alt_min_length';
        $fallback = (string) ($settings[$fallbackKey] ?? 'İçerik görseli');

        if (($settings[$autoKey] ?? '1') !== '1') {
            return $fallback;
        }

        $template = seoGetImageTextTemplate($variant, $context, $settings);

        // Get category if available (from global or passed data)
        $category = '';
        if (isset($GLOBALS['_cardCategory'])) {
            $category = $GLOBALS['_cardCategory'];
        }

        $text = seoApplyTemplate($template, [
            'title' => $title,
            'category' => $category,
            'username' => (string) ($GLOBALS['_cardAuthor'] ?? ''),
            'context' => $context
        ]);

        $text = seoSanitizeImageText($text);

        if (!seoValidateImageText($text, (int) ($settings[$minLengthKey] ?? 10))) {
            return $fallback;
        }

        return $text;
    }
}

if (!function_exists('seoGetImageTextTemplate')) {
    function seoGetImageTextTemplate(string $variant, string $context, array $settings): string
    {
        $templateKey = $variant === 'title' ? 'image_title_template' : 'image_alt_template';
        $templates = [
            'topic-card' => $settings[$templateKey] ?? '{{title}} - {{category}} modu',
            'topic-hero' => '{{title}} kapak görseli',
            'avatar' => '{{username}} profil fotoğrafı',
            'category-icon' => '{{category}} kategorisi',
        ];

        return $templates[$context] ?? '{{title}}';
    }
}

if (!function_exists('seoValidateImageText')) {
    function seoValidateImageText(string $text, int $minLength): bool
    {
        if (trim($text) === '') {
            return false;
        }

        if (mb_strlen($text, 'UTF-8') < $minLength) {
            return false;
        }

        return true;
    }
}

if (!function_exists('seoSanitizeImageText')) {
    function seoSanitizeImageText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        return $text;
    }
}

if (!function_exists('seoGenerateImageAlt')) {
    function seoGenerateImageAlt(string $context, string $title, array $settings): string
    {
        return seoGenerateImageText('alt', $context, $title, $settings);
    }
}

if (!function_exists('seoGenerateImageTitle')) {
    function seoGenerateImageTitle(string $context, string $title, array $settings): string
    {
        return seoGenerateImageText('title', $context, $title, $settings);
    }
}
