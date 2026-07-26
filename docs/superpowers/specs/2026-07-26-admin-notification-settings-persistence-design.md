# Admin Notification Settings Persistence Design

## Goal

Fix notification settings that appear to save but return to their previous or default values after the admin page reloads. The known failure is `Admin Paneli > Bildirim Merkezi > E-Posta Bildirimleri > Yönetici E-Posta Şablonları > Yeni Üye Kaydı`. The same persistence contract must also cover the editable site-internal new-member notification copy and other dynamically generated email template settings.

## Root Cause

`admin/notifications.php` writes template values directly to `admin_settings`, then invalidates the application settings cache. `App\Core\AppSettings`, however, only loads database rows whose keys exist in `adminSettingDefinitions()`.

Account email template keys are added dynamically to that definition catalog, but administrator email template keys and the editable site-internal new-member template fields are not. Their rows can therefore exist in the database while the canonical settings reader discards them. The next request uses catalog defaults, making the save operation look ineffective.

The new-member administrator email also has two event-level switches:

- `notif_admin_registration_email_enabled` under email queue settings
- `admin_email_registration_admin_notice_enabled` on the administrator email template card

Both control the same delivery event in addition to the global email queue switch, which creates an unnecessary and potentially contradictory state.

## Canonical Controls

Email delivery uses two clear layers:

- `notif_email_channel_ready` is the global notification email queue switch.
- `admin_email_<template_key>_enabled` is the event/template-level switch shown on each administrator email template card.

The duplicate `notif_admin_registration_email_enabled` control is removed from the email queue settings UI and from the normal dispatch decision. It remains a legacy fallback only when `admin_email_registration_admin_notice_enabled` has never been persisted. This preserves existing installations while making the template-card switch canonical after the first explicit save.

Site-internal new-member delivery remains independent through `notif_admin_registration_site_enabled`.

## Settings Definition Catalog

`adminSettingDefinitions()` must register every administrator email template field produced by `AdminEmailService::catalog()`:

- `admin_email_<template_key>_enabled`
- `admin_email_<template_key>_subject`
- `admin_email_<template_key>_body`
- `admin_email_<template_key>_action_label`

Definitions use the corresponding catalog defaults and suitable scalar types. This mirrors the existing account email registration loop and ensures future administrator email templates receive persistence support automatically.

The Notifications module config must also define the editable site-internal new-member fields:

- `notif_admin_registration_site_name`
- `notif_admin_registration_site_description`
- `notif_admin_registration_site_type`
- `notif_admin_registration_site_title_template`
- `notif_admin_registration_site_message_template`
- `notif_admin_registration_site_link_template`

These definitions make saved values visible to `AppSettings` without changing the database schema.

## Admin UI And Save Flow

The queue settings group keeps global queue readiness and retry limits. It no longer shows the duplicate new-member administrator email switch.

The `Yeni Üye Kaydı` card in `Yönetici E-Posta Şablonları` remains the only event-level email control. Its existing save action persists enabled state, subject, body, and button label together. After cache invalidation, the next request must render the saved values.

The site-internal new-member card keeps its separate enabled state and editable copy. Its existing save flow becomes persistent once all of its keys are registered.

Save errors continue to use the existing flash-message and anchor redirect behavior. No migration or new table is required.

## Backward Compatibility

For `registration_admin_notice`, the administrator email service resolves enabled state in this order:

1. Persisted `admin_email_registration_admin_notice_enabled`
2. Legacy `notif_admin_registration_email_enabled`
3. Template catalog default

Once the canonical key exists, later changes to the legacy key cannot override it. Existing subject, body, and action-label values already stored in `admin_settings` become effective automatically when the definitions are added.

## Similar-Issue Audit

Audit every key written by the notification admin save flows against `adminSettingDefinitions()`:

- general notification settings
- email queue settings
- account email settings and templates
- administrator email templates
- site-internal administrator registration template

Any setting written through these flows must either be present directly in module/base definitions or be generated from its service catalog. Unrelated settings subsystems are outside this change.

## Testing

Add regression coverage that verifies:

- all administrator email catalog fields appear in `adminSettingDefinitions()` with their catalog defaults;
- all editable site-internal new-member fields appear in the definition catalog;
- persisted administrator email enabled, subject, body, and action-label values survive a fresh settings read;
- persisted site-internal new-member values survive a fresh settings read;
- the canonical administrator email switch overrides the legacy switch when present;
- the legacy new-member email switch is honored only when the canonical switch is absent;
- the duplicate switch is absent from the email queue settings schema/UI;
- existing account email dynamic definitions remain intact.

Run the focused tests plus the broader PHP unit suite and project lint. Verify the notification admin page manually if a runnable local authenticated session is available.

## Success Criteria

Saving the `Yeni Üye Kaydı` administrator email template changes its active state and copy after reload, and dispatch uses those saved values. The site-internal new-member notification fields also persist. Each new-member channel has one unambiguous event-level control, existing installations retain their prior effective behavior, and no similar notification-admin setting is silently discarded by the canonical settings reader.
