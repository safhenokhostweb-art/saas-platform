# Infrastructure Readiness Report
> Generated: 2026-06-08 | Platform: SaaS Platform Laravel 10 | Environment: Local (Laragon)

---

## Executive Summary

The platform is **NOT production-ready** as-is for remote server deployment, but the architecture is fundamentally sound. The blockers are configuration anti-patterns (hardcoded values, non-abstracted file I/O) that require targeted remediation — not architectural rewrites. Estimated remediation effort: 2–3 days for HIGH/CRITICAL items.

---

## 1. Hardcoded Domain & URL Findings

| ID | Severity | File | Line | Issue | Status |
|----|----------|------|------|-------|--------|
| F1.1 | HIGH | `config/app.php` | 58 | `'http://localhost'` fallback for APP_URL | Open |
| F1.2 | CRITICAL | `.env` | 5–7 | Three APP_URL entries; last one wins (`saas-platform.test`) | Open — fix before deploy |
| F1.3 | HIGH | `config/cors.php` | 21–22 | Hardcoded `customer-site.test` blocked CORS in production | **FIXED** — now env-driven (`CORS_ALLOWED_ORIGINS`) |
| F1.4 | MEDIUM | `config/sanctum.php` | 18–22 | Localhost/127.0.0.1 in fallback stateful domains | Open |
| F1.5 | HIGH | `config/queue.php` | 58 | SQS prefix placeholder `your-account-id` | Open |

**Fixed this session:** F1.3

---

## 2. Storage Path & File I/O Findings

| ID | Severity | File | Lines | Issue |
|----|----------|------|-------|-------|
| F2.1 | HIGH | `app/Helpers/PlatformSettings.php` | 435–440 | `mkdir()` + `$file->move()` instead of `Storage::` facade |
| F2.2 | HIGH | `app/Http/Controllers/InstallController.php` | 26–35 | `file_get_contents` / `file_put_contents` on `.env` |
| F2.3 | MEDIUM | `app/Services/CustomerService.php` | 181–187 | `fopen()` with hardcoded path `app/exports/` |
| F2.4 | MEDIUM | `app/Http/Controllers/Admin/UserController.php` | 230–235 | Duplicate CSV fopen pattern |
| F2.5 | MEDIUM | `app/Http/Controllers/Admin/UserController.php` | 275–284 | Import: stores via Storage then reads raw `storage_path()` |
| F2.6 | MEDIUM | `app/Services/UserService.php` | 789–795 | Duplicate CSV export pattern |
| F2.7 | MEDIUM | `app/Services/SecurityValidationService.php` | 60 | Hardcoded `storage_path('app/public/ticket-attachments')` |
| F2.8 | MEDIUM | `app/Http/Controllers/Admin/AppReleaseController.php` | 64–65 | Raw `mkdir()` call |
| F2.9 | MEDIUM | `app/Http/Controllers/Api/Mobile/MobileTicketController.php` | 218 | Inline `mkdir()` |

**Required remediation pattern:**
```php
// Before
$path = storage_path("app/exports/{$filename}");
mkdir(dirname($path), 0755, true);
$handle = fopen($path, 'w');

// After
$stream = fopen('php://temp', 'w');
// ... write to $stream ...
Storage::disk(config('filesystems.default'))->put("exports/{$filename}", stream_get_contents($stream));
```

---

## 3. Storage Driver Assumptions

| ID | Severity | Issue |
|----|----------|-------|
| F3.1 | MEDIUM | 9+ locations hardcode `Storage::disk('public')` — breaks with S3/MinIO |
| F3.2 | MEDIUM | 2 locations hardcode `Storage::disk('local')` for exports |
| F3.3 | LOW | `'private'` disk is well-defined in `config/filesystems.php` — OK |

**Recommended fix:** Add `UPLOAD_DISK` and `EXPORT_DISK` env vars to `config/filesystems.php`:
```php
'upload_disk'  => env('UPLOAD_DISK', 'public'),
'export_disk'  => env('EXPORT_DISK', 'local'),
```
Replace all `Storage::disk('public')` in upload contexts with `Storage::disk(config('filesystems.upload_disk'))`.

---

## 4. Database Connection Findings

