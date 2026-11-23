<?php

namespace Tests\Unit\ThemeBuilder;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessGate;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;

/**
 * Verification of ADR-087 access governance for the Safhe Nokhost pilot header.
 *
 * Guarantees:
 * 1. Default fail-closed: completely hidden and disallowed for arbitrary customer sites.
 * 2. Unaffected by other kits: a site with solar kit or other entitlements cannot access it.
 * 3. Explicit grant: a site with site-grant or entitlement (safhenokhost.ir) unlocks it.
 */
class SafheHeaderAccessTest extends TestCase
{
    private const ASSET_REF = 'template:safhe-header';
    private const LIB_ID = 'lib:safhe-header';

    protected function setUp(): void
    {
        parent::setUp();
        AssetAccessCatalog::flush();
    }

    public function test_safhe_header_is_classified_under_safhenokhost_family(): void
    {
        $templates = AssetAccessCatalog::section(AssetRef::KIND_TEMPLATE);
        $this->assertArrayHasKey('safhe-header', $templates);
        $this->assertSame('safhenokhost', $templates['safhe-header']);

        $families = AssetAccessCatalog::families();
        $this->assertArrayHasKey('safhenokhost', $families);
        $this->assertSame('restricted', $families['safhenokhost']['visibility']);
        $this->assertSame('hidden', $families['safhenokhost']['locked_display']);
        $this->assertContains('safhenokhost_exclusive', $families['safhenokhost']['entitlement_keys']);
    }

    public function test_default_sites_fail_closed_and_header_is_hidden(): void
    {
        $nobody = SiteAssetEntitlements::none();
        $decision = AssetAccessGate::decideTemplate(self::LIB_ID, $nobody);

        $this->assertFalse($decision->allowed(), 'Safhe header must be denied for default sites');
        $this->assertSame(AssetAccessDecision::STATE_HIDDEN, $decision->state, 'Safhe header must stay hidden from other customer sites');
    }

    public function test_other_verticals_and_kits_cannot_access_safhe_header(): void
    {
        // Even a fully licensed solar industry or shop customer has no access
        $solarSite = SiteAssetEntitlements::make('solar_energy', ['solar_industry_kit', 'shop'], ['template:solar-header']);
        $decision = AssetAccessGate::decideTemplate(self::LIB_ID, $solarSite);

        $this->assertFalse($decision->allowed());
        $this->assertSame(AssetAccessDecision::STATE_HIDDEN, $decision->state);
    }

    public function test_site_with_explicit_grant_unlocks_safhe_header(): void
    {
        // Direct site asset grant to safhenokhost.ir site ID
        $safheSite = SiteAssetEntitlements::make(null, [], [self::ASSET_REF]);
        $decision = AssetAccessGate::decideTemplate(self::LIB_ID, $safheSite);

        $this->assertTrue($decision->allowed(), 'Explicit grant to safhenokhost.ir must allow the header');
        $this->assertSame(AssetAccessDecision::STATE_AVAILABLE, $decision->state);
    }

    public function test_site_with_entitlement_key_unlocks_safhe_header(): void
    {
        $entitledSite = SiteAssetEntitlements::make(null, ['safhenokhost_exclusive'], []);
        $decision = AssetAccessGate::decideTemplate(self::LIB_ID, $entitledSite);

        $this->assertTrue($decision->allowed(), 'Entitlement key must allow the header');
        $this->assertSame(AssetAccessDecision::STATE_AVAILABLE, $decision->state);
    }
}
