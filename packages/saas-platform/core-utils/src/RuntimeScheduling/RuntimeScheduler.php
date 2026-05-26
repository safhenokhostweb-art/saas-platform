<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeScheduler
{
    public function __construct(
        private TenantPriorityScheduler      $priorityScheduler,
        private DeferredExecutionCoordinator $deferredCoordinator,
        private GovernanceAwareDispatcher    $dispatcher,
        private RuntimeWorkloadClassifier    $classifier,
        private TelemetryManager             $telemetry
    ) {}

    public function dispatch(int $tenantId, string $jobClass, array $payload = []): DispatchResult
    {
        return $this->dispatcher->dispatch($tenantId, $jobClass, $payload);
    }

    public function dispatchBatch(int $tenantId, array $jobs): array
    {
        return $this->dispatcher->dispatchMany($tenantId, $jobs);
    }

    public function drainDeferred(int $limit = 20): array
    {
        $drained = $this->deferredCoordinator->drain($limit);

        if (!empty($drained)) {
            $this->telemetry->info('scheduling', 'scheduling.deferred.drained', [
                'count' => count($drained),
            ]);
        }

        return $drained;
    }

    public function schedulerStatus(): array
    {
        return [
            'deferred_count'  => $this->deferredCoordinator->pendingCount(),
            'deferred_pending' => $this->deferredCoordinator->pending(),
        ];
    }

    public function workloadClass(string $jobClass): string
    {
        return $this->classifier->classify($jobClass);
    }

    public function computePriority(int $tenantId, string $jobClass): int
    {
        return $this->priorityScheduler->computePriority($tenantId, $jobClass);
    }
}
