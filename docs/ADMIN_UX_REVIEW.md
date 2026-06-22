# ADMIN_UX_REVIEW
> RC1 Platform Admin UX Audit  
> Date: 2026-06-22  
> Scope: Admin dashboard, menus, Theme Builder, Marketplace, Lifecycle Center, Monitoring Center  
> Rule: Improvements only. No feature additions.

---

## Audit Overview

| Area | Score | Issues Found |
|------|-------|-------------|
| Admin Dashboard | 88/100 | 2 improvements |
| Admin Menus & Navigation | 91/100 | 2 improvements |
| Theme Builder | 90/100 | 2 improvements |
| Theme Marketplace | 92/100 | 1 improvement |
| Lifecycle Center | 89/100 | 2 improvements |
| Monitoring Center | 87/100 | 3 improvements |

**Overall Admin UX Score: 90/100**

---

## Area 1: Admin Dashboard

### AUX-001 — Dashboard Lacks Quick-Action Row (Severity: MEDIUM)

**Problem:** The platform admin dashboard shows KPIs and recent activity, but there are no quick-action buttons. An admin who logs in must navigate through the sidebar to perform common tasks.

**Common tasks that should be one click:**
- "ایجاد سایت جدید"
- "مشاهده تیکت‌های باز"
- "بررسی حوادث فعال"
- "بررسی سایت‌های در حالت grace period"

**Improvement:** Add a sticky "اقدامات سریع" card row at the top of the dashboard with 4 icon buttons linking to the above. No new routes or controllers.

**Impact:** Reduces time-to-action for common admin operations.
**Effort:** 1 hour. Pure Blade + Tailwind.

---

### AUX-002 — Dashboard KPI Cards Not Linked (Severity: LOW)

**Problem:** KPI cards (Total Sites, Active Subscriptions, Open Tickets, etc.) display numbers but are not clickable. An admin who sees "۱۲ سایت معلق" cannot click the card to see which sites.

**Improvement:** Wrap each KPI card in a link to the relevant filtered list:
- "سایت‌های معلق" → `/admin/lifecycle?status=suspended`
- "تیکت‌های باز" → `/admin/support-tickets?status=open`
- "اشتراک‌های منقضی" → filtered subscription list

**Impact:** One-click drill-down from overview to details.
**Effort:** 30 minutes. `<a href>` wrapper per card.

---

## Area 2: Admin Menus & Navigation

### AUX-003 — Sidebar Group "بازاریابی / دانش‌محور" Has Only One Item (Severity: LOW)

**Problem:** The "بازاریابی / دانش‌محور" group in the admin sidebar contains only `kb` (مقالات دانش‌نامه). A single-item group is visually wasteful and adds unnecessary scrolling.

**Improvement:** Merge `kb` into the "محتوا / مقالات" group which already contains `theme_builder`, `theme_templates`, `theme_packs`, `zip_import`, `education`. This reduces sidebar sections by 1.

**Impact:** Cleaner sidebar, less visual noise.
**Effort:** 5 minutes. Move one key in `AdminMenu::getGroups()`.

---

### AUX-004 — No Confirmation on Destructive Sidebar Actions (Severity: MEDIUM)

**Problem:** Several admin list pages have "حذف" buttons in table rows that fire immediately with a JS `confirm()` native browser dialog. This is inconsistent — some pages use a custom modal (modules, users), others use native confirm.

**Improvement:** Standardize all destructive confirmations to use the existing `<x-confirm-modal>` Blade component. Affected pages: resellers, profile-fields, pages, knowledge base categories.

**Impact:** Consistent UX across the admin panel. Prevents accidental deletions.
**Effort:** 2 hours across 4 views.

---

## Area 3: Theme Builder

### AUX-005 — Canvas Editor Has No "Save" Keyboard Shortcut Indicator (Severity: LOW)

**Problem:** The Canvas editor supports Ctrl+Z/Y (undo/redo) and has auto-save every 60 seconds. However, there is no visible indicator that auto-save is happening, and no `Ctrl+S` shortcut for manual save.

**Improvement:**
1. Add `Ctrl+S` → trigger `saveCanvas()` in the editor JS
2. Show a brief "ذخیره شد ✓" toast in the top-right corner on auto-save and manual save
3. Show a pulsing amber dot when there are unsaved changes

**Impact:** Editors feel confident their work is saved. Reduces frustration from accidental tab-close.
**Effort:** 2 hours. JavaScript additions in canvas-editor.blade.php only.

---

### AUX-006 — Theme Builder Index Has No "Last Edited" Column (Severity: LOW)

**Problem:** The Theme Builder index (`/admin/theme-builder`) shows theme name, status, and page count. It does not show when each theme was last edited, making it impossible to know which themes are "stale."

**Improvement:** Add a "آخرین ویرایش" column using `updated_at` in Jalali format.

**Impact:** Admins can quickly identify recently-touched themes.
**Effort:** 15 minutes. One column in the Blade table + query already has `updated_at`.

---

## Area 4: Theme Marketplace

### AUX-007 — Marketplace Admin Has No "Unpublish" Toggle (Severity: MEDIUM)

**Problem:** Themes in the marketplace can be published or listed, but there is no quick toggle to temporarily hide a theme from the customer-facing marketplace without deleting it. An admin who discovers a bug in a theme must delete and re-create it.

