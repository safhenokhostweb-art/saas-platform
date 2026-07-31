<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use Tests\TestCase;

class TemplateBuilderCompatibilityValidatorTest extends TestCase
{
    private function validator(): TemplateBuilderCompatibilityValidator
    {
        return new TemplateBuilderCompatibilityValidator();
    }

    public function test_it_passes_when_no_rules_are_declared(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie');

        $report = $this->validator()->validate($asset, []);

        $this->assertTrue($report->valid);
    }

    public function test_asset_type_mismatch_is_rejected(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie');

        $report = $this->validator()->validate($asset, ['asset_type' => 'svg']);

        $this->assertFalse($report->valid);
        $this->assertSame('asset_type_mismatch', $report->errors[0]['code']);
    }

    public function test_section_tag_membership_is_checked(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['tags' => ['hero', 'finance']]);

        $this->assertTrue($this->validator()->validate($asset, ['section' => 'hero'])->valid);
        $this->assertFalse($this->validator()->validate($asset, ['section' => 'footer'])->valid);
    }

    public function test_industry_tag_membership_is_checked(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['tags' => ['finance']]);

        $this->assertTrue($this->validator()->validate($asset, ['industry' => 'finance'])->valid);

        $report = $this->validator()->validate($asset, ['industry' => 'retail']);
        $this->assertFalse($report->valid);
        $this->assertSame('industry_mismatch', $report->errors[0]['code']);
    }

    public function test_theme_tokens_required_checks_theme_support(): void
    {
        $themed = new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['theme_support' => true]);
        $unthemed = new MotionAssetReferenceDTO('motion-2', 1, 'lottie', ['theme_support' => false]);

        $this->assertTrue($this->validator()->validate($themed, ['theme_tokens_required' => true])->valid);
        $this->assertFalse($this->validator()->validate($unthemed, ['theme_tokens_required' => true])->valid);
    }

    public function test_multiple_mismatches_are_all_reported(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'svg', ['tags' => []]);

        $report = $this->validator()->validate($asset, ['asset_type' => 'lottie', 'section' => 'hero']);

        $this->assertFalse($report->valid);
        $this->assertCount(2, $report->errors);
    }
}
