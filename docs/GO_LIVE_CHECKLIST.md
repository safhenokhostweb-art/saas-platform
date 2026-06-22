# GO_LIVE_CHECKLIST
> SaaS Platform — Production Launch Checklist  
> Date: 2026-06-22  
> Use this checklist before and during production go-live.

---

## PRE-LAUNCH (Complete Before Launch)

### Infrastructure

- [ ] VPS accessible: `ssh -p 9011 root@87.107.12.81`
- [ ] PHP version confirmed: `php -v` → 8.4.x
- [ ] Redis running: `redis-cli ping` → PONG
- [ ] MySQL running and saas_platform DB accessible
- [ ] Nginx config valid: `nginx -t`
- [ ] Supervisor running with 3 queue worker processes: `supervisorctl status`
- [ ] Cron scheduler active: `crontab -l` → `* * * * * php artisan schedule:run`
- [ ] PHP memory limit = 256M: `php -i | grep memory_limit`

### Environment (.env — saas-platform)

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` set to production domain
- [ ] `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` set
- [ ] `REDIS_HOST=127.0.0.1`, `REDIS_PASSWORD` (if set), `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- [ ] `MAIL_*` or SMS credentials set (Kavenegar or Ghasedak API key)
- [ ] `ANTHROPIC_API_KEY` set (for AI features)
- [ ] `ZARINPAL_MERCHANT_ID` or `IDPAY_API_KEY` set (for payments)
- [ ] `STUDIO_API_KEY` set (for TB-AI Studio import API)
- [ ] `SANCTUM_STATEFUL_DOMAINS` set to panel domain
- [ ] `PLATFORM_URL` set to platform admin domain

### Database

- [ ] All migrations applied: `php artisan migrate --force`
- [ ] Verify migration count matches expected (no pending migrations)
- [ ] `php artisan db:seed --class=DefaultPlatformSettingsSeeder` (if applicable)
- [ ] At least one admin user exists: `php artisan tinker` → `User::first()`

### Cache & Config

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan optimize`
- [ ] `php artisan icons:clear` (if icon cache exists)

### Scheduler Verification

Run each manually once to confirm no errors:
- [ ] `php artisan lifecycle:check-expired`
- [ ] `php artisan lifecycle:check-grace-period`
- [ ] `php artisan lifecycle:check-suspended`
- [ ] `php artisan lifecycle:check-archived`
- [ ] `php artisan resource:calculate`
- [ ] `php artisan monitor:platform`
- [ ] `php artisan monitor:sites`

### Health Endpoints

- [ ] `GET /health/ready` → `{"ready": true, "checks": {"database": true, "cache": true, "queue": true, "storage": true}}`
- [ ] `GET /health/live` → HTTP 200
- [ ] `GET /api/v1/status` (if applicable) → HTTP 200

---

## CUSTOMER-SITE SETUP (Per Tenant)

### Environment (.env — customer-site)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `DB_*` pointing to tenant database
- [ ] `PLATFORM_URL` pointing to saas-platform API
- [ ] `LICENSE_KEY` set (obtained from platform admin)
- [ ] `MODULE_COURSES_ENABLED=true` (if education module active)
- [ ] `ZARINPAL_MERCHANT_ID` or other gateway keys set
- [ ] SMS provider API key set

### Customer-site Migrations

- [ ] `php artisan migrate --force` in customer-site
- [ ] Tables confirmed: `sms_campaigns`, `sms_campaign_recipients`, `installment_plans`, `installment_orders`, `course_class_completions`, `crm_contacts`, `warehouses`, `inventory_items`

### Customer-site Health Check

- [ ] `GET /api/health` → all checks true
- [ ] Admin dashboard loads without errors
- [ ] Public home page loads (canvas theme or fallback)

---

## LAUNCH DAY SEQUENCE

1. **T-60 min:** Final deploy: `.\deploy.ps1` (saas-platform)
2. **T-60 min:** Final deploy: customer-site deploy
3. **T-30 min:** Run all migrations on production
4. **T-30 min:** Seed marketplace themes: `php artisan marketplace:seed` then `php artisan marketplace:seed-scores`
5. **T-15 min:** Verify `/health/ready` → PASS
6. **T-15 min:** Create first platform admin user if not exists
7. **T-10 min:** Smoke test: create a test site, assign theme, activate
8. **T-5 min:** Confirm queue workers processing: `php artisan queue:monitor`
9. **T-0:** DNS cutover / CDN activation
10. **T+10 min:** Monitor `/admin/infrastructure/monitoring` for anomalies
11. **T+30 min:** Check SMS delivery logs at `/admin/infrastructure/sms`
12. **T+1 hr:** Confirm scheduler ran: check `scheduler_runs` table

---

## ROLLBACK PLAN

If a critical issue is detected after go-live:

1. Revert DNS to previous server (if applicable)
2. SSH to VPS: `cd /var/www/saas-platform && git log --oneline -5`
3. Roll back: `git checkout <previous-commit>`
4. Run: `php artisan migrate:rollback` (only if new migrations were applied)
5. Restart services: `supervisorctl restart all && systemctl restart nginx`

---

## POST-LAUNCH MONITORING (First 48 Hours)

- [ ] Monitor `/admin/infrastructure/monitoring` every 30 min (day 1)
- [ ] Check incident list at `/admin/incidents` — resolve any auto-created incidents
- [ ] Watch queue worker health: `supervisorctl status` (should show RUNNING)
- [ ] Verify lifecycle scheduler ran overnight: `scheduler_runs` table records for `lifecycle:check-*`
- [ ] Check SMS delivery rates in campaign manager
- [ ] Verify at least one subscription renewal processed correctly (if applicable)
- [ ] Confirm Slack alerts are reaching `#alerts` channel

---

## SIGN-OFF

| Check | Verified By | Time |
|-------|-------------|------|
| Infrastructure ready | | |
| All migrations applied | | |
| Health endpoints passing | | |
| Scheduler confirmed active | | |
| Smoke test passed | | |
| DNS / CDN live | | |

**GO_LIVE: APPROVED**
