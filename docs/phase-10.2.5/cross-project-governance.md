# Cross-Project Frontend Governance
## Phase 10.2.5

---

## Principle

All shared modules (Forms, Tickets, AI, Dashboards, future modules) MUST render
identically in both `saas-platform` and `customer-site`. The canonical source of
UI truth is the `saas-platform/core-utils` package.

---

## Cross-Project Contract

| Concern | Authority | Enforced by |
|---------|-----------|-------------|
| Design tokens | `core-utils/resources/css/design-tokens.css` | CI / token audit |
| Components | `core-ui::*` namespace | Component authority check |
| Module views | Single canonical view from package | No duplication policy |
| Permissions | `policies` / `gates` | Laravel policy layer |
| Feature toggling | `feature-flags` config | `@platformfeature` directive |
| UI customization | CSS variable overrides only | Token layer |

---

## How Cross-Project Governance is Enforced

### 1. Shared Package Architecture

`customer-site/composer.json` depends on `saas-platform/core-utils` via path repository.
This means:

- All canonical Blade views are loaded from the package
- All `x-core-ui::*` components resolve from `core-utils/resources/views/components/`
- All design tokens are imported from `core-utils/resources/css/design-tokens.css`

### 2. FrontendCrossRepositoryGovernanceValidator

Runs as part of the Phase 10.2.5 governance stack. Checks:

- Both repos have `frontend-governance.yml` workflows
- Both CI pipelines enforce identical job sets
- Both `app.css` files import canonical design tokens
- `CoreUtilsServiceProvider` registers `core-ui` namespace in both
- No `continue-on-error` bypasses in either workflow
- Both enforce `--fail-below=95` governance threshold

Run check:
```bash
php artisan frontend:validate-cross-repository-governance --strict
```

### 3. CI Pipeline Parity

Both repositories MUST have identical governance job structure:

```
static-blade-analysis → component-authority → enterprise-governance → frontend-parity
                      ↗ responsive-governance ↗
```

### 4. Drift Lockdown

`FrontendDriftLockdownService` scans both repositories simultaneously and reports
any divergence between them.

---

## Allowed Differences Between Repositories

The following differences are **permitted** and will not trigger governance violations:

| What | How | Example |
|------|-----|---------|
| Feature visibility | `@platformfeature('key')` | Admin-only buttons |
| User permission | `@can('permission')` | Edit vs. view-only |
| Context awareness | `PlatformContext::isTenant()` | Tenant vs. platform admin |
| Runtime capability | `CapabilityResolver::canAccess('key')` | Premium features |
| Tenant branding | CSS var override per tenant | `--ui-primary` overridden |

The following differences are **forbidden**:

- Different Blade templates for the same module view
- Different CSS class naming for the same component
- Different component names for the same UI element
- Inline styles in one repo that don't exist in the other
- Module-local design token systems

---

## Cross-Repository CI Check Schedule

In addition to per-PR enforcement, a weekly drift detection workflow runs:

`.github/workflows/drift-detection.yml`

This workflow:
1. Checks out both repositories
2. Compares governance workflow structure
3. Diffs design token imports
4. Reports any divergence as an artifact

---

## Recovery Procedure

When cross-project drift is detected:

1. Run: `php artisan frontend:validate-cross-repository-governance --json`
2. Review violations in output
3. Run: `php artisan frontend:lockdown-report`
4. Apply canonical replacements from the `replacements` map
5. Re-run: `php artisan frontend:validate-cross-repository-governance --strict`
6. Commit and push — CI will confirm governance passes
