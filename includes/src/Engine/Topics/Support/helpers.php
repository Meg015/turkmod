<?php

declare(strict_types=1);

/**
 * Topics Module — topic CRUD, media, favorites, download links.
 */

function getTopicPrimaryMediaPath(array $row): ?string
{
    $path = trim((string)($row['primary_media_path'] ?? ''));
    return $path !== '' ? $path : null;
}

function getTopicMediaRecords(?PDO $pdo, int $topicId, bool $includeAttachments = false): array
{
    if (!$pdo || $topicId <= 0) {
        return [];
    }

    try {
        $where = $includeAttachments
            ? 'WHERE topic_id = ?'
            : "WHERE topic_id = ? AND (type = 'image' OR type = 'video' OR mime_type LIKE 'image/%')";
        $stmt = $pdo->prepare("SELECT id, topic_id, path, original_name, mime_type, type, disk, is_primary, display_order
                               FROM media_files
                               {$where}
                               ORDER BY is_primary DESC, display_order ASC, id ASC");
        $stmt->execute([$topicId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function getTopicMediaGallery(?PDO $pdo, int $topicId): array
{
    return array_values(array_filter(array_map(static function (array $row): ?string {
        $path = trim((string)($row['path'] ?? ''));
        return $path !== '' ? $path : null;
    }, getTopicMediaRecords($pdo, $topicId))));
}

function createTopicMediaRecord(?PDO $pdo, int $topicId, string $path, string $type = 'video', int $displayOrder = 0, bool $isPrimary = false, string $disk = 'remote'): ?int
{
    if (!$pdo || $topicId <= 0) {
        return null;
    }

    $path = trim($path);
    if ($path === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO media_files (topic_id, user_id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at)
                               VALUES (:topic_id, :user_id, :type, :disk, :path, :original_name, :mime_type, NULL, :display_order, :is_primary, NOW(), NOW())");
        $stmt->execute([
            'topic_id' => $topicId,
            'user_id' => $_SESSION['_auth_user_id'] ?? null,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => basename(parse_url($path, PHP_URL_PATH) ?: $path),
            'mime_type' => $type === 'image' ? 'image/remote' : 'video/remote',
            'display_order' => max(0, $displayOrder),
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'createTopicMediaRecord', 'topic_id' => $topicId]);
        return null;
    }
}

function deleteTopicMediaRecord(?PDO $pdo, int $mediaId): void
{
    if (!$pdo || $mediaId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT path, disk FROM media_files WHERE id = ? LIMIT 1");
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $pdo->prepare("DELETE FROM media_files WHERE id = ?")->execute([$mediaId]);
        if ((string)($row['disk'] ?? 'local') === 'local') {
            topicDeletePhysicalFile((string)($row['path'] ?? ''));
        }
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'deleteTopicMediaRecord', 'media_id' => $mediaId]);
    }
}

function ensureTopicFavoritesTable(?PDO $pdo): void
{
    if ($pdo && function_exists('usersTableExists') && !usersTableExists($pdo, 'topic_favorites')) {
        throw new RuntimeException('Missing topic_favorites; run Admin Panel > Database Synchronization.');
    }
}

function userHasFavoritedTopic(?PDO $pdo, int $topicId, int $userId): bool
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return false;
    }

    ensureTopicFavoritesTable($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM topic_favorites WHERE topic_id = :topic_id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function getTopicFavoriteCount(?PDO $pdo, int $topicId): int
{
    if (!$pdo || $topicId <= 0) {
        return 0;
    }

    ensureTopicFavoritesTable($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM topic_favorites WHERE topic_id = :topic_id');
    $stmt->execute(['topic_id' => $topicId]);
    return (int) $stmt->fetchColumn();
}

function toggleTopicFavorite(?PDO $pdo, int $topicId, int $userId): bool
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return false;
    }

    ensureTopicFavoritesTable($pdo);

    if (userHasFavoritedTopic($pdo, $topicId, $userId)) {
        $stmt = $pdo->prepare('DELETE FROM topic_favorites WHERE topic_id = :topic_id AND user_id = :user_id');
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        if (function_exists('logActivity')) {
            logActivity($pdo, 'topic_favorite_removed', 'topic', $topicId);
        }
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO topic_favorites (topic_id, user_id, created_at) VALUES (:topic_id, :user_id, NOW())');
    $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
    if (function_exists('logActivity')) {
        logActivity($pdo, 'topic_favorite_added', 'topic', $topicId);
    }
    return true;
}

function parseTopicDownloadLinksText(string $raw): array
{
    $links = [];
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    foreach ($lines as $line) {
        $parts = explode('|', $line, 2);
        $name = count($parts) === 2 ? trim($parts[0]) : 'Link';
        $url = count($parts) === 2 ? trim($parts[1]) : trim($parts[0]);
        $url = topicDownloadNormalizeUrl($url);

        if ($url === '') {
            continue;
        }

        $links[] = [
            'id' => null,
            'name' => $name !== '' ? $name : 'Link',
            'url' => $url,
            'download_count' => 0,
        ];
    }

    return $links;
}

function topicDownloadNormalizeUrl(string $url): string
{
    $url = preg_replace('/[\x00-\x1F\x7F]+/u', '', $url) ?? $url;

    return trim($url);
}

function topicDownloadAccessMode(array $settings): string
{
    $mode = strtolower(trim((string) ($settings['download_access_mode'] ?? 'public')));
    if (!in_array($mode, ['public', 'members', 'members_comment'], true)) {
        return 'public';
    }

    return $mode;
}

function topicDownloadCommentRequirement(array $settings): string
{
    $mode = strtolower(trim((string) ($settings['download_access_comment_requirement'] ?? 'submitted')));
    return $mode === 'approved' ? 'approved' : 'submitted';
}

function topicDownloadExemptionAllowedScopes(): array
{
    return [
        'access_lock' => true,
        'inline_countdown' => true,
        'redirect_countdown' => true,
        'count_rate_limit' => true,
    ];
}

function topicDownloadExemptionToken(string $value): string
{
    if (function_exists('commentSpamNormalizeExemptionToken')) {
        return commentSpamNormalizeExemptionToken($value);
    }

    $value = trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function topicDownloadExemptionList(string $raw): array
{
    if (function_exists('commentSpamParseExemptionList')) {
        return commentSpamParseExemptionList($raw);
    }

    $items = [];
    foreach (preg_split('/[\r\n,;]+/u', $raw) ?: [] as $item) {
        $token = topicDownloadExemptionToken((string) $item);
        if ($token !== '') {
            $items[$token] = $token;
        }
    }

    return array_values($items);
}

function topicDownloadExemptionScopes(array $settings): array
{
    $allowedScopes = topicDownloadExemptionAllowedScopes();
    if (!array_key_exists('download_exempt_scopes', $settings)) {
        return array_keys($allowedScopes);
    }

    $raw = trim((string) ($settings['download_exempt_scopes'] ?? ''));
    if ($raw === '') {
        return [];
    }

    $scopes = [];
    foreach (preg_split('/[\r\n,;]+/u', $raw) ?: [] as $scope) {
        $scope = trim((string) $scope);
        if ($scope !== '' && isset($allowedScopes[$scope])) {
            $scopes[$scope] = $scope;
        }
    }

    return array_values($scopes);
}

function topicDownloadExemptionScopeEnabled(array $settings, string $scope): bool
{
    return in_array($scope, topicDownloadExemptionScopes($settings), true);
}

function topicDownloadIsUserExemptForScope(?PDO $pdo, array $settings, int $userId, string $scope): bool
{
    if ($userId <= 0 || !topicDownloadExemptionScopeEnabled($settings, $scope)) {
        return false;
    }

    $usernameTokens = topicDownloadExemptionList((string) ($settings['download_exempt_usernames'] ?? ''));
    $groupTokens = topicDownloadExemptionList((string) ($settings['download_exempt_groups'] ?? ''));
    if ($usernameTokens === [] && $groupTokens === []) {
        return false;
    }

    $context = [
        'username' => (string) ($_SESSION['_auth_user_name'] ?? ''),
        'group_names' => [],
        'group_slugs' => [],
    ];
    if ($pdo instanceof PDO && function_exists('commentSpamGetUserContext')) {
        try {
            $context = commentSpamGetUserContext($pdo, $userId);
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['fn' => 'topicDownloadIsUserExemptForScope', 'user_id' => $userId]);
            }
        }
    }

    if ($usernameTokens !== []) {
        $username = topicDownloadExemptionToken((string) ($context['username'] ?? ''));
        if ($username !== '' && in_array($username, $usernameTokens, true)) {
            return true;
        }
    }

    if ($groupTokens !== []) {
        $groupCandidates = array_merge(
            (array) ($context['group_names'] ?? []),
            (array) ($context['group_slugs'] ?? []),
            [(string) ($context['group_name'] ?? ''), (string) ($context['group_slug'] ?? '')]
        );
        foreach ($groupCandidates as $groupCandidate) {
            $token = topicDownloadExemptionToken((string) $groupCandidate);
            if ($token !== '' && in_array($token, $groupTokens, true)) {
                return true;
            }
        }
    }

    return false;
}

function topicDownloadCountdownSeconds(?PDO $pdo, array $settings, int $userId, string $settingKey, string $scope, int $fallback): int
{
    if (topicDownloadIsUserExemptForScope($pdo, $settings, $userId, $scope)) {
        return 0;
    }

    return max(0, (int) ($settings[$settingKey] ?? $fallback));
}

function topicDownloadSettingText(array $settings, string $key, string $fallback): string
{
    $value = trim((string) ($settings[$key] ?? ''));
    if ($value === '') {
        return $fallback;
    }

    return $value;
}

function topicDownloadProgressTemplate(array $settings): string
{
    $fallback = '{{completed}} adımdan {{total}} adımı tamamlandı';
    $template = trim((string) ($settings['download_access_progress_template'] ?? ''));
    if ($template === '' || !str_contains($template, '{{completed}}') || !str_contains($template, '{{total}}')) {
        return $fallback;
    }

    return $template;
}

function topicDownloadProgressText(array $settings, int $completed, int $total): string
{
    return str_replace(
        ['{{completed}}', '{{total}}'],
        [(string) max(0, $completed), (string) max(0, $total)],
        topicDownloadProgressTemplate($settings)
    );
}

function topicDownloadGrantMode(array $settings): string
{
    return strtolower(trim((string) ($settings['download_access_grant_mode'] ?? 'permanent'))) === 'timed'
        ? 'timed'
        : 'permanent';
}

function topicDownloadGrantDurationUnit(array $settings): string
{
    $unit = strtolower(trim((string) ($settings['download_access_grant_duration_unit'] ?? 'hours')));
    return in_array($unit, ['minutes', 'hours', 'days'], true) ? $unit : 'hours';
}

function topicDownloadGrantDurationMaximum(string $unit): int
{
    return match (strtolower(trim($unit))) {
        'minutes' => 525600,
        'days' => 3650,
        default => 87600,
    };
}

function topicDownloadGrantDurationValue(array $settings): int
{
    $unit = topicDownloadGrantDurationUnit($settings);
    $maximum = topicDownloadGrantDurationMaximum($unit);

    return max(1, min($maximum, (int) ($settings['download_access_grant_duration_value'] ?? 24)));
}

function topicDownloadGrantDurationSeconds(array $settings): int
{
    $multiplier = match (topicDownloadGrantDurationUnit($settings)) {
        'minutes' => 60,
        'days' => 86400,
        default => 3600,
    };

    return topicDownloadGrantDurationValue($settings) * $multiplier;
}

function topicDownloadGrantExpiry(array $settings, string $grantedAt): ?string
{
    if (topicDownloadGrantMode($settings) !== 'timed') {
        return null;
    }

    $grantedTimestamp = strtotime($grantedAt);
    if ($grantedTimestamp === false) {
        $grantedTimestamp = time();
    }

    return date('Y-m-d H:i:s', $grantedTimestamp + topicDownloadGrantDurationSeconds($settings));
}

function topicDownloadActiveUntilTemplate(array $settings): string
{
    $fallback = 'İndirme erişiminiz {{expires_at}} tarihine kadar açık.';
    $template = trim((string) ($settings['download_access_active_until_template'] ?? ''));
    return $template !== '' && str_contains($template, '{{expires_at}}') ? $template : $fallback;
}

function topicDownloadActiveUntilText(array $settings, ?string $expiresAt): string
{
    $expiresAt = trim((string) $expiresAt);
    if ($expiresAt === '') {
        return '';
    }

    $timestamp = strtotime($expiresAt);
    if ($timestamp === false) {
        return '';
    }

    $dateFormat = trim((string) ($settings['date_format'] ?? 'd.m.Y')) ?: 'd.m.Y';
    $formatted = date($dateFormat . ' H:i', $timestamp);
    return str_replace('{{expires_at}}', $formatted, topicDownloadActiveUntilTemplate($settings));
}

function topicDownloadAccessStage(bool $locked, string $reason): string
{
    if (!$locked) {
        return 'open';
    }

    $reason = strtolower(trim($reason));
    if ($reason === 'auth_required') {
        return 'login';
    }

    if ($reason === 'comment_required' || $reason === 'comment_expired') {
        return 'comment';
    }

    if ($reason === 'comment_pending') {
        return 'pending';
    }

    return 'locked';
}

function topicDownloadAccessStepClasses(string $stage, bool $commentStepRequired = true): array
{
    $stage = in_array($stage, ['login', 'comment', 'pending', 'open', 'locked'], true) ? $stage : 'locked';

    if (!$commentStepRequired) {
        if ($stage === 'open') {
            return [
                'login' => 'is-complete',
                'comment' => 'is-muted',
                'open' => 'is-active',
            ];
        }

        return [
            'login' => $stage === 'login' ? 'is-active' : 'is-pending',
            'comment' => 'is-muted',
            'open' => 'is-pending',
        ];
    }

    switch ($stage) {
        case 'login':
            return [
                'login' => 'is-active',
                'comment' => 'is-pending',
                'open' => 'is-pending',
            ];
        case 'comment':
            return [
                'login' => 'is-complete',
                'comment' => 'is-active',
                'open' => 'is-pending',
            ];
        case 'pending':
            return [
                'login' => 'is-complete',
                'comment' => 'is-waiting',
                'open' => 'is-pending',
            ];
        case 'open':
            return [
                'login' => 'is-complete',
                'comment' => 'is-complete',
                'open' => 'is-active',
            ];
        default:
            return [
                'login' => 'is-pending',
                'comment' => 'is-pending',
                'open' => 'is-pending',
            ];
    }
}

function topicDownloadAccessFinalizeState(array $state): array
{
    $stage = topicDownloadAccessStage((bool) ($state['locked'] ?? false), (string) ($state['reason'] ?? 'none'));
    $mode = (string) ($state['mode'] ?? 'public');
    $commentStepRequired = $mode === 'members_comment';
    $state['stage'] = $stage;
    $state['comment_step_required'] = $commentStepRequired;
    $state['step_classes'] = topicDownloadAccessStepClasses($stage, $commentStepRequired);
    $state['progress_total'] = match ($mode) {
        'members' => 2,
        'members_comment' => 3,
        default => 0,
    };
    if ($mode === 'public') {
        $state['progress_completed'] = 0;
    } elseif ($mode === 'members') {
        $state['progress_completed'] = $stage === 'open' ? 2 : 0;
    } elseif ($stage === 'login') {
        $state['progress_completed'] = 0;
    } elseif ($stage === 'comment') {
        $state['progress_completed'] = 1;
    } elseif ($stage === 'pending') {
        $state['progress_completed'] = 2;
    } else {
        $state['progress_completed'] = 3;
    }

    return $state;
}

function topicUserTopicCommentState(?PDO $pdo, int $topicId, int $userId): string
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return 'none';
    }

    try {
        $stmt = $pdo->prepare("SELECT status
            FROM comments
            WHERE topic_id = :topic_id
              AND user_id = :user_id
              AND deleted_at IS NULL
              AND status IN ('approved', 'pending')
            ORDER BY CASE status WHEN 'approved' THEN 0 ELSE 1 END, id DESC
            LIMIT 1");
        $stmt->execute([
            'topic_id' => $topicId,
            'user_id' => $userId,
        ]);
        $status = strtolower(trim((string) $stmt->fetchColumn()));
        return in_array($status, ['approved', 'pending'], true) ? $status : 'none';
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicUserTopicCommentState', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
        return 'none';
    }
}

function topicUserHasTopicComment(?PDO $pdo, int $topicId, int $userId, bool $approvedOnly = false): bool
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $sql = "SELECT 1
                FROM comments
                WHERE topic_id = :topic_id
                  AND user_id = :user_id
                  AND deleted_at IS NULL";
        if ($approvedOnly) {
            $sql .= " AND status = 'approved'";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'topic_id' => $topicId,
            'user_id' => $userId,
        ]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicUserHasTopicComment', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
        return false;
    }
}

