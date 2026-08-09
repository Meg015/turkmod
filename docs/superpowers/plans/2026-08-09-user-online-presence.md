# User Online Presence Implementation Plan

## 1. Shared Presence Domain

- Add `App\Engine\UserActivity\UserPresence` as the single owner of the 300-second online threshold and presence labels.
- Inject a clock into the class so the 299/300-second boundary can be verified deterministically.
- Return a normalized presentation array containing `is_online`, `status_label`, `relative_label`, and `exact_label`.
- Treat empty, invalid, future, anonymous, and deleted-user timestamps as offline; use `Bilinmiyor` only for the profile value when no valid last activity exists.
- Expose narrowly scoped wrapper functions from `includes/src/Engine/UserActivity/Support/helpers.php`, which is already loaded by the global bootstrap, so profiles and comment APIs use the same rule.

## 2. Throttled Activity Persistence

- Add a helper that updates `users.last_activity_at = NOW()` only for a positive authenticated user ID.
- Store the last successful presence write time in a dedicated session key and skip repeated writes for 60 seconds.
- Call the helper in `includes/init.php` after the authenticated session refresh has completed and while the session is still writable.
- Use a parameterized update and catch failures without changing the HTTP response; report failures through the existing application exception logger.
- Update the session throttle timestamp only after a successful database write so transient failures can be retried on the next request.
- Do not add a migration because `users.last_activity_at` and its index already exist in `database/schema.sql`.

## 3. Profile Presentation Data

- Extend `includes/src/Engine/Users/ProfilePresentation.php` so `profileContext()` reads `last_activity_at` from the supplied user row and merges the shared presence presentation into the profile context.
- Preserve the existing injected profile clock behavior and ensure presence calculation does not duplicate threshold or label logic in the profile class.
- Add explicit page variables in `includes/src/Engine/Users/Http/public-profile-page-content.php` only where required by the renderer fallback path.
- Extend `includes/PublicThemeRenderer.php` fallback profile construction with the same normalized presence keys so captured and theme-rendered profiles stay equivalent.

## 4. Profile Sidebar Rendering

- Update `themes/turkmod/profile-sidebar.tpl` to add the “Son Çevrimiçi” row immediately after “Üyelik Süresi”.
- Update `includes/partials/profile-sidebar.php` with the equivalent fallback markup and safe attribute escaping.
- Render a semantic status marker, the relative value, and the exact date/time through `title` and accessible text when available.
- Show “Şimdi çevrimiçi” for online users and `Bilinmiyor` for users without valid activity data.
- Add narrowly scoped profile-presence styles to `themes/turkmod/css/bundle.css` and `assets/css/theme.css`, reusing the current profile meta grid, color variables, spacing, and responsive rules.

## 5. Comment API Presence Data

- Extend the root-comment select columns and reply query in `api/comments.php` with `u.last_activity_at`; do not add per-user queries.
- Ensure recursive comment-tree construction carries the selected timestamp into `formatComment()` for both roots and replies.
- Add only `is_online` and `presence_label` to the public comment JSON result, using the shared presence helper.
- Keep the raw last-activity timestamp out of the JSON response.
- Ensure guest, deleted-user, and missing-user comments resolve to the offline label without warnings.

## 6. Comment Status Component

- Extend `themes/turkmod/comment-item.tpl` with a placeholder for the presence indicator inside the avatar container.
- Update `assets/js/topic-comments.js` to build one escaped, focusable status-indicator fragment from `is_online` and `presence_label`.
- Pass that fragment through the client template values and use the identical fragment in the JavaScript fallback HTML branch.
- Keep the existing recursive `renderComment()` path so roots and nested replies cannot drift visually.
- Default missing or malformed presence data to `Çevrimdışı` on the client.

## 7. Visual Design and Accessibility

- Add the avatar indicator styles to `assets/css/pro-comments.css`: approximately 10 px, bottom-right anchored, card-colored outline, green online state, muted-red offline state, and a compact dark tooltip.
- Provide hover and `:focus-visible` tooltip behavior, a visible keyboard focus treatment, and an accessible label so color is never the sole signal.
- Apply a restrained pulse only to the online state and disable it under `prefers-reduced-motion: reduce`.
- Verify the indicator does not cover the avatar image, author name, badges, or nested-reply layout at desktop and mobile widths.
- Keep active-theme overrides narrowly scoped under the existing public-theme selectors if `bundle.css` specificity requires them.

## 8. Focused Verification

- Add `scripts/verify-user-presence.php` using a fixed clock to verify 299 seconds online; 300 seconds offline; older, empty, invalid, and future values offline; and correct relative/exact labels.
- Exercise the 60-second throttle helper with an isolated PDO-compatible test seam or a focused fake so repeated writes, successful writes, and failed-write retry behavior are covered without touching production data.
- Verify that `api/comments.php` emits presence fields for roots and replies and does not emit `last_activity_at`.
- Verify both profile sidebar implementations place “Son Çevrimiçi” immediately after “Üyelik Süresi”.
- Run `composer lint`, `php scripts/verify-user-presence.php`, and `npm run build`.
- Run `composer guard:migration:workspace` and confirm the feature introduces no schema delta.

## 9. Browser and Layout Validation

- Open a public profile for one recently active and one inactive user; confirm labels, full-date tooltip, row order, spacing, and mobile wrapping.
- Open a topic containing root comments and nested replies from online and offline users; confirm status colors, tooltip text, keyboard focus, and recursive consistency.
- Temporarily emulate reduced motion and confirm the online pulse is disabled.
- Check the browser console and network response for rendering errors, unsafe raw timestamps, duplicate API requests, and unexpected layout shifts.

## 10. Completion Criteria

- All acceptance criteria in `docs/superpowers/specs/2026-08-09-user-online-presence-design.md` pass.
- Online state is computed from one shared 300-second rule and activity writes occur no more than once per authenticated session per 60 seconds.
- Profile and comment renderers remain functional when presence data is missing or persistence fails.
- Comment listing adds no N+1 queries and exposes no raw last-activity timestamp.
- Generated CSS/JS assets are rebuilt from their source files.
- No unrelated working-tree changes are reverted, reformatted, staged, or committed.
