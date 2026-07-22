<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

adminRequirePermission('notifications.view', 'Bildirim merkezini görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('notifications.manage', 'Bildirimleri yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}

$suppressAdminFooterToasts = true;

require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Bildirim Merkezi';
$currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$tab = $_GET['tab'] ?? 'history';
$legacyTabRedirects = ['templates' => 'site'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($legacyTabRedirects[(string) $tab])) {
    header('Location: notifications.php?tab=' . $legacyTabRedirects[(string) $tab]);
    exit;
}
$allowedTabs = ['history', 'new', 'site', 'email', 'settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'history';
}
$emailGroup = (string) ($_GET['email_group'] ?? 'account');
$allowedEmailGroups = ['account', 'admin', 'events', 'settings'];
if (!in_array($emailGroup, $allowedEmailGroups, true)) {
    $emailGroup = 'account';
}

function admin_notification_types(): array
{
    return [
        'info' => ['label' => 'Bilgi', 'icon' => 'bi-info-circle', 'class' => 'info'],
        'success' => ['label' => 'Başarılı', 'icon' => 'bi-check-circle', 'class' => 'success'],
        'warning' => ['label' => 'Uyarı', 'icon' => 'bi-exclamation-triangle', 'class' => 'warning'],
        'error' => ['label' => 'Hata', 'icon' => 'bi-x-circle', 'class' => 'error'],
        'system' => ['label' => 'Sistem', 'icon' => 'bi-cpu', 'class' => 'system'],
    ];
}

function admin_notification_settings_schema(): array
{
    return [
        'delivery' => [
            'title' => 'Gönderim Kuralları',
            'description' => 'Bildirim merkezinin açık olup olmadığını, hedefleme izinlerini ve kullanıcı tercihlerini belirler.',
            'items' => [
                ['key' => 'notif_center_enabled', 'type' => 'bool', 'label' => 'Bildirim Merkezi Aktif', 'help' => 'Kapatılırsa yeni bildirim gönderimi engellenir ve kullanıcı üst menü bildirimleri sessize alınır.', 'default' => '1'],
                ['key' => 'notif_allow_global_broadcasts', 'type' => 'bool', 'label' => 'Genel Yayınlara İzin Ver', 'help' => 'Hedef kullanıcı boş bırakılarak herkese bildirim gönderilmesine izin verir.', 'default' => '1'],
                ['key' => 'notif_allow_direct_messages', 'type' => 'bool', 'label' => 'Kullanıcıya Özel Bildirimlere İzin Ver', 'help' => 'Belirli kullanıcı ID hedefli bildirim gönderimini açar veya kapatır.', 'default' => '1'],
                ['key' => 'notif_respect_user_preferences', 'type' => 'bool', 'label' => 'Kullanıcı Tercihlerini Uygula', 'help' => 'Kullanıcının kapattığı bildirim tipleri üst menü ve kullanıcı bildirim sayfasında gizlenir.', 'default' => '1'],
                ['key' => 'notif_require_https_links', 'type' => 'bool', 'label' => 'Harici Linklerde HTTPS Zorunlu', 'help' => 'http:// ile başlayan harici bağlantıların gönderilmesini engeller. Site içi /link formatı serbesttir.', 'default' => '0'],
            ],
        ],
        'display' => [
            'title' => 'Görünüm ve Etkileşim',
            'description' => 'Kullanıcı arayüzünde kaç bildirim görüneceğini ve liste davranışını yönetir.',
            'items' => [
                ['key' => 'notif_show_header_badge', 'type' => 'bool', 'label' => 'Üst Menü Rozetini Göster', 'help' => 'Okunmamış bildirim sayısını üst menüde gösterir.', 'default' => '1'],
                ['key' => 'notif_auto_mark_link_click', 'type' => 'bool', 'label' => 'Linke Tıklayınca Okundu Yap', 'help' => 'Bildirim linki açıldığında okundu kaydı otomatik oluşturulur.', 'default' => '1'],
                ['key' => 'notif_enable_read_more', 'type' => 'bool', 'label' => 'Uzun Mesajlarda Genişletme Butonu', 'help' => 'Kullanıcı sayfasında uzun mesajlar için daha fazla göster düğmesi kullanılır.', 'default' => '1'],
                ['key' => 'notif_empty_state_tips', 'type' => 'bool', 'label' => 'Boş Durum Yardım Metni', 'help' => 'Bildirim olmadığında kullanıcıya kısa açıklama gösterir.', 'default' => '1'],
                ['key' => 'notif_dropdown_limit', 'type' => 'number', 'label' => 'Üst Menü Bildirim Limiti', 'help' => 'Üst menü açılır listesinde gösterilecek son bildirim sayısı.', 'default' => '5', 'min' => 1, 'max' => 20],
                ['key' => 'notif_user_page_per_page', 'type' => 'number', 'label' => 'Kullanıcı Sayfası Sayfa Başına', 'help' => 'Kullanıcı bildirim listesinde her sayfada gösterilecek kayıt sayısı.', 'default' => '10', 'min' => 5, 'max' => 50],
                ['key' => 'notif_user_message_lines', 'type' => 'number', 'label' => 'Kullanıcı Mesaj Satır Limiti', 'help' => 'Uzun mesajlar daraltıldığında görünecek satır sayısı.', 'default' => '3', 'min' => 1, 'max' => 8],
            ],
        ],
        'composer' => [
            'title' => 'Gönderim Formu Varsayılanları',
            'description' => 'Yeni bildirim oluşturma ekranındaki varsayılanlar ve güvenlik limitleri.',
            'items' => [
                ['key' => 'notif_default_type', 'type' => 'select', 'label' => 'Varsayılan Bildirim Tipi', 'help' => 'Yeni bildirim formunda otomatik seçilecek tip.', 'default' => 'info', 'options' => ['info' => 'Bilgi', 'success' => 'Başarılı', 'warning' => 'Uyarı', 'error' => 'Hata', 'system' => 'Sistem']],
                ['key' => 'notif_default_link', 'type' => 'text', 'label' => 'Varsayılan Link', 'help' => 'Yeni bildirimde link boş bırakılırsa bu adres kullanılır.', 'default' => ''],
                ['key' => 'notif_max_title_length', 'type' => 'number', 'label' => 'Maksimum Başlık Uzunluğu', 'help' => 'Gönderim sırasında başlık uzunluğu bu değerle sınırlandırılır.', 'default' => '120', 'min' => 20, 'max' => 255],
                ['key' => 'notif_max_message_length', 'type' => 'number', 'label' => 'Maksimum Mesaj Uzunluğu', 'help' => 'Gönderim sırasında mesaj uzunluğu bu değerle sınırlandırılır.', 'default' => '800', 'min' => 80, 'max' => 5000],
            ],
        ],
        'history' => [
            'title' => 'Geçmiş ve Bakım',
            'description' => 'Geçmiş tablosunun yoğunluğunu ve eski kayıt temizliğini kontrol eder.',
            'items' => [
                ['key' => 'notif_history_per_page', 'type' => 'number', 'label' => 'Geçmiş Sayfa Başına', 'help' => 'Admin geçmiş tablosunda her sayfada gösterilecek kayıt sayısı.', 'default' => '10', 'min' => 5, 'max' => 100],
                ['key' => 'notif_history_message_preview', 'type' => 'number', 'label' => 'Mesaj Önizleme Uzunluğu', 'help' => 'Geçmiş tablosunda mesaj metninin kaç karakter görüneceği.', 'default' => '140', 'min' => 40, 'max' => 500],
                ['key' => 'notif_retention_days', 'type' => 'number', 'label' => 'Saklama Süresi Gün', 'help' => 'Ayar kaydedildiğinde bu günden eski bildirimler silinir. 0 sınırsız saklar.', 'default' => '30', 'min' => 0, 'max' => 3650],
            ],
        ],
        'events' => [
            'title' => 'Olay Bildirimleri',
            'description' => 'Yorumlar, bahsetmeler, favori konular ve moderasyon kararlarından otomatik bildirim üretimini yönetir.',
            'items' => [
                ['key' => 'notif_events_enabled', 'type' => 'bool', 'label' => 'Otomatik Olay Bildirimleri', 'help' => 'Site içi yorum, bahsetme ve moderasyon olaylarından otomatik bildirim oluşturur.', 'default' => '1'],
                ['key' => 'notif_event_comments_enabled', 'type' => 'bool', 'label' => 'Yorum Olayları', 'help' => 'Konuya yorum, yoruma yanıt ve yorum onayı bildirimlerini etkinleştirir.', 'default' => '1'],
                ['key' => 'notif_event_mentions_enabled', 'type' => 'bool', 'label' => 'Bahsetme Olayları', 'help' => 'Yorumlarda @kullanıcı adı ile bahsedilen kullanıcılara bildirim gönderir.', 'default' => '1'],
                ['key' => 'notif_event_topic_moderation_enabled', 'type' => 'bool', 'label' => 'Konu Moderasyon Olayları', 'help' => 'Konu onaylandı, reddedildi veya revizyon istendi bildirimlerini yönetir.', 'default' => '1'],
                ['key' => 'notif_event_favorites_enabled', 'type' => 'bool', 'label' => 'Favori Konu Olayları', 'help' => 'Favoriye eklenen konulara yeni yorum geldiğinde bildirim üretir.', 'default' => '0'],
                ['key' => 'notif_event_skip_actor', 'type' => 'bool', 'label' => 'Kendi İşleminden Bildirim Alma', 'help' => 'Bir kullanıcı kendi yaptığı yorum veya işlemden bildirim almasın.', 'default' => '1'],
                ['key' => 'notif_event_dedupe_enabled', 'type' => 'bool', 'label' => 'Tekrar Bildirimlerini Engelle', 'help' => 'Aynı olay için aynı kullanıcıya tekrar bildirim oluşturulmasın.', 'default' => '1'],
            ],
        ],
        'automation' => [
            'title' => 'Otomatik Bildirimler',
            'description' => 'Yeni kullanıcılar için otomatik hoş geldin bildirimini ayarlar.',
            'items' => [
                ['key' => 'notif_system_sender', 'type' => 'text', 'label' => 'Sistem Gönderen Adı', 'help' => 'Otomatik bildirimlerde başlıkta kullanılacak kısa ad.', 'default' => 'Sistem'],
                ['key' => 'notif_welcome_enabled', 'type' => 'bool', 'label' => 'Site İçi Hoş Geldin Bildirimi', 'help' => 'Kayıt olan kullanıcıya yalnızca bildirim merkezinde otomatik sistem bildirimi gönderir.', 'default' => '0'],
                ['key' => 'notif_welcome_msg', 'type' => 'textarea', 'label' => 'Site İçi Hoş Geldin Mesajı', 'help' => 'Yeni üyeye bildirim merkezinde gösterilecek metin. E-posta metninden ayrıdır.', 'default' => 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.'],
            ],
        ],
    ];
}

function admin_notification_bool(array $settings, string $key, string $default = '1'): bool
{
    $value = $settings[$key] ?? $default;
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function admin_notification_bool_with_legacy(array $settings, string $key, string $default = '1', ?string $legacyKey = null): bool
{
    if (array_key_exists($key, $settings)) {
        return admin_notification_bool($settings, $key, $default);
    }
    if ($legacyKey !== null && array_key_exists($legacyKey, $settings)) {
        return admin_notification_bool($settings, $legacyKey, $default);
    }

    return admin_notification_bool([], 'default', $default);
}

function admin_notification_int(array $settings, string $key, int $default, int $min, int $max): int
{
    $value = (int) ($settings[$key] ?? $default);
    return max($min, min($max, $value));
}

function admin_notification_setting_value(array $settings, array $item): string
{
    if (
        !array_key_exists((string) $item['key'], $settings)
        && in_array((string) $item['key'], ['notif_admin_registration_site_enabled', 'notif_admin_registration_email_enabled'], true)
        && array_key_exists('notif_admin_registration_enabled', $settings)
    ) {
        return (string) $settings['notif_admin_registration_enabled'];
    }

    return (string) ($settings[$item['key']] ?? $item['default'] ?? '');
}

function admin_notification_save_value(array $item, array $source): string
{
    $key = $item['key'];
    if ($item['type'] === 'bool') {
        return isset($source[$key]) ? '1' : '0';
    }

    $value = trim((string) ($source[$key] ?? ($item['default'] ?? '')));
    if ($item['type'] === 'number') {
        $min = (int) ($item['min'] ?? 0);
        $max = (int) ($item['max'] ?? 999999);
        return (string) max($min, min($max, (int) $value));
    }

    if ($item['type'] === 'select') {
        $options = $item['options'] ?? [];
        return array_key_exists($value, $options) ? $value : (string) ($item['default'] ?? '');
    }

    return $value;
}

function admin_notification_flat_settings_schema(): array
{
    $flat = [];
    foreach (admin_notification_settings_schema() as $section) {
        foreach ($section['items'] as $item) {
            $flat[$item['key']] = $item;
        }
    }

    return $flat;
}

function admin_notification_email_settings_schema(): array
{
    return [
        ['key' => 'notif_email_channel_ready', 'type' => 'bool', 'label' => 'E-posta Kuyruğu Aktif', 'help' => 'E-posta açık kayıtlardan notification_email_queue kaydı oluşturur; cron worker bu kayıtları gönderir.', 'default' => '0'],
        ['key' => 'notif_admin_registration_email_enabled', 'type' => 'bool', 'label' => 'Yeni Kullanıcı Kaydı Admin E-postası', 'help' => 'Yeni kullanıcı kayıt olduğunda admin/yetkili hesaplara e-posta kuyruğu üzerinden bildirim gönderir.', 'default' => '1'],
        ['key' => 'notif_email_queue_max_attempts', 'type' => 'number', 'label' => 'E-posta Deneme Hakkı', 'help' => 'Worker başarısız gönderimleri en fazla bu kadar tekrar dener.', 'default' => '3', 'min' => 1, 'max' => 10],
    ];
}

function admin_notification_account_email_anchor(string $templateKey): string
{
    return 'account-email-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $templateKey);
}

function admin_notification_mail_diagnostic(array $mailResult, array $secrets = []): string
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

    return function_exists('mb_substr') ? mb_substr($diagnostic, 0, 700, 'UTF-8') : substr($diagnostic, 0, 700);
}

function admin_notification_json_attr(mixed $value): string
{
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]', ENT_QUOTES, 'UTF-8');
}

function admin_notification_route_badge(string $kind, ?string $label = null, ?string $title = null): string
{
    $meta = [
        'site' => ['class' => 'site', 'icon' => 'bi-bell', 'label' => 'Site İçi', 'title' => 'Bildirim merkezi ve üst menü üzerinden görünür.'],
        'email' => ['class' => 'email', 'icon' => 'bi-envelope', 'label' => 'E-posta', 'title' => 'E-posta kanalı üzerinden gönderilir.'],
        'user' => ['class' => 'user', 'icon' => 'bi-person', 'label' => 'Kullanıcı', 'title' => 'Kullanıcı tarafına gönderilir.'],
        'global' => ['class' => 'global', 'icon' => 'bi-people', 'label' => 'Tüm Kullanıcılar', 'title' => 'Tüm kullanıcıların bildirim akışında görünür.'],
        'admin' => ['class' => 'admin', 'icon' => 'bi-shield-lock', 'label' => 'Admin', 'title' => 'Admin veya yetkili hesaplara gönderilir.'],
        'admin_flow' => ['class' => 'admin-flow', 'icon' => 'bi-eye', 'label' => 'Admin Akışı', 'title' => 'Bu kayıt admin bildirim akışında da izlenir.'],
    ];
    $item = $meta[$kind] ?? ['class' => 'unknown', 'icon' => 'bi-question-circle', 'label' => $kind, 'title' => 'Bildirim hedef bilgisi.'];

    $label = $label !== null && trim($label) !== '' ? trim($label) : (string) $item['label'];
    $title = $title !== null && trim($title) !== '' ? trim($title) : (string) $item['title'];

    return '<span class="notif-route-badge notif-route-badge-' . htmlspecialchars((string) $item['class']) . '" title="' . htmlspecialchars($title) . '">'
        . '<i class="bi ' . htmlspecialchars((string) $item['icon']) . '"></i>'
        . '<span>' . htmlspecialchars($label) . '</span>'
        . '</span>';
}

function admin_notification_route_badges(mixed ...$items): string
{
    $html = '';
    foreach ($items as $item) {
        if (is_array($item)) {
            $html .= admin_notification_route_badge(
                (string) ($item['kind'] ?? ''),
                isset($item['label']) ? (string) $item['label'] : null,
                isset($item['title']) ? (string) $item['title'] : null
            );
            continue;
        }

        $html .= admin_notification_route_badge((string) $item);
    }

    return '<span class="notif-route-badges" aria-label="Bildirim kanalı ve hedefi">' . $html . '</span>';
}

