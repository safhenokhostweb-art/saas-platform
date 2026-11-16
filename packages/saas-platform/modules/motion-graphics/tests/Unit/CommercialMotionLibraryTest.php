<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\Modules\MotionGraphics\Support\MotionSvgComposer;

/**
 * ADR-078/079: the commercial library motions are complete, safe,
 * self-contained, light/dark aware, reduced-motion safe and RTL; v2 adds
 * runtime parameters, speed scaling and the Vazirmatn stack.
 */
final class CommercialMotionLibraryTest extends TestCase
{
    private const LIBRARY = __DIR__.'/../../assets/library';

    public static function assets(): array
    {
        return [
            'hero v1' => ['hero-motion', 'v1', 'free'],
            'hero v2' => ['hero-motion', 'v2', 'free'],
            'business growth v1' => ['business-growth-motion', 'v1', 'premium'],
            'business growth v2' => ['business-growth-motion', 'v2', 'premium'],
            // ADR-086: private library motion, built to the v2 parameter contract.
            'solar v1' => ['solar-motion-graphic', 'v1', 'premium'],
        ];
    }

    /** Assets built to the ADR-079 runtime-parameter contract. */
    public static function v2Assets(): array
    {
        return array_filter(self::assets(), fn (array $a): bool => $a[1] === 'v2' || $a[0] === 'solar-motion-graphic');
    }

