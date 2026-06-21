# BUSINESS FLOW GAP ANALYSIS
> Derived from BUSINESS_FLOW_AUDIT.md — 2026-06-21
> All gaps classified by severity, impact, and required fix.

---

## SEVERITY SCALE

| Level | Definition |
|-------|-----------|
| **CRITICAL** | Business-blocking; a real customer cannot complete the flow; data loss risk |
| **HIGH** | Significant friction or money left on the table; platform commitment not delivered |
| **MEDIUM** | Degraded UX or missing convenience; workaround exists |
| **LOW** | Polish or nice-to-have; no business impact |

---

## CRITICAL GAPS (Business Blocking)

### GAP-C01 — Restore Invoice Has No Payment Flow
**Flow:** 11 (Restore), 15 (Re-activation)  
**Evidence:** `SiteLifecycleService::requestRestore()` creates `SiteLifecycleInvoice` with `STATUS_PENDING`. No route, no controller action, no payment gateway integration allows the customer (or anyone) to pay this invoice. Only admin can call `markInvoicePaid()` manually.  
**Impact:** Reactivation fee (configured in `lifecycle.reactivation_fee`) can never be collected through the platform. Revenue loss. Admin must track payment externally.  
**Required Fix:**
1. Add `GET /account/lifecycle/restore-invoice/{invoice}` → show restore invoice to customer
2. Add `POST /account/lifecycle/restore-invoice/{invoice}/pay` → redirect to payment gateway
3. Wire Zarinpal/IDPay callback → `SiteLifecycleService::restore()` on verified payment
4. Add PDF download for `SiteLifecycleInvoice`

**Regression Risk:** SiteLifecycleInvoice model, SiteLifecycleController, payment callback controllers

---

### GAP-C02 — Subscription Renewal Does NOT Reactivate Suspended Site
**Flow:** 13 (Billing), 15 (Re-activation)  
**Evidence:** `ProcessRenewalsCommand` runs daily, creates invoices, activates subscriptions. There is zero code path connecting `Subscription::STATUS_ACTIVE` to `SiteLifecycleService::activate()`. A customer can renew their subscription and still see the 503 page.  
**Impact:** Customer pays for renewal and their site remains suspended. Support escalation guaranteed. Churn risk.  
**Required Fix:**
1. In `SubscriptionService::activate()` (or `ProcessRenewalsCommand`), after activation:
   ```php
   if ($site->lifecycle_status !== Site::LIFECYCLE_ACTIVE) {
       app(SiteLifecycleService::class)->activate($site, 'اشتراک تمدید شد');
   }
   ```
2. Also wire payment callback (`SubscriptionPaymentController`) to check lifecycle state

**Regression Risk:** SubscriptionService, ProcessRenewalsCommand, SubscriptionPaymentController, 10+ subscription tests

---

### GAP-C03 — Scheduled SMS Campaigns Never Auto-Fire
**Flow:** 12 (SMS Campaign)  
**Evidence:** `SmsCampaign` has `scheduled_at` column. `SmsCampaignController` shows `scheduled` as a valid status. The campaign creation UI allows setting a scheduled time. But there is NO `Kernel.php` command, NO scheduler entry, NO command class that queries `WHERE status = 'scheduled' AND scheduled_at <= NOW()` and fires the campaign.  
**Impact:** Every scheduled campaign silently never runs. Admin thinks it's queued; customer never receives SMS. Feature is completely non-functional.  
**Required Fix:**
1. Create `app/Console/Commands/ProcessScheduledSmsCampaignsCommand.php`
2. Query: `SmsCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()`
3. For each: call `SmsCampaignService::send($campaign)`
4. Register in `Kernel.php`: `->everyMinute()` or `->everyFiveMinutes()`

**Regression Risk:** SmsCampaign model, SendSmsCampaignJob, existing campaign tests

---

### GAP-C04 — No Customer Self-Service Reactivation
**Flow:** 9 (Suspension), 11 (Restore), 15 (Re-activation)  
**Evidence:** The suspended.blade.php 503 page has no button, link, or form allowing the customer to request reactivation. The archived site has no customer portal access. Customers must contact support via SMS/phone.  
**Impact:** Every suspension/archival requires manual admin intervention. No self-service revenue path. Poor customer experience.  
**Required Fix:**
1. Add "درخواست بازگردانی" button on `suspended.blade.php` (POST to `/account/lifecycle/request-restore`)
2. Create `AccountLifecycleController::requestRestore()` — calls `SiteLifecycleService::requestRestore()`, creates invoice, shows payment link
3. Wire to GAP-C01 payment flow
4. Notify platform admin via notification center on request

