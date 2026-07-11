<?php

declare(strict_types=1);
/**
 * Sidebar Render Helper
 * Sayfa bazlı dinamik sidebar render sistemi
 */

if (!function_exists('renderSidebar')) {
    /**
     * Sidebar render fonksiyonu
     * @param PDO $pdo
     * @param string $context 'home', 'topic', 'category', 'search'
     * @param array $data Context'e özel veri (topic_id, category_slug, vb.)
     * @return string HTML
     */
    function renderSidebar(PDO $pdo, string $context = 'home', array $data = []): string
    {
        $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
        
        // Sidebar aktif mi?
        if (($settings['sidebar_enabled'] ?? '1') !== '1') {
            return '';
        }
        
        // Context'e göre template seç
        $template = $settings["sidebar_{$context}_template"] ?? 'default';
        
        // Widget'ları topla
        $widgets = [];
        
        switch ($context) {
            case 'home':
                if (($settings['sidebar_home_popular'] ?? '1') === '1') {
                    $widgets[] = renderPopularWidget($pdo, $settings);
                }
                if (($settings['sidebar_home_categories'] ?? '1') === '1') {
                    $widgets[] = renderCategoriesWidget($pdo, $settings);
                }
                if (($settings['sidebar_home_stats'] ?? '1') === '1') {
                    $widgets[] = renderStatsWidget($pdo, $settings);
                }
                if (!empty($settings['sidebar_home_custom'] ?? '')) {
                    $widgets[] = renderCustomWidget($settings['sidebar_home_custom']);
                }
                break;
                
            case 'topic':
                if (($settings['sidebar_topic_related'] ?? '1') === '1') {
                    $widgets[] = renderRelatedTopicsWidget($pdo, $settings, $data);
                }
                if (($settings['sidebar_topic_popular'] ?? '1') === '1') {
                    $widgets[] = renderPopularWidget($pdo, $settings, $data['topic_id'] ?? null);
                }
                if (($settings['sidebar_topic_author'] ?? '1') === '1' && !empty($data['author'])) {
                    $widgets[] = renderAuthorWidget($data['author']);
                }
                if (!empty($settings['sidebar_topic_custom'] ?? '')) {
                    $widgets[] = renderCustomWidget($settings['sidebar_topic_custom']);
                }
                break;
                
            case 'category':
                if (($settings['sidebar_category_list'] ?? '1') === '1') {
                    $widgets[] = renderCategoryListWidget($pdo, $settings, $data['category_slug'] ?? '');
                }
                if (($settings['sidebar_category_popular'] ?? '1') === '1') {
                    $widgets[] = renderPopularWidget($pdo, $settings);
                }
                if (($settings['sidebar_category_stats'] ?? '1') === '1') {
                    $widgets[] = renderCategoryStatsWidget($pdo, $settings, $data);
                }
                if (!empty($settings['sidebar_category_custom'] ?? '')) {
                    $widgets[] = renderCustomWidget($settings['sidebar_category_custom']);
                }
                break;
                
            case 'search':
                if (($settings['sidebar_search_filters'] ?? '1') === '1') {
                    $widgets[] = renderSearchFiltersWidget($settings, $data);
                }
                if (($settings['sidebar_search_categories'] ?? '1') === '1') {
                    $widgets[] = renderCategoriesWidget($pdo, $settings);
                }
                if (($settings['sidebar_search_popular'] ?? '1') === '1') {
                    $widgets[] = renderPopularSearchesWidget($pdo, $settings);
                }
                if (!empty($settings['sidebar_search_custom'] ?? '')) {
                    $widgets[] = renderCustomWidget($settings['sidebar_search_custom']);
                }
                break;
        }
        
        $html = '<aside class="public-sidebar topic-sidebar" aria-label="Sidebar">';
        foreach ($widgets as $widget) {
            if (!empty($widget)) {
                $html .= $widget;
            }
        }
        $html .= '</aside>';
        
        return $html;
    }
}

if (!function_exists('renderSidebarContentWidget')) {
    function renderSidebarContentWidget(array $items, string $title, string $icon, string $badgeLabel, string $emptyText, string $extraClass = ''): string
    {
        $class = trim('public-widget sidebar-panel sidebar-popular-widget ' . $extraClass);
        $html = '<section class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="public-widget__header sidebar-popular-header ui-panel__head">';
        $html .= '<h2><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        $html .= '<span class="sidebar-popular-badge"><i class="bi bi-layers"></i> ' . htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        $html .= '<div class="public-widget__body ui-panel__body">';

        if (empty($items)) {
            $html .= '<p class="sidebar-widget-empty">' . htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</div></section>';
            return $html;
        }

        $html .= '<div class="sidebar-popular-list">';
        $rank = 0;
        foreach (array_slice($items, 0, 5) as $item) {
            $rank++;
            $url = topicUrlForRow($item);
            $titleText = htmlspecialchars((string)($item['title'] ?? 'İçerik'), ENT_QUOTES, 'UTF-8');
            $category = htmlspecialchars((string)($item['category'] ?? 'Genel'), ENT_QUOTES, 'UTF-8');
            $date = date('d.m.Y', strtotime((string)($item['created_at'] ?? 'now')));
            $viewCount = number_format((int)($item['view_count'] ?? 0), 0, ',', '.');
            $rankClass = $rank <= 3 ? ' top-rank' : '';

            $html .= "<a href=\"{$url}\" class=\"sidebar-popular-item{$rankClass}\">";
            $html .= "<span class=\"sidebar-popular-rank\">{$rank}</span>";
            $html .= '<div class="sidebar-popular-content ui-section">';
            $html .= "<span class=\"sidebar-popular-title\">{$titleText}</span>";
            $html .= '<div class="sidebar-popular-meta">';
            $html .= "<span><i class=\"bi bi-folder2-open\"></i> {$category}</span>";
            $html .= "<span><i class=\"bi bi-eye-fill\"></i> {$viewCount}</span>";
            $html .= "<span><i class=\"bi bi-calendar3\"></i> {$date}</span>";
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<span class="sidebar-popular-action" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>';
            $html .= '</a>';
        }
        $html .= '</div></div></section>';

        return $html;
    }
}

