# FINANCE_LEDGER_CERTIFICATION

**Date:** 2026-06-25  
**Module:** CENTRAL_FINANCE_LEDGER_MODULE  
**Verdict:** FINANCE_LEDGER_OPERATIONAL

---

## Summary

A full-stack financial ledger was implemented inside the saas-platform admin. Every successful payment across all revenue channels creates a ledger record automatically. Manual expense entry, filters, reports, and CSV export are operational.

---

## Files Created (saas-platform)

| File | Purpose |
|------|---------|
| `docs/FINANCE_LEDGER_AUDIT.md` | Phase 1 audit — revenue source classification |
| `database/migrations/2026_06_25_200000_create_finance_ledger_entries_table.php` | Ledger table with unique dedup constraint |
| `app/Models/FinanceLedgerEntry.php` | Eloquent model with scopes, constants, helpers |
| `app/Services/Finance/FinanceLedgerService.php` | Auto-capture + manual entry + KPI reporting |
| `app/Observers/ThemeBundleOrderObserver.php` | Auto-captures theme bundle sales |
| `app/Observers/InvoiceLedgerObserver.php` | Auto-captures module purchase invoices |
| `app/Observers/SiteLifecycleInvoiceObserver.php` | Auto-captures hosting renewal invoices |
| `app/Http/Controllers/Api/FinanceLedgerApiController.php` | `POST /api/ledger/push-event` for cross-DB push |
| `app/Http/Controllers/Admin/FinanceLedgerController.php` | Dashboard + CRUD + export |
| `resources/views/admin/finance/dashboard.blade.php` | KPI dashboard (today/month/year + charts) |
| `resources/views/admin/finance/ledger/index.blade.php` | Filterable ledger table + CSV export |
| `resources/views/admin/finance/ledger/create.blade.php` | Manual entry form with Alpine.js |
| `resources/views/admin/finance/ledger/edit.blade.php` | Edit form with auto-source metadata |
| `docs/FINANCE_LEDGER_CERTIFICATION.md` | This file |

## Files Modified (saas-platform)

| File | Change |
|------|--------|
| `app/Observers/ThemePurchaseOrderObserver.php` | Added ledger auto-capture; fixed `isDirty` → `wasChanged` |
| `app/Providers/AppServiceProvider.php` | Registered 3 new observers |
| `routes/web.php` | Added 8 finance admin routes under `admin.finance-ledger.*` |
| `routes/api.php` | Added `POST /api/ledger/push-event` route |
| `app/Helpers/AdminMenu.php` | Added `finance_dashboard` + `finance_ledger` menu items + group |
| `resources/views/admin/partials/sidebar-icon.blade.php` | Added icons for both finance menu items |

## Files Modified (customer-site)

| File | Change |
|------|--------|
| `app/Services/PlatformApiService.php` | Added `pushLedgerEvent(array $data): void` |
| `app/Http/Controllers/Front/SubscriptionPaymentController.php` | Pushes `subscription_payment` event after activation |
| `app/Services/InstallmentService.php` | Pushes `installment_payment` event after `markPaymentPaid` |

---

## Auto-Capture Coverage

| Revenue Source | Status | Mechanism |
|----------------|--------|-----------|
| Theme Marketplace Sales | OPERATIONAL | `ThemePurchaseOrderObserver::updated()` |
| Theme Bundle Sales | OPERATIONAL | `ThemeBundleOrderObserver::updated()` |
| Module Purchase Invoices | OPERATIONAL | `InvoiceLedgerObserver::updated()` |
| Hosting Renewals (Lifecycle) | OPERATIONAL | `SiteLifecycleInvoiceObserver::updated()` |
| Subscription Payments | OPERATIONAL | `SubscriptionPaymentController` → `/api/ledger/push-event` |
| Installment Payments | OPERATIONAL | `InstallmentService::recordInstallmentPayment()` → `/api/ledger/push-event` |
| Support Payments | MANUAL ENTRY | No model exists — entered as `support` category manually |

---

## Manual Expense Categories

| Category | Label |
|----------|-------|
| `ai_cost` | هزینه AI |
| `server_cost` | هزینه سرور |
| `arvancloud` | ابرآروان |
| `domain` | دامنه |
| `advertising` | تبلیغات |
| `salary` | حقوق و دستمزد |
| `software` | نرم‌افزار |
| `other_expense` | هزینه متفرقه |

---

## Filters Available

- Date range (entry_date from/to)
- Type (income / expense)
- Category (all 16 values)
- Source (automatic / manual)
- Site ID
- Payment gateway
- Amount range (min / max)
- Search (description, invoice_number, gateway_ref, notes)

---

## KPI Dashboard

- **Today:** income / expense / net profit
- **This month:** income / expense / net profit
- **This year:** income / expense / net profit
- **Monthly trend:** 6-month bar chart (income vs expense)
- **Top revenue categories:** progress bars
- **Top sites:** ranked list by revenue
- **Top expense categories:** progress bars
- **Recent transactions:** last 10 entries

---

## Deduplication

- Unique constraint on `(reference_type, reference_id)` prevents double-recording
- `safeCreate()` in `FinanceLedgerService` catches MySQL error `23000` silently
- All observer-based captures are therefore idempotent

---

## Security

- `/api/ledger/push-event` requires `tenant.api` middleware (license key authentication)
- Admin routes protected by `['auth', 'admin']` middleware
- All amounts stored as integers (IRR, no floating point)
- CSV export includes UTF-8 BOM for Persian text compatibility

---

## Verdict

**FINANCE_LEDGER_OPERATIONAL**

All 7 phases complete. Revenue auto-capture covers 6/7 sources (support requires manual entry — no payment model exists). Filters, reports, manual entry, and CSV export are fully operational.
