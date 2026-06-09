# ================================================================
# deploy.ps1 — SaaS Platform Deploy Script
# Usage: .\deploy.ps1 "توضیح تغییر"
# ================================================================

param([string]$Message = "deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm')")

$SSH_KEY  = "$env:USERPROFILE\.ssh\deploy_saas"
$SERVER   = "root@87.107.12.81"
$PORT     = "9011"
$PROJECT  = "saas"
$DIR      = "c:\laragon\www\saas-platform"
$ZIPFILE  = "$env:TEMP\deploy_saas.zip"

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

# ── ۲. Archive فایل‌ها ────────────────────────────────────────
Write-Host "`n[2/4] Creating archive..." -ForegroundColor Yellow
if (Test-Path $ZIPFILE) { Remove-Item $ZIPFILE }
git archive --format=zip HEAD -o $ZIPFILE
Write-Host "     Archive: $ZIPFILE" -ForegroundColor Green

# ── ۳. Upload به سرور ────────────────────────────────────────
Write-Host "`n[3/4] Uploading to server..." -ForegroundColor Yellow
scp -P $PORT -i $SSH_KEY -o StrictHostKeyChecking=no $ZIPFILE "${SERVER}:/tmp/deploy_saas.zip"
Write-Host "     Uploaded" -ForegroundColor Green

# ── ۴. اجرای deploy روی سرور ─────────────────────────────────
Write-Host "`n[4/4] Running deploy on server..." -ForegroundColor Yellow
ssh -i $SSH_KEY -p $PORT -o StrictHostKeyChecking=no $SERVER "bash /var/www/deploy.sh saas"

Write-Host "`n=== Deploy Complete ===" -ForegroundColor Green
