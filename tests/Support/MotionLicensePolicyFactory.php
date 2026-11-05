<?php

namespace Tests\Support;

use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;

/**
 * ADR-078 test helper: a MotionAssetLicensePolicy over a stub license
 * service, for tests that construct the activation/runtime services by hand
 * and are not about licensing.
 */
final class MotionLicensePolicyFactory
{
    /** Every module entitlement check passes, so premium assets are allowed. */
    public static function allowAll(): MotionAssetLicensePolicy
    {
        return new MotionAssetLicensePolicy(self::stub(true));
    }

    /** Every module entitlement check fails, so only free-tier assets pass. */
    public static function freeOnly(): MotionAssetLicensePolicy
    {
        return new MotionAssetLicensePolicy(self::stub(false));
    }

    private static function stub(bool $allowed): ModuleLicenseServiceInterface
    {
        $mock = \Mockery::mock(ModuleLicenseServiceInterface::class);
        $mock->shouldReceive('checkEntitlement')->andReturn(['allowed' => $allowed, 'reason' => $allowed ? null : 'denied']);

        return $mock;
    }
}
