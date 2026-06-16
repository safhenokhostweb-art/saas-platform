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
$ZIPFILE    = "$DIR\storage\app\deploy_saas_$(Get-Date -Format 'HHmmss').zip"
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

# Sync local path-repositories into vendor (Compress-Archive does not follow junctions/symlinks)
$localPackages = @(
    'packages\saas-platform\core-utils',
    'packages\saas-platform\modules\education',
    'packages\saas-platform\modules\forms',
    'packages\saas-platform\modules\tickets'
)
foreach ($pkg in $localPackages) {
    $src = Join-Path $DIR $pkg
    # vendor name = last two path segments of the package path
    $parts   = $pkg -replace '\\','/' -split '/'
    $vendor  = Join-Path $DIR "vendor\$($parts[-2])\$($parts[-1])"
    if ((Test-Path $src) -and (Test-Path $vendor)) {
        Write-Host "     Syncing $pkg → vendor" -ForegroundColor DarkGray
        robocopy $src $vendor /E /XD .git /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    }
}

# پاکسازی zip های قدیمی (بیش از 1 ساعت)
Get-ChildItem "$DIR\storage\app\deploy_saas_*.zip" -ErrorAction SilentlyContinue | Where-Object { $_.LastWriteTime -lt (Get-Date).AddHours(-1) } | Remove-Item -Force -ErrorAction SilentlyContinue

if (Test-Path $ZIPFILE) { Remove-Item $ZIPFILE }
# PHP ZipArchive — از lock شدن فایل‌های Laragon مصون است
$ZIPSCRIPT = "$env:TEMP\make_zip.php"
$EXCLUDED_DIRS = '.git|.claude|.github|mobile|node_modules|tests|storage|bootstrap'
$EXCLUDED_FILES = '.env|.env.local|.env.testing'
[System.IO.File]::WriteAllText($ZIPSCRIPT, @"
<?php
`$src  = `$argv[1];
`$dest = `$argv[2];
`$exDirs  = explode('|', '${EXCLUDED_DIRS}');
`$exFiles = explode('|', '${EXCLUDED_FILES}');
`$zip = new ZipArchive();
`$zip->open(`$dest, ZipArchive::CREATE | ZipArchive::OVERWRITE);
`$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(`$src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
foreach (`$iter as `$file) {
    if (`$file->isDir()) continue;
    `$rel   = str_replace('\\\\', '/', substr(`$file->getRealPath(), strlen(`$src) + 1));
    `$parts = explode('/', `$rel);
    `$skip  = false;
    foreach (`$exDirs  as `$ex) { if (in_array(trim(`$ex), `$parts)) { `$skip = true; break; } }
    foreach (`$exFiles as `$ex) { if (trim(`$ex) === end(`$parts)) { `$skip = true; break; } }
    if (!`$skip) {
        `$content = @file_get_contents(`$file->getRealPath());
        if (`$content !== false) { `$zip->addFromString(`$rel, `$content); }
    }
}
`$zip->close();
echo round(filesize(`$dest)/1048576, 1) . ' MB' . PHP_EOL;
"@)
$archiveSize = & $PHP $ZIPSCRIPT $DIR $ZIPFILE
Write-Host "     Archive: $ZIPFILE ($archiveSize)" -ForegroundColor Green

# ── ۳. Upload به سرور ────────────────────────────────────────
Write-Host "`n[3/4] Uploading to server..." -ForegroundColor Yellow
scp -P $PORT -i $SSH_KEY -o StrictHostKeyChecking=no $ZIPFILE "${SERVER}:/tmp/deploy_saas.zip"
Write-Host "     Uploaded" -ForegroundColor Green

# ── ۴. اجرای deploy روی سرور ─────────────────────────────────
Write-Host "`n[4/4] Running deploy on server..." -ForegroundColor Yellow
ssh -i $SSH_KEY -p $PORT -o StrictHostKeyChecking=no -o ServerAliveInterval=30 $SERVER "bash /var/www/deploy.sh saas"

Write-Host "`n=== Deploy Complete ===" -ForegroundColor Green
