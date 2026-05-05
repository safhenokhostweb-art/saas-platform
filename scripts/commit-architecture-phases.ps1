# اسکریپت کامیت مراحل معماری
# دو حالت: کامیت تکی (دستورات در COMMIT-PHASES.md) یا دو کامیت کلی (پایین).

$platformRoot = "c:\laragon\www\saas-platform"
$customerRoot = "c:\laragon\www\customer-site"

# --- حالت ساده: دو کامیت کلی (همهٔ تغییرات هر پروژه در یک کامیت) ---

Write-Host "=== saas-platform (1 commit) ===" -ForegroundColor Cyan
Set-Location $platformRoot
git add app/Http/Controllers/Api/UpdateController.php `
  docs/implementation-guides/04-update-server-and-customer-site-check.md `
  docs/PROJECT-GUIDE.md `
  docs/DESIGN-SYSTEM.md `
  database/seeders/ModuleSeeder.php
git status
git commit -m "fix(api): gate updates by support; docs: DESIGN-SYSTEM, PROJECT-GUIDE; feat: blog module in ModuleSeeder"

Write-Host "=== customer-site (1 commit) ===" -ForegroundColor Cyan
Set-Location $customerRoot
git add app/Helpers/SiteHelper.php `
  app/Helpers/Seo.php `
  app/Helpers/JsonLd.php `
  app/Http/Controllers/Front/SitemapController.php `
  routes/web.php `
  resources/views/themes/default/layout.blade.php `
  resources/views/themes/default/page.blade.php `
  resources/views/themes/default/blog/show.blade.php `
  resources/views/themes/default/shop/show.blade.php `
  docs/PROJECT-GUIDE.md
git status
git commit -m "feat: license-based modules, SEO meta/sitemap/JSON-LD, unified front layout; docs: PROJECT-GUIDE"

Write-Host "Done." -ForegroundColor Green
