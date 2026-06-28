# Events Module Notes

This folder is the module-owned boundary for `App\Modules\Events`.
Public pages, API routing adapters, services, lifecycle hooks, module metadata,
translations, and future migrations for events must live here.

## Ownership Boundary

- `Pages/` owns public events page aliases for `/events`, `/events/wheel`,
  `/events/raffle`, `/events/rewards`, and `/events/tasks`.
- `Api/EventsApiHandler.php` owns the `/events/api/*` Handler adapter while
  compatibility API scripts live under `LegacyRuntime/api/`.
- `Legacy/LegacyEventsBridge.php` is the only bridge for legacy events includes.
- `LegacyRuntime/` owns migrated legacy-compatible page/admin/api/helper runtime
  files that wrappers delegate to.
- `Services/` owns events lifecycle and future non-trivial events business logic.
- `Database/migrations/` owns events-specific schema migrations only.
- `lang/` owns module translations.
- `routes.php` is the single module route/config source consumed by `route.php`.
- `module.php` is the single metadata source for permissions, config schema,
  admin menu entries, lifecycle, migrations, language path, and events map.

## Do Not Re-Introduce

- Do not add new business logic directly into `LegacyRuntime/*`; treat it as a
  compatibility boundary while extracting reusable logic into module services.
- Do not add new hardcoded events route maps directly in `route.php`; update
  `routes.php` and keep `route.php` as the compatibility dispatcher.
- Do not bypass `EventsApiHandler` for `/events/api/*`.
- Do not bypass `App\Core\Routing\AssetRouteAdapter` for `/events/assets/*`.
- Do not duplicate events feature gates, rate limits, reward logic, raffle draw
  logic, or task claim logic across wrapper and service layers.

## Security and Behavior Contracts

- Keep POST API CSRF checks through `eventsApiVerifyCsrf()` / `verify_csrf_token()`.
- Keep anonymous API behavior backward compatible; protected endpoints return
  JSON 401 via the legacy API response helper.
- Keep events API response helpers on the existing JSON envelope:
  `success`, `message`, optional `error`, optional `code`, optional `_token`,
  and endpoint-specific data keys.
- Keep session-backed events API rate limiting from events config:
  `api_rate_limit_window` and `api_rate_limit_max`.
- Permission gates are `events.view` and `events.manage`.
- Missing `/events/api/*` targets must keep the themed 404 behavior.
- Event assets must keep legacy MIME behavior and
  `Cache-Control: public, max-age=3600`.

## Migration Rules

- Legacy lowercase `includes/modules/events` has been removed in Phase 11;
  do not re-introduce that tree.
- New PHP classes must use the `App\Modules\Events\*` namespace and PSR-4 paths.
- Legacy page/API shims may include existing files, but new reusable logic must
  move into `Services/` or another module-owned class.
- Future raffle/task/reward services should receive explicit dependencies where
  practical and keep direct SQL in one service boundary per behavior.
- Module migrations may create/drop events-owned tables only; do not mutate
  unrelated schema.

## Validation Checklist

- `php -l` for each touched PHP file.
- `vendor/bin/phpunit`
- `powershell -ExecutionPolicy Bypass -File scripts/ui-smoke-check.ps1`
- Live smoke for representative events routes:
  `/events`, `/events/api/tasks`, and one `/events/assets/*` CSS file.
- Compatibility checks including the future `EventsCompatibilityTest`.
