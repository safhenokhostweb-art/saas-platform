# INSTALLER RECOMMENDATION
> Date: 2026-06-25
> Purpose: Select best deployment engine approach for long-term use

---

## Compared Approaches

### Option 1 — Laravel Installer Wizard

**Description:** A standalone Laravel application (separate repo) that installs saas-platform/customer-site.
Similar to the official Laravel Installer but customized for this platform.

**How it works:**
- User visits `http://new-server.example.com/install/`
- Laravel wizard app is pre-installed on the new server
- Wizard installs the actual platform into `/var/www/`
- After install, wizard self-removes or is blocked

**Pros:**
- Familiar Blade/Tailwind UI (reuses your existing design system)
- Full Laravel feature set (Artisan, jobs, validation)
- Can reuse existing services (SlackAlertService, ArvanBackupService)
- Easy to maintain — same stack as your platform

**Cons:**
- Circular dependency: must install Laravel to run the installer that installs Laravel
- Requires PHP + Composer pre-installed on server before wizard can run
- Two separate repos to maintain
- Heavier (full framework just for installation)

**Verdict:** GOOD but over-engineered for this use case.

---

### Option 2 — Laravel Zero

**Description:** A CLI application built with Laravel Zero — a stripped-down Laravel for command-line tools.

**How it works:**
- Single compiled PHAR file distributed alongside the platform
- Run: `php installer.phar install` on new server
- Wizard runs in terminal with interactive prompts
- Outputs progress to console

**Pros:**
- No browser required — pure CLI
- Single compiled binary — easy to distribute
- Powered by Laravel Zero which is well-maintained
- Can SSH into remote server and run directly

**Cons:**
- Terminal-only: non-technical users need CLI knowledge
- No visual progress bar (only text output)
- Must recompile after each platform change
- Harder for disaster recovery by non-developers

**Verdict:** EXCELLENT for developers, POOR for non-technical operators.

---

### Option 3 — Bash Deployment Engine

**Description:** A comprehensive bash script (`install.sh`) that handles everything.

**How it works:**
```bash
curl -s https://raw.githubusercontent.com/.../install.sh | bash
# Interactive prompts + automated execution
```

**Pros:**
- No dependencies beyond bash + curl
- Can run on any Linux server without PHP pre-installed
- Single file — trivial to audit and update
- Well-understood tool: every Linux admin knows bash

**Cons:**
- Bash becomes unmanageable at scale (no objects, limited error handling)
- Cross-distribution compatibility issues (apt vs yum, nginx config paths vary)
- No browser UI — pure terminal
- Harder to add a restore wizard for non-technical users
- Testing requires a real server (no unit tests)

**Verdict:** GOOD for developers, NOT scalable as platform grows, poor UX for restore operations.

---

### Option 4 — Hybrid Web Installer + PHP CLI Engine (RECOMMENDED)

**Description:** A standalone PHP file (`installer/index.php`) provides a browser-based wizard UI.
The actual execution is handled by a pure PHP class library (no framework). After the platform
is installed, Laravel Artisan commands (`installer:*`) provide CLI equivalents.

**How it works:**
- Drop `installer/` folder on new server (or fetch via `curl`)
- Visit `http://new-server.example.com/installer/` in browser
- Step-by-step wizard with real-time streamed output
- After install completes, installer self-disables (creates `install_lock`)
- Once Laravel is running: `php artisan tenant:provision`, `php artisan restore:tenant` etc.

**Pros:**
- **Zero dependencies before install:** only requires PHP 8.1+ (already needed for the platform anyway)
- **Browser UI:** non-technical staff can run it without SSH knowledge
- **Streamed output:** shows real-time progress (SSE or chunked transfer)
- **Single maintainable repo:** `installer/` lives inside saas-platform repo
- **Dual mode:** Web wizard for initial install, Artisan commands for restore/provisioning
- **Arvan integration built-in:** downloads restore files from Arvan directly in wizard
- **Security:** `install_lock` file prevents re-runs; wizard prompts for removal post-install
- **No circular dependency:** plain PHP bootstraps the environment, THEN Laravel runs

