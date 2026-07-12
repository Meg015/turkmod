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
    'user_uploads' => ['title' => 'Kullanıcı Gönderimleri', 'icon' => 'bi-cloud-plus'],
    'route_filters' => ['title' => 'Rota Filtreleri', 'icon' => 'bi-signpost-split'],
    'notifications' => ['title' => 'Bildirim Sistemi', 'icon' => 'bi-bell'],
    'toast_notifications' => ['title' => 'Toast Bildirimleri', 'icon' => 'bi-chat-square-dots'],
    'email' => ['title' => 'E-posta', 'icon' => 'bi-envelope'],
    'rate_limit' => ['title' => 'İstek Sınırları', 'icon' => 'bi-speedometer2'],
    'leaderboard' => ['title' => 'Liderlik Tablosu', 'icon' => 'bi-trophy'],
    'performance' => ['title' => 'Performans', 'icon' => 'bi-lightning-charge'],
    'social_features' => ['title' => 'Sosyal Özellikler', 'icon' => 'bi-people'],
    'content_moderation' => ['title' => 'İçerik Moderasyonu', 'icon' => 'bi-exclamation-triangle'],
    'popup_announcement' => ['title' => 'Açılır Duyuru', 'icon' => 'bi-megaphone'],
    'cron' => ['title' => 'Cron & Görevler', 'icon' => 'bi-clock-history'],
];

$sectionDescriptions = [
    'user_system' => 'Kayıt erişimi, oturum süreleri ve şifre politikalarını tek merkezden yönetin.',
    'route_filters' => 'Konu ve kategori URL ön eklerini yönetin. Örnek: /konu/slug-id yerine /topic/slug-id veya /kategori/slug yerine /category/slug kullanabilirsiniz.',
    'rate_limit' => 'Her satırda iki ana alan vardır: Limit ve Pencere (dakika). Limit, pencere süresinde izin verilen maksimum istek sayısını; pencere ise sayacın ne zaman sıfırlanacağını belirler.',
    'leaderboard' => 'Liderlik tablosu sistemi ayarlarını yönetin. Önbellek süreleri, minimum gereksinimler ve görünürlük seçeneklerini yapılandırın.',
    'performance' => 'Önbellekleme, GZIP sıkıştırma, CDN, lazy loading ve minifikasyon gibi performans optimizasyonlarını yönetin.',
    'social_features' => 'Sosyal medya bağlantıları ve kullanıcı etkileşimiyle ilgili sosyal özellikleri tek merkezden yönetin.',
    'content_moderation' => 'İçerik kalitesi, otomatik etiketleme, intihal kontrolü ve yinelenen içerik tespiti gibi moderasyon ayarlarını yapılandırın.',
    'toast_notifications' => 'Anlık bildirim (toast) konumu, görünümü, animasyonu, zamanlayıcısı ve davranış ayarlarını tek merkezden yönetin.',
    'popup_announcement' => 'Ziyaretçilerinize veya üyelerinize ilk girişte gösterilmek üzere popup duyuruları ayarlayın, süre ve hedef kitle kuralları belirleyin.',
    'cron' => 'Arka plan görevleri ve zamanlanmış işlemleri (Cron Job) buradan yönetebilirsiniz.',
];

$sectionDescriptions['route_filters'] = 'Friendly URL ön eklerini ve canonical yönlendirme davranışını tek yerden yönetin. Konu, kategori ve profil rotaları aynı sistemden çalışır.';

$cronGroups = [
    'cron-tab-general' => [
        'title' => 'Genel Ayarlar',
        'icon' => 'bi-gear',
        'description' => 'Arka plan görevleri için temel çalışma kuralları ve güvenlik ayarları.',
        'keys' => ['cron_enabled', 'cron_php_binary', 'cron_secret_key', 'cron_health_scan_interval', 'cron_batch_size']
    ],
    'cron-tab-endpoints' => [
        'title' => 'Görev Yöneticisi',
        'icon' => 'bi-terminal',
        'description' => 'Cron komutlarını tek listede görün, kopyalayın ve manuel tetikleyin. Her görev için CLI, HTTP yedek ve direkt URL görünür.',
        'keys' => []
    ],
    'cron-tab-health' => [
        'title' => 'Sağlık Durumu',
        'icon' => 'bi-heart-pulse',
        'description' => 'Cron görevlerinin son çalışma durumu ve tazeliğini tek ekranda izleyin.',
        'keys' => []
    ],
];

