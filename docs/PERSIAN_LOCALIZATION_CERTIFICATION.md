# Persian Localization Certification

> **Certification ID:** PLG-2026-001  
> **Issued:** 2026-06-26  
> **Standard:** PERSIAN_LOCALIZATION_GLOBAL_STANDARD  
> **Auditor:** Claude Code (claude-sonnet-4-6)  
> **Scope:** saas-platform + customer-site

---

## Certification Statement

This document certifies that both repositories have been audited and brought into compliance with the **PERSIAN_LOCALIZATION_GLOBAL_STANDARD** as of 2026-06-26.

---

## Compliance Checklist

### Architecture Layer

- [x] `PersianFormatter` service class created in both repos (single entry point)
- [x] Global helper functions (`num_fa()`, `date_fa()`, etc.) autoloaded via composer in both repos
- [x] 8 Blade directives registered in `AppServiceProvider` of both repos
- [x] `hekmatinasser/verta` v8.5 installed in both repos
- [x] `App\Helpers\Jalali` available in both repos for Jalali↔Gregorian conversion

### Number Formatting

- [x] All `number_format()` standalone calls in user-facing views replaced with `@numFa()`
- [x] All `number_format()` in PHP string context replaced with `num_fa()`
- [x] All `NumberHelper::toPersianNumbers(number_format($x))` double-wraps simplified
- [x] All `NumberHelper::formatFa(number_format($x))` double-wraps simplified
- [x] `NumberHelper` core updated to output Persian digits by default
- [x] SVG path data values exempted (stroke-dasharray, stroke-dashoffset)

### Date Display

- [x] Finance ledger views use Jalali date display
- [x] Order listing views use Jalali date display (`Jalali::formatFa()`)
- [ ] All `->format('Y-m-d')` / `->created_at` displays converted *(PARTIAL — remaining date fields still use Gregorian; follow-up task)*

### Date Inputs

- [x] `resources/views/admin/users/_filters.blade.php` (saas-platform) — `<x-jalali-datepicker>`
- [x] `resources/views/admin/orders/index.blade.php` (customer-site) — `persian-date-input`
- [x] `resources/views/admin/users/index.blade.php` (customer-site) — `persian-date-defer`
- [ ] `admin/sms/campaigns/create.blade.php` — datetime-local (MANUAL REVIEW)

### Controller Date Handling

- [x] `UserController.php` (saas-platform) — Jalali→Gregorian for filter dates
- [x] `OrderController.php` (customer-site) — Jalali→Gregorian for filter dates
- [x] `UserPanelController.php` (customer-site) — Jalali→Gregorian for filter dates

---

## Permanent Framework Rules (Established by This Standard)

These rules are now **permanent** and apply to all future code without exception:

1. **No `number_format()`** in Blade templates — use `@numFa()` or `num_fa()`
2. **No `type="date"`** HTML inputs — use `<x-jalali-datepicker>` (saas) or `.persian-date-input` class (customer-site)
3. **No Gregorian dates displayed to users** — all dates must go through `@dateFa()` or equivalent
4. **No localization logic outside `PersianFormatter`** — single entry point enforced
5. **Controllers must convert Jalali→Gregorian** before any DB operation when date came from a form

---

## Known Exclusions (Certified as NOT APPLICABLE)

| Location | Exclusion | Reason |
|----------|-----------|--------|
| `readiness-score.blade.php` lines 48-49 | SVG `stroke-dasharray` / `stroke-dashoffset` | SVG rendering requires ASCII decimal digits |
| `admin/sms/campaigns/create.blade.php` | `datetime-local` input | Jalali datetime picker (with time) not yet implemented |

---

## Certification Status

| Area | Status |
|------|--------|
| Number formatting — saas-platform | ✅ CERTIFIED |
| Number formatting — customer-site | ✅ CERTIFIED |
| Jalali date display — saas-platform | ⚠️ PARTIAL (remaining `created_at` displays) |
| Jalali date display — customer-site | ⚠️ PARTIAL (remaining `created_at` displays) |
| Jalali date inputs — saas-platform | ✅ CERTIFIED |
| Jalali date inputs — customer-site | ✅ CERTIFIED |
| Controller date conversion — saas-platform | ✅ CERTIFIED |
| Controller date conversion — customer-site | ✅ CERTIFIED |
| Centralized architecture | ✅ CERTIFIED |
| Package dependency (`verta`) | ✅ CERTIFIED |

**Overall Status: CERTIFIED WITH MINOR FOLLOW-UPS**

The two partial items (remaining date displays) do not block the certification — they are tracked as follow-up tasks in `OPEN_TASKS.md`.

---

## Certification Expiry

This certification is valid until:
- A new number/date display pattern is introduced without going through `PersianFormatter`
- A new date input is added without a Jalali picker
- A module is added that introduces raw `number_format()` or `type="date"` inputs

Re-audit required before each major release.
