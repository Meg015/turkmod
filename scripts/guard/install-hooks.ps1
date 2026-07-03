param()

$ErrorActionPreference = "Stop"

$repoRoot = (git rev-parse --show-toplevel 2>$null).Trim()
if ([string]::IsNullOrWhiteSpace($repoRoot)) {
    throw "Git repo root bulunamadi."
}

Set-Location -LiteralPath $repoRoot

$hooksDir = Join-Path $repoRoot ".githooks"
if (-not (Test-Path -LiteralPath $hooksDir)) {
    throw ".githooks klasoru bulunamadi: $hooksDir"
}

git config core.hooksPath .githooks | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "core.hooksPath ayarlanamadi."
}

Write-Output "OK: Git hooks aktif edildi (.githooks)."
Write-Output "Pre-commit migration autogen + guard aktif."
Write-Output "Pre-push migration guard aktif."
Write-Output "Elle kontrol icin: php scripts/guard/migration_guard.php --cached"
