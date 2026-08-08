# Static Pages Management Design

## Goal

Add an administrator-managed static page system for institutional, legal, and informational content. Administrators can create unlimited pages with the site's existing rich text editing experience, publish them at clean root-level URLs, control their SEO and footer visibility, and manage them without code changes.

The initial system provides draft records for `Hakkimizda`, `Sikca Sorulan Sorular`, `DMCA / Telif Hakki Ihlali Bildirimi`, `Gizlilik Politikasi`, and `Kullanim Kosullari`. The application does not publish placeholder legal text; an administrator must provide and approve each page's content before publication.

## Scope

This change adds:

- Unlimited static page creation and management in the admin panel.
- The existing Quill-based rich text editor for page bodies.
- Draft, published, and archived lifecycle behavior.
- Clean URLs such as `/hakkimizda`, `/sss`, and `/dmca`.
- Page-level SEO, indexing, sitemap, and footer controls.
- Authenticated draft preview.
- Permanent redirects when a published page's slug changes.
- Initial empty draft records for the five agreed institutional and legal pages.

This change does not add page categories, authorship displays, comments, reactions, downloads, topic relationships, a block-based page builder, structured FAQ items, or automatic FAQ schema generation. SSS content is authored as normal rich text with headings, paragraphs, and lists.

## Existing System Fit

Static pages will be implemented as a focused application module rather than as topic records. The module owns its data access, validation, lifecycle, admin operations, public resolution, and redirect behavior. `route.php`, admin entrypoints, theme rendering, and sitemap helpers remain thin integration points.

The implementation reuses:

- The existing admin navigation, layout, form controls, CSRF handling, activity logging, and permission conventions.
- The current Quill toolbar and editor initialization used by topic creation and editing.
- `sanitizeTopicHtml()` and the topic content typography for safe, consistent rich text rendering.
- Existing public header/footer and active theme rendering.
- Existing canonical, Open Graph, robots, and `/sitemap.xml` helpers.
- Existing migration and cache invalidation conventions.

Static pages do not reuse the `topics` table. Their lifecycle, routing rules, SEO fields, and absence of topic interaction features make a dedicated model clearer and safer.

## Data Model

### `static_pages`

Each row represents one managed page:

- `id`
- `system_key`, nullable and unique for seeded system pages
- `title`
- `slug`, unique across active and archived pages
- `body_html`
- `status`: `draft`, `published`, or `archived`
- `seo_title`
- `meta_description`
- `og_image`
- `robots_noindex`
- `robots_nofollow`
- `sitemap_include`
- `show_in_footer`
- `footer_label`
- `footer_order`
- `created_by_admin_id`, `updated_by_admin_id`
- `published_at`
- `created_at`, `updated_at`, `archived_at`

`system_key` identifies the seeded `about`, `faq`, `dmca`, `privacy`, and `terms` records. It is not editable in the admin form. It allows the footer to prefer a published local privacy or terms page without coupling that behavior to a mutable title or slug. Administrator-created pages have a null `system_key`.

Slugs remain globally unique even after archival. This preserves deterministic restore and redirect behavior. An archived slug can be reused only after an administrator explicitly changes the archived page's slug.

`published_at` is set on the first transition to `published` and retained through later edits. Returning a page to draft makes it publicly unavailable without discarding its original publication audit value.

### `static_page_redirects`

Each row preserves a previous public slug:

- `id`
- `page_id`
- `old_slug`, unique
- `created_by_admin_id`
- `created_at`

When a published page's slug changes, the previous slug is recorded transactionally with the page update. Redirect rows point to the page ID rather than a stored destination slug, so every old slug resolves directly to the page's current canonical URL and redirect chains do not accumulate.

If a requested old slug targets a page that is no longer published, the request returns `404` instead of redirecting to a draft or archived page. A current page slug cannot duplicate a reserved route, another page slug, or an existing redirect slug.

## Seed Records

The migration creates these records only when their `system_key` does not already exist:

| System key | Title | Initial slug |
| --- | --- | --- |
| `about` | Hakkimizda | `hakkimizda` |
| `faq` | Sikca Sorulan Sorular | `sss` |
| `dmca` | DMCA / Telif Hakki Ihlali Bildirimi | `dmca` |
| `privacy` | Gizlilik Politikasi | `gizlilik-politikasi` |
| `terms` | Kullanim Kosullari | `kullanim-kosullari` |

All seed records have an empty body, `draft` status, sitemap inclusion enabled for eventual publication, and footer visibility enabled. Draft status prevents them from appearing publicly, in the footer, or in the sitemap until an administrator supplies content and publishes them.