function admin_notification_delivery_channel_badges(mixed $deliveryChannels): array
{
    $channels = [];
    if (is_array($deliveryChannels)) {
        $channels = $deliveryChannels;
    } else {
        $raw = trim((string) ($deliveryChannels ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $channels = is_array($decoded) ? $decoded : [$raw];
        }
    }

    $channelKinds = [];
    foreach ($channels as $channel) {
        $channel = strtolower(trim((string) $channel));
        if ($channel === '') {
            continue;
        }
        if (str_contains($channel, 'email')) {
            $channelKinds['email'] = true;
            continue;
        }
        if (in_array($channel, ['in_app', 'site', 'notification_center'], true)) {
            $channelKinds['site'] = true;
        }
    }

    if ($channelKinds === []) {
        $channelKinds['site'] = true;
    }

    return array_keys($channelKinds);
}

function admin_notification_history_target_badges(array $notification): string
{
    $items = [];
    foreach (admin_notification_delivery_channel_badges($notification['delivery_channels'] ?? null) as $channelKind) {
        $items[] = $channelKind;
    }

    $userId = (int) ($notification['user_id'] ?? 0);
    $isTargetAdmin = (int) ($notification['target_is_admin'] ?? 0) === 1;
    $isAdminFlow = (int) ($notification['is_admin_loggable'] ?? 0) === 1;

    if ($userId <= 0) {
        $items[] = 'global';
    } elseif ($isTargetAdmin) {
        $username = trim((string) ($notification['target_username'] ?? ''));
        $items[] = [
            'kind' => 'admin',
            'label' => $username !== '' ? 'Admin: ' . $username : 'Admin #' . $userId,
            'title' => 'Bu bildirim admin veya yetkili kullanıcı hesabına gönderildi.',
        ];
    } else {
        $username = trim((string) ($notification['target_username'] ?? ''));
        $items[] = [
            'kind' => 'user',
            'label' => $username !== '' ? 'Kullanıcı: ' . $username : 'Kullanıcı #' . $userId,
            'title' => 'Bu bildirim belirli bir kullanıcıya gönderildi.',
        ];
        if ($isAdminFlow) {
            $items[] = 'admin_flow';
        }
    }

    return admin_notification_route_badges(...$items);
}

function admin_notification_template_variables(string ...$parts): array
{
    preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', implode("\n", $parts), $matches);

    return array_values(array_unique($matches[1] ?? []));
}

function admin_notification_variable_names(array $variables): string
{
    return implode(', ', array_map(static fn (string $variable): string => '{{' . $variable . '}}', $variables));
}

function admin_notification_variable_issues(array $usedVariables, array $allowedVariables, array $requiredVariables = []): array
{
    return [
        'unknown' => array_values(array_diff($usedVariables, $allowedVariables)),
        'missing' => array_values(array_diff(array_values(array_unique($requiredVariables)), $usedVariables)),
    ];
}

function admin_notification_required_notification_variables(string $templateKey, string $channel): array
{
    $defaults = notificationTemplateDefaults();
    $default = $defaults[$templateKey] ?? null;
    if (!is_array($default)) {
        return [];
    }

    $fields = $channel === 'email'
        ? ['email_subject_template', 'email_body_template', 'email_link_template', 'email_preview_template']
        : ['title_template', 'message_template', 'link_template'];
    $parts = [];
    foreach ($fields as $field) {
        $parts[] = (string) ($default[$field] ?? '');
    }

    return admin_notification_template_variables(...$parts);
}

function admin_notification_validate_account_email_copy(string $templateKey, string $subject, string $body, bool $enforceRequired = true): void
{
    if (trim($subject) === '' || trim($body) === '') {
        throw new RuntimeException('Hesap e-posta konusu ve içeriği boş bırakılamaz.');
    }

    $usedVariables = admin_notification_template_variables($subject, $body);
    $requiredVariables = \App\Engine\Email\AccountEmailService::requiredVariables()[$templateKey] ?? [];
    $issues = admin_notification_variable_issues($usedVariables, \App\Engine\Email\AccountEmailService::allowedVariables(), $requiredVariables);
    if ($issues['unknown'] !== []) {
        throw new RuntimeException('Bilinmeyen hesap e-posta değişkeni: ' . admin_notification_variable_names($issues['unknown']));
    }
    if ($enforceRequired && $issues['missing'] !== []) {
        throw new RuntimeException('Zorunlu hesap e-posta değişkeni eksik: ' . admin_notification_variable_names($issues['missing']));
    }
}

function admin_notification_validate_admin_email_copy(string $templateKey, string $subject, string $body, string $actionLabel, bool $enforceRequired = true): void
{
    if (trim($subject) === '' || trim($body) === '') {
        throw new RuntimeException('Yönetici e-posta konusu ve içeriği boş bırakılamaz.');
    }

    $usedVariables = admin_notification_template_variables($subject, $body, $actionLabel);
    $requiredVariables = \App\Engine\Email\AdminEmailService::requiredVariables()[$templateKey] ?? [];
    $issues = admin_notification_variable_issues($usedVariables, array_keys(\App\Engine\Email\AdminEmailService::allowedVariables()), $requiredVariables);
    if ($issues['unknown'] !== []) {
        throw new RuntimeException('Bilinmeyen yönetici e-posta değişkeni: ' . admin_notification_variable_names($issues['unknown']));
    }
    if ($enforceRequired && $issues['missing'] !== []) {
        throw new RuntimeException('Zorunlu yönetici e-posta değişkeni eksik: ' . admin_notification_variable_names($issues['missing']));
    }
}

function admin_notification_validate_notification_email_variables(string $templateKey, array $input, bool $enforceRequired = true): void
{
    $usedVariables = admin_notification_template_variables(
        (string) ($input['email_subject_template'] ?? ''),
        (string) ($input['email_body_template'] ?? ''),
        (string) ($input['email_link_template'] ?? ''),
        (string) ($input['email_preview_template'] ?? '')
    );
    $requiredVariables = admin_notification_required_notification_variables($templateKey, 'email');
    $issues = admin_notification_variable_issues($usedVariables, array_keys(notificationTemplateAllowedVariables()), $requiredVariables);
    if ($issues['unknown'] !== []) {
        throw new RuntimeException('Bilinmeyen e-posta bildirim değişkeni: ' . admin_notification_variable_names($issues['unknown']));
    }
    if ($enforceRequired && $issues['missing'] !== []) {
        throw new RuntimeException('Zorunlu e-posta bildirim değişkeni eksik: ' . admin_notification_variable_names($issues['missing']));
    }
}

function admin_notification_validate_admin_registration_site_template(array $input): void
{
    $name = trim((string) ($input['name'] ?? ''));
    $title = trim((string) ($input['title_template'] ?? ''));
    $message = trim((string) ($input['message_template'] ?? ''));
    $link = trim((string) ($input['link_template'] ?? ''));
    $type = trim((string) ($input['type'] ?? 'system'));

    if ($name === '' || $title === '' || $message === '') {
        throw new RuntimeException('Metin adı, site içi başlık ve site içi mesaj alanları boş bırakılamaz.');
    }
    if (mb_strlen($name) > 160) {
        throw new RuntimeException('Metin adı en fazla 160 karakter olabilir.');
    }
    if (mb_strlen($title) > 255) {
        throw new RuntimeException('Site içi başlık en fazla 255 karakter olabilir.');
    }
    if (mb_strlen($message) > 5000) {
        throw new RuntimeException('Site içi mesaj en fazla 5000 karakter olabilir.');
    }
    if (mb_strlen($link) > 1024) {
        throw new RuntimeException('Site içi link en fazla 1024 karakter olabilir.');
    }
    if (!array_key_exists($type, admin_notification_types())) {
        throw new RuntimeException('Geçersiz bildirim tipi seçildi.');
    }

    $allowedVariables = function_exists('usersAdminRegistrationSiteAllowedVariables')
        ? array_keys(usersAdminRegistrationSiteAllowedVariables())
        : ['site_name', 'username', 'email', 'user_id', 'user_status', 'approval_status', 'admin_link', 'link'];
    $usedVariables = admin_notification_template_variables($title, $message, $link);
    $issues = admin_notification_variable_issues($usedVariables, $allowedVariables);
    if ($issues['unknown'] !== []) {
        throw new RuntimeException('Bilinmeyen admin kayıt bildirimi değişkeni: ' . admin_notification_variable_names($issues['unknown']));
    }
}

function admin_notification_admin_registration_site_input(array $source, array $fallback): array
{
    return [
        'name' => trim((string) ($source['notif_admin_registration_site_name'] ?? ($fallback['name'] ?? ''))),
        'description' => trim((string) ($source['notif_admin_registration_site_description'] ?? ($fallback['description'] ?? ''))),
        'type' => trim((string) ($source['notif_admin_registration_site_type'] ?? ($fallback['type'] ?? 'system'))),
        'title_template' => trim((string) ($source['notif_admin_registration_site_title_template'] ?? ($fallback['title_template'] ?? ''))),
        'message_template' => trim((string) ($source['notif_admin_registration_site_message_template'] ?? ($fallback['message_template'] ?? ''))),
        'link_template' => trim((string) ($source['notif_admin_registration_site_link_template'] ?? ($fallback['link_template'] ?? ''))),
    ];
}

function admin_notification_admin_registration_site_setting_values(array $input, string $enabled): array
{
    return [
        'notif_admin_registration_site_enabled' => $enabled,
        'notif_admin_registration_site_name' => $input['name'],
        'notif_admin_registration_site_description' => $input['description'],
        'notif_admin_registration_site_type' => $input['type'],
        'notif_admin_registration_site_title_template' => $input['title_template'],
        'notif_admin_registration_site_message_template' => $input['message_template'],
        'notif_admin_registration_site_link_template' => $input['link_template'],
    ];
}

function admin_notification_admin_registration_site_sample_payload(array $settings): array
{
    $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $adminLink = $baseUri !== '' ? $baseUri . '/admin/user-edit.php?id=1001' : '/admin/user-edit.php?id=1001';
    if (function_exists('usersAdminRegistrationSiteVariables')) {
        return usersAdminRegistrationSiteVariables(
            1001,
            'Test Kullanıcısı',
            'test@example.com',
            false,
            $adminLink,
            $settings
        );
    }

    $siteName = trim((string) ($settings['site_name'] ?? ''));
    return [
        'site_name' => $siteName !== '' ? $siteName : 'Sistem',
        'username' => 'Test Kullanıcısı',
        'email' => 'test@example.com',
        'user_id' => '1001',
        'user_status' => 'Aktif',
        'approval_status' => 'Onay gerekmiyor',
        'admin_link' => $adminLink,
        'link' => $adminLink,
    ];
}

function admin_notification_render_admin_registration_site_preview(array $input, array $settings): array
{
    $payload = admin_notification_admin_registration_site_sample_payload($settings);
    $render = static function (string $template) use ($payload): string {
        if (function_exists('usersRenderAdminRegistrationSiteTemplate')) {
            return usersRenderAdminRegistrationSiteTemplate($template, $payload);
        }

        return trim((string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($payload): string {
            $value = $payload[$matches[1]] ?? '';

            return is_scalar($value) || $value === null ? (string) $value : '';
        }, $template));
    };

    $type = (string) ($input['type'] ?? 'system');
    if (!array_key_exists($type, admin_notification_types())) {
        $type = 'system';
    }

    return [
        'type' => $type,
        'title' => $render((string) ($input['title_template'] ?? '')) ?: 'Yeni kullanıcı kaydı',
        'message' => $render((string) ($input['message_template'] ?? '')) ?: 'Test kullanıcısı yeni hesap oluşturdu.',
        'link' => $render((string) ($input['link_template'] ?? '')),
    ];
}

function admin_notification_save_setting_values(PDO $pdo, array $values): void
{
    if ($values === []) {
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    foreach ($values as $key => $value) {
        $stmt->execute([(string) $key, (string) $value]);
    }

    invalidateAdminSettingsCache();
    try {
        getAdminSettings($pdo);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'notifications.account_email.cache_warm']);
        } else {
            error_log('Account email settings cache warm failed: ' . $e->getMessage());
        }
    }
}

function admin_notification_account_email_sample_payload(array $settings, string $recipient = ''): array
{
    $recipient = trim($recipient) !== '' ? trim($recipient) : 'test@example.com';
    $publicBase = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';

    return [
        'site_name' => (string) ($settings['site_name'] ?? 'Türk Mod'),
        'username' => 'Test Kullanıcısı',
        'recipient_email' => $recipient,
        'action_url' => $publicBase !== '' ? $publicBase . '/test-action' : '#',
        'login_url' => function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : ($publicBase !== '' ? $publicBase . '/giris' : '#'),
        'profile_url' => $publicBase !== '' ? $publicBase . '/profil/test-kullanici' : '#',
        'expires_minutes' => '60',
        'old_email' => 'eski@example.com',
        'new_email' => $recipient,
        'actor_context' => 'Yönetim paneli test gönderimi',
        'ip_address' => function_exists('getRealIp') ? (string) getRealIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        'date_time' => date('d.m.Y H:i'),
        'support_email' => (string) ($settings['mail_from_address'] ?? ''),
    ];
}

function admin_notification_external_link_is_insecure(string $link): bool
{
    if ($link === '' || str_starts_with($link, '/')) {
        return false;
    }

    $scheme = parse_url($link, PHP_URL_SCHEME);
    return $scheme !== null && strtolower((string) $scheme) !== 'https';
}

function admin_notification_preview(string $message, int $limit): string
{
    if (mb_strlen($message) <= $limit) {
        return $message;
    }

    return rtrim(mb_substr($message, 0, max(0, $limit - 1))) . '…';
}

function admin_notification_insert(
    PDO $pdo,
    ?int $userId,
    string $title,
    string $message,
    string $type,
    ?string $link,
    bool $adminLoggable = false,
    ?array $deliveryChannels = null,
    array $logContext = []
): int {
    $columns = ['user_id', 'title', 'message', 'type', 'link'];
    $values = [$userId, $title, $message, $type, $link];

    try {
        if (function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'is_admin_loggable')) {
            $columns[] = 'is_admin_loggable';
            $values[] = ($adminLoggable || $type === 'system') ? 1 : 0;
        }
        if ($deliveryChannels !== null && function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'delivery_channels')) {
            $columns[] = 'delivery_channels';
            $values[] = json_encode(array_values($deliveryChannels), JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        error_log('Notification admin loggable column lookup failed: ' . $e->getMessage());
    }

    $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $pdo->prepare('INSERT INTO notifications (' . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})");
    $stmt->execute($values);

    $notificationId = (int) $pdo->lastInsertId();
    if ($notificationId > 0 && function_exists('notificationDeliveryLog')) {
        notificationDeliveryLog($pdo, 'notification_delivery_created', array_merge([
            'source' => 'admin_notification_insert',
            'status' => 'created',
            'recipient_user_id' => $userId,
            'recipient_type' => $userId !== null ? 'user' : 'broadcast',
            'notification_id' => $notificationId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'delivery_channels' => $deliveryChannels ?? ['in_app'],
        ], $logContext));
    }

    return $notificationId;
}

function admin_notification_update_delivery_channels(PDO $pdo, int $notificationId, array $deliveryChannels): void
{
    if ($notificationId <= 0) {
        return;
    }

    try {
        if (!function_exists('adminColumnExists') || !adminColumnExists($pdo, 'notifications', 'delivery_channels')) {
            return;
        }

        $channels = array_values(array_unique(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $deliveryChannels
        ))));

        $stmt = $pdo->prepare('UPDATE notifications SET delivery_channels = ? WHERE id = ?');
        $stmt->execute([json_encode($channels, JSON_UNESCAPED_UNICODE), $notificationId]);
    } catch (Throwable $e) {
        error_log('Notification delivery channel update failed: ' . $e->getMessage());
    }
}

function admin_notification_template_anchor(string $templateKey): string
{
    return 'notification-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $templateKey);
}

function admin_notification_template_key_from_name(string $name): string
{
    $value = strtr(trim($name), [
        'Ç' => 'C',
        'Ğ' => 'G',
        'İ' => 'I',
        'I' => 'I',
        'Ö' => 'O',
        'Ş' => 'S',
        'Ü' => 'U',
        'ç' => 'c',
        'ğ' => 'g',
        'ı' => 'i',
        'ö' => 'o',
        'ş' => 's',
        'ü' => 'u',
    ]);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    $value = trim($value, '_');
    if ($value === '') {
        $value = 'bildirim_metni';
    }
    if (!str_starts_with($value, 'manual_')) {
        $value = 'manual_' . $value;
    }

    return substr($value, 0, 100);
}

