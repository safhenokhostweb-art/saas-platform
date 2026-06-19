# ================================================================
# deploy.ps1 — SaaS Platform Deploy Script
# Usage: .\deploy.ps1 "توضیح تغییر"
# vendor از لوکال آپلود می‌شود — سرور نیازی به دانلود ندارد
# NOTE: برای SCP مستقیم فایل PHP از .NET UTF8Encoding($false) استفاده کن
#       تا BOM اضافه نشود (Out-File -Encoding utf8 در PS 5.1 BOM اضافه می‌کند)
# ================================================================

param([string]$Message = "deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm')")

$SSH_KEY    = "$env:USERPROFILE\.ssh\deploy_saas"
$SERVER     = "root@87.107.12.81"
$PORT       = "9011"
$DIR        = "c:\laragon\www\saas-platform"
$ZIPFILE    = "$env:TEMP\deploy_saas_$(Get-Date -Format 'HHmmss').zip"
$PHP        = "C:\laragon\bin\php\php-8.4.16-nts-Win32-vs17-x64\php.exe"
$COMPOSER   = "C:\laragon\bin\composer\composer.phar"

Set-Location $DIR

Write-Host "`n=== SaaS Platform Deploy ===" -ForegroundColor Cyan
Write-Host "Message: $Message" -ForegroundColor Gray

# ── ۱. Git commit + push ──────────────────────────────────────
Write-Host "`n[1/4] Git commit & push..." -ForegroundColor Yellow
git add -A
$status = git status --porcelain
if ($status) {
    git commit -m $Message
    git push origin main
    Write-Host "     Pushed to GitHub" -ForegroundColor Green
} else {
    Write-Host "     No changes to commit" -ForegroundColor Gray
}

# ── ۲. Build vendor + Archive ────────────────────────────────
Write-Host "`n[2/4] Building vendor & creating archive..." -ForegroundColor Yellow
& $PHP $COMPOSER install --no-dev --optimize-autoloader --no-scripts --no-interaction --quiet

# Sync local path-repositories into vendor
$localPackages = @(
    'packages\saas-platform\core-utils',
    'packages\saas-platform\modules\education',
    'packages\saas-platform\modules\forms',
    'packages\saas-platform\modules\tickets'
)
foreach ($pkg in $localPackages) {
    $src = Join-Path $DIR $pkg
    $parts   = $pkg -replace '\\','/' -split '/'
    $vendor  = Join-Path $DIR "vendor\$($parts[-2])\$($parts[-1])"
    if ((Test-Path $src) -and (Test-Path $vendor)) {
        Write-Host "     Syncing $pkg -> vendor" -ForegroundColor DarkGray
        robocopy $src $vendor /E /XD .git /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    }
}

# پاکسازی zip های قدیمی (بیش از 1 ساعت)
Get-ChildItem "$env:TEMP\deploy_saas_*.zip" -ErrorAction SilentlyContinue |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddHours(-1) } |
    Remove-Item -Force -ErrorAction SilentlyContinue

if (Test-Path $ZIPFILE) { Remove-Item $ZIPFILE -Force }

# ── .NET ZipFile — مستقیم از PowerShell، بدون نیاز به PHP ──────
Add-Type -Assembly System.IO.Compression.FileSystem
Add-Type -Assembly System.IO.Compression

$excludeDirs  = @('.git','.claude','.github','mobile','node_modules','tests','storage','bootstrap')
$excludeFiles = @('.env','.env.local','.env.testing')

Write-Host "     Building ZIP (this may take a few minutes)..." -ForegroundColor DarkGray
$zip = [System.IO.Compression.ZipFile]::Open($ZIPFILE, [System.IO.Compression.ZipArchiveMode]::Create)

Get-ChildItem -Path $DIR -Recurse -File -ErrorAction SilentlyContinue | ForEach-Object {
    $fullPath = $_.FullName
    $rel      = $fullPath.Substring($DIR.Length + 1) -replace '\\', '/'
    $parts    = $rel -split '/'
    $excluded = $false

    # check top-level directory
    if ($excludeDirs -contains $parts[0]) { $excluded = $true }
    # check any path segment
    if (-not $excluded) {
        foreach ($part in $parts[0..([Math]::Max(0,$parts.Length-2))]) {
            if ($excludeDirs -contains $part) { $excluded = $true; break }
        }
    }
    # check filename
    if (-not $excluded -and ($excludeFiles -contains $_.Name)) { $excluded = $true }

    if (-not $excluded) {
        try {
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $fullPath, $rel,
                [System.IO.Compression.CompressionLevel]::Fastest
            ) | Out-Null
        } catch { <# skip locked files #> }
    }
}
$zip.Dispose()

$sizeMB = [Math]::Round((Get-Item $ZIPFILE).Length / 1MB, 1)
Write-Host "     Archive: $ZIPFILE ($sizeMB MB)" -ForegroundColor Green

# ── ۳. Upload به سرور ────────────────────────────────────────
Write-Host "`n[3/4] Uploading to server..." -ForegroundColor Yellow
scp -P $PORT -i $SSH_KEY -o StrictHostKeyChecking=no $ZIPFILE "${SERVER}:/tmp/deploy_saas.zip"
Write-Host "     Uploaded" -ForegroundColor Green

# ── ۴. اجرای deploy روی سرور ─────────────────────────────────
Write-Host "`n[4/4] Running deploy on server..." -ForegroundColor Yellow
ssh -i $SSH_KEY -p $PORT -o StrictHostKeyChecking=no -o ServerAliveInterval=30 $SERVER "bash /var/www/deploy.sh saas"

Write-Host "`n=== Deploy Complete ===" -ForegroundColor Green
