# Phase 9.3 — Distributed Update Visibility
**Date:** 2026-05-27  
**Component:** `<x-core-ui::update-banner>`

---

## OVERVIEW

The `update-banner` core-ui component implements a **license-aware** update notification system. Security patches are ALWAYS visible to all tenants (regardless of license status); feature upgrades require an active license.

---

## ARCHITECTURE

```
Update Types:
  security  → ALWAYS visible (legal obligation, safety)
  feature   → ONLY visible to licensed tenants
  patch     → teaser shown to unlicensed, full details to licensed

Visibility Logic:
  ┌──────────────────────────────────────────────────────────┐
  │ update.type == 'security'                                │
  │   → Show to ALL tenants, regardless of license           │
  │   → Red banner, animated pulse border                    │
  │   → Cannot be permanently dismissed (session only)       │
  ├──────────────────────────────────────────────────────────┤
  │ update.type == 'feature' AND tenant.licensed == true     │
  │   → Show to licensed tenants only                        │
  │   → Indigo banner, sparkle icon                          │
  │   → Dismissible                                          │
  ├──────────────────────────────────────────────────────────┤
  │ update.type == 'feature' AND tenant.licensed == false    │
  │   → Show teaser only (count + upgrade CTA)               │
  │   → No update content shown                              │
  │   → Gray banner with lock icon                           │
  └──────────────────────────────────────────────────────────┘
```

---

## USAGE

```blade
{{-- In admin layout or dashboard --}}
<x-core-ui::update-banner
    :updates="[
        [
            'module'      => 'Forms',
            'version'     => '9.3.1',
            'type'        => 'security',
            'description' => 'رفع آسیب‌پذیری XSS در فیلد متنی',
            'released_at' => '2026-05-27',
        ],
        [
            'module'      => 'Tickets',
            'version'     => '9.4.0',
            'type'        => 'feature',
            'description' => 'تخصیص خودکار تیکت با هوش مصنوعی',
            'released_at' => '2026-05-27',
        ],
    ]"
    :licensed="$tenant->isLicensed()"
    :dismissible="true"
/>
```

---

## DATA CONTRACT

```php
// Update array shape:
[
    'module'      => string,          // 'Forms', 'Tickets', etc.
    'version'     => string,          // '9.3.1'
    'type'        => 'security'|'feature'|'patch',
    'description' => string,          // Persian description
    'released_at' => string|Carbon,   // date
]
```

---

## INTEGRATION POINTS

The banner should be fed by a `ModuleUpdateService` (planned for Phase 9.4) that:
1. Reads available versions from `packages/*/config/module-manifest.php`
2. Compares against installed versions
3. Classifies each update by type (security/feature/patch)
4. Returns the array passed to the component

For now, updates can be hardcoded or read from a config/JSON file.

---

## SECURITY DESIGN PRINCIPLES

1. **Security patches bypass license gates** — legal obligation to notify all users
2. **Feature details are license-gated** — commercial boundary enforcement
3. **Dismissal is session-scoped** — security alerts cannot be hidden permanently
4. **No external calls** — banner is purely data-driven, no API polling
5. **Server-side rendering** — no JavaScript fetches, no CORS concerns
