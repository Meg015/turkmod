<?php

declare(strict_types=1);

use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Notifications\Services\NotificationEmailQueueService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Notifications\Services\NotificationSchemaService;
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

function notificationDispatchService(): NotificationDispatchService
{
    static $service = null;

    return $service ??= new NotificationDispatchService(
        notificationPreferenceService(),
        notificationSchemaService(),
        notificationTemplateService(),
        notificationEmailQueueService()
    );
}

function notificationEventDefinitions(): array
{
    return notificationPreferenceService()->eventDefinitions();
}

function notificationEventPreferenceItems(): array
{
    return notificationPreferenceService()->eventPreferenceItems();
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

function notificationPreferenceWhereSql(array $settings, string $alias = 'n', bool $filterEvents = true): array
{
    return notificationPreferenceService()->whereSql($settings, $alias, $filterEvents);
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

function notificationEnsureEmailQueueSchema(PDO $pdo): void
{
    notificationSchemaService()->ensureEmailQueueSchema($pdo);
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
    return notificationEmailQueueService()->queue(
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
