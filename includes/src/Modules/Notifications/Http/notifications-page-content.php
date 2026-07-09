<?php
require_once $projectRoot . '/includes/notifications.php';

$pageTitle = 'Bildirimleriniz';
$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$notificationsBaseUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('notifications')
    : (rtrim((string) ($baseUri ?? ''), '/') . '/notifications.php');

if ($userId <= 0) {
    $loginUrl = function_exists('routePublicStaticUrl')
        ? routePublicStaticUrl('login')
        : (($baseUri ?? '') . '/giris');
    header('Location: ' . $loginUrl . '?redirect=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? $notificationsBaseUrl)));
    exit;
}

function notification_type_meta(string $type): array
{
    switch ($type) {
        case 'success':
            return ['class' => 'success', 'icon' => 'bi-check2-circle', 'label' => 'Başarılı', 'summary' => 'Onay, tamamlanan işlem veya olumlu sonuç'];
        case 'warning':
            return ['class' => 'warning', 'icon' => 'bi-exclamation-triangle', 'label' => 'Uyarı', 'summary' => 'Dikkat gerektiren hesap veya içerik uyarısı'];
        case 'error':
            return ['class' => 'error', 'icon' => 'bi-x-circle', 'label' => 'Kritik', 'summary' => 'Güvenlik, erişim veya kritik sistem bildirimi'];
        case 'system':
            return ['class' => 'system', 'icon' => 'bi-cpu', 'label' => 'Sistem', 'summary' => 'Bakım, sürüm ve platform duyurusu'];
        default:
            return ['class' => 'info', 'icon' => 'bi-info-circle', 'label' => 'Bilgi', 'summary' => 'Genel bilgilendirme ve standart güncelleme'];
    }
}

function notification_setting_checked(array $settings, string $key, string $default = '1'): string
{
    return (($settings[$key] ?? $default) === '1') ? 'checked' : '';
}

function notification_bool_setting(array $settings, string $key, string $default = '1'): bool
{
    return (($settings[$key] ?? $default) === '1');
}

function notification_int_setting(array $settings, string $key, int $default, int $min, int $max): int
{
    $value = (int) ($settings[$key] ?? $default);
    return max($min, min($max, $value));
}

function notification_user_preference_groups(): array
{
    return [
        'email' => [
            'key' => 'notif_group_email',
            'icon' => 'bi-envelope-check',
            'title' => 'E-posta Bildirimleri',
            'description' => 'E-posta ile gonderilecek hesap, guvenlik ve icerik bildirimlerini yonetin.',
            'default' => '1',
            'items' => [
                ['key' => 'notif_email_updates', 'icon' => 'bi-envelope', 'title' => 'E-posta teslimi', 'description' => 'E-posta destekleyen bildirimler hesabinizdaki e-posta adresine kuyruklanir.', 'default' => '1'],
            ],
        ],
        'header' => [
            'key' => 'notif_group_header',
            'icon' => 'bi-bell',
            'title' => 'Ust Menu Bildirimleri',
            'description' => 'Header rozeti, acilir bildirim listesi ve gorunen bildirim tonlarini yonetin.',
            'default' => '1',
            'items' => [
                ['key' => 'notif_browser_push', 'icon' => 'bi-bell', 'title' => 'Header bildirim merkezi', 'description' => 'Okunmamis rozeti ve acilir bildirim listesini ust menude goster.', 'default' => '1'],
                ['key' => 'notif_type_info', 'icon' => 'bi-info-circle', 'title' => 'Bilgi bildirimleri', 'description' => 'Genel bilgilendirme ve standart platform duyurulari.', 'default' => '1'],
                ['key' => 'notif_type_success', 'icon' => 'bi-check2-circle', 'title' => 'Basari bildirimleri', 'description' => 'Tamamlanan islem, onay ve olumlu sonuc bildirimleri.', 'default' => '1'],
                ['key' => 'notif_type_warning', 'icon' => 'bi-exclamation-triangle', 'title' => 'Uyari bildirimleri', 'description' => 'Dikkat gerektiren hesap ve icerik uyarilari.', 'default' => '1'],
                ['key' => 'notif_type_error', 'icon' => 'bi-x-circle', 'title' => 'Kritik hata bildirimleri', 'description' => 'Guvenlik, erisim ve kritik sistem uyarilari.', 'default' => '1'],
                ['key' => 'notif_type_system', 'icon' => 'bi-cpu', 'title' => 'Sistem bildirimleri', 'description' => 'Bakim, surum ve altyapi duyurulari.', 'default' => '1'],
                ['key' => 'notif_auto_mark_on_open', 'icon' => 'bi-check2-all', 'title' => 'Link acinca okundu yap', 'description' => 'Bildirim baglantisina tikladiginizda bildirimi otomatik okundu olarak isaretle.', 'default' => '1'],
                ['key' => 'notif_compact_view', 'icon' => 'bi-layout-text-sidebar-reverse', 'title' => 'Kompakt gorunum', 'description' => 'Gelen kutusunda daha sik araliklarla daha fazla bildirimi ayni anda goster.', 'default' => '0'],
            ],
        ],
        'events' => [
            'key' => 'notif_group_events',
            'icon' => 'bi-activity',
            'title' => 'Site Etkinlikleri',
            'description' => 'Yorum, bahsetme, favori konu ve moderasyon olaylarindan hangilerinin bildirime donusecegini secin.',
            'default' => '1',
            'items' => notificationEventPreferenceItems(),
        ],
    ];

    return [
        'channels' => [
            'title' => 'Kanallar',
            'description' => 'Bildirimlerin hangi yüzeylerde görüneceğini seçin.',
            'items' => [
                ['key' => 'notif_email_updates', 'icon' => 'bi-envelope', 'title' => 'E-posta bildirimleri', 'description' => 'Hesap, güvenlik ve önemli duyuruları e-posta adresinize gönderelim.', 'default' => '1'],
                ['key' => 'notif_browser_push', 'icon' => 'bi-bell', 'title' => 'Üst menü bildirimleri', 'description' => 'Okunmamış rozetini ve açılır bildirim listesini üst menüde göster.', 'default' => '1'],
                ['key' => 'notif_marketing', 'icon' => 'bi-megaphone', 'title' => 'Duyuru ve kampanyalar', 'description' => 'Yeni özellikler, topluluk duyuruları ve özel etkinlik haberlerini al.', 'default' => '0'],
            ],
        ],
        'types' => [
            'title' => 'Bildirim Tipleri',
            'description' => 'Gelen kutunuzda ve üst menüde görmek istediğiniz bildirim türlerini belirleyin.',
            'items' => [
                ['key' => 'notif_type_info', 'icon' => 'bi-info-circle', 'title' => 'Bilgi bildirimleri', 'description' => 'Genel bilgilendirme ve standart platform duyuruları.', 'default' => '1'],
                ['key' => 'notif_type_success', 'icon' => 'bi-check2-circle', 'title' => 'Başarı bildirimleri', 'description' => 'Tamamlanan işlem, onay ve olumlu sonuç bildirimleri.', 'default' => '1'],
                ['key' => 'notif_type_warning', 'icon' => 'bi-exclamation-triangle', 'title' => 'Uyarı bildirimleri', 'description' => 'Dikkat gerektiren hesap ve içerik uyarıları.', 'default' => '1'],
                ['key' => 'notif_type_error', 'icon' => 'bi-x-circle', 'title' => 'Kritik hata bildirimleri', 'description' => 'Güvenlik, erişim ve kritik sistem uyarıları.', 'default' => '1'],
                ['key' => 'notif_type_system', 'icon' => 'bi-cpu', 'title' => 'Sistem bildirimleri', 'description' => 'Bakım, sürüm ve altyapı duyuruları.', 'default' => '1'],
            ],
        ],
        'events' => [
            'title' => 'Site Etkinlikleri',
            'description' => 'Yorumlar, bahsetmeler, favori konular ve moderasyon kararları için hangi olaylardan bildirim alacağınızı seçin.',
            'items' => notificationEventPreferenceItems(),
        ],
        'experience' => [
            'title' => 'Okuma Deneyimi',
            'description' => 'Bildirim listesinin çalışma şeklini kişiselleştirin.',
            'items' => [
                ['key' => 'notif_auto_mark_on_open', 'icon' => 'bi-check2-all', 'title' => 'Link açınca okundu yap', 'description' => 'Bildirim bağlantısına tıkladığınızda bildirimi otomatik okundu olarak işaretle.', 'default' => '1'],
                ['key' => 'notif_compact_view', 'icon' => 'bi-layout-text-sidebar-reverse', 'title' => 'Kompakt görünüm', 'description' => 'Gelen kutusunda daha sıkı aralıklarla daha fazla bildirimi aynı anda göster.', 'default' => '0'],
            ],
        ],
    ];
}

function notification_user_setting_keys(): array
{
    $keys = [];
    foreach (notification_user_preference_groups() as $group) {
        if (!empty($group['key'])) {
            $keys[] = (string) $group['key'];
        }
        foreach ($group['items'] as $item) {
            $keys[] = $item['key'];
        }
    }

    return array_values(array_unique($keys));
}

function notification_user_default_settings(): array
{
    $defaults = [];
    foreach (notification_user_preference_groups() as $group) {
        if (!empty($group['key'])) {
            $defaults[(string) $group['key']] = (string) ($group['default'] ?? '1');
        }
        foreach ($group['items'] as $item) {
            $defaults[$item['key']] = (string) ($item['default'] ?? '1');
        }
    }

    return $defaults;
}

function notification_enabled_types(array $settings): array
{
    return function_exists('notificationEnabledTypesForUser') ? notificationEnabledTypesForUser($settings) : [];
}

function notification_safe_link(string $link, string $baseUri): string
{
    $link = trim(html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($link === '' || preg_match('~^(?:javascript|data|vbscript):~i', $link) === 1 || str_starts_with($link, '//')) {
        return '';
    }

    $path = (string) (parse_url($link, PHP_URL_PATH) ?? '');
    $query = (string) (parse_url($link, PHP_URL_QUERY) ?? '');
    $normalizedPath = '/' . ltrim($path, '/');
    $basePath = '/' . trim((string) $baseUri, '/');
    if ($basePath !== '/' && str_starts_with($normalizedPath, $basePath . '/')) {
        $normalizedPath = '/' . ltrim(substr($normalizedPath, strlen($basePath)), '/');
    }

    if (preg_match('~/edit-topic\.php$~i', $normalizedPath) === 1) {
        parse_str($query, $params);
        if ((int) ($params['id'] ?? 0) <= 0) {
            return rtrim($baseUri, '/') . '/profile.php?tab=topics';
        }
    }

    return $link;
}

$adminSettings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$notificationCenterEnabled = notification_bool_setting($adminSettings, 'notif_center_enabled', '1');
$respectUserPreferences = notification_bool_setting($adminSettings, 'notif_respect_user_preferences', '1');
$readMoreEnabled = notification_bool_setting($adminSettings, 'notif_enable_read_more', '1');
$globalAutoMarkOnOpen = notification_bool_setting($adminSettings, 'notif_auto_mark_link_click', '1');
$emptyStateTipsEnabled = notification_bool_setting($adminSettings, 'notif_empty_state_tips', '1');
$messageLineLimit = notification_int_setting($adminSettings, 'notif_user_message_lines', 3, 1, 8);

$tab = $_GET['tab'] ?? 'list';
$allowedTabs = ['list', 'settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'list';
}

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'unread', 'read'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: ' . $notificationsBaseUrl . '?tab=settings');
        exit;
    }

    $recommendedSettings = notification_user_default_settings();
    $useRecommendedSettings = (string) ($_POST['preset'] ?? '') === 'recommended';

    foreach (notification_user_setting_keys() as $key) {
        $value = $useRecommendedSettings
            ? (string) ($recommendedSettings[$key] ?? '1')
            : (isset($_POST[$key]) ? '1' : '0');
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$userId, $key, $value, $value]);
    }

    flash('success', $useRecommendedSettings ? 'Bildirim ayarları önerilen düzene alındı.' : 'Bildirim ayarlarınız başarıyla güncellendi.');
    header('Location: ' . $notificationsBaseUrl . '?tab=settings');
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = notification_int_setting($adminSettings, 'notif_user_page_per_page', 10, 5, 50);

