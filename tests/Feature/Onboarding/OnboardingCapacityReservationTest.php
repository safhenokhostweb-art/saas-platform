<?php

namespace Tests\Feature\Onboarding;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\Infrastructure\ServerResourceProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P1 (2026-11-19): the capacity check and the installation INSERT are one
 * reservation. A second submission on the same server sees the first one's
 * pending quota, and a submission that arrives while another holds the
 * server lock is refused instead of racing it.
 */
class OnboardingCapacityReservationTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\CustomerInstallationPackage::create([
            'version' => '1.1.0', 'arvan_key' => 'packages/customer-site-v1.1.0.zip',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1024, 'is_active' => true, 'released_at' => now(),
        ]);
        Queue::fake();
        $this->platformRootDomain();
        // A small node: 30 GB disk -> 24 GB of hard quotas allocatable.
        $this->app->instance(ServerResourceProbe::class, new class extends ServerResourceProbe {
            public function __construct() {}
            public function probe(CustomerRemoteServer $server): ?array
            {
                return ['ram_total_mb' => 8192, 'ram_available_mb' => 6000, 'cpu_cores' => 4.0, 'disk_total_gb' => 30.0, 'disk_free_gb' => 28.0];
            }
        });
    }

    public function test_a_second_submission_sees_the_first_ones_reservation(): void
    {
        $server = $this->server();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.onboarding.store'), $this->submission($server, [
            'mobile' => '09351110001', 'customer_name' => 'اول', 'domain' => 'first',
            'disk_gb' => 15, 'soft_disk_gb' => 10, 'ram_mb' => 1024, 'cpu_cores' => 1,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerInstallation::query()->count());

        $this->actingAs($admin)->post(route('admin.onboarding.store'), $this->submission($server, [
            'mobile' => '09351110002', 'customer_name' => 'دوم', 'domain' => 'second',
            'disk_gb' => 15, 'soft_disk_gb' => 10, 'ram_mb' => 1024, 'cpu_cores' => 1,
        ]))->assertSessionHasErrors('ram_mb');

        $this->assertSame(1, CustomerInstallation::query()->count(), '15 GB reserved + 15 GB requested exceed 24 GB.');
    }

    public function test_a_submission_while_the_server_lock_is_held_is_refused(): void
    {
        $server = $this->server();
        $lock = Cache::lock("onboarding:server-capacity:{$server->id}", 60);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($server, [
                'mobile' => '09351110003', 'customer_name' => 'سوم', 'domain' => 'third',
            ]))->assertSessionHasErrors('remote_server_id');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, CustomerInstallation::query()->count());
    }
}
