# DATE LOCALIZATION FIX REPORT
**Date:** 1404/03/30 (2026-06-20)
**Status:** DATE_LOCALIZATION_CERTIFIED

---

## NEW CODE ADDED

### 1. `Jalali::diffForHumansFaStatic()` — Persian relative time

**File:** `packages/saas-platform/core-utils/src/Support/Jalali.php`
**Proxy:** `app/Helpers/Jalali.php` → `Jalali::diffForHumansFa($date)`

Replaces Carbon's `diffForHumans()` with fully Persian output:
```
همین الان | ۳ دقیقه پیش | ۲ ساعت پیش | دیروز | ۵ روز پیش | ۲ ماه پیش | ۱ سال پیش
```

---

## FIXES APPLIED

### saas-platform — Controllers

| File | Change |
|------|--------|
| `app/Http/Controllers/Admin/AdminNotificationController.php:34` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `app/Http/Controllers/Admin/CustomerController.php:158` | `->format('Y-m-d H:i')` → `Jalali::formatFa(, 'Y/m/d H:i')` |
| `app/Http/Controllers/Admin/UserController.php:233` | `->format('Y-m-d H:i')` → `Jalali::formatFa(, 'Y/m/d H:i')` |

### saas-platform — Services

| File | Change |
|------|--------|
| `app/Services/ExpirySmsService.php:40` | `Jalali::format()` → `Jalali::formatFa()` |
| `app/Services/ExpiryReminderService.php:90` | `Jalali::format()` → `Jalali::formatFa()` |

### saas-platform — Blade Display Templates

| File | Change |
|------|--------|
| `views/admin/support-tickets/show.blade.php` (4 instances) | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/support-tickets/index.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/support-tickets/_legacy/show.blade.php` (4 instances) | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/support-tickets/_legacy/index.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/licenses/index.blade.php` (2 instances) | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/sites/updates.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/updates/index.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/payout-requests/index.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/customer/support-tickets/show.blade.php` (3 instances) | `Jalali::format()` → `Jalali::formatFa()` |
| `views/customer/support-tickets/index.blade.php` | `Jalali::format()` → `Jalali::formatFa()` |
| `views/admin/reseller-commissions/index.blade.php` | `->format('Y/m/d')` → `Jalali::formatFa(, 'Y/m/d')` |
| `views/admin/resource-governance/site.blade.php` | `->format('Y-m-d')` → `Jalali::formatFa(, 'Y/m/d')` |
| `views/admin/theme-builder/index.blade.php` | `->format('Y/m/d H:i')` → `Jalali::formatFa(, 'Y/m/d H:i')` |
| `views/admin/sites/index.blade.php` | `->format('Y/m/d')` → `Jalali::formatFa(, 'Y/m/d')` |
| `views/admin/theme-marketplace/licenses.blade.php` (2 instances) | `->format('Y/m/d')` → `Jalali::formatFa(, 'Y/m/d')` |
| `views/admin/incidents/show.blade.php` (6 instances) | `NumberHelper::toPersianNumbers(->format())` → `Jalali::formatFa()` |
| `views/admin/incidents/index.blade.php` | `NumberHelper::toPersianNumbers(->format())` → `Jalali::formatFa()` |
| `views/admin/infrastructure/monitoring/site.blade.php` (2 instances) | `NumberHelper::...format()` + `diffForHumans()` → Jalali |
| `views/admin/infrastructure/monitoring/index.blade.php` (2 instances) | `NumberHelper::...format()` + `diffForHumans()` → Jalali |
| 16× `views/admin/*/guide.blade.php` | `NumberHelper::toPersianNumbers(->format('Y/m/d'))` → `Jalali::formatFa(, 'Y/m/d')` |
| `views/admin/app-releases/index.blade.php` (2 instances) | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/infrastructure/monitoring/site.blade.php` (3 instances) | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/infrastructure/monitoring/index.blade.php` (4 instances) | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/infrastructure/sms/index.blade.php` (2 instances) | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/notification-center/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/module-adoption/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/system-alerts/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/resource-governance/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/resource-governance/site.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/kb/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `views/admin/infrastructure/remote-agents/index.blade.php` | `diffForHumans()` → `Jalali::diffForHumansFa()` |

### customer-site — Controllers

| File | Change |
|------|--------|
| `app/Http/Controllers/Admin/AdminInboxController.php:34` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `app/Http/Controllers/Admin/NotificationCenterController.php:90` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `app/Http/Controllers/Front/PageBuilderController.php:205` | `diffForHumans()` → `Jalali::diffForHumansFa()` |
| `app/Http/Controllers/Front/PageBuilderController.php:206` | `->format('Y/m/d H:i')` → `Jalali::formatFa(, 'Y/m/d H:i')` |
| `app/Http/Controllers/Front/PageBuilderController.php:320` | `diffForHumans()` → `Jalali::diffForHumansFa()` |

---

## FORM INPUTS — NO CHANGE (Intentional)

The following use `Jalali::format()` (ASCII output) intentionally because they are date picker `<input>` values that JavaScript must parse:

- `views/admin/infrastructure/*/form.blade.php` — datepicker inputs
- `views/admin/sites/edit.blade.php` — expires_at inputs
- `views/admin/sites/notifications/_form.blade.php` — starts_at/expires_at
- `views/admin/sites/updates.blade.php:24` — distributed_at input (line 51 is display — fixed)
- `customer-site/views/account/profile/index.blade.php:61` — birth_date input

---

## TOTAL FILES CHANGED

| Repository | Files |
|------------|-------|
| saas-platform | 42 files |
| customer-site | 4 files |
| **Total** | **46 files** |

---

## VERDICT: DATE_LOCALIZATION_CERTIFIED
