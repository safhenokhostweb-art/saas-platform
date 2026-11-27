<?php

namespace Tests\Unit\AssetAccess;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessRule;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;

/**
 * ADR-087: the access manifest (theme-runtime resources/access/policy.json)
 * classifies every platform asset the package ships. Adding a template or
 * a canonical widget without classifying it fails here, and the runtime
 * treats it as restricted and hidden meanwhile, so nothing specialized can
 * ship public by omission.
 */
class AssetAccessCatalogContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::resetHostTypes();
        AssetAccessCatalog::flush();
    }

    /** @return list<string> */
    private function libraryKeys(): array
    {
        return array_map(fn (array $c) => substr($c['id'], strlen(TemplateLibrary::ID_PREFIX)), TemplateLibrary::list());
    }

    public function test_the_manifest_is_valid_json_schema_1(): void
    {
        $raw = json_decode((string) file_get_contents(AssetAccessCatalog::path()), true);

        $this->assertIsArray($raw);
        $this->assertSame(1, $raw['schema']);
    }

    public function test_every_library_template_is_classified(): void
    {
        $missing = array_values(array_diff($this->libraryKeys(), array_keys(AssetAccessCatalog::section(AssetRef::KIND_TEMPLATE))));

        $this->assertSame([], $missing, 'Classify these templates in resources/access/policy.json ("public" or a family).');
    }

    public function test_every_canonical_widget_is_classified(): void
    {
        $missing = array_values(array_diff(array_keys(BlockRegistry::all()), array_keys(AssetAccessCatalog::section(AssetRef::KIND_WIDGET))));

        $this->assertSame([], $missing, 'Classify these widget types in resources/access/policy.json ("public" or a family).');
    }

    public function test_no_classified_template_is_stale(): void
    {
        $stale = array_values(array_diff(array_keys(AssetAccessCatalog::section(AssetRef::KIND_TEMPLATE)), $this->libraryKeys()));

        $this->assertSame([], $stale);
    }

    public function test_every_entry_names_public_or_a_declared_family_with_known_vertical(): void
    {
        $families = AssetAccessCatalog::families();
        foreach ($families as $name => $raw) {
            $rule = AssetAccessRule::fromArray($raw);
            $this->assertFalse($rule->isPublic, "family {$name} must be restricted; public assets are listed as \"public\"");
            if ($rule->requiredVertical !== null) {
                $this->assertTrue(BusinessVerticals::isKnown($rule->requiredVertical), "family {$name}: unknown vertical");
            }
            $this->assertNotEmpty($raw['label'] ?? null, "family {$name} needs a label for the badge");
        }
        foreach (AssetRef::KINDS as $kind) {
            foreach (AssetAccessCatalog::section($kind) as $key => $entry) {
                $this->assertTrue($entry === AssetAccessCatalog::PUBLIC || isset($families[$entry]), "{$kind}:{$key} names unknown family {$entry}");
                $this->assertTrue(AssetRef::isValid($kind . ':' . $key), "{$kind}:{$key} is not a valid asset reference");
            }
        }
    }

    public function test_the_solar_kit_is_restricted_and_generic_assets_stay_public(): void
    {
        $solar = array_filter($this->libraryKeys(), fn (string $k) => str_starts_with($k, 'solar-'));
        $this->assertNotEmpty($solar);

        $nobody = SiteAssetEntitlements::none();
        foreach ($solar as $key) {
            $decision = AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, $key, $nobody);
            $this->assertSame(AssetAccessDecision::STATE_HIDDEN, $decision->state, $key);
        }
        foreach (['header-glass', 'footer-minimal-light', 'page-store-home', 'hero-panorama'] as $key) {
            $this->assertTrue(AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, $key, $nobody)->allowed(), $key);
        }
        foreach (array_keys(BlockRegistry::all()) as $type) {
            if ($type === 'header_glass_nimbus') {
                $this->assertFalse(AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, $type, $nobody)->allowed(), $type);
                continue;
            }
            $this->assertTrue(AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, $type, $nobody)->allowed(), $type);
        }
    }

    public function test_an_override_replaces_the_declared_rule_and_keeps_the_family_label(): void
    {
        $site = SiteAssetEntitlements::make(null, [], [], ['template:solar-header' => ['is_public' => true]]);
        $this->assertTrue(AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, 'solar-header', $site)->allowed());

        $site = SiteAssetEntitlements::make(null, [], [], ['template:header-glass' => ['is_public' => false, 'locked_display' => 'teaser']]);
        $d = AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, 'header-glass', $site);
        $this->assertSame(AssetAccessDecision::STATE_LOCKED, $d->state);
    }

    public function test_ungoverned_host_types_are_left_to_their_host(): void
    {
        $this->assertNull(AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, 'column', SiteAssetEntitlements::none()));
        $this->assertNotNull(AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, 'solar_calculator', SiteAssetEntitlements::none()));
        $this->assertNull(AssetAccessCatalog::decide(AssetRef::KIND_TEMPLATE, 'no-such-template', SiteAssetEntitlements::none()));
    }
}
