# Admin Notifications Shared HTTP Design

## Goal

Remove the JavaScript build violations in `admin/assets/notifications-page.js` without changing the bulk email campaign user experience or API contract.

## Scope

The four direct JSON request paths in the bulk email campaign controller will use the existing `window.adminFetchJson` helper:

- operational overview refresh;
- campaign action POST requests;
- active campaign polling;
- campaign edit/history loading.

No endpoint, payload, polling interval, DOM rendering, button state, toast copy, or campaign lifecycle behavior will change.

## Design

Each direct `fetch()` plus `response.json()` pair will be replaced with `window.adminFetchJson(url, options)`. Request methods, `FormData`, credentials, and existing headers will be retained where they are behaviorally relevant. Calls whose errors are already handled silently or rendered by the local controller will pass `notifyError: false` so the shared helper does not add duplicate error toasts.

The shared helper already validates HTTP/application failures, parses JSON, updates the global and form CSRF tokens, retries eligible CSRF failures once, and raises forbidden-session UI. The local POST wrapper will continue copying a returned `csrfToken` into its closure variable because subsequent payloads read that variable directly. Existing checks for required campaign data will remain local.

The controller will fail clearly if `window.adminFetchJson` is unavailable instead of falling back to direct requests, because the admin shell is the required provider and the build contract forbids direct AJAX usage.

## Error Handling

- Overview and polling failures remain transient and are retried by their existing timers.
- Campaign actions continue to surface errors through the current local error box and toast flow.
- Edit/history load failures continue to use the current local error box.
- Malformed JSON, non-success HTTP responses, `success: false`, authorization failures, and CSRF refresh are delegated to the shared helper.

## Verification

1. Run `node --check admin/assets/notifications-page.js`.
2. Confirm the file contains no direct `fetch()` or response `.json()` calls.
3. Run `npm run build:js` and require a successful exit.
4. Run `git diff --check` on the changed source file.

## Non-Goals

- Refactoring unrelated notification page code.
- Changing bulk campaign UI or server APIs.
- Fixing warnings or dirty-worktree changes outside this file.
