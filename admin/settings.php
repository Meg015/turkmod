<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('settings.view', 'Ayarlari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Gelişmiş Ayarlar';
$definitions = adminSettingDefinitions();
$centralPublicRoutes = function_exists('routePublicRouteCatalog') ? routePublicRouteCatalog() : [];
$sections = [
    'general' => ['title' => 'Genel', 'icon' => 'bi-gear'],
    'user_system' => ['title' => 'Kullanıcı Sistemi', 'icon' => 'bi-person-gear'],
    'seo' => ['title' => 'SEO', 'icon' => 'bi-search'],
    'moderation' => ['title' => 'Moderasyon', 'icon' => 'bi-shield-check'],
    'comments' => ['title' => 'Yorum Sistemi', 'icon' => 'bi-chat-dots'],
    'downloads' => ['title' => 'İndirme Yöneticisi', 'icon' => 'bi-download'],
    'file_manager' => ['title' => 'Dosya Yöneticisi', 'icon' => 'bi-folder2-open'],
    'user_uploads' => ['title' => 'Mod Yükle', 'icon' => 'bi-cloud-plus'],
    'route_filters' => ['title' => 'Rota Filtreleri', 'icon' => 'bi-signpost-split'],
    'notifications' => ['title' => 'Bildirim Sistemi', 'icon' => 'bi-bell'],
    'toast_notifications' => ['title' => 'Toast Bildirim', 'icon' => 'bi-chat-square-dots'],
    'email' => ['title' => 'E-posta', 'icon' => 'bi-envelope'],
    'rate_limit' => ['title' => 'Rate Limit', 'icon' => 'bi-speedometer2'],
    'leaderboard' => ['title' => 'Liderlik Tablosu', 'icon' => 'bi-trophy'],
    'performance' => ['title' => 'Performans', 'icon' => 'bi-lightning-charge'],
    'social_features' => ['title' => 'Sosyal Özellikler', 'icon' => 'bi-people'],
    'content_moderation' => ['title' => 'İçerik Moderasyonu', 'icon' => 'bi-exclamation-triangle'],
    'popup_announcement' => ['title' => 'Popup Duyuru', 'icon' => 'bi-megaphone'],
    'cron' => ['title' => 'Cron & Görevler', 'icon' => 'bi-clock-history'],
];

$sectionDescriptions = [
    'user_system' => 'Kayıt erişimi, oturum süreleri ve şifre politikalarını tek merkezden yönetin.',
    'route_filters' => 'Konu ve kategori URL on eklerini yönetin. Örnek: /konu/slug-id yerine /topic/slug-id veya /kategori/slug yerine /category/slug kullanabilirsiniz.',
    'rate_limit' => 'Kötüye kullanımı önlemek için farklı işlemler için rate limit (hız sınırı) ayarlarını yapılandırın. Her işlem için maksimum deneme sayısı ve zaman penceresi belirleyebilirsiniz.',
    'leaderboard' => 'Liderlik tablosu sistemi ayarlarını yönetin. Cache süreleri, minimum gereksinimler ve görünürlük seçeneklerini yapılandırın.',
    'performance' => 'Önbellekleme, GZIP sıkıştırma, CDN, lazy loading ve minifikasyon gibi performans optimizasyonlarını yönetin.',
    'social_features' => 'Sosyal medya bağlantıları ve kullanıcı etkileşimiyle ilgili sosyal özellikleri tek merkezden yönetin.',
    'content_moderation' => 'İçerik kalitesi, otomatik etiketleme, intihal kontrolü ve yinelenen içerik tespiti gibi moderasyon ayarlarını yapılandırın.',
    'toast_notifications' => 'Anlık bildirim (toast) konumu, görünümü, animasyonu, zamanlayıcısı ve davranış ayarlarını tek merkezden yönetin.',
    'popup_announcement' => 'Ziyaretçilerinize veya üyelerinize ilk girişte gösterilmek üzere popup duyuruları ayarlayın, süre ve hedef kitle kuralları belirleyin.',
    'cron' => 'Arka plan görevleri ve zamanlanmış işlemleri (Cron Job) buradan yönetebilirsiniz.',
];

$sectionDescriptions['route_filters'] = 'Friendly URL on eklerini, alias rotalarini ve canonical yonlendirme davranisini tek yerden yonetin. Konu, kategori ve profil rotalari ayni sistemden calisir.';

$cronGroups = [
    'cron-tab-general' => [
        'title' => 'Genel Ayarlar',
        'icon' => 'bi-gear',
        'description' => 'Arka plan görevleri için temel çalışma kuralları ve güvenlik ayarları.',
        'keys' => ['cron_enabled', 'cron_secret_key', 'cron_health_scan_interval', 'cron_batch_size']
    ],
    'cron-tab-health' => [
        'title' => 'Sistem Sağlığı',
        'icon' => 'bi-heart-pulse',
        'description' => 'Cron görevlerinin düzgün çalışıp çalışmadığını kontrol eden gerçek zamanlı sağlık monitörü.',
        'keys' => []
    ],
    'cron-tab-logs' => [
        'title' => 'Cron Logları',
        'icon' => 'bi-card-list',
        'description' => 'Arka planda çalışan cron görevlerinin geçmişi ve detaylı hata kayıtları.',
        'keys' => []
    ],
    'cron-tab-endpoints' => [
        'title' => 'Görev Yöneticisi',
        'icon' => 'bi-terminal',
        'description' => 'Sunucunuzda (cPanel, Plesk, Terminal) ayarlamanız gereken komutlar.',
        'keys' => []
    ]
];

$routeFilterGroups = [
    'route-tab-central' => [
        'title' => 'Merkezi Rotalar',
        'icon' => 'bi-diagram-3',
        'description' => 'Router tarafindan sunulan public clean URL listesini ve hedef dosyalarini kontrol edin.',
        'keys' => [],
    ],
    'route-tab-prefixes' => [
        'title' => 'URL On Ekleri',
        'icon' => 'bi-link-45deg',
        'description' => 'Konu, kategori ve profil URL\'lerinin on eklerini ve alias\'larını yönetin.',
        'keys' => [
            'route_topic_prefix',
            'route_category_prefix',
            'route_category_list_prefix',
            'route_profile_prefix',
            'route_topic_aliases',
            'route_category_aliases',
            'route_profile_aliases',
        ],
    ],
    'route-tab-public-pages' => [
        'title' => 'Sabit Public Sayfalar',
        'icon' => 'bi-globe2',
        'description' => 'Giris, kayit, sifre sifirlama, liderlik, etkinlik ve diger sabit public sayfalarin URL yollarini yonetin.',
        'keys' => [
            'route_login_path',
            'route_register_path',
            'route_logout_path',
            'route_forgot_password_path',
            'route_reset_password_path',
            'route_notifications_path',
            'route_leaderboard_path',
            'route_ban_appeals_path',
            'route_contact_path',
            'route_upload_topic_path',
            'route_edit_topic_path',
            'route_download_path',
            'route_events_path',
        ],
    ],
    'route-tab-redirects' => [
        'title' => 'Yönlendirmeler',
        'icon' => 'bi-arrow-repeat',
        'description' => 'Canonical yönlendirme, alias yönlendirme ve eski URL yönlendirmelerini yapılandırın.',
        'keys' => [
            'route_redirect_to_canonical',
            'route_alias_redirects',
            'route_old_url_redirect',
            'route_www_redirect',
            'route_https_redirect',
        ],
    ],
    'route-tab-format' => [
        'title' => 'URL Formatı',
        'icon' => 'bi-type',
        'description' => 'URL yapısı, slug formatı ve karakter işleme ayarlarını belirleyin.',
        'keys' => [
            'route_hide_index_php',
            'route_trailing_slash',
            'route_topic_id_suffix',
            'route_slug_format',
            'route_case_sensitive',
            'route_url_max_length',
        ],
    ],
    'route-tab-advanced' => [
        'title' => 'Gelişmiş',
        'icon' => 'bi-sliders',
        'description' => 'Sayfalama ve sıralama formatı gibi gelişmiş URL ayarları.',
        'keys' => [
            'route_pagination_format',
            'route_sort_format',
        ],
    ],
];

$fileManagerGroups = [
    'file-manager-tab-files' => [
        'title' => 'Dosya Ayarları',
        'icon' => 'bi-folder2-open',
        'description' => 'Yükleme dizini, boyut sınırı, varsayılan klasör ve kabul edilecek güvenli dosya türleri.',
        'keys' => [
            'max_upload_size',
            'upload_path',
            'allowed_file_ext',
            'media_default_folder',
        ],
    ],
    'file-manager-tab-images' => [
        'title' => 'Resim Ayarları',
        'icon' => 'bi-images',
        'description' => 'Görsel uzantıları, WebP dönüşümü, sıkıştırma, thumbnail ve filigran ayarları.',
        'keys' => [
            'allowed_image_ext',
            'auto_resize_images',
            'image_resize_width',
            'image_resize_height',
            'webp_enabled',
            'webp_quality',
            'webp_keep_original',
            'jpeg_quality',
            'png_compression',
            'image_strip_metadata',
            'image_sharpen',
            'thumbnail_enabled',
            'thumbnail_width',
            'thumbnail_height',
            'thumbnail_crop',
            'watermark_enabled',
            'watermark_text',
            'watermark_position',
            'watermark_opacity',
            'watermark_font_size',
        ],
    ],
];

$seoGroups = [
    'seo-tab-meta' => [
        'title' => 'Meta & Canonical',
        'icon' => 'bi-card-text',
        'description' => 'Sayfa basliklari, aciklamalar, indeksleme ve canonical davranisi.',
        'keys' => [
            'default_meta_title',
            'meta_title_suffix',
            'default_meta_description',
            'meta_description_max_length',
            'canonical_base_url',
            'canonical_trailing_slash',
            'category_meta_template',
            'profile_meta_template',
        ],
    ],
    'seo-tab-social' => [
        'title' => 'Sosyal & Dogrulama',
        'icon' => 'bi-share',
        'description' => 'Open Graph, Twitter kartlari ve arama motoru dogrulama kodlari.',
        'keys' => [
            'og_image',
            'og_type',
            'twitter_card',
            'twitter_handle',
            'google_analytics_id',
            'google_site_verification',
        ],
    ],
    'seo-tab-sitemap' => [
        'title' => 'Sitemap Ayarları',
        'icon' => 'bi-diagram-3',
        'description' => 'XML sitemap ve görsel sitemap ayarlarını tek yerden yönetin.',
        'keys' => [
            'sitemap_enabled',
            'sitemap_max_urls',
            'sitemap_changefreq',
            'sitemap_priority_home',
            'sitemap_priority_topics',
            'sitemap_priority_categories',
            'sitemap_include_categories',
            'sitemap_exclude_drafts',
            'sitemap_route_enabled',
            'sitemap_cache_duration',
        ],
    ],
    'seo-tab-images' => [
        'title' => 'Gorseller',
        'icon' => 'bi-images',
        'description' => 'Gorsel sitemap ve medya indeksleme ayarlari.',
        'keys' => [
            'image_sitemap_enabled',
            'image_sitemap_max_images',
            'image_sitemap_hero',
            'image_sitemap_media',
            'image_sitemap_inline',
        ],
    ],
    'seo-tab-robots' => [
        'title' => 'Robots.txt',
        'icon' => 'bi-robot',
        'description' => 'Robots.txt ayarları ve crawl delay politikalarını yönetin.',
        'keys' => [
            'robots_enabled',
            'robots_disallow_admin',
            'robots_disallow_includes',
            'robots_disallow_uploads',
            'robots_disallow_database',
            'robots_crawl_delay',
            'robots_custom_rules',
        ],
    ],
    'seo-tab-structured' => [
        'title' => 'Structured Data',
        'icon' => 'bi-code-square',
        'description' => 'Schema.org yapisal veri ve organizasyon bilgileri.',
        'keys' => [
            'structured_data_category',
            'structured_data_profile',
            'schema_organization_name',
            'schema_organization_logo',
        ],
    ],
    'seo-tab-pagination' => [
        'title' => 'Pagination SEO',
        'icon' => 'bi-arrow-left-right',
        'description' => 'Sayfalama stratejisi ve indeksleme ayarlari.',
        'keys' => [
            'pagination_strategy',
            'pagination_max_pages_index',
        ],
    ],
    'seo-tab-image-seo' => [
        'title' => 'Image SEO',
        'icon' => 'bi-image',
        'description' => 'Gorsel alt metni otomatik olusturma ve sablonlari.',
        'keys' => [
            'image_alt_auto_generate',
            'image_alt_template',
            'image_alt_fallback',
            'image_alt_min_length',
        ],
    ],
    'seo-tab-index' => [
        'title' => 'Index/Noindex',
        'icon' => 'bi-eye-slash',
        'description' => 'Sayfa türlerine göre gelişmiş indeksleme kontrolü.',
        'keys' => [
            'allow_indexing',
            'index_homepage',
            'index_categories',
            'index_topics',
            'index_tag_pages',
            'index_profiles',
            'robots_noindex_search',
            'noindex_empty_categories',
            'noindex_draft_topics',
            'index_paginated_pages',
        ],
    ],
    'seo-tab-advanced' => [
        'title' => 'Yapisal Veri & Kod',
        'icon' => 'bi-code-square',
        'description' => 'Schema.org isaretlemeleri ve ozel head kodlari.',
        'keys' => [
            'structured_data',
            'schema_site_search',
            'schema_breadcrumbs',
            'custom_head_code',
        ],
    ],
];

$commentGroups = [
    'comments-tab-general' => [
        'title' => 'Genel',
        'icon' => 'bi-gear',
        'description' => 'Yorum sisteminin temel işleyiş kuralları.',
        'keys' => ['allow_comments', 'comment_approval_required', 'comment_allow_guest', 'comment_per_page', 'comment_sort_order']
    ],
    'comments-tab-limits' => [
        'title' => 'Limitler & Kısıtlamalar',
        'icon' => 'bi-shield-exclamation',
        'description' => 'Yorum uzunluğu, yanıt derinliği ve düzenleme penceresi.',
        'keys' => ['max_comment_length', 'comment_min_length', 'comment_nested', 'comment_max_depth', 'comment_edit_window']
    ],
    'comments-tab-features' => [
        'title' => 'Özellikler',
        'icon' => 'bi-star',
        'description' => 'Reaksiyonlar, markdown ve medya izinleri.',
        'keys' => ['comment_reactions_enabled', 'comment_reactions_types', 'comment_markdown_enabled', 'comment_mentions_enabled', 'comment_edit_history', 'comment_media_enabled', 'comment_realtime_poll']
    ],
    'comments-tab-moderation' => [
        'title' => 'Moderasyon & Şikayet',
        'icon' => 'bi-shield-check',
        'description' => 'Spam tespiti, otomatik gizleme ve şikayet sistemi.',
        'keys' => ['comment_spam_detection', 'comment_report_enabled', 'comment_auto_hide_reports', 'comment_word_filter', 'comment_auto_ban_words']
    ]
];

$downloadGroups = [
    'downloads-tab-general' => [
        'title' => 'Mevcut Ayarlar',
        'icon' => 'bi-download',
        'description' => 'Konu detayındaki indirme kartları ve sayaç davranışı.',
        'keys' => [
            'download_countdown_seconds',
            'download_ready_text',
            'download_wait_text',
            'download_done_text',
            'download_show_counts',
        ],
    ],
    'downloads-tab-redirect' => [
        'title' => 'İndirme Yönlendirme',
        'icon' => 'bi-box-arrow-up-right',
        'description' => 'Dış indirme yönlendirme sayfasındaki metin, görünürlük ve otomatik yönlendirme davranışı.',
        'keys' => [
            'download_redirect_page_enabled',
            'download_redirect_auto_enabled',
            'download_redirect_show_target_url',
            'download_redirect_kicker',
            'download_redirect_title',
            'download_redirect_intro',
            'download_redirect_host_label',
            'download_redirect_link_label',
            'download_redirect_topic_label',
            'download_redirect_protocol_label',
            'download_redirect_safety_domain_text',
            'download_redirect_safety_count_text',
            'download_redirect_safety_external_text',
            'download_redirect_note',
            'download_redirect_timer_template',
            'download_redirect_primary_label',
            'download_redirect_primary_countdown_label',
            'download_redirect_redirecting_label',
            'download_redirect_secondary_label',
            'download_redirect_default_link_name',
            'download_redirect_default_topic_title',
            'download_redirect_missing_message',
            'download_redirect_invalid_message',
            'download_redirect_error_message',
        ],
    ],
];

$contentModerationGroups = [
    'content-moderation-tab-workflow' => [
        'title' => 'Akış',
        'icon' => 'bi-shield-check',
        'description' => 'Yeni gönderi, kullanıcı düzenlemesi ve otomatik moderasyon ana davranışları.',
        'keys' => [
            'content_moderation_enabled',
            'user_upload_require_approval',
            'user_upload_default_status',
            'topic_user_edit_requires_approval',
        ],
    ],
    'content-moderation-tab-quality' => [
        'title' => 'Kalite',
        'icon' => 'bi-card-checklist',
        'description' => 'Başlık, açıklama ve yinelenen içerik kontrolleri.',
        'keys' => [
            'topic_min_title_length',
            'topic_require_excerpt',
            'user_upload_min_title_length',
            'user_upload_max_title_length',
            'user_upload_min_content_length',
            'user_upload_block_duplicate_titles',
        ],
    ],
    'content-moderation-tab-required' => [
        'title' => 'Zorunlu Alanlar',
        'icon' => 'bi-ui-checks-grid',
        'description' => 'Kullanıcı gönderilerinde zorunlu tutulacak medya ve meta alanları.',
        'keys' => [
            'user_upload_require_cover',
            'user_upload_require_gallery',
            'user_upload_require_author',
            'user_upload_require_version',
            'user_upload_require_download_link',
        ],
    ],
    'content-moderation-tab-words' => [
        'title' => 'Kelime Filtresi',
        'icon' => 'bi-filter-circle',
        'description' => 'Başlık ve açıklamada otomatik yasak kelime kontrolü.',
        'keys' => [
            'content_moderation_blocked_words',
            'content_moderation_blocked_words_action',
            'content_moderation_blocked_words_message',
            'content_moderation_flag_note',
        ],
    ],
];

$userSystemGroups = [
    'user-system-tab-access' => [
        'title' => 'Erişim & Oturum',
        'icon' => 'bi-person-check',
        'description' => 'Kayıt erişimi, normal oturum ve oturumu açık tut sürelerini yönetin.',
        'keys' => ['allow_registration', 'login_identifier_mode', 'session_timeout_minutes', 'remember_session_timeout_minutes'],
    ],
    'user-system-tab-passwords' => [
        'title' => 'Şifre Politikası',
        'icon' => 'bi-key',
        'description' => 'Şifre karmaşıklığı ve geçerlilik politikasını yönetin.',
        'keys' => [
            'password_min_length',
            'password_require_uppercase',
            'password_require_numbers',
            'password_require_special',
            'password_expiry_days',
        ],
    ],
];

$rateLimitGroups = [
    'rate-tab-auth' => [
        'title' => 'Giriş & Üyelik',
        'icon' => 'bi-person-lock',
        'description' => 'Giriş, kayıt ve şifre sıfırlama denemeleri için güvenlik limitleri.',
        'keys' => [
            'login_rate_limit',
            'login_rate_window',
            'register_rate_limit',
            'register_rate_window',
            'password_reset_rate_limit',
            'password_reset_rate_window',
        ],
    ],
    'rate-tab-search-api' => [
        'title' => 'Arama & API',
        'icon' => 'bi-braces',
        'description' => 'Genel arama ve konu API uçları için istek limitleri.',
        'keys' => [
            'search_rate_limit',
            'search_rate_window',
            'api_topics_rate_limit',
            'api_topics_rate_window',
            'api_leaderboard_rate_limit',
            'api_leaderboard_rate_window',
            'api_analytics_rate_limit',
            'api_analytics_rate_window',
        ],
    ],
    'rate-tab-interactions' => [
        'title' => 'Etkileşimler',
        'icon' => 'bi-hand-thumbs-up',
        'description' => 'Favori, konu şikayeti ve indirme sayacı istek limitleri.',
        'keys' => [
            'api_favorite_rate_limit',
            'api_favorite_rate_window',
            'api_reports_rate_limit',
            'api_reports_rate_window',
            'api_report_submit_rate_limit',
            'api_report_submit_rate_window',
            'download_count_rate_limit',
            'download_count_rate_window',
        ],
    ],
    'rate-tab-user-reports' => [
        'title' => 'Kullanici Sikayetleri',
        'icon' => 'bi-person-exclamation',
        'description' => 'Kullanici sikayeti API ve gonderim limitleri.',
        'keys' => [
            'api_user_reports_rate_limit',
            'api_user_reports_rate_window',
            'api_user_report_submit_rate_limit',
            'api_user_report_submit_rate_window',
        ],
    ],
    'rate-tab-comments' => [
        'title' => 'Yorumlar',
        'icon' => 'bi-chat-left-text',
        'description' => 'Yorum gönderme, mention arama ve yorum işlem limitleri.',
        'keys' => [
            'comment_rate_minutes',
            'comment_rate_max',
            'comment_rate_admin_bypass',
            'comment_mention_rate_max',
            'comment_mention_rate_window',
            'comment_edit_rate_max',
            'comment_edit_rate_window',
            'comment_reaction_rate_max',
            'comment_reaction_rate_window',
            'comment_report_rate_max',
            'comment_report_rate_window',
        ],
    ],
    'rate-tab-submissions' => [
        'title' => 'Gönderimler',
        'icon' => 'bi-cloud-arrow-up',
        'description' => 'Kullanıcı mod gönderim sıklığı limitleri.',
        'keys' => [
            'user_upload_hourly_limit',
            'user_upload_daily_limit',
        ],
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        if ($isAjax) {
            sendCsrfError();
        }
        flash('error', 'Güvenlik hatası.');
        header('Location: settings.php');
        exit;
    }
    if (!adminCurrentUserCan('settings.edit')) {
        adminDenyAction('Ayarlari kaydetmek icin gerekli izin hesabiniza tanimlanmamis.', 'settings.php');
    }

    try {
        saveAdminSettings($pdo, $_POST);
        $activeTab = $_POST['_active_tab'] ?? 'general';
        logActivity($pdo, 'settings_updated', 'settings', null, ['active_tab' => $activeTab]);
        
        if ($isAjax) {
            sendSuccess('Ayarlar başarıyla kaydedildi.', ['activeTab' => $activeTab]);
        }
        
        flash('success', 'Ayarlar kaydedildi.');
        header('Location: settings.php#' . $activeTab);
        exit;
    } catch (Throwable $e) {
        if ($isAjax) {
            sendError('settings_save_failed', 'Ayarlar kaydedilemedi: ' . safeErrorMessage($e), 500);
        }
        flash('error', 'Ayarlar kaydedilemedi: ' . safeErrorMessage($e));
    }
}

$settings = getAdminSettings($pdo);
$adminPublicBaseUrl = function_exists('appPublicBaseUrl')
    ? rtrim(appPublicBaseUrl(true, (string) ($baseUri ?? ''), is_array($envConfig ?? null) ? $envConfig : []), '/')
    : rtrim((string) ($baseUri ?? ''), '/');
$adminPublicBaseUrl = $adminPublicBaseUrl !== '' ? $adminPublicBaseUrl : rtrim((string) ($baseUri ?? ''), '/');
$settingsGdLoaded = extension_loaded('gd');
$settingsGdInfo = $settingsGdLoaded ? gd_info() : [];
$settingsWebpSupport = $settingsGdLoaded && !empty($settingsGdInfo['WebP Support']);
$settingsWebpRequirementNote = $settingsGdLoaded
    ? 'PHP GD kurulu ancak WebP desteğiyle derlenmemiş. WebP dönüşümü için GD WebP desteği veya hosting tarafında WebP destekli GD kurulumu gerekir.'
    : 'PHP GD eklentisi kurulu değil. WebP dönüşümü için PHP GD eklentisini WebP desteğiyle etkinleştirmeniz gerekir.';
$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
?>

<form method="post" action="settings.php" class="settings-admin-form" id="settingsForm" data-admin-no-lock="1">
    <?= csrf_field() ?>
    <input type="hidden" name="_active_tab" id="activeTabInput" value="general">
    <input type="hidden" name="_sections" value="<?= htmlspecialchars(implode(',', array_keys($sections))) ?>">

    <div class="settings-tabs-wrapper ui-section">
        <ul class="settings-tabs">
            <?php foreach ($sections as $id => $section): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $id === 'general' ? 'active' : '' ?>" href="#<?= htmlspecialchars($id) ?>">
                        <i class="bi <?= htmlspecialchars($section['icon']) ?>"></i><?= htmlspecialchars($section['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="settings-tab-content ui-section">
        <?php foreach ($sections as $id => $section): ?>
            <section id="<?= htmlspecialchars($id) ?>" class="settings-section ui-section">
                <?php if ($id !== 'file_manager'): ?>
                <div class="admin-card admin-card-spaced ui-panel">
                    <div class="card-header ui-panel__head">
                        <i class="bi <?= htmlspecialchars($section['icon']) ?> me-2"></i><?= htmlspecialchars($section['title']) ?> Ayarları
                    </div>
                    <div class="card-body ui-panel__body">
                <?php endif; ?>
                        <?php if (!empty($sectionDescriptions[$id] ?? '')): ?>
                            <div class="admin-section-note ui-section">
                                <?= htmlspecialchars($sectionDescriptions[$id]) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($id === 'route_filters'): ?>
                            <div class="route-filter-subtabs">
                                <?php $routeFirst = true; foreach ($routeFilterGroups as $routeTabId => $routeGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn<?= $routeFirst ? ' active' : '' ?>" data-route-tab="<?= htmlspecialchars($routeTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($routeGroup['icon'] ?? 'bi-sliders')) ?>"></i><?= htmlspecialchars((string) ($routeGroup['title'] ?? $routeTabId)) ?>
                                    </button>
                                <?php $routeFirst = false; endforeach; ?>
                            </div>

                            <?php $routeFirst = true; foreach ($routeFilterGroups as $routeTabId => $routeGroup): ?>
                                <div class="route-filter-subtab-panel admin-card admin-card-spaced<?= $routeFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($routeTabId) ?>">
                                    <div class="card-header ui-panel__head">
                                        <i class="bi <?= htmlspecialchars((string) ($routeGroup['icon'] ?? 'bi-sliders')) ?> me-2"></i><?= htmlspecialchars((string) ($routeGroup['title'] ?? $routeTabId)) ?>
                                    </div>
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($routeGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($routeGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($routeTabId === 'route-tab-central'): ?>
                                            <div class="route-public-route-overview">
                                                <div class="route-public-route-summary">
                                                    <i class="bi bi-signpost-split"></i>
                                                    <div>
                                                        <strong>Router tarafindan ayrilan public rotalar</strong>
                                                        <span>Bu liste merkezi katalogdan okunur; konu, kategori, profil ve sabit public sayfa yollari bu adreslerle cakismamali.</span>
                                                    </div>
                                                </div>
                                                <div class="route-public-route-grid">
                                                    <?php foreach ($centralPublicRoutes as $routePath => $routeMeta): ?>
                                                        <?php
                                                            if (!empty($routeMeta['is_alias'])) {
                                                                continue;
                                                            }
                                                            $routePath = (string) $routePath;
                                                            $routeUrl = $routePath === '' ? '/' : '/' . ltrim($routePath, '/');
                                                            $target = (string) ($routeMeta['target'] ?? '');
                                                            $kind = (string) ($routeMeta['kind'] ?? 'Rota');
                                                            $dispatch = (string) ($routeMeta['dispatch'] ?? 'file');
                                                            $dispatchLabel = $dispatch === 'events' ? 'Events handler' : 'Dosya';
                                                        ?>
                                                        <div class="route-public-route-card">
                                                            <div class="route-public-route-card-head">
                                                                <code><?= htmlspecialchars($routeUrl) ?></code>
                                                                <span><?= htmlspecialchars($kind) ?></span>
                                                            </div>
                                                            <strong><?= htmlspecialchars((string) ($routeMeta['label'] ?? $routeUrl)) ?></strong>
                                                            <div class="route-public-route-target">
                                                                <span><?= htmlspecialchars($dispatchLabel) ?></span>
                                                                <code><?= htmlspecialchars($target) ?></code>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($routeGroup['keys'])): ?>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php foreach (($routeGroup['keys'] ?? []) as $key): ?>
                                                    <?php if (!isset($definitions[$key])) { continue; } ?>
                                                    <?php $definition = $definitions[$key]; ?>
                                                    <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label">
                                                                    <?= htmlspecialchars($definition['label']) ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                            <?php if ($definition['type'] === 'text'): ?>
                                                                <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                            <?php elseif ($definition['type'] === 'select'): ?>
                                                                <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                    <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php elseif ($definition['type'] === 'multicheck'): ?>
                                                                <?php $currentValues = array_map('trim', explode(',', $settings[$key] ?? $definition['default'])); ?>
                                                                <div class="admin-multicheck-group">
                                                                    <?php foreach (($definition['options'] ?? []) as $val => $lbl): ?>
                                                                        <label class="ui-admin-switch">
                                                                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $currentValues) ? 'checked' : '' ?>>
                                                                            <span class="ui-admin-switch-label"><?= htmlspecialchars($lbl) ?></span>
                                                                        </label>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php $routeFirst = false; endforeach; ?>



                            <script src="<?= asset_url('admin/assets/settings-page-notifications.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'cron'): ?>
                            <div class="route-filter-subtabs">
                                <?php $cronFirst = true; foreach ($cronGroups as $cronTabId => $cronGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn cron-subtab-btn<?= $cronFirst ? ' active' : '' ?>" data-cron-tab="<?= htmlspecialchars($cronTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($cronGroup['icon'] ?? 'bi-gear')) ?>"></i><?= htmlspecialchars((string) ($cronGroup['title'] ?? $cronTabId)) ?>
                                    </button>
                                <?php $cronFirst = false; endforeach; ?>
                            </div>

                            <?php $cronFirst = true; foreach ($cronGroups as $cronTabId => $cronGroup): ?>
                                <div class="route-filter-subtab-panel cron-subtab-panel admin-card admin-card-spaced<?= $cronFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($cronTabId) ?>">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($cronGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($cronGroup['description'])) ?></div>
                                        <?php endif; ?>

                                        <?php if ($cronTabId === 'cron-tab-endpoints'): ?>
                                            <div class="admin-section-block ui-section">
                                                <div class="admin-inline-head ui-panel__head">
                                                    <i class="bi bi-info-circle text-primary"></i>
                                                    <span class="admin-inline-title">Nasıl Çalışır?</span>
                                                </div>
                                                <div class="alert alert-info border-info d-flex gap-3">
                                                    <i class="bi bi-info-square-fill fs-4 mt-1"></i>
                                                    <div>
                                                        Arka plan görevlerinin otomatik çalışabilmesi için sunucunuzda (cPanel/Plesk veya Crontab) aşağıdaki komutlardan birini tanımlamanız gerekmektedir. <b>CLI komutu</b> daha performanslıdır. Eğer sunucunuz CLI komutu desteklemiyorsa <b>URL Adresi (Wget)</b> yöntemini kullanabilirsiniz.
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="admin-divider-block mt-4">
                                                <div class="admin-inline-head ui-panel__head"><i class="bi bi-heart-pulse text-danger"></i> Sistem ve Veri Tarama Görevleri</div>
                                                <div class="admin-settings-grid-sm ui-grid">
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">Konu Sağlığı Taraması (Health Scan)</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="php <?= realpath(__DIR__.'/../cron/topic-health-scan.php') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">Önerilen: <code>* * * * *</code> (Her dakika). Hatalı veya kırık içerikleri tespit eder.</small>
                                                    </div>
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">URL ile Tetikleme</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/topic-health-scan.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                            <a href="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/topic-health-scan.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Test Et"><i class="bi bi-box-arrow-up-right"></i></a>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3">
                                                        <label class="ui-admin-form-label fw-bold">Bildirim E-posta Kuyruğu (Notification Emails)</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="php <?= realpath(__DIR__.'/../cron/send-notification-email-queue.php') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">Önerilen: <code>* * * * *</code> (Her dakika). Bildirim maillerini sırayla gönderir.</small>
                                                    </div>
                                                    <div class="mt-3">
                                                        <label class="ui-admin-form-label fw-bold">URL ile Tetikleme</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/send-notification-email-queue.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                            <a href="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/send-notification-email-queue.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Test Et"><i class="bi bi-box-arrow-up-right"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="admin-divider-block mt-4">
                                                <div class="admin-inline-head ui-panel__head"><i class="bi bi-trophy text-warning"></i> Liderlik Tablosu Görevleri</div>
                                                <div class="admin-settings-grid-sm ui-grid">
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">Liderlik Ön Bellek Yenileme (Günlük Örnek)</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="php <?= realpath(__DIR__.'/../cron/update-leaderboard-cache.php') ?> --period=daily" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">Önerilen: <code>*/15 * * * *</code> (15 dakikada bir). Periyot <code>daily, weekly, monthly, quarterly, yearly</code> olabilir.</small>
                                                    </div>
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">URL ile Tetikleme (Günlük periyot)</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/update-leaderboard-cache.php?period=daily&secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                            <a href="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/update-leaderboard-cache.php?period=daily&secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Test Et"><i class="bi bi-box-arrow-up-right"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="admin-divider-block mt-4">
                                                <div class="admin-inline-head ui-panel__head"><i class="bi bi-calendar-event text-success"></i> Etkinlik Sistemi Görevleri</div>
                                                <div class="admin-settings-grid-sm ui-grid">
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">Etkinlik Sistemi Ana Görevi (Master Cron)</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="php <?= realpath(__DIR__.'/../cron/events-master.php') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">Önerilen: <code>* * * * *</code> (Her dakika). Tüm etkinlik işlemlerini (Log temizleme, çekiliş sonuçlandırma, ödül iptalleri ve mailler) tek başına sırasıyla yönetir.</small>
                                                    </div>
                                                    <div>
                                                        <label class="ui-admin-form-label fw-bold">URL ile Tetikleme</label>
                                                        <div class="admin-inline-control">
                                                            <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/events-master.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" readonly data-ui-select-on-focus>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                                            <a href="<?= htmlspecialchars($adminPublicBaseUrl) ?>/cron/events-master.php?secret=<?= htmlspecialchars($settings['cron_secret_key'] ?? '') ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Test Et"><i class="bi bi-box-arrow-up-right"></i></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php elseif ($cronTabId === 'cron-tab-health'): ?>
                                            <?php
                                            $latestCron = null;
                                            if (isset($pdo)) {
                                                try {
                                                    $stmt = $pdo->query("SELECT created_at FROM application_logs WHERE channel = 'cron' ORDER BY id DESC LIMIT 1");
                                                    $latestCron = $stmt->fetchColumn();
                                                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
                                            }
                                            $isHealthy = false;
                                            $minutesAgo = -1;
                                            if ($latestCron) {
                                                $timeDiff = time() - strtotime($latestCron);
                                                $minutesAgo = max(0, floor($timeDiff / 60));
                                                $isHealthy = $timeDiff < 3600; // 1 saat
                                            }
                                            ?>
                                            <div class="alert <?= $isHealthy ? 'alert-success border-success' : 'alert-danger border-danger' ?> d-flex gap-3 align-items-center mt-3">
                                                <i class="bi <?= $isHealthy ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger' ?> fs-1"></i>
                                                <div>
                                                    <h5 class="alert-heading mb-1 fw-bold"><?= $isHealthy ? 'Sistem Sağlıklı: Cron Aktif' : 'Uyarı: Cron Çalışmıyor Olabilir' ?></h5>
                                                    <p class="mb-0">
                                                        <?php if ($latestCron): ?>
                                                            Son cron görevi <strong><?= $minutesAgo ?> dakika önce</strong> (<?= date('d.m.Y H:i:s', strtotime($latestCron)) ?>) çalıştı.
                                                        <?php else: ?>
                                                            Sistemde henüz kaydedilmiş bir cron logu bulunmuyor.
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!$isHealthy): ?>
                                                        <hr>
                                                        <p class="mb-0 small">
                                                            <strong>Not:</strong> CPanel veya sunucunuzdaki Cron ayarlarını kontrol ediniz. Eğer son 1 saattir hiçbir görev çalışmadıysa Master Cron komutlarınız tetiklenmiyor olabilir.
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        <?php elseif ($cronTabId === 'cron-tab-logs'): ?>
                                            <?php
                                            $cronLogs = [];
                                            if (isset($pdo)) {
                                                try {
                                                    $stmt = $pdo->query("SELECT * FROM application_logs WHERE channel = 'cron' ORDER BY id DESC LIMIT 50");
                                                    $cronLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
                                            }
                                            ?>
                                            <?php if (empty($cronLogs)): ?>
                                                <div class="alert alert-warning border-warning mt-3">Henüz kaydedilmiş bir cron logu bulunamadı.</div>
                                            <?php else: ?>
                                                <div class="table-responsive mt-3">
                                                    <table class="ui-admin-table w-100">
                                                        <thead>
                                                            <tr>
                                                                <th>Tarih</th>
                                                                <th>Seviye</th>
                                                                <th>Mesaj</th>
                                                                <th>Bağlam (Job/SAPI)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($cronLogs as $log): ?>
                                                                <?php 
                                                                $ctx = json_decode($log['context_json'] ?? '{}', true); 
                                                                $badgeClass = match($log['level']) {
                                                                    'error' => 'bg-danger',
                                                                    'warning' => 'bg-warning text-dark',
                                                                    'success' => 'bg-success',
                                                                    default => 'bg-secondary'
                                                                };
                                                                ?>
                                                                <tr>
                                                                    <td class="text-nowrap text-muted ui-admin-width-date"><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                                                                    <td class="ui-admin-width-action">
                                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper($log['level'])) ?></span>
                                                                    </td>
                                                                    <td><strong><?= htmlspecialchars($log['message']) ?></strong></td>
                                                                    <td class="text-muted small">
                                                                        <?php if(!empty($ctx['job_key'])): ?>
                                                                            <span class="badge bg-light text-dark border">Job: <?= htmlspecialchars($ctx['job_key']) ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if(!empty($ctx['sapi'])): ?>
                                                                            <span class="badge bg-light text-dark border">Yöntem: <?= htmlspecialchars($ctx['sapi']) ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php foreach (($cronGroup['keys'] ?? []) as $key): ?>
                                                    <?php if (!isset($definitions[$key])) { continue; } ?>
                                                    <?php $definition = $definitions[$key]; ?>
                                                    <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label fw-medium">
                                                                    <?= htmlspecialchars($definition['label']) ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <label class="ui-admin-form-label fw-medium" for="<?= htmlspecialchars($key) ?>">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                            <?php if ($definition['type'] === 'text'): ?>
                                                                <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control bg-light"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                            <?php elseif ($definition['type'] === 'select'): ?>
                                                                <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select bg-light">
                                                                    <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php else: ?>
                                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control bg-light" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php $cronFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-theme.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'seo'): ?>
                            <div class="seo-subtabs">
                                <?php $seoFirst = true; foreach ($seoGroups as $seoTabId => $seoGroup): ?>
                                    <button type="button" class="seo-subtab-btn<?= $seoFirst ? ' active' : '' ?>" data-seo-tab="<?= htmlspecialchars($seoTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($seoGroup['icon'] ?? 'bi-search')) ?>"></i><?= htmlspecialchars((string) ($seoGroup['title'] ?? $seoTabId)) ?>
                                    </button>
                                <?php $seoFirst = false; endforeach; ?>
                            </div>

                            <?php $seoFirst = true; foreach ($seoGroups as $seoTabId => $seoGroup): ?>
                                <div class="seo-subtab-panel admin-card admin-card-spaced<?= $seoFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($seoTabId) ?>">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($seoGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($seoGroup['description'])) ?></div>
                                        <?php endif; ?>
                                    <?php if ($seoTabId === 'seo-tab-sitemap'): ?>
                                        <!-- Sitemap Routing Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-gear"></i>
                                                <span class="admin-inline-title">Sitemap Routing</span>
                                            </div>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php
                                                $routingKeys = ['sitemap_route_enabled', 'sitemap_cache_duration'];
                                                foreach ($routingKeys as $key):
                                                    if (!isset($definitions[$key])) continue;
                                                    $definition = $definitions[$key];
                                                ?>
                                                    <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label">
                                                                    <?= htmlspecialchars($definition['label']) ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- XML Sitemap Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-diagram-3"></i>
                                                <span class="admin-inline-title">XML Sitemap</span>
                                            </div>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php
                                                $xmlKeys = ['sitemap_enabled', 'sitemap_max_urls', 'sitemap_changefreq', 'sitemap_priority_home', 'sitemap_priority_topics', 'sitemap_priority_categories', 'sitemap_include_categories', 'sitemap_exclude_drafts'];
                                                foreach ($xmlKeys as $key): 
                                                    if (!isset($definitions[$key])) continue;
                                                    $definition = $definitions[$key];
                                                ?>
                                                    <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label">
                                                                    <?= htmlspecialchars($definition['label']) ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                            <?php if ($definition['type'] === 'select'): ?>
                                                                <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                    <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php else: ?>
                                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Görsel Sitemap Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-images"></i>
                                                <span class="admin-inline-title">Görsel Sitemap</span>
                                            </div>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php 
                                                $imageKeys = ['image_sitemap_enabled', 'image_sitemap_max_images', 'image_sitemap_hero', 'image_sitemap_media', 'image_sitemap_inline'];
                                                foreach ($imageKeys as $key): 
                                                    if (!isset($definitions[$key])) continue;
                                                    $definition = $definitions[$key];
                                                ?>
                                                    <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label">
                                                                    <?= htmlspecialchars($definition['label']) ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Sitemap URL'leri -->
                                        <div class="admin-divider-block">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-link-45deg"></i>Sitemap & Robots URL'leri
                                            </div>
                                            <div class="admin-settings-grid-sm ui-grid">
                                                <div>
                                                    <label class="ui-admin-form-label">Sitemap Index</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Ac"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Topic Sitemap</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/topic-sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/topic-sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Ac"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Image Sitemap</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/image-sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/image-sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Ac"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Robots.txt</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/robots.txt" readonly>
                                                        <a href="<?= $baseUri ?>/robots.txt" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Ac"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Diğer SEO Alt Sekmeleri -->
                                        <div class="admin-settings-grid ui-grid">
                                            <?php foreach (($seoGroup['keys'] ?? []) as $key): ?>
                                                <?php if (!isset($definitions[$key])) { continue; } ?>
                                                <?php $definition = $definitions[$key]; ?>
                                                <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                    <?php if ($definition['type'] === 'bool'): ?>
                                                        <label class="ui-admin-switch">
                                                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                            <span class="ui-admin-switch-label">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                    <?php else: ?>
                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                            <?= htmlspecialchars($definition['label']) ?>
                                                            <?php if (!empty($definition['tooltip'])): ?>
                                                                <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <?php if ($definition['type'] === 'text'): ?>
                                                            <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                        <?php elseif ($definition['type'] === 'select'): ?>
                                                            <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php elseif ($definition['type'] === 'color'): ?>
                                                            <?php
                                                                $colorValue = (string) ($settings[$key] ?? '');
                                                                $colorDefault = (string) ($definition['default'] ?? '#000000');
                                                                $resolvedColor = preg_match('/^#[0-9a-fA-F]{6}$/', $colorValue) ? $colorValue : $colorDefault;
                                                            ?>
                                                            <div class="admin-color-field" data-color-field>
                                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="color" class="admin-color-input" value="<?= htmlspecialchars($resolvedColor) ?>" data-color-input>
                                                                <div class="admin-color-meta">
                                                                    <strong data-color-value><?= htmlspecialchars(strtoupper($resolvedColor)) ?></strong>
                                                                    <span>Mevcut renk<?= $colorValue === '' ? ' (varsayılan)' : '' ?></span>
                                                                </div>
                                                                <span class="admin-color-default">Varsayılan: <?= htmlspecialchars(strtoupper($colorDefault)) ?></span>
                                                            </div>
                                                        <?php else: ?>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            <?php $seoFirst = false; endforeach; ?>
                        <?php elseif ($id === 'comments'): ?>
                            <div class="comments-subtabs">
                                <?php $commentFirst = true; foreach ($commentGroups as $commentTabId => $commentGroup): ?>
                                    <button type="button" class="comments-subtab-btn<?= $commentFirst ? ' active' : '' ?>" data-comments-tab="<?= htmlspecialchars($commentTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($commentGroup['icon'] ?? 'bi-gear')) ?>"></i><?= htmlspecialchars((string) ($commentGroup['title'] ?? $commentTabId)) ?>
                                    </button>
                                <?php $commentFirst = false; endforeach; ?>
                            </div>

                            <?php $commentFirst = true; foreach ($commentGroups as $commentTabId => $commentGroup): ?>
                                <div class="comments-subtab-panel admin-card admin-card-spaced<?= $commentFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($commentTabId) ?>">
                                    <div class="card-header ui-panel__head">
                                        <i class="bi <?= htmlspecialchars((string) ($commentGroup['icon'] ?? 'bi-gear')) ?> me-2"></i><?= htmlspecialchars((string) ($commentGroup['title'] ?? $commentTabId)) ?>
                                    </div>
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($commentGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($commentGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <div class="admin-settings-grid ui-grid">
                                            <?php foreach (($commentGroup['keys'] ?? []) as $key): ?>
                                                <?php if (!isset($definitions[$key])) { continue; } ?>
                                                <?php $definition = $definitions[$key]; ?>
                                                <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                    <?php if ($definition['type'] === 'bool'): ?>
                                                        <label class="ui-admin-switch">
                                                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                            <span class="ui-admin-switch-label">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                    <?php else: ?>
                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                            <?= htmlspecialchars($definition['label']) ?>
                                                            <?php if (!empty($definition['tooltip'])): ?>
                                                                <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <?php if ($definition['type'] === 'text'): ?>
                                                            <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                        <?php elseif ($definition['type'] === 'select'): ?>
                                                            <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php elseif ($definition['type'] === 'multicheck'): ?>
                                                            <?php $currentValues = array_map('trim', explode(',', $settings[$key] ?? $definition['default'])); ?>
                                                            <div class="admin-multicheck-group">
                                                                <?php foreach (($definition['options'] ?? []) as $val => $lbl): ?>
                                                                    <label class="ui-admin-switch">
                                                                        <input type="checkbox" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $currentValues) ? 'checked' : '' ?>>
                                                                        <span class="ui-admin-switch-label"><?= htmlspecialchars($lbl) ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php $commentFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-header.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'user_system'): ?>
                            <div class="settings-subtabs" data-settings-subtabs>
                                <?php $userSystemFirst = true; foreach ($userSystemGroups as $userSystemTabId => $userSystemGroup): ?>
                                    <button type="button" class="settings-subtab-link<?= $userSystemFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($userSystemTabId) ?>" data-settings-subtab-scope="user-system">
                                        <i class="bi <?= htmlspecialchars((string) ($userSystemGroup['icon'] ?? 'bi-person-gear')) ?> me-1"></i><?= htmlspecialchars((string) ($userSystemGroup['title'] ?? $userSystemTabId)) ?>
                                    </button>
                                <?php $userSystemFirst = false; endforeach; ?>
                            </div>

                            <?php $userSystemFirst = true; foreach ($userSystemGroups as $userSystemTabId => $userSystemGroup): ?>
                                <div class="route-filter-subtab-panel admin-card admin-card-spaced<?= $userSystemFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($userSystemTabId) ?>" data-settings-subtab-panel="<?= htmlspecialchars($userSystemTabId) ?>" data-settings-subtab-scope="user-system">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($userSystemGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($userSystemGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?= adminRenderSettingsGrid($definitions, $settings, 'user_system', (array) ($userSystemGroup['keys'] ?? [])) ?>
                                    </div>
                                </div>
                            <?php $userSystemFirst = false; endforeach; ?>
                        <?php elseif ($id === 'rate_limit'): ?>
                            <div class="rate-limit-subtabs">
                                <?php $rateFirst = true; foreach ($rateLimitGroups as $rateTabId => $rateGroup): ?>
                                    <button type="button" class="rate-limit-subtab-btn<?= $rateFirst ? ' active' : '' ?>" data-rate-tab="<?= htmlspecialchars($rateTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($rateGroup['icon'] ?? 'bi-speedometer2')) ?>"></i><?= htmlspecialchars((string) ($rateGroup['title'] ?? $rateTabId)) ?>
                                    </button>
                                <?php $rateFirst = false; endforeach; ?>
                            </div>

                            <?php $rateFirst = true; foreach ($rateLimitGroups as $rateTabId => $rateGroup): ?>
                                <div class="rate-limit-subtab-panel admin-card admin-card-spaced<?= $rateFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($rateTabId) ?>">
                                    <div class="card-header ui-panel__head">
                                        <i class="bi <?= htmlspecialchars((string) ($rateGroup['icon'] ?? 'bi-speedometer2')) ?> me-2"></i><?= htmlspecialchars((string) ($rateGroup['title'] ?? $rateTabId)) ?>
                                    </div>
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($rateGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($rateGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <div class="admin-settings-grid ui-grid">
                                            <?php foreach (($rateGroup['keys'] ?? []) as $key): ?>
                                                <?php if (!isset($definitions[$key])) { continue; } ?>
                                                <?php $definition = $definitions[$key]; ?>
                                                <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                    <?php if ($definition['type'] === 'bool'): ?>
                                                        <label class="ui-admin-switch">
                                                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                            <span class="ui-admin-switch-label">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                                       data-bs-toggle="tooltip"
                                                                       data-bs-placement="top"
                                                                       data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                    <?php else: ?>
                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                            <?= htmlspecialchars($definition['label']) ?>
                                                            <?php if (!empty($definition['tooltip'])): ?>
                                                                <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-placement="top"
                                                                   data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <?php if ($definition['type'] === 'select'): ?>
                                                            <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php else: ?>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php $rateFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-footer.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'user_uploads'): ?>
                            <?php
                                $userUploadGroupOrder = ['Genel', 'Zorunlu Alanlar', 'Görsel Kuralları', 'Video ve Linkler', 'Limitler', 'Form Davranışı'];
                                $userUploadGroups = [];
                                foreach ($definitions as $key => $definition) {
                                    if (($definition['section'] ?? '') !== 'user_uploads') {
                                        continue;
                                    }
                                    $groupName = (string) ($definition['group'] ?? 'Genel');
                                    $userUploadGroups[$groupName][$key] = $definition;
                                }
                                $userUploadGroups = array_replace(array_fill_keys($userUploadGroupOrder, []), $userUploadGroups);
                            ?>
                            <div class="user-upload-settings-groups">
                                <?php foreach ($userUploadGroups as $groupName => $groupDefinitions): ?>
                                    <?php if (!$groupDefinitions) { continue; } ?>
                                    <div class="user-upload-setting-group">
                                        <div class="user-upload-setting-group-head ui-panel__head">
                                            <i class="bi bi-sliders2"></i>
                                            <strong><?= htmlspecialchars($groupName) ?></strong>
                                            <span><?= count($groupDefinitions) ?> ayar</span>
                                        </div>
                                        <div class="user-upload-setting-group-grid ui-grid">
                                            <?php foreach ($groupDefinitions as $key => $definition): ?>
                                                <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                                    <?php if ($definition['type'] === 'bool'): ?>
                                                        <label class="ui-admin-switch">
                                                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                            <span class="ui-admin-switch-label">
                                                                <?= htmlspecialchars($definition['label']) ?>
                                                                <?php if (!empty($definition['tooltip'])): ?>
                                                                    <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                                       data-bs-toggle="tooltip"
                                                                       data-bs-placement="top"
                                                                       data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                    <?php else: ?>
                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                            <?= htmlspecialchars($definition['label']) ?>
                                                            <?php if (!empty($definition['tooltip'])): ?>
                                                                <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                                   data-bs-toggle="tooltip"
                                                                   data-bs-placement="top"
                                                                   data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <?php if ($definition['type'] === 'text'): ?>
                                                            <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                        <?php elseif ($definition['type'] === 'select'): ?>
                                                            <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                                <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php elseif ($definition['type'] === 'multicheck'): ?>
                                                            <?php $currentValues = array_map('trim', explode(',', $settings[$key] ?? $definition['default'])); ?>
                                                            <div class="admin-multicheck-group">
                                                                <?php foreach (($definition['options'] ?? []) as $val => $lbl): ?>
                                                                    <label class="ui-admin-switch">
                                                                        <input type="checkbox" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $currentValues) ? 'checked' : '' ?>>
                                                                        <span class="ui-admin-switch-label"><?= htmlspecialchars($lbl) ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($id === 'downloads'): ?>
                            <div class="route-filter-subtabs settings-subtabs" data-settings-subtabs>
                                <?php $downloadFirst = true; foreach ($downloadGroups as $downloadTabId => $downloadGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn settings-subtab-btn<?= $downloadFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($downloadTabId) ?>" data-settings-subtab-scope="downloads">
                                        <i class="bi <?= htmlspecialchars((string) ($downloadGroup['icon'] ?? 'bi-download')) ?>"></i><?= htmlspecialchars((string) ($downloadGroup['title'] ?? $downloadTabId)) ?>
                                    </button>
                                <?php $downloadFirst = false; endforeach; ?>
                            </div>

                            <?php $downloadFirst = true; foreach ($downloadGroups as $downloadTabId => $downloadGroup): ?>
                                <div class="route-filter-subtab-panel settings-subtab-panel admin-card admin-card-spaced<?= $downloadFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($downloadTabId) ?>" data-settings-subtab-panel="<?= htmlspecialchars($downloadTabId) ?>" data-settings-subtab-scope="downloads">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($downloadGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($downloadGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?= adminRenderSettingsGrid($definitions, $settings, 'downloads', (array) ($downloadGroup['keys'] ?? [])) ?>
                                    </div>
                                </div>
                            <?php $downloadFirst = false; endforeach; ?>
                        <?php elseif ($id === 'content_moderation'): ?>
                            <div class="route-filter-subtabs settings-subtabs" data-settings-subtabs>
                                <?php $contentModerationFirst = true; foreach ($contentModerationGroups as $moderationTabId => $moderationGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn settings-subtab-btn<?= $contentModerationFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($moderationTabId) ?>" data-settings-subtab-scope="content-moderation">
                                        <i class="bi <?= htmlspecialchars((string) ($moderationGroup['icon'] ?? 'bi-shield-check')) ?>"></i><?= htmlspecialchars((string) ($moderationGroup['title'] ?? $moderationTabId)) ?>
                                    </button>
                                <?php $contentModerationFirst = false; endforeach; ?>
                            </div>

                            <?php $contentModerationFirst = true; foreach ($contentModerationGroups as $moderationTabId => $moderationGroup): ?>
                                <div class="route-filter-subtab-panel settings-subtab-panel admin-card admin-card-spaced<?= $contentModerationFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($moderationTabId) ?>" data-settings-subtab-panel="<?= htmlspecialchars($moderationTabId) ?>" data-settings-subtab-scope="content-moderation">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($moderationGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($moderationGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?= adminRenderSettingsGrid($definitions, $settings, 'content_moderation', (array) ($moderationGroup['keys'] ?? [])) ?>
                                    </div>
                                </div>
                            <?php $contentModerationFirst = false; endforeach; ?>
                        <?php elseif ($id !== 'file_manager' && $id !== 'seo'): ?>
                            <div class="admin-settings-grid <?= $id === 'route_filters' ? 'route-filter-settings-grid' : '' ?> ui-grid">
                                <?php foreach ($definitions as $key => $definition): ?>
                                    <?php if ($definition['section'] !== $id) { continue; } ?>
                                    <div class="<?= ($definition['type'] === 'text' || $definition['type'] === 'richtext') ? 'admin-field-wide' : '' ?>">
                                        <?php if ($definition['type'] === 'bool'): ?>
                                            <label class="ui-admin-switch">
                                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <span class="ui-admin-switch-label">
                                                    <?= htmlspecialchars($definition['label']) ?>
                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                        <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php else: ?>
                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                <?= htmlspecialchars($definition['label']) ?>
                                                <?php if (!empty($definition['tooltip'])): ?>
                                                    <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-placement="top"
                                                       data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                <?php endif; ?>
                                            </label>
                                            <?php if ($definition['type'] === 'text' || $definition['type'] === 'richtext'): ?>
                                                <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control<?= ($definition['type'] === 'richtext' || !empty($definition['rich'])) ? ' rich-editor' : '' ?>"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                            <?php elseif ($definition['type'] === 'select'): ?>
                                                <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                    <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($definition['type'] === 'color'): ?>
                                                <?php
                                                    $colorValue = (string) ($settings[$key] ?? '');
                                                    $colorDefault = (string) ($definition['default'] ?? '#000000');
                                                    $resolvedColor = preg_match('/^#[0-9a-fA-F]{6}$/', $colorValue) ? $colorValue : $colorDefault;
                                                ?>
                                                <div class="admin-color-field" data-color-field>
                                                    <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="color" class="admin-color-input" value="<?= htmlspecialchars($resolvedColor) ?>" data-color-input>
                                                    <div class="admin-color-meta">
                                                        <strong data-color-value><?= htmlspecialchars(strtoupper($resolvedColor)) ?></strong>
                                                        <span>Mevcut renk<?= $colorValue === '' ? ' (varsayılan)' : '' ?></span>
                                                    </div>
                                                    <span class="admin-color-default">Varsayılan: <?= htmlspecialchars(strtoupper($colorDefault)) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php if ($id !== 'file_manager'): ?>
                    </div>
                </div>
                    <?php endif; ?>
                <?php if ($id === 'file_manager'): ?>
                <!-- Dosya Yöneticisi Ayarları -->
                <div class="admin-card admin-card-spaced ui-panel">
                    <div class="card-header ui-panel__head">
                        <i class="bi bi-folder2-open me-2"></i>Dosya Yöneticisi Ayarları
                    </div>
                    <div class="card-body ui-panel__body">
                        <div class="admin-section-desc">
                            Dosya yükleme kuralları ile görsel optimizasyon ayarlarını aynı merkezden yönetin. Dosya Ayarları kabul edilen türleri ve hedef klasörü, Resim Ayarları ise WebP, thumbnail ve filigran davranışlarını kontrol eder.
                        </div>

                        <div class="file-manager-subtabs">
                            <?php $fileManagerFirst = true; foreach ($fileManagerGroups as $fileManagerTabId => $fileManagerGroup): ?>
                                <button type="button" class="file-manager-subtab-btn<?= $fileManagerFirst ? ' active' : '' ?>" data-file-manager-tab="<?= htmlspecialchars($fileManagerTabId) ?>">
                                    <i class="bi <?= htmlspecialchars((string) ($fileManagerGroup['icon'] ?? 'bi-folder2-open')) ?>"></i><?= htmlspecialchars((string) ($fileManagerGroup['title'] ?? $fileManagerTabId)) ?>
                                </button>
                            <?php $fileManagerFirst = false; endforeach; ?>
                        </div>

                        <?php $fileManagerFirst = true; foreach ($fileManagerGroups as $fileManagerTabId => $fileManagerGroup): ?>
                            <div class="file-manager-subtab-panel<?= $fileManagerFirst ? ' is-active' : '' ?>" id="<?= htmlspecialchars($fileManagerTabId) ?>">
                                <div class="file-manager-subtab-head">
                                    <i class="bi <?= htmlspecialchars((string) ($fileManagerGroup['icon'] ?? 'bi-folder2-open')) ?>"></i>
                                    <div>
                                        <h3><?= htmlspecialchars((string) ($fileManagerGroup['title'] ?? $fileManagerTabId)) ?></h3>
                                        <?php if (!empty($fileManagerGroup['description'])): ?>
                                            <p><?= htmlspecialchars((string) ($fileManagerGroup['description'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="settings-images-grid ui-grid">
                                    <?php foreach (($fileManagerGroup['keys'] ?? []) as $key): ?>
                                        <?php if (!isset($definitions[$key])) { continue; } ?>
                                        <?php $definition = $definitions[$key]; ?>
                                        <div class="<?= $definition['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                            <?php if ($definition['type'] === 'bool'): ?>
                                                <?php
                                                    $isWebpToggle = $key === 'webp_enabled';
                                                    $isWebpLocked = $isWebpToggle && !$settingsWebpSupport;
                                                ?>
                                                <label class="ui-admin-switch<?= $isWebpLocked ? ' ui-admin-switch-disabled' : '' ?>">
                                                    <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" <?= (!$isWebpLocked && ($settings[$key] ?? '0') === '1') ? 'checked' : '' ?> <?= $isWebpLocked ? 'disabled' : '' ?>>
                                                    <span class="ui-admin-switch-label">
                                                        <?= htmlspecialchars($definition['label']) ?>
                                                        <?php if ($isWebpToggle && $settingsWebpSupport): ?>
                                                            <span class="settings-support-badge settings-support-badge-ok">Destekleniyor</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($definition['tooltip'])): ?>
                                                            <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                               data-bs-toggle="tooltip"
                                                               data-bs-placement="top"
                                                               data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <?php if ($isWebpLocked): ?>
                                                    <div class="settings-webp-lock-note">
                                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                                        <span><?= htmlspecialchars($settingsWebpRequirementNote) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                    <?= htmlspecialchars($definition['label']) ?>
                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                        <i class="bi bi-info-circle ms-1 text-secondary admin-help-icon"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-placement="top"
                                                           data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                    <?php endif; ?>
                                                </label>
                                                <?php if ($definition['type'] === 'text'): ?>
                                                    <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                <?php elseif ($definition['type'] === 'select'): ?>
                                                    <select id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" class="ui-admin-form-select">
                                                        <?php foreach (($definition['options'] ?? []) as $value => $label): ?>
                                                            <option value="<?= htmlspecialchars($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $fileManagerFirst = false; endforeach; ?>

                        <script src="<?= asset_url('admin/assets/settings-page-seo.js', $baseUri) ?>" defer></script>

                    <!-- Sunucu Desteği -->
                    <div class="settings-images-support">
                        <h3 class="settings-images-support-title">Sunucu Görsel Desteği</h3>
                        <p class="settings-images-support-subtitle">Sisteminizde yüklü olan görsel formatları ve kütüphaneleri kontrol edin</p>

                        <?php
                            $gdLoaded = extension_loaded('gd');
                            $gdInfo = $gdLoaded ? gd_info() : [];
                            $webpSupport = $gdLoaded && !empty($gdInfo['WebP Support']);
                            $jpegSupport = $gdLoaded && !empty($gdInfo['JPEG Support']);
                            $pngSupport = $gdLoaded && !empty($gdInfo['PNG Support']);
                            $gifSupport = $gdLoaded && !empty($gdInfo['GIF Read Support']);
                            $avifSupport = $gdLoaded && !empty($gdInfo['AVIF Support']);
                        ?>

                        <div class="settings-images-support-grid ui-grid">
                            <!-- GD Kütüphanesi -->
                            <div class="settings-images-support-card <?= $gdLoaded ? 'settings-images-support-active' : 'settings-images-support-inactive' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $gdLoaded ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">GD Kütüphanesi</h4>
                                    <p class="settings-images-support-version"><?= $gdLoaded ? ($gdInfo['GD Version'] ?? 'Yüklü') : 'Yüklü Değil' ?></p>
                                </div>
                            </div>

                            <!-- WebP -->
                            <div class="settings-images-support-card <?= $webpSupport ? 'settings-images-support-active' : 'settings-images-support-warning' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $webpSupport ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">WebP Format</h4>
                                    <p class="settings-images-support-version"><?= $webpSupport ? 'Destekleniyor' : 'Desteklenmiyor' ?></p>
                                </div>
                            </div>

                            <!-- JPEG -->
                            <div class="settings-images-support-card <?= $jpegSupport ? 'settings-images-support-active' : 'settings-images-support-inactive' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $jpegSupport ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">JPEG Format</h4>
                                    <p class="settings-images-support-version"><?= $jpegSupport ? 'Destekleniyor' : 'Desteklenmiyor' ?></p>
                                </div>
                            </div>

                            <!-- PNG -->
                            <div class="settings-images-support-card <?= $pngSupport ? 'settings-images-support-active' : 'settings-images-support-inactive' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $pngSupport ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">PNG Format</h4>
                                    <p class="settings-images-support-version"><?= $pngSupport ? 'Destekleniyor' : 'Desteklenmiyor' ?></p>
                                </div>
                            </div>

                            <!-- GIF -->
                            <div class="settings-images-support-card <?= $gifSupport ? 'settings-images-support-active' : 'settings-images-support-inactive' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $gifSupport ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">GIF Format</h4>
                                    <p class="settings-images-support-version"><?= $gifSupport ? 'Destekleniyor' : 'Desteklenmiyor' ?></p>
                                </div>
                            </div>

                            <!-- AVIF -->
                            <div class="settings-images-support-card <?= $avifSupport ? 'settings-images-support-active' : 'settings-images-support-inactive' ?>">
                                <div class="settings-images-support-icon">
                                    <i class="bi <?= $avifSupport ? 'bi-check-circle-fill' : 'bi-dash-circle' ?>"></i>
                                </div>
                                <div class="settings-images-support-content ui-section">
                                    <h4 class="settings-images-support-name">AVIF Format</h4>
                                    <p class="settings-images-support-version"><?= $avifSupport ? 'Destekleniyor' : 'Mevcut değil' ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!$webpSupport): ?>
                        <div class="settings-images-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <strong>WebP Desteği Yok</strong>
                                <p>WebP dönüşümü için PHP GD kütüphanesinin WebP desteğiyle derlenmiş olması gerekir. Hosting sağlayıcınıza başvurun.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
</div>
                </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="settings-savebar">
        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-save-enhanced">
            <span class="btn-icon-wrapper">
                <i class="bi bi-check-circle-fill"></i>
            </span>
            <span class="btn-text">Ayarları Kaydet</span>
            <span class="btn-shine"></span>
        </button>
    </div>
</form>

<script src="<?= asset_url('admin/assets/settings-page-main.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
