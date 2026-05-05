# راهنمای بخش ۱: License Server API و اعتبارسنجی لایسنس در customer-site

## هدف

- **پلتفرم (saas-platform):** ارائهٔ API برای اعتبارسنجی لایسنس و امکان صدور لایسنس از پنل ادمین برای هر سایت.
- **customer-site:** هنگام ثبت کلید لایسنس، اعتبار را از طریق API پلتفرم بررسی کند و در صورت معتبر بودن، ماژول‌ها و تاریخ انقضای پشتیبانی را ذخیره کند.

---

## ۱. اجزای پیاده‌سازی در پلتفرم (saas-platform)

### ۱.۱ جدول لایسنس‌ها

- **فایل مایگریشن:** `database/migrations/2026_02_26_140000_create_platform_licenses_table.php`
- جدول `platform_licenses` با ستون‌ها:
  - `site_id` — ارتباط با سایت
  - `license_key_prefix` — ۸ کاراکتر اول کلید (برای جستجو، بدون ذخیرهٔ کلید کامل)
  - `license_key_hash` — SHA-256 کلید کامل
  - `domain_allowed` — دامنهٔ مجاز (خالی = هر دامنه)
  - `expires_at` — تاریخ انقضا (اختیاری)
  - `product_sku` — شناسهٔ محصول (مثلاً site_template)
  - `issued_at`، `revoked_at`

**اجرا:**

```bash
cd /path/to/saas-platform
php artisan migrate
```

### ۱.۲ مدل و سرویس

- **مدل:** `app/Models/PlatformLicense.php`  
  ارتباط با `Site`، متدهای `isRevoked()`, `isExpired()`, `isDomainAllowed($domain)`.

- **سرویس:** `app/Services/LicenseServerService.php`  
  - `issue(Site $site, $domain, $expiresAt, $productSku)` — تولید کلید، ذخیره در DB، برگرداندن کلید (فقط یک‌بار).
  - `validate($licenseKey, $domain)` — جستجو با prefix و hash، بررسی دامنه و انقضا و وضعیت سایت؛ خروجی شامل `valid`, `modules`, `support_expires_at`.

### ۱.۳ API

- **کنترلر:** `app/Http/Controllers/Api/LicenseController.php`
- **روت:** `POST /api/license/validate` با بدنهٔ JSON:
  - `license_key` (الزامی)
  - `domain` (الزامی)

  پاسخ در صورت معتبر:  
  `{ "valid": true, "expires_at": "...", "support_expires_at": "...", "modules": ["blog","shop"], "site_title": "..." }`  
  در صورت نامعتبر:  
  `{ "valid": false, "error": "invalid_key|revoked|expired|domain_mismatch|site_inactive" }`

- **محدودیت درخواست:** `throttle:60,1` (۶۰ درخواست در دقیقه).

### ۱.۴ صدور لایسنس از پنل ادمین

- **کنترلر:** `app/Http/Controllers/Admin/SiteLicenseController.php`
  - `create(site)` — فرم صدور (دامنه، تاریخ انقضا)
  - `store(request, site)` — صدور لایسنس و ریدایرکت به صفحهٔ نمایش یک‌بارهٔ کلید
  - `show(site)` — نمایش کلید از session و پاک کردن آن

- **روت‌ها (داخل گروه ادمین):**
  - `GET admin/sites/{site}/license` — فرم صدور
  - `POST admin/sites/{site}/license` — صدور
  - `GET admin/sites/{site}/license/show` — نمایش کلید

- **ویوها:**
  - `resources/views/admin/sites/license-create.blade.php`
  - `resources/views/admin/sites/license-show.blade.php`

- در لیست سایت‌ها لینک **«لایسنس»** به صفحهٔ صدور لایسنس اضافه شده است.

---

## ۲. اجزای پیاده‌سازی در customer-site

### ۲.۱ فیلدهای اضافه در جدول لایسنس

