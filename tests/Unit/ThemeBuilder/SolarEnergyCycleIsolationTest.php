<?php

namespace Tests\Unit\ThemeBuilder;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\SolarIndustryBlockTypes;
use SaaSPlatform\ThemeRuntime\VendorAssets;
use Tests\TestCase;

/**
 * The solar-industry theme's `solar-energy-cycle` section is a theme block,
 * not the licensed motion product. These pin the separation from the
 * ADR-086 private library motion `solar-motion-graphic`:
 *
 * - no theme block shares an id with a motion-library asset, and the theme
 *   block is not presented as a «موشن» (motion) product;
 * - in no mode does it render a motion-library file (`/motion/...`), so it
 *   cannot deliver a motion around the licensing (MotionAssetLicensePolicy /
 *   the runtime manifest);
 * - its Lottie mode uses the vendored, pinned, integrity-checked player.
 */
final class SolarEnergyCycleIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SolarIndustryBlockTypes::register();
    }

    public function test_no_theme_block_shares_an_id_with_a_motion_library_asset(): void
    {
        $blockTypes = array_keys(SolarIndustryBlockTypes::definitions());
        $librarySlugs = array_keys(MotionLibraryCatalogService::catalogAssets());

        $this->assertSame(['solar-energy-cycle'], $blockTypes);
        $this->assertSame([], array_values(array_intersect($blockTypes, $librarySlugs)));
        // ADR-086 reserves this id for the licensed library motion.
        $this->assertNotContains('solar-motion-graphic', $blockTypes);
    }

    public function test_the_theme_block_is_not_presented_as_a_motion_product(): void
    {
        $definition = SolarIndustryBlockTypes::definitions()[SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE];
        $visible = json_encode([$definition['name'], $definition['tags'], array_column($definition['schema'], 'label')], JSON_UNESCAPED_UNICODE);

        $this->assertSame('بخش چرخه انرژی خورشیدی', $definition['name']);
        $this->assertStringNotContainsString('موشن', (string) $visible);
    }

    public function test_no_mode_renders_a_motion_library_file(): void
    {
        $renderer = new BlockRenderer();
        foreach (['svg_particle', 'lottie', 'webm_video'] as $mode) {
            $html = $renderer->renderBlock([
                'id' => 'iso-'.$mode,
                'type' => SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE,
                'props' => [
                    'mode' => $mode,
                    'lottie_url' => '/storage/solar/cycle.json',
                    'video_webm_url' => '/storage/solar/cycle.webm',
                ],
            ]);

            $this->assertNotSame('', $html, $mode);
            $this->assertDoesNotMatchRegularExpression('#["\'(]/motion/#', $html, "{$mode} must not embed a motion-library file");
        }
    }

    public function test_lottie_mode_loads_the_vendored_player_never_a_cdn(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'id' => 'iso-lottie',
            'type' => SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE,
            'props' => ['mode' => 'lottie', 'lottie_url' => '/storage/solar/cycle.json'],
        ]);

        $this->assertStringContainsString(VendorAssets::scriptTag(VendorAssets::LOTTIE_PLAYER), $html);
        $this->assertStringContainsString('integrity="sha384-', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('@latest', $html);
    }
}
