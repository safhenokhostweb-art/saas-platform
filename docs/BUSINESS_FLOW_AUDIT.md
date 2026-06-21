# BUSINESS FLOW AUDIT
> Full end-to-end workflow validation — simulated real customer scenarios.
> Auditor: Claude Sonnet 4.6 | Date: 2026-06-21 | Scope: Both repos

---

## AUDIT METHODOLOGY

Each flow is traced through:
- Route registration
- Controller action
- Service layer logic
- Model state transitions
- Notification dispatch
- Customer-facing feedback

**Legend:**
- ✅ WORKING — Flow executes correctly end-to-end
- ⚠️ GAP — Flow exists but has a missing step or silent failure path
- ❌ BROKEN — Flow cannot complete; dead-end or missing implementation

---

## FLOW 1: Create Customer Site

**Actor:** Platform Admin  
**Entry:** `/admin/sites/create` or `/admin/sites/wizard`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Open create form | `GET /admin/sites/create` | ✅ | SiteController@create |
| 2. Wizard (step-by-step) | `GET /admin/sites/wizard` | ✅ | Separate wizard blade |
| 3. Fill customer, title, domain | Form validation | ✅ | `unique:sites,domain` enforced |
| 4. Select modules | site.modules sync | ✅ | All active modules shown |
| 5. Assign payment gateway | Schema::hasTable guard | ✅ | Schema-guarded |
| 6. Save site | `POST /admin/sites` → store() | ✅ | Site::create() |
| 7. Module linking | site->modules()->sync() | ✅ | |
| 8. Welcome notification | WelcomeNotificationService | ✅ | If notifications module active |
| 9. Activity log | ActivityLogger::log() | ✅ | |
| 10. Customer SMS on create | — | ❌ **MISSING** | No SMS to customer with site credentials |
| 11. DNS/domain validation | — | ❌ **MISSING** | No DNS check before create |
| 12. DB schema provisioning | — | ⚠️ **GAP** | Stores DB credentials only; no automated customer-site DB setup |
| 13. Post-create checklist | — | ❌ **MISSING** | No "setup checklist" screen after creation |

**Verdict:** PARTIAL — Site is created and logged. Three missing steps prevent a self-contained onboarding flow.

---

## FLOW 2: Assign Theme

**Actor:** Platform Admin  
**Entry:** `/admin/sites/{site}/edit` → theme_id field

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Open site edit | `GET /admin/sites/{site}/edit` | ✅ | themes collection passed to view |
| 2. Select theme from dropdown | Form field | ✅ | Only `published` themes shown |
| 3. Save theme assignment | `PUT /admin/sites/{site}` → update() | ✅ | `sites.theme_id` updated |
| 4. Theme reflected on customer site | — | ⚠️ **GAP** | `sites.theme_id` is not the Canvas theme system. Canvas reads `active_canvas_theme_data` from customer-site settings table, not `sites.theme_id` |
| 5. Platform preview before assign | — | ❌ **MISSING** | No preview of theme on customer site before saving |
| 6. Theme switch notification | — | ❌ **MISSING** | Customer not notified when platform admin assigns a different theme |

**Verdict:** PARTIAL — `sites.theme_id` stores a reference but is decoupled from the live Canvas system. Two separate theme assignment mechanisms exist without a unified bridge.

---

## FLOW 3: Install Theme (Canvas)

