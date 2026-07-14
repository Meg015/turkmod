# Legacy Cleanup and Hard Cutover Design

## Purpose

Remove obsolete database structures, compatibility entrypoints, runtime schema
mutation, transitional wrappers, dead feature surfaces, and unused generated
assets without losing active data or breaking the current public, admin, API,
Events, messaging, reporting, leaderboard, notification, upload, or edit flows.

The delivered release is a hard cutover. Removed legacy `.php` URLs return 404;
no one-release redirect or compatibility period is retained.

## Scope

This cleanup covers:

- executable maintenance and smoke-test scripts exposed below the web root;
- one-time XenForo import and username recovery artifacts in the current tree;
- duplicate settings storage in `settings` and `admin_settings`;
- obsolete and empty database tables and unused topic rating columns;
- failed or incomplete root migrations;
- runtime DDL and old-schema normalization paths;
- deprecated database aliases, permission aliases, and older-schema fallbacks;
- Events migration ownership and the Events legacy bridge/runtime;
- legacy root and admin route wrappers;
- broken lint/build commands and unused generated assets;
- duplicate stylesheet injection;
- disabled search and synchronous queue placeholders;
- legacy file-cache and rate-limit serialization compatibility.

Git history rewriting is explicitly out of scope. Personal data is removed from
the current source tree and live database only.

Presentation resilience is also out of scope: default avatars, missing-image
handling, safe empty-state text, and equivalent user-facing defaults remain.

## Delivery Strategy

Implementation is internally phased but released as one compatible package.
The new code must stop reading an old structure before the migration drops it.
All production database mutations are exposed through Admin Panel > Database
Synchronization. Direct manual SQL is not required for normal deployment.

The release order is:

1. close exposed tooling and repair validation commands;
2. make new code independent of legacy database structures;
3. add idempotent data/schema migrations;
4. remove compatibility routes, wrappers, and assets;
5. apply the complete migration set locally;
6. run full regression and residue checks;
7. deploy code, take a production database backup, then apply pending migrations
   from Database Synchronization.

## Safety Rules

- Preserve all existing active topic, user, comment, message, notification,
  report, download, Events, and settings data.
- Do not overwrite unrelated staged or unstaged workspace changes.
- Take a local database dump before applying destructive cleanup migrations.
- Require a production database backup before the cleanup migration is applied.
- Destructive migrations must validate that prerequisite data was copied and
  consumers were migrated before dropping a table or column.
- Migration operations must be idempotent through table, column, and index
  existence checks.
- A failure must stop the migration and leave an actionable error in the
  Database Synchronization report.
- Do not silently fall back to an older table or schema after this release.

## Database Design

### Topic report migration repair

Repair `2026_07_14_0002_add_topic_report_snapshot_columns` so it safely adds:

- `topic_reports.reporter_name`
- `topic_reports.reporter_email`
- `topic_reports.reporter_type`
- `topic_reports_reporter_email_index`

The migration must work on MariaDB and SQLite test databases. The topic report
service may use these columns only after the migration is locally verified.

### Settings consolidation

`admin_settings` becomes the sole global settings source.

Before dropping `settings`:

1. copy every key missing from `admin_settings`, including the 48 scraper keys;
2. verify that common keys contain matching values;
3. move Scraper, Leaderboard, Admin, and all other direct readers to the shared
   canonical settings service;
4. remove dual writes;
5. remove the legacy settings cleanup cron;
6. drop `settings` only after no runtime SQL references remain.

### Reports consolidation

Private profile report history moves from obsolete `reports` to
`topic_reports`, using `reporter_user_id` as ownership. The profile query must
retain topic title, slug, category, reason, details, status, admin note, and
timestamps required by the current renderer.

After all consumers use the module-owned tables, drop:

- `reports`
- `report_events`

### Obsolete table removal

Drop the following only after a final zero-row and reference check:

- `blocked_ips`
- `failed_login_attempts`
- `suspicious_activities`
- `ratings`
- `reactions`
- `pages`
- `permissions`
- `users_username_backup_20260710_184907`

