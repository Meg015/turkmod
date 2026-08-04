# Public Topbar Real-Time Badges

## Goal

Keep the public header's unread message and notification badges current while a
logged-in user remains on a page, without requiring a page reload.

## Scope

The public header will establish one authenticated WebSocket connection per
page. Existing message events and a new notification event will trigger a
refresh of the corresponding topbar dropdown data. The dropdown API responses
remain the source of truth for unread counts and rendered items.

## Data Flow

1. The header renders the current authenticated user id as a data attribute.
2. A shared header runtime connects to the existing `:8080` WebSocket endpoint
   with that user id. The server continues to authenticate the connection from
   the PHP session cookie.
3. When a message is sent, the existing `new_message` event is received by all
   of that thread's participants. The header runtime refreshes the messages
   dropdown endpoint.
4. When notification dispatch stores a notification, it publishes a
   `notification` event for each recipient through the existing loopback
   broadcast endpoint. The header runtime refreshes the notifications dropdown
   endpoint.
5. Each refreshed response updates its badge. If that dropdown is open, its
   list is also re-rendered.

## Resilience

The header runtime reconnects with bounded exponential backoff after a socket
close or connection error. As a fallback for unavailable WebSocket service,
the existing topbar API fetchers will run periodically only while the document
is visible. A visibility change performs an immediate refresh when the page is
shown again.

The runtime never accepts a count directly from a socket payload. This keeps
the server-side unread-count and user-preference rules authoritative and avoids
stale event ordering changing the displayed state.

## Components

- `includes/public-header.php`: supplies the authenticated user id to the
  browser and loads the shared header runtime.
- `assets/js/public-topbar-realtime.js`: owns the single header WebSocket
  connection, reconnect policy, visibility handling, and refresh dispatch.
- `assets/js/public-notifications-menu.js`: exposes a namespaced refresh
  function and accepts the shared refresh events.
- `assets/js/public-messages-menu.js`: exposes a namespaced refresh function
  and accepts the shared refresh events.
- Notification dispatch service: publishes an event only after a notification
  was successfully persisted for a recipient.

## Error Handling

WebSocket failures are silent and do not affect the topbar controls. Failed
background refreshes preserve the currently displayed badge rather than
clearing it. The existing explicit dropdown-open refresh continues to show its
error state if its API request fails.

## Verification

- PHP lint changed server-side files.
- Parse changed JavaScript files with Node.
- Manually or through a browser test, confirm a message event refreshes the
  message badge and a notification event refreshes the notification badge
  without page navigation.
- Confirm an unavailable socket reconnects and the visible-page fallback still
  refreshes both badge counts.
