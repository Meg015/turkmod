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
$allowedTabs = ['history', 'new', 'settings', 'templates', 'logs'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'history';
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
                ['key' => 'notif_history_per_page', 'type' => 'number', 'label' => 'Geçmiş Sayfa Başına', 'help' => 'Admin geçmiş tablosunda her sayfada gösterilecek kayıt sayısı.', 'default' => '20', 'min' => 10, 'max' => 100],
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
                ['key' => 'notif_email_channel_ready', 'type' => 'bool', 'label' => 'E-posta Kuyruğu Aktif', 'help' => 'E-posta açık şablonlardan notification_email_queue kaydı oluşturur; cron worker bu kayıtları SMTP/mail ayarlarıyla gönderir.', 'default' => '0'],
                ['key' => 'notif_email_queue_max_attempts', 'type' => 'number', 'label' => 'E-posta Deneme Hakkı', 'help' => 'Worker başarısız gönderimleri en fazla bu kadar tekrar dener.', 'default' => '3', 'min' => 1, 'max' => 10],
            ],
        ],
        'automation' => [
            'title' => 'Otomatik Bildirimler',
            'description' => 'Yeni kullanıcılar için otomatik hoş geldin bildirimini ayarlar.',
            'items' => [
                ['key' => 'notif_system_sender', 'type' => 'text', 'label' => 'Sistem Gönderen Adı', 'help' => 'Otomatik bildirimlerde başlıkta kullanılacak kısa ad.', 'default' => 'Sistem'],
                ['key' => 'notif_welcome_enabled', 'type' => 'bool', 'label' => 'Site İçi Hoş Geldin Bildirimi', 'help' => 'Kayıt olan kullanıcıya yalnızca bildirim merkezinde otomatik sistem bildirimi gönderir.', 'default' => '0'],
                ['key' => 'notif_welcome_msg', 'type' => 'textarea', 'label' => 'Site İçi Hoş Geldin Mesajı', 'help' => 'Yeni üyeye bildirim merkezinde gösterilecek metin. E-posta şablonundan ayrıdır.', 'default' => 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.'],
            ],
        ],
    ];
}

function admin_notification_bool(array $settings, string $key, string $default = '1'): bool
{
    return (($settings[$key] ?? $default) === '1');
}

function admin_notification_int(array $settings, string $key, int $default, int $min, int $max): int
{
    $value = (int) ($settings[$key] ?? $default);
    return max($min, min($max, $value));
}

function admin_notification_setting_value(array $settings, array $item): string
{
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

function admin_notification_template_anchor(string $templateKey): string
{
    return 'template-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $templateKey);
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
        'is_active' => $source['is_active'] ?? null,
        'allow_create' => $source['allow_create'] ?? null,
    ];
}

function admin_notification_email_status_meta(?string $status): array
{
    return match ((string) $status) {
        'queued' => ['label' => 'Kuyrukta', 'class' => 'queued', 'icon' => 'bi-hourglass-split'],
        'processing' => ['label' => 'İşleniyor', 'class' => 'processing', 'icon' => 'bi-arrow-repeat'],
        'sent' => ['label' => 'Gönderildi', 'class' => 'sent', 'icon' => 'bi-check2-circle'],
        'failed' => ['label' => 'Hatalı', 'class' => 'failed', 'icon' => 'bi-exclamation-octagon'],
        default => ['label' => 'E-posta yok', 'class' => 'none', 'icon' => 'bi-dash-circle'],
    };
}

function admin_notification_log_filter(string $key, array $allowed, string $default = 'all'): string
{
    $value = trim((string) ($_GET[$key] ?? $default));
    return in_array($value, $allowed, true) ? $value : $default;
}