The obsolete `reactions` metric in admin user details is removed or replaced by
the active `comment_reactions` source as appropriate.

### Topic rating surface removal

Because the rating tables and all rating aggregates are empty and no rating
write endpoint exists, remove:

- `topics.rating_average`
- `topics.rating_count`
- `topics.like_count`
- rating sort options from public listing and topic APIs;
- rating structured-data output;
- rating analytics methods and UI explanations;
- rating-related unused Events task metadata.

Comment reactions and topic favorites remain because they are active features.

### Permission migration

Migrate leaderboard group permissions to `leaderboard.admin`. Remove direct
checks and aliases for `leaderboard.view` and `leaderboard.manage` only after
the new permission has been granted to every group that held either old key.

### Events migration ownership

Create module migrations that describe all 23 current `events_*` tables and
required indexes without deleting or rewriting the existing 109 Events rows.
On an existing database the migration uses `CREATE TABLE IF NOT EXISTS` and
missing-column/index checks, then records itself in `events_migrations`.

Events seed data must be separated from schema creation and remain idempotent.
No Events schema mutation remains in normal requests after migration ownership
is established.

## Code Architecture Cleanup

### Runtime schema mutation

Move all application-owned DDL into root or module migrations. Remove runtime
creation, alteration, renaming, and dropping from:

- `ensureAdminSchema()`;
- security logging;
- users, reports, messages, notifications, contact, ban appeals, leaderboard,
  email, admin quality, user activity, scraper, topics, and Events helpers;
- database cache and database rate limiter initialization where applicable.

Runtime services may perform read-only schema readiness assertions. Missing
required schema produces an explicit migration-required error rather than a
silent feature fallback.

### Old data normalization

Remove `adminNormalizeLegacyTopicStatuses()` and its admin bootstrap call after
confirming no `pending` or `archived` topic statuses remain. Any equivalent
one-time normalization belongs in a migration.

### Database access

Move remaining callers from deprecated `App\Core\Database` to
`DatabaseConnection` or injected PDO/config dependencies. Remove:

- the deprecated facade subclass;
- `Boot::requireLegacyDatabase()`;
- compatibility class loading that exists only for the old alias.

### Settings service

Make the OOP settings abstraction canonical. It must read only
`admin_settings`, provide validated defaults, support cache invalidation, and
be injectable into services. Procedural helpers may temporarily delegate to
it during the same implementation branch, but are deleted before release when
no callers remain.

### Module wrappers

Move service consumers away from global functions so isolated tests do not
require the full legacy bootstrap. In particular:

- Leaderboard receives profile URL/presentation dependencies directly;
- Messages receives its route URL resolver directly;
- Contact, Reports, Notifications, and Leaderboard stop requiring Legacy helper
  files from handlers;
- compatibility helper files are deleted after reference count reaches zero.

### Rate limiting

Use one database-backed rate limiter because the admin rate-limit screen and
cleanup cron already operate on `request_rate_limits`.

Remove:

- file-backend enforcement for application requests;
- file-to-database mirroring;
- `legacyRateLimit*` database fallback helpers;
- serialized legacy rate-limit parsing;
- stale files under `storage/cache/rate-limits` after cutover.

Rate-limit failure must fail closed for protected write endpoints and return a
clear operational error if the configured backend cannot initialize.

### Cache compatibility

Keep the configured canonical cache backend, but remove PHP serialized-entry
compatibility after clearing and rebuilding the current cache. Cache backend
operational policy is explicit; old data-format compatibility is not retained.

### Search and queue placeholders

The current search listener cannot target `DisabledSearchEngine`. Because no
real external search backend exists in this release, remove the unused search
event listener, job, disabled implementation, and binding. Existing SQL search
remains the canonical search implementation.

Remove the queue abstraction only where it exists solely to run jobs through
`SyncQueue`. Notification delivery and other active behavior must remain
synchronous and explicit unless a real queue backend is introduced later.

## Events Refactor

Events cleanup is implemented in bounded slices but merged only when all slices
are complete:

