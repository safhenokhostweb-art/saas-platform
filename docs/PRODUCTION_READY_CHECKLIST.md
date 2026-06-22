# PRODUCTION READY CHECKLIST
> Derived from PRODUCTION_READINESS_SCORE_V2.md — 2026-06-22
> Final go-live verification checklist.

---

## CRITICAL FLOWS — ALL VERIFIED ✅

### Site Lifecycle
- [x] **C02** — Subscription renewal triggers lifecycle reactivation  
  `ProcessRenewalsCommand::reactivateSiteIfSuspended()` — verified in code
- [x] **C04** — Customer can request self-service restore from suspended page  
  `suspended.blade.php` form → `/account/lifecycle/restore`
- [x] **C01** — Restore invoice has payment flow (Zarinpal/IDPay)  
  `LifecyclePaymentController` + `/front/lifecycle/pay.blade.php`

### SMS Campaigns
- [x] **C03** — Scheduled campaigns auto-fire  
  `ProcessScheduledSmsCampaignsCommand` registered in Kernel (customer-site)

### Storage
- [x] **C05** — Physical files deleted on site archive  
  `SiteArchiveService::deleteActiveFiles()` called in `SiteLifecycleService::archive()`

---

## HIGH-PRIORITY FLOWS — ALL VERIFIED ✅

### Theme System
- [x] **H02** — Theme install backs up current theme before overwriting  
  `previous_canvas_theme_data` in settings; "بازگشت به قالب قبلی" revert button
- [x] **H02** — Old draft cleared on new theme install (M05)  
  `Setting::set('canvas_draft_data', '')` in `ThemeMarketplaceController::install()`

### Billing
- [x] **H05** — Dunning SMS sent on day 3, 7, 14 for unpaid renewal invoices  
  `ProcessDunningCommand` — day intervals, SubscriptionLog tracking
- [x] **H06** — PDF invoices available for marketplace + bundle purchases  
  `/admin/theme-marketplace/my-licenses/{key}/invoice`

### Resource Governance
- [x] **H04** — Overage fees billed monthly  
  `BillResourceOverageCommand` — monthly, `SiteLifecycleInvoice::TYPE_OVERAGE`
- [x] **H07** — Uploads blocked when site exceeds storage limit  
  `SmartImageUploadService::enforceStorageLimit()` — 512MB default, 5min cache

---

## MEDIUM FLOWS — ALL VERIFIED ✅

### Canvas Editor
- [x] **M01** — Autosave every 60 seconds  
  `setInterval(() => saveBlocks(), 60000)` in `canvas-editor.blade.php`
- [x] **M02** — Published theme revision history (5-slot cap)  
  `rotateRevisions()` called before each `canvasPublish()`

### Lifecycle
- [x] **H03** — Mid-period grace reminder SMS sent  
  `CheckGracePeriodCommand` + `gracePeriodMidReminder()` with `grace_reminder_sent_at`
- [x] **M03** — Suspended 503 page has support contact + payment link  
  Support phone + Telegram contact section in `suspended.blade.php`
- [x] **L05** — Admin lifecycle panel shows days-until-archive countdown  
  Countdown chip: red ≤30d, amber ≤90d

### Marketplace & Onboarding
- [x] **M07** — Admin sees warning when `PLATFORM_URL` not configured  
  Amber banner in admin layout with `.env` setup instructions
- [x] **M09** — Customer receives SMS when site is created  
  `SiteController::sendSiteCreatedSms()` after `Site::create()`
- [x] **L09** — Post-create checklist shown after site creation  
  Redirect to edit + `just_created` flash + 4-action setup banner
- [x] **L01** — License expiry date shown in my-licenses  
  `expires_at` in API response + my-licenses view
- [x] **L02** — Payment failure shows guided retry  
  `pay-failed.blade.php` — 4-step guidance + support contacts
- [x] **L03** — Campaign delivery report CSV export  
  `SmsCampaignController::exportCsv()` + button in campaign show view

### Storage & Archival
- [x] **M08** — Resource push failure triggers admin alert  
  `alertPushFailed()` in `SiteResourceAlertService`
- [x] **M10** — Lifecycle delete triggers physical file cleanup  
  `SiteArchiveService::deleteArchiveZip()` wired in `delete()`
- [x] **L06** — Archive ZIP verified after creation  
  `verifyZip()` — size > 0 + `ZipArchive::RDONLY` check
- [x] **L08** — Resource counters recalculated immediately on restore  
  `restore()` calls `calculator->calculate()` before returning

---

## PRE-DEPLOY PRODUCTION CHECKS

### Environment
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `CACHE_DRIVER=redis` (AppServiceProvider throws if file in production)
- [ ] `SESSION_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `ANTHROPIC_API_KEY=...` (Theme AI generator)
- [ ] `PLATFORM_URL=https://...` (Customer site ↔ Platform API)
- [ ] `PLATFORM_API_KEY=...`
- [ ] `SMS_GATEWAY=kavenegar` or `ghasedak`
- [ ] `KAVENEGAR_API_KEY=...` or `GHASEDAK_API_KEY=...`
- [ ] `SLACK_ALERT_WEBHOOK=...`
- [ ] `EDUCATION_AI_PROVIDER=claude` (if AI education enabled)

### Services
- [ ] Redis running — `redis-cli ping` → PONG
- [ ] Supervisor running — `supervisorctl status`
- [ ] Scheduler registered — `crontab -l` shows `artisan schedule:run`
- [ ] Queue workers healthy — `php artisan queue:monitor`
- [ ] DB backup scheduled — 02:00 daily via Kernel.php
- [ ] `monitor:platform` running every 2 minutes
- [ ] `monitor:sites` running every 5 minutes

### Health Endpoint
- [ ] `GET /health/ready` → `{"status":"ready","ready":true}` (all 4 checks passing)

### Migrations
- [ ] All migrations applied on production (`php artisan migrate --force`)
- [ ] Customer-site migrations applied on each tenant DB

### First-Run Actions
- [ ] Navigate to `/admin/theme-marketplace` → click "راه‌اندازی" to seed 12 themes
- [ ] Run `php artisan marketplace:seed-scores` to populate quality scores
- [ ] Verify `/health/ready` still returns `ready: true` after seed

---

## KNOWN NON-BLOCKERS (Post-Launch Backlog)

| Item | Severity | Description |
|------|----------|-------------|
| GAP-H01 | HIGH (deferred) | Two theme systems (classic + Canvas) without unified bridge |
| GAP-L07 | LOW | SMS grace period messages contain no renewal URL |
| GAP-L10 | LOW | Canvas collaborative editing has no conflict detection (multi-admin) |
| Cloud ZIP offload | LOW | Archive ZIPs stored on local disk only (no S3/cloud) |
| Recipient count preview | LOW | No preview of recipient count before sending SMS campaign |
| Purchase confirmation SMS | LOW | No SMS sent on marketplace theme purchase completion |
| DNS validation | LOW | No DNS check before site domain creation |
| DB auto-provisioning | LOW | Manual DB setup required for new customer sites |

---

*Checklist generated: 2026-06-22*
*Based on: PRODUCTION_READINESS_SCORE_V2.md*
*Verdict: PRODUCTION_READY (91/100)*