The migration is idempotent and never overwrites an existing page's title, slug, body, status, or SEO settings.

## Routing and URL Resolution

Published static pages use a single root-level segment: `/{slug}`. Nested page paths are outside this scope.

Request resolution follows this order:

1. Existing exact public, authentication, module, event, API, sitemap, asset, and other system routes.
2. Existing topic, category, and profile prefix routes.
3. A published static page whose slug matches the remaining single path segment.
4. A redirect whose old slug matches the remaining single path segment and whose target page is published.
5. The existing public `404` response.

The static page module never handles multi-segment misses. This keeps the extra database lookup bounded to root-level page candidates and prevents it from masking malformed dynamic URLs.

Slug validation normalizes the value with the application's existing slug rules and rejects:

- Empty or malformed slugs.
- All exact system paths and configurable public route paths.
- Route prefixes used by topics, categories, profiles, APIs, admin, events, assets, and sitemap endpoints.
- Existing static page slugs.
- Existing static page redirect slugs.

The reserved-path set is derived from the route catalogs and settings rather than maintained as an unrelated hard-coded list. If configurable route settings change, their new values become reserved before the settings are accepted; a conflict with an existing static page blocks the route-setting change with a clear validation error.

## Admin Experience

### Navigation and Permissions

The admin navigation adds `Icerik > Sabit Sayfalar`. Viewing and mutating this area requires the dedicated `manage_static_pages` permission. The migration grants this permission to the administrator group using the project's existing permission seeding conventions. Every mutation requires CSRF validation and is written to the existing admin activity log.

### List View

The list shows:

- Title and public path.
- Draft, published, or archived status.
- Footer visibility.
- Last update time.
- Edit, preview, archive, and restore actions when applicable.

The view supports text search, status filtering, pagination, and an explicit `Yeni Sayfa` action. Archived pages remain available through the status filter and are not mixed into the default active list.

### Create and Edit Form

The form contains:

- Title.
- Slug, generated from the title until the administrator edits it manually.
- Quill rich text body.
- Status.
- SEO title, meta description, and Open Graph image URL.
- Noindex, nofollow, and sitemap inclusion controls.
- Footer visibility, footer label, and footer order.

The editor reuses the topic toolbar for headings, inline formatting, colors, ordered and unordered lists, blockquotes, links, images, video embeds, alignment, and format clearing. Content is validated and sanitized on the server before storage and sanitized again for public rendering as defense in depth.

Publishing requires a non-empty title, valid non-reserved slug, and non-empty visible body text after HTML is stripped. A draft may be saved with an empty body so the initial records and work in progress remain valid.

Changing the slug of a published page displays the resulting old-to-new redirect in the confirmation message. Repeated form submission is idempotent and does not create duplicate redirect rows.

Archiving is the delete behavior. It requires confirmation, removes the page from public access, footer output, and sitemap output, and retains the record and its redirects for later restoration. There is no irreversible hard-delete action in this scope.

Restoring an archived page always returns it to `draft`. An administrator must review and publish it explicitly; restoration never makes old content public automatically.

### Preview

An authorized administrator can preview a draft, published, or archived page through an admin-authenticated preview URL. Preview uses the same public template, theme, sanitizer, and SEO-resolution path as normal rendering, but it emits `noindex, nofollow`, is excluded from sitemap output, and is never accessible without the permission check.

## Public Rendering

A dedicated static page template renders:

- The page title.
- The sanitized rich text body.
- Optional last-updated metadata when the theme design calls for it.

The page uses the existing public header, footer, responsive container, and topic rich text typography. It does not render author information, categories, topic statistics, comments, reactions, download controls, related topics, or other topic-only UI.

Published pages return `200`. Draft, archived, missing, or empty-body pages return the existing themed `404`. Old slugs return permanent `301` redirects only while their target is published. Redirect destinations are generated through canonical route helpers and never accept arbitrary external URLs.

## Footer Integration

Published pages with `show_in_footer = 1` are loaded in `footer_order`, then title order as a stable tie-breaker. `footer_label` is used when non-empty; otherwise the page title is used.

The dynamic links are supplied to both the active TPL theme footer and the PHP fallback footer. Existing manually configured footer navigation remains available. Duplicate destination URLs are removed before rendering.

For the existing legal link settings:

- A published `terms` system page supplies the local Kullanim Kosullari link.
- A published `privacy` system page supplies the local Gizlilik Politikasi link.
- If the corresponding local page is not published, the existing `terms_url` or `privacy_url` setting remains the fallback.

This preserves existing installations that currently link to external legal documents while allowing local managed pages to take precedence deliberately.