function topicDownloadAccessGrantTableExists(?PDO $pdo, bool $refresh = false): bool
{
    if (!$pdo) {
        return false;
    }

    static $cache = [];
    $cacheKey = spl_object_id($pdo);
    if (!$refresh && array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->query("SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'topic_download_access_grants'
            LIMIT 1");
        return $cache[$cacheKey] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadAccessGrantTableExists']);
        }
        return $cache[$cacheKey] = false;
    }
}

function topicDownloadEnsureAccessGrantSchema(?PDO $pdo): bool
{
    if (!$pdo) {
        return false;
    }
    if (!topicDownloadAccessGrantTableExists($pdo)) {
        throw new RuntimeException('Missing topic_download_access_grants; run Admin Panel > Database Synchronization.');
    }
    return true;
}

function topicDownloadCommentQualifiesForGrant(array $settings, array $comment): bool
{
    if ((int) ($comment['id'] ?? 0) <= 0 || (int) ($comment['topic_id'] ?? 0) <= 0 || (int) ($comment['user_id'] ?? 0) <= 0) {
        return false;
    }
    if (!empty($comment['deleted_at'])) {
        return false;
    }

    $status = strtolower(trim((string) ($comment['status'] ?? '')));
    if (topicDownloadCommentRequirement($settings) === 'approved') {
        return $status === 'approved';
    }

    return in_array($status, ['approved', 'pending'], true);
}

