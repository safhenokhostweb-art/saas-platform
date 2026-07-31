# Motion Graphics Runtime Installation & Template Activation Bridge

Status: **implemented — Phase 4 of the Motion Graphics SaaS integration**. This phase answers one question: *"When a Template is selected for a Site, which Motion Graphics assets should this Site receive?"* — by composing Phase 1-3's building blocks into one orchestrated flow, plus a customer-safe runtime projection step. It does **not** activate a real customer-site deployment.

```text
Template Builder
        |
        v
Template Asset Package        (Phase 3 — TemplateAssetPackageService, reused)
        |
        v
Site Asset Package             (Phase 3 — SiteAssetPackageActivationService, reused)
        |
        v
Motion Runtime Projection       (Phase 2 — MotionRuntimeProjectionReadContract, reused)
        |
        v
Customer Site consumption (future — no customer-site changes in this phase)
```

## New in this phase

- `App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService` — the orchestrator. See [RUNTIME_INSTALLATION_FLOW.md](RUNTIME_INSTALLATION_FLOW.md).
- `App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator` — the richer, named-dimension compatibility gate. See [TEMPLATE_ACTIVATION_MODEL.md](TEMPLATE_ACTIVATION_MODEL.md).
- Idempotent template-package resolution (no duplicate rows on a repeated identical request). See [IDEMPOTENCY_RULES.md](IDEMPOTENCY_RULES.md).
- `App\Modules\MotionGraphics\DTO\BrandVariantReference` — brand variant preparation (no AI generation). See [BRAND_VARIANT_FLOW.md](BRAND_VARIANT_FLOW.md).
- Four new audit events (`motion.template.activation.{started,completed,failed}`, `motion.runtime.projection.generated`).

## Authorization Rules (refines Phase 3)

| Action | Platform Admin | Customer |
|---|---|---|
| Create Template Asset Package | ✅ (`motion.manage_template_package`) | ❌ |
| Activate template for a site | ✅ (`motion.activate_site_package`, tightened this phase) | ❌ |
| Roll back a site's activation | ✅ (`motion.rollback_site_package`, tightened this phase) | ❌ |
| Consume runtime projection | ✅ | ✅ (`motion.use`) |

Phase 3 originally let a Customer self-activate/roll back their own site via `motion.use`. This phase's task brief is explicit — "Customer forbidden: create package, activate assets, access master data" — so `SiteAssetPackageActivationService::activate()`/`rollback()` were updated to use two new, admin-only ability strings. See [SITE_RUNTIME_MODEL.md](SITE_RUNTIME_MODEL.md).

## Documents

| Document | Covers |
|---|---|
| [RUNTIME_INSTALLATION_FLOW.md](RUNTIME_INSTALLATION_FLOW.md) | `MotionRuntimeInstallationService`'s full flow and what "activation plan" means |
| [TEMPLATE_ACTIVATION_MODEL.md](TEMPLATE_ACTIVATION_MODEL.md) | The atomic Template→Site activation pipeline and its validation gates |
| [SITE_RUNTIME_MODEL.md](SITE_RUNTIME_MODEL.md) | The authorization refinement and site package lifecycle |
| [PROJECTION_SECURITY.md](PROJECTION_SECURITY.md) | The runtime projection allowlist and how it's enforced |
| [IDEMPOTENCY_RULES.md](IDEMPOTENCY_RULES.md) | The exact idempotency guarantee and its test |
| [BRAND_VARIANT_FLOW.md](BRAND_VARIANT_FLOW.md) | Master → Variant → Site Runtime Asset preparation |
| [INTEGRATION_STATUS.md](INTEGRATION_STATUS.md) | What's wired, what's still deferred, and why |

## What this phase does not do

No customer-site project changes, no asset deployment to customer servers, no real customer-site activation, no public runtime endpoints, no source SVG or AI metadata exposure, no internal ownership data exposure, no authorization/licensing bypass, no Master Asset or Asset Library file modification, no change to the standalone module's ownership model, and no real AI generation for brand variants. No commit, no push, no deployment. The feature flag (`motion_graphics_integration`) remains `false` by default.
