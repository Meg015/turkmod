# Admin Bulk Email Campaigns Design

## Goal

Add a durable bulk email workflow to the Admin Notification Center so an authorized administrator can compose, preview, test, start, monitor, pause, resume, cancel, and retry an email campaign for all eligible members. The workflow must remain reliable with 5,000-10,000 recipients and must not depend on the admin page remaining open.

## Scope

This change adds:

- A `Toplu E-posta` view under the existing Notification Center email area.
- A Quill-based campaign composer compatible with the site's current rich text editor.
- Server-rendered desktop and mobile email previews.
- A separate test-recipient field and real test-email delivery.
- Persistent campaign and recipient records.
- A cron-driven worker with ordered campaigns, batching, locking, retries, and crash recovery.
- Live progress, campaign history, pause/resume/cancel, and failed-recipient retry controls.

This change does not add scheduled delivery by date/time, arbitrary audience segmentation, raw HTML editing, or a third-party bulk-provider integration. Campaigns target the eligible member population defined below.

## Existing System Fit

The Notifications module remains the owner of this behavior. Business logic will live under `App\Modules\Notifications`; `admin/notifications.php`, admin API entrypoints, compatibility helpers, and cron scripts remain thin delegates.

The implementation reuses:

- The current admin Notification Center layout and permissions.
- The site's Quill rich text editor behavior and toolbar conventions.
- `appRenderMailLayout()` for the standard email shell.
- The existing application mail service and email logging path for delivery.
- Existing cron-run recording and health-monitoring conventions.

Bulk campaigns use dedicated tables instead of overloading `notification_email_queue`. The current queue requires a notification record and is designed for event-driven, per-user notification emails; bulk campaign lifecycle and progress are separate concerns.

## Recipient Eligibility

A campaign snapshot includes each user who meets all of these conditions when the administrator confirms the send:

- The account status is `active`.
- The account is not banned.
- The email field is non-empty and passes application email validation.

Inactive, banned, deleted, empty-email, and invalid-email accounts are excluded. Email verification is not an additional requirement. Recipient rows are deduplicated by campaign and user ID.

The recipient email and username are copied into the campaign recipient row. Later changes to the user record do not alter a campaign already in progress, which keeps the target count, personalization, audit trail, and retries deterministic.

## Data Model

### `bulk_email_campaigns`

Each row represents one immutable send definition plus mutable lifecycle state:

- `id`
- `created_by_user_id`
- `subject_template`
- `body_html_template`
- `status`
- `snapshot_after_user_id`, `snapshot_completed_at`
- Cached counters: total, pending, processing, sent, failed, cancelled
- `started_at`, `paused_at`, `completed_at`, `cancelled_at`
- `created_at`, `updated_at`

Supported campaign states are:

- `draft`
- `preparing`
- `queued`
- `sending`
- `paused`
- `completed`
- `cancelled`

The subject and sanitized editor HTML are frozen when recipient preparation starts. The campaign remains `preparing` while eligible users are copied in bounded ID-ordered chunks. `snapshot_after_user_id` is the durable checkpoint, and the campaign can become `queued` only after `snapshot_completed_at` is set and its counters reconcile with recipient rows. A prepared or started campaign's content cannot be edited. A new campaign can be created from an earlier campaign when revised content is required.

### `bulk_email_recipients`

Each row is a durable delivery unit:

- `id`
- `campaign_id`
- `user_id`
- `recipient_email`
- `recipient_username`
- `status`
- `attempt_count`
- `available_at`
- `lock_token`, `locked_at`
- `sent_at`
- `last_error`
- `created_at`, `updated_at`

Supported recipient states are:

- `pending`
- `processing`
- `sent`
- `failed`
- `cancelled`

A unique key on `(campaign_id, user_id)` prevents duplicate delivery within a campaign. Worker lookup indexes cover campaign/status/availability and stale processing locks.

Cached campaign counters are updated transactionally when recipient states change and can be reconciled from recipient rows. The UI reads database state rather than browser-maintained counts.

## Module Components

### Campaign Service

`BulkEmailCampaignService` owns:

- Draft creation and updates.
- Subject, content, and personalization validation.
- Eligible-recipient counting and recipient snapshot creation.
- State transitions for queue, pause, resume, cancel, and retry.
- Campaign list, detail, progress, and failed-recipient queries.
- Counter reconciliation.

State transitions are explicit and reject invalid operations. For example, a completed campaign cannot be resumed, and cancellation only changes unsent recipient rows.

### Content Service

`BulkEmailContentService` owns:

- Quill HTML sanitization.
- Personalization token validation and substitution.
- Preview/test/recipient sample contexts.
- Rendering through `appRenderMailLayout()`.
- Plain-text fallback generation used by the existing mail path.

Allowed personalization tokens are:

- `{{username}}`
- `{{email}}`
- `{{site_name}}`
- `{{profile_link}}`

Unknown tokens block preview, test delivery, and campaign start with a field-level validation error. Preview uses clearly recognizable sample-member data. A test email uses the signed-in administrator's member data for personalization. Bulk delivery uses the snapshotted recipient data.

### Worker Service

`BulkEmailWorkerService` owns ordered campaign selection, recipient claiming, sending, retry timing, stale-lock recovery, and terminal campaign state calculation. The cron entrypoint only parses safe bounded options, invokes the service, records the run, and prints a concise result.

## Rich Text and Email Safety

The composer uses the site's existing Quill editor integration. It supports headings, paragraphs, bold, italic, underline, strike-through, links, ordered and unordered lists, blockquotes, alignment, code blocks, and images.

The submitted body is sanitized again on the server. The allowlist preserves the Quill structures and inline styles needed by the standard renderer while removing scripts, event-handler attributes, unsafe protocols, forms, iframes, and other executable or interactive markup. Links and image sources must use allowed HTTP(S) or site-relative URLs; generated email output resolves site-relative assets to absolute public URLs.

Video and embedded media are not sent because major email clients render them inconsistently. If the editor content contains a Quill video/embed, validation blocks test and campaign delivery and identifies the unsupported content.

The inner rich text is always placed inside the existing standard email shell. Preview, test send, and campaign send call the same content renderer with different personalization contexts. No alternate client-side renderer may generate a look-alike preview.

## Admin Experience

### Composer

The `Toplu E-posta` view presents a focused two-column workspace on desktop and a single-column flow on narrow screens:

- Subject input and Quill editor on the left.
- A real rendered preview in a sandboxed iframe on the right.
- Desktop/mobile segmented preview controls.
- Personalization-token insertion controls.
- Eligible-recipient count before send.
- A separate test-recipient email input and `Test Gonder` action.
- Draft save and campaign-start actions.

Preview refresh is debounced while editing. It posts subject/body/sample context to the server and injects the returned complete email document into the sandboxed iframe. The preview iframe is isolated from the admin page and does not execute submitted scripts.

Test delivery validates a single explicit email address and sends the currently displayed content, including unsaved edits, through the real mail service. Test sends are written to normal email logs with a bulk-email test source, but never create recipient rows or alter campaign progress.

Campaign start displays the exact eligible-recipient count and requires confirmation. Recipient snapshot creation is chunked and checkpointed so one request does not load thousands of users into PHP memory or need to remain open until every row is copied. A short-lived preparation action continues incomplete snapshots, including after reload, until they are internally consistent and ready to queue. Starting is idempotent: repeated submission cannot create a second recipient set or queue the same campaign twice.

### Progress and Controls

The active campaign view polls a permission-protected JSON status endpoint every few seconds and displays:

- Campaign state.
- A stable progress bar and percentage.
- Total, sent, pending, processing, failed, and cancelled counts.
- Start time, latest activity time, and completion time where applicable.
- Recent sanitized failure summaries.

Controls are state-aware:

- `Duraklat` is available while queued or sending.
- `Devam Et` is available while paused.
- `Iptal Et` is available before terminal completion and affects only unsent rows.
- `Basarisizlari Yeniden Dene` is available when failed rows exist and returns only those rows to pending, within the configured retry policy.

Closing the page has no effect on worker activity. A paused campaign remains paused until an authorized administrator resumes it.

### Campaign History

History lists subject, creator, target total, result counters, state, creation/start/completion times, and actions. Each campaign can reopen its snapshotted subject/body in the same exact preview renderer. History is paginated and does not load recipient rows unless campaign details or failures are requested.

## Queue and Concurrency Behavior

Only one campaign may be in `sending` state at a time. Administrators may prepare drafts and queue more campaigns while another is active. Queued campaigns are processed in ID/creation order.

For each worker run:

1. Recover recipient rows whose processing lock exceeded the configured stale-lock interval.
2. Select the current sending campaign or promote the oldest queued campaign.
3. Claim a bounded recipient batch transactionally using a unique lock token.
4. Commit the claim before network delivery; do not hold database transactions open during SMTP calls.
5. Render and send each recipient independently.
6. Persist each result and update campaign counters.
7. Mark the campaign completed when no pending or processing rows remain, then leave the next queued campaign for the same or next bounded worker cycle.

