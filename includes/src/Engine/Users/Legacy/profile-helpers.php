<?php
/**
 * Profile Module - Kullanici profili is mantigi
 */

declare(strict_types=1);

use App\Engine\Users\ProfilePresentation;

require_once dirname(__DIR__, 2) . '/Media/Legacy/helpers.php';

function profilePresentation(): ProfilePresentation
{
    static $presentation = null;

    if (!$presentation instanceof ProfilePresentation) {
        $presentation = new ProfilePresentation();
    }

    return $presentation;
}

function profileGetUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT u.*
                           FROM users u
                           WHERE u.id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if ($user && function_exists('usersDecorateUserWithPrimaryGroup')) {
        $user = usersDecorateUserWithPrimaryGroup($pdo, $user);
    }
    return $user ?: null;
}

function profileUserDisplayNameExpr(PDO $pdo, string $alias = 'u'): string
{
    return function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username')
        ? "COALESCE(NULLIF({$alias}.username, ''), CONCAT('user-', {$alias}.id))"
        : "CONCAT('user-', {$alias}.id)";
}

function profileEnsureColumns(PDO $pdo): void
{
    // Profile columns are defined in database/schema.sql. This remains as a
    // compatibility hook for older call sites without changing schema at runtime.
}

function ensureTopicCollectionsTables(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    static $initialized = false;
    if ($initialized) {
        return;
    }

    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        $initialized = true;
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_collections (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(500) DEFAULT NULL,
        visibility VARCHAR(20) NOT NULL DEFAULT 'private',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY topic_collections_user_name_unique (user_id, name),
        INDEX topic_collections_user_index (user_id),
        CONSTRAINT topic_collections_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS topic_collection_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        collection_id BIGINT UNSIGNED NOT NULL,
        topic_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY topic_collection_items_unique (collection_id, topic_id),
        INDEX topic_collection_items_topic_index (topic_id),
        CONSTRAINT topic_collection_items_collection_foreign FOREIGN KEY (collection_id) REFERENCES topic_collections(id) ON DELETE CASCADE,
        CONSTRAINT topic_collection_items_topic_foreign FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $initialized = true;
}

function createTopicCollection(PDO $pdo, int $userId, string $name, string $description = ''): array
{
    $name = trim($name);
    if ($name === '') {
        return ['success' => false, 'message' => 'Koleksiyon adı boş olamaz.'];
    }
    if (mb_strlen($name) > 120) {
        return ['success' => false, 'message' => 'Koleksiyon adı en fazla 120 karakter olabilir.'];
    }

    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO topic_collections (user_id, name, description, visibility, created_at, updated_at)
                                VALUES (:user_id, :name, :description, 'private', NOW(), NOW())");
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'description' => mb_substr(trim($description), 0, 500),
        ]);
        return ['success' => true, 'message' => 'Koleksiyon oluşturuldu.'];
    } catch (Throwable $e) {
        if ($e->getCode() === '23000') {
            return ['success' => false, 'message' => 'Bu adda bir koleksiyonunuz zaten var.'];
        }
        return ['success' => false, 'message' => 'Koleksiyon oluşturulamadı.'];
    }
}

