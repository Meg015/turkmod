# Comment Short-Term Exception Design

## Status

Approved on 2026-07-26. This document supersedes only the `sa` and `as` seed-term decisions in `2026-07-26-comment-duplicate-scope-design.md`. Cross-topic duplicate detection and the `vv` exact-term rule remain unchanged.

## Goal

Allow comments whose entire normalized body is `sa` or `as` without weakening duplicate-comment protection. Continue rejecting `vv` through the exact-term spam filter.

## Behavior

- `sa` and `as` are not default exact spam terms.
- Case and surrounding punctuation variants such as `SA!` and `as,` are also not rejected solely by the exact-term filter.
- `vv` remains a default exact spam term and continues to be rejected.
- Duplicate-comment detection remains enabled and keeps its configured window and scope. Therefore, a first `sa` or `as` comment may be accepted while a repeated normalized copy within the configured window may still be rejected as `duplicate_comment`.
- Other spam checks, including minimum character and nonsense-word checks, are not changed by this correction.

## Administration Defaults

The `comment_spam_exact_terms` catalog default becomes `vv`. Its help text describes `vv` as the default short spam expression and no longer presents `sa` or `as` as defaults.

Administrators can still manage other exact spam terms through the existing field. This correction intentionally removes `sa` and `as` from stored exact-term lists because the required site-wide behavior is that these greetings must not be blocked as exact terms.

## Data Migration

The pending short-term migration is corrected so it:

1. Parses the stored exact-term list using the existing newline, comma, and semicolon separators.
2. Removes entries whose normalized exact value is `sa` or `as`, including case and surrounding-punctuation variants.
3. Preserves all unrelated custom entries and their spelling.
4. Ensures `vv` exists exactly once according to normalized comparison.
5. Seeds `vv` when the setting is absent or empty.
6. Invalidates the admin-settings cache after a successful update.

The migration remains idempotent and one-way. Its rollback continues to throw because it cannot reconstruct intentionally removed terms safely.

## Error Handling

- The migration exits without changes when `admin_settings` does not exist.
- Unexpected database failures propagate so the migration runner cannot record a partial success.
- Runtime duplicate-scope fallback remains `all_topics`; this change does not alter that behavior.

## Testing

Regression coverage will verify:

- `sa`, `SA!`, `as`, and `as,` are not marked as exact-term spam when no other rule applies.
- `vv` remains exact-term spam.
- Missing and empty settings migrate to `vv`.
- Stored `sa`/`as` variants are removed while unrelated custom terms are preserved.
- Running the migration repeatedly does not duplicate or otherwise change the resulting list.
- Cross-topic duplicate-comment tests continue to pass, confirming that repeated `sa`/`as` comments are still controlled by the duplicate rule.

Run the focused comment-spam and migration tests, the full PHPUnit suite, project lint, and migration guards.
