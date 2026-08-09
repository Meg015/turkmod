# Message Presence and Natural Last-Seen Implementation Plan

## 1. Extend the Shared Presence Domain

- Update `includes/src/Engine/UserActivity/UserPresence.php` so relative labels use the injected clock and the configured local timezone.
- Preserve the existing 300-second online boundary and invalid/future timestamp handling.
- Return `Şimdi çevrimiçi` for online users.
- For offline users, return minutes only for valid activity earlier on the same calendar day and less than one hour old.
- Return hours for activity earlier on the same calendar day and at least one hour old.
- Return `Dün HH:mm` for the previous local calendar day, `N gün önce` for two through six local calendar days, and `dd.mm.YYYY HH:mm` for seven or more local calendar days.
- Keep `Bilinmiyor` for missing or invalid profile activity and retain the exact date label for valid timestamps.

## 2. Strengthen Deterministic Presence Verification

- Extend `scripts/verify-user-presence.php` with a fixed clock and explicit timezone.
- Cover 299/300-second online boundaries, same-day minute and hour labels, midnight crossings, previous-day labels, two-day and six-day labels, the seven-day exact-date boundary, month/year transitions, invalid input, and future timestamps.
- Ensure the tests assert both relative and exact labels without relying on the machine's current time.

## 3. Add Presence to Message Service Queries

- Add `u.last_activity_at AS with_user_last_activity_at` to the existing `threadForUser()` and `listThreads()` select lists in `includes/src/Modules/Messages/Services/MessageService.php`.
- Do not introduce per-user lookups or a new query path.
- In the shared thread-row decorator, pass the selected value to the common presence helper.
- Return only `with_user_is_online`, `with_user_presence_label`, and `with_user_presence_state_class` to message consumers; do not expose the raw timestamp.
- Default a missing/deleted counterpart to the offline presentation.

## 4. Render Message Avatar Indicators

- Update `includes/src/Modules/Messages/Http/messages-page-content.php` to wrap list and active-header avatars in scoped presence containers.
- Render a focusable status indicator in every conversation row and in the active conversation header.
- Escape the status class, label, accessible name, and tooltip content with the existing view helpers.
- Leave message-search result avatars unchanged.

## 5. Style the Message Presence Component

- Add narrowly scoped styles to `assets/css/messages-page.css` without changing existing avatar dimensions or row alignment.
- Anchor the dot at the avatar's bottom-right with a surface-colored outline.
- Use the established green online and muted-red offline colors, with a restrained online pulse only.
- Provide hover and `:focus-visible` tooltip behavior plus a visible focus treatment.
- Disable pulse animation under `prefers-reduced-motion: reduce`.
- Keep tooltips inside narrow mobile viewports and ensure the active-header and list variants remain visually consistent.

## 6. Update Active Conversation Presence

- Extend `assets/js/messages-page.js` with a small normalization function that accepts only online/offline state and safely defaults unknown data to offline.
- Add a DOM updater that changes only the indicator class, tooltip text, and accessible label.
- After each successful existing `action=thread` poll, update the active-header indicator and the matching conversation-row indicator from `data.thread`.
- Preserve all current message rendering, typing, unread, scrolling, and user-authored changes in the file.

## 7. Refresh Other Conversation Indicators

- Reuse the existing `GET action=list` response; do not add a presence endpoint.
- Start a 60-second status refresh while the page is visible.
- Map returned threads by counterpart user ID or thread ID and update only existing status indicators; do not rebuild the conversation list.
- Skip periodic refreshes when `document.hidden` is true.
- On return to a visible state, perform one immediate refresh and restart the interval without creating duplicate timers.
- Retain the last rendered state on network, JSON, or API failure and allow later scheduled attempts to recover.

## 8. Focused Static and Source Verification

- Verify message list and single-thread queries both select the last-activity column and the API output omits the raw timestamp.
- Verify list and header markup both contain focusable presence indicators, while search results do not.
- Verify the JavaScript contains one 60-second interval, visibility handling, thread-poll updates, safe normalization, and no list replacement.
- Run PHP syntax checks on changed PHP files and Node syntax checking on the message page script.
- Run `php scripts/verify-user-presence.php` and the workspace migration guard.

## 9. Asset Build and Browser Validation

- Rebuild the project CSS assets using the repository's existing build command so message-page source styles reach generated bundles where applicable.
- Sign in with a test account and open the messages page at desktop and mobile widths.
- Confirm green/red dots in the conversation list and active header, correct hover/focus tooltips, keyboard accessibility, and reduced-motion behavior.
- Confirm active presence follows the existing fast thread poll and other rows refresh without list flicker or scroll/selection loss.
- Hide and restore the tab to verify the 60-second refresh pauses and resumes immediately.
- Inspect the console and network log for JavaScript errors, duplicate timers, unexpected requests, and raw `last_activity_at` exposure.

## 10. Completion Criteria

- All acceptance criteria in `docs/superpowers/specs/2026-08-09-message-presence-natural-last-seen-design.md` pass.
- Message presence uses the shared 300-second rule and produces no N+1 queries or new endpoint.
- Profile last-seen labels follow local calendar boundaries exactly as approved.
- Presence refresh failures never interrupt message reading or sending and never blank a previously rendered indicator.
- Generated assets are synchronized with their sources.
- Existing unrelated working-tree changes are preserved and are not staged or committed with this feature.