if (!function_exists('sidebarBuilderWidgetCatalog')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function sidebarBuilderWidgetCatalog(): array
    {
        return [
            'navigation_menu' => [
                'label' => 'Ana Navigasyon',
                'description' => 'Gorunum > Menu ogeleriyle senkron calisan linkler.',
                'icon' => 'bi-list-nested',
                'default_area' => 'left',
                'default_title' => 'Menu',
                'settings' => [],
            ],
            'category_tree' => [
                'label' => 'Kategori Agaci',
                'description' => 'Akordeon kategori listesi, aktif dal ve sayaclar.',
                'icon' => 'bi-grid-3x3-gap',
                'default_area' => 'left',
                'default_title' => 'Kategoriler',
                'settings' => [
                    'limit' => 16,
                    'accordion' => true,
                    'show_counts' => true,
                ],
            ],
            'recent_comments' => [
                'label' => 'Son Yorumlar',
                'description' => 'Son onayli yorumlar, yazar ve tarih bilgisi.',
                'icon' => 'bi-chat-square-text',
                'default_area' => 'right',
                'default_title' => 'Son Yorumlar',
                'settings' => [
                    'limit' => 3,
                ],
            ],
            'popular_topics' => [
                'label' => 'Populer Konular',
                'description' => 'Indirme, goruntulenme veya tarihe gore populer icerikler.',
                'icon' => 'bi-fire',
                'default_area' => 'right',
                'default_title' => 'Populer',
                'settings' => [
                    'limit' => 5,
                    'sort' => 'downloads',
                ],
            ],
            'tag_cloud' => [
                'label' => 'Etiket Bulutu',
                'description' => 'Kategoriler veya populer aramalar icin etiket bulutu.',
                'icon' => 'bi-tags',
                'default_area' => 'right',
                'default_title' => 'Etiketler',
                'settings' => [
                    'limit' => 12,
                ],
            ],
            'editor_picks' => [
                'label' => 'Editorun Sectikleri',
                'description' => 'Manuel secilen konular veya otomatik one cikan icerikler.',
                'icon' => 'bi-stars',
                'default_area' => 'right',
                'default_title' => 'Editorun Sectikleri',
                'settings' => [
                    'limit' => 5,
                    'topic_ids' => '',
                ],
            ],
            'category_showcase' => [
                'label' => 'Kategori Vitrini',
                'description' => 'Secili kategorileri ikonlu mini vitrin olarak gosterir.',
                'icon' => 'bi-grid-1x2',
                'default_area' => 'left',
                'default_title' => 'Kategori Vitrini',
                'settings' => [
                    'limit' => 6,
                    'category_slugs' => '',
                    'show_counts' => true,
                ],
            ],
            'latest_downloads' => [
                'label' => 'Son Indirilenler',
                'description' => 'Son indirilen veya guncel modlari listeler.',
                'icon' => 'bi-cloud-arrow-down',
                'default_area' => 'right',
                'default_title' => 'Son Indirilenler',
                'settings' => [
                    'limit' => 5,
                ],
            ],
            'trending_tags' => [
                'label' => 'Trend Etiketler',
                'description' => 'Hareketli kategorileri/etiketleri trafik skoruna gore listeler.',
                'icon' => 'bi-hash',
                'default_area' => 'right',
                'default_title' => 'Trend Etiketler',
                'settings' => [
                    'limit' => 12,
                ],
            ],
            'announcement_band' => [
                'label' => 'Duyuru Bandi',
                'description' => 'Bakim, kampanya veya etkinlik icin vurgulu duyuru kutusu.',
                'icon' => 'bi-broadcast',
                'default_area' => 'right',
                'default_title' => 'Duyuru',
                'settings' => [
                    'message' => 'Yeni etkinlikler ve guncellemeler yayinda.',
                    'button_label' => 'Detaylari gor',
                    'button_url' => '#',
                    'tone' => 'primary',
                ],
            ],
            'community_activity' => [
                'label' => 'Topluluk Aktivitesi',
                'description' => 'Bugunku konu, yorum ve indirme hareketlerini ozetler.',
                'icon' => 'bi-activity',
                'default_area' => 'right',
                'default_title' => 'Topluluk Aktivitesi',
                'settings' => [],
            ],
            'user_action' => [
                'label' => 'Kullanici Aksiyonu',
                'description' => 'Misafire kayit/giris, uyeye profil ve mod yukleme aksiyonu.',
                'icon' => 'bi-person-plus',
                'default_area' => 'left',
                'default_title' => 'Hemen Basla',
                'settings' => [
                    'guest_title' => 'Topluluga katil',
                    'guest_text' => 'Mod indir, yorum yap ve favorilerini takip et.',
                    'member_title' => 'Hos geldin',
                    'member_text' => 'Profilini yonet veya yeni mod yukle.',
                ],
            ],
            'related_content' => [
                'label' => 'Ilgili Icerikler',
                'description' => 'Konu detayinda ayni kategoriden benzer icerikler.',
                'icon' => 'bi-diagram-3',
                'default_area' => 'right',
                'default_title' => 'Benzer Icerikler',
                'settings' => [
                    'limit' => 4,
                ],
            ],
            'sponsored_content' => [
                'label' => 'Sponsorlu Icerik',
                'description' => 'Sponsor etiketi, gorsel, metin ve takip linki olan kutu.',
                'icon' => 'bi-badge-ad',
                'default_area' => 'right',
                'default_title' => 'Sponsor',
                'settings' => [
                    'sponsor_label' => 'Sponsorlu',
                    'headline' => 'Sponsorlu alan',
                    'description' => 'Reklam veya is ortakligi mesajinizi burada yayinlayin.',
                    'image_url' => '',
                    'target_url' => '#',
                    'button_label' => 'Incele',
                ],
            ],
            'poll_cta' => [
                'label' => 'Anket / CTA',
                'description' => 'Kisa anket, duyuru veya aksiyon kutusu.',
                'icon' => 'bi-megaphone',
                'default_area' => 'right',
                'default_title' => 'Anket',
                'settings' => [
                    'question' => 'Siteyi nasil buluyorsunuz?',
                    'options' => "Cok iyi\nGelisebilir\nDaha sade olabilir",
                    'button_label' => 'Oy ver',
                    'button_url' => '#',
                ],
            ],
            'leaderboard' => [
                'label' => 'Liderlik Tablosu',
                'description' => 'Top kullanicilar ve kisa performans ozeti.',
                'icon' => 'bi-trophy',
                'default_area' => 'right',
                'default_title' => 'Liderlik',
                'settings' => [
                    'limit' => 5,
                ],
            ],
            'site_stats' => [
                'label' => 'Site Istatistikleri',
                'description' => 'Icerik, kategori, indirme ve yorum sayilari.',
                'icon' => 'bi-bar-chart',
                'default_area' => 'right',
                'default_title' => 'Site Istatistikleri',
                'settings' => [],
            ],
            'custom_html' => [
                'label' => 'HTML / Reklam',
                'description' => 'Guvenli HTML, reklam, duyuru veya ozel kutu.',
                'icon' => 'bi-code-square',
                'default_area' => 'right',
                'default_title' => 'Ozel Alan',
                'settings' => [
                    'html' => '<p>Ozel duyuru metni.</p>',
                ],
            ],
        ];
    }
}

if (!function_exists('sidebarBuilderDefaultConfig')) {
    /**
     * @return array<string, mixed>
     */
    function sidebarBuilderDefaultConfig(): array
    {
        return [
            'version' => 1,
            'global' => [
                'enabled' => true,
                'left_width' => 260,
                'right_width' => 300,
                'sticky' => true,
                'mobile_behavior' => 'offcanvas',
                'desktop_layout' => 'both',
            ],
            'areas' => [
                'left' => [
                    [
                        'id' => 'left_navigation',
                        'type' => 'navigation_menu',
                        'title' => 'Menu',
                        'enabled' => true,
                        'pages' => ['all'],
                        'devices' => ['desktop', 'tablet', 'mobile'],
                        'settings' => [],
                    ],
                    [
                        'id' => 'left_categories',
                        'type' => 'category_tree',
                        'title' => 'Kategoriler',
                        'enabled' => true,
                        'pages' => ['home', 'category', 'topic'],
                        'devices' => ['desktop', 'tablet', 'mobile'],
                        'settings' => ['limit' => 16, 'accordion' => true, 'show_counts' => true],
                    ],
                ],
                'right' => [
                    [
                        'id' => 'right_comments',
                        'type' => 'recent_comments',
                        'title' => 'Son Yorumlar',
                        'enabled' => true,
                        'pages' => ['home', 'topic'],
                        'devices' => ['desktop', 'tablet'],
                        'settings' => ['limit' => 3],
                    ],
                    [
                        'id' => 'right_popular',
                        'type' => 'popular_topics',
                        'title' => 'Populer',
                        'enabled' => true,
                        'pages' => ['all'],
                        'devices' => ['desktop', 'tablet'],
                        'settings' => ['limit' => 5, 'sort' => 'downloads'],
                    ],
                    [
                        'id' => 'right_tags',
                        'type' => 'tag_cloud',
                        'title' => 'Etiketler',
                        'enabled' => true,
                        'pages' => ['home', 'category', 'search'],
                        'devices' => ['desktop', 'tablet'],
                        'settings' => ['limit' => 12],
                    ],
                    [
                        'id' => 'right_poll',
                        'type' => 'poll_cta',
                        'title' => 'Anket',
                        'enabled' => true,
                        'pages' => ['home', 'category'],
                        'devices' => ['desktop'],
                        'settings' => [
                            'question' => 'Siteyi nasil buluyorsunuz?',
                            'options' => "Cok iyi\nGelisebilir\nDaha sade olabilir",
                            'button_label' => 'Oy ver',
                            'button_url' => '#',
                        ],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('sidebarBuilderBool')) {
    function sidebarBuilderBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true)
                ? true
                : (in_array($value, ['0', 'false', 'no', 'off'], true) ? false : $default);
        }
        return $default;
    }
}

if (!function_exists('sidebarBuilderNormalizeConfig')) {
    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    function sidebarBuilderNormalizeConfig(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw) || $raw === []) {
            $raw = sidebarBuilderDefaultConfig();
        }

        $default = sidebarBuilderDefaultConfig();
        $catalog = sidebarBuilderWidgetCatalog();
        $global = is_array($raw['global'] ?? null) ? $raw['global'] : [];
        $config = [
            'version' => 1,
            'global' => [
                'enabled' => sidebarBuilderBool($global['enabled'] ?? null, (bool) $default['global']['enabled']),
                'left_width' => min(420, max(180, (int) ($global['left_width'] ?? $default['global']['left_width']))),
                'right_width' => min(460, max(200, (int) ($global['right_width'] ?? $default['global']['right_width']))),
                'sticky' => sidebarBuilderBool($global['sticky'] ?? null, (bool) $default['global']['sticky']),
                'mobile_behavior' => in_array((string) ($global['mobile_behavior'] ?? ''), ['offcanvas', 'stack', 'hide'], true)
                    ? (string) $global['mobile_behavior']
                    : (string) $default['global']['mobile_behavior'],
                'desktop_layout' => in_array((string) ($global['desktop_layout'] ?? ''), ['both', 'left', 'right'], true)
                    ? (string) $global['desktop_layout']
                    : (string) $default['global']['desktop_layout'],
            ],
            'areas' => [
                'left' => [],
                'right' => [],
            ],
        ];

        $areas = is_array($raw['areas'] ?? null) ? $raw['areas'] : [];
        foreach (['left', 'right'] as $area) {
            $widgets = $areas[$area] ?? ($default['areas'][$area] ?? []);
            if (!is_array($widgets)) {
                $widgets = [];
            }
            $index = 0;
            foreach ($widgets as $widget) {
                if (!is_array($widget)) {
                    continue;
                }
                $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($widget['type'] ?? '')));
                if ($type === '' || !isset($catalog[$type])) {
                    continue;
                }
                $definition = $catalog[$type];
                $settings = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];
                $settings = array_replace((array) ($definition['settings'] ?? []), $settings);
                $pages = $widget['pages'] ?? ['all'];
                $devices = $widget['devices'] ?? ['desktop', 'tablet', 'mobile'];
                $config['areas'][$area][] = [
                    'id' => sidebarBuilderWidgetId((string) ($widget['id'] ?? ''), $area, $type, $index),
                    'type' => $type,
                    'title' => trim((string) ($widget['title'] ?? '')) !== '' ? trim((string) $widget['title']) : (string) $definition['default_title'],
                    'enabled' => sidebarBuilderBool($widget['enabled'] ?? null, true),
                    'pages' => sidebarBuilderStringList($pages, ['all', 'home', 'topic', 'category', 'search']),
                    'devices' => sidebarBuilderStringList($devices, ['desktop', 'tablet', 'mobile']),
                    'settings' => sidebarBuilderNormalizeSettings($type, $settings),
                ];
                $index++;
            }
        }

        return $config;
    }
}

