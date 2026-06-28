# HANDOVER DOCUMENT
> Reading this file alone is sufficient to resume development from any future session.
> If context is lost, read this file first, then PROJECT_STATE.md.
> Last Updated: 2026-06-27

---

## CURRENT STATE (2026-06-28 — PHASE-003D5_COMPLETE)

**Last completed phase:** Deployment Target UI Refactor. Full driver-aware admin interface for managing remote deployment environments.

**What was built:**
- `CustomerRemoteServerAdminController` — fully rewritten: accepts all 5 driver types (ssh/cpanel/directadmin/sftp/docker), `driverMetaMap()` static method as single source of truth
- `servers/index.blade.php` — "مقصد استقرار" throughout, 5-driver support banner, color-coded type badges, capability chips (desktop only), disabled test button for stubs
- `servers/create.blade.php` — Alpine.js two-step flow: Step 1 type selector grid (5 cards + disabled K8s) → Step 2 dynamic form (fields change per driver) → Step 3 capability preview
- `servers/edit.blade.php` — driver-aware static form, type locked with badge, optional credential update (empty = keep existing), capability snapshot grid
- Migration: `api_token_encrypted`, `path`, `domain` added to `customer_remote_servers`; `auth_method` enum expanded to include `api_token` and `sftp_password`
- 3 docs: `DEPLOYMENT_TARGET_UI_ARCHITECTURE.md`, `DEPLOYMENT_TARGET_DRIVER_UI.md`, `HOSTING_TARGET_MATRIX.md`

**Architectural invariant:** `driverMetaMap()` is the ONLY place with driver-specific UI knowledge. Zero hardcoded driver logic in views.

**NEXT STEP:** No active tasks. Ready for next development phase.

---

## CURRENT STATE (2026-06-27 — ARCHITECTURE_UPGRADE_001_COMPLETE)

**Last completed phase:** UNIVERSAL_DEPLOYMENT_ENGINE architecture upgrade. Driver-based system for all future hosting types.

**What was built:**
- `DeploymentDriverInterface` — universal driver contract extending `RemoteConnectionInterface`
- `SshDeploymentDriver` — full SSH/VPS implementation using `DeploymentTarget` model
- `CpanelDeploymentDriver`, `DirectAdminDeploymentDriver`, `SftpDeploymentDriver`, `DockerDeploymentDriver` — architecture stubs (interface only, no business logic)
- `DriverType` enum (SSH/CPANEL/DIRECTADMIN/SFTP/DOCKER/FAKE)
- `AuthMethod` enum (SSH_KEY/SSH_PASSWORD/CPANEL_API_TOKEN/DIRECTADMIN_API_TOKEN/SFTP_PASSWORD)
- `DriverCapabilities` DTO — 10 capability flags with named constructors per hosting type
- `ValidationResult` DTO — driver pre-flight validation
- `DeploymentTarget` model + `deployment_targets` migration (11 fields: name, type, connection_driver, auth_method, host, port, username, path, domain, status, capabilities JSON)
- `FakeRemoteDriver` — now implements `DeploymentDriverInterface` for full test coverage
- `RemoteConnectionService` — added `buildDriverForTarget()` + `testAndRecordTarget()`, backward compatible
- `config/customer_installation.php` — added `deployment_drivers` registry with all 6 drivers
- 4 docs: `UNIVERSAL_DEPLOYMENT_ARCHITECTURE.md`, `DEPLOYMENT_DRIVER_INTERFACE.md`, `HOSTING_SUPPORT_MATRIX.md`, `FUTURE_DEPLOYMENT_DRIVERS.md`

**ADR-014 recorded:** Pipeline must never contain if-VPS/if-cPanel. Only `capabilities()` queries.

**Backward compatibility:** PASS — `CustomerRemoteServer`, `SshRemoteDriver`, all 20 pipeline stages unchanged.

**Production deployment required:** `php artisan migrate --force` to create `deployment_targets` table.

**NEXT STEP:** No active tasks. Ready for next development phase.

---

## CURRENT STATE (2026-06-27 — HOTFIX-003_COMPLETE)

**Last completed phase:** All production 500 errors resolved. Platform is STABLE.

**What was fixed (HOTFIX-001 through HOTFIX-003):**
- InstallerService.php ParseError (];→};) — BUG-001
- RestoreWizardController::validate() conflict → renamed checkBackup() — BUG-002
- INSTALLER_MASTER_KEY missing from .env — BUG-003
- RestoreWizardController sites.name SQL column missing — BUG-004
- mode-select.blade.php Alpine x-show orphaned scope → vanilla JS — BUG-005
- source-select.blade.php Alpine x-model never populated → vanilla JS — BUG-005-B
- SlackAlertService 1-arg calls in UpgradeAdapter.php, SiteResourceAlertService.php, CustomerReadyStage.php — BUG-006
- Customer-deployment/servers 500 transient (mid-deploy, key present) — BUG-007

**Production URLs — all verified 200/302:**
- `/platform-installer` — 200
- `/admin/disaster-recovery` — 302 (auth redirect)
- `/admin/disaster-recovery/center` — 302 (auth redirect)
- `/admin/installer` — 302 (auth redirect)
- `/admin/infrastructure/customer-deployment/servers` — 200

**Last deploy:** `50ea792` (2026-06-27 22:19)

**NEXT STEP:** No active bugs. Ready for next development phase.

---

## PREVIOUS STATE (2026-06-27 — PHASE-003C_COMPLETE)

**Last completed phase:** PHASE-003C Customer Installation Execution Engine — real deployment engine complete, certified, ready to deploy.

**What's built (PHASE-003C):**
- `PostInstallValidationService` — 8 health checks: database (artisan migrate:status + tinker SELECT 1), queue (supervisorctl), redis (redis-cli ping), storage (symlink + writable), license (.env grep), scheduler (cron.d), domain (HTTP 200), ssl (HTTPS 200)
- `SshRemoteDriver` — reconnect support: `MAX_RECONNECTS=2`, `isConnectionDropped()`, `doExecute()`, 0.8s pause on reconnect
- `CustomerReadyStage` — uses PostInstallValidationService, persists results in `post_install_validation`, fails only if required checks (database, storage) fail, Slack notification with check summary
- `InitializeBackupStage` — full INITIAL_CUSTOMER_BASELINE flow: remote mysqldump → SFTP download → Arvan upload → SHA-256 verify → installation record update (`initial_baseline_at`, `initial_baseline_arvan_key`, `initial_baseline_sha256`)
- `CustomerInstallAcceptanceCommand` — `php artisan customer-install:acceptance-test {server} [--full]` — measures SSH ping, command exec, PHP version, disk, extensions, MySQL, supervisor, redis, /var/www writable
- Migration `2026_06_27_200000_add_baseline_and_validation_to_customer_installations.php` — 4 new columns

**Pipeline status:** 20 stages, all real SSH execution. No stubs, no simulation.

**NEXT STEP:** Run migration on production: `php artisan migrate --force`. Then run acceptance test: `php artisan customer-install:acceptance-test {server_id} --full`.

---

## CURRENT STATE (2026-06-27 — PHASE-003B_COMPLETE)

**Last completed phase:** PHASE-003B Customer Installation Admin Center — complete, certified, ready to deploy.

**All 8 modules delivered. 4 documentation files generated.**

**What's built (PHASE-003B):**
- `CustomerDeploymentController` — dashboard, wizard, history, show, start, rollback, packages
- `CustomerRemoteServerAdminController` — CRUD + SSH test (CredentialVault + RemoteConnectionService injected)
- `CustomerInstallationProgressController` — SSE stream (Redis + DB fallback, auto-close on terminal status)
- 8 Blade views: dashboard, wizard (8-step Alpine.js), history (filterable), show (timeline + SSE), packages, servers/index, servers/create, servers/edit
- AdminMenu: `customer_deployment` entry in "زیرساخت / DevOps" group
- 15 routes under `/admin/infrastructure/customer-deployment/`
- 4 docs: CUSTOMER_INSTALL_ADMIN_CENTER.md, CUSTOMER_INSTALL_UI.md, CUSTOMER_INSTALL_ACCEPTANCE.md, CUSTOMER_INSTALL_CERTIFICATION.md

**NEXT STEP:** Deploy PHASE-003B to production via `.\deploy.ps1`. Then PHASE-003C (Arvan real package download integration) or SSH live-connection testing.

---

## CURRENT STATE (2026-06-27 — PHASE-003A_FOUNDATION_CERTIFIED)

**Last completed phase:** PHASE-003A Customer Installation Engine — Foundation complete, certified, deployed.

**31/31 tests pass. Deployed to production.**

**What's built:**
- 5 DB tables: `customer_remote_servers`, `customer_installation_packages`, `customer_installations`, `customer_installation_logs` (immutable), `customer_install_rollbacks`
- 5 Models: `CustomerRemoteServer`, `CustomerInstallationPackage`, `CustomerInstallation`, `CustomerInstallationLog` (booted: no update/delete), `CustomerInstallRollback`
- DTOs: `CustomerInstallRequest`, `CommandResult`, `ConnectionTestResult`, `StageResult`, `PackageManifest`, `InstallationContext`
- Contracts: `RemoteConnectionInterface`, `InstallationStageInterface`, `CustomerPackageInterface`, `InstallationStateInterface`
- `CredentialVault` — AES-256-GCM, random 12-byte nonce per encryption, INSTALLER_MASTER_KEY env, rotate() method
- `SshRemoteDriver` — phpseclib3\Net\SSH2+SFTP, RSA key or password auth, host key pinning, chunked SFTP upload with progress
- `FakeRemoteDriver` — full test double: response registry, command/upload log, configurable disk free, willFail()
- 20 stage classes in `app/Services/CustomerInstallation/Stages/`
- `CustomerInstallationOrchestrator` — buildStageList(mode), run(installation, callback), resume support, LIFO rollback dispatch
- `CustomerInstallationRollbackService` — LIFO, idempotent, continues after individual stage rollback failure
- `InstallationStateManager` — implements `InstallationStateInterface`, Redis progress streaming (try-catch, non-fatal), immutable audit log creation
- 3 Jobs: `CustomerInstallationPipelineJob` (3600s), `CustomerInstallationRollbackJob` (1800s), `CustomerPackageUploadJob` (1800s, 3 tries)
- 2 Commands: `installer:generate-master-key`, `customer-install:register-package`
- `CustomerInstallationServiceProvider` — registered in config/app.php, binds all interfaces, registers commands

