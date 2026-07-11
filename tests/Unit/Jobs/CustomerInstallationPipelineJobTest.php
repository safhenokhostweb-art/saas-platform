<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class CustomerInstallationPipelineJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(?int $siteId = null): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'pending',
            'site_id'           => $siteId,
            'customer_company'  => 'Lock Test Co',
            'customer_email'    => 'lock@example.com',
            'customer_domain'   => 'lock.example.com',
            'request_payload'   => [
                'company' => 'Lock Test Co', 'owner_name' => 'Owner',
                'email' => 'lock@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'lock.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_retry_after_is_safely_above_job_timeout(): void
    {
        $job = new CustomerInstallationPipelineJob(1);

        $retryAfter = config('queue.connections.customer-install.retry_after');

        $this->assertGreaterThan($job->timeout, $retryAfter);
        $this->assertSame(3900, $retryAfter);
    }

    public function test_other_queue_connections_are_unchanged(): void
    {
        $this->assertSame(90, config('queue.connections.redis.retry_after'));
        $this->assertSame(7200, config('queue.connections.restore.retry_after'));
        $this->assertSame(1800, config('queue.connections.customer-upload.retry_after'));
    }

    public function test_lock_key_matches_for_same_site_id(): void
    {
        $a = $this->makeInstallation(siteId: 42);
        $b = $this->makeInstallation(siteId: 42);

        $keyA = (new CustomerInstallationPipelineJob($a->id))->middleware()[0]->key;
        $keyB = (new CustomerInstallationPipelineJob($b->id))->middleware()[0]->key;

        $this->assertSame($keyA, $keyB);
        $this->assertSame('cie-site-42', $keyA);
    }

    public function test_lock_key_differs_for_different_site_id(): void
    {
        $a = $this->makeInstallation(siteId: 42);
        $b = $this->makeInstallation(siteId: 43);

        $keyA = (new CustomerInstallationPipelineJob($a->id))->middleware()[0]->key;
        $keyB = (new CustomerInstallationPipelineJob($b->id))->middleware()[0]->key;

        $this->assertNotSame($keyA, $keyB);
    }

    public function test_fresh_installs_with_null_site_id_get_independent_lock_keys(): void
    {
        $a = $this->makeInstallation(siteId: null);
        $b = $this->makeInstallation(siteId: null);

        $keyA = (new CustomerInstallationPipelineJob($a->id))->middleware()[0]->key;
        $keyB = (new CustomerInstallationPipelineJob($b->id))->middleware()[0]->key;

        $this->assertNotSame($keyA, $keyB);
        $this->assertSame("cie-install-{$a->id}", $keyA);
        $this->assertSame("cie-install-{$b->id}", $keyB);
    }

    public function test_without_overlapping_release_and_expire_settings(): void
    {
        $installation = $this->makeInstallation(siteId: 99);
        $middleware = (new CustomerInstallationPipelineJob($installation->id))->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame(30, $middleware->releaseAfter);
        $this->assertSame(3700, $middleware->expiresAfter);
    }

    public function test_tries_is_removed_in_favor_of_a_time_boxed_retry_until(): void
    {
        $job = new CustomerInstallationPipelineJob(1);

        $this->assertFalse(property_exists($job, 'tries'));
        $this->assertEqualsWithDelta(
            now()->addMinutes(90)->getTimestamp(),
            $job->retryUntil()->getTimestamp(),
            5
        );
    }
}
