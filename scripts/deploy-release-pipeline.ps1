<#
.SYNOPSIS
    Safe wrapper around the official release contract (release-prepare) for
    this repository. Identical copy in saas-platform and customer-site.

.DESCRIPTION
    This script never stages, commits or pushes anything itself, and never
    reports test results it did not run. Release content, the release commit,
    the push and the HMAC-SHA256 signed metadata delivery to the Deployment
    Hub are all performed by
    ../deployment-system/deployment/release-prepare.php, the only sanctioned
    path (docs/quality/WORK_UNIT_DELIVERY_WORKFLOW.md, P5/P6). Run it from an
    isolated release worktree of `main`, never from a shared development tree.

    Modes:
      check    (default) Fail-fast preflight, then `release-prepare check`.
               Side-effect free apart from `git fetch`.
      prepare  Same preflight, then `release-prepare prepare`, which runs the
               policy's real checks, creates the single
               `release(<project>): <task_id>` commit from the task-owned paths
               only, pushes it and delivers the signed metadata to the Hub.
      health   Read-only HTTP probes of -HealthUrls. Deployment itself is an
               owner action in the Hub and is never triggered from here.

    Preflight (fails fast, exit 1, before any side effect):
      - the current branch is the policy's production branch (no checkout);
      - no merge or rebase is in progress and nothing is pre-staged;
      - after `git fetch`, HEAD equals the remote production HEAD
        (no unpushed, behind or diverged state);
      - an active task file (.deployment/release-task.json) exists, or is
        created here from -TaskId/-Summary/-OwnedPaths. It is never
        overwritten;
      - the task's base_sha equals the remote production HEAD, i.e. the last
        released state, not HEAD~1. Commits that reached the branch without a
        `release(<project>):` commit are listed as a warning, because they ship
        in the artifact but are absent from changed_files;
      - every uncommitted path is covered by the task's owned_paths
        (or is untracked runtime data excluded by the policy). Any other
        uncommitted change aborts the run: nothing is ever swept into a release.

.EXAMPLE
    .\scripts\deploy-release-pipeline.ps1 -TaskId WU-X -Summary "..." -OwnedPaths "app/Foo.php","tests/Feature/FooTest.php"
    .\scripts\deploy-release-pipeline.ps1 -Mode prepare
    .\scripts\deploy-release-pipeline.ps1 -Mode health
#>

[CmdletBinding()]
param (
    [ValidateSet("check", "prepare", "health")]
    [string]$Mode = "check",

    [string]$TaskId,
    [string]$Summary,
    [string[]]$OwnedPaths,

    [string]$HubBaseUrl = "https://deployment.safhenokhost.ir",

    [string[]]$HealthUrls = @(
        "https://panel.safhenokhost.ir/login",
        "https://safhenokhost.ir/",
        "https://deployment.safhenokhost.ir/login"
    ),

    [string]$DeploymentSystemPath,
    [string]$AgentsPath,
    [string]$SecretsEnvPath,
    [string]$PhpPath,

    [switch]$SkipReadiness
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version 2.0

$TaskFile = ".deployment/release-task.json"

function Write-Section([string]$title) {
    Write-Host ""
    Write-Host ("=" * 70) -ForegroundColor Cyan
    Write-Host "  $title" -ForegroundColor Cyan
    Write-Host ("=" * 70) -ForegroundColor Cyan
}
function Write-Step([string]$m) { Write-Host "  --> $m" -ForegroundColor Yellow }
function Write-Ok([string]$m)   { Write-Host "  [OK] $m" -ForegroundColor Green }
function Write-Warn([string]$m) { Write-Host "  [WARN] $m" -ForegroundColor Yellow }
function Stop-Release([string]$m) { throw "RELEASE ABORTED: $m" }

# Runs git in the repository root; returns stdout lines, throws on failure.
function Invoke-Git([string[]]$GitArgs, [switch]$AllowFailure) {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $out = & git -C $script:RepoRoot @GitArgs 2>$null
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }
    if ($code -ne 0 -and -not $AllowFailure) {
        Stop-Release ("git " + ($GitArgs -join " ") + " failed with exit code $code.")
    }
    if ($null -eq $out) { return @() }
    return @($out)
}

