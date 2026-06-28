<?php

declare(strict_types=1);

/**
 * Detailed per-user activity tracking.
 *
 * The existing activity/security/admin logs are still useful, but this table
 * stores normalized request, device, actor and subject data for user tracking.
 */

if (!function_exists('userActivityIsSqlite')) {
    function userActivityIsSqlite(PDO $pdo): bool
    {
        try {
            return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('userActivityNowSql')) {
    function userActivityNowSql(PDO $pdo): string
    {
        return userActivityIsSqlite($pdo) ? "datetime('now')" : 'NOW()';
    }
}

if (!function_exists('userActivityEnsureSchema')) {
    function userActivityEnsureSchema(?PDO $pdo): void
    {
        if (!$pdo) {
            return;
        }
        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }

        static $initialized = [];
        $key = spl_object_id($pdo);
        if (!empty($initialized[$key])) {
            return;
        }

        if (userActivityIsSqlite($pdo)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                actor_user_id INTEGER NULL,
                event_type TEXT NOT NULL,
                event_group TEXT NOT NULL DEFAULT 'activity',
                subject_type TEXT NULL,
                subject_id INTEGER NULL,
                title TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                device_type TEXT NULL,
                browser TEXT NULL,
                platform TEXT NULL,
                request_method TEXT NULL,
                request_path TEXT NULL,
                session_id_hash TEXT NULL,
                metadata_json TEXT NULL,
                created_at TEXT NULL
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS user_activity_events_user_created ON user_activity_events (user_id, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS user_activity_events_actor_created ON user_activity_events (actor_user_id, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS user_activity_events_type_created ON user_activity_events (event_type, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS user_activity_events_group_created ON user_activity_events (event_group, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS user_activity_events_ip_created ON user_activity_events (ip_address, created_at)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(80) NOT NULL,
                event_group VARCHAR(40) NOT NULL DEFAULT 'activity',
                subject_type VARCHAR(80) NULL,
                subject_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                device_type VARCHAR(40) NULL,
                browser VARCHAR(80) NULL,
                platform VARCHAR(80) NULL,
                request_method VARCHAR(12) NULL,
                request_path VARCHAR(255) NULL,
                session_id_hash VARCHAR(64) NULL,
                metadata_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL CHECK (json_valid(metadata_json)),
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_activity_events_user_created (user_id, created_at),
                KEY user_activity_events_actor_created (actor_user_id, created_at),
                KEY user_activity_events_type_created (event_type, created_at),
                KEY user_activity_events_group_created (event_group, created_at),
                KEY user_activity_events_subject (subject_type, subject_id),
                KEY user_activity_events_ip_created (ip_address, created_at),
                KEY user_activity_events_created (created_at),
                CONSTRAINT user_activity_events_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT user_activity_events_actor_foreign FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $initialized[$key] = true;
    }
}

if (!function_exists('userActivityParseDevice')) {
    function userActivityParseDevice(string $userAgent): array
    {
        $ua = strtolower($userAgent);
        $device = 'desktop';
        if (str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider')) {
            $device = 'bot';
        } elseif (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $device = 'tablet';
        } elseif (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            $device = 'mobile';
        }

        $browser = 'Unknown';
        if (str_contains($ua, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'chrome/') || str_contains($ua, 'chromium/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari/')) {
            $browser = 'Safari';
        }

        $platform = 'Unknown';
        if (str_contains($ua, 'windows')) {
            $platform = 'Windows';
        } elseif (str_contains($ua, 'android')) {
            $platform = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
            $platform = 'iOS';
        } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
            $platform = 'macOS';
        } elseif (str_contains($ua, 'linux')) {
            $platform = 'Linux';
        }

        return [
            'device_type' => $device,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }
}

if (!function_exists('userActivityRequestSnapshot')) {
    function userActivityRequestSnapshot(): array
    {
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
        $device = userActivityParseDevice($userAgent);
        $sessionHash = null;
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $sessionHash = hash('sha256', session_id());
        }

        return [
            'ip_address' => function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_type' => $device['device_type'],
            'browser' => $device['browser'],
            'platform' => $device['platform'],
            'request_method' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 12) ?: null,
            'request_path' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255) ?: null,
            'session_id_hash' => $sessionHash,
        ];
    }
}

if (!function_exists('userActivityAllKnownEvents')) {
    function userActivityAllKnownEvents(): array
    {
        return [
            'user_registered' => 'Kayıt Olundu',
            'user_login' => 'Giriş Yapıldı',
            'user_login_failed' => 'Giriş Başarısız',
            'user_logout' => 'Çıkış Yapıldı',
            'password_reset_requested' => 'Şifre Sıfırlama İstendi',
            'password_reset_completed' => 'Şifre Sıfırlandı',
            'email_verified' => 'E-posta Doğrulandı',
            
            'profile_updated' => 'Profil Güncellendi',
            'password_changed' => 'Şifre Değiştirildi',
            'avatar_updated' => 'Avatar Güncellendi',
            
            'topic_created' => 'Konu Oluşturuldu',
            'topic_updated' => 'Konu Güncellendi',
            'topic_deleted' => 'Konu Silindi',
            'topic_uploaded' => 'Konu/Mod Yüklendi',
            'topic_resubmitted' => 'Konu Tekrar Gönderildi',
            'topic_viewed' => 'Konu Görüntülendi',
            'topic_favorite_added' => 'Favoriye Eklendi',
            'topic_favorite_removed' => 'Favoriden Çıkarıldı',
            'topic_downloaded' => 'Dosya İndirildi',
            'download_link_checked' => 'İndirme Linki Kontrol Edildi',
            'Download Link Checked' => 'İndirme Linki Kontrol Edildi',
            'download_link_clicked' => 'İndirme Linkine Tıklandı',
            'topic_liked' => 'Konu Beğenildi',
            
            'comment_created' => 'Yorum Yapıldı',
            'comment_updated' => 'Yorum Düzenlendi',
            'comment_deleted' => 'Yorum Silindi',
            'comment_reported' => 'Yorum Şikayet Edildi',
            'comment_liked' => 'Yorum Beğenildi',
            
            'user_reported' => 'Kullanıcı Şikayet Edildi',
            'user_followed' => 'Kullanıcı Takip Edildi',
            'user_unfollowed' => 'Kullanıcı Takipten Çıkıldı',
            
            'user_banned' => 'Kullanıcı Banlandı',
            'user_unbanned' => 'Ban Kaldırıldı',
            'user_restricted' => 'Kısıtlama Eklendi',
            'user_restriction_removed' => 'Kısıtlama Kaldırıldı',
            'user_restrictions_cleared' => 'Tüm Kısıtlamalar Kaldırıldı',
            'user_group_changed' => 'Grup Değişti',
            'user_status_changed' => 'Durum Değişti',
            'user_admin_note_added' => 'Admin Notu Eklendi',
            'ban_appeal_created' => 'Ban İtirazı Oluşturuldu',
            'ban_appeal_updated' => 'Ban İtirazı Güncellendi',
            'ban_appeal_message_added' => 'İtiraz Mesajı Eklendi',
            'admin_user_updated' => 'Admin Kullanıcıyı Düzenledi',
        ];
    }
}

if (!function_exists('userActivityEventLabel')) {
    function userActivityEventLabel(string $eventType): string
    {
        $labels = userActivityAllKnownEvents();
        return $labels[$eventType] ?? ucwords(str_replace('_', ' ', $eventType));
    }
}

if (!function_exists('userActivityGroupLabels')) {
    function userActivityGroupLabels(): array
    {
        return [
            'activity' => 'Aktivite',
            'auth' => 'Giris',
            'security' => 'Guvenlik',
            'content' => 'Icerik',
            'moderation' => 'Moderasyon',
            'admin' => 'Admin',
            'appeal' => 'Itiraz',
            'note' => 'Not',
        ];
    }
}

if (!function_exists('userActivityLog')) {
    function userActivityLog(
        ?PDO $pdo,
        ?int $userId,
        string $eventType,
        string $eventGroup = 'activity',
        ?string $subjectType = null,
        ?int $subjectId = null,
        string $title = '',
        array $metadata = [],
        ?int $actorUserId = null
    ): int {
        if (!$pdo || $eventType === '') {
            return 0;
        }

        try {
            userActivityEnsureSchema($pdo);
            $snapshot = userActivityRequestSnapshot();
            $actorUserId = $actorUserId ?? (isset($_SESSION['_auth_user_id']) ? (int) $_SESSION['_auth_user_id'] : null);
            if ($userId !== null && $userId <= 0) {
                $userId = null;
            }
            if ($actorUserId !== null && $actorUserId <= 0) {
                $actorUserId = null;
            }
            if ($title === '') {
                $title = userActivityEventLabel($eventType);
            }

            $stmt = $pdo->prepare("INSERT INTO user_activity_events
                (user_id, actor_user_id, event_type, event_group, subject_type, subject_id, title, ip_address, user_agent, device_type, browser, platform, request_method, request_path, session_id_hash, metadata_json, created_at)
                VALUES (:user_id, :actor_user_id, :event_type, :event_group, :subject_type, :subject_id, :title, :ip_address, :user_agent, :device_type, :browser, :platform, :request_method, :request_path, :session_id_hash, :metadata_json, " . userActivityNowSql($pdo) . ")");
            $stmt->execute([
                'user_id' => $userId,
                'actor_user_id' => $actorUserId,
                'event_type' => substr($eventType, 0, 80),
                'event_group' => substr($eventGroup !== '' ? $eventGroup : 'activity', 0, 40),
                'subject_type' => $subjectType !== null ? substr($subjectType, 0, 80) : null,
                'subject_id' => $subjectId,
                'title' => mb_substr($title, 0, 255),
                'ip_address' => $snapshot['ip_address'],
                'user_agent' => $snapshot['user_agent'],
                'device_type' => $snapshot['device_type'],
                'browser' => $snapshot['browser'],
                'platform' => $snapshot['platform'],
                'request_method' => $snapshot['request_method'],
                'request_path' => $snapshot['request_path'],
                'session_id_hash' => $snapshot['session_id_hash'],
                'metadata_json' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'userActivityLog', 'event_type' => $eventType]);
            }
            return 0;
        }
    }
}

if (!function_exists('userActivityBuildFilters')) {
    function userActivityBuildFilters(array $filters, string $alias = 'e'): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "{$alias}.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['actor_user_id'])) {
            $where[] = "{$alias}.actor_user_id = :actor_user_id";
            $params['actor_user_id'] = (int) $filters['actor_user_id'];
        }
        if (!empty($filters['event_group'])) {
            $where[] = "{$alias}.event_group = :event_group";
            $params['event_group'] = (string) $filters['event_group'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = "{$alias}.event_type = :event_type";
            $params['event_type'] = (string) $filters['event_type'];
        }
        if (!empty($filters['ip_address'])) {
            $where[] = "{$alias}.ip_address LIKE :ip_address";
            $params['ip_address'] = '%' . (string) $filters['ip_address'] . '%';
        }
        if (!empty($filters['device_type'])) {
            $where[] = "{$alias}.device_type = :device_type";
            $params['device_type'] = (string) $filters['device_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "{$alias}.created_at >= :date_from";
            $params['date_from'] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "{$alias}.created_at < :date_to";
            $params['date_to'] = date('Y-m-d H:i:s', strtotime((string) $filters['date_to'] . ' +1 day'));
        }

        return [implode(' AND ', $where), $params];
    }
}

if (!function_exists('userActivityList')) {
    function userActivityList(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            userActivityEnsureSchema($pdo);
            [$where, $params] = userActivityBuildFilters($filters, 'e');

            $q = trim((string) ($filters['q'] ?? ''));
            if ($q !== '') {
                $where .= " AND (u.name LIKE :q_name OR u.email LIKE :q_email OR actor.name LIKE :q_actor OR e.ip_address LIKE :q_ip";
                $params['q_name'] = '%' . $q . '%';
                $params['q_email'] = '%' . $q . '%';
                $params['q_actor'] = '%' . $q . '%';
                $params['q_ip'] = '%' . $q . '%';
                if (ctype_digit($q)) {
                    $where .= " OR e.user_id = :q_id OR e.actor_user_id = :q_actor_id";
                    $params['q_id'] = (int) $q;
                    $params['q_actor_id'] = (int) $q;
                }
                $where .= ')';
            }

            $stmt = $pdo->prepare("SELECT e.*, u.name AS user_name, u.email AS user_email, actor.name AS actor_name, actor.email AS actor_email
                FROM user_activity_events e
                LEFT JOIN users u ON u.id = e.user_id
                LEFT JOIN users actor ON actor.id = e.actor_user_id
                WHERE {$where}
                ORDER BY e.created_at DESC, e.id DESC
                LIMIT :limit OFFSET :offset");
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', max(1, min(250, $limit)), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'userActivityList']);
            }
            return [];
        }
    }
}

if (!function_exists('userActivityCount')) {
    function userActivityCount(PDO $pdo, array $filters = []): int
    {
        try {
            userActivityEnsureSchema($pdo);
            [$where, $params] = userActivityBuildFilters($filters, 'e');

            $q = trim((string) ($filters['q'] ?? ''));
            if ($q !== '') {
                $where .= " AND (u.name LIKE :q_name OR u.email LIKE :q_email OR actor.name LIKE :q_actor OR e.ip_address LIKE :q_ip";
                $params['q_name'] = '%' . $q . '%';
                $params['q_email'] = '%' . $q . '%';
                $params['q_actor'] = '%' . $q . '%';
                $params['q_ip'] = '%' . $q . '%';
                if (ctype_digit($q)) {
                    $where .= " OR e.user_id = :q_id OR e.actor_user_id = :q_actor_id";
                    $params['q_id'] = (int) $q;
                    $params['q_actor_id'] = (int) $q;
                }
                $where .= ')';
            }

            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM user_activity_events e
                LEFT JOIN users u ON u.id = e.user_id
                LEFT JOIN users actor ON actor.id = e.actor_user_id
                WHERE {$where}");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('userActivityStats')) {
    function userActivityStats(PDO $pdo, array $filters = []): array
    {
        try {
            userActivityEnsureSchema($pdo);
            [$where, $params] = userActivityBuildFilters($filters, 'e');

            $stmt = $pdo->prepare("SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN e.event_group = 'auth' THEN 1 ELSE 0 END) AS auth_total,
                    SUM(CASE WHEN e.event_group = 'security' THEN 1 ELSE 0 END) AS security_total,
                    SUM(CASE WHEN e.event_group = 'content' THEN 1 ELSE 0 END) AS content_total,
                    SUM(CASE WHEN e.event_group IN ('admin','moderation') THEN 1 ELSE 0 END) AS admin_total,
                    COUNT(DISTINCT e.ip_address) AS unique_ips,
                    COUNT(DISTINCT e.session_id_hash) AS unique_sessions,
                    MAX(e.created_at) AS last_seen_at
                FROM user_activity_events e
                WHERE {$where}");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total' => (int) ($row['total'] ?? 0),
                'auth_total' => (int) ($row['auth_total'] ?? 0),
                'security_total' => (int) ($row['security_total'] ?? 0),
                'content_total' => (int) ($row['content_total'] ?? 0),
                'admin_total' => (int) ($row['admin_total'] ?? 0),
                'unique_ips' => (int) ($row['unique_ips'] ?? 0),
                'unique_sessions' => (int) ($row['unique_sessions'] ?? 0),
                'last_seen_at' => $row['last_seen_at'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'total' => 0,
                'auth_total' => 0,
                'security_total' => 0,
                'content_total' => 0,
                'admin_total' => 0,
                'unique_ips' => 0,
                'unique_sessions' => 0,
                'last_seen_at' => null,
            ];
        }
    }
}

if (!function_exists('userActivitySecuritySummary')) {
    function userActivitySecuritySummary(PDO $pdo, array $filters = [], int $limit = 12): array
    {
        try {
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['user_id'])) {
                $where[] = 'se.user_id = :user_id';
                $params['user_id'] = (int) $filters['user_id'];
            }
            if (!empty($filters['ip_address'])) {
                $where[] = 'se.ip_address LIKE :ip';
                $params['ip'] = '%' . (string) $filters['ip_address'] . '%';
            }
            if (!empty($filters['date_from'])) {
                $where[] = 'se.created_at >= :date_from';
                $params['date_from'] = (string) $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = 'se.created_at < :date_to';
                $params['date_to'] = date('Y-m-d H:i:s', strtotime((string) $filters['date_to'] . ' +1 day'));
            }

            $stmt = $pdo->prepare("SELECT se.*, u.name AS user_name, u.email AS user_email
                FROM security_events se
                LEFT JOIN users u ON u.id = se.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY se.created_at DESC, se.id DESC
                LIMIT :limit");
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('userActivityContentSummary')) {
    function userActivityContentSummary(PDO $pdo, int $userId): array
    {
        $summary = [
            'topics' => [],
            'comments' => [],
            'reports_made' => [],
            'reports_about' => [],
            'counts' => [
                'topics' => 0,
                'comments' => 0,
                'reports_made' => 0,
                'reports_about' => 0,
            ],
        ];

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM topics WHERE author_id = :uid AND deleted_at IS NULL) AS topics_count,
                    (SELECT COUNT(*) FROM comments WHERE user_id = :uid AND deleted_at IS NULL) AS comments_count,
                    (SELECT COUNT(*) FROM user_reports WHERE reporter_user_id = :uid) AS reports_made_count,
                    (SELECT COUNT(*) FROM user_reports WHERE reported_user_id = :uid) AS reports_about_count
            ");
            $stmt->execute(['uid' => $userId]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($counts) {
                $summary['counts']['topics'] = (int)$counts['topics_count'];
                $summary['counts']['comments'] = (int)$counts['comments_count'];
                $summary['counts']['reports_made'] = (int)$counts['reports_made_count'];
                $summary['counts']['reports_about'] = (int)$counts['reports_about_count'];
            }
        } catch (Throwable $e) {
            error_log('[silent-catch] ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare("SELECT id, title, slug, status, created_at FROM topics WHERE author_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([$userId]);
            $summary['topics'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            $stmt = $pdo->prepare("SELECT c.id, c.topic_id, c.body, c.status, c.created_at, t.title AS topic_title
                FROM comments c
                LEFT JOIN topics t ON t.id = c.topic_id
                WHERE c.user_id = ? AND c.deleted_at IS NULL
                ORDER BY c.created_at DESC LIMIT 8");
            $stmt->execute([$userId]);
            $summary['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            $stmt = $pdo->prepare("SELECT id, reported_user_id, reason, status, created_at FROM user_reports WHERE reporter_user_id = ? ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([$userId]);
            $summary['reports_made'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            $stmt = $pdo->prepare("SELECT id, reporter_user_id, reason, status, created_at FROM user_reports WHERE reported_user_id = ? ORDER BY created_at DESC LIMIT 8");
            $stmt->execute([$userId]);
            $summary['reports_about'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        return $summary;
    }
}

if (!function_exists('userActivityAdminActions')) {
    function userActivityAdminActions(PDO $pdo, array $filters = [], int $limit = 20): array
    {
        if (!function_exists('adminGetActionLog')) {
            return [];
        }

        $auditFilters = [];
        if (!empty($filters['user_id'])) {
            $auditFilters['target_type'] = 'user';
            $auditFilters['target_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['actor_user_id'])) {
            $auditFilters['actor_id'] = (int) $filters['actor_user_id'];
        }
        if (!empty($filters['date_from'])) {
            $auditFilters['date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $auditFilters['date_to'] = (string) $filters['date_to'];
        }

        return adminGetActionLog($pdo, $auditFilters, $limit, 0);
    }
}
