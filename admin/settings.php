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
    'toast_notifications' => ['title' => 'Toast Bildirimleri', 'icon' => 'bi-chat-square-dots'],
    'email' => ['title' => 'E-posta', 'icon' => 'bi-envelope'],
    'rate_limit' => ['title' => 'İstek Sınırları', 'icon' => 'bi-speedometer2'],
    'performance' => ['title' => 'Performans', 'icon' => 'bi-lightning-charge'],
    'social_features' => ['title' => 'Sosyal Özellikler', 'icon' => 'bi-people'],
    'popup_announcement' => ['title' => 'Açılır Duyuru', 'icon' => 'bi-megaphone'],
    'cron' => ['title' => 'Cron & Görevler', 'icon' => 'bi-clock-history'],
];

$sectionDescriptions = [
    'general' => 'Site adı, dil, iletişim adresi, listeleme ve bakım modu gibi temel site davranışlarını yönetin.',
    'user_system' => 'Giriş, kayıt ve spam kurallarını tek merkezden yönetin. Form davranışı, parola kuralları, yasaklı kullanıcı adları ve içerik filtreleri burada toplanır.',
    'seo' => 'Meta, canonical, sitemap, robots, public sayfa presetleri ve yapısal veri ayarlarını aynı SEO akışı içinde yönetin.',
    'moderation' => 'Rapor eşikleri, otomatik gizleme ve temel moderasyon davranışlarını kontrol edin.',
    'comments' => 'Yorum sistemi, uzunluk limitleri, reaksiyonlar, spam filtreleri ve şikayet davranışlarını tek merkezden yönetin.',
    'downloads' => 'İndirme kartları, dış bağlantı yönlendirme metinleri ve erişim kilidi davranışlarını yönetin.',
    'file_manager' => 'Dosya yükleme, izinli uzantılar, görsel optimizasyon, WebP, thumbnail ve filigran kurallarını yönetin.',
    'user_uploads' => 'Kullanıcı mod gönderimi, düzenleme onayı, zorunlu alanlar, medya kuralları ve otomatik içerik kontrollerini tek yerden yönetin.',
    'route_filters' => 'Konu ve kategori URL ön eklerini yönetin. Örnek: /konu/slug-id yerine /topic/slug-id veya /kategori/slug yerine /category/slug kullanabilirsiniz.',
    'rate_limit' => 'Her işlem için limit ve süre alanlarını birlikte okuyun. Limit, seçilen süre içinde kaç deneme, istek veya işlem yapılabileceğini; süre ise bu kuralın kaç dakika geçerli olduğunu belirler.',
    'email' => 'SMTP, gönderici bilgileri ve genel test gönderimini yönetin.',
    'performance' => 'Önbellekleme, GZIP sıkıştırma, CDN, lazy loading ve minifikasyon gibi performans optimizasyonlarını yönetin.',
    'social_features' => 'Sosyal medya bağlantıları ve kullanıcı etkileşimiyle ilgili sosyal özellikleri tek merkezden yönetin.',
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
    'comments-tab-spam' => [
        'title' => 'Spam Yönetimi',
        'icon' => 'bi-shield-exclamation',
        'description' => 'Yorum spam davranışını sade filtreler, açıklamalı kullanıcı mesajları ve tek eylem seçimiyle yönetin.',
        'sections' => [
            [
                'title' => 'Temel Koruma',
                'icon' => 'bi-shield-check',
                'description' => 'Spam filtresini açıp kapatın ve spam yakalandığında uygulanacak davranışı seçin.',
                'class' => 'comments-spam-section comments-spam-section--primary',
                'keys' => ['comment_spam_detection', 'comment_spam_violation_action'],
            ],
            [
                'title' => 'Yorum Kutusu Bilgisi',
                'icon' => 'bi-info-circle',
                'description' => 'Yorum yazan kullanıcılara spam ve kalite kurallarını kısa bir notla hatırlatın.',
                'class' => 'comments-spam-section comments-spam-section--form-info',
                'keys' => ['comment_form_info_text'],
            ],
            [
                'title' => 'Filtreler',
                'icon' => 'bi-toggles',
                'description' => 'Tek kelimeleri, cümle içinde geçen ifadeleri, kısa içerikleri ve tamamen büyük harf yazımını denetleyin.',
                'class' => 'comments-spam-section comments-spam-section--quick',
                'keys' => [
                    'comment_spam_exact_terms',
                    'comment_spam_contains_terms',
                    'comment_spam_min_alnum_count',
                    'comment_spam_block_uppercase',
                ],
            ],
            [
                'title' => 'Anlamsız Kelime Engelleme',
                'icon' => 'bi-regex',
                'description' => 'Rastgele harf/rakam dizilerini, tekrarlı kalıpları, klavye dizilerini ve uzun sayı yorumlarını sade bir aç/kapat filtresiyle yakalayın.',
                'class' => 'comments-spam-section comments-spam-section--nonsense',
                'keys' => ['comment_spam_nonsense_words_enabled'],
            ],
            [
                'title' => 'Tekrarlı Yorum Kontrolü',
                'icon' => 'bi-copy',
                'description' => 'Aynı kullanıcının belirlenen dakika içinde aynı yorumu tekrar göndermesini spam sebebi olarak yakalayın.',
                'class' => 'comments-spam-section comments-spam-section--duplicate',
                'keys' => ['comment_spam_duplicate_enabled', 'comment_spam_duplicate_minutes'],
            ],
            [
                'title' => 'Spam Muafiyetleri',
                'icon' => 'bi-person-check',
                'description' => 'Belirli kullanıcı adları ve gruplar tüm yorum spam kontrollerinden muaf tutulur.',
                'class' => 'comments-spam-section comments-spam-section--exemptions',
                'keys' => ['comment_spam_exempt_usernames', 'comment_spam_exempt_groups'],
            ],
        ],
    ],
    'comments-tab-moderation' => [
        'title' => 'Moderasyon & Şikayet',
        'icon' => 'bi-shield-check',
        'description' => 'Şikayet sistemi ve şikayet sayısına bağlı otomatik gizleme.',
        'keys' => ['comment_report_enabled', 'comment_auto_hide_reports']
    ]
];

