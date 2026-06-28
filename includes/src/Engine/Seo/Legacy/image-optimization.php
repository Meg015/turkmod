<?php

declare(strict_types=1);

if (!function_exists('seoGenerateImageAlt')) {
    function seoGenerateImageAlt(string $context, string $title, array $settings): string
    {
        if (($settings['image_alt_auto_generate'] ?? '1') !== '1') {
            return $settings['image_alt_fallback'] ?? 'İçerik görseli';
        }

        $template = seoGetImageAltTemplate($context, $settings);

        // Get category if available (from global or passed data)
        $category = '';
        if (isset($GLOBALS['_cardCategory'])) {
            $category = $GLOBALS['_cardCategory'];
        }

        $alt = seoApplyTemplate($template, [
            'title' => $title,
            'category' => $category,
            'context' => $context
        ]);

        $alt = seoSanitizeImageAlt($alt);

        if (!seoValidateImageAlt($alt, $settings)) {
            return $settings['image_alt_fallback'] ?? 'İçerik görseli';
        }

        return $alt;
    }
}

if (!function_exists('seoGetImageAltTemplate')) {
    function seoGetImageAltTemplate(string $context, array $settings): string
    {
        $templates = [
            'topic-card' => $settings['image_alt_template'] ?? '{{title}} - {{category}} modu',
            'topic-hero' => '{{title}} kapak görseli',
            'avatar' => '{{username}} profil fotoğrafı',
            'category-icon' => '{{category}} kategorisi'
        ];

        return $templates[$context] ?? '{{title}}';
    }
}

if (!function_exists('seoValidateImageAlt')) {
    function seoValidateImageAlt(string $alt, array $settings): bool
    {
        $minLength = (int) ($settings['image_alt_min_length'] ?? 10);

        if (trim($alt) === '') {
            return false;
        }

        if (mb_strlen($alt, 'UTF-8') < $minLength) {
            return false;
        }

        return true;
    }
}

if (!function_exists('seoSanitizeImageAlt')) {
    function seoSanitizeImageAlt(string $alt): string
    {
        // Remove HTML tags
        $alt = strip_tags($alt);

        // Remove extra whitespace
        $alt = preg_replace('/\s+/', ' ', $alt);

        // Trim
        $alt = trim($alt);

        return $alt;
    }
}
