<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;

/**
 * Laravel-side mirror of `TemplateAssetPackageContract` from
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md.
 */
interface TemplateAssetResolverInterface
{
    /**
     * @param array<string, mixed> $templateManifest
     */
    public function validateManifest(array $templateManifest, MotionAdapterContext $context): ValidationReport;

    /**
     * @param array<string, mixed> $templateManifest
     * @param array<string, mixed> $siteProfile
     */
    public function resolveRequiredAssets(
        array $templateManifest,
        array $siteProfile,
        MotionAdapterContext $context,
    ): TemplateAssetRequestDTO;

    /**
     * Side-effect-free package definition; persistence/activation is a
     * later, explicitly out-of-scope phase (no direct database coupling
     * from this adapter — see docs/motion-saas-integration/PERSISTENCE_DESIGN.md).
     *
     * @return array<string, mixed>
     */
    public function createPackageDefinition(TemplateAssetRequestDTO $dependencyLock, MotionAdapterContext $context): array;
}
