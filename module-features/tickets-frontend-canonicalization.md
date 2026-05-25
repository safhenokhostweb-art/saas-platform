# Tickets Module — Frontend Canonicalization (PHASE 7.0 TASK 4)

**Date:** 2026-05-24  
**Author:** AI Architecture Audit  
**Status:** ✅ COMPLETE — All frontend patterns standardized

---

## 1. PURPOSE

Standardize all frontend (Blade view) patterns across the three implementations into a single canonical approach. This covers layout references, CSS methodology, data access patterns, date/number formatting, Alpine.js usage, and component structure.

---

## 2. CANONICAL VIEW ARCHITECTURE DECISIONS

### Decision 1: Layout Reference

**Canonical:** `@extends('admin.layout')`

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `@extends('admin.layout')` | ✅ Already canonical | None |
| B: Project | `@extends('layouts.admin')` | ❌ Must change | **UPDATE to `@extends('admin.layout')`** |
| C: customer-site | `@extends('admin.layout')` | ✅ Already canonical | None |

### Decision 2: View Namespace

**Canonical:** `tickets::*` (package view namespace)

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `tickets::index`, `tickets::create`, `tickets::show` | ✅ Already canonical | None |
| B: Project | `admin.support-tickets.*` | ❌ Must change | **UPDATE to `tickets::*`** |
| C: customer-site | `admin.support.*` | ❌ Must change | **UPDATE to `tickets::*`** |

### Decision 3: CSS Approach

**Canonical:** Tailwind utility classes + `@asset_module('tickets', 'css/tickets.css')` for module-specific styles.  
**No inline `<style>` blocks** in canonical views.

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `@asset_module('tickets', 'css/tickets.css')` | ✅ Already canonical | None |
| B: Project | Inline `<style>` blocks (ticket-stat-card, glass-filter-bar, etc.) | ❌ Must change | **MOVE styles to package CSS file** |
| C: customer-site | Inline `<style>` blocks (ticket-stat-card, glass-filter-bar, etc.) | ❌ Must change | **MOVE styles to package CSS file** |

### Decision 4: Data Access Pattern

**Canonical:** Eloquent object access (`$ticket->field`, `$ticket->relation->field`)

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `$ticket->field` | ✅ Already canonical | None |
| B: Project | `$supportTicket->field` | ✅ Eloquent (rename variable) | **RENAME to `$ticket`** |
| C: customer-site | `$ticket['field']` (array) | ❌ Must change | **CONVERT to Eloquent** (via API response mapping) |

### Decision 5: Date Formatting

**Canonical:** `Jalali::format($ticket->created_at)` for dates, `Jalali::formatTime($ticket->created_at)` for times

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `$ticket->created_at->format('Y/m/d H:i')` | ❌ Must change | **UPDATE to `Jalali::format()`** |
| B: Project | `Jalali::format($t->created_at)` | ✅ Already canonical | None |
| C: customer-site | `Jalali::formatFa(Carbon::parse(...))` | ⚠️ Similar | **UPDATE to `Jalali::format()`** |

### Decision 6: Number Formatting

**Canonical:** `NumberHelper::formatFa($number)` for Persian number display

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | `number_format($stats['total'])` | ❌ Must change | **UPDATE to `NumberHelper::formatFa()`** |
| B: Project | `NumberHelper::formatFa($count)` | ✅ Already canonical | None |
| C: customer-site | `NumberHelper::formatFa($count)` | ✅ Already canonical | None |

### Decision 7: Stat Cards

**Canonical:** 5 stat cards (total, open, in_progress, closed, sla_breach) using glass-card pattern

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | 4 cards (no SLA breach) | ❌ Must change | **ADD SLA breach card** |
| B: Project | 5 cards (with SLA breach) | ✅ Already canonical | None |
| C: customer-site | 5 cards (SLA hardcoded to 0) | ⚠️ Partial | **USE real SLA data from API** |

### Decision 8: Alpine.js Chat Component

**Canonical:** `x-data="supportChat()"` with scroll handling, loading states, and configurable colors from Setting model

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | Simple reply list (no Alpine.js chat) | ❌ Must change | **ADD `supportChat()` Alpine component** |
| B: Project | Full `supportChat()` with Setting model colors | ✅ Already canonical | None |
| C: customer-site | `supportChat()` with hardcoded colors | ⚠️ Partial | **USE Setting model or API-provided colors** |

### Decision 9: Module Features Component

**Canonical:** `<x-module-features module="support" />` at bottom of index view

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | ❌ Not present | ❌ Must change | **ADD `<x-module-features module="support" />`** |
| B: Project | ✅ Present | ✅ Already canonical | None |
| C: customer-site | ✅ Present | ✅ Already canonical | None |

### Decision 10: Guide Include

**Canonical:** `@include('tickets::guide')` at bottom of index view

