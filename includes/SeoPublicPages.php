<?php

declare(strict_types=1);

if (!function_exists('seoPublicPagePresetSettingKey')) {
    function seoPublicPagePresetSettingKey(): string
    {
        return 'seo_public_page_presets_json';
    }
}

if (!function_exists('seoPublicPageLockedNoindexKeys')) {
    /**
     * @return array<int,string>
     */
    function seoPublicPageLockedNoindexKeys(): array
    {
        return [];
    }
}

if (!function_exists('seoPublicPageIsNoindexLocked')) {
    function seoPublicPageIsNoindexLocked(string $pageKey): bool
    {
        return in_array($pageKey, seoPublicPageLockedNoindexKeys(), true);
    }
}

if (!function_exists('seoPublicPageDefaultNofollow')) {
    /**
     * @param array<string,mixed> $meta
     */
    function seoPublicPageDefaultNofollow(array $meta): bool
    {
        if (array_key_exists('default_nofollow', $meta)) {
            $value = $meta['default_nofollow'];
            if (is_bool($value)) {
                return $value;
            }

            $normalized = strtolower(trim((string) $value));
            if ($normalized === '') {
                return !empty($meta['default_noindex']);
            }
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }
        }

        return !empty($meta['default_noindex']);
    }
}

if (!function_exists('seoPublicPageApplyTemplate')) {
    function seoPublicPageApplyTemplate(string $template, array $vars): string
    {
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace('{{' . $key . '}}', (string) $value, $result);
        }

        return $result;
    }
}

if (!function_exists('seoPublicPageNormalizeSegment')) {
    function seoPublicPageNormalizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('routePrefixSanitize')) {
            $clean = routePrefixSanitize($value);
            if ($clean !== '') {
                return $clean;
            }
        }

        if (function_exists('slugify')) {
            $value = slugify($value);
        } else {
            $value = strtolower($value);
            $value = preg_replace('~[^a-z0-9_-]+~i', '-', $value) ?? '';
        }

        return trim((string) $value, "-_");
    }
}

if (!function_exists('seoPublicPageRoutePrefixDefaults')) {
    function seoPublicPageRoutePrefixDefaults(): array
    {
        if (function_exists('routePrefixDefaults')) {
            return routePrefixDefaults();
        }

        return [
            'topic' => 'konu',
            'category' => 'kategori',
            'category_list' => 'kategori',
            'profile' => 'profil',
        ];
    }
}

if (!function_exists('seoPublicPageRoutePrefixes')) {
    function seoPublicPageRoutePrefixes(?array $settings = null): array
    {
        $defaults = seoPublicPageRoutePrefixDefaults();
        $settings = is_array($settings) ? $settings : [];
        $resolved = $defaults;

        $settingMap = [
            'topic' => 'route_topic_prefix',
            'category' => 'route_category_prefix',
            'category_list' => 'route_category_list_prefix',
            'profile' => 'route_profile_prefix',
        ];

        foreach ($settingMap as $routeKey => $settingKey) {
            $candidate = seoPublicPageNormalizeSegment((string) ($settings[$settingKey] ?? ''));
            if ($candidate !== '') {
                $resolved[$routeKey] = $candidate;
            }
        }

        $uniqueCore = [
            'topic' => (string) ($resolved['topic'] ?? $defaults['topic']),
            'category' => (string) ($resolved['category'] ?? $defaults['category']),
            'profile' => (string) ($resolved['profile'] ?? $defaults['profile']),
        ];
        if (count(array_unique($uniqueCore)) !== count($uniqueCore)) {
            $resolved['topic'] = $defaults['topic'];
            $resolved['category'] = $defaults['category'];
            $resolved['profile'] = $defaults['profile'];
        }

        if (in_array((string) ($resolved['category_list'] ?? ''), [
            (string) ($resolved['topic'] ?? ''),
            (string) ($resolved['profile'] ?? ''),
        ], true)) {
            $resolved['category_list'] = $defaults['category_list'];
        }

        return $resolved;
    }
}

if (!function_exists('seoPublicPageStaticPathDefaults')) {
    function seoPublicPageStaticPathDefaults(): array
    {
        if (function_exists('routePublicStaticPathDefaults')) {
            return routePublicStaticPathDefaults();
        }

        return [
            'login' => 'giris',
            'register' => 'kayit',
            'logout' => 'cikis',
            'forgot_password' => 'sifremi-unuttum',
            'reset_password' => 'sifre-sifirla',
            'notifications' => 'bildirimler',
            'messages' => 'mesajlar',
            'leaderboard' => 'liderlik',
            'ban_appeals' => 'ban-itiraz',
            'contact' => 'iletisim',
            'upload_topic' => 'konu-yukle',
            'edit_topic' => 'konu-duzenle',
            'download' => 'indir',
            'events' => 'events',
        ];
    }
}

