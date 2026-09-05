<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 3G-B-5 — the actual commercial publish integration: proves a Theme
 * using ext:* content cannot become commercially publishable unless its
 * Extension dependencies are structurally discovered, unambiguously owned,
 * backed by an explicit author-selected release, represented with a
 * truthful exact-version constraint, persisted into the immutable
 * ThemeReleaseDependency ledger, and verified fail-closed by the real
 * ThemeCommercialQualityGate — through the exact same production entry
 * point (publishIfCompliant → ThemeReleaseService::release()), not a
 * reimplementation of either.
 */
class ThemeExtensionDependencyReleaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ThemeReleaseService $releaseService;
    private ThemeCommercialQualityGate $gate;
    private ExtensionReleaseService $extensionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
        $this->gate = $this->app->make(ThemeCommercialQualityGate::class);
        $this->extensionService = $this->app->make(ExtensionReleaseService::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست',
            'slug' => 'ext-release-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب برای تست.',
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    private function publishExtension(string $vendor, string $package, string $version, array $elementTypes): Extension
    {
        $extension = $this->extensionService->createExtension($vendor, $package, ucfirst($package));
        $this->extensionService->release($extension, $version, 1, [
            'elements' => array_map(fn ($t) => ['type' => $t, 'label' => 'L', 'category' => 'general'], $elementTypes),
        ]);

        return $extension->fresh();
    }

    private function selection(string $productIdentity, string $version): array
    {
        return [$productIdentity => ['version' => $version]];
    }

    // ── release extraction (checklist §29) ──────────────────────────────────

    public function test_theme_without_ext_blocks_releases_unchanged(): void
    {
        $theme = $this->makeTheme();

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertSame(0, $release->structuredDependencies()->count());
        $this->assertNull($release->dependencies);
    }

    public function test_valid_ext_usage_and_valid_selection_produces_extension_dependency_row(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '2.3.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'g1']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '2.3.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $rows = $release->structuredDependencies;
        $this->assertCount(1, $rows);
        $this->assertSame(ThemeReleaseDependency::TYPE_EXTENSION, $rows[0]->dependency_type);
        $this->assertSame('extension', $rows[0]->dependency_type);
        $this->assertSame('acme/gallery-pro', $rows[0]->dependency_identifier);
        $this->assertSame('=2.3.0', $rows[0]->version_constraint);
        $this->assertTrue($rows[0]->is_required);
    }

    public function test_repeated_blocks_same_extension_dedupe_to_one_row(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:card', 'ext:acme:grid']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [
                ['type' => 'ext:acme:card', 'id' => 'a'],
                ['type' => 'ext:acme:grid', 'id' => 'b'],
                ['type' => 'ext:acme:card', 'id' => 'c'],
            ]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertCount(1, $release->structuredDependencies);
    }

    public function test_multiple_extensions_produce_independent_rows(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '2.3.0', ['ext:acme:gallery']);
        $this->publishExtension('foo', 'maps', '1.4.2', ['ext:foo:map']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [
                ['type' => 'ext:acme:gallery', 'id' => 'a'],
                ['type' => 'ext:foo:map', 'id' => 'b'],
            ]]],
            'settings' => ['extension_selections' => array_merge(
                $this->selection('acme/gallery-pro', '2.3.0'),
                $this->selection('foo/maps', '1.4.2'),
            )],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $identifiers = $release->structuredDependencies->pluck('dependency_identifier')->sort()->values()->all();
        $this->assertSame(['acme/gallery-pro', 'foo/maps'], $identifiers);
    }

    public function test_header_usage_is_extracted(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:widget']);
        $theme = $this->makeTheme([
            'header_blocks' => [['type' => 'ext:acme:widget', 'id' => 'h1']],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertCount(1, $release->structuredDependencies);
    }

    public function test_footer_usage_is_extracted(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:widget']);
        $theme = $this->makeTheme([
            'footer_blocks' => [['type' => 'ext:acme:widget', 'id' => 'f1']],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertCount(1, $release->structuredDependencies);
    }

    public function test_deep_nested_usage_is_extracted(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:deep']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [
                ['type' => 'section', 'id' => 's1', 'children' => [
                    ['type' => 'section', 'id' => 's2', 'children' => [
                        ['type' => 'ext:acme:deep', 'id' => 'd1'],
                    ]],
                ]],
            ]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertCount(1, $release->structuredDependencies);
    }

    public function test_missing_selection_blocks_release(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
        ]);

        $this->expectException(ThemeQualityGateFailedException::class);
        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        } finally {
            $this->assertSame(0, ThemeRelease::count());
        }
    }

    public function test_invalid_selection_blocks_release(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '9.9.9')],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
            $this->fail('expected ThemeQualityGateFailedException');
        } catch (ThemeQualityGateFailedException $e) {
            $this->assertFalse($e->report->isPassing());
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_unresolved_element_blocks_release(): void
    {
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:nobody:owns_this', 'id' => 'a']]]],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
            $this->fail('expected ThemeQualityGateFailedException');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_ambiguous_ownership_blocks_release(): void
    {
        $one = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $two = $this->extensionService->createExtension('acme', 'widgets-plus', 'Widgets Plus');
        foreach ([$one, $two] as $extension) {
            DB::table('extension_releases')->insert([
                'extension_id' => $extension->id,
                'version' => '1.0.0',
                'sdk_contract_version' => 1,
                'manifest' => json_encode([
                    'product_identity' => $extension->identifier(),
                    'package_version' => '1.0.0',
                    'sdk_contract_version' => 1,
                    'elements' => [['type' => 'ext:acme:widget', 'label' => 'W', 'category' => 'general']],
                ]),
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:widget', 'id' => 'a']]]],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
            $this->fail('expected ThemeQualityGateFailedException');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_selected_release_missing_used_element_blocks_release(): void
    {
        $extension = $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $this->extensionService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:other', 'label' => 'L', 'category' => 'general']],
        ]);

        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '2.0.0')],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
            $this->fail('expected ThemeQualityGateFailedException');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_unused_selection_does_not_create_a_dependency(): void
    {
        $this->publishExtension('bar', 'unused-thing', '1.0.0', ['ext:bar:thing']);
        $theme = $this->makeTheme([
            'settings' => ['extension_selections' => $this->selection('bar/unused-thing', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->assertSame(0, $release->structuredDependencies()->count());
    }

    public function test_existing_non_extension_dependencies_are_preserved(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);

        $types = $release->structuredDependencies->pluck('dependency_type')->sort()->values()->all();
        $this->assertSame(['extension', 'module'], $types);
    }

    // ── atomicity (checklist §30) ────────────────────────────────────────────

    public function test_failed_extraction_leaves_no_partial_state(): void
    {
        $theme = $this->makeTheme();
        $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $theme->refresh();
        $releaseIdBefore = $theme->current_release_id;
        $releaseCountBefore = ThemeRelease::count();
        $dependencyCountBefore = ThemeReleaseDependency::count();

        // Now make it fail: unresolved ext usage on the next attempt.
        $theme->update(['pages' => ['home' => ['blocks' => [['type' => 'ext:nobody:owns_this', 'id' => 'a']]]]]);

        try {
            $this->gate->publishIfCompliant($theme->fresh(), $this->releaseService, '1.1.0', 'v1.1.');
            $this->fail('expected ThemeQualityGateFailedException');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $theme->refresh();
        $this->assertSame($releaseIdBefore, $theme->current_release_id);
        $this->assertSame($releaseCountBefore, ThemeRelease::count());
        $this->assertSame($dependencyCountBefore, ThemeReleaseDependency::count());
    }

    public function test_release_write_failure_leaves_no_partial_release_or_dependency_rows(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        // Force a downstream failure inside the same release() transaction
        // via a version that collides with itself (duplicate release check)
        // by publishing twice with the same version through the low-level
        // writer directly, proving no partial rows survive a rolled-back
        // attempt.
        $this->releaseService->release($theme, '1.0.0', 'first');

        $countBefore = ThemeReleaseDependency::count();
        $releaseCountBefore = ThemeRelease::count();

        try {
            $this->releaseService->release($theme->fresh(), '1.0.0', 'duplicate version, must fail');
            $this->fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame($releaseCountBefore, ThemeRelease::count());
        $this->assertSame($countBefore, ThemeReleaseDependency::count());
    }

    // ── immutability (checklist §31) ─────────────────────────────────────────

    public function test_historical_dependency_survives_settings_selection_change(): void
    {
        $extension = $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $originalConstraint = $release->structuredDependencies->first()->version_constraint;

        // Author changes the draft selection after release.
        $this->extensionService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        $theme->fresh()->update(['settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '2.0.0')]]);

        $release->refresh();
        $this->assertSame($originalConstraint, $release->structuredDependencies->first()->version_constraint);
        $this->assertSame('=1.0.0', $release->structuredDependencies->first()->version_constraint);
    }

    public function test_historical_dependency_survives_theme_content_change(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $rowCountBefore = $release->structuredDependencies()->count();

        $theme->fresh()->update(['pages' => ['home' => ['blocks' => []]]]);

        $release->refresh();
        $this->assertSame($rowCountBefore, $release->structuredDependencies()->count());
    }

    public function test_historical_dependency_survives_current_release_id_change(): void
    {
        $extension = $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        $this->extensionService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        $extension->refresh();
        $this->assertSame('2.0.0', $extension->currentRelease->version);

        $release->refresh();
        $this->assertSame('=1.0.0', $release->structuredDependencies->first()->version_constraint);
    }

    public function test_historical_dependency_survives_newer_extension_release_creation(): void
    {
        $extension = $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        $rowBefore = $release->structuredDependencies->first()->toArray();

        $this->extensionService->release($extension->fresh(), '3.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);

        $release->refresh();
        $rowAfter = $release->structuredDependencies->first()->toArray();
        $this->assertSame($rowBefore['version_constraint'], $rowAfter['version_constraint']);
        $this->assertSame($rowBefore['dependency_identifier'], $rowAfter['dependency_identifier']);
    }

    // ── manual/derived collision (checklist §33) ─────────────────────────────

    public function test_identical_manual_and_derived_extension_dependency_dedupes(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'extension', 'ref' => 'acme/gallery-pro', 'version_constraint' => '=1.0.0', 'is_required' => true],
        ]);

        $this->assertCount(1, $release->structuredDependencies);
    }

    public function test_conflicting_manual_constraint_fails_closed(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
                ['type' => 'extension', 'ref' => 'acme/gallery-pro', 'version_constraint' => '=9.9.9', 'is_required' => true],
            ]);
            $this->fail('expected a conflict failure');
        } catch (ThemeQualityGateFailedException $e) {
            // expected — surfaced as a failed quality report
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_conflicting_required_flag_fails_closed(): void
    {
        $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
                ['type' => 'extension', 'ref' => 'acme/gallery-pro', 'version_constraint' => '=1.0.0', 'is_required' => false],
            ]);
            $this->fail('expected a conflict failure');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }
        $this->assertSame(0, ThemeRelease::count());
    }

    public function test_unrelated_manual_dependency_types_are_untouched_by_merge(): void
    {
        $theme = $this->makeTheme();

        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.', dependencies: [
            ['type' => 'motion_asset', 'ref' => 'hero-intro', 'is_required' => false],
        ]);

        $this->assertCount(1, $release->structuredDependencies);
        $this->assertSame('motion_asset', $release->structuredDependencies->first()->dependency_type);
    }

    // ── gate consumes immutable ledger, not mutable Theme (checklist §20) ───

    public function test_relink_evaluation_uses_persisted_dependency_row_not_live_theme_selection(): void
    {
        $extension = $this->publishExtension('acme', 'gallery-pro', '1.0.0', ['ext:acme:gallery']);
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:gallery', 'id' => 'a']]]],
            'settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '1.0.0')],
        ]);
        $release = $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');

        // Live Theme now points at a NONEXISTENT selection — if the gate's
        // re-link path re-extracted from mutable Theme state, this would
        // fail. It must not: it verifies the persisted ledger row instead.
        $theme->fresh()->update(['settings' => ['extension_selections' => $this->selection('acme/gallery-pro', '9.9.9')]]);

        $report = $this->gate->linkExistingReleaseIfCompliant($theme->fresh(), $release);

        $this->assertTrue($report->isPassing());
    }

    public function test_theme_is_commercially_ineligible_while_a_required_extension_dependency_is_unresolved(): void
    {
        $theme = $this->makeTheme([
            'pages' => ['home' => ['blocks' => [['type' => 'ext:nobody:owns_this', 'id' => 'a']]]],
        ]);

        try {
            $this->gate->publishIfCompliant($theme, $this->releaseService, '1.0.0', 'v1.');
        } catch (ThemeQualityGateFailedException $e) {
            // expected
        }

        $this->assertFalse($theme->fresh()->isCommerciallyEligible());
    }
}
