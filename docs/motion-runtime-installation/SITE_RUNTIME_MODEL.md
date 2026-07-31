# Site Runtime Model

## Authorization refinement

Phase 3's `SiteAssetPackageActivationService` originally authorized `activate()`/`rollback()` with `motion.use` — the same ability a Customer holds for read consumption — reasoning that a site owner might self-service their own package. This phase's explicit brief corrects that:

```text
Allowed:
  Platform Admin: create package, activate template, rollback activation
  Customer:       consume runtime projection only

Forbidden:
  Customer: create package, activate assets, access master data
```

`PermissionGatewayAdapter` (Phase 1) already grants a Customer only `motion.view`/`motion.use` and grants a Platform Admin every ability unconditionally — so simply renaming the abilities checked by `activate()`/`rollback()` to `motion.activate_site_package` and `motion.rollback_site_package` (both absent from the Customer allowlist) achieves the correction with **zero changes to `PermissionGatewayAdapter` itself**. `motion.use` remains reserved for read-only projection consumption everywhere in this codebase.

| Method | Ability before Phase 4 | Ability from Phase 4 |
|---|---|---|
| `SiteAssetPackageActivationService::activate()` | `motion.use` | `motion.activate_site_package` |
| `SiteAssetPackageActivationService::rollback()` | `motion.use` | `motion.rollback_site_package` |
| `MotionRuntimeInstallationService::projectSitePackage()` | *(new)* | `motion.use` (Customer-accessible) |

## Site package lifecycle (unchanged from Phase 3, now only admin-reachable)

```text
pending -> active -> (rolled_back | retired)
```

See `docs/motion-persistence/SITE_PACKAGE_MODEL.md` for the full model — Phase 4 does not add fields or change the schema, only who may drive the transitions.

## "Access master data" — what a Customer can never reach

A Customer's `motion.use` ability only ever reaches:

- `MotionRuntimeInstallationService::projectSitePackage()` — returns `RuntimeProjectionDTO` only (see [PROJECTION_SECURITY.md](PROJECTION_SECURITY.md)).

It can never reach `TemplateAssetPackageService`, `SiteAssetPackageActivationService::activate()`/`rollback()`, or any module-internal model — those all require abilities a Customer's permission gateway entry never grants, verified in `tests/Feature/Modules/MotionGraphics/RuntimeInstallation/RuntimeInstallationSecurityTest.php`.