function topicDownloadCreateAccessGrant(?PDO $pdo, array $settings, array $comment, ?string $grantedAt = null): bool
{
    if (!$pdo || topicDownloadAccessMode($settings) !== 'members_comment' || !topicDownloadCommentQualifiesForGrant($settings, $comment)) {
        return false;
    }
    if (!topicDownloadEnsureAccessGrantSchema($pdo)) {
        return false;
    }

    $grantedAt = trim((string) $grantedAt);
    if ($grantedAt === '') {
        $grantedAt = trim((string) ($comment['created_at'] ?? '')) ?: date('Y-m-d H:i:s');
    }
    $grantMode = topicDownloadGrantMode($settings);
    $expiresAt = topicDownloadGrantExpiry($settings, $grantedAt);

    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO topic_download_access_grants
            (topic_id, user_id, comment_id, grant_mode, granted_at, expires_at, revoked_at, revoke_reason, created_at, updated_at)
            VALUES (:topic_id, :user_id, :comment_id, :grant_mode, :granted_at, :expires_at, NULL, NULL, NOW(), NOW())");
        $stmt->execute([
            'topic_id' => (int) $comment['topic_id'],
            'user_id' => (int) $comment['user_id'],
            'comment_id' => (int) $comment['id'],
            'grant_mode' => $grantMode,
            'granted_at' => $grantedAt,
            'expires_at' => $expiresAt,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadCreateAccessGrant', 'comment_id' => (int) ($comment['id'] ?? 0)]);
        }
        return false;
    }
}

