<?php

namespace Tests\Unit\Services\Modules;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17-H — Part 8: Release Status Lifecycle mechanics — the migration
 * backfill/default, and latestForModule() vs latestPublishedForModule()
 * (the two must genuinely disagree when a newer release isn't published,
 * or Part 7's fix means nothing).
 */
class ModuleReleaseStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeModule(string $slug): Module
    {
        return Module::create([
            'slug' => $slug, 'title' => $slug, 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_CORE,
        ]);
    }

    public function test_new_release_created_directly_defaults_to_published(): void
    {
        $module = $this->makeModule('status-default-' . uniqid('', true));

        $release = ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor', 'is_active' => true,
        ]);

        $this->assertSame(ModuleRelease::STATUS_PUBLISHED, $release->fresh()->status);
    }

    public function test_status_can_be_set_explicitly_to_draft(): void
    {
        $module = $this->makeModule('status-draft-' . uniqid('', true));

        $release = ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_DRAFT,
        ]);

        $this->assertSame(ModuleRelease::STATUS_DRAFT, $release->fresh()->status);
    }

    public function test_latest_for_module_ignores_status_but_latest_published_does_not(): void
    {
        $module = $this->makeModule('status-diverge-' . uniqid('', true));

        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED,
            'package_sha256' => str_repeat('a', 64),
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '2.0.0', 'release_type' => 'major',
            'is_active' => true, 'status' => ModuleRelease::STATUS_DRAFT,
            'package_sha256' => str_repeat('b', 64),
        ]);

        // Unfiltered: sees the newer draft (used by the version-monotonicity guard).
        $this->assertSame('2.0.0', ModuleRelease::latestForModule($module->id)->version);

        // Published-only: the draft doesn't count, even though it's newer.
        $this->assertSame('1.0.0', ModuleRelease::latestPublishedForModule($module->id)->version);
    }

    public function test_latest_published_for_module_excludes_deprecated(): void
    {
        $module = $this->makeModule('status-deprecated-' . uniqid('', true));

        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED,
            'package_sha256' => str_repeat('a', 64),
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '2.0.0', 'release_type' => 'major',
            'is_active' => false, 'status' => ModuleRelease::STATUS_DEPRECATED,
            'package_sha256' => str_repeat('b', 64),
        ]);

        $this->assertSame('1.0.0', ModuleRelease::latestPublishedForModule($module->id)->version);
    }

    public function test_latest_published_for_module_returns_null_when_nothing_published(): void
    {
        $module = $this->makeModule('status-none-' . uniqid('', true));

        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_DRAFT,
        ]);

        $this->assertNull(ModuleRelease::latestPublishedForModule($module->id));
    }

    public function test_get_latest_published_version_falls_back_to_modules_version(): void
    {
        $module = $this->makeModule('status-fallback-' . uniqid('', true));
        // No module_releases rows at all.

        $this->assertSame('1.0.0', ModuleRelease::getLatestPublishedVersionForModule($module));
    }
}