| Implementation | Current | Canonical | Action |
|:--------------|:--------|:----------|--------|
| A: Package | ❌ Not present | ❌ Must change | **ADD guide include** |
| B: Project | `@include('admin.support-tickets.guide')` | ⚠️ Partial | **UPDATE to `@include('tickets::guide')`** |
| C: customer-site | `@include('admin.support.guide')` | ⚠️ Partial | **UPDATE to `@include('tickets::guide')`** |

---

## 3. CANONICAL VIEW FILES

### 3.1 `tickets::index` — Ticket List View

**Canonical features:**
- Layout: `@extends('admin.layout')`
- CSS: `@asset_module('tickets', 'css/tickets.css')` in `@push('head')`
- 5 stat cards: total, open, in_progress, closed, sla_breach
- Create button: `bg-gradient-to-r from-indigo-600 to-purple-600`
- Filter bar: Alpine.js `x-data="{ search: '', status: '', priority: '' }"`
- Table columns: ticket_number, site, subject (with assignee), status, priority, date, actions
- SLA breach row highlighting (red border-right)
- Pagination: `$tickets->links()`
- Module features: `<x-module-features module="support" />`
- Guide: `@include('tickets::guide')`
- Date format: `Jalali::format($ticket->created_at)`
- Number format: `NumberHelper::formatFa()`
- Variable name: `$ticket` (not `$t` or `$supportTicket`)

### 3.2 `tickets::create` — Create Ticket View

**Canonical features:**
- Layout: `@extends('admin.layout')`
- CSS: `@asset_module('tickets', 'css/tickets.css')` in `@push('head')`
- Form fields: site_id (select), subject, message (textarea), priority (select), customer_email, customer_name, category (select with predefined options), tags, attachments
- Submit button: `bg-gradient-to-r from-indigo-600 to-purple-600`
- Error display: glass-card error box with Alpine.js dismiss
- Field styling: `px-4 py-3 rounded-xl` for inputs
- Gap between fields: `gap-4` or `gap-6`

### 3.3 `tickets::show` — Ticket Detail View

**Canonical features:**
- Layout: `@extends('admin.layout')`
- CSS: `@asset_module('tickets', 'css/tickets.css')` in `@push('head')`
- Chat UI layout: 2/3 + 1/3 grid (chat panel + sidebar)
- Chat header: status badge, priority badge, SLA breach badge, subject
- Chat messages: Alpine.js `supportChat()` with scroll handling
- Chat colors: from Setting model (configurable)
- Sidebar sections: Ticket Info, Status Control (dropdown), Assign Control, Quick Actions
- Reply form: textarea with Enter/Shift+Enter handling, send button
- Date format: `Jalali::format()` for dates, `Jalali::formatTime()` for times
- Variable name: `$ticket` (not `$supportTicket`)

### 3.4 `tickets::settings` — Settings View

**Canonical features:**
- Layout: `@extends('admin.layout')`
- Chat color configuration (customer_bg, customer_text, staff_bg, staff_text)
- Saved to Setting model

### 3.5 `tickets::guide` — Guide View

**Canonical features:**
- Uses `@component('admin.partials.module-guide-box')`
- Module usage instructions

---

## 4. CANONICAL CSS CLASSES

### 4.1 Stat Cards

```html
<div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
            <svg class="w-5 h-5 flex-shrink-0">...</svg>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ NumberHelper::formatFa($count) }}</div>
            <div class="text-xs text-gray-500 mt-0.5">برچسب</div>
        </div>
    </div>
</div>
```

### 4.2 Filter Bar

```html
<div class="bg-white/75 backdrop-blur-xl border border-gray-200/50 rounded-2xl p-4 shadow-sm" x-data="{ search: '', status: '', priority: '' }">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <div class="relative flex-1 w-full sm:w-auto">
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 flex-shrink-0">...</svg>
            <input type="text" x-model="search" placeholder="جستجو..." class="w-full pr-10 pl-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
        </div>
        <select x-model="status" class="px-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">...</select>
        <select x-model="priority" class="px-4 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">...</select>
    </div>
</div>
```

### 4.3 Primary Button

```html
<a href="{{ route('admin.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-sm transition-all">
    <svg class="w-5 h-5 flex-shrink-0">...</svg>
    ثبت تیکت جدید
</a>
```

### 4.4 Status Badges

```html
<!-- Open -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">
    <span class="w-2 h-2 rounded-full bg-yellow-500"></span> باز
</span>

<!-- In Progress -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
    <span class="w-2 h-2 rounded-full bg-blue-500"></span> در حال بررسی
</span>

<!-- Closed -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> بسته
</span>
```

### 4.5 Priority Badges

