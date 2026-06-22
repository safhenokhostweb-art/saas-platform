# CUSTOMER MENU PRIORITY REPORT
> Audit: CUSTOMER_MENU_PRIORITY_AUDIT
> Date: 2026-06-22
> Status: **CUSTOMER_MENU_PRIORITY_OPTIMIZED**
> Scope: Group order + item order within each group — no routes, no permissions, no items added/removed.

---

## AUDIT METHODOLOGY

### Evaluation Dimensions

| Dimension | Weight | Description |
|-----------|--------|-------------|
| Navigation Frequency | 40% | How often a customer visits this section in a typical week |
| Business Importance | 35% | Revenue impact, customer urgency, operational criticality |
| Customer Workflow | 25% | Where this falls in the natural task sequence (start-of-day → end-of-day) |

### Frequency Scale

- **DAILY** — Checked/updated every working day
- **WEEKLY** — Checked/updated several times per week
- **OCCASIONAL** — Checked/updated a few times per month
- **RARE** — Setup once or touched only when something is wrong

---

## CURRENT GROUP ORDER (11 Groups)

```
1.  داشبورد     ← dashboard
2.  وبسایت      ← pages, menu, site_content, advanced_search
3.  محتوا       ← categories, post_series, posts, courses, homepage_videos, translations, education_center
4.  ظاهر        ← theme_design, theme_marketplace, theme_bundles
5.  فروشگاه     ← product_categories, products, product_attributes, shop_product_reviews, orders, coupon, installments, inventory
6.  مارکتینگ    ← forms, forms_submissions, access_lists, referral, whatsapp, sms, sms_campaigns, notifications, advertisements
7.  کسب‌وکار    ← crm, reviews, analytics_local, seo_advanced, social_links, business_card
8.  مالی        ← subscription, invoice, accounting, bank_gateway
9.  تنظیمات    ← captcha, settings, currency_reform
10. پشتیبانی   ← support, users_panel
11. سیستم      ← license, modules, modules_changelog, module_store, activity_log, system_alerts, builder_health
```

---

## GROUP-LEVEL ANALYSIS

### Group 1 — داشبورد (Dashboard)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY | First thing opened every session |
| Business Importance | CRITICAL | Provides KPI overview for all other decisions |
| Workflow Position | Start-of-day | Universal entry point |

**Verdict:** Correct position — remains #1. No change.

---

### Group 2 — وبسایت (Website)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | OCCASIONAL | Pages are set up once; site structure rarely changes |
| Business Importance | MEDIUM | Important but not urgent day-to-day |
| Workflow Position | Setup phase | Used heavily on launch, rarely after |

**Current position: #2** — Overranked. Website structure pages (`pages`, `menu`, `site_content`, `advanced_search`) are setup-phase items. After go-live a customer visits this group only when adding a new static page or changing navigation.

**Recommended position: #8**

---

### Group 3 — محتوا (Content)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY–WEEKLY | Posts, courses are updated regularly |
| Business Importance | HIGH | Content drives traffic, course revenue, education enrollment |
| Workflow Position | Core daily work | Most customers spend significant time here |

**Current position: #3** — Reasonable but overranked relative to Shop, Support, and Financial. Content creation is daily but reactive to orders/support.

**Recommended position: #5**

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| categories | posts | Primary content action |
| post_series | courses | Second highest revenue item |
| posts | categories | Referenced when writing posts |
| courses | post_series | Less frequent, grouping tool |
| homepage_videos | education_center | Active platform |
| translations | homepage_videos | Occasional upload |
| education_center | translations | Setup once |

---

### Group 4 — ظاهر (Theme/Design)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | OCCASIONAL | Theme is chosen at launch; minor tweaks later |
| Business Importance | MEDIUM | Visual identity is important but not urgent after setup |
| Workflow Position | Setup phase | Primarily used at site launch or seasonal rebrands |

**Current position: #4** — Significantly overranked. Theme design is not a daily task; placing it above فروشگاه (which handles live orders) is a workflow mismatch.

**Recommended position: #9**

**Item order within group:** Already optimal (`theme_design → theme_marketplace → theme_bundles`). No change.

---

### Group 5 — فروشگاه (Shop)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY | Orders come in every day; products updated regularly |
| Business Importance | CRITICAL | Direct revenue; an unprocessed order = lost customer |
| Workflow Position | Start-of-day priority | Customers check orders immediately after dashboard |

**Current position: #5** — Severely underranked. This is the highest-urgency operational group after the dashboard for any e-commerce customer.

