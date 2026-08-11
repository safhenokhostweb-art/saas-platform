# Admin Management

## Extension, not a rebuild

Phase 7's admin dashboard (`/admin/motion-graphics/{site}/dashboard`, `MotionGraphicsAdminService::dashboardSummary()`) is extended with two new, real fields — no new page, no new route.

| Task requirement | Field | Source |
|---|---|---|
| Module status | `moduleStatus` (`active` / `inactive` / `not_registered`) | `Module.is_active` for the `motion-graphics` row |
| Licensed sites | `licensedSitesCount` | Real count of distinct sites with an active, non-expired `ModuleLicense` — `App\Models\ModuleLicense`, unmodified query pattern already used elsewhere on the platform |
| Plan assignment | N/A — see PLAN_INTEGRATION.md | No Plan model exists to assign |
| Expiration | Visible per-license via the existing `Admin\ModuleLicenseController` index/edit views (unmodified, already lists `expires_at` for every module including Motion Graphics automatically, since it's a real `Module` row now) | Existing admin surface, not Motion-specific |
| Active packages | `assignedPackages` / `activatedInternalSites` (Phase 7, unchanged) | `TemplateAssetPackage` / `SiteAssetPackage` counts |
| Usage summary | The five counts together | See below |

## Why licensed sites ≠ activated sites, deliberately shown as two different numbers

`activatedInternalSites` (Phase 7) counts sites with the runtime flag on; `licensedSitesCount` (Phase 9) counts sites with a valid `ModuleLicense`. These can legitimately differ — a site can be licensed (paid, or admin-granted) but not yet technically activated (ENTITLEMENT_RULES.md). Showing both separately gives an admin the real picture: "how many customers own this" vs. "how many are actually running it right now" — exactly the distinction task section 5's workflow requires.

## No customer editing (task section 6's explicit rule)

Unchanged from Phase 7: every `admin.motion-graphics.*` route is Platform-Admin-only (`AdminMiddleware` + `MotionAuthorizationInterface`'s existing ability checks). Nothing in Phase 9 adds any customer-reachable write path — proven in `SecurityValidationTest::test_unauthorized_customer_cannot_read_admin_dashboard_licensing_fields` (a customer gets 403 on the dashboard, same as every other admin page).

## Usage tracking — deliberately lightweight (task section 8)

The audit (AUDIT_REPORT.md §7, via the dedicated research pass) found **no existing module-usage-analytics pattern anywhere in this codebase** to follow. Per "do not over-engineer," this phase does not introduce new analytics infrastructure (an events table, a queued aggregation job, a chart). The five dashboard counts (`totalAssets`, `totalTemplates`, `assignedPackages`, `activatedInternalSites`, `licensedSitesCount`) are the entire usage summary — plain, real-time COUNT queries against existing tables, matching the *shape* of usage tracking every other lightweight admin summary on this platform already uses (a handful of counts, not an events pipeline).
