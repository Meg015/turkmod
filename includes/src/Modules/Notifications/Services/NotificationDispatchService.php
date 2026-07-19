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
        private ?NotificationSuppressionLogService $suppressionLogs = null,
    ) {
        $this->preferences ??= new NotificationPreferenceService();
        $this->schema ??= new NotificationSchemaService();
        $this->templates ??= new NotificationTemplateService($this->preferences, $this->schema);
        $this->emailQueue ??= new NotificationEmailQueueService($this->schema);
        $this->suppressionLogs ??= new NotificationSuppressionLogService($this->schema);
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
            return $this->suppressed($pdo, 'invalid_target', $eventKey, $recipientId, $actorId, $entityType, $entityId, null, null, null, [
                'recipient_id' => $recipientId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
        if ($this->recipientIsBanned($pdo, $recipientId)) {
            return $this->suppressed($pdo, 'recipient_banned', $eventKey, $recipientId, $actorId, $entityType, $entityId);
        }

        $definitions = $this->preferences->eventDefinitions();
        if (!isset($definitions[$eventKey])) {
            return $this->suppressed($pdo, 'unknown_event', $eventKey, $recipientId, $actorId, $entityType, $entityId);
        }
        $definition = $definitions[$eventKey];

        $adminSettings = $this->preferences->adminSettings($pdo);
        if (!$this->preferences->bool($adminSettings, 'notif_center_enabled', '1')) {
            return $this->suppressed($pdo, 'admin_center_disabled', $eventKey, $recipientId, $actorId, $entityType, $entityId);
        }
        if (!$this->preferences->bool($adminSettings, 'notif_events_enabled', '1')) {
            return $this->suppressed($pdo, 'admin_events_disabled', $eventKey, $recipientId, $actorId, $entityType, $entityId);
        }

        $adminSetting = (string) ($definition['admin_setting'] ?? '');
        $adminDefault = (string) ($definition['admin_default'] ?? '1');
        if ($adminSetting !== '' && !$this->preferences->bool($adminSettings, $adminSetting, $adminDefault)) {
            return $this->suppressed($pdo, 'admin_event_disabled', $eventKey, $recipientId, $actorId, $entityType, $entityId, null, null, null, [
                'admin_setting' => $adminSetting,
            ]);
        }

        if ($actorId !== null && $actorId === $recipientId && $this->preferences->bool($adminSettings, 'notif_event_skip_actor', '1')) {
            return $this->suppressed($pdo, 'self_actor_skipped', $eventKey, $recipientId, $actorId, $entityType, $entityId);
        }

        $userSettings = $this->preferences->userSettings($pdo, $recipientId);
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
            return $this->suppressed($pdo, 'template_disabled', $eventKey, $recipientId, $actorId, $entityType, $entityId);
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
        $adminLoggable = $this->preferences->eventAdminLoggable($eventKey, $definition) || $type === 'system';
        if (array_key_exists('admin_loggable', $payload) || array_key_exists('is_admin_loggable', $payload)) {
            $adminLoggableValue = $payload['admin_loggable'] ?? $payload['is_admin_loggable'];
            $adminLoggable = $adminLoggableValue === true || $adminLoggableValue === 1 || $adminLoggableValue === '1';
        }

        $inAppAllowedByUser = $this->preferences->groupEnabled($userSettings, 'notif_group_events')
            && $this->preferences->eventEnabledForUser($userSettings, $eventKey, $definition)
            && $this->preferences->bool($userSettings, 'notif_type_' . $type, '1');
        $emailAllowedByUser = $this->preferences->groupEnabled($userSettings, 'notif_group_email')
            && $this->preferences->bool($userSettings, 'notif_email_updates', '1')
            && $this->preferences->emailEventEnabledForUser($userSettings, $eventKey, $definition);
        $inAppRequested = (int) ($dispatchTemplate['in_app_enabled'] ?? 1) === 1 && $inAppAllowedByUser;
        $emailChannelReady = $this->preferences->bool($adminSettings, 'notif_email_channel_ready', '0');
        $emailQueueRequested = (
            (int) ($dispatchTemplate['email_enabled'] ?? 0) === 1
            && $emailChannelReady
            && $emailAllowedByUser
        );

        if (!$inAppRequested && !$emailQueueRequested) {
            $templateInAppEnabled = (int) ($dispatchTemplate['in_app_enabled'] ?? 1) === 1;
            $templateEmailEnabled = (int) ($dispatchTemplate['email_enabled'] ?? 0) === 1;
            $reason = 'user_preferences_disabled';
            if (!$templateInAppEnabled && !$templateEmailEnabled) {
                $reason = 'template_channels_disabled';
            } elseif (!$emailChannelReady && $templateEmailEnabled && $emailAllowedByUser && !$inAppRequested) {
                $reason = 'email_channel_not_ready';
            }

            return $this->suppressed($pdo, $reason, $eventKey, $recipientId, $actorId, $entityType, $entityId, null, (string) ($dispatchTemplate['template_key'] ?? $eventKey), $type, [
                'in_app_allowed_by_user' => $inAppAllowedByUser ? 1 : 0,
                'email_allowed_by_user' => $emailAllowedByUser ? 1 : 0,
                'template_in_app_enabled' => $templateInAppEnabled ? 1 : 0,
                'template_email_enabled' => $templateEmailEnabled ? 1 : 0,
                'email_channel_ready' => $emailChannelReady ? 1 : 0,
            ]);
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
                    return $this->suppressed($pdo, 'duplicate_dedupe', $eventKey, $recipientId, $actorId, $entityType, $entityId, $dedupeKey, (string) ($dispatchTemplate['template_key'] ?? $eventKey), $type);
                }
            } catch (Throwable $e) {
                error_log('Notification duplicate check failed: ' . $e->getMessage());
            }
        }

        $insertData = [
            'user_id' => $recipientId,
            'title' => mb_substr($title, 0, 255),
            'message' => $message,
            'type' => $type,
            'link' => $link !== '' ? $link : null,
        ];

        $deliveryChannels = $inAppRequested ? ['in_app'] : [];
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
            'is_admin_loggable' => $adminLoggable ? 1 : 0,
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
                return $this->suppressed($pdo, 'insert_failed', $eventKey, $recipientId, $actorId, $entityType, $entityId, $dedupeKey, (string) ($dispatchTemplate['template_key'] ?? $eventKey), $type);
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
                    $columnsAvailable,
                    $deliveryChannels
                );
            }

            return true;
        } catch (Throwable $e) {
            error_log('Notification dispatch insert failed: ' . $e->getMessage());

            return $this->suppressed($pdo, 'insert_failed', $eventKey, $recipientId, $actorId, $entityType, $entityId, $dedupeKey, (string) ($dispatchTemplate['template_key'] ?? $eventKey), $type, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function suppressed(
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
    ): bool {
        $this->suppressionLogs->log(
            $pdo,
            $reasonKey,
            $eventKey,
            $recipientId,
            $actorId,
            $entityType,
            $entityId,
            $dedupeKey,
            $templateKey,
            $type,
            $context
        );

        return false;
    }

    /**
     * @param array<string,mixed> $dispatchTemplate
     * @param array<string,mixed> $payload
     * @param array<string,bool> $columnsAvailable
     * @param list<string> $deliveryChannels
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
        array $columnsAvailable,
        array $deliveryChannels
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
                $deliveryChannels = array_values(array_filter(
                    $deliveryChannels,
                    static fn (string $channel): bool => $channel !== 'email_queue_pending'
                ));
                $deliveryChannels[] = $emailQueued ? 'email_queue' : 'email_queue_failed';
                $update = $pdo->prepare('UPDATE notifications SET delivery_channels = ? WHERE id = ?');
                $update->execute([json_encode($deliveryChannels, JSON_UNESCAPED_UNICODE), $notificationId]);
            }
        } catch (Throwable $e) {
            error_log('Notification email fan-out failed: ' . $e->getMessage());
        }
    }

    private function recipientIsBanned(PDO $pdo, int $recipientId): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT status, is_banned FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$recipientId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return false;
            }

            return (int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned';
        } catch (Throwable $e) {
            error_log('Notification banned recipient check failed: ' . $e->getMessage());

            return false;
        }
    }
}
