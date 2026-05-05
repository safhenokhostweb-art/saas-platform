# Enterprise Licensed Website Builder Ecosystem — Design (Iranian Market)

This document describes the full architecture for an enterprise licensed website builder ecosystem using Laravel, targeting the Iranian market. It builds on the existing **saas-platform** (core business platform) and **customer-site** (customer site template) and adds license server, update server, one-time purchase, paid support, module marketplace, and reseller system.

---

## 1. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         SAAS-PLATFORM (Core Business Platform)                    │
│  • Admin panel (customers, sites, modules, forms, settings)                       │
│  • License Server API (issue/validate licenses)                                  │
│  • Update Server API (distribute versions, changelog)                            │
│  • Module Marketplace (products, pricing, delivery)                              │
│  • Support plans & subscriptions (paid support)                                   │
│  • Reseller management (commissions, white-label)                                │
│  • Payment gateway integration (IRR, Zarinpal, IDPay, etc.)                      │
└─────────────────────────────────────────────────────────────────────────────────┘
                    │                    │                    │
                    │ License/Config     │ Updates            │ Marketplace
                    ▼                    ▼                    ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    CUSTOMER-SITE (Deployable Template)                            │
│  • Install wizard → first admin                                                  │
│  • License activation (validate against platform API)                             │
│  • Pull config/modules from platform or sync                                      │
│  • Check for updates (Update Server API)                                          │
│  • Optional: purchase modules from marketplace (one-time)                        │
│  • Optional: subscribe to support (renew support_expires_at)                      │
└─────────────────────────────────────────────────────────────────────────────────┘
```

**Principles (from workspace rules):**

- All management (sites, modules, forms, settings) is done from **saas-platform**.
- **customer-site** is one “site” in the platform’s site list; it consumes licenses, config, and updates from the platform.
- No duplicate definition of sites/modules in customer-site; single source of truth is saas-platform.

---

## 2. Core Business Platform (saas-platform)

### 2.1 Existing and Extended Scope

| Area | Current State | Extension |
|------|----------------|-----------|
| Customers & Sites | Customer → Site, Site ↔ Module | Add License (1:1 per site or per product), link to purchases |
| Modules | slug, title, description, is_active | Add marketplace fields: price (IRR), type (core/addon), version, file package |
| Support | support_plans, site_support_subscriptions, support_expires_at on Site | Payment flow, renewal, feature flags (ticket, SMS) |
| Site updates | SiteUpdate (version_label, distributed_at, notes) | Update Server API + optional package URL |
| Payments | None | Invoices, transactions, gateway (Zarinpal/IDPay/Saman) |
| Resellers | None | Reseller role, commission %, sub-customers |

### 2.2 Key New/Extended Entities

- **License (platform):** Issued per Site (or per Product purchase). Stores: license_key (hashed/signed), site_id, domain(s), expires_at, product_sku, issued_at. Used by License Server API.
- **Product (marketplace):** SKU, name, type (license_template | module_addon | support_plan), price_irr, is_one_time, module_id (optional), support_plan_id (optional). One-time purchase = buy once, get license + optional module access.
- **Invoice / Transaction:** Link payments (gateway callback) to Customer/Site/Product/SupportPlan; status (pending, paid, failed, refunded).
- **Reseller:** User/Customer with reseller_role; reseller_id on Customer; commission_rate; optional white-label (branding, domain).

---

## 3. License and Update Server

### 3.1 License Server (saas-platform)

**Responsibilities:**

- Issue licenses when a product (template or module) is purchased.
- Validate license when customer-site calls the API (activation + periodic check).

**API (internal or authenticated):**

- `POST /api/license/validate`  
  Body: `{ "license_key": "...", "domain": "example.ir" }`  
  Response: `{ "valid": true, "expires_at": "...", "modules": ["blog","shop"], "support_expires_at": "..." }`  
  Used by customer-site to activate and to refresh local state (e.g. cron).
- `POST /api/license/issue` (admin or after payment)  
  Creates License record tied to Site; returns license_key (only once, then masked).

**Security:**

- Sign payloads (e.g. HMAC with shared secret or per-license secret).
- Rate limit by IP and by license_key.
- Validate domain against allowed list (Site.domain or multiple domains per license).
- Store license_key hashed; return only on issue, then show masked in admin.

**Iranian considerations:**

- No reliance on non-Iranian third-party license services; everything on your infrastructure.
- Optional: SMS or email OTP for sensitive operations (e.g. re-issue).

### 3.2 Update Server (saas-platform)

**Responsibilities:**

- Expose “latest version” and optional “changelog” per product/channel (e.g. customer-site template, or per module).
- Optionally serve update packages (zip) from private storage (signed URLs).

**API:**

- `GET /api/updates/check?product=site_template&current_version=1.2.0&license_key=...`  
  Response: `{ "latest_version": "1.3.0", "url": "https://...", "changelog": "...", "required": false }`
- Updates already stored in `site_updates` (per site); can add a global “product_version” or “channel” (stable/beta) for the template.

**Security:**

- Require valid license_key (and optionally domain) for download URL.
- Short-lived signed URLs for package download.
- Rate limit; log access for audit.

---

## 4. One-Time Purchase Model

### 4.1 Products

- **Site template license (one-time):** One purchase = one Site in platform + one License. Customer receives download link (or pre-provisioned site) + license_key. Price in IRR.
- **Module addon (one-time):** One purchase = attach Module to Site + extend License scope. No recurring fee for the module itself.
- **Support plan:** Recurring (monthly/yearly); see Section 5.

### 4.2 Flow

1. Customer (or guest) selects product on platform (or reseller) → Cart/Checkout.
2. Payment via Iranian gateway (Zarinpal, IDPay, Saman, etc.) → callback/webhook.
3. On success: create Invoice (paid), create/update License, attach Module to Site if applicable, send email with license_key and download/setup link.
4. Customer installs customer-site (or uses existing), enters license_key in admin → customer-site calls License Server → activate and optionally pull config/modules.

### 4.3 Database (conceptual)

- **products:** id, sku, name_fa, name_en, type (license_template | module_addon | support_plan), price_irr, is_one_time, module_id, support_plan_id, is_active.
- **licenses (platform):** id, site_id, product_sku, license_key_hash, domain_allowed, expires_at, issued_at (customer-site keeps its own licenses table for local cache; platform is source of truth).
- **invoices:** id, customer_id, amount_irr, status, gateway, gateway_ref, paid_at.
- **invoice_items:** invoice_id, product_id, quantity, price_irr, license_id (nullable).

---

## 5. Paid Support Subscriptions

### 5.1 Use Existing Tables

- **support_plans:** title, price (IRR), duration_days, has_ticket, has_sms (and any extra features).
- **site_support_subscriptions:** site_id, support_plan_id, total_price, starts_at, ends_at, status (active | expired).

### 5.2 Flow

1. Admin or customer selects a support plan for a site → create Invoice (recurring or one-time for 1/3/6/12 months).
2. Payment success → create or extend site_support_subscription; set Site.support_expires_at = ends_at (or max of current and new ends_at).
3. Cron: mark subscriptions as expired when ends_at passed; optionally notify “support expiring soon” (already supported by Site.supportStatus()).

### 5.3 Features

- Support plans as products (type = support_plan) so they go through the same cart and gateway.
- Optional: auto-renew (store card/token if gateway supports) or manual renewal each period.

---

## 6. Module Marketplace

### 6.1 Roles

- **Platform:** Sells “official” modules (blog, shop, etc.); can add third-party later.
- **Customer:** Buys module addon (one-time) → License scope updated; site gets module enabled in platform; customer-site syncs config (or reads from API).

### 6.2 Module as Product

- **modules** table: add price_irr, version, package_path (or store in object storage), is_marketplace.
- **products** with type = module_addon and module_id → one-time purchase adds module to site and updates license.

### 6.3 Delivery

- After payment: attach module to Site (site_modules); License Server returns updated `modules` list.
- If customer-site pulls config from API: next sync gets new module; if config is file-based, platform can trigger “config export” or customer re-downloads config from panel.

### 6.4 Optional: Third-Party Developers

- Developer registration; submit module (package + metadata); platform approves and lists in marketplace. Revenue share (e.g. 70/30). Requires module review and signing for security.

---

## 7. Reseller System

### 7.1 Model

- **Reseller** = User/Customer with flag reseller_role = true (or separate reseller table linked to User).
- **Customer.reseller_id** = nullable; if set, customer was introduced by that reseller.
- **Commission:** On each Invoice (or per Product), compute commission for reseller; store in reseller_commissions (reseller_id, invoice_id, amount_irr, status: pending | paid).

### 7.2 Flows

- Reseller dashboard: list their customers (Customer where reseller_id = X), sales, commission balance, request payout.
- White-label (optional): reseller has own subdomain/branding (e.g. reseller.yourplatform.ir) and optional custom domain; same codebase, different theme/logo.
- Pricing: reseller sees wholesale price or discounted price; end customer pays full price; difference = commission.

### 7.3 Tables (conceptual)

- **resellers:** id, user_id, company_name, commission_rate, bank_info (encrypted), is_white_label.
- **customers.reseller_id** (FK to resellers or users).
- **reseller_commissions:** id, reseller_id, invoice_id, amount_irr, status, paid_at.

---

## 8. Customer-Site Template (customer-site)

### 8.1 License Activation

- Replace or extend current **local-only** License check:
  - On “Save license” in admin: call Platform **License Server API** with license_key + current domain.
  - If valid: store in local licenses table (or cache) and optionally store returned modules/support_expires_at.
  - If invalid: show error (domain mismatch, expired, revoked).
- Optional: cron job to re-validate periodically (e.g. daily) and disable site or show banner if license invalid.

### 8.2 Config and Modules Sync

- **Option A (recommended for simplicity):** Platform exposes `GET /api/site-config?license_key=...&domain=...` returning JSON: `{ "site_name": "...", "modules": ["blog","shop"], "support_expires_at": "..." }`. Customer-site caches this and uses it to drive config/site.php and modules (instead of only .env).
- **Option B:** Shared database: customer-site connects to platform DB read-only for sites/modules. Simpler for small scale but tighter coupling and security considerations.
- **Option C:** Export from platform: admin exports config file; customer uploads to customer-site. Manual but no API needed.

### 8.3 Update Check

- In admin (or cron): call Update Server API with current_version and license_key.
- If update available: show “New version X available” and link to download or in-place update (careful with file permissions and backups).

### 8.4 Purchases and Support

- Customer can buy modules or support from platform (redirect to platform or iframe). After payment, license/config sync gives new modules or extended support_expires_at.

---

## 9. Scalable Architecture

### 9.1 Application

- **Laravel:** Use queues (database or Redis) for: sending license emails, generating invoices, computing reseller commissions, webhook processing.
- **Cache:** Redis for license validation result (short TTL, e.g. 5–15 min) to reduce DB load; cache “latest version” per product.
- **API:** Stateless; use API tokens or signed requests for customer-site → platform (no session).

### 9.2 Database

- Index: licenses(license_key_hash or lookup field), site_id, expires_at; invoices(customer_id, status, paid_at); site_support_subscriptions(site_id, ends_at).
- Optional: read replica for reporting and reseller dashboard if traffic grows.

### 9.3 Files and Updates

- Store update packages and module packages in object storage (e.g. MinIO or S3-compatible) with signed URLs; avoid serving large files from app server.

### 9.4 Multi-Tenancy

- Current model: one DB for platform; each customer-site has its own DB. No multi-tenant single-DB required for the template; scale by adding more customer-site instances and more sites in platform.

---

## 10. Security Best Practices

### 10.1 License and API

- Hash or HMAC license keys at rest; never return full key after first issue.
- Use HTTPS only; certificate valid and trusted.
- License/Update API: authenticate by license_key + domain; rate limit per key and per IP (e.g. 60/min for validate, 10/min for update check).
- Short-lived signed URLs for update/module downloads (e.g. 10 min).

### 10.2 Platform Admin

- Strong auth (password + optional 2FA); admin middleware (existing); activity log for sensitive actions (already present).
- Separate roles if needed: super_admin, support, reseller (limited scope).

### 10.3 Payment

- Validate gateway callbacks with signature/verification (Zarinpal/IDPay provide verification APIs).
- Idempotency: use gateway ref or invoice id to avoid double-fulfillment.
- Do not log full card or sensitive gateway tokens; store only last4 or reference.

### 10.4 Customer-Site

- Validate license server response (signature if you sign payloads); reject tampered responses.
- Keep Laravel and dependencies updated; use env for API endpoint and secrets (not in code).
- If config is pulled from API, validate and sanitize before writing to local config/cache.

### 10.5 Iranian Compliance

- Data localization: store Iranian users’ data in-country if required by policy.
- No dependency on sanctioned services for core flows (license, payment, auth).
- Logging: avoid logging national ID or full mobile; comply with local data protection expectations.

---

## 11. Iranian Market Specifics

### 11.1 Currency and Pricing

- All prices in **IRR** (Iranian Rial). Store in database as integer (e.g. amount_irr) to avoid float issues.
- Display: format with thousand separators (e.g. ۱٬۲۳۴٬۵۶۷ ریال) and optional Toman (÷10) for display.

### 11.2 Payment Gateways

- Integrate at least one of: **Zarinpal**, **IDPay**, **Saman**, **Pay.ir** (or current popular gateways). Use their REST APIs and callback URLs; verify payment server-side before fulfilling order.
- Sandbox for development; production merchant ID and keys in .env.

### 11.3 Locale and RTL

- Default locale **fa**; RTL layout in both platform and customer-site (already common in your views).
- All user-facing strings in Persian; optional en for resellers or internal.

### 11.4 Legal and Support

- Terms of service and privacy policy in Persian.
- Support channels: ticket (has_ticket in support_plan), optional SMS (has_sms) using Iranian SMS provider (e.g. Kavenegar, Ghasedak). Store support_plans and site_support_subscriptions as designed.

---

## 12. Implementation Order (Suggested)

1. **License Server API** (platform) + **license validation from customer-site** (replace local-only check).
2. **One-time purchase flow:** Products (template + module addon), cart, gateway (one Iranian gateway), invoice, issue license on payment.
3. **Support as product:** Support plan in cart; payment extends site_support_subscriptions and support_expires_at.
4. **Update Server API** + **update check in customer-site** admin.
5. **Config/API for modules** so customer-site gets module list from platform (or sync).
6. **Reseller:** reseller_id on customers, commission calculation, reseller dashboard.
7. **Module marketplace:** module pricing, delivery to site after purchase, optional third-party.
8. **White-label and advanced reseller features.**

---

## 13. Summary

| Component | Location | Purpose |
|-----------|----------|---------|
| Core business platform | saas-platform | Admin, customers, sites, modules, forms, settings |
| License Server | saas-platform API | Issue and validate licenses; customer-site calls for activation |
| Update Server | saas-platform API | Serve latest version and update packages for template/modules |
| One-time purchase | saas-platform | Products (template, module), cart, gateway, invoice, license issuance |
| Paid support | saas-platform | support_plans + site_support_subscriptions + payment and renewal |
| Module marketplace | saas-platform | Modules as products; attach to site after purchase; optional third-party |
| Reseller system | saas-platform | Reseller role, commission, reseller dashboard; optional white-label |
| Customer site template | customer-site | Install, activate via License API, sync config/updates, optional in-admin purchase links |

This design keeps the **single source of truth** in saas-platform, uses **Laravel** across the stack, supports **one-time purchase** and **paid support subscriptions**, adds a **license and update server**, a **module marketplace**, and a **reseller system**, with **scalability** and **security** in mind and **Iranian market** requirements (IRR, gateways, locale, compliance) built in.
