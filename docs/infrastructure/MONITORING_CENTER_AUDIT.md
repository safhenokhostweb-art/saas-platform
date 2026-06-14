# MONITORING_CENTER_AUDIT.md
> Phase: INFRA-MONITORING-01 — Central Monitoring & Health Center  
> Completed: 2026-06-14  
> Status: COMPLETE

---

## Files Created

### Migrations
| File | Table | Purpose |
|------|-------|---------|
| `database/migrations/2026_06_14_100001_create_infra_site_health_logs_table.php` | `infra_site_health_logs` | Stores per-site health check snapshots |
| `database/migrations/2026_06_14_100002_create_infra_incidents_table.php` | `infra_incidents` | Stores open/resolved incidents with SMS throttle |

### Models
| File | Model | Notes |
|------|-------|-------|
| `app/Models/Infrastructure/InfraSiteHealthLog.php` | `InfraSiteHealthLog` | `latestPerSite()` scope, `recent()` scope |
| `app/Models/Infrastructure/InfraIncident.php` | `InfraIncident` | `open()`, `critical()`, `needsSmsAlert()` helpers |

### Services
| File | Class | Namespace |
|------|-------|-----------|
| `app/Services/Infrastructure/Monitoring/HealthCheckService.php` | `HealthCheckService` | Checks DB, Redis, Queue, Reverb, Web |
| `app/Services/Infrastructure/Monitoring/SiteHealthChecker.php` | `SiteHealthChecker` | Per-site HTTP probe + SSL cert check |
| `app/Services/Infrastructure/Monitoring/DeploymentVerificationService.php` | `DeploymentVerificationService` | Post-deploy verification of /, /login, /api/health |
| `app/Services/Infrastructure/Monitoring/InfrastructureErrorCollector.php` | `InfrastructureErrorCollector` | Centralizes incident creation, SMS & Slack throttle |

### Commands
| File | Signature | Schedule |
|------|-----------|----------|
| `app/Console/Commands/MonitorSitesCommand.php` | `monitor:sites [--site=]` | `everyFiveMinutes()` |

### Controllers
| File | Class | Routes Served |
|------|-------|---------------|
| `app/Http/Controllers/Admin/Infrastructure/MonitoringController.php` | `MonitoringController` | index, summary, sites, incidents, resolveIncident |

### Views
| File | Purpose |
|------|---------|
| `resources/views/admin/infrastructure/monitoring/index.blade.php` | Persian RTL monitoring dashboard |

### customer-site Changes
| File | Change |
|------|--------|
| `c:\laragon\www\customer-site\routes\api.php` | Added `GET /api/health` public endpoint (no auth) |

---

## Routes Added

### saas-platform (web.php)
| Method | URI | Route Name | Controller |
|--------|-----|-----------|------------|
| GET | `/admin/infrastructure/monitoring` | `admin.infrastructure.monitoring.index` | MonitoringController@index |
| GET | `/admin/api/monitoring/summary` | `admin.api.monitoring.summary` | MonitoringController@summary |
| GET | `/admin/api/monitoring/sites` | `admin.api.monitoring.sites` | MonitoringController@sites |
| GET | `/admin/api/monitoring/incidents` | `admin.api.monitoring.incidents` | MonitoringController@incidents |
| POST | `/admin/api/monitoring/incidents/{id}/resolve` | `admin.api.monitoring.incidents.resolve` | MonitoringController@resolveIncident |

### customer-site (api.php)
| Method | URI | Auth | Purpose |
|--------|-----|------|---------|
| GET | `/api/health` | None (public) | Platform monitoring probe |

---

## Migrations Added

### `infra_site_health_logs`
```sql
id, site_id (nullable, FK→sites), domain, status (healthy|warning|critical|unknown),
http_status, response_time_ms, ssl_status, ssl_expires_at, ssl_days_remaining,
db_status, redis_status, queue_status, error_message, checked_at, timestamps
Indexes: site_id, status, checked_at, (site_id, checked_at)
```

### `infra_incidents`
```sql
id, site_id (nullable), severity (warning|critical), type (site_down|db_error|redis_error|queue_dead|ssl_expiring|deploy_failure|general),
title, message, resolved_at, sms_sent_at, timestamps
Indexes: site_id, severity, resolved_at, (site_id, resolved_at)
```

---

## Services Added

### HealthCheckService
Checks platform-level services synchronously on dashboard load:
- `checkDatabase()` — `DB::select('SELECT 1')`
- `checkRedis()` — `Redis::ping()`
- `checkQueue()` — `Queue::size()` with >1000 → warning
- `checkReverb()` — TCP socket to `reverb.host:reverb.port`
- `checkWebServer()` — HTTP GET to `config('app.url')`

