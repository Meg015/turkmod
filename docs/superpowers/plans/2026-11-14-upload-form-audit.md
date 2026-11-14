# Mod Upload and Edit Form Audit Implementation Plan

**Goal:** Fix reproducible defects in the existing upload and edit forms, preserving the approved layout and single-notification behavior.

**Architecture:** Keep the existing theme and standalone form ownership guards. Validate steps in order, reveal the first failing step, and guard asynchronous navigation and submission before starting validation.

**Tech Stack:** PHP templates, vanilla JavaScript, esbuild, Playwright CLI.

**Spec:** The user's request to find and fix remaining issues in the existing mod upload and edit workflow.

## Global Constraints

- Preserve existing uncommitted changes and the compact progress bar.
- Keep validation warnings to one notification per attempted action.
- Use intercepted submissions in browser fixtures; do not change real topics.
- Keep required downloads as an at-least-one rule; ignore empty optional rows and reject invalid nonempty URLs.
- Honor retained cover/gallery images when editing.

## Review Focus

- Forward jumps must validate intermediate steps when skipping is disabled.
- Submission from the review step must reveal and focus the first invalid step.
- Concurrent validation must not advance twice or send duplicate requests.
- A retained cover/gallery image must satisfy required-media settings.
- Failed requests and successful unlocked submissions must leave the correct retry state.

### Task 1: Reproduce edge cases

**Files:** `tests/fixtures/upload-form-edge-ui.js`, existing rendered form fixtures.

- [x] Add browser cases for forward jumps, hidden required fields, retained media, download rows, and concurrent requests.
- [x] Run the cases on theme and standalone forms to establish failures.

### Task 2: Fix validation and concurrency

**Files:** `themes/turkmod/js/bundle.js`, `assets/js/ui.js`, `assets/js/upload-topic-form.js`, `assets/js/edit-topic-form.js`, `includes/src/Modules/TopicWorkflow/Http/edit-topic-page-content.php`.

- [x] Correct grouped download validation and required-media checks.
- [x] Validate forward navigation and final submission in step order, revealing the failed step.
- [x] Guard pending navigation/submission and release submission state appropriately.
- [x] Exclude the wizard from generic form validation/loading handlers, preserve gallery files on reordering, and correct the failed-edit redirect scope.
- [x] Run the edge-case fixture and existing validation/controller/toast fixtures.

### Task 3: Build and finish verification

**Files:** Generated JavaScript bundles, this plan.

- [x] Run `npm run build` and JavaScript syntax/whitespace checks.
- [x] Check the generated bundle against the same browser scenarios.
- [x] Close the test browser and report concrete fixes and verification limits.

## Verification

- Edge cases: 80 checks, no failures or JavaScript errors across upload/edit, theme source, generated theme bundle, and standalone controllers.
- Existing validation fixture: 59 checks passed. Updated the final-error expectation to the earliest invalid step.
- Existing toast fixture: 29 checks passed.
- Existing controller fixture: 64 checks passed.
- `npm run build`, JavaScript syntax checks, PHP syntax check for the edit handler, and `git -c core.whitespace=cr-at-eol diff --check` passed.
- Submissions were intercepted in browser fixtures. Live topic persistence was not exercised; the PHP redirect fix was inspected and syntax checked.

## Server validation follow-up

The server review found that the themed form posts `topic_download_links` while creation reads only `download_lines`. Editing omits the configured video-provider check, and upload transport errors can count as selected images before the service skips them. Share request normalization, HTTP(S) URL checks, description text and selected-file validation between upload/edit in a pure `TopicFormRules` class. Accept both legacy hidden-field names and use `download_lines` in the template.

- [x] Add PHP regression cases using rendered forms, legacy payloads, invalid URLs, invisible description text, image dimensions and upload failures.
- [x] Wire shared rules into create/edit before persistence and normalize sparse gallery inputs.
- [x] Use the canonical hidden download field in the template and browser fixtures.
- [x] Run PHP regression cases, relevant browser fixtures, build and syntax checks; state the remaining live-persistence verification limit.

Follow-up verification: `php tests/topic-form-rules-regression.php` passed 73 checks, including 20 cases executing the real create/edit POST validation blocks up to the persistence boundary. Regenerated the actual template fixtures; browser validation passed 59 checks and controller integration passed 64 checks. Build, PHP syntax and whitespace checks passed. Existing topics were not modified. A real account's database save, moderation approval and subsequent edit remain outside this verification.
