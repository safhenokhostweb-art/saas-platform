<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\DirectAdminDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\DatabaseNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 14-F2 — naming logic lives only here, not duplicated in either Provisioner. */
class DatabaseNameResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(string $type, string $driverInstantiator, ?CustomerRemoteServer $server = null): array
    {
        $server ??= CustomerRemoteServer::create([
            'label' => 'Server', 'type' => $type, 'host' => 'host.example.test',
            'port' => 22, 'username' => 'deploy', 'auth_method' => $type === 'cpanel' ? 'password' : 'key',
            'password_encrypted' => $type === 'cpanel' ? (new CredentialVault())->encrypt('pw') : null,
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Naming Co',
            'customer_email'    => 'naming@example.com',
            'customer_domain'   => 'naming.example.com',
            'request_payload'   => [
                'company' => 'Naming Co', 'owner_name' => 'Owner', 'email' => 'naming@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'naming.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = $driverInstantiator === 'cpanel'
            ? new CpanelDeploymentDriver($server, new CredentialVault())
            : new FakeRemoteDriver();

        return [new InstallationContext($installation, $request, $driver), $server];
    }

    public function test_vps_naming_is_unchanged_pattern(): void
    {
        [$ctx] = $this->context('fake', 'fake');

        $names = (new DatabaseNameResolver())->resolve($ctx, null);

        $this->assertMatchesRegularExpression('/^cust_[a-zA-Z0-9]{6}_\d+$/', $names->database);
        $this->assertMatchesRegularExpression('/^u_[a-zA-Z0-9]{8}$/', $names->username);
    }

    public function test_cpanel_names_are_prefixed_with_account_username(): void
    {
        [$ctx, $server] = $this->context('cpanel', 'cpanel');
        $server->username = 'johndoe';

        $names = (new DatabaseNameResolver())->resolve($ctx, $server);

        $this->assertStringStartsWith('johndoe_', $names->database);
        $this->assertStringStartsWith('johndoe_', $names->username);
    }

    public function test_cpanel_names_never_exceed_mysql_user_length_limit(): void
    {
        [$ctx, $server] = $this->context('cpanel', 'cpanel');
        // A username at cPanel's own historical max length (16) to check
        // the resolver still leaves room for a usable random suffix.
        $server->username = 'a_very_long_user'; // 16 chars

        $names = (new DatabaseNameResolver())->resolve($ctx, $server);

        $this->assertLessThanOrEqual(32, strlen($names->database));
        $this->assertLessThanOrEqual(32, strlen($names->username));
        // Still has a non-trivial random suffix, not just the bare prefix.
        $this->assertGreaterThan(strlen('a_very_long_user_'), strlen($names->database));
    }

    public function test_cpanel_without_a_server_record_falls_back_to_a_safe_prefix(): void
    {
        [$ctx] = $this->context('cpanel', 'cpanel');

        $names = (new DatabaseNameResolver())->resolve($ctx, null);

        $this->assertStringStartsWith('cpuser_', $names->database);
    }

    public function test_directadmin_uses_the_same_prefixed_scheme_as_cpanel(): void
    {
        // DirectAdmin has no live implementation yet, but driverType() is a
        // real (non-stub) method on the architecture-stub driver, so this
        // exercises the resolver's DIRECTADMIN match arm for real rather
        // than asserting nothing.
        $target = new CustomerRemoteServer([
            'type' => 'directadmin', 'host' => 'da.example.test', 'port' => 2222, 'username' => 'janedoe',
        ]);
        $driver = new DirectAdminDeploymentDriver($target, new CredentialVault());

        [$ctx, $server] = $this->context('directadmin', 'fake');
        $server->username = 'janedoe';
        // Swap in the DirectAdmin driver so driverType() reports DIRECTADMIN
        // (the FakeRemoteDriver built by context() reports FAKE).
        $ctx->connection = $driver;

        $names = (new DatabaseNameResolver())->resolve($ctx, $server);

        $this->assertStringStartsWith('janedoe_', $names->database);
        $this->assertStringStartsWith('janedoe_', $names->username);
    }
}
