# INSTALLER IMPLEMENTATION ROADMAP
> Date: 2026-06-26
> Status: FINAL — ROADMAP DECIDED
> Authority: INSTALLER_ARCHITECTURE_DECISION_PHASE
> Constraint: LAUNCH FREEZE ACTIVE — implementation begins post-launch only

---

## Pre-Implementation Blockers (Must Be Resolved First)

These two blockers must be resolved before any phase can be built:

| Blocker | Resolution | Spec |
|---------|------------|------|
| BLOCKER-1: PlatformSeeder does not exist | Build AdminUserSeeder + DefaultPlanSeeder + PlatformSettingsSeeder + PlatformSeeder | docs/PLATFORM_SEEDER_SPECIFICATION.md |
| BLOCKER-2: Deployment strategy mismatch | Extend deploy.ps1 to also upload release ZIP to Arvan `saas-platform/releases/` | docs/INSTALLER_ARCHITECTURE_DECISION.md Phase A |

**BLOCKER-1 estimated effort:** 4 hours (3 seeders + PlatformSeeder master + validation test)
**BLOCKER-2 estimated effort:** 2 hours (add ArvanBackupService call to deploy.ps1; no logic change)

Both blockers are **compatible with LAUNCH FREEZE** (bug fixes/operations, no new features).
Both can be implemented during freeze before post-launch phases begin.

---

## Phase 1 — Restore Wizard

**Priority:** HIGHEST — directly reduces RTO (Recovery Time Objective)
**Launch Freeze compatible:** Yes — this is disaster recovery, permitted under freeze policy
**Builds on:** ArvanBackupService ✅, SlackAlertService ✅, DisasterRecoveryPlaybook ✅
**Architecture:** docs/RESTORE_WIZARD_ARCHITECTURE.md

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `RestoreService` | Service class | 6h |
| `RestoreTenantDatabaseJob` | Job | 3h |
| `RestoreStorageJob` | Job | 2h |
| `DisasterRecoveryController` | Controller (6 routes) | 4h |
| `admin/disaster-recovery/index.blade.php` | View (Arvan file browser) | 4h |
| `admin/disaster-recovery/restore-tenant.blade.php` | View (SSE progress) | 3h |
| `admin/disaster-recovery/restore-platform.blade.php` | View (confirmation + SSE) | 3h |
| `admin/disaster-recovery/restore-storage.blade.php` | View | 2h |
| SSE streaming infrastructure | `RestoreProgressService` | 3h |
| AdminMenu: disaster_recovery key + routes | Config | 1h |
| Tests: RestoreService unit tests (mocked Arvan) | Tests | 4h |

**Total: ~35 hours (~4–5 working days)**

### Dependencies

- BLOCKER-1 resolved (PlatformSeeder — needed for post-restore `db:seed`)
- BLOCKER-2 resolved (Arvan release storage — needed to verify release exists before restore)
- `restore` queue must be configured in Supervisor (separate from default worker)
- Redis must be operational (progress tracking)

### Risk Level: MEDIUM

- Risk: `RestoreTenantDatabaseJob` deletes and re-inserts site_id-scoped rows.
  Mitigation: pre-restore snapshot always created; job `tries = 1`
- Risk: Platform DB restore puts platform in maintenance mode.
  Mitigation: SSE keeps admin informed; hard timeout 10min; health check before `up`
- Risk: SSE connection drops during long restore.
  Mitigation: Redis state persists; client EventSource auto-reconnects

### Expected ROI

- Reduces Scenario A (tenant deleted) RTO from 45–90 min to ~10 min
- Reduces Scenario B (DB corrupted) RTO from 20–60 min (SSH + manual) to ~15 min (web UI)
- Eliminates SSH requirement for DB restores → non-technical operators can recover
- **ROI: HIGH** — first tool used in any data loss event

---

## Phase 2 — Tenant Provision Wizard

