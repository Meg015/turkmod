# Repository Cleanup and Deploy Design

## Goal

Remove confirmed-unused repository files and disposable local artifacts without touching user uploads, secrets, database state, or unrelated work in progress. Ensure a production Git deployment removes tracked files deleted by later commits and safely clears non-ignored untracked application residue.

## Safety Boundaries

- Never delete `uploads/**` or inspect image names as a reason to delete uploaded content.
- Preserve `.env`, database contents, and persistent production runtime data.
- Preserve all existing unstaged and untracked user source changes.
- Do not use `git clean -x` or `git clean -X`; both can remove ignored secrets, uploads, dependencies, or runtime data.
- Treat dynamic PHP autoloading, routes, template lookup, theme manifests, and minified-asset selection as valid references.
- Do not delete a tracked source or asset solely because a filename search has no result.

## Cleanup Scope

### Disposable Local Artifacts

Remove locally installed dependencies and reproducible output:

- `vendor/`
- `node_modules/`
- `storage/cache/*`, `storage/logs/*`, and `storage/sessions/*`, preserving tracked placeholders
- `.playwright-cli/` and `output/`
- copied production logs under `canlı/`
- obsolete brainstorming screenshots/state under `.codex/brainstorm/` and `.superpowers/brainstorm/`
- empty, untracked directories that have no project or tooling role

Local editor/tool configuration such as `.claude/launch.json` and `.claude/settings.local.json` is not disposable and remains in place.

### Tracked Repository Files

Classify a tracked file as removable only when all applicable checks pass:

1. No direct include, import, route, template, manifest, CSS URL, or HTML reference exists.
2. No dynamic naming convention or autoload rule can resolve the file.
3. The build script neither consumes nor produces the file as a required deploy artifact.
4. The web server does not expose it as an intentional entry point.
5. Git history and paired source/minified files do not show a current compatibility purpose.
6. Build, PHP lint, and relevant tests pass after removal.

Ambiguous candidates remain in the repository and are reported rather than deleted.

## Production Deployment

Add a Linux deployment script under `scripts/` and make the production instructions call it. The script will:

1. Verify it is running inside the expected Git working tree.
2. Refuse to proceed when tracked production files contain local modifications.
3. Fetch `origin/master` and update with a fast-forward-only merge.
4. Let Git remove every tracked path deleted by the incoming commit.
5. Preview and then remove only untracked, non-ignored residue with explicit exclusions for `.env`, `uploads/`, `storage/`, and `vendor/`.
6. Refresh optimized production Composer dependencies without development packages.
7. Run a PHP syntax smoke check.

The deployment script must never use an ignored-file cleanup mode. Consequently, uploaded images and protected runtime paths remain untouched even if they contain files unknown to Git.

## Verification

- Record disk usage and candidate counts before and after local cleanup.
- Run the asset build before deleting local Node dependencies.
- Run the project PHP linter and focused/full PHPUnit suite after Composer dependencies are available.
- Run shell syntax validation and a dry-run mode for the deployment script.
- Verify `git status` contains only the user's pre-existing changes plus intentional cleanup/deploy changes.
- Verify representative files under `uploads/` still exist and the upload file count is unchanged.

## Deliverables

- Confirmed-unused tracked files removed from Git.
- Disposable local artifacts removed from disk.
- A guarded production deploy script and updated deployment instructions.
- A summary of deleted paths, recovered space, preserved protected data, and verification results.
