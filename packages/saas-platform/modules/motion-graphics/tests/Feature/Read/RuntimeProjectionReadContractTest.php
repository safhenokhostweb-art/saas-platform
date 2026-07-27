<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class RuntimeProjectionReadContractTest extends TestCase
{
    public function test_it_builds_a_projection_for_a_published_global_asset(): void
    {
        $motion = $this->published('runtime-global');

        $projection = app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid);

        $this->assertNotNull($projection);
        $this->assertSame(1, $projection->publishedVersion);
        $this->assertSame($motion->uuid, $projection->assetReference->assetId);
        $this->assertStringStartsWith('/motion/'.$motion->slug.'/v1/', $projection->optimizedReference);
    }

    public function test_it_returns_null_for_an_unpublished_asset(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('runtime-draft'), $this->svgInput(), $this->admin);

        $this->assertNull(app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid));
    }

    public function test_it_returns_null_when_a_customer_specific_asset_is_requested_by_the_wrong_site(): void
    {
        $motion = $this->published('runtime-customer', MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');

        $projection = app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid, 'site-b');

        $this->assertNull($projection);
    }

    public function test_it_returns_null_when_no_site_is_supplied_for_a_customer_specific_asset(): void
    {
        $motion = $this->published('runtime-customer-no-site', MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');

        $this->assertNull(app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid));
    }

    public function test_it_resolves_for_the_correct_owning_site(): void
    {
        $motion = $this->published('runtime-customer-ok', MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');

        $projection = app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid, 'site-a');

        $this->assertNotNull($projection);
    }

    public function test_projection_never_carries_draft_or_approval_history_fields(): void
    {
        $motion = $this->published('runtime-allowlist');

        $projection = app(MotionRuntimeProjectionReadContract::class)->projectAsset($motion->uuid);

        $this->assertNotNull($projection);
        $properties = array_map(
            fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass($projection))->getProperties()
        );
        $this->assertEqualsCanonicalizing(
            ['assetReference', 'publishedVersion', 'optimizedReference', 'themeTokens', 'placementRules'],
            $properties
        );
    }

    private function published(string $slug, string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): MotionGraphic
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData($slug, null, $ownership, $site), $this->svgInput(), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);

        return $motion->fresh();
    }
}
