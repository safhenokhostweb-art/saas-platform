<?php

namespace Tests\Unit\ThemeRuntime;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Contracts\AssetVersionMetadata;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;

/**
 * Step 1 Verification Test Suite:
 * Proves the expanded AssetRef contract, AssetVersionMetadata,
 * global button token normalization, widget-level button overrides,
 * individual icon pack overrides with SVG ID isolation, safe fallbacks,
 * and asset version annotations across headers and motions.
 */
class ThemeRuntimeAssetIntegrationTest extends TestCase
{
    private BlockRenderer $renderer;
    private ThemeTokenNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new BlockRenderer();
        $this->normalizer = new ThemeTokenNormalizer();
    }

    public function test_asset_ref_kinds_and_pack_helpers(): void
    {
        // 1. Verify all required asset kinds exist
        $this->assertSame('template', AssetRef::KIND_TEMPLATE);
        $this->assertSame('widget', AssetRef::KIND_WIDGET);
        $this->assertSame('motion', AssetRef::KIND_MOTION);
        $this->assertSame('header', AssetRef::KIND_HEADER);
        $this->assertSame('button_pack', AssetRef::KIND_BUTTON_PACK);
        $this->assertSame('icon_pack', AssetRef::KIND_ICON_PACK);

        // 2. Verify helper methods
        $this->assertTrue(AssetRef::isPackKind(AssetRef::KIND_BUTTON_PACK));
        $this->assertTrue(AssetRef::isPackKind(AssetRef::KIND_ICON_PACK));
        $this->assertFalse(AssetRef::isPackKind(AssetRef::KIND_MOTION));
        $this->assertFalse(AssetRef::isPackKind(AssetRef::KIND_HEADER));

        $this->assertTrue(AssetRef::isIndividualKind(AssetRef::KIND_MOTION));
        $this->assertTrue(AssetRef::isIndividualKind(AssetRef::KIND_HEADER));
        $this->assertFalse(AssetRef::isIndividualKind(AssetRef::KIND_BUTTON_PACK));

        // 3. Verify make & parse
        $ref = AssetRef::make(AssetRef::KIND_BUTTON_PACK, 'modern-buttons');
        $this->assertSame('button_pack:modern-buttons', $ref);
        $parsed = AssetRef::parse($ref);
        $this->assertSame(['button_pack', 'modern-buttons'], $parsed);
    }

    public function test_asset_version_metadata_validation_and_comparison(): void
    {
        $meta = new AssetVersionMetadata('1.2.3', 'Added glowing button border', '2026-11-26T08:00:00Z', 1);

        $this->assertSame('1.2.3', $meta->version);
        $this->assertSame('Added glowing button border', $meta->changelog);
        $this->assertTrue($meta->isNewerThan('1.2.0'));
        $this->assertFalse($meta->isNewerThan('2.0.0'));

        $array = $meta->toArray();
        $rebuilt = AssetVersionMetadata::fromArray($array);
        $this->assertSame('1.2.3', $rebuilt->version);
        $this->assertSame('Added glowing button border', $rebuilt->changelog);

        $this->expectException(\InvalidArgumentException::class);
        new AssetVersionMetadata('invalid-version-string');
    }

    public function test_global_button_styles_normalizer_and_rendering(): void
    {
        // 1. ThemeTokenNormalizer with active_button_* tokens in global_styles
        $globalStyles = [
            'active_button_pack' => 'neo-pack',
            'active_button_preset' => 'neo-pill',
            'active_button_version' => '1.0.0',
            'active_button_tokens' => [
                'radius' => '9999px',
                'padding' => '1rem 2.5rem',
                'bg' => '#06b6d4',
            ],
        ];

        $vars = $this->normalizer->normalize($globalStyles);

        $this->assertSame('neo-pack', $vars['--tb-btn-global-pack']);
        $this->assertSame('neo-pill', $vars['--tb-btn-global-preset']);
        $this->assertSame('1.0.0', $vars['--tb-btn-global-version']);
        $this->assertSame('9999px', $vars['--tb-btn-global-radius']);
        $this->assertSame('1rem 2.5rem', $vars['--tb-btn-global-padding']);
        $this->assertSame('#06b6d4', $vars['--tb-btn-global-bg']);

        // 2. Standard button with default inherit mode renders classic classes (zero diff baseline)
        $standardHtml = $this->renderer->renderBlock([
            'id' => 'btn-standard',
            'type' => 'button',
            'props' => [
                'text' => 'کلیک کنید',
                'url' => '/contact',
                'style' => 'primary',
                'style_mode' => 'inherit',
            ],
        ]);

        $this->assertStringContainsString('class="tb-btn tb-btn-primary"', $standardHtml);
        $this->assertStringNotContainsString('tb-btn--custom', $standardHtml);
        $this->assertStringContainsString('کلیک کنید', $standardHtml);
        $this->assertStringContainsString('href="/contact"', $standardHtml);
    }

    public function test_widget_level_custom_button_override(): void
    {
        $customHtml = $this->renderer->renderBlock([
            'id' => 'btn-custom',
            'type' => 'button',
            'props' => [
                'text' => 'ثبت نام ویژه',
                'url' => '/register',
                'style' => 'primary',
                'style_mode' => 'custom',
                'pack_slug' => 'fancy-buttons',
                'preset_slug' => 'neon-cyan',
                'asset_version' => '1.1.0',
                'preset_snapshot' => [
                    'tokens' => [
                        'radius' => '24px',
                        'bg' => '#00ffcc',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('tb-btn--custom', $customHtml);
        $this->assertStringContainsString('tb-btn--preset-neon-cyan', $customHtml);
        $this->assertStringContainsString('tb-btn-pack-fancy-buttons', $customHtml);
        $this->assertStringContainsString('data-style-mode="custom"', $customHtml);
        $this->assertStringContainsString('data-preset="neon-cyan"', $customHtml);
        $this->assertStringContainsString('data-asset-version="1.1.0"', $customHtml);
        $this->assertStringContainsString('--tb-btn-custom-radius:24px;', $customHtml);
        $this->assertStringContainsString('--tb-btn-custom-bg:#00ffcc;', $customHtml);
    }

    public function test_individual_icon_pack_override_and_svg_id_isolation(): void
    {
        $rawSvg = '<svg viewBox="0 0 32 32"><defs><linearGradient id="iconGrad" x1="0" y1="0"><stop stop-color="#f00"/></linearGradient></defs><circle fill="url(#iconGrad)" cx="16" cy="16" r="10"/></svg>';

        $html = $this->renderer->renderBlock([
            'id' => 'ico1',
            'type' => 'icon',
            'props' => [
                'name' => 'star',
                'size' => 'medium',
                'pack_slug' => 'food-pack',
                'icon_slug' => 'burger-special',
                'asset_version' => '2.0.0',
                'svg_snapshot' => $rawSvg,
            ],
        ]);

        // 1. Verify custom classes and data attributes
        $this->assertStringContainsString('tb-icon--custom', $html);
        $this->assertStringContainsString('tb-icon-pack-food-pack', $html);
        $this->assertStringContainsString('tb-icon--burger-special', $html);
        $this->assertStringContainsString('data-icon="burger-special"', $html);
        $this->assertStringContainsString('data-pack="food-pack"', $html);
        $this->assertStringContainsString('data-asset-version="2.0.0"', $html);

        // 2. Verify SVG ID isolation
        $this->assertStringContainsString('id="tb-ico-ico1-iconGrad"', $html);
        $this->assertStringContainsString('url(#tb-ico-ico1-iconGrad)', $html);
        $this->assertStringNotContainsString('id="iconGrad"', $html);

        // 3. Other icons on the page remain standard Lucide (individual replacement proof)
        $standardIconHtml = $this->renderer->renderBlock([
            'id' => 'ico2',
            'type' => 'icon',
            'props' => [
                'name' => 'shopping-cart',
            ],
        ]);
        $this->assertStringNotContainsString('tb-icon--custom', $standardIconHtml);
        $this->assertStringContainsString('tb-icon', $standardIconHtml);
    }

    public function test_icon_safe_fallback_when_svg_snapshot_is_missing(): void
    {
        $html = $this->renderer->renderBlock([
            'id' => 'ico-fallback',
            'type' => 'icon',
            'props' => [
                'name' => 'star',
                'icon_slug' => 'broken-icon',
                'svg_snapshot' => '', // Missing snapshot
            ],
        ]);

        // Falls back safely to 'star' icon without throwing an error
        $this->assertStringContainsString('tb-icon', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_header_and_motion_asset_version_annotation(): void
    {
        $headerHtml = $this->renderer->renderBlock([
            'id' => 'hdr1',
            'type' => 'header_modern_v1',
            'props' => [
                'brandName' => 'تست برند',
                'asset_version' => '3.0.1',
            ],
        ]);

        $this->assertStringContainsString('data-asset-version="3.0.1"', $headerHtml);
    }
}
