# INSTALLER IMPLEMENTATION ROADMAP V2
> Date: 2026-06-26
> Status: FINAL — SUPERSEDES V1 ROADMAP
> Supersedes: docs/INSTALLER_IMPLEMENTATION_ROADMAP.md (V1)
> Constraint: LAUNCH FREEZE ACTIVE — no code implementation until explicitly authorized

---

## Business Context Correction (V1 → V2)

V1 was written with an incorrect assumption about the production environment.

### Corrected Understanding

| Assumption | V1 (Wrong) | V2 (Correct) |
|------------|------------|--------------|
| customer-site nature | Real customer installation | Master showcase/demo site — no paying customers yet |
| Project stage | Near-launch, feature-complete | Structural development — foundational systems still being built |
| Primary risk | New tenant onboarding | **Disaster recovery — the platform itself going down** |
| Tenant provisioning urgency | Immediate | Future — when real customers are ready to onboard |
| Customer Installer urgency | Medium (near-term) | Low (remote, deferred) |

### Impact on Roadmap

V1 elevated `PlatformSeeder` and `Customer Installer` because it assumed real tenants were
being managed. Under correct context:

- **PlatformSeeder** is NOT a blocker for any DR phase. It is a deliverable inside Phase 4.
- **Customer Installer** is NOT an immediate priority. It moves to Priority 6 as "Remote Customer Installer".
- **Disaster Recovery Center** emerges as Priority 2 — a separate phase from the Restore Wizard,
  focused on DR health monitoring rather than reactive recovery execution.
- **Release Recovery** (Arvan release packages) is Priority 3 — it enables fast code rollback,
  which is the second most critical DR capability after data restore.

The risk model shifts from "onboarding efficiency" to "recovery assurance".

---

## Dependency Graph (V2)

```
Phase 1 (Restore Wizard)          ← no dependencies; uses existing ArvanBackupService
     ↓
Phase 2 (DR Center)               ← extends Phase 1 infrastructure (RestoreService, controllers)
     
Phase 3 (Release Recovery)        ← independent; extends deploy.ps1 + builds release browser
     ↓
Phase 4 (Platform Installer)      ← depends on Phase 3 (releases in Arvan); PlatformSeeder is here
     ↓
Phase 5 (Tenant Provision Wizard) ← depends on Phase 1 (restore should exist before provisioning real tenants)
     ↓
Phase 6 (Remote Customer Installer) ← depends on Phase 3 + Phase 4
```

Phases 1/2 and Phase 3 are independent — can be built in parallel if resources allow.

---

## Priority 1 — Restore Wizard

**Goal:** Enable platform admin to restore any backup from Arvan without SSH.
**Type:** Reactive recovery — used when something breaks.
**Launch Freeze:** ✅ PERMITTED — disaster recovery is exempt from feature freeze.
**Architecture:** `docs/RESTORE_WIZARD_ARCHITECTURE.md` (already defined — no changes needed).

### Why This Is Priority 1

The current state: all 5 backup types are operational and restorable (RESTORE_AUDIT_COMPLETE).
The gap: every restore requires SSH + manual bash commands. If the person with SSH access
is unavailable, recovery is blocked. The Restore Wizard eliminates this single point of failure.

### Scope (unchanged from V1)

4 restore flows:
- **Flow A:** Tenant database restore (site_id-scoped row restore from customer_site DB)
- **Flow B:** Platform database restore (full saas_platform DB restore, maintenance mode)
- **Flow C:** Storage files restore (tar.gz extract, overwrite or merge)
- **Flow D:** Full disaster restore (new server, standalone restore.php — no Laravel)

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `RestoreService` | Service class | 6h |
| `RestoreProgressService` (SSE) | Service class | 3h |
| `RestoreTenantDatabaseJob` | Job (queue: restore, tries: 1) | 3h |
| `RestoreStorageJob` | Job (queue: restore) | 2h |
| `DisasterRecoveryController` | Controller (6 routes) | 4h |
| `admin/disaster-recovery/index.blade.php` | View: Arvan file browser | 4h |
| `admin/disaster-recovery/restore-tenant.blade.php` | View: tenant restore + SSE | 3h |
| `admin/disaster-recovery/restore-platform.blade.php` | View: platform restore + confirmation | 3h |
| `admin/disaster-recovery/restore-storage.blade.php` | View: storage restore | 2h |
| `installer/restore.php` | Standalone PHP for Scenario E | 6h |
| AdminMenu: disaster_recovery routes | Config | 1h |
| Tests: RestoreService (mocked ArvanBackupService) | Feature tests | 4h |

