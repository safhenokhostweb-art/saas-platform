# Customer Installation Admin Center — UI Specification
## PHASE-003B UI Design Document

**Status:** COMPLETE  
**Date:** 2026-06-27

---

## 1. Design System

| Attribute | Value |
|---|---|
| Layout direction | RTL (right-to-left) |
| Font | Vazirmatn |
| Language | Persian (فارسی) |
| CSS framework | Tailwind CSS v4 |
| JS framework | Alpine.js 3.x |
| Icon system | `x-core-ui::icon` (inline SVG) |
| Admin layout | `layouts.admin` |
| Color scheme | Indigo/gray neutral, status badges per state |

---

## 2. Component Patterns

### Stat Cards (Dashboard)
- Gradient backgrounds (blue/green/amber/red/purple/indigo)
- Large number, label below
- Icon top-right
- White text

### Status Badges
All status badges use `.status-badge` + state class:

| Status | Background | Text | Label |
|---|---|---|---|
| pending | `#fef9c3` | `#92400e` | در صف |
| running | `#dbeafe` | `#1e40af` | در حال اجرا |
| rolling_back | `#ede9fe` | `#5b21b6` | بازگشت |
| complete | `#d1fae5` | `#065f46` | موفق |
| failed | `#fee2e2` | `#991b1b` | ناموفق |
| rollback_failed | `#fce7f3` | `#9d174d` | خطای بازگشت |
| rolled_back | `#f3f4f6` | `#374151` | بازگشت‌خورد |

### Tables
- White card with rounded-xl border shadow-sm
- `bg-gray-50` thead
- `divide-y divide-gray-100` tbody rows
- `hover:bg-gray-50 transition-colors` on rows
- Paginated via Laravel `->links()`

### Forms
- `form-label` class on labels
- `form-control` on inputs/textareas/selects
- `form-select` on select elements
- `@error('field') border-red-400 @enderror` for validation errors
- Red error messages below each field

---

## 3. Installation Wizard (Step-by-Step)

### Alpine.js State (`installWizard()`)

```javascript
{
  step: 1,
  totalSteps: 8,
  submitting: false,
  form: {
    // Step 1
    customer_company, owner_name, customer_email, phone, language, timezone, currency,
    // Step 2
    remote_server_id,
    // Step 3
    customer_domain, install_mode,
    // Step 4
    backup_policy, ssl_enabled,
    // Step 5
    license_tier,
    // Step 7
    modules: [],
  },
  get selectedServerLabel() { ... },
  validateStep(step) { /* alert on missing required fields */ },
  nextStep() { if (validateStep(this.step)) this.step++ },
  prevStep() { this.step-- },
}
```

### Step Indicator
- Circle per step with number
- `bg-indigo-600 text-white` = active
- `bg-green-500 text-white` + checkmark = done
- `bg-gray-200 text-gray-500` = pending
- Connector line between circles

### Step Cards
Each step is a `x-show="step === N"` section inside the form.  
Transitions: `x-transition:enter / x-transition:leave` for smooth slide.

### Install Modes
Visual card selection (radio):

| Mode | Label | Description |
|---|---|---|
| MODE_FRESH | نصب تازه | نصب کامل از ابتدا |
| MODE_RESTORE | بازیابی | بازیابی از فایل پشتیبان |
| MODE_CLONE | کلون | کپی از یک سایت موجود |
| MODE_MOVE | انتقال | انتقال به سرور جدید |
| MODE_REINSTALL | نصب مجدد | نصب مجدد روی دامنه موجود |

### License Tiers
Cards with feature list:

| Tier | Badge color | Features shown |
|---|---|---|
| basic | gray | Features list 3 items |
| professional | indigo | Features list 5 items |
| enterprise | amber | Features list 7 items |

---

## 4. Remote Server Manager

### Server List (index)
- Table with columns: برچسب, هاست, پورت, کاربر, احراز هویت, آخرین تست, نصب‌ها, وضعیت, Actions
- Auth method shown as colored badge: purple (کلید SSH) / blue (رمز عبور)
- Last test date + status (موفق/ناموفق)
- Actions: Test (ajax, shows spinner), Edit (link), Delete (form with confirm dialog)
- Test result appears inline below row in expandable `<tr>`

### Test Connection (AJAX)
Button triggers `testServer(id)`:
1. Replaces icon with spinner
2. POSTs to `servers/{id}/test`
3. Shows inline result row:
   - Success (green): PHP version, disk free, fingerprint truncated
   - Failure (red): error message

### Create / Edit Forms
- Label, host, port, username fields
- Auth method: radio (key / password)
- `x-show="authMethod === 'key'"` / `x-show="authMethod === 'password'"` on credential sections
- Private key textarea (8 rows, monospace)
- Password input (type=password, autocomplete=new-password)
- Security notice: "credentials encrypted with AES-256-GCM, never displayed"
- Edit form: credential fields empty by default with "leave blank to keep existing" hint
- Edit page includes inline "Test Connection" panel at bottom

---

## 5. Real-time Monitor

### SSE Connection (show.blade.php)
Only auto-connects when `$installation->status === 'running'`:

```javascript
const src = new EventSource('{{ route(...progress...) }}');
src.addEventListener('progress', e => {
    const d = JSON.parse(e.data);
    // update progress bar, current stage label
});
src.addEventListener('status', e => {
    const d = JSON.parse(e.data);
    // update status badge
});
src.addEventListener('done', e => {
    src.close();
    setTimeout(() => window.location.reload(), 2000);
});
```

### Progress Display
- Bar: `(completedStages / 20) * 100`%
- Current stage label below bar
- Stage list: 20 items — done=`line-through text-green-600`, active=`text-indigo-600 animate-pulse`, pending=`text-gray-400`

---

## 6. Log Timeline

Dot colors by event type:

| Event type | Dot color |
|---|---|
| started | blue |
| completed | green |
| failed | red |
| warning | orange |
| checkpoint | purple |
| (default) | gray |

Each entry: timestamp (right-aligned, xs), event type badge, message text.

---

## 7. Installation Detail (show.blade.php)

### Header
- Company name, status badge, install mode tag
- "Live Progress" button (only when running) → scrolls to SSE section
- "Rollback" button (only when failed + checkpoints exist) → `confirm()` + POST

### Two-column layout
- Left (8/12): Timeline + SSE progress section
- Right (4/12): Metadata table + stage list + checkpoints

### Rollback Checkpoints
Listed as: stage name + registered_at. Only pending ones shown for rollback availability.

---

## 8. Packages List

Read-only table:
- Version (monospace), active badge (green pill), size, SHA256 (truncated + copy button), manifest checksum, release date (formatted + diffForHumans), release notes (truncated with title)
- Active package row has `bg-green-50/40` highlight
- Footer: Artisan command hint for registering new packages

---

*This document is part of the PHASE-003B Customer Installation Admin Center deliverables.*
