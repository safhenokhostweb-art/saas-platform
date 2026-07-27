<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17-H — Part 8: module:publish scenarios (the Publishing Contract,
 * Part 3). Uses module:create + module:release (Phase 17-G) to produce a
 * real, throwaway fixture module — proving the whole create → release →
 * publish chain works end-to-end, not just this command in isolation.
 */
class ModuleReleasePublishingTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $createdPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            $this->removeDirectory($path);
        }
        $manifests = glob(storage_path('app/modules/releases/test-pub-*.json')) ?: [];
        foreach ($manifests as $file) {
            @unlink($file);
        }
        $packages = glob(storage_path('app/modules/packages/test-pub-*.zip')) ?: [];
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
     * @return array{slug: string, module: Module, path: string}
     */
    private function makeFixtureModule(): array
    {
        $slug = 'test-pub-' . substr(uniqid('', true), -8);
        $path = base_path("packages/saas-platform/modules/{$slug}");
        $this->createdPaths[] = $path;

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);

        $module = Module::create([
            'slug' => $slug, 'title' => $slug, 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_PAID,
        ]);

        return compact('slug', 'module', 'path');
    }

    // ─── Scenario 1 — publish succeeds ─────────────────────────────────────

    public function test_publish_succeeds_for_a_fully_released_module(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule();

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(0);

        $this->artisan('module:publish', ['slug' => $slug, 'version' => '1.0.0'])
            ->assertExitCode(0)
            ->expectsOutputToContain('SUCCESS');

        $release = ModuleRelease::where('module_id', $module->id)->where('version', '1.0.0')->first();
        $this->assertSame(ModuleRelease::STATUS_PUBLISHED, $release->status);
    }

    // ─── Scenario 2 — publish without a package rejected ───────────────────

    public function test_publish_without_package_is_rejected(): void
    {
        ['module' => $module, 'slug' => $slug] = $this->makeFixtureModule();

        // A release row exists, but no package was ever built for it.
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_DRAFT,
        ]);

        $this->artisan('module:publish', ['slug' => $slug, 'version' => '1.0.0'])
            ->assertExitCode(1)
            ->expectsOutputToContain('No package artifact registered');

        $release = ModuleRelease::where('module_id', $module->id)->first();
        $this->assertSame(ModuleRelease::STATUS_DRAFT, $release->status);
    }

    // ─── Scenario 3 — publish without compatibility rejected ──────────────

    public function test_publish_without_compatibility_declared_is_rejected(): void
    {
        ['slug' => $slug, 'module' => $module, 'path' => $path] = $this->makeFixtureModule();

        // Strip compatibility from module.json before releasing — this is
        // only a WARNING at build time (ModuleManifestSchema), but must
        // hard-block publishing specifically (Part 3).
        $manifest = json_decode(file_get_contents("{$path}/module.json"), true);
        unset($manifest['compatibility']);
        file_put_contents("{$path}/module.json", json_encode($manifest));

        $this->artisan('module:release', ['module' => $slug, 'version' => '1.0.0', '--dry-run' => true])
            ->assertExitCode(0);

        $this->artisan('module:publish', ['slug' => $slug, 'version' => '1.0.0'])
            ->assertExitCode(1)
            ->expectsOutputToContain('compatibility');

        $release = ModuleRelease::where('module_id', $module->id)->where('version', '1.0.0')->first();
        $this->assertNotSame(ModuleRelease::STATUS_PUBLISHED, $release->status);
    }

    // ─── Guards ─────────────────────────────────────────────────────────────

    public function test_publish_nonexistent_release_fails(): void
    {
        ['slug' => $slug] = $this->makeFixtureModule();

        $this->artisan('module:publish', ['slug' => $slug, 'version' => '9.9.9'])->assertExitCode(1);
    }

    public function test_publish_deprecated_release_is_rejected(): void
    {
        ['slug' => $slug, 'module' => $module] = $this->makeFixtureModule();
        $release = ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => false, 'status' => ModuleRelease::STATUS_DEPRECATED,
            'package_sha256' => str_repeat('a', 64), 'package_path' => '/tmp/fake.zip',
        ]);

        $this->artisan('module:publish', ['slug' => $slug, 'version' => '1.0.0'])->assertExitCode(1);

        $this->assertSame(ModuleRelease::STATUS_DEPRECATED, $release->fresh()->status);
    }
}
