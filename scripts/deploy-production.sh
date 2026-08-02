#!/usr/bin/env bash

set -Eeuo pipefail

remote="${DEPLOY_REMOTE:-origin}"
branch="${DEPLOY_BRANCH:-master}"
dry_run=0

if [[ "${1:-}" == "--dry-run" ]]; then
    dry_run=1
elif [[ $# -gt 0 ]]; then
    echo "Usage: $0 [--dry-run]" >&2
    exit 64
fi

repo_root="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "$repo_root" ]]; then
    echo "[deploy] Git repository root not found." >&2
    exit 1
fi
cd "$repo_root"

current_branch="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
if [[ "$current_branch" != "$branch" ]]; then
    echo "[deploy] Expected branch '$branch', found '${current_branch:-detached HEAD}'." >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "[deploy] Tracked production files contain local changes; deployment stopped." >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

echo "[deploy] Fetching $remote/$branch..."
git fetch --prune "$remote" "$branch"
target_ref="$remote/$branch"
git rev-parse --verify "$target_ref" >/dev/null

if ! git merge-base --is-ancestor HEAD "$target_ref"; then
    echo "[deploy] $target_ref is not a fast-forward of the current deployment." >&2
    exit 1
fi

clean_excludes=(
    -e '.env'
    -e '.env.*'
    -e 'uploads/'
    -e 'storage/'
    -e 'vendor/'
    -e 'includes/storage/'
)

echo "[deploy] Incoming tracked changes:"
git diff --name-status HEAD.."$target_ref" || true

echo "[deploy] Untracked, non-ignored residue selected for cleanup:"
git clean -nd "${clean_excludes[@]}"

if [[ $dry_run -eq 1 ]]; then
    echo "[deploy] Dry run completed; no working-tree changes were made."
    exit 0
fi

git merge --ff-only "$target_ref"

# Deleted tracked files are removed by the merge. This removes only additional
# untracked, non-ignored residue while preserving all production data paths.
git clean -fd "${clean_excludes[@]}"

if ! command -v composer >/dev/null 2>&1; then
    echo "[deploy] Composer is required but was not found in PATH." >&2
    exit 1
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php tools/lint.php

echo "[deploy] Production deployment completed at $(git rev-parse --short HEAD)."
