# Plan Integration

## The finding this document exists to explain honestly

Task section 3 asks to support Basic/Business/Premium/Enterprise-style plan tiers using "existing Plan management." **There is no Plan model, no `plan_modules` relation, and no Site→Plan resolution anywhere in this codebase** — verified directly (AUDIT_REPORT.md §2): the only "Plan" is `App\Models\SupportPlan`, a support/SLA tier entirely unrelated to module bundling.

Per the explicit "Do NOT redesign architecture" rule, **this phase does not create a Plan model.** Inventing one would itself be exactly the kind of unrequested architecture change the rule forbids, and would risk becoming a second, Motion-specific, half-built "plan system" alongside whatever real Plan layer the platform eventually builds for all modules — a duplication risk, not a solution.

## What "do not hardcode plans" means here, satisfied by *not* hardcoding anything plan-shaped

Instead of a coded `Plan` entity, the tiers map onto the real, already-existing commercial primitives:

| Task's example tier | Real mechanism today |
|---|---|
| Basic: unavailable | No `ModuleLicense` for the site → `RealMotionLicenseGateway` denies (EntitlementRulesTest, "a site with no license is denied") |
| Business: limited Motion | A `ModuleLicense` with a near-term `expires_at` (admin-controlled term, e.g. a quarterly renewal) — "limited" expressed as *time-bounded*, the only limitation dimension `ModuleLicense` actually models today (no per-asset-count limiting exists to model a "limited" catalog subset without inventing new schema) |
| Premium: full Motion | A `ModuleLicense` with `expires_at = null` (never expires) |
| Enterprise: unlimited/custom | Same mechanism, `granted_by`/`support_eligibility`/custom `expires_at` — "custom" is already whatever an admin sets via `ModuleLicenseController::grant()`, no new field needed |

This is not a workaround bolted on after the fact — it is the literal, direct application of the *only* temporality/tiering dimension `ModuleLicense` already exposes (`expires_at`), reused exactly as `ModuleLicenseController::grant()`/`ModuleLicenseService::grantByAdmin()` already support (both accept an optional `?Carbon $expiresAt`), proven in EntitlementRulesTest's `test_upgrade_extending_expiry_restores_entitlement`.

## Upgrade / downgrade

Both are the same operation on the same `ModuleLicense` row — extend `expires_at` (upgrade) or shorten/null it out toward expiry (downgrade) — no separate "upgrade" or "downgrade" code path exists or is needed; `hasValidLicense()`'s existing `expires_at > now()` check is the only logic involved, unmodified. Proven directly: EntitlementRulesTest's `test_upgrade_extending_expiry_restores_entitlement` exercises exactly this sequence (grant → let lapse → extend again) against the real gateway.

## If a real Plan layer is ever built platform-wide

Nothing here would need to change. A future `Plan` model that includes Motion Graphics would, structurally, still need to end up creating the same `ModuleLicense` row this phase already produces (via purchase or an equivalent plan-provisioning step) — `RealMotionLicenseGateway`/`ModuleLicenseAdapter::checkEntitlement()` would keep working unmodified, exactly as it already does for every other paid module a hypothetical Plan might one day bundle.
