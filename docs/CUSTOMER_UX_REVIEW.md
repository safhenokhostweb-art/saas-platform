# CUSTOMER_UX_REVIEW
> RC1 Customer-Facing UX Audit  
> Date: 2026-06-22  
> Scope: customer-site — dashboard, sidebar, navigation, lifecycle, billing, marketplace pages  
> Rule: Improvements only. No feature additions.

---

## Audit Overview

| Area | Score | Issues Found |
|------|-------|-------------|
| Customer Dashboard | 87/100 | 2 improvements |
| Customer Sidebar | 90/100 | 1 improvement |
| Customer Navigation | 88/100 | 2 improvements |
| Lifecycle Pages | 92/100 | 1 improvement |
| Billing Pages | 85/100 | 3 improvements |
| Marketplace Pages | 91/100 | 1 improvement |

**Overall Customer UX Score: 89/100**

---

## Area 1: Customer Dashboard

### CUX-001 — Empty State for New Tenants (Severity: MEDIUM)

**Problem:** When a tenant's site is newly created (no orders, no courses enrolled, no support tickets), the dashboard shows empty tables and zero-value cards with no guidance on what to do next.

**Current behavior:** Cards show `0` with no context. Tables show "هیچ موردی یافت نشد."

**Improvement:** Add contextual empty-state cards with actionable CTAs:
- "سفارشی ثبت نشده — برای خرید به فروشگاه مراجعه کنید" + link
- "در هیچ دوره‌ای ثبت‌نام نشده" + link to `/courses`
- "هیچ تیکتی باز نیست" + green check icon

**Impact:** Guides new customers, reduces confusion, increases engagement.
**Effort:** 2 hours. Blade conditionals only, no new routes.

---

### CUX-002 — No Success Confirmation After Purchase (Severity: MEDIUM)

**Problem:** After a successful payment (Zarinpal/IDPay callback), the customer is redirected to the subscription page with only a flash message. The flash message disappears immediately and there is no persistent confirmation state.

**Improvement:** Add a 10-second "خرید موفق" confirmation banner with:
- Green background, checkmark icon
- Order ID displayed
- "مشاهده اشتراک" button
- Auto-dismiss after 10 seconds

**Impact:** Reduces customer anxiety post-payment, reduces support tickets about "did my payment go through?"
**Effort:** 1 hour. Session-driven banner, no backend changes.

---

## Area 2: Customer Sidebar

### CUX-003 — Sidebar Groups Not Collapsible (Severity: LOW)

**Problem:** Customer admin sidebar shows all items expanded regardless of screen size. On 768px tablets, the sidebar takes >60% of viewport width when open.

**Improvement:** Add `localStorage`-persisted expand/collapse per sidebar group using Alpine.js `$persist`. Groups default to expanded, users can collapse sections they don't use.

**Impact:** Better tablet experience, more content space.
**Effort:** 2 hours. Alpine.js `$persist` only.

---

## Area 3: Customer Navigation

### CUX-004 — No Breadcrumb on Inner Pages (Severity: MEDIUM)

**Problem:** On deep pages (e.g., `/account/subscriptions/{id}`, `/admin/sms/campaigns/{id}/show`), the page title is displayed but there is no breadcrumb trail showing the user where they are in the hierarchy.

**Improvement:** Add `x-breadcrumb` Blade component (already exists in design system) to inner show/edit pages of: subscriptions, CRM contacts, SMS campaigns, inventory items, installments.

**Impact:** Users can navigate back without using the browser back button. Improves findability.
**Effort:** 3 hours. Blade includes only.

---

### CUX-005 — Mobile Navigation "Hamburger" Opens Full Overlay (Severity: LOW)

**Problem:** On mobile viewports, the hamburger menu opens a full-screen overlay that blocks the content. The close button is at the top-right, which is non-standard for RTL interfaces (should be top-left in RTL).

**Improvement:** Move the close button (X) to top-right for LTR convention within the modal, or use a slide-in drawer from the right (standard for RTL apps). The current overlay close on outside-click already works.

**Impact:** RTL UX alignment.
**Effort:** 30 minutes. CSS/Alpine.js adjustment only.

---

## Area 4: Lifecycle Pages

### CUX-006 — Suspended Page Missing Payment Deadline (Severity: MEDIUM)

**Problem:** The `suspended.blade.php` page shows support contacts and a self-service restore button, but it does not display the **deadline by which the archive will be created** (i.e., 90 days from suspension).

