<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallationPackage;
use App\Services\CustomerInstallation\CustomerPackageSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class CustomerPackageSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;
    private string $storagePackagesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-customer-site-' . uniqid();
        $this->storagePackagesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-storage-packages-' . uniqid();

        File::makeDirectory($this->fixtureDir, 0777, true, true);
        File::makeDirectory($this->storagePackagesDir, 0777, true, true);

        config(['customer_installation.package.local_temp_path' => $this->storagePackagesDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureDir);
        File::deleteDirectory($this->storagePackagesDir);

        parent::tearDown();
    }

    private function createValidSiteFixture(): void
    {
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'artisan', '#!/usr/bin/env php');
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'composer.json', '{"name":"customer-site"}');
        File::makeDirectory($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap', 0777, true, true);
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php', '<?php return null;');

        $themeServiceDir = $this->fixtureDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'ThemeCanvas';
        File::makeDirectory($themeServiceDir, 0777, true, true);
        File::put($themeServiceDir . DIRECTORY_SEPARATOR . 'ThemeInstanceRevisionService.php', '<?php class ThemeInstanceRevisionService {}');

        // Extra files including sensitive, compiled cache and logs that should be excluded
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . '.env', 'DB_PASSWORD=secret');
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'installed.lock', 'lock');
        File::makeDirectory($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache', 0777, true, true);
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php', '<?php return [];');
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'routes-v7.php', '<?php return [];');
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '.gitignore', "*\n!.gitignore\n");
        File::makeDirectory($this->fixtureDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage', 0777, true, true);
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'stale.txt', 'stale');
        File::makeDirectory($this->fixtureDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true, true);
        File::put($this->fixtureDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'laravel.log', 'log entry');

        // Public demo asset
        $solarAssetsDir = $this->fixtureDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'solar-assets';
        File::makeDirectory($solarAssetsDir, 0777, true, true);
        File::put($solarAssetsDir . DIRECTORY_SEPARATOR . 'demo.jpg', 'image bytes');
    }

    public function test_validate_source_directory_throws_when_directory_missing(): void
    {
        $service = new CustomerPackageSyncService();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source customer-site directory not found');

        $service->validateSourceDirectory('/non/existent/path/' . uniqid());
    }

    public function test_validate_source_directory_throws_when_required_marker_missing(): void
    {
        $service = new CustomerPackageSyncService();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required customer-site file: artisan');

        $service->validateSourceDirectory($this->fixtureDir);
    }

    public function test_resolve_version_increments_patch_and_respects_minimum(): void
    {
        $service = new CustomerPackageSyncService();

        // When no package exists: defaults to 1.1.1
        $this->assertSame('1.1.1', $service->resolveVersion(null));

        // When package 1.1.0 exists: next is 1.1.1
        CustomerInstallationPackage::create([
            'version'     => '1.1.0',
            'arvan_key'   => '',
            'local_path'  => '/tmp/test.zip',
            'sha256'      => 'hash',
            'size_bytes'  => 1000,
            'released_at' => now(),
            'is_active'   => true,
        ]);
        $this->assertSame('1.1.1', $service->resolveVersion(null));

        // When explicit version passed: respected
        $this->assertSame('1.2.0', $service->resolveVersion('1.2.0'));
    }

    public function test_should_exclude_path_filters_sensitive_and_temporary_files(): void
    {
        $service = new CustomerPackageSyncService();

        $this->assertTrue($service->shouldExcludePath('.env'));
        $this->assertTrue($service->shouldExcludePath('.env.backup'));
        $this->assertTrue($service->shouldExcludePath('installed.lock'));
        $this->assertTrue($service->shouldExcludePath('.git/HEAD'));
        $this->assertTrue($service->shouldExcludePath('.github/workflows/ci.yml'));
        $this->assertTrue($service->shouldExcludePath('bootstrap/cache/config.php'));
        $this->assertTrue($service->shouldExcludePath('bootstrap/cache/routes-v7.php'));
        $this->assertTrue($service->shouldExcludePath('bootstrap/cache/services.php'));
        $this->assertTrue($service->shouldExcludePath('bootstrap/cache/events.php'));
        $this->assertTrue($service->shouldExcludePath('bootstrap/cache/packages.php'));
        $this->assertFalse($service->shouldExcludePath('bootstrap/cache/.gitignore'));
        $this->assertTrue($service->shouldExcludePath('public/storage'));
        $this->assertTrue($service->shouldExcludePath('public/storage/somefile.png'));
        $this->assertTrue($service->shouldExcludePath('storage/logs/laravel.log'));
        $this->assertTrue($service->shouldExcludePath('storage/framework/cache/data/abc'));
        $this->assertTrue($service->shouldExcludePath('public/uploads/file.png'));
        $this->assertTrue($service->shouldExcludePath('dump.sql'));
        $this->assertTrue($service->shouldExcludePath('backup.tar'));

        $this->assertFalse($service->shouldExcludePath('artisan'));
        $this->assertFalse($service->shouldExcludePath('composer.json'));
        $this->assertFalse($service->shouldExcludePath('app/Services/ThemeCanvas/ThemeInstanceRevisionService.php'));
        $this->assertFalse($service->shouldExcludePath('storage/app/public/solar-assets/demo.jpg'));
        $this->assertFalse($service->shouldExcludePath('public/build/manifest.json'));
    }

    public function test_sync_from_deployed_site_creates_and_activates_package_atomically(): void
    {
        $this->createValidSiteFixture();

        // Create an existing older active package
        $oldPackage = CustomerInstallationPackage::create([
            'version'     => '1.1.0',
            'arvan_key'   => '',
            'local_path'  => $this->storagePackagesDir . DIRECTORY_SEPARATOR . 'customer-site-v1.1.0.zip',
            'sha256'      => 'oldhash',
            'size_bytes'  => 5000,
            'released_at' => now()->subDay(),
            'is_active'   => true,
        ]);

        $service = new CustomerPackageSyncService();
        $newPackage = $service->syncFromDeployedSite(
            sourcePath: $this->fixtureDir,
            commit: '50ade55b9edfde8dcc2e52421d1dc3052b44baf9',
            explicitVersion: '1.1.1'
        );

        $this->assertSame('1.1.1', $newPackage->version);
        $this->assertTrue($newPackage->is_active);
        $this->assertFileExists($newPackage->local_path);
        $this->assertGreaterThan(0, $newPackage->size_bytes);
        $this->assertNotEmpty($newPackage->sha256);
        $this->assertStringContainsString('50ade55b', $newPackage->release_notes);

        // Previous package must now be inactive
        $oldPackage->refresh();
        $this->assertFalse($oldPackage->is_active);

        // Verify content inside the created zip
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($newPackage->local_path));
        $this->assertNotFalse($zip->locateName('artisan'));
        $this->assertNotFalse($zip->locateName('app/Services/ThemeCanvas/ThemeInstanceRevisionService.php'));
        $this->assertNotFalse($zip->locateName('storage/app/public/solar-assets/demo.jpg'));
        // Sensitive and cached files must NOT be present
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('installed.lock'));
        $this->assertFalse($zip->locateName('bootstrap/cache/config.php'));
        $this->assertFalse($zip->locateName('bootstrap/cache/routes-v7.php'));
        $this->assertFalse($zip->locateName('public/storage'));
        $this->assertFalse($zip->locateName('public/storage/stale.txt'));
        $zip->close();
    }

    public function test_verify_archive_integrity_rejects_bootstrap_cache_and_public_storage(): void
    {
        $service = new CustomerPackageSyncService();
        $invalidZipPath = $this->storagePackagesDir . DIRECTORY_SEPARATOR . 'invalid-test.zip';

        // 1. Test rejection of bootstrap/cache/*.php
        $zip = new ZipArchive();
        $zip->open($invalidZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('artisan', str_repeat('a', 1500));
        $zip->addFromString('app/Services/ThemeCanvas/ThemeInstanceRevisionService.php', '<?php');
        $zip->addFromString('bootstrap/cache/config.php', '<?php return [];');
        $zip->close();

        try {
            $service->verifyArchiveIntegrity($invalidZipPath, minSizeBytes: 100);
            $this->fail('Expected RuntimeException for bootstrap/cache/*.php was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bootstrap/cache/*.php', $e->getMessage());
        }

        // 2. Test rejection of public/storage
        $zip = new ZipArchive();
        $zip->open($invalidZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('artisan', '#!/usr/bin/env php');
        $zip->addFromString('app/Services/ThemeCanvas/ThemeInstanceRevisionService.php', '<?php');
        $zip->addFromString('public/storage/leak.png', 'binary data');
        $zip->close();

        try {
            $service->verifyArchiveIntegrity($invalidZipPath, minSizeBytes: 100);
            $this->fail('Expected RuntimeException for public/storage was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('public/storage', $e->getMessage());
        }
    }

    public function test_sync_from_deployed_site_is_idempotent_for_same_commit(): void
    {
        $this->createValidSiteFixture();

        $service = new CustomerPackageSyncService();
        $first = $service->syncFromDeployedSite(
            sourcePath: $this->fixtureDir,
            commit: 'abcdef1234567890',
            explicitVersion: '1.1.1'
        );

        // Second run with same commit
        $second = $service->syncFromDeployedSite(
            sourcePath: $this->fixtureDir,
            commit: 'abcdef1234567890'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CustomerInstallationPackage::count());
    }

    public function test_artisan_command_sync_from_release(): void
    {
        $this->createValidSiteFixture();

        $this->artisan('customer-install:sync-from-release', [
            '--source'          => $this->fixtureDir,
            '--commit'          => '50ade55b9edf',
            '--package-version' => '1.1.2',
        ])
            ->expectsOutputToContain('Customer installation package synchronized successfully')
            ->assertExitCode(0);

        $active = CustomerInstallationPackage::active();
        $this->assertNotNull($active);
        $this->assertSame('1.1.2', $active->version);
    }
}
