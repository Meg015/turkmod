<?php

declare(strict_types=1);

namespace App\Modules\BanAppeals\Services;

use PDO;
use Throwable;

final class BanAppealNotificationService
{
    public function typeForStatus(string $status): string
    {
        return match ($status) {
            'accepted' => 'success',
            'rejected' => 'error',
            default => 'info',
        };
    }

    public function dispatchUpdate(
        PDO $pdo,
        int $recipientId,
        ?int $actorId,
        int $appealId,
        string $message,
        string $status
    ): void {
        if ($recipientId <= 0 || $appealId <= 0) {
            return;
        }

        if (!function_exists('notificationDispatch')) {
            $notificationsFile = dirname(__DIR__, 4) . '/notifications.php';
            if (is_file($notificationsFile)) {
                require_once $notificationsFile;
            }
        }
        if (!function_exists('notificationDispatch')) {
            return;
        }
        if (!$this->notificationsTableExists($pdo)) {
            return;
        }

        $appealsLink = '/' . ltrim((string) \routePublicStaticPath('ban_appeals'), '/');

        try {
            notificationDispatch($pdo, 'ban_appeal_updated', $recipientId, $actorId, 'user', $appealId, [
                'recipient_name' => 'Kullanici',
                'actor_name' => 'Yonetim',
                'moderation_note' => $message,
                'type' => $this->typeForStatus($status),
                'link' => $appealsLink,
                'dedupe_key' => 'ban_appeal_updated:' . $recipientId . ':' . $appealId . ':' . substr(hash('sha1', $message . microtime(true)), 0, 10),
            ]);
        } catch (Throwable $e) {
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'BanAppealNotificationService', 'event_key' => 'ban_appeal_updated']);
                return;
            }

            error_log('Ban appeal notification dispatch failed: ' . $e->getMessage());
        }
    }

    public function dispatchAdminSubmission(
        PDO $pdo,
        int $actorId,
        int $appealId,
        string $message,
        bool $isReply
    ): void {
        if ($actorId <= 0 || $appealId <= 0) {
            return;
        }

        $this->loadNotificationHelpers();
        if (!$this->notificationsTableExists($pdo)) {
            return;
        }

        $adminIds = $this->adminRecipientIds($pdo);
        if ($adminIds === []) {
            return;
        }

        $actorName = $this->userDisplayName($pdo, $actorId);
        $excerpt = $this->excerpt($message, 220);
        $eventKey = $isReply ? 'ban_appeal_message_added' : 'ban_appeal_created';
        $title = $isReply ? 'Ban itirazina yeni mesaj' : 'Yeni ban itirazi';
        $body = $isReply
            ? $actorName . ' #' . $appealId . ' numarali ban itirazina yeni mesaj ekledi.'
            : $actorName . ' #' . $appealId . ' numarali yeni ban itirazi gonderdi.';
        if ($excerpt !== '') {
            $body .= ' Mesaj: ' . $excerpt;
        }

        $adminLink = $this->adminAppealsLink();
        $columns = $this->notificationColumns($pdo);
        $hasColumn = static fn (string $column): bool => isset($columns[$column]);

        $baseColumns = ['user_id', 'title', 'message', 'type', 'link'];
        foreach (['event_key', 'entity_type', 'entity_id', 'actor_user_id', 'dedupe_key', 'delivery_channels'] as $column) {
            if ($hasColumn($column)) {
                $baseColumns[] = $column;
            }
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $baseColumns);
        $placeholders = array_fill(0, count($baseColumns), '?');
        $sql = 'INSERT INTO notifications (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $startedTx = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTx = true;
            }

            $stmt = $pdo->prepare($sql);
            foreach ($adminIds as $adminId) {
                $adminId = (int) $adminId;
                if ($adminId <= 0 || $adminId === $actorId) {
                    continue;
                }

                $dedupePart = $isReply
                    ? substr(hash('sha1', $message . microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12)
                    : (string) $appealId;
                $params = [
                    $adminId,
                    mb_substr($title, 0, 255),
                    $body,
                    $isReply ? 'info' : 'warning',
                    $adminLink,
                ];
                if ($hasColumn('event_key')) {
                    $params[] = $eventKey;
                }
                if ($hasColumn('entity_type')) {
                    $params[] = 'ban_appeal';
                }
                if ($hasColumn('entity_id')) {
                    $params[] = $appealId;
                }
                if ($hasColumn('actor_user_id')) {
                    $params[] = $actorId;
                }
                if ($hasColumn('dedupe_key')) {
                    $params[] = $eventKey . ':' . $adminId . ':' . $appealId . ':' . $dedupePart;
                }
                if ($hasColumn('delivery_channels')) {
                    $params[] = json_encode(['in_app'], JSON_UNESCAPED_UNICODE);
                }

                $stmt->execute($params);
            }

            if ($startedTx && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if (isset($startedTx) && $startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'BanAppealNotificationService.admin_submission', 'appeal_id' => $appealId]);
                return;
            }

            error_log('Ban appeal admin notification failed: ' . $e->getMessage());
        }
    }

    private function notificationsTableExists(PDO $pdo): bool
    {
        try {
            if (strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'notifications' LIMIT 1");

                return (bool) ($stmt ? $stmt->fetchColumn() : false);
            }

            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ');
            $stmt->execute(['notifications']);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('Ban appeal notification table lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    private function loadNotificationHelpers(): void
    {
        if (function_exists('notificationDispatch')) {
            return;
        }

        $notificationsFile = dirname(__DIR__, 4) . '/notifications.php';
        if (is_file($notificationsFile)) {
            require_once $notificationsFile;
        }
    }

    /** @return list<int> */
    private function adminRecipientIds(PDO $pdo): array
    {
        if (function_exists('usersGetAdminRecipientIds')) {
            return array_values(array_filter(array_map('intval', usersGetAdminRecipientIds($pdo)), static fn (int $id): bool => $id > 0));
        }

        return [];
    }

    /** @return array<string,bool> */
    private function notificationColumns(PDO $pdo): array
    {
        try {
            return function_exists('notificationEventTableColumns')
                ? notificationEventTableColumns($pdo)
                : [];
        } catch (Throwable $e) {
            error_log('Ban appeal notification columns lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    private function userDisplayName(PDO $pdo, int $userId): string
    {
        try {
            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = trim((string) ($row['username'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($row['email'] ?? ''));
                }
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (Throwable $e) {
            error_log('Ban appeal notification user lookup failed: ' . $e->getMessage());
        }

        return 'Kullanici #' . $userId;
    }

    private function adminAppealsLink(): string
    {
        $baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');

        return ($baseUri !== '' ? $baseUri : '') . '/admin/users.php?tab=appeals';
    }

    private function excerpt(string $message, int $limit): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));
        if ($message === '') {
            return '';
        }
        if (mb_strlen($message) <= $limit) {
            return $message;
        }

        return mb_substr($message, 0, max(1, $limit - 3)) . '...';
    }
}