```html
<!-- High -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
    <span class="w-2 h-2 rounded-full bg-red-500"></span> بالا
</span>

<!-- Normal -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200">
    <span class="w-2 h-2 rounded-full bg-blue-400"></span> عادی
</span>

<!-- Low -->
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">
    <span class="w-2 h-2 rounded-full bg-gray-400"></span> کم
</span>
```

### 4.6 Form Inputs

```html
<input type="text" name="field" value="{{ old('field') }}"
    class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all @error('field') border-red-300 @enderror"
    placeholder="...">
```

### 4.7 Form Select

```html
<select name="field" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
    <option value="">انتخاب کنید</option>
    ...
</select>
```

### 4.8 Success Alert

```html
<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-3" role="status">
    <svg class="w-5 h-5 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span>{{ session('success') }}</span>
</div>
```

---

## 5. CANONICAL ALPINE.JS COMPONENTS

### 5.1 `supportChat()` — Chat UI Component

```javascript
function supportChat() {
    return {
        message: '',
        isSending: false,
        showScrollBtn: false,
        init() {
            this.$nextTick(() => this.scrollDown());
        },
        scrollDown() {
            const el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
            this.showScrollBtn = false;
        },
        handleScroll() {
            const el = this.$refs.chatMessages;
            if (!el) return;
            const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
            this.showScrollBtn = distanceFromBottom > 100;
        }
    }
}
```

### 5.2 Filter Bar — Index View

```html
<div x-data="{ search: '', status: '', priority: '' }">
    <!-- Filter inputs with x-model bindings -->
</div>
```

---

## 6. CANONICAL ICON SET

All icons use the standard format:
```html
<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M..."/>
</svg>
```

| Icon | Path `d` value | Usage |
|------|----------------|-------|
| **Plus** | `M12 6v6m0 0v6m0-6h6m-6 0H6` | Create button |
| **Check** | `M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z` | Success, Close badge |
| **Eye** | `M15 12a3 3 0 11-6 0 3 3 0 016 0z` + `M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z` | View action |
| **Search** | `M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z` | Search input |
| **Arrow right** | `M15 19l-7-7 7-7` | Back button |
| **Close/X** | `M6 18L18 6M6 6l12 12` | Dismiss button |
| **Send** | `M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5` | Reply send |
| **Clock** | `M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z` | In progress stat |
| **Inbox** | `M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4` | Total stat |
| **Lightning** | `M13 10V3L4 14h7v7l9-11h-7z` | In progress badge, Quick actions |
| **User** | `M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z` | Assign section |
| **Settings** | `M10.325 4.317c.426-1.756 2.924-1.756 3.35 0...` | Settings link |
| **Lock** | `M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5...` | Security note |
| **Attachment** | `M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5...` | File attachment |
| **Warning** | `M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z` | SLA breach |
| **Arrow up** | `M5 10l7-7m0 0l7 7m-7-7v18` | Priority high |
| **Spinner** | `M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15` | Loading state |

---

## 7. CANONICAL VIEW VARIABLE NAMES

| Context | Variable | Type | Source |
|---------|----------|------|--------|
| Index | `$tickets` | `LengthAwarePaginator` | `$this->ticketService->getAll($filters)` |
| Index (loop) | `$ticket` | `Ticket` | `@foreach($tickets as $ticket)` |
| Create | `$sites` | `Collection` | `Site::where('is_active', true)->get()` |
| Show | `$ticket` | `Ticket` | Route model binding `{ticket}` |
| Show (replies) | `$reply` | `TicketReply` | `@foreach($ticket->replies as $reply)` |
| Settings | `$settings` | `array` | From `Setting::get()` |

---

## 8. CANONICAL VIEW FILE LIST

| View | Path | Purpose |
|------|------|---------|
| `tickets::index` | `packages/.../Resources/views/index.blade.php` | Ticket list with stats, filters, table |
| `tickets::create` | `packages/.../Resources/views/create.blade.php` | Create ticket form |
| `tickets::show` | `packages/.../Resources/views/show.blade.php` | Ticket detail with chat UI |
| `tickets::settings` | `packages/.../Resources/views/settings.blade.php` | Chat color settings |
| `tickets::guide` | `packages/.../Resources/views/guide.blade.php` | Module usage guide |

---

## 9. FRONTEND MIGRATION ORDER

When migration begins, the frontend should be updated in this order:

1. **Package views first** — Rewrite all 5 package views to canonical standard
2. **Project views second** — Update B views to use `@extends('admin.layout')` and `tickets::*` namespace
3. **customer-site views third** — Update C views to use canonical field names and data access patterns
4. **CSS consolidation** — Move all inline styles to `tickets.css` in package assets
5. **Alpine.js chat** — Add `supportChat()` to package show view
6. **SLA display** — Add SLA breach card and row highlighting to package index view
7. **Module features** — Add `<x-module-features>` and guide include to package index view

---

*End of Frontend Canonicalization — Ready for TASK 5: Shared Contract Extraction*
