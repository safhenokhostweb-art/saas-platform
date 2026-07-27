<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;

/**
 * Laravel-side mirror of `MotionAssetResolverContract` from
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md. Implementations
 * must delegate to the standalone Motion Graphics module's own contracts —
 * this interface only shapes the boundary, it must not carry business rules.
 */
interface MotionAssetResolverInterface
{
    public function resolveCanonical(string $assetId, int $version, MotionAdapterContext $context): MotionAssetReferenceDTO;

    /**
     * @param array<string, mixed> $templateRequirement
     * @param array<string, mixed> $siteProfile
     */
    public function validateCompatibility(
        MotionAssetReferenceDTO $asset,
        array $templateRequirement,
        array $siteProfile,
    ): CompatibilityDecision;
}
