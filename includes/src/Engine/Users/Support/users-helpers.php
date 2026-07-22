<?php
/**
 * Users Module - is mantigi fonksiyonlari
 */

declare(strict_types=1);

use App\Modules\BanAppeals\Services\BanAppealNotificationService;
use App\Modules\BanAppeals\Services\BanAppealSchemaService;
use App\Modules\BanAppeals\Services\BanAppealService;

function usersDbDriver(PDO $pdo): string
{
    try {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {
        return '';
    }
}

function usersIsSqlite(PDO $pdo): bool
{
    return usersDbDriver($pdo) === 'sqlite';
}

function usersTableExists(PDO $pdo, string $table): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $stmt = $pdo->prepare("PRAGMA table_info(" . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . ")");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersNotificationBoolSetting(array $settings, string $key, string $default = '1', ?string $legacyKey = null): bool
{
    if (array_key_exists($key, $settings)) {
        $value = $settings[$key];
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
    if ($legacyKey !== null && array_key_exists($legacyKey, $settings)) {
        $value = $settings[$legacyKey];
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    return in_array(strtolower(trim($default)), ['1', 'true', 'yes', 'on'], true);
}

function usersAdminRegistrationSiteTemplateKey(): string
{
    return 'registration_admin_notice';
}

function usersAdminRegistrationSiteAllowedVariables(): array
{
    return [
        'site_name' => 'Site adı',
        'username' => 'Yeni kullanıcının adı',
        'email' => 'Yeni kullanıcının e-posta adresi',
        'user_id' => 'Yeni kullanıcı ID değeri',
        'user_status' => 'Kullanıcının kayıt sonrası durumu',
        'approval_status' => 'Yönetici onayı bilgisi',
        'admin_link' => 'Admin panelindeki kullanıcı düzenleme bağlantısı',
        'link' => 'Admin bağlantısı kısayolu',
    ];
}

function usersAdminRegistrationSiteTemplateDefaults(): array
{
    return [
        'template_key' => usersAdminRegistrationSiteTemplateKey(),
        'name' => 'Yeni Kullanıcı Kaydı Admin Bildirimi',
        'description' => 'Yeni üyelik oluştuğunda admin ve yetkili hesapların bildirim merkezine düşer.',
        'type' => 'system',
        'title_template' => 'Yeni kullanıcı kaydı',
        'message_template' => '{{username}} ({{email}}) yeni hesap oluşturdu. Durum: {{user_status}}',
        'link_template' => '{{admin_link}}',
    ];
}

function usersAdminRegistrationSiteTemplate(array $settings): array
{
    $defaults = usersAdminRegistrationSiteTemplateDefaults();
    $fields = [
        'name' => 'notif_admin_registration_site_name',
        'description' => 'notif_admin_registration_site_description',
        'type' => 'notif_admin_registration_site_type',
        'title_template' => 'notif_admin_registration_site_title_template',
        'message_template' => 'notif_admin_registration_site_message_template',
        'link_template' => 'notif_admin_registration_site_link_template',
    ];

    foreach ($fields as $field => $settingKey) {
        if (!array_key_exists($settingKey, $settings)) {
            continue;
        }

        $value = trim((string) $settings[$settingKey]);
        if ($value !== '' || in_array($field, ['description', 'link_template'], true)) {
            $defaults[$field] = $value;
        }
    }

    if (!in_array((string) $defaults['type'], ['info', 'success', 'warning', 'error', 'system'], true)) {
        $defaults['type'] = 'system';
    }

    return $defaults;
}

function usersAdminRegistrationSiteVariables(
    int $newUserId,
    string $username,
    string $email,
    bool $requiresApproval,
    string $adminLink,
    array $settings
): array {
    $siteName = trim((string) ($settings['site_name'] ?? ''));
    $displayUsername = trim($username) !== '' ? trim($username) : 'Bir kullanıcı';

    return [
        'site_name' => $siteName !== '' ? $siteName : 'Sistem',
        'username' => $displayUsername,
        'email' => $email,
        'user_id' => (string) $newUserId,
        'user_status' => $requiresApproval ? 'Yönetici onayı bekliyor' : 'Aktif',
        'approval_status' => $requiresApproval ? 'Yönetici onayı bekliyor' : 'Onay gerekmiyor',
        'admin_link' => $adminLink,
        'link' => $adminLink,
    ];
}

function usersRenderAdminRegistrationSiteTemplate(string $template, array $variables): string
{
    return trim((string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($variables): string {
        if (!array_key_exists($matches[1], usersAdminRegistrationSiteAllowedVariables())) {
            return '';
        }

        $value = $variables[$matches[1]] ?? '';

        return is_scalar($value) || $value === null ? (string) $value : '';
    }, $template));
}

function usersUpdateNotificationDeliveryChannels(PDO $pdo, int $notificationId, array $channels): void
{
    if ($notificationId <= 0 || !usersColumnExists($pdo, 'notifications', 'delivery_channels')) {
        return;
    }

    $channels = array_values(array_unique(array_filter(array_map(
        static fn (mixed $channel): string => trim((string) $channel),
        $channels
    ))));

    try {
        $stmt = $pdo->prepare('UPDATE notifications SET delivery_channels = ? WHERE id = ?');
        $stmt->execute([json_encode($channels, JSON_UNESCAPED_UNICODE), $notificationId]);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersUpdateNotificationDeliveryChannels', 'notification_id' => $notificationId]);
        }
    }
}

function usersCreateWelcomeNotification(PDO $pdo, int $newUserId, array $settings, string $notificationsUrl): bool
{
    if ($newUserId <= 0) {
        return false;
    }
    if (
        !usersNotificationBoolSetting($settings, 'notif_center_enabled', '1')
        || !usersNotificationBoolSetting($settings, 'notif_welcome_enabled', '0')
    ) {
        return false;
    }

    try {
        $senderName = trim((string) ($settings['notif_system_sender'] ?? 'Sistem'));
        $senderName = $senderName !== '' ? $senderName : 'Sistem';
        $welcomeMessage = trim((string) ($settings['notif_welcome_msg'] ?? 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.'));
        $welcomeMessage = $welcomeMessage !== '' ? $welcomeMessage : 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.';
        $welcomeTitle = mb_substr($senderName . ' hoş geldin dedi', 0, 255);
        $notificationColumns = function_exists('notificationEventTableColumns') ? notificationEventTableColumns($pdo) : [];
        $insertColumns = ['user_id', 'title', 'message', 'type', 'link'];
        $insertValues = [$newUserId, $welcomeTitle, $welcomeMessage, 'system', $notificationsUrl];
        if (isset($notificationColumns['delivery_channels'])) {
            $insertColumns[] = 'delivery_channels';
            $insertValues[] = json_encode(['in_app'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($notificationColumns['is_admin_loggable'])) {
            $insertColumns[] = 'is_admin_loggable';
            $insertValues[] = 1;
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $insertColumns);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $notificationStmt = $pdo->prepare('INSERT INTO notifications (' . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})");

        $created = $notificationStmt->execute($insertValues);
        if (function_exists('notificationDeliveryLog')) {
            notificationDeliveryLog($pdo, $created ? 'notification_delivery_created' : 'notification_delivery_failed', [
                'source' => 'user_welcome_notification',
                'status' => $created ? 'created' : 'failed',
                'reason' => $created ? '' : 'insert_failed',
                'event_key' => 'welcome_notification',
                'template_key' => 'welcome_notification',
                'recipient_user_id' => $newUserId,
                'recipient_type' => 'user',
                'notification_id' => $created ? (int) $pdo->lastInsertId() : null,
                'type' => 'system',
                'title' => $welcomeTitle,
                'message' => $welcomeMessage,
                'link' => $notificationsUrl,
                'delivery_channels' => ['in_app'],
            ], $created ? 'info' : 'error');
        }

        return $created;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersCreateWelcomeNotification', 'user_id' => $newUserId]);
        } else {
            error_log('Welcome notification failed: ' . $e->getMessage());
        }
    }

    return false;
}

function usersIndexExists(PDO $pdo, string $table, string $index): bool
{
    try {
        if (usersIsSqlite($pdo)) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $stmt = $pdo->prepare("PRAGMA index_list({$safeTable})");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersGroupsAvailable(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (!empty($GLOBALS['_users_group_schema_ready_' . $key])) {
        return usersTableExists($pdo, 'user_groups')
            && usersTableExists($pdo, 'user_group_members')
            && usersTableExists($pdo, 'user_group_permissions');
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $cache[$key] = usersTableExists($pdo, 'user_groups')
        && usersTableExists($pdo, 'user_group_members')
        && usersTableExists($pdo, 'user_group_permissions');

    return $cache[$key];
}

function usersResetGroupAvailabilityCache(PDO $pdo): void
{
    $GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)] = true;
}

function usersGroupSlug(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('slugify')) {
        $slug = (string) slugify($value);
    } else {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
        $slug = trim($slug, '-');
    }

    return substr($slug, 0, 100);
}

function usersDefaultGroupPermissions(string $slug): array
{
    $catalog = array_keys(usersPermissionCatalog());

    if ($slug === 'admin') {
        return ['*'];
    }

    if ($slug === 'editor') {
        return array_values(array_unique(array_merge([
            'admin.access',
            'dashboard.view',
            'queue.view',
            'users.view',
            'topics.view',
            'topics.create',
            'topics.edit',
            'categories.view',
            'categories.create',
            'categories.edit',
            'comments.view',
            'comments.edit',
            'comments.delete',
            'media.view',
            'media.manage',
            'reports.view',
            'reports.manage',
            'scraper.view',
            'logs.view',
        ], array_filter($catalog, static fn(string $key): bool => str_starts_with($key, 'topics.') || str_starts_with($key, 'comments.')))));
    }

    return [
        'topics.view',
        'topics.create',
        'comments.view',
        'comments.create',
    ];
}

function usersUsernameLengthBounds(?array $settings = null): array
{
    $min = 3;
    $max = 30;

    if (is_array($settings)) {
        $min = max(3, (int) ($settings['register_username_min_length'] ?? $min));
        $max = max($min, min(30, (int) ($settings['register_username_max_length'] ?? $max)));
    }

    return ['min' => $min, 'max' => $max];
}

function usersParseTextList(string $value): array
{
    $items = preg_split('/[\r\n,;]+/', strtolower(trim($value))) ?: [];
    $items = array_values(array_filter(array_map(static function (string $item): string {
        return trim($item);
    }, $items), static function (string $item): bool {
        return $item !== '';
    }));

    return array_values(array_unique($items));
}

function usersNormalizeEmailDomain(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    if (str_contains($value, '@')) {
        $value = substr($value, strrpos($value, '@') + 1);
    }

    $value = ltrim(trim($value), '@.');
    $value = rtrim($value, '.');

    return $value;
}

function usersParseEmailDomainList(string $value): array
{
    $items = usersParseTextList($value);
    $items = array_values(array_filter(array_map(static function (string $item): string {
        return usersNormalizeEmailDomain($item);
    }, $items), static function (string $item): bool {
        return $item !== '';
    }));

    return array_values(array_unique($items));
}

function usersFoldPolicyText(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    $value = strtr($value, [
        'ç' => 'c',
        'ğ' => 'g',
        'ı' => 'i',
        'i̇' => 'i',
        'ö' => 'o',
        'ş' => 's',
        'ü' => 'u',
        'â' => 'a',
        'î' => 'i',
        'û' => 'u',
    ]);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    return $value;
}

function usersNormalizePolicyToken(string $value): string
{
    $value = usersFoldPolicyText($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('slugify')) {
        $value = (string) slugify($value);
    } else {
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
        $value = trim($value, '-');
    }

    $value = preg_replace('/[^a-z0-9]+/i', '', $value) ?: '';

    return strtolower($value);
}

function usersParsePolicyTerms(string $value): array
{
    return array_values(array_filter(array_map(static function (string $item): string {
        return usersNormalizePolicyToken($item);
    }, usersParseTextList($value)), static function (string $item): bool {
        return $item !== '';
    }));
}

function usersPolicyTextMatches(string $haystack, array $needles): bool
{
    $haystack = usersNormalizePolicyToken($haystack);
    if ($haystack === '' || $needles === []) {
        return false;
    }

    foreach ($needles as $needle) {
        $needle = usersNormalizePolicyToken((string) $needle);
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }
    }

    return false;
}

function usersUsernamePattern(?array $settings = null): string
{
    $bounds = usersUsernameLengthBounds($settings);
    return '/^[A-Za-z0-9_-]{' . $bounds['min'] . ',' . $bounds['max'] . '}$/';
}

function usersNormalizeUsername(string $username, int $fallbackUserId = 0): string
{
    $username = trim($username);
    if ($username === '' && $fallbackUserId > 0) {
        $username = 'user' . $fallbackUserId;
    }
    if ($username === '') {
        $username = 'user';
    }

    if (function_exists('slugify')) {
        $username = (string) slugify($username);
    } else {
        $username = strtolower($username);
        $username = preg_replace('/[^a-z0-9]+/i', '-', $username) ?: '';
        $username = trim($username, '-');
    }

    $username = str_replace('-', '_', $username);
    $username = preg_replace('/[^A-Za-z0-9_]/', '', $username) ?: '';
    $username = trim($username, '_');
    $username = strtolower($username);

    if ($username === '' && $fallbackUserId > 0) {
        $username = 'user' . $fallbackUserId;
    } elseif ($username === '') {
        $username = 'user';
    }

    if (strlen($username) < 3) {
        $username = str_pad($username, 3, '0');
    }
    if (strlen($username) > 30) {
        $username = substr($username, 0, 30);
    }

    return $username;
}

function usersValidateUsernameInput(string $username, ?array $settings = null): string
{
    $username = trim($username);
    if ($username === '') {
        return '';
    }

    if ($settings === null && function_exists('validateUsername')) {
        $validated = validateUsername($username);
        if ($validated !== null) {
            return strtolower($validated);
        }
    }

    return preg_match(usersUsernamePattern($settings), $username) === 1
        ? strtolower($username)
        : '';
}

function usersValidateUsernamePolicy(string $username, ?array $settings = null): string
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return '';
    }

    $bounds = usersUsernameLengthBounds($settings);
    $length = strlen($username);
    if ($length < $bounds['min'] || $length > $bounds['max']) {
        return "Kullanici adi {$bounds['min']}-{$bounds['max']} karakter olmali ve sadece harf, rakam, _ veya - icermelidir.";
    }

    if (!is_array($settings)) {
        return '';
    }

    $normalizedUsername = usersNormalizePolicyToken($username);
    $blockedUsernames = usersParsePolicyTerms((string) ($settings['spam_blocked_usernames'] ?? ''));
    if ($blockedUsernames !== [] && in_array($normalizedUsername, $blockedUsernames, true)) {
        return 'Bu kullanici adi kullanilamaz.';
    }

    foreach ([
        'spam_blocked_username_fragments',
        'spam_profanity_words',
        'spam_meaningless_words',
        'spam_meaningless_patterns',
    ] as $settingKey) {
        $terms = usersParsePolicyTerms((string) ($settings[$settingKey] ?? ''));
        if ($terms !== [] && usersPolicyTextMatches($username, $terms)) {
            return 'Bu kullanici adi kullanilamaz.';
        }
    }

    return '';
}

function usersValidateEmailDomainPolicy(string $email, ?array $settings = null): string
{
    if (!is_array($settings)) {
        return '';
    }

    $email = strtolower(trim($email));
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return '';
    }

    $domain = usersNormalizeEmailDomain(substr($email, $atPos + 1));
    if ($domain === '') {
        return '';
    }

    $allowedDomains = usersParseEmailDomainList((string) ($settings['register_allowed_email_domains'] ?? ''));
    if ($allowedDomains !== [] && !in_array($domain, $allowedDomains, true)) {
        return 'Bu e-posta adresi ile kayit yapilamaz.';
    }

    $blockedDomains = usersParseEmailDomainList((string) ($settings['spam_blocked_email_domains'] ?? ''));
    if ($blockedDomains === []) {
        return '';
    }

    return in_array($domain, $blockedDomains, true)
        ? 'Bu e-posta adresi ile kayit yapilamaz.'
        : '';
}

