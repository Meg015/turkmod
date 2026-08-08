# Admin Bulk Email Campaigns Implementation Plan

1. Add an idempotent Notifications module migration for `bulk_email_campaigns` and `bulk_email_recipients`, including foreign keys, state indexes, counters, snapshot checkpoints, and worker lock columns. Mirror the resulting tables in `database/schema.sql`.
2. Add module-owned services:
   - `BulkEmailContentService` for token validation, Quill HTML sanitization, absolute assets, shared rendering, preview data, and mail delivery.
   - `BulkEmailCampaignService` for drafts, eligible recipient snapshots, state transitions, history, progress, retry, and reconciliation.
   - `BulkEmailWorkerService` for sequential campaign selection, batch claims, retry/backoff, stale-lock recovery, sending, and terminal state updates.
3. Add thin admin JSON endpoints for preview/progress and campaign mutations, each enforcing Notification permissions and CSRF. Add a dedicated cron entrypoint and health/config metadata.
4. Extend `admin/notifications.php` with a `bulk` email group that loads only presentation data from services and renders composer, exact iframe preview, test recipient, active progress, history, and state-aware controls.
5. Extend `admin/assets/notifications-page.js` with Quill initialization, token insertion, debounced server preview, sandboxed iframe updates, test/start confirmation, progress polling, and mutation controls.
6. Extend the existing admin CSS with a restrained operations-console layout that matches current controls, remains dense and scannable, and has stable desktop/mobile preview dimensions.
7. Verify PHP syntax, migration idempotency, sanitizer/token/state/worker behavior with deterministic sender callbacks, and unchanged notification-queue behavior. Run the local site and use Playwright CLI for authenticated desktop/mobile UI and preview checks where credentials/session permit.
