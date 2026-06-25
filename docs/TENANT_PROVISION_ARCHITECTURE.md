# TENANT PROVISION WIZARD ARCHITECTURE
> Date: 2026-06-26
> Status: FINAL — ARCHITECTURE DECIDED
> Entry: /admin/sites/provision

---

## Overview

The Tenant Provision Wizard automates the creation of a new customer site from a single
admin form. It replaces the current manual workflow (create site → configure settings →
send SMS manually → initialize resource limits individually).

**Builds on existing services:** SiteLifecycleService, SiteResourceGovernorService,
ExpiryReminderService, SmsService — all operational. No new infrastructure required.

**Constraint:** Must remain compatible with the current SCP-based deploy.ps1 workflow
and existing production topology (single VPS, shared DB).

---

## Current Manual Workflow (being replaced)

```
Admin: POST /admin/sites (create site)
Admin: manually set subscription plan
Admin: manually set resource limits
Admin: manually send welcome SMS
Admin: manually trigger ExpiryReminderService
Admin: wait for scheduler to pick up monitoring
```

**Problems:** 6 separate actions, no validation between steps, easy to forget any step,
no progress visibility, new tenants go live without proper initialization.

---

## Provision Wizard — Complete Workflow

**Entry:** `GET /admin/sites/provision` — 6-step wizard modal.
**Alternative:** `php artisan tenant:provision` (CLI equivalent, same logic).

### Step 1 — Customer Information

**Inputs:**

| Field | Type | Validation |
|-------|------|------------|
| site_name | string | required, min:3, max:100 |
| owner_first_name | string | required |
| owner_last_name | string | required |
| owner_mobile | string | required, Iranian mobile format: `09[0-9]{9}` |
| owner_email | string | required, valid email, unique in `users` table |