if (!function_exists('sidebarBuilderWidgetId')) {
    function sidebarBuilderWidgetId(string $id, string $area, string $type, int $index): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        if ($id !== '') {
            return substr($id, 0, 64);
        }
        return substr($area . '_' . $type . '_' . ($index + 1), 0, 64);
    }
}

if (!function_exists('sidebarBuilderStringList')) {
    /**
     * @param mixed $values
     * @param array<int, string> $allowed
     * @return array<int, string>
     */
    function sidebarBuilderStringList(mixed $values, array $allowed): array
    {
        if (is_string($values)) {
            $values = preg_split('/[\s,]+/', $values) ?: [];
        }
        if (!is_array($values)) {
            $values = [];
        }
        $out = [];
        foreach ($values as $value) {
            $value = strtolower(trim((string) $value));
            if (in_array($value, $allowed, true)) {
                $out[] = $value;
            }
        }
        $out = array_values(array_unique($out));
        return $out !== [] ? $out : [$allowed[0]];
    }
}

if (!function_exists('sidebarBuilderNormalizeSettings')) {
    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    function sidebarBuilderNormalizeSettings(string $type, array $settings): array
    {
        foreach (['limit'] as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = min(30, max(1, (int) $settings[$key]));
            }
        }
        foreach (['accordion', 'show_counts', 'hide_title'] as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = sidebarBuilderBool($settings[$key], true);
            }
        }
        if (isset($settings['style_variant']) && !in_array((string) $settings['style_variant'], ['card', 'minimal', 'list', 'highlight', 'compact'], true)) {
            $settings['style_variant'] = 'card';
        }
        if (!isset($settings['style_variant'])) {
            $settings['style_variant'] = 'card';
        }
        if (isset($settings['custom_icon'])) {
            $settings['custom_icon'] = preg_replace('/[^a-zA-Z0-9_\s-]/', '', trim((string) $settings['custom_icon']));
            if (!str_starts_with((string) $settings['custom_icon'], 'bi-')) {
                $settings['custom_icon'] = '';
            }
        }
        if (isset($settings['accent_color'])) {
            $color = trim((string) $settings['accent_color']);
            $settings['accent_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtoupper($color) : '';
        }
        if (isset($settings['tone']) && !in_array((string) $settings['tone'], ['primary', 'success', 'warning', 'danger', 'info'], true)) {
            $settings['tone'] = 'primary';
        }
        if (isset($settings['sort']) && !in_array((string) $settings['sort'], ['downloads', 'views', 'date'], true)) {
            $settings['sort'] = 'downloads';
        }
        foreach (['html', 'question', 'options', 'button_label', 'button_url', 'topic_ids', 'category_slugs', 'message', 'guest_title', 'guest_text', 'member_title', 'member_text', 'sponsor_label', 'headline', 'description', 'image_url', 'target_url'] as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = trim((string) $settings[$key]);
            }
        }
        return $settings;
    }
}

if (!function_exists('sidebarBuilderConfigFromSettings')) {
    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    function sidebarBuilderConfigFromSettings(array $settings): array
    {
        return sidebarBuilderNormalizeConfig((string) ($settings['sidebar_builder_config'] ?? ''));
    }
}

