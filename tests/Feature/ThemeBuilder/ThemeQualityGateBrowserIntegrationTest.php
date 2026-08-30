<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 2g — proves the two new browser-backed checks (ResponsiveReadinessCheck,
 * ColorContrastCheck) are genuinely wired into the ONE Commercial Quality
 * Gate's default check list — not a second, parallel quality system — and
 * that the Mandatory Publish Gate's atomicity and fail-closed guarantees
 * (Phase 2c/2d) hold exactly the same way for a browser-evidenced failure
 * as for any other check's failure.
 */
class ThemeQualityGateBrowserIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست یکپارچه‌سازی',
            'slug' => 'browser-gate-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'تست.',
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    public function test_the_default_check_list_includes_both_new_browser_backed_checks(): void
    {
        // No mock at all — this exercises the base TestCase's default
        // "browser infrastructure unavailable" binding, proving the gate
        // never silently drops these checks even when nothing mocks them.
        $theme = $this->makeGoodTheme();
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        $report = $gate->evaluate($theme, '1.0.0');

        $categories = array_column($report->checks, 'category');
        $this->assertContains('responsive_readiness', $categories);
        $this->assertContains('accessibility', $categories);

        $codes = array_column($report->checks, 'code');
        $this->assertContains('accessibility.contrast_unavailable', $codes);
        $this->assertContains('responsive.desktop.capture_unavailable', $codes);
    }

    public function test_a_real_browser_evidenced_contrast_failure_blocks_publish_atomically(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'viewport_dimensions' => ['width' => 1440, 'height' => 900],
                'navigation' => ['ok' => true, 'error' => null],
                'dom' => ['rootPresent' => true, 'bodyContentLength' => 500],
                'overflow' => ['measured' => true, 'scrollWidth' => 1440, 'clientWidth' => 1440, 'hasOverflow' => false, 'overflowPx' => 0],
                'console_errors' => ['total' => 0, 'entries' => []],
                'page_errors' => ['total' => 0, 'entries' => []],
                'resource_failures' => ['total' => 0, 'entries' => []],
                'screenshot_base64' => '',
                'contrast' => ['sampled' => 3, 'checked' => 3, 'passed' => 2, 'failed' => 1, 'unverified' => 0, 'failures' => [
                    ['tag' => 'span', 'text' => 'x', 'fg' => 'rgb(107,114,128)', 'bg' => 'rgb(219,234,254)', 'ratio' => 3.96, 'required' => 4.5, 'isLargeText' => false],
                ]],
            ]);
        });

        $theme = $this->makeGoodTheme();
        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $releaseService = $this->app->make(ThemeReleaseService::class);

        try {
            $gate->publishIfCompliant($theme, $releaseService, '1.0.0', 'v1.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            $codes = array_column($e->report->blockingFailures(), 'code');
            $this->assertContains('accessibility.contrast_insufficient', $codes);
        }

        // Atomic: no release, no current_release_id change — identical
        // guarantee to every other check's blocking failure (Phase 2c).
        $this->assertSame(0, ThemeRelease::count());
        $this->assertNull($theme->fresh()->current_release_id);
    }

    public function test_evaluating_with_real_browser_evidence_never_touches_another_themes_data_or_screenshots(): void
    {
        Storage::fake('public');
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn([
                'success' => true,
                'viewport_dimensions' => ['width' => 1440, 'height' => 900],
                'navigation' => ['ok' => true, 'error' => null],
                'dom' => ['rootPresent' => true, 'bodyContentLength' => 500],
                'overflow' => ['measured' => true, 'scrollWidth' => 1440, 'clientWidth' => 1440, 'hasOverflow' => false, 'overflowPx' => 0],
                'console_errors' => ['total' => 0, 'entries' => []],
                'page_errors' => ['total' => 0, 'entries' => []],
                'resource_failures' => ['total' => 0, 'entries' => []],
                'screenshot_base64' => base64_encode('fake-evidence-png'),
                'contrast' => ['sampled' => 3, 'checked' => 3, 'passed' => 3, 'failed' => 0, 'unverified' => 0, 'failures' => []],
            ]);
        });

        $theme = $this->makeGoodTheme();
        $site = $this->makeCrmTestSite();
        $customerInstance = $this->makeGoodTheme(['slug' => 'browser-gate-instance-' . uniqid(), 'site_id' => $site->id]);

        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $gate->evaluate($theme, '1.0.0');

        $customerInstance->refresh();
        $this->assertNull($customerInstance->current_release_id);
        $this->assertSame(0, \App\Models\ThemeQualityReport::where('theme_id', $customerInstance->id)->count());

        // Evidence for this theme only — nothing under the customer
        // instance's slug, and no Marketplace marketing path touched.
        Storage::disk('public')->assertExists("theme-quality-evidence/{$theme->slug}/1.0.0/desktop.png");
        $allFiles = Storage::disk('public')->allFiles();
        foreach ($allFiles as $file) {
            $this->assertStringNotContainsString($customerInstance->slug, $file);
            $this->assertStringNotContainsString('theme-screenshots/', $file);
        }
    }
}
