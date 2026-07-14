# Notifications Module Notes

This folder is the module-owned boundary for `App\Modules\Notifications`.
Notification center, preference filtering, template rendering, email
queue fan-out, and event subscription behavior must live here. Procedural
helpers in `includes/notifications.php` stay as thin service delegates only.

## Ownership Boundary

- `Services/NotificationCenterService.php` owns dropdown payload and read-marking.
- `Services/NotificationPreferenceService.php` owns event definitions, preference filtering, and where-SQL generation.
- `Services/NotificationSchemaService.php` owns schema readiness checks; module migrations own DDL.
- `Services/NotificationTemplateService.php` owns template CRUD, render, validate, and defaults.
- `Services/NotificationEmailQueueService.php` owns email queue stats, recipient lookup, queue/build/process.
- `Services/NotificationDispatchService.php` owns dispatch orchestration and synchronous email-queue fan-out.
- `cron/send-notification-email-queue.php` invokes the email queue service directly.
- `Listeners/NotificationEventSubscriber.php` owns event subscriptions for topic.published, comment.created, report.created.
- `module.php` is the single metadata source for permissions, config, events, lifecycle, migrations, and lang.

## Do Not Re-Introduce

- Do not move notification business logic back into `includes/notifications.php` inline implementations.
- Do not bypass `NotificationDispatchService` when fanning out notification emails.
- Do not write notification SQL directly in API endpoints or page controllers; delegate through service helpers.
- Do not add new notification event subscriptions outside `module.php` events metadata.

## Security and Behavior Contracts

- `api/notifications-read.php` POST must call `verify_csrf_token()`.
- `api/notifications.php` GET is auth-gated (session user id required).
- Notification email queue cron secret comparison must use `hash_equals()` (timing attack safe).
- `NotificationCenterService::markRead()` must validate notification ownership before marking read (cross-user rejection).
- `NotificationDispatchService` must respect user preference filtering before dispatching.

## Events and Delivery Contracts

- `module.php` event subscriptions are canonical:
  - `topic.published` -> `NotificationEventSubscriber` (dispatches `topic_approved` to topic author)
  - `comment.created` -> `NotificationEventSubscriber` (dispatches `comment_on_topic`/`comment_reply`)
  - `report.created` -> `NotificationEventSubscriber` (dispatches report-specific notification events)
- `NotificationEventSubscriber` handlers safely no-op when required payload data is missing.
- `NotificationDispatchService` writes email work to `notification_email_queue` synchronously.
- `cron/send-notification-email-queue.php` processes persisted email work directly through `NotificationEmailQueueService`.
- Listener/job failures must be logged (`appLogException` or `error_log`), not silently swallowed.

## Permissions

- `notifications.view` — view notification history, templates, and dispatch logs (admin).
- `notifications.manage` — manage notification templates and settings (admin).
- `notifications.dispatch` — dispatch manual announcements (admin).
- These are declared in `module.php` and aggregated into the permission catalog via `usersModulePermissionCatalog()`.

## Migration Rules

- New module classes use `App\Modules\Notifications\*` namespace with PSR-4 paths.
- Compatibility helpers in `includes/notifications.php` must delegate to module services, not contain inline logic.
- API entrypoints (`api/notifications.php`, `api/notifications-read.php`) must remain thin wrappers.
- `notifications.php` page is a known thick endpoint (762 lines) that will be further thinned in later phases.
- Theme header notification menu integration uses `Engine\Themes\ThemeHeaderViewData` view data, not inline PHP.
