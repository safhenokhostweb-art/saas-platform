<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\Checks\ResponsiveReadinessCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2g — real browser-backed responsive verification. Every scenario
 * here mocks PlaywrightExtractionService (per this project's established
 * convention — see CaptureThemeScreenshotsCommandTest): a real, non-mocked
 * Chromium run against a real signed preview URL was proven manually
 * end-to-end before this check was wired in, exactly as this test suite
 * proves it stays wired in and maps evidence to findings correctly.
 */
class ResponsiveReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست پاسخگو',
            'slug' => 'responsive-check-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'تست.',
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
        ], $overrides));
    }

    /** @return array<int, array{category:string,code:string,status:string,severity:string,message:string,details:array}> */
    private function runCheck(Theme $theme, string $version = '1.0.0'): array
    {
        $results = $this->app->make(ResponsiveReadinessCheck::class)->run($theme, $version, null);
        return array_map(fn (ThemeQualityCheckResult $r) => $r->toArray(), $results);
    }

    private function cleanEvidence(array $overrides = []): array
    {
        return array_replace_recursive([
            'success' => true,
            'viewport_dimensions' => ['width' => 1440, 'height' => 900],
            'navigation' => ['ok' => true, 'error' => null],
            'dom' => ['rootPresent' => true, 'bodyContentLength' => 500],
            'overflow' => ['measured' => true, 'scrollWidth' => 1440, 'clientWidth' => 1440, 'hasOverflow' => false, 'overflowPx' => 0],
            'console_errors' => ['total' => 0, 'entries' => []],
            'page_errors' => ['total' => 0, 'entries' => []],
            'resource_failures' => ['total' => 0, 'entries' => []],
            'screenshot_base64' => '',
        ], $overrides);
    }

    // ── 1. no browser infra → UNVERIFIED, never a fabricated pass ─────────

    public function test_browser_infrastructure_unavailable_reports_unverified_never_pass(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(false);
            $mock->shouldReceive('availabilityReport')->andReturn(['node' => false, 'script' => true, 'playwright' => false, 'ready' => false, 'chrome_path' => null, 'using_system' => false]);
        });

        $theme = $this->makeTheme();
        $results = $this->runCheck($theme);

        $codes = array_column($results, 'code');
        foreach (['desktop', 'tablet', 'mobile'] as $viewport) {
            $this->assertContains("responsive.{$viewport}.capture_unavailable", $codes);
            $unverifiedResult = collect($results)->firstWhere('code', "responsive.{$viewport}.capture_unavailable");
            $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $unverifiedResult['status']);
        }
    }

    // ── 2. all three viewports captured for real (mocked) ──────────────────

    public function test_desktop_tablet_and_mobile_all_capture_successfully(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence());
        });

        $theme = $this->makeTheme();
        $results = $this->runCheck($theme);

        $codes = array_column($results, 'code');
        foreach (['desktop', 'tablet', 'mobile'] as $viewport) {
            $this->assertContains("responsive.{$viewport}.captured", $codes);
        }
    }

    // ── 3. browser cannot render the page at all → BLOCKING ────────────────

    public function test_navigation_failure_is_a_blocking_failure(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'navigation' => ['ok' => false, 'error' => 'HTTP 500'],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        $failures = array_filter($results, fn ($r) => str_contains($r['code'], 'navigation_failed'));
        $this->assertNotEmpty($failures);
        foreach ($failures as $f) {
            $this->assertSame(ThemeQualityCheckResult::STATUS_FAILED, $f['status']);
            $this->assertSame(ThemeQualityCheckResult::SEVERITY_BLOCKING, $f['severity']);
        }
    }

    // ── 4. catastrophic overflow blocks; minor overflow only warns ─────────

    public function test_catastrophic_horizontal_overflow_blocks(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'overflow' => ['measured' => true, 'scrollWidth' => 2200, 'clientWidth' => 1440, 'hasOverflow' => true, 'overflowPx' => 760],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        $overflow = array_values(array_filter($results, fn ($r) => $r['code'] === 'responsive.horizontal_overflow'));
        $this->assertNotEmpty($overflow);
        $this->assertSame(ThemeQualityCheckResult::STATUS_FAILED, $overflow[0]['status']);
        $this->assertSame(ThemeQualityCheckResult::SEVERITY_BLOCKING, $overflow[0]['severity']);
    }

    public function test_minor_horizontal_overflow_only_warns(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'overflow' => ['measured' => true, 'scrollWidth' => 1460, 'clientWidth' => 1440, 'hasOverflow' => true, 'overflowPx' => 20],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        $overflow = array_values(array_filter($results, fn ($r) => $r['code'] === 'responsive.horizontal_overflow'));
        $this->assertNotEmpty($overflow);
        $this->assertSame(ThemeQualityCheckResult::STATUS_WARNING, $overflow[0]['status']);
        $this->assertSame(ThemeQualityCheckResult::SEVERITY_WARNING, $overflow[0]['severity']);
        // Minor overflow alone must not block the whole viewport's verdict.
        $this->assertContains('responsive.desktop.captured', array_column($results, 'code'));
    }

    // ── 5. console errors are recorded but never treated as blocking ───────

    public function test_console_errors_are_a_warning_not_a_blocker(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'console_errors' => ['total' => 2, 'entries' => [['level' => 'error', 'text' => 'x']]],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        $consoleFindings = array_values(array_filter($results, fn ($r) => $r['code'] === 'browser.console_error'));
        $this->assertNotEmpty($consoleFindings);
        foreach ($consoleFindings as $f) {
            $this->assertSame(ThemeQualityCheckResult::SEVERITY_WARNING, $f['severity']);
        }
        $this->assertNotEmpty(array_filter($results, fn ($r) => $r['status'] === ThemeQualityCheckResult::STATUS_PASSED));
    }

    // ── 6. an uncaught JS exception is fatal → BLOCKING ─────────────────────

    public function test_uncaught_javascript_exception_blocks(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'page_errors' => ['total' => 1, 'entries' => [['message' => 'TypeError: x is not a function']]],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        $pageErrors = array_values(array_filter($results, fn ($r) => $r['code'] === 'browser.page_error'));
        $this->assertNotEmpty($pageErrors);
        $this->assertSame(ThemeQualityCheckResult::SEVERITY_BLOCKING, $pageErrors[0]['severity']);
    }

    // ── 7. a required local asset failing blocks; an optional remote one warns ─

    public function test_local_resource_failure_blocks_remote_resource_failure_warns(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'resource_failures' => ['total' => 2, 'entries' => [
                    ['url' => '/preview/app.css', 'resourceType' => 'stylesheet', 'error' => 'net::ERR_FAILED', 'sameOriginAsTarget' => true],
                    ['url' => 'https://fonts.googleapis.com/x.css', 'resourceType' => 'stylesheet', 'error' => 'net::ERR_FAILED', 'sameOriginAsTarget' => false],
                ]],
            ]));
        });

        $results = $this->runCheck($this->makeTheme());

        // Evaluated once per viewport (desktop/tablet/mobile), each finding
        // the same local+remote failure pair — 2 findings x 3 viewports.
        $findings = array_values(array_filter($results, fn ($r) => $r['code'] === 'browser.resource_failure'));
        $this->assertCount(6, $findings);
        $severities = array_column($findings, 'severity');
        $this->assertContains(ThemeQualityCheckResult::SEVERITY_BLOCKING, $severities);
        $this->assertContains(ThemeQualityCheckResult::SEVERITY_WARNING, $severities);
    }

    // ── 8. a Node/Chromium process failure is infrastructure, not a theme defect ──

    public function test_process_level_failure_is_unverified_not_a_failure(): void
    {
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andThrow(new \RuntimeException('Playwright: timeout بعد از 90 ثانیه.'));
        });

        $results = $this->runCheck($this->makeTheme());

        $capturesFailed = array_values(array_filter($results, fn ($r) => str_contains($r['code'], 'capture_failed')));
        $this->assertCount(3, $capturesFailed);
        foreach ($capturesFailed as $f) {
            $this->assertSame(ThemeQualityCheckResult::STATUS_UNVERIFIED, $f['status']);
        }
    }

    // ── 9. real screenshot evidence is stored and referenced, distinctly from Marketplace assets ──

    public function test_screenshot_evidence_is_stored_separately_from_marketplace_screenshots(): void
    {
        Storage::fake('public');
        $this->mock(PlaywrightExtractionService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('runQualityCheck')->andReturn($this->cleanEvidence([
                'screenshot_base64' => base64_encode('fake-quality-evidence-png'),
            ]));
        });

        $theme = $this->makeTheme();
        $results = $this->runCheck($theme, '9.9.9');

        $desktopPass = collect($results)->firstWhere('code', 'responsive.desktop.captured');
        $this->assertNotNull($desktopPass);
        $path = $desktopPass['details']['screenshot_path'] ?? null;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('theme-quality-evidence/', $path);
        $this->assertStringContainsString($theme->slug, $path);
        Storage::disk('public')->assertExists($path);

        // Never the Marketplace marketing screenshot path, and never touches
        // the Theme/ThemeMarketplace marketing fields at all.
        $this->assertStringNotContainsString('theme-screenshots/', $path);
        $this->assertNull($theme->fresh()->thumbnail);
    }
}
