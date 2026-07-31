<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\VersionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 10 (Feature): "rollback preparation" — a package
 * installed through the Phase 4 orchestrator can still be rolled back
 * through Phase 3's SiteAssetPackageActivationService::rollback(),
 * which Phase 4 tightened to Platform-Admin-only (see
 * docs/motion-runtime-installation/README.md's Authorization Rules).
 */
class RollbackPreparationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
    }

    public function test_an_admin_can_roll_back_a_freshly_installed_package(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 3);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null, $context
        );
        $item = SiteAssetPackageItem::query()->first();

        $this->app->instance(MotionVersionReadContract::class, new class implements MotionVersionReadContract {
            public function resolveVersion(string $assetId, int $version): ?VersionReadModel {
                return $version === 2 ? new VersionReadModel($assetId, 2, 'published', 1, false) : null;
            }
            public function resolvePublishedVersion(string $assetId): ?VersionReadModel { return null; }
            public function lineage(string $assetId): array { return []; }
        });
        $this->app->forgetInstance(SiteAssetPackageActivationService::class);

        $activation = $this->app->make(SiteAssetPackageActivationService::class)->rollback($item, 2, $context);

        $this->assertSame(2, $activation->version);
        $this->assertSame(3, $activation->previous_version);
        $this->assertSame('rollback', $activation->activation_type);
        $this->assertSame(2, $item->fresh()->active_version);
    }

    public function test_a_customer_cannot_roll_back_a_package(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 3);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');
        $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null, $context
        );
        $item = SiteAssetPackageItem::query()->first();

        $this->actingAs($this->customer($site));
        $this->app->forgetInstance(SiteAssetPackageActivationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface::class);

        try {
            $this->app->make(SiteAssetPackageActivationService::class)->rollback(
                $item, 1, new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }

        $this->assertSame(1, AssetActivation::query()->count());
    }
}
