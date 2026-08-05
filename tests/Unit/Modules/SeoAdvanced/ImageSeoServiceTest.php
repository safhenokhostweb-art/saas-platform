<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use SaaSPlatform\Modules\SeoAdvanced\Services\ImageSeoService;
use Tests\TestCase;

class ImageSeoServiceTest extends TestCase
{
    private function service(): ImageSeoService
    {
        return new ImageSeoService();
    }

    public function test_flags_missing_alt_text(): void
    {
        $issues = $this->service()->audit([['src' => 'a.jpg', 'alt' => '']]);

        $this->assertTrue(collect($issues)->contains('code', 'missing_alt'));
    }

    public function test_does_not_flag_alt_when_present(): void
    {
        $issues = $this->service()->audit([['src' => 'a.jpg', 'alt' => 'متن جایگزین', 'size_bytes' => 1000, 'format' => 'webp']]);

        $this->assertFalse(collect($issues)->contains('code', 'missing_alt'));
    }

    public function test_flags_large_images(): void
    {
        $issues = $this->service()->audit([['src' => 'a.jpg', 'alt' => 'x', 'size_bytes' => 500 * 1024, 'format' => 'webp']]);

        $this->assertTrue(collect($issues)->contains('code', 'large_image'));
    }

    public function test_flags_unsupported_formats(): void
    {
        $issues = $this->service()->audit([['src' => 'a.tiff', 'alt' => 'x', 'format' => 'tiff']]);

        $this->assertTrue(collect($issues)->contains('code', 'unsupported_format'));
    }

    public function test_suggests_webp_for_legacy_but_acceptable_formats(): void
    {
        $issues = $this->service()->audit([['src' => 'a.jpg', 'alt' => 'x', 'format' => 'jpg']]);

        $this->assertTrue(collect($issues)->contains('code', 'not_modern_format'));
    }

    public function test_does_not_suggest_webp_for_svg(): void
    {
        $issues = $this->service()->audit([['src' => 'a.svg', 'alt' => 'x', 'format' => 'svg']]);

        $this->assertFalse(collect($issues)->contains('code', 'not_modern_format'));
    }

    public function test_flags_poor_camera_generated_filenames(): void
    {
        foreach (['IMG_1234.jpg', 'DSC0001.png', 'photo123.jpg', 'Screenshot.png'] as $filename) {
            $issues = $this->service()->audit([['src' => $filename, 'alt' => 'x', 'format' => 'webp']]);
            $this->assertTrue(collect($issues)->contains('code', 'poor_filename'), "expected poor_filename for {$filename}");
        }
    }

    public function test_does_not_flag_descriptive_filenames(): void
    {
        $issues = $this->service()->audit([['src' => 'kafsh-varzeshi-mardane.jpg', 'alt' => 'x', 'format' => 'webp']]);

        $this->assertFalse(collect($issues)->contains('code', 'poor_filename'));
    }

    public function test_a_well_optimized_image_produces_no_issues(): void
    {
        $issues = $this->service()->audit([[
            'src' => 'kafsh-varzeshi.webp', 'alt' => 'کفش ورزشی مردانه', 'size_bytes' => 100 * 1024, 'format' => 'webp',
        ]]);

        $this->assertEmpty($issues);
    }
}
