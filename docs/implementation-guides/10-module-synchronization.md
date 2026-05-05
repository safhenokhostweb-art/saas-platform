# Phase 13: Centralized Module Synchronization

> **هدف:** ماژول‌های تعریف‌شده در پنل اصلی (Main Admin) باید در سایت‌های مشتری (Tenant) با همان نام، ساختار و قابلیت‌ها نمایش داده شوند.

---

## ۱. وضعیت فعلی (Current State)

### Main Admin (saas-platform)

| منبع | توضیح |
|------|--------|
| `modules` | جدول: `slug`, `title`, `description`, `is_active`, `version`, `type` (core/paid/internal) |
| `module_features` | فیچرهای هر ماژول: `module_id`, `key`, `title`, `is_active` |
| `module_labels` | برچسب سفارشی per-site برای منو (site_id nullable = پلتفرم) |
| `site_modules` | pivot سایت–ماژول: `is_active`, `installed_version`, `activated_at` |
| `site_module_features` | فعال/غیرفعال فیچرها per-site (از طریق `site_module_id`) |
| `site_module_purchases` | مالکیت ماژول‌های PAID |

**APIهای فعلی:**
- `POST /api/license/validate` → `modules`: آرایهٔ **فقط slug**ها
- `GET /api/site-config` → `modules`: آرایهٔ **فقط slug**ها

**سرویس‌ها:**
- `ModuleAssignmentService::getEnabledSlugsForSite()`: فقط slugهای core/paid با مالکیت و فعال
- `Module::scopeCustomerFacing()`: فقط core و paid؛ **internal هرگز**

### Tenant (customer-site)

| منبع | توضیح |
|------|--------|
| `config/modules.php` | تعریف **محلی** هر ماژول: `name`, `env`, `default` — **نام‌ها تکراری و مستقل** |
| `License` | ذخیرهٔ `modules` از API (فقط slugها) |
| `SiteHelper::moduleEnabled()` | از slugها لایسنس یا fallback به .env |
| `EnsureModuleEnabled` | middleware برای مسیرهای ماژول |

**مشکل:** نام نمایشی ماژول‌ها از `config/modules.php` می‌آید و می‌تواند با پلتفرم متفاوت باشد.

---

## ۲. مکانیزم همگام‌سازی پیشنهادی

### اصل: Single Source of Truth

- **تعریف ماژول** (slug, title, description, type, features) **فقط** در Main Admin است.
- Tenant **هیچ تعریف مستقلی** برای metadata ماژول ندارد؛ همه از API می‌گیرد.
- Tenant فقط `config/modules.php` را برای **ساختار کد** (env key، default) نگه می‌دارد؛ **نام** از پلتفرم می‌آید.

### جریان داده

```
[Main Admin]                    [Tenant]
     │                               │
     │  modules (DB)                 │
     │  module_features              │
     │  site_modules                 │
     │  site_module_features         │
     │  site_module_purchases        │
     │                               │
     │  GET /api/site-config         │
     │  ─────────────────────────────>
     │  modules: [                   │
     │    {slug, title, description, │
     │     features: [{key, title}]} │
     │  ]                            │
     │  <─────────────────────────────
     │                               │  PlatformUxService
     │                               │  → cache
     │                               │  → نمایش در UI
```

### تغییرات API

**پاسخ `GET /api/site-config`** فعلی:

```json
{
  "allowed": true,
  "modules": ["blog", "shop", "forms"],
  ...
}
```

**پاسخ پیشنهادی (سازگار با قبل):**

```json
{
  "allowed": true,
  "modules": ["blog", "shop", "forms"],
  "modules_meta": [
    {
      "slug": "blog",
      "title": "وبلاگ",
      "description": "مدیریت مطالب و دسته‌بندی"
    },
    {
      "slug": "shop",
      "title": "فروشگاه",
      "description": "محصولات و سفارشات"
    },
    ...
  ],
  ...
}
```

- `modules`: آرایهٔ slugها (همان قبل برای سازگاری با `License::modules` و `SiteHelper::moduleEnabled`)
- `modules_meta`: فقط برای ماژول‌های **assign شده** به این سایت؛ **internal** هرگز در آن نیست.

### تغییرات Tenant

- `PlatformUxService` / `getSiteConfig()`: `modules_meta` را در کش ذخیره کند.
- Tenant برای **نام نمایشی** از `modules_meta` استفاده کند؛ fallback به `config/modules.php` وقتی `modules_meta` خالی یا ناقص است.

---

## ۳. کنترل دسترسی (Access Control)

### الزامات

| شرط | وضعیت فعلی | اقدام |
|-----|------------|-------|
| Purchase status | ✅ `ModuleAssignmentService` + `ModulePurchaseService` | بدون تغییر |
| License validity | ✅ `LicenseServerService::validate()` | بدون تغییر |
| Activation status | ✅ `site_modules.is_active` | بدون تغییر |
| Support eligibility | ✅ برای updates؛ برای دسترسی ماژول: خیر | بدون تغییر (دسترسی ماژول از لایسنس) |

### Internal modules

- `Module::scopeCustomerFacing()` فقط core و paid؛ **internal هرگز** در خروجی API نیست.
- `site-config` و `license/validate` فقط از `getEnabledSlugsForSite()` استفاده می‌کنند که از `whereIn('type', [core, paid])` استفاده می‌کند.