**Improvement:** Add an `is_active` boolean column (already exists or use `is_featured` repurposed) and a toggle switch in the marketplace admin index. Hidden themes are not returned by the API.

**Note:** This uses the **existing** `is_featured`/active flags already on the `themes` table — NOT a new column. Verify the existing schema before implementation.

**Impact:** Operational flexibility without data loss.
**Effort:** 1 hour. Toggle button + API filter, no migration.

---

## Area 5: Lifecycle Center

### AUX-008 — Lifecycle Overview No "At-a-Glance" State Distribution Chart (Severity: MEDIUM)

**Problem:** The lifecycle overview (`/admin/lifecycle`) shows a list of sites with their states. When there are 50+ sites, finding how many are in each state requires counting rows manually.

**Improvement:** Add a 5-segment horizontal progress bar at the top of the overview showing:
`active | grace_period | suspended | archived | deleted`
With counts and percentages. Uses existing query data — no additional DB call.

**Impact:** Instant operational awareness.
**Effort:** 1.5 hours. Alpine.js bar calculation from existing `$counts` array already passed to the view.

---

### AUX-009 — Lifecycle Per-Site Page Has No Event Timeline (Severity: MEDIUM)

**Problem:** The per-site lifecycle page (`/admin/sites/{site}/lifecycle`) shows current state and action buttons, but the `site_lifecycle_logs` table has a full event history that is not displayed.

**Improvement:** Add an event timeline section at the bottom of the per-site lifecycle page showing the last 10 lifecycle events (state changes, SMS sent, invoices created) in chronological order.

**Impact:** Admins can understand why a site is in its current state without querying the DB.
**Effort:** 1.5 hours. Query `site_lifecycle_logs` where `site_id = $site->id`, display in reverse-chronological Blade list.

---

## Area 6: Monitoring Center

### AUX-010 — Incident List Has No Severity Filter (Severity: MEDIUM)

**Problem:** The incidents index (`/admin/incidents`) shows all incidents sorted by creation date. When there are 20+ incidents, finding `critical` or `high` severity incidents requires scrolling through the full list.

**Improvement:** Add a severity filter dropdown above the incidents table: All | Critical | High | Medium | Low. Client-side Alpine.js filter on the existing rendered list.

**Impact:** Operators can triage by severity immediately.
**Effort:** 1 hour. Alpine.js filter, no backend changes needed.

---

### AUX-011 — Monitoring Site Grid Has No "Last Checked" Timestamp (Severity: LOW)

**Problem:** The monitoring overview shows site uptime status (UP/DOWN) but does not show when the last health check was performed. If `monitor:sites` has been failing silently, an admin would not know the data is stale.

**Improvement:** Add a "آخرین بررسی" column or subtitle per site row, using the `checked_at` timestamp from `site_health_logs`.

**Impact:** Admins immediately know if monitoring data is fresh.
**Effort:** 45 minutes. One column in the view, data already available.

---

### AUX-012 — Platform Health Log Shows No Trend (Severity: LOW)

**Problem:** The platform health log page shows the latest health snapshot. There is no trend line showing whether platform health is improving or degrading over time.

**Improvement:** Add a simple 24-hour sparkline chart using Chart.js (already loaded) showing the last 24 `monitor:platform` health scores. Use `InfraPlatformHealthLog::recent(24)->pluck('score')`.

**Impact:** Operators see trends, not just the last snapshot.
**Effort:** 2 hours. Chart.js line chart, `platformHistory` API endpoint already exists.

---

## Priority Matrix

| ID | Issue | Severity | Effort | Priority |
|----|-------|----------|--------|---------|
| AUX-001 | Dashboard quick-action row | MEDIUM | 1h | P1 |
| AUX-004 | Standardize destructive confirmations | MEDIUM | 2h | P1 |
| AUX-005 | Canvas Ctrl+S + save toast | LOW | 2h | P1 |
| AUX-007 | Marketplace unpublish toggle | MEDIUM | 1h | P1 |
| AUX-008 | Lifecycle state distribution chart | MEDIUM | 1.5h | P1 |
| AUX-009 | Lifecycle event timeline | MEDIUM | 1.5h | P1 |
| AUX-010 | Incident severity filter | MEDIUM | 1h | P1 |
| AUX-002 | Dashboard KPI cards linked | LOW | 0.5h | P2 |
| AUX-006 | Theme Builder "last edited" column | LOW | 0.25h | P2 |
| AUX-011 | Monitoring "last checked" timestamp | LOW | 0.75h | P2 |
| AUX-012 | Platform health trend sparkline | LOW | 2h | P2 |
| AUX-003 | Merge single-item sidebar group | LOW | 0.08h | P3 |

---

## Not Included (Feature Additions — Deferred to Roadmap)

The following were considered but are **FEATURE** classified — not permitted under launch freeze:

- Platform MRR / churn analytics (ANA-1) — POST_LAUNCH_ROADMAP
- Canvas collaborative editing lock (CE-1) — POST_LAUNCH_ROADMAP
- Third-party theme submission portal (ME-1) — POST_LAUNCH_ROADMAP
- Theme rating system (ME-2) — POST_LAUNCH_ROADMAP

---

## Verdict

**ADMIN_UX_SCORE: 90/100**  
12 improvements identified. All are enhancements to existing functionality. None require new tables, new modules, or new architectural systems.

---

*ADMIN_UX_REVIEW — generated 2026-06-22 for RC1 baseline*
