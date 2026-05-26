# PHASE 7.15 — Final Validation Report: Canonical UI & Tenant Experience Unification

**Completed:** 2026-05-25  
**Status:** COMPLETE — Architecture enforced, route migration deferred to PHASE 7.2  
**Scope:** saas-platform + customer-site + packages/saas-platform/core-utils

---

## Deliverables

| # | Deliverable                             | Status  | Location |
|---|-----------------------------------------|---------|----------|
| 1 | UI Authority Audit                      | ✅ Done | `docs/phase-7.15/UI-AUTHORITY-AUDIT.md` |
| 2 | SharedLayoutResolver                    | ✅ Done | `core-utils/src/Services/SharedLayoutResolver.php` |
| 3 | ModuleNavigationService                 | ✅ Done | `core-utils/src/Services/ModuleNavigationService.php` |
| 4 | OverrideAuthorityValidator              | ✅ Done | `core-utils/src/Services/OverrideAuthorityValidator.php` |
| 5 | CanonicalFrontendValidationService      | ✅ Done | `core-utils/src/Services/CanonicalFrontendValidationService.php` |
| 6 | `platform:validate-ui-authority` command| ✅ Done | `core-utils/src/Console/Commands/ValidateUiAuthorityCommand.php` |
| 7 | `module-card` component                 | ✅ Done | `core-utils/resources/views/components/module-card.blade.php` |
| 8 | `stats-card` component                  | ✅ Done | `core-utils/resources/views/components/stats-card.blade.php` |
| 9 | `module-actions` component              | ✅ Done | `core-utils/resources/views/components/module-actions.blade.php` |
| 10| `module-status-badge` component         | ✅ Done | `core-utils/resources/views/components/module-status-badge.blade.php` |
| 11| `data-table` component                  | ✅ Done | `core-utils/resources/views/components/data-table.blade.php` |
| 12| `filter-bar` component                  | ✅ Done | `core-utils/resources/views/components/filter-bar.blade.php` |
| 13| `empty-state` component                 | ✅ Done | `core-utils/resources/views/components/empty-state.blade.php` |
| 14| `table-pagination` component            | ✅ Done | `core-utils/resources/views/components/table-pagination.blade.php` |
| 15| `status-badge` component                | ✅ Done | `core-utils/resources/views/components/status-badge.blade.php` |
| 16| CoreUtilsServiceProvider updated        | ✅ Done | Registers all new services + views + command |
| 17| Legacy views deprecated (saas-platform) | ✅ Done | admin/support-tickets/ — @deprecated markers |
| 18| Legacy views deprecated (customer-site) | ✅ Done | admin/support/ — @deprecated markers |

---

## Architecture Decisions

### AD-1: SharedLayoutResolver replaces LayoutResolver
The existing `LayoutResolver` was hardcoded to `forms.layouts.*`. The new `SharedLayoutResolver` accepts any module slug:
```php
$resolver->adminLayout('tickets');  // reads config('tickets.layouts.admin')
$resolver->adminLayout('forms');    // reads config('forms.layouts.admin')
$resolver->adminLayout();           // falls back to context-based default
```
Both services remain registered — `LayoutResolver` is not removed (backward compatibility).

### AD-2: ModuleNavigationService is data-driven from module manifests
Navigation items are derived from `config('module-manifest-*')` — the same manifests registered by each module's ServiceProvider. No hardcoded menu items. Sidebar rendering in both projects can be unified by looping over `ModuleNavigationService::getSidebarItems()`.

### AD-3: core-utils gets its first resources/ directory
The `core-utils` package was previously PHP-only. PHASE 7.15 establishes it as the canonical Blade component authority with view namespace `core-ui`. All shared UI primitives live here, published via `vendor:publish --tag=core-ui-views`.

### AD-4: @deprecated markers — no destructive moves
Legacy views are marked `@deprecated` with canonical replacements noted. Moving or deleting them requires route-level verification (PHASE 7.2). This prevents accidental breakage.

### AD-5: CanonicalFrontendValidationService runs 10 structural checks
The validation service checks layout existence, package view resolution, override paths, component availability, tenant isolation, sidebar authority, runtime gates, and module card structure — all without requiring a running browser.

---

## Success Criteria Assessment

