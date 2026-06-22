# LAUNCH_DAY_RUNBOOK
> SaaS Platform — Launch Day Operations Guide  
> Date: 2026-06-22  
> Status: **LAUNCH_RUNBOOK_READY**  
> Supersedes: GO_LIVE_CHECKLIST.md (this document provides more operational detail)

---

## Launch Team

| Role | Responsible | Contact |
|------|------------|---------|
| Platform Admin | Lead Developer | On-call during T-60 to T+4h |
| DNS / CDN | DevOps | Active at T-30 to T+15m |
| QA / Smoke Test | Developer | Active at T-10 to T+1h |
| Support | Customer Success | Active at T+0 to T+48h |

---

## Pre-Launch Checks (Complete 24 Hours Before Launch)

### P0 — Environment

```bash
# Connect to production VPS:
ssh -p 9011 root@87.107.12.81

# Check PHP version (must be 8.4.x):
php -v

# Check MySQL:
mysql -u root -p -e "SHOW DATABASES;"

# Check Redis:
redis-cli ping
# Expected: PONG

# Check Supervisor (must show 3x RUNNING):
supervisorctl status
# Expected:
# saas-platform-worker:saas-platform-worker_00   RUNNING
# saas-platform-worker:saas-platform-worker_01   RUNNING
# saas-platform-worker:saas-platform-worker_02   RUNNING

# Check disk space (must be < 80%):
df -h /var/www

# Check Nginx config:
nginx -t
# Expected: configuration file /etc/nginx/nginx.conf syntax is ok
```

### P1 — Environment Variables

```bash
# Check critical env vars:
grep -E "APP_ENV|APP_DEBUG|APP_URL|CACHE_DRIVER|SESSION_DRIVER|QUEUE_CONNECTION|DB_HOST|REDIS_HOST" /var/www/saas-platform/.env
```

Expected values:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.safhenokhost.ir   ← must be HTTPS
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
```

Also verify:
```bash
grep -E "KAVENEGAR_API_KEY|GHASEDAK_API_KEY|SLACK_ALERT_WEBHOOK|SANCTUM_STATEFUL_DOMAINS|ANTHROPIC_API_KEY" /var/www/saas-platform/.env
# All must be set (non-empty)
```

### P2 — Database State

```bash
cd /var/www/saas-platform

# Check pending migrations (must be 0):
php artisan migrate:status | grep "Pending" | wc -l
# Expected: 0

# Check admin user exists:
php artisan tinker --execute="echo App\Models\User::where('is_admin',true)->count().' admin users';"
# Expected: 1 admin users (or more)
```

### P3 — Cache & Optimization

```bash
cd /var/www/saas-platform

# Clear and rebuild all caches:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Verify config is cached:
php artisan config:cache && echo "Config cached OK"
```

### P4 — Health Endpoints

```bash
# Platform health (run from VPS):
curl -sk http://127.0.0.1/health/ready | python3 -m json.tool
# Expected:
# {"ready": true, "checks": {"database": true, "cache": true, "queue": true, "storage": true}}

# Platform liveness:
curl -o /dev/null -sw "%{http_code}" http://127.0.0.1/health/live
# Expected: 200
```

### P5 — Scheduler Verification

Run each lifecycle command manually once to verify zero errors:

```bash
cd /var/www/saas-platform
php artisan lifecycle:check-expired --dry-run 2>&1
php artisan lifecycle:check-grace-period --dry-run 2>&1
php artisan lifecycle:check-suspended --dry-run 2>&1
php artisan lifecycle:check-archived --dry-run 2>&1
php artisan resource:calculate --dry-run 2>&1
php artisan monitor:platform 2>&1 | tail -5
php artisan monitor:sites 2>&1 | tail -5

# Verify cron is active:
crontab -l | grep schedule:run
# Expected output: * * * * * cd /var/www/saas-platform && php artisan schedule:run >> /dev/null 2>&1
```

### P6 — SMS Test

```bash
cd /var/www/saas-platform
php artisan tinker --execute="
    \$sms = app(App\Services\SmsService::class);
    \$result = \$sms->send('YOUR_MOBILE', 'تست ارسال پیامک');
    echo \$result ? 'SMS SENT' : 'SMS FAILED';
"
```

### P7 — Backup Verification

```bash
# Run fresh backup:
cd /var/www/saas-platform
php artisan db:backup

# Verify backup file:
ls -lh storage/app/backups/
gzip -t storage/app/backups/$(ls -t storage/app/backups/ | head -1)
echo "Exit code: $?"  # Must be 0

