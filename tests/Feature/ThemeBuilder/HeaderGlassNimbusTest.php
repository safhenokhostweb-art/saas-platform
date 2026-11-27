<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\CanvasAssetVersion;
use App\Models\CanvasHeaderPreset;
use App\Models\Site;
use App\Services\ThemeBuilder\BlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessDecision;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

class HeaderGlassNimbusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::resetHostTypes();
        AssetAccessCatalog::flush();
    }

    public function test_header_glass_nimbus_is_registered_in_canonical_registry_with_all_inspector_fields(): void
    {
        $all = BlockRegistry::all();
        $this->assertArrayHasKey('header_glass_nimbus', $all);

        $def = $all['header_glass_nimbus'];
        $this->assertSame('هدر شناور شیشه‌ای مدرن', $def['name']);
        $this->assertSame(BlockRegistry::CATEGORY_UTILITY, $def['category']);

        $schema = $def['schema'];
        $requiredFields = [
            'brand_name',
            'logo_url',
            'logo_badge_symbol',
            'menu_items',
            'secondary_label',
            'secondary_url',
            'cta_label',
            'cta_url',
            'cta_color_start',
            'cta_color_end',
            'ink_color',
            'asset_version',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $schema, "Schema missing field [{$field}]");
        }

        $this->assertSame('repeater', $schema['menu_items']['type']);
        $this->assertSame('color', $schema['cta_color_start']['type']);
        $this->assertSame('color', $schema['cta_color_end']['type']);
        $this->assertSame('color', $schema['ink_color']['type']);
    }

    public function test_renderer_outputs_expected_glassmorphic_structure_rtl_arrow_and_popover(): void
    {
        $renderer = new BlockRenderer();
        $renderer->setContext(['current_url' => '/products']);

        $block = [
            'type' => 'header_glass_nimbus',
            'id' => 'nimbus_test_1',
            'props' => [
                'brand_name' => 'تست برند',
                'logo_badge_symbol' => '◆',
                'home_url' => '/',
                'menu_items' => [
                    ['label' => 'خانه', 'url' => '/'],
                    ['label' => 'محصولات', 'url' => '/products'],
                    ['label' => 'تعرفه‌ها', 'url' => '/pricing'],
                ],
                'secondary_label' => 'ورود کاربر',
                'secondary_url' => '/login',
                'cta_label' => 'شروع رایگان',
                'cta_url' => '/register',
                'cta_color_start' => '#ff0055',
                'cta_color_end' => '#aa00ff',
                'ink_color' => '#112233',
                'asset_version' => '1.0.0',
            ],
        ];

        $html = $renderer->renderBlock($block);

        // Glassmorphic header class and attributes
        $this->assertStringContainsString('class="tb-header-nimbus', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('data-asset-version="1.0.0"', $html);

        // Brand & Badge
        $this->assertStringContainsString('تست برند', $html);
        $this->assertStringContainsString('◆', $html);

        // Color styles & variables
        $this->assertStringContainsString('--color-tgn01-cta1:#ff0055', $html);
        $this->assertStringContainsString('--color-tgn01-cta2:#aa00ff', $html);
        $this->assertStringContainsString('--color-tgn01-ink:#112233', $html);

        // Active link detection for /products
        $this->assertStringContainsString('href="/products" aria-current="page"', $html);
        $this->assertStringNotContainsString('href="/pricing" aria-current="page"', $html);

        // RTL arrow in CTA button
        $this->assertStringContainsString('←', $html);
        $this->assertStringContainsString('شروع رایگان', $html);

        // Popover mobile menu
        $this->assertStringContainsString('class="tb-header-nimbus-mobile', $html);
        $this->assertStringContainsString('popover', $html);
        $this->assertStringContainsString('data-nimbus-toggle="tgn01-menu-nimbus_test_1"', $html);
    }

    public function test_access_gate_locks_header_glass_nimbus_without_grant_and_unlocks_with_header_grant(): void
    {
        $nobody = SiteAssetEntitlements::none();

        // 1. Without grant: strictly hidden / not allowed
        $decision = AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, 'header_glass_nimbus', $nobody);
        $this->assertNotNull($decision);
        $this->assertFalse($decision->allowed());
        $this->assertSame(AssetAccessDecision::STATE_HIDDEN, $decision->state);

        // 2. With header preset grant (header:header-glass-nimbus): unlocked and available
        $grantedSite = SiteAssetEntitlements::make(null, [], ['header:header-glass-nimbus']);
        $decisionGranted = AssetAccessCatalog::decide(AssetRef::KIND_WIDGET, 'header_glass_nimbus', $grantedSite);
        $this->assertNotNull($decisionGranted);
        $this->assertTrue($decisionGranted->allowed());
        $this->assertSame(AssetAccessDecision::STATE_AVAILABLE, $decisionGranted->state);
        $this->assertSame(AssetAccessDecision::REASON_GRANT, $decisionGranted->reason);
    }

    public function test_canvas_header_preset_is_seeded_with_paid_access_and_initial_version(): void
    {
        // Run migration logic
        $this->artisan('migrate', ['--force' => true]);

        $preset = CanvasHeaderPreset::where('slug', 'header-glass-nimbus')->first();
        $this->assertNotNull($preset, 'header-glass-nimbus preset must exist in canvas_header_presets');
        $this->assertSame('paid', $preset->access_type, 'Must NOT be free');
        $this->assertSame(490000, $preset->price);
        $this->assertTrue((bool) $preset->is_active);
        $this->assertSame('1.0.0', $preset->current_version);
        $this->assertIsArray($preset->blocks);
        $this->assertSame('header_glass_nimbus', $preset->blocks[0]['type']);

        $version = CanvasAssetVersion::where('asset_kind', AssetRef::KIND_HEADER)
            ->where('asset_slug', 'header-glass-nimbus')
            ->where('version', '1.0.0')
            ->first();

        $this->assertNotNull($version, 'Initial asset version record must exist');
        $this->assertStringContainsString('انتشار اولیه هدر شناور شیشه‌ای مدرن', $version->changelog);
    }
}
