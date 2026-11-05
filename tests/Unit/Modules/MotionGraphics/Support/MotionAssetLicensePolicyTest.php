<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use Tests\TestCase;

final class MotionAssetLicensePolicyTest extends TestCase
{
    private function policy(?bool $premiumAllowed): MotionAssetLicensePolicy
    {
        $licenses = Mockery::mock(ModuleLicenseServiceInterface::class);
        if ($premiumAllowed === null) {
            $licenses->shouldNotReceive('checkEntitlement');
        } else {
            $licenses->shouldReceive('checkEntitlement')
                ->with(Mockery::type(Site::class), MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG)
                ->andReturn(['allowed' => $premiumAllowed, 'reason' => null]);
        }

        return new MotionAssetLicensePolicy($licenses);
    }

    public function test_a_free_asset_is_allowed_without_consulting_the_license_service(): void
    {
        $decision = $this->policy(null)->decide(new Site(), ['license_tier' => 'free']);

        $this->assertTrue($decision->allowed);
        $this->assertSame('license_free_tier', $decision->code);
    }

    public function test_a_premium_asset_needs_the_premium_module_license(): void
    {
        $this->assertTrue($this->policy(true)->decide(new Site(), ['license_tier' => 'premium'])->allowed);

        $denied = $this->policy(false)->decide(new Site(), ['license_tier' => 'premium']);
        $this->assertFalse($denied->allowed);
        $this->assertSame('premium_license_required', $denied->code);
    }

    public function test_a_missing_or_unknown_tier_is_treated_as_premium(): void
    {
        $this->assertFalse($this->policy(false)->decide(new Site(), [])->allowed);
        $this->assertFalse($this->policy(false)->decide(new Site(), ['license_tier' => 'gratis'])->allowed);
        $this->assertSame('premium', MotionAssetLicensePolicy::tierOf(['license_tier' => null]));
    }
}