**Cons:**
- Requires one PHP file to be accessible via HTTP on the new server before install
  (mitigation: pre-configure Nginx with a minimal rule pointing to `installer/`)
- Not a distributed binary like Laravel Zero
- Must be kept updated as platform evolves (low effort: installer is independent)

---

## Recommendation

**Use Option 4: Hybrid Web Installer + PHP CLI Engine**

This is the best fit for:
- ✅ Non-technical deployment (web browser, step-by-step)
- ✅ Fast disaster recovery (restore wizard accessible at `/admin/disaster-recovery/restore`)
- ✅ Minimal Claude involvement after build (self-guided wizard with validation)
- ✅ Works before Laravel is installed (standalone PHP)
- ✅ Full Arvan backup integration (restore directly from bucket)
- ✅ Maintainable inside existing repo (no separate project)

---

## Implementation Recommendation by Phase

| Phase | Tool | Why |
|-------|------|-----|
| Phase 1: Platform Install | Standalone PHP wizard (`installer/`) | Works before Laravel exists |
| Phase 2: Customer Install | Same installer wizard (separate config flow) | Reuse Phase 1 engine |
| Phase 3: Tenant Creation | Laravel Artisan command + Admin web UI | Laravel is running, reuse existing UI |
| Phase 4: Restore Wizard | Laravel Admin panel + `RestoreService` | Full ArvanCloud + SSE stream |
| Phase 5: Migration Tool | Laravel Admin panel | Complex orchestration needs Laravel |

---

## Technology Stack for Installer

```
installer/
  index.php            ← entry point (plain PHP, no framework)
  src/
    InstallerEngine.php      ← orchestrator
    RequirementsChecker.php  ← PHP/extensions/disk/ports
    EnvWriter.php            ← .env generation (no BOM)
    ServiceConfigurator.php  ← nginx/supervisor/cron writer
    ArvanClient.php          ← ArvanCloud S3-compatible API (curl, no SDK)
    StreamOutput.php         ← SSE streaming helper
    InstallLock.php          ← lock file management
  templates/
    layout.html.php          ← RTL Vazirmatn Persian UI
    step_*.html.php          ← one file per step
  install_lock              ← created on success
```

**No NPM. No Composer. No framework. 100% plain PHP.**
After install, add Artisan commands to wrap the same engine for CLI use.

---

## Build Estimate

| Component | Hours |
|-----------|-------|
| InstallerEngine core classes | 8h |
| Web UI wizard (8 steps × 1h) | 8h |
| Phase 1 platform install logic | 6h |
| Phase 2 customer install (delta) | 4h |
| Phase 3 tenant provisioning (Artisan) | 6h |
| Phase 4 restore wizard (Laravel admin) | 10h |
| Phase 5 migration tool | 12h |
| Testing + hardening | 8h |
| **Total** | **~62 hours (~8 working days)** |

---

## Recommended Next Step

Build in this order:

1. **Phase 4 first** (Restore Wizard) — highest immediate value for disaster recovery.
   Uses existing Laravel stack. Can be built in 2–3 days.
   Accessible at `/admin/disaster-recovery/restore` after next deploy.

2. **Phase 1 + 2** (Platform + Customer Installer) — for fresh server provisioning.
   Required when adding new customers or migrating servers.
   Build after Phase 4 is tested.

3. **Phase 3** (Tenant Provisioning) — speeds up onboarding new customers.

4. **Phase 5** (Migration Tool) — only needed when changing hosting provider.
   Defer until actually needed.

---

## Summary Recommendation

```
RECOMMENDED APPROACH: Hybrid Web Installer + PHP CLI Engine (Option 4)

Easiest for non-technical deployment:    ✅ YES (browser wizard)
Fastest disaster recovery:               ✅ YES (restore wizard in admin panel)
Minimal Claude involvement after build:  ✅ YES (self-guided + validation built-in)
Estimated time to build full installer:  8 working days
Recommended starting point:             Phase 4 — Restore Wizard (2–3 days)
```

*INSTALLER_RECOMMENDATION — generated 2026-06-25*
