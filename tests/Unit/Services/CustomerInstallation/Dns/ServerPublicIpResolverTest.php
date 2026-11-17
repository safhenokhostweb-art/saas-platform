<?php

namespace Tests\Unit\Services\CustomerInstallation\Dns;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Installation 50 pointed its A record at the server's outbound NAT address
 * (`curl api.ipify.org` = 205.252.135.x) instead of eth0 87.107.12.81.
 */
class ServerPublicIpResolverTest extends TestCase
{
    use RefreshDatabase;

    private function server(string $host): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'ssh', 'host' => $host, 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
    }

    private function context(CustomerRemoteServer $server, FakeRemoteDriver $driver): InstallationContext
    {
        $payload = [
            'company' => 'C', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'solar.example.ir', 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => 'solar.example.ir', 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver);
    }

    public function test_the_ssh_host_ip_wins_and_the_outbound_address_is_never_asked_for(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('api.ipify.org', 0, '205.252.135.188');
        $resolver = new ServerPublicIpResolver(new FakeDnsResolver());

        $this->assertSame('87.107.12.81', $resolver->forContext($this->context($this->server('87.107.12.81'), $driver)));
        foreach ($driver->getCommandLog() as $command) {
            $this->assertStringNotContainsString('ipify', $command);
            $this->assertStringNotContainsString('ifconfig.me', $command);
        }
    }

    public function test_a_hostname_is_resolved_through_public_dns(): void
    {
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set('box1.example.ir', DnsLookup::resolved(['10.0.0.5', '93.115.1.2']));

        $this->assertSame('93.115.1.2', (new ServerPublicIpResolver($dns))->forServer($this->server('box1.example.ir')));
        $this->assertSame('93.115.1.2', (new ServerPublicIpResolver($dns))->forServer($this->server('https://box1.example.ir:2083')));
    }

    public function test_private_host_falls_back_to_a_public_address_bound_on_the_server(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('ip -4 -o addr show scope global', 0, "10.1.2.3/24\n87.107.12.81/24\n");

        $ip = (new ServerPublicIpResolver(new FakeDnsResolver()))->forContext($this->context($this->server('10.1.2.3'), $driver));

        $this->assertSame('87.107.12.81', $ip);
    }

    public function test_no_public_address_anywhere_yields_null(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('ip -4 -o addr show scope global', 0, "192.168.1.10/24\n");

        $resolver = new ServerPublicIpResolver(new FakeDnsResolver());

        $this->assertNull($resolver->forContext($this->context($this->server('localhost'), $driver)));
        $this->assertNull($resolver->forServer(null));
        $this->assertFalse(ServerPublicIpResolver::isPublicIpv4('127.0.0.1'));
        $this->assertTrue(ServerPublicIpResolver::isPublicIpv4('87.107.12.81'));
    }
}
