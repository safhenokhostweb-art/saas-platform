<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\Checks\ColorContrastCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2g — real, browser-computed WCAG AA contrast verification. Proves
 * the check moves from "entirely UNVERIFIED" (the old, honest-but-useless
 * baseline) to "verified where computation is reliable, UNVERIFIED only
 * where it genuinely cannot be determined" — never a guess presented as a
 * pass.
 */
class ColorContrastCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeTheme(): Theme
    {
        return Theme::create([
            'name' => 'قالب تست کنتراست',
            'slug' => 'contrast-check-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'تست.',
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
        ]);
    }

    /** @return array<int, array{category:string,code:string,status:string,severity:string,message:string,details:array}> */
    private function runCheck(Theme $theme): array
    {
        $results = $this->app->make(ColorContrastCheck::class)->run($theme, '1.0.0', null);
        return array_map(fn (ThemeQualityCheckResult $r) => $r->toArray(), $results);
    }

    public function test_browser_infrastructure_unavailable_is_unverified(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(false);
            $mock->shouldReceive('availabilityReport')->andReturn(['node' => false, 'script' => true, 'playwright' => false, 'ready' => false, 'chrome_path' => null, 'using_system' => false]);
        });

        $results = $this->runCheck($this->makeTheme());

        $this->assertCount(1, $results);
        $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $results[0]['status']);
        $this->assertSame('accessibility.contrast_unavailable', $results[0]['code']);
    }

    public function test_reliable_high_contrast_case_is_verified_as_a_real_pass(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'contrast' => ['sampled' => 10, 'checked' => 10, 'passed' => 10, 'failed' => 0, 'unverified' => 0, 'failures' => []],
            ]);
        });

        $results = $this->runCheck($this->makeTheme());

        $pass = collect($results)->firstWhere('code', 'accessibility.contrast_verified');
        $this->assertNotNull($pass);
        $this->assertSame(ThemeQualityCheckResult::STATUS_PASSED, $pass['status']);
        // Never overclaims a full audit.
        $this->assertStringContainsString('Not an exhaustive audit', $pass['message']);
    }

    public function test_a_real_low_contrast_combination_is_a_blocking_failure(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'contrast' => ['sampled' => 5, 'checked' => 5, 'passed' => 4, 'failed' => 1, 'unverified' => 0, 'failures' => [
                    ['tag' => 'span', 'text' => 'کم‌رنگ', 'fg' => 'rgb(107,114,128)', 'bg' => 'rgb(219,234,254)', 'ratio' => 3.96, 'required' => 4.5, 'isLargeText' => false],
                ]],
            ]);
        });

        $results = $this->runCheck($this->makeTheme());

        $fail = collect($results)->firstWhere('code', 'accessibility.contrast_insufficient');
        $this->assertNotNull($fail);
        $this->assertSame(ThemeQualityCheckResult::STATUS_FAILED, $fail['status']);
        $this->assertSame(ThemeQualityCheckResult::SEVERITY_BLOCKING, $fail['severity']);
        $this->assertSame(3.96, $fail['details']['ratio']);
    }

    public function test_an_unresolvable_background_is_reported_unverified_never_guessed(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'contrast' => ['sampled' => 6, 'checked' => 4, 'passed' => 4, 'failed' => 0, 'unverified' => 2, 'failures' => []],
            ]);
        });

        $results = $this->runCheck($this->makeTheme());

        $unresolved = collect($results)->firstWhere('code', 'accessibility.contrast_partially_unresolvable');
        $this->assertNotNull($unresolved);
        $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $unresolved['status']);
        $this->assertSame(2, $unresolved['details']['unresolvable_count']);
        // A partial unresolvable set doesn't erase real, verified passes.
        $this->assertNotNull(collect($results)->firstWhere('code', 'accessibility.contrast_verified'));
    }

    public function test_process_level_failure_is_unverified_not_a_theme_defect(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andThrow(new \RuntimeException('Playwright: فرآیند Node.js راه‌اندازی نشد.'));
        });

        $results = $this->runCheck($this->makeTheme());

        $this->assertCount(1, $results);
        $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $results[0]['status']);
        $this->assertSame('accessibility.contrast_unavailable', $results[0]['code']);
    }

    public function test_no_sampleable_text_is_reported_unverified_not_a_silent_pass(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'contrast' => ['sampled' => 0, 'checked' => 0, 'passed' => 0, 'failed' => 0, 'unverified' => 0, 'failures' => []],
            ]);
        });

        $results = $this->runCheck($this->makeTheme());

        $this->assertCount(1, $results);
        $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $results[0]['status']);
        $this->assertSame('accessibility.contrast_no_samples', $results[0]['code']);
    }
}
