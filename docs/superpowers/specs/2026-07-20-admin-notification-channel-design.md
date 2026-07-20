# Admin Notification Channel Design

## Goal

Restructure `Admin Paneli > Bildirim Merkezi` around delivery channels instead of a separate template screen.

Admins must be able to manage site-internal notification copy and email notification copy separately. The old visible `Bildirim Sablonlari` area must be removed without leaving admin-facing labels, tabs, helper text, or visible URLs behind. The existing `Bildirim Loglari` tab inside Bildirim Merkezi is also removed because notification logs already belong under `Gunlukler`.

## Approved Navigation

The Bildirim Merkezi top-level tabs become:

- `Gecmis Bildirimler`
- `Yeni Bildirim Gonder`
- `Site Ici Bildirimleri`
- `E-Posta Bildirimleri`
- `Bildirim Ayarlari`

Remove these visible tabs from Bildirim Merkezi:

- `Bildirim Sablonlari`
- `Bildirim Loglari`

The logs remain available through the existing `Gunlukler` admin area.

## Data Model

Keep one notification template/event record per notification event. Extend that record with separate channel copy instead of creating separate template rows per channel.

Site-internal copy remains:

- `title_template`
- `message_template`
- `link_template`
- `in_app_enabled`

Email copy adds:

- `email_subject_template`
- `email_body_template`
- `email_link_template`
- `email_preview_template`
- `email_enabled`

Existing rows are preserved during migration. New email fields are seeded from professional default email copy where defaults exist. Existing custom rows can receive copied starting values from their current title/message/link, but email delivery remains controlled by `email_enabled` and the global email channel setting.

## Site-Ici Bildirimleri

This tab manages the short notification-center experience.

It shows a compact operational summary:

- Active site-internal events
- Site-internal enabled records
- Disabled records

Each notification event card includes:

- Active state
- Site-internal channel enabled state
- Notification type
- Site-internal title template
- Site-internal message template
- Site-internal link template
- Live notification-card preview
- Send site-internal test notification
- Save action

The copy should stay short and action-oriented because it appears in the header menu and user notification page.

## E-Posta Bildirimleri

This tab manages email-specific delivery and copy.

It shows:

- Global email channel state
- Email queue summary
- Cron command
- Queue health warnings when queued or failed counts are nonzero

Each notification event card includes:

- Email channel enabled state
- Email subject template
- Email preview/preheader template
- Email body template
- Email link template
- Live email-style preview
- Send email test action
- Save action

Email copy must not silently fall back to site-internal copy when the email fields are incomplete. Instead, the admin UI shows a missing-copy warning and blocks email test/send behavior for that template until required email fields are present.

## Dispatch Behavior

Dispatch keeps a single event key and evaluates each channel independently.

When site-internal delivery is enabled and allowed by user/admin preferences, the system writes the site-internal title, message, link, and type to `notifications`.

When email delivery is enabled, the global email channel is ready, and user/admin preferences allow email, the system writes the email subject, body, link, and preview metadata to `notification_email_queue`.

If both channels are enabled, both outputs are produced from their own channel copy. If only one channel is enabled, only that channel is produced. If neither channel can be produced, the existing suppression/audit behavior continues to explain why dispatch was skipped.

## Settings Cleanup

Move email queue summary and cron helper from `Bildirim Ayarlari` to `E-Posta Bildirimleri`.

Keep `Bildirim Ayarlari` focused on global behavior and limits:

- Notification center enabled state
- Global/direct delivery rules
- User preference respect
- Link security
- Display limits
- Composer defaults
- Retention
- Automatic event toggles
- Welcome notification settings

Email-specific operational status belongs in `E-Posta Bildirimleri`.

## UI Quality And Consistency

The implementation must look like a clean, professional part of the existing admin panel rather than a separate one-off screen.

Follow the shared admin UI conventions already used in the project:

- Reuse existing admin buttons, switches, form controls, badges, stat cards, alerts, tables, panels, spacing, and icon patterns.
- Keep the two channel tabs visually consistent with each other.
- Use the same card anatomy for notification event editors: header, status/actions, fields, preview, footer actions.
- Keep controls predictable: switches for enabled states, selects for notification type, text inputs for short fields, textareas for bodies, and icon-plus-text buttons for concrete actions.
- Avoid nested cards, oversized decorative layouts, and marketing-style sections.
- Keep dense admin workflows scannable on desktop and mobile.
- Make warnings and disabled states consistent with existing admin alert and badge styles.
- Preserve readable Turkish labels and helper text without reintroducing `Bildirim Sablonlari` or `Bildirim Loglari` as admin-facing areas.

The final UI should feel orderly, uniform, and aligned with shared admin rules across the rest of the panel.

## Removed Admin-Facing Residue

Remove visible references to:

- `Bildirim Sablonlari`
- `notifications.php?tab=templates`
- `Bildirim Loglari`
- `notifications.php?tab=logs`
- The Bildirim Merkezi log/suppression panel

The internal database/service name `notification_templates` may remain because it is a technical persistence boundary. Admin-facing copy should describe the feature as channel notification management, not a separate template center.

## Errors And Guardrails

Missing email subject or body shows a clear warning on the email card.

Email test action is disabled when:

- Global email channel is off
- Required email copy is missing
- The current admin account has no valid recipient email

Site-internal test action inserts a notification for the current admin account only.

Save errors preserve the selected channel tab and anchor back to the affected card.

## Testing

Verify:

- `Bildirim Sablonlari` and `Bildirim Loglari` no longer appear in the Bildirim Merkezi UI.
- Legacy `tab=templates` and `tab=logs` routes do not expose their old panels.
- Site-internal edits do not change email copy.
- Email edits do not change site-internal copy.
- Dispatch writes site-internal copy to `notifications`.
- Dispatch writes email copy to `notification_email_queue`.
- Missing email copy blocks email test behavior and displays a warning.
- Email queue summary appears only under `E-Posta Bildirimleri`.
- Notification logs remain accessible through `Gunlukler`.
