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

        $appealsLink = \function_exists('routePublicStaticPath')
            ? '/' . ltrim((string) \routePublicStaticPath('ban_appeals'), '/')
            : '/ban-appeals.php';

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
}