# Copy to offsite before DNS cutover:
# scp storage/app/backups/latest.sql.gz user@offsite:/backups/saas/
```

### P8 — Marketplace Seed

```bash
cd /var/www/saas-platform
# If starting fresh (no themes in marketplace):
php artisan marketplace:seed
php artisan marketplace:seed-scores

# Verify themes exist:
php artisan tinker --execute="echo App\Models\Theme::count().' themes in DB';"
```

---

## Launch Sequence

### T-60 minutes: Final Deploy

```powershell
# From local machine (PowerShell):
cd c:\laragon\www\saas-platform
.\deploy.ps1 "deploy: launch day final"

# Verify deploy completed:
# Check last commit on VPS matches local:
# ssh -p 9011 root@87.107.12.81 "cd /var/www/saas-platform && git log --oneline -1"
```

### T-30 minutes: DNS Preparation

1. Log into DNS provider / ArvanCloud panel
2. Navigate to domain DNS settings
3. Prepare DNS records (DO NOT apply yet):
   - `A` record: `@` → `87.107.12.81`
   - `A` record: `panel` → `87.107.12.81`
   - `CNAME` record: `www` → `@`
4. Prepare CDN proxy activation (ArvanCloud orange cloud)

### T-15 minutes: Final Health Check

```bash
# Run health check one more time:
curl -sk http://127.0.0.1/health/ready | python3 -m json.tool

# Check queue workers:
supervisorctl status | grep "RUNNING"
# Must show 3x RUNNING

# Check no failed jobs:
cd /var/www/saas-platform && php artisan queue:failed | head -5
# Should be empty
```

### T-10 minutes: Smoke Test (Pre-DNS)

With VPS accessible via IP or staging domain:

1. Open `https://staging.domain.ir` (or `http://87.107.12.81` directly)
2. Log in to admin panel
3. Create a test site → assign a theme → activate
4. Check monitoring dashboard for no active incidents
5. Check SMS log for test SMS delivery

### T-5 minutes: Final Go/No-Go Decision

| Check | Status | Go/No-Go |
|-------|--------|---------|
| Health endpoint: `ready: true` | | |
| All 3 queue workers RUNNING | | |
| No open CRITICAL incidents | | |
| SMS test delivered | | |
| Fresh backup exists (< 1h old) | | |

**If any check is NO-GO:** Do NOT cut DNS. Fix the issue first.

### T-0: DNS Cutover

1. Apply DNS records in provider panel
2. Enable ArvanCloud CDN proxy (orange cloud) for both root and www
3. Note the time of cutover
4. TTL: DNS changes propagate in 1–5 minutes with low TTL, up to 24h with high TTL

```bash
# Monitor DNS propagation:
watch -n 10 "dig +short yourdomain.ir"
# Watch for IP to change from old server to 87.107.12.81 / CDN IP
```

### T+2 minutes: CDN Health Check

```bash
# Check via CDN (real domain):
curl -sI https://yourdomain.ir/health/live
# Expected: HTTP/1.1 200 OK

curl -sk https://yourdomain.ir/health/ready | python3 -m json.tool
# Expected: {"ready": true, ...}
```

### T+5 minutes: First Customer Access

```bash
# Navigate to https://yourdomain.ir
# Verify:
# 1. Login page loads (HTTPS padlock visible)
# 2. Login succeeds
# 3. Admin dashboard loads
# 4. Canvas theme builder opens
# 5. Public site (tenant) loads at customer domain
```

### T+10 minutes: Monitoring Sweep

```bash
# Check admin monitoring dashboard:
# Navigate to /admin/infrastructure/monitoring
# Expected: Platform status GREEN, no active incidents

# Check site health log freshness:
php artisan tinker --execute="echo App\Models\Infrastructure\InfraSiteHealthLog::latest()->first()?->checked_at;"
# Expected: within last 5 minutes

# Check for auto-created incidents:
php artisan tinker --execute="echo App\Models\Infrastructure\InfraIncident::active()->count().' active incidents';"
# Expected: 0
```

### T+30 minutes: Subscription / Payment Test

If payment gateways are active:
1. Go to `/account/subscriptions/plans` on customer site
2. Attempt a test subscription with a small amount
3. Verify payment callback returns correctly
4. Verify subscription activates
5. Verify SMS confirmation sends

---

## Rollback Plan

### Scenario A: Application Error (500 on all pages)

```bash
ssh -p 9011 root@87.107.12.81
cd /var/www/saas-platform

# Check Laravel error log:
tail -100 storage/logs/laravel.log

# Roll back to previous commit:
git log --oneline -5
git checkout <previous-commit-hash>
php artisan optimize
supervisorctl restart all
```