**Priority:** HIGH — operationally significant; each new customer onboarding benefits
**Launch Freeze compatible:** No — new feature (POST_LAUNCH only)
**Builds on:** SiteLifecycleService ✅, SiteResourceGovernorService ✅, SmsService ✅
**Architecture:** docs/TENANT_PROVISION_ARCHITECTURE.md

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `TenantProvisioningService` | Service class | 5h |
| `ProvisionData` value object | Value object | 1h |
| `ProvisionResult` value object | Value object | 1h |
| `TenantProvisionController` | Controller (3 routes) | 3h |
| `ProvisionTenantJob` | Job (for async mode) | 2h |
| `admin/sites/provision.blade.php` | View (6-step wizard) | 8h |
| `php artisan tenant:provision` | Artisan command | 3h |
| Tests: TenantProvisioningServiceTest | Tests | 4h |
| AdminMenu: provision route wiring | Config | 0.5h |

**Total: ~27.5 hours (~3–4 working days)**

### Dependencies

- Phase 1 complete (Restore Wizard) — team should validate restore before adding provision
- `subscription_plans` table must have at least 1 active plan (BLOCKER-1 DefaultPlanSeeder)
- SMS provider configured in production `.env`

### Risk Level: LOW

- All component services are already tested individually
- DB transaction wraps the critical path — no partial state on failure
- Side effects (SMS, settings push) are non-blocking with clear warning messages
- No schema changes required

### Expected ROI

- Reduces new tenant onboarding from 6 manual steps to 1 wizard submission
- Prevents missing steps (no backup init, no resource limits, no SMS)
- Estimated time savings per onboarding: 20–30 minutes → <3 minutes
- **ROI: HIGH** — used for every new customer

---

## Phase 3 — Operations Center

**Priority:** MEDIUM — quality-of-life for platform operators
**Launch Freeze compatible:** No — new feature (POST_LAUNCH only)
**Builds on:** ArvanBackupService ✅, BackupDatabaseCommand ✅, deploy.ps1 release uploads (BLOCKER-2)

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `ReleaseManagerService` | Service class | 4h |
| `OperationsCenterController` | Controller | 4h |
| `admin/operations-center/index.blade.php` | View: release history + rollback | 5h |
| `admin/operations-center/backups.blade.php` | View: Arvan backup browser | 4h |
| `RollbackReleaseJob` | Job (download prev release from Arvan + deploy) | 5h |
| Manual backup trigger button | Reuses BackupDatabaseCommand | 1h |
| Deployment log view (reads activity_logs) | View | 2h |
| AdminMenu: operations_center key | Config | 0.5h |

**Total: ~25.5 hours (~3 working days)**

### Dependencies

- BLOCKER-2 resolved (releases must be stored in Arvan)
- Phase 1 complete (Restore Wizard infrastructure shared)
- Release ZIP naming convention standardized (from deploy.ps1 extension)

### Risk Level: LOW

- Rollback is same mechanism as restore — already battle-tested in Phase 1
- Read-only operations (browse backups, view releases) carry zero risk
- Rollback is human-initiated only — no automatic triggers

### Expected ROI

- Enables rollback without SSH in ~5 minutes
- Backup browser removes need to log into Arvan console for status checks
- Deployment log provides audit trail for compliance
- **ROI: MEDIUM** — valuable but not operationally critical vs Phases 1 & 2

---

## Phase 4 — Platform Installer (saas-platform)

**Priority:** MEDIUM — only needed for fresh server provisioning
**Launch Freeze compatible:** No — new infrastructure (POST_LAUNCH only)
**Architecture:** docs/INSTALLER_ARCHITECTURE_DECISION.md Phase B Layer 1
**Topology:** Standalone PHP at `installer/index.php` — NOT a Laravel controller

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `installer/src/InstallerEngine.php` | PHP class | 4h |
| `installer/src/RequirementsChecker.php` | PHP class | 3h |
| `installer/src/EnvWriter.php` | PHP class | 2h |
| `installer/src/ArvanClient.php` | PHP class (curl-based S3) | 4h |
| `installer/src/ReleaseDeployer.php` | PHP class | 3h |
| `installer/src/ServiceConfigurator.php` | PHP class | 3h |
| `installer/src/StreamOutput.php` | PHP class (SSE) | 2h |
| `installer/src/InstallLock.php` | PHP class | 1h |
| `installer/src/HealthVerifier.php` | PHP class | 2h |
| `installer/index.php` | Entry point | 2h |
| `installer/templates/*.html.php` (7 steps) | Templates (RTL Persian UI) | 7h |
| `installer/state.json` (gitignored) | State persistence | — |
| Testing: manual on fresh VM (1x Ubuntu 22.04) | QA | 6h |

