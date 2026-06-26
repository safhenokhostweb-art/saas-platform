# Jalali Date Picker Architecture

> **Version:** 1.0 — 2026-06-26  
> **Status:** MANDATORY — All date inputs must use Jalali pickers.

---

## Overview

The platform uses two different Jalali date picker implementations — one per repository — unified by the same Jalali/Gregorian conversion layer (`App\Helpers\Jalali`).

```
User sees Jalali input → submits Jalali string → Controller converts → DB stores Gregorian
```

---

## 1. saas-platform — Alpine.js Component

### Component location
`resources/views/components/jalali-datepicker.blade.php`

### Usage

```blade
{{-- Basic --}}
<x-jalali-datepicker name="start_date" :value="old('start_date', '')" placeholder="۱۴۰۵/۰۱/۰۱"/>

{{-- With extra CSS class --}}
<x-jalali-datepicker name="created_from" :value="request('created_from', '')" placeholder="از تاریخ" extraClass="form-control text-sm"/>

{{-- Edit form: convert existing Gregorian value to Jalali for display --}}
<x-jalali-datepicker name="birth_date"
    :value="old('birth_date', $user->birth_date ? \App\Services\PersianFormatter::date($user->birth_date) : '')"/>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `name` | string | required | Form field name |
| `value` | string | `''` | Initial value (Jalali format: Y/m/d) |
| `placeholder` | string | `۱۴۰۵/۰۱/۰۱` | Input placeholder |
| `extraClass` | string | `''` | Additional CSS classes |
| `id` | string | same as `name` | Element ID override |

### How it works

- Renders an `<input type="text">` styled for RTL
- Alpine.js `x-data` initializes the Jalali calendar popup
- On selection, populates input with Jalali date string (e.g., `۱۴۰۵/۰۳/۱۵` or `1405/03/15`)
- Form submits the Jalali string; controller converts before DB use

---

## 2. customer-site — jQuery persian-datepicker

### Plugin location
Loaded via CDN or `resources/js/` — initialized in `resources/js/admin-persian-dates.js` (or inline in layout).

### CSS classes

| Class | Behavior |
|-------|----------|
| `persian-date-input` | Initialized immediately on page load |
| `persian-date-defer` | Deferred — initialized when container becomes visible |

### Usage: Standard visible input

```html
<input type="text"
       name="date_from"
       value="{{ request('date_from') }}"
       placeholder="۱۴۰۵/۰۱/۰۱"
       class="form-control persian-date-input"
       style="height:2.25rem;font-size:.85rem">
```

### Usage: Input inside hidden container (x-show / Alpine.js)

```html
{{-- The input --}}
<input type="text"
       x-model="from_date"
       @change="from_date = $event.target.value"
       placeholder="۱۴۰۵/۰۱/۰۱"
       class="tkt-form-input text-sm persian-date-defer">

{{-- The toggle button that shows the container --}}
<button
    @click="showFilters = !showFilters; if(showFilters) $nextTick(() => {
        window.promoteDeferredPersianInputs && promoteDeferredPersianInputs($el.closest('.container'));
        window.initPersianDateInputs && initPersianDateInputs($el.closest('.container'));
    })">
    فیلتر
</button>
```

**Why deferred?** `persian-datepicker` jQuery plugin skips elements that are `!el.is(':visible')` at init time. The defer class marks them for later initialization once visible.

**Why `@change` alongside `x-model`?** jQuery plugin fires native `change` events, not Alpine-specific input events. The `@change` handler ensures Alpine's reactive state updates when the picker selects a date.

### JS initialization functions

```javascript
// Initialize all .persian-date-input elements (or within a container)
window.initPersianDateInputs(container = document)

// Promote .persian-date-defer → .persian-date-input within a container
window.promoteDeferredPersianInputs(container)
```

---

## 3. Jalali ↔ Gregorian Conversion

Both repos include `App\Helpers\Jalali` with:

```php
// Convert Jalali string to Carbon (Gregorian)
// Accepts: "1405/03/15", "۱۴۰۵/۰۳/۱۵", "1405-03-15"
Jalali::toCarbon('1405/03/15');  // → Carbon instance or null

// Format a date as Jalali with Persian digits
Jalali::formatFa($carbon, 'Y/m/d H:i');  // → "۱۴۰۵/۰۳/۱۵ ۱۴:۳۰"
```

### Controller pattern

```php
use App\Helpers\Jalali;

// Filter query
->when($request->date_from, function ($q) use ($request) {
    $c = Jalali::toCarbon($request->date_from);
    $q->whereDate('created_at', '>=', $c ? $c->toDateString() : $request->date_from);
})

// Before validation (request merge)
if ($request->filled('start_date')) {
    $c = Jalali::toCarbon($request->start_date);
    if ($c) $request->merge(['start_date' => $c->toDateString()]);
}
// Then validate 'start_date' => 'required|date'
```

---

## 4. Edge Cases

### datetime-local inputs

`admin/sms/campaigns/create.blade.php` has a `type="datetime-local"` input (includes time). The Jalali date picker does not support time selection. This field is **MANUAL REVIEW** — use native browser datetime picker or a custom Jalali datetime picker if needed.

### Read-only date display

For displaying dates (not inputs), use Blade directives — no picker needed:

```blade
@dateFa($model->created_at)          {{-- ۱۴۰۵/۰۳/۱۵ --}}
@datetimeFa($model->created_at)      {{-- ۱۴۰۵/۰۳/۱۵ ۱۴:۳۰ --}}
@agoFa($model->created_at)           {{-- ۲ روز پیش --}}
```

### Edit forms (existing Gregorian values)

When editing a record with an existing date stored in Gregorian, convert to Jalali for the picker's initial value:

```blade
<x-jalali-datepicker name="expire_date"
    :value="old('expire_date', $item->expire_date
        ? \App\Services\PersianFormatter::date($item->expire_date)
        : '')"/>
```

---

## 5. Future Date Pickers

If a new UI framework is adopted (Vue, React, etc.), the conversion layer (`Jalali::toCarbon`) is framework-agnostic. Only the picker component changes; the controller pattern stays identical.
