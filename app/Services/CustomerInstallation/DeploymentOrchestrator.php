<?php

namespace App\Services\CustomerInstallation;

use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\Contracts\DeploymentJobInterface;
use App\Services\CustomerInstallation\Dto\DeploymentResult;
use App\Services\CustomerInstallation\Enums\DriverType;
use InvalidArgumentException;
use Throwable;

/**
 * @deprecated Phase 14-B1 — architecture scaffolding with no production
 * caller (grep-verified: only DeploymentOrchestratorTest constructs this
 * class). Nothing in the app creates a DeploymentTarget row yet, so this
 * ssh→cpanel→sftp fallback chain can never run against real data. Left
 * intact (not removed/refactored) per the 14-B1 audit — see docs/architecture
 * Phase 14-B/14-B1 reports before adding a caller or extending this class.
 * TODO: either wire a real caller in a later phase or remove this class.
 */
final class DeploymentOrchestrator
{
    private const CHAIN = [
        ['key' => 'ssh', 'type' => DriverType::SSH],
        ['key' => 'cpanel', 'type' => DriverType::CPANEL],
        ['key' => 'file_upload', 'type' => DriverType::SFTP],
    ];

    private readonly int $maxRetries;
    private readonly int $baseDelay;

    public function __construct(
        private readonly RemoteConnectionService $connections,
        ?int $maxRetries = null,
        private readonly bool $exponentialBackoff = true,
        ?int $baseDelaySeconds = null,
    ) {
        $this->maxRetries = max(0, min(20, $maxRetries ?? 3));
        $this->baseDelay = max(1, $baseDelaySeconds ?? 30);
    }

    public function execute(DeploymentJobInterface $job): DeploymentResult
    {
        $attempt = $job->attempt();
        if ($attempt < 1) {
            throw new InvalidArgumentException('Deployment queue attempts must start at one.');
        }

        $logs = [];
        $errors = [];
        $targets = $job->targets();
        $this->log($logs, 'deployment.started', [
            'deployment_id' => $job->deploymentId(),
            'attempt' => $attempt,
            'chain' => array_column(self::CHAIN, 'key'),
        ]);

        foreach (self::CHAIN as $index => $strategy) {
            $key = $strategy['key'];
            $target = $targets[$key] ?? ($key === 'file_upload' ? ($targets['sftp'] ?? null) : null);
            $next = self::CHAIN[$index + 1]['key'] ?? null;

            if (!$target instanceof DeploymentTarget) {
                $errors[] = $this->failure($key, 'TARGET_UNAVAILABLE', 'No target supplied.', $next);
                $this->log($logs, 'driver.skipped', end($errors));
                continue;
            }

            if ($target->driverType() !== $strategy['type']) {
                $errors[] = $this->failure($key, 'TARGET_DRIVER_MISMATCH', 'Target driver does not match strategy.', $next);
                $this->log($logs, 'driver.rejected', end($errors));
                continue;
            }

            $driver = null;
            try {
                $this->log($logs, 'driver.started', ['driver' => $key, 'target_id' => $target->getKey()]);
                $driver = $this->connections->buildDriverForTarget($target);
                $driver->connect();
                $output = $job->execute($driver);
                $this->log($logs, 'deployment.succeeded', ['driver' => $key, 'attempt' => $attempt]);

                return new DeploymentResult(true, $job->deploymentId(), $key, $output, $logs, $errors, false, null);
            } catch (Throwable $exception) {
                $errors[] = $this->failure($key, 'DRIVER_EXECUTION_FAILED', $exception->getMessage(), $next, $exception);
                $this->log($logs, 'driver.failed', end($errors));
            } finally {
                try {
                    $driver?->disconnect();
                } catch (Throwable $exception) {
                    $this->log($logs, 'driver.disconnect_failed', ['driver' => $key, 'message' => $exception->getMessage()]);
                }
            }
        }

        $retry = $attempt <= $this->maxRetries;
        $delay = $retry ? $this->delay($attempt) : null;
        if ($retry) {
            try {
                $job->release($delay);
                $this->log($logs, 'deployment.retry_scheduled', ['next_attempt' => $attempt + 1, 'delay_seconds' => $delay]);
            } catch (Throwable $exception) {
                $retry = false;
                $errors[] = $this->failure('queue', 'RETRY_SCHEDULING_FAILED', $exception->getMessage(), null, $exception);
                $this->log($logs, 'deployment.retry_failed', end($errors));
            }
        } else {
            $this->log($logs, 'deployment.exhausted', ['attempt' => $attempt, 'max_retries' => $this->maxRetries]);
        }

        return new DeploymentResult(false, $job->deploymentId(), null, null, $logs, $errors, $retry, $retry ? $delay : null);
    }

    private function delay(int $attempt): int
    {
        return $this->exponentialBackoff
            ? min(3600, $this->baseDelay * (2 ** ($attempt - 1)))
            : $this->baseDelay;
    }

    private function failure(string $driver, string $code, string $message, ?string $next, ?Throwable $error = null): array
    {
        return [
            'driver' => $driver,
            'code' => $code,
            'message' => $message,
            'exception' => $error ? $error::class : null,
            'next_driver' => $next,
        ];
    }

    private function log(array &$logs, string $event, array $context): void
    {
        $logs[] = ['sequence' => count($logs) + 1, 'event' => $event, 'context' => $context];
    }
}
