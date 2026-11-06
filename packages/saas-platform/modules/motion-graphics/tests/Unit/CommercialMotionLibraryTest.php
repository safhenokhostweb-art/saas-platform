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
            'hero v3' => ['hero-motion', 'v3', 'free'],
            'business growth v3' => ['business-growth-motion', 'v3', 'premium'],
        ];
    }

    /** Versions with runtime parameters (ADR-079 and later). */
    public static function v2Assets(): array
    {
        return array_filter(self::assets(), fn (array $a): bool => $a[1] !== 'v1');
    }

    public static function v3Assets(): array
    {
        return array_filter(self::assets(), fn (array $a): bool => $a[1] === 'v3');
    }

    private const FULL_FONT_SHA256 = '4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92';

    private static function definitions(string $slug, string $version): array
    {
        $declared = json_decode(file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR)['parameters'];

        return MotionSvgComposer::normalizeDefinitions($declared);
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

    #[DataProvider('v3Assets')]
    public function test_v3_makes_every_text_editable(string $slug, string $version): void
    {
        $doc = new \DOMDocument();
        $this->assertTrue($doc->load(self::LIBRARY.'/'.$slug.'/'.$version.'/animation.svg'));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('s', 'http://www.w3.org/2000/svg');

        $texts = 0;
        foreach ($xpath->query('//s:text//text()[normalize-space()]') as $node) {
            $texts++;
            $this->assertTrue(
                $node->parentNode instanceof \DOMElement && $node->parentNode->hasAttribute('data-p'),
                "visible text «{$node->textContent}» has no data-p hook"
            );
        }
        $this->assertGreaterThan(10, $texts);
    }

    #[DataProvider('v3Assets')]
    public function test_v3_exposes_every_colour_token_the_artwork_uses_and_a_scheme(string $slug, string $version): void
    {
        $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/animation.svg');
        $definitions = self::definitions($slug, $version);

        preg_match_all('/var\((--asset-[a-z]+)\)/', $svg, $m);
        $used = array_values(array_unique($m[1]));
        sort($used);
        $declared = array_column(array_filter($definitions, fn (array $d): bool => $d['type'] === 'color'), 'token');
        sort($declared);
        $this->assertSame($used, $declared, 'colour parameters = the tokens the artwork actually reads');
        $this->assertDoesNotMatchRegularExpression('/fill:#|fill="#|stroke:#|stroke="#/i', preg_replace('/<style id="asset-theme">.*?<\/style>/s', '', $svg), 'colours only through tokens');

        $scheme = array_values(array_filter($definitions, fn (array $d): bool => $d['key'] === 'scheme'));
        $this->assertCount(1, $scheme);
        $this->assertSame('scheme', $scheme[0]['role']);
        $this->assertSame(['auto', 'light', 'dark'], array_keys($scheme[0]['options']));
    }

    #[DataProvider('v3Assets')]
    public function test_v3_sections_and_show_switches_match_one_to_one(string $slug, string $version): void
    {
        $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/animation.svg');
        preg_match_all('/data-s="([a-z0-9_]+)"/', $svg, $m);
        $hooks = array_values(array_unique($m[1]));
        sort($hooks);
        $switches = array_column(array_filter(self::definitions($slug, $version), fn (array $d): bool => $d['type'] === 'boolean' && $d['role'] === 'show'), 'key');
        sort($switches);

        $this->assertNotEmpty($switches);
        $this->assertSame($hooks, $switches);
        foreach (self::definitions($slug, $version) as $def) {
            if ($def['type'] === 'boolean' && $def['role'] === 'show') {
                $this->assertSame('sections', $def['group']);
                $this->assertTrue($def['default'], 'every section is visible by default');
            }
        }
    }

    #[DataProvider('v3Assets')]
    public function test_v3_asks_for_the_full_vazirmatn_font(string $slug, string $version): void
    {
        $metadata = json_decode(file_get_contents(self::LIBRARY.'/'.$slug.'/'.$version.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('vazirmatn-variable', $metadata['typography']['embedded_font']);
        $this->assertSame('100 900', $metadata['typography']['font_weights']);
    }

    public function test_the_full_vazirmatn_font_ships_unmodified(): void
    {
        $font = __DIR__.'/../../assets/fonts/vazirmatn-variable.woff2';

        $this->assertFileExists($font);
        $this->assertSame('wOF2', substr(file_get_contents($font), 0, 4));
        $this->assertSame(self::FULL_FONT_SHA256, hash_file('sha256', $font), 'the complete Vazirmatn 33.003 variable font, byte for byte');
        $this->assertStringContainsString(self::FULL_FONT_SHA256, file_get_contents(__DIR__.'/../../assets/fonts/README.md'));
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
