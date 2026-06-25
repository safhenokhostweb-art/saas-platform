# INSTALLER ARCHITECTURE DECISION
> Date: 2026-06-26
> Status: FINAL — ARCHITECTURE DECIDED
> Authority: INSTALLER_ARCHITECTURE_DECISION_PHASE

---

## Phase A — Deployment Model Decision

### Verdict: OPTION A — Pre-built Release Package (Arvan Object Storage)

#### Why Option A Wins

| Criterion | Option A (Pre-built + Arvan) | Option B (Git clone + Composer + Node) |
|-----------|------------------------------|----------------------------------------|
| Security | ✅ No build tools on prod server; exact tested artifacts deployed | ❌ Composer/npm on prod = dependency confusion risk; git credentials on server |
| Reliability | ✅ Build verified before upload; atomic swap; rollback = download prev package | ❌ Network failures during composer install = outage; version drift risk |
| Recovery speed | ✅ Download from Arvan (regional CDN) + extract = ~5 min | ❌ git clone + composer install + npm build = 15–30 min |
| Production consistency | ✅ Bit-for-bit identical to what was tested locally | ❌ Different build environment produces different output |
| Operational complexity | ✅ Simpler server; no build tools; deploy.ps1 already uses this pattern | ❌ Complex server setup; npm/node version drift between server and local |

#### Option A Design (Extended from deploy.ps1)

**Current workflow (deploy.ps1):**
1. Build locally (vendor + npm run build already compiled)
2. SCP upload directly to server
3. Restart services

**Improved Option A workflow:**
1. Build locally (same as now)
2. Create release ZIP: `release-{YYYY-MM-DD-HHmm}.zip`
3. Upload release ZIP to Arvan: `saas-platform/releases/release-{date}.zip`
4. SCP to server (current path — fast, atomic)
5. Server: extract + chown + php artisan cache:clear + supervisorctl restart
6. Arvan copy serves as immutable rollback artifact

**Benefit over current state:**
- Every release is permanently stored in Arvan
- Rollback = download previous ZIP from Arvan + re-extract (no git required)
- Full disaster recovery (Scenario E) can deploy code in ~5 minutes instead of 30
- No change to current deploy.ps1 logic — Arvan upload is additive

#### Arvan Release Storage Path

```
safhenokhost-backups/
  saas-platform/
    releases/
      release-2026-06-26-1400.zip   ← each deploy creates one
      release-2026-06-25-1550.zip
      release-2026-06-25-1525.zip
    database/          ← daily .sql.gz (existing)
    storage/           ← weekly .tar.gz (existing)
    archives/          ← on-event .zip (existing)
  customer-sites/
    releases/
      release-2026-06-26-1400.zip   ← customer-site builds
    tenant-1/
      database/        ← existing
      archives/        ← existing
```

#### Release Retention Policy

| Type | Retention | Rationale |
|------|-----------|-----------|
| Last 5 releases | Permanent | Instant rollback window |
| Older releases | 90 days | Cost control; beyond 90d use DB backup instead |
| Release notes file | Alongside ZIP | `release-notes-{date}.json`: commit hash, migration list, deployment time |

---

## Phase B — Installer Layer Architecture

### Architecture Decision: Hybrid Web Installer + PHP CLI Engine

This confirms the INSTALLER_RECOMMENDATION.md decision. The 5 layers are defined below.

---

### Layer 1 — Installer Layer

**Purpose:** Install saas-platform or customer-site on a fresh server with no prior state.

**When it runs:** Before Laravel exists on the target server.

**Entry point:** `installer/index.php` — standalone PHP (no framework, no Composer).

#### Responsibilities
- System requirements check (PHP version, extensions, disk, ports)
- Database connectivity test + schema creation
- Application configuration (ENV generation)
- Arvan bucket connectivity test + release download
- Code extraction + permission setup
- Nginx / Supervisor / Cron configuration
- Health verification + installer self-disable

#### Services (standalone PHP classes)

| Class | Responsibility |
|-------|---------------|
| `installer/src/InstallerEngine.php` | Orchestrator — calls all services in sequence |
| `installer/src/RequirementsChecker.php` | PHP version, extensions, disk, mysql/redis ports |
| `installer/src/EnvWriter.php` | Write .env file (UTF-8, no BOM) |
| `installer/src/ArvanClient.php` | curl-based S3-compatible client (no SDK) |
| `installer/src/ReleaseDeployer.php` | Download ZIP from Arvan, extract, set permissions |
| `installer/src/ServiceConfigurator.php` | Nginx vhost, Supervisor config, Cron entry |
| `installer/src/StreamOutput.php` | Server-Sent Events (SSE) helper |
| `installer/src/InstallLock.php` | Create/check install_lock file |
| `installer/src/HealthVerifier.php` | GET /health/ready, queue status, scheduler test |

#### Controllers
None. Installer is standalone PHP — no Laravel controllers.

#### Jobs
None. All execution is synchronous (streamed via SSE to browser).

