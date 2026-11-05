<?php

namespace Tests\Support;

use App\Models\Module;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibraryBrowseContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibrarySummaryContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;

/**
 * Shared fixtures for Phase 4 host-side Feature/Security tests: a
 * no-op audit logger (test actors are never persisted), a
 * trusted-tenant-context fake (mirrors Phase 2/3's own test helpers),
 * an admin actor, and fake read contracts simulating one published
 * asset — real database-backed reading is proven by the standalone
 * module's own Testbench suite, not re-proven here.
 */
trait InteractsWithMotionRuntimeInstallation
{
    protected function fakeActivityLogger(): void
    {
        $this->app->instance(ActivityLoggerInterface::class, new class implements ActivityLoggerInterface {
            public function log(string $action, ?string $description = null, ?object $subject = null, array $properties = []): void {}
        });
    }

    /**
     * Phase 6: MotionLicenseGatewayContract's default binding is now the
     * real ModuleLicenseAdapter-backed gateway (RealMotionLicenseGateway),
     * not a fake — so any test whose flow reaches the license gateway and
     * expects an ALLOW outcome needs a real `modules` row to check
     * against, matching database/seeders/ModuleSeeder.php's shape
     * (docs/motion-integration-phase6-audit.md §5).
     */
    protected function registerMotionGraphicsModule(): void
    {
        Module::query()->firstOrCreate(
            ['slug' => RealMotionLicenseGateway::MODULE_SLUG],
            [
                'title' => 'Motion Graphics',
                'description' => 'Motion asset packages for template rendering.',
                'is_active' => true,
                'type' => Module::TYPE_CORE,
                'free_assignable' => true,
                'license_required' => false,
                'marketplace_visible' => false,
            ]
        );
    }

    protected function admin(): User
    {
        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;

        return $admin;
    }

    protected function customer(Site $site): User
    {
        $customer = new User();
        $customer->id = 100 + $site->id;
        $customer->is_admin = false;
        $customer->site_id = $site->id;

        return $customer;
    }

    protected function bindTrustedSite(Site $site): void
    {
        $this->app->instance(TenantContextContract::class, new class($site) implements TenantContextContract {
            public function __construct(private readonly Site $site) {}
            public function current(): ?object { return $this->site; }
            public function type(): ?string { return 'site'; }
            public function isReseller(): bool { return false; }
            public function isSite(): bool { return true; }
            public function reseller(): ?object { return null; }
            public function site(): ?object { return $this->site; }
            public function id(): ?int { return $this->site->id; }
            public function hasTenant(): bool { return true; }
            public function hasFeature(string $feature): bool { return (bool) config("feature-flags.{$feature}"); }
        });
    }