$routeFilterGroups = [
    'route-tab-central' => [
        'title' => 'Merkezi Rotalar',
        'icon' => 'bi-diagram-3',
        'description' => 'Router tarafından sunulan genel temiz URL listesini ve hedef dosyalarını kontrol edin.',
        'keys' => [],
    ],
    'route-tab-prefixes' => [
        'title' => 'URL On Ekleri',
        'icon' => 'bi-link-45deg',
        'description' => 'Konu, kategori ve profil URL\'lerinin ön eklerini yönetin.',
        'keys' => [
            'route_topic_prefix',
            'route_category_prefix',
            'route_category_list_prefix',
            'route_profile_prefix',
        ],
    ],
    'route-tab-public-pages' => [
        'title' => 'Sabit Public Sayfalar',
        'icon' => 'bi-globe2',
        'description' => 'Giriş, kayıt, şifre sıfırlama, liderlik, etkinlik ve diğer sabit public sayfaların URL yollarını yönetin.',
        'keys' => [
            'route_login_path',
            'route_register_path',
            'route_logout_path',
            'route_forgot_password_path',
            'route_reset_password_path',
            'route_notifications_path',
            'route_messages_path',
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
        'description' => 'WWW ve HTTPS yönlendirmelerini yapılandırın.',
        'keys' => [
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
        'description' => 'Site geneli meta varsayılanları ve canonical URL davranışı.',
        'keys' => [
            'default_meta_title',
            'meta_title_suffix',
            'default_meta_description',
            'meta_description_max_length',
            'canonical_base_url',
            'canonical_trailing_slash',
        ],
    ],
    'seo-tab-public-pages' => [
        'title' => 'Public Sayfalar',
        'icon' => 'bi-window-stack',
        'description' => 'Tüm public sayfaların meta başlığını, açıklamasını, görselini, noindex, nofollow ve sitemap davranışını tek ekrandan yönetin.',
        'keys' => [],
    ],
    'seo-tab-social' => [
        'title' => 'Sosyal Medya & Analitik',
        'icon' => 'bi-share',
        'description' => 'Open Graph, Twitter kartları, Google Analytics ve arama motoru doğrulama kodları.',
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
        'title' => 'Site Haritası',
        'icon' => 'bi-diagram-3',
        'description' => 'XML site haritası, görsel site haritası ve yönlendirme ayarlarını tek yerden yönetin.',
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
        'description' => 'Robots.txt ayarları, crawl delay politikaları ve global indeks kilidini yönetin.',
        'keys' => [
            'allow_indexing',
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
        'title' => 'Yapısal Veri & Özel Kod',
        'icon' => 'bi-code-square',
        'description' => 'Schema.org yapısal veri işaretlemeleri, organizasyon bilgileri ve özel head kodları.',
        'keys' => [
            'structured_data_category',
            'structured_data_profile',
            'schema_organization_name',
            'schema_organization_logo',
            'structured_data',
            'schema_site_search',
            'schema_breadcrumbs',
            'custom_head_code',
        ],
    ],
    'seo-tab-image-seo' => [
        'title' => 'Görsel SEO',
        'icon' => 'bi-image',
        'description' => 'Görsel alt ve title metnini otomatik oluşturma ve şablonları.',
        'keys' => [
            'image_alt_auto_generate',
            'image_alt_template',
            'image_alt_fallback',
            'image_alt_min_length',
            'image_title_auto_generate',
            'image_title_template',
            'image_title_fallback',
            'image_title_min_length',
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
    'downloads-tab-access' => [
        'title' => 'Erişim Kilidi ve Metinler',
        'icon' => 'bi-shield-lock',
        'description' => 'Üyelik ve yorum şartlı indirme kilidinin başlıklarını, açıklamalarını ve kilit açma davranışlarını yönetin.',
        'sections' => [
            [
                'title' => 'Erişim Politikası',
                'icon' => 'bi-shield-check',
                'description' => 'İndirme bağlantılarının kimlere ve hangi yorum doğrulamasıyla açılacağını belirleyin.',
                'keys' => [
                    'download_access_mode',
                    'download_access_comment_requirement',
                ],
            ],
            [
                'title' => 'Süre ve Yenileme',
                'icon' => 'bi-clock-history',
                'description' => 'Yorumla kazanılan erişimin kalıcı veya süreli olmasını ve silme sonrası davranışı yönetin.',
                'keys' => [
                    'download_access_grant_mode',
                    'download_access_grant_duration_value',
                    'download_access_grant_duration_unit',
                    'download_access_relock_on_comment_delete',
                ],
            ],
            [
                'title' => 'Kilit Metinleri ve Davranışlar',
                'icon' => 'bi-lock',
                'description' => 'Kilitli kart mesajlarını, yönlendirme çağrılarını ve otomatik kilit açma davranışlarını düzenleyin.',
                'keys' => [
                    'download_access_login_message',
                    'download_access_comment_title',
                    'download_access_comment_message',
                    'download_access_locked_button_text',
                    'download_access_comment_cta_label',
                    'download_access_open_auth_popup',
                    'download_access_focus_comment_form',
                    'download_access_unlock_after_auth',
                    'download_access_unlock_after_comment',
                ],
            ],
            [
                'title' => 'Giriş/Kayıt Penceresi',
                'icon' => 'bi-person-lock',
                'description' => 'Kilit üzerinden açılan giriş ve kayıt penceresindeki başlık ve işlem metinlerini yönetin.',
                'keys' => [
                    'download_access_auth_modal_title',
                    'download_access_auth_login_label',
                    'download_access_auth_register_label',
                    'download_access_auth_success_message',
                ],
            ],
            [
                'title' => 'Bekleme ve Süre Dolumu',
                'icon' => 'bi-hourglass-split',
                'description' => 'Yorum onayı beklenirken veya süreli erişim sona erdiğinde gösterilecek metinleri belirleyin.',
                'keys' => [
                    'download_access_pending_message',
                    'download_access_pending_button_text',
                    'download_access_expired_title',
                    'download_access_expired_message',
                    'download_access_active_until_template',
                ],
            ],
            [
                'title' => 'Başarı ve İlerleme Görünümü',
                'icon' => 'bi-check-circle',
                'description' => 'Şartlar tamamlandığında gösterilen başarı alanını, adım bilgisini ve görsel geçişleri yönetin.',
                'keys' => [
                    'download_access_success_notice_enabled',
                    'download_access_success_message',
                    'download_access_progress_enabled',
                    'download_access_progress_template',
                    'download_access_success_animation_enabled',
                    'download_access_success_auto_compact',
                    'download_access_success_compact_delay',
                    'download_access_highlight_first_card',
                ],
            ],
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

$emailGroups = [
    'email-tab-settings' => [
        'title' => 'E-posta Ayarları',
        'icon' => 'bi-sliders',
        'description' => 'SMTP sunucusu, gönderici bilgileri ve e-posta sürücüsünü yönetin.',
        'keys' => [
            'mail_driver',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'mail_from_name',
            'mail_from_address',
        ],
    ],
    'email-tab-test' => [
        'title' => 'Test Gönderimi',
        'icon' => 'bi-send-check',
        'description' => 'Mevcut ayarlarla seçtiğiniz adrese test e-postası gönderin.',
        'keys' => [],
    ],
    'email-tab-account' => [
        'title' => 'Hesap E-Posta Şablonları',
        'icon' => 'bi-person-check',
        'description' => 'Kayıt, doğrulama, şifre ve hesap güvenliği e-postalarını tek merkezden yönetin.',
        'keys' => [],
    ],
];

$rateLimitGroups = [
    'rate-tab-auth' => [
        'title' => 'Giriş ve Hesap Güvenliği',
        'icon' => 'bi-person-lock',
        'description' => 'Giriş, kayıt ve şifre sıfırlama denemeleri için IP bazlı güvenlik limitleri.',
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
        'title' => 'Arama ve Veri API',
        'icon' => 'bi-braces',
        'description' => 'Arama, konu listeleme, mesaj, liderlik tablosu ve analitik API istek limitleri.',
        'keys' => [
            'search_rate_limit',
            'search_rate_window',
            'api_topics_rate_limit',
            'api_topics_rate_window',
            'api_messages_rate_limit',
            'api_messages_rate_window',
            'api_leaderboard_rate_limit',
            'api_leaderboard_rate_window',
            'api_analytics_rate_limit',
            'api_analytics_rate_window',
        ],
    ],
    'rate-tab-interactions' => [
        'title' => 'Etkileşim ve Şikayet',
        'icon' => 'bi-hand-thumbs-up',
        'description' => 'Favori, konu şikayet ve indirme sayacı istekleri için limitler.',
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
        'title' => 'Kullanıcı Şikayetleri',
        'icon' => 'bi-person-exclamation',
        'description' => 'Kullanıcı şikayeti listeleme ve gönderim limitleri.',
        'keys' => [
            'api_user_reports_rate_limit',
            'api_user_reports_rate_window',
            'api_user_report_submit_rate_limit',
            'api_user_report_submit_rate_window',
        ],
    ],
    'rate-tab-comments' => [
        'title' => 'Yorum Akışı',
        'icon' => 'bi-chat-left-text',
        'description' => 'Yorum gönderme, mention arama, düzenleme, reaksiyon ve şikayet limitleri.',
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
        'title' => 'Gönderim Limitleri',
        'icon' => 'bi-cloud-arrow-up',
        'description' => 'Kullanıcı gönderim sıklığı için saatlik ve günlük limitler.',
        'keys' => [
            'user_upload_hourly_limit',
            'user_upload_daily_limit',
        ],
    ],
];

if (!function_exists('settingsCronTaskCatalog')) {
    if (!function_exists('settingsCronShellArg')) {
        function settingsCronShellArg(string $value): string
        {
            $value = trim(str_replace(["\r", "\n"], '', $value));
            if ($value === '') {
                return "''";
            }

            return escapeshellarg($value);
        }
    }

    if (!function_exists('settingsCronPhpBinary')) {
        function settingsCronPhpBinary(array $settings): string
        {
            $binary = trim(str_replace(["\r", "\n"], '', (string) ($settings['cron_php_binary'] ?? '')));
            if ($binary === '' || (function_exists('adminCronPhpBinaryIsAutoValue') && adminCronPhpBinaryIsAutoValue($binary))) {
                $candidates = function_exists('adminCronPhpBinaryCandidates') ? adminCronPhpBinaryCandidates() : [];
                return $candidates[0] ?? 'php';
            }

            return $binary;
        }
    }

    if (!function_exists('settingsCronHttpCommand')) {
        function settingsCronHttpCommand(string $url): string
        {
            $quotedUrl = settingsCronShellArg($url);

            return 'curl -fsS ' . $quotedUrl . ' >/dev/null 2>&1 || wget -qO- ' . $quotedUrl . ' >/dev/null 2>&1';
        }
    }

if (!function_exists('settingsBuildEmailTestHtml')) {
        function settingsBuildEmailTestHtml(array $settings, string $recipient, string $message): string
        {
            $siteName = trim((string) ($settings['site_name'] ?? 'Yenidosyalar'));
            if ($siteName === '') {
                $siteName = 'Yenidosyalar';
            }

            $driver = strtoupper(trim((string) ($settings['mail_driver'] ?? 'smtp')));
            $fromName = trim((string) ($settings['mail_from_name'] ?? $siteName));
            if ($fromName === '') {
                $fromName = $siteName;
            }
            $fromAddress = trim((string) ($settings['mail_from_address'] ?? 'noreply@localhost'));
            $smtpHost = trim((string) ($settings['smtp_host'] ?? ''));
            $smtpPort = trim((string) ($settings['smtp_port'] ?? ''));
            $smtpEncryption = strtoupper(trim((string) ($settings['smtp_encryption'] ?? 'tls')));
            $messageSafe = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

            $metaRows = [
                'Sistem' => $siteName,
                'Surucu' => $driver,
                'Gonderici' => $fromName,
                'Gonderici Adresi' => $fromAddress,
                'SMTP Sunucu' => $smtpHost !== '' ? $smtpHost : '—',
                'SMTP Port' => $smtpPort !== '' ? $smtpPort : '—',
                'Sifreleme' => $smtpEncryption !== '' ? $smtpEncryption : '—',
                'Alici' => $recipient,
            ];

            $metaHtml = '';
            foreach ($metaRows as $label => $value) {
                $metaHtml .= '<tr>'
                    . '<th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:600;width:38%;">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</th>'
                    . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#111827;">' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }

            return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"></head>'
                . '<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
                . '<div style="max-width:640px;margin:0 auto;padding:32px 18px;">'
                . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:28px;">'
                . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">E-posta Testi</p>'
                . '<h1 style="margin:0 0 18px;font-size:22px;line-height:1.35;color:#0f172a;">' . htmlspecialchars($siteName . ' e-posta testi', ENT_QUOTES, 'UTF-8') . '</h1>'
                . '<div style="padding:16px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:15px;line-height:1.75;">' . $messageSafe . '</div>'
                . '<div style="margin-top:22px;border-top:1px solid #e5e7eb;padding-top:18px;">'
                . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.6;">' . $metaHtml . '</table>'
                . '</div>'
                . '<p style="margin:20px 0 0;font-size:12px;color:#94a3b8;">Bu ileti, paneldeki mevcut ayarlar kullanılarak test amaçlı oluşturuldu.</p>'
                . '</div></div></body></html>';
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    function settingsCronTaskCatalog(string $adminPublicBaseUrl, array $settings): array
    {
        $baseUrl = rtrim($adminPublicBaseUrl, '/');
        $secret = trim((string) ($settings['cron_secret_key'] ?? ''));
        $phpBinary = settingsCronPhpBinary($settings);
        $phpBinaryCommand = preg_match('/\s/', $phpBinary) === 1 ? settingsCronShellArg($phpBinary) : $phpBinary;

        $scriptPath = static function (string $filename): string {
            $path = realpath(__DIR__ . '/../cron/' . $filename);
            if (is_string($path) && $path !== '') {
                return $path;
            }

            return str_replace('\\', '/', __DIR__ . '/../cron/' . $filename);
        };

        $buildUrl = static function (string $path, array $extraQuery = []) use ($baseUrl, $secret): string {
            $url = $baseUrl . '/' . ltrim($path, '/');
            $query = $extraQuery;
            if ($secret !== '') {
                $query['secret'] = $secret;
            }
            if ($query !== []) {
                $url .= '?' . http_build_query($query);
            }

            return $url;
        };

        return [
            'topic_health_scan' => [
                'job_key' => 'topic_health_scan',
                'group' => 'Sistem ve Veri',
                'title' => 'Konu Sağlığı Taraması',
                'description' => 'Kırık veya eksik içerik sinyallerini tarar.',
                'icon' => 'bi-heart-pulse',
                'schedule' => '* * * * *',
                'schedule_label' => 'Her 1 dakika',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('topic-health-scan.php')) . ' --limit=50',
                'http' => settingsCronHttpCommand($buildUrl('/cron/topic-health-scan.php', ['limit' => '50'])),
                'url' => $buildUrl('/cron/topic-health-scan.php', ['limit' => '50']),
            ],
            'notification_email_queue' => [
                'job_key' => 'notification_email_queue',
                'group' => 'Sistem ve Veri',
                'title' => 'Bildirim E-posta Kuyruğu',
                'description' => 'Bildirim e-posta kuyruğunu sırayla gönderir.',
                'icon' => 'bi-envelope',
                'schedule' => '* * * * *',
                'schedule_label' => 'Her 1 dakika',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('send-notification-email-queue.php')) . ' --limit=25',
                'http' => settingsCronHttpCommand($buildUrl('/cron/send-notification-email-queue.php', ['limit' => '25'])),
                'url' => $buildUrl('/cron/send-notification-email-queue.php', ['limit' => '25']),
            ],
            'rate_limits_cleanup' => [
                'job_key' => 'rate_limits_cleanup',
                'group' => 'Sistem ve Veri',
                'title' => 'Süresi Dolmuş İstek Sınırı Temizliği',
                'description' => 'Süresi dolan request_rate_limits kayıtlarını siler.',
                'icon' => 'bi-speedometer2',
                'schedule' => '*/15 * * * *',
                'schedule_label' => 'Her 15 dakika',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('cleanup-expired-rate-limits.php')),
                'http' => settingsCronHttpCommand($buildUrl('/cron/cleanup-expired-rate-limits.php')),
                'url' => $buildUrl('/cron/cleanup-expired-rate-limits.php'),
            ],
            'leaderboard_cache_daily' => [
                'job_key' => 'leaderboard_cache',
                'group' => 'Sistem ve Veri',
                'title' => 'Liderlik Önbellek Güncelleme',
                'description' => 'Liderlik önbellek hesaplamalarını yeniler.',
                'icon' => 'bi-trophy',
                'schedule' => '*/15 * * * *',
                'schedule_label' => 'Her 15 dakika (daily örneği)',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('update-leaderboard-cache.php')) . ' --period=daily',
                'http' => settingsCronHttpCommand($buildUrl('/cron/update-leaderboard-cache.php', ['period' => 'daily'])),
                'url' => $buildUrl('/cron/update-leaderboard-cache.php', ['period' => 'daily']),
            ],
            'events_master' => [
                'job_key' => 'events_master',
                'group' => 'Sistem ve Veri',
                'title' => 'Etkinlik Ana Cron',
                'description' => 'Etkinlik temizliği, ödül geçerlilik ve kuyruk işlerini yönetir.',
                'icon' => 'bi-calendar-event',
                'schedule' => '* * * * *',
                'schedule_label' => 'Her 1 dakika',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('events-master.php')),
                'http' => settingsCronHttpCommand($buildUrl('/cron/events-master.php')),
                'url' => $buildUrl('/cron/events-master.php'),
            ],
        ];
    }
}

if (!function_exists('settingsEmailTestDiagnostic')) {
    /**
     * Build a concise SMTP diagnostic that is safe to show to an administrator.
     *
     * @param array<string,mixed> $mailResult
     * @param array<int,string> $secrets
     */
    function settingsEmailTestDiagnostic(array $mailResult, array $secrets = []): string
    {
        $diagnostic = trim((string) ($mailResult['error'] ?? ''));
        if ($diagnostic === '') {
            $diagnostic = trim((string) ($mailResult['smtp_response'] ?? ''));
        }

        foreach ($secrets as $secret) {
            $secret = (string) $secret;
            if ($secret !== '') {
                $diagnostic = str_replace($secret, '[masked]', $diagnostic);
            }
        }

        $diagnostic = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $diagnostic) ?? '';
        $diagnostic = trim(preg_replace('/\s+/', ' ', $diagnostic) ?? '');

        if (function_exists('mb_substr')) {
            return mb_substr($diagnostic, 0, 700, 'UTF-8');
        }

        return substr($diagnostic, 0, 700);
    }
}

if (!function_exists('settingsCronTriggerByUrl')) {
    /**
     * @return array{ok:bool,status_code:int,output:string,error:string}
     */
    function settingsCronTriggerByUrl(string $url, int $timeoutSeconds = 20): array
    {
        $result = [
            'ok' => false,
            'status_code' => 0,
            'output' => '',
            'error' => '',
        ];

        if ($url === '') {
            $result['error'] = 'Cron URL bos.';
            return $result;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $result['error'] = 'cURL baslatilamadi.';
                return $result;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => max(5, $timeoutSeconds),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'yenidosyalar-admin-cron-trigger/1.0',
            ]);

            $raw = curl_exec($ch);
            $result['status_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                $result['error'] = (string) curl_error($ch);
                curl_close($ch);
                return $result;
            }

            $result['output'] = trim((string) $raw);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => max(5, $timeoutSeconds),
                    'ignore_errors' => true,
                    'header' => "User-Agent: yenidosyalar-admin-cron-trigger/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            if (is_array($headers)) {
                foreach ($headers as $headerLine) {
                    if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $headerLine, $matches) === 1) {
                        $result['status_code'] = (int) $matches[1];
                    }
                }
            }

            if ($raw === false) {
                $result['error'] = 'URL tetiklenemedi.';
                return $result;
            }
            $result['output'] = trim((string) $raw);
        }

        $result['ok'] = $result['status_code'] >= 200 && $result['status_code'] < 300;
        if (!$result['ok'] && $result['error'] === '') {
            $result['error'] = 'HTTP ' . $result['status_code'] . ' dondu.';
        }

        return $result;
    }
}

