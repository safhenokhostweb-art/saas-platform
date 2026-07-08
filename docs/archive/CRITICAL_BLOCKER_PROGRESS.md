# CRITICAL BLOCKER PROGRESS — Production Readiness

> Generated: 2026-06-22
> Last Updated: 2026-06-22
> Status: ALL 5 PHASES CERTIFIED

---

## Summary

| Phase | Code | Description | Status | Certification |
|-------|------|-------------|--------|---------------|
| 1 | C02 | Subscription renewal → lifecycle reactivation | ✅ COMPLETE | C02_REACTIVATION_OPERATIONAL |
| 2 | C04 | Customer self-service reactivation on suspended page | ✅ COMPLETE | C04_SELF_SERVICE_OPERATIONAL |
| 3 | C01 | Restore invoice payment flow (Zarinpal/IDPay) | ✅ COMPLETE | C01_RESTORE_PAYMENT_OPERATIONAL |
| 4 | C05 | Physical file deletion on archive | ✅ COMPLETE | C05_STORAGE_RELEASE_OPERATIONAL |
| 5 | C03 | Scheduled SMS campaign execution | ✅ COMPLETE | C03_SMS_SCHEDULER_OPERATIONAL |

---

## PHASE 1 — C02: Subscription Renewal → Lifecycle Reactivation

**Certification: C02_REACTIVATION_OPERATIONAL**

### Problem
Subscription renewal in customer-site was completely decoupled from lifecycle state in platform.
Sites could pay and renew subscription but remain in `suspended` or `grace_period` state.

### Solution

**saas-platform — New:**
- `app/Http/Controllers/Api/ApiLifecycleController.php`
  - `POST /api/lifecycle/reactivate` — tenant.api protected, idempotent
  - `GET /api/lifecycle/status` — returns lifecycle state + fee breakdown
  - `POST /api/lifecycle/request-restore` — creates SiteLifecycleInvoice
  - `POST /api/lifecycle/confirm-restore` — marks paid + calls lifecycle.restore()

**saas-platform — routes/api.php:**
```php
Route::prefix('lifecycle')->middleware(['throttle:20,1', 'tenant.api'])->group(function () {
    Route::get('/status',           [ApiLifecycleController::class, 'status']);
    Route::post('/reactivate',      [ApiLifecycleController::class, 'reactivate']);
    Route::post('/request-restore', [ApiLifecycleController::class, 'requestRestore']);
    Route::post('/confirm-restore', [ApiLifecycleController::class, 'confirmRestore']);
});
```

**customer-site — Modified:**
- `app/Http/Controllers/Front/SubscriptionPaymentController.php`
  - Added `triggerLifecycleReactivation()` call in `activateSubscription()`
  - On subscription payment success: POST /api/lifecycle/reactivate
  - Also sets local `site_lifecycle_status = 'active'` for instant UI effect

- `app/Console/Commands/ProcessRenewalsCommand.php`
  - Added `reactivateSiteIfSuspended()` at end of `renewFree()`
  - Triggers reactivation for free plan renewals too

- `app/Services/PlatformApiService.php`
  - `requestLifecycleReactivation()` — POST /api/lifecycle/reactivate
  - `getLifecycleStatus()` — GET /api/lifecycle/status
  - `requestLifecycleRestore()` — POST /api/lifecycle/request-restore
  - `confirmLifecycleRestore()` — POST /api/lifecycle/confirm-restore

### Verification
- Subscription payment → `SubscriptionPaymentController::activateSubscription()` → `triggerLifecycleReactivation()` → platform API → `SiteLifecycleService::activate()` → DB status `active` + push to customer-site settings table
- Free renewal → `ProcessRenewalsCommand::renewFree()` → `reactivateSiteIfSuspended()` → same path

---

## PHASE 2 — C04: Customer Self-Service Reactivation

**Certification: C04_SELF_SERVICE_OPERATIONAL**

### Problem
Suspended page was a dead end — no action buttons, customers had no self-service path to restore.

### Solution

**customer-site — New:**
- `app/Http/Controllers/Account/AccountLifecycleController.php`
  - `requestRestore()` — calls platform API, handles free/paid flows
  - `callback()` — payment gateway callback with verify → confirmRestore
  - `manualConfirm()` — fallback when no gateway configured

**customer-site — Modified:**
- `resources/views/front/suspended.blade.php`
  - Added auth-conditional restore buttons:
    - Logged-in: POST form → `/account/lifecycle/restore` + "تمدید اشتراک" link
    - Guest: Login link with redirect parameter