### امنیت مسیرها

- `EnsureModuleEnabled` middleware: فقط `SiteHelper::moduleEnabled($slug)` را چک می‌کند؛ منبع آن `License::modules` است که از API پر می‌شود.
- با حفظ این flow، دسترسی مستقیم به مسیرهای ماژول غیرمجاز بدون تغییر درست کار می‌کند.

---

## ۴. لایهٔ Configuration

### Tenant-specific settings

- **جدول `site_settings`** (در پلتفرم): تنظیمات tenant-level مثل SEO، لوگو، رنگ.
- **جدول `site_module_features`**: فعال/غیرفعال کردن فیچرها per-site.
- **جدول `module_labels`** (site_id = tenant): برچسب سفارشی برای منو.

### تفکیک

- **کد ماژول:** در customer-site تغییر نمی‌کند.
- **تنظیمات:** فقط در `site_settings`، `site_module_features`، `module_labels`.
- **داده:** جداول tenant (posts، products، …) دست نخورده می‌مانند.

---

## ۵. همگام‌سازی UI

- **منوی کناری:** فقط ماژول‌های دارای slug در `license->modules` نمایش داده شوند.
- **صفحهٔ ماژول‌ها:** نام از `modules_meta` یا fallback به config.
- **نام‌های ناهماهنگ:** با `modules_meta` از پلتفرم یکسان می‌شوند.

---

## ۶. تغییرات دیتابیس

**نیاز به تغییر دیتابیس نیست.**

- `modules` و `module_features` از قبل وجود دارند.
- `site_module_features` و `module_labels` برای tenant-level config موجود هستند.
- فقط خروجی API گسترش می‌یابد.

---

## ۷. طرح پیاده‌سازی (Implementation Plan)

### مرحله ۱: Main Admin — گسترش site-config API

**فایل:** `app/Http/Controllers/Api/SiteConfigController.php`

1. بعد از `$result['modules']`، برای هر slug در `$result['modules']`، از `Module::whereIn('slug', $slugs)->whereIn('type', [core, paid])` رکوردهای ماژول را بخوان.
2. برای هر ماژول: `slug`, `title`, `description` را برگردان.
3. `modules_meta` را به payload اضافه کن.
4. اطمینان از عدم ارسال `internal` modules.

---

### مرحله ۲: Tenant — استفاده از modules_meta

**فایل:** `app/Services/PlatformUxService.php` (customer-site)

1. `getSiteConfig()` از قبل `modules` و `modules_meta` را در کش ذخیره می‌کند (خروجی API).
2. متد جدید: `getModuleMetadata(): array` — `modules_meta` از کش یا `[]`.

**فایل:** `app/Helpers/Module.php` (customer-site)

1. متد `getDisplayName(string $slug): string`:
   - اگر `modules_meta` برای این slug موجود است → `title` از آن
   - وگرنه → `config('modules.modules.' . $slug . '.name', $slug)`

**فایل‌های view:** جایی که نام ماژول نمایش داده می‌شود (مثلاً `admin/modules/index.blade.php`، `admin/dashboard.blade.php`)

1. به‌جای `$info['name']` از `Module::getDisplayName($slug)` یا `PlatformUx::getModuleTitle($slug)` استفاده کنید.

---

### مرحله ۳: Fallback برای site مالک و حالت بدون لایسنس

- `site.owner_site = true` و بدون لایسنس: `modules_meta` خالی یا null → fallback به config.
- لایسنس نامعتبر: همان fallback.

---

### مرحله ۴: (اختیاری) فیچرهای ماژول

- اگر لازم باشد tenant بداند کدام فیچرها فعال‌اند، می‌توان `features` را به هر آیتم در `modules_meta` اضافه کرد:
  - از `Site::getEnabledModuleFeatureIds()` و `module_features` برای ساخت لیست فیچرهای فعال استفاده شود.
- برای Phase ۱۳ فعلی می‌توان این بخش را حذف کرد تا تغییرات کوچک‌تر بمانند.

---

## ۸. چک‌لیست سازگاری

- [x] استفاده از `ModuleAssignmentService` و `ModulePurchaseService` بدون تغییر
- [x] استفاده از `LicenseServerService::validate()` بدون تغییر
- [x] استفاده از `EnsureModuleEnabled` بدون تغییر
- [x] عدم تغییر ساختار `License` یا `config/modules.php` (فقط نحوهٔ استفاده از نام)
- [x] عدم تغییر داده‌های tenant
- [x] backward compatibility: `modules` همچنان آرایهٔ slugها

---

## ۹. خلاصه‌ی تغییرات

| پروژه | فایل | تغییر |
|-------|------|-------|
| saas-platform | `Api/SiteConfigController.php` | افزودن `modules_meta` به پاسخ |
| customer-site | `PlatformUxService.php` | خواندن و کش `modules_meta` |
| customer-site | `Module.php` یا helper جدید | `getDisplayName($slug)` با fallback |
| customer-site | views (modules، dashboard) | استفاده از نام از پلتفرم |

**بدون:** مایگریشن، تغییر در جداول، refactor غیرمجاز.
