<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\InfraServer;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Services\Infrastructure\ServerCapacityService;
use App\Services\Infrastructure\ServerResourceProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-11-19 (P1): the capacity guard uses the TARGET server's real figures,
 * never invents specs, treats overcommit as an error, and counts quotas held
 * by sites and by installations still in flight.
 */
class ServerCapacityServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    /** @param array<string, int|float>|null $figures */
    private function fakeProbe(?array $figures): void
    {
        $this->app->instance(ServerResourceProbe::class, new class($figures) extends ServerResourceProbe {
            public function __construct(private ?array $figures) {}
            public function probe(CustomerRemoteServer $server): ?array { return $this->figures; }
        });
    }

    private function service(): ServerCapacityService
    {
        return $this->app->make(ServerCapacityService::class);
    }

    private function server(string $type = 'ssh', string $host = '10.9.0.1'): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label' => 'Node ' . $host, 'host' => $host, 'username' => 'deploy',
            'port' => 22, 'type' => $type, 'is_active' => true,
        ]);
    }

    /** 4 GB / 2 cores / 30 GB with 20 GB free — like production's VPS, but with room. */
    private function vps(): array
    {
        return ['ram_total_mb' => 4096, 'ram_available_mb' => 2048, 'cpu_cores' => 2.0, 'disk_total_gb' => 30.0, 'disk_free_gb' => 20.0];
    }

    private function siteWithLimit(CustomerRemoteServer $server, int $ramMb, float $diskGb, float $cpu): Site
    {
        $customer = Customer::create(['name' => 'C' . random_int(1, 99999), 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'S', 'domain' => 's' . random_int(1, 999999) . '.test', 'is_active' => true]);
        CustomerInstallation::create([
            'site_id' => $site->id, 'remote_server_id' => $server->id, 'installer_user_id' => 1, 'status' => 'complete',
            'customer_company' => 'S', 'customer_email' => '', 'customer_domain' => $site->domain,
        ]);
        SiteResourceLimit::create([
            'site_id' => $site->id, 'memory_limit_bytes' => $ramMb * 1024 * 1024,
            'disk_hard_limit_bytes' => (int) ($diskGb * self::GB), 'storage_limit_bytes' => (int) ($diskGb * self::GB),
            'disk_soft_limit_bytes' => (int) ($diskGb * 0.7 * self::GB), 'cpu_cores_limit' => $cpu,
        ]);

        return $site;
    }

    public function test_figures_come_from_the_target_server_probe(): void
    {
        $this->fakeProbe($this->vps());
        $capacity = $this->service()->getCapacity($this->server(), fresh: true);

        $this->assertSame('live_probe', $capacity['metrics_source']);
        $this->assertSame(4096, $capacity['ram']['total_mb']);
        $this->assertSame(2048, $capacity['ram']['free_mb']);
        $this->assertSame(20.0, $capacity['disk']['free_gb']);
        $this->assertSame(2.0, $capacity['physical_cpu_cores']);
    }

    public function test_unknown_specs_are_never_invented_and_fail_closed(): void
    {
        $this->fakeProbe(null);
        $server = $this->server();

        $capacity = $this->service()->getCapacity($server, fresh: true);
        $this->assertFalse($capacity['specs_known']);
        $this->assertNull($capacity['ram']['total_mb'], 'No more invented 16 GB.');

        $check = $this->service()->validateAllocation($server, 1024, 5.0);
        $this->assertFalse($check['valid']);
        $this->assertNotEmpty($check['errors']);
    }

    public function test_registered_specs_are_used_when_the_probe_fails(): void
    {
        $this->fakeProbe(null);
        $server = $this->server();
        InfraServer::create(['name' => 'n', 'ip_address' => $server->host, 'cpu_cores' => 4, 'ram_gb' => 8, 'disk_gb' => 100, 'is_active' => true]);

        $check = $this->service()->validateAllocation($server, 1024, 5.0);

        $this->assertTrue($check['valid'], implode(' | ', $check['errors']));
        $this->assertSame('registered_specs', $check['capacity']['metrics_source']);
        $this->assertNotEmpty($check['warnings'], 'The operator is told the figures are not live.');
    }

    public function test_a_reasonable_allocation_passes(): void
    {
        $this->fakeProbe($this->vps());

        $check = $this->service()->validateAllocation($this->server(), 1024, 5.0, 3.5, 1.0);

        $this->assertTrue($check['valid'], implode(' | ', $check['errors']));
    }

    public function test_disk_beyond_live_free_space_is_an_error(): void
    {
        $this->fakeProbe(['disk_free_gb' => 3.0] + $this->vps());

        $check = $this->service()->validateAllocation($this->server(), 1024, 5.0);

        $this->assertFalse($check['valid']);
    }

    public function test_ram_overcommit_is_an_error_not_a_warning(): void
    {
        $this->fakeProbe($this->vps());
        $server = $this->server();
        $this->siteWithLimit($server, 6144, 2.0, 0.5); // 6 GB already committed on a 4 GB host

        $check = $this->service()->validateAllocation($server, 4096, 2.0, null, 0.5);

        $this->assertFalse($check['valid']);
        $this->assertStringContainsString('رم', implode(' ', $check['errors']));
    }

    public function test_allocated_disk_quotas_are_subtracted(): void
    {
        $this->fakeProbe(['disk_free_gb' => 25.0] + $this->vps());
        $server = $this->server();
        $this->siteWithLimit($server, 512, 20.0, 0.5); // 30 GB disk -> 24 GB allocatable

        $check = $this->service()->validateAllocation($server, 512, 5.0, null, 0.5);

        $this->assertFalse($check['valid'], '20 + 5 GB of hard quotas exceed the 24 GB allocatable.');
    }

    public function test_cpu_pool_overcommit_is_an_error(): void
    {
        $this->fakeProbe($this->vps());
        $server = $this->server();
        $this->siteWithLimit($server, 256, 1.0, 7.0); // 7 of 8 allowed core-shares on 2 cores

        $check = $this->service()->validateAllocation($server, 256, 1.0, null, 2.0);

        $this->assertFalse($check['valid']);
    }

    public function test_in_flight_installations_reserve_capacity(): void
    {
        $this->fakeProbe(['disk_free_gb' => 25.0] + $this->vps());
        $server = $this->server();
        CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'status' => 'pending',
            'customer_company' => 'Pending', 'customer_email' => '', 'customer_domain' => 'pending.test',
            'request_payload' => ['ram_mb' => 1024, 'disk_gb' => 20.0, 'cpu_cores' => 1.0],
        ]);

        $capacity = $this->service()->getCapacity($server, fresh: true);
        $this->assertSame(1, $capacity['reservations_count']);
        $this->assertSame(20.0, $capacity['reserved_disk_gb']);

        $check = $this->service()->validateAllocation($server, 1024, 5.0, null, 1.0);
        $this->assertFalse($check['valid'], 'The pending installation holds 20 of the 24 allocatable GB.');
    }

    public function test_panel_managed_hosting_warns_instead_of_guessing(): void
    {
        $this->fakeProbe(null);
        $server = $this->server('cpanel', 'box.example.net');

        $check = $this->service()->validateAllocation($server, 1024, 5.0);

        $this->assertTrue($check['valid']);
        $this->assertNotEmpty($check['warnings']);
    }

    public function test_probe_parses_linux_output(): void
    {
        $output = "MemTotal:        4009216 kB\nMemFree:          780000 kB\nMemAvailable:    2236416 kB\n__NPROC__\n2\n__DF__\n/dev/vda1 31444092 27262124 3145728  90% /\n";

        $parsed = ServerResourceProbe::parse($output);

        $this->assertSame(3915, $parsed['ram_total_mb']);
        $this->assertSame(2184, $parsed['ram_available_mb']);
        $this->assertSame(2.0, $parsed['cpu_cores']);
        $this->assertSame(3.0, $parsed['disk_free_gb']);
        $this->assertNull(ServerResourceProbe::parse('not linux'));
    }

    public function test_dynamically_scales_site_resources(): void
    {
        $customer = Customer::create(['name' => 'Tester', 'mobile' => '09121111111']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Scaling Test Site', 'domain' => 'scale-test.test', 'is_active' => true]);

        $limit = $this->service()->scaleSiteResources($site, [
            'plan_name' => 'pro', 'ram_mb' => 2048, 'disk_gb' => 20.0, 'soft_disk_gb' => 14.0, 'cpu_cores' => 2.0, 'bandwidth_gb' => 50.0,
        ]);

        $this->assertInstanceOf(SiteResourceLimit::class, $limit);
        $this->assertSame('pro', $limit->plan_name);
        $this->assertSame(2048 * 1024 * 1024, (int) $limit->memory_limit_bytes);
        $this->assertSame(20 * self::GB, (int) $limit->disk_hard_limit_bytes);
        $this->assertSame((int) (14 * self::GB), (int) $limit->disk_soft_limit_bytes);
        $this->assertSame(2.0, (float) $limit->cpu_cores_limit);
    }
}
