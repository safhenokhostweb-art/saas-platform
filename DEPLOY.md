# Deployment Guide — saas-platform

> **Legacy notice:** `deploy.ps1`, `deploy.sh`, and `deploy.bat` are blocked and must not be used for production. Production authority is transitioning to `deployment-system`; releases must originate from an explicit committed revision and its validated artifact.

Run `php scripts/release-compatibility.php` before handing a clean source revision to `deployment-system`. Its `--require-vendor` mode is for validating a built artifact.

> Production-ready deployment scripts and rollback procedure for the **saas-platform** (main admin panel) repository.

## 1. What gets deployed

The scripts execute these steps **in order** and stop immediately on any failure:

1. Create a timestamped rollback tag (`deploy-backup-YYYYMMDD-HHMMSS`) pointing at the current `HEAD` — your safety anchor.
2. `git fetch origin`
3. `git checkout main`
4. `git pull --ff-only origin main` (fast-forward only — refuses to clobber local commits)
5. `composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist`
6. `php artisan migrate --force` (runs only **new** migrations; never resets data)
7. `php artisan optimize:clear`
8. `php artisan optimize` (config + route + view caches)
9. *(Optional)* If `package-lock.json` exists **and** `node` + `npm` are available: `npm ci && npm run build`
10. Prints a reminder to restart queue workers when applicable.

The scripts **never** call `git reset`, `git clean`, `php artisan db:wipe`, or `migrate:fresh`. Customer entitlements stored in the database are preserved.

## 2. Exact commands to run

### Linux / macOS

```bash
cd /path/to/saas-platform
chmod +x deploy.sh           # first time only
./deploy.sh
```

### Windows Server (cmd.exe or PowerShell)

```bat
cd C:\path\to\saas-platform
deploy.bat
```