$userSettings = [];
$notifications = [];
$notificationStats = ['total' => 0, 'unread' => 0, 'read' => 0];
$totalFilteredNotifications = 0;
$totalPages = 1;
$loadError = null;
$preferenceTypeSql = '';
$preferenceTypeParams = [];
$canFilterNotificationEvents = true;

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        $loadError = 'Bildirim tercihleri yüklenemedi.';
    }

    try {
        notificationEnsureEventSchema($pdo);
        $canFilterNotificationEvents = isset(notificationEventTableColumns($pdo)['event_key']);
    } catch (Throwable $e) {
        $canFilterNotificationEvents = false;
    }

    if (!$notificationCenterEnabled) {
        $preferenceTypeSql = ' AND 1 = 0';
    } else {
        $preferenceWhere = notificationPreferenceWhereSql($userSettings, 'n', $canFilterNotificationEvents);
        $preferenceTypeSql = (string) ($preferenceWhere['sql'] ?? '');
        $preferenceTypeParams = is_array($preferenceWhere['params'] ?? null) ? $preferenceWhere['params'] : [];
    }

    try {
        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN nr.id IS NULL THEN 1 ELSE 0 END), 0) AS unread_count
            FROM notifications n
            LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
            WHERE (n.user_id IS NULL OR n.user_id = ?)
            {$preferenceTypeSql}
        ");
        $statsStmt->execute(array_merge([$userId, $userId], $preferenceTypeParams));
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $notificationStats['total'] = (int) ($stats['total_count'] ?? 0);
        $notificationStats['unread'] = (int) ($stats['unread_count'] ?? 0);
        $notificationStats['read'] = max(0, $notificationStats['total'] - $notificationStats['unread']);
    } catch (Throwable $e) {
        $loadError = 'Bildirim özeti yüklenemedi.';
    }
}

