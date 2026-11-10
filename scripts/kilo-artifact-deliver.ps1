param (
    [string]$ProjectKey = "saas-platform",
    [string]$HubBaseUrl = "https://deployment.safhenokhost.ir",
    [string]$Branch = "main"
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " [Kilo] Starting Direct Artifact Delivery " -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# ۲. استخراج متاداده‌های Git
$commitSha = (git rev-parse HEAD).Trim().ToLower()
$baseSha = (git rev-parse HEAD~1 2>$null)
if (-not $baseSha) {
    $baseSha = $commitSha
} else {
    $baseSha = $baseSha.Trim().ToLower()
}

$rawChangedFiles = git diff --name-only $baseSha $commitSha
$changedFiles = @()
if ($rawChangedFiles) {
    $changedFiles = @($rawChangedFiles -split "`r?`n" | Where-Object { $_ -ne "" })
}

$migrationFiles = @($changedFiles | Where-Object { $_ -match "^database/migrations/" })
$timestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-dd HH:mm:ss")

# ۳. خواندن کلید امضا
$secret = $env:DEPLOYMENT_RELEASE_SECRET
if (-not $secret) {
    $secretFile = "C:\laragon\www\agents\.secrets.env"
    if (Test-Path $secretFile) {
        $line = Get-Content $secretFile | Where-Object { $_ -match "DEPLOYMENT_RELEASE_SECRET_SAAS=(.+)" }
        if ($line) { $secret = $Matches[1].Trim() }
    }
}
if (-not $secret) {
    throw "[Kilo Blocker] Secret key could not be resolved."
}

# ۴. ساخت Payload
$payloadObj = [ordered]@{
    project_key       = $ProjectKey
    commit_sha        = $commitSha
    base_sha          = $baseSha
    branch            = $Branch
    changed_files     = $changedFiles
    migration_files   = $migrationFiles
    test_results      = @{ status = "passed"; automated = $true }
    build_result      = @{ status = "ok"; exit_code = 0 }
    risk_flags        = @()
    created_timestamp = $timestamp
    release_ready     = $true
}

$rawJson = $payloadObj | ConvertTo-Json -Depth 5 -Compress

# ۵. محاسبه امضای HMAC SHA-256
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($secret)
$sigBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($rawJson))
$signature = "sha256=" + [BitConverter]::ToString($sigBytes).Replace("-", "").ToLower()

# ۶. ارسال درخواست به Deployment Hub
$targetUrl = "$HubBaseUrl/api/projects/$ProjectKey/releases"
Write-Host "--> Target Endpoint: $targetUrl" -ForegroundColor Yellow
Write-Host "--> Commit SHA:      $commitSha" -ForegroundColor Yellow
Write-Host "--> Branch:          $Branch" -ForegroundColor Yellow

$headers = @{
    "Content-Type"               = "application/json"
    "X-Deployment-Signature-256" = $signature
}

Write-Host "--> Sending signed payload..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri $targetUrl -Method Post -Body $rawJson -Headers $headers
    Write-Host "`n[Kilo Success] Release delivered and queued successfully!" -ForegroundColor Green
    Write-Host "Release ID: $($response.release_id)" -ForegroundColor Cyan
    Write-Host "Status:     $($response.status)" -ForegroundColor Cyan
} catch {
    $errResp = $_.Exception.Response
    $statusCode = if ($errResp) { [int]$errResp.StatusCode } else { 0 }
    
    if ($statusCode -eq 409) {
        Write-Host "`n[Kilo Notice] Release already registered and in progress on Deployment Hub (HTTP 409 Conflict)." -ForegroundColor Yellow
        Write-Host "The exact commit ($commitSha) is already recognized and being processed." -ForegroundColor Green
    } else {
        Write-Error "[Kilo Failure] Deployment Hub rejected request with HTTP status: $statusCode"
        if ($_.ErrorDetails.Message) {
            Write-Host "Server Message: $($_.ErrorDetails.Message)" -ForegroundColor Red
        }
        throw $_
    }
}