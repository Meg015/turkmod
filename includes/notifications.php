<?php

declare(strict_types=1);

use App\Modules\Notifications\Services\CommentEditNotificationService;
use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Notifications\Services\NotificationEmailQueueService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Notifications\Services\NotificationSchemaService;
use App\Modules\Notifications\Services\NotificationSuppressionLogService;
use App\Modules\Notifications\Services\NotificationTemplateService;

function notificationPreferenceService(): NotificationPreferenceService
{
    static $service = null;

    return $service ??= new NotificationPreferenceService();
}

function notificationSchemaService(): NotificationSchemaService
{
    static $service = null;

    return $service ??= new NotificationSchemaService();
}

function notificationTemplateService(): NotificationTemplateService
{
    static $service = null;

    return $service ??= new NotificationTemplateService(notificationPreferenceService(), notificationSchemaService());
}

function notificationEmailQueueService(): NotificationEmailQueueService
{
    static $service = null;

    return $service ??= new NotificationEmailQueueService(notificationSchemaService());
}

function notificationSuppressionLogService(): NotificationSuppressionLogService
{
    static $service = null;

    return $service ??= new NotificationSuppressionLogService(notificationSchemaService());
}

function notificationDeliveryLogExcerpt(mixed $value, int $limit = 220): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $value = is_string($encoded) ? $encoded : '';
    }

    $text = (string) $value;
    $text = preg_replace('~<\s*br\s*/?\s*>~i', ' ', $text) ?? $text;
    $text = preg_replace('~</\s*(p|div|li|tr|td|th|h[1-6]|section|article)\s*>~i', ' ', $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    if ($text === '') {
        return '';
    }

    $limit = max(20, $limit);
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(0, $limit - 3), 'UTF-8')) . '...';
}

function notificationDeliveryLogNormalizeList(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/\s*,\s*/', $value) ?: [];
        }
    }
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $item): string => trim((string) $item),
        $value
    ), static fn (string $item): bool => $item !== '')));
}

function notificationDeliveryLogContext(array $context): array
{
    if (isset($context['recipient_id']) && !isset($context['recipient_user_id'])) {
        $context['recipient_user_id'] = $context['recipient_id'];
    }
    if (isset($context['user_id']) && !isset($context['recipient_user_id'])) {
        $context['recipient_user_id'] = $context['user_id'];
    }
    if (isset($context['template']) && !isset($context['template_key'])) {
        $context['template_key'] = $context['template'];
    }
    if (isset($context['subject']) && !isset($context['title'])) {
        $context['title'] = $context['subject'];
    }
    if (isset($context['message']) && !isset($context['message_excerpt'])) {
        $context['message_excerpt'] = notificationDeliveryLogExcerpt($context['message']);
    }
    if (isset($context['body']) && !isset($context['message_excerpt'])) {
        $context['message_excerpt'] = notificationDeliveryLogExcerpt($context['body']);
    }
    if (isset($context['channels']) && !isset($context['delivery_channels'])) {
        $context['delivery_channels'] = $context['channels'];
    }
    if (isset($context['delivery_channels'])) {
        $context['delivery_channels'] = notificationDeliveryLogNormalizeList($context['delivery_channels']);
    }

    foreach (['payload', 'settings', 'message', 'body', 'channels', 'template', 'recipient_id'] as $key) {
        unset($context[$key]);
    }

    $clean = [];
    foreach ($context as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || $value === null || $value === '') {
            continue;
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $itemText = notificationDeliveryLogExcerpt($item, 120);
                    if ($itemText !== '') {
                        $items[] = $itemText;
                    }
                }
            }
            if ($items !== []) {
                $clean[$key] = $items;
            }
            continue;
        }

        if (is_object($value)) {
            continue;
        }

        $limit = in_array($key, ['error', 'reason', 'title', 'link', 'recipient_email'], true) ? 255 : 160;
        $text = notificationDeliveryLogExcerpt($value, $limit);
        if ($text !== '') {
            $clean[$key] = $text;
        }
    }

    return $clean;
}

function notificationDeliveryLog(?PDO $pdo, string $message, array $context = [], string $level = 'info'): void
{
    if (!$pdo || !function_exists('appLog')) {
        return;
    }

    $level = strtolower(trim($level));
    if (!in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical'], true)) {
        $level = 'info';
    }

    appLog(
        $pdo,
        $level,
        'notification',
        trim($message) !== '' ? trim($message) : 'notification_delivery_event',
        notificationDeliveryLogContext($context)
    );
}

function notificationDispatchService(): NotificationDispatchService
{
    static $service = null;

    return $service ??= new NotificationDispatchService(
        notificationPreferenceService(),
        notificationSchemaService(),
        notificationTemplateService(),
        notificationEmailQueueService(),
        notificationSuppressionLogService()
    );
}