function usersRegistrationRequiresAdminApproval(?array $settings = null): bool
{
    return is_array($settings) && (($settings['registration_requires_admin_approval'] ?? '0') === '1');
}

function usersRegistrationStatusForNewUser(?array $settings = null): string
{
    return usersRegistrationRequiresAdminApproval($settings) ? 'inactive' : 'active';
}

function usersRegistrationPendingMessage(?array $settings = null): string
{
    $message = is_array($settings) ? trim((string) ($settings['registration_pending_message'] ?? '')) : '';
    return $message !== '' ? $message : 'Hesabınız oluşturuldu. Yönetici onayından sonra giriş yapabilirsiniz.';
}

function usersPasswordResetTokenTtlMinutes(?array $settings = null): int
{
    if (!is_array($settings)) {
        return 60;
    }

    return max(15, min(1440, (int) ($settings['password_reset_token_ttl_minutes'] ?? 60)));
}

function usersEmailVerificationResendCooldownRemainingSeconds(array $user, int $cooldownMinutes, ?int $now = null): int
{
    $cooldownMinutes = max(1, min(1440, $cooldownMinutes));
    $sentAt = trim((string) ($user['email_verification_sent_at'] ?? ''));
    if ($sentAt === '') {
        return 0;
    }

    $sentTimestamp = strtotime($sentAt);
    if ($sentTimestamp === false || $sentTimestamp <= 0) {
        return 0;
    }

    $now ??= time();
    $remaining = ($sentTimestamp + ($cooldownMinutes * 60)) - $now;

    return max(0, $remaining);
}

function usersRecentRegistrationSpikeSummary(PDO $pdo, int $windowMinutes = 15, int $limit = 5, ?int $now = null): array
{
    $windowMinutes = max(5, min(1440, $windowMinutes));
    $limit = max(1, min(20, $limit));
    $now ??= time();

    $summary = [
        'window_minutes' => $windowMinutes,
        'since' => date('Y-m-d H:i:s', $now - ($windowMinutes * 60)),
        'total' => 0,
        'distinct_ips' => 0,
        'top_ip' => '',
        'top_ip_count' => 0,
        'rows' => [],
    ];

    if (!usersTableExists($pdo, 'user_activity_events')) {
        return $summary;
    }

    try {
        $countStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT COALESCE(NULLIF(ip_address, ''), 'unknown')) AS distinct_ips
            FROM user_activity_events
            WHERE event_type = 'user_registered'
              AND created_at >= :since
        ");
        $countStmt->execute(['since' => $summary['since']]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['total'] = (int) ($countRow['total'] ?? 0);
        $summary['distinct_ips'] = (int) ($countRow['distinct_ips'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(ip_address, ''), 'unknown') AS ip_address,
                COUNT(*) AS total,
                MIN(created_at) AS first_seen,
                MAX(created_at) AS last_seen
            FROM user_activity_events
            WHERE event_type = 'user_registered'
              AND created_at >= :since
            GROUP BY COALESCE(NULLIF(ip_address, ''), 'unknown')
            ORDER BY total DESC, last_seen DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':since', $summary['since'], PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary['rows'] = array_map(static function (array $row): array {
            return [
                'ip_address' => (string) ($row['ip_address'] ?? 'unknown'),
                'total' => (int) ($row['total'] ?? 0),
                'first_seen' => (string) ($row['first_seen'] ?? ''),
                'last_seen' => (string) ($row['last_seen'] ?? ''),
            ];
        }, $rows);

        if ($summary['rows'] !== []) {
            $summary['top_ip'] = (string) ($summary['rows'][0]['ip_address'] ?? '');
            $summary['top_ip_count'] = (int) ($summary['rows'][0]['total'] ?? 0);
        }
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersRecentRegistrationSpikeSummary']);
        }
    }

    return $summary;
}

