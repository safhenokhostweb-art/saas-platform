# Template Asset Read Contract

`TemplateAssetReadContract` resolves a template's declared asset references against the real catalog.

```text
resolveReferences(references: list<{asset_id, version}>) -> TemplateAssetResolutionReadModel
```

```text
Template
  |  owned by the SaaS Platform, not the module
  v
Asset References
  |  {asset_id, version} pairs the caller (Laravel adapter, on behalf of
  |  the platform's template selection) already knows about
  v
Published Versions
  |  what this contract actually confirms
```

The module does not own "template" identity — per `docs/motion-saas-integration/INTEGRATION_BOUNDARY.md`, template selection belongs to the SaaS Platform. `TemplateAssetReader` never looks up a template; it only confirms which caller-supplied references currently resolve to a readable version by delegating to `MotionAssetReadContract::findByCanonicalId()` for each one — it never queries `MotionGraphic`/`MotionGraphicVersion` directly, so it can never diverge from the single source of truth for lifecycle/ownership rules.

## Allowed output — `TemplateAssetResolutionReadModel`

```json
{ "resolved": ["AssetReadModel, ..."], "unresolved": ["asset-id-that-could-not-be-resolved", "..."] }
```

A reference is `unresolved` — never an exception — when it is malformed, unknown, or not currently readable (draft/review, or retired without policy). This lets a template render a safe fallback for a missing asset instead of failing entirely.

## Laravel adapter usage

`App\Modules\MotionGraphics\Adapters\TemplateAssetResolverAdapter::resolveRequiredAssets()` resolves the manifest's `required_assets` and `optional_assets` separately: any unresolved **required** reference fails the whole call closed (`unresolved_required_asset`); an unresolved **optional** reference is silently dropped from the returned `TemplateAssetRequestDTO`.
