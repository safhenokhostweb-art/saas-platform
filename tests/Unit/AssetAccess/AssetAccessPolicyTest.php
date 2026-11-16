<?php

namespace Tests\Unit\AssetAccess;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision as D;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessPolicy;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessRule;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;

/**
 * ADR-087: the decision table of the one access policy both hosts share.
 */
class AssetAccessPolicyTest extends TestCase
{
    private const REF = 'template:solar-header';

    private function kit(string $display = 'teaser'): AssetAccessRule
    {
        return AssetAccessRule::fromArray([
            'visibility' => 'restricted', 'required_vertical' => 'solar_energy',
            'entitlement_keys' => ['solar_industry_kit'], 'locked_display' => $display, 'label' => 'کیت',
        ], 'solar-industry');
    }

    public function test_public_asset_is_available_to_a_site_with_nothing(): void
    {
        $d = AssetAccessPolicy::decide(AssetAccessRule::public(), self::REF, SiteAssetEntitlements::none());

        $this->assertSame([D::STATE_AVAILABLE, D::REASON_PUBLIC, false, null], [$d->state, $d->reason, $d->restricted, $d->badge()]);
    }

    public function test_site_outside_the_vertical_never_sees_the_kit(): void
    {
        $retail = SiteAssetEntitlements::make('retail', ['solar_industry_kit']);
        $none = SiteAssetEntitlements::none();

        foreach ([$retail, $none] as $site) {
            $d = AssetAccessPolicy::decide($this->kit(), self::REF, $site);
            $this->assertSame(D::STATE_HIDDEN, $d->state);
            $this->assertSame(D::REASON_VERTICAL_MISMATCH, $d->reason);
        }
    }

    public function test_site_in_the_vertical_without_the_key_sees_a_purchase_teaser(): void
    {
        $d = AssetAccessPolicy::decide($this->kit(), self::REF, SiteAssetEntitlements::make('solar_energy', ['shop']));

        $this->assertSame(D::STATE_LOCKED, $d->state);
        $this->assertSame(D::BADGE_PURCHASE, $d->badge());
        $this->assertFalse($d->allowed());
        $this->assertStringContainsString('کیت', (string) $d->message());
    }

    public function test_hidden_display_hides_instead_of_teasing(): void
    {
        $d = AssetAccessPolicy::decide($this->kit('hidden'), self::REF, SiteAssetEntitlements::make('solar_energy', []));

        $this->assertSame(D::STATE_HIDDEN, $d->state);
    }

    public function test_vertical_and_key_together_unlock(): void
    {
        $d = AssetAccessPolicy::decide($this->kit(), self::REF, SiteAssetEntitlements::make('solar_energy', ['solar_industry_kit']));

        $this->assertSame([D::STATE_AVAILABLE, D::REASON_ENTITLED, true], [$d->state, $d->reason, $d->restricted]);
        $this->assertSame(D::BADGE_EXCLUSIVE, $d->badge());
    }

    public function test_an_explicit_grant_unlocks_regardless_of_conditions(): void
    {
        $site = SiteAssetEntitlements::make('retail', [], [self::REF]);

        $this->assertSame(D::REASON_GRANT, AssetAccessPolicy::decide($this->kit(), self::REF, $site)->reason);
        // ... but only that exact asset.
        $this->assertFalse(AssetAccessPolicy::decide($this->kit(), 'template:solar-footer', $site)->allowed());
    }

    public function test_restricted_rule_without_conditions_is_grant_only(): void
    {
        $rule = AssetAccessRule::fromArray(['visibility' => 'restricted', 'locked_display' => 'teaser']);
        $d = AssetAccessPolicy::decide($rule, self::REF, SiteAssetEntitlements::make('solar_energy', ['solar_industry_kit']));

        $this->assertSame([D::STATE_LOCKED, D::REASON_GRANT_REQUIRED, D::BADGE_EXCLUSIVE], [$d->state, $d->reason, $d->badge()]);
    }

    public function test_parsing_fails_closed(): void
    {
        foreach ([[], ['visibility' => 'Public'], ['visibility' => 'publik'], ['is_public' => 1], ['is_public' => 'true']] as $raw) {
            $this->assertFalse(AssetAccessRule::fromArray($raw)->isPublic, json_encode($raw));
        }
        // A malformed condition is dropped, which leaves a grant-only rule.
        $rule = AssetAccessRule::fromArray(['visibility' => 'restricted', 'required_vertical' => 'Solar Energy!', 'entitlement_keys' => ['', 'BAD KEY', 7]]);
        $this->assertFalse($rule->hasConditions());
        $this->assertSame(AssetAccessRule::DISPLAY_HIDDEN, $rule->lockedDisplay);
        $this->assertSame(D::STATE_HIDDEN, AssetAccessPolicy::decide(AssetAccessRule::failClosed(), self::REF, SiteAssetEntitlements::make('solar_energy', ['solar_industry_kit']))->state);
    }

    public function test_platform_designer_uses_everything_but_keeps_the_badge(): void
    {
        $d = AssetAccessPolicy::decide(AssetAccessRule::failClosed(), self::REF, SiteAssetEntitlements::platformDesigner());

        $this->assertTrue($d->allowed());
        $this->assertSame(D::BADGE_EXCLUSIVE, $d->badge());
    }
}
