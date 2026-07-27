# ADR: Module Packaging Contract

**Status:** Accepted
**Date:** 2026-07-18
**Phase:** 17-F
**Supersedes:** No prior document defined module.json's field contract explicitly — it existed only as implicit convention across forms/tickets/education.
**Related:** [module-version-authority-adr.md](module-version-authority-adr.md), [distributed-module-delivery.md](distributed-module-delivery.md)

---

## بخش ۱ — چرا این سند

Phase 6.98 قبلاً `ModuleVersionRegistry`، `ReleaseManifest`، و `PlatformCompatibilityService` را ساخت — یک سیستم واقعی برای خواندن و اعتبارسنجی `module.json` در سطح build-time/پلتفرم. Phase 17-F سیستم موازی نمی‌سازد؛ سه شکاف واقعی را می‌بندد که آن سیستم پر نمی‌کرد:

1. **هیچ‌کس محتوای ZIP ساخته‌شده را قبل از Deploy باز نمی‌کرد** — فقط sha256 چک می‌شد، نه اینکه `module.json` داخلش هست، یا با ردیف `module_releases` مطابقت دارد.
2. **Compatibility Check فقط مسیر Upgrade را می‌پوشاند** — یک نصب تازه از ماژولی ناسازگار با پلتفرم هرگز چک نمی‌شد.
3. **Dependency فقط در سطح مونوریپو معنا داشت** — هیچ چیز چک نمی‌کرد که آیا وابستگی یک ماژول واقعاً روی یک Site مشخص نصب و فعال است.

---

## بخش ۲ — Module Manifest Schema (رسمی)

تعریف در `packages/saas-platform/core-utils/src/Support/ModuleManifestSchema.php`.

### Required (عدم وجود = Package رد می‌شود)
از قبل روی هر ۳ ماژول واقعی (forms/tickets/education) وجود دارند — هیچ ماژول موجودی با این لیست نمی‌شکند:

```
name, module_slug, version, type, namespace, provider
```

### Recommended (عدم وجود = فقط Warning)
```
compatibility, update_type, dependencies
```

### فیلدی که عمداً اضافه نشد
`display_name` — در `Module::$fillable` ستون `title` از قبل همین نقش را دارد (admin-editable، قابل نمایش به کاربر). افزودن `display_name` به `module.json` یعنی دو منبع حقیقت برای یک چیز؛ طبق همان منطق ADR نسخه (`module.json` = build-time، DB = runtime-authoritative)، نام نمایشی باید فقط در DB زندگی کند.

### `lifecycle` به‌صورت wrapper اضافه نشد
درخواست اولیه `lifecycle: {install, upgrade, uninstall}` را به‌صورت یک گروه‌بندی مفهومی خواسته بود. چون `install` از قبل یک کلید top-level در هر ۴ فایل `module.json` واقعی است (نه زیر `lifecycle`)، افزودن یک wrapper جدید یعنی بازنویسی ساختار هر ۴ فایل — این دقیقاً همان بازطراحی غیرضروری‌ای است که این فاز آن را منع کرده. به‌جایش `install` (از قبل) و `uninstall` (جدید، Part 6) هر دو به‌صورت کلید top-level هم‌تراز باقی می‌مانند. `upgrade` چون هیچ مصرف‌کننده‌ای در کد ندارد، به این فاز اضافه نشد (YAGNI — یک فیلد بدون خواننده، ریسک بدون فایده است).

### استثنا: `motion-graphics`
`type: "standalone-package"`, بدون `namespace`/`provider`/`compatibility`, `distributable: false`, و هیچ ردیف در جدول `modules` ندارد. چون `ModuleReleaseProducerAdapter` بدون ردیف `modules` هرگز release نمی‌سازد (`STATUS_SKIPPED_NO_MODULE_ROW`)، این ماژول ساختاراً هرگز به `ModulePackageValidator` نمی‌رسد و از این Schema معاف است.

---

## بخش ۳ — Module Package Validator

`App\Services\Modules\ModulePackageValidator` — فقط یک‌بار فراخوانی می‌شود: از داخل `ModulePackageBuilderService::build()`، بلافاصله بعد از ساخت ZIP و **قبل از** نوشتن `package_sha256`/`package_path` روی ردیف `module_releases`.

**چرا فقط یک‌بار، نه در هر Deploy:** یک release که Validation را رد کند هرگز `package_sha256` نمی‌گیرد، و `ModuleDeploymentRequestService` از قبل هر release با `package_sha256` خالی را رد می‌کند. پس Gate کردن در نقطهٔ ساخت کافی است — نیازی نیست هر فراخوانی Deploy دوباره ZIP را باز کند (که هم پرهزینه است و هم بی‌فایده، چون محتوای ZIP بین دو Deploy عوض نمی‌شود).

