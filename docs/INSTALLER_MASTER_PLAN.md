# INSTALLER MASTER PLAN
> Date: 2026-06-25
> Status: DESIGN PHASE — DO NOT IMPLEMENT YET
> Purpose: Full deployment engine for SaaS Platform + Customer Site

---

## Overview

A web-based deployment and restore wizard that handles:
- Fresh server installation for saas-platform
- Fresh server installation for customer-site
- New tenant/customer site creation
- Backup restore from ArvanCloud
- Full server migration

This document is a design specification only. Implementation begins after explicit user approval.

---

## Architecture Decision

**Hybrid approach: Web Installer UI + PHP CLI Engine**

- **Frontend:** Single-page PHP installer wizard (no framework dependency, self-contained)
- **Backend engine:** PHP CLI class library (`InstallerEngine`)
- **Entry point:** `installer/index.php` — standalone, works before Laravel is installed
- **Commands:** Laravel Artisan commands that delegate to `InstallerEngine` after Laravel is bootstrapped
- **No external dependencies** beyond PHP 8.1+, curl, zip, and standard Linux tools

Rationale: The installer must run when Laravel is NOT yet installed. A standalone PHP installer
can bootstrap the environment, pull the codebase, configure `.env`, and then hand off to
Artisan for migrations and seeding. This is the same pattern used by Craft CMS, Statamic, and
Sylius installers.

---

## Phase 1 — Platform Installer (saas-platform)

**Goal:** Install saas-platform on a fresh Ubuntu 22.04 server via a web wizard.

### Entry Point
```
https://new-server.example.com/install/
→ installer/index.php (self-contained PHP file, no framework)
```

### Wizard Steps

#### Step 1: Welcome + System Requirements Check
```
PHP version check     (>= 8.2)
PHP extensions check  (mysql, redis, gd, curl, zip, mbstring, xml, bcmath)
MySQL connectivity    (test connection with provided credentials)
Redis connectivity    (test ping)
Disk space check      (>= 5 GB free)
Write permission      (/var/www/saas-platform)
Nginx status          (systemctl is-active nginx)
Supervisor status     (systemctl is-active supervisor)
```

**Display:** Green/Red checklist. Cannot proceed until all items pass.

#### Step 2: Database Configuration
```
Form fields:
- MySQL host (default: 127.0.0.1)
- MySQL port (default: 3306)
- MySQL username
- MySQL password
- Database name (default: saas_platform)

Action: Test connection → CREATE DATABASE IF NOT EXISTS → show success
```

#### Step 3: Application Configuration
```
Form fields:
- APP_URL (e.g., https://panel.safhenokhost.ir)
- APP_KEY (auto-generated via random_bytes(32))
- Admin email
- Admin password (min 10 chars)
- Redis host/port
- Queue driver (redis / sync)
```

#### Step 4: ArvanCloud Backup Configuration (optional)
```
Form fields:
- ARVAN_ACCESS_KEY
- ARVAN_SECRET_KEY
- ARVAN_ENDPOINT (default: https://s3.ir-thr-at1.arvanstorage.ir)
- ARVAN_BUCKET
- ARVAN_REGION (default: ir-thr-at1)

Action: Test bucket access → show bucket contents preview
```

#### Step 5: Code Installation
```
Actions (streamed output):
1. git clone https://github.com/YOUR_ORG/saas-platform.git /var/www/saas-platform
2. composer install --no-dev --optimize-autoloader
3. Write .env file
4. php artisan key:generate
5. php artisan migrate --force
6. php artisan db:seed --class=PlatformSeeder
7. php artisan config:cache && php artisan route:cache && php artisan view:cache
8. php artisan storage:link
9. chown -R www-data:www-data /var/www/saas-platform/storage
```

#### Step 6: Service Configuration
```
Sub-steps:
A) Nginx config — write vhost file + test + reload
B) Supervisor config — write worker config + reload
C) Cron config — write /etc/cron.d/saas-scheduler
D) SSL check — verify HTTPS endpoint (or note it needs ArvanCloud CDN)
```

#### Step 7: Health Check + Finalize
```
Checks:
- GET /health/ready → expect 200 + ready:true
- Queue worker status (supervisorctl status)
- Scheduler test (php artisan schedule:run --ping)
- Backup test (php artisan db:backup --dry-run)

Final: Display admin URL, admin credentials, delete installer
```

