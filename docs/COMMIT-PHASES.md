# دستورات کامیت برای مراحل پیاده‌سازی

هر بلوک را در ترمینال مربوط به همان پروژه اجرا کنید. اول **saas-platform**، بعد **customer-site**.

---

## saas-platform

```powershell
cd c:\laragon\www\saas-platform
```

### کامیت ۱ — محدودیت بروزرسانی به دورهٔ پشتیبانی
```powershell
git add app/Http/Controllers/Api/UpdateController.php docs/implementation-guides/04-update-server-and-customer-site-check.md docs/PROJECT-GUIDE.md
git commit -m "fix(api): gate updates by active support period"
```

### کامیت ۷ — سند طراحی (قبل از customer-site تا ترتیب منطقی رعایت شود)
```powershell
git add docs/DESIGN-SYSTEM.md
git commit -m "docs: add DESIGN-SYSTEM.md"
```

### کامیت ۸ — یادداشت lazy loading در DESIGN-SYSTEM (همان فایل)
اگر قبلاً DESIGN-SYSTEM را کامیت کرده‌اید، این را با کامیت بعدی ادغام کنید یا:
```powershell
git add docs/DESIGN-SYSTEM.md
git commit -m "docs: design-system note for lazy loading and image dimensions"
```

### کامیت ۹ — ماژول blog در پلتفرم
```powershell
git add database/seeders/ModuleSeeder.php docs/PROJECT-GUIDE.md
git commit -m "feat(platform): add blog module to ModuleSeeder"
```

---

## customer-site

```powershell
cd c:\laragon\www\customer-site
```

### کامیت ۲ — ماژول از لایسنس در SiteHelper
```powershell
git add app/Helpers/SiteHelper.php docs/PROJECT-GUIDE.md
git commit -m "feat(customer-site): resolve blog/shop from license, fallback to config"
```

### کامیت ۳ — یکپارچگی layout فرانت با platform-styles و Vazirmatn
```powershell
git add resources/views/themes/default/layout.blade.php
git commit -m "style(customer-site): unify front layout with platform-styles and Vazirmatn"
```

### کامیت ۴ — SEO پایه (متا، OG، canonical و helper)
```powershell
git add app/Helpers/Seo.php resources/views/themes/default/layout.blade.php resources/views/themes/default/page.blade.php resources/views/themes/default/blog/show.blade.php resources/views/themes/default/shop/show.blade.php
git commit -m "feat(customer-site): SEO meta, canonical, OpenGraph and Seo helper"
```

### کامیت ۵ — Sitemap
```powershell
git add app/Http/Controllers/Front/SitemapController.php routes/web.php
git commit -m "feat(customer-site): sitemap.xml with cache"
```

### کامیت ۶ — JSON-LD
```powershell
git add app/Helpers/JsonLd.php resources/views/themes/default/layout.blade.php resources/views/themes/default/page.blade.php resources/views/themes/default/blog/show.blade.php resources/views/themes/default/shop/show.blade.php
git commit -m "feat(customer-site): JSON-LD for Organization, WebPage, Article, Product"
```

### کامیت ۸ — کش sitemap (اگر SitemapController را جدا کامیت کرده‌اید)
```powershell
git add app/Http/Controllers/Front/SitemapController.php
git commit -m "perf(customer-site): cache sitemap response 1h"
```

### به‌روزرسانی نهایی راهنما (سیمپ و SEO)
```powershell
git add docs/PROJECT-GUIDE.md
git commit -m "docs: PROJECT-GUIDE sitemap route and SEO helpers"
```

---

## نکته

اگر فایل‌ها را قبلاً با سایر تغییرات مخلوط کرده‌اید، می‌توانید همهٔ تغییرات مربوط به این مراحل را یک‌جا اضافه و در یک یا دو کامیت با پیام کلی ثبت کنید، مثلاً:

**saas-platform:**
```powershell
git add app/Http/Controllers/Api/UpdateController.php docs/implementation-guides/04-update-server-and-customer-site-check.md docs/PROJECT-GUIDE.md docs/DESIGN-SYSTEM.md database/seeders/ModuleSeeder.php
git commit -m "feat: gate updates by support, design-system doc, blog module"
```

**customer-site:**
```powershell
git add app/Helpers/SiteHelper.php app/Helpers/Seo.php app/Helpers/JsonLd.php app/Http/Controllers/Front/SitemapController.php routes/web.php resources/views/themes/default/layout.blade.php resources/views/themes/default/page.blade.php resources/views/themes/default/blog/show.blade.php resources/views/themes/default/shop/show.blade.php docs/PROJECT-GUIDE.md
git commit -m "feat: license-based modules, SEO meta/sitemap/JSON-LD, unified front layout"
```