**Total: ~39 hours (~5 working days)**

### Dependencies

- BLOCKER-1 resolved (PlatformSeeder must exist before installer calls `db:seed`)
- BLOCKER-2 resolved (installer downloads release from Arvan — releases must be uploaded)
- Phase 3 complete (Operations Center for post-install release management)
- Nginx vhost config templates finalized and documented
- Supervisor config templates finalized and documented

### Risk Level: HIGH

- Standalone PHP executed on a fresh server — no framework safety net
- ServiceConfigurator writes Nginx configs — incorrect configs could block all traffic
  Mitigation: always run `nginx -t` before `systemctl reload nginx`
- Shell command injection risk in `runShellCommand()`
  Mitigation: all inputs sanitized via `escapeshellarg()`; no user input goes directly to shell
- Installer must be disabled after use
  Mitigation: `InstallLock` + Nginx location block documented in post-install checklist

### Expected ROI

- Reduces new server setup time from 3–6 hours (Scenario E) to ~30–45 minutes
- Enables non-technical operators to provision a new server without SSH expertise
- **ROI: MEDIUM** — used only when adding new servers or after catastrophic VPS failure

---

## Phase 5 — Customer Installer (customer-site)

**Priority:** LOW — customer-site is always co-located with platform in current topology
**Launch Freeze compatible:** No — new infrastructure (POST_LAUNCH only)
**Architecture:** Delta from Phase 4 (same engine, different config flow)

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| Additional installer steps (platform connectivity check) | PHP class extension | 3h |
| Customer-site specific ENV template | EnvWriter extension | 2h |
| Customer-site Nginx vhost template | ServiceConfigurator extension | 2h |
| `installer/restore.php` (standalone Full Disaster Restore) | PHP file | 8h |
| Testing: manual on fresh VM | QA | 4h |

**Total: ~19 hours (~2–3 working days)**

### Dependencies

- Phase 4 complete (reuses all Phase 4 infrastructure)
- customer-site releases stored in Arvan: `customer-sites/releases/` (BLOCKER-2 extension)

### Risk Level: MEDIUM

- Same risks as Phase 4, plus cross-repo dependency (platform must be running first)
- `installer/restore.php` is the Full Disaster Restore path — highest-stakes code in the system
  Mitigation: step-by-step with resumable state; every destructive action preceded by dry-run check

### Expected ROI

- Completes Scenario E (entire VPS destroyed) end-to-end automation
- Reduces Full Disaster Restore from 3–6 hours to ~60–90 minutes
- **ROI: HIGH for disaster scenario; LOW for routine use**

---

## Phase 6 — Migration Tool

**Priority:** LOW — only needed when changing hosting provider
**Launch Freeze compatible:** No — new feature (POST_LAUNCH, low priority)
**Architecture:** docs/INSTALLER_ARCHITECTURE_DECISION.md Phase B Layer 4

### Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `ServerMigrationService` | Service class | 6h |
| `ServerMigrationJob` | Job (long-running) | 4h |
| `ServerMigrationController` | Controller | 3h |
| `admin/server-migration/*.blade.php` (7 steps) | Views | 7h |
| DNS cutover integration (ArvanCloud API) | API client extension | 4h |
| Testing: dry-run mode on staging | QA | 4h |

**Total: ~28 hours (~3–4 working days)**

### Dependencies

- Phase 4 + Phase 5 complete (installer infrastructure reused)
- ArvanCloud DNS API credentials configured
- Second server available for testing

### Risk Level: VERY HIGH

