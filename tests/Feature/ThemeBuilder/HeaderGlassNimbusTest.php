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
        $this->assertStringContainsString('tb-header-nimbus', $html);
        $this->assertStringContainsString('tb-header-skin--glass-nimbus', $html);
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
        $this->assertStringContainsString('href="/products" class="tb-nav-header-link is-active" aria-current="page"', $html);
        $this->assertStringNotContainsString('href="/pricing" class="tb-nav-header-link is-active"', $html);

        // RTL arrow in CTA button
        $this->assertStringContainsString('←', $html);
        $this->assertStringContainsString('شروع رایگان', $html);

        // Popover mobile menu
        $this->assertStringContainsString('tb-header-nimbus-mobile', $html);
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
        $this->assertSame('هدر شیشه‌ای مدرن', $preset->title);
        $this->assertSame('paid', $preset->access_type, 'Must NOT be free');
        $this->assertSame(490000, $preset->price);
        $this->assertTrue((bool) $preset->is_active);
        $this->assertSame('1.3.0', $preset->current_version);
        $this->assertIsArray($preset->blocks);
        $this->assertSame('nav_header', $preset->blocks[0]['type']);
        $this->assertSame('glass_nimbus', $preset->blocks[0]['props']['header_skin'] ?? null);

        $version = CanvasAssetVersion::where('asset_kind', AssetRef::KIND_HEADER)
            ->where('asset_slug', 'header-glass-nimbus')
            ->where('version', '1.3.0')
            ->first();

        $this->assertNotNull($version, 'Asset version 1.3.0 record must exist');
    }

    public function test_nav_header_with_glass_nimbus_renders_pure_css_styles_and_all_unified_features(): void
    {
        $renderer = new BlockRenderer();
        $renderer->setContext(['current_url' => '/shop']);

        $block = [
            'type' => 'nav_header',
            'id' => 'glass_nav_test',
            'props' => [
                'header_skin' => 'glass_nimbus',
                'logo_text' => 'شیشه نوین',
                'logo_badge_symbol' => '◆',
                'logo_url' => '/',
                'topbar_enable' => true,
                'topbar_phone' => '09120000000',
                'nav_source' => 'custom_links',
                'nav_links' => [
                    ['label' => 'خانه', 'url' => '/', 'icon' => 'home'],
                    ['label' => 'فروشگاه', 'url' => '/shop', 'icon' => 'shopping-bag'],
                ],
                'show_search' => true,
                'show_cart' => true,
                'show_account' => true,
                'show_dark_toggle' => true,
                'show_social_in_main_header' => true,
                'social_links' => [
                    ['platform' => 'telegram', 'url' => 'https://t.me/example'],
                    ['platform' => 'eitaa', 'url' => 'https://eitaa.com/example'],
                ],
                'cta_text' => 'ثبت سفارش',
                'cta_url' => '/order',
                'cta_style' => 'gradient',
                'cta_color_start' => '#e20c35',
                'cta_color_end' => '#c4008d',
                'ink_color' => '#211c48',
                'backdrop_blur' => 20,
                'glass_bg_opacity' => 60,
                'glass_floating_top' => 15,
                'asset_version' => '1.3.0',
            ],
        ];

        $html = $renderer->renderBlock($block);

        // 1. Glass skin class & CTA styling
        $this->assertStringContainsString('tb-header-skin--glass-nimbus', $html);
        $this->assertStringContainsString('tb-header-cta-primary', $html);
        $this->assertStringContainsString('←', $html, 'CTA arrow must be rendered');

        // 2. Live glass variables injected
        $this->assertStringContainsString('--color-tgn01-cta1:#e20c35', $html);
        $this->assertStringContainsString('--color-tgn01-cta2:#c4008d', $html);
        $this->assertStringContainsString('--color-tgn01-ink:#211c48', $html);
        $this->assertStringContainsString('--tgn01-glass-blur:20px', $html);
        $this->assertStringContainsString('--tgn01-glass-top:15px', $html);
        $this->assertStringContainsString('--tgn01-glass-bg:rgba(255, 255, 255, 0.6)', $html);

        // 3. Unified features present
        $this->assertStringContainsString('09120000000', $html, 'Topbar content must be present');
        $this->assertStringContainsString('tb-nav-header-search', $html, 'Search must be present');
        $this->assertStringContainsString('tb-nav-header-cart', $html, 'Live cart must be present');
        $this->assertStringContainsString('tb-nav-header-account', $html, 'Account link must be present');
        $this->assertStringContainsString('tb-theme-toggle', $html, 'Dark toggle must be present');
        $this->assertStringContainsString('telegram', $html, 'Social icon must be present');
        $this->assertStringContainsString('eitaa', $html, 'Iranian social icon must be present');
        $this->assertStringContainsString('aria-current="page"', $html, 'Current page link must be marked active');

        // 4. Pure CSS verified from stylesheet
        $css = file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssHeaderPath());
        $this->assertStringContainsString('.tb-header-skin--glass-nimbus', $css);
        $this->assertStringContainsString('backdrop-filter: blur(var(--tgn01-glass-blur, 16px)) saturate(180%)', $css);
        $this->assertStringContainsString('position: sticky', $css);
        $this->assertStringContainsString('border-radius: 1rem', $css);
        $this->assertStringContainsString('.tb-header-skin--glass-nimbus .tb-header-cta-primary', $css);
    }

    public function test_inspector_isolation_glass_fields_conditioned_and_hidden_on_default(): void
    {
        $schema = \SaaSPlatform\ThemeRuntime\Header\SharedHeaderSchema::schema();

        // 1. Verify glass-specific fields are conditioned on header_skin = glass_nimbus
        $glassScopedKeys = [
            'ink_color',
            'backdrop_blur',
            'glass_bg_opacity',
            'glass_floating_top',
            'glass_shadow',
            'cta_color_start',
            'cta_color_end',
        ];

        foreach ($glassScopedKeys as $key) {
            $this->assertArrayHasKey($key, $schema, "Schema must contain {$key}");
            $this->assertSame(
                ['header_skin' => 'glass_nimbus'],
                $schema[$key]['condition'] ?? null,
                "Field [{$key}] must be strictly conditioned on header_skin = glass_nimbus"
            );
        }

        // 2. Verify standard header_shadow is conditioned NOT to show on glass_nimbus
        $this->assertArrayHasKey('header_shadow', $schema);
        $this->assertSame(['header_skin!' => 'glass_nimbus'], $schema['header_shadow']['condition'] ?? null);
    }
}