if (!function_exists('seoPublicPageStaticPaths')) {
    function seoPublicPageStaticPaths(?array $settings = null): array
    {
        $defaults = seoPublicPageStaticPathDefaults();
        if (!is_array($settings) || $settings === []) {
            return $defaults;
        }

        if (function_exists('routePublicStaticPathSettings')) {
            return routePublicStaticPathSettings(null, $settings);
        }

        $resolved = $defaults;
        $settingMap = [
            'login' => 'route_login_path',
            'register' => 'route_register_path',
            'logout' => 'route_logout_path',
            'forgot_password' => 'route_forgot_password_path',
            'reset_password' => 'route_reset_password_path',
            'notifications' => 'route_notifications_path',
            'messages' => 'route_messages_path',
            'leaderboard' => 'route_leaderboard_path',
            'ban_appeals' => 'route_ban_appeals_path',
            'contact' => 'route_contact_path',
            'upload_topic' => 'route_upload_topic_path',
            'edit_topic' => 'route_edit_topic_path',
            'download' => 'route_download_path',
            'events' => 'route_events_path',
        ];

        foreach ($settingMap as $routeKey => $settingKey) {
            $candidate = seoPublicPageNormalizeSegment((string) ($settings[$settingKey] ?? ''));
            if ($candidate !== '') {
                $resolved[$routeKey] = $candidate;
            }
        }

        return $resolved;
    }
}

if (!function_exists('seoPublicPageRoutePath')) {
    function seoPublicPageRoutePath(string $type, string $slug = '', ?array $settings = null): string
    {
        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $prefixes = seoPublicPageRoutePrefixes($settings);
        $prefix = (string) ($prefixes[$type] ?? ($prefixes['topic'] ?? $type));

        $path = $baseUri . '/' . trim($prefix, '/');
        $slug = trim($slug);
        if ($slug !== '') {
            $path .= '/' . rawurlencode($slug);
        }

        return $path;
    }
}

if (!function_exists('seoPublicPageStaticUrl')) {
    function seoPublicPageStaticUrl(string $routeKey, string $suffix = '', ?array $settings = null): string
    {
        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $paths = seoPublicPageStaticPaths($settings);
        $path = trim((string) ($paths[$routeKey] ?? ''), '/');
        $url = $baseUri . '/' . $path;

        $suffix = trim($suffix, '/');
        if ($suffix !== '') {
            $url .= '/' . $suffix;
        }

        return $url;
    }
}

