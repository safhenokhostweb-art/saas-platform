<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Phase 7: one required asset's compatibility outcome, shown on the
 * Template Assignment admin page before the admin commits to assigning
 * a package. Carries only the asset id and the ValidationReport — never
 * the underlying MotionAssetReferenceDTO's fuller compatibility payload.
 */
final class AssetCompatibilityPreview
{
    public function __construct(
        public readonly string $assetId,
        public readonly ValidationReport $result,
    ) {}
}
