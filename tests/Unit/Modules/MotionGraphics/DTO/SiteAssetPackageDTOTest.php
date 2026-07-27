<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\SiteAssetPackageDTO;
use InvalidArgumentException;
use Tests\TestCase;

class SiteAssetPackageDTOTest extends TestCase
{
    public function test_it_holds_resolved_assets_for_one_site(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie');
        $dto = new SiteAssetPackageDTO('site-1', 'template-1', [$asset], 'brand-a');

        $this->assertSame('site-1', $dto->siteId);
        $this->assertSame('template-1', $dto->templateId);
        $this->assertSame([$asset], $dto->resolvedAssets);
        $this->assertSame('brand-a', $dto->brandVariant);
    }

    public function test_it_rejects_non_reference_resolved_assets(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteAssetPackageDTO('site-1', 'template-1', ['not-a-dto']);
    }

    public function test_brand_variant_is_optional(): void
    {
        $dto = new SiteAssetPackageDTO('site-1', 'template-1');

        $this->assertNull($dto->brandVariant);
    }
}
