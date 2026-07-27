<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;

/**
 * Phase 17-G — Part 8: module:build scenarios. This is the pre-existing
 * Phase 6.98 structural linter (App\Console\Commands namespace lives in
 * core-utils, not this one) — Part 5 harmonized its required-field check
 * to delegate to ModuleManifestSchema instead of a second hardcoded list.
 * No test previously existed for this command at all; this both adds
 * that missing coverage and proves the harmonization didn't regress it.
 */
class ModuleBuildCommandTest extends TestCase
{
    /** @var string[] */
    private array $createdPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            $this->removeDirectory($path);
        }
        parent::tearDown();
    }

    private function makeFixtureModule(): string
    {
        $slug = 'test-bld-' . substr(uniqid('', true), -8);
        $path = base_path("packages/saas-platform/modules/{$slug}");
        $this->createdPaths[] = $path;

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);

        return $slug;
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

    // ─── Scenario 5 — build (structural) succeeds ──────────────────────────

    public function test_real_forms_module_passes(): void
    {
        $this->artisan('module:build', ['module' => 'forms'])->assertExitCode(0);
    }

    public function test_freshly_generated_module_passes(): void
    {
        $slug = $this->makeFixtureModule();

        $this->artisan('module:build', ['module' => $slug])->assertExitCode(0);
    }

    // ─── Failure path ───────────────────────────────────────────────────────

    public function test_module_missing_service_provider_fails(): void
    {
        $slug = $this->makeFixtureModule();
        $path = base_path("packages/saas-platform/modules/{$slug}");
        $manifest = json_decode(file_get_contents("{$path}/module.json"), true);
        $providerParts = explode('\\', $manifest['provider']);
        unlink("{$path}/src/Providers/" . end($providerParts) . '.php');

        $this->artisan('module:build', ['module' => $slug])->assertExitCode(1);
    }

    public function test_nonexistent_module_fails(): void
    {
        $this->artisan('module:build', ['module' => 'this-module-does-not-exist-anywhere'])
            ->assertExitCode(1);
    }
}
