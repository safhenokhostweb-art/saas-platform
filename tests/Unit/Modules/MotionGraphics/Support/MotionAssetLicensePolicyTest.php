<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\CreatesMotionTestSite;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use Tests\TestCase;

final class MotionAssetLicensePolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

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

    public function test_a_private_asset_needs_an_active_grant_and_never_the_premium_licence(): void
    {
        $site = $this->createSite('A');
        $other = $this->createSite('B');
        $private = ['license_tier' => 'premium', 'visibility' => 'private'];

        // The premium licence is never consulted for a private asset.
        $denied = $this->policy(null)->decide($site, $private, 'asset-1');
        $this->assertFalse($denied->allowed);
        $this->assertSame(MotionAssetLicensePolicy::GRANT_REQUIRED, $denied->code);

        $grant = MotionSiteAssetGrant::query()->create(['site_id' => $site->id, 'asset_id' => 'asset-1', 'status' => 'active', 'granted_at' => now()]);
        $allowed = $this->policy(null)->decide($site, $private, 'asset-1');
        $this->assertTrue($allowed->allowed);
        $this->assertSame('license_asset_granted', $allowed->code);

        // Per site and per asset, never wider.
        $this->assertFalse($this->policy(null)->decide($other, $private, 'asset-1')->allowed);
        $this->assertFalse($this->policy(null)->decide($site, $private, 'asset-2')->allowed);
        $this->assertFalse($this->policy(null)->decide($site, $private)->allowed, 'no asset id, no grant check, deny');

        $grant->update(['status' => MotionSiteAssetGrant::STATUS_REVOKED]);
        $this->assertFalse($this->policy(null)->decide($site, $private, 'asset-1')->allowed);
    }

    public function test_visibility_defaults_public_when_absent_and_private_when_unknown(): void
    {
        $this->assertSame('public', MotionAssetLicensePolicy::visibilityOf([]));
        $this->assertSame('public', MotionAssetLicensePolicy::visibilityOf(['visibility' => 'public']));
        $this->assertSame('private', MotionAssetLicensePolicy::visibilityOf(['visibility' => 'private']));
        $this->assertSame('private', MotionAssetLicensePolicy::visibilityOf(['visibility' => 'Public ']));
        $this->assertSame('private', MotionAssetLicensePolicy::visibilityOf(['visibility' => null]));

        // A public asset keeps the ADR-078 tier rules exactly.
        $this->assertTrue($this->policy(null)->decide(new Site(), ['license_tier' => 'free', 'visibility' => 'public'])->allowed);
        $this->assertFalse($this->policy(false)->decide(new Site(), ['license_tier' => 'premium', 'visibility' => 'public'])->allowed);
    }

    public function test_runtime_enabled_is_false_only_when_explicitly_switched_off(): void
    {
        $this->assertTrue(MotionAssetLicensePolicy::isRuntimeEnabled([]));
        $this->assertTrue(MotionAssetLicensePolicy::isRuntimeEnabled(['runtime_enabled' => true]));
        $this->assertFalse(MotionAssetLicensePolicy::isRuntimeEnabled(['runtime_enabled' => false]));
    }
}
