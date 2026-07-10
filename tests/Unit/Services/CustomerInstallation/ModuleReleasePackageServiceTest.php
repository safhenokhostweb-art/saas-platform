<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Services\CustomerInstallation\ModuleReleasePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 13-H — ModuleReleasePackageService (CustomerPackageInterface
 * adapter) scenarios. Not bound into any pipeline in this commit — these
 * tests exercise the class directly, matching the "independently
 * testable before integration" plan from Phase 13-F.
 */
class ModuleReleasePackageServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function makeModule(): Module
    {
        return Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE]
        );
    }

    private function makeFile(string $content = 'zip-bytes'): string
    {
        $path = sys_get_temp_dir() . '/mrps_test_' . uniqid() . '.zip';
        file_put_contents($path, $content);
        return $path;
    }

    public function test_get_manifest_returns_registered_artifact(): void
    {
        $module = $this->makeModule();
        $file = $this->makeFile();
        $sha256 = hash_file('sha256', $file);

        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '2.0.0',
            'release_type' => 'major',
            'is_active' => true,
            'package_sha256' => $sha256,
            'package_path' => $file,
            'package_size_bytes' => filesize($file),
        ]);

        $service = new ModuleReleasePackageService(self::SLUG);
        $manifest = $service->getManifest('2.0.0');

        $this->assertSame('2.0.0', $manifest->version);
        $this->assertSame($sha256, $manifest->sha256);
        $this->assertSame(filesize($file), $manifest->sizeBytes);

        @unlink($file);
    }

    public function test_get_manifest_fails_without_registered_package(): void
    {
        $module = $this->makeModule();
        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '2.0.0',
            'release_type' => 'major',
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no registered package/');

        (new ModuleReleasePackageService(self::SLUG))->getManifest('2.0.0');
    }

    public function test_latest_version_ignores_releases_without_a_package(): void
    {
        $module = $this->makeModule();
        $file = $this->makeFile();

        // Newer version, but never packaged — must be ignored.
        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '3.0.0',
            'release_type' => 'major',
            'is_active' => true,
        ]);

        // Older version, packaged — this is the one latestVersion() should return.
        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '2.0.0',
            'release_type' => 'major',
            'is_active' => true,
            'package_sha256' => hash_file('sha256', $file),
            'package_path' => $file,
            'package_size_bytes' => filesize($file),
        ]);

        $latest = (new ModuleReleasePackageService(self::SLUG))->latestVersion();

        $this->assertSame('2.0.0', $latest);

        @unlink($file);
    }

    public function test_download_locally_copies_without_touching_registered_artifact(): void
    {
        $module = $this->makeModule();
        $file = $this->makeFile('original-content');

        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '2.0.0',
            'release_type' => 'major',
            'is_active' => true,
            'package_sha256' => hash_file('sha256', $file),
            'package_path' => $file,
            'package_size_bytes' => filesize($file),
        ]);

        $service = new ModuleReleasePackageService(self::SLUG);
        $copy = $service->downloadLocally('2.0.0');

        $this->assertFileExists($copy);
        $this->assertNotSame($file, $copy);
        $this->assertSame('original-content', file_get_contents($copy));

        $service->cleanup($copy);
        $this->assertFileDoesNotExist($copy);
        $this->assertFileExists($file);

        @unlink($file);
    }

    public function test_verify_checks_sha256(): void
    {
        $file = $this->makeFile('some-bytes');
        $service = new ModuleReleasePackageService(self::SLUG);

        $this->assertTrue($service->verify($file, hash_file('sha256', $file)));
        $this->assertFalse($service->verify($file, str_repeat('0', 64)));

        @unlink($file);
    }
}
