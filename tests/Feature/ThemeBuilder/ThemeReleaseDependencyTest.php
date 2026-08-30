<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 1e — Structured Theme Release Dependencies.
 *
 * Proves theme_release_dependencies behaves as a real, structured,
 * immutable, open-ended dependency ledger written only through
 * ThemeReleaseService — the same sole writer that owns theme_releases.
 */
class ThemeReleaseDependencyTest extends TestCase
{
    use RefreshDatabase;
    use MakesCrmTestSites;

    private ThemeReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ThemeReleaseService::class);
    }

    private function makeTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'کلینیک پریمیوم تست',
            'slug' => 'dep-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [['type' => 'header_basic']],
            'footer_blocks' => [['type' => 'footer_basic']],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    public function test_a_module_dependency_is_stored(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'version_constraint' => '>=2.0', 'is_required' => false],
        ]);

        $this->assertDatabaseHas('theme_release_dependencies', [
            'theme_release_id' => $release->id,
            'dependency_type' => 'module',
            'dependency_identifier' => 'forms',
            'version_constraint' => '>=2.0',
            'is_required' => false,
        ]);
        $this->assertCount(1, $release->structuredDependencies()->get());
    }

    public function test_a_motion_dependency_is_stored(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'motion_asset', 'ref' => 'medical-hero-intro', 'version_constraint' => null, 'is_required' => false],
        ]);

        $this->assertDatabaseHas('theme_release_dependencies', [
            'theme_release_id' => $release->id,
            'dependency_type' => 'motion_asset',
            'dependency_identifier' => 'medical-hero-intro',
            'version_constraint' => null,
        ]);
    }

    public function test_multiple_dependency_types_are_supported_in_one_release(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
            ['type' => 'motion_pack', 'ref' => 'medical-motion', 'version_constraint' => '^2.0', 'is_required' => false],
            ['type' => 'icon_pack', 'ref' => 'medical-icons', 'version_constraint' => '>=1.5 <3.0', 'is_required' => true],
            ['type' => 'design_system', 'ref' => 'future-product-class', 'is_required' => false],
        ]);

        $this->assertCount(4, $release->structuredDependencies()->get());
        $this->assertEqualsCanonicalizing(
            ['module', 'motion_pack', 'icon_pack', 'design_system'],
            $release->structuredDependencies()->pluck('dependency_type')->all()
        );
    }

    public function test_a_duplicate_dependency_in_the_same_release_is_rejected(): void
    {
        $theme = $this->makeTheme();

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
            ['type' => 'module', 'ref' => 'forms', 'is_required' => true],
        ]);
    }

    public function test_attaching_a_duplicate_dependency_to_an_existing_release_is_rejected(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->attachDependencies($release, [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);
    }

    public function test_an_invalid_version_constraint_is_rejected(): void
    {
        $theme = $this->makeTheme();

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'version_constraint' => 'whatever-2.0', 'is_required' => false],
        ]);
    }

    public function test_compound_and_caret_version_constraints_are_accepted(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'motion_pack', 'ref' => 'medical-motion', 'version_constraint' => '^2.0', 'is_required' => false],
            ['type' => 'icon_pack', 'ref' => 'medical-icons', 'version_constraint' => '>=1.5 <3.0', 'is_required' => false],
        ]);

        $this->assertSame('^2.0', $release->structuredDependencies()->where('dependency_identifier', 'medical-motion')->first()->version_constraint);
        $this->assertSame('>=1.5 <3.0', $release->structuredDependencies()->where('dependency_identifier', 'medical-icons')->first()->version_constraint);
    }

    public function test_a_null_constraint_is_allowed_and_means_unconstrained(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'version_constraint' => null, 'is_required' => false],
        ]);

        $this->assertNull($release->structuredDependencies()->first()->version_constraint);
    }

    public function test_a_dependency_row_on_a_released_version_cannot_be_modified(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);
        $dependency = $release->structuredDependencies()->first();

        $this->expectException(RuntimeException::class);
        $dependency->update(['is_required' => true]);
    }

    public function test_a_dependency_row_cannot_be_deleted(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);
        $dependency = $release->structuredDependencies()->first();

        $this->expectException(RuntimeException::class);
        $dependency->delete();
    }

    public function test_a_second_release_has_an_independent_dependency_set(): void
    {
        $theme = $this->makeTheme();
        $first = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);

        $second = $this->service->release($theme->fresh(), '1.1.0', 'v2.', dependencies: [
            ['type' => 'module', 'ref' => 'tickets', 'is_required' => false],
            ['type' => 'motion_asset', 'ref' => 'medical-hero-intro', 'is_required' => false],
        ]);

        $this->assertCount(1, $first->structuredDependencies()->get());
        $this->assertCount(2, $second->structuredDependencies()->get());
        $this->assertSame('forms', $first->structuredDependencies()->first()->dependency_identifier);
    }

    public function test_release_immutability_is_unaffected_by_dependency_writes(): void
    {
        $theme = $this->makeTheme();
        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);

        $this->expectException(RuntimeException::class);
        $release->update(['changelog' => 'tampered']);
    }

    public function test_customer_instance_dependency_data_is_independent_of_the_master(): void
    {
        $master = $this->makeTheme(['slug' => 'dep-master-' . uniqid()]);
        $site = $this->makeCrmTestSite();
        $instance = $this->makeTheme(['slug' => 'dep-instance-' . uniqid(), 'site_id' => $site->id]);

        $masterRelease = $this->service->release($master, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false],
        ]);
        $instanceRelease = $this->service->release($instance, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'shop', 'is_required' => false],
        ]);

        $this->assertSame('forms', $masterRelease->structuredDependencies()->first()->dependency_identifier);
        $this->assertSame('shop', $instanceRelease->structuredDependencies()->first()->dependency_identifier);
    }

    public function test_metadata_is_stored_when_provided_and_never_fabricated_when_absent(): void
    {
        $theme = $this->makeTheme();

        $release = $this->service->release($theme, '1.0.0', 'v1.', dependencies: [
            ['type' => 'module', 'ref' => 'forms', 'is_required' => false, 'metadata' => ['source' => 'settings.supported_modules', 'description' => 'فرم تماس']],
            ['type' => 'module', 'ref' => 'shop', 'is_required' => false],
        ]);

        $withMeta = $release->structuredDependencies()->where('dependency_identifier', 'forms')->first();
        $withoutMeta = $release->structuredDependencies()->where('dependency_identifier', 'shop')->first();

        $this->assertEquals(['source' => 'settings.supported_modules', 'description' => 'فرم تماس'], $withMeta->metadata);
        $this->assertNull($withoutMeta->metadata);
    }
}
