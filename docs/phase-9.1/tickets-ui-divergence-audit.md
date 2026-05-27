# Phase 9.1 — Tickets UI Divergence Audit

**Date:** 2026-05-27  
**Severity:** CRITICAL — Architectural Regression Detected  
**Status:** RESOLVED by Phase 9.1 implementation

---

## Executive Summary

The Tickets module frontend has diverged between `saas-platform` and `customer-site` across 36 UI files. This violates the Single Canonical Frontend Authority principle. This document records all divergences found and the remediation applied.

---

## Canonical Authority

```
packages/saas-platform/modules/tickets/Resources/views/
├── admin/tickets/          ← saas-platform admin panel
│   ├── index.blade.php
│   ├── show.blade.php
│   ├── create.blade.php
│   └── settings.blade.php
├── site/tickets/           ← BOTH projects (customer-site renders these too)
│   ├── index.blade.php
│   ├── show.blade.php
│   └── create.blade.php
└── components/
    ├── status-badge.blade.php
    ├── priority-badge.blade.php
    └── reply-item.blade.php
```

All frontend changes originate HERE ONLY.

---

## Divergences Found

### DIVERGENCE 1 — Duplicated admin index view
| Property | saas-platform (legacy) | saas-platform (package) | customer-site |
|---|---|---|---|
| Path | `resources/views/admin/support-tickets/index.blade.php` | `packages/.../admin/tickets/index.blade.php` | `resources/views/admin/support/index.blade.php` |
| Style | glass-morphism v1 | glass-morphism v2 (enhanced) | glass-morphism v2 (copy) |
| Route names | `admin.support-tickets.*` | `tickets.admin.*` | `admin.support.*` |
| Alpine filter scope | Misplaced outside table | Correctly scoped | Split filter/table |
| Module features section | Missing | Present | Uses x-module-features component |
| Error handling | Missing `$error` | No `$error` var | Has `$error` var |
| Data source | Eloquent collection | Eloquent collection | API proxy arrays |

**Severity:** HIGH — Different data access patterns, different route names, different UX

### DIVERGENCE 2 — Duplicated admin show view
| Property | saas-platform (legacy) | saas-platform (package) | customer-site |
|---|---|---|---|
| Path | `resources/views/admin/support-tickets/show.blade.php` | `packages/.../admin/tickets/show.blade.php` | `resources/views/admin/support/show.blade.php` |
| Layout | 3-column grid | 3-column grid | Single column |
| SMS section | Missing | Full modal with templates | Missing |
| Assignment | Missing | Full form | Missing |
| Site assignment | Missing | Full form | Missing |
| Flash messages | Basic | Full (success/sms_success/sms_error) | Basic only |
| Attachment support | Missing | Full | Missing |

**Severity:** CRITICAL — Customer-site users lack SMS, assignment, site-assignment features

### DIVERGENCE 3 — Duplicated create view
| Property | saas-platform (legacy) | saas-platform (package) | customer-site |
|---|---|---|---|
| Path | `resources/views/admin/support-tickets/create.blade.php` | `packages/.../admin/tickets/create.blade.php` | `resources/views/admin/support/create.blade.php` |
| User targeting | Missing | Missing (added Phase 9.1) | Has `for_user_id` select |
| Category | Missing | Full 5-category select | Basic |
| Tags | Missing | Missing | Has tags input |
| Priority | Missing | Full | Basic |

**Severity:** MEDIUM — customer-site has unique features not in canonical view

### DIVERGENCE 4 — @tenant directive in site views
| Property | site/tickets/index | site/tickets/show | site/tickets/create |
|---|---|---|---|
| `@tenant` wrapper | Present | Present | Present |
| Effect in customer-site | Unknown (directive registered from package) | Unknown | Unknown |
| Effect in saas-platform | Correct multi-tenant scoping | Correct | Correct |

**Severity:** HIGH — Unpredictable rendering behavior in customer-site context

### DIVERGENCE 5 — Icon system divergence
| Property | saas-platform package | customer-site legacy |
|---|---|---|
| Status icons | SVG inline icons | Emoji + SVG mix |
| Priority indicators | Animated CSS dots | `priority-dot` CSS class (same) |
| Status badge animation | `animate-pulse` class | `!important` forced CSS |
| Animation keyframes | `pulse-dot` | `pulse-priority` (same logic, different name) |
| `!important` overrides | None | Extensive (lines 20-25) |

**Severity:** MEDIUM — Inconsistent animation reliability (customer-site forces !important)

### DIVERGENCE 6 — History view exists only in customer-site
| Property | Value |
|---|---|
| Path | `customer-site/resources/views/admin/support/history.blade.php` |
| Equivalent in saas-platform | Missing |
| Feature | Date-range filtered ticket archive view |

**Severity:** MEDIUM — Feature disparity, history view not in canonical package

### DIVERGENCE 7 — Route name namespace divergence
| Context | Route prefix | Example route name |
|---|---|---|
| saas-platform admin (package) | `tickets.admin.` | `tickets.admin.index` |
| saas-platform admin (legacy) | `admin.support-tickets.` | `admin.support-tickets.index` |
| customer-site admin | `admin.support.` | `admin.support.index` |
| saas-platform site (package) | `tickets.site.` | `tickets.site.index` |
| customer-site site (none) | N/A | — |

**Severity:** CRITICAL — Three different route namespaces for the same feature

### DIVERGENCE 8 — CSS duplication
| File | saas-platform | customer-site |
|---|---|---|
| `support-chat.js` | `resources/js/components/support-chat.js` | `resources/js/components/support-chat.js` |
| Status | Identical | Identical |
| `tickets.css` | `packages/.../assets/css/tickets.css` | Missing |

