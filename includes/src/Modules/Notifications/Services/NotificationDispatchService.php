<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationDispatchService
{
    public function __construct(
        private ?NotificationPreferenceService $preferences = null,
        private ?NotificationSchemaService $schema = null,
        private ?NotificationTemplateService $templates = null,
        private ?NotificationEmailQueueService $emailQueue = null,
    ) {
        $this->preferences ??= new NotificationPreferenceService();
        $this->schema ??= new NotificationSchemaService();
        $this->templates ??= new NotificationTemplateService($this->preferences, $this->schema);
        $this->emailQueue ??= new NotificationEmailQueueService($this->schema);
    }

    public function dispatch(
        PDO $pdo,
        string $eventKey,
        int $recipientId,
        ?int $actorId,
        string $entityType,
        int $entityId,
        array $payload = []
    ): bool {
        if ($recipientId <= 0 || $entityId <= 0 || $entityType === '') {
            return false;
        }

        $definitions = $this->preferences->eventDefinitions();
        if (!isset($definitions[$eventKey])) {
            return false;
        }
        $definition = $definitions[$eventKey];

        $adminSettings = $this->preferences->adminSettings($pdo);
        if (!$this->preferences->bool($adminSettings, 'notif_center_enabled', '1')) {
            return false;
        }
        if (!$this->preferences->bool($adminSettings, 'notif_events_enabled', '1')) {
            return false;
        }

        $adminSetting = (string) ($definition['admin_setting'] ?? '');
        $adminDefault = (string) ($definition['admin_default'] ?? '1');
        if ($adminSetting !== '' && !$this->preferences->bool($adminSettings, $adminSetting, $adminDefault)) {
            return false;
        }

        if ($actorId !== null && $actorId === $recipientId && $this->preferences->bool($adminSettings, 'notif_event_skip_actor', '1')) {
            return false;
        }

        $userSettings = $this->preferences->userSettings($pdo, $recipientId);
        if (!$this->preferences->groupEnabled($userSettings, 'notif_group_events')) {
            return false;
        }

        $settingKey = (string) ($definition['setting_key'] ?? '');
        $default = (string) ($definition['default'] ?? '1');
        if ($settingKey !== '' && !$this->preferences->bool($userSettings, $settingKey, $default)) {
            return false;
        }

        $this->schema->ensureEventSchema($pdo);
        $columnsAvailable = $this->schema->eventTableColumns($pdo);
        $dedupeKey = $this->templates->payloadValue($payload, 'dedupe_key');
        if ($dedupeKey === '') {
            $dedupeKey = $eventKey . ':' . $recipientId . ':' . $entityType . ':' . $entityId;
        }

        if (isset($columnsAvailable['dedupe_key']) && $this->preferences->bool($adminSettings, 'notif_event_dedupe_enabled', '1')) {
            try {
                $stmt = $pdo->prepare('SELECT id FROM notifications WHERE dedupe_key = ? LIMIT 1');
                $stmt->execute([$dedupeKey]);
                if ($stmt->fetchColumn()) {
                    return false;
                }
            } catch (Throwable $e) {
                error_log('Notification duplicate check failed: ' . $e->getMessage());
            }
        }

        $payload = array_merge([
            'recipient_name' => 'Kullanici',
            'comment_excerpt' => '',
            'moderation_note' => '',
            'site_name' => 'Mod Portal',
            'link' => '',
            'actor_name' => 'Bir kullanici',
            'topic_title' => 'Konu',
            'moderation_note_line' => '',
        ], $payload);

        $dispatchTemplate = $this->templates->forDispatch($pdo, $eventKey, $definition);
        if ($dispatchTemplate === null) {
            return false;
        }

        $title = $this->templates->payloadValue($payload, 'title');
        if ($title === '') {
            $title = $this->templates->render((string) ($dispatchTemplate['title_template'] ?? $definition['title']), $payload);
        }

        $message = $this->templates->payloadValue($payload, 'message');
        if ($message === '') {
            $message = $this->templates->render((string) ($dispatchTemplate['message_template'] ?? $definition['message']), $payload);
        }

        $link = $this->templates->payloadValue($payload, 'link');
        if ($link === '' && trim((string) ($dispatchTemplate['link_template'] ?? '')) !== '') {
            $link = $this->templates->render((string) $dispatchTemplate['link_template'], $payload);
        }

        $type = $this->templates->payloadValue($payload, 'type', (string) ($dispatchTemplate['type'] ?? $definition['type'] ?? 'info'));
        if (!in_array($type, ['info', 'success', 'warning', 'error', 'system'], true)) {
            $type = 'info';
        }
        if (!$this->preferences->bool($userSettings, 'notif_type_' . $type, '1')) {
            return false;
        }

        $insertData = [
            'user_id' => $recipientId,
            'title' => mb_substr($title, 0, 255),
            'message' => $message,
            'type' => $type,
            'link' => $link !== '' ? $link : null,
        ];

        $emailAllowedByUser = $this->preferences->groupEnabled($userSettings, 'notif_group_email')
            && $this->preferences->bool($userSettings, 'notif_email_updates', '1');
        $deliveryChannels = ['in_app'];
        $emailQueueRequested = (
            (int) ($dispatchTemplate['email_enabled'] ?? 0) === 1
            && $this->preferences->bool($adminSettings, 'notif_email_channel_ready', '0')
            && $emailAllowedByUser
        );
        if ($emailQueueRequested) {
            $deliveryChannels[] = 'email_queue_pending';
        }
        $emailQueueMaxAttempts = max(1, min(10, (int) ($adminSettings['notif_email_queue_max_attempts'] ?? 3)));

        $metadata = [
            'event_key' => $eventKey,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_user_id' => $actorId,
            'dedupe_key' => $dedupeKey,
            'delivery_channels' => json_encode($deliveryChannels, JSON_UNESCAPED_UNICODE),
        ];

        foreach ($metadata as $column => $value) {
            if (isset($columnsAvailable[$column])) {
                $insertData[$column] = $value;
            }
        }

        $columns = array_keys($insertData);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO notifications (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $stmt = $pdo->prepare($sql);
            $inserted = $stmt->execute(array_values($insertData));
            if (!$inserted) {
                return false;
            }

            $notificationId = (int) $pdo->lastInsertId();
            if ($emailQueueRequested && $notificationId > 0) {
                $this->queueEmailForNotification(
                    $pdo,
                    $notificationId,
                    $recipientId,
                    $dispatchTemplate,
                    (string) $insertData['title'],
                    $message,
                    $link !== '' ? $link : null,
                    $eventKey,
                    $entityType,
                    $entityId,
                    $actorId,
                    $dedupeKey,
                    $payload,
                    $emailQueueMaxAttempts,
                    $columnsAvailable
                );
            }

            return true;
        } catch (Throwable $e) {
            error_log('Notification dispatch insert failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string,mixed> $dispatchTemplate
     * @param array<string,mixed> $payload
     * @param array<string,bool> $columnsAvailable
     */
    private function queueEmailForNotification(
        PDO $pdo,
        int $notificationId,
        int $recipientId,
        array $dispatchTemplate,
        string $title,
        string $message,
        ?string $link,
        string $eventKey,
        string $entityType,
        int $entityId,
        ?int $actorId,
        string $dedupeKey,
        array $payload,
        int $emailQueueMaxAttempts,
        array $columnsAvailable
    ): void {
        try {
            $emailQueued = $this->emailQueue->queue(
                $pdo,
                $notificationId,
                $recipientId,
                (string) ($dispatchTemplate['template_key'] ?? $eventKey),
                $title,
                $message,
                $link,
                [
                    'event_key' => $eventKey,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'actor_user_id' => $actorId,
                    'dedupe_key' => $dedupeKey,
                    'payload' => $payload,
                ],
                $emailQueueMaxAttempts
            );
            if (isset($columnsAvailable['delivery_channels'])) {
                $deliveryChannels = ['in_app', $emailQueued ? 'email_queue' : 'email_queue_failed'];
                $update = $pdo->prepare('UPDATE notifications SET delivery_channels = ? WHERE id = ?');
                $update->execute([json_encode($deliveryChannels, JSON_UNESCAPED_UNICODE), $notificationId]);
            }
        } catch (Throwable $e) {
            error_log('Notification email fan-out failed: ' . $e->getMessage());
        }
    }
}
