<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\ThemeFleetUpdate;
use App\Models\User;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\CustomerPackageUpgradePipeline;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPackageUpgradeWebRootResolutionTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;
    private string $packageZip;
    private string $packageSha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/upgrade_webroot_test_' . uniqid();
        mkdir($this->fixtureDir . '/site_root/app', 0777, true);
        file_put_contents($this->fixtureDir . '/site_root/artisan', '#!/usr/bin/env php');
        file_put_contents($this->fixtureDir . '/site_root/.env', "APP_NAME=CustSite\nAPP_URL=https://safhenokhost.ir\nDB_DATABASE=safhenokhost_db\n");

        $zip = new \ZipArchive();
        $this->packageZip = $this->fixtureDir . '/package.zip';
        $zip->open($this->packageZip, \ZipArchive::CREATE);
        $zip->addFromString('version.json', json_encode(['customer_core' => '1.1.7']));
        $zip->close();
        $this->packageSha = hash_file('sha256', $this->packageZip);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function makeFakeConnection(string $validRoot, string $packageSha): RemoteConnectionInterface
    {
        return new class($validRoot, $packageSha) implements RemoteConnectionInterface {
            public array $executedCommands = [];
            public array $uploadedFiles = [];

            public function __construct(private string $validRoot, private string $packageSha) {}

            public function connect(): void {}
            public function testConnection(): ConnectionTestResult {
                return new ConnectionTestResult(true, 10, 'OK');
            }

            public function execute(string $command, array $env = []): CommandResult {
                $this->executedCommands[] = $command;

                if (str_contains($command, 'test -d') && str_contains($command, 'test -f')) {
                    $escaped = escapeshellarg($this->validRoot);
                    if (str_contains($command, $escaped) || str_contains($command, $this->validRoot)) {
                        return new CommandResult(0, 'exists', '', 5);
                    }
                    return new CommandResult(0, 'absent', '', 5);
                }

                if (str_contains($command, 'test -d')) {
                    $escaped = escapeshellarg($this->validRoot);
                    if (str_contains($command, $escaped) || str_contains($command, $this->validRoot)) {
                        return new CommandResult(0, 'exists', '', 5);
                    }
                    return new CommandResult(0, 'absent', '', 5);
                }

                if (str_contains($command, 'grep -E')) {
                    return new CommandResult(0, "APP_URL=https://safhenokhost.ir\nDB_DATABASE=safhenokhost_db\n", '', 5);
                }

                if (str_contains($command, 'sha256sum') || str_contains($command, 'shasum')) {
                    return new CommandResult(0, $this->packageSha . "  file.zip", '', 5);
                }

                if (str_contains($command, 'php artisan --version')) {
                    return new CommandResult(0, 'Laravel Framework 12.0.0', '', 5);
                }

                return new CommandResult(0, 'OK', '', 5);
            }

            public function upload(string $localPath, string $remotePath, ?callable $progress = null): void {
                $this->uploadedFiles[$remotePath] = $localPath;
            }

            public function download(string $remotePath, string $localPath): void {}
            public function fileExists(string $remotePath): bool { return true; }
            public function diskFree(string $path): int { return 1000000000; }
            public function disconnect(): void {}
        };
    }

    public function test_upgrade_resolves_cust_site_webroot_even_if_request_payload_has_wrong_domain_path(): void
    {
        $customer = Customer::create(['name' => 'Safhe Nokhost', 'mobile' => '09120000099']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'سایت صفحه نخست',
            'domain'      => 'safhenokhost.ir',
            'db_name'     => 'safhenokhost_db',
        ]);

        $server = CustomerRemoteServer::create([
            'label'               => 'VPS-Production',
            'type'                => 'ssh',
            'is_platform_managed' => true,
            'host'                => '1.2.3.4',
            'port'                => 22,
            'username'            => 'root',
        ]);

        $installation = CustomerInstallation::create([
            'site_id'          => $site->id,
            'remote_server_id' => $server->id,
            'installer_user_id'=> 1,
            'status'           => 'complete',
            'customer_company' => 'Safhe Nokhost',
            'customer_email'   => 'admin@safhenokhost.ir',
            'customer_domain'  => 'safhenokhost.ir',
            'package_version'  => '1.1.0',
            // Onboarding filled this with unverified domain path:
            'request_payload'  => ['web_root_path' => '/var/www/safhenokhost.ir'],
        ]);

        // Real webroot recorded in installation log:
        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'EXTRACT_PACKAGE',
            'event'           => 'completed',
            'message'         => 'Extracted files',
            'output'          => ['web_root' => '/var/www/cust_site_' . $installation->id],
        ]);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.7',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => $this->packageSha,
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        $realWebRoot = '/var/www/cust_site_' . $installation->id;
        $fakeConn = $this->makeFakeConnection($realWebRoot, $this->packageSha);

        $mockConnService = $this->createMock(RemoteConnectionService::class);
        $mockConnService->method('buildDriver')->willReturn($fakeConn);
        $this->app->instance(RemoteConnectionService::class, $mockConnService);

        $pipeline = app(CustomerPackageUpgradePipeline::class);
        $fleetUpdate = $pipeline->upgradeSite($site, $pkg, null);

        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $fleetUpdate->status);
        $this->assertSame('1.1.0', $fleetUpdate->component_versions['customer_core']['from']);
        $this->assertSame('1.1.7', $fleetUpdate->component_versions['customer_core']['to']);

        // Assert that the pipeline executed commands against /var/www/cust_site_* and NOT /var/www/safhenokhost.ir
        $executed = implode("\n", $fakeConn->executedCommands);
        $this->assertStringContainsString("cd " . escapeshellarg($realWebRoot) . " && php artisan down", $executed);
        $this->assertStringNotContainsString("safhenokhost.ir", $executed);
    }

    public function test_fleet_updates_routes_via_get_redirect_safely_without_405(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $resPackage = $this->actingAs($admin)->get('/admin/onboarding/fleet-updates/apply-package');
        $resPackage->assertRedirect(route('admin.onboarding.create', ['tab' => 'fleet-updates']));

        $resApply = $this->actingAs($admin)->get('/admin/onboarding/fleet-updates/apply');
        $resApply->assertRedirect(route('admin.onboarding.create', ['tab' => 'fleet-updates']));

        $resRollback = $this->actingAs($admin)->get('/admin/onboarding/fleet-updates/rollback');
        $resRollback->assertRedirect(route('admin.onboarding.create', ['tab' => 'fleet-updates']));
    }
}
