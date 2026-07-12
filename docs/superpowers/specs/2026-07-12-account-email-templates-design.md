# Account Email Templates Design

## Goal

Create a complete, working account-email management surface under `Gelişmiş Ayarlar > E-posta > Hesap E-postaları`. Account-related transactional messages will have one source of truth, use the existing SMTP configuration, support safe test delivery, and be wired to every applicable account flow without duplicating the notification-template system.

## Current State

- Password-reset request mail exists as hard-coded HTML and is wired to the forgot-password flow.
- Welcome mail exists as hard-coded HTML but is not called by either registration path.
- Full-page registration and popup registration are separate code paths.
- The database already has `email_verified_at` and `email_verification_token`, but no complete verification issue/resend/consume flow exists.
- Profile password changes and successful password resets do not send security confirmation mail.
- Profile self-service does not currently change the account e-mail address; administrators can edit users.
- The Notification Center owns content, moderation, message, report, ban, and restriction notification templates. Those templates must stay there.
- SMTP driver, host, port, credentials, encryption, sender name, and sender address already exist under the E-posta settings section and must be reused.

## Architecture

Introduce a focused `AccountEmailService` responsible for the account-template catalog, settings lookup, variable rendering, test rendering, delivery, and logging. Authentication/profile/admin flows will call named service methods instead of building mail HTML directly.

The service will expose clear operations:

- `sendWelcome(...)`
- `sendVerificationRequest(...)`
- `sendVerificationCompleted(...)`
- `sendPasswordResetRequest(...)`
- `sendPasswordResetCompleted(...)`
- `sendPasswordChanged(...)`
- `sendEmailChanged(...)`
- `sendTest(...)`

The existing shared `appSendMail()` transport remains the only delivery implementation. Account templates will not introduce a second SMTP client or sender configuration.

## Source of Truth

Account template configuration will use dedicated admin-setting keys managed by `adminSettingDefinitions()` and the existing settings cache. Each template has one canonical definition consisting of:

- enabled flag;
- subject template;
- HTML body template.

Global account-mail behavior uses separate keys:

- account e-mail system enabled;
- e-mail verification enabled;
- e-mail verification required for login;
- verification-token lifetime;
- verification resend cooldown.

The verification system is disabled and non-blocking by default so existing users and current login behavior are preserved. Password-reset delivery remains enabled by default.

No account template will be inserted into `notification_templates`. This avoids duplicate editing surfaces and avoids exposing account-security mail as ordinary notification events.

## Template Catalog

### Welcome

Sent after successful full-page registration and popup registration. It includes the username, site name, and login/profile URL. Delivery failure does not roll back the newly created user.

### Verification Request

Sent when verification is enabled after registration and through a resend action. It includes a single-use verification URL and expiration information.

### Verification Completed

Sent after a verification token is consumed successfully. It confirms that the account address is verified and links to the account/profile page.

### Password Reset Request

Replaces the existing hard-coded reset mail while preserving the one-hour secure reset-token behavior. The public response continues to avoid disclosing whether an address exists.

### Password Reset Completed

Sent after the reset form successfully changes the password and invalidates the reset token and remember tokens.

### Password Changed

Sent after a logged-in user changes their password from the profile security page. It states that the change occurred and advises the user to start a reset flow if it was not theirs.

Administrative password replacement will use the same security template with an `actor_context` variable indicating an administrative change. It will never include the password.

### Email Address Changed

When an administrator changes a user's e-mail address, a security notice is sent to the old valid address and a confirmation notice is sent to the new valid address. If verification is enabled, the new address becomes unverified and receives a fresh verification request.

No inactive self-service e-mail-change template will be presented as if it were wired. If self-service e-mail change is added later, it will reuse this same service method and template source.

## Supported Variables

Only an allowlisted variable set may be rendered:

- `{{site_name}}`
- `{{username}}`
- `{{recipient_email}}`
- `{{action_url}}`
- `{{login_url}}`
- `{{profile_url}}`
- `{{expires_minutes}}`
- `{{old_email}}`
- `{{new_email}}`
- `{{actor_context}}`
- `{{ip_address}}`
- `{{date_time}}`
- `{{support_email}}`

Values are HTML-escaped by default. URL variables are validated as application/public URLs before insertion. Unknown variables remain visible in admin validation and prevent saving rather than silently rendering blank.