# Same glob semantics as ReleasePreparer::matches(): ** any, * and ? within a segment.
function Test-PathMatch([string]$Path, [string[]]$Patterns) {
    $p = $Path.Replace("\", "/")
    foreach ($pattern in $Patterns) {
        $quoted = [regex]::Escape($pattern.Replace("\", "/"))
        $regex = $quoted.Replace("\*\*", ".*").Replace("\*", "[^/]*").Replace("\?", "[^/]")
        if ($p -match ("^" + $regex + "$")) { return $true }
    }
    return $false
}

function Resolve-Php {
    if ($PhpPath) {
        if (-not (Test-Path $PhpPath)) { Stop-Release "PHP not found at $PhpPath." }
        return $PhpPath
    }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $laragon = "C:\laragon\bin\php\php-8.4.16-nts-Win32-vs17-x64\php.exe"
    if (Test-Path $laragon) { return $laragon }
    Stop-Release "PHP executable not found; pass -PhpPath."
}

function Resolve-Sibling([string]$Explicit, [string]$Name) {
    if ($Explicit) {
        if (-not (Test-Path $Explicit)) { Stop-Release "$Name not found at $Explicit." }
        return (Resolve-Path $Explicit).Path
    }
    foreach ($candidate in @((Join-Path (Split-Path $script:RepoRoot -Parent) $Name), (Join-Path "C:\laragon\www" $Name))) {
        if (Test-Path $candidate) { return (Resolve-Path $candidate).Path }
    }
    Stop-Release "$Name checkout not found; pass its path explicitly."
}

# The project's release secret: environment first, then the agents secrets file.
# The value is never printed.
function Get-ReleaseSecret([string]$ProjectKey) {
    if ($ProjectKey -eq "saas-platform") {
        $names = @("DEPLOYMENT_RELEASE_SECRET_SAAS", "DEPLOYMENT_RELEASE_SECRET")
    } else {
        $names = @("DEPLOYMENT_RELEASE_SECRET_CUSTOMER_SITE", "DEPLOYMENT_RELEASE_SECRET_CUSTOMER", "DEPLOYMENT_RELEASE_SECRET")
    }
    foreach ($name in $names) {
        $value = [System.Environment]::GetEnvironmentVariable($name)
        if ($value) { return $value.Trim() }
    }
    $file = $SecretsEnvPath
    if (-not $file) { $file = Join-Path $script:Agents ".secrets.env" }
    if (Test-Path $file) {
        $lines = Get-Content $file
        foreach ($name in $names) {
            foreach ($line in $lines) {
                if ($line -match ("^" + [regex]::Escape($name) + "=(.+)$")) {
                    $value = $Matches[1].Trim().Trim('"')
                    if ($value) { return $value }
                }
            }
        }
    }
    Stop-Release "Release secret for [$ProjectKey] could not be resolved (env or $file)."
}

function Invoke-Preflight {
    Write-Section "Preflight - $($script:ProjectKey)"

    $branch = (Invoke-Git @("branch", "--show-current") | Select-Object -First 1)
    if ($branch -ne $script:Branch) {
        Stop-Release "current branch is '$branch'; run from a '$($script:Branch)' release worktree. This script never switches branches."
    }
    $gitDir = (Invoke-Git @("rev-parse", "--git-dir") | Select-Object -First 1)
    if (-not [System.IO.Path]::IsPathRooted($gitDir)) { $gitDir = Join-Path $script:RepoRoot $gitDir }
    foreach ($marker in @("MERGE_HEAD", "rebase-merge", "rebase-apply", "CHERRY_PICK_HEAD")) {
        if (Test-Path (Join-Path $gitDir $marker)) { Stop-Release "a $marker operation is in progress." }
    }

    Write-Step "Fetching $($script:Remote)/$($script:Branch)"
    Invoke-Git @("fetch", "--no-tags", $script:Remote, $script:Branch) | Out-Null
    $remoteHead = (Invoke-Git @("rev-parse", "FETCH_HEAD") | Select-Object -First 1).Trim().ToLower()
    $localHead = (Invoke-Git @("rev-parse", "HEAD") | Select-Object -First 1).Trim().ToLower()
    if ($localHead -ne $remoteHead) {
        $base = (Invoke-Git @("merge-base", $localHead, $remoteHead) -AllowFailure | Select-Object -First 1)
        if ($base -eq $remoteHead) { $state = "has unpushed commits" }
        elseif ($base -eq $localHead) { $state = "is behind the remote (fast-forward first)" }
        else { $state = "has diverged from the remote" }
        Stop-Release "local $($script:Branch) ($localHead) $state ($remoteHead)."
    }
    Write-Ok "HEAD equals $($script:Remote)/$($script:Branch): $remoteHead"

    # Lineage: the last release commit, and anything that bypassed the contract since.
    $lastRelease = (Invoke-Git @("log", "-1", "--format=%H %s", "-E", "--grep=^release\($($script:ProjectKey)\):", $remoteHead) -AllowFailure | Select-Object -First 1)
    if ($lastRelease) {
        $lastSha = $lastRelease.Split(" ")[0]
        Write-Ok "Last release commit: $lastRelease"
        $bypassed = @(Invoke-Git @("log", "--format=%h %s", "$lastSha..$remoteHead") | Where-Object { $_ -notmatch "^\w+ release\(" })
        if ($bypassed.Count -gt 0) {
            Write-Warn "$($bypassed.Count) commit(s) reached $($script:Branch) after the last release without release-prepare. They ship in the next artifact but are not listed in its changed_files:"
            $bypassed | ForEach-Object { Write-Host "         $_" }
        }
    } else {
        Write-Warn "No release($($script:ProjectKey)): commit found in the history."
    }

    # Status: nothing pre-staged; every change is task-owned (or excluded runtime data).
    $entries = @()
    $raw = (Invoke-Git @("status", "--porcelain=v1", "-z", "--untracked-files=all")) -join "`n"
    $records = $raw.Split([char]0)
    for ($i = 0; $i -lt $records.Length; $i++) {
        $record = $records[$i]
        if ($record.Length -lt 4) { continue }
        $xy = $record.Substring(0, 2)
        $entries += [pscustomobject]@{ XY = $xy; Path = $record.Substring(3).Replace("\", "/") }
        if ($xy[0] -eq "R" -or $xy[0] -eq "C" -or $xy[1] -eq "R" -or $xy[1] -eq "C") { $i++ }
    }
    $staged = @($entries | Where-Object { $_.XY[0] -ne " " -and $_.XY[0] -ne "?" })
    if ($staged.Count -gt 0) {
        Stop-Release ("pre-staged changes in the index: " + (($staged | ForEach-Object { $_.Path }) -join ", "))
    }

    # The task file is written only after every check below passes, so a
    # failed run never leaves a half-made task behind.
    $taskPath = Join-Path $script:RepoRoot $TaskFile
    $newTaskJson = $null
    if (Test-Path $taskPath) {
        if ($TaskId -or $Summary -or $OwnedPaths) {
            Stop-Release "$TaskFile already exists; it is never overwritten. Remove it deliberately or run without -TaskId/-Summary/-OwnedPaths."
        }
        $task = Get-Content $taskPath -Raw | ConvertFrom-Json
    } else {
        if (-not ($TaskId -and $Summary -and $OwnedPaths)) {
            Stop-Release "no $TaskFile. Pass -TaskId, -Summary and -OwnedPaths to create it from the remote production HEAD."
        }
        # `powershell -File` passes "a,b" as one string: split on commas.
        $paths = @($OwnedPaths | ForEach-Object { $_ -split "," } | ForEach-Object { $_.Trim().Replace("\", "/") } | Where-Object { $_ })
        $newTaskJson = ([ordered]@{
            task_id     = $TaskId
            summary     = $Summary
            base_sha    = $remoteHead
            owned_paths = $paths
        } | ConvertTo-Json -Depth 4)
        $task = $newTaskJson | ConvertFrom-Json
    }
    foreach ($field in @("task_id", "summary", "base_sha", "owned_paths")) {
        if (-not ($task.PSObject.Properties.Name -contains $field) -or -not $task.$field) {
            Stop-Release "$TaskFile is missing '$field'."
        }
    }
    if ($task.base_sha.ToLower() -ne $remoteHead) {
        Stop-Release "$TaskFile base_sha $($task.base_sha) is not the remote production HEAD $remoteHead (stale baseline). Recreate the task from the current HEAD."
    }

    $owned = @($task.owned_paths)
    $excluded = @()
    if ($script:Policy.PSObject.Properties.Name -contains "excluded_runtime_paths") { $excluded = @($script:Policy.excluded_runtime_paths) }
    $candidate = @()
    $unowned = @()
    foreach ($entry in $entries) {
        if ($entry.XY -eq "??" -and $entry.Path -eq $TaskFile) { continue }
        if ($excluded.Count -gt 0 -and (Test-PathMatch $entry.Path $excluded)) {
            if ($entry.XY -eq "??") { continue }
            Stop-Release "tracked runtime data is modified: $($entry.Path)"
        }
        if (Test-PathMatch $entry.Path $owned) { $candidate += $entry.Path } else { $unowned += $entry.Path }
    }
    if ($unowned.Count -gt 0) {
        Stop-Release ("uncommitted changes outside the task's owned_paths (nothing is ever swept into a release):`n  " + ($unowned -join "`n  "))
    }
    if ($candidate.Count -eq 0) {
        Stop-Release "no task-owned changes exist on top of $remoteHead."
    }
    if ($newTaskJson) {
        [System.IO.File]::WriteAllText($taskPath, $newTaskJson + "`n", (New-Object System.Text.UTF8Encoding($false)))
        Write-Ok "Created $TaskFile (base_sha $remoteHead)"
    }
    Write-Ok "Task $($task.task_id): $($candidate.Count) owned change(s), 0 unowned"
    $script:Task = $task
}

function Invoke-Readiness {
    if ($SkipReadiness) { Write-Warn "Readiness agent skipped (-SkipReadiness)."; return }
    Write-Section "Readiness agent - deployment.readiness --strict"
    # Windows PowerShell 5.1 strips the double quotes of a JSON argument passed
    # to a native program, so the payload goes through a file.
    $payload = (@{ projects = @($script:ProjectKey); task_id = $script:Task.task_id } | ConvertTo-Json -Compress)
    $payloadFile = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($payloadFile, $payload, (New-Object System.Text.UTF8Encoding($false)))
    Push-Location $script:RepoRoot
    try {
        $prev = $ErrorActionPreference; $ErrorActionPreference = "Continue"
        & $script:Php (Join-Path $script:Agents "bin\agent") run deployment.readiness --strict "--payload-file=$payloadFile"
        $code = $LASTEXITCODE
        $ErrorActionPreference = $prev
    } finally {
        Pop-Location
        Remove-Item $payloadFile -ErrorAction SilentlyContinue
    }
    if ($code -ne 0) { Stop-Release "readiness agent exit code $code (0 PASS, 1 failed, 2 blocked, 3 not READY)." }
    Write-Ok "Readiness READY"
}

function Invoke-ReleasePrepare([string]$Action) {
    Write-Section "release-prepare $Action - $($script:ProjectKey)"
    $entry = Join-Path $script:DeploymentSystem "deployment\release-prepare.php"
    if (-not (Test-Path $entry)) { Stop-Release "release-prepare not found at $entry." }

    $phpDir = Split-Path $script:Php -Parent
    $savedPath = $env:PATH
    $savedUrl = $env:DEPLOYMENT_RELEASE_METADATA_URL
    $savedSecret = $env:DEPLOYMENT_RELEASE_SECRET
    # The policy's checks shell out to bare `php`.
    $env:PATH = "$phpDir;$savedPath"
    if ($Action -eq "prepare") {
        # Resolved before anything is committed: a missing secret must not
        # leave a pushed release without recorded metadata.
        $env:DEPLOYMENT_RELEASE_METADATA_URL = "$($HubBaseUrl.TrimEnd('/'))/api/projects/$($script:ProjectKey)/releases"
        $env:DEPLOYMENT_RELEASE_SECRET = Get-ReleaseSecret $script:ProjectKey
        Write-Step "Signed metadata target: $($env:DEPLOYMENT_RELEASE_METADATA_URL)"
    }
    Push-Location $script:RepoRoot
    try {
        $prev = $ErrorActionPreference; $ErrorActionPreference = "Continue"
        $out = & $script:Php $entry $Action
        $code = $LASTEXITCODE
        $ErrorActionPreference = $prev
    } finally {
        Pop-Location
        $env:PATH = $savedPath
        $env:DEPLOYMENT_RELEASE_METADATA_URL = $savedUrl
        $env:DEPLOYMENT_RELEASE_SECRET = $savedSecret
    }
    if ($code -ne 0) { Stop-Release "release-prepare $Action failed (exit $code); see its message above." }

    $result = ($out -join "`n") | ConvertFrom-Json
    Write-Ok "Status: $($result.status); base_sha $($result.base_sha); $(@($result.changed_files).Count) changed file(s)"
    # Entries are {name, type, command, exit_code, duration_ms}; release-prepare
    # aborts on any non-zero exit, so every listed check passed.
    foreach ($t in @($result.test_results)) { Write-Host "         $($t.name): exit $($t.exit_code) ($($t.duration_ms) ms)" }
    if ($Action -eq "prepare") {
        Invoke-Git @("fetch", "--no-tags", $script:Remote, $script:Branch) | Out-Null
        $pushed = (Invoke-Git @("rev-parse", "FETCH_HEAD") | Select-Object -First 1).Trim().ToLower()
        if ($pushed -ne $result.commit_sha) {
            Stop-Release "remote HEAD $pushed does not equal the release commit $($result.commit_sha)."
        }
        $metaDir = ".deployment/releases"
        if ($script:Policy.PSObject.Properties.Name -contains "metadata" -and $script:Policy.metadata.local_directory) {
            $metaDir = $script:Policy.metadata.local_directory
        }
        Write-Ok "Release commit $($result.commit_sha) pushed; signed metadata delivered (HTTP 2xx)."
        Write-Ok "Local metadata: $(Join-Path $script:RepoRoot $metaDir)\$($result.commit_sha).json"
        Write-Warn "Receipt and artifact identity must be confirmed from the Hub's own records (P7). Deployment is the owner's action in the Hub."
    }
}

function Invoke-Health {
    Write-Section "HTTP health (read-only)"
    $failed = 0
    foreach ($url in $HealthUrls) {
        $prev = $ErrorActionPreference; $ErrorActionPreference = "Continue"
        $line = & curl.exe -s -o NUL -L -m 20 -w "%{http_code} %{url_effective}" $url
        $ErrorActionPreference = $prev
        if ($line -match "^200 ") { Write-Ok "$url --> $line" }
        else { Write-Host "  [FAIL] $url --> $line" -ForegroundColor Red; $failed++ }
    }
    if ($failed -gt 0) { throw "$failed health probe(s) did not end in HTTP 200." }
}

try {
    $script:RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
    $policyPath = Join-Path $script:RepoRoot ".deployment\release-policy.json"
    if (-not (Test-Path $policyPath)) { Stop-Release "no .deployment/release-policy.json in $($script:RepoRoot)." }
    $script:Policy = Get-Content $policyPath -Raw | ConvertFrom-Json
    $script:ProjectKey = [string]$script:Policy.project_key
    $script:Branch = [string]$script:Policy.production_branch
    $script:Remote = "origin"
    if ($script:Policy.PSObject.Properties.Name -contains "remote" -and $script:Policy.remote) { $script:Remote = [string]$script:Policy.remote }

    Write-Host ""
    Write-Host "  Release pipeline: $($script:ProjectKey) [$Mode] in $($script:RepoRoot)" -ForegroundColor Magenta

    if ($Mode -eq "health") {
        Invoke-Health
    } else {
        $script:Php = Resolve-Php
        $script:DeploymentSystem = Resolve-Sibling $DeploymentSystemPath "deployment-system"
        $script:Agents = Resolve-Sibling $AgentsPath "agents"
        Invoke-Preflight
        Invoke-Readiness
        Invoke-ReleasePrepare $Mode
    }
    Write-Section "Completed: $Mode"
    exit 0
} catch {
    Write-Section "Failed: $Mode"
    Write-Host "  [ERROR] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
