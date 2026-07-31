# Template Activation Model

```text
Template
   |
   v
TemplateAssetPackage              (frozen {asset_id, version} set — Phase 3)
   |
   v
Resolve Published Asset Versions  (MotionAssetReadContract — Phase 2)
   |
   v
Create SiteAssetPackage           (Phase 3, natural key site_id+template_reference)
   |
   v
Create PackageSnapshot            (Phase 3, immutable full-state record)
   |
   v
Create AssetActivation records    (Phase 3, append-only, one per install/upgrade)
```

All of this is Phase 3 machinery, reused verbatim — Phase 4 adds the two steps around it: template-package idempotency ([IDEMPOTENCY_RULES.md](IDEMPOTENCY_RULES.md)) and the richer compatibility gate below.

## Template Builder Compatibility (task scope item 4)

`TemplateBuilderCompatibilityValidator::validate(MotionAssetReferenceDTO $asset, array $rules): ValidationReport` runs once per required asset, **before** `SiteAssetPackageActivationService::activate()` is even called — an invalid package never reaches persistence.

| Dimension | Rule key | How it's checked |
|---|---|---|
| Asset type | `asset_type` | exact match against `asset.type` |
| Section | `section` | tag membership against `asset.compatibility['tags']` |
| Industry | `industry` | tag membership against `asset.compatibility['tags']` |
| Theme | `theme_tokens_required` | requires `asset.compatibility['theme_support'] === true` |
| Performance budget | — | `PerformanceBudgetDecision::notImplemented()` |
| Responsive support | — | `ResponsiveSupportDecision::notImplemented()` |
| License status | — | `LicenseCompatibilityDecision::notImplemented()` |

### Why three dimensions are explicit stubs

`AssetReadModel` (`docs/motion-read-contracts/ASSET_READ_CONTRACT.md`) does not expose file-weight, breakpoint/responsive, or license data today — there is nothing real to check. Rather than fabricate a pass (silently skip) or fabricate a fail (block every activation for a check that doesn't exist), each stub returns an **explicit, auditable "not implemented, defaults to pass"** decision with its own stable code (`performance_budget_not_implemented`, `responsive_support_not_implemented`, `license_check_not_implemented`). A future phase that adds real data to a read contract only needs to replace one decision's implementation — the validation pipeline's shape does not change. This mirrors the same pattern already established for license checks in `docs/motion-persistence/ACTIVATION_FLOW.md`.

### Reject invalid package

Any single dimension failing rejects the **entire** activation with the stable code `compatibility_rejected` — validation runs to completion for all required assets' relevant checks before any DB write, and the outer transaction ([RUNTIME_INSTALLATION_FLOW.md](RUNTIME_INSTALLATION_FLOW.md)) guarantees nothing partial is left behind even if a later asset in the same package fails.

## Defense in depth

`SiteAssetPackageActivationService::activate()` (Phase 3) still runs its own simpler compatibility check via `MotionAssetResolverInterface::validateCompatibility()` immediately afterward. This is deliberate redundancy, not duplicated business logic — both checks ultimately read from the same `MotionAssetReferenceDTO.compatibility` allowlist; Phase 4's validator is strictly richer (more named dimensions), and Phase 3's check remains as an independent recheck at its own boundary, per `docs/motion-saas-integration/AUTHORIZATION_MATRIX.md`'s "rechecked at every boundary" rule.
