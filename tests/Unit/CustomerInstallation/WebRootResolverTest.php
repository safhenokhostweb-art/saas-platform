<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use App\Services\CustomerInstallation\WebRootResolver;
use Tests\TestCase;

/**
 * Phase 14-F1 — WebRootResolver is the single place that knows how a web
 * root path is derived per hosting type, so InstallationContext::webRoot()
 * (and therefore every Stage that calls it) never needs to branch on driver
 * type itself.
 */
class WebRootResolverTest extends TestCase
{
    private function resolver(): WebRootResolver
    {
        return new WebRootResolver();
    }

    public function test_vps_returns_var_www_path(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'ssh', 'host' => 'vps.example.test', 'port' => 22,
            'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $driver = new SshRemoteDriver($server, new CredentialVault());

        $path = $this->resolver()->resolve($driver, 'customer', $server);

        $this->assertSame('/var/www/customer', $path);
    }

    public function test_cpanel_returns_home_public_html_path(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'johndoe', 'auth_method' => 'password',
        ]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $path = $this->resolver()->resolve($driver, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html', $path);
    }

    public function test_cpanel_without_username_falls_back_to_slug_namespaced_path(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test', 'auth_method' => 'password',
        ]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $path = $this->resolver()->resolve($driver, 'customer', $server);

        $this->assertSame('/home/customer/public_html', $path);
    }

    public function test_explicit_server_path_overrides_every_default(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test', 'username' => 'johndoe',
            'auth_method' => 'password', 'path' => '/home/johndoe/public_html/shop.example.com/',
        ]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $path = $this->resolver()->resolve($driver, 'customer', $server);

        // Trailing slash stripped, and this wins over the cPanel default.
        $this->assertSame('/home/johndoe/public_html/shop.example.com', $path);
    }

    public function test_vps_explicit_path_override_also_honored(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'ssh', 'host' => 'vps.example.test', 'username' => 'deploy',
            'auth_method' => 'key', 'path' => '/srv/apps/custom-path',
        ]);
        $driver = new SshRemoteDriver($server, new CredentialVault());

        $path = $this->resolver()->resolve($driver, 'customer', $server);

        $this->assertSame('/srv/apps/custom-path', $path);
    }

    public function test_fake_driver_preserves_previous_var_www_behavior(): void
    {
        $driver = new FakeRemoteDriver();

        $path = $this->resolver()->resolve($driver, 'customer', null);

        $this->assertSame('/var/www/customer', $path);
    }

    public function test_no_server_record_falls_back_to_var_www(): void
    {
        // e.g. a driver type the resolver doesn't special-case and no
        // CustomerRemoteServer available — must still return a deterministic
        // path, not throw.
        $driver = new FakeRemoteDriver();

        $path = $this->resolver()->resolve($driver, 'customer', null);

        $this->assertSame('/var/www/customer', $path);
    }
}
