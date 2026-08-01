# CRM — Installation Guide (Developers / Platform Admins)

## نصب از طریق Module Ecosystem (توصیه‌شده)

```bash
php artisan module:validate crm
php artisan module:release crm-suite --type=minor   # مثلاً 1.0.0 -> 1.1.0 برای این فاز
php artisan module:package-build crm-suite <version>
php artisan module:publish crm-suite <version>
```

پس از publish، نصب واقعی روی سایت مشتری از طریق `ModuleAssignmentService::activatePaidPurchase()` و `CustomerInstallationPipelineJob` به‌صورت خودکار انجام می‌شود — بدون هیچ دستور دستی.

## نصب برای توسعه (Local/Dev)

```bash
php artisan migrate --path=packages/saas-platform/modules/crm/database/migrations
```

## اعتبارسنجی

```bash
php artisan module:validate crm
php artisan crm:validate-isolation
```

## زمان‌بندی (Scheduler)

یادآوری وظایف (Part 6) به‌صورت خودکار هر ساعت اجرا می‌شود (`app/Console/Kernel.php` → `crm:send-task-reminders`) — نیازی به تنظیم دستی نیست، فقط اطمینان حاصل کنید Laravel Scheduler روی سرور فعال است (`* * * * * php artisan schedule:run`).

## اتصال به ماژول‌های دیگر (اختیاری)

اگر ماژول‌های Forms و/یا Tickets نصب باشند، CRM به‌صورت خودکار (بدون تنظیم اضافی) به رویدادهای آن‌ها گوش می‌دهد:
- **Forms**: هر ارسال فرم سایت‌محور که ایمیل یا تلفن داشته باشد → Lead جدید.
- **Tickets**: هر تیکت با اولویت «بالا» که با یک مخاطب موجود (بر اساس شماره موبایل) مطابقت داشته باشد → Activity روی همان مخاطب.

هیچ‌کدام از این دو ماژول نیازی به نصب CRM ندارند و بدون آن هم کاملاً عادی کار می‌کنند.
