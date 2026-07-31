<?php

namespace App\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\Models\SiteAssetPackage;

/**
 * The result of `MotionRuntimeInstallationService::installTemplateForSite()`
 * — task scope item 1's "generate activation plan" responsibility.
 * Describes exactly what was (or, on an idempotent re-run, already was)
 * installed and the customer-safe runtime projection generated for each
 * bound asset. See
 * docs/motion-runtime-installation/RUNTIME_INSTALLATION_FLOW.md.
 */
final class ActivationPlan
{
    /**
     * @param list<array{asset_id: string, version: int, activation_type: string}> $items
     * @param list<RuntimeProjectionDTO> $projections
     */
    public function __construct(
        public readonly SiteAssetPackage $sitePackage,
        public readonly array $items,
        public readonly array $projections,
        public readonly bool $wasAlreadyUpToDate,
    ) {}
}