function admin_notification_unique_template_key(PDO $pdo, string $name): string
{
    $base = admin_notification_template_key_from_name($name);
    $defaults = notificationTemplateDefaults();
    $exists = static function (string $candidate) use ($pdo, $defaults): bool {
        if (isset($defaults[$candidate])) {
            return true;
        }

        try {
            return notificationTemplateGet($pdo, $candidate) !== null;
        } catch (Throwable $e) {
            return false;
        }
    };

    if (!$exists($base)) {
        return $base;
    }

    for ($i = 2; $i <= 999; $i++) {
        $suffix = '_' . $i;
        $candidate = substr($base, 0, 100 - strlen($suffix)) . $suffix;
        if (!$exists($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Otomatik kayıt anahtarı üretilemedi. Metin adını biraz değiştirip tekrar deneyin.');
}

function admin_notification_template_input(array $source): array
{
    return [
        'name' => $source['name'] ?? '',
        'description' => $source['description'] ?? '',
        'type' => $source['type'] ?? 'info',
        'title_template' => $source['title_template'] ?? '',
        'message_template' => $source['message_template'] ?? '',
        'link_template' => $source['link_template'] ?? '',
        'in_app_enabled' => $source['in_app_enabled'] ?? null,
        'email_enabled' => $source['email_enabled'] ?? null,
        'email_subject_template' => $source['email_subject_template'] ?? '',
        'email_body_template' => $source['email_body_template'] ?? '',
        'email_link_template' => $source['email_link_template'] ?? '',
        'email_preview_template' => $source['email_preview_template'] ?? '',
        'is_active' => $source['is_active'] ?? null,
        'allow_create' => $source['allow_create'] ?? null,
        'channel' => $source['channel'] ?? null,
    ];
}


function admin_notifications_delete_related(PDO $pdo, ?int $notificationId = null): void
{
    $tables = ['notification_email_queue', 'notification_reads', 'notification_dismissals'];
    foreach ($tables as $table) {
        if (function_exists('adminTableExists') && !adminTableExists($pdo, $table)) {
            continue;
        }

        if ($notificationId !== null) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE notification_id = ?");
            $stmt->execute([$notificationId]);
            continue;
        }

        $pdo->exec("DELETE FROM {$table}");
    }
}

function admin_notifications_delete_one(PDO $pdo, int $notificationId): int
{
    if ($notificationId <= 0) {
        return 0;
    }

    admin_notifications_delete_related($pdo, $notificationId);
    $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ?');
    $stmt->execute([$notificationId]);

    return $stmt->rowCount();
}

function admin_notifications_clear_all(PDO $pdo): int
{
    admin_notifications_delete_related($pdo);
    $deleted = $pdo->exec('DELETE FROM notifications');

    return is_int($deleted) ? $deleted : 0;
}

$adminSettings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$settingsSchema = admin_notification_settings_schema();
$flatSettingsSchema = admin_notification_flat_settings_schema();
$emailSettingsSchema = admin_notification_email_settings_schema();
$accountEmailCatalog = \App\Engine\Email\AccountEmailService::catalog();
$accountEmailAllowedVariables = \App\Engine\Email\AccountEmailService::allowedVariables();
$accountEmailRequiredVariables = \App\Engine\Email\AccountEmailService::requiredVariables();
$adminEmailCatalog = \App\Engine\Email\AdminEmailService::catalog();
$adminEmailAllowedVariables = \App\Engine\Email\AdminEmailService::allowedVariables();
$adminEmailRequiredVariables = \App\Engine\Email\AdminEmailService::requiredVariables();
$accountEmailSystemEnabled = admin_notification_bool($adminSettings, 'account_email_system_enabled', '1');
$accountEmailStats = ['total' => count($accountEmailCatalog), 'enabled' => 0, 'disabled' => 0];
foreach ($accountEmailCatalog as $accountTemplateKey => $accountTemplate) {
    $accountEnabledKey = \App\Engine\Email\AccountEmailService::settingKey((string) $accountTemplateKey, 'enabled');
    if (admin_notification_bool($adminSettings, $accountEnabledKey, (string) ($accountTemplate['enabled'] ?? '1'))) {
        $accountEmailStats['enabled']++;
    } else {
        $accountEmailStats['disabled']++;
    }
}
$adminEmailStats = ['total' => count($adminEmailCatalog), 'enabled' => 0, 'disabled' => 0];
foreach ($adminEmailCatalog as $adminTemplateKey => $adminTemplate) {
    $adminEnabledKey = \App\Engine\Email\AdminEmailService::settingKey((string) $adminTemplateKey, 'enabled');
    if (admin_notification_bool($adminSettings, $adminEnabledKey, (string) ($adminTemplate['enabled'] ?? '1'))) {
        $adminEmailStats['enabled']++;
    } else {
        $adminEmailStats['disabled']++;
    }
}
$accountEmailPreviewPayload = admin_notification_account_email_sample_payload($adminSettings, (string) ($_SESSION['_auth_user_email'] ?? ''));
$adminEmailPreviewPayload = \App\Engine\Email\AdminEmailService::samplePayload($adminSettings);
$adminRegistrationSiteTemplate = function_exists('usersAdminRegistrationSiteTemplate')
    ? usersAdminRegistrationSiteTemplate($adminSettings)
    : [
        'template_key' => 'registration_admin_notice',
        'name' => 'Yeni Kullanıcı Kaydı Admin Bildirimi',
        'description' => 'Yeni üyelik oluştuğunda admin ve yetkili hesapların bildirim merkezine düşer.',
        'type' => 'system',
        'title_template' => 'Yeni kullanıcı kaydı',
        'message_template' => '{{username}} ({{email}}) yeni hesap oluşturdu. Durum: {{user_status}}',
        'link_template' => '{{admin_link}}',
    ];
$adminRegistrationSiteVariables = function_exists('usersAdminRegistrationSiteAllowedVariables')
    ? usersAdminRegistrationSiteAllowedVariables()
    : [
        'site_name' => 'Site adı',
        'username' => 'Yeni kullanıcının adı',
        'email' => 'Yeni kullanıcının e-posta adresi',
        'user_id' => 'Yeni kullanıcı ID değeri',
        'user_status' => 'Kullanıcının kayıt sonrası durumu',
        'approval_status' => 'Yönetici onayı bilgisi',
        'admin_link' => 'Admin panelindeki kullanıcı düzenleme bağlantısı',
        'link' => 'Admin bağlantısı kısayolu',
    ];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        if (in_array($action, ['delete', 'clear_all'], true)) {
            adminLogCleanupRespond(false, 'Güvenlik doğrulaması başarısız.', 'notifications.php?tab=history', 403);
        }

        flash('error', 'Güvenlik hatası.');
        header('Location: notifications.php?tab=' . $tab);
        exit;
    }

    try {
        if (in_array($action, ['save_site_settings', 'send_admin_registration_site_test', 'reset_admin_registration_site_template'], true)) {
            if ($action === 'reset_admin_registration_site_template') {
                $adminRegistrationDefaults = function_exists('usersAdminRegistrationSiteTemplateDefaults')
                    ? usersAdminRegistrationSiteTemplateDefaults()
                    : [
                        'name' => 'Yeni Kullanıcı Kaydı Admin Bildirimi',
                        'description' => 'Yeni üyelik oluştuğunda admin ve yetkili hesapların bildirim merkezine düşer.',
                        'type' => 'system',
                        'title_template' => 'Yeni kullanıcı kaydı',
                        'message_template' => '{{username}} ({{email}}) yeni hesap oluşturdu. Durum: {{user_status}}',
                        'link_template' => '{{admin_link}}',
                    ];
                $siteSettings = admin_notification_admin_registration_site_setting_values($adminRegistrationDefaults, '1');
                admin_notification_save_setting_values($pdo, $siteSettings);
                $adminSettings = array_replace($adminSettings, $siteSettings);
                if (function_exists('usersAdminRegistrationSiteTemplate')) {
                    $adminRegistrationSiteTemplate = usersAdminRegistrationSiteTemplate($adminSettings);
                }

                flash('success', 'Yeni kullanıcı kayıt admin bildirimi varsayılana döndürüldü.');
                header('Location: notifications.php?tab=site#admin-registration-site');
                exit;
            }

            $adminRegistrationEnabled = isset($_POST['notif_admin_registration_site_enabled']) ? '1' : '0';
            $adminRegistrationSiteInput = admin_notification_admin_registration_site_input($_POST, $adminRegistrationSiteTemplate);
            admin_notification_validate_admin_registration_site_template($adminRegistrationSiteInput);

            if ($action === 'send_admin_registration_site_test') {
                if ($currentUserId <= 0) {
                    throw new RuntimeException('Test bildirimi için aktif admin hesabı bulunamadı.');
                }

                $preview = admin_notification_render_admin_registration_site_preview($adminRegistrationSiteInput, $adminSettings);
                admin_notification_insert(
                    $pdo,
                    $currentUserId,
                    mb_substr('Test: ' . $preview['title'], 0, 255),
                    $preview['message'],
                    $preview['type'],
                    $preview['link'] !== '' ? $preview['link'] : null,
                    true,
                    ['in_app'],
                    [
                        'source' => 'admin_registration_site_test',
                        'event_key' => 'registration_admin_notice',
                        'template_key' => 'registration_admin_notice',
                        'recipient_type' => 'admin',
                        'actor_user_id' => $currentUserId,
                    ]
                );

                flash('success', 'Admin kayıt bildirimi testi kendi hesabınıza gönderildi.');
                header('Location: notifications.php?tab=site#admin-registration-site');
                exit;
            }

            $siteSettings = admin_notification_admin_registration_site_setting_values($adminRegistrationSiteInput, $adminRegistrationEnabled);
            admin_notification_save_setting_values($pdo, $siteSettings);
            $adminSettings = array_replace($adminSettings, $siteSettings);
            if (function_exists('usersAdminRegistrationSiteTemplate')) {
                $adminRegistrationSiteTemplate = usersAdminRegistrationSiteTemplate($adminSettings);
            }

            flash('success', 'Yeni kullanıcı kayıt admin bildirimi kaydedildi.');
            header('Location: notifications.php?tab=site#admin-registration-site');
            exit;
        }

        if ($action === 'create') {
            if (!admin_notification_bool($adminSettings, 'notif_center_enabled', '1')) {
                throw new RuntimeException('Bildirim merkezi kapalı olduğu için yeni bildirim gönderilemez.');
            }

            $title = trim((string) ($_POST['title'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $type = trim((string) ($_POST['type'] ?? ($adminSettings['notif_default_type'] ?? 'info')));
            $link = trim((string) ($_POST['link'] ?? ''));
            $targetUserId = trim((string) ($_POST['target_user_id'] ?? ''));
            $defaultLink = trim((string) ($adminSettings['notif_default_link'] ?? ''));
            $maxTitleLength = admin_notification_int($adminSettings, 'notif_max_title_length', 120, 20, 255);
            $maxMessageLength = admin_notification_int($adminSettings, 'notif_max_message_length', 800, 80, 5000);

            if ($title === '' || $message === '') {
                throw new RuntimeException('Başlık ve mesaj alanları zorunludur.');
            }
            if (mb_strlen($title) > $maxTitleLength) {
                throw new RuntimeException("Başlık en fazla {$maxTitleLength} karakter olabilir.");
            }
            if (mb_strlen($message) > $maxMessageLength) {
                throw new RuntimeException("Mesaj en fazla {$maxMessageLength} karakter olabilir.");
            }

            $validTypes = array_keys(admin_notification_types());
            if (!in_array($type, $validTypes, true)) {
                $type = 'info';
            }

            $userId = null;
            if ($targetUserId !== '') {
                if (!admin_notification_bool($adminSettings, 'notif_allow_direct_messages', '1')) {
                    throw new RuntimeException('Kullanıcıya özel bildirim gönderimi kapalı.');
                }
                $userId = (int) $targetUserId;
                if ($userId <= 0) {
                    throw new RuntimeException('Geçersiz Kullanıcı ID.');
                }
            } elseif (!admin_notification_bool($adminSettings, 'notif_allow_global_broadcasts', '1')) {
                throw new RuntimeException('Genel yayın gönderimi kapalı. Bir hedef kullanıcı ID girin.');
            }

            if ($link === '' && $defaultLink !== '') {
                $link = $defaultLink;
            }
            if (admin_notification_bool($adminSettings, 'notif_require_https_links', '0') && admin_notification_external_link_is_insecure($link)) {
                throw new RuntimeException('Harici bildirim linkleri HTTPS olmalıdır.');
            }

            admin_notification_insert($pdo, $userId, $title, $message, $type, $link !== '' ? $link : null, $type === 'system', null, [
                'source' => 'admin_manual_notification',
                'event_key' => 'manual_notification',
                'recipient_type' => $userId !== null ? 'user' : 'broadcast',
                'actor_user_id' => $currentUserId,
            ]);

            flash('success', 'Bildirim başarıyla gönderildi.');
            header('Location: notifications.php?tab=history');
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            adminRunLogCleanup($pdo, [
                'action_type' => 'notification_records_deleted',
                'scope' => 'single',
                'allowed_scopes' => ['single'],
                'permission' => 'notifications.manage',
                'permission_message' => 'Bildirimleri yönetmek için gerekli izin hesabınıza tanımlanmamış.',
                'redirect_url' => 'notifications.php?tab=history',
                'source' => 'notification_history',
                'activity_subject' => 'notifications',
                'delete' => static fn (PDO $pdo): int => admin_notifications_delete_one($pdo, $id),
                'context' => [
                    'action' => 'delete',
                    'notification_id' => $id,
                ],
                'require_deleted' => true,
                'failure_message' => 'Silinecek bildirim bulunamadı.',
                'success_message' => 'Bildirim silindi.',
            ]);
        }

        if ($action === 'clear_all') {
            adminRunLogCleanup($pdo, [
                'action_type' => 'notification_records_deleted',
                'scope' => 'all',
                'allowed_scopes' => ['all'],
                'permission' => 'notifications.manage',
                'permission_message' => 'Bildirimleri yönetmek için gerekli izin hesabınıza tanımlanmamış.',
                'redirect_url' => 'notifications.php?tab=history',
                'source' => 'notification_history',
                'activity_subject' => 'notifications',
                'delete' => static fn (PDO $pdo): int => admin_notifications_clear_all($pdo),
                'context' => [
                    'action' => 'clear_all',
                ],
                'success_message' => static fn (int $deleted): string => $deleted > 0 ? 'Tüm bildirimler silindi.' : 'Silinecek bildirim bulunmuyor.',
            ]);
        }

        if (in_array($action, ['save_template', 'reset_template', 'delete_template', 'send_site_test', 'send_email_test'], true)) {
            $templateKey = strtolower(trim((string) ($_POST['template_key'] ?? '')));
            if ($templateKey === '' && $action === 'save_template' && !empty($_POST['allow_create'])) {
                $templateKey = admin_notification_unique_template_key($pdo, (string) ($_POST['name'] ?? ''));
                $_POST['template_key'] = $templateKey;
            }
            $channel = (string) ($_POST['channel'] ?? ($tab === 'email' ? 'email' : 'site'));
            if (!in_array($channel, ['site', 'email'], true)) {
                $channel = 'site';
            }
            $channelRedirect = $channel === 'email'
                ? 'notifications.php?tab=email&email_group=events'
                : 'notifications.php?tab=site';
            if ($templateKey === '') {
                throw new RuntimeException('Bildirim metni anahtarı eksik.');
            }
            if (
                $channel === 'email'
                && in_array($action, ['save_template', 'reset_template', 'send_email_test'], true)
                && function_exists('notificationTemplateEmailSchemaReady')
                && !notificationTemplateEmailSchemaReady($pdo)
            ) {
                throw new RuntimeException('E-posta metin alanları henüz hazır değil. Admin Panel > Veritabanı Senkronizasyonu çalıştırılmalı.');
            }

            if ($action === 'reset_template') {
                if (!notificationTemplateReset($pdo, $templateKey)) {
                    throw new RuntimeException('Bildirim metni varsayılana döndürülemedi.');
                }
                flash('success', 'Bildirim metni varsayılana döndürüldü.');
                header('Location: ' . $channelRedirect . '#' . admin_notification_template_anchor($templateKey));
                exit;
            }

            if ($action === 'delete_template') {
                if (!notificationTemplateDelete($pdo, $templateKey)) {
                    throw new RuntimeException('Varsayılan bildirim metinleri silinemez.');
                }
                flash('success', 'Bildirim metni silindi.');
                header('Location: ' . $channelRedirect);
                exit;
            }

            $templateInput = admin_notification_template_input($_POST);
            if ($channel === 'email' && in_array($action, ['save_template', 'send_email_test'], true)) {
                admin_notification_validate_notification_email_variables(
                    $templateKey,
                    $templateInput,
                    $action === 'send_email_test' || !empty($templateInput['email_enabled'])
                );
            }

            if ($action === 'send_site_test') {
                if ($currentUserId <= 0) {
                    throw new RuntimeException('Test bildirimi için aktif admin hesabı bulunamadı.');
                }

                $errors = notificationTemplateValidate($templateInput);
                if (!empty($errors)) {
                    throw new RuntimeException(implode(' ', $errors));
                }

                $samplePayload = notificationTemplateSamplePayload();
                $samplePayload['recipient_name'] = (string) ($_SESSION['_auth_user_name'] ?? 'Admin');
                $preview = notificationTemplatePreview($templateInput, $samplePayload);
                $validTypes = array_keys(admin_notification_types());
                $testType = in_array($preview['type'], $validTypes, true) ? $preview['type'] : 'info';
                $testTitle = mb_substr('Test: ' . $preview['title'], 0, 255);

                admin_notification_insert(
                    $pdo,
                    $currentUserId,
                    $testTitle,
                    $preview['message'],
                    $testType,
                    $preview['link'] !== '' ? $preview['link'] : null,
                    $testType === 'system',
                    ['in_app'],
                    [
                        'source' => 'admin_site_notification_test',
                        'event_key' => $templateKey,
                        'template_key' => $templateKey,
                        'recipient_type' => 'admin',
                        'actor_user_id' => $currentUserId,
                    ]
                );

                flash('success', 'Test bildirimi kendi hesabınıza gönderildi.');
                header('Location: notifications.php?tab=site#' . admin_notification_template_anchor($templateKey));
                exit;
            }

            if ($action === 'send_email_test') {
                if ($currentUserId <= 0) {
                    throw new RuntimeException('Test e-postası için aktif admin hesabı bulunamadı.');
                }
                if (!admin_notification_bool($adminSettings, 'notif_email_channel_ready', '0')) {
                    throw new RuntimeException('E-posta kuyruğu aktif değil.');
                }
                if (!notificationEmailRecipient($pdo, $currentUserId)) {
                    throw new RuntimeException('Aktif admin hesabında geçerli e-posta adresi bulunamadı.');
                }

                $errors = array_merge(notificationTemplateValidate($templateInput), notificationTemplateEmailCopyErrors($templateInput));
                if (!empty($errors)) {
                    throw new RuntimeException(implode(' ', $errors));
                }

                $samplePayload = notificationTemplateSamplePayload();
                $samplePayload['recipient_name'] = (string) ($_SESSION['_auth_user_name'] ?? 'Admin');
                $preview = notificationTemplateEmailPreview($templateInput, $samplePayload);
                $subject = mb_substr('Test: ' . $preview['subject'], 0, 255);
                $startedTx = false;
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                    $startedTx = true;
                }

                try {
                    $notificationId = admin_notification_insert(
                        $pdo,
                        $currentUserId,
                        $subject,
                        $preview['body'],
                        'system',
                        $preview['link'] !== '' ? $preview['link'] : null,
                        true,
                        ['email_queue_pending'],
                        [
                            'source' => 'admin_email_notification_test',
                            'event_key' => $templateKey,
                            'template_key' => $templateKey,
                            'recipient_type' => 'admin',
                            'actor_user_id' => $currentUserId,
                        ]
                    );
                    $queued = notificationQueueEmail(
                        $pdo,
                        $notificationId,
                        $currentUserId,
                        $templateKey,
                        $subject,
                        $preview['body'],
                        $preview['link'] !== '' ? $preview['link'] : null,
                        [
                            'source' => 'admin_email_test',
                            'template_key' => $templateKey,
                            'event_key' => $templateKey,
                            'recipient_type' => 'admin',
                            'actor_user_id' => $currentUserId,
                            'email_preview' => $preview['preview'],
                        ],
                        admin_notification_int($adminSettings, 'notif_email_queue_max_attempts', 3, 1, 10)
                    );
                    admin_notification_update_delivery_channels($pdo, $notificationId, [$queued ? 'email_queue' : 'email_queue_failed']);
                    if (!$queued) {
                        throw new RuntimeException('Test e-postası kuyruğa eklenemedi.');
                    }
                    if ($startedTx && $pdo->inTransaction()) {
                        $pdo->commit();
                    }
                } catch (Throwable $e) {
                    if ($startedTx && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (function_exists('notificationDeliveryLog')) {
                        notificationDeliveryLog($pdo, 'notification_delivery_failed', [
                            'source' => 'admin_email_notification_test',
                            'status' => 'failed',
                            'reason' => 'email_queue_transaction_failed',
                            'error' => $e->getMessage(),
                            'event_key' => $templateKey,
                            'template_key' => $templateKey,
                            'recipient_user_id' => $currentUserId,
                            'recipient_type' => 'admin',
                            'actor_user_id' => $currentUserId,
                            'title' => $subject ?? '',
                            'message' => $preview['body'] ?? '',
                            'link' => $preview['link'] ?? '',
                            'delivery_channels' => ['email_queue_failed'],
                        ], 'error');
                    }
                    throw $e;
                }

                flash('success', 'Test e-postası kuyruğa eklendi.');
                header('Location: notifications.php?tab=email&email_group=events#' . admin_notification_template_anchor($templateKey));
                exit;
            }

            $saved = notificationTemplateSave($pdo, $templateKey, $templateInput);
            if (!$saved) {
                throw new RuntimeException('Bildirim metni kaydedilemedi.');
            }

            flash('success', 'Bildirim metni kaydedildi.');
            header('Location: ' . $channelRedirect . '#' . admin_notification_template_anchor($templateKey));
            exit;
        }

        if ($action === 'save_settings') {
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

            foreach ($flatSettingsSchema as $settingItem) {
                $value = admin_notification_save_value($settingItem, $_POST);
                $stmt->execute([$settingItem['key'], $value]);
                $adminSettings[$settingItem['key']] = $value;
            }

            invalidateAdminSettingsCache();

            $retentionDays = admin_notification_int($adminSettings, 'notif_retention_days', 30, 0, 3650);
            if ($retentionDays > 0) {
                $pdo->exec("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");
            }

            flash('success', 'Bildirim ayarları kaydedildi.');
            header('Location: notifications.php?tab=settings');
            exit;
        }

        if ($action === 'save_email_settings') {
            if (
                isset($_POST['notif_email_channel_ready'])
                && function_exists('notificationTemplateEmailSchemaReady')
                && !notificationTemplateEmailSchemaReady($pdo)
            ) {
                throw new RuntimeException('E-posta kanalı açılmadan önce Admin Panel > Veritabanı Senkronizasyonu çalıştırılmalı.');
            }

            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

            foreach (admin_notification_email_settings_schema() as $settingItem) {
                $value = admin_notification_save_value($settingItem, $_POST);
                $stmt->execute([$settingItem['key'], $value]);
                $adminSettings[$settingItem['key']] = $value;
            }

            invalidateAdminSettingsCache();

            flash('success', 'E-posta bildirim ayarları kaydedildi.');
            header('Location: notifications.php?tab=email&email_group=settings');
            exit;
        }

        if ($action === 'save_account_email_settings') {
            $value = isset($_POST['account_email_system_enabled']) ? '1' : '0';
            admin_notification_save_setting_values($pdo, ['account_email_system_enabled' => $value]);
            $adminSettings['account_email_system_enabled'] = $value;

            flash('success', 'Hesap e-posta genel ayarı kaydedildi.');
            header('Location: notifications.php?tab=email&email_group=account#account-email-settings');
            exit;
        }

        if (in_array($action, ['save_account_email_template', 'send_account_email_test'], true)) {
            $accountTemplateKey = trim((string) ($_POST['account_email_template_key'] ?? ''));
            if (!isset($accountEmailCatalog[$accountTemplateKey])) {
                throw new RuntimeException('Geçersiz hesap e-posta şablonu.');
            }

            $enabledKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'enabled');
            $subjectKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'subject');
            $bodyKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'body');
            $enabledValue = isset($_POST[$enabledKey]) ? '1' : '0';
            $subjectValue = trim((string) ($_POST[$subjectKey] ?? $accountEmailCatalog[$accountTemplateKey]['subject']));
            $bodyValue = (string) ($_POST[$bodyKey] ?? $accountEmailCatalog[$accountTemplateKey]['body']);

            admin_notification_validate_account_email_copy($accountTemplateKey, $subjectValue, $bodyValue, true);

            if ($action === 'save_account_email_template') {
                admin_notification_save_setting_values($pdo, [
                    $enabledKey => $enabledValue,
                    $subjectKey => $subjectValue,
                    $bodyKey => $bodyValue,
                ]);

                flash('success', 'Hesap e-posta şablonu kaydedildi.');
                header('Location: notifications.php?tab=email&email_group=account#' . admin_notification_account_email_anchor($accountTemplateKey));
                exit;
            }

            $recipient = trim((string) ($_POST['account_email_test_recipient'] ?? ''));
            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Geçerli bir test e-posta adresi girin.');
            }

            $testSettings = $adminSettings;
            $testSettings[$enabledKey] = $enabledValue;
            $testSettings[$subjectKey] = $subjectValue;
            $testSettings[$bodyKey] = $bodyValue;
            $ok = accountEmailService($pdo)->send($accountTemplateKey, $recipient, admin_notification_account_email_sample_payload($adminSettings, $recipient), [
                'force' => true,
                'enabled' => '1',
                'subject' => $subjectValue,
                'body' => $bodyValue,
                'settings' => $testSettings,
            ]);
            if (!$ok) {
                $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
                $detail = admin_notification_mail_diagnostic($mailResult, [(string) ($testSettings['smtp_password'] ?? '')]);
                throw new RuntimeException('Hesap e-posta testi gönderilemedi.' . ($detail !== '' ? ' ' . $detail : ''));
            }

            flash('success', 'Hesap e-posta testi gönderildi: ' . $recipient);
            header('Location: notifications.php?tab=email&email_group=account#' . admin_notification_account_email_anchor($accountTemplateKey));
            exit;
        }

        if (in_array($action, ['save_admin_email_template', 'send_admin_email_test'], true)) {
            $adminTemplateKey = trim((string) ($_POST['admin_email_template_key'] ?? ''));
            if (!isset($adminEmailCatalog[$adminTemplateKey])) {
                throw new RuntimeException('Geçersiz yönetici e-posta şablonu.');
            }

            $enabledKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'enabled');
            $subjectKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'subject');
            $bodyKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'body');
            $actionLabelKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'action_label');
            $enabledValue = isset($_POST[$enabledKey]) ? '1' : '0';
            $subjectValue = trim((string) ($_POST[$subjectKey] ?? $adminEmailCatalog[$adminTemplateKey]['subject']));
            $bodyValue = \App\Engine\Email\AdminEmailService::bodyForEditor(
                $adminTemplateKey,
                (string) ($_POST[$bodyKey] ?? $adminEmailCatalog[$adminTemplateKey]['body'])
            );
            $actionLabelValue = trim((string) ($_POST[$actionLabelKey] ?? $adminEmailCatalog[$adminTemplateKey]['action_label']));

            admin_notification_validate_admin_email_copy($adminTemplateKey, $subjectValue, $bodyValue, $actionLabelValue, true);

            if ($action === 'save_admin_email_template') {
                admin_notification_save_setting_values($pdo, [
                    $enabledKey => $enabledValue,
                    $subjectKey => $subjectValue,
                    $bodyKey => $bodyValue,
                    $actionLabelKey => $actionLabelValue,
                ]);

                flash('success', 'Yönetici e-posta şablonu kaydedildi.');
                header('Location: notifications.php?tab=email&email_group=admin#admin-email-' . rawurlencode($adminTemplateKey));
                exit;
            }

            $recipient = trim((string) ($_POST['admin_email_test_recipient'] ?? ''));
            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Geçerli bir test e-posta adresi girin.');
            }

            $testSettings = $adminSettings;
            $testSettings[$enabledKey] = $enabledValue;
            $testSettings[$subjectKey] = $subjectValue;
            $testSettings[$bodyKey] = $bodyValue;
            $testSettings[$actionLabelKey] = $actionLabelValue;
            $ok = adminEmailService($pdo)->sendTest($adminTemplateKey, $recipient, $adminEmailPreviewPayload, [
                'force' => true,
                'enabled' => '1',
                'subject' => $subjectValue,
                'body' => $bodyValue,
                'action_label' => $actionLabelValue,
                'settings' => $testSettings,
            ]);
            if (!$ok) {
                $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
                $detail = admin_notification_mail_diagnostic($mailResult, [(string) ($testSettings['smtp_password'] ?? '')]);
                throw new RuntimeException('Yönetici e-posta testi gönderilemedi.' . ($detail !== '' ? ' ' . $detail : ''));
            }

            flash('success', 'Yönetici e-posta testi gönderildi: ' . $recipient);
            header('Location: notifications.php?tab=email&email_group=admin#admin-email-' . rawurlencode($adminTemplateKey));
            exit;
        }
    } catch (Throwable $e) {
        flash('error', 'İşlem başarısız: ' . safeErrorMessage($e));
        $failureFragment = '';
        if (in_array($action, ['save_site_settings', 'send_admin_registration_site_test', 'reset_admin_registration_site_template'], true)) {
            $failureFragment = '#admin-registration-site';
        }
        if (isset($_POST['account_email_template_key'])) {
            $failureFragment = '#' . admin_notification_account_email_anchor((string) $_POST['account_email_template_key']);
            $emailGroup = 'account';
        }
        if (isset($_POST['admin_email_template_key'])) {
            $failureFragment = '#admin-email-' . rawurlencode((string) $_POST['admin_email_template_key']);
            $emailGroup = 'admin';
        }
        if (($tab === 'email' || (string) ($_POST['channel'] ?? '') === 'email') && isset($_POST['template_key'])) {
            $failureFragment = '#' . admin_notification_template_anchor((string) $_POST['template_key']);
            $emailGroup = 'events';
        }
        $failureTarget = $tab === 'email'
            ? 'notifications.php?tab=email&email_group=' . rawurlencode($emailGroup)
            : 'notifications.php?tab=' . $tab;
        header('Location: ' . $failureTarget . $failureFragment);
        exit;
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = admin_notification_int($adminSettings, 'notif_history_per_page', 10, 5, 100);
$offset = ($page - 1) * $perPage;
$previewLength = admin_notification_int($adminSettings, 'notif_history_message_preview', 140, 40, 500);
$maxTitleLength = admin_notification_int($adminSettings, 'notif_max_title_length', 120, 20, 255);
$maxMessageLength = admin_notification_int($adminSettings, 'notif_max_message_length', 800, 80, 5000);
$defaultType = (string) ($adminSettings['notif_default_type'] ?? 'info');
$defaultLink = (string) ($adminSettings['notif_default_link'] ?? '');

$notifications = [];
$totalNotifications = 0;
$totalPages = 1;
$notificationTemplates = [];
$siteTemplateStats = ['active' => 0, 'enabled' => 0, 'disabled' => 0];
$emailTemplateStats = ['active' => 0, 'enabled' => 0, 'missing' => 0];
$composerTemplates = [];
$composerTemplatePayload = [];
$templatePreviewPayloads = ['__new' => notificationTemplateSamplePayload()];
$templatePreviewPayloads['admin_registration_site'] = admin_notification_admin_registration_site_sample_payload($adminSettings);
$emailQueueStats = ['total' => 0, 'queued' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0];
$currentAdminEmailRecipient = null;
$templateLoadError = null;
$templateSchemaNotice = null;
$emailTemplateSchemaReady = true;
$emailTemplateMissingColumns = [];

if ($pdo) {
    try {
        $statsRow = $pdo->query("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END), 0) AS global_count,
                COALESCE(SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS direct_count
            FROM notifications
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $notificationStats['total'] = (int) ($statsRow['total_count'] ?? 0);
        $notificationStats['global'] = (int) ($statsRow['global_count'] ?? 0);
        $notificationStats['direct'] = (int) ($statsRow['direct_count'] ?? 0);

        $unreadRow = $pdo->query("
            SELECT COUNT(*) FROM notifications n
            WHERE NOT EXISTS (
                SELECT 1 FROM notification_reads nr WHERE nr.notification_id = n.id
            )
        ");
        $notificationStats['unread'] = (int) $unreadRow->fetchColumn();
        try {
            $emailQueueStats = notificationEmailQueueStats($pdo);
        } catch (Throwable $e) {
            error_log('Notification email queue stats skipped: ' . $e->getMessage());
        }
        if ($currentUserId > 0) {
            $currentAdminEmailRecipient = notificationEmailRecipient($pdo, $currentUserId);
        }
        if (function_exists('notificationTemplateMissingEmailColumns')) {
            $emailTemplateMissingColumns = notificationTemplateMissingEmailColumns($pdo);
            $emailTemplateSchemaReady = $emailTemplateMissingColumns === [];
            if (!$emailTemplateSchemaReady) {
                $templateSchemaNotice = 'E-posta metin kolonları bekliyor: ' . implode(', ', $emailTemplateMissingColumns) . '. Veritabanı Senkronizasyonu çalıştırıldığında e-posta metinleri kaydedilebilir hale gelir.';
            }
        }

    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    try {
        $notificationTemplates = notificationTemplateList($pdo);
        $composerTemplates = notificationTemplateList($pdo, true);
        foreach ($composerTemplates as $template) {
            $preview = notificationTemplatePreview($template);
            $composerTemplatePayload[(string) $template['template_key']] = [
                'type' => $preview['type'],
                'title' => $preview['title'],
                'message' => $preview['message'],
                'link' => $preview['link'],
            ];
        }
        foreach ($notificationTemplates as $template) {
            $templateKey = (string) $template['template_key'];
            $templatePreviewPayloads[$templateKey] = $template['sample_payload_array'] ?? notificationTemplateSamplePayload();
            if ((int) ($template['is_active'] ?? 1) === 1) {
                $siteTemplateStats['active']++;
                $emailTemplateStats['active']++;
            }
            if ((int) ($template['in_app_enabled'] ?? 1) === 1) {
                $siteTemplateStats['enabled']++;
            } else {
                $siteTemplateStats['disabled']++;
            }
            if ((int) ($template['email_enabled'] ?? 0) === 1) {
                $emailTemplateStats['enabled']++;
            }
            if (notificationTemplateEmailCopyErrors($template) !== []) {
                $emailTemplateStats['missing']++;
            }
        }
    } catch (Throwable $e) {
        $templateLoadError = safeErrorMessage($e, 'Bildirim metni yüklenemedi.');
        $notificationTemplates = array_values(notificationTemplateDefaults());
        $composerTemplates = array_values(array_filter(
            $notificationTemplates,
            static fn (array $template): bool => (int) ($template['is_active'] ?? 1) === 1 && (int) ($template['in_app_enabled'] ?? 1) === 1
        ));
        foreach ($composerTemplates as $template) {
            $preview = notificationTemplatePreview($template);
            $composerTemplatePayload[(string) $template['template_key']] = [
                'type' => $preview['type'],
                'title' => $preview['title'],
                'message' => $preview['message'],
                'link' => $preview['link'],
            ];
        }
        foreach ($notificationTemplates as $template) {
            $templateKey = (string) $template['template_key'];
            $templatePreviewPayloads[$templateKey] = $template['sample_payload_array'] ?? $template['sample_payload'] ?? notificationTemplateSamplePayload();
            if ((int) ($template['is_active'] ?? 1) === 1) {
                $siteTemplateStats['active']++;
                $emailTemplateStats['active']++;
            }
            if ((int) ($template['in_app_enabled'] ?? 1) === 1) {
                $siteTemplateStats['enabled']++;
            } else {
                $siteTemplateStats['disabled']++;
            }
            if ((int) ($template['email_enabled'] ?? 0) === 1) {
                $emailTemplateStats['enabled']++;
            }
            if (notificationTemplateEmailCopyErrors($template) !== []) {
                $emailTemplateStats['missing']++;
            }
        }
    }
}

if ($pdo && $tab === 'history') {
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM notifications");
        $totalNotifications = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalNotifications / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $targetIsAdminSql = '0 AS target_is_admin';
        try {
            if (
                function_exists('adminTableExists')
                && function_exists('adminColumnExists')
                && adminTableExists($pdo, 'user_group_members')
                && adminTableExists($pdo, 'user_groups')
                && adminTableExists($pdo, 'user_group_permissions')
                && adminColumnExists($pdo, 'user_group_members', 'user_id')
                && adminColumnExists($pdo, 'user_group_members', 'group_id')
                && adminColumnExists($pdo, 'user_groups', 'id')
                && adminColumnExists($pdo, 'user_groups', 'slug')
                && adminColumnExists($pdo, 'user_groups', 'is_active')
                && adminColumnExists($pdo, 'user_group_permissions', 'group_id')
                && adminColumnExists($pdo, 'user_group_permissions', 'permission_key')
                && adminColumnExists($pdo, 'user_group_permissions', 'permission_value')
            ) {
                $targetIsAdminSql = "CASE WHEN n.user_id IS NOT NULL AND EXISTS (
                    SELECT 1
                    FROM user_group_members rgm
                    INNER JOIN user_groups rg ON rg.id = rgm.group_id
                    LEFT JOIN user_group_permissions rgp
                        ON rgp.group_id = rg.id
                       AND rgp.permission_value = 1
                       AND rgp.permission_key IN ('*', 'admin.access')
                    WHERE rgm.user_id = n.user_id
                      AND rg.is_active = 1
                      AND (rg.slug = 'admin' OR rgp.permission_key IS NOT NULL)
                    LIMIT 1
                ) THEN 1 ELSE 0 END AS target_is_admin";
            }
        } catch (Throwable $e) {
            $targetIsAdminSql = '0 AS target_is_admin';
        }

        $stmt = $pdo->prepare("SELECT n.*, u.username AS target_username, {$targetIsAdminSql} FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        flash('error', 'Bildirimler yüklenemedi: ' . safeErrorMessage($e));
    }
}


$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
$csrfToken = csrf_token();
?>

<?php if ($successMsg): ?>
<?= adminRenderAlert('', 'success', [
    'icon' => '',
    'class' => 'notification-flash notification-flash-success',
    'role' => 'status',
    'closable' => true,
    'close_class' => 'notification-flash-close',
    'close_label' => 'Uyarıyı kapat',
    'html' => '<span class="notification-flash-icon"><i class="bi bi-check2-circle"></i></span><span class="notification-flash-copy"><strong>İşlem tamamlandı</strong><span>' . htmlspecialchars($successMsg) . '</span></span>',
]) ?>
<?php endif; ?>
<?php if ($errorMsg): ?>
<?= adminRenderAlert('', 'danger', [
    'icon' => '',
    'class' => 'notification-flash notification-flash-error',
    'role' => 'alert',
    'closable' => true,
    'close_class' => 'notification-flash-close',
    'close_label' => 'Uyarıyı kapat',
    'html' => '<span class="notification-flash-icon"><i class="bi bi-exclamation-triangle"></i></span><span class="notification-flash-copy"><strong>İşlem tamamlanamadı</strong><span>' . htmlspecialchars($errorMsg) . '</span></span>',
]) ?>
<?php endif; ?>
<div class="notifications-page">
    <?= adminRenderPageHero('bi-bell', 'Bildirim merkezi', 'Bildirim Merkezi', 'Tüm kullanıcılara duyuru yapın, gönderim kurallarını yönetin ve bildirim deneyimini tek merkezden ayarlayın.', [], [
        'tag' => 'div',
        'actions_html' => '<div class="notif-stats" aria-label="Bildirim özeti">'
            . '<div class="notif-stat"><strong>' . (int) $notificationStats['total'] . '</strong><span>Toplam</span></div>'
            . '<div class="notif-stat"><strong>' . (int) $notificationStats['global'] . '</strong><span>Genel yayın</span></div>'
            . '<div class="notif-stat"><strong>' . (int) $notificationStats['direct'] . '</strong><span>Özel hedef</span></div>'
            . '<div class="notif-stat"><strong>' . (int) $notificationStats['unread'] . '</strong><span>Hiç okunmamış</span></div>'
            . '</div>',
    ]) ?>

    <div class="notif-tabs">
        <a href="notifications.php?tab=history" class="ui-admin-btn <?= $tab === 'history' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-clock-history"></i> Geçmiş Bildirimler
        </a>
        <a href="notifications.php?tab=new" class="ui-admin-btn <?= $tab === 'new' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-send-plus"></i> Yeni Bildirim Gönder
        </a>
        <a href="notifications.php?tab=site" class="ui-admin-btn <?= $tab === 'site' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-bell"></i> Site İçi Bildirimleri
        </a>
        <a href="notifications.php?tab=email" class="ui-admin-btn <?= $tab === 'email' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-envelope-paper"></i> E-Posta Bildirimleri
        </a>
        <a href="notifications.php?tab=settings" class="ui-admin-btn <?= $tab === 'settings' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-sliders"></i> Bildirim Ayarları
        </a>
    </div>

    <?php if ($tab === 'new'): ?>
        <div class="notif-card">
            <div class="notif-card-header">
                <div>
                    <h3>Yeni Bildirim Oluştur</h3>
                    <p>Gönderim limitleri ve link güvenliği Bildirim Ayarları sekmesindeki kurallara göre uygulanır.</p>
                </div>
                <div class="notif-toolbar-badges">
                    <?= admin_notification_route_badges('site', [
                        'kind' => 'user',
                        'label' => 'Kullanıcı / Tüm Kullanıcılar',
                        'title' => 'Hedef kullanıcı ID girilirse tek kullanıcıya, boş bırakılırsa tüm kullanıcılara gider.',
                    ]) ?>
                    <?php if (!admin_notification_bool($adminSettings, 'notif_center_enabled', '1')): ?>
                        <span class="notif-badge notif-badge-user"><i class="bi bi-lock"></i> Merkez Kapalı</span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="notifications.php?tab=new" class="ui-admin-form" data-live-template-preview="1" data-channel-preview="site" data-template-key="__new">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">

                <div class="notif-form-grid">
                    <?php if (!empty($composerTemplates)): ?>
                        <div class="notif-form-wide">
                            <label class="ui-admin-form-label">Kayıtlı Metin</label>
                            <select id="notificationTemplatePicker" class="ui-admin-form-control">
                                <option value="">Kayıtlı metin kullanma</option>
                                <?php foreach ($composerTemplates as $template): ?>
                                    <option value="<?= htmlspecialchars((string) $template['template_key']) ?>"><?= htmlspecialchars((string) $template['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="notif-help">Kayıt seçildiğinde başlık, mesaj, tip ve link alanları doldurulur; göndermeden önce düzenleyebilirsiniz.</small>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="ui-admin-form-label">Başlık <span class="ui-admin-required">*</span></label>
                        <input type="text" name="title" class="ui-admin-form-control" required maxlength="<?= (int) $maxTitleLength ?>" placeholder="Örn: Sistem Bakımı">
                        <small class="notif-help">Maksimum <?= (int) $maxTitleLength ?> karakter.</small>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">Bildirim Tipi</label>
                        <select name="type" class="ui-admin-form-control">
                            <?php foreach (admin_notification_types() as $typeKey => $typeMeta): ?>
                                <option value="<?= htmlspecialchars($typeKey) ?>" <?= $defaultType === $typeKey ? 'selected' : '' ?>><?= htmlspecialchars($typeMeta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">Hedef Kullanıcı ID</label>
                        <input type="number" name="target_user_id" class="ui-admin-form-control" placeholder="Boş bırakılırsa genel yayın">
                        <small class="notif-help">Boş bırakılırsa tüm kullanıcılara görünür.</small>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">Bağlantı</label>
                        <input type="text" name="link" class="ui-admin-form-control" value="<?= htmlspecialchars($defaultLink) ?>" placeholder="/konu/ornek-baslik-123 veya https://...">
                        <small class="notif-help">Boş bırakılırsa ayarlardaki varsayılan link kullanılır.</small>
                    </div>
                    <div class="notif-form-wide">
                        <label class="ui-admin-form-label">Mesaj İçeriği <span class="ui-admin-required">*</span></label>
                        <textarea name="message" class="ui-admin-form-control" rows="5" required maxlength="<?= (int) $maxMessageLength ?>" placeholder="Bildirim içeriğini buraya yazın..."></textarea>
                        <small class="notif-help">Maksimum <?= (int) $maxMessageLength ?> karakter.</small>
                    </div>
                </div>

                <div class="notif-form-footer notif-composer-actions">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-notification-preview-open>
                        <i class="bi bi-eye"></i> Önizleme
                    </button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary" <?= admin_notification_bool($adminSettings, 'notif_center_enabled', '1') ? '' : 'disabled' ?>>
                        <i class="bi bi-send"></i> Bildirimi Gönder
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'history'): ?>
        <?php
            $historyActionsHtml = '<span class="notif-badge notif-badge-global">' . (int) $totalNotifications . ' kayıt</span>';
            if ($totalNotifications > 0) {
                ob_start();
                ?>
                        <form method="POST" action="notifications.php?tab=history" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Tüm bildirim geçmişi kalıcı olarak silinecek. Bu işlem geri alınamaz.', 'title' => 'Geçmişi Temizle', 'ok' => 'Tümünü Kalıcı Olarak Sil', 'cancel' => 'İptal', 'tone' => 'danger', 'kind' => 'logs-clear', 'icon' => 'bi-trash']) ?>>
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="clear_all">
                            <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" title="Tümünü Sil">
                                <i class="bi bi-trash"></i> Tümünü Sil
                            </button>
                        </form>
                <?php
                $historyActionsHtml .= ob_get_clean();
            }
            echo adminRenderLogListPanelOpen([
                'tag' => 'div',
                'class' => 'notif-card notif-card-flush ui-card',
                'header_class' => 'notif-card-header notif-card-header-flush',
                'icon' => 'bi-send-check',
                'title' => 'Gönderilmiş Bildirimler',
                'count_text' => 'Mesaj önizleme uzunluğu ve sayfa başına kayıt sayısı ayarlardan yönetilir.',
                'actions_html' => $historyActionsHtml,
            ]);
        ?>
            <?= adminRenderLogTableOpen([
                'wrapper_class' => 'ui-admin-table-wrapper ui-table-wrap ui-surface admin-log-table-wrap',
                'table_class' => 'ui-admin-table admin-log-table',
                'table_attrs' => ['aria-label' => 'Gönderilmiş bildirimler'],
            ]) ?>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hedef</th>
                            <th>Bildirim</th>
                            <th>Tarih</th>
                            <th class="notif-action-cell">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                        <?= adminRenderTableEmptyRow(5, [
                            'icon' => 'bi-inbox',
                            'tone' => 'info',
                            'title' => 'Henüz hiç bildirim gönderilmemiş.',
                            'description' => 'Yeni bildirimler gönderildiğinde burada listelenir.',
                        ]) ?>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                    $typeMeta = admin_notification_types()[$notification['type']] ?? admin_notification_types()['info'];
                                    $priority = $typeMeta['class'] === 'error' ? 'high' : (in_array($typeMeta['class'], ['warning', 'system'], true) ? 'medium' : 'normal');
                                    $priorityLabel = $priority === 'high' ? 'Yüksek öncelik' : ($priority === 'medium' ? 'Orta öncelik' : 'Normal');
                                ?>
                                <tr class="notif-row-priority-<?= htmlspecialchars($priority) ?>">
                                    <td>#<?= (int) $notification['id'] ?></td>
                                    <td class="notif-target-cell">
                                        <?= admin_notification_history_target_badges($notification) ?>
                                    </td>
                                    <td>
                                        <div class="notif-message-line">
                                            <i class="bi <?= htmlspecialchars($typeMeta['icon']) ?> type-<?= htmlspecialchars($typeMeta['class']) ?> notif-type-icon"></i>
                                            <div>
                                                <strong class="notif-title-line"><?= htmlspecialchars($notification['title']) ?></strong>
                                                <span class="notif-priority-badge is-<?= htmlspecialchars($priority) ?>"><i class="bi bi-flag-fill"></i> <?= htmlspecialchars($priorityLabel) ?></span>
                                                <div class="history-message"><?= htmlspecialchars(admin_notification_preview((string) $notification['message'], $previewLength)) ?></div>
                                                <?php if ($notification['link']): ?>
                                                    <a href="<?= htmlspecialchars($notification['link']) ?>" target="_blank" rel="noopener" class="notif-link-inline"><i class="bi bi-link-45deg"></i><?= htmlspecialchars($notification['link']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="notif-date-cell"><?= date('d.m.Y H:i', strtotime((string) $notification['created_at'])) ?></td>
                                    <td class="notif-action-cell">
                                        <form method="POST" action="notifications.php?tab=history" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu bildirim kalıcı olarak silinecek ve kullanıcılardan da kaldırılacak. Bu işlem geri alınamaz.', 'title' => 'Kayıtları Temizle', 'ok' => 'Seçilenleri Kalıcı Olarak Sil', 'cancel' => 'İptal', 'tone' => 'danger', 'kind' => 'logs-clear', 'icon' => 'bi-trash']) ?>>
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                            <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger notif-delete-btn" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
            <?= adminRenderLogTableClose() ?>

            <?php if ($totalPages > 1): ?>
            <div class="notif-pagination-bar">
                <span class="notif-pagination-meta">Sayfa <?= (int) $page ?> / <?= (int) $totalPages ?> (Toplam <?= (int) $totalNotifications ?> bildirim)</span>
                <?= adminRenderPagination($totalPages, $page, static fn (int $targetPage): string => '?tab=history&page=' . $targetPage, [
                    'wrapper_class' => 'notif-pagination-actions',
                    'inner_class' => 'notif-pagination-list',
                    'aria_label' => 'Bildirim geçmişi sayfalama',
                ]) ?>
            </div>
            <?php endif; ?>
        <?= adminRenderLogListPanelClose('div') ?>
    <?php endif; ?>


    <?php if ($tab === 'site'): ?>
        <?php
            $allowedTemplateVariables = notificationTemplateAllowedVariables();
        ?>
        <div class="notification-template-page notification-channel-page">
            <div class="notification-template-toolbar notification-channel-toolbar">
                <div>
                    <h3><i class="bi bi-bell"></i> Site İçi Bildirimleri</h3>
                    <p>Kullanıcı bildirim merkezi, üst menü ve otomatik olay akışında gösterilecek kısa metinleri yönetin.</p>
                </div>
                <div class="notif-toolbar-badges">
                    <span class="notif-badge notif-badge-global"><?= (int) count($notificationTemplates) ?> kayıt</span>
                    <?= admin_notification_route_badges('site', 'user') ?>
                </div>
            </div>

            <?= adminRenderStatCards([
                ['tone' => 'info', 'icon' => 'bi-check2-circle', 'label' => 'Aktif Kayıt', 'value' => number_format((int) $siteTemplateStats['active'], 0, ',', '.')],
                ['tone' => 'success', 'icon' => 'bi-bell', 'label' => 'Site İçi Açık', 'value' => number_format((int) $siteTemplateStats['enabled'], 0, ',', '.')],
                ['tone' => 'warning', 'icon' => 'bi-bell-slash', 'label' => 'Site İçi Kapalı', 'value' => number_format((int) $siteTemplateStats['disabled'], 0, ',', '.')],
            ], ['class' => 'notification-channel-summary', 'aria_label' => 'Site içi bildirim özeti']) ?>

            <?php if ($templateLoadError): ?>
                <?= adminRenderAlert('', 'warning', [
                    'icon' => '',
                    'class' => 'notification-flash notification-flash-warning',
                    'role' => 'alert',
                    'html' => '<span class="notification-flash-icon"><i class="bi bi-exclamation-triangle"></i></span><span class="notification-flash-copy"><strong>Varsayılan bildirim metinleri gösteriliyor</strong><span>' . htmlspecialchars($templateLoadError) . '</span></span>',
                ]) ?>
            <?php endif; ?>

            <div class="notification-template-grid notification-channel-grid ui-grid">
                <?php $adminRegistrationSiteType = (string) ($adminRegistrationSiteTemplate['type'] ?? 'system'); ?>
                <form id="admin-registration-site"
                      method="POST"
                      action="notifications.php?tab=site#admin-registration-site"
                      class="notification-template-card notification-channel-card notification-admin-registration-card ui-card"
                      data-live-template-preview="1"
                      data-channel-preview="site"
                      data-template-key="admin_registration_site"
                      data-preview-type-fields="notif_admin_registration_site_type"
                      data-preview-title-fields="notif_admin_registration_site_title_template"
                      data-preview-message-fields="notif_admin_registration_site_message_template"
                      data-preview-link-fields="notif_admin_registration_site_link_template"
                      data-variable-control="1"
                      data-variable-fields="notif_admin_registration_site_title_template,notif_admin_registration_site_message_template,notif_admin_registration_site_link_template"
                      data-variable-allowed="<?= admin_notification_json_attr(array_keys($adminRegistrationSiteVariables)) ?>"
                      data-variable-required="[]"
                      data-variable-enforce-required="0">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="notification-template-head ui-panel__head">
                        <div>
                            <h4><?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['name'] ?? 'Yeni Kullanıcı Kaydı Admin Bildirimi')) ?></h4>
                            <p><?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['description'] ?? 'Yeni üyelik oluştuğunda admin ve yetkili hesapların bildirim merkezine düşer.')) ?></p>
                            <div class="notification-template-meta">
                                <span class="notif-badge notif-badge-global"><i class="bi bi-person-plus"></i> Otomatik olay</span>
                                <?= admin_notification_route_badges('site', 'admin') ?>
                            </div>
                        </div>
                        <div class="notification-channel-switches">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="notif_admin_registration_site_enabled" value="1" <?= admin_notification_bool_with_legacy($adminSettings, 'notif_admin_registration_site_enabled', '1', 'notif_admin_registration_enabled') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div class="notification-template-body ui-panel__body">
                        <div>
                            <label class="ui-admin-form-label">Bildirim Tipi</label>
                            <select name="notif_admin_registration_site_type" class="ui-admin-form-control">
                                <?php foreach (admin_notification_types() as $typeKey => $typeInfo): ?>
                                    <option value="<?= htmlspecialchars($typeKey) ?>" <?= $adminRegistrationSiteType === $typeKey ? 'selected' : '' ?>><?= htmlspecialchars($typeInfo['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Metin Adı</label>
                            <input type="text" name="notif_admin_registration_site_name" class="ui-admin-form-control" required maxlength="160" value="<?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['name'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Site İçi Link</label>
                            <input type="text" name="notif_admin_registration_site_link_template" class="ui-admin-form-control" maxlength="1024" value="<?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['link_template'] ?? '{{admin_link}}')) ?>">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Açıklama</label>
                            <textarea name="notif_admin_registration_site_description" class="ui-admin-form-control" rows="2"><?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Site İçi Başlık</label>
                            <input type="text" name="notif_admin_registration_site_title_template" class="ui-admin-form-control" required maxlength="255" value="<?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['title_template'] ?? '')) ?>">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Site İçi Mesaj</label>
                            <textarea name="notif_admin_registration_site_message_template" class="ui-admin-form-control" rows="4" required><?= htmlspecialchars((string) ($adminRegistrationSiteTemplate['message_template'] ?? '')) ?></textarea>
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Değişkenler</label>
                            <div class="notification-template-token-list">
                                <?php foreach ($adminRegistrationSiteVariables as $variable => $description): ?>
                                    <span class="notification-template-token" title="<?= htmlspecialchars((string) $description) ?>">{{<?= htmlspecialchars((string) $variable) ?>}}</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="is-wide notification-variable-status" data-variable-status></div>
                    </div>

                    <div class="notification-template-actions">
                        <span class="notif-help">Bu metin yeni üyelik oluştuğunda admin ve yetkili hesaplara gönderilir.</span>
                        <div class="notification-template-actions-group">
                            <button type="submit" name="action" value="reset_admin_registration_site_template" class="ui-admin-btn ui-admin-btn-outline" formnovalidate<?= adminConfirmAttrs(['message' => 'Yeni kullanıcı kayıt admin bildirimi varsayılan metinlere döndürülecek. Devam edilsin mi?', 'title' => 'Metin sıfırlansın mı?', 'ok' => 'Varsayılana Dön', 'tone' => 'warning']) ?>>
                                <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                            </button>
                            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-notification-preview-open>
                                <i class="bi bi-eye"></i> Önizle
                            </button>
                            <button type="submit" name="action" value="send_admin_registration_site_test" class="ui-admin-btn ui-admin-btn-outline" <?= $currentUserId > 0 ? '' : 'disabled' ?>>
                                <i class="bi bi-send-check"></i> Test Gönder
                            </button>
                            <button type="submit" name="action" value="save_site_settings" class="ui-admin-btn ui-admin-btn-primary">
                                <i class="bi bi-save"></i> Kaydet
                            </button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="notifications.php?tab=site" class="notification-template-card notification-channel-card is-create ui-card" data-live-template-preview="1" data-channel-preview="site" data-template-key="__new">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="allow_create" value="1">
                    <input type="hidden" name="channel" value="site">
                    <input type="hidden" name="email_enabled" value="0">
                    <input type="hidden" name="email_subject_template" value="">
                    <input type="hidden" name="email_body_template" value="">
                    <input type="hidden" name="email_link_template" value="">
                    <input type="hidden" name="email_preview_template" value="">

                    <div class="notification-template-head ui-panel__head">
                        <div>
                            <h4>Yeni Özel Bildirim Metni</h4>
                            <p>Manuel gönderim ekranında hızlı seçilecek özel bir site içi bildirim metni oluşturun.</p>
                            <div class="notification-template-meta">
                                <span class="notif-badge notif-badge-user"><i class="bi bi-plus-circle"></i> Özel kayıt</span>
                                <?= admin_notification_route_badges('site', 'user') ?>
                            </div>
                        </div>
                        <div class="notification-channel-switches">
                            <input type="hidden" name="is_active" value="0">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span class="ui-admin-switch-label">Aktif</span>
                            </label>
                            <input type="hidden" name="in_app_enabled" value="0">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="in_app_enabled" value="1" checked>
                                <span class="ui-admin-switch-label">Site içi açık</span>
                            </label>
                        </div>
                    </div>

                    <div class="notification-template-body ui-panel__body">
                        <div>
                            <label class="ui-admin-form-label">Bildirim Tipi</label>
                            <select name="type" class="ui-admin-form-control">
                                <?php foreach (admin_notification_types() as $typeKey => $typeInfo): ?>
                                    <option value="<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeInfo['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Metin Adı</label>
                            <input type="text" name="name" class="ui-admin-form-control" required maxlength="160" placeholder="Haftalık duyuru">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Site İçi Link</label>
                            <input type="text" name="link_template" class="ui-admin-form-control" maxlength="1024" value="{{link}}" placeholder="{{link}}">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Açıklama</label>
                            <textarea name="description" class="ui-admin-form-control" rows="2" placeholder="Bu metin ne zaman kullanılacak?"></textarea>
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Site İçi Başlık</label>
                            <input type="text" name="title_template" class="ui-admin-form-control" required maxlength="255" value="Duyuru: {{topic_title}}">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Site İçi Mesaj</label>
                            <textarea name="message_template" class="ui-admin-form-control" rows="4" required>{{site_name}} duyurusu: {{comment_excerpt}}</textarea>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Değişkenler</label>
                            <div class="notification-template-token-list">
                                <?php foreach ($allowedTemplateVariables as $variable => $description): ?>
                                    <span class="notification-template-token" title="<?= htmlspecialchars($description) ?>">{{<?= htmlspecialchars($variable) ?>}}</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="notification-template-actions">
                        <span class="notif-help">Kaydedilince Yeni Bildirim Gönder alanındaki kayıtlı metin seçicisinde görünür.</span>
                        <div class="notification-template-actions-group">
                            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-notification-preview-open>
                                <i class="bi bi-eye"></i> Önizle
                            </button>
                            <button type="submit" name="action" value="save_template" class="ui-admin-btn ui-admin-btn-primary">
                                <i class="bi bi-save"></i> Metin Oluştur
                            </button>
                        </div>
                    </div>
                </form>

                <?php foreach ($notificationTemplates as $template): ?>
                    <?php
                        $templateKey = (string) $template['template_key'];
                        $anchor = admin_notification_template_anchor($templateKey);
                        $variables = array_values((array) ($template['variables'] ?? []));
                    ?>
                    <form id="<?= htmlspecialchars($anchor) ?>" method="POST" action="notifications.php?tab=site#<?= htmlspecialchars($anchor) ?>" class="notification-template-card notification-channel-card ui-card" data-live-template-preview="1" data-channel-preview="site" data-template-key="<?= htmlspecialchars($templateKey) ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="channel" value="site">
                        <input type="hidden" name="template_key" value="<?= htmlspecialchars($templateKey) ?>">
                        <input type="hidden" name="email_enabled" value="<?= (int) ($template['email_enabled'] ?? 0) ?>">
                        <input type="hidden" name="email_subject_template" value="<?= htmlspecialchars((string) ($template['email_subject_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="email_body_template" value="<?= htmlspecialchars((string) ($template['email_body_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="email_link_template" value="<?= htmlspecialchars((string) ($template['email_link_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="email_preview_template" value="<?= htmlspecialchars((string) ($template['email_preview_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="notification-template-head ui-panel__head">
                            <div>
                                <h4><?= htmlspecialchars((string) $template['name']) ?></h4>
                                <p><?= htmlspecialchars((string) ($template['description'] ?? '')) ?></p>
                                <div class="notification-template-meta">
                                    <span class="notif-badge <?= !empty($template['is_default']) ? 'notif-badge-global' : 'notif-badge-user' ?>">
                                        <i class="bi <?= !empty($template['is_default']) ? 'bi-diagram-3' : 'bi-pencil-square' ?>"></i>
                                        <?= !empty($template['is_default']) ? 'Varsayılan metin' : 'Özel metin' ?>
                                    </span>
                                    <?= admin_notification_route_badges('site', 'user') ?>
                                </div>
                            </div>
                            <div class="notification-channel-switches">
                                <input type="hidden" name="is_active" value="0">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) ($template['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Aktif</span>
                                </label>
                                <input type="hidden" name="in_app_enabled" value="0">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="in_app_enabled" value="1" <?= (int) ($template['in_app_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Site içi açık</span>
                                </label>
                            </div>
                        </div>

                        <div class="notification-template-body ui-panel__body">
                            <div>
                                <label class="ui-admin-form-label">Metin Adı</label>
                                <input type="text" name="name" class="ui-admin-form-control" required maxlength="160" value="<?= htmlspecialchars((string) $template['name']) ?>">
                            </div>
                            <div>
                                <label class="ui-admin-form-label">Bildirim Tipi</label>
                                <select name="type" class="ui-admin-form-control">
                                    <?php foreach (admin_notification_types() as $typeKey => $typeInfo): ?>
                                        <option value="<?= htmlspecialchars($typeKey) ?>" <?= (string) $template['type'] === $typeKey ? 'selected' : '' ?>><?= htmlspecialchars($typeInfo['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Açıklama</label>
                                <textarea name="description" class="ui-admin-form-control" rows="2"><?= htmlspecialchars((string) ($template['description'] ?? '')) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Site İçi Başlık</label>
                                <input type="text" name="title_template" class="ui-admin-form-control" required maxlength="255" value="<?= htmlspecialchars((string) $template['title_template']) ?>">
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Site İçi Mesaj</label>
                                <textarea name="message_template" class="ui-admin-form-control" rows="4" required><?= htmlspecialchars((string) $template['message_template']) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Site İçi Link</label>
                                <input type="text" name="link_template" class="ui-admin-form-control" maxlength="1024" value="<?= htmlspecialchars((string) ($template['link_template'] ?? '')) ?>">
                            </div>
                            <div>
                                <label class="ui-admin-form-label">Değişkenler</label>
                                <div class="notification-template-token-list">
                                    <?php foreach ($variables as $variable): ?>
                                        <span class="notification-template-token" title="<?= htmlspecialchars($allowedTemplateVariables[$variable] ?? '') ?>">{{<?= htmlspecialchars((string) $variable) ?>}}</span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="notification-template-actions">
                            <span class="notif-help"><?= !empty($template['is_default']) ? 'Varsayılan olay metni' : 'Özel manuel metin' ?></span>
                            <div class="notification-template-actions-group">
                                <?php if (!empty($template['is_default'])): ?>
                                    <button type="submit" name="action" value="reset_template" class="ui-admin-btn ui-admin-btn-outline" formnovalidate<?= adminConfirmAttrs(['message' => 'Bu kaydı varsayılan metinlere döndürmek istiyor musunuz?', 'title' => 'Metin sıfırlansın mı?', 'ok' => 'Sıfırla', 'tone' => 'warning']) ?>>
                                        <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="delete_template" class="ui-admin-btn ui-admin-btn-danger" formnovalidate<?= adminConfirmAttrs(['message' => 'Bu özel metni silmek istiyor musunuz?', 'title' => 'Metin silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-notification-preview-open>
                                    <i class="bi bi-eye"></i> Önizle
                                </button>
                                <button type="submit" name="action" value="send_site_test" class="ui-admin-btn ui-admin-btn-outline" <?= $currentUserId > 0 ? '' : 'disabled' ?>>
                                    <i class="bi bi-send-check"></i> Test Gönder
                                </button>
                                <button type="submit" name="action" value="save_template" class="ui-admin-btn ui-admin-btn-primary">
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'email'): ?>
        <?php $allowedTemplateVariables = notificationTemplateAllowedVariables(); ?>
        <div class="notification-template-page notification-channel-page">
            <div class="notification-template-toolbar notification-channel-toolbar">
                <div>
                    <h3><i class="bi bi-envelope-paper"></i> E-Posta Bildirimleri</h3>
                    <p>E-posta konu satırı, gövde metni, önizleme ve kuyruk davranışını site içi metinden bağımsız yönetin.</p>
                </div>
                <span class="notif-badge <?= admin_notification_bool($adminSettings, 'notif_email_channel_ready', '0') ? 'notif-badge-global' : 'notif-badge-user' ?>">
                    <?= admin_notification_bool($adminSettings, 'notif_email_channel_ready', '0') ? 'Kanal aktif' : 'Kanal kapalı' ?>
                </span>
            </div>

            <?php if (!$emailTemplateSchemaReady && $templateSchemaNotice): ?>
                <?= adminRenderAlert('', 'warning', [
                    'icon' => '',
                    'class' => 'notification-flash notification-flash-warning notification-schema-warning',
                    'role' => 'status',
                    'html' => '<span class="notification-flash-icon"><i class="bi bi-database-exclamation"></i></span><span class="notification-flash-copy"><strong>E-posta metin alanları bekliyor</strong><span>' . htmlspecialchars($templateSchemaNotice) . ' <a href="database-sync/" class="notif-link-inline">Veritabanı Senkronizasyonu</a></span></span>',
                ]) ?>
            <?php endif; ?>

            <div class="notification-email-subtabs" role="tablist" aria-label="E-posta bildirim grupları">
                <a role="tab" aria-selected="<?= $emailGroup === 'account' ? 'true' : 'false' ?>" href="notifications.php?tab=email&amp;email_group=account" class="notification-email-subtab <?= $emailGroup === 'account' ? 'is-active' : '' ?>">
                    <i class="bi bi-person-check"></i>
                    <span><strong>Hesap</strong><small><?= (int) $accountEmailStats['enabled'] ?>/<?= (int) $accountEmailStats['total'] ?> aktif</small></span>
                </a>
                <a role="tab" aria-selected="<?= $emailGroup === 'admin' ? 'true' : 'false' ?>" href="notifications.php?tab=email&amp;email_group=admin" class="notification-email-subtab <?= $emailGroup === 'admin' ? 'is-active' : '' ?>">
                    <i class="bi bi-shield-check"></i>
                    <span><strong>Yönetici</strong><small><?= (int) $adminEmailStats['enabled'] ?>/<?= (int) $adminEmailStats['total'] ?> aktif</small></span>
                </a>
                <a role="tab" aria-selected="<?= $emailGroup === 'events' ? 'true' : 'false' ?>" href="notifications.php?tab=email&amp;email_group=events" class="notification-email-subtab <?= $emailGroup === 'events' ? 'is-active' : '' ?>">
                    <i class="bi bi-envelope-check"></i>
                    <span><strong>Olay</strong><small><?= (int) $emailTemplateStats['enabled'] ?>/<?= (int) count($notificationTemplates) ?> açık</small></span>
                </a>
                <a role="tab" aria-selected="<?= $emailGroup === 'settings' ? 'true' : 'false' ?>" href="notifications.php?tab=email&amp;email_group=settings" class="notification-email-subtab <?= $emailGroup === 'settings' ? 'is-active' : '' ?>">
                    <i class="bi bi-sliders"></i>
                    <span><strong>Kuyruk</strong><small><?= admin_notification_bool($adminSettings, 'notif_email_channel_ready', '0') ? 'aktif' : 'kapalı' ?></small></span>
                </a>
            </div>

            <?php if ($emailGroup === 'settings'): ?>
            <form method="POST" action="notifications.php?tab=email&amp;email_group=settings" class="notification-email-settings-card ui-card">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_email_settings">
                <?= adminRenderStatCards([
                    ['tone' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Kuyrukta', 'value' => number_format((int) $emailQueueStats['queued'], 0, ',', '.')],
                    ['tone' => 'info', 'icon' => 'bi-arrow-repeat', 'label' => 'İşleniyor', 'value' => number_format((int) $emailQueueStats['processing'], 0, ',', '.')],
                    ['tone' => 'success', 'icon' => 'bi-envelope-check', 'label' => 'Gönderildi', 'value' => number_format((int) $emailQueueStats['sent'], 0, ',', '.')],
                    ['tone' => 'danger', 'icon' => 'bi-envelope-exclamation', 'label' => 'Hatalı', 'value' => number_format((int) $emailQueueStats['failed'], 0, ',', '.')],
                ], ['class' => 'notification-email-queue-summary', 'aria_label' => 'E-posta kuyruğu özeti']) ?>
                <div class="notification-email-settings-grid">
                    <?php foreach ($emailSettingsSchema as $item): ?>
                        <?php $value = admin_notification_setting_value($adminSettings, $item); ?>
                        <div class="notification-setting-item">
                            <?php if ($item['type'] === 'bool'): ?>
                                <div class="notification-switch-row">
                                    <span class="notification-setting-label notif-setting-label-flat">
                                        <span>
                                            <strong><?= htmlspecialchars($item['label']) ?></strong>
                                            <span><?= htmlspecialchars($item['help']) ?></span>
                                        </span>
                                    </span>
                                    <label class="ui-admin-switch">
                                        <input type="checkbox" name="<?= htmlspecialchars($item['key']) ?>" value="1" <?= admin_notification_bool($adminSettings, (string) $item['key'], (string) ($item['default'] ?? '0')) ? 'checked' : '' ?> <?= (!$emailTemplateSchemaReady && $item['key'] === 'notif_email_channel_ready') ? 'disabled' : '' ?>>
                                        <span class="ui-admin-switch-label">Aktif</span>
                                    </label>
                                </div>
                            <?php else: ?>
                                <label class="notification-setting-label">
                                    <span>
                                        <strong><?= htmlspecialchars($item['label']) ?></strong>
                                        <span><?= htmlspecialchars($item['help']) ?></span>
                                    </span>
                                </label>
                                <input type="number" name="<?= htmlspecialchars($item['key']) ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($value) ?>" min="<?= (int) ($item['min'] ?? 0) ?>" max="<?= (int) ($item['max'] ?? 999999) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="notification-template-actions">
                    <small class="notif-help notif-cron-help">Cron komutu: <code>php cron/send-notification-email-queue.php --limit=25</code></small>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> E-Posta Ayarlarını Kaydet</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($emailGroup === 'account'): ?>
            <div id="account-email-settings" class="notification-template-toolbar notification-channel-toolbar notification-account-email-toolbar">
                <div>
                    <h3><i class="bi bi-person-check"></i> Hesap E-Posta Şablonları</h3>
                    <p>Kayıt sonrası hoş geldin, doğrulama, şifre ve hesap güvenliği e-postalarını ayrı ayrı yönetin. SMTP sunucusu ve gönderen bilgileri Gelişmiş Ayarlar > E-posta Ayarları bölümünden kullanılır.</p>
                </div>
                <div class="notif-toolbar-badges">
                    <span class="notif-badge <?= $accountEmailSystemEnabled ? 'notif-badge-global' : 'notif-badge-user' ?>">
                        <?= $accountEmailSystemEnabled ? 'Hesap e-postaları aktif' : 'Hesap e-postaları kapalı' ?>
                    </span>
                    <?= admin_notification_route_badges('email', 'user') ?>
                </div>
            </div>

            <?= adminRenderStatCards([
                ['tone' => 'info', 'icon' => 'bi-envelope-paper', 'label' => 'Hesap Şablonu', 'value' => number_format((int) $accountEmailStats['total'], 0, ',', '.')],
                ['tone' => 'success', 'icon' => 'bi-envelope-check', 'label' => 'Aktif', 'value' => number_format((int) $accountEmailStats['enabled'], 0, ',', '.')],
                ['tone' => 'warning', 'icon' => 'bi-envelope-slash', 'label' => 'Kapalı', 'value' => number_format((int) $accountEmailStats['disabled'], 0, ',', '.')],
            ], ['class' => 'notification-channel-summary notification-account-email-summary', 'aria_label' => 'Hesap e-posta şablon özeti']) ?>

            <?php if (!$accountEmailSystemEnabled): ?>
                <?= adminRenderAlert('Hesap e-postaları genel anahtarı kapalı olduğu için yeni üyelere hoş geldin e-postası gönderilmez. "Hesap E-postaları Aktif" anahtarını açıp kaydedin.', 'warning', ['icon' => 'bi-exclamation-triangle']) ?>
            <?php endif; ?>

            <form method="POST" action="notifications.php?tab=email&amp;email_group=account#account-email-settings" class="notification-email-settings-card notification-account-email-settings-card ui-card">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_account_email_settings">
                <div class="notification-email-settings-grid">
                    <div class="notification-setting-item is-wide">
                        <div class="notification-switch-row">
                            <span class="notification-setting-label notif-setting-label-flat">
                                <span>
                                    <strong>Hesap E-postaları Aktif</strong>
                                    <span>Kayıt sonrası hoş geldin ve hesap güvenliği e-postalarının otomatik gönderimini açar veya kapatır.</span>
                                </span>
                            </span>
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="account_email_system_enabled" value="1" <?= $accountEmailSystemEnabled ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="notification-template-actions">
                    <small class="notif-help">Hoş geldin e-postası için “Yeni Üyeye Hoş Geldin” şablonu ve bu genel anahtar açık olmalıdır.</small>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Hesap E-Posta Ayarını Kaydet</button>
                </div>
            </form>

            <div class="notification-template-grid notification-channel-grid account-email-template-list ui-grid">
                <?php foreach ($accountEmailCatalog as $accountTemplateKey => $accountTemplate): ?>
                    <?php
                        $accountTemplateKey = (string) $accountTemplateKey;
                        $accountAnchor = admin_notification_account_email_anchor($accountTemplateKey);
                        $enabledKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'enabled');
                        $subjectKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'subject');
                        $bodyKey = \App\Engine\Email\AccountEmailService::settingKey($accountTemplateKey, 'body');
                        $enabledActive = admin_notification_bool($adminSettings, $enabledKey, (string) ($accountTemplate['enabled'] ?? '1'));
                        $subjectValue = (string) ($adminSettings[$subjectKey] ?? $accountTemplate['subject']);
                        $bodyValue = (string) ($adminSettings[$bodyKey] ?? $accountTemplate['body']);
                        $accountRequired = $accountEmailRequiredVariables[$accountTemplateKey] ?? [];
                    ?>
                    <form id="<?= htmlspecialchars($accountAnchor) ?>"
                          method="POST"
                          action="notifications.php?tab=email&amp;email_group=account#<?= htmlspecialchars($accountAnchor) ?>"
                          class="notification-template-card notification-channel-card account-email-template-card ui-card"
                          data-account-email-card="<?= htmlspecialchars($accountTemplateKey) ?>"
                          data-variable-control="1"
                          data-variable-fields="<?= htmlspecialchars($subjectKey . ',' . $bodyKey) ?>"
                          data-variable-allowed="<?= admin_notification_json_attr($accountEmailAllowedVariables) ?>"
                          data-variable-required="<?= admin_notification_json_attr($accountRequired) ?>"
                          data-variable-enforce-required="1">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="account_email_template_key" value="<?= htmlspecialchars($accountTemplateKey) ?>">

                        <div class="notification-template-head ui-panel__head">
                            <div>
                                <h4><?= htmlspecialchars((string) $accountTemplate['label']) ?></h4>
                                <p><?= htmlspecialchars((string) $accountTemplate['description']) ?></p>
                                <div class="notification-template-meta">
                                    <span class="notif-badge <?= $enabledActive ? 'notif-badge-global' : 'notif-badge-user' ?>">
                                        <i class="bi <?= $enabledActive ? 'bi-envelope-check' : 'bi-envelope-slash' ?>"></i>
                                        <?= $enabledActive ? 'Şablon aktif' : 'Şablon kapalı' ?>
                                    </span>
                                    <?= admin_notification_route_badges('email', 'user') ?>
                                </div>
                            </div>
                            <div class="notification-channel-switches">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="<?= htmlspecialchars($enabledKey) ?>" value="1" <?= $enabledActive ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Aktif</span>
                                </label>
                            </div>
                        </div>

                        <div class="notification-template-body ui-panel__body">
                            <div class="is-wide">
                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($subjectKey) ?>">E-posta Konusu</label>
                                <input id="<?= htmlspecialchars($subjectKey) ?>" name="<?= htmlspecialchars($subjectKey) ?>" class="ui-admin-form-control" maxlength="255" value="<?= htmlspecialchars($subjectValue) ?>" required>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($bodyKey) ?>">HTML E-posta İçeriği</label>
                                <textarea id="<?= htmlspecialchars($bodyKey) ?>" name="<?= htmlspecialchars($bodyKey) ?>" class="ui-admin-form-control account-email-body" rows="10" required><?= htmlspecialchars($bodyValue) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Değişkenler</label>
                                <div class="notification-template-token-list">
                                    <?php foreach ($accountEmailAllowedVariables as $variable): ?>
                                        <button type="button" class="notification-template-token account-email-token" data-token="{{<?= htmlspecialchars($variable) ?>}}">{{<?= htmlspecialchars($variable) ?>}}</button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="is-wide notification-variable-status" data-variable-status></div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label" for="account_email_test_recipient_<?= htmlspecialchars($accountTemplateKey) ?>">Test Alıcısı</label>
                                <input id="account_email_test_recipient_<?= htmlspecialchars($accountTemplateKey) ?>" name="account_email_test_recipient" class="ui-admin-form-control" type="email" value="<?= htmlspecialchars((string) ($_SESSION['_auth_user_email'] ?? '')) ?>" placeholder="test@domain.com">
                            </div>
                            <textarea class="ui-admin-hidden account-email-default-body"><?= htmlspecialchars((string) $accountTemplate['body']) ?></textarea>
                        </div>

                        <div class="notification-template-actions">
                            <span class="notif-help">Bu şablon hesap işlemlerinden direkt gönderilir; bildirim kuyruğunu beklemez.</span>
                            <div class="notification-template-actions-group">
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline account-email-reset" data-default-subject="<?= htmlspecialchars((string) $accountTemplate['subject']) ?>">
                                    <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                                </button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline account-email-preview-button">
                                    <i class="bi bi-eye"></i> Önizle
                                </button>
                                <button type="submit" name="action" value="send_account_email_test" class="ui-admin-btn ui-admin-btn-outline">
                                    <i class="bi bi-send-check"></i> Test Gönder
                                </button>
                                <button type="submit" name="action" value="save_account_email_template" class="ui-admin-btn ui-admin-btn-primary">
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($emailGroup === 'admin'): ?>
            <div class="notification-template-toolbar notification-channel-toolbar notification-admin-email-toolbar">
                <div>
                    <h3><i class="bi bi-shield-check"></i> Yönetici E-Posta Şablonları</h3>
                    <p>Yeni üye kaydı ve güvenlik yoğunluğu gibi yönetici hesaplarına giden e-postaları ayrı ayrı düzenleyin.</p>
                </div>
                <div class="notif-toolbar-badges">
                    <span class="notif-badge notif-badge-global"><?= (int) $adminEmailStats['enabled'] ?> aktif</span>
                    <?= admin_notification_route_badges('email', 'admin') ?>
                </div>
            </div>

            <?= adminRenderStatCards([
                ['tone' => 'info', 'icon' => 'bi-envelope-paper', 'label' => 'Yönetici Şablonu', 'value' => number_format((int) $adminEmailStats['total'], 0, ',', '.')],
                ['tone' => 'success', 'icon' => 'bi-envelope-check', 'label' => 'Aktif', 'value' => number_format((int) $adminEmailStats['enabled'], 0, ',', '.')],
                ['tone' => 'warning', 'icon' => 'bi-envelope-slash', 'label' => 'Kapalı', 'value' => number_format((int) $adminEmailStats['disabled'], 0, ',', '.')],
            ], ['class' => 'notification-channel-summary notification-admin-email-summary', 'aria_label' => 'Yönetici e-posta şablon özeti']) ?>

            <div class="notification-template-grid notification-channel-grid admin-email-template-list ui-grid">
                <?php foreach ($adminEmailCatalog as $adminTemplateKey => $adminTemplate): ?>
                    <?php
                        $adminTemplateKey = (string) $adminTemplateKey;
                        $adminAnchor = 'admin-email-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $adminTemplateKey);
                        $enabledKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'enabled');
                        $subjectKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'subject');
                        $bodyKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'body');
                        $actionLabelKey = \App\Engine\Email\AdminEmailService::settingKey($adminTemplateKey, 'action_label');
                        $enabledActive = admin_notification_bool($adminSettings, $enabledKey, (string) ($adminTemplate['enabled'] ?? '1'));
                        $subjectValue = (string) ($adminSettings[$subjectKey] ?? $adminTemplate['subject']);
                        $bodyValue = \App\Engine\Email\AdminEmailService::bodyForEditor($adminTemplateKey, (string) ($adminSettings[$bodyKey] ?? $adminTemplate['body']));
                        $actionLabelValue = (string) ($adminSettings[$actionLabelKey] ?? $adminTemplate['action_label']);
                        $adminRequired = $adminEmailRequiredVariables[$adminTemplateKey] ?? [];
                    ?>
                    <form id="<?= htmlspecialchars($adminAnchor) ?>"
                          method="POST"
                          action="notifications.php?tab=email&amp;email_group=admin#<?= htmlspecialchars($adminAnchor) ?>"
                          class="notification-template-card notification-channel-card admin-email-template-card ui-card"
                          data-admin-email-card="<?= htmlspecialchars($adminTemplateKey) ?>"
                          data-variable-control="1"
                          data-variable-fields="<?= htmlspecialchars($subjectKey . ',' . $bodyKey . ',' . $actionLabelKey) ?>"
                          data-variable-allowed="<?= admin_notification_json_attr(array_keys($adminEmailAllowedVariables)) ?>"
                          data-variable-required="<?= admin_notification_json_attr($adminRequired) ?>"
                          data-variable-enforce-required="1">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_email_template_key" value="<?= htmlspecialchars($adminTemplateKey) ?>">

                        <div class="notification-template-head ui-panel__head">
                            <div>
                                <h4><?= htmlspecialchars((string) $adminTemplate['label']) ?></h4>
                                <p><?= htmlspecialchars((string) $adminTemplate['description']) ?></p>
                                <div class="notification-template-meta">
                                    <span class="notif-badge <?= $enabledActive ? 'notif-badge-global' : 'notif-badge-user' ?>">
                                        <i class="bi <?= $enabledActive ? 'bi-envelope-check' : 'bi-envelope-slash' ?>"></i>
                                        <?= $enabledActive ? 'Şablon aktif' : 'Şablon kapalı' ?>
                                    </span>
                                    <?= admin_notification_route_badges('email', 'admin') ?>
                                </div>
                            </div>
                            <div class="notification-channel-switches">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="<?= htmlspecialchars($enabledKey) ?>" value="1" <?= $enabledActive ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Aktif</span>
                                </label>
                            </div>
                        </div>

                        <div class="notification-template-body ui-panel__body">
                            <div class="is-wide">
                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($subjectKey) ?>">E-posta Konusu</label>
                                <input id="<?= htmlspecialchars($subjectKey) ?>" name="<?= htmlspecialchars($subjectKey) ?>" class="ui-admin-form-control" maxlength="255" value="<?= htmlspecialchars($subjectValue) ?>" required>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($bodyKey) ?>">E-posta İçeriği</label>
                                <textarea id="<?= htmlspecialchars($bodyKey) ?>" name="<?= htmlspecialchars($bodyKey) ?>" class="ui-admin-form-control admin-email-body" rows="8" required><?= htmlspecialchars($bodyValue) ?></textarea>
                            </div>
                            <div>
                                <label class="ui-admin-form-label" for="<?= htmlspecialchars($actionLabelKey) ?>">Buton Metni</label>
                                <input id="<?= htmlspecialchars($actionLabelKey) ?>" name="<?= htmlspecialchars($actionLabelKey) ?>" class="ui-admin-form-control" maxlength="80" value="<?= htmlspecialchars($actionLabelValue) ?>">
                            </div>
                            <div>
                                <label class="ui-admin-form-label" for="admin_email_test_recipient_<?= htmlspecialchars($adminTemplateKey) ?>">Test Alıcısı</label>
                                <input id="admin_email_test_recipient_<?= htmlspecialchars($adminTemplateKey) ?>" name="admin_email_test_recipient" class="ui-admin-form-control" type="email" value="<?= htmlspecialchars((string) ($_SESSION['_auth_user_email'] ?? '')) ?>" placeholder="test@domain.com">
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Değişkenler</label>
                                <div class="notification-template-token-list">
                                    <?php foreach ($adminEmailAllowedVariables as $variable => $description): ?>
                                        <button type="button" class="notification-template-token admin-email-token" data-token="{{<?= htmlspecialchars((string) $variable) ?>}}" title="<?= htmlspecialchars((string) $description) ?>">{{<?= htmlspecialchars((string) $variable) ?>}}</button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="is-wide notification-variable-status" data-variable-status></div>
                            <textarea class="ui-admin-hidden admin-email-default-body"><?= htmlspecialchars((string) $adminTemplate['body']) ?></textarea>
                        </div>

                        <div class="notification-template-actions">
                            <span class="notif-help">Bu grup admin hesaplarına giden otomatik e-postaları yönetir.</span>
                            <div class="notification-template-actions-group">
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline admin-email-reset" data-default-subject="<?= htmlspecialchars((string) $adminTemplate['subject']) ?>" data-default-action-label="<?= htmlspecialchars((string) $adminTemplate['action_label']) ?>">
                                    <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                                </button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline admin-email-preview-button">
                                    <i class="bi bi-eye"></i> Önizle
                                </button>
                                <button type="submit" name="action" value="send_admin_email_test" class="ui-admin-btn ui-admin-btn-outline">
                                    <i class="bi bi-send-check"></i> Test Gönder
                                </button>
                                <button type="submit" name="action" value="save_admin_email_template" class="ui-admin-btn ui-admin-btn-primary">
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($emailGroup === 'events'): ?>
            <div class="notification-template-toolbar notification-channel-toolbar notification-event-email-toolbar">
                <div>
                    <h3><i class="bi bi-envelope-check"></i> Olay E-Posta Şablonları</h3>
                    <p>Yorum, bahsetme, favori konu ve moderasyon olaylarından çıkan kuyruklu e-posta metinlerini yönetin.</p>
                </div>
                <div class="notif-toolbar-badges">
                    <span class="notif-badge notif-badge-global"><?= (int) count($notificationTemplates) ?> kayıt</span>
                    <?= admin_notification_route_badges('email', 'user') ?>
                </div>
            </div>

            <?= adminRenderStatCards([
                ['tone' => 'info', 'icon' => 'bi-check2-circle', 'label' => 'Aktif Kayıt', 'value' => number_format((int) $emailTemplateStats['active'], 0, ',', '.')],
                ['tone' => 'success', 'icon' => 'bi-envelope-check', 'label' => 'E-Posta Açık', 'value' => number_format((int) $emailTemplateStats['enabled'], 0, ',', '.')],
                ['tone' => 'warning', 'icon' => 'bi-envelope-exclamation', 'label' => 'Metni Eksik', 'value' => number_format((int) $emailTemplateStats['missing'], 0, ',', '.')],
            ], ['class' => 'notification-channel-summary', 'aria_label' => 'E-posta bildirim özeti']) ?>

            <div class="notification-template-grid notification-channel-grid ui-grid">
                <?php foreach ($notificationTemplates as $template): ?>
                    <?php
                        $templateKey = (string) $template['template_key'];
                        $anchor = admin_notification_template_anchor($templateKey);
                        $emailErrors = notificationTemplateEmailCopyErrors($template);
                        $emailReady = $emailErrors === [];
                        $emailControlsDisabled = !$emailTemplateSchemaReady;
                        $emailTestDisabled = $emailControlsDisabled || !admin_notification_bool($adminSettings, 'notif_email_channel_ready', '0') || !$currentAdminEmailRecipient;
                        $variables = array_values((array) ($template['variables'] ?? []));
                        $emailRequired = admin_notification_required_notification_variables($templateKey, 'email');
                    ?>
                    <form id="<?= htmlspecialchars($anchor) ?>"
                          method="POST"
                          action="notifications.php?tab=email&amp;email_group=events#<?= htmlspecialchars($anchor) ?>"
                          class="notification-template-card notification-channel-card ui-card <?= !$emailReady ? 'is-email-missing' : '' ?>"
                          data-live-template-preview="1"
                          data-channel-preview="email"
                          data-template-key="<?= htmlspecialchars($templateKey) ?>"
                          data-variable-control="1"
                          data-variable-fields="email_subject_template,email_body_template,email_link_template,email_preview_template"
                          data-variable-allowed="<?= admin_notification_json_attr(array_keys($allowedTemplateVariables)) ?>"
                          data-variable-required="<?= admin_notification_json_attr($emailRequired) ?>"
                          data-variable-enforce-required="conditional"
                          data-variable-required-toggle="email_enabled">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="channel" value="email">
                        <input type="hidden" name="template_key" value="<?= htmlspecialchars($templateKey) ?>">
                        <input type="hidden" name="in_app_enabled" value="<?= (int) ($template['in_app_enabled'] ?? 1) ?>">
                        <input type="hidden" name="title_template" value="<?= htmlspecialchars((string) ($template['title_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="message_template" value="<?= htmlspecialchars((string) ($template['message_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="link_template" value="<?= htmlspecialchars((string) ($template['link_template'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="notification-template-head ui-panel__head">
                            <div>
                                <h4><?= htmlspecialchars((string) $template['name']) ?></h4>
                                <p><?= htmlspecialchars((string) ($template['description'] ?? '')) ?></p>
                                <div class="notification-template-meta">
                                    <span class="notif-badge <?= $emailReady ? 'notif-badge-global' : 'notif-badge-user' ?>">
                                        <i class="bi <?= $emailReady ? 'bi-envelope-check' : 'bi-envelope-exclamation' ?>"></i>
                                        <?= $emailReady ? 'E-posta metni hazır' : 'E-posta metni eksik' ?>
                                    </span>
                                    <?= admin_notification_route_badges('email', 'user') ?>
                                </div>
                            </div>
                            <div class="notification-channel-switches">
                                <input type="hidden" name="is_active" value="0">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) ($template['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Aktif</span>
                                </label>
                                <input type="hidden" name="email_enabled" value="0">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="email_enabled" value="1" <?= (int) ($template['email_enabled'] ?? 0) === 1 ? 'checked' : '' ?> <?= $emailControlsDisabled ? 'disabled' : '' ?>>
                                    <span class="ui-admin-switch-label">E-posta açık</span>
                                </label>
                            </div>
                        </div>

                        <div class="notification-template-body ui-panel__body">
                            <div>
                                <label class="ui-admin-form-label">Metin Adı</label>
                                <input type="text" name="name" class="ui-admin-form-control" required maxlength="160" value="<?= htmlspecialchars((string) $template['name']) ?>">
                            </div>
                            <div>
                                <label class="ui-admin-form-label">Bildirim Tipi</label>
                                <select name="type" class="ui-admin-form-control">
                                    <?php foreach (admin_notification_types() as $typeKey => $typeInfo): ?>
                                        <option value="<?= htmlspecialchars($typeKey) ?>" <?= (string) $template['type'] === $typeKey ? 'selected' : '' ?>><?= htmlspecialchars($typeInfo['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Açıklama</label>
                                <textarea name="description" class="ui-admin-form-control" rows="2"><?= htmlspecialchars((string) ($template['description'] ?? '')) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">E-Posta Konusu</label>
                                <input type="text" name="email_subject_template" class="ui-admin-form-control" maxlength="255" value="<?= htmlspecialchars((string) ($template['email_subject_template'] ?? '')) ?>" <?= $emailControlsDisabled ? 'disabled' : '' ?>>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Önizleme Metni</label>
                                <input type="text" name="email_preview_template" class="ui-admin-form-control" maxlength="255" value="<?= htmlspecialchars((string) ($template['email_preview_template'] ?? '')) ?>" <?= $emailControlsDisabled ? 'disabled' : '' ?>>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">E-Posta Gövdesi</label>
                                <textarea name="email_body_template" class="ui-admin-form-control" rows="6" <?= $emailControlsDisabled ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($template['email_body_template'] ?? '')) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">E-Posta Linki</label>
                                <input type="text" name="email_link_template" class="ui-admin-form-control" maxlength="1024" value="<?= htmlspecialchars((string) ($template['email_link_template'] ?? '')) ?>" <?= $emailControlsDisabled ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="ui-admin-form-label">Değişkenler</label>
                                <div class="notification-template-token-list">
                                    <?php foreach ($variables as $variable): ?>
                                        <span class="notification-template-token" title="<?= htmlspecialchars($allowedTemplateVariables[$variable] ?? '') ?>">{{<?= htmlspecialchars((string) $variable) ?>}}</span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="is-wide notification-variable-status" data-variable-status></div>
                            <?php if (!$emailReady): ?>
                                <div class="is-wide notification-email-warning"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars(implode(' ', $emailErrors)) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="notification-template-actions">
                            <span class="notif-help">E-posta metni site içi bildirim metninden bağımsızdır.</span>
                            <div class="notification-template-actions-group">
                                <?php if (!empty($template['is_default'])): ?>
                                    <button type="submit" name="action" value="reset_template" class="ui-admin-btn ui-admin-btn-outline" formnovalidate <?= $emailControlsDisabled ? 'disabled' : '' ?><?= adminConfirmAttrs(['message' => 'Bu kaydı varsayılan metinlere döndürmek istiyor musunuz?', 'title' => 'Metin sıfırlansın mı?', 'ok' => 'Sıfırla', 'tone' => 'warning']) ?>>
                                        <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="delete_template" class="ui-admin-btn ui-admin-btn-danger" formnovalidate<?= adminConfirmAttrs(['message' => 'Bu özel metni silmek istiyor musunuz?', 'title' => 'Metin silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-notification-preview-open>
                                    <i class="bi bi-eye"></i> Önizle
                                </button>
                                <button type="submit" name="action" value="send_email_test" class="ui-admin-btn ui-admin-btn-outline" <?= $emailTestDisabled ? 'disabled' : '' ?>>
                                    <i class="bi bi-envelope-check"></i> Test E-Postası
                                </button>
                                <button type="submit" name="action" value="save_template" class="ui-admin-btn ui-admin-btn-primary" <?= $emailControlsDisabled ? 'disabled' : '' ?>>
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <?php if ($tab === 'settings'): ?>
        <div class="notif-card">
            <div class="notif-card-header">
                <div>
                    <h3>Sistem Bildirim Ayarları</h3>
                    <p>Bu ayarlar kullanıcı sayfası, üst menü API çıktısı, gönderim formu ve otomatik hoş geldin bildirimi üzerinde doğrudan çalışır.</p>
                </div>
            </div>
            <form method="POST" action="notifications.php?tab=settings" class="ui-admin-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="save_settings">


                <div class="notification-settings-layout ui-section">
                    <?php foreach ($settingsSchema as $section): ?>
                        <section class="notification-settings-section ui-section">
                            <div class="notification-settings-section-head ui-panel__head">
                                <h4><?= htmlspecialchars($section['title']) ?></h4>
                                <p><?= htmlspecialchars($section['description']) ?></p>
                            </div>
                            <div class="notification-settings-grid ui-grid">
                                <?php foreach ($section['items'] as $item): ?>
                                    <?php
                                        $value = admin_notification_setting_value($adminSettings, $item);
                                        $isWide = in_array($item['type'], ['textarea'], true) || $item['key'] === 'notif_default_link';
                                    ?>
                                    <div class="notification-setting-item <?= $isWide ? 'is-wide' : '' ?>">
                                        <?php if ($item['type'] === 'bool'): ?>
                                            <div class="notification-switch-row">
                                                <span class="notification-setting-label notif-setting-label-flat">
                                                    <span>
                                                        <strong><?= htmlspecialchars($item['label']) ?></strong>
                                                        <span><?= htmlspecialchars($item['help']) ?></span>
                                                    </span>
                                                </span>
                                                <label class="ui-admin-switch">
                                                    <input type="checkbox" name="<?= htmlspecialchars($item['key']) ?>" value="1" <?= admin_notification_bool($adminSettings, (string) $item['key'], (string) ($item['default'] ?? '0')) ? 'checked' : '' ?>>
                                                    <span class="ui-admin-switch-label">Aktif</span>
                                                </label>
                                            </div>
                                        <?php else: ?>
                                            <label class="notification-setting-label">
                                                <span>
                                                    <strong><?= htmlspecialchars($item['label']) ?></strong>
                                                    <span><?= htmlspecialchars($item['help']) ?></span>
                                                </span>
                                            </label>
                                            <?php if ($item['type'] === 'textarea'): ?>
                                                <textarea name="<?= htmlspecialchars($item['key']) ?>" class="ui-admin-form-control" rows="3"><?= htmlspecialchars($value) ?></textarea>
                                            <?php elseif ($item['type'] === 'select'): ?>
                                                <select name="<?= htmlspecialchars($item['key']) ?>" class="ui-admin-form-control">
                                                    <?php foreach (($item['options'] ?? []) as $optionValue => $optionLabel): ?>
                                                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($item['type'] === 'number'): ?>
                                                <input type="number" name="<?= htmlspecialchars($item['key']) ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($value) ?>" min="<?= (int) ($item['min'] ?? 0) ?>" max="<?= (int) ($item['max'] ?? 999999) ?>">
                                            <?php else: ?>
                                                <input type="text" name="<?= htmlspecialchars($item['key']) ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($value) ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="notif-form-footer">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary">
                        <i class="bi bi-save"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="notification-preview-modal" id="notificationPreviewModal" hidden aria-hidden="true">
    <div class="notification-preview-backdrop" data-notification-preview-close></div>
    <section class="notification-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="notificationPreviewTitle">
        <header class="notification-preview-modal-head">
            <div>
                <span class="notification-preview-channel" data-notification-preview-channel>Önizleme</span>
                <h3 id="notificationPreviewTitle">Bildirim Önizlemesi</h3>
            </div>
            <button type="button" class="ui-admin-detail-close" data-notification-preview-close aria-label="Önizlemeyi kapat">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>
        <div class="notification-preview-modal-body">
            <div class="notification-template-preview notification-preview-modal-content" data-notification-preview-content></div>
        </div>
    </section>
</div>

<script type="application/json" id="adminNotificationsPageData"><?= json_encode([
    'composerTemplates' => $composerTemplatePayload,
    'templatePreviewPayloads' => $templatePreviewPayloads,
    'accountEmailPreviewPayload' => $accountEmailPreviewPayload,
    'adminEmailPreviewPayload' => $adminEmailPreviewPayload,
    'typeMeta' => admin_notification_types(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}' ?></script>
<script src="<?= asset_url('admin/assets/notifications-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
