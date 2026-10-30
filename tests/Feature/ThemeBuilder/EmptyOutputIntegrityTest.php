<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeQualityReport;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\Checks\RenderIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2f — Eliminate Canonical Empty Rendering & Strengthen Runtime
 * Content Integrity (ADR-027).
 *
 * Proves BlockRegistry::emptyOutputPolicy() is the one durable,
 * registry-driven distinction RenderIntegrityCheck uses to tell accidental
 * empty output (a canonical commercial block missing required content —
 * BLOCKING) apart from a deliberately optional accent left unused
 * (informational, never a defect) apart from a runtime/placeholder-backed
 * type whose contract promises it never renders truly empty (still
 * BLOCKING if that promise is ever broken) — with zero block-type names
 * hardcoded into the check itself.
 */
class EmptyOutputIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'empty-output-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب کامل برای تست.',
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    // ── 1/10. the real catalog's 4 known occurrences are now resolved ─────

    public function test_the_real_canonical_catalog_has_zero_unexplained_empty_output(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        foreach (Theme::whereNull('site_id')->get() as $theme) {
            $report = $gate->evaluate($theme, '1.0.0');
            $emptyFindings = array_filter($report->checks, fn (array $c) => $c['code'] === 'render.empty_output');
            $this->assertSame([], array_values($emptyFindings), "{$theme->slug} has unexplained empty output: " . json_encode($emptyFindings));
        }
    }

    // ── 2. accidental empty output (flagged/default policy) is detected, blocking ──

    public function test_accidental_empty_output_on_a_default_policy_type_is_blocking(): void
    {
        // promo_banner defaults to 'optional' in the registry; construct a
        // hypothetical unclassified case by using a check bound to a
        // renderer that returns truly empty output for an ordinary type
        // BlockRegistry has never given a policy to (its own default is
        // EMPTY_POLICY_FLAGGED).
        $this->assertSame(BlockRegistry::EMPTY_POLICY_FLAGGED, BlockRegistry::emptyOutputPolicy('does_not_exist_as_a_registered_type'));
        $this->assertSame(BlockRegistry::EMPTY_POLICY_FLAGGED, BlockRegistry::emptyOutputPolicy('heading'));

        $emptyRenderer = new class extends BlockRenderer {
            public function renderHeading(array $props, string $inner, string $id): string
            {
                return '';
            }
        };

        $theme = $this->makeGoodTheme(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['type' => 'heading', 'props' => ['text' => 'عنوان']],
        ]]]]);

        $gate = new ThemeCommercialQualityGate([new RenderIntegrityCheck($emptyRenderer)]);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());
        $codes = array_column($report->blockingFailures(), 'code');
        $this->assertContains('render.empty_output', $codes);
    }

    // ── 3. legitimate optional-accent empty state follows its explicit contract ──

    public function test_an_unused_optional_accent_reports_an_explicit_non_blocking_finding(): void
    {
        $this->assertSame(BlockRegistry::EMPTY_POLICY_OPTIONAL, BlockRegistry::emptyOutputPolicy('promo_banner'));

        $theme = $this->makeGoodTheme(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['type' => 'hero', 'props' => ['title' => 'خوش آمدید']],
            ['type' => 'promo_banner', 'props' => []], // no text — deliberately unused
        ]]]]);

        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertTrue($report->isPassing());
        $codes = array_column($report->checks, 'code');
        $this->assertContains('render.intentionally_empty', $codes);
        $this->assertNotContains('render.empty_output', $codes);
    }

    // ── 4. a placeholder-contract type that is STILL empty is a broken-contract defect ──

    public function test_a_broken_placeholder_contract_is_still_blocking(): void
    {
        $this->assertSame(BlockRegistry::EMPTY_POLICY_PLACEHOLDER, BlockRegistry::emptyOutputPolicy('client_logos'));

        $brokenRenderer = new class extends BlockRenderer {
            public function renderClientLogos(array $props, string $inner, string $id): string
            {
                return ''; // simulates a regression that breaks the placeholder promise
            }
        };

        $theme = $this->makeGoodTheme(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['type' => 'client_logos', 'props' => ['title' => 'مشتریان']],
        ]]]]);

        $gate = new ThemeCommercialQualityGate([new RenderIntegrityCheck($brokenRenderer)]);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());
        $codes = array_column($report->blockingFailures(), 'code');
        $this->assertContains('render.empty_output', $codes);
    }

    // ── 5. Builder placeholder never becomes fake runtime customer data ───

    public function test_client_logos_placeholder_never_fabricates_business_data(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'client_logos', 'id' => 'x1', 'props' => ['title' => 'مشتریان']]);

        $this->assertStringContainsString('مشتریان', $html);
        $this->assertStringNotContainsString('برند اول', $html); // the registry's own default sample names must never leak into a real render
        foreach (['Carrier', 'Trane', 'Lennox', 'Daikin'] as $fabricatedBrand) {
            $this->assertStringNotContainsString($fabricatedBrand, $html);
        }
    }

    public function test_client_logos_renders_real_logos_when_authored(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'client_logos', 'id' => 'x1', 'props' => [
            'title' => 'مشتریان',
            'logos' => [['name' => 'شرکت الف'], ['name' => 'شرکت ب']],
        ]]);

        $this->assertStringContainsString('شرکت الف', $html);
        $this->assertStringContainsString('شرکت ب', $html);
        $this->assertStringNotContainsString('پس از افزودن', $html); // placeholder hint must not appear once real data exists
    }

    // ── 6. nested empty output is detected ─────────────────────────────────

    public function test_empty_output_nested_inside_a_container_is_detected(): void
    {
        $theme = $this->makeGoodTheme(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['type' => 'section', 'props' => [], 'children' => [
                ['type' => 'client_logos', 'props' => ['title' => 'مشتریان']], // no logos — but placeholder-backed, so this specific one won't actually be empty
            ]],
        ]]]]);

        // Use a renderer whose client_logos is broken (returns empty) to
        // prove the *nested* occurrence is caught, not just top-level ones.
        $brokenRenderer = new class extends BlockRenderer {
            public function renderClientLogos(array $props, string $inner, string $id): string
            {
                return '';
            }
        };

        $gate = new ThemeCommercialQualityGate([new RenderIntegrityCheck($brokenRenderer)]);
        $report = $gate->evaluate($theme, '1.0.0');

        $this->assertFalse($report->isPassing());

        // The nested finding's location must reflect the section it is
        // inside — proving checkBlockList()'s recursion (not just its
        // top-level scan) caught it.
        $allEmpty = array_filter($report->checks, fn (array $c) => $c['code'] === 'render.empty_output');
        $found = false;
        foreach ($allEmpty as $finding) {
            if (($finding['details']['type'] ?? '') === 'client_logos' && str_contains($finding['details']['location'] ?? '', 'section')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'expected the nested client_logos empty-output finding to report a location inside the section container');
    }

    // ── 7/8. canonical source reconstruction preserves the fix, idempotently ──

    public function test_canonical_reconstruction_preserves_the_client_logos_fix_and_is_idempotent(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        $firstPassCounts = [];
        foreach (['portfolio-khalaq', 'azhans-digital', 'sanat-molodi', 'enterprise-cloud'] as $slug) {
            $theme = Theme::where('slug', $slug)->firstOrFail();
            $report = $gate->evaluate($theme, '1.0.0');
            $firstPassCounts[$slug] = count(array_filter($report->checks, fn (array $c) => $c['code'] === 'render.empty_output'));
        }
        $this->assertSame([0, 0, 0, 0], array_values($firstPassCounts));

        // Simulate a second, independent fresh database.
        Theme::query()->delete();
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        foreach (['portfolio-khalaq', 'azhans-digital', 'sanat-molodi', 'enterprise-cloud'] as $slug) {
            $theme = Theme::where('slug', $slug)->firstOrFail();
            $report = $gate->evaluate($theme, '1.0.0');
            $emptyCount = count(array_filter($report->checks, fn (array $c) => $c['code'] === 'render.empty_output'));
            $this->assertSame(0, $emptyCount, "{$slug} regressed after a second rebuild");
        }
    }

    // ── 9. unsupported canonical block count remains zero ─────────────────

    public function test_unsupported_block_count_remains_zero_after_this_phases_changes(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $gate = $this->app->make(ThemeCommercialQualityGate::class);

        foreach (Theme::whereNull('site_id')->get() as $theme) {
            $report = $gate->evaluate($theme, '1.0.0');
            $unsupported = array_filter($report->checks, fn (array $c) => $c['code'] === 'render.unsupported_block_type');
            $this->assertSame([], array_values($unsupported), "{$theme->slug} regressed with an unsupported block type");
        }
    }

    // ── 11. 29/29 real catalog passes the full gate ────────────────────────
    // 29 since ariana-multiplus joined the canonical catalog (9010288c).

    public function test_all_canonical_themes_pass_the_full_quality_gate(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $this->artisan('theme-release:backfill')->assertExitCode(0);
        $this->artisan('theme:quality-gate')->assertExitCode(0);

        $themes = Theme::whereNull('site_id')->get();
        $this->assertSame(29, $themes->count());
        foreach ($themes as $theme) {
            $report = ThemeQualityReport::where('theme_id', $theme->id)->latest('evaluated_at')->first();
            $this->assertNotNull($report, "{$theme->slug} has no quality report");
            $this->assertTrue($report->isPassing(), "{$theme->slug} failed the quality gate: " . json_encode($report->blockingFailures()));
        }
    }

    // ── 16. no customer data touched ───────────────────────────────────────

    public function test_no_customer_data_is_touched_by_evaluating_empty_output(): void
    {
        $theme = $this->makeGoodTheme(['pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
            ['type' => 'client_logos', 'props' => ['title' => 'مشتریان']],
        ]]]]);

        $gate = $this->app->make(ThemeCommercialQualityGate::class);
        $gate->evaluate($theme, '1.0.0');

        $this->assertSame(0, \App\Models\Site::count());
        $this->assertSame(0, \App\Models\ThemeLicense::count());
    }
}