#### Storage
- `installer/state.json` — step persistence between wizard pages
- `installer/install_lock` — created on success, blocks re-run
- Downloaded release ZIP stored in `/tmp/release-{date}.zip` (deleted after extraction)

#### Security Boundaries
- `install_lock` file: if present, all installer requests return 403
- HTTPS required: HTTP redirected to HTTPS by Nginx before installer loads
- No credentials written to disk in plaintext (state.json stores only step number and non-secret config)
- DB password and API keys are POST-only, never in URL
- Installer directory must be removed or blocked at Nginx level post-install

#### Wizard Steps (Platform Installer)

```
Step 1: System Requirements   → RequirementsChecker (PHP/extensions/disk/ports)
Step 2: Database Config       → EnvWriter + DB connectivity test
Step 3: Application Config    → EnvWriter (APP_URL, APP_KEY auto-gen, admin credentials)
Step 4: Arvan Config          → ArvanClient test + list latest releases
Step 5: Code Installation     → ArvanClient download + ReleaseDeployer + php artisan migrate + seed
Step 6: Service Config        → ServiceConfigurator (Nginx + Supervisor + Cron)
Step 7: Health Check          → HealthVerifier + display credentials + disable installer
```

#### Customer Site Installer (delta from Platform)
- Step 1 extension: Platform connectivity check (`GET {PLATFORM_URL}/api/v1/health`)
- Step 3 extension: PLATFORM_URL, PLATFORM_API_KEY, PLATFORM_SITE_ID, LICENSE_VERIFY_SSL fields
- Step 5 extension: No npm build (pre-built assets in release ZIP)
- Step 6 extension: customer-site Nginx vhost (wildcard domain routing)

---

### Layer 2 — Provision Layer

**Purpose:** Create and fully configure a new tenant/customer site after platform is running.

**When it runs:** Laravel is operational. Triggered by platform admin.

**Entry points:**
- Web: `GET/POST /admin/sites/provision` — 6-step wizard modal
- CLI: `php artisan tenant:provision --domain= --name= --plan= --mobile=`

#### Responsibilities
- Create `sites` record with all required fields
- Generate license key (UUID v4)
- Assign subscription plan + set billing dates
- Push initial settings to customer-site DB (via SiteLifecycleService)
- Allocate resource limits (SiteResourceGovernorService)
- Configure domain (Nginx entry or ArvanCloud DNS API)
- Seed tenant initial data (categories, CRM stages)
- Send welcome SMS to tenant admin mobile
- Initialize backup run record
- Initialize monitoring record (site_health_logs entry)

#### Services

| Service | Existing | Action |
|---------|----------|--------|
| `TenantProvisioningService` | ❌ NEW | Master orchestrator for provision flow |
| `SiteLifecycleService` | ✅ EXISTS | Call `activate()` to push initial state |
| `SiteResourceGovernorService` | ✅ EXISTS | Set default resource limits for plan |
| `ExpiryReminderService` | ✅ EXISTS | Initialize hosting expiry notification |
| `SmsService` | ✅ EXISTS | Send welcome SMS |

#### Controllers

| Controller | Route | Method |
|------------|-------|--------|
| `TenantProvisionController` (NEW) | `GET /admin/sites/provision` | Show wizard form |
| `TenantProvisionController` (NEW) | `POST /admin/sites/provision` | Execute provision |
| `TenantProvisionController` (NEW) | `GET /admin/sites/provision/status` | SSE progress stream |

#### Jobs

| Job | Purpose |
|-----|---------|
| `ProvisionTenantJob` (NEW) | Queued execution of full provision flow; streams progress via SSE |

#### Storage
- Progress state in Redis: `tenant_provision_{adminUserId}` (TTL 1 hour)
- Provision log in `activity_logs` (existing table)

#### Security Boundaries
- Route protected by `auth` + `admin` middleware
- Cannot provision a domain that already exists in `sites` table
- Resource limits capped to plan maximums — cannot exceed plan allocation
- Welcome SMS sent only after DB commit confirms success

---

### Layer 3 — Restore Layer

**Purpose:** Restore data from Arvan backups after failure or data loss.

**When it runs:** Two modes.
- **Mode A (Normal):** Laravel is running → `/admin/disaster-recovery/restore`
- **Mode B (Emergency):** Laravel is down → `installer/restore.php` (standalone PHP)

#### Responsibilities
- Browse available backups in Arvan bucket
- Download backup file with integrity verification
- Execute DB restore (with pre-restore safety snapshot)
- Execute storage restore
- Flush all caches post-restore
- Run pending migrations post-restore
- Restart queue workers
- Report restore result + health status

#### Services

| Service | Existing | Action |
|---------|----------|--------|
| `RestoreService` (NEW) | ❌ NEW | Master orchestrator for all restore flows |
| `ArvanBackupService` | ✅ EXISTS | List/download files from Arvan bucket |
| `SlackAlertService` | ✅ EXISTS | Alert on restore start + completion |