if (!function_exists('settingsCronRunSnapshots')) {
    /**
     * @param array<int,string> $jobKeys
     * @return array<string,array{found:bool,status:string,created_at:?string,level:string,message:string,context:array<string,mixed>}>
     */
    function settingsCronRunSnapshots(?PDO $pdo, array $jobKeys): array
    {
        $snapshots = [];
        foreach ($jobKeys as $jobKey) {
            $normalized = trim((string) $jobKey);
            if ($normalized === '') {
                continue;
            }
            $snapshots[$normalized] = [
                'found' => false,
                'status' => 'missing',
                'created_at' => null,
                'level' => 'info',
                'message' => '',
                'context' => [],
            ];
        }

        if (!$pdo instanceof PDO || $snapshots === []) {
            return $snapshots;
        }

        try {
            $stmt = $pdo->query("SELECT level, message, context_json, created_at FROM application_logs WHERE channel = 'cron' ORDER BY id DESC LIMIT 1000");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return $snapshots;
        }

        $remaining = count($snapshots);
        foreach ($rows as $row) {
            $context = json_decode((string) ($row['context_json'] ?? ''), true);
            if (!is_array($context)) {
                $context = [];
            }
            $message = (string) ($row['message'] ?? '');
            $jobKey = trim((string) ($context['job_key'] ?? ''));
            if ($jobKey === '' && str_starts_with($message, 'cron_run:')) {
                $jobKey = substr($message, 9);
            }
            if (!isset($snapshots[$jobKey]) || !empty($snapshots[$jobKey]['found'])) {
                continue;
            }

            $status = strtolower(trim((string) ($context['status'] ?? '')));
            if ($status === '') {
                $status = match ((string) ($row['level'] ?? 'info')) {
                    'error' => 'error',
                    'warning' => 'warning',
                    default => 'success',
                };
            }

            $snapshots[$jobKey] = [
                'found' => true,
                'status' => $status,
                'created_at' => (string) ($row['created_at'] ?? null),
                'level' => (string) ($row['level'] ?? 'info'),
                'message' => $message,
                'context' => $context,
            ];

            $remaining--;
            if ($remaining <= 0) {
                break;
            }
        }

        return $snapshots;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        if ($isAjax) {
            sendCsrfError();
        }
        flash('error', 'Guvenlik hatasi.');
        header('Location: settings.php');
        exit;
    }
    if (!adminCurrentUserCan('settings.edit')) {
        adminDenyAction('Ayarlari kaydetmek icin gerekli izin hesabiniza tanimlanmamis.', 'settings.php');
    }

    $postAction = trim((string) ($_POST['action'] ?? 'save_settings'));
    if ($postAction === 'send_account_email_test') {
        $templateKey = trim((string) ($_POST['account_email_template_key'] ?? ''));
        $recipient = trim((string) ($_POST['account_email_test_recipient'] ?? ''));
        $catalog = \App\Engine\Email\AccountEmailService::catalog();
        if (!isset($catalog[$templateKey])) {
            sendError('account_email_template_invalid', 'Geçersiz hesap e-posta şablonu.', 422);
        }
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            sendError('account_email_recipient_invalid', 'Geçerli bir test e-posta adresi girin.', 422);
        }

        $currentSettings = getAdminSettings($pdo);
        $smtpOverrides = $currentSettings;
        foreach (['mail_driver', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'mail_from_name', 'mail_from_address'] as $settingKey) {
            if (array_key_exists($settingKey, $_POST)) {
                $smtpOverrides[$settingKey] = trim((string) $_POST[$settingKey]);
            }
        }
        $subjectKey = \App\Engine\Email\AccountEmailService::settingKey($templateKey, 'subject');
        $bodyKey = \App\Engine\Email\AccountEmailService::settingKey($templateKey, 'body');
        $publicBase = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        $ok = accountEmailService($pdo)->send($templateKey, $recipient, [
            'username' => 'Test Kullanıcısı',
            'action_url' => $publicBase . '/test-action',
            'login_url' => function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : $publicBase . '/giris',
            'profile_url' => $publicBase . '/profil/test-kullanici',
            'expires_minutes' => '60',
            'old_email' => 'eski@example.com',
            'new_email' => $recipient,
            'actor_context' => 'Yönetim paneli test gönderimi',
        ], [
            'force' => true,
            'enabled' => '1',
            'subject' => (string) ($_POST[$subjectKey] ?? $catalog[$templateKey]['subject']),
            'body' => (string) ($_POST[$bodyKey] ?? $catalog[$templateKey]['body']),
            'settings' => $smtpOverrides,
        ]);
        $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
        if ($ok) {
            sendSuccess('Hesap e-posta testi gönderildi: ' . $recipient, ['template' => $templateKey]);
        }
        $detail = settingsEmailTestDiagnostic($mailResult, [(string) ($smtpOverrides['smtp_password'] ?? '')]);
        sendError('account_email_test_failed', 'Hesap e-posta testi gönderilemedi.' . ($detail !== '' ? ' ' . $detail : ''), 500);
    }
    if ($postAction === 'send_email_test') {
        $currentSettings = getAdminSettings($pdo);
        $recipient = trim((string) ($_POST['email_test_recipient'] ?? ''));
        $message = trim((string) ($_POST['email_test_message'] ?? ''));

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $messageText = 'Geçerli bir test e-posta adresi girin.';
            if ($isAjax) {
                sendError('email_test_invalid_recipient', $messageText, 422);
            }
            flash('error', $messageText);
            header('Location: settings.php#email');
            exit;
        }

        if ($message === '') {
            $messageText = 'Test mesajı boş olamaz.';
            if ($isAjax) {
                sendError('email_test_empty_message', $messageText, 422);
            }
            flash('error', $messageText);
            header('Location: settings.php#email');
            exit;
        }

        $overrideSettings = is_array($currentSettings) ? $currentSettings : [];
        foreach ([
            'mail_driver',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'mail_from_name',
            'mail_from_address',
        ] as $settingKey) {
            if (array_key_exists($settingKey, $_POST)) {
                $overrideSettings[$settingKey] = trim((string) ($_POST[$settingKey] ?? ''));
            }
        }

        $siteName = trim((string) ($overrideSettings['site_name'] ?? 'Yenidosyalar'));
        if ($siteName === '') {
            $siteName = 'Yenidosyalar';
        }

        $subject = $siteName . ' - E-posta Testi';
        $fromName = trim((string) ($overrideSettings['mail_from_name'] ?? $siteName));
        if ($fromName === '') {
            $fromName = $siteName;
        }
        $fromAddress = trim((string) ($overrideSettings['mail_from_address'] ?? ''));

        $mailOk = appSendMail(
            $recipient,
            $subject,
            settingsBuildEmailTestHtml($overrideSettings, $recipient, $message),
            [
                'from_name' => $fromName,
                'from_address' => $fromAddress,
                'reply_to' => $fromAddress,
                'settings' => $overrideSettings,
                'email_log' => [
                    'source' => 'settings',
                    'source_key' => 'email_test',
                    'recipient_name' => $recipient,
                ],
            ]
        );
        $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
        $mailError = settingsEmailTestDiagnostic($mailResult, [
            (string) ($overrideSettings['smtp_password'] ?? ''),
        ]);

        if ($mailOk) {
            $successMessage = 'Test e-postası gönderildi: ' . $recipient;
            if ($isAjax) {
                sendSuccess($successMessage, ['recipient' => $recipient]);
            }
            flash('success', $successMessage);
        } else {
            $errorMessage = 'Test e-postası gönderilemedi.';
            if ($mailError !== '') {
                $errorMessage .= ' ' . $mailError;
            }
            if ($isAjax) {
                sendError('email_test_failed', $errorMessage, 500, [
                    'transport' => (string) ($mailResult['transport'] ?? ''),
                    'smtp_code' => $mailResult['smtp_code'] ?? null,
                ]);
            }
            flash('error', $errorMessage);
        }

        header('Location: settings.php#email');
        exit;
    }
    if ($postAction === 'trigger_cron') {
        $currentSettings = getAdminSettings($pdo);
        $cronSecret = trim((string) ($currentSettings['cron_secret_key'] ?? ''));
        if ($cronSecret === '') {
            $message = 'Cron tetikleme icin once cron_secret_key tanimlanmalidir.';
            if ($isAjax) {
                sendError('cron_secret_missing', $message, 422);
            }
            flash('error', $message);
            header('Location: settings.php#cron');
            exit;
        }
        $triggerBaseUrl = function_exists('appPublicBaseUrl')
            ? rtrim(appPublicBaseUrl(true, (string) ($baseUri ?? ''), is_array($envConfig ?? null) ? $envConfig : []), '/')
            : rtrim((string) ($baseUri ?? ''), '/');
        $triggerBaseUrl = $triggerBaseUrl !== '' ? $triggerBaseUrl : rtrim((string) ($baseUri ?? ''), '/');
        $triggerCatalog = settingsCronTaskCatalog($triggerBaseUrl, $currentSettings);
        $taskKey = trim((string) ($_POST['cron_task'] ?? ''));

        if ($taskKey === '' || !isset($triggerCatalog[$taskKey])) {
            if ($isAjax) {
                sendError('cron_task_missing', 'Gecerli bir cron gorevi secilmedi.', 422);
            }
            flash('error', 'Gecerli bir cron gorevi secilmedi.');
            header('Location: settings.php#cron');
            exit;
        }

        $task = $triggerCatalog[$taskKey];
        $triggerResult = settingsCronTriggerByUrl((string) ($task['url'] ?? ''));
        $resultTone = !empty($triggerResult['ok']) ? 'success' : 'error';
        $outputPreview = mb_substr(trim((string) ($triggerResult['output'] ?? '')), 0, 280, 'UTF-8');
        $errorMessage = trim((string) ($triggerResult['error'] ?? ''));
        $statusCode = (int) ($triggerResult['status_code'] ?? 0);

        if (function_exists('logActivity')) {
            logActivity($pdo, 'cron_manual_triggered', 'settings', null, [
                'task_key' => $taskKey,
                'job_key' => (string) ($task['job_key'] ?? ''),
                'status' => $resultTone,
                'status_code' => $statusCode,
                'error' => $errorMessage,
            ]);
        }

        if (function_exists('adminAuditLogger')) {
            adminAuditLogger()->logAction(
                $pdo,
                'cron_manual_triggered',
                'settings',
                0,
                'Cron gorevi manuel tetiklendi',
                [],
                [
                    'task_key' => $taskKey,
                    'job_key' => (string) ($task['job_key'] ?? ''),
                    'status' => $resultTone,
                    'status_code' => $statusCode,
                ],
                false
            );
        }

        if (!empty($triggerResult['ok'])) {
            $message = 'Cron gorevi tetiklendi: ' . (string) ($task['title'] ?? $taskKey);
            if ($outputPreview !== '') {
                $message .= ' | Cikti: ' . $outputPreview;
            }

            if ($isAjax) {
                sendSuccess($message);
            }

            flash('success', $message);
        } else {
            $message = 'Cron tetikleme basarisiz: ' . (string) ($task['title'] ?? $taskKey) . ' (HTTP ' . $statusCode . ')';
            if ($errorMessage !== '') {
                $message .= ' | Hata: ' . $errorMessage;
            } elseif ($outputPreview !== '') {
                $message .= ' | Cikti: ' . $outputPreview;
            }

            if ($isAjax) {
                sendError('cron_trigger_failed', $message, 500);
            }

            flash('error', $message);
        }

        header('Location: settings.php#cron');
        exit;
    }

    try {
        saveAdminSettings($pdo, $_POST);
        $activeTab = $_POST['_active_tab'] ?? 'general';
        logActivity($pdo, 'settings_updated', 'settings', null, ['active_tab' => $activeTab]);
        adminAuditLogger()->logAction($pdo, 'settings_updated', 'settings', 0, 'Ayarlar güncellendi', [], ['active_tab' => $activeTab], false);

        if ($isAjax) {
            sendSuccess('Ayarlar basariyla kaydedildi.', ['activeTab' => $activeTab]);
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
$seoPublicPageGroups = function_exists('seoPublicPageGroups')
    ? seoPublicPageGroups($settings)
    : [];
$adminPublicBaseUrl = function_exists('appPublicBaseUrl')
    ? rtrim(appPublicBaseUrl(true, (string) ($baseUri ?? ''), is_array($envConfig ?? null) ? $envConfig : []), '/')
    : rtrim((string) ($baseUri ?? ''), '/');
$adminPublicBaseUrl = $adminPublicBaseUrl !== '' ? $adminPublicBaseUrl : rtrim((string) ($baseUri ?? ''), '/');
$cronTaskCatalog = settingsCronTaskCatalog($adminPublicBaseUrl, $settings);
$cronTaskGroups = [];
foreach ($cronTaskCatalog as $taskKey => $taskMeta) {
    $groupName = trim((string) ($taskMeta['group'] ?? 'Diger'));
    if (!isset($cronTaskGroups[$groupName])) {
        $cronTaskGroups[$groupName] = [];
    }
    $taskMeta['task_key'] = $taskKey;
    $cronTaskGroups[$groupName][] = $taskMeta;
}
$cronJobKeys = array_values(array_unique(array_filter(array_map(
    static fn (array $task): string => trim((string) ($task['job_key'] ?? '')),
    array_values($cronTaskCatalog)
))));
$cronRunSnapshots = settingsCronRunSnapshots($pdo, $cronJobKeys);
$cronStatusLabel = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'success' => 'Basarili',
        'warning' => 'Uyari',
        'error' => 'Hata',
        'skipped' => 'Atlandi',
        'missing' => 'Yok',
        default => strtoupper($status),
    };
};
$cronStatusBadgeClass = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'success' => 'bg-success',
        'warning' => 'bg-warning text-dark',
        'error' => 'bg-danger',
        'skipped' => 'bg-secondary',
        default => 'bg-secondary',
    };
};
$cronAgeLabel = static function (?string $createdAt): string {
    if (!$createdAt) {
        return '-';
    }
    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return '-';
    }
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 120) {
        return 'az once';
    }
    if ($seconds < 3600) {
        return (int) floor($seconds / 60) . ' dk once';
    }
    if ($seconds < 86400) {
        return (int) floor($seconds / 3600) . ' saat once';
    }

    return (int) floor($seconds / 86400) . ' gun once';
};
$cronHealthRows = [];
$cronHealthOkCount = 0;
$cronLatestAt = null;
foreach ($cronTaskCatalog as $taskKey => $taskMeta) {
    $jobKey = trim((string) ($taskMeta['job_key'] ?? ''));
    $run = $cronRunSnapshots[$jobKey] ?? ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
    $status = (string) ($run['status'] ?? 'missing');
    $createdAt = isset($run['created_at']) ? (string) $run['created_at'] : null;
    $isHealthy = in_array($status, ['success'], true) && $createdAt !== null;
    if ($isHealthy) {
        $cronHealthOkCount++;
    }
    if ($createdAt !== null) {
        $ts = strtotime($createdAt);
        if ($ts !== false && ($cronLatestAt === null || $ts > strtotime((string) $cronLatestAt))) {
            $cronLatestAt = $createdAt;
        }
    }
    $cronHealthRows[] = [
        'task_key' => $taskKey,
        'title' => (string) ($taskMeta['title'] ?? $taskKey),
        'job_key' => $jobKey,
        'schedule_label' => (string) ($taskMeta['schedule_label'] ?? ''),
        'status' => $status,
        'created_at' => $createdAt,
        'healthy' => $isHealthy,
    ];
}
$cronHealthTotal = count($cronHealthRows);
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
                                                        <strong>Router tarafından ayrılan public rotalar</strong>
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
    <div class="alert alert-info border-info d-flex gap-3 align-items-start">
        <i class="bi bi-info-square-fill fs-4 mt-1"></i>
        <div>
            <strong>Görev yönetimi:</strong> Her cron görevi için CLI komutu, HTTP yedek komutu, URL endpoint'i ve manuel tetikleme aksiyonu tek kartta sunulur.
            HestiaCP/CPanel/Plesk tarafında CLI komutunu kullanın; PHP yolu boş bırakılırsa sistem uygun CLI yolunu otomatik seçer.
        </div>
    </div>

    <?php foreach ($cronTaskGroups as $groupName => $groupTasks): ?>
        <div class="admin-divider-block mt-4">
            <div class="admin-inline-head ui-panel__head">
                <i class="bi bi-folder2-open text-primary"></i>
                <?= htmlspecialchars((string) $groupName) ?> Görevleri
            </div>
            <div class="cron-task-grid ui-grid">
                <?php foreach ($groupTasks as $task): ?>
                    <?php
                    $taskKey = (string) ($task['task_key'] ?? '');
                    $jobKey = (string) ($task['job_key'] ?? '');
                    $run = $cronRunSnapshots[$jobKey] ?? ['found' => false, 'status' => 'missing', 'created_at' => null, 'context' => []];
                    $runStatus = (string) ($run['status'] ?? 'missing');
                    $runBadge = $cronStatusBadgeClass($runStatus);
                    $runAtRaw = isset($run['created_at']) ? (string) $run['created_at'] : null;
                    $runAt = $runAtRaw ? date('d.m.Y H:i:s', strtotime($runAtRaw) ?: time()) : '-';
                    $runAgo = $cronAgeLabel($runAtRaw);
                    ?>
                    <article class="cron-task-card ui-surface">
                        <div class="cron-task-head">
                            <div class="cron-task-title-wrap">
                                <h4><?= htmlspecialchars((string) ($task['title'] ?? $taskKey)) ?></h4>
                                <small><?= htmlspecialchars((string) ($task['description'] ?? '')) ?></small>
                            </div>
                            <span class="badge <?= htmlspecialchars($runBadge) ?>"><?= htmlspecialchars($cronStatusLabel($runStatus)) ?></span>
                        </div>

                        <div class="cron-task-meta">
                            <span><strong>Plan:</strong> <?= htmlspecialchars((string) ($task['schedule'] ?? '-')) ?></span>
                            <span><strong>Öneri:</strong> <?= htmlspecialchars((string) ($task['schedule_label'] ?? '-')) ?></span>
                            <span><strong>Son Çalışma:</strong> <?= htmlspecialchars($runAt) ?> (<?= htmlspecialchars($runAgo) ?>)</span>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">CLI Komutu</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars((string) ($task['cli'] ?? '')) ?>" readonly data-ui-select-on-focus>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                            </div>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">HTTP Yedek Komutu</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars((string) ($task['http'] ?? '')) ?>" readonly data-ui-select-on-focus>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                            </div>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">URL Endpoint</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input font-monospace bg-light" value="<?= htmlspecialchars((string) ($task['url'] ?? '')) ?>" readonly data-ui-select-on-focus>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                                <a href="<?= htmlspecialchars((string) ($task['url'] ?? '')) ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Endpoint'i aç"><i class="bi bi-box-arrow-up-right"></i></a>
                            </div>
                        </div>

                        <div class="cron-task-actions">
                            <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" form="cron-trigger-<?= htmlspecialchars($taskKey) ?>">
                                <i class="bi bi-play-fill"></i> Tetikle
                            </button>
                            <a href="logs.php?view=cron&cron_job=<?= urlencode($jobKey) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">
                                <i class="bi bi-card-list"></i> Cron Logları
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($cronTabId === 'cron-tab-health'): ?>
    <?php
    $healthTone = $cronHealthTotal > 0 && $cronHealthOkCount === $cronHealthTotal ? 'success' : ($cronHealthOkCount > 0 ? 'warning' : 'danger');
    $healthTitle = $healthTone === 'success'
        ? 'Tum cron gorevleri saglikli gorunuyor'
        : ($healthTone === 'warning' ? 'Bazi cron gorevleri kontrol edilmeli' : 'Cron gorevlerinde acil kontrol gerekiyor');
    ?>
    <div class="alert alert-<?= htmlspecialchars($healthTone) ?> border-<?= htmlspecialchars($healthTone) ?> d-flex gap-3 align-items-start mt-2">
        <i class="bi <?= $healthTone === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-4"></i>
        <div>
            <strong><?= htmlspecialchars($healthTitle) ?></strong><br>
            Toplam <?= (int) $cronHealthTotal ?> görevden <?= (int) $cronHealthOkCount ?> tanesi son kaydına göre başarılı.
            <?php if ($cronLatestAt): ?>
                Son cron hareketi: <?= htmlspecialchars(date('d.m.Y H:i:s', strtotime((string) $cronLatestAt) ?: time())) ?>.
            <?php else: ?>
                Henüz cron kaydı görünmüyor.
            <?php endif; ?>
            <br><a href="logs.php?view=cron">Cron loglarını aç</a>
        </div>
    </div>

    <div class="table-responsive mt-3">
        <table class="ui-admin-table w-100">
            <thead>
                <tr>
                    <th>Görev</th>
                    <th>İş Anahtarı</th>
                    <th>Plan</th>
                    <th>Durum</th>
                    <th>Son Çalışma</th>
                    <th>Log</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cronHealthRows as $healthRow): ?>
                    <?php
                    $rowStatus = (string) ($healthRow['status'] ?? 'missing');
                    $rowBadge = $cronStatusBadgeClass($rowStatus);
                    $rowAtRaw = isset($healthRow['created_at']) ? (string) $healthRow['created_at'] : null;
                    $rowAt = $rowAtRaw ? date('d.m.Y H:i:s', strtotime($rowAtRaw) ?: time()) : '-';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($healthRow['title'] ?? '')) ?></td>
                        <td><code><?= htmlspecialchars((string) ($healthRow['job_key'] ?? '')) ?></code></td>
                        <td><?= htmlspecialchars((string) ($healthRow['schedule_label'] ?? '-')) ?></td>
                        <td><span class="badge <?= htmlspecialchars($rowBadge) ?>"><?= htmlspecialchars($cronStatusLabel($rowStatus)) ?></span></td>
                        <td><?= htmlspecialchars($rowAt) ?><?php if ($rowAtRaw): ?> <small>(<?= htmlspecialchars($cronAgeLabel($rowAtRaw)) ?>)</small><?php endif; ?></td>
                        <td><a href="logs.php?view=cron&cron_job=<?= urlencode((string) ($healthRow['job_key'] ?? '')) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Aç</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
                                                            <?php if ($key === 'cron_php_binary'): ?>
                                                                <?php
                                                                $cronPhpBinaryValue = trim(str_replace(["\r", "\n"], '', (string) ($settings[$key] ?? '')));
                                                                $cronPhpBinaryIsAuto = function_exists('adminCronPhpBinaryIsAutoValue')
                                                                    ? adminCronPhpBinaryIsAutoValue($cronPhpBinaryValue)
                                                                    : $cronPhpBinaryValue === '';
                                                                $cronPhpBinaryResolved = function_exists('settingsCronPhpBinary')
                                                                    ? settingsCronPhpBinary($settings)
                                                                    : ($cronPhpBinaryValue !== '' ? $cronPhpBinaryValue : 'php');
                                                                $cronPhpBinaryCandidates = function_exists('adminCronPhpBinaryCandidates')
                                                                    ? adminCronPhpBinaryCandidates()
                                                                    : [];
                                                                $cronPhpBinaryListId = 'cron-php-binary-candidates';
                                                                ?>
                                                                <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="text" class="ui-admin-form-control bg-light font-monospace" list="<?= htmlspecialchars($cronPhpBinaryListId) ?>" value="<?= htmlspecialchars($cronPhpBinaryIsAuto ? '' : $cronPhpBinaryValue) ?>" placeholder="Boş bırakın: otomatik tespit edilsin">
                                                                <datalist id="<?= htmlspecialchars($cronPhpBinaryListId) ?>">
                                                                    <?php foreach ($cronPhpBinaryCandidates as $candidate): ?>
                                                                        <option value="<?= htmlspecialchars($candidate) ?>"></option>
                                                                    <?php endforeach; ?>
                                                                </datalist>
                                                                <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                                                                    <span class="badge <?= $cronPhpBinaryIsAuto ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $cronPhpBinaryIsAuto ? 'Otomatik mod' : 'Elle girildi' ?></span>
                                                                    <span class="small text-muted">Komutlarda kullanılacak: <code class="font-monospace"><?= htmlspecialchars($cronPhpBinaryResolved) ?></code></span>
                                                                </div>
                                                                <?php if ($cronPhpBinaryCandidates !== []): ?>
                                                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                                                        <?php foreach (array_slice($cronPhpBinaryCandidates, 0, 6) as $candidate): ?>
                                                                            <span class="badge text-bg-light border font-monospace"><?= htmlspecialchars($candidate) ?></span>
                                                                        <?php endforeach; ?>
                                                                        <?php if (count($cronPhpBinaryCandidates) > 6): ?>
                                                                            <span class="badge text-bg-light border">+<?= count($cronPhpBinaryCandidates) - 6 ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="small text-warning mt-2">Uygun bir PHP CLI yolu otomatik bulunamadı. İsterseniz tam yolu elle girin.</div>
                                                                <?php endif; ?>
                                                            <?php elseif ($definition['type'] === 'text'): ?>
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
                                    <?php if ($seoTabId === 'seo-tab-public-pages'): ?>
                                        <?php
                                        $publicPageGroups = is_array($seoPublicPageGroups) ? $seoPublicPageGroups : [];
                                        $publicPageCount = 0;
                                        foreach ($publicPageGroups as $publicPageGroup) {
                                            $publicPageCount += !empty($publicPageGroup['pages']) && is_array($publicPageGroup['pages'])
                                                ? count($publicPageGroup['pages'])
                                                : 0;
                                        }
                                        ?>
                                        <div class="admin-section-block ui-section seo-public-pages-intro">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-window-stack"></i>
                                                <span class="admin-inline-title">Public Sayfa Presetleri</span>
                                            </div>
                                            <div class="admin-section-desc">
                                                Başlık ve açıklama alanları boş bırakılırsa sayfa kendi varsayılan SEO katmanını kullanır. Noindex anahtarı, o sayfanın arama motorlarına açık olup olmadığını belirler; noindex açık sayfalar sitemap'ten otomatik düşer.
                                            </div>
                                            <div class="seo-public-pages-token-row">
                                                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-files"></i><?= (int) $publicPageCount ?> sayfa</span>
                                                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-tag"></i>{{page_title}}</span>
                                                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-tag"></i>{{site_name}}</span>
                                                <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-tag"></i>{{page_description}}</span>
                                            </div>
                                        </div>

                                        <?php if (!empty($publicPageGroups) && function_exists('seoPublicPagePresetForKey')): ?>
                                            <?php foreach ($publicPageGroups as $groupKey => $group): ?>
                                                <?php
                                                $groupPages = !empty($group['pages']) && is_array($group['pages']) ? $group['pages'] : [];
                                                $groupConfigured = 0;
                                                foreach ($groupPages as $pageKey => $pageMeta) {
                                                    $preset = seoPublicPagePresetForKey((string) $pageKey, $settings);
                                                    $defaultNoindex = !empty($pageMeta['default_noindex']);
                                                    $defaultNofollow = function_exists('seoPublicPageDefaultNofollow')
                                                        ? seoPublicPageDefaultNofollow((array) $pageMeta)
                                                        : $defaultNoindex;
                                                    $isCustomized = $preset['title'] !== ''
                                                        || $preset['description'] !== ''
                                                        || $preset['image'] !== ''
                                                        || (($preset['noindex'] === '1') !== $defaultNoindex)
                                                        || (($preset['nofollow'] === '1') !== $defaultNofollow);
                                                    if ($isCustomized) {
                                                        $groupConfigured++;
                                                    }
                                                }
                                                ?>
                                                <div class="admin-section-block ui-section seo-public-pages-group">
                                                    <div class="admin-inline-head ui-panel__head seo-public-pages-group-head">
                                                        <div class="seo-public-pages-group-meta">
                                                            <span class="admin-inline-title"><?= htmlspecialchars((string) ($group['title'] ?? $groupKey)) ?></span>
                                                            <?php if (!empty($group['description'])): ?>
                                                                <span class="seo-public-pages-group-desc"><?= htmlspecialchars((string) $group['description']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="seo-public-pages-group-badges">
                                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-list-ul"></i><?= count($groupPages) ?> sayfa</span>
                                                            <span class="ui-admin-badge <?= $groupConfigured > 0 ? 'ui-admin-badge-primary' : 'ui-admin-badge-secondary' ?>">
                                                                <i class="bi <?= $groupConfigured > 0 ? 'bi-sliders' : 'bi-check2-circle' ?>"></i>
                                                                <?= $groupConfigured > 0 ? $groupConfigured . ' özel' : 'Varsayılan' ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="seo-public-pages-grid">
                                                        <?php foreach ($groupPages as $pageKey => $pageMeta): ?>
                                                            <?php
                                                            $preset = seoPublicPagePresetForKey((string) $pageKey, $settings);
                                                            $defaultNoindex = !empty($pageMeta['default_noindex']);
                                                            $defaultNofollow = function_exists('seoPublicPageDefaultNofollow')
                                                                ? seoPublicPageDefaultNofollow((array) $pageMeta)
                                                                : $defaultNoindex;
                                                            $isCustomized = $preset['title'] !== ''
                                                                || $preset['description'] !== ''
                                                                || $preset['image'] !== ''
                                                                || (($preset['noindex'] === '1') !== $defaultNoindex)
                                                                || (($preset['nofollow'] === '1') !== $defaultNofollow);
                                                            $pageLabel = (string) ($pageMeta['label'] ?? $pageKey);
                                                            $pageSummary = trim((string) ($pageMeta['summary'] ?? ''));
                                                            $pagePath = trim((string) ($pageMeta['path'] ?? ''));
                                                            $placeholders = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), (array) ($pageMeta['placeholders'] ?? []))));
                                                            $fieldIdBase = 'seo-public-' . preg_replace('~[^a-zA-Z0-9_-]+~', '-', (string) $pageKey);
                                                            $isNoindexLocked = function_exists('seoPublicPageIsNoindexLocked') ? seoPublicPageIsNoindexLocked((string) $pageKey) : false;
                                                            ?>
                                                            <section class="seo-public-page-card<?= ($preset['noindex'] ?? '0') === '1' ? ' is-noindex' : '' ?>" data-seo-public-page-card>
                                                                <div class="seo-public-page-card-head">
                                                                    <div class="seo-public-page-title">
                                                                        <strong><?= htmlspecialchars($pageLabel) ?></strong>
                                                                        <span><?= htmlspecialchars((string) $pageKey) ?></span>
                                                                    </div>
                                                                    <div class="seo-public-page-head-badges">
                                                                        <span class="ui-admin-badge <?= $preset['noindex'] === '1' ? 'ui-admin-badge-warning' : 'ui-admin-badge-success' ?>">
                                                                            <i class="bi <?= $preset['noindex'] === '1' ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                                                            <?= $preset['noindex'] === '1' ? 'Noindex' : 'Index' ?>
                                                                        </span>
                                                                        <span class="ui-admin-badge <?= $preset['nofollow'] === '1' ? 'ui-admin-badge-info' : 'ui-admin-badge-success' ?>">
                                                                            <i class="bi <?= $preset['nofollow'] === '1' ? 'bi-link-45deg' : 'bi-link' ?>"></i>
                                                                            <?= $preset['nofollow'] === '1' ? 'Nofollow' : 'Follow' ?>
                                                                        </span>
                                                                        <?php if ($isNoindexLocked): ?>
                                                                            <span class="ui-admin-badge ui-admin-badge-muted">
                                                                                <i class="bi bi-lock-fill"></i>
                                                                                Kilitli
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <span class="ui-admin-badge <?= $isCustomized ? 'ui-admin-badge-primary' : 'ui-admin-badge-muted' ?>">
                                                                            <i class="bi <?= $isCustomized ? 'bi-sliders' : 'bi-check2' ?>"></i>
                                                                            <?= $isCustomized ? 'Özel' : 'Varsayılan' ?>
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <?php if ($pagePath !== ''): ?>
                                                                    <div class="seo-public-page-path-row">
                                                                        <span class="seo-public-page-path"><?= htmlspecialchars($pagePath) ?></span>
                                                                        <a href="<?= htmlspecialchars($pagePath) ?>" target="_blank" rel="noopener noreferrer" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Sayfayı aç">
                                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if ($pageSummary !== ''): ?>
                                                                    <div class="seo-public-page-summary"><?= htmlspecialchars($pageSummary) ?></div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($placeholders)): ?>
                                                                    <div class="seo-public-page-placeholders">
                                                                        <?php foreach ($placeholders as $placeholder): ?>
                                                                            <code><?= htmlspecialchars('{{' . $placeholder . '}}') ?></code>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="seo-public-page-fields">
                                                                    <div class="seo-public-page-field seo-public-page-field-wide">
                                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($fieldIdBase . '-title') ?>">Meta Başlık</label>
                                                                        <input id="<?= htmlspecialchars($fieldIdBase . '-title') ?>" type="text" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][title]" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($preset['title'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string) ($pageMeta['placeholder_title'] ?? '')) ?>">
                                                                    </div>

                                                                    <div class="seo-public-page-field seo-public-page-field-wide">
                                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($fieldIdBase . '-description') ?>">Meta Açıklama</label>
                                                                        <textarea id="<?= htmlspecialchars($fieldIdBase . '-description') ?>" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][description]" rows="3" class="ui-admin-form-control"><?= htmlspecialchars((string) ($preset['description'] ?? '')) ?></textarea>
                                                                    </div>

                                                                    <div class="seo-public-page-field seo-public-page-field-wide">
                                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($fieldIdBase . '-image') ?>">OG Görseli</label>
                                                                        <input id="<?= htmlspecialchars($fieldIdBase . '-image') ?>" type="text" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][image]" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($preset['image'] ?? '')) ?>" placeholder="https://...">
                                                                    </div>

                                                                    <div class="seo-public-page-field seo-public-page-field-wide">
                                                                        <label class="ui-admin-switch seo-public-page-switch">
                                                                            <input type="checkbox" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][noindex]" value="1" <?= ($preset['noindex'] ?? '0') === '1' ? 'checked' : '' ?><?= $isNoindexLocked ? ' disabled aria-disabled="true"' : '' ?> data-seo-public-page-noindex>
                                                                            <span class="ui-admin-switch-label">Arama motorlarından gizle</span>
                                                                        </label>
                                                                        <?php if ($isNoindexLocked): ?>
                                                                            <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                                                                                <i class="bi bi-lock-fill"></i>
                                                                                Bu sayfanın noindex durumu sistem tarafından kilitlidir.
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <div class="seo-public-page-field seo-public-page-field-wide">
                                                                        <label class="ui-admin-switch seo-public-page-switch">
                                                                            <input type="checkbox" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][nofollow]" value="1" <?= ($preset['nofollow'] ?? '0') === '1' ? 'checked' : '' ?> data-seo-public-page-nofollow>
                                                                            <span class="ui-admin-switch-label">Nofollow yap</span>
                                                                        </label>
                                                                        <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                                                                            <i class="bi bi-link-45deg"></i>
                                                                            Arama motorlarının linkleri takip etmesini kapatır.
                                                                        </div>
                                                                    </div>

                                                                    <div class="seo-public-page-field seo-public-page-field-wide seo-public-page-sitemap-row<?= ($preset['noindex'] ?? '0') === '1' ? ' is-disabled' : '' ?>" data-seo-public-page-sitemap-row>
                                                                        <div class="seo-public-page-sitemap-head">
                                                                            <label class="ui-admin-switch seo-public-page-switch mb-0">
                                                                                <input type="checkbox" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][sitemap_include]" value="1" <?= ($preset['sitemap_include'] ?? '1') === '1' ? 'checked' : '' ?> data-seo-public-page-sitemap>
                                                                                <span class="ui-admin-switch-label">Site haritasına ekle</span>
                                                                            </label>
                                                                            <div class="small text-muted d-flex align-items-center gap-1 seo-public-page-sitemap-note">
                                                                                <i class="bi bi-diagram-3"></i>
                                                                                Noindex açıkken bu sayfa sitemap'ten otomatik dışlanır.
                                                                            </div>
                                                                        </div>

                                                                        <div class="seo-public-page-priority-wrap">
                                                                            <label class="ui-admin-form-label mb-0" for="<?= htmlspecialchars($fieldIdBase . '-priority') ?>">Öncelik (Priority):</label>
                                                                            <select id="<?= htmlspecialchars($fieldIdBase . '-priority') ?>" name="seo_public_pages[<?= htmlspecialchars((string) $pageKey) ?>][sitemap_priority]" class="ui-admin-form-select seo-public-page-priority-select">
                                                                                <?php foreach (['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1'] as $p): ?>
                                                                                    <option value="<?= $p ?>" <?= ($preset['sitemap_priority'] ?? '0.5') === $p ? 'selected' : '' ?>><?= $p ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </section>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="admin-divider-block">
                                                <div class="admin-inline-head ui-panel__head">
                                                    <i class="bi bi-exclamation-triangle"></i>Public sayfa kataloğu hazır değil
                                                </div>
                                                <div class="admin-section-desc">
                                                    Public sayfa presetleri şu anda yüklenemedi. Lütfen SEO yardımcılarının ve route kataloğunun doğru yüklendiğini kontrol edin.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($seoTabId === 'seo-tab-sitemap'): ?>
                                        <!-- Site Haritası Yönlendirme Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-gear"></i>
                                                <span class="admin-inline-title">Site Haritası Yönlendirme</span>
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

                                        <!-- XML Site Haritası Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-diagram-3"></i>
                                                <span class="admin-inline-title">XML Site Haritası</span>
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

                                        <!-- Görsel Site Haritası Ayarları -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-images"></i>
                                                <span class="admin-inline-title">Görsel Site Haritası</span>
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

                                        <!-- Site haritası URL'leri -->
                                        <div class="admin-divider-block">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-link-45deg"></i>Site Haritası & Robots URL'leri
                                            </div>
                                            <div class="admin-settings-grid-sm ui-grid">
                                                <div>
                                                    <label class="ui-admin-form-label">Site Haritası İndeksi</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Aç"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Konu Site Haritası</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/topic-sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/topic-sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Aç"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Görsel Site Haritası</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/image-sitemap.xml" readonly>
                                                        <a href="<?= $baseUri ?>/image-sitemap.xml" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Aç"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="ui-admin-form-label">Robots.txt</label>
                                                    <div class="admin-inline-control">
                                                        <input type="text" class="ui-admin-form-control admin-muted-input" value="<?= htmlspecialchars($adminPublicBaseUrl) ?>/robots.txt" readonly>
                                                        <a href="<?= $baseUri ?>/robots.txt" target="_blank" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Aç"><i class="bi bi-box-arrow-up-right"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($seoTabId === 'seo-tab-structured'): ?>
                                        <!-- Yapısal Veri İşaretlemeleri -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-diagram-2"></i>
                                                <span class="admin-inline-title">Schema.org İşaretlemeleri</span>
                                            </div>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php
                                                $schemaKeys = ['structured_data_category', 'structured_data_profile', 'schema_organization_name', 'schema_organization_logo', 'schema_site_search', 'schema_breadcrumbs'];
                                                foreach ($schemaKeys as $key):
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

                                        <!-- Özel Kodlar -->
                                        <div class="admin-section-block ui-section">
                                            <div class="admin-inline-head ui-panel__head">
                                                <i class="bi bi-code-slash"></i>
                                                <span class="admin-inline-title">Özel Kodlar</span>
                                            </div>
                                            <div class="admin-settings-grid ui-grid">
                                                <?php
                                                $codeKeys = ['structured_data', 'custom_head_code'];
                                                foreach ($codeKeys as $key):
                                                    if (!isset($definitions[$key])) continue;
                                                    $definition = $definitions[$key];
                                                ?>
                                                    <div class="admin-field-wide">
                                                        <label class="ui-admin-form-label" for="<?= htmlspecialchars($key) ?>">
                                                            <?= htmlspecialchars($definition['label']) ?>
                                                            <?php if (!empty($definition['tooltip'])): ?>
                                                                <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= htmlspecialchars($definition['tooltip']) ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <textarea id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" rows="5" class="ui-admin-form-control ui-admin-code-editor"><?= htmlspecialchars($settings[$key] ?? '') ?></textarea>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <!-- Diğer SEO Alt Sekmeleri -->
                                        <?php if (false && $seoTabId === 'seo-tab-index'): ?>
                                            <div class="admin-divider-block">
                                                <div class="admin-inline-head ui-panel__head">
                                                    <i class="bi bi-info-circle"></i>İndeksleme Notları
                                                </div>
                                                <div class="admin-section-desc">
                                                    Giriş, kayıt ve parola akışları sistem tarafında noindex olarak kilitlidir.
                                                    Bu sekme; ar?iv ve sayfalama gibi teknik indeksleme kurallar?n? y?netir.
                                                    Public sayfaların meta ve noindex ayarları yeni "Public Sayfalar" sekmesindedir.
                                                </div>
                                            </div>
                                        <?php endif; ?>
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
                        <?php elseif ($id === 'email'): ?>
                            <div class="settings-subtabs" data-settings-subtabs>
                                <?php $emailFirst = true; foreach ($emailGroups as $emailTabId => $emailGroup): ?>
                                    <button type="button" class="settings-subtab-link<?= $emailFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($emailTabId) ?>" data-settings-subtab-scope="email">
                                        <i class="bi <?= htmlspecialchars((string) ($emailGroup['icon'] ?? 'bi-envelope')) ?> me-1"></i><?= htmlspecialchars((string) ($emailGroup['title'] ?? $emailTabId)) ?>
                                    </button>
                                <?php $emailFirst = false; endforeach; ?>
                            </div>

                            <?php $emailFirst = true; foreach ($emailGroups as $emailTabId => $emailGroup): ?>
                                <div class="route-filter-subtab-panel settings-subtab-panel admin-card admin-card-spaced<?= $emailFirst ? ' is-active' : '' ?> ui-panel" id="<?= htmlspecialchars($emailTabId) ?>" data-settings-subtab-panel="<?= htmlspecialchars($emailTabId) ?>" data-settings-subtab-scope="email">
                                    <div class="card-body ui-panel__body">
                                        <?php if (!empty($emailGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($emailGroup['description'])) ?></div>
                                        <?php endif; ?>

                                        <?php if ($emailTabId === 'email-tab-settings'): ?>
                                            <?= adminRenderSettingsGrid($definitions, $settings, 'email', (array) ($emailGroup['keys'] ?? [])) ?>
                                        <?php elseif ($emailTabId === 'email-tab-test'): ?>
                                            <?php $currentAdminEmail = trim((string) ($_SESSION['_auth_user_email'] ?? '')); ?>
                                            <div class="ui-admin-alert ui-admin-alert-info ui-alert ui-admin-alert-spaced">
                                                <i class="bi bi-info-circle"></i>
                                                <div>
                                                    <strong>Test gönderimi</strong><br>
                                                    Bu araç, üstteki SMTP alanlarında yaptığınız mevcut değişiklikleri de kullanır ve ayarları kaydetmeden tek başına test gönderir.
                                                </div>
                                            </div>

                                            <div class="admin-settings-grid ui-grid">
                                                <div class="admin-field-wide">
                                                    <label class="ui-admin-form-label" for="email_test_recipient">Test E-Posta Adresi</label>
                                                    <input id="email_test_recipient" name="email_test_recipient" type="email" class="ui-admin-form-control" value="<?= htmlspecialchars($currentAdminEmail) ?>" placeholder="test@domain.com" autocomplete="email">
                                                </div>
                                                <div class="admin-field-wide">
                                                    <label class="ui-admin-form-label" for="email_test_message">Test Mesajı</label>
                                                    <textarea id="email_test_message" name="email_test_message" rows="6" class="ui-admin-form-control">Bu bir test e-postasıdır. Gönderim yapıldıktan sonra gelen kutusunu ve SMTP loglarını kontrol edin.</textarea>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end mt-3">
                                                <button type="submit" name="action" value="send_email_test" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm">
                                                    <i class="bi bi-send-check"></i> Test Gönder
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                                $accountEmailCatalog = \App\Engine\Email\AccountEmailService::catalog();
                                                $accountEmailBehaviorKeys = [
                                                    'account_email_system_enabled',
                                                    'account_email_verification_enabled',
                                                    'account_email_verification_required',
                                                    'account_email_verification_ttl_minutes',
                                                    'account_email_verification_resend_cooldown_minutes',
                                                ];
                                            ?>
                                            <div class="ui-admin-alert ui-admin-alert-info ui-alert ui-admin-alert-spaced">
                                                <i class="bi bi-info-circle"></i>
                                                <div>
                                                    <strong>Tek kaynak, duplicate ayar yok</strong><br>
                                                    SMTP ve gönderen bilgileri E-posta Ayarları sekmesinden kullanılır. Yorum, mesaj ve moderasyon e-postaları <a href="notifications.php?tab=templates">Bildirim Şablonları</a> ekranında kalır.
                                                </div>
                                            </div>
                                            <section class="account-email-behavior admin-section-block ui-section">
                                                <div class="admin-inline-head ui-panel__head"><i class="bi bi-shield-check"></i><span class="admin-inline-title">Hesap E-posta Davranışı</span></div>
                                                <?= adminRenderSettingsGrid($definitions, $settings, 'email', $accountEmailBehaviorKeys) ?>
                                            </section>
                                            <div class="account-email-template-list">
                                                <?php foreach ($accountEmailCatalog as $accountTemplateKey => $accountTemplate): ?>
                                                    <?php
                                                        $enabledKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'enabled');
                                                        $subjectKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'subject');
                                                        $bodyKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'body');
                                                        $enabledValue = (string) ($settings[$enabledKey] ?? $accountTemplate['enabled']);
                                                        $subjectValue = (string) ($settings[$subjectKey] ?? $accountTemplate['subject']);
                                                        $bodyValue = (string) ($settings[$bodyKey] ?? $accountTemplate['body']);
                                                    ?>
                                                    <section class="account-email-template-card admin-card ui-panel" data-account-email-card="<?= htmlspecialchars($accountTemplateKey) ?>">
                                                        <div class="card-header ui-panel__head">
                                                            <div><strong><?= htmlspecialchars($accountTemplate['label']) ?></strong><small class="d-block"><?= htmlspecialchars($accountTemplate['description']) ?></small></div>
                                                            <label class="ui-admin-switch">
                                                                <input type="checkbox" name="<?= htmlspecialchars($enabledKey) ?>" value="1" <?= $enabledValue === '1' ? 'checked' : '' ?>>
                                                                <span class="ui-admin-switch-label">Aktif</span>
                                                            </label>
                                                        </div>
                                                        <div class="card-body ui-panel__body">
                                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($subjectKey) ?>">E-posta Konusu</label>
                                                            <input id="<?= htmlspecialchars($subjectKey) ?>" name="<?= htmlspecialchars($subjectKey) ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($subjectValue) ?>" required>
                                                            <label class="ui-admin-form-label mt-3" for="<?= htmlspecialchars($bodyKey) ?>">HTML E-posta İçeriği</label>
                                                            <textarea id="<?= htmlspecialchars($bodyKey) ?>" name="<?= htmlspecialchars($bodyKey) ?>" class="ui-admin-form-control account-email-body" rows="10" required><?= htmlspecialchars($bodyValue) ?></textarea>
                                                            <div class="notification-template-token-list mt-2">
                                                                <?php foreach (\App\Engine\Email\AccountEmailService::allowedVariables() as $variable): ?>
                                                                    <button type="button" class="notification-template-token account-email-token" data-token="{{<?= htmlspecialchars($variable) ?>}}">{{<?= htmlspecialchars($variable) ?>}}</button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm mt-2 account-email-preview-button"><i class="bi bi-eye"></i> Önizlemeyi Aç</button>
                                                            <div class="account-email-preview mt-3" data-account-email-preview></div>
                                                            <div class="admin-settings-grid ui-grid mt-3">
                                                                <div class="admin-field-wide">
                                                                    <label class="ui-admin-form-label" for="account_email_test_recipient_<?= htmlspecialchars($accountTemplateKey) ?>">Test Alıcısı</label>
                                                                    <input id="account_email_test_recipient_<?= htmlspecialchars($accountTemplateKey) ?>" class="ui-admin-form-control" type="email" value="<?= htmlspecialchars((string) ($_SESSION['_auth_user_email'] ?? '')) ?>">
                                                                </div>
                                                            </div>
                                                            <textarea class="ui-admin-hidden account-email-default-body"><?= htmlspecialchars((string) $accountTemplate['body']) ?></textarea>
                                                            <div class="d-flex justify-content-between mt-3">
                                                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm account-email-reset" data-default-subject="<?= htmlspecialchars((string) $accountTemplate['subject']) ?>"><i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön</button>
                                                                <button type="submit" name="action" value="send_account_email_test" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" data-account-email-template="<?= htmlspecialchars($accountTemplateKey) ?>"><i class="bi bi-send-check"></i> Test Gönder</button>
                                                            </div>
                                                        </div>
                                                    </section>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php $emailFirst = false; endforeach; ?>
                        <?php elseif ($id === 'rate_limit'): ?>
                            <div class="ui-admin-alert ui-admin-alert-info ui-alert ui-admin-alert-spaced">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    <strong>Hizli Kullanim:</strong>
                                    Limit, pencere suresi icinde izin verilen maksimum istek sayisidir.
                                    Pencere (dakika), bu sayacin ne kadar sure sonra sifirlanacagini belirler.
                                    Ornek: Limit 5 + Pencere 15 = 15 dakikada en fazla 5 istek.
                                </div>
                            </div>
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
                                        <?php if (!empty($downloadGroup['sections']) && is_array($downloadGroup['sections'])): ?>
                                            <div class="download-access-settings-sections">
                                                <?php foreach ($downloadGroup['sections'] as $downloadSection): ?>
                                                    <section class="download-access-settings-section">
                                                        <header class="download-access-settings-section__header">
                                                            <span class="download-access-settings-section__icon" aria-hidden="true">
                                                                <i class="bi <?= htmlspecialchars((string) ($downloadSection['icon'] ?? 'bi-sliders')) ?>"></i>
                                                            </span>
                                                            <div>
                                                                <h3><?= htmlspecialchars((string) ($downloadSection['title'] ?? 'Ayarlar')) ?></h3>
                                                                <?php if (!empty($downloadSection['description'])): ?>
                                                                    <p><?= htmlspecialchars((string) $downloadSection['description']) ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </header>
                                                        <?= adminRenderSettingsGrid($definitions, $settings, 'downloads', (array) ($downloadSection['keys'] ?? []), 'admin-settings-grid download-access-settings-section__grid ui-grid') ?>
                                                    </section>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <?= adminRenderSettingsGrid($definitions, $settings, 'downloads', (array) ($downloadGroup['keys'] ?? [])) ?>
                                        <?php endif; ?>
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

<?php foreach ($cronTaskCatalog as $cronTaskKey => $cronTaskMeta): ?>
    <form method="post" action="settings.php#cron" id="cron-trigger-<?= htmlspecialchars((string) $cronTaskKey) ?>" class="ui-admin-hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="trigger_cron">
        <input type="hidden" name="cron_task" value="<?= htmlspecialchars((string) $cronTaskKey) ?>">
    </form>
<?php endforeach; ?>

<script src="<?= asset_url('admin/assets/settings-page-main.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
