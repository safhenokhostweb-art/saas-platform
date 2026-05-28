# Frontend Drift Prevention
## Phase 10.2.5

---

## What is Frontend Drift?

Frontend drift occurs when UI code deviates from the canonical design system.
This manifests as:

1. **Raw controls** — `<button>`, `<input>`, `<select>`, `<textarea>` outside `x-core-ui::*`
2. **Hardcoded values** — `#3b82f6`, `z-index: 9999`, `animation: 0.3s ease`
3. **Module-local styles** — `<style>` blocks or separate CSS files per module
4. **Cross-project divergence** — different Blade templates for the same module in saas-platform vs customer-site
5. **Token bypasses** — using Tailwind `text-blue-500` instead of `var(--ui-primary)`
6. **Duplicate primitives** — local `Button.blade.php` that shadows `x-core-ui::button`

---

## Drift Prevention Architecture

### Gate 1 — Static Analysis (pre-push)

The fastest gate. Runs without Laravel boot on changed files only.
Blocks commits with raw controls or hardcoded hex colors within seconds.

### Gate 2 — Component Authority (CI)

Full artisan scan of all module views. Detects any unauthorized HTML element
using `FrontendComponentGovernanceService`. Reports file, line, severity.

### Gate 3 — Enterprise Governance Score (CI)

Aggregates 17 dimensions. Score must be ≥95. If any dimension falls below 80,
the overall score may drop below 95, blocking the merge.

### Gate 4 — Drift Lockdown Report (CI)

`FrontendDriftLockdownService` scans both repositories simultaneously.
Cross-project divergence is detected and reported as CI failure.

### Gate 5 — Branch Protection

GitHub branch protection rules enforce that all 5 CI jobs pass before
any merge to `main` or `develop`. Admins are not exempt.

---

## Canonical Replacements Quick Reference

| Drift Pattern | Canonical Replacement |
|--------------|----------------------|
| `<button>` | `<x-core-ui::button>` |
| `<input>` | `<x-core-ui::input>` |
| `<select>` | `<x-core-ui::select>` |
| `<textarea>` | `<x-core-ui::textarea>` |
| `<table>` | `<x-core-ui::data-table>` |
| `window.alert()` | `<x-core-ui::alert>` + Alpine |
| `window.confirm()` | `<x-core-ui::confirm-modal>` |
| `#hex` in `style=` | `var(--ui-*)` |
| `z-index: 9999` | `var(--ui-z-modal)` |
| `animation: 0.3s` | `var(--ui-duration-*)` + reduced-motion |
| `text-blue-500` | `text-[color:var(--ui-primary)]` |
| Inline `<style>` | Design token utilities |

---

## Drift Detection Commands

```bash
# Full lockdown report (both repositories)
php artisan frontend:lockdown-report

# Cross-repository parity check
php artisan frontend:validate-cross-repository-governance --strict

# Component-level drift
php artisan frontend:validate-components --strict

# Auto-heal with cross-project analysis
php artisan frontend:auto-heal --cross-project --dry-run
```

---

## Developer Workflow

When creating new frontend code:

1. **Never** write raw `<button>`, `<input>`, etc.
2. **Always** use `x-core-ui::*` canonical components
3. **Always** use `var(--ui-*)` for colors, spacing, z-index, animations
4. **Never** add `<style>` blocks to Blade views
5. **Never** create module-local CSS files
6. **Always** extend `layouts.admin` in admin views
7. Run `php artisan frontend:lockdown-report` before opening a PR

---

## Architecture Invariant

```
saas-platform UI === customer-site UI
         for all shared modules
```

This invariant is enforced at every layer: design system, component registry,
CI pipeline, and branch protection. It cannot be bypassed without explicit
exception approval.
