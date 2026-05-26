<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\TenantPressureAnalyzer;
use Illuminate\Support\Facades\Cache;

class TenantPriorityScheduler
{
    private const SCHEDULE_PREFIX = 'scheduling.tenant.';
    private const SCHEDULE_TTL   = 300;

    public function __construct(
        private TenantQuotaManager     $quotaManager,
        private TenantPressureAnalyzer $pressureAnalyzer,
        private RuntimeWorkloadClassifier $classifier
    ) {}

    public function computePriority(int $tenantId, string $jobClass): int
    {
        $tenantPriority  = $this->quotaManager->getPriority($tenantId);
        $workloadClass   = $this->classifier->classify($jobClass);
        $workloadPriority = $this->classifier->priority($workloadClass);
        $pressureScore   = $this->pressureAnalyzer->pressureScore($tenantId);

        // Critical path jobs always get full priority regardless of tenant pressure
        if ($this->classifier->isCriticalPath($workloadClass)) {
            return 100;
        }

        // High-pressure tenants get lower scheduling priority
        $pressurePenalty = (int) ($pressureScore * 0.3);

        return max(1, (int) round(($tenantPriority * 0.4) + ($workloadPriority * 0.6)) - $pressurePenalty);
    }

    public function schedule(int $tenantId, string $jobClass, array $payload = []): ScheduledWork
    {
        $priority      = $this->computePriority($tenantId, $jobClass);
        $workloadClass = $this->classifier->classify($jobClass);
        $queue         = $this->classifier->queueForClass($workloadClass);
        $deferred      = $this->shouldDefer($tenantId, $workloadClass);

        $work = new ScheduledWork(
            tenantId:      $tenantId,
            jobClass:      $jobClass,
            workloadClass: $workloadClass,
            priority:      $priority,
            queue:         $queue,
            deferred:      $deferred,
            payload:       $payload
        );

        $this->recordSchedule($tenantId, $work);
        return $work;
    }

    public function shouldDefer(int $tenantId, string $workloadClass): bool
    {
        if ($this->classifier->isCriticalPath($workloadClass)) {
            return false;
        }

        if (!$this->classifier->isDeferred($workloadClass)) {
            return false;
        }

        // Defer non-critical work from high-pressure tenants
        return $this->pressureAnalyzer->isHighPressure($tenantId);
    }

    private function recordSchedule(int $tenantId, ScheduledWork $work): void
    {
        $key  = self::SCHEDULE_PREFIX . $tenantId . '.last';
        Cache::put($key, $work->toArray(), self::SCHEDULE_TTL);
    }
}
