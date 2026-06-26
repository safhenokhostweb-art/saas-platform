# Persian Localization Standard — PERSIAN_LOCALIZATION_GLOBAL_STANDARD

> **Version:** 1.0 — 2026-06-26  
> **Status:** MANDATORY — All current and future code must comply.  
> **Enforcement:** Automatic via Blade directives, helper functions, and component library.

---

## 1. Core Rules (Non-Negotiable)

| Rule | Requirement |
|------|-------------|
| **R-1** | No English digits (0-9) anywhere in the UI — Persian digits only (۰-۹) |
| **R-2** | All dates shown to users must be Jalali (Solar Hijri) — never Gregorian |
| **R-3** | All date inputs must use a Jalali Date Picker |
| **R-4** | Database stores UTC/Gregorian — conversion happens at presentation layer only |
| **R-5** | Single entry point: `PersianFormatter` service class |
| **R-6** | No duplicate localization logic anywhere in the codebase |

---

## 2. Number Formatting

### Blade Templates (preferred)

```blade
{{-- Standalone numbers --}}
@numFa($count)                    {{-- integer --}}
@numFa($price)                    {{-- currency --}}
@numFa($value, 2)                 {{-- 2 decimal places --}}
@priceFa($amount)                 {{-- with تومان suffix --}}
@digitsFa($string)                {{-- convert digits in arbitrary string --}}

{{-- Inside PHP expressions --}}
{{ num_fa($value) }}
{{ num_fa($value, 1) . ' MB' }}
{{ $x > 0 ? num_fa($x) . ' ت' : '—' }}
```

### PHP Code (Services, Controllers)

```php
use App\Services\PersianFormatter;

PersianFormatter::num($value);          // basic number
PersianFormatter::currency($amount);    // with تومان
PersianFormatter::digits($str);         // arbitrary string digit conversion
```

### FORBIDDEN patterns

```blade
{{-- NEVER use these --}}
{{ number_format($x) }}
{{ \App\Helpers\NumberHelper::toPersianNumbers(number_format($x)) }}
{{ \App\Helpers\NumberHelper::formatFa(number_format($x)) }}
```

---

## 3. Date Formatting

### Blade Templates

```blade
@dateFa($date)                   {{-- Y/m/d Jalali (default) --}}
@dateFa($date, 'Y/m/d')         {{-- explicit format --}}
@dateFaLong($date)               {{-- روز جمعه ۱ فروردین ۱۴۰۵ --}}
@dateFaShort($date)              {{-- ۱ فروردین ۱۴۰۵ --}}
@datetimeFa($date)               {{-- Y/m/d H:i --}}
@agoFa($date)                    {{-- X روز پیش --}}
```

### PHP Code

```php
use App\Services\PersianFormatter;

PersianFormatter::date($date);           // Y/m/d
PersianFormatter::dateLong($date);       // full Persian date
PersianFormatter::datetime($date);       // Y/m/d H:i
PersianFormatter::ago($date);            // relative time
```

### Global Helpers

```php
date_fa($date)
date_fa_long($date)
date_fa_short($date)
datetime_fa($date)
ago_fa($date)
```

---

## 4. Date Inputs

### saas-platform (Alpine.js Component)

```blade
<x-jalali-datepicker name="field_name" :value="old('field_name', '')" placeholder="۱۴۰۵/۰۱/۰۱"/>

{{-- With existing Gregorian value (edit forms) --}}
<x-jalali-datepicker name="entry_date" :value="old('entry_date', $entry->entry_date ? \App\Services\PersianFormatter::date($entry->entry_date, 'Y/m/d') : '')"/>
```

### customer-site (jQuery persian-datepicker)

```html
<input type="text" name="field_name" value="{{ request('field_name') }}"
       placeholder="۱۴۰۵/۰۱/۰۱" class="form-control persian-date-input">
```

For fields inside hidden containers (`x-show`, `display:none`):
```html
<input type="text" class="form-control persian-date-defer" ...>
```
Then call `window.promoteDeferredPersianInputs(container)` + `window.initPersianDateInputs(container)` when the container becomes visible.

---

## 5. Controller Date Handling

When a Jalali date arrives from a form (datepicker submits Jalali strings):

```php
use App\Helpers\Jalali;

// Convert before Laravel validation or DB comparison
if ($request->filled('entry_date')) {
    $carbon = Jalali::toCarbon($request->entry_date);
    if ($carbon) {
        $request->merge(['entry_date' => $carbon->toDateString()]);
    }
}

// For filter queries
if ($request->filled('date_from')) {
    $c = Jalali::toCarbon($request->date_from);
    $q->whereDate('column', '>=', $c ? $c->toDateString() : $request->date_from);
}
```

---

## 6. Architecture: Centralized Layer

```
PersianFormatter (app/Services/PersianFormatter.php)
    ↓ delegates to
    Jalali (app/Helpers/Jalali.php)         ← Jalali/Gregorian conversion
    NumberHelper (packages/.../NumberHelper.php)  ← Persian digit conversion
    
Blade Directives (@numFa, @dateFa, etc.)   ← registered in AppServiceProvider
Global Helpers (num_fa(), date_fa(), etc.) ← autoloaded via composer.json files[]
```

---

## 7. Exceptions (Do NOT convert)

| Location | Reason |
|----------|--------|
| SVG `stroke-dasharray` / `stroke-dashoffset` | Breaking SVG rendering |
| `<script>` JS blocks | JavaScript runtime requires ASCII digits |
| CSS values (`width:50%`) | CSS parser requires ASCII |
| Database queries/values | DB stores Gregorian; convert at PHP layer |
| API responses (JSON) | APIs should return Gregorian; client localizes |
| PDF generation | Some PDF libraries require ASCII — verify case by case |

---

## 8. Package Dependencies

| Package | Repo | Purpose |
|---------|------|---------|
| `hekmatinasser/verta` v8.5 | saas-platform | Jalali calendar |
| `hekmatinasser/verta` v8.5 | customer-site | Jalali calendar |

---

## 9. Future Module Checklist

When building a new module, verify:
- [ ] All `number_format()` calls replaced with `@numFa()` or `num_fa()`
- [ ] All `->format('Y-m-d')` date displays replaced with `@dateFa()`
- [ ] All `type="date"` inputs replaced with Jalali date picker
- [ ] Controllers convert incoming Jalali dates to Gregorian before validation/DB
- [ ] No English digits visible in any user-facing template