if (!function_exists('sidebarBuilderSanitizeConfigJson')) {
    function sidebarBuilderSanitizeConfigJson(string $json): string
    {
        return (string) json_encode(sidebarBuilderNormalizeConfig($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('sidebarBuilderPageMatches')) {
    /**
     * @param array<int, string> $pages
     */
    function sidebarBuilderPageMatches(array $pages, string $pageKey): bool
    {
        if (in_array('all', $pages, true)) {
            return true;
        }
        return in_array($pageKey, $pages, true);
    }
}

if (!function_exists('sidebarBuilderDeviceClass')) {
    /**
     * @param array<int, string> $devices
     */
    function sidebarBuilderDeviceClass(array $devices): string
    {
        $classes = [];
        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            if (!in_array($device, $devices, true)) {
                $classes[] = 'ui-theme-sidebar-hide-' . $device;
            }
        }
        return implode(' ', $classes);
    }
}

if (!function_exists('sidebarBuilderAreaTemplateVars')) {
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $config
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    function sidebarBuilderAreaTemplateVars(?PDO $pdo, array $settings, array $config, string $area, string $pageKey, array $source): array
    {
        $global = is_array($config['global'] ?? null) ? $config['global'] : [];
        $areas = is_array($config['areas'] ?? null) ? $config['areas'] : [];
        $widgets = is_array($areas[$area] ?? null) ? $areas[$area] : [];
        $rendered = [];
        foreach ($widgets as $widget) {
            if (!is_array($widget) || !sidebarBuilderBool($widget['enabled'] ?? null, true)) {
                continue;
            }
            $pages = is_array($widget['pages'] ?? null) ? $widget['pages'] : ['all'];
            if (!sidebarBuilderPageMatches($pages, $pageKey)) {
                continue;
            }
            $item = sidebarBuilderWidgetTemplateVars($pdo, $settings, $widget, $source, $area);
            if ($item !== null) {
                $rendered[] = $item;
            }
        }

        return [
            'sidebar_area' => $area,
            'sidebar_width' => (string) (int) ($global[$area . '_width'] ?? ($area === 'left' ? 260 : 300)),
            'sidebar_is_sticky' => !empty($global['sticky']),
            'sidebar_mobile_behavior' => (string) ($global['mobile_behavior'] ?? 'offcanvas'),
            'sidebar_global_enabled' => !empty($global['enabled']),
            'sidebar_widgets' => $rendered,
            'has_sidebar_widgets' => $rendered !== [],
        ];
    }
}

if (!function_exists('sidebarBuilderWidgetTemplateVars')) {
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $widget
     * @param array<string, mixed> $source
     * @return array<string, mixed>|null
     */
    function sidebarBuilderWidgetTemplateVars(?PDO $pdo, array $settings, array $widget, array $source, string $area): ?array
    {
        $type = (string) ($widget['type'] ?? '');
        $catalog = sidebarBuilderWidgetCatalog();
        if (!isset($catalog[$type])) {
            return null;
        }
        $widgetSettings = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];
        $title = trim((string) ($widget['title'] ?? '')) !== ''
            ? trim((string) $widget['title'])
            : (string) ($catalog[$type]['default_title'] ?? $catalog[$type]['label']);
        $devices = is_array($widget['devices'] ?? null) ? $widget['devices'] : ['desktop', 'tablet', 'mobile'];
        $styleVariant = in_array((string) ($widgetSettings['style_variant'] ?? 'card'), ['card', 'minimal', 'list', 'highlight', 'compact'], true)
            ? (string) ($widgetSettings['style_variant'] ?? 'card')
            : 'card';
        $customIcon = (string) ($widgetSettings['custom_icon'] ?? '');
        $icon = $customIcon !== '' ? $customIcon : (string) ($catalog[$type]['icon'] ?? 'bi-puzzle');
        $accentColor = function_exists('uiCssColorValue')
            ? uiCssColorValue((string) ($widgetSettings['accent_color'] ?? ''))
            : '';
        $classParts = [
            'ui-theme-sidebar-widget',
            'ui-theme-sidebar-widget-' . str_replace('_', '-', $type),
            'ui-theme-widget-style-' . $styleVariant,
            sidebarBuilderDeviceClass($devices),
        ];
        if (!empty($widgetSettings['hide_title'])) {
            $classParts[] = 'ui-theme-sidebar-title-hidden';
        }
        $colorStyle = $accentColor !== '' ? '--ui-theme-widget-accent:' . $accentColor : '';
        $base = [
            'id' => (string) ($widget['id'] ?? ''),
            'type' => $type,
            'title' => $title,
            'icon' => $icon,
            'style_variant' => $styleVariant,
            'accent_color' => $accentColor,
            'style' => '',
            'color_style' => $colorStyle,
            'show_title' => empty($widgetSettings['hide_title']),
            'class' => trim(implode(' ', array_filter($classParts))),
            'base_url' => (string) ($source['base_url'] ?? ''),
            'html' => '',
            'items' => [],
            'has_items' => false,
            'is_navigation_menu' => $type === 'navigation_menu',
            'is_category_tree' => $type === 'category_tree',
            'is_recent_comments' => $type === 'recent_comments',
            'is_popular_topics' => $type === 'popular_topics',
            'is_tag_cloud' => $type === 'tag_cloud',
            'is_editor_picks' => $type === 'editor_picks',
            'is_category_showcase' => $type === 'category_showcase',
            'is_latest_downloads' => $type === 'latest_downloads',
            'is_trending_tags' => $type === 'trending_tags',
            'is_announcement_band' => $type === 'announcement_band',
            'is_community_activity' => $type === 'community_activity',
            'is_user_action' => $type === 'user_action',
            'is_related_content' => $type === 'related_content',
            'is_sponsored_content' => $type === 'sponsored_content',
            'is_poll_cta' => $type === 'poll_cta',
            'is_leaderboard' => $type === 'leaderboard',
            'is_site_stats' => $type === 'site_stats',
            'is_custom_html' => $type === 'custom_html',
        ];

        switch ($type) {
            case 'navigation_menu':
                $base['items'] = sidebarBuilderNavigationItems($settings, (string) ($source['base_url'] ?? ''));
                $base['has_items'] = $base['items'] !== [];
                break;

            case 'category_tree':
                $categoryItems = $source['category_menu_items'] ?? [];
                if (!is_array($categoryItems) || $categoryItems === []) {
                    return null;
                }
                $base['category_items'] = $categoryItems;
                $base['has_category_items'] = true;
                break;

            case 'recent_comments':
                $items = is_array($source['recent_comments'] ?? null) ? $source['recent_comments'] : [];
                $base['items'] = array_slice($items, 0, min(12, max(1, (int) ($widgetSettings['limit'] ?? 3))));
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'popular_topics':
                $items = is_array($source['popular_topics'] ?? null) ? $source['popular_topics'] : [];
                $base['items'] = array_slice($items, 0, min(12, max(1, (int) ($widgetSettings['limit'] ?? 5))));
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'tag_cloud':
                $items = is_array($source['tag_cloud_items'] ?? null) ? $source['tag_cloud_items'] : [];
                $base['items'] = array_slice($items, 0, min(24, max(1, (int) ($widgetSettings['limit'] ?? 12))));
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'editor_picks':
                $base['items'] = sidebarBuilderTopicItems($pdo, $widgetSettings, (string) ($source['base_url'] ?? ''), 'editor_picks');
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'category_showcase':
                $base['items'] = sidebarBuilderCategoryShowcaseItems($pdo, $widgetSettings, $source);
                $base['has_items'] = $base['items'] !== [];
                $base['show_counts'] = !empty($widgetSettings['show_counts']);
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'latest_downloads':
                $base['items'] = sidebarBuilderTopicItems($pdo, $widgetSettings, (string) ($source['base_url'] ?? ''), 'latest_downloads');
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'trending_tags':
                $base['items'] = sidebarBuilderTrendingTagItems($pdo, $widgetSettings, $source);
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'announcement_band':
                $message = trim((string) ($widgetSettings['message'] ?? ''));
                if ($message === '') {
                    return null;
                }
                $base['message'] = $message;
                $base['button_label'] = trim((string) ($widgetSettings['button_label'] ?? 'Detaylari gor'));
                $base['button_url'] = sidebarBuilderSafeUrl((string) ($widgetSettings['button_url'] ?? '#'), (string) ($source['base_url'] ?? ''));
                $base['tone'] = (string) ($widgetSettings['tone'] ?? 'primary');
                break;

            case 'community_activity':
                $base['items'] = sidebarBuilderCommunityActivityItems($pdo);
                $base['has_items'] = $base['items'] !== [];
                break;

            case 'user_action':
                $loggedIn = !empty($source['logged_in']);
                $base['logged_in'] = $loggedIn;
                $base['action_title'] = $loggedIn
                    ? str_replace('{name}', (string) ($source['user_name'] ?? 'Uye'), (string) ($widgetSettings['member_title'] ?? 'Hos geldin'))
                    : (string) ($widgetSettings['guest_title'] ?? 'Topluluga katil');
                $base['action_text'] = $loggedIn
                    ? (string) ($widgetSettings['member_text'] ?? 'Profilini yonet veya yeni mod yukle.')
                    : (string) ($widgetSettings['guest_text'] ?? 'Mod indir, yorum yap ve favorilerini takip et.');
                $base['primary_label'] = $loggedIn ? 'Mod Yukle' : 'Kayit Ol';
                $base['primary_url'] = $loggedIn
                    ? routePublicStaticUrl('upload_topic')
                    : routePublicStaticUrl('register');
                $base['secondary_label'] = $loggedIn ? 'Profilim' : 'Giris Yap';
                $base['secondary_url'] = $loggedIn
                    ? rtrim((string) ($source['base_url'] ?? ''), '/') . '/profile.php'
                    : routePublicStaticUrl('login');
                break;

            case 'related_content':
                $base['items'] = sidebarBuilderRelatedContentItems($pdo, $widgetSettings, $source);
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'sponsored_content':
                $headline = trim((string) ($widgetSettings['headline'] ?? ''));
                $description = trim((string) ($widgetSettings['description'] ?? ''));
                if ($headline === '' && $description === '') {
                    return null;
                }
                $base['sponsor_label'] = trim((string) ($widgetSettings['sponsor_label'] ?? 'Sponsorlu'));
                $base['headline'] = $headline !== '' ? $headline : $title;
                $base['description'] = $description;
                $base['image_url'] = sidebarBuilderSafeUrl((string) ($widgetSettings['image_url'] ?? ''), (string) ($source['base_url'] ?? ''));
                $base['target_url'] = sidebarBuilderSafeUrl((string) ($widgetSettings['target_url'] ?? '#'), (string) ($source['base_url'] ?? ''));
                $base['button_label'] = trim((string) ($widgetSettings['button_label'] ?? 'Incele'));
                $base['has_image'] = $base['image_url'] !== '';
                break;

            case 'poll_cta':
                $base['question'] = (string) ($widgetSettings['question'] ?? 'Siteyi nasil buluyorsunuz?');
                $base['button_label'] = (string) ($widgetSettings['button_label'] ?? 'Oy ver');
                $base['button_url'] = (string) ($widgetSettings['button_url'] ?? '#');
                $options = preg_split('/\R+/', (string) ($widgetSettings['options'] ?? '')) ?: [];
                $base['options'] = array_map(static fn (string $option): array => ['label' => trim($option)], array_values(array_filter($options, static fn (string $option): bool => trim($option) !== '')));
                $base['has_options'] = $base['options'] !== [];
                break;

            case 'leaderboard':
                $base['items'] = sidebarBuilderLeaderboardItems($pdo, min(12, max(1, (int) ($widgetSettings['limit'] ?? 5))));
                $base['has_items'] = $base['items'] !== [];
                if (!$base['has_items']) {
                    return null;
                }
                break;

            case 'site_stats':
                $base['items'] = sidebarBuilderStatsItems($pdo);
                $base['has_items'] = $base['items'] !== [];
                break;

            case 'custom_html':
                $html = sidebarBuilderSanitizeHtml((string) ($widgetSettings['html'] ?? ''));
                if (trim($html) === '') {
                    return null;
                }
                $base['html'] = $html;
                break;
        }

        if (is_array($base['items'] ?? null)) {
            if (in_array($type, ['community_activity', 'site_stats'], true)) {
                $base['items'] = array_values(array_map(static function ($row): array {
                    $item = is_array($row) ? $row : [];
                    return [
                        'icon' => (string) ($item['icon'] ?? 'bi-dot'),
                        'value' => (string) ($item['value'] ?? '0'),
                        'label' => (string) ($item['label'] ?? ''),
                    ] + $item;
                }, $base['items']));
            } elseif (in_array($type, ['tag_cloud', 'trending_tags', 'navigation_menu'], true)) {
                $base['items'] = array_values(array_map(static function ($row): array {
                    $item = is_array($row) ? $row : [];
                    return [
                        'label' => (string) ($item['label'] ?? ($item['name'] ?? '')),
                        'url' => (string) ($item['url'] ?? '#'),
                        'icon' => (string) ($item['icon'] ?? ''),
                        'count' => (string) ($item['count'] ?? ''),
                    ] + $item;
                }, $base['items']));
            } elseif ($type === 'category_showcase') {
                $base['items'] = array_values(array_map(static function ($row): array {
                    $item = is_array($row) ? $row : [];
                    return [
                        'icon' => (string) ($item['icon'] ?? 'bi-folder2-open'),
                        'label' => (string) ($item['label'] ?? ($item['name'] ?? '')),
                        'count' => (string) ($item['count'] ?? ''),
                        'url' => (string) ($item['url'] ?? '#'),
                    ] + $item;
                }, $base['items']));
            }

            // The template renderer resolves loops before conditionals in debug mode.
            // Keep a complete minimal key-set on every sidebar item so inactive
            // widget blocks do not emit noisy "TPL Missing Variable: item.*" logs.
            $base['items'] = array_values(array_map(static function ($row): array {
                $item = is_array($row) ? $row : ['value' => $row];
                $title = (string) ($item['title'] ?? ($item['name'] ?? ($item['label'] ?? '')));
                $label = (string) ($item['label'] ?? ($item['name'] ?? $title));
                $value = (string) ($item['value'] ?? ($item['score'] ?? ($item['count'] ?? ($item['meta'] ?? ''))));
                $name = (string) ($item['name'] ?? ($item['label'] ?? $title));

                return [
                    'url' => (string) ($item['url'] ?? '#'),
                    'icon' => (string) ($item['icon'] ?? ''),
                    'label' => $label,
                    'value' => $value,
                    'count' => (string) ($item['count'] ?? ''),
                    'rank' => (string) ($item['rank'] ?? ''),
                    'name' => $name,
                    'score' => (string) ($item['score'] ?? $value),
                    'title' => $title,
                    'meta' => (string) ($item['meta'] ?? $value),
                    'image' => (string) ($item['image'] ?? ''),
                    'avatar' => (string) ($item['avatar'] ?? ''),
                    'author' => (string) ($item['author'] ?? $name),
                    'excerpt' => (string) ($item['excerpt'] ?? ''),
                    'date' => (string) ($item['date'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                ] + $item;
            }, $base['items']));
        }

        if ($type === 'poll_cta' && is_array($base['options'] ?? null)) {
            $base['options'] = array_values(array_map(static function ($row): array {
                $item = is_array($row) ? $row : [];
                return [
                    'label' => (string) ($item['label'] ?? ''),
                ] + $item;
            }, $base['options']));
        }

        return $base;
    }
}

if (!function_exists('sidebarBuilderSafeUrl')) {
    function sidebarBuilderSafeUrl(string $url, string $baseUrl = ''): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('~^(https?:)?//|^#|^mailto:~i', $url)) {
            return $url;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
}

if (!function_exists('sidebarBuilderListValues')) {
    /**
     * @return array<int, string>
     */
    function sidebarBuilderListValues(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('sidebarBuilderTopicItemVars')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    function sidebarBuilderTopicItemVars(array $row, string $baseUrl = '', string $metaMode = 'downloads'): array
    {
        $image = (string) ($row['image_path'] ?? $row['primary_media_path'] ?? '');
        if ($image !== '' && !preg_match('~^(https?:)?//|^data:|^/~i', $image)) {
            $image = rtrim($baseUrl, '/') . '/' . ltrim($image, '/');
        }
        $date = function_exists('formatAppDate')
            ? formatAppDate((string) ($row['published_at'] ?? $row['created_at'] ?? 'now'), $GLOBALS['pdo'] ?? null)
            : (string) ($row['created_at'] ?? '');
        $lastDownloadDate = trim((string) ($row['last_downloaded_at'] ?? '')) !== ''
            ? (function_exists('formatAppDate') ? formatAppDate((string) $row['last_downloaded_at'], $GLOBALS['pdo'] ?? null) : (string) $row['last_downloaded_at'])
            : '';
        $downloads = number_format((int) ($row['download_count'] ?? 0), 0, ',', '.');
        $views = number_format((int) ($row['view_count'] ?? 0), 0, ',', '.');
        $meta = match ($metaMode) {
            'views' => $views . ' goruntulenme',
            'date' => $date,
            'latest_downloads' => $lastDownloadDate !== '' ? 'Son indirme ' . $lastDownloadDate : $downloads . ' indirme',
            default => $downloads . ' indirme',
        };
        return [
            'title' => (string) ($row['title'] ?? 'Konu'),
            'url' => topicUrlForRow($row),
            'image' => $image,
            'meta' => $meta,
            'category' => (string) ($row['category'] ?? 'Genel'),
            'badge' => (string) ($row['category'] ?? ''),
        ];
    }
}

if (!function_exists('sidebarBuilderTopicItems')) {
    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderTopicItems(?PDO $pdo, array $settings, string $baseUrl, string $mode): array
    {
        if (!$pdo instanceof PDO) {
            return [];
        }
        $limit = min(12, max(1, (int) ($settings['limit'] ?? 5)));
        $rows = [];
        if ($mode === 'editor_picks') {
            $ids = array_values(array_filter(array_map('intval', sidebarBuilderListValues((string) ($settings['topic_ids'] ?? '')))));
            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    $stmt = $pdo->prepare(
                        "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name AS category, pm.path AS image_path
                         FROM topics t
                         LEFT JOIN categories cat ON cat.id = t.category_id
                         LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                         WHERE t.id IN ({$placeholders}) AND t.status = 'published' AND t.deleted_at IS NULL"
                    );
                    $stmt->execute($ids);
                    $found = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $order = array_flip($ids);
                    usort($found, static fn (array $a, array $b): int => ($order[(int) ($a['id'] ?? 0)] ?? 9999) <=> ($order[(int) ($b['id'] ?? 0)] ?? 9999));
                    $rows = array_slice($found, 0, $limit);
                } catch (Throwable) {
                    $rows = [];
                }
            }
            if ($rows === []) {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name AS category, pm.path AS image_path
                         FROM topics t
                         LEFT JOIN categories cat ON cat.id = t.category_id
                         LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                         WHERE t.status = 'published' AND t.deleted_at IS NULL
                         ORDER BY t.download_count DESC, t.view_count DESC, COALESCE(t.published_at, t.created_at) DESC
                         LIMIT :lim"
                    );
                    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable) {
                    $rows = [];
                }
            }
            return array_map(static fn (array $row): array => sidebarBuilderTopicItemVars($row, $baseUrl, 'downloads'), $rows);
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, MAX(d.created_at) AS last_downloaded_at, cat.name AS category, pm.path AS image_path
                 FROM downloads d
                 INNER JOIN topics t ON t.id = d.topic_id
                 LEFT JOIN categories cat ON cat.id = t.category_id
                 LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                 WHERE t.status = 'published' AND t.deleted_at IS NULL
                 GROUP BY t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name, pm.path
                 ORDER BY last_downloaded_at DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name AS category, pm.path AS image_path
                     FROM topics t
                     LEFT JOIN categories cat ON cat.id = t.category_id
                     LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                     WHERE t.status = 'published' AND t.deleted_at IS NULL
                     ORDER BY COALESCE(t.updated_at, t.published_at, t.created_at) DESC, t.id DESC
                     LIMIT :lim"
                );
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $rows = [];
            }
        }

        return array_map(static fn (array $row): array => sidebarBuilderTopicItemVars($row, $baseUrl, 'latest_downloads'), $rows);
    }
}

