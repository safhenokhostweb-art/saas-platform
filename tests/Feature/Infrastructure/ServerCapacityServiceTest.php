<?php

namespace Tests\Feature\Infrastructure;

use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Services\Infrastructure\ServerCapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerCapacityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServerCapacityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServerCapacityService::class);
    }

    public function test_inspects_server_capacity_and_computes_buffers(): void
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Production Edge 01',
            'host' => '127.0.0.1',
            'username' => 'deploy',
            'port' => 22,
            'type' => 'ssh',
            'is_active' => true,
        ]);

        $capacity = $this->service->getCapacity($server, fresh: true);

        $this->assertIsArray($capacity);
        $this->assertSame($server->id, $capacity['server']['id']);
        $this->assertArrayHasKey('ram', $capacity);
        $this->assertArrayHasKey('disk', $capacity);
        $this->assertArrayHasKey('has_headroom', $capacity);
        $this->assertArrayHasKey('sites_count', $capacity);
        $this->assertGreaterThan(0, $capacity['ram']['total_mb']);
        $this->assertGreaterThan(0, $capacity['disk']['total_gb']);
    }

    public function test_validates_allocation_against_buffer(): void
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Test Node',
            'host' => '127.0.0.1',
            'username' => 'deploy',
            'port' => 22,
            'type' => 'ssh',
            'is_active' => true,
        ]);

        // Reasonable allocation passes
        $checkValid = $this->service->validateAllocation($server, 1024, 10.0);
        $this->assertTrue($checkValid['valid']);

        // Extreme allocation fails
        $checkExtreme = $this->service->validateAllocation($server, 1024 * 1024, 100000.0);
        $this->assertFalse($checkExtreme['valid']);
        $this->assertNotEmpty($checkExtreme['errors']);
    }

    public function test_dynamically_scales_site_resources(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Tester',
            'mobile' => '09121111111',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'Scaling Test Site',
            'domain' => 'scale-test.test',
            'is_active' => true,
        ]);

        $limit = $this->service->scaleSiteResources($site, [
            'plan_name' => 'pro',
            'ram_mb' => 2048,
            'disk_gb' => 20.0,
            'soft_disk_gb' => 14.0,
            'cpu_cores' => 2.0,
            'bandwidth_gb' => 50.0,
        ]);

        $this->assertInstanceOf(SiteResourceLimit::class, $limit);
        $this->assertSame('pro', $limit->plan_name);
        $this->assertSame(2048 * 1024 * 1024, (int) $limit->memory_limit_bytes);
        $this->assertSame(20 * 1024 * 1024 * 1024, (int) $limit->disk_hard_limit_bytes);
        $this->assertSame((int) (14 * 1024 * 1024 * 1024), (int) $limit->disk_soft_limit_bytes);
        $this->assertSame(2.0, (float) $limit->cpu_cores_limit);
    }
}
