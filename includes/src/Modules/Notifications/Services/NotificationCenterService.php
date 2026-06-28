<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationCenterService
{
    public function __construct(
        private ?NotificationPreferenceService $preferences = null,
        private ?NotificationSchemaService $schema = null,
    ) {
        $this->preferences ??= new NotificationPreferenceService();
        $this->schema ??= new NotificationSchemaService();
    }

    /**
     * @return array<string,mixed>
     */
    public function dropdownPayload(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ok' => false,
                'message' => 'Oturum açmanız gerekiyor.',
            ];
        }

        $adminSettings = $this->preferences->adminSettings($pdo);
        $userSettings = $this->preferences->userSettings($pdo, $userId);
        $canFilterEvents = $this->canFilterEvents($pdo);

        if (!$this->preferences->bool($adminSettings, 'notif_center_enabled', '1')) {
            return $this->emptyDropdown(true, true, false, false);
        }

        $browserNotificationsEnabled = $this->preferences->bool($userSettings, 'notif_group_header', '1')
            && $this->preferences->bool($userSettings, 'notif_browser_push', '1');
        $showBadge = $this->preferences->bool($adminSettings, 'notif_show_header_badge', '1');
        $autoMarkOnOpen = $this->preferences->bool($adminSettings, 'notif_auto_mark_link_click', '1')
            && (($userSettings['notif_auto_mark_on_open'] ?? '1') === '1');

        if (!$browserNotificationsEnabled) {
            return $this->emptyDropdown(false, true, false, $autoMarkOnOpen);
        }

        $preferenceWhere = $this->preferences->whereSql($userSettings, 'n', $canFilterEvents);
        $typeSql = (string) ($preferenceWhere['sql'] ?? '');
        $typeParams = is_array($preferenceWhere['params'] ?? null) ? $preferenceWhere['params'] : [];

        $unreadCount = 0;
        if ($showBadge) {
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) FROM notifications n
                WHERE (n.user_id IS NULL OR n.user_id = ?)
                {$typeSql}
                AND NOT EXISTS (
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.notification_id = n.id AND nr.user_id = ?
                )
            ");
            $stmtCount->execute(array_merge([$userId], $typeParams, [$userId]));
            $unreadCount = (int) $stmtCount->fetchColumn();
        }

        $limit = $this->intSetting($adminSettings, 'notif_dropdown_limit', 5, 1, 20);
        $stmtLatest = $pdo->prepare("
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
            {$typeSql}
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmtLatest->bindValue(1, $userId, PDO::PARAM_INT);
        $stmtLatest->bindValue(2, $userId, PDO::PARAM_INT);
        $bindIndex = 3;
        foreach ($typeParams as $typeParam) {
            $stmtLatest->bindValue($bindIndex, $typeParam, PDO::PARAM_STR);
            $bindIndex++;
        }
        $stmtLatest->bindValue($bindIndex, $limit, PDO::PARAM_INT);
        $stmtLatest->execute();

        return [
            'ok' => true,
            'disabled' => false,
            'muted' => false,
            'show_badge' => $showBadge,
            'auto_mark_on_open' => $autoMarkOnOpen,
            'unread_count' => $unreadCount,
            'latest' => $stmtLatest->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    public function markRead(PDO $pdo, int $userId, string|int $notificationId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ((string) $notificationId === 'all') {
            $stmtUnread = $pdo->prepare("
                SELECT id FROM notifications n
                WHERE (n.user_id IS NULL OR n.user_id = ?)
                AND NOT EXISTS (
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.notification_id = n.id AND nr.user_id = ?
                )
            ");
            $stmtUnread->execute([$userId, $userId]);
            $unreadIds = $stmtUnread->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if ($unreadIds === []) {
                return true;
            }

            $values = [];
            $params = [];
            foreach ($unreadIds as $id) {
                $values[] = '(?, ?)';
                $params[] = (int) $id;
                $params[] = $userId;
            }

            $stmtInsert = $pdo->prepare($this->insertIgnorePrefix($pdo) . ' INTO notification_reads (notification_id, user_id) VALUES ' . implode(', ', $values));

            return $stmtInsert->execute($params);
        }

        $notifId = (int) $notificationId;
        if ($notifId <= 0) {
            return false;
        }

        $stmtCheck = $pdo->prepare('SELECT id FROM notifications WHERE id = ? AND (user_id IS NULL OR user_id = ?)');
        $stmtCheck->execute([$notifId, $userId]);
        if (!$stmtCheck->fetchColumn()) {
            return false;
        }

        $stmtInsert = $pdo->prepare($this->insertIgnorePrefix($pdo) . ' INTO notification_reads (notification_id, user_id) VALUES (?, ?)');

        return $stmtInsert->execute([$notifId, $userId]);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function intSetting(array $settings, string $key, int $default, int $min, int $max): int
    {
        $value = (int) ($settings[$key] ?? $default);

        return max($min, min($max, $value));
    }

    private function canFilterEvents(PDO $pdo): bool
    {
        try {
            $this->schema->ensureEventSchema($pdo);

            return isset($this->schema->eventTableColumns($pdo)['event_key']);
        } catch (Throwable $e) {
            error_log('Notification event schema check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyDropdown(bool $disabled, bool $muted, bool $showBadge, bool $autoMarkOnOpen): array
    {
        return [
            'ok' => true,
            'disabled' => $disabled,
            'muted' => $muted,
            'show_badge' => $showBadge,
            'auto_mark_on_open' => $autoMarkOnOpen,
            'unread_count' => 0,
            'latest' => [],
        ];
    }

    private function insertIgnorePrefix(PDO $pdo): string
    {
        return stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false
            ? 'INSERT OR IGNORE'
            : 'INSERT IGNORE';
    }
}
