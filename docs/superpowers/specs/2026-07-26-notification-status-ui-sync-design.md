# Notification Status UI Synchronization Design

## Status

Approved on 2026-07-26.

## Problem

Notification settings are persisted successfully, but the Notification Center can continue showing stale status badges and summary counts until the administrator manually refreshes the page. The issue is most visible on **E-Posta Bildirimleri > Yönetici E-Posta Şablonları > Yeni Üye Kaydı**, but the same server-rendered status pattern is used by account-email, administrator-email, event-email, and in-app notification cards.

The current JavaScript manages previews, rich editors, token insertion, and variable validation. It does not synchronize enabled switches with card badges or section summary counters. Browser form-state restoration can therefore leave controls and server-rendered status text visually inconsistent after a POST redirect.

## Goals

- Show the current enabled state immediately without requiring a manual refresh.
- Apply the correction consistently across Notification Center card groups.
- Keep the existing non-AJAX POST/redirect workflow and server-side validation.
- Prevent duplicate submissions while a form is being saved or tested.
- Make the redirected page read and display the canonical persisted state.

## Non-Goals

- Converting notification forms to AJAX.
- Changing notification delivery behavior, recipients, templates, or queues.
- Adding database columns or migrations.
- Redesigning the Notification Center layout.

## Client-Side Status Contract

Notification forms and summary areas receive semantic data attributes instead of relying on text or CSS selectors:

- A form/card identifies its status group and enabled checkbox.
- Its status badge identifies the active and inactive label, icon, and tone.
- A section summary identifies active and inactive count targets.
- Global channel controls use the same contract where their state is represented by a badge.

`notifications-page.js` adds one shared status synchronizer that:

1. Reads the checkbox state.
2. Updates the related card badge label, icon, and tone classes.
3. Recalculates active and inactive totals from all status cards in that section.
4. Runs at page initialization and on every enabled-switch change.
5. Runs after reset controls alter a card.

The synchronizer covers in-app templates, account-email templates, administrator-email templates, and event-email templates. Missing optional hooks are ignored so custom or legacy cards remain functional.

## Submission Feedback

On a valid form submission, the clicked submit button becomes disabled and changes to a saving/sending label. Other submit controls in the same form are disabled to prevent duplicate requests. Client-side variable validation runs first; a validation failure must not leave the controls disabled.

The browser continues submitting a normal form POST. Server errors and validation messages continue to use the existing flash-message behavior.

## Canonical Server State

The notification settings save helper continues to upsert values, invalidate the shared admin-settings cache, and warm it again. It will additionally verify the freshly read values against every value just written. A mismatch raises an exception, preventing a misleading success message.

Notification Center responses use no-store/no-cache headers so browser navigation cannot reuse stale server-rendered status markup. Successful save handlers use an explicit `303 See Other` redirect to the canonical tab, group, and card anchor. The database remains the source of truth.

## Error Handling

- If persistence or read-back verification fails, the existing exception path displays an error and no success message is emitted.
- Client-side status synchronization never changes the saved value; it only reflects the current form state.
- Missing data hooks or malformed count values fall back safely without blocking form submission.
- Submission controls are disabled only after client-side validation succeeds.

## Testing

Regression coverage will verify:

- Administrator “Yeni Üye Kaydı” markup exposes the shared status hooks.
- Account, administrator, event, and in-app card groups use the same hook contract.
- Active/inactive summary targets are present for groups that display those counts.
- The save helper performs cache invalidation, fresh read-back, and mismatch detection.
- Successful save redirects use HTTP 303 and retain the expected tab/group/card anchor.
- The JavaScript status synchronizer updates badge label, icon, tone, and section counts.
- Submit locking occurs only after variable validation passes.

Run focused notification persistence/integrity tests, JavaScript syntax validation, the full PHPUnit suite, project PHP lint, and migration guards. If an authenticated local admin session is available, also smoke-test toggling and saving the administrator registration template without a manual refresh.