$downloadGroups = [
    'downloads-tab-general' => [
        'title' => 'Mevcut Ayarlar',
        'icon' => 'bi-download',
        'description' => 'Konu detayındaki indirme kartları ve sayaç davranışı.',
        'keys' => [
            'download_countdown_seconds',
            'download_redirect_countdown_seconds',
            'download_ready_text',
            'download_wait_text',
            'download_done_text',
            'download_security_notice_text',
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
                'title' => 'İndirme Muafiyetleri',
                'icon' => 'bi-person-check',
                'description' => 'Belirli kullanıcı adları ve grupları, seçtiğiniz kapsam dahilindeki indirme kilitleri ve sınırlarından muaf tutun.',
                'class' => 'download-access-settings-section--exemptions',
                'keys' => [
                    'download_exempt_usernames',
                    'download_exempt_groups',
                    'download_exempt_scopes',
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

$userUploadGroups = [
    'user-upload-tab-workflow' => [
        'title' => 'Akış',
        'icon' => 'bi-shield-check',
        'description' => 'Mod gönderim sayfası, yeni gönderi onayı ve kullanıcı düzenleme onayı.',
        'keys' => [
            'user_upload_enabled',
            'user_upload_require_approval',
            'user_upload_default_status',
            'topic_user_edit_requires_approval',
        ],
    ],
    'user-upload-tab-quality' => [
        'title' => 'Kalite',
        'icon' => 'bi-card-checklist',
        'description' => 'Başlık, açıklama ve yinelenen içerik kontrolleri.',
        'keys' => [
            'topic_require_excerpt',
            'user_upload_min_title_length',
            'user_upload_max_title_length',
            'user_upload_min_content_length',
            'user_upload_block_duplicate_titles',
        ],
    ],
    'user-upload-tab-required' => [
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
    'user-upload-tab-media' => [
        'title' => 'Görsel Kuralları',
        'icon' => 'bi-images',
        'description' => 'Kapak ve galeri görsellerinin boyut, uzantı ve adet sınırları.',
        'keys' => [
            'user_upload_max_images',
            'user_upload_cover_max_size_mb',
            'user_upload_gallery_max_size_mb',
            'user_upload_allowed_image_ext',
            'user_upload_image_min_width',
            'user_upload_image_min_height',
            'user_upload_image_max_width',
            'user_upload_image_max_height',
        ],
    ],
    'user-upload-tab-video' => [
        'title' => 'Video ve Linkler',
        'icon' => 'bi-play-btn',
        'description' => 'Tanıtım videosu ve izin verilen video sağlayıcıları.',
        'keys' => [
            'user_upload_allow_video_url',
            'user_upload_allowed_video_hosts',
            'user_upload_max_size_mb',
        ],
    ],
    'user-upload-tab-form' => [
        'title' => 'Form Davranışı',
        'icon' => 'bi-layout-text-sidebar',
        'description' => 'Wizard arayüzü, gönderim sonrası kilitleme ve kullanıcı takip kutusu davranışları.',
        'keys' => [
            'user_upload_default_content_align',
            'user_upload_submission_notice',
            'user_upload_lock_after_submit',
            'user_upload_wizard_enabled',
            'user_upload_allow_step_skip',
            'user_upload_show_profile_followup',
            'user_upload_show_profile_button',
        ],
    ],
    'user-upload-tab-words' => [
        'title' => 'Kelime Filtresi',
        'icon' => 'bi-filter-circle',
        'description' => 'Başlık ve açıklamada otomatik yasak kelime kontrolü.',
        'keys' => [
            'content_moderation_enabled',
            'content_moderation_blocked_words',
            'content_moderation_blocked_words_action',
            'content_moderation_blocked_words_message',
            'content_moderation_flag_note',
        ],
    ],
];

$userSystemGroups = [
    'user-system-tab-login' => [
        'title' => 'Giriş',
        'icon' => 'bi-box-arrow-in-right',
        'description' => 'Giriş kimliği, oturum davranışı ve bu cihazda oturum açık tutma tercihini yönetin.',
        'sections' => [
            [
                'title' => 'Giriş Kimliği',
                'icon' => 'bi-person-badge',
                'badge' => 'Kimlik',
                'summary_mode' => 'login_identity',
                'description' => 'Kullanıcının giriş ekranında hangi bilgiyle oturum açacağını belirleyin.',
                'keys' => ['login_identifier_mode'],
            ],
            [
                'title' => 'Oturum Davranışı',
                'icon' => 'bi-hourglass-split',
                'badge' => 'Oturum',
                'summary_mode' => 'session_behavior',
                'description' => 'Normal oturum ve bu cihazda açık tutma seçeneklerinin süresini ve varsayılan davranışını yönetin.',
                'keys' => ['login_show_remember_session', 'login_remember_session_default', 'session_timeout_minutes', 'remember_session_timeout_minutes'],
            ],
        ],
    ],
    'user-system-tab-register' => [
        'title' => 'Kayıt',
        'icon' => 'bi-person-plus',
        'description' => 'Kayıt izni, kullanıcı adı boyutu, şifre politikası ve e-posta domain izinlerini yönetin.',
        'sections' => [
            [
                'title' => 'Kayıt Durumu',
                'icon' => 'bi-person-plus',
                'badge' => 'Ana Kural',
                'summary_mode' => 'registration_status',
                'description' => 'Yeni kullanıcı kayıtlarının açık veya kapalı olacağını belirleyin.',
                'keys' => ['allow_registration'],
            ],
            [
                'title' => 'Kullanıcı Adı Kuralları',
                'icon' => 'bi-type',
                'badge' => 'Min / Maks',
                'summary_mode' => 'username_length',
                'description' => 'Kayıt formunda kabul edilen kullanıcı adı uzunluk aralığını yönetin.',
                'keys' => ['register_username_min_length', 'register_username_max_length'],
            ],
            [
                'title' => 'Parola Kuralları',
                'icon' => 'bi-key',
                'badge' => 'Güvenlik',
                'summary_mode' => 'password_policy',
                'description' => 'Parola uzunluğu, karakter zorunlulukları ve geçerlilik süresini tek blokta düzenleyin.',
                'keys' => ['password_min_length', 'password_require_uppercase', 'password_require_numbers', 'password_require_special', 'password_expiry_days'],
            ],
            [
                'title' => 'E-posta Domain İzinleri',
                'icon' => 'bi-envelope-check',
                'badge' => 'İzin Listesi',
                'summary_mode' => 'email_allow_list',
                'description' => 'Liste doluysa sadece belirtilen e-posta domainleri kayıt olabilir.',
                'layout' => 'wide',
                'keys' => ['register_allowed_email_domains'],
            ],
        ],
    ],
    'user-system-tab-approvals' => [
        'title' => 'Kayıt Onayları',
        'icon' => 'bi-patch-check',
        'description' => 'Yeni hesapların otomatik mi yoksa yönetici onayıyla mı açılacağını ve e-posta doğrulama davranışını yönetin.',
        'sections' => [
            [
                'title' => 'Kayıt Onayları',
                'icon' => 'bi-patch-check',
                'badge' => 'Onay',
                'summary_mode' => 'registration_approval',
                'description' => 'Yeni kayıtların otomatik mi yoksa yönetici onayıyla mı açılacağını ve bekleme mesajını belirleyin.',
                'keys' => [
                    'registration_requires_admin_approval',
                    'registration_pending_message',
                ],
            ],
            [
                'title' => 'Şüpheli Kayıt Bildirimleri',
                'icon' => 'bi-shield-exclamation',
                'badge' => 'Alarm',
                'summary_mode' => 'suspicious_registration',
                'description' => 'Aynı IP üzerinden yoğun kayıt sinyali algılandığında yöneticilere bildirim ve e-posta gönderin.',
                'keys' => [
                    'registration_suspicious_alert_enabled',
                    'registration_suspicious_window_minutes',
                    'registration_suspicious_ip_threshold',
                    'registration_suspicious_cooldown_minutes',
                ],
            ],
            [
                'title' => 'Doğrulama Hatırlatma Cron',
                'icon' => 'bi-clock-history',
                'badge' => 'E-posta',
                'summary_mode' => 'email_verification',
                'description' => 'Doğrulama bekleyen hesaplara belirli aralıklarla yeniden doğrulama e-postası gönderin.',
                'keys' => [
                    'account_email_verification_enabled',
                    'account_email_verification_required',
                    'account_email_verification_ttl_minutes',
                    'account_email_verification_resend_cooldown_minutes',
                    'account_email_verification_reminder_enabled',
                    'account_email_verification_reminder_after_minutes',
                    'account_email_verification_reminder_batch_size',
                ],
            ],
        ],
    ],
    'user-system-tab-password-reset' => [
        'title' => 'Şifre Sıfırlama',
        'icon' => 'bi-key',
        'description' => 'Şifre sıfırlama bağlantısının geçerlilik süresini ve kullanıcıya gösterilecek davranışı yönetin.',
        'sections' => [
            [
                'title' => 'Bağlantı Geçerliliği',
                'icon' => 'bi-link-45deg',
                'badge' => 'Süre',
                'summary_mode' => 'password_reset_ttl',
                'description' => 'Şifremi unuttum bağlantısının kaç dakika geçerli kalacağını belirleyin.',
                'keys' => [
                    'password_reset_token_ttl_minutes',
                ],
            ],
        ],
    ],
    'user-system-tab-spam' => [
        'title' => 'Spam Yönetimi',
        'icon' => 'bi-shield-exclamation',
        'description' => 'Yasaklı kullanıcı adları, parçaları, küfür/argo kelimeler, anlamsız ifadeler ve e-posta alan adlarını tek yerden yönetin.',
        'sections' => [
            [
                'title' => 'Kullanıcı Adı Engelleri',
                'icon' => 'bi-person-slash',
                'badge' => 'Kullanıcı Adı',
                'summary_mode' => 'username_block_lists',
                'description' => 'Kayıt ve profil değişikliğinde birebir veya parça eşleşmesiyle engellenecek kullanıcı adlarını belirleyin.',
                'layout' => 'wide',
                'keys' => [
                    'spam_blocked_usernames',
                    'spam_blocked_username_fragments',
                ],
            ],
            [
                'title' => 'Kelime ve Desen Filtreleri',
                'icon' => 'bi-filter-circle',
                'badge' => 'Metin',
                'summary_mode' => 'text_block_lists',
                'description' => 'Kullanıcı adında geçerse engellenecek argo, anlamsız kelime ve klavye/desen kalıplarını yönetin.',
                'layout' => 'wide',
                'keys' => [
                    'spam_profanity_words',
                    'spam_meaningless_words',
                    'spam_meaningless_patterns',
                ],
            ],
            [
                'title' => 'E-posta Domain Engelleri',
                'icon' => 'bi-envelope-x',
                'badge' => 'Domain',
                'summary_mode' => 'email_block_list',
                'description' => 'Geçici veya istenmeyen e-posta domainleriyle kayıt açılmasını engelleyin.',
                'layout' => 'wide',
                'keys' => [
                    'spam_blocked_email_domains',
                ],
            ],
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
];

$rateLimitGroups = [
    'rate-tab-auth' => [
        'title' => 'Giriş ve Hesap Güvenliği',
        'icon' => 'bi-person-lock',
        'description' => 'Giriş, kayıt ve şifre sıfırlama işlemleri için aynı süre/limit mantığıyla çalışan IP bazlı güvenlik ayarları.',
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
        'description' => 'Arama, konu listeleme, mesaj, liderlik tablosu ve analitik API istekleri için süreye bağlı limitler.',
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
        'description' => 'Favori işlemleri, konu şikayetleri ve indirme sayacı için süreye bağlı işlem limitleri.',
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
        'description' => 'Kullanıcı şikayetlerini listeleme ve gönderme işlemleri için süreye bağlı limitler.',
        'keys' => [
            'api_user_reports_rate_limit',
            'api_user_reports_rate_window',
            'api_user_report_submit_rate_limit',
            'api_user_report_submit_rate_window',
        ],
    ],
    'rate-tab-ban-appeals' => [
        'title' => 'Ban İtirazları',
        'icon' => 'bi-shield-exclamation',
        'description' => 'Ban itiraz mesajlarında kullanıcı başına limit ve dakika penceresini birlikte belirleyin.',
        'keys' => [
            'ban_appeal_message_limit',
            'ban_appeal_message_cooldown_minutes',
        ],
    ],
    'rate-tab-comments' => [
        'title' => 'Yorum Akışı',
        'icon' => 'bi-chat-left-text',
        'description' => 'Yorum gönderme, mention arama, düzenleme, reaksiyon ve şikayet limitleri.',
        'keys' => [
            'comment_rate_max',
            'comment_rate_minutes',
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
        'description' => 'Kullanıcı mod gönderim sıklığını tek limit ve dakika penceresiyle belirleyin.',
        'keys' => [
            'user_upload_rate_limit',
            'user_upload_rate_window',
        ],
    ],
];

$rateLimitPairs = [
    'login_rate_limit' => [
        'window' => 'login_rate_window',
        'title' => 'Başarısız Giriş Denemesi',
        'action' => 'başarısız giriş denemesi',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen başarısız giriş denemelerine uygulanır.',
    ],
    'register_rate_limit' => [
        'window' => 'register_rate_window',
        'title' => 'Kayıt Denemesi',
        'action' => 'kayıt denemesi',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen kayıt denemelerine uygulanır.',
    ],
    'password_reset_rate_limit' => [
        'window' => 'password_reset_rate_window',
        'title' => 'Şifre Sıfırlama İsteği',
        'action' => 'şifre sıfırlama isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen şifre sıfırlama isteklerine uygulanır.',
    ],
    'search_rate_limit' => [
        'window' => 'search_rate_window',
        'title' => 'Arama İsteği',
        'action' => 'arama isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen arama isteklerine uygulanır.',
    ],
    'api_topics_rate_limit' => [
        'window' => 'api_topics_rate_window',
        'title' => 'Konu API İsteği',
        'action' => 'konu API isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen konu API isteklerine uygulanır.',
    ],
    'api_messages_rate_limit' => [
        'window' => 'api_messages_rate_window',
        'title' => 'Mesaj API İsteği',
        'action' => 'mesaj API isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen mesaj API isteklerine uygulanır.',
    ],
    'api_leaderboard_rate_limit' => [
        'window' => 'api_leaderboard_rate_window',
        'title' => 'Liderlik API İsteği',
        'action' => 'liderlik API isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen liderlik API isteklerine uygulanır.',
    ],
    'api_analytics_rate_limit' => [
        'window' => 'api_analytics_rate_window',
        'title' => 'Analitik API İsteği',
        'action' => 'analitik API isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Aynı IP adresinden gelen analitik API isteklerine uygulanır.',
    ],
    'api_favorite_rate_limit' => [
        'window' => 'api_favorite_rate_window',
        'title' => 'Favori İşlemi',
        'action' => 'favori işlemi',
        'scope' => 'IP bazlı',
        'scope_help' => 'Favori işlem kontrolü istek yapan IP adresine göre uygulanır.',
    ],
    'api_reports_rate_limit' => [
        'window' => 'api_reports_rate_window',
        'title' => 'Konu Şikayet Listeleme',
        'action' => 'konu şikayet listeleme isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Konu şikayet listeleme istekleri IP adresine göre uygulanır.',
    ],
    'api_report_submit_rate_limit' => [
        'window' => 'api_report_submit_rate_window',
        'title' => 'Konu Şikayet Gönderimi',
        'action' => 'konu şikayeti',
        'scope' => 'Kullanıcı/IP+e-posta',
        'scope_help' => 'Üyelerde kullanıcı hesabına, misafirlerde IP adresi ve e-posta birleşimine göre uygulanır.',
    ],
    'api_user_reports_rate_limit' => [
        'window' => 'api_user_reports_rate_window',
        'title' => 'Kullanıcı Şikayet Listeleme',
        'action' => 'kullanıcı şikayet listeleme isteği',
        'scope' => 'IP bazlı',
        'scope_help' => 'Kullanıcı şikayet listeleme istekleri IP adresine göre uygulanır.',
    ],
    'api_user_report_submit_rate_limit' => [
        'window' => 'api_user_report_submit_rate_window',
        'title' => 'Kullanıcı Şikayet Gönderimi',
        'action' => 'kullanıcı şikayeti',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
    ],
    'download_count_rate_limit' => [
        'window' => 'download_count_rate_window',
        'title' => 'İndirme Sayacı Artırma',
        'action' => 'sayaç artırma işlemi',
        'scope' => 'IP+kayıt bazlı',
        'scope_help' => 'Aynı IP adresi ve aynı indirme kaydı birleşimine göre uygulanır.',
    ],
    'comment_rate_max' => [
        'window' => 'comment_rate_minutes',
        'title' => 'Yorum Gönderimi',
        'action' => 'yorum gönderimi',
        'scope' => 'Kullanıcı/IP bazlı',
        'scope_help' => 'Üyelerde kullanıcı hesabına, misafirlerde IP adresine göre uygulanır.',
        'zero_limit_help' => '0 = yorum gönderim limiti kapalı.',
        'zero_summary' => 'Yorum gönderim limiti kapalı.',
    ],
    'comment_mention_rate_max' => [
        'window' => 'comment_mention_rate_window',
        'title' => 'Kullanıcı Etiketleme Araması',
        'action' => 'etiketleme araması',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
    ],
    'comment_edit_rate_max' => [
        'window' => 'comment_edit_rate_window',
        'title' => 'Yorum Düzenleme/Silme',
        'action' => 'yorum düzenleme veya silme işlemi',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
    ],
    'comment_reaction_rate_max' => [
        'window' => 'comment_reaction_rate_window',
        'title' => 'Yorum Reaksiyonu',
        'action' => 'yorum reaksiyon işlemi',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
    ],
    'comment_report_rate_max' => [
        'window' => 'comment_report_rate_window',
        'title' => 'Yorum Şikayet Gönderimi',
        'action' => 'yorum şikayeti',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
    ],
    'ban_appeal_message_limit' => [
        'window' => 'ban_appeal_message_cooldown_minutes',
        'title' => 'Ban İtiraz Mesajı',
        'action' => 'ban itiraz mesajı',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Ban itirazı gönderen kullanıcının hesabına göre uygulanır.',
        'zero_limit_help' => '0 = ban itiraz mesaj limiti kapalı.',
        'zero_summary' => 'Ban itiraz mesaj limiti kapalı.',
    ],
    'user_upload_rate_limit' => [
        'window' => 'user_upload_rate_window',
        'title' => 'Mod Gönderimi',
        'action' => 'mod gönderimi',
        'scope' => 'Kullanıcı bazlı',
        'scope_help' => 'Giriş yapan kullanıcının hesabına göre uygulanır.',
        'zero_limit_help' => '0 = mod gönderim sınırı yok.',
        'zero_summary' => 'Mod gönderim sınırı yok.',
    ],
];

$rateLimitWindowKeys = [];
foreach ($rateLimitPairs as $limitKey => $pairMeta) {
    $rateLimitWindowKeys[(string) ($pairMeta['window'] ?? '')] = $limitKey;
}

$rateLimitSingles = [
    'comment_rate_admin_bypass' => [
        'scope' => 'Admin bazlı',
        'scope_help' => 'Yalnızca admin hesaplarının yorum limitine takılıp takılmayacağını belirler.',
        'summary' => 'Açıksa admin hesapları yorum gönderim limitinden muaf tutulur.',
    ],
];

$simpleSettingsGroups = [
    'general' => [
        [
            'title' => 'Site Temeli',
            'icon' => 'bi-window',
            'description' => 'Site adı, açıklama, dil, saat dilimi ve listeleme davranışları.',
            'keys' => ['site_name', 'site_description', 'site_language', 'timezone', 'date_format', 'items_per_page'],
        ],
        [
            'title' => 'Yasal Bağlantılar ve Alt Metin',
            'icon' => 'bi-file-earmark-text',
            'description' => 'Kullanım koşulları, gizlilik politikası ve footer metnini düzenleyin.',
            'keys' => ['terms_url', 'privacy_url', 'footer_text'],
        ],
        [
            'title' => 'Bakım Modu',
            'icon' => 'bi-tools',
            'description' => 'Siteyi geçici olarak bakıma alıp ziyaretçiye gösterilecek mesajı belirleyin.',
            'keys' => ['maintenance_mode', 'maintenance_message'],
        ],
    ],
    'moderation' => [
        [
            'title' => 'Temel Moderasyon Kuralları',
            'icon' => 'bi-shield-check',
            'description' => 'Raporlanan konular ve rapor eşiğine bağlı otomatik gizleme davranışını yönetin.',
            'keys' => ['auto_hide_reported', 'report_threshold'],
        ],
    ],
    'toast_notifications' => [
        [
            'title' => 'Görünüm ve Konum',
            'icon' => 'bi-chat-square-dots',
            'description' => 'Toast sisteminin aktifliği, konumu, teması ve animasyon davranışı.',
            'keys' => ['toast_enabled', 'toast_position', 'toast_theme', 'toast_animation', 'toast_stack_direction'],
        ],
        [
            'title' => 'Süreler',
            'icon' => 'bi-hourglass-split',
            'description' => 'Varsayılan, başarı, hata ve uyarı bildirimlerinin ekranda kalma süreleri.',
            'keys' => ['toast_duration', 'toast_duration_success', 'toast_duration_error', 'toast_duration_warning'],
        ],
        [
            'title' => 'Etkileşim',
            'icon' => 'bi-cursor',
            'description' => 'İlerleme çubuğu, kapatma butonu, görünür bildirim limiti ve hover davranışları.',
            'keys' => ['toast_progress_bar', 'toast_close_button', 'toast_max_visible', 'toast_click_to_close', 'toast_pause_on_hover'],
        ],
    ],
    'performance' => [
        [
            'title' => 'Önbellek',
            'icon' => 'bi-lightning-charge',
            'description' => 'Sistem önbelleğini ve geçerlilik süresini yönetin.',
            'keys' => ['cache_enabled', 'cache_ttl'],
        ],
    ],
    'social_features' => [
        [
            'title' => 'Sosyal Bağlantılar',
            'icon' => 'bi-share',
            'description' => 'Tema ve footer alanlarında kullanılacak sosyal medya URL adresleri.',
            'keys' => adminSettingKeysForSection($definitions, 'social_features'),
        ],
    ],
    'popup_announcement' => [
        [
            'title' => 'İçerik ve Görünüm',
            'icon' => 'bi-megaphone',
            'description' => 'Popup duyurunun aktifliği, başlığı, içeriği ve görsel temasını belirleyin.',
            'keys' => ['popup_announcement_enabled', 'popup_announcement_title', 'popup_announcement_content', 'popup_announcement_type'],
        ],
        [
            'title' => 'Davranış ve Hedef',
            'icon' => 'bi-bullseye',
            'description' => 'Kapatma davranışı, otomatik kapanma, aksiyon butonu, hedef kitle ve gösterim sıklığı.',
            'keys' => [
                'popup_announcement_strict',
                'popup_announcement_timer',
                'popup_announcement_button_text',
                'popup_announcement_action_text',
                'popup_announcement_action_url',
                'popup_announcement_target',
                'popup_announcement_cookie_days',
            ],
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

            if (function_exists('appRenderMailLayout')) {
                return appRenderMailLayout([
                    'site_name' => $siteName,
                    'eyebrow' => 'E-posta testi',
                    'title' => $siteName . ' e-posta testi',
                    'content_html' => function_exists('appMailPlainTextHtml')
                        ? appMailPlainTextHtml($message)
                        : nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')),
                    'detail_rows' => [
                        'Sistem' => $siteName,
                        'Surucu' => $driver,
                        'Gonderici' => $fromName,
                        'Gonderici Adresi' => $fromAddress,
                        'SMTP Sunucu' => $smtpHost !== '' ? $smtpHost : '-',
                        'SMTP Port' => $smtpPort !== '' ? $smtpPort : '-',
                        'Sifreleme' => $smtpEncryption !== '' ? $smtpEncryption : '-',
                        'Alici' => $recipient,
                    ],
                    'footer_note' => 'Bu ileti, paneldeki mevcut ayarlar kullanilarak test amacli olusturuldu.',
                ]);
            }

            $metaHtml = '';
            foreach ($metaRows as $label => $value) {
                $metaHtml .= '<tr>'
                    . '<th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:600;width:38%;">' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</th>'
                    . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#111827;">' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }

            return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"></head>'
                . '<body style="margin:0;background:#f3f5f8;font-family:Segoe UI,Roboto,sans-serif;color:#111827;">'
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
            'verification_reminders' => [
                'job_key' => 'verification_reminders',
                'group' => 'Hesap Güvenliği',
                'title' => 'Doğrulama Hatırlatma Cronu',
                'description' => 'Doğrulanmamış hesaplara belirlenen süreden sonra yeniden doğrulama e-postası gönderir.',
                'icon' => 'bi-envelope-arrow-up',
                'schedule' => '0 * * * *',
                'schedule_label' => 'Her 1 saat',
                'cli' => $phpBinaryCommand . ' ' . settingsCronShellArg($scriptPath('send-verification-reminders.php')) . ' --limit=50',
                'http' => settingsCronHttpCommand($buildUrl('/cron/send-verification-reminders.php', ['limit' => '50'])),
                'url' => $buildUrl('/cron/send-verification-reminders.php', ['limit' => '50']),
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

if (!function_exists('settingsPreserveMovedAccountEmailSettings')) {
    /**
     * Account email templates are edited in Notification Center now. Preserve their
     * values when the legacy all-settings form is submitted without those fields.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $currentSettings
     * @return array<string,mixed>
     */
    function settingsPreserveMovedAccountEmailSettings(array $input, array $currentSettings): array
    {
        $defaults = ['account_email_system_enabled' => '1'];
        foreach (\App\Engine\Email\AccountEmailService::catalog() as $templateKey => $template) {
            foreach (['enabled', 'subject', 'body'] as $field) {
                $defaults[\App\Engine\Email\AccountEmailService::settingKey((string) $templateKey, $field)] = (string) ($template[$field] ?? '');
            }
        }

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $input)) {
                $currentValue = (string) ($currentSettings[$key] ?? '');
                $input[$key] = $currentValue !== '' ? $currentValue : $defaultValue;
            }
        }

        return $input;
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

function settingsCommentSpamExemptionToken(string $value): string
{
    if (function_exists('commentSpamNormalizeExemptionToken')) {
        return commentSpamNormalizeExemptionToken($value);
    }

    return mb_strtolower(trim($value), 'UTF-8');
}

function settingsCommentSpamExemptUserOptions(?PDO $pdo, string $currentValue): array
{
    $options = [];
    foreach (adminSettingListValues($currentValue, true) as $token) {
        $options[$token] = $token . ' (kayitli deger)';
    }

    if (!$pdo instanceof PDO || !function_exists('usersTableExists') || !usersTableExists($pdo, 'users')
        || !function_exists('usersColumnExists') || !usersColumnExists($pdo, 'users', 'username')) {
        return $options;
    }

    $where = ["username IS NOT NULL", "username <> ''"];
    if (usersColumnExists($pdo, 'users', 'deleted_at')) {
        $where[] = 'deleted_at IS NULL';
    }
    if (usersColumnExists($pdo, 'users', 'status')) {
        $where[] = "status = 'active'";
    }
    if (usersColumnExists($pdo, 'users', 'is_banned')) {
        $where[] = '(is_banned = 0 OR is_banned IS NULL)';
    }

    try {
        $stmt = $pdo->query('SELECT username FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY username ASC');
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) as $username) {
            $username = trim((string) $username);
            $token = settingsCommentSpamExemptionToken($username);
            if ($token === '') {
                continue;
            }

            $options[$token] = $username;
        }
    } catch (Throwable $e) {
        error_log('[settings-comment-spam-options] ' . $e->getMessage());
    }

    return $options;
}

function settingsCommentSpamExemptGroupRows(?PDO $pdo): array
{
    if (!$pdo instanceof PDO || !function_exists('usersTableExists') || !usersTableExists($pdo, 'user_groups')) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT name, slug FROM user_groups WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        error_log('[settings-comment-spam-groups] ' . $e->getMessage());
        return [];
    }
}

function settingsCommentSpamExemptGroupOptions(array $groupRows, string $currentValue): array
{
    $options = [];
    foreach (adminSettingListValues($currentValue, true) as $token) {
        $options[$token] = $token . ' (kayitli deger)';
    }

    foreach ($groupRows as $group) {
        $name = trim((string) ($group['name'] ?? ''));
        $slug = trim((string) ($group['slug'] ?? ''));
        $value = settingsCommentSpamExemptionToken($slug !== '' ? $slug : $name);
        if ($value === '') {
            continue;
        }

        $label = $name !== '' ? $name : $value;
        if ($slug !== '' && $slug !== $label) {
            $label .= ' (' . $slug . ')';
        }
        $options[$value] = $label;
    }

    return $options;
}

function settingsCommentSpamResolveGroupSelection(string $currentValue, array $groupRows): string
{
    $resolved = [];
    foreach (adminSettingListValues($currentValue, true) as $token) {
        $match = $token;
        foreach ($groupRows as $group) {
            $slug = settingsCommentSpamExemptionToken((string) ($group['slug'] ?? ''));
            $name = settingsCommentSpamExemptionToken((string) ($group['name'] ?? ''));
            if ($token !== '' && ($token === $slug || $token === $name)) {
                $match = $slug !== '' ? $slug : $name;
                break;
            }
        }
        if ($match !== '') {
            $resolved[$match] = $match;
        }
    }

    return implode(',', array_values($resolved));
}

function settingsApplyCommentSpamExemptionOptions(array &$definitions, array &$settings, ?PDO $pdo): void
{
    if (isset($definitions['comment_spam_exempt_usernames'])) {
        $definitions['comment_spam_exempt_usernames']['options'] = settingsCommentSpamExemptUserOptions(
            $pdo,
            (string) ($settings['comment_spam_exempt_usernames'] ?? '')
        );
    }

    if (isset($definitions['comment_spam_exempt_groups'])) {
        $groupRows = settingsCommentSpamExemptGroupRows($pdo);
        $settings['comment_spam_exempt_groups'] = settingsCommentSpamResolveGroupSelection(
            (string) ($settings['comment_spam_exempt_groups'] ?? ''),
            $groupRows
        );
        $definitions['comment_spam_exempt_groups']['options'] = settingsCommentSpamExemptGroupOptions(
            $groupRows,
            (string) ($settings['comment_spam_exempt_groups'] ?? '')
        );
    }
}

function settingsApplyDownloadExemptionOptions(array &$definitions, array &$settings, ?PDO $pdo): void
{
    if (isset($definitions['download_exempt_usernames'])) {
        $definitions['download_exempt_usernames']['options'] = settingsCommentSpamExemptUserOptions(
            $pdo,
            (string) ($settings['download_exempt_usernames'] ?? '')
        );
    }

    if (isset($definitions['download_exempt_groups'])) {
        $groupRows = settingsCommentSpamExemptGroupRows($pdo);
        $settings['download_exempt_groups'] = settingsCommentSpamResolveGroupSelection(
            (string) ($settings['download_exempt_groups'] ?? ''),
            $groupRows
        );
        $definitions['download_exempt_groups']['options'] = settingsCommentSpamExemptGroupOptions(
            $groupRows,
            (string) ($settings['download_exempt_groups'] ?? '')
        );
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
        $settingsPost = settingsPreserveMovedAccountEmailSettings($_POST, getAdminSettings($pdo));
        saveAdminSettings($pdo, $settingsPost);
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
settingsApplyCommentSpamExemptionOptions($definitions, $settings, $pdo instanceof PDO ? $pdo : null);
settingsApplyDownloadExemptionOptions($definitions, $settings, $pdo instanceof PDO ? $pdo : null);
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
    if (function_exists('adminStatusMeta')) {
        return (string) adminStatusMeta($status, 'cron')['label'];
    }

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
    if (function_exists('adminStatusMeta') && function_exists('adminToneBadgeClass')) {
        $meta = adminStatusMeta($status, 'cron');
        return adminToneBadgeClass((string) ($meta['tone'] ?? 'muted'));
    }

    return match (strtolower(trim($status))) {
        'success' => 'ui-admin-badge-success',
        'warning' => 'ui-admin-badge-warning',
        'error' => 'ui-admin-badge-danger',
        'skipped' => 'ui-admin-badge-muted',
        default => 'ui-admin-badge-muted',
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

<form method="post" action="settings.php" class="settings-admin-form" id="settingsForm" data-admin-no-lock="1" data-suppress-info-toasts="1" data-suppress-inline-alert-toasts="1">
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
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'icon' => (string) $section['icon'],
                    'title' => (string) $section['title'] . ' Ayarları',
                ]) ?>
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
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel' . ($routeFirst ? ' is-active' : ''),
                                    'attrs' => ['id' => (string) $routeTabId],
                                    'icon' => (string) ($routeGroup['icon'] ?? 'bi-sliders'),
                                    'title' => (string) ($routeGroup['title'] ?? $routeTabId),
                                ]) ?>
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
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'route_filters', [[
                                                'title' => 'Rota Alanları',
                                                'icon' => (string) ($routeGroup['icon'] ?? 'bi-sliders'),
                                                'description' => (string) ($routeGroup['description'] ?? ''),
                                                'keys' => (array) ($routeGroup['keys'] ?? []),
                                            ]]) ?>
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $routeFirst = false; endforeach; ?>



                            <script src="<?= asset_url('admin/assets/settings-page-route-filters.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'cron'): ?>
                            <div class="route-filter-subtabs">
                                <?php $cronFirst = true; foreach ($cronGroups as $cronTabId => $cronGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn cron-subtab-btn<?= $cronFirst ? ' active' : '' ?>" data-cron-tab="<?= htmlspecialchars($cronTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($cronGroup['icon'] ?? 'bi-gear')) ?>"></i><?= htmlspecialchars((string) ($cronGroup['title'] ?? $cronTabId)) ?>
                                    </button>
                                <?php $cronFirst = false; endforeach; ?>
                            </div>

                            <?php $cronFirst = true; foreach ($cronGroups as $cronTabId => $cronGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel cron-subtab-panel' . ($cronFirst ? ' is-active' : ''),
                                    'attrs' => ['id' => (string) $cronTabId],
                                ]) ?>
                                        <?php if (!empty($cronGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($cronGroup['description'])) ?></div>
                                        <?php endif; ?>

                                        <?php if ($cronTabId === 'cron-tab-endpoints'): ?>
    <?= adminRenderAlert('', 'info', [
        'icon' => 'bi-info-square-fill',
        'class' => 'ui-admin-alert-spaced',
        'html' => '<div><strong>Görev yönetimi:</strong> Her cron görevi için CLI komutu, HTTP yedek komutu, URL endpoint\'i ve manuel tetikleme aksiyonu tek kartta sunulur. HestiaCP/CPanel/Plesk tarafında CLI komutunu kullanın; PHP yolu boş bırakılırsa sistem uygun CLI yolunu otomatik seçer.</div>',
    ]) ?>

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
                            <span class="ui-admin-badge <?= htmlspecialchars($runBadge) ?>"><?= htmlspecialchars($cronStatusLabel($runStatus)) ?></span>
                        </div>

                        <div class="cron-task-meta">
                            <span><strong>Plan:</strong> <?= htmlspecialchars((string) ($task['schedule'] ?? '-')) ?></span>
                            <span><strong>Öneri:</strong> <?= htmlspecialchars((string) ($task['schedule_label'] ?? '-')) ?></span>
                            <span><strong>Son Çalışma:</strong> <?= htmlspecialchars($runAt) ?> (<?= htmlspecialchars($runAgo) ?>)</span>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">CLI Komutu</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input settings-readonly-input" value="<?= htmlspecialchars((string) ($task['cli'] ?? '')) ?>" readonly data-ui-select-on-focus>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                            </div>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">HTTP Yedek Komutu</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input settings-readonly-input" value="<?= htmlspecialchars((string) ($task['http'] ?? '')) ?>" readonly data-ui-select-on-focus>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" title="Kopyala" data-ui-copy-previous><i class="bi bi-copy"></i></button>
                            </div>
                        </div>

                        <div class="cron-task-command-row">
                                <label class="ui-admin-form-label fw-bold">URL Endpoint</label>
                            <div class="admin-inline-control">
                                <input type="text" class="ui-admin-form-control admin-muted-input settings-readonly-input" value="<?= htmlspecialchars((string) ($task['url'] ?? '')) ?>" readonly data-ui-select-on-focus>
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
    <?= adminRenderAlert('', $healthTone, [
        'icon' => $healthTone === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill',
        'class' => 'ui-admin-alert-spaced',
        'html' => '<div><strong>' . htmlspecialchars($healthTitle) . '</strong><br>Toplam ' . (int) $cronHealthTotal . ' görevden ' . (int) $cronHealthOkCount . ' tanesi son kaydına göre başarılı. '
            . ($cronLatestAt ? 'Son cron hareketi: ' . htmlspecialchars(date('d.m.Y H:i:s', strtotime((string) $cronLatestAt) ?: time())) . '.' : 'Henüz cron kaydı görünmüyor.')
            . '<br><a href="logs.php?view=cron">Cron loglarını aç</a></div>',
    ]) ?>

    <?= adminRenderTableOpen([
        'Görev',
        'İş Anahtarı',
        'Plan',
        'Durum',
        'Son Çalışma',
        'Log',
    ], [
        'class' => 'w-100',
        'wrap_class' => 'table-responsive mt-3',
        'label' => 'Cron sağlık durumu',
    ]) ?>
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
                        <td><span class="ui-admin-badge <?= htmlspecialchars($rowBadge) ?>"><?= htmlspecialchars($cronStatusLabel($rowStatus)) ?></span></td>
                        <td><?= htmlspecialchars($rowAt) ?><?php if ($rowAtRaw): ?> <small>(<?= htmlspecialchars($cronAgeLabel($rowAtRaw)) ?>)</small><?php endif; ?></td>
                        <td><a href="logs.php?view=cron&cron_job=<?= urlencode((string) ($healthRow['job_key'] ?? '')) ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Aç</a></td>
                    </tr>
                <?php endforeach; ?>
    <?= adminRenderTableClose() ?>
<?php else: ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'cron', [[
                                                'title' => 'Genel Cron Kuralları',
                                                'icon' => 'bi-gear',
                                                'description' => (string) ($cronGroup['description'] ?? ''),
                                                'keys' => (array) ($cronGroup['keys'] ?? []),
                                            ]]) ?>
                                            <?php
                                                $cronPhpBinaryValue = trim(str_replace(["\r", "\n"], '', (string) ($settings['cron_php_binary'] ?? '')));
                                                $cronPhpBinaryIsAuto = function_exists('adminCronPhpBinaryIsAutoValue')
                                                    ? adminCronPhpBinaryIsAutoValue($cronPhpBinaryValue)
                                                    : $cronPhpBinaryValue === '';
                                                $cronPhpBinaryResolved = function_exists('settingsCronPhpBinary')
                                                    ? settingsCronPhpBinary($settings)
                                                    : ($cronPhpBinaryValue !== '' ? $cronPhpBinaryValue : 'php');
                                                $cronPhpBinaryCandidates = function_exists('adminCronPhpBinaryCandidates')
                                                    ? adminCronPhpBinaryCandidates()
                                                    : [];
                                            ?>
                                            <div class="settings-rule-note">
                                                <i class="bi <?= $cronPhpBinaryIsAuto ? 'bi-magic' : 'bi-pencil-square' ?>"></i>
                                                <div>
                                                    <strong><?= $cronPhpBinaryIsAuto ? 'PHP CLI otomatik seçiliyor.' : 'PHP CLI yolu elle girildi.' ?></strong>
                                                    Komutlarda kullanılacak değer: <code><?= htmlspecialchars($cronPhpBinaryResolved, ENT_QUOTES, 'UTF-8') ?></code>.
                                                    <?php if ($cronPhpBinaryCandidates !== []): ?>
                                                        <div class="settings-chip-row mt-2">
                                                            <?php foreach (array_slice($cronPhpBinaryCandidates, 0, 6) as $candidate): ?>
                                                                <span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endforeach; ?>
                                                            <?php if (count($cronPhpBinaryCandidates) > 6): ?>
                                                                <span class="ui-admin-badge ui-admin-badge-muted">+<?= count($cronPhpBinaryCandidates) - 6 ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $cronFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-cron.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'seo'): ?>
                            <div class="seo-subtabs">
                                <?php $seoFirst = true; foreach ($seoGroups as $seoTabId => $seoGroup): ?>
                                    <button type="button" class="seo-subtab-btn<?= $seoFirst ? ' active' : '' ?>" data-seo-tab="<?= htmlspecialchars($seoTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($seoGroup['icon'] ?? 'bi-search')) ?>"></i><?= htmlspecialchars((string) ($seoGroup['title'] ?? $seoTabId)) ?>
                                    </button>
                                <?php $seoFirst = false; endforeach; ?>
                            </div>

                            <?php $seoFirst = true; foreach ($seoGroups as $seoTabId => $seoGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'seo-subtab-panel' . ($seoFirst ? ' is-active' : ''),
                                    'attrs' => ['id' => (string) $seoTabId],
                                ]) ?>
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
                                        <?= adminRenderSettingsRuleSections($definitions, $settings, 'seo', [
                                            [
                                                'title' => 'Site Haritası Yönlendirme',
                                                'icon' => 'bi-gear',
                                                'description' => 'Sitemap route davranışı ve cache süresini belirleyin.',
                                                'keys' => ['sitemap_route_enabled', 'sitemap_cache_duration'],
                                            ],
                                            [
                                                'title' => 'XML Site Haritası',
                                                'icon' => 'bi-diagram-3',
                                                'description' => 'XML sitemap üretimi, öncelik, değişim sıklığı ve kategori davranışı.',
                                                'keys' => ['sitemap_enabled', 'sitemap_max_urls', 'sitemap_changefreq', 'sitemap_priority_home', 'sitemap_priority_topics', 'sitemap_priority_categories', 'sitemap_include_categories', 'sitemap_exclude_drafts'],
                                            ],
                                            [
                                                'title' => 'Görsel Site Haritası',
                                                'icon' => 'bi-images',
                                                'description' => 'Konu görsellerinin image sitemap içine nasıl dahil edileceğini yönetin.',
                                                'keys' => ['image_sitemap_enabled', 'image_sitemap_max_images', 'image_sitemap_hero', 'image_sitemap_media', 'image_sitemap_inline'],
                                            ],
                                        ]) ?>

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
                                        <?= adminRenderSettingsRuleSections($definitions, $settings, 'seo', [
                                            [
                                                'title' => 'Schema.org İşaretlemeleri',
                                                'icon' => 'bi-diagram-2',
                                                'description' => 'Kategori, profil, organizasyon ve breadcrumb yapısal verilerini yönetin.',
                                                'keys' => ['structured_data_category', 'structured_data_profile', 'schema_organization_name', 'schema_organization_logo', 'schema_site_search', 'schema_breadcrumbs'],
                                            ],
                                            [
                                                'title' => 'Özel Kodlar',
                                                'icon' => 'bi-code-slash',
                                                'description' => 'JSON-LD ve head içine eklenecek özel kodları kontrol edin.',
                                                'keys' => ['structured_data', 'custom_head_code'],
                                                'layout' => 'wide',
                                            ],
                                        ]) ?>

                                    <?php else: ?>
                                        <?= adminRenderSettingsRuleSections($definitions, $settings, 'seo', [[
                                            'title' => (string) ($seoGroup['title'] ?? 'SEO Ayarları'),
                                            'icon' => (string) ($seoGroup['icon'] ?? 'bi-search'),
                                            'description' => (string) ($seoGroup['description'] ?? ''),
                                            'keys' => (array) ($seoGroup['keys'] ?? []),
                                        ]]) ?>
                                    <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
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
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'comments-subtab-panel' . ($commentFirst ? ' is-active' : ''),
                                    'attrs' => ['id' => (string) $commentTabId],
                                    'icon' => (string) ($commentGroup['icon'] ?? 'bi-gear'),
                                    'title' => (string) ($commentGroup['title'] ?? $commentTabId),
                                ]) ?>
                                        <?php if (!empty($commentGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($commentGroup['description'])) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($commentGroup['sections']) && is_array($commentGroup['sections'])): ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'comments', (array) $commentGroup['sections']) ?>
                                        <?php else: ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'comments', [[
                                                'title' => (string) ($commentGroup['title'] ?? 'Yorum Ayarları'),
                                                'icon' => (string) ($commentGroup['icon'] ?? 'bi-gear'),
                                                'description' => (string) ($commentGroup['description'] ?? ''),
                                                'keys' => (array) ($commentGroup['keys'] ?? []),
                                            ]]) ?>
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $commentFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-comments.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'user_system'): ?>
                            <div class="settings-subtabs" data-settings-subtabs>
                                <?php $userSystemFirst = true; foreach ($userSystemGroups as $userSystemTabId => $userSystemGroup): ?>
                                    <button type="button" class="settings-subtab-link<?= $userSystemFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($userSystemTabId) ?>" data-settings-subtab-scope="user-system">
                                        <i class="bi <?= htmlspecialchars((string) ($userSystemGroup['icon'] ?? 'bi-person-gear')) ?> me-1"></i><?= htmlspecialchars((string) ($userSystemGroup['title'] ?? $userSystemTabId)) ?>
                                    </button>
                                <?php $userSystemFirst = false; endforeach; ?>
                            </div>

                            <?php $userSystemFirst = true; foreach ($userSystemGroups as $userSystemTabId => $userSystemGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel user-system-subtab-panel' . ($userSystemFirst ? ' is-active' : ''),
                                    'attrs' => [
                                        'id' => (string) $userSystemTabId,
                                        'data-settings-subtab-panel' => (string) $userSystemTabId,
                                        'data-settings-subtab-scope' => 'user-system',
                                    ],
                                ]) ?>
                                        <?php if (!empty($userSystemGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($userSystemGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($userSystemTabId === 'user-system-tab-approvals'): ?>
                                            <?php
                                                $settingsBool = static function (string $key, string $default = '1') use ($settings): bool {
                                                    $value = $settings[$key] ?? $default;
                                                    if (is_bool($value)) {
                                                        return $value;
                                                    }

                                                    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
                                                };
                                                $registrationApprovalEnabled = $settingsBool('registration_requires_admin_approval', '0');
                                                $emailVerificationEnabled = $settingsBool('account_email_verification_enabled', '0');
                                                $emailVerificationRequired = $settingsBool('account_email_verification_required', '0');
                                                $emailVerificationTtlMinutes = max(15, min(10080, (int) ($settings['account_email_verification_ttl_minutes'] ?? 1440)));
                                                $emailVerificationCooldownMinutes = max(1, min(1440, (int) ($settings['account_email_verification_resend_cooldown_minutes'] ?? 10)));
                                            ?>
                                            <?= adminRenderAlert('', 'info', [
                                                'icon' => 'bi-shield-check',
                                                'class' => 'ui-alert-spaced',
                                                'html' => '<div><strong>Kısa durum özeti</strong><br>'
                                                    . ($registrationApprovalEnabled ? 'Yeni kayıtlar yönetici onayına düşer.' : 'Yeni kayıtlar otomatik olarak aktif açılır.')
                                                    . ($emailVerificationEnabled ? ' E-posta doğrulama aktiftir.' : ' E-posta doğrulama kapalıdır.')
                                                    . ($emailVerificationRequired ? ' Girişte doğrulama zorunludur.' : ' Girişte doğrulama zorunlu değildir.')
                                                    . ' Doğrulama bağlantısı ' . (int) $emailVerificationTtlMinutes . ' dakika, tekrar gönderme bekleme süresi ' . (int) $emailVerificationCooldownMinutes . ' dakikadır.</div>',
                                            ]) ?>
                                        <?php endif; ?>
                                        <?php $userSystemSections = (array) ($userSystemGroup['sections'] ?? []); ?>
                                        <?php if (!empty($userSystemSections)): ?>
                                            <div class="user-system-rule-list">
                                                <?php foreach ($userSystemSections as $userSystemSection): ?>
                                                    <?php
                                                        $userSystemKeys = array_values(array_filter(
                                                            (array) ($userSystemSection['keys'] ?? []),
                                                            static fn($key): bool => isset($definitions[(string) $key])
                                                        ));
                                                        if ($userSystemKeys === []) {
                                                            continue;
                                                        }

                                                        $userSystemLayout = trim((string) ($userSystemSection['layout'] ?? ''));
                                                        $userSystemRuleClass = 'user-system-rule';
                                                        if ($userSystemLayout !== '') {
                                                            $userSystemRuleClass .= ' user-system-rule--' . preg_replace('/[^a-z0-9_-]/i', '', $userSystemLayout);
                                                        }
                                                        $userSystemSummaryMode = trim((string) ($userSystemSection['summary_mode'] ?? ''));
                                                        $userSystemSummaryAttributes = $userSystemSummaryMode !== ''
                                                            ? ' data-user-summary-mode="' . htmlspecialchars($userSystemSummaryMode, ENT_QUOTES, 'UTF-8') . '"'
                                                            : '';
                                                        $userSystemGridClass = 'user-system-rule-grid ui-grid';
                                                        if ($userSystemLayout !== '') {
                                                            $userSystemGridClass .= ' user-system-rule-grid--' . preg_replace('/[^a-z0-9_-]/i', '', $userSystemLayout);
                                                        }
                                                    ?>
                                                    <section class="<?= htmlspecialchars($userSystemRuleClass, ENT_QUOTES, 'UTF-8') ?>"<?= $userSystemSummaryAttributes ?>>
                                                        <div class="user-system-rule-head">
                                                            <div class="user-system-rule-title-wrap">
                                                                <span class="user-system-rule-icon" aria-hidden="true">
                                                                    <i class="bi <?= htmlspecialchars((string) ($userSystemSection['icon'] ?? $userSystemGroup['icon'] ?? 'bi-person-gear')) ?>"></i>
                                                                </span>
                                                                <div class="user-system-rule-title-text">
                                                                    <div class="user-system-rule-title"><?= htmlspecialchars((string) ($userSystemSection['title'] ?? $userSystemGroup['title'] ?? $userSystemTabId), ENT_QUOTES, 'UTF-8') ?></div>
                                                                    <?php if (!empty($userSystemSection['description'])): ?>
                                                                        <div class="user-system-rule-desc"><?= htmlspecialchars((string) $userSystemSection['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($userSystemSection['badge'])): ?>
                                                                <span class="user-system-rule-badge"><?= htmlspecialchars((string) $userSystemSection['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?= adminRenderSettingsGrid($definitions, $settings, 'user_system', $userSystemKeys, $userSystemGridClass) ?>
                                                        <?php if ($userSystemSummaryMode !== ''): ?>
                                                            <div class="user-system-rule-summary" data-user-system-summary></div>
                                                            <div class="user-system-rule-warning" data-user-system-warning hidden></div>
                                                        <?php endif; ?>
                                                    </section>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <?= adminRenderSettingsGrid($definitions, $settings, 'user_system', (array) ($userSystemGroup['keys'] ?? []), 'user-system-rule-grid ui-grid') ?>
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
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
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel' . ($emailFirst ? ' is-active' : ''),
                                    'attrs' => [
                                        'id' => (string) $emailTabId,
                                        'data-settings-subtab-panel' => (string) $emailTabId,
                                        'data-settings-subtab-scope' => 'email',
                                    ],
                                ]) ?>
                                        <?php if (!empty($emailGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($emailGroup['description'])) ?></div>
                                        <?php endif; ?>

                                        <?php if ($emailTabId === 'email-tab-settings'): ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'email', [[
                                                'title' => 'SMTP ve Gönderici',
                                                'icon' => 'bi-envelope-gear',
                                                'description' => (string) ($emailGroup['description'] ?? ''),
                                                'keys' => (array) ($emailGroup['keys'] ?? []),
                                            ]]) ?>
                                        <?php elseif ($emailTabId === 'email-tab-test'): ?>
                                            <?php $currentAdminEmail = trim((string) ($_SESSION['_auth_user_email'] ?? '')); ?>
                                            <?= adminRenderAlert('', 'info', [
                                                'icon' => 'bi-info-circle',
                                                'class' => 'ui-admin-alert-spaced',
                                                'html' => '<div><strong>Test gönderimi</strong><br>Bu araç, üstteki SMTP alanlarında yaptığınız mevcut değişiklikleri de kullanır ve ayarları kaydetmeden tek başına test gönderir.</div>',
                                            ]) ?>

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
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $emailFirst = false; endforeach; ?>
                        <?php elseif ($id === 'rate_limit'): ?>
                            <?= adminRenderAlert('', 'info', [
                                'icon' => 'bi-info-circle',
                                'class' => 'ui-admin-alert-spaced',
                                'html' => '<div><strong>Hızlı Kullanım:</strong> Limit alanı, yanındaki süre içinde kaç deneme, istek veya işlem yapılabileceğini belirler. Süre alanı dakika bazlıdır. Örnek: Limit 5 + Süre 15 = 15 dakikada en fazla 5 işlem.</div>',
                            ]) ?>
                            <div class="rate-limit-subtabs">
                                <?php $rateFirst = true; foreach ($rateLimitGroups as $rateTabId => $rateGroup): ?>
                                    <button type="button" class="rate-limit-subtab-btn<?= $rateFirst ? ' active' : '' ?>" data-rate-tab="<?= htmlspecialchars($rateTabId) ?>">
                                        <i class="bi <?= htmlspecialchars((string) ($rateGroup['icon'] ?? 'bi-speedometer2')) ?>"></i><?= htmlspecialchars((string) ($rateGroup['title'] ?? $rateTabId)) ?>
                                    </button>
                                <?php $rateFirst = false; endforeach; ?>
                            </div>

                            <?php $rateFirst = true; foreach ($rateLimitGroups as $rateTabId => $rateGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'rate-limit-subtab-panel' . ($rateFirst ? ' is-active' : ''),
                                    'attrs' => ['id' => (string) $rateTabId],
                                    'icon' => (string) ($rateGroup['icon'] ?? 'bi-speedometer2'),
                                    'title' => (string) ($rateGroup['title'] ?? $rateTabId),
                                ]) ?>
                                        <?php if (!empty($rateGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($rateGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <div class="rate-limit-rule-list">
                                            <?php $renderedRateKeys = []; ?>
                                            <?php foreach (($rateGroup['keys'] ?? []) as $key): ?>
                                                <?php
                                                    if (isset($renderedRateKeys[$key]) || !isset($definitions[$key])) {
                                                        continue;
                                                    }

                                                    $definition = $definitions[$key];
                                                    $pairMeta = $rateLimitPairs[$key] ?? null;
                                                    if (is_array($pairMeta)) {
                                                        $windowKey = (string) ($pairMeta['window'] ?? '');
                                                        if ($windowKey === '' || !isset($definitions[$windowKey])) {
                                                            $pairMeta = null;
                                                        }
                                                    }
                                                ?>
                                                <?php if (is_array($pairMeta)): ?>
                                                    <?php
                                                        $windowKey = (string) $pairMeta['window'];
                                                        $windowDefinition = $definitions[$windowKey];
                                                        $renderedRateKeys[$key] = true;
                                                        $renderedRateKeys[$windowKey] = true;
                                                        $scopeLabel = (string) ($pairMeta['scope'] ?? 'Kapsam');
                                                        $scopeHelp = (string) ($pairMeta['scope_help'] ?? '');
                                                        $zeroLimitHelp = (string) ($pairMeta['zero_limit_help'] ?? '');
                                                        $zeroSummary = (string) ($pairMeta['zero_summary'] ?? '');
                                                    ?>
                                                    <div class="rate-limit-rule"
                                                         data-rate-summary-mode="pair"
                                                         data-rate-action="<?= htmlspecialchars((string) ($pairMeta['action'] ?? 'işlem'), ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-zero-summary="<?= htmlspecialchars($zeroSummary, ENT_QUOTES, 'UTF-8') ?>">
                                                        <div class="rate-limit-rule-head">
                                                            <div>
                                                                <div class="rate-limit-rule-title"><?= htmlspecialchars((string) ($pairMeta['title'] ?? $definition['label']), ENT_QUOTES, 'UTF-8') ?></div>
                                                            </div>
                                                            <span class="rate-limit-scope-badge"
                                                                  <?php if ($scopeHelp !== ''): ?>
                                                                      data-bs-toggle="tooltip"
                                                                      data-bs-placement="top"
                                                                      data-bs-title="<?= htmlspecialchars($scopeHelp, ENT_QUOTES, 'UTF-8') ?>"
                                                                  <?php endif; ?>><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </div>
                                                        <div class="rate-limit-rule-controls">
                                                            <div class="rate-limit-rule-control">
                                                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon"
                                                                           data-bs-toggle="tooltip"
                                                                           data-bs-placement="top"
                                                                           data-bs-title="<?= htmlspecialchars((string) $definition['tooltip'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                    <?php endif; ?>
                                                                </label>
                                                                <input id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                                       name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                                       type="number"
                                                                       class="ui-admin-form-control rate-limit-number-input"
                                                                       value="<?= htmlspecialchars((string) ($settings[$key] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                       <?= isset($definition['min']) ? 'min="' . htmlspecialchars((string) $definition['min'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       <?= isset($definition['max']) ? 'max="' . htmlspecialchars((string) $definition['max'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       data-rate-role="limit">
                                                                <?php if ($zeroLimitHelp !== ''): ?>
                                                                    <div class="rate-limit-field-help"><?= htmlspecialchars($zeroLimitHelp, ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="rate-limit-rule-control">
                                                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($windowKey, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars((string) $windowDefinition['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                    <?php if (!empty($windowDefinition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon"
                                                                           data-bs-toggle="tooltip"
                                                                           data-bs-placement="top"
                                                                           data-bs-title="<?= htmlspecialchars((string) $windowDefinition['tooltip'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                    <?php endif; ?>
                                                                </label>
                                                                <input id="<?= htmlspecialchars($windowKey, ENT_QUOTES, 'UTF-8') ?>"
                                                                       name="<?= htmlspecialchars($windowKey, ENT_QUOTES, 'UTF-8') ?>"
                                                                       type="number"
                                                                       class="ui-admin-form-control rate-limit-number-input"
                                                                       value="<?= htmlspecialchars((string) ($settings[$windowKey] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                       <?= isset($windowDefinition['min']) ? 'min="' . htmlspecialchars((string) $windowDefinition['min'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       <?= isset($windowDefinition['max']) ? 'max="' . htmlspecialchars((string) $windowDefinition['max'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       data-rate-role="window">
                                                            </div>
                                                        </div>
                                                        <div class="rate-limit-rule-summary" data-rate-summary-text></div>
                                                    </div>
                                                <?php elseif (isset($rateLimitWindowKeys[$key])): ?>
                                                    <?php $renderedRateKeys[$key] = true; ?>
                                                <?php else: ?>
                                                    <?php
                                                        $renderedRateKeys[$key] = true;
                                                        $singleMeta = $rateLimitSingles[$key] ?? [];
                                                        $scopeLabel = (string) ($singleMeta['scope'] ?? 'Kapsam');
                                                        $scopeHelp = (string) ($singleMeta['scope_help'] ?? '');
                                                        $singleMode = (string) ($singleMeta['mode'] ?? ($definition['type'] === 'bool' ? 'switch' : 'fixed'));
                                                        $zeroHelp = (string) ($singleMeta['zero_help'] ?? '');
                                                        $zeroSummary = (string) ($singleMeta['zero_summary'] ?? '');
                                                    ?>
                                                    <div class="rate-limit-rule rate-limit-rule--single"
                                                         data-rate-summary-mode="<?= htmlspecialchars($singleMode, ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-action="<?= htmlspecialchars((string) ($singleMeta['action'] ?? 'işlem'), ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-window-label="<?= htmlspecialchars((string) ($singleMeta['window_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-zero-summary="<?= htmlspecialchars($zeroSummary, ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-on-summary="<?= htmlspecialchars((string) ($singleMeta['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                         data-rate-off-summary="Kapalıysa bu muafiyet uygulanmaz.">
                                                        <div class="rate-limit-rule-head">
                                                            <div>
                                                                <div class="rate-limit-rule-title"><?= htmlspecialchars((string) ($singleMeta['title'] ?? $definition['label']), ENT_QUOTES, 'UTF-8') ?></div>
                                                            </div>
                                                            <span class="rate-limit-scope-badge"
                                                                  <?php if ($scopeHelp !== ''): ?>
                                                                      data-bs-toggle="tooltip"
                                                                      data-bs-placement="top"
                                                                      data-bs-title="<?= htmlspecialchars($scopeHelp, ENT_QUOTES, 'UTF-8') ?>"
                                                                  <?php endif; ?>><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </div>
                                                        <?php if ($definition['type'] === 'bool'): ?>
                                                            <label class="ui-admin-switch rate-limit-switch">
                                                                <input type="checkbox"
                                                                       name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                                       value="1"
                                                                       <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>
                                                                       data-rate-role="switch">
                                                                <span class="ui-admin-switch-label">
                                                                    <?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon"
                                                                           data-bs-toggle="tooltip"
                                                                           data-bs-placement="top"
                                                                           data-bs-title="<?= htmlspecialchars((string) $definition['tooltip'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php else: ?>
                                                            <div class="rate-limit-rule-control rate-limit-rule-control--single">
                                                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars((string) $definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                                                    <?php if (!empty($definition['tooltip'])): ?>
                                                                        <i class="bi bi-info-circle admin-help-icon"
                                                                           data-bs-toggle="tooltip"
                                                                           data-bs-placement="top"
                                                                           data-bs-title="<?= htmlspecialchars((string) $definition['tooltip'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                    <?php endif; ?>
                                                                </label>
                                                                <input id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                                       name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                                                       type="<?= $definition['type'] === 'number' ? 'number' : 'text' ?>"
                                                                       class="ui-admin-form-control rate-limit-number-input"
                                                                       value="<?= htmlspecialchars((string) ($settings[$key] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                       <?= $definition['type'] === 'number' && isset($definition['min']) ? 'min="' . htmlspecialchars((string) $definition['min'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       <?= $definition['type'] === 'number' && isset($definition['max']) ? 'max="' . htmlspecialchars((string) $definition['max'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                       data-rate-role="single">
                                                                <?php if ($zeroHelp !== ''): ?>
                                                                    <div class="rate-limit-field-help"><?= htmlspecialchars($zeroHelp, ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="rate-limit-rule-summary" data-rate-summary-text></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $rateFirst = false; endforeach; ?>

                            <script src="<?= asset_url('admin/assets/settings-page-rate-limit.js', $baseUri) ?>" defer></script>
                        <?php elseif ($id === 'user_uploads'): ?>
                            <div class="route-filter-subtabs settings-subtabs" data-settings-subtabs>
                                <?php $userUploadFirst = true; foreach ($userUploadGroups as $userUploadTabId => $userUploadGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn settings-subtab-btn<?= $userUploadFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($userUploadTabId) ?>" data-settings-subtab-scope="user-uploads">
                                        <i class="bi <?= htmlspecialchars((string) ($userUploadGroup['icon'] ?? 'bi-cloud-plus')) ?>"></i><?= htmlspecialchars((string) ($userUploadGroup['title'] ?? $userUploadTabId)) ?>
                                    </button>
                                <?php $userUploadFirst = false; endforeach; ?>
                            </div>

                            <?php $userUploadFirst = true; foreach ($userUploadGroups as $userUploadTabId => $userUploadGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel' . ($userUploadFirst ? ' is-active' : ''),
                                    'attrs' => [
                                        'id' => (string) $userUploadTabId,
                                        'data-settings-subtab-panel' => (string) $userUploadTabId,
                                        'data-settings-subtab-scope' => 'user-uploads',
                                    ],
                                ]) ?>
                                        <?php if (!empty($userUploadGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($userUploadGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?= adminRenderSettingsRuleSections($definitions, $settings, 'user_uploads', [[
                                            'title' => (string) ($userUploadGroup['title'] ?? 'Kullanıcı Gönderimleri'),
                                            'icon' => (string) ($userUploadGroup['icon'] ?? 'bi-cloud-plus'),
                                            'description' => (string) ($userUploadGroup['description'] ?? ''),
                                            'keys' => (array) ($userUploadGroup['keys'] ?? []),
                                        ]]) ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $userUploadFirst = false; endforeach; ?>
                        <?php elseif ($id === 'downloads'): ?>
                            <div class="route-filter-subtabs settings-subtabs" data-settings-subtabs>
                                <?php $downloadFirst = true; foreach ($downloadGroups as $downloadTabId => $downloadGroup): ?>
                                    <button type="button" class="route-filter-subtab-btn settings-subtab-btn<?= $downloadFirst ? ' active' : '' ?>" data-settings-subtab="<?= htmlspecialchars($downloadTabId) ?>" data-settings-subtab-scope="downloads">
                                        <i class="bi <?= htmlspecialchars((string) ($downloadGroup['icon'] ?? 'bi-download')) ?>"></i><?= htmlspecialchars((string) ($downloadGroup['title'] ?? $downloadTabId)) ?>
                                    </button>
                                <?php $downloadFirst = false; endforeach; ?>
                            </div>

                            <?php $downloadFirst = true; foreach ($downloadGroups as $downloadTabId => $downloadGroup): ?>
                                <?= adminRenderSubtabPanelOpen([
                                    'class' => 'route-filter-subtab-panel' . ($downloadFirst ? ' is-active' : ''),
                                    'attrs' => [
                                        'id' => (string) $downloadTabId,
                                        'data-settings-subtab-panel' => (string) $downloadTabId,
                                        'data-settings-subtab-scope' => 'downloads',
                                    ],
                                ]) ?>
                                        <?php if (!empty($downloadGroup['description'])): ?>
                                            <div class="admin-section-desc"><?= htmlspecialchars((string) ($downloadGroup['description'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($downloadGroup['sections']) && is_array($downloadGroup['sections'])): ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'downloads', (array) $downloadGroup['sections']) ?>
                                        <?php else: ?>
                                            <?= adminRenderSettingsRuleSections($definitions, $settings, 'downloads', [[
                                                'title' => (string) ($downloadGroup['title'] ?? 'İndirme Ayarları'),
                                                'icon' => (string) ($downloadGroup['icon'] ?? 'bi-download'),
                                                'description' => (string) ($downloadGroup['description'] ?? ''),
                                                'keys' => (array) ($downloadGroup['keys'] ?? []),
                                            ]]) ?>
                                        <?php endif; ?>
                                <?= adminRenderSubtabPanelClose() ?>
                            <?php $downloadFirst = false; endforeach; ?>
                        <?php elseif (isset($simpleSettingsGroups[$id])): ?>
                            <?= adminRenderSettingsRuleSections($definitions, $settings, $id, $simpleSettingsGroups[$id]) ?>
                        <?php endif; ?>
                    <?php if ($id !== 'file_manager'): ?>
                    </div>
                </div>
                    <?php endif; ?>
                <?php if ($id === 'file_manager'): ?>
                <!-- Dosya Yöneticisi Ayarları -->
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'icon' => 'bi-folder2-open',
                    'title' => 'Dosya Yöneticisi Ayarları',
                ]) ?>
                        <div class="admin-section-desc">
                            Dosya yükleme kuralları ile görsel optimizasyon ayarlarını aynı merkezden yönetin. Dosya Ayarları kabul edilen türleri ve hedef klasörü, Resim Ayarları ise WebP, thumbnail ve filigran davranışlarını kontrol eder.
                        </div>
                        <?php
                            $fileManagerRenderDefinitions = $definitions;
                            if (!$settingsWebpSupport && isset($fileManagerRenderDefinitions['webp_enabled'])) {
                                $fileManagerRenderDefinitions['webp_enabled']['disabled'] = true;
                                $fileManagerRenderDefinitions['webp_enabled']['disabled_help'] = $settingsWebpRequirementNote;
                            }
                        ?>

                        <div class="file-manager-subtabs">
                            <?php $fileManagerFirst = true; foreach ($fileManagerGroups as $fileManagerTabId => $fileManagerGroup): ?>
                                <button type="button" class="file-manager-subtab-btn<?= $fileManagerFirst ? ' active' : '' ?>" data-file-manager-tab="<?= htmlspecialchars($fileManagerTabId) ?>">
                                    <i class="bi <?= htmlspecialchars((string) ($fileManagerGroup['icon'] ?? 'bi-folder2-open')) ?>"></i><?= htmlspecialchars((string) ($fileManagerGroup['title'] ?? $fileManagerTabId)) ?>
                                </button>
                            <?php $fileManagerFirst = false; endforeach; ?>
                        </div>

                        <?php $fileManagerFirst = true; foreach ($fileManagerGroups as $fileManagerTabId => $fileManagerGroup): ?>
                            <div class="file-manager-subtab-panel<?= $fileManagerFirst ? ' is-active' : '' ?>" id="<?= htmlspecialchars($fileManagerTabId) ?>">
                                <?= adminRenderSettingsRuleSections($fileManagerRenderDefinitions, $settings, 'file_manager', [[
                                    'title' => (string) ($fileManagerGroup['title'] ?? $fileManagerTabId),
                                    'icon' => (string) ($fileManagerGroup['icon'] ?? 'bi-folder2-open'),
                                    'description' => (string) ($fileManagerGroup['description'] ?? ''),
                                    'keys' => (array) ($fileManagerGroup['keys'] ?? []),
                                ]]) ?>
                            </div>
                        <?php $fileManagerFirst = false; endforeach; ?>

                        <script src="<?= asset_url('admin/assets/settings-page-file-manager.js', $baseUri) ?>" defer></script>

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
                <?= adminRenderPanelClose('div') ?>
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
