<?php

namespace Tests\Unit\AssetAccess;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessGate;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;

/**
 * ADR-087: the listing and write gates both editors call, and the
 * provisioning payload round trip.
 */
class AssetAccessGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AssetAccessCatalog::flush();
    }

    private function ids(array $cards): array
    {
        return array_column($cards, 'id');
    }

    public function test_template_cards_hide_the_kit_from_other_verticals_and_tease_it_inside_the_vertical(): void
    {
        $cards = array_merge(TemplateLibrary::list('header'), [['id' => 12, 'source' => 'saved', 'name' => 'mine']]);

        $none = AssetAccessGate::annotateTemplateCards($cards, SiteAssetEntitlements::none());
        $this->assertNotContains('lib:solar-header', $this->ids($none));
        $this->assertContains('lib:header-glass', $this->ids($none));
        $this->assertContains(12, $this->ids($none), 'saved templates are not platform assets');
        $this->assertArrayNotHasKey('access', $none[array_search(12, $this->ids($none), true)]);

        $solarNoKit = AssetAccessGate::annotateTemplateCards($cards, SiteAssetEntitlements::make('solar_energy', []));
        $card = $solarNoKit[array_search('lib:solar-header', $this->ids($solarNoKit), true)];
        $this->assertTrue($card['access']['locked']);
        $this->assertSame('نیازمند خرید', $card['access']['badge']);

        $licensed = AssetAccessGate::annotateTemplateCards($cards, SiteAssetEntitlements::make('solar_energy', ['solar_industry_kit']));
        $card = $licensed[array_search('lib:solar-header', $this->ids($licensed), true)];
        $this->assertFalse($card['access']['locked']);
        $this->assertSame('available', $card['access']['state']);
    }

    public function test_insert_decision_for_library_and_saved_templates(): void
    {
        $this->assertFalse(AssetAccessGate::decideTemplate('lib:solar-page-home', SiteAssetEntitlements::none())->allowed());
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-page-home', SiteAssetEntitlements::make(null, [], ['template:solar-page-home']))->allowed());
        $this->assertNull(AssetAccessGate::decideTemplate(5, SiteAssetEntitlements::none()));
    }

    public function test_palette_drops_hidden_widgets_and_keeps_the_registry_intact(): void
    {
        $byCategory = BlockRegistry::byCategory();
        $byCategory[BlockRegistry::CATEGORY_MODULE]['solar_calculator'] = ['name' => 'calc', 'category' => 'module'];
        $byCategory[BlockRegistry::CATEGORY_LAYOUT]['column'] = ['name' => 'column', 'category' => 'layout'];

        [$palette, $access] = AssetAccessGate::filterPalette($byCategory, SiteAssetEntitlements::none());
        $this->assertArrayNotHasKey('solar_calculator', $palette[BlockRegistry::CATEGORY_MODULE]);
        $this->assertArrayHasKey('column', $palette[BlockRegistry::CATEGORY_LAYOUT], 'host types are not governed here');
        $this->assertArrayHasKey('heading', $palette[BlockRegistry::CATEGORY_CONTENT]);
        $this->assertSame([], $access);

        [$palette, $access] = AssetAccessGate::filterPalette($byCategory, SiteAssetEntitlements::make(null, ['solar_calculator']));
        $this->assertArrayHasKey('solar_calculator', $palette[BlockRegistry::CATEGORY_MODULE]);
        $this->assertTrue($access['solar_calculator']['restricted']);
    }

    public function test_save_refuses_only_newly_introduced_locked_widgets(): void
    {
        $before = [['id' => 'a', 'type' => 'section', 'props' => [], 'children' => [['id' => 'b', 'type' => 'heading', 'props' => []]]]];
        $after = [['id' => 'a', 'type' => 'section', 'props' => ['blocks' => [['id' => 'c', 'type' => 'solar_calculator', 'props' => []]]], 'children' => []]];
        $none = SiteAssetEntitlements::none();

        $this->assertSame(['section', 'solar_calculator'], AssetAccessGate::widgetTypes($after));
        $this->assertSame(['solar_calculator'], AssetAccessGate::newlyLockedWidgetTypes($before, $after, $none));
        // Already on the page (e.g. a lapsed licence): editing the rest still saves.
        $this->assertSame([], AssetAccessGate::newlyLockedWidgetTypes($after, $after, $none));
        $this->assertSame([], AssetAccessGate::newlyLockedWidgetTypes($before, $after, SiteAssetEntitlements::make(null, ['solar_calculator'])));
    }

    public function test_payload_round_trip_and_strict_rejection(): void
    {
        $site = SiteAssetEntitlements::make('solar_energy', ['shop', 'solar_industry_kit'], ['template:solar-header'], ['template:header-glass' => ['is_public' => false]], '2026-11-16T10:00:00+00:00');
        $payload = json_decode(json_encode($site->toPayload()), true);
        $back = SiteAssetEntitlements::fromPayload($payload);

        $this->assertNotNull($back);
        $this->assertSame($site->toPayload()['grants'], $back->toPayload()['grants']);
        $this->assertSame('solar_energy', $back->vertical);
        $this->assertTrue($back->hasGrant('template:solar-header'));
        $this->assertFalse($back->override('template:header-glass')->isPublic);
        $this->assertSame('{}', json_encode(SiteAssetEntitlements::none()->toPayload()['overrides']));

        foreach ([
            null, [], ['schema' => 2] + $payload, array_merge($payload, ['vertical' => 'Not Valid']),
            array_merge($payload, ['grants' => ['template:../../etc']]), array_merge($payload, ['grants' => 'template:x']),
            array_merge($payload, ['entitlement_keys' => ['a' => 'b']]), array_merge($payload, ['overrides' => [['is_public' => true]]]),
            array_merge($payload, ['overrides' => ['template:x' => ['is_public' => 'yes']]]),
        ] as $i => $bad) {
            $this->assertNull(SiteAssetEntitlements::fromPayload($bad), "case {$i}");
        }
    }
}
