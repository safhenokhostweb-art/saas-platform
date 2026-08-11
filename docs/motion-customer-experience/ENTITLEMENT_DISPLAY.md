# Entitlement Display

No new entitlement table, no `MotionLicense`, no `AssetLicense`. The customer dashboard only ever *reads* the two entitlement layers that already exist:

| Shown as | Read via | Layer |
|---|---|---|
| License Status | `ModuleLicenseService::hasValidLicenseBySlug()` (Phase 9) | Module-granularity — does this site have Motion Graphics at all |
| My Assets (runtime) | `MotionBespokeCustomerRuntimeService::bespokeAssetsForSite()` (Phase X) | Asset-granularity — which specific bespoke asset, at which published version |
| Custom Requests status | `MotionBespokeCustomerRuntimeService::commissionsStatusForSite()` (Phase X) | Business/commission status + purchase flag, not a runtime entitlement check — a customer must see "in review" even before any entitlement exists |

Validation performed before anything is shown or purchasable (all pre-existing checks, none reimplemented):
- **Owns asset** — `MotionBespokeCommission.site_id` / `MotionBespokeAssignment.site_id` match, re-verified in the controller against `auth()->user()->customer->sites` before any service call.
- **Asset is assigned** — `MotionBespokeAssignment.status = active` (Phase X).
- **Version is published** — `MotionAssetReadContract::findByCanonicalId()` returns null for anything not `approved`/`published` (module-owned, Phase 1-2, unchanged).
- **License is valid** — `ModuleLicense::isValid()` / the real gateway's `validateEntitlement()` (Phase 6/9, unchanged).

If any check fails, the page shows a plain "not available" state (`runtimeUnavailableReason`) — never a stack trace, never a partial/guessed value.
