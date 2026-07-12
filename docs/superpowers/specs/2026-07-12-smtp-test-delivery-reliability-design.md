# SMTP Test Delivery Reliability Design

## Goal

Make the Admin Panel e-mail test action execute a real SMTP delivery without saving unrelated settings, report the actual delivery result, and keep the normal application mail path compatible with the SMTP values provided by HestiaCP.

## Confirmed Problem

The settings page intercepts every form submission with JavaScript and builds the AJAX payload with `new FormData(form)`. That payload does not reliably include the clicked submit button's `name=action` and `value=send_email_test`. The PHP controller therefore falls back to `save_settings`, saves the form, and returns the misleading `Ayarlar başarıyla kaydedildi` response without reaching the SMTP test branch.

The SMTP host resolves and TCP ports 465 and 587 are reachable. Transport reachability therefore does not explain the reported panel behavior; the request-routing defect must be corrected before authenticated delivery can be evaluated.

## Selected Approach

Keep the existing settings form and server-side `send_email_test` branch, but make the AJAX submission explicitly submitter-aware.

- Determine the actual submit button from `SubmitEvent.submitter`, with a safe fallback for older browsers.
- Add the submitter's name and value to the `FormData` payload explicitly. Use `action=save_settings` only for the main save button and `action=send_email_test` only for the test button.
- Do not allow the test action to run unrelated settings-save validation or save-only field state mutations.
- Preserve the existing ability to test unsaved SMTP field values without writing them to the database.
- Keep the non-JavaScript form behavior functional through the button's native `name` and `value` attributes.

This approach changes the smallest possible surface while retaining the existing controller, CSRF checks, permissions, logging, and mail helper.

## SMTP Configuration Contract

The live configuration will use one of HestiaCP's supported authenticated combinations:

- Primary: SMTP host `mail.turkmod.net`, port `465`, encryption `ssl`.
- Fallback verification: SMTP host `mail.turkmod.net`, port `587`, encryption `tls` (STARTTLS).
- Driver: `smtp`.
- Authentication: full mailbox address as the username and the mailbox password stored through the existing protected settings path.
- Sender address: the same domain mailbox used for authentication unless the server explicitly permits another sender.

No password, authentication token, or session value may be written to application logs, browser console output, test scripts, commits, or user-facing error messages.

## Result and Error Handling

On success, the panel will display a message specific to the operation, including the test recipient: `Test e-postası gönderildi: <recipient>`.

On failure, the panel will display `Test e-postası gönderilemedi` plus a sanitized diagnostic from the existing last-mail-result structure. Diagnostics may identify the failed SMTP stage, response code, host, port, and encryption mode, but must never include credentials.

The e-mail log will record the test source, recipient, transport, SMTP response/code, and sanitized error. The test action must not produce a settings-updated audit entry because it does not save settings.

AJAX response parsing will also handle non-JSON server failures safely so that a PHP error or proxy response does not leave the button stuck in a loading state or falsely report success.

## Verification

1. Add a focused regression check proving that the test submitter produces `action=send_email_test` and the main button produces `action=save_settings`.
2. Run JavaScript syntax validation and PHP lint on affected files.
3. Exercise the local/admin request path and confirm the test action does not call the settings-save branch.
4. In the live panel, test authenticated delivery with port 465 and SSL/TLS.
5. If 465 fails, capture the sanitized SMTP stage and verify port 587 with STARTTLS.
6. Confirm receipt in the destination inbox and confirm the panel message says that the test e-mail was sent rather than that settings were saved.
7. Confirm the E-posta Logları entry contains no password or other secret.

## Scope Boundaries

This change covers the Admin Panel test action and the shared SMTP transport behavior needed for normal application messages. It does not add IMAP or POP3 support because the application only needs outbound SMTP delivery. It does not change unrelated settings, notification content, download behavior, or other dirty-worktree changes.
