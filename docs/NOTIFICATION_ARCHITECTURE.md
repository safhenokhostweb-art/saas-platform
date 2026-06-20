# NOTIFICATION ARCHITECTURE
> Unified notification system — post-fix architecture (2026-06-20)

---

## Principle

**One notification container per application context. No exceptions.**

---

## Architecture Map

### saas-platform (Platform Admin)

```
layouts/admin.blade.php          ← SINGLE flash renderer for all admin pages
  └── renders: success / error / warning / info / $errors
  └── auto-dismiss: 5s (success), 6s (info), 7s (warning), manual (error)
  └── close button: ✕ on each flash div

layouts/app.blade.php            ← SINGLE flash renderer for reseller/checkout pages
  └── renders: success / error / $errors

layouts/auth-login.blade.php     ← SINGLE flash renderer for auth pages
  └── renders: success / error
```

**JS toast systems (separate, non-conflicting):**

| System | Namespace | Trigger | Used By |
|--------|-----------|---------|---------|
| `x-core-ui::toast` | `ui-toast` CustomEvent | `$dispatch('ui-toast', {...})` | Forms module index/submissions |
| `window.showToast(type, text)` | Direct global fn | Explicit call | Forms builder |
| Alpine `this.showToast()` | Canvas local | Internal canvas state | Theme canvas builder |

These are all isolated JS toast systems for **real-time AJAX feedback** (not session flash). They do not conflict with the layout flash system.

---

### customer-site (Tenant Admin + Account + Front)

```
admin/layout.blade.php           ← SINGLE flash renderer for all admin pages
  └── renders: success / warning / error / info / $errors
  └── auto-dismiss: 5s (success), 6s (info), 7s (warning), manual (error)
  └── close button: via .admin-flash__close CSS class
  └── CSS: public/css/customer-admin-layout.css

account/layout.blade.php         ← SINGLE flash renderer for all account pages
  └── renders: success / error / info / warning / $errors
  └── includes: pending-invoice-banner, notifications-toolbar

themes/default/layout.blade.php  ← SINGLE flash renderer for public front pages
  └── renders: success / error / warning / info  (all 4 keys, fixed 2026-06-20)
  └── auto-dismiss: 5s (success), 6s (info), 7s (warning), manual (error)
  └── inline styles (no CSS class dependency)

layouts/auth-login.blade.php     ← SINGLE flash renderer for auth pages
  └── renders: success / error
```

**JS toast systems (separate, non-conflicting):**

| System | Location | Trigger | Used By |
|--------|----------|---------|---------|
| Alpine `showToast(msg, type)` | `canvas-editor.blade.php` | Local Alpine method | Canvas save/publish/delete AJAX |
| Alpine `showToast(msg, type)` | `builder/edit.blade.php` | Local Alpine method | Page builder save/delete AJAX |
| Alpine `notify(from, body)` | `account/tickets/show.blade.php` | Polling trigger | New reply real-time notification |
| Alpine `showToast()` | `admin/user-tickets/show.blade.php` | Polling trigger | New message real-time notification |
| Alpine `showToast()` | `admin/users/chat.blade.php` | Polling trigger | New chat message real-time notification |

These are isolated to specific interactive pages for real-time events. They never fire for session flash.

---

## Flash Dispatch Convention

All controllers must use the standard Laravel redirect+flash pattern:

```php
return redirect()->route('some.route')->with('success', 'پیام موفقیت');
return redirect()->back()->with('error', 'پیام خطا');
return redirect()->route('some.route')->with('info', 'پیام اطلاعاتی');
return redirect()->route('some.route')->with('warning', 'پیام هشدار');
```

**Never dispatch session flash from Livewire or JS** — use `$dispatch('ui-toast', {...})` for in-page AJAX notifications instead.

---

## Auto-Dismiss Timings

| Key | Duration | Rationale |
|-----|----------|-----------|
| success | 5 seconds | Short confirmation, user already saw the result |
| info | 6 seconds | User may need to read informational content |
| warning | 7 seconds | User needs time to understand the warning |
| error | Never (manual only) | User must explicitly dismiss errors |

---

## What Is NOT a Flash Notification

These systems exist independently and are NOT managed by the layout flash system:

1. **Platform bell** (`partials/admin-notifications-widget.blade.php`) — system-level platform push notifications
2. **Account toolbar bell** (`account/partials/notifications-toolbar.blade.php`) — user inbox (UserNotification model)
3. **Notification center** (`admin/notification-center/`) — system-level alerts from InfrastructureErrorCollector
4. **Pending invoice banner** (`account/partials/pending-invoice-banner.blade.php`) — persistent billing state
5. **License transient banner** (`admin/partials/license-transient-banner.blade.php`) — Redis-cached license error state
6. **Browser push permission prompt** (`partials/notification-permission.blade.php`) — browser API permission request

---

## Invariants (Never Violate)

1. `layouts/admin.blade.php` is the ONLY place that renders session flash in saas-platform admin
2. `admin/layout.blade.php` is the ONLY place that renders session flash in customer-site admin
3. `account/layout.blade.php` is the ONLY place that renders session flash in account pages
4. `themes/default/layout.blade.php` is the ONLY place that renders session flash on public pages
5. Individual `@section('content')` views must NEVER call `session('success')`, `session('error')`, `session('warning')`, or `session('info')` for display
6. `$errors->any()` is handled by the admin layout — never in individual views
7. Page-specific view variables (`$error`, `$purchased`) are allowed in views — they are NOT session flash