if ($pdo && $tab === 'list') {
    try {
        $filterSql = '';
        if ($filter === 'unread') {
            $filterSql = ' AND nr.id IS NULL';
        } elseif ($filter === 'read') {
            $filterSql = ' AND nr.id IS NOT NULL';
        }

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications n
            LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
            WHERE (n.user_id IS NULL OR n.user_id = ?)
            {$preferenceTypeSql}
            {$filterSql}
        ");
        $countStmt->execute(array_merge([$userId, $userId], $preferenceTypeParams));
        $totalFilteredNotifications = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalFilteredNotifications / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT
                n.id,
                n.title,
                n.message,
                n.type,
                n.link,
                n.created_at,
                CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM notifications n
            LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
            WHERE (n.user_id IS NULL OR n.user_id = ?)
            {$preferenceTypeSql}
            {$filterSql}
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $bindIndex = 3;
        foreach ($preferenceTypeParams as $typeParam) {
            $stmt->bindValue($bindIndex, $typeParam, PDO::PARAM_STR);
            $bindIndex++;
        }
        $stmt->bindValue($bindIndex, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($bindIndex + 1, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $loadError = 'Bildirimler yüklenemedi.';
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error') ?: $loadError;
if (is_string($errorMsg) && str_contains($errorMsg, 'mod bulunamad')) {
    $errorMsg = null;
}
$csrfToken = csrf_token();
$notificationsReadEndpoint = rtrim((string) ($baseUri ?? ''), '/') . '/api/notifications-read.php';

$pageCssFiles = array_values(array_unique(array_merge(
    $pageCssFiles ?? [],
    ['assets/css/public-notifications.css'],
)));

$notifications_compact = notification_bool_setting($userSettings, 'notif_compact_view', '0');
$notifications_shell_class='notifications-shell ui-section' . ($notifications_compact ? 'is-compact' : '') . ($readMoreEnabled ? '' : ' is-readmore-disabled');
$notifications_message_lines = (int) $messageLineLimit;
$notifications_total = number_format($notificationStats['total']);
$notifications_unread = number_format($notificationStats['unread']);
$notifications_read = number_format($notificationStats['read']);
$notifications_has_unread = $notificationStats['unread'] > 0;
$notifications_unread_badge = $notificationStats['unread'] > 99 ? '99+' : (string) (int) $notificationStats['unread'];
$notifications_unread_ratio = $notificationStats['total'] > 0 ? (string) (int) round(($notificationStats['unread'] / $notificationStats['total']) * 100) : '0';
$notifications_per_page = (string) (int) $perPage;
$notifications_tab_list = $tab === 'list';
$notifications_tab_settings = $tab === 'settings';
$notifications_success = (string) ($successMsg ?? '');
$notifications_error = (string) ($errorMsg ?? '');
$notifications_result_count = (string) (int) $totalFilteredNotifications;
$notifications_center_enabled = $notificationCenterEnabled;
$notifications_empty_tips_enabled = $emptyStateTipsEnabled;
$notifications_empty_message = !$notificationCenterEnabled
    ? 'Bildirim merkezi su anda yonetici tarafindan kapali.'
    : ($filter === 'unread'
        ? 'Okunmamis bildiriminiz kalmadi. Yeni bir duyuru geldiginde burada gorunecek.'
        : 'Henuz hesabiniza ait bir bildirim bulunmuyor. Tercihler sekmesinden gormek istediginiz bildirim turlerini degistirebilirsiniz.');
$notifications_read_endpoint = $notificationsReadEndpoint;
$notifications_csrf_token = $csrfToken;
$notifications_read_more_js = $readMoreEnabled ? 'true' : 'false';
$notifications_auto_mark_js = ($globalAutoMarkOnOpen && notification_bool_setting($userSettings, 'notif_auto_mark_on_open', '1')) ? 'true' : 'false';
$notifications_filters = [
    [
        'url' => $notificationsBaseUrl . '?tab=list&filter=all',
        'class' => $filter === 'all' ? 'notifications-filter is-active' : 'notifications-filter',
        'label' => 'Tumu',
        'count' => (string) (int) $notificationStats['total'],
        'data_attr' => '',
    ],
    [
        'url' => $notificationsBaseUrl . '?tab=list&filter=unread',
        'class' => $filter === 'unread' ? 'notifications-filter is-active' : 'notifications-filter',
        'label' => 'Okunmamis',
        'count' => (string) (int) $notificationStats['unread'],
        'data_attr' => 'data-filter-unread',
    ],
    [
        'url' => $notificationsBaseUrl . '?tab=list&filter=read',
        'class' => $filter === 'read' ? 'notifications-filter is-active' : 'notifications-filter',
        'label' => 'Okunmus',
        'count' => (string) (int) $notificationStats['read'],
        'data_attr' => 'data-filter-read',
    ],
];
$notifications_items = [];
foreach ($notifications as $notification) {
    if (!is_array($notification)) {
        continue;
    }
    $typeMeta = notification_type_meta((string) ($notification['type'] ?? 'info'));
    $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
    $createdAt = !empty($notification['created_at']) ? strtotime((string) $notification['created_at']) : false;
    $link = notification_safe_link((string) ($notification['link'] ?? ''), (string) ($baseUri ?? ''));
    $notifications_items[] = [
        'id' => (string) (int) ($notification['id'] ?? 0),
        'class' => $isUnread ? 'notification-item is-unread' : 'notification-item is-read',
        'icon_class' => 'notification-icon is-' . (string) ($typeMeta['class'] ?? 'info'),
        'icon' => (string) ($typeMeta['icon'] ?? 'bi-info-circle'),
        'title' => (string) ($notification['title'] ?? ''),
        'status_class' => $isUnread ? 'notification-status is-unread' : 'notification-status is-read',
        'status_icon' => $isUnread ? 'bi-circle-fill' : 'bi-check2-circle',
        'status_label' => ($isUnread ? 'Okunmamis' : 'Okundu') . ' - ' . (string) ($typeMeta['label'] ?? 'Bilgi'),
        'type_label' => (string) ($typeMeta['label'] ?? 'Bilgi'),
        'type_chip_class' => 'notification-type-chip is-' . (string) ($typeMeta['class'] ?? 'info'),
        'type_summary' => (string) ($typeMeta['summary'] ?? ''),
        'datetime' => (string) ($notification['created_at'] ?? ''),
        'date_title' => $createdAt ? date('d.m.Y H:i', $createdAt) : '',
        'date_short' => $createdAt ? date('d.m.Y', $createdAt) : 'Tarih yok',
        'message' => (string) ($notification['message'] ?? ''),
        'has_link' => $link !== '',
        'link' => $link,
    ];
}
$notifications_has_items = $notifications_items !== [];
$notifications_has_pagination = $totalPages > 1;
$notifications_prev_url = '?tab=list&filter=' . rawurlencode($filter) . '&page=' . max(1, $page - 1);
$notifications_next_url = '?tab=list&filter=' . rawurlencode($filter) . '&page=' . min($totalPages, $page + 1);
$notifications_has_prev = $page > 1;
$notifications_has_next = $page < $totalPages;
$notifications_page_label = (string) (int) $page;
$notifications_total_pages_label = (string) (int) $totalPages;
$notification_preference_groups = [];
foreach (notification_user_preference_groups() as $preferenceGroup) {
    $items = [];
    foreach ($preferenceGroup['items'] as $preferenceItem) {
        $items[] = [
            'key' => (string) ($preferenceItem['key'] ?? ''),
            'input_id' => 'notif-setting-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($preferenceItem['key'] ?? '')),
            'icon' => (string) ($preferenceItem['icon'] ?? 'bi-bell'),
            'title' => (string) ($preferenceItem['title'] ?? ''),
            'description' => (string) ($preferenceItem['description'] ?? ''),
            'checked' => notification_setting_checked($userSettings, (string) ($preferenceItem['key'] ?? ''), (string) ($preferenceItem['default'] ?? '1')),
        ];
    }
    $groupKey = (string) ($preferenceGroup['key'] ?? '');
    $groupChecked = $groupKey !== ''
        ? notification_setting_checked($userSettings, $groupKey, (string) ($preferenceGroup['default'] ?? '1'))
        : '';
    $notification_preference_groups[] = [
        'key' => $groupKey,
        'input_id' => 'notif-group-' . preg_replace('/[^a-z0-9_-]+/i', '-', $groupKey),
        'icon' => (string) ($preferenceGroup['icon'] ?? 'bi-bell'),
        'title' => (string) ($preferenceGroup['title'] ?? ''),
        'description' => (string) ($preferenceGroup['description'] ?? ''),
        'checked' => $groupChecked,
        'is_enabled' => $groupChecked !== '',
        'items' => $items,
    ];
}

$publicHeaderVars = isset($publicHeaderVars) && is_array($publicHeaderVars) ? $publicHeaderVars : [];
$notificationThemeVars = get_defined_vars();
foreach ($notificationThemeVars as $notificationThemeKey => $notificationThemeValue) {
    if ($notificationThemeKey === 'notification_preference_groups' || str_starts_with((string) $notificationThemeKey, 'notifications_')) {
        $publicHeaderVars[$notificationThemeKey] = $notificationThemeValue;
    }
}

require_once $projectRoot . '/includes/public-header.php';
?>



<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container public-container public-breadcrumb breadcrumb-container breadcrumb-container-spaced ui-container">
    <nav class="breadcrumb" aria-label="Sayfa yolu">
        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <span>Bildirim Merkezi</span>
    </nav>
</div>
<?php endif; ?>

<main
    class="public-container public-content notifications-shell <?= notification_bool_setting($userSettings, 'notif_compact_view', '0') ? 'is-compact' : '' ?> <?= $readMoreEnabled ? '' : 'is-readmore-disabled' ?> ui-container ui-section"
    data-notifications-page
    data-notifications-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-notifications-read-endpoint="<?= htmlspecialchars($notificationsReadEndpoint, ENT_QUOTES, 'UTF-8') ?>"
    data-notifications-read-more="<?= $readMoreEnabled ? '1' : '0' ?>"
    data-notifications-auto-mark="<?= ($globalAutoMarkOnOpen && notification_bool_setting($userSettings, 'notif_auto_mark_on_open', '1')) ? '1' : '0' ?>"
    data-ui-style-number="--notification-message-lines:<?= (int) $messageLineLimit ?>"
>
    <section class="notifications-hero" aria-labelledby="notifications-title">
        <div>
            <span class="notifications-kicker"><i class="bi bi-bell"></i> Hesap Merkezi</span>
            <h1 id="notifications-title">Bildirimleriniz</h1>
            <p>Platform duyurularını, hesabınıza özel güncellemeleri ve okunmamış bildirimleri tek bir düzenli ekrandan takip edin.</p>
        </div>
        <div class="notifications-hero-metrics" aria-label="Bildirim özeti">
            <div class="notifications-metric">
                <strong data-notif-total><?= number_format($notificationStats['total']) ?></strong>
                <span>Toplam bildirim</span>
            </div>
            <div class="notifications-metric">
                <strong data-notif-unread><?= number_format($notificationStats['unread']) ?></strong>
                <span>Okunmamış</span>
            </div>
            <div class="notifications-metric">
                <strong data-notif-read><?= number_format($notificationStats['read']) ?></strong>
                <span>Okunmuş</span>
            </div>
        </div>
    </section>

    <?php if ($successMsg): ?>
        <div class="notifications-alert is-success" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="notifications-alert is-error" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <div class="notifications-workspace">
        <aside class="notifications-rail" aria-label="Bildirim menüsü">
            <div class="notifications-rail-head">
                <strong>Bildirim Merkezi</strong>
                <span>Gelen kutusu ve tercihler</span>
            </div>
            <nav class="notifications-nav">
                <a href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=list" class="notifications-nav-link <?= $tab === 'list' ? 'is-active' : '' ?>">
                    <span><i class="bi bi-inbox"></i> Gelen Kutusu</span>
                    <?php if ($notificationStats['unread'] > 0): ?>
                        <span class="notifications-pill" data-sidebar-unread><?= $notificationStats['unread'] > 99 ? '99+' : (int) $notificationStats['unread'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=settings" class="notifications-nav-link <?= $tab === 'settings' ? 'is-active' : '' ?>">
                    <span><i class="bi bi-sliders"></i> Tercihler</span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </nav>
            <div class="notifications-rail-summary" aria-label="Kısa özet">
                <div class="rail-summary-row">
                    <span>Okunmamış oranı</span>
                    <strong><?= $notificationStats['total'] > 0 ? (int) round(($notificationStats['unread'] / $notificationStats['total']) * 100) : 0 ?>%</strong>
                </div>
                <div class="rail-summary-row">
                    <span>Sayfa başına</span>
                    <strong><?= (int) $perPage ?></strong>
                </div>
            </div>
        </aside>

        <section class="notifications-main" aria-label="Bildirim içeriği">
            <?php if ($tab === 'list'): ?>
                <div class="notifications-panel-head">
                    <div class="notifications-panel-title">
                        <h2>Gelen Kutusu</h2>
                        <p>Okunmamışları hızlıca yakalayın, eski duyurulara dönün veya ilgili sayfaya tek tıkla geçin.</p>
                    </div>
                    <?php if ($notificationStats['unread'] > 0): ?>
                        <button type="button" class="notifications-action" data-mark-all-read>
                            <i class="bi bi-check2-all"></i>
                            <span>Tümünü okundu yap</span>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="notifications-toolbar">
                    <div class="notifications-filters" aria-label="Bildirim filtreleri">
                        <a class="notifications-filter <?= $filter === 'all' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=list&filter=all">
                            <span>Tümü</span>
                            <strong><?= (int) $notificationStats['total'] ?></strong>
                        </a>
                        <a class="notifications-filter <?= $filter === 'unread' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=list&filter=unread">
                            <span>Okunmamış</span>
                            <strong data-filter-unread><?= (int) $notificationStats['unread'] ?></strong>
                        </a>
                        <a class="notifications-filter <?= $filter === 'read' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=list&filter=read">
                            <span>Okunmuş</span>
                            <strong data-filter-read><?= (int) $notificationStats['read'] ?></strong>
                        </a>
                    </div>
                    <span class="notifications-count">
                        <?= (int) $totalFilteredNotifications ?> sonuç
                    </span>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="notifications-empty">
                        <div>
                            <span class="notifications-empty-icon"><i class="bi bi-envelope-open"></i></span>
                            <h3>Burada gösterilecek bildirim yok</h3>
                            <?php if (!$notificationCenterEnabled): ?>
                                <p>Bildirim merkezi şu anda yönetici tarafından kapalı.</p>
                            <?php elseif ($emptyStateTipsEnabled): ?>
                                <p><?= $filter === 'unread' ? 'Okunmamış bildiriminiz kalmadı. Yeni bir duyuru geldiğinde burada görünecek.' : 'Henüz hesabınıza ait bir bildirim bulunmuyor. Tercihler sekmesinden görmek istediğiniz bildirim türlerini değiştirebilirsiniz.' ?></p>
                            <?php endif; ?>
                            <div class="notifications-empty-actions">
                                <a href="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=settings"><i class="bi bi-sliders"></i> Tercihleri Düzenle</a>
                                <a href="<?= $baseUri ?>/index.php"><i class="bi bi-grid ui-grid"></i> İçeriklere Git</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="notifications-feed" data-notif-feed data-current-filter="<?= htmlspecialchars($filter) ?>">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                                $typeMeta = notification_type_meta((string) ($notification['type'] ?? 'info'));
                                $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
                                $createdAt = !empty($notification['created_at']) ? strtotime((string) $notification['created_at']) : false;
                                $dateTitle = $createdAt ? date('d.m.Y H:i', $createdAt) : '';
                                $dateShort = $createdAt ? date('d.m.Y', $createdAt) : 'Tarih yok';
                                $link = notification_safe_link((string) ($notification['link'] ?? ''), (string) ($baseUri ?? ''));
                            ?>
                            <article class="notification-item <?= $isUnread ? 'is-unread' : 'is-read' ?>" data-notif-item data-id="<?= (int) $notification['id'] ?>">
                                <span class="notification-icon is-<?= htmlspecialchars($typeMeta['class']) ?>" aria-hidden="true">
                                    <i class="bi <?= htmlspecialchars($typeMeta['icon']) ?>"></i>
                                </span>
                                <div class="notification-body ui-panel__body">
                                    <div class="notification-topline">
                                        <div class="notification-title-group">
                                            <h3 class="notification-title"><?= htmlspecialchars((string) $notification['title']) ?></h3>
                                            <span class="notification-status <?= $isUnread ? 'is-unread' : 'is-read' ?>" data-type-label="<?= htmlspecialchars($typeMeta['label']) ?>">
                                                <i class="bi <?= $isUnread ? 'bi-circle-fill' : 'bi-check2-circle' ?>"></i>
                                                <?= $isUnread ? 'Okunmamış' : 'Okundu' ?> · <?= htmlspecialchars($typeMeta['label']) ?>
                                            </span>
                                            <span class="notification-type-chip is-<?= htmlspecialchars($typeMeta['class']) ?>" title="<?= htmlspecialchars($typeMeta['summary']) ?>">
                                                <i class="bi <?= htmlspecialchars($typeMeta['icon']) ?>"></i>
                                                <?= htmlspecialchars($typeMeta['summary']) ?>
                                            </span>
                                        </div>
                                        <time class="notification-time" datetime="<?= htmlspecialchars((string) ($notification['created_at'] ?? '')) ?>" title="<?= htmlspecialchars($dateTitle) ?>">
                                            <i class="bi bi-clock"></i>
                                            <?= htmlspecialchars($dateShort) ?>
                                        </time>
                                    </div>

                                    <p class="notification-message" data-notif-message><?= nl2br(htmlspecialchars((string) $notification['message'])) ?></p>

                                    <div class="notification-footer ui-panel__foot">
                                        <?php if ($link !== ''): ?>
                                            <a href="<?= htmlspecialchars($link) ?>" class="notification-link" data-notif-open data-id="<?= (int) $notification['id'] ?>">
                                                <span>Görüntüle</span>
                                                <i class="bi bi-arrow-right-short"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="notification-read-more" data-notif-toggle hidden>
                                            <span>Daha fazla göster</span>
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="notifications-pagination" aria-label="Bildirim sayfaları">
                            <?php if ($page > 1): ?>
                                <a href="?tab=list&filter=<?= htmlspecialchars($filter) ?>&page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i> Önceki</a>
                            <?php endif; ?>
                            <span><?= (int) $page ?> / <?= (int) $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?tab=list&filter=<?= htmlspecialchars($filter) ?>&page=<?= $page + 1 ?>">Sonraki <i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'settings'): ?>
                <form method="POST" action="<?= htmlspecialchars($notificationsBaseUrl, ENT_QUOTES, 'UTF-8') ?>?tab=settings" class="notification-settings">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="settings-intro">
                        <div>
                            <h2>Bildirim Tercihleri</h2>
                            <p>Hangi kanallardan haber almak istediğinizi seçin. Kritik hesap ve güvenlik bildirimleri gerektiğinde yine gösterilebilir.</p>
                        </div>
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                    </div>

                    <?php foreach ($notification_preference_groups as $preferenceGroup): ?>
                        <section class="settings-group-panel ui-panel" data-notification-preference-group>
                            <div class="settings-group-head ui-panel__head">
                                <span class="settings-group-icon" aria-hidden="true"><i class="bi <?= htmlspecialchars((string) $preferenceGroup['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <div>
                                    <h3><?= htmlspecialchars((string) $preferenceGroup['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p><?= htmlspecialchars((string) $preferenceGroup['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <label class="settings-group-switch" for="<?= htmlspecialchars((string) $preferenceGroup['input_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="settings-group-switch-text">Grubu aktif tut</span>
                                    <span class="notification-switch">
                                        <input
                                            id="<?= htmlspecialchars((string) $preferenceGroup['input_id'], ENT_QUOTES, 'UTF-8') ?>"
                                            type="checkbox"
                                            name="<?= htmlspecialchars((string) $preferenceGroup['key'], ENT_QUOTES, 'UTF-8') ?>"
                                            value="1"
                                            data-notification-group-toggle
                                            <?= (string) $preferenceGroup['checked'] ?>
                                        >
                                        <span class="notification-slider"></span>
                                    </span>
                                </label>
                            </div>
                            <div class="settings-grid ui-grid">
                                <?php foreach ($preferenceGroup['items'] as $preferenceItem): ?>
                                    <label class="setting-row" for="<?= htmlspecialchars((string) $preferenceItem['input_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="setting-row-icon"><i class="bi <?= htmlspecialchars((string) $preferenceItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                        <span>
                                            <strong><?= htmlspecialchars((string) $preferenceItem['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars((string) $preferenceItem['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <span class="notification-switch">
                                            <input
                                                id="<?= htmlspecialchars((string) $preferenceItem['input_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                type="checkbox"
                                                name="<?= htmlspecialchars((string) $preferenceItem['key'], ENT_QUOTES, 'UTF-8') ?>"
                                                value="1"
                                                data-notification-group-item
                                                <?= (string) $preferenceItem['checked'] ?>
                                            >
                                            <span class="notification-slider"></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <div class="settings-actions">
                        <button type="submit" class="settings-save-btn">
                            <i class="bi bi-check2-circle"></i>
                            <span>Ayarları Kaydet</span>
                        </button>
                        <button type="submit" class="settings-reset-btn" name="preset" value="recommended">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Önerilen Ayarlara Dön</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script src="<?= asset_url('assets/js/notifications-page.js', $baseUri) ?>" defer></script>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>

