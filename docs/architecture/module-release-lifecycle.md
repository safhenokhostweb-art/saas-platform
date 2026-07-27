# ADR: Module Release Lifecycle & Publishing Contract

**Status:** Accepted
**Date:** 2026-07-19
**Phase:** 17-H
**Related:** [module-version-authority-adr.md](module-version-authority-adr.md), [module-package-contract.md](module-package-contract.md)

---

## بخش ۱ — Audit جریان فعلی (پیش از این فاز)

- `ModuleReleaseProducerAdapter::record()` یک ردیف `module_releases` می‌سازد: version، release_type، changelog، distributed_at، is_active. **هیچ Status نداشت** — تنها سیگنال «آماده بودن» وجود `package_sha256` بود.
- Package (ZIP) در `storage/app/modules/packages/{slug}-{version}.zip` (دیسک محلی سرور پلتفرم) نگهداری می‌شود؛ مسیرش در `module_releases.package_path` ثبت می‌شود.
- Manifest (فایل جدا، برای گزارش/چک compatibility) در `storage/app/modules/releases/{slug}.json` — طبق `ReleaseManifest` — یک فایل روی دیسک، نه ردیف DB.
- **یافتهٔ اصلی:** یک Release بلافاصله بعد از ساخته‌شدن (داشتن `package_sha256`) از نظر `ModuleRelease::latestForModule()` «آخرین نسخه» محسوب و توسط `ModuleDeploymentRequestService`/`ModuleUpdateEligibilityService` قابل انتخاب بود — **هیچ Gate انسانی/تجاری بین «ساخته‌شدن» و «در دسترس مشتری» وجود نداشت.**

## بخش ۲ — Release Status Lifecycle

ستون جدید و Additive: `module_releases.status` (string، پیش‌فرض `published`).

```
draft → validated → built → published → deprecated
```

- **draft** — `module:release` آن را همین‌طور می‌سازد (Part 6).
- **built** — بعد از این‌که `ModulePackageBuilderService` بسته را واقعاً ساخت (اتوماتیک، در `ModulePackageBuilderService::build()`).
- **published** — فقط با `module:publish {slug} {version}` بعد از عبور از قرارداد Part 3.
- **deprecated** — Backfill شد برای ردیف‌های `is_active=false` موجود؛ فعلاً هیچ Command جدیدی برای رسیدن به این حالت ساخته نشد (خارج از محدودهٔ این فاز — فقط Publish خواسته شده بود).
- **validated** — در Schema نگه داشته شد برای تکمیل بودن Lifecycle، ولی هیچ کد فعلی آن را Set نمی‌کند (هیچ‌جا خواسته نشده بود جدا از build/publish یک مرحلهٔ Validate-only داشته باشیم).

**چرا پیش‌فرض `published` است، نه `draft`:** هر ردیف موجود قبل از Migration، و هر ردیفی که مستقیماً با `ModuleRelease::create()` ساخته شود (مثل تقریباً همهٔ تست‌های فعلی پلتفرم) باید همان رفتار سابق را حفظ کند — بلافاصله قابل‌استقرار. فقط مسیر تعاملی `module:release` صریحاً `status: 'draft'` می‌فرستد.

`is_active` دست‌نخورده ماند — همان معنای قبلی خودش (Rollback safety flag، طبق ADR نسخه) را دارد؛ `status` یک محور جداست.

## بخش ۳ — Publishing Contract

`module:publish {slug} {version}` قبل از تغییر Status چک می‌کند:

| چک | چگونه |
|---|---|
| Manifest معتبر | `ModuleManifestSchema::validate()` روی `module.json` فعلی |
| Compatibility مشخص | بخشی از همان چک Schema (Recommended field) |
| Dependencies معتبر | بخشی از همان چک Schema (shape) |
| Package موجود | `package_sha256`/`package_path` غیرخالی — که خودش یعنی از `ModulePackageValidator` (17-F) قبلاً عبور کرده |
| Release version معتبر | ردیف `module_releases` با آن نسخه اصلاً وجود دارد |
| رد Deprecated | یک Release بازنشسته هرگز دوباره Publish نمی‌شود |

بعد از Publish موفق: `ModuleRelease::latestPublishedForModule()` این ردیف را می‌بیند و بنابراین `ModuleDeploymentRequestService` می‌تواند آن را انتخاب کند.

## بخش ۴ — Marketplace Readiness Audit

بررسی شد که آیا `module_releases` باید فیلدهای فروش (title, description, icon, screenshots, documentation, pricing) داشته باشد. **نتیجه: خیر، هیچ‌کدام اضافه نشد.**

| فیلد | مسئول فعلی | نیاز به تغییر؟ |
|---|---|---|
| title / display name | `modules.title` | خیر — از قبل درست |
| description | `modules.description`, `modules.short_description` | خیر — از قبل درست |
| screenshots | `modules.screenshots` (JSON) | خیر — از قبل درست |
| pricing | `modules.price_irr`, `modules.price_promo_irr` | خیر — از قبل درست |
| icon | هیچ‌جا | این فاز اضافه نکرد — وقتی UI واقعی Marketplace به آن نیاز داشت، باید روی `modules` (نه `module_releases`) اضافه شود |
| documentation | `README.md` تولیدشده توسط `module:create` (Phase 17-G) | کافی است؛ نیاز به ستون DB نیست |
| compatibility | `module.json` (build-time، طبق ADR نسخه) | خیر — نباید به DB منتقل شود |

**اصل تفکیک مسئولیت:** `Module` (DB) = Platform Catalog (چیزی که ادمین در پنل ویرایش می‌کند، مشتری در Marketplace می‌بیند). `module_releases` = Package Identity (نسخه، checksum، مسیر فایل، وضعیت انتشار). `module.json` = Build-time Contract (compatibility، dependencies، namespace). این سه هرگز نباید همپوشانی/تکرار پیدا کنند.

## بخش ۵ — Changelog Contract

**از قبل وجود داشت و کافی است:** `module_releases.changelog` (Phase 6.98). هیچ فیلد یا فایل جدیدی لازم نبود. برای نمایش به مشتری قبل از Upgrade، `SiteModuleUpgradeController`/`ModuleUpdateEligibilityService` می‌توانند مستقیماً از همین ستون بخوانند (در این فاز به آن Controller دست زده نشد — خارج از محدوده).

## بخش ۶ — Deployment Selection (Part 7)

قبل از این فاز، `ModuleRelease::latestForModule()` فقط `is_active=true` را چک می‌کرد — نسخه‌ای که هنوز Draft/Built بود اما `is_active` پیش‌فرضش true است می‌توانست به‌اشتباه انتخاب شود. اصلاح شد:

- `ModuleDeploymentRequestService::deployUnderLock()` (نصب/آپدیت/Reinstall/Retry) → `ModuleRelease::latestPublishedForModule()`
- `ModuleUpdateEligibilityService::getAvailableModuleUpdates()` (فهرست Upgrade) → `ModuleRelease::getLatestPublishedVersionForModule()`
- `DeploymentResolver` (نمایش وضعیت "آپدیت موجود است؟") → همان متد بالا

`ModuleRelease::latestForModule()`/`getLatestVersionForModule()` **دست‌نخورده ماندند** — `ModuleReleaseProducerAdapter`'s نسخه-باید-بزرگ‌تر-باشد Guard باید در برابر همهٔ Releaseها (صرف‌نظر از Status) چک کند، نه فقط منتشرشده‌ها؛ وگرنه یک توسعه‌دهنده می‌توانست همان نسخهٔ Draft قبلی را دوباره بسازد.
