<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\User;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use Tests\TestCase;

/**
 * Proves the `motion_graphics_integration` flag (task scope item 6) is
 * wired end-to-end through the real service container: disabled means
 * existing behavior is unchanged (deny-all / null, matching the
 * standalone module's own defaults); enabled means the adapter becomes
 * reachable.
 */
class FeatureFlagBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The test actor is never persisted, so swap in a no-op audit
        // logger — MotionAuditEvents delegates to ActivityLoggerInterface,
        // whose real adapter would otherwise write a row referencing a
        // non-existent user_id.
        $this->app->instance(ActivityLoggerInterface::class, new class implements ActivityLoggerInterface {
            public function log(string $action, ?string $description = null, ?object $subject = null, array $properties = []): void {}
        });
    }

    public function test_the_integration_flag_defaults_to_disabled(): void
    {
        $this->assertFalse(config('feature-flags.motion_graphics_integration'));
    }

    public function test_the_adapter_denies_everything_while_the_flag_is_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);

        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;
        $this->actingAs($admin);

        $decision = $this->app->make(MotionAuthorizationInterface::class)
            ->authorize('user:1', 'motion.view', 'motion-1', 'site-1');

        $this->assertFalse($decision->allowed);
        $this->assertSame('motion_adapter_disabled', $decision->code);
    }

    public function test_the_module_contract_bindings_behave_like_the_deny_all_defaults_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);

        $this->assertFalse($this->app->make(PlatformAdminResolverContract::class)->isPlatformAdmin(null));
        $this->assertFalse($this->app->make(SiteResolverContract::class)->exists('1'));
        $this->assertNull($this->app->make(SiteResolverContract::class)->currentSiteIdentifier());
    }

    public function test_the_adapter_becomes_reachable_once_the_flag_is_enabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);

        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;
        $this->actingAs($admin);

        $decision = $this->app->make(MotionAuthorizationInterface::class)
            ->authorize('user:1', 'motion.view', 'motion-1', 'site-1');

        $this->assertTrue($decision->allowed);
    }

    public function test_asset_resolution_still_fails_closed_when_enabled_because_the_asset_does_not_exist(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);

        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;
        $this->actingAs($admin);

        // Real DB-backed lookup (this is the module reading its own
        // table, not the adapter reaching into module internals) — swap
        // in a fake so this test doesn't depend on the module's
        // migrations having been run against the host database.
        $this->app->instance(MotionAssetReadContract::class, new class implements MotionAssetReadContract {
            public function findByCanonicalId(string $assetId, ?int $version = null): ?\SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel { return null; }
            public function resolveActiveVersion(string $assetId): ?int { return null; }
            public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool { return false; }
        });

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionAssetResolverInterface::class)
            ->resolveCanonical('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
    }
}