### Scenario B: Database Migration Error

```bash
# DO NOT run migrate:rollback for production migrations
# Instead:
# 1. Identify the failing migration in storage/logs/laravel.log
# 2. Fix the migration locally
# 3. Deploy fix via deploy.ps1
# 4. Re-run: php artisan migrate --force

# Emergency: roll back last batch only:
php artisan migrate:rollback --step=1 --force
# Use only if the batch has no data-destructive operations
```

### Scenario C: DNS Revert (Critical Bug Found)

If a critical bug is discovered within 30 minutes of DNS cutover:

1. Revert DNS records in provider panel to old server IPs
2. Disable ArvanCloud CDN proxy (gray cloud)
3. Wait for DNS propagation (1–5 minutes)
4. Fix the bug on the platform VPS
5. Re-cut DNS when fixed

**Note:** If TTL was high (3600+), revert takes up to 1 hour to propagate.

### Scenario D: Queue Workers Down

```bash
ssh -p 9011 root@87.107.12.81

# Restart Supervisor:
supervisorctl restart all

# If Supervisor itself is down:
systemctl restart supervisor

# Verify workers are up:
supervisorctl status
```

### Scenario E: Redis Down

```bash
# Check Redis:
systemctl status redis
redis-cli ping

# Restart if down:
systemctl restart redis

# Verify sessions/cache work:
php artisan cache:clear
php artisan tinker --execute="Cache::put('test', 1, 10); echo Cache::get('test') === 1 ? 'REDIS OK' : 'REDIS FAIL';"
```

---

## Post-Launch Monitoring (First 48 Hours)

### Hour 0–1: Intensive Watch

Every 10 minutes:
- [ ] Check `/admin/infrastructure/monitoring` for new incidents
- [ ] Check queue depth: `php artisan queue:monitor`
- [ ] Check Slack `#alerts` channel for any alerts

### Hour 1–24: Active Watch

Every 30 minutes:
- [ ] Check incident list at `/admin/incidents`
- [ ] Verify SMS delivery at `/admin/infrastructure/sms` (success rate > 95%)
- [ ] Check queue workers: `supervisorctl status`

### Day 1 End-of-Day (T+24h):

```bash
# Verify scheduler ran all commands:
php artisan tinker --execute="
    App\Models\Infrastructure\InfraSchedulerRun::where('created_at', '>=', now()->subDay())
        ->groupBy('command')
        ->selectRaw('command, count(*) as runs, max(status) as last_status')
        ->get()
        ->each(fn(\$r) => print(\"{$r->command}: {$r->runs} runs, {$r->last_status}\n\"));
"

# Verify backup ran:
ls -lh /var/www/saas-platform/storage/app/backups/

# Check error rate in Laravel log:
grep -c "ERROR\|CRITICAL" /var/www/saas-platform/storage/logs/laravel.log
```

### Day 2 (T+48h): First Stability Review

- [ ] Review all auto-created incidents (resolve if false positives)
- [ ] Check disk usage trend: `df -h`
- [ ] Verify subscription renewal ran (if any subscriptions due)
- [ ] Review SMS delivery rate in campaign dashboard
- [ ] Send internal team review email: 48-hour stability confirmed

---

## First 48-Hour Watchlist

| Metric | Alert Threshold | Check Command |
|--------|----------------|--------------|
| Queue failed jobs | > 0 | `php artisan queue:failed` |
| Disk usage | > 80% | `df -h /var/www` |
| Active incidents | > 0 CRITICAL | `/admin/incidents?severity=critical` |
| SMS failure rate | > 5% | `/admin/infrastructure/sms` |
| Backup freshness | > 26h old | `ls -lt storage/app/backups/` |
| Scheduler freshness | > 5m gap | InfraSchedulerRun latest timestamp |
| HTTP error rate | > 1% 5xx | Nginx access log: `grep " 5" /var/log/nginx/access.log | wc -l` |

---

## Sign-Off Sheet

| Check | Verified By | Time (UTC+3:30) |
|-------|-------------|-----------------|
| All pre-launch P0–P8 checks passed | | T-60 |
| Fresh backup taken and stored | | T-30 |
| DNS cutover complete | | T-0 |
| CDN health check passed | | T+2 |
| First customer access confirmed | | T+5 |
| Monitoring sweep clean | | T+10 |
| Payment callback test passed | | T+30 |
| 24h stability confirmed | | T+24h |
| 48h stability confirmed | | T+48h |

**LAUNCH: GO** ✅

---

*LAUNCH_DAY_RUNBOOK — generated 2026-06-22 for RC1 go-live*
