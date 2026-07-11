<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 13-O6 — closes two gaps flagged in the Phase 13-O4 audit:
 * CustomerInstallationRollbackJob had no overlap protection, and its
 * failed() called SlackAlertService::critical() with the wrong signature
 * (3 positional strings where the 3rd parameter is a typed array), which
 * threw a TypeError that the surrounding try/catch silently swallowed —
 * meaning the most critical alert in the pipeline ("manual intervention
 * required") was never actually delivered.
 */
class CustomerInstallationRollbackJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'failed',
            'failed_at'         => now(),
            'customer_company'  => 'Rollback Job Co',
            'customer_email'    => 'rollbackjob@example.com',
            'customer_domain'   => 'rollbackjob.example.com',
            'request_payload'   => [
                'company' => 'Rollback Job Co', 'owner_name' => 'Owner',
                'email' => 'rollbackjob@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'rollbackjob.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_middleware_includes_without_overlapping(): void
    {
        $job = new CustomerInstallationRollbackJob(1);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_lock_key_matches_for_the_same_installation(): void
    {
        $keyA = (new CustomerInstallationRollbackJob(42))->middleware()[0]->key;
        $keyB = (new CustomerInstallationRollbackJob(42))->middleware()[0]->key;

        $this->assertSame($keyA, $keyB);
        $this->assertSame('rollback-installation-42', $keyA);
    }

    public function test_lock_key_differs_for_different_installations(): void
    {
        $keyA = (new CustomerInstallationRollbackJob(42))->middleware()[0]->key;
        $keyB = (new CustomerInstallationRollbackJob(43))->middleware()[0]->key;

        $this->assertNotSame($keyA, $keyB);
    }

    public function test_release_and_expire_settings_are_safely_above_job_timeout(): void
    {
        $job = new CustomerInstallationRollbackJob(1);
        $middleware = $job->middleware()[0];

        $this->assertSame(30, $middleware->releaseAfter);
        $this->assertSame(1900, $middleware->expiresAfter);
        $this->assertGreaterThan($job->timeout, $middleware->expiresAfter);
    }

    public function test_failed_sends_a_real_slack_alert_with_correct_signature(): void
    {
        config(['services.slack_alert.webhook' => 'https://hooks.example.test/webhook']);
        Http::fake();

        $installation = $this->makeInstallation();

        $job = new CustomerInstallationRollbackJob($installation->id);
        $job->failed(new \RuntimeException('database restore failed during rollback'));

        $this->assertSame('rollback_failed', $installation->fresh()->status);

        Http::assertSent(function ($request) use ($installation) {
            $body = json_encode($request->data());

            return str_contains($request->url(), 'hooks.example.test')
                && str_contains($body, 'ROLLBACK FAILED')
                && str_contains($body, "Installation {$installation->id}")
                && str_contains($body, 'database restore failed during rollback');
        });
    }
}
