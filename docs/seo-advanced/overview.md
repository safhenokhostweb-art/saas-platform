# SEO Advanced — Overview

ماژول تجاری **SEO Advanced** (`saas-platform/modules-seo-advanced`, slug: `seo-advanced`) اولین ماژول کاملاً مستقل و قابل‌فروش پلتفرم است. این سند یک نمای کلی از قابلیت‌ها، معماری، و وضعیت آماده‌سازی برای فروش را ارائه می‌دهد.

## قابلیت‌ها

| حوزه | توضیح |
|---|---|
| Meta Management | عنوان، توضیحات، کلمات کلیدی، Robots، Canonical Policy (auto / force_https / custom) |
| OpenGraph & Twitter Card | مقادیر پیش‌فرض OG + نوع Twitter Card (summary / summary_large_image) |
| Schema.org | Organization، LocalBusiness، WebSite JSON-LD |
| Sitemap | `/seo-advanced/{siteId}/sitemap.xml` — منابع URL از Core (صفحات/محصولات) از طریق Extension Point جمع‌آوری می‌شود، نه Hardcode |
| Robots.txt | `/seo-advanced/{siteId}/robots.txt` |
| Redirects | ریدایرکت‌های 301/302 با اجرای واقعی در سطح Middleware |
| SEO Audit Engine | بررسی Title، Description، H1، سلسله‌مراتب تیترها، Canonical، Robots، Schema، Sitemap، OpenGraph، Alt تصاویر، ساختار URL — خروجی: امتیاز + فهرست مشکلات با severity/message/suggested_fix |
| Keyword Tracking (Foundation) | ثبت کلمات کلیدی، آدرس هدف، وضعیت، و تاریخچه رتبه (بدون اتصال به سرویس خارجی) |
| SEO Reports | گزارش مدیریتی (روند امتیاز، تعداد مشکلات) + گزارش مشتری (فقط سایت خودش) |

## معماری

- **استقلال کامل**: کد داخل `src/` هرگز به `App\Models\*` (مدل‌های Core) وابسته نیست. تمام مدل‌ها (`SeoAdvancedSetting`, `SeoAdvancedRedirect`, `SeoAdvancedAudit`, `SeoAdvancedKeyword`) در جداول اختصاصی این ماژول (`seo_advanced_*`) ذخیره می‌شوند.
- **Extension Points**: اتصال به runtime واقعی (customer-site) از طریق دو Contract عمومی در `core-utils`:
  - `SeoOverrideProviderInterface` — مقادیر Meta/OG/Twitter/Canonical واقعی سایت را به `SeoService` هاست تزریق می‌کند.
  - `SitemapUrlProviderInterface` — URLهای واقعی صفحات/محصولات Core را بدون اینکه این ماژول مدل‌های Core را بشناسد فراهم می‌کند.
- **Single-Tenant Convention**: در customer-site (که همیشه دقیقاً یک سایت است)، آداپتورها از یک ثابت `SINGLE_TENANT_SITE_ID = 1` استفاده می‌کنند. در پلتفرم ادمین (چند-سایتی)، همان جداول با `site_id` واقعی هر سایت به کار می‌روند.
- **Backward Compatibility**: تمام migrationهای این فاز (18-D) افزایشی و nullable/defaulted هستند؛ هیچ رفتار قبلی سایت‌های موجود تغییر نمی‌کند مگر با backfill صریح (مثال: `canonical_strategy` برای ردیف‌هایی که از قبل `canonical_url` داشتند).

## وضعیت فروش (Marketplace Readiness)

| مورد | وضعیت |
|---|---|
| Module (Catalog) | ✅ `slug=seo-advanced`, `price_irr=490000`, `marketplace_visible=true` |
| Product | ✅ `sku=module-seo-advanced`, `type=module_addon` |
| Release منتشرشده | ✅ نسخه `1.0.0`, `status=published` |
| License Flow | ✅ از طریق زیرساخت عمومی Module Ecosystem (Phase 17) — بدون منطق اختصاصی licensing در این ماژول |

> **توجه**: محتوای این ماژول در فاز 18-D به‌طور قابل‌توجهی گسترش یافته (Canonical Policy، Audit Engine حرفه‌ای، Keyword Tracking، Reports). توصیه می‌شود پیش از فروش واقعی، یک Release جدید (`1.1.0`) از طریق `module:release` / `module:package-build` / `module:publish` منتشر شود تا هش بسته منتشرشده با کد فعلی همخوانی داشته باشد.

## اسناد مرتبط

- [installation.md](installation.md) — نصب برای توسعه‌دهندگان/ادمین پلتفرم
- [admin-guide.md](admin-guide.md) — راهنمای پنل مدیریت
- [customer-guide.md](customer-guide.md) — راهنمای مشتری نهایی
- [seo-rules.md](seo-rules.md) — قوانین و آستانه‌های موتور Audit
