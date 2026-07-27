<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17-G — Part 8: module:release scenarios, end-to-end through the
 * real module:create → module:build → module:release → module:package-build
 * chain (Part 6) against a throwaway fixture module. --dry-run is passed
 * throughout to skip the real `git tag` side effect (its documented
 * meaning); packaging still runs for real under --dry-run (Part 6's
 * design — only the git tag step is skipped by it).
 */
class ModuleReleaseCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $createdPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            $this->removeDirectory($path);
        }

        $manifests = glob(storage_path('app/modules/releases/test-rel-*.json')) ?: [];
        foreach ($manifests as $file) {
            @unlink($file);
        }

        $packages = glob(storage_path('app/modules/packages/test-rel-*.zip')) ?: [];
        foreach ($packages as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * @return array{slug: string, module: Module}
     */
    private function makeFixtureModule(string $type = Module::TYPE_PAID): array
    {
        $slug = 'test-rel-' . substr(uniqid('', true), -8);
        $path = base_path("packages/saas-platform/modules/{$slug}");
        $this->createdPaths[] = $path;

        $this->artisan('module:create', ['slug' => $slug, '--type' => $type])->assertExitCode(0);

        $module = Module::create([
            'slug' => $slug, 'title' => $slug, 'is_active' => true,
            'version' => '1.0.0', 'type' => $type,
        ]);

        return compact('slug', 'module');
    }

    // ─── Scenario 6 — release succeeds end-to-end ──────────────────────────

    public function test_release_creates_module_release_and_builds_package(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule();

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(0);

        $release = ModuleRelease::where('module_id', $module->id)->where('version', '1.0.0')->first();
        $this->assertNotNull($release);
        $this->assertNotEmpty($release->package_sha256);
        $this->assertFileExists($release->package_path);
        $this->assertFileExists(storage_path("app/modules/releases/{$slug}.json"));
    }

    // ─── Scenario 7 — internal module rejected ─────────────────────────────

    public function test_internal_module_release_is_rejected(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule(Module::TYPE_INTERNAL);

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(1);

        $this->assertDatabaseCount('module_releases', 0);
    }

    // ─── Scenario 8 — duplicate version rejected ───────────────────────────

    public function test_duplicate_version_release_is_rejected(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule();

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(0);

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(1);

        $this->assertSame(
            1,
            ModuleRelease::where('module_id', $module->id)->where('version', '1.0.0')->count()
        );
    }

    // ─── Version argument bumps module.json ────────────────────────────────

    public function test_explicit_version_argument_bumps_module_json(): void
    {
        ['slug' => $slug] = $this->makeFixtureModule();
        $manifestPath = base_path("packages/saas-platform/modules/{$slug}/module.json");

        $this->artisan('module:release', ['module' => $slug, 'version' => '2.5.0', '--dry-run' => true])
            ->assertExitCode(0);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('2.5.0', $manifest['version']);
    }

    public function test_release_without_version_argument_uses_module_json_version(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule();

        $this->artisan('module:release', ['module' => $slug, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('module_releases', ['module_id' => $module->id, 'version' => '1.0.0']);
    }
}