**Estimated effort: 41 hours (~5–6 working days)**

### Dependencies

None. Phase 1 depends only on:
- `ArvanBackupService` ✅ exists and operational
- `SlackAlertService` ✅ exists and operational
- Redis ✅ operational (progress tracking)
- Existing backup files in Arvan ✅ verified by DISASTER_RECOVERY_AUDIT.md

### Risk Level: MEDIUM

- Pre-restore snapshots always created before destructive operations
- Job `tries = 1` on restore jobs (no auto-retry)
- Platform DB restore runs in maintenance mode — SSE keeps admin informed
- `installer/restore.php` is highest-stakes code; requires manual testing on fresh VM

### ROI Impact

| Metric | Before | After |
|--------|--------|-------|
| Scenario A RTO | 45–90 min (SSH required) | ~10 min (browser wizard) |
| Scenario B RTO | 20–60 min (SSH required) | ~15 min (browser wizard) |
| SSH dependency | Required | Eliminated for data restores |
| Operator skill required | Linux SysAdmin | Platform Admin (any) |

---

## Priority 2 — Disaster Recovery Center

**Goal:** Proactive DR monitoring — always know the health of recovery capability before a crisis.
**Type:** Monitoring and health dashboard — used daily, not just in emergencies.
**Launch Freeze:** ✅ PERMITTED — extends existing monitoring infrastructure (MHC).
**Note:** This is distinct from the Restore Wizard. Restore Wizard = execute recovery. DR Center = monitor readiness.

### Why This Is Priority 2

The Restore Wizard tells you HOW to recover. The DR Center tells you WHETHER recovery is safe.
After Phase 1 is deployed, the next risk is: "Is our backup actually fresh and restorable right now?"
Without DR Center, an admin must manually check Arvan, check backup run logs, and verify integrity
every time before a restore. DR Center automates this into a single readiness score.

### Scope

The DR Center is a dedicated admin panel section at `/admin/disaster-recovery` that shows:

#### A — Backup Freshness Dashboard

```
Backup Type       | Last Run        | Age    | Status    | Arvan Path
------------------+-----------------+--------+-----------+----------------------------------
Platform DB       | 2026-06-26 02:00 | 14h    | ✅ FRESH  | saas-platform/database/...
Customer-Site DB  | 2026-06-26 12:13 | 3h     | ✅ FRESH  | customer-sites/tenant-1/database/...
Platform Storage  | 2026-06-22 03:30 | 4 days | ⚠ AGING   | saas-platform/storage/...
Site Archives     | On-event        | N/A    | ✅ OK     | saas-platform/archives/
```

Thresholds:
- DB backup: FRESH < 26h, AGING 26–48h, OVERDUE > 48h
- Storage backup: FRESH < 8 days, AGING 8–14 days, OVERDUE > 14 days

Data source: `infra_backup_runs` table (already populated by existing `BackupDatabaseCommand`).

#### B — Restore Readiness Score

Single 0–100 score computed from:

| Factor | Weight | Score When |
|--------|--------|------------|
| Platform DB backup fresh | 30 pts | Age < 26h |
| Customer-Site DB backup fresh | 25 pts | Age < 26h |
| Storage backup not overdue | 15 pts | Age < 14 days |
| Arvan bucket accessible | 15 pts | connectivity test passes |
| Platform DB backup passes gzip -t | 10 pts | latest backup file is valid gzip |
| Restore procedure documented | 5 pts | DISASTER_RECOVERY_PLAYBOOK.md exists |

