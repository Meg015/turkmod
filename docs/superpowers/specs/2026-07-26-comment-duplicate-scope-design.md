# Comment Duplicate Scope Design

## Goal

Prevent users and guests from posting the same comment across several topics inside the configured duplicate window, while retaining an administrator-selectable scope. Seed the short low-quality terms `sa`, `as`, and `vv` without overwriting an installation's existing exact-term filter.

The reported example—one user posting `sa` to three different topics in the same minute—must be rejected after the first accepted comment when the default `Tüm konular` scope is active.

## Existing Behavior

`comment_spam_duplicate_enabled` and `comment_spam_duplicate_minutes` currently operate per topic.

For logged-in users, `commentSpamFindRecentDuplicateComment()` filters by both `topic_id` and `user_id`. For guests, `commentSpamDuplicateRateKey()` includes the topic ID together with IP address and normalized body. Existing tests explicitly treat the same body on another topic as distinct.

The exact-term list defaults to empty. With a minimum alphanumeric count of two, `sa` contains enough characters to pass the minimum-length spam check unless it is added to the exact-term filter.

## Admin Settings

Add `comment_spam_duplicate_scope` to the canonical admin setting definitions:

- `all_topics` — label `Tüm konular`; default for new and existing installations.
- `same_topic` — label `Yalnızca aynı konu`; preserves the previous behavior when explicitly selected.

Show the setting in `Gelişmiş Ayarlar > Yorum Yönetimi > Spam Yönetimi > Tekrarlı Yorum Kontrolü` together with the enabled switch and minute window.

Update the section description and tooltips to state the scope precisely. Unknown or missing scope values resolve to `all_topics` so existing installations immediately receive the stronger behavior without resaving settings.

## Duplicate Detection

Introduce a small scope resolver that returns only `all_topics` or `same_topic`.

For logged-in users:

- `all_topics` queries recent non-deleted comments by user and time window, without a topic predicate.
- `same_topic` also requires the current topic ID.
- Comment bodies continue to use the existing case, whitespace, and entity normalization before comparison.

For guests:

- `all_topics` builds the duplicate rate-limit key from IP address and normalized body.
- `same_topic` also includes the topic ID.

The API resolves the scope once and passes it to both paths. Spam exemptions continue to bypass the check. Duplicate matches continue to use the existing reject-or-pending action and user-facing messages.

The scope should be included in the spam reason/log payload so operators can tell which rule matched.

## Short Exact Terms

Change the canonical default of `comment_spam_exact_terms` to:

```text
sa
as
vv
```

Add a root database migration that updates `admin_settings` safely:

- If the setting row is absent or empty, store the three seed terms.
- If a custom list exists, parse it with the same line/comma/semicolon separators used by the application and append only missing seed terms.
- Compare normalized values case-insensitively and punctuation-insensitively so existing equivalents are not duplicated.
- Preserve all existing custom terms.
- Invalidate the admin settings cache after the update.

The migration is intentionally one-way because it cannot safely distinguish terms that existed before the upgrade from terms it appended.

The existing exact-term normalization means `sa`, `SA`, `sa,`, and `sa!` match the same configured term.

## Compatibility

Existing callers gain an optional scope argument with `all_topics` as the new default. The API passes the resolved setting explicitly. No comments are deleted or retroactively moderated.

Existing administrator selections for spam action, duplicate minutes, exact-term additions, exempt usernames, and exempt groups remain intact. Selecting `same_topic` restores the prior duplicate semantics.

The change requires the normal database synchronization step so the seed-term migration runs in deployed installations. The scope setting itself needs no database schema change because `admin_settings` already stores dynamic keys.

## Error Handling

An invalid scope value falls back to `all_topics` rather than disabling protection.

Duplicate-query failures retain the current fail-open behavior and return no duplicate match; they must not prevent comment submission because of a transient database read error. Existing application error logging behavior remains unchanged.

The migration exits safely when `admin_settings` is absent and throws on unexpected database failures so the migration runner does not record a partial success.

## Testing

Add or update regression coverage for:

- Missing and invalid scope values resolving to `all_topics`.
- A logged-in user repeating a normalized body on another topic under `all_topics`.
- The same cross-topic comments being allowed under `same_topic`.
- Same-topic duplicates remaining blocked in both scopes.
- Guest rate keys matching across topics under `all_topics` and differing under `same_topic`.
- `sa`, `SA!`, `as,`, and `vv` matching the seeded exact terms.
- Migration behavior for absent, empty, partially populated, and fully populated exact-term settings.
- Preservation of custom exact terms during migration.
- Admin setting definition and UI placement for the scope selector.

Run the focused comment spam tests, migration tests, the full PHP unit suite, project lint, and migration guards.

## Success Criteria

With duplicate detection enabled, a five-minute window, and the default `Tüm konular` scope, the second `sa` comment from the same logged-in user—or the same guest IP—must be handled as spam even when it targets a different topic. Administrators can switch back to `Yalnızca aynı konu`, existing custom filter terms are preserved, and no unrelated comment or moderation behavior changes.