function notificationCommentEditService(): CommentEditNotificationService
{
    static $service = null;

    return $service ??= new CommentEditNotificationService(notificationDispatchService());
}

function notificationDispatchCommentEdited(
    PDO $pdo,
    array $comment,
    int $editorUserId,
    string $editorName,
    array $editResult
): bool {
    return notificationCommentEditService()->dispatch($pdo, $comment, $editorUserId, $editorName, $editResult);
}

function notificationEventDefinitions(): array
{
    return notificationPreferenceService()->eventDefinitions();
}

function notificationEventPreferenceItems(): array
{
    return notificationPreferenceService()->eventPreferenceItems();
}

function notificationEmailEventPreferenceItems(): array
{
    return notificationPreferenceService()->emailEventPreferenceItems();
}

function notificationEventBool(array $settings, string $key, string $default = '1'): bool
{
    return notificationPreferenceService()->bool($settings, $key, $default);
}

function notificationEventAdminSettings(?PDO $pdo): array
{
    return notificationPreferenceService()->adminSettings($pdo);
}

function notificationEventUserSettings(PDO $pdo, int $userId): array
{
    return notificationPreferenceService()->userSettings($pdo, $userId);
}

function notificationPreferenceBool(array $settings, string $key, string $default = '1'): bool
{
    return notificationPreferenceService()->bool($settings, $key, $default);
}

function notificationPreferenceGroupEnabled(array $settings, string $groupKey): bool
{
    return notificationPreferenceService()->groupEnabled($settings, $groupKey);
}

function notificationEnabledTypesForUser(array $settings): array
{
    return notificationPreferenceService()->enabledTypesForUser($settings);
}

function notificationEnabledEventKeysForUser(array $settings): array
{
    return notificationPreferenceService()->enabledEventKeysForUser($settings);
}

function notificationEnabledEmailEventKeysForUser(array $settings): array
{
    return notificationPreferenceService()->enabledEmailEventKeysForUser($settings);
}

function notificationPreferenceWhereSql(array $settings, string $alias = 'n', bool $filterEvents = true, bool $respectUserPreferences = true): array
{
    return notificationPreferenceService()->whereSql($settings, $alias, $filterEvents, $respectUserPreferences);
}

function notificationEventTableColumns(PDO $pdo, bool $refresh = false): array
{
    return notificationSchemaService()->eventTableColumns($pdo, $refresh);
}

function notificationEventIndexExists(PDO $pdo, string $indexName): bool
{
    return notificationSchemaService()->eventIndexExists($pdo, $indexName);
}

function notificationEnsureEventSchema(PDO $pdo): void
{
    notificationSchemaService()->ensureEventSchema($pdo);
}

function notificationTemplateAllowedVariables(): array
{
    return notificationTemplateService()->allowedVariables();
}

function notificationTemplateSamplePayload(): array
{
    return notificationTemplateService()->samplePayload();
}

function notificationTemplateDefaults(): array
{
    return notificationTemplateService()->defaults();
}

function notificationEnsureTemplateSchema(PDO $pdo): void
{
    notificationTemplateService()->ensureSchema($pdo);
}

function notificationTemplateEmailSchemaReady(PDO $pdo): bool
{
    return notificationTemplateService()->emailColumnsReady($pdo);
}

function notificationTemplateMissingEmailColumns(PDO $pdo): array
{
    return notificationTemplateService()->missingEmailColumns($pdo);
}

function notificationEnsureEmailQueueSchema(PDO $pdo): void
{
    notificationSchemaService()->ensureEmailQueueSchema($pdo);
}

function notificationEnsureSuppressionLogSchema(PDO $pdo): void
{
    notificationSchemaService()->ensureSuppressionLogSchema($pdo);
}

function notificationSuppressionLogTableExists(PDO $pdo): bool
{
    return notificationSuppressionLogService()->tableExists($pdo);
}

function notificationSuppressionReasonMeta(string $reasonKey): array
{
    return notificationSuppressionLogService()->reasonMeta($reasonKey);
}

function notificationSuppressionReasonOptions(): array
{
    return notificationSuppressionLogService()->reasonOptions();
}

function notificationSuppressionLogStats(PDO $pdo): array
{
    return notificationSuppressionLogService()->stats($pdo);
}

function notificationSuppressionLogCount(PDO $pdo, string $reasonKey = 'all'): int
{
    return notificationSuppressionLogService()->count($pdo, $reasonKey);
}

function notificationSuppressionLogRecent(PDO $pdo, int $limit = 10, string $reasonKey = 'all'): array
{
    return notificationSuppressionLogService()->recent($pdo, $limit, $reasonKey);
}

function notificationEnsureDismissalSchema(PDO $pdo, bool $respectRuntimeGate = true): void
{
    notificationSchemaService()->ensureNotificationDismissalSchema($pdo, $respectRuntimeGate);
}

