# ================================================================
# deploy.ps1 — SaaS Platform Deploy Script
# Usage: .\deploy.ps1 "توضیح تغییر"
# vendor از لوکال آپلود می‌شود — سرور نیازی به دانلود ندارد
# ================================================================

param([string]$Message = "deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm')")

$SSH_KEY    = "$env:USERPROFILE\.ssh\deploy_saas"
$SERVER     = "root@87.107.12.81"
$PORT       = "9011"
$DIR        = "c:\laragon\www\saas-platform"
$ZIPFILE    = "$env:TEMP\deploy_saas.zip"
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
if (Test-Path $ZIPFILE) { Remove-Item $ZIPFILE }
# آرشیو از روی فایل‌های واقعی (شامل vendor) نه git archive
$exclude = @('.git', 'node_modules', 'tests', 'storage/logs', 'storage/framework/cache')
$items   = Get-ChildItem -Path $DIR | Where-Object { $_.Name -notin $exclude }
Compress-Archive -Path ($items | ForEach-Object { $_.FullName }) -DestinationPath $ZIPFILE -Force
Write-Host "     Archive: $ZIPFILE ($('{0:N1}' -f ((Get-Item $ZIPFILE).Length / 1MB)) MB)" -ForegroundColor Green

# ── ۳. Upload به سرور ────────────────────────────────────────
Write-Host "`n[3/4] Uploading to server..." -ForegroundColor Yellow
scp -P $PORT -i $SSH_KEY -o StrictHostKeyChecking=no $ZIPFILE "${SERVER}:/tmp/deploy_saas.zip"
Write-Host "     Uploaded" -ForegroundColor Green

# ── ۴. اجرای deploy روی سرور ─────────────────────────────────
Write-Host "`n[4/4] Running deploy on server..." -ForegroundColor Yellow
ssh -i $SSH_KEY -p $PORT -o StrictHostKeyChecking=no -o ServerAliveInterval=30 $SERVER "bash /var/www/deploy.sh saas"

Write-Host "`n=== Deploy Complete ===" -ForegroundColor Green