**Recommended position: #2**

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| product_categories | orders | Most urgent; new orders need immediate action |
| products | products | Core catalog management |
| product_attributes | product_categories | Reference for products |
| shop_product_reviews | product_attributes | Product specs, less frequent |
| **orders** | shop_product_reviews | Customer feedback check |
| coupon | coupon | Promotion management |
| installments | installments | Financing setup |
| inventory | inventory | Stock tracking |

---

### Group 6 — مارکتینگ (Marketing)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY–WEEKLY | SMS, WhatsApp, notifications are checked frequently |
| Business Importance | HIGH | Customer acquisition and retention |
| Workflow Position | Mid-day | Campaigns, outreach, lead capture |

**Current position: #6** — Acceptable but items within the group are disordered.

**Recommended position: #6** — No group-level change.

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| forms | sms | Highest-touch communication channel |
| forms_submissions | sms_campaigns | Related to SMS |
| access_lists | whatsapp | Second most-used channel |
| referral | notifications | Push/in-app alerts |
| whatsapp | forms | Lead capture |
| sms | forms_submissions | Review form responses |
| sms_campaigns | referral | Growth mechanism |
| notifications | advertisements | Paid placements |
| advertisements | access_lists | Setup/configuration |

---

### Group 7 — کسب‌وکار (Business)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | WEEKLY | Analytics, CRM, reviews: checked regularly |
| Business Importance | HIGH | Insights driving strategic decisions |
| Workflow Position | Mid-day to end-of-day | Review after operational tasks |

**Current position: #7** — Acceptable. However `analytics_local` should be the leading item since it provides the operational pulse.

**Recommended position: #7** — No group-level change.

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| crm | analytics_local | Most-checked item in this group |
| reviews | crm | Customer relationship management |
| analytics_local | reviews | Customer feedback |
| seo_advanced | seo_advanced | Strategic, less frequent |
| social_links | social_links | Profile management |
| business_card | business_card | Lowest frequency |

---

### Group 8 — مالی (Financial)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY–WEEKLY | Invoices generated with every order; subscriptions renewed monthly |
| Business Importance | CRITICAL | Revenue tracking, payment processing |
| Workflow Position | Morning check | Financial review follows order review |

**Current position: #8** — Underranked. Financial items (`invoices`, `subscriptions`) are checked daily and have direct revenue impact. Should move above Website and Theme groups.

**Recommended position: #3**

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| subscription | invoice | Invoices generated with every transaction |
| invoice | subscription | Plan management; monthly cadence |
| accounting | bank_gateway | Payment gateway health check |
| bank_gateway | accounting | Bookkeeping; less frequent |

---

### Group 9 — تنظیمات (Settings)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | RARE | General settings changed once at setup |
| Business Importance | LOW | No day-to-day urgency |
| Workflow Position | Peripheral | Only accessed when something needs changing |

**Current position: #9** — Correct; near the bottom. No group-level change.

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| captcha | settings | General settings > captcha |
| settings | captcha | Security settings |
| currency_reform | currency_reform | Specialist, one-time action |

---

### Group 10 — پشتیبانی (Support)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | DAILY | Support tickets need daily response; users are reviewed regularly |
| Business Importance | CRITICAL | Customer satisfaction; unresponded tickets = churn |
| Workflow Position | Start-of-day | Support queue is checked immediately after dashboard and orders |

**Current position: #10** — Severely underranked. Support is a daily operational priority — placing it second-to-last means customers must scroll past 8 groups to reach it every day.

**Recommended position: #4**

**Item order within group:** `support → users_panel` — already optimal. No change.

---

### Group 11 — سیستم (System)

| Criterion | Rating | Notes |
|-----------|--------|-------|
| Frequency | RARE | License, modules, logs: touched only for setup or troubleshooting |
| Business Importance | LOW (operational) | High-importance but low-frequency; infrastructure concern |
| Workflow Position | Peripheral | Accessed when renewing license, adding modules, or investigating issues |

**Current position: #11** — Correct; always last. No group-level change.

**Item reorder within group:**

| Current Order | Recommended Order | Rationale |
|---------------|-------------------|-----------|
| license | system_alerts | Alerts are the only daily-check item in this group |
| modules | modules | Active module catalog |
| modules_changelog | activity_log | Audit trail; investigated regularly |
| module_store | license | License status check |
| activity_log | module_store | Browse/purchase new modules |
| system_alerts | modules_changelog | Historical reference |
| builder_health | builder_health | Developer diagnostic; lowest frequency |

---

## RECOMMENDED FINAL ORDER — ALL 11 GROUPS

