# SEO Advanced — Audit Engine Rules

مرجع فنی قوانین موتور ارزیابی (`SeoAuditor`). این موتور **کاملاً قطعی (deterministic)** است — هیچ فراخوانی به سرویس خارجی یا crawler واقعی انجام نمی‌دهد؛ تمام بررسی‌ها بر اساس مقادیری است که صریحاً به آن پاس داده می‌شود.

## امتیازدهی

امتیاز پایه: `100`. هر مشکل بر اساس severity از امتیاز کم می‌کند:

| Severity | کسر امتیاز |
|---|---|
| `critical` | ۲۰ |
| `warning` | ۱۰ |
| `recommendation` | ۵ |

(موارد میراثی Title/Description/Heading/Canonical/Alt همچنان از فرمول اصلی Phase 18 پیروی می‌کنند — هر مورد در `missing_meta`: ۲۰ امتیاز، هر Alt گمشده: ۵ امتیاز — تا سازگاری کامل با نسخه‌های قبلی حفظ شود.)

## بررسی‌های Page-Level

| بررسی | شرط فعال‌سازی | Severity |
|---|---|---|
| Title موجود است | همیشه | critical (در صورت نبود) |
| طول Title در بازه مجاز | همیشه | warning |
| Description موجود است | همیشه | critical (در صورت نبود) |
| طول Description در بازه مجاز | همیشه | warning |
| H1 (heading) خالی نیست | فقط اگر `$heading` صراحتاً پاس داده شود | critical |
| Canonical خالی نیست | فقط اگر `$canonical` صراحتاً پاس داده شود | warning |
| Alt تصاویر | برای هر `$imageAltTexts` خالی/null | recommendation |
| سلسله‌مراتب تیترها (دقیقاً یک H1، بدون پرش سطح) | فقط اگر `$headings` (آرایه کامل) پاس داده شود | critical (بدون H1) / warning (چند H1) / recommendation (پرش سطح) |
| کامل بودن OpenGraph (title/description/image) | فقط اگر `$og` پاس داده شود | warning |
| ساختار URL (حروف کوچک، بدون `_`/فاصله، طول ≤ ۷۵) | فقط اگر `$urlPath` پاس داده شود | recommendation |

> **آستانه‌های طول Title/Description** پیش‌فرض ۳۰-۶۰ و ۷۰-۱۶۰ کاراکتر است؛ اگر سایت مقدار اختصاصی در `SeoAdvancedSetting` تنظیم کرده باشد (`title_min_length` و…)، همان مقدار جایگزین پیش‌فرض می‌شود.

## بررسی‌های Site-Level (Technical)

توسط `technicalChecks(siteId)` محاسبه می‌شود و در `auditAndStore()` با کسر ۱۰ امتیاز به‌ازای هر مورد ناموفق اعمال می‌گردد:

- `robots_configured` — آیا `robots_directive` روی تنظیمات سایت مقداردهی شده؟
- `schema_configured` — آیا `schema_type` مقداردهی شده؟
- `sitemap_available` — همیشه true (خود مسیر sitemap با نصب ماژول همیشه فعال است).

## چرا این طراحی؟

هر بررسی جدید (سلسله‌مراتب تیترها، OpenGraph، ساختار URL) **کاملاً اختیاری/افزایشی (opt-in)** طراحی شده — اگر فراخواننده پارامتر مربوطه را پاس ندهد، آن بررسی اصلاً اجرا نمی‌شود و امتیاز/رفتار قبلی بدون تغییر می‌ماند. این یعنی هر Call Site موجود از فازهای قبلی (Phase 18/18-C)، بدون هیچ تغییری، دقیقاً همان رفتار قبلی را دارد.
