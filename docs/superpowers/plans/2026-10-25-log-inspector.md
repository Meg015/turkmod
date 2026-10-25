# Günlükler: Bölünmüş İnceleme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the approved fourth mockup to all seven admin log screens with an explicitly selected record opening a right detail panel.

**Architecture:** A shared, progressively enhanced inspector consumes the existing server-rendered log tables and activity feed. Original controls and detail nodes move into the inspector, preserving form ownership, permissions, CSRF fields and all real data. A dedicated stylesheet owns the shared navigation, summary, filter and inspector layout.

**Tech Stack:** PHP 8.2, native JavaScript, CSS, existing admin components.

**Spec:** User-approved mockup 4 in `C:/Users/Mehmet/.codex/visualizations/2026/10/25/01a1721e-73bd-7fd1-915c-c7fd981b2f14/logs-design-directions.html` and `logs-layout-review.md`.

## Global Constraints

- Detail initially closed; only a deliberate record click opens it. Closing restores full list width.
- Cover administrator, user, application, email, cron, notifications and rate-limit logs.
- Preserve existing backend queries, filters, permissions and destructive-action endpoints.
- Keep the central clear dialog, “Tümünü sil”, and silent successful cleanup behavior.
- No production record deletion during verification.

## Review Focus

- Long unbroken messages, identifiers and JSON wrap inside their own area.
- Narrow screens retain readable fields and accessible filter controls.
- Bulk checkboxes remain inside their original form and retain selection.
- Empty lists, changing record selection and closing details work without phantom panels.
- Rich email, notification and audit details retain their full data and working actions.

### Task 1: Shared layout and inspector

**Files:** Create `admin/assets/log-inspector.js`, `admin/assets/log-inspector.css`; modify `admin/header.php`, `admin/helpers.php`.

**Interfaces:** `adminRenderLogTableOpen(array $options)` emits `data-log-kind` from `kind` (`activity`, `application`, `email`, `cron`, `system_notifications`, `rate_limits`). Consume existing labeled table cells and activity detail fields. Produce `.log-inspector`, `[data-log-select]`, `[data-log-detail]`, `[data-log-close]`, native filter forms and a mobile log-page selector.

- [x] Implement compact navigation, four primary summary cards and supplementary summary text.
- [x] Enhance filter forms with a search, primary filter and collapsible extra fields; retain all original nodes and submitted names.
- [x] Enhance existing records into selectable rows and a closed-by-default detail area; preserve original controls without cloning forms or IDs.
- [x] Verify 1440, 1024, 390 and 320 px layouts on all seven routes; verify selection, switching, Escape and close, long text and bulk form ownership.

### Task 2: Integration and verification

**Files:** Existing seven log renderers, as needed; `docs/` verification results.

- [x] Verify user activity uses the same inspector while supplemental user information stays accessible below the list.
- [x] Run PHP lint, JavaScript/CSS syntax checks and `npm run build`.
- [x] Check real records, native filters and pagination, empty states, both themes and unchanged cleanup dialogs without submitting destructive actions.
- [x] Save review screenshots and report completed behavior and verification limits.

### Task 3: Admin tasarımıyla uyum revizyonu

User direction: use the existing burgundy admin heading and the same font, control and button scales; keep a single record list on the standalone user log.

**Files:** `admin/assets/log-inspector.css`, `admin/assets/log-inspector.js`, `admin/assets/admin.css`, `admin/action-log.php`, `admin/users-tabs/activity.php`.

**Interfaces:** Keep `adminRenderPageHero()` / `page-heading.css` authoritative. `$activityShowSupplementaryPanels = false` in `action-log.php` disables supplementary summaries in the shared activity partial; the user management view keeps its existing supplementary information.

- [x] Remove log-specific heading and miniature typography overrides; retain responsive layout and the explicit-click inspector.
- [x] Reuse native admin header actions, badges, buttons and statistics styling.
- [x] Do not render or query supplementary panels on the standalone user log.
- [x] Compare native admin and log headings/control typography; verify seven routes at desktop and mobile sizes, single list, detail interaction, PHP lint and build.
