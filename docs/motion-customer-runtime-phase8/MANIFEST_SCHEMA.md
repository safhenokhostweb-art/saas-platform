# Manifest Schema

`schema_version: "1.0"` — `App\Modules\MotionGraphics\DTO\CustomerRuntimeManifest::SCHEMA_VERSION`.

## Shape

```json
{
  "available": true,
  "schema_version": "1.0",
  "site_reference": "42",
  "template_reference": "theme-template:...",
  "package_id": "b1e2...uuid",
  "package_version": 1,
  "generated_at": "2026-08-05T12:00:00+00:00",
  "assets": [
    {
      "asset_id": "motion-1",
      "published_version": 1,
      "optimized_reference": "/motion/motion-1/v1/output.json",
      "checksum": "a1b2...64-hex-chars",
      "asset_type": "lottie",
      "theme_tokens": { "primary": "#4f46e5" },
      "placement_rules": { "section": "hero" },
      "responsive_rules": {}
    }
  ],
  "manifest_checksum": "c3d4...64-hex-chars"
}
```

`available: false` is the only other shape ever returned: `{"available": false, "reason": "<stable code>"}` — see the failure-code table in HEALTH_CHECK_RESULTS.md/TEST_REPORT.md.

## Allowed vs. forbidden, and how each is enforced

| Allowed (task spec) | Field | Enforced by |
|---|---|---|
| Published runtime asset references | `assets[].asset_id`, `published_version` | `MotionAssetReadContract::findByCanonicalId()` only ever returns published/approved versions (Phase 2, unchanged) |
| Optimized output references | `assets[].optimized_reference` | Root-relative only; customer-site's `MotionRuntimeManifestReader::isSafeRelativeReference()` rejects anything else |
| Approved theme tokens | `assets[].theme_tokens` | `ForbiddenFieldGuard::assertClean()` on every `CustomerRuntimeAssetEntry` construction |
| Placement rules | `assets[].placement_rules` | Same guard |
| Responsive rules | `assets[].responsive_rules` | Same guard; always `{}` today (honest "not implemented" — see CustomerRuntimeAssetEntry's own docblock) |
| Checksums | `assets[].checksum`, `manifest_checksum` | Computed server-side (sha256), validated client-side against a strict `^[a-f0-9]{64}$` pattern |
| Package identity | `package_id`, `package_version`, `template_reference` | Read directly from the host-owned `SiteAssetPackage`/`TemplateAssetPackage` models |

| Forbidden (task spec) | Why it can never appear |
|---|---|
| Source definitions | `MotionAssetReadContract`'s own contract forbids returning `source_definition` at the read-model level (Phase 2) — the field does not exist anywhere in this chain |
| AI metadata / prompts | Same — `ai_metadata`/`prompt` forbidden at the read-model boundary |
| Editable schema | Same |
| Internal storage paths | Never read; only `optimized_reference` (a URL path) crosses the boundary |
| Ownership internals | `CustomerRuntimeManifest`/`CustomerRuntimeAssetEntry` have no field for it — structurally absent |
| Approval identities | Same |

Defense in depth, not single-layer trust: `ForbiddenFieldGuard::assertClean()` runs server-side at DTO construction (platform side), **and** `MotionRuntimeManifestReader::isStructurallyValid()` independently re-checks for the same forbidden keys client-side before accepting any manifest (customer-site side) — see its `foreach (['source_definition', 'ai_metadata', ...] as $forbidden)` loop. Either layer failing independently would still stop a leak.

## Explicit schema versioning

`MotionRuntimeManifestReader::SUPPORTED_SCHEMA_VERSIONS = ['1.0']` — any other value (including a hypothetical future `"2.0"` before this reader is updated) is rejected outright (`isStructurallyValid()` returns `false`, `read()` returns `null`, the Blade component renders nothing). Proven by `MotionRuntimeManifestReaderTest::test_it_rejects_an_unsupported_schema_version`.