**Regression Risk:** CheckSiteLifecycle middleware (must allow POST to restore endpoint while suspended), SiteLifecycleService

---

### GAP-C05 — Storage Release Is Accounting-Only (Files Remain on Disk)
**Flow:** 10 (Archive), 14 (Storage Release)  
**Evidence:** `SiteResourceCalculatorService::releaseActiveResources()` only updates `site_resource_usage` table columns to zero. No file system operation occurs. No `Storage::deleteDirectory()` is called. Server disk fills up with archived site files.  
**Impact:** With a growing number of archived sites, server disk fills. Performance degrades. At scale, this is a production-critical disk exhaustion risk.  
**Required Fix:**
1. After `releaseActiveResources()` in `SiteLifecycleService::archive()`:
   ```php
   app(SiteArchiveService::class)->deleteActiveFiles($site);
   ```
2. `deleteActiveFiles()`: delete `storage/app/sites/{site_id}/` directory (or equivalent upload path)
3. Optional: offload ZIP to S3 via Storage facade before deleting local copy
4. For lifecycle_deleted: also trigger customer DB cleanup or DROP TABLE cascade

**Regression Risk:** SiteArchiveService, storage paths, SmartImageUploadService, StorageManager

---

## HIGH GAPS

### GAP-H01 — Two Theme Systems Without Bridge
**Flow:** 2 (Assign Theme), 3 (Install Theme)  
**Evidence:** `sites.theme_id` (classic theme assignment by platform admin) and `active_canvas_theme_data` in customer-site settings (Canvas theme installed from marketplace) are completely separate. Assigning `theme_id` in platform admin does NOT change what the customer's frontend renders.  
**Impact:** Platform admin "assigns" a theme but customer sees something different. Confusing UX for both admin and customer.  
**Required Fix:**
1. Either: deprecate `sites.theme_id` and centralize on Canvas, OR
2. When platform admin saves `theme_id`, also push the ThemePack data to customer-site via `pushStatusToCustomerSite()` pattern
3. Document which system takes precedence

---

### GAP-H02 — Theme Install Has No Backup/Rollback
**Flow:** 3 (Install Theme)  
**Evidence:** `ThemeMarketplaceController::install()` overwrites `active_canvas_theme_data` directly. Old theme data is lost. If install fails midway (e.g., `marketplaceDownloadTheme()` returns partial data), site goes blank.  
**Impact:** Theme install is a destructive operation with no safety net. Customer can lose their entire site customization.  
**Required Fix:**
1. Before overwriting: `$backup = Setting::get('active_canvas_theme_data'); Setting::set('previous_canvas_theme_data', $backup, 'theme');`
2. Expose "Revert to previous theme" button in `/admin/theme-design/canvas`
3. Clear `canvas_draft_data` on install to prevent old draft contaminating new theme

---

### GAP-H03 — Grace Period Reminder Never Sent
**Flow:** 8 (Lifecycle Expiration)  
**Evidence:** `SiteLifecycleNotificationService::gracePeriodReminder()` is implemented and sends SMS. However, no scheduler command calls it. The `CheckGracePeriodCommand` only checks if grace period ended (→ suspend). No mid-period reminder is dispatched.  
**Impact:** Customer receives one SMS at grace start, then silence until suspension. Conversion to renewal is significantly lower without reminders.  
**Required Fix:**
1. In `CheckGracePeriodCommand` (or new command), add:
   ```php
   $remindAt = (int)(config('lifecycle.grace_period_days', 15) / 2); // halfway reminder
   $sites = Site::where('lifecycle_status', 'grace_period')
       ->where('grace_period_started_at', '<=', now()->subDays($remindAt))
       ->whereNull('grace_reminder_sent_at')
       ->get();
   ```
2. Send reminder, set `grace_reminder_sent_at` (add column to sites migration)

---

### GAP-H04 — Overage Fees Never Billed
**Flow:** 7 (Resource Governance)  
**Evidence:** `SiteResourceGovernorService::calculateOverageFees()` correctly computes storage and bandwidth overage fees with per-GB rates. These values are displayed in the admin panel. But no billing action is taken — no invoice created, no SMS sent, no subscription impact.  
**Impact:** Revenue leakage. Customers who exceed storage/bandwidth pay nothing extra despite overage fee config.  
**Required Fix:**
1. Create `BillResourceOverageCommand` (monthly)
2. For each site with overage: create `SiteLifecycleInvoice` type `overage` with line items
3. Send invoice SMS to customer
4. Admin notification

---

