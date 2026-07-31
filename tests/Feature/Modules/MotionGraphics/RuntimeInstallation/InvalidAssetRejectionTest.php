<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 10 (Feature): an invalid/unpublished asset rejects
 * the whole activation — nothing is written.
 */
class InvalidAssetRejectionTest extends TestCase
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

    public function test_an_unpublished_required_asset_rejects_the_whole_installation(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindUnpublishedAsset('motion-draft');

        try {
            $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-draft']], [], [], null,
                new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('unresolved_required_asset', $e->failureCode);
        }

        $this->assertSame(0, TemplateAssetPackage::query()->count());
        $this->assertSame(0, SiteAssetPackage::query()->count());
    }
}