**Actor:** Customer Site Admin  
**Entry:** `/admin/theme-marketplace/my-licenses` → Install button

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. View licensed themes | `GET /admin/theme-marketplace/my-licenses` | ✅ | ThemeMarketplaceController@myLicenses |
| 2. Click install | `POST /admin/theme-marketplace/{id}/install` | ✅ | ThemeMarketplaceController@install |
| 3. Download theme pack from platform API | `PlatformApiService::marketplaceDownloadTheme()` | ✅ | Bearer token authenticated |
| 4. Write to settings table | `Setting::set('active_canvas_theme_data')` | ✅ | All 4 keys set |
| 5. Clear resolver cache | `TenantThemeResolver::clearCache()` | ✅ | |
| 6. Site frontend updated | `ThemeCanvasPageController` reads settings | ✅ | 300s cache |
| 7. Backup previous theme before install | — | ❌ **MISSING** | Old `active_canvas_theme_data` overwritten without backup |
| 8. Install rollback on failure | — | ❌ **MISSING** | Partial install leaves site in broken state |
| 9. Install success notification | — | ⚠️ **GAP** | Flash message only; no SMS or notification center entry |
| 10. Draft data cleared after install | — | ⚠️ **GAP** | `canvas_draft_data` NOT cleared; old draft may overwrite new theme if editor opened |

**Critical:** If `active_canvas_theme_data` was already set and install fails at step 4 (e.g., malformed JSON), the old theme data is overwritten but nothing is written. Site goes blank.

**Verdict:** PARTIAL — Core install works. No safety net (no backup, no rollback, draft contamination risk).

---

## FLOW 4: Customer Customization (Canvas Editor)

**Actor:** Customer Site Admin  
**Entry:** `/admin/theme-design/canvas-edit`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Open canvas editor | `GET /admin/theme-design/canvas-edit` | ✅ | Draft seeded from active if empty |
| 2. Select page | JS page list | ✅ | All pages + header/footer virtual pages |
| 3. Drag block from library | SortableJS | ✅ | Drop zones active |
| 4. Edit block props | Right panel | ✅ | 6 field types, TB3-05 |
| 5. Inline text editing | dblclick → contenteditable | ✅ | TB3-04 |
| 6. Per-block styling | Style tab | ✅ | 20+ CSS props |
| 7. Upload media | ThemeMediaController | ✅ | TB3-07 |
| 8. Undo/Redo | 25-entry history | ✅ | Ctrl+Z/Y |
| 9. Save blocks (to draft) | `POST /admin/theme-design/canvas/save-blocks` | ✅ | canvas_draft_data |
| 10. Global styles (colors/font) | `POST .../canvas/save-global-styles` | ✅ | |
| 11. Add/delete pages | AJAX endpoints | ✅ | |
| 12. Publish draft | `POST .../canvas/publish` | ✅ | draft → active |
| 13. Discard draft | `POST .../canvas/discard-draft` | ✅ | |
| 14. Auto-save | — | ❌ **MISSING** | No autosave; browser close loses all unsaved work |
| 15. Revision history | — | ❌ **MISSING** | Cannot roll back to previous published version |
| 16. Responsive preview (mobile/tablet) | Device toggle in editor | ✅ | TB3 Sprint 1 |
| 17. Collaborative editing conflict detection | — | ❌ **MISSING** | Two admins can overwrite each other's drafts |
| 18. Export theme as JSON | `GET /admin/theme-design/canvas/export` | ✅ | |

**Verdict:** WORKING — Core customization loop is solid. Missing: autosave, revision history, multi-user conflict guard.

---

## FLOW 5: Marketplace Purchase

**Actor:** Customer Site Admin  
**Entry:** `/admin/theme-marketplace`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Browse marketplace | `GET /admin/theme-marketplace` | ✅ | Alpine.js filter + search |
| 2. View theme detail | `GET /admin/theme-marketplace/{id}` | ✅ | Hero, pricing, screenshots |
| 3. Click purchase | `POST /admin/theme-marketplace/{id}/purchase` | ✅ | |
| 4. Already-purchased check | `error: already_purchased` | ✅ | |
| 5. Free theme bypass | `free: true` in result | ✅ | Direct to my-licenses |
| 6. Redirect to payment gateway | `redirect_url` from platform | ✅ | |
| 7. Customer pays via Zarinpal/IDPay | External gateway | ✅ | |
| 8. Payment callback | `GET /theme-marketplace/callback` (platform) | ✅ | ThemeMarketplacePaymentController |
| 9. License granted | `ThemeLicense::grant()` | ✅ | |
| 10. Redirect back to customer site | `redirectBack` URL | ✅ | |
| 11. Customer sees my-licenses | `purchased=1` query param | ✅ | Flash message |
| 12. Install from my-licenses | Purchase → Install (Flow 3) | ✅ | |
| 13. Purchase confirmation SMS | — | ❌ **MISSING** | No SMS on license grant |
| 14. Purchase invoice/receipt | — | ❌ **MISSING** | `ThemePurchaseOrder` exists but no PDF invoice |
| 15. License expiry visible | — | ⚠️ **GAP** | No expiry date shown in my-licenses view |
| 16. Payment failure retry | — | ⚠️ **GAP** | `?failed=1` query param only; no guided retry |
| 17. Platform_url not configured | `isConfigured()` → error view | ⚠️ **GAP** | Error message lacks admin fix instructions |

