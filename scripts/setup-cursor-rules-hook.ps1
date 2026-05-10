$ErrorActionPreference = 'Stop'
$repoRoot = (git rev-parse --show-toplevel).Trim()
if (-not $repoRoot) { throw 'Run inside a git repository.' }
Set-Location $repoRoot
git config core.hooksPath .githooks
Write-Host "OK: core.hooksPath=.githooks for $repoRoot"