## SEO and Sitemap Integration

Each published page resolves:

- Page title and optional SEO title through the existing title conventions.
- Meta description from the explicit field, with a bounded plain-text body excerpt as fallback.
- Canonical URL from the current slug.
- Open Graph fields from the page values and existing site defaults.
- Robots directives from page-level noindex and nofollow controls plus global indexing settings.
- `WebPage` structured data using the existing safe JSON-LD helpers where supported.

Eligible pages are added to the existing `/sitemap.xml`, which already owns home and indexable public page URLs. A page appears only when it is published, has a non-empty body, has `sitemap_include = 1`, is not noindex, and global sitemap/indexing settings allow it. No new sitemap endpoint is introduced.

The page update, publish, draft, archive, restore, slug change, and relevant SEO setting paths invalidate the public-page sitemap and static-page cache tags. Redirect-only changes invalidate static-page route cache entries.

Because SSS is stored as generic rich text, the application does not infer question-answer pairs or emit `FAQPage` structured data. Incorrect inferred FAQ schema would be less reliable than omitting it.

## Validation and Failure Behavior

- Duplicate or reserved slugs produce a field-level validation error and no partial write.
- Invalid status, footer order, URLs, or SEO values are normalized or rejected according to existing admin form conventions.
- Unsafe HTML, executable attributes, scripts, forms, and unsafe URL protocols are removed by server-side sanitization.
- A database failure during create, update, archive, restore, or redirect creation rolls back the full transaction.
- Public resolution failures log server diagnostics through existing exception handling and return the themed `404` or `500` response without exposing database details.
- A conflicting configurable route change is rejected before it can make an existing published page unreachable.
- Footer and sitemap queries fail closed: an unavailable static page result does not break the rest of the footer or produce malformed sitemap XML.

## Caching

Published page lookups may use the existing core cache with keys based on slug and page ID. Cached data contains only public, sanitized page fields. Draft and archived preview data is not placed in the public cache.

All page mutations invalidate the affected page ID, old slug, new slug, footer page-list, and sitemap tags. Correctness must not depend on cache availability; a disabled or unavailable cache falls back to database reads.

## Verification Strategy

Focused automated verification covers:

- Idempotent table creation and seed records.
- Page creation, draft updates, publishing, archiving, restoring, and list filters.
- `manage_static_pages` permission and CSRF enforcement.
- Required-field validation for publication.
- Slug normalization, uniqueness, redirect conflicts, and system route conflicts.
- Transactional redirect creation and direct-to-current-slug resolution after repeated slug changes.
- `200`, `301`, `404`, and authenticated preview behavior.
- Quill HTML preservation and removal of unsafe tags, attributes, and protocols.
- Canonical, meta, Open Graph, robots, and `WebPage` structured data output.
- Sitemap inclusion and exclusion across status, noindex, sitemap, and global settings.
- Footer ordering, labels, deduplication, and terms/privacy fallback behavior.
- Cache invalidation for content, slug, status, footer, and SEO changes.

Manual browser verification covers:

- Admin list and editor behavior on desktop and mobile widths.
- Quill editing and saved-content round trips.
- Draft preview matching public published rendering.
- Theme-consistent public typography for long legal text, headings, lists, links, images, and SSS-style sections.
- Footer layout with short, long, and numerous page labels without overlap.
- Clean URL navigation, old-slug redirects, and themed error pages.

Project-wide PHP syntax validation runs after implementation. Sitemap output is parsed as XML, and representative public/admin flows are checked with the local application server.

## Acceptance Criteria

- An authorized administrator can create unlimited static pages without code changes.
- The five initial institutional/legal pages exist as unpublished empty drafts after migration.
- A page cannot become public until it has a title, safe unique slug, and non-empty visible content.
- Published pages resolve at clean root-level URLs and use the active public theme.
- Static pages contain no topic author, category, comment, reaction, download, or related-content features.
- Changing a published slug preserves old links with a permanent redirect to the current canonical URL.
- Draft and archived pages are unavailable publicly but previewable by an authorized administrator.
- Page HTML is sanitized server-side while supported Quill formatting survives a save/render round trip.
- Published eligible pages appear exactly once in `/sitemap.xml`; excluded pages do not appear.
- Footer-enabled published pages render in configured order in both footer implementations.
- Published local terms/privacy pages take precedence over legacy URL settings; legacy URLs remain fallback behavior.
- Existing system routes always win over static page resolution, and conflicting slugs or route-setting changes are rejected.
- Existing topic, category, profile, contact, authentication, SEO, sitemap, and footer behavior remains functional.
