# Motion Graphics Read Contracts

Status: **implemented — Phase 2 of the Motion Graphics SaaS integration**. This layer replaces Phase 1's fail-closed placeholders with real, provider-neutral read contracts owned by the standalone Motion Graphics module.

```text
Laravel Adapter (app/Modules/MotionGraphics/)
        |
        | provider-neutral read contracts
        v
Motion Graphics Read Contracts (packages/saas-platform/modules/motion-graphics/src/Contracts/Read/)
        |
        | database-backed providers, module-owned
        v
Standalone Motion Graphics Module (its own tables, its own invariants)
```

The module remains the source of truth. The Laravel adapter never queries `MotionGraphic`/`MotionGraphicVersion`/`MotionGraphicDeployment` directly — it only calls the five read contracts below, which live inside the module and enforce the module's own lifecycle/ownership rules before anything crosses the boundary. Only published and approved information may cross; draft/review content, source definitions, editable schemas, AI metadata, and internal ownership fields never do.

## Contracts

| Contract | Location | Purpose |
|---|---|---|
| `MotionAssetReadContract` | `src/Contracts/Read/MotionAssetReadContract.php` | canonical asset/version resolution — see [ASSET_READ_CONTRACT.md](ASSET_READ_CONTRACT.md) |
| `MotionRuntimeProjectionReadContract` | `src/Contracts/Read/MotionRuntimeProjectionReadContract.php` | customer-safe published projection — see [RUNTIME_PROJECTION_CONTRACT.md](RUNTIME_PROJECTION_CONTRACT.md) |
| `MotionVersionReadContract` | `src/Contracts/Read/MotionVersionReadContract.php` | version lifecycle and lineage — see [VERSION_READ_MODEL.md](VERSION_READ_MODEL.md) |
| `TemplateAssetReadContract` | `src/Contracts/Read/TemplateAssetReadContract.php` | template asset reference resolution — see [TEMPLATE_READ_MODEL.md](TEMPLATE_READ_MODEL.md) |
| `SiteAssetPackageReadContract` | `src/Contracts/Read/SiteAssetPackageReadContract.php` | installed site deployment state — see [SITE_PACKAGE_READ_MODEL.md](SITE_PACKAGE_READ_MODEL.md) |

Each contract's return values are plain, framework-agnostic read-model classes under `src/Contracts/Read/Model/` — no Laravel dependency, no `App\` dependency, no ORM models crossing the boundary.

## Providers

Database-backed implementations live in `src/Support/Read/` and are bound by the module's own `MotionGraphicsServiceProvider`:

- `DatabaseMotionAssetReader`
- `PublishedRuntimeProjectionProvider`
- `DatabaseMotionVersionReader`
- `TemplateAssetReader`
- `SitePackageReader`

See [SECURITY_BOUNDARY.md](SECURITY_BOUNDARY.md) for how each provider enforces data minimization, and [INTEGRATION_UPDATE.md](INTEGRATION_UPDATE.md) for how the Phase 1 Laravel adapters now consume them.

## Path deviation from the task brief

The task's suggested location was `packages/saas-platform/modules/motion-graphics/contracts/read/`. This implementation instead uses `packages/saas-platform/modules/motion-graphics/src/Contracts/Read/`, because the module's PSR-4 autoload root (declared in both the module's own `composer.json` and the root `composer.json`) is `src/`. Placing files outside `src/` would have required editing the root autoload map and running `composer dump-autoload` — a build-configuration change with a larger blast radius than a namespace choice. The namespace (`SaaSPlatform\Modules\MotionGraphics\Contracts\Read`) still matches the suggested `contracts/read/` grouping exactly; only the physical path differs.
