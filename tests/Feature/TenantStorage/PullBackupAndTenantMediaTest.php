<?php

namespace Tests\Feature\TenantStorage;

use App\Console\Commands\BackupSitesPullCommand;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\CustomerInstallation\Stages\ConfigureMediaStorageStage;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use App\Services\DevOps\TenantResourceGovernor;
use App\Services\ResourceGovernance\SiteResourceCalculatorService;
use App\Services\TenantMedia\TenantMediaEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesTenantHost;
use Tests\Feature\Onboarding\OnboardingFixtures;
use Tests\TestCase;

/**
 * Phase 2 (pull-model tenant DB backups) and Phase 4 (tenant media on
 * object storage, only for platform-managed servers, never with the backup key).
 */
class PullBackupAndTenantMediaTest extends TestCase
{
    use FakesTenantHost;
    use OnboardingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        config([
            'filesystems.disks.arvan_backup.key' => 'PLATFORM-BACKUP-KEY',
            'resource_governance.tenant_media' => [
                'enabled' => true, 'access_key' => 'MEDIA-ONLY-KEY', 'secret_key' => 'MEDIA-SECRET',
                'bucket' => 'safhenokhost-media', 'region' => 'ir-central1',
                'endpoint' => 'https://s3.ir-central1.arvanstorage.ir/', 'url' => null,
            ],
        ]);
    }

    /** @return array{0: Site, 1: CustomerInstallation, 2: CustomerRemoteServer} */
    private function installedSite(bool $platformManaged = true): array
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Prod', 'type' => 'fake', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'deploy',
            'auth_method' => 'key', 'is_platform_managed' => $platformManaged,
        ]);
        $customer = Customer::create(['name' => 'C', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Shop', 'domain' => 'shop-' . uniqid() . '.ir']);
        $payload = [
            'company' => 'Shop Co', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => $site->domain, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'complete',
            'customer_company' => 'Shop Co', 'customer_email' => '', 'customer_domain' => $site->domain,
            'request_payload' => $payload, 'site_id' => $site->id,
        ]);

        return [$site, $installation, $server];
    }

    private function bindDriver(FakeRemoteDriver $driver): void
    {
        $this->app->instance(RemoteConnectionService::class, new class($driver) extends RemoteConnectionService {
            public function __construct(private readonly FakeRemoteDriver $driver) {}
            public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
            {
                return $this->driver;
            }
        });
    }

    // ── Phase 4: tenant media .env ───────────────────────────────────────

    public function test_media_variables_use_the_site_prefix_and_never_the_backup_bucket(): void
    {
        $vars = app(TenantMediaEnvironment::class)->variables(42);

        $this->assertSame('sites/42/media', $vars['ARVAN_MEDIA_PREFIX']);
        $this->assertSame('sites/42/private', $vars['ARVAN_MEDIA_PRIVATE_PREFIX']);
        $this->assertSame('safhenokhost-media', $vars['ARVAN_MEDIA_BUCKET']);
        $this->assertSame('MEDIA-ONLY-KEY', $vars['ARVAN_ACCESS_KEY']);
        $this->assertSame('https://s3.ir-central1.arvanstorage.ir/safhenokhost-media', $vars['ARVAN_MEDIA_URL']);
        $this->assertSame('MEDIA_DISK', array_key_last($vars), 'the switch comes last, after its credentials');
        $this->assertArrayNotHasKey('ARVAN_BUCKET', $vars);
        $this->assertNotContains('safhenokhost-backups', $vars);
    }

    public function test_media_key_is_only_for_platform_managed_servers_and_never_the_backup_key(): void
    {
        $media = app(TenantMediaEnvironment::class);
        [, , $own] = $this->installedSite(true);
        [, , $customers] = $this->installedSite(false);

        $this->assertNull($media->ineligibility($own, 7));
        $this->assertSame(TenantMediaEnvironment::SKIP_NOT_PLATFORM_MANAGED, $media->ineligibility($customers, 7));
        $this->assertSame(TenantMediaEnvironment::SKIP_NO_SITE, $media->ineligibility($own, null));

        config(['resource_governance.tenant_media.access_key' => 'PLATFORM-BACKUP-KEY']);
        $this->assertSame(TenantMediaEnvironment::SKIP_BACKUP_KEY, $media->ineligibility($own, 7));

        config(['resource_governance.tenant_media.access_key' => '']);
        $this->assertSame(TenantMediaEnvironment::SKIP_INCOMPLETE, $media->ineligibility($own, 7));

        config(['resource_governance.tenant_media.enabled' => false]);
        $this->assertSame(TenantMediaEnvironment::SKIP_DISABLED, $media->ineligibility($own, 7));
    }

    public function test_configure_media_stage_writes_the_env_on_a_platform_server(): void
    {
        [$site, $installation] = $this->installedSite(true);
        $driver = new FakeRemoteDriver();
        $driver->addResponse('base64 -d', 0, 'env-set-ok');
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $driver);

        $result = app(ConfigureMediaStorageStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('object_storage', $result->output['status']);
        $this->assertSame("sites/{$site->id}/media", $result->output['prefix']);
        $this->assertSame('MEDIA_DISK', last($result->output['written']));
        $this->assertTrue(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'php artisan config:clear')));
    }

    public function test_configure_media_stage_keeps_a_customer_owned_server_on_local_disk(): void
    {
        [, $installation] = $this->installedSite(false);
        $driver = new FakeRemoteDriver();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $driver);

        $result = app(ConfigureMediaStorageStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('local_disk', $result->output['status']);
        $this->assertSame([], $driver->getCommandLog(), 'nothing is written to a customer server');
    }

    public function test_a_failed_env_write_never_switches_media_disk(): void
    {
        [$site, , $server] = $this->installedSite(true);
        $driver = new FakeRemoteDriver();
        $driver->addResponse('ARVAN_SECRET_KEY', 1, 'env-set-failed');
        $driver->addResponse('base64 -d', 0, 'env-set-ok');

        $result = app(TenantMediaEnvironment::class)->apply($driver, '/var/www/shop', $server, $site->id);

        $this->assertFalse($result['applied']);
        $this->assertSame(['ARVAN_ACCESS_KEY'], $result['written']);
        $this->assertFalse(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, "'^MEDIA_DISK='")));
    }

    public function test_generated_env_carries_no_object_storage_key_at_all(): void
    {
        config(['filesystems.disks.arvan_backup.key' => 'PLATFORM-BACKUP-KEY', 'filesystems.disks.arvan_backup.bucket' => 'safhenokhost-backups']);
        [, $installation] = $this->installedSite(true);
        $driver = new FakeRemoteDriver();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $driver);

        app(GenerateEnvironmentStage::class)->execute($ctx);

        $write = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, 'base64 -d >'));
        preg_match('/^echo ["\']([^"\']+)["\']/', (string) $write, $m);
        $env = base64_decode($m[1] ?? '');
        $this->assertStringContainsString('APP_URL=', $env);
        $this->assertStringNotContainsString('ARVAN_', $env);
        $this->assertStringNotContainsString('PLATFORM-BACKUP-KEY', $env);
        $this->assertStringNotContainsString('safhenokhost-backups', $env);
    }

    public function test_server_ownership_flag_is_saved_from_the_admin_form(): void
    {
        [, , $server] = $this->installedSite(false);

        $this->actingAs($this->admin())->put(route('admin.infrastructure.customer-deployment.servers.update', $server), [
            'label' => 'Prod', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'deploy', 'is_platform_managed' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($server->fresh()->is_platform_managed);

        $this->actingAs($this->admin())->put(route('admin.infrastructure.customer-deployment.servers.update', $server), [
            'label' => 'Prod', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'deploy', 'is_platform_managed' => '0',
        ]);
        $this->assertFalse($server->fresh()->is_platform_managed);
    }

    // ── Phase 4: quota ───────────────────────────────────────────────────

    public function test_disk_audit_adds_object_storage_media_to_the_local_du(): void
    {
        $site = $this->makeDevOpsSite();
        $driver = $this->fakeTenantHost('/var/www/cust_acme_12');
        $driver->addResponse('du -skx', 0, "1024\n/dev/vda1 100 40 60 40% /\n"); // 1 MiB local
        $this->bindTenantTarget($site, $driver, '/var/www/cust_acme_12');
        $calculator = $this->partialMock(SiteResourceCalculatorService::class);
        $calculator->shouldReceive('remoteMediaBytes')->andReturn(5 * 1048576);

        $result = app(TenantResourceGovernor::class)->audit($site);

        $this->assertSame(1048576, $result['local_bytes']);
        $this->assertSame(5 * 1048576, $result['remote_media_bytes']);
        $this->assertSame(6 * 1048576, $result['disk_usage_bytes']);
        $this->assertSame(6 * 1048576, (int) \App\Models\SiteResourceUsage::where('site_id', $site->id)->value('disk_usage_bytes'));
    }

    // ── Phase 2: pull-model backups ──────────────────────────────────────

    private function backupDriver(string $remoteFile, bool $dumpFails = false): FakeRemoteDriver
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep -E '^(DB_HOST", 0, "DB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=cust_shop\nDB_USERNAME=cust_shop\nDB_PASSWORD=\"p@ss word\"");
        $driver->addResponse('mysqldump', 0, $dumpFails ? 'dump-failed' : "size=2048\nsha256=" . hash('sha256', 'fake-content'));
        $driver->putFile($remoteFile);

        return $driver;
    }

    public function test_pull_backup_dumps_the_sites_own_database_into_the_private_bucket(): void
    {
        Storage::fake('arvan_backup');
        Carbon::setTestNow('2026-11-20 03:15:00');
        [$site] = $this->installedSite(false); // backups cover every server, platform-owned or not
        $remoteFile = "/tmp/sp_pull_{$site->id}_20261120_031500.sql.gz";
        $driver = $this->backupDriver($remoteFile);
        $this->bindDriver($driver);
        $prefix = BackupSitesPullCommand::remotePrefix($site->id);
        foreach (['a', 'b'] as $i => $old) {
            Storage::disk('arvan_backup')->put("{$prefix}/old-{$old}.sql.gz", 'old');
            touch(Storage::disk('arvan_backup')->path("{$prefix}/old-{$old}.sql.gz"), time() - 86400 * (3 - $i));
        }

        $this->artisan('db:backup-sites', ['--retention' => 2])->assertSuccessful();

        Storage::disk('arvan_backup')->assertExists("{$prefix}/site-{$site->id}_20261120_031500.sql.gz");
        Storage::disk('arvan_backup')->assertMissing("{$prefix}/old-a.sql.gz"); // pruned to 2
        Storage::disk('arvan_backup')->assertExists("{$prefix}/old-b.sql.gz");
        $dump = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, 'mysqldump'));
        $this->assertStringContainsString(escapeshellarg('cust_shop'), $dump);
        $this->assertStringContainsString('MYSQL_PWD=' . escapeshellarg('p@ss word'), $dump);
        $this->assertTrue(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'rm -f') && str_contains($c, 'sp_pull_')));
        $this->assertSame('success', InfraBackupRun::query()->where('site_id', $site->id)->value('status'));
        $this->assertSame([], glob(storage_path('app/tmp-site-backups/*')) ?: [], 'local temp copy removed');
    }

    public function test_a_failed_dump_uploads_nothing_and_is_reported(): void
    {
        Storage::fake('arvan_backup');
        Carbon::setTestNow('2026-11-20 03:15:00');
        [$site] = $this->installedSite();
        $this->bindDriver($this->backupDriver("/tmp/sp_pull_{$site->id}_20261120_031500.sql.gz", dumpFails: true));

        $this->artisan('db:backup-sites')->assertFailed();

        $this->assertSame([], Storage::disk('arvan_backup')->allFiles());
        $this->assertSame('failed', InfraBackupRun::query()->where('site_id', $site->id)->value('status'));
    }

    public function test_a_corrupted_download_is_never_uploaded(): void
    {
        Storage::fake('arvan_backup');
        Carbon::setTestNow('2026-11-20 03:15:00');
        [$site] = $this->installedSite();
        $driver = $this->backupDriver("/tmp/sp_pull_{$site->id}_20261120_031500.sql.gz");
        $driver->addResponse('mysqldump', 0, "size=2048\nsha256=" . str_repeat('0', 64)); // server hash ≠ downloaded bytes
        $this->bindDriver($driver);

        $this->artisan('db:backup-sites')->assertFailed();

        $this->assertSame([], Storage::disk('arvan_backup')->allFiles());
    }
}
