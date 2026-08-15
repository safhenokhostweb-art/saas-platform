# Storage Model

Scope of this document: how Motion Graphics customer-facing content (Phase 11) relates to `MediaStorage` (`app/Services/Infrastructure/MediaStorage.php`), and the precise current state of Motion asset persistence — verified by reading code, not assumed.

## 1. What Phase 11 itself stores

Nothing new. The customer dashboard (`MotionGraphicsController`) and its view read only:

- `ModuleLicense` rows (via `ModuleLicenseService`) — database rows, no files.
- `MotionBespokeCommission` / `MotionBespokeAssignment` / `MotionBespokePurchase` rows (via `MotionBespokeCustomerRuntimeService`) — database rows, no files.

Phase 11 does not call `Storage::disk()`, `asset()`, `storage_path()`, or `MediaStorage` directly anywhere in `app/Http/Controllers/Customer/MotionGraphicsController.php` or `resources/views/customer/motion-graphics/index.blade.php` — confirmed by grep, zero matches. There was nothing to route through `MediaStorage` in this phase's own new code, because this phase adds a status/entitlement dashboard, not a file upload or file-serving surface.

## 2. Where Motion binary content actually lives today — verified, not assumed

Grepped the entire Motion Graphics surface (`app/Modules/MotionGraphics/**` and `packages/saas-platform/modules/motion-graphics/**`) for `Storage::disk(`, `storage_path(`, `asset(`, `public_path(`: **zero matches**. Motion code has never bypassed `MediaStorage` — but that is only half the picture.

The module package defines a port, `MotionAssetStorageContract` (`packages/saas-platform/modules/motion-graphics/src/Contracts/MotionAssetStorageContract.php`), described in the package's own `docs/FUTURE_INTEGRATION_CONTRACT.md` as one of several adapters "future platform adapters must bind." Today it has exactly one bound implementation, `InMemoryMotionAssetStorage` (`packages/saas-platform/modules/motion-graphics/src/Support/InMemoryMotionAssetStorage.php`), registered in `MotionGraphicsServiceProvider.php:58`. This is an in-process array — not a disk, not a database table, not object storage. `MotionGraphic`/`MotionGraphicVersion` never store a real filesystem path; their `compiled_assets`/`preview_metadata` JSON columns hold whatever the bound `MotionAssetStorageContract` implementation returned (currently in-memory references only).

**Plain statement of fact:** no Motion asset binary (thumbnail, preview, master source) is durably persisted anywhere today — not on local disk, not on `arvan`, not on `s3`. This predates Phase 11 (it was true through Phases 1–X as well) and Phase 11 does not change it.

## 3. Why this phase does not fix that

The task's storage rule is "all Motion files MUST use the existing MediaStorage abstraction" and explicitly forbids creating another storage system. Binding a real, `MediaStorage`-backed implementation of `MotionAssetStorageContract` would be new production code inside the asset pipeline (`MotionAssetPipeline`, `MotionLibraryService`, `MotionGraphicsServiceProvider`'s container bindings) — outside Phase 11's stated scope of "customer experience & marketplace productization," and risks side effects on `AssetPipelineTest.php` and every other test that currently asserts against the in-memory adapter's behavior. Per this phase's own constraint ("do not redesign the asset ownership/versioning system"), that adapter swap is left as an explicit, separately-scoped follow-up — not silently skipped, documented here instead.

## 4. The correct integration shape for that future work (specification only, not implemented)

When a real implementation is built, it should be a new class — e.g. `App\Modules\MotionGraphics\Support\MediaStorageMotionAssetStorage implements MotionAssetStorageContract` — that does nothing but delegate to the existing `MediaStorage` service, never a parallel disk/config system:

| Content | `MediaStorage` kind | Reasoning |
|---|---|---|
| Thumbnails, safe previews, demo animations | `'public'` | Web-visible by design; same disk category as theme media / sitemap. |
| Master assets, editable sources, internal/unpublished versions | `'private'` | Never web-accessible; same disk category as tenant attachments. |
| Runtime-served, published, customer-authorized output | `'public'`, but only the specific published+assigned object — resolved exclusively through `MotionBespokeCustomerRuntimeService`/`MotionAssetReadContract`, never a raw disk listing. | Matches the existing rule that runtime output is allowlisted and structurally cannot include drafts (see `SECURITY_MODEL.md`). |

This table is the constraint a future implementer must satisfy — it does not require a new disk, a new env variable, or a new config key. `MediaStorage::diskNameFor('public' | 'private')` already resolves correctly today (local by default, `arvan`/`s3` once `MEDIA_DISK` is set) and needs no change to support this.

## 5. What this means for today's customer-facing dashboard

Because no binary is durably stored yet, the customer dashboard correctly never renders an `<img>`/preview of a Motion asset — it shows only metadata (asset id, published version number, commission status). This is not an oversight of Phase 11; it is a direct, honest consequence of §2. `FINAL_REPORT.md`'s "remaining limitations" already flags the absence of a purchase-time preview for the same underlying reason.

## 6. Verification

- Grep for `Storage::disk(`, `asset(`, `storage_path(`, `public_path(` under `app/Modules/MotionGraphics/**`, `packages/saas-platform/modules/motion-graphics/**`, `app/Http/Controllers/Customer/MotionGraphicsController.php`, `resources/views/customer/motion-graphics/**`: zero matches.
- `MediaStorageTest.php` (both platform and customer-site) already covers `MediaStorage`'s own correctness independent of Motion Graphics; no Motion-specific storage test is meaningful to add until §4 is actually implemented, since there is no real disk write path to test yet.
