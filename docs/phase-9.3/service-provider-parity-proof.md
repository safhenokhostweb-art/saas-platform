# Phase 9.3 — Service Provider Parity Proof
**Date:** 2026-05-27  
**Scope:** FormsServiceProvider + TicketsServiceProvider boot pipeline

---

## OVERVIEW

Both FormsServiceProvider and TicketsServiceProvider now implement the same boot pipeline, ensuring that views, directives, and components register identically regardless of which application loads the modules.

---

## FormsServiceProvider — Phase 9.3 State

```php
boot() pipeline:
  loadViewsFrom(..., 'forms')
  Blade::anonymousComponentNamespace('forms::core-ui.components', 'core-ui')  ← CANONICAL
  loadRoutesFrom(routes/web.php)
  loadMigrationsFrom(database/migrations)
  registerCommands() {
    // Phase 9.2
    ValidateFormsRuntimeStabilityCommand
    ValidateFormsFrontendConsistencyCommand
    ValidateFormsEnterpriseUxCommand
    ValidateFormsAssetsCommand
    // Phase 9.3 NEW
    ValidateFrontendParityCommand         ← frontend:validate-parity
    FrontendRuntimeDiagnosticsCommand     ← frontend:runtime-diagnostics
  }
  publishes(config, manifest, views, migrations)
  Blade::if('platform', ...)
  Blade::if('tenant', ...)
  Blade::if('capability', ...)
  view()->composer('forms::admin.*', ...)

singletons registered (Phase 9.3 additions):
  FrontendVisualParityEngine
  FrontendRuntimeRecoveryService
  CrossContextRuntimeValidationService
```

---

## TicketsServiceProvider — Phase 9.3 State

```php
boot() pipeline:
  registerRoutes() { admin.php, site.php, api.php }
  registerViews() {
    loadViewsFrom(..., 'tickets')
    // NEW Phase 9.3:
    if (! app->bound('core-ui.namespace.registered')) {
      Blade::anonymousComponentNamespace('tickets::core-ui.components', 'core-ui')
      app->instance('core-ui.namespace.registered', true)
    }
  }
  registerBladeDirectives() {  ← NEW Phase 9.3
    Blade::if('platform', ...)
    Blade::if('tenant', ...)
    Blade::if('capability', ...)
  }
  registerMigrations()
  registerPolicies()
  registerPublishables()
  registerModuleAssets()
  registerViewComposers() { view()->composer('tickets::admin.*', ...) }
  registerCommands() {
    ValidateRuntimeAuthorityCommand
    MigrateTicketDataCommand
    ValidateTicketsIsolationCommand
    ValidateFrontendGovernanceCommand
  }

singletons registered (Phase 9.3 additions):
  TicketTimelineService
  TicketInternalNotesService
```

---

## Parity Proof

| Capability | FormsServiceProvider | TicketsServiceProvider | Parity |
|-----------|---------------------|----------------------|--------|
| View namespace | `forms::` | `tickets::` | ✅ Both |
| core-ui:: namespace | ✅ Canonical | ✅ Fallback | ✅ Both |
| @platform directive | ✅ | ✅ | ✅ Both |
| @tenant directive | ✅ | ✅ | ✅ Both |
| @capability directive | ✅ | ✅ | ✅ Both |
| View composer (layout) | ✅ admin.* | ✅ admin.* | ✅ Both |
| Migration loading | ✅ | ✅ | ✅ Both |
| Command registration | ✅ 8 commands | ✅ 4 commands | ✅ Both |

---

## Race Condition Prevention

If both FormsServiceProvider and TicketsServiceProvider are loaded (which is the normal case), the `core-ui.namespace.registered` binding prevents double-registration:

```php
// FormsServiceProvider::boot() runs first → registers canonical forms:: components
Blade::anonymousComponentNamespace('forms::core-ui.components', 'core-ui');

// TicketsServiceProvider::boot() runs second → skips (already registered)
if (! $this->app->bound('core-ui.namespace.registered')) {
    // Only executes if Forms module is NOT loaded
    Blade::anonymousComponentNamespace('tickets::core-ui.components', 'core-ui');
    $this->app->instance('core-ui.namespace.registered', true);
}
```

This means:
- **Normal (both modules):** Forms canonical components used — correct
- **Tickets-only deployment:** Tickets components used as fallback — acceptable
- **Forms-only deployment:** Forms canonical components used — correct
