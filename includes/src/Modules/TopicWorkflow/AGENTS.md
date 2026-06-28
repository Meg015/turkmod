# TopicWorkflow Module Notes

This folder is the module-owned boundary for `App\Modules\TopicWorkflow`.
Public upload/edit/download flows, service orchestration, and
TopicWorkflow-owned event/listener/job behavior must live here.
Legacy root entrypoints stay as thin compatibility wrappers only.

## Ownership Boundary

- `Http/CreateTopicPage.php` + `Http/create-topic-page-content.php` own upload flow.
- `Http/EditTopicPage.php` + `Http/edit-topic-page-content.php` own edit flow.
- `Http/DownloadAction.php` + `Http/download-action-content.php` own download confirmation/redirect flow.
- `Services/TopicSubmissionService.php` and `Services/TopicEditService.php` own write orchestration.
- `Events/TopicWorkflowEvent.php` owns module event payload shape.
- `Listeners/` + `Jobs/SearchIndexJob.php` own TopicWorkflow event reactions and queue jobs.
- `routes.php` is the module route source.
- `module.php` is the single metadata source for event wiring and module metadata.

## Do Not Re-Introduce

- Do not move business logic back into:
  - `upload-topic.php`
  - `edit-topic.php`
  - `download.php`
- Do not add new business logic to legacy helpers under `includes/modules/topics/*`
  or `includes/modules/media/*`.
- Do not duplicate create/edit orchestration across wrapper, HTTP content, and service layers.

## Security and Behavior Contracts

- Keep CSRF checks on create/edit POST flows.
- Preserve upload MIME/validation checks (`UploadSecurity` and current file validation contract).
- Keep AJAX response contract:
  - `Content-Type: application/json; charset=utf-8`
  - payload keys `success` and `message`
- Keep compatibility form actions:
  - `/upload-topic.php`
  - `/edit-topic.php?id=...`
- Keep download safe-URL and confirmation behavior unchanged.

## Events and Queue Contracts

- `TopicSubmissionService` emits:
  - `topic.created` (always after successful create commit)
  - `topic.published` (only for published/approved)
- `TopicEditService` emits:
  - `topic.updated` (after successful edit commit)
  - `topic.published` (when edited topic is published/approved)
- `module.php` event map is canonical:
  - `topic.created` -> activity/audit/leaderboard bridge listeners
  - `topic.updated` -> edit audit listener
  - `topic.published` -> `TopicPublishedSearchIndexer`
- `TopicPublishedSearchIndexer` enqueues `SearchIndexJob` only; indexing happens in the job.
- Listener/job failures must be logged (`appLogException` or `error_log`), not silently swallowed.

## Migration Rules

- New module classes use `App\Modules\TopicWorkflow\*` namespace with PSR-4 paths.
- New Topic domain adapters belong under `App\Engine\Topics\*` and `App\Engine\Media\*`.
- Route/metadata changes go through `routes.php` and `module.php`, not ad hoc in wrappers.
- Future TopicWorkflow migrations may touch only TopicWorkflow-owned schema/data behavior.

## Validation Checklist

- `php -l` for each touched PHP file.
- Targeted PHPUnit:
  - `tests/Unit/Modules/TopicWorkflow/Http/*`
  - `tests/Unit/Modules/TopicWorkflow/Listeners/*`
  - `tests/Unit/Modules/TopicWorkflow/Jobs/*`
  - `tests/Unit/Modules/TopicWorkflow/Services/TopicSubmissionServiceTest.php`
  - `tests/Compatibility/TopicWorkflowCompatibilityTest.php`
- Full `vendor/bin/phpunit`
- Auth header checks for login/register/forgot/reset/upload/edit routes.
- `powershell -ExecutionPolicy Bypass -File scripts/ui-smoke-check.ps1`
- `git diff --check`

