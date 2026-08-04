# Admin User Detail Modal

## Scope

Use the existing User Management detail modal on both `admin/users.php` and
`admin/comments-manager.php`. A comment author trigger must open the same
modal, request the same user-details endpoint, and expose the same user
information and moderation actions.

## Design

- Move the reusable modal markup from the Users page to a small PHP partial.
- Load the existing `users-tab.js` detail-modal controller on the Comments
  Manager page, without initializing the unrelated Users table behaviors.
- Change comment author triggers to the common `data-user-detail-open` and
  `data-user-id` contract.
- Remove the Comments Manager-specific detail modal markup, rendering logic,
  handlers, and styles.
- Preserve the comments page's ban, unban, restriction, and edit-comment
  controls; the shared modal delegates its moderation actions to the existing
  page controls when they are present.

## Verification

- PHP lint changed PHP files and syntax-check changed JavaScript.
- Open the modal from both pages and verify loading, failure, backdrop/Escape
  close, and available action buttons.
