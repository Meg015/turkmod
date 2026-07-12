# Account Email Rich Editor Design

## Goal

Rename the account-mail settings subtab to `Hesap E-Posta Şablonları` and replace each raw HTML body textarea with the site's existing Quill-based rich text editing experience without adding a second editor library or changing the account-email settings source of truth.

## Selected Approach

Create an account-email-specific initializer that uses the already supported Quill runtime, toolbar conventions, and CSS. The initializer remains independent from upload-form behavior while reusing the same editor technology and fallback principles.

The canonical form field remains the existing `account_email_<template>_body` textarea. Quill is only a presentation layer and synchronizes its HTML into that textarea on every edit and immediately before form submission.

## Interface Changes

- Rename `Hesap E-postaları` to `Hesap E-Posta Şablonları` in the subtab and description context.
- Convert all seven HTML body fields into rich editors.
- Provide heading, bold, italic, underline, strike, blockquote, ordered/bullet list, alignment, link, image, video, and clean-format controls.
- Preserve the existing variable chips, reset-to-default button, sandbox preview, test recipient, and test-send action.

## Initialization and Performance

Editors initialize lazily when the account-email subtab is first opened. They do not initialize during the initial settings-page render.

Each textarea receives at most one editor instance. Reopening the subtab does not create duplicate toolbars or event handlers.

Quill assets are loaded through the existing approved CDN/CSP paths already used by the application. If Quill is not immediately available, the initializer retries briefly before activating the fallback.

## Synchronization Contract

- Initial textarea HTML is loaded into Quill using clipboard conversion.
- Quill `text-change` updates the textarea value with `quill.root.innerHTML`.
- Before any settings save or template test send, all account-email editor instances synchronize into their textareas.
- Reset-to-default updates both Quill and the textarea.
- Clicking a variable chip inserts it at the current Quill selection. Without Quill, it inserts at the fallback selection.
- Sandbox preview always reads the synchronized editor HTML.

## Fallback

If Quill cannot load, create a local `contenteditable` editor with a compact formatting toolbar and bind its HTML to the same textarea. The original textarea remains the final emergency fallback if browser capabilities prevent `contenteditable` initialization.

The page must continue to save and test templates even when external editor assets fail.

## Security

- Preview remains inside the existing sandboxed iframe.
- Editor output is never injected directly into the admin document.
- Existing server-side template-variable validation remains active.
- Subject fields remain plain text and cannot become rich editors.
- The editor does not alter SMTP settings or introduce additional storage.

## Verification

1. The subtab displays `Hesap E-Posta Şablonları` exactly once.
2. Opening the subtab creates seven editor instances and no duplicates after repeated opens.
3. Existing HTML loads without losing template variables.
4. Formatting changes synchronize to the textarea and persist through settings save.
5. Test send uses the latest editor HTML without requiring a prior save.
6. Variable insertion works at the editor cursor.
7. Reset-to-default updates editor, textarea, and preview.
8. Preview remains sandboxed.
9. Quill-unavailable simulation activates a working fallback.
10. PHP lint, JavaScript syntax, account-email smoke, settings smoke, and real SMTP test delivery remain green.

## Scope Boundaries

- No new editor library is added.
- Notification Center editors and upload-topic editors are not refactored.
- Account-email data keys, templates, delivery service, and migration remain unchanged.
