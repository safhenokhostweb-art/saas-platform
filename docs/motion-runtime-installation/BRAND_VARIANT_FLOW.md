# Brand Variant Preparation

Task scope item 5 asks for *preparation*, not implementation — "No actual AI generation in this phase." This phase adds exactly one new type, `App\Modules\MotionGraphics\DTO\BrandVariantReference`, expressing the intended shape without any new persistence (no migration was added for this — see `docs/motion-persistence/` for the tables this phase reuses unchanged).

```text
Master Asset
    |          (immutable — nothing in this phase, or any phase so far,
    |           writes to the standalone module)
    v
Brand Variant
    |          (BrandVariantReference: variantId, masterAssetId, masterVersion,
    |           siteId, approvalStatus, lineageParentReference)
    v
Site Runtime Asset
    (a customer-safe RuntimeProjectionDTO, once the variant is bound into
     a SiteAssetPackage the same way a plain asset reference is)
```

## Rules and how each is enforced today

| Rule | Enforcement |
|---|---|
| Master never changes | Structural — `BrandVariantReference` only ever *references* a master asset/version by id; no code path in this phase writes to the module. |
| Variant belongs to a site | `BrandVariantReference.siteId` is a required constructor argument. `MotionRuntimeInstallationService::installTemplateForSite()` doesn't yet cross-check it against the trusted tenant beyond the approval gate below — a future phase wiring real variant persistence should add that check where the variant is looked up. |
| Variant requires approval | `installTemplateForSite()` rejects with `brand_variant_not_approved` before anything is written, whenever a `BrandVariantReference` is supplied and `approvalStatus !== 'approved'`. A plain `string` brand reference (Phase 3's existing shape) is still accepted unchanged and carries no approval gate — it predates this concept. |
| Variant has a lineage reference | `BrandVariantReference.lineageParentReference` (nullable) — mirrors the module's own version-lineage pattern (`MotionGraphicVersion.parent_version`). |

## What is deliberately not built this phase

- No `brand_variants` table or Eloquent model — `SiteAssetPackage.brand_variant_reference` (a plain string, Phase 3) remains the only persisted representation; `BrandVariantReference` is a value object a caller can construct and pass in, not yet a stored, queryable entity.
- No approval workflow (no UI, no approval-state transition service) — `approvalStatus` is read, never written, by this phase's code.
- No AI generation of variant content, per the task's explicit instruction.

## Test coverage

`tests/Unit/Modules/MotionGraphics/DTO/BrandVariantReferenceTest.php` (the shape and `isApproved()`) and `MotionRuntimeInstallationServiceTest::test_it_rejects_an_unapproved_brand_variant` (the enforcement).
