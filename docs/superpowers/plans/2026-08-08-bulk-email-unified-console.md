# Bulk Email Unified Console Implementation Plan

## 1. Settings Ownership

- Split `admin_notification_email_settings_schema()` into normal queue settings and a dedicated bulk settings schema.
- Add a `save_bulk_email_settings` POST action with the same CSRF, permission, bounds, persistence, audit, and redirect conventions.
- Keep `save_email_settings` limited to event-notification queue settings so `email_group=settings` no longer renders or writes `notif_bulk_email_*` keys.

## 2. Operational Snapshot

- Add a small Notification Center helper that reads the latest `bulk_email_campaigns` cron run from `application_logs` and bounded pending/processing/failed recipient counts from the bulk tables.
- Keep schema-missing behavior explicit: show a warning and do not query bulk tables when the migration is absent.
- Pass settings, operational counts, and cron metadata through `adminNotificationsPageData` for client-side state refresh.

## 3. Unified Bulk View

- Extend the existing bulk workspace with a compact status strip for worker state, cron freshness/result, eligible users, pending/processing, and failures.
- Add a single bulk delivery settings form containing worker enabled, batch size, and max attempts.
- Add a collapsible operations panel with the safe CLI command, protected HTTP endpoint placeholder, latest cron details, and last result counts.
- Keep composer, exact preview, test recipient, active campaign controls, and history in the same page flow.

## 4. Client State And Controls

- Extend `initBulkEmailCampaigns()` to refresh operational status while polling campaign progress.
- Make lifecycle controls state-aware for preparing/queued/sending/paused/completed/cancelled and disable start when worker/schema/permission prerequisites are unavailable.
- Preserve existing draft, preview, test, pause/resume/cancel/retry behavior and error handling.

## 5. Styling And Verification

- Add scoped status/settings/operations styles with the existing admin visual language and responsive one-column mobile layout.
- Run PHP/JavaScript lint, bulk service verification, migration guard, and authenticated Playwright desktop/mobile checks.
- Confirm the normal queue settings page no longer contains bulk fields and the bulk page contains every bulk control.