- DNS cutover affects all customer sites simultaneously
- SSH credentials handled transiently — never persisted
- If migration fails mid-way, system must be in a defined recoverable state
  Mitigation: All steps are reversible until DNS cutover; DNS cutover has 5-min countdown + abort
- This phase should only be built when a server migration is actually planned
  Recommendation: **DEFER until actually needed** — not a pre-launch or early post-launch requirement

### Expected ROI

- Eliminates manual server migration risk (currently ~6 hours with SSH expertise required)
- Enables future hosting provider change without downtime beyond DNS propagation
- **ROI: MEDIUM — but very low probability of use in near term**

---

## Summary Table

| Phase | Deliverable | Effort | Launch Freeze | Dependencies | Risk | ROI |
|-------|-------------|--------|---------------|--------------|------|-----|
| Pre-blocker | PlatformSeeder (BLOCKER-1) | 4h | ✅ ALLOWED | — | LOW | CRITICAL |
| Pre-blocker | Arvan release upload (BLOCKER-2) | 2h | ✅ ALLOWED | — | LOW | CRITICAL |
| **Phase 1** | **Restore Wizard** | **35h** | **✅ ALLOWED** | Blockers | MEDIUM | **HIGH** |
| Phase 2 | Tenant Provision Wizard | 27.5h | ❌ POST-LAUNCH | Phase 1 | LOW | HIGH |
| Phase 3 | Operations Center | 25.5h | ❌ POST-LAUNCH | Blocker-2 | LOW | MEDIUM |
| Phase 4 | Platform Installer | 39h | ❌ POST-LAUNCH | All prior | HIGH | MEDIUM |
| Phase 5 | Customer Installer | 19h | ❌ POST-LAUNCH | Phase 4 | MEDIUM | HIGH* |
| Phase 6 | Migration Tool | 28h | ❌ POST-LAUNCH | Phase 4+5 | VERY HIGH | MEDIUM |
| **TOTAL** | | **~180h** | | | | |

*High ROI only in disaster scenario (Full VPS loss)

---

## Recommended Execution Sequence

### During Launch Freeze (Immediate)

```
Week 1 (permitted under freeze):
  Day 1–2: Resolve BLOCKER-1 (PlatformSeeder + 3 sub-seeders)
  Day 3:   Resolve BLOCKER-2 (deploy.ps1 Arvan release upload)
  Day 4–5: Begin Phase 1 (Restore Wizard — permitted as disaster recovery)

Week 2 (permitted under freeze):
  Day 6–9:  Complete Phase 1 (Restore Wizard)
  Day 10:   Test Phase 1 against production Arvan backups (dry-run mode)
```

### Post-Launch (in order of ROI)

```
Sprint 1 (post-launch): Phase 2 — Tenant Provision Wizard (3–4 days)
Sprint 2 (post-launch): Phase 3 — Operations Center (3 days)
Sprint 3 (post-launch): Phase 4 — Platform Installer (5 days)
Sprint 4 (post-launch): Phase 5 — Customer Installer (2–3 days)
Sprint 5 (when needed): Phase 6 — Migration Tool (4 days — DEFER until server migration planned)
```

---

## Architecture Compatibility Statement

All 6 phases are designed to be fully compatible with:

| Constraint | How Each Phase Respects It |
|------------|---------------------------|
| `deploy.ps1` SCP workflow | Phases add Arvan upload as extension; SCP deploy path unchanged |
| Arvan Offsite Backup | All phases read from existing Arvan bucket structure; no restructuring |
| Current production topology (single VPS) | No multi-server assumptions; all code runs on existing server |
| LAUNCH FREEZE | Only Blockers + Phase 1 are freeze-eligible; all others deferred |
| Multi-tenant `site_id` isolation | Tenant restore operates on site_id-scoped rows only |
| No `App\` imports in packages | Installer Layer is standalone PHP — zero framework coupling |

No greenfield replacement of existing infrastructure is proposed.
Every phase builds incrementally on the operational platform.

---

*INSTALLER_IMPLEMENTATION_ROADMAP — generated 2026-06-26 — FINAL*
