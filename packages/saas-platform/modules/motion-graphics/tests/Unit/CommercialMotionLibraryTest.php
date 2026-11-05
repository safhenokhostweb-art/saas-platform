<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ADR-078: the two commercial library motions are complete, safe,
 * self-contained, light/dark aware, reduced-motion safe and RTL.
 */
final class CommercialMotionLibraryTest extends TestCase
{
    private const LIBRARY = __DIR__.'/../../assets/library';

    public static function assets(): array
    {
        return [
            'hero' => ['hero-motion', 'free'],
            'business growth' => ['business-growth-motion', 'premium'],
        ];
    }

    #[DataProvider('assets')]
    public function test_package_is_complete_with_a_declared_license_tier(string $slug, string $tier): void
    {
        $dir = self::LIBRARY.'/'.$slug.'/v1';
        foreach (['animation.svg', 'preview.svg', 'metadata.json', 'AI_EDITING_INSTRUCTIONS.txt', 'README.md'] as $file) {
            $this->assertFileExists($dir.'/'.$file);
        }

        $metadata = json_decode(file_get_contents($dir.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['name', 'slug', 'category', 'purpose', 'description', 'usage', 'tags', 'editable_properties', 'ai_editable', 'dimensions', 'accessibility'] as $key) {
            $this->assertArrayHasKey($key, $metadata);
        }
        $this->assertSame($slug, $metadata['slug']);
        $this->assertSame('svg-js', $metadata['engine']);
        $this->assertSame([], $metadata['dependencies']);
        $this->assertSame($tier, $metadata['license_tier']);
        $this->assertSame('0 0 960 540', $metadata['dimensions']['viewBox']);
    }

    #[DataProvider('assets')]
    public function test_svg_is_safe_self_contained_and_lightweight(string $slug): void
    {
        foreach (['animation.svg', 'preview.svg'] as $file) {
            $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/v1/'.$file);

            $this->assertNotFalse(simplexml_load_string($svg), "{$file} is not well-formed XML");
            $this->assertStringContainsString('viewBox="0 0 960 540"', $svg);
            $this->assertStringContainsString('role="img"', $svg);
            $this->assertMatchesRegularExpression('/<title\b[^>]*>[^<]+<\/title>/u', $svg);
            // Same rule MotionAssetPipeline::validateExecutableContent() enforces.
            $this->assertDoesNotMatchRegularExpression('/<script\b|\son\w+\s*=|<foreignObject\b|(?:href|src)\s*=\s*[\'"]https?:/i', $svg);
            // No SMIL: CSS cannot stop it under prefers-reduced-motion.
            $this->assertDoesNotMatchRegularExpression('/<animate|<set\b/i', $svg);
            // No web fonts or other remote loads, no costly filters.
            $this->assertDoesNotMatchRegularExpression('/@import|url\(\s*[\'"]?(?:https?:)?\/\//i', $svg);
            $this->assertStringNotContainsString('<filter', $svg);
            // var() only through CSS classes — presentation attributes with
            // var() are not reliable across engines.
            $this->assertDoesNotMatchRegularExpression('/\s(?:fill|stroke|stop-color)="var\(/', $svg);
            $this->assertLessThan(20_000, strlen($svg), "{$file} exceeds the 20 KB budget");
        }
    }

    #[DataProvider('assets')]
    public function test_animation_honours_color_scheme_reduced_motion_and_rtl(string $slug): void
    {
        $svg = file_get_contents(self::LIBRARY.'/'.$slug.'/v1/animation.svg');

        $this->assertStringContainsString('prefers-reduced-motion:reduce', $svg);
        $this->assertStringContainsString('prefers-color-scheme:dark', $svg);
        $this->assertStringContainsString('<style id="asset-theme">', $svg);
        $this->assertStringContainsString('direction:rtl', $svg);
        // Persian digits, not Latin ones, in visible copy.
        $this->assertMatchesRegularExpression('/[۰-۹]/u', $svg);
        $this->assertDoesNotMatchRegularExpression('/>[^<]*[0-9][^<]*<\/text>/u', $svg);
    }

    #[DataProvider('assets')]
    public function test_preview_is_the_same_artwork_with_animation_disabled(string $slug): void
    {
        $dir = self::LIBRARY.'/'.$slug.'/v1';
        $preview = file_get_contents($dir.'/preview.svg');

        $this->assertStringContainsString('*{animation:none!important}', $preview);
        $this->assertSame(
            file_get_contents($dir.'/animation.svg'),
            str_replace('*{animation:none!important}', '@media (prefers-reduced-motion:reduce){*{animation:none!important}}', $preview)
        );
    }
}
