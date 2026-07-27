<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\OwnershipDecision;
use App\Modules\MotionGraphics\DTO\SiteAssetPackageDTO;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;

/**
 * Laravel-side mirror of `SiteAssetPackageContract` from
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md.
 */
interface SiteAssetPackageResolverInterface
{
    public function createCandidate(
        TemplateAssetRequestDTO $templatePackage,
        ?string $brandIntent,
        MotionAdapterContext $context,
    ): SiteAssetPackageDTO;

    public function validateOwnership(SiteAssetPackageDTO $candidate, string $siteId): OwnershipDecision;

    /**
     * @return array<string, mixed>
     */
    public function lineage(string $packageId, int $version, MotionAdapterContext $context): array;
}
