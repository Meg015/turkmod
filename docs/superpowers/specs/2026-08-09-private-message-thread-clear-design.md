# Private Message Thread Clear Design

## Goal

Allow an authenticated user to permanently remove a private-message conversation from their own public message list without changing or deleting the other participant's history. If either participant sends a later message, the conversation must reappear for the clearing user with only post-clear messages visible.

## User-Visible Semantics

- The action applies only to the user who confirms it.
- The cleared conversation disappears from that user's conversation list, active panel, unread totals, dropdown data, and message-history APIs.
- The clearing user cannot recover or access pre-clear messages through an old thread URL or history request.
- The other participant retains the complete conversation and is not notified that it was cleared.
- A later incoming or outgoing message makes the conversation visible again, but pre-clear messages never return for the clearing user.
- Because the conversation is shared, pre-clear database rows remain solely to preserve the other participant's history. “Permanent” means permanently inaccessible to the clearing user, not physical deletion of the other user's copy.

## Data Model

Add `cleared_through_message_id` to `message_thread_participants` through a Messages module migration and schema installer support:

- integer/bigint, non-null, default `0`;
- scoped naturally by the existing unique `(thread_id, user_id)` participant row;
- no foreign key, because it is an immutable visibility boundary rather than ownership of a message row.

Clearing a conversation records the thread's current maximum message ID in the requesting user's participant row. A value of `0` also hides a thread with no messages. Messages are visible to that participant only when their ID is greater than the stored boundary.

## Service and Query Rules

`MessageService` owns the operation and all visibility enforcement.

The clear method will:

1. validate positive user and thread IDs and schema readiness;
2. verify that the requesting user is a participant;
3. start a transaction when it does not already own one;
4. read the thread's current maximum message ID;
5. update only the requester's `cleared_through_message_id`, read cursor, read timestamp, and typing state;
6. commit and broadcast a user-scoped `thread_cleared` event for cross-tab cleanup;
7. return the refreshed unread count through the API response.

The following read paths must apply the same participant boundary:

- thread list and dropdown list;
- direct thread lookup/opening;
- initial message history and older-history pagination;
- unread count and mark-all-read calculations.

A thread is visible only when it contains at least one message with an ID greater than the current user's boundary. Existing canonical thread creation remains unchanged. Once a new message is inserted into a cleared thread, its ID exceeds the boundary and normal list/open/toast behavior resumes automatically.

## API and Security

Add a CSRF-protected POST action named `delete_thread` to `api/messages.php`. It accepts `thread_id`, delegates authorization and persistence to `MessageService`, and returns a normal JSON success or validation error response.

Security requirements:

- authentication and CSRF validation remain mandatory;
- membership is checked server-side and cannot be inferred from the UI;
- arbitrary thread IDs must not reveal whether another user's conversation exists;
- SQL changes use prepared statements and a transaction;
- no endpoint may bypass the visibility boundary after a successful clear.

## Public Interface

Each conversation entry becomes a container with:

- the existing conversation link as the primary interactive area;
- a sibling trash button with an accessible label containing the peer name;
- a destructive confirmation dialog explaining that the action is irreversible for the current user and does not delete the other user's copy.

On success, JavaScript removes the conversation row and updates unread UI. If the cleared conversation is active, the page returns to the empty `/mesajlar` state. A `thread_cleared` realtime event performs the same cleanup in the clearing user's other open tabs. The other participant ignores the event and sees no UI change.

The button will be keyboard accessible, will not trigger conversation navigation, and will expose a busy/disabled state while the request is pending. Failure leaves the row intact and shows the server error.

## Styling

Message-page CSS will preserve the current row layout while adding a compact destructive action that becomes clearly visible on hover and keyboard focus. Touch devices receive a persistent, sufficiently sized target. Active, unread, responsive, light, and dark states must remain legible.

## Verification

An isolated SQLite service test will cover:

- only a participant can clear a thread;
- clearing for user A does not alter user B's list or history;
- user A's list, direct open, history, dropdown, and unread count exclude the cleared content;
- an old thread URL cannot restore pre-clear messages;
- a later message makes the thread visible to user A with only new messages;
- repeated clears are safe and advance the boundary;
- clearing an empty thread is safe;
- transaction failure does not leave a partial participant state.

Frontend verification will cover confirmation, cancel, success removal, active-thread redirect, failure recovery, keyboard behavior, and realtime cross-tab cleanup. PHP lint, JavaScript syntax checks, migration verification, `git diff --check`, and the full asset build must pass.

## Non-Goals

- Physically deleting shared message rows or the thread record.
- Deleting the other participant's copy.
- Adding recovery, trash, archive, bulk selection, or administrator deletion tools.
- Changing the existing 15-minute single-message edit/delete behavior.
