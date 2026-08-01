# Comment User Detail Tabs Design

## Goal

Replace the cramped tab strip in the Comment Management user detail modal with a calmer segmented control that gives labels and icons adequate vertical and horizontal space.

## Scope

Only the tab strip and its spacing relative to the tab panels change. Modal markup, tab behavior, panel content, pagination, and data loading remain unchanged.

## Visual Design

- The tab list becomes a single softly contrasted surface with a subtle border and an 8px corner radius.
- The container receives balanced internal padding instead of relying on a heavy full-width bottom rule.
- Every tab keeps a stable minimum height and increased horizontal padding.
- Inactive tabs remain quiet and readable without individual borders.
- The active tab uses the main surface color, accent-colored text, a subtle border, and a restrained shadow.
- Hover and keyboard-focus states remain clear without changing layout dimensions.
- The content panels begin with increased top spacing so the tab control does not feel attached to the first list row.

## Responsive Behavior

The tab list remains a single horizontal row and scrolls when it exceeds the available width. Tabs do not shrink or wrap, preserving readable labels and consistent height on mobile.

## Accessibility

Existing tab roles, selection attributes, and keyboard navigation remain unchanged. Focus-visible styling provides a clear outline while maintaining sufficient contrast for active and inactive states.

## Verification

- Confirm the strip has balanced space above and below every label.
- Confirm active, hover, and focus states do not resize the bar.
- Confirm all six tabs remain usable through horizontal scrolling on narrow screens.
- Confirm the panel content has clear separation from the strip.
- Confirm pagination and tab switching behavior are unaffected.
