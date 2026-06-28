<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use PDO;

final class ReportNotificationService
{
    public function statusLabel(string $status): string
    {
        return [
            'open' => 'Açık',
            'reviewing' => 'İnceleniyor',
            'resolved' => 'Çözüldü',
            'rejected' => 'Reddedildi',
        ][$status] ?? $status;
    }

    public function statusType(string $status): string
    {
        return [
            'resolved' => 'success',
            'rejected' => 'warning',
            'reviewing' => 'info',
            'open' => 'info',
        ][$status] ?? 'info';
    }

    public function dispatchStatus(
        PDO $pdo,
        string $eventKey,
        int $recipientId,
        ?int $actorId,
        string $entityType,
        int $reportId,
        string $status,
        string $adminNote = '',
        array $payload = []
    ): void {
        if ($recipientId <= 0 || $reportId <= 0) {
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

        $baseUri = (string) ($GLOBALS['baseUri'] ?? '');
        $note = trim($adminNote);
        notificationDispatch($pdo, $eventKey, $recipientId, $actorId, $entityType, $reportId, array_merge([
            'report_status' => $this->statusLabel($status),
            'moderation_note' => $note,
            'moderation_note_line' => $note !== '' ? ' Not: ' . $note : '',
            'type' => $this->statusType($status),
            'link' => $baseUri . '/profile.php?tab=reports',
            'dedupe_key' => $eventKey . ':' . $recipientId . ':' . $reportId . ':' . $status . ':' . hash('sha256', $note),
        ], $payload));
    }
}
