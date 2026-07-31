<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\LicenseGatewayDecision;

/**
 * License integration boundary (task scope item 5). No real License
 * system is connected in this phase — only
 * App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway. Every
 * deployment preparation must still call through this contract (it is
 * never skippable — see docs/motion-deployment-preparation/LICENSE_BOUNDARY.md),
 * so wiring a real license backend later requires no change to
 * MotionDeploymentPreparationService.
 */
interface MotionLicenseGatewayContract
{
    public function checkAvailability(string $assetId, string $siteReference): LicenseGatewayDecision;

    public function validateEntitlement(string $siteReference, string $packageReference): LicenseGatewayDecision;
}