**Verdict:** WORKING (happy path). Missing: purchase SMS, PDF receipt, license expiry display, payment retry guidance.

---

## FLOW 6: Theme Bundle Purchase

**Actor:** Customer Site Admin  
**Entry:** `/admin/theme-bundles`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Browse bundles | `GET /admin/theme-bundles` | ✅ | Featured banner + grid |
| 2. View bundle detail | `GET /admin/theme-bundles/{id}` | ✅ | Pricing sidebar |
| 3. Purchase bundle | `POST /admin/theme-bundles/{id}/purchase` | ✅ | PlatformApiService::purchaseBundle |
| 4. Redirect to payment | | ✅ | |
| 5. Payment callback | `ThemeBundlePaymentCallbackController` | ✅ | Zarinpal + IDPay |
| 6. Batch license grant | `ThemeBundle::grantLicensesToSite()` | ✅ | All bundle themes licensed |
| 7. Redirect to my-licenses | | ✅ | |
| 8. Install each theme individually | — | ⚠️ **GAP** | No "install all" button; must install one by one |
| 9. Bundle purchase invoice/receipt | — | ❌ **MISSING** | No PDF receipt for bundle purchase |
| 10. Partial grant on callback failure | — | ⚠️ **GAP** | Silent failure if neither Zarinpal nor IDPay matches callback |
| 11. Bundle expiry tracking | — | ⚠️ **GAP** | `theme_bundle_orders` has no expiry date column |

**Verdict:** WORKING (happy path). Missing: "install all", PDF receipt, silent callback failure path.

---

## FLOW 7: Resource Governance

**Actor:** Platform Admin / Scheduler  
**Entry:** Daily scheduler + `/admin/resource-governance`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Daily calculation | `resource:calculate` 06:00 | ✅ | SiteResourceCalculatorService |
| 2. Platform overview | `GET /admin/resource-governance` | ✅ | Sort + filter |
| 3. Per-site detail | `GET /admin/resource-governance/{site}` | ✅ | Charts, limits, overage fees |
| 4. Overage fee calculation | `SiteResourceGovernorService::calculateOverageFees()` | ✅ | Computed correctly |
| 5. Overage fee billing | — | ❌ **MISSING** | Overage fees calculated but never invoiced or charged |
| 6. Alerts at 60%/80%/95% thresholds | `SiteResourceAlertService` | ✅ | SMS alerts |
| 7. Throttling uploads at limit | — | ❌ **MISSING** | No enforcement; uploads succeed even when over limit |
| 8. Force recalculate (admin) | `POST /admin/resource-governance/{site}/recalculate` | ✅ | |
| 9. Per-site limit override | `PUT /admin/resource-governance/{site}/limits` | ✅ | |
| 10. Push summary to customer-site | `pushSummaryToCustomerSite()` | ✅ | Dynamic DB connection |
| 11. Push failure recovery | — | ⚠️ **GAP** | Silent failure if customer DB unreachable; no retry |
| 12. Customer resource widget | `/account/resources` | ✅ | Color-coded usage bars |
| 13. Cleanup old snapshots | `resource:cleanup` Sunday 02:00 | ✅ | |
| 14. Archive integration | `releaseActiveResources()` on archive | ✅ | Counters zeroed |
| 15. Physical file deletion | — | ❌ **MISSING** | Only counters reset; actual files remain on disk |

