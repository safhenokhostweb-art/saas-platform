<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Module;
use App\Models\Theme;
use App\Models\ThemeQualityReport;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\Checks\AccessibilityBaselineCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\CommercialMetadataCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\DependencyIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\RenderIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\StructuralIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 2a — Production-Grade Theme Commercial Quality Gate.
 *
 * Proves the gate is fail-closed and atomic (a blocking failure never
 * creates a release, never advances current_release_id, never touches
 * legacy state), that warnings never block, that the report is structured
 * and persisted, and that the check list is genuinely extensible.
 */
class ThemeCommercialQualityGateTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $releaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
    }

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'qgate-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب کامل برای تست.',
            'header_blocks' => [['type' => 'text', 'props' => ['text' => 'هدر']]],
            'footer_blocks' => [['type' => 'text', 'props' => ['text' => 'فوتر']]],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    // ── 1. valid theme passes ─────────────────────────────────────────────

    public function test_a_valid_theme_passes_the_gate(): void
    {
        $theme = $this->makeGoodTheme();
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertTrue($report->isPassing());
        $this->assertSame(ThemeQualityReport::STATUS_PASSED, $report->status);
    }

    // ── 2/7/8/9. structural failure blocks release atomically ────────────

    public function test_structural_failure_blocks_release_and_leaves_no_partial_state(): void
    {
        $theme = $this->makeGoodTheme(['pages' => []]);
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        try {
            $gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
            $this->fail('Expected ThemeQualityGateFailedException.');
        } catch (ThemeQualityGateFailedException $e) {
            $this->assertFalse($e->report->isPassing());
            $this->assertNotEmpty($e->report->blockingFailures());
        }

        $this->assertSame(0, ThemeRelease::count());
        $this->assertNull($theme->fresh()->current_release_id);
    }

    // ── 3. rendering failure blocks release ──────────────────────────────

    public function test_a_rendering_exception_blocks_release(): void
    {
        $theme = $this->makeGoodTheme();

        $throwingRenderer = new class extends BlockRenderer {
            public function renderBlock(array $block): string
            {
                throw new \RuntimeException('simulated renderer crash');
            }
        };

        $gate = new ThemeCommercialQualityGate([new RenderIntegrityCheck($throwingRenderer)]);

        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());
        $codes = array_column($report->blockingFailures(), 'code');
        $this->assertContains('render.exception', $codes);
    }

    // ── 4. required dependency failure blocks release ────────────────────

    public function test_a_missing_required_module_dependency_blocks_release(): void
    {
        $theme = $this->makeGoodTheme();
        $gate = new ThemeCommercialQualityGate([new DependencyIntegrityCheck()]);

        $report = $gate->evaluate($theme, '1.0.0', [
            ['type' => 'module', 'ref' => 'does-not-exist-anywhere', 'is_required' => true],
        ]);

        $this->assertFalse($report->isPassing());
        $codes = array_column($report->blockingFailures(), 'code');
        $this->assertContains('dependency.required_module_missing', $codes);
    }

    public function test_an_existing_required_module_dependency_passes(): void
    {
        $module = Module::create(['slug' => 'qgate-forms-' . uniqid(), 'title' => 'Forms', 'is_active' => true]);
        $theme = $this->makeGoodTheme();
        $gate = new ThemeCommercialQualityGate([new DependencyIntegrityCheck()]);

        $report = $gate->evaluate($theme, '1.0.0', [
            ['type' => 'module', 'ref' => $module->slug, 'is_required' => true],
        ]);

        $this->assertTrue($report->isPassing());
    }

    // ── 5. critical accessibility failure blocks release ─────────────────

    public function test_an_image_with_no_alt_attribute_at_all_blocks_release(): void
    {
        $theme = $this->makeGoodTheme();

        $badRenderer = new class extends BlockRenderer {
            public function renderBlocks(?array $blocks): string
            {
                return '<img src="broken.jpg">'; // no alt attribute at all
            }
        };

        $gate = new ThemeCommercialQualityGate([new AccessibilityBaselineCheck($badRenderer)]);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());
        $codes = array_column($report->blockingFailures(), 'code');
        $this->assertContains('accessibility.image_missing_alt', $codes);
    }

    // ── 6. warnings never incorrectly block ───────────────────────────────

    public function test_a_warning_level_finding_does_not_block_the_gate(): void
    {
        $theme = $this->makeGoodTheme(['description' => null, 'category' => null]);
        $gate = new ThemeCommercialQualityGate([new CommercialMetadataCheck()]);

        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertTrue($report->isPassing());
        $this->assertNotEmpty($report->nonBlockingFindings());
    }

    public function test_an_optional_missing_module_dependency_is_a_warning_not_a_block(): void
    {
        $theme = $this->makeGoodTheme();
        $gate = new ThemeCommercialQualityGate([new DependencyIntegrityCheck()]);

        $report = $gate->evaluate($theme, '1.0.0', [
            ['type' => 'module', 'ref' => 'optional-missing-module', 'is_required' => false],
        ]);

        $this->assertTrue($report->isPassing());
        $codes = array_column($report->nonBlockingFindings(), 'code');
        $this->assertContains('dependency.optional_module_missing', $codes);
    }

    // ── 10/11. successful gate creates a real, traceable release ─────────

    public function test_a_passing_gate_creates_a_real_release_traceable_to_its_report(): void
    {
        $theme = $this->makeGoodTheme();
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        $release = $gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'Initial commercial release.');

        $this->assertInstanceOf(ThemeRelease::class, $release);
        $this->assertSame($release->id, $theme->fresh()->current_release_id);

        $report = ThemeQualityReport::where('theme_id', $theme->id)->sole();
        $this->assertTrue($report->isPassing());
        $this->assertSame($release->id, $report->theme_release_id);
        $this->assertNotEmpty($report->checks);
        // Structured, not boolean-only: every entry carries all five fields.
        foreach ($report->checks as $check) {
            $this->assertArrayHasKey('code', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('severity', $check);
            $this->assertArrayHasKey('message', $check);
            $this->assertArrayHasKey('details', $check);
        }
    }

    // ── 12. a future custom check can be registered without redesign ─────

    public function test_a_custom_future_check_can_be_registered(): void
    {
        $theme = $this->makeGoodTheme();

        $customCheck = new class implements ThemeQualityCheck {
            public function run(Theme $theme, string $version, ?array $dependencies): array
            {
                return [ThemeQualityCheckResult::fail('icon_pack_readiness', 'icon.not_ready', 'Simulated future Icon Pack check.')];
            }
        };

        $gate = new ThemeCommercialQualityGate([$customCheck]);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());
        $this->assertSame('icon_pack_readiness', $report->checks[0]['category']);
    }

    // ── 13. customer data remains untouched ───────────────────────────────

    public function test_evaluating_and_publishing_a_master_theme_does_not_touch_a_customer_instance(): void
    {
        $master = $this->makeGoodTheme(['slug' => 'qgate-master-' . uniqid()]);
        $site = $this->makeCrmTestSite();
        $instance = $this->makeGoodTheme([
            'slug' => 'qgate-instance-' . uniqid(),
            'site_id' => $site->id,
            'global_styles' => ['primary_color' => '#customer-color'],
        ]);

        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $gate->publishIfCompliant($master, $this->releaseService, '1.0.0', 'v1.');

        $instance->refresh();
        $this->assertNull($instance->current_release_id);
        $this->assertSame('#customer-color', $instance->global_styles['primary_color']);
        $this->assertSame(0, \App\Models\ThemeQualityReport::where('theme_id', $instance->id)->count());
    }

    // ── console command: multiple themes evaluated and reported honestly ──

    public function test_the_console_command_evaluates_multiple_themes_without_publishing_anything(): void
    {
        $good = $this->makeGoodTheme();
        $broken = $this->makeGoodTheme(['pages' => []]);

        $this->artisan('theme:quality-gate')->assertExitCode(1); // one failure present

        $this->assertSame(0, ThemeRelease::count()); // evaluation only, never publishes
        $this->assertGreaterThanOrEqual(2, ThemeQualityReport::count());
    }
}