### Files
```
installer/
  index.php              ← single entry point
  src/
    Installer.php        ← orchestrator
    StepChecker.php      ← requirements check
    EnvWriter.php        ← .env file writer (no BOM)
    ServiceConfigurator.php  ← nginx/supervisor/cron writer
    HealthVerifier.php   ← final validation
  templates/
    layout.html.php
    step1_requirements.html.php
    step2_database.html.php
    step3_config.html.php
    step4_arvan.html.php
    step5_install.html.php
    step6_services.html.php
    step7_health.html.php
  install_lock          ← created on success → blocks re-run
```

### Security
- `install_lock` file prevents re-running after completion
- Installer must be removed or blocked at nginx level after install
- No credentials logged to disk during installation
- All form fields use HTTPS POST (never GET)

---

## Phase 2 — Customer Site Installer (customer-site)

**Goal:** Install customer-site on same or different server after saas-platform is running.

### Additional Prerequisites vs Phase 1
- saas-platform must already be running (API connectivity required)
- `PLATFORM_URL`, `PLATFORM_API_KEY`, `PLATFORM_SITE_ID` needed

### Wizard Steps (delta from Phase 1)

#### Step 1: Platform Connectivity Check
```
Form: Platform URL, Platform API Key
Action: GET {PLATFORM_URL}/api/v1/health → expect success
Display: Platform version, connected sites count
```

#### Step 3 extension: Customer Site Config
```
Additional fields:
- PLATFORM_URL
- PLATFORM_API_KEY (generated from platform admin)
- PLATFORM_SITE_ID
- LICENSE_VERIFY_SSL (true/false — false for self-signed SSL)
- SMS provider credentials (Kavenegar / Ghasedak)
```

#### Step 5 extension: Asset Build
```
Additional actions:
- npm install
- npm run build
```

#### Step 6 extension: Customer Site Nginx
```
Separate vhost for customer domains (wildcard or specific domain)
Test subdomain routing
```

---

## Phase 3 — One-Click Tenant Creation

**Goal:** Create a new customer/tenant site with full provisioning in one automated flow.

### Trigger
Platform admin: `/admin/sites/provision` — form with tenant details.

### Provisioning Steps (all automated, no wizard steps)
```
1. Create Site record in saas_platform DB
   - domain, name, plan_id, contact_mobile

2. Generate license_key (UUID v4)

3. Push initial settings to customer-site DB
   - site settings (name, logo, colors, timezone)
   - default admin user for tenant

4. Configure domain in Nginx (if wildcard not set up)
   - Add server_name entry OR
   - Register subdomain in ArvanCloud DNS via API

5. Run initial data seeding
   - Default product categories
   - Default CRM pipeline stages
   - Default subscription plan assignment

6. Send welcome SMS to tenant admin
   - Mobile number from form
   - Platform URL + login credentials

7. Create first backup run record
   - infra_backup_runs entry (marks tenant as backup-initialized)
```

### Implementation
- `TenantProvisioningService` (new service)
- `ProvisionTenantCommand` (`php artisan tenant:provision --domain= --name= --plan=`)
- Admin wizard at `/admin/sites/provision` (6-step modal)
- Estimated time: 2–5 minutes per tenant

---

## Phase 4 — Backup Restore Wizard

**Goal:** One-click restore for any backup type from ArvanCloud, via web UI.

### Entry Points
```
Platform admin: /admin/disaster-recovery/restore
```

### Wizard Flows

#### Flow A: Restore Tenant Database
```
Step 1: Select tenant (dropdown from sites table)
Step 2: Browse Arvan backups for that tenant
         → list files in customer-sites/tenant-{id}/database/
         → show: filename, size, date, age
Step 3: Select backup file
Step 4: Confirm: "This will overwrite all data for Tenant X"
Step 5: Execute restore
         → Download backup from Arvan to /tmp/
         → Verify gzip integrity
         → Drop + recreate tenant data (WHERE site_id = X)
         → Restore from dump
         → Flush caches
         → Show result
```

#### Flow B: Restore Platform Database
```
Step 1: Show warning: "Full platform restore — affects ALL sites"
Step 2: Browse saas-platform/database/ backups
Step 3: Select backup
Step 4: Type confirmation string ("RESTORE-PLATFORM")
Step 5: Execute
         → Put platform in maintenance mode
         → Download + verify backup
         → Drop + restore saas_platform DB
         → php artisan migrate
         → Bring back online
         → Show result
```

#### Flow C: Restore Storage Files
```
Step 1: Select scope (platform storage / tenant storage)
Step 2: Browse saas-platform/storage/ backups (weekly)
Step 3: Select backup
Step 4: Choose: Overwrite / Merge (merge = add missing files only)
Step 5: Execute
         → Download tar.gz
         → Extract to target directory
         → Fix permissions
         → Show result
```

