# Motion Graphics — Phase 7: Admin Management & Internal Site Activation

The first operational admin workflow for Motion Graphics: Platform-Admin-only, internal-testing-only, no customer-facing surface anywhere.

## Documents in this directory

- [ADMIN_WORKFLOW.md](ADMIN_WORKFLOW.md) — the full journey (select site → dashboard → assign package → activate → preview) and how it maps to routes/controller/service methods.
- [PERMISSION_MODEL.md](PERMISSION_MODEL.md) — the four admin abilities, how they're enforced, and why nothing needed to change in the underlying permission gateway.
- [INTERNAL_SITE_ACTIVATION.md](INTERNAL_SITE_ACTIVATION.md) — how a specific site becomes "the internal test site" for a given admin action, and why every Motion Graphics admin action is site-scoped even when the data shown is platform-wide.
- [RUNTIME_PREVIEW.md](RUNTIME_PREVIEW.md) — what the preview page shows, what it deliberately never shows, and how that's enforced.
- [SECURITY_RULES.md](SECURITY_RULES.md) — every enforced boundary and its test.
- [TEST_REPORT.md](TEST_REPORT.md) — results.

## One-paragraph summary

Phase 7 adds `MotionGraphicsAdminService` — a thin composition layer over the Phase 3-6 services, gated behind the four admin abilities the task specifies (`motion.view_library`, `motion.manage_template_package`, `motion.activate_site_package`, `motion.view_runtime_projection`) — plus a Platform-Admin-only controller and three minimal RTL/Persian Blade pages (`/admin/motion-graphics/...`) that let an admin pick a template, assign a Motion package to it, activate that package for one chosen internal test site, and preview the resulting runtime projection. No new business logic was introduced: every action delegates to `TemplateAssetPackageService`, `MotionRuntimeInstallationService`, or `TemplateBuilderCompatibilityValidator`, all built in Phases 3-4. The feature flag stays off by default (global config is `null` since Phase 6); nothing in this phase turns it on for any site. A genuine, pre-existing, unrelated bug in `App\Http\Middleware\CheckTenantMaintenance` (a call to a method that never existed on `TenantContextContract`) was fixed as a required root-cause correction — see [ADMIN_WORKFLOW.md](ADMIN_WORKFLOW.md) for why this phase surfaced it.
