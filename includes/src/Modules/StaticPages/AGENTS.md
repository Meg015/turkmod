# StaticPages Module Guidance

- `module.php` is the metadata source for permissions, lifecycle, migrations, and routes.
- `StaticPageService` owns page validation, persistence, lifecycle transitions, slug conflicts, redirects, footer queries, and sitemap eligibility.
- Root-level dynamic page dispatch must remain after all exact and prefixed application routes.
- Public rendering must sanitize stored rich text and must not expose draft or archived pages.
- Restoring an archived page always returns it to draft.
- Keep `database/schema.sql` aligned with module migrations.
- Run `composer lint`, `npm run build`, and migration guard after changes.
