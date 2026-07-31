# Idempotency Rules

## The exact scenario (task scope item 9)

```text
Same Template + Same Site + Same Package
  second request -> no duplicate package, no duplicate activation, return existing result

Different package version -> create new activation
```

## Template package idempotency — new in this phase

Phase 3's `TemplateAssetPackageService::createTemplateAssetPackage()` always creates a **new** row with an incremented `package_version`, on every call — appropriate for that service's own contract (an explicit "define a new package version"), but not idempotent on its own.

`MotionRuntimeInstallationService::resolveOrCreateTemplatePackage()` closes that gap:

```text
1. resolve every reference (required + optional) to its currently readable version
   — read-only, no write, via TemplateAssetPackageService::resolveReferences() (made
     public this phase for exactly this reuse)
2. fetch the latest TemplateAssetPackage row for this template_reference
3. if one exists AND its required_assets + optional_assets + compatibility_rules
   are byte-for-byte identical to what was just resolved -> reuse it, no new row
4. otherwise -> call createTemplateAssetPackage(), which creates package_version + 1
```

## Site package / activation idempotency — unchanged from Phase 3

`SiteAssetPackageActivationService::bindItem()` already guarantees this at the item level:

```text
existing item, same active_version -> no-op, no new AssetActivation row
existing item, different version   -> AssetActivation (activation_type: upgrade)
no existing item                   -> AssetActivation (activation_type: install)
```

`findOrCreateSitePackage()` reuses the existing `SiteAssetPackage` row (keyed on `site_id` + `template_reference`) rather than creating a new one on a repeat call — see `docs/motion-persistence/SITE_PACKAGE_MODEL.md`.

## `wasAlreadyUpToDate`

`ActivationPlan.wasAlreadyUpToDate` is `true` when an `SiteAssetPackage` already existed for this site+template *and* the count of `AssetActivation` rows across its items did not change during this call — i.e. every item's `bindItem()` call was a no-op. A caller can use this flag to distinguish "nothing changed, here's the existing state" from "something was actually installed/upgraded," matching "return existing result."

## What is *not* idempotent, by design

`PackageSnapshot` — `docs/motion-persistence/ACTIVATION_FLOW.md` already documented this Phase 3 behavior: every `activate()` call captures a fresh, immutable snapshot of the package's current full state, even when nothing changed. This is intentional (a snapshot is an append-only "confirmed valid at time T" record, not a duplicate-avoidance target) and is unaffected by this phase — the task's idempotency scenario names only "package" and "activation," not "snapshot."

## Test coverage

`tests/Unit/Modules/MotionGraphics/Services/MotionRuntimeInstallationServiceTest.php`:
- `test_a_second_identical_installation_is_idempotent` — same refs twice, one `TemplateAssetPackage`, one `AssetActivation`, `wasAlreadyUpToDate === true`.
- `test_a_changed_required_version_creates_a_new_package_and_an_upgrade_activation` — a changed resolution produces a second `TemplateAssetPackage` (`package_version` 2) and a second, `upgrade`-typed `AssetActivation`.
