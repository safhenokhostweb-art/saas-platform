<?php

namespace Tests\Unit\Console\Commands;

use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use Tests\TestCase;

/**
 * Phase 17-G — Part 8: module:create scenarios. Writes real files under
 * packages/saas-platform/modules/{slug}/ — every test slug is uniquely
 * generated and removed in tearDown so nothing leaks into the repo.
 */
class ModuleCreateCommandTest extends TestCase
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

    private function slug(): string
    {
        return 'test-gen-' . substr(uniqid('', true), -8);
    }

    private function path(string $slug): string
    {
        $path = base_path("packages/saas-platform/modules/{$slug}");
        $this->createdPaths[] = $path;
        return $path;
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

    // ─── Scenario 1 — create a new module ──────────────────────────────────

    public function test_create_scaffolds_full_directory_structure(): void
    {
        $slug = $this->slug();
        $path = $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);

        $this->assertDirectoryExists("{$path}/src/Models");
        $this->assertDirectoryExists("{$path}/src/Services");
        $this->assertDirectoryExists("{$path}/src/Contracts");
        $this->assertDirectoryExists("{$path}/src/Providers");
        $this->assertDirectoryExists("{$path}/database/migrations");
        $this->assertDirectoryExists("{$path}/routes");
        $this->assertDirectoryExists("{$path}/resources/views");
        $this->assertDirectoryExists("{$path}/resources/lang");
        $this->assertDirectoryExists("{$path}/tests");
        $this->assertFileExists("{$path}/module.json");
        $this->assertFileExists("{$path}/composer.json");
        $this->assertFileExists("{$path}/README.md");
        $this->assertFileExists("{$path}/routes/web.php");
    }

    // ─── Scenario 2 — module.json content ──────────────────────────────────

    public function test_create_generates_manifest_that_passes_official_schema(): void
    {
        $slug = $this->slug();
        $path = $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug, '--name' => 'Test Generated Module'])->assertExitCode(0);

        $manifest = json_decode(file_get_contents("{$path}/module.json"), true);

        $this->assertSame($slug, $manifest['module_slug']);
        $this->assertSame('Test Generated Module', $manifest['name']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame('paid', $manifest['type']); // default per --type
        $this->assertArrayHasKey('uninstall', $manifest);
        $this->assertSame('soft', $manifest['uninstall']['type']);

        $result = (new ModuleManifestSchema())->validate($manifest);
        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
        $this->assertEmpty($result['warnings'], implode('; ', $result['warnings']));
    }

    public function test_create_generates_service_provider_with_boot_and_register(): void
    {
        $slug = $this->slug();
        $path = $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);

        $manifest = json_decode(file_get_contents("{$path}/module.json"), true);
        $providerParts = explode('\\', $manifest['provider']);
        $providerFile = "{$path}/src/Providers/" . end($providerParts) . '.php';

        $this->assertFileExists($providerFile);
        $content = file_get_contents($providerFile);
        $this->assertStringContainsString('function boot', $content);
        $this->assertStringContainsString('function register', $content);
    }

    public function test_create_honors_custom_type_option(): void
    {
        $slug = $this->slug();
        $path = $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug, '--type' => 'core'])->assertExitCode(0);

        $manifest = json_decode(file_get_contents("{$path}/module.json"), true);
        $this->assertSame('core', $manifest['type']);
    }

    // ─── Guards ─────────────────────────────────────────────────────────────

    public function test_create_rejects_invalid_slug(): void
    {
        $this->artisan('module:create', ['slug' => 'Invalid Slug!'])->assertExitCode(1);
    }

    public function test_create_rejects_invalid_type(): void
    {
        $slug = $this->slug();
        $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug, '--type' => 'nonsense'])->assertExitCode(1);
    }

    public function test_create_refuses_to_overwrite_without_force(): void
    {
        $slug = $this->slug();
        $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);
        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(1);
    }

    public function test_create_overwrites_with_force(): void
    {
        $slug = $this->slug();
        $this->path($slug);

        $this->artisan('module:create', ['slug' => $slug])->assertExitCode(0);
        $this->artisan('module:create', ['slug' => $slug, '--force' => true])->assertExitCode(0);
    }
}
