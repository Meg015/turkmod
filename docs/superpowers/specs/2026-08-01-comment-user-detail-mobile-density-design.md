# Comment User Detail Mobile Density Design

## Goal

Reduce the visual density and vertical space consumption of the Comment Management user detail modal on phones without removing information or actions.

## Scope

The change applies only below the existing 640px mobile breakpoint. Desktop layout, modal data, tab behavior, pagination, and action behavior remain unchanged.

## Mobile Layout

- The overlay uses a small consistent viewport gutter while the modal uses the available height efficiently.
- Header, scrollable shell, and footer padding are reduced proportionally.
- The user profile remains horizontal instead of stacking vertically.
- The avatar becomes smaller and the identity column is allowed to shrink and wrap safely.
- Username, status, registration metadata, last-login metadata, and email remain visible without horizontal overflow.
- The four statistics form one horizontally scrollable row instead of a two-row grid.
- Each statistic has a stable compact width so content does not resize the strip.
- The segmented tab strip keeps horizontal scrolling but uses reduced mobile padding and height.
- History rows use tighter spacing while retaining readable text and touch-safe links.
- Pagination remains centered and touchable.

## Action Bar

The footer remains visible and becomes a single horizontally scrollable row. Action buttons retain their icons and labels, use content-based widths, and do not stack into a tall column.

## Accessibility

No text, actions, roles, or keyboard behavior are removed. Touch targets remain at least 40px high. Horizontal strips remain usable with touch scrolling, and text wraps rather than clipping or overflowing.

## Verification

- Verify at 390x844 and 360x800 viewports.
- Confirm the profile stays horizontal and all user metadata remains readable.
- Confirm statistics, tabs, and footer actions scroll horizontally without wrapping.
- Confirm the modal has no page-level horizontal overflow.
- Confirm list content receives materially more visible vertical space.
- Confirm desktop appearance and interactions remain unchanged.