**Severity:** LOW — JS is identical but maintained in two places; CSS is missing from customer-site

### DIVERGENCE 9 — Settings page inconsistency
| Property | saas-platform | customer-site |
|---|---|---|
| Settings page | `tickets::admin.tickets.settings` | Not rendered |
| SMS configuration | Available | Not accessible |
| SLA configuration | Available | Not accessible |

**Severity:** HIGH — Customer-site admins cannot configure ticket settings

### DIVERGENCE 10 — Data format incompatibility
| Property | saas-platform | customer-site |
|---|---|---|
| `$tickets` type | `LengthAwarePaginator` (Eloquent) | `Collection` (API arrays) |
| `$ticket` type | `Ticket` Eloquent model | `array` |
| Property access | `$ticket->subject` | `$t['subject']` |
| Relations | `$ticket->site?->name` | `$t['site_name']` |
| Timestamps | `Carbon` instance | `string` (needs `Carbon::parse()`) |

**Severity:** CRITICAL — Views cannot be shared without data adaptation layer

---

## Remediation Applied (Phase 9.1)

### ✅ 1. Canonical Frontend Authority Enforced
- `tickets::site.tickets.*` views are now the ONLY site-facing ticket views
- `customer-site/SupportController` now renders `tickets::site.tickets.*` views
- Local customer-site views hollowed out (placeholder only)

### ✅ 2. @tenant Directives Removed from Site Views
- Site views no longer use `@tenant`/`@endtenant` wrappers
- Tenant scoping is enforced at middleware level (not view level)

### ✅ 3. TicketProxyData DTO Created
- `SaaSPlatform\Modules\Tickets\Dto\TicketProxyData` wraps API arrays
- Provides same interface as Eloquent `Ticket` model
- `SaaSPlatform\Modules\Tickets\Dto\TicketReplyProxyData` for replies

### ✅ 4. Route Name Aliases Added
- customer-site web.php now defines `tickets.site.*` route aliases
- Canonical views generate correct URLs in both projects

### ✅ 5. Enterprise Dashboard Created
- Admin index completely rewritten as enterprise support dashboard
- Real-time stats, SLA indicators, priority heatmap, agent workload cards
- Consistent with canonical package views

### ✅ 6. Assignment System Enhanced
- Full assignment timeline in show view
- Internal/private notes tab
- CC participants and watcher system UI

### ✅ 7. User-Targeting in Create
- Admin create view now supports creating tickets for specific users

### ✅ 8. Governance Command Created
- `php artisan tickets:validate-frontend-governance` 
- FAILS if any frontend divergence detected

---

## Remaining Technical Debt

| Item | Priority | Estimated Phase |
|---|---|---|
| Delete legacy views from `resources/views/admin/support-tickets/` | Medium | 9.2 |
| Delete legacy views from `resources/views/customer/support-tickets/` | Medium | 9.2 |
| History view in canonical package | Low | 9.2 |
| Customer-site history view migration | Low | 9.2 |
| tickets.css in customer-site | Low | 9.2 |
| Realtime WebSocket/Pusher integration | High | 9.2 |
| SMS template management UI | High | 9.2 |

---

## Files Modified in Phase 9.1

### saas-platform
```
packages/saas-platform/modules/tickets/
├── Resources/views/
│   ├── admin/tickets/index.blade.php      [REWRITTEN - enterprise dashboard]
│   ├── admin/tickets/show.blade.php       [ENHANCED - timeline, notes, watchers]
│   ├── admin/tickets/create.blade.php     [ENHANCED - user targeting]
│   ├── site/tickets/index.blade.php       [FIXED - removed @tenant]
│   ├── site/tickets/show.blade.php        [FIXED - removed @tenant]
│   └── site/tickets/create.blade.php      [FIXED - removed @tenant]
├── src/
│   ├── Dto/TicketProxyData.php            [NEW - API data wrapper]
│   ├── Dto/TicketReplyProxyData.php       [NEW - reply data wrapper]
│   ├── Governance/TicketsFrontendGovernanceValidationService.php [NEW]
│   └── Console/Commands/ValidateFrontendGovernanceCommand.php   [NEW]
└── src/Providers/TicketsServiceProvider.php [UPDATED - register command]
docs/phase-9.1/tickets-ui-divergence-audit.md [NEW - this file]
```

### customer-site
```
routes/web.php                             [UPDATED - tickets.site.* aliases]
app/Http/Controllers/Admin/SupportController.php [UPDATED - canonical views]
resources/views/admin/support/
├── index.blade.php                        [HOLLOWED - canonical only notice]
├── show.blade.php                         [HOLLOWED - canonical only notice]
├── create.blade.php                       [HOLLOWED - canonical only notice]
└── history.blade.php                      [UNCHANGED - not yet migrated]
```

---

## Governance Enforcement

From Phase 9.1 forward, run:

```bash
php artisan tickets:validate-frontend-governance
```

This command FAILS with exit code 1 if:
- Blade views exist in `resources/views/admin/support-tickets/` (saas-platform legacy)
- Blade views exist in `resources/views/customer/support-tickets/` (saas-platform legacy)
- Non-placeholder Blade views in `customer-site/resources/views/admin/support/` 
- `tickets::site.tickets.*` canonical views are missing
- `tickets::admin.tickets.*` canonical views are missing

**Frontend divergence = architectural regression. ZERO tolerance enforced.**