The worker uses a configurable batch size with enforced minimum and maximum values. The initial default is 100 deliveries per cron run, adjustable without code changes to respect the configured mail provider's rate limits. The cron is expected to run once per minute. Campaign progress remains correct if a cron run ends early.

## Retry and Recovery

The default maximum is three delivery attempts. A failed attempt below the limit returns to `pending` with an increasing `available_at` delay. Exhausted attempts become `failed`. One recipient failure never aborts the rest of a campaign.

A worker crash after claiming but before recording a result leaves a processing lock. Stale-lock recovery returns that row to pending and counts the interrupted claim as an attempt only when the mail call had started. The implementation must structure claiming and result recording so retries are conservative and duplicate risk is minimized. SMTP cannot guarantee exactly-once delivery if a process dies after the provider accepts a message but before the database result is committed; this narrow ambiguous case is surfaced in application email logs and campaign recipient state rather than silently claimed as exactly once.

Manual retry starts a new bounded attempt cycle for selected failed rows: it resets `attempt_count` to zero, returns the rows to pending, and clears their availability/lock fields while retaining prior error information in application logs. It does not requeue sent or cancelled rows.

## Security and Audit

- Viewing campaign history and progress requires `notifications.view`.
- Creating drafts, previews, and test emails requires `notifications.manage`.
- Starting, pausing, resuming, cancelling, and retrying requires `notifications.dispatch`.
- Every mutating request requires CSRF validation.
- API responses expose no mail credentials and return only bounded, sanitized error summaries.
- Test recipients and campaign lifecycle actions are recorded through existing email/activity/admin audit facilities.
- Raw SMTP diagnostics are never rendered unescaped and secrets are masked with the existing diagnostic conventions.
- The preview iframe is sandboxed and receives only server-sanitized HTML.

## Operations and Health

A dedicated `send-bulk-email-campaigns.php` cron worker keeps bulk throughput isolated from event-notification email latency. System Health reports whether the worker has run recently, the active/queued campaign count, pending recipients, failed recipients, and stale locks. The worker supports bounded CLI options and dry-run inspection consistent with the existing notification queue worker.

Disabling the bulk worker setting stops new claims without changing campaign state or deleting recipient rows. Re-enabling it continues from persisted state.

## Error Presentation

Validation errors are attached to the relevant composer field. Transport failures appear in campaign progress and the paginated failure view. User-facing errors are concise; full diagnostic data stays in server logs and email logs.

Preview or test failures do not save invalid content, create recipient rows, or affect a running campaign. If recipient snapshot creation fails, the durable chunk checkpoint keeps the campaign in `preparing`; resuming preparation continues after the last committed user ID, and the campaign stays out of `queued` until a complete, internally consistent snapshot exists.

## Verification Strategy

Focused automated verification covers:

- Recipient eligibility and `(campaign, user)` deduplication.
- Chunked snapshot consistency and idempotent campaign start.
- Campaign transition rules.
- Quill HTML sanitization, unsafe URLs, blocked embeds, and allowed formatting.
- Known and unknown personalization tokens.
- Identical renderer use for preview, test, and real delivery.
- Ordered single-campaign processing.
- Batch claiming and concurrent-worker exclusion.
- Retry delays, maximum attempts, and stale-lock recovery.
- Pause, resume, cancel, failed-recipient retry, and terminal completion.
- Counter reconciliation against recipient rows.
- Permission and CSRF enforcement for every operation.

Manual browser verification covers the desktop and mobile admin layouts, editor behavior, stable progress dimensions, control visibility by state, iframe isolation, desktop/mobile email previews, and polling after page reload. A controlled mail transport verifies that a test message and a sampled campaign message match their server preview except for the intended personalization values.

## Acceptance Criteria

- An authorized administrator can compose freely with the existing Quill rich text controls and supported images.
- Preview, test email, and recipient email are produced by the same standard email renderer.
- The administrator can send a test email to an independently entered valid address without affecting campaign totals.
- Starting a campaign snapshots every eligible active, non-banned member exactly once.
- A 10,000-recipient campaign does not require all recipients in PHP memory and is processed in bounded batches.
- Delivery continues after the admin page closes or reloads.
- Progress and result counts survive browser, PHP, worker, and server restarts.
- Campaigns run sequentially; later campaigns wait without blocking draft preparation.
- Pause/resume continues at the remaining recipient set; cancel affects only unsent recipients.
- Failed recipients do not stop the campaign and can be retried explicitly.
- Concurrent worker runs do not intentionally claim the same recipient.
- Existing event-notification email queue behavior remains unchanged.