**Verdict:** PARTIAL — Monitoring and alerting work. Critical gap: overage fees are never actually billed, uploads not throttled, files not physically deleted.

---

## FLOW 8: Lifecycle Expiration

**Actor:** Scheduler (daily)  
**Entry:** `lifecycle:check-expired` command

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Check expired sites | `CheckExpiredSitesCommand` daily | ✅ | `host_expires_at` OR `support_expires_at` < now |
| 2. Transition: active → grace_period | `SiteLifecycleService::startGracePeriod()` | ✅ | |
| 3. Set grace_period_ends_at | +LIFECYCLE_GRACE_PERIOD_DAYS | ✅ | Default 15 days |
| 4. SMS to customer | SiteLifecycleNotificationService | ✅ | |
| 5. Platform admin notification | NotificationCenterService | ✅ | |
| 6. Grace period reminder SMS (mid-period) | `gracePeriodReminder()` | ⚠️ **GAP** | Method exists but NO scheduler command calls it |
| 7. Renewal link in SMS | — | ❌ **MISSING** | SMS text is plain; no URL or payment link |
| 8. Mixed-trigger concern | Both host+support expiry trigger same flow | ⚠️ **GAP** | Hosting expiry should not start "subscription grace period" |

**Verdict:** PARTIAL — Expiration detection and grace period start work. Grace period reminders are never sent (dead code). No actionable renewal link in SMS.

---

## FLOW 9: Suspension

**Actor:** Scheduler (daily)  
**Entry:** `lifecycle:check-grace-period` command

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Check grace period ended | `CheckGracePeriodCommand` daily | ✅ | `grace_period_ends_at <= now` |
| 2. Transition: grace_period → suspended | `SiteLifecycleService::suspend()` | ✅ | |
| 3. `is_active = false` | Site model updated | ✅ | |
| 4. `suspended_at` + reason set | | ✅ | |
| 5. Push to customer-site DB | `pushStatusToCustomerSite()` | ✅ | `site_lifecycle_status = suspended` |
| 6. Customer site shows 503 | `CheckSiteLifecycle` middleware | ✅ | suspended.blade.php |
| 7. SMS to customer | `siteSuspended()` | ✅ | |
| 8. Platform admin notification (HIGH) | | ✅ | |
| 9. Customer self-service reactivation from 503 page | — | ❌ **MISSING** | 503 page has no payment/renewal link |
| 10. Admin panel accessible during suspension | CheckSiteLifecycle allows admin paths | ⚠️ **GAP** | Undocumented — intentional or security gap? |
| 11. "Days until archive" countdown shown | — | ❌ **MISSING** | Admin lifecycle panel doesn't show time-to-archive |

**Verdict:** PARTIAL — Suspension works correctly. 503 page is a dead-end for customer (no self-service). Countdown missing from admin overview.

---

## FLOW 10: Archive

**Actor:** Scheduler (daily)  
**Entry:** `lifecycle:check-suspended` command

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Find sites suspended ≥ archive_after_days | `sitesReadyToArchive()` | ✅ | Default 90 days |
| 2. Transition: suspended → archived | `SiteLifecycleService::archive()` | ✅ | |
| 3. `archived_at` set | | ✅ | |
| 4. Create ZIP archive package | `SiteArchiveService::createArchivePackage()` | ✅ | Local disk only |
| 5. ZIP failure logged but not retried | `Log::error()` + continue | ⚠️ **GAP** | No retry queue, no admin alert on ZIP fail |
| 6. Release active resources | `releaseActiveResources()` | ✅ | Counters zeroed |
| 7. Physical files deleted from disk | — | ❌ **MISSING** | Files remain; only counters reset |
| 8. SMS to customer | `siteArchived()` | ✅ | |
| 9. Platform admin notification | | ✅ | |
| 10. ZIP offloaded to cloud storage | — | ❌ **MISSING** | Local disk only; risk of filling server |
| 11. Archive verified/confirmed | — | ❌ **MISSING** | No checksum or verification step after ZIP creation |