| ID | Severity | File | Issue | Risk |
|----|----------|------|-------|------|
| F4.1 | CRITICAL | `config/database.php` | MySQL/PgSQL/MSSQL default to `127.0.0.1` / `localhost` | Silent failure in remote DB scenario |
| F4.2 | CRITICAL | `config/database.php` | Redis defaults to `127.0.0.1` | Cache/queue break without explicit config |
| F4.3 | MEDIUM | `config/cache.php` | Memcached defaults to `127.0.0.1` | Low risk (Memcached is not primary driver) |
| F4.4 | MEDIUM | `config/queue.php` | Beanstalkd host hardcoded `localhost` | Low risk (not primary queue driver) |

**Assessment:** Default fallbacks in Laravel config files are a framework convention, not a bug. In production, these **must** be explicitly set in `.env`. The risk is operational (missing env var causes silent connection to localhost, which fails or uses wrong DB).

**Required action:** Production `.env` checklist must enforce all DB/cache/queue env vars.

---

## 5. AI Provider Configuration

| ID | Severity | File | Issue |
|----|----------|------|-------|
| F6.1 | MEDIUM | `app/Services/AI/LocalAIClient.php` | Falls back to `http://127.0.0.1:11434` if `services.local_ai.endpoint` unset |

**Note:** Claude and OpenAI providers in `packages/saas-platform/core-utils/config/ai.php` correctly use env vars with appropriate production defaults.

---

## 6. SMS Infrastructure

**Before this session:** Single provider (Melipayamak), no failover, no health tracking.

**After this session:**
- `KavenegarGateway` and `GhasedakGateway` implemented
- `FailoverSmsGateway` wraps primary + secondary with automatic fallback
- Provider health tracked in cache (`sms_health_primary`, `sms_health_secondary`)
- Config: `SMS_FAILOVER_PROVIDER=kavenegar` activates failover

**Remaining:** Delivery statistics dashboard (Phase 2 — see SMS roadmap below).

---

## 7. Multi-Tenant Domain Resolution

**Assessment: GOOD** — Domain-to-tenant mapping is fully database-driven via `ResellerDetectionService`. No hardcoded tenant domains in code. The `sites` table stores `custom_domain` and `slug` columns. Tenant resolution survives any domain change that's updated in the database.

**Gap:** No domain health check endpoint to verify that `custom_domain` actually resolves to this server's IP.

---

## 8. Current Infrastructure Module Gaps

Items logged in the new Infrastructure Management module for future tracking:
- No servers registered yet (empty state)
- No domains tracked yet (the `infra_domains` table is separate from `sites.custom_domain` — the latter is the live routing table; `infra_domains` is the inventory/monitoring layer)
- No backup schedule verified

---

## Overall Readiness Score

| Scenario | Readiness | Blockers |
|----------|-----------|---------|
| Scenario A (Single VPS) | **85/100** | CORS fixed, storage paths need cleanup |
| Scenario B (App + DB separated) | **70/100** | Must set DB_HOST explicitly, remove localhost defaults |
| Scenario C (App + DB + S3) | **60/100** | Storage facade migration required (F2.x, F3.x) |
| Scenario D (Multiple tenant servers) | **75/100** | Already DB-driven; needs domain health checks |
| Scenario E (Central admin + remote tenants) | **Architecture designed** | Remote agent layer not yet implemented |

---

## Priority Remediation Order

### Phase INFRA-1 (Before any production deployment) — ~1 day
1. Fix `.env` — remove duplicate `APP_URL` entries, set to actual production URL
2. Set `DB_HOST`, `REDIS_HOST`, `REDIS_PASSWORD` explicitly in production `.env`
3. Set `CORS_ALLOWED_ORIGINS` to actual tenant domains (already code-ready)
4. Run all 5 infrastructure migrations: `php artisan migrate`

### Phase INFRA-2 (Before S3/MinIO storage) — ~1 day
5. Migrate `PlatformSettings.php` (F2.1) to `Storage::disk()` — highest impact upload path
6. Add `UPLOAD_DISK` and `EXPORT_DISK` to `config/filesystems.php`
7. Replace `Storage::disk('public')` in upload contexts with config-driven disk
8. Fix CSV export services (F2.3, F2.4, F2.6) — use Storage streams

### Phase INFRA-3 (Hardening) — ~1 day
9. Fix `InstallController` (F2.2) — use `Artisan::call('config:set')` or write env via a safe wrapper
10. Fix `LocalAIClient` fallback endpoint (F6.1) — throw exception if not configured
11. Document `Sanctum stateful domains` — require `SANCTUM_STATEFUL_DOMAINS` in production env
12. Add SMS failover provider to production `.env`: `SMS_FAILOVER_PROVIDER=kavenegar`