**Logic:**
- `sites.name` ← site_name
- A `User` is created in `saas_platform.users` as the site owner
- User: `first_name`, `last_name`, `mobile`, `email`, `is_admin = false`, temporary password generated
- Temporary password is shown to admin once (not sent to owner — that's Step 6 SMS)

**Forbidden:** Do NOT write to `users.name` column (USERS-NAME-001).

---

### Step 2 — Domain Assignment

**Inputs:**

| Field | Type | Validation |
|-------|------|------------|
| domain_type | enum | 'subdomain' or 'custom' |
| domain | string | required, unique in `sites.domain`, valid FQDN format |
| ssl_enabled | boolean | default: true |

**Sub-cases:**

**Subdomain** (`type = 'subdomain'`):
- Domain format: `{slug}.safhenokhost.ir`
- Validation: slug is URL-safe, not already taken
- No Nginx changes needed — wildcard Nginx config already handles `*.safhenokhost.ir`
- Site is immediately accessible after provision

**Custom domain** (`type = 'custom'`):
- Domain: any FQDN (e.g., `client-business.ir`)
- Wizard informs admin: "پس از تأیید، DNS مشتری را به IP `87.107.12.81` تنظیم کنید"
- Platform records domain in `sites.domain`
- Nginx config update is NOT automated in v1 — admin must update Nginx manually post-provision
- This is a known operational limitation; automated Nginx update is post-launch roadmap

---

### Step 3 — Plan & Billing

**Inputs:**

| Field | Type | Validation |
|-------|------|------------|
| plan_id | integer | required, must exist in `subscription_plans` where `is_active = true` |
| trial_days | integer | optional, 0–30, default: 0 |
| billing_start_at | date | optional, default: today |

**Logic:**
- Create `subscriptions` record: `site_id`, `plan_id`, `status = active`
- `started_at = billing_start_at`, `expires_at = billing_start_at + plan.duration_days + trial_days`
- `SiteLifecycleService::activate()` called → pushes `lifecycle_status = active` to customer-site settings

**Plan display:** Plan cards show name, price, duration, feature list, storage/bandwidth limits.

---

### Step 4 — Theme Selection

**Inputs:**

| Field | Type | Validation |
|-------|------|------------|
| theme_id | integer | optional (nullable), must exist in `theme_marketplace` where `is_active = true` |
| skip_theme | boolean | if true, theme_id is ignored |

**Logic (if theme selected):**
- Create `theme_licenses` record: `site_id`, `theme_id`, `licensed_at = now()`
- Write `active_canvas_theme_data` to customer-site `settings` table for this site_id
- Write `active_canvas_theme_name`, `active_canvas_theme_slug`, `active_canvas_theme_id`
- This reuses the existing `ThemeCanvasController::installToSite()` logic (same 4-field write)

**Logic (if skipped):**
- No theme installed; customer sees default blank canvas on first login
- Admin can install theme later via `/admin/theme-marketplace`

---

### Step 5 — Hosting & Resource Allocation

**Inputs:**

| Field | Type | Validation |
|-------|------|------------|
| storage_limit_mb | integer | default from plan, max cap enforced by plan |
| bandwidth_limit_gb | integer | default from plan |
| hosting_expires_at | date | required, cannot be in the past |

**Logic:**
- `SiteResourceGovernorService::setLimits($siteId, $limits)` — sets `site_resource_limits` row
- `ExpiryReminderService::syncForSite($siteId)` — creates/updates `platform_site_notifications` row for expiry
- `SiteController::syncExpiryNotifications($site)` — ensures notification is initialized immediately
  (does not wait for daily scheduler)

**Hosting expiry display:**
- Wizard pre-fills `hosting_expires_at` = today + 365 days
- Admin can change to any future date
- Customer will see the expiry widget on their dashboard immediately after provision

---

### Step 6 — Welcome & Finalization

**No additional inputs.** This step is a summary + execution confirmation.

**Display:** Summary card showing all inputs from Steps 1–5.

**On confirm — execution sequence:**

```
1. DB Transaction BEGIN
   a. Insert sites record
   b. Insert/update user record (site owner)
   c. Insert subscription record
   d. Insert site_resource_limits record
   e. Insert theme_licenses record (if theme selected)
   f. Insert infra_backup_runs initialization record
DB Transaction COMMIT

2. Outside transaction (side effects):
   g. SiteLifecycleService::activate() — push settings to customer-site DB
   h. ExpiryReminderService::syncForSite() — create expiry notification
   i. SmsService::send($mobile, $welcomeMessage) — welcome SMS to owner
   j. Cache::tags(['sites'])->flush() — clear site list cache
   k. Log activity: 'tenant_provision' in activity_logs
   l. Slack alert (info): "New tenant provisioned: {domain}"
```

**Why steps g–l are outside transaction:**
- `SiteLifecycleService` writes to customer-site DB (different DB connection)
- SMS delivery is external; failure should not roll back the tenant creation
- If any of g–l fails, the tenant is still created (DB transaction committed)
- Admin sees warning for any failed side effect

### Failure Handling

| Failure Point | Behavior |
|---------------|----------|
| DB transaction fails | Full rollback; no partial state; show error to admin |
| SiteLifecycleService::activate() fails | Tenant created; warning shown: "سایت ایجاد شد اما تنظیمات اولیه push نشد — لطفاً از صفحه lifecycle این سایت activate کنید" |
| SMS fails | Tenant created; warning shown: "پیامک خوشامدگویی ارسال نشد — لطفاً دستی ارسال کنید" |
| Theme install fails | Tenant created without theme; warning shown |

---

## Service Layer Design

### TenantProvisioningService (NEW)

**Location:** `app/Services/TenantProvisioningService.php`

**Depends on (all existing):**
- `SiteLifecycleService`
- `SiteResourceGovernorService`
- `ExpiryReminderService`
- `SmsService`
- `ArvanBackupService` (for backup init record)

**Methods:**

```
provision(ProvisionData $data): ProvisionResult
  — Orchestrates all 6 steps
  — Returns: site_id, domain, temp_password, warnings[]

validateDomain(string $domain): bool
  — Checks uniqueness in sites.domain
  — Validates FQDN format

generateTempPassword(): string
  — 12-char alphanumeric (no symbols — for SMS compatibility)

buildWelcomeSms(Site $site, string $password): string
  — Returns Persian SMS text with domain + credentials
```

### ProvisionData (Value Object)

```
site_name
owner_first_name, owner_last_name, owner_mobile, owner_email
domain_type, domain
plan_id, trial_days, billing_start_at
theme_id (nullable)
storage_limit_mb, bandwidth_limit_gb, hosting_expires_at
```

### ProvisionResult (Value Object)

```
site_id
domain
temp_password
warnings[]    — non-fatal issues (SMS failed, theme install failed, etc.)
provision_log — serialized log of each step's outcome
```

---

## CLI Equivalent

```bash
php artisan tenant:provision \
  --name="نام سایت" \
  --domain="client.safhenokhost.ir" \
  --plan=starter \
  --mobile=09123456789 \
  --email=owner@client.ir \
  --hosting-expires=2027-06-26
```

CLI output: step-by-step progress, final summary with temp password.
Same `TenantProvisioningService::provision()` call — no duplicate logic.

---

## Security Constraints

1. **Temp password shown once** — only in the wizard success screen; not stored in DB in plaintext
2. **No direct DB write to customer-site from provision wizard** — only via `SiteLifecycleService`
   which uses the existing cross-DB connection (respects multi-tenant boundaries)
3. **Domain uniqueness enforced at DB level** — `sites.domain` has unique index (existing constraint)
4. **Resource limits capped by plan** — `SiteResourceGovernorService` enforces plan maximums;
   admin cannot allocate more than plan allows
5. **Provision route is `is_platform_admin` only** — regular admin users cannot provision tenants

---

## Compatibility with Current Topology

This wizard requires NO changes to:
- `deploy.ps1` — provision is a runtime operation, not a deployment operation
- Nginx configuration — subdomain wildcard already works; custom domains are manual (documented)
- `customer_site` DB schema — `SiteLifecycleService::activate()` already handles cross-DB push
- Arvan backup — `infra_backup_runs` record initializes tenant in backup tracking

No new DB tables required. All writes use existing tables.

---

*TENANT_PROVISION_ARCHITECTURE — generated 2026-06-26 — FINAL*
