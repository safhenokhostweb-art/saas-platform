<?php

namespace App\Modules\MotionGraphics\DTO;

use InvalidArgumentException;

/**
 * A candidate (or activated) Site Asset Package: the resolved set of
 * Motion assets for one site's template, with an optional approved brand
 * variant applied. `siteId` is always the platform-trusted tenant
 * identifier — callers must never populate it from unauthenticated input.
 * See docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md
 * (`SiteAssetPackageContract::createCandidate`).
 */
class SiteAssetPackageDTO
{
    /**
     * @param list<MotionAssetReferenceDTO> $resolvedAssets
     */
    public function __construct(
        public readonly string $siteId,
        public readonly string $templateId,
        public readonly array $resolvedAssets = [],
        public readonly ?string $brandVariant = null,
    ) {
        foreach ($resolvedAssets as $asset) {
            if (! $asset instanceof MotionAssetReferenceDTO) {
                throw new InvalidArgumentException(
                    'SiteAssetPackageDTO resolvedAssets must be MotionAssetReferenceDTO instances.'
                );
            }
        }
    }
}
