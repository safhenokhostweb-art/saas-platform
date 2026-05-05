# معماری Tenant و جداسازی داده

> **شناسایی tenant از دامنه/زیردامنه و جداسازی داده بین tenantها.**

---

## ۱. انواع Tenant

### saas-platform (پلتفرم)

| نوع | منبع شناسایی | مدل |
|-----|--------------|-----|
| **Reseller** | دامنهٔ اختصاصی (`custom_domain`) یا زیردامنه (`subdomain.platform.com`) | `Reseller` |
| **Reseller** | کاربر احراز هویت‌شده در مسیر `/reseller/*` | `Reseller` (از `user()->reseller`) |
| **Site** | پارامتر مسیر در ادمین (`/admin/sites/{site}`) یا API (`license_key` + `domain`) | `Site` |

### customer-site (سایت مشتری)

| نوع | منبع شناسایی | مدل |
|-----|--------------|-----|
| **Site** | `License::current()` با تطابق دامنه | `License` |

هر نصب customer-site یک tenant است؛ داده به‌طور طبیعی با دیتابیس جدا ایزوله است.

---

## ۲. سرویس‌ها و Middleware

### TenantContext (پلتفرم)

- **مسیر:** `app/Services/TenantContext.php`
- **متدها:** `current()`, `reseller()`, `site()`, `type()`, `hasTenant()`, `scopeQueryToTenant()`
- **استفاده:** `app(TenantContext::class)->current()`

### TenantResolver (پلتفرم)

- **مسیر:** `app/Services/TenantResolver.php`
- **وظیفه:** تشخیص Reseller از host (دامنه/زیردامنه)

### SetTenantContext (هر دو پروژه)

- **پلتفرم:** بعد از `DetectResellerFromDomain` اجرا می‌شود؛ tenant را از دامنه تنظیم می‌کند.
- **customer-site:** tenant را از `License::current()` تنظیم می‌کند (فقط پس از نصب).

### EnsureUserIsReseller

- هنگام ورود کاربر Reseller، `TenantContext` را با Reseller او به‌روز می‌کند.

---

## ۳. جداسازی داده

### مدل‌های وابسته به Reseller

- `Customer` (reseller_id)
- `ResellerCommission` (reseller_id)
- `PayoutRequest` (reseller_id)

### Traitها

- **BelongsToResellerTenant:** `scopeForCurrentTenant()` برای محدود کردن کوئری به tenant فعلی.
- **BelongsToSiteTenant:** `scopeForSite($site)` برای محدود کردن کوئری به site مشخص.

### مثال

```php
// در کنترلر Reseller
$invoices = Invoice::whereIn('customer_id', $reseller->customers->pluck('id'))->get();

// با trait (اختیاری)
$commissions = ResellerCommission::forCurrentTenant()->get();
```

---

## ۴. پیکربندی

- **config/tenant.php:** `ignore_subdomains`, `reseller_from_domain`

---

## ۵. نقش‌ها و احراز هویت (Authorization)

- **AuthorizationService** (`app/Services/AuthorizationService.php`): `can()`, `canAccessSite()`, `canAccessAdmin()`, `passesTenantCheck()`
- **Super Admin:** دسترسی کامل؛ tenant check را bypass می‌کند.
- **Staff:** دسترسی بر اساس permission؛ tenant check ندارد (کل پلتفرم).
- **Reseller:** فقط دادهٔ tenant خودش؛ `passesTenantCheck()` اطمینان می‌دهد `TenantContext::reseller()` با `user->reseller` یکی است.
- **Gate `access-site`:** برای بررسی دسترسی به یک سایت خاص؛ Reseller فقط سایت‌های مشتریان خودش را می‌بیند.

---

## ۶. سازگاری با گذشته

- `DetectResellerFromDomain` و `ResellerBranding` بدون تغییر کار می‌کنند.
- کنترلرهای موجود از `$request->attributes->get('current_reseller')` استفاده می‌کنند؛ `TenantContext` همان مقدار را برمی‌گرداند.
- هیچ منطق کسب‌وکاری تغییر نکرده است.

---

*آخرین بروزرسانی: ۱۴۰۴/۱۲/۰۷ — افزودن بخش نقش‌ها و AuthorizationService*