    public function test_the_solar_motion_is_private_with_a_release_label(): void
    {
        $metadata = json_decode(file_get_contents(self::LIBRARY.'/solar-motion-graphic/v1/metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('private', $metadata['visibility']);
        $this->assertSame('1.0.0', $metadata['version_label']);
        $this->assertSame('solar', $metadata['category']);
        $this->assertSame('platform', $metadata['ownership']['type']);
        $this->assertFalse($metadata['ownership']['customer_restriction']);
    }

    #[DataProvider('assets')]
    public function test_package_is_complete_with_a_declared_license_tier(string $slug, string $version, string $tier): void
    {
        $dir = self::LIBRARY.'/'.$slug.'/'.$version;
        foreach (['animation.svg', 'preview.svg', 'metadata.json', 'AI_EDITING_INSTRUCTIONS.txt', 'README.md'] as $file) {
            $this->assertFileExists($dir.'/'.$file);
        }

        $metadata = json_decode(file_get_contents($dir.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['name', 'slug', 'category', 'purpose', 'description', 'usage', 'tags', 'editable_properties', 'ai_editable', 'dimensions', 'accessibility'] as $key) {
            $this->assertArrayHasKey($key, $metadata);
        }
        $this->assertSame($slug, $metadata['slug']);
        $this->assertSame($version, $metadata['version']);
        $this->assertSame('svg-js', $metadata['engine']);
        $this->assertSame([], $metadata['dependencies']);
        $this->assertSame($tier, $metadata['license_tier']);
        $this->assertSame('0 0 960 540', $metadata['dimensions']['viewBox']);
    }

    #[DataProvider('assets')]
    public function test_svg_is_safe_self_contained_and_lightweight(string $slug, string $version): void
    {
        foreach (['animation.svg', 'preview.svg'] as $file) {
            $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/'.$file);

            $this->assertNotFalse(simplexml_load_string($svg), "{$file} is not well-formed XML");
            $this->assertStringContainsString('viewBox="0 0 960 540"', $svg);
            $this->assertStringContainsString('role="img"', $svg);
            $this->assertMatchesRegularExpression('/<title\b[^>]*>[^<]+<\/title>/u', $svg);
            // Same rule MotionAssetPipeline::validateExecutableContent() enforces.
            $this->assertDoesNotMatchRegularExpression('/<script\b|\son\w+\s*=|<foreignObject\b|(?:href|src)\s*=\s*[\'"]https?:/i', $svg);
            // No SMIL: CSS cannot stop it under prefers-reduced-motion.
            $this->assertDoesNotMatchRegularExpression('/<animate|<set\b/i', $svg);
            // No web fonts or other remote loads, no costly filters; the
            // platform embeds the font at delivery, never in the source.
            $this->assertDoesNotMatchRegularExpression('/@import|@font-face|url\(\s*[\'"]?(?:https?:)?\/\//i', $svg);
            $this->assertStringNotContainsString('<filter', $svg);
            // var() only through CSS classes — presentation attributes with
            // var() are not reliable across engines.
            $this->assertDoesNotMatchRegularExpression('/\s(?:fill|stroke|stop-color)="var\(/', $svg);
            $this->assertLessThan(20_000, strlen($svg), "{$file} exceeds the 20 KB budget");
        }
    }

    #[DataProvider('assets')]
    public function test_animation_honours_color_scheme_reduced_motion_and_rtl(string $slug, string $version): void
    {
        $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/animation.svg');

        $this->assertStringContainsString('prefers-reduced-motion:reduce', $svg);
        $this->assertStringContainsString('prefers-color-scheme:dark', $svg);
        $this->assertStringContainsString('<style id="asset-theme">', $svg);
        $this->assertStringContainsString('direction:rtl', $svg);
        // Persian digits, not Latin ones, in visible copy.
        $this->assertMatchesRegularExpression('/[۰-۹]/u', $svg);
        $this->assertDoesNotMatchRegularExpression('/>[^<]*[0-9][^<]*<\/(?:text|tspan)>/u', $svg);
    }

    #[DataProvider('assets')]
    public function test_preview_is_the_same_artwork_with_animation_disabled(string $slug, string $version): void
    {
        $dir = self::LIBRARY.'/'.$slug.'/'.$version;
        $preview = file_get_contents($dir.'/preview.svg');

        $this->assertStringContainsString('*{animation:none!important}', $preview);
        $this->assertSame(
            file_get_contents($dir.'/animation.svg'),
            str_replace('*{animation:none!important}', '@media (prefers-reduced-motion:reduce){*{animation:none!important}}', $preview)
        );
    }

    #[DataProvider('v2Assets')]
    public function test_v2_declares_valid_parameters_that_all_have_a_target(string $slug, string $version): void
    {
        $dir = self::LIBRARY.'/'.$slug.'/'.$version;
        $svg = file_get_contents($dir.'/animation.svg');
        $declared = json_decode(file_get_contents($dir.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR)['parameters'];
        $definitions = MotionSvgComposer::normalizeDefinitions($declared);

        $this->assertCount(count($declared), $definitions, 'every declared parameter must be valid');
        $hooks = [];
        preg_match_all('/data-p="([a-z0-9_]+)"/', $svg, $m);
        foreach ($m[1] as $key) {
            $hooks[$key] = ($hooks[$key] ?? 0) + 1;
        }
        foreach ($definitions as $def) {
            if ($def['type'] === 'text') {
                $this->assertSame(1, $hooks[$def['key']] ?? 0, "text parameter {$def['key']} needs exactly one data-p target");
                $this->assertStringContainsString('data-p="'.$def['key'].'">'.$def['default'].'<', $svg, "{$def['key']} default must equal the artwork text");
            }
            if ($def['type'] === 'color') {
                $this->assertStringContainsString($def['token'].':'.$def['default'], $svg, "{$def['key']} default must equal the light token");
            }
        }
        $this->assertSame([], array_diff(array_keys($hooks), array_column($definitions, 'key')), 'every data-p hook is a declared parameter');
        foreach (['animate', 'loop', 'speed'] as $runtime) {
            $this->assertContains($runtime, array_column($definitions, 'key'));
        }
    }

    #[DataProvider('v2Assets')]
    public function test_v2_scales_every_duration_and_uses_the_vazirmatn_stack(string $slug, string $version): void
    {
        $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/animation.svg');

        preg_match_all('/animation(?:-delay|-duration)?:[^;}]+/', $svg, $m);
        $this->assertNotEmpty($m[0]);
        foreach ($m[0] as $declaration) {
            $this->assertDoesNotMatchRegularExpression('/(?<!calc\()(?<![\w.(-])-?\d*\.?\d+s\b/', $declaration, "unscaled time in: {$declaration}");
        }
        $this->assertStringContainsString("font-family:'Vazirmatn',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif", $svg);
        $this->assertStringNotContainsString('Tahoma', $svg);
        $this->assertStringContainsString('direction="rtl"', $svg);
    }

    public function test_the_bundled_font_ships_with_its_licence(): void
    {
        $fonts = __DIR__.'/../../assets/fonts';
        $this->assertFileExists($fonts.'/vazirmatn-motion.woff2');
        $this->assertSame('wOF2', substr(file_get_contents($fonts.'/vazirmatn-motion.woff2'), 0, 4));
        $this->assertLessThan(60_000, filesize($fonts.'/vazirmatn-motion.woff2'));
        $this->assertStringContainsString('SIL OPEN FONT LICENSE Version 1.1', file_get_contents($fonts.'/OFL.txt'));
        $this->assertStringContainsString('The Vazirmatn Project Authors', file_get_contents($fonts.'/OFL.txt'));
    }
}
