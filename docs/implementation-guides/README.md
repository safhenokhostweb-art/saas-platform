# راهنماهای پیاده‌سازی — اکوسیستم لایسنس و فروش

این پوشه شامل راهنماهای گام‌به‌گام برای هر بخش از طراحی Enterprise Licensed Website Builder است.

| شماره | فایل | موضوع |
|-------|------|--------|
| ۱ | [01-license-server-and-validation.md](01-license-server-and-validation.md) | License Server API در پلتفرم و اعتبارسنجی لایسنس در customer-site |
| ۲ | [02-one-time-purchase-and-gateway.md](02-one-time-purchase-and-gateway.md) | خرید یک‌بار، سبد، درگاه زرین‌پال، فاکتور و Fulfillment |
| ۳ | [03-support-as-product.md](03-support-as-product.md) | پشتیبانی به‌عنوان محصول و تمدید اشتراک |
| ۴ | [04-update-server-and-customer-site-check.md](04-update-server-and-customer-site-check.md) | Update Server API و بررسی بروزرسانی در customer-site |
| ۵ | [05-site-config-api.md](05-site-config-api.md) | API تنظیمات/ماژول‌ها برای customer-site |
| ۶ | [06-reseller-system.md](06-reseller-system.md) | سیستم Reseller و کمیسیون |
| ۷ | [07-module-marketplace.md](07-module-marketplace.md) | Module Marketplace و تحویل ماژول |
| ۸ | [08-white-label-and-advanced-reseller.md](08-white-label-and-advanced-reseller.md) | White-label و امکانات پیشرفته Reseller |
| ۹ | [09-license-flow-and-module-deployment.md](09-license-flow-and-module-deployment.md) | جریان لایسنس، بارگذاری کد ماژول‌ها، پشتیبانی رایگان و فروش ماژول |
| ۱۰ | [10-module-synchronization.md](10-module-synchronization.md) | همگام‌سازی مرکزی ماژول‌ها بین پنل اصلی و سایت‌های مشتری |

## ترتیب اجرا

1. مایگریشن‌های پلتفرم را اجرا کنید: `php artisan migrate` (در مسیر saas-platform).
2. مایگریشن‌های customer-site را اجرا کنید در مسیر customer-site.
3. در پلتفرم: ادمین → سایت‌ها → لایسنس برای صدور کلید؛ ادمین → محصولات برای تعریف محصولات.
4. در customer-site: در `.env` مقدار `PLATFORM_URL` را تنظیم کنید و از پنل لایسنس، کلید را فعال‌سازی کنید.

## فهرست فایل‌های اضافه‌شده (خلاصه)

- **پلتفرم:** مایگریشن‌های `platform_licenses`, `products`, `invoices`, `invoice_items`, `resellers`, `reseller_commissions`؛ مدل‌ها و کنترلرهای API (License, Update, SiteConfig)؛ سرویس‌های LicenseServer، Cart، Zarinpal، OrderFulfillment؛ کنترلرهای Store، Checkout، Admin Product، SiteLicense؛ فرم مشتری با فیلد نماینده.
- **customer-site:** مایگریشن فیلدهای `modules` و `support_expires_at` در licenses؛ سرویس‌های LicenseActivation، UpdateCheck؛ به‌روزرسانی کنترلر و ویو لایسنس؛ دکمهٔ «بررسی بروزرسانی».
