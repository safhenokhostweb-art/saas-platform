<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

class TenantCapacityProfile
{
    private const DEFAULT_PROFILES = [
        'trial' => [
            'max_requests_per_minute' => 60,
            'max_queue_jobs_per_hour' => 100,
            'max_runtime_seconds_per_hour' => 300,
            'max_storage_mb' => 100,
            'max_concurrent_jobs' => 2,
            'priority' => 10,
        ],
        'starter' => [
            'max_requests_per_minute' => 300,
            'max_queue_jobs_per_hour' => 500,
            'max_runtime_seconds_per_hour' => 1800,
            'max_storage_mb' => 1024,
            'max_concurrent_jobs' => 5,
            'priority' => 30,
        ],
        'professional' => [
            'max_requests_per_minute' => 1200,
            'max_queue_jobs_per_hour' => 2000,
            'max_runtime_seconds_per_hour' => 7200,
            'max_storage_mb' => 10240,
            'max_concurrent_jobs' => 20,
            'priority' => 60,
        ],
        'enterprise' => [
            'max_requests_per_minute' => 6000,
            'max_queue_jobs_per_hour' => 10000,
            'max_runtime_seconds_per_hour' => 36000,
            'max_storage_mb' => 102400,
            'max_concurrent_jobs' => 100,
            'priority' => 100,
        ],
    ];

    public function forPlan(string $plan): array
    {
        return self::DEFAULT_PROFILES[$plan] ?? self::DEFAULT_PROFILES['starter'];
    }

    public function allPlans(): array
    {
        return array_keys(self::DEFAULT_PROFILES);
    }

    public function requestsPerMinute(string $plan): int
    {
        return $this->forPlan($plan)['max_requests_per_minute'];
    }

    public function queueJobsPerHour(string $plan): int
    {
        return $this->forPlan($plan)['max_queue_jobs_per_hour'];
    }

    public function runtimeSecondsPerHour(string $plan): int
    {
        return $this->forPlan($plan)['max_runtime_seconds_per_hour'];
    }

    public function storageMb(string $plan): int
    {
        return $this->forPlan($plan)['max_storage_mb'];
    }

    public function concurrentJobs(string $plan): int
    {
        return $this->forPlan($plan)['max_concurrent_jobs'];
    }

    public function priority(string $plan): int
    {
        return $this->forPlan($plan)['priority'];
    }

    public function isValidPlan(string $plan): bool
    {
        return isset(self::DEFAULT_PROFILES[$plan]);
    }
}
