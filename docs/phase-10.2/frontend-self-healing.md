# Frontend Self-Healing Architecture

**Phase:** 10.2  
**Service:** `FrontendDriftAutoRecoveryService`  
**Command:** `php artisan frontend:auto-heal`

---

## Overview

The self-healing service detects and automatically repairs frontend governance failures that can occur at runtime — broken Blade namespaces, missing token imports, stale published views, and customer-site divergence.

---

## Issue Categories

| Type | Severity | Auto-Repairable | Description |
|------|----------|-----------------|-------------|
| `namespace` | Critical | No (requires reboot) | `core-ui` Blade namespace not registered |
| `missing_component` | High | No | Canonical component file missing from disk |
| `stale_published` | High | Flag only | Vendor-published views may override canonical components |
| `customer_divergence` | Critical | Partial | customer-site has forbidden module view overrides |
| `token_import` | High | Yes | app.css missing canonical design-tokens.css import |

---

## Auto-Repair Actions

The service can automatically repair:

- **Missing token imports** — Adds `@import` to `app.css` files that are missing it
- After repair, runs `php artisan view:clear` to flush Blade cache

The service flags for manual review (cannot auto-repair):
- Stale published views (deleting requires human verification)
- Customer-site view overrides (deleting requires human verification)

---

## Command Usage

### Dry Run (see what would happen)
```bash
php artisan frontend:auto-heal --dry-run
```

Output:
```
DRY RUN — no changes will be made

Frontend Health Score: 85/100

Issues that would be repaired:
┌──────────────┬──────────┬──────────────────────────────────────────┬─────────────────────┐
│ Type         │ Severity │ Description                              │ Repair              │
├──────────────┼──────────┼──────────────────────────────────────────┼─────────────────────┤
│ token_import │ high     │ customer-site app.css missing token import│ add_token_import    │
└──────────────┴──────────┴──────────────────────────────────────────┴─────────────────────┘

Run php artisan frontend:auto-heal (without --dry-run) to apply repairs.
```

### Apply Repairs
```bash
php artisan frontend:auto-heal
```

### JSON Output (for monitoring)
```bash
php artisan frontend:auto-heal --dry-run --json
```

---

## Health Score

The health score deducts:
- **20 points** per critical issue
- **10 points** per high issue
- **5 points** per medium issue

A score of 100 means no detectable governance issues.

---

## Integration with CI

The CI pipeline runs `frontend:auto-heal --dry-run --json` and uploads the report as an artifact. If critical issues are detected in dry run, the pipeline logs them but does not fail (the other validation commands handle failures).
