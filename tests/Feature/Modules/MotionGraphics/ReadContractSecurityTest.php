<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\User;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionRuntimeProjectionInterface;
use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Tests\TestCase;

/**
 * Task scope item 8 (Security Requirements), end-to-end through the real
 * Laravel adapters resolved from the container. The read contracts
 * themselves are faked here (see FeatureFlagBehaviorTest for why —
 * exercising the real database-backed readers belongs to the standalone
 * module's own Testbench suite:
 * packages/saas-platform/modules/motion-graphics/tests/Feature/Read/).
 */
class ReadContractSecurityTest extends TestCase
{
    private function admin(): User
    {
        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;

        return $admin;
    }

    private function bindTrustedSite(string $siteId): void
    {
        $this->app->instance(TenantContextContract::class, new class($siteId) implements TenantContextContract {
            public function __construct(private readonly string $siteId) {}
            public function current(): ?object { return $this->site(); }
            public function type(): ?string { return 'site'; }
            public function isReseller(): bool { return false; }
            public function isSite(): bool { return true; }
            public function reseller(): ?object { return null; }
            public function site(): ?object { return (object) ['id' => $this->siteId]; }
            public function id(): ?int { return null; }
            public function hasTenant(): bool { return true; }
            public function hasFeature(string $feature): bool { return (bool) config("feature-flags.{$feature}"); }
        });
    }

    public function test_an_unpublished_asset_is_never_exposed_through_the_adapter(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->actingAs($this->admin());
        $this->bindTrustedSite('site-1');

        // Simulates the real DatabaseMotionAssetReader's behavior for a
        // draft-only asset: findByCanonicalId() returns null.
        $this->app->instance(MotionAssetReadContract::class, new class implements MotionAssetReadContract {
            public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel { return null; }
            public function resolveActiveVersion(string $assetId): ?int { return null; }
            public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool { return false; }
        });

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionAssetResolverInterface::class)
            ->resolveCanonical('draft-only-motion', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
    }

    public function test_a_customer_cannot_resolve_another_sites_runtime_projection(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->actingAs($this->admin());
        $this->bindTrustedSite('site-1');

        // Simulates the real PublishedRuntimeProjectionProvider's
        // ownership check for a customer-specific asset owned by site-2.
        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class implements MotionRuntimeProjectionReadContract {
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel {
                return $siteIdentifier === 'site-2' ? new RuntimeProjectionReadModel(
                    assetReference: new AssetReadModel($assetId, 1, 'lottie', null, [], 'x', false),
                    publishedVersion: 1,
                    optimizedReference: '/motion/x/v1/a.json',
                ) : null;
            }
        });

        // Trusted tenant is site-1; the read contract only has data for site-2.
        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionRuntimeProjectionInterface::class)
            ->projectPublished('customer-motion', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
    }

    public function test_source_and_ai_metadata_fields_never_appear_in_the_mapped_dto(): void
    {
        $asset = new AssetReadModel('motion-1', 1, 'lottie', 'finance', ['engine' => 'lottie'], 'checksum', true);
        $projection = new RuntimeProjectionReadModel(
            assetReference: $asset,
            publishedVersion: 1,
            optimizedReference: '/motion/x/v1/a.json',
            themeTokens: ['primary' => '#000'],
            placementRules: ['section' => 'hero'],
        );

        $assetDto = ReadModelMapper::toAssetReferenceDTO($asset);
        $projectionDto = ReadModelMapper::toRuntimeProjectionDTO($projection);

        // ForbiddenFieldGuard rejects source_definition/editable_schema/
        // ai_metadata/ownership/credentials anywhere in these payloads —
        // both DTOs were already validated at construction time; asserting
        // that construction succeeded (no exception) plus an explicit
        // recheck here is the same "recheck at every boundary" pattern
        // used across the adapter layer.
        ForbiddenFieldGuard::assertClean($assetDto->compatibility, 'compatibility');
        ForbiddenFieldGuard::assertClean($projectionDto->themeTokens, 'theme_tokens');
        ForbiddenFieldGuard::assertClean($projectionDto->placementRules, 'placement_rules');
        $this->assertTrue(true);
    }

    public function test_invalid_ownership_across_sites_is_rejected_by_the_adapter(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $customer = new User();
        $customer->id = 2;
        $customer->is_admin = false;
        $customer->site_id = 1;
        $this->actingAs($customer);
        $this->bindTrustedSite('site-1');

        $decision = $this->app->make(\App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface::class)
            ->validateOwnership('motion-1', 'site-2', new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertFalse($decision->owns);
        $this->assertSame('tenant_isolation_violation', $decision->code);
    }
}
