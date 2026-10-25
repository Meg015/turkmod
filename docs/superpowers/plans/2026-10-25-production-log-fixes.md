# Production Log Fixes Implementation Plan

**Goal:** Resolve the confirmed production failures and reduce misleading diagnostics.

**Architecture:** Retain the database limiter, shared image processor and existing frontend entry points. Correct the failing operations and test the observed failure modes without changing admin work already in progress.

**Tech stack:** PHP >=8.1, PDO, GD, browser JavaScript, Node's built-in test runner, esbuild.

**Spec:** ../specs/2026-10-25-production-log-fixes-design.md

## Constraints and review focus

- Preserve database-backed rate limiting and propagate genuine backend failures.
- Never remove the current avatar before the replacement database update succeeds.
- Reject invalid/oversized raster images and remove payloads through re-encoding.
- Retain first-party error reports and mixed CSP batch violations.
- Preserve existing comments when a refresh fails, including missing loader markup.

## Tasks

- [x] Update `DatabaseRateLimiter::assertSchemaReady()` in `includes/src/Core/Security/DatabaseRateLimiter.php` to select the required columns with `WHERE 1=0`; test using an in-memory SQLite table and failure cases. Extend `includes/ErrorHandler.php` private trace formatting to include chained exceptions and SQLSTATE/driver code.
- [x] Capture `$profilePageBaseUrl` in `includes/src/Engine/Users/Http/profile-page-content.php`. Execute the actual pagination closure under warning-to-exception handling for root/subdirectory routes and filtered tabs.
- [x] Fix `assets/js/topic-comments.js` loader/error/pagination guards and remove regex lookbehind. Use a VM DOM harness to exercise successful and failed loads, missing optional markup, escaped errors and Markdown output. Failed load-more requests retry the same page.
- [x] Add `mediaSanitizeImage(string $filePath): void` in the shared image processor and bounded GIF sanitization with independent per-frame decoding. Update `includes/UploadSecurity.php` raster checks and `profileUploadAvatar()` replacement order. Test all accepted raster formats, animation preservation, binary false positives, explicit PHP payloads, large dimensions and cleanup on failure.
- [x] Update `Analytics::trackError()` in both frontend sources and `CspReportEndpoint` item filtering. Test extension filtering, null rejections, transition cancellations and retained application violations.
- [x] Run focused regressions, existing media regressions, `php scripts/php-lint.php` and `npm run build:js`; inspect the final diff and record results.

Implement in this session. Review only the scoped changes; do not stage or commit the user's unrelated files.

## Follow-up audit completed

- [x] Add `tests/rate-limiter-mysql-regression.php`: 60 actual MySQL assertions using temporary tables across six PHP/database timezone pairs. Use database timestamps for all limiter expiry operations and retain renewed keys during expired reads.
- [x] Add versioned comment request/hash-loop coordination, rollback failed sort changes, and reject stale responses/polls. Add successful/failed sort and hash-change race tests; 17 Node/HTTP tests pass.
- [x] Complete independent final review, JavaScript build and whitespace/syntax checks; record results in `docs/verification/2026-10-25-production-log-fixes.md`.
