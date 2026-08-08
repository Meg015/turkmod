# Bulk Email Unified Console Design

## Goal

Consolidate every bulk-email-specific control into the existing Notification Center URL `admin/notifications.php?tab=email&email_group=bulk`. An administrator must be able to compose, preview, test, configure, start, monitor, pause, resume, cancel, retry, and inspect bulk campaigns without leaving this view.

## Scope

The bulk view will own:

- Eligible-recipient, worker, cron, queue, and failure status.
- Active campaign progress and lifecycle controls.
- Subject, Quill body, personalization tokens, exact preview, and test delivery.
- Bulk worker enabled state, batch size, and maximum attempts.
- Cron command details and latest execution status.
- Campaign draft/history actions.

The existing `Kuyruk & Ayarlar` email subgroup remains available for normal event-notification email settings. The three `notif_bulk_email_*` settings are removed from that subgroup so bulk configuration has one visible owner.

## Page Structure

The view remains one continuous operational page rather than adding nested tabs or modal-only settings:

1. A status strip shows eligible members, worker enabled state, latest bulk cron result/time, pending recipients, and failed recipients.
2. The active campaign panel shows stable progress and state-aware lifecycle actions: pause, resume, cancel, and retry failed recipients.
3. The composer contains subject, the existing Quill integration, personalization controls, a separate test recipient, draft save, and campaign start.
4. The exact server-rendered desktop/mobile preview remains beside the composer on desktop and follows it on narrow screens.
5. A bulk delivery settings panel contains worker enabled, batch size, and maximum attempts with one explicit save action.
6. A collapsible operations area exposes CLI and HTTP cron commands plus the latest run diagnostic without placing secrets into page text beyond the existing protected admin convention.
7. Campaign history remains at the bottom of the same view.

## Behavior And Ownership

Bulk setting writes use the existing Notification Center settings POST flow, CSRF validation, permission checks, sanitization, and audit logging. Saving bulk settings returns to the bulk view and preserves its page context. Normal email queue settings continue to save through `email_group=settings`.

Lifecycle buttons remain backed by `admin/api/bulk-email-campaigns.php`. Their visibility and disabled state are derived from the persisted campaign status; the browser does not invent campaign state. Starting a campaign is available only when the worker is enabled and the bulk schema is ready. Pausing or cancelling remains available for an already active campaign even if the worker has subsequently been disabled.

The worker enabled toggle controls new background claims. Disabling it does not delete recipients or reset campaign counters. Re-enabling it lets cron continue from persisted pending rows.

## Cron And Health Status

The unified console reads the existing `bulk_email_campaigns` cron-run record and bounded aggregate recipient counts. It shows whether the last run succeeded, warned, failed, or has never run. CLI remains the recommended production command; the existing secret-protected HTTP endpoint is shown as a fallback.

Cron output and SMTP diagnostics are summarized and escaped. The page does not expose database credentials or mail credentials. Existing System Health and Admin Settings cron catalog integrations remain intact as secondary operational views, but day-to-day bulk control no longer requires navigating to them.

## Responsive Design

Desktop keeps the existing two-column composer/preview workspace. Status and settings use dense operational rows rather than decorative cards. Mobile stacks all controls, makes command buttons full width, allows tables to remain readable, and must not introduce horizontal page overflow. Fixed and sticky global admin navigation behavior remains unchanged.

## Error Handling

- Settings validation errors return to the bulk view and identify the invalid field.
- Campaign API errors remain in the campaign/composer error region.
- A missing migration disables unsafe actions and links to database synchronization.
- A missing or stale cron run produces an operational warning but does not discard a draft.
- Invalid batch size or retry values cannot be persisted outside enforced server bounds.

## Verification

- Confirm the three bulk settings render and save only from `email_group=bulk`.
- Confirm normal email queue settings still render and save in `email_group=settings`.
- Confirm each campaign state exposes only valid lifecycle controls.
- Confirm worker disable/enable preserves campaign and recipient state.
- Confirm latest cron status and bounded queue counts match database records.
- Confirm composer, exact preview, test recipient, settings, operations, and history are usable in one desktop and mobile page.
- Run PHP/JavaScript syntax checks, the bulk service verification script, migration guard, and authenticated Playwright checks with no console errors or horizontal overflow.

## Acceptance Criteria

- An authorized administrator can complete every routine bulk-email task from `admin/notifications.php?tab=email&email_group=bulk`.
- No `notif_bulk_email_*` field remains in the normal email queue settings form.
- Start, pause, resume, cancel, and retry controls are visible in the bulk view when their campaign states permit them.
- Worker, batch, retry, cron, queue, and failure status are visible without navigating away.
- Existing authorization, CSRF, audit, cron, worker, and mail-rendering guarantees remain unchanged.