function usersSuspiciousRegistrationAlertCooldownActive(PDO $pdo, int $cooldownMinutes, ?int $now = null): bool
{
    $cooldownMinutes = max(1, min(1440, $cooldownMinutes));
    if (!usersTableExists($pdo, 'application_logs')) {
        return false;
    }

    $now ??= time();
    $since = date('Y-m-d H:i:s', $now - ($cooldownMinutes * 60));

    try {
        $stmt = $pdo->prepare("
            SELECT created_at
            FROM application_logs
            WHERE channel = 'security'
              AND message = 'registration_suspicious_alert'
              AND created_at >= :since
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute(['since' => $since]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function usersNotifyAdminsOnSuspiciousRegistrations(PDO $pdo, ?array $settings = null, ?int $now = null): int
{
    if ($pdo === null) {
        return 0;
    }

    $settings ??= function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];
    if (!usersNotificationBoolSetting($settings, 'registration_suspicious_alert_enabled', '1')) {
        return 0;
    }

    $windowMinutes = max(5, min(1440, (int) ($settings['registration_suspicious_window_minutes'] ?? 15)));
    $threshold = max(2, min(100, (int) ($settings['registration_suspicious_ip_threshold'] ?? 3)));
    $cooldownMinutes = max(5, min(1440, (int) ($settings['registration_suspicious_cooldown_minutes'] ?? 60)));

    $summary = usersRecentRegistrationSpikeSummary($pdo, $windowMinutes, 5, $now);
    if ((int) ($summary['top_ip_count'] ?? 0) < $threshold) {
        return 0;
    }

    if (usersSuspiciousRegistrationAlertCooldownActive($pdo, $cooldownMinutes, $now)) {
        return 0;
    }

    $adminIds = usersGetAdminRecipientIds($pdo);
    if ($adminIds === []) {
        return 0;
    }

    $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $adminLink = $baseUri !== '' ? $baseUri . '/admin/users.php' : '/admin/users.php';
    $siteName = trim((string) ($settings['site_name'] ?? ''));
    if ($siteName === '') {
        $siteName = 'Sistem';
    }

    $title = 'Şüpheli kayıt yoğunluğu';
    $topIp = (string) ($summary['top_ip'] ?? '');
    if ($topIp === '') {
        $topIp = 'bilinmiyor';
    }
    $message = 'Son ' . $windowMinutes . ' dakikada ' . (int) ($summary['total'] ?? 0) . ' kayıt görüldü. ';
    $message .= 'En yoğun IP ' . $topIp . ' üzerinden ' . (int) ($summary['top_ip_count'] ?? 0) . ' kayıt geldi.';
    if (!empty($summary['rows'])) {
        $topLines = [];
        foreach (array_slice((array) $summary['rows'], 0, 3) as $row) {
            $rowIp = (string) ($row['ip_address'] ?? '');
            $rowTotal = (int) ($row['total'] ?? 0);
            if ($rowIp === '') {
                continue;
            }
            $topLines[] = $rowIp . ' (' . $rowTotal . ')';
        }
        if ($topLines !== []) {
            $message .= ' İlk IP sıralaması: ' . implode(', ', $topLines) . '.';
        }
    }

    $columns = function_exists('notificationEventTableColumns')
        ? notificationEventTableColumns($pdo)
        : [];
    $hasColumn = static function (array $columns, string $column): bool {
        return isset($columns[$column]);
    };
    $startedTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTx = true;
    }

    $inserted = 0;
    $insertedRows = [];
    try {
        $baseColumns = ['user_id', 'title', 'message', 'type', 'link'];
        if ($hasColumn($columns, 'event_key')) {
            $baseColumns[] = 'event_key';
        }
        if ($hasColumn($columns, 'entity_type')) {
            $baseColumns[] = 'entity_type';
        }
        if ($hasColumn($columns, 'entity_id')) {
            $baseColumns[] = 'entity_id';
        }
        if ($hasColumn($columns, 'actor_user_id')) {
            $baseColumns[] = 'actor_user_id';
        }
        if ($hasColumn($columns, 'dedupe_key')) {
            $baseColumns[] = 'dedupe_key';
        }
        if ($hasColumn($columns, 'delivery_channels')) {
            $baseColumns[] = 'delivery_channels';
        }
        if ($hasColumn($columns, 'is_admin_loggable')) {
            $baseColumns[] = 'is_admin_loggable';
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $baseColumns);
        $placeholders = array_fill(0, count($baseColumns), '?');
        $sql = 'INSERT INTO notifications (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);

        foreach ($adminIds as $adminId) {
            $adminId = (int) $adminId;
            if ($adminId <= 0) {
                continue;
            }

            $dedupeKey = 'registration_suspicious_alert:' . $adminId . ':' . sha1((string) ($summary['since'] ?? '') . '|' . $topIp . '|' . (int) ($summary['top_ip_count'] ?? 0));
            $params = [
                $adminId,
                mb_substr($title, 0, 255),
                $message,
                'system',
                $adminLink,
            ];
            if ($hasColumn($columns, 'event_key')) {
                $params[] = 'registration_suspicious_alert';
            }
            if ($hasColumn($columns, 'entity_type')) {
                $params[] = 'security';
            }
            if ($hasColumn($columns, 'entity_id')) {
                $params[] = null;
            }
            if ($hasColumn($columns, 'actor_user_id')) {
                $params[] = null;
            }
            if ($hasColumn($columns, 'dedupe_key')) {
                $params[] = $dedupeKey;
            }
            if ($hasColumn($columns, 'delivery_channels')) {
                $params[] = json_encode(['in_app'], JSON_UNESCAPED_UNICODE);
            }
            if ($hasColumn($columns, 'is_admin_loggable')) {
                $params[] = 1;
            }

            $stmt->execute($params);
            $notificationId = (int) $pdo->lastInsertId();
            $insertedRows[] = [
                'notification_id' => $notificationId,
                'user_id' => $adminId,
            ];
            if (function_exists('notificationDeliveryLog')) {
                notificationDeliveryLog($pdo, 'notification_delivery_created', [
                    'source' => 'registration_suspicious_alert',
                    'status' => 'created',
                    'event_key' => 'registration_suspicious_alert',
                    'template_key' => 'registration_suspicious_alert',
                    'recipient_user_id' => $adminId,
                    'recipient_type' => 'admin',
                    'entity_type' => 'security',
                    'dedupe_key' => $dedupeKey,
                    'notification_id' => $notificationId,
                    'type' => 'system',
                    'delivery_channels' => ['in_app'],
                    'title' => $title,
                    'message' => $message,
                    'link' => $adminLink,
                ]);
            }
            $inserted++;
        }

        if ($startedTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersNotifyAdminsOnSuspiciousRegistrations']);
        }
        return 0;
    }

    if (function_exists('notificationQueueEmail') && usersNotificationBoolSetting($settings, 'notif_email_channel_ready', '0')) {
        $adminEmailService = function_exists('adminEmailService') ? adminEmailService($pdo) : null;
        $adminEmailTemplate = $adminEmailService
            ? $adminEmailService->template('registration_suspicious_alert', $settings)
            : [];
        $adminEmailEnabled = $adminEmailTemplate === []
            || usersNotificationBoolSetting(['enabled' => $adminEmailTemplate['enabled'] ?? '1'], 'enabled', '1');

        foreach ($insertedRows as $row) {
            $adminId = (int) ($row['user_id'] ?? 0);
            $notificationId = (int) ($row['notification_id'] ?? 0);
            if ($adminId <= 0 || $notificationId <= 0 || !$adminEmailEnabled) {
                continue;
            }

            if ($adminEmailService && $adminEmailTemplate !== []) {
                $variables = $adminEmailService->suspiciousRegistrationVariables($summary, $windowMinutes, $threshold, $adminLink, $settings);
                $subject = $adminEmailService->render((string) ($adminEmailTemplate['subject'] ?? ''), $variables);
                $body = $adminEmailService->render((string) ($adminEmailTemplate['body'] ?? ''), $variables);
                $actionLabel = $adminEmailService->render((string) ($adminEmailTemplate['action_label'] ?? 'Kayıtları İncele'), $variables);
            } else {
                $subject = $siteName . ' - ' . $title;
                $body = function_exists('appMailPlainTextHtml')
                    ? appMailPlainTextHtml($message)
                    : nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
                $actionLabel = 'Kayıtları İncele';
            }

            try {
                $queued = notificationQueueEmail(
                    $pdo,
                    $notificationId,
                    $adminId,
                    'registration_suspicious_alert',
                    $subject,
                    $body,
                    $adminLink,
                    [
                        'source' => 'registration_suspicious_alert',
                        'event_key' => 'registration_suspicious_alert',
                        'recipient_type' => 'admin',
                        'eyebrow' => 'Yönetim bildirimi',
                        'mail_title' => $title,
                        'action_label' => $actionLabel,
                        'footer_note' => 'Bu e-posta güvenlik izleme sistemi tarafından otomatik gönderilmiştir.',
                        'window_minutes' => $windowMinutes,
                        'threshold' => $threshold,
                        'summary' => $summary,
                    ],
                    max(1, min(10, (int) ($settings['notif_email_queue_max_attempts'] ?? 3)))
                );
                usersUpdateNotificationDeliveryChannels(
                    $pdo,
                    $notificationId,
                    ['in_app', $queued ? 'email_queue' : 'email_queue_failed']
                );
            } catch (Throwable $emailQueueError) {
                usersUpdateNotificationDeliveryChannels($pdo, $notificationId, ['in_app', 'email_queue_failed']);
                if (function_exists('appLogException')) {
                    appLogException($emailQueueError, ['source' => 'usersNotifyAdminsOnSuspiciousRegistrations.email_queue', 'admin_user_id' => $adminId]);
                }
            }
        }
    }

    if (function_exists('appLog')) {
        appLog($pdo, 'warning', 'security', 'registration_suspicious_alert', [
            'window_minutes' => $windowMinutes,
            'threshold' => $threshold,
            'cooldown_minutes' => $cooldownMinutes,
            'summary' => $summary,
            'inserted_notifications' => $inserted,
        ]);
    }

    return $inserted;
}

function usersVerificationReminderCandidates(PDO $pdo, ?array $settings = null, int $limit = 50): array
{
    $limit = max(1, min(500, $limit));
    $settings ??= function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];

    if (!usersNotificationBoolSetting($settings, 'account_email_verification_enabled', '0')) {
        return [];
    }
    if (!usersNotificationBoolSetting($settings, 'account_email_verification_required', '0')) {
        return [];
    }
    if (!usersTableExists($pdo, 'users')) {
        return [];
    }

    $afterMinutes = max(60, min(10080, (int) ($settings['account_email_verification_reminder_after_minutes'] ?? 1440)));
    $threshold = date('Y-m-d H:i:s', time() - ($afterMinutes * 60));

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, email_verified_at, email_verification_sent_at
            FROM users
            WHERE email_verified_at IS NULL
              AND email_verification_sent_at IS NOT NULL
              AND email_verification_sent_at <= :threshold
            ORDER BY email_verification_sent_at ASC, id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersVerificationReminderCandidates']);
        }
        return [];
    }
}

function usersGetAdminRecipientIds(PDO $pdo): array
{
    if (function_exists('usersEnsureGroupSchema')) {
        try {
            usersEnsureGroupSchema($pdo);
        } catch (Throwable $e) {
            // Schema zaten hazır değilse aşağıdaki sorgu yine de çalışabilir.
        }
    }

    try {
        $sql = "SELECT DISTINCT u.id
                FROM users u
                INNER JOIN user_group_members m ON m.user_id = u.id
                INNER JOIN user_groups g ON g.id = m.group_id
                LEFT JOIN user_group_permissions p
                    ON p.group_id = g.id
                   AND p.permission_value = 1
                   AND p.permission_key IN ('*', 'admin.access')
                WHERE g.is_active = 1
                  AND (g.slug = 'admin' OR p.permission_key IS NOT NULL)";
        if (usersColumnExists($pdo, 'users', 'status')) {
            $sql .= " AND u.status = 'active'";
        }
        if (usersColumnExists($pdo, 'users', 'deleted_at')) {
            $sql .= ' AND u.deleted_at IS NULL';
        }
        $sql .= ' ORDER BY u.id ASC';

        $stmt = $pdo->query($sql);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $id) {
            $userId = (int) $id;
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersGetAdminRecipientIds']);
        }
        return [];
    }
}

