# Leaderboard Module Notes

This folder is the module-owned boundary for `App\Modules\Leaderboard`.
Public, API, admin, service, lifecycle, and migration logic for leaderboard
must live here. Root/admin/API entrypoints must remain thin adapters.

## Ownership Boundary

- `Http/LeaderboardPage.php` and `Http/leaderboard-page-content.php` own public page rendering.
- `Api/LeaderboardApi.php` owns API payload and validation flow.
- `Admin/LeaderboardAdminPage.php` and `Admin/leaderboard-admin-content.php` own admin leaderboard UI flow.
- `Services/` owns leaderboard domain logic, caching, invalidation, and lifecycle hooks.
- `Database/migrations/` owns leaderboard-specific schema migrations only.
- `module.php` is the single metadata source for routes, permissions, config schema, events, lifecycle, and migrations.

## Do Not Re-Introduce

- Do not move business logic back into:
  - `leaderboard.php`
  - `api/leaderboard.php`
  - `admin/leaderboard.php`
  - `includes/src/Modules/Leaderboard/Support/*.php` (these are procedural adapters)
- Do not duplicate leaderboard logic across service and wrapper layers.
- Do not add direct SQL/business logic into shim files; keep wrappers delegation-only.

## Security and Behavior Contracts

- Keep POST CSRF checks (`verify_csrf_token`) in admin update flows.
- Keep rate limit behavior in leaderboard API.
- Keep the stable API response shape:
  - `success`, `category`, `period`, `data`, `total`, `limit`, `offset`,
    `calculated_at`, `is_cached`, `period_range.start`, `period_range.end`.
- Permission gate for module admin is `leaderboard.admin`

## Events and Lifecycle

- Event map lives in `module.php`:
  - `topic.published` -> `Services\LeaderboardCacheInvalidator`
- Lifecycle handler:
  - `Services\LeaderboardLifecycle`
- Cache tag contract:
  - invalidate tag `leaderboard` on relevant module/event actions.
- Migration contract:
  - only create/drop leaderboard-owned tables; do not mutate unrelated schema.

## Validation Checklist

- `php -l` for each touched PHP file.
- `vendor/bin/phpunit`
- `powershell -ExecutionPolicy Bypass -File scripts/ui-smoke-check.ps1`
- Compatibility checks including `tests/Compatibility/LeaderboardCompatibilityTest.php`.