1. create canonical Events schema migrations and seed services;
2. move API behavior from `LegacyRuntime/api` into typed handlers/services;
3. move public page preparation into module page handlers/services;
4. move admin actions and queries into module admin controllers/services;
5. move reusable procedural helpers into focused services;
6. update routes and assets to target canonical module files;
7. delete `Api/Legacy`, `LegacyEventsBridge`, and `LegacyRuntime`;
8. verify every public/admin/API Events route and cron behavior.

Existing Events URLs, response envelopes, permissions, CSRF behavior, and all
stored Events data remain unchanged.

## Routing Hard Cutover

Delete the following compatibility entrypoints:

- `messages.php`
- `admin/reports.php`
- `admin/user-reports.php`

Remove obsolete sitemap rewrites targeting non-existent PHP files, old
`/leaderboard.php` sidebar markers, and duplicated Events default route maps.

Only canonical friendly routes remain. Requests to removed `.php` URLs return
the standard themed 404 with no 301 or 302 compatibility redirect.

## Tooling and Asset Cleanup

### Executable scripts

Block HTTP access to `scripts/` before deleting or relocating executable smoke
tests. Smoke tests that remain useful move to the test suite and enforce CLI
execution. Remove one-time XenForo import/recovery utilities from the current
tree.

### Build and lint

Restore working implementations for:

- `composer lint`
- `npm run build`
- `npm run build:js`
- `npm run build:css`
- `npm run watch`

Generated outputs must be reproducible from tracked source files.

### Assets

After the build pipeline is authoritative:

- remove unused Events minified duplicates or switch runtime references to the
  generated canonical files, but do not keep both unused copies;
- remove unused `bundle-listing.min.css` if no route loads it;
- remove the production source map containing a local temporary source path;
- remove duplicate leaderboard stylesheet injection;
- remove already identified temporary screenshots and regression artifacts.

## Error Handling

- Database Synchronization reports the exact migration and prerequisite when a
  cleanup step cannot continue.
- No destructive migration catches and suppresses SQL exceptions.
- Services do not silently query an older table when the canonical table is
  absent.
- Removed route requests use the normal themed 404 response.
- Missing schema produces a migration-required error and an application log
  entry without exposing SQL details publicly.
- Existing public API response formats remain stable for retained endpoints.

## Verification

The cleanup is complete only when all checks pass:

1. every PHP file passes syntax validation;
2. `composer validate` and `composer lint` pass;
3. the complete PHPUnit suite passes with no errors or skipped compatibility
   failures caused by missing global functions;
4. NPM dependencies install from the lockfile and all build commands pass;
5. generated asset diff is understood and reproducible;
6. migration preview lists only the intended pending migrations;
7. migrations apply successfully to a local backup copy and the active local
   database;
8. a clean database can be built from base schema plus migrations;
9. public, auth, profile, upload, edit, download, messages, notifications,
   leaderboard, contact, reports, and Events smoke checks pass;
10. representative admin pages and APIs retain permission, CSRF, and auth
    behavior;
11. removed `.php` routes return 404;
12. database queries confirm every targeted table and column is absent;
13. code search confirms no references to removed tables, aliases, wrappers,
    or compatibility functions remain;
14. live-rendered pages contain no duplicate leaderboard CSS;
15. application, PHP, and web-server logs contain no new SQL, schema, route,
    search, queue, or fallback errors.

## Production Deployment

1. deploy the complete code and generated assets together;
2. place the application in maintenance mode if available;
3. take and verify a full production database backup;
4. open Admin Panel > Database Synchronization;
5. preview pending migrations and confirm only the cleanup set is listed;
6. apply pending migrations once;
7. run public/admin/API smoke checks and inspect logs;
8. exit maintenance mode only after verification succeeds.

The release must not be deployed partially. New code with old migrations, or
new migrations with old code, is unsupported.

## Completion Criteria

The project is considered clean when the targeted tables, columns, wrappers,
one-time scripts, runtime DDL, compatibility aliases, dead rating/search paths,
unused assets, and old routes are absent; canonical flows pass all automated
and live tests; Database Synchronization is the only required production DB
change mechanism; and no compatibility fallback silently restores old behavior.