بررسی‌ها:
1. ZIP باز می‌شود؛ نبود `module.json` = خطای سخت.
2. `module.json` با `ModuleManifestSchema` اعتبارسنجی می‌شود.
3. `module_slug`/`version`/`type` داخل manifest با `module_releases`/`modules` مطابقت داده می‌شود (عدم تطابق = خطای سخت).
4. ساختار پوشه (بخش ۵ پایین) — فقط Warning.
5. هر `dependencies.modules` باید به یک ماژول واقعاً موجود در DB اشاره کند (عدم وجود = خطای سخت؛ این یک چک site-agnostic است — نه اینکه آیا روی یک سایت خاص نصب است).
6. Checksum فایل روی دیسک با `package_sha256` مطابقت دارد.

---

## بخش ۴ — Compatibility Check روی همهٔ مسیرها

`ModuleDeploymentRequestService::deployUnderLock()` حالا برای هر چهار Mode (`install`/`upgrade`/`reinstall`/`retry`) قبل از هر نوشتنی، `PlatformCompatibilityService::check($module->slug, $release->version)` را اجرا می‌کند. اگر ناسازگار بود:
- هیچ ردیف `site_modules` نوشته نمی‌شود.
- هیچ `CustomerInstallation` ساخته نمی‌شود.
- `ActivityLogger::log('module.deployment_incompatible', ...)` ثبت می‌شود.

مسیر Upgrade UI (`SiteModuleUpgradeController` → `ModuleUpdateEligibilityService::isCompatibleRelease`) بدون تغییر باقی ماند — این‌ها دو لایهٔ متفاوت‌اند: یکی eligibility (چه چیزی در فهرست Upgrade نشان داده شود)، دیگری یک Gate اجباری و غیرقابل‌دور‌زدن روی خودِ Deploy.

---

## بخش ۵ — Dependency System در سطح Site

`App\Services\Modules\ModuleDeploymentDependencyResolver` — از `ModuleVersionRegistry::dependencies($slug)` (همان منبع build-time موجود، نه ذخیره‌سازی موازی) وابستگی‌ها را می‌خواند و در برابر `site_modules` همان Site چک می‌کند.

فرمت `dependencies.modules` (backward-compatible — هر ۴ ماژول واقعی امروز آرایهٔ خالی دارند):
```json
"dependencies": { "modules": ["core-users", "core-billing>=1.0"] }
```

قوانین پیاده‌شده:
1. Dependency باید از قبل روی همان Site نصب و فعال باشد — این Resolver هرگز به‌صورت خودکار وابستگی گمشده را نصب نمی‌کند، فقط رد می‌کند.
2. وابستگی از نوع `internal` همیشه «موجود» فرض می‌شود — چون هرگز ردیف `site_modules` نمی‌گیرد (قابلیت پلتفرم است، نه یک add-on مشتری).
3. Circular dependency (با ردیابی زنجیرهٔ resolve فعلی) رد می‌شود.
4. Version constraint (`>=`, `<=`, `>`, `<`, `=`) در برابر `site_modules.installed_version` چک می‌شود.

---

## بخش ۶ — Uninstall Contract (فقط Schema)

فیلد جدید و اختیاری در `module.json`:
```json
"uninstall": { "type": "soft", "cleanup": ["tables", "files", "permissions"] }
```
- `soft` — ماژول غیرفعال می‌شود، داده‌ها می‌مانند (قابل بازگشت).
- `hard` — طبق `cleanup` پاک‌سازی می‌شود (غیرقابل بازگشت).

`SaaSPlatform\CoreUtils\Contracts\Services\ModuleUninstallStrategyInterface` این قرارداد را به‌صورت یک Interface خالی تعریف می‌کند — **هیچ پیاده‌سازی یا فراخوانی‌ای در این فاز وجود ندارد**؛ اجرای واقعی Uninstall به فاز بعدی موکول شده.

---

## بخش ۷ — استاندارد Folder Structure

الزامی: `module.json`، `src/`.
اختیاری (case-insensitive، فقط Warning در نبود): `database`/`Database`، `resources`/`Resources`، `routes`/`Routes`. اگر `install.publishes`/`publish_tags` به یک قابلیت اشاره کند (مثلاً یک tag شامل «migration») ولی پوشهٔ متناظر در ZIP نباشد، فقط Warning ثبت می‌شود — هرگز مانع Deploy نمی‌شود، چون:
- `education`/`tickets` از `Database/Resources/Routes` (PascalCase) استفاده می‌کنند.
- `forms`/`motion-graphics` از `database/resources/routes` (lowercase) استفاده می‌کنند.
یک Validator سخت‌گیر روی نام پوشه همین امروز نیمی از ماژول‌های واقعی را رد می‌کرد.