function usersNotifyAdminsOnRegistration(PDO $pdo, int $newUserId, string $username, string $email, bool $requiresApproval = false): int
{
    if ($newUserId <= 0) {
        return 0;
    }

    $settings = function_exists('getAdminSettings') ? (array) getAdminSettings($pdo) : [];
    $siteNotificationEnabled = usersNotificationBoolSetting($settings, 'notif_admin_registration_site_enabled', '1', 'notif_admin_registration_enabled');
    $emailNotificationEnabled = usersNotificationBoolSetting($settings, 'notif_admin_registration_email_enabled', '1', 'notif_admin_registration_enabled')
        && usersNotificationBoolSetting($settings, 'notif_email_channel_ready', '0')
        && function_exists('notificationQueueEmail');

    $adminEmailService = $emailNotificationEnabled && function_exists('adminEmailService') ? adminEmailService($pdo) : null;
    $adminEmailTemplate = $adminEmailService
        ? $adminEmailService->template('registration_admin_notice', $settings)
        : [];
    $emailNotificationEnabled = $emailNotificationEnabled
        && (
            $adminEmailTemplate === []
            || usersNotificationBoolSetting(['enabled' => $adminEmailTemplate['enabled'] ?? '1'], 'enabled', '1')
        );

    if (!$siteNotificationEnabled && !$emailNotificationEnabled) {
        return 0;
    }

    $adminIds = usersGetAdminRecipientIds($pdo);
    if ($adminIds === []) {
        return 0;
    }

    $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
    $adminLink = $baseUri !== '' ? $baseUri . '/admin/user-edit.php?id=' . $newUserId : '/admin/user-edit.php?id=' . $newUserId;
    $siteName = trim((string) ($settings['site_name'] ?? ''));
    if ($siteName === '') {
        $siteName = 'Sistem';
    }
    $siteTemplate = usersAdminRegistrationSiteTemplate($settings);
    $siteVariables = usersAdminRegistrationSiteVariables($newUserId, $username, $email, $requiresApproval, $adminLink, $settings);
    $siteDefaults = usersAdminRegistrationSiteTemplateDefaults();
    $title = usersRenderAdminRegistrationSiteTemplate((string) ($siteTemplate['title_template'] ?? ''), $siteVariables);
    if ($title === '') {
        $title = (string) $siteDefaults['title_template'];
    }
    $message = usersRenderAdminRegistrationSiteTemplate((string) ($siteTemplate['message_template'] ?? ''), $siteVariables);
    if ($message === '') {
        $message = usersRenderAdminRegistrationSiteTemplate((string) $siteDefaults['message_template'], $siteVariables);
    }
    $link = usersRenderAdminRegistrationSiteTemplate((string) ($siteTemplate['link_template'] ?? ''), $siteVariables);
    $type = (string) ($siteTemplate['type'] ?? 'system');
    if (!in_array($type, ['info', 'success', 'warning', 'error', 'system'], true)) {
        $type = 'system';
    }

    $columns = function_exists('notificationEventTableColumns')
        ? notificationEventTableColumns($pdo)
        : [];
    $hasColumn = static function (array $columns, string $column): bool {
        return isset($columns[$column]);
    };
    if (!$siteNotificationEnabled && $emailNotificationEnabled && !$hasColumn($columns, 'delivery_channels')) {
        if (function_exists('appLogException')) {
            appLogException(new RuntimeException('Email-only admin registration notification requires notifications.delivery_channels.'), [
                'source' => 'usersNotifyAdminsOnRegistration',
                'user_id' => $newUserId,
            ]);
        }

        return 0;
    }

    $startedTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTx = true;
    }

    $inserted = 0;
    $insertedRows = [];
    try {
        $baseColumns = ['user_id', 'title', 'message', 'type', 'link'];
        if ($hasColumn($columns, 'event_key')) {
            $baseColumns[] = 'event_key';
        }
        if ($hasColumn($columns, 'entity_type')) {
            $baseColumns[] = 'entity_type';
        }
        if ($hasColumn($columns, 'entity_id')) {
            $baseColumns[] = 'entity_id';
        }
        if ($hasColumn($columns, 'actor_user_id')) {
            $baseColumns[] = 'actor_user_id';
        }
        if ($hasColumn($columns, 'dedupe_key')) {
            $baseColumns[] = 'dedupe_key';
        }
        if ($hasColumn($columns, 'delivery_channels')) {
            $baseColumns[] = 'delivery_channels';
        }
        if ($hasColumn($columns, 'is_admin_loggable')) {
            $baseColumns[] = 'is_admin_loggable';
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $baseColumns);
        $placeholders = array_fill(0, count($baseColumns), '?');
        $sql = 'INSERT INTO notifications (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);

        foreach ($adminIds as $adminId) {
            $dedupeKey = 'user_registered_admin:' . $adminId . ':' . $newUserId;
            $deliveryChannels = [];
            if ($siteNotificationEnabled) {
                $deliveryChannels[] = 'in_app';
            }
            if ($emailNotificationEnabled) {
                $deliveryChannels[] = 'email_queue_pending';
            }
            $params = [
                $adminId,
                mb_substr($title, 0, 255),
                $message,
                $type,
                $link !== '' ? $link : null,
            ];
            if ($hasColumn($columns, 'event_key')) {
                $params[] = null;
            }
            if ($hasColumn($columns, 'entity_type')) {
                $params[] = 'user';
            }
            if ($hasColumn($columns, 'entity_id')) {
                $params[] = $newUserId;
            }
            if ($hasColumn($columns, 'actor_user_id')) {
                $params[] = $newUserId;
            }
            if ($hasColumn($columns, 'dedupe_key')) {
                $params[] = $dedupeKey;
            }
            if ($hasColumn($columns, 'delivery_channels')) {
                $params[] = json_encode($deliveryChannels, JSON_UNESCAPED_UNICODE);
            }
            if ($hasColumn($columns, 'is_admin_loggable')) {
                $params[] = 1;
            }

            $stmt->execute($params);
            $insertedRows[] = [
                'notification_id' => (int) $pdo->lastInsertId(),
                'user_id' => $adminId,
            ];
            if (function_exists('notificationDeliveryLog')) {
                notificationDeliveryLog($pdo, 'notification_delivery_created', [
                    'source' => 'user_registration_admin_notice',
                    'status' => 'created',
                    'event_key' => 'registration_admin_notice',
                    'template_key' => 'registration_admin_notice',
                    'recipient_user_id' => $adminId,
                    'recipient_type' => 'admin',
                    'actor_user_id' => $newUserId,
                    'entity_type' => 'user',
                    'entity_id' => $newUserId,
                    'dedupe_key' => $dedupeKey,
                    'notification_id' => (int) $pdo->lastInsertId(),
                    'type' => $type,
                    'delivery_channels' => $deliveryChannels,
                    'title' => $title,
                    'message' => $message,
                    'link' => $link,
                ]);
            }
            $inserted++;
        }

        if ($startedTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersNotifyAdminsOnRegistration', 'user_id' => $newUserId]);
        }
        return 0;
    }

    if ($emailNotificationEnabled) {
        foreach ($insertedRows as $row) {
            $adminId = (int) ($row['user_id'] ?? 0);
            $notificationId = (int) ($row['notification_id'] ?? 0);
            if ($adminId <= 0 || $notificationId <= 0) {
                continue;
            }

            if ($adminEmailService && $adminEmailTemplate !== []) {
                $variables = $adminEmailService->registrationVariables($newUserId, $username, $email, $requiresApproval, $adminLink, $settings);
                $subject = $adminEmailService->render((string) ($adminEmailTemplate['subject'] ?? ''), $variables);
                $body = $adminEmailService->render((string) ($adminEmailTemplate['body'] ?? ''), $variables);
                $actionLabel = $adminEmailService->render((string) ($adminEmailTemplate['action_label'] ?? 'Kullanıcıyı İncele'), $variables);
            } else {
                $subject = $siteName . ' - Yeni kullanıcı kaydı';
                $displayUsername = trim($username) !== '' ? trim($username) : 'Bir kullanıcı';
                $body = (function_exists('appMailPlainTextHtml') ? appMailPlainTextHtml($displayUsername . ' yeni hesap oluşturdu.') : '<p>' . htmlspecialchars($displayUsername . ' yeni hesap oluşturdu.', ENT_QUOTES, 'UTF-8') . '</p>')
                    . (function_exists('appMailDetailTableHtml') ? appMailDetailTableHtml([
                        'Kullanıcı adı' => $displayUsername,
                        'E-posta' => $email,
                        'Kullanıcı ID' => $newUserId,
                        'Durum' => $requiresApproval ? 'Yönetici onayı bekliyor' : 'Aktif',
                    ]) : '');
                $actionLabel = 'Kullanıcıyı İncele';
            }

            try {
                $queued = notificationQueueEmail(
                    $pdo,
                    $notificationId,
                    $adminId,
                    'registration_admin_notice',
                    $subject,
                    $body,
                    $adminLink,
                    [
                        'source' => 'user_registration',
                        'event_key' => 'registration_admin_notice',
                        'recipient_type' => 'admin',
                        'actor_user_id' => $newUserId,
                        'eyebrow' => 'Yönetim bildirimi',
                        'mail_title' => 'Yeni kullanıcı kaydı',
                        'action_label' => $actionLabel,
                        'footer_note' => 'Bu e-posta kullanıcı kayıt sistemi tarafından otomatik gönderilmiştir.',
                        'user_id' => $newUserId,
                        'username' => $username,
                        'email' => $email,
                    ],
                    max(1, min(10, (int) ($settings['notif_email_queue_max_attempts'] ?? 3)))
                );
                usersUpdateNotificationDeliveryChannels(
                    $pdo,
                    $notificationId,
                    array_merge(
                        $siteNotificationEnabled ? ['in_app'] : [],
                        [$queued ? 'email_queue' : 'email_queue_failed']
                    )
                );
            } catch (Throwable $emailQueueError) {
                usersUpdateNotificationDeliveryChannels(
                    $pdo,
                    $notificationId,
                    array_merge(
                        $siteNotificationEnabled ? ['in_app'] : [],
                        ['email_queue_failed']
                    )
                );
                if (function_exists('appLogException')) {
                    appLogException($emailQueueError, ['source' => 'usersNotifyAdminsOnRegistration.email_queue', 'user_id' => $newUserId, 'admin_user_id' => $adminId]);
                }
            }
        }
    }

    return $inserted;
}

function usersResolveUniqueUsername(PDO $pdo, string $seed, int $excludeUserId = 0): string
{
    $candidate = usersNormalizeUsername($seed, $excludeUserId);
    $base = $candidate;
    $suffix = 2;
    $column = 'username';

    while (true) {
        $sql = "SELECT id FROM users WHERE {$column} = :candidate";
        if ($excludeUserId > 0) {
            $sql .= " AND id <> :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $params = ['candidate' => $candidate];
        if ($excludeUserId > 0) {
            $params['exclude_id'] = $excludeUserId;
        }
        $stmt->execute($params);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $candidate;
        }

        $suffixText = (string) $suffix;
        $trimmedBase = substr($base, 0, max(1, 30 - strlen($suffixText)));
        $candidate = $trimmedBase . $suffixText;
        $suffix++;
    }
}

function usersBackfillUsernameColumn(PDO $pdo): void
{
    if (!usersColumnExists($pdo, 'users', 'username')) {
        return;
    }

    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return;
    }

    $used = [];
    $updates = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $rawUsername = trim((string) ($row['username'] ?? ''));
        $base = $rawUsername;
        if ($base === '' || preg_match(usersUsernamePattern(), $base) !== 1) {
            $base = usersNormalizeUsername($rawUsername, $userId);
        } else {
            $base = strtolower($base);
        }

        $candidate = $base;
        $suffix = 2;
        while (isset($used[$candidate])) {
            $suffixText = (string) $suffix;
            $trimmedBase = substr($base, 0, max(1, 30 - strlen($suffixText)));
            $candidate = $trimmedBase . $suffixText;
            $suffix++;
        }
        $used[$candidate] = true;

        if ($rawUsername !== $candidate) {
            $updates[] = [
                'id' => $userId,
                'username' => $candidate,
            ];
        }
    }

    if ($updates === []) {
        return;
    }

    $startedTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTx = true;
    }

    try {
        $updateStmt = $pdo->prepare("UPDATE users SET username = :username WHERE id = :id");
        foreach ($updates as $update) {
            $updateStmt->execute($update);
        }
        if ($startedTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersBackfillUsernameColumn']);
        }
    }
}

function usersEnsureUsernameSchema(PDO $pdo): void
{
    if (!usersTableExists($pdo, 'users') || !usersColumnExists($pdo, 'users', 'username')) {
        throw new RuntimeException('Missing users.username; run Admin Panel > Database Synchronization.');
    }
}

function usersEnsureGroupSchema(PDO $pdo): void
{
    static $done = [];
    $key = spl_object_id($pdo);
    if (!empty($done[$key])) {
        return;
    }
    $requiredTables = [
        'user_groups', 'user_group_permissions', 'user_group_members',
        'user_group_logs', 'user_group_permission_overrides',
    ];
    $missing = [];
    foreach ($requiredTables as $table) {
        if (!usersTableExists($pdo, $table)) {
            $missing[] = $table;
        }
    }
    foreach (['color', 'priority', 'parent_group_id'] as $column) {
        if (!usersColumnExists($pdo, 'user_groups', $column)) {
            $missing[] = 'user_groups.' . $column;
        }
    }
    if ($missing !== []) {
        throw new RuntimeException('Missing user group schema: ' . implode(', ', $missing) . '; run Database Synchronization.');
    }
    $done[$key] = true;
    usersResetGroupAvailabilityCache($pdo);
}