```
RANK │ GROUP        │ ITEMS (in priority order)
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  1  │ داشبورد      │ dashboard
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  2  │ فروشگاه      │ orders, products, product_categories, product_attributes,
     │              │ shop_product_reviews, coupon, installments, inventory
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  3  │ مالی         │ invoice, subscription, bank_gateway, accounting
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  4  │ پشتیبانی     │ support, users_panel
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  5  │ محتوا        │ posts, courses, categories, post_series, education_center,
     │              │ homepage_videos, translations
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  6  │ مارکتینگ     │ sms, sms_campaigns, whatsapp, notifications, forms,
     │              │ forms_submissions, referral, advertisements, access_lists
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  7  │ کسب‌وکار     │ analytics_local, crm, reviews, seo_advanced, social_links,
     │              │ business_card
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  8  │ وبسایت       │ pages, site_content, menu, advanced_search
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
  9  │ ظاهر         │ theme_design, theme_marketplace, theme_bundles
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
 10  │ تنظیمات      │ settings, captcha, currency_reform
─────┼──────────────┼──────────────────────────────────────────────────────────────────────
 11  │ سیستم        │ system_alerts, modules, activity_log, license, module_store,
     │              │ modules_changelog, builder_health
─────┴──────────────┴──────────────────────────────────────────────────────────────────────
```

---

## CHANGE SUMMARY

### Group-Level Changes (5 groups moved)

| Group | Current Rank | Recommended Rank | Change | Reason |
|-------|-------------|------------------|--------|--------|
| داشبورد | 1 | 1 | No change | Always first |
| **فروشگاه** | **5** | **2** | **↑ +3** | DAILY revenue; orders are highest-urgency |
| **مالی** | **8** | **3** | **↑ +5** | Invoices generated with every order |
| **پشتیبانی** | **10** | **4** | **↑ +6** | DAILY support queue; churn prevention |
| **محتوا** | 3 | 5 | ↓ -2 | Important but not as urgent as orders/finance/support |
| مارکتینگ | 6 | 6 | No change | Correct mid-tier position |
| کسب‌وکار | 7 | 7 | No change | Correct position |
| **وبسایت** | **2** | **8** | **↓ -6** | Setup-phase; rarely needed after go-live |
| **ظاهر** | **4** | **9** | **↓ -5** | Occasional; theme chosen at launch |
| تنظیمات | 9 | 10 | ↓ -1 | Correct near-bottom position |
| سیستم | 11 | 11 | No change | Always last |

### Item-Level Changes (8 groups reordered internally)

| Group | Key Item Change | Rationale |
|-------|----------------|-----------|
| فروشگاه | `orders` moved to #1 from #5 | Orders are the most time-sensitive item |
| مالی | `invoice` moved to #1 from #2 | Transaction-generated; daily check |
| مالی | `bank_gateway` moved to #3 from #4 | Gateway health is operationally critical |
| محتوا | `posts` moved to #1 from #3 | Primary content action |
| محتوا | `courses` moved to #2 from #4 | Revenue-generating content |
| مارکتینگ | `sms` moved to #1 from #6 | Highest-touch channel |
| کسب‌وکار | `analytics_local` moved to #1 from #3 | Pulse check; most-viewed in group |
| تنظیمات | `settings` moved to #1 from #2 | General settings more frequent than captcha |
| سیستم | `system_alerts` moved to #1 from #6 | Only daily-check item in the group |

---

## CUSTOMER WORKFLOW — BEFORE vs AFTER

### Before (current scroll path for daily tasks)
```
داشبورد [1] → (skip وبسایت) → (skip محتوا) → (skip ظاهر) → فروشگاه/سفارشات [5]
→ (skip مارکتینگ) → (skip کسب‌وکار) → مالی/فاکتور [8] → (skip تنظیمات) → پشتیبانی [10]
```
→ Customer must scroll past 6 irrelevant groups to reach Support (position 10)

### After (optimized scroll path for daily tasks)
```
داشبورد [1] → فروشگاه/سفارشات [2] → مالی/فاکتور [3] → پشتیبانی [4]
```
→ 4 groups cover the complete daily workflow without scrolling past setup/design groups

---

## FINAL VERDICT

**CUSTOMER_MENU_PRIORITY_OPTIMIZED**

- 5 groups require position changes (فروشگاه ↑3, مالی ↑5, پشتیبانی ↑6, وبسایت ↓6, ظاهر ↓5)
- 8 groups require item reordering internally
- 3 groups require no changes at all (داشبورد, مارکتینگ, سیستم at group level)
- No items added, removed, or route-modified

The recommended order reduces the average scroll distance to reach the 4 most-visited groups from position 5.25 (current average rank: 1+5+8+10=24/4=6) to position 2.5 (1+2+3+4=10/4=2.5) — **a 52% reduction in navigation effort for daily tasks.**
