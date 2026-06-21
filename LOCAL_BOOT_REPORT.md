# LOCAL BOOT REPORT
Date: 2026-06-21

## Environment Status
| Component | Status | Detail |
|-----------|--------|--------|
| PHP | ✅ OK | 8.4.16 at C:\laragon\bin\php\php-8.4.16-nts-Win32-vs17-x64 (not in PATH) |
| Apache | ✅ OK | Listening on :80 and :443 |
| MySQL | ✅ OK | Listening on :3306 |
| Redis | ✅ OK | Listening on :6379, active connections |
| Composer | N/A | Not tested (PHP not in PATH, but projects have vendor/) |

## Project Boot Status

### saas-platform
- **HTTP**: ✅ 200 OK
- **Artisan**: ✅ Laravel 10.50.2 boots cleanly
- **Migrations**: All up to date
- **Action**: None required

### customer-site
- **HTTP**: ✅ 200 OK (after fix)
- **Artisan**: ✅ Laravel 12.62.0 boots cleanly
- **Migrations**: ⚠️ 12 pending migrations were found and run
- **Root Error**: `Table 'customer_site.homepage_videos' doesn't exist` — caused by missing migrations
- **Fix Applied**: `php artisan migrate --force` — all 12 migrations executed successfully

### tb-ai-studio
- **HTTP**: ❌ DNS resolution failure
- **Apache vhost**: ✅ Config exists at `auto.tb-ai-studio.test.conf`
- **Root Error**: `tb-ai-studio.test` missing from `C:\Windows\System32\drivers\etc\hosts`
- **Fix Required**: Manual — requires admin to add hosts entry (see LOCAL_FIX_REPORT.md)
