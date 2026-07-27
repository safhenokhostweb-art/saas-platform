<?php

namespace Tests\Unit\Services\Modules;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Services\Modules\ModulePackageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use Tests\TestCase;
use ZipArchive;

/**
 * Phase 17-F — Part 8: ModulePackageValidator scenarios. Builds small
 * synthetic ZIP fixtures (not the real forms/tickets/education packages)
 * so every scenario — including deliberately broken ones — is isolated
 * and fast.
 */
class ModulePackageValidatorTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $tempZips = [];

    protected function tearDown(): void
    {
        foreach ($this->tempZips as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function validator(): ModulePackageValidator
    {
        return new ModulePackageValidator(new ModuleManifestSchema());
    }

    private function makeModule(string $slug = 'test-pkg', string $type = Module::TYPE_CORE): Module
    {
        return Module::create([
            'slug' => $slug, 'title' => 'Test Package', 'is_active' => true,
            'version' => '1.0.0', 'type' => $type,
        ]);
    }

    private function makeRelease(Module $module, string $version = '1.0.0'): ModuleRelease
    {
        return ModuleRelease::create([
            'module_id' => $module->id, 'version' => $version,
            'release_type' => 'patch', 'is_active' => true,
        ]);
    }

    /**
     * @param  array<string,mixed>|null  $manifest  null omits module.json entirely
     */
    private function makeZip(?array $manifest, bool $includeSrc = true): string
    {
        $dir = storage_path('app/modules/packages');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/validator-test-' . uniqid('', true) . '.zip';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        if ($manifest !== null) {
            $zip->addFromString('module.json', json_encode($manifest));
        }
        if ($includeSrc) {
            $zip->addFromString('src/Providers/TestServiceProvider.php', '<?php // test provider');
        }
        $zip->close();

        $this->tempZips[] = $path;
        return $path;
    }

    private function attachPackage(ModuleRelease $release, string $zipPath, ?string $sha256 = null): void
    {
        $release->update([
            'package_path' => $zipPath,
            'package_sha256' => $sha256 ?? hash_file('sha256', $zipPath),
            'package_size_bytes' => filesize($zipPath),
        ]);
        $release->refresh();
    }

    private function validManifestFor(Module $module, ModuleRelease $release): array
    {
        return [
            'name' => 'Test Package', 'module_slug' => $module->slug, 'version' => $release->version,
            'type' => $module->type, 'namespace' => 'Test\\Package', 'provider' => 'Test\\Package\\Provider',
            'compatibility' => ['php' => '^8.1'], 'update_type' => 'patch',
            'dependencies' => ['packages' => [], 'modules' => []],
        ];
    }

    // ─── Scenario 1 — valid package ────────────────────────────────────────

    public function test_valid_package_passes(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $zip = $this->makeZip($this->validManifestFor($module, $release));
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Scenario 3 — ZIP without module.json ──────────────────────────────

    public function test_zip_without_module_json_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $zip = $this->makeZip(null);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('module.json', implode(' ', $result['errors']));
    }

    // ─── Scenario 4 — version mismatch ─────────────────────────────────────

    public function test_version_mismatch_between_manifest_and_release_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module, '2.0.0');
        $manifest = $this->validManifestFor($module, $release);
        $manifest['version'] = '1.0.0'; // deliberately stale
        $zip = $this->makeZip($manifest);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('version', implode(' ', $result['errors']));
    }

    public function test_slug_mismatch_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $manifest = $this->validManifestFor($module, $release);
        $manifest['module_slug'] = 'a-completely-different-slug';
        $zip = $this->makeZip($manifest);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
    }

    public function test_type_mismatch_fails(): void
    {
        $module = $this->makeModule(type: Module::TYPE_CORE);
        $release = $this->makeRelease($module);
        $manifest = $this->validManifestFor($module, $release);
        $manifest['type'] = 'paid';
        $zip = $this->makeZip($manifest);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
    }

    // ─── Scenario 5 — checksum mismatch ────────────────────────────────────

    public function test_checksum_mismatch_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $zip = $this->makeZip($this->validManifestFor($module, $release));
        $this->attachPackage($release, $zip, sha256: str_repeat('f', 64));

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('package_sha256', implode(' ', $result['errors']));
    }

    // ─── Manifest-level (delegated to ModuleManifestSchema) still blocks ───

    public function test_manifest_missing_required_fields_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $zip = $this->makeZip(['module_slug' => $module->slug]); // missing name/version/type/namespace/provider
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(1, count($result['errors']));
    }

    // ─── Dependency reference check ────────────────────────────────────────

    public function test_dependency_on_nonexistent_module_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $manifest = $this->validManifestFor($module, $release);
        $manifest['dependencies']['modules'] = ['does-not-exist-anywhere'];
        $zip = $this->makeZip($manifest);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
    }

    public function test_dependency_on_existing_module_passes(): void
    {
        $dep = $this->makeModule('a-real-dependency');
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $manifest = $this->validManifestFor($module, $release);
        $manifest['dependencies']['modules'] = [$dep->slug];
        $zip = $this->makeZip($manifest);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Structure — case-insensitive, non-blocking ────────────────────────

    public function test_missing_optional_folders_never_blocks(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $zip = $this->makeZip($this->validManifestFor($module, $release), includeSrc: true);
        $this->attachPackage($release, $zip);

        $result = $this->validator()->validate($release->fresh());

        $this->assertTrue($result['valid']);
    }

    public function test_missing_package_file_fails(): void
    {
        $module = $this->makeModule();
        $release = $this->makeRelease($module);
        $release->update(['package_path' => '/tmp/does-not-exist-' . uniqid('', true) . '.zip', 'package_sha256' => str_repeat('a', 64)]);

        $result = $this->validator()->validate($release->fresh());

        $this->assertFalse($result['valid']);
    }
}
