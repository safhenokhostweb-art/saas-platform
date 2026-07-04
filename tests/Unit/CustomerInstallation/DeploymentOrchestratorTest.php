<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\DeploymentJobInterface;
use App\Services\CustomerInstallation\DeploymentOrchestrator;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeploymentOrchestratorTest extends TestCase
{
    public function test_strict_fallback_stops_after_file_upload_success(): void
    {
        [$targets, $drivers, $connections] = $this->dependencies();
        $order = [];
        $job = new OrchestratorTestJob($targets, function (DeploymentDriverInterface $driver) use (&$order): string {
            $order[] = $driver->driverType()->value;
            if ($driver->driverType() !== DriverType::SFTP) {
                throw new RuntimeException('unavailable');
            }

            return 'deployed';
        });

        $result = (new DeploymentOrchestrator($connections))->execute($job);

        self::assertTrue($result->success);
        self::assertSame('file_upload', $result->driver);
        self::assertSame(['ssh', 'cpanel', 'sftp'], $order);
        self::assertCount(2, $result->errors);
        self::assertFalse($result->retryScheduled);
    }

    public function test_all_failures_schedule_only_bounded_exponential_retries(): void
    {
        [$targets, , $connections] = $this->dependencies();
        $job = new OrchestratorTestJob($targets, fn () => throw new RuntimeException('failed'), 2);
        $result = (new DeploymentOrchestrator($connections, maxRetries: 2, baseDelaySeconds: 10))->execute($job);

        self::assertFalse($result->success);
        self::assertTrue($result->retryScheduled);
        self::assertSame(20, $result->retryDelaySeconds);
        self::assertSame([20], $job->releaseDelays);
        self::assertSame('cpanel', $result->errors[0]['next_driver']);
        self::assertNull($result->errors[2]['next_driver']);

        $finalJob = new OrchestratorTestJob($targets, fn () => throw new RuntimeException('failed'), 3);
        $final = (new DeploymentOrchestrator($connections, maxRetries: 2))->execute($finalJob);
        self::assertFalse($final->retryScheduled);
        self::assertSame([], $finalJob->releaseDelays);
    }

    private function dependencies(): array
    {
        $targets = [];
        $drivers = [];
        foreach (['ssh' => DriverType::SSH, 'cpanel' => DriverType::CPANEL, 'file_upload' => DriverType::SFTP] as $key => $type) {
            $targets[$key] = new DeploymentTarget(['connection_driver' => $type->value]);
            $drivers[$type->value] = $this->createConfiguredMock(DeploymentDriverInterface::class, ['driverType' => $type]);
        }

        $connections = $this->createMock(RemoteConnectionService::class);
        $connections->method('buildDriverForTarget')->willReturnCallback(
            fn (DeploymentTarget $target) => $drivers[$target->connection_driver],
        );

        return [$targets, $drivers, $connections];
    }
}

final class OrchestratorTestJob implements DeploymentJobInterface
{
    public array $releaseDelays = [];

    public function __construct(
        private readonly array $deploymentTargets,
        private readonly Closure $operation,
        private readonly int $queueAttempt = 1,
    ) {}

    public function deploymentId(): string { return 'deployment-test'; }
    public function attempt(): int { return $this->queueAttempt; }
    public function targets(): array { return $this->deploymentTargets; }
    public function execute(DeploymentDriverInterface $driver): mixed { return ($this->operation)($driver); }
    public function release(int $delaySeconds): void { $this->releaseDelays[] = $delaySeconds; }
}
