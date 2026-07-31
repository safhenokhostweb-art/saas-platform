<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\LicenseGatewayDecision;

/**
 * The only MotionLicenseGatewayContract implementation this phase ships
 * (task scope item 5 — "Implementation: Fake allow/deny adapter only.
 * Do not connect real License system yet."). Defaults to allow so the
 * rest of the preparation pipeline can be exercised end to end; tests
 * construct `new FakeMotionLicenseGateway(allow: false)` to prove the
 * license boundary is never bypassed.
 */
final class FakeMotionLicenseGateway implements MotionLicenseGatewayContract
{
    public function __construct(
        private readonly bool $allow = true,
    ) {}

    public function checkAvailability(string $assetId, string $siteReference): LicenseGatewayDecision
    {
        return $this->allow
            ? LicenseGatewayDecision::allow()
            : LicenseGatewayDecision::deny('license_denied', 'The fake license gateway is configured to deny.');
    }

    public function validateEntitlement(string $siteReference, string $packageReference): LicenseGatewayDecision
    {
        return $this->allow
            ? LicenseGatewayDecision::allow()
            : LicenseGatewayDecision::deny('license_denied', 'The fake license gateway is configured to deny.');
    }
}
