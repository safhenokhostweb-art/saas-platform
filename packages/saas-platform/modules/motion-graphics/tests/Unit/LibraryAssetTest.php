<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LibraryAssetTest extends TestCase
{
    private const ASSET = __DIR__.'/../../assets/library/iranian-business-growth-network/v1';

    public function test_iranian_business_asset_package_is_complete_and_small(): void
    {
        foreach (['animation.svg', 'preview.svg', 'metadata.json', 'AI_EDITING_INSTRUCTIONS.txt', 'README.md'] as $file) {
            $this->assertFileExists(self::ASSET.'/'.$file);
        }

        $metadata = json_decode(file_get_contents(self::ASSET.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['name', 'slug', 'category', 'purpose', 'description', 'usage', 'tags', 'editable_properties', 'ai_editable'] as $key) {
            $this->assertArrayHasKey($key, $metadata);
        }
        $this->assertSame('iranian-business-growth-network', $metadata['slug']);
        $this->assertSame('svg-js', $metadata['engine']);
        $this->assertTrue($metadata['ai_editable']);
        $this->assertSame([], $metadata['dependencies']);
        $this->assertLessThan(100_000, filesize(self::ASSET.'/animation.svg'));
    }

    public function test_svg_assets_are_safe_self_contained_and_responsive(): void
    {
        foreach (['animation.svg', 'preview.svg'] as $file) {
            $svg = file_get_contents(self::ASSET.'/'.$file);
            $this->assertNotFalse(simplexml_load_string($svg));
            $this->assertStringContainsString('viewBox="0 0 960 540"', $svg);
            $this->assertDoesNotMatchRegularExpression('/<script\b|<foreignObject\b|\son\w+\s*=|(?:href|src)\s*=\s*[\'\"]https?:/i', $svg);
        }
        $this->assertStringContainsString('prefers-reduced-motion:reduce', file_get_contents(self::ASSET.'/animation.svg'));
    }
}