$adminSettings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$settingsSchema = admin_notification_settings_schema();
$flatSettingsSchema = admin_notification_flat_settings_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: notifications.php?tab=' . $tab);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
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

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $message, $type, $link !== '' ? $link : null]);

            flash('success', 'Bildirim başarıyla gönderildi.');
            header('Location: notifications.php?tab=history');
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->execute([$id]);
                flash('success', 'Bildirim silindi.');
            }
            header('Location: notifications.php?tab=history');
            exit;
        }

        if ($action === 'clear_all') {
            $deletedCount = (int) $pdo->exec("DELETE FROM notifications");
            flash('success', $deletedCount > 0 ? 'Tüm bildirimler silindi.' : 'Silinecek bildirim bulunmuyor.');
            header('Location: notifications.php?tab=history');
            exit;
        }

        if (in_array($action, ['save_template', 'reset_template', 'delete_template', 'send_template_test'], true)) {
            $templateKey = strtolower(trim((string) ($_POST['template_key'] ?? '')));
            if ($templateKey === '') {
                throw new RuntimeException('Şablon anahtarı eksik.');
            }

            if ($action === 'reset_template') {
                if (!notificationTemplateReset($pdo, $templateKey)) {
                    throw new RuntimeException('Şablon varsayılana döndürülemedi.');
                }
                flash('success', 'Bildirim şablonu varsayılana döndürüldü.');
                header('Location: notifications.php?tab=templates#' . admin_notification_template_anchor($templateKey));
                exit;
            }

            if ($action === 'delete_template') {
                if (!notificationTemplateDelete($pdo, $templateKey)) {
                    throw new RuntimeException('Varsayılan şablonlar silinemez.');
                }
                flash('success', 'Bildirim şablonu silindi.');
                header('Location: notifications.php?tab=templates');
                exit;
            }

            $templateInput = admin_notification_template_input($_POST);

            if ($action === 'send_template_test') {
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

                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $currentUserId,
                    $testTitle,
                    $preview['message'],
                    $testType,
                    $preview['link'] !== '' ? $preview['link'] : null,
                ]);

                flash('success', 'Test bildirimi kendi hesabınıza gönderildi.');
                header('Location: notifications.php?tab=templates#' . admin_notification_template_anchor($templateKey));
                exit;
            }

            $saved = notificationTemplateSave($pdo, $templateKey, $templateInput);
            if (!$saved) {
                throw new RuntimeException('Şablon kaydedilemedi.');
            }

            flash('success', 'Bildirim şablonu kaydedildi.');
            header('Location: notifications.php?tab=templates#' . admin_notification_template_anchor($templateKey));
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
    } catch (Throwable $e) {
        flash('error', 'İşlem başarısız: ' . safeErrorMessage($e));
        header('Location: notifications.php?tab=' . $tab);
        exit;
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = admin_notification_int($adminSettings, 'notif_history_per_page', 20, 10, 100);
$offset = ($page - 1) * $perPage;
$previewLength = admin_notification_int($adminSettings, 'notif_history_message_preview', 140, 40, 500);
$maxTitleLength = admin_notification_int($adminSettings, 'notif_max_title_length', 120, 20, 255);
$maxMessageLength = admin_notification_int($adminSettings, 'notif_max_message_length', 800, 80, 5000);
$defaultType = (string) ($adminSettings['notif_default_type'] ?? 'info');
$defaultLink = (string) ($adminSettings['notif_default_link'] ?? '');

$notifications = [];
$notificationLogs = [];
$totalNotifications = 0;
$totalPages = 1;
$totalNotificationLogs = 0;
$logTotalPages = 1;
$notificationStats = ['total' => 0, 'global' => 0, 'direct' => 0, 'unread' => 0];
$notificationLogStats = ['total' => 0, 'read' => 0, 'unread' => 0, 'email_sent' => 0, 'email_failed' => 0];
$notificationTemplates = [];
$composerTemplates = [];
$composerTemplatePayload = [];
$templatePreviewPayloads = ['__new' => notificationTemplateSamplePayload()];
$emailQueueStats = ['total' => 0, 'queued' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0];
$templateLoadError = null;
$logFilters = [
    'read' => admin_notification_log_filter('read', ['all', 'read', 'unread']),
    'email' => admin_notification_log_filter('email', ['all', 'none', 'queued', 'processing', 'sent', 'failed']),
    'target' => admin_notification_log_filter('target', ['all', 'global', 'direct']),
    'q' => trim((string) ($_GET['q'] ?? '')),
];

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
        $emailQueueStats = notificationEmailQueueStats($pdo);

        $logStatsRow = $pdo->query("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN COALESCE(r.read_count, 0) > 0 THEN 1 ELSE 0 END), 0) AS read_count,
                COALESCE(SUM(CASE WHEN COALESCE(r.read_count, 0) = 0 THEN 1 ELSE 0 END), 0) AS unread_count,
                COALESCE(SUM(CASE WHEN q.status = 'sent' THEN 1 ELSE 0 END), 0) AS email_sent_count,
                COALESCE(SUM(CASE WHEN q.status = 'failed' THEN 1 ELSE 0 END), 0) AS email_failed_count
            FROM notifications n
            LEFT JOIN (
                SELECT notification_id, COUNT(*) AS read_count
                FROM notification_reads
                GROUP BY notification_id
            ) r ON r.notification_id = n.id
            LEFT JOIN notification_email_queue q ON q.notification_id = n.id
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $notificationLogStats['total'] = (int) ($logStatsRow['total_count'] ?? 0);
        $notificationLogStats['read'] = (int) ($logStatsRow['read_count'] ?? 0);
        $notificationLogStats['unread'] = (int) ($logStatsRow['unread_count'] ?? 0);
        $notificationLogStats['email_sent'] = (int) ($logStatsRow['email_sent_count'] ?? 0);
        $notificationLogStats['email_failed'] = (int) ($logStatsRow['email_failed_count'] ?? 0);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    try {
        notificationEnsureTemplateSchema($pdo);
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
            $templatePreviewPayloads[(string) $template['template_key']] = $template['sample_payload_array'] ?? notificationTemplateSamplePayload();
        }
    } catch (Throwable $e) {
        $templateLoadError = safeErrorMessage($e, 'Şablon yüklenemedi.');
    }
}

if ($pdo && $tab === 'history') {
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM notifications");
        $totalNotifications = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalNotifications / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("SELECT n.*, u.username AS target_username FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        flash('error', 'Bildirimler yüklenemedi: ' . safeErrorMessage($e));
    }
}

