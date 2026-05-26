# Security Production Checklist
## Phase 8.0 — Pre-Deployment Verification
**Date:** 2026-05-25

---

## PRE-DEPLOYMENT (Server Setup)

### HTTPS / TLS
- [ ] Valid TLS certificate installed (Let's Encrypt or commercial)
- [ ] HTTP → HTTPS redirect enforced at nginx/Apache level
- [ ] `FORCE_HTTPS=true` set in `.env` (activates HSTS header)
- [ ] TLS 1.2 minimum, TLS 1.3 preferred. Disable SSLv3/TLS 1.0/1.1
- [ ] HSTS preload applied after 30 days of successful operation

### Environment
- [ ] `APP_ENV=production` in `.env`
- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_KEY` set (run `php artisan key:generate` on fresh install)
- [ ] No `.env` file accessible via web (confirm `public_path` is document root)
- [ ] `config:cache` run after every deploy
- [ ] `route:cache` run after every deploy
- [ ] `view:cache` run after every deploy

### Database
- [ ] DB credentials in `.env` only — not hardcoded anywhere
- [ ] DB user has minimum privileges (no `SUPER`, `GRANT`, `DROP DATABASE`)
- [ ] `php artisan migrate` verified successful
- [ ] No `_dev` or `_staging` table prefixes in production DB

---

## QUEUE WORKERS

- [ ] Supervisor configured for at least 1 queue worker
- [ ] Queue worker restart script in deploy hook (`php artisan queue:restart`)
- [ ] `QUEUE_CONNECTION=redis` (not `sync`) in production `.env`
- [ ] Redis accessible from platform server (firewall rules verified)
- [ ] Failed jobs table exists (`php artisan queue:failed-table && php artisan migrate`)
- [ ] Alert configured for failed job count > threshold

---

## REDIS / CACHE

- [ ] `CACHE_DRIVER=redis` in production `.env`
- [ ] `SESSION_DRIVER=redis` in production `.env`
- [ ] Redis password set (no unauthenticated Redis)
- [ ] Redis not exposed to public internet (bind to localhost or private network)
- [ ] Cache prefix set: `CACHE_PREFIX=saas-prod` to avoid collisions across environments

---

## STORAGE & ATTACHMENTS

- [ ] `storage:link` NOT required — platform uses a custom storage route (file serving handled by `Route::get('storage/{path}', ...)`)
- [ ] `storage/app/private/` directory has chmod 750 (web server readable, not world-readable)
- [ ] `storage/app/public/` contains NO ticket attachments (verify: `ls storage/app/public/ticket-attachments/` returns empty)
- [ ] `SecureAttachmentController` reachable via `/admin/attachments/...` (test with an admin session)
- [ ] Tenant attachment API reachable via `/api/tenant/attachments/...` (test with valid license)

---

## API SECURITY

- [ ] Run `php artisan security:validate --fail-on-warning` — must exit 0
- [ ] `/api/support-tickets` routes return 401 without credentials (test: `curl -X GET /api/support-tickets`)
- [ ] `/api/tenant/tickets` returns 401 without `Authorization: Bearer` header
- [ ] `/api/tenant/tickets` returns 403 with invalid license key
- [ ] `/api/tenant/tickets` returns 200 with valid license key and domain
- [ ] Legacy endpoint returns `X-Deprecated-Endpoint: true` header

---

## TENANT ISOLATION VERIFICATION

- [ ] Run `php artisan tenant:validate-isolation` — must exit 0
- [ ] Verify: creating a ticket via site A's API cannot be read by site B's API
- [ ] Admin `GET /admin/tickets` shows tickets from all sites (no scope applied in web context)
- [ ] `GET /api/tenant/tickets` with site A license shows only site A tickets

---

## RATE LIMITING

- [ ] License validation: 60/minute throttle active
- [ ] Ticket API: 30/minute throttle active
- [ ] Password reset: 5/minute throttle active
- [ ] Verify rate limit returns 429 after exceeding threshold

---

## CRON / SCHEDULER

- [ ] Laravel scheduler registered in crontab: `* * * * * php /path/to/artisan schedule:run`
- [ ] `expiry:send-reminders` running daily at 08:00
- [ ] `activity-log:prune` running daily at 03:00
- [ ] Log rotation configured for `storage/logs/*.log`

---

## BACKUPS

- [ ] Database backup scheduled (daily minimum)
- [ ] `storage/app/private/` backup scheduled (attachment files)
- [ ] Backup restore procedure documented and tested
- [ ] Backup stored off-server (S3, remote SFTP, or equivalent)

---

## MONITORING & HEALTH

- [ ] `GET /health` endpoint responds 200 (if implemented, else use Laravel's built-in)
- [ ] Error monitoring configured (Sentry, Bugsnag, or equivalent)
- [ ] `APP_DEBUG=false` verified — no stack traces in production responses
- [ ] Server uptime monitoring active (UptimeRobot, Pingdom, or equivalent)

---

## SECURITY SCAN

- [ ] `php artisan security:validate` exits 0
- [ ] `php artisan security:scan-secrets` exits 0 (no secrets in logs)
- [ ] `php artisan package:validate-boundaries` exits 0 (or known violations documented)
- [ ] `php artisan tenant:validate-isolation` exits 0

---

## ROLLBACK PREPARATION

- [ ] Current DB snapshot taken before deploy
- [ ] Git tag applied to current commit before deploy (e.g. `git tag v1.0.0-prod-YYYYMMDD`)
- [ ] Rollback command sequence documented:
  ```
  1. git checkout <previous-tag>
  2. composer install --no-dev
  3. php artisan migrate:rollback (last N steps)
  4. php artisan config:cache
  5. php artisan queue:restart
  ```
- [ ] Rollback tested in staging environment

---

## POST-DEPLOY VERIFICATION

- [ ] Platform admin login works: `GET /admin` redirects to login correctly
- [ ] Module pages load: `GET /admin/tickets` shows ticket list
- [ ] Tenant API works: `POST /api/tenant/tickets` with valid credentials
- [ ] Attachment upload (admin) → file stored in private disk, NOT public
- [ ] Attachment download (admin) → served via `/admin/attachments/` route
- [ ] No PHP errors in `storage/logs/laravel.log`
- [ ] Queue worker processing jobs (check `php artisan queue:work --once`)

---

## ONGOING SECURITY MAINTENANCE

- [ ] Run `php artisan security:validate` weekly (add to cron)
- [ ] Run `php artisan security:scan-secrets` after any debug session
- [ ] Run `php artisan package:validate-boundaries` before every merge to main
- [ ] Review `storage/logs/laravel.log` for `DEPRECATED_API_ACCESS` entries weekly
- [ ] Rotate license keys for any site that has `DEPRECATED_CREDENTIAL_TRANSPORT` warnings
- [ ] Keep `composer.json` dependencies updated monthly (`composer audit` for vulnerabilities)