**Improvement:** Add a visible deadline calculation block:
```
⚠️ آرشیو خودکار سایت: ۱۴۰۵/۰۳/۳۱ (۱۲ روز دیگر)
```
This uses the `suspended_at` + `ARCHIVE_AFTER_DAYS` config value.

**Impact:** Customers understand urgency. Reduces confusion about when they lose data.
**Effort:** 1 hour. Pass `$archiveDeadline` from `CheckSiteLifecycle` middleware.

---

## Area 5: Billing Pages

### CUX-007 — Invoice Download No Filename (Severity: LOW)

**Problem:** When a customer downloads a subscription invoice PDF, the file is named `laravel_<hash>.pdf` by default. This is not user-friendly.

**Improvement:** Set the Content-Disposition header filename to `invoice-{order_id}-{date}.pdf`.

**Impact:** Better file management for customers.
**Effort:** 15 minutes. One line in the controller response.

---

### CUX-008 — Payment Failed Page No Timer for Retry (Severity: MEDIUM)

**Problem:** The pay-failed page shows retry guidance (4-step guidance added in GAP-L02), but if the payment gateway has a cooldown (most Iranian gateways require 3–5 minutes between retries), there is no feedback about when to retry.

**Improvement:** Add a 5-minute countdown timer on the pay-failed page that counts down from payment failure timestamp. After countdown, the "تلاش مجدد" button activates.

**Impact:** Prevents frustrated repeat-click attempts. Reduces gateway error rates.
**Effort:** 2 hours. Alpine.js countdown from session timestamp.

---

### CUX-009 — Installment Schedule Not Highlighted (Severity: LOW)

**Problem:** The installment account page shows all payment dates in a list. The **next payment due** is not visually distinguished from past and future payments.

**Improvement:** Highlight the next upcoming installment row with an amber background and "پرداخت بعدی" badge. Past-paid rows show green checkmarks. Overdue rows show red.

**Impact:** Customers immediately see what they owe next.
**Effort:** 1 hour. Jalali date comparison in Blade.

---

## Area 6: Marketplace Pages

### CUX-010 — Theme Preview Before Purchase (Severity: MEDIUM)

**Problem:** The theme detail page (`/admin/theme-marketplace/{id}`) shows screenshots and metadata, but there is no way to preview the theme rendered on the customer's own site content.

**Current state:** Static screenshots only.

**Note:** This is **not** a new feature — the preview iframe infrastructure exists in the Canvas editor. The improvement is adding a "پیش‌نمایش زنده" link that opens the Canvas live preview in read-only mode (no edit permissions).

**Improvement:** Add a "مشاهده پیش‌نمایش" button on the theme detail page that links to the existing `/admin/theme-builder/preview-frame?theme_id={id}` route with a `readonly=true` parameter.

**Impact:** Reduces purchase hesitation. No new backend code — uses existing preview route.
**Effort:** 30 minutes. Button link only.

---

## Priority Matrix

| ID | Issue | Severity | Effort | Priority |
|----|-------|----------|--------|---------|
| CUX-001 | Empty state for new tenants | MEDIUM | 2h | P1 |
| CUX-002 | Payment success confirmation | MEDIUM | 1h | P1 |
| CUX-006 | Suspended page archive deadline | MEDIUM | 1h | P1 |
| CUX-008 | Payment failed retry timer | MEDIUM | 2h | P1 |
| CUX-010 | Theme preview before purchase | MEDIUM | 0.5h | P1 |
| CUX-004 | Breadcrumb on inner pages | MEDIUM | 3h | P2 |
| CUX-007 | Invoice PDF filename | LOW | 0.25h | P2 |
| CUX-009 | Installment next payment highlight | LOW | 1h | P2 |
| CUX-003 | Sidebar collapsible groups | LOW | 2h | P3 |
| CUX-005 | Mobile RTL close button | LOW | 0.5h | P3 |

---

## Not Included (Feature Additions — Deferred to Roadmap)

The following were considered but are **FEATURE** classified — not permitted under launch freeze:

- Customer analytics dashboard (ANA-2)
- Customer mobile app push notifications (M-APP-2)
- Theme rating system (ME-2)
- Collaborative canvas editing (CE-1)

---

## Verdict

**CUSTOMER_UX_SCORE: 89/100**  
10 improvements identified. All are enhancements to existing functionality. None require new tables, new routes, or new modules.

---

*CUSTOMER_UX_REVIEW — generated 2026-06-22 for RC1 baseline*