if (!function_exists('sidebarBuilderCategoryShowcaseItems')) {
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $source
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderCategoryShowcaseItems(?PDO $pdo, array $settings, array $source): array
    {
        $limit = min(12, max(1, (int) ($settings['limit'] ?? 6)));
        $slugs = sidebarBuilderListValues((string) ($settings['category_slugs'] ?? ''));
        $rows = [];
        if ($pdo instanceof PDO) {
            try {
                if ($slugs !== []) {
                    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
                    $stmt = $pdo->prepare(
                        "SELECT cat.id, cat.name, cat.slug, parent.slug AS parent_slug, COUNT(t.id) AS topic_count
                         FROM categories cat
                         LEFT JOIN categories parent ON parent.id = cat.parent_id
                         LEFT JOIN topics t ON t.category_id = cat.id AND t.status = 'published' AND t.deleted_at IS NULL
                         WHERE cat.slug IN ({$placeholders}) AND cat.deleted_at IS NULL
                         GROUP BY cat.id, cat.name, cat.slug, parent.slug"
                    );
                    $stmt->execute($slugs);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $order = array_flip($slugs);
                    usort($rows, static fn (array $a, array $b): int => ($order[(string) ($a['slug'] ?? '')] ?? 9999) <=> ($order[(string) ($b['slug'] ?? '')] ?? 9999));
                } else {
                    $stmt = $pdo->prepare(
                        "SELECT cat.id, cat.name, cat.slug, parent.slug AS parent_slug, COUNT(t.id) AS topic_count
                         FROM categories cat
                         LEFT JOIN categories parent ON parent.id = cat.parent_id
                         LEFT JOIN topics t ON t.category_id = cat.id AND t.status = 'published' AND t.deleted_at IS NULL
                         WHERE cat.deleted_at IS NULL
                         GROUP BY cat.id, cat.name, cat.slug, parent.slug
                         ORDER BY topic_count DESC, cat.display_order ASC, cat.name ASC
                         LIMIT :lim"
                    );
                    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (Throwable) {
                $rows = [];
            }
        }
        if ($rows === []) {
            $rows = array_slice(is_array($source['category_menu_items'] ?? null) ? $source['category_menu_items'] : [], 0, $limit);
        }
        $items = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            $parentSlug = (string) ($row['parent_slug'] ?? '');
            $items[] = [
                'label' => (string) ($row['name'] ?? $row['label'] ?? 'Kategori'),
                'url' => $slug !== '' ? categoryUrl($slug, $parentSlug) : (string) ($row['url'] ?? '#'),
                'count' => number_format((int) ($row['topic_count'] ?? $row['count'] ?? 0), 0, ',', '.'),
                'icon' => 'bi-folder2-open',
            ];
        }
        return $items;
    }
}

if (!function_exists('sidebarBuilderTrendingTagItems')) {
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $source
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderTrendingTagItems(?PDO $pdo, array $settings, array $source): array
    {
        $limit = min(24, max(1, (int) ($settings['limit'] ?? 12)));
        $items = [];
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT cat.name, cat.slug, parent.slug AS parent_slug, COUNT(t.id) AS topic_count, COALESCE(SUM(t.download_count + t.view_count), 0) AS trend_score
                     FROM categories cat
                     LEFT JOIN categories parent ON parent.id = cat.parent_id
                     LEFT JOIN topics t ON t.category_id = cat.id AND t.status = 'published' AND t.deleted_at IS NULL
                     WHERE cat.deleted_at IS NULL
                     GROUP BY cat.id, cat.name, cat.slug, parent.slug
                     ORDER BY trend_score DESC, topic_count DESC, cat.name ASC
                     LIMIT :lim"
                );
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $slug = (string) ($row['slug'] ?? '');
                    $items[] = [
                        'label' => (string) ($row['name'] ?? 'Etiket'),
                        'url' => $slug !== '' ? categoryUrl($slug, (string) ($row['parent_slug'] ?? '')) : '#',
                        'count' => number_format((int) ($row['topic_count'] ?? 0), 0, ',', '.'),
                    ];
                }
            } catch (Throwable) {
                $items = [];
            }
        }
        if ($items === []) {
            $items = array_slice(is_array($source['tag_cloud_items'] ?? null) ? $source['tag_cloud_items'] : [], 0, $limit);
        }
        return $items;
    }
}

