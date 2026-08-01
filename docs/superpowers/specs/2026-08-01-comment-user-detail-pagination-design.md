# Comment User Detail Pagination Design

## Goal

Prevent long user-history lists from stretching and visually degrading the Comment Management user detail modal. Each history tab displays at most 10 rows at a time.

## Scope

Pagination applies independently to these modal tabs:

- Summary activity
- Comments
- Topics
- Reports
- Admin notes
- Restriction and moderation history

The profile header, statistics, modal actions, and the existing full-history link remain unchanged.

## Data Flow

The existing user-details endpoint continues returning its current recent-record collections, which are capped at 20 records per collection. The browser stores these collections in the existing modal data object and slices the active tab's collection into pages of 10 rows.

This intentionally keeps the modal focused on recent information. Administrators use the existing full-history link when they need records older than the endpoint's recent-record window.

## Interaction Design

- Every tab starts on page 1 when the modal opens or reloads user data.
- Each tab maintains its own current page while the modal remains open.
- Only the current page's maximum 10 rows are rendered.
- Compact Previous, numbered-page, and Next controls appear below a list only when it has more than 10 rows.
- Previous and Next are disabled at the respective boundaries.
- Clicking a page control updates only the related tab panel and does not reload the API.
- Empty states remain unchanged and never show pagination.
- Pagination buttons expose accessible labels and the current page through `aria-current`.

## Implementation Boundaries

- `admin/assets/comments-manager-page.js` owns pagination state, list slicing, rendering, and delegated click handling.
- `admin/assets/admin.css` provides compact responsive pagination styling scoped to the user detail modal.
- The API contract and `admin/comments-manager.php` modal markup do not need structural changes.

## Error Handling

Invalid or stale page values are clamped to the available page range. Reloading data resets all tab pages to page 1. Existing loading, retry, empty, and request-race behavior remains intact.

## Verification

- Confirm lists with 0, 1, 10, 11, and 20 records render correctly.
- Confirm pagination appears only for 11 or more records.
- Confirm no page displays more than 10 rows.
- Confirm each tab retains its own page during tab switches.
- Confirm reopening the modal resets every tab to page 1.
- Confirm Previous and Next boundary states and accessible attributes.
- Confirm layout on desktop and mobile widths.