**Bug fixed (test infrastructure):**
- `SecurityPatchRegistry.boot()` + `MobilePatchRegistryAdapter.seedMobileRegistry()` — wrapped in try-catch, no longer crashes when Redis unavailable during test bootstrap

**NEXT STEP:** PHASE-003B — Admin Center UI (wizard, server list, progress SSE, installation history, logs viewer). Or integrate real Arvan package download. Architecture is frozen.

---

## CURRENT STATE (2026-06-27 — CORE_ENTERPRISE_CERTIFIED)

**Last completed phase:** CORE_FREEZE_ACCEPTANCE_FINAL — 12/12 acceptance scenarios PASS. Full enterprise certification complete.

**Core Health: 95/100 — CORE_ENTERPRISE_CERTIFIED**

**12 Acceptance Scenarios:**
All 12 PASS: Installation, Backup, Restore, DR Center, Finance, License, Hosting, Customer Site, Infrastructure, Security, Performance, Operational Recovery

**4 Certification documents:**
- `docs/CORE_FREEZE_ACCEPTANCE_REPORT.md` — 12 scenarios, full evidence
- `docs/ENTERPRISE_CERTIFICATION.md` — subsystem cert scores, conditions
- `docs/KNOWN_LIMITATIONS.md` — KL-001..KL-006 (accepted, non-blocking)
- `docs/FREEZE_APPROVAL.md` — CORE_ENTERPRISE_CERTIFIED verdict + Phase 3 entry

**Known Limitations (Phase 3 targets, non-blocking):**
- KL-001: Supervisor group name mismatch in deploy.sh (ops)
- KL-002: evalRestoreLogs() false low score on fresh install (cosmetic)
- KL-003: ALERT-007 TTL math in ScanDrHealthCommand (minor false-positive)
- KL-004: Finance createManual() no idempotency (admin-only)
- KL-005: No rate limit on /api/license/validate (hardening)
- KL-006: Tenant storage restore uses platform storage job (placeholder)

**NEXT STEP:** Phase 3 — Restore Engine Enterprise (APPROVED TO START)

---

## CURRENT STATE (2026-06-27 — CORE_BLOCKERS_IMPLEMENTATION_COMPLETE)

**Last completed phase:** CORE_BLOCKERS_IMPLEMENTATION — All P0/P1/P2 blockers from CORE_CERTIFICATION_PHASE_1 resolved.

**Overall Core Health: 95/100 — CORE_READY_FOR_FREEZE**

**Subsystem results (post-implementation):**
| Subsystem | Score | Freeze |
|-----------|-------|--------|
| Installer Foundation | 96/100 | FROZEN |
| Backup Engine / DR Center | 90/100 | FROZEN |
| Restore Foundation | 92/100 | FROZEN |
| Arvan Offsite Backup | 96/100 | FROZEN |
| Queue / Job Reliability | 94/100 | FROZEN |
| Finance Ledger | 78/100 | FROZEN* |
| License Sync | 82/100 | FROZEN* |
| (all others) | 85–95 | FROZEN |

*Minor warnings accepted — no blocking issues

**Blockers resolved:**
1. `executePlatformDatabaseRestore()` — IMPLEMENTED (11-step flow, maintenance mode, pre-snapshot, MYSQL_PWD)
2. `RestorePlatformDatabaseJob` — CREATED (`$timeout=3600`, `$queue=restore`, `failed()` releases lock + `artisan up`)
3. `config/queue.php` — Added `restore` connection (`retry_after=7200`)
4. All restore jobs — dispatch `->onConnection('restore')->onQueue('restore')`
5. InstallerValidator — `sync` driver is now hard FAIL
6. Restore atomicity — `DB::transaction()` wraps delete+copy; parameterized SQL
7. `BackupTenantDatabaseCommand` — CREATED, scheduled daily 02:30
8. ArvanBackupService `prune()` — ADDED, called by all 3 backup commands
9. `ArvanBackupService::listFiles()` — O(2N+1)→O(1) via `listContents()`
10. MYSQL_PWD — All mysqldump/mysql CLI calls converted
11. Scheduler heartbeat — `Cache::put('scheduler_last_run', now(), 120)` every minute
12. SCHEDULER_DEAD — Fixed: checks heartbeat cache, not `InfraSchedulerRun`
13. DB composite index — Migration `2026_06_27_000001_add_composite_index_to_infra_backup_runs.php`

**Documents generated:**
- `docs/CORE_BLOCKERS_IMPLEMENTATION.md` — all 13 changes with per-file evidence
- `docs/CORE_HEALTH_REASSESSMENT.md` — per-finding PASS/WARNING/FAIL table
- `docs/CORE_FREEZE_READINESS_V2.md` — final gate assessment + CORE_READY_FOR_FREEZE verdict

**Accepted warnings (not blocking):**
- SUP-001: Supervisor group name mismatch (server-side, ops)
- DR-SCORE-01: evalRestoreLogs() bias on fresh installs (cosmetic)
- DR-ALERT-02: ALERT-007 TTL math (minor false-positive window)
- FIN-IDEM-01: createManual() no idempotency (admin-only)
- FIN-BOUND-02: finance amount no upper bound (admin-only)
- SEC-RATE-01: no rate limit on license validate (Phase 3 hardening)

**NEXT STEP:** Phase 3 — Restore Engine Enterprise (requires user approval to start)

**POST-DEPLOY REQUIRED:**
- `php artisan migrate --force` (composite index migration)
- Verify redis queue worker active: `supervisorctl status`
- Test tenant backup: `php artisan db:backup-tenants --dry-run`

---

## CURRENT STATE (2026-06-26 — PLATFORM_INSTALLER_FOUNDATION_FINALIZED)

**Last completed phase:** PLATFORM_INSTALLER_FOUNDATION_FINALIZED — Enterprise-level installer foundation complete. All 5 tasks: Manifest, Immutable Baselines, Verification, Report Improvement, Admin Panel.

**New files:**
- `app/Services/Installer/BaselineManifestService.php` — streaming SHA-256, manifest.json self-covering checksum, immutable naming, full integrity verification, read-only lock
- `app/Http/Controllers/Admin/InstallerAdminController.php` — read-only admin panel controller
- `resources/views/admin/installer/index.blade.php` — admin dashboard view
- `docs/INSTALLER_BASELINE_MANIFEST.md` / `INSTALLER_BASELINE_SECURITY.md` / `INSTALLER_BASELINE_CERTIFICATION.md`

**Modified files:**
- `UpgradeAdapter.php` — Phase 1: `createInstallationBaseline($uuid)` + Phase 2: `addReportsToBaseline()` + `getBaselinesRootPath()`
- `InstallationReportService.php` — `enrichWithBaseline()` + 8 new report fields + `computeOverallStatus()`
- `PlatformInstallerController::processFreshFinish()` — 5-phase orchestration flow
- `installation-report.blade.php` — Baseline Snapshot + Overall Status banner + Certification sections
- `routes/web.php` — 5 admin installer routes under `admin.installer.*`
- `AdminMenu.php` + `sidebar-icon.blade.php` — `installer_status` key with shield icon

**Key invariants:**
- INITIAL_INSTALLATION_BASELINE is NEVER overwritten — subsequent baselines auto-named INSTALLATION_BASELINE_YYYYMMDD_HHMMSS
- manifest.json covers all baseline files with streaming SHA-256 + manifest self-covering checksum
- Baselines become read-only (0444/0555) after finalization
- Admin panel is strictly read-only — no delete, no overwrite, path traversal guard on all download/verify endpoints

**Certification:** INSTALLER_FOUNDATION_ENTERPRISE_CERTIFIED

**Next phase (requires explicit approval):** Restore Engine or Upgrade Engine — DO NOT START without user instruction.

---

## CURRENT STATE (2026-06-26 — SELECT_DROPDOWN_FIX_DEPLOYED)

**Last completed task:** SELECT_DROPDOWN_FIX — Admin UI `<select>` dropdowns in customer-site settings page now visible and functional.

**Two-layer fix applied:**
1. **CSS layer** (`module-components.css` + `customer-admin-layout.css`): Force `color-scheme: light`, `color: #1e293b`, `background-color: #fff` on all `select` elements to override dark-mode CSS variable inheritance.
2. **JS layer** (`tinymce-field-init.blade.php`): Lazy TinyMCE initialization — `tinymce.init()` is NOT called until the user first interacts with the textarea. This prevents TinyMCE from registering its global `document.mousedown` handler which was stealing focus from native `<select>` popups.

**Status:** DEPLOYED to `safhenokhost.ir` via customer-site deploy.

---

## CURRENT STATE (2026-06-26 — PLATFORM_INSTALLER_PHASE1_COMPLETE)

**Last completed phase:** PLATFORM_INSTALLER_PHASE_1 — Platform Installer scaffolding for all 3 modes.

**What was built:**
1. `app/Services/Installer/InstallerService.php` — orchestrator, session state, install.lock management
2. `app/Services/Installer/InstallerValidator.php` — 10 pre-flight environment checks
3. `app/Services/Installer/EnvironmentWriter.php` — atomic .env writer
4. `app/Services/Installer/InfrastructureInstaller.php` — migrations, seeding, Supervisor/Cron/Reverb config generation
5. `app/Services/Installer/RestoreAdapter.php` — wraps RestoreService + ArvanBackupService
6. `app/Services/Installer/UpgradeAdapter.php` — snapshot → migrate → validate → auto-rollback
7. `app/Services/Installer/HealthCheckRunner.php` — wraps DrReadinessScoreService
8. `app/Http/Controllers/Installer/PlatformInstallerController.php` — all 3 modes, all 15 steps
9. `app/Http/Middleware/InstallerLockMiddleware.php` — blocks installer if install.lock exists
10. 20 Blade views in `resources/views/installer/` — standalone RTL dark mode Persian UI wizard

**Routes added:** 9 routes under `/platform-installer/` prefix

**Admin Menu:** `platform_installer` added to زیرساخت / DevOps group with server icon

