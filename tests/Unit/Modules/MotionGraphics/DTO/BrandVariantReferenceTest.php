<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\BrandVariantReference;
use Tests\TestCase;

class BrandVariantReferenceTest extends TestCase
{
    public function test_an_approved_variant_reports_approved(): void
    {
        $variant = new BrandVariantReference('variant-1', 'motion-1', 3, 'site-1', BrandVariantReference::STATUS_APPROVED);

        $this->assertTrue($variant->isApproved());
    }

    public function test_a_pending_variant_is_not_approved(): void
    {
        $variant = new BrandVariantReference('variant-1', 'motion-1', 3, 'site-1', BrandVariantReference::STATUS_PENDING);

        $this->assertFalse($variant->isApproved());
    }

    public function test_lineage_parent_reference_is_optional(): void
    {
        $variant = new BrandVariantReference('variant-1', 'motion-1', 3, 'site-1', BrandVariantReference::STATUS_APPROVED, 'variant-0');

        $this->assertSame('variant-0', $variant->lineageParentReference);
    }
}
