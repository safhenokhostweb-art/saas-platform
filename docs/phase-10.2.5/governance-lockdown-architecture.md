# Frontend Governance Lockdown Architecture
## Phase 10.2.5

---

## Overview

Phase 10.2.5 completes the frontend governance system by transitioning from
"pipeline exists" to **fully enforced, self-healing, cross-project lockdown**.

The architecture consists of three layers:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  LAYER 3 — CI ENFORCEMENT                                               │
│  GitHub Actions (.github/workflows/frontend-governance.yml)             │
│  Branch protection (required-checks.json)                               │
│  PR blocking — no unsafe code merges to main/develop                   │
├─────────────────────────────────────────────────────────────────────────┤
│  LAYER 2 — GOVERNANCE VALIDATORS (PHP artisan commands)                 │
│  FrontendCrossRepositoryGovernanceValidator                             │
│  FrontendStaticGovernanceScanner (multi-language)                       │
│  EnterpriseFrontendGovernanceValidationService (17 dimensions)          │
│  FrontendDriftLockdownService                                           │
│  ModuleFrontendInheritanceBootstrapper                                  │
├─────────────────────────────────────────────────────────────────────────┤
│  LAYER 1 — CANONICAL DESIGN SYSTEM                                     │
│  core-utils/resources/css/design-tokens.css (single source of truth)   │
│  core-ui::* canonical components (authority namespace)                  │
│  CoreUtilsServiceProvider (auto-registration on boot)                   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Key Services (Phase 10.2.5)

| Service | Location | Purpose |
|---------|----------|---------|
| `FrontendCrossRepositoryGovernanceValidator` | `src/Frontend/` | Validates identical governance posture across both repos |
| `FrontendStaticGovernanceScanner` | `src/Frontend/` | Multi-language violation scanner (Blade/PHP/JS/TS/CSS) |
| `FrontendDriftLockdownService` | `src/Frontend/` | Detects and classifies all drift categories |
| `ModuleFrontendInheritanceBootstrapper` | `src/Frontend/` | Guarantees new modules inherit full governance automatically |
| `EnterpriseFrontendGovernanceValidationService` | `src/Frontend/` | 17-dimension master score aggregator |

---

## Governance Score Dimensions (17)

| # | Dimension | Weight | Target |
|---|-----------|--------|--------|
| 1 | Design token enforcement | 12 | 100% token coverage |
| 2 | Core-ui adoption | 12 | All canonical components present |
| 3 | Runtime consistency | 10 | Provider registration correct |
| 4 | Modal normalization | 8 | x-core-ui::modal used everywhere |
| 5 | Form normalization | 8 | x-core-ui::input/select/textarea |
| 6 | Table normalization | 8 | x-core-ui::data-table used |
| 7 | Responsive integrity | 8 | Touch targets, RTL, breakpoints |
| 8 | Accessibility | 8 | WCAG AA, aria, focus-visible |
| 9 | Mobile governance | 8 | Mobile sidebar, inset-x layout |
| 10 | Cross-context parity | 8 | Both repos import tokens |
| 11 | Runtime recovery | 5 | Recovery services present |
| 12 | Legacy drift detection | 5 | No inline styles / hex colors |
| 13 | Cross-project governance | 8 | CI parity across both repos |
| 14 | CI enforcement readiness | 7 | No continue-on-error, fetch-depth:0 |
| 15 | Drift lockdown readiness | 6 | LockdownService + Scanner present |
| 16 | Branch protection readiness | 5 | required-checks.json exists |
| 17 | Future module inheritance | 5 | Bootstrapper present, boot registered |

**Target: 100/100**

---

## Artisan Commands (Phase 10.2.5)

```bash
# Cross-repository governance parity check
php artisan frontend:validate-cross-repository-governance --strict --json

# Multi-language static governance scan
# (invoked via existing commands that use FrontendStaticGovernanceScanner)

# Drift lockdown report (all categories)
php artisan frontend:lockdown-report --json

# Enhanced auto-heal with cross-project analysis
php artisan frontend:auto-heal --cross-project --suggest-ci --dry-run

# Full enterprise governance score (17 dimensions)
php artisan frontend:validate-enterprise-governance --fail-below=95 --json
```

---

## Zero-Drift Invariant

The architecture enforces one invariant at all layers:

> **saas-platform and customer-site MUST render the same frontend for shared modules.**
> Differences are ONLY allowed through: policies, gates, feature flags, permissions,
> or runtime capabilities. NEVER through duplicated Blade views.

Any violation of this invariant fails CI and cannot merge.
