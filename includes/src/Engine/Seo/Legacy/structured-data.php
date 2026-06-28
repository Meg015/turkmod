<?php

declare(strict_types=1);

if (!function_exists('seoGetCategoryStructuredData')) {
    function seoGetCategoryStructuredData(array $category, array $items, array $settings): string
    {
        if (($settings['structured_data_category'] ?? '1') !== '1') {
            return '';
        }

        $baseUrl = seoCanonicalUrl('/', $settings);
        $categoryName = $category['name'] ?? '';
        $categorySlug = $category['slug'] ?? '';
        $parentName = $category['parent_name'] ?? '';

        // BreadcrumbList
        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Ana Sayfa',
                    'item' => $baseUrl
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Kategoriler',
                    'item' => seoCanonicalUrl(categoryListUrl(), $settings)
                ]
            ]
        ];

        $position = 3;
        if ($parentName !== '') {
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $parentName,
                'item' => seoCanonicalUrl(categoryUrl((string) ($category['parent_slug'] ?? '')), $settings)
            ];
        }

        $breadcrumbs['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $categoryName,
            'item' => seoCanonicalUrl(categoryUrl($categorySlug, (string) ($category['parent_slug'] ?? '')), $settings)
        ];

        // CollectionPage
        $collectionPage = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $categoryName,
            'description' => "$categoryName kategorisindeki tüm içerikler",
            'url' => seoCanonicalUrl(categoryUrl($categorySlug, (string) ($category['parent_slug'] ?? '')), $settings),
            'numberOfItems' => count($items)
        ];

        $breadcrumbJson = json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $collectionJson = json_encode($collectionPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">' . "\n" . $breadcrumbJson . "\n" . '</script>' . "\n" .
               '<script type="application/ld+json">' . "\n" . $collectionJson . "\n" . '</script>';
    }
}

if (!function_exists('seoGetProfileStructuredData')) {
    function seoGetProfileStructuredData(array $user, array $stats, array $settings): string
    {
        if (($settings['structured_data_profile'] ?? '1') !== '1') {
            return '';
        }

        global $baseUri;

        $username = $user['name'] ?? '';
        $avatar = !empty($user['avatar']) ? profileAvatarUrl($baseUri ?? '', (string) $user['avatar']) : '';
        $profileUrl = seoCanonicalUrl(publicProfileUrl($user), $settings);

        $personSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $username,
            'url' => $profileUrl,
            'interactionStatistic' => [
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/CreateAction',
                    'userInteractionCount' => (int) ($stats['topics'] ?? 0)
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/CommentAction',
                    'userInteractionCount' => (int) ($stats['comments'] ?? 0)
                ]
            ]
        ];

        if ($avatar !== '') {
            $personSchema['image'] = $avatar;
        }

        $orgName = $settings['schema_organization_name'] ?? 'İçerik Topic';
        if ($orgName !== '') {
            $personSchema['memberOf'] = [
                '@type' => 'Organization',
                'name' => $orgName
            ];

            if (!empty($settings['schema_organization_logo'])) {
                $logo = trim((string) $settings['schema_organization_logo']);
                $personSchema['memberOf']['logo'] = preg_match('~^(?:https?:)?//~i', $logo) === 1
                    ? $logo
                    : seoCanonicalUrl($logo, $settings);
            }
        }

        $json = json_encode($personSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }
}
