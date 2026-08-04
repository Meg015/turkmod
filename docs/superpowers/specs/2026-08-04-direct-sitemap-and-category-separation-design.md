# Direct Sitemap and Category Separation Design

## Goal

Use `/sitemap.xml` as a normal URL sitemap for the home page and other indexable public static pages. Move category URLs into `/category-sitemap.xml`, keep topic URLs in `/topic-sitemap.xml`, and remove `/page-sitemap.xml`.

## Public Sitemap Structure

- `/sitemap.xml`: home page and indexable public static pages only.
- `/category-sitemap.xml`: indexable category URLs only.
- `/topic-sitemap.xml` and `/topic-sitemap-N.xml`: topic detail URLs only.
- `/profile-sitemap.xml` and its paginated variants: unchanged.
- `/image-sitemap.xml` and its paginated variants: unchanged.
- `/page-sitemap.xml`: removed and returns `404`.
- No sitemap index endpoint will be introduced.

Because an XML sitemap cannot mix `<url>` and `<sitemap>` entries, `/sitemap.xml` will not embed or list the other sitemap files. Discovery of the separate sitemap files will be handled through `robots.txt`.

## Robots Discovery

`robots.txt` will emit separate `Sitemap:` lines for:

- `/sitemap.xml`
- `/category-sitemap.xml`
- `/topic-sitemap.xml` and every generated paginated topic sitemap
- `/profile-sitemap.xml` and every generated paginated profile sitemap when public profile sitemap output is enabled
- `/image-sitemap.xml` and every generated paginated image sitemap when image sitemap output is enabled

The robots generator will calculate the same content totals and page count used by the sitemap handlers, so every generated sitemap is explicitly discoverable without a sitemap index. Category output will remain a single sitemap because categories are expected to stay below the sitemap protocol limit of 50,000 URLs.

## Implementation

Convert `SitemapIndexPage` from a sitemap-index response into the static public-page URL sitemap served at `/sitemap.xml`. It will reuse the public-page catalog, sitemap inclusion rules, canonical URL generation, priorities, change frequencies, cache helpers, and indexing feature flags. The class will be renamed to reflect its new non-index responsibility.

Replace the temporary `PageSitemapPage` with `CategorySitemapPage`. The new handler will own category-tree loading and recursive category URL rendering. It will preserve the existing category visibility/noindex rules and canonical category paths.

Remove every `/page-sitemap.xml` route, reserved path, helper mapping, admin link, and cache reference. Register `/category-sitemap.xml` in the stateless route recognition, route catalog, reserved paths, helper mapping, admin sitemap link list, and route documentation.

`TopicSitemapPage` remains topic-only. Existing profile and image handlers remain unchanged.

## Caching

Use separate cache keys and tags for the static-page sitemap and category sitemap. Version the affected cache keys so deployment cannot serve the previous sitemap-index or page-sitemap XML until cache expiry. Existing global `sitemap` invalidation continues to clear all outputs.

## Indexing Rules

- Global indexing and sitemap feature flags control all outputs.
- Static pages use `seoPublicPageShouldAppearInSitemap()` and `seoPublicPageSitemapPriority()`.
- Dynamic catalog entries such as topic, category, profile, public profile, and search are excluded from `/sitemap.xml`.
- Categories use `seoCategoryShouldAppearInSitemap()`.
- Category URLs appear only in `/category-sitemap.xml`.
- Topic URLs appear only in topic sitemap files.
- No URL is duplicated between `/sitemap.xml`, `/category-sitemap.xml`, and `/topic-sitemap.xml`.

## Failure Behavior

When indexing or sitemap generation is disabled, each URL sitemap returns a valid empty `<urlset>`. Removed `/page-sitemap.xml` and unsupported category pagination routes return `404`.

## Verification

- Run project-wide PHP syntax validation.
- Parse every generated sitemap as XML.
- Confirm `/sitemap.xml` contains the home page and eligible static pages, with no category or topic URLs.
- Confirm `/category-sitemap.xml` contains only eligible category URLs.
- Confirm topic sitemap pages contain only topic URLs.
- Confirm the three URL sets are disjoint and contain no duplicate locations.
- Confirm `robots.txt` lists each enabled sitemap endpoint and every generated paginated sitemap separately.
- Confirm `/page-sitemap.xml` and `/category-sitemap-2.xml` return `404`.
- Confirm existing topic, profile, and image sitemap routes still resolve.
