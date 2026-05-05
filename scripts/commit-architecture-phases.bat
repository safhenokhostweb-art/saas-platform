@echo off
chcp 65001 >nul
setlocal

echo === saas-platform ===
cd /d c:\laragon\www\saas-platform
git add app/Http/Controllers/Api/UpdateController.php
git add docs/implementation-guides/04-update-server-and-customer-site-check.md
git add docs/PROJECT-GUIDE.md
git add docs/DESIGN-SYSTEM.md
git add database/seeders/ModuleSeeder.php
git status
git commit -m "fix(api): gate updates by support; docs: DESIGN-SYSTEM, PROJECT-GUIDE; feat: blog module in ModuleSeeder"
if errorlevel 1 echo Warning: saas-platform commit had an issue.

echo.
echo === customer-site ===
cd /d c:\laragon\www\customer-site
git add app/Helpers/SiteHelper.php
git add app/Helpers/Seo.php
git add app/Helpers/JsonLd.php
git add app/Http/Controllers/Front/SitemapController.php
git add routes/web.php
git add resources/views/themes/default/layout.blade.php
git add resources/views/themes/default/page.blade.php
git add resources/views/themes/default/blog/show.blade.php
git add resources/views/themes/default/shop/show.blade.php
git add docs/PROJECT-GUIDE.md
git status
git commit -m "feat: license-based modules, SEO meta/sitemap/JSON-LD, unified front layout; docs: PROJECT-GUIDE"
if errorlevel 1 echo Warning: customer-site commit had an issue.

echo.
echo Done.
pause
