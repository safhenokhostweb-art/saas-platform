# Canonical Frontend Parity Governance — Phase 9.5-B

## Principle

**Every UI feature in Forms, Tickets, AI panels, Analytics, Dashboards, Notifications, and Governance MUST render from the canonical package view only.**

`customer-site` MUST NEVER contain duplicate Blade implementations for module views.

## Enforcement Architecture

```
saas-platform (canonical authority)
  └── packages/saas-platform/modules/forms/resources/views/   ← AUTHORITATIVE
  └── packages/saas-platform/modules/tickets/Resources/views/ ← AUTHORITATIVE
  └── packages/saas-platform/core-utils/resources/views/      ← SHARED COMPONENTS

customer-site
  └── NO admin/forms/, NO admin/tickets/, NO site/forms/, NO site/tickets/ overrides
  └── Receives all UI through symlinked packages
```

## FrontendCanonicalParityEnforcer

Class: `SaaSPlatform\CoreUtils\Services\FrontendCanonicalParityEnforcer`

### Audit Dimensions (10 checks)

1. **no_forbidden_overrides** — customer-site has zero forbidden Blade overrides
2. **canonical_views_exist** — all required package views are present
3. **core_ui_components** — all 13+ core-ui components are present
4. **no_duplicate_namespaces** — no provider re-declares `core-ui` namespace
5. **blade_directives** — `@platform` / `@tenant` directives registered

### Scoring

| Score   | Grade | Meaning                        |
|---------|-------|--------------------------------|
| 97–100  | A+    | Perfect parity                 |
| 90–96   | A     | Production-ready               |
| 80–89   | B     | Minor drift — review needed    |
| 70–79   | C     | Significant drift — fix before release |
| <70     | F     | Critical violations — block deploy |

## Commands

```bash
# Audit parity
php artisan frontend:validate-canonical-parity --json
php artisan frontend:validate-canonical-parity --strict --fail-on-warning

# Enforce parity (detects + reports enforcement actions)
php artisan frontend:enforce-parity --dry-run
php artisan frontend:enforce-parity --json
```

## Forbidden Paths in customer-site

The following paths in `customer-site` are considered violations:
- `resources/views/admin/forms/`
- `resources/views/admin/tickets/`
- `resources/views/site/forms/`
- `resources/views/site/tickets/`
- `resources/views/vendor/modules-forms/`
- `resources/views/vendor/modules-tickets/`

## Allowed Differences Between Platform and Tenant

| Feature            | saas-platform | customer-site |
|--------------------|---------------|---------------|
| View files          | Same package  | Same package  |
| Blade components    | Same package  | Same package  |
| Visible actions     | Full admin    | Tenant-scoped |
| Capabilities        | All           | Per plan      |
| Quotas              | Unlimited     | Plan-based    |
| Feature flags       | All enabled   | Per config    |

## ci Integration

Add to `.github/workflows/ci.yml`:

```yaml
- name: Validate Frontend Parity
  run: php artisan frontend:validate-canonical-parity --strict --fail-on-warning
```
