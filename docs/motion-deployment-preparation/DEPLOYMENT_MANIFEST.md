# Deployment Manifest

`App\Modules\MotionGraphics\DTO\DeploymentManifest` (task scope item 2) — immutable.

## Fields

| Field | Allowed | Source |
|---|---|---|
| `uuid` | ✅ | generated fresh per `prepareDeployment()` call |
| `siteReference` | ✅ | the platform-trusted tenant site id — never the caller's claimed value |
| `templateReference` | ✅ | `TemplateAssetPackage.template_reference` (Phase 3) |
| `packageVersion` | ✅ | `TemplateAssetPackage.package_version` (Phase 3) |
| `assetReferences` | ✅ | `list<MotionAssetReferenceDTO>` (Phase 1) |
| `runtimeProjectionReferences` | ✅ | `list<RuntimeProjectionDTO>` (Phase 1/2) |
| `checksum` | ✅ | see below |
| `compatibilityResult` | ✅ | `ValidationReport` (Phase 1) — always `pass()`, because any failing check throws before a manifest is ever constructed |
| `status` | (lifecycle bookkeeping, not content) | see [DEPLOYMENT_LIFECYCLE.md](DEPLOYMENT_LIFECYCLE.md) |

## Forbidden

Source files, AI metadata, internal storage paths — never present, by construction: `assetReferences`/`runtimeProjectionReferences` only ever hold already-allowlisted `MotionAssetReferenceDTO`/`RuntimeProjectionDTO` values, both of which already run `ForbiddenFieldGuard` at their own construction time (Phase 1). `tests/Feature/Modules/MotionGraphics/DeploymentPreparation/DeploymentPreparationSecurityTest.php::test_source_and_ai_data_can_never_leak_into_the_manifest` verifies this via reflection — a manifest has *only* the nine fields listed above, nothing else.

## Immutability

Every content field is `readonly`. A lifecycle transition never mutates a manifest — `withStatus()` returns a **new** instance with the same content and a different `status`:

```php
$validated = $manifest->withStatus(DeploymentManifest::STATUS_VALIDATED);
// $manifest is untouched; $validated is a new object.
```

## Checksum

```php
hash('sha256', json_encode([
    'site_reference' => ..., 'template_reference' => ..., 'package_version' => ...,
    'assets' => [...], 'projections' => [...],
]))
```

Computed **only** from content fields — `uuid` and `status` are excluded, so:

- Two manifests built from identical site/template/assets/projections have the **same checksum** even though their `uuid`s differ (`DeploymentManifestTest`/`MotionDeploymentPreparationServiceTest::test_the_checksum_is_stable_for_identical_content`).
- A lifecycle transition (`withStatus()`) never changes the checksum — it stays a stable integrity fingerprint of *what* is being deployed throughout the whole approval flow.