function usersGroupIdBySlug(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare("SELECT id FROM user_groups WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function usersDefaultGroupId(PDO $pdo): int
{
    try {
        $id = (int) ($pdo->query("SELECT id FROM user_groups WHERE is_default = 1 AND is_active = 1 ORDER BY display_order, id LIMIT 1")->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        return (int) ($pdo->query("SELECT id FROM user_groups WHERE slug = 'member' AND is_active = 1 LIMIT 1")->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function usersReplaceGroupPermissions(PDO $pdo, int $groupId, array $permissions): void
{
    $groupId = max(0, $groupId);
    if ($groupId <= 0) {
        return;
    }

    $permissions = usersNormalizePermissionKeys($permissions);
    $group = usersGetGroupById($pdo, $groupId);
    if ($group && ((string) ($group['slug'] ?? '') === 'admin')) {
        if (!in_array('*', $permissions, true)) {
            $permissions[] = '*';
        }
    }

    $pdo->prepare("DELETE FROM user_group_permissions WHERE group_id = ?")->execute([$groupId]);
    if (empty($permissions)) {
        return;
    }

    $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
    $stmt = $pdo->prepare("INSERT INTO user_group_permissions (group_id, permission_key, permission_value, created_at, updated_at)
        VALUES (:group_id, :permission_key, 1, {$nowSql}, {$nowSql})");
    foreach ($permissions as $permission) {
        $stmt->execute([
            'group_id' => $groupId,
            'permission_key' => substr($permission, 0, 191),
        ]);
    }
}

function usersGetGroups(PDO $pdo, bool $activeOnly = false): array
{
    try {
        usersEnsureGroupSchema($pdo);
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $stmt = $pdo->query("SELECT g.*,
                (SELECT COUNT(*) FROM user_group_members m WHERE m.group_id = g.id) AS member_count,
                (SELECT COUNT(*) FROM user_group_permissions p WHERE p.group_id = g.id AND p.permission_value = 1) AS permission_count
            FROM user_groups g
            {$where}
            ORDER BY g.display_order ASC, g.name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersGetGroupById(PDO $pdo, int $groupId): ?array
{
    if ($groupId <= 0) {
        return null;
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT * FROM user_groups WHERE id = ? LIMIT 1");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        return $group ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function usersGetGroupPermissionMap(PDO $pdo, int $groupId): array
{
    if ($groupId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT permission_key FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 ORDER BY permission_key");
        $stmt->execute([$groupId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $permission) {
            $map[(string) $permission] = true;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function usersModulePermissionCatalog(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $modulesRoot = dirname(__DIR__, 4) . '/src/Modules';
    if (!is_dir($modulesRoot)) {
        $cached = ['labels' => [], 'descriptions' => []];
        return $cached;
    }

    $labels = [];
    $descriptions = [];

    foreach (glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        try {
            $metadata = require $moduleFile;
        } catch (Throwable $e) {
            continue;
        }

        if (!is_array($metadata) || !isset($metadata['permissions']) || !is_array($metadata['permissions'])) {
            continue;
        }

        foreach ($metadata['permissions'] as $permission) {
            $key = (string) ($permission['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $labels[$key] = (string) ($permission['label'] ?? $key);
            $descriptions[$key] = (string) ($permission['description'] ?? $permission['label'] ?? $key);
        }
    }

    $cached = ['labels' => $labels, 'descriptions' => $descriptions];
    return $cached;
}

function usersPermissionCatalog(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $core = [
        '*' => 'Tum Yetkiler',
        'admin.access' => 'Admin Paneline Erisim',
        'dashboard.view' => 'Dashboard Goruntule',
        'queue.view' => 'Bekleyen Isleri Goruntule',
        'groups.view' => 'Gruplari Goruntule',
        'groups.create' => 'Grup Olustur',
        'groups.edit' => 'Grup Duzenle',
        'groups.delete' => 'Grup Sil',
        'users.view' => 'Kullanicilari Goruntule',
        'users.create' => 'Kullanici Olustur',
        'users.edit' => 'Kullanici Duzenle',
        'users.delete' => 'Kullanici Sil',
        'topics.view' => 'Konulari Goruntule',
        'topics.create' => 'Konu Olustur',
        'topics.edit' => 'Konu Duzenle',
        'topics.delete' => 'Konu Sil',
        'categories.view' => 'Kategorileri Goruntule',
        'categories.create' => 'Kategori Olustur',
        'categories.edit' => 'Kategori Duzenle',
        'categories.delete' => 'Kategori Sil',
        'comments.view' => 'Yorumlari Goruntule',
        'comments.create' => 'Yorum Olustur',
        'comments.edit' => 'Yorum Duzenle',
        'comments.delete' => 'Yorum Sil',
        'settings.view' => 'Ayarlari Goruntule',
        'settings.edit' => 'Ayarlari Duzenle',
        'logs.view' => 'Kayitlari Goruntule',
        'logs.manage' => 'Kayitlari Yonet',
        'media.view' => 'Medyayi Goruntule',
        'media.manage' => 'Medyayi Yonet',
        'scraper.view' => 'Icerik Botunu Goruntule',
        'scraper.manage' => 'Icerik Botunu Yonet',
        'system.view' => 'Sistem Sagligini Goruntule',
        'system.manage' => 'Sistem Bakimini Yonet',
        'notifications.view' => 'Bildirimleri Goruntule',
        'notifications.manage' => 'Bildirimleri Yonet',
        'appearance.view' => 'Gorunumu Goruntule',
        'appearance.edit' => 'Gorunumu Duzenle',
        'themes.view' => 'Temalari Goruntule',
        'themes.edit' => 'Temalari Duzenle',
        'rate_limits.view' => 'Rate Limit Kayitlarini Goruntule',
        'rate_limits.manage' => 'Rate Limit Kayitlarini Yonet',
        'events.view' => 'Etkinlikleri Goruntule',
        'events.manage' => 'Etkinlikleri Yonet',
    ];

    $cached = $core + usersModulePermissionCatalog()['labels'];
    return $cached;
}

function usersPermissionDescriptions(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $core = [
        '*' => 'Sistemdeki tum admin ve yonetim yetkilerini verir. Sadece tam guvenilen gruplarda kullanin.',
        'admin.access' => 'Admin paneline giris yapabilir ve yetkili oldugu admin ekranlarini gorebilir.',
        'dashboard.view' => 'Admin dashboard ozetlerini ve hizli durum kartlarini gorebilir.',
        'queue.view' => 'Bekleyen isler kuyrugunu, bekleyen rapor ve icerik ozetlerini gorebilir.',
        'groups.view' => 'Kullanici gruplarini ve grup yetki listesini goruntuleyebilir.',
        'groups.create' => 'Yeni kullanici grubu olusturabilir.',
        'groups.edit' => 'Mevcut gruplarin ad, sira, durum ve yetkilerini duzenleyebilir.',
        'groups.delete' => 'Gruplari pasife alabilir veya silebilir.',
        'users.view' => 'Kullanici listesini ve kullanici detaylarini goruntuleyebilir.',
        'users.create' => 'Admin panelinden yeni kullanici olusturabilir.',
        'users.edit' => 'Kullanici bilgilerini, durumunu ve grup atamalarini duzenleyebilir.',
        'users.delete' => 'Kullanici hesaplarini silebilir veya silme islemi baslatabilir.',
        'topics.view' => 'Admin panelinde konulari listeleyip detaylarini gorebilir.',
        'topics.create' => 'Yeni konu veya icerik olusturabilir.',
        'topics.edit' => 'Mevcut konu iceriklerini ve yayin durumlarini duzenleyebilir.',
        'topics.delete' => 'Konulari silebilir veya kaldirma islemi yapabilir.',
        'categories.view' => 'Kategori listesini ve kategori ayarlarini goruntuleyebilir.',
        'categories.create' => 'Yeni kategori olusturabilir.',
        'categories.edit' => 'Kategori ad, siralama ve gorunum ayarlarini duzenleyebilir.',
        'categories.delete' => 'Kategorileri silebilir veya pasife alabilir.',
        'comments.view' => 'Yorumlari ve yorum moderasyon ekranlarini goruntuleyebilir.',
        'comments.create' => 'Yorum olusturabilir veya yorum ekleme islemlerini kullanabilir.',
        'comments.edit' => 'Yorum iceriklerini ve moderasyon durumlarini duzenleyebilir.',
        'comments.delete' => 'Yorumlari silebilir veya kaldirabilir.',
        'settings.view' => 'Genel ayarlar ve sistem yapilandirmasini goruntuleyebilir.',
        'settings.edit' => 'Site ayarlarini, gorunum ve sistem yapilandirmasini degistirebilir.',
        'logs.view' => 'Aktivite, islem ve sistem kayitlarini goruntuleyebilir.',
        'logs.manage' => 'Aktivite ve islem kayitlarini temizleyebilir veya geri alma islemlerini kullanabilir.',
        'media.view' => 'Medya kutuphanesini ve yuklenen dosyalari goruntuleyebilir.',
        'media.manage' => 'Medya dosyalarini yukleyebilir, duzenleyebilir veya silebilir.',
        'scraper.view' => 'Icerik botu panelini, site eslemelerini ve bot kayitlarini gorebilir.',
        'scraper.manage' => 'Icerik botu site/esleme ayarlarini, cekme ve yayinlama islemlerini yonetebilir.',
        'system.view' => 'Sistem sagligi ve ortam kontrollerini goruntuleyebilir.',
        'system.manage' => 'Sistem bakim islemlerini calistirabilir.',
        'notifications.view' => 'Bildirim gecmisi, sablonlar ve gonderim loglarini goruntuleyebilir.',
        'notifications.manage' => 'Bildirim olusturabilir, sablonlari ve bildirim ayarlarini yonetebilir.',
        'appearance.view' => 'Gorunum, header, footer, sidebar ve menu ayarlarini gorebilir.',
        'appearance.edit' => 'Gorunum, header, footer, sidebar ve menu ayarlarini kaydedebilir.',
        'themes.view' => 'Tema merkezini, tema dosyalarini ve tema ayarlarini goruntuleyebilir.',
        'themes.edit' => 'Tema aktiflestirme, dosya kaydetme, cogaltma, ZIP yukleme ve tema ayarlari islemlerini yapabilir.',
        'rate_limits.view' => 'Rate limit kayitlarini ve durum ozetlerini goruntuleyebilir.',
        'rate_limits.manage' => 'Rate limit kayitlarini silebilir veya temizleyebilir.',
        'events.view' => 'Etkinlik modulu admin ekranlarini goruntuleyebilir.',
        'events.manage' => 'Etkinlik ayarlari, oduller, cekilisler ve admin aksiyonlarini yonetebilir.',
    ];

    $cached = $core + usersModulePermissionCatalog()['descriptions'];
    return $cached;
}

function usersNormalizePermissionKeys(array $permissions): array
{
    $known = array_fill_keys(array_keys(usersPermissionCatalog()), true);
    $normalized = [];
    foreach ($permissions as $permission) {
        $permission = substr(trim((string) $permission), 0, 191);
        if ($permission === '' || !isset($known[$permission])) {
            continue;
        }
        $normalized[$permission] = true;
    }

    if (isset($normalized['*'])) {
        return ['*'];
    }

    return array_keys($normalized);
}

function usersGroupGrantsAdmin(PDO $pdo, int $groupId): bool
{
    $group = usersGetGroupById($pdo, $groupId);
    if (!$group) {
        return $groupId === 1;
    }
    if ((string) ($group['slug'] ?? '') === 'admin') {
        return true;
    }
    $permissions = usersGetGroupPermissionMap($pdo, $groupId);
    return isset($permissions['*']) || isset($permissions['admin.access']);
}

function usersUserGroupIds(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT m.group_id FROM user_group_members m INNER JOIN user_groups g ON g.id = m.group_id WHERE m.user_id = ? AND g.is_active = 1 ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC");
        $stmt->execute([$userId]);
        return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    } catch (Throwable $e) {
        return [];
    }
}

function usersPrimaryGroupMap(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
    if ($userIds === []) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) {
        return [];
    }

    if (!usersGroupsAvailable($pdo) && empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("SELECT
                m.user_id,
                g.id,
                g.name,
                g.slug
            FROM user_group_members m
            INNER JOIN user_groups g ON g.id = m.group_id
            WHERE m.user_id IN ({$placeholders}) AND g.is_active = 1
            ORDER BY m.user_id ASC, m.is_primary DESC, g.display_order ASC, g.name ASC");
        $stmt->execute($userIds);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid <= 0 || isset($map[$uid])) {
                continue;
            }
            $map[$uid] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function usersDecorateUsersWithPrimaryGroup(PDO $pdo, array $users): array
{
    if ($users === []) {
        return $users;
    }

    $map = usersPrimaryGroupMap($pdo, array_column($users, 'id'));
    if ($map === []) {
        return $users;
    }

    foreach ($users as $index => $user) {
        $uid = (int) ($user['id'] ?? 0);
        $group = $map[$uid] ?? null;
        if (!$group) {
            continue;
        }
        $users[$index]['group_id'] = (int) $group['id'];
        $users[$index]['group_name'] = (string) $group['name'];
        $users[$index]['group_slug'] = (string) $group['slug'];
    }

    return $users;
}

function usersPrimaryGroupForUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $map = usersPrimaryGroupMap($pdo, [$userId]);
    return $map[$userId] ?? null;
}

function usersDecorateUserWithPrimaryGroup(PDO $pdo, array $user): array
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return $user;
    }

    $group = usersPrimaryGroupForUser($pdo, $userId);
    if (!$group) {
        return $user;
    }
    $user['group_id'] = (int) $group['id'];
    $user['group_name'] = (string) $group['name'];
    $user['group_slug'] = (string) $group['slug'];

    return $user;
}

function usersSaveGroup(PDO $pdo, array $data, int $actorId = 0): array
{
    usersEnsureGroupSchema($pdo);

    $groupId = max(0, (int) ($data['group_id'] ?? $data['id'] ?? 0));
    $name = trim((string) ($data['name'] ?? ''));
    $slug = usersGroupSlug((string) ($data['slug'] ?? $name));
    $description = trim((string) ($data['description'] ?? ''));
    $priority = max(1, (int) ($data['priority'] ?? $data['display_order'] ?? 1));
    $displayOrder = $priority;
    $color = function_exists('uiCssColorValue') ? uiCssColorValue((string)($data['color'] ?? '')) : trim((string)($data['color'] ?? ''));
    if ($color === '') {
        $color = null;
    }
    $isActive = isset($data['is_active']) ? 1 : 0;
    $isDefault = isset($data['is_default']) ? 1 : 0;
    $isStaff = isset($data['is_staff']) ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Grup adi zorunludur.'];
    }
    if ($slug === '') {
        return ['ok' => false, 'message' => 'Grup slug degeri olusturulamadi.'];
    }

    $existingGroup = $groupId > 0 ? usersGetGroupById($pdo, $groupId) : null;
    $existingSlug = (string) ($existingGroup['slug'] ?? '');
    $existingIsDefault = (int) ($existingGroup['is_default'] ?? 0) === 1;

    if ($existingSlug === 'admin') {
        if ($slug !== 'admin') {
            return ['ok' => false, 'message' => 'Admin grubunun slug degeri degistirilemez.'];
        }
        $isActive = 1;
        $isStaff = 1;
    }

    if ($existingIsDefault && $isActive !== 1) {
        return ['ok' => false, 'message' => 'Varsayilan grup pasife alinamaz.'];
    }
    if ($isDefault === 1 && $isActive !== 1) {
        return ['ok' => false, 'message' => 'Varsayilan grup aktif olmak zorundadir.'];
    }
    if ($existingIsDefault && $isDefault !== 1) {
        $otherDefaultStmt = $pdo->prepare("SELECT id FROM user_groups WHERE is_default = 1 AND is_active = 1 AND id <> ? LIMIT 1");
        $otherDefaultStmt->execute([$groupId]);
        if (!$otherDefaultStmt->fetchColumn()) {
            return ['ok' => false, 'message' => 'En az bir aktif varsayilan grup kalmalidir.'];
        }
    }

    $duplicateStmt = $pdo->prepare("SELECT id FROM user_groups WHERE slug = ? AND id <> ? LIMIT 1");
    $duplicateStmt->execute([$slug, $groupId]);
    if ($duplicateStmt->fetchColumn()) {
        return ['ok' => false, 'message' => 'Bu slug ile baska bir grup var.'];
    }

    $permissions = (array) ($data['permissions'] ?? []);
    $permissions = usersNormalizePermissionKeys($permissions);
    if ($slug === 'admin' && !in_array('*', $permissions, true)) {
        $permissions[] = '*';
    }

    $ownTransaction = !$pdo->inTransaction();

    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';

        if ($isDefault === 1) {
            $pdo->exec("UPDATE user_groups SET is_default = 0");
        }

        if ($groupId > 0) {
            $stmt = $pdo->prepare("UPDATE user_groups SET
                    name = :name,
                    slug = :slug,
                    description = :description,
                    color = :color,
                    priority = :priority,
                    display_order = :display_order,
                    is_active = :is_active,
                    is_default = :is_default,
                    is_staff = :is_staff,
                    updated_at = {$nowSql}
                WHERE id = :id");
            $stmt->execute([
                'id' => $groupId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'color' => $color,
                'priority' => $priority,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_staff' => $isStaff,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_groups (name, slug, description, color, priority, display_order, is_active, is_default, is_staff, created_at, updated_at)
                VALUES (:name, :slug, :description, :color, :priority, :display_order, :is_active, :is_default, :is_staff, {$nowSql}, {$nowSql})");
            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'color' => $color,
                'priority' => $priority,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'is_staff' => $isStaff,
            ]);
            $groupId = (int) $pdo->lastInsertId();
        }

        usersReplaceGroupPermissions($pdo, $groupId, $permissions);

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return ['ok' => true, 'message' => 'Grup kaydedildi.', 'group_id' => $groupId];
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSaveGroup', 'group_id' => $groupId, 'actor_id' => $actorId]);
        }
        return ['ok' => false, 'message' => 'Grup kaydedilemedi.'];
    }
}

function usersDeleteGroup(PDO $pdo, int $groupId, int $actorId = 0): string
{
    usersEnsureGroupSchema($pdo);

    if ($groupId <= 0) {
        return 'Gecersiz grup.';
    }

    $group = usersGetGroupById($pdo, $groupId);
    if (!$group) {
        return 'Grup bulunamadi.';
    }
    if ((string) ($group['slug'] ?? '') === 'admin') {
        return 'Admin grubu pasife alinamaz.';
    }
    if ((int) ($group['is_default'] ?? 0) === 1) {
        return 'Varsayilan grup pasife alinamaz. Once baska bir grubu varsayilan yapin.';
    }

    $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
    $fallbackGroupId = usersDefaultGroupId($pdo);
    if ($fallbackGroupId <= 0 || $fallbackGroupId === $groupId) {
        return 'Aktif varsayilan grup bulunamadigi icin grup pasife alinamaz.';
    }

    $pdo->prepare("UPDATE user_groups SET is_active = 0, is_default = 0, updated_at = {$nowSql} WHERE id = ?")->execute([$groupId]);
    if ($fallbackGroupId > 0 && $fallbackGroupId !== $groupId) {
        $userIdsStmt = $pdo->prepare("SELECT user_id FROM user_group_members WHERE group_id = ?");
        $userIdsStmt->execute([$groupId]);
        foreach ($userIdsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $userId) {
            $current = array_values(array_filter(usersUserGroupIds($pdo, (int) $userId), static fn(int $id): bool => $id !== $groupId));
            if (empty($current)) {
                $current[] = $fallbackGroupId;
            }
            usersSyncUserGroups($pdo, (int) $userId, $current, $actorId, 'group_deactivated');
        }
    }

    return '';
}

function usersSyncUserGroups(PDO $pdo, int $userId, array $groupIds, int $changedBy = 0, string $reason = ''): string
{
    usersEnsureGroupSchema($pdo);

    if ($userId <= 0) {
        return 'Gecersiz kullanici.';
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => $id > 0)));
    if (empty($groupIds)) {
        $defaultGroupId = usersDefaultGroupId($pdo);
        if ($defaultGroupId > 0) {
            $groupIds[] = $defaultGroupId;
        }
    }

    if (empty($groupIds)) {
        return 'En az bir aktif grup secilmelidir.';
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    if (usersIsSqlite($pdo)) {
        $activeStmt = $pdo->prepare("SELECT id FROM user_groups WHERE id IN ({$placeholders}) AND is_active = 1");
        $activeStmt->execute($groupIds);
        $activeSet = array_fill_keys(array_map('intval', $activeStmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
        $activeGroupIds = array_values(array_filter($groupIds, static fn(int $id): bool => isset($activeSet[$id])));
    } else {
        $activeStmt = $pdo->prepare("SELECT id FROM user_groups WHERE id IN ({$placeholders}) AND is_active = 1 ORDER BY FIELD(id, {$placeholders})");
        $activeStmt->execute(array_merge($groupIds, $groupIds));
        $activeGroupIds = array_values(array_map('intval', $activeStmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }
    if (empty($activeGroupIds)) {
        return 'Aktif grup bulunamadi.';
    }

    if ($userId === $changedBy) {
        $keepsAdmin = false;
        foreach ($activeGroupIds as $groupId) {
            if (usersGroupGrantsAdmin($pdo, $groupId)) {
                $keepsAdmin = true;
                break;
            }
        }
        if (!$keepsAdmin) {
            return 'Kendi admin grubunuzu kaldiramazsiniz.';
        }
    }

    $oldGroupIds = usersUserGroupIds($pdo, $userId);

    $ownTransaction = !$pdo->inTransaction();

    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        $pdo->prepare("DELETE FROM user_group_members WHERE user_id = ?")->execute([$userId]);

        $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
        $insertStmt = $pdo->prepare("INSERT INTO user_group_members (user_id, group_id, is_primary, assigned_by, reason, created_at, updated_at)
            VALUES (:user_id, :group_id, :is_primary, :assigned_by, :reason, {$nowSql}, {$nowSql})");
        foreach ($activeGroupIds as $index => $groupId) {
            $insertStmt->execute([
                'user_id' => $userId,
                'group_id' => $groupId,
                'is_primary' => $index === 0 ? 1 : 0,
                'assigned_by' => $changedBy > 0 ? $changedBy : null,
                'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
            ]);
        }

        $logStmt = $pdo->prepare("INSERT INTO user_group_logs (user_id, old_group_ids, new_group_ids, changed_by, reason, created_at)
            VALUES (:user_id, :old_group_ids, :new_group_ids, :changed_by, :reason, {$nowSql})");
        $logStmt->execute([
            'user_id' => $userId,
            'old_group_ids' => implode(',', $oldGroupIds),
            'new_group_ids' => implode(',', $activeGroupIds),
            'changed_by' => $changedBy > 0 ? $changedBy : null,
            'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
        ]);

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return '';
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSyncUserGroups', 'user_id' => $userId]);
        }
        return 'Kullanici gruplari guncellenemedi.';
    }
}

function usersUserHasGroupPermission(PDO $pdo, int $userId, string $permission): ?bool
{
    if ($userId <= 0 || $permission === '') {
        return false;
    }

    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) {
        return null;
    }

    if (!usersGroupsAvailable($pdo) && empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return null;
    }

    try {
        $permissionKeys = [$permission];

        // 1. Bireysel yetki ezme (override) kontrolleri (Grup yetkilerinden once degerlendirilir)
        $overrideStmt = $pdo->prepare("SELECT permission_key, permission_value 
            FROM user_group_permission_overrides 
            WHERE user_id = ? AND (permission_key = '*' OR permission_key IN (" . implode(',', array_fill(0, count($permissionKeys), '?')) . "))");
        $overrideStmt->execute(array_merge([$userId], $permissionKeys));
        $overrides = $overrideStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($overrides)) {
            $hasDeny = false;
            $hasGrant = false;
            foreach ($overrides as $override) {
                if ((int)$override['permission_value'] === 0) {
                    $hasDeny = true;
                } else {
                    $hasGrant = true;
                }
            }
            if ($hasDeny) {
                return false;
            }
            if ($hasGrant) {
                return true;
            }
        }

        // 2. Grup yetkisi kontrolleri
        $groupStmt = $pdo->prepare("SELECT g.id, g.slug
            FROM user_group_members m
            INNER JOIN user_groups g ON g.id = m.group_id
            WHERE m.user_id = ? AND g.is_active = 1
            ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC");
        $groupStmt->execute([$userId]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if ((string) ($group['slug'] ?? '') === 'admin') {
                return true;
            }

            $wildcardStmt = $pdo->prepare("SELECT 1 FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 AND permission_key = '*' LIMIT 1");
            $wildcardStmt->execute([(int) $group['id']]);
            if ($wildcardStmt->fetchColumn()) {
                return true;
            }

            $permissionStmt = $pdo->prepare("SELECT 1 FROM user_group_permissions WHERE group_id = ? AND permission_value = 1 AND permission_key IN (" . implode(',', array_fill(0, count($permissionKeys), '?')) . ") LIMIT 1");
            $permissionStmt->execute(array_merge([(int) $group['id']], $permissionKeys));
            if ($permissionStmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersUserHasGroupPermission', 'user_id' => $userId, 'permission' => $permission]);
        }
        return null;
    }
}

function usersGetUserPermissionOverrides(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT permission_key, permission_value, reason FROM user_group_permission_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersSaveUserPermissionOverrides(PDO $pdo, int $userId, array $overrides, int $updatedBy = 0, string $reason = ''): void
{
    if ($userId <= 0) {
        return;
    }

    $ownTransaction = !$pdo->inTransaction();
    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        $pdo->prepare("DELETE FROM user_group_permission_overrides WHERE user_id = ?")->execute([$userId]);

        if (!empty($overrides)) {
            $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
            $stmt = $pdo->prepare("INSERT INTO user_group_permission_overrides (user_id, permission_key, permission_value, reason, updated_by, created_at, updated_at) 
                VALUES (:user_id, :permission_key, :permission_value, :reason, :updated_by, {$nowSql}, {$nowSql})");
            foreach ($overrides as $key => $value) {
                $stmt->execute([
                    'user_id' => $userId,
                    'permission_key' => substr((string)$key, 0, 191),
                    'permission_value' => (int)$value === 1 ? 1 : 0,
                    'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
                    'updated_by' => $updatedBy > 0 ? $updatedBy : null,
                ]);
            }
        }

        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersSaveUserPermissionOverrides', 'user_id' => $userId]);
        }
    }
}

function userHasPermission(?PDO $pdo, int $userId, string $permission): bool
{
    if (!$pdo || $userId <= 0 || $permission === '') {
        return false;
    }

    $result = usersUserHasGroupPermission($pdo, $userId, $permission);
    return $result === true;
}

function userIsAdmin(?PDO $pdo, int $userId): bool
{
    return $pdo instanceof PDO
        && $userId > 0
        && userHasPermission($pdo, $userId, 'admin.access');
}

function usersGetGroupInfo(PDO $pdo, int $userId): ?array
{
    $user = usersGetById($pdo, $userId);
    return $user ?: null;
}

function usersGetGroupHistory(PDO $pdo, int $userId, int $limit = 50): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        usersEnsureGroupSchema($pdo);
        $stmt = $pdo->prepare("SELECT l.*, actor.username AS changed_by_name
            FROM user_group_logs l
            LEFT JOIN users actor ON actor.id = l.changed_by
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersChangeGroup(PDO $pdo, int $userId, int $newGroupId, int $currentUserId, array $validGroupIds): string
{
    if (!in_array($newGroupId, $validGroupIds, false)) {
        return 'Gecersiz grup.';
    }
    if ($userId === $currentUserId && !usersGroupGrantsAdmin($pdo, $newGroupId)) {
        return 'Kendi admin grubunuzu kaldiramazsiniz.';
    }

    return usersSyncUserGroups($pdo, $userId, [$newGroupId], $currentUserId, 'admin_primary_group_change');
}

function usersBan(PDO $pdo, int $userId, string $reason = ''): void
{
    \App\Engine\Users\BanService::ban($pdo, $userId, $reason);
}

function usersUnban(PDO $pdo, int $userId): void
{
    \App\Engine\Users\BanService::unban($pdo, $userId);
}

function usersActivate(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id")
        ->execute(['id' => $userId]);
}

function usersDeactivate(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = :id")
        ->execute(['id' => $userId]);
}

function usersDelete(PDO $pdo, int $userId): void
{
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $userId]);
}

function usersBuildListFilters(string $search = '', string $filterGroup = '', string $filterStatus = '', ?PDO $pdo = null): array
{
    $where = ['1=1'];
    $params = [];
    $searchNameExpr = ($pdo && usersColumnExists($pdo, 'users', 'username'))
        ? "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))"
        : "CONCAT('user-', u.id)";

    if ($search !== '') {
        $searchIpLike = '%' . $search . '%';
        $where[] = '(' . $searchNameExpr . ' LIKE :search_name OR u.email LIKE :search_email OR u.location LIKE :search_location OR u.last_login_ip LIKE :search_ip OR EXISTS (SELECT 1 FROM security_events se WHERE se.user_id = u.id AND se.ip_address LIKE :search_security_ip)' . (ctype_digit($search) ? ' OR u.id = :search_id' : '') . ')';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
        $params['search_location'] = $searchTerm;
        $params['search_ip'] = $searchIpLike;
        $params['search_security_ip'] = $searchIpLike;
        if (ctype_digit($search)) {
            $params['search_id'] = (int) $search;
        }
    }
    if ($filterGroup !== '') {
        if ($pdo && (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)]))) {
            $where[] = 'EXISTS (SELECT 1 FROM user_group_members ugmf WHERE ugmf.user_id = u.id AND ugmf.group_id = :group_id)';
        } else {
            $where[] = '0=1';
        }
        $params['group_id'] = (int) $filterGroup;
    }
    if ($filterStatus === 'banned') {
        $where[] = 'u.is_banned = 1';
    } elseif ($filterStatus === 'active') {
        $where[] = "(u.status = 'active' AND (u.is_banned = 0 OR u.is_banned IS NULL))";
    } elseif ($filterStatus === 'inactive') {
        $where[] = "u.status = 'inactive'";
    } elseif ($filterStatus === 'restricted') {
        $where[] = "EXISTS (SELECT 1 FROM user_restrictions WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW()))";
    }

    return [implode(' AND ', $where), $params];
}

function usersCountList(PDO $pdo, string $search = '', string $filterGroup = '', string $filterStatus = ''): int
{
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    [$whereStr, $params] = usersBuildListFilters($search, $filterGroup, $filterStatus, $pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereStr}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function usersGetList(PDO $pdo, string $search = '', string $filterGroup = '', string $filterStatus = '', int $limit = 50, int $offset = 0, string $sort = 'id', string $dir = 'asc'): array
{
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    [$whereStr, $params] = usersBuildListFilters($search, $filterGroup, $filterStatus, $pdo);
    $lastActivitySources = [];
    if (usersTableExists($pdo, 'user_activity_events')) {
        $lastActivitySources[] = '(SELECT MAX(e.created_at) FROM user_activity_events e WHERE e.user_id = u.id)';
    }
    if (usersColumnExists($pdo, 'users', 'last_activity_at')) {
        $lastActivitySources[] = 'u.last_activity_at';
    }
    if (usersColumnExists($pdo, 'users', 'last_login_at')) {
        $lastActivitySources[] = 'u.last_login_at';
    }
    if (usersColumnExists($pdo, 'users', 'updated_at')) {
        $lastActivitySources[] = 'u.updated_at';
    }
    $lastActivitySources[] = 'u.created_at';
    $lastActivitySql = count($lastActivitySources) > 1
        ? 'COALESCE(' . implode(', ', $lastActivitySources) . ')'
        : $lastActivitySources[0];
    $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
    $orderMap = [
        'id' => 'u.id',
        'user' => "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))",
        'email' => 'u.email',
        'group' => 'primary_group_name',
        'status' => 'user_status_rank',
        'restrictions' => 'active_restriction_count',
        'activity' => 'computed_last_activity_at',
        'created' => 'u.created_at',
    ];
    $sortKey = array_key_exists($sort, $orderMap) ? $sort : 'id';
    $orderBy = $orderMap[$sortKey] . ' ' . $direction . ', u.id ASC';
    $stmt = $pdo->prepare("SELECT u.*,\n                           (SELECT GROUP_CONCAT(DISTINCT restriction_type SEPARATOR ',')\n                            FROM user_restrictions\n                            WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW())) AS restrictions,\n                           (SELECT COUNT(*)\n                            FROM user_restrictions\n                            WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW())) AS active_restriction_count,\n                           (SELECT g.name\n                            FROM user_group_members m\n                            INNER JOIN user_groups g ON g.id = m.group_id\n                            WHERE m.user_id = u.id AND g.is_active = 1\n                            ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC\n                            LIMIT 1) AS primary_group_name,\n                           CASE\n                               WHEN u.is_banned = 1 THEN 4\n                               WHEN EXISTS (SELECT 1 FROM user_restrictions ur WHERE ur.user_id = u.id AND (ur.expires_at IS NULL OR ur.expires_at > NOW())) THEN 3\n                               WHEN u.status = 'inactive' THEN 2\n                               ELSE 1\n                           END AS user_status_rank,\n                           {$lastActivitySql} AS computed_last_activity_at\n                           FROM users u\n                           WHERE {$whereStr}\n                           ORDER BY {$orderBy}\n                           LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetStats(PDO $pdo): array
{
    usersPruneExpiredRestrictions($pdo);
    try {
        usersEnsureGroupSchema($pdo);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    $adminCountSql = "SELECT COUNT(DISTINCT m.user_id)\n            FROM user_group_members m\n            INNER JOIN user_groups g ON g.id = m.group_id\n            LEFT JOIN user_group_permissions p ON p.group_id = g.id AND p.permission_value = 1 AND p.permission_key IN ('*', 'admin.access')\n            WHERE g.is_active = 1 AND (g.slug = 'admin' OR p.permission_key IS NOT NULL)";
    return [
        'total' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND (is_banned = 0 OR is_banned IS NULL)")->fetchColumn(),
        'banned' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
        'admins' => (int) $pdo->query($adminCountSql)->fetchColumn(),
        'restricted' => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_restrictions WHERE expires_at IS NULL OR expires_at > NOW()")->fetchColumn(),
    ];
}

function usersGetById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT u.*\n                           FROM users u\n                           WHERE u.id = :id\n                           LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        return usersDecorateUserWithPrimaryGroup($pdo, $user);
    }
    return $user;
}

function usersUpdateProfile(PDO $pdo, int $userId, array $data, int $currentUserId, array $validGroupIds): string
{
    if (function_exists('usersEnsureUsernameSchema')) {
        usersEnsureUsernameSchema($pdo);
    }
    $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

    $usernameRaw = trim((string) ($data['username'] ?? ''));
    $username = function_exists('usersValidateUsernameInput')
        ? usersValidateUsernameInput($usernameRaw, $settings)
        : '';
    $usernamePolicyError = function_exists('usersValidateUsernamePolicy')
        ? usersValidateUsernamePolicy($username, $settings)
        : '';
    $email = trim((string) ($data['email'] ?? ''));
    $groupId = (int) ($data['group_id'] ?? 0);
    $status = trim((string) ($data['status'] ?? 'active'));
    $bio = trim((string) ($data['bio'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $github = trim((string) ($data['social_github'] ?? ''));
    $twitter = trim((string) ($data['social_twitter'] ?? ''));
    $discord = trim((string) ($data['social_discord'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $nowSql = usersIsSqlite($pdo) ? "datetime('now')" : 'NOW()';

    if ($username === '' || $email === '') {
        return 'Kullanici adi ve e-posta zorunludur.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Gecerli bir e-posta adresi girin.';
    }
    if (function_exists('usersValidateEmailDomainPolicy')) {
        $emailPolicyError = usersValidateEmailDomainPolicy($email, $settings);
        if ($emailPolicyError !== '') {
            return $emailPolicyError;
        }
    }
    if ($username === '') {
        $bounds = function_exists('usersUsernameLengthBounds') ? usersUsernameLengthBounds($settings) : ['min' => 3, 'max' => 30];
        return "Kullanici adi {$bounds['min']}-{$bounds['max']} karakter olmali ve sadece harf, rakam, _ veya - icermelidir.";
    }
    if ($usernamePolicyError !== '') {
        return $usernamePolicyError;
    }
    if (!in_array($groupId, $validGroupIds, false)) {
        return 'Gecersiz grup.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        return 'Gecersiz durum.';
    }
    if ($userId === $currentUserId && !usersGroupGrantsAdmin($pdo, $groupId)) {
        return 'Kendi admin grubunuzu kaldiramazsiniz.';
    }
    if ($password !== '') {
        $policyError = validatePasswordPolicy($password, null, 'Sifre');
        if ($policyError !== '') {
            return $policyError;
        }
    }

    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $existsStmt->execute(['email' => $email, 'id' => $userId]);
    if ($existsStmt->fetch()) {
        return 'Bu e-posta adresi baska bir kullanicida kayitli.';
    }

    $usernameExistsSql = 'SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1';
    $usernameExistsStmt = $pdo->prepare($usernameExistsSql);
    $usernameExistsStmt->execute(['username' => $username, 'id' => $userId]);
    if ($usernameExistsStmt->fetch()) {
        return 'Bu kullanici adi baska bir kullanicida kayitli.';
    }

    $sql = "UPDATE users
            SET username = :username,
                email = :email,
                status = :status,
                bio = :bio,
                website = :website,
                location = :location,
                social_github = :github,
                social_twitter = :twitter,
                social_discord = :discord,
                updated_at = {$nowSql}";
    $params = [
        'username' => $username,
        'email' => $email,
        'status' => $status,
        'bio' => $bio !== '' ? $bio : null,
        'website' => function_exists('profileNormalizeExternalUrl') ? (profileNormalizeExternalUrl($website) ?: null) : ($website !== '' ? $website : null),
        'location' => $location !== '' ? $location : null,
        'github' => function_exists('profileNormalizeSocialHandle') ? (profileNormalizeSocialHandle($github) ?: null) : ($github !== '' ? $github : null),
        'twitter' => function_exists('profileNormalizeSocialHandle') ? (profileNormalizeSocialHandle($twitter) ?: null) : ($twitter !== '' ? $twitter : null),
        'discord' => $discord !== '' ? $discord : null,
        'id' => $userId,
    ];
    if ($password !== '') {
        $sql .= ', password = :password, password_changed_at = ' . $nowSql . ', remember_token = NULL';
        $params['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if (function_exists('invalidatePublicContentCache')) {
        invalidatePublicContentCache();
    }

    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $groupError = usersSyncUserGroups($pdo, $userId, [$groupId], $currentUserId, 'admin_profile_group_update');
        if ($groupError !== '') {
            return $groupError;
        }
    }

    return '';
}

function usersApplyBulkAction(PDO $pdo, string $action, array $userIds, int $currentUserId, array $payload, array $validGroupIds): string
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
    if (empty($userIds)) {
        return 'Toplu islem icin en az bir kullanici secin.';
    }

    if (in_array($currentUserId, $userIds, true) && in_array($action, ['ban', 'deactivate', 'delete'], true)) {
        return 'Kendi hesabiniza bu toplu islemi uygulayamazsiniz.';
    }

    switch ($action) {
        case 'activate':
            $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
            foreach ($userIds as $id) {
                $stmt->execute([$id]);
            }
            return '';

        case 'deactivate':
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$id]);
            }
            return '';

        case 'ban':
            $reason = trim((string) ($payload['ban_reason'] ?? ''));
            if ($reason === '') {
                return 'Toplu ban icin gerekce zorunludur.';
            }
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 1, banned_at = NOW(), ban_reason = ?, updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$reason, $id]);
            }
            return '';

        case 'unban':
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, banned_at = NULL, ban_reason = NULL, updated_at = NOW() WHERE id = ?');
            foreach ($userIds as $id) {
                $stmt->execute([$id]);
            }
            return '';

        case 'change_group':
            $groupId = (int) ($payload['group_id'] ?? 0);
            if (!in_array($groupId, $validGroupIds, true)) {
                return 'Toplu grup degisimi icin gecerli bir grup secin.';
            }
            foreach ($userIds as $id) {
                $err = usersChangeGroup($pdo, $id, $groupId, $currentUserId, $validGroupIds);
                if ($err !== '') {
                    return $err;
                }
            }
            return '';

        case 'delete':
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            foreach ($userIds as $id) {
                if ($id === $currentUserId) {
                    continue;
                }
                $stmt->execute([$id]);
            }
            return '';
    }

    return 'Gecersiz toplu islem.';
}

function usersGetRestrictionTypes(): array
{
    return [
        'all' => 'Tum Islemler',
        'comment' => 'Yorum',
        'topic' => 'Konu',
        'upload' => 'Yukleme',
        'message' => 'Mesaj',
        'download' => 'Indirme',
        'profile' => 'Profil',
        'events' => 'Etkinlik',
    ];
}

function usersAddRestriction(PDO $pdo, int $userId, string $restrictionType, ?string $reason = null, ?int $expiresInDays = null, ?int $adminId = null): void
{
    if (!array_key_exists($restrictionType, usersGetRestrictionTypes())) {
        $restrictionType = 'all';
    }

    $expiresAt = $expiresInDays && $expiresInDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days")) : null;
    $pdo->prepare("INSERT INTO user_restrictions (user_id, restriction_type, reason, expires_at, admin_id, created_at)
                   VALUES (:user_id, :type, :reason, :expires_at, :admin_id, NOW())")
        ->execute([
            'user_id' => $userId,
            'type' => $restrictionType,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'admin_id' => $adminId
        ]);
}

function usersRemoveRestriction(PDO $pdo, int $restrictionId): void
{
    $pdo->prepare("DELETE FROM user_restrictions WHERE id = :id")
        ->execute(['id' => $restrictionId]);
}

function usersRemoveAllRestrictions(PDO $pdo, int $userId): void
{
    $pdo->prepare("DELETE FROM user_restrictions WHERE user_id = :user_id")
        ->execute(['user_id' => $userId]);
}

function usersPruneExpiredRestrictions(PDO $pdo, int $limit = 100): int
{
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    try {
        $nowSql = function_exists('userActivityNowSql') ? userActivityNowSql($pdo) : 'NOW()';
        $stmt = $pdo->prepare("SELECT id, user_id, restriction_type, reason, expires_at FROM user_restrictions WHERE expires_at IS NOT NULL AND expires_at <= {$nowSql} ORDER BY expires_at ASC LIMIT ?");
        $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            if (function_exists('userActivityLog')) {
                userActivityLog($pdo, (int) $row['user_id'], 'user_restriction_expired', 'moderation', 'restriction', (int) $row['id'], 'Kisitlama suresi doldu', [
                    'restriction_type' => (string) $row['restriction_type'],
                    'reason' => (string) ($row['reason'] ?? ''),
                    'expires_at' => (string) ($row['expires_at'] ?? ''),
                ], null);
            }
            if (function_exists('usersDispatchAccountNotification')) {
                usersDispatchAccountNotification(
                    $pdo,
                    'user_restriction_removed',
                    (int) $row['user_id'],
                    null,
                    'Hesabinizdaki ' . usersGetRestrictionTypeLabel((string) $row['restriction_type']) . ' kisitlamasi suresi doldugu icin kaldirildi.',
                    'success'
                );
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delete = $pdo->prepare("DELETE FROM user_restrictions WHERE id IN ({$placeholders})");
        $delete->execute($ids);
        return count($ids);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersPruneExpiredRestrictions']);
        }
        return 0;
    }
}

function usersGetRestrictionsForUsers(PDO $pdo, array $userIds, bool $onlyActive = true): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
    if ($userIds === []) {
        return [];
    }

    if ($onlyActive) {
        usersPruneExpiredRestrictions($pdo);
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT r.*, u.username AS admin_name
            FROM user_restrictions r
            LEFT JOIN users u ON u.id = r.admin_id
            WHERE r.user_id IN ({$placeholders})";
    if ($onlyActive) {
        $sql .= " AND (r.expires_at IS NULL OR r.expires_at > NOW())";
    }
    $sql .= " ORDER BY r.user_id ASC, r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        if (!isset($map[$uid])) {
            $map[$uid] = [];
        }
        $map[$uid][] = $row;
    }

    return $map;
}

function usersGetRestrictions(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $map = usersGetRestrictionsForUsers($pdo, [$userId], true);
    return $map[$userId] ?? [];
}

function usersGetBannedList(PDO $pdo, string $search = ''): array
{
    $where = ['u.is_banned = 1'];
    $params = [];
    $searchNameExpr = usersColumnExists($pdo, 'users', 'username')
        ? "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))"
        : "CONCAT('user-', u.id)";

    if ($search !== '') {
        $where[] = '(' . $searchNameExpr . ' LIKE :search_name OR u.email LIKE :search_email)';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT u.* FROM users u
                           WHERE {$whereStr}
                           ORDER BY u.banned_at DESC
                           LIMIT 200");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetRestrictedList(PDO $pdo, string $search = ''): array
{
    usersPruneExpiredRestrictions($pdo);
    $where = ['EXISTS (SELECT 1 FROM user_restrictions WHERE user_id = u.id AND (expires_at IS NULL OR expires_at > NOW()))'];
    $params = [];
    $searchNameExpr = usersColumnExists($pdo, 'users', 'username')
        ? "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))"
        : "CONCAT('user-', u.id)";

    if ($search !== '') {
        $where[] = '(' . $searchNameExpr . ' LIKE :search_name OR u.email LIKE :search_email)';
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_email'] = $searchTerm;
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT u.* FROM users u
                           WHERE {$whereStr}
                           ORDER BY u.id ASC
                           LIMIT 200");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (usersGroupsAvailable($pdo) || !empty($GLOBALS['_users_group_schema_ready_' . spl_object_id($pdo)])) {
        $rows = usersDecorateUsersWithPrimaryGroup($pdo, $rows);
    }
    return $rows;
}

function usersGetRestrictionTypeLabel(string $type): string
{
    return match($type) {
        'all' => 'Tum Islemler',
        'comment' => 'Yorum Yapma',
        'topic' => 'Konu Olusturma',
        'upload' => 'Dosya Yukleme',
        'download' => 'Indirme',
        'message' => 'Mesaj Gonderme',
        'profile' => 'Profil Duzenleme',
        'events' => 'Etkinlik Kullanimi',
        default => ucfirst($type)
    };
}

function usersHasRestriction(PDO $pdo, int $userId, string $restrictionType): bool
{
    return \App\Engine\Users\BanCheck::hasRestriction($pdo, $userId, $restrictionType);
}

function usersGetAccessRestriction(PDO $pdo, int $userId): ?array
{
    return \App\Engine\Users\BanCheck::accessRestriction($pdo, $userId);
}

function usersEnsureAdminNotesTable(PDO $pdo): void
{
    if (!usersTableExists($pdo, 'user_admin_notes')) {
        throw new RuntimeException('Missing user_admin_notes; run Admin Panel > Database Synchronization.');
    }
}

function usersAddAdminNote(PDO $pdo, int $userId, int $adminId, string $note, string $tone = 'info', string $tags = ''): string
{
    $note = trim($note);
    if ($userId <= 0) {
        return 'Gecersiz kullanici.';
    }
    if (mb_strlen($note) < 3) {
        return 'Not en az 3 karakter olmalidir.';
    }
    if (mb_strlen($note) > 2000) {
        return 'Not en fazla 2000 karakter olabilir.';
    }
    if (!in_array($tone, ['info', 'warning', 'danger', 'success'], true)) {
        $tone = 'info';
    }

    usersEnsureAdminNotesTable($pdo);
    $nowSql = function_exists('userActivityNowSql') ? userActivityNowSql($pdo) : 'NOW()';
    $pdo->prepare("INSERT INTO user_admin_notes (user_id, admin_id, note, tone, tags, created_at, updated_at)
        VALUES (:user_id, :admin_id, :note, :tone, :tags, {$nowSql}, {$nowSql})")
        ->execute([
            'user_id' => $userId,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'note' => $note,
            'tone' => $tone,
            'tags' => trim($tags) !== '' ? mb_substr(trim($tags), 0, 255) : null,
        ]);

    if (function_exists('userActivityLog')) {
        userActivityLog($pdo, $userId, 'user_admin_note_added', 'note', 'user', $userId, 'Admin notu eklendi', [
            'tone' => $tone,
            'tags' => $tags,
        ], $adminId);
    }
    if (function_exists('adminAuditLogger')) {
        adminAuditLogger()->logAction($pdo, 'user_admin_note_added', 'user', $userId, 'Admin notu eklendi', [], [
            'tone' => $tone,
            'tags' => $tags,
        ], false);
    }

    return '';
}

function usersGetAdminNotes(PDO $pdo, int $userId, int $limit = 20): array
{
    try {
        usersEnsureAdminNotesTable($pdo);
        $stmt = $pdo->prepare("SELECT n.*, a.username AS admin_name, a.email AS admin_email
            FROM user_admin_notes n
            LEFT JOIN users a ON a.id = n.admin_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function usersDispatchAccountNotification(
    PDO $pdo,
    string $eventKey,
    int $userId,
    ?int $actorId,
    string $message,
    string $type = 'info',
    string $link = '/bildirimler',
    ?int $entityId = null
): void {
    if (!function_exists('notificationDispatch') || $userId <= 0) {
        return;
    }

    if ($link === '/bildirimler') {
        $link = '/' . ltrim((string) routePublicStaticPath('notifications'), '/');
    }

    try {
        notificationDispatch($pdo, $eventKey, $userId, $actorId, 'user', $entityId ?? $userId, [
            'recipient_name' => 'Kullanici',
            'actor_name' => 'Yonetim',
            'moderation_note' => $message,
            'type' => $type,
            'link' => $link,
            'dedupe_key' => $eventKey . ':' . $userId . ':' . ($entityId ?? $userId) . ':' . substr(hash('sha1', $message . microtime(true)), 0, 10),
        ]);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'usersDispatchAccountNotification', 'event_key' => $eventKey]);
        }
    }
}

function usersBanAppealService(): BanAppealService
{
    static $service = null;

    return $service ??= new BanAppealService(
        new BanAppealSchemaService(),
        new BanAppealNotificationService()
    );
}

function usersEnsureBanAppealSchema(PDO $pdo): void
{
    usersBanAppealService()->ensureSchema($pdo);
}

function usersEnsureBanAppealMessagesTable(PDO $pdo): void
{
    usersBanAppealService()->ensureMessagesTable($pdo);
}

function usersSubmitBanAppeal(PDO $pdo, int $userId, string $message): string
{
    return usersBanAppealService()->submitForUser($pdo, $userId, $message);
}

function usersAddBanAppealMessage(PDO $pdo, int $appealId, ?int $senderUserId, string $senderType, string $message): string
{
    return usersBanAppealService()->addMessage($pdo, $appealId, $senderUserId, $senderType, $message);
}

function usersGetBanAppealMessages(PDO $pdo, int $appealId): array
{
    return usersBanAppealService()->messages($pdo, $appealId);
}

function usersBanAppealStatusLabel(string $status): string
{
    return usersBanAppealService()->statusLabel($status);
}

function usersCreateBanAppeal(PDO $pdo, int $userId, string $message): string
{
    return usersBanAppealService()->create($pdo, $userId, $message);
}

function usersGetBanAppealsForUser(PDO $pdo, int $userId): array
{
    return usersBanAppealService()->forUser($pdo, $userId);
}

function usersGetActiveBanAppealId(PDO $pdo, int $userId): ?int
{
    return usersBanAppealService()->activeId($pdo, $userId);
}

function usersGetBanAppealStats(PDO $pdo): array
{
    return usersBanAppealService()->stats($pdo);
}

function usersGetBanAppealsForAdmin(PDO $pdo, string $statusFilter = ''): array
{
    return usersBanAppealService()->forAdmin($pdo, $statusFilter);
}

function usersGetBanAppealForAdmin(PDO $pdo, int $appealId): ?array
{
    return usersBanAppealService()->forAdminById($pdo, $appealId);
}

function usersUpdateBanAppeal(PDO $pdo, int $appealId, string $status, string $adminNote, int $adminId): string
{
    return usersBanAppealService()->update($pdo, $appealId, $status, $adminNote, $adminId);
}

function usersReplyBanAppeal(PDO $pdo, int $appealId, int $adminId, string $message): string
{
    return usersBanAppealService()->reply($pdo, $appealId, $adminId, $message);
}

function usersRestrictedPathAllowed(string $path): bool
{
    return \App\Engine\Users\BanCheck::restrictedPathAllowed($path);
}


