# Production log remediation verification — 25 October 2026

Changes address the supplied local copy of production logs. They have not been deployed to turkmod.net.

## Behavior

- Rate-limit readiness validates the actual table/columns without INFORMATION_SCHEMA. Expiry creation, expiry comparisons and remaining seconds now use the database clock, fixing counter resets when PHP and MySQL timezones differ. Expired reads cannot delete a concurrently renewed key; NULL expiry is inactive and resets on the next hit. Missing schema and genuine backend errors remain failures; private exception logs now retain the underlying PDO cause/SQLSTATE/driver code. The historical production PDO cause was absent, so its exact origin cannot be established from those logs alone.
- Profile pagination retains the canonical route and selected filters inside its nested closure.
- Comments tolerate missing optional markup, render errors as text, retain loaded comments on failed refresh, and retry the correct page after failed load-more. Failed sort changes retain the previous sort/page; stale requests and polling cannot overwrite a newer selected sort. Markdown no longer requires regex lookbehind.
- Upload validation accepts harmless short-tag/function-name bytes in raster data while retaining explicit PHP-tag rejection, MIME/type/size/decode checks and shared dimension/memory limits. JPEG/PNG/WebP are re-encoded before publication. GIFs retain independently decoded frames, timing, transparency and looping while metadata/trailing bytes are removed. GIF limits: 1000 frames, 240 million cumulative pixels. Animated WebP unsupported by server GD receives an explicit error suggesting GIF.
- Avatar replacement reserves a new filename, sanitizes before publication, preserves the previous avatar on file/database failure and removes the replacement on database failure.
- Analytics ignores identified extension-origin errors and the observed stackless browser transition cancellations; first-party/unknown-origin errors remain. Mixed CSP batches retain actual violations and discard individually identified extension items. CSP policy was not relaxed.

## Validation

- `php tests/production-log-regression.php`: 43 checks passed.
- `php tests/media-webp-regression.php`: 18 existing image/bot scenarios passed.
- `php tests/rate-limiter-mysql-regression.php`: 60 checks passed against local MySQL temporary tables, across six PHP/database timezone combinations. Existing application tables are not modified.
- `node --test tests/production-log-regression.test.mjs tests/avatar-upload-regression.test.mjs`: 17 tests passed. Includes real local multipart uploads, avatar replacement success, invalid input, forced database failure, generic upload sanitization, failed sorts, stale responses/polls, deep-link loading across successful/failed sort changes and target-hash changes during pending requests.
- `php scripts/php-lint.php`: 448 PHP files passed.
- `npm run build:js`: passed; generated public/theme JavaScript updated.
- Git whitespace validation passed with the checkout's CRLF setting.
- Independent review identified GIF animation loss in the initial approach; the final implementation preserves GIF frames and adds regression coverage.
- Follow-up review found and verified the correction for a deep-link/sort race. No blocking issues were found in the final request/hash coordination changes.

Pre-existing and concurrently edited admin/notification files are outside this change. No production database change or schema migration is required by these corrections. Deploy the changed PHP sources (including `gif-sanitizer.php`), source/compiled JavaScript together, then verify fresh production logs and registration/avatar/comment flows.

One early test created `C:\Windows\Temp\turkmod-log-regression-4d4e4812b6158b1d\safe.jpg`; automatic approval review blocked its cleanup with `blocked by policy`. Subsequent tests use isolated directories under project storage and clean those successfully.