Score interpretation:
- 90–100: ✅ READY (green)
- 70–89: ⚠ DEGRADED (amber)
- 0–69: ❌ AT RISK (red)

Score is computed on demand (AJAX) and cached 15 minutes. Full recompute includes live Arvan
connectivity test + gzip integrity check on latest platform DB backup.

#### C — Restore History

Table showing all restore operations executed via the Restore Wizard:
- Who initiated it, when, which backup, which restore type
- Result (success / failed / aborted)
- Duration + rows affected before/after
- Source: `activity_logs` filtered by `action = 'disaster_recovery_restore'`

#### D — Manual Backup Trigger

Buttons to run any backup immediately (outside scheduled window):
- "بکاپ فوری دیتابیس پلتفرم" → dispatches `BackupDatabaseCommand` as a queued job
- "بکاپ فوری دیتابیس مشتری" → same for customer-site
- Progress shown inline; result logged to `infra_backup_runs`

#### E — Alert Configuration

Simple form:
- Slack channel for DR alerts (reads from `SLACK_ALERT_WEBHOOK` env — informational display only)
- Show when last Slack alert was sent (from `activity_logs`)
- Test button: sends a test alert to confirm webhook is alive

#### F — Arvan Bucket Browser (integrated from Restore Wizard Phase 1)

Browse all backup paths in the Arvan bucket. Reuses the AJAX browse endpoint from Phase 1.
Displays: file counts per path, total size, oldest file, newest file.

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `DrReadinessService` | Service class (readiness score computation) | 5h |
| `DrBackupFreshnessService` | Service class (freshness thresholds + status) | 3h |
| `DisasterRecoveryCenterController` | Controller (extends Phase 1 controller) | 3h |
| `admin/disaster-recovery/center.blade.php` | View: master dashboard | 6h |
| `admin/disaster-recovery/readiness.blade.php` | View: readiness score breakdown | 3h |
| `admin/disaster-recovery/history.blade.php` | View: restore history log | 2h |
| AJAX: `GET /admin/disaster-recovery/readiness-score` | Route (cached 15min) | 2h |
| AJAX: `POST /admin/disaster-recovery/trigger-backup` | Route (dispatches job) | 2h |
| Tests: DrReadinessService | Unit tests | 3h |

**Estimated effort: 29 hours (~3–4 working days)**

### Dependencies

- Phase 1 complete (Restore Wizard + browser endpoint + activity log writes)
- `infra_backup_runs` table ✅ exists (populated by BackupDatabaseCommand)
- `SlackAlertService` ✅ exists
- `ArvanBackupService` ✅ exists

### Risk Level: LOW

- All data is read-only (no destructive operations in DR Center itself)
- Manual backup trigger dispatches existing commands — no new logic
- Readiness score is advisory — does not block any operations

### ROI Impact

- Admin always knows recovery is possible BEFORE a crisis
- Degraded backup state caught 26–48h before it becomes critical
- Reduces "discover backup is stale DURING a disaster" risk to near zero
- Bridges gap between having backup infrastructure and trusting it

---

## Priority 3 — Release Recovery (Arvan Release Packages)

**Goal:** Every deploy creates an immutable release artifact in Arvan.
Any previous release can be deployed in ~5 minutes from the admin panel — no SSH required.
**Type:** Operational infrastructure — extends deploy.ps1.
**Launch Freeze:** ⚠ BORDERLINE — extending deploy.ps1 is operational; the admin UI is a new feature.
Recommend: deploy.ps1 extension (release upload) is freeze-permitted; admin UI deferred to post-launch.

### Why This Is Priority 3

Currently, if a bad deploy goes out:
- Rollback requires SSH + git + composer + npm (30+ minutes)
- Or: restore from DB backup (loses all changes since last DB backup — up to 24h)

