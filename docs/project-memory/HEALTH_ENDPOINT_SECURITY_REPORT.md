# HEALTH ENDPOINT SECURITY REPORT
> Finding: PROD-001 — Information Disclosure via /health/release
> Resolution Date: 2026-06-15
> Status: RESOLVED ✅

---

## Finding Summary

All three health endpoints (`/health/live`, `/health/ready`, `/health/release`) were public with no IP restriction or authentication. The `/health/release` endpoint specifically exposed:

| Field | Value Type | Sensitivity |
|-------|-----------|-------------|
| `deployment_uuid` | Internal UUID | HIGH — identifies deployment unit |
| `git_commit` | Git SHA1 hash | HIGH — enables CVE targeting |
| `platform_version` | Platform version string | HIGH — enables version-specific exploit research |
| `pending_migrations` | Integer count | MEDIUM — reveals schema deployment state |
| `schema_drift` | Boolean | MEDIUM — reveals infrastructure state |

The `/health/live` endpoint additionally exposed `"php": "8.3.6"` (PHP version fingerprinting).

The `/health/ready` endpoint exposed internal error strings in `"detail"` fields, including:
- `"140 tables accessible"` — reveals approximate DB schema size
- Cache/Redis connection error strings — reveals infrastructure configuration

---

## Fix Implementation

**File modified:** `packages/saas-platform/core-utils/src/Services/Health/RuntimeHealthService.php`

**Strategy:** Environment-aware response filtering via `isProduction()` helper.

### `/health/release` — Production Response (AFTER fix)
```json
{"coherent": false, "timestamp": "2026-06-15T17:21:09+00:00"}
```
No UUID, no git commit, no platform version, no migration count.

### `/health/live` — Production Response (AFTER fix)
```json
{"status": "alive", "timestamp": "2026-06-15T17:21:09+00:00"}
```
No PHP version.

### `/health/ready` — Production Response (AFTER fix)
```json
{
  "ready": false,
  "timestamp": "2026-06-15T17:21:09+00:00",
  "checks": {
    "database": {"passed": true},
    "cache": {"passed": false},
    "storage": {"passed": true},
    "queue": {"passed": true}
  }
}
```
Only pass/fail booleans — no internal detail strings.

### Non-Production (local/staging) — Full diagnostics preserved
In local/staging environments (`APP_ENV != production`), all original diagnostic data is still returned for debugging purposes.

---

## Verification — Production Test Results

```
GET /health/release  → HTTP 503 — {"coherent":false,"timestamp":"..."} ✅
GET /health/live     → HTTP 200 — {"status":"alive","timestamp":"..."}  ✅
GET /health/ready    → HTTP 200 — {"ready":false,"timestamp":"...","checks":{...}} ✅
```

All sensitive fields absent from production responses. Confirmed via `curl` on production server after PHP-FPM restart.

---

## Deployment Notes

**Root cause discovered:** The server-side `deploy.sh` did not include the `vendor/saas-platform/` sync step. The package updates deployed only to `packages/`, while PHP's autoloader loads from `vendor/`. Resolution: `rsync -a --delete packages/ vendor/` step added to `/var/www/deploy.sh` for all 4 local packages (core-utils, modules-education, modules-forms, modules-tickets).

**PHP-FPM restart added to deploy.sh** — future deploys will automatically clear opcache after every deployment.

---

## Pre-existing Issues (not introduced by this fix)

| Issue | Status |
|-------|--------|
| `cache: passed:false` in `/health/ready` — Redis probe failing | Pre-existing, out of scope |
| `coherent: false` in `/health/release` — `latest_release` is null (no release manifest seeded) | Pre-existing, out of scope |

---

## Git Commit

`f8846d7` — `fix(security): PROD-001 — strip deployment metadata from health endpoints in production`

---

## Auditor

Claude Code automated execution — 2026-06-15