if (!function_exists('seoPublicPageCatalog')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function seoPublicPageCatalog(?array $settings = null): array
    {
        $settings = is_array($settings) ? $settings : [];
        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($GLOBALS['envConfig']['APP_NAME'] ?? 'İçerik Topic'));
        }
        $siteDescription = trim((string) ($settings['site_description'] ?? $settings['footer_description'] ?? $settings['footer_text'] ?? ''));
        if ($siteDescription === '') {
            $siteDescription = 'Topluluk içerikleri, modlar ve rehberler.';
        }

        $items = [
            'home' => [
                'group' => 'core',
                'label' => 'Ana Sayfa',
                'path' => $baseUri . '/index.php',
                'summary' => 'Ana sayfa meta başlığı ve açıklaması.',
                'placeholders' => ['page_title', 'page_description', 'site_name', 'site_description'],
                'default_noindex' => false,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'search' => [
                'group' => 'core',
                'label' => 'Arama Sonuçları',
                'path' => $baseUri . '/?q=ornek',
                'summary' => 'Arama sonuçları için ayrı başlık ve açıklama.',
                'placeholders' => ['query', 'page_title', 'page_description', 'site_name', 'page_suffix'],
                'default_noindex' => true,
                'placeholder_title' => '{{query}} arama sonuçları{{page_suffix}} | {{site_name}}',
                'placeholder_description' => '{{query}} için arama sonuçları.',
            ],
            'category_list' => [
                'group' => 'core',
                'label' => 'Kategori Listesi',
                'path' => seoPublicPageRoutePath('category_list', '', $settings),
                'summary' => 'Kategorilerin genel liste sayfası.',
                'placeholders' => ['page_title', 'page_description', 'site_name', 'page_suffix'],
                'default_noindex' => false,
                'placeholder_title' => '{{page_title}}{{page_suffix}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'category' => [
                'group' => 'core',
                'label' => 'Kategori Sayfası',
                'path' => seoPublicPageRoutePath('category', 'ornek-kategori', $settings),
                'summary' => 'Kategori detay sayfaları için şablon.',
                'placeholders' => ['child_category', 'parent_category', 'category_description', 'site_name', 'page_suffix'],
                'default_noindex' => false,
                'placeholder_title' => '{{child_category}}{{page_suffix}} | {{site_name}}',
                'placeholder_description' => '{{category_description}}',
            ],
            'topic' => [
                'group' => 'core',
                'label' => 'Konu Sayfası',
                'path' => seoPublicPageRoutePath('topic', (string) ((string) ($settings['route_topic_id_suffix'] ?? '1') === '1' ? 'ornek-konu-123' : 'ornek-konu'), $settings),
                'summary' => 'Konu detay sayfaları için şablon.',
                'placeholders' => ['title', 'category', 'author', 'topic_description', 'site_name'],
                'default_noindex' => false,
                'placeholder_title' => '{{title}} | {{site_name}}',
                'placeholder_description' => '{{topic_description}}',
            ],
            'profile' => [
                'group' => 'core',
                'label' => 'Profilim',
                'path' => seoPublicPageRoutePath('profile', '', $settings),
                'summary' => 'Kullanıcının kendi profil ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => ((string) ($settings['robots_noindex_profiles'] ?? '0')) === '1',
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'public_profile' => [
                'group' => 'core',
                'label' => 'Kullanıcı Profili',
                'path' => seoPublicPageRoutePath('profile', 'ornek-uye-123', $settings),
                'summary' => 'Diğer kullanıcıların profil sayfaları.',
                'placeholders' => ['username', 'topics', 'comments', 'views', 'downloads', 'page_title', 'page_description', 'site_name'],
                'default_noindex' => ((string) ($settings['robots_noindex_profiles'] ?? '0')) === '1',
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'login' => [
                'group' => 'account',
                'label' => 'Giriş',
                'path' => seoPublicPageStaticUrl('login', '', $settings),
                'summary' => 'Giriş ekranı için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'register' => [
                'group' => 'account',
                'label' => 'Kayıt',
                'path' => seoPublicPageStaticUrl('register', '', $settings),
                'summary' => 'Kayıt ekranı için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'logout' => [
                'group' => 'account',
                'label' => 'Çıkış',
                'path' => seoPublicPageStaticUrl('logout', '', $settings),
                'summary' => 'Oturum kapatma işlemi.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'forgot_password' => [
                'group' => 'account',
                'label' => 'Şifremi Unuttum',
                'path' => seoPublicPageStaticUrl('forgot_password', '', $settings),
                'summary' => 'Şifre sıfırlama isteği ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'reset_password' => [
                'group' => 'account',
                'label' => 'Şifre Sıfırla',
                'path' => seoPublicPageStaticUrl('reset_password', '', $settings),
                'summary' => 'Şifre sıfırlama formu.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'notifications' => [
                'group' => 'utility',
                'label' => 'Bildirimler',
                'path' => seoPublicPageStaticUrl('notifications', '', $settings),
                'summary' => 'Bildirim sayfası için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'messages' => [
                'group' => 'utility',
                'label' => 'Mesajlar',
                'path' => seoPublicPageStaticUrl('messages', '', $settings),
                'summary' => 'Mesaj kutusu için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'leaderboard' => [
                'group' => 'utility',
                'label' => 'Liderlik Tablosu',
                'path' => seoPublicPageStaticUrl('leaderboard', '', $settings),
                'summary' => 'Liderlik sayfası için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name', 'page_suffix'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}}{{page_suffix}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'ban_appeals' => [
                'group' => 'utility',
                'label' => 'Ban İtirazları',
                'path' => seoPublicPageStaticUrl('ban_appeals', '', $settings),
                'summary' => 'Ban itiraz ekranı için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => false,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'contact' => [
                'group' => 'utility',
                'label' => 'İletişim',
                'path' => seoPublicPageStaticUrl('contact', '', $settings),
                'summary' => 'İletişim sayfası için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => false,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'upload_topic' => [
                'group' => 'utility',
                'label' => 'Konu Yükle',
                'path' => seoPublicPageStaticUrl('upload_topic', '', $settings),
                'summary' => 'Yeni içerik yükleme ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'edit_topic' => [
                'group' => 'utility',
                'label' => 'Konu Düzenle',
                'path' => seoPublicPageStaticUrl('edit_topic', '', $settings),
                'summary' => 'İçerik düzenleme ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'download' => [
                'group' => 'utility',
                'label' => 'İndirme',
                'path' => seoPublicPageStaticUrl('download', '', $settings),
                'summary' => 'İndirme yönlendirme sayfası.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'events' => [
                'group' => 'events',
                'label' => 'Etkinlikler',
                'path' => seoPublicPageStaticUrl('events', '', $settings),
                'summary' => 'Etkinlik merkezinin ana ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'error_404' => [
                'group' => 'utility',
                'label' => '404 Hata Sayfası',
                'path' => seoPublicPageStaticUrl('404', '', $settings),
                'summary' => 'Bulunamayan sayfalar için meta ayarları.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => 'Sayfa Bulunamadı | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'events.wheel' => [
                'group' => 'events',
                'label' => 'Çark',
                'path' => seoPublicPageStaticUrl('events', 'wheel', $settings),
                'summary' => 'Çark çevirmeli etkinlik ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'events.raffle' => [
                'group' => 'events',
                'label' => 'Çekilişler',
                'path' => seoPublicPageStaticUrl('events', 'raffle', $settings),
                'summary' => 'Çekiliş listesi ve sonuç ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'events.rewards' => [
                'group' => 'events',
                'label' => 'Ödüllerim',
                'path' => seoPublicPageStaticUrl('events', 'rewards', $settings),
                'summary' => 'Kazanılan ödül geçmişi ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
            'events.tasks' => [
                'group' => 'events',
                'label' => 'Görevler',
                'path' => seoPublicPageStaticUrl('events', 'tasks', $settings),
                'summary' => 'Görev ve puan kazanma ekranı.',
                'placeholders' => ['page_title', 'page_description', 'site_name'],
                'default_noindex' => true,
                'placeholder_title' => '{{page_title}} | {{site_name}}',
                'placeholder_description' => '{{page_description}}',
            ],
        ];

        return $items;
    }
}

if (!function_exists('seoPublicPageGroups')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function seoPublicPageGroups(?array $settings = null): array
    {
        $catalog = seoPublicPageCatalog($settings);

        return [
            'core' => [
                'title' => 'Çekirdek Sayfalar',
                'description' => 'Ana sayfa, arama, kategori, konu ve profil türleri.',
                'icon' => 'bi-house-door',
                'pages' => array_intersect_key($catalog, array_flip(['home', 'search', 'category_list', 'category', 'topic', 'profile', 'public_profile'])),
            ],
            'account' => [
                'title' => 'Üyelik',
                'description' => 'Giriş, kayıt, çıkış ve parola akışları.',
                'icon' => 'bi-person-check',
                'pages' => array_intersect_key($catalog, array_flip(['login', 'register', 'logout', 'forgot_password', 'reset_password'])),
            ],
            'utility' => [
                'title' => 'Araçlar',
                'description' => 'Bildirim, mesaj, iletişim ve yükleme sayfaları.',
                'icon' => 'bi-wrench-adjustable-circle',
                'pages' => array_intersect_key($catalog, array_flip(['notifications', 'messages', 'leaderboard', 'ban_appeals', 'contact', 'upload_topic', 'edit_topic', 'download', 'error_404'])),
            ],
            'events' => [
                'title' => 'Etkinlikler',
                'description' => 'Etkinlik merkezi ve alt ekranları.',
                'icon' => 'bi-calendar-event',
                'pages' => array_intersect_key($catalog, array_flip(['events', 'events.wheel', 'events.raffle', 'events.rewards', 'events.tasks'])),
            ],
        ];
    }
}

if (!function_exists('seoPublicPagePresetsNormalize')) {
    /**
     * @param mixed $raw
     * @return array<string,array<string,string>>
     */
    function seoPublicPagePresetsNormalize(mixed $raw): array
    {
        $catalog = seoPublicPageCatalog();
        $source = [];
        $fromArray = is_array($raw);

        if (is_string($raw)) {
            $decoded = json_decode(trim($raw), true);
            if (is_array($decoded)) {
                $source = $decoded;
            }
        } elseif (is_array($raw)) {
            $source = $raw;
        }

        $normalized = [];
        foreach ($catalog as $pageKey => $meta) {
            $entry = $source[$pageKey] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $title = trim((string) ($entry['title'] ?? ''));
            $description = trim((string) ($entry['description'] ?? ''));
            $image = trim((string) ($entry['image'] ?? ''));
            $lockedNoindex = seoPublicPageIsNoindexLocked((string) $pageKey);
            $defaultNoindex = $lockedNoindex || !empty($meta['default_noindex']);
            $defaultNofollow = seoPublicPageDefaultNofollow($meta);
            $inputNoindexDefault = $fromArray ? false : $defaultNoindex;
            $inputNofollowDefault = $fromArray ? false : $defaultNofollow;
            $inputSitemapDefault = $fromArray ? false : true;
            $noindex = $lockedNoindex
                ? true
                : seoPublicPageBoolValue($entry['noindex'] ?? null, $inputNoindexDefault);
            $nofollow = seoPublicPageBoolValue($entry['nofollow'] ?? null, $inputNofollowDefault);

            $page = [];
            if ($title !== '') {
                $page['title'] = $title;
            }
            if ($description !== '') {
                $page['description'] = $description;
            }
            if ($image !== '') {
                $page['image'] = $image;
            }
            if ($noindex !== $defaultNoindex) {
                $page['noindex'] = $noindex ? '1' : '0';
            }
            if ($nofollow !== $defaultNofollow) {
                $page['nofollow'] = $nofollow ? '1' : '0';
            }
            
            $sitemapInclude = seoPublicPageBoolValue($entry['sitemap_include'] ?? null, $inputSitemapDefault);
            if ($noindex || !$sitemapInclude) {
                $page['sitemap_include'] = '0';
            }
            
            $sitemapPriority = trim((string) ($entry['sitemap_priority'] ?? ''));
            if ($sitemapPriority !== '' && is_numeric($sitemapPriority)) {
                $page['sitemap_priority'] = $sitemapPriority;
            }

            if ($page !== []) {
                $normalized[$pageKey] = $page;
            }
        }

        return $normalized;
    }
}

if (!function_exists('seoPublicPagePresetsJson')) {
    /**
     * @param mixed $raw
     */
    function seoPublicPagePresetsJson(mixed $raw): string
    {
        $normalized = seoPublicPagePresetsNormalize($raw);
        if ($normalized === []) {
            return '';
        }

        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return is_string($json) ? $json : '';
    }
}

if (!function_exists('seoPublicPagePresetsFromSettings')) {
    /**
     * @return array<string,array<string,string>>
     */
    function seoPublicPagePresetsFromSettings(?array $settings = null): array
    {
        $settings = function_exists('seoSettings') ? seoSettings($settings) : (is_array($settings) ? $settings : []);
        $raw = (string) ($settings[seoPublicPagePresetSettingKey()] ?? '');

        return seoPublicPagePresetsNormalize($raw);
    }
}

if (!function_exists('seoPublicPagePresetForKey')) {
    /**
     * @return array<string,string>
     */
    function seoPublicPagePresetForKey(string $pageKey, ?array $settings = null): array
    {
        $catalog = seoPublicPageCatalog($settings);
        $stored = seoPublicPagePresetsFromSettings($settings);
        $defaultNoindex = !empty($catalog[$pageKey]['default_noindex']);
        $defaultNofollow = seoPublicPageDefaultNofollow($catalog[$pageKey] ?? []);

        $defaultPriority = [
            'home' => '1.0',
            'category_list' => '0.9',
            'search' => '0.8', 'category' => '0.8', 'topic' => '0.8', 'profile' => '0.8', 'public_profile' => '0.8',
            'leaderboard' => '0.7', 'contact' => '0.7',
            'events' => '0.6', 'events.wheel' => '0.6', 'events.raffle' => '0.6', 'events.rewards' => '0.6', 'events.tasks' => '0.6',
            'login' => '0.3', 'register' => '0.3', 'forgot_password' => '0.3', 'reset_password' => '0.3',
            'error_404' => '0.1', 'ban_appeals' => '0.1', 'upload_topic' => '0.1', 'edit_topic' => '0.1', 'download' => '0.1'
        ][$pageKey] ?? '0.5';

        $preset = [
            'title' => '',
            'description' => '',
            'image' => '',
            'noindex' => $defaultNoindex ? '1' : '0',
            'nofollow' => $defaultNofollow ? '1' : '0',
            'sitemap_include' => '1',
            'sitemap_priority' => $defaultPriority,
        ];

        if (isset($stored[$pageKey]) && is_array($stored[$pageKey])) {
            $preset = array_merge($preset, $stored[$pageKey]);
        }

        $preset['title'] = trim((string) ($preset['title'] ?? ''));
        $preset['description'] = trim((string) ($preset['description'] ?? ''));
        $preset['image'] = trim((string) ($preset['image'] ?? ''));
        $preset['noindex'] = seoPublicPageBoolValue($preset['noindex'] ?? null, $defaultNoindex) ? '1' : '0';
        $preset['nofollow'] = seoPublicPageBoolValue($preset['nofollow'] ?? null, $defaultNofollow) ? '1' : '0';
        $preset['sitemap_include'] = seoPublicPageBoolValue($preset['sitemap_include'] ?? null, true) ? '1' : '0';
        if ($preset['noindex'] === '1') {
            $preset['sitemap_include'] = '0';
        }
        $preset['sitemap_priority'] = trim((string) ($preset['sitemap_priority'] ?? $defaultPriority));

        return $preset;
    }
}

if (!function_exists('seoPublicPageBoolValue')) {
    function seoPublicPageBoolValue(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('seoPublicPageResolveKey')) {
    function seoPublicPageResolveKey(?string $requestUri = null, ?array $settings = null, ?string $fallbackPageKey = null): string
    {
        $settings = function_exists('seoSettings') ? seoSettings($settings) : (is_array($settings) ? $settings : []);
        $requestUri = $requestUri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        $query = (string) parse_url($requestUri, PHP_URL_QUERY);
        $baseUri = trim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        $normalizedPath = trim(rawurldecode($path), '/');

        if ($baseUri !== '') {
            $baseSegments = array_values(array_filter(explode('/', $baseUri), static fn (string $part): bool => $part !== ''));
            $pathSegments = array_values(array_filter(explode('/', $normalizedPath), static fn (string $part): bool => $part !== ''));
            if ($baseSegments !== [] && array_slice($pathSegments, 0, count($baseSegments)) === $baseSegments) {
                $normalizedPath = implode('/', array_slice($pathSegments, count($baseSegments)));
            }
        }

        $normalizedPath = trim($normalizedPath, '/');
        $pathSegments = $normalizedPath !== ''
            ? array_values(array_filter(explode('/', $normalizedPath), static fn (string $part): bool => $part !== ''))
            : [];

        $hasSearchQuery = $query !== '' && (str_contains($query, 'q=') || str_contains($query, 'search='));
        if ($normalizedPath === '' || in_array($normalizedPath, ['index.php', 'index'], true)) {
            if ($hasSearchQuery) {
                return 'search';
            }
            if ($fallbackPageKey === 'search') {
                return 'search';
            }
            return $fallbackPageKey !== null && $fallbackPageKey !== '' ? $fallbackPageKey : 'home';
        }

        if (function_exists('routeAuthPageKey')) {
            $authPageKey = routeAuthPageKey($requestUri);
            if ($authPageKey !== '') {
                return $authPageKey;
            }
        }

        $staticPaths = seoPublicPageStaticPaths($settings);
        $staticRouteMap = [
            'login' => 'login',
            'register' => 'register',
            'logout' => 'logout',
            'forgot_password' => 'forgot_password',
            'reset_password' => 'reset_password',
            'notifications' => 'notifications',
            'messages' => 'messages',
            'leaderboard' => 'leaderboard',
            'ban_appeals' => 'ban_appeals',
            'contact' => 'contact',
            'upload_topic' => 'upload_topic',
            'edit_topic' => 'edit_topic',
            'download' => 'download',
        ];
        foreach ($staticRouteMap as $routeKey => $pageKey) {
            $cleanPath = trim((string) ($staticPaths[$routeKey] ?? ''), '/');
            if ($cleanPath !== '' && $normalizedPath === $cleanPath) {
                return $pageKey;
            }
        }

        $eventsBase = trim((string) ($staticPaths['events'] ?? 'events'), '/');
        if ($eventsBase !== '') {
            if ($normalizedPath === $eventsBase) {
                return 'events';
            }
            if (str_starts_with($normalizedPath, $eventsBase . '/')) {
                $suffix = trim(substr($normalizedPath, strlen($eventsBase) + 1), '/');
                if ($suffix !== '') {
                    return 'events.' . strtolower(str_replace('-', '_', $suffix));
                }
            }
        }

        $prefixes = seoPublicPageRoutePrefixes($settings);
        $prefix = strtolower((string) ($pathSegments[0] ?? ''));
        if ($prefix !== '') {
            if ($prefix === strtolower((string) ($prefixes['topic'] ?? ''))) {
                return 'topic';
            }
            if ($prefix === strtolower((string) ($prefixes['category_list'] ?? '')) || $prefix === strtolower((string) ($prefixes['category'] ?? ''))) {
                return count($pathSegments) <= 1 ? 'category_list' : 'category';
            }
            if ($prefix === strtolower((string) ($prefixes['profile'] ?? ''))) {
                return count($pathSegments) <= 1 ? 'profile' : 'public_profile';
            }
        }

        if ($pathSegments !== []) {
            $prefix = strtolower((string) ($pathSegments[0] ?? ''));
            if ($prefix === 'search' && $hasSearchQuery) {
                return 'search';
            }
        }

        return $fallbackPageKey !== null && $fallbackPageKey !== '' ? $fallbackPageKey : 'home';
    }
}

if (!function_exists('seoPublicPageTemplateVars')) {
    /**
     * @return array<string,scalar|null>
     */
    function seoPublicPageTemplateVars(string $pageKey, array $context = [], ?array $settings = null): array
    {
        $settings = function_exists('seoSettings') ? seoSettings($settings) : (is_array($settings) ? $settings : []);
        $siteName = trim((string) ($settings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) ($GLOBALS['envConfig']['APP_NAME'] ?? 'İçerik Topic'));
        }
        $siteDescription = trim((string) ($settings['site_description'] ?? $settings['footer_description'] ?? $settings['footer_text'] ?? ''));
        if ($siteDescription === '') {
            $siteDescription = 'Topluluk içerikleri, modlar ve rehberler.';
        }

        $vars = [
            'site_name' => $siteName,
            'site_description' => $siteDescription,
            'page_title' => trim((string) ($context['page_title'] ?? '')),
            'page_description' => trim((string) ($context['page_description'] ?? '')),
            'category_description' => trim((string) ($context['category_description'] ?? '')),
            'topic_description' => trim((string) ($context['topic_description'] ?? '')),
            'query' => trim((string) ($context['query'] ?? ($_GET['q'] ?? $_GET['search'] ?? ''))),
            'title' => trim((string) ($context['title'] ?? '')),
            'category' => trim((string) ($context['category'] ?? '')),
            'child_category' => trim((string) ($context['child_category'] ?? '')),
            'parent' => trim((string) ($context['parent'] ?? '')),
            'parent_category' => trim((string) ($context['parent_category'] ?? '')),
            'count' => (string) (int) ($context['count'] ?? 0),
            'author' => trim((string) ($context['author'] ?? '')),
            'excerpt' => trim((string) ($context['excerpt'] ?? '')),
            'page_number' => (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 1) ? (string) (int)$_GET['page'] : '',
            'page_suffix' => (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 1) ? ' - Sayfa ' . (int)$_GET['page'] : '',
            'username' => trim((string) ($context['username'] ?? '')),
            'topics' => (string) (int) ($context['topics'] ?? 0),
            'comments' => (string) (int) ($context['comments'] ?? 0),
            'views' => (string) (int) ($context['views'] ?? 0),
            'downloads' => (string) (int) ($context['downloads'] ?? 0),
            'event_name' => trim((string) ($context['event_name'] ?? '')),
            'page_key' => $pageKey,
        ];

        if ($vars['title'] === '' && $vars['page_title'] !== '') {
            $vars['title'] = (string) $vars['page_title'];
        }
        if ($vars['page_title'] === '' && $vars['title'] !== '') {
            $vars['page_title'] = (string) $vars['title'];
        }
        if ($vars['category'] === '' && $vars['title'] !== '') {
            $vars['category'] = (string) $vars['title'];
        }
        if ($vars['child_category'] === '' && $vars['title'] !== '') {
            $vars['child_category'] = (string) $vars['title'];
        }
        if ($vars['username'] === '' && $vars['title'] !== '') {
            $vars['username'] = (string) $vars['title'];
        }
        if ($vars['event_name'] === '' && $vars['page_title'] !== '') {
            $vars['event_name'] = (string) $vars['page_title'];
        }

        return $vars;
    }
}

if (!function_exists('seoPublicPageMeta')) {
    /**
     * @param array{title?:string,description?:string,image?:string} $defaults
     * @return array{title:string,description:string,image:string,title_is_final:bool,noindex:bool,nofollow:bool,page_key:string,preset:array<string,string>}
     */
    function seoPublicPageMeta(string $pageKey, array $defaults = [], array $context = [], ?array $settings = null): array
    {
        $settings = function_exists('seoSettings') ? seoSettings($settings) : (is_array($settings) ? $settings : []);
        $preset = seoPublicPagePresetForKey($pageKey, $settings);
        $vars = seoPublicPageTemplateVars($pageKey, array_merge($defaults, $context), $settings);

        $title = trim((string) ($defaults['title'] ?? ''));
        $description = trim((string) ($defaults['description'] ?? ''));
        $image = trim((string) ($defaults['image'] ?? ''));
        $titleIsFinal = false;

        if ($preset['title'] !== '') {
            $title = seoPublicPageApplyTemplate($preset['title'], $vars);
            $titleIsFinal = true;
        }
        if ($preset['description'] !== '') {
            $description = seoPublicPageApplyTemplate($preset['description'], $vars);
        }
        if ($preset['image'] !== '') {
            $image = $preset['image'];
        }

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'title_is_final' => $titleIsFinal,
            'noindex' => $preset['noindex'] === '1',
            'nofollow' => $preset['nofollow'] === '1',
            'page_key' => $pageKey,
            'preset' => $preset,
        ];
    }
}

if (!function_exists('seoPublicPageMetaTags')) {
    /**
     * Build meta tags for a public page while honoring the public page preset.
     *
     * @param array{title?:string,description?:string,image?:string} $defaults
     */
    function seoPublicPageMetaTags(
        string $pageKey,
        array $defaults = [],
        array $context = [],
        ?array $settings = null,
        string $canonicalUrl = '',
        bool $includeCanonical = true,
        ?string $ogType = null
    ): string {
        $resolved = seoPublicPageMeta($pageKey, $defaults, $context, $settings);

        $previousSkip = $GLOBALS['_seo_skip_public_page_presets'] ?? null;
        $previousTitleFinal = $GLOBALS['_seo_public_page_title_is_final'] ?? null;
        $GLOBALS['_seo_skip_public_page_presets'] = true;
        $GLOBALS['_seo_public_page_title_is_final'] = !empty($resolved['title_is_final']);

        try {
            return getSeoMeta(
                (string) ($resolved['title'] ?? ($defaults['title'] ?? '')),
                (string) ($resolved['description'] ?? ($defaults['description'] ?? '')),
                $canonicalUrl,
                (string) ($resolved['image'] ?? ($defaults['image'] ?? '')),
                $includeCanonical,
                $ogType
            );
        } finally {
            if ($previousSkip === null) {
                unset($GLOBALS['_seo_skip_public_page_presets']);
            } else {
                $GLOBALS['_seo_skip_public_page_presets'] = $previousSkip;
            }

            if ($previousTitleFinal === null) {
                unset($GLOBALS['_seo_public_page_title_is_final']);
            } else {
                $GLOBALS['_seo_public_page_title_is_final'] = $previousTitleFinal;
            }
        }
    }
}

if (!function_exists('seoPublicPageIsNoindex')) {
    function seoPublicPageIsNoindex(string $pageKey, ?array $settings = null): bool
    {
        $preset = seoPublicPagePresetForKey($pageKey, $settings);

        return ($preset['noindex'] ?? '0') === '1';
    }
}

if (!function_exists('seoPublicPageIsNofollow')) {
    function seoPublicPageIsNofollow(string $pageKey, ?array $settings = null): bool
    {
        $preset = seoPublicPagePresetForKey($pageKey, $settings);

        return ($preset['nofollow'] ?? '0') === '1';
    }
}

if (!function_exists('seoPublicPageShouldAppearInSitemap')) {
    function seoPublicPageShouldAppearInSitemap(string $pageKey, ?array $settings = null): bool
    {
        $settings = function_exists('seoSettings')
            ? seoSettings($settings)
            : (is_array($settings) ? $settings : []);

        if (function_exists('seoIndexToggleValue')) {
            if (seoIndexToggleValue($settings, 'allow_indexing', '1') !== '1') {
                return false;
            }
        } elseif ((string) ($settings['allow_indexing'] ?? '1') !== '1') {
            return false;
        }

        return !seoPublicPageIsNoindex($pageKey, $settings) && (seoPublicPagePresetForKey($pageKey, $settings)['sitemap_include'] ?? '1') === '1';
    }
}

if (!function_exists('seoPublicPageSitemapPriority')) {
    function seoPublicPageSitemapPriority(string $pageKey, ?array $settings = null): string
    {
        return seoPublicPagePresetForKey($pageKey, $settings)['sitemap_priority'] ?? '0.5';
    }
}

if (!function_exists('seoSitemapTopicStatuses')) {
    /**
     * @return list<string>
     */
    function seoSitemapTopicStatuses(?array $settings = null): array
    {
        $settings = function_exists('seoSettings')
            ? seoSettings($settings)
            : (is_array($settings) ? $settings : []);

        if (!seoPublicPageShouldAppearInSitemap('topic', $settings)) {
            return [];
        }

        $statuses = ['published'];
        $allowDraftsInSitemap = ((string) ($settings['sitemap_exclude_drafts'] ?? '1')) !== '1';
        $draftsIndexable = seoIndexToggleValue($settings, 'index_draft_topics', '0') === '1';

        if ($allowDraftsInSitemap && $draftsIndexable) {
            $statuses[] = 'draft';
        }

        return $statuses;
    }
}

if (!function_exists('seoTopicShouldAppearInSitemap')) {
    function seoTopicShouldAppearInSitemap(array $topic, ?array $settings = null): bool
    {
        $statuses = seoSitemapTopicStatuses($settings);
        if ($statuses === []) {
            return false;
        }

        $status = strtolower(trim((string) ($topic['status'] ?? 'published')));

        return in_array($status, $statuses, true);
    }
}

if (!function_exists('seoCategoryShouldAppearInSitemap')) {
    /**
     * @param array<string,mixed> $category
     */
    function seoCategoryTopicCountRecursive(array $category): int
    {
        $total = max(0, (int) ($category['topic_count'] ?? 0));
        foreach (($category['children'] ?? []) as $child) {
            if (is_array($child)) {
                $total += seoCategoryTopicCountRecursive($child);
            }
        }

        return $total;
    }
}

if (!function_exists('seoCategoryShouldAppearInSitemap')) {
    function seoCategoryShouldAppearInSitemap(array $category, ?array $settings = null): bool
    {
        $settings = function_exists('seoSettings')
            ? seoSettings($settings)
            : (is_array($settings) ? $settings : []);

        if (!seoPublicPageShouldAppearInSitemap('category', $settings)) {
            return false;
        }

        $topicCount = function_exists('seoCategoryTopicCountRecursive')
            ? seoCategoryTopicCountRecursive($category)
            : max(0, (int) ($category['topic_count'] ?? 0));
        if ($topicCount > 0) {
            return true;
        }

        return seoIndexToggleValue($settings, 'index_empty_categories', '0') === '1';
    }
}
