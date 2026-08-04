# Google Sitemap Compliance Hardening Design

## Goal

Make the sitemap system conform to current Google Search sitemap guidance, eliminate empty or misleading sitemap declarations, and keep pagination, cache, routing, and administrative settings consistent.

The user-approved public structure remains unchanged:

- `/sitemap.xml` is the sitemap index.
- Category, topic, profile, and image sitemap files are listed by the index when enabled and non-empty.
- Home and other static-page URLs are not included.
- No `page-sitemap.xml`, `static-sitemap.xml`, or `home-sitemap.xml` endpoint is introduced.

## Verified Current State

- Topic sitemaps contain 4,070 unique URLs across five pages; sampled URLs return `200`, self-canonical, and `index, follow`.
- All 34 category URLs return `200`, self-canonical, and indexable responses.
- XML responses are well formed, use absolute same-site URLs, and are below Google's 50,000 URL and 50 MB limits.
- ETag conditional requests correctly return `304`.
- The sitemap index currently lists five image sitemap files even though every one is empty.
- Sitemap index and category entries currently use generation time as `lastmod`, which is not a verifiable content modification time.
- Image sitemap output still includes Google's deprecated `image:caption` element.
- Category sitemap output is not paginated.
- Out-of-range topic, profile, and image sitemap page requests return empty `200` responses.

## Sitemap Inventory

Create one shared sitemap inventory component for page counts and route validation. It will use the same visibility filters as the handlers and expose totals/page counts for:

- visible categories;
- indexable topics;
- public profiles;
- image-bearing topics.

The inventory will apply `sitemap_max_urls`, clamped to `1..50000`, consistently. The sitemap index will list only sitemap pages whose inventory total is greater than zero. This removes empty profile/image declarations and gives handlers one authoritative maximum page number.

Image inventory and image handler selection must use the same candidate rules. Image sitemap output is active only when:

- `image_sitemap_enabled=1`;
- at least one of hero, inline, or media image sources is enabled;
- image paths are crawlable under the current robots configuration.

With the current `robots_disallow_uploads=1` setting, local upload images are intentionally excluded and empty image sitemap files are omitted from the sitemap index. If uploads are later allowed, image-bearing topics are selected and paginated using the same SQL/source predicates in both inventory and handler code.

## Category Pagination

Flatten the visible category tree in deterministic tree order, retaining each node's parent slug for canonical URL generation. Slice the flattened list by `sitemap_max_urls`.

- Page 1: `/category-sitemap.xml`
- Later pages: `/category-sitemap-N.xml`

Register paginated category routes in stateless route detection and route dispatch. The sitemap index will list the exact number of non-empty category sitemap pages.

## Last-Modified Semantics

Google uses `lastmod` only when it consistently reflects a verifiable significant update.

- Keep topic, profile, and non-empty image URL `lastmod` values derived from database timestamps.
- Remove `lastmod` from category entries because the current category tree does not provide a reliable page modification timestamp.
- Remove `lastmod` from sitemap-index entries instead of writing the index generation time.
- HTTP `Last-Modified` remains for response-cache validation; it is separate from XML `lastmod` semantics.

## Image Sitemap Compliance

- Remove deprecated `image:caption` output and its unused caption data flow.
- Keep only required `image:image` and `image:loc` elements.
- Clamp `image_sitemap_max_images` to `1..1000`, matching Google's per-page image limit.
- Keep robots filtering so blocked local uploads are never declared to Google.

## Invalid Page Handling

For category, topic, profile, and image sitemap pagination, requests above the authoritative maximum page return an XML `404` response. Page 1 remains a valid empty sitemap only when the corresponding feature is intentionally disabled; disabled sitemap types are not listed by the index.

This prevents arbitrary empty sitemap URLs from returning successful responses and wasting crawler requests.

## Caching

- Version all affected sitemap cache keys so stale index/category/image XML cannot survive deployment.
- Preserve the existing global `sitemap` cache tag and invalidation calls.
- Cache inventory-derived index output through the existing `TaggableCache` injection.
- Preserve ETag, HTTP `Last-Modified`, `Cache-Control`, and `304 Not Modified` behavior.

## Admin Consistency

- Add server-side bounds for `sitemap_max_urls` and `image_sitemap_max_images` regardless of submitted admin values.
- Update image-count help text to state Google's 1,000-image maximum.
- Keep `priority` and `changefreq` settings for backward compatibility even though Google ignores them; they remain valid sitemap protocol elements.

## Verification

- Run project-wide PHP syntax validation.
- Validate the sitemap index and URL sitemaps against the official sitemap XSDs.
- Confirm every sitemap-index URL returns `200`, valid XML, and a non-empty URL set.
- Confirm topic URLs remain unique across all pages and category URLs remain unique across all category pages.
- Confirm every URL is absolute, same-site, canonical, indexable, and properly XML-escaped.
- Confirm no sitemap exceeds 50,000 URLs or 50 MB uncompressed.
- Confirm index/category XML contains no fabricated `lastmod` values.
- Confirm image XML contains no deprecated `image:caption`, no robots-blocked images, and no more than 1,000 images per page URL.
- Confirm out-of-range category/topic/profile/image sitemap pages return `404`.
- Confirm `robots.txt` exposes only `/sitemap.xml`.
- Confirm ETag conditional requests return `304` after the changes.