    protected function bindPublishedAsset(string $assetId, int $version, ?string $name = null, ?string $category = null): void
    {
        $this->app->instance(MotionAssetReadContract::class, new class($assetId, $version, $name, $category) implements MotionAssetReadContract {
            public function __construct(
                private readonly string $assetId,
                private readonly int $version,
                private readonly ?string $name,
                private readonly ?string $category,
            ) {}
            public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel {
                return $assetId === $this->assetId
                    ? new AssetReadModel($assetId, $this->version, 'lottie', $this->category, ['license_tier' => 'free'], 'checksum', false, $this->name)
                    : null;
            }
            public function resolveActiveVersion(string $assetId): ?int {
                return $assetId === $this->assetId ? $this->version : null;
            }
            public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool {
                return $assetId === $this->assetId && $version === $this->version && $expectedState === 'published';
            }
        });

        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class($assetId, $version, $name, $category) implements MotionRuntimeProjectionReadContract {
            public function __construct(
                private readonly string $assetId,
                private readonly int $version,
                private readonly ?string $name,
                private readonly ?string $category,
            ) {}
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel {
                if ($assetId !== $this->assetId) {
                    return null;
                }
                return new RuntimeProjectionReadModel(
                    assetReference: new AssetReadModel($assetId, $this->version, 'lottie', $this->category, ['license_tier' => 'free'], 'checksum', false, $this->name),
                    publishedVersion: $this->version,
                    optimizedReference: '/motion/x/v'.$this->version.'/a.json',
                    themeTokens: ['primary' => '#111111'],
                    placementRules: ['section' => 'hero'],
                );
            }
        });
    }

    /**
     * Multi-asset variant of bindPublishedAsset() — needed whenever a flow
     * (e.g. installing a theme with several module:motion blocks) resolves
     * more than one distinct asset_id in the same test. bindPublishedAsset()
     * itself stays single-asset because most existing tests only need one.
     *
     * @param list<array{id: string, version: int, name?: ?string, category?: ?string}> $assets
     */
    protected function bindPublishedAssets(array $assets): void
    {
        $this->app->instance(MotionAssetReadContract::class, new class($assets) implements MotionAssetReadContract {
            public function __construct(private readonly array $assets) {}
            private function find(string $assetId): ?array {
                foreach ($this->assets as $a) {
                    if ($a['id'] === $assetId) {
                        return $a;
                    }
                }
                return null;
            }
            public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel {
                $a = $this->find($assetId);
                return $a ? new AssetReadModel($assetId, $a['version'], 'lottie', $a['category'] ?? null, ['license_tier' => 'free'], 'checksum', false, $a['name'] ?? null) : null;
            }
            public function resolveActiveVersion(string $assetId): ?int {
                return $this->find($assetId)['version'] ?? null;
            }
            public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool {
                $a = $this->find($assetId);
                return $a !== null && $version === $a['version'] && $expectedState === 'published';
            }
        });

        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class($assets) implements MotionRuntimeProjectionReadContract {
            public function __construct(private readonly array $assets) {}
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel {
                foreach ($this->assets as $a) {
                    if ($a['id'] === $assetId) {
                        return new RuntimeProjectionReadModel(
                            assetReference: new AssetReadModel($assetId, $a['version'], 'lottie', $a['category'] ?? null, ['license_tier' => 'free'], 'checksum', false, $a['name'] ?? null),
                            publishedVersion: $a['version'],
                            optimizedReference: '/motion/'.$assetId.'/v'.$a['version'].'/a.json',
                            themeTokens: ['primary' => '#111111'],
                            placementRules: ['section' => 'hero'],
                        );
                    }
                }
                return null;
            }
        });
    }

    /**
     * Phase 7: the admin dashboard's "total assets" count goes through
     * MotionAssetLibrarySummaryContract, whose real implementation reads
     * the standalone module's own `motion_graphics` table — not migrated
     * on the host test database, same limitation as every other
     * module-owned read contract since Phase 2. Fake it here rather than
     * require the module's migrations to run against the host DB.
     */
    protected function bindAssetLibrarySummary(int $count = 0): void
    {
        $this->app->instance(MotionAssetLibrarySummaryContract::class, new class($count) implements MotionAssetLibrarySummaryContract {
            public function __construct(private readonly int $count) {}
            public function countPublishedAssets(): int { return $this->count; }
        });
    }

    /**
     * @param list<AssetReadModel> $globalAssets Only global, published
     *   assets belong here — this fake exists to test
     *   MotionGraphicsAdminService::browseLibrary() against a controlled
     *   result set, mirroring exactly what
     *   DatabaseMotionAssetLibraryBrowseReader's real query guarantees
     *   (ownership_type=global, whereNotNull(published_version)) without
     *   requiring the module's own (unmigrated on this host) tables.
     */
    protected function bindLibraryBrowse(array $globalAssets, int $total): void
    {
        $this->app->instance(MotionAssetLibraryBrowseContract::class, new class($globalAssets, $total) implements MotionAssetLibraryBrowseContract {
            public function __construct(private readonly array $assets, private readonly int $total) {}
            public function browsePublished(array $filters, int $page, int $perPage): array {
                $items = $this->assets;
                if (!empty($filters['search'])) {
                    $needle = mb_strtolower($filters['search']);
                    $items = array_values(array_filter($items, fn ($a) => str_contains(mb_strtolower((string) $a->name), $needle)));
                }
                if (!empty($filters['category'])) {
                    $items = array_values(array_filter($items, fn ($a) => $a->category === $filters['category']));
                }
                return ['items' => $items, 'total' => count($items) === count($this->assets) ? $this->total : count($items), 'page' => $page, 'per_page' => $perPage];
            }
        });

        // browseLibrary() also resolves a thumbnail per item via the
        // existing MotionRuntimeProjectionReadContract (same contract
        // previewAsset() already used) — bind a matching multi-asset fake
        // so the thumbnail lookup for every asset passed above resolves
        // without needing the module's own (unmigrated on this host) tables.
        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class($globalAssets) implements MotionRuntimeProjectionReadContract {
            public function __construct(private readonly array $assets) {}
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel {
                foreach ($this->assets as $asset) {
                    if ($asset->assetId === $assetId) {
                        return new RuntimeProjectionReadModel(
                            assetReference: $asset,
                            publishedVersion: $asset->version,
                            optimizedReference: '/motion/'.$assetId.'/v'.$asset->version.'/a.svg',
                            themeTokens: [],
                            placementRules: [],
                        );
                    }
                }
                return null;
            }
        });
    }

    protected function bindUnpublishedAsset(string $assetId): void
    {
        $this->app->instance(MotionAssetReadContract::class, new class implements MotionAssetReadContract {
            public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel { return null; }
            public function resolveActiveVersion(string $assetId): ?int { return null; }
            public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool { return false; }
        });
    }
}
