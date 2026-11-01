# Google Ads Measurement Implementation Plan

> **For agentic workers:** Execute this scoped repair in this session under the user's instruction to apply remaining fixes.

**Goal:** Restore and verify the existing Google Ads pageview measurement without activating advertising.

**Architecture:** Add a small renderer helper using existing settings and environment configuration. Extend the existing external initializer and CSP defaults. Apply only these changes to production-derived source snapshots.

**Tech Stack:** PHP, JavaScript, Google tag, CSP, Chrome network inspection.

**Spec:** `docs/superpowers/specs/2026-11-01-google-ads-measurement-design.md`

## Global Constraints

- No funding, activation, or automated top-up.
- Existing conversion destination only; homepage conversion only.
- Preserve unrelated local edits; production deployment from production snapshots.
- Backups outside public webroot; no secrets in output.

## Review Focus

- Blank and malformed tag IDs emit no Google scripts.
- Conversion destination must match the configured AW tag.
- Category pages initialize the tag but emit no homepage conversion.
- Homepage query strings and installation subdirectories preserve correct scoping.
- Optional conversion attribute emits exactly one queued conversion event.

## Tasks

- [x] Add renderer helper in `includes/PublicThemeRenderer.php`, and conversion handling in `assets/js/google-analytics-init.js`; test input classes above.
- [x] Add documented Google script/frame origins to `includes/src/Core/Security/SecurityHeadersMiddleware.php` and `includes/init.php`; lint changed PHP.
- [x] Build focused patch against production snapshots, back up originals and `.env`, set `GOOGLE_ADS_CONVERSION_SEND_TO`, verify hashes, and atomically replace changed files.
- [x] Confirm live Google library and conversion endpoint responses without a paid click. Recheck campaign remains paused and save proof.

## Verification record — 1 November 2026

- PHP regression: 23 checks passed locally and against production-derived staging. JavaScript initializer regression passed locally. PHP lint passed locally, in staging, and on the deployed files. Production `git diff --check` passed.
- Live clean-browser responses: Google tag library, external initializer, labeled page conversion endpoint, and CCM conversion endpoint returned HTTP 200. No paid click or fabricated click ID was used. Category page has the base tag without the homepage conversion attribute.
- Some optional no-CORS CCM transports are blocked by existing COEP; successful conversion fallbacks were verified. COEP was preserved.
- Production deployment changed only the four scoped source files (43 insertions, 4 deletions), plus the ignored environment configuration. Originals and deployment guards are in `/root/codex-backups/turkmod-ads-20261101-01a1973b` outside the public webroot. No commit was made and unrelated local edits were preserved.
- Campaign 24414301137 remains paused. Average daily budget is 19.50 TL, standard daily billed ad cost limit 39 TL, maximum CPC 3 TL. Turkey surcharges and taxes are additional; the difference toward 50 TL is a reserve, not a guaranteed tax-inclusive cap. Activate no earlier than 2 November 2026 because budget changes use the highest selected budget for the change day.
- All ten ads are policy approved and point to `https://turkmod.net/`. There are 93 exact/phrase keywords across ten game groups. ATS headline keyword coverage was improved to high. No sitelinks were added under the homepage-only requirement.
- The 18 Wheels, FS2009, and MudRunner editor evaluations show Good with all four text criteria high, while the ad table still displays Poor. No edits were made solely to refresh those scores. FS25 and ATS remain Average with all four text criteria high and sitelinks absent.
- Four campaign callouts were saved; the subsequent final check shows all four as Eligible (Uygun). Conversion default value was changed from USD 1 to no value. The subsequent conversion detail check shows Awaiting conversions (Dönüşümler bekleniyor), explaining that no conversions in the last seven days may be due to the inactive campaign. The earlier measurement misconfiguration is no longer shown in this detail view.
- Identity and funding-source verification are displayed as optional without a mandatory deadline. After the user's explicit reply, the account declaration that EU political ads will not be run was submitted; Google Ads displays Completed. Campaign remains paused. Auto top-up remains disabled. No balance was added.
- Proof files: `google-ads-olcum-test-sonucu.json`, `google-ads-ek-aciklamalar.png`, `google-ads-metin-guc-denetim.png`, and `google-ads-hazir-kapali.png` in this task's visualization directory.
