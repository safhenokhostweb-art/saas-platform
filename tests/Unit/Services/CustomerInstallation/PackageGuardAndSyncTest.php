<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallationPackage;
use App\Services\CustomerInstallation\CustomerReleasePackageService;
use App\Services\CustomerInstallation\InstallationTargetGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageGuardAndSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_installation_package_detects_stale_versions(): void
    {
        $v100 = new CustomerInstallationPackage(['version' => '1.0.0']);
        $v101 = new CustomerInstallationPackage(['version' => '1.0.1']);
        $v110 = new CustomerInstallationPackage(['version' => '1.1.0']);
        $v200 = new CustomerInstallationPackage(['version' => '2.0.0']);

        $this->assertTrue($v100->isStale());
        $this->assertTrue($v101->isStale());
        $this->assertFalse($v110->isStale());
        $this->assertFalse($v200->isStale());
    }

    public function test_installation_target_guard_flags_missing_and_stale_packages(): void
    {
        $guard = app(InstallationTargetGuard::class);

        // 1. No package active
        $this->assertNotNull($guard->packageError());
        $this->assertStringContainsString('هیچ بسته نصب فعالی', $guard->packageError());

        // 2. Stale package active (1.0.1)
        CustomerInstallationPackage::create([
            'version'     => '1.0.1',
            'arvan_key'   => '',
            'sha256'      => str_repeat('a', 64),
            'size_bytes'  => 1024,
            'released_at' => now(),
            'is_active'   => true,
        ]);

        $this->assertNotNull($guard->packageError());
        $this->assertStringContainsString('قدیمی است', $guard->packageError());

        // 3. Modern valid package active (1.1.0)
        CustomerInstallationPackage::create([
            'version'     => '1.1.0',
            'arvan_key'   => '',
            'sha256'      => str_repeat('b', 64),
            'size_bytes'  => 2048,
            'released_at' => now(),
            'is_active'   => false,
        ])->activate();

        $this->assertNull($guard->packageError());
    }

    public function test_customer_release_package_service_rejects_stale_active_package(): void
    {
        $service = app(CustomerReleasePackageService::class);

        CustomerInstallationPackage::create([
            'version'     => '1.0.1',
            'arvan_key'   => '',
            'sha256'      => str_repeat('a', 64),
            'size_bytes'  => 1024,
            'released_at' => now(),
            'is_active'   => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stale');
        $service->latestVersion();
    }

    public function test_sync_install_packages_command_scans_and_syncs_packages(): void
    {
        $tmpDir = sys_get_temp_dir() . '/cie_packages_' . uniqid();
        mkdir($tmpDir, 0777, true);

        try {
            file_put_contents($tmpDir . '/customer-site-v1.0.1.zip', 'dummy zip 1.0.1');
            file_put_contents($tmpDir . '/customer-site-v1.1.0.zip', 'dummy zip 1.1.0');

            $this->artisan('customer-install:sync-packages', [
                '--path'     => $tmpDir,
                '--activate' => true,
            ])
            ->expectsOutputToContain('Activated latest valid package: v1.1.0')
            ->assertExitCode(0);

            $this->assertDatabaseHas('customer_installation_packages', [
                'version'   => '1.0.1',
                'is_active' => false,
            ]);

            $this->assertDatabaseHas('customer_installation_packages', [
                'version'   => '1.1.0',
                'is_active' => true,
            ]);

            $active = CustomerInstallationPackage::active();
            $this->assertNotNull($active);
            $this->assertSame('1.1.0', $active->version);
            $this->assertFalse($active->isStale());
        } finally {
            @unlink($tmpDir . '/customer-site-v1.0.1.zip');
            @unlink($tmpDir . '/customer-site-v1.1.0.zip');
            @rmdir($tmpDir);
        }
    }
}