**Verdict:** PARTIAL — Archive state machine works. Physical cleanup incomplete (files remain). ZIP not offloaded to cloud. No verification.

---

## FLOW 11: Restore

**Actor:** Platform Admin  
**Entry:** `/admin/sites/{site}/lifecycle` → Restore button

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. View site lifecycle | `GET /admin/sites/{site}/lifecycle` | ✅ | Logs + invoices |
| 2. Force restore (admin) | `POST /admin/sites/{site}/lifecycle/restore` | ✅ | `SiteLifecycleService::restore()` |
| 3. Reset all lifecycle timestamps | archived_at, suspended_at etc. → null | ✅ | |
| 4. `is_active = true` | | ✅ | |
| 5. Transition to ACTIVE logged | `SiteLifecycleLog` | ✅ | |
| 6. Push active to customer-site DB | `pushStatusToCustomerSite()` | ✅ | |
| 7. SMS on restore | `siteRestored()` | ✅ | |
| 8. Request restore (invoice) | `POST .../lifecycle/transition` + createRestoreInvoice | ✅ | Invoice created |
| 9. Customer pays restore invoice | — | ❌ **CRITICAL MISSING** | No payment gateway flow for restore invoice; admin must manually mark paid |
| 10. Customer self-service restore | — | ❌ **CRITICAL MISSING** | No customer-facing restore request button |
| 11. Cache invalidation post-restore | — | ⚠️ **GAP** | Customer-site lifecycle middleware may cache suspended status; no explicit invalidation |
| 12. Post-restore checklist for admin | — | ❌ **MISSING** | No DB restore guide, theme verification prompt |

**Verdict:** PARTIAL — Admin can force-restore. No customer self-service. No payment flow for restore invoice (critical).

---

## FLOW 12: SMS Campaign

**Actor:** Customer Site Admin  
**Entry:** `/admin/sms/campaigns`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Create campaign | `GET /admin/sms/campaigns/create` | ✅ | |
| 2. Set audience type (4 types) | Form | ✅ | all/crm/users/manual |
| 3. Save draft | `POST /admin/sms/campaigns` | ✅ | status=draft |
| 4. Edit campaign | `GET /{campaign}/edit` | ✅ | |
| 5. Manual send | `POST /{campaign}/send` | ✅ | SmsCampaignService::send() |
| 6. Small batch (≤50) inline | SmsService per recipient | ✅ | |
| 7. Large batch (>50) queued | `SendSmsCampaignJob` | ✅ | chunks of 50 |
| 8. Delivery tracking per recipient | `sms_campaign_recipients` | ✅ | pending/sent/failed |
| 9. Campaign progress bar | show.blade.php | ✅ | deliveryRate() |
| 10. Scheduled campaigns auto-trigger | — | ❌ **CRITICAL MISSING** | `scheduled_at` column exists but NO scheduler command checks and triggers scheduled campaigns |
| 11. Job failure → campaign stuck "running" | — | ⚠️ **GAP** | If `SendSmsCampaignJob` fails permanently, campaign stays "running" |
| 12. Duplicate campaign | `POST /{campaign}/duplicate` | ✅ | |
| 13. Cancel campaign | `POST /{campaign}/cancel` | ✅ | |
| 14. Export delivery report (CSV) | — | ❌ **MISSING** | No export functionality |
| 15. Cost estimation before send | — | ❌ **MISSING** | No recipient count or cost preview before sending |

**Verdict:** PARTIAL — Manual send works. Scheduled campaigns are a dead feature (no auto-trigger). Campaigns can get stuck in "running" state.

---

## FLOW 13: Billing Workflow

