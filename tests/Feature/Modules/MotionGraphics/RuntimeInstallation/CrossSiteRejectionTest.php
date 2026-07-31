<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 10 (Feature): a request claiming a site other than
 * the trusted tenant is rejected before anything is written.
 */
class CrossSiteRejectionTest extends TestCase
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

    public function test_installation_is_denied_when_the_caller_claims_a_site_it_is_not_trusted_for(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $this->bindPublishedAsset('motion-1', 1);

        try {
            $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-1']], [], [], null,
                new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_a_customer_actor_cannot_install_a_template_at_all(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->customer($site));
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        try {
            $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-1']], [], [], null,
                new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }
}