### GAP-H05 — No Dunning Management for Failed Renewals
**Flow:** 13 (Billing)  
**Evidence:** `ProcessRenewalsCommand` for paid plans creates an invoice and sends SMS. But if customer doesn't pay, nothing happens on day 2, 3, 7. No retry, no escalation, no grace period trigger.  
**Impact:** Subscription churn from failed payments. No automated recovery path.  
**Required Fix:**
1. Track unpaid renewal invoices with `due_date`
2. Send reminder SMS at day 3, day 7
3. At day 14 unpaid: trigger `SiteLifecycleService::startGracePeriod()`

---

### GAP-H06 — Marketplace + Bundle Purchase: No PDF Invoice
**Flow:** 5 (Marketplace), 6 (Bundle)  
**Evidence:** `ThemePurchaseOrder` and `ThemeBundleOrder` models exist with amount_rial and status. But there is no PDF invoice generation for marketplace or bundle purchases. Subscription invoices have PDF (dompdf), but theme purchases do not.  
**Impact:** Customers cannot get receipts for theme purchases. VAT compliance risk.  
**Required Fix:**
1. Add `GET /admin/theme-marketplace/my-licenses/{order}/invoice` route
2. Reuse existing dompdf invoice template with ThemePurchaseOrder data
3. Same for bundle orders

---

### GAP-H07 — Upload Not Throttled When Site Exceeds Storage Limit
**Flow:** 7 (Resource Governance)  
**Evidence:** `SiteResourceGovernorService` calculates limits and overage but `SmartImageUploadService` has no check against site storage limits. Uploads succeed regardless of limit.  
**Impact:** Storage enforcement is advisory only. Sites can grow without bound.  
**Required Fix:**
1. In `SmartImageUploadService::upload()`:
   ```php
   $usage = SiteResourceUsage::where('site_id', $siteId)->first();
   $limit = SiteResourceLimit::defaultForSite($site);
   if ($usage && $usage->storage_bytes >= $limit->storage_limit_bytes) {
       throw new StorageLimitExceededException();
   }
   ```

---

## MEDIUM GAPS

### GAP-M01 — Canvas Editor Has No Autosave
**Flow:** 4 (Customer Customization)  
**Impact:** All unsaved work lost on browser close or crash.  
**Fix:** Auto-save to draft every 60 seconds via `setInterval(() => saveBlocks(), 60000)`.

---

### GAP-M02 — No Published Theme Revision History
**Flow:** 4 (Customer Customization)  
**Impact:** Cannot roll back to a previous published state. One bad publish wipes the theme.  
**Fix:** Before each `canvasPublish()`, copy current active to `canvas_revision_{n}` in settings with 5-revision cap.

---

### GAP-M03 — Suspended Site 503 Page Is a Dead End
**Flow:** 9 (Suspension)  
**Impact:** Customer sees 503 but has no payment link or support contact.  
**Fix:** Add support phone/Telegram contact + "پرداخت و فعال‌سازی مجدد" link to suspended.blade.php.

---

### GAP-M04 — Bundle: No "Install All" Button
**Flow:** 6 (Bundle Purchase)  
**Impact:** After bundle purchase, user must install each theme individually from my-licenses.  
**Fix:** Add "نصب قالب پیش‌فرض" button on bundle success page that installs the first/featured theme automatically.

---

### GAP-M05 — Draft Contamination After Theme Install
**Flow:** 3 (Install Theme)  
**Impact:** If old `canvas_draft_data` exists when new theme is installed, opening the editor loads the old draft, not the new theme's content.  
**Fix:** In `ThemeMarketplaceController::install()`, add `Setting::set('canvas_draft_data', '', 'theme')`.

---

### GAP-M06 — Admin Panel Accessible During Suspension (Undocumented)
**Flow:** 9 (Suspension)  
**Impact:** Unclear if this is a feature (admin access) or a gap (admin should also be blocked). Security/policy ambiguity.  
**Fix:** Document explicitly in `CheckSiteLifecycle` middleware. If intentional, add a comment in the blade suspended page for admins.

---

### GAP-M07 — Platform URL Not Configured: No Admin Fix Path
**Flow:** 5 (Marketplace Purchase)  
**Evidence:** `PlatformApiService::isConfigured()` returns false if `PLATFORM_URL` env is not set. Customer site shows "اتصال به پلتفرم پیکربندی نشده است" with no instructions.  
**Fix:** Add admin-only notice: "برای اتصال به مارکت‌پلیس، مقدار PLATFORM_URL را در تنظیمات محیط تعریف کنید."

---

### GAP-M08 — Resource Push to Customer-Site: Silent Failure
**Flow:** 7 (Resource Governance)  
**Evidence:** `pushSummaryToCustomerSite()` has `Log::warning()` on failure. No retry, no alert.  
**Fix:** Add platform admin notification via `NotificationCenterService` on push failure.

