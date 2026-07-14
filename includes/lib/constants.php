<?php
/**
 * Uygulama Sabitleri
 *
 * Tüm magic numbers ve sabit değerler burada tanımlanır
 */

// Sayfalama
define('TOPICS_PER_PAGE', 20);
define('CATEGORY_TOPICS_PER_PAGE', 20);
define('SIDEBAR_POPULAR_LIMIT', 10);

// Rate Limiting (saniye cinsinden)
define('RATE_LIMIT_VIEW_WINDOW', 120);  // 2 dakika
define('RATE_LIMIT_DOWNLOAD_WINDOW', 60);  // 1 dakika
define('RATE_LIMIT_COMMENT_WINDOW', 30);  // 30 saniye

// Yorum Sistemi
define('COMMENT_MAX_LENGTH', 2000);
define('COMMENT_REALTIME_POLL_SECONDS', 15);

// İndirme Sistemi
define('DOWNLOAD_COUNTDOWN_SECONDS', 5);
define('DOWNLOAD_READY_TEXT', 'İndirmek için tıklayınız');
define('DOWNLOAD_WAIT_TEXT', 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz');
define('DOWNLOAD_DONE_TEXT', 'İndirme linkiniz hazır, indirmek için tıklayın');

// Sayfalama
define('PAGINATION_MAX_VISIBLE_PAGES', 5);

// SEO
define('META_DESCRIPTION_MAX_LENGTH', 160);

// Dosya Yükleme
define('UPLOAD_MAX_SIZE_MB', 50);

// Dosya Yükleme - İzin Verilen Uzantılar (const olarak tanımlandı)
const UPLOAD_ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const UPLOAD_ALLOWED_ARCHIVE_EXTENSIONS = ['zip', 'rar', '7z'];
const UPLOAD_ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'txt'];

// Cache (saniye cinsinden)
define('CACHE_CATEGORIES_TTL', 3600);  // 1 saat
define('CACHE_POPULAR_TOPICS_TTL', 300);  // 5 dakika
define('CACHE_SETTINGS_TTL', 600);  // 10 dakika

// Asset Versioning
if (!defined('ASSET_VERSION_METHOD')) {
    define('ASSET_VERSION_METHOD', 'filemtime');  // 'filemtime' veya 'git_hash' veya 'manual'
}
if (!defined('ASSET_VERSION_MANUAL')) {
    define('ASSET_VERSION_MANUAL', '1.0.0');  // Manuel versiyon numarasi
}

// Marka / UI varsayilan degerleri (admin_settings uzerinden runtime'da override edilir)
define('BRAND_DEFAULT_ACCENT', '#8b1538');     // Varsayilan marka vurgu rengi
define('BRAND_TOPBAR_BG_DEFAULT', '#0f172a'); // Varsayilan topbar arka plan rengi