With release packages in Arvan:
- Rollback = download prev release ZIP + extract (5 minutes, browser-triggered from admin panel)
- This is fundamentally different from DB restore — it rolls back CODE, not DATA

The release package infrastructure is also a prerequisite for Phase 4 (Platform Installer).

### Scope

#### A — deploy.ps1 Extension (freeze-permitted)

Extend the existing `deploy.ps1` to also create a release ZIP and upload it to Arvan
after each SCP deploy. The SCP path is unchanged — this is purely additive.

```
Current deploy.ps1 flow:
  Build → SCP to server → Restart

Extended deploy.ps1 flow:
  Build → Create release-{date}.zip → SCP to server → Upload ZIP to Arvan → Restart
```

Release ZIP contents:
```
release-YYYY-MM-DD-HHmm.zip
  vendor/               ← composer packages (pre-built)
  public/build/         ← npm compiled assets
  app/                  ← application PHP files
  database/             ← migrations + seeders
  config/               ← config files
  resources/            ← Blade views + CSS + JS source
  routes/               ← route files
  packages/             ← local packages
  artisan               ← artisan CLI
  composer.json/lock    ← for reference
  release-notes.json    ← metadata: commit hash, timestamp, migration list
```

Not included in ZIP:
```
.env                    ← never in release package (credentials)
storage/                ← persistent data, not code
node_modules/           ← not needed (assets pre-built)
.git/                   ← not needed on server
```

Arvan storage paths:
```
safhenokhost-backups/
  saas-platform/
    releases/
      release-2026-06-26-1400.zip
      release-notes-2026-06-26-1400.json
  customer-sites/
    releases/
      release-2026-06-26-1400.zip
      release-notes-2026-06-26-1400.json
```

`release-notes.json` structure:
```json
{
  "release": "2026-06-26-1400",
  "timestamp": "2026-06-26T14:00:00+03:30",
  "git_commit": "8cc9919",
  "git_branch": "main",
  "migrations_added": ["2026_06_25_..."],
  "deployed_by": "deploy.ps1",
  "app": "saas-platform"
}
```

#### B — Release Browser Admin UI (post-launch)

Admin panel page at `/admin/operations-center/releases`:

```
Release             | Date              | Commit  | Migrations | Status     | Actions
--------------------+-------------------+---------+------------+------------+-------------
release-2026-06-26  | 2026-06-26 14:00  | 8cc9919 | 0          | CURRENT    | —
release-2026-06-25  | 2026-06-25 15:50  | b4f90c0 | 1          | previous   | [Rollback]
release-2026-06-25b | 2026-06-25 15:25  | 91fa3d2 | 0          | previous   | [Rollback]
...
```