function deleteTopicCollection(PDO $pdo, int $collectionId, int $userId): bool
{
    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("DELETE FROM topic_collections WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $collectionId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function addTopicToCollection(PDO $pdo, int $collectionId, int $topicId, int $userId): bool
{
    if ($collectionId <= 0 || $topicId <= 0) {
        return false;
    }

    ensureTopicCollectionsTables($pdo);

    try {
        // Koleksiyonun kullanıcıya ait olduğunu kontrol et
        $check = $pdo->prepare("SELECT 1 FROM topic_collections WHERE id = :id AND user_id = :user_id LIMIT 1");
        $check->execute(['id' => $collectionId, 'user_id' => $userId]);
        if (!$check->fetch()) {
            return false;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO topic_collection_items (collection_id, topic_id, created_at)
                                VALUES (:collection_id, :topic_id, NOW())");
        $stmt->execute(['collection_id' => $collectionId, 'topic_id' => $topicId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function removeTopicFromCollection(PDO $pdo, int $collectionId, int $topicId, int $userId): bool
{
    if ($collectionId <= 0 || $topicId <= 0) {
        return false;
    }

    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("DELETE ci FROM topic_collection_items ci
                                INNER JOIN topic_collections c ON c.id = ci.collection_id
                                WHERE ci.collection_id = :collection_id AND ci.topic_id = :topic_id AND c.user_id = :user_id");
        $stmt->execute([
            'collection_id' => $collectionId,
            'topic_id' => $topicId,
            'user_id' => $userId,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function profileAllowedTabs(): array
{
    return ['overview', 'topics', 'comments', 'favorites', 'reports', 'activity', 'settings', 'security'];
}

function profileResolveTab(mixed $tab): string
{
    $tab = is_string($tab) ? $tab : '';
    return in_array($tab, profileAllowedTabs(), true) ? $tab : 'overview';
}

function profileNormalizeExternalUrl(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    return $url;
}

function profileNormalizeSocialHandle(?string $handle): string
{
    $handle = trim((string) $handle);
    if ($handle === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $handle)) {
        $path = parse_url($handle, PHP_URL_PATH);
        $handle = is_string($path) ? trim($path, '/') : '';
    }

    $handle = ltrim($handle, '@');
    if (!preg_match('/^[A-Za-z0-9_.-]{1,255}$/', $handle)) {
        return '';
    }

    return $handle;
}

function profileUpdate(PDO $pdo, int $userId, array $data): void
{
    if (function_exists('usersEnsureUsernameSchema')) {
        usersEnsureUsernameSchema($pdo);
    }

    $usernameRaw = trim((string) ($data['username'] ?? ''));
    $username = function_exists('usersValidateUsernameInput')
        ? usersValidateUsernameInput($usernameRaw)
        : '';
    if ($username === '') {
        throw new RuntimeException('Kullanici adi 3-30 karakter olmali ve sadece harf, rakam, _ veya - icermelidir.');
    }

    $usernameCheckSql = "SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1";
    $usernameStmt = $pdo->prepare($usernameCheckSql);
    $usernameStmt->execute([
        'username' => $username,
        'id' => $userId,
    ]);
    if ($usernameStmt->fetch()) {
        throw new RuntimeException('Bu kullanici adi zaten kayitli.');
    }

    $stmt = $pdo->prepare("UPDATE users SET
        username = :username,
        bio = :bio,
        website = :website,
        location = :location,
        social_github = :github,
        social_twitter = :twitter,
        social_discord = :discord,
        public_profile = :public_profile,
        public_show_topics = :public_show_topics,
        public_show_comments = :public_show_comments,
        public_show_socials = :public_show_socials,
        updated_at = NOW()
        WHERE id = :id");
    $params = [
        'username'=> $username,
        'bio'     => $data['bio'],
        'website' => profileNormalizeExternalUrl($data['website'] ?? ''),
        'location'=> $data['location'],
        'github'  => profileNormalizeSocialHandle($data['social_github'] ?? ''),
        'twitter' => profileNormalizeSocialHandle($data['social_twitter'] ?? ''),
        'discord' => trim((string) ($data['social_discord'] ?? '')),
        'public_profile' => !empty($data['public_profile']) ? 1 : 0,
        'public_show_topics' => !empty($data['public_show_topics']) ? 1 : 0,
        'public_show_comments' => !empty($data['public_show_comments']) ? 1 : 0,
        'public_show_socials' => !empty($data['public_show_socials']) ? 1 : 0,
        'id'      => $userId,
    ];
    $stmt->execute($params);
}

function profileChangePassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword, ?string $newPasswordConfirm = null): string
{
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) return 'Kullanıcı bulunamadı.';

    if (!password_verify($currentPassword, $row['password'])) {
        return 'Mevcut şifreniz yanlış.';
    }
    $policyError = validatePasswordPolicy($newPassword, null, 'Yeni şifre');
    if ($policyError !== '') {
        return $policyError;
    }

    if ($newPasswordConfirm !== null && $newPassword !== $newPasswordConfirm) {
        return 'Yeni sifre tekrari eslesmiyor.';
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = :pw, password_changed_at = NOW(), remember_token = NULL, updated_at = NOW() WHERE id = :id")
        ->execute(['pw' => $hash, 'id' => $userId]);
    return '';
}

function profileUploadAvatar(PDO $pdo, int $userId, array $file, string $uploadBase): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Dosya yükleme hatası.';
    if ($file['size'] > 2 * 1024 * 1024) return 'Avatar en fazla 2 MB olabilir.';

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
        return 'Sadece JPG, PNG, WebP ve GIF desteklenir.';
    }

    if (class_exists('UploadSecurity')) {
        $validation = UploadSecurity::getInstance()->validateUpload($file, 'image');
        if (empty($validation['valid'])) {
            return implode(' ', (array) ($validation['errors'] ?? ['Gecersiz dosya.']));
        }
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!str_starts_with($mime, 'image/')) return 'Geçersiz dosya türü.';

    $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return 'Kullanıcı bulunamadı.';
    }

    $profileIdentity = trim((string) ($user['username'] ?? ''));
    if ($profileIdentity === '') {
        $profileIdentity = 'profil-' . $userId;
    }
    $profileSlug = profileSlugify($profileIdentity) ?: ('profil-' . $userId);
    $relativeDir = 'uploads/profil/profil-' . $userId . '-' . $profileSlug;
    $dir = rtrim($uploadBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, str_replace('uploads/', '', $relativeDir));
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return 'Profil klasörü oluşturulamadı.';
    }

    // Eski avatari sil
    $oldAvatar = $user['avatar'] ?? null;
    $oldAvatarRelative = preg_replace('#^uploads/#', '', str_replace('\\', '/', (string)$oldAvatar)) ?: '';
    $oldAvatarPath = $oldAvatarRelative !== '' ? mediaResolvePath($uploadBase, $oldAvatarRelative) : null;
    if ($oldAvatarPath && is_file($oldAvatarPath)) {
        unlink($oldAvatarPath);
    }

    $fileName = function_exists('uploadProfileAvatarFilename')
        ? uploadProfileAvatarFilename($userId, $profileIdentity, $ext)
        : 'user-' . $userId . '-' . $profileSlug . '-avatar.' . $ext;
    $fileName = function_exists('uploadAvailableFilename')
        ? uploadAvailableFilename($dir, $fileName)
        : $fileName;
    $destPath = $dir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'Dosya taşınamadı.';
    }

    $relPath = $relativeDir . '/' . $fileName;
    $pdo->prepare("UPDATE users SET avatar = :avatar, updated_at = NOW() WHERE id = :id")
        ->execute(['avatar' => $relPath, 'id' => $userId]);
    if (function_exists('invalidateUserAvatarCache')) {
        invalidateUserAvatarCache();
    }
    if (function_exists('invalidatePublicContentCache')) {
        invalidatePublicContentCache();
    }
    if (is_file(dirname(__DIR__, 3) . '/Modules/Events/init.php')) {
        require_once dirname(__DIR__, 3) . '/Modules/Events/init.php';
    }
    if (function_exists('eventsRecordActivity')) {
        eventsRecordActivity($pdo, $userId, 'profile_avatar_uploaded', 'user', $userId, [
            'is_approved' => true,
            'dedupe_key' => 'profile_avatar_uploaded:user:' . $userId,
        ]);
    }
    return '';
}

function profileSlugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    if ($value === '') {
        return '';
    }

    $map = [
        "\u{00E7}" => 'c', "\u{011F}" => 'g', "\u{0131}" => 'i',
        "i\u{0307}" => 'i', "\u{00F6}" => 'o', "\u{015F}" => 's',
        "\u{00FC}" => 'u', "\u{00E2}" => 'a', "\u{00EE}" => 'i', "\u{00FB}" => 'u',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-');
}

function profileGetTopics(PDO $pdo, int $userId, int $limit = 10, int $offset = 0): array
{
    $stmt = $pdo->prepare("SELECT t.*, cat.name AS category, cat.slug AS category_slug,
                                  m.path AS hero_image
                           FROM topics t
                           LEFT JOIN categories cat ON t.category_id = cat.id
                           LEFT JOIN media_files m ON t.id = m.topic_id AND m.type = 'image' AND m.display_order = 0
                           WHERE t.author_id = :uid AND t.deleted_at IS NULL AND t.status = 'published'
                           ORDER BY t.published_at DESC
                           LIMIT :lim OFFSET :offset");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function profileCountRows(PDO $pdo, string $table, string $userColumn, int $userId, string $extraWhere = ''): int
{
    $sql = "SELECT COUNT(*) FROM {$table} WHERE {$userColumn} = :uid";
    if ($extraWhere !== '') {
        $sql .= " AND {$extraWhere}";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function profileCountPublishedTopics(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
                           FROM topics
                           WHERE author_id = :uid AND deleted_at IS NULL AND status = 'published'");
    $stmt->execute(['uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function profileResubmitTopicForModeration(PDO $pdo, int $topicId, int $userId): bool
{
    if ($topicId <= 0 || $userId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE topics
                           SET status = 'draft', updated_at = NOW()
                           WHERE id = :id
                             AND author_id = :uid
                             AND deleted_at IS NULL
                             AND status IN ('draft', 'rejected', 'revision')");
    $stmt->execute([
        'id' => $topicId,
        'uid' => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

function profileCountComments(PDO $pdo, int $userId): int
{
    return profileCountRows($pdo, 'comments', 'user_id', $userId, 'deleted_at IS NULL');
}

function profileCountFavorites(PDO $pdo, int $userId): int
{
    ensureTopicFavoritesTable($pdo);
    return profileCountRows($pdo, 'topic_favorites', 'user_id', $userId);
}

function profileCountReports(PDO $pdo, int $userId): int
{
    ensureTopicReportsTable($pdo);
    return profileCountRows($pdo, 'reports', 'user_id', $userId);
}

function profileCountActivity(PDO $pdo, int $userId, string $filter = 'all'): int
{
    $filter = profileResolveActivityFilter($filter);
    $condition = profileActivityFilterCondition($filter, 'a');
    if ($condition === '') {
        return profileCountRows($pdo, 'activity_logs', 'actor_id', $userId);
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a WHERE a.actor_id = :uid AND ({$condition})");
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function profileActivityFilterOptions(): array
{
    return [
        'all' => ['label' => 'Tümü', 'icon' => 'bi-grid'],
        'login' => ['label' => 'Girişler', 'icon' => 'bi-box-arrow-in-right'],
        'topics' => ['label' => 'Konular', 'icon' => 'bi-file-earmark-text'],
        'comments' => ['label' => 'Yorumlar', 'icon' => 'bi-chat-dots'],
        'security' => ['label' => 'Güvenlik', 'icon' => 'bi-shield-lock'],
    ];
}

function profileResolveActivityFilter($value): string
{
    $filter = strtolower(trim((string) $value));
    return array_key_exists($filter, profileActivityFilterOptions()) ? $filter : 'all';
}

function profileActivityFilterCondition(string $filter, string $alias = 'a'): string
{
    $filter = profileResolveActivityFilter($filter);
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'a';
    $action = "LOWER(COALESCE({$alias}.action, ''))";
    $subjectType = "LOWER(COALESCE({$alias}.subject_type, ''))";

    return match ($filter) {
        'login' => "({$action} LIKE '%login%' OR {$action} LIKE '%logout%')",
        'topics' => "({$subjectType} = 'topic' OR {$action} LIKE 'topic_%' OR {$action} LIKE '%favorite%')",
        'comments' => "({$subjectType} = 'comment' OR {$action} LIKE 'comment_%')",
        'security' => "({$action} LIKE '%password%' OR {$action} LIKE '%security%' OR {$action} LIKE '%restriction%' OR {$action} LIKE '%ban%' OR {$action} LIKE '%appeal%')",
        default => '',
    };
}

function profileActivityIsLogin(array $activity): bool
{
    $action = (string)($activity['action'] ?? $activity['event_type'] ?? '');

    return $action === 'user_login' || $action === 'successful_login' || str_contains($action, 'login');
}

function profileActivityIsLocalIp(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }

    return preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip) === 1;
}

function profileActivityTitle(array $activity): string
{
    $action = (string)($activity['action'] ?? $activity['event_type'] ?? '');
    if ($action === '') {
        return 'Aktivite';
    }

    if (function_exists('logsFormatAction')) {
        return logsFormatAction($action);
    }

    if (function_exists('userActivityEventLabel')) {
        return userActivityEventLabel($action);
    }

    return ucwords(str_replace('_', ' ', $action));
}

function profileActivityProperties(array $activity): array
{
    if (empty($activity['properties'])) {
        return [];
    }

    $props = json_decode((string) $activity['properties'], true);
    return is_array($props) ? $props : [];
}

function profileActivityReadableText($value, int $maxLength = 96): ?string
{
    if (is_array($value) || is_object($value) || $value === null) {
        return null;
    }

    $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    if ($text === '') {
        return null;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            return rtrim(mb_substr($text, 0, max(1, $maxLength - 3), 'UTF-8')) . '...';
        }

        return $text;
    }

    return strlen($text) > $maxLength
        ? rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...'
        : $text;
}

function profileActivitySubjectTitle(array $activity): ?string
{
    $subjectType = strtolower(trim((string) ($activity['subject_type'] ?? '')));
    $props = profileActivityProperties($activity);

    if ($subjectType === 'comment') {
        return profileActivityReadableText($activity['activity_comment_topic_title'] ?? null)
            ?? profileActivityReadableText($props['topic_title'] ?? null)
            ?? profileActivityReadableText($props['topic_name'] ?? null)
            ?? profileActivityReadableText($props['subject_title'] ?? null)
            ?? profileActivityReadableText($activity['activity_comment_body'] ?? null, 72);
    }

    if ($subjectType === 'topic') {
        return profileActivityReadableText($props['subject_title'] ?? null)
            ?? profileActivityReadableText($activity['activity_topic_title'] ?? null);
    }

    if ($subjectType === 'user') {
        return profileActivityReadableText($props['subject_title'] ?? null)
            ?? profileActivityReadableText($activity['activity_subject_user_name'] ?? null);
    }

    return profileActivityReadableText($props['subject_title'] ?? null);
}

function profileActivityPropertyLabel(string $key): string
{
    $labels = [
        'title' => 'Başlık',
        'status' => 'Durum',
        'decision' => 'Karar',
        'note' => 'Not',
        'reason' => 'Sebep',
        'source' => 'Kaynak',
        'topic_slug' => 'Konu bağlantısı',
        'health_status' => 'Bağlantı durumu',
        'status_code' => 'Durum kodu',
    ];

    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function profileActivityLowerLabel(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function profileActivityIntValue($value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return max(0, (int) $value);
    }

    return 0;
}

function profileActivityTopicUrl(?string $slug, int $topicId): string
{
    $slug = trim((string) $slug);
    $topicId = max(0, $topicId);
    if ($slug !== '' && function_exists('topicUrl')) {
        return topicUrl($slug, $topicId > 0 ? $topicId : null);
    }

    if ($topicId <= 0) {
        return '';
    }

    global $baseUri;
    return ($baseUri ?: '') . '/topic.php?id=' . $topicId;
}

function profileActivityTargetUrl(array $activity): string
{
    $action = strtolower((string)($activity['action'] ?? $activity['event_type'] ?? ''));
    $subjectType = strtolower(trim((string)($activity['subject_type'] ?? '')));
    $props = profileActivityProperties($activity);

    if ($subjectType === 'comment' || str_contains($action, 'comment')) {
        $topicId = profileActivityIntValue($activity['activity_comment_topic_id'] ?? null)
            ?: profileActivityIntValue($props['topic_id'] ?? null);
        $topicSlug = profileActivityReadableText($activity['activity_comment_topic_slug'] ?? null, 140)
            ?? profileActivityReadableText($props['topic_slug'] ?? null, 140)
            ?? profileActivityReadableText($props['slug'] ?? null, 140);
        $commentId = $subjectType === 'comment'
            ? profileActivityIntValue($activity['subject_id'] ?? null)
            : 0;
        $commentId = $commentId ?: profileActivityIntValue($activity['activity_comment_id'] ?? null);
        $commentId = $commentId ?: profileActivityIntValue($props['comment_id'] ?? null);

        $topicUrl = profileActivityTopicUrl($topicSlug, $topicId);
        if ($topicUrl === '') {
            return '';
        }

        return $commentId > 0 ? $topicUrl . '#comment-' . $commentId : $topicUrl;
    }

    if ($subjectType === 'topic' || $subjectType === 'topic_report' || str_contains($action, 'topic') || str_contains($action, 'favorite')) {
        $topicId = profileActivityIntValue($activity['activity_topic_id'] ?? null)
            ?: ($subjectType === 'topic' ? profileActivityIntValue($activity['subject_id'] ?? null) : 0)
            ?: profileActivityIntValue($props['topic_id'] ?? null);
        $topicSlug = profileActivityReadableText($activity['activity_topic_slug'] ?? null, 140)
            ?? profileActivityReadableText($props['topic_slug'] ?? null, 140)
            ?? profileActivityReadableText($props['slug'] ?? null, 140);

        return profileActivityTopicUrl($topicSlug, $topicId);
    }

    return '';
}

function profileActivitySentence(array $activity): string
{
    $action = strtolower((string)($activity['action'] ?? $activity['event_type'] ?? ''));
    $subjectType = strtolower(trim((string)($activity['subject_type'] ?? '')));
    $subjectTitle = profileActivitySubjectTitle($activity);
    $topicObject = $subjectTitle !== null ? $subjectTitle . ' konusunu' : 'Bir konuyu';
    $topicTarget = $subjectTitle !== null ? $subjectTitle . ' konusuna' : 'Bir konuya';
    $topicPlace = $subjectTitle !== null ? $subjectTitle . ' konusunda' : 'Bir konuda';
    $topicSubject = $subjectTitle !== null ? $subjectTitle . ' konusu' : 'Bir konu';

    if (profileActivityIsLogin($activity)) {
        return 'Giriş yaptın';
    }
    if (str_contains($action, 'logout')) {
        return 'Çıkış yaptın';
    }
    if (str_contains($action, 'register')) {
        return 'Hesabını oluşturdun';
    }
    if (str_contains($action, 'password')) {
        return 'Şifreni güncelledin';
    }
    if (str_contains($action, 'avatar')) {
        return 'Profil fotoğrafını güncelledin';
    }
    if (str_contains($action, 'profile')) {
        return 'Profil bilgilerini güncelledin';
    }

    if ($subjectType === 'comment' || str_contains($action, 'comment')) {
        if (str_contains($action, 'report')) {
            return $topicPlace . ' bir yorumu raporladın';
        }
        if (str_contains($action, 'delete')) {
            return $topicPlace . ' yorumunu sildin';
        }
        if (str_contains($action, 'edit') || str_contains($action, 'update')) {
            return $topicPlace . ' yorumunu düzenledin';
        }

        return $topicTarget . ' yorum yaptın';
    }

    if (str_contains($action, 'favorite_added')) {
        return $topicObject . ' favorilere ekledin';
    }
    if (str_contains($action, 'favorite_removed')) {
        return $topicObject . ' favorilerden kaldırdın';
    }

    if ($subjectType === 'topic' || str_contains($action, 'topic')) {
        if ($action === 'topic_viewed') {
            return $topicObject . ' görüntüledin';
        }
        if (str_contains($action, 'resubmit')) {
            return $topicObject . ' tekrar onaya gönderdin';
        }
        if (str_contains($action, 'upload') || str_contains($action, 'create')) {
            return $topicObject . ' yükledin';
        }
        if (str_contains($action, 'edit') || str_contains($action, 'update')) {
            return $topicObject . ' düzenledin';
        }
        if (str_contains($action, 'delete')) {
            return $topicObject . ' sildin';
        }
        if (str_contains($action, 'restore')) {
            return $topicObject . ' geri aldın';
        }
        if (str_contains($action, 'report')) {
            return $topicObject . ' raporladın';
        }

        return $topicSubject . ' üzerinde işlem yaptın';
    }

    if ($subjectType === 'user' && str_contains($action, 'report')) {
        return $subjectTitle !== null ? $subjectTitle . ' kullanıcısını raporladın' : 'Bir kullanıcıyı raporladın';
    }

    $label = profileActivityTitle($activity);
    return $subjectTitle !== null ? $subjectTitle . ' için ' . profileActivityLowerLabel($label) : $label;
}

function profileActivityLoginDetail(array $activity): string
{
    $ip = trim((string)($activity['activity_ip_address'] ?? $activity['ip_address'] ?? ''));
    $browser = trim((string)($activity['activity_browser'] ?? $activity['browser'] ?? ''));
    $platform = trim((string)($activity['activity_platform'] ?? $activity['platform'] ?? ''));
    $device = trim((string)($activity['activity_device_type'] ?? $activity['device_type'] ?? ''));

    $parts = [];
    $parts[] = 'IP: ' . ($ip !== '' ? $ip : 'Kayıt yok');
    $parts[] = 'Tarayıcı: ' . ($browser !== '' ? $browser : 'Bilinmiyor');

    if ($platform !== '') {
        $parts[] = 'Platform: ' . $platform;
    }
    if ($device !== '') {
        $parts[] = 'Cihaz: ' . $device;
    }

    return implode(' · ', $parts);
}

function profileActivityDetailLabel(array $activity): string
{
    if (profileActivityIsLogin($activity)) {
        return profileActivityLoginDetail($activity);
    }

    $props = profileActivityProperties($activity);
    $subjectType = strtolower(trim((string) ($activity['subject_type'] ?? '')));
    $parts = [];

    if ($subjectType === 'comment') {
        $topicTitle = profileActivityReadableText($activity['activity_comment_topic_title'] ?? null)
            ?? profileActivityReadableText($props['topic_title'] ?? null)
            ?? profileActivityReadableText($props['topic_name'] ?? null);
        if ($topicTitle !== null) {
            $parts[] = 'Konu: ' . $topicTitle;
        }

        $commentExcerpt = profileActivityReadableText($activity['activity_comment_body'] ?? null, 86);
        if ($commentExcerpt !== null) {
            $parts[] = 'Yorum özeti: ' . $commentExcerpt;
        }
    } elseif ($subjectType === 'topic') {
        $topicTitle = profileActivitySubjectTitle($activity);
        if ($topicTitle !== null) {
            $parts[] = 'Konu: ' . $topicTitle;
        }
    }

    foreach ($props as $key => $value) {
        $key = (string) $key;
        if ($key === 'subject_title' || $key === 'topic_title' || $key === 'topic_name' || $key === 'id' || str_ends_with($key, '_id')) {
            continue;
        }

        $text = profileActivityReadableText($value);
        if ($text === null) {
            continue;
        }

        $parts[] = profileActivityPropertyLabel($key) . ': ' . $text;
    }

    $parts = array_values(array_unique($parts));
    return $parts ? implode(' · ', $parts) : 'Ek detay yok';
}

function profileActivityDisplayDetail(array $activity): string
{
    $detail = profileActivityDetailLabel($activity);
    if ($detail === 'Ek detay yok') {
        return '';
    }

    $subjectTitle = profileActivitySubjectTitle($activity);
    if ($subjectTitle !== null && in_array($detail, ['Konu: ' . $subjectTitle, 'Başlık: ' . $subjectTitle], true)) {
        return '';
    }

    return $detail;
}

function profileActivityRowsMatch(array $activity, array $event): bool
{
    if ((string)($activity['action'] ?? '') !== (string)($event['event_type'] ?? '')) {
        return false;
    }

    $activitySubject = (string)($activity['subject_type'] ?? '');
    $eventSubject = (string)($event['subject_type'] ?? '');
    if ($activitySubject !== '' && $eventSubject !== '' && $activitySubject !== $eventSubject) {
        return false;
    }

    $activitySubjectId = (int)($activity['subject_id'] ?? 0);
    $eventSubjectId = (int)($event['subject_id'] ?? 0);
    if ($activitySubjectId > 0 && $eventSubjectId > 0 && $activitySubjectId !== $eventSubjectId) {
        return false;
    }

    $activityTime = strtotime((string)($activity['created_at'] ?? ''));
    $eventTime = strtotime((string)($event['created_at'] ?? ''));
    if ($activityTime !== false && $eventTime !== false && abs($activityTime - $eventTime) > 300) {
        return false;
    }

    return true;
}

function profileEnrichActivityRows(PDO $pdo, int $userId, array $rows): array
{
    if (empty($rows) || !function_exists('userActivityEnsureSchema')) {
        return $rows;
    }

    try {
        userActivityEnsureSchema($pdo);

        $times = [];
        foreach ($rows as $row) {
            $ts = strtotime((string)($row['created_at'] ?? ''));
            if ($ts !== false) {
                $times[] = $ts;
            }
        }
        if (empty($times)) {
            return $rows;
        }

        $from = date('Y-m-d H:i:s', min($times) - 600);
        $to = date('Y-m-d H:i:s', max($times) + 600);
        $stmt = $pdo->prepare("SELECT id, event_type, subject_type, subject_id, title, ip_address, user_agent, device_type, browser, platform, request_path, metadata_json, created_at
                               FROM user_activity_events
                               WHERE user_id = :user_id
                                 AND created_at >= :date_from
                                 AND created_at <= :date_to
                               ORDER BY created_at DESC, id DESC
                               LIMIT 250");
        $stmt->execute([
            'user_id' => $userId,
            'date_from' => $from,
            'date_to' => $to,
        ]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $used = [];

        foreach ($rows as $rowIndex => $row) {
            foreach ($events as $eventIndex => $event) {
                if (isset($used[$eventIndex]) || !profileActivityRowsMatch($row, $event)) {
                    continue;
                }

                $rows[$rowIndex]['activity_event_id'] = (int)($event['id'] ?? 0);
                $rows[$rowIndex]['activity_title'] = (string)($event['title'] ?? '');
                $rows[$rowIndex]['activity_ip_address'] = (string)($event['ip_address'] ?? '');
                $rows[$rowIndex]['activity_user_agent'] = (string)($event['user_agent'] ?? '');
                $rows[$rowIndex]['activity_device_type'] = (string)($event['device_type'] ?? '');
                $rows[$rowIndex]['activity_browser'] = (string)($event['browser'] ?? '');
                $rows[$rowIndex]['activity_platform'] = (string)($event['platform'] ?? '');
                $rows[$rowIndex]['activity_request_path'] = (string)($event['request_path'] ?? '');
                $rows[$rowIndex]['activity_metadata_json'] = (string)($event['metadata_json'] ?? '');
                $used[$eventIndex] = true;
                break;
            }
        }
    } catch (Throwable $e) {
        return $rows;
    }

    return $rows;
}
function profileGetPendingTopics(PDO $pdo, int $userId, int $limit = 10, string $statusFilter = ''): array
{
    $allowedStatuses = ['draft', 'rejected', 'revision'];
    $where = "t.author_id = :uid AND t.deleted_at IS NULL AND t.status IN ('draft', 'rejected', 'revision')";
    if ($statusFilter === 'draft') {
        $where = "t.author_id = :uid AND t.deleted_at IS NULL AND t.status = 'draft'";
    } elseif (in_array($statusFilter, $allowedStatuses, true)) {
        $where = "t.author_id = :uid AND t.deleted_at IS NULL AND t.status = :status";
    }

    $stmt = $pdo->prepare("SELECT t.*, cat.name AS category, cat.slug AS category_slug
                           FROM topics t
                           LEFT JOIN categories cat ON t.category_id = cat.id
                           WHERE {$where}
                           ORDER BY t.updated_at DESC, t.created_at DESC
                           LIMIT :lim");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    if ($statusFilter !== 'draft' && in_array($statusFilter, $allowedStatuses, true)) {
        $stmt->bindValue(':status', $statusFilter);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function profileGetComments(PDO $pdo, int $userId, int $limit = 10, int $offset = 0): array
{
    $stmt = $pdo->prepare("SELECT c.*, t.title AS topic_title, t.slug AS topic_slug
                           FROM comments c
                           LEFT JOIN topics t ON c.topic_id = t.id
                           WHERE c.user_id = :uid AND c.deleted_at IS NULL
                           ORDER BY c.created_at DESC
                           LIMIT :lim OFFSET :offset");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function profileGetStatsReal(PDO $pdo, int $userId): array
{
    $topicCount = 0; $commentCount = 0; $totalViews = 0; $totalDownloads = 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE author_id = :uid AND deleted_at IS NULL");
        $s->execute(['uid' => $userId]);
        $topicCount = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :uid AND deleted_at IS NULL");
        $s->execute(['uid' => $userId]);
        $commentCount = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COALESCE(SUM(view_count),0) FROM topics WHERE author_id = :uid AND deleted_at IS NULL");
        $s->execute(['uid' => $userId]);
        $totalViews = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COALESCE(SUM(download_count),0) FROM topics WHERE author_id = :uid AND deleted_at IS NULL");
        $s->execute(['uid' => $userId]);
        $totalDownloads = (int)$s->fetchColumn();
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    return [
        'topics'    => $topicCount,
        'comments'  => $commentCount,
        'views'     => $totalViews,
        'downloads' => $totalDownloads,
    ];
}

function profileGetFavorites(PDO $pdo, int $userId, int $limit = 50, int $offset = 0): array
{
    ensureTopicFavoritesTable($pdo);

    try {
        $authorExpr = profileUserDisplayNameExpr($pdo, 'u');
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.view_count, t.download_count,
                                      cat.name AS category, cat.slug AS category_slug, {$authorExpr} AS author, f.created_at AS favorited_at
                               FROM topic_favorites f
                               INNER JOIN topics t ON t.id = f.topic_id AND t.deleted_at IS NULL
                               LEFT JOIN categories cat ON t.category_id = cat.id
                               LEFT JOIN users u ON t.author_id = u.id
                               WHERE f.user_id = :user_id
                               ORDER BY f.created_at DESC
                               LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileGetCollections(PDO $pdo, int $userId): array
{
    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("SELECT c.*,
                                      COUNT(ci.id) AS item_count,
                                      MAX(ci.created_at) AS last_item_at
                               FROM topic_collections c
                               LEFT JOIN topic_collection_items ci ON ci.collection_id = c.id
                               WHERE c.user_id = :user_id
                               GROUP BY c.id, c.user_id, c.name, c.description, c.visibility, c.created_at, c.updated_at
                               ORDER BY c.updated_at DESC, c.created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileUpdateCollectionVisibility(PDO $pdo, int $collectionId, int $userId, string $visibility): bool
{
    ensureTopicCollectionsTables($pdo);
    $visibility = $visibility === 'public' ? 'public' : 'private';

    try {
        $stmt = $pdo->prepare("UPDATE topic_collections
                               SET visibility = :visibility, updated_at = NOW()
                               WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            'visibility' => $visibility,
            'id' => $collectionId,
            'user_id' => $userId,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function profileGetPublicCollections(PDO $pdo, int $userId, int $limit = 6): array
{
    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("SELECT c.id, c.name, c.description, c.updated_at,
                                      COUNT(t.id) AS item_count
                               FROM topic_collections c
                               LEFT JOIN topic_collection_items ci ON ci.collection_id = c.id
                               LEFT JOIN topics t ON t.id = ci.topic_id AND t.deleted_at IS NULL AND t.status = 'published'
                               WHERE c.user_id = :user_id AND c.visibility = 'public'
                               GROUP BY c.id, c.name, c.description, c.updated_at
                               HAVING item_count > 0
                               ORDER BY c.updated_at DESC, c.id DESC
                               LIMIT :limit");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(12, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileGetCollectionPreviewTopics(PDO $pdo, int $collectionId, int $limit = 3): array
{
    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.slug, t.view_count, t.download_count
                               FROM topic_collection_items ci
                               INNER JOIN topics t ON t.id = ci.topic_id
                               WHERE ci.collection_id = :collection_id
                                 AND t.deleted_at IS NULL
                                 AND t.status = 'published'
                               ORDER BY ci.created_at DESC, t.published_at DESC
                               LIMIT :limit");
        $stmt->bindValue(':collection_id', $collectionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(6, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileGetActiveRestrictions(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("SELECT restriction_type, reason, expires_at, created_at
                               FROM user_restrictions
                               WHERE user_id = :user_id
                                 AND (expires_at IS NULL OR expires_at > NOW())
                               ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileRestrictionLabel(string $type): string
{
    return [
        'all' => 'Tum islemler',
        'comment' => 'Yorum yapma',
        'topic' => 'Konu olusturma',
        'upload' => 'Dosya yukleme',
        'download' => 'Indirme',
    ][$type] ?? $type;
}

function profileGetCollectionItems(PDO $pdo, int $userId): array
{
    ensureTopicCollectionsTables($pdo);

    try {
        $stmt = $pdo->prepare("SELECT ci.collection_id, ci.topic_id, ci.created_at
                               FROM topic_collection_items ci
                               INNER JOIN topic_collections c ON c.id = ci.collection_id
                               WHERE c.user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $collectionId = (int) $row['collection_id'];
            $topicId = (int) $row['topic_id'];
            $map[$topicId] ??= [];
            $map[$topicId][$collectionId] = true;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function profileGetReports(PDO $pdo, int $userId, int $limit = 50, int $offset = 0): array
{
    ensureTopicReportsTable($pdo);

    try {
        $stmt = $pdo->prepare("SELECT r.*, t.title AS topic_title, t.slug AS topic_slug, cat.name AS category
                               FROM reports r
                               LEFT JOIN topics t ON t.id = r.topic_id
                               LEFT JOIN categories cat ON cat.id = t.category_id
                               WHERE r.user_id = :user_id
                               ORDER BY r.created_at DESC
                               LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function profileGetActivity(PDO $pdo, int $userId, int $limit = 15, int $offset = 0, string $filter = 'all'): array
{
    try {
        $condition = profileActivityFilterCondition($filter, 'a');
        $where = "a.actor_id = :uid" . ($condition !== '' ? " AND ({$condition})" : '');
        $subjectUserExpr = profileUserDisplayNameExpr($pdo, 'subject_user');
        $stmt = $pdo->prepare("SELECT a.*,
                                      topic.id AS activity_topic_id,
                                      topic.title AS activity_topic_title,
                                      topic.slug AS activity_topic_slug,
                                      comment_row.id AS activity_comment_id,
                                      comment_row.body AS activity_comment_body,
                                      comment_topic.id AS activity_comment_topic_id,
                                      comment_topic.title AS activity_comment_topic_title,
                                      comment_topic.slug AS activity_comment_topic_slug,
                                      {$subjectUserExpr} AS activity_subject_user_name
                               FROM activity_logs a
                               LEFT JOIN topics topic ON a.subject_type = 'topic' AND a.subject_id = topic.id
                               LEFT JOIN comments comment_row ON a.subject_type = 'comment' AND a.subject_id = comment_row.id
                               LEFT JOIN topics comment_topic ON comment_row.topic_id = comment_topic.id
                               LEFT JOIN users subject_user ON a.subject_type = 'user' AND a.subject_id = subject_user.id
                               WHERE {$where}
                               ORDER BY a.created_at DESC
                               LIMIT :lim OFFSET :offset");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return profileEnrichActivityRows($pdo, $userId, $stmt->fetchAll() ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function profileGroupBadge(string $groupSlug, string $groupName): string
{
    return profilePresentation()->groupBadge($groupSlug, $groupName);
}
function profileAvatarUrl(string $baseUri, ?string $avatar): string
{
    return profilePresentation()->avatarUrl($baseUri, $avatar);
}
function profileMemberSince(string $createdAt): string
{
    return profilePresentation()->memberSince($createdAt);
}

function profileTenureLabel(string $createdAt): string
{
    return profilePresentation()->tenureLabel($createdAt);
}

function profileBuildProfileContext(array $user, array $options = []): array
{
    return profilePresentation()->profileContext($user, $options);
}

function profileBuildReportReasonOptions(): array
{
    $items = [];
    foreach (userReportReasonLabels() as $value => $label) {
        $items[] = [
            'value' => (string) $value,
            'label' => (string) $label,
        ];
    }

    return $items;
}

function profileBuildSocialLinks(array $user): array
{
    return profilePresentation()->socialLinks($user);
}
function profileBuildSidebarData(array $user, array $options = []): array
{
    return profilePresentation()->sidebarData($user, $options);
}
