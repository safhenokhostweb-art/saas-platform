<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance;

use Illuminate\Support\Facades\Cache;

class PluginResourceBudget
{
    private const BUDGET_PREFIX  = 'plugin.resource.budget2';
    private const WINDOW_SECONDS = 3600;

    public function __construct(
        private int $maxJobsPerHour      = 60,
        private int $maxHttpPerHour      = 120,
        private int $maxStorageMbPerDay  = 100
    ) {}

    public function consumeJob(string $moduleId): bool
    {
        if (!$this->canConsumeJob($moduleId)) {
            return false;
        }
        $this->increment($moduleId, 'jobs');
        return true;
    }

    public function consumeHttp(string $moduleId): bool
    {
        if (!$this->canConsumeHttp($moduleId)) {
            return false;
        }
        $this->increment($moduleId, 'http');
        return true;
    }

    public function canConsumeJob(string $moduleId): bool
    {
        return $this->windowCount($moduleId, 'jobs') < $this->maxJobsPerHour;
    }

    public function canConsumeHttp(string $moduleId): bool
    {
        return $this->windowCount($moduleId, 'http') < $this->maxHttpPerHour;
    }

    public function remainingJobs(string $moduleId): int
    {
        return max(0, $this->maxJobsPerHour - $this->windowCount($moduleId, 'jobs'));
    }

    public function remainingHttp(string $moduleId): int
    {
        return max(0, $this->maxHttpPerHour - $this->windowCount($moduleId, 'http'));
    }

    public function utilizationPct(string $moduleId): float
    {
        $jobPct  = $this->windowCount($moduleId, 'jobs') / max(1, $this->maxJobsPerHour) * 100;
        $httpPct = $this->windowCount($moduleId, 'http') / max(1, $this->maxHttpPerHour) * 100;
        return round(max($jobPct, $httpPct), 1);
    }

    public function snapshot(string $moduleId): array
    {
        return [
            'jobs_used'  => $this->windowCount($moduleId, 'jobs'),
            'jobs_max'   => $this->maxJobsPerHour,
            'http_used'  => $this->windowCount($moduleId, 'http'),
            'http_max'   => $this->maxHttpPerHour,
            'utilization_pct' => $this->utilizationPct($moduleId),
        ];
    }

    private function windowCount(string $moduleId, string $resource): int
    {
        $window = floor(time() / self::WINDOW_SECONDS);
        return (int) Cache::get(self::BUDGET_PREFIX . ".{$moduleId}.{$resource}.{$window}", 0);
    }

    private function increment(string $moduleId, string $resource): void
    {
        $window = floor(time() / self::WINDOW_SECONDS);
        $key    = self::BUDGET_PREFIX . ".{$moduleId}.{$resource}.{$window}";
        Cache::increment($key);
        Cache::put($key, (int) Cache::get($key, 1), self::WINDOW_SECONDS + 60);
    }
}
