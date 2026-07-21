<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationSuppressionLogService
{
    private const TABLE = 'notification_dispatch_suppression_logs';
    private const REASON_META = [
        'invalid_target' => ['label' => 'Geçersiz hedef', 'class' => 'failed', 'icon' => 'bi-exclamation-triangle'],
        'recipient_banned' => ['label' => 'Hedef banlı', 'class' => 'failed', 'icon' => 'bi-slash-circle'],
        'unknown_event' => ['label' => 'Bilinmeyen olay', 'class' => 'failed', 'icon' => 'bi-question-circle'],
        'admin_center_disabled' => ['label' => 'Merkez kapalı', 'class' => 'none', 'icon' => 'bi-power'],
        'admin_events_disabled' => ['label' => 'Olaylar kapalı', 'class' => 'none', 'icon' => 'bi-toggle-off'],
        'admin_event_disabled' => ['label' => 'Admin olayı kapatmış', 'class' => 'none', 'icon' => 'bi-shield-lock'],
        'self_actor_skipped' => ['label' => 'Kendi işlemi atlandı', 'class' => 'queued', 'icon' => 'bi-person-check'],
        'template_disabled' => ['label' => 'Bildirim metni kapalı', 'class' => 'none', 'icon' => 'bi-file-earmark-x'],
        'template_channels_disabled' => ['label' => 'Bildirim kanalı kapalı', 'class' => 'none', 'icon' => 'bi-diagram-2'],
        'email_channel_not_ready' => ['label' => 'E-posta kanalı hazır değil', 'class' => 'queued', 'icon' => 'bi-envelope-slash'],
        'email_copy_missing' => ['label' => 'E-posta metni eksik', 'class' => 'failed', 'icon' => 'bi-envelope-exclamation'],
        'user_preferences_disabled' => ['label' => 'Kullanıcı tercihi kapalı', 'class' => 'none', 'icon' => 'bi-person-gear'],
        'duplicate_dedupe' => ['label' => 'Tekrar engellendi', 'class' => 'queued', 'icon' => 'bi-intersect'],
        'insert_failed' => ['label' => 'Kayıt başarısız', 'class' => 'failed', 'icon' => 'bi-database-x'],
    ];
    private const DEFAULT_REASON_META = ['label' => 'Gönderim atlandı', 'class' => 'none', 'icon' => 'bi-bell-slash'];

    public function __construct(private ?NotificationSchemaService $schema = null)
    {
        $this->schema ??= new NotificationSchemaService();
    }

    /** @return array{label:string,class:string,icon:string} */
    public function reasonMeta(string $reasonKey): array
    {
        return self::REASON_META[$reasonKey] ?? self::DEFAULT_REASON_META;
    }

    /** @return array<string,array{label:string,class:string,icon:string}> */
    public function reasonOptions(): array
    {
        return self::REASON_META;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log(
        PDO $pdo,
        string $reasonKey,
        string $eventKey,
        int $recipientId,
        ?int $actorId,
        string $entityType,
        int $entityId,
        ?string $dedupeKey = null,
        ?string $templateKey = null,
        ?string $type = null,
        array $context = []
    ): void {
        try {
            if (!$this->schema->tableExists($pdo, self::TABLE)) {
                return;
            }
            $this->schema->ensureSuppressionLogSchema($pdo);
            $reasonKey = $this->normalizeKey($reasonKey, 'unknown');
            $eventKey = $this->normalizeKey($eventKey, 'unknown_event');
            $entityType = trim($entityType);
            $meta = $this->reasonMeta($reasonKey);
            $contextJson = $context !== []
                ? json_encode($this->safeContext($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            $stmt = $pdo->prepare('
                INSERT INTO notification_dispatch_suppression_logs
                    (event_key, reason_key, reason_label, recipient_user_id, actor_user_id, entity_type, entity_id, dedupe_key, template_key, type, context_json, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                mb_substr($eventKey, 0, 100),
                mb_substr($reasonKey, 0, 80),
                mb_substr((string) $meta['label'], 0, 160),
                $recipientId > 0 ? $recipientId : null,
                $actorId !== null && $actorId > 0 ? $actorId : null,
                $entityType !== '' ? mb_substr($entityType, 0, 50) : null,
                $entityId > 0 ? $entityId : null,
                $dedupeKey !== null && trim($dedupeKey) !== '' ? mb_substr(trim($dedupeKey), 0, 190) : null,
                $templateKey !== null && trim($templateKey) !== '' ? mb_substr(trim($templateKey), 0, 100) : null,
                $type !== null && trim($type) !== '' ? mb_substr(trim($type), 0, 50) : null,
                $contextJson,
            ]);
        } catch (Throwable $e) {
            error_log('Notification suppression log failed: ' . $e->getMessage());
        }
    }

    public function tableExists(PDO $pdo): bool
    {
        return $this->schema->tableExists($pdo, self::TABLE);
    }

    /** @return array{total:int,today:int,user_preferences:int,admin_policy:int,duplicates:int} */
    public function stats(PDO $pdo): array
    {
        $stats = [
            'total' => 0,
            'today' => 0,
            'user_preferences' => 0,
            'admin_policy' => 0,
            'duplicates' => 0,
        ];
        if (!$this->tableExists($pdo)) {
            return $stats;
        }

        try {
            $row = $pdo->query("
                SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END), 0) AS today_count,
                    COALESCE(SUM(CASE WHEN reason_key = 'user_preferences_disabled' THEN 1 ELSE 0 END), 0) AS user_preferences_count,
                    COALESCE(SUM(CASE WHEN reason_key IN ('admin_center_disabled', 'admin_events_disabled', 'admin_event_disabled') THEN 1 ELSE 0 END), 0) AS admin_policy_count,
                    COALESCE(SUM(CASE WHEN reason_key = 'duplicate_dedupe' THEN 1 ELSE 0 END), 0) AS duplicate_count
                FROM notification_dispatch_suppression_logs
            ")->fetch(PDO::FETCH_ASSOC) ?: [];

            $stats['total'] = (int) ($row['total_count'] ?? 0);
            $stats['today'] = (int) ($row['today_count'] ?? 0);
            $stats['user_preferences'] = (int) ($row['user_preferences_count'] ?? 0);
            $stats['admin_policy'] = (int) ($row['admin_policy_count'] ?? 0);
            $stats['duplicates'] = (int) ($row['duplicate_count'] ?? 0);
        } catch (Throwable $e) {
            error_log('Notification suppression stats failed: ' . $e->getMessage());
        }

        return $stats;
    }

    public function count(PDO $pdo, string $reasonKey = 'all'): int
    {
        if (!$this->tableExists($pdo)) {
            return 0;
        }

        try {
            $reasonKey = $this->filterReasonKey($reasonKey);
            if ($reasonKey === 'all') {
                return (int) $pdo->query('SELECT COUNT(*) FROM notification_dispatch_suppression_logs')->fetchColumn();
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notification_dispatch_suppression_logs WHERE reason_key = ?');
            $stmt->execute([$reasonKey]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification suppression count failed: ' . $e->getMessage());

            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    public function recent(PDO $pdo, int $limit = 10, string $reasonKey = 'all'): array
    {
        if (!$this->tableExists($pdo)) {
            return [];
        }

        try {
            $limit = max(1, min(50, $limit));
            $reasonKey = $this->filterReasonKey($reasonKey);
            $whereSql = $reasonKey === 'all' ? '' : 'WHERE l.reason_key = ?';
            $stmt = $pdo->prepare("
                SELECT
                    l.*,
                    target.username AS target_username,
                    actor.username AS actor_username
                FROM notification_dispatch_suppression_logs l
                LEFT JOIN users target ON target.id = l.recipient_user_id
                LEFT JOIN users actor ON actor.id = l.actor_user_id
                {$whereSql}
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT ?
            ");
            $bindIndex = 1;
            if ($reasonKey !== 'all') {
                $stmt->bindValue($bindIndex, $reasonKey, PDO::PARAM_STR);
                $bindIndex++;
            }
            $stmt->bindValue($bindIndex, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Notification suppression recent lookup failed: ' . $e->getMessage());

            return [];
        }
    }

    private function normalizeKey(string $value, string $fallback): string
    {
        $normalized = trim(strtolower($value));
        $normalized = (string) preg_replace('/[^a-z0-9_:-]+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function filterReasonKey(string $reasonKey): string
    {
        $reasonKey = $this->normalizeKey($reasonKey, 'all');

        return $reasonKey === 'all' || isset(self::REASON_META[$reasonKey]) ? $reasonKey : 'all';
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function safeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $safeKey = $this->normalizeKey((string) $key, 'context');
            if (is_scalar($value) || $value === null) {
                $safe[$safeKey] = is_string($value) ? mb_substr($value, 0, 500) : $value;
                continue;
            }
            if (is_array($value)) {
                $safe[$safeKey] = array_slice(array_map(static function (mixed $item): mixed {
                    return is_scalar($item) || $item === null ? $item : get_debug_type($item);
                }, $value), 0, 20);
                continue;
            }
            $safe[$safeKey] = get_debug_type($value);
        }

        return $safe;
    }
}
