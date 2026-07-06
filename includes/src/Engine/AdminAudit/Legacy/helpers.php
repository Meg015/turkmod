<?php

declare(strict_types=1);

use App\Engine\AdminAudit\AuditLogger;

/**
 * Admin Action Audit Log
 *
 * Kullanıcı üzerinde yapılan kritik admin eylemlerini (grup/ban/durum/kısıt/silme)
 * tek bir merkezi tabloda toplar. Eski/yeni değer + gerekçe saklanır; tersine
 * çevrilebilir eylemler için "Geri Al" (undo) desteklenir.
 *
 * Tasarım notları:
 * - ensureAdminActionLogTable, reports/helpers.php'deki ensure*Table desenini izler.
 * - adminRevertAction, topicRevisionRestore desenini izler: önce mevcut durumu
 *   yedekler (undo'yu da loglar), sonra eski değere geri yazar.
 */

if (!function_exists('adminAuditIsSqlite')) {
    function adminAuditIsSqlite(PDO $pdo): bool
    {
        try {
            return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ensureAdminActionLogTable')) {
    function ensureAdminActionLogTable(?PDO $pdo): void
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

        if (adminAuditIsSqlite($pdo)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_action_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_id INTEGER,
                action_type TEXT NOT NULL,
                target_type TEXT NOT NULL DEFAULT 'user',
                target_id INTEGER NOT NULL,
                reason TEXT,
                old_value TEXT,
                new_value TEXT,
                is_reversible INTEGER NOT NULL DEFAULT 0,
                reverted_at TEXT,
                reverted_by INTEGER,
                ip_address TEXT,
                created_at TEXT
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS admin_action_log_target_index ON admin_action_log (target_type, target_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS admin_action_log_created_index ON admin_action_log (created_at)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_action_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_id BIGINT UNSIGNED NULL,
                action_type VARCHAR(40) NOT NULL,
                target_type VARCHAR(40) NOT NULL DEFAULT 'user',
                target_id BIGINT UNSIGNED NOT NULL,
                reason TEXT NULL,
                old_value LONGTEXT NULL,
                new_value LONGTEXT NULL,
                is_reversible TINYINT(1) NOT NULL DEFAULT 0,
                reverted_at TIMESTAMP NULL,
                reverted_by BIGINT UNSIGNED NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP NULL,
                INDEX admin_action_log_target_index (target_type, target_id),
                INDEX admin_action_log_created_index (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $initialized[$key] = true;
    }
}

if (!function_exists('adminAuditLogger')) {
    function adminAuditLogger(): AuditLogger
    {
        static $logger = null;
        if (!$logger instanceof AuditLogger) {
            $logger = new AuditLogger();
        }

        return $logger;
    }
}

if (!function_exists('adminLogAction')) {
    /**
     * Bir admin eylemini audit tablosuna kaydeder.
     *
     * @param string $actionType group_change|ban|unban|status_change|restrict|delete
     * @param array  $oldValue   ['group_ids' => [2]] gibi, undo için kullanılır
     * @param array  $newValue   ['group_id' => 3] gibi
     * @return int   oluşturulan log id'si (0 = başarısız)
     */
    function adminLogAction(
        PDO $pdo,
        string $actionType,
        string $targetType,
        int $targetId,
        string $reason,
        array $oldValue = [],
        array $newValue = [],
        bool $reversible = false
    ): int {
        try {
            ensureAdminActionLogTable($pdo);

            $actorId = isset($_SESSION['_auth_user_id']) ? (int) $_SESSION['_auth_user_id'] : null;
            $ip = function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? null);

            $stmt = $pdo->prepare("INSERT INTO admin_action_log
                (actor_id, action_type, target_type, target_id, reason, old_value, new_value, is_reversible, ip_address, created_at)
                VALUES (:actor_id, :action_type, :target_type, :target_id, :reason, :old_value, :new_value, :reversible, :ip, " . adminAuditNow($pdo) . ")");
            $stmt->execute([
                'actor_id'    => $actorId,
                'action_type' => $actionType,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'reason'      => $reason !== '' ? $reason : null,
                'old_value'   => $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
                'new_value'   => $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
                'reversible'  => $reversible ? 1 : 0,
                'ip'          => $ip,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'adminLogAction', 'action_type' => $actionType]);
            }
            return 0;
        }
    }
}

if (!function_exists('adminAuditNow')) {
    /** Sürücüye göre NOW() ifadesi döndürür. */
    function adminAuditNow(PDO $pdo): string
    {
        return adminAuditIsSqlite($pdo) ? "datetime('now')" : "NOW()";
    }
}

if (!function_exists('adminGetActionLog')) {
    /**
     * Audit loglarını filtreleyerek getirir. Aktör ve hedef kullanıcı adlarını JOIN eder.
     *
     * @param array $filters ['target_type'=>'user','target_id'=>5,'action_type'=>'ban']
     */
    function adminGetActionLog(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            ensureAdminActionLogTable($pdo);

            $where = ['1=1'];
            $params = [];

            if (!empty($filters['target_type'])) {
                $where[] = 'l.target_type = :target_type';
                $params['target_type'] = (string) $filters['target_type'];
            }
            if (isset($filters['target_id'])) {
                $where[] = 'l.target_id = :target_id';
                $params['target_id'] = (int) $filters['target_id'];
            }
            if (!empty($filters['action_type'])) {
                $where[] = 'l.action_type = :action_type';
                $params['action_type'] = (string) $filters['action_type'];
            }
            if (isset($filters['actor_id'])) {
                $where[] = 'l.actor_id = :actor_id';
                $params['actor_id'] = (int) $filters['actor_id'];
            }
            if (!empty($filters['state'])) {
                if ($filters['state'] === 'reverted') {
                    $where[] = 'l.reverted_at IS NOT NULL';
                } elseif ($filters['state'] === 'active') {
                    $where[] = 'l.reverted_at IS NULL';
                } elseif ($filters['state'] === 'reversible') {
                    $where[] = 'l.is_reversible = 1 AND l.reverted_at IS NULL';
                }
            }
            if (!empty($filters['date_from'])) {
                $where[] = 'l.created_at >= :date_from';
                $params['date_from'] = (string) $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = 'l.created_at < :date_to';
                $params['date_to'] = date('Y-m-d H:i:s', strtotime((string) $filters['date_to'] . ' +1 day'));
            }

            $sql = "SELECT l.*,
                           actor.name AS actor_name,
                           target.name AS target_name
                    FROM admin_action_log l
                    LEFT JOIN users actor ON actor.id = l.actor_id
                    LEFT JOIN users target ON target.id = l.target_id AND l.target_type = 'user'
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY l.created_at DESC, l.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'adminGetActionLog']);
            }
            return [];
        }
    }
}