### SiteHealthChecker
Per-site check via HTTP:
- Fetches `{siteUrl}/api/health` → reads `db`, `redis`, `queue` from JSON
- SSL cert check via `stream_socket_client ssl://domain:443` → parses `openssl_x509_parse`
- SSL rules: <10 days → critical, <30 days → expiring_soon, else valid
- Localhost/192.168.x domains skip SSL check
- Saves `InfraSiteHealthLog` record

### DeploymentVerificationService
After each deploy call `verify($baseUrl, $siteId=null)`:
- GETs `/`, `/login`, `/api/health`
- Any HTTP 500/502/503 → creates critical incident via `InfrastructureErrorCollector`

### InfrastructureErrorCollector
Deduplicates incidents (finds existing open incident of same type+site), sends:
- SMS to all `is_admin=true` users (throttled: one SMS per 30 min per incident)
- Slack alert via `SlackAlertService`
- `collect(type, title, message, siteId, severity)` — public API
- `collectQueueFailure(jobName, error)` — queue hook helper
- `collectSchedulerFailure(command, error)` — scheduler hook helper

---

## Scheduler Jobs
| Command | Frequency | Flag |
|---------|-----------|------|
| `monitor:sites` | Every 5 minutes | `withoutOverlapping()`, logged to `storage/logs/monitor-sites.log` |

---

## AdminMenu Changes
Added `monitoring_center` key to `AdminMenu.php`:
- Label: `مرکز مانیتورینگ`
- Route: `admin.infrastructure.monitoring.index`
- Pattern: `admin.infrastructure.monitoring.*`
- Group: `زیرساخت / DevOps`

---

## Dashboard Features

| Feature | Implementation |
|---------|----------------|
| Platform Status cards (4) | Total / Healthy / Warning / Critical site counts |
| Infrastructure Health panel | Real-time DB, Redis, Queue, Reverb, Web status |
| Response Time Trend chart | 24-hour bar chart from `infra_site_health_logs` |
| Site Status Table | Per-site: domain, status, response time, SSL, queue, DB, Redis |
| Open Incidents list | Color-coded (red=critical, amber=warning), inline resolve button |
| Incident Trend chart | 7-day bar chart |
| JSON API links | Quick links to all 3 API endpoints |
| Auto-refresh button | Reloads page |
| 30s SMS throttle | `sms_sent_at` column prevents alert spam |

---

## Known Limitations

1. **No real-time WebSocket push** — dashboard requires manual page refresh or `setInterval`. Auto-polling can be added by calling `/admin/api/monitoring/summary` every 30s via Alpine.js.
2. **Queue check via `Queue::size()`** — counts pending jobs, not whether workers are running. A dead worker with empty queue will show `ok`.
3. **Reverb check is TCP only** — verifies port is open, not that WebSocket handshake works.
4. **SSL check skips localhost** — domains matching `localhost`, `127.*`, `192.168.*` return `status: unknown` for SSL (by design).
5. **No historical health log pruning** — `infra_site_health_logs` will grow indefinitely. Add a pruning command to keep last N days.
6. **DeploymentVerificationService is manual** — must be called explicitly after deploy; not auto-wired to `deploy.ps1` yet.

---

## Security Review

| Area | Finding | Risk | Status |
|------|---------|------|--------|
| `/api/health` endpoint (customer-site) | Public, no auth | LOW — returns only status strings, no sensitive data | ACCEPTED |
| Monitoring dashboard | Behind `auth + admin` middleware | ✓ SECURE | PASS |
| SMS admin numbers | Pulled from `users where is_admin=true` | Same pattern as `ExpirySmsService` | PASS |
| Incident resolve POST | CSRF-protected (web middleware), admin-only | ✓ SECURE | PASS |
| SSL cert check | `verify_peer: false` on stream socket | Required for self-signed; only reads cert metadata | ACCEPTED |

---

## Deployment Notes

1. Run migrations on production:
   ```bash
   php artisan migrate --path=database/migrations/2026_06_14_100001_create_infra_site_health_logs_table.php
   php artisan migrate --path=database/migrations/2026_06_14_100002_create_infra_incidents_table.php
   ```

2. Deploy customer-site (routes/api.php changed):
   ```bash
   # in customer-site repo
   ./deploy.ps1
   ```

3. Ensure Laravel scheduler is running on production:
   ```cron
   * * * * * php /path/to/saas-platform/artisan schedule:run >> /dev/null 2>&1
   ```

4. First health check run:
   ```bash
   php artisan monitor:sites
   ```

5. To verify a deployment after deploy:
   ```php
   app(DeploymentVerificationService::class)->verify('https://yoursite.com', $siteId);
   ```

---

## Final Verdict: PASS

All deliverables implemented. System works on localhost today, production-ready for VPS deployment.
