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

        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        }
        $categoryName = trim((string) ($category['name'] ?? ''));
        $parentName = trim((string) ($category['parent_name'] ?? ''));
        $parentChain = $parentName !== '' && $categoryName !== ''
            ? $parentName . ' › ' . $categoryName
            : ($categoryName !== '' ? $categoryName : $parentName);
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
            $description = seoApplyTemplate('{{child_category}} kategorisindeki en güncel içerikler, modlar ve rehberler. {{site_name}} üzerinden inceleyin.', [
                'category' => $categoryName,
                'child_category' => $categoryName,
                'parent' => $parentChain,
                'parent_category' => $parentChain,
                'count' => $topicCount,
                'site_name' => $siteName,
            ]);
        }

        $ogImage = trim((string) ($settings['og_image'] ?? ''));

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
                    'child_category' => $categoryName,
                    'parent' => $parentChain,
                    'parent_category' => $parentChain,
                    'category_description' => $description,
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

        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        }
        $username = trim((string) ($user['username'] ?? ''));
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

        $ogImage = !empty($user['avatar'])
            ? profileAvatarUrl($baseUri, (string) $user['avatar'])
            : trim((string) ($settings['og_image'] ?? ''));

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

        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($envConfig['APP_NAME'] ?? 'İçerik Topic'));
        }
        $title = trim((string) ($topic['meta_title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($topic['title'] ?? ''));
        }
        if ($title === '') {
            $title = $siteName;
        }

        // Always generate description directly from content, ignoring the potentially outdated meta_description field
        $descHtml = (string) ($topic['topic_descriptions'] ?? ($topic['description'] ?? ''));
        if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
            $descHtml = topicDescriptionWithoutRepeatedTitle($descHtml, (string) ($topic['title'] ?? ''));
        }
        $descriptionSource = $descHtml;
        
        $descriptionSource = html_entity_decode($descriptionSource, ENT_QUOTES, 'UTF-8');
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags($descriptionSource)) ?? '');

        $ogImage = trim((string) ($topic['primary_media_path'] ?? ''));
        if ($ogImage === '') {
            $ogImage = trim((string) ($topic['topic_first_image'] ?? ''));
        }
        if ($ogImage === '') {
            $ogImage = trim((string) ($settings['og_image'] ?? ''));
        }

        if ($canonicalUrl === '') {
            $slug = (string) ($topic['slug'] ?? '');
            $id = (int) ($topic['id'] ?? 0);
            if ($slug !== '') {
                $canonicalUrl = topicUrl($slug, $id > 0 ? $id : null);
            }
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
                    'topic_description' => $description,
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