#### Controllers

| Controller | Route |
|------------|-------|
| `DisasterRecoveryController` (NEW) | `GET /admin/disaster-recovery` — restore dashboard |
| `DisasterRecoveryController` (NEW) | `GET /admin/disaster-recovery/browse` — Arvan file browser |
| `DisasterRecoveryController` (NEW) | `POST /admin/disaster-recovery/restore` — initiate restore |
| `DisasterRecoveryController` (NEW) | `GET /admin/disaster-recovery/stream` — SSE progress |

#### Jobs

| Job | Restore Type | Queue |
|-----|-------------|-------|
| `RestoreTenantDatabaseJob` (NEW) | Tenant DB restore | `restore` queue (isolated) |
| `RestorePlatformDatabaseJob` (NEW) | Platform DB restore | Synchronous (maintenance mode) |
| `RestoreStorageJob` (NEW) | Storage restore | `restore` queue |

#### Storage
- Restore progress in Redis: `restore_progress_{token}` (TTL 2 hours)
- Downloaded backup files: `/tmp/restore-{type}-{date}.sql.gz` (deleted after use)
- Pre-restore DB snapshot: `/tmp/pre-restore-snapshot-{timestamp}.sql.gz`

#### Security Boundaries
- All restore routes require `auth` + `admin` middleware
- Platform DB restore requires typed confirmation string `RESTORE-PLATFORM`
- Full disaster restore from installer/restore.php requires Arvan credentials + DB credentials
- Pre-restore snapshots always created before destructive operations
- Restore log written to `activity_logs` with before/after counts

---

### Layer 4 — Migration Layer

**Purpose:** Move the platform from one VPS to another with zero-risk DNS cutover.

**When it runs:** Planned server migration only. Not for routine operations.

**Entry point:** `GET /admin/server-migration` (Laravel admin panel)

#### Responsibilities
- Validate new server SSH connectivity
- Trigger pre-migration backup on old server
- Deploy code to new server (using Arvan release artifact)
- Restore databases to new server from Arvan
- Validate new server health before DNS cutover
- Coordinate 5-minute DNS cutover window with rollback option
- Decommission old server after 24h confirmation

#### Services

| Service | Existing | Action |
|---------|----------|--------|
| `ServerMigrationService` (NEW) | ❌ NEW | Full orchestration |
| `ArvanBackupService` | ✅ EXISTS | Trigger backup + download |
| `HealthVerifier` (installer) | ✅ PLANNED | Health check on remote server |

#### Controllers
- `ServerMigrationController` (NEW): `GET/POST /admin/server-migration`

#### Jobs
- `ServerMigrationJob` (NEW): Long-running; dispatched to `migration` queue; streams via SSE

#### Security Boundaries
- SSH credentials never stored in DB — session-only, cleared after migration
- DNS cutover requires admin confirmation with 5-minute timer
- Rollback reverts DNS within 60 seconds (ArvanCloud API)

---

### Layer 5 — Operations Layer

**Purpose:** Day-2 operations: release management, rollback, backup browser, deployment log.

**When it runs:** Ongoing, as part of platform admin panel.

**Entry points:** Extensions to existing admin panel areas.

#### Responsibilities
- Browse release history from Arvan (list + download)
- Trigger rollback to any previous release
- View deployment log (what was deployed, when, by whom)
- Arvan backup browser (list all backup types + sizes)
- Manual backup trigger (outside scheduled window)
- Retention policy management (set TTL on Arvan bucket objects)

#### Services

| Service | Existing | Action |
|---------|----------|--------|
| `ReleaseManagerService` (NEW) | ❌ NEW | List/rollback releases from Arvan |
| `ArvanBackupService` | ✅ EXISTS | List backups |
| `BackupDatabaseCommand` | ✅ EXISTS | Manual trigger via admin |

#### Controllers
- Extension of `InfrastructureController` (existing) or new `OperationsCenterController`
- Route: `/admin/operations-center`

#### Jobs
- `RollbackReleaseJob` (NEW): Download + deploy previous release ZIP from Arvan

#### Security Boundaries
- Rollback requires admin confirmation
- Rollback log written to `activity_logs`
- No automatic rollback — always human-initiated

---

## Architectural Constraints (Never Violate)

1. **install_lock is sacred** — once created, installer cannot run again without manual deletion
2. **Pre-restore snapshots always** — no destructive DB operation without prior snapshot
3. **Release ZIPs are immutable** — never overwrite an existing release in Arvan
4. **Tenant isolation during restore** — tenant DB restore only touches rows with matching `site_id`
5. **Maintenance mode before platform DB restore** — `php artisan down` mandatory, never skip
6. **Installer Layer has no App\ imports** — standalone PHP only; zero Laravel dependencies
7. **Arvan credentials never in installer state.json** — only validated and discarded; not persisted

---

*INSTALLER_ARCHITECTURE_DECISION — generated 2026-06-26 — FINAL*