**Docs generated:**
- `docs/PLATFORM_INSTALLER_ARCHITECTURE.md`
- `docs/PLATFORM_INSTALLER_SECURITY.md`
- `docs/PLATFORM_INSTALLER_FLOW.md`
- `docs/PLATFORM_INSTALLER_CERTIFICATION.md`

**Key decisions:**
- Session-based state (not DB) so installer works before DB is configured
- Standalone layout — no dependency on admin sidebar
- `install.lock` = JSON file in `storage/` (not DB record)
- Confirmation strings: `RESTORE-PLATFORM`, `UPGRADE-PLATFORM`, `UNLOCK-INSTALLER`
- UpgradeAdapter auto-rollbacks on migration failure

**Phase 2 blockers:**
- `PlatformSeeder` not yet implemented (BLOCKER-1)
- Integration tests on real VPS (BLOCKER-2)

**No outstanding steps for Phase 1.**

---

## CURRENT STATE (2026-06-26 — PERSIAN_LOCALIZATION_GLOBAL_STANDARD_DEPLOYED)

**Last completed phase:** PERSIAN_LOCALIZATION_GLOBAL_STANDARD — Full Persian digit + Jalali date localization across both repos.

**What was built:**
1. `PersianFormatter` service class (both repos) — single entry point for all number/date localization
2. `app/Helpers/persian_helpers.php` (both repos) — `num_fa()`, `price_fa()`, `date_fa()`, `datetime_fa()`, `ago_fa()`, `digits_fa()`, `jalali_to_gregorian()` autoloaded via composer
3. 8 Blade directives in `AppServiceProvider` (both repos): `@numFa`, `@priceFa`, `@dateFa`, `@dateFaLong`, `@dateFaShort`, `@datetimeFa`, `@agoFa`, `@digitsFa`
4. `<x-jalali-datepicker>` Alpine.js component (saas-platform)
5. `.persian-date-input` / `.persian-date-defer` jQuery picker classes (customer-site)
6. All `number_format()` in user-facing views replaced — 0 violations remain in either repo

**Files changed (saas-platform views):** 49 view files, UserController.php (date conversion), _filters.blade.php (date picker)
**Files changed (customer-site views):** 54+ view files, OrderController.php, UserPanelController.php (date conversion), orders/index, users/index (date picker inputs)

**Certification docs generated:**
- `docs/PERSIAN_LOCALIZATION_STANDARD.md`
- `docs/LOCALIZATION_AUDIT.md`
- `docs/DATE_PICKER_ARCHITECTURE.md`
- `docs/PERSIAN_LOCALIZATION_CERTIFICATION.md`

**Key permanent rules added:**
- No `number_format()` in any Blade template — use `@numFa()` or `num_fa()`
- No `type="date"` inputs — use Jalali date pickers
- Controllers must convert incoming Jalali dates to Gregorian before DB operations
- SVG `stroke-dasharray`/`stroke-dashoffset` are intentionally excluded (readiness-score.blade.php)

**No outstanding steps.**

---

## CURRENT STATE (2026-06-26 — SITE_THEME_PRESET_FIX_DEPLOYED)

**Last completed phase:** SITE_THEME_PRESET_FIX_DEPLOYED — Bug fix: site theme presets now apply to the public customer site frontend; custom mode fully operational.

**Root causes fixed:**
1. `partials.theme-engine-tokens` was missing from both public layouts — preset CSS vars were never emitted on public pages
2. `ThemeSetting::toCssVariables()` only generated a minimal set of CSS vars — custom mode had no effect
3. `ThemeSetting` model was missing 9 color columns (accent, background, surface, text, muted, border, link, header_bg, footer_bg)

**Files changed (customer-site):**
- `database/migrations/2026_06_26_110000_add_color_fields_to_theme_settings_table.php` — NEW: adds 9 color columns
- `app/Models/ThemeSetting.php` — 9 new fields in $fillable, defaults, toCssVariables() fully rewritten
- `app/Services/ThemeEngine/TenantThemeResolver.php` — fromThemeSetting() now generates full CSS var set; extractSiteTokens() adds --platform-primary
- `resources/views/themes/default/layout.blade.php` — added @include('partials.theme-engine-tokens') at line 77
- `resources/views/layouts/canvas-theme.blade.php` — added @include('partials.theme-engine-tokens') at line 38
- `resources/views/admin/theme-design/index.blade.php` — custom card in preset grid + expanded custom color form (4 sections)
- `app/Http/Controllers/Admin/ThemeDesignController.php` — validates 9 new fields; auto-sets site_theme_preset_slug='custom' on save

**Production verification:**
- Migration at batch [111] — CONFIRMED
- ThemeSetting: 19 fillable fields — CONFIRMED
- theme-engine-tokens in default/layout.blade.php (line 77) — CONFIRMED
- theme-engine-tokens in canvas-theme.blade.php (line 38) — CONFIRMED

**No outstanding steps.**

---

## CURRENT STATE (2026-06-26 — DISASTER_RECOVERY_CENTER_ENTERPRISE_READY)

**Last completed phase:** DR_CENTER_PHASE2_COMPLETION — All 4 enterprise features added to the DR Center.

**New files created:**
- `database/migrations/2026_06_26_200000_create_dr_simulations_table.php`
- `app/Models/Infrastructure/DrSimulation.php`
- `app/Services/Infrastructure/DrReadinessScoreService.php`
- `resources/views/admin/disaster-recovery/center/partials/readiness-score.blade.php`
- `resources/views/admin/disaster-recovery/center/partials/arvan-status.blade.php`
- `resources/views/admin/disaster-recovery/center/simulation-history.blade.php`

**Files modified:**
- `app/Http/Controllers/Admin/DrCenterController.php` — readiness score, simulation, Arvan detail, integrity check tracking
- `routes/web.php` — 2 new simulation routes
- `resources/views/admin/disaster-recovery/center/index.blade.php` — readiness score + Arvan status sections
- `resources/views/admin/disaster-recovery/center/partials/platform-health.blade.php` — Jalali backup age

**Production step required:**
- `php artisan migrate` — creates `dr_simulations` table

**Certification:** `docs/DR_CENTER_FINAL_CERTIFICATION.md` — DISASTER_RECOVERY_CENTER_ENTERPRISE_READY

**Next options:**
1. Platform Installer Phase 3a (deploy.ps1 Arvan release upload)
2. EDU-17: AI provider live wiring

---

## CURRENT STATE (2026-06-26 — DR_CENTER_OPERATIONAL)

**Last completed phase:** DR_CENTER_OPERATIONAL — Phase 2 fully implemented and deployed.

**New files created:**
- `app/Http/Controllers/Admin/DrCenterController.php` (7 methods)
- `app/Services/Infrastructure/DrIntegrityService.php`
- `app/Console/Commands/ScanDrHealthCommand.php` (`dr:scan-health`)
- `database/migrations/2026_06_26_100000_create_dr_alert_log_table.php`
- `resources/views/admin/disaster-recovery/center/` (9 view files)

**Production steps required:**
1. `php artisan migrate` — creates dr_alert_log (optional but recommended)
2. Scheduler auto-runs `dr:scan-health` hourly

**DR Center URL:** `/admin/disaster-recovery/center`
**Alert History URL:** `/admin/disaster-recovery/center/alerts`

**Previous:** DISASTER_RECOVERY_CENTER_DESIGN_COMPLETE — 3 design documents produced.

**Next options:**
1. Phase 3a: deploy.ps1 Arvan release upload (4h)
2. EDU-17: AI provider live wiring

---

## CURRENT STATE (2026-06-26 — RESTORE_WIZARD_OPERATIONAL)

**Last completed phase:** RESTORE_WIZARD_OPERATIONAL — Phase 1 Restore Wizard fully implemented, 30 tests, security audit PASS, certified.

**What was built:**
- 7-step Alpine.js wizard at `/admin/disaster-recovery` (Operations → Disaster Recovery → Restore Wizard)
- 4 restore types: platform_database, platform_storage, tenant_database, tenant_storage
- Safety: Redis NX lock, dry-run simulation, pre-restore mysqldump snapshot, tries=1 jobs
- SSE real-time progress streaming via Redis list + EventSource
- RestoreLog model + `restore_logs` table + audit trail
- Security: CSRF, escapeshellarg(), site_id validation, platform DB requires "RESTORE-PLATFORM" text

**Production steps NOT yet run:**
- `php artisan migrate` — needed for restore_logs table
- `php artisan queue:work --queue=restore` — needed for job execution
- Nginx SSE timeout: verify `fastcgi_read_timeout 300`

**Next options:**
1. Phase 2: DR Center (29h) — disaster recovery monitoring dashboard
2. Phase 3a: deploy.ps1 Arvan release upload (4h)
3. EDU-17: AI provider live wiring

---

## CURRENT STATE (2026-06-26 — INSTALLER_ARCHITECTURE_DECISION_PHASE)

**Last completed phase:** INSTALLER_ARCHITECTURE_DECISION_COMPLETE — Architecture + documentation only. Zero code changes.

**5 Architecture Decisions Finalized:**
1. **Deployment Model:** OPTION A — Pre-built release ZIPs uploaded to Arvan `saas-platform/releases/` (extends existing deploy.ps1)
2. **Installer Architecture:** Hybrid Web (standalone PHP wizard) + CLI (Artisan commands) — 5 layers defined
3. **PlatformSeeder:** AdminUserSeeder + DefaultPlanSeeder + PlatformSettingsSeeder — all idempotent, all use `updateOrCreate`
4. **Restore Wizard:** 4 flows (tenant DB / platform DB / storage / full disaster) with SSE streaming + pre-restore snapshots
5. **Tenant Provision Wizard:** 6-step TenantProvisioningService orchestrating all existing services

**Blockers to resolve (permitted under LAUNCH FREEZE):**
- BLOCKER-1: PlatformSeeder does not exist → 4h implementation → spec in `docs/PLATFORM_SEEDER_SPECIFICATION.md`
- BLOCKER-2: deploy.ps1 does not upload release ZIPs to Arvan → 2h extension

**Architecture docs:**
- `docs/INSTALLER_ARCHITECTURE_DECISION.md` — deployment model + 5 installer layers
- `docs/PLATFORM_SEEDER_SPECIFICATION.md` — seeder scope + constraints (users.name forbidden)
- `docs/RESTORE_WIZARD_ARCHITECTURE.md` — 4 restore flows
- `docs/TENANT_PROVISION_ARCHITECTURE.md` — provision workflow
- `docs/INSTALLER_IMPLEMENTATION_ROADMAP.md` — 6 phases + estimates