- **مایگریشن:** `database/migrations/2026_02_26_150000_add_remote_fields_to_licenses_table.php`
  - `modules` (JSON، nullable)
  - `support_expires_at` (timestamp، nullable)

```bash
cd /path/to/customer-site
php artisan migrate
```

### ۲.۲ تنظیمات

- در `config/site.php` مقدار `platform_url` از env خوانده می‌شود.
- در `.env` سایت مشتری حتماً آدرس پلتفرم را تنظیم کنید:

```env
PLATFORM_URL=https://your-platform-domain.com
```

### ۲.۳ سرویس اعتبارسنجی

- **فایل:** `app/Services/LicenseActivationService.php`
- متد `validate($licenseKey, $domain)`:
  - درخواست `POST` به `{PLATFORM_URL}/api/license/validate`
  - در صورت موفق بودن پاسخ، آرایهٔ خروجی پلتفرم (شامل `valid`, `modules`, `support_expires_at`, …) برگردانده می‌شود.
  - در صورت خطا، آرایه با `valid => false` و `error` و `message` فارسی برگردانده می‌شود.

### ۲.۴ کنترلر و ویو لایسنس

- **کنترلر:** `app/Http/Controllers/Admin/LicenseController.php`
  - در `update()` ابتدا `LicenseActivationService::validate()` فراخوانی می‌شود.
  - اگر `valid` باشد، یک رکورد لایسنس (جدید یا به‌روزرسانی) با `license_key`, `domain`, `expires_at`, `modules`, `support_expires_at` ذخیره می‌شود.
  - اگر نامعتبر باشد، با پیام خطا به همان صفحه برگردانده می‌شود.

- **مدل License:** فیلدهای `modules` و `support_expires_at` به `fillable` و `casts` اضافه شده‌اند.

- **ویو:** `resources/views/admin/licenses/index.blade.php`  
  نمایش وضعیت، انقضای پشتیبانی و ماژول‌های فعال و فرم فعال‌سازی با ارسال به `admin.license.update`.

---

## ۳. سناریوی استفاده

1. **در پلتفرم:** ادمین از منوی سایت‌ها → انتخاب سایت → «لایسنس» → «صدور لایسنس» را می‌زند، دامنه (اختیاری) و انقضا (اختیاری) را وارد و تأیید می‌کند. کلید یک‌بار نمایش داده می‌شود؛ آن را کپی و به مشتری می‌دهد.
2. **در customer-site:** مشتری در پنل ادمین → لایسنس، کلید و در صورت نیاز دامنه را وارد و «فعال‌سازی لایسنس» را می‌زند. درخواست به API پلتفرم ارسال می‌شود؛ در صورت معتبر بودن، لایسنس محلی به‌همراه ماژول‌ها و تاریخ پشتیبانی ذخیره می‌شود.

---

## ۴. امنیت

- کلید کامل در پلتفرم ذخیره نمی‌شود؛ فقط prefix و hash.
- API بدون احراز هویت است (فقط با ارسال کلید و دامنه)؛ با throttle از سوءاستفادهٔ خشن جلوگیری شده است.
- در customer-site کلید در فرم فقط با `old('license_key')` پر می‌شود و پس از ذخیره، مقدار کامل از روی فرم نمایش داده نمی‌شود (نمایش ماسک‌شده با `maskedKey()`).

---

## ۵. عیب‌یابی

- **خطای «آدرس پلتفرم تنظیم نشده»:** در customer-site مقدار `PLATFORM_URL` را در `.env` تنظیم کنید.
- **خطای ارتباط با سرور:** از customer-site به آدرس `PLATFORM_URL` و مسیر `/api/license/validate` دسترسی شبکه (و در صورت نیاز CORS) را بررسی کنید. در پلتفرم لاگ‌های مربوط به API و throttle را چک کنید.
- **دامنه نامطابقت:** در پلتفرم هنگام صدور لایسنس، دامنهٔ مجاز را مطابق دامنهٔ واقعی سایت مشتری وارد کنید، یا خالی بگذارید تا هر دامنه قبول شود.
