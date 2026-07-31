<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 10 (Security): no AI metadata leakage, no source
 * exposure, no customer access to master data, tenant isolation.
 */
class RuntimeInstallationSecurityTest extends TestCase
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

    public function test_generated_projections_never_carry_ai_metadata_or_source_fields(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null,
            new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
        );

        $this->assertCount(1, $plan->projections);
        $projection = $plan->projections[0];

        // ForbiddenFieldGuard already ran at DTO construction time inside
        // ReadModelMapper; this is the same "recheck at every boundary"
        // pattern used throughout Phase 1-3.
        ForbiddenFieldGuard::assertClean($projection->themeTokens, 'theme_tokens');
        ForbiddenFieldGuard::assertClean($projection->placementRules, 'placement_rules');
        ForbiddenFieldGuard::assertClean($projection->assetReference->compatibility, 'compatibility');

        $allowedProjectionFields = ['assetReference', 'publishedVersion', 'themeTokens', 'placementRules', 'optimizedReference'];
        $actualFields = array_map(
            fn (\ReflectionProperty $p): string => $p->getName(),
            (new ReflectionClass($projection))->getProperties()
        );
        $this->assertEqualsCanonicalizing($allowedProjectionFields, $actualFields);
    }

    public function test_a_customer_cannot_access_master_data_by_attempting_to_create_a_package(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->customer($site));
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $decision = null;
        try {
            $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-1']], [], [], null,
                new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $decision = $e->failureCode;
        }

        $this->assertSame('ability_denied', $decision);
        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_a_customer_may_only_consume_projections_never_manage_packages(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null,
            new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
        );

        $this->actingAs($this->customer($site));
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface::class);

        $projections = $this->app->make(MotionRuntimeInstallationService::class)->projectSitePackage(
            $plan->sitePackage, new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1')
        );

        $this->assertCount(1, $projections);
    }

    public function test_tenant_isolation_is_enforced_end_to_end(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $this->bindPublishedAsset('motion-1', 1);
        $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null,
            new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-1'),
        );

        $this->assertSame(1, SiteAssetPackage::query()->where('site_id', $siteA->id)->count());
        $this->assertSame(0, SiteAssetPackage::query()->where('site_id', $siteB->id)->count());
    }
}
