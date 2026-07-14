<?php

declare(strict_types=1);

use App\Modules\Contact\Services\ContactCategoryService;
use App\Modules\Contact\Services\ContactMailService;
use App\Modules\Contact\Services\ContactMessageService;
use App\Modules\Contact\Services\ContactSchemaService;

function contactSchemaService(): ContactSchemaService
{
    static $service = null;

    return $service ??= new ContactSchemaService();
}

function contactCategoryService(): ContactCategoryService
{
    static $service = null;

    return $service ??= new ContactCategoryService(contactSchemaService());
}

function contactMailService(): ContactMailService
{
    static $service = null;

    return $service ??= new ContactMailService();
}

function contactMessageService(): ContactMessageService
{
    static $service = null;

    return $service ??= new ContactMessageService(contactSchemaService(), contactCategoryService(), contactMailService());
}

function contactEnsureSchema(PDO $pdo): void
{
    contactSchemaService()->ensureSchema($pdo);
}

function contactEnsureCategoriesTable(PDO $pdo): void
{
    contactSchemaService()->ensureCategoriesTable($pdo);
}

function contactEnsureMessagesTable(PDO $pdo): void
{
    contactSchemaService()->ensureMessagesTable($pdo);
}

/**
 * @return list<array<string,mixed>>
 */
function contactCategories(PDO $pdo, bool $activeOnly = false): array
{
    return contactCategoryService()->all($pdo, $activeOnly);
}

/**
 * @return array<string,mixed>|null
 */
function contactCategory(PDO $pdo, int $categoryId): ?array
{
    return contactCategoryService()->find($pdo, $categoryId);
}

/**
 * @return array<string,mixed>|null
 */
function contactCategoryBySlug(PDO $pdo, string $slug): ?array
{
    return contactCategoryService()->findBySlug($pdo, $slug);
}

/**
 * @param array<string,mixed> $input
 * @return array{success:bool,message:string,category:?array<string,mixed>,id:int,slug:string}
 */
function contactSaveCategory(PDO $pdo, array $input, ?int $categoryId = null): array
{
    return contactCategoryService()->save($pdo, $input, $categoryId);
}

function contactDeleteCategory(PDO $pdo, int $categoryId): bool
{
    return contactCategoryService()->delete($pdo, $categoryId);
}

function contactToggleCategory(PDO $pdo, int $categoryId, bool $active): bool
{
    return contactCategoryService()->toggleActive($pdo, $categoryId, $active);
}

/**
 * @return array<string,array{label:string,class:string,icon:string}>
 */
function contactMessageStatusLabels(): array
{
    return contactMessageService()->statusLabels();
}

/**
 * @return array<string,array{label:string,class:string,icon:string}>
 */
function contactMessageEmailStatusLabels(): array
{
    return contactMessageService()->emailStatusLabels();
}

/**
 * @return array{total:int,new:int,replied:int,resolved:int,unseen:int}
 */
function contactMessageStats(PDO $pdo): array
{
    return contactMessageService()->stats($pdo);
}

/**
 * @param array<string,mixed> $filters
 */
function contactMessageCount(PDO $pdo, array $filters = []): int
{
    return contactMessageService()->count($pdo, $filters);
}

/**
 * @param array<string,mixed> $filters
 * @return list<array<string,mixed>>
 */
function contactMessages(PDO $pdo, array $filters = []): array
{
    return contactMessageService()->list($pdo, $filters);
}

/**
 * @return array<string,mixed>|null
 */
function contactMessage(PDO $pdo, int $messageId): ?array
{
    return contactMessageService()->find($pdo, $messageId);
}

function contactMarkMessageSeen(PDO $pdo, int $messageId): bool
{
    return contactMessageService()->markSeen($pdo, $messageId);
}

/**
 * @param array<string,mixed> $input
 * @return array{success:bool,message:string,id:int,mail_sent:bool,mail_error:string,status:string}
 */
function contactSubmitMessage(PDO $pdo, array $input, ?int $userId = null): array
{
    return contactMessageService()->submit($pdo, $input, $userId);
}

/**
 * @return array{success:bool,message:string,mail_sent:bool,mail_error:string,status:string}
 */
function contactReplyToMessage(PDO $pdo, int $messageId, string $replyBody, int $adminId = 0, string $adminName = 'Yonetim'): array
{
    return contactMessageService()->reply($pdo, $messageId, $replyBody, $adminId, $adminName);
}

function contactResolveMessage(PDO $pdo, int $messageId): bool
{
    return contactMessageService()->resolve($pdo, $messageId);
}

function contactDeleteMessage(PDO $pdo, int $messageId): bool
{
    return contactMessageService()->delete($pdo, $messageId);
}

function contactOpenMessageCount(PDO $pdo): int
{
    return contactMessageService()->openCount($pdo);
}

/**
 * @return list<array{id:int,name:string,email:string}>
 */
function contactAdminRecipients(PDO $pdo): array
{
    return contactMailService()->adminRecipients($pdo);
}
