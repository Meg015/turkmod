# Page Sitemap Separation Design

## Goal

Ensure `topic-sitemap.xml` and its paginated variants contain only topic detail URLs. Move the home page, indexable public pages, the category listing page, and indexable category URLs into a dedicated `page-sitemap.xml`.

## Public Sitemap Structure

- `/sitemap.xml`: sitemap index.
- `/page-sitemap.xml`: home page, indexable public static pages, category listing page, and indexable category pages.
- `/topic-sitemap.xml` and `/topic-sitemap-N.xml`: topic detail URLs only.
- `/profile-sitemap.xml` and its paginated variants: unchanged.
- `/image-sitemap.xml` and its paginated variants: unchanged.

The sitemap index will reference `page-sitemap.xml` alongside the existing topic, profile, and optional image sitemap entries.

## Implementation

Add a `PageSitemapPage` HTTP handler that owns the static-page and category URL generation currently embedded in `TopicSitemapPage`. The new handler will reuse the existing public-page catalog, category visibility rules, canonical URL generation, XML response helpers, and sitemap cache conventions.

Remove static-page and category rendering from `TopicSitemapPage`. Its first page will no longer receive special non-topic entries, so every topic sitemap page follows the same topic-only contract.

Register `page-sitemap.xml` in the sitemap route resolver, stateless route recognition, request allowlists/labels, and the `seoGenerateSitemapOutput()` handler mapping. Existing sitemap URLs remain valid.

## Indexing Rules

The move must preserve all current inclusion and exclusion behavior:

- Global indexing and sitemap feature flags continue to control output.
- Public pages continue to use `seoPublicPageShouldAppearInSitemap()`.
- Categories continue to use `seoCategoryShouldAppearInSitemap()`.
- Existing canonical URL, `lastmod`, `changefreq`, and `priority` values remain unchanged.
- Dynamic catalog entries such as topic, category, profile, public profile, and search are not emitted as static pages.
- A URL must not appear in both the page sitemap and topic sitemap.

## Caching

Use a dedicated page sitemap cache key and the existing sitemap cache tags, including a page-specific tag. Existing global `sitemap` invalidation will therefore continue to clear all sitemap outputs after relevant content or configuration changes.

## Failure Behavior

The new handler follows the existing sitemap response behavior. When indexing or sitemap generation is disabled, it returns a valid empty sitemap URL set rather than leaking excluded URLs or producing malformed XML.

## Verification

- Run PHP syntax checks on every modified PHP file.
- Parse generated sitemap XML to confirm it is well formed.
- Confirm `topic-sitemap.xml` contains topic detail URLs only.
- Confirm `page-sitemap.xml` contains the home URL and eligible category/public-page URLs, and contains no topic detail URLs.
- Confirm `/sitemap.xml` references `/page-sitemap.xml` and retains the existing sitemap entries.
- Confirm excluded/noindex public pages and categories remain absent.
- Confirm existing paginated topic, profile, and image sitemap routes still resolve.

