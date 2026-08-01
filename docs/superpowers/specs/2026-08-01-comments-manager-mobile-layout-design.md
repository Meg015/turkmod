# Comments Manager Mobile Layout Design

## Goal

Make the Comment Management page and user detail modal efficient and readable on phones without removing moderation information or capabilities.

## Scope

The change applies at the existing mobile breakpoint. Desktop layout, backend queries, permissions, moderation endpoints, modal data, pagination, and tab behavior remain unchanged.

## User Detail Modal Actions

- The mobile modal footer displays Ban, Restrict, Admin Note, and Full History in a fixed two-column grid.
- Buttons retain icons and full labels, wrap text safely, and remain at least 40px high.
- The footer has no horizontal scrolling.
- The redundant footer Close button is hidden on mobile; the persistent top-right close control remains available.
- Desktop footer behavior remains unchanged.

## Mobile Filters

- The filter surface gains a compact mobile disclosure control labeled Filters.
- Filter fields are collapsed by default on mobile and expand in a single-column layout.
- The current filter state remains visible through the existing selected values and page results; no query behavior changes.
- Desktop filters remain permanently visible.
- The disclosure uses native accessible expanded state and requires no new backend logic.

## Bulk Actions

- The selection control and selected-count indicator remain in a compact first row.
- Bulk action buttons do not consume four full rows when there is no selection.
- When comments are selected, available actions use a two-column grid with complete labels.
- Existing disabled state and JavaScript selection behavior remain authoritative.

## Comment Cards

- Card padding and gaps are reduced on mobile while preserving clear sections.
- The avatar becomes smaller and does not create unused vertical space.
- Author identity and moderation status share the available header row and wrap safely.
- Date and status metadata remain readable in a compact secondary row.
- Topic links and comment text wrap without horizontal overflow.
- Card actions form a compact single row and preserve touch-safe target sizes.
- Nested replies use a smaller mobile indentation so content width remains usable.

## Accessibility

All labels, actions, roles, and focus behavior remain available. Touch targets remain at least 40px high. The filter disclosure reports its expanded state. Text wraps instead of clipping, and no required action depends on horizontal scrolling.

## Verification

- Verify the page and user detail modal at 360x800 and 390x844.
- Confirm the modal footer shows all four actions in a 2x2 grid.
- Confirm the footer Close button is hidden only on mobile and the header close control works.
- Confirm filters expand and collapse without losing current values.
- Confirm bulk actions remain compact with zero selections and usable with selected comments.
- Confirm comment headers, topic links, bodies, nested replies, and card actions have no horizontal overflow.
- Confirm desktop layout and existing moderation behavior remain unchanged.