| Criterion                                          | Status              | Notes |
|----------------------------------------------------|---------------------|-------|
| Forms UI identical across both projects            | ⚠️ Architecture done | Route migration needed (PHASE 7.2) |
| Tickets UI identical across both projects          | ⚠️ Architecture done | Route migration needed (PHASE 7.2) |
| No `@platform` leakage in tenant UI               | ✅ Verified          | All site/ views clean |
| No duplicated **active** views                     | ⚠️ Legacy exists     | @deprecated, routes still active |
| No hardcoded layouts in package views              | ✅ Enforced          | All use config() |
| Sidebar authority unified (data-driven)            | ✅ Architecture done | ModuleNavigationService ready |
| Shared components canonicalized                    | ✅ Complete          | 9 components in core-ui |
| Runtime gates control visibility                   | ✅ Complete          | PlatformContext + TicketGateService |
| Package views are sole authority (new routes)      | ✅ Complete          | tickets::, forms:: namespaces |
| customer-site becomes pure runtime consumer        | ⚠️ Partial           | Requires PHASE 7.2 route migration |

---

## Validation Command

```bash
# Run all 10 frontend authority checks
php artisan platform:validate-ui-authority

# JSON output for CI
php artisan platform:validate-ui-authority --json

# Exit with code 1 if any check fails (CI gates)
php artisan platform:validate-ui-authority --fail-on-warning

# Full drift check suite
php artisan drift:blades
php artisan drift:contracts
php artisan drift:authority
php artisan platform:validate-authority
php artisan platform:validate-ui-authority
```

---

## Component Usage Examples

### module-card
```blade
@include('core-ui::components.module-card', [
    'slug'        => 'tickets',
    'name'        => 'تیکت‌های پشتیبانی',
    'version'     => '1.1.0',
    'description' => 'سیستم تیکت با SLA و پورتال مشتری',
    'status'      => 'active',
    'route'       => 'tickets.admin.index',
])
```

### stats-card
```blade
@include('core-ui::components.stats-card', [
    'label' => 'تیکت‌های باز',
    'value' => $openCount,
    'color' => 'yellow',
    'trend' => 'up',
    'change' => '+3 امروز',
    'href'  => route('tickets.admin.index', ['status' => 'open']),
])
```

### filter-bar
```blade
@include('core-ui::components.filter-bar', [
    'action'      => route('tickets.admin.index'),
    'placeholder' => 'جستجو در تیکت‌ها...',
    'searchName'  => 'search',
    'searchValue' => request('search'),
])
```

### data-table with pagination
```blade
@include('core-ui::components.data-table', [
    'headers' => [['label' => '#'], ['label' => 'موضوع'], ['label' => 'وضعیت']],
])

{{-- inside slot: --}}
@foreach($tickets as $ticket)
<tr>
    <td class="px-4 py-3 text-sm">{{ $ticket->id }}</td>
    <td class="px-4 py-3 text-sm">{{ $ticket->subject }}</td>
    <td class="px-4 py-3">
        @include('core-ui::components.module-status-badge', ['status' => $ticket->status])
    </td>
</tr>
@endforeach

@include('core-ui::components.table-pagination', ['paginator' => $tickets])
```

---

## Deferred to PHASE 7.2

| Work Item                                               | Reason for Deferral |
|---------------------------------------------------------|---------------------|
| Update routes/web.php to point to canonical controllers | Requires route-level testing in live app |
| Delete legacy view directories                          | After route migration confirms no breakage |
| Update customer-site AdminMenu to use ModuleNavigationService | Requires customer-site bootstrap update |
| customer-site sidebar integration with module manifests  | Requires deploying core-utils to customer-site |
| icon size canonicalization (w-4 → w-5)                  | CSS-only change, safe but low priority |

---

## Governance Rules (ALL future modules must follow)

1. **Package views are the sole authority.** No module view exists outside its package.
2. **Never hardcode layout strings.** Always use `config('module.layouts.key', 'fallback')`.
3. **`@tenant` wrapper** on every `site/` view's content section.
4. **No `@platform`** in any `site/` view.
5. **UI differences between contexts** via gates only — never via duplicate views.
6. **New shared UI primitives** go to `core-ui::components.*` — never duplicated per-module.
7. **Sidebar items** registered via module manifest `menu[]` — never hardcoded Blade fragments.
8. **Run `platform:validate-ui-authority`** before every module release.