**Actor:** Customer (subscription), Platform Admin (lifecycle invoices)  
**Entry:** `/subscriptions/plans` + subscription payment flow

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Browse plans | `GET /subscriptions/plans` | ✅ | Public page |
| 2. Subscribe | `POST /subscriptions/subscribe` | ✅ | SubscriptionService |
| 3. Pay invoice | `GET /subscription/pay/{invoiceNumber}` | ✅ | |
| 4. Payment gateway redirect | Zarinpal v4 / IDPay v1.1 | ✅ | |
| 5. Callback → verify → activate | SubscriptionPaymentController | ✅ | |
| 6. Subscription activated | `Subscription::STATUS_ACTIVE` | ✅ | |
| 7. Invoice PDF download | `GET /subscriptions/{id}/invoice/pdf` | ✅ | dompdf RTL |
| 8. Subscription webhook (6 events) | `DispatchWebhookJob` | ✅ | HMAC-signed |
| 9. Auto-renewal (daily 08:00) | `ProcessRenewalsCommand` | ✅ | |
| 10. Free plan → direct renew | | ✅ | |
| 11. Paid plan → invoice + SMS | | ✅ | |
| 12. Upgrade/Downgrade | Prorated credit calc | ✅ | |
| 13. Group subscriptions | invite/accept/remove | ✅ | |
| 14. Dunning (retry on fail) | — | ❌ **MISSING** | No retry logic for failed renewal payments |
| 15. Renewal payment gateway selection | — | ⚠️ **GAP** | ProcessRenewalsCommand creates invoice but doesn't validate gateway is configured |
| 16. Subscription renewal → lifecycle reactivation | — | ❌ **MISSING** | Active subscription renewal does NOT trigger site lifecycle reactivation if site is suspended |
| 17. Theme marketplace invoice | — | ❌ **MISSING** | Marketplace purchase has ThemePurchaseOrder but NO PDF invoice |
| 18. Lifecycle restore invoice PDF | — | ❌ **MISSING** | SiteLifecycleInvoice has no PDF generation |

