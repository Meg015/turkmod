# Reports Module Notes

This folder is the module-owned boundary for `App\Modules\Reports`.
User report and topic report submission, listing, event history,
status update, and status-notification behavior must live here.
Procedural adapters in `includes/src/Modules/Reports/Support/helpers.php`
stay as thin service delegates only.

## Ownership Boundary

- `Services/UserReportService.php` owns user complaint submission, listing, event history, and status updates.
- `Services/TopicReportService.php` owns topic report submission, listing, event history, and status updates.
- `Services/ReportsSchemaService.php` owns schema readiness checks; module migrations own DDL.
- `Services/ReportNotificationService.php` owns report status-update notification dispatch.
- `Services/ReportsLifecycle.php` owns module install/uninstall migrations.
- `module.php` is the single metadata source for permissions, config, events, lifecycle, migrations, routes, and lang.

## Do Not Re-Introduce

- Do not move report business logic back into `includes/src/Modules/Reports/Support/helpers.php` inline implementations.
- Do not write report SQL directly in API endpoints or admin controllers; delegate through service helpers.
- Do not add report permissions to the hardcoded `usersPermissionCatalog()`; they are sourced from `module.php` via `usersModulePermissionCatalog()`.
- Do not duplicate report status-update logic across admin pages.

## Security and Behavior Contracts

- `api/reports.php` and `api/user-reports.php` POST must call `verify_csrf_token()`.
- Public topic report submission accepts both members and visitors, with CSRF protection and dual rate limiting (IP + reporter identity).
- Admin moderation (`admin/complaints-reports.php`) gates on `reports.view` (page) and `reports.manage` (POST) via `adminRequirePermission()`.
- `admin/complaints-reports.php` POST must call `verify_csrf_token()`.
- Report status updates must append an event history row and dispatch a status notification to the reporter.

## Permissions

- `reports.view` - view topic reports and user complaints (admin). Sourced from `module.php` via `usersModulePermissionCatalog()`.
- `reports.manage` - moderate topic reports and user complaints (admin). Sourced from `module.php`.
- `reports.create` - create topic reports and user complaints (default: true). Declared in `module.php`; enforcement at public API is deferred until the permission system supports module-declared defaults.
- Permission aliases: `reports.view` is accepted when `reports.manage` is held (via `usersPermissionAliases()`).

## Migration Rules

- New module classes use `App\Modules\Reports\*` namespace with PSR-4 paths.
- Support helpers must delegate to module services, not contain inline logic.
- API entrypoints (`api/reports.php`, `api/user-reports.php`) must remain thin wrappers with rate limiting and CSRF.
- `admin/complaints-reports.php` is a known thick endpoint (546 lines) that will be further thinned in later phases.
- Report permissions are the single source of truth from `module.php`; the hardcoded catalog entries were removed in Phase 9.7.
