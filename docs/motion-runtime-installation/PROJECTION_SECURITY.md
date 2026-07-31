# Runtime Projection Security

`MotionRuntimeInstallationService::projectSitePackage(SiteAssetPackage $sitePackage, MotionAdapterContext $context): list<RuntimeProjectionDTO>` connects a `SiteAssetPackage`'s items to Phase 2's `MotionRuntimeProjectionReadContract` — the bridge task scope item 3 asks for.

## Allowed (exactly the `RuntimeProjectionDTO` shape, Phase 1/2)

```json
{
  "assetReference": "MotionAssetReferenceDTO — asset_id, version, type, compatibility (no ownership fields)",
  "publishedVersion": 3,
  "optimizedReference": "/motion/{slug}/v3/animation.json",
  "themeTokens": { "primary": "#111111" },
  "placementRules": { "section": "hero" }
}
```

## Forbidden

Source SVG, AI data/prompts, editing schema, internal paths — never present, by construction:

1. `PublishedRuntimeProjectionProvider` (Phase 2, inside the standalone module) never reads `source_definition_json`, `editable_schema_json`, or `ai_metadata_json` anywhere in its implementation.
2. `ReadModelMapper::toRuntimeProjectionDTO()` (Phase 2) maps only the read model's own allowlisted fields.
3. `RuntimeProjectionDTO`'s constructor runs `ForbiddenFieldGuard::assertClean()` on `themeTokens`/`placementRules` — a second, independent recheck at the Laravel boundary.
4. `RuntimeInstallationSecurityTest::test_generated_projections_never_carry_ai_metadata_or_source_fields` asserts, via reflection, that a `RuntimeProjectionDTO` has *only* the five allowed properties — a future field added anywhere in the chain without updating this allowlist fails the test immediately.

## Fail-soft per item, never fabricated

`projectSitePackage()` calls `MotionRuntimeProjectionReadContract::projectAsset()` once per `SiteAssetPackageItem`. If a specific item can no longer be projected (e.g. its published version was retired since installation), that **one item is silently omitted** from the result — the method never throws for a single bad item, and it never fabricates a projection for it either. The rest of the package's projections remain trustworthy. This is deliberate: a customer-facing read path should degrade gracefully, not fail an entire page render because one asset became temporarily unavailable.

## Still no public runtime endpoint

This phase adds no route, no controller, no customer-site code. `projectSitePackage()` is a service-layer method only, reachable exclusively through the Laravel container by an authenticated actor holding `motion.use` — see [SITE_RUNTIME_MODEL.md](SITE_RUNTIME_MODEL.md). Wiring an actual public/customer-site-facing endpoint is explicitly out of scope for this phase.
