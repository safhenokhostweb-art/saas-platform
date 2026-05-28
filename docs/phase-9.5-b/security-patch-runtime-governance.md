# Security Patch Runtime Governance — Phase 9.5-B

## Overview

Security patch governance ensures that critical security updates are visible, enforceable, and cryptographically verifiable across all tenant contexts.

## SecurityPatchDashboardService

Provides real-time security posture data:

```php
$dashboard->getActivePatchWarnings();     // advisories with severity ≥ medium
$dashboard->getRiskScore();               // 0-100 score with grade
$dashboard->getStagedRolloutStatus();     // active staged rollout state
$dashboard->getModuleVulnerabilityNotices(); // per-module vuln data
$dashboard->getUpgradeImpactAnalysis($patchId); // breaking changes, downtime
```

## Risk Score Calculation

| Advisory Severity | Score Deduction |
|-------------------|-----------------|
| critical          | 30              |
| high              | 15              |
| medium            | 5               |
| low               | 2               |

Score starts at 100, deductions applied per active advisory.

## Forced Security Patches

Critical patches (severity = critical) MUST:
- Bypass license restrictions (`bypasses_license: true`)
- Be cryptographically verifiable (SHA-256)
- Never break tenant runtime
- Support staged rollout with per-tenant targeting
- Be visible in dashboard even if operator dismisses other warnings

## Dashboard Integration

`core-ui::security-patch-banner` renders at the top of admin layouts:
- Red banner for critical advisories (non-dismissible)
- Orange banner for high advisories (dismissible)
- Yellow banner for medium advisories (dismissible)
- Shows advisory title, module, and required action

## Staged Rollout Architecture

Security patches can be applied progressively:
1. Canary (1% tenants)
2. Early adopters (10%)
3. General availability (50%)
4. Full rollout (100%)

Status tracked in `security:staged_rollout` cache key.

## SecurityAdvisoryService (Phase 9.4 foundation)

- `getActiveAdvisories()` — returns advisories from cache / config
- Advisory shape: `{id, severity, title, module, action, forced, bypasses_license, published_at}`

## Governance Invariants

1. Critical patches cannot be suppressed by tenant
2. All patches are SHA-256 verified before application
3. Staged rollout never exceeds 100% of tenants
4. Impact analysis must be generated before full rollout
5. Emergency patches applied to all tenants immediately