---

### GAP-M09 — Site Create: No Customer SMS with Site Info
**Flow:** 1 (Create Site)  
**Impact:** Customer not notified when their site is provisioned.  
**Fix:** After `Site::create()`, send SMS to customer: "سایت {title} ایجاد شد. آدرس دسترسی: {domain}"

---

### GAP-M10 — Lifecycle Delete: No Physical Cleanup
**Flow:** 14 (Storage Release)  
**Evidence:** `SiteLifecycleService::delete()` sets `lifecycle_deleted_at` and logs. No file deletion, no DB drop.  
**Fix:** Create `SiteDeleteService::purge($site)` that deletes upload directory and (optionally) drops customer DB.

---

## LOW GAPS

### GAP-L01 — License Expiry Not Shown in My-Licenses
**Flow:** 5 (Marketplace)  
**Fix:** Add `expires_at` to `ThemeLicense` API response and show in my-licenses view.

---

### GAP-L02 — Payment Failure: No Retry Guidance
**Flow:** 5 (Marketplace), 6 (Bundle)  
**Fix:** On `?failed=1`, show specific error + "تلاش مجدد" button that re-initiates purchase.

---

### GAP-L03 — Campaign Delivery Report: No CSV Export
**Flow:** 12 (SMS Campaign)  
**Fix:** Add `GET /{campaign}/export` → CSV with recipient mobile, status, sent_at, error_message.

---

### GAP-L04 — SMS Campaign Stuck in "Running" on Job Failure
**Flow:** 12 (SMS Campaign)  
**Fix:** In `SendSmsCampaignJob::failed()` callback, set campaign status to `failed`.

---

### GAP-L05 — Admin Lifecycle Page: No "Days Until Archive" Countdown
**Flow:** 9 (Suspension)  
**Fix:** In `overview.blade.php`, compute `$suspendDays = $site->suspended_at ? $site->suspended_at->diffInDays(now()) : 0` and show remaining days before archive.

---

### GAP-L06 — Archive ZIP Not Verified After Creation
**Flow:** 10 (Archive)  
**Fix:** After `SiteArchiveService::createArchivePackage()`, check `Storage::exists($zipPath)` and `Storage::size($zipPath) > 0`. Log CRITICAL if failed.

---

### GAP-L07 — SMS Contains No Renewal/Payment Link
**Flow:** 8 (Lifecycle Expiration)  
**Fix:** Include `route('subscriptions.plans')` URL in grace period SMS text.

---

### GAP-L08 — Post-Restore Resource Counters Show Zero Until Daily Job
**Flow:** 15 (Re-activation)  
**Fix:** After `SiteLifecycleService::restore()`, dispatch `resource:calculate --site={id}` as queued job.

---

### GAP-L09 — Site Create: No Post-Create Checklist
**Flow:** 1 (Create Site)  
**Fix:** After site creation, show "چک‌لیست راه‌اندازی" page: DNS config, DB setup, module activation, theme assignment, admin user creation.

---

### GAP-L10 — Collaborative Canvas Editing: No Conflict Detection
**Flow:** 4 (Customer Customization)  
**Fix:** Store `canvas_editor_locked_by` in settings. On `canvasEdit()`, check if another user holds lock.

---

## PRIORITIZED FIX PLAN

| Priority | Gap | Effort | Revenue/UX Impact |
|----------|-----|--------|-------------------|
| 1 | GAP-C02 — Renewal reactivates site | 2h | Critical — prevents churn |
| 2 | GAP-C03 — Schedule SMS campaign command | 3h | Critical — broken feature |
| 3 | GAP-C01 — Restore invoice payment flow | 6h | Critical — revenue |
| 4 | GAP-C04 — Customer self-service restore | 4h | Critical — UX |
| 5 | GAP-C05 — Physical file deletion on archive | 4h | Critical — disk safety |
| 6 | GAP-H03 — Grace period reminder SMS | 2h | High — conversion |
| 7 | GAP-H02 — Theme install backup/rollback | 3h | High — data safety |
| 8 | GAP-M05 — Clear draft on theme install | 0.5h | Medium — UX |
| 9 | GAP-L04 — Campaign stuck in running | 1h | Low — reliability |
| 10 | GAP-L07 — SMS renewal link | 0.5h | Low — conversion |

---

## TOTAL GAP COUNT

| Severity | Count |
|----------|-------|
| CRITICAL | 5 |
| HIGH | 7 |
| MEDIUM | 10 |
| LOW | 10 |
| **TOTAL** | **32** |

---

*End of BUSINESS_FLOW_GAP_ANALYSIS.md*
