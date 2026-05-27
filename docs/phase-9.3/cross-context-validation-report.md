# Phase 9.3 — Cross-Context Runtime Validation Report
**Date:** 2026-05-27  
**Services:** `CrossContextRuntimeValidationService`, `FrontendRuntimeRecoveryService`  
**Commands:** `frontend:validate-parity`, `frontend:runtime-diagnostics`

---

## OVERVIEW

Phase 9.3 introduces two new validation layers for the cross-context runtime:

1. **`CrossContextRuntimeValidationService`** — 9-dimension parity audit (route/component/asset/modal/toast/builder/animation/state/gates)
2. **`FrontendRuntimeRecoveryService`** — 7-check live diagnostic with auto-recovery capabilities

---

## CrossContextRuntimeValidationService

### 9 Dimensions

| # | Dimension | Weight | What it verifies |
|---|-----------|--------|-----------------|
| 1 | Route coverage | 15 pts | All required tickets routes registered (index/show/create/store/bulk/status/assign/replies) |
| 2 | Component namespace | 15 pts | core-ui:: anonymous namespace in FormsServiceProvider |
| 3 | Asset integrity | 10 pts | SRI hash on CDN scripts in builder.blade.php |
| 4 | Modal system | 10 pts | Alpine `showDeleteConfirm` present, no native `confirm()` |
| 5 | Toast system | 10 pts | `showToast()` + deduplication in builder |
| 6 | Builder state | 10 pts | AbortController + updateFieldOrderFromDom + pushHistory + saveStatus |
| 7 | Animation tokens | 10 pts | `--forms-anim-*` tokens in design-tokens.blade.php |
| 8 | Alpine state safety | 10 pts | `_saveController.abort()` + `sortableInstance.destroy()` + debounce |
| 9 | Tenant/platform gates | 10 pts | @platform/@tenant in both FormsServiceProvider + TicketsServiceProvider |

**Total: 100 pts**  
**isValid(): score >= 85**

### Usage

```php
$service = app(CrossContextRuntimeValidationService::class);
$results = $service->validate();
$score   = $service->score();
$valid   = $service->isValid();
```

---

## FrontendRuntimeRecoveryService

### 7 Checks

| Check | Auto-recovers? | What it detects |
|-------|----------------|-----------------|
| `blade_namespaces_forms` | ✅ Yes | `forms::` namespace not registered |
| `blade_namespaces_tickets` | ✗ No | `tickets::` namespace not registered |
| `directive_platform` | ✗ No (warning) | `@platform` directive not registered |
| `directive_tenant` | ✗ No (warning) | `@tenant` directive not registered |
| `directive_capability` | ✗ No (warning) | `@capability` directive not registered |
| `config_*` | ✗ No | Missing `forms.layouts.admin` / `tickets.layouts.admin` |
| `view_*` | ✗ No | Forms/tickets views not resolvable |
| `core_ui_namespace` | ✅ Yes | core-ui:: namespace not registered — auto-registers |
| `route_*` | ✗ No (warning) | Required routes missing |
| `migrations` | ✗ No (warning) | Pending migration check |

### Auto-Recovery Mechanism

When the service detects a recoverable failure, it calls the recovery closure:

```php
private function attemptRecovery(string $key, callable $recovery): void
{
    try {
        $recovery();  // e.g., Blade::anonymousComponentNamespace(...)
        $this->diagnostics[$key]['status'] = 'recovered';
    } catch (\Throwable $e) {
        $this->recoveries[$key] = ['status' => 'recovery_failed', ...];
    }
}
```

Recovery is non-destructive — it only adds missing registrations, never removes existing ones.

### Cache

Results are cached for 5 minutes (`Cache::put(CACHE_KEY, $result, 300)`).

```bash
# Flush cache + run fresh
php artisan frontend:runtime-diagnostics --no-cache

# Flush only
php artisan frontend:runtime-diagnostics --flush-cache
```

---

## Command Output Example

```
 🔧 Frontend Runtime Diagnostics
  Phase 9.3 — Cross-Context Runtime Parity
  Timestamp: 2026-05-27T10:00:00+00:00

  ✓  forms:: view namespace registered
  ✓  tickets:: view namespace registered
  ⚠  @platform Blade directive not registered — @platform/@tenant blocks will silently fail
  ✓  Forms admin layout config: admin.layout
  ✓  Tickets admin layout config: admin.layout
  ✓  forms::admin.forms.index resolvable
  ✓  forms::admin.forms.builder resolvable
  ✓  tickets::admin.tickets.index resolvable
  ✓  tickets::admin.tickets.show resolvable
  ↻  core_ui_namespace: Auto-recovered: core_ui_namespace [AUTO-RECOVERED]

  Runtime Score  85/100  [█████████████████░░░]

  ✅  Frontend runtime is HEALTHY
```

---

## Service Registration (FormsServiceProvider)

```php
$this->app->singleton(CrossContextRuntimeValidationService::class);
$this->app->singleton(FrontendRuntimeRecoveryService::class);

// Commands:
ValidateFrontendParityCommand::class,          // frontend:validate-parity
FrontendRuntimeDiagnosticsCommand::class,      // frontend:runtime-diagnostics
```
