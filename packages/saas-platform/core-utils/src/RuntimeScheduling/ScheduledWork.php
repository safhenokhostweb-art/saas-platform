<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

class ScheduledWork
{
    public function __construct(
        public readonly int    $tenantId,
        public readonly string $jobClass,
        public readonly string $workloadClass,
        public readonly int    $priority,
        public readonly string $queue,
        public readonly bool   $deferred,
        public readonly array  $payload = []
    ) {}

    public function toArray(): array
    {
        return [
            'tenant_id'      => $this->tenantId,
            'job_class'      => $this->jobClass,
            'workload_class' => $this->workloadClass,
            'priority'       => $this->priority,
            'queue'          => $this->queue,
            'deferred'       => $this->deferred,
            'scheduled_at'   => now()->toIso8601String(),
        ];
    }
}