if (!function_exists('sidebarBuilderCommunityActivityItems')) {
    /**
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderCommunityActivityItems(?PDO $pdo): array
    {
        $stats = ['topics' => 0, 'comments' => 0, 'downloads' => 0, 'members' => 0];
        if ($pdo instanceof PDO) {
            try {
                $stats['topics'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status='published' AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
                $stats['comments'] = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE status='approved' AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
                $stats['downloads'] = (int) $pdo->query("SELECT COUNT(*) FROM downloads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
                $stats['members'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            } catch (Throwable $e) {
                error_log('[sidebar] community activity query failed: ' . $e->getMessage());
            }
        }
        return [
            ['icon' => 'bi-file-earmark-plus', 'value' => number_format($stats['topics'], 0, ',', '.'), 'label' => 'Bugun konu'],
            ['icon' => 'bi-chat-dots', 'value' => number_format($stats['comments'], 0, ',', '.'), 'label' => 'Bugun yorum'],
            ['icon' => 'bi-download', 'value' => number_format($stats['downloads'], 0, ',', '.'), 'label' => 'Bugun indirme'],
            ['icon' => 'bi-people', 'value' => number_format($stats['members'], 0, ',', '.'), 'label' => 'Yeni uye'],
        ];
    }
}

if (!function_exists('sidebarBuilderRelatedContentItems')) {
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $source
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderRelatedContentItems(?PDO $pdo, array $settings, array $source): array
    {
        if (!$pdo instanceof PDO) {
            return [];
        }
        $limit = min(12, max(1, (int) ($settings['limit'] ?? 4)));
        $topic = is_array($source['current_topic'] ?? null) ? $source['current_topic'] : [];
        $topicId = (int) ($topic['id'] ?? 0);
        $categoryId = (int) ($topic['category_id'] ?? 0);
        try {
            if ($topicId > 0 && $categoryId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name AS category, pm.path AS image_path
                     FROM topics t
                     LEFT JOIN categories cat ON cat.id = t.category_id
                     LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                     WHERE t.id != :topic_id AND t.category_id = :category_id AND t.status = 'published' AND t.deleted_at IS NULL
                     ORDER BY t.download_count DESC, t.view_count DESC, t.comment_count DESC, t.published_at DESC
                     LIMIT :lim"
                );
                $stmt->bindValue(':topic_id', $topicId, PDO::PARAM_INT);
                $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare(
                    "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, t.published_at, cat.name AS category, pm.path AS image_path
                     FROM topics t
                     LEFT JOIN categories cat ON cat.id = t.category_id
                     LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                     WHERE t.status = 'published' AND t.deleted_at IS NULL
                     ORDER BY t.view_count DESC, t.download_count DESC
                     LIMIT :lim"
                );
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $rows = [];
        }
        return array_map(static fn (array $row): array => sidebarBuilderTopicItemVars($row, (string) ($source['base_url'] ?? ''), 'downloads'), $rows);
    }
}

if (!function_exists('sidebarBuilderNavigationItems')) {
    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderNavigationItems(array $settings, string $baseUrl): array
    {
        $routeUrl = static function (string $routeKey): string {
            return trim((string) routePublicStaticUrl($routeKey));
        };

        $categoryListHref = function_exists('categoryListUrl')
            ? trim((string) categoryListUrl())
            : '';
        if ($categoryListHref === '') {
            $prefix = rtrim($baseUrl, '/');
            $categoryListHref = ($prefix !== '' ? $prefix : '') . '/kategoriler';
        }

        $lower = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            return function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
        };

        $defaultItems = [
            'home' => [
                'line' => 'Anasayfa|/index.php|bi-house',
                'markers' => ['anasayfa', 'ana sayfa', '/index.php', 'index.php'],
            ],
            'categories' => [
                'line' => 'Kategoriler|{category_list}|bi-grid',
                'markers' => ['kategoriler', 'kategori', '{category_list}', '/kategori', '/kategoriler', 'category', 'categories'],
            ],
            'upload_topic' => [
                'line' => 'Mod Yukle|{upload_topic}|bi-cloud-arrow-up',
                'markers' => ['mod yukle', 'mod yükle', 'icerik yukle', 'içerik yükle', '{upload_topic}', '{upload_topic_url}', '/konu-yukle', 'konu-yukle'],
            ],
            'events' => [
                'line' => 'Etkinlikler|{events}|bi-calendar-event',
                'markers' => ['etkinlikler', 'events', '{events}', '/events'],
            ],
            'leaderboard' => [
                'line' => 'Liderlik|{leaderboard}|bi-trophy',
                'markers' => ['liderlik', 'leaderboard', '{leaderboard}', '/leaderboard.php', 'leaderboard.php', '/liderlik'],
            ],
            'contact' => [
                'line' => 'Iletisim|{contact}|bi-envelope-paper',
                'markers' => ['iletisim', 'iletişim', 'contact', '{contact}', '/contact', '/iletisim'],
            ],
        ];

        $raw = trim((string) ($settings['menu_items'] ?? ''));
        $lines = $raw !== '' ? (preg_split('/\R+/', $raw) ?: []) : [];
        if ($lines === []) {
            $lines = array_column($defaultItems, 'line');
        }

        $placeholderMap = [
            '{category_list}' => $categoryListHref,
            '{upload_topic}' => $routeUrl('upload_topic'),
            '{events}' => $routeUrl('events'),
            '{leaderboard}' => $routeUrl('leaderboard'),
            '{contact}' => $routeUrl('contact'),
            '{notifications}' => $routeUrl('notifications'),
        ];

        $categoryPath = (string) parse_url($categoryListHref, PHP_URL_PATH);
        $categoryMarkers = array_filter([
            '{category_list}',
            '/kategori',
            'kategori',
            '/kategoriler',
            'kategoriler',
            '/category',
            'category',
            '/categories',
            'categories',
            $lower($categoryListHref),
            $lower($categoryPath),
            $lower(trim($categoryPath, '/')),
        ], static fn (string $value): bool => $value !== '');
        $categoryMarkers = array_values(array_unique($categoryMarkers));

        $items = [];
        $itemIndex = 1;
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', (string) $line, 3));
            $label = $parts[0] ?? '';
            $rawUrl = $parts[1] ?? '#';
            $icon = trim((string) ($parts[2] ?? ''));

            if ($label === '') {
                continue;
            }

            if ($icon !== '' && preg_match('/^bi-[a-z0-9-]+$/i', $icon) !== 1) {
                $icon = '';
            }

            $urlToken = $lower((string) $rawUrl);
            $isCategoryMenu = in_array($urlToken, $categoryMarkers, true);
            if ($isCategoryMenu) {
                $url = $categoryListHref;
            } elseif (isset($placeholderMap[$urlToken])) {
                $url = $placeholderMap[$urlToken];
            } else {
                $url = trim((string) $rawUrl);
                if ($url === '') {
                    $url = '#';
                } elseif (!preg_match('~^(?:https?:)?//|^#|^mailto:|^tel:~i', $url)) {
                    if (str_starts_with($url, '/')) {
                        $basePrefix = '/' . trim($baseUrl, '/');
                        if ($basePrefix !== '/' && ($url === $basePrefix || str_starts_with($url, $basePrefix . '/'))) {
                            $url = $url;
                        } else {
                            $url = ($basePrefix === '/' ? '' : rtrim($baseUrl, '/')) . '/' . ltrim($url, '/');
                        }
                    } else {
                        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                    }
                }
            }

            $items[] = [
                'label' => $label,
                'url' => $url,
                'icon' => $icon,
                'is_category_menu' => $isCategoryMenu ? '1' : '',
                'dropdown_id' => 'headerMenuDropdown' . $itemIndex,
            ];
            $itemIndex++;
        }

        return $items;
    }
}

if (!function_exists('sidebarBuilderStatsItems')) {
    /**
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderStatsItems(?PDO $pdo): array
    {
        $stats = ['mods' => 0, 'categories' => 0, 'downloads' => 0, 'comments' => 0];
        if ($pdo instanceof PDO) {
            try {
                $stats['mods'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status='published' AND deleted_at IS NULL")->fetchColumn();
                $stats['categories'] = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL")->fetchColumn();
                $stats['downloads'] = (int) $pdo->query("SELECT COALESCE(SUM(download_count),0) FROM topics WHERE deleted_at IS NULL")->fetchColumn();
                $stats['comments'] = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL")->fetchColumn();
            } catch (Throwable $e) {
                error_log('[sidebar] stats items query failed: ' . $e->getMessage());
            }
            return [
                ['icon' => 'bi-cloud-arrow-up', 'value' => number_format($stats['mods'], 0, ',', '.'), 'label' => 'Icerik'],
                ['icon' => 'bi-folder2-open', 'value' => number_format($stats['categories'], 0, ',', '.'), 'label' => 'Kategori'],
                ['icon' => 'bi-download', 'value' => number_format($stats['downloads'], 0, ',', '.'), 'label' => 'Indirme'],
                ['icon' => 'bi-chat-left-text', 'value' => number_format($stats['comments'], 0, ',', '.'), 'label' => 'Yorum'],
            ];
        }

        return [];
    }
}

if (!function_exists('sidebarBuilderLeaderboardItems')) {
    /**
     * @return array<int, array<string, string>>
     */
    function sidebarBuilderLeaderboardItems(?PDO $pdo, int $limit): array
    {
        if (!$pdo instanceof PDO) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("SELECT id, username, total_downloads, total_topics FROM users WHERE status='active' AND deleted_at IS NULL ORDER BY total_downloads DESC, total_topics DESC, id ASC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $items = [];
        foreach ($rows as $index => $row) {
            $name = (string) ($row['username'] ?? 'Uye');
            $items[] = [
                'rank' => (string) ($index + 1),
                'name' => $name,
                'initial' => mb_substr($name, 0, 1),
                'score' => number_format((int) ($row['total_downloads'] ?? 0), 0, ',', '.'),
                'url' => publicProfileUrl(['id' => (int) ($row['id'] ?? 0), 'username' => $name]),
            ];
        }
        return $items;
    }
}

if (!function_exists('sidebarBuilderSanitizeHtml')) {
    function sidebarBuilderSanitizeHtml(string $html): string
    {
        $html = strip_tags($html, '<a><strong><b><em><i><p><br><ul><ol><li><span><div><small><img>');
        $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/iu', '', $html);
        $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/iu', '', $html);
        $html = (string) preg_replace('/javascript\s*:/iu', '', $html);
        return trim($html);
    }
}

if (!function_exists('renderPopularWidget')) {
    function renderPopularWidget(PDO $pdo, array $settings, ?int $excludeId = null): string
    {
        $count = 5;
        $sort = $settings['sidebar_popular_sort'] ?? 'downloads';
        
        $orderBy = match($sort) {
            'views' => 't.view_count DESC',
            'date' => 't.created_at DESC',
            'likes' => 't.download_count DESC',
            default => 't.download_count DESC'
        };
        
        try {
            $sql = "SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.created_at, cat.name AS category
                    FROM topics t
                    LEFT JOIN categories cat ON t.category_id = cat.id
                    WHERE t.status = 'published' AND t.deleted_at IS NULL";
            if ($excludeId) {
                $sql .= " AND t.id != :exclude_id";
            }
            $sql .= " ORDER BY {$orderBy} LIMIT :lim";
            
            $stmt = $pdo->prepare($sql);
            if ($excludeId) {
                $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':lim', $count, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $items = [];
        }

        $sortLabel = match($sort) { 'views' => 'Görüntülenme', 'date' => 'Yeni', default => 'İndirme' };

        return renderSidebarContentWidget($items, 'Popüler İçerikler', 'bi-fire', $sortLabel, 'Henüz popüler içerik yok.');
    }
}

if (!function_exists('renderSidebarCategoryAtlasWidget')) {
    function renderSidebarCategoryAtlasWidget(PDO $pdo, string $activeSlug = '', string $title = 'Kategoriler', bool $showAllLink = true, bool $activateAllWhenEmpty = false): string
    {
        $activeSlug = function_exists('validateSlug')
            ? (validateSlug($activeSlug) ?? '')
            : preg_replace('/[^a-z0-9-]/', '', $activeSlug);
        $tree = function_exists('getPublicCategoriesTree') ? getPublicCategoriesTree($pdo) : [];
        $flatCategories = empty($tree) ? getPublicCategories($pdo) : [];

        if (empty($tree) && empty($flatCategories)) {
            return '';
        }

        $totalCount = 0;
        foreach ($tree as $cat) {
            $totalCount += (int)($cat['topic_count'] ?? 0);
            foreach (($cat['children'] ?? []) as $child) {
                $totalCount += (int)($child['topic_count'] ?? 0);
            }
        }
        foreach ($flatCategories as $cat) {
            $totalCount += (int)($cat['topic_count'] ?? 0);
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $html = '<section class="public-widget topic-side-panel sidebar-category-atlas category-atlas-widget ui-panel">';
        $html .= '<div class="public-widget__header topic-panel-head sidebar-category-head ui-panel__head">';
        $html .= '<span class="sidebar-category-title"><i class="bi bi-folder2-open" aria-hidden="true"></i><strong>' . $safeTitle . '</strong></span>';
        $html .= '<span class="sidebar-category-total">' . number_format($totalCount) . '</span>';
        $html .= '</div>';
        $html .= '<div class="public-widget__body sidebar-category-list category-atlas-list ui-panel__body">';

        if ($showAllLink) {
            $allActiveClass = $activateAllWhenEmpty && $activeSlug === '' ? ' active' : '';
            $allUrl = htmlspecialchars(function_exists('categoryListUrl') ? categoryListUrl() : rtrim($GLOBALS['baseUri'] ?? base_uri(), '/') . '/kategori', ENT_QUOTES, 'UTF-8');
            $html .= "<a class=\"sidebar-category-link sidebar-category-root-row sidebar-category-all{$allActiveClass}\" href=\"{$allUrl}\">";
            $html .= '<span class="sidebar-category-copy"><span class="sidebar-category-icon"><i class="bi bi-grid ui-grid" aria-hidden="true"></i></span><span class="sidebar-category-name">Tüm Kategoriler</span></span>';
            $html .= '<span class="sidebar-category-count">' . number_format($totalCount) . '</span>';
            $html .= '<span class="sidebar-category-chevron-spacer" aria-hidden="true"></span>';
            $html .= '</a>';
        }

        foreach ($tree as $cat) {
            $slug = (string)($cat['slug'] ?? '');
            $name = htmlspecialchars((string)($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $children = $cat['children'] ?? [];
            $hasChildren = !empty($children);
            $isActiveCategory = $activeSlug === $slug;
            $hasActiveChild = false;
            $count = (int)($cat['topic_count'] ?? 0);

            foreach ($children as $child) {
                $childSlug = (string)($child['slug'] ?? '');
                $hasActiveChild = $hasActiveChild || $activeSlug === $childSlug;
                $count += (int)($child['topic_count'] ?? 0);
            }

            $isOpenCategory = $isActiveCategory || $hasActiveChild;
            $itemClass = 'category-item sidebar-category-item';
            $itemClass .= $isActiveCategory ? ' active' : '';
            $itemClass .= $hasActiveChild ? ' has-active-child' : '';
            $itemClass .= $isOpenCategory ? ' open' : '';
            $parentUrl = htmlspecialchars(categoryUrl($slug), ENT_QUOTES, 'UTF-8');
            $parentActiveClass = $isActiveCategory ? ' active' : '';
            $childGroupId = 'sidebar-subcategories-' . (int)($cat['id'] ?? 0);

            if ($hasChildren) {
                $html .= "<div class=\"{$itemClass}\">";
                $html .= '<div class="sidebar-category-parent-row">';
                $html .= '<button class="sidebar-category-link sidebar-category-root-row sidebar-category-parent-link sidebar-category-parent-toggle' . $parentActiveClass . '" type="button" data-ui-theme-atlas-toggle aria-expanded="' . ($isOpenCategory ? 'true' : 'false') . '" aria-controls="' . $childGroupId . '" aria-label="' . $name . ' alt kategorilerini ac veya kapat">';
                $html .= '<span class="sidebar-category-copy"><span class="sidebar-category-icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span><span class="sidebar-category-name">' . $name . '</span></span>';
                $html .= '<span class="sidebar-category-count">' . number_format($count) . '</span>';
                $html .= '<span class="sidebar-category-chevron" aria-hidden="true"><i class="bi bi-arrow-right chevron-icon"></i></span>';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '<div id="' . $childGroupId . '" class="subcategories sidebar-category-children">';

                foreach ($children as $child) {
                    $childSlug = (string)($child['slug'] ?? '');
                    $childName = htmlspecialchars((string)($child['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $childCount = (int)($child['topic_count'] ?? 0);
                    $childUrl = htmlspecialchars(categoryUrl($childSlug, $slug), ENT_QUOTES, 'UTF-8');
                    $childActiveClass = $activeSlug === $childSlug ? ' active' : '';

                    $html .= "<a class=\"sidebar-category-child subcategory-link{$childActiveClass}\" href=\"{$childUrl}\">";
                    $html .= '<span class="subcategory-link-content ui-section"><span class="subcategory-rail" aria-hidden="true"></span><span class="subcategory-name">' . $childName . '</span></span>';
                    $html .= '<span class="subcategory-count">' . number_format($childCount) . '</span>';
                    $html .= '</a>';
                }

                $html .= '</div></div>';
                continue;
            }

            $html .= "<div class=\"{$itemClass}\">";
            $html .= "<a class=\"sidebar-category-link sidebar-category-root-row{$parentActiveClass}\" href=\"{$parentUrl}\">";
            $html .= '<span class="sidebar-category-copy"><span class="sidebar-category-icon"><i class="bi bi-folder2" aria-hidden="true"></i></span><span class="sidebar-category-name">' . $name . '</span></span>';
            $html .= '<span class="sidebar-category-count">' . number_format($count) . '</span>';
            $html .= '<span class="sidebar-category-chevron-spacer" aria-hidden="true"></span>';
            $html .= '</a></div>';
        }

        foreach ($flatCategories as $cat) {
            $slug = (string)($cat['slug'] ?? '');
            $name = htmlspecialchars((string)($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars(categoryUrlForRow($pdo, $cat), ENT_QUOTES, 'UTF-8');
            $count = (int)($cat['topic_count'] ?? 0);
            $activeClass = $activeSlug === $slug ? ' active' : '';

            $html .= "<a class=\"sidebar-category-link sidebar-category-root-row{$activeClass}\" href=\"{$url}\">";
            $html .= '<span class="sidebar-category-copy"><span class="sidebar-category-icon"><i class="bi bi-folder2" aria-hidden="true"></i></span><span class="sidebar-category-name">' . $name . '</span></span>';
            $html .= '<span class="sidebar-category-count">' . number_format($count) . '</span>';
            $html .= '<span class="sidebar-category-chevron-spacer" aria-hidden="true"></span>';
            $html .= '</a>';
        }

        $html .= '</div></section>';
        return $html;
    }
}

if (!function_exists('renderCategoriesWidget')) {
    function renderCategoriesWidget(PDO $pdo, array $settings): string
    {
        return renderSidebarCategoryAtlasWidget($pdo, '', 'Kategoriler', true, false);
    }
}

if (!function_exists('renderStatsWidget')) {
    function renderStatsWidget(PDO $pdo, array $settings): string
    {
        try {
            $stats = [
                'mods' => $pdo->query("SELECT COUNT(*) FROM topics WHERE status='published' AND deleted_at IS NULL")->fetchColumn(),
                'kategoriler' => $pdo->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL")->fetchColumn(),
                'downloads' => $pdo->query("SELECT COALESCE(SUM(download_count),0) FROM topics WHERE deleted_at IS NULL")->fetchColumn(),
                'comments' => $pdo->query("SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL")->fetchColumn(),
            ];
        } catch (Throwable $e) {
            $stats = ['mods' => 0, 'kategoriler' => 0, 'downloads' => 0, 'comments' => 0];
        }
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= '<div class="public-widget__header ui-panel__head"><h2><i class="bi bi-bar-chart"></i> Site İstatistikleri</h2></div>';
        $html .= '<div class="public-widget__body ui-panel__body"><div class="stats-list">';
        $html .= '<div><span>' . number_format($stats['mods']) . '</span><small>Onaylı içerik</small></div>';
        $html .= '<div><span>' . number_format($stats['kategoriler']) . '</span><small>Kategori</small></div>';
        $html .= '<div><span>' . number_format($stats['downloads']) . '</span><small>İndirme</small></div>';
        $html .= '<div><span>' . number_format($stats['comments']) . '</span><small>Yorum</small></div>';
        $html .= '</div></div></section>';
        
        return $html;
    }
}

if (!function_exists('renderCustomWidget')) {
    function renderCustomWidget(string $content): string
    {
        if (empty(trim($content))) {
            return '';
        }
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= $content;
        $html .= '</section>';
        
        return $html;
    }
}

if (!function_exists('renderRelatedTopicsWidget')) {
    function renderRelatedTopicsWidget(PDO $pdo, array $settings, array $data): string
    {
        $topicId = (int)($data['topic_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        
        if (!$topicId || !$categoryId) {
            return '';
        }
        
        try {
            $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.download_count, t.view_count, t.comment_count, t.created_at, cat.name AS category
                    FROM topics t
                    LEFT JOIN categories cat ON t.category_id = cat.id
                    WHERE t.id != :topic_id AND t.category_id = :cat_id AND t.status = 'published' AND t.deleted_at IS NULL
                    ORDER BY t.download_count DESC, t.view_count DESC, t.comment_count DESC, t.created_at DESC
                    LIMIT 5");
            $stmt->execute([':cat_id' => $categoryId, ':topic_id' => $topicId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $items = [];
        }
        
        if (empty($items)) {
            return '';
        }

        return renderSidebarContentWidget($items, 'Benzer İçerikler', 'bi-diagram-3', 'Benzer', 'Henüz benzer içerik yok.', 'sidebar-related-widget');
    }
}

if (!function_exists('renderAuthorWidget')) {
    function renderAuthorWidget(array $author): string
    {
        $name = htmlspecialchars($author['name'] ?? 'Anonim');
        $id = (int)($author['id'] ?? 0);
        
        if (!$id) {
            return '';
        }
        
        $profileUrl = publicProfileUrl(['id' => $id, 'name' => $name]);
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= '<div class="public-widget__header ui-panel__head"><h2><i class="bi bi-person-circle"></i> Yazar</h2></div>';
        $html .= '<div class="public-widget__body ui-panel__body"><div class="sidebar-author-widget">';
        $html .= "<a href=\"{$profileUrl}\" class=\"sidebar-author-link\">";
        $html .= '<i class="bi bi-person-circle sidebar-author-icon"></i>';
        $html .= "<div><div class=\"sidebar-author-name\">{$name}</div>";
        $html .= '<div class="sidebar-author-hint">Profili görüntüle</div></div>';
        $html .= '</a></div></div></section>';
        
        return $html;
    }
}

if (!function_exists('renderCategoryListWidget')) {
    function renderCategoryListWidget(PDO $pdo, array $settings, string $activeSlug = ''): string
    {
        return renderSidebarCategoryAtlasWidget($pdo, $activeSlug, 'Tüm Kategoriler', true, true);
    }
}

if (!function_exists('renderCategoryStatsWidget')) {
    function renderCategoryStatsWidget(PDO $pdo, array $settings, array $data): string
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        
        if (!$categoryId) {
            return '';
        }
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(download_count),0) as downloads, COALESCE(SUM(view_count),0) as views FROM topics WHERE (category_id = :cat_id OR category_id IN (SELECT id FROM categories WHERE parent_id = :parent_cat_id AND deleted_at IS NULL)) AND status = 'published' AND deleted_at IS NULL");
            $stmt->execute([':cat_id' => $categoryId, ':parent_cat_id' => $categoryId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return '';
        }
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= '<div class="public-widget__header ui-panel__head"><h2><i class="bi bi-graph-up"></i> Kategori İstatistikleri</h2></div>';
        $html .= '<div class="public-widget__body ui-panel__body"><div class="stats-list">';
        $html .= '<div><span>' . number_format((float)($stats['total'] ?? 0)) . '</span><small>İçerik</small></div>';
        $html .= '<div><span>' . number_format((float)($stats['downloads'] ?? 0)) . '</span><small>İndirme</small></div>';
        $html .= '<div><span>' . number_format((float)($stats['views'] ?? 0)) . '</span><small>Görüntülenme</small></div>';
        $html .= '</div></div></section>';
        
        return $html;
    }
}

if (!function_exists('renderSearchFiltersWidget')) {
    function renderSearchFiltersWidget(array $settings, array $data): string
    {
        $query = htmlspecialchars($data['query'] ?? '');
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= '<div class="public-widget__header ui-panel__head"><h2><i class="bi bi-funnel"></i> Arama Filtreleri</h2></div>';
        $html .= '<div class="public-widget__body ui-panel__body"><div class="sidebar-sort-widget">';
        $html .= '<div class="sidebar-sort-field">';
        $html .= '<label class="sidebar-sort-label">Sıralama</label>';
        $html .= '<select class="form-select form-select-sm" data-ui-query-sort="?q=' . urlencode($query) . '&sort=">';
        $html .= '<option value="relevance">İlgililik</option>';
        $html .= '<option value="date">Tarih (Yeni)</option>';
        $html .= '<option value="popular">Popülerlik</option>';
        $html .= '<option value="downloads">İndirme</option>';
        $html .= '</select></div>';
        $html .= '</div></div></section>';
        
        return $html;
    }
}

if (!function_exists('renderPopularSearchesWidget')) {
    function renderPopularSearchesWidget(PDO $pdo, array $settings): string
    {
        $searches = ['ETS2 Mod', 'Kamyon', 'Harita', 'Skin', 'Trailer'];
        
        $html = '<section class="public-widget sidebar-panel ui-panel">';
        $html .= '<div class="public-widget__header ui-panel__head"><h2><i class="bi bi-search"></i> Popüler Aramalar</h2></div>';
        $html .= '<div class="public-widget__body ui-panel__body"><div class="tag-cloud">';
        
        foreach ($searches as $term) {
            $url = '/index.php?q=' . urlencode($term);
            $html .= "<a href=\"{$url}\">{$term}</a>";
        }
        
        $html .= '</div></div></section>';
        return $html;
    }
}
