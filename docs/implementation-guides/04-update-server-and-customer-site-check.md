# راهنمای بخش ۴: Update Server API و بررسی بروزرسانی در customer-site

## هدف

- **پلتفرم:** API برای اعلام آخرین نسخهٔ توزیع‌شده برای هر سایت (بر اساس لایسنس).
- **customer-site:** در پنل ادمین امکان «بررسی بروزرسانی» و نمایش نسخهٔ جدید در صورت وجود.

## ۱. API سرور بروزرسانی (پلتفرم)

- **روت:** `GET /api/updates/check?product=site_template&current_version=1.0.0&license_key=...&domain=...`
- **منطق:** اعتبار لایسنس با `LicenseServerService::validate()`؛ سپس **بررسی دورهٔ پشتیبانی:** دریافت بروزرسانی فقط زمانی مجاز است که `support_expires_at` سایت مقدار داشته باشد و در آینده باشد؛ در غیر این صورت پاسخ با `allowed: false` و `error: support_expired` (۴۰۳) برگردانده می‌شود. در صورت فعال بودن پشتیبانی، آخرین `SiteUpdate` برای سایت آن لایسنس برگردانده می‌شود؛ پاسخ شامل `latest_version`, `update_available`, `changelog`, `distributed_at`.
- **محدودیت:** `throttle:30,1`.

## ۲. پیاده‌سازی در customer-site

- سرویس `UpdateCheckService` با متد `check($currentVersion)` که به `{PLATFORM_URL}/api/updates/check` درخواست می‌زند.
- در صفحهٔ لایسنس یا داشبورد دکمهٔ «بررسی بروزرسانی»؛ در صورت `update_available` نمایش نسخهٔ جدید و لینک راهنما.
- نسخهٔ فعلی در `config/app.php` یا فایل `version.php` تعریف شود.

## ۳. توزیع از پلتفرم

ادمین در سایت → توزیع بروزرسانی رکورد `SiteUpdate` ثبت می‌کند؛ همان به‌عنوان آخرین نسخه در API برمی‌گردد.
