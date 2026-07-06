<?php

declare(strict_types=1);

if (!function_exists('seoApplyTemplate')) {
    function seoApplyTemplate(string $template, array $vars): string
    {
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace('{{' . $key . '}}', (string) $value, $result);
        }
        return $result;
    }
}

if (!function_exists('seoGenerateCategoryMeta')) {
    function seoGenerateCategoryMeta(array $category, array $settings, string $canonicalUrl = '', bool $includeCanonical = true): string
    {
        global $baseUri, $envConfig;

        $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        $categoryName = trim((string) ($category['name'] ?? ''));
        $parentName = trim((string) ($category['parent_name'] ?? ''));
        $topicCount = (int) ($category['topic_count'] ?? 0);
        $title = trim((string) ($category['seo_title'] ?? ''));
        if ($title === '') {
            $title = $parentName !== '' ? $parentName . ' › ' . $categoryName : $categoryName;
        }

        $description = trim((string) ($category['seo_description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($category['description'] ?? ''));
        }
        if ($description === '') {
            $description = seoApplyTemplate('{{category}} kategorisindeki modlar | {{site_name}}', [
                'category' => $categoryName,
                'parent' => $parentName,
                'count' => $topicCount,
                'site_name' => $siteName,
            ]);
        }

        $maxLength = (int) ($settings['meta_description_max_length'] ?? ($settings['meta_description_length'] ?? 160));
        if (mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        $ogImage = trim((string) ($settings['default_og_image'] ?? ''));
        if ($ogImage === '' || $ogImage === '/assets/og-default.jpg') {
            $activeThemeManager = $GLOBALS['themeManager'] ?? null;
            if (class_exists(ThemeManager::class) && $activeThemeManager instanceof ThemeManager) {
                $ogImage = $activeThemeManager->themeUrl($activeThemeManager->activeThemeId()) . '/images/preview.png';
            }
        }

        if ($canonicalUrl === '') {
            $canonicalUrl = categoryUrl((string) ($category['slug'] ?? ''), (string) ($category['parent_slug'] ?? ''));
        }

        if (function_exists('seoPublicPageMetaTags')) {
            return seoPublicPageMetaTags(
                'category',
                [
                    'title' => $title,
                    'description' => $description,
                    'image' => $ogImage,
                ],
                [
                    'category' => $categoryName,
                    'parent' => $parentName,
                    'count' => $topicCount,
                    'page_title' => $title,
                    'page_description' => $description,
                ],
                $settings,
                $canonicalUrl,
                $includeCanonical,
                'website'
            );
        }

        return getSeoMeta($title, $description, $canonicalUrl, $ogImage, $includeCanonical, 'website');
    }
}

if (!function_exists('seoGenerateProfileMeta')) {
    function seoGenerateProfileMeta(array $user, array $stats, array $settings, string $canonicalUrl = '', bool $includeCanonical = true): string
    {
        global $baseUri, $envConfig;

        $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        $username = trim((string) ($user['name'] ?? ''));
        $topics = (int) ($stats['topics'] ?? 0);
        $comments = (int) ($stats['comments'] ?? 0);
        $views = (int) ($stats['views'] ?? 0);
        $downloads = (int) ($stats['downloads'] ?? 0);
        $bio = trim((string) ($user['bio'] ?? ''));

        $description = $bio !== '' ? $bio : seoApplyTemplate('{{username}} profili ve paylaşımları | {{site_name}}', [
            'username' => $username,
            'topics' => $topics,
            'comments' => $comments,
            'views' => $views,
            'downloads' => $downloads,
            'site_name' => $siteName,
        ]);

        $maxLength = (int) ($settings['meta_description_max_length'] ?? ($settings['meta_description_length'] ?? 160));
        if (mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        $ogImage = !empty($user['avatar'])
            ? profileAvatarUrl($baseUri, (string) $user['avatar'])
            : trim((string) ($settings['default_og_image'] ?? ''));
        if ($ogImage === '' || $ogImage === '/assets/og-default.jpg') {
            $activeThemeManager = $GLOBALS['themeManager'] ?? null;
            if (class_exists(ThemeManager::class) && $activeThemeManager instanceof ThemeManager) {
                $ogImage = $activeThemeManager->themeUrl($activeThemeManager->activeThemeId()) . '/images/preview.png';
            }
        }

        if ($canonicalUrl === '') {
            $canonicalUrl = publicProfileUrl($user);
        }

        $title = $username !== '' ? $username . ' Profili' : 'Profil';

        if (function_exists('seoPublicPageMetaTags')) {
            return seoPublicPageMetaTags(
                'public_profile',
                [
                    'title' => $title,
                    'description' => $description,
                    'image' => $ogImage,
                ],
                [
                    'username' => $username,
                    'topics' => $topics,
                    'comments' => $comments,
                    'views' => $views,
                    'downloads' => $downloads,
                    'page_title' => $title,
                    'page_description' => $description,
                ],
                $settings,
                $canonicalUrl,
                $includeCanonical,
                'profile'
            );
        }

        return getSeoMeta($title, $description, $canonicalUrl, $ogImage, $includeCanonical, 'profile');
    }
}

if (!function_exists('seoGenerateTopicMeta')) {
    function seoGenerateTopicMeta(array $topic, array $settings, string $canonicalUrl = '', bool $includeCanonical = true): string
    {
        global $envConfig;

        $siteName = (string) ($settings['site_name'] ?? ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        $title = trim((string) ($topic['meta_title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($topic['title'] ?? ''));
        }
        if ($title === '') {
            $title = $siteName;
        }

        $descriptionSource = trim((string) ($topic['meta_description'] ?? ''));
        if ($descriptionSource === '') {
            $descriptionSource = trim((string) ($topic['topic_descriptions'] ?? ($topic['description'] ?? '')));
        }
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags($descriptionSource)) ?? '');

        $maxLength = (int) ($settings['meta_description_max_length'] ?? ($settings['meta_description_length'] ?? 160));
        if ($description !== '' && mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        $ogImage = trim((string) ($topic['primary_media_path'] ?? ''));
        if ($ogImage === '') {
            $ogImage = trim((string) ($topic['topic_first_image'] ?? ''));
        }
        if ($ogImage === '') {
            $ogImage = trim((string) ($settings['default_og_image'] ?? ''));
        }
        if ($ogImage === '' || $ogImage === '/assets/og-default.jpg') {
            $activeThemeManager = $GLOBALS['themeManager'] ?? null;
            if (class_exists(ThemeManager::class) && $activeThemeManager instanceof ThemeManager) {
                $ogImage = $activeThemeManager->themeUrl($activeThemeManager->activeThemeId()) . '/images/preview.png';
            }
        }

        if ($canonicalUrl === '') {
            $slug = (string) ($topic['slug'] ?? '');
            $id = (int) ($topic['id'] ?? 0);
            $canonicalUrl = function_exists('topicUrl') ? topicUrl($slug, $id > 0 ? $id : null) : '';
        }

        if (function_exists('seoPublicPageMetaTags')) {
            return seoPublicPageMetaTags(
                'topic',
                [
                    'title' => $title,
                    'description' => $description,
                    'image' => $ogImage,
                ],
                [
                    'title' => $title,
                    'category' => (string) ($topic['category'] ?? ''),
                    'author' => (string) ($topic['author'] ?? ''),
                    'excerpt' => (string) ($topic['excerpt'] ?? ''),
                    'page_title' => $title,
                    'page_description' => $description,
                ],
                $settings,
                $canonicalUrl,
                $includeCanonical,
                'article'
            );
        }

        return getSeoMeta($title, $description, $canonicalUrl, $ogImage, $includeCanonical, 'article');
    }
}
