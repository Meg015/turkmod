# BanAppeals Module Notes

This folder is the module-owned boundary for `App\Modules\BanAppeals`.
Ban appeal submission, user reply, admin moderation, status
notification, and unban-on-accept behavior must live here. Procedural helpers
for ban appeals stay as thin service delegates only. Ban restriction
checks and ban/unban write operations belong in `Engine\Users`, not
this module.

## Ownership Boundary

- `Services/BanAppealService.php` owns appeal CRUD, user submission/reply, admin moderation, status labels, and unban-on-accept delegation.
- `Services/BanAppealSchemaService.php` owns schema readiness checks; module migrations own DDL.
- `Services/BanAppealNotificationService.php` owns appeal status-update notification dispatch.
- `Services/BanAppealsLifecycle.php` owns module install/uninstall migrations.
- `module.php` is the single metadata source for permissions, events, lifecycle, migrations, routes, and lang.

## Do Not Re-Introduce

- Do not move appeal business logic back into `includes/modules/users/helpers.php` inline implementations.
- Do not duplicate ban/unban SQL inside this module; delegate to `Engine\Users\BanService`.
- Do not write appeal SQL directly in page controllers; delegate through service helpers.
- Do not add ban restriction check logic to this module; ban checks belong in `Engine\Users\BanCheck`.

## Security and Behavior Contracts

- `ban-appeals.php` POST must call `verify_csrf_token()`.
- Appeal submission is auth-gated (login required).
- Admin appeal moderation in `admin/users.php` POST must call `verify_csrf_token()`.
- `admin/users.php` appeal actions (`appeal_update`, `bulk_appeal_update`) delegate to `usersUpdateBanAppeal()`.
- When an appeal is accepted, `BanAppealService::update()` calls `unbanUser()` which delegates to `usersUnban()` -> `Engine\Users\BanService::unban()`.
- Appeal status notifications are dispatched via `BanAppealNotificationService::dispatchUpdate()`.

## Permissions

- `ban_appeals.view` — view ban appeals (admin). Sourced from `module.php` via `usersModulePermissionCatalog()`.
- `ban_appeals.manage` — moderate ban appeals, update status/note (admin). Sourced from `module.php`.
- `ban_appeals.create` — submit ban appeals (default: true for users). Sourced from `module.php`.

## Events

- `ban_appeal.created` — emitted when a new appeal is created.
- `ban_appeal.message_created` — emitted when a message is added to an appeal thread.
- `ban_appeal.updated` — emitted when admin updates appeal status/note.
- These are declared in `module.php` events metadata.

## Migration Rules

- New module classes use `App\Modules\BanAppeals\*` namespace with PSR-4 paths.
- Compatibility helpers in `includes/modules/users/helpers.php` (ban appeal functions) must delegate to module services, not contain inline logic.
- `ban-appeals.php` page entrypoint must remain a thin-to-moderate wrapper.
- `admin/users-tabs/appeals.php` is a view partial that must not contain inline SQL.
- Ban restriction checks (`usersGetAccessRestriction`, `usersHasRestriction`, `usersRestrictedPathAllowed`) are owned by `Engine\Users\BanCheck`, not this module.
- Ban/unban write operations (`usersBan`, `usersUnban`) are owned by `Engine\Users\BanService`, not this module.
- `BanAppealService::unbanUser()` must delegate to `usersUnban()` (engine), not duplicate SQL.