if (!function_exists('adminCountActionLog')) {
    function adminCountActionLog(PDO $pdo, array $filters = []): int
    {
        try {
            ensureAdminActionLogTable($pdo);
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['target_type'])) {
                $where[] = 'target_type = :target_type';
                $params['target_type'] = (string) $filters['target_type'];
            }
            if (isset($filters['target_id'])) {
                $where[] = 'target_id = :target_id';
                $params['target_id'] = (int) $filters['target_id'];
            }
            if (!empty($filters['action_type'])) {
                $where[] = 'action_type = :action_type';
                $params['action_type'] = (string) $filters['action_type'];
            }
            if (isset($filters['actor_id'])) {
                $where[] = 'actor_id = :actor_id';
                $params['actor_id'] = (int) $filters['actor_id'];
            }
            if (!empty($filters['state'])) {
                if ($filters['state'] === 'reverted') {
                    $where[] = 'reverted_at IS NOT NULL';
                } elseif ($filters['state'] === 'active') {
                    $where[] = 'reverted_at IS NULL';
                } elseif ($filters['state'] === 'reversible') {
                    $where[] = 'is_reversible = 1 AND reverted_at IS NULL';
                }
            }
            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= :date_from';
                $params['date_from'] = (string) $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = 'created_at < :date_to';
                $params['date_to'] = date('Y-m-d H:i:s', strtotime((string) $filters['date_to'] . ' +1 day'));
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_action_log WHERE " . implode(' AND ', $where));
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('adminRevertAction')) {
    /**
     * Tersine çevrilebilir bir admin eylemini geri alır.
     * topicRevisionRestore deseni: önce mevcut durumu yedekle (undo'yu da logla),
     * sonra eski değere geri yaz, log satırını reverted olarak damgala.
     *
     * @return string '' = başarı, aksi halde hata mesajı
     */
    function adminRevertAction(PDO $pdo, int $logId, int $actorId): string
    {
        try {
            ensureAdminActionLogTable($pdo);

            $stmt = $pdo->prepare("SELECT * FROM admin_action_log WHERE id = ? LIMIT 1");
            $stmt->execute([$logId]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                return 'Kayıt bulunamadı.';
            }
            if ((int) $log['is_reversible'] !== 1) {
                return 'Bu eylem geri alınamaz.';
            }
            if (!empty($log['reverted_at'])) {
                return 'Bu eylem zaten geri alınmış.';
            }
            if ((string) $log['target_type'] !== 'user') {
                return 'Bu hedef türü için geri alma desteklenmiyor.';
            }

            $old = json_decode((string) ($log['old_value'] ?? ''), true) ?: [];
            $targetId = (int) $log['target_id'];
            $actionType = (string) $log['action_type'];

            $applied = false;

            switch ($actionType) {
                case 'group_change':
                    $groupIds = array_values(array_filter(array_map('intval', (array)($old['group_ids'] ?? [])), static fn(int $id): bool => $id > 0));
                    if ($groupIds && function_exists('usersSyncUserGroups')) {
                        $err = usersSyncUserGroups($pdo, $targetId, $groupIds, $actorId, 'admin_action_revert_group_change');
                        if ($err !== '') {
                            return $err;
                        }
                        $applied = true;
                    }
                    break;

                case 'status_change':
                    if (isset($old['status'])) {
                        $pdo->prepare("UPDATE users SET status = :s, updated_at = " . adminAuditNow($pdo) . " WHERE id = :id")
                            ->execute(['s' => (string) $old['status'], 'id' => $targetId]);
                        $applied = true;
                    }
                    break;

                case 'ban':
                    // ban'i geri al = unban
                    if (function_exists('usersUnban')) {
                        usersUnban($pdo, $targetId);
                    } else {
                        $pdo->prepare("UPDATE users SET is_banned = 0, banned_at = NULL, ban_reason = NULL, updated_at = " . adminAuditNow($pdo) . " WHERE id = :id")
                            ->execute(['id' => $targetId]);
                    }
                    $applied = true;
                    break;

                case 'unban':
                    // unban'i geri al = tekrar banla (eski ban nedeniyle)
                    $oldReason = (string) ($old['ban_reason'] ?? '');
                    if (function_exists('usersBan')) {
                        usersBan($pdo, $targetId, $oldReason);
                    } else {
                        $pdo->prepare("UPDATE users SET is_banned = 1, banned_at = " . adminAuditNow($pdo) . ", ban_reason = :reason, updated_at = " . adminAuditNow($pdo) . " WHERE id = :id")
                            ->execute(['reason' => $oldReason, 'id' => $targetId]);
                    }
                    $applied = true;
                    break;

                default:
                    return 'Bu eylem türü geri alınamaz.';
            }

            if (!$applied) {
                return 'Geri almak için yeterli veri yok.';
            }

            // Log satırını reverted olarak damgala
            $pdo->prepare("UPDATE admin_action_log SET reverted_at = " . adminAuditNow($pdo) . ", reverted_by = :by WHERE id = :id")
                ->execute(['by' => $actorId, 'id' => $logId]);

            // Undo'nun kendisini de yeni bir log satırı olarak kaydet (izlenebilirlik)
            adminLogAction(
                $pdo,
                $actionType . '_revert',
                'user',
                $targetId,
                'Geri alma (#' . $logId . ')',
                json_decode((string) ($log['new_value'] ?? ''), true) ?: [],
                $old,
                false
            );

            if (function_exists('logActivity')) {
                logActivity($pdo, 'admin_action_reverted', 'user', $targetId, [
                    'log_id' => $logId,
                    'action_type' => $actionType,
                ]);
            }

            return '';
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'adminRevertAction', 'log_id' => $logId]);
            }
            return 'Geri alma sırasında hata oluştu.';
        }
    }
}

if (!function_exists('adminActionLabel')) {
    /** action_type için Türkçe etiket. */
    function adminActionLabel(string $actionType): string
    {
        $map = [
            'group_change'         => 'Grup Değişimi',
            'group_change_revert'  => 'Grup Değişimi (Geri Alındı)',
            'status_change'        => 'Durum Değişimi',
            'status_change_revert' => 'Durum Değişimi (Geri Alındı)',
            'ban'                  => 'Yasaklama',
            'ban_revert'           => 'Yasaklama (Geri Alındı)',
            'unban'                => 'Yasak Kaldırma',
            'unban_revert'         => 'Yasak Kaldırma (Geri Alındı)',
            'restrict'             => 'Kısıtlama',
            'delete'               => 'Silme',
            'activity_logs_cleared' => 'Aktivite Logları Temizlendi',
            'application_logs_cleared' => 'Uygulama Loglari Temizlendi',
            'rate_limit_records_deleted' => 'Rate Limit Kayıtları Temizlendi',
        ];
        return $map[$actionType] ?? $actionType;
    }
}

