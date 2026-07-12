# Download System Hardening and Settings Organization Design

Date: 2026-07-12

## Objective

Harden the existing download-access implementation without replacing its architecture, preserve the safe external-link confirmation flow, and make Admin > Gelişmiş Ayarlar > İndirme Yöneticisi > Erişim Kilidi ve Metinler easier to scan.

The approved implementation scope consists of the targeted-hardening approach plus audit items 2, 3, 4, 5, 7, and 9. The download-counter key change, API contract cleanup, and a broad standalone test-suite expansion are outside this scope.

## Constraints

- Keep download confirmation and redirect business logic under `includes/src/Modules/TopicWorkflow/Http/download-action-content.php`.
- Preserve HTTP/HTTPS target validation, visible target-host information, the confirmation page, and external-link safety attributes.
- Preserve every existing admin setting key and saved value. No settings migration is required.
- Do not add another subtab, accordion, collapsible panel, or card layer inside `Erişim Kilidi ve Metinler`.
- Preserve unrelated changes in the dirty worktree, especially the notification edits in the theme bundles.

## Admin Settings Organization

`Erişim Kilidi ve Metinler` remains one continuously visible page. Its settings are divided by subtle section headers containing an icon, title, one-sentence description, and a horizontal divider. The existing two-column grid remains in use and collapses to one column at the current mobile breakpoint.

The six sections and their setting keys are:

1. **Erişim Politikası**
   - `download_access_mode`
   - `download_access_comment_requirement`
2. **Süre ve Yenileme**
   - `download_access_grant_mode`
   - `download_access_grant_duration_value`
   - `download_access_grant_duration_unit`
   - `download_access_relock_on_comment_delete`
3. **Kilit Metinleri ve Davranışlar**
   - `download_access_login_message`
   - `download_access_comment_title`
   - `download_access_comment_message`
   - `download_access_locked_button_text`
   - `download_access_comment_cta_label`
   - `download_access_open_auth_popup`
   - `download_access_focus_comment_form`
   - `download_access_unlock_after_auth`
   - `download_access_unlock_after_comment`
4. **Giriş/Kayıt Penceresi**
   - `download_access_auth_modal_title`
   - `download_access_auth_login_label`
   - `download_access_auth_register_label`
   - `download_access_auth_success_message`
5. **Bekleme ve Süre Dolumu**
   - `download_access_pending_message`
   - `download_access_pending_button_text`
   - `download_access_expired_title`
   - `download_access_expired_message`
   - `download_access_active_until_template`
6. **Başarı ve İlerleme Görünümü**
   - `download_access_success_notice_enabled`
   - `download_access_success_message`
   - `download_access_progress_enabled`
   - `download_access_progress_template`
   - `download_access_success_animation_enabled`
   - `download_access_success_auto_compact`
   - `download_access_success_compact_delay`
   - `download_access_highlight_first_card`

The download-group metadata in `admin/settings.php` gains optional `sections` metadata for the access group. Rendering continues to use the shared setting-field/grid helpers, with one grid emitted per section. Small shared CSS classes provide the heading and divider treatment. The other Download Manager subtabs remain unchanged.

## Access Progress for Members-Only Mode

The current progress model treats every non-public mode as a three-step flow. This incorrectly presents the comment step as completed when the selected mode only requires membership.

The finalized state will use mode-aware progress:

- `public`: zero steps and no access notice.
- `members`: two steps, `Giriş` and `Aç`; the comment step is not rendered as a requirement.
- `members_comment`: three steps, `Giriş`, `Yorum`, and `Aç`.

Server-rendered output and asynchronous refreshes must use the same step model. The state response will expose whether the comment step is required. The template and JavaScript will hide or omit that step in members-only mode, update `progress_total`, and keep accessible labels consistent with the visible sequence.

## Reliable Comment and Approval Refresh

The current asynchronous watcher uses `setInterval(async ...)`, which can overlap slow requests and stops after roughly 27 seconds. It will be replaced with a single-flight recursive timeout loop.

Behavior:

- Only one access-status request may run at a time per download section.
- Poll every two seconds for the first 30 seconds, every five seconds until five minutes, then every 15 seconds while the page remains open and visible.
- Pause polling while the document is hidden and perform an immediate refresh when it becomes visible again.
- Stop polling immediately after access opens or when the section is removed/unloaded.
- Back off failed requests up to 30 seconds and show at most one non-blocking connection warning after three consecutive failures.
- A pending comment approved after the old 27-second window must still unlock the download area without a manual page reload.

This change remains compatible with both submitted-comment and approved-comment policies.

## Countdown State Safety

Countdown handles will be stored per card instead of remaining as untracked local intervals.

When a card becomes locked during a countdown:

- cancel its active timer;
- reset `data-ready` and `data-counting`;
- remove `aria-busy`, counting, and ready classes;
- restore the correct locked icon, message, and action label;
- prevent a stale callback from marking the card ready later.

Every countdown tick and completion callback will also re-check the current locked state before changing the card. Unlocking later starts from a clean, non-ready state and requires the normal countdown again.

## Duration Limit Consistency

The runtime currently applies unit-specific limits while the admin number input always exposes a single broad maximum. This can save a value that is displayed differently from the value the runtime actually uses.

A single duration-maximum helper will define the existing effective limits:

- minutes: `525600`
- hours: `87600`
- days: `3650`

The runtime calculation, settings-save normalization, and admin input will use the same values. Changing the unit updates the input's `max` attribute and clamps an out-of-range value. Server-side saving performs the same unit-aware clamp, so crafted requests cannot produce a displayed/effective mismatch. Minimum duration remains `1`.

## Authentication Modal Keyboard Behavior

The existing modal structure and authentication flow remain unchanged. Accessibility is improved by:

- remembering the element that opened the modal;
- trapping `Tab` and `Shift+Tab` inside visible modal controls;
- keeping `Escape` close behavior;
- restoring focus to the opener when the modal closes;
- handling a missing or detached opener without throwing an error.

No credentials, authentication endpoint, redirect rule, or session behavior changes as part of this work.

## Confirmation and Safe URL Contract

The download action continues to:

- load the selected link from the database;
- re-check the current user's access state before displaying or redirecting;
- accept only targets with an HTTP or HTTPS scheme and a non-empty host;
- show the existing confirmation page when enabled;
- display the outbound host and safety copy;
- increment the counter only at the established continuation point;
- use the existing safe external-link attributes and redirect behavior.

No change may bypass the confirmation screen, weaken target validation, or move this business logic out of the TopicWorkflow-owned download action.

## Error Handling

- Access refresh failures remain non-destructive: cards retain their last authoritative locked state.
- Timer cancellation is idempotent and safe when no timer exists.
- Invalid duration values are normalized rather than causing a settings-save failure.
- Modal focus restoration checks that the previous element is still connected and focusable.
- Existing server exceptions continue through the project logging helpers.

## Verification

Implementation verification will include:

- PHP syntax checks for every touched PHP file;
- JavaScript syntax checks for every touched JavaScript file;
- focused helper tests for members-only progress and unit-specific duration limits;
- the existing download-access grant smoke test;
- browser verification of public, members-only, comment-required, pending, active, expired, deleted/restored, and relock-during-countdown states;
- browser keyboard verification of modal focus containment and restoration;
- admin desktop and mobile verification that all 32 settings remain present under the six visible divider sections;
- download confirmation verification for valid and invalid targets without changing the established safety behavior;
- `git diff --check`.

The known unrelated full-suite failure caused by the PHP 8.2 CLI and a PHP 8.3 dependency feature will be reported separately if it remains present.
