# Production WebSocket Hardening

## Goal

Deliver real-time public topbar updates over a production-safe WebSocket
deployment, with HTTPS support, automatic process recovery, a single client
connection per authenticated page, and bounded local broadcast handling.

## Architecture

The Ratchet process binds only to loopback addresses. Apache terminates TLS and
proxies a public WebSocket path such as `/ws` to the local Ratchet port. Public
clients use the configured path on the current application origin, so HTTPS
pages always connect with `wss` without exposing a separate public port.

Runtime configuration supplies the public path, the local WebSocket bind host
and port, the local broadcast bind host and port, and fallback refresh timing.
Development defaults remain usable without Apache proxy configuration.

## Client Flow

One shared public realtime client connects after the authenticated header menu
initializes. It refreshes the message or notification menu from its existing
authenticated API when it receives the matching event. It exposes an event
subscription API so the messages page can consume the same message events and
does not open another connection.

If the connection is unavailable, bounded reconnect backoff and visible-page
HTTP refresh preserve functional unread badges. Socket event payloads never
contain authoritative badge counts.

## Server Flow

Notification dispatch publishes a local event only after a notification has
been persisted. Message and notification publishers use one shared local
broadcast client. The internal broadcast listener accepts only loopback
connections, validates `Content-Length`, limits request size, and closes
incomplete requests after a short timeout.

## Operations

A systemd unit runs the Ratchet process with restart-on-failure behavior and
the application deployment guide documents Apache proxy directives, service
enablement, status checks, and post-deploy verification. The proxy and service
are opt-in operational configuration: they do not overwrite existing Apache
virtual host files during deployment.

## Verification

- PHP lint all changed server files and the repository lint script.
- Parse changed JavaScript with Node.
- Run the local WebSocket process and verify its loopback broadcast endpoint.
- Verify the shared client reacts to message and notification events through a
  browser session where an authenticated test user is available.
- Validate the Apache configuration with `apachectl -t` on production before
  enabling the virtual host changes.