- `app/Http/Middleware/CheckSiteLifecycle.php` — ADMIN_PATHS whitelist:
  - `/account/lifecycle` — self-service restore routes
  - `/account/subscriptions/plans` — plan selection while suspended
  - `/account/subscriptions/subscribe` — subscription while suspended
  - `/subscription/pay` + `/subscription/callback`

- `routes/web.php` — Added lifecycle account routes inside auth group:
  ```php
  Route::prefix('lifecycle')->name('lifecycle.')->group(function () {
      Route::post('/restore',                                [AccountLifecycleController::class, 'requestRestore'])->name('restore');
      Route::get('/restore/callback/{invoiceNumber}',        [AccountLifecycleController::class, 'callback'])->name('callback');
      Route::post('/restore/manual-confirm/{invoiceNumber}', [AccountLifecycleController::class, 'manualConfirm'])->name('manual-confirm');
  });
  ```

- Added lifecycle views:
  - `resources/views/front/lifecycle/restore-pay.blade.php` — manual payment fallback

---

## PHASE 3 — C01: Restore Invoice Payment Flow

**Certification: C01_RESTORE_PAYMENT_OPERATIONAL**

### Problem
`SiteLifecycleInvoice` existed in platform but had no payment UI accessible to customers.
Admin had no way to send a payment link. Customers couldn't pay restore fees independently.

### Solution

**customer-site — New:**
- `app/Http/Controllers/Front/LifecyclePaymentController.php`
  - `show()` — GET /lifecycle-pay/{invoiceRef} — shows fee breakdown
  - `initiate()` — POST — calls requestLifecycleRestore, handles free/paid
  - `callback()` — payment gateway callback
  - `manualConfirm()` — fallback when no gateway
  - Accessible while suspended (whitelisted in CheckSiteLifecycle)

- Views:
  - `resources/views/front/lifecycle/pay.blade.php` — payment page with fee breakdown
  - `resources/views/front/lifecycle/pay-failed.blade.php` — failure page
  - `resources/views/front/lifecycle/already-active.blade.php` — already active redirect

- `routes/web.php` — lifecycle-pay routes (outside auth group, middleware: auth):
  ```php
  Route::prefix('lifecycle-pay')->name('lifecycle.pay.')->middleware('auth')->group(function () {
      Route::get('/{invoiceRef}',                  [LifecyclePaymentController::class, 'show'])->name('show');
      Route::post('/{invoiceRef}',                 [LifecyclePaymentController::class, 'initiate'])->name('initiate');
      Route::get('/callback/{localInvoiceNumber}', [LifecyclePaymentController::class, 'callback'])->name('callback');
      Route::post('/manual/{localInvoiceNumber}',  [LifecyclePaymentController::class, 'manualConfirm'])->name('manual-confirm');
  });
  ```

**saas-platform — Modified:**
- `app/Services/Lifecycle/SiteLifecycleNotificationService.php`
  - `sendRestoreInvoiceSms()` — builds payment URL + sends SMS to customer

- `app/Http/Controllers/Admin/SiteLifecycleController.php`
  - `sendPaymentLink()` — admin action: creates restore invoice + SMS + shows URL

- `routes/web.php` — admin route:
  ```php
  Route::post('sites/{site}/lifecycle/send-payment-link', [SiteLifecycleController::class, 'sendPaymentLink'])
       ->name('sites.lifecycle.send-payment-link');
  ```

---

## PHASE 4 — C05: Physical Storage Release on Archive

**Certification: C05_STORAGE_RELEASE_OPERATIONAL**

### Problem
Archival only reset DB counters. Uploaded files remained on disk indefinitely.
Storage costs continued accumulating for archived/deleted sites.

### Solution

**saas-platform — Modified:**
- `app/Services/Lifecycle/SiteArchiveService.php`
  - `deleteActiveFiles(Site $site): int` — deletes from 'local' + 'public' disks
    - Calculates bytes freed before deletion (best-effort)
    - Deletes `sites/{site_id}/` directory on each disk
    - Records `files_deleted_at` + `files_freed_bytes` on site model
    - Returns bytes freed
  - `resolveUploadPaths(Site $site): array` — returns `['disk' => 'directory']` map
    - Handles optional media disk from `config('filesystems.media_disk')`

