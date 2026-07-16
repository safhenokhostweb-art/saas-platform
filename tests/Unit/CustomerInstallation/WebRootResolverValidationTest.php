<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\WebRootResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 15-E — Part 4: resolveForInstallation()'s non-blocking sanity check
 * (logPathSanityWarningIfNeeded()) — never changes the returned path, never
 * throws, only logs. Also re-confirms resolve() (CustomerDeletion's own,
 * unchanged path) never triggers this new probe at all.
 *
 * Uses FakeRemoteDriver rather than a real CpanelDeploymentDriver throughout:
 * the latter would attempt a genuine SSH connection on execute(), which this
 * suite must never do.
 */
class WebRootResolverValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver): array
    {
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('pw'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Validation Co',
            'customer_email'    => 'validation@example.com',
            'customer_domain'   => 'shop.johndoe.example.com',
            'request_payload'   => [
                'company' => 'Validation Co', 'owner_name' => 'Owner', 'email' => 'validation@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'shop.johndoe.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx     = new InstallationContext($installation, $request, $driver);

        return [$ctx, $server];
    }

    public function test_logs_a_warning_but_returns_the_path_unchanged_when_the_sanity_check_fails(): void
    {
        // No canned response for 'test -d' -> FakeRemoteDriver default
        // (exit 0, empty stdout) -> fails the sanity check -> Log::warning,
        // but the resolved path itself must still be returned unchanged.
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        [$ctx, $server] = $this->context($driver);
        $ctx->recordOutput('PROVISION_DOMAIN', ['document_root' => '/home/johndoe/public_html/shop']);

        Log::shouldReceive('warning')->once();

        $path = (new WebRootResolver())->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/shop', $path);
    }

    public function test_no_warning_logged_when_the_path_passes_the_sanity_check(): void
    {
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $driver->addResponse('test -d', 0, 'sane');
        [$ctx, $server] = $this->context($driver);
        $ctx->recordOutput('PROVISION_DOMAIN', ['document_root' => '/home/johndoe/public_html/shop']);

        Log::shouldReceive('warning')->never();

        $path = (new WebRootResolver())->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/shop', $path);
    }

    public function test_sanity_probe_failure_itself_never_throws(): void
    {
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $driver->willFail('ssh connection reset');
        [$ctx, $server] = $this->context($driver);
        $ctx->recordOutput('PROVISION_DOMAIN', ['document_root' => '/home/johndoe/public_html/shop']);

        $path = (new WebRootResolver())->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/shop', $path, 'A failed sanity probe must never affect the resolved path');
    }

    public function test_resolve_used_by_customer_deletion_never_triggers_the_sanity_probe(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'ssh', 'host' => 'vps.example.test', 'port' => 22,
            'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $driver = new SshRemoteDriver($server, new CredentialVault());

        Log::shouldReceive('warning')->never();

        $path = (new WebRootResolver())->resolve($driver, 'customer', $server);

        $this->assertSame('/var/www/customer', $path);
    }
}
