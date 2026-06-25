# FINANCE_LEDGER_AUDIT.md
> Phase 1 — Audit of Existing Revenue Sources
> Date: 2026-06-25

---

## Scope

Full audit of all financial flows in the saas-platform ecosystem before implementing the Central Finance Ledger.

---

## Revenue Source Inventory

### 1. Module / Product Purchases (Platform Checkout)

| Field | Value |
|-------|-------|
| Table (saas-platform) | `invoices` + `invoice_items` + `site_module_purchases` |
| Model | `App\Models\Invoice`, `App\Models\ModulePurchase` |
| Payment confirmed at | `CheckoutController::callback()` → `STATUS_PAID` |
| Amount field | `invoices.amount_irr` |
| Gateway info | `invoices.gateway`, `invoices.gateway_ref` |
| Customer link | `invoices.customer_id` |
| Status | **EXISTS** |

### 2. Theme Marketplace Sales

| Field | Value |
|-------|-------|
| Table (saas-platform) | `theme_purchase_orders` |
| Model | `App\Models\ThemePurchaseOrder` |
| Payment confirmed at | `ThemeMarketplacePaymentController::callback()` → `STATUS_PAID` |
| Amount field | `theme_purchase_orders.amount_rial` |
| Gateway info | `gateway`, `gateway_ref` |
| Site link | `theme_purchase_orders.site_id` |
| Status | **EXISTS** |

### 3. Theme Bundle Sales

| Field | Value |
|-------|-------|
| Table (saas-platform) | `theme_bundle_orders` |
| Model | `App\Models\ThemeBundleOrder` |
| Payment confirmed at | `ThemeBundlePaymentCallbackController::handle()` → `STATUS_PAID` |
| Amount field | `theme_bundle_orders.amount_rial` |
| Gateway info | `gateway` |
| Site link | `theme_bundle_orders.site_id` |
| Status | **EXISTS** |

### 4. Hosting / Lifecycle Invoices

| Field | Value |
|-------|-------|
| Table (saas-platform) | `site_lifecycle_invoices` |
| Model | `App\Models\SiteLifecycleInvoice` |
| Payment confirmed at | `ApiLifecycleController::confirmRestore()` → `markInvoicePaid()` |
| Amount field | `site_lifecycle_invoices.amount` |
| Types | reactivation, archive_restore, storage_retention, storage_overage |
| Site link | `site_lifecycle_invoices.site_id` |
| Status | **EXISTS** |

### 5. Subscription Payments (Tenant Customer-Site)

| Field | Value |
|-------|-------|
| Table (customer-site) | `subscriptions` + `invoices` |
| Model | `Subscription`, `Invoice` (per-tenant DB) |
| Payment confirmed at | `SubscriptionPaymentController::activateSubscription()` |
| Amount field | `invoices.total` |
| Status | **PARTIAL** — lives in tenant DB, not aggregated in platform |
| Ledger approach | customer-site pushes event to platform API on success |

### 6. Installment Payments (Tenant Customer-Site)

| Field | Value |
|-------|-------|
| Table (customer-site) | `installment_orders` + `installment_payments` |
| Model | `InstallmentOrder`, `InstallmentPayment` (per-tenant DB) |
| Payment confirmed at | Installment payment callbacks in customer-site |
| Status | **PARTIAL** — lives in tenant DB, not aggregated in platform |
| Ledger approach | customer-site API push |

### 7. Support Payments

| Field | Value |
|-------|-------|
| Model | `SupportPlan` (saas-platform) — no payment model found |
| Status | **MISSING** — no payment flow for support plans in current codebase |
| Ledger approach | Manual entry or future implementation |

---

## Expense Sources (Manual Entry Required)

| Category | Description |
|----------|-------------|
| `ai_cost` | Claude API, OpenAI API costs |
| `server_cost` | VPS hosting (87.107.12.81) costs |
| `arvancloud` | ArvanCloud CDN / object storage costs |
| `domain` | Domain registration and renewal |
| `advertising` | Marketing and advertising spend |
| `salary` | Salaries and contractor payments |
| `software` | Software licenses, tools |
| `other_expense` | Miscellaneous expenses |

---

## Classification Summary

| Revenue Source | Status | Location | Auto-Capture Strategy |
|----------------|--------|----------|-----------------------|
| Module/Product Purchases | **EXISTS** | saas-platform | Observer on `Invoice` |
| Theme Marketplace Sales | **EXISTS** | saas-platform | Observer on `ThemePurchaseOrder` |
| Theme Bundle Sales | **EXISTS** | saas-platform | Observer on `ThemeBundleOrder` |
| Hosting/Lifecycle Invoices | **EXISTS** | saas-platform | Observer on `SiteLifecycleInvoice` |
| Subscription Payments | **PARTIAL** | customer-site | API push on payment success |
| Installment Payments | **PARTIAL** | customer-site | API push on payment success |
| Support Payments | **MISSING** | — | Manual entry only |

---

## Audit Verdict

- **4 revenue sources EXIST** in saas-platform DB → auto-capture via Observers ✅
- **2 revenue sources PARTIAL** → require customer-site API push ✅
- **1 revenue source MISSING** → manual entry fallback ✅
- **All expenses** → manual entry with rich categorization ✅

**AUDIT_COMPLETE — Ready for Phase 2 (Design)**