Rollback action:
1. Download target release ZIP from Arvan → `/tmp/rollback-{date}.zip`
2. Verify ZIP integrity
3. Extract to `/var/www/saas-platform/` (atomic: extract to `saas-platform-new/`, then swap)
4. `php artisan cache:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. `supervisorctl restart all`
6. Health check: `GET /health/ready`
7. Log rollback in `activity_logs`

Note on migrations: rollback does NOT run `migrate:rollback` — only code is rolled back.
If the previous release required a migration that has already run, the migration stays.
This is intentional — data rollbacks go through the Restore Wizard (Phase 1).

#### C — Retention Policy

Arvan has no automatic TTL on objects. Retention is managed by the release browser:
- Keep last 5 releases: permanent
- Releases 6–30: kept 90 days
- Releases older than 90 days: auto-pruned during next deploy (before new upload)

A `PruneOldReleasesCommand` (scheduled monthly) handles cleanup.

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| deploy.ps1 extension (create ZIP + upload to Arvan) | PowerShell | 3h |
| `release-notes.json` generator (in deploy.ps1) | PowerShell | 1h |
| `ReleaseManagerService` | Service class | 4h |
| `RollbackReleaseJob` | Job (download + extract + verify) | 5h |
| `OperationsCenterController` (releases section) | Controller | 3h |
| `admin/operations-center/releases.blade.php` | View | 5h |
| `PruneOldReleasesCommand` | Artisan command | 2h |
| Tests: ReleaseManagerService (mocked Arvan) | Tests | 3h |

**Estimated effort: 26 hours (~3 working days)**
*(deploy.ps1 extension: 4h — freeze-permitted; admin UI: 22h — post-launch)*

### Dependencies

- `ArvanBackupService` ✅ exists (reused for upload)
- deploy.ps1 ✅ exists (extended, not replaced)
- Phase 1 complete (for rollback job infrastructure, SSE streaming)

### Risk Level: LOW (deploy.ps1 extension) / MEDIUM (rollback execution)

- deploy.ps1 extension is additive — failure to upload release to Arvan does NOT block deploy
  (Arvan upload failure = warning logged + Slack alert; SCP deploy continues)
- Rollback is the same atomic extract pattern — if extract fails, server still runs previous code

---

## Priority 4 — Platform Installer

**Goal:** Install saas-platform on a fresh server via a browser-based wizard.
No SSH required from the admin. No git/composer/npm on the target server.
**Type:** Standalone PHP wizard — independent of Laravel.
**Launch Freeze:** ❌ POST-LAUNCH ONLY — new infrastructure.
**Architecture:** `docs/INSTALLER_ARCHITECTURE_DECISION.md` Layer 1.

### Why This Is Priority 4

Before Phase 4, the disaster recovery path for "entire VPS destroyed" (Scenario E) requires:
- Manual server provisioning (apt install, MySQL, Nginx, PHP, etc.)
- Manual git clone + composer install
- Manual .env reconstruction
- Manual restore from Arvan

Phase 4 automates the code deployment part of Scenario E from ~3 hours (manual) to ~30 minutes (wizard).
`installer/restore.php` from Phase 1 handles the data portion; Phase 4 handles the code portion.

### PlatformSeeder is a Phase 4 Deliverable (Not a Pre-Blocker)

PlatformSeeder is needed by the installer (`php artisan db:seed --class=PlatformSeeder` in Step 5
of the installer wizard) to create the initial admin user and default plan.

It is NOT needed by Phase 1, 2, or 3. Moving it here avoids blocking DR work on a seeder.

**PlatformSeeder deliverables (included in Phase 4 estimate):**

| Seeder | Purpose |
|--------|---------|
| `AdminUserSeeder` | Platform admin user from ENV vars (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) |
| `DefaultPlanSeeder` | 2 subscription plans: Starter (free/trial) + Professional (placeholder price) |
| `PlatformSettingsSeeder` | 6 platform-level settings (name, timezone, locale, currency, SMS provider, version) |
| `PlatformSeeder` | Master seeder calling the 3 above |

**Constraint:** All seeders must use `first_name` + `last_name` (not `users.name` column — USERS-NAME-001).

### Scope

```
installer/
  index.php                     ← entry point (standalone PHP, no framework)
  restore.php                   ← full disaster restore (Phase 1 deliverable — already included)
  src/
    InstallerEngine.php         ← orchestrator
    RequirementsChecker.php     ← PHP version, extensions, disk, ports
    EnvWriter.php               ← .env generation (UTF-8, no BOM)
    ArvanClient.php             ← curl-only S3 client (no SDK)
    ReleaseDeployer.php         ← download ZIP from Arvan + extract + permissions
    ServiceConfigurator.php     ← nginx/supervisor/cron templates
    StreamOutput.php            ← SSE streaming
    InstallLock.php             ← prevent re-run after completion
    HealthVerifier.php          ← GET /health/ready + queue status
  templates/
    layout.html.php             ← RTL Vazirmatn Persian UI
    step1_requirements.html.php
    step2_database.html.php
    step3_config.html.php
    step4_arvan.html.php        ← browse releases, pick version to install
    step5_install.html.php      ← streamed execution log
    step6_services.html.php     ← nginx/supervisor/cron setup
    step7_health.html.php       ← final validation + disable installer
  install_lock                  ← created on success; blocks all re-runs
  state.json                    ← step persistence (gitignored)