**Verdict:** PARTIAL — Subscription billing is solid. Critical gap: subscription renewal is decoupled from site lifecycle (paying a renewal doesn't reactivate a suspended site).

---

## FLOW 14: Storage Release

**Actor:** Scheduler (on archive event)  
**Entry:** `SiteLifecycleService::archive()` → `releaseActiveResources()`

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Archive triggers release | `archive()` calls `releaseActiveResources()` | ✅ | |
| 2. Reset counters to 0 | storage_bytes, db_bytes, file_count → 0 | ✅ | archive_bytes preserved |
| 3. Log release | | ✅ | Warning if fails |
| 4. Actual file deletion from upload disk | — | ❌ **CRITICAL MISSING** | Files remain on disk; only DB counters reset |
| 5. Customer DB cleanup | — | ❌ **MISSING** | No DROP TABLE or DB purge for archived sites |
| 6. Archive ZIP offload to S3 | — | ❌ **MISSING** | ZIP on local disk only |
| 7. Disk space recovered | — | ❌ **MISSING** | Server disk space not actually freed |
| 8. Post-restore resource recalculation | — | ⚠️ **GAP** | After restore, counters are still 0 until daily resource:calculate runs |
| 9. Lifecycle delete → actual data purge | — | ❌ **MISSING** | `delete()` only sets `lifecycle_deleted_at`; no file/DB cleanup |

**Verdict:** BROKEN — Storage counters are reset but no actual disk space is recovered. The "storage release" is accounting-only.

---

## FLOW 15: Re-activation

**Actor:** Platform Admin (or customer, ideally)  
**Entry:** Admin → `/admin/sites/{site}/lifecycle` → Restore

| Step | Route/Action | Status | Notes |
|------|-------------|--------|-------|
| 1. Admin force-restores | `forceRestore()` | ✅ | No payment check |
| 2. Site reactivated in platform DB | `activate()` | ✅ | |
| 3. Status pushed to customer DB | `pushStatusToCustomerSite()` | ✅ | |
| 4. Customer site front-end unblocked | `CheckSiteLifecycle` clears 503 | ✅ | |
| 5. Canvas theme data restored | — | ⚠️ **GAP** | If customer-site DB was wiped/recreated, theme data is gone and cannot be restored from ZIP |
| 6. Resource counters recalculated | — | ⚠️ **GAP** | Daily scheduler only; counters show 0 until next morning |
| 7. Subscription also reactivated | — | ❌ **MISSING** | Reactivation does not also reactivate a cancelled/expired subscription |
| 8. Customer self-service reactivation | — | ❌ **CRITICAL MISSING** | No customer-facing interface to request reactivation |
| 9. Reactivation fee payment flow | — | ❌ **CRITICAL MISSING** | Invoice exists but no payment gateway integration |
| 10. Post-reactivation checklist | — | ❌ **MISSING** | No admin checklist (check DB, theme, modules, queue workers) |
| 11. Lifecycle invoice PDF | — | ❌ **MISSING** | SiteLifecycleInvoice has no PDF or download route |
| 12. Admin notification after self-service request | — | ❌ **MISSING** | If customer requested restore, admin has no notification |

**Verdict:** PARTIAL — Admin can reactivate. No customer self-service. No payment flow for the reactivation fee. Lifecycle invoice has no PDF or payment route.

---

## CROSS-FLOW ISSUES

### Issue A: Subscription ↔ Lifecycle Decoupling
A customer can renew their subscription while the site is in `suspended` state — this renewal does NOT trigger site reactivation. The two systems are completely independent.

### Issue B: Two Theme Systems Coexist Without Bridge
- Classic: `sites.theme_id` (platform assigns)
- Canvas: `active_canvas_theme_data` in customer-site settings (customer installs from marketplace)
These are not connected. A platform admin assigning `theme_id` has no effect on the Canvas theme the customer sees.

### Issue C: Storage Release Is Accounting-Only
Archiving resets DB counters but does not reclaim actual disk space. A platform with many archived sites will continue filling disk until manual intervention.

### Issue D: Scheduled SMS Campaigns Are Dead Code
`SmsCampaign.scheduled_at` column exists and UI allows setting it, but no scheduler command fires campaigns at that time. All campaigns must be manually sent.

### Issue E: Restore Invoice Has No Payment Path
`SiteLifecycleService::requestRestore()` creates a `SiteLifecycleInvoice` but:
- No route exists for customer to pay it
- No integration with Zarinpal/IDPay
- Admin must mark it paid manually via `markInvoicePaid()`

---

## SUMMARY TABLE

| Flow | Status | Critical Gaps |
|------|--------|--------------|
| 1. Create Site | PARTIAL | No customer SMS, no DNS check, no DB provisioning |
| 2. Assign Theme | PARTIAL | Two disconnected theme systems |
| 3. Install Theme | PARTIAL | No backup, no rollback, draft contamination |
| 4. Customer Customization | WORKING | Missing autosave, revision history |
| 5. Marketplace Purchase | WORKING | No SMS receipt, no PDF invoice, no retry |
| 6. Bundle Purchase | WORKING | No "install all", no invoice |
| 7. Resource Governance | PARTIAL | Overage never billed, no file deletion, no throttle |
| 8. Lifecycle Expiration | PARTIAL | No grace period reminders, no renewal link in SMS |
| 9. Suspension | PARTIAL | 503 page is dead-end, no countdown |
| 10. Archive | PARTIAL | Files not deleted, ZIP not offloaded, no verification |
| 11. Restore | PARTIAL | **No customer payment flow for restore invoice** |
| 12. SMS Campaign | PARTIAL | **Scheduled campaigns never fire** |
| 13. Billing | PARTIAL | **Renewal doesn't reactivate suspended site** |
| 14. Storage Release | BROKEN | **Accounting only — disk not freed** |
| 15. Re-activation | PARTIAL | **No self-service, no payment flow** |

---

*End of BUSINESS_FLOW_AUDIT.md*
