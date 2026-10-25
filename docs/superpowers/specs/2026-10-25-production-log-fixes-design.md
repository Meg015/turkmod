# Production log fixes

The user approved fixing the issues identified in the production-log audit. Apply targeted corrections in the existing checkout and preserve the pre-existing admin changes.

Use a direct, zero-row query against the configured rate-limit table instead of querying INFORMATION_SCHEMA. Validate the columns the limiter actually uses, retain fail-closed behavior, and preserve underlying exception details in private logs. Do not create production tables or switch to an unshared limiter.

Follow-up audit: use the database clock for expiry creation, comparisons and remaining seconds, so PHP/MySQL timezone differences cannot reset or prolong a limit. Expired reads must not delete keys another request may have renewed. Validate with local MySQL connection-scoped temporary tables across differing timezones.

Capture the canonical profile URL in the pagination closure. Make comment loading tolerate optional markup, show escaped errors without throwing a second exception, retain already loaded comments on a failed refresh, and replace the Markdown lookbehind expression with compatible syntax.

Version comment requests and hash-loading loops. A failed sort restores the previously loaded order/page; stale requests and polling cannot overwrite a newer selection. Hash lookup waits for pending loads and continues after a sort success/rollback or target change.

Keep raster MIME, extension, size and decode checks. Stop treating arbitrary PHP-like byte pairs and function names in compressed images as executable code. Reject explicit PHP opening tags and sanitize accepted raster images before publication. Re-encode JPEG/PNG/WebP; preserve GIF animation using bounded container parsing and independent decoding of every frame. Keep palettes, raster frames, graphic controls and recognized loop metadata, and strip other extensions/trailing data. Bound GIFs to 1000 frames and 240 million cumulative frame pixels. Preserve format and alpha; replace the old avatar only after the new image and database update succeed. Reject images exceeding the shared decoder's size/memory limits. If the server cannot decode animated WebP, report that explicitly and suggest GIF.

Filter errors explicitly attributed to browser extensions and the observed stackless browser transition cancellations. Keep application errors. Filter individual CSP items so a mixed batch cannot retain extension noise beside a genuine violation; keep unknown sources and maintain the current policy.

PHP >=8.1; no new dependencies. Use the shared image processor. Changes are local until deployed. Validate behavior with focused PHP/Node regressions, the existing media regression, PHP lint and the JavaScript build. Test missing table/columns, genuine PDO failures, image polyglots and binary false positives, nullable rejection reasons, missing comment loader and real rendering/network failures.