if ($pdo && $tab === 'logs') {
    try {
        notificationEnsureEmailQueueSchema($pdo);
        $where = [];
        $params = [];

        if ($logFilters['target'] === 'global') {
            $where[] = 'n.user_id IS NULL';
        } elseif ($logFilters['target'] === 'direct') {
            $where[] = 'n.user_id IS NOT NULL';
        }

        if ($logFilters['read'] === 'read') {
            $where[] = 'COALESCE(r.read_count, 0) > 0';
        } elseif ($logFilters['read'] === 'unread') {
            $where[] = 'COALESCE(r.read_count, 0) = 0';
        }

        if ($logFilters['email'] === 'none') {
            $where[] = 'q.id IS NULL';
        } elseif ($logFilters['email'] !== 'all') {
            $where[] = 'q.status = ?';
            $params[] = $logFilters['email'];
        }

        if ($logFilters['q'] !== '') {
            $where[] = "(n.title LIKE ? OR n.message LIKE ? OR n.event_key LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR q.recipient_email LIKE ? OR q.subject LIKE ?)";
            $search = '%' . $logFilters['q'] . '%';
            array_push($params, $search, $search, $search, $search, $search, $search, $search);
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $readSubquery = "
            SELECT notification_id, COUNT(*) AS read_count, MAX(read_at) AS last_read_at
            FROM notification_reads
            GROUP BY notification_id
        ";
        $baseFrom = "
            FROM notifications n
            LEFT JOIN users u ON u.id = n.user_id
            LEFT JOIN users actor ON actor.id = n.actor_user_id
            LEFT JOIN ({$readSubquery}) r ON r.notification_id = n.id
            LEFT JOIN notification_email_queue q ON q.notification_id = n.id
            {$whereSql}
        ";

        $countStmt = $pdo->prepare("SELECT COUNT(*) {$baseFrom}");
        $countStmt->execute($params);
        $totalNotificationLogs = (int) $countStmt->fetchColumn();
        $logTotalPages = max(1, (int) ceil($totalNotificationLogs / $perPage));
        $page = min($page, $logTotalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT
                n.*,
                u.username AS target_username,
                u.email AS target_email,
                actor.username AS actor_username,
                COALESCE(r.read_count, 0) AS read_count,
                r.last_read_at,
                q.id AS email_queue_id,
                q.status AS email_status,
                q.recipient_email,
                q.template_key,
                q.subject AS email_subject,
                q.attempts,
                q.max_attempts,
                q.error_message,
                q.available_at,
                q.sent_at,
                q.created_at AS email_created_at
            {$baseFrom}
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT ? OFFSET ?
        ");
        $selectParams = $params;
        $selectParams[] = $perPage;
        $selectParams[] = $offset;
        foreach ($selectParams as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $notificationLogs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        flash('error', 'Bildirim logları yüklenemedi: ' . safeErrorMessage($e));
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
$csrfToken = csrf_token();
?>

<?php if ($successMsg): ?>
<div class="ui-admin-alert notification-flash notification-flash-success ui-alert" role="status">
    <span class="notification-flash-icon"><i class="bi bi-check2-circle"></i></span>
    <span class="notification-flash-copy">
        <strong>İşlem tamamlandı</strong>
        <span><?= htmlspecialchars($successMsg) ?></span>
    </span>
    <button type="button" class="ui-admin-alert-close notification-flash-close" aria-label="Uyarıyı kapat"><i class="bi bi-x-lg"></i></button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="ui-admin-alert notification-flash notification-flash-error ui-alert" role="alert">
    <span class="notification-flash-icon"><i class="bi bi-exclamation-triangle"></i></span>
    <span class="notification-flash-copy">
        <strong>İşlem tamamlanamadı</strong>
        <span><?= htmlspecialchars($errorMsg) ?></span>
    </span>
    <button type="button" class="ui-admin-alert-close notification-flash-close" aria-label="Uyarıyı kapat"><i class="bi bi-x-lg"></i></button>
</div>
<?php endif; ?>
<div class="notifications-page">
    <div class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <h2><i class="bi bi-bell"></i> Bildirim Merkezi</h2>
            <p>Tüm kullanıcılara duyuru yapın, gönderim kurallarını yönetin ve bildirim deneyimini tek merkezden ayarlayın.</p>
        </div>
        <div class="notif-stats" aria-label="Bildirim özeti">
            <div class="notif-stat"><strong><?= (int) $notificationStats['total'] ?></strong><span>Toplam</span></div>
            <div class="notif-stat"><strong><?= (int) $notificationStats['global'] ?></strong><span>Genel yayın</span></div>
            <div class="notif-stat"><strong><?= (int) $notificationStats['direct'] ?></strong><span>Özel hedef</span></div>
            <div class="notif-stat"><strong><?= (int) $notificationStats['unread'] ?></strong><span>Hiç okunmamış</span></div>
        </div>
    </div>

    <div class="notif-tabs">
        <a href="notifications.php?tab=history" class="ui-admin-btn <?= $tab === 'history' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-clock-history"></i> Geçmiş Bildirimler
        </a>
        <a href="notifications.php?tab=new" class="ui-admin-btn <?= $tab === 'new' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-send-plus"></i> Yeni Bildirim Gönder
        </a>
        <a href="notifications.php?tab=settings" class="ui-admin-btn <?= $tab === 'settings' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-sliders"></i> Bildirim Ayarları
        </a>
        <a href="notifications.php?tab=templates" class="ui-admin-btn <?= $tab === 'templates' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-file-earmark-text"></i> Bildirim Şablonları
        </a>
        <a href="notifications.php?tab=logs" class="ui-admin-btn <?= $tab === 'logs' ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
            <i class="bi bi-activity"></i> Bildirim Logları
        </a>
    </div>

    <?php if ($tab === 'new'): ?>
        <div class="notif-card">
            <div class="notif-card-header">
                <div>
                    <h3>Yeni Bildirim Oluştur</h3>
                    <p>Gönderim limitleri ve link güvenliği Bildirim Ayarları sekmesindeki kurallara göre uygulanır.</p>
                </div>
                <?php if (!admin_notification_bool($adminSettings, 'notif_center_enabled', '1')): ?>
                    <span class="notif-badge notif-badge-user"><i class="bi bi-lock"></i> Merkez Kapalı</span>
                <?php endif; ?>
            </div>
            <form method="POST" action="notifications.php?tab=new" class="ui-admin-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">

                <div class="notif-form-grid">
                    <?php if (!empty($composerTemplates)): ?>
                        <div class="notif-form-wide">
                            <label class="ui-admin-form-label">Hazır Şablon</label>
                            <select id="notificationTemplatePicker" class="ui-admin-form-control">
                                <option value="">Şablonsuz gönder</option>
                                <?php foreach ($composerTemplates as $template): ?>
                                    <option value="<?= htmlspecialchars((string) $template['template_key']) ?>"><?= htmlspecialchars((string) $template['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="notif-help">Şablon seçildiğinde başlık, mesaj, tip ve link alanları doldurulur; göndermeden önce düzenleyebilirsiniz.</small>
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

                <div class="notif-form-footer">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary" <?= admin_notification_bool($adminSettings, 'notif_center_enabled', '1') ? '' : 'disabled' ?>>
                        <i class="bi bi-send"></i> Bildirimi Gönder
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'history'): ?>
        <div class="notif-card notif-card-flush">
            <div class="notif-card-header notif-card-header-flush ui-admin-card-header-actions">
                <div>
                    <h3>Gönderilmiş Bildirimler</h3>
                    <p>Mesaj önizleme uzunluğu ve sayfa başına kayıt sayısı ayarlardan yönetilir.</p>
                </div>
                <div class="ui-admin-action-row">
                    <span class="notif-badge notif-badge-global"><?= (int) $totalNotifications ?> kayıt</span>
                    <?php if ($totalNotifications > 0): ?>
                        <form method="POST" action="notifications.php?tab=history" class="ui-admin-inline-form" data-admin-confirm="Tüm bildirim geçmişi kalıcı olarak silinecek. Bu işlem geri alınamaz." data-admin-confirm-title="Tüm geçmiş silinsin mi?" data-admin-confirm-ok="Tümünü Sil" data-admin-confirm-tone="danger">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="clear_all">
                            <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" title="Tümünü Sil">
                                <i class="bi bi-trash"></i> Tümünü Sil
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ui-admin-table-wrapper ui-table-wrap ui-surface">
                <table class="ui-admin-table">
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
                        <tr>
                            <td colspan="5" class="notif-table-empty">
                                <i class="bi bi-inbox"></i>
                                Henüz hiç bildirim gönderilmemiş.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                    $typeMeta = admin_notification_types()[$notification['type']] ?? admin_notification_types()['info'];
                                    $priority = $typeMeta['class'] === 'error' ? 'high' : (in_array($typeMeta['class'], ['warning', 'system'], true) ? 'medium' : 'normal');
                                    $priorityLabel = $priority === 'high' ? 'Yüksek öncelik' : ($priority === 'medium' ? 'Orta öncelik' : 'Normal');
                                ?>
                                <tr class="notif-row-priority-<?= htmlspecialchars($priority) ?>">
                                    <td>#<?= (int) $notification['id'] ?></td>
                                    <td>
                                        <?php if ($notification['user_id']): ?>
                                            <span class="notif-badge notif-badge-user" title="Kullanıcı: <?= htmlspecialchars($notification['target_username'] ?? 'Silinmiş') ?>">Özel #<?= (int) $notification['user_id'] ?></span>
                                        <?php else: ?>
                                            <span class="notif-badge notif-badge-global">Genel Yayın</span>
                                        <?php endif; ?>
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
                                        <form method="POST" action="notifications.php?tab=history" class="ui-admin-inline-form" data-admin-confirm="Bu bildirimi silmek istediğinize emin misiniz? Kullanıcılardan da silinir." data-admin-confirm-title="Bildirim silinsin mi?" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
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
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="notif-pagination-bar">
                <span class="notif-pagination-meta">Sayfa <?= (int) $page ?> / <?= (int) $totalPages ?> (Toplam <?= (int) $totalNotifications ?> bildirim)</span>
                <div class="notif-pagination-actions">
                    <?php if ($page > 1): ?>
                        <a href="?tab=history&page=<?= $page - 1 ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline"><i class="bi bi-chevron-left"></i> Önceki</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?tab=history&page=<?= $page + 1 ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">Sonraki <i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'logs'): ?>
        <?php
            $logQueryBase = [
                'tab' => 'logs',
                'read' => $logFilters['read'],
                'email' => $logFilters['email'],
                'target' => $logFilters['target'],
                'q' => $logFilters['q'],
            ];
        ?>
        <div class="notif-card notif-card-flush">
            <div class="notif-card-header notif-card-header-flush">
                <div>
                    <h3><i class="bi bi-activity"></i> Bildirim Logları</h3>
                    <p>Site içi gönderim, okunma ve e-posta kuyruğu durumlarını tek ekranda izleyin.</p>
                </div>
                <span class="notif-badge notif-badge-global"><?= (int) $totalNotificationLogs ?> log</span>
            </div>

            <div class="notif-filter-wrap">
                <div class="notification-log-summary">
                    <div class="notification-log-stat"><strong><?= (int) $notificationLogStats['total'] ?></strong><span>Toplam kayıt</span></div>
                    <div class="notification-log-stat"><strong><?= (int) $notificationLogStats['read'] ?></strong><span>Okunmuş</span></div>
                    <div class="notification-log-stat"><strong><?= (int) $notificationLogStats['unread'] ?></strong><span>Okunmamış</span></div>
                    <div class="notification-log-stat"><strong><?= (int) $notificationLogStats['email_sent'] ?></strong><span>E-posta gönderildi</span></div>
                    <div class="notification-log-stat"><strong><?= (int) $notificationLogStats['email_failed'] ?></strong><span>E-posta hatalı</span></div>
                </div>

                <form method="GET" action="notifications.php" class="notification-log-filters">
                    <input type="hidden" name="tab" value="logs">
                    <div>
                        <label class="ui-admin-form-label">Okunma Durumu</label>
                        <select name="read" class="ui-admin-form-control">
                            <option value="all" <?= $logFilters['read'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                            <option value="read" <?= $logFilters['read'] === 'read' ? 'selected' : '' ?>>Okunmuş</option>
                            <option value="unread" <?= $logFilters['read'] === 'unread' ? 'selected' : '' ?>>Okunmamış</option>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">E-posta Durumu</label>
                        <select name="email" class="ui-admin-form-control">
                            <option value="all" <?= $logFilters['email'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                            <option value="none" <?= $logFilters['email'] === 'none' ? 'selected' : '' ?>>E-posta yok</option>
                            <option value="queued" <?= $logFilters['email'] === 'queued' ? 'selected' : '' ?>>Kuyrukta</option>
                            <option value="processing" <?= $logFilters['email'] === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
                            <option value="sent" <?= $logFilters['email'] === 'sent' ? 'selected' : '' ?>>Gönderildi</option>
                            <option value="failed" <?= $logFilters['email'] === 'failed' ? 'selected' : '' ?>>Hatalı</option>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">Hedef</label>
                        <select name="target" class="ui-admin-form-control">
                            <option value="all" <?= $logFilters['target'] === 'all' ? 'selected' : '' ?>>Tümü</option>
                            <option value="global" <?= $logFilters['target'] === 'global' ? 'selected' : '' ?>>Genel yayın</option>
                            <option value="direct" <?= $logFilters['target'] === 'direct' ? 'selected' : '' ?>>Kullanıcıya özel</option>
                        </select>
                    </div>
                    <div>
                        <label class="ui-admin-form-label">Arama</label>
                        <input type="search" name="q" class="ui-admin-form-control" value="<?= htmlspecialchars($logFilters['q']) ?>" placeholder="Başlık, kullanıcı, olay...">
                    </div>
                    <div class="notification-log-filter-actions">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-funnel"></i> Filtrele</button>
                        <a href="notifications.php?tab=logs" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>

            <div class="ui-admin-table-wrapper ui-table-wrap ui-surface">
                <table class="ui-admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bildirim</th>
                            <th>Hedef</th>
                            <th>Okunma</th>
                            <th>E-posta</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notificationLogs)): ?>
                            <tr>
                                <td colspan="6" class="notif-table-empty">
                                    <i class="bi bi-journal-x"></i>
                                    Bu filtrelerle eşleşen bildirim logu bulunamadı.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notificationLogs as $log): ?>
                                <?php
                                    $typeMeta = admin_notification_types()[$log['type']] ?? admin_notification_types()['info'];
                                    $emailMeta = admin_notification_email_status_meta($log['email_status'] ?? null);
                                    $priority = ($emailMeta['class'] ?? '') === 'failed' || $typeMeta['class'] === 'error' ? 'high' : (in_array($typeMeta['class'], ['warning', 'system'], true) || in_array(($emailMeta['class'] ?? ''), ['queued', 'processing'], true) ? 'medium' : 'normal');
                                    $priorityLabel = $priority === 'high' ? 'Yüksek öncelik' : ($priority === 'medium' ? 'Orta öncelik' : 'Normal');
                                    $readCount = (int) ($log['read_count'] ?? 0);
                                    $isRead = $readCount > 0;
                                    $createdAt = !empty($log['created_at']) ? date('d.m.Y H:i', strtotime((string) $log['created_at'])) : '-';
                                    $lastReadAt = !empty($log['last_read_at']) ? date('d.m.Y H:i', strtotime((string) $log['last_read_at'])) : '';
                                    $sentAt = !empty($log['sent_at']) ? date('d.m.Y H:i', strtotime((string) $log['sent_at'])) : '';
                                    $availableAt = !empty($log['available_at']) ? date('d.m.Y H:i', strtotime((string) $log['available_at'])) : '';
                                ?>
                                <tr class="notif-row-priority-<?= htmlspecialchars($priority) ?>">
                                    <td>#<?= (int) $log['id'] ?></td>
                                    <td>
                                        <div class="notification-log-title">
                                            <i class="bi <?= htmlspecialchars($typeMeta['icon']) ?> type-<?= htmlspecialchars($typeMeta['class']) ?> notif-type-icon"></i>
                                            <div>
                                                <strong><?= htmlspecialchars((string) $log['title']) ?></strong>
                                                <span class="notif-priority-badge is-<?= htmlspecialchars($priority) ?>"><i class="bi bi-flag-fill"></i> <?= htmlspecialchars($priorityLabel) ?></span>
                                                <div class="history-message"><?= htmlspecialchars(admin_notification_preview((string) $log['message'], $previewLength)) ?></div>
                                                <div class="notification-log-meta">
                                                    <span class="notification-log-chip sent"><i class="bi bi-check2-circle"></i> Site içi gönderildi</span>
                                                    <?php if (!empty($log['event_key'])): ?>
                                                        <span class="notification-log-chip"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars((string) $log['event_key']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($log['template_key'])): ?>
                                                        <span class="notification-log-chip"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars((string) $log['template_key']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="notification-log-target">
                                            <?php if ($log['user_id']): ?>
                                                <span class="notif-badge notif-badge-user">Özel #<?= (int) $log['user_id'] ?></span>
                                                <small><?= htmlspecialchars((string) ($log['target_username'] ?? 'Silinmiş kullanıcı')) ?></small>
                                                <?php if (!empty($log['target_email'])): ?>
                                                    <small><?= htmlspecialchars((string) $log['target_email']) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="notif-badge notif-badge-global">Genel yayın</span>
                                                <small>Tüm kullanıcılar</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="notification-log-chip <?= $isRead ? 'read' : 'unread' ?>">
                                            <i class="bi <?= $isRead ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                                            <?= $isRead ? 'Okunmuş' : 'Okunmamış' ?>
                                        </span>
                                        <div class="notif-help ui-admin-mt-xs">
                                            <?= $isRead ? ((int) $readCount . ' okuma' . ($lastReadAt ? ' · ' . htmlspecialchars($lastReadAt) : '')) : 'Okuma kaydı yok' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="notification-log-email">
                                            <span class="notification-log-chip email-<?= htmlspecialchars($emailMeta['class']) ?>">
                                                <i class="bi <?= htmlspecialchars($emailMeta['icon']) ?>"></i> <?= htmlspecialchars($emailMeta['label']) ?>
                                            </span>
                                            <?php if (!empty($log['email_queue_id'])): ?>
                                                <small>#<?= (int) $log['email_queue_id'] ?> · Deneme <?= (int) ($log['attempts'] ?? 0) ?>/<?= (int) ($log['max_attempts'] ?? 0) ?></small>
                                                <?php if (!empty($log['recipient_email'])): ?>
                                                    <small><?= htmlspecialchars((string) $log['recipient_email']) ?></small>
                                                <?php endif; ?>
                                                <?php if ($sentAt): ?>
                                                    <small>Gönderim: <?= htmlspecialchars($sentAt) ?></small>
                                                <?php elseif ($availableAt): ?>
                                                    <small>Uygun zaman: <?= htmlspecialchars($availableAt) ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($log['error_message'])): ?>
                                                    <div class="notification-log-error"><?= htmlspecialchars(admin_notification_preview((string) $log['error_message'], 120)) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small>Bu bildirim için e-posta kuyruğu oluşturulmamış.</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="notif-date-cell"><?= htmlspecialchars($createdAt) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($logTotalPages > 1): ?>
                <div class="notif-pagination-bar">
                    <span class="notif-pagination-meta">Sayfa <?= (int) $page ?> / <?= (int) $logTotalPages ?> (Toplam <?= (int) $totalNotificationLogs ?> log)</span>
                    <div class="notif-pagination-actions">
                        <?php if ($page > 1): ?>
                            <a href="?<?= htmlspecialchars(http_build_query(array_merge($logQueryBase, ['page' => $page - 1]))) ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline"><i class="bi bi-chevron-left"></i> Önceki</a>
                        <?php endif; ?>
                        <?php if ($page < $logTotalPages): ?>
                            <a href="?<?= htmlspecialchars(http_build_query(array_merge($logQueryBase, ['page' => $page + 1]))) ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">Sonraki <i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'templates'): ?>
        <?php
            $allowedTemplateVariables = notificationTemplateAllowedVariables();
            $newTemplatePreview = notificationTemplatePreview([
                'type' => 'info',
                'title_template' => 'Duyuru: {{topic_title}}',
                'message_template' => '{{site_name}} duyurusu: {{comment_excerpt}}',
                'link_template' => '{{link}}',
                'sample_payload_array' => notificationTemplateSamplePayload(),
            ]);
            $newTypeMeta = admin_notification_types()[$newTemplatePreview['type']] ?? admin_notification_types()['info'];
        ?>
        <div class="notification-template-page">
            <div class="notification-template-toolbar">
                <div>
                    <h3><i class="bi bi-file-earmark-text"></i> Bildirim Şablonları</h3>
                    <p>Otomatik olay bildirimleri ve manuel gönderim ekranında kullanılacak metinleri, kanalları ve önizlemeleri buradan yönetin.</p>
                </div>
                <span class="notif-badge notif-badge-global"><?= (int) count($notificationTemplates) ?> şablon</span>
            </div>

            <?php if ($templateLoadError): ?>
                <div class="ui-admin-alert notification-flash notification-flash-error ui-alert" role="alert">
                    <span class="notification-flash-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="notification-flash-copy">
                        <strong>Şablonlar yüklenemedi</strong>
                        <span><?= htmlspecialchars($templateLoadError) ?></span>
                    </span>
                </div>
            <?php endif; ?>

            <div class="notification-template-grid ui-grid">
                <form method="POST" action="notifications.php?tab=templates" class="notification-template-card is-create ui-card" data-live-template-preview="1" data-template-key="__new">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="allow_create" value="1">

                    <div class="notification-template-head ui-panel__head">
                        <div>
                            <h4>Yeni Özel Şablon</h4>
                            <p>Manuel bildirim gönderiminde hızlı seçim için özel bir şablon oluşturun.</p>
                            <span class="notif-badge notif-badge-user"><i class="bi bi-plus-circle"></i> Özel</span>
                        </div>
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="ui-admin-switch-label">Aktif</span>
                        </label>
                    </div>

                    <div class="notification-template-body ui-panel__body">
                        <div>
                            <label class="ui-admin-form-label">Şablon Anahtarı</label>
                            <input type="text" name="template_key" class="ui-admin-form-control" required pattern="[a-z0-9_]{3,100}" placeholder="ornek_duyuru">
                            <small class="notif-help">Küçük harf, rakam ve alt çizgi kullanın.</small>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Bildirim Tipi</label>
                            <select name="type" class="ui-admin-form-control">
                                <?php foreach (admin_notification_types() as $typeKey => $typeMeta): ?>
                                    <option value="<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeMeta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Şablon Adı</label>
                            <input type="text" name="name" class="ui-admin-form-control" required maxlength="160" placeholder="Haftalık duyuru">
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Link Şablonu</label>
                            <input type="text" name="link_template" class="ui-admin-form-control" maxlength="1024" value="{{link}}" placeholder="{{link}}">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Açıklama</label>
                            <textarea name="description" class="ui-admin-form-control" rows="2" placeholder="Bu şablon ne zaman kullanılır?"></textarea>
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Başlık Şablonu</label>
                            <input type="text" name="title_template" class="ui-admin-form-control" required maxlength="255" value="Duyuru: {{topic_title}}" placeholder="Duyuru: {{topic_title}}">
                        </div>
                        <div class="is-wide">
                            <label class="ui-admin-form-label">Mesaj Şablonu</label>
                            <textarea name="message_template" class="ui-admin-form-control" rows="4" required placeholder="{{site_name}} duyurusu: {{comment_excerpt}}">{{site_name}} duyurusu: {{comment_excerpt}}</textarea>
                        </div>
                        <div class="notification-template-channels">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="in_app_enabled" value="1" checked>
                                <span class="ui-admin-switch-label">Site içi açık</span>
                            </label>
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="email_enabled" value="1">
                                <span class="ui-admin-switch-label">E-posta hazır</span>
                            </label>
                        </div>
                        <div>
                            <label class="ui-admin-form-label">Değişkenler</label>
                            <div class="notification-template-token-list">
                                <?php foreach ($allowedTemplateVariables as $variable => $description): ?>
                                    <span class="notification-template-token" title="<?= htmlspecialchars($description) ?>">{{<?= htmlspecialchars($variable) ?>}}</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="is-wide notification-template-preview">
                            <strong><i data-preview-icon class="bi <?= htmlspecialchars($newTypeMeta['icon']) ?> type-<?= htmlspecialchars($newTypeMeta['class']) ?>"></i> <span data-preview-title><?= htmlspecialchars($newTemplatePreview['title']) ?></span></strong>
                            <p data-preview-message><?= htmlspecialchars($newTemplatePreview['message']) ?></p>
                            <a data-preview-link-wrapper href="<?= htmlspecialchars($newTemplatePreview['link']) ?>" target="_blank" rel="noopener" class="<?= $newTemplatePreview['link'] !== '' ? '' : 'notif-preview-link-hidden' ?>"><i class="bi bi-link-45deg"></i> <span data-preview-link><?= htmlspecialchars($newTemplatePreview['link']) ?></span></a>
                        </div>
                    </div>

                    <div class="notification-template-actions">
                        <span class="notif-help">Kaydedildikten sonra Yeni Bildirim Gönder ekranındaki şablon seçicide görünür.</span>
                        <button type="submit" name="action" value="save_template" class="ui-admin-btn ui-admin-btn-primary">
                            <i class="bi bi-save"></i> Şablon Oluştur
                        </button>
                    </div>
                </form>

                <?php foreach ($notificationTemplates as $template): ?>
                    <?php
                        $templateKey = (string) $template['template_key'];
                        $anchor = admin_notification_template_anchor($templateKey);
                        $preview = notificationTemplatePreview($template);
                        $typeMeta = admin_notification_types()[$preview['type']] ?? admin_notification_types()['info'];
                        $variables = array_values((array) ($template['variables'] ?? []));
                    ?>
                    <form id="<?= htmlspecialchars($anchor) ?>" method="POST" action="notifications.php?tab=templates#<?= htmlspecialchars($anchor) ?>" class="notification-template-card ui-card" data-live-template-preview="1" data-template-key="<?= htmlspecialchars($templateKey) ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="template_key" value="<?= htmlspecialchars($templateKey) ?>">

                        <div class="notification-template-head ui-panel__head">
                            <div>
                                <h4><?= htmlspecialchars((string) $template['name']) ?></h4>
                                <p><?= htmlspecialchars((string) ($template['description'] ?? '')) ?></p>
                                <span class="notif-badge <?= !empty($template['is_default']) ? 'notif-badge-global' : 'notif-badge-user' ?>">
                                    <i class="bi <?= !empty($template['is_default']) ? 'bi-diagram-3' : 'bi-pencil-square' ?>"></i>
                                    <?= htmlspecialchars($templateKey) ?>
                                </span>
                            </div>
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="is_active" value="1" <?= (int) ($template['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Aktif</span>
                            </label>
                        </div>

                        <div class="notification-template-body ui-panel__body">
                            <div>
                                <label class="ui-admin-form-label">Şablon Adı</label>
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
                                <label class="ui-admin-form-label">Başlık Şablonu</label>
                                <input type="text" name="title_template" class="ui-admin-form-control" required maxlength="255" value="<?= htmlspecialchars((string) $template['title_template']) ?>">
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Mesaj Şablonu</label>
                                <textarea name="message_template" class="ui-admin-form-control" rows="4" required><?= htmlspecialchars((string) $template['message_template']) ?></textarea>
                            </div>
                            <div class="is-wide">
                                <label class="ui-admin-form-label">Link Şablonu</label>
                                <input type="text" name="link_template" class="ui-admin-form-control" maxlength="1024" value="<?= htmlspecialchars((string) ($template['link_template'] ?? '')) ?>">
                            </div>
                            <div class="notification-template-channels">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="in_app_enabled" value="1" <?= (int) ($template['in_app_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">Site içi açık</span>
                                </label>
                                <label class="ui-admin-switch">
                                    <input type="checkbox" name="email_enabled" value="1" <?= (int) ($template['email_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                                    <span class="ui-admin-switch-label">E-posta hazır</span>
                                </label>
                            </div>
                            <div>
                                <label class="ui-admin-form-label">Değişkenler</label>
                                <div class="notification-template-token-list">
                                    <?php foreach ($variables as $variable): ?>
                                        <span class="notification-template-token" title="<?= htmlspecialchars($allowedTemplateVariables[$variable] ?? '') ?>">{{<?= htmlspecialchars((string) $variable) ?>}}</span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="is-wide notification-template-preview">
                                <strong><i data-preview-icon class="bi <?= htmlspecialchars($typeMeta['icon']) ?> type-<?= htmlspecialchars($typeMeta['class']) ?>"></i> <span data-preview-title><?= htmlspecialchars($preview['title']) ?></span></strong>
                                <p data-preview-message><?= htmlspecialchars($preview['message']) ?></p>
                                <a data-preview-link-wrapper href="<?= htmlspecialchars($preview['link']) ?>" target="_blank" rel="noopener" class="<?= $preview['link'] !== '' ? '' : 'notif-preview-link-hidden' ?>"><i class="bi bi-link-45deg"></i> <span data-preview-link><?= htmlspecialchars($preview['link']) ?></span></a>
                            </div>
                        </div>

                        <div class="notification-template-actions">
                            <span class="notif-help">
                                <?= !empty($template['is_default']) ? 'Varsayılan olay şablonu' : 'Özel manuel şablon' ?>
                            </span>
                            <div class="notification-template-actions-group">
                                <?php if (!empty($template['is_default'])): ?>
                                    <button type="submit" name="action" value="reset_template" class="ui-admin-btn ui-admin-btn-outline" formnovalidate data-admin-confirm="Bu şablonu varsayılan metinlere döndürmek istiyor musunuz?" data-admin-confirm-title="Şablon sıfırlansın mı?" data-admin-confirm-ok="Sıfırla" data-admin-confirm-tone="warning">
                                        <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="delete_template" class="ui-admin-btn ui-admin-btn-danger" formnovalidate data-admin-confirm="Bu özel şablonu silmek istiyor musunuz?" data-admin-confirm-title="Şablon silinsin mi?" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="send_template_test" class="ui-admin-btn ui-admin-btn-outline" <?= $currentUserId > 0 ? '' : 'disabled' ?>>
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

                <div class="notification-email-queue-summary" aria-label="E-posta kuyruğu özeti">
                    <div class="notification-email-queue-stat"><strong><?= (int) $emailQueueStats['queued'] ?></strong><span>Kuyrukta</span></div>
                    <div class="notification-email-queue-stat"><strong><?= (int) $emailQueueStats['processing'] ?></strong><span>İşleniyor</span></div>
                    <div class="notification-email-queue-stat"><strong><?= (int) $emailQueueStats['sent'] ?></strong><span>Gönderildi</span></div>
                    <div class="notification-email-queue-stat"><strong><?= (int) $emailQueueStats['failed'] ?></strong><span>Hatalı</span></div>
                </div>
                <small class="notif-help notif-cron-help">Cron komutu: <code>php cron/send-notification-email-queue.php --limit=25</code></small>

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
                                                    <input type="checkbox" name="<?= htmlspecialchars($item['key']) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
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

<script type="application/json" id="adminNotificationsPageData"><?= json_encode([
    'composerTemplates' => $composerTemplatePayload,
    'templatePreviewPayloads' => $templatePreviewPayloads,
    'typeMeta' => admin_notification_types(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}' ?></script>
<script src="<?= asset_url('admin/assets/notifications-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
