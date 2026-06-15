<?php

namespace Tests\Feature\Infrastructure;

use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Support\Facades\Http;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;
use Tests\TestCase;

class ScanRuntimeAnomaliesCommandTest extends TestCase
{
    public function test_exits_zero_when_no_anomalies(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        $this->artisan('runtime:scan-anomalies')->assertExitCode(0);
    }

    public function test_exits_zero_when_only_warning_anomalies(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([
            ['code' => 'QUEUE_SLOW', 'severity' => 'warning', 'message' => 'slow queue', 'context' => []],
        ]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        $this->artisan('runtime:scan-anomalies')->assertExitCode(0);
    }

    public function test_exits_one_when_critical_anomaly(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([
            ['code' => 'TENANT_ISOLATION_BREACH', 'severity' => 'critical', 'message' => 'breach detected', 'context' => []],
        ]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        Http::fake(); // prevent real HTTP calls
        $this->artisan('runtime:scan-anomalies')->assertExitCode(1);
    }

    public function test_exits_one_when_high_anomaly(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([
            ['code' => 'QUEUE_STALL', 'severity' => 'high', 'message' => 'queue stalled', 'context' => ['queue' => 'default']],
        ]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        Http::fake();
        $this->artisan('runtime:scan-anomalies')->assertExitCode(1);
    }

    public function test_sends_slack_alert_for_critical_anomaly(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([
            ['code' => 'SECURITY_BREACH', 'severity' => 'critical', 'message' => 'suspicious access', 'context' => []],
        ]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        $slackCalled = false;
        $slack = $this->createMock(SlackAlertService::class);
        $slack->expects($this->once())->method('critical')
            ->with($this->stringContains('SECURITY_BREACH'), $this->anything())
            ->willReturnCallback(function () use (&$slackCalled) { $slackCalled = true; });
        $this->app->instance(SlackAlertService::class, $slack);

        $this->artisan('runtime:scan-anomalies')->assertExitCode(1);
        $this->assertTrue($slackCalled);
    }

    public function test_no_slack_for_warning_severity(): void
    {
        $engine = $this->createMock(RuntimeAnomalyEngine::class);
        $engine->method('scan')->willReturn([
            ['code' => 'MEMORY_HIGH', 'severity' => 'warning', 'message' => 'memory warning', 'context' => []],
        ]);
        $this->app->instance(RuntimeAnomalyEngine::class, $engine);

        $slack = $this->createMock(SlackAlertService::class);
        $slack->expects($this->never())->method('critical');
        $slack->expects($this->never())->method('high');
        $this->app->instance(SlackAlertService::class, $slack);

        $this->artisan('runtime:scan-anomalies')->assertExitCode(0);
    }
}
