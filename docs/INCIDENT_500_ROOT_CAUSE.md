# INCIDENT — 500 Error Root Cause Report

**Date:** 2026-06-25  
**Severity:** CRITICAL  
**Status:** RESOLVED  

---

## Affected URLs

| URL | Before Fix | After Fix |
|-----|-----------|-----------|
| https://safhenokhost.ir | HTTP 500 | HTTP 200 |
| https://panel.safhenokhost.ir/admin/finance/dashboard | HTTP 500 | HTTP 302 (auth redirect → working) |
| https://panel.safhenokhost.ir/admin/finance/ledger | HTTP 500 | HTTP 302 (auth redirect → working) |

---

## Root Cause 1 — Finance Views (saas-platform)

**Exception:**
```
Illuminate\View\ViewException: View [admin.layouts.admin] not found.
```

**Files:**
- `resources/views/admin/finance/dashboard.blade.php`
- `resources/views/admin/finance/ledger/index.blade.php`
- `resources/views/admin/finance/ledger/create.blade.php`
- `resources/views/admin/finance/ledger/edit.blade.php`

**Cause:** All 4 Finance Ledger views called `@extends('admin.layouts.admin')`. This layout path does not exist. The correct path is `layouts.admin` (at `resources/views/layouts/admin.blade.php`). This was a typo introduced when the Finance Ledger module was created (2026-06-25).

**Fix Applied:**
- Changed `@extends('admin.layouts.admin')` → `@extends('layouts.admin')` in all 4 views.
- Applied via SCP directly to server + `php artisan view:clear`.

---

## Root Cause 2 — Customer Site Homepage (customer-site)

**Exception:**
```
PHP Warning: require(...mtdowling/jmespath.php/src/JmesPath.php): Failed to open stream: Permission denied
PHP Fatal error: Uncaught Error: Failed opening required '...JmesPath.php'
```

**File:** `/var/www/customer-site/vendor/mtdowling/jmespath.php/src/JmesPath.php`

**Cause:** The deploy at 13:13–13:17 UTC+3 on 2026-06-25 installed `league/flysystem-aws-s3-v3` (for ArvanCloud offsite backup). This pulled in `aws/aws-sdk-php` and `mtdowling/jmespath.php` as new dependencies. When extracted on the server, the **vendor directories** `mtdowling/`, `aws/`, `maennchen/`, `markbaker/`, `nikic/`, `phpoffice/` were created without the execute bit (`drw-r--r--` instead of `drwxr-xr-x`). PHP-FPM (running as `www-data`) requires execute permission on directories to traverse them and load files inside.

**Fix Applied:**
```bash
find /var/www/customer-site/vendor/ -type d ! -perm /111 -exec chmod 755 {} \;
```
Then: `php artisan optimize:clear && config:cache && route:cache && view:cache`

**Side effect:** `customer-site-worker` was FATAL (same permission denied at startup). Restarted via `supervisorctl restart customer-site-worker:customer-site-worker_00`.

---

## Prevention

The root cause of the permission issue is that the deploy ZIP extraction on the server creates vendor directories as `644` when new packages are introduced. 

**Recommended fix to deploy script:** Add after extraction:
```bash
find /var/www/customer-site/vendor/ -type d -exec chmod 755 {} \;
```
This is deferred to post-launch roadmap (LAUNCH_FREEZE_ACTIVE).

---

## Timeline

| Time | Event |
|------|-------|
| 13:13–13:17 | Deploy: ARVAN_OFFSITE_BACKUP — new S3 packages installed with wrong dir permissions |
| ~10:10 | Finance Dashboard 500 first logged (views had wrong layout name from creation) |
| 15:43 | Customer site 500 first detected by nginx log |
| 15:50 | Incident investigation begins |
| 15:52 | Root Cause 1 identified (wrong layout name) |
| 15:53 | Root Cause 2 identified (Permission denied on vendor dirs) |
| 15:54 | Finance views patched locally + SCP'd to server |
| 15:54 | Vendor directory permissions fixed (`chmod 755`) |
| 15:55 | Customer site caches rebuilt — homepage 200 confirmed |
| 15:56 | Finance routes 302 confirmed |
| 15:58 | customer-site-worker restarted — RUNNING |

---

## Final Status

- **Homepage:** HTTP 200 ✅  
- **Finance Dashboard:** HTTP 302 ✅  
- **Finance Ledger:** HTTP 302 ✅  
- **All 4 workers:** RUNNING ✅  
- **New errors in logs:** 0 ✅  