function notificationDismissalTableExists(PDO $pdo): bool
{
    return notificationSchemaService()->tableExists($pdo, 'notification_dismissals');
}

function notificationSeedDefaultTemplates(PDO $pdo): void
{
    notificationTemplateService()->seedDefaultTemplates($pdo);
}

function notificationTemplateDecodeJson(?string $json, array $fallback): array
{
    return notificationTemplateService()->decodeJson($json, $fallback);
}

function notificationNormalizeTemplateRow(array $row): array
{
    return notificationTemplateService()->normalizeRow($row);
}

function notificationTemplateList(PDO $pdo, bool $onlyActive = false): array
{
    return notificationTemplateService()->list($pdo, $onlyActive);
}

function notificationTemplateGet(PDO $pdo, string $templateKey): ?array
{
    return notificationTemplateService()->get($pdo, $templateKey);
}

function notificationTemplateExtractVariables(string $template): array
{
    return notificationTemplateService()->extractVariables($template);
}

function notificationTemplateValidate(array $input): array
{
    return notificationTemplateService()->validate($input);
}

function notificationTemplateSave(PDO $pdo, string $templateKey, array $input): bool
{
    return notificationTemplateService()->save($pdo, $templateKey, $input);
}

function notificationTemplateDelete(PDO $pdo, string $templateKey): bool
{
    return notificationTemplateService()->delete($pdo, $templateKey);
}

function notificationTemplateReset(PDO $pdo, string $templateKey): bool
{
    return notificationTemplateService()->reset($pdo, $templateKey);
}

function notificationTemplatePreview(array $template, ?array $payload = null): array
{
    return notificationTemplateService()->preview($template, $payload);
}

function notificationTemplateEmailPreview(array $template, ?array $payload = null): array
{
    return notificationTemplateService()->emailPreview($template, $payload);
}

function notificationTemplateEmailCopyErrors(array $template): array
{
    return notificationTemplateService()->emailCopyErrors($template);
}

function notificationTemplateForDispatch(PDO $pdo, string $eventKey, array $definition): ?array
{
    return notificationTemplateService()->forDispatch($pdo, $eventKey, $definition);
}

function notificationEmailQueueStats(PDO $pdo): array
{
    return notificationEmailQueueService()->stats($pdo);
}

function notificationEmailRecipient(PDO $pdo, int $userId): ?array
{
    return notificationEmailQueueService()->recipient($pdo, $userId);
}

function notificationQueueEmail(
    PDO $pdo,
    int $notificationId,
    int $recipientId,
    string $templateKey,
    string $subject,
    string $body,
    ?string $link,
    array $metadata,
    int $maxAttempts = 3
): bool {
    $queued = notificationEmailQueueService()->queue(
        $pdo,
        $notificationId,
        $recipientId,
        $templateKey,
        $subject,
        $body,
        $link,
        $metadata,
        $maxAttempts
    );
    notificationDeliveryLog($pdo, $queued ? 'notification_email_queued' : 'notification_delivery_failed', [
        'source' => (string) ($metadata['source'] ?? 'notification_queue'),
        'status' => $queued ? 'queued' : 'failed',
        'reason' => $queued ? '' : 'email_queue_insert_failed',
        'event_key' => (string) ($metadata['event_key'] ?? ''),
        'template_key' => $templateKey,
        'recipient_user_id' => $recipientId,
        'recipient_type' => (string) ($metadata['recipient_type'] ?? 'user'),
        'notification_id' => $notificationId,
        'title' => $subject,
        'message' => $body,
        'link' => $link,
        'delivery_channels' => [$queued ? 'email_queue' : 'email_queue_failed'],
    ], $queued ? 'info' : 'error');

    return $queued;
}

function notificationEmailQueueAbsoluteLink(?string $link): ?string
{
    return notificationEmailQueueService()->absoluteLink($link);
}

function notificationEmailQueueBuildHtml(array $row): string
{
    return notificationEmailQueueService()->buildHtml($row);
}

function notificationProcessEmailQueue(PDO $pdo, int $limit = 25, bool $dryRun = false, ?callable $sender = null): array
{
    return notificationEmailQueueService()->process($pdo, $limit, $dryRun, $sender);
}

function notificationRenderEventTemplate(string $template, array $payload): string
{
    return notificationTemplateService()->render($template, $payload);
}

function notificationEventPayloadValue(array $payload, string $key, string $default = ''): string
{
    return notificationTemplateService()->payloadValue($payload, $key, $default);
}

function notificationDispatch(
    PDO $pdo,
    string $eventKey,
    int $recipientId,
    ?int $actorId,
    string $entityType,
    int $entityId,
    array $payload = []
): bool {
    return notificationDispatchService()->dispatch(
        $pdo,
        $eventKey,
        $recipientId,
        $actorId,
        $entityType,
        $entityId,
        $payload
    );
}
