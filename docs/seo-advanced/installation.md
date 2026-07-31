# SEO Advanced — Installation Guide (Developers / Platform Admins)

## پیش‌نیازها

- PHP `^8.1`, Laravel `^10.0|^11.0`
- بسته `saas-platform/core-utils` (برای Extension Pointهای `SeoOverrideProviderInterface` و `SitemapUrlProviderInterface`)

## نصب از طریق Module Ecosystem (توصیه‌شده — برای مشتریان واقعی)

نصب واقعی این ماژول روی سایت مشتری، از طریق زیرساخت عمومی ساخته‌شده در فازهای 17-E تا 17-I انجام می‌شود و نیازی به دستور دستی ندارد:

1. مشتری ماژول را از Marketplace خریداری می‌کند (`ModuleAssignmentService::activatePaidPurchase`).
2. `CustomerInstallationPipelineJob` به‌صورت خودکار Release منتشرشده (`ModuleRelease::latestPublishedForModule`) را روی سرور مشتری Deploy می‌کند.
3. `SeoAdvancedServiceProvider` هنگام بوت شدن composer/Laravel، بایندینگ‌های خودش را (شامل بازنویسی `SeoOverrideProviderInterface`) ثبت می‌کند و migrationها اجرا می‌شوند.

هیچ مرحله دستی دیگری لازم نیست — این همان چیزی است که در Phase 18 برای اولین‌بار end-to-end تست و تایید شد.

## نصب برای توسعه (Local/Dev)

```bash
composer require saas-platform/modules-seo-advanced:@dev
php artisan migrate
```

پس از نصب، این publish tagها در دسترس‌اند (اختیاری، معمولاً فقط برای override کردن View/Config لازم است):

```bash
php artisan vendor:publish --tag=seo-advanced-config
php artisan vendor:publish --tag=seo-advanced-views
php artisan vendor:publish --tag=seo-advanced-migrations
```

## اعتبارسنجی نصب

```bash
php artisan module:validate seo-advanced
```

این دستور ماژول را در برابر Module Package Contract رسمی (Phase 17-F) بررسی می‌کند — namespace، ساختار پوشه، `module.json`، عدم وابستگی به `App\Models\*`.

## Runtime Integration (برای customer-site)

اگر ماژول در `customer-site` نصب شود، `AppServiceProvider` هاست باید (طبق قرارداد عمومی Phase 18-B) این دو Interface را از قبل بایند کرده باشد؛ SEO Advanced به‌صورت خودکار آن‌ها را بازنویسی می‌کند:

- `SeoOverrideProviderInterface` → `SeoOverrideProviderAdapter` (این ماژول)
- هیچ تغییری در Blade تم‌ها لازم نیست — تزریق از طریق `@stack('head')`/`@stack('json-ld')` موجود انجام می‌شود.

## انتشار نسخه جدید (برای توسعه‌دهندگان ماژول)

پس از هرگونه تغییر در کد این ماژول:

```bash
php artisan module:release seo-advanced --type=minor   # مثلاً 1.0.0 -> 1.1.0
php artisan module:package-build seo-advanced
php artisan module:publish seo-advanced
```

> فاز 18-D تغییرات قابل‌توجهی به کد اضافه کرده (Canonical Policy، Audit Engine، Keyword Tracking، Reports) در حالی که Release منتشرشده فعلی هنوز نسخه `1.0.0` (متعلق به Phase 18) است. پیش از فروش واقعی این نسخه گسترش‌یافته، انتشار یک Release جدید ضروری است.
