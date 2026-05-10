<#
.SYNOPSIS
  Sync .cursor/rules/*.mdc between saas-platform and customer-site.

.DESCRIPTION
  Peer path resolution order (first match wins):
  1) Environment: CURSOR_RULES_CUSTOMER_SITE_ROOT (from saas -> customer) or use local JSON
  2) File: <saas-root>/cursor-rules-sync.local.json key "customerSiteRoot"
  3) Default: sibling folder ../customer-site next to saas-platform (local dev)

  Works when customer-site lives on another drive, NAS, or a different clone path.
  For fully separate machines, copy rules via deploy pipeline or scp; this script still
  runs where both paths are visible (e.g. your laptop with two clones).

.PARAMETER Reverse
  Copy customer-site -> saas-platform.

.PARAMETER DeleteOrphans
  Remove .mdc files in destination that do not exist in source.

.NOTES
  Default: saas-platform -> customer-site (canonical).
#>
param(
    [switch]$Reverse,
    [switch]$DeleteOrphans
)

$ErrorActionPreference = 'Stop'

function Get-EnvPath([string]$name) {
    foreach ($scope in @('Process', 'User', 'Machine')) {
        $v = [Environment]::GetEnvironmentVariable($name, $scope)
        if ($v -and $v.Trim()) { return $v.Trim() }
    }
    return $null
}

function Read-JsonPath([string]$file, [string]$property) {
    if (-not (Test-Path -LiteralPath $file)) { return $null }
    try {
        $j = Get-Content -LiteralPath $file -Raw -Encoding UTF8 | ConvertFrom-Json
        $val = $j.$property
        if ($val -and $val.ToString().Trim()) { return $val.ToString().Trim() }
    } catch {
        Write-Warning "Invalid JSON: $file - $_"
    }
    return $null
}

function Resolve-CustomerSiteRoot([string]$saasRoot) {
    $p = Get-EnvPath 'CURSOR_RULES_CUSTOMER_SITE_ROOT'
    if ($p) { return $p }
    $cfg = Join-Path $saasRoot 'cursor-rules-sync.local.json'
    $p = Read-JsonPath $cfg 'customerSiteRoot'
    if ($p) { return $p }
    $parentDir = Split-Path -Parent $saasRoot
    return Join-Path $parentDir 'customer-site'
}

$saasRoot = Split-Path -Parent $PSScriptRoot
$customerRoot = Resolve-CustomerSiteRoot $saasRoot

if (-not (Test-Path -LiteralPath $customerRoot)) {
    $cfgPath = Join-Path $saasRoot 'cursor-rules-sync.local.json'
    throw (
        "Peer repo not found: $customerRoot`n`n" +
        "Set CURSOR_RULES_CUSTOMER_SITE_ROOT (env) or create $cfgPath with customerSiteRoot.`n" +
        'See docs/CURSOR-RULES-SYNC.md'
    )
}

$saasRoot = (Resolve-Path -LiteralPath $saasRoot).Path
$customerRoot = (Resolve-Path -LiteralPath $customerRoot).Path

if ($Reverse) {
    $src = Join-Path $customerRoot '.cursor\rules'
    $dst = Join-Path $saasRoot '.cursor\rules'
    $label = 'customer-site -> saas-platform'
} else {
    $src = Join-Path $saasRoot '.cursor\rules'
    $dst = Join-Path $customerRoot '.cursor\rules'
    $label = 'saas-platform -> customer-site'
}

if (-not (Test-Path -LiteralPath $src)) {
    throw "Source missing: $src"
}

New-Item -ItemType Directory -Force -Path $dst | Out-Null

$files = @(Get-ChildItem -LiteralPath $src -Filter '*.mdc' -File)
foreach ($f in $files) {
    Copy-Item -LiteralPath $f.FullName -Destination (Join-Path $dst $f.Name) -Force
}

if ($DeleteOrphans) {
    $names = @{}
    foreach ($f in $files) { $names[$f.Name] = $true }
    Get-ChildItem -LiteralPath $dst -Filter '*.mdc' -File -ErrorAction SilentlyContinue | ForEach-Object {
        if (-not $names.ContainsKey($_.Name)) {
            Remove-Item -LiteralPath $_.FullName -Force
            Write-Host "Removed orphan: $($_.Name)"
        }
    }
}

Write-Host ('Cursor rules synced ({0}): {1} .mdc file(s).' -f $label, $files.Count)
