<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1e — dependency extraction backfill for existing (Phase 1d)
 * releases. Proves the command only extracts what the stored data actually
 * proves, is idempotent, and never touches customer instances.
 */
class ThemeReleaseDependencyBackfillCommandTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $releaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->releaseService = $this->app->make(ThemeReleaseService::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'depbackfill-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    public function test_extracts_supported_modules_and_motion_assets_from_the_release_snapshot(): void
    {
        $theme = $this->makeTheme([
            'settings' => [
                'supported_modules' => ['forms', 'tickets'],
                'motion' => ['required_assets' => [['asset_id' => 'medical-hero-intro']], 'package_reference' => 'theme:clinic-premium'],
            ],
        ]);
        ThemeMarketplace::create([
            'theme_id' => $theme->id,
            'requirements' => ['forms' => 'نوبت‌دهی آنلاین'],
        ]);
        $release = $this->releaseService->release($theme, '1.0.0', 'Initial commercial release.');

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);

        $dependencies = $release->fresh()->structuredDependencies()->get();
        $this->assertCount(3, $dependencies);

        $formsRow = $dependencies->firstWhere('dependency_identifier', 'forms');
        $this->assertSame('module', $formsRow->dependency_type);
        $this->assertFalse($formsRow->is_required);
        $this->assertNull($formsRow->version_constraint);
        $this->assertSame('نوبت‌دهی آنلاین', $formsRow->metadata['description']);

        $ticketsRow = $dependencies->firstWhere('dependency_identifier', 'tickets');
        $this->assertSame('module', $ticketsRow->dependency_type);
        $this->assertArrayNotHasKey('description', $ticketsRow->metadata ?? []);

        $motionRow = $dependencies->firstWhere('dependency_identifier', 'medical-hero-intro');
        $this->assertSame('motion_asset', $motionRow->dependency_type);
        $this->assertSame('theme:clinic-premium', $motionRow->metadata['package_reference']);
    }

    public function test_a_theme_with_no_extractable_data_gets_zero_rows_and_is_not_an_error(): void
    {
        $theme = $this->makeTheme(['settings' => ['responsive' => ['mobile' => ['base_font_size' => '14px']]]]);
        $this->releaseService->release($theme, '1.0.0', 'Initial commercial release.');

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);

        $this->assertCount(0, $theme->fresh()->currentRelease->structuredDependencies()->get());
    }

    public function test_dependency_backfill_is_idempotent(): void
    {
        $theme = $this->makeTheme(['settings' => ['supported_modules' => ['forms']]]);
        $release = $this->releaseService->release($theme, '1.0.0', 'Initial commercial release.');

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);
        $firstRunCount = $release->fresh()->structuredDependencies()->count();

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);
        $secondRunCount = $release->fresh()->structuredDependencies()->count();

        $this->assertSame(1, $firstRunCount);
        $this->assertSame($firstRunCount, $secondRunCount);
    }

    public function test_customer_instance_releases_are_never_touched(): void
    {
        $site = $this->makeCrmTestSite();
        $instance = $this->makeTheme([
            'slug' => 'depbackfill-instance-' . uniqid(),
            'site_id' => $site->id,
            'settings' => ['supported_modules' => ['forms']],
        ]);
        $instanceRelease = $this->releaseService->release($instance, '1.0.0', 'Initial commercial release.');

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);

        $this->assertCount(0, $instanceRelease->fresh()->structuredDependencies()->get());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $theme = $this->makeTheme(['settings' => ['supported_modules' => ['forms']]]);
        $release = $this->releaseService->release($theme, '1.0.0', 'Initial commercial release.');

        $this->artisan('theme-release:backfill-dependencies --dry-run')->assertExitCode(0);

        $this->assertCount(0, $release->fresh()->structuredDependencies()->get());
    }

    public function test_all_29_style_catalog_can_be_processed_in_one_run(): void
    {
        $releases = collect(range(1, 10))->map(function ($i) {
            $theme = $this->makeTheme([
                'slug' => "depbackfill-catalog-{$i}-" . uniqid(),
                'settings' => ['supported_modules' => ['forms', 'blog']],
            ]);
            return $this->releaseService->release($theme, '1.0.0', 'Initial commercial release.');
        });

        $this->artisan('theme-release:backfill-dependencies')->assertExitCode(0);

        foreach ($releases as $release) {
            $this->assertCount(2, $release->fresh()->structuredDependencies()->get());
        }
    }
}
