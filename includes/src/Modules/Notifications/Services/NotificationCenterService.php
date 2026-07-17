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

        if ($this->recipientIsBanned($pdo, $userId)) {
            return $this->emptyDropdown(true, true, false, false);
        }

        $adminSettings = $this->preferences->adminSettings($pdo);
        $userSettings = $this->preferences->userSettings($pdo, $userId);
        $canFilterEvents = $this->canFilterEvents($pdo);
        $this->schema->ensureNotificationDismissalSchema($pdo);
        $dismissalSql = $this->dismissalSql($pdo);
        $dismissalParams = $dismissalSql !== '' ? [$userId] : [];

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
            {$dismissalSql}
        ");
            $stmtCount->execute(array_merge([$userId], $typeParams, [$userId], $dismissalParams));
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
            {$dismissalSql}
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
        foreach ($dismissalParams as $dismissalParam) {
            $stmtLatest->bindValue($bindIndex, $dismissalParam, PDO::PARAM_INT);
            $bindIndex++;
        }
        $stmtLatest->bindValue($bindIndex, $limit, PDO::PARAM_INT);
        $stmtLatest->execute();
        $latest = $stmtLatest->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($latest as &$notification) {
            if (isset($notification['id'])) {
                $notification['id'] = (int) $notification['id'];
            }
            if (isset($notification['is_read'])) {
                $notification['is_read'] = (int) $notification['is_read'];
            }
        }
        unset($notification);

        return [
            'ok' => true,
            'disabled' => false,
            'muted' => false,
            'show_badge' => $showBadge,
            'auto_mark_on_open' => $autoMarkOnOpen,
            'unread_count' => $unreadCount,
            'latest' => $latest,
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
                {$this->dismissalSql($pdo)}
            ");
            $stmtUnread->execute(array_merge([$userId, $userId], $this->dismissalSql($pdo) !== '' ? [$userId] : []));
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
     * @param list<int|string> $notificationIds
     */
    public function dismissNotifications(PDO $pdo, int $userId, array $notificationIds): int
    {
        if ($userId <= 0 || $notificationIds === []) {
            return 0;
        }

        $this->schema->ensureNotificationDismissalSchema($pdo, false);

        $ids = [];
        foreach ($notificationIds as $notificationId) {
            $id = (int) $notificationId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return 0;
        }

        $stmtCheck = $pdo->prepare('SELECT id FROM notifications WHERE id = ? AND (user_id IS NULL OR user_id = ?)');
        $stmtInsert = $pdo->prepare($this->insertIgnorePrefix($pdo) . ' INTO notification_dismissals (notification_id, user_id) VALUES (?, ?)');

        $dismissedCount = 0;
        foreach ($ids as $notifId) {
            $stmtCheck->execute([$notifId, $userId]);
            if (!$stmtCheck->fetchColumn()) {
                continue;
            }

            if ($stmtInsert->execute([$notifId, $userId])) {
                $dismissedCount += max(0, (int) $stmtInsert->rowCount());
            }
        }

        return $dismissedCount;
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

    private function dismissalSql(PDO $pdo): string
    {
        if (!$this->schema->tableExists($pdo, 'notification_dismissals')) {
            return '';
        }

        return '
            AND NOT EXISTS (
                SELECT 1 FROM notification_dismissals nd
                WHERE nd.notification_id = n.id AND nd.user_id = ?
            )';
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

    private function recipientIsBanned(PDO $pdo, int $userId): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT status, is_banned FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return false;
            }

            return (int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned';
        } catch (Throwable $e) {
            error_log('Notification center banned user check failed: ' . $e->getMessage());

            return false;
        }
    }

    private function insertIgnorePrefix(PDO $pdo): string
    {
        return stripos((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false
            ? 'INSERT OR IGNORE'
            : 'INSERT IGNORE';
    }
}