- `app/Services/Lifecycle/SiteLifecycleService.php`
  - `archive()` wired with C05:
    ```php
    // C05 — Physical file deletion after ZIP is created
    if ($site->archive_path) {
        $freed = $archiveService->deleteActiveFiles($site);
        Log::info("SiteLifecycleService: C05 freed {$freed} bytes for site {$site->id}");
    } else {
        Log::warning("SiteLifecycleService: C05 skipped — ZIP not confirmed for site {$site->id}");
    }
    ```
  - Guard: only deletes if `archive_path` is set (ZIP created successfully)
  - Wrapped in try-catch so file deletion failure does NOT abort archival

### Safety
- Files deleted AFTER ZIP creation succeeds
- ZIP absence → deletion skipped + warning logged
- Deletion failure → error logged, archival continues (non-fatal)

---

## PHASE 5 — C03: Scheduled SMS Campaign Execution

**Certification: C03_SMS_SCHEDULER_OPERATIONAL**

### Problem
`SmsCampaign` had `scheduled_at` column and scheduler UI but no command actually fired campaigns.
All scheduled campaigns remained in `status='scheduled'` forever.

### Solution

**customer-site — New:**
- `app/Console/Commands/ProcessScheduledSmsCampaignsCommand.php`
  - Signature: `sms:process-scheduled {--dry-run}`
  - Queries `SmsCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())`
  - Per campaign: `DB::transaction()` with `lockForUpdate()` — prevents double-dispatch
  - Atomically transitions `scheduled → running` inside transaction
  - Calls `SmsCampaignService::sendBatch($campaign)` for actual dispatch
  - On failure: updates status to `STATUS_FAILED`

- `bootstrap/app.php` — Scheduler entry:
  ```php
  $schedule->command('sms:process-scheduled')->everyFiveMinutes()->withoutOverlapping(5);
  ```

### Race Condition Prevention
```php
$claimed = DB::transaction(function () use ($campaignId) {
    $locked = SmsCampaign::lockForUpdate()->find($campaignId);
    if (! $locked || $locked->status !== SmsCampaign::STATUS_SCHEDULED) {
        return null;  // Another process already claimed it
    }
    $locked->update(['status' => SmsCampaign::STATUS_RUNNING, 'started_at' => now()]);
    return $locked;
});
```

---

## Files Changed — saas-platform

| File | Type | Purpose |
|------|------|---------|
| `app/Http/Controllers/Api/ApiLifecycleController.php` | NEW | Tenant lifecycle API (C02+C01+C04) |
| `app/Services/Lifecycle/SiteArchiveService.php` | MODIFIED | C05: deleteActiveFiles() |
| `app/Services/Lifecycle/SiteLifecycleService.php` | MODIFIED | C05: wired in archive() |
| `app/Services/Lifecycle/SiteLifecycleNotificationService.php` | MODIFIED | C01: sendRestoreInvoiceSms() |
| `app/Http/Controllers/Admin/SiteLifecycleController.php` | MODIFIED | C01: sendPaymentLink() |
| `routes/api.php` | MODIFIED | C02: /api/lifecycle/* routes |
| `routes/web.php` | MODIFIED | C01: admin send-payment-link route |

## Files Changed — customer-site

| File | Type | Purpose |
|------|------|---------|
| `app/Http/Controllers/Account/AccountLifecycleController.php` | NEW | C04: self-service restore |
| `app/Http/Controllers/Front/LifecyclePaymentController.php` | NEW | C01: direct payment page |
| `app/Console/Commands/ProcessScheduledSmsCampaignsCommand.php` | NEW | C03: scheduler command |
| `app/Services/PlatformApiService.php` | MODIFIED | C02+C01+C04: lifecycle API methods |
| `app/Http/Controllers/Front/SubscriptionPaymentController.php` | MODIFIED | C02: triggerLifecycleReactivation() |
| `app/Console/Commands/ProcessRenewalsCommand.php` | MODIFIED | C02: reactivateSiteIfSuspended() |
| `app/Http/Middleware/CheckSiteLifecycle.php` | MODIFIED | C04+C01: ADMIN_PATHS whitelist |
| `resources/views/front/suspended.blade.php` | MODIFIED | C04: restore buttons |
| `routes/web.php` | MODIFIED | C04+C01: lifecycle routes |
| `bootstrap/app.php` | MODIFIED | C03: scheduler entry |
| `resources/views/front/lifecycle/pay.blade.php` | NEW | C01: payment page |
| `resources/views/front/lifecycle/pay-failed.blade.php` | NEW | C01: failure page |
| `resources/views/front/lifecycle/already-active.blade.php` | NEW | C01: already active page |
| `resources/views/front/lifecycle/restore-pay.blade.php` | NEW | C04: manual payment fallback |
