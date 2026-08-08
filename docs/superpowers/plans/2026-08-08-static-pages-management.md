# Static Pages Management Implementation Plan

## 1. Module Skeleton and Ownership

- Add `App\Modules\StaticPages` with `module.php`, `routes.php`, lifecycle, schema installer, service, HTTP handler, and support helpers.
- Declare the `manage_static_pages` permission and the admin menu metadata in the module manifest.
- Keep dynamic slug lookup out of the exact-route registry; add one bounded root-slug dispatch step in `route.php` after all existing static and prefixed content routes.
- Add an `AGENTS.md` describing the module ownership boundaries, route order, schema rules, and verification commands.

## 2. Schema and Seed Data

- Add an idempotent module migration for `static_pages` and `static_page_redirects` with foreign keys, unique keys, lookup indexes, and MySQL/SQLite-compatible timestamp handling.
- Implement the five `system_key` seed records as empty drafts without overwriting existing data.
- Update `database/schema.sql` with the same final schema and seed records for clean installations.
- Register the module lifecycle and migration directory through `module.php`.
- Verify migration autogeneration and migration guard behavior with only the intended schema changes staged.

## 3. Static Page Service

- Implement page listing, filtering, pagination, create, update, publish, archive, restore-to-draft, preview lookup, published slug lookup, redirect lookup, footer list, and sitemap list operations.
- Centralize input normalization for titles, slugs, HTML, SEO fields, booleans, footer labels, and ordering.
- Reuse `slugify()` and `sanitizeTopicHtml()`; require non-empty visible content only when publishing.
- Build the reserved-slug set from exact route catalogs, configurable static paths, dynamic route prefixes, and top-level infrastructure paths.
- Reject collisions with current page slugs and historical redirect slugs.
- Wrap page writes and published-slug redirect creation in database transactions.
- Resolve redirect rows through the current page slug to avoid redirect chains.
- Log mutations through the existing activity and admin audit facilities.

## 4. Admin Page Management

- Add `admin/static-pages.php` with `manage_static_pages` authorization and CSRF-protected create, edit, archive, restore, status, and filter flows.
- Build a searchable, paginated list with stable status badges and responsive row actions.
- Build one create/edit form using the existing Quill assets and toolbar conventions, including title, slug, body, lifecycle, SEO, robots, sitemap, and footer controls.
- Add `admin/static-page-preview.php` for permission-protected draft/archived previews through the public renderer with forced `noindex, nofollow`.
- Add a static-pages sidebar entry under `Icerik` and active-route mapping.
- Add narrowly scoped styles and editor initialization in dedicated static-page admin assets; include source assets in the normal build inputs when necessary.
- Preserve unrelated current changes in admin and schema files while integrating against their latest workspace contents.

## 5. Public Routing and Rendering

- Add a `StaticPagePage` handler and focused public content controller that load the current root slug through the service.
- Dispatch only unresolved single-segment paths after existing exact and prefixed routes.
- Return `200` for published non-empty pages, `301` for valid historical slugs, and the existing themed `404` for missing, draft, archived, or empty pages.
- Add a `static-page.tpl` theme template and PHP fallback rendering that reuse the topic rich-text typography without topic metadata or interaction UI.
- Pass explicit page title, meta description, canonical URL, Open Graph image, robots directives, and `WebPage` structured data into the current public header/SEO flow.
- Ensure previews reuse the same rendering path while bypassing public status lookup only after admin authorization.

## 6. Footer and Legal Link Integration

- Load published footer-enabled pages in `footer_order`, then title order.
- Add the managed links to both `PublicThemeRenderer` TPL variables and `includes/public-footer.php` fallback output.
- Deduplicate by normalized destination URL while retaining existing manually configured footer links.
- Prefer published `terms` and `privacy` system pages over `terms_url` and `privacy_url`; preserve the existing settings as fallback when local pages are unavailable.
- Keep draft and archived pages out of every footer path.

## 7. Sitemap, SEO, and Cache Integration

- Append eligible managed pages to the existing `/sitemap.xml` URL set without introducing a new sitemap endpoint.
- Apply published, non-empty, sitemap-enabled, indexable, and global indexing rules before emitting a URL.
- Generate canonical URLs from current slugs and prevent old redirect slugs from entering the sitemap.
- Add managed-page cache keys/tags for slug, ID, footer list, redirect lookup, and sitemap list using the existing core cache helpers where available.
- Invalidate old slug, new slug, ID, footer, redirect, and sitemap entries after every relevant mutation.
- Keep database reads as the correctness fallback when cache is disabled or unavailable.

## 8. Verification

- Add a focused CLI verification script for seed idempotency, CRUD transitions, restore-to-draft, reserved/duplicate slug rejection, redirect behavior, sanitization, footer eligibility, and sitemap eligibility against an isolated test database when supported by the existing database layer.
- Run `composer lint`, `npm run build`, migration guard, and the focused verification script.
- Start the local PHP application on an unused port and verify public `200`, `301`, and `404` flows.
- Use authenticated browser checks for the admin list, editor, preview, publish, archive, restore, desktop layout, and mobile layout.
- Capture desktop and mobile screenshots and check for overflow, overlapping controls, broken editor dimensions, footer wrapping, and theme consistency.
- Parse `/sitemap.xml` as XML and confirm eligible managed URLs appear once while drafts, archives, noindex pages, and old slugs remain absent.

## 9. Completion Criteria

- All acceptance criteria in `docs/superpowers/specs/2026-08-08-static-pages-management-design.md` pass.
- No existing exact or prefixed route changes behavior.
- No unrelated working-tree changes are reverted, reformatted, staged, or committed.
- The implementation commit includes only static-page feature files and the exact integration changes required by the feature.
