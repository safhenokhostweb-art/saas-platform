# همگام‌سازی قوانین Cursor بین پلتفرم و سایت مشتری

سایت مشتری ممکن است روی **هاست یا ماشین دیگر** باشد؛ این مخزن‌ها لزوماً کنار هم روی دیسک نیستند. اسکریپت و hook طوری طراحی شده‌اند که مسیر مخزن مقصد را از **متغیر محیطی** یا **فایل محلی** بگیرند (استاندارد: تنظیم خارج از کد، بدون commit کردن مسیرهای خصوصی).

## ترتیب تشخیص مسیر مخزن مقابل

### از داخل saas-platform (`scripts/sync-cursor-rules.ps1`)

1. متغیر محیطی **`CURSOR_RULES_CUSTOMER_SITE_ROOT`** (مسیر مطلق clone سایت مشتری روی همان ماشینی که اسکریپت اجرا می‌شود)
2. فایل **`cursor-rules-sync.local.json`** در ریشهٔ همین مخزن، کلید `customerSiteRoot`
3. پیش‌فرض توسعهٔ محلی: پوشهٔ خواهر **`../customer-site`** کنار `saas-platform`

### از داخل customer-site (`scripts/sync-cursor-rules.ps1`)

1. **`CURSOR_RULES_SAAS_PLATFORM_ROOT`**
2. **`cursor-rules-sync.local.json`** در ریشهٔ همین مخزن، کلید `saasPlatformRoot`
3. پیش‌فرض: **`../saas-platform`**

فایل نمونه (قابل commit): `cursor-rules-sync.local.example.json` — یک کپی با نام `cursor-rules-sync.local.json` بسازید (در `.gitignore` است).

## منبع حقیقت پیش‌فرض

- جریان عادی: **saas-platform → customer-site**
- برگرداندن تغییرات فقط از سمت مشتری: **`composer sync-cursor-rules:reverse`** (در مخزن customer-site)

## فعال‌سازی Git hook (هر مخزن جدا)

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-cursor-rules-hook.ps1
```

| مخزن | پس از commit روی `.cursor/rules/*.mdc` |
|------|----------------------------------------|
| **saas-platform** | کپی به مسیر حل‌شدهٔ customer-site |
| **customer-site** | کپی به saas-platform (`-Reverse`) |

برای hook، بهتر است **`cursor-rules-sync.local.json`** روی همان ماشین تنظیم شود تا وابسته به متغیر محیطی در ترمینال نباشد.

## دو سرور جدا (بدون دسترسی فایل مشترک)

اسکریپت PowerShell فقط وقتی کار می‌کند که **هر دو مسیر روی یک سیستم فایل** در دسترس باشند (مثلاً لپ‌تاپ توسعه‌دهنده با دو clone، یا VPN به درایو شبکه).

اگر پلتفرم و سایت مشتری روی سرورهای جدا هستند:

- قوانین را در **CI/CD** از مخزن canonical (معمولاً همان جایی که `saas-platform` merge می‌شود) به آرتیفکت/Repo مشتری **کپی** کنید، یا
- با `scp`/`rsync` فقط پوشهٔ `.cursor/rules/*.mdc` را منتقل کنید.

Cursor همچنان فقط قوانین **همان پروژهٔ باز** را می‌خواند؛ همگام‌سازی بین سرورها بخشی از فرآیند deploy/Repo است، نه خود IDE.

## دستور Composer

```powershell
composer sync-cursor-rules
composer sync-cursor-rules:reverse
```

## مستندات مرتبط

- `docs/PROMPT_FOR_CURSOR.md`
- `docs/CHANGELOG.md`