```

Wizard flow (7 steps):
```
Step 1: Requirements check (PHP/extensions/disk/ports)
Step 2: Database config (host/port/user/pass/name + test connection + CREATE DATABASE)
Step 3: Application config (APP_URL, APP_KEY auto-gen, admin credentials)
Step 4: Arvan config (credentials + browse releases + select version)
Step 5: Code installation (download release ZIP → extract → migrate → seed → cache)
Step 6: Service config (Nginx vhost + Supervisor config + Cron entry — guided templates)
Step 7: Health check (GET /health/ready + queue status + disable installer)
```

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| All `installer/src/*.php` classes (8 files) | Standalone PHP | 24h |
| All `installer/templates/*.html.php` (7 files) | PHP templates (RTL) | 7h |
| `installer/index.php` entry point | PHP | 2h |
| `PlatformSeeder` + 3 sub-seeders (in main app) | Laravel seeders | 4h |
| Manual testing on fresh Ubuntu 22.04 VM | QA | 6h |

**Estimated effort: 43 hours (~5–6 working days)**

### Dependencies

- Phase 3 complete (releases must exist in Arvan for installer to download)
- Phase 1 complete (`installer/restore.php` is reused — already built)
- Nginx vhost config templates finalized (from existing production config)
- Supervisor config templates finalized (from `InfrastructureProductionSeeder` data)

### Risk Level: HIGH

- Standalone PHP executed on a fresh server — no framework safety net
- `ServiceConfigurator` writes Nginx configs — always run `nginx -t` before reload
- Shell command execution — all inputs through `escapeshellarg()`, no raw interpolation
- `InstallLock` must be enforced — if bypassed, installer can overwrite a running platform

---

## Priority 5 — Tenant Provision Wizard

**Goal:** Create a new customer site with full initialization in a single admin form.
**Type:** Laravel admin panel feature — requires platform to be running.
**Launch Freeze:** ❌ POST-LAUNCH ONLY — new feature; no real customers yet.
**Architecture:** `docs/TENANT_PROVISION_ARCHITECTURE.md` (already defined — no changes needed).

### Why This Is Priority 5

The business context confirms: current customer-site is a demo/showcase, not a real customer.
Real customer provisioning is a future business milestone. This wizard should be ready
BEFORE the first real customer is onboarded — not before DR infrastructure is solid.

Priority 5 means: build it as the business approaches first customer onboarding, not now.

### Scope (unchanged from TENANT_PROVISION_ARCHITECTURE.md)

6-step wizard:
1. Customer information (name, contact, mobile, email)
2. Domain assignment (subdomain or custom)
3. Plan & billing (plan selection, trial days, billing start)
4. Theme selection (from marketplace)
5. Hosting & resource allocation (limits, expiry date)
6. Welcome & finalization (summary + execution + SMS)

Core service: `TenantProvisioningService` orchestrating existing:
- `SiteLifecycleService::activate()` (cross-DB push)
- `SiteResourceGovernorService::setLimits()`
- `ExpiryReminderService::syncForSite()`
- `SmsService::send()` (welcome SMS)

CLI alternative: `php artisan tenant:provision --domain= --name= --plan= --mobile=`

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `TenantProvisioningService` | Service class | 5h |
| `ProvisionData` + `ProvisionResult` value objects | Value objects | 2h |
| `TenantProvisionController` (3 routes) | Controller | 3h |
| `ProvisionTenantJob` | Job | 2h |
| `admin/sites/provision.blade.php` (6-step wizard) | View | 8h |
| `php artisan tenant:provision` | Artisan command | 3h |
| Tests: TenantProvisioningServiceTest | Feature tests | 4h |

**Estimated effort: 27 hours (~3–4 working days)**

### Dependencies

- Phase 1 complete (restore should be available before provisioning real customer data)
- `subscription_plans` table must have at least 1 active plan (Phase 4 DefaultPlanSeeder)
- SMS provider configured in production `.env`
- Phase 4 PlatformSeeder run (provides DefaultPlanSeeder data)

### Risk Level: LOW

- All underlying services already tested individually
- DB transaction wraps critical path — no partial state on failure
- Side effects (SMS, settings push) are non-blocking with clear admin warnings

---

## Priority 6 — Remote Customer Installer

**Goal:** Install customer-site on a remote server (different from platform VPS) from a browser.
**Type:** Standalone PHP installer — extends Phase 4 installer for customer-site target.
**Launch Freeze:** ❌ POST-LAUNCH ONLY — required only when deploying to separate customer servers.

### Why This Is Priority 6

The current customer-site lives on the same VPS as saas-platform. Future real customers
may require customer-site on dedicated or separate infrastructure. This phase supports that.

"Remote" means the target server is NOT the platform server. The installer must:
1. Run on the remote server (admin uploads installer package to target)
2. Connect back to saas-platform API to validate platform connectivity
3. Self-configure with correct `PLATFORM_URL`, `PLATFORM_API_KEY`, `PLATFORM_SITE_ID`

### Delivery Mechanism: Pre-configured Install Package

The platform admin generates a customer install package from `/admin/sites/{site}/installer`:

```
customer-install-{site_id}-{token}.zip
  installer/index.php          ← entry point
  installer/src/               ← all Phase 4 installer classes
  installer/config.json        ← pre-configured values (PLATFORM_URL, PLATFORM_API_KEY, PLATFORM_SITE_ID)
  installer/install_token      ← one-time token; validated against platform API on first run
  customer-site-release.zip    ← latest customer-site release from Arvan (bundled)
  README.txt                   ← 3-step instructions in Persian
```

Admin workflow:
1. Platform admin goes to `/admin/sites/{site}/installer`
2. Platform generates the package (downloads customer-site release from Arvan, bundles config)
3. Admin downloads the package
4. Admin uploads package to target server (FTP/SCP)
5. Admin visits `http://target-server/installer/` in browser
6. Installer runs (same 7-step flow from Phase 4)
7. On completion, installer pings `PLATFORM_URL/api/v1/health` to confirm cross-connectivity
8. Platform admin dashboard shows site as "connected"

No SSH to target server required from admin's perspective after upload.

### Installer Token Security

`install_token` in the package is:
- Generated by platform: UUID v4, stored in `sites` table as `installer_token`
- Single-use: invalidated after first successful run
- TTL: 72 hours from generation
- Used to verify the installer is legitimate (not a forged package)

Platform API endpoint used by installer:
```
POST /api/installer/validate-token
Body: { site_id, token }
Response: { valid: true, platform_api_key, site_id }
```

This prevents the install package from being used on an unauthorized server.

### Key Deliverables

| Deliverable | Type | Hours |
|-------------|------|-------|
| `installer/src/CustomerSiteConfigurator.php` | PHP class (customer-site delta from Phase 4) | 4h |
| `installer/templates/` — customer-site variants (steps 1, 3, 6, 7) | PHP templates | 4h |
| `InstallPackageGeneratorService` | Service class (generates the bundled ZIP) | 5h |
| `InstallPackageController` | Controller (download + validate endpoint) | 3h |
| `admin/sites/installer.blade.php` | View: generate + download install package | 3h |
| Platform API: `POST /api/installer/validate-token` | API route | 2h |
| `installer_token` column on `sites` table | Migration | 1h |
| Tests: package generation + token validation | Tests | 3h |

**Estimated effort: 25 hours (~3 working days)**

### Dependencies

- Phase 3 complete (customer-site releases in Arvan: `customer-sites/releases/`)
- Phase 4 complete (installer core classes reused)
- Phase 5 complete (site must be provisioned in platform before installer package is generated)

### Risk Level: MEDIUM

- Installer token prevents unauthorized use of generated packages
- Bundled release ZIP is large (~200–300MB) — download may be slow on target server
  Mitigation: alternative mode where installer downloads release directly from Arvan
  (if admin provides Arvan credentials to installer wizard)

---

## Summary Table (V2)

| Priority | Phase | Effort | Freeze | Dependencies | Risk | ROI |
|----------|-------|--------|--------|--------------|------|-----|
| P1 | Restore Wizard | 41h | ✅ PERMITTED | None | MEDIUM | CRITICAL |
| P2 | Disaster Recovery Center | 29h | ✅ PERMITTED | Phase 1 | LOW | HIGH |
| P3a | deploy.ps1 Release Upload | 4h | ✅ PERMITTED | None | LOW | HIGH |
| P3b | Release Browser Admin UI | 22h | ❌ POST-LAUNCH | Phase 1 | MEDIUM | HIGH |
| P4 | Platform Installer | 43h | ❌ POST-LAUNCH | Phase 3 | HIGH | MEDIUM |
| P5 | Tenant Provision Wizard | 27h | ❌ POST-LAUNCH | Phase 4 | LOW | HIGH* |
| P6 | Remote Customer Installer | 25h | ❌ POST-LAUNCH | Phase 4+5 | MEDIUM | MEDIUM |
| **TOTAL** | | **~191h** | | | | |

*High ROI when first real customer is being onboarded

---

## Execution Sequence

### During Launch Freeze (Immediate)

```
Week 1–2 (all freeze-permitted):
  Day 1–5:   Phase 1 — Restore Wizard (41h)
  Day 6–9:   Phase 2 — DR Center (29h)
  Day 9:     Phase 3a — deploy.ps1 release upload (4h — can be done in parallel with any day)
```

### Post-Launch

```
Sprint 1 (post-launch): Phase 3b — Release Browser Admin UI (22h, ~3 days)
Sprint 2 (post-launch): Phase 4 — Platform Installer (43h, ~5–6 days)
Sprint 3 (when approaching first real customer): Phase 5 — Tenant Provision Wizard (27h, ~3–4 days)
Sprint 4 (when remote customer-site deployment needed): Phase 6 — Remote Customer Installer (25h, ~3 days)
```

---

## Changes from V1 → V2

| Item | V1 | V2 | Reason |
|------|----|----|--------|
| BLOCKER-1 (PlatformSeeder) | Pre-blocker | Phase 4 deliverable | Not needed for DR phases 1–3 |
| BLOCKER-2 (deploy.ps1 upload) | Pre-blocker | Phase 3a deliverable | Not blocking DR phases 1–2 |
| Customer Installer priority | Phase 5 (medium) | Phase 6 (remote, low) | No real customers yet |
| Disaster Recovery Center | Not in V1 | Phase 2 (high) | Proactive DR monitoring needed |
| Release Recovery | BLOCKER-2 only | Full Phase 3 | Deserves dedicated phase scope |
| PlatformSeeder as blocker | Yes | No (Phase 4 internal) | Correctly scoped as installer prereq |
| Phase execution order | Feature-first | DR-first | Aligned with actual risk profile |

---

## Architecture Compatibility Statement

All 6 phases remain compatible with:

| Constraint | How Respected |
|------------|---------------|
| deploy.ps1 SCP workflow | Phase 3a extends it additively; SCP path unchanged |
| Arvan Offsite Backup | All phases read/write to existing bucket structure; no restructuring |
| Current production topology (single VPS) | No multi-server assumptions; all phases work on current server |
| LAUNCH FREEZE | Only P1, P2, P3a are freeze-eligible |
| Multi-tenant `site_id` isolation | Phase 1 tenant restore is site_id-scoped only |
| No `App\` imports in packages | Installer Layer is standalone PHP — zero framework coupling |
| No greenfield replacement | Every phase builds incrementally on the existing operational platform |

---

*INSTALLER_IMPLEMENTATION_ROADMAP_V2 — generated 2026-06-26 — FINAL*
*Supersedes: INSTALLER_IMPLEMENTATION_ROADMAP.md (V1)*