## Admin Interface

Add a third E-posta subtab named `Hesap E-postaları` beside `E-posta Ayarları` and `Test Gönderimi`.

The subtab contains:

1. A behavior card for the account-mail master switch and optional verification policy.
2. One collapsible card per template with enabled state, subject, HTML body, variable chips, preview, reset-to-default, test recipient, and test-send action.
3. A notice linking to `Bildirim Merkezi > Bildirim Şablonları` for comment, message, moderation, ban, restriction, and report e-mails.

The existing Notification Center setting `Yeni Üyeye Hoş Geldin Bildirimi` will be relabeled `Site İçi Hoş Geldin Bildirimi`. Its key and behavior remain unchanged to preserve compatibility.

Template test actions use unsaved values from the selected card, do not save global settings, and return operation-specific success or sanitized failure messages. They use the corrected submitter-aware AJAX contract already established on the settings page.

## Verification Flow

Verification tokens are generated with cryptographically secure random bytes. Only a SHA-256 hash is stored. The raw token appears only in the outbound URL.

The users schema will receive a verification expiry timestamp and last-sent timestamp through a migration. Existing `email_verification_token` and `email_verified_at` columns are reused.

The public verification endpoint:

1. validates token shape and request parameters;
2. looks up the hashed token and non-expired record;
3. atomically sets `email_verified_at` and clears token fields;
4. logs `email_verified` activity;
5. sends the verification-completed mail;
6. redirects to a clear success or failure screen.

The resend action applies the configured cooldown and returns a generic response that does not disclose account existence.

When verification-required-for-login is enabled, unverified users are denied normal login with a clear resend option. The setting is off by default and enabling it must not invalidate already verified accounts.

## Error Handling and Security

- Account operations complete even if non-essential welcome or confirmation mail fails.
- Reset and verification request pages continue returning generic responses to prevent account enumeration.
- Passwords, raw reset tokens, raw verification tokens, cookies, and SMTP credentials are never logged.
- Every delivery writes a sanitized e-mail log with `source=auth` or `source=account`, a stable source key, recipient, transport result, and SMTP response.
- Test-send errors expose only sanitized SMTP stages and codes to authorized administrators.
- Templates cannot inject arbitrary mail headers; subject and sender metadata are normalized through the shared mail helper.
- Verification and reset URLs are generated from the canonical public base URL and route settings.

## Data and Migration

A migration adds only missing verification lifecycle columns:

- `email_verification_expires_at` nullable timestamp;
- `email_verification_sent_at` nullable timestamp.

Template content remains in existing admin settings storage, so no second template table is introduced. The migration and schema snapshot are kept consistent.

## Integration Points

- Full-page registration sends welcome and optional verification request.
- Popup registration sends the same messages through the same service.
- Forgot-password sends the configurable reset-request template.
- Reset-password success sends reset-completed confirmation.
- Profile password change sends password-changed confirmation.
- Admin password replacement sends password-changed security notice.
- Admin e-mail change sends old/new address notices and optional re-verification.
- E-mail Logs and System Health continue to report delivery and queue failures through existing surfaces.

## Verification

1. PHP lint and JavaScript syntax checks pass.
2. Settings definitions, saving, cache invalidation, and template reset behavior have regression coverage.
3. Unknown variables and invalid subjects/bodies are rejected.
4. Test-send does not save settings and produces account-specific logs.
5. Both registration paths create the same welcome/verification deliveries.
6. Password-reset request and completion messages render correct single-use links and never expose tokens in logs.
7. Profile and admin password changes send security notices without password content.
8. Admin e-mail changes notify old and new addresses and reset verification state only when appropriate.
9. Verification tokens expire, cannot be reused, respect resend cooldown, and log successful verification.
10. Verification-required login behavior is tested both disabled and enabled.
11. Real SMTP delivery is verified through port 465/SSL with the configured live provider, followed by E-posta Logları inspection.
12. Existing Notification Center templates remain in their current location and no duplicate settings are introduced.

## Scope Boundaries

- Content/moderation/message/event notification templates remain in the Notification Center.
- Ban, restriction, report, and appeal mail behavior is not duplicated in account templates.
- The work does not redesign the full settings page or notification center.
- Self-service e-mail address change is not added as a new profile feature in this scope; the account-mail service is prepared for it and the existing administrator change flow is wired.