#### Flow D: Restore Tenant Archive
```
Step 1: Select from saas-platform/archives/ or customer-sites/tenant-X/archives/
Step 2: Preview archive contents (list files in ZIP)
Step 3: Choose restore type:
         a) Full restore (new site from archive)
         b) Partial (DB only / files only)
Step 4: Execute
```

### Implementation Files
```
app/Http/Controllers/Admin/DisasterRecoveryController.php
app/Services/Infrastructure/RestoreService.php
resources/views/admin/disaster-recovery/
  index.blade.php          ← browse available backups
  restore-tenant.blade.php ← Flow A
  restore-platform.blade.php ← Flow B
  restore-storage.blade.php  ← Flow C
  restore-archive.blade.php  ← Flow D
```

---

## Phase 5 — Server Migration Tool

**Goal:** Move the entire platform from one VPS to another with minimal downtime.

### Migration Modes

#### Mode A: Live Migration (recommended, <1 hour downtime)
```
1. [New Server] Run Phase 1 Platform Installer
2. [New Server] Run Phase 2 Customer Site Installer
3. [Old Server] php artisan db:backup (fresh backup)
4. [Old Server] php artisan storage:backup (fresh storage snapshot)
5. [New Server] Restore both DBs from Arvan
6. [New Server] Restore storage from Arvan
7. [New Server] php artisan migrate (apply any pending)
8. Test new server at IP directly (bypass DNS)
9. Update DNS (ArvanCloud CDN) → new IP
10. [Old Server] Keep alive 24h as fallback, then decommission
```

#### Mode B: Full Backup-Based Migration
```
1. Take full backup on old server
2. Upload ALL files to Arvan (db + storage + code snapshot)
3. On new server: download everything from Arvan
4. Restore + configure
5. Switch DNS
```

### Migration Wizard UI
```
Platform admin: /admin/server-migration

Step 1: Migration type (Live / Backup-Based)
Step 2: New server SSH credentials (host, port, key)
Step 3: Pre-migration backup (trigger immediately)
Step 4: Migration execution (streamed progress)
Step 5: Validation (health checks on new server)
Step 6: DNS cutover (with 5-minute countdown + rollback option)
Step 7: Old server decommission confirmation
```

---

## Technical Design Principles

### Installer Engine Core
```
installer/src/InstallerEngine.php

Methods:
- checkRequirements(): array{name, status, message}[]
- testDatabaseConnection(config): bool
- testRedisConnection(config): bool
- testArvanConnection(config): bool
- writeEnvFile(path, values): void
- runShellCommand(cmd): {output, exitCode}
- streamedExec(cmd, callback): int
- verifyHealth(url): bool
- createInstallLock(path): void
- isInstalled(path): bool
```

### Streaming Progress
All long-running steps must use Server-Sent Events (SSE) to stream progress to the browser.
```javascript
// Browser side:
const evtSource = new EventSource('/install/step5/stream');
evtSource.onmessage = (e) => appendToLog(e.data);
```

```php
// Server side:
header('Content-Type: text/event-stream');
header('X-Accel-Buffering: no');
while ($line = fgets($process)) {
    echo "data: " . htmlspecialchars($line) . "\n\n";
    ob_flush(); flush();
}
```

### State Persistence Between Steps
```
installer/state.json (local file, gitignored)
{
  "step": 3,
  "db_config": {...},
  "app_config": {...},
  "arvan_config": {...},
  "completed_steps": [1, 2, 3]
}
```

---

## Timeline Estimate

| Phase | Complexity | Est. Dev Time |
|-------|-----------|---------------|
| Phase 1 — Platform Installer | HIGH | 3–4 days |
| Phase 2 — Customer Installer | MEDIUM | 2 days |
| Phase 3 — Tenant Provisioning | MEDIUM | 2 days |
| Phase 4 — Restore Wizard | HIGH | 3 days |
| Phase 5 — Migration Tool | VERY HIGH | 4–5 days |
| **Total** | | **14–16 days** |

---

## Dependencies Before Implementation

- [ ] All `.env` values for both apps documented (for installer templates)
- [ ] Nginx vhost configs documented (for service configurator)
- [ ] Supervisor configs documented (for service configurator)
- [ ] Admin user seeder exists (`PlatformSeeder`)
- [ ] GitHub repo visibility (public or deploy key configured)

---

*INSTALLER_MASTER_PLAN — generated 2026-06-25 — DESIGN ONLY*