**V2 Correction (2026-06-26):**
- customer-site = demo/showcase, no real customers → roadmap reprioritized to DR-first
- BLOCKER-1 (PlatformSeeder) is NO LONGER a pre-blocker — it is Phase 4 internal deliverable
- BLOCKER-2 (deploy.ps1 release upload) is Phase 3a deliverable
- Disaster Recovery Center added as Phase 2 (was missing from V1)

**Next action (freeze-permitted):**
1. Phase 1: Restore Wizard (41h) — start here
2. Phase 2: DR Center (29h)
3. Phase 3a: deploy.ps1 Arvan release upload (4h — can run in parallel)

**Roadmap:** `docs/INSTALLER_IMPLEMENTATION_ROADMAP_V2.md` (supersedes V1)

**⛔ LAUNCH FREEZE ACTIVE** — Architecture decisions only completed this session. No code written.

---

## CURRENT STATE (2026-06-25 — CUSTOMER_HOSTING_EXPIRY_WIDGET_FIX)

**Last completed phase:** CUSTOMER_HOSTING_EXPIRY_WIDGET_FIX — Production bug fix, allowed under Launch Freeze

**Root Cause Fixed:** Customer dashboard hosting expiry widget showed stale/wrong date and had no CTA.
- `ExpiryReminderService` embedded expiry date in `PlatformSiteNotification.link` as internal marker
- On admin renewal, the marker was never updated → stale date shown indefinitely
- `SiteConfigController` read from stale marker instead of `Site.host_expires_at`

**Code Changes (saas-platform):**
- `app/Http/Controllers/Api/SiteConfigController.php`: always reads `Site.host_expires_at` as source of truth; if days_remaining > 30 → deactivates notification; fixes stale link in DB; adds `/admin/inbox` renewal CTA
- `app/Http/Controllers/Admin/SiteController.php`: `syncExpiryNotifications()` called after every `update()` to proactively sync notifications

**Code Changes (customer-site):**
- `resources/views/admin/dashboard.blade.php`: CTA link rendered as amber button; internal vs external link detection

**Certification:** `customer-site/docs/HOSTING_EXPIRY_WIDGET_CERTIFICATION.md` — CUSTOMER_HOSTING_EXPIRY_FIXED

**⛔ LAUNCH FREEZE ACTIVE** — This was a permitted production bug fix.

---

## CURRENT STATE (2026-06-25 — LICENSE_SYNC_FALSE_WARNING_ELIMINATION)

**Last completed phase:** LICENSE_SYNC_FALSE_WARNING_ELIMINATION — Production bug fix, allowed under Launch Freeze

**Root Cause Fixed:** Customer dashboard showed permanent "هشدار موقت لایسنس" banner.
- SSL cert at `https://panel.safhenokhost.ir` was self-signed → cURL error 60 on every request
- `last_validated_at` not updated on transient skip → retry storm (2 log errors per page load)

**Code Changes (customer-site):**
- `app/Services/LicenseActivationService.php`: `withoutVerifying()` when `LICENSE_VERIFY_SSL=false`; timeout 10→15s; `last_validated_at` updated on transient skip; banner TTL 30→2×interval
- `config/license.php`: added `verify_ssl` config key

**Production `.env` changes (`/var/www/customer-site/.env`):**
- `PLATFORM_URL=http://panel.safhenokhost.ir` (was https — HTTP block exists for ArvanCloud Flexible SSL)
- `LICENSE_VERIFY_SSL=false`

**Certification:** `customer-site/docs/LICENSE_SYNC_CERTIFICATION.md` — LICENSE_SYNC_FALSE_WARNING_ELIMINATED

**⛔ LAUNCH FREEZE ACTIVE** — See `docs/LAUNCH_FREEZE_RULES.md`. This was a permitted production bug fix.

---

## PROJECT SUMMARY

**SaaS Platform** — A multi-tenant Laravel 10 SaaS built for managing customer support, forms, education content, and platform communication. One Laravel installation serves multiple customer sites via domain-based tenant resolution (`site_id` scoping).

**Two repositories:**
- `c:\laragon\www\saas-platform` — Platform admin, API, all packages
- `c:\laragon\www\customer-site` — Per-tenant customer-facing application

**Stack:** Laravel 10, PHP 8.4, MySQL, Redis, Tailwind CSS v4.3, Alpine.js, Flutter (mobile)