On PowerShell, run `.\deploy.bat` (the leading `.\` is required).

## 3. Sample output

```
===============================================================
 saas-platform deployment
 timestamp: 20260511-201500
===============================================================
current HEAD before deploy: 309ad1a (309ad1afbd62215f02680172179b0be1f43c2017)

[..] Step 1: Create rollback tag deploy-backup-20260511-201500
[OK] Step 1: rollback tag created

[..] Step 2: git fetch origin
[OK] Step 2: git fetch origin

[..] Step 3: git checkout main
[OK] Step 3: git checkout main

... (steps 4–8) ...

===============================================================
[OK] saas-platform deploy completed successfully
 before: 309ad1a
 after:  abc1234
 backup tag (rollback): deploy-backup-20260511-201500
===============================================================
```

## 4. Rollback procedure

Every run creates a tag named `deploy-backup-YYYYMMDD-HHMMSS` pinned to the commit that was live just before the deployment. Find the tag with:

```bash
git tag --list 'deploy-backup-*'
```

### 4.a. Code-only rollback (fast)

If only application code is the problem (no migration introduced new schema):

```bash
git checkout main
git reset --hard deploy-backup-YYYYMMDD-HHMMSS
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
php artisan optimize
```

### 4.b. Rollback including migrations (advanced)

If the failed deploy ran new migrations and you must back them out:

```bash
# 1) Inspect what was added since the backup tag
git log deploy-backup-YYYYMMDD-HHMMSS..HEAD -- database/migrations

# 2) For each new migration listed above, run:
php artisan migrate:rollback --step=1
# (repeat once per migration; review the rolled-back set after each step)

# 3) Then reset code
git checkout main
git reset --hard deploy-backup-YYYYMMDD-HHMMSS
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
php artisan optimize
```

> Important: `migrate:rollback` may not be reversible for `DROP`/`ALTER` migrations. If unsure, restore the database from your last logical backup **before** running rollback.

### 4.c. Removing a bad rollback tag (housekeeping)

```bash
git tag -d deploy-backup-YYYYMMDD-HHMMSS         # local
git push origin :refs/tags/deploy-backup-YYY...  # remote (only if pushed)
```

By default the scripts **do not push** rollback tags. They stay local on the production server.

## 5. Recommended deployment workflow

```
   Cursor (local dev)
        │
        ▼
   Git Commit (feature branch + PR)
        │
        ▼
   GitHub (review, CI, merge into main)
        │
        ▼
   Staging server  ──►  ./deploy.sh  (smoke-test with real data clone)
        │
        ▼
   Production server  ──►  ./deploy.sh  (during maintenance window)
        │
        ▼
   Verify    │
            ├── /admin/dashboard reachable
            ├── /api/updates/check returns expected payload for a known site
            ├── activity_logs has entries from the smoke-test admin action
            └── No 5xx in tail of storage/logs/laravel.log
```

If staging deploy fails: investigate before touching production. Tag a fix, redeploy on staging, **then** production.

## 6. Compatibility

- **Laravel:** 10, 11, 12 (the scripts use only `migrate --force`, `optimize:clear`, `optimize`, all stable across these versions).
- **PHP:** 8.2+ (this repo currently uses PHP 8.4 for local development).
- **OS:** Linux (bash 4+) and Windows Server (cmd.exe / PowerShell).
- **Frontend:** Optional Vite build via `npm run build`. Skipped automatically if Node is not installed.
- **Queues:** If you run `php artisan queue:work` under Supervisor or as a Windows service, restart workers after each deploy so they reload the new code:
  ```bash
  php artisan queue:restart            # signals workers to exit after current job
  sudo supervisorctl restart all       # only if Supervisor manages them
  ```

## 7. Safety guarantees (what the scripts will NEVER do)

The scripts honour the platform's global safety rules (`.cursor/rules/global-rules.mdc`):

- **No data destruction:** no `db:wipe`, no `migrate:fresh`, no `migrate:rollback`, no `git reset` to an older state, no `git clean`.
- **No automatic revocation of customer entitlements:** migrations only add capabilities. Customer purchases and module activations in `customer_module_entitlements` / `site_modules` are preserved.
- **No force-push or destructive remote operation:** the only push the scripts perform is none — they only `fetch` and `pull --ff-only`. If `pull --ff-only` cannot fast-forward, the deploy aborts and asks the operator to investigate.
- **No silent failure:** every step prints `[OK]` on success or `[FAIL]` and exits non-zero on failure.

## 8. First-time setup on a new server

1. Clone the repo:
   ```bash
   git clone https://github.com/safhenokhostweb-art/saas-platform.git
   cd saas-platform
   ```
2. Copy the production `.env` (out of band — never committed).
3. Install once:
   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
   php artisan key:generate --force            # only if APP_KEY is empty
   php artisan storage:link
   php artisan migrate --force
   php artisan optimize
   ```
4. Configure the webserver (Apache/Nginx) to serve the `public/` directory.
5. Subsequent deploys: `./deploy.sh` (Linux) or `deploy.bat` (Windows).

> **APP_KEY rotation is prohibited in production** unless you also re-encrypt all `licenses.license_key` rows on customer sites and re-hash `platform_licenses.license_key_hash` rows (Phase 5 / Wave 2 contract). See `docs/CHANGELOG.md` Wave 2 entries for the migration plan.

## 9. Wave-tip tags (permanent names for development milestones)

In addition to automatic `deploy-backup-*` tags created by the deploy scripts, the repository carries **lightweight tags** that name the tip of each Phase 5 wave. These let you refer to important historical points without remembering raw SHAs.

| Tag | Commit | What it captures |
|---|---|---|
| `phase5-wave1-tip` | `03092ae` | End of Wave 1: security hardening, credential removal, P0-3, P0-7 |
| `phase5-wave2-tip` | `a8d32cd` | End of Wave 2: P1-4 (password hashed cast), P0-5 (HMAC license hashing) |
| `phase5-wave3-tip` | `95e4c61` | End of Wave 3: P0-6 (module-history API), P1-3 (ModuleController audit logging) |

These are **local-only** by design (not pushed to GitHub, to keep the remote tag list clean). They are stable, never advance, and survive deletion of the original `phase5-waveN` feature branches.

### Common operations

```bash
# List every wave-tip tag
git tag --list 'phase5-wave*-tip'

# Inspect repository state at the end of Wave 2 (read-only)
git checkout phase5-wave2-tip
# ... explore files ...
git checkout main

# Compare two waves
git diff phase5-wave1-tip phase5-wave2-tip --stat

# List commits introduced by Wave 3
git log phase5-wave2-tip..phase5-wave3-tip --oneline

# Read a file as it was at a milestone
git show phase5-wave1-tip:app/Models/User.php

# Branch off a milestone for an emergency hotfix
git checkout -b hotfix-wave2 phase5-wave2-tip
```

### Future waves

After completing and merging each new wave, create the matching tag before deleting the feature branch:

```bash
# Replace N with the wave number, HASH with the tip commit (e.g. git rev-parse phase5-wave4)
git tag phase5-waveN-tip <HASH>
```

If you forgot to tag before merging, the wave tip is still recoverable from the merge commit's second parent: `git rev-parse <merge-commit>^2`.

### Publishing to GitHub (optional)

If you ever want the wave-tip tags on GitHub, push them explicitly (never `--tags` blindly — that would also push transient `deploy-backup-*` tags):

```bash
git push origin phase5-wave1-tip phase5-wave2-tip phase5-wave3-tip
```

For full operator details and Persian guidance, see `DEPLOY-fa.md` §9.

## 10. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `pull --ff-only` fails | Local commits on production server | Inspect with `git log --oneline origin/main..HEAD`; do not rebase blindly. Stash or revert local edits, then redeploy. |
| `composer install` fails on a host with no network | Composer cannot reach packagist | Pre-populate `~/.composer/cache/` or use `composer install --no-scripts --no-plugins` offline. |
| `migrate --force` complains about missing table | First-ever deploy on this host | Run the first-time setup in §8 instead. |
| Frontend assets stale | Browser cache | Hard-refresh; Vite output filenames are content-hashed so this should be rare. |
| Queue jobs run old code | Workers not restarted | `php artisan queue:restart` (and `supervisorctl restart all` if applicable). |

---

**Phase 5 / Wave 3 reference state:** `main` at `309ad1a` (saas-platform). Tags: `pre-phase5-baseline`, `phase5-wave1-tip` → `03092ae`, `phase5-wave2-tip` → `a8d32cd`, `phase5-wave3-tip` → `95e4c61`.
