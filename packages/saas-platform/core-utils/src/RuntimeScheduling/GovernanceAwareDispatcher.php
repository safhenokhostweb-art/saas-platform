<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\DistributedQueue\TenantQueueAffinityResolver;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class GovernanceAwareDispatcher
{
    public function __construct(
        private TenantPriorityScheduler    $scheduler,
        private DeferredExecutionCoordinator $deferredCoordinator,
        private RuntimeThrottleManager     $throttleManager,
        private TenantQueueAffinityResolver $affinityResolver,
        private GovernanceContext          $governanceContext,
        private TelemetryManager           $telemetry
    ) {}

    public function dispatch(int $tenantId, string $jobClass, array $payload = []): DispatchResult
    {
        $work = $this->scheduler->schedule($tenantId, $jobClass, $payload);

        // Respect governance state
        if ($this->governanceContext->getState() === GovernanceContext::STATE_EMERGENCY) {
            if ($work->workloadClass !== RuntimeWorkloadClassifier::CLASS_CRITICAL) {
                return DispatchResult::rejected('platform_emergency');
            }
        }

        // Apply throttle factor
        $throttleFactor = $this->throttleManager->effectiveFactor($tenantId);
        if ($throttleFactor < 0.2 && $work->workloadClass === RuntimeWorkloadClassifier::CLASS_DEFERRED) {
            $this->deferredCoordinator->defer($work);
            return DispatchResult::deferred($work->queue, $work->priority);
        }

        if ($work->deferred) {
            $this->deferredCoordinator->defer($work);
            return DispatchResult::deferred($work->queue, $work->priority);
        }

        // Resolve sharded queue
        $resolvedQueue = $this->affinityResolver->resolveShardedQueue($tenantId, $work->queue);

        $this->telemetry->info('scheduling', 'scheduling.dispatched', [
            'tenant_id'     => $tenantId,
            'job_class'     => $jobClass,
            'queue'         => $resolvedQueue,
            'priority'      => $work->priority,
            'throttle_factor' => $throttleFactor,
        ]);

        return DispatchResult::dispatched($resolvedQueue, $work->priority, $throttleFactor);
    }

    public function dispatchMany(int $tenantId, array $jobs): array
    {
        return array_map(
            fn($job) => $this->dispatch($tenantId, $job['class'], $job['payload'] ?? []),
            $jobs
        );
    }
}
