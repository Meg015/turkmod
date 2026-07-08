<?php

declare(strict_types=1);

/**
 * Activity Logging Helper Functions
 * Track user actions for audit trail
 */

if (!function_exists('appLog')) {
    function appLog(
        ?PDO $pdo,
        string $level,
        string $channel,
        string $message,
        array $context = [],
    ): void {
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO application_logs (level, channel, message, context_json, ip_address, created_at)
                 VALUES (:level, :channel, :message, :context_json, :ip_address, NOW())",
            );
            $stmt->execute([
                'level' => $level,
                'channel' => $channel,
                'message' => $message,
                'context_json' => !empty($context)
                    ? json_encode($context, JSON_UNESCAPED_UNICODE)
                    : null,
                'ip_address' => function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->error('Application logging failed', [
                    'channel' => $channel,
                    'message' => $message,
                    'error' => $e->getMessage(),
                ]);
            } else {
                error_log('Application logging failed: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('publicContentCacheNamespace')) {
    function publicContentCacheNamespace(): string
    {
        return 'public-content-v1';
    }
}

if (!function_exists('publicContentCacheTag')) {
    function publicContentCacheTag(): string
    {
        return 'public-content';
    }
}

if (!function_exists('publicContentCacheTags')) {
    /**
     * @return array<int,string>
     */
    function publicContentCacheTags(): array
    {
        return [publicContentCacheTag()];
    }
}

if (!function_exists('publicContentCacheKey')) {
    function publicContentCacheKey(string $key): string
    {
        $key = trim($key);
        return publicContentCacheNamespace() . ':' . ($key !== '' ? $key : 'default');
    }
}

if (!function_exists('publicContentCacheActionShouldInvalidate')) {
    function publicContentCacheActionShouldInvalidate(string $action): bool
    {
        $action = trim($action);
        if ($action === '' || $action === 'topic_viewed') {
            return false;
        }

        static $actions = [
            'category_created',
            'category_updated',
            'category_deleted',
            'profile_updated',
            'avatar_updated',
            'profile_avatar_uploaded',
            'comment_created',
            'comment_updated',
            'comment_deleted',
            'comment_restored',
            'comment_approved',
            'comment_rejected',
            'admin_updated_user',
            'admin_user_updated',
            'settings_updated',
            'topic_approved',
            'topic_created',
            'topic_deleted',
            'topic_deleted_permanently',
            'topic_moderated',
            'topic_revision_restored',
            'topic_restored',
            'topic_settings_updated',
            'topic_updated',
            'topic_uploaded',
            'topic_user_edited',
            'bot_import_published',
        ];

        if (in_array($action, $actions, true)) {
            return true;
        }

        return str_starts_with($action, 'topic_bulk_');
    }
}

if (!function_exists('invalidatePublicContentCache')) {
    function invalidatePublicContentCache(): bool
    {
        try {
            if (!class_exists(\App\Core\Bootstrap\Boot::class)) {
                return false;
            }

            $cache = \App\Core\Bootstrap\Boot::container(dirname(__DIR__))->get(\App\Core\Cache\TaggableCache::class);
            if (!$cache instanceof \App\Core\Cache\TaggableCache) {
                return false;
            }

            return $cache->invalidateTag(publicContentCacheTag());
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'invalidatePublicContentCache']);
            }

            return false;
        }
    }
}

if (!function_exists('logActivity')) {
    /**
     * Kullanıcı aktivitesini logla
     */
    function logActivity(
        ?PDO $pdo,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = []
    ): void {
        if (!$pdo) {
            return;
        }

        try {
            $actorId = $_SESSION['_auth_user_id'] ?? null;
            $actorId = is_numeric($actorId) ? (int) $actorId : null;
            if ($actorId !== null && $actorId > 0) {
                static $activityActorExistsCache = [];
                if (!array_key_exists($actorId, $activityActorExistsCache)) {
                    try {
                        $actorCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                        $actorCheck->execute(['id' => $actorId]);
                        $activityActorExistsCache[$actorId] = (bool) $actorCheck->fetchColumn();
                    } catch (Throwable) {
                        $activityActorExistsCache[$actorId] = false;
                    }
                }

                if (!$activityActorExistsCache[$actorId]) {
                    $actorId = null;
                }
            } else {
                $actorId = null;
            }

            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (actor_id, subject_type, subject_id, action, properties, created_at)
                VALUES (:actor_id, :subject_type, :subject_id, :action, :properties, NOW())
            ");

            $stmt->execute([
                'actor_id' => $actorId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action' => $action,
                'properties' => !empty($properties)
                    ? json_encode($properties, JSON_UNESCAPED_UNICODE)
                    : null,
            ]);

            if (function_exists('appLog')) {
                appLog($pdo, 'info', 'activity', $action, [
                    'actor_id' => $actorId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'properties' => $properties,
                ]);
            }

            if (function_exists('userActivityLog')) {
                $actorIdInt = $actorId !== null ? (int) $actorId : null;
                $targetUserId = ($subjectType === 'user' && $subjectId !== null) ? (int) $subjectId : $actorIdInt;
                $group = 'activity';
                if (in_array($action, ['user_login', 'user_logout'], true)) {
                    $group = 'auth';
                } elseif (str_starts_with($action, 'topic_') || str_starts_with($action, 'comment_') || str_contains($action, 'favorite') || str_contains($action, 'uploaded')) {
                    $group = 'content';
                } elseif (str_contains($action, 'report') || str_contains($action, 'moderated')) {
                    $group = 'moderation';
                } elseif (str_starts_with($action, 'admin_') || str_contains($action, 'group_changed')) {
                    $group = 'admin';
                }

                userActivityLog(
                    $pdo,
                    $targetUserId,
                    $action,
                    $group,
                    $subjectType,
                    $subjectId,
                    function_exists('userActivityEventLabel') ? userActivityEventLabel($action) : $action,
                    $properties,
                    $actorIdInt
                );
            }

            if (publicContentCacheActionShouldInvalidate($action)) {
                invalidatePublicContentCache();
            }
        } catch (Throwable $e) {
            if (function_exists('appLog')) {
                appLog($pdo, 'error', 'activity', 'activity_log_failed', [
                    'action' => $action,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'error' => $e->getMessage(),
                ]);
            } elseif (class_exists('Logger')) {
                Logger::getInstance()->error('Activity logging failed', [
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            } else {
                error_log('Activity logging failed: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('getActivityLog')) {
    /**
     * Aktivite loglarını al
     */
    function getActivityLog(
        ?PDO $pdo,
        int $limit = 100,
        int $offset = 0,
        ?int $actorId = null
    ): array {
        if (!$pdo) {
            return [];
        }

        try {
            $query = "SELECT * FROM activity_logs WHERE 1=1";
            $params = [];

            if ($actorId !== null) {
                $query .= " AND actor_id = ?";
                $params[] = $actorId;
            }

            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->error('Activity log retrieval failed', [
                    'error' => $e->getMessage(),
                ]);
            } else {
                error_log('Activity log retrieval failed: ' . $e->getMessage());
            }
            return [];
        }
    }
}
