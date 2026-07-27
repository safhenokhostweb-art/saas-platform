<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;

/**
 * Phase 17-G — Part 8: module:validate scenarios. Uses module:create to
 * produce real fixtures on disk (proving the generator and validator
 * agree), then mutates module.json in place to exercise failure paths.
 */
class ModuleValidateCommandTest extends TestCase
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
        $slug = 'test-val-' . substr(uniqid('', true), -8);
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

    private function manifestPath(string $slug): string
    {
        return base_path("packages/saas-platform/modules/{$slug}/module.json");
    }

    // ─── Scenario 3 — validation success ───────────────────────────────────

    public function test_freshly_generated_module_validates_successfully(): void
    {
        $slug = $this->makeFixtureModule();

        $this->artisan('module:validate', ['slug' => $slug])
            ->assertExitCode(0)
            ->expectsOutputToContain("SUCCESS: Module {$slug} is valid.");
    }

    public function test_real_forms_module_validates_successfully(): void
    {
        $this->artisan('module:validate', ['slug' => 'forms'])
            ->assertExitCode(0)
            ->expectsOutputToContain('SUCCESS: Module forms is valid.');
    }

    // ─── Scenario 4 — validation failure ───────────────────────────────────

    public function test_manifest_missing_required_field_fails_validation(): void
    {
        $slug = $this->makeFixtureModule();
        $manifest = json_decode(file_get_contents($this->manifestPath($slug)), true);
        unset($manifest['module_slug']);
        file_put_contents($this->manifestPath($slug), json_encode($manifest));

        $this->artisan('module:validate', ['slug' => $slug])
            ->assertExitCode(1)
            ->expectsOutputToContain('module_slug');
    }

    public function test_invalid_semver_fails_validation(): void
    {
        $slug = $this->makeFixtureModule();
        $manifest = json_decode(file_get_contents($this->manifestPath($slug)), true);
        $manifest['version'] = 'not-a-version';
        file_put_contents($this->manifestPath($slug), json_encode($manifest));

        $this->artisan('module:validate', ['slug' => $slug])->assertExitCode(1);
    }

    public function test_nonexistent_module_fails(): void
    {
        $this->artisan('module:validate', ['slug' => 'this-module-does-not-exist-anywhere'])
            ->assertExitCode(1);
    }

    public function test_malformed_json_fails(): void
    {
        $slug = $this->makeFixtureModule();
        file_put_contents($this->manifestPath($slug), '{ not valid json');

        $this->artisan('module:validate', ['slug' => $slug])->assertExitCode(1);
    }
}