**Architecture:** Package-based modules at `packages/saas-platform/modules/`. No `App\` imports allowed inside packages.

---

## CURRENT STATE (2026-06-23)

**Last completed phase:** FINAL_PRE_LAUNCH_VERIFICATION — 2 critical canvas theme bugs permanently fixed (2026-06-23)

**Previous phase:** CANVAS_THEME_BUGFIX (2026-06-23) + PRE_LAUNCH_POLISH_AND_HARDENING — PRE_LAUNCH_HARDENING_COMPLETE (2026-06-22)

**⛔ LAUNCH FREEZE ACTIVE** — No new features, no new modules, no new DB tables, no architectural changes.
Allowed: BUG FIX | UX IMPROVEMENT | PERFORMANCE | SECURITY | DOCUMENTATION | MONITORING
See `docs/LAUNCH_FREEZE_RULES.md` for complete policy.

**Pre-Launch Hardening Documents Generated:**
- `docs/PRE_LAUNCH_CUSTOMER_UX_REPORT.md` — 3 UX fixes applied (CUX-006/008/009), customer score 89→92
- `docs/PRE_LAUNCH_ADMIN_UX_REPORT.md` — 1 UX fix applied (AUX-008 state bar), admin score 90→93
- `docs/SSL_HARDENING_REPORT.md` — SSL audit, 2 pre-launch actions (APP_URL + CORS), ArvanCloud CDN plan
- `docs/OFFSITE_BACKUP_AUDIT.md` — Backup audit, CRITICAL gap: onsite-only DB backup, restore procedure
- `docs/MONITORING_HARDENING_REPORT.md` — 3 monitoring gaps (disk/backup-overdue/scheduler-dead alert)
- `docs/LAUNCH_DAY_RUNBOOK.md` — Full launch day runbook with T-60→T+48h sequence, rollback procedures

**Code Changes (2026-06-23 — Canvas Theme Bugfix):**
- `customer-site/app/Models/Page.php`: `getBuilderContentForRender()` now normalizes `['blocks']` → `['sections']` so canvas-provisioned pages render correctly after deactivation
- `customer-site/resources/views/themes/default/page.blade.php`: switched from `BlockRenderer` to `ThemeCanvasRenderer` so `module:*` blocks show live module data (courses, products, etc.)
- `customer-site/app/Http/Controllers/Front/ThemeCanvasPageController.php`: `loadThemeData()` now reads `active_canvas_theme_data` directly from DB with 60s TTL (bypasses 1h settings cache — platform admin installs visible within 60s)
- `customer-site/app/Http/Controllers/Admin/ThemeMarketplaceController.php`: `install()` now calls `Cache::forget('canvas_theme_data')` after install
- `saas-platform/app/Http/Controllers/Admin/ThemeCanvasController.php`: `installToSite()` now writes all 4 canvas settings (previously only wrote `active_canvas_theme_data`, missing name/slug/id)

**Code Changes (previous session):**
- `SiteLifecycleService.php`: pushes `site_lifecycle_changed_at` to customer-site settings on every transition
- `front/suspended.blade.php`: shows archive deadline block (Jalali date + days remaining)
- `front/lifecycle/pay-failed.blade.php`: Alpine.js 3-min cooldown timer on retry button
- `account/installments/show.blade.php`: next pending payment row highlighted amber + "بعدی" badge
- `admin/lifecycle/overview.blade.php`: state distribution bar (5 colors, proportional)

**Before that:** RC1_LAUNCH_FREEZE_ENFORCEMENT — LAUNCH_FREEZE_ACTIVE (2026-06-22)

**RC1 Launch Freeze Documents Generated:**
- `docs/LAUNCH_FREEZE_RULES.md` — Complete freeze policy + roadmap gate
- `docs/PRE_LAUNCH_CLEANUP_REPORT.md` — Codebase cleanup audit (PASS)
- `docs/CUSTOMER_UX_REVIEW.md` — 10 customer UX improvements identified (89/100)
- `docs/ADMIN_UX_REVIEW.md` — 12 admin UX improvements identified (90/100)
- `docs/OPERATIONAL_HEALTH_REPORT.md` — 24 schedulers + queue + backup + SMS verified (OPERATIONAL_HEALTH_VERIFIED)

**Before that:** RELEASE_CANDIDATE_1 — RC1_READY (2026-06-22)
**Status:** All 15 systems AUDIT_PASS. GAP-L07 fixed. 3 RC1 documents generated. POST_LAUNCH_ROADMAP created. Platform cleared for production go-live.

**RC1 Documents:**
- `docs/RELEASE_CANDIDATE_1.md` — RC1 identity, audit results, module inventory
- `docs/GO_LIVE_CHECKLIST.md` — pre-launch, launch-day sequence, rollback plan
- `docs/POST_LAUNCH_ROADMAP.md` — H01, Collaborative Editing, Analytics, Marketplace, AI

**Before that:** BFA Batch 3 (LOW) — All 8 remaining business flow gaps closed — BFA_BATCH3_COMPLETE (2026-06-22)
**Before that:** RE Accuracy V2 — REVERSE_ENGINEERING_V2_CERTIFIED (2026-06-22)
**Before that:** TB3 Sprint 12 — ZIP Template Import Engine — ZIP_IMPORT_ENGINE_OPERATIONAL (2026-06-22)
**Before that:** BFA High Priority Batch — BFA-M05 + BFA-H02 + BFA-H03 COMPLETE (2026-06-22)
**Before that:** Acool HVAC Theme v2.0 — ACOOL_HVAC_V2_CERTIFIED (2026-06-22)
**Before that:** Production Blocker Remediation — ALL 5 CRITICAL BLOCKERS RESOLVED (2026-06-22)
**Status:** Business Flow Gap Remediation COMPLETE. All gaps (C01–C05, H01–H07, M01–M10, L01–L09) resolved or documented. Platform is production-ready.

### Business Flow Remediation — ALL BATCHES COMPLETE

**Batch 1 — CRITICAL (C01–C05):** All 5 production blockers resolved.
**Batch 2 — HIGH/MEDIUM (H04–H07, M01–M04, M07–M09):** All 11 gaps resolved.
**Batch 3 — LOW (M10, L01–L03, L05–L06, L08–L09):** All 8 gaps resolved.

**Deferred (by design):**
- GAP-H01 (Two Theme Systems Without Bridge) — complex, deferred
- GAP-M06 (Admin Panel Accessible During Suspension) — documented behavior
- GAP-L04 (Campaign Stuck Recovery) — resolved in previous session (recoverStuckCampaigns)

### RE Accuracy V2 — REVERSE_ENGINEERING_V2_CERTIFIED (2026-06-22)

**Fixes Applied:**
- RE-FP-003 (nav labels): 3-pass XPath extraction in HTTP mode, `nav_labels[]` + `nav_depth` in DOM structure
- RE-FP-004 (Tailwind JIT): 80+ TAILWIND_COLOR_MAP in extract.mjs, 3-pass color extraction, `mergeTailwindColors()` in VisualAnalysisService
- RE-FP-007 (breakpoints): CSS @media rule extraction in extract.mjs, real breakpoints in ThemePack global_styles
- SimilarityEngine: 2 new reference sites (tailwindcss/maktabkhooneh), URL boost +10, landing/education type weights

**V2 Scores:** amazon 99%, aws 90%, stripe 93%, maktabkhooneh 89%, tailwindcss 76% | Avg 89.4%

**New Docs:**
- `docs/REVERSE_ENGINEERING_ACCURACY_V2.md`
- `docs/TOP_FAILURE_PATTERNS_V2.md`
- `docs/SIMILARITY_SCORE_REPORT_V2.md`

### Real-World RE Validation — PASSED (2026-06-22)

**Command:** `php artisan re:validate-real-world`

**Results (V1 baseline):**

| Site | Category | Render | Similarity | Stages | Result |
|------|----------|--------|-----------|--------|--------|
| aws.amazon.com | corporate | HTTP | 80% | 7/7 | ✅ PASS |
| www.amazon.com | ecommerce | Playwright | 99% | 7/7 | ✅ PASS |
| stripe.com | service | Playwright | 83% | 7/7 | ✅ PASS |
| maktabkhooneh.org | education | HTTP | 73% | 7/7 | ✅ PASS |
| tailwindcss.com | landing | Playwright | 42% | 7/7 | ⚠️ PARTIAL → V2: 76% ✅ |

**Pipeline Stages Verified:** Extraction → Reconstruction → ThemePack → Marketplace Publish → Tenant Install → Canvas Editing → Customization

**New Files:**
```
app/Console/Commands/RealWorldValidationCommand.php    — 5-category validation suite
scripts/headless/extract.mjs                           — 2 Playwright selector bugs fixed
docs/REAL_WORLD_VALIDATION_REPORT.md                  — per-site detailed report
docs/TOP_FAILURE_PATTERNS.md                          — 4 failure patterns + fixes
docs/REVERSE_ENGINEERING_ACCURACY_REPORT.md           — aggregate accuracy + certification
```

**Open Issues (see KNOWN_ISSUES.md):**
- RE-FP-003: Navigation extraction 0% — JS menus not in raw DOM
- RE-FP-007: Responsive breakpoints not injected into ThemePacks
- RE-FP-004: CSS-variable-less sites (TailwindJIT) score low

### SMS Campaign Manager — COMPLETE (2026-06-21) [customer-site]

**Files:**
```
customer-site/database/migrations/2026_06_21_100000_create_sms_campaigns_tables.php
customer-site/app/Models/SmsCampaign.php
customer-site/app/Models/SmsCampaignRecipient.php
customer-site/app/Services/SmsService.php          (updated — real Kavenegar+Ghasedak impl)
customer-site/app/Services/SmsCampaignService.php
customer-site/app/Jobs/SendSmsCampaignJob.php
customer-site/app/Http/Controllers/Admin/SmsCampaignController.php
customer-site/resources/views/admin/sms/campaigns/{index,create,show,edit}.blade.php
customer-site/routes/web.php                       (10 new routes)
customer-site/app/Helpers/AdminMenu.php            (sms_campaigns key added)
customer-site/resources/views/admin/partials/sidebar-icon.blade.php (megaphone icon)
```

**Features:**
- Campaign lifecycle: draft → scheduled/running → completed/failed/cancelled
- 4 audience types: all orders / CRM contacts / active users / manual mobile list
- Bulk send: ≤50 recipients inline, >50 dispatched via `SendSmsCampaignJob` in chunks of 50
- SMS providers: Kavenegar v1 + Ghasedak v2 (real HTTP implementations)
- Delivery tracking per recipient: pending/sent/failed with sent_at and error_message
- Admin panel: index (filter by status, stats cards), create, show (delivery progress bar), edit
- AdminMenu group: `فرم‌ها و ارتباط با مخاطب` → sms_campaigns

**✅ PRODUCTION ACTIVATED (2026-06-21):**
- Migration `2026_06_21_100000_create_sms_campaigns_tables` — Ran [4]
- Tables `sms_campaigns` + `sms_campaign_recipients` verified with indexes and FK
- All 12 routes, 4 views, AdminMenu, Service, Job validated
- Reports: `SMS_CAMPAIGN_DEPLOY_REPORT.md`, `DEPLOY_VALIDATION_REPORT.md`, `DEPLOY_FIX_REPORT.md`

### TB3 Sprint 11.5 — Playwright Headless Engine Enhancement — CERTIFIED (2026-06-21)

**Goal:** Increase RE accuracy from 70-75% → 90%+ for React/Next.js/Vue/Angular/SPA sites.

**New files:**
- `app/Services/ThemeBuilder/ReverseEngineering/PlaywrightSnapshotService.php` — DOM/CSS/Layout snapshots
- `app/Console/Commands/ValidateHeadlessEngineeringCommand.php` — 6-site validation (apple/malltina/digikala/amazon/stripe/shopify)
- `docs/TB3_SPRINT11_5_CERTIFICATION.md`, `docs/PLAYWRIGHT_ENGINE_ARCHITECTURE.md`, `docs/HEADLESS_BROWSER_VALIDATION_REPORT.md`

**Enhanced files:**
- `scripts/headless/extract.mjs` — multi-viewport (D/T/M), 4-scroll lazy load, SPA hydration wait, 60+ product selectors, CSS vars, ARIA, perf metrics, framework detection
- `SimilarityEngineService` — headless quality bonus (0-15 pts), headless brand boost (+38 vs +32), EXCELLENT tier at 90%+
- `VisualReverseEngineeringService` — snapshot generation, device param, `meta['snapshots']`/`meta['playwright']`
- `PlaywrightExtractionService` — device param forwarded to Node argv[4]
- `reverse-engineer-result.blade.php` — Screenshot preview, Performance metrics, Snapshots Explorer

**Validation command:** `php artisan reverse-engineer:validate-headless`

**Run output:** `HEADLESS_ENGINE_OPERATIONAL` (all targets met) or `HEADLESS_ENGINE_BLOCKED` (Playwright unavailable)

---

### JSON Import Engine — COMPLETE (2026-06-21)

**Pipeline:** JSON → ThemeStructureAnalyzer → Adapter → WidgetMapper → ThemePackGenerator → ThemePack

**5 Supported Sources:**
- Elementor: `elType` section→column→widget hierarchy, 35 widget types
- Bricks: nested (children) + flat (parent ids) formats, 25 types
- Shopify: sections.json multi-page, header/footer auto-split, 15 types
- Webflow: payload.pages, tag→type fallback, node tree, 16 types
- Custom: ThemePack v1 passthrough + generic JSON

**Quality Scoring (100 pts):** page coverage (25) + block variety (25) + header/footer (20) + block count (20) + home content (10)

**Admin UI:** `/admin/theme-builder/json-import`
- Drag-drop JSON upload with AJAX auto-detection banner
- Source detected + metadata preview before submission
- Force-source override for edge cases
- Result page: quality score, block type breakdown, install → Canvas, export normalized JSON

**Routes (6):** form / detect (AJAX) / import / result / install / export

**Files:**
```
app/Services/ThemeBuilder/JsonImport/ThemeStructureAnalyzer.php
app/Services/ThemeBuilder/JsonImport/WidgetMapper.php
app/Services/ThemeBuilder/JsonImport/ThemePackGenerator.php
app/Services/ThemeBuilder/JsonImport/JsonImportPipelineService.php
app/Services/ThemeBuilder/JsonImport/Adapters/{Elementor,Bricks,Shopify,Webflow,Custom}Adapter.php
app/Http/Controllers/Admin/ThemeJsonImportController.php
resources/views/admin/theme-builder/json-import.blade.php
resources/views/admin/theme-builder/json-import-result.blade.php
docs/JSON_IMPORT_ENGINE_CERTIFICATION.md
```

### Theme Marketplace — COMPLETE (Sprints 10–12, 2026-06-20)

**Platform admin (`/admin/theme-marketplace`):**
- Seed 12 professional themes via "راه‌اندازی" button or `marketplace:seed` command
- 5-dimension quality scoring dashboard (`/scoring`)
- Per-theme preview page with full metadata, pricing cards, quality scores, screenshots
- Demo data manifest (block scanner, `ThemeDemoDataService`)
- Search + category filter, bestseller/new/featured badges
- `installs_count` increment + auto-bestseller at 10+
- `installed_theme_version` tracking on licenses (GAP-011)

**Platform API (`/api/theme-marketplace`):**
- `GET /` — returns all Sprint 10 fields: category, license_type, annual/lifetime price, badges, quality_score, preview_url
- `GET /{id}` — single theme detail
- `GET /my-licenses` — now returns thumbnail, version, is_outdated per license
- `GET /{id}/download` — records install (installs_count++, installed_theme_version)

**Customer-site admin:**
- `GET /admin/theme-marketplace` — index with Alpine.js category filter + search, quality score chip, pricing tiers, all badges
- `GET /admin/theme-marketplace/{id}` — **NEW** full detail page (hero image, pricing sidebar, screenshots, CTA)
- `GET /admin/theme-marketplace/my-licenses` — thumbnails, "آپدیت موجود" badge, version notes, orange install button for outdated

**Deferred:** GAP-010 Theme Bundle System (requires `theme_bundles` table + separate purchase flow)

### Post-deploy action needed
Navigate to `/admin/theme-marketplace` and click **"راه‌اندازی"** to seed 12 themes + score them all in one click.

### DATE LOCALIZATION Summary (2026-06-20)

**Scope:** 72 findings across both repos (5 CRITICAL, 52 HIGH, 3 MEDIUM, 12 LOW)
**All CRITICAL + HIGH fixed:** 57 fixes across 46 files
**New infrastructure:** `diffForHumansFaStatic()` in CoreJalali — Persian relative time (همین الان / ۳ دقیقه پیش / دیروز)
**Key patterns eliminated:**
- `NumberHelper::toPersianNumbers(->format('Y/m/d'))` — Gregorian year with Persian digits
- `->diffForHumans()` — Carbon English relative time
- `Jalali::format()` in display contexts (was ASCII digits, fixed to `formatFa()`)
**Reports:** `docs/GLOBAL_DATE_LOCALIZATION_AUDIT.md` + `docs/DATE_LOCALIZATION_FIX_REPORT.md`

### RE Pipeline Real-World Validation (2026-06-21)

**Verdict: REVERSE_ENGINEERING_VALIDATED (EXCELLENT)**

| Site | Score | Grade | HTML Size | Render Mode |
|------|-------|-------|-----------|-------------|
| apple.com | 96% | EXCELLENT | 287KB | SSR |
| malltina.com | 70% | PASS | 368KB | Next.js App Router (all JS) |
| digikala.com | 70% | PASS | 3.5KB | Next.js Static Shell (CSR) |

**5 Pipeline Bugs Fixed:**
1. `DomExtractionService`: CSS selector XPath crash (`.hero` → Invalid XPath)
2. `VisualAnalysisService`: Duplicate method + brightness filter for primary color
3. `DomExtractionService`: Added `detectRenderMode()` + `semanticFallback()` (HTML5 elements)
4. `LayoutReconstructionService`: Infer sections from semantic `<section>` count
5. `SimilarityEngineService`: URL-based brand boost (+32pts) for 6 known reference sites
6. `CommerceAnalysisService`: URL commerce hint + 60/100 knowledge floor

**Key Limitation Confirmed:** SPA/Next.js sites with minified CSS or CSR need Screenshot Mode for 85%+ accuracy.

**New assets:**
- `app/Console/Commands/ValidateReverseEngineeringCommand.php`
- `docs/APPLE_VALIDATION_REPORT.md`
- `docs/MALLTINA_VALIDATION_REPORT.md`
- `docs/DIGIKALA_VALIDATION_REPORT.md`
- `docs/re_validation_results.json`

---

### TB3 Sprint 9.5 Summary (2026-06-20)

**7-Phase Pipeline:**
1. DomExtractionService — DOMXPath, 12 component types (header/hero/pricing/testimonials/FAQ/blog/CTA/footer/grid/cards/features/mega-menu)
2. LayoutReconstructionService — column counts, container config, spacing system, section flow graph, responsive breakpoints
3. VisualAnalysisService — color palette (hex+RGB extraction), typography, button styles, radius system, shadow, visual density
4. CommerceAnalysisService — product cards, price blocks (Persian Toman+USD), discount badges, gallery, reviews, specs, cart flow
5. SimilarityEngineService — 4 scoring dimensions × 6 reference sites, Euclidean RGB color distance, type-weighted overall
6. ReverseThemeReconstructorService — Claude claude-sonnet-4-6 AI + ThemePlaceholderService fallback, ThemePack+ThemeTemplates DB
7. ReverseEngineeringQualityService — 10 checks (missing sections/hierarchy/duplicate IDs/block types/scores/commerce/pack), PASS/FAIL/PARTIAL

**New Files:** `reverse_engineering_jobs` migration+model, 8 service classes, controller, 2 Blade views, 6 routes, 4 docs
**Admin URL:** `/admin/theme-builder/reverse-engineer`

---

## PREVIOUS STATE (2026-06-19)

**Last completed phase:** TB3 Sprint 6 — Professional Template Library — CERTIFIED COMPLETE (2026-06-19)  
**Before that:** LIFECYCLE-2 — Storage & Resource Governance — COMPLETE (2026-06-19)  
**Status:** Both repos committed and deployed. Full template library: theme_templates + theme_packs tables, 3 services, 7 admin views, canvas integration (modal + save-as-template), 6 API endpoints, AdminMenu wiring.  
**Certification:** `docs/TB3_SPRINT6_CERTIFICATION.md` — TB3_SPRINT6_CERTIFIED  
**Next recommended milestone:** Additional UX improvements, or next sprint from backlog.

### LIFECYCLE-2 Summary (2026-06-19)

**4 Tables:** site_resource_usage, site_resource_limits, site_bandwidth_usage, site_storage_snapshots  
**4 Models:** SiteResourceUsage, SiteResourceLimit, SiteBandwidthUsage, SiteStorageSnapshot  
**4 Services:** SiteResourceCalculatorService (metrics), SiteResourceGovernorService (limits), SiteResourceAlertService (alerts), SiteResourceReportService (reports)  
**4 Commands:** resource:calculate (06:00 daily), resource:cleanup (Sunday 02:00), resource:archive-report (Monday 07:00), resource:usage-report (1st monthly 08:00)  
**Admin Panel:** `/admin/resource-governance` overview, `/admin/resource-governance/{site}` per-site, `/admin/resource-governance/settings`  
**Customer-site:** resource-widget (shows >60%, color-coded), /account/resources page  
**Config:** `config/resource_governance.php` — 13 env-driven keys  
**Architecture doc:** `docs/RESOURCE_GOVERNANCE_ARCHITECTURE.md`  
**Lifecycle integration:** SiteLifecycleService::archive() → releaseActiveResources() after ZIP created

### LIFECYCLE-1 Summary (2026-06-19)

**5 States:** active → grace_period → suspended → archived → deleted  
**3 Migrations:** lifecycle columns on sites, site_lifecycle_logs, site_lifecycle_invoices  
**3 Services:** SiteLifecycleService (state machine + push), SiteArchiveService (ZIP), SiteLifecycleNotificationService (SMS + dashboard)  
**4 Commands:** check-expired / check-grace-period / check-suspended / check-archived (all daily, Kernel wired)  
**Admin Panel:** `/admin/lifecycle` overview, `/admin/sites/{site}/lifecycle` per-site, `/admin/lifecycle/settings`  
**Customer-site:** CheckSiteLifecycle middleware, suspended page (503), dashboard widget  
**Config:** `config/lifecycle.php` — 6 keys (grace days, archive days, delete days, 3 fees)  
**Architecture doc:** `docs/CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md`

### Production Certification Summary (2026-06-19)

**Independent re-audit of all 12 PROD findings — PRODUCTION_CERTIFIED.**

| New Finding | Severity | Resolution |
|-------------|----------|------------|
| NEW-001 Cache health false-negative | CRITICAL | FIXED — string sentinel in `checkCache()` (`RuntimeHealthService.php`) |
| NEW-002 ZIP creation intermittent fail | LOW | Documented — non-structural |
| NEW-003 deploy.sh rsync wrong source | LOW | Documented — primary zip sync works |

**Health endpoint confirmed:** `/health/ready` returns `ready: true` with all 4 checks passing.  
**Scheduler confirmed:** `monitor:platform` (2min), `monitor:sites` (5min), `runtime:scan-anomalies` — all running.  
**Incident lifecycle confirmed:** create → investigate → resolve → SMS notification wired.  
**Full report:** `docs/PRODUCTION_CERTIFICATION_AUDIT.md`

### TB3 Sprint 2 Summary (2026-06-16)

| Feature | Detail |
|---------|--------|
| Undo/Redo | 25-entry JSON snapshot history; Ctrl+Z/Y shortcuts; toolbar buttons |
| Block Duplication | Clone with new ID; inserted after source; auto-selected |
| 4-button action bar | up / down / duplicate / delete (icon-only, compact) |
| Content/Style tabs | Props panel switches context |
| Per-block Style tab | bgColor, textColor, paddingY, paddingX, borderRadius, opacity, reset |
| `applyBlockStyle()` PHP | Inline CSS outer div in BlockRenderer; XSS-safe `escapeAttr()` |

Both `saas-platform` and `customer-site` updated in sync.  
Commits: `046b195` (saas-platform), `1b5bb33` (customer-site)

### Production Safety Summary (2026-06-16)

All 12 PRODUCTION_FIX_PLAN.md findings resolved:
- PROD-001: Health endpoints strip deployment metadata in production (`RuntimeHealthService`)
- PROD-002: MHC migrations confirmed ran (batch 10) — no action needed
- PROD-003/004/005: StorageManager already handles all uploads/CSV — pre-existing
- PROD-006: Composite `site_id` indexes already exist on all key tables
- PROD-007: `SitemapService` intentionally uses `public` disk — no change
- PROD-008: `SANCTUM_STATEFUL_DOMAINS=panel.safhenokhost.ir` set on production server
- PROD-009: `AppServiceProvider::boot()` throws if `CACHE_DRIVER=file` in production
- PROD-010: Shadow-var CSS warnings already resolved by KI-002
- PROD-011: `kb` key added to AdminMenu.php (Knowledge Base now in sidebar)
- PROD-012: 3 orphaned form controllers deleted (303 LOC removed)

### MHC — Monitoring & Health Center (2026-06-15)

**6 Migrations:** enhance_incidents (4-state lifecycle), incident_notes, platform_health_logs, backup_runs, scheduler_runs, extend_site_health_logs  
**5 Models:** InfraIncident (rewritten), InfraIncidentNote, InfraPlatformHealthLog, InfraBackupRun, InfraSchedulerRun  
**7 Services:** IncidentLifecycleService (new), UptimeCalculator (new), SchedulerHealthTracker (new), HealthCheckService (extended), SiteHealthChecker (extended), InfrastructureErrorCollector (extended), NotificationCenterService (extended)  
**2 Commands:** MonitorPlatformCommand (new, every 2min), MonitorSitesCommand (rewritten, 4-level severity)  
**Incident UI:** admin/incidents/index.blade.php + admin/incidents/show.blade.php  
**Monitoring UI:** admin/infrastructure/monitoring/index.blade.php (extended), admin/infrastructure/monitoring/site.blade.php (new)  
**Routes:** 7 incident routes (admin.incidents.*), site drilldown (admin.infrastructure.monitoring.site), platform-history API  
**Scheduler:** monitor:platform every 2 minutes  

**Key invariants:**
- Incident severity: critical / high / medium / low (NOT warning — migrated)
- Incident status: open / investigating / resolved / closed (4-state)
- `InfraIncident::active()` = open OR investigating
- Customer SMS sent on resolve via `IncidentLifecycleService::notifyCustomerOnResolve()`
- Platform incidents auto-created via `IncidentLifecycleService::open()` from `InfrastructureErrorCollector`

### KI/FI Resolution Batch (2026-06-15)

**Bug Fix — NumberHelper TypeError:**
- `customer-site/app/Helpers/NumberHelper.php` — `formatFa()` accepts pre-formatted strings (strips commas)
- Affected: all 5 installment views (account/admin/front), 19 occurrences total

**FI-008 — Runtime Anomaly Scanner:**
- `app/Console/Commands/ScanRuntimeAnomaliesCommand.php` — NEW
- Scheduled every 5 minutes in `Kernel.php`, Slack alert for critical/high
- 6/6 tests

**KI-006 — Domain Resolution Cache:**
- `app/Services/ResellerDetectionService.php` — Redis cache per host (TTL 300s)
- `customer-site/app/Http/Middleware/SetTenantContext.php` — License + schema caching
- `customer-site/app/Services/LicenseActivationService.php` — cache invalidation on update

**KI-002 — Tailwind v4 Syntax:**
- 301 replacements: `[var(--token)]` → `(--token)` across 27 core-ui components
- Shadow arbitrary values replaced with named `@utility` classes
- Lightning CSS warnings eliminated

### NEXUS PRO Summary (2026-06-14)

**Platform (saas-platform):**
- `app/Services/ThemeBuilder/BlockRegistry.php` — +15 premium block definitions
- `app/Services/ThemeBuilder/BlockRenderer.php` — +15 render methods
- `app/Console/Commands/NexusSeedCommand.php` — **NEW**: `php artisan nexus:seed`

**Customer Site:**
- `app/Services/ThemeCanvas/BlockRegistry.php` — +15 matching block definitions
- `resources/views/layouts/canvas-theme.blade.php` — +CSS for all 15 blocks + JS skills animation

**Themes in marketplace:**
- `nexus-pro-corporate` — Navy/Indigo, 6 صفحه شرکتی
- `nexus-pro-personal` — Dark Violet, 4 صفحه شخصی/پورتفولیو
- `nexus-pro-store` — Orange, 4 صفحه فروشگاهی

**New block types available:**
`hero_split`, `hero_gradient`, `features_grid`, `stats_row`, `client_logos`, `process_steps`, `team_cards`, `skills_bars`, `testimonials_slider`, `trust_badges`, `pricing_cards`, `newsletter_form`, `bento_grid`, `services_cards`, `contact_split`

### 5-Module Summary (2026-06-13)

**INSTALL — خرید قسطی:**
- Migration/Models: `installment_plans`, `installment_orders`, `installment_payments`
- `InstallmentService`, `InstallmentController` (front), `InstallmentAccountController` (account panel)
- Admin: `InstallmentAdminController` (plans CRUD + orders browse)
- Routes: `/installment/plans`, `/account/installments`, `admin/installments/*`
- Compound interest formula implemented in `InstallmentPlan::calculate()`

**LANG — چند زبانه AI:**
- Migration: `content_translations` (hash check) + `translation_queue`
- `HasTranslations` trait applied to Post, Product, Course
- `ContentTranslationService` — Claude Haiku-4-5, lazy translate on queue, hash invalidation
- Command: `translations:process --batch=20` (every 15 min via scheduler)
- `SetLocale` middleware + `language-switcher` component + `/lang/{locale}` route

**VPLAY — پخش فیلم حرفه‌ای:**
- `HomepageVideo` model (youtube/mp4/cloudflare, autoplay/loop/muted)
- `HomepageVideoController` + admin CRUD + drag-reorder
- `video-player` Blade component (Alpine.js, custom MP4 controls bar RTL)
- Wired to homepage and `ThemeCanvasRenderer` (`module:hero_video` block)

**FORM-EXT — لیست سفید Excel:**
- Migration: `access_lists`, `access_list_entries`, `access_gates`, `access_verifications`
- `AccessListImportService` (PhpSpreadsheet v5.8, batch 500, auto-detect FA/EN headers)
- `AccessGateController` (public `/gate/{slug}` show+verify, session grant)
- Admin: `AccessListController` (lists CRUD + Excel import + gates CRUD) — customer-site only, NOT saas-platform admin
- Public verify page: standalone RTL minimal form

**IMG — آپلود تصویر هوشمند:**
- `SmartImageUploadService` (15 contexts, WebP GD, AI hook via `LOCAL_AI_ENABLED`)
- `smart-image-picker` Blade component (drag-drop, XHR progress, metadata badges)
- Applied to: products, posts, courses admin forms

---

### TB-4 Customer-site Canvas Theme Renderer — COMPLETE (2026-06-13)

**فایل‌های جدید (customer-site):**
- `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` — extends BlockRenderer، module:* blocks را با داده زنده رندر می‌کند
- `app/Http/Controllers/Front/ThemeCanvasPageController.php` — GET / و GET /page/{slug} با fallback
- `resources/views/layouts/canvas-theme.blade.php` — full-page RTL Vazirmatn بدون admin sidebar
- `routes/web.php` — `home` و `page.show` به ThemeCanvasPageController منتقل شدند

**معماری:**
- `ThemeCanvasRenderer` از `BlockRenderer` ارث می‌برد
- `renderBlock()` override می‌کند تا `module:*` types را قبل از schema validation جداسازی کند
- Module blocks: `module:blog_posts`, `module:shop_product_grid`, `module:courses_grid`, `module:pricing_table`, `module:contact_quick`, `module:faq_dynamic`, `module:support_button` و ده‌ها block دیگر
- Module blocks با داده زنده: Post::published(), Product::active(), Course::active()
- Controller fallback: اگر `active_canvas_theme_data` در Setting خالی باشد → HomeController/PageController
- Page resolution: پشتیبانی از هر دو فرمت pages (indexed و associative)
- Cache: theme data با Cache::remember 300 ثانیه کش می‌شود

**URLs:**
```
GET  /          → ThemeCanvasPageController@index (canvas home → HomeController fallback)
GET  /page/{slug} → ThemeCanvasPageController@show (canvas page → FrontPageController fallback)
```

---

## LAST COMPLETED SPRINT

### TB-5 Canvas Theme Admin Preview — COMPLETE (2026-06-13)

**فایل‌های جدید (customer-site):**
- `resources/views/admin/theme-design/canvas.blade.php` — مدیریت قالب Canvas (پالت رنگ، صفحات، هدر/فوتر blocks، preview link، deactivate)

**فایل‌های تغییریافته (customer-site):**
- `app/Http/Controllers/Admin/ThemeDesignController.php` — `canvasTheme()` + `canvasDeactivate()` + `$canvasActive`/`$canvasName` در index()
- `routes/web.php` — 2 route: `GET admin/theme-design/canvas` + `POST admin/theme-design/canvas/deactivate`
- `resources/views/admin/theme-design/index.blade.php` — بنر وضعیت Canvas در بالای صفحه

**URLs:**
```
GET  /admin/theme-design/canvas             → مدیریت قالب Canvas
POST /admin/theme-design/canvas/deactivate  → غیرفعال‌سازی قالب Canvas
```

---

### TB-3 AI URL Analyzer — COMPLETE (2026-06-13)

**ThemeAiAnalyzerService:**
- `analyzeUrl(url, notes)` → fetches URL, DOMXPath extracts (title/h1/h2/nav/footer/og/colors) → Claude `claude-sonnet-4-6` → parsed theme JSON
- `createFromJson(json, name)` → `Theme::create()` with full blocks structure

**ThemeAiController:** form / analyze / analyzeAjax / confirm / generate / generatePrepare

**UI:** 3-step Alpine.js (form → progress bar → block preview + color swatch → ذخیره و باز کردن canvas)

**Env:** `ANTHROPIC_API_KEY` / `services.anthropic.key`

---

### TB-2 Theme Marketplace — COMPLETE (2026-06-13)

**New Tables:** `theme_marketplace`, `theme_licenses`, `theme_purchase_orders`  
**Platform:** `ThemeMarketplaceAdminController` (manage listings, grant/revoke licenses), `ThemeMarketplaceApiController` (tenant API), `ThemeMarketplacePaymentController` (Zarinpal/IDPay callback → grant license)  
**Customer-site:** `PlatformApiService` + marketplace methods (tenantGet/tenantPost with license_key Bearer), `ThemeMarketplaceController`, 2 views (index + my-licenses)  
**Purchase Flow:** customer-site API call → platform creates ThemePurchaseOrder → payment gateway → callback → ThemeLicense::grant() → redirect back → install  
**Routes:** 12 total (6 admin, 5 API, 1 public callback)  
**Migrations:** ✅ All 3 applied

**URLs (platform admin):**
```
GET  /admin/theme-marketplace              → مدیریت فروشگاه
PUT  /admin/theme-marketplace/{id}/listing → به‌روزرسانی قیمت/اطلاعات
GET  /admin/theme-marketplace/licenses     → مدیریت لایسنس‌ها
GET  /theme-marketplace/callback           → callback درگاه (public)
```

**URLs (customer-site admin):**
```
GET  /admin/theme-marketplace              → مرور فروشگاه
POST /admin/theme-marketplace/{id}/purchase → شروع خرید
GET  /admin/theme-marketplace/my-licenses  → قالب‌های من
POST /admin/theme-marketplace/{id}/install → نصب قالب
```

---

### Public Learning Portal (customer-site) — COMPLETE (2026-06-11)

**CourseClassCompletion:** جدول جدید برای tracking پیشرفت کلاس‌ها  
**Front\CourseController:** index, show, enroll (رایگان)، myLearning، learn، markComplete  
**4 view:** courses/index (grid+filter+search), courses/show (detail+class list+progress), courses/learn (sidebar+AJAX complete), courses/my-learning (progress per course)  
**Routes:** 13 route زیر `module:courses` middleware  
**Env:** `MODULE_COURSES_ENABLED=true` در production  
**Tests:** 423/423 pass

**URLs:**
```
GET  /courses                              → لیست دوره‌ها
GET  /courses/{slug}                       → جزئیات دوره
POST /courses/{slug}/enroll               → ثبت‌نام (auth)
GET  /my-learning                          → داشبورد دانشجو
GET  /courses/{slug}/learn/{classOrder?}  → محیط یادگیری
POST /courses/{slug}/learn/{classId}/complete → تکمیل جلسه (AJAX)
```

---

### Infrastructure Hardening Batch — COMPLETE (2026-06-11)

**INFRA-3:** InstallController path-traversal guard + atomic `.env` write + LocalAI `LOCAL_AI_ENABLED` gate  
**INFRA-4:** `BackupDatabaseCommand` (`db:backup --retention=N`) + scheduled at 02:00 + Slack on failure  
**INFRA-5:** `sms_logs` table + `SmsLog` model + `InfraSmsController` + dashboard view + route  
**8.3d:** `SlackAlertService` singleton (`critical/high/warning/info/deploymentEvent`) + `slack:test` command + wired to backup failure + SMS dual-failure  
**scheduler:** Education AI commands (generate-content, generate-gap-content, refresh-recommendations) added to Kernel.php

**Key new files:**
- `app/Console/Commands/BackupDatabaseCommand.php`
- `app/Console/Commands/TestSlackAlertCommand.php`
- `app/Services/Infrastructure/SlackAlertService.php`
- `app/Models/SmsLog.php`
- `app/Http/Controllers/Admin/Infrastructure/InfraSmsController.php`
- `resources/views/admin/infrastructure/sms/index.blade.php`
- `database/migrations/2026_06_10_000001_create_sms_logs_table.php`

**Env vars required:**
- `SLACK_ALERT_WEBHOOK=https://hooks.slack.com/...`
- `SLACK_ALERT_CHANNEL=#alerts`
- `LOCAL_AI_ENABLED=false` (default)

---

### CACHE-1: Redis Cache Architecture — COMPLETE (2026-06-08)

Named stores, isolated flush, CacheManager service. Full implementation.

**Delivered:**
- `CacheManager.php` — `key()`, `store()`, `remember/forget/put/get/flushStore`
- 6 named Redis stores on separate DBs (DB3–DB8): users, customers, themes, tickets, modules, rate_limit
- Eliminated all `Cache::flush()` — replaced with per-store flush
- 7 services/repositories refactored to use named stores
- 15/15 tests pass

**Redis DB Map:** DB0=queues, DB1=legacy, DB2=sessions, DB3=rate_limit, DB4=users, DB5=customers, DB6=themes, DB7=tickets, DB8=modules

---

### EDU-17: Live AI Provider Integration — CERTIFIED COMPLETE (2026-06-07)

All 5 tasks delivered. AI provider layer fully operational.

**Commits:** `da0cbbc` (Phase 2/3) · `18e218c` (Phase 4/5)  
**Tests:** 57 passed / 0 failed / 77 assertions  
**Validation score:** 100/100

**Delivered:**
- `ClaudeAiContentProvider` — real Claude API HTTP adapter
- `CircuitBreakerAiContentProvider` — CLOSED/OPEN/HALF_OPEN state machine
- `PersianEducationPromptBuilder` — Persian-language prompt construction
- `TenantAiQuotaManager` integration — quota check before circuit, key: `edu_site_{siteId}`
- `EDUCATION_AI_PROVIDER` env drives provider selection in `EducationServiceProvider`

**Technical Debt M-01 (LOW):** `education:validate` does not yet check EDU-17-specific components. Future maintenance pass only — EDU-17 is CLOSED.

---

## CURRENT MODULE

**Education Module** at `packages/saas-platform/modules/education/`  
Namespace: `SaaSPlatform\Modules\Education`

**Service layer (EDU-14A/14B/15):**
```
src/Services/Education/
├── UserLearningProfileService.php       — learning profile (cached 15min)
├── KnowledgeGapService.php              — gap scoring (cache: edu_gap.{userId}.{siteId})
├── AIRecommendationScoreService.php     — scores existing recommendations
├── ContentReadinessService.php          — module content health (0-100)
├── RecommendationRefreshService.php     — expires stale, regenerates
├── KnowledgeGapQueueService.php         — in-memory gap queue for generation
├── ContentDeficitQueueService.php       — in-memory deficit queue for generation
├── EducationAnalyticsService.php        — combined analytics snapshot
├── EducationAiContentGenerationService.php — stub (no live AI until env set)
├── EducationDraftPersistenceService.php — persist drafts with dedup
└── GeneratedContentValidator.php        — validates generated content DTOs
```

**Draft generators (no DB writes — return DTOs only):**
```
src/Generation/
├── ArticleDraftGenerator.php
├── WalkthroughDraftGenerator.php
└── LearningPathDraftGenerator.php
```

**API endpoints active:**
```
GET  /api/v1/education/articles              — mobile articles list
GET  /api/v1/education/articles/{id}         — single article
GET  /api/v1/education/search                — full-text search
GET  /api/v1/education/learning-paths        — learning paths
POST /api/v1/education/articles/{id}/progress — mark progress
GET  /api/v1/education/profile               — learning profile + gaps + AI-scored recs (EDU-14A)
GET  /api/v1/education/analytics             — combined snapshot (EDU-14B)
GET  /api/v1/education/generated-content     — draft overview, admin only (EDU-15/S-02)
GET|POST /api/v1/education/sync/{siteId}/... — customer site sync (EDU-12)
```

---

## RECENT DECISIONS

1. **AI generators return DTOs only** — no DB writes inside generators (ADR-001)
2. **Persistence = command responsibility** — commands call `EducationDraftPersistenceService::persist()` (ADR-001)
3. **Two-layer dedup in persist()** — `lockForUpdate` + `QueryException(1062)` catch (ADR-005)
4. **whereNull for null siteId** — never `where('site_id', null)` in MySQL (ADR-004)
5. **x-bind:attr on Blade components** — never `:attr="alpineVar"` (ADR-008)
6. **Education AI is stub until EDU-16** — `EDUCATION_AI_PROVIDER` env gates live calls (ADR-011)

---

## RECENT FINDINGS (ALL RESOLVED)

| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| F-01 | HIGH | GenerateContentCommand set module_slug='unknown' | FIXED |
| T-01 | CRITICAL | Security test false positives (withTenantSite() fix) | FIXED |
| F-02 | MEDIUM | logFailure() not implemented in interface | FIXED |
| RC-01 | MEDIUM | No dedup index on edu_articles | FIXED (migration added) |
| S-02 | MEDIUM | /generated-content had no policy | FIXED (viewGeneratedContent policy) |

**Open findings:** See AUDIT_HISTORY.md — OPF-001 through OPF-004.

---

## OPEN TASKS

All major epics are complete. Next sprint options:

### TB3 Sprint 2 (Theme Builder V3)
- [ ] TB3-04: Block duplication (right-click or clone button)
- [ ] TB3-05: Nested columns / grid layouts (2-col, 3-col, sidebar+content)
- [ ] TB3-06: Per-block style editor (background, padding, border, shadow via right panel)
- [ ] TB3-07: Undo/redo history (JS state array)
- [ ] TB3-08: Block locking / hide in mobile

### Low Priority Backlog
- M2.1-e: AppImage packaging (Linux desktop)
- M2.1-f: System tray icon assets
- M-01: `education:validate` EDU-17 component checks (LOW debt)

---

## NEXT ACTION

**All production safety findings resolved.** Platform is production-ready.

Next: TB3 Sprint 2 or user-directed feature.

Verify production health:
```bash
ssh -p 9011 root@87.107.12.81 "cd /var/www/saas-platform && php artisan monitor:platform"
```

---

## KEY RULES (Never Violate)

1. **No `App\` imports in packages** — use `config('module.key')` pattern
2. **Every content table has site_id** — structural tenant isolation
3. **whereNull for null siteId** — never `where('site_id', null)` in MySQL
4. **Generators return DTOs only** — no DB writes inside generation services
5. **x-bind:attr on Blade components** — never `:attr="alpineVar"` (crashes PHP)
6. **Persian UI everywhere** — RTL, Vazirmatn font, Persian numerals
7. **All views: professional, beautiful** — cards, icons, proper spacing (platform law)
8. **Cross-browser: Chrome/Firefox/Safari/Edge** — flex+margin fallbacks, standard colors
9. **Firebase is optional** — Reverb + Redis is primary. System works without Firebase
10. **Education AI is stub until EDU-16 + env** — no live LLM calls before review workflow exists

---

## PLATFORM MATURITY SCORES

| Area | Score | Notes |
|------|-------|-------|
| Security | 96/100 | Phase 8.0 |
| Plugin Governance | 98/100 | Phase 8.6 |
| Observability | 82/100 | Needs Redis + Slack + OTel |
| Runtime Governance | 98/100 | Phase 8.4 |
| Scalability | 90/100 | Phase 8.5 |
| Frontend Authority | 100/100 | Phase 10.1 |
| Forms Module | 97/100 | Phase 9.x |
| AI Provider Integration | 96/100 | Phase 9.5-A |
| Mobile Client | TBD | Phase M2.0 |
| Education Module | ~93/100 | Post EDU-15.2 |

---

## REFERENCE MAP

| What | Where |
|------|-------|
| All project phases | `docs/` — one folder per phase |
| Education module | `packages/saas-platform/modules/education/` |
| Design tokens | `packages/saas-platform/core-utils/resources/css/design-tokens.css` |
| Blade components | `packages/saas-platform/core-utils/resources/views/components/` |
| Admin views | `resources/views/admin/` |
| Mobile app | `mobile/lib/` |
| Customer site | `c:\laragon\www\customer-site` |
| AI config | `config/ai.php` |
| Session memory | `C:\Users\Farshad-IT\.claude\projects\c--laragon-www-saas-platform\memory\` |
| Project memory | `docs/project-memory/` ← YOU ARE HERE |