function topicDownloadApproveAccessGrant(?PDO $pdo, array $settings, array $comment, ?string $approvedAt = null): bool
{
    $comment['status'] = 'approved';
    $comment['deleted_at'] = null;
    if (!$pdo || topicDownloadAccessMode($settings) !== 'members_comment' || !topicDownloadCommentQualifiesForGrant($settings, $comment)) {
        return false;
    }
    if (!topicDownloadEnsureAccessGrantSchema($pdo)) {
        return false;
    }

    $approvedAt = trim((string) $approvedAt) ?: date('Y-m-d H:i:s');
    $grantMode = topicDownloadGrantMode($settings);
    $expiresAt = topicDownloadGrantExpiry($settings, $approvedAt);

    try {
        $stmt = $pdo->prepare("UPDATE topic_download_access_grants
            SET grant_mode = :grant_mode,
                granted_at = :granted_at,
                expires_at = :expires_at,
                revoked_at = NULL,
                revoke_reason = NULL,
                updated_at = NOW()
            WHERE comment_id = :comment_id
              AND revoke_reason = 'comment_rejected'");
        $stmt->execute([
            'grant_mode' => $grantMode,
            'granted_at' => $approvedAt,
            'expires_at' => $expiresAt,
            'comment_id' => (int) $comment['id'],
        ]);
        if ($stmt->rowCount() > 0) {
            return true;
        }

        return topicDownloadCreateAccessGrant($pdo, $settings, $comment, $approvedAt);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadApproveAccessGrant', 'comment_id' => (int) ($comment['id'] ?? 0)]);
        }
        return false;
    }
}

