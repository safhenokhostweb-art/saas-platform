<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;

/**
 * Laravel-side mirror of `MotionRuntimeProjectionContract` from
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md. Projection is
 * generated only from an exact published package/version after
 * authorization and license decisions — see
 * docs/motion-saas-integration/RUNTIME_PROJECTION.md.
 */
interface MotionRuntimeProjectionInterface
{
    public function projectPublished(string $sitePackageId, int $version, MotionAdapterContext $context): RuntimeProjectionDTO;

    public function validateProjection(RuntimeProjectionDTO $projection): ValidationReport;
}
