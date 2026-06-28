<?php

declare(strict_types=1);

if (!function_exists('eventsPublicUrl')) {
    function eventsPublicUrl(string $suffix = ''): string
    {
        $baseUrl = function_exists('routePublicStaticUrl')
            ? routePublicStaticUrl('events')
            : rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/') . '/events';

        $suffix = trim($suffix);
        if ($suffix === '') {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($suffix, '/');
    }
}

if (!function_exists('eventsDefaultConfig')) {
    function eventsDefaultConfig(): array
    {
        return [
            'events_system_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'general'],
            'events_system_disabled_message' => ['value' => 'Etkinlik sistemi şu anda kısa süreliğine kapalı.', 'type' => 'string', 'group' => 'general'],
            'events_min_account_age_days' => ['value' => '0', 'type' => 'int', 'group' => 'general'],
            'events_min_messages' => ['value' => '0', 'type' => 'int', 'group' => 'general'],
            'events_wheel_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'modules'],
            'events_wheel_disabled_message' => ['value' => '', 'type' => 'string', 'group' => 'modules'],
            'events_raffles_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'modules'],
            'events_raffles_disabled_message' => ['value' => '', 'type' => 'string', 'group' => 'modules'],
            'events_tasks_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'modules'],
            'events_tasks_disabled_message' => ['value' => '', 'type' => 'string', 'group' => 'modules'],
            'events_rewards_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'modules'],
            'events_rewards_disabled_message' => ['value' => '', 'type' => 'string', 'group' => 'modules'],
            'api_rate_limit_window' => ['value' => '60', 'type' => 'int', 'group' => 'security'],
            'api_rate_limit_max' => ['value' => '45', 'type' => 'int', 'group' => 'security'],
            'events_activity_points_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'activity'],
            'wheel_daily_limit' => ['value' => '3', 'type' => 'int', 'group' => 'wheel'],
            'wheel_hourly_limit' => ['value' => '1', 'type' => 'int', 'group' => 'wheel'],
            'wheel_spin_cooldown_seconds' => ['value' => '30', 'type' => 'int', 'group' => 'wheel'],
            'wheel_min_points' => ['value' => '0', 'type' => 'int', 'group' => 'wheel'],
            'wheel_extra_spin_cost' => ['value' => '0', 'type' => 'int', 'group' => 'wheel'],
            'wheel_reward_expiry_days' => ['value' => '30', 'type' => 'int', 'group' => 'wheel'],
            'wheel_pity_spins' => ['value' => '5', 'type' => 'int', 'group' => 'wheel'],
            'wheel_pity_threshold' => ['value' => '10.0', 'type' => 'string', 'group' => 'wheel'],
            'wheel_spin_duration_seconds' => ['value' => '5', 'type' => 'int', 'group' => 'wheel'],
            'wheel_spin_speed_multiplier' => ['value' => '1', 'type' => 'int', 'group' => 'wheel'],
            'wheel_spin_sound_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'wheel'],
            'wheel_spin_sound_volume' => ['value' => '55', 'type' => 'int', 'group' => 'wheel'],
            'wheel_result_sound_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'wheel'],
            'wheel_result_sound_volume' => ['value' => '70', 'type' => 'int', 'group' => 'wheel'],
            'raffle_reward_expiry_days' => ['value' => '30', 'type' => 'int', 'group' => 'raffle'],
            'points_system_enabled' => ['value' => 'false', 'type' => 'bool', 'group' => 'points'],
            'points_system_table' => ['value' => '', 'type' => 'string', 'group' => 'points'],
            'points_system_column' => ['value' => '', 'type' => 'string', 'group' => 'points'],
            'points_system_user_id_column' => ['value' => 'id', 'type' => 'string', 'group' => 'points'],
            'email_notifications_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'notifications'],
            'email_queue_enabled' => ['value' => 'false', 'type' => 'bool', 'group' => 'notifications'],
            'email_max_retry_count' => ['value' => '3', 'type' => 'int', 'group' => 'notifications'],
            'frontend_toast_polling_interval' => ['value' => '15', 'type' => 'int', 'group' => 'frontend'],
            'frontend_animations_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'frontend'],
            'frontend_sounds_enabled' => ['value' => 'true', 'type' => 'bool', 'group' => 'frontend'],
            'events_banner_enabled' => ['value' => 'false', 'type' => 'bool', 'group' => 'frontend'],
            'events_banner_text' => ['value' => '', 'type' => 'string', 'group' => 'frontend'],
            'events_banner_style' => ['value' => 'info', 'type' => 'string', 'group' => 'frontend'],
            'audit_log_retention_days' => ['value' => '30', 'type' => 'int', 'group' => 'system'],
            'reward_low_stock_threshold' => ['value' => '5', 'type' => 'int', 'group' => 'system'],
            'cache_driver' => ['value' => 'database', 'type' => 'string', 'group' => 'system'],
            'events_login_streak_enabled' => ['value' => 'false', 'type' => 'bool', 'group' => 'activity'],
            'events_login_streak_days' => ['value' => '7', 'type' => 'int', 'group' => 'activity'],
            'events_login_streak_reward_type' => ['value' => 'points', 'type' => 'string', 'group' => 'activity'],
            'events_login_streak_reward_value' => ['value' => '50', 'type' => 'string', 'group' => 'activity'],
            'events_max_points_limit' => ['value' => '0', 'type' => 'int', 'group' => 'activity'],
            'raffle_auto_resolve' => ['value' => 'true', 'type' => 'bool', 'group' => 'raffle'],
        ];
    }
}

if (!function_exists('eventsConfigUiSections')) {
    function eventsConfigUiSections(): array
    {
        return [
            'general' => [
                'title' => 'Genel Etkinlik Durumu',
                'icon' => 'bi-power',
                'description' => 'Etkinlik merkezinin ziyaretçiye açık olup olmadığını, mesajları ve temel katılım şartlarını yönet.',
                'fields' => [
                    ['key' => 'events_system_enabled', 'label' => 'Etkinlik sistemi açık', 'type' => 'bool', 'help' => 'Kapalıyken tüm etkinlik sayfaları ve kullanıcı API işlemleri durur.'],
                    ['key' => 'events_system_disabled_message', 'label' => 'Kapalıyken mesaj', 'type' => 'textarea', 'help' => 'Etkinlik sistemi kapalıyken kullanıcıya gösterilecek kısa açıklama.'],
                    ['key' => 'events_min_account_age_days', 'label' => 'Minimum üyelik (gün)', 'type' => 'number', 'min' => 0, 'max' => 3650, 'help' => 'Etkinlik sistemini (çark, çekiliş) kullanabilmek için kullanıcının en az kaç gündür üye olması gerektiği. 0 limitsizdir.'],
                    ['key' => 'events_min_messages', 'label' => 'Minimum mesaj sayısı', 'type' => 'number', 'min' => 0, 'max' => 1000000, 'help' => 'Etkinlik sistemini kullanabilmek için kullanıcının sahip olması gereken en az mesaj sayısı. 0 limitsizdir.'],
                ],
            ],
            'modules' => [
                'title' => 'Modül Görünürlüğü',
                'icon' => 'bi-grid-1x2',
                'description' => 'Hangi etkinlik bölümlerinin kullanıcıya açık olacağını seç.',
                'fields' => [
                    ['key' => 'events_wheel_enabled', 'label' => 'Çark açık', 'type' => 'bool', 'help' => 'Kapalıyken çark sayfası ve çevirme işlemi durur.'],
                    ['key' => 'events_wheel_disabled_message', 'label' => 'Çark kapalı mesajı', 'type' => 'textarea', 'help' => 'Boş bırakılırsa varsayılan mesaj gösterilir.'],
                    ['key' => 'events_raffles_enabled', 'label' => 'Çekilişler açık', 'type' => 'bool', 'help' => 'Kapalıyken çekiliş listesi ve katılım işlemi durur.'],
                    ['key' => 'events_raffles_disabled_message', 'label' => 'Çekiliş kapalı mesajı', 'type' => 'textarea', 'help' => 'Boş bırakılırsa varsayılan mesaj gösterilir.'],
                    ['key' => 'events_tasks_enabled', 'label' => 'Görevler açık', 'type' => 'bool', 'help' => 'Kapalıyken görev sayfası ve ödül alma işlemi durur.'],
                    ['key' => 'events_tasks_disabled_message', 'label' => 'Görev kapalı mesajı', 'type' => 'textarea', 'help' => 'Boş bırakılırsa varsayılan mesaj gösterilir.'],
                    ['key' => 'events_rewards_enabled', 'label' => 'Ödüller açık', 'type' => 'bool', 'help' => 'Kapalıyken ödül kasası ve teslim alma işlemi durur.'],
                    ['key' => 'events_rewards_disabled_message', 'label' => 'Ödüller kapalı mesajı', 'type' => 'textarea', 'help' => 'Boş bırakılırsa varsayılan mesaj gösterilir.'],
                    ['key' => 'points_system_enabled', 'label' => 'Puan sistemi açık', 'type' => 'bool', 'help' => 'Kapalıyken puan ödülleri otomatik uygulanmaz; admin bekleyen ödülü manuel teslim eder. Açıkken puan tablosu ayarlarını yapılandırmalısınız.'],
                ],
            ],
            'points' => [
                'title' => 'Puan Entegrasyonu',
                'icon' => 'bi-database-check',
                'description' => 'Harici puan tablosuna yazılacak tablo ve kolonları belirle. Puan sistemi açıkken bu alanlar doğrulanır.',
                'fields' => [
                    ['key' => 'points_system_table', 'label' => 'Puan tablosu', 'type' => 'string', 'placeholder' => 'users', 'help' => 'Puan değerinin tutulduğu veritabanı tablosu. Sadece güvenli SQL adları kabul edilir.'],
                    ['key' => 'points_system_column', 'label' => 'Puan kolonu', 'type' => 'string', 'placeholder' => 'points', 'help' => 'Puan değerinin artırılacağı kolon.'],
                    ['key' => 'points_system_user_id_column', 'label' => 'Kullanıcı ID kolonu', 'type' => 'string', 'placeholder' => 'id', 'help' => 'Kullanıcıyı eşleştiren ID kolonu.'],
                ],
            ],
            'security' => [
                'title' => 'Güvenlik (Rate Limit)',
                'icon' => 'bi-shield-lock',
                'description' => 'Spam ve DDoS saldırılarını önlemek için API limitlerini belirle.',
                'fields' => [
                    ['key' => 'api_rate_limit_window', 'label' => 'Zaman Aralığı (Saniye)', 'type' => 'number', 'min' => 10, 'max' => 3600, 'help' => 'Hız sınırının uygulanacağı zaman dilimi (Örn: 60 saniye).'],
                    ['key' => 'api_rate_limit_max', 'label' => 'Maksimum İstek Sayısı', 'type' => 'number', 'min' => 1, 'max' => 500, 'help' => 'Belirtilen zaman aralığında kullanıcının atabileceği en fazla istek adedi (Örn: 45).'],
                ],
            ],
            'wheel' => [
                'title' => 'Çark Ayarları',
                'icon' => 'bi-arrow-clockwise',
                'description' => 'Çark kullanım limitleri, giriş eşiği ve çarktan gelen ödüllerin geçerlilik süresi.',
                'fields' => [
                    ['key' => 'wheel_daily_limit', 'label' => 'Günlük limit', 'type' => 'number', 'min' => 0, 'max' => 1000, 'help' => 'Bir kullanıcının bir günde ücretsiz çevirebileceği maksimum adet. 0 limitsizdir.'],
                    ['key' => 'wheel_hourly_limit', 'label' => 'Saatlik limit', 'type' => 'number', 'min' => 0, 'max' => 1000, 'help' => 'Kısa sürede arka arkaya çevirimi sınırlar. 0 limitsizdir.'],
                    ['key' => 'wheel_spin_cooldown_seconds', 'label' => 'Bekleme Süresi (Sn)', 'type' => 'number', 'min' => 0, 'max' => 86400, 'help' => 'İki çevirme arası zorunlu bekleme süresi (Cooldown).'],
                    ['key' => 'wheel_min_points', 'label' => 'Minimum puan', 'type' => 'number', 'min' => 0, 'max' => 1000000000, 'help' => 'Çarkı çevirmek için gereken en düşük kullanıcı puanı.'],
                    ['key' => 'wheel_extra_spin_cost', 'label' => 'Ekstra hak puanı', 'type' => 'number', 'min' => 0, 'max' => 1000000000, 'help' => 'Limit dolduğunda ek çevirme hakkının puan maliyeti. 0 kapalıdır.'],
                    ['key' => 'wheel_reward_expiry_days', 'label' => 'Ödül geçerlilik günü', 'type' => 'number', 'min' => 0, 'max' => 3650, 'help' => 'Çarktan çıkan ödüllerin varsayılan son kullanma süresi. 0 süresizdir.'],
                    ['key' => 'wheel_pity_spins', 'label' => 'Garanti deneme sayısı', 'type' => 'number', 'min' => 0, 'max' => 1000, 'help' => 'Belirlenen sayı kadar premium ödül çıkmazsa garanti sistemi devreye girer. 0 kapalıdır.'],
                    ['key' => 'wheel_pity_threshold', 'label' => 'Premium eşik yüzdesi', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01', 'help' => 'Bu ihtimal yüzdesi ve altındaki ödüller premium kabul edilir.'],
                    ['key' => 'wheel_spin_duration_seconds', 'label' => 'Dönüş süresi seviyesi', 'type' => 'number', 'min' => 1, 'max' => 10, 'help' => 'Çark animasyon süresini belirler. 1 en sakin, 10 en hızlı dönüş seviyesidir.'],
                    ['key' => 'wheel_spin_speed_multiplier', 'label' => 'Dönüş ivmesi', 'type' => 'number', 'min' => 1, 'max' => 100, 'help' => 'Animasyon motorunun ek hız çarpanı. 1 varsayılan, 100 en agresif davranıştır.'],
                    ['key' => 'wheel_spin_sound_enabled', 'label' => 'Çark dönme sesi', 'type' => 'bool', 'help' => 'Çark dönerken mekanik çark sesi çalınsın.'],
                    ['key' => 'wheel_spin_sound_volume', 'label' => 'Dönme sesi seviyesi', 'type' => 'number', 'min' => 1, 'max' => 100, 'help' => 'Çark dönme sesini 1 ile 100 arasında ayarla.'],
                    ['key' => 'wheel_result_sound_enabled', 'label' => 'Çark bitiş sesi', 'type' => 'bool', 'help' => 'Çark durup ödül göründüğünde bitiş sesi çalınsın.'],
                    ['key' => 'wheel_result_sound_volume', 'label' => 'Bitiş sesi seviyesi', 'type' => 'number', 'min' => 1, 'max' => 100, 'help' => 'Çark bitiş sesini 1 ile 100 arasında ayarla.'],
                ],
            ],
            'raffle' => [
                'title' => 'Çekiliş Ayarları',
                'icon' => 'bi-ticket-perforated',
                'description' => 'Çekilişten verilen ödüller için varsayılan davranış.',
                'fields' => [
                    ['key' => 'raffle_reward_expiry_days', 'label' => 'Ödül geçerlilik günü', 'type' => 'number', 'min' => 0, 'max' => 3650, 'help' => 'Çekiliş ödüllerinin varsayılan son kullanma süresi. 0 süresizdir.'],
                    ['key' => 'raffle_auto_resolve', 'label' => 'Otomatik Sonuçlandır', 'type' => 'bool', 'help' => 'Süresi biten çekilişler cron veya sistem tarafından otomatik çekilsin.'],
                ],
            ],
            'activity' => [
                'title' => 'Aktivite Puanları',
                'icon' => 'bi-stars',
                'description' => 'Kullanıcı hareketlerinden otomatik etkinlik puanı kazanımını yönet.',
                'fields' => [
                    ['key' => 'events_activity_points_enabled', 'label' => 'Aktivite puanı kazanımı açık', 'type' => 'bool', 'help' => 'Kapalıyken aktivite hookları puan defterine yeni kayıt yazmaz.'],
                    ['key' => 'events_login_streak_enabled', 'label' => 'Seri Giriş Bonusu', 'type' => 'bool', 'help' => 'Ardışık giriş yapan kullanıcılara bonus ödül verilir.'],
                    ['key' => 'events_login_streak_days', 'label' => 'Seri Hedefi (Gün)', 'type' => 'number', 'min' => 1, 'max' => 365, 'help' => 'Kaç gün üst üste girince ödül verilecek?'],
                    ['key' => 'events_login_streak_reward_type', 'label' => 'Seri Ödülü Türü', 'type' => 'select', 'options' => ['points' => 'Puan', 'wheel_spin' => 'Çark Hakkı'], 'help' => 'Kullanıcı hedefe ulaşınca ne kazansın?'],
                    ['key' => 'events_login_streak_reward_value', 'label' => 'Seri Ödülü Değeri', 'type' => 'string', 'help' => 'Kaç puan veya kaç çark hakkı?'],
                    ['key' => 'events_max_points_limit', 'label' => 'Puan Cüzdanı Limiti', 'type' => 'number', 'min' => 0, 'max' => 1000000000, 'help' => 'Kullanıcıların biriktirebileceği en yüksek puan (0 = limitsiz).'],
                ],
            ],
            'notifications' => [
                'title' => 'Bildirim ve E-posta',
                'icon' => 'bi-envelope-check',
                'description' => 'Etkinlik bildirimleri ve e-posta kuyruğunun çalışma şekli.',
                'fields' => [
                    ['key' => 'email_notifications_enabled', 'label' => 'E-posta bildirimleri', 'type' => 'bool', 'help' => 'Global e-posta bildirim anahtarı.'],
                    ['key' => 'email_queue_enabled', 'label' => 'E-posta kuyruğu', 'type' => 'bool', 'help' => 'Açıkken cron kuyruğu e-postaları işler.'],
                    ['key' => 'email_max_retry_count', 'label' => 'Maksimum deneme', 'type' => 'number', 'min' => 1, 'max' => 10, 'help' => 'Kuyruktaki başarısız e-postalar için en fazla deneme sayısı.'],
                ],
            ],
            'frontend' => [
                'title' => 'Arayüz ve Deneyim',
                'icon' => 'bi-window-sidebar',
                'description' => 'Kullanıcı paneli (Frontend) animasyon, ses ve bildirim ayarları.',
                'fields' => [
                    ['key' => 'frontend_toast_polling_interval', 'label' => 'Bildirim Kontrolü (Sn)', 'type' => 'number', 'min' => 5, 'max' => 300, 'help' => 'Arka planda okunmamış toast bildirimleri kaç saniyede bir sorgulansın?'],
                    ['key' => 'frontend_animations_enabled', 'label' => 'Animasyonlar (Konfeti vb.)', 'type' => 'bool', 'help' => 'Ödül kazanıldığında görsel konfeti ve animasyonlar oynatılsın mı?'],
                    ['key' => 'frontend_sounds_enabled', 'label' => 'Ses Efektleri', 'type' => 'bool', 'help' => 'Çark dönerken ve ödül çıkarken ses efektleri çalınsın mı?'],
                    ['key' => 'events_banner_enabled', 'label' => 'Global Duyuru Açık', 'type' => 'bool', 'help' => 'Tüm etkinlik sayfalarının en üstünde duyuru çubuğu çıkar.'],
                    ['key' => 'events_banner_text', 'label' => 'Duyuru Metni', 'type' => 'textarea', 'help' => 'Duyuru çubuğunda yazacak mesaj.'],
                    ['key' => 'events_banner_style', 'label' => 'Duyuru Rengi', 'type' => 'select', 'options' => ['info' => 'Mavi (Bilgi)', 'warning' => 'Sarı (Uyarı)', 'success' => 'Yeşil (Başarı)', 'danger' => 'Kırmızı (Acil)'], 'help' => 'Duyuru şeridinin arkaplan stili.'],
                ],
            ],
            'system' => [
                'title' => 'Sistem ve Bakım',
                'icon' => 'bi-gear-wide-connected',
                'description' => 'Log tutma süreleri ve arka plan temizlik ayarları.',
                'fields' => [
                    ['key' => 'audit_log_retention_days', 'label' => 'Log Saklama (Gün)', 'type' => 'number', 'min' => 1, 'max' => 365, 'help' => 'Etkinlik hareket geçmişi kaç gün sonra otomatik silinsin?'],
                    ['key' => 'reward_low_stock_threshold', 'label' => 'Kritik Stok Sınırı', 'type' => 'number', 'min' => 0, 'max' => 999, 'help' => 'Fiziksel veya limitli ödüller bu sayının altına düştüğünde panele uyarı logu atılır.'],
                ],
            ],
        ];
    }
}

if (!function_exists('eventsConfigEditableKeys')) {
    function eventsConfigEditableKeys(): array
    {
        $keys = [];
        foreach (eventsConfigUiSections() as $section) {
            foreach (($section['fields'] ?? []) as $field) {
                $keys[] = (string)$field['key'];
            }
        }

        return array_values(array_unique($keys));
    }
}

if (!function_exists('eventsConfigFieldDisplayValue')) {
    function eventsConfigFieldDisplayValue(array $config, array $field): string
    {
        $key = (string)($field['key'] ?? '');
        $value = (string)($config[$key] ?? '');
        if (($field['type'] ?? '') !== 'number' || $value === '' || !is_numeric($value)) {
            return $value;
        }

        if (!isset($field['min']) && !isset($field['max'])) {
            return $value;
        }

        $number = (float)$value;
        if (isset($field['min'])) {
            $number = max((float)$field['min'], $number);
        }
        if (isset($field['max'])) {
            $number = min((float)$field['max'], $number);
        }

        if (floor($number) === $number) {
            return (string)(int)$number;
        }

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }
}

if (!function_exists('eventsFormatCompactNumber')) {
    function eventsFormatCompactNumber(float $number): string
    {
        if (floor($number) === $number) {
            return number_format((int)$number, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
    }
}

if (!function_exists('eventsFormatReadableDurationSeconds')) {
    function eventsFormatReadableDurationSeconds(int $seconds, string $zeroLabel = 'Hazır'): string
    {
        $seconds = max(0, $seconds);
        if ($seconds === 0) {
            return $zeroLabel;
        }

        $units = [
            ['label' => 'gün', 'seconds' => 86400],
            ['label' => 'saat', 'seconds' => 3600],
            ['label' => 'dk', 'seconds' => 60],
            ['label' => 'sn', 'seconds' => 1],
        ];
        $parts = [];
        foreach ($units as $unit) {
            $amount = intdiv($seconds, (int)$unit['seconds']);
            if ($amount <= 0) {
                continue;
            }

            $parts[] = $amount . ' ' . $unit['label'];
            $seconds %= (int)$unit['seconds'];
            if (count($parts) >= 2) {
                break;
            }
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('eventsFormatReadableDays')) {
    function eventsFormatReadableDays(int $days, string $zeroLabel = '0 gün'): string
    {
        $days = max(0, $days);
        if ($days === 0) {
            return $zeroLabel;
        }

        if ($days >= 365) {
            $years = intdiv($days, 365);
            $remainingDays = $days % 365;
            return $remainingDays > 0
                ? $years . ' yıl ' . $remainingDays . ' gün'
                : $years . ' yıl';
        }

        if ($days >= 7 && $days % 7 === 0) {
            return intdiv($days, 7) . ' hafta';
        }

        return $days . ' gün';
    }
}

if (!function_exists('eventsConfigNumberDisplayMeta')) {
    function eventsConfigNumberDisplayMeta(array $field): array
    {
        $key = (string)($field['key'] ?? '');
        $map = [
            'events_min_account_age_days' => ['format' => 'days', 'zeroLabel' => 'üyelik şartı yok'],
            'events_min_messages' => ['format' => 'count', 'unit' => 'mesaj', 'zeroLabel' => 'mesaj şartı yok'],
            'api_rate_limit_window' => ['format' => 'duration_seconds', 'zeroLabel' => '0 sn'],
            'api_rate_limit_max' => ['format' => 'count', 'unit' => 'istek'],
            'wheel_daily_limit' => ['format' => 'count', 'unit' => 'hak', 'zeroLabel' => 'limitsiz'],
            'wheel_hourly_limit' => ['format' => 'count', 'unit' => 'hak', 'zeroLabel' => 'limitsiz'],
            'wheel_spin_cooldown_seconds' => ['format' => 'duration_seconds', 'zeroLabel' => 'bekleme yok'],
            'wheel_min_points' => ['format' => 'count', 'unit' => 'puan', 'zeroLabel' => 'puan şartı yok'],
            'wheel_extra_spin_cost' => ['format' => 'count', 'unit' => 'puan', 'zeroLabel' => 'ek hak kapalı'],
            'wheel_reward_expiry_days' => ['format' => 'days', 'zeroLabel' => 'süresiz'],
            'wheel_pity_spins' => ['format' => 'count', 'unit' => 'deneme', 'zeroLabel' => 'garanti kapalı'],
            'wheel_pity_threshold' => ['format' => 'percent'],
            'wheel_spin_duration_seconds' => ['format' => 'level'],
            'wheel_spin_speed_multiplier' => ['format' => 'count', 'unit' => 'ivme seviyesi'],
            'wheel_spin_sound_volume' => ['format' => 'percent', 'suffix' => 'ses'],
            'wheel_result_sound_volume' => ['format' => 'percent', 'suffix' => 'ses'],
            'raffle_reward_expiry_days' => ['format' => 'days', 'zeroLabel' => 'süresiz'],
            'events_login_streak_days' => ['format' => 'days'],
            'events_max_points_limit' => ['format' => 'count', 'unit' => 'puan', 'zeroLabel' => 'limitsiz'],
            'email_max_retry_count' => ['format' => 'count', 'unit' => 'deneme'],
            'frontend_toast_polling_interval' => ['format' => 'duration_seconds', 'zeroLabel' => '0 sn'],
            'audit_log_retention_days' => ['format' => 'days'],
            'reward_low_stock_threshold' => ['format' => 'count', 'unit' => 'adet ve altı'],
            'expires_in_days' => ['format' => 'days', 'zeroLabel' => 'süresiz'],
            'quantity' => ['format' => 'count', 'unit' => 'adet', 'zeroLabel' => 'stok yok'],
            'remaining_quantity' => ['format' => 'count', 'unit' => 'adet', 'zeroLabel' => 'stok yok'],
            'min_user_points' => ['format' => 'count', 'unit' => 'puan', 'zeroLabel' => 'puan şartı yok'],
            'display_order' => ['format' => 'level', 'zeroLabel' => 'ilk sıra'],
            'probability' => ['format' => 'percent'],
            'weight' => ['format' => 'percent'],
            'daily_limit' => ['format' => 'count', 'unit' => 'kez', 'zeroLabel' => 'limitsiz'],
            'weekly_limit' => ['format' => 'count', 'unit' => 'kez', 'zeroLabel' => 'limitsiz'],
            'monthly_limit' => ['format' => 'count', 'unit' => 'kez', 'zeroLabel' => 'limitsiz'],
            'cooldown_minutes' => ['format' => 'duration_minutes', 'zeroLabel' => 'bekleme yok'],
            'min_length' => ['format' => 'count', 'unit' => 'karakter', 'zeroLabel' => 'uzunluk şartı yok'],
            'points' => ['format' => 'count', 'unit' => 'puan', 'zeroLabel' => 'puan yok'],
            'max_entries_per_user' => ['format' => 'count', 'unit' => 'katılım hakkı'],
            'winner_count' => ['format' => 'count', 'unit' => 'kazanan'],
            'target_count' => ['format' => 'count', 'unit' => 'hedef'],
            'reward_quantity' => ['format' => 'count', 'unit' => 'adet'],
        ];

        return array_replace(['format' => 'count', 'unit' => '', 'zeroLabel' => '', 'suffix' => ''], $map[$key] ?? [], $field['display'] ?? []);
    }
}

if (!function_exists('eventsConfigReadableNumberValue')) {
    function eventsConfigReadableNumberValue($value, array $field): string
    {
        $rawValue = str_replace(',', '.', trim((string)$value));
        if ($rawValue === '' || !is_numeric($rawValue)) {
            return '';
        }

        $number = (float)$rawValue;
        if (isset($field['min'])) {
            $number = max((float)$field['min'], $number);
        }
        if (isset($field['max'])) {
            $number = min((float)$field['max'], $number);
        }

        $meta = eventsConfigNumberDisplayMeta($field);
        $zeroLabel = (string)($meta['zeroLabel'] ?? '');
        if ($number == 0.0 && $zeroLabel !== '') {
            return $zeroLabel;
        }

        return match ((string)($meta['format'] ?? 'count')) {
            'duration_seconds' => eventsFormatReadableDurationSeconds((int)round($number), $zeroLabel !== '' ? $zeroLabel : '0 sn'),
            'duration_minutes' => eventsFormatReadableDurationSeconds((int)round($number * 60), $zeroLabel !== '' ? $zeroLabel : '0 dk'),
            'days' => eventsFormatReadableDays((int)round($number), $zeroLabel !== '' ? $zeroLabel : '0 gün'),
            'percent' => '%' . eventsFormatCompactNumber($number) . ((string)($meta['suffix'] ?? '') !== '' ? ' ' . (string)$meta['suffix'] : ''),
            'level' => eventsFormatCompactNumber($number) . '. seviye',
            default => trim(eventsFormatCompactNumber($number) . ' ' . (string)($meta['unit'] ?? '')),
        };
    }
}

if (!function_exists('eventsReadableNumberDataAttributes')) {
    function eventsReadableNumberDataAttributes(array $field): string
    {
        $meta = eventsConfigNumberDisplayMeta($field);
        $attrs = ['data-ui-events-readable-input'];
        $attrMap = [
            'format' => 'data-ui-events-readable-format',
            'unit' => 'data-ui-events-readable-unit',
            'zeroLabel' => 'data-ui-events-readable-zero-label',
            'suffix' => 'data-ui-events-readable-suffix',
        ];
        foreach ($attrMap as $metaKey => $attrName) {
            $value = (string)($meta[$metaKey] ?? '');
            if ($value !== '') {
                $attrs[] = $attrName . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return implode(' ', $attrs);
    }
}

if (!function_exists('eventsRenderReadableNumberValue')) {
    function eventsRenderReadableNumberValue($value, array $field): string
    {
        $rawValue = trim((string)$value);
        $meta = eventsConfigNumberDisplayMeta($field);
        $readableValue = $rawValue === ''
            ? (string)($meta['zeroLabel'] ?? '')
            : eventsConfigReadableNumberValue($rawValue, $field);
        if ($readableValue === '') {
            $readableValue = 'Değer girilmedi';
        }

        return '<span class="ui-events-readable-value" data-ui-events-readable-shell>'
            . '<i class="bi bi-eye" aria-hidden="true"></i>'
            . '<span>Uygulanacak değer</span>'
            . '<strong data-ui-events-readable-value>' . htmlspecialchars($readableValue, ENT_QUOTES, 'UTF-8') . '</strong>'
            . '</span>';
    }
}

if (!function_exists('eventsNormalizeConfigInput')) {
    function eventsNormalizeConfigInput(array $input): array
    {
        $errors = [];
        $data = [];
        $editableKeys = array_flip(eventsConfigEditableKeys());
        $intRanges = [
            'wheel_spin_duration_seconds' => [1, 10],
            'wheel_spin_speed_multiplier' => [1, 100],
            'wheel_spin_sound_volume' => [1, 100],
            'wheel_result_sound_volume' => [1, 100],
        ];
        $numericStringRanges = [
            'wheel_pity_threshold' => [0.0, 100.0],
        ];
        $fieldDefinitions = [];
        foreach (eventsConfigUiSections() as $section) {
            foreach (($section['fields'] ?? []) as $field) {
                $fieldKey = (string)($field['key'] ?? '');
                if ($fieldKey !== '') {
                    $fieldDefinitions[$fieldKey] = $field;
                }
            }
        }

        foreach (eventsDefaultConfig() as $key => $definition) {
            if (!isset($editableKeys[$key])) {
                continue;
            }

            $type = (string)($definition['type'] ?? 'string');
            if ($type === 'bool') {
                $raw = strtolower(trim((string)($input[$key] ?? '')));
                $data[$key] = ($raw !== '' && !in_array($raw, ['0', 'false', 'off', 'no'], true)) ? 'true' : 'false';
                continue;
            }

            if ($type === 'int') {
                $value = max(0, (int)trim((string)($input[$key] ?? $definition['value'])));
                if (isset($intRanges[$key])) {
                    [$min, $max] = $intRanges[$key];
                    $value = min($max, max($min, $value));
                } elseif (isset($fieldDefinitions[$key])) {
                    $field = $fieldDefinitions[$key];
                    if (isset($field['min'])) {
                        $value = max((int)$field['min'], $value);
                    }
                    if (isset($field['max'])) {
                        $value = min((int)$field['max'], $value);
                    }
                }
                $data[$key] = (string)$value;
                continue;
            }

            if (($key === 'events_system_disabled_message')) {
                $message = trim((string)($input[$key] ?? $definition['value']));
                $data[$key] = mb_substr($message !== '' ? $message : (string)$definition['value'], 0, 300);
                continue;
            }

            $field = $fieldDefinitions[$key] ?? [];
            if (isset($field['options']) && is_array($field['options'])) {
                $value = trim((string)($input[$key] ?? $definition['value']));
                if (!array_key_exists($value, $field['options'])) {
                    $errors[$key] = 'Geçerli bir seçenek seçin.';
                    $value = (string)$definition['value'];
                }
                $data[$key] = $value;
                continue;
            }

            if (isset($numericStringRanges[$key])) {
                [$min, $max] = $numericStringRanges[$key];
                $rawValue = str_replace(',', '.', trim((string)($input[$key] ?? $definition['value'])));
                if (!is_numeric($rawValue)) {
                    $errors[$key] = 'Geçerli bir sayı girin.';
                    $rawValue = (string)$definition['value'];
                }
                $number = min($max, max($min, (float)$rawValue));
                $data[$key] = rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
                continue;
            }

            $data[$key] = trim((string)($input[$key] ?? $definition['value']));
        }

        if (($data['points_system_enabled'] ?? 'false') === 'true') {
            $pointsValidation = eventsValidatePointsTarget($data, $GLOBALS['pdo'] ?? null);
            foreach (($pointsValidation['errors'] ?? []) as $fieldKey => $message) {
                $errors[(string)$fieldKey] = (string)$message;
            }
        }

        return [
            'valid' => $errors === [],
            'data' => $data,
            'errors' => $errors,
        ];
    }
}

if (!function_exists('eventsClampIntConfig')) {
    function eventsClampIntConfig(array $config, string $key, int $fallback, int $min, int $max): int
    {
        $value = isset($config[$key]) ? (int)$config[$key] : $fallback;
        return min($max, max($min, $value));
    }
}

if (!function_exists('eventsWheelFrontendSettings')) {
    function eventsWheelFrontendSettings(array $config): array
    {
        $speedLevel = eventsClampIntConfig($config, 'wheel_spin_duration_seconds', 5, 1, 10);
        $durationMs = 11000 - ($speedLevel * 700);
        return [
            'wheelSpinSpeedLevel' => $speedLevel,
            'wheelSpinDurationMs' => $durationMs,
            'wheelSpinSpeedMultiplier' => eventsClampIntConfig($config, 'wheel_spin_speed_multiplier', 1, 1, 100),
            'wheelSpinSoundEnabled' => eventsConfigBool($config, 'wheel_spin_sound_enabled'),
            'wheelSpinSoundVolume' => eventsClampIntConfig($config, 'wheel_spin_sound_volume', 55, 1, 100),
            'wheelResultSoundEnabled' => eventsConfigBool($config, 'wheel_result_sound_enabled'),
            'wheelResultSoundVolume' => eventsClampIntConfig($config, 'wheel_result_sound_volume', 70, 1, 100),
        ];
    }
}

if (!function_exists('eventsFeatureConfigMap')) {
    function eventsFeatureConfigMap(): array
    {
        return [
            'wheel' => ['key' => 'events_wheel_enabled', 'label' => 'Çark'],
            'raffle' => ['key' => 'events_raffles_enabled', 'label' => 'Çekilişler'],
            'raffles' => ['key' => 'events_raffles_enabled', 'label' => 'Çekilişler'],
            'tasks' => ['key' => 'events_tasks_enabled', 'label' => 'Görevler'],
            'rewards' => ['key' => 'events_rewards_enabled', 'label' => 'Ödüller'],
        ];
    }
}

if (!function_exists('eventsFeatureGate')) {
    function eventsFeatureGate(array $config, ?string $feature = null): array
    {
        $defaultMessage = 'Etkinlik sistemi şu anda kısa süreliğine kapalı.';
        $message = trim((string)($config['events_system_disabled_message'] ?? $defaultMessage));
        if ($message === '') {
            $message = $defaultMessage;
        }

        if (!eventsConfigBool($config, 'events_system_enabled')) {
            return [
                'enabled' => false,
                'reason' => 'system_disabled',
                'message' => $message,
            ];
        }

        if ($feature !== null) {
            $map = eventsFeatureConfigMap();
            $featureKey = strtolower($feature);
            if (isset($map[$featureKey]) && !eventsConfigBool($config, $map[$featureKey]['key'])) {
                $customMessageKey = str_replace('_enabled', '_disabled_message', $map[$featureKey]['key']);
                $customMessage = trim((string)($config[$customMessageKey] ?? ''));
                if ($customMessage === '') {
                    $customMessage = $map[$featureKey]['label'] . ' şu anda kapalı.';
                }
                return [
                    'enabled' => false,
                    'reason' => $featureKey . '_disabled',
                    'message' => $customMessage,
                ];
            }
        }

        return ['enabled' => true, 'reason' => null, 'message' => ''];
    }
}

if (!function_exists('eventsActivityPointsEnabled')) {
    function eventsActivityPointsEnabled(array $config): bool
    {
        $systemEnabled = array_key_exists('events_system_enabled', $config)
            ? eventsConfigBool($config, 'events_system_enabled')
            : true;
        $activityPointsEnabled = array_key_exists('events_activity_points_enabled', $config)
            ? eventsConfigBool($config, 'events_activity_points_enabled')
            : true;

        return $systemEnabled && $activityPointsEnabled;
    }
}

if (!function_exists('eventsNormalizePagination')) {
    function eventsNormalizePagination(array $input, int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $page = max(1, (int)($input['page'] ?? 1));
        $perPage = (int)($input['per_page'] ?? $defaultPerPage);
        if ($perPage <= 0) {
            $perPage = $defaultPerPage;
        }
        $perPage = min($maxPerPage, max(1, $perPage));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }
}

if (!function_exists('eventsResolvePaginationWindow')) {
    function eventsResolvePaginationWindow(int $requestedPage, int $totalItems, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int)ceil(max(0, $totalItems) / $perPage));
        $page = min($totalPages, max(1, $requestedPage));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'total_pages' => $totalPages,
        ];
    }
}

if (!function_exists('eventsRenderPagination')) {
    function eventsRenderPagination(
        int $currentPage,
        int $totalPages,
        string $baseUrl = '',
        array $query = [],
        string $pageParam = 'p',
        string $ariaLabel = 'Sayfalama'
    ): string {
        $totalPages = max(1, $totalPages);
        if ($totalPages <= 1) {
            return '';
        }

        $currentPage = min($totalPages, max(1, $currentPage));
        $pageParam = $pageParam !== '' ? $pageParam : 'p';

        $buildUrl = static function (int $page) use ($baseUrl, $query, $pageParam): string {
            $params = $query;
            $params[$pageParam] = $page;
            $queryString = http_build_query($params);

            if ($queryString === '') {
                return $baseUrl !== '' ? $baseUrl : '?';
            }

            $separator = $baseUrl === '' ? '?' : (str_contains($baseUrl, '?') ? '&' : '?');
            return $baseUrl . $separator . $queryString;
        };

        $renderControl = static function (
            string $className,
            string $icon,
            string $label,
            ?int $page,
            bool $disabled = false,
            ?string $rel = null
        ) use ($buildUrl): string {
            $labelText = html_entity_decode(strip_tags($label), ENT_QUOTES, 'UTF-8');
            $content = '<i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i><span>' . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') . '</span>';
            $classes = 'ui-events-page-btn ui-events-page-control ' . $className . ($disabled ? ' is-disabled' : '');

            if ($disabled || $page === null) {
                return '<span class="' . $classes . '" aria-disabled="true">' . $content . '</span>';
            }

            $relAttr = $rel !== null ? ' rel="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"' : '';
            $ariaLabel = htmlspecialchars($labelText . ' sayfa', ENT_QUOTES, 'UTF-8');
            return '<a class="' . $classes . '" href="' . htmlspecialchars($buildUrl($page), ENT_QUOTES, 'UTF-8') . '"' . $relAttr . ' aria-label="' . $ariaLabel . '">' . $content . '</a>';
        };

        $pages = [1, $totalPages];
        for ($page = $currentPage - 2; $page <= $currentPage + 2; $page++) {
            if ($page >= 1 && $page <= $totalPages) {
                $pages[] = $page;
            }
        }
        $pages = array_values(array_unique($pages));
        sort($pages);

        $html = '<nav class="ui-events-pagination" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="ui-events-pagination-summary">Sayfa <strong>' . $currentPage . '</strong><span>/</span>' . $totalPages . '</div>';
        $html .= '<div class="ui-events-page-list">';
        $html .= $renderControl('ui-events-page-prev', 'bi-chevron-left', '&Ouml;nceki', $currentPage > 1 ? $currentPage - 1 : null, $currentPage <= 1, 'prev');

        $lastPage = 0;
        foreach ($pages as $page) {
            if ($lastPage > 0 && $page > $lastPage + 1) {
                $html .= '<span class="ui-events-page-ellipsis" aria-hidden="true">&hellip;</span>';
            }

            $isCurrent = $page === $currentPage;
            $classes = 'ui-events-page-btn ui-events-page-number' . ($isCurrent ? ' is-current' : '');
            $ariaCurrent = $isCurrent ? ' aria-current="page"' : '';
            $html .= '<a class="' . $classes . '" href="' . htmlspecialchars($buildUrl($page), ENT_QUOTES, 'UTF-8') . '"' . $ariaCurrent . ' aria-label="Sayfa ' . $page . ($isCurrent ? ' mevcut' : '') . '">' . $page . '</a>';
            $lastPage = $page;
        }

        $html .= $renderControl('ui-events-page-next', 'bi-chevron-right', 'Sonraki', $currentPage < $totalPages ? $currentPage + 1 : null, $currentPage >= $totalPages, 'next');
        $html .= '</div></nav>';

        return $html;
    }
}

if (!function_exists('eventsEligibleWheelRewards')) {
    function eventsEligibleWheelRewards(array $rewards, int $userPoints = 0): array
    {
        return array_values(array_filter($rewards, static function (array $reward) use ($userPoints): bool {
            $isActive = (int)($reward['is_active'] ?? 1) === 1;
            $weight = (float)($reward['probability'] ?? 0);
            $remaining = $reward['remaining_quantity'] ?? null;
            $minPoints = $reward['min_user_points'] ?? null;

            if (!$isActive || $weight <= 0) {
                return false;
            }

            if ($remaining !== null && (int)$remaining <= 0) {
                return false;
            }

            if ($minPoints !== null && $minPoints !== '' && $userPoints < (int)$minPoints) {
                return false;
            }

            return true;
        }));
    }
}

if (!function_exists('eventsPickWeightedReward')) {
    function eventsPickWeightedReward(array $rewards, ?callable $randomInt = null, int $userPoints = 0): ?array
    {
        $eligible = eventsEligibleWheelRewards($rewards, $userPoints);
        if ($eligible === []) {
            return null;
        }

        $scale = 1;
        foreach ($eligible as $reward) {
            $raw = (string)($reward['probability'] ?? '0');
            if (str_contains($raw, '.') && (float)$raw !== floor((float)$raw)) {
                $scale = 10000;
                break;
            }
        }

        $weights = [];
        $total = 0;
        foreach ($eligible as $index => $reward) {
            $weight = max(0, (int)round(((float)($reward['probability'] ?? 0)) * $scale));
            if ($weight <= 0) {
                continue;
            }
            $weights[$index] = $weight;
            $total += $weight;
        }

        if ($total <= 0) {
            return null;
        }

        $randomInt = $randomInt ?: static fn(int $min, int $max): int => random_int($min, $max);
        $cursor = (int)$randomInt(1, $total);

        foreach ($weights as $index => $weight) {
            $cursor -= $weight;
            if ($cursor <= 0) {
                return $eligible[$index];
            }
        }

        return end($eligible) ?: null;
    }
}

if (!function_exists('eventsCalculateExpiryAt')) {
    function eventsCalculateExpiryAt(?int $days, ?string $from = null): ?string
    {
        if ($days === null || $days <= 0) {
            return null;
        }

        $date = new DateTimeImmutable($from ?: 'now');
        return $date->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
    }
}

if (!function_exists('eventsAllowedRewardTypes')) {
    function eventsAllowedRewardTypes(): array
    {
        return ['points', 'custom', 'coupon', 'discount'];
    }
}

if (!function_exists('eventsAllowedRaffleStatuses')) {
    function eventsAllowedRaffleStatuses(): array
    {
        return ['draft', 'active', 'closed', 'drawn', 'cancelled'];
    }
}

if (!function_exists('eventsRaffleIsOpen')) {
    function eventsRaffleIsOpen(array $raffle, ?string $now = null): bool
    {
        if ((int)($raffle['is_active'] ?? 0) !== 1 || (string)($raffle['status'] ?? '') !== 'active') {
            return false;
        }

        $nowTimestamp = strtotime($now ?: 'now');
        $startTimestamp = strtotime((string)($raffle['start_date'] ?? ''));
        $endTimestamp = strtotime((string)($raffle['end_date'] ?? ''));

        return $nowTimestamp !== false
            && $startTimestamp !== false
            && $endTimestamp !== false
            && $nowTimestamp >= $startTimestamp
            && $nowTimestamp <= $endTimestamp;
    }
}

if (!function_exists('eventsRaffleAvailabilityLabel')) {
    function eventsRaffleAvailabilityLabel(array $raffle, ?string $now = null): string
    {
        if ((int)($raffle['is_active'] ?? 0) !== 1 || (string)($raffle['status'] ?? '') !== 'active') {
            return eventsStatusLabel((string)($raffle['status'] ?? 'draft'));
        }

        $nowTimestamp = strtotime($now ?: 'now');
        $startTimestamp = strtotime((string)($raffle['start_date'] ?? ''));
        $endTimestamp = strtotime((string)($raffle['end_date'] ?? ''));

        if ($nowTimestamp !== false && $startTimestamp !== false && $nowTimestamp < $startTimestamp) {
            return 'Yakında';
        }

        if ($nowTimestamp !== false && $endTimestamp !== false && $nowTimestamp > $endTimestamp) {
            return 'Sona erdi';
        }

        return eventsRaffleIsOpen($raffle, $now) ? 'Aktif' : 'Kapalı';
    }
}

if (!function_exists('eventsNormalizeDateTimeInput')) {
    function eventsNormalizeDateTimeInput(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('eventsNormalizeWheelRewardInput')) {
    function eventsNormalizeWheelRewardInput(array $input): array
    {
        $errors = [];
        $type = (string)($input['type'] ?? 'custom');
        $quantityRaw = trim((string)($input['quantity'] ?? ''));
        $remainingRaw = trim((string)($input['remaining_quantity'] ?? ''));
        $quantity = $quantityRaw === '' ? null : max(0, (int)$quantityRaw);
        $remaining = $remainingRaw === '' ? $quantity : max(0, (int)$remainingRaw);
        if ($quantity === null) {
            $remaining = null;
        } elseif ($remaining !== null) {
            $remaining = min($remaining, $quantity);
        }

        $data = [
            'name' => trim((string)($input['name'] ?? '')),
            'type' => $type,
            'value' => trim((string)($input['value'] ?? '')),
            'probability' => max(0, (float)($input['probability'] ?? 0)),
            'image_url' => trim((string)($input['image_url'] ?? '')),
            'display_order' => (int)($input['display_order'] ?? 0),
            'min_user_points' => trim((string)($input['min_user_points'] ?? '')) === '' ? null : max(0, (int)$input['min_user_points']),
            'quantity' => $quantity,
            'remaining_quantity' => $remaining,
            'expires_in_days' => trim((string)($input['expires_in_days'] ?? '')) === '' ? null : max(0, (int)$input['expires_in_days']),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];

        if ($data['name'] === '') {
            $errors['name'] = 'Ödül adı gerekli.';
        }
        if (!in_array($type, eventsAllowedRewardTypes(), true)) {
            $errors['type'] = 'Desteklenmeyen ödül türü.';
        }
        if ($data['value'] === '') {
            $errors['value'] = 'Ödül değeri gerekli.';
        }
        if ($data['probability'] <= 0) {
            $errors['probability'] = 'Åans yüzdesi 0’dan büyük olmalı.';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $data];
    }
}

if (!function_exists('eventsNormalizeRaffleInput')) {
    function eventsNormalizeRaffleInput(array $input): array
    {
        $errors = [];
        $status = (string)($input['status'] ?? 'draft');
        $startDate = eventsNormalizeDateTimeInput($input['start_date'] ?? null);
        $endDate = eventsNormalizeDateTimeInput($input['end_date'] ?? null);
        $drawDate = eventsNormalizeDateTimeInput($input['draw_date'] ?? null);

        $data = [
            'name' => trim((string)($input['name'] ?? '')),
            'description' => trim((string)($input['description'] ?? '')),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'draw_date' => $drawDate,
            'max_entries_per_user' => max(1, (int)($input['max_entries_per_user'] ?? 1)),
            'winner_count' => max(1, (int)($input['winner_count'] ?? 1)),
            'status' => $status,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'item_ids' => array_values(array_unique(array_filter(array_map('intval', (array)($input['item_ids'] ?? [])), static fn(int $id): bool => $id > 0))),
        ];

        if ($data['name'] === '') {
            $errors['name'] = 'Çekiliş adı gerekli.';
        }
        if (!$startDate) {
            $errors['start_date'] = 'Başlangıç tarihi gerekli.';
        }
        if (!$endDate) {
            $errors['end_date'] = 'Bitiş tarihi gerekli.';
        }
        if ($startDate && $endDate && strtotime($endDate) <= strtotime($startDate)) {
            $errors['end_date'] = 'Bitiş tarihi başlangıçtan sonra olmalı.';
        }
        if (!in_array($status, eventsAllowedRaffleStatuses(), true)) {
            $errors['status'] = 'Geçersiz çekiliş durumu.';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $data];
    }
}


if (!function_exists('eventsNormalizePrizePoolItemInput')) {
    function eventsNormalizePrizePoolItemInput(array $input): array
    {
        $errors = [];
        $type = (string)($input['type'] ?? 'custom');
        $quantity = max(0, (int)($input['quantity'] ?? 0));
        $remainingRaw = trim((string)($input['remaining_quantity'] ?? ''));
        $remaining = $remainingRaw === '' ? $quantity : min($quantity, max(0, (int)$remainingRaw));
        $data = [
            'name' => trim((string)($input['name'] ?? '')),
            'type' => $type,
            'value' => trim((string)($input['value'] ?? '')),
            'quantity' => $quantity,
            'remaining_quantity' => $remaining,
            'weight' => max(0, (float)($input['weight'] ?? 1)),
            'description' => trim((string)($input['description'] ?? '')),
            'expires_in_days' => trim((string)($input['expires_in_days'] ?? '')) === '' ? null : max(0, (int)$input['expires_in_days']),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
        if ($data['name'] === '') {
            $errors['name'] = 'Öğe adı gerekli.';
        }
        if (!in_array($type, eventsAllowedRewardTypes(), true)) {
            $errors['type'] = 'Desteklenmeyen ödül türü.';
        }
        if ($data['value'] === '') {
            $errors['value'] = 'Ödül değeri gerekli.';
        }
        if ($data['quantity'] <= 0) {
            $errors['quantity'] = 'Havuz öğesi stoğu 0’dan büyük olmalı.';
        }
        if ($data['weight'] <= 0) {
            $errors['weight'] = 'Åans yüzdesi 0’dan büyük olmalı.';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $data];
    }
}

if (!function_exists('eventsSelectRaffleWinners')) {
    function eventsSelectRaffleWinners(array $entries, int $winnerCount, ?callable $randomInt = null): array
    {
        $unique = [];
        foreach ($entries as $entry) {
            $userId = (int)($entry['user_id'] ?? 0);
            if ($userId > 0 && !isset($unique[$userId])) {
                $entry['user_id'] = $userId;
                $unique[$userId] = $entry;
            }
        }

        $pool = array_values($unique);
        $winnerCount = min(max(0, $winnerCount), count($pool));
        $randomInt = $randomInt ?: static fn(int $min, int $max): int => random_int($min, $max);
        $winners = [];

        while (count($winners) < $winnerCount && $pool !== []) {
            $index = $randomInt(0, count($pool) - 1);
            $winners[] = $pool[$index];
            array_splice($pool, $index, 1);
        }

        return $winners;
    }
}

if (!function_exists('eventsPickWeightedPrizePoolItem')) {
    function eventsPickWeightedPrizePoolItem(array $items, ?callable $randomInt = null): ?array
    {
        $eligible = array_values(array_filter($items, static function (array $item): bool {
            return (int)($item['is_active'] ?? 1) === 1
                && (float)($item['weight'] ?? 0) > 0
                && (int)($item['remaining_quantity'] ?? 0) > 0;
        }));

        if ($eligible === []) {
            return null;
        }

        $scale = 1;
        foreach ($eligible as $item) {
            $raw = (string)($item['weight'] ?? '0');
            if (str_contains($raw, '.') && (float)$raw !== floor((float)$raw)) {
                $scale = 10000;
                break;
            }
        }

        $total = 0;
        $weights = [];
        foreach ($eligible as $index => $item) {
            $weight = max(0, (int)round(((float)($item['weight'] ?? 0)) * $scale));
            if ($weight <= 0) {
                continue;
            }
            $weights[$index] = $weight;
            $total += $weight;
        }

        if ($total <= 0) {
            return null;
        }

        $randomInt = $randomInt ?: static fn(int $min, int $max): int => random_int($min, $max);
        $cursor = $randomInt(1, $total);
        foreach ($weights as $index => $weight) {
            $cursor -= $weight;
            if ($cursor <= 0) {
                return $eligible[$index];
            }
        }

        return end($eligible) ?: null;
    }
}

if (!function_exists('eventsValidateSqlIdentifier')) {
    function eventsValidateSqlIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1;
    }
}

if (!function_exists('eventsQuoteSqlIdentifier')) {
    function eventsQuoteSqlIdentifier(string $identifier): string
    {
        if (!eventsValidateSqlIdentifier($identifier)) {
            throw new InvalidArgumentException('Geçersiz SQL identifier.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('eventsValidatePointsTarget')) {
    function eventsValidatePointsTarget(array $config, ?PDO $pdo = null): array
    {
        $errors = [];
        $enabled = eventsConfigBool($config, 'points_system_enabled');
        $table = trim((string)($config['points_system_table'] ?? ''));
        $column = trim((string)($config['points_system_column'] ?? ''));
        $userColumn = trim((string)($config['points_system_user_id_column'] ?? 'id'));

        if (!$enabled) {
            return ['valid' => false, 'enabled' => false, 'errors' => ['points_system_enabled' => 'Puan sistemi kapalı.']];
        }
        if ($table === '' || !eventsValidateSqlIdentifier($table)) {
            $errors['points_system_table'] = 'Geçersiz puan tablosu.';
        }
        if ($column === '' || !eventsValidateSqlIdentifier($column)) {
            $errors['points_system_column'] = 'Geçersiz puan kolonu.';
        }
        if ($userColumn === '' || !eventsValidateSqlIdentifier($userColumn)) {
            $errors['points_system_user_id_column'] = 'Geçersiz kullanıcı ID kolonu.';
        }

        if ($errors === [] && $pdo) {
            try {
                $stmt = $pdo->query('SHOW COLUMNS FROM ' . eventsQuoteSqlIdentifier($table));
                $columns = array_map(
                    static fn(array $row): string => (string)($row['Field'] ?? ''),
                    $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : []
                );
                if (!in_array($column, $columns, true)) {
                    $errors['points_system_column'] = 'Puan kolonu tabloda bulunamadı.';
                }
                if (!in_array($userColumn, $columns, true)) {
                    $errors['points_system_user_id_column'] = 'Kullanıcı ID kolonu tabloda bulunamadı.';
                }
            } catch (Throwable $e) {
                $errors['points_system_table'] = 'Puan tablosu bulunamadı veya okunamadı.';
            }
        }

        return [
            'valid' => $errors === [],
            'enabled' => true,
            'errors' => $errors,
            'table' => $table,
            'column' => $column,
            'user_column' => $userColumn,
        ];
    }
}

if (!function_exists('eventsTablesReady')) {
    function eventsTablesReady(?PDO $pdo): bool
    {
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'events_config'");
            return $stmt && $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('eventsSeedDefaultConfig')) {
    function eventsSeedDefaultConfig(PDO $pdo): void
    {
        $stmt = $pdo->prepare("INSERT INTO events_config (config_key, config_value, value_type, created_at, updated_at)
            VALUES (:config_key, :config_value, :value_type, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value_type = VALUES(value_type), updated_at = updated_at");

        foreach (eventsDefaultConfig() as $key => $definition) {
            $stmt->execute([
                'config_key' => $key,
                'config_value' => $definition['value'],
                'value_type' => $definition['type'],
            ]);
        }
    }
}

if (!function_exists('eventsGetConfig')) {
    function eventsGetConfig(?PDO $pdo, bool $forceReload = false): array
    {
        static $cache = null;
        if ($cache !== null && !$forceReload) {
            return $cache;
        }

        $config = [];
        foreach (eventsDefaultConfig() as $key => $definition) {
            $config[$key] = (string)$definition['value'];
        }

        if (!$pdo || !eventsTablesReady($pdo)) {
            $cache = $config;
            return $cache;
        }

        try {
            $stmt = $pdo->query('SELECT config_key, config_value FROM events_config');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = (string)$row['config_key'];
                if (array_key_exists($key, $config)) {
                    $config[$key] = (string)($row['config_value'] ?? '');
                }
            }
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'eventsGetConfig']);
            }
        }

        $cache = $config;
        return $cache;
    }
}

if (!function_exists('eventsConfigBool')) {
    function eventsConfigBool(array $config, string $key): bool
    {
        return in_array(strtolower((string)($config[$key] ?? 'false')), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('eventsAuditLog')) {
    function eventsAuditLog(?PDO $pdo, string $action, ?string $subjectType = null, ?int $subjectId = null, array $details = [], ?int $userId = null): void
    {
        if (!$pdo || !eventsTablesReady($pdo) || !eventsTableExists($pdo, 'events_audit_log')) {
            return;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO events_audit_log (user_id, action, subject_type, subject_id, details, ip_address, created_at)
                VALUES (:user_id, :action, :subject_type, :subject_id, :details, :ip_address, NOW())");
            $stmt->execute([
                'user_id' => $userId ?? ($_SESSION['_auth_user_id'] ?? null),
                'action' => mb_substr($action, 0, 80),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'eventsAuditLog']);
            }
        }
    }
}

if (!function_exists('eventsTableExists')) {
    function eventsTableExists(?PDO $pdo, string $tableName): bool
    {
        if (!$pdo || $tableName === '' || preg_match('/^[A-Za-z0-9_]+$/', $tableName) !== 1) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$tableName]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('eventsExpirePendingRewards')) {
    function eventsExpirePendingRewards(?PDO $pdo, ?int $actorId = null): int
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("UPDATE events_user_rewards
                SET status = 'expired', updated_at = NOW()
                WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at <= NOW()");
            $stmt->execute();
            $expiredCount = $stmt->rowCount();
            if ($expiredCount > 0) {
                eventsAuditLog($pdo, 'reward_expire_sweep', 'user_reward', null, ['expired_count' => $expiredCount], $actorId);
            }

            return $expiredCount;
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Reward expiry sweep failed.', ['error' => $e->getMessage()], 'WARNING');
            return 0;
        }
    }
}

if (!function_exists('eventsRewardExpired')) {
    function eventsRewardExpired(array $reward, ?int $now = null): bool
    {
        $expiresAt = trim((string)($reward['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        $expiresAtTimestamp = strtotime($expiresAt);
        if ($expiresAtTimestamp === false) {
            return false;
        }

        return $expiresAtTimestamp <= ($now ?? time());
    }
}

if (!function_exists('eventsMarkRewardExpired')) {
    function eventsMarkRewardExpired(PDO $pdo, int $rewardId, ?int $actorId = null): void
    {
        if ($rewardId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("UPDATE events_user_rewards
            SET status = 'expired', updated_at = NOW()
            WHERE id = ? AND status = 'pending'");
        $stmt->execute([$rewardId]);
        if ($stmt->rowCount() > 0) {
            eventsAuditLog($pdo, 'reward_expired', 'user_reward', $rewardId, [], $actorId);
        }
    }
}

if (!function_exists('eventsPendingRewardRows')) {
    function eventsPendingRewardRows(?PDO $pdo, int $limit = 150): array
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return [];
        }

        eventsExpirePendingRewards($pdo);

        try {
            $limit = max(1, min(500, $limit));
            $stmt = $pdo->prepare("SELECT ur.*, u.name AS user_name, u.email AS user_email
                FROM events_user_rewards ur
                LEFT JOIN users u ON u.id = ur.user_id
                WHERE ur.status = 'pending'
                ORDER BY ur.id DESC
                LIMIT {$limit}");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Pending rewards list failed.', ['error' => $e->getMessage()], 'WARNING');
            return [];
        }
    }
}

if (!function_exists('eventsPendingRaffleRows')) {
    function eventsPendingRaffleRows(?PDO $pdo, int $limit = 50): array
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return [];
        }

        try {
            $limit = max(1, min(250, $limit));
            $stmt = $pdo->prepare("SELECT r.*,
                    (SELECT COUNT(DISTINCT e.user_id) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS unique_entry_count,
                    (SELECT COALESCE(SUM(i.remaining_quantity), 0)
                        FROM events_prize_pool_items i
                        INNER JOIN events_raffle_items ri ON ri.item_id = i.id
                        WHERE ri.raffle_id = r.id AND i.is_active = 1) AS prize_stock
                FROM events_raffles r
                WHERE r.status IN ('active', 'closed') AND r.is_active = 1 AND r.end_date <= NOW()
                ORDER BY r.end_date ASC
                LIMIT {$limit}");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                $row['is_draw_ready'] = (int)($row['unique_entry_count'] ?? 0) >= (int)($row['winner_count'] ?? 0)
                    && (int)($row['prize_stock'] ?? 0) >= (int)($row['winner_count'] ?? 0);
            }
            unset($row);

            return $rows;
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Pending raffles list failed.', ['error' => $e->getMessage()], 'WARNING');
            return [];
        }
    }
}

if (!function_exists('eventsPendingOverview')) {
    function eventsPendingOverview(?PDO $pdo): array
    {
        $overview = [
            'reward_count' => 0,
            'raffle_count' => 0,
            'total' => 0,
        ];

        if (!$pdo || !eventsTablesReady($pdo)) {
            return $overview;
        }

        eventsExpirePendingRewards($pdo);

        try {
            $overview['reward_count'] = (int)$pdo->query("SELECT COUNT(*) FROM events_user_rewards WHERE status = 'pending'")->fetchColumn();
            $overview['raffle_count'] = (int)$pdo->query("SELECT COUNT(*) FROM events_raffles WHERE status IN ('active', 'closed') AND is_active = 1 AND end_date <= NOW()")->fetchColumn();
            $overview['total'] = $overview['reward_count'] + $overview['raffle_count'];
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Pending overview failed.', ['error' => $e->getMessage()], 'WARNING');
        }

        return $overview;
    }
}

if (!function_exists('eventsWheelUsageState')) {
    function eventsWheelUsageState(?PDO $pdo, int $userId, array $config, ?int $now = null): array
    {
        $now = $now ?? time();
        $dailyLimit = max(0, (int)($config['wheel_daily_limit'] ?? 0));
        $hourlyLimit = max(0, (int)($config['wheel_hourly_limit'] ?? 0));
        $cooldownSeconds = max(0, (int)($config['wheel_spin_cooldown_seconds'] ?? 0));
        $extraSpinCost = max(0, (int)($config['wheel_extra_spin_cost'] ?? 0));

        $usage = [
            'daily_limit' => $dailyLimit,
            'hourly_limit' => $hourlyLimit,
            'cooldown_seconds' => $cooldownSeconds,
            'extra_spin_cost' => $extraSpinCost,
            'daily_count' => 0,
            'hourly_count' => 0,
            'remaining_daily' => $dailyLimit > 0 ? $dailyLimit : null,
            'remaining_hourly' => $hourlyLimit > 0 ? $hourlyLimit : null,
            'cooldown_remaining' => 0,
            'bonus_spin_count' => 0,
            'last_spin_at' => null,
            'next_spin_at' => null,
            'next_spin_at_epoch' => null,
            'limit_reason' => '',
            'limit_blocked' => false,
            'can_spin_free' => true,
            'can_spin_with_bonus' => false,
            'can_spin_with_extra' => false,
            'can_spin_now' => true,
        ];

        if (!$pdo || !eventsTablesReady($pdo) || $userId <= 0) {
            return $usage;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ? AND created_at >= CURDATE()");
            $stmt->execute([$userId]);
            $usage['daily_count'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$userId]);
            $usage['hourly_count'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT created_at FROM events_wheel_spins WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $lastSpinAt = $stmt->fetchColumn();
            if ($lastSpinAt) {
                $usage['last_spin_at'] = (string)$lastSpinAt;
                $lastSpinTimestamp = strtotime((string)$lastSpinAt);
                if ($lastSpinTimestamp !== false && $cooldownSeconds > 0) {
                    $elapsed = max(0, $now - $lastSpinTimestamp);
                    $usage['cooldown_remaining'] = max(0, $cooldownSeconds - $elapsed);
                    if ($usage['cooldown_remaining'] > 0) {
                        $nextSpinAt = $lastSpinTimestamp + $cooldownSeconds;
                        $usage['next_spin_at_epoch'] = $nextSpinAt;
                        $usage['next_spin_at'] = date('Y-m-d H:i:s', $nextSpinAt);
                    }
                }
            }

            $usage['remaining_daily'] = $dailyLimit > 0 ? max(0, $dailyLimit - (int)$usage['daily_count']) : null;
            $usage['remaining_hourly'] = $hourlyLimit > 0 ? max(0, $hourlyLimit - (int)$usage['hourly_count']) : null;

            if (function_exists('eventsTasksTablesReady') && eventsTasksTablesReady($pdo)) {
                $bonusStmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_quantity), 0)
                    FROM events_user_bonus_spins
                    WHERE user_id = ? AND remaining_quantity > 0 AND (expires_at IS NULL OR expires_at >= NOW())");
                $bonusStmt->execute([$userId]);
                $usage['bonus_spin_count'] = (int)$bonusStmt->fetchColumn();
            }

            $dailyExceeded = $dailyLimit > 0 && (int)$usage['daily_count'] >= $dailyLimit;
            $hourlyExceeded = $hourlyLimit > 0 && (int)$usage['hourly_count'] >= $hourlyLimit;
            $cooldownActive = (int)$usage['cooldown_remaining'] > 0;

            if ($cooldownActive) {
                $usage['limit_reason'] = 'cooldown';
            } elseif ($dailyExceeded) {
                $usage['limit_reason'] = 'daily';
            } elseif ($hourlyExceeded) {
                $usage['limit_reason'] = 'hourly';
            }

            $limitExceeded = $dailyExceeded || $hourlyExceeded;
            $usage['can_spin_free'] = !$cooldownActive && !$limitExceeded;
            $usage['can_spin_with_bonus'] = !$cooldownActive && $limitExceeded && (int)$usage['bonus_spin_count'] > 0;
            $usage['can_spin_with_extra'] = !$cooldownActive && $limitExceeded && $extraSpinCost > 0;
            $usage['can_spin_now'] = $usage['can_spin_free'] || $usage['can_spin_with_bonus'] || $usage['can_spin_with_extra'];
            $usage['limit_blocked'] = !$usage['can_spin_now'];
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Wheel usage state failed.', ['error' => $e->getMessage()], 'WARNING');
        }

        return $usage;
    }
}

if (!function_exists('eventsLimitLabel')) {
    function eventsLimitLabel(mixed $value): string
    {
        return $value === null ? 'Limitsiz' : (string)max(0, (int)$value);
    }
}

if (!function_exists('eventsRenderPublicEmptyState')) {
    function eventsRenderPublicEmptyState(string $icon, string $title, string $description, string $actionHtml = ''): string
    {
        $html = '<div class="ui-events-empty ui-events-empty-state ui-events-public-empty-state ui-empty" data-ui-events-empty-state>';
        $html .= '<div class="ui-events-empty-icon"><i class="bi ' . e($icon) . '" aria-hidden="true"></i></div>';
        $html .= '<h3>' . e($title) . '</h3>';
        $html .= '<p>' . e($description) . '</p>';
        if ($actionHtml !== '') {
            $html .= '<div class="ui-events-empty-action">' . $actionHtml . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('eventsErrorLog')) {
    function eventsErrorLog(?PDO $pdo, string $message, array $context = [], string $level = 'ERROR'): void
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            if (function_exists('appFileLog')) {
                appFileLog(strtolower($level), $message, $context);
            }
            return;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO events_error_log (level, message, context, user_id, ip_address, user_agent, created_at)
                VALUES (:level, :message, :context, :user_id, :ip_address, :user_agent, NOW())");
            $stmt->execute([
                'level' => in_array($level, ['ERROR', 'WARNING', 'INFO', 'DEBUG'], true) ? $level : 'ERROR',
                'message' => $message,
                'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'user_id' => $_SESSION['_auth_user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'eventsErrorLog']);
            }
        }
    }
}

if (!function_exists('eventsAdminStats')) {
    function eventsAdminStats(?PDO $pdo): array
    {
        $stats = [
            'ready' => eventsTablesReady($pdo),
            'active_wheel_rewards' => 0,
            'active_raffles' => 0,
            'pending_rewards' => 0,
            'unread_notifications' => 0,
            'recent_audit' => [],
        ];

        if (!$pdo || !$stats['ready']) {
            return $stats;
        }

        try {
            $stats['active_wheel_rewards'] = (int)$pdo->query("SELECT COUNT(*) FROM events_wheel_rewards WHERE is_active = 1")->fetchColumn();
            $stats['active_raffles'] = (int)$pdo->query("SELECT COUNT(*) FROM events_raffles WHERE is_active = 1 AND status = 'active'")->fetchColumn();
            $stats['pending_rewards'] = (int)$pdo->query("SELECT COUNT(*) FROM events_user_rewards WHERE status = 'pending'")->fetchColumn();
            $stats['unread_notifications'] = (int)$pdo->query("SELECT COUNT(*) FROM events_notifications WHERE is_read = 0")->fetchColumn();
            if (eventsTableExists($pdo, 'events_audit_log')) {
                $stmt = $pdo->query("SELECT action, subject_type, subject_id, created_at FROM events_audit_log ORDER BY id DESC LIMIT 8");
                $stats['recent_audit'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Events admin stats could not be loaded.', ['error' => $e->getMessage()], 'WARNING');
        }

        return $stats;
    }
}

if (!function_exists('eventsUserOverview')) {
    function eventsUserOverview(?PDO $pdo, int $userId): array
    {
        $overview = [
            'ready' => eventsTablesReady($pdo),
            'today_spins' => 0,
            'pending_rewards' => 0,
            'active_raffles' => [],
            'recent_rewards' => [],
            'recent_spins' => [],
            'notifications' => [],
        ];

        if (!$pdo || !$overview['ready'] || $userId <= 0) {
            return $overview;
        }

        try {
            eventsExpirePendingRewards($pdo);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ? AND created_at >= CURDATE()");
            $stmt->execute([$userId]);
            $overview['today_spins'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_user_rewards WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$userId]);
            $overview['pending_rewards'] = (int)$stmt->fetchColumn();

            $overview['active_raffles'] = eventsActiveRaffles($pdo, $userId, 6);
            $overview['recent_rewards'] = eventsUserRewards($pdo, $userId, 6);
            $overview['recent_spins'] = eventsWheelHistory($pdo, $userId, 6);
            $overview['notifications'] = eventsNotifications($pdo, $userId, 5);
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Events user overview could not be loaded.', ['error' => $e->getMessage()], 'WARNING');
        }

        return $overview;
    }
}

if (!function_exists('eventsActiveRaffles')) {
    function eventsActiveRaffles(?PDO $pdo, ?int $userId = null, int $limit = 20): array
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT r.*,
                    (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS entry_count,
                    (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id AND e.user_id = :user_id) AS user_entries
                FROM events_raffles r
                WHERE r.is_active = 1 AND r.status IN ('active', 'closed', 'drawn')
                ORDER BY FIELD(r.status, 'active', 'closed', 'drawn'), r.end_date ASC
                LIMIT {$limit}");
            $stmt->execute(['user_id' => $userId ?? 0]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('eventsDrawnRafflesWithWinners')) {
    function eventsDrawnRafflesWithWinners(?PDO $pdo, int $limit = 30): array
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT r.*,
                    d.created_at AS draw_date_actual,
                    (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS entry_count,
                    (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
                     FROM events_raffle_winners w 
                     LEFT JOIN users u ON u.id = w.user_id 
                     WHERE w.raffle_id = r.id) AS winner_names
                FROM events_raffles r
                INNER JOIN events_raffle_draws d ON d.raffle_id = r.id
                WHERE r.status = 'drawn' AND r.is_active = 1
                ORDER BY d.id DESC
                LIMIT {$limit}");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
if (!function_exists('eventsUserRewards')) {
    function eventsUserRewards(?PDO $pdo, int $userId, int $limit = 20): array
    {
        if (!$pdo || !eventsTablesReady($pdo) || $userId <= 0) {
            return [];
        }

        try {
            eventsExpirePendingRewards($pdo);

            $stmt = $pdo->prepare("SELECT * FROM events_user_rewards WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('eventsWheelHistory')) {
    function eventsWheelHistory(?PDO $pdo, int $userId, int $limit = 20): array
    {
        if (!$pdo || !eventsTablesReady($pdo) || $userId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT s.*, r.name AS reward_name, r.type AS reward_type, r.value AS reward_value
                FROM events_wheel_spins s
                LEFT JOIN events_wheel_rewards r ON r.id = s.reward_id
                WHERE s.user_id = ?
                ORDER BY s.id DESC
                LIMIT {$limit}");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('eventsNotifications')) {
    function eventsNotifications(?PDO $pdo, int $userId, int $limit = 20): array
    {
        if (!$pdo || !eventsTablesReady($pdo) || $userId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM events_notifications WHERE user_id = ? ORDER BY is_read ASC, id DESC LIMIT {$limit}");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('eventsFormatDateTime')) {
    function eventsFormatDateTime(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        if (function_exists('formatAppDateTime')) {
            return formatAppDateTime($value);
        }

        return date('d.m.Y H:i', strtotime($value));
    }
}

if (!function_exists('eventsStatusLabel')) {
    function eventsStatusLabel(string $status): string
    {
        return [
            'draft' => 'Taslak',
            'active' => 'Aktif',
            'inactive' => 'Pasif',
            'closed' => 'Kapalı',
            'drawn' => 'Sonuçlandı',
            'cancelled' => 'İptal edildi',
            'pending' => 'Bekliyor',
            'claimed' => 'Teslim edildi',
            'available' => 'Alınabilir',
            'completed' => 'Alınabilir',
            'expired' => 'Süresi doldu',
        ][$status] ?? ucfirst($status);
    }
}

if (!function_exists('eventsSourceLabel')) {
    function eventsSourceLabel(string $source): string
    {
        return [
            'wheel' => 'Çark Çevirme',
            'raffle' => 'Çekiliş',
            'task' => 'Görev Tamamlama',
            'admin' => 'Admin (Manuel)',
            'activity' => 'Aktivite',
        ][$source] ?? ucfirst($source);
    }
}

if (!function_exists('eventsStatusBadgeClass')) {
    function eventsStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'active', 'claimed', 'completed', 'available', 'joined', 'ready' => 'ui-events-badge-success',
            'pending', 'draft', 'closed', 'progress' => 'ui-events-badge-warning',
            'cancelled', 'expired', 'inactive', 'drawn' => 'ui-events-badge-muted',
            default => ''
        };
    }
}

if (!function_exists('eventsRewardCanBeAppliedStatus')) {
    function eventsRewardCanBeAppliedStatus(string $status): bool
    {
        return $status === 'pending';
    }
}

if (!function_exists('eventsApplyPointsReward')) {
    function eventsApplyPointsReward(PDO $pdo, int $rewardId, array $config, int $actorId): array
    {
        $target = eventsValidatePointsTarget($config);
        if (!$target['valid']) {
            return ['success' => false, 'error' => 'points_target_invalid', 'message' => implode(' ', $target['errors'])];
        }

        $table = eventsQuoteSqlIdentifier($target['table']);
        $column = eventsQuoteSqlIdentifier($target['column']);
        $userColumn = eventsQuoteSqlIdentifier($target['user_column']);

        $tableCheck = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($target['table']));
        if (!$tableCheck || $tableCheck->rowCount() < 1) {
            return ['success' => false, 'error' => 'points_table_missing', 'message' => 'Puan tablosu bulunamadı.'];
        }
        foreach ([$target['column'], $target['user_column']] as $columnName) {
            $columnCheck = $pdo->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $pdo->quote($columnName));
            if (!$columnCheck || $columnCheck->rowCount() < 1) {
                return ['success' => false, 'error' => 'points_column_missing', 'message' => 'Puan sistemi kolonları bulunamadı.'];
            }
        }

        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM events_user_rewards WHERE id = ? FOR UPDATE");
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reward) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'reward_not_found', 'message' => 'Ödül bulunamadı.'];
            }
            if (eventsRewardExpired($reward)) {
                eventsMarkRewardExpired($pdo, $rewardId, $actorId);
                if ($started) {
                    $pdo->commit();
                }
                return ['success' => false, 'error' => 'reward_expired', 'message' => 'Bu odulun suresi dolmus.'];
            }
            if (!eventsRewardCanBeAppliedStatus((string)$reward['status']) || (string)$reward['reward_type'] !== 'points') {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'reward_not_applicable', 'message' => 'Bu ödül puan uygulamaya uygun değil.'];
            }

            $points = max(0, (int)$reward['reward_value']);
            if ($points <= 0) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'points_value_invalid', 'message' => 'Puan değeri geçersiz.'];
            }

            $updateStmt = $pdo->prepare("UPDATE {$table} SET {$column} = COALESCE({$column}, 0) + :points WHERE {$userColumn} = :user_id");
            $updateStmt->execute(['points' => $points, 'user_id' => (int)$reward['user_id']]);
            if ($updateStmt->rowCount() < 1) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'points_user_missing', 'message' => 'Puan uygulanacak kullanıcı kaydı bulunamadı.'];
            }

            $pdo->prepare("UPDATE events_user_rewards SET status = 'claimed', claimed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$rewardId]);
            eventsAuditLog($pdo, 'points_apply', 'user_reward', $rewardId, ['points' => $points, 'table' => $target['table'], 'column' => $target['column']], $actorId);
            if ($started) {
                $pdo->commit();
            }

            return ['success' => true, 'reward_id' => $rewardId, 'points' => $points];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('eventsApplyCustomReward')) {
    function eventsApplyCustomReward(PDO $pdo, int $rewardId, int $actorId, string $note = '', string $baseUri = ''): array
    {
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM events_user_rewards WHERE id = ? FOR UPDATE");
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reward) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'reward_not_found', 'message' => 'Ödül bulunamadı.'];
            }
            if (eventsRewardExpired($reward)) {
                eventsMarkRewardExpired($pdo, $rewardId, $actorId);
                if ($started) {
                    $pdo->commit();
                }
                return ['success' => false, 'error' => 'reward_expired', 'message' => 'Bu odulun suresi dolmus.'];
            }
            if (!eventsRewardCanBeAppliedStatus((string)$reward['status'])) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'error' => 'reward_not_applicable', 'message' => 'Bu ödül özel uygulamaya uygun değil.'];
            }

            // Update status first
            $updateStmt = $pdo->prepare("UPDATE events_user_rewards SET status = 'claimed', claimed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$rewardId]);

            // Try to insert notification, but don't fail if it doesn't work
            try {
                $pdo->prepare("INSERT INTO events_notifications (user_id, type, title, message, related_type, related_id, action_url, priority, created_at)
                    VALUES (?, 'reward_claimed', 'Ödülünüz uygulandı', ?, 'reward', ?, ?, 'medium', NOW())")
                    ->execute([(int)$reward['user_id'], 'Uygulanan ödül: ' . (string)$reward['reward_name'], $rewardId, eventsPublicUrl('rewards')]);
            } catch (Throwable $notifError) {
                // Log but don't fail - notification is not critical
                eventsErrorLog($pdo, 'Reward notification insert failed', ['error' => $notifError->getMessage(), 'reward_id' => $rewardId], 'WARNING');
            }

            eventsAuditLog($pdo, 'custom_reward_apply', 'user_reward', $rewardId, ['note' => mb_substr($note, 0, 500)], $actorId);

            if ($started) {
                $pdo->commit();
            }

            return ['success' => true, 'reward_id' => $rewardId];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('eventsRenderBanner')) {
    function eventsRenderBanner(array $config): string
    {
        if (!eventsConfigBool($config, 'events_banner_enabled')) {
            return '';
        }

        $text = trim((string)($config['events_banner_text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $style = (string)($config['events_banner_style'] ?? 'info');
        $validStyles = ['info', 'warning', 'success', 'danger'];
        $styleClass = in_array($style, $validStyles, true) ? $style : 'info';

        $html = '<div class="ui-events-global-banner ui-events-global-banner-' . $styleClass . '">';
        $html .= '<i class="bi bi-megaphone-fill ui-events-global-banner-icon"></i> ' . htmlspecialchars($text);
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('eventsDrawRaffle')) {
    function eventsDrawRaffle(PDO $pdo, int $raffleId, array $config, ?int $adminId = null, string $notes = ''): array
    {
        $stmt = $pdo->prepare("SELECT * FROM events_raffles WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stmt->execute([$raffleId]);
        $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$raffle) {
            throw new RuntimeException('Çekiliş bulunamadı.');
        }
        if (in_array((string)$raffle['status'], ['drawn', 'cancelled', 'draft'], true)) {
            throw new RuntimeException('Bu çekiliş çekime uygun değil.');
        }
        
        $endTime = strtotime((string)$raffle['end_date']);
        if ($endTime === false || $endTime > time()) {
            throw new RuntimeException('Bu çekilişin süresi henüz dolmadı.');
        }

        $entryStmt = $pdo->prepare("SELECT id, user_id, entry_type, created_at FROM events_raffle_entries WHERE raffle_id = ? ORDER BY id ASC");
        $entryStmt->execute([$raffleId]);
        $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $winnerCount = max(1, (int)$raffle['winner_count']);
        
        $winners = eventsSelectRaffleWinners($entries, $winnerCount);
        if (count($winners) < $winnerCount) {
            throw new RuntimeException('Kazanan sayısı için yeterli benzersiz katılımcı yok.');
        }

        $itemStmt = $pdo->prepare("SELECT i.*
            FROM events_prize_pool_items i
            INNER JOIN events_raffle_items ri ON ri.item_id = i.id
            WHERE ri.raffle_id = ? AND i.is_active = 1 AND i.remaining_quantity > 0
            ORDER BY i.id ASC
            FOR UPDATE");
        $itemStmt->execute([$raffleId]);
        $poolItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $availableStock = array_sum(array_map(static fn(array $item): int => (int)$item['remaining_quantity'], $poolItems));
        if ($availableStock < $winnerCount) {
            throw new RuntimeException('Kazanan sayısı için yeterli havuz stoğu yok.');
        }

        $drawStmt = $pdo->prepare("INSERT INTO events_raffle_draws (raffle_id, drawn_by, notes, created_at) VALUES (?, ?, ?, NOW())");
        $drawStmt->execute([$raffleId, $adminId, $notes !== '' ? $notes : null]);
        $drawId = (int)$pdo->lastInsertId();

        $rewardStmt = $pdo->prepare("INSERT INTO events_user_rewards (user_id, source_type, source_id, reward_name, reward_type, reward_value, status, expires_at, created_at, updated_at)
            VALUES (?, 'raffle', ?, ?, ?, ?, 'pending', ?, NOW(), NOW())");
        $winnerStmt = $pdo->prepare("INSERT INTO events_raffle_winners (raffle_id, draw_id, user_id, pool_item_id, user_reward_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stockStmt = $pdo->prepare("UPDATE events_prize_pool_items SET remaining_quantity = remaining_quantity - 1, updated_at = NOW() WHERE id = ? AND remaining_quantity > 0");
        $notificationStmt = $pdo->prepare("INSERT INTO events_notifications (user_id, type, title, message, related_type, related_id, action_url, priority, created_at)
            VALUES (?, 'raffle_win', 'Çekiliş ödülü kazandınız', ?, 'raffle', ?, ?, 'high', NOW())");

        $createdWinners = [];
        global $baseUri;
        
        foreach ($winners as $winner) {
            $item = eventsPickWeightedPrizePoolItem($poolItems);
            if (!$item) {
                throw new RuntimeException('Uygun ödül havuzu öğesi bulunamadı.');
            }

            $stockStmt->execute([(int)$item['id']]);
            if ($stockStmt->rowCount() < 1) {
                throw new RuntimeException('Ödül stoğu güncellenemedi.');
            }

            foreach ($poolItems as &$poolItem) {
                if ((int)$poolItem['id'] === (int)$item['id']) {
                    $poolItem['remaining_quantity'] = (int)$poolItem['remaining_quantity'] - 1;
                    break;
                }
            }
            unset($poolItem);

            $expiresAt = eventsCalculateExpiryAt($item['expires_in_days'] !== null ? (int)$item['expires_in_days'] : (int)$config['raffle_reward_expiry_days']);
            $rewardStmt->execute([(int)$winner['user_id'], $raffleId, $item['name'], $item['type'], $item['value'], $expiresAt]);
            $userRewardId = (int)$pdo->lastInsertId();
            $pointsApplied = false;
            if ((string)$item['type'] === 'points' && eventsValidatePointsTarget($config)['valid']) {
                $pointsResult = eventsApplyPointsReward($pdo, $userRewardId, $config, $adminId);
                $pointsApplied = (bool)($pointsResult['success'] ?? false);
            }
            $winnerStmt->execute([$raffleId, $drawId, (int)$winner['user_id'], (int)$item['id'], $userRewardId]);
            $notificationStmt->execute([
                (int)$winner['user_id'],
                'Kazandığınız çekiliş ödülü: ' . (string)$item['name'],
                $raffleId,
                eventsPublicUrl('rewards'),
            ]);

            $createdWinners[] = [
                'user_id' => (int)$winner['user_id'],
                'pool_item_id' => (int)$item['id'],
                'user_reward_id' => $userRewardId,
                'reward_name' => (string)$item['name'],
                'status' => $pointsApplied ? 'claimed' : 'pending',
            ];
        }

        $updateRaffle = $pdo->prepare("UPDATE events_raffles SET status = 'drawn', draw_date = COALESCE(draw_date, NOW()), updated_at = NOW() WHERE id = ?");
        $updateRaffle->execute([$raffleId]);
        
        $actorId = $adminId;
        eventsAuditLog($pdo, 'raffle_draw', 'raffle', $raffleId, ['draw_id' => $drawId, 'winners' => $createdWinners, 'auto' => $adminId === null], $adminId);

        return [
            'draw_id' => $drawId,
            'winners' => $createdWinners,
        ];
    }
}