function topicDownloadRevokeAccessGrant(?PDO $pdo, int $commentId, string $reason): bool
{
    if (!$pdo || $commentId <= 0 || !topicDownloadEnsureAccessGrantSchema($pdo)) {
        return false;
    }

    $reason = in_array($reason, ['comment_deleted', 'comment_rejected'], true) ? $reason : 'comment_invalid';
    try {
        $stmt = $pdo->prepare("UPDATE topic_download_access_grants
            SET revoked_at = NOW(), revoke_reason = :reason, updated_at = NOW()
            WHERE comment_id = :comment_id AND revoked_at IS NULL");
        $stmt->execute(['reason' => $reason, 'comment_id' => $commentId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadRevokeAccessGrant', 'comment_id' => $commentId]);
        }
        return false;
    }
}

function topicDownloadRestoreAccessGrant(?PDO $pdo, array $settings, array $comment): bool
{
    if (!$pdo || !topicDownloadCommentQualifiesForGrant($settings, array_merge($comment, ['deleted_at' => null])) || !topicDownloadEnsureAccessGrantSchema($pdo)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE topic_download_access_grants
            SET revoked_at = NULL, revoke_reason = NULL, updated_at = NOW()
            WHERE comment_id = :comment_id
              AND revoke_reason = 'comment_deleted'
              AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute(['comment_id' => (int) $comment['id']]);
        if ($stmt->rowCount() > 0) {
            return true;
        }

        return topicDownloadCreateAccessGrant(
            $pdo,
            $settings,
            array_merge($comment, ['deleted_at' => null]),
            trim((string) ($comment['created_at'] ?? '')) ?: null
        );
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadRestoreAccessGrant', 'comment_id' => (int) ($comment['id'] ?? 0)]);
        }
        return false;
    }
}

function topicDownloadLatestUserComment(?PDO $pdo, int $topicId, int $userId): ?array
{
    if (!$pdo || $topicId <= 0 || $userId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, topic_id, user_id, status, deleted_at, created_at, updated_at
            FROM comments
            WHERE topic_id = :topic_id AND user_id = :user_id
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadLatestUserComment', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
        return null;
    }
}

function topicDownloadReconcileAccessGrant(?PDO $pdo, array $settings, int $topicId, int $userId): void
{
    if (!$pdo || $topicId <= 0 || $userId <= 0 || !topicDownloadEnsureAccessGrantSchema($pdo)) {
        return;
    }

    try {
        $requirement = topicDownloadCommentRequirement($settings);
        $statusSql = $requirement === 'approved' ? "c.status = 'approved'" : "c.status IN ('approved', 'pending')";
        $stmt = $pdo->prepare("SELECT c.id, c.topic_id, c.user_id, c.status, c.deleted_at, c.created_at, c.updated_at
            FROM comments c
            LEFT JOIN topic_download_access_grants g ON g.comment_id = c.id
            WHERE c.topic_id = :topic_id
              AND c.user_id = :user_id
              AND c.deleted_at IS NULL
              AND {$statusSql}
              AND g.id IS NULL
            ORDER BY c.id DESC
            LIMIT 1");
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($comment)) {
            return;
        }

        // Reconciliation is for pre-existing comments. The user-selected
        // migration policy uses the original comment date, so an old comment
        // cannot receive a fresh duration merely because the feature was enabled.
        $grantedAt = trim((string) ($comment['created_at'] ?? ''));
        topicDownloadCreateAccessGrant($pdo, $settings, $comment, $grantedAt ?: null);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadReconcileAccessGrant', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
    }
}

function topicDownloadLatestAccessGrant(?PDO $pdo, int $topicId, int $userId): ?array
{
    if (!$pdo || $topicId <= 0 || $userId <= 0 || !topicDownloadEnsureAccessGrantSchema($pdo)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT g.*, c.status AS comment_status, c.deleted_at AS comment_deleted_at,
                c.created_at AS comment_created_at, c.updated_at AS comment_updated_at
            FROM topic_download_access_grants g
            INNER JOIN comments c ON c.id = g.comment_id
            WHERE g.topic_id = :topic_id AND g.user_id = :user_id
            ORDER BY g.granted_at DESC, g.id DESC
            LIMIT 1");
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadLatestAccessGrant', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
        return null;
    }
}

function topicDownloadActiveAccessGrant(?PDO $pdo, array $settings, int $topicId, int $userId): ?array
{
    if (!$pdo || $topicId <= 0 || $userId <= 0 || !topicDownloadEnsureAccessGrantSchema($pdo)) {
        return null;
    }

    $statusSql = topicDownloadCommentRequirement($settings) === 'approved'
        ? "c.status = 'approved'"
        : "c.status IN ('approved', 'pending')";
    $deletedSql = (string) ($settings['download_access_relock_on_comment_delete'] ?? '1') === '1'
        ? 'AND c.deleted_at IS NULL'
        : '';

    try {
        $stmt = $pdo->prepare("SELECT g.*, c.status AS comment_status, c.deleted_at AS comment_deleted_at,
                c.created_at AS comment_created_at, c.updated_at AS comment_updated_at
            FROM topic_download_access_grants g
            INNER JOIN comments c ON c.id = g.comment_id
            WHERE g.topic_id = :topic_id
              AND g.user_id = :user_id
              AND g.revoked_at IS NULL
              AND (g.expires_at IS NULL OR g.expires_at > NOW())
              AND {$statusSql}
              {$deletedSql}
            ORDER BY g.granted_at DESC, g.id DESC
            LIMIT 1");
        $stmt->execute(['topic_id' => $topicId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['fn' => 'topicDownloadActiveAccessGrant', 'topic_id' => $topicId, 'user_id' => $userId]);
        }
        return null;
    }
}

function topicDownloadAccessState(?PDO $pdo, array $settings, int $topicId, int $userId = 0): array
{
    $mode = topicDownloadAccessMode($settings);
    $commentRequirement = topicDownloadCommentRequirement($settings);
    $loginMessage = topicDownloadSettingText(
        $settings,
        'download_access_login_message',
        'Önce giriş yapın, sonra bir yorum gönderin; kilit otomatik açılır.'
    );
    $commentMessage = topicDownloadSettingText(
        $settings,
        'download_access_comment_message',
        'Önce bir yorum gönderin; kilit otomatik açılır.'
    );
    $pendingMessage = topicDownloadSettingText(
        $settings,
        'download_access_pending_message',
        'Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.'
    );
    $expiredMessage = topicDownloadSettingText(
        $settings,
        'download_access_expired_message',
        'İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.'
    );

    $state = [
        'mode' => $mode,
        'comment_requirement' => $commentRequirement,
        'grant_mode' => topicDownloadGrantMode($settings),
        'locked' => false,
        'reason' => 'none',
        'message' => '',
        'has_comment' => false,
        'comment_status' => 'none',
        'requires_login' => false,
        'grant_id' => 0,
        'grant_comment_id' => 0,
        'granted_at' => null,
        'expires_at' => null,
        'access_until_text' => '',
        'is_exempt' => false,
    ];

    if ($topicId <= 0 || $mode === 'public') {
        return topicDownloadAccessFinalizeState($state);
    }

    if (topicDownloadIsUserExemptForScope($pdo, $settings, $userId, 'access_lock')) {
        $state['reason'] = 'download_exempt';
        $state['is_exempt'] = true;
        return topicDownloadAccessFinalizeState($state);
    }

    if ($userId <= 0) {
        $state['locked'] = true;
        $state['reason'] = 'auth_required';
        $state['message'] = $loginMessage !== '' ? $loginMessage : 'Bu icerigi gormek icin kayit olmaniz veya giris yapmaniz lazim.';
        $state['requires_login'] = true;
        return topicDownloadAccessFinalizeState($state);
    }

    if ($mode !== 'members_comment') {
        return topicDownloadAccessFinalizeState($state);
    }

    topicDownloadReconcileAccessGrant($pdo, $settings, $topicId, $userId);
    $grant = topicDownloadLatestAccessGrant($pdo, $topicId, $userId);
    if (is_array($grant)) {
        $commentStatus = strtolower(trim((string) ($grant['comment_status'] ?? '')));
        $commentDeleted = trim((string) ($grant['comment_deleted_at'] ?? '')) !== '';
        $relockOnDelete = (string) ($settings['download_access_relock_on_comment_delete'] ?? '1') === '1';
        $statusQualifies = $commentRequirement === 'approved'
            ? $commentStatus === 'approved'
            : in_array($commentStatus, ['approved', 'pending'], true);

        if (!$statusQualifies && trim((string) ($grant['revoked_at'] ?? '')) === '') {
            topicDownloadRevokeAccessGrant($pdo, (int) ($grant['comment_id'] ?? 0), 'comment_rejected');
            $grant = topicDownloadLatestAccessGrant($pdo, $topicId, $userId);
        } elseif ($commentDeleted && $relockOnDelete && trim((string) ($grant['revoked_at'] ?? '')) === '') {
            topicDownloadRevokeAccessGrant($pdo, (int) ($grant['comment_id'] ?? 0), 'comment_deleted');
            $grant = topicDownloadLatestAccessGrant($pdo, $topicId, $userId);
        } elseif (!$commentDeleted && (string) ($grant['revoke_reason'] ?? '') === 'comment_deleted') {
            topicDownloadRestoreAccessGrant($pdo, $settings, [
                'id' => (int) ($grant['comment_id'] ?? 0),
                'topic_id' => $topicId,
                'user_id' => $userId,
                'status' => $commentStatus,
                'deleted_at' => null,
                'created_at' => (string) ($grant['comment_created_at'] ?? ''),
                'updated_at' => (string) ($grant['comment_updated_at'] ?? ''),
            ]);
            $grant = topicDownloadLatestAccessGrant($pdo, $topicId, $userId);
        }
    }

    $activeGrant = topicDownloadActiveAccessGrant($pdo, $settings, $topicId, $userId);
    if (is_array($activeGrant)) {
        $expiresAt = trim((string) ($activeGrant['expires_at'] ?? '')) ?: null;
        $state['grant_id'] = (int) ($activeGrant['id'] ?? 0);
        $state['grant_comment_id'] = (int) ($activeGrant['comment_id'] ?? 0);
        $state['grant_mode'] = (string) ($activeGrant['grant_mode'] ?? topicDownloadGrantMode($settings));
        $state['granted_at'] = trim((string) ($activeGrant['granted_at'] ?? '')) ?: null;
        $state['expires_at'] = $expiresAt;
        $state['comment_status'] = strtolower(trim((string) ($activeGrant['comment_status'] ?? ''))) ?: 'none';
        $state['has_comment'] = true;
        $state['access_until_text'] = topicDownloadActiveUntilText($settings, $expiresAt);
        return topicDownloadAccessFinalizeState($state);
    }

    if (is_array($grant)) {
        $state['grant_id'] = (int) ($grant['id'] ?? 0);
        $state['grant_comment_id'] = (int) ($grant['comment_id'] ?? 0);
        $state['grant_mode'] = (string) ($grant['grant_mode'] ?? topicDownloadGrantMode($settings));
        $state['granted_at'] = trim((string) ($grant['granted_at'] ?? '')) ?: null;
        $state['expires_at'] = trim((string) ($grant['expires_at'] ?? '')) ?: null;
        $state['comment_status'] = strtolower(trim((string) ($grant['comment_status'] ?? ''))) ?: 'none';
    }

    $latestComment = topicDownloadLatestUserComment($pdo, $topicId, $userId);
    $latestCommentStatus = is_array($latestComment) && empty($latestComment['deleted_at'])
        ? strtolower(trim((string) ($latestComment['status'] ?? '')))
        : 'none';
    if ($commentRequirement === 'approved' && $latestCommentStatus === 'pending') {
        $state['locked'] = true;
        $state['reason'] = 'comment_pending';
        $state['message'] = $pendingMessage;
        $state['comment_status'] = 'pending';
        return topicDownloadAccessFinalizeState($state);
    }

    if (is_array($grant)) {
        $expiresAt = trim((string) ($grant['expires_at'] ?? ''));
        $expired = $expiresAt !== '' && (($expiresTimestamp = strtotime($expiresAt)) === false || $expiresTimestamp <= time());
        if (trim((string) ($grant['revoked_at'] ?? '')) === '' && $expired) {
            $state['locked'] = true;
            $state['reason'] = 'comment_expired';
            $state['message'] = $expiredMessage;
            return topicDownloadAccessFinalizeState($state);
        }
    }

    $state['locked'] = true;
    $state['reason'] = 'comment_required';
    $state['message'] = $commentMessage !== '' ? $commentMessage : 'Indirme linklerini gormek icin once yorum yapmaniz lazim.';
    return topicDownloadAccessFinalizeState($state);
}

function getTopicDownloadLinks(?PDO $pdo, int $topicId): array
{
    if (!$pdo || $topicId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, url, download_count
                               FROM topic_download_links
                               WHERE topic_id = ?
                               ORDER BY display_order ASC, id ASC");
        $stmt->execute([$topicId]);
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            return array_map(static function (array $row): array {
                return [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'url' => topicDownloadNormalizeUrl((string)$row['url']),
                    'download_count' => (int)($row['download_count'] ?? 0),
                ];
            }, $rows);
        }
    } catch (Throwable $e) {
        error_log('[silent-catch] ' . $e->getMessage());
    }

    return [];
}

function topicDownloadIntentTtlSeconds(): int
{
    return 1800;
}

function topicDownloadPruneAccessIntents(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $intents = $_SESSION['_topic_download_access_intents'] ?? [];
    if (!is_array($intents)) {
        $_SESSION['_topic_download_access_intents'] = [];
        return;
    }

    $now = time();
    $ttl = topicDownloadIntentTtlSeconds();
    foreach ($intents as $token => $intent) {
        if (
            !is_string($token) ||
            !is_array($intent) ||
            (int) ($intent['link_id'] ?? 0) <= 0 ||
            (int) ($intent['topic_id'] ?? 0) <= 0 ||
            (int) ($intent['created_at'] ?? 0) < ($now - $ttl)
        ) {
            unset($intents[$token]);
        }
    }

    if (count($intents) > 80) {
        uasort($intents, static function (array $a, array $b): int {
            return (int) ($b['created_at'] ?? 0) <=> (int) ($a['created_at'] ?? 0);
        });
        $intents = array_slice($intents, 0, 80, true);
    }

    $_SESSION['_topic_download_access_intents'] = $intents;
}

function topicDownloadCreateAccessIntent(int $linkId, int $topicId): string
{
    if ($linkId <= 0 || $topicId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    topicDownloadPruneAccessIntents();

    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $token = hash('sha256', uniqid((string) $linkId, true) . '|' . microtime(true));
    }

    $_SESSION['_topic_download_access_intents'][$token] = [
        'link_id' => $linkId,
        'topic_id' => $topicId,
        'created_at' => time(),
    ];

    return $token;
}

function topicDownloadBuildActionUrl(int $linkId, int $topicId): string
{
    $href = routePublicStaticUrl('download') . '?id=' . $linkId;
    $intent = topicDownloadCreateAccessIntent($linkId, $topicId);
    if ($intent !== '') {
        $href .= '&intent=' . rawurlencode($intent);
    }

    return $href;
}

function topicDownloadAccessIntentIsValid(int $linkId, int $topicId, string $token): bool
{
    if ($linkId <= 0 || $topicId <= 0 || $token === '' || session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    topicDownloadPruneAccessIntents();

    $intent = $_SESSION['_topic_download_access_intents'][$token] ?? null;
    if (!is_array($intent)) {
        return false;
    }

    return (int) ($intent['link_id'] ?? 0) === $linkId
        && (int) ($intent['topic_id'] ?? 0) === $topicId;
}

function topicDownloadConsumeAccessIntent(int $linkId, int $topicId, string $token): void
{
    if (!topicDownloadAccessIntentIsValid($linkId, $topicId, $token)) {
        return;
    }

    unset($_SESSION['_topic_download_access_intents'][$token]);
}

function syncTopicDownloadLinks(?PDO $pdo, int $topicId, string $rawLinks): void
{
    if (!$pdo || $topicId <= 0) {
        return;
    }

    $links = parseTopicDownloadLinksText($rawLinks);
    $existingCounts = [];

    $inTransaction = $pdo->inTransaction();
    if (!$inTransaction) {
        $pdo->beginTransaction();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT url, download_count FROM topic_download_links WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        foreach ($stmt->fetchAll() as $row) {
            $normalizedUrl = topicDownloadNormalizeUrl((string)$row['url']);
            if ($normalizedUrl === '') {
                continue;
            }
            $existingCounts[$normalizedUrl] = (int)($row['download_count'] ?? 0);
        }

        $pdo->prepare("DELETE FROM topic_download_links WHERE topic_id = ?")->execute([$topicId]);

        if (!empty($links)) {
            $insert = $pdo->prepare("INSERT INTO topic_download_links
                (topic_id, name, url, download_count, display_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

            foreach ($links as $index => $link) {
                $url = topicDownloadNormalizeUrl((string)$link['url']);
                if ($url === '') {
                    continue;
                }
                $insert->execute([
                    $topicId,
                    (string)$link['name'],
                    $url,
                    $existingCounts[$url] ?? 0,
                    $index,
                ]);
            }
        }

        if (!$inTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if (!$inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function incrementTopicDownloadLink(?PDO $pdo, int $linkId): ?array
{
    if (!$pdo || $linkId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, topic_id, name, url, download_count
                               FROM topic_download_links
                               WHERE id = ?
                               LIMIT 1");
        $stmt->execute([$linkId]);
        $link = $stmt->fetch();
        if (!$link) {
            return null;
        }

        $topicId = (int)($link['topic_id'] ?? 0);
        $clientKey = 'download_' . $topicId . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
        $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
        $currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        $countRateLimitExempt = topicDownloadIsUserExemptForScope($pdo, $settings, $currentUserId, 'count_rate_limit');
        $downloadCountRateLimit = max(1, (int)($settings['download_count_rate_limit'] ?? 1));
        $downloadCountRateWindow = max(1, (int)($settings['download_count_rate_window'] ?? 60));
        $shouldCount = $countRateLimitExempt || checkRateLimit($clientKey, $downloadCountRateLimit, $downloadCountRateWindow);

        if ($shouldCount) {
            $pdo->prepare("UPDATE topic_download_links SET download_count = download_count + 1, updated_at = NOW() WHERE id = ?")
                ->execute([$linkId]);
            $pdo->prepare("UPDATE topics SET download_count = download_count + 1 WHERE id = ?")
                ->execute([$topicId]);
            if (!$countRateLimitExempt) {
                incrementRateLimit($clientKey, $downloadCountRateWindow);
            }

            try {
                $pdo->prepare("INSERT INTO downloads (topic_id, user_id, ip_address, user_agent, created_at, updated_at)
                               VALUES (?, ?, ?, ?, NOW(), NOW())")
                    ->execute([
                        $topicId,
                        $currentUserId > 0 ? $currentUserId : null,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ]);
            } catch (Throwable $e) {
                appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId]);
            }

            // Update leaderboard stats for topic owner
            try {
                $topicStmt = $pdo->prepare("SELECT author_id FROM topics WHERE id = ? LIMIT 1");
                $topicStmt->execute([$topicId]);
                $topicOwnerId = (int)($topicStmt->fetchColumn() ?: 0);
                $downloadUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
                if ($downloadUserId > 0) {
                    if (is_file(dirname(__DIR__, 3) . '/Modules/Events/init.php')) {
                        require_once dirname(__DIR__, 3) . '/Modules/Events/init.php';
                    }
                    if (function_exists('eventsRecordActivity')) {
                        eventsRecordActivity($pdo, $downloadUserId, 'topic_downloaded', 'topic', $topicId, [
                            'subject_user_id' => $topicOwnerId,
                            'dedupe_key' => 'topic_downloaded:topic:' . $topicId . ':user:' . $downloadUserId . ':date:' . date('Y-m-d'),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId, 'context' => 'events_activity']);
            }

            $link['download_count'] = (int)($link['download_count'] ?? 0) + 1;
        }

        return $link;
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'incrementTopicDownloadLink', 'link_id' => $linkId]);
        return null;
    }
}
